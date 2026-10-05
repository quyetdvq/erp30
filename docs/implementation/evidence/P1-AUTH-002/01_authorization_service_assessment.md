# P1-AUTH-002 AuthorizationService Core

Task context was resolved with `task-context.php P1-AUTH-002` and reported
`AUTHORITY_CACHE: VALID`, HIGH risk, backend-security impact, and all four
dependencies DONE. Direct authority verification was limited to FRS
`FRS-P1-PBAC-001..007`, Implementation Contract §6, and Implementation Plan
`P1-AUTH-002`.

## Implemented

- Added `AuthorizationService` as a persistence-composing PBAC decision engine.
- Base Permission is resolved first through `EffectiveProfileResolver`; missing
  Base Permission returns `DENY` with `AUTH_NO_BASE_PERMISSION`.
- Authoritative Evaluation Organization and client-hint mismatch remain owned by
  `EvaluationOrganizationResolver`.
- Exact `GROUP -> ... -> O` scope paths are resolved through `OrganizationService`.
- Actor, effective assignment, and common ORGANIZATION lanes apply QT1:
  `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`, with `NO_MATCH` allowing after
  Base Permission.
- Applicable absolute denies propagate to all lanes before the final denied-set
  intersection. No System Admin branch, audit persistence, lifecycle decision,
  API, or UI was added.
- Added `AuthorizationDecision` with the contract-required explainability fields.
- Added the repository-only `findResourceByType()` catalog primitive.

## Validation

| Check | Result |
|---|---|
| `vendor/bin/phpunit tests/unit/P1Auth002AuthorizationServiceTest.php --no-coverage` | PASS, 8 tests, 16 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS, 127 tests, 1,896 assertions |
| `vendor/bin/phpstan analyse --no-progress --level=8` | PASS |
| `architecture-guard.php` | PASS |
| `validate-context-cache.php` | PASS |
| `validate-ledger.php` | PASS |
| `git diff --check` | PASS |

No authorization decision is persisted and no database schema was changed.
