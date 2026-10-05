---
task_id: "P1-DB-050"
title: "Resource/Auth/Auto Numbering Migrations"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-DB-050 Resource/Auth/Auto Numbering Migrations

## 1. Scope and authority

This task implements only the nine P1-DB-050 persistence tables. It does not
seed Resource/Action catalog rows, create numbering configuration rows, or
implement IAM/PBAC services, repositories, controllers, API, frontend, or
Auto Numbering services.

Authority consulted:

- `docs/architecture/02_P1_Database_Design_v1.10.md`, sections 11, 13, 14,
  18, the migration order, and the Auto Numbering/transaction rules;
- `docs/architecture/01_P1_Functional_Requirements_Specification_v1.7.md`,
  IAM/PBAC/NUM requirements;
- `docs/architecture/03_P1_Implementation_Contract_v1.5.md`, authorization,
  migration, and numbering conventions;
- `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, Gate 1 and
  P1-DB-001/P1-DB-050 dependency and acceptance sections;
- `AGENTS.md` and `.codex/skills/dinkerp-implementation` references.

P1-DB-050 depends on P1-DB-030 and P1-DB-040. Accepted HEAD was
`47337c8 feat(P1-DB-040): add workflow lifecycle migrations`; the worktree was
clean before this task. No authority conflict was found.

## 2. Starting state and safety boundary

- Test database: `erp_test`, host `127.0.0.1:3306`, prefix `dinktech_`.
- Development database `erp` was not reset, migrated, or written.
- The harness isolation guard confirmed `erp_test` before destructive fresh
  rehearsals.
- MariaDB target used by the harness is MariaDB 10.4.32.

## 3. Migration and schema

Created
`backend/app/Database/Migrations/2026-10-05-000005_CreateResourceAuthorizationNumberingTables.php`.
The forward-only migration creates, in dependency order:

1. `resources` with nullable `lifecycle_id` FK, unique `type`, and domain/
   lifecycle indexes;
2. `resource_actions` with `resource_id` FK and unique
   `(resource_id, code)`;
3. `auto_numbering_series` with exact DBD series columns, Resource FK, and
   unique `(resource_id, series_code)`;
4. `auto_numbering_counters` with series/organization FKs and unique
   `(numbering_series_id, scope_organization_id, period_key)`;
5. `groups` as the ERP Authorization Group bridge for Shield group codes,
   without a Shield physical FK;
6. `group_permissions` with composite PK `(group_id, resource_action_id)` and
   no organization scope column;
7. `policies` with organization/resource/state FKs and polymorphic,
   no-FK `participant_ref_id`;
8. `policy_actions` with unique `(policy_id, resource_action_id)` and
   canonical VARCHAR `effect`;
9. `authorization_logs` with exactly the DBD resource/state/matched-policy
   FKs and a logical, no-FK Shield `user_id`.

All tables use unsigned `BIGINT` technical IDs, InnoDB, exact DBD
columns/nullability/indexes, and logical Shield actor fields without physical
actor FKs. No seed data, trigger, ENUM/CHECK invention, catalog row, local
identity table, Resource Instance Authorization table, Effective Profile, KQ/
lane table, or runtime service was added. Active-default uniqueness,
same-Resource policy/action validation, participant validation, and
post-issuance series immutability remain service/transaction rules.

## 4. Focused database assertions

Created `backend/tests/database/P1Db050ResourceAuthorizationNumberingTest.php`.
Assertions cover:

- all nine tables, every DBD column type/nullability, primary keys, unique and
  ordinary indexes, and required FK targets;
- duplicate `resources.type`, duplicate
  `resource_actions(resource_id, code)`, duplicate
  `auto_numbering_series(resource_id, series_code)`, duplicate counter key,
  and duplicate group-permission key rejection;
- nullable `policies.state_id` and acceptance of a polymorphic
  `participant_ref_id` with no FK;
- logical Shield user/actor references with no physical FK;
- absence of forbidden `profiles`, `permissions`, Resource Instance
  Authorization, Effective Profile, and persisted authorization lane tables.

## 5. Fresh rehearsal

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes`
completed two consecutive clean cycles on `erp_test`. Each cycle applied
package migrations followed by P1-DB-010, P1-DB-020, P1-DB-030, P1-DB-040,
and P1-DB-050 in order with exit code `0`.

- Fresh rehearsal #1: PASS.
- Fresh rehearsal #2: PASS.

## 6. Forward rehearsal and preservation

P1-DB-050 was temporarily moved outside migration discovery. A fresh
`erp_test` state through P1-DB-040 was created, then one sentinel row was
inserted into each prior table: Province, Ward, Organization, Contact,
Department, Position, Employee, Assignment, Lifecycle, Workflow State,
Lifecycle State, and Lifecycle Transition. Before the forward migration every
sentinel table had count `1`.

The migration file was restored and this non-reset command was run with the
tests connection selected:

`D:\\XAMPP\\php\\php.exe spark migrate --all -g tests`

The command applied P1-DB-050 with exit code `0`. Afterward every prior table
still had count `1`, all nine new tables existed with count `0`, and the test
migration ledger contained one `2026-10-05-000005` row.

- P1-DB-040 -> P1-DB-050 forward migration: PASS.
- Prior schema/data preservation: PASS.
- Development database isolation: PASS; `erp` had no P1-DB-050 ledger row and
  none of the nine new tables.

Detailed command output and counts are in
`docs/implementation/evidence/P1-DB-050/01_fresh_forward_schema.md`.

## 7. Validation commands

Commands were run from `D:\\XAMPP\\htdocs\\erp\\backend` unless noted:

| Command | Result |
|---|---|
| `php -l app\\Database\\Migrations\\2026-10-05-000005_CreateResourceAuthorizationNumberingTables.php` | PASS |
| `php -l tests\\database\\P1Db050ResourceAuthorizationNumberingTest.php` | PASS |
| `php tools\\test-db-harness.php rebuild --yes` | PASS; two fresh rehearsals |
| `php tools\\test-db-harness.php db-tests` | PASS; 23 tests, 1079 assertions |
| `php vendor\\bin\\phpunit --no-coverage` | PASS; 31 tests, 1095 assertions |
| `php vendor\\bin\\phpstan analyse --no-progress` | PASS; no errors |
| `php tools\\test-db-harness.php inventory` | PASS; isolation guard `erp_test` |
| `git diff --check` | PASS |

## 8. Files changed

- `backend/app/Database/Migrations/2026-10-05-000005_CreateResourceAuthorizationNumberingTables.php`
- `backend/tests/database/P1Db050ResourceAuthorizationNumberingTest.php`
- `docs/implementation/assessments/16_P1_DB_050_Resource_Auth_Numbering_Migrations_v1.0.md`
- `docs/implementation/evidence/P1-DB-050/01_fresh_forward_schema.md`

No architecture baseline, prior migration, seeder, application layer, API,
frontend, or unrelated file was modified.

## 9. Git completion and verdict

The four files above belong exclusively to P1-DB-050 and are staged by
explicit path only. The completion commit subject is:

`feat(P1-DB-050): add resource auth and numbering migrations`

Task: P1-DB-050 - Resource/Auth/Auto Numbering Migrations
Status: DONE

Contract notes:

- DB: nine forward-only InnoDB tables matching DBD sections 11, 13, 14, and
  18.
- Authorization: persistence boundaries only; no PBAC evaluation, bypass, or
  service behavior added.
- Auto Numbering: series/counter persistence only; no allocation or business
  identifier service added.
- API/OpenAPI: no impact.
- UI/i18n: no impact.
- Remaining: P1-SEED-001/003/004, P1-DB-060, IAM/PBAC services, Auto Numbering
  services, API, and UI remain downstream and were not started.
