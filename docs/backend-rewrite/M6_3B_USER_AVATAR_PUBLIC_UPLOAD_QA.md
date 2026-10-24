# M6.3B — Shield User Avatar Upload/Delete (public upload)

## Scope / release gate

- Frozen v1.2.4 operations: `POST /api/v1/users/{id}/avatar` and `DELETE /api/v1/users/{id}/avatar` (both HTTP 200). Source inventory **129/150**; previous **127/150** preserved.
- Single owner: `backend/app/Modules/Identity/Media/`, no second Identity module. Separate `M63BRoutes.php` explicit include in `Routes.php`, AutoRoute remains disabled.
- Content is never read by PHP GET. Static Apache maps `/uploads/YYYY/{filename}` to `backend/public/upload/YYYY/{filename}` (the same verified Product upload rule). Public paths only for newly validated avatar filenames; historical private files cannot be exposed by a guessed URL.
- Naming and constraints retained from baseline: `YYYYMMDDavatar{seven digits of Unix seconds}.jpg|png|webp`, max 2 MiB, accepted input JPEG/PNG/WebP, max output dimensions 2048x2048. Shared `PublicImageStorage` validates both MIME and image bytes and strips/re-encodes metadata.
- Successful response envelope `data` is `{user_id, has_avatar, avatar_url}`. No private `avatar_filename`, raw bytes or disk path. `Auth/me` and `security/users` already project public URLs separately.

## Authorization and consistency

1. Authenticate via Shield, enforce server-side write gate `DINKERP_M33B_WRITE_MODE=enabled`; CI4 route uses existing CSRF/session and CORS protections.
2. Fetch the **persisted** target row, excluding soft-deleted Users, *before* multipart inspection. A session actor with exactly the target User ID receives the baseline **self-service** exception. Other targets require `user.view` then `user.edit` through `UnifiedAccessService` using the persisted row. No unconditional global `user.edit` bypass.
3. Reject missing/invalid multipart, oversized payload and invalid image bytes with frozen bilingual error codes. Store new file first, then in an independent SQL transaction compare-and-swap `avatar_filename` **and** current `status`, `active`, non-deleted target and `id`. SQL affected rows must be 1; conflicts return `USER_AVATAR_CONCURRENT_UPDATE` (409).
4. If metadata fails or concurrent request wins, rollback and remove newly staged image. If metadata commits, remove old public avatar only if no User row still refers to it. Legacy private files are deliberately not unlinked by public storage. If post-commit old-file cleanup fails, do not claim the database mutation failed; log a deferred cleanup warning.
5. No new database table/migration, Apache change or code rewrite of frozen language catalogs.

## Offline verification

```powershell
cd D:\XAMPP\htdocs\erp
# Apply only after the M6.3A.1 patch already applied.
git apply --check .\M6.3B-user-avatar-public-upload.patch
git apply .\M6.3B-user-avatar-public-upload.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Runs `m63b_avatar_contract_pure.php`, `m63b_avatar_rules_pure.php`, `m63b_avatar_security_pure.php`, `m63b_avatar_storage_pure.php`, `m63b_avatar_cas_fixture.php`. The fixture checks authorization branching, idempotent delete, shared-file retention, old private file safety, database CAS conflicts/rollback. No live HTTP or MariaDB are accessed.

## DEV HTTP verification (not included in source pass)

- For own avatar, use a **disposable DEV user or an explicitly approved Avatar**; existing real-user photo must not be overwritten by an automated test. Owner self-service is the primary case even without `user.edit`. The target ID must match the *authenticated session* `auth/me` ID, not an arbitrary parameter.
- Confirm CSRF cookie and session from `GET /api/v1/auth/csrf` and login. Verify 401 without a session; 403 if write gate is disabled; 422 bilingual invalid size/type/content. Test a separate account without VIEW/EDIT of the target for 404/403 respectively; test authorized admin target-scoped edit separately.
- `POST /api/v1/users/{id}/avatar` with multipart part `image` (actual PNG/JPEG/WebP) should return HTTP 200 and `data.avatar_url` (`/uploads/YYYY/...`). Verify the static `GET` of that URL with Apache returns a correctly decoded image, and `auth/me` / `security/users` show `has_avatar=true` where authorized.
- Re-upload a second image: old public URL must return 404 after successful replace and new URL must return 200; DELETE must return HTTP 200 `has_avatar=false`, `avatar_url=null`, and removed public file should not be served. Verify `users.avatar_filename IS NULL` by separate read-only audit before claiming SQL parity. Test concurrency (two overlapping writers), forced metadata failure/orphan cleanup, and compare with baseline independently.
- **Never** test on a production account or remove its avatar without explicit approval; source/offline PASS is not the same as DEV HTTP or production acceptance.

## Frozen legacy PUT caveat

`backend_baseline/app/Config/Routes.php` also exposes `PUT /api/v1/users/{id}/avatar` for older clients. **The 150-operation frozen v1.2.4 manifest contains only POST and DELETE**. No extra PUT route was introduced here because doing so would silently expand the frozen API surface; approval and a separate compatibility contract would be required if a PUT client is still in use.

## Remaining work

DEV HTTP multipart/GD/Apache/SQL, real User PBAC state/scope, concurrent CAS and forced SQL failure are **NOT TESTED** by this patch. File attachments beyond Product Image and User Avatar remain out of scope until a baseline-backed resource/storage contract is reviewed.
