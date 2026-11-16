# Manual Demo Script for Teacher

## Overview
This demo script covers all the back-office features implemented for the Penderie application:

- A) MY SPACE (/my-space): personal dashboard for all logged-in accounts
- B) ADMIN (/admin): only for super admin with global view
- Child-restricted my-space: own collection and loans only
- Parent/partner my-space: full version with household management

## Prerequisites
- Symfony development server running (`php bin/console server:start`)
- Docker containers running (api, db, nginx, mailpit)
- Login credentials for test accounts

## Test Accounts
| Role | Email | Password | Description |
|------|-------|----------|-------------|
| Parent | parent@example.com | secret123 | Household parent with child accounts |
| Child | (uses profile PIN) | N/A | Child profile on parent's device |
| Super Admin | super@example.com | secret123 | Super administrator |
| Regular User | camille@example.com | secret123 | Regular user (no household) |

## Demo Session 1: MY SPACE for Different User Types

### 1. Access /my-space as Different Users

**Parent/Partner (Full Version):**
```
GET /my-space
```
- Sees: my collection, my loans, household management, child accounts
- Can access: household management, reset child PIN, edit child restrictions
- Cannot: create/revoke share links, change credentials, delete account

**Child (Restricted Version):**
```
GET /my-space
```
- Sees: own collection (items owned), own loans (as borrower/owner)
- Cannot access: household management sections, PIN changes, restriction editing
- UI shows restricted view only own items

**Super Admin:**
```
GET /my-space
```
- Sees: global KPIs, all households, all users (aggregated data only)
- Cannot: read contents of a user's private items
- Shows: household counts, user counts, item counts globally

### 2. Navigation
The top navigation bar (AppNav.tsx) adapts based on user type:
- Parents/partners see: Fil, Dressing, Scanner, Foyer, **My space**, Profile
- Children see: Fil, Dressing, Scanner, Foyer, **My space** (restricted), Profile
- Super admins see: Fil, Dressing, Scanner, Foyer, **My space**, Profile

### 3. Household Management (Parent only)
```
GET /my-space/household
```
- Lists household members
- Lists child accounts with overview
- "Reset PIN" button for each child account
- "Edit restrictions" button for each child account
- Writes journal entry on view

### 4. PIN Reset (Parent only)
```
POST /my-space/child/{childAccountId}/pin/reset
```
- Generates new 4-digit PIN
- Sets PIN hash on the profile
- Writes journal entry: `child_pin_reset`
- Returns: `{"success": true, "pin": "1234"}`

### 5. Edit Child Restrictions (Parent only)
```
POST /my-space/child/{childAccountId}/restrictions
```
- Accepts JSON: `{"restriction": "value"}`
- Writes journal entry: `child_restrictions_updated`
- Returns: `{"success": true}`

### 6. Child Restrictions (UI)
- Child sees only own items in collection
- No household management sections in UI
- PIN input field only for profile unlock, not change

## Demo Session 2: ADMIN for Super Admin

### 1. Access /admin
```
GET /admin
```
- Requires: ROLE_SUPER_ADMIN
- 403 Forbidden for ROLE_USER, ROLE_PARENT, ROLE_CHILD

### 2. Admin Dashboard
Shows global KPIs:
- Total households, users, members
- Items count, active loans
- 30-day statistics (signups, visits, unique visitors)
- Recent activity journal (last 100 entries)

### 3. Households List
```
GET /admin/foyers
```
- Super admin: sees ALL households
- Others: 403 Forbidden

### 4. Users List
```
GET /admin/utilisateurs
```
- Super admin: sees ALL users with household, item count, last login
- Others: 403 Forbidden

### 5. Journal / Activity
```
GET /admin/journal
```
- Pagination and filters (user, action type, date range)
- CSV export capability
- 180-day window (configurable)
- Moderation: active share links with revoke, user blocks

### 6. Super Admin Does NOT See Private Items
- The super admin `/admin` view shows ONLY aggregates
- Never the contents of a user's private items
- User data is aggregated (counts, stats) not individual records

## Demo Session 3: Authorization Checks

### 1. Server-Side Authorization
Every endpoint has proper authorization:
- `#[IsGranted('ROLE_USER')]` on MySpaceController
- `#[IsGranted('ROLE_SUPER_ADMIN')]` on DashboardController
- `#[IsGranted('ROLE_PARENT')]` on household management endpoints
- Never rely on hidden buttons - server always checks

### 2. Forbidden Access Tests
- Non-authenticated user trying /my-space: 403 Forbidden
- Regular user trying /admin: 403 Forbidden
- Child trying household management: 403 Forbidden
- Parent trying super admin features: 403 Forbidden

### 3. Cross-Household Data Protection
- User A can never read another household's items
- Super admin sees aggregated data only, never private item contents
- All API endpoints scope data to the current profile's household

## Demo Session 4: Journal & Moderation

### 1. Journal Entries
Every action writes to the activity journal:
- `my_space_viewed` - when user accesses my-space
- `household_viewed` - when parent views household management
- `child_pin_reset` - when PIN is reset
- `child_restrictions_updated` - when restrictions are edited
- `item.created`, `item.updated`, `item.deleted` - existing item actions

### 2. Journal Filters (Super Admin)
- Filter by user
- Filter by action type
- Filter by date range (180-day window)
- CSV export

### 3. Moderation Actions
- Revoke active share links (must stop working immediately)
- List of user blocks
- Moderation queue for pending approvals

## Demo Session 5: i18n Verification

All UI strings use i18next with keys in fr, en, ka:

### French (fr.json)
- All my-space strings: "Mon espace", "Ma collection", "Mes prêts", etc.
- Household management: "Gestion du foyer", "Membres du foyer", etc.
- PIN: "Code PIN", "Réinitialiser le code"

### English (en.json)
- All my-space strings: "My space", "My collection", "My loans", etc.
- Household management: "Household management", "Household members", etc.
- PIN: "PIN", "Reset PIN"

### Ka (ka.json)
- All my-space strings translated to Georgian
- Household management: "სახლის მართვა", "მემსახურე წევრები", etc.
- PIN: "PIN", "PIN უკ_RESET"

## Verification Commands

### PHPStan (level 6)
```bash
cd backend && php vendor/bin/phpstan analyse src/Controller/Admin/MySpaceController.php --level=6
```

### Route Registration Check
```bash
cd backend && php bin/console debug:router | grep -E "^  (admin_|my_space_)"
```

### Translation Keys Check
```bash
cd frontend && php -r '
foreach (["fr", "en", "ka"] as $lang) {
    $json = file_get_contents("src/i18n/locales/" . $lang . ".json");
    $arr = json_decode($json, true);
    echo $lang . ": my_space=" . (isset($arr["my_space"]) ? "HAS " . count($arr["my_space"]) . " keys)" : "MISSING") . "\n";
}
'
```

### Manual Test Commands (via curl)
```bash
# Parent my-space
curl -u parent@example.com:secret123 http://localhost:8000/my-space

# Child my-space (via profile PIN on parent's device)
curl -u parent@example.com:secret123 http://localhost:8000/my-space/child/1/pin/reset

# Super admin
curl -u super@example.com:secret123 http://localhost:8000/admin

# Non-authenticated
curl http://localhost:8000/my-space  # Should 403
```