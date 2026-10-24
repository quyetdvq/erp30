# M6.2B.1 — DEV Product mutation HTTP negative-path gate

## Purpose

This is a **development convenience test** following M6.2B's 91 offline suites. It does **not** add API routes, alter Product domain code, read/modify the database directly, alter Apache, or require a cloned database. The tested API inventory remains **125/150**.

The script sends exactly six **intentionally malformed Product mutation requests** (three operations × EN/VI) to the existing local Apache HTTPS VirtualHost `api.monmin.net`. The socket is always `127.0.0.1:443` with the correct HTTPS SNI and Host header. No arbitrary server URLs are accepted. It logs in using an explicit DEV actor, obtains CSRF, confirms `/auth/me` identity and reads one existing Product before and after the probes.

A PASS requires:

- Successful authenticated session and accessible Product ID.
- CREATE rejects an unsupported field with `422 PRODUCT_INVALID_FIELD`.
- PATCH rejects direct `status` change with `422 PRODUCT_STATUS_REQUIRES_SETSTATE`.
- SETSTATE rejects the invalid state string `INVALID*STATE` with `422 PRODUCT_INVALID_STATUS`.
- All three errors are returned for both `Accept-Language: en` and `vi`, with distinct translated messages and corresponding `Content-Language` headers.
- The selected Product's JSON data remains identical before and after requests.

**No valid business mutation is sent by this runner.** Logging in may still update session/authentication metadata. This is *not* a SQL rollback, successful CREATE/PATCH/SETSTATE, Numbering issuance, COMBO rollback, two-writer concurrency, production acceptance or independent baseline parity test.

## Run on XAMPP

Use **only a development Apache/database**. Do not use a production-facing authenticated actor for mutation probes. From `D:\XAMPP\htdocs\erp\backend`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE

$env:M62B_DEV_USER = 'quyetdv'
$secret = Read-Host 'DEV password' -AsSecureString
$env:M62B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password
php tools\m62b_dev_http.php --product-id=1
Remove-Item Env:\M62B_DEV_PASSWORD
Remove-Item Env:\M62B_DEV_USER
```

Select an **existing Product visible to the account**. The runner never creates or deletes Product records. Do not include plaintext passwords in shell command literals, screenshots or reports. A failure is logged as an HTTP status, stable error code and locale only; the report contains no password, token, cookie, username or full API payload.

Expected terminal output:

```text
M6.2B.1 DEV HTTP: PASS_DEV_PRODUCT_NEGATIVE_HTTP
M6.2B.1 NEGATIVE: 6/6 error responses; 3/3 localized; product unchanged=YES
```

Report: `backend/writable/m62b/reports/m62b_dev_*.json`.

## Remaining required acceptance

Successful Product CREATE/PATCH/SETSTATE still requires explicit DEV testing with disposable records. For numbering, verify counter + Product persisted within one transaction and two parallel writers cannot obtain the same identifier. For COMBO rollback, verify **all three** persisted participants (Product row, counter row, component rows) remain unchanged on failure. Independently compare successful mutation behavior with baseline v1.2.4 if parity is required. None of these gates is auto-marked PASS by this runner.
