# P1-IMP-002 Generic CSV Import Reader

Status: DONE

## Outcome

Implemented `App\Infrastructure\Import\Reader\CsvImportReader` with generic
`CsvImportDocument`, `CsvImportRow`, and `CsvParseException` value/error
objects. The reader accepts UTF-8 input, strips an initial UTF-8 BOM, uses a
comma delimiter and double-quote quoting, supports quoted commas, escaped
double quotes, CRLF/LF records, and quoted multiline fields, and preserves raw
cell strings. The first logical record is the header; data rows retain logical
source row numbers beginning at 2.

Malformed quote transitions, bare carriage returns, unterminated quoted
fields, empty input, and invalid UTF-8 fail deterministically with stable error
codes and byte/logical-row locations. No malformed-row recovery is attempted.

The reader has no Product, Product Import, template/header, mode/null,
authorization, mutation, row-persistence, or XLSX knowledge. `P1-IMP-003` was
not started.

## Architecture boundary

```text
App\Modules\Product\Import
    -> App\Infrastructure\Import\Reader\CsvImportReader
App\Infrastructure\Import\Reader
    -X-> Product module
```

The compiled module map records `App\Infrastructure\Import` as the shared
technical root for Gate 6 and marks `P1-IMP-002` Product ownership as DONE
under `App\Modules\Product\Import`.

## Validation

| Check | Result |
| --- | --- |
| `vendor/bin/phpunit tests/Infrastructure/Import/Reader/CsvImportReaderTest.php --no-coverage` | PASS; 8 tests, 27 assertions |
| `vendor/bin/phpunit tests/Modules/Product/Import/P1Imp001ProductImportJobServiceTest.php --no-coverage` | PASS; 5 tests, 38 assertions |
| `tools/test-db-harness.php fresh --yes` | PASS; isolated `erp_test` rebuilt |
| `tools/test-db-harness.php full-tests` | PASS; 311 tests, 3,113 assertions |
| `vendor/bin/phpstan analyse --configuration phpstan.neon --level=8 --no-progress` | PASS; no errors |
| `validate-context-cache.php` | PASS; authority cache valid, 100 tasks |
| `architecture-guard.php` | PASS; 27 application files checked |
| `validate-ledger.php` | PASS before this task row update |
| scoped `git diff --check` | PASS |

The first attempted parallel PHPUnit invocation was discarded because two
processes raced on the shared test database. The required regression was then
rerun serially through the approved harness and passed.

## Scope impact

- DB: no schema or migration changes.
- Authorization: unchanged.
- API/OpenAPI: unchanged.
- UI/i18n: unchanged.
- Next dependency: P1-IMP-003; it remains not started.
