# P1-IMP-006 Product Import Row Mutation

## Outcome

P1-IMP-006 implements Product-owned row execution under
`App\Modules\Product\Import`. `ProductImportRowMutationService` consumes the
validated dependency plan in processing order, keeps original source row
numbers, returns in-memory row outcomes, and maintains a runtime
`source_key -> product_id/product_code` map. No `import_job_rows`, job summary,
API, UI, or schema persistence was added.

## Canonical boundaries

- Product rows call `ProductMasterService` for create, update, and setstate.
- UOM conversion rows call `ProductUomService` and resolve shared UOM masters
  through `ProductUomMasterService`.
- COMBO rows call `ComboCompositionService::mergeItem`, which uses the existing
  graph lock, product-structure locks, role/cycle validation, authorization,
  audit, and transaction. Unrelated direct components remain untouched.
- Import contains no direct Product/UOM/COMBO repository writes and adds no
  `App\Modules\Import` namespace.

## Semantics and invariants

`CREATE_ONLY`, `UPDATE_ONLY`, and `UPSERT` are applied to Product, conversion,
and COMBO logical keys. Product updates never pass or generate a new code.
Blank cells are omitted for update; `__NULL__` is passed as an explicit null.
Generated Product codes are returned in row results and become available to
later source-key references. Product creation continues to use the canonical
Auto Number transaction, so allocation and Product persistence roll back
together on failure. Each logical row is processed independently; a later
error cannot undo an earlier committed row.

## Validation evidence

- IMP-006 focused PHPUnit: PASS (5 tests, 23 assertions).
- IMP-001/004/005 regression: PASS (21 tests, 107 assertions).
- NUM-006 and mandatory numbering regression: PASS (12 tests, 86 assertions).
- Product/UOM/COMBO regressions: PASS (22 tests, 207 assertions).
- PHPStan level 8: PASS.
- Context cache, architecture guard, ledger guard, and scoped diff checks:
  PASS.
- Full PHPUnit was run serially against the approved `erp_test` database; its
  result is recorded in the completion report.

## Boundary

P1-IMP-007 two-gate import authorization and P1-IMP-008 row/job reconciliation
remain unimplemented and were not started.
