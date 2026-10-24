# M6.3B.2 — DEV Admin-Delegated Avatar PBAC, HTTP, Apache, MariaDB QA

## Purpose and safety boundary

- M6.3B.1 was accepted on the owner's XAMPP: self-service User `3` (`toandv`) passed upload, replace, delete and independent final SQL audit. This does **not** prove delegated administration or live CAS races.
- M6.3B.2 uses **two different authenticated Shield sessions**: a privileged administrator and an unprivileged owner of an avatar-empty, active **disposable DEV account**. The first session may manage the target only when the persisted `user.view` and `user.edit` PBAC decisions allow it. The second session must be unable to manage the administrator's Avatar.
- The runner is **PLAN ONLY** by default, with no SQL or HTTP. `--preflight` uses SELECT-only Spark audit plus session login and GET, with **no Avatar POST/DELETE**. Login may update session metadata. `--apply` requires an explicit confirmation token and sends mutations; it leaves the target with `avatar_filename=NULL` on successful completion.
- Reuses the verified M6.3B.1 Apache-only HTTP transport pinned to `https://127.0.0.1` + `Host: api.monmin.net`. It never follows redirects, connects to remote hosts, creates Users, changes PBAC/Lifecycle, changes migration/Apache, or adds API routes.
- `backend/public/upload/YYYY/...` is the only image storage; no image byte-read PHP API is added. Baseline filenames and 2 MiB Avatar limit are preserved.
- **Do not run on an active business account with a real Avatar.** Both SQL and authenticated target `/auth/me` must show an empty Avatar before upload. The admin's own Avatar is never changed: unauthorized nonowner probes intentionally send **no image bytes**.

## Install

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\M6.3B.2-dev-admin-avatar-delegation-qa.patch
git apply .\M6.3B.2-dev-admin-avatar-delegation-qa.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expected **107/107 offline PASS**, M3.4D architecture **6/6**, route inventory still **129/150**. This is NOT a live HTTP result.

## Select the two DEV accounts

Previously observed DEV fixture: target `toandv`, User ID `3`, with `avatar_filename=NULL` and no base `user.edit`. Candidate administrator: `quyetdv`, **only if** logged-in `/auth/me` has `user.view` and `user.edit` and target record's PBAC actually allows delegated editing. The preflight rejects a missing base grant, a non-distinct actor, a nonempty Avatar, wrong target identity, or missing GD codec.

Verify the disposable target remains empty:

```powershell
php spark m63b1:db-audit 3 EMPTY
php tools\m63b2_dev_admin_avatar.php --target-user-id=3
```

Preflight (enter each password separately; never place passwords in CLI arguments):

```powershell
$env:M63B2_ADMIN_USER = 'quyetdv'
$env:M63B2_TARGET_USER = 'toandv'
$adminSecure = Read-Host 'Admin DEV password' -AsSecureString
$targetSecure = Read-Host 'Target DEV password' -AsSecureString
$env:M63B2_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $adminSecure).Password
$env:M63B2_TARGET_PASSWORD = [System.Net.NetworkCredential]::new('', $targetSecure).Password
try {
    php tools\m63b2_dev_admin_avatar.php --target-user-id=3 --preflight
}
finally {
    Remove-Item Env:\M63B2_ADMIN_PASSWORD,Env:\M63B2_TARGET_PASSWORD,Env:\M63B2_ADMIN_USER,Env:\M63B2_TARGET_USER -ErrorAction SilentlyContinue
}
```

Expected:

```text
M6.3B.2 DEV ADMIN AVATAR: PASS_DEV_ADMIN_AVATAR_PREFLIGHT; phase=complete
M6.3B.2 checks: distinct_admin=1 owner=1 sql=1 gd=1
```

`--preflight` **does not** prove target-scoped edit PBAC; that check occurs first in `--apply` using safe malformed multipart requests.

## Explicit delegated Avatar QA

Run only when the target is disposable and Preflight passed. This will upload two test PNG images through the **administrator** session, then delete the final Avatar. A successful run restores the target to empty Avatar metadata.

```powershell
$env:M63B2_ADMIN_USER = 'quyetdv'
$env:M63B2_TARGET_USER = 'toandv'
$adminSecure = Read-Host 'Admin DEV password' -AsSecureString
$targetSecure = Read-Host 'Target DEV password' -AsSecureString
$env:M63B2_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $adminSecure).Password
$env:M63B2_TARGET_PASSWORD = [System.Net.NetworkCredential]::new('', $targetSecure).Password
try {
    php tools\m63b2_dev_admin_avatar.php --target-user-id=3 --apply --confirm=DEV_ADMIN_AVATAR_QA
}
finally {
    Remove-Item Env:\M63B2_ADMIN_PASSWORD,Env:\M63B2_TARGET_PASSWORD,Env:\M63B2_ADMIN_USER,Env:\M63B2_TARGET_USER -ErrorAction SilentlyContinue
}
```

Checks performed, in order:

1. SELECT-only SQL confirms User `3` is active and Avatar empty; two logged-in sessions are distinct, the target lacks base `user.edit`, the admin has both `user.view` and `user.edit`.
2. Admin sends `POST /users/3/avatar` **without an image**, both `en`/`vi`. Authorized target-scoped PBAC is proven only if both return `422 USER_AVATAR_INVALID_CONTENT` and messages are localized; no file or metadata is written.
3. Unprivileged owner sends **empty multipart** to the admin's Avatar route, both `en`/`vi`. Requires `404 USER_NOT_FOUND` (concealed) or `403 USER_AUTHORIZATION_DENIED` (explicit denial), not `422`. This probe never sends valid image bytes and checks the admin's own Avatar remains unchanged.
4. Admin uploads PNG A: HTTP 200; target account `auth/me` and independent MariaDB SELECT point to A; Apache image bytes are identical to the file in `public/upload`.
5. Admin replaces with PNG B: HTTP 200, different file; A removed from disk and static GET 404, B served with matching bytes.
6. Admin deletes target Avatar: HTTP 200, MariaDB `avatar_filename=NULL`, target `/auth/me` reports no Avatar, B removed from disk and static GET 404; non-media profile and admin avatar remain unchanged.

Expected:

```text
M6.3B.2 DEV ADMIN AVATAR: PASS_DEV_ADMIN_AVATAR_HTTP_FS_SQL; phase=complete
M6.3B.2 PBAC: admin_probe=2 owner_denied=2
M6.3B.2 HTTP: UPLOAD=200 REPLACE=200 DELETE=200
M6.3B.2 FILES: first=200 second=200 old_gone=404 removed=1 final_sql_empty=1
```

Independent final SQL check:

```powershell
php spark m63b1:db-audit 3 EMPTY
```

## Failure handling and untested boundaries

- If any `--apply` phase fails, **do not rerun blindly**. SQL or file writes may have committed; review the sanitized report at `backend/writable/m63b2/reports/m63b2_dev_*.json` and inspect the target's current Avatar first.
- The report contains phase, HTTP status, stable `error.code`, public Avatar URL paths, booleans and markerless account IDs; **never** credentials, session cookies, CSRF, or raw response bodies.
- An admin with both base grants can still be denied by target-record PBAC. This is expected: the empty-image authorization probe returns 403 or 404 and aborts without modifying media. Do not change or bypass PBAC solely for QA.
- **NOT TESTED**: deterministic overlapping two-writer compare-and-swap (ordinary parallel POST may both succeed sequentially), forced SQL storage failures, independent baseline HTTP parity, attachments outside the frozen API contract, or production readiness.
- M6.3B.2 adds **zero routes** and does not modify the Product/Avatar business services. Source inventory remains **129/150**.
