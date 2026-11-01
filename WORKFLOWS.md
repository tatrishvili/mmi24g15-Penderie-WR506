# Penderie — User workflows

Use this document for the intended user journeys. [PRODUCT_SPEC.md](./PRODUCT_SPEC.md) is the source of truth for product decisions, domain boundaries, privacy, and implementation gaps.

## 1. First visit and adult registration

Registration should be quick. It must not ask someone to create a family before they can use their own inventory.
For the repeatable local test account and browser walkthrough, follow [README.md](./README.md#repeatable-local-smoke-test).

1. The adult chooses a unique username, enters an email address and date of birth, and chooses a password. Usernames are case-insensitive (stored lowercase), 3–30 letters, numbers, or underscores. Email remains the sign-in identifier.
2. Penderie calculates the self-declared age from the date of birth and rejects registration if the user is under 18. This is an age gate only: it does not verify identity or prevent someone from entering a false date. Minors use a parent-managed child profile.
3. Penderie stores a short-lived pending registration with the username, birth date, password hash, and hash of its single-use verification token; the email contains only the verification link/token, never the password or birth date. A pending registration is not an active account and cannot access inventory.
4. The user opens the link and explicitly confirms on the Penderie page. The API must use POST for confirmation so email-security scanners that prefetch links cannot consume the one-time token.
5. Confirmation verifies the email and creates the account; no separate approval or second registration is needed. The birth date is retained for the registration age rule but omitted from auth responses and profile display.
6. In the same successful flow, Penderie signs the user in, automatically creates the private **Mon dressing** space with the **Moi** owner profile and a default **Maison** place, and opens **Créer mon foyer** (`/foyer/creer`). The user may create an additional household and continue through members, rooms and storage, or choose **Je le ferai plus tard** to use the automatically provisioned dressing immediately. Family members are optional.
7. The user can add an item immediately. The chosen username is visible on the signed-in account page; sign-in continues to use email and password.
8. Offer optional family setup in **Settings → Family and profiles** when the user is ready.

An existing verified account created before usernames were required may not have a username. Its owner can choose one once from **Mon compte**; use that username, not the account email, for friend requests.

If the email is delayed, provide a clear “resend verification” action with rate limiting and a cooldown. Verification links must be single-use, expire, and avoid exposing whether an email is already registered. A verified email is not 2FA. Before production, review the legal basis, retention period, and deletion process for birth dates; this self-declared age gate is not verified age assurance.

For registration, do not describe an “I am 18+” checkbox as age verification. Age assurance and parental/child consent need their own reviewed policy. Do not collect date of birth by default. A later adult 2FA setup is a separate security workflow: authenticator app plus recovery codes first; passkeys can follow.

## 2. Optional family setup

### Parent adds a child

1. An authenticated adult opens **Settings → Family and profiles → Add child**.
2. The adult gives the child a display name and creates/activates restricted child access.
3. The parent authorizes the child's own device with a high-entropy, short-lived, one-time setup link/code. The child sets a username/password; a child email address is not required for the first version.
4. The child signs in and receives a child-scoped session. On their own device they can add and manage their own items within the permissions described in section 3.
5. The parent can view and manage the child's inventory, reset the child's credentials, revoke sessions, disable access, and archive/remove the child's items.

Do not use a household slug plus a 4–6 digit PIN as the child's only internet sign-in. A short PIN may be an optional quick unlock on a trusted parent device; it must not grant adult permissions or replace a child account session on another device.
The current app supports only this trusted-device profile unlock: the adult must already be signed in, choose a profile, and enter its optional PIN. The former public household-code/profile-list sign-in is intentionally unavailable. Children cannot yet sign in on a separate device; do not treat the current profile screen as a child account.

### Parent invites another adult

1. The signed-in adult enters the other adult's email in **Settings → Family and profiles → Invite adult**.
2. Penderie sends a time-limited invitation.
3. The invited adult signs in or registers with their own credentials and accepts the invitation.
4. The owner can remove the adult's membership and revoke access.

Never ask the first adult to set or know another adult's password. The current “create partner with email/password” form is not the desired invitation workflow.

### Adult friend requests

1. A signed-in adult finds another adult by username and can follow them immediately. The target does not need to approve a follow.
2. The adult may also send a friend request. Sending the request follows the recipient, and the recipient sees the pending request without either account's email being disclosed.
3. The recipient can accept to become mutual friends or decline. Declining the request does not remove the sender as a follower.
4. Either person can unfollow. Blocking prevents future follows and friend requests.
5. Following or friendship alone does not show items, profiles, collections, places, or storage locations. Sharing remains a separate, explicit, scoped action. Child profiles cannot follow or send/accept friend requests.
6. Ordinary follow and friend-request use must not hit an arbitrary low hourly quota. Duplicate actions should be safe, invalid lookups should not consume new-relationship allowance, and any targeted anti-abuse delay must explain when the action can be retried.

The app now implements direct follow/unfollow and follower/following lists alongside friend requests. Account search/discovery and blocking/reporting remain to be implemented. Apply the latest database migration before testing follows. See [FRIENDS_AND_FOLLOWERS.md](./FRIENDS_AND_FOLLOWERS.md) for the observed problems and acceptance checks.

## 3. Sign in, switch profiles, and enforce permissions

- Adults sign in with their own verified account. If multiple spaces are supported later, choose a space after sign-in.
- A child signs in with their parent-authorized child credential and receives a restricted session. Child sessions must not be implemented as an adult session with only a different frontend screen.
- An adult may switch the active owner profile when adding an item. A child may act only as their own profile.
- A parent can manage all child inventory in their space. A child may view/add/edit their own permitted items, but cannot view siblings' or adults' private items, change family/security settings, manage access, or share/sell/lend unless a parent-enabled feature explicitly allows it.
- Every list, detail, create, update, delete, photo, QR, and action endpoint must enforce the actor's permissions server-side. Test both allowed and denied access across profiles and spaces.
- Signing out revokes the current session. Parents need a way to revoke a child's other sessions and reset the child's access.

An item belonging to the same household does not automatically mean every household profile may see or edit it.

## 4. Add one item manually

1. Choose **Add item**.
2. Enter a short name. The default owner is the active profile; allow the user to change it only to a profile they are authorized to manage.
3. Optionally choose category, place, room, storage location, photos, brand, color, size, material, condition, price, and notes.
4. Save. Only a name is required for basic inventory.
5. Show the saved item with its current owner and location; allow editing, moving, archiving, or adding photos later.

The owner profile answers **whose item?**. Category answers **what is it?**. Place/room/storage answer **where is it?**. Do not collapse these into one field or make the user configure all of them first.

## 5. Places, rooms, storage, and categories

Manage locations from **Lieux** in the app, independently from account registration or family setup. Add one or more places, add rooms under a place, then add storage units and nested shelves/boxes under a room. Item entry may stop without a location; users can organize the location tree first or add an item immediately and assign its location later.

Use an optional location path:

```text
Place (Home)
  └── Room (Bedroom)
       └── Storage (Wardrobe)
            └── Storage (Top shelf)
                 └── Item
```

- A household can have more than one physical place.
- Rooms belong to a place. Storage locations belong to a room or parent storage location.
- A storage location can be nested, e.g. `Wardrobe → Shelf 2 → Box 4`.
- Room creation offers common presets (dressing room, living room, bedroom, kitchen, bathroom, entrance, office, garage, cellar, attic, laundry room) plus a custom room name. Presets are suggestions only; users can choose any name.
- An item has at most one current location; unknown/not stored yet is valid.
- Categories form a separate optional hierarchy, e.g. `Clothing → Tops → Sweaters`.
- Moving an item changes its current location but does not erase its previous loan or activity history.
- Before deleting a location, move its items or mark their locations unknown.

## 6. Scan and review a photo

### Single-item scan (first dependable milestone)

1. The user chooses one of four hints—T-shirt, trousers, skirt, or other/object—and, if useful, selects the intended owner and location.
2. Opening the scanner requests the device camera automatically and shows a live in-app preview; no extra camera-open action is required. Camera denial/unavailability is explained, and the separate gallery action remains available both while the preview is active and when the camera cannot start.
3. A simple visual frame matches the selected hint and reminds the user to fit one complete object in view. It is framing guidance only, not real-time object detection or a guarantee that the photo is clear.
4. The user captures one image in the live preview or separately selects an image from the gallery.
5. The client resizes/compresses the image and sends it to the backend. The OpenRouter key stays on the backend.
6. The backend validates the image and sends it to the configured vision provider.
7. The app displays the image and an editable draft. Clearly mark uncertain/unknown fields; do not guess condition, exact category, brand, owner, price, purchase date, or location when the image cannot establish them.
8. The user reviews/corrects the draft and confirms. Only then does Penderie persist the item and photo; they can assign its optional room/storage path in the same form.
9. If analysis fails or the free provider is rate-limited, preserve the photo and offer retry or manual entry with a clear error. Do not require a paid provider and never show blank fields as a successful AI identification.

### Batch scan (after the single-item path works)

1. Start a batch and choose a visible default owner and current place/room/storage context.
2. Scan one object per photo; each result is a separate draft. The user can retry, remove a draft, or correct its details.
3. A repeated scan may reuse the displayed default context, but each draft shows its owner and full location and can override them.
4. Before saving, show a review list and identify any required/invalid fields.
5. Save drafts as individual items. Report which items succeeded or failed; never imply a partially saved batch fully succeeded.

When the user moves to another wardrobe or room, they must explicitly change the selected context. One photo containing several objects is a separate multi-object recognition feature and must not silently create records.

## 7. Find and manage items

- The inventory lists only records the current actor is authorized to see.
- Search and filters should progressively include name/brand/color, category, owner, place, room, storage, and lifecycle status.
- Item details show photos, owner, category, current location, current state, and relevant history.
- Editing current details must not erase loan/move history.
- Archive is a reversible way to remove an item from the active dressing. Permanently delete only with clear confirmation and a documented effect on photos/history.
- A QR code is a lookup shortcut. Scanning it must still enforce the same authorization as opening the item normally.

## 8. Lend and return an item

1. The authorized owner chooses **Lend** on an available item.
2. Record the borrower, lent date, optional due date, condition before, and optional note.
3. Create a loan-history record and make the item unavailable for another active loan.
4. Show open loans and who has each item.
5. On return, record the returned date, optional condition after, and note.
6. Close the loan and make the item available again unless another lifecycle action prevents it.

An item may have many closed historical loans but at most one open loan. Do not store the entire loan as a mutable borrower text field or overwrite earlier loans.

## 9. Share, sell, give away, and archive

- Only a signed-in adult with verified email and authority over the resource can create or revoke a share. A child cannot share/publish in v1; a parent must explicitly approve external sharing of a child's item or look.
- For each share link, let the owner choose: **anyone with this unguessable link can view read-only without signing in**, or **a signed-in account with verified email is required**. Anyone-with-link is a forwardable bearer credential, not proof of who the viewer is.
- Adult users may deliberately publish a look for anonymous viewing. Show only the selected look representation; do not grant access to the underlying item or its private fields. Child looks require explicit parent approval and default to private.
- Private is the default. Sharing must be an explicit, revocable action scoped to selected items or a collection.
- Define audience and capability separately: view does not imply comment, lend, or buy.
- Commenting requires a signed-in account with verified email and explicit comment permission. Anonymous link viewers are read-only.
- A link should be random, scoped, optionally expiring, revocable, and must not expose a home's precise storage layout by default.
- The first selling/gifting flow should be a controlled offer to chosen people, not a public marketplace.
- Confirm a sale/gift/archive action and preserve useful history. A transferred item must no longer appear as available in the former owner's active wardrobe.

See [PERMISSIONS.md](./PERMISSIONS.md) for the complete access matrix and rules about parent/guardian access, Personal items, locations, published looks, and link forwarding.

## 10. Current implementation snapshot and blockers

This describes the repository snapshot, not a claim of production readiness:

| Area | Current state / gap |
| --- | --- |
| Adult registration | Unique username, self-declared birth date with an 18+ registration gate, pending email registration, single-use confirmation, rate-limited resend, and automatic private-space setup are implemented. Email remains the login identifier and the birth date is not returned by auth APIs. This does not verify age. Delivery uses Mailpit locally or a configured Symfony mail transport; a verified production sender/domain is still needed before real delivery. |
| Child access | Profile PIN login exists with a 4–6 digit PIN and IP-based throttling. It is not the intended secure own-device credential flow. Public household/profile discovery and rate limits do not replace account-level security. |
| Authorization | Item endpoints are household-scoped, while item ownership is stored on a profile. Current list/update/delete paths do not consistently enforce child-to-own-profile access; fix and test this before describing child access as secure. |
| Permission matrix | A pure resolver and tests exist, but tests currently allow a stranger/link holder with a COMMENT grant to comment and apply PERSONAL as an absolute veto. Both conflict with the agreed verified-comment and parent/guardian-management rules; see [PERMISSIONS.md](./PERMISSIONS.md). |
| Item model and locations | Optional type/category and room/nested storage assignment are wired through item persistence, household-scoped APIs, forms, inventory display, and the Lieux manager. The hierarchy migration is applied to the current local development database; existing deployments must run pending migrations before using these endpoints. |
| Scan capture | Opening the scanner requests the in-app camera automatically. A selected T-shirt/trousers/skirt/object hint updates the visual framing guide; gallery selection is available while the camera preview is active. This guide is not live object detection and requires browser camera permission on HTTPS or localhost. |
| Scan provider/errors | Provider errors and temporary rate limits remain explicit; photos can be retried or used for manual entry. AI assistance is optional and no paid provider is required for item entry. The user reviews all suggestions before saving. |
| Places and batch scan | Place → room → nested storage is implemented as an optional single-item location workflow. Batch scan remains future work. |
| Email verification and 2FA | Email confirmation is implemented; adult 2FA and its recovery workflow are not. |
| Loans, user-facing sharing, sale, outfits | Future work unless a complete persistence/API/UI/permission flow is added. |

## 11. Recommended build order

1. Agree and implement personal-first verified adult account and automatic private space.
2. Secure the existing item/photo endpoints and finish core item create/list/detail/edit/archive/search.
3. Run and verify pending place → room → nested storage migrations in each target deployment; the current local development database is already migrated.
4. Continue hardening the single-item scan: configuration checks, real failures, validated response, editable draft, confirmed persistence.
5. Implement parent-authorized child credentials and object-level permissions before enabling child device sign-in.
6. Add adult invitation/acceptance and session revocation.
7. Add reviewed batch scanning, then lending/return.
8. Add privacy-safe sharing, controlled sale/gifting, then outfits and insights.

For every step, deliver a vertical slice: database/migration, API contract, server validation and authorization, frontend flow, and positive/negative tests. Update [PRODUCT_SPEC.md](./PRODUCT_SPEC.md) when a product decision changes.
