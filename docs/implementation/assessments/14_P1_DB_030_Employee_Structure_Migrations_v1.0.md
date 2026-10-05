---
task_id: "P1-DB-030"
title: "Employee Structure Migrations"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-DB-030 Employee Structure Migrations

## 1. Scope and authority

This task implements only the four employee-structure tables required by the
database baseline. It does not implement employee/assignment repositories,
services, controllers, API, frontend, seeders, GROUP bootstrap, or workflow
tables.

Authority consulted:

- `docs/architecture/02_P1_Database_Design_v1.10.md`, sections 9.1-9.4,
  Shield identity integration, migration order, and employee rules;
- `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, Gate 1 and
  P1-DB-030/P1-DB-040 dependency sections;
- `docs/architecture/03_P1_Implementation_Contract_v1.5.md` for migration and
  persistence conventions;
- `AGENTS.md` and `.codex/skills/dinkerp-implementation` references.

Dependency: P1-DB-020 was already accepted at `91cddf3`, so P1-DB-030 was
READY. No authority conflict was found.

## 2. Starting state and safety boundary

- Initial HEAD: `91cddf3 feat(P1-DB-020): add organization and contact migrations`.
- Initial worktree: clean except for the two P1-DB-030 files created by this task.
- Test database: `erp_test`, host `127.0.0.1:3306`, prefix `dinktech_`.
- Development database `erp` was not reset, migrated, or written.
- MariaDB client/server used by the harness is MariaDB 10.4.32.

## 3. Migration and schema

Created `backend/app/Database/Migrations/2026-10-05-000003_CreateEmployeeStructureTables.php`.
The migration is forward-only and creates/drops in dependency order:

1. `departments` with nullable self-parent FK;
2. `positions`;
3. `employees` with nullable unique logical Shield `user_id` and manager self-FK;
4. `employee_assignments` with employee, organization, nullable department and
   nullable position FKs.

All tables use unsigned `BIGINT` auto-increment technical IDs, the exact DBD
columns and nullability, InnoDB, audit actor fields without physical Shield
FKs, and required timestamps. Required unique keys and indexes are present:

- departments: `code`, `parent_id`, `status`;
- positions: `code`, `level`, `status`;
- employees: `user_id`, `employee_code`, `employee_name`,
  `manager_employee_id`, `status`;
- employee_assignments: `employee_id`, `organization_id`, `department_id`,
  `position_id`, `(employee_id, is_primary, effective_from, effective_to)`,
  `status`.

Service-owned rules such as no self/cycle manager, assignment date overlap,
active-target validation, primary-assignment uniqueness, and authorization are
intentionally not encoded as triggers/checks in this task.

## 4. Focused database assertions

Created `backend/tests/database/P1Db030EmployeeStructureTest.php`.
Assertions cover:

- all four table/column types and nullability;
- primary, unique, and ordinary indexes;
- all required physical FKs;
- absence of physical FKs for `user_id`, `created_by`, and `updated_by`;
- duplicate department/position/employee identifiers rejected;
- unknown department parent, manager, and assignment references rejected.

## 5. Fresh rehearsal

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes`
completed two consecutive clean rebuilds on `erp_test`. Both applied package
migrations followed by P1-DB-010, P1-DB-020, and P1-DB-030 in order with exit
code `0`.

- Fresh rehearsal #1: PASS.
- Fresh rehearsal #2: PASS.

## 6. Forward rehearsal and preservation

For the forward test, P1-DB-030 was temporarily moved outside migration
discovery, then `tools\\test-db-harness.php fresh --yes` created the state
through P1-DB-020. One Province, Ward, Organization, and Contact row was
inserted. The migration was restored and the process environment was explicitly
set to `database.defaultGroup=tests` before running:

`D:\\XAMPP\\php\\php.exe spark migrate --all -g tests`

The forward migration exited `0`. Before migration, each preserved table had
one row and the migration ledger had 6 rows. After migration, each preserved
table still had one row, all four new tables existed, and the ledger had 7
rows.

- P1-DB-020 -> P1-DB-030 forward migration: PASS.
- Prior data preservation: PASS.

Detailed evidence is in
`docs/implementation/evidence/P1-DB-030/01_fresh_forward_schema.md`.

## 7. Validation commands

Commands run from `D:\\XAMPP\\htdocs\\erp\\backend`:

| Command | Result |
|---|---|
| `D:\\XAMPP\\php\\php.exe -l app\\Database\\Migrations\\2026-10-05-000003_CreateEmployeeStructureTables.php` | PASS |
| `D:\\XAMPP\\php\\php.exe -l tests\\database\\P1Db030EmployeeStructureTest.php` | PASS |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` | PASS; two fresh rehearsals |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php db-tests` | PASS; 16 tests, 494 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpunit --no-coverage` | PASS; 24 tests, 510 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` | PASS; no errors |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php inventory` | PASS; isolation guard `erp_test` |
| `git diff --check` | PASS |

An initial focused test run exposed an incorrect test expectation for the
database primary-key index name (`PRIMARY` rather than `id`); the assertion was
corrected and the complete validation above was rerun. A later exploratory
post-forward run was mistakenly launched concurrently against the stateful
test database and was discarded; the database was rebuilt and all final tests
were run sequentially. Neither event changed the development database.

## 8. Files changed

- `backend/app/Database/Migrations/2026-10-05-000003_CreateEmployeeStructureTables.php`
- `backend/tests/database/P1Db030EmployeeStructureTest.php`
- `docs/implementation/assessments/14_P1_DB_030_Employee_Structure_Migrations_v1.0.md`
- `docs/implementation/evidence/P1-DB-030/01_fresh_forward_schema.md`

No architecture baseline, prior migration, seeder, application layer, API,
frontend, or unrelated file was modified.

## 9. Git completion and verdict

The four files above belong exclusively to P1-DB-030. They must be staged by
explicit path and committed as exactly one task checkpoint with subject:

`feat(P1-DB-030): add employee structure migrations`

The commit hash and final clean-worktree verification are reported with the
task completion result. P1-DB-040 remains the next dependency and is not
started in this task.

Task: P1-DB-030 - Employee Structure Migrations
Status: DONE

Contract notes:

- DB: four forward-only InnoDB tables matching DBD sections 9.1-9.4.
- Authorization: no authorization behavior or bypass added; Shield references
  remain logical.
- API/OpenAPI: no impact.
- UI/i18n: no impact.
- Remaining: service-layer lifecycle, manager-cycle, assignment-overlap, and
  authorization rules remain for later tasks.
