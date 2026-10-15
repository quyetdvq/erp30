# User Full Name / Private Avatar / Optional Multi-Group v1.2 — Release Gate E

**Project:** ERP v1.1.4 / CodeIgniter Shield / Vue 3
**Change:** `CHANGE-USER-V12-001` (incremental, additive)
**Date:** 2026-10-15
**Status:** **PARTIAL — not approved for production until Gate E evidence is attached**

This evidence sheet records results reported by the Windows/XAMPP operator. It is not proof of a deployment, a full-suite pass, a production database backup, or a restored baseline. Use the approved v1.2 spec and implementation plan as the normative design.

## 1. Applied source and database state (operator-reported)

The Windows checkout at `D:\XAMPP\htdocs\erp` has applied, in order:

1. A1 — application Shield provider + `users.full_name` / `users.avatar_filename` migration.
2. A2, A2.1, A2.2 — User name, optional 0..n Group memberships, rollback and CI4 Time fixes.
3. B1, B2/B3 — private Avatar storage, service, GET/PUT/DELETE endpoints.
4. C1, C1.1 — canonical OpenAPI + Windows CRLF/YAML fixes.
5. D — Security User create/edit UI, Avatar manager, Profile, Header and session safety.

The operator showed migration `2026-10-15-000018_AddShieldUserDisplayFields` applied as **batch 7**. This records an applied migration, not verification of the actual database name or proof of a production backup. `erp_test` is the **only authorized target for destructive/integration tests**; never use the `erp` database for PHPUnit runs with database resets.

## 2. Targeted test evidence already reported

| Gate | Command / scope | Reported result | Status |
|---|---|---|---|
| A1 | `UserDisplayFieldsStructureTest.php` | 1 test, 3 assertions | PASS — targeted |
| A2 | `UserIdentityGroupCreationTest.php` | 4 tests, 69 assertions | PASS — targeted |
| A2 | `UserIdentityInputTest.php` | 4 tests, 38 assertions | PASS — targeted |
| A2 | `AppShieldUserModelTest.php` | 1 test, 6 assertions | PASS — targeted |
| A2 | `ShieldSecurityUserRepositoryTest.php` | 1 test, 6 assertions | PASS — targeted |
| B | `UserAvatarApiContractTest.php` | 3 tests, 46 assertions | PASS — targeted |
| B | `UserAvatarServiceTest.php` | 5 tests, 66 assertions | PASS — targeted |
| B | `UserAvatarStorageTest.php` | 6 tests, 93 assertions | PASS — targeted |
| B | CLI GD / `imagewebp()` | `bool(true)`, `bool(true)` | PASS — capability |
| C | `openapi:v12-contract:test` | 6/6 | PASS — targeted |
| C | `P1Api090ContractRegressionTest.php` | 6 tests, 78 assertions | PASS — targeted |
| C | `openapi:lint` | Redocly valid | PASS |
| C | `openapi:generate:check` | Generator OK; 17 tool-owned TS files checked | PASS |
| D | `user:v12-ui:logic:test` | 8/8 | PASS — targeted |
| D | Three specified Vitest files | 11/11 | PASS — targeted |
| D | `vue-tsc --noEmit` | Zero reported errors | PASS |
| D | Security + Auth/CSRF Playwright | 24/24 | PASS — targeted |

**Important distinction:** Counts above are individual test invocations. They are not a substitute for an unfiltered full PHPUnit/Vitest/Playwright run. The historical **252/252 Playwright** checkpoint predates this feature and is not a fresh v1.2 full-suite result.

## 3. Gate E verification on the operator's machine

Run in PowerShell, using the existing checked-out branch. Record full output, exit code and `git rev-parse HEAD` in the evidence folder or in the release ticket.

### Backend — only on verified `erp_test`

Before running database tests, independently confirm that the **CLI testing environment** is connected to a backed-up database named **exactly `erp_test`**. Do not rely on the `default` label in `migrate:status`: it is a migration namespace/group label, not a database name.

```powershell
cd D:\XAMPP\htdocs\erp\backend
$env:CI_ENVIRONMENT = 'testing'
php spark migrate:status
# STOP unless the configured testing database was independently verified as erp_test.
php vendor/bin/phpunit --no-coverage
Remove-Item Env:CI_ENVIRONMENT
```

If PHPUnit fails, save the failed test names and stack traces. Do not remove or bypass the downgrade guard on production; the E2 test-only exception is restricted to an explicitly opted-in PHPUnit run on `erp_test`. Full PHPUnit initial attempt: **609 tests, 484 errors** — all the reported errors came from `AddShieldUserDisplayFields::down()` refusing to discard populated account metadata while `DatabaseTestTrait` was refreshing migrations. This is a **BLOCKED** full-suite run, not 484 independently validated business defects.

**E2 test-only remedy (requires verification):** `phpunit.dist.xml` opts into test-schema reset through `ERP_PHPUNIT_ALLOW_DISPLAY_FIELD_RESET=1`. The migration permits rollback with populated display columns only when **all three conditions** hold: `ENVIRONMENT === 'testing'`, database name exactly `erp_test`, and that explicit PHPUnit flag is present. In any other context, the original `Refusing downgrade` guard remains effective. The reset **deletes test data**; ensure `erp_test` is disposable and never apply this procedure to `erp`.

First run the new read-only preflight/policy test (it uses no `DatabaseTestTrait`):

```powershell
$env:CI_ENVIRONMENT = 'testing'
php vendor/bin/phpunit --no-coverage tests/unit/UserDisplayMetadataResetPolicyTest.php
# STOP on any error or if the test fails to confirm DATABASE() = 'erp_test'.
php vendor/bin/phpunit --no-coverage tests
Remove-Item Env:CI_ENVIRONMENT
```

**Full backend PHPUnit after E2: PENDING operator run.**

### Contract, frontend typecheck and full regression

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm run openapi:v12-contract:test
pnpm run openapi:lint
pnpm run openapi:generate:check
pnpm exec vue-tsc --noEmit
pnpm exec vitest run
pnpm exec playwright test
```

The full Playwright invocation excludes optional real-backend tests under `e2e/live/` by default, per project config. Capture actual totals across the configured browsers (Chromium, Firefox and WebKit). A new profile regression is in `e2e/user-profile-avatar.spec.ts`; run it standalone first when verifying the E patch:

```powershell
pnpm exec playwright test e2e/user-profile-avatar.spec.ts
```

Full Vitest results: **174/174 PASS** (39 files), operator-reported. Full Playwright results: **NOT YET REPORTED**. New E2E Profile/Avatar results: **6/6 PASS**, operator-reported.

### Git and generated client review

```powershell
cd D:\XAMPP\htdocs\erp
git diff --check
git status --short
git rev-parse HEAD
git diff --stat
```

Make sure generated SDK changes derive solely from `pnpm run openapi:generate`, no `vendor/` or `node_modules/` is committed, no new account-creation route was added and the User→Employee/Contact independence remains unchanged.

## 4. Staging smoke matrix — manual sign-off required

On a separately backed-up staging environment, record PASS/FAIL, actor/target IDs (non-sensitive), response codes, timestamp, defect reference and evidence for each scenario:

| ID | Scenario | Required invariant | Result |
|---|---|---|---|
| E-01 | Legacy POST with no `name` and no `group_ids` | HTTP 201, `name = NULL`, zero implicit Group memberships | PENDING |
| E-02 | Create new User with name and zero Groups | New User created exactly once; zero memberships | PENDING |
| E-03 | Create User with two authorized ACTIVE Groups | Both and only those Group IDs assigned atomically | PENDING |
| E-04 | Second Group denied/inactive during creation | No orphan User, identity or membership after rollback | PENDING |
| E-05 | Edit only Họ và tên on User with Group memberships | Group memberships, email and password remain unchanged | PENDING |
| E-06 | Self-service upload valid JPEG/PNG/WebP | Private storage; encoded validated file; `has_avatar = true` | PENDING |
| E-07 | PUT failure after User POST 201 | Retry only PUT; never duplicate account POST | PENDING |
| E-08 | Replace, race two PUTs, race PUT/DELETE | No reference to deleted file; rejected writer cleans only own new file | PENDING |
| E-09 | Another User without PBAC `user.view`/`user.edit` | No cross-user avatar access; no filename/path disclosure | PENDING |
| E-10 | Logout/login another User with pending image/auth response | No previous user's name, avatar or permissions presented | PENDING |
| E-11 | Refresh page / legacy NULL name/avatar | Username/initials fallback; no broken image URL | PENDING |
| E-12 | Invalid image, >2MiB, too large dimensions, decoder/storage failure | Rejected; old Avatar unchanged; no filesystem path returned | PENDING |

## 5. Preproduction deployment and data-preserving rollback

**Preflight:** secure application and DB backup, test **restore** procedure, record existing User count and nullable columns, check `utf8mb4`, confirm private `WRITEPATH/uploads/user-avatars` exists outside `public/`, has minimum required permissions for the PHP service identity, and is included in the asset backup. Use a maintenance window and explicit owner approval for any production schema change.

**Forward:** deploy reviewed source plus generated SDK, confirm the additive `000018` migration was applied to the **intended** database exactly once, then run smoke tests with least-privilege accounts. Restrict credentials and never expose raw Avatar paths, filenames, file bytes in JSON or debug dumps.

**Rollback:** if the release fails, roll back the **application binary/source** to the prior revision, but **retain** `users.full_name`, `users.avatar_filename` and all Avatar files pending a data audit. Do not run a broad `migrate:rollback` in production. The migration `down()` intentionally rejects dropping non-NULL data. Delete orphan files only after proving there are no DB references and the agreed retention period has passed.

No production deployment, staging smoke completion, restore drill or rollback rehearsal has been reported. **Release approval: BLOCKED until the missing Gate E evidence is reviewed.**
