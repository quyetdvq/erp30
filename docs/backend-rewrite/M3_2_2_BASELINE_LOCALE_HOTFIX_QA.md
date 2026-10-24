# M3.2.2 — Baseline Bilingual / Error Contract Hotfix

## Why

The previously approved M3.2 code had a request language selector and an `Api.php` dictionary, but only 1/23 English and 1/51 Vietnamese baseline language catalogs. `ApiResponder` separately recognized only 2 of the 11 baseline special message codes. The generic v2 exception handler did not yet implement baseline DomainException error-status/code/details mapping.

## Files and semantics

- Restored 72 missing translation PHP files from verified `erp_v1.2.4.zip`: now 23 English and 51 Vietnamese catalogs; pre-existing `en/Api.php` and `vi/Api.php` were checked byte-equal and left untouched.
- Expanded `ApiResponder::messageKey` with the exact 11 special-case error codes in the original `ApiTransport`.
- Added a small `App\Exceptions\DomainException` compatibility value and `App\Shared\Http\ApiErrorMapper` implementing baseline machine code, status, field/details decisions. Generic internal server errors remain sanitized.
- Updated `ApiExceptionHandler` to use this mapper, and to retain the request ID established by the filter instead of generating a different value in the exception path.
- Added `backend/tests/m322_baseline_fidelity_pure.php`: verifies 74 checksummed translations, `vi`/`en` messages, special code mapping, and compares 120 status/code outcomes with a pinned baseline mapper snapshot. A signature-checked baseline fixture is used explicitly; the test does not claim to execute a separately running baseline.
- Added [BASELINE_FIDELITY_POLICY.md](BASELINE_FIDELITY_POLICY.md) as a mandatory acceptance checklist for future modules.

## Safe install

From `D:\XAMPP\htdocs\erp`, back up locally modified files first, then extract `DinkERP_Backend_v2_M3.2.2_BaselineLocale_Overlay.zip` into the project root. The ZIP paths start at `backend/` or `docs/`. No `frontend/`, `.env`, `vendor`, database migration or upload files are included.

Run:

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m322_baseline_fidelity_pure.php
php backend\tests\m32_baseline_lanes_pure.php
php backend\tests\m32_runtime_pure.php
cd backend
php spark m3:audit
php spark m32:verify 50 100
php spark routes
```

Inspect Frontend Network requests under both UI locales, including errors on `401`, `403`, `404` and `422` and response `Content-Language`. For an authenticated browser session, compare `Accept-Language: en` and `Accept-Language: vi`. Check that the returned `error.message` and any `error.details[*].message` are translated, while `error.code` remains the same.

**Do not switch to `enforce` solely because this patch passes.** Keep the prior approved `legacy`/`shadow` deployment gate and complete runtime PBAC + full error response comparisons before enabling enforcement.

## Offline results

- M3.2.2 contract: 35 pure assertions and 120 baseline error status/code comparisons PASS.
- All 74 translation files byte-for-byte identical to the source ZIP (SHA-256 manifest).
- Regression suites M1 / M2 / M2.1 / M2.1.1 / M3.1 / M3.2 PASS, including 729 M3 policy lanes and 1,024 baseline M3.2 lane cases.
- 173 application + test PHP files pass `php -l`.
- 22 API operations unchanged. Frontend and `backend_basline` directory snapshots unchanged.
- HTTP/locale/runtime DB parity on the user's XAMPP remains to be tested by the user.
