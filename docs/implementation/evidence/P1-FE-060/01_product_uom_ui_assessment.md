# P1-FE-060 Product/UOM UI Assessment

## Scope and readiness

`P1-FE-060 — Product/UOM UI` was executed after the bounded task context
reported `AUTHORITY_CACHE: VALID`, `P1-API-050 DONE`, `P1-FE-050 DONE` at
`a658ee2d4d91019c3d1cc644c9122eb2edbade1e`, and `P1-FE-060 NOT_STARTED`.
`P1-FE-070` was not started. The mandatory `frontend-table-first.md` reference
was applied.

## Implemented screens and behavior

- `/products/groups` uses a Table First tree-table with expand/collapse,
  root/child creation, edit/reparent, parent selector, self/descendant UX
  exclusion, and flat `parent_id` payloads. Cycle enforcement remains backend
  authority.
- `/products/uoms` uses Table First list/query/pagination with canonical
  `product_uom.view/create/edit/setstate` permission-aware CRUD and state
  actions.
- `/products` exposes the requested product columns and only the canonical
  `q`, `status`, `product_type`, `role`, `product_group_id`, and `base_uom_id`
  filters. `/products/new` and `/products/:id` provide role-aware editing.
- STANDARD root, CHILD, COMBO, and derived PARENT UX follows the authority:
  CHILD uses Parent + Product Child Name and display-only `Parent - Child`;
  PARENT is not selectable/transactionable; COMBO forces Serial false and
  does not expose conversions.
- SIMPLE/CHILD conversions use the approved GET/PUT endpoints. Decimal and
  BIGINT values remain canonical strings; no frontend authoritative arithmetic
  or FE-070 combo-items surface was added.
- Product navigation is a permission-filtered nested Menu group. The price
  policy placeholder is not rendered without its canonical permission/route.

## Validation

| Check | Result |
|---|---|
| `php .../task-context.php P1-FE-060` | PASS; `AUTHORITY_CACHE: VALID`, dependency gates satisfied |
| API-050 focused PHPUnit | PASS; 3 tests, 53 assertions |
| Product/UOM backend regressions | PASS; 42 tests, 402 assertions |
| `pnpm run test:unit -- --run` | PASS; 13 files, 29 tests |
| `pnpm run type-check` | PASS |
| `pnpm run lint:eslint` | PASS |
| `pnpm run lint:oxlint` | NOT PASS; untouched pre-existing `SearchableSelect.spec.ts:10:11` unused `wrapper` |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS |
| `pnpm run openapi:lint` | PASS |
| `pnpm run openapi:generate:check` | PASS; 17 generated files verified |
| `pnpm exec playwright test e2e/fe060-product-uom.spec.ts --project=chromium` | PASS; 3 tests; task-specific critical axe checks clean |
| Full existing Playwright suite | NOT RUN; focused FE-060 suite exercised |
| `git diff --check` | NOT PASS globally because unrelated pre-existing `Tracuuu-Tonghop.md` whitespace; task files clean |

## Contract impact

- DB/schema: none; no migration or schema changes.
- Authorization: existing Base Permission keys only; backend remains final
  authority for 403/404/409/422 and hierarchy/role invariants.
- API/OpenAPI: generated API-050 operations reused; no public contract drift.
- UI/i18n: Product menu, four screens, vi/en keys, Table First structure, and
  permission-aware actions added.
- Accessibility: focused Chromium/axe coverage passes critical checks. Existing
  shell contrast/landmark/icon findings remain outside this bounded task.

Commit: recorded by task closure.
P1-FE-070 started: NO.
