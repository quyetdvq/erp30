# P1-CMB-002 ComboExpansionService Assessment

Status: DONE

## Service and boundary

`App\\Modules\\Product\\Combo\\ComboExpansionService` is a read-only
structural calculation service. It accepts a root COMBO Product ID and exact
root quantity, reads the existing `ComboCompositionRepository` edge snapshot,
and returns only aggregated leaf rows. It adds no mutation, authorization,
DomainLock, schema, API/OpenAPI, UI/i18n, pricing, import, or allocation path.

## Input and output

Input is `expand(rootComboProductId, rootQuantity)`. The root must exist, be a
canonical active selectable COMBO resolved by `ProductRoleService`, and have an
active canonical base UOM. The result is a deterministic list of:

`product_id`, `base_uom_id`, `quantity`

Only SIMPLE and CHILD leaves are returned. Nested COMBO nodes and all price,
currency, alternate-UOM, and allocation fields are excluded. Empty active
COMBO composition returns an empty list.

## Traversal and graph safety

The service loads direct composition rows once per operation, indexes them by
COMBO ID, and performs DFS with an active recursion-stack set. A global visited
set is deliberately not used, so shared subgraphs are counted once per path.
Self, reciprocal, deep, and persisted cycles fail with no partial result.
PARENT components, missing Products, impossible leaf compositions, invalid
roles, and malformed persisted quantities fail closed.

## Quantity and aggregation

All quantities remain decimal strings and use `Decimal`/BCMath. Multiplication
uses the sum of operand fractional scales and addition uses the maximum operand
scale, preserving exact finite decimal products beyond the persisted six-place
edge scale without premature rounding. Aggregation is keyed by
`product_id:base_uom_id`; output is sorted by `product_id ASC`, then
`base_uom_id ASC`.

The canonical example is covered: A x2 with X x1 and B x3, where B contains
X x2 and Y x4, yields X=14 and Y=24. A diamond/shared subgraph contributes
every independent path rather than being falsely rejected as a cycle.

## Product and UOM authority

Every role decision uses `ProductRoleService`. Active selectable semantics are
enforced for root, nested COMBO nodes, and final leaves. Final leaves and
nested structural nodes require a valid active base UOM through the existing
`ProductUomService` reference check; no alternate-UOM conversion is performed.
Nested COMBO prices and all Price List data are untouched.

## Validation evidence

- Focused P1-CMB-002: PASS, 7 tests / 26 assertions.
- P1-CMB-001 and independent concurrency regression: PASS, 6 tests / 65 assertions.
- P1-PRD-004 ProductRole: PASS, 4 tests / 29 assertions.
- P1-PRD-003 ProductMaster: PASS, 6 tests / 44 assertions.
- P1-UOM-001 Product UOM: PASS, 6 tests / 69 assertions.
- DB-060 Product/UOM/COMBO schema: PASS, 2 tests / 293 assertions.
- Full backend PHPUnit `--no-coverage`: PASS, 288 tests / 3,000 assertions.
- PHPStan level 8 application: PASS; architecture guard: PASS; context cache:
  PASS; ledger validator: PASS; scoped staged diff check: PASS.

The standalone scripts named module-map validator and decision validator are
not present in the repository; module-map coverage is included in the context
cache validator and decision-count coverage is included in the ledger validator.
The unrelated pre-existing `docs/thamkhao` edit was preserved and not staged.
