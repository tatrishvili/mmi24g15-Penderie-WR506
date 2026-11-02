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

### Final results from this session

- PHPUnit: 232 tests / 1128 assertions passed.
- Vitest: 77 tests across 13 files passed (final isolated-worker run).
- Frontend lint and TypeScript: clean; production build successful.
- PHPStan, coding standard, schema, container and YAML checks: passed; Composer audit clean.
- Chromium: 75 layout cases, zero horizontal overflows/page errors/broken visible images. Sample responses were used for these layout checks.
- Cypress: 9 existing login/home/inventory cases passed in the full rerun; the four registration/household cases passed in the final targeted rerun after stale selectors were corrected. All 13 cases have passing evidence across those runs.

For the complete chronological handover, authoritative stopping point, version-control handoff and remaining real-data review items, read [session.29.10.2026.md](./session.29.10.2026.md).


### Collection audiences implemented — 1 November 2026

Collections now support `personal` (private), `friends` (accepted friends),
`followers` (people following the owner, plus accepted friends), and `app`
(all signed-in verified adult Penderie accounts). `app` is not anonymous public
web access. Private remains the default. Both creation and existing-collection
settings provide these choices. Discover permitted collections from
**Mes collections → Explorer les collections** (`/communaute/collections`).

A verified adult owner approves a limited publication: collection name and
metadata-stripped cover, plus selected item names, types, colors and selected
photos. The preview digest prevents publishing content that changed after review.
Private notes, memories, prices, household identity, source item/profile IDs,
QR tokens and storage locations are omitted. Item detail endpoints stay private.
Personal, archived, child-owned and other adults' items are excluded. Setting an
item Personal or archiving it removes its published entry; restoring it does not
republish it without approval. Photo requests recheck audience and live vetoes.

Changing membership makes the collection private until the owner reviews and
publishes again. Friends/followers are checked on each read, including photos;
removing a relationship or switching the collection to private revokes access.
Discovery filters audiences before pagination (24 collections per page). A shared
collection can also be opened at `/communaute/collections/:collectionId`.

The additive migration `Version20261101160342` stores approved publications
separately from private workspace data. It is applied locally; other environments
must apply it. This supersedes older private-only collection implementation notes;
external profiles/vitrines, anonymous collection access and item-level audience
publication are separate features and remain outside this change.
