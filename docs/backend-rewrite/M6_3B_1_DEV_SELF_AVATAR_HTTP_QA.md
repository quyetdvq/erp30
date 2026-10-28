# M6.3B.1 - DEV Shield User Self-Avatar HTTP / MariaDB / Apache QA

## Scope and invariants

- Runs against the currently configured **local XAMPP DEV** backend through `https://127.0.0.1` with pinned `Host: api.monmin.net`. Does not contact remote hosts, follow redirects, add API routes, create Users, or modify Numbering/Access/Lifecycle rules.
- Source inventory remains **129/150**. This patch adds only a QA runner, a strict transport, an offline safety suite and one **SELECT-only** Spark audit.
- M6.3B.1 validates the narrow **self-service exception** by requiring a disposable, active Shield account whose `auth/me` has **no `user.edit`** permission, and whose own `avatar_filename IS NULL` in MariaDB. The authenticated actor ID must equal the target user ID.
- **Never use `quyetdv` or any existing account with an important avatar** unless it is a dedicated, disposable, currently avatar-empty DEV fixture. An existing avatar blocks the test; the runner will never automatically back it up or overwrite it.
- Default command is PLAN only (**zero HTTP and zero SQL**). `--preflight` performs read-only SELECT, login and GET. `--apply --confirm=DEV_SELF_AVATAR_QA` sends image POST/DELETE and updates the selected disposable account; SQL audit remains SELECT-only.

## Install and offline checks

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\M6.3B.1-dev-self-avatar-http-qa.patch
git apply .\M6.3B.1-dev-self-avatar-http-qa.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expected: **106/106 offline**, **6/6 source architecture**, **129/150 unchanged**. These are not DEV HTTP results.

## Choose a safe fixture

Use a **dedicated ordinary DEV user without `user.edit`** and **without an avatar**. Find its integer ID in the authenticated frontend `GET /api/v1/auth/me` response. Do not invent an ID or use the current admin session's ID when logging in as the test user. Before any HTTP mutation:

```powershell
php spark m63b1:db-audit <DEV_USER_ID> EMPTY
php tools\m63b1_dev_avatar.php --target-user-id=<DEV_USER_ID>
```

`m63b1:db-audit` must show `PASS_DEV_AVATAR_METADATA_EMPTY`. This command uses the current backend `.env` and runs **SELECT only**. It refuses inactive, soft-deleted or nonempty-avatar users.

## HTTP/SQL read-only preflight

```powershell
$env:M63B_DEV_USER = '<DEV_TEST_USERNAME>'
$secure = Read-Host 'DEV test user password' -AsSecureString
$env:M63B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secure).Password
try {
    php tools\m63b1_dev_avatar.php --target-user-id=<DEV_USER_ID> --preflight
}
finally {
    Remove-Item Env:\M63B_DEV_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:\M63B_DEV_USER -ErrorAction SilentlyContinue
}
```

Expected `PASS_DEV_SELF_AVATAR_PREFLIGHT`, `owner=1 sql=1 gd=1`. The test requires: CSRF/login and authenticated Shield actor, exact same ID, active status, no avatar, absence of `user.edit` base grant, full GD codecs, and independent live MariaDB SELECT. No avatar mutation is sent.

## Explicit DEV mutation test

Run **only after** the preflight has passed, and only on that disposable empty-avatar self-service account:

```powershell
$env:M63B_DEV_USER = '<DEV_TEST_USERNAME>'
$secure = Read-Host 'DEV test user password' -AsSecureString
$env:M63B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secure).Password
try {
    php tools\m63b1_dev_avatar.php --target-user-id=<DEV_USER_ID> --apply --confirm=DEV_SELF_AVATAR_QA
}
finally {
    Remove-Item Env:\M63B_DEV_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:\M63B_DEV_USER -ErrorAction SilentlyContinue
}
```

The runner checks:

1. Four negative multipart requests (missing image and GIF, each in English/Vietnamese) return 422 with the frozen `USER_AVATAR_*` codes, different localized messages, and no metadata writes.
2. Upload real PNG A: HTTP 200 and a baseline-format `/uploads/YYYY/YYYYMMDDavatarNNNNNNN.png` URL; `GET auth/me` and MariaDB SELECT agree; Apache static GET 200 serves **exact** physical `public/upload/YYYY` bytes.
3. Replace with real PNG B: HTTP 200, different URL; both MariaDB and `auth/me` point to B. A must be removed from disk and HTTP 404.
4. DELETE: HTTP 200, `auth/me` has no avatar, `users.avatar_filename IS NULL`, B no longer exists and returns HTTP 404; unrelated Shield profile fields remain unchanged.

Expected success:

```text
M6.3B.1 DEV AVATAR: PASS_DEV_SELF_AVATAR_HTTP_FS_SQL; phase=complete
M6.3B.1 HTTP: negative=4 localized=2 UPLOAD=200 REPLACE=200 DELETE=200
M6.3B.1 FILES: first=200 second=200 old_gone=404 removed=1 sql_final_empty=1
```

Finally run `php spark m63b1:db-audit <DEV_USER_ID> EMPTY` again.

## Failure policy

- **Do not rerun `--apply` after any failure**. A previous upload may have committed and left a valid public avatar. Inspect the report and SQL state before deciding on any cleanup.
- Sanitized JSON reports: `backend/writable/m63b1/reports/m63b1_dev_*.json`. Reports contain status, stage, stable error code and URL paths only; never password, cookies, CSRF or raw HTTP bodies.
- This is **not** an admin delegation test. Target-scoped `user.view`+`user.edit`, unauthorized nonowner cases, two-writer CAS races, forced storage/SQL failure, orphan cleanup and independent baseline HTTP parity require separate DEV verification. The existing M6.3B source tests cover their structural rules but do not prove runtime behavior.
- The `GET /uploads/...` check uses Apache static serving only; no PHP image-byte GET endpoint is introduced.
