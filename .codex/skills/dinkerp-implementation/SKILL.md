---
name: dinkerp-implementation
description: Implement, review, or plan DinkERP P1 work through bounded authority reading, gate registries, scoped validation, and isolated task commits.
---

# DinkERP Implementation Control Plane

Use this skill for one approved DinkERP task, vertical slice, review, or
planning outcome. `AGENTS.md` remains the governing repository instruction.

## Execution flow

1. **Task ID and gate**: identify the concrete Task ID/outcome and load only
   its registry at `references/tasks-gate<N>.md`.
2. **Bounded authority**: follow the registry's authority IDs/headings into the
   baseline documents. Read the smallest relevant ranges; expand only for a
   dependency, conflict, or debugging question.
3. **Repository inspection**: run `git status --short`, record pre-existing
   changes, and inspect current paths, commands, contracts, and tests before
   proposing edits.
4. **Task contract**: state scope/out-of-scope, dependencies and READY status,
   DB, authorization/context, transaction/concurrency, API/OpenAPI, UI/i18n,
   and test impact.
5. **Risk and validation**: classify LOW/MEDIUM/HIGH and select a profile from
   `references/validation-profiles.md`. HIGH-risk work is never batched by
   default.
6. **Implement**: make the smallest coherent change within the task boundary;
   preserve the authority order and project invariants.
7. **Scoped validation**: run the selected profile, plus the relevant review
   checklist. Do not run the full repository regression suite after every
   LOW/MEDIUM task.
8. **Independent checkpoint**: if DONE, run `git diff --check`, inspect the
   diff/status, stage only task files explicitly, and create exactly one commit
   containing the Task ID. Never stage unrelated work.
9. **Batch progression**: after successful task commits, run the `batch-full`
   profile. If it finds a regression, make a corrective commit; do not amend,
   rebase, or rewrite prior task commits.
10. **Compact report**: record outcome, files/behavior, critical contract
    checks, validation as `command/check | PASS | count/version`, commit,
    blocker, and next dependency. Keep normal assessments about 30–60 lines.

## Progressive loading

Never read all architecture documents by default. Given a Task ID, identify its
gate registry, locate exact authority sections, read bounded ranges, and expand
only when dependency closure, a source conflict, or debugging requires it. Do
not reread accepted assessment documents unless the current task depends on
their implementation or evidence state.

## Authority and safety

Use this order when sources differ: UR -> FRS -> DBD -> Implementation Contract
-> Implementation Plan -> API Design -> UI Design. Stop and report an
unresolved conflict instead of inventing semantics, schema, Resource/Action,
API behavior, or persistence. Read `project-invariants.md` for high-risk work
and `review-checklists.md` before finalizing.

Preserve PBAC/Evaluation Organization rules, atomic multi-table mutations,
DomainLock conventions, decimal arithmetic, Auto Number transaction semantics,
Product/COMBO/Pricing/Import boundaries, API serialization/error contracts,
and `vi`/`en` i18n rules. Migrations are forward-only.

## Required references

- `references/execution-protocol.md` — batching, stop conditions, and Git
  isolation.
- `references/validation-profiles.md` — scoped and batch validation.
- `references/tasks-gate0.md` through `references/tasks-gate9.md` — task
  navigation pointers only; authoritative requirements stay in `docs/`.
- `references/project-invariants.md` — compact guardrails.
- `references/review-checklists.md` — final self-review.

## Completion shape

```text
Task: <id/title>
Status: DONE | BLOCKED | PARTIAL | NEEDS_FIX

Implemented:
- <changed output and behavior>

Validation:
- <command/check> | PASS | <relevant count/version>

Contract notes:
- DB: ...
- Authorization: ...
- API/OpenAPI: ...
- UI/i18n: ...

Commit: <hash or NOT CREATED>
Remaining / blocker:
- ...
```

For a batch, use:

```text
Batch: <gate/tasks>
Status: DONE | BLOCKED | PARTIAL
Task | Status | Scoped gate | Commit | Blocker
Batch gates:
- ...
Worktree: <clean or details>
Next recommended batch: <gate/tasks>
```
