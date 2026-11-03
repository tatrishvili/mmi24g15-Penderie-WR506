# Parent-managed child accounts

Status: implementation design, 3 November 2026. The restricted-profile hardening
described below is implemented. Independent child accounts, room grants, child
collections, approved connections and the approval inbox are not implemented yet.
The confirmed product decisions come from [the November handover](session02/11/26.md).

## Confirmed behavior

The service is intended for people aged 12+. Parents create accounts for children
aged 12–17 and give them a username and password. Children sign in independently,
manage their own items and create private collections. Approved contacts can be
other parent-managed children or specifically approved adult relatives. Sharing
requires a guardian to review and approve the exact content and named recipients
through Notifications before anything becomes visible. Children can withdraw a
share immediately. No child directory, anonymous child links, public publication,
direct messages, selling or lending are included.

Sharing a room never grants access to somebody else's belongings. Guardians can
manage their own children's inventory. Explain that private collections are hidden
from contacts and remain accessible to the responsible guardian.

## Security foundation implemented

Selected-child mode cannot use `CurrentActor::requireUser()` for adult account
operations or inherit an adult profile through `requireAdultProfile()`. Thus
household creation/selection, PIN unlock, adult friendships and account updates
cannot escape child mode. Returning to adult mode requires a successful adult
login. `/api/auth/me` returns only the selected child's profile, the current
household and no parent account/email, sibling list, other household list or
unfiltered place summaries. Existing item ownership guards remain in place.

Legacy Profile principal tokens remain rejected. No new child login route is
enabled by this slice. A profile PIN remains a trusted-device unlock.

## Required policy decisions for existing data

These questions were presented to the product owner on 3 November and must be
answered before migrating existing child/room permissions:

1. Does the adult explicitly enabling an existing child's new login become its
   guardian, or may only the household creator enable it? Do not silently grant
   guardianship to every adult household member.
2. Do existing rooms require explicit common/child assignments, or become common
   by default? Recommended: require explicit access, preserve existing inventory,
   hide restricted location names and let guardians correct assignments.

Temporary initial passwords with expiry and a required first-login change remain
a recommendation, rather than a previously confirmed product constraint.

## Data boundaries and proposed records

| Record | Ownership and invariants |
| --- | --- |
| ChildAccount | One child Profile, normalized unique child username, password hash, enabled flag, credential/session version; no fabricated adult User or child email. Explicit age-policy acknowledgement during parent setup. |
| GuardianLink | Named adult User and child Profile, accepted/revoked state and audit timestamps. Creation establishes the explicitly responsible guardian; later guardians require an invitation and acceptance. Household membership alone is insufficient. |
| Room access | Common flag or explicit profile assignments. Storage inherits room access. Existing-data defaults require the decision above. |
| ChildCollection | Owned by child Profile, private by default, versioned membership. Never expose the adult-owned SocialWorkspace as a child's workspace. |
| ChildConnection | Named child/child or child/adult-relative identities. Both sides accept; each child side also needs its own guardian's approval. Revocation invalidates dependent access immediately. |
| ApprovalRequest | Child requester, action, immutable preview and recipient snapshot, source version/digest, pending/approved/rejected/withdrawn/invalidated state, expiry and decision audit. |
| ApprovedShare | Exact approved snapshot and named recipients. Source ownership, vetoes, current guardian authority, connection eligibility and version remain necessary on every data/photo read. |
| Notification | Recipient identity, authoritative request reference, event type, created/read timestamps. Viewing a notification never approves a request. |

Use additive migrations. Never infer a legacy guardian from an arbitrary adult
membership, convert a PIN into a remote password, purge fixtures or delete items
when their room permissions change.

## Restricted session and API design

Use a distinct child security principal. It resolves exactly one Profile and its
Household, independent of active-profile/household IDs in session data. Adult
methods always reject it. Parent reset, disable, guardian removal and session
revocation must invalidate existing child sessions on the next request. Rotate
session identifiers at login and credential changes. Returning to adult mode
requires real adult credentials, including the existing trusted-device flow.

Proposed API surface:

| Endpoint | Authority and effect |
| --- | --- |
| POST `/api/children` | Verified adult, explicit guardianship setup; creates child profile/account. |
| POST `/api/auth/child-login` | Username/password, generic failure, independent account and IP throttles; restricted session only. |
| POST `/api/children/{id}/credentials` | Responsible guardian only; reset credentials and revoke earlier sessions. |
| PATCH `/api/children/{id}/access` | Responsible guardian only; enable/disable and revoke sessions. |
| PUT `/api/rooms/{id}/access` | Authorized guardian/room administrator; common or explicit assignments, no cross-household IDs. |
| GET/POST/PATCH `/api/child-collections` | Child's own records or explicitly authorized guardian, version checks, private by default. |
| POST `/api/child-connections` | Named contact only; reciprocal acceptance and guardian approvals. No directory. |
| POST `/api/child-approvals` | Restricted child creates a frozen review request for eligible named recipients. |
| GET `/api/notifications` | Recipient-only inbox, unread count, pagination. |
| POST `/api/child-approvals/{id}/decision` | Current responsible guardian, exact version/digest, transactional accept/reject. |
| DELETE `/api/child-shares/{id}` | Owning child or responsible guardian; immediate withdrawal. |

Use long hashed passwords; reject common/compromised choices without sending
passwords to an external service. Do not log credentials or include them in inbox
payloads. Keep usernames separate from public adult discovery. Password setup,
expiry, recovery and failure copy must be localized in French and English.

## Endpoint audit before enabling independent login

| Surface | Current result and remaining requirement |
| --- | --- |
| Account/profile/household changes | Child-mode adult fallback closed; HTTP regression covers account mutation, household reselection, PIN escape and child creation. Independent principal must receive the same denial. |
| Adult friends/feed/community/collection publications | Keep adult and verification guards. Child connections/publications need separate authorized flows. |
| Items, search, QR, photos | Existing selected-child ownership checks retained. Add independent-session positive/negative coverage, explicit guardianship and room redaction across every payload. Photo responses should use no-store for restricted/revocable access. |
| Place lists and item location writes | Existing lists derive child locations from owned items. Replace with explicit room grants; reject forbidden room/storage writes and redact legacy restricted paths. Storage must never broaden room access. |
| Scan | Authenticated endpoint accepts untrusted images; verify child session validity before provider work and preserve existing reviewed-save behavior. |
| Notifications and approvals | Not implemented. Enforce recipient-only reads, guardian authority, immutable preview, duplicate-decision races, withdrawal and stale-source invalidation. |
| Guardian inventory management | Existing household adult behavior is not explicit guardianship. Add object-level guardian checks and tests before enabling child accounts. |

## Approval transaction and revocation

On submission, freeze the reviewed names, selected photos, safe fields, recipient
IDs and source versions. The parent sees this exact representation. Approval
locks/checks the pending request and verifies the guardian link, source ownership,
live vetoes, recipient eligibility and digest inside the same transaction. A
second decision is idempotent or conflicts; it cannot publish twice. Editing
content or recipients invalidates approval and requires a new review.

Each share/photo/feed request checks the approved snapshot, current recipients,
connections, guardian authority and live source state. Archived/deleted/Personal
items, revoked guardians, removed contacts and withdrawn shares deny access.
Restoring content or adding new friends never silently republishes it. Keep
decision history without retaining unnecessary copies of revoked private photos.

## Delivery and validation

1. Restricted actors and adult-operation denial (current slice).
2. Explicit guardianship, child credentials, session revocation and parent setup.
3. Room assignments, storage inheritance and location redaction.
4. Private child-owned collections and guardian management.
5. Reciprocal, guardian-approved connections.
6. Durable Notifications, frozen approval and revocable publication.
7. End-to-end parent/child tests with isolated accounts and data.

Each step needs server allow/deny tests plus the French/English UI and explicit
errors. Independent login stays disabled until the item, location and guardian
checks protect all child request paths. The unrelated pending item-audience versus
collection-audience policy remains unresolved and must not change in this work.
