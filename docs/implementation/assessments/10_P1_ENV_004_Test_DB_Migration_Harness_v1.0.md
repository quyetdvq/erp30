---
task_id: "P1-ENV-004"
title: "Test Database and Migration Harness"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-ENV-004 Test Database and Migration Harness

## 1. Scope

This task establishes and verifies an isolated CodeIgniter/MariaDB test database workflow only. It does not implement P1 business functionality, Slice 1, new schema, seeds, API behavior, authorization behavior, or unrelated PHPStan fixes.

Authority used: `AGENTS.md`, `docs/implementation/assessments/08_P1_Repository_Runtime_Assessment_v1.0.md`, `docs/implementation/assessments/09_P1_Gate0_Readiness_Assessment_v1.0.md`, Implementation Plan v1.3 sections 5.3, 7.1, 8.1, and 24, DBD v1.10 migration/transaction invariants, and Implementation Contract v1.5 section 19.

## 2. Database safety controls

The new `backend/tools/test-db-harness.php` reads the existing `Config\Database::$tests` group. It refuses all mutating actions unless the configured database name is exactly `erp_test`. Destructive actions additionally require an explicit `--yes` flag. Reset drops tables/views only inside that approved database with foreign-key checks temporarily disabled, then restores the setting.

The harness keeps credentials in local runtime configuration; no credentials are committed. Migration child processes receive `database.defaultGroup=tests`, so the CodeIgniter connection is the `tests` group. This is explicit because `spark -g tests` alone is a migration group/history selector and does not replace the runner's default connection.

An intentional negative test set `database.tests.database=erp`; the harness refused the reset with exit code 1 and did not run a database command. A development sentinel before and after all runs confirmed that `erp` remained at 26 migration rows and version `2026-09-29-000022`.

## 3. Actual migration state

The application migration directory contains 21 files: `000001`-`000020` and `000022`. There are no duplicate version prefixes. Both `erp` and the pre-reset `erp_test` migration histories contained an applied row for `2026-09-29-000021` with class `App\\Database\\Migrations\\MigrateLegacyRolesToGroups`, but no such file exists in the working tree.

The physical history table is `dinktech_migrations` because the configured migration table is `migrations` and both database groups use prefix `dinktech_`.

After the final fresh build, `erp_test` contains 25 migration rows: four Shield/Settings package migrations plus application `000001`-`000020` and `000022`. It contains 29 tables. The missing `000021` is not silently represented by a no-op row.

Detailed inventory and comparison: [01_migration_file_inventory.md](../evidence/P1-ENV-004/01_migration_file_inventory.md) and [02_migration_history_comparison.md](../evidence/P1-ENV-004/02_migration_history_comparison.md).

## 4. Missing migration investigation

The ordering gap is exactly `2026-09-29-000021` between `000020` and `000022`. This is not a new migration design problem that can be guessed in this task; it is an applied-history/source conflict. Existing database objects such as groups and group permissions do not prove the exact legacy-role transformation semantics.

The task therefore does not invent a replacement and does not rewrite any applied migration.

## 5. Git recovery result

Git history recovered exact `000014` and `000015` from commit `8455d461025fab7b8bafdb4da4ec74467b622243`; those files were preserved byte-for-byte. Commit `d82f6f886030b3388cf49f8e23e0b2a7dfd77927` deleted them. Git history search found no commit, deleted/renamed path, or content for `000021_MigrateLegacyRolesToGroups`.

No SQL dump, prior schema snapshot, or migration fixture exists in the repository. Full search evidence is in [03_git_recovery.md](../evidence/P1-ENV-004/03_git_recovery.md).

## 6. Test database configuration

The existing PHPUnit convention is `Config\Database::$tests`:

| Setting | Actual value |
|---|---|
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `erp_test` |
| Driver | MySQLi |
| Prefix | `dinktech_` |
| Credentials | local configuration; not committed |
| MariaDB | 10.4.32-MariaDB |

Development remains `erp`, and was not reset.

## 7. Fresh migration procedure

From `backend`:

```text
D:\XAMPP\php\php.exe tools\test-db-harness.php prepare
D:\XAMPP\php\php.exe tools\test-db-harness.php fresh --yes
D:\XAMPP\php\php.exe tools\test-db-harness.php rebuild --yes
```

The harness resets only the approved test database, then runs `spark migrate --all -g tests` with `database.defaultGroup=tests`. Fresh migration #1 and both rebuild passes completed successfully for the current source inventory. This does not make the missing historical migration recoverable.

## 8. Forward migration procedure

The intended procedure would build a committed prior schema fixture in `erp_test`, then run only later migrations and compare the resulting history/schema. No reproducible prior fixture exists. The stale pre-reset database cannot serve as that fixture because its history references the missing `000021` source. Forward migration is therefore `NOT RUN / BLOCKED`.

## 9. Commands and evidence

The harness syntax check, isolation negative test, fresh runs, PHPUnit runs, and development sentinel are recorded in [04_commands_and_results.md](../evidence/P1-ENV-004/04_commands_and_results.md). Fresh/forward conclusions are recorded in [05_fresh_forward_results.md](../evidence/P1-ENV-004/05_fresh_forward_results.md).

Executed results:

- harness syntax: PASS;
- test DB isolation guard: PASS;
- fresh migration #1: PASS;
- fresh migration #2/rebuild: PASS;
- database PHPUnit: PASS, 2 tests / 3 assertions;
- full PHPUnit: PASS, 5 tests / 7 assertions;
- development DB unchanged: PASS;
- forward migration from prior state: NOT RUN / BLOCKED.

## 10. Remaining blockers

1. `2026-09-29-000021_MigrateLegacyRolesToGroups.php` is recorded as applied in both existing databases but cannot be recovered from Git or the repository.
2. No approved decision exists to recover its exact content, provide an authoritative snapshot, or disposition the applied history under forward-only migration rules.
3. No reproducible prior-schema fixture exists, so the required forward-migration rehearsal cannot be executed honestly.

The smallest decision needed is an authoritative recovery/disposition for `000021` and a reproducible prior-state artifact if forward migration acceptance is required.

## 11. Historical task verdict before reset (superseded)

The isolated test DB harness was implemented and safe, but the pre-reset task was blocked by the then-applied migration/source conflict and unavailable forward fixture. This historical verdict is superseded by section 12 after the authorized reset.

Task: P1-ENV-004 (historical pre-reset)
Status: BLOCKED (superseded)

Files changed:
- `backend/tools/test-db-harness.php`
- `docs/implementation/evidence/P1-ENV-004/01_migration_file_inventory.md`
- `docs/implementation/evidence/P1-ENV-004/02_migration_history_comparison.md`
- `docs/implementation/evidence/P1-ENV-004/03_git_recovery.md`
- `docs/implementation/evidence/P1-ENV-004/04_commands_and_results.md`
- `docs/implementation/evidence/P1-ENV-004/05_fresh_forward_results.md`
- `docs/implementation/assessments/10_P1_ENV_004_Test_DB_Migration_Harness_v1.0.md`

Recovered historical migrations:
- `backend/app/Database/Migrations/2026-09-28-000014_CreateRolesTable.php` — commit `8455d461025fab7b8bafdb4da4ec74467b622243`
- `backend/app/Database/Migrations/2026-09-28-000015_CreateRolePermissionsTable.php` — commit `8455d461025fab7b8bafdb4da4ec74467b622243`
- `000021` — none; not recoverable from Git/repository

Validation:
- test DB isolation: PASS
- migration inventory: PASS
- fresh migration #1: PASS
- fresh migration #2: PASS
- forward migration: NOT RUN
- PHPUnit after harness: PASS

Blockers:
- Applied `2026-09-29-000021` has no recoverable source file; no guessed/no-op migration was created.
- No reproducible prior-schema fixture exists for forward migration.

## 12. Current reassessment after the authorized development reset

Reassessment timestamp: `2026-10-05 13:30:00 +07:00`.

The blocked conclusion in sections 3-11 describes the pre-reset legacy lineage and is retained as historical evidence. The Product Owner subsequently authorized a one-time local development/test reset. Both `erp` and `erp_test` were recreated without the obsolete application migration history, and `php spark shield:setup` restored the canonical Shield/Settings package migrations. No application migration was guessed, recreated as a no-op, or rewritten.

### Current source and database inventory

- `backend/app/Database/Migrations/` contains only `.gitkeep`; the discarded application lineage is not part of the current source baseline.
- The current approved test database is exactly `erp_test`, using the existing PHPUnit `tests` connection and `dinktech_` prefix.
- The current source inventory contains four canonical Shield/Settings package migrations. The final test inventory reports four migration rows and the isolation guard passes.
- Development `erp` remains a separate database; no test harness reset targets it.

### Current harness acceptance

| Check | Status | Current evidence |
|---|---|---|
| Approved database guard | PASS | `tools\\test-db-harness.php inventory`; database is exactly `erp_test` |
| Destructive-operation safety | PASS | mutating actions require `--yes` and reject non-`erp_test` configuration |
| Fresh rebuild repeatability | PASS | `tools\\test-db-harness.php rebuild --yes`; two consecutive fresh runs completed with exit code 0 |
| Database PHPUnit path | PASS | `tools\\test-db-harness.php db-tests`; 2 tests, 3 assertions |
| Full PHPUnit path | PASS | `vendor\\bin\\phpunit --no-coverage`; 10 tests, 19 assertions |
| PHPStan baseline after harness | PASS | level 8 reports no errors |
| Final test DB inventory | PASS | `inventory`; isolation guard PASS and 4 package migration rows |
| Development DB isolation | PASS | test harness never targets `erp`; development migration status remains separate |

### Forward-migration boundary

The current application migration baseline is intentionally empty after the authorized reset. Therefore there is no approved prior application schema from which a business forward migration can be rehearsed in this task. This is not resolved by inventing `000021` or a no-op migration. The harness itself is ready for forward execution as soon as `P1-DB-001` defines the approved current-baseline migration inventory; that Gate 1 task owns business fresh/forward schema verification.

This boundary does not block the P1-ENV-004 primary output, which is the isolated and repeatable test database workflow. It remains an explicit dependency for `P1-DB-001` and later Gate 1 migration acceptance.

### Current task verdict

Task: P1-ENV-004
Status: DONE

Files/evidence retained:
- `backend/tools/test-db-harness.php`
- `docs/implementation/evidence/P1-ENV-004/01_migration_file_inventory.md`
- `docs/implementation/evidence/P1-ENV-004/02_migration_history_comparison.md`
- `docs/implementation/evidence/P1-ENV-004/03_git_recovery.md`
- `docs/implementation/evidence/P1-ENV-004/04_commands_and_results.md`
- `docs/implementation/evidence/P1-ENV-004/05_fresh_forward_results.md`

Validation:
- test database isolation: PASS
- fresh/rebuild repeatability: PASS
- database PHPUnit: PASS
- full PHPUnit: PASS
- PHPStan level 8: PASS
- current application forward migration: NOT APPLICABLE until `P1-DB-001` defines the approved baseline

Remaining dependency:
- `P1-DB-001` must define the forward-only application migration inventory before business schema fresh/forward rehearsal. No legacy `000021` content is inferred here.
