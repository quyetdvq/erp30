# P1-PRD-004 Product Role Service Assessment

Task: `P1-PRD-004`
Status: DONE

## Canonical role service

`App\\Modules\\Product\\Product\\ProductRoleService` is the single read-only role authority. It derives exactly:

- `COMBO`: persisted `product_type = COMBO`, root only.
- `CHILD`: `STANDARD` with a non-null root parent.
- `PARENT`: `STANDARD` root with one or more valid children.
- `SIMPLE`: `STANDARD` root with no children.

No role, derived role, or full-name column was added. `ProductRepository` remains persistence-only; its only addition is the narrow `findParent` read primitive.

## Predicates and naming

All predicates delegate to one role evaluation. SIMPLE/CHILD/COMBO are selectable only while `status = ACTIVE`; PARENT is never selectable. Leaf transactionability is true only for SIMPLE/CHILD, and combo expansion is required only for COMBO. `getFullName()` resolves current parent data for CHILD and returns `Parent Name - Child Name`; root SIMPLE/PARENT/COMBO return `product_name`. No value is cached or persisted, so parent rename immediately changes future child full-name results without updating the child row.

## Fail-closed behavior

`PRODUCT_INVALID_ROLE` is raised for unknown product types, COMBO parent/children, self-parenting, missing/non-root parents, CHILDs with children, and invalid child structure. Missing required names also fail closed through the same canonical role error. Inactive products remain readable and retain their role; only selection is disabled.

## P1-PRD-003 integration

`ProductMasterService` now consumes `ProductRoleService::isParent()` for persisted merged-state role checks. Mutation-specific checks remain local where they require prospective state, conversion eligibility, history locks, transactions, or structural locking. The dependency direction is `ProductMasterService -> ProductRoleService -> ProductRepository`; ProductRoleService has no authorization, mutation, transaction, DomainLock, Pricing, UOM-conversion, or COMBO-composition dependency.

## Validation evidence

| Check | Result |
|---|---|
| P1-PRD-004 focused role tests | PASS: 4 tests, 29 assertions |
| P1-PRD-003 focused regression after integration | PASS: 6 tests, 44 assertions |
| Combined role + Product mutation suite | PASS: 10 tests, 73 assertions |
| P1-PRD-001/002, NUM-006/090, DB-060 | PASS: 33 tests, 558 assertions |
| Full backend PHPUnit `--no-coverage` | PASS: 258 tests, 2734 assertions |
| PHPStan level 8 scoped Product/tests | PASS |
| Context cache validator | PASS |
| Architecture guard | PASS |
| Ledger validator | PASS |
| `git diff --check` | PASS |
