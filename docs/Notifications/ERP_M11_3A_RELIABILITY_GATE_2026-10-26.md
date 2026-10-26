# M11.3A — In-app Worker Reliability & Automated QA Gate

**Scope:** Testing gate prior to original M11.3 Channels (Email/Push). No new
transport or channel has been enabled. Current M11.2 acceptance: 26/26 Unit,
14/14 MariaDB Integration, 145 total assertions on user's Windows/XAMPP DEV.

## Decision

A single outer transaction rollback cannot prove 2-worker contention:
independent MariaDB connections cannot observe uncommitted fixtures. Preserve
M11.2's rollback-only `RUN M11` unchanged and isolate genuine committed-
fixture testing in opt-in `RUN M11-RELIABILITY`.

## Test acceptance matrix

| ID | Real test | Acceptance |
|---|---|---|
| REL-01 | Two PHP processes / independent MariaDB connections | Exactly one claim, terminal processing once; 0 Inbox for invalid synthetic event |
| REL-02 | Expired lease | Recovered on next attempt; stale token conditional update affects 0 rows |
| REL-03 | Expired lease at attempts=6 | DEAD, no seventh attempt, reason LEASE_EXHAUSTED |
| REL-04 | Delivery disabled before claim | Event stays PENDING; attempts unchanged |
| REL-05 | Fault after Inbox insert, before ACK | Rollback Inbox and ACK; 1-minute RETRY then successful single Inbox |
| REL-06 | Permanent fault after Inbox insert | Rollback Inbox, DEAD with explicit reason |
| REL-07 | Unit truth table | Retry schedule 1,5,15,60,240 and DEAD at 6 attempts |

## Security and operations

- Only `database.tests=erp_test` with `erp_test_runner`, `127.0.0.1:3306`,
  MySQLi and `dinktech_`; `M11DatabaseGuard` validates current DB/account.
- No DDL and no migration/seed; PHPUnit test fixture uses commits *only*
  in this opt-in suite, then targeted DELETE in finally/teardown.
- A crash/force-kill before teardown may strand **test-only** event rows.
  Do not run `DELETE` without checking exact IDs and event keys.
- `RUN M11` remains the stable rollback-only regression command; run the
  reliability gate separately and serially.
- Test fault injection is constructor-only and forbidden outside the CI4
  testing environment. Never expose it through the Worker CLI or API.

## Test execution

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\ERP_M11_3A_Worker_Reliability_PHPUnit.patch
git apply .\ERP_M11_3A_Worker_Reliability_PHPUnit.patch
git diff --check

cd .\backend
.\tools\test-unit.ps1 CHECK M11-RELIABILITY
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
```

**Release status:** Tests are implemented, but the 2-process MariaDB gate
requires the user's actual `erp_test` runtime and is NOT considered PASS
until `RUN M11-RELIABILITY` succeeds. Browser HTTP/CSRF and Channels remain
outside this patch.
