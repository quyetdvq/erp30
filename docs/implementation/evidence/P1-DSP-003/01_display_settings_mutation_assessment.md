# P1-DSP-003 Assessment

Status: DONE
Task: P1-DSP-003 - Display Settings mutation service + Authorization + audit integration
Risk/impact/profile: HIGH / backend-security / high-risk
Dependencies: P1-DSP-002 DONE; P1-AUTH-002 DONE
Authority cache: VALID

## Scope and implementation

The task reuses the existing `DisplaySettingRegistry`, `DisplaySettingsContext`,
`CodeIgniterDisplaySettingsStorage`, and `DisplaySettingsResolver`. The
canonical Organization scope registry now includes `display_setting` as an
`ORGANIZATION` resource resolver. The idempotent Resource/Action seed adds
exactly `display_setting.view` and `display_setting.edit`.

`DisplaySettingsMutationService` validates the registry key/type/value and
canonical Organization target before calling `AuthorizationService`. `system`
derives the single persisted GROUP root; `organization:<id>` derives the
target Organization. No client ancestry, Primary Assignment fallback, USER or
COMPANY pseudo-scope is accepted.

SET and RESET batches validate before one transaction. Only actual persisted
override changes are written. RESET of a missing override and SET of an
identical canonical value are no-ops. The approved forward-only
`configuration_audit_events` migration/repository stores one immutable event
per change with canonical context/key, operation, actor, request correlation,
UTC timestamp, and canonical JSON old/new override values. Settings and audit
inserts share the same connection/transaction; audit failure rolls back every
Settings row. No Display Settings event is written to `authorization_logs`.

## Focused behavior evidence

- Seed idempotency/catalog: `P1Seed001ResourceActionSeedTest` (21 resources,
  94 actions; two runs identical).
- System/GROUP and Organization authorization context, missing target,
  missing base permission, DENY, and ABSOLUTE_DENY:
  `P1Dsp003DisplaySettingsMutationServiceTest`.
- SET/RESET inheritance, canonical scalar JSON, actor/request/UTC event
  fields, idempotent missing RESET, and `authorization_logs` unchanged:
  `P1Dsp003DisplaySettingsMutationServiceTest`.
- Audit failure rollback of the complete SET batch:
  `testAuditFailureRollsBackEverySettingsOverrideInTheBatch`.
- Approved migration shape: `P1Db003ConfigurationAuditEventsTest`.
- Resolver precedence, sibling isolation, stale/unknown key exclusion, and
  Business Timezone isolation remain covered by P1-DSP-002 tests.

## Validation

| Check | Result |
|---|---|
| `tests/unit/P1Dsp003DisplaySettingsMutationServiceTest.php --no-coverage` | PASS; 5 tests, 43 assertions |
| P1-DSP-001/002, P1-DSP-003, P1-AUTH-002, mandatory Authorization, seed, and DB regressions | PASS; 46 tests, 678 assertions |
| `tests/database/P1Db003ConfigurationAuditEventsTest.php --no-coverage` | PASS; 1 test, 2 assertions |
| Full backend PHPUnit `--no-coverage` | PASS; 426 tests, 3817 assertions |
| PHPStan level 8 (`app`) | PASS; no errors |
| Architecture guard | PASS; 45 application files checked |
| Context cache validator | PASS; 8 authorities, 107 tasks, acyclic graph |
| Ledger validator (pre-closure) | PASS; 81 DONE tasks |
| `git diff --check` | PASS |
| Spark migration CLI | NOT RUN; repository baseline `app/Config/Paths.php` lacks the framework `SUPPORTPATH` constant and `php spark migrate:status` fails before command dispatch; PHPUnit migration bootstrap and the approved schema test passed against `erp_test`. |

## Closure boundary

Changed files are limited to the Display Settings authorization registry,
Settings adapter, seed, mutation/audit service/repository, forward migration,
focused tests, this assessment, and the execution ledger. Revised authority
files supplied in the worktree remain untracked and unstaged. No controller,
route, API/OpenAPI, frontend, or P1-DSP-004 work was started.

The completion commit is recorded in the ledger using the repository's
pre-commit `0000000` convention; the actual post-commit Git hash is
authoritative after commit.
