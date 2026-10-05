# DinkERP Implementation Review Checklists

Use only the sections relevant to the task.

## Every task

- Source docs and Task ID identified.
- Existing code/conventions inspected before editing.
- Scope/out-of-scope respected.
- No unrelated refactor.
- No new requirement/schema/action invented.
- Tests added for behavior and negative paths.
- Static analysis/lint/type checks run as applicable.
- Changed public contract reflected in docs/OpenAPI/generated client.

## Migration / persistence

- Forward-only migration.
- Fresh migration tested.
- Forward migration tested when applicable.
- PK/FK/unique/index rules match DBD.
- Seed idempotent.
- Concurrency-sensitive uniqueness has final DB guard.
- No table/column added merely for UI convenience.

## Authorization

- Correct Resource + Action.
- Correct authoritative Evaluation Organization source.
- Base Permission checked first.
- No System Admin bypass.
- GROUP vs Authorization Group not confused.
- Exact ancestor path and assignment eligibility tested.
- ABSOLUTE_DENY propagation tested.
- Invisible target 404 / visible-denied 403 tested.
- Client context cannot override authoritative context.

## API

- `/api/v1`, snake_case.
- BIGINT/DECIMAL serialization correct.
- request_id propagated.
- Error code stable and status mapping correct.
- Authorization occurs before protected data disclosure/count.
- `Accept-Language` / `Content-Language` behavior tested where human-readable messages are returned.
- OpenAPI lint passes.
- Generated frontend artifacts refreshed.

## UI

- Uses current API contract; no duplicate business rules.
- Permission-aware UI is UX only, not security authority.
- 401/403/404/409/422/500 states handled.
- User-facing application text uses semantic `vue-i18n` keys.
- `vi`/`en` key parity preserved.
- Canonical key, not translated label, is stored for menu/favorite/recent/workspace tab identity.
- No authoritative decimal calculation via JavaScript Number.
- Typecheck/lint/unit/build pass.
- Critical Playwright and accessibility smoke pass.

## Auto Numbering

- Active/default series validation.
- Scope and period timezone resolution correct.
- First-counter race serialized.
- Existing counter locked.
- Manual occupied candidate does not consume counter.
- Generated collision advances safely.
- Rollback returns counter state.
- Committed number not reused.
- Issued series immutable/no-hard-delete rules enforced.

## Product Import

- Only enabled Product import is exposed.
- CSV/XLSX strict rules preserved.
- Formula cells rejected.
- Unknown header rejects whole file.
- source_key dependency graph used for create dependencies.
- UPDATE existing target uses persistent product_code.
- Two-gate authorization enforced.
- Row result keeps original row number.
- Failed row leaves no partial mutation/number allocation.
