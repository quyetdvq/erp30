# P1-IMP-003 Generic XLSX Import Reader

Status: DONE

## Outcome

Implemented `App\Infrastructure\Import\Reader\XlsxImportReader` using the
existing PhpSpreadsheet dependency. The reader accepts an XLSX path or binary
document, selects the first worksheet containing nonempty data, returns its
first worksheet row as the generic header, and preserves actual worksheet row
numbers for data records. Results reuse `CsvImportDocument` and
`CsvImportRow`; XLSX cells retain literal scalar values (`string`, `int`,
`float`, `bool`, or `null`) without domain coercion.

Formula cells are rejected before value consumption with a typed
`XlsxParseException`, including formulas that contain a cached result. The
reader does not calculate formulas. Malformed, non-XLSX, unreadable, and
data-less workbooks fail with typed deterministic parser errors.

No Product semantics, template/header validation, mode/null semantics,
authorization, mutation, row persistence, dependency graph, or IMP-004 logic
was added. `P1-IMP-004` was not started.

## Architecture boundary

```text
App\Modules\Product\Import
    -> App\Infrastructure\Import\Reader\XlsxImportReader
App\Infrastructure\Import\Reader
    -X-> Product module
```

The module map marks `P1-IMP-003` as DONE under Product Import while retaining
the shared technical root `App\Infrastructure\Import` for Gate 6 reader work.

## Validation

| Check | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Infrastructure/Import/Reader/XlsxImportReaderTest.php --no-coverage` | PASS; 6 tests, 32 assertions |
| IMP-001/002 regression (`P1Imp001...` + `CsvImportReaderTest`) | PASS; 13 tests, 65 assertions |
| `tools/test-db-harness.php fresh --yes` | PASS; isolated `erp_test` rebuilt |
| `tools/test-db-harness.php full-tests` | PASS; 317 tests, 3,143 assertions |
| `vendor/bin/phpstan analyse --configuration phpstan.neon --level=8 --no-progress` | PASS; no errors |
| `validate-context-cache.php` | PASS; authority cache valid, 100 tasks |
| `architecture-guard.php` | PASS; 27 application files checked |
| `validate-ledger.php` | PASS before this task row update |
| scoped `git diff --check` | PASS |

Concurrent PHPUnit attempts were discarded because they raced on the shared
`erp_test` database. The reported regression and full-suite results are from
the subsequent serialized runs through the approved harness.

## Scope impact

- DB: no schema or migration changes.
- Authorization: unchanged.
- API/OpenAPI: unchanged.
- UI/i18n: unchanged.
- Next dependency: P1-IMP-004; it remains not started.
