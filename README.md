# Penderie

> Your whole home inventory in your phone — what do I own, where is it, who did I lend it to, what should I do with it?

Penderie is a personal inventory application focused on clothing and dressings: scan an item, let the app fill in
the details, then find it, lend it, share it or sell it to people you trust.

This repository contains the **development environment** (Docker) and the first working slice of the product:
a Symfony API and a React front end, wired together and covered by CI.

---

## Stack

| Layer     | Technology                                              |
| --------- | ------------------------------------------------------- |
| API       | Symfony 7.4 LTS (PHP 8.4), Doctrine ORM, MariaDB 11     |
| Front end | React 19, TypeScript 6, Vite 8, React Router            |
| QA        | PHP-CS-Fixer, PHPStan, PHPUnit · oxlint, tsc, Vitest    |
| Runtime   | Docker Compose (nginx + php-fpm + MariaDB + Node)       |
| CI        | GitHub Actions (`.github/workflows/ci.yml`)             |

## Repository layout

```
.
├── backend/                  Symfony API (PHP 8.4)
│   ├── src/                  Application code
│   ├── tests/                PHPUnit tests
│   ├── migrations/           Doctrine migrations
│   ├── phpstan.neon          Static analysis config
│   └── .php-cs-fixer.dist.php Coding standard config
├── frontend/                 React application (Vite dev server)
│   ├── src/                  Components, pages, tests
│   └── vite.config.ts        Dev server + /api proxy + Vitest config
├── docker/                   Dockerfile + nginx configuration
├── docker-compose.yml        Local environment definition
└── .github/workflows/ci.yml  Continuous integration
```

## Prerequisites

- Docker + Docker Compose v2
- Node.js 24 and npm 11 (only needed if you prefer running front-end tooling on the host)

## Quick start

```bash
docker compose up -d --build
```

Then open:

| URL                              | Service                                          |
| -------------------------------- | ------------------------------------------------ |
| http://localhost:5173            | Front end (Vite dev server, proxies `/api`)      |
| http://localhost:8000/api/health | API health check (JSON)                          |
| 127.0.0.1:3306                   | MariaDB (`penderie` / `penderie`, db `penderie`)  |

The whole chain — browser → Vite → nginx → PHP-FPM → MariaDB — is containerised; nothing needs to be
installed on the host except Docker.

## Common commands

```bash
# Backend (executed inside the PHP container)
docker compose exec api composer install
docker compose exec api composer cs:check    # coding standard
docker compose exec api composer cs:fix      # fix coding standard
docker compose exec api composer stan        # static analysis
docker compose exec api composer test        # unit tests
docker compose exec api composer audit       # dependency vulnerabilities
docker compose exec api php bin/console list # Symfony console

# Frontend (host or container — node_modules are shared through the mount)
cd frontend
npm run lint        # oxlint
npm run typecheck   # tsc
npm test            # vitest
npm run build       # type check + production build
```

## Environment files

| File                     | Purpose                                                        |
| ------------------------ | -------------------------------------------------------------- |
| `.env.example`           | Docker Compose variables (copy to `.env` if you need overrides) |
| `backend/.env`           | Symfony configuration (committed, **dev-only** values)         |
| `backend/.env.local`     | Local secrets — **never committed**                            |
| `OPENROUTER_API_KEY`     | Reserved for the item-scanning feature, lives in `backend/.env.local` |

## Git workflow (Git Flow)

```
feature/*  →  develop  →  release/* / pre-production/*  →  main
```

- `main` is frozen until the project is finished — **never push directly to it**.
- Every feature lives on `feature/<name>`, branched from `develop`.
- Merge only when **CI is green** (push or open a pull request and wait for the checks).
- All commits, documentation and code comments are written in **English**.

## Continuous integration

`.github/workflows/ci.yml` runs on every push to `main`, `develop`, `feature/**`, `release/**`,
`pre-production/**` and on every pull request:

- **Backend**: PHP 8.4 → `composer install` → PHP-CS-Fixer → PHPStan → PHPUnit → `composer audit`
- **Frontend**: Node 24 → `npm ci` → oxlint → `tsc` → Vitest → production build

> The repository is private on a free plan, so GitHub branch protection is not available: the flow above
> is enforced by discipline — CI must be green before any merge.

## Roadmap

1. ✅ Development environment (Docker, API, front end, CI)
2. 🔜 Item scanning: photo → base64 → LLM vision API → prefilled item form
3. 🔜 Wardrobe, rooms, storage locations and people
4. 🔜 Lending, condition tracking, sharing and outfit proposals
