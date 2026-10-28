# M6.3B.2 - Delegated Avatar DEV acceptance (safe fixture workflow)

Status (2026-10-24): **SOURCE VERIFIED; LIVE DELEGATED QA BLOCKED until the owner's DEV evidence is supplied.**
This is a QA runner, not a new API or a production safety certification. Route count remains **129/150**.

## Why User 3 must not be reused automatically

M6.3B.1 previously passed owner-reported upload/replace/delete tests for User 3 (`toandv`). However, on 2026-10-24 this command **failed** on the owner's XAMPP:

```powershell
php spark m63b1:db-audit 3 EMPTY
```

`M63B1DevAudit` compares the current `avatar_filename` against `EMPTY` and refuses a mismatch. This is a **read-only guard**, not evidence of broken Avatar storage. The value may have changed after the earlier test; the cause has not been established. **Do not clear, overwrite or delete User 3's avatar to make QA pass.** User 3 is not an approved fixture unless independently revalidated as an intentionally disposable account.

First inspect the current database without writes in phpMyAdmin or a read-only SQL client (adapt the Shield users table name if configured differently):

```sql
SELECT DATABASE() AS connected_database;
SELECT id, username, active, status, deleted_at, avatar_filename
FROM users WHERE id = 3;
```

Review prior `backend/writable/m63b1/reports/` and `backend/writable/m63b2/reports/` (if present). Do not infer that an older `PASS` describes today's data. Do not upload or include these reports with passwords/cookies; the runner itself emits sanitized status-only evidence.

## Safe account selection and isolated DEV environment

Use an **active disposable test account** different from the admin, with `avatar_filename IS NULL` and no base `user.edit`. Test the delegated edit using a separately authenticated admin with base `user.view` and `user.edit`, subject to **persisted target-record PBAC**. Neither script provisions accounts or grants permissions.

- Limit all tests to a local Apache/XAMPP environment and explicitly approved *non-production database*. Review the actual database used by both Spark and the local web server; a host name alone does not prove DB isolation.
- `--plan` behavior (omitting both `--preflight` and `--apply`) is completely inert; `--preflight` performs SELECT, logins and GET only. Session login may update session state but no Avatar POST or DELETE is sent.
- `--apply` performs real Avatar POST/replace/DELETE and is **not** an automatic cleanup tool. It requires valid disposable fixture, an explicit confirmation, `CI_ENVIRONMENT=development|testing`, `M63B2_DEV_QA_WRITE=YES`, and `M63B2_EXPECTED_DATABASE` pinned to the actual database name. The expected name is checked against `SELECT DATABASE()` in the Spark SQL audit **before** valid image bytes are sent.
- The runner does not prove the source database is safely cloned merely because its name matches. An independent operator must validate that it is an approved DEV/test database. Never use real business accounts or production storage for this QA.
- The runner uses `https://127.0.0.1` with `Host: api.monmin.net` and never follows redirects or contacts a remote server. Avatar URLs are served statically from `backend/public/upload/YYYY`; no PHP media-byte API or `/writable` alias is introduced.

## 1. Source-only checks (safe on a machine without DB)

From `D:\XAMPP\htdocs\erp`:

```powershell
python backend\tools\verify_baseline.py --v1-root .
python backend\tools\verify_layout.py
python backend\tools\verify_routes.py
php backend\tools\test-unit-check.php
php backend\tools\m34b_run.php --offline
php backend\tools\m34d_accept.php
php backend\tools\m63b_accept.php
```

The `m34d_accept.php` output may write a JSON report inside `backend/writable` but never performs SQL/HTTP. The **6/6 architecture gates**, **107/107 offline suites**, **129/150 routes** are source-level evidence only.

## 2. Fresh fixture preflight, without Avatar mutations

These are PowerShell commands from `D:\XAMPP\htdocs\erp\backend`. Replace the ID, users and database with values actually checked on the DEV environment; **do not copy `3` from old documentation**.

```powershell
$targetId = 'YOUR_DISPOSABLE_DEV_USER_ID'
$env:M63B2_ADMIN_USER = 'YOUR_DEV_ADMIN_USERNAME'
$env:M63B2_TARGET_USER = 'YOUR_DEV_TARGET_USERNAME'
$env:M63B2_EXPECTED_DATABASE = 'YOUR_TEST_DATABASE_NAME'

php spark m63b1:db-audit $targetId EMPTY
php tools\m63b2_dev_admin_avatar.php --target-user-id=$targetId

$adminSecure = Read-Host 'Admin DEV password' -AsSecureString
$targetSecure = Read-Host 'Target DEV password' -AsSecureString
$env:M63B2_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $adminSecure).Password
$env:M63B2_TARGET_PASSWORD = [System.Net.NetworkCredential]::new('', $targetSecure).Password
try {
    php tools\m63b2_dev_admin_avatar.php --target-user-id=$targetId --preflight
} finally {
    Remove-Item Env:\M63B2_ADMIN_PASSWORD,Env:\M63B2_TARGET_PASSWORD -ErrorAction SilentlyContinue
}
```

Check that the `M63B1_DB_JSON` output contains the correct `database_name`, `id`, `avatar_filename: null`. Check both Spark and the authenticated Apache backend use the same **approved** DEV/test database. Expected preflight output:

```text
M6.3B.2 DEV ADMIN AVATAR: PASS_DEV_ADMIN_AVATAR_PREFLIGHT; phase=complete
M6.3B.2 checks: distinct_admin=1 owner=1 sql=1 gd=1
```

`--preflight` does **not** prove scoped `user.edit` PBAC; that is tested by harmless malformed multipart requests first when `--apply` is explicitly authorized.

## 3. Apply on disposable account only (separate operator approval)

Only after the fresh preflight passes and the target is independently confirmed disposable:

```powershell
$targetId = 'YOUR_DISPOSABLE_DEV_USER_ID'
$env:CI_ENVIRONMENT = 'development'  # or 'testing', matching your actual DEV server
$env:M63B2_DEV_QA_WRITE = 'YES'
$env:M63B2_EXPECTED_DATABASE = 'YOUR_TEST_DATABASE_NAME'
$env:M63B2_ADMIN_USER = 'YOUR_DEV_ADMIN_USERNAME'
$env:M63B2_TARGET_USER = 'YOUR_DEV_TARGET_USERNAME'
$adminSecure = Read-Host 'Admin DEV password' -AsSecureString
$targetSecure = Read-Host 'Target DEV password' -AsSecureString
$env:M63B2_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $adminSecure).Password
$env:M63B2_TARGET_PASSWORD = [System.Net.NetworkCredential]::new('', $targetSecure).Password
try {
    php tools\m63b2_dev_admin_avatar.php --target-user-id=$targetId --apply --confirm=DEV_ADMIN_AVATAR_QA
} finally {
    Remove-Item Env:\M63B2_ADMIN_PASSWORD,Env:\M63B2_TARGET_PASSWORD,Env:\M63B2_DEV_QA_WRITE,Env:\M63B2_EXPECTED_DATABASE,Env:\M63B2_ADMIN_USER,Env:\M63B2_TARGET_USER -ErrorAction SilentlyContinue
    # Restore your original CI_ENVIRONMENT if you changed it for this command.
}
```

The runner's ordered acceptance checks are:

1. Validate SQL fixture + connected DB; authenticate separate admin/owner; check base grants and GD codecs.
2. Verify admin's **record-scoped** rights in `en` and `vi` by sending no image bytes; 422 `USER_AVATAR_INVALID_CONTENT` in both locales shows authorization passed before validation, with distinct localized messages.
3. Verify owner's nonowner denial on the admin's Avatar with empty multipart in both locales; require 403 `USER_AUTHORIZATION_DENIED` or 404 `USER_NOT_FOUND`; preserve admin Avatar.
4. Admin uploads PNG A; verify owner `/auth/me`, SQL metadata and Apache static bytes match disk bytes.
5. Admin replaces A with B; check new URL, SQL metadata, Apache status 200 for B, A removed (disk and HTTP 404).
6. Admin deletes B; check SQL NULL, owner `/auth/me`, disk removal, static 404 and unchanged other profile fields.

Expected output **only after a real successful run**:

```text
M6.3B.2 DEV ADMIN AVATAR: PASS_DEV_ADMIN_AVATAR_HTTP_FS_SQL; phase=complete
M6.3B.2 PBAC: admin_probe=2 owner_denied=2
M6.3B.2 HTTP: UPLOAD=200 REPLACE=200 DELETE=200
M6.3B.2 FILES: first=200 second=200 old_gone=404 removed=1 final_sql_empty=1
```

Independent final check (only if no failed/partial phase needs investigation):

```powershell
php spark m63b1:db-audit $targetId EMPTY
```

## Failures, evidence and acceptance boundary

- **Mismatch** (`Existing avatar metadata differs`): stop. Inspect existing `avatar_filename` and the recent sanitized report. Never reset the target to EMPTY to bypass the check.
- **Database pin mismatch**: stop. Check `SELECT DATABASE()` in the configured local DB and compare with `M63B2_EXPECTED_DATABASE`; never change the expected value merely to bypass a wrong-server warning.
- **Wrong PBAC or permissions**: stop without granting broad privileges just for QA.
- **Failed/partial `--apply`**: **never rerun blindly**. Inspect current SQL metadata, file existence and newest `backend/writable/m63b2/reports/m63b2_dev_*.json` before choosing an explicit, reviewed remediation. HTTP writes may already have committed.
- Only a successful real run with preserved report and independent SQL/HTTP confirmation can close **M6.3B.2 delegated DEV acceptance**. Source checks do not constitute DB/HTTP acceptance.
- Out of scope / **NOT TESTED**: deterministic two-writer CAS races, forced SQL and file-storage faults, independent old-vs-v2 baseline replay, production deployment. M6.3B.2 adds **zero new HTTP operations**.
