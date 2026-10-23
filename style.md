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
| `h2` | 700 | 24px | 30px | 1.2 | `--bordeaux-title` |
| `h3` | 600 | 19px | 22px | 1.3 | `--bordeaux-title` |
| `p` (body) | 400 | 17px | 18px | 1.55 | `--gris-texte` |
| Small / hint | 400–600 | 13–15px | 13–15px | 1.45 | `--gris-texte` |
| Button label | 600 | 16px (19px for the onboarding CTA) | same | 1 | inherit |

Rules: no all-caps headings, no letter-spacing tricks, no underlines except for secondary text
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
- Minimum tap target `--tap: 44px`; onboarding CTA height 68px.

## Components

- **Primary button** (`.btn.btn-primary`): full width, pill, `--bordeaux` on white text.
- **Secondary button** (`.btn.btn-secondary`): pill, `--rose-blush` background.
- **Card** (`.card`): white, `--rose-poudre` border, 20px radius, soft wine-tinted shadow.
- **Onboarding screen** (`.welcome*`): full-height photo with a curved creme mask (SVG path in
  `Home.tsx`), white logo over the photo, centered title/tagline, step dots (active `--bordeaux`,
  inactive `--rose-sable`), CTA + sign-in link.
- **Registration screen** (`.register*`): centered title/intro, white pill fields with left icons
  (SVG assets in `frontend/src/images/icons/`), sr-only labels + placeholders, terms line, full
  width CTA, sign-in switch.
- **Badge** (`.badge--pending|--online|--offline`): reserved for connection status only.

## Layout & assets

- Mobile-first at ~390px; `max-width: 480px` centered shell for the welcome screen, `900px` for
  the app pages. Desktop enhancement kicks in at ≥900px (larger type, sidebar nav).
- Page shells: `.welcome` (onboarding), `.auth-page` (sign-in / register), `.app-shell` (signed-in
  tabs). Do not invent new shells — extend these.
- Wireframes live in `frontend/src/Wireframes/<Page>.png`; image assets in `frontend/src/images/`.
  A new screen starts from its wireframe: sample colors from it, reuse tokens from this table, and
  only introduce a new token when no existing one fits.
- Photography and the logo are the only illustration assets; icons come from `lucide-react` and
  inherit `currentColor`.
