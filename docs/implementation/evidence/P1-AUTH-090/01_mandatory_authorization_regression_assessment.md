# P1-AUTH-090 Gate 3 Mandatory Authorization Regression

Task context was resolved with `task-context.php P1-AUTH-090`; the authority
cache was `VALID`, dependencies P1-AUTH-002..005 were `DONE`, and the task was
`READY` under the HIGH-risk backend-security profile. Direct authority checks
were limited to FRS-P1-PBAC-001..007, Contract sections 6 and 18, and the
Implementation Plan P1-AUTH-090 pointer.

The durable release guard is `backend/tests/unit/P1AuthorizationMandatoryRegressionTest.php`.
It contains behavior-level assertions for base permission, per-lane
precedence, denied-set intersection, GROUP/ancestor policy and assignment
inheritance, secondary assignments, Company and sibling isolation, state
matching, authoritative Evaluation Organization, group-level security
resources, System Admin no-bypass, decision evidence, audit insert-only
behavior, fail-closed persistence, and the WF-002 authorization boundary.

The existing committed P1-AUTH-005 test remains the owner of the real two
independent-connection Group-permission concurrency worker regression and was
run with this mandatory suite. Existing AUTH-002..005, EffectiveProfile, and
WF-002 tests were also run in the same focused gate. No production defect was
exposed, so no file under `backend/app/` changed.

Validation evidence:

| Check | Result |
| --- | --- |
| Mandatory suite | PASS, 9 tests, 68 assertions |
| Focused Gate 3 group | PASS, 52 tests, 262 assertions |
| Full backend PHPUnit `--no-coverage` | PASS, 169 tests, 2,150 assertions |
| PHPStan level 8 | PASS, no errors |
| Architecture guard | PASS |
| Authority context-cache validator | PASS |
| Ledger validator (pre-closeout) | PASS |
| `git diff --check` | PASS |

Contract result: Base Permission remains mandatory; NO_MATCH defaults to
ALLOW; ordinary lane precedence and ABSOLUTE_DENY propagation are exercised;
System Admin follows the same profile and policy path; client context hints
cannot override persisted/canonical context; group-level resources resolve at
GROUP; state NULL and state-specific policies are distinguished; decision
evidence remains deterministic and non-persisted; audited actions remain
insert-only and trusted; infrastructure/context failures remain failures; and
WF-002 validates transition semantics before authorization and mutation.

Status: DONE. Gate 3 authorization evidence is complete for P1-AUTH-090.
