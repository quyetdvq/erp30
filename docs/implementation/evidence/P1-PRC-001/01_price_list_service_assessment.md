# P1-PRC-001 Price List Service Assessment

Status: DONE

## Scope and module boundary

Canonical Pricing configuration is implemented under
`App\\Modules\\Pricing\\PriceList`:

- `PriceListRepository`
- `ProductPriceListItemRepository`
- `PriceListService`

The service owns Price List headers, Product/UOM item configuration, inherited
authorization, trusted audit, and atomic replacement batches. No migration or
schema change was made. PricingService resolution is explicitly **not**
implemented in this task.

## Header behavior

- Price Lists accept only GROUP or COMPANY owner Organizations; BRANCH, PLANT,
  and WAREHOUSE scopes are rejected through the existing OrganizationService
  read boundary.
- Create uses requested `organization_id` as authoritative Evaluation
  Organization. Existing edit/setstate/item operations use the persisted
  Price List owner. Client evaluation hints cannot override either context.
- Code, name, timestamps, nullable end time, signed integer priority, status,
  and description are validated using canonical string/timestamp rules.
- Effective intervals use `effective_from <= evaluation_time < effective_to`
  semantics for future consumers; configuration permits overlapping lists.
- Edit uses merged-state validation, setstate is separate, inactive lists remain
  readable, and no Price List hard-delete operation exists.

## Item behavior

- Item authorization inherits `price_list.view` for reads and `price_list.edit`
  for mutation; `price_list_item.*` and Pricing-specific bypasses were not added.
- ProductRoleService is the role authority. SIMPLE, CHILD, and COMBO are
  supported; PARENT and inactive Products are rejected for new/changed items.
- ProductUomService validates SIMPLE/CHILD base or active SALES/BOTH alternate
  UOMs. COMBO accepts base UOM only. Product/UOM conversion rules are not
  duplicated in Pricing.
- Prices are required non-negative DECIMAL(18,2) strings with no binary float
  or silent precision rounding. `(price_list_id, product_id, uom_id)` remains
  DB-enforced unique.
- `replaceItems()` validates the complete submitted set, locks the parent Price
  List row `FOR UPDATE`, and inserts/updates/removes items atomically. Empty
  submitted sets are valid. Unchanged historical rows remain readable after
  Product/UOM inactivation; changing their configuration revalidates references.

## Validation evidence

- Focused P1-PRC-001: PASS, 5 tests / 73 assertions, including real independent
  MariaDB batch concurrency.
- P1-PRD-003/004 and P1-UOM-001 regressions: PASS, 16 tests / 142 assertions.
- DB-070 and DB-060 regressions: PASS, 4 tests / 396 assertions.
- Mandatory authorization regression: PASS, 9 tests / 68 assertions.
- Full backend PHPUnit `--no-coverage`: PASS after final tree.
- PHPStan level 8 application and focused Pricing/Product analysis: PASS.
- Architecture guard, authority context/module-map validator, ledger validator,
  and `git diff --check`: PASS.

No API/OpenAPI, UI/i18n, Import, COMBO expansion, or Product-to-Pricing reverse
dependency was introduced. P1 progress advances to `55/100` and Gate 5 to
`6/11`; P1-PRC-002 remains not started.
