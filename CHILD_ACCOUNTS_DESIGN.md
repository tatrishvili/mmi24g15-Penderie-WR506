# Parent-managed child accounts

Status: implemented locally, 3 November 2026. This supersedes the earlier design
proposal and follows the user's latest mother/father, per-person item visibility
and repeated-room decisions. The earlier history is in [the handover](session02/11/26.md).

## Parents and independent child sign-in

- Accounts are created for self-declared ages 12–17 by a verified adult explicitly
  identifying themselves as the child's mother or father. This is a product rule,
  not proof of biological parenthood or verified age.
- Creating the account establishes the responsible parent. A second mother/father
  can be invited by their existing verified account username. They must accept in
  Notifications before receiving guardianship and household membership. Invitations
  expire after seven days and can be declined. At most two parents manage a child.
- Other adults/relatives never receive parental controls just from being household
  members or approved contacts. The inviting parent may revoke the additional
  parent; that additional parent cannot revoke the original creating parent.
- `Profile.createdBy` records creators going forward. Legacy profiles have no
  trustworthy creator record: only the original household parent may explicitly
  activate them, declaring mother/father. Migration does not assign every adult
  guardianship or invent historical relationships.
- `ChildAccount` is a separate security principal, not an adult User or a Profile
  PIN token. The child signs in through **Connexion enfant** using their username
  and password. No child email is needed.
- Initial/reset passwords expire after 24 hours. Passwords contain 15–128 characters;
  the service rejects a local set of common/repeated passwords. The child chooses
  a different password at first sign-in and signs in again. Before that change,
  the session permits only password setup, auth state and sign-out.
- Login uses independent account and IP rate limits, generic errors and Symfony's
  session fixation protection. Password changes/resets, disabling access and
  revoking the additional parent invalidate existing child sessions. Session
  refresh checks that at least one responsible verified parent remains a member.
- Selected-child mode also stays restricted. Returning to adult authority requires
  real adult sign-in. Adults cannot switch/PIN-unlock another adult's identity.

## Rooms, storage and ownership

Every room has its own ID and name and an optional type. Three bedrooms, two
bathrooms and two garages are ordinary independent records. A room is common or
assigned to one/multiple profiles; siblings can share a room. Storage inherits
its room's child access. Household adults retain room administration.

Child location choices contain only common or assigned rooms and their storage.
Forbidden room/storage IDs are rejected on item creation and updates. If access
changes, existing items are preserved and forbidden locations are redacted on
list, detail, QR and update responses. The parent can correct the assignment.
Existing rooms start without child grants; parents explicitly assign them or
mark them common. No item or existing room is deleted by these migrations.

Room access never grants access to someone else's belongings. Owner, physical
location and item audience are independent.

## Per-object visibility inside the household

New objects default to **Only me**. Owners and responsible parents of child owners
may manage them. An object's family audience can be:

1. `owner`: hidden from other household members; responsible parents still manage
   their own child's object;
2. `selected`: a reviewed list of household profiles;
3. `household`: everyone currently in the household, frozen to the reviewed set.

New household members do not silently receive old approvals. A child can view an
adult's/sibling's explicitly approved object. That grants read-only access to
approved name, type, color, brand, author/artist/publisher, condition and photos.
Notes, memories, QR tokens, precise locations, category/size/material and private
profile/security fields are omitted. All mutation/photo-write routes separately
require management authority. Viewing never grants editing or lending.

A child asks for parent approval before expanding visibility, including for a
sibling. No pending request grants access. The child can hide their object or
withdraw a request/share immediately. A parent may approve a reviewed audience
for their own object or an object belonging to their child.

Existing database objects receive `familyAudience=legacy`, preserving previous
non-Personal adult household access rather than silently broadening publication
or rewriting saved data. Children receive no implicit legacy-object access.
All newly created objects use owner-only access. Explicitly saving an audience
replaces that object's legacy behavior.

This household audience is separate from existing adult collection-publication
consent. It does not settle the still-pending general item-versus-adult-collection
precedence question. `Personal` and archive remain source vetoes for sharing.

## Collections, contacts and external child sharing

- Child collections belong to the child's Profile, independently of adult
  SocialWorkspace records. They are private, versioned and limited to that child's
  active items. Their responsible parents can create/edit them too.
- Contacts are requested by a known child username, or a verified adult username
  explicitly identified as a relative. There is no child directory.
- A child/child connection needs the invited child's acceptance plus approval from
  each child's responsible parent. An adult-relative connection needs the adult's
  acceptance plus approval from the child's parent. Being a relative gives no
  guardianship or inventory-management authority.
- Item/collection publication requests freeze exact safe content, named recipients
  and connection versions. Notifications shows the reviewed content and names.
- Approval creates a limited publication for those named approved contacts only.
  Recipients use **Partages familiaux**, without access to original inventory IDs,
  source records, home paths, notes, QR codes or source photo URLs.
- Data and photo reads recheck current guardianship, source versions/vetoes,
  connection eligibility and the frozen connection proof. Removing and re-adding
  a contact does not restore old shares. Restoring/renaming content back to its
  old value does not restore an old approval.
- Parents and child owners can stop publications. Children cannot use adult social
  feeds, adult friendships, public/anonymous publication, lending, selling or DMs.

## Approval inbox and revocation

`InboxNotification` is durable and addressed to one adult or child identity.
Unread counts, older-page loading and marking read are separate from approval.
`ChildApproval` stores the immutable proposal, content digest, status, version,
responsible decision maker and timestamps. Pending review expires after seven
days. Rejection and withdrawal do not publish content.

Approval checks guardian authority and source/recipient freshness inside a
transaction. Optimistic versions reject duplicate decision races. Changed source
content or disabled child access prevents approval. Parent revocation invalidates
that parent's approved shares/connections and closes child sessions. Photos use
protected endpoints and `private, no-store` responses.

## Entry points and persistence

| Entry point | Purpose |
| --- | --- |
| `/connexion` → Connexion enfant | Child username/password sign-in |
| `/enfant/mot-de-passe` | Initial password change |
| `/foyer/enfants` | Create/activate child, reset/suspend, invite second parent |
| `/foyer/pieces/ajouter` | Add repeated room types, rename, assign/common rooms |
| `/articles/:id/visibilite` | Household item audience and child approval request |
| `/enfant` | Own child items, private collections, contacts and sharing requests |
| `/enfant?enfant=:id` | Responsible parent's child management view |
| `/notifications` | Approval decisions, parent/contact invitations, child decisions |
| `/partages-famille` | Read approved child publications, including for adult relatives |

Additive migrations `Version20261103185817` and `Version20261103191334` are applied
locally. They add child accounts, parent links, collections, connections, approval
requests, publications, notifications, family audiences and room assignments.
Other environments must apply migrations before using these flows. Fixtures and
existing inventory were preserved. New UI copy is localizable in French/English.

Validation includes isolated HTTP allow/deny tests for child credentials,
revocation, repeated rooms, location redaction, per-person read-only access,
parent invitations, cross-family contact/publication approval, photo revocation,
review versions, frozen household recipients and account throttling across IPs;
frontend tests exercise the forms, read-only controls, approval versus read state,
child login and route restrictions. Browser checks use sample API responses, not
the user's live family data.
