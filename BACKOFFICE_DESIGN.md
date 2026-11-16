# Back office design and decision log

This document explains **why** the Penderie back office is built the way it is.
It complements `PRODUCT_SPEC.md`, `PERMISSIONS.md` and `CHILD_ACCOUNTS_DESIGN.md`.
UI copy is French by default, with English and Georgian translations.

## 1. Goal

Give the site owner and each household a trustworthy, low-risk way to answer:

- How is the product being used? (visits, signups, active accounts, items, scans, shares)
- Is the service healthy and where are the main usage trends?
- What happened in the product? (append-only audit trail)
- How can reported abuse be reviewed without turning the admin into a browser for private inventories?

The back office is **not** a second product UI. It is an operational view on top of
the existing SPA/API. The React app stays the place where people manage their
wardrobe, family and social features.

## 2. Decision summary

| Area | Decision | Why |
| --- | --- | --- |
| Framework | Symfony controllers + Twig templates under `/admin` | Server-rendered pages are fast to build, cheap to secure and easy to audit. No second SPA build. |
| Authentication | Reuse the existing SPA/API session | Avoid a second password store, a second login flow and duplicate session logic. |
| Who can open `/admin` | Only a CLI-promoted `ROLE_SUPER_ADMIN` account | Global operational access is exceptional. Ordinary adults use the product and My space; children have no site back office. |
| Data scope | My space is limited to the active household/profile; `/admin` is site-wide metadata for the promoted super admin | Household members must not read another family's data. |
| Super admin | `ROLE_SUPER_ADMIN`, granted by a CLI command only | Global access is exceptional and must never be self-assigned from a form. |
| Moderation v1 | Reversible account suspension/restoration and report-scoped takedown of a shared item link | Keep account data and source items; audit every decision and preserve the private-inventory boundary. |
| Visit tracking | Anonymous beacon sent by the React app | Page-level visits are meaningful; server-side API counting is noisy and misses navigation. |
| Active user | Signed in within 30 days | Standard monthly-active definition and easy to explain. |
| Personal space | React `/my-space` page and `/api/me/space` endpoint | Users manage their own space in the product UI; this is not site administration. |
| Audit retention | 180 days | Enough to investigate incidents and show history without keeping personal data forever. |
| Item restore | Not implemented | Restore requires versioned item snapshots, separate from the append-only activity log. |
| i18n | French default, English and Georgian | Matches the existing app. Legacy React screens fall back to English for Georgian. |

## 3. Access model

### 3.1 Three different "admins"

The word *admin* is overloaded, so the product distinguishes three different surfaces:

1. **My space** — the signed-in person sees only information their active profile
   is permitted to see in their active household.
2. **Household management** — responsible adults manage their own household and
   children from normal product screens. This does not grant `/admin`.
3. **Site back office** — only an adult `User` with `ROLE_SUPER_ADMIN` can open
   `/admin`. The current admin controller has a class-level
   `#[IsGranted('ROLE_SUPER_ADMIN')]` guard.

Children use a separate restricted security principal. They have no `/admin`
access, site-wide KPIs, or admin journal access.

### 3.2 Household management is not site administration

Adults manage only their own household in the product and see their personal My
space. Only the site owner, promoted with the CLI command, can open `/admin`. This
avoids granting site-wide operational controls to every registered adult.

### 3.3 Separate household and site scopes

Household adults see only their active household/profile in My space. The site-wide `/admin` area is reserved for the promoted super admin. That role can
see account and household metadata and aggregate operational data, but never the
contents of private items or loans:

- allowed: e-mail, display name, household name, membership records, aggregate
  counts, enabled/verification state, timestamps and action labels;
- never: private item names/photos/notes/memories, borrower details, loan messages,
  QR tokens, share tokens or addresses.

Any future moderation workflow that needs item content must be report-scoped and
minimize what it reveals; it must not add a general inventory browser to `/admin`.

### 3.4 Why the app session is reused

A second login would create two sources of truth for identity. Reusing the session
means:

- one password, one logout, one "remember me" behaviour;
- the same authenticated account as the app;
- no token duplication in the browser.

The same Symfony firewall covers `/api` and `/admin`. The admin controller adds
the stricter super-admin role check after authentication.

### 3.5 Why super admin is CLI-only

Global access is powerful. A web form that can grant it would be a privilege
escalation target. Promotion therefore happens outside the request cycle:

```bash
php bin/console app:promote-super-admin owner@example.com
```

Revocation uses the same command with `--revoke`. Every promotion and revocation is
written to the audit log.

## 4. What is implemented now

### Site admin

- Dashboard with selectable 7-, 30-, 90-day and all-retained-data periods.
- Clickable KPI details for households, adult accounts, memberships, items,
  active loans, page visits, unique browser IDs, signups, active accounts,
  unverified e-mails, scans, shares, child accounts and observed request metrics.
- Household and account drill-downs show metadata only. Item and loan drill-downs
  remain aggregate-only; they do not reveal item names, photos, locations, notes,
  borrowers or loan messages.
- The admin layout follows the app's Baloo Chettan 2 typography, Bordeaux/rose
  palette, rounded cards and mobile-first navigation/tables.
- Item creation charts use Europe/Paris local time. Request metrics aggregate
  request counts, 5xx errors and latency by route name, method, status and day;
  they do not store URL paths or IP addresses and are not an uptime guarantee.
- Household list with name, slug, creation date and member count.
- Read-only user list with e-mail, verification, super-admin/user role, last
  sign-in and creation date.
- Filterable and paginated activity journal for the activity rows currently
  retained. Newer dashboard activity is a short preview; older entries are
  available through the journal.
- Site-wide totals and KPI details for the super admin.
- Symfony translation keys in French, English and Georgian for Twig admin UI.

The `/utilisateurs` page currently has no search, filters, sorting, pagination or
state-changing controls. The `/foyers` page is a summary list rather than a
household detail/management screen.

### How to use the current back office

1. Sign in with an adult account that has already been promoted to
   `ROLE_SUPER_ADMIN`. There is no web form to grant this role; an operator runs
   the CLI command in section 14. If the role was just changed, sign out and back
   in so the session is refreshed.
2. Open `/admin`. The dashboard defaults to the 30-day period. Choose 7 days,
   30 days, 90 days or all retained data to update the KPI totals and details.
3. Select a KPI card to open its detail dialog. Household/account/membership
   cards list permitted metadata; item and loan cards show aggregate counts only.
   Visitor details identify browser IDs as estimates, not verified people.
4. Open **Journal** to search the retained audit history by action, actor and
   date range. Use previous/next to reach older pages; the journal only contains
   records still inside the retention window.
5. In **Users**, search, filter by account status, sort, and page through adult
   sign-in accounts. Suspend an eligible account with a required reason or restore
   a suspended account; both actions require confirmation and are audited. Account
   deletion and role editing are not available.
6. In **Reports**, review an active shared link's report, account metadata and
   approved presentation fields only. Dismiss, disable, or restore that exact link.
   The page never exposes the source item's photos or private fields.
7. Open **Households** for read-only metadata.
8. Use **My space** in the React app for the signed-in person's own household.
   Open a stat card for details; return an eligible loan, revoke an authorized
   share, dismiss an activity from this account's feed, or follow an activity
   item link to edit/view it according to the server's permission decision.

### My space (separate from site admin)

- `/api/me/space` is scoped to the current household and active profile. Child
  accounts get only their own items and loans; the server controls whether a
  share can be revoked or a loan returned.
- Adult stat cards open item, place, household/profile/account and active-loan
  details. Child views omit adult household controls.
- Active shares can be revoked by an authorized adult. The activity feed is
  readable and localized, excludes login events and raw API paths, and formats
  its timestamps for the active locale.
- Item changes may show changed field names, never the changed private values.
  An activity sentence links to edit only when the server says the current actor
  can manage the item; otherwise it links to read-only item details.
- Dismissing an activity entry creates a separate actor-scoped dismissal. It
  does not alter the append-only audit record.
- Active-share rows and item-completion links use the mobile-first Penderie styles.
- “For sale / rarely used” and “Comments received” currently show honest
  unavailable messages. Sale/last-used data and a user comments workflow are
  not implemented.
- The activity feed returns its newest 20 visible entries and has no load-more
  control. The admin journal pagination is separate.

### Deliberately not implemented

- Permanent account deletion, suspension expiry, an appeal workflow, or an
  account-owner notice about suspension.
- Resending verification, resetting passwords, forcing sessions out, or changing
  user roles from `/admin`.
- Household suspension/reactivation or detail management from `/admin`.
- CSV export, billing/subscription features, or item-version restore.

The moderation actions apply to adult `User` accounts. Child access remains
separately controlled by the child's responsible parent; this admin workflow does
not suspend a household or child's principal.

## 5. KPIs and definitions

The dashboard offers rolling 7-, 30- and 90-day windows, plus all retained data.
Dates are stored in UTC; item and visitor charts group by Europe/Paris local time.

| KPI | Definition | Scope |
| --- | --- | --- |
| Visits | Page-view events recorded by the beacon | Site / household when known |
| Unique visitors | Distinct hashed random browser identifiers per period; not distinct verified people | Site / household when known |
| Signups | `User` rows created in the period | Site; household via membership |
| Active users | `User` rows with a sign-in in the period | Site; household via membership |
| Items | Items created, active, archived | Household / site |
| Scans | Successful scan analyses requested | Household / site |
| Shares | Active, revoked and expired `ItemShare` rows | Household / site |
| Households | Household rows | Site |
| Child accounts | `ChildAccount` rows, enabled vs disabled | Household / site |
| Performance | Aggregated request counts, 5xx errors and observed latency by route name, method, response status and day | Site |

Visit and activity history begins with the migrations that introduced those
tables; neither is backfilled from older logs. Daily request aggregates begin
with `Version20261115223000`. Analytics, performance records and the admin
activity journal are subject to the 180-day retention policy.
The performance buckets measure requests that reached the application; they do not
prove availability during an outage.

### Why 30 days

It is the most common "monthly active" definition, it smooths weekly noise and it
matches how people think about a family app. Seven days would over-react to holidays;
ninety days would hide churn.

### Why these KPIs

They answer four different questions:

1. acquisition: visits, unique visitors, signups;
2. engagement: active users, scans, items;
3. sharing behaviour: shares, loans;
4. family adoption: households, child accounts.

## 6. Visit tracking

### Decision

The React app sends an anonymous page-view event to `POST /api/analytics/visit`:

```json
{ "path": "/dressing", "visitorId": "random-uuid", "locale": "fr" }
```

The household is never sent by the client: the server reads it from the shared
session, so a signed-in visitor cannot claim another foyer. The endpoint stores:

- the UTC timestamp;
- a normalised page bucket (for example `items`, `loans`, `account`);
- a SHA-256 hash of the visitor id;
- the active locale and whether the visitor was signed in;
- the active household id when the session has one.

It does **not** store the IP address, user agent, cookies or cross-site identifiers.
A short-lived rate limiter uses the IP in memory but never writes it to the table.

### Why

- Page-level data is what "visits" means to a product owner.
- Counting API requests would count background polling and would miss client-side
  navigation.
- A random id in `localStorage` lets us count unique visitors without cookies or
  fingerprinting. It is pseudonymous, not personal, and can be cleared by the user.
- The raw browser id is not stored; the table keeps its hash and event timestamp.

### Privacy guardrails

- No IP, no user agent, no referrer.
- No cross-site tracking; the id is only sent to Penderie.
- Path is normalised to avoid storing query strings or tokens.
- Events older than 180 days are deleted when the purge command is run. Automatic
  scheduling must be configured and monitored in each deployment.

## 7. Audit log and personal activity

### Decision

An append-only `activity_log` table records successful mutations and important
security events:

- signup, e-mail verification, login, logout;
- household and profile changes;
- item create, edit, archive, restore, delete;
- scans, shares, share revocation, loans;
- child account and approval decisions;
- super-admin promotion and revocation.

Each row stores the actor (user/profile when available), household, action key,
subject type/id and a small metadata JSON object. It never stores private content.

### Why append-only

Audit rows are evidence. Editing them would make the log untrustworthy. Deletion is
limited to retention cleanup after 180 days.

### Why 180 days

It covers a school-year project cycle and realistic support investigations while
limiting the amount of personal data kept. The cleanup command is explicit and
logged.

### Item restore

The audit log answers "what happened". Restore needs the actual old values, so item
versions must be stored separately. The current implementation does not have item
revision snapshots or restore. If added, owners and responsible parents should be
able to restore a recent version; the restore itself must create a new audit entry.
Decide the revision retention before implementation.

## 8. Why personal space lives in React

“My space” is part of the product experience, not site operations. Keeping it in
the React `/my-space` page:

- keeps the user in one visual language;
- reuses the existing session and API client;
- avoids duplicating layout and navigation in Twig;
- lets the API enforce household/profile permissions on every request.

The Twig back office remains a site-owner-only operations surface. It does not
become a second place for ordinary household adults to manage their data.

## 9. Internationalisation

The back office uses Symfony translation keys in `translations/messages.{fr,en,ka}.yaml`.
French is the default and fallback. Georgian main screens are translated; legacy
React screens fall back to English rather than showing untranslated French.

Reasons:

- the course requires a French/English/Georgian product;
- server-rendered pages must not hardcode one language;
- the existing app already has a locale switcher and stores the choice locally;
- the `Accept-Language` header now carries the choice to the API and the back office.

## 10. Security checklist

- `/admin` requires an authenticated adult `User` with `ROLE_SUPER_ADMIN`.
- Unauthenticated visitors are redirected to the SPA login; authenticated children are denied with 403.
- Ordinary My space queries are scoped to the active household/profile. Site-admin
  metrics are global only for the super admin.
- Super admin is granted only by CLI.
- Every implemented state-changing admin action uses POST, CSRF protection,
  explicit confirmation and a server-side `ROLE_SUPER_ADMIN` check.
- No private notes, photos, QR tokens or share tokens are rendered in `/admin`.
- Audit writes happen after the mutation succeeds.
- Retention jobs are idempotent and print their deletion counts.
- Tests cover admin role denial, CSRF-protected moderation, suspension/restoration,
  report-link lifecycle, aggregate-only metrics, visitor privacy, KPI cards,
  periods and pagination.
- Add positive and negative authorization tests for every future admin action.

## 11. Open follow-ups

- Decide whether account deletion is a delayed/anonymized erasure workflow or a
  permanent delete, and how to handle loans, shares, child guardianship and
  legally required audit records.
- Add an appeal/support route and translated account-owner notice for suspensions.
- Add household detail and suspend/reactivate only after defining the effect on
  every account and child in that household.
- Add CSV export only after a privacy review.
- Configure a scheduled task runner for retention and alert on missed runs.

## 12. Moderation design and implementation

Moderation is intentionally limited to audited adult-account suspension and
report-scoped shared-link actions. Do not mistake these controls for account
deletion or a general private-inventory browser.

### 12.1 Account suspension and deletion

The first moderation action is **temporarily suspend an adult account**.
Suspension should block new sign-ins and invalidate existing sessions, but should
not delete the user's items, household memberships, loan history or audit records.
The implemented action requires:

1. A super admin to choose a required reason and confirm the exact action.
2. An audit entry with acting admin, target account, timestamp, reason code and
   resulting state. Do not put sensitive evidence in general audit metadata.
3. A restore action that is also confirmed and audited.

Suspension/restoration is implemented. It prevents an admin from suspending
themselves or the last enabled super admin. The account's existing enabled check
on session refresh makes suspension apply to existing sessions as well as future
sign-ins. Permanent deletion is explicitly deferred; do not add a delete button or
cascade account-owned data. Suspension expiry, owner notice and appeal/support
journeys remain future work.

`AccountSuspension` records who suspended an account, why and when, and who
restored it. Do not make a blanket account delete the first response to a report.

Account deletion should be a separate, high-impact **erasure workflow**, not a
button that cascades through related data. Before implementation, define a grace
period, reauthentication/confirmation, what becomes of owned items, active shares,
loans and child accounts, and which audit/security records must be retained or
anonymized. Prevent a super admin from deleting or suspending themselves or the
last remaining super admin. Keep super-admin role grants/revocations CLI-only.

### 12.2 Suspicious item moderation

The current privacy decision is that a super admin does not browse users' private
items. Keep that boundary. The approved v1 workflow accepts reports only for an
active shared-item link and allows action only on that exact link:

1. A signed-in, verified adult can report a specific active shared-item link with
   a reason and optional bounded details. A reporter may report a link only once.
   A report does not grant access to the owner's source item.
2. The queue contains the report reason, timestamps, reporter/owner account
   metadata and status, plus only the shared presentation's name/type/color.
   It never exposes photos, notes, memories, locations, addresses or other items.
3. An admin can dismiss the report or temporarily disable the reported link.
   This is a reversible link-level takedown; the source item and other links stay
   untouched.
4. Every report and decision is audited. Restoring a link is allowed only if the
   owner has not revoked it, it has not expired, and current item safety vetoes
   still permit access. All admin actions require a CSRF token.

A report is not proof of a violation and must not trigger irreversible deletion.
The source item is never deleted from the admin panel. No private-item inspection
or private-item report is part of this release.

### 12.4 Agreed implementation scope — 16 November 2026

The product owner approved the following v1 scope:

- Make the **Users** list searchable, filterable and paginated; show account
  status and offer audited suspend/restore actions with a required reason.
- Do not add account deletion, role editing, password reset or email resend.
- Add a verified-adult report action for an active shared-item link, an
  admin-only report queue, dismiss/take-down/restore actions, CSRF protection,
  and positive/negative authorization and lifecycle tests.
- Takedown disables only the reported link and is reversible. It never removes
  the private source item or reveals private item data to the admin.
- Keep admin routes under `ROLE_SUPER_ADMIN`; prevent self-suspension and
  suspension of the last enabled super admin.
- Polish all admin pages for small and wide screens with the app's typography,
  palette, rounded shapes, accessible focus and translated copy in fr/en/ka.

Implementation is present in `ModerationController`, `SocialController`,
`SharedItemReport`, and `AccountSuspension`, with a schema migration, localized
French/English/Georgian pages, responsive admin styling, and focused functional
tests.

Validation completed:

- Backend coding standards and PHPStan level 6 pass.
- Full backend suite passes: 279 tests, 2,039 assertions.
- Symfony YAML/Twig lint and Doctrine mapping validation pass; the local database
  was migrated and is synchronized with the mappings.
- Frontend oxlint, typecheck, all 121 tests, Prettier checks for changed files,
  and production build pass. The build reports the existing large-chunk warning.

### 12.3 Other useful operations

After account and report workflows, consider:

- Household suspend/reactivate with an impact preview and per-member notification.
- Resend verification email only on an explicit request and with existing rate
  limits; never reveal whether an arbitrary email is registered to unauthenticated
  callers.
- Admin-action audit filters, export and retention controls, with a privacy review.
- Operational alerts for elevated 5xx rates, latency and unusual signup/visit
  changes; request aggregates are not an uptime monitor.
- Backups, restoration drills, retention-run monitoring, and a documented support/
  incident-response contact path.

Do not add billing, public marketplace moderation or broad content browsing unless
those features become real product requirements.

## 14. Commands

```bash
# Promote or revoke a global super admin
php bin/console app:promote-super-admin owner@example.com
php bin/console app:promote-super-admin owner@example.com --revoke

# Delete visit and audit rows older than 180 days
php bin/console app:purge-analytics
```
