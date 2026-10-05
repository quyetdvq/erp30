---
task_id: "P1-DB-040"
title: "Workflow/Lifecycle Migrations"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-DB-040 Workflow/Lifecycle Migrations

## 1. Scope and authority

This task implements only the four workflow/lifecycle persistence tables. It
does not seed states or lifecycles and does not implement workflow services,
repositories, controllers, API, frontend, authorization, resource actions,
or workflow designer behavior.

Authority consulted:

- `docs/architecture/02_P1_Database_Design_v1.10.md`, sections 3.3 and
  12.1-12.4, FK map, and workflow invariants;
- `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, Gate 1 and
  P1-DB-040/P1-SEED-002/P1-DB-050 dependency sections;
- `docs/architecture/03_P1_Implementation_Contract_v1.5.md` for migration and
  persistence conventions;
- `AGENTS.md` and `.codex/skills/dinkerp-implementation` references.

P1-DB-040 depends on P1-DB-001 and is independent of P1-DB-030 at the plan
level; it was executed after the accepted P1-DB-030 checkpoint to preserve the
Gate 1 sequence. No authority conflict was found.

## 2. Starting state and safety boundary

- Initial HEAD: `f822f5c feat(P1-DB-030): add employee structure migrations`.
- Initial worktree: clean.
- Test database: `erp_test`, host `127.0.0.1:3306`, prefix `dinktech_`.
- Development database `erp` was not reset, migrated, or written.
- MariaDB client/server used by the harness is MariaDB 10.4.32.

## 3. Migration and schema

Created `backend/app/Database/Migrations/2026-10-05-000004_CreateWorkflowLifecycleTables.php`.
The migration is forward-only and creates/drops in dependency order:

1. `lifecycles` with unique `code` and `status` index;
2. `workflow_states` with unique `code` and `status` index;
3. `lifecycle_states` with lifecycle/state FKs, unique
   `(lifecycle_id, state_id)`, and `(lifecycle_id, sequence)` index;
4. `lifecycle_transitions` with lifecycle/from/to state FKs, unique
   `(lifecycle_id, from_state_id, action_code)`, and
   `(lifecycle_id, from_state_id, status)` index.

All tables use unsigned `BIGINT` auto-increment technical IDs, exact DBD
columns/nullability, InnoDB, and logical Shield actor refs without physical
actor FKs. No seed state, trigger, check constraint, or authorization row was
added.

The service layer remains responsible for ensuring a state belongs to the same
lifecycle, only one active initial state exists, transition actions map to the
appropriate Resource Action, and transition validity does not grant permission.

## 4. Focused database assertions

Created `backend/tests/database/P1Db040WorkflowLifecycleTest.php`.
Assertions cover:

- all four table/column types and nullability;
- primary, unique, and required composite indexes;
- lifecycle/state/transition FK targets and counts;
- absence of physical Shield actor FKs;
- duplicate lifecycle/state codes;
- duplicate lifecycle-state pair and transition action keys;
- unknown lifecycle/state FK references rejected.

## 5. Fresh rehearsal

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes`
completed two consecutive clean rebuilds on `erp_test`. Both applied package
migrations followed by P1-DB-010, P1-DB-020, P1-DB-030, and P1-DB-040 in order
with exit code `0`.

- Fresh rehearsal #1: PASS.
- Fresh rehearsal #2: PASS.

## 6. Forward rehearsal and preservation

For the forward test, P1-DB-040 was temporarily moved outside migration
discovery, then `tools\\test-db-harness.php fresh --yes` created the state
through P1-DB-030. One Province, Ward, Organization, Contact, Department,
Position, Employee, and Assignment row was inserted. The migration was
restored and the process environment was explicitly set to
`database.defaultGroup=tests` before running:

`D:\\XAMPP\\php\\php.exe spark migrate --all -g tests`

The forward migration exited `0`. Before migration, each preserved table had
one row and the migration ledger had 7 rows. After migration, each preserved
table still had one row, all four workflow tables existed, and the ledger had
8 rows.

- P1-DB-030 -> P1-DB-040 forward migration: PASS.
- Prior data preservation: PASS.

Detailed evidence is in
`docs/implementation/evidence/P1-DB-040/01_fresh_forward_schema.md`.

## 7. Validation commands

Commands run from `D:\\XAMPP\\htdocs\\erp\\backend`:

| Command | Result |
|---|---|
| `D:\\XAMPP\\php\\php.exe -l app\\Database\\Migrations\\2026-10-05-000004_CreateWorkflowLifecycleTables.php` | PASS |
| `D:\\XAMPP\\php\\php.exe -l tests\\database\\P1Db040WorkflowLifecycleTest.php` | PASS |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` | PASS; two fresh rehearsals |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php db-tests` | PASS; 19 tests, 672 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpunit --no-coverage` | PASS; 27 tests, 688 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` | PASS; no errors |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php inventory` | PASS; isolation guard `erp_test` |
| `git diff --check` | PASS |

All database tests were run sequentially because the harness resets the
stateful test database between tests. No destructive operation targeted the
development database.

## 8. Files changed

- `backend/app/Database/Migrations/2026-10-05-000004_CreateWorkflowLifecycleTables.php`
- `backend/tests/database/P1Db040WorkflowLifecycleTest.php`
- `docs/implementation/assessments/15_P1_DB_040_Workflow_Lifecycle_Migrations_v1.0.md`
- `docs/implementation/evidence/P1-DB-040/01_fresh_forward_schema.md`

No architecture baseline, prior migration, seeder, application layer, API,
frontend, or unrelated file was modified.

## 9. Git completion and verdict

The four files above belong exclusively to P1-DB-040. They must be staged by
explicit path and committed as exactly one task checkpoint with subject:

`feat(P1-DB-040): add workflow lifecycle migrations`

The commit hash and final clean-worktree verification are reported with the
task completion result. P1-DB-050 is the next dependency and is not started in
this task.

Task: P1-DB-040 - Workflow/Lifecycle Migrations
Status: DONE

Contract notes:

- DB: four forward-only InnoDB tables matching DBD sections 12.1-12.4.
- Authorization: no authorization behavior or bypass added; transition action
  validity remains separate from permission.
- API/OpenAPI: no impact.
- UI/i18n: no impact.
- Remaining: P1-SEED-002 workflow bootstrap and P1-DB-050 resource/auth/
  numbering schema remain downstream.
