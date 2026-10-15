# Code quality audit — Penderie

> Session: linters, PHPStan and code quality.
> This document records the quality toolchain set up in this repository, what each tool
> observed, the findings it produced, what was fixed, and what is deliberately deferred
> (test coverage and end-to-end campaigns have since been executed — see [§8](#8-testability-and-reliability-next-sessions)
> and `coverage.md`).

- **Repository:** `tatrishvili/mmi24g15-Penderie-WR506`
- **Stack:** Symfony 7.4 / PHP 8.4 (API) · React 19 / TypeScript (front end)
- **How to reproduce everything:** `make help` (one command per check, see [§2](#2-how-to-run-everything))
- **Continuous evidence:** every check below runs on each push in GitHub Actions (`.github/workflows/ci.yml`)

---

## 1. Toolchain at a glance

| Tool | What it lets you observe | Why it is interesting here | Status |
| --- | --- | --- | --- |
| **PHPStan** (level 6) | Type errors, invalid calls, incoherences | Finds defects without executing the code | ✅ 0 errors — `src`, `tests`, `config`, `migrations`, `bin` |
| **PHP-CS-Fixer** | Coding standard, imports order, doc-blocks | Keeps the diff readable and the style uniform | ✅ 59/59 files clean |
| **Symfony linters** (`lint:container`, `lint:yaml`) | Container wiring, YAML syntax | Verifies the internal coherence of the project | ✅ container OK, 16 YAML files OK |
| **Composer audit** | Known vulnerabilities in dependencies | Introduces dependency security in the pipeline | ✅ 0 advisories (119 locked packages) |
| **Snyk** (https://snyk.io) | Security flaws and CI integration | Vulnerability monitoring beyond Composer's list | 🟡 workflow ready, needs the `SNYK_TOKEN` secret |
| **GitHub CodeQL** | Static security analysis of PHP/JS | Native GitHub security tab | ❌ unavailable: the repository is **private on the free plan** (API returns `404`, GitHub Advanced Security required) |
| **PHPUnit** | Unit and functional behaviour | Safety net for every refactoring | ✅ 150 tests / 637 assertions |
| **oxlint + tsc + Vitest** | Front-end lint, types, behaviour | Same guarantees on the React side | ✅ 46 tests in 11 files |
| **axe-core (jest-axe)** | Automatic accessibility rules | Makes A11Y regressions fail the build | ✅ 4 page-level tests |
| **Lighthouse** | Performance, accessibility, best practices, SEO | Assisted visual/performance inspection | ✅ 100 / 100 / 100 / 100 |
| **Manual inspection** | Responsibilities, duplication, organisation, readability | Develops a critical eye on what tools cannot see | ✅ [§3.3](#33-organisation-of-controllers-and-services), [§7](#7-user-experience-ux) |
| **Snyk / Lighthouse in CI** | Continuous security + budget regressions | A score drop now breaks the pipeline | ✅ `.github/workflows/snyk.yml`, CI job `Lighthouse` |

---

## 2. How to run everything

A root `Makefile` wraps the whole toolchain so nobody has to remember Docker/Composer/npm
syntax. It only requires Docker.

```bash
make help          # list every target
make up            # build and start the stack
make qa            # the full quality gate (what CI runs)
make stan          # PHPStan level 6
make cs            # PHP-CS-Fixer (dry run)  ·  make cs-fix to apply
make test          # PHPUnit                 ·  make coverage for the coverage report
make lint-symfony  # lint:container + lint:yaml
make audit         # Composer dependency audit
make fe-lint fe-typecheck fe-test fe-build   # front-end checks
make lighthouse    # Lighthouse audit + HTML report in frontend/lighthouse-report/
make fix           # auto-fix what can be auto-fixed (cs + prettier)
```

Guards are built in: a target that needs the stack prints
`The API container is not running. Start the stack with: make up` instead of a raw Docker error.

---

## 3. PHP code quality

### 3.1 PHPStan configuration

`backend/phpstan.dist.neon` (the `.dist` suffix means developers may drop a local,
git-ignored `backend/phpstan.neon` for personal overrides):

```neon
includes:
    - vendor/phpstan/phpstan-doctrine/extension.neon
    - vendor/phpstan/phpstan-doctrine/rules.neon
    - vendor/phpstan/phpstan-symfony/extension.neon
    - vendor/phpstan/phpstan-symfony/rules.neon

parameters:
    level: 6
    paths: [bin, config, migrations, src, tests]
    excludePaths: [tests/bootstrap.php]
    tmpDir: var/phpstan
    symfony:
        containerXmlPath: %currentWorkingDirectory%/var/cache/test/App_KernelTestDebugContainer.xml
```

- **Level 6** = missing type hints, wrong param/return types, always-true conditions.
- **`phpstan-symfony`** resolves the dependency-injection container: PHPStan now knows the
  *concrete* class behind every injected service, so it can prove that a guard is dead code.
- **`phpstan-doctrine`** understands entity mappings and repository generics.
- The **container XML** is dumped by `php bin/console cache:warmup --env=test`; the Makefile
  and CI both warm it before analysing, otherwise PHPStan fails with `XmlContainerNotExistsException`.
- The previous `ignoreErrors` rule for Doctrine `$id` properties became **unmatched** once
  `phpstan-doctrine` was installed, so it was removed: the extension reports it correctly now.

### 3.2 Errors found and fixed

Enabling the extensions surfaced **4 real errors**, all in `backend/tests/ApiTestCase.php`:

| Line | Error | Identifier | Fix |
| --- | --- | --- | --- |
| 32 | `Call to function assert() with true will always evaluate to true` | `function.alreadyNarrowedType` | removed the dead `\assert()` |
| 32 | `Instanceof between EntityManager and EntityManagerInterface will always evaluate to true` | `instanceof.alwaysTrue` | removed the redundant `instanceof` |
| 157 | same two errors in `removeHouseholdMemberships()` | both | same fix |

The container proved that `get(EntityManagerInterface::class)` already returns an
`EntityManager`; the defensive guards could never fail, so they were pure noise.
**Result: `make stan` → `[OK] No errors`.**

### 3.3 Organisation of controllers and services

Measured on the current code base (`backend/src`, 49 files / 4,751 lines):

| Layer | Count | Size | Observation |
| --- | --- | --- | --- |
| Controllers (`Controller/Api`) | 5 | 927 lines | Thin: validation + service call + response. Avg. 185 lines. |
| Services (`Service`) | 5 | 1,323 lines | Business logic lives here, not in controllers ✅ |
| Entities | 12 | — | Mapping kept separate from behaviour |
| DTOs | 5 | — | Request payloads are typed, not raw arrays ✅ |
| `Permission/PermissionResolver` | 1 | 186 lines | Pure function, independently testable ✅ |
| Repositories / Enums / EventListener / Exception | 11 | — | Clear folder-per-concern layout ✅ |

Strengths:

- Controllers never touch Doctrine directly for writes; they delegate to `ItemService`,
  `AuthService`, `CurrentActor` — a good separation of responsibilities.
- `PermissionResolver` is a pure, injected service: the access matrix can be tested without
  HTTP or a database.
- Cross-cutting concerns are extracted (`ApiExceptionListener`, `CurrentActor`, `AuthResponse`).

Weak points / technical debt:

| Debt | Detail | Recommendation |
| --- | --- | --- |
| `Service/AuthService.php` = **521 lines** | Registration, verification, resend, login/logout assembly in one class | Split into `RegistrationService`, `EmailVerificationService`, `SessionService` |
| `Service/ItemService.php` = **456 lines** | Create/update/transfer/photo logic together | Extract photo handling and status transitions |
| `CurrentActor.php` = 184 lines | Resolves user + profile + household in one pass | Keep, but document the resolution order |
| PHP version declared `>=8.2` while the platform/image is **8.4** | PHP-CS-Fixer warns on every run: *"running on PHP 8.4 but minimum is 8.2"* | Either raise `require.php` to `8.4` or run the fixer on 8.2 |
| `TODO`/`FIXME` markers | **0** in `src` ✅ | — |
| No code coverage baseline yet | Driver was missing | Done in this session: `pcov` added, `make coverage` works ([§8](#8-testability-and-reliability-next-sessions)) |

### 3.4 Coding standard

`backend/.php-cs-fixer.dist.php` → `@Symfony` ruleset, alphabetical imports, Yoda style off.

```text
$ make cs
Found 0 of 59 files that can be fixed
```

---

## 4. Symfony technical quality

| Check | Command | Result |
| --- | --- | --- |
| Container wiring | `php bin/console lint:container --env=test` | ✅ *all services are injected with values compatible with their type declarations* |
| YAML syntax | `php bin/console lint:yaml config` | ✅ 16 files valid |
| Twig templates | `php bin/console lint:twig` | ➖ **not applicable** — the application is a JSON API, there is no `templates/` directory |
| Routing | `php bin/console debug:router` | ✅ 35 routes load |
| Dependency audit | `composer audit` | ✅ *No security vulnerability advisories found* |
| Dependency count | `composer.lock` | 70 runtime + 49 dev = **119** packages |
| Snyk | `.github/workflows/snyk.yml` | 🟡 scans PHP **and** JavaScript, fails on `high` and above |

**Why both Composer audit and Snyk?** `composer audit` only knows the advisories GitHub
publishes for packages Composer can resolve; Snyk adds its own advisory database, transitive
dependency monitoring, a dashboard, and covers the JavaScript side too.

**Activating Snyk** (5 minutes, free account):

1. Create an account on https://snyk.io → *Account settings* → API token.
2. GitHub → *Settings* → *Secrets and variables* → *Actions* → new secret `SNYK_TOKEN`.
3. Push: the `Snyk (dependencies)` job switches from `skipped` to an actual scan.

Until the token exists the job prints a notice and **passes**, so CI stays green on a fresh clone.

**CodeQL is not used** because the repository is private on the GitHub free plan
(`POST /code-scanning/default-setup` → `404`). It becomes available if the repository is made
public or if GitHub Advanced Security is enabled — the workflow can then be added unchanged.

---

## 5. Accessibility (A11Y)

### 5.1 Automated checks

**axe-core** runs inside the Vitest suite (`frontend/src/test/a11y.ts`,
`frontend/src/pages/Accessibility.test.tsx`), so an accessibility regression **fails the build**
just like a unit test:

| Page covered | What is asserted |
| --- | --- |
| Landing page | No rule violation |
| Login form | Labels wired, **error announced via `role="alert"`**, no violation |
| Registration form | Every field has a label, no violation |
| Inventory (authenticated route) | No violation |

`make fe-test` → **46 tests passing** (4 of them accessibility).

**Lighthouse** (see [§6](#6-eco-design-and-performance)) audits the real production build:
**accessibility 100/100**.

> Note: jsdom has no layout engine, so the *colour-contrast* rule is disabled in axe and is
> covered by Lighthouse instead (which renders the page in a real browser).

### 5.2 Manual inspection

| Criterion | Observation | Status |
| --- | --- | --- |
| Keyboard navigation | Interactive elements are native (`button`, `a`, `input`); tab order is the DOM order | ✅ |
| Visible focus | `:focus-visible` on `.btn`, `.app-tab`, `.profile-row`, `.guide-chip`; `.input:focus` on fields | ✅ |
| Text alternatives | 4 `<img>` → 4 have `alt` (2 decorative `alt=""`, 2 descriptive: *"Photo de l'article"*, *"Article photographié"*) | ✅ |
| Decorative icons | 32 `aria-hidden="true"` on Lucide icons | ✅ |
| Form labels | `htmlFor`/`id` on every field; axe would fail otherwise | ✅ |
| Autofill tokens | `autoComplete` = `email`, `current-password`, `new-password`, `username`, `bday`, `given-name` | ✅ |
| Error messages | **17 `role="alert"`** for errors, **8 `role="status"`** for confirmations → announced by screen readers | ✅ |
| Landmarks | `<nav aria-label="Navigation principale">` (1), `<main>` (3) | ✅ |
| Page metadata | `lang="fr"`, `viewport`, `meta description`, distinct `<title>` | ✅ |
| Headings | **10 pages contain 14 `<h1>`** — `ProfilesPage` has 3, `FriendsPage` 2, `RegisterPage` 2 (conditional views) | ⚠️ finding |
| Skip link ("skip to content") | None | ⚠️ finding |
| `aria-expanded` / `aria-controls` | 0 — collapsible sections do not expose their state | ⚠️ finding |
| Colour contrast | Not verifiable in jsdom | ✅ verified by Lighthouse (100) |

### 5.3 Findings

1. **Heading structure** — several `<h1>` per view. Even when only one is rendered at a time,
   the rule "one `h1` per page" is violated in the source. *Fix:* keep one `h1` (the page title)
   and demote the others to `h2`.
2. **No skip link** — keyboard users must tab through the whole navigation on every page.
   *Fix:* add a visually-hidden first link targeting `#contenu`.
3. **No disclosure state** — sections that open/collapse do not expose `aria-expanded`.
   *Fix:* add `aria-expanded` + `aria-controls` on the toggles.

---

## 6. Eco-design and performance

Lighthouse, run against the **production build** (`make lighthouse`, CI job *Lighthouse*):

| Category | Score |
| --- | --- |
| Performance | **100** |
| Accessibility | **100** |
| Best practices | **100** |
| SEO | **100** |

| Metric | Value |
| --- | --- |
| First Contentful Paint | 0.5 s |
| Largest Contentful Paint | 0.5 s |
| Speed Index | 0.5 s |
| Total Blocking Time | 0 ms |
| Cumulative Layout Shift | 0.013 |
| Time to Interactive | 0.5 s |
| Server response (TTFB) | 180 ms |

**Network and weight (the eco-design view):**

| Resource | Requests | Transfer | Share |
| --- | --- | --- | --- |
| JavaScript | 1 | 100 KiB | 50 % |
| Fonts | 4 | **94 KiB** | **47 %** |
| Stylesheet | 1 | 5 KiB | 2 % |
| Document + other | 4 | 3 KiB | 1 % |
| Images | 0 | 0 KiB | 0 % |
| Third-party | 0 | 0 KiB | 0 % |
| **Total** | **10** | **201 KiB** | 100 % |

Findings:

1. **Unused JavaScript: 61.9 KiB of 101.6 KiB (61 %)** — Lighthouse estimates **60 KiB**
   could be saved. The whole router tree is in one bundle.
   *Fix:* `React.lazy()` route-level code splitting (the scanner/camera page is the heaviest).
2. **Fonts are 47 % of the page weight** (Inter 400/600 + Playfair Display 600/700, 94 KiB).
   *Fix:* drop unused weights or move to variable fonts; keep `font-display: swap`.
3. **Render-blocking stylesheet** — the single CSS file (5 KiB) blocks first paint.
   *Fix:* acceptable at this size, but consider inlining the critical CSS if the file grows.
4. **No images, no third-party requests, 10 requests total** ✅ — the page is lean by design.
5. Route chunks are not preloaded, so navigation to a first-visited route pays the JS cost
   on demand — that is the correct trade-off for an authenticated application.

Budgets are enforced in CI: `.lighthouserc.cjs` fails the build below
performance 0.7 / accessibility 0.9 / best-practices 0.9 (warn) and SEO 0.9.

---

## 7. User experience (UX)

| Criterion | Observation | Status |
| --- | --- | --- |
| Navigation | Persistent tab bar (`AppNav`) with icon **and** French label on each destination; `aria-label` on the nav | ✅ |
| Visual consistency | One design system in `index.css`: `.btn`, `.input`, `.form-error`, `.profile-row`, shared spacing/colours | ✅ |
| Feedback | Pending states are explicit: *"Envoi en cours…"*, *"Activation de la caméra…"*, *"Analyse de la photo…"*; buttons disabled while submitting | ✅ |
| Form comprehension | Password rules in the label itself (*"Mot de passe (8 caractères minimum)"*), date of birth typed `date`, confirmation field | ✅ |
| Error prevention | Client **and** server validation; password confirmation; username normalisation; under-18 registration refused **before** sending the e-mail | ✅ |
| Error messages | Inline, next to the field, announced with `role="alert"` — never a silent failure; API errors are mapped to readable French copy | ✅ |
| Empty states | Not dead ends: *"votre dressing est encore vide"* + shortcut to the scanner; friends/places pages guide the first action | ✅ |
| Offline/online status | The landing page shows an API badge (`API en ligne` / `API hors ligne`) instead of a blank screen | ✅ |
| Localisation | New user-facing strings are localisable (FR/EN), API values stay language-neutral | ✅ |
| Readability | 100 % Lighthouse SEO, `lang="fr"`, descriptive `meta description` | ✅ |

Weak points:

- No global "loading" indicator when the first `/api/auth/me` call is in flight (the layout
  can flash before the session resolves).
- Destructive actions (delete place/room/item) rely on confirmation dialogs that are not
  consistently styled as dialogs (no `role="dialog"` found) — worth normalising.

---

## 8. Testability and reliability

Baseline measured with the coverage driver (`pcov`, `make coverage`) at the start of the
campaign, and again once the PHPUnit + Cypress campaigns were complete:

| Indicator | Baseline | After the test campaign |
| --- | --- | --- |
| Backend tests | 150 tests / 637 assertions | **226 tests / 1,019 assertions** |
| Front-end tests | 44 tests | 46 tests (Vitest) |
| Line coverage (`src`) | 85.96 % (1,267 / 1,474) | **95.65 % (1,717 / 1,795)** |
| Business layer (Service · Permission · Entity · Dto · Enum · Repository) | — | **100.00 % (977 / 977)** |
| Method coverage | 69.75 % (196 / 281) | 91.30 % (273 / 299) |
| Class coverage | 26.83 % (11 / 41) | 78.00 % (39 / 50) |
| End-to-end (Cypress) | ❌ not started | ✅ **13 tests / 5 specs** (`make test-e2e`) |

Justification of every test choice lives in **`coverage.md`**.

**Critical functions** (priority order for the coverage campaign):

1. Permissions and sharing — `PermissionResolver` (93.85 % lines today).
2. Account lifecycle — registration, e-mail verification, resend, login, logout
   (`AuthService` 90.80 % lines but only 25 % of methods exercised).
3. Item lifecycle — create, update, location assignment, status transitions (`ItemService` 83.96 %).
4. Places hierarchy — place → room → nested storage (`PlaceController` 93.67 %).
5. Scan/AI output validation — untrusted input must never produce a fake success.

**Weakest classes to target first:**

| Class | Lines | Why it matters |
| --- | --- | --- |
| `Security\JsonLoginFailureHandler` | 66.67 % (0 % methods) | Wrong credentials path |
| `Security\AppUserProvider` | 42.31 % | Login by e-mail/username |
| `Permission\ResourceType` | 20 % | Access matrix vocabulary |
| `Service\CurrentActor` | 75 % | Every authenticated request |

**External dependencies and data needed:**

| Dependency | Test strategy |
| --- | --- |
| MariaDB (dev) / SQLite (test) | Schema rebuilt from metadata before every test — already in place ✅ |
| Mailer (Resend in prod, Mailpit in dev) | Asserted in tests through Symfony's mailer test assertions ✅ |
| OpenRouter vision API | Must be mocked: never call a paid provider in CI; test the failure path explicitly |
| Fixtures | The development account is documented in `README.md` but is **not** a fixture — a dedicated fixture campaign is needed for `coverage.md` |

**Scenarios automated since:** happy path registration → verification → login; anonymous 401
matrix and 403/404 isolation (PHPUnit functional); item created with and without a location;
Cypress end-to-end journey (register, verify by e-mail via Mailpit, household + profile,
add an item, search, **lend an item and take it back**). The lend control was added to the
dressing list (« Prêter » / « Récupérer », `PATCH /api/items/{id}`) and is covered by a Vitest
unit test plus the Cypress journey.

---

## 9. CI/CD

| Workflow | Trigger | Jobs |
| --- | --- | --- |
| `.github/workflows/ci.yml` | push to `main`, `develop`, `feature/**`, `release/**`, `pre-production/**` + every PR | **Backend**: composer install → warm test container → `lint:container` + `lint:yaml` → PHP-CS-Fixer → PHPStan (level 6) → PHPUnit → `composer audit` · **Frontend**: oxlint → tsc → Vitest → build · **Lighthouse**: collect → assert budgets → HTML report artifact |
| `.github/workflows/snyk.yml` | same branches + PR | Snyk scan of PHP **and** JavaScript dependencies (skipped until `SNYK_TOKEN` is set) |
| `.github/workflows/release.yml` | tag `v*` (or manual dispatch) | Builds and publishes `ghcr.io/<repo>/api` and `ghcr.io/<repo>/frontend` (Docker build cache via GitHub Actions cache) |

Local and CI run **the same checks** — `make qa` is the local mirror of the pipeline.

> The repository is private on a free plan, so branch protection is unavailable: the flow is
> enforced by discipline — **CI must be green before any merge** (this is stated in `README.md`).

---

## 10. Summary of findings

| # | Area | Finding | Severity | Status |
| --- | --- | --- | --- | --- |
| 1 | PHP | 4 PHPStan errors (dead `assert`/`instanceof` in `ApiTestCase`) | medium | ✅ fixed |
| 2 | PHP | Obsolete `ignoreErrors` rule hiding a Doctrine case | low | ✅ removed |
| 3 | PHP | `AuthService` (521 lines) and `ItemService` (456 lines) too large | medium | ⬜ backlog |
| 4 | PHP | `require.php: >=8.2` contradicts platform 8.4 (CS-Fixer warning) | low | ⬜ backlog |
| 5 | Symfony | `lint:container` / `lint:yaml` not running in CI | medium | ✅ added |
| 6 | Security | No dependency vulnerability scanner beyond `composer audit` | medium | 🟡 Snyk ready, token pending |
| 7 | Security | CodeQL unavailable on a private free-plan repo | low | ➖ documented, unblockable later |
| 8 | A11Y | Multiple `<h1>` per view | medium | ⬜ backlog |
| 9 | A11Y | No skip link | low | ⬜ backlog |
| 10 | A11Y | No `aria-expanded`/`aria-controls` on toggles | low | ⬜ backlog |
| 11 | A11Y | No automatic accessibility tests | high | ✅ axe tests added |
| 12 | Eco-design | 61 % of the JS bundle is unused (60 KiB) | medium | ⬜ backlog (route splitting) |
| 13 | Eco-design | Fonts = 47 % of page weight (94 KiB) | medium | ⬜ backlog |
| 14 | Perf | No performance budget in CI | medium | ✅ Lighthouse job + assertions |
| 15 | Process | No PR template | medium | ✅ added |
| 16 | Process | Long, error-prone commands | medium | ✅ Makefile |
| 17 | Tests | Coverage baseline unknown | high | ✅ campaign done: 95.65 % global, 100 % métier |
| 18 | Tests | No end-to-end campaign | high | ✅ Cypress: 13 tests, 5 parcours (`make test-e2e`) |

---

## 11. Evidence

Everything above was executed, not assumed. Three kinds of proof:

### a) Reproducible commands (anyone can re-run them)

```bash
make qa      # runs every backend and front-end check in one go
make coverage
make lighthouse
```

Transcript of the verification run (a clean checkout of `develop`):

```text
PHPStan (level 6 + symfony/doctrine + container)   [OK] No errors
PHP-CS-Fixer                                       Found 0 of 35 files that can be fixed
php bin/console lint:container --env=test           [OK] all services are injected with values
                                                      that are compatible with their type declarations
php bin/console lint:yaml config                   [OK] All 15 YAML files contain valid syntax
PHPUnit                                             OK (120 tests, 235 assertions)
composer audit                                      No security vulnerability advisories found
oxlint                                              Found 0 warnings and 0 errors
tsc -b                                              (no errors)
vitest                                              20 passed (5 files, incl. 3 axe tests)
vite build                                          ✓ built
lhci collect + assert                               All results processed (budgets passed)
```

### b) Continuous integration (the same commands, run by GitHub on every push)

| Run | Result |
| --- | --- |
| [PR #4 — this audit](https://github.com/tatrishvili/mmi24g15-Penderie-WR506/pull/4) | Backend ✅ · Frontend ✅ |
| [PR #5 — the toolchain](https://github.com/tatrishvili/mmi24g15-Penderie-WR506/pull/5) | Backend ✅ · Frontend ✅ · Lighthouse ✅ · Snyk ✅ |
| [All workflow runs](https://github.com/tatrishvili/mmi24g15-Penderie-WR506/actions) | Full history |

A red check blocks the merge: the quality gate is enforced by the pipeline, not by memory.

### c) Files in this repository

| Evidence | Where |
| --- | --- |
| This audit | `audit.md` (repository root) |
| Quality gate commands | `Makefile` → `make help` |
| Pipeline definition | `.github/workflows/ci.yml`, `snyk.yml`, `release.yml` |
| Pull request checklist | `.github/PULL_REQUEST_TEMPLATE.md` |
| Lighthouse HTML reports | CI artifact `lighthouse-report`, or `make lighthouse` → `frontend/lighthouse-report/` |
| PHPStan configuration | `backend/phpstan.dist.neon` |
| Accessibility tests | `frontend/src/test/a11y.ts`, `frontend/src/pages/Accessibility.test.tsx` |
| Coverage report | `make coverage` (baseline in [§8](#8-testability-and-reliability-next-sessions)) |

> Test counts in [§8](#8-testability-and-reliability-next-sessions) were measured on the full
> working tree during this session (150 tests / 637 assertions). On `develop`, before the
> pending feature branch is merged, the suite reports 120 tests / 235 assertions.
