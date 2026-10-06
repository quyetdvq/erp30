# P1-IMP-007 Product Import Two-Gate Authorization

## Outcome

Implemented `ProductImportAuthorizationService` under
`App\Modules\Product\Import` as the Product import application boundary.
The service requires a trusted `OperationContext`, authorizes the
`product.import` start gate at the authoritative GROUP, and only then creates
an Import Job or delegates row execution.

## Gate 1

- `product.import` is evaluated through the canonical `AuthorizationService`.
- Product is a GROUP Master, so the resolver supplies the canonical GROUP root.
- A client organization hint is validated against that context and cannot
  override it.
- Denial occurs before Import Job creation, Product/UOM/COMBO mutation, or
  Auto Number allocation.

## Gate 2

`ProductImportAuthorizationService` passes the same trusted
`OperationContext` to the existing mutation services. No authorization logic
is duplicated in Import:

- Product create uses canonical `product.create`.
- Product updates use canonical `product.edit`.
- Product status changes still require canonical `product.setstate`.
- UOM conversions and COMBO items use the Product `product.edit` boundary.
- UPSERT follows the actual mutation path selected by the canonical service.

Denied rows remain in-memory results and do not persist `import_job_rows` or
job reconciliation state. No System Admin bypass, new Resource/Action,
permission table, schema, API, or UI was added.

## Validation

- IMP-001..007 focused regression: PASS; 37 tests, 166 assertions.
- AUTH-001..005 and AUTH-090 relevant suites: PASS.
- Product/UOM/COMBO/NUM regressions: PASS.
- Full PHPUnit: PASS; 349 tests, 3,273 assertions.
- PHPStan level 8: PASS.
- Context cache, architecture, module-map, and ledger guards: PASS.
- Scoped `git diff --check`: PASS; unrelated `docs/thamkhao` modification
  remains untouched.

## Scope boundary

P1-IMP-008 row-result persistence and job reconciliation were not started.
