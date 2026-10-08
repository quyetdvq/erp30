---
name: dinkerp-implementation
description: Implement, review, diagnose, or draft compact Codex handoff prompts for DinkERP P1 from a Task ID, gate, approved change control, implementation decision, or execution ledger. Resolve the current UR v1.0.15, FRS v1.12, DBD v1.15, Contract v1.10, Plan v1.10, API v1.8, UI v1.9, approved overlays, real P1 task status, and bounded source pointers. Protect PBAC, Shield, migrations, Product/Pricing/Import, frontend Table First, validation gates, and task-isolated commits. Do not invent P2 contracts or reopen completed P1 tasks.
---

# DinkERP P1 Implementation Control Plane

Work on a named P1 Task ID, bounded approved change-control retrofit, review, or concise Codex prompt. Repository `AGENTS.md` remains governing local instructions. This Skill is an execution aid, **not** a new architecture authority and not a P2 task registry.

## Sources and status — distinguish them

- **Seven pinned P1 baselines:** UR `1.0.15` (business), FRS `1.12` (functional), DBD `1.15` (schema), Implementation Contract `1.10` (runtime/wire), Plan `1.10` (task ordering), API Design `1.8` (public routes), UI Design `1.9` (screens).
- **Five approved change-control documents in manifest:** Display Settings `07` v1.2; Permission-aware UI `08` v1.0; Policy Scope Uniqueness `09` v1.0; User Identity Mutation `10` v1.0; Resource Actions Bulk API `11` (filename v1.0; internal document version v1.1). Older CHANGE-AUTH-DOMAIN-001 is reflected in the seven baselines; do not restore old Resource Domain values.
- **Operational decisions:** `docs/implementation/P1_DECISIONS.md` (nine current `DEC-*` IDs). They are accepted supplementary decisions, not substitutes for architecture authority.
- **Operational progress:** `docs/implementation/P1_EXECUTION_LEDGER.md` is the **live** Task ID/status/commit source; never trust static planning snapshots or module-map status. At the supplied 2026-10-08 snapshot: 96/107 DONE; 11 not started, comprising FE-080/090 and nine Gate-9 tasks. Re-check every time.
- **Approved additive tracks:** `compiled/change-controls.json` records scope and affected tasks for `08`–`11`. Approval is **not implementation evidence**, not a new Task ID, and not permission to reopen DONE tasks. Confirm actual repository migrations/API/UI/tests separately.
- **Frontend operational rule:** [references/ui-table-action-status.md](references/ui-table-action-status.md) supplements [references/frontend-table-first.md](references/frontend-table-first.md). Neither changes business authority.

## Modes

- **Prompt/handoff:** generate a short task delta prompt; do not duplicate every baseline.
- **Execute/review:** inspect real worktree, code, source pointers, implement only an approved scoped change, run appropriate gates.
- **Audit a closure report:** validate evidence, ledger, commit isolation and required negative/concurrency tests; refuse premature DONE.
- **Ask about status:** read the live ledger and distinguish planned/recorded/runtime verified work.

## Mandatory preflight (no exception for prompts)

From repository root:

```bash
php .codex/skills/dinkerp-implementation/scripts/task-context.php <TASK-ID>
```

The script first enforces `validate-context-cache.php` and `validate-ledger.php`, pins the 12 architecture/change-control hashes and current decision source, cross-checks 107-task Plan metadata, and reads *live* ledger status. Require `AUTHORITY_CACHE: VALID`. On `AUTHORITY_CACHE_STALE`, validation failure, unknown Task ID, unknown decision, missing Git authority, unresolved dependency or open required contract, **STOP** rather than guessing. For a declared independent batch use `--batch`.

Check `git status --short`, current files/tests, actual dependency commits, and `AGENTS.md`. A `DONE` Task ID means verify/review by default; a retrofit requires an explicitly approved change-control boundary and its own evidence. Module ownership references never prove completion. Do not assert an approved change is deployed based on its specification.

For HIGH tasks read the **exact** current critical source pointers after the bounded context. For LOW/MEDIUM read architecture directly only if the context is insufficient, conflicting or debugging requires it. Do not paste all architecture documents into a prompt.

## Compact Codex handoff

Include only: `(1)` Task ID/title and desired result, `(2)` context/ledger/worktree/dependency preflight, `(3)` 2–6 task-specific risk deltas and relevant approved change overlays, `(4)` validation/profile and exact database safety, `(5)` task-only commit and closure report. Aim for **220 words or less** unless an unresolved conflict, source-open contract, or explicitly requested detailed handoff requires expansion. Use [references/compact-prompts.md](references/compact-prompts.md).

Never silently promote `CONTRACT_PENDING` to ready. The approved v1.8 Shield User admin API is no longer the same as the *historical* v1.7 pending item; implementation still requires actual code/evidence review. Exact export media type, public Price Resolution exposure, optional COMBO preview and other source-open items remain pending unless approved later.

## Implementation and quality controls

1. Keep task scope explicit; respect dependency order, risk and validation profile. Default batch LOW 3–5, MEDIUM 2–3, HIGH 1.
2. Preserve Resource Domains `SYSTEM`/`MASTER`/`ORGANIZATION`, GROUP context for system/master, authoritative persisted owner/target Organization resolvers, Base Permission, PBAC, `ABSOLUTE_DENY` propagation, denied-set intersection and no System Admin bypass.
3. Preserve transaction boundaries, DomainLock, series counter rollback, BIGINT/DECIMAL string serialization, exact Product/UOM/COMBO/Pricing arithmetic, Product-only Import and shared infrastructure ownership. Migrations are forward-only.
4. Keep frontend Table First for List Management; use shared `TableStatus`, `TableRowActions`, `TableDetailModal`, accessible vi/en i18n, permission hint only and server-side authorization. Keep Display Settings purely presentation-layer; business timezone remains separate.
5. For destructive DB test/rehearsal require environment `testing` and connected database **exactly `erp_test`**. Never reset development `erp`.
6. Select scoped gates from [references/validation-profiles.md](references/validation-profiles.md). HIGH and Gate 9 tasks must retain real regressions/negative/concurrency/run-time evidence where required, not static-only proof.
7. For changed public APIs, update canonical OpenAPI, lint and regenerate frontend client in the same scope; never hand-edit generated types.
8. Review diff, `git diff --check`, stage only task-owned paths, preserve unrelated work, and create one Task-ID commit only when mandatory gates pass. Update ledger/evidence in that commit using established self-reference convention, never rewrite accepted history.
9. Report `DONE | BLOCKED | PARTIAL | NEEDS_FIX | NEEDS_VERIFY`, changed files/behavior, contract checks, commands/test counts, commit, unrelated pre-existing changes, blockers and next dependency. No unverified completion claims.

## References

- [references/execution-protocol.md](references/execution-protocol.md): readiness, Git isolation, operational ledger, approved change overlays.
- [references/tasks-gate0.md](references/tasks-gate0.md) through [references/tasks-gate9.md](references/tasks-gate9.md): navigation pointers for the fixed P1 registry; do not extrapolate a P2 task plan.
- [references/project-invariants.md](references/project-invariants.md): shared safeguards and updated source-open boundaries.
- [references/review-checklists.md](references/review-checklists.md): task-specific closure review.
- [references/frontend-table-first.md](references/frontend-table-first.md), [references/ui-table-action-status.md](references/ui-table-action-status.md): mandatory list/table presentation for applicable UI.

## Completion format

```text
Task / Approved change: <ID>
Live ledger status before work: <state>
Outcome: DONE | BLOCKED | PARTIAL | NEEDS_FIX | NEEDS_VERIFY
Changed: <files and behavior>
Validation: <command | PASS/FAIL | count/version>
Contract: <DB/Auth/API/UI invariants + change-control evidence>
Commit: <hash or NOT CREATED>
Unrelated work preserved: <paths>
Remaining/blockers: <items>
Next dependency: <ID>
```
