# Penderie — User workflows

Use this document for the intended user journeys. [PRODUCT_SPEC.md](./PRODUCT_SPEC.md) is the source of truth for product decisions, domain boundaries, privacy, and implementation gaps.

## 1. First visit and adult registration

Registration should be quick. It must not ask someone to create a family before they can use their own inventory.

1. The adult enters an email address and password and accepts the applicable terms/privacy notices.
2. Penderie stores a short-lived pending registration (not an active account) and sends a single-use, expiring verification link. Store the password and token securely as hashes; do not permit inventory access yet.
3. The user clicks the link. That action verifies the email and completes account creation; no separate approval or second registration is needed.
4. In the same successful flow, Penderie signs the user in, automatically creates a private personal space with a default owner profile and default place, and redirects straight into the app. Do not ask for a household name, room list, partner, or child here.
5. The user can add an item immediately. Display name and space name can be added or changed later in Settings.
6. Offer optional family setup in **Settings → Family and profiles** when the user is ready.

If the email is delayed, provide a clear “resend verification” action with rate limiting. Verification links must be single-use, expire, and avoid exposing whether an email is already registered. A verified email is not 2FA.

For registration, do not describe an “I am 18+” checkbox as age verification. Age assurance and parental/child consent need their own reviewed policy. Do not collect date of birth by default. A later adult 2FA setup is a separate security workflow: authenticator app plus recovery codes first; passkeys can follow.

## 2. Optional family setup

### Parent adds a child

1. An authenticated adult opens **Settings → Family and profiles → Add child**.
2. The adult gives the child a display name and creates/activates restricted child access.
3. The parent authorizes the child's own device with a high-entropy, short-lived, one-time setup link/code. The child sets a username/password; a child email address is not required for the first version.
4. The child signs in and receives a child-scoped session. On their own device they can add and manage their own items within the permissions described in section 3.
5. The parent can view and manage the child's inventory, reset the child's credentials, revoke sessions, disable access, and archive/remove the child's items.

Do not use a household slug plus a 4–6 digit PIN as the child's only internet sign-in. A short PIN may be an optional quick unlock on a trusted parent device; it must not grant adult permissions or replace a child account session on another device.

### Parent invites another adult

1. The signed-in adult enters the other adult's email in **Settings → Family and profiles → Invite adult**.
2. Penderie sends a time-limited invitation.
3. The invited adult signs in or registers with their own credentials and accepts the invitation.
4. The owner can remove the adult's membership and revoke access.

Never ask the first adult to set or know another adult's password. The current “create partner with email/password” form is not the desired invitation workflow.

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
- An item has at most one current location; unknown/not stored yet is valid.
- Categories form a separate optional hierarchy, e.g. `Clothing → Tops → Sweaters`.
- Moving an item changes its current location but does not erase its previous loan or activity history.
- Before deleting a location, move its items or mark their locations unknown.

## 6. Scan and review a photo

### Single-item scan (first dependable milestone)

1. The user chooses the object/garment type and, if useful, selects the intended owner and location.
2. The app explains how to frame one item. A decorative guide shown before opening the native camera is **not** a live camera overlay; if a live framing overlay is required, implement an in-app camera preview and validate camera permissions/device behavior separately.
3. The user captures or selects one image.
4. The client resizes/compresses the image and sends it to the backend. The OpenRouter key stays on the backend.
5. The backend validates the image and sends it to the configured vision provider.
6. The app displays the image and an editable draft. Clearly mark uncertain/unknown fields; do not guess condition, exact category, brand, owner, price, purchase date, or location when the image cannot establish them.
7. The user reviews/corrects the draft and confirms. Only then does Penderie persist the item and photo.
8. If analysis fails, preserve the photo and offer manual entry with a clear error. Never show blank fields as a successful AI identification.

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
| Adult registration | Email/password flow exists, but it signs in immediately and then asks for first name and household name. The frontend's “18+” checkbox is not sent to the API. Email ownership verification is not implemented. |
| Child access | Profile PIN login exists with a 4–6 digit PIN and IP-based throttling. It is not the intended secure own-device credential flow. Public household/profile discovery and rate limits do not replace account-level security. |
| Authorization | Item endpoints are household-scoped, while item ownership is stored on a profile. Current list/update/delete paths do not consistently enforce child-to-own-profile access; fix and test this before describing child access as secure. |
| Permission matrix | A pure resolver and tests exist, but tests currently allow a stranger/link holder with a COMMENT grant to comment and apply PERSONAL as an absolute veto. Both conflict with the agreed verified-comment and parent/guardian-management rules; see [PERMISSIONS.md](./PERMISSIONS.md). |
| Item model | Basic items/photos/QR exist. Category, rooms, and hierarchical storage are not yet wired end-to-end through the item entity, API, and UI. |
| Scan capture | The page has a garment guide and opens the device's native camera/gallery picker. The guide is not overlaid on a live in-app camera preview. |
| Scan provider/errors | OpenRouter integration exists. In the current client, missing API configuration or unparsable model output can return an empty success-shaped result; unknown condition can become “damaged”. This can look like scan succeeded when it did not. |
| Scan persistence | The form/scan flow exists, but fields such as category/type are not all persisted by the current item model/API. |
| Places and batch scan | The complete place → room → nested storage workflow and batch scan are future work. |
| Email verification and 2FA | Neither flow is implemented. |
| Loans, user-facing sharing, sale, outfits | Future work unless a complete persistence/API/UI/permission flow is added. |

## 11. Recommended build order

1. Agree and implement personal-first verified adult account and automatic private space.
2. Secure the existing item/photo endpoints and finish core item create/list/detail/edit/archive/search.
3. Add optional category and place → room → storage with migrations and per-item assignment.
4. Make the single-item scan honest and dependable: configuration checks, real failures, validated response, editable draft, confirmed persistence.
5. Implement parent-authorized child credentials and object-level permissions before enabling child device sign-in.
6. Add adult invitation/acceptance and session revocation.
7. Add reviewed batch scanning, then lending/return.
8. Add privacy-safe sharing, controlled sale/gifting, then outfits and insights.

For every step, deliver a vertical slice: database/migration, API contract, server validation and authorization, frontend flow, and positive/negative tests. Update [PRODUCT_SPEC.md](./PRODUCT_SPEC.md) when a product decision changes.
