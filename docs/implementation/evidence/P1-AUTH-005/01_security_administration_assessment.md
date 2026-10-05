# P1-AUTH-005 Security Administration Assessment

## Scope

HIGH-risk `P1-AUTH-005` only. The task depends on completed `P1-AUTH-002`
and uses the validated authority cache (`AUTHORITY_CACHE: VALID`). The
implementation is limited to Authorization Group and Policy administration
services plus the existing Shield identity adapter mutation boundary.

## Implemented controls

- `AuthorizationGroupRepository` provides typed group lookup, audit-safe group
  persistence, Resource Action lookup, Shield-user existence checks, and atomic
  group permission replacement with a locked Group row.
- `AuthorizationGroupService` authorizes `create`, `edit`, `setstate`,
  `assign_member`, and `assign_permission` before mutation. Membership and
  direct User Permission writes use Shield's canonical tables through
  `ShieldIdentityAdapter`.
- `PolicyAdministrationService` authorizes Policy create/edit/delete before
  reference validation/mutation and delegates Policy + Policy Action atomic
  writes to `PolicyRepository`/`PolicyValidator`.
- `PolicyRepository::deleteWithActions()` locks the Policy row and deletes
  Actions and Policy atomically.
- Caller audit fields are discarded; trusted `OperationContext` values stamp
  mutations. No System Admin bypass, ROLE runtime write, local permission
  table, Profile table, Resource Instance Authorization, or final ALLOW/DENY
  algorithm was added.

## Evidence

- Focused `P1Auth005SecurityAdministrationTest`: PASS, 5 tests / 20 assertions.
- AUTH-001..004 and IAM regression filter: PASS, 46 tests / 151 assertions.
- Full backend PHPUnit `--no-coverage`: PASS, 140 tests / 1,966 assertions.
- PHPStan level 8: PASS.
- Architecture guard: PASS.
- Context-cache validator: PASS.
- Ledger validator: PASS.
- `git diff --check`: PASS.

The first full-suite attempt was run concurrently with the focused suite and
corrupted the shared `erp_test` migration state; only the configured test
database was cleared and rebuilt before the successful sequential runs.

## Out of scope

No public endpoint, controller/API/UI work, authorization regression task,
workflow task, catalog administration, or participant lane/inheritance logic
was implemented.

## Corrective regression evidence

Corrective verification was limited to P1-AUTH-005. Production code was not
changed: all new regressions passed against primary commit `15b593d`.

- Added a real two-process MariaDB concurrency regression. Each worker opens an
  independent `tests` connection and invokes the normal
  `AuthorizationGroupService::replacePermissions()` path. The final matrix is
  exactly one complete batch outcome; no mixed/interleaved matrix is committed.
- Added explicit zero-mutation coverage for missing Base Permission, PBAC
  `DENY`, and `ABSOLUTE_DENY` across group, membership, group-permission,
  direct-user-permission, and Policy administration.
- Added negative sensitive-action separation coverage for edit, membership,
  group permission, and direct user permission capabilities.
- Added EffectiveProfile consistency coverage for group, direct, mixed-source,
  removal, and inactive-group behavior.
- Added SYSTEM_ADMIN evidence through the same authorization path, including
  ordinary denial, absolute denial, and missing sensitive action.

The corrective test suite found no production defect and introduced no cache,
permission, Profile, API, UI, workflow, or authorization-decision logic.

| Corrective check | Result |
|---|---|
| `vendor/bin/phpunit tests/unit/P1Auth005SecurityAdministrationTest.php --no-coverage` | PASS, 11 tests, 77 assertions |
| `vendor/bin/phpunit tests/unit/P1Auth002AuthorizationServiceTest.php tests/unit/P1Auth003AuthorizationDecisionTest.php tests/unit/P1Auth004AuthorizationAuditTest.php tests/unit/P1Iam001ShieldIdentityAdapterTest.php tests/unit/P1Iam002EffectiveProfileResolverTest.php --no-coverage` | PASS, 32 tests, 109 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS, 146 tests, 2,023 assertions |
| `vendor/bin/phpstan analyse --configuration phpstan.neon --level=8 --memory-limit=1G --no-progress` | PASS |
| `architecture-guard.php` | PASS |
| `validate-context-cache.php` | PASS |
| `validate-ledger.php` | PASS before corrective ledger append |
| `git diff --check` | PASS |
