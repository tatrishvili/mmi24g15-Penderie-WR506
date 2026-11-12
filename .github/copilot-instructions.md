# Penderie agent instructions

## Latest session handover

- Before resuming work, read [session03/11/26.md](../session03/11/26.md). It records the implemented child accounts, parent decisions, item audiences, repeated rooms, approvals and Docker recovery; do not ask the user to re-explain settled decisions.

## Product source of truth

- Read [PRODUCT_SPEC.md](../PRODUCT_SPEC.md) for product decisions, domain vocabulary, security boundaries, and current implementation gaps.
- Read [WORKFLOWS.md](../WORKFLOWS.md) for user journeys.
- Read [PERMISSIONS.md](../PERMISSIONS.md) before changing authentication, authorization, sharing, profile access, or resource visibility.
- Read [README.md](../README.md) for setup, architecture, and validation commands.
- `CONCEPT.md` is a local, git-ignored legacy note; do not treat it as canonical when it conflicts with the tracked product docs.

## Project conventions

- Keep the product personal-first: registration must not require a household name or family member.
- Family/profile management is optional and belongs in Settings. Do not weaken server-side access checks to make a UI flow work.
- Treat child sign-in, profile switching, household membership, item ownership, and sharing as security-sensitive. Enforce permissions on every API operation and add tests for allowed and denied access.
- A pure permission resolver is not enough: account verification, link scope/revocation, parent approval, and field filtering must be enforced in the request/application layer. Anonymous link viewers are read-only; comments require a signed-in verified account.
- Never treat a household slug, QR token, or short PIN as authorization by itself. Never expose secrets or provider keys in the frontend.
- Keep items, categories, ownership, physical locations, and activity history as separate concepts. Location fields are optional for quick item entry.
- Scan/AI output is untrusted and must be validated. Keep the user in control: review before saving; report provider/configuration/invalid-output failures explicitly; do not turn failures into fake successful results.
- Keep all new user-facing frontend strings localizable in French and English. Keep API/domain values language-neutral.
- Preserve unrelated worktree changes. Inspect the existing code and tests before editing; do not assume the documentation's desired behavior is already implemented.
- If a change affects account ownership, child permissions, privacy defaults, item lifecycle, or physical-location relationships and the docs do not resolve the decision, ask the product owner before changing the data model or behavior.
- Do not perform broad folder/model rewrites just because the current project feels messy. Prefer the smallest vertical slice that follows the product specification.

## Validation

- Backend in Docker: `docker compose exec api composer cs:check`, `docker compose exec api composer stan`, `docker compose exec api composer test`.
- Frontend: from `frontend/`, run `npm run lint`, `npm run typecheck`, `npm test`, and `npm run build` as relevant to the change.
- Update `PRODUCT_SPEC.md` and `WORKFLOWS.md` when a product decision or user journey changes.
