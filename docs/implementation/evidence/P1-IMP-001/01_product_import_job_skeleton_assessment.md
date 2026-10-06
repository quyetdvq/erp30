# P1-IMP-001 Import Job Repository/Service Skeleton

Status: DONE

## Outcome

Implemented the generic persistence adapter at
`App\Infrastructure\Import\Persistence\ImportJobRepository` and the
Product-specific orchestration boundary at
`App\Modules\Product\Import\ProductImportJobService`.

The service creates only `PRODUCT` jobs with `CREATE_ONLY`, `UPDATE_ONLY`, or
`UPSERT` mode and `CSV` or `XLSX` source format. Every new job starts as
`PENDING` with `total_rows`, `success_rows`, and `error_rows` set to zero.
Optional source filename/hash are retained; duplicate hashes are not treated as
idempotent success. Audit fields are always overwritten from trusted
`OperationContext`, so caller-supplied audit values cannot spoof the actor or
timestamp. Repository lookup returns `null` for a missing row and propagates a
typed `PersistenceException` for database failures.

No parser, header/null validation, Product mutation, Auto Number, import
authorization, row reconciliation, API, UI, or schema changes were made.

## Architecture boundary

```text
App\Modules\Product\Import\ProductImportJobService
    -> App\Infrastructure\Import\Persistence\ImportJobRepository
App\Infrastructure\Import
    -X-> Product module
```

The compiled module map now records `App\Infrastructure\Import` as the shared
Gate 6 technical root for IMP-001..008 and `App\Modules\Product\Import` as
Product-specific ownership for IMP-001..090. No `App\Modules\Import` module
was created.

## Validation

| Check | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Modules/Product/Import/P1Imp001ProductImportJobServiceTest.php --no-coverage` | PASS; 5 tests, 38 assertions |
| `vendor/bin/phpunit tests/database/P1Db080ImportTest.php --no-coverage` | PASS; 2 tests, 112 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS; 303 tests, 3,086 assertions |
| `vendor/bin/phpstan analyse --configuration phpstan.neon --level=8 --no-progress` | PASS; no errors |
| `validate-context-cache.php` | PASS; authority cache valid, 100 tasks |
| `architecture-guard.php` | PASS; 27 application files checked |
| `validate-ledger.php` | PASS before this task row update |
| scoped `git diff --check` | PASS |

## Scope impact

- DB: consumes existing `import_jobs`; no migration/schema change.
- Authorization: no authorization behavior added; later IMP task owns two-gate enforcement.
- API/OpenAPI: unchanged.
- UI/i18n: unchanged.
- Next dependency: P1-IMP-002; it was not started.
