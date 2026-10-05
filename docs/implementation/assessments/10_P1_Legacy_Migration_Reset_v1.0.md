---
task_id: "LEGACY-P1-MIGRATION-RESET"
title: "One-Time Legacy P1 Migration/Database Reset"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# Legacy P1 Migration/Database Reset

## 1. User reset decision

The Product Owner explicitly authorized a one-time reset of the obsolete local development/test migration lineage. The existing application migration files were created for an older document set and are not part of the current DinkERP P1 baseline.

This operation is local development/test cleanup only. It is not a production forward-migration operation. No new P1 migrations will be created in this run, and no migration will be replaced with a no-op.

## 2. Safety checks before deletion

- Effective CodeIgniter environment: `development` (`php spark env`).
- Database client/server: XAMPP MariaDB 10.4.32-MariaDB on `127.0.0.1:3306`; server hostname reported as `MRDINH`.
- Development DB configured by `backend/.env`: `erp`, host `localhost`.
- Test DB configured by `Config\Database::$tests`: `erp_test`, host `127.0.0.1`.
- Both target schemas use the `dinktech_` prefix and contain only ERP/CodeIgniter tables.
- No production/staging/remote database connection string was found in repository configuration. The remote-looking `app.baseURL` is an HTTP URL and is not a database connection.
- Other MariaDB schemas (`ci4`, `ci47`, `ci474`, `ci47x`, `kho`, `phpmyadmin`) were excluded and will not be touched.

Exact databases approved for reset, printed before execution:

```text
erp
erp_test
```

## 3. State recorded before reset

Timestamp: 2026-10-05 (local Asia/Saigon execution; MariaDB time values are Unix timestamps).

Migration files present before deletion:

```text
2026-09-28-000001_CreateProvincesTable.php
2026-09-28-000002_CreateWardsTable.php
2026-09-28-000003_CreateContactsTable.php
2026-09-28-000004_CreateOrganizationsTable.php
2026-09-28-000005_CreateDepartmentsTable.php
2026-09-28-000006_CreatePositionsTable.php
2026-09-28-000007_CreateEmployeesTable.php
2026-09-28-000008_CreateEmployeeAssignmentsTable.php
2026-09-28-000009_CreateLifecyclesTable.php
2026-09-28-000010_CreateWorkflowStatesTable.php
2026-09-28-000011_CreateLifecycleStatesTable.php
2026-09-28-000012_CreateResourcesTable.php
2026-09-28-000013_CreateResourceActionsTable.php
2026-09-28-000014_CreateRolesTable.php
2026-09-28-000015_CreateRolePermissionsTable.php
2026-09-28-000016_CreatePoliciesTable.php
2026-09-28-000017_CreatePolicyActionsTable.php
2026-09-28-000018_CreateAuthorizationLogsTable.php
2026-09-29-000019_CreateGroupsTable.php
2026-09-29-000020_CreateGroupPermissionsTable.php
2026-09-29-000022_EnsureShieldAuthTables.php
```

Git status for the migration directory before deletion: `000014` and `000015` were untracked working-tree files; the other listed application migrations were tracked and clean. `000021` was absent from the working tree.

Immediately before reset, `erp` had 27 tables and `erp_test` had 2 tables (`dinktech_factories`, `dinktech_migrations`). The preceding PHPUnit diagnostic had refreshed the test schema; this does not change the reset authorization or target scope.

The old `erp.dinktech_migrations` history contained package migrations plus application `000001`-`000022`, including `2026-09-29-000021 App\\Database\\Migrations\\MigrateLegacyRolesToGroups`. The old `erp_test.dinktech_migrations` history contained the PHPUnit support migration at the final snapshot. Earlier stale history containing application `000021` is documented in the prior P1-ENV-004 evidence.

Current pre-reset PHPUnit diagnostic:

```text
D:\XAMPP\php\php.exe vendor\\bin\\phpunit --no-coverage
OK (5 tests, 7 assertions)
```

The former migration-gap failure was caused by the discarded old lineage's applied `000021` row without a source file; it is not a reason to retain that lineage under this explicit reset decision.

## 4. Reset execution plan

Because both target databases are local and dedicated to this ERP checkout, the approved strategy is to drop and recreate exactly `erp` and `erp_test` with `utf8mb4`/`utf8mb4_unicode_ci`. No other schema will be dropped, and no migration history row will be manually inserted.

## 5. Other artifacts inspected (not deleted)

| Area | Observed artifacts | Classification |
|---|---|---|
| `backend/app/Database/Seeds/` | empty except `.gitkeep` | SAFE WITH NEW BASELINE |
| `backend/app/Models/` | empty except `.gitkeep` | SAFE WITH NEW BASELINE |
| `backend/app/Entities/` | no files | SAFE WITH NEW BASELINE |
| `backend/app/Repositories/` | directory absent | SAFE WITH NEW BASELINE |
| `backend/app/Services/` | directory absent | SAFE WITH NEW BASELINE |
| `backend/tests/` | CodeIgniter example model/seeder/migration and starter tests | NEEDS REVIEW; retained |

The PHPUnit example migration under `backend/tests/_support/` is test support, not an application migration, and is not deleted in this reset.

## 6. Shield / Settings handling

Shield and Settings tables are inside the same dedicated `erp` and `erp_test` schemas and will be removed together with the database reset. They will later be restored only through their canonical package/application migration mechanism. No replacement schema, privileged user, or authorization bypass will be created.

## 7. Post-reset update

The destructive reset completed successfully on 2026-10-05.

### Deleted migration files

All 21 application migration PHP files listed in section 3 were deleted. The directory remains present with only `.gitkeep`. No replacement migration, `000021` no-op, or restored legacy migration was created.

### Database objects removed

- Development database `erp`: dropped and recreated; final table count 0.
- Test database `erp_test`: dropped and recreated; final table count 0.
- Both schemas were recreated with `utf8mb4` and `utf8mb4_unicode_ci`.
- No other MariaDB schema was modified.

### Migration history reset

Both `erp.dinktech_migrations` and `erp_test.dinktech_migrations` were removed. Final read-only checks confirmed zero tables and zero migration-history tables in both target databases. The former active `2026-09-29-000021` history row no longer exists because the obsolete lineage was intentionally discarded.

### Shield / Settings state

Shield and Settings tables were removed with the two dedicated database resets. They will be restored later through their canonical package/application migration mechanism. No replacement tables or privileged users were created.

### Validation after reset

| Check | Result |
|---|---|
| `D:\XAMPP\php\php.exe spark env` before reset | PASS; environment `development` |
| local DB host/version query | PASS; `127.0.0.1:3306`, MariaDB `10.4.32` |
| migration directory contains PHP files | PASS; count `0`, `.gitkeep` retained |
| drop/recreate `erp` | PASS; exit code `0` |
| drop/recreate `erp_test` | PASS; exit code `0` |
| final `information_schema` table count for `erp` + `erp_test` | PASS; `0` |
| final migration-history table count | PASS; `0` |
| `D:\XAMPP\php\php.exe spark migrate:status` after reset | PASS; only package migration definitions shown as not migrated; this command temporarily created an empty history table, which was then removed and rechecked |
| one allowed PHPUnit diagnostic | PASS; `OK (5 tests, 7 assertions)`; result is diagnostic only, not migration readiness |

The test-support state created by the diagnostic was removed by recreating `erp_test`; the final databases are empty.

### Git working-tree result

Git records the tracked application migration files as deleted. The previously untracked `000014` and `000015` files were also deleted. The migration directory itself remains available for future current-baseline files. Unrelated existing worktree changes were preserved.

## 8. Recommended next task

`P1-ENV-004 - rebuild the test database/migration harness from the empty current baseline.` The next implementation must first define the approved current-baseline migration inventory and then establish the harness without restoring the discarded lineage.

## 9. Final reset report

Operation: Legacy P1 migration/database reset
Status: DONE

Safety:
- environment: `development`
- DB host: local XAMPP MariaDB, `127.0.0.1:3306`
- development DB: `erp`
- test DB: `erp_test`
- production DB touched: NO

Deleted migration files:
- all 21 PHP files under `backend/app/Database/Migrations/`, including the previously untracked `000014` and `000015`

Database reset:
- development database: PASS
- test database: PASS
- migration history cleared: PASS

Remaining migration PHP files:
- none; `.gitkeep` remains

Shield/Settings status:
- removed with the clean local database reset; canonical package/application migrations must restore them later

Potential obsolete artifacts requiring later review:
- `backend/tests/_support/Database/Migrations/2020-02-22-222222_example_migration.php`
- `backend/tests/_support/Database/Seeds/ExampleSeeder.php`
- `backend/tests/_support/Models/ExampleModel.php`
- starter tests under `backend/tests/`

Git status:
- tracked application migration files: deleted
- untracked restored `000014`/`000015`: deleted
- migration directory retained with `.gitkeep`

Validation commands:
- `D:\XAMPP\php\php.exe spark env`: PASS
- local MariaDB host/version query: PASS
- migration PHP file count: PASS (`0`)
- database drop/recreate SQL for `erp` and `erp_test`: PASS
- final `information_schema` empty/history check: PASS
- one post-reset PHPUnit diagnostic: PASS (`5` tests, `7` assertions); not a readiness claim

Recommended next task:
P1-ENV-004 - rebuild the test database/migration harness from the empty current baseline.
