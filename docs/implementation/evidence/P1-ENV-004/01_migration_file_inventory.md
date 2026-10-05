# P1-ENV-004 Migration File Inventory

Date: 2026-10-05
Repository: `D:\XAMPP\htdocs\erp`

## Application migration location

`backend/app/Database/Migrations/` is the configured application migration directory (`Config\Database::$filesPath`). The current working tree contains these 21 application migration files:

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

## File-level checks

- Duplicate version prefixes: none detected.
- Ordering gap in the repository: `2026-09-29-000021` is absent between `000020` and `000022`.
- `000014` and `000015` are exact historical files restored from Git commit `8455d46`; their current blob hashes are `5d37a385270050c716a6c51ff433e5ccc14bf35f` and `2a2e9a0e39dbaab6a69899826c4a8b3e9f450c27`.
- No application seed file is present under `backend/app/Database/Seeds/`.

## Comparison result

| Set | Result |
|---|---|
| Applied in DB and present in current repo | Application `000001`-`000020`, `000022`; package migrations as applicable |
| Applied in DB but missing from current repo | Application `000021_MigrateLegacyRolesToGroups` in the pre-reset `erp` and `erp_test` histories |
| Present in repo but not applied after final fresh build | None among the current application files |
| Duplicate version/timestamp | None |
| Ordering gap | `000021` missing from the repository sequence |
