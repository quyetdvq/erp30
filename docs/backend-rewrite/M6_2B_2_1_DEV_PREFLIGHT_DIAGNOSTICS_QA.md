# M6.2B.2.1 — DEV successful-write preflight diagnostics

**Scope:** Test-tool-only follow-up to M6.2B.2. It does not change Product API behavior, base permissions, policy evaluation, database schema, migrations, frontend, Apache routing or any baseline file. API inventory stays **125/150**.

## Why this patch exists

An XAMPP report for M6.2B.2 (`m62b2_dev_20261024_104318_978f153d.json`) printed `FAIL_DEV_PRODUCT_WRITE; phase=preflight` and `id=NOT_CREATED`. Earlier tooling combined three distinct causes in this one phase: auth/session bootstrap; invalid, inactive or invisible Product Group; invalid, inactive or invisible Product UOM. No real Product mutation was sent in that run. The original report does not supply the specific failure reason, so **do not infer which one failed** from its console output.

This small hotfix adds a separate `--preflight` mode which uses **GET and login only** (login may update its session metadata). It never sends Product CREATE/PATCH/SETSTATE or edits SQL. A pass means login and Group/UOM reference checks succeeded; **it is not proof CREATE will succeed**. The existing `--apply` path retains its mandatory `--confirm=DEV_PRODUCT_WRITE` guard.

## Apply and run

Apply `M6.2B.2.1-dev-preflight-diagnostics.patch` on the installed ERP with M6.2B.2, then from `D:\XAMPP\htdocs\erp\backend`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN

$env:M62B_DEV_USER = 'quyetdv'
$secure = Read-Host 'DEV password' -AsSecureString
$env:M62B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secure).Password

php tools\m62b2_dev_write.php --group-id=3 --uom-id=1 --preflight

Remove-Item Env:\M62B_DEV_PASSWORD
Remove-Item Env:\M62B_DEV_USER
```

If the two referenced records are not both `ACTIVE`, select other existing active Group/UOM IDs. No migration, fake database clone or additional VirtualHost is required. **Do not run `--apply` again until the reason for the failed preflight is resolved.**

The console prints bounded per-stage results, for example:

```text
M6.2B.2.1 DEV PREFLIGHT: FAIL_DEV_PRODUCT_PREFLIGHT; step=product_group
M6.2B.2 PRE-FLIGHT CHECKS (HTTP status and stable codes only):
  auth_csrf: HTTP 200 code=-
  auth_login: HTTP 200 code=-
  auth_me: HTTP 200 code=-
  product_group: HTTP 200 state=DRAFT code=- reason=NOT_ACTIVE
```

A successful reference check prints `HTTP 200 state=ACTIVE reason=PASS` for **both** `product_group` and `product_uom`, then `PASS_DEV_PRODUCT_PREFLIGHT`.

Interpretation:

- `step=authentication`: check local HTTPS CSRF, credentials and authenticated session. HTTP status/error code are recorded only if the corresponding request returned a valid HTTP response.
- `HTTP 403` plus `AUTH_POLICY_DENIED`: check logged-in actor and persisted PBAC for the specific reference; do not bypass PBAC.
- `HTTP 404`: the referenced ID is unavailable or not visible. Do not assume the database is empty.
- `HTTP 200 state=DRAFT/INACTIVE`: choose a known ACTIVE ID; do not change a business record solely to force a test to pass.
- `HTTP 200` but `ID_MISMATCH/INVALID_DATA`: unexpected response contract; inspect the owning GET endpoint.

The new report is under `backend/writable/m62b/reports/m62b2_dev_*.json`, with `integration.preflight` and `integration.preflight_step`. It stores **only numeric HTTP status, sanitized stable error code, sanitized reference state and a yes/no ID match**. It never serializes HTTP response bodies, usernames, email, password, cookies or CSRF tokens.

## Offline evidence and remaining work

- `php backend/tests/m62b21_preflight_pure.php`: 22 pure assertions.
- `php backend/tests/m62b2_dev_write_pure.php`: pre-existing 53 assertions.
- `php backend/tools/m34b_run.php --offline`: expected 94/94 suites.
- `php backend/tools/m34d_accept.php --offline`: expected 6/6 source gates.

This code has not been run against the operator's Windows Apache/MariaDB in the packaging environment. Production write remains blocked; successful Product CREATE/PATCH, duplicate handling, COMBO SQL rollback and numbering concurrency remain separately unverified.
