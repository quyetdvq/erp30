# P1-DB-030 Evidence: Fresh, Forward, and Schema Checks

Date: 2026-10-05
Repository: `D:\\XAMPP\\htdocs\\erp`
Database under test: `erp_test` only

## Fresh rehearsal #1 and #2

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes`
completed two consecutive clean cycles. Each cycle applied package migrations,
P1-DB-010, P1-DB-020, and P1-DB-030 in dependency order and exited `0`.

## Forward rehearsal 020 -> 030

The P1-DB-030 migration file was temporarily moved outside discovery, then:

1. `tools\\test-db-harness.php fresh --yes` created package + P1-DB-010 +
   P1-DB-020 state;
2. one Province, Ward, Organization, and Contact was inserted;
3. the P1-DB-030 file was restored;
4. process environment `database.defaultGroup=tests` was set;
5. `spark migrate --all -g tests` ran without reset.

Pre-forward verification:

```text
provinces_before       1
wards_before           1
organizations_before   1
contacts_before        1
migrations_before      6
```

Forward command result:

```text
Thực hiện: (App) 2026-10-05-000003_App\\Database\\Migrations\\CreateEmployeeStructureTables
Di trú đã hoàn thành.
```

Post-forward verification:

```text
provinces_after              1
wards_after                  1
organizations_after          1
contacts_after               1
employee_structure_tables    4
migrations_after             7
```

Forward migration: PASS. Prior data preservation: PASS.

## Physical schema and integrity boundary

`departments` has the DBD self-parent FK, unique `code`, and `parent_id` and
`status` indexes. `positions` has unique `code` and `level` and `status`
indexes. `employees` has nullable unique `user_id`, unique `employee_code`,
manager self-FK, and required lookup indexes. `employee_assignments` has the
four required domain FKs and the composite employee/primary/effective-date
index. Actor/user references have no physical Shield FK.

Focused PHPUnit checks all columns/nullability, primary/unique/ordinary
indexes, FK targets, actor-FK absence, duplicate identifier rejection, and
unknown FK target rejection. Service-owned cycle/overlap/status/authorization
rules are intentionally not claimed as database constraints.

## Validation result summary

```text
PHP syntax checks: PASS
Fresh #1/#2: PASS
Database PHPUnit: PASS (16 tests, 494 assertions)
Full PHPUnit: PASS (24 tests, 510 assertions)
PHPStan level 8: PASS (no errors)
Test database isolation inventory: PASS (erp_test)
git diff --check: PASS
```

An initial primary-index assertion failure was fixed from `id` to the actual
MariaDB index name `PRIMARY`. One concurrent exploratory PHPUnit run was
discarded after it raced on the stateful test database; the database was
rebuilt and the final validation was rerun sequentially.
