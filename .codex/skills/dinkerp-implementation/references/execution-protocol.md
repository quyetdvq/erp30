# Execution Protocol

This protocol controls P1 task execution. It does not replace UR, FRS, DBD, the Implementation Contract, the Plan, API Design, or UI Design.

## Bounded reading

1. Identify P1 Task IDs and run `scripts/task-context.php` (`--batch` only for a declared batch). It runs both validators before emitting context; do not load compiled catalogs into model context.
2. Require `AUTHORITY_CACHE: VALID` plus `LEDGER_VALIDATION: PASS` and `CACHE_VALIDATION: PASS` (automatically enforced by task-context); stale hashes, Plan/task mismatch, missing decision/provenance, or cache/repository conflicts stop execution.
3. Read `AGENTS.md` and the applicable `tasks-gate<N>.md` registry. LOW/MEDIUM tasks do not directly read architecture documents with a valid cache.
4. For HIGH tasks, verify only exact critical authority pointers emitted by the context script. Expand only for dependency closure, contract status, source conflict, or debugging.
5. Treat accepted implementation assessments as evidence, not authority.

## Task contract and readiness

Before editing, record Task ID/title, **live ledger status** and dependency status, registry risk/profile, primary output, approved overlays and only the task-specific DB/auth/context/transaction/API/UI/test deltas. Do not restate global invariants already resolved by compiled context.

A task is READY only when every dependency is DONE/VERIFIED or the Plan explicitly permits parallel opening. If the task is already DONE, default to review/evidence verification; corrections require their own approved scoped change and must not silently reopen the original task. An unresolved authority conflict, missing decision, stale cache, or required `CONTRACT_PENDING` API surface is BLOCKED; do not invent a rule to proceed.

## Risk and batching

- **LOW**: documentation/tooling, isolated tests, read-only inventory, or a change with no schema/auth/concurrency/public-contract effect.
- **MEDIUM**: ordinary repository/service/UI/API work with bounded blast radius and established contracts.
- **HIGH**: Authorization/PBAC; Evaluation Organization; DomainLock/concurrency; Auto Number allocation; Pricing resolution; COMBO expansion/allocation; Import mutation/authorization/reconciliation; security bootstrap; public contract infrastructure; or release verification.

Default batch sizes: LOW 3–5, MEDIUM 2–3, HIGH 1. Batch only independent dependencies/file ownership. Never batch HIGH by default.

## Sequential execution

For a batch, execute tasks in registry/dependency order. Stop on the first failure, blocker, unexpected worktree change, cache conflict, or authority conflict. A failed task does not authorize moving to dependents.

## Database safety

- Never run destructive migration/reset/refresh operations against the development database `erp`.
- Before any destructive fresh/reset test, assert the runtime environment is `testing` and the connected database name is exactly `erp_test`; otherwise abort.
- Forward rehearsal uses a controlled pre-change test state and forward migrations; do not use destructive refresh as a substitute for forward verification.

## Compact Codex prompt handoff

When the user asks for a Codex prompt rather than direct execution, resolve the task through `task-context.php` first, then emit a short handoff instead of restating the baselines. Include only: Task ID/objective, start/preflight command, 2–6 task-specific non-obvious deltas from resolved rules/decisions, required validation profile, commit condition, and closure-report request. Omit global rules already enforced by this skill. Default target: <= 220 words unless the user asks for a detailed handoff. See `references/compact-prompts.md`.

## Git isolation

At task start, run `git status --short` and record pre-existing paths. One Task ID produces at most one completion commit containing only that task's files. Stage paths explicitly; never use `git add .` or `git add -A`. Run `git diff --check` before a DONE commit.

Never restore/revert/stage an unrelated pre-existing change. Do not amend, squash, rebase, reset, force-push, or rewrite accepted task commits unless explicitly instructed.

## Durable execution ledger

- A DONE task updates `docs/implementation/P1_EXECUTION_LEDGER.md` in the same task commit.
- If the ledger is part of that completion commit, it cannot embed its own final hash. Keep the repository's current self-reference convention (for example `0000000`) and treat the actual post-commit Git hash in the report/history as authoritative.
- Do not create a second corrective/checkpoint commit merely to make the ledger self-reference its own commit hash.
- A later genuine corrective commit appends its hash to the accepted Task row; it does not replace the original completion commit.
- HIGH-risk, gate-close, and release tasks create/update compact assessment evidence. LOW/MEDIUM tasks need separate evidence only when the registry or exceptional behavior requires it.
- The ledger is operational evidence only; it never overrides architecture authority or task semantics.

## Batch closeout

After task-scoped gates pass and each task has its own commit, run the impact-aware batch gate. Add unrelated layer gates only for cross-layer/release impact, unexpected changed paths, or explicit registry requirements. If the batch gate exposes a regression, create a new corrective commit tied to the affected Task ID or correction task.

## High-risk minimums

Retain the relevant deep/full checks: PBAC/context negative paths; same-session lock/race behavior; Auto Number rollback/no-reuse; Pricing fallback; acyclic COMBO and decimal allocation; Import parser/dependency/two-gate/row/reconciliation behavior; bootstrap no-bypass; public API serialization/locale/authorization/OpenAPI drift; and release evidence. Static checks alone cannot close HIGH risk.

## Approved additive changes versus execution progress

The 107-task registry remains fixed. Documents 08 (Base Permission snapshot), 09 (Policy Scope uniqueness), 10 (Shield User mutation) and 11 (Resource Actions bulk representation) are approved overlays. `compiled/change-controls.json` identifies affected completed task boundaries; treat approval as design authority only. Verify implementation, migration, API/OpenAPI, frontend and regressions in the actual repository before claiming closure. The runtime ledger is the only task-status source.

`P1_DECISIONS.md` supplies PO decisions as operational decision support but never overrides the seven architecture baselines. `UI_TABLE_ACTION_STATUS_RULE.md` is bundled in `references/ui-table-action-status.md`; apply its shared icon/status primitives without inventing new business actions. Do not invent P2 Task IDs from the P1 registry; P2 needs a separately approved authority/task catalog.
