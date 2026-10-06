# P1-IMP-008 Product Import Result Reconciliation

## Outcome

Implemented generic `ImportJobRowRepository` under
`App\Infrastructure\Import\Persistence` and completed the Product-owned
import orchestration boundary under `App\Modules\Product\Import`.

The pipeline is:

`product.import` authorization -> PENDING job creation -> PROCESSING transition
-> CSV/XLSX reader -> Product template validation -> dependency plan -> row
mutation -> persisted row outcomes -> locked job reconciliation.

## Persistence and lifecycle

- Row persistence stores only `row_number`, `row_status`, `entity_key`,
  `target_ref_id`, `error_code`, `error_message`, and trusted timestamp data.
- Existing schema uniqueness enforces `import_job_id + row_number`; the
  repository also rejects deterministic duplicates before insert.
- Job reconciliation locks the `import_jobs` row with `FOR UPDATE` in the same
  transaction and derives counts exclusively from persisted rows.
- `SUCCESS` and `ERROR` counts are exact; `SKIPPED` contributes only to
  `total_rows`.
- No-error jobs become `COMPLETED`; jobs with error rows become
  `COMPLETED_WITH_ERRORS`; fatal pre-row failures become `FAILED`.
- Terminal jobs receive `completed_at`.

Generated Product identifiers are persisted as `entity_key`, and original
source row numbers remain stable even when dependency processing reorders rows.
No raw source payload is stored.

## Scope boundary

No schema, API/UI, new authorization semantics, or Product/UOM/COMBO mutation
rules were added. P1-IMP-090 was not started.

## Validation

- IMP-008 focused: PASS; 7 tests, 39 assertions.
- IMP-001..008 regression: PASS; 44 tests, 205 assertions.
- DB-080: PASS; 2 tests, 112 assertions.
- AUTH/Product/UOM/COMBO/NUM regressions: PASS.
- Full PHPUnit: PASS; 356 tests, 3,312 assertions.
- PHPStan level 8: PASS.
- Context cache, architecture, module-map, and ledger guards: PASS.
- Scoped `git diff --check`: PASS; unrelated `docs/thamkhao` change remains
  untouched.
