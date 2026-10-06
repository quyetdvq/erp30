# P1-UOM-001 Product UOM Service Assessment

Status: DONE

## Scope and boundary

`ProductUomService` owns Product-specific alternate-UOM relationships and
direct alternate-to-base quantity conversion. `ProductUomConversionRepository`
contains persistence primitives only. The implementation uses the existing
`product_uom_conversions` schema and shared Product module conventions.

Pricing, COMBO expansion/allocation, Import, API/OpenAPI, and UI/i18n work are
outside this task and remain untouched.

## Behavior implemented

- Create, edit, activate, inactivate, historical lookup, active lookup, and
  deterministic Product conversion listing.
- SIMPLE and CHILD products are eligible; PARENT and COMBO products are
  rejected. Base UOM is validated through the active shared UOM master; no
  base-UOM conversion row is persisted.
- Conversion factors, prices, usage types, statuses, and package dimensions
  use strict decimal/domain validation. Conversion uses the shared Decimal
  implementation and direct alternate-to-base multiplication.
- `product.edit` is required at GROUP evaluation scope. Client context and
  caller audit fields cannot override authoritative authorization/audit data.
- Mutations use the shared `product-structure:{root_id}` MariaDB advisory lock
  and an atomic transaction. The focused race test proves child creation and
  conversion creation cannot both commit for one root.

## Validation evidence

- Focused UOM suite: PASS, 6 tests / 69 assertions.
- Dependent Product UOM master, Product Master, Product Role, and combo-schema
  regressions: PASS, 28 tests / 495 assertions combined.
- Mandatory authorization regression: PASS, 9 tests / 68 assertions.
- Full backend PHPUnit `--no-coverage`: PASS, 264 tests / 2,803 assertions.
- PHPStan level 8 application and changed-file analysis: PASS.
- Architecture guard, authority context/module-map validator, and
  `git diff --check`: PASS.

The persistent `erp_test` database was repaired only through the approved
test-db harness after an earlier concurrent-run migration corruption; no
repository migration was changed.

## Contract impact

No new schema, Resource/Action, public API, OpenAPI, frontend, Pricing, COMBO,
or Import contract was introduced. P1 progress advances to `54/100` and Gate 5
to `5/11`; downstream product-domain tasks remain not started.
