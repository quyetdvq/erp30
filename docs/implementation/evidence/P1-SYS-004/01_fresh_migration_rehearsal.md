# P1-SYS-004 - Fresh Migration Rehearsal

Date: 2026-10-09
Task: `P1-SYS-004`
Outcome: **DONE**

## Preflight and authority

`php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-SYS-004`
returned `AUTHORITY_CACHE: VALID`, `CATALOG_AND_LEDGER_VALIDATORS: PASS`, and
live ledger `101/107 DONE`. The pinned HEAD was
`be0326954a2cd8f431bbb0d6536092b221dea634`; Gate 1 is `14/14 DONE` and is the
only dependency. The worktree was `main` with two pre-existing unrelated
Pricing modifications, preserved throughout:

- `backend/app/Modules/Pricing/PriceList/PriceListService.php`
- `backend/tests/Modules/Pricing/PriceList/P1Prc001PriceListServiceTest.php`

The bounded authority read was DBD v1.15 section 22 (dependency order),
Implementation Contract v1.10 section 19 (migration/bootstrap), and
Implementation Plan v1.10 section 17 (Gate 9/P1-SYS-004). The approved change
controls were checked from `compiled/change-controls.json` and the four
architecture overlays. Database-impacting controls are represented by the
Display Settings audit migration, Policy Scope Uniqueness migration, and the
Shield User reset-action migration. Permission-aware UI and Resource Actions
bulk representation add no physical schema to this rehearsal.

## Isolated environment

All destructive operations used the existing guarded repository harness:

```text
CI_ENVIRONMENT=testing
Config\Database::$tests.database = erp_test
host = 127.0.0.1:3306
prefix = dinktech_
MariaDB = 10.4.32-MariaDB
```

`tools/test-db-harness.php inventory` reported `Isolation guard: PASS` before
the first reset. A read-only comparison after the rehearsal showed `erp` still
at 16 migration rows (maximum `2026-10-08-000012`) while `erp_test` was at 17
rows (maximum `2026-10-08-000013`). Development data was not reset, migrated,
or seeded. P1-SYS-005 was not run.

## Fresh migration sequence

Canonical command:

```text
D:\XAMPP\php\php.exe tools\test-db-harness.php fresh --yes
```

The fresh run completed with exit code 0. The observed order was:

1. Shield `2020-12-28-223112` `create_auth_tables`
2. Settings `2021-07-04-041948` `CreateSettingsTable`
3. Settings `2021-11-14-143905` `AddContextColumn`
4. Settings `2026-07-20-181246` `ConvertSqlsrvValueColumn`
5. App `2026-10-05-000001` through `000008` (Foundation, Organization/Contact,
   Employee, Workflow, Resource/Auth/Numbering, Product/UOM/COMBO, Pricing,
   Import)
6. App `2026-10-05-000009` `AddProductGroupHierarchy`
7. App `2026-10-05-000010` `NormalizeResourceDomains`
8. App `2026-10-05-000011` `CreateConfigurationAuditEvents`
9. App `2026-10-08-000012` `EnforcePolicyScopeUniqueness`
10. App `2026-10-08-000013` `AddUserResetPasswordAction`

The final `migrate:status` run under the testing connection showed all 17 rows
in group `tests`, batch 1, with no pending migration or error. No migration
file was rewritten and no rollback/refresh was used outside the disposable
`erp_test` database.

## Seed/bootstrap repeatability

After a clean fresh migration, the following canonical operations were run in
order twice:

```text
D:\XAMPP\php\php.exe tools\test-db-harness.php seed --yes
php spark db:seed App\Database\Seeds\GroupRootSeeder
php spark db:seed App\Database\Seeds\WorkflowStateSeeder
php spark systemadmin:bootstrap
```

The `spark` commands were executed through the repository's existing test
bootstrap path, which defines `SUPPORTPATH` and sets `CI_ENVIRONMENT=testing`
and `database.defaultGroup=tests`. A direct standalone `spark db:seed` was
also attempted and failed before dispatch because this checkout's entrypoint
does not define `SUPPORTPATH`; no database mutation occurred in that failed
attempt. The harness-compatible canonical commands all exited successfully.

Final post-rerun assertions (unchanged after the second run):

| Invariant | Result |
|---|---:|
| GROUP roots (`type=GROUP`, `parent_id IS NULL`) | **PASS - 1** |
| Canonical root `GROUP_DINKTECH` | **PASS - 1** |
| Resource catalog | **PASS - 21** |
| Resource Action catalog | **PASS - 95** |
| Workflow State catalog | **PASS - 8** |
| `SYSTEM_ADMIN` groups | **PASS - 1** |
| `SYSTEM_ADMIN` permissions | **PASS - 93 approved actions** |
| Duplicate resources/actions/permissions after rerun | **PASS** |

The two catalog actions not assigned to the privileged matrix are the explicit
`display_setting.view` and `display_setting.edit` actions; this is the approved
matrix boundary, not an overgrant or seed failure.

## Schema and change-control checks

Direct read-only `information_schema` checks against `erp_test` passed:

| Check | Result |
|---|---|
| 40 canonical physical tables after fresh migration | PASS |
| Primary/unique constraints present | PASS - 71 table-scoped declarations |
| Foreign-key constraints present | PASS - 50 declarations |
| Secondary/index definitions present | PASS - 167 table/index declarations |
| Shield tables (`users`, identities, group/user and direct permissions) | PASS |
| Resource domains are non-null and only `SYSTEM`, `MASTER`, `ORGANIZATION` | PASS |
| `auto_numbering_series` and `auto_numbering_counters` plus unique identities | PASS |
| `import_jobs` and `import_job_rows` plus row uniqueness | PASS |
| `configuration_audit_events` and Organization FK | PASS |
| Policy `normalized_state_id` stored generated column | PASS |
| Policy `uq_policies_scope` unique key over normalized scope | PASS |

The approved migration regressions passed against the isolated database:

```text
php vendor/bin/phpunit --no-coverage \
  tests/database/ChangeAuthDomain001MigrationTest.php \
  tests/database/ChangePolicyScopeUniqueness001MigrationTest.php
PASS - 3 tests, 12 assertions
```

## Validation matrix

| Command | Result |
|---|---|
| `task-context.php P1-SYS-004` | PASS - authority cache valid, 101/107 ledger |
| `tools/test-db-harness.php inventory` | PASS - testing/`erp_test` guard |
| `tools/test-db-harness.php fresh --yes` | PASS - complete 17-migration sequence |
| `spark migrate:status` via testing bootstrap | PASS - 17 applied, no pending |
| Database PHPUnit profile | PASS - 49 tests, 1,694 assertions |
| Focused change-control migration tests | PASS - 3 tests, 12 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS - 440 tests, 3,907 assertions |
| `vendor/bin/phpstan analyse --level=8` | PASS - no errors |
| `architecture-guard.php` | PASS |
| `validate-context-cache.php` / `validate-ledger.php` | PASS |
| `git diff --check` | PASS (after evidence/ledger edit) |

No frontend, OpenAPI, Playwright, or SYS-005 forward-upgrade command was run;
those are outside this database-only rehearsal boundary.

## Final state and handoff

`erp_test` is left at the fresh schema plus the idempotent Resource/Action,
Workflow State, GROUP-root, and SYSTEM_ADMIN bootstrap data. The next
dependency is **P1-SYS-005 - Existing-schema forward migration rehearsal**;
this task does not claim that forward-upgrade evidence.
