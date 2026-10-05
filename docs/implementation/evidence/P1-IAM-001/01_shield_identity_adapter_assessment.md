# P1-IAM-001 Shield Identity Adapter Assessment

## Outcome

- Task: P1-IAM-001 — Shield identity/group/direct-permission adapter.
- Status: DONE.
- Dependency: P1-SEED-001 is DONE and its canonical Resource/Action catalog is used.
- Scope is limited to the Shield bridge and removal of obsolete pre-P1 security entrypoints.
- P1-IAM-002 EffectiveProfile union and P1-AUTH authorization decisions remain out of scope.

## Security preflight

- The initial preflight found legacy `Admin` membership authorization and a client-supplied organization context.
- Those obsolete public routes/controllers and the old PBAC/profile stack were removed before implementation.
- No public Controller, Command, Import, Background, or Adapter imports an application Repository directly.
- Architecture guard statically scans Controllers plus Commands, Filters, Adapters, and Import surfaces.
- No `isSystemAdmin`, root flag, privileged-user runtime shortcut, or unconditional authorization allow path remains.
- Shield bootstrap still provisions `SYSTEM_ADMIN` membership only; runtime permissions come from explicit catalog rows.
- No password, token, session secret, or full Shield profile payload is logged or returned by the adapter.

## Adapter contract

- `ShieldIdentityAdapter` resolves a trusted Shield `User` or `OperationContext` actor.
- Identity lookup distinguishes invalid/missing identity from `IAM_PERSISTENCE_FAILED`.
- Inactive, locked, suspended, banned, or disabled identities resolve as non-operational and grant no adapter output.
- Active Shield memberships map case-insensitively to active ERP Authorization Groups.
- Inactive or unknown ERP groups are omitted and cannot grant permissions.
- Group permissions map only joined canonical `Resource`/`Action` rows and are deterministic/deduplicated.
- Direct Shield permissions are exact allow additions only; malformed or unknown tokens are ignored.
- No union, PBAC lane, Policy, Evaluation Organization, DENY, or System Admin bypass is implemented.

## Validation evidence

| Check | Result |
|---|---|
| `task-context.php P1-IAM-001` | PASS; authority cache valid |
| Focused IAM PHPUnit | PASS; 8 tests, 19 assertions |
| IAM + privileged seed PHPUnit | PASS; 12 tests, 42 assertions |
| Full backend PHPUnit `--no-coverage` | PASS; 102 tests, 1,834 assertions |
| PHPStan level 8 | PASS; no errors |
| Architecture guard | PASS; 7 application files checked |
| Context-cache validator | PASS; 100 tasks, acyclic dependency graph |
| Ledger validator | PASS; 100 rows, 33 DONE |
| `git diff --check` | PASS |
