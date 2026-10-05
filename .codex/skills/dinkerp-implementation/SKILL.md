---
name: dinkerp-implementation
description: Implement, review, or plan DinkERP P1 code changes from a Task ID, vertical slice, bug, API endpoint, UI screen, migration, authorization rule, Auto Numbering behavior, import flow, or i18n change. Use in the DinkERP repository when Codex must trace work to the baseline docs, preserve PBAC and schema invariants, inspect existing code before editing, run the required backend/frontend quality gates, and report evidence without inventing missing requirements.
---

# DinkERP Implementation

Use the repository's current `AGENTS.md` as always-on guidance. Treat this skill as the repeatable execution workflow for one task or one coherent slice.

## Workflow

1. Identify the requested Task ID, slice, bug, or concrete outcome.
2. Read only the relevant parts of the current project documents under `docs/`.
3. Apply this authority order when sources differ:
   - User Requirements: business intent and scope.
   - FRS: functional semantics.
   - Database Design: persistence/schema invariants.
   - Implementation Contract: wire/runtime conventions.
   - Implementation Plan: sequencing, Task IDs, dependencies, quality gates.
   - API Design: downstream HTTP contract.
   - UI Design: downstream UI/UX contract.
4. Inspect existing repository code before proposing file locations, abstractions, commands, or conventions. Reuse established structure unless it conflicts with an authority document.
5. Before editing, state a compact task contract:
   - source references;
   - scope and out-of-scope;
   - dependencies;
   - database impact;
   - authorization context/action impact;
   - transaction/concurrency impact;
   - API/OpenAPI impact;
   - UI/i18n impact;
   - tests required.
6. If an unresolved conflict would require inventing business semantics, schema, Resource/Action, public API behavior, or persistence, stop and mark the task `BLOCKED`. Do not guess.
7. Implement the smallest coherent change that satisfies the task contract. Do not opportunistically refactor unrelated code.
8. Run the applicable quality gates. Fix failures caused by the change; do not suppress them merely to pass.
9. Self-review against `references/review-checklists.md`.
10. Report what changed, tests/evidence, unresolved items, and the next dependency.

## High-risk rules

Read `references/project-invariants.md` before work involving authorization, Organization context, migrations, Auto Numbering, Product/COMBO/Pricing, Import, API transport, or localization.

Read `references/review-checklists.md` before finalizing any implementation task.

## Planning behavior

For a substantial feature or multi-file refactor, make a short execution plan first. Keep it task-specific. Do not restate the full project documentation.

When the user asks only for analysis/review, do not modify code unless explicitly requested.

## Output format

At completion, use this compact structure:

```text
Task: <id/title>
Status: DONE | BLOCKED | PARTIAL

Implemented:
- ...

Validation:
- <command/check>: PASS | FAIL | NOT RUN

Contract notes:
- DB: ...
- Authorization: ...
- API/OpenAPI: ...
- UI/i18n: ...

Remaining / blocker:
- ...
```

If blocked, name the exact source conflict or missing decision and the smallest decision needed to continue.


## Git commit policy

Treat one approved implementation task as one Git checkpoint.

Before starting a task:
1. Run `git status --short`.
2. Record pre-existing modified/untracked files.
3. Never overwrite, stage, revert, or commit unrelated pre-existing changes.

At task completion:

### If task status is DONE
1. Run all applicable validation required by the task.
2. Run `git diff --check`.
3. Inspect `git status` and `git diff`.
4. Confirm every changed file belongs to the current Task ID.
5. Stage only task-related files explicitly.
6. Do not use `git add -A` or blindly stage the entire repository.
7. Create exactly one task commit unless repository history/convention requires otherwise.
8. Include the Task ID in the commit subject.
9. Report the resulting commit hash.

Preferred commit subject:

`<type>(<task-id>): <concise outcome>`

Examples:

`chore(P1-ENV-004): establish isolated test database harness`
`feat(P1-DB-010): add foundation location migrations`
`fix(P1-AUTH-002): correct assignment lane resolution`
`docs(P1-DB-001): define P1 migration execution plan`

### If task status is PARTIAL, BLOCKED, or NEEDS_FIX
- Do not create a task-completion commit.
- Do not automatically revert work.
- Report all modified/untracked files.
- Report why the task cannot be committed as DONE.
- Wait for the next instruction.

### Safety
Never:
- commit `.env`, credentials, secrets, database dumps, or local machine configuration;
- amend, squash, rebase, force-push, or rewrite existing history unless explicitly instructed;
- commit unrelated changes made before the task;
- bypass failing required quality gates just to produce a commit.