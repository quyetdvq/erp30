---
task_id: "P1-SEED-002"
title: "Workflow State Seed"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-SEED-002 Workflow State Seed

## Scope and authority

The requested seed scope is exactly these five canonical workflow state codes:

- `DRAFT`
- `PENDING_APPROVAL`
- `APPROVED`
- `REJECTED`
- `CANCELLED`

Authority consulted:

- `docs/architecture/02_P1_Database_Design_v1.10.md`, sections 12.2 and 21.1;
- `docs/architecture/01_P1_Functional_Requirements_Specification_v1.7.md`,
  FRS-P1-WF-001;
- `docs/architecture/03_P1_Implementation_Contract_v1.5.md`, workflow-state
  seeder idempotency convention;
- `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, Gate 1 and
  P1-SEED-002 dependency;
- `AGENTS.md` and the existing repository seed/migration conventions.

P1-SEED-001 completed and was committed at
`72c8d92 feat(P1-SEED-001): seed resource action catalog`. The worktree was
clean before this task boundary.

## Product Owner decision

The Product Owner resolved the previous blocker on 2026-10-05:

- `workflow_states.code` is the canonical machine/business identity;
- application logic, lifecycle resolution, transition checks, authorization,
  and API semantics use `code`, never `name`;
- `workflow_states.name` is display/business text only;
- the P1 baseline bootstrap uses `name = code`;
- later `name` changes do not change workflow behavior;
- no translation persistence is introduced.

Approved initial rows are `DRAFT`, `PENDING_APPROVAL`, `APPROVED`, `REJECTED`,
and `CANCELLED`, with each initial `name` equal to its `code`.

No `docs/implementation/decisions/` directory or established decision artifact
convention exists, so this assessment is the decision record. No UR/FRS/DBD or
Implementation Contract baseline was modified.

## Implementation

Created `backend/app/Database/Seeds/WorkflowStateSeeder.php`. It is
idempotent by `workflow_states.code`, inserts exactly the five approved rows
with `name = code` and `status = ACTIVE`, and preserves an existing display
`name` when rerun. It does not create additional states, lifecycles,
`lifecycle_states`, or `lifecycle_transitions`.

Created `backend/tests/database/P1Seed002WorkflowStateSeedTest.php`. The
regression tests prove initial `name = code`, exactly five rows, code-based
rerun identity, preservation of a later display-name change, and absence of
lifecycle/bootstrap rows.

## Validation

Validation was run against `erp_test` only. The seeder was executed twice and
the focused/database/full tests were run sequentially because the database is
stateful.

| Check | Result |
|---|---|
| Seeder run #1 | PASS; exactly 5 approved rows |
| Seeder run #2 | PASS; no duplicates, code catalog stable |
| Database PHPUnit | PASS |
| Full PHPUnit | PASS |
| PHPStan level 8 | PASS |
| `git diff --check` | PASS |

Development database `erp` was not reset or seeded.

Task: P1-SEED-002 - Workflow State seed
Status: DONE

Canonical identity: `code`
Display field: `name`
Bootstrap convention: `name = code`
