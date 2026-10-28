# M4.2A — Isolated M4 HTTP/MariaDB acceptance runner

**Status at packaging:** Source + offline regression only. Full M4 local HTTP/MariaDB results are **NOT SUPPLIED**. Independent v1.2.4 baseline live parity, two-writer concurrency and production enablement remain **BLOCKED/NOT_TESTED**.

**Prerequisite:** M4.1 applied. This is an **incremental** acceptance-tools-only patch: no public routes, runtime service, migration, Frontend, or baseline copy changed.

## Why a separate suite

`RUN INTEGRATION` continues to test **29 M3.4B mutations**, not the new M4 APIs. This patch introduces **`RUN M4-INTEGRATION`**, a deliberately separate opt-in command. Do not interpret an older M3.4D `LOCAL_INTEGRATION_ACCEPTED...` marker as M4 approval.

## Sandbox protections

- Inspects `information_schema` and `SHOW CREATE TABLE` metadata only from an approved local schema template. No source business rows are selected/copied.
- Refuses incomplete M4 schemas (Settings storage, immutable audit, Numbering series/counters). Default template is `erp_test`, with existing hard-coded metadata-only `erp` fallback if the default is incomplete; never uses `erp` as a mutation target.
- Creates a random `erp_m34b_<nonce>_sandbox` schema with a database guard row; all fixture SQL re-attests schema and nonce. All child CI4 servers use loopback and pin both `database.default` and `database.tests` to this sandbox.
- Creates synthetic Shield identity, GROUP root, PBAC permissions, Numbering Resource and a two-State Organization Lifecycle. No real user/account/product data is copied.
- First starts a separate sandbox HTTP server with `DINKERP_M33B_WRITE_MODE=off`; then restarts it with `enabled` **only in the filtered child environment**.
- Deletes only its own nonce-owned schema on complete PASS, retains on error for inspection. Use `--keep` to preserve the database on PASS. The parent process never enables production writes.

## Test gates on actual local MariaDB (once run by operator)

| Coverage | Expected |
|---|---|
| 14 exact M4 endpoints, authenticated | 14/14; per-route JSON + SQL fingerprint assertions |
| All 14 endpoints unauthenticated, vi/en | 28/28; 401/403 structured errors |
| All 7 M4 POST/PATCH endpoints with invalid CSRF | 7/7; 403, zero business-table changes |
| 6 M4 write routes with write gate OFF | 6/6; 403 `ACCESS_MUTATION_NOT_ENABLED`, zero business-table changes |
| Settings | 15 registry definitions, read/SET/RESET, audit changes in same transaction, forced SQL audit-trigger rollback |
| Numbering | Read, dry-run POST (zero changes), create, patch, state change; duplicate code/pattern/default, immutable issued fields |
| Workflow | Lifecycle states/options/action on a real synthetic organization row; `ABSOLUTE_DENY`, forced SQL rollback, stale action rejection |

This is **not** baseline-live HTTP parity (no second baseline server) and **not** two-writer concurrency (single test server). These gates remain OPEN even if the new suite reports `PASS_LOCAL_M4_SANDBOX`.

## Operator instructions (Windows XAMPP)

From `D:\XAMPP\htdocs\erp` apply **only the new M4.2A patch** on top of M4.1:

```powershell
 git apply --check .\M4.2A-local-http-mariadb-runner.patch
 git apply .\M4.2A-local-http-mariadb-runner.patch
 cd backend
 .\tools\test-unit.ps1 CHECK
 .\tools\test-unit.ps1 RUN
 .\tools\test-unit.ps1 RUN ACCEPTANCE
 .\tools\test-unit.ps1 RUN M4-INTEGRATION
```

`RUN M4-INTEGRATION` creates and mutates a disposable **local** MariaDB schema. Check DB privileges and target settings before running. Runner uses the existing `M34B_MYSQL_USER`, `M34B_MYSQL_PASSWORD`, `M34B_MYSQL_PORT`, and optional `M34B_SOURCE_SCHEMA` env vars; do not supply production credentials or direct it at external hosts. The `erp` fallback only reads schema metadata; it does not read customer rows.

Alternatively, `php tools/m42_run.php --offline` tests source-only gates, creates no MariaDB schema; `php tools/m42_run.php --keep` retains a passing sandbox for inspection.

Output: `backend/writable/m42/reports/m42_*.json`. Include the **exact** report file and sanitized final output in the handoff. On error, a disposable database may be intentionally left for investigation; clean it up manually only after verifying the nonce guard, and never drop another database.

## Evidence and release decision

The author's local environment has PHP but does **not** include CI4 `vendor/`, MySQL/MariaDB client/server, or `pdo_mysql`; therefore this package includes **no claimed successful M4 HTTP/SQL run**. Source `php -l`, `php tools/test-unit-check.php`, `php tools/m34b_run.php --offline`, `php tools/m34d_accept.php --offline`, `php tools/m4_accept.php`, `php tools/m42_run.php --offline` must PASS before handoff. A PASS on the operator's local M4 suite supports only local sandbox gate closure; a future two-writer concurrency run and an independent frozen-baseline comparison are still required for full M4 acceptance.

Keep `DINKERP_M33B_WRITE_MODE=off` outside the spawned disposable test processes.

## M4.2A.1 bootstrap correction (2026-10-24)

**Observed operator result:** Source/offline gates 55/55 PASS; M4 local integration returned
`0/14`, `0/28`, `0/7`, `0/6`, `NEEDS_FIX_OR_VERIFY`. Without the operator's
`m42_*.json` report, this output does not identify the exact runtime exception.

**Deterministic source defect:** M4.2A reused the M3.4B `child_spark.php`,
`router.php`, and `child_web.php`. Their strict M3 bootstrap requires
`DINKERP_M33B_WRITE_MODE=enabled` and an isolated `writable/m34b/run_<nonce>`
environment directory. The M4.2A runner first starts the server with write mode
`off`, and creates only `writable/m42/run_<nonce>`; either constraint prevents the
M4 HTTP gate-off phase from bootstrapping successfully.

**Minimal remedy:** New, *M4-only* `tools/m42/{M42ChildPaths.php,child_spark.php,
child_web.php,router.php}`. These recognize `off` and `enabled` only in the child
process, still require an exact nonce-owned schema and the per-request SQL guard
row, pin both CI4 DB connection groups to localhost sandbox, and use the empty
`writable/m42/run_<nonce>` for CI4 DotEnv bootstrap. The original M3.4B router,
child paths, CLI attestation command, public API runtime, database schema, and
`DINKERP_M33B_WRITE_MODE=off` default are untouched. CLI attestation remains
strictly `enabled`; the M4 router itself independently re-attests every HTTP
request in either mode.

Apply the incremental `M4.2A.1-off-mode-bootstrap-fix.patch` **only after M4.2A**.
Run `CHECK`, `RUN` and `RUN M4-INTEGRATION` again. `55/55` offline and `41` M4
safety assertions were verified in the author environment, but the full M4
HTTP/MariaDB pass remains **NOT TESTED** until a successful local operator run.
The runner now outputs `FIRST_FAIL_PHASE` on a failing probe; the exact JSON
report contains the sanitized failure reason. Do not send raw HTTP stderr logs
which could include environment details or test identity credentials.

Independent frozen-baseline parity, two-writer concurrency, and production
write remain **BLOCKED/NOT_TESTED**, even after local PASS.
