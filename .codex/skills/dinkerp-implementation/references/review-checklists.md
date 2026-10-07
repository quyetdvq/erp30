# DinkERP Implementation Review Checklists

Use only the sections relevant to the task.

## Every task

- Task ID, compiled context, and source pointers identified.
- Existing code/conventions inspected before editing.
- Scope/out-of-scope respected; no unrelated refactor.
- No new requirement/schema/action/endpoint semantics invented.
- Approved Product/Pricing/Import ownership preserved.
- Tests include behavior and relevant negative paths.
- Static analysis/lint/type checks run as applicable.
- Changed public contract reflected in OpenAPI/generated client in the same task.
- Worktree checked before/after; unrelated pre-existing changes untouched.

## Architecture boundaries

- Product does not import Pricing.
- Pricing does not import concrete Product repositories; use approved Product service/contract boundaries.
- Shared `Infrastructure\Import` does not import Product or other business modules.
- No `App\Modules\Import` top-level business module introduced.
- Controllers/public adapters do not import repositories or reimplement business/authorization rules.

## Migration / persistence

- Forward-only migration.
- Fresh migration tested; forward migration tested when applicable.
- PK/FK/unique/index rules match DBD.
- Seed idempotent.
- Concurrency-sensitive uniqueness has final DB guard.
- No table/column added merely for UI convenience.

## Authorization

- Correct Resource + Action.
- Resource catalog/domain resolved before context; only `SYSTEM`/`MASTER`/`ORGANIZATION`, with `SYSTEM`/`MASTER -> GROUP`.
- Correct authoritative Evaluation Organization source; ORGANIZATION resolver fails closed if missing/unresolved.
- Base Permission checked first; no System Admin bypass.
- GROUP vs Authorization Group not confused.
- Exact ancestor path and assignment eligibility tested where relevant.
- ABSOLUTE_DENY propagation tested.
- Invisible target 404 / visible-denied 403 tested.
- Client context cannot override authoritative context; Primary Assignment is not a generic Resource-scope fallback.

## API / Gate 7

- Endpoint/surface is `CONTRACT_READY`; `CONTRACT_PENDING` items remain blocked/out-of-scope.
- `/api/v1`, snake_case, BIGINT/DECIMAL string serialization.
- Request ID accepted/generated, echoed, and propagated.
- Protected endpoint declares Resource/Action, authoritative Evaluation Organization, and visibility behavior.
- Authorization/data scope is applied before collection totals/counts.
- Query/filter/sort/search use allowlists and deterministic tie-breaks.
- Error code/status mapping is stable; 400/401/403/404/409/422/500 semantics are not collapsed.
- `Accept-Language` / `Content-Language` behavior tested where human-readable messages are returned.
- Controller remains thin and does not call repositories directly.
- OpenAPI source updated in the same public-contract task; Redocly lint passes.
- Generated frontend client/types regenerated and not hand-edited; drift/smoke check passes.
- Exact export media type is not invented while contract remains pending.
- For P1-API-020, Department/Position/Employee stay MASTER/GROUP; Employee Assignment uses request/persisted row `organization_id` according to operation.
- Employee Organization/Department/Position filters are data filters only; list scope decisions happen before total/pagination.
- Employee gender uses only the DEC-P1-001 nullable catalog `MALE`, `FEMALE`, `OTHER`, or `null`.

## UI

- Uses current API contract; no duplicate business rules.
- Permission-aware UI is UX only, not security authority.
- 401/403/404/409/422/500 states handled.
- User-facing application text uses semantic `vue-i18n` keys; `vi`/`en` parity preserved.
- Canonical keys, not translated labels, drive menu/favorite/recent/workspace identity.
- No authoritative decimal calculation via JavaScript Number.
- Typecheck/lint/unit/build pass; critical Playwright/accessibility smoke runs when required.

## Auto Numbering

- Active/default series validation.
- Scope and period timezone resolution correct.
- First-counter race serialized; existing counter locked.
- AUTO_IF_EMPTY manual identifier does not consume counter.
- Generated collision advances safely.
- Rollback restores counter state; committed number not reused.
- Issued series immutable/no-hard-delete rules enforced.

## Product / Pricing / COMBO

- Product Group arbitrary-depth hierarchy is not confused with Product Parent/Child max depth 1.
- Product Group hierarchy mutation uses `product-group-tree` lock and ancestor ACTIVE checks for new Product references.
- Pricing fallback is Company -> Group -> correct-UOM default with deterministic order.
- COMBO only uses Base UOM pricing; nested COMBO price is not re-resolved during allocation.
- Allocation uses decimal-safe leaf weights, rejects zero total weight, conserves root amount, and applies deterministic residual.

## Product Import

- Only enabled Product import is exposed.
- Shared Import infrastructure stays Product-agnostic; Product-specific logic stays under Product/Import.
- CSV/XLSX strict rules and format-neutral row model preserved.
- Formula cells rejected; unknown header rejects whole file.
- `source_key` dependency graph handles forward references; persistent updates use `product_code`.
- Two-gate authorization enforced at GROUP + actual row mutation action.
- Original row number preserved despite processing reorder.
- Failed row leaves no partial business mutation/number allocation.
- Generated Product code becomes successful row `entity_key`.
- SUCCESS/ERROR/SKIPPED row persistence reconciles job counts from DB rows.
- Reconciliation locks `import_jobs` `FOR UPDATE`; SKIPPED counts only toward total.
- Same source hash does not auto-skip rerun; raw source payload is not persisted unnecessarily.
