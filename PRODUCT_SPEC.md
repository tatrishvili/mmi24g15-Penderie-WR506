# Penderie — Product specification

**Status:** Working product direction after discussion on 8 October 2026. Personal-first onboarding, optional family setup, restricted child access on the child's own device using a parent-authorized username/password, and France-first/French-first localization reflect the user's stated preferences; remaining authentication and legal controls are recommendations to confirm before implementation.  
**First market:** France. **Product languages:** French first; English must be supported by the product architecture.

This is the canonical description of what Penderie is and the decisions that should guide product and code changes. Read [WORKFLOWS.md](./WORKFLOWS.md) for step-by-step user journeys, [PERMISSIONS.md](./PERMISSIONS.md) for the access matrix and sharing rules, and [README.md](./README.md) for development setup. If existing code disagrees with this specification, do not assume the code is the intended behavior: call out the gap and make an implementation plan before changing data or access rules.

## 1. Product purpose

Penderie is a simple, private inventory for clothes and other personal or household belongings. It helps people:

1. remember what they own;
2. find where an item is;
3. record who an item belongs to and who borrowed it;
4. decide whether to keep, wear, lend, share, sell, give away, or archive it.

The core promise is **scan or add quickly, correct the details, and find the item later**. Penderie is not a public marketplace or a complex home-management system.

## 2. Product decisions

These decisions resolve earlier ambiguities. Treat them as defaults for the first useful release.

### 2.1 Personal-first onboarding

- A person starts registration with a unique username, email address, self-declared date of birth, and password, then receives a single-use verification link. Usernames are case-insensitive, normalized to lowercase, and contain 3–30 letters, numbers, or underscores. Email remains the sign-in identifier.
- Adult registration requires a self-declared birth date showing the person is at least 18. This check is not identity proof or verified age assurance; a person can enter a false date. Minors use a parent-managed child profile, not an adult account.
- Before verification, store an expiring pending registration with the password securely hashed, username and birth date, and a hash of the single-use verification token; it is not an active account and cannot access inventory.
- Store the birth date on the account after verification only to enforce the adult-registration rule. Do not include it in authentication responses or display it in the profile. The user must be told it is self-declared and how the data is used; production retention/deletion requirements need review before launch.
- Opening the email verification link presents an explicit confirmation page. A POST then atomically creates the adult account using the pending password hash, establishes the signed-in session, creates a private personal space with a default owner profile and default place, and opens the **Créer mon foyer** setup page. The user can create an additional household and continue through the existing setup screens, or skip directly to the automatically provisioned dressing.
- There is no second manual approval and no need to enter credentials again after clicking the link. The only unavoidable wait is delivery/opening of the verification email.
- **Known security trade-off:** before verification, someone can submit another person's email with a password they chose. If the email owner follows that confirmation link, that password becomes the account password. This flow prevents access before confirmation but does not prevent account pre-hijacking; use password-after-verification if preventing that risk becomes a requirement.
- Do **not** require a household/family name, first name, room layout, child, or partner during registration.
- Use friendly default labels such as **My wardrobe** / **Mon dressing**. Let the person rename their space or add a display name later in Settings.
- “Household” may remain an internal data-model term for now; the user-facing product should say “My space” or “My wardrobe” until they choose to add family members.
- The chosen username is a unique account handle shown in the signed-in account page. It is not the login identifier or a public sharing identity; continue to sign in with the verified email.
- Foyer setup is offered after email confirmation and can be skipped. Family and profile management remains optional and accessible from Settings.

The current database already uses a `Household` as the item scope. Preserve that boundary initially: create a private household automatically and hide the technical “household” concept in the UI. Do not redesign or migrate this model casually.

### 2.2 Family is optional; children have restricted access

Initial permission matrix:

| Action | Parent/adult | Child |
| --- | --- | --- |
| View own items | Yes | Yes |
| View child items | Yes, for children they manage | Own items only |
| Add items | For any profile they manage | Own profile only |
| Edit item details/location | For any profile they manage | Own items only |
| Archive/restore | For any profile they manage | Archive own items; parent can restore |
| Permanently delete | Confirmed parent action | No |
| View/edit another child's or adult's private items | Per adult membership/permissions | No |
| Change members, security, sharing, sale, lending | Per adult role | No in the first version |

The child can choose an approved place/room/storage location for their own item. A parent remains able to correct the item, archive it, and revoke the child's session.

- An adult can use Penderie alone indefinitely.
- From **Settings → Family and profiles**, an adult may add a child profile, invite another adult, or manage existing members.
- A parent can see and manage a child's wardrobe, correct or archive its items, and disable the child's access.
- A child may sign in on their own device and add or manage **their own** items within explicit limits.
- A child must not see another child's or an adult's private items, manage family settings, invite people, change permissions, or share/sell/lend items unless a parent explicitly enables a future capability.
- The server—not merely the frontend—must enforce these restrictions for every endpoint.
- A child account/profile is created or activated only by an authenticated parent/guardian. The parent can revoke its sessions and reset its access.

### 2.3 A PIN is not a child's remote account password

Do **not** treat a 4–6 digit PIN plus a public/guessable household code as secure sign-in for a child on the internet. Hashing the PIN protects it if the database is exposed, but does not make the PIN hard to guess. An IP-only request limit is also not enough to protect an account against distributed attempts.

Recommended separation:

- **Child sign-in on their own device:** a parent-authorized child credential and a restricted server-side session. Prefer a one-time, high-entropy parent-issued setup link/code, followed by a child username and password (no child email required for the first version). Parent recovery/reset and session revocation are mandatory.
- **PIN on a parent's already trusted device:** optional convenience lock/profile switch only. It must require the parent's existing authenticated session, be rate-limited by account/profile and device/IP, and never replace or elevate that session.
- Do not publish household codes or child profile lists as a security boundary. Login errors should not reveal whether a household, child, username, or PIN exists.
- Add stronger sign-in protection, secure cookies, CSRF protection appropriate to the deployed architecture, and session rotation/revocation before production.

The standalone child PIN login has been removed. A PIN can only unlock a profile within a parent's existing authenticated session; the child cannot yet sign in on a separate device. Item endpoints still need consistent child-to-own-profile authorization, so do not add child credentials until that server-side work and its allowed/denied tests are complete.

### 2.4 Verification, age assurance, and two-factor authentication

These are different controls:

- **Email verification** proves control of the email address. Registration should create a short-lived pending registration, send a single-use verification link, and create/activate the actual account only when that link is confirmed. Complete sign-in and personal-space setup in that same verification callback; do not require the user to register or sign in again. This is not 2FA.
- **Two-factor authentication (2FA)** asks for an additional factor at sign-in. Plan an optional adult-account authenticator-app code (TOTP) with one-time recovery codes; passkeys can follow. Do not use SMS as the default second factor. Never make email confirmation alone count as a second factor when the same email account can reset the password.
- **Age assurance** is separate from the registration age gate. Registration now requests a date of birth and calculates whether the user is 18; this is still self-declared and is not reliable verification. Do not describe it as verified age assurance. Any stronger check requires an explicit privacy, legal, and provider-cost review.
- For the France-first launch, document the intended adult/guardian responsibility and have the actual age/child-consent process reviewed for the launch jurisdiction. Requirements vary by service and country; this document is product guidance, not legal advice. Support English text later without changing the underlying consent or age policy.

Do not add more registration questions to compensate for missing security controls. Email verification, 2FA, guardian approval, and age assurance each need their own explicit workflow.

### 2.5 Item data and locations

One `Item` means one physical object. Its fields should be conceptually separate:

- **Identity:** name, photos, brand, color, size, material, condition.
- **Classification:** optional category and subcategory; answers “what is it?”
- **Ownership:** owner profile; answers “whose item is it?”
- **Current location:** optional path through a place, room, and nested storage locations; answers “where is it?”
- **Lifecycle:** available, loaned, sold, given away, or archived. A loan is also a separate historical record, not just a text status.
- **History:** loans, moves, wear events, and other actions are records; editing current details must not erase them.

Recommended location tree:

```text
Personal space
  └── Place (Home, holiday home, storage unit)
       └── Room (Bedroom, dressing room, garage)
            └── Storage location (Wardrobe → Shelf → Box)
                 └── Item
```

The owner profile and category are not levels in the location tree. A place, room, category, and precise location should be optional; a short item name is enough to save an item.

### 2.6 Scanning and batch adding

- Photo recognition is optional assistance, not truth, and local development must not require a paid provider. Free providers may be temporarily rate-limited; show that clearly, preserve the photo, and let the user retry later or continue with manual entry.
- The model may suggest visible facts such as item type or color. It must not invent owner, exact location, price, purchase date, or condition that cannot be judged from a photo.
- A single scan creates a **draft for one item**. Offer only four scan hints—T-shirt, trousers, skirt, and other/object. The first reliable milestone is one photo → one reviewed item → one saved record.
- After that works, add a batch session: scan several objects, keep each as a separate draft, and allow the user to assign or change owner, place, room, and storage location per item.
- A “scan another in this location” action may reuse the last selected owner/location as a visible default. Never silently apply a previous room or box after the user changes context.
- For physical moves, let the user choose a place/room/storage location first, then scan a sequence of items into that context; show the selected context and let them correct an individual item.
- If one photograph contains several objects, do not automatically create several inventory records until multi-object detection, item boundaries, and review UX are designed and tested.

### 2.7 Privacy and sharing

- All spaces, profiles, items, photos, and precise storage locations are private by default.
- Adult users may follow another adult account directly; follows are one-way and do not require recipient approval. A friend request also follows its recipient. Accepting a request makes the relationship mutual; declining does not remove the requester as a follower. Either party can unfollow, and blocking prevents future contact. A child profile cannot follow, send, accept, or manage adult social relationships.
- Being friends does not grant access to an inventory, item, collection, profile, home, room, or storage location. Every future share must still be explicit, scoped, and revocable.
- Normal follow and friend-request use must not be blocked by an arbitrary low hourly quota. Use idempotent relationship actions and proportionate, abuse-focused protections; do not charge failed lookups or duplicate actions as new requests.
- A child’s items are not visible to strangers. A parent/guardian with the proper household role can manage them.
- Only a signed-in adult with verified email and authority over the resource can create or revoke a share. A child cannot publish/share in v1; a parent/guardian must explicitly approve any external publication of a child's item or look.
- For each share link, the owner chooses whether anyone possessing the unguessable link can view read-only without signing in, or whether a signed-in account with verified email is required. Anyone-with-link is a bearer credential and can be forwarded; it does not verify the recipient's identity.
- Published adult looks can be viewed without signing in, but show only a deliberately selected representation. A look does not make its underlying item records public. Child looks remain private unless parent-approved.
- Interactive capabilities such as commenting require an authenticated account with verified email plus an explicit grant. Link-only anonymous viewers cannot comment. Lending and buying require separate eligible, authenticated workflows; no public marketplace in v1.
- Sharing is a deliberate action with a defined audience, resource scope, access mode, and separate capabilities. Visibility does not automatically grant permission to act.
- A QR code is a lookup convenience, never an authorization credential.
- A public or share link must be high-entropy, scoped, revocable, optionally expiring, and must not expose precise home locations by default.
- The user can remove an item from their active wardrobe without silently destroying its loan/activity history. Prefer archive/soft delete when history matters.

See [PERMISSIONS.md](./PERMISSIONS.md) for the viewer/resource matrix, decision order, child/parent exceptions, and known mismatches in the current permission code/tests.

### 2.8 Language and localization

- France and French are the first launch market/language.
- The product should offer French and English in the future. Keep user-visible copy out of business logic and centralize it in translation resources from the start of new UI work.
- Include all forms of feedback, validation, empty states, emails, and accessibility labels in localization work—not just navigation labels.
- Locale changes presentation only; it must not alter stored domain values, permissions, dates, or API contracts.
- Keep repository and engineering documentation in English, consistent with the repository convention.

## 3. Information architecture

Keep the main navigation small:

- **Dressing / Items:** browse, search, filter, scan, and add.
- **Places:** browse the places, rooms, and storage locations needed to find things.
- **Activity:** open loans and item history when lending is implemented.
- **Settings:** account security, language, personal-space name, optional family/profiles, child access, privacy, and deletion/export controls.

Do not place family setup in registration or make a child/partner a prerequisite to use the inventory. Show family features as optional, discoverable actions inside Settings.

## 4. Codebase architecture guidance

The existing Symfony API + React frontend + MariaDB monorepo is a reasonable starting structure; a broad folder rewrite is not needed to make the product coherent.

- Keep Symfony as the authority for authentication, validation, authorization, persistence, and business rules.
- Keep API controllers thin: parse HTTP input, call an application/service operation, and return a stable response. Keep domain rules and access checks out of React components.
- Keep Doctrine entities focused on persisted domain relationships; use migrations for schema changes. A database relationship is not a permission check.
- On the frontend, keep pages for route-level flows, reusable UI in components, API calls in `src/api/`, and shared API/domain types in `src/types/`.
- Keep localization resources separate from domain values and never persist translated labels as enum values.
- Prefer complete vertical features over speculative abstractions: entity/migration, API, permission tests, UI, and user-facing errors together.
- Before renaming `Household`, changing account/profile relationships, or splitting packages, write a migration/compatibility plan and ask if the product decision is not settled.

## 5. Product boundaries and priorities

### First useful product

1. Adult account and verified email.
2. Private personal space created automatically.
3. Manual item creation, safe photo storage, browse/search/edit/archive.
4. Clear optional category and current-location organization.
5. One-item photo scan with a reliable editable result and a manual-entry fallback.
6. Account deletion/export and secure session lifecycle.

### After the core is dependable

1. Parent-managed child access with server-side object-level authorization and device sessions.
2. Adult invitation/acceptance; do not exchange another adult's password.
3. Places → rooms → nested storage locations; multi-item scan batches.
4. Loan/return history and reminders.
5. Revocable sharing with explicit permissions.
6. Controlled sale/gift flows, then outfits and wear insights.

### Not first-release goals

- Public marketplace, shipping, or dispute resolution.
- Requiring a family/household name or family member to get started.
- Guessing detailed clothing facts with AI without user review.
- Treating a QR code, household slug, or short PIN as proof of authorization.
- Building outfits or social sharing before the basic inventory can be trusted.

## 6. Current code versus intended product

The following are known differences between this specification and the repository snapshot:

| Area | Current code | Required direction |
| --- | --- | --- |
| Registration | Pending email registration, unique account username, self-declared date-of-birth age gate (18+), single-use confirmation, rate-limited resend, and automatic private-space setup are implemented. The username is displayed in the signed-in account page; verified email remains the login identifier. Existing verified accounts without a username can set one once from that page. Local delivery uses Mailpit; production delivery still needs a verified sender/domain and provider credentials. | Do not call the birth-date check age verification. Review the lawfulness, retention, deletion, and data minimization of collecting birth dates before production; use a reviewed provider only if stronger age assurance is required. |
| Family | Child/partner creation appears in the profile flow after a household is created. | Optional family management in Settings. Another adult should accept an invitation and use their own account. |
| Child sign-in | Standalone PIN sign-in and public household/profile lookup are removed. A parent can optionally unlock a child profile on their already authenticated device. | Parent-managed one-time child setup and child credentials for another device; enforce per-profile permissions on every server request before enabling it. |
| Friends and followers | Verified adults can follow/unfollow, see follower/following lists, and send, accept, decline, cancel, or remove friend relationships by username. A friend request follows its recipient; accepting creates mutual follows, while declining/cancelling preserves the follow. Repeated direct follows are idempotent. A verified legacy account can set a missing username once. Following does not grant inventory visibility. The former fixed hourly friend-request limiter has been removed. | Add account search/discovery and block/report controls. Add proportionate abuse protection that does not block ordinary use or count invalid/duplicate actions as new relationships. |
| Item authorization | Item list/show/update/delete are household-scoped. Items store an owner profile, but endpoint access is not consistently restricted to that owner/role. | Parent may manage child items; a child sees and changes only allowed items. Add authorization tests before child access is treated as safe. |
| Item structure | Items persist optional type/category and an optional room plus nested storage location. The “Lieux” workflow manages places, rooms, and storage; item forms allow assigning or leaving the current location unknown. API references are scoped to the active private space. The hierarchy migration is applied to the local development database; target deployments must run pending migrations. | Keep location optional and separate from item ownership and category. Add explicit place-only assignment only if the product needs it. |
| Scan | Opening the scanner requests the device camera automatically and shows an in-app preview with a simple guide for T-shirt, trousers, skirt, or other/object. Gallery selection remains available while the camera is active. Camera access requires browser permission on HTTPS or localhost. The guide is visual framing assistance, not real-time object detection. | Preserve clear camera permission guidance and continue to let the user review or enter the item manually. |
| Scan error behavior | Provider failures, including temporary free-provider rate limits, are shown as errors; the captured photo is retained for retry or manual entry. Results remain editable before saving. | Keep invalid configuration, provider failures, and invalid model output explicit; never return blank success-shaped identification or guess fields the photo cannot establish. |
| Email and 2FA | Email verification before account activation is implemented; adult 2FA is not. | Configure production mail delivery and implement a separate adult 2FA setup/recovery workflow. |
| Multiple scans | Item creation and scanning remain single-item flows. | Add reviewed multi-item batches with per-item owner and location only after the single-item path is dependable. |

These gaps mean “there is a screen or endpoint” must not be reported as “the user workflow is complete.” Keep this table current when implementation changes.

## 7. Decision log

- **2026-10-08:** Personal-first onboarding; no required family name or family-member setup during registration.
- **2026-10-08:** Parents manage child collections; children should be able to sign in on their own device with restricted permissions.
- **2026-10-08:** Use a parent-authorized child username/password (no child email required) for own-device sign-in; a short PIN is only a convenience unlock on a trusted device.
- **2026-10-08:** France is the first market; English is a planned product language. Legal age/child-consent requirements need jurisdiction-specific review.
- **2026-10-08:** Single-item scan/review/save comes before batch scanning; location is per item and optional.
- **2026-10-08:** Only verified adults with resource authority can create/revoke shares. Owners choose anonymous read-only link access or verified-account access; interactive capabilities require a verified account.
- **2026-10-08:** Adults may manage non-Personal items in a shared household. A child's responsible parent/guardian retains access to that child's Personal items; other peers and external viewers do not.
- **2026-10-08:** Adult looks may be deliberately published for anonymous viewing without exposing source items; child looks require parent/guardian approval before external publication.
- **2026-10-08:** Registration collects email and password before sending verification; only the hashed password is kept pending, and confirmation creates the account and private starter space.
- **2026-10-09:** The optional physical organization path is Place → Room → nested Storage location → Item. Item location is optional; scanner hints and framing overlays guide composition but do not claim real-time object detection.
- **2026-10-12:** The place/room/storage migration is applied to the local development database. Deployment databases must run their pending migrations before using the location workflow.
- **2026-10-12:** Adult registration asks for a unique username and date of birth, blocks self-declared ages under 18, and keeps email as the sign-in identifier. This is an age gate, not verified age assurance; minors use parent-managed profiles.
- **2026-10-13:** Friend requests are adult-only, use account usernames, and require recipient acceptance. Friendship alone never reveals inventory. A PIN is only a trusted-device profile unlock inside a parent's authenticated session; child own-device sign-in remains disabled until child-owned item authorization is complete.

When a decision changes, update this log and both the product specification and affected workflow docs in the same change.


### Scanner review and item-specific details (1 November 2026)

The scanner offers T-shirt, trousers, skirt and object framing outlines contained
inside the camera preview. Capturing or selecting a photo immediately replaces
the camera screen with a dedicated review screen, including while analysis runs
or fails. Retake and remove are available there; stale analysis responses cannot
restore a discarded photo. Manual entry remains available without AI.

The editable item type includes common clothing and accessories plus books,
vinyl records, CDs and household objects. Clothing shows brand, size, material
and color; books show author and publisher; music shows artist/band and label.
Condition is visible for every type. Author, artist and publisher have separate
validated database fields and are not stored as brands. Only clearly readable
creator/brand information should be suggested by photo analysis; all suggestions
remain editable. Changing type adapts the fields before saving.

Migration `Version20261101160000` adds nullable author, artist and publisher
columns and must be applied in other environments before using these fields.


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
