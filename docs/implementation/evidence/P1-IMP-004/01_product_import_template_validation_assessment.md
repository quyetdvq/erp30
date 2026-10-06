# P1-IMP-004 Product Import Template and Null Validation

Status: DONE

## Outcome

Implemented structural validation over both generic CSV and XLSX reader
documents. The shared reader result is now format-neutral:
`App\Infrastructure\Import\Reader\ImportDocument` and `ImportRow` replace
the previously CSV-named result classes.

`ImportHeaderNormalizer` applies only `trim` followed by lowercase. The
Product validator owns the canonical union of PRODUCT, UOM_CONVERSION, and
COMBO_ITEM headers, rejects missing/empty/unknown/duplicate normalized headers,
and validates the structural record types and CREATE_ONLY, UPDATE_ONLY, and
UPSERT modes with stable machine error codes.

Validated rows retain original row numbers and map every raw cell to an
explicit `VALUE`, `BLANK`, or `EXPLICIT_NULL` state. `__NULL__` is never
collapsed into blank. Cell helpers preserve CREATE blank-to-NULL and
UPDATE/UPSERT-existing blank-to-NO_CHANGE semantics; supplied field policies
can reject explicit null for non-nullable fields.

No product existence lookup, role/business validation, source-key graph,
mutation, Auto Number, authorization, row persistence, API/UI, XLSX-specific
semantics, or schema change was added.

## Architecture

```text
App\Modules\Product\Import\ProductImportTemplateValidator
    -> App\Infrastructure\Import\Reader\ImportDocument
    -> App\Infrastructure\Import\Reader\ImportHeaderNormalizer
App\Infrastructure\Import\Reader
    -X-> App\Modules\Product
```

The module map marks P1-IMP-004 DONE under Product Import while retaining
`App\Infrastructure\Import` as the shared Gate 6 technical root. No generic
`App\Modules\Import` module exists.

## Validation

| Check | Result |
| --- | --- |
| Focused IMP-004 + reader tests | PASS; 22 tests, 100 assertions |
| PHPStan level 8 | PASS; no errors |
| IMP-001/002/003 regression plus IMP-004 | PASS; 27 tests, 138 assertions |
| Full PHPUnit via approved harness | PASS; 325 tests, 3,186 assertions |
| Context cache, architecture, and ledger guards | PASS; cache valid, 100 tasks, 64 DONE, architecture guard 27 files |
| Scoped `git diff --check` | PASS |

## Scope impact

- DB/schema/migrations: unchanged.
- Authorization/security: unchanged.
- API/OpenAPI: unchanged.
- UI/i18n: unchanged.
- Next dependency: P1-IMP-005; it was not started.
