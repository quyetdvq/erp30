# P1-DB-040 Evidence: Fresh, Forward, and Schema Checks

Date: 2026-10-05
Repository: `D:\\XAMPP\\htdocs\\erp`
Database under test: `erp_test` only

## Fresh rehearsal #1 and #2

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes`
completed two consecutive clean cycles. Each cycle applied package migrations,
P1-DB-010, P1-DB-020, P1-DB-030, and P1-DB-040 in dependency order and exited
`0`.

## Forward rehearsal 030 -> 040

The P1-DB-040 migration file was temporarily moved outside discovery, then:

1. `tools\\test-db-harness.php fresh --yes` created package + P1-DB-010 +
   P1-DB-020 + P1-DB-030 state;
2. one row was inserted into each prior domain table: Province, Ward,
   Organization, Contact, Department, Position, Employee, Assignment;
3. the P1-DB-040 file was restored;
4. process environment `database.defaultGroup=tests` was set;
5. `spark migrate --all -g tests` ran without reset.

Pre-forward verification:

```text
provinces_before       1
wards_before           1
organizations_before   1
contacts_before        1
departments_before     1
positions_before       1
employees_before       1
assignments_before     1
migrations_before      7
```

Forward command result:

```text
Thực hiện: (App) 2026-10-05-000004_App\\Database\\Migrations\\CreateWorkflowLifecycleTables
Di trú đã hoàn thành.
```

Post-forward verification:

```text
provinces_after              1
wards_after                  1
organizations_after          1
contacts_after               1
departments_after            1
positions_after              1
employees_after              1
assignments_after            1
workflow_tables              4
migrations_after             8
```

Forward migration: PASS. Prior data preservation: PASS.

## Physical schema and integrity boundary

`lifecycles` and `workflow_states` each have unique `code` and `status` index.
`lifecycle_states` has lifecycle/state FKs, unique pair
`(lifecycle_id, state_id)`, and `(lifecycle_id, sequence)` index.
`lifecycle_transitions` has lifecycle/from/to state FKs, unique
`(lifecycle_id, from_state_id, action_code)`, and
`(lifecycle_id, from_state_id, status)` index. Actor fields remain logical
Shield references without physical FKs.

Focused PHPUnit checks all columns/nullability, primary/unique/composite
indexes, FK targets, actor-FK absence, duplicate keys, and unknown FK target
rejection. Same-lifecycle validation, initial-state cardinality, and
authorization/action mapping remain service responsibilities.

## Validation result summary

```text
PHP syntax checks: PASS
Fresh #1/#2: PASS
Database PHPUnit: PASS (19 tests, 672 assertions)
Full PHPUnit: PASS (27 tests, 688 assertions)
PHPStan level 8: PASS (no errors)
Test database isolation inventory: PASS (erp_test)
git diff --check: PASS
```
