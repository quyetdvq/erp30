# M6.2A.1 — Product VIEW PBAC alignment and public-upload media policy

## Baseline evidence and user decision

- M6.2A has **122/150 registered operations**; M6.2A.1 adds **no API routes**.
- In M6.1/M6.2A, `Shield User::can('product[_group|_uom].view')` was used as a preliminary gate. This reads Shield grants and can disagree with M3 `BasePermissionReader` (which merges active ERP group_permissions and recognized Shield direct grants). `UnifiedAccessService::hasBaseViewPermission()` uses the **same persisted snapshot** as per-row PBAC. It does not grant anything by itself; per-row `canView()` and mutation authorization remain mandatory.
- `AUTH_POLICY_DENIED` reported by `GET /product-groups` and `/product-uoms` is consistent with the duplicate preliminary gate; after correction, lack of a base grant, eligible assignment, resource ACTIVE state, or PBAC ALLOW can still legitimately deny. The only way to attribute an individual account's 403 is to audit its DB decisions.
- `backend/public/upload/` was already the approved shared physical location in `BASELINE_FIDELITY_POLICY.md` and `UploadPaths`. The older v1.2.4 YearlyImageStorage supplies the reviewed format `YYYYMMDD{product|avatar}{last 7 digits of Unix seconds}.jpg|png|webp` and 2048px/GD validation. M6.2A.1 supplies `Shared/Media/PublicImageStorage`, which changes **only the default physical root**, not the filename/codec specification. No SQL, permission, or Frontend change.
- Valid year-based filenames produce `/uploads/YYYY/filename` URLs. Product DTO exposes those URLs through `ImageUrl::for`; private legacy SHA-1 names and arbitrary filenames are never exposed. URL generation does not assert the file exists on disk.
- Root `backend/.htaccess` now supports the restricted `/uploads/` static alias for the user's existing VirtualHost with DocumentRoot=`backend`. `backend/public/.htaccess` retains that alias for the recommended public DocumentRoot. The physical `public/upload/.htaccess` disables script handlers and directory listing. **Neither aliases nor DTO URLs expose `/writable`.**

## Acceptance on XAMPP (development environment)

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\M6.2A.1-product-pbac-public-upload.patch
git apply .\M6.2A.1-product-pbac-public-upload.patch

cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE

# Read-only: shows missing base permission vs. row PBAC denial for an existing account.
php spark m62:access-check quyetdv

# Dry-run on the local writable/uploads/YYYY source; no DB writes or file copies.
php tools\m62a1_media_migrate.php

# Optional, controlled copy of validated images only; original files retained.
php tools\m62a1_media_migrate.php --apply

# If original files are under backend_baseline/writable/uploads/YYYY instead:
php tools\m62a1_media_migrate.php --baseline
php tools\m62a1_media_migrate.php --baseline --apply
```

Do not use migration flags if media has already been copied and manually renamed. On conflicts the utility refuses overwrites and exits nonzero. Existing legacy 40-hex filenames **cannot be migrated by merely copying**, because they lack the year-based name; a separate metadata-aware conversion is needed. No automatic DB rewrites or deletions are performed.

After copying a known good file, load `/uploads/YYYY/filename` from `https://api.monmin.net`. This tests Apache static access independently of API/PBAC. Check that `/writable/...` is NOT served publicly. Product GET on the same account must still enforce row-level PBAC. Use `Accept-Language: vi` and `en` for bilingual API error checks.

## Files/attachments — scope boundary

The pinned v1.2.4 source defines a **durable physical naming convention for Product and Avatar images**. It does **not** define a general persisted attachment-upload route, stable attachment filename or extension policy among the 122 implemented API operations. Import's `source_file_name` is metadata, not evidence of a publicly served attachment directory. Therefore this patch does not invent a public arbitrary-file uploader or move unknown documents into the static image directory. Future attachment-owning modules must explicitly choose validated file types and naming rules and use the shared `public/upload/` root only for files approved as publicly accessible. Sensitive attachments need authorization before download, not an unrestricted static URL.

## Known acceptance limits

1. Product Group/UOM/Product 403 fix is source-reviewed; real HTTP/PBAC for the logged-in DEV user is pending.
2. `PublicImageStorage` is the ready shared storage primitive; upload/delete operations for Product Media/User Avatar remain **unimplemented in the new rewrite** (they are still included in the frozen 150 contract). Its GD encode/resize/store path needs local HTTP/GD tests when those operations are implemented. No fake upload route is added.
3. `php spark m62:access-check` is read-only diagnostics; running it does not create any grants or change DB permissions.
4. Images not yet physically copied (or renamed by an approved migration) may return a valid-looking static URL that still resolves HTTP 404.
