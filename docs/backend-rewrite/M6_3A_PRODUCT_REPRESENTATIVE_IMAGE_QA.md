# M6.3A — Product Representative Image (Source + DEV QA)

## Contract and ownership

This increment adds exactly two pre-existing, frozen v1.2.4 API-050 operations:

| Method | Path | Success | Requirement |
| --- | --- | --- | --- |
| POST | `/api/v1/products/{id}/image` | 200, Product DTO | `multipart/form-data` with one `image` part; `product.edit` on current persisted State |
| DELETE | `/api/v1/products/{id}/image` | 200, Product DTO | `product.edit` on current persisted State |

No new image GET API is added. The Product owner is `backend/app/Modules/Product/Media/`. The existing `Master/ProductReadService` serves public Product metadata with `image_available` and validated `image_url`; `image_filename`, `image_mime`, and `image_size_bytes` are never returned to HTTP clients. Other Product Master contract surfaces remain unchanged.

## Storage and authorization

- Absolute physical root: `<backend>/public/upload/YYYY/` (never `writable/`).
- Static URL: `/uploads/YYYY/YYYYMMDDproductNNNNNNN.jpg|png|webp`, mapped by the existing backend/public Apache rewrite. No PHP read proxy is involved.
- JPEG, PNG, WebP; max **3 MiB**, image dimensions validated (source ceiling 40 million pixels; resized to max 2048 per axis), animated PNG/WebP forbidden. Requires PHP GD JPEG/PNG/WebP codecs, fileinfo and a writable `backend/public/upload` directory.
- `Shield` login and `DINKERP_M33B_WRITE_MODE=enabled` are mandatory. An authoritative Product row is read `FOR UPDATE` and checked by central `UnifiedAccessService` (record `view` then `edit`, including the current State, policy and scope) **before inspecting multipart image content**.
- Media takes the same MariaDB advisory mutex (`:product-master`) as Product CREATE/PATCH/SETSTATE. The new file is saved before the SQL metadata update; if the SQL transaction fails the new file is deleted. Old approved public images are unlinked only after the SQL transaction commits. Failure to remove an obsolete file is logged without falsifying a committed HTTP mutation.
- Existing private SHA-style Product filenames are never exposed via `image_url` and are deliberately left untouched by media DELETE/replace; use only the audited media-migration procedure to move them.
- Only filename metadata is persisted; the baseline's legacy `image_mime` and `image_size_bytes` columns remain nullable, and `updated_by`/`updated_at` are updated by the trusted actor.
- Errors `PRODUCT_IMAGE_INVALID_SIZE`, `PRODUCT_IMAGE_INVALID_TYPE`, `PRODUCT_IMAGE_INVALID_CONTENT` (422) and `PRODUCT_IMAGE_STORAGE_FAILED` (500) have reviewed `en` and `vi` messages in `ProductRuntime`. The 500 response is stable and does not expose a real filesystem path, raw exception, or stack trace.
- General **attachments** and **User Avatar mutation** endpoints are outside M6.3A. Never infer a public attachment file contract from these image-specific routes.

## Safe offline source verification

From `D:\XAMPP\htdocs\erp\backend`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tools\m63_accept.php
```

Expected: 99/99 pure/fixture suites, 6/6 architecture gates, M6.3A source 125 + 2 = **127/150**.
These suites do **not** prove authenticated multipart POST, physical GD conversion, Apache static serving, or SQL/file failure recovery on Windows.

## DEV HTTP acceptance (manual, explicitly on a disposable Product)

1. Pick an authorized Product **owned by the developer**, with an empty representative image and no real business reliance. You may inspect the existing fixture Product `1466` (`M62B2_1B6B52993C72`) only after confirming no representative image is already attached and that `product.edit` is permitted in its **current ACTIVE State**. Never use an arbitrary live Product as a test target.
2. On XAMPP check `php -r "var_export(extension_loaded('gd') && function_exists('imagewebp'));"` and ensure `backend/public/upload` is writable by Apache/PHP.
3. With the actual authenticated browser session and CSRF token, POST a real PNG/JPEG/WebP as form part `image` to `/api/v1/products/{id}/image`. Expect 200 Product DTO, `image_available=true` and `image_url=/uploads/YYYY/...`.
4. Open `https://api.monmin.net` + `image_url` in browser. Confirm HTTP 200 and actual image bytes, **not** JSON, `writable/` or a private file path. Confirm the corresponding file exists in `backend/public/upload/YYYY/` and that PHP does not execute it.
5. Replace with a different small supported image, then confirm the metadata points at the new file and the old public file was removed (or inspect server logs for failed cleanup). All actions must be allowed by the current Product State and PBAC.
6. DELETE `/api/v1/products/{id}/image` with the same browser session. Expect 200, `image_available=false`, `image_url=null`, physical new file removed. GET Product again to confirm persisted changes. **DELETE is destructive for the image**, so run only on the temporary image created during this QA.
7. Negative checks: missing image, GIF, oversized image return the pinned validation envelope; denied edit/State or disabled mutation gate returns forbidden **before** image decoding; an unauthorized actor cannot overwrite another Product image. Verify EN and VI messages.
8. Deliberate SQL write failure, process kill, file cleanup errors and simultaneous Media vs Product PATCH are not simulated by source/offline gates: keep their runtime status **NOT_TESTED** until separately tested.

## Release status

- Source/Offline: tested locally, pending a repeat on the user's XAMPP.
- DEV HTTP/multipart/GD/static Apache and DB-file atomicity: **NOT_TESTED** by this deliverable.
- Independent baseline HTTP parity and Production: **NOT_ACCEPTED**.
