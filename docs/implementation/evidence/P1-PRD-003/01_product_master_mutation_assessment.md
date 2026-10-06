# P1-PRD-003 Product Master Mutation Assessment

Task: `P1-PRD-003`
Status: DONE

## Scope and module

Production code is under `App\\Modules\\Product\\Product`:

- `ProductRepository` owns Product persistence primitives, deterministic lookup, `FOR UPDATE`, child queries, dependency probes, audit stamping, and typed database failures.
- `ProductMasterService` owns Product create/edit/setstate invariants, GROUP authorization, transaction orchestration, and structural locking.
- `ProductHistoryProvider` is the replaceable transaction-history port. P1's `NoProductHistoryProvider` returns false because Sales/Purchase/Inventory history tables are not in P1.

No schema, API/OpenAPI, UI/i18n, ProductRoleService, UOM conversion, Pricing, COMBO composition, or Import code was added.

## Product mutation behavior

- Root `STANDARD` creates as a selectable SIMPLE-shaped root with required name, SKU, active Base UOM, non-negative price, and canonical naming fields.
- Root `COMBO` requires a root relation, active Base UOM, SKU, non-negative price, and `serial_management_enabled = false`.
- CHILD creates are `STANDARD` rows with a root STANDARD parent, `product_name = NULL`, required `product_child_name`, and maximum depth one.
- The first child is serialized by `product-structure:{root_product_id}`. The parent is re-read under the same transaction, conversion dependencies are checked, then parent price is cleared and serial management disabled before child insertion. Existing parents accept additional children without repeating first-conversion probes.
- Conversion probes cover transaction history, `product_price_list_items`, `product_uom_conversions`, and `product_combo_items.component_product_id`; no dependency is deleted.
- Existing-child reparenting and reverse PARENT-to-SIMPLE conversion remain intentionally unsupported and are reported as `PRODUCT_CHILD_REPARENT_UNSUPPORTED`.
- Edit validation uses the merged final state. Semantic fields use the history port; group and Base UOM selectors are reused only when a new reference is selected. Unchanged inactive historical references remain valid for unrelated edits.
- Setstate uses `product.setstate`, GROUP evaluation, trusted audit, row locking, and authorization audit.

## Numbering and atomicity

Product code maps to `products.code` through the existing `ProductIdentifierAdapter`/`AutoNumberService`; `product_code` is accepted only as the transport alias. Generated code, parent conversion, child insert, audit fields, and counter allocation run in one allocator transaction. Manual/no-series mutation uses the service transaction. A generated child failure regression proves parent, child, and counter rollback together.

## References, authorization, and audit

Create/edit/setstate authorize `product.create`, `product.edit`, and `product.setstate` respectively. `EvaluationOrganizationResolver` remains authoritative for GROUP; client hints cannot replace it and no SYSTEM_ADMIN bypass was added. Product Group creation references use `requireSelectableProductGroup`; new Base UOM references use `requireActiveProductUom`. Caller audit fields are ignored in favor of `OperationContext` actor/time.

## Validation evidence

| Check | Result |
|---|---|
| Product focused + real MariaDB same-root concurrency | PASS: 6 tests, 44 assertions |
| P1-PRD-001 and P1-PRD-002 regressions | PASS: 25 tests, 223 assertions |
| NUM-005/006/090 | PASS: 18 tests, 113 assertions |
| DB-060 | PASS: 2 tests, 293 assertions |
| AUTH-090 mandatory regression | PASS: 9 tests, 68 assertions |
| Full backend PHPUnit `--no-coverage` | PASS: 254 tests, 2705 assertions |
| PHPStan level 8 scoped Product/adapter | PASS |
| Context cache validator | PASS |
| Architecture guard | PASS |
| `git diff --check` | PASS |
