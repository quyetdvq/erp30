# M5.3 — Bilingual API errors and DEV write gate

## Scope

This patch is an **M5.3 follow-up**, not a new business API milestone. The frozen v1.2.4 `Language/en/Api.php` and `Language/vi/Api.php` are unchanged and still pass their SHA-256 baseline checks. One reviewed `ApiRuntime.php` catalog in each locale provides a specific message for the already-existing error code `ACCESS_MUTATION_NOT_ENABLED`.

**Bilingual contract:** `error.code` stays machine-readable and language-neutral; `error.message` follows `Accept-Language: vi|en`. Unknown/domain error codes still use the identical v1.2.4 status-based messages. Internal programming/configuration exceptions use stable diagnostic identifiers (`READ_ENDPOINT_UNSUPPORTED`, `ERP_PERMISSION_CATALOG_MAPPING_INVALID`); HTTP 500 never returns those identifiers, SQL details or exception text to the UI.

## Why Employee/Organization/Ward PATCH returned 403

The code checks `DINKERP_M33B_WRITE_MODE` **before** calling the PBAC/transaction service. `403 ACCESS_MUTATION_NOT_ENABLED` means the environment-wide mutation switch is off, not that the user lacks record permission.

To exercise mutations on a **development-only backend**, set in `D:\XAMPP\htdocs\erp\backend\.env`:

```dotenv
DINKERP_M33B_WRITE_MODE=enabled
```

Do not put the flag into the baseline `.env` unless deliberately testing baseline mutations. Restart local Apache if a running PHP process has cached configuration. Then retry a PATCH with an authenticated session and valid CSRF. A successful update must still pass PBAC, workflow, validation and database constraints. If the response becomes `AUTH_POLICY_DENIED`, the gate is open, but the actor lacks permission; do **not** bypass PBAC to resolve that failure.

**Scope:** This is a global gate shared by existing M3/M4/M5 mutation endpoints. Enabling it opens those endpoints for normal authorization checks. Use only against disposable/development data; leave it off in production/unknown environments. Never commit a personal `.env` containing credentials.

To return to a read-only development state, set the flag back to `off`.

## Inspect locale in the browser

`frontend/src/services/api.ts` already sends `Accept-Language` from `i18n.global.locale.value`. On a blocked authenticated request, the response must have:

- `Content-Language: vi`: `error.code=ACCESS_MUTATION_NOT_ENABLED`, message `Chức năng ghi dữ liệu đang bị tắt trong môi trường này. Hãy bật cờ cho phép ghi của môi trường phát triển để tiếp tục.`
- `Content-Language: en`: same `error.code`, message `Data modifications are disabled in this environment. Enable the development write gate to proceed.`

Switch language in the UI to test both. The error status remains 403. An internal `InvalidArgumentException`/`UnexpectedValueException` is still mapped to 500 with generic bilingual `Api.serverError` as in v1.2.4, never to its raw diagnostic.

## Offline gates

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

This test suite is database-free; it does not prove authorized PATCH success or runtime record-level PBAC. Those remain separate DEV HTTP checks.
