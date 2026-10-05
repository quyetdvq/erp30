# P1-DB-050 Evidence: Fresh, Forward, and Schema Checks

Date: 2026-10-05
Repository: `D:\\XAMPP\\htdocs\\erp`
Accepted HEAD: `47337c8`
Database under test: `erp_test` only
Development database: `erp` was not reset, migrated, or written

## Fresh rehearsal #1 and #2

Command:

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes`

The isolation guard accepted only `erp_test`. Both consecutive fresh cycles
applied package migrations, P1-DB-010, P1-DB-020, P1-DB-030, P1-DB-040, and
P1-DB-050 in order and exited `0`.

```text
Fresh rehearsal #1: PASS
Fresh rehearsal #2: PASS
P1-DB-050 migration: 2026-10-05-000005_CreateResourceAuthorizationNumberingTables
```

## Forward 040 -> 050

To create a genuine completed P1-DB-040 state, the P1-DB-050 migration file
was temporarily moved outside discovery and the isolated fresh harness was
run. Before applying P1-DB-050, one sentinel row was inserted in each prior
table:

```text
before_provinces=1
before_wards=1
before_organizations=1
before_contacts=1
before_departments=1
before_positions=1
before_employees=1
before_employee_assignments=1
before_lifecycles=1
before_workflow_states=1
before_lifecycle_states=1
before_lifecycle_transitions=1
```

The migration file was restored and the following non-reset command was run
with the tests connection selected:

`D:\\XAMPP\\php\\php.exe spark migrate --all -g tests`

```text
Thực hiện: (App) 2026-10-05-000005_App\\Database\\Migrations\\CreateResourceAuthorizationNumberingTables
Di trú đã hoàn thành.
Exit code: 0
```

Post-forward counts:

```text
after_provinces=1
after_wards=1
after_organizations=1
after_contacts=1
after_departments=1
after_positions=1
after_employees=1
after_employee_assignments=1
after_lifecycles=1
after_workflow_states=1
after_lifecycle_states=1
after_lifecycle_transitions=1
after_resources=0
after_resource_actions=0
after_auto_numbering_series=0
after_auto_numbering_counters=0
after_groups=0
after_group_permissions=0
after_policies=0
after_policy_actions=0
after_authorization_logs=0
migration_050_rows=1
```

Forward migration: PASS. Previous schema/data preservation: PASS.

## Development database isolation

The development sentinel query returned:

```text
erp_migration_050_rows=0
erp_resources=absent
erp_resource_actions=absent
erp_auto_numbering_series=absent
erp_auto_numbering_counters=absent
erp_groups=absent
erp_group_permissions=absent
erp_policies=absent
erp_policy_actions=absent
erp_authorization_logs=absent
```

Development database isolation: PASS.

## Schema and integrity coverage

Focused PHPUnit verifies all nine table schemas, exact primary/unique/
composite/index definitions, required FK targets, duplicate-key rejection,
nullable policy state, polymorphic participant reference without FK, logical
Shield references without FK, and forbidden persistence absence.

```text
Database PHPUnit: PASS (23 tests, 1079 assertions)
Full PHPUnit:     PASS (31 tests, 1095 assertions)
PHPStan level 8:  PASS (no errors)
PHP syntax:       PASS
git diff --check: PASS
```

No seed data, catalog rows, triggers, local identity tables, separate
permissions table, Resource Instance Authorization, Effective Profile, KQ/
lane persistence, or numbering configuration rows were created.
