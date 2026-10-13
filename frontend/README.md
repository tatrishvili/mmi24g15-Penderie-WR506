# Penderie frontend

The frontend is a React + TypeScript application built with Vite. Product behavior and language/security requirements are documented in the repository-root [PRODUCT_SPEC.md](../PRODUCT_SPEC.md) and [WORKFLOWS.md](../WORKFLOWS.md).

## Development

From this directory:

```bash
npm run lint
npm run typecheck
npm test
npm run build
```

For the full Docker-based setup and backend commands, see the repository-root [README.md](../README.md).

## Frontend conventions

- Route API calls through the existing `src/api/` modules and shared client.
- Keep user-visible text ready for French and English localization; do not couple domain/API values to translated labels.
- Frontend visibility is not authorization. The backend must enforce account, household, profile, and item permissions.
- Scan results are drafts. Show failures and require user review before saving an item.
- Add or update focused Vitest coverage for user-visible behavior and denied/empty/error states.
