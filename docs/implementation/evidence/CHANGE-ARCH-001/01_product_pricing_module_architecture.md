# CHANGE-ARCH-001 Product and Pricing Module Architecture

Status: DONE

## Before layout and inventory

The repository uses the application PSR-4 mapping `App\\` -> `backend/app/`.
Product-specific production code currently exists at:

- `backend/app/Repositories/ProductGroupRepository.php`
- `backend/app/Services/ProductGroupService.php`
- `backend/app/Services/ProductIdentifierAdapter.php`
- `backend/app/Services/ProductIdentifierUniqueViolation.php`

The Product Group tests are currently under `backend/tests/unit/` with the
reciprocal-reparent worker under `backend/tests/fixtures/`. Product Group
language files remain under `backend/app/Language/{en,vi}/`.

The identifier registry constructs `App\\Services\\ProductIdentifierAdapter`
when no explicit adapter list is supplied. P1-NUM-005/006/090 tests import the
flat Product adapter and one P1-PRD-001 test imports the flat Product Group
classes. No production `PriceListRepository`, `PriceListService`,
`PricingService`, Combo service, Product master, UOM master, or Product role
class currently exists to move.

No Product/Pricing module directories existed before this change. No controller,
route, migration, API/OpenAPI, frontend, or Composer module framework is part
of the current Product/Pricing implementation boundary.

## Scope contract

This change moves the completed P1-PRD-001 implementation and the concrete
Product identifier adapter into canonical module namespaces. It updates imports,
registry composition, test paths, architecture decision/contract evidence, and
the static architecture guard. It does not add business behavior, schema,
routes, API contracts, UI code, or future Product/Pricing placeholder classes.

## After layout

```text
backend/app/Modules/Product/ProductGroup/
  ProductGroupRepository.php
  ProductGroupService.php
backend/app/Modules/Product/Integration/Numbering/
  ProductIdentifierAdapter.php
  ProductIdentifierUniqueViolation.php
backend/tests/Modules/Product/ProductGroup/
  P1Prd001ProductGroupServiceTest.php
backend/tests/fixtures/Product/ProductGroup/
  product_group_concurrency_worker.php
backend/app/Language/{en,vi}/ProductGroup.php  (unchanged)
```

The application PSR-4 mapping remains `App\\` -> `backend/app/`; no Composer
package or module framework was added. The old flat production files are gone,
and all construction/import sites use the module namespaces.

## Ownership and dependency direction

| Area | Product module | Pricing module |
| --- | --- | --- |
| Product Group, UOM, Product, roles/ParentChild | Owns | Consumes public Product contracts only |
| Structural Combo composition/expansion | Owns | Consumes expanded Product result |
| Price List/resolution | No | Owns |
| Monetary Combo allocation | No | Owns |
| Product-specific numbering integration | Owns | No |

Canonical direction is `Foundation/shared -> Product -> Pricing`. Product has
no Pricing import. The guard rejects Pricing imports of concrete Product
repositories; no Pricing production classes existed to move in this change.
Import remains a separate boundary.

The compiled context sidecar `.codex/skills/dinkerp-implementation/compiled/module-map.json`
maps the requested future Gate 5 tasks to their canonical module paths. It is
emitted by `task-context.php` as additive context and does not alter task IDs,
dependencies, acceptance text, or the 100-task count.

## Verification

- Direct autoload from `backend`: three moved FQCNs resolved successfully.
- `tests/Modules/Product/ProductGroup/P1Prd001ProductGroupServiceTest.php`:
  PASS, 9 tests / 50 assertions, including real reciprocal-reparent
  concurrency and `product-group-tree` lock coverage.
- P1-NUM-005/006/090 regression command: PASS, 18 tests / 113 assertions.
- Full backend PHPUnit `--no-coverage`: PASS, 238 tests / 2532 assertions.
- PHPStan level 8 application and focused moved-file analyses: PASS.
- Architecture guard, context cache, ledger validation, and `git diff --check`:
  PASS.
- No migration, schema, API/OpenAPI, UI/i18n, or business-semantic changes.

## Control-plane impact

`CHANGE-ARCH-001` remains outside the 100 P1 tasks. P1 progress remains
`50/100`; Gate 5 remains `1/11`; P1-PRD-001 remains `DONE` at commit `0b4ed9b`.
