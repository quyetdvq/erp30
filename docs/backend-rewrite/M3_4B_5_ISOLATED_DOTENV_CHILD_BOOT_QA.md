# M3.4B.5 — Isolated CI4 DotEnv Bootstrap Hotfix

## Reported Windows failure

- `CHECK` PASS.
- `RUN` PASS 42/42 offline suites.
- `RUN INTEGRATION` created an independently named, prefixed MariaDB sandbox.
- Spark child attestation returned `DB_CONFIG` before any HTTP mutation; the sanitized child report said `CI4 resolved a database group different from the disposable sandbox`.

## Verified root cause in CI4 4.7.5

The `proc_open()` child received `database.default.*` and `database.tests.*` as environment variables; the earlier M3.4B.4 pure test already verified propagation to another PHP process. However, CodeIgniter `DotEnv::setVariable()` can add values from the existing `backend/.env` into `$_ENV` and `$_SERVER`, even if the OS process environment was already set. `BaseConfig::getEnvValue()` prefers those superglobals over `getenv()`. As a result, a child can pass the raw `getenv()` guard but resolve the existing ERP database configuration through CI4.

The bug was a **test-harness configuration isolation failure**, not evidence that any of the 29 mutation API operations failed.

Source references: CI4 4.7.5 `system/Config/DotEnv.php`, `system/Config/BaseConfig.php`, `system/Boot.php`, and `system/Database/Config.php`.

## M3.4B.5 changes

1. The child Spark process now launches through `backend/tools/m34b/child_spark.php`; the loopback HTTP router launches `backend/tools/m34b/child_web.php`.
2. Both use `M34BChildPaths::forSandbox()` to set `Config\Paths::envDirectory` to the **empty, nonce-owned** `backend/writable/m34b/run_<16 hex>/` directory **before** CodeIgniter Boot loads DotEnv.
3. Both sandbox processes receive the same explicit DB environment. The real `backend/.env` is never moved, overwritten, deleted, or opened by this bootstrap.
4. Sandbox bootstrap rejects production/non-loopback hosts, wrong DB names, conflicting `default`/`tests` DB settings, port/prefix mismatch, wrong nonce, wrong child environment, or a `.env` file injected into the ephemeral directory.
5. The existing Spark attestation still checks actual `SELECT DATABASE()`, `DBPrefix`, and the MariaDB nonce marker. HTTP router still checks the marker on **every** request before CI4 is bootstrapped.
6. Spark attestation now consults the same cached `config(Database::class)` that CI4 `Database::connect()` consumes, not an unrelated `new Database()` instance.
7. Diagnostic codes now distinguish `DB_GROUP` and `DB_SCHEMA`, while maintaining compatibility with the earlier `DB_CONFIG` classification.

**No application API route, PBAC decision engine, Frontend file, baseline file, production `.env`, or runtime `Config/Database.php` has been changed.** Normal `backend/spark` and `backend/public/index.php` remain unchanged. The sandbox child entrypoints are internal only.

## Installation on existing M3.4B.4

Extract the M3.4B.5 Overlay ZIP at `D:\XAMPP\htdocs\`, where the ZIP contains the `erp/` directory. No new JSON, MySQL users, local ports, or manual DB migrations are needed beyond M3.4B's existing prerequisites.

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN INTEGRATION
```

Expected `CHECK` count: **43** pure/fixture suites. The first two commands must PASS without MariaDB/HTTP mutations. The third command creates a **new** sandbox with a fresh nonce and attempts the same integration gate.

If `INTEGRATION` fails, send only the newly generated JSON report plus:

```powershell
Get-ChildItem .\writable\m34b\run_*\child_attestation.json |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1 |
    Get-Content -Raw
```

Do not provide `.env`, passwords, raw logs or secrets. Sanitized attestation reports contain only a code, exit code, and advice.

## Quality gates / limits

- New child-bootstrap assertions: **29/29 PASS offline**.
- Existing M3.4B through M3.4B.4 pure/fixture regressions plus new suite: **43/43 PASS offline**.
- Existing contract inventory: **29/29 mutation operations pinned**.
- Real XAMPP CI4 + MariaDB HTTP mutation, Shield session, CSRF, and rollback: **NOT YET VERIFIED** on this platform.
- Independent baseline-v1.2.4 live HTTP parity: **NOT TESTED**; remains a separate acceptance gate.
- Production write mode remains `DINKERP_M33B_WRITE_MODE=off`.

Retained previous `erp_m34b_*_sandbox` schemas are deliberately **not** dropped automatically by this patch. The runner only drops its **own**, newly generated sandbox after full integration PASS and attested ownership.

Next planned milestone after real integration acceptance: M3.4C Module Boundary Refactor (Identity/Workflow extraction from Access), not additional API expansion.
