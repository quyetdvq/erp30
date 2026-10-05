---
task_id: "P1-SEED-001"
title: "Resource + Resource Action Seed"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-SEED-001 Resource + Resource Action Seed

## Scope and authority

This task seeds only the approved Resource/Action catalog. Authority is
`docs/architecture/03_P1_Implementation_Contract_v1.5.md` section 7.3,
`docs/architecture/02_P1_Database_Design_v1.10.md` section 21, the Gate 1
section of `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, and
`AGENTS.md`.

Accepted dependency: `fb15731 feat(P1-DB-050): add resource auth and numbering migrations`.

## Implementation

Created `backend/app/Database/Seeds/ResourceActionSeeder.php` with the exact
20-resource, 92-action P1 matrix. It is idempotent by `resources.type` and
`resource_actions(resource_id, code)`, and stores canonical permission identity
as `<resource.type>.<action.code>` through the related rows.

The seed includes `product.import` only; no other Resource has `import`.
`authorization_group.assign_member`, `authorization_group.assign_permission`,
`user.assign_permission`, and `resource.configure_numbering` are present, with
`configure_numbering` independent of `resource.edit`.

No User permissions, Shield memberships, group permission rows, System Admin,
numbering series, PBAC policies, employee/province/ward import actions, or
other catalog entries are created.

## Tests and idempotency

Created `backend/tests/database/P1Seed001ResourceActionSeedTest.php`.
The focused test runs the seeder twice, compares complete catalog snapshots,
asserts the exact 20/92 matrix, verifies scoped special actions, and checks
that groups, group permissions, policies, and numbering tables are unchanged.

`erp_test` was the only database used for repeatable seed validation. The
development database `erp` was not reset or seeded.

## Validation

| Check | Result |
|---|---|
| Seed/database PHPUnit | PASS |
| Full PHPUnit | PASS |
| PHPStan level 8 | PASS |
| `git diff --check` | PASS |
| Seeder run #1 | PASS; 20 resources, 92 actions |
| Seeder run #2 | PASS; stable 20 resources, 92 actions |

## Files changed

- `backend/app/Database/Seeds/ResourceActionSeeder.php`
- `backend/tests/database/P1Seed001ResourceActionSeedTest.php`
- `docs/implementation/assessments/17_P1_SEED_001_Resource_Action_Seed_v1.0.md`

Task: P1-SEED-001 - Resource + Resource Action seed
Status: DONE

Remaining downstream work: P1-SEED-002, P1-SEED-003, P1-SEED-004, and later
Gate 1 tasks. No workflow, privileged-group, GROUP-root, IAM/PBAC, API, or UI
behavior was implemented here.
