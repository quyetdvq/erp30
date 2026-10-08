# P1-FE-070 Pricing/COMBO UI Assessment

## Scope and readiness

`P1-FE-070 — Pricing/COMBO UI` was executed after the bounded task context
reported `AUTHORITY_CACHE: VALID`, `P1-API-050 DONE`, and `P1-FE-070 NOT_STARTED`.
The mandatory `frontend-table-first.md` reference was applied. `P1-FE-080` and
`P1-FE-090` were not started.

The approved UI surface is `/products/price-lists`. Price Resolution Tester and
COMBO Allocation Preview remain out of scope because their authority status is
`DESIGN_PENDING` / `CONTRACT_PENDING`.

## Implemented behavior

- Added the permission-gated Price Policy menu item and `/products/price-lists`
  route.
- Added a Table First Price List master table with Code, Name, Scope
  Organization, Effective From/To, Priority, Status, and Actions columns.
- Added create/edit modal fields for Code, Name, GROUP/COMPANY Organization
  scope, effective dates, priority, status-on-create, and description.
- Price List PATCH requests strip `status`; status changes use the canonical
  set-state action endpoint.
- Added detail/items modal using the existing list/replace items endpoints.
  PARENT is excluded, COMBO exposes Base UOM only, and active SALES/BOTH
  conversions are available for SIMPLE/CHILD. PURCHASE-only alternates are
  excluded.
- Preserved BIGINT identifiers and DECIMAL prices as strings in UI drafts and
  request payloads. Backend remains authoritative for 403/404/409/422 and all
  business validation.
- Added vi/en pricing translations with canonical permission checks for create,
  view, edit, and set-state actions.

## Validation

| Check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-FE-070` | PASS; `AUTHORITY_CACHE: VALID`, HIGH/frontend, dependency ready |
| Pricing/COMBO backend regressions with `phpunit --no-coverage` | PASS; 34 tests, 243 assertions |
| `pnpm run test:unit -- --run` | PASS; 14 files, 31 tests |
| `pnpm run type-check` | PASS |
| touched-file ESLint/Oxlint | PASS |
| `pnpm run lint` | NOT PASS; untouched pre-existing `src/components/forms/__tests__/SearchableSelect.spec.ts:10` Oxlint unused `wrapper` |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS |
| `pnpm run openapi:lint` | PASS |
| `pnpm run openapi:generate:check` | PASS; 17 generated files verified |
| `pnpm exec playwright test e2e/fe070-pricing-combo.spec.ts --project=chromium --retries=0` | PASS; 4 tests; critical axe violations empty |
| preview Playwright smoke (`CI=1`) | NOT PASS; existing Naive UI preview chunk error `V is not a function`; dev-server critical smoke passed |
| `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | PASS; dependency graph acyclic |
| `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | PASS before closure update; ledger row/count updated for this task |
| task-scoped `git diff --check` | PASS |

## Contract impact

- DB/schema: none; no migration or schema changes.
- Authorization: canonical `price_list.view/create/edit/setstate` Base Permission
  checks only; backend remains final authority.
- API/OpenAPI: existing generated API-050 operations reused; no public contract
  drift.
- UI/i18n: Price List Table First route, modal CRUD/items workflow, vi/en keys,
  and conservative permission hiding added.
- COMBO scope: no resolution tester or JS allocation preview was added.

Commit: recorded by task closure.
