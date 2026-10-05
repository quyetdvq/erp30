# Execution Protocol

This protocol is the operating rule for P1 task execution. It is a control
plane, not a replacement for UR, FRS, DBD, the Implementation Contract, the
Plan, API Design, or UI Design.

## Bounded reading

1. Identify Task IDs and run `scripts/task-context.php` (use `--batch` for a
   declared batch); do not read compiled catalog files into model context.
2. Require `AUTHORITY_CACHE: VALID`; stale hashes, missing provenance/rules, or
   cache/repository conflicts stop execution.
3. Read `AGENTS.md` and the applicable `tasks-gate<N>.md` registry. LOW/MEDIUM
   tasks do not directly read architecture documents with a valid cache.
4. For HIGH tasks, verify only exact critical authority pointers emitted by the
   context script. Expand only for dependency closure, source conflict, or
   debugging; never load all architecture documents by default.
5. Treat accepted implementation assessments as evidence, not authority, and
   reread them only when the current task depends on their state.

## Task contract and readiness

Before editing, record Task ID/title, scope and exclusions, registry risk and
profile, dependency status, primary output, and DB/auth/context/transaction/API/
UI/test impact. A task is READY only when every dependency is DONE/VERIFIED or
the Plan explicitly permits the parallel opening. An unresolved authority
conflict or missing decision is BLOCKED; do not invent a rule to proceed.

## Risk and batching

- **LOW**: documentation/tooling, isolated tests, read-only inventory, or a
  change with no schema/auth/concurrency/public-contract effect.
- **MEDIUM**: ordinary repository/service/UI/API work with bounded blast radius
  and established contracts.
- **HIGH**: Authorization/PBAC; Evaluation Organization; DomainLock or other
  concurrency; Auto Number allocation; Pricing resolution; COMBO
  expansion/allocation; Import mutation/authorization; security bootstrap; or
  release verification. Any task that can alter these guarantees is HIGH.

Default batch sizes are LOW 3–5 tasks, MEDIUM 2–3 tasks, and HIGH 1 task.
Batch only tasks whose dependencies and file ownership are independent. Never
batch a HIGH task by default, and never declare a slice VERIFIED while an
invariant dependency is unresolved.

## Sequential execution

For a batch, execute tasks in registry/dependency order. Stop on the first
failure, blocker, unexpected worktree change, or authority conflict. A failed
task does not authorize moving to its dependents. Record the failure and leave
the worktree recoverable for the next instruction.

## Git isolation

At task start, run `git status --short` and record pre-existing paths. One Task
ID produces at most one completion commit, containing only that task's files;
stage paths explicitly and never use `git add .` or `git add -A`. Run
`git diff --check` before a DONE commit. Keep the worktree clean between tasks
unless an explicitly recorded pre-existing change remains outside the task.

## Batch closeout

After all task-scoped gates pass and each task has its independent commit, run
the impact-aware batch gate. Add unrelated layer gates only for cross-layer or
release impact, an unexpected changed path, or an explicit registry requirement.
If the batch gate exposes a regression, create a new corrective commit tied to
the affected Task ID or a clearly named correction task. Do not amend, squash,
rebase, reset, or rewrite earlier task commits.

## High-risk minimums

High-risk validation must retain the relevant deep/full contract checks,
including negative authorization paths, authoritative Evaluation Organization,
same-session DomainLock and race tests, Auto Number rollback/no-reuse, exact
Pricing fallback, acyclic COMBO expansion and decimal allocation, Import's
two-gate/atomic row behavior, bootstrap no-bypass behavior, and release
evidence. A fast static check alone cannot close HIGH risk.
