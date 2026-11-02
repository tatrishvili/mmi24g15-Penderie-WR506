# Session handover — 29 October 2026

**Read this file first when resuming Penderie.** This is the continuation record for the request to implement all 25 existing Figma household/social screens. Do not restart, redesign, or repeat the full discovery. The user explicitly asked for this handover so they do not have to explain the task again.

## Authoritative final status — saved after verification completed

This section supersedes the in-progress verification notes retained below.

- All **25 requested views are implemented and routed** locally. Household/inventory forms reuse existing APIs; private profile/collections/vitrine, scoped share links and the basic loan lifecycle have new persisted backend support.
- Latest checks: **232 backend tests / 1128 assertions**, **77 frontend tests**, clean PHPStan/lint/TypeScript, successful production build, valid schema/container/YAML, and a clean Composer audit.
- Browser validation: **75 cases passed**, zero horizontal overflows, zero page errors and zero broken visible images, using isolated sample responses.
- Cypress: login 3/3, home 2/2 and inventory/scanner 4/4 passed in the full rerun. After correcting the stale registration selector, the targeted registration/household rerun passed **4/4**. Thus all **13 existing E2E cases passed across the final full/targeted runs**; do not call this a single wholly green full run.
- No verification command remains pending. The user subsequently requested a commit on the feature branch for a future PR into `develop`. The PR handoff branch is `feature/social-figma`; use `git log -1` for its implementation commit. No push, PR, merge or deployment is part of this handoff.
- The remaining work is product/backend functionality and focused real-data review, not replaying initial discovery. Review collection membership cleanup after permanent deletion and primary-photo behavior on edits, then agree the next backend scope from the documented audience/invitation/child-access gaps. Do not claim these follow-ups are implemented.
- The original landing/auth designs and the user's pre-existing Salon/onboarding changes were preserved. Database migrations were additive and applied only locally; fixtures were not purged.

The older “running”, “queued”, “pending” and failure notes below document how verification progressed and are historical now.

## Exact stopping point at the first handover save

The 25 frontend views and their navigation have been implemented locally, along with additive backend persistence for private collections/profile/vitrine settings, revocable share links and the loan request lifecycle. The code is on **`feature/social-figma`**, with base HEAD **`20e18ef`**, and is **uncommitted**. No commit, push, PR or deployment was performed. Do not describe the entire product as finished: the unsupported backend workflows listed below remain explicit in the UI, and the final frontend/browser/Cypress verification still needs its last results checked.

**At the first save, the immediate task was to finish verification. That verification has now completed; use the authoritative final status above and the review items at the end for the next session.** Do not launch a new feature phase or silently enable public audiences.

## Original user request and constraints

Implement these current Figma maquettes directly into the existing React frontend:
https://www.figma.com/design/iFWk0CH1v2qHltXK5CsrlM/Penderie?node-id=0-1

- Inspect all 25 actual frames before coding, including manual modifications. This was completed using design-context responses and their screenshots.
- Preserve icons already inside the actual frames. Do not substitute the separate `Penderie / Iconographie validée / SVG` library or generic icons.
- Match the 402 × 874 references, Baloo Chettan 2, cream/burgundy/pink palette, rounded cards/buttons, spacing and illustrations.
- Reuse the existing structure, components, backend, auth, household and inventory logic. Do not create a replacement project or duplicate equivalent functionality.
- Connect real forms/actions/navigation; never fake a successful backend action.
- Never expose private item details or exact household locations through external sharing.
- Preserve original home, registration, login and email-verification screens.
- At completion, report all pages, connected functionality and backend work still missing.

The Figma file was read/exported only; no maquettes were modified. Figma session UUID for this same task: `e4c67ef8-0730-41e2-9ee0-c57dc3f52b4e`. File key: `iFWk0CH1v2qHltXK5CsrlM`. The loaded Figma skills were design-to-code and read-only use-figma. The exact frame IDs and routes are recorded below.

## Protect the user's pre-existing work

These changes existed before this task and must not be reverted or claimed as newly authored here:

- Deleted `frontend/src/images/icons/Salon_icon.png`.
- New `frontend/src/images/icons/Salon_icon.svg`.
- `frontend/src/pages/OnboardOrganise.tsx` changed to use the SVG.

All other implementation changes described here were made during this task. Check `git status --short` before any follow-up. Do not reset, clean, load fixtures, or overwrite uncommitted work.

## Environment and workflow already established

- Workspace: `/home/atatrishvili/penderie`; shell Bash.
- Existing Docker services: `api`, `frontend`, `nginx`, `db`, `mailpit`. They were already running. No replacement dev server was started.
- Frontend: http://localhost:5173; backend through nginx: http://localhost:8000; Mailpit: http://localhost:8025.
- Run PHP/Node checks in Docker, e.g. `docker compose exec -T api composer test` and `docker compose exec -T frontend npm test`.
- Docker and Figma asset downloads required sandbox escalation in this session. Docker Compose approval was granted. Do not bypass approval mechanisms if the new session requires authorization again.
- Do not print environment variables or secret files. Existing demo-account instructions are in README; no new secrets were introduced.
- Source instructions reviewed: WORKFLOWS.md, WIREFRAME_WORKFLOW.md, SESSION_CONTEXT.md, README.md, PERMISSIONS.md, style.md, PRODUCT_SPEC.md, CONCEPT.md and FRIENDS_AND_FOLLOWERS.md. Some are historical; use the current product/privacy documents and this handover for the latest state.
- Styling stays in `frontend/src/index.css`; original auth/onboarding styles remain scoped separately.
- Family setup must remain optional after automatic personal-space provisioning. Child PIN switching is a convenience inside an adult session, not independent child sign-in.

## Implementation files

Frontend additions:

- `src/pages/HouseholdSetupPage.tsx`: seven household/room/storage views using existing APIs.
- `src/pages/SocialProfilePage.tsx`: profile, edit, collections, create, private vitrine, collection membership.
- `src/pages/ItemDetailPage.tsx`: detail, actions, visibility, sharing, archive/delete and access management.
- `src/pages/ItemEditorPage.tsx`: create/edit/move wrapper around the shared form.
- `src/pages/LoansPage.tsx`: request, received request, list, detail and limited shared-item presentation.
- `src/components/DesignPage.tsx`: shared page, row, tile, field, form, notice and error components extending existing shells/buttons.
- `src/components/frameAssets.ts`: static per-frame SVG mapping.
- `src/components/FigmaGlyph.tsx`: complete original exported SVGs clipped to their leading glyph at natural size when the glyph was part of a text layer.
- `src/api/social.ts`, `src/lib/errors.ts`, `src/pages/DesignPages.test.tsx`.
- `src/images/figma/`: **51 SVG files** — 42 frame-provided assets, three exact standalone outlined illustrations (house/scan/article), and six exact text/glyph exports. SVG paths were not redrawn or edited. No full-screen screenshot is an application asset.

Frontend extensions:

- `App.tsx`: routes for all 25 views plus private collection detail and limited shared view.
- `AppNav.tsx`: Profil and Foyer entry points; existing settings and managers stay reachable.
- `ItemFormPage.tsx`: existing form replaced with reusable details/location steps; supports existing optional fields in an expandable section. New-storage link opens another tab to preserve a draft; locations refresh on focus.
- `ScanPage.tsx`: preserved camera/gallery/AI/error/retry/manual behavior, adapted capture layout and shared form.
- `ItemsPage.tsx`: item links, manual creation, archive navigation, server-side room filtering and existing quick loan-status buttons retained.
- `api/items.ts`: room filter and loading all inventory pages for collection membership.
- `types/item.ts`: visibility and owner selection typing.
- `api/client.ts`: safe handling of malformed error bodies.
- `ProtectedRoute.tsx` and `LoginPage.tsx`: safe internal return destination for protected share/lending flows; original sign-in appearance retained.
- `ScanPage.test.tsx`: explicitly completes the new location/save step.
- `index.css`: shared visual components and frame-specific spacing; fixed inherited auth alignment/width/padding conflicts discovered in Chromium.

Backend additions/extensions:

- `Entity/SocialWorkspace.php`, `Entity/ItemShare.php`, `Entity/Loan.php`.
- `Controller/Api/SocialController.php` with persistence and scoped read/write workflows.
- `Item` visibility column; optional ownerProfileId in create DTO and authorized same-household owner selection.
- Item service/repository/controller checks for selected-child ownership and Personal visibility; active-loan guards; archive revokes links; active inventory filtering and room filtering.
- Place controller blocks child mutations and limits child location lists to those needed by their own items.
- `security.yaml`: public access only to limited shared GET/photo routes, not loan mutations.
- `rate_limiter.yaml`: public share-read limiter.
- `docker/nginx/default.conf`: suppress normal access logging of bearer-share API URLs. Configuration was tested and local nginx reloaded.
- `tests/Controller/Api/SocialControllerTest.php`: six HTTP integration tests covering actual privacy and lifecycle behavior.

Documentation: FIGMA_IMPLEMENTATION.md (full frame/route/action report), style.md and README.md updated. Cypress tests/helpers were being aligned with preserved current auth/home designs and the new item-form step; see verification state below.

## Database changes already applied locally

1. `backend/migrations/Version20261029215520.php`: social_workspace, item_share, item_loan and item.visibility.
2. `backend/migrations/Version20261029220711.php`: approved share-presentation snapshot.

Both migrations were generated, reviewed as additive and applied to the existing local MariaDB database. Schema validation passes. Existing records were not replaced and fixtures were not reloaded. Other environments must apply pending migrations before using this frontend. PHPUnit uses its isolated SQLite test database. Cypress uses the development stack and can leave e2e accounts/pending registrations/test items; do not purge the user's database to clean them.

## Verification — confirmed versus still pending

**Confirmed:**

- Backend full suite: **232 tests / 1128 assertions passed**.
- PHPStan: no errors.
- Backend coding standard fixed; latest complete formatting run succeeded.
- Doctrine mapping and local database schema: in sync.
- Symfony container lint: passed.
- YAML lint: all 16 files valid.
- Composer audit: no security vulnerability advisories.
- Six new focused social HTTP tests: passed (109 assertions when run separately before the final full suite).
- Frontend suite passed **77 tests across 13 files** before the last glyph/spacing refinements. The initial scanner failures were updated for the intended two-step save workflow and then passed.
- Earlier frontend lint and production build passed. A later full typecheck exposed an overly narrow test helper signature and an unused FigmaGlyph import; both were corrected. Do not confuse an earlier failed run with the current source.
- Earlier Chromium layout pass rendered 75 cases (25 screens × 402px, 390px, 1280px), with zero horizontal overflows, JavaScript page errors or broken mapped frame assets. Later refinements require the final pass below to be read before claiming final verification.

**Checks running / final results to collect at the time this file was first saved:**

- Frontend final pipeline: formatting, lint, TypeScript, Vitest, production build. Unified-exec session ID was `28612`; it had reached clean lint and TypeScript. Tool sessions are ephemeral; if unavailable, rerun the necessary check instead of assuming success.
- Browser final pass: unified-exec session ID `27505`; output redirected to `/tmp/penderie-browser-check.log`. Raw report/screenshots inside frontend container: `/tmp/penderie-visual`; host copies may be older. Read the final JSON report before recording final success.
- Cypress rerun: unified-exec session ID `92347`; log `/tmp/penderie-cypress.log`. The first full run failed 8/13 because several tests still targeted older unchanged home/auth markup plus the old item form. Login/navigation now passed 3/3 in the next run, but registration was still using the wrong apostrophe in its button-text selector. This was just corrected to `button.register__submit` in `cypress/support/commands.js` and `cypress/e2e/auth-register.cy.js`. The already-running Cypress process may have loaded the previous helper, so a final rerun of affected specs is needed after it finishes.

Current Cypress adaptations:

- Registration helper no longer expects the absent confirmation-password input. The current preserved registration UI sends passwordConfirm equal to the password.
- Registration validation test now checks the real under-18 age gate instead of a removed confirmation-password UI.
- Home test checks the actual original “Bienvenue chez Penderie” and Suivant→/onboarding/2 flow, not a removed health badge or old landing title.
- Logout test follows Profil→Plus→Mon compte after the main-nav update.
- Scanner helper expects h1 Nouvel article, not h2; item-create test completes Choisir l’emplacement then Enregistrer l’article.

## Exact resume actions

1. Read this file and FIGMA_IMPLEMENTATION.md; inspect git status. Preserve the original Salon/onboarding changes.
2. Finish/read the final frontend pipeline. If needed: `docker compose exec -T frontend npm run lint`, `npm run typecheck`, `npm test`, `npm run build`.
3. Read `/tmp/penderie-browser-check.log`. The final pass must show 75 expected screens, no overflow/errors/bad images. Export the container's latest `/tmp/penderie-visual` report/screenshots and inspect relevant changed pages. Earlier screenshots exposed and helped fix narrow inherited forms, misplaced buttons, glyph fallbacks, room-icon placement and extra controls pushing the article CTA down.
4. Finish Cypress; correct remaining actual failures and rerun affected specs. Use the existing Docker service, not a second Vite server. Capture the full log to /tmp so large colored output is not lost. Clear the development rate-limiter pool before a fresh whole registration campaign. Do not reset fixtures.
5. Run `git diff --check`; update this handover and the implementation report with final results. No commit/push/merge was requested.
6. Give the user a concise report of implemented screens, connected actions, remaining backend limitations and verification. Include the link to this handover.

## Temporary QA artifacts (not durable project dependencies)

- `/tmp/penderie-visual.cjs`: Chromium browser harness. It must intercept only URL paths starting `/api/`; the first harness accidentally intercepted `/src/api/*.ts`, producing blank pages. This was fixed, and `.design-page` is now awaited before inspecting a screen.
- Browser runner in frontend container uses `/usr/bin/chromium` and copied existing Playwright toolkit at `/tmp/penderie-shot/node_modules/playwright-core`.
- `/tmp/penderie-browser-check.log`, `/tmp/penderie-visual/results.json`, 01.png…25.png (host snapshots may be older than the final container snapshots).
- `/tmp/penderie-cypress.log`; Cypress videos/screenshots under the existing gitignored cypress artifact directories.
- `/tmp/penderie-figma-*.txt`: saved design-context reference code for all 25 frames.
- `/tmp/penderie-assets.json`: exact frame-asset mapping from Figma.

Do not rely on /tmp surviving a new session. Source, tests, SVGs, migrations, and the documentation in the repository are the durable deliverables. If necessary, reconstruct the browser runner using the existing WIREFRAME_WORKFLOW.md toolkit guidance.

---

# Full page/action/privacy report saved with this handover

# Penderie — current Figma implementation (29 October 2026)

Source: [Penderie design file](https://www.figma.com/design/iFWk0CH1v2qHltXK5CsrlM/Penderie?node-id=0-1).
All 25 current frames were read with design context and screenshots before frontend implementation. This includes the manually changed room illustrations. The frontend imports the 42 SVG assets returned for these frames and nine exact outlined exports for the original symbol illustrations and glyphs. Original text exports are clipped to their leading glyph at natural size where that glyph was part of a text layer. No assets were taken from the separate validated iconography library. The Figma document was not modified.

The original landing, registration, sign-in and email confirmation designs remain intact. Family setup stays optional. Sign-in also remembers a protected destination when somebody follows a lending/share workflow.

## Screen and route map

| Frame | Current maquette | Application route | Connected behavior / limitation |
| --- | --- | --- | --- |
| `160:8` | Créer mon foyer | `/foyer/creer` | Existing household creation and automatic owner/place setup. Housing selection names the initial place; there is no separate housing-type database field. |
| `160:27` | Qui habite ici ? | `/foyer/membres` | Actual profiles, child setup, existing profile management. Adult email invitations explicitly unavailable. |
| `160:57` | Ajouter un enfant | `/foyer/enfant` | Existing parent-managed profile with trusted-device PIN; clearly distinguishes this from independent child sign-in. |
| `160:81` | Ajouter les pièces | `/foyer/pieces/ajouter` | Search, selection and real room creation in the chosen place. Successful creations are removed from the pending selection if a later request fails. |
| `160:123` | Mes pièces et rangements | `/foyer/rangements` | Actual rooms and storage, links to add storage. |
| `160:152` | Maison Martin ⌄ | `/foyer` | Actual household selector, room search, filtered inventory links, members, scanner. |
| `160:189` | Ajouter un rangement | `/foyer/rangement/ajouter` | Existing storage API, room selection, presets, custom names, optional parent storage. |
| `160:219` | Scanner un article | `/scanner` | Existing live camera, framing hints, gallery, AI analysis, retry and manual fallback. |
| `160:241` | Nouvel article | `/articles/nouveau`, or scanner draft | Shared two-step item form; photos, owner, type, color and optional existing item fields. |
| `160:264` | Lieu de rangement | Second item-form step; `/articles/:id/emplacement` | Optional place/room/root/nested storage; actual save. New-storage action opens another tab to preserve the unsaved draft and refreshes locations when focus returns. |
| `165:8` | Mon profil | `/profil` | Persisted display name, photo, bio, private collection counts and saved pins. Extra destinations are in the Plus menu. |
| `165:46` | Modifier mon profil | `/profil/modifier` | Persisted display name/photo/bio. Username remains governed by existing account rules; no fabricated public profile URL. |
| `165:71` | Mes collections | `/collections` | Persisted private collections with real membership counts; links to a collection's items. |
| `165:110` | Créer une collection | `/collections/nouvelle` | Name/cover upload and actual private creation. |
| `165:133` | Personnaliser ma vitrine | `/vitrine` | Private collection pin selection/removal, order reversal and persistence; no external publication. |
| `165:177` | Visibilité d’un article | `/articles/:id/visibilite` | Owner-only Personal/household-private settings. Personal revokes existing shares. Other audience rows explain the missing backend feature. |
| `165:235` | Partager un article | `/articles/:id/partager` | Explicit approval, seven-day link, optional verified-account gate, copy and revocation destination. Recipient-specific sending is explicitly unavailable. |
| `165:271` | Gérer les accès | `/acces` | Actual links, expiration/revocation state, persisted revocation. No invented recipients. |
| `165:308` | Fiche article | `/articles/:id` | Real photo, owner, private location, visibility and action links. |
| `165:345` | Actions sur un article | `/articles/:id/actions` | Edit, move, collection membership, private vitrine destination, archive/restore, confirmed delete. Direct article publication remains unavailable. |
| `165:398` | Demander un emprunt | `/emprunter/:token` | A signed-in verified adult requests an available explicitly shared item with a date/message. |
| `165:434` | Demande reçue | `/prets/:id/demande` | Owner accepts or declines the actual request. Acceptance reserves the item; handover is a separate action. |
| `165:454` | Mes prêts | `/prets` | Actual incoming/outgoing requests and loan history; pending-request filter. |
| `165:494` | Détail du prêt | `/prets/:id` | Actual timestamps, owner-confirmed handover and return; item availability restored on return. |
| `165:544` | Archiver ou supprimer | `/articles/:id/archiver` | Confirmation, reversible archive, permanent deletion and server-enforced active-loan guard. |

Additional routes: `/partage/:token` is a limited shared presentation; `/collections/:collectionId` manages membership. Existing `/compte`, `/lieux`, `/profils`, `/amis`, `/dressing` and auth routes remain available. The main navigation now links to Profil and Foyer; account settings remain accessible from the profile menu.

## Persistence and authorization

Two additive migrations (`Version20261029215520`, `Version20261029220711`) have been applied to the local development database. Other environments must run `php bin/console doctrine:migrations:migrate --no-interaction` before deploying the frontend.

- `SocialWorkspace` stores the adult's private profile presentation, collections and pins per household. Version checks reject stale edits. No public collection/profile endpoint is enabled.
- `ItemShare` stores an SHA-256 token hash, creator, item scope, creation/expiration, verification gate and revocation state. Only the adult item owner may create a share. Child-owned and Personal items cannot be externally shared through these endpoints.
- The public payload is an allowlist: approved name/type/color, selected photo endpoint, username and availability. Name/type/color/photo selection are frozen at approval, so later item edits cannot silently publish new details. The photo route rechecks access/expiry/revocation and re-encodes the image to remove source metadata. Private notes, memories, prices, profile IDs, QR credentials, household identity and exact locations are never returned.
- Public share reads are rate-limited. Responses are non-cacheable and carry no-referrer/noindex headers. The shared React page installs a no-referrer policy. Nginx excludes bearer-share API URLs from its normal access log; the validated local configuration was reloaded.
- `Loan` persists owner/borrower, item-name snapshot, requested/accepted/handed/returned timestamps, due date and message. Only participants can list the record, and only the owner changes its state. Borrowers do not receive an internal item ID or location. Row locking on the development MariaDB database serializes acceptance against current availability.
- Item detail, QR and photo endpoints now enforce the selected child's own-profile restriction. Personal items are hidden from adult peers, retaining household child-management behavior. Child inventory creation cannot choose another owner or lending status; child permanent deletion and place mutations are blocked. Child place lists are reduced to locations needed by their own items.
- Active loans block item deletion and direct lifecycle-status changes. Archiving revokes links. The active dressing excludes archived records, and an archive view links to restoration. Historical loan records survive item deletion after return.

The existing quick Prêter/Récupérer inventory buttons remain as legacy status-only controls. They do not invent a borrower/history entry. Loans created by the new request workflow must be returned through Mes prêts.

## Remaining backend/product work

- Secure adult invitation delivery and acceptance with separate credentials.
- Independent parent-authorized child credentials, credential recovery and revocation, and explicit guardian relationships. Current PIN switching remains within an adult session.
- Friend/follower/public/custom publication and recipient-specific access grants. These must apply actual relationships and privacy-safe projections before enabling the audience rows.
- External profile/vitrine/collection publication, public profile handles, direct article pins and sharing of child-owned resources with explicit guardian approval.
- Lending notifications, cancellation/overdue workflows, condition-before/after and richer activity/audit history. The basic request/accept/decline/handover/return workflow is persisted now.
- Username changes beyond the existing one-time legacy-username setup and a separate housing-type field.

These limitations are shown in the corresponding controls. No local-storage stand-in or fake successful backend action is used.

## Verification

The implementation has focused HTTP integration tests for scope isolation, Personal vetoes, approval, verified-only links, revocation, expiry, photo access, immutable approved projections, child restrictions and loan transitions. Frontend tests cover the screen families, real IDs, the two-step draft, selected locations, share approval and rejected writes. Existing scanner tests now include the explicit location/save step.

Browser checks render all 25 screens at 402×874, 390×844 and desktop widths using isolated sample responses. They check that each expected frame is present, each visible image loads with nonzero geometry, and there is no horizontal overflow or JavaScript page error. These mocked layout checks are separate from the real-backend PHPUnit/Cypress checks. Real data counts, empty states, permission errors and unavailable-feature explanations intentionally differ from the illustrative names/counts in Figma.

Final command results are recorded in the task completion report; screenshots and raw layout reports are temporary QA artifacts under `/tmp/penderie-visual`, not application assets.

## Verification update after the handover was saved

- Final frontend lint and TypeScript passed. The parallel full Vitest run then had **75 passes and two registration-test timeouts** while Chromium and Cypress were also running. These tests had passed earlier; they must be rerun without competing browser jobs before treating the latest suite as green. The sequential rerun is logged in `/tmp/penderie-final-frontend.log` and also runs a fresh production build if tests pass.
- The second full Cypress campaign completed with **9/13 passing**: login 3/3, original home 2/2, inventory/scanner 4/4. Registration 0/3 and household verification 0/1 had loaded the stale curly-apostrophe button selector. Source now uses `button.register__submit`; a targeted final rerun of those two specs is logged in `/tmp/penderie-cypress-auth-final.log` after the frontend checks.
- The final browser report was written in the frontend container at `/tmp/penderie-visual/results.json` (29 October, 22:58 UTC). Collect its result rather than relying on a still-empty or earlier host log. The temporary runner processes have finished.
- No additional implementation phase was started after the user's request to save the session. This update only records outstanding verification.

### Final browser result collected

`/tmp/penderie-browser-check.log` confirms **75/75 rendered cases**, **zero horizontal overflows**, **zero JavaScript page errors**, and **zero broken/zero-size visible images**, including the newly exported glyph assets. The article-details form, profile, profile edit, collections and private vitrine now fit the reference minimum height. Screens with extra real members, camera errors, explicit share consent or storage-management controls intentionally scroll. This checks rendering/assets, not pixel-perfect equality or real-backend behavior.

### Sequential frontend result collected

The resource-isolated rerun passed **77/77 frontend tests across 13 files** (`npm test -- --maxWorkers=1`). This resolves the two registration-test timeouts seen during concurrent browser/Cypress work; no registration UI change was needed. The latest lint and TypeScript checks are also clean. The fresh production build and corrected registration/household Cypress rerun were queued immediately afterward; read their final logs before marking those completed.

### Final production build collected

The final `tsc -b && vite build` completed successfully (Vite built in 3.62 seconds). The latest source therefore has clean lint, a passing TypeScript check, 77 passing frontend tests, a passing production build and the 75-case browser result. Only the corrected registration/household Cypress rerun was still in progress at this update.

### Review items to keep visible in the next session

These are implementation details worth exercising with real data before calling the whole feature production-ready, not confirmed passing behavior:

- Collection membership is persisted as item IDs in the private workspace JSON. Review cleanup/counts when an item is permanently deleted; there is no dedicated relational collection-member table yet.
- The edit wrapper adds newly selected photos through the existing photo endpoint. Review primary-photo selection when an article already had photos; the original primary/ordering semantics may keep the old image first.
- The new page family uses mostly French copy inline, consistent with several existing app pages; a future full English pass should centralize these new strings in the existing i18n layer.
- The private vitrine supports collection pins and order reversal; arbitrary drag reordering and direct article publication are not implemented.

Do not silently claim these review items or the documented missing audience/invitation/child-login flows have been completed.

### Final corrected Cypress result collected

`/tmp/penderie-cypress-auth-final.log` reports **all specs passed, 4/4 tests**: registration 3/3 and household verification 1/1. This resolves the remaining selector failures. Combined with the earlier successful login/home/inventory specs, all 13 existing E2E cases have passing evidence across the final runs. There are no test processes left to collect for this task.

Next-session priority: read the authoritative final status at the top, preserve all uncommitted work, review the explicit real-data follow-ups, and continue the chosen missing backend scope without redesigning or restarting the application.

## Version-control handoff requested after implementation

The user requested committing this work on a feature branch, explicitly excluding `main` and `develop`. The existing branch `feature/social-figma` is used for that commit and is intended for a later PR targeting `develop`. The session implementation, documentation, tests, migrations, exact Figma SVGs, and the user's existing Salon PNG-to-SVG change are included together so the checkout builds without missing assets. See the branch's `git log` for the resulting commit ID. No remote push, PR creation or merge was requested or performed. Earlier references to uncommitted work describe the state before this handoff request.
