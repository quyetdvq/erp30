---
task_id: "P1-SEED-003"
title: "Privileged Authorization Group bootstrap"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-SEED-003 Privileged Authorization Group bootstrap

## Scope and authority

This task implements the Gate 1 privileged Authorization Group bootstrap only.
It does not create Employee, Assignment, Department, Position, Policy, direct
User permission, API, UI, or architecture-baseline changes.

Authority consulted:

- FRS-P1-IAM-003 and FRS-P1-PBAC-007;
- Implementation Contract v1.5 sections 7.3, 9, and 19;
- Implementation Plan v1.3 P1-SEED-003;
- P1-SEED-001 baseline evidence: exactly 20 Resource Types and 92 Resource Actions;
- AGENTS.md and the Gate 1 HIGH-risk `seed` validation profile.

## Product Owner decision

Approved bootstrap values:

- Authorization Group code: `SYSTEM_ADMIN`;
- name: `System Admin`;
- description: `NULL`;
- status: `ACTIVE`;
- permissions: exactly the approved 92 P1 Resource Actions from the P1-SEED-001
  baseline snapshot;
- membership: deployment/CLI provisioned by Shield user ID, membership only.

The group is a normal PBAC Base Permission source. It does not create a runtime
System Admin bypass, special policy, direct user permission, or business-data
identity. Future Resource Actions are not automatically granted. Existing
unrelated group permissions are preserved.

No `docs/implementation/decisions/` directory or separate decision artifact
convention exists, so this assessment records the approved implementation
decision without changing architecture baselines.

## Implementation

`PrivilegedAuthorizationGroupSeeder` ensures the canonical group and adds only
the explicit static 92-action matrix. It is idempotent, preserves unrelated
permissions, and fails when an approved resource/action is missing.

`systemadmin:bootstrap [userId]` is the deployment/CLI path. It validates an
existing Shield user ID, ensures the group bootstrap, and adds only the
lowercase Shield membership `system_admin`; it does not create employee,
assignment, department, position, policy, or direct user permission rows.

## Validation

Validation is run against `erp_test` only. Development database `erp` is not
reset, migrated, or seeded.

| Check | Result |
|---|---|
| Focused seed/bootstrap PHPUnit | PASS; 4 tests, 22 assertions |
| P1-SEED-001/002 regression plus P1-SEED-003 | PASS; 8 tests, 45 assertions |
| Full PHPUnit | PASS; 50 tests, 1668 assertions |
| PHPStan level 8 | PASS; 76 files |
| Exact approved matrix | PASS; 20 resource types, 92 actions |
| Idempotency and unrelated/future permission preservation | PASS |
| Missing approved action rollback | PASS |
| CLI membership idempotency | PASS; one `system_admin` membership row |
| Runtime bypass scan | PASS; no `isSystemAdmin`/`SYSTEM_ADMIN => ALLOW` path |
| `git diff --check` | PASS |
