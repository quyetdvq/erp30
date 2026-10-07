# P1-API-040 Auto Numbering API Assessment

## Result

Status: `DONE`

Implementation commit: `bb2a3c7dd30ec70fc3805bc79bb9dd5ad608790`

The six approved Auto Numbering operations reuse the existing configuration,
repository, authorization, locking, allocation, rollback, and no-reuse
semantics. No migration, DELETE route, renumber operation, Product API, or
P1-API-050 work was started.

## Operations

Runtime and OpenAPI each expose exactly six operations:

1. `GET /resources/{resource_id}/numbering-series`
2. `POST /resources/{resource_id}/numbering-series`
3. `GET /resources/{resource_id}/numbering-series/{series_id}`
4. `PATCH /resources/{resource_id}/numbering-series/{series_id}`
5. `POST /resources/{resource_id}/numbering-series/{series_id}/actions/set-state`
6. `POST /resources/{resource_id}/numbering-series/actions/validate-config`

The list operation uses the canonical collection envelope. `start_number` is
transported as a BIGINT JSON string. Authorization uses the canonical
`resource.configure_numbering` action through the existing authorization port
and service boundary; `resource.edit` is not substituted.

Configuration preserves the existing `GROUP|COMPANY|ORGANIZATION` scope,
`AUTO_REQUIRED|AUTO_IF_EMPTY` generation, `NEVER|YEARLY|MONTHLY|DAILY` reset,
pattern, and identifier-namespace validators. Active default changes lock the
Resource row inside the existing configuration transaction. Issued series
identity fields are immutable, issued series cannot be hard-deleted, and
changing `start_number` does not rewrite an existing counter. Validation is
strictly non-persisting and non-consuming.

## Final validation

All commands below were run against the final implementation tree. Database
tests used the guarded `testing` environment and exact database `erp_test`.

| Check | Result |
|---|---|
| Focused Auto Number/API suites | PASS: 20 tests, 131 assertions |
| Full PHPUnit (`test-db-harness.php full-tests`) | PASS: 392 tests, 3,516 assertions |
| PHPStan level 8 | PASS: no errors |
| OpenAPI Redocly lint | PASS |
| OpenAPI codegen and generated-client drift | PASS: 17 tool-owned files |
| Frontend type-check | PASS |
| Frontend lint | PASS |
| Frontend Prettier check | PASS |
| Frontend production build | PASS |
| Runtime/OpenAPI operation parity | PASS: 6 / 6 |
| Architecture guard | PASS: 42 application files checked |
| Ledger validator | PASS: 76 DONE tasks |
| Context cache | PASS: 7 authorities, 100 tasks, acyclic |
| `git diff --check` | PASS |

Allocation semantics were not changed. The validate-config endpoint allocated
no counter, advanced no counter, and consumed no business number in the
focused non-consuming validation tests.
