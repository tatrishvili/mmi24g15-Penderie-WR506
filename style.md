# Penderie design system

Mobile-first visual language for the frontend. Colors were sampled from the wireframes in
`frontend/src/Wireframes/`; all UI type uses a single font family. The CSS source of truth is
`frontend/src/index.css` — keep this document in sync with it.

## Typography

One family everywhere: **Baloo Chettan 2** (rounded display sans, `@fontsource/baloo-chettan-2`,
weights 400–700). Headings and body share the family; hierarchy comes from size, weight and color.

Token: `--font: 'Baloo Chettan 2', system-ui, 'Segoe UI', Roboto, sans-serif`

| Element | Weight | Size (mobile) | Size (≥900px) | Line height | Color |
| --- | --- | --- | --- | --- | --- |
| `h1` | 700 | 34px | 44px | 1.1 | `--bordeaux-title` |
| Welcome title (`.welcome__title`) | 700 | 40px | 44px | 0.82 stacked | `--bordeaux-title` |
| Auth titles (`.register__title` / `.login__title` / `.verify__title`) | 700 | 40px | 44px | 0.78 stacked / 1.1 single-line | `--bordeaux-title` |
| Step titles (`.organise__title` / `.lend__title` / `.look__title`) | 700 | 40px (34px `.lend__title` 2nd line) | same | 0.725–0.85 stacked | `--bordeaux-title` |
| `h2` | 700 | 24px | 30px | 1.2 | `--bordeaux-title` |
| `h3` | 600 | 19px | 22px | 1.3 | `--bordeaux-title` |
| `p` (body) | 400 | 17px | 18px | 1.55 | `--gris-texte` |
| Small / hint | 400–600 | 13–15px | 13–15px | 1.45 | `--gris-texte` |
| Button label | 600 | 16px (19px for the onboarding CTA) | same | 1 | inherit |

Rules: no all-caps headings, no letter-spacing tricks (the welcome title's `0.55px` is the single
exception, it matches its wireframe glyph run), no underlines except for secondary text
links. Never mix font families inside a page.

## Colors

| Token | Hex | Use |
| --- | --- | --- |
| `--creme` | `#fcf7f4` | Page background |
| `--bordeaux` | `#73182d` | Primary actions, active dots, links, brand accents |
| `--bordeaux-title` | `#631a25` | Headings |
| `--bordeaux-deep` | `#521825` | Hover/pressed state of primary actions |
| `--gris-texte` | `#5a4c4c` | Body copy, labels |
| `--rose-sable` | `#eed2c4` | Inactive onboarding dots, subtle fills |
| `--rose-wash` | `#fbe1e0` | Onboarding background gradient (top wash + bottom blob) |
| `--rose-poudre` | `#fad9e0` | Borders, dividers |
| `--rose-blush` | `#f8e8e5` | Soft backgrounds (chips, active nav) |
| `--gris-rose` | `#c88a9a` | Muted accents |
| `--bleu-lien` | `#56658a` | Inline informational links (e.g. terms of use) |
| `--white` | `#ffffff` | Cards, panels |
| `--green` | `#2f9e44` | Success only |
| `--red` / `--red-soft` | `#c92a2a` / `#fff5f5` | Errors only |

Body text on creme must keep a contrast ratio ≥ 7:1 (AAA); interactive text ≥ 4.5:1 (AA). Never
use white text on `--creme` or `--rose-*`.

## Spacing, radius, targets

- Base spacing unit: 4px; page gutters 20px (`--radius-card` container padding).
- Card radius `--radius-card: 20px`, inputs `--radius-input: 12px`, buttons pill `999px`.
- Minimum tap target `--tap: 44px`; onboarding CTA height 74px (297×74).

## Components

- **Primary button** (`.btn.btn-primary`): full width, pill, `--bordeaux` on white text.
- **Secondary button** (`.btn.btn-secondary`): pill, `--rose-blush` background.
- **Card** (`.card`): white, `--rose-poudre` border, 20px radius, soft wine-tinted shadow.
- **Onboarding screen** (`.welcome*`): creme shell with a full-height SVG background (top
  `--rose-wash` → `--creme` wash cut by a curve, bottom organic `--rose-wash` blob fading out),
  the `home_page_buddie.svg` mascot pinned over the title's first line, centered 40px title /
  tagline, step dots (active `--bordeaux`, inactive `--rose-sable`), 297×74 CTA + sign-in link.
- **Onboarding step screens** (`.welcome--step`, routes `/onboarding/2…4`): same shell, no
  mascot; `.welcome__flow` (66px top / 35px bottom padding) stacks the step block and the shared
  `OnboardingFoot` — four `StepDots` (step 1 = welcome, page 4 = last, a deviation from the 3-dot
  maquettes), 297×74 CTA and the sign-in link kept on every page. Per screen:
  - `.organise__*` (step 2): 40px title, 17px/19px intro, three pill tabs (active `--bordeaux`),
    94px `--rose-blush` discs holding the natural-size wireframe SVGs, 3×2 grid (row-gap 20px)
    with 13px centered labels.
  - `.lend__*` (step 3): two-line title (40/32px), `.step-card` (1px `--rose-wash`, ~30px radius)
    with a 163px photo cell (12px white inset), 18px item name, status badge, meta rows (10px,
    lucide icons), creme chips (`--rose-poudre` border), 17px/25px body, three-step timeline
    (rose `::before` line behind the icons — `.lend__step` is `position: relative; z-index: 1`
    so the absolutely-positioned line never paints over them — and a green check for the
    accepted step).
  - `.look__*` (step 4): two-line title, 17px/24px body, `.step-card` with the 179px outfit photo
    and a weather block (sun icon, 28px temperature, 16px day), two 154×53 choice buttons with
    heart icons, centered 17px caption.
  Multi-line copy ships as `…Lines: string[]` and renders as block `<span>`s so the wraps land
  exactly where the wireframes break them.
- **Pill field** (`.pill-group` > `.pill-field` + `.pill-field__icon` / `__input` / `__placeholder`):
  shared white pill input row with a left icon, sr-only label and placeholder; used by both auth
  screens.
- **Registration screen** (`.register*`): centered title/intro, pill fields, terms line, full
  width CTA, sign-in switch.
- **Sign-in screen** (`.login*`): 40px title (44 ≥900px), 18px subtitle, 103×83 mascot
  (`cute_icon.svg`), pill fields, options row (24px custom checkbox + bordeaux text link), CTA
  inset `calc(100% - 27px)` (297×74 like the register), 17px account switch.
- **Email verification screen** (`.verify*`): 40px title, rose-blush card (20px radius, 16px text,
  address in bordeaux bold), 148×120 left-aligned mascot (`cute_icon_email_verif.svg`), 297×74
  full-width CTA, account switch. After registering, the page receives the real address via router
  state (`navigate('/verifier-email', { state: { email } })`) — the address in the wireframe is
  only a placeholder.
- **Badge** (`.badge--pending|--online|--offline`): reserved for connection status only.

## Layout & assets

- Mobile-first at ~390px; `max-width: 480px` centered shell for the welcome screen, `900px` for
  the app pages. Desktop enhancement kicks in at ≥900px (larger type, sidebar nav).
- Page shells: `.welcome` (onboarding), `.register` / `.login` (auth screens), `.auth-page`
  (verification and other app pages), `.app-shell` (signed-in tabs). Do not invent new shells —
  extend these.
- The auth shells cap at `max-width: 402px`; percentage padding resolves against the viewport, so
  a `min-width: 403px` media block pins their gutter to 39px on wider screens.
- Wireframes live in `frontend/src/Wireframes/<Page>.png`; image assets in `frontend/src/images/`
  and the wireframe's own SVG/PNG icons in `frontend/src/images/icons/`. A new screen starts from
  its wireframe: sample colors from it, reuse tokens from this table, and only introduce a new
  token when no existing one fits.
- Onboarding flow: welcome (step 1) → `/onboarding/2` → `/onboarding/3` → `/onboarding/4` →
  `/inscription`; an unknown step redirects to `/`.
- Photography, mascots and the logo are illustration assets in `frontend/src/images/`; icons come
  from `lucide-react` and inherit `currentColor`.
