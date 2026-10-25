# M6.3C — Avatar concurrency & failure recovery QA (DEV-only)

**Prepared:** 2026-10-25 (Asia/Bangkok). **Based on:** ERP v1.4.0 + M6.3B.2 QA Consolidation + Snapshot CRLF hotfix. **Change type:** test tooling and documentation only. **Public API footprint:** unchanged, **129/150**. No changes to `UserAvatarService`, `PublicImageStorage`, Frontend, frozen OpenAPI, database schema or baseline backend.

## Starting evidence

- Operator confirmed M6.3B.2 `PASS_DEV_ADMIN_AVATAR_HTTP_FS_SQL` on XAMPP, connected to `erp`, admin `quyetdv` (ID 1), target `toandv` (ID 3). Report `m63b2_dev_20261024_174647_d6676347.json`.
- Final SELECT-only `m63b1:db-audit 3 EMPTY` passed. User 4 has existing avatar metadata; **do not** reset/overwrite User 4.
- Fingerprint snapshots of local Frontend and Backend baseline were **waived by operator** for this QA phase; do not report them as passed or mutate the local files to match a pinned snapshot.

## What was added

1. `backend/tools/m63c_dev_avatar_race.php`: plan (no DB/HTTP), preflight (reuses reviewed M6.3B.2 SQL+authenticated GET), explicit `--apply` (two independent authenticated PHP clients POST to the **same** pinned Avatar endpoint via `https://127.0.0.1`, Host `api.monmin.net`).
2. `backend/tools/m63c/M63CAvatarRace.php`: uses a **third, separately attested PDO/MySQL connection** to hold a `SELECT ... FOR UPDATE` lock on the already-empty target row. Both workers must be seen in `information_schema.INNODB_TRX` with `LOCK WAIT`, before parent lock release. Then it requires exactly **one HTTP 200 and one HTTP 409** with `USER_AVATAR_CONCURRENT_UPDATE`.
3. `backend/tools/m63c/M63CRaceWorker.php`: independent Shield sessions and CSRF with per-process sample PNG; no direct SQL mutations, no arbitrary URL, no credentials on disk or argv.
4. `backend/tools/m63c/M63CRacePlan.php`: strict DB pin, mode validation and winner/loser evidence. Cleanup is allowed only for a validated run-owned winner, after SQL points exactly at that filename, via authorized Avatar DELETE; arbitrary files and user profiles are never reset.
5. Three new offline suites: race safety, `UserAvatarService` fault injection without DB/GD, and actual `PublicImageStorage` filesystem/SQL fixture when GD is available (on environments without GD, GD-dependent cases explicitly print `SKIPPED_UNAVAILABLE`).

The runner does **not** create database users or alter PBAC. The operator must choose an already-authorized disposable DEV account. No production mode is accepted. If an HTTP request is interrupted or an orphan cannot be identified safely, report **failure + manual review**, not automatic destructive cleanup. Retained `writable/m63c/run_<nonce>/` files contain only status markers and sanitized worker diagnostics; no plaintext credentials are written.

## Offline/source checks (run first)

From `D:\XAMPP\htdocs\erp\backend`:

```powershell
php tests\m63c_avatar_race_safety_pure.php
php tests\m63c_avatar_service_fault_fixture.php
php tests\m63c_avatar_failure_fixture.php
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tools\m63c_dev_avatar_race.php --target-user-id=3
```

The final command must say `PLAN ONLY`. On PHP with fully working GD codecs, the real-storage fixture should say `GD_RUNTIME=EXECUTED`. If it says `SKIPPED_UNAVAILABLE`, physical storage fault behavior is **not yet accepted** on that host.

## XAMPP read-only preflight and explicitly opted-in race

Prerequisites: backup the approved DEV database and `backend/public/upload`, ensure no other writer is changing User 3's avatar, ensure Apache serves backend on local HTTPS loopback, PHP CLI supports `pdo_mysql` and GD (JPG/PNG/WebP). The MySQL account must be able to inspect `information_schema.INNODB_TRX` and `PROCESSLIST`. Database `erp` was explicitly approved by the operator as DEV; it is **not** generally safe to use an arbitrary database with that name. **Never** copy these variables to a production service environment.

Execute the PowerShell block below with `$executeRace = $false` first. It runs SELECT/login/GET preflight **without Avatar POST/DELETE** and clears all plaintext environment secrets in `finally`. Only after PASS and confirming the backup, set `$executeRace = $true` and run the **same block again**; this triggers two independent authorized HTTP Avatar POSTs. The runtime runner does not create/modify users or PBAC fixtures.

```powershell
cd D:\XAMPP\htdocs\erp\backend
$executeRace = $false  # Change to $true only after verifying backup and preflight
$oldCI = [Environment]::GetEnvironmentVariable('CI_ENVIRONMENT', 'Process')
try {
    $env:CI_ENVIRONMENT = 'development'
    $env:M63C_EXPECTED_DATABASE = 'erp'
    $env:M63C_ADMIN_USER = 'quyetdv'
    $env:M63C_TARGET_USER = 'toandv'

    php spark m63b1:db-audit 3 EMPTY
    if ($LASTEXITCODE -ne 0) { throw 'Avatar metadata is not empty. Do not write.' }

    $adminSecure = Read-Host 'DEV Admin password' -AsSecureString
    $targetSecure = Read-Host 'DEV Target password' -AsSecureString
    $env:M63C_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $adminSecure).Password
    $env:M63C_TARGET_PASSWORD = [System.Net.NetworkCredential]::new('', $targetSecure).Password

    php tools\m63c_dev_avatar_race.php --target-user-id=3 --preflight
    if ($LASTEXITCODE -ne 0) { throw 'Preflight not accepted; no write allowed.' }

    if ($executeRace) {
        $confirmation = Read-Host 'Type DEV_AVATAR_RACE_QA to authorize the two-writer test'
        if ($confirmation -cne 'DEV_AVATAR_RACE_QA') { throw 'QA write confirmation rejected.' }
        $env:M63C_MYSQL_USER = 'root'  # Change if your authorized local account differs
        $env:M63C_MYSQL_PORT = '3306' # Use your actual XAMPP MySQL port
        $mysqlSecure = Read-Host 'Local MySQL password (Enter if empty)' -AsSecureString
        $env:M63C_MYSQL_PASSWORD = [System.Net.NetworkCredential]::new('', $mysqlSecure).Password
        $env:M63C_DEV_QA_WRITE = 'YES'

        php tools\m63c_dev_avatar_race.php --target-user-id=3 --apply --confirm=DEV_AVATAR_RACE_QA
        if ($LASTEXITCODE -ne 0) { throw 'M6.3C did not PASS. Do not retry; inspect JSON and DB/FS.' }
        php spark m63b1:db-audit 3 EMPTY
        if ($LASTEXITCODE -ne 0) { throw 'Final metadata not empty. Investigate before retry.' }
    }
} finally {
    Remove-Item Env:\M63C_DEV_QA_WRITE,Env:\M63C_EXPECTED_DATABASE,Env:\M63C_MYSQL_USER,Env:\M63C_MYSQL_PASSWORD,Env:\M63C_MYSQL_PORT,Env:\M63C_ADMIN_USER,Env:\M63C_ADMIN_PASSWORD,Env:\M63C_TARGET_USER,Env:\M63C_TARGET_PASSWORD,Env:\M63B2_EXPECTED_DATABASE -ErrorAction SilentlyContinue
    Remove-Variable adminSecure,targetSecure,mysqlSecure,confirmation -ErrorAction SilentlyContinue
    if ($null -eq $oldCI) { Remove-Item Env:\CI_ENVIRONMENT -ErrorAction SilentlyContinue }
    else { $env:CI_ENVIRONMENT = $oldCI }
}
```

An empty `M63C_MYSQL_PASSWORD` is permitted for a local XAMPP account with no password; an explicit local MySQL username, database name, port and DEV write opt-in remain mandatory. The script is blocked in production, for mismatching environment labels, without the exact confirmation, or if the target is not empty. No passwords are stored in QA JSON or worker arguments.

### Required evidence for PASS

- Two separate successful authenticated worker sessions and both `requesting` markers.
- **Exactly two distinct InnoDB connections observed in `LOCK WAIT`** on avatar update before the parent lock is released (not merely two PHP processes started).
- API responses: one `200` success and one `409 USER_AVATAR_CONCURRENT_UPDATE`; neither an unqualified `409` nor a `500` can pass.
- SQL audit verifies that the winner filename is the only pointer; the winning file served by Apache exactly equals physical bytes (SHA-256 comparison).
- One winning image only added to the public Avatar inventory (no loser staged orphan); authorized DELETE clears the winner; final SQL metadata is `EMPTY`, physical file absent and static request `404`.
- JSON report: `backend/writable/m63c/reports/m63c_*.json` with `PASS_DEV_AVATAR_TWO_HTTP_WRITER_CAS` and `partial_state_review_required=false`.

If insufficient MariaDB permissions, single-request Apache serialization, database lock telemetry unavailability or a timeout prevent observing two waits, the run is **FAIL / not evidenced**, even if one or both HTTP requests completed. The script releases its held SQL lock in `finally`. On any failure, **do not immediately rerun**; inspect the JSON report, `m63b1:db-audit` and physical files first. Worker HTTP requests may continue briefly after client termination; the runner deliberately refuses to guess/delete unproven files.

## M6.3C.1 — Read-only MySQL attestation diagnostics (2026-10-25)

If `--apply` stops at `phase=attest_local_mysql`, **no Avatar POST has been dispatched**: it has not started the HTTP workers. The original M6.3C report used `QA_RUNTIME_FAILURE` for all exceptions and conservatively marked every `--apply` failure as a possible partial write. This hotfix replaces those with a safe `mysql_attest_step`, a fixed `failure_code`, and correct `partial_state_review_required=false` before the `race_go` HTTP barrier.

Do not repeat `--apply` to diagnose local MySQL. Instead run the new **SELECT-only** attestation using the same independent local PDO credentials; no Shield login, HTTP POST/DELETE, row-lock transaction, or schema change occurs:

```powershell
cd D:\XAMPP\htdocs\erp\backend
$oldCI = [Environment]::GetEnvironmentVariable('CI_ENVIRONMENT','Process')
try {
    $env:CI_ENVIRONMENT = 'development'
    $env:M63C_EXPECTED_DATABASE = 'erp'
    $env:M63C_MYSQL_USER = 'root'   # Use the actual permitted local MySQL account
    $env:M63C_MYSQL_PORT = '3306' # Confirm the real MariaDB/MySQL port
    $pass = Read-Host 'Local MySQL password (Enter if empty)' -AsSecureString
    $env:M63C_MYSQL_PASSWORD = [System.Net.NetworkCredential]::new('', $pass).Password
    php tools\m63c_dev_avatar_race.php --target-user-id=3 --mysql-check
} finally {
    Remove-Item Env:\M63C_EXPECTED_DATABASE,Env:\M63C_MYSQL_USER,Env:\M63C_MYSQL_PORT,Env:\M63C_MYSQL_PASSWORD -ErrorAction SilentlyContinue
    Remove-Variable pass -ErrorAction SilentlyContinue
    if ($null -eq $oldCI) { Remove-Item Env:\CI_ENVIRONMENT -ErrorAction SilentlyContinue }
    else { $env:CI_ENVIRONMENT = $oldCI }
}
```

A `PASS_DEV_AVATAR_MYSQL_ATTEST_READ_ONLY` only proves PDO connection, database selection, InnoDB `users`, empty avatar, local file inventory and that lock telemetry can be queried; it does **not** establish privilege to see other sessions' waits under load. Real two-writer `--apply` is still required for CAS acceptance.

| `failure_code` | Meaning / permitted next action |
|---|---|
| `MYSQL_CREDENTIALS_OR_PORT_INVALID` | Supply explicit local DB username and valid port; empty password is allowed |
| `PDO_MYSQL_DRIVER_MISSING` | Enable `pdo_mysql` in the PHP CLI installation used by XAMPP; CI4 `MySQLi` success alone does not prove PDO works |
| `MYSQL_AUTHENTICATION_DENIED` | Check independent MySQL user/password and TCP `127.0.0.1` grants; never reveal password in reports |
| `MYSQL_CONNECTION_UNAVAILABLE` | Verify local MySQL service and actual port; do not substitute a remote host |
| `MYSQL_DATABASE_NOT_FOUND` / `MYSQL_DATABASE_IDENTITY_FAILED` | Confirm explicit DEV database pin without creating or renaming schemas |
| `MYSQL_USERS_ENGINE_NOT_INNODB_OR_UNREADABLE` | Check `information_schema.TABLES` read-only; do not change table engine just to pass QA |
| `MYSQL_AVATAR_TARGET_AUDIT_FAILED` | Recheck `m63b1:db-audit 3 EMPTY` before any writes |
| `MYSQL_AVATAR_FILE_INVENTORY_FAILED` | Inspect local public upload path and symlinks; don't reset files |
| `MYSQL_LOCK_TELEMETRY_QUERY_FAILED` | Check permissions to read InnoDB transaction/process metadata; do not elevate privileges by default |

The JSON report stores only fixed error codes/stages. Never include PDO exception messages or credentials in shared QA reports. **The baseline/source fingerprint waiver remains unchanged.**

## Failure-recovery classification

| Test | Environment | Evidence criterion | State |
|---|---|---|---|
| Service-level storage exception | deterministic in-memory fake, no PHP GD | stable `USER_AVATAR_STORAGE_FAILED`, DB untouched | offline test |
| SQL update/commit fault after staging | service-level fake + scratch filesystem when GD installed | rollback SQL, remove staged image | offline / local GD fixture |
| Stale CAS loser | service-level fake + real MySQL two-writer opt-in | winner retained, loser staged image removed, 409 code | source / DEV runtime pending |
| Forced on-disk folder collision | actual `PublicImageStorage` with isolated temp `YYYY` obstruction | stable storage failure; SQL untouched | local GD fixture pending operator |
| Forced production HTTP storage failure | public/upload or service running on DEV Apache | not done: no risky chmod/rewrite of public upload | **NOT TESTED** |
| Live failure after SQL commit / power loss | filesystem + database crash conditions | no independent crash recovery evidence | **NOT TESTED** |

**Acceptance boundary:** even a successful M6.3C race and isolated fault suite does not prove independent baseline parity, actual public-upload fault injection through Apache or recovery after process termination. Production cutover stays **BLOCKED**. Keep 129/150 API until the remaining M5/M6 runtime debt is handled. Next feature slice remains M6.4A Product Selector (+3 API).
