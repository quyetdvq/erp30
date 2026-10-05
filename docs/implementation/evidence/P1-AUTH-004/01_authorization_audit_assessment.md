# P1-AUTH-004 Authorization Audit Persistence

Task context resolved with `task-context.php P1-AUTH-004` reported
`AUTHORITY_CACHE: VALID`, HIGH risk, backend-security impact, and dependency
`P1-AUTH-002` DONE. Direct authority verification was limited to FRS
`FRS-P1-PBAC-006`, DBD §14.1 `authorization_logs`, Implementation Contract
§10.3, and Implementation Plan `P1-AUTH-004`.

## Implemented

- Added `AuthorizationLogRepository` as an insert-only persistence boundary
  for the existing `authorization_logs` schema. It has no update/delete path
  and translates failed inserts to typed persistence failures.
- Added centralized `AuthorizationAuditScope` for canonical persisted actions:
  `approve`, `delete`, and `setstate`. View/list decisions return without an
  audit write.
- Added `AuthorizationAuditService` as an adjacent application-level mapper
  from trusted `AuthorizationDecision` evidence. No public use case exists yet,
  so no business endpoint was invented.
- Resource IDs come from the canonical resource catalog. User, organization,
  state, decision, reason, request ID, and UTC captured Evaluation Time map only
  to approved schema columns.
- `matched_policy_id` is persisted only for exactly one decisive policy;
  zero or multiple decisive policies persist `NULL`, while richer runtime
  evidence remains in `AuthorizationDecision`.
- No schema, authorization algorithm, audit update/delete, retention policy,
  API/UI, lifecycle, or System Admin special path was added.

Audit is an adjacent application transaction because no current concrete
Approve/Delete/State-change business use case exists to provide a shared
business transaction boundary. Insert failures propagate and are never
converted into authorization decisions.

## Validation

| Check | Result |
|---|---|
| `vendor/bin/phpunit tests/unit/P1Auth004AuthorizationAuditTest.php --no-coverage` | PASS, 5 tests, 30 assertions |
| `vendor/bin/phpunit tests/unit/P1Auth002AuthorizationServiceTest.php --no-coverage` | PASS, 8 tests, 25 assertions |
| `vendor/bin/phpunit tests/unit/P1Auth003AuthorizationDecisionTest.php --no-coverage` | PASS, 3 tests, 11 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS, 135 tests, 1,946 assertions |
| `vendor/bin/phpstan analyse --no-progress --level=8` | PASS |
| `architecture-guard.php` | PASS |
| `validate-context-cache.php` | PASS |
| `validate-ledger.php` | PASS before task row update |
| `git diff --check` | PASS |
