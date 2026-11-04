# Penderie

> Your whole home inventory in your phone — what do I own, where is it, who did I lend it to, what should I do with it?

Penderie is a personal inventory application focused on clothing and dressings: scan an item, let the app fill in
the details, then find it, lend it, share it or sell it to people you trust.

This repository contains the **development environment** (Docker) and the first working slice of the product:
a Symfony API and a React front end, wired together and covered by CI.

Start with [PRODUCT_SPEC.md](./PRODUCT_SPEC.md) for product decisions and known gaps, [WORKFLOWS.md](./WORKFLOWS.md) for user journeys, and [PERMISSIONS.md](./PERMISSIONS.md) before changing access control. Repository agents should also read [.github/copilot-instructions.md](./.github/copilot-instructions.md).

---

## Stack

| Layer     | Technology                                              |
| --------- | ------------------------------------------------------- |
| API       | Symfony 7.4 LTS (PHP 8.4), Doctrine ORM, MariaDB 11     |
| Front end | React 19, TypeScript 6, Vite 8, React Router            |
| QA        | PHP-CS-Fixer, PHPStan, PHPUnit · oxlint, tsc, Vitest    |
| Runtime   | Docker Compose (nginx + php-fpm + MariaDB + Node)       |
| CI        | GitHub Actions (`.github/workflows/ci.yml`)             |

## Repository layout

```
.
├── backend/                  Symfony API (PHP 8.4)
│   ├── src/                  Application code
│   ├── tests/                PHPUnit tests
│   ├── migrations/           Doctrine migrations
│   ├── phpstan.dist.neon     Static analysis config (level 6)
│   └── .php-cs-fixer.dist.php Coding standard config
├── frontend/                 React application (Vite dev server)
│   ├── src/                  Components, pages, tests
│   └── vite.config.ts        Dev server + /api proxy + Vitest config
├── PRODUCT_SPEC.md           Product decisions and domain boundaries
├── WORKFLOWS.md              Intended user journeys and implementation order
├── PERMISSIONS.md            Access matrix and sharing policy
├── style.md                  Front-end design system (colors, typography)
├── docker/                   Dockerfile + nginx configuration
├── docker-compose.yml        Local environment definition
├── .github/copilot-instructions.md  Project-specific agent guidance
└── .github/workflows/ci.yml  Continuous integration
```

## Prerequisites

- Docker + Docker Compose v2
- Node.js 24 and npm 11 (only needed if you prefer running front-end tooling on the host)

## Quick start

```bash
docker compose up -d --build
```

Then open:

| URL                              | Service                                          |
| -------------------------------- | ------------------------------------------------ |
| http://localhost:5173            | Front end (Vite dev server, proxies `/api`)      |
| http://localhost:8000/api/health | API health check (JSON)                          |
| 127.0.0.1:3306                   | MariaDB (`penderie` / `penderie`, db `penderie`)  |
| http://localhost:8025            | Mailpit inbox for local verification emails       |

The whole chain — browser → Vite → nginx → PHP-FPM → MariaDB — is containerised; nothing needs to be
installed on the host except Docker.

Compose waits for PHP-FPM and the gateway health check before starting the frontend.
The gateway refreshes Docker DNS every five seconds so recreating the API container
does not leave it forwarding requests to an obsolete address.

If login reports a gateway/proxy error, check `docker compose ps` and
`docker compose logs --tail=50 frontend nginx api`. An `ENOTFOUND nginx` error means
the frontend cannot reach the Docker gateway; start the complete stack with
`docker compose up -d` and wait for its health checks before retrying login.

After restarting Docker Desktop/WSL, use `make restart`. This recreates the
application containers to refresh their bind mounts and waits for API/gateway
readiness, while keeping the database and its data. A plain `docker compose restart`
reuses existing mounts and does not apply updated Compose settings. If nginx
returns `File not found.`, check that `backend/public/index.php` is visible inside
the API container; an empty mount requires container recreation. The API health
check verifies both the entry point and Composer autoloader are present.

The recurring 3 November failure was confirmed in Docker's container state:
Docker Desktop restored an empty WSL backend mount, and nginx failed before its
process could start because the saved config-file mount appeared as a directory.
Restart policies cannot repair stale mounts; a health check reports the failure
but does not recreate a container. nginx now builds its configuration into a
small image and has no source/config bind mounts. Rebuild it with `make restart`
after editing `docker/nginx/default.conf`. `make up` also recreates application
containers, and frontend startup refuses an empty source mount.

This workstation additionally has `penderie-wsl-recovery.timer` installed as a WSL
user service, with user lingering enabled for startup. It checks every 30 seconds
while WSL is running and recreates application containers only when it observes
the known empty-mount/config-mount failure. It preserves the database and leaves
a healthy or deliberately stopped stack alone. The service and timer definitions
are in `docker/`; another workstation must install them explicitly and adjust
their project path. This does not start Docker Desktop or WSL from Windows.

```bash
systemctl --user status penderie-wsl-recovery.timer
journalctl --user -u penderie-wsl-recovery.service -n 30 --no-pager
# Optional: disable background recovery
systemctl --user disable --now penderie-wsl-recovery.timer
# Recovery decision tests, without touching Docker/data
python3 docker/test_wsl_recovery.py
```

Registration emails are captured locally by Mailpit; no external email account is needed for development.
For deployment, set `MAILER_DSN` to the Symfony Resend transport
(`resend+api://<API_KEY>@default`), `MAILER_FROM_ADDRESS` to an address on your verified sending domain,
and `PUBLIC_APP_URL` to the public front-end origin. Keep provider credentials in an untracked environment
file or deployment secret store, never in source control.

## Repeatable local smoke test

The following test login is for a disposable **local development** database only. It is not a seeded account;
create it through registration the first time. Do not use this password for a real account or deployment.

- **Email:** `penderie.test.20261009@example.com`
- **Password:** `PenderieTest!2026`
- **Username:** `penderie_test`
- **Date of birth for the adult test account:** `1990-05-12`

1. Start the development stack:

   ```bash
   docker compose up -d --build
   ```

2. Apply any migrations that have not yet run:

   ```bash
   docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction
   ```

   The place/room/nested-storage migration (`Version20261009130000`) and registration username/date-of-birth
   migration (`Version20261012180000`), the friend-request/account-verification migrations
   (`Version20261013100000` and `Version20261013110000`), and the one-way follow migration
   (`Version20261013120000`) are applied to the current local development database.
   Run the command again after pulling future schema changes.

3. Open http://localhost:5173. Register with the test email, password, username, and date of birth above. If
   this account was already registered in the persistent local database, go straight to **Sign in** instead.
   If it was registered before username/date-of-birth fields were added, older accounts are not retroactively
   assigned a username or birth date. A verified account without a username can choose one once from **Mon
   compte**; use that username (not its email) for friend requests.
   Email stays the login identifier; username is a unique account handle. The date of birth is self-declared
   and only gates adult registration (18+); it is not official age verification. To test the under-18
   rejection, use a date less than 18 years ago; the API should reject it without sending a verification
   email.
4. Open Mailpit at http://localhost:8025, find the verification email for that address, and follow its link.
   The account is created and signed in only after email verification. Mailpit is local; no paid mail provider
   or external mailbox is needed.
5. Open **Lieux**. Add a place (the default **Maison** is already available), then add a room. Under that
   room choose a preset such as **Dressing**, **Salon**, or **Garage** (or enter a custom name). Add a
   rangement such as **Penderie**; use **Dans un rangement existant** to add a nested shelf or
   box such as **Boîte hiver**.
6. To test child accounts, sign in as the verified parent, open **Profil → Famille et profils → Comptes
   enfants**, choose mother/father and create the child username and initial long password for age 12–17.
   On a separate browser/device, choose **Connexion enfant**, change that initial password and sign in
   again. Assign common/child rooms from **Foyer → Ajouter une pièce**. Set individual object audiences
   from **Visibilité et partage**; child requests require a parent decision in **Notifications**.
   See [CHILD_ACCOUNTS_DESIGN.md](./CHILD_ACCOUNTS_DESIGN.md) for parent invitations, collections,
   approved contacts and revocation. Optional PINs remain trusted-parent-device unlock only.
7. Open **Amis** to send friend requests by username. Requests require acceptance; friendship does not share
   inventory. Only verified adult accounts can send or accept them. To test both sides, register and verify
   another disposable adult account with a different username.
8. Add an item manually or open **Scanner un article**. In the item form, select the room and optionally
   its rangement/box. Saving without a location is also supported. Confirm the item appears in the inventory
   with its place → room → storage path.
9. The scanner requests camera access when opened. Allow the browser permission to use the live preview and
   capture button; the gallery picker remains available too. If camera permission is denied or unavailable,
   use the gallery or retry after changing browser permissions. Camera access requires HTTPS or localhost.
10. Photo analysis is optional. A configured free vision provider may be rate-limited or unavailable; in that
   case the captured photo is kept and the screen offers retry or manual entry. No paid provider is required
   to test item creation and location assignment.

The local registration email and password are ordinary test credentials, not a database fixture. The account
and its inventory remain in the local database until that development database is reset. Never store personal
items in this test space.

## Common commands

```bash
# Backend (executed inside the PHP container)
docker compose exec api composer install
docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T api php bin/console doctrine:schema:validate
docker compose exec api composer cs:check    # coding standard
docker compose exec api composer cs:fix      # fix coding standard
docker compose exec api composer stan        # static analysis
docker compose exec api composer test        # unit tests
docker compose exec api composer audit       # dependency vulnerabilities
docker compose exec api php bin/console list # Symfony console

# Frontend (host or container — node_modules are shared through the mount)
cd frontend
npm run lint        # oxlint
npm run typecheck   # tsc
npm test            # vitest
npm run build       # type check + production build
```

## Environment files

| File                     | Purpose                                                        |
| ------------------------ | -------------------------------------------------------------- |
| `.env.example`           | Docker Compose variables (copy to `.env` if you need overrides) |
| `backend/.env`           | Symfony configuration (committed, **dev-only** values)         |
| `backend/.env.local`     | Local secrets — **never committed**                            |
| `OPENROUTER_API_KEY`     | Reserved for the item-scanning feature, lives in `backend/.env.local` |
| `MAILER_DSN`, `MAILER_FROM_ADDRESS` | Email provider and verified sender; configure in deployment secrets for real delivery |

## Git workflow (Git Flow)

```
feature/*  →  develop  →  release/* / pre-production/*  →  main
```

- `main` is frozen until the project is finished — **never push directly to it**.
- Every feature lives on `feature/<name>`, branched from `develop`.
- Merge only when **CI is green** (push or open a pull request and wait for the checks).
- All commits, documentation and code comments are written in **English**.

## Continuous integration

`.github/workflows/ci.yml` runs on every push to `main`, `develop`, `feature/**`, `release/**`,
`pre-production/**` and on every pull request:

- **Backend**: PHP 8.4 → `composer install` → PHP-CS-Fixer → PHPStan → PHPUnit → `composer audit`
- **Frontend**: Node 24 → `npm ci` → oxlint → `tsc` → Vitest → production build

> The repository is private on a free plan, so GitHub branch protection is not available: the flow above
> is enforced by discipline — CI must be green before any merge.

## Roadmap

Recommended next steps:

1. Review and complete server-side object-level permissions for child/profile access and sharing before enabling those workflows.
2. Configure production email delivery and implement adult 2FA with recovery.
3. Improve the single-item scan experience and provider validation while preserving explicit errors and manual entry.
4. Add reviewed batch scanning, then invitations, lending, and sharing with their full permission and history flows.

The current working slices and known security/product gaps are tracked in [PRODUCT_SPEC.md](./PRODUCT_SPEC.md) and [WORKFLOWS.md](./WORKFLOWS.md).

### Current Figma household and social screens

See [FIGMA_IMPLEMENTATION.md](./FIGMA_IMPLEMENTATION.md) for all 25 frame-to-route
mappings, implemented workflows, additive migrations, privacy boundaries and
remaining backend work. Start at `/foyer` or `/profil` after signing in. On another
environment, apply pending migrations before using collections, sharing or loans.

Latest session handover: [session03/11/26.md](./session03/11/26.md). It records the implemented child accounts, household item audiences, repeated rooms, approval inbox and recurring Docker mount repair.
