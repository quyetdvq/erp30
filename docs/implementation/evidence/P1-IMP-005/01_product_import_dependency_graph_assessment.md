# P1-IMP-005 Product Import Model and Source-Key Dependency Graph

Status: DONE

## Outcome

Implemented Product-owned read-only preflight planning under
`App\Modules\Product\Import`:

- `ProductImportModelBuilder` builds the complete validated-file model before
  any later mutation task.
- `ProductImportReference` distinguishes source-local keys from persistent
  Product-code references and retains resolved Product identity where an
  existing Product is read.
- `ProductImportDependencyGraph` produces deterministic topological order with
  original source row numbers preserved.
- `ProductImportPlan` and `ProductImportPlanRow` retain source order,
  processing order, mode, target path, supplied Product code, source key, and
  resolved references.

PRODUCT rows require unique `source_key`; supplied Product codes are unique.
Parent, UOM owner, and COMBO owner/component references resolve forward or
backward through source-local keys, or read existing Products by canonical
`ProductRepository::findByCode`. Product group and UOM codes resolve through
their existing Product module read boundaries. ProductRoleService is used for
existing Product role compatibility checks. UOM and COMBO duplicate identities
are checked after reference resolution, so mixed source-key/product-code forms
are treated consistently.

The graph supports child-before-parent, UOM-before-owner, COMBO-before-owner /
component, nested COMBO ordering, shared diamond dependencies, and arbitrary
depth. Direct and deep nested COMBO cycles fail deterministically.

No Product/UOM/COMBO mutation, Auto Number allocation, authorization,
`import_job_rows` persistence, API/UI, schema, or Infrastructure-to-Product
reverse dependency was added. `source_key` is never used as a Product business
identifier.

## Architecture

```text
App\Modules\Product\Import\ProductImportModelBuilder
    -> ProductRepository / ProductRoleService / ProductGroupService / ProductUomMasterService
    -> App\Infrastructure\Import\Reader result model
App\Infrastructure\Import
    -X-> App\Modules\Product
```

The module map marks P1-IMP-005 DONE under Product Import. No generic
`App\Modules\Import` module exists.

## Validation

| Check | Result |
| --- | --- |
| Focused IMP-005 tests | PASS; 8 tests, 28 assertions |
| IMP-004 + IMP-005 focused regression | PASS; 16 tests, 69 assertions |
| IMP-001..004 + Product/UOM/COMBO regression | PASS; 57 tests, 373 assertions |
| Full PHPUnit via approved harness | PASS; 333 tests, 3,214 assertions |
| PHPStan level 8 | PASS; no errors |
| Context cache, architecture, and ledger guards | PASS; cache valid, 100 tasks, 65 DONE, architecture guard 27 files |
| Scoped `git diff --check` | PASS |

## Scope impact

- DB/schema/migrations: unchanged.
- Authorization/security: unchanged.
- API/OpenAPI: unchanged.
- UI/i18n: unchanged.
- Next dependency: P1-IMP-006; it was not started.
