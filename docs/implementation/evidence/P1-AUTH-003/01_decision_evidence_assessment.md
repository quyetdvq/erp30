# P1-AUTH-003 Authorization Decision Evidence

Task context resolved with `task-context.php P1-AUTH-003` reported
`AUTHORITY_CACHE: VALID`, HIGH risk, backend-security impact, and dependency
`P1-AUTH-002` DONE. Direct authority verification was limited to FRS
`FRS-P1-PBAC-005`, Implementation Contract §6 explainability, and
Implementation Plan `P1-AUTH-003`.

## Implemented

- Centralized stable reason constants in `AuthorizationService`:
  `AUTH_NO_BASE_PERMISSION`, `AUTH_POLICY_DENIED`, `AUTH_ABSOLUTE_DENY`,
  `AUTH_NO_MATCH_ALLOW`, and `AUTH_POLICY_ALLOWED`.
- Preserved the AUTH-002 authorization result while distinguishing default
  NO_MATCH ALLOW from policy-derived ALLOW.
- `matched_policy_ids` continues to contain only matched, in-scope,
  resource/action/state policies, deterministically sorted and deduplicated.
- `decisive_policy_ids` now contains only policies that explain the final
  effective result; an ordinary lane DENY reopened by another lane is not
  reported as decisive. ABSOLUTE_DENY remains decisive and propagated.
- `participating_assignment_ids`, base permission source, request ID, and the
  single operation Evaluation Time remain deterministic and non-localized.
- `AuthorizationDecision` normalizes evidence ordering/deduplication at the
  value-object boundary and exposes only internal runtime evidence.

No authorization logs, schema, public API, controller, UI, or System Admin
special path were added.

## Validation

| Check | Result |
|---|---|
| `vendor/bin/phpunit tests/unit/P1Auth003AuthorizationDecisionTest.php --no-coverage` | PASS, 3 tests, 11 assertions |
| `vendor/bin/phpunit tests/unit/P1Auth002AuthorizationServiceTest.php --no-coverage` | PASS, 8 tests, 24 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS, 130 tests, 1,916 assertions |
| `vendor/bin/phpstan analyse --no-progress --level=8` | PASS |
| `architecture-guard.php` | PASS |
| `validate-context-cache.php` | PASS |
| `validate-ledger.php` | PASS before task row update |
| `git diff --check` | PASS |

Persistence failures remain typed failures; they are not converted into
authorization decisions.
