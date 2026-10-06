# P1-CMB-003 ComboAllocationService assessment

## Outcome

`App\Modules\Pricing\ComboAllocation\ComboAllocationService` is implemented as a read-only domain service. It accepts directly selected COMBO roots, resolves each root selling price once through `PricingService`, passes one shared `EvaluationTime` to every resolution, and delegates structural flattening to `ComboExpansionService`.

## Allocation contract

- Root pricing uses the root active Base UOM and the canonical Company -> Group -> default pricing fallback.
- Flattened leaves are only SIMPLE/CHILD rows. Nested COMBO nodes are structural and are never priced by allocation.
- Weight is `leaf quantity * leaf products.price` from the leaf default Base-UOM product row. Leaf Company/Group Price Lists and alternate-UOM prices are not consulted.
- A zero-weight leaf is retained; total weight `<= 0` rejects without equal redistribution.
- Raw allocation uses BCMath-backed `Decimal` arithmetic at high division precision. Monetary amounts round to two decimals, sorted leaves receive the complete residual on the last leaf, and each root conserves its rounded total.
- Multiple roots aggregate by `product_id + base_uom_id`; quantity and allocated amount are summed first, then unit price is derived as amount divided by quantity.

## Boundary impact

No persistence, migration, authorization, locking, API/OpenAPI, UI/i18n, or schema changes were made. Pricing depends on Product public role reads only; Product has no Pricing dependency, and Pricing does not inject ProductRepository.

## Validation

- Focused: `P1Cmb003ComboAllocationServiceTest.php` | PASS | 6 tests, 18 assertions.
- Regressions: CMB-002 7/26; CMB-001 6/63; PRC-002 6/35; PRD-004 4/29; UOM-001 6/69; DB-060 2/293; DB-070 2/103 | PASS.
- Full database harness (`tools/test-db-harness.php full-tests`) | PASS | 294 tests, 3,018 assertions.
- PHPStan level 8 (`vendor/bin/phpstan analyse --level=8 app --no-progress`) | PASS.
- Scoped PHPStan for service/tests | PASS.
- Architecture/context/ledger guards | PASS. Scoped `git diff --check` for task files | PASS. Full-repository diff check reports only the pre-existing trailing whitespace in unrelated `docs/thamkhao (Không được sử dụng)/Tracuuu-Tonghop.md`, which remains untouched.
