<!--
  Thanks for contributing to Penderie!
  Open the PR from your feature/* branch against `develop`.
  Every check below must be green before the PR can be merged.
-->

## What does this PR do?

<!-- 1–3 sentences. Link the issue / the product decision if there is one. -->

## Type of change

- [ ] Bug fix
- [ ] New feature / vertical slice
- [ ] Refactor (no behaviour change)
- [ ] Documentation only
- [ ] Chore (tooling, CI, dependencies)

## Why?

<!-- What problem does it solve, and which user journey (WORKFLOWS.md) does it serve? -->

## How to test it manually

<!-- Steps a reviewer can follow against the local stack (`make up`, http://localhost:5173). -->

1.
2.

## Quality gate

Run locally with `make qa` (or let CI confirm it):

- [ ] `make cs` — coding standard (PHP-CS-Fixer)
- [ ] `make stan` — PHPStan level 6, no new errors
- [ ] `make test` — backend tests pass
- [ ] `make lint-symfony` — container + YAML linters pass
- [ ] `make audit` — no dependency advisories
- [ ] `make fe-lint` / `make fe-typecheck` / `make fe-test` — frontend checks pass
- [ ] New behaviour is covered by tests (backend and/or frontend)

## Checklist

- [ ] Docs updated where needed (`PRODUCT_SPEC.md`, `WORKFLOWS.md`, `README.md`)
- [ ] `PERMISSIONS.md` reviewed — this PR touches authentication, authorization, sharing, profile access or resource visibility
- [ ] New user-facing strings are localisable in French and English (no hard-coded text)
- [ ] No secrets, API keys or provider credentials committed
- [ ] Accessible UI: keyboard reachable, visible focus, labelled inputs, error messages tied to fields
- [ ] No unrelated changes mixed in (the worktree stays clean)

## Screenshots (if the UI changed)

<!-- Drag and drop images here. -->
