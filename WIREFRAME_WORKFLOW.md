# Wireframe → code workflow (Penderie frontend)

> **Read this document first in a new session.** It describes how screens are rebuilt from the
> PNG maquettes, the measurement tooling, the conventions, the accepted deviations and everything
> that has been done so far — so no design/workflow question needs to be asked again.
> Companion docs: [`style.md`](./style.md) = design tokens + component reference (keep in sync
> with `frontend/src/index.css`), `SESSION_CONTEXT.md` = private product/handoff notes (gitignored,
> never commit), [`README.md`](./README.md) = stack + smoke test.

**Languages:** product UI French, docs and commit messages English.
**Quality gate:** `make qa` (= `cs stan lint-symfony test audit fe-lint fe-typecheck fe-test`, mirrors
CI). It must be green before every push. `make fix` auto-fixes what it can.

---

## 1. Environment

| Thing | Value |
| --- | --- |
| Stack | Docker Compose: `make up` / `make down` / `make ps` / `make logs` |
| Frontend dev server | Vite inside container `penderie-frontend`, host `0.0.0.0:5173`, `strictPort: true`, `http://localhost:5173` |
| API proxy | Vite proxies `/api` → `http://localhost:8000` (nginx) |
| Frontend commands | in `frontend/`: `npm run lint` (oxlint), `npm run typecheck` (tsc), `npm test` (vitest run), `npm run format` (prettier) |
| Unit tests | Vitest + Testing Library, jsdom, colocated `src/**/*.test.tsx`. Config in `frontend/vite.config.ts`: `maxWorkers: 4`, `testTimeout: 15000` — **do not "fix" flakiness by changing these** (see commit `db9cc75`, 4GB dev VM) |
| Maquettes | `frontend/src/Wireframes/*.png`, **402 × 874 px**, hand-drawn (internally inconsistent — see §5) |
| Screenshot toolkit | `/tmp/opencode/shot/` (ephemeral, `/tmp` — recreate from Appendix A if gone) |
| Chromium | host cache `~/.cache/ms-playwright/chromium-1248` (+ headless shell), reused by the shot container |

### Why the screenshots run in Docker

The host has no browser/node toolchain for this. Every Playwright script runs in a throwaway
`node:24-bookworm` container that (a) shares the network namespace of `penderie-frontend`
(`--network=container:penderie-frontend`) so `localhost:5173` resolves to the dev server,
(b) mounts `/tmp/opencode/shot` as `/work` (script in, PNG out), and (c) `npm i --no-save
playwright-core@1.64.1` on the fly with `PLAYWRIGHT_BROWSERS_PATH=/ms-playwright` bound to the
host cache.

- `run.sh <script.js>` pipes the output through **`tail -8` → only the last 8 lines survive**.
  Always use **`run2.sh`** (same but `tail -200`) when you need logs, or redirect to a file.
- Node shot scripts are **CommonJS** (`require('playwright-core')`), write PNGs to
  `process.cwd()` (= `/work` = `/tmp/opencode/shot`), wait with
  `waitUntil: 'networkidle'` + `await document.fonts.ready` + ~300 ms, and use
  `deviceScaleFactor: 1` so 1 px in the PNG = 1 CSS px.
- Python measurement scripts are **pure Python** (no Pillow in this environment) — all of them
  import `pngutil.load()` (PNG decoder for 8-bit RGB/RGBA, non-interlaced) and start with
  `sys.path.insert(0, '/tmp/opencode/shot')`.

---

## 2. Measurement toolkit (`/tmp/opencode/shot/`)

If the directory is missing, run **Appendix A** (one bash block recreates everything).

| Script | Usage | Answers |
| --- | --- | --- |
| `run2.sh` | `bash run2.sh script.js` | run a Playwright script in the shot container (logs visible) |
| `shoot-<name>.js` | `bash run2.sh shoot-steps.js` | render route(s) → `<name>-402.png` / `-390.png` |
| `rects.js` | `bash run2.sh rects.js > rects.txt` | **DOM `boundingBox` of every key selector** (x, y, w, h, right, bottom) — most reliable for layout; edit the selector list per screen |
| `cmpbands.py` | `python3 cmpbands.py <wire.png> <render.png>` | the main diff: row bands (y0,y1,x0,x1) of `dark` and `bordeaux` ink, wire vs render side by side. Iterate CSS until the band lists line up row for row |
| `crop.py` | `python3 crop.py <in> x0 y0 x1 y1 <out> [scale]` | magnified crop (2–4×) — **the only trustworthy way to judge font size / glyph shape** |
| `sbs.py` | `python3 sbs.py <wire> <render> <out>` | side-by-side composite (wire left, grey separator, render right) for a whole-page visual check |
| `runs.py` | `python3 runs.py <png> y 300` / `x 24` / `yrange 260 300` / `xrange 0 400` | runs of non-background pixels along rows/columns (find element edges) |
| `brd.py` | `python3 brd.py <png> row 300` (or `col 24`) | positions of light-pink `--rose-wash` borders (card/photo frame edges) |
| `pillbox.py` | `python3 pillbox.py <png> y0 y1` | x-runs of pills/tabs/buttons inside a horizontal band |
| `textbox.py` | `python3 textbox.py <png> x0 y0 x1 y1` | bbox of dark bordeaux-ish text inside a region |
| `inkbox.py` | `python3 inkbox.py <png> x0 y0 x1 y1` | bbox of any non-background ink inside a region |
| `iconbox.py` | `python3 iconbox.py <png> x0 y0 x1 y1` | bbox of dark icon ink (icons on white/rose discs) |
| `probe.py` | `python3 probe.py <png> 10,10 200,400` | exact RGB of pixels (token sampling, card fills) |

**How to read `cmpbands` output:** one line per horizontal ink band
`(y0, y1, x0, x1) | (y0, y1, x0, x1)` = wire | render. Pair lines up by meaning (title, line 2,
intro line 1, …). Match `y` first (vertical rhythm), then `x`/width (horizontal position and
font width). `None` on one side = element present only in the other render. Some bands merge
neighbouring elements in the wire but not in the render — compare by content, not by index.

---

## 3. The loop (wireframe → merged screen)

1. **Read the maquette.** Open the PNG (402×874). Note every element, its y position, its text
   (the maquettes contain typos — fix them in copy, see §5.11). Existing maquettes:
   `connection-page.png`, `Register-page.png`, `Email-verif-page.png`, `Home-page.png`,
   `After-home-page1.png` (⚠ capital A), `after-home-page2.png`, `after-home-page3.png`.
   Windows `*:Zone.Identifier` sidecars are gitignored.
2. **Sample, don't invent.** `python3 probe.py <wire> x,y` the colors and match them against the
   token table in `style.md`. Add a token to `index.css :root` **only** if nothing fits
   (`--rose-wash` was the single addition so far).
3. **Scaffold** the screen: page in `frontend/src/pages/`, route in `App.tsx`, all UI strings in
   `frontend/src/i18n/copy.ts` (**both `fr` and `en`, and the `AppCopy` interface must be updated
   in the same edit or typecheck fails**).
4. **Screenshot at 402×874** (the calibration viewport) and at 390×844:
   ```bash
   bash /tmp/opencode/shot/run2.sh /tmp/opencode/shot/shoot-<name>.js
   ```
5. **Compare and calibrate** (this is the bulk of the work):
   - `cmpbands.py wire render` → fix the biggest vertical mismatches first (title, then each
     block below it, then the foot).
   - `rects.js` → exact CSS-box geometry of your selectors; derive margins/paddings from it.
   - `crop.py … 3` on both images → decide font sizes and line breaks by eye at 3–4×.
   - `runs.py` / `brd.py` / `pillbox.py` for edges, borders and pill positions.
   - Re-shoot after every CSS change; convergence is usually 3–8 iterations per screen.
6. **Multi-line copy** must break exactly where the wire breaks: store an array of lines
   (`titleLines`, `introLines`, `bodyLines`) in `copy.ts` and render
   ```tsx
   {lines.map((line, index) => (
     <Fragment key={line}>
       {index > 0 && ' '}
       <span>{line}</span>
     </Fragment>
   ))}
   ```
   with `CSS: .X span { display: block; }`. **The `{' '}` is mandatory** — without it the
   accessible name loses the space and tests like `getByRole('heading', { name: /… …/ })` fail.
   (Whitespace between two `display:block` spans adds no height, so the layout is unaffected.)
7. **Check 390 px too** — no horizontal overflow (the runner does not forward extra CLI args,
   keep the route list inside the script):
   ```bash
   bash /tmp/opencode/shot/run2.sh /tmp/opencode/shot/overflow.js   # routes inside the script
   ```
   Shoot the 390 variant and eyeball it; the maquette only exists at 402 but the app must not
   break at 390.
8. **Tests**: colocated `*.test.tsx` with Testing Library — assert role + French text + link
   targets for every screen you add (`Onboarding.test.tsx` is the reference for a 3-route
   family; `Home.test.tsx` for a single screen).
9. **`make qa` green**, then **update `style.md`** (typography row, component bullet, tokens,
   routes) — it is part of the definition of done for a visual change.
10. **Commit** (English conventional commit + a workflow body explaining *what was measured and
    which deviations are intentional* — copy the style of `git log -1 37a54b7`) and **push only
    `feature/verify-email-wireframe`** (§6).

---

## 4. Code conventions

- **Structure:** pages `frontend/src/pages/*Page.tsx` (onboarding steps are `Onboard*.tsx`),
  shared pieces in `frontend/src/components/`, routes in `App.tsx`, copy in `i18n/copy.ts`,
  all CSS in `frontend/src/index.css` (single stylesheet, section per screen, appended near the
  related screen's rules).
- **Tokens over literals** — use `var(--…)` from `style.md`; new token only when nothing fits.
- **Shell classes:** `.welcome` (onboarding/welcome), `.register` / `.login` (auth), `.auth-page`
  (verification/app pages), `.app-shell` (signed-in). Never invent a new shell.
- **Buttons:** base `.btn` + modifier (`.btn-primary`, …). `.btn` is declared *after* the
  modifiers in `index.css`, so a modifier that needs to win must be written
  **`.btn.<modifier>`** (specificity), e.g. `.welcome--step .btn.welcome__next { margin-top: 32px }`.
- **Resets that exist:** `* { box-sizing: border-box }`, `h1, h2, p { margin: 0 }`.
  **No `ul`/`ol` reset exists** — any list you introduce must reset itself with `:where(...)`
  (specificity `(0,0,0)` via `:where`), never plain `.parent ul` (specificity `(0,1,1)` beats
  class-based rules like `.lend__timeline`):
  ```css
  :where(.welcome__flow ul, .welcome__flow ol) { margin: 0; padding: 0; list-style: none; }
  ```
- **SVG/PNG assets** live in `frontend/src/images/` (+ `images/icons/` for the wireframe SVGs).
  Imported as URLs and used in `<img>`. Give icons their **natural size** (`max-width/max-height`)
  instead of stretching them; photographic squares get a white padded frame (`.lend__photo`,
  `.look__outfit`).
- **Naming:** BEM-ish — `.block`, `.block__element`, `.block--modifier`, `.block--step` for
  state variants (`.organise`, `.lend`, `.look`, `.step-card`).
- **Accessibility:** decorative images `alt=""`; interactive elements are real `<Link>`/`<button>`
  with visible French labels; step dots use `role="img"` + label like `Étape 2 sur 4`.
- **No comments in code** unless explicitly asked.
- **Footer math (welcome + steps):** `.welcome__flow { flex: 1; padding: 66px 0 35px }`,
  foot = dots (10px, 6px gap) + 32px + `.btn.welcome__next` (297×74) + sign-in link.
  With that: dots land at y683–692, CTA at y725–798, sign-in bottom ≈ y838 at 402×874 — these
  three are **pixel-exact on every step screen** and are a good regression check.

---

## 5. Traps (questions that were already answered — don't ask again)

1. **Calibration viewport = 402 × 874** (the maquette size). Also verify 390 × 844 for overflow.
2. **The maquettes are hand-jittered.** Element centers drift (191–206 px), some rows are off by
   1–3 px, the dot counts differ between pages (3) and reality (4). Keep everything **centered at
   x = 201**, keep **4 dots** everywhere, and treat ±2 px as "match". Never chase a 1–3 px wire
   inconsistency with a negative margin that breaks the other pages.
3. **Text-ink thresholds lie on small text** (thin antialiased glyphs fall outside `dark`/
   `bordeaux` tests). For anything ≲15 px, compare **crops at 3–4×** instead of band numbers.
4. **Deriving font sizes:** measure the same string in wire and render (`textbox.py`/`crop.py`)
   and scale: `new = old × wire_width / render_width`, then re-shoot and re-measure. Remember the
   wire itself is ±5–8 % inconsistent between lines — prefer the value that matches *both* lines
   and the visual weight.
5. **Stacked title leading:** glyph pitch of two lines equals the line-height exactly; the first
   line's glyph sits at `boxTop + (lineHeight − contentHeight) / 2 + ascentOffset`. Empirically
   for Baloo Chettan 2 @40px: `glyph1 ≈ boxTop + lineHeight/2 − 14`. Use it to pick `line-height`
   (and per-line sizes, e.g. `.lend__title span + span { font-size: 32px }`).
6. **Card fill** in the maquettes is `rgba(255,255,255,0.85)` over creme = `(254,254,253)`; the
   render's `rgba(255,255,255,0.85)`-equivalent reads `(255,254,254)` — accepted. Borders are
   1 px `--rose-wash`, radius ≈ 30 px on step cards (`.step-card`), `--radius-card` (20 px) on
   the classic `.card`.
7. **Screenshot runner gotchas:** `run.sh` shows only `tail -8` (use `run2.sh`); scripts are
   CommonJS; PNGs are written to `process.cwd()`; the dev server must be up (`make ps`); Chromium
   comes from `~/.cache/ms-playwright` — if launch reports "Executable doesn't exist", reinstall
   that version inside a container with the cache volume mounted.
8. **TypeScript:** adding a field to `copy.ts` means editing the `AppCopy` interface **and both
   locale objects** in the same pass (they are checked structurally). Convert a string to a line
   array by renaming `body` → `bodyLines: string[]` and updating every consumer.
9. **Imports can be shadowed by destructuring:** in `OnboardLooks.tsx` the copy key `like` was
   destructured over `import like from '…/like.svg'` and rendered a broken image — destructure as
   `like: likeLabel` when the key collides with an asset import.
10. **Vitest/jsdom quirks:** tests fail without the `{' '}` line-break space (§3.6); assert link
    `href`s, not just labels; `css: false` in the vitest config so CSS regressions are *not*
    caught by tests — only by screenshots.
11. **Wire typos are corrected in copy** (never reproduced): `Cusine → Cuisine`,
    `septembte → septembre`, `creer → créer`, `penderie → Penderie`, `tenus → tenues`.
12. **Missing assets are substituted, not faked:** the step-3 wire avatar (two women) doesn't
    exist in the repo → `sophie-pfp.jpg` is used instead; document such substitutions in the
    commit body.
13. **Fonts** are loaded via `@fontsource/baloo-chettan-2` (weights 400–700) — always
    `await document.fonts.ready` before shooting or the metrics are wrong.
14. **Prettier** runs in `make qa` (`format:check`) — run `npm run format` in `frontend/` before
    committing if you hand-wrote JSX/TS.

---

## 6. Accepted decisions & deviations (frozen — do not re-litigate)

| Decision | Rationale |
| --- | --- |
| **4 step dots on every onboarding page** (maquettes show 3) | the welcome screen *is* step 1 → welcome, 2, 3, 4 |
| **"Se connecter" kept on pages 2–4** (absent on maquette p1) | functionally required; same foot component on all screens |
| **Last "Suivant" → `/inscription`** | end of the pre-sign-up carousel |
| **Routes `/`, `/onboarding/2`, `/onboarding/3`, `/onboarding/4`**; unknown step → `<Navigate to="/" replace />` | mirrors the maquettes `After-home-page1/2/3` |
| **CTA = 297×74 pill**, dots 10 px / 6 px gap | sampled from the maquettes, identical on all four screens |
| **Avatar = `sophie-pfp.jpg`** | wire photo not in the repo |
| **Letter-spacing `0.55px` on `.welcome__title`** | the single tracked exception; makes the glyph run match `Home-page.png` |
| **New token `--rose-wash`** | the maquette pink fit no existing rose token |
| **Copy typo corrections** (§5.11) | French quality beats pixel-faithful typos |
| Work happens on **`feature/verify-email-wireframe`**; the user opens the PR into `develop`; **never push `feature/test`** | agreed branch policy |

---

## 7. Done so far (frontend wireframe work)

All commits are on `feature/verify-email-wireframe`, `make qa` green (**51 unit tests** at
`c7dd957`):

| Commit | Screen | Notes |
| --- | --- | --- |
| `aa1aba5` | docs | `style.md` (design system) |
| `ba0224e` + `37a54b7` | Welcome / Home (`Home-page.png`) | full-height SVG background (top wash + curve, bottom blob), mascot pinned over the title, 40/0.82 title, CTA |
| `411cecf` + `135dc6d` + `e38040c` + `38501e5` | Register (`Register-page.png`) | pill fields, gutter pinned above the 402 cap, page-agnostic pill styles |
| `dab9ad2` + `17b9a5d` | Login (`connection-page.png`) | mascot drop shadows warmed up |
| `91141e6` | Email verification (`Email-verif-page.png`) | real address via router state |
| `c7dd957` (current HEAD) | **Onboarding steps 2–4** (`After-home-page1/2/3.png`) | `OnboardingPage` + `OnboardOrganise` / `OnboardLend` / `OnboardLooks`, shared `StepDots` + `OnboardingFoot`, `Onboarding.test.tsx` (4 tests), copy as `*Lines` arrays, `style.md` updated; pixel-calibrated (dots y683, CTA y725–798 exact on all three) |

Screen-by-screen status:

- ✅ Welcome, Login, Register, Email verification, Onboarding steps 2–4 — calibrated at 402 px.
- ⏳ Signed-in app pages (`ItemsPage`, `PlacesPage`, `ProfilesPage`, `FriendsPage`, `AccountPage`,
  `ScanPage`, …) are functional but **not yet wireframe-calibrated** — `Home-page.png`-style
  maquettes don't exist for them yet.
- Known micro-nits (cosmetic, non-blocking): step-3 "Sophie" label ≈ 4 px narrower than the wire,
  step-3 chip 2 ≈ 6 px wider, step-2 tab pill row ≈ 2 px left of the wire.

**Tools used for all of the above:** the scripts in Appendix A (`cmpbands`, `rects`, `crop`,
`runs`, `brd`, `pillbox`) + side-by-side visual review of the final PNGs.

---

## 8. Session-start checklist

1. `git status` / `git log --oneline -8` / current branch (`feature/verify-email-wireframe`).
2. Read `style.md` (tokens/components) and §7 of this file for status.
3. `make ps` — stack up? `make up` if not; dev server on `:5173` must answer before shooting.
4. `/tmp/opencode/shot` present? If not → **Appendix A**.
5. For a new screen: `probe` the maquette → scaffold (§3.3) → shoot → `cmpbands` → calibrate →
   tests → `make qa` → `style.md` → commit (English, workflow body) → push **only**
   `feature/verify-email-wireframe`.
6. Private product context: `SESSION_CONTEXT.md` (gitignored).

---

## Appendix A — recreate the toolkit in one shot

Paste as-is. Creates `/tmp/opencode/shot` with the runner, all measurement scripts and three
example Playwright scripts. If the checkout or the browser cache moved, edit the `REPO=…` default
at the top of the generated `run.sh`.

```bash
SHOT=/tmp/opencode/shot
mkdir -p "$SHOT"

cat > "$SHOT/run.sh" <<'EOS'
#!/usr/bin/env bash
# usage: run.sh <node-script.js>   (output is truncated to the LAST 8 lines — use run2.sh)
# EDIT REPO here or export it if the checkout lives elsewhere.
REPO="${REPO:-/home/atatrishvili/penderie}"
set -e
docker run --rm -u root \
  -v /tmp/opencode/shot:/work \
  -v "$REPO/frontend":/frontend:ro \
  -v "$HOME/.cache/ms-playwright":/ms-playwright \
  -w /work -e PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
  --network=container:penderie-frontend \
  node:24-bookworm bash -c "
    apt-get update -qq >/dev/null 2>&1
    apt-get install -y -qq libnspr4 libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libpango-1.0-0 libcairo2 libasound2 libx11-6 libxcb1 libxext6 >/dev/null 2>&1
    npm i --no-save playwright-core@1.64.1 >/dev/null 2>&1
    node "${1/#\/tmp\/opencode\/shot//work}" 2>&1 | tail -8
    echo NODE_EXIT=\$?
  "
EOS
chmod +x "$SHOT/run.sh"
sed 's/tail -8/tail -200/' "$SHOT/run.sh" > "$SHOT/run2.sh"
chmod +x "$SHOT/run2.sh"

cat > "$SHOT/pngutil.py" <<'EOS'
import sys, zlib, struct

def load(path):
    data = open(path, 'rb').read()
    pos = 8; w = h = ct = None; idat = b''
    while pos < len(data):
        ln = struct.unpack('>I', data[pos:pos+4])[0]
        typ = data[pos+4:pos+8]
        chunk = data[pos+8:pos+8+ln]
        if typ == b'IHDR':
            w, h, bd, ct = struct.unpack('>IIBB', chunk[:10])
        elif typ == b'IDAT':
            idat += chunk
        pos += 12 + ln
    raw = zlib.decompress(idat)
    bpp = 4 if ct == 6 else 3
    stride = w * bpp
    out = bytearray(w * h * bpp)
    prev = bytearray(stride)
    i = 0
    for y in range(h):
        f = raw[i]; i += 1
        line = bytearray(raw[i:i+stride]); i += stride
        if f == 1:
            for x in range(bpp, stride): line[x] = (line[x] + line[x-bpp]) & 255
        elif f == 2:
            for x in range(stride): line[x] = (line[x] + prev[x]) & 255
        elif f == 3:
            for x in range(stride):
                a = line[x-bpp] if x >= bpp else 0
                line[x] = (line[x] + ((a + prev[x]) >> 1)) & 255
        elif f == 4:
            for x in range(stride):
                a = line[x-bpp] if x >= bpp else 0
                b = prev[x]; c = prev[x-bpp] if x >= bpp else 0
                p = a + b - c; pa = abs(p-a); pb = abs(p-b); pc = abs(p-c)
                pr = a if (pa <= pb and pa <= pc) else (b if pb <= pc else c)
                line[x] = (line[x] + pr) & 255
        out[y*stride:(y+1)*stride] = line
        prev = line
    return w, h, bpp, stride, out
EOS

cat > "$SHOT/cmpbands.py" <<'EOS'
import sys
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
def bands(path,test):
    w,h,bpp,stride,out=load(path)
    def px(x,y):
        o=y*stride+x*bpp; return tuple(out[o:o+bpp])
    res=[];cur=None
    for y in range(h):
        xs=[x for x in range(w) if test(px(x,y))]
        if len(xs)>=3:
            if cur is None: cur=[y,y,min(xs),max(xs)]
            else: cur[1]=y;cur[2]=min(cur[2],min(xs));cur[3]=max(cur[3],max(xs))
        else:
            if cur: res.append(tuple(cur));cur=None
    if cur: res.append(tuple(cur))
    return res
def dark(c): return c[0]<200 and c[1]<190 and c[2]<190
def borde(c): return c[0]<160 and c[1]<70 and c[2]<90
W=sys.argv[1]; R=sys.argv[2]
for name,t in (('dark',dark),('bordeaux',borde)):
    wb=bands(W,t); rb=bands(R,t)
    print('==',name,' (wire | rend)')
    for i in range(max(len(wb),len(rb))):
        a=wb[i] if i<len(wb) else None
        b=rb[i] if i<len(rb) else None
        print('  ',a,'|',b)
EOS

cat > "$SHOT/crop.py" <<'EOS'
"""crop.py <in> <x0> <y0> <x1> <y1> <out> [scale]"""
import sys, zlib, struct
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
src=sys.argv[1]
x0,y0,x1,y1=map(int,sys.argv[2:6])
dst=sys.argv[6]
sc=int(sys.argv[7]) if len(sys.argv)>7 else 1
w,h,bpp,stride,out = load(src)
def px(x,y):
    o=y*stride+x*bpp; return out[o:o+bpp]
W=(x1-x0+1)*sc; H=(y1-y0+1)*sc
raw=bytearray()
for yy in range(H):
    raw.append(0)
    sy=min(y0+yy//sc,h-1)
    for xx in range(W):
        sx=min(x0+xx//sc,w-1)
        raw+=px(sx,sy)
def chunk(t,d):
    return struct.pack('>I',len(d))+t+d+struct.pack('>I',zlib.crc32(t+d)&0xffffffff)
png=b'\x89PNG\r\n\x1a\n'
png+=chunk(b'IHDR',struct.pack('>IIBBBBB',W,H,8,2 if bpp==3 else 6,0,0,0))
png+=chunk(b'IDAT',zlib.compress(bytes(raw),9))
png+=chunk(b'IEND',b'')
open(dst,'wb').write(png)
print('wrote',dst,W,H)
EOS

cat > "$SHOT/runs.py" <<'EOS'
"""usage: runs.py <png> y|yrange|x|xrange <args...>"""
import sys
sys.path.insert(0, '/tmp/opencode/shot')
from pngutil import load
path = sys.argv[1]; mode = sys.argv[2]
w, h, bpp, stride, out = load(path)
def px(x, y):
    o = y * stride + x * bpp
    return tuple(out[o:o + bpp])
bg = px(5, h - 5)
def isbg(c, tol=6):
    return all(abs(c[i] - bg[i]) <= tol for i in range(3))
def runs_row(y):
    res = []; x = 0
    while x < w:
        if not isbg(px(x, y)):
            s = x
            while x < w and not isbg(px(x, y)): x += 1
            res.append((s, x - 1))
        else: x += 1
    return res
def runs_col(x):
    res = []; y = 0
    while y < h:
        if not isbg(px(x, y)):
            s = y
            while y < h and not isbg(px(x, y)): y += 1
            res.append((s, y - 1))
        else: y += 1
    return res
if mode == 'y':
    for a in map(int, sys.argv[3:]): print(f'y{a}: {runs_row(a)}')
elif mode == 'x':
    for a in map(int, sys.argv[3:]): print(f'x{a}: {runs_col(a)}')
elif mode == 'yrange':
    a, b = int(sys.argv[3]), int(sys.argv[4]); step = int(sys.argv[5]) if len(sys.argv) > 5 else 4
    for y in range(a, b + 1, step): print(f'y{y}: {runs_row(y)}')
elif mode == 'xrange':
    a, b = int(sys.argv[3]), int(sys.argv[4]); step = int(sys.argv[5]) if len(sys.argv) > 5 else 4
    for x in range(a, b + 1, step): print(f'x{x}: {runs_col(x)}')
EOS

cat > "$SHOT/brd.py" <<'EOS'
"""brd.py <png> row|col <n> — light-pink (--rose-wash) border positions"""
import sys
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
path=sys.argv[1]
w,h,bpp,stride,out=load(path)
def px(x,y):
    o=y*stride+x*bpp; return tuple(out[o:o+bpp])[:3]
def hit(c): return c[1]<240 and c[0]>200
def group(vals):
    res=[];cur=None
    for v in vals:
        if cur and v==cur[1]+1: cur[1]=v
        else:
            if cur: res.append(tuple(cur))
            cur=[v,v]
    if cur: res.append(tuple(cur))
    return res
if sys.argv[2]=='col':
    x=int(sys.argv[3]); print(path.split('/')[-1],'x',x,group([y for y in range(h) if hit(px(x,y))]))
else:
    y=int(sys.argv[3]); print(path.split('/')[-1],'y',y,group([x for x in range(w) if hit(px(x,y))]))
EOS

cat > "$SHOT/pillbox.py" <<'EOS'
"""pillbox.py <png> y0 y1 — x-runs of non-card pixels in the band (pills/tabs/buttons)"""
import sys
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
path=sys.argv[1]; y0=int(sys.argv[2]); y1=int(sys.argv[3])
w,h,bpp,stride,out=load(path)
def px(x,y):
    o=y*stride+x*bpp; return tuple(out[o:o+bpp])[:3]
def notwhite(c): return abs(c[0]-254)>4 or abs(c[1]-254)>4 or abs(c[2]-253)>4
cols=[any(notwhite(px(x,y)) for y in range(y0,y1+1)) for x in range(w)]
runs=[];x=0
while x<w:
    if cols[x]:
        s=x
        while x<w and cols[x]: x+=1
        runs.append((s,x-1))
    else: x+=1
print(path.split('/')[-1], runs)
EOS

cat > "$SHOT/textbox.py" <<'EOS'
"""textbox.py <png> x0 y0 x1 y1 — bbox of dark bordeaux-ish text"""
import sys
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
path=sys.argv[1]; x0,y0,x1,y1=map(int,sys.argv[2:6])
w,h,bpp,stride,out=load(path)
def px(x,y):
    o=y*stride+x*bpp; return tuple(out[o:o+bpp])[:3]
minx=miny=10**9;maxx=maxy=-1
for y in range(y0,y1+1):
    for x in range(x0,x1+1):
        c=px(x,y)
        if c[0]<170 and c[1]<110 and c[2]<130:
            minx=min(minx,x);maxx=max(maxx,x);miny=min(miny,y);maxy=max(maxy,y)
print(f'{path.split("/")[-1]} text x[{minx},{maxx}] w={maxx-minx+1} y[{miny},{maxy}] h={maxy-miny+1}')
EOS

cat > "$SHOT/inkbox.py" <<'EOS'
"""inkbox.py <png> x0 y0 x1 y1 — bbox of any non-background ink"""
import sys
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
path=sys.argv[1]; x0,y0,x1,y1=map(int,sys.argv[2:6])
w,h,bpp,stride,out=load(path)
def px(x,y):
    o=y*stride+x*bpp; return tuple(out[o:o+bpp])[:3]
minx=miny=10**9;maxx=maxy=-1
for y in range(y0,y1+1):
    for x in range(x0,x1+1):
        c=px(x,y)
        if not (c[0]>=246 and c[1]>=246 and c[2]>=244):
            minx=min(minx,x);maxx=max(maxx,x);miny=min(miny,y);maxy=max(maxy,y)
print(f'{path.split("/")[-1]} ink x[{minx},{maxx}] w={maxx-minx+1} y[{miny},{maxy}] h={maxy-miny+1}')
EOS

cat > "$SHOT/iconbox.py" <<'EOS'
"""iconbox.py <png> x0 y0 x1 y1 — bbox of dark icon ink"""
import sys
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
path=sys.argv[1]; x0,y0,x1,y1=map(int,sys.argv[2:6])
w,h,bpp,stride,out=load(path)
def px(x,y):
    o=y*stride+x*bpp; return tuple(out[o:o+bpp])
minx=miny=10**9; maxx=maxy=-1
for y in range(y0,y1+1):
    for x in range(x0,x1+1):
        c=px(x,y)
        if c[0]<215 and c[1]<205 and c[2]<205:
            minx=min(minx,x);maxx=max(maxx,x);miny=min(miny,y);maxy=max(maxy,y)
print(f'{path.split("/")[-1]} bbox x[{minx},{maxx}] w={maxx-minx+1} y[{miny},{maxy}] h={maxy-miny+1}')
EOS

cat > "$SHOT/probe.py" <<'EOS'
"""probe.py <png> x,y x,y ... — RGB of the given pixels"""
import sys
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
path=sys.argv[1]
w,h,bpp,stride,out=load(path)
def px(x,y):
    o=y*stride+x*bpp; return tuple(out[o:o+bpp])[:3]
for arg in sys.argv[2:]:
    x,y=map(int,arg.split(','))
    print(f'({x},{y}) = {px(x,y)}')
EOS

cat > "$SHOT/sbs.py" <<'EOS'
"""sbs.py <wire.png> <render.png> <out.png> — side-by-side composite (wire | render)"""
import sys, zlib, struct
sys.path.insert(0,'/tmp/opencode/shot')
from pngutil import load
def side(wire,rend,out_path):
    w1,h1,b1,s1,d1=load(wire); w2,h2,b2,s2,d2=load(rend)
    def px(d,bpp,s,x,y):
        o=y*s+x*bpp; return tuple(d[o:o+bpp])
    H=max(h1,h2); W=w1+w2+8
    rows=bytearray()
    for y in range(H):
        rows.append(0)
        for x in range(W):
            if x<w1 and y<h1:
                c=px(d1,b1,s1,x,y)
                rows+=bytes(c) if len(c)==4 else bytes(c)+b'\xff'
            elif x>=w1+8 and x<w1+8+w2 and y<h2:
                c=px(d2,b2,s2,x-w1-8,y)
                rows+=bytes(c) if len(c)==4 else bytes(c)+b'\xff'
            else:
                rows+=bytes((110,110,110,255))
    def chunk(t,d):
        return struct.pack('>I',len(d))+t+d+struct.pack('>I',zlib.crc32(t+d)&0xffffffff)
    png=b'\x89PNG\r\n\x1a\n'
    png+=chunk(b'IHDR',struct.pack('>IIBBBBB',W,H,8,6,0,0,0))
    png+=chunk(b'IDAT',zlib.compress(bytes(rows)))
    png+=chunk(b'IEND',b'')
    open(out_path,'wb').write(png)
    print('wrote',out_path,W,H)
side(sys.argv[1],sys.argv[2],sys.argv[3])
EOS

cat > "$SHOT/shoot-steps.js" <<'EOS'
const { chromium } = require('playwright-core')

async function main() {
  const browser = await chromium.launch({ headless: true })
  const shots = [
    { name: 'step2-402', path: '/onboarding/2' },
    { name: 'step3-402', path: '/onboarding/3' },
    { name: 'step4-402', path: '/onboarding/4' },
    { name: 'step2-390', path: '/onboarding/2', width: 390, height: 844 },
    { name: 'step3-390', path: '/onboarding/3', width: 390, height: 844 },
    { name: 'step4-390', path: '/onboarding/4', width: 390, height: 844 },
  ]
  for (const shot of shots) {
    const context = await browser.newContext({
      viewport: { width: shot.width || 402, height: shot.height || 874 },
      deviceScaleFactor: 1,
      locale: 'fr-FR',
    })
    const page = await context.newPage()
    await page.goto(`http://localhost:5173${shot.path}`, { waitUntil: 'networkidle' })
    await page.evaluate(() => document.fonts.ready)
    await page.waitForTimeout(300)
    await page.screenshot({ path: `${process.cwd()}/${shot.name}.png` })
    await context.close()
    console.log('saved', shot.name)
  }
  await browser.close()
}
main().catch((e) => { console.error(e); process.exit(1) })
EOS

cat > "$SHOT/rects.js" <<'EOS'
const { chromium } = require('playwright-core')

// Edit `targets` for the screen you are calibrating: one entry per route with the
// selectors whose geometry you want. Output = x, y, w, h, right, bottom per selector.
async function main() {
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({
    viewport: { width: 402, height: 874 },
    deviceScaleFactor: 1,
    locale: 'fr-FR',
  })
  const page = await context.newPage()
  const targets = [
    {
      path: '/onboarding/4',
      sels: [
        '.look__title', '.look__title span:nth-child(1)', '.look__title span:nth-child(2)',
        '.look__body', '.look__card', '.look__outfit', '.look__weather',
        '.look__actions', '.look__choice', '.look__caption', '.welcome__flow',
      ],
    },
  ]
  for (const t of targets) {
    await page.goto(`http://localhost:5173${t.path}`, { waitUntil: 'networkidle' })
    await page.evaluate(() => document.fonts.ready)
    await page.waitForTimeout(200)
    console.log('==', t.path)
    for (const sel of t.sels) {
      const r = await page.locator(sel).first().boundingBox()
      if (!r) { console.log(sel, 'NOT FOUND'); continue }
      console.log(
        sel.padEnd(34),
        'x', Math.round(r.x), 'y', Math.round(r.y),
        'w', Math.round(r.width), 'h', Math.round(r.height),
        '-> right', Math.round(r.x + r.width), 'bottom', Math.round(r.y + r.height),
      )
    }
  }
  await browser.close()
}
main().catch((e) => { console.error(e); process.exit(1) })
EOS

cat > "$SHOT/overflow.js" <<'EOS'
const { chromium } = require('playwright-core')
const ROUTES = ['/onboarding/2', '/onboarding/3', '/onboarding/4'] // edit per screen
async function main() {
  const b = await chromium.launch({ headless: true })
  for (const w of [390, 402]) {
    const p = await b.newPage({ viewport: { width: w, height: 844 } })
    for (const path of ROUTES) {
      await p.goto('http://localhost:5173' + path, { waitUntil: 'networkidle' })
      const r = await p.evaluate(() => ({
        sw: document.documentElement.scrollWidth,
        cw: document.documentElement.clientWidth,
      }))
      console.log(w, path, r.sw > r.cw ? 'OVERFLOW ' + r.sw + '>' + r.cw : 'ok')
    }
    await p.close()
  }
  await b.close()
}
main()
EOS

echo "toolkit ready in $SHOT"
```

Typical calibration session:

```bash
W=/home/atatrishvili/penderie/frontend/src/Wireframes
bash /tmp/opencode/shot/run2.sh /tmp/opencode/shot/shoot-steps.js
cd /tmp/opencode/shot
python3 cmpbands.py $W/after-home-page3.png step4-402.png
python3 crop.py step4-402.png 0 170 402 225 wire-body.png 3
python3 sbs.py $W/after-home-page3.png step4-402.png sbs-step4.png
# … edit frontend/src/index.css … repeat
```

## Appendix B — frontend file map for onboarding (reference implementation)

```
frontend/src/
├── App.tsx                       # routes: / , /onboarding/:step , /inscription …
├── index.css                     # :root tokens + every screen's CSS (onboarding block at the end)
├── i18n/copy.ts                  # AppCopy interface + fr + en (all UI strings)
├── components/
│   ├── StepDots.tsx              # 4 dots, role="img", label from stepLabel
│   └── OnboardingFoot.tsx        # dots + <Link.btn.btn-primary.welcome__next> + sign-in link
├── pages/
│   ├── Home.tsx                  # step 1 (welcome) — uses OnboardingFoot step={1}
│   ├── OnboardingPage.tsx        # /onboarding/:step → 2|3|4, unknown → <Navigate to="/" replace>
│   ├── OnboardOrganise.tsx       # step 2 "Tout à sa place"
│   ├── OnboardLend.tsx           # step 3 "Prêtez vos affaires…"
│   ├── OnboardLooks.tsx          # step 4 "Des looks chaque jour"
│   ├── Onboarding.test.tsx       # 4 tests: each step renders + links, unknown step → Accueil
│   └── Home.test.tsx             # welcome assertions (mascot, dots ×4, Suivant → /onboarding/2)
└── images/                       # Veste_beige.png, outfit_of_the_day_example.png, sophie-pfp.jpg
    └── icons/                    # wireframe SVGs: room icons, timeline, sun, like/dislike
```

Copy keys live under `getCopy().onboard.{organise,lend,look, next, signIn}` plus
`home.stepLabel` ("Étape N sur 4"). The foot component receives `nextTo` (route of the next
screen) — step 4 sends the user to `/inscription`.
