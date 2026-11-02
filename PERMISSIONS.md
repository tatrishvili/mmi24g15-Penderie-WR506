# Penderie — Resource access and permissions

**Status:** Product policy agreed in discussion on 8 October 2026.  
This document defines the intended policy. It does not claim the current API enforces every rule.

Read [PRODUCT_SPEC.md](./PRODUCT_SPEC.md) for the product/domain model and [WORKFLOWS.md](./WORKFLOWS.md) for user journeys. When implementing a permission change, update this policy and its authorization tests together.

## 1. Key principles

1. **Authentication, verification, authorization, visibility, and capabilities are separate checks.**
2. The backend must authorize every request. A hidden frontend button is not a security boundary.
3. Private is the default. Public means deliberately visible to anyone; an unlisted link means anyone possessing the link may have access.
4. A share grants only its explicit resource scope and capabilities. It must not reveal neighboring items, hidden parent resources, or private metadata.
5. `PERSONAL` is a veto against peers and external viewers. A parent/guardian with responsibility for a child retains management access to that child's items, including items marked Personal.
6. A child can manage their own permitted items, but cannot manage household membership, security, sharing, sale, or lending in the first version.
7. A published outfit/look is a separate, deliberately published presentation. It does not make the underlying item records public.
8. Never treat a household slug, item ID, QR code, or short PIN as proof of authorization.
9. Friendship requires an accepted request between verified adult accounts; it is a relationship, not a resource grant or household membership.

## 2. Access level is not a capability

Use one base access level:

| Level | Meaning |
| --- | --- |
| **ADMIN** | Full control of this resource, subject to non-overridable safety/ownership rules. |
| **EDIT** | May edit permitted content, but does not automatically get administration, sharing, or deletion powers. |
| **VIEW** | May view the permitted representation only. |
| **DENIED** | No access; do not reveal that the resource exists. |

Capabilities are separate, explicit grants, for example:

| Capability | Meaning / limit |
| --- | --- |
| **COMMENT** | Add a comment. Requires a signed-in account with verified email, and an explicit grant. |
| **LEND** | Start a lending workflow. First release: no share link grants this; require an authorized, verified adult and an implemented loan flow. |
| **BUY** | Start a purchase/offer workflow. First release: no public marketplace; require an explicitly eligible, verified account and an implemented sale flow. |
| **SHARE / MANAGE ACCESS** | Create, edit, or revoke a share. Only the owner or an authorized adult manager with verified email; never a child in v1. |

“VIEW + capabilities” therefore means **base level VIEW plus a separate capability set**. A capability never upgrades VIEW to EDIT or bypasses a DENIED decision.

## 3. Identity and verification gates

### Who may create or change a share?

- The creator must be signed in as an **adult account with verified email**.
- They must own the resource or have the applicable adult/guardian management authority.
- A child cannot create a share, publish an outfit, or grant capabilities in v1.
- When an adult shares a child-owned resource externally, require an explicit parent/guardian approval step showing exactly what will be visible.
- The owner may revoke a share at any time. Revocation must block future requests even if the recipient previously opened the link.

Email verification proves access to an email address; it does not prove legal age, identity, or that a person is trustworthy. 2FA is an additional account-security option, not a substitute for resource authorization. For a high-impact public publication, step-up authentication can be considered later; do not claim it exists until implemented.

### Who may view a link?

The owner chooses one access mode for each link:

1. **Anyone with the link:** no account is needed for read-only viewing. The high-entropy URL is a bearer credential: anyone who receives or is forwarded the URL can view the link's exact scope. This is not recipient identity verification.
2. **Verified account required:** viewer must sign in to a Penderie account whose email is verified. The link still grants only its specific scope; the creator can separately choose whether it is restricted to a friend/recipient list.

For both modes:

- The link is unguessable, scoped, revocable, optionally expiring, and rate-limited.
- A link grants **VIEW only by default**. It cannot grant ADMIN or EDIT.
- Interactions such as comments require a signed-in verified account and an explicit capability; an anonymous link viewer cannot comment.
- Lending and buying cannot be granted to anonymous link viewers. They require a separate authenticated workflow and eligibility check.
- Do not place the token in analytics, logs, referrer URLs, or publicly cache private content.

### Public versus link-only

- **Public:** deliberately published and viewable without signing in; may be discoverable or forwarded. Use only for content the owner explicitly approves for public display.
- **Anyone-with-link:** unlisted, not intended for discovery, but still forwardable. Possession of the URL grants read-only access.
- **Verified-account link:** requires a signed-in account with verified email.
- **Friends / followers:** account-based audiences evaluated using the actual relationship; a link does not silently turn a stranger into a friend.

## 4. Viewer types

| Viewer | Meaning |
| --- | --- |
| **Owner** | Adult account/profile that owns the resource. |
| **Household adult** | An invited, authenticated adult member of the same private space. |
| **Parent/guardian** | Adult explicitly responsible for a child's profile. Has management access to that child's items, including Personal items. |
| **Child owner** | Child session acting as its own profile, limited to its own permitted items. |
| **Other child** | Another child's profile in the same space; no implicit access to a peer's items. |
| **Friend** | A signed-in, email-verified account in the owner's accepted friend relationship. |
| **Follower** | A signed-in, email-verified account following the owner, without friend-level trust. |
| **Stranger** | Any external viewer with no friend/follower relationship. |
| **Link viewer** | An actor presenting a valid link. This is an access mechanism, **not** an identity or relationship. They may also be a friend, follower, or stranger. Apply both the link rule and any identity/verification rule. |

Do not use “adult sub-account” as a synonym for another person's credentials. Each adult has their own account; household membership grants an adult role within a space.

Friend requests are limited to adult accounts and do not grant any resource visibility. The recipient must accept; either party may remove the relationship. A child profile cannot create, accept, or manage requests. Every later friend-only share must independently verify the relationship and apply its own explicit scope/capabilities.

## 5. Resource visibility matrix

The table shows the default base access for the named resource status. Capabilities are governed separately by §2. “Household adult” excludes the owner; the parent/guardian rule for child-owned items is described in §6.

| Resource/status | Owner | Household adult | Child owner | Other child | Friend | Follower | Stranger |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **Private item** | ADMIN | EDIT | EDIT if own | DENIED | DENIED | DENIED | DENIED |
| **Friends item** | ADMIN | EDIT | EDIT if own | DENIED unless explicitly shared to household | VIEW | DENIED | DENIED |
| **Followers item** | ADMIN | EDIT | EDIT if own | DENIED unless explicitly shared to household | VIEW | VIEW | DENIED |
| **Public item** | ADMIN | EDIT | EDIT if own | DENIED unless explicitly shared to household | VIEW | VIEW | VIEW |
| **Custom item** | ADMIN | EDIT | EDIT if own | DENIED unless explicitly granted | DENIED unless granted | DENIED unless granted | DENIED unless granted |
| **Personal item** | ADMIN | DENIED, except responsible parent/guardian | EDIT if own | DENIED | DENIED | DENIED | DENIED |
| **Category** | ADMIN | EDIT | VIEW when needed for own inventory | DENIED unless shared to household | VIEW if explicitly shared | DENIED | DENIED |
| **Room / storage location** | ADMIN | EDIT | VIEW only for locations needed by own items | DENIED unless shared to household | VIEW only if explicitly shared | DENIED | DENIED |
| **Home/place details or address** | ADMIN | EDIT | VIEW only as needed for own inventory | DENIED unless parent-approved | DENIED | DENIED | DENIED |
| **Loan record** | ADMIN | VIEW if household-authorized | VIEW only if own participant/item and parent policy permits | DENIED | DENIED | DENIED | DENIED |
| **Sale record** | ADMIN | VIEW if household-authorized | DENIED in v1 | DENIED | VIEW only in an explicit friends sale | VIEW only if an explicit follower stage is enabled | DENIED in v1 |

### Reading the matrix

- “Private” means private from external users, but non-Personal household inventory remains editable by household adults, as agreed. “Personal” is the stronger owner-only setting for peers; a child's responsible parent/guardian remains an exception for safety and management.
- A child owner may add/edit its own items. A child does not automatically see siblings' items just because they share a household.
- Public item visibility does not include purchase price, private notes, precise location, home address, loan details, or other fields not explicitly published.
- External sharing of room/storage labels should not reveal the home address or an item's precise path. Prefer showing a sanitized/omitted location on all external views.
- First release should not offer stranger/public sales. Sale visibility to friends or followers is a later, explicit feature and does not imply that a stranger can buy.
- A child-owned item or look is not externally shared unless a parent/guardian explicitly approves the exact publication or share.

## 6. Published looks and embedded items

- An adult can deliberately publish a look. Anyone may view the selected look presentation without signing in, as agreed.
- If a viewer must be signed in for a particular look, the owner can choose the verified-account access mode or a relationship-restricted audience.
- Publishing a look does **not** change the visibility of any source item. Opening an item directly still applies that item's own status and access rules.
- Render a limited look representation containing only the selected photo/crop, labels, and details approved for that publication. Never reveal an item's private notes, exact location, price, ownership data, or hidden photos through the look.
- If a look is based on a child profile, require parent/guardian approval before any external publication. Default child looks to private.
- Revoking a look publication/link stops future access. If media has already been copied or cached outside Penderie's control, revocation cannot recall those copies; communicate this before publishing.

## 7. Sharing a collection, category, room, or storage location

- A collection/wardrobe link is a **scope**, not blanket access to the account. Include only the selected items and fields.
- Sharing a room or storage location with friends may expose the selected label and selected contents only if the owner explicitly confirms it. Do not include the home/address or ancestor path automatically.
- A selected item's location is omitted from external views by default, even when the item itself is shared.
- A share of a category does not reveal the owner's whole category tree or private items in that category.
- Home/place records and street addresses are never public and never included in a normal share link.
- Links do not override Personal status or another explicit denial. For a child-owned item, parent approval is additionally required.

## 8. Decision order for every request

Apply checks in this order:

1. **Authenticate or validate link:** establish the account session or validate the high-entropy link, including expiry and revocation.
2. **Verify required identity:** verified email for account-required links and all interactive capabilities; verify the actor is an adult where required.
3. **Resolve scope:** identify the requested resource and confirm it is inside the household, relationship, publication, or link perimeter.
4. **Apply safety vetoes:** deny Personal to peers/external viewers; preserve parent/guardian access to a child's resources; never expose home/address data externally.
5. **Evaluate base visibility:** owner, household role, child ownership, friend/follower relationship, Public status, or Custom grant.
6. **Evaluate capability separately:** require explicit grant, permitted resource type, eligible authenticated actor, and any workflow-specific state.
7. **Filter fields and nested resources:** return only fields and descendants explicitly in scope.
8. **Audit and respond safely:** audit share creation/revocation and sensitive publication. Return a consistent denied/not-found response that does not leak resource existence.

More restrictive rules always win. A share can add only access explicitly allowed by this policy; it cannot cross the Personal veto, reveal a Home/address, grant edit/admin, or change the source resource's visibility.

## 9. Current implementation mismatch

The repository has a pure permission resolver and matrix tests, but they are not a complete authenticated request-authorization system. In particular:

- The current resolver represents `LinkHolder` as a separate viewer type rather than an access method combined with the actual viewer's identity/verification status.
- `PermissionResolver::filterCapabilities()` and its tests allow **COMMENT** for `Stranger`; the share-link test consequently allows an anonymous forwarded link holder to comment. This policy conflicts with the verified-account requirement above.
- `PermissionMatrixTest` describes PERSONAL as an absolute veto for all non-owner roles; this conflicts with the agreed parent/guardian management exception for a child's Personal item.
- Current item endpoints are household-scoped and do not consistently enforce item-owner/child permissions.
- The permission classes do not currently establish email verification, parent approval, verified viewer status, link expiry/revocation, or privacy-safe field projections.
- Current item and look APIs do not yet implement the full resources described in this matrix.

Before enabling sharing or child sign-in in production, align the resolver, authorization service, endpoint checks, share-token model, and positive/negative integration tests with this policy. Do not treat a passing pure matrix test as proof that every HTTP route enforces the matrix.


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

### Member profiles and feed — 2 November 2026

Member profile GETs require a signed-in verified adult and return only a verified
account's username plus self/friend/following relationship flags. They do not
publish the private SocialWorkspace profile. Child and anonymous access is denied.
Profile collection queries and both feed scopes reuse the publication audience
checks before pagination; an owner filter never expands access. The following
scope further restricts results to accepted friends or followed accounts.
Source-item vetoes and photo authorization remain in force. No private collection
count, source item ID, email, location, bio or profile photo is exposed by these views.
