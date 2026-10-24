# M4.2B — Two-writer Numbering CONFIGURATION concurrency probe

**Milestone entry:** 2026-10-24. This is an **incremental acceptance-tools-only** change on top of M4.2A.1. The operator reported `PASS_LOCAL_M4_SANDBOX` for the prior 14 M4 routes (14/14 HTTP, 28/28 unauthenticated, 7/7 CSRF, 6/6 write gate). The M4.2B race runner still requires a **separate XAMPP/MariaDB run**. No success on MariaDB is claimed in the packaging environment.

## What is (and is not) covered

The six M4 Numbering endpoints currently implement **Numbering Series configuration** (list, dry-run validation, create, show, update, state). They do **not** expose an issue/allocate-next-number endpoint; the presence of `auto_numbering_counters` in the schema does not mean allocation has been implemented. Consequently this suite verifies a *two-writer configuration CREATE race*, **not** sequence issuance concurrency.

A dedicated `RUN M4-CONCURRENCY` command creates a new synthetic, nonce-owned local SQL sandbox and starts **two separate PHP built-in HTTP servers**, each with an independent authenticated Shield session. Both POST the **same** active/default series configuration to the same resource while a third, separately attested MariaDB connection holds that resource row `FOR UPDATE`.

A passing run requires:

1. Both authenticated clients are ready independently, and both requests reach the M4-only router **after database nonce attestation**.
2. `information_schema.INNODB_TRX` joined with `PROCESSLIST`, scoped to the generated sandbox, shows **two distinct connections** in an InnoDB `LOCK WAIT` running `FOR UPDATE` while the parent holds its lock. A mere pair of HTTP requests in flight **does not** qualify.
3. Only after the two lock waits are observed does the parent release the resource lock. Exactly one response must be `201 Created`, and the other `409 NUM_SERIES_DUPLICATE`.
4. Post-race SQL fingerprints show **only** `auto_numbering_series` changed, with exactly one matching series, one active default and zero allocated counters. No test mutation may reach the template schema.
5. On PASS, only the exact newly-created nonce-owned database is dropped; on failure it is retained with a JSON report and private logs for root-cause analysis. The test exits nonzero on incomplete evidence.

`M4.2B` intentionally does **not** re-run or broaden `RUN INTEGRATION` (29 M3 operations) or `RUN M4-INTEGRATION` (14 M4 routes). It does **not** prove independent v1.2.4 baseline HTTP parity; that needs another isolated two-backend comparison. It does **not** authorize enabling production writes.

## Safety and execution

- No production or baseline business rows are read or copied. The source template is restricted to `information_schema` and `SHOW CREATE TABLE`, with M4 schema checks before any new database is created.
- Writable database name is unguessable `erp_m34b_<nonce>_sandbox`, with a strict guard-row nonce check across the parent fixture, lock transaction, router and CI4 connection groups. Database host is loopback only.
- Exactly two child PHP servers are spawned with `DINKERP_M33B_WRITE_MODE=enabled`, **in filtered child environments only**. The parent's default and the existing M3/M4 runner semantics are unchanged.
- Synthetic credentials are in private, one-use `race_<role>_config.json` files inside `backend/writable/m42/run_<nonce>`, removed by the worker before HTTP requests. Never post unredacted child stderr logs.
- The race-router arrival markers can only be written after the existing nonce-attestation and only to that exact private directory, for a POST Numbering Series URL.
- Metadata queries for `INNODB_TRX`/`PROCESSLIST` need suitable local MariaDB privileges. An unavailable/incomplete lock-wait observation is a **FAIL/NEEDS_FIX_OR_VERIFY**, never a pass with assumptions.
- Failure retains a disposable sandbox; do not manually drop any schema unless you have verified the nonce guard and its exact name.

## Windows XAMPP commands

From `D:\XAMPP\htdocs\erp` **after M4.2A.1**:

```powershell
# Place M4.2B-two-writer-numbering-race.patch in this directory.
git apply --check .\M4.2B-two-writer-numbering-race.patch
git apply .\M4.2B-two-writer-numbering-race.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN M4-CONCURRENCY
```

`RUN M4-CONCURRENCY` is opt-in and creates/mutates/drops **only a new local sandbox**. Check MySQL service and local credentials first. Optional CLI `php tools/m42b_run.php --offline` runs no DB; `php tools/m42b_run.php --keep` preserves a passing disposable sandbox for inspection.

Reports: `backend/writable/m42/reports/m42b_*.json` (distinct from `m42_*.json`). A PASS prints `PASS_LOCAL_M4_TWO_WRITER`, including lock wait attestation. On failure, use `FIRST_FAIL_PHASE` and share sanitized JSON `integration.failed`, not child logs containing environment details.

## Release decision

- Source/offline checks after this patch: must be executed; does not replace actual MariaDB run.
- M4.2B local two-writer configuration test: **NOT_TESTED until XAMPP run**.
- Number issuance concurrency: **NOT_IMPLEMENTED_NOT_TESTED**.
- Independent baseline v1.2.4 HTTP parity: **NOT_TESTED**.
- Production write mode: **BLOCKED**; keep `DINKERP_M33B_WRITE_MODE=off` outside the child sandbox.
