# DinkERP Backend v2 — M2.1.1 hotfix and QA

Date: 2026-10-23. Scope: fixes M2.1 only; no Frontend, baseline Backend, DB migration, or upload changes.

## Fixed

- All 14 resource list/detail GET routes use explicit `[ReadCatalogController::class, 'methodName']` handlers, avoiding the malformed namespace `\\App\\Controllers\\App\\Modules\\...` observed in `php spark routes` on CI4 4.7.5. The original 16 M2.1 HTTP operations and all six M1 operations remain unchanged.
- `m21:verify` now checks the resource/policy/action columns and uses independently checked read queries (no raw JOIN) to test the seven VIEW actions. A missing expected column or SQL execution failure is reported with an actionable error instead of calling `getRowArray()` on `false`.
- `ReadViewGate::resource()` uses the same safe resource/action lookup, and missing VIEW actions fail closed.
- New vendor-free route regression test: `php backend/tests/m211_routes_pure.php`.

## Installation

Back up locally modified files before overlay. Unzip `DinkERP_Backend_v2_M2.1.1_Hotfix_Overlay.zip` into `D:\XAMPP\htdocs\erp\`, preserving the `backend/` and `docs/` directories. Do not overwrite `.env`, `vendor/`, `frontend/`, `backend_basline/` or uploaded files; none of these exist in the overlay.

## Checks on Windows

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m1_pure.php
php backend\tests\m2_pure.php
php backend\tests\m21_pure.php
php backend\tests\m211_routes_pure.php
python backend\tools\verify_routes.py
cd backend
php spark m21:verify
php spark routes
```

Expected pure tests: 25 M1, 39 M2, 36 M2.1 and 15 new route assertions. Check `spark routes` for handlers such as `\\App\\Modules\\ReadApi\\ReadCatalogController::listProvinces` and `::showProvince` (without `\\App\\Controllers\\App\\Modules\\`).

If verification reports missing catalog columns, the configured database may not have received the existing baseline migrations; compare actual schema before making any change. If a query fails, the new diagnostic includes the DB error message for further investigation. **Do not bypass PBAC or auto-run migrations to force PASS.**

Finally, use a signed-in Frontend session to test at least the GET provinces and organizations routes. Verify that a scoped or denied actor cannot access out-of-scope detail records, list totals or organization labels. This offline hotfix cannot establish runtime MariaDB/Shield behavior without the user's deployed environment.
