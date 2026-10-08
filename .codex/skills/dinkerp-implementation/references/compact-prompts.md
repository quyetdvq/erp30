# Compact Codex Prompt Pattern

Use this only when the user wants a prompt/handoff for Codex, not direct implementation. The skill and task context carry the detailed baseline; the prompt carries only execution deltas.

## Default template

```text
Use the repository `dinkerp-implementation` skill. Execute <TASK-ID — TITLE>.

Run `php .codex/skills/dinkerp-implementation/scripts/task-context.php <TASK-ID>` (includes cache/ledger validation); require `AUTHORITY_CACHE: VALID`, confirm live ledger status and dependencies, inspect `AGENTS.md`, worktree, real code, approved change overlays, and exact HIGH-risk source pointers. If DONE, audit/retrofit only under an approved scoped change, not a fresh task implementation. Stop on stale cache, unmet dependency, source conflict, or required CONTRACT_PENDING surface.

Task deltas:
- <2–6 non-obvious rules from resolved task context only>

Implement the smallest coherent change; preserve unrelated work. Run the task validation profile and all mandatory deep checks. For DB reset/fresh tests use only guarded `testing` / exact `erp_test`; never destructive-test `erp`. Update OpenAPI/codegen only when the public API changes.

Create one task commit only if every mandatory gate passes and update required ledger/evidence. Return: Status, commit, changed files, key contract checks, validation commands/results, preserved unrelated work, blocker/next dependency.
```

## Compression rules

- Do not paste endpoint tables, schema tables, global PBAC precedence, standard HTTP envelopes, or all test cases when the task context already resolves them.
- Keep only differences that could make Codex implement the wrong semantics.
- For HIGH-risk API tasks, usually keep: Resource Domain/context source, pending surfaces, list visibility-before-total, concurrency/history edge if applicable, and required closure gates.
- If Codex is already implementing the task, do not issue a second implementation prompt; wait for its closure report, then use audit mode.

## Currently pending P1 work

The live ledger (not task cache) gives 96/107 DONE at the 2026-10-08 Project snapshot. The 11 not-started IDs are P1-FE-080, P1-FE-090, P1-SYS-001..007, P1-UAT-001, P1-RC-001. Re-read the ledger on every prompt; do not hard-code this snapshot as live truth. Approved 08–11 change-control overlays are NOT additional P1 tasks or automatic evidence of implementation.
