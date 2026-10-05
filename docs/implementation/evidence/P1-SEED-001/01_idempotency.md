# P1-SEED-001 Evidence: Exact Matrix and Idempotency

Date: 2026-10-05
Repository: `D:\\XAMPP\\htdocs\\erp`
Database: `erp_test` only

## Catalog

The approved matrix contains exactly 20 Resource Types and 92 Resource Actions.
The focused database test asserts every Resource/Action pair from Contract
v1.5 section 7.3.

Special action checks:

```text
product.import: PASS
non-product import actions: none
authorization_group.assign_member: PASS
authorization_group.assign_permission: PASS
user.assign_permission: PASS
resource.configure_numbering: PASS
resource.edit remains separate: PASS
```

## Repeated execution

`ResourceActionSeeder::run()` was executed twice against `erp_test`.

```text
Run #1: 20 resources, 92 actions
Run #2: 20 resources, 92 actions
Resource snapshot stable: PASS
No duplicate resources: PASS
No duplicate actions: PASS
Groups/group_permissions unchanged: PASS
Policies unchanged: PASS
Auto Numbering series/counters unchanged: PASS
```

The development database `erp` was not seeded.
