# DinkERP Product Media — PM-2/01 engineering checkpoint closure

**Record date:** 2026-10-19
**Decision:** **ACCEPTED WITH EXCEPTIONS — engineering test checkpoint only**
**Track:** M12 Product / Product Media pilot v0.1; not an approval to deploy to production and not a `CLEAN` runner result.

## 1. Authoritative evidence and exact scope

Approval is `Product_Media_Pilot_Scope_v0.1.md` with SHA-256 `c32b79eb0adfefb2fb1aae32b45835717832f6df9c3c1a773837b18ae4708806`. PM-2 scope specifically requires eliminating both `productForImage()` and `getProduct()` *reverse calls* from canonical Media into Api050, while retaining initial edit authorization and the separate post-mutation view check. It does not authorize broad Api050 reorganization or behavior/schema changes.

The user ran the guarded PM-2/01 runner against `127.0.0.1:3306/erp_test` (prefix `dinktech_`, PHP 8.3.30, PHPUnit 10.5.65). The original, unmodified `result.json` is preserved under `evidence/PM2_01_regression_result.json`.

| Evidence item | Observed result | Evidence source |
| --- | --- | --- |
| PM-2/01 Pilot | **64/64 tests PASS, 855 assertions** | Prior user-provided XAMPP console; copied verbatim as `evidence/PM2_01_pilot_console.txt` |
| PM-2/01 Regression | **1,112/1,112 tests PASS, 11,116 assertions** | User-provided PHPUnit summary in conversation (transcribed in `evidence/PM2_01_regression_summary.txt`) |
| Regression runtime | 12:21.344; 78 MB | User-provided PHPUnit summary |
| Runner status | **`NOT_CLEAN`** | Original `result.json` |
| `phpunit_exit`, `junit_identity_error` | `0`, `null` | Original `result.json` |
| Source checkpoint | **26/26 MATCH** | Original `result.json`, PM-2/01 manifest |
| New test files | **2/2 preserved** | Original `result.json`, PM-2/01 manifest |
| Reviewed files stable during run | **28/28** | Original `result.json` |
| PHPStan | **exit 1**; Pilot log contains **115 diagnostics** | Original regression `result.json`; Pilot console |
| DB isolation | `erp_test`, guarded `testing`, prefix `dinktech_` | Pilot console; regression terminal target and guard |

**Important evidence limitation:** The full-regression `phpunit.xml`, `discovered-cases.json`, `phpunit.log`, and `phpstan.log` were **not uploaded as separate raw artifacts** for this record. The runner's `junit_identity_error=null` is recorded, but raw case-by-case XML was not independently re-parsed here. The **115** diagnostic count is evidenced by the Pilot log; the regression `result.json` establishes `phpstan_exit=1` but does not contain a diagnostic count. Assertions are from the user's PHPUnit output, not fields in `result.json`. Do not claim full raw-log evidence is present.

Regression token: `878bc456e95b8ac4e2f65a48dad1ed83` (use to locate evidence within `backend/build/logs/product-media-pm2-01/` on XAMPP).

## 2. Baseline lock

The existing `backend/tools/product-media-pm2-01/manifest.json` **remains authoritative and unmodified**. This delivery mirrors it byte-for-byte as `baseline/PM2_01_source_and_test_manifest.json`, with 26 source SHA-256 hashes (LF-normalized checks) and two new test-file hashes; upstream PM-1/02 and PM-1/01 manifests are also preserved. The new record `baseline/PM2_01_baseline_lock.json` pins those manifest hashes, the user-provided regression result, and the accepted exceptions.

An independent local source-copy verification matched **26/26 source** and **2/2 new-test** hashes, and confirmed the obsolete `Api050MediaAccessAdapter.php` path is absent. This local verification **does not assert that every unreviewed file in the user's Git repository has the same hash**. Source checkout fingerprint requires a Git commit ID from the user's own machine to extend the baseline to the entire tree.

## 3. Acceptance boundaries / exceptions

- Engineering acceptance: PM-2 reverse dependency extraction, compatibility and available PHPUnit integration/regression checks.
- Not accepted as clean: raw PHPStan exited `1`; it remains tracked separately. No ignores/baseline suppression added.
- Not covered: wire-level multipart, authenticated real-browser CSRF, deployment checkpoint, independent review.
- Architecture caveat: `Modules/Product/Media/Infrastructure/Persistence/SqlProductImageMetadataRepository.php` still reads `auth()->user()` and `date('Y-m-d H:i:s')` directly. This preserves characterized semantics but does not meet the stricter pilot-to-platform criterion of actor/time dependency via collaborator. Do **not** silently replace the timestamp clock; it would potentially change stored values/timezone and test expectations.
- Legacy `ProductImageService(db, catalog, storage)` and `ProductImageStorage` remain for compatibility. The bootstrap factory still type-hints optional Api050 catalog to preserve caller injection; **the canonical Media module** has no Api050 imports or calls.

## 4. Change control

PM-2/01 is closed as an **engineering checkpoint with documented exceptions**. PM-2 reverse-call removal is functionally complete under v0.1; no PM-2/02 feature patch is required on present evidence. **PM-3 testing and independent review may begin**, but the Product Media pilot is **not approved as a generalized architecture pattern or production release**. Resolve/explicitly sign off the actor/time dependency criterion and other PM-3 gates before declaring the pilot complete. Any future source modification must create a NEW checkpoint/manifest and cannot rewrite this evidence.

No DB reset, migration, production write, git commit or application-source change is performed by this evidence package.
