# P1-IMP-090 Product Import Regression Suite

## Outcome

Completed the Gate 6 cross-task regression suite in
`backend/tests/Modules/Product/Import/P1Imp090ProductImportRegressionTest.php`.
The suite exercises the real Product import pipeline and reuses the focused
IMP-001..008 suites for local contracts rather than duplicating them.

## Cross-task coverage

- CSV and XLSX use equivalent normalized headers and produce equivalent
  successful Product jobs, including Vietnamese text.
- XLSX formula cells are rejected before row mutation; the job is terminal
  `FAILED` and no import rows or Product are persisted.
- Reordered child/parent source rows are topologically processed while the
  persisted result keeps original row numbers and generated Product codes.
- `CREATE_ONLY`, `UPDATE_ONLY`, and `UPSERT` are covered by the end-to-end
  pipeline; blank cells retain update values while `__NULL__` clears them.
- An UPSERT containing no UOM/COMBO rows leaves existing dependency rows
  untouched.
- Re-running the same source hash creates and processes a distinct job; it is
  not treated as an automatic idempotent success.
- Gate 1 denial creates no job, and a SYSTEM_ADMIN operation cannot bypass the
  authorization boundary.

The existing focused IMP-001..008 tests cover the remaining mandatory local
contracts: parser grammar, template/unknown-header rejection, nested COMBO
cycle detection, Auto Number modes and rollback, row-level authorization,
persisted SUCCESS/ERROR/SKIPPED results, row-derived counts, and `FOR UPDATE`
reconciliation.

## Scope and defects

No production defect was exposed by the cross-task suite, so no application
code, schema, API, UI, authorization semantics, or task dependencies were
changed. No Gate 7 task was started. The unrelated
`docs/thamkhao (Khong duoc su dung)/Tracuuu-Tonghop.md` working-tree change was
left untouched.

## Validation

- IMP-090 focused: PASS; 6 tests, 41 assertions.
- IMP-001..008 plus IMP-090: PASS; 64 tests, 305 assertions.
- DB-080 and relevant NUM/Product/UOM/COMBO/AUTH regressions: PASS; 45 tests,
  440 assertions.
- Full PHPUnit: PASS; 362 tests, 3,353 assertions. PHPUnit emitted the
  existing warning that no code coverage driver is available.
- PHPStan level 8: PASS; no errors.
- Context cache validator: PASS.
- Architecture guard: PASS.
- Module-map/ledger validators: PASS after ledger update.
- Scoped `git diff --check`: PASS.
