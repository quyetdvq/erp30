# P1-SYS-007 - Approved Parameter Implementation and Recheck

Date: 2026-10-09  
Task: `P1-SYS-007`  
Approval input: user-approved decision table in the current task

## Implemented and verified repository behavior

| Parameter | Implementation / evidence | Status |
|---|---|---|
| Product Import | `Config\\Import` defaults to 10 MiB (`10485760`) and 5,000 rows; exact-boundary and overflow error-code tests pass. | PASS |
| Lock/retry | `MariaDbDomainLockService` defaults to 5 seconds and validates retry count `0..1`; the approved default is 0 and the regression test passes. | PASS |
| Business timezone | GROUP fallback is `Asia/Ho_Chi_Minh`; Company context reads canonical `App.businessTimezone`; application/database defaults remain UTC. | PASS |
| Retention | 365-day target is recorded; no purge job or destructive retention mechanism was added. | DECISION RECORDED; OPERATIONAL EVIDENCE PENDING |
| CSRF | `/api/v1/auth/csrf`, rotated `X-CSRF-TOKEN`, mutation interceptor, and logout/login refresh are implemented. Targeted Chromium lifecycle test passes. | CODE PASS; LIVE AUTHENTICATED EVIDENCE PENDING |
| SYSTEM_ADMIN | `erp_test` has migration `000013`, one `SYSTEM_ADMIN` group, `user.reset_password`, and exactly 93 permissions. | TEST BASELINE PASS |

The reconciliation command now explicitly connects to the `tests` group for
testing-mode maintenance, preventing an accidental connection to development
`erp`. The command remains guarded by `erp_test`/explicit staging target,
backup checksum, and explicit approval; no production or development write was
performed.

## Remaining release evidence

SYS-007 is **BLOCKED**, not `DONE`, because the approved parameters do not
substitute for deployment evidence:

- PHP `display_startup_errors=0`, UTC host timezone, OPcache posture, and TLS
  verification still require production/staging evidence.
- Retention ownership, access controls, and restore/expiry rehearsal are not
  complete; purge remains intentionally disabled.
- CSRF needs a real HTTPS authenticated browser journey covering bootstrap,
  login, missing/invalid token rejection, mutation, rotation, and logout.
- The reviewed `SYSTEM_ADMIN` correction still requires the approved backup,
  forward migration path and exact-set reconciliation evidence in the target
  deployment environment. The isolated `erp_test` baseline is verified but is
  not production approval.
- Decision register entries must be reconciled with the latest HTTPS/CORS/DSN
  recheck, including the distinction between code/runtime PASS and deployment
  sign-off.

**Outcome: BLOCKED / NOT RELEASE READY.**
