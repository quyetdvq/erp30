# P1-SEED-003 Evidence: Privileged Authorization Group Bootstrap

Date: 2026-10-05
Repository: `D:\XAMPP\htdocs\erp`
Database: `erp_test` only

## Approved bootstrap

```text
Group code: SYSTEM_ADMIN
Group name: System Admin
Description: NULL
Status: ACTIVE
Approved permissions: 20 Resource Types / 92 Resource Actions
Shield membership: system_admin (provisioned by user ID through systemadmin:bootstrap)
```

## Repeated execution

```text
Seed run #1: PASS; 92 approved permissions
Seed run #2: PASS; identical snapshot; no duplicates
Future action: not auto-granted
Existing unrelated permission: preserved
Group display fields: converge to approved values
```

## Security and failure paths

```text
Missing approved resource/action: PASS; complete transaction rollback
CLI membership run #1: PASS
CLI membership run #2: PASS; one membership row
Direct user permission rows: 0
Employee/assignment/department/position rows: 0
Runtime System Admin bypass scan: PASS
```

No API/UI, migration, architecture baseline, policy, or business-data
bootstrap was added. The development database `erp` was not reset, migrated,
or seeded.
