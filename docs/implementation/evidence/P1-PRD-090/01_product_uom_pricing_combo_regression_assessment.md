# P1-PRD-090 Product/UOM/Pricing/COMBO regression assessment

## Outcome

The canonical Gate 5 cross-domain regression suite is complete in `P1Prd090ProductDomainRegressionTest.php`. It adds only integration journeys that were not proven together by the existing domain-focused suites; no production code changes were required.

## Coverage inventory

Existing Gate 5 suites were inventoried and rerun: Product Group `P1Prd001` (9/50), Product UOM master `P1Prd002` (10/129), Product master `P1Prd003` (6/44), Product roles `P1Prd004` (4/29), Product UOM `P1Uom001` (6/69), Price List `P1Prc001` (5/73), Pricing `P1Prc002` (6/35), COMBO composition `P1Cmb001` (6/65), COMBO expansion `P1Cmb002` (7/26), and COMBO allocation `P1Cmb003` (6/18). Their embedded MariaDB concurrency tests remain passing.

## New cross-domain scenarios

- SIMPLE + alternate UOM + company Price List item proves exact requested-UOM pricing through Product UOM validation and `PricingService`.
- A SIMPLE referenced by both UOM conversion and COMBO composition cannot become a PARENT; both rejection paths leave zero child rows and preserve the SIMPLE role.
- A nested COMBO with no default price is expanded and allocated successfully from a priced root; only SIMPLE leaves appear and the rounded allocation conserves the root amount.
- A Company list missing the requested UOM falls back to the Group exact item, then to the correct requested-UOM default. Two nested root COMBOs sharing one leaf aggregate quantity and amount first, yielding quantity `5`, amount `420000.00`, unit price `84000`.

## Required regressions

- NUM-006 Product identifier adapter: 7 tests / 30 assertions | PASS.
- Auto Numbering mandatory regression: 5 tests / 56 assertions | PASS.
- Authorization mandatory regression: 9 tests / 68 assertions | PASS.
- DB-060: 2 tests / 293 assertions | PASS.
- DB-070: 2 tests / 103 assertions | PASS.
- Full backend PHPUnit: 298 tests / 3,048 assertions | PASS.
- PHPStan level 8, architecture guard, authority context/module-map cache, decision/ledger validation, and scoped `git diff --check` are required final gates.

## Scope boundary

No schema, migration, API/OpenAPI, UI/i18n, import, authorization, or production behavior changes were made. `P1-IMP-001` and all Gate 6 work remain unstarted.
