---
name: dinkerp-implementation
description: Implement, review, or plan DinkERP P1 work from Task IDs, gate work, API endpoints, UI screens, migrations, authorization, Auto Numbering, Product/Pricing/COMBO, Product Import, or i18n changes. Use in the DinkERP repository to resolve bounded authority context, preserve approved architecture and PBAC/schema invariants, run impact-aware validation, and create isolated task commits without inventing missing contracts.
---

# DinkERP Implementation Control Plane

Use this skill for one approved DinkERP task, bounded batch, review, or planning
outcome. `AGENTS.md` remains the governing repository instruction.

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
5. **Delta-only task contract**: state only task-specific scope/out-of-scope,
   dependency readiness, changed DB/auth/context/transaction/API/UI impact, and
   risky/non-obvious invariants. Reference compiled context instead of restating
   global project rules.
6. **Risk and validation**: use the registry risk/profile and task impact. Use
   impact-aware gates from `references/validation-profiles.md`; HIGH-risk work
   is never batched by default.
7. **Implement**: make the smallest coherent change within the task boundary;
   preserve authority order, approved architecture decisions, and project
   invariants.
8. **Scoped validation**: run the selected profile plus relevant review
   checklist. Do not run unrelated gates merely for volume; HIGH-risk and gate
   close tasks retain their required deep/full regressions.
9. **Independent checkpoint**: if DONE, run `git diff --check`, inspect the
   diff/status, stage only task files explicitly, and create exactly one commit
   containing the Task ID. Never stage or revert unrelated pre-existing work.
10. **Batch progression**: after successful task commits, run the impact-aware
    batch gate. If it finds a regression, make a corrective commit; do not
    amend, rebase, squash, reset, or rewrite prior task commits.
11. **Compact report**: record outcome, changed behavior, critical contract
    checks, validation as `command/check | PASS | count/version`, completion
    commit, blocker, and next dependency.

## Progressive loading

Never read all architecture documents by default. Resolve context through
`task-context.php`, which reports `rule_count` and `context_char_count`. Given a
Task ID, use the registry and exact pointers emitted by the script; expand only
for dependency closure, source conflict, contract status, or debugging. Treat
accepted implementation assessments as evidence, not authority.

## Source-read policy

- LOW: with a valid cache, architecture documents are not read directly.
- MEDIUM: with a valid cache, architecture documents are not read directly
  unless context is insufficient, conflicting, or debugging requires it.
- HIGH: load compiled context first and directly verify only exact critical
  sections declared for that task.
- Hash mismatch, missing rule/provenance, `CONTRACT_PENDING`, or a
  cache/repository conflict stops execution instead of guessing.

## Authority and safety

Use this order when sources differ: UR -> FRS -> DBD -> Implementation Contract
-> Implementation Plan -> API Design -> UI Design. Stop on unresolved conflict
instead of inventing semantics, schema, Resource/Action, endpoint behavior,
field values, or persistence.

Preserve PBAC/Evaluation Organization rules, atomic multi-table mutations,
DomainLock conventions, decimal arithmetic, Auto Number transaction semantics,
Product/Pricing/COMBO ownership, Product Import boundaries, API
serialization/error/contract-status rules, and `vi`/`en` i18n. Migrations are
forward-only.

## Required references

- `references/execution-protocol.md` — readiness, batching, Git isolation, and
  ledger convention.
- `references/validation-profiles.md` — scoped and batch validation.
- `references/tasks-gate0.md` through `references/tasks-gate9.md` — task
  navigation pointers only; authoritative requirements stay in `docs/`.
- `references/project-invariants.md` — compact architecture/domain guardrails.
- `references/review-checklists.md` — final self-review, including Gate 7 API.

## Completion shape

```text
Task: <id/title>
Status: DONE | BLOCKED | PARTIAL | NEEDS_FIX | NEEDS_VERIFY

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

For a batch:

```text
Batch: <gate/tasks>
Status: DONE | BLOCKED | PARTIAL
Task | Status | Scoped gate | Commit | Blocker
Batch gates:
- ...
Worktree: <clean or exact pre-existing paths>
Next recommended batch: <gate/tasks>
```
