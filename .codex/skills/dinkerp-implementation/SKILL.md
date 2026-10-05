---
name: dinkerp-implementation
description: Implement, review, or plan DinkERP P1 work through bounded authority reading, gate registries, scoped validation, and isolated task commits.
---

# DinkERP Implementation Control Plane

Use this skill for one approved DinkERP task, vertical slice, review, or
planning outcome. `AGENTS.md` remains the governing repository instruction.

## Execution flow

1. **Task context first**: identify Task IDs and run
   `php .codex/skills/dinkerp-implementation/scripts/task-context.php` (use
   `--batch` for a declared batch). Do not read compiled catalogs directly.
2. **Cache guard**: require `AUTHORITY_CACHE: VALID`. A stale hash, missing
   provenance/rule, or cache/repository conflict is a stop condition.
3. **Bounded authority**: LOW/MEDIUM tasks use valid compiled context without
   direct architecture reads unless context is insufficient or debugging needs
   it. HIGH tasks load context first, then verify only its exact critical source
   pointers; never reread full documents.
4. **Repository inspection**: run `git status --short`, record pre-existing
   changes, and inspect current paths, commands, contracts, and tests before
   proposing edits.
5. **Task contract**: state scope/out-of-scope, dependencies and READY status,
   DB, authorization/context, transaction/concurrency, API/OpenAPI, UI/i18n,
   and test impact.
6. **Risk and validation**: use the registry risk/profile and task impact. Use
   impact-aware gates from `references/validation-profiles.md`; HIGH-risk work
   is never batched by default.
7. **Implement**: make the smallest coherent change within the task boundary;
   preserve the authority order and project invariants.
8. **Scoped validation**: run the selected profile, plus the relevant review
   checklist. Do not run the full repository regression suite after every
   LOW/MEDIUM task.
9. **Independent checkpoint**: if DONE, run `git diff --check`, inspect the
   diff/status, stage only task files explicitly, and create exactly one commit
   containing the Task ID. Never stage unrelated work.
10. **Batch progression**: after successful task commits, run the impact-aware
   batch gate. If it finds a regression, make a corrective commit; do not
   amend, rebase, or rewrite prior task commits.
11. **Compact report**: record outcome, files/behavior, critical contract
    checks, validation as `command/check | PASS | count/version`, commit,
    blocker, and next dependency. Keep normal assessments about 30–60 lines.

## Progressive loading

Never read all architecture documents by default. Resolve context through
`task-context.php`, which reports `rule_count` and `context_char_count`. Given a
Task ID, use the registry and exact pointers emitted by the script; expand only
for dependency closure, source conflict, or debugging. Do not reread accepted
assessment documents unless the current task depends on their implementation or
evidence state. Do not paste verbose successful logs or reread unchanged files
in one batch.

## Source-read policy

- LOW: with a valid cache, architecture documents are not read directly.
- MEDIUM: with a valid cache, architecture documents are not read directly
  unless context is insufficient, conflicting, or debugging requires it.
- HIGH: load compiled context first and directly verify only exact critical
  sections declared for that task.
- Hash mismatch, missing rule/provenance, or a cache/repository conflict stops
  execution instead of guessing.

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
