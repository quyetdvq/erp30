# NFX-D1 browser regression (mocked API, no real writes)

## Goal and boundary

This test file exercises real Vue pages through Playwright/Chromium with a complete in-memory mock of `/api/v1/**`.
It checks UI regressions, **not** real backend PBAC, SMTP delivery, Push notification delivery, or a live account's Browser UAT.
The mock is installed before navigation; all unexpected non-GET API requests are rejected (403).
There are no credentials, test database migrations, external service calls, or delivery workers.

## Run on the existing DinkERP v1.8.0 + NFX-D1 checkout

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm exec playwright test e2e/nfx-d1-browser-qa.spec.ts --project=chromium --reporter=line
```

If Playwright reports the Chromium browser binary missing, run once (with normal corporate download controls):

```powershell
pnpm exec playwright install chromium
```

This uses the existing `playwright.config.ts` and its local Vite server (normally http://localhost:5173).
It does **not** use the `playwright.live.config.ts` security suite and must not be treated as live UAT.
Do not pass a production URL as `baseURL`, or convert the mock to `route.continue()`.

## Expected cases (9 tests)

1. 9 personal policies grouped 5/3/1; Inherit is not consent.
2. `import.failed.email` is independent, saved with CAS, survives reload and revokes cleanly.
3. Unsaved Push On cannot register a browser/device.
4. Locked/unavailable personal policy is non-editable.
5. Personal 409 conflict retains draft until deliberate Reload.
6. System policy is read-only without `notification_setting.edit`.
7. System Email/Push default On disabled; override lock warns and uses CAS without changing other rows.
8. System 409 conflict does not silently overwrite or retry.
9. English translations and 390px viewport remain usable.

The suite leaves **all real application state untouched**. Test mutations go only to memory.

## Separate real Browser UAT release gate

Use the existing NFX-D1 QA guide's ten scenarios with explicitly authorized test accounts.
Read-only inspections can be run on an authorized application environment. Consent changes and
policy lock/revision/epoch mutations must be tested on an isolated DEV clone, not a live business database.
If a scenario cannot be tested, record `DEFERRED`, not `PASS`.

Only close NFX-D1 after real Browser UAT results are documented; automated Playwright mock PASS is
additional evidence and is **not** a substitute for that gate.
