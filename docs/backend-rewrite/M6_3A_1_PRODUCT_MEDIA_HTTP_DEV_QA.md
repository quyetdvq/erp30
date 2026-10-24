# M6.3A.1 — Product Image HTTP/Apache/Filesystem DEV QA

## Purpose and boundaries

This is a **QA-only increment**. No Product endpoint, database migration, module owner, access policy, baseline dictionary, or route footprint changes; M6.3A remains **127/150**. It verifies real multipart image upload/replace/delete on local **XAMPP `api.monmin.net`** using `127.0.0.1:443` with pinned Host/SNI. It does not send HTTP to a remote server. The fixed DEV TLS trust exception applies only to this loopback QA tool.

M6.3A.1 may mutate **only** a Product which is the uniquely identified M6.2B.2 retained fixture (e.g., `M62B2_1B6B52993C72`, Product `1466`). The authenticated Product DTO must match the marker, SKU, code, name, description and STANDARD type; Product must be ACTIVE, SIMPLE, allow `edit`, and have **no image**. Any mismatch blocks writes. Preflight additionally requires GD **JPEG, PNG, and WebP codecs** plus fileinfo (an enabled GD extension alone is insufficient). **Do not use a production or business Product.**

`--apply` sends four bilingual negative-path multipart probes (missing image and GIF), uploads a small generated PNG, GETs the Product and verifies Apache static image bytes match the stored file, replaces it with a second generated PNG, checks the first is gone, and DELETEs the second. The fixture remains in DEV without an image if all checks pass. The command checks file paths under `backend/public/upload/YYYY`; no PHP image read endpoint is opened. An interrupted or failed run can leave an image on the fixture; **never rerun `--apply` automatically**. Do not manipulate private `writable` images.

## Apply source patch and offline tests

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\M6.3A.1-dev-product-media-http-qa.patch
git apply .\M6.3A.1-dev-product-media-http-qa.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

## Read-only SQL identity/metadata check

```powershell
php spark m63a1:db-audit M62B2_1B6B52993C72 1466
```

`PASS_DEV_PRODUCT_MEDIA_METADATA_EMPTY` verifies the same persisted fixture and empty image metadata, using SELECT only. Run both **before** and **after** the explicit media QA to establish the SQL final state independently. It does not prove the historical absence of orphan files; the HTTP runner checks the two paths it creates.

## Plan, HTTP preflight, and explicit DEV writes

```powershell
# No SQL and no HTTP.
php tools\m63a1_dev_media.php --marker=M62B2_1B6B52993C72 --product-id=1466

$env:M62B_DEV_USER = 'quyetdv'
$secure = Read-Host 'DEV password' -AsSecureString
$env:M62B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secure).Password
try {
    # Authenticated GET only, no media mutations.
    php tools\m63a1_dev_media.php --marker=M62B2_1B6B52993C72 --product-id=1466 --preflight

    # Only after preflight + SQL audit pass, explicitly mutate the test image.
    php tools\m63a1_dev_media.php --marker=M62B2_1B6B52993C72 --product-id=1466 --apply --confirm=DEV_PRODUCT_IMAGE_QA
}
finally {
    Remove-Item Env:\M62B_DEV_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:\M62B_DEV_USER -ErrorAction SilentlyContinue
}

# Final independent read-only SQL verification.
php spark m63a1:db-audit M62B2_1B6B52993C72 1466
```

Success indicators: `PASS_DEV_PRODUCT_MEDIA_PREFLIGHT`, then `PASS_DEV_PRODUCT_MEDIA_HTTP_FS`, `negative=4 localized=2 UPLOAD=200 REPLACE=200 DELETE=200`, static file HTTP 200 and first deleted image HTTP 404, final image metadata empty, and Spark DB audit `PASS_DEV_PRODUCT_MEDIA_METADATA_EMPTY`.

## Failure handling, scope, and remaining gaps

Reports are saved in `backend/writable/m63a1/reports/m63a1_dev_*.json`. They contain only marker, fixture id, URL-safe paths, status codes, and bounded failure classes; credentials/cookies/CSRF/raw HTTP response bodies are not serialized. If `--apply` fails after upload, it may have left the test image attached; inspect the Product `image_url`, the physical image and report before manual remediation. The QA runner **does not blindly delete on exception**.

The QA proves authenticated happy-path multipart/GD and Apache static serving plus successful replace/delete cleanup for these two images. It does **not** inject failed SQL commits, process crashes, forced unlink errors or concurrency with PATCH; these remain `NOT_TESTED`. It does not accept User Avatar or arbitrary attachments, or certify independent baseline parity or production readiness.
