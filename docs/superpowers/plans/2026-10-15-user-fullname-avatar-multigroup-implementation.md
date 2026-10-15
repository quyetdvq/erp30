# User Full Name, Private Avatar and Multi-Group Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing ERP v1.1.4 Shield account flow with `users.full_name`, `users.avatar_filename`, private avatar management and optional assignment to multiple authorization groups, without introducing a second account-creation API or coupling User to Employee/Contact.

**Architecture:** Extend the Shield users table additively and bind a custom Shield UserModel through the existing provider. Keep account creation, name updates and group memberships on one verified database connection; manage private avatar files through an independent service and authenticated binary API. Update canonical OpenAPI and regenerate the client before implementing Vue screens.

**Tech Stack:** PHP 8.2+, CodeIgniter 4 / Shield 1.4.1, MariaDB, PHPUnit 10, OpenAPI / Hey API, Vue 3, TypeScript, Pinia, Naive UI, Vitest 4 and Playwright.

**Spec:** `docs/superpowers/specs/2026-10-15-user-fullname-avatar-multigroup-design.md` (approved business/architecture spec v1.2; supersedes v1.1).

## Global Constraints

- The user-reported baseline is `vue-tsc` PASS, Employee User Lookup Vitest 4/4 PASS and Playwright **252/252** PASS. This is reported evidence, not a locally rerun suite.
- Current source snapshot must include, in order, the four previously applied patches: frontend E2E fixes; workflow state selector; employee User lookup; its Vitest follow-up. Preserve all existing changes in the actual Git checkout.
- Existing `POST /api/v1/users`, `PATCH /api/v1/users/{id}`, `PATCH /api/v1/auth/profile` remain the account/profile mutation routes. Only the three proposed avatar resource routes are new.
- Only `users.full_name VARCHAR(255) NULL` and `users.avatar_filename VARCHAR(128) NULL` are added. Do not write human names to `auth_identities.name` or invent a `user_profiles` table.
- `name` is required for the new Create UI but optional on the legacy POST API. On PATCH, omitted means unchanged; nonblank string means update; `null` and whitespace-only are invalid.
- `group_ids` is an optional list of distinct canonical positive decimal ID strings: omitted and `[]` both mean zero memberships. No automatic default group, direct permissions, group replacement or group patch through User PATCH.
- `user.create` does not imply `authorization_group.assign_member`. Check every selected active/visible group on the server using PBAC and preserve `ABSOLUTE_DENY`.
- User and Employee/Contact remain independent. No synchronizing or auto-creation in either direction.
- Avatar: optional JPEG/PNG/WebP, max 2 MiB, max 2048x2048 pixels, decoder verification and re-encoding; private `WRITEPATH/uploads/user-avatars`; never accept a client-supplied filename.
- Avatar uploads occur **after** successful account creation. On a failed PUT, retry only PUT using the already created user ID, not POST.
- Database integration or destructive tests run only with `CI_ENVIRONMENT=testing` and database name **exactly** `erp_test`; never reset `erp`. Production schema changes require separately approved rollout/backup.
- No vendor edits, no hand-edited generated SDK. Keep existing P1 domain/PBAC/authorization semantics and Table First UI. Run project change-control/ledger validation in the real Git checkout before code changes.
- An implementation task is not DONE without executed tests and a scoped diff review; do not declare the new feature verified from the 252/252 pre-feature checkpoint.

## Review Focus

1. **Legacy account without name/groups:** POST with only the original four fields still gives 201, NULL name, zero membership even if `AuthGroups.defaultGroup='user'`. Pin in Task 3 integration tests.
2. **One denied or inactive group among two IDs:** creating a user leaves zero users, identities and memberships, including failure on the *second* assignment. Pin in Task 3 database tests.
3. **Two uploads or upload + delete racing:** DB never references a deleted image; failed contender cleans only its own new file. Pin in Task 5 concurrency tests.
4. **Create returns 201, then avatar PUT returns 403/500:** form displays created account ID, keeps retryable image state and never issues another POST. Pin in Task 9 E2E.
5. **Existing user has `NULL` name/avatar and unauthorized avatar access:** fallback works without staff data; 403/404 decisions do not reveal hidden users or internal filesystem paths. Pin in Tasks 2, 6 and 10.

## Code Ownership Map

| Component | Owned files/interfaces |
|---|---|
| Schema + Shield provider | `backend/app/Database/Migrations/2026-10-15-000018_AddShieldUserDisplayFields.php`, `backend/app/Models/UserModel.php`, `backend/app/Config/Auth.php` |
| Account name projection | `backend/app/Repositories/ShieldSecurityUserRepository.php`, `backend/app/Controllers/Api/Auth.php`, `backend/app/Services/ShieldUserIdentityService.php` |
| Atomic group creation | `backend/app/Services/ShieldUserIdentityService.php`; reuse `AuthorizationGroupService::assignMember()` and `ShieldIdentityAdapter` on same DB connection |
| Avatar file/storage | new `backend/app/Services/UserAvatarStorage.php` (only byte verification, canonical filename, re-encoding, file I/O) |
| Avatar business/access | new `backend/app/Services/UserAvatarService.php` (authorization, CAS metadata update, cleanup); no Product permissions |
| Avatar transport | new `backend/app/Controllers/Api/UserAvatars.php`, `backend/app/Config/Routes.php`; error mapper/translations |
| Contract/client | `contracts/openapi/openapi.yaml`, generated `frontend/src/api/generated/*` through configured generator |
| Admin form | `frontend/src/features/security/pages/SecurityUsersView.vue`, optional extracted `frontend/src/features/security/user-editor/useUserCreateGroups.ts` |
| Avatar presentation | new `frontend/src/services/userAvatar.ts` and `frontend/src/components/users/UserAvatar.vue`, optional editor component |
| Self-profile/auth | `frontend/src/features/account/pages/ProfileView.vue`, `frontend/src/services/auth.ts`, `frontend/src/stores/auth.ts`, shell avatar hook where present |
| Verification/docs | backend database/unit tests, frontend Vitest/E2E, i18n, change-control/as-built release evidence |

---

### Task 1: Additive Shield User columns and provider

**Files:** Create `backend/app/Database/Migrations/2026-10-15-000018_AddShieldUserDisplayFields.php`, `backend/app/Models/UserModel.php`; modify `backend/app/Config/Auth.php`; test `backend/tests/database/UserDisplayFieldsMigrationTest.php`, `backend/tests/unit/AppShieldUserModelTest.php`.

**Interfaces:** `App\Models\UserModel extends CodeIgniter\Shield\Models\UserModel`; inherited fields and hooks remain, `$allowedFields` additionally contains `full_name` and `avatar_filename`; `Config\Auth::$userProvider` references the app class.

- [ ] **Step 1: Add failing migration/provider tests.** Assert the configured Shield users table has two nullable columns after migration, old users read as NULL, `App\Models\UserModel` persists both fields, and legacy Shield user/identity operations still work. Test `down()` refusing populated columns.
- [ ] **Step 2: Run targeted PHPUnit on the isolated `erp_test` DB.** Expected RED for missing columns and app provider; verify the database name before any migrations.
- [ ] **Step 3: Implement the migration/provider.** Use `config('Auth')->tables['users']`, respect table prefix, no backfill from identity/Employee/Contact, and ensure rollback refuses destructive drops.
- [ ] **Step 4: Re-run tests and `php -l` on new PHP files.** Expected GREEN plus login, email identity and password-hash regression.
- [ ] **Step 5: Review the scoped diff and commit** `feat(iam): extend Shield users with display metadata` in the real Git checkout.

### Task 2: Name projection and authenticated profile payload

**Files:** Modify `backend/app/Repositories/ShieldSecurityUserRepository.php`, `backend/app/Controllers/Api/Auth.php`; extend `backend/tests/unit/ShieldSecurityUserRepositoryTest.php`, new `backend/tests/database/UserNameProfileApiTest.php`.

**Interfaces:** Existing `SecurityUser.name` remains `string|null` but is sourced from `u.full_name`; auth user payload adds `name: string|null`, `has_avatar: bool`. `PATCH /auth/profile` optionally accepts `name`; no group/privilege fields allowed.

- [ ] **Step 1: Write failing tests.** Verify that changing `auth_identities.name` cannot change displayed full name; email still comes from email-password identity; legacy NULL names yield null; `/auth/me`, login, self-profile return `name` and `has_avatar`.
- [ ] **Step 2: Execute targeted tests.** Expected RED for new response fields/source.
- [ ] **Step 3: Implement a single canonical name validator** (e.g. `App\Services\UserDisplayName::normalize(mixed $value): string`, trim, nonblank Unicode, <=255 characters), projection, self-profile PATCH and app-model saves; keep existing profile username/email/password behavior. Use a safe UTF-8 length check, not byte count.
- [ ] **Step 4: Execute tests.** Verify token/password/email unchanged after name-only edit, legacy profile patch remains valid and no duplicate rows from identity joins.
- [ ] **Step 5: Review/commit** `feat(iam): expose Shield user names in projections and profile`.

### Task 3: Atomic POST/PATCH with optional multi-group assignment

**Files:** Modify `backend/app/Services/ShieldUserIdentityService.php`; adjust `AuthorizationGroupService.php` and/or `ShieldIdentityAdapter.php` **only if same-connection integration tests demonstrate a wiring issue**; create `backend/tests/database/UserIdentityGroupCreationTest.php`, extend `backend/tests/unit/P1Auth005SecurityAdministrationTest.php`.

**Interfaces:** Preserve public `create(array $payload): array` and `update(int $id, array $payload): array`; add private `groupIds(mixed $input): array` returning distinct validated positive ID strings; use the existing `AuthorizationGroupService::assignMember(int $groupId, int $userId, bool $assign = true, ?OperationContext $operation = null): bool` with the caller's OperationContext and connection.

- [ ] **Step 1: Write RED real-DB tests** for legacy four-field create -> 201/NULL name/**zero** Shield memberships; create with `name` plus two ACTIVE group IDs -> two memberships; duplicate/scalar/null/invalid IDs rejected; inactive/hidden/denied group -> no User; second assignment persistence failure -> complete rollback; PATCH `name` alone and alongside email; reject `group_ids` on PATCH.
- [ ] **Step 2: Run tests in `erp_test`.** Verify failure is contract-related and exercise the actual connection and membership tables rather than mocks.
- [ ] **Step 3: Extend create/update and dependency wiring.** Use app Shield UserModel bound to the *same* CI DB connection as the service and identity adapter, preserve user/password policy and existing error mapping; recheck PBAC/status for each group inside transaction; do not call `replaceMembers()` or `addToDefaultGroup()`.
- [ ] **Step 4: Run DB-backed tests plus Shield login/reset-password and user permissions regressions.** Expected complete atomicity, no default group, and no partial writes.
- [ ] **Step 5: Review/commit** `feat(iam): atomically assign optional authorization groups at creation`.

### Task 4: Private avatar image validation and storage

**Files:** Create `backend/app/Services/UserAvatarStorage.php`; test `backend/tests/unit/UserAvatarStorageTest.php` and genuine image fixtures under `backend/tests/fixtures/user-avatars/`.

**Interfaces:** `inspect(string $temporaryFile): array{mime:string,extension:string,size:int,width:int,height:int}`; `store(string $temporaryFile): array{filename:string,mime:string}`; `path(string $filename): string`; `delete(string $filename): void`. Server file key matches `\A[a-f0-9]{40}\.(jpg|png|webp)\z`.

- [ ] **Step 1: Write RED file tests** with real JPEG/PNG/WebP, EXIF-bearing JPEG, empty file, 2 MiB overflow, 2049px image, corrupt data, SVG/GIF, mismatched MIME/extension, path traversal and re-encoded output without EXIF/animation.
- [ ] **Step 2: Run storage tests.** RED until dedicated storage is implemented; verify GD supports JPEG/PNG/WebP (if not, BLOCK the feature and report missing extension/codec).
- [ ] **Step 3: Implement private storage** under `WRITEPATH/uploads/user-avatars`, verify finfo + decoder + dimensions, re-encode with available GD codecs, random filename, restricted permissions, never trust original filename; do not persist user metadata in this class.
- [ ] **Step 4: Run storage tests and PHP lint.** Expected GREEN, including safe deletion and no client-controlled paths.
- [ ] **Step 5: Review/commit** `feat(iam): add validated private avatar storage`.

### Task 5: Avatar authorization, metadata and concurrency

**Files:** Create `backend/app/Services/UserAvatarService.php`; test `backend/tests/database/UserAvatarServiceTest.php` and concurrency worker `backend/tests/fixtures/user_avatar_concurrency_worker.php` if necessary.

**Interfaces:** `read(int $userId): array{path:string,mime:string}`; `upload(int $userId, string $temporaryFile): array{user_id:string,has_avatar:bool}`; `remove(int $userId): array{user_id:string,has_avatar:bool}`. Owner is identified by server session ID; non-owner operations use `user.view` for read or `user.edit` for mutation and existing visibility checks.

- [ ] **Step 1: Write RED database/security tests.** Cover self, authorized admin, denied/invisible targets; first upload/replace/delete; DB update failure preserves old image; concurrent replace/replace and replace/delete maintain a valid referenced file.
- [ ] **Step 2: Execute DB-backed tests** on `erp_test`, with temporary WRITEPATH; RED for missing service.
- [ ] **Step 3: Implement service.** Authorize before revealing upload or filesystem errors. Use DB row lock or conditional compare-and-swap metadata update in a transaction, clean newly created file on failure, delete old file only after successful commit, and never delete an image still referenced by another row. Log orphan-cleanup failures without logging personal data.
- [ ] **Step 4: Re-run targeted permission, conflict and concurrency tests.** Expected no dangling `avatar_filename` or unauthorized reads/writes.
- [ ] **Step 5: Review/commit** `feat(iam): enforce avatar ownership and safe replacement`.

### Task 6: Binary avatar HTTP endpoints and error contract

**Files:** Create `backend/app/Controllers/Api/UserAvatars.php`; modify `backend/app/Config/Routes.php`, `backend/app/Infrastructure/Api/ApiErrorMapper.php` and backend message mapping as required; test `backend/tests/database/UserAvatarApiTest.php`, extend `backend/tests/unit/CorsPreflightRouteTest.php`.

**Interfaces:** `GET /api/v1/users/{id}/avatar` -> binary with correct MIME / `Cache-Control: private, no-store` / `X-Content-Type-Options: nosniff`; `PUT` multipart field `image` -> HTTP 200 `{data:{user_id,has_avatar:true}}`; `DELETE` -> HTTP 200 `{data:{user_id,has_avatar:false}}`.

- [ ] **Step 1: Write RED HTTP tests** for authenticated GET/PUT/DELETE, CSRF on mutations, anonymous access, cross-user privilege denial, no-avatar 404, corrupt/oversize/unsupported image 422 and internal storage failure 500 without leaking paths.
- [ ] **Step 2: Run targeted API tests.** Expected RED while routes are absent.
- [ ] **Step 3: Implement thin controller and routes** delegating to UserAvatarService; map `USER_AVATAR_INVALID_SIZE`, `USER_AVATAR_INVALID_TYPE`, `USER_AVATAR_INVALID_CONTENT`, `USER_AVATAR_NOT_FOUND`, `USER_AVATAR_STORAGE_FAILED` using current transport shape, locale support and visibility conventions.
- [ ] **Step 4: Re-run tests, verify browser preflight and binary headers.** Ensure permission checks precede filename/file-content validation.
- [ ] **Step 5: Review/commit** `feat(api): expose protected user avatar resource`.

### Task 7: Canonical OpenAPI and generated client

**Files:** Modify `contracts/openapi/openapi.yaml`, generated `frontend/src/api/generated/*` only through generator; adjust `backend/tests/unit/P1Api090ContractRegressionTest.php` and frontend contract checks.

**Interfaces:** `UserIdentityCreate` adds optional `name` and `group_ids: string[]`; `UserIdentityPatch` adds optional nonblank `name`; `UserIdentity`, `SecurityUser`, `AuthUser` add `name: string|null`, `has_avatar: boolean`; `AuthProfilePatch` adds optional `name`; 3 new binary avatar operations and error schemas.

- [ ] **Step 1: Update/add failing contract tests** for legacy required fields, new group-ID canonicality, `image` multipart field, avatar binary response, omission of `avatar_filename` from public JSON and unchanged operation IDs for existing routes.
- [ ] **Step 2: Run contract tests/lint.** RED until schema matches backend implementation.
- [ ] **Step 3: Update canonical OpenAPI then run** `cd frontend && pnpm run openapi:generate`; review generated typing/SDK without manually editing generated files.
- [ ] **Step 4: Run** `pnpm run openapi:lint`, `pnpm run openapi:generate:check`, and PHP contract tests; all must pass.
- [ ] **Step 5: Review/commit** `feat(contract): document user display, groups and avatar APIs`.

### Task 8: Security Users create/edit form with names and multi-group picker

**Files:** Modify `frontend/src/features/security/pages/SecurityUsersView.vue`, `frontend/src/services/security.ts`, `frontend/src/i18n/vi.ts`, `frontend/src/i18n/en.ts`; optional focused composable `frontend/src/features/security/user-editor/useUserCreateGroups.ts`; tests `frontend/src/features/security/user-editor/__tests__/userCreateGroups.spec.ts`, `frontend/e2e/fe030-security.spec.ts`.

**Interfaces:** Create form state `{username,email,password,state,name,group_ids:string[]}`; Edit sends only `{username,email,name}` when changed, never `group_ids`; use existing `SearchableSelect` with `multiple=true`, `loadLookupPages(listAuthorizationGroupPage,{sort:'name'})`, filter ACTIVE and permission-aware eligibility.

- [ ] **Step 1: Write RED Vitest/Playwright tests.** Required trimmed Unicode name; search by group name/code, pick two and remove both, no Group enabled/required, missing group-view/assign permission, lookup error with retry and preserved draft, 401/403/409/422 save errors.
- [ ] **Step 2: Run targeted tests.** RED for missing inputs and picker, verify existing security E2E still works.
- [ ] **Step 3: Implement form/picker and service mapping** without changing table-first list or membership edit tab; preflight picker permissions as UI hint only; `saveSecurityUser` uses generated writable type and SDK; prevent double-submit.
- [ ] **Step 4: Run Vitest, `pnpm exec vue-tsc --noEmit` and relevant `fe030` E2E.** Expected GREEN and accessible dialog/keyboard navigation.
- [ ] **Step 5: Review/commit** `feat(ui): create named users with optional multiple groups`.

### Task 9: Security User avatar upload/preview with safe retry

**Files:** Create `frontend/src/services/userAvatar.ts`, `frontend/src/components/users/UserAvatar.vue` and optional `UserAvatarEditor.vue`; modify `frontend/src/features/security/pages/SecurityUsersView.vue`, i18n files; test `frontend/src/components/users/__tests__/UserAvatar.spec.ts`, `frontend/e2e/fe030-security.spec.ts`.

**Interfaces:** `readUserAvatar(userId: string): Promise<Blob>`; `uploadUserAvatar(userId: string,file: File): Promise<void>`; `deleteUserAvatar(userId: string): Promise<void>` via authenticated/CSRF-aware client; object URLs are revoked when replaced/unmounted. Client never supplies `avatar_filename`.

- [ ] **Step 1: Write RED tests** for fallback/no avatar, successful private blob, invalid format/size, admin permissions, create with file executes exactly one POST then one PUT, failed PUT retains created `user_id` and retry PUT without new POST, avatar replacement/deletion and stale file response.
- [ ] **Step 2: Execute targeted Vitest/Playwright.** Expected RED for missing avatar UX.
- [ ] **Step 3: Implement preview and post-create upload state machine.** Display a stable created-account ID and partial-success messaging; refresh list/detail only after relevant API success; fetch blob only where `has_avatar` and visibility allow, avoid uncontrolled request fanout across full User list.
- [ ] **Step 4: Run targeted tests and axe checks.** Expected no duplicate account creation, no leaked object URLs, proper avatar alt semantics.
- [ ] **Step 5: Review/commit** `feat(ui): add user avatar editor with upload retry`.

### Task 10: Self-profile, session store and shell avatar

**Files:** Modify `frontend/src/features/account/pages/ProfileView.vue`, `frontend/src/services/auth.ts`, `frontend/src/stores/auth.ts` and app shell avatar display code if needed; reuse `UserAvatar.vue` and `userAvatar.ts`; test `frontend/src/stores/__tests__/authSession.spec.ts`, `frontend/e2e/auth-csrf.spec.ts`, new `frontend/e2e/user-profile-avatar.spec.ts`.

**Interfaces:** Extend `AuthUser` with `name: string|null`, `has_avatar: boolean`; optional `name` in `updateProfile()`/`saveProfile()` payload; revalidate current session after successful avatar mutation without elevating permissions or accepting a client-provided owner ID.

- [ ] **Step 1: Write RED tests** for profile full-name save, owner-only avatar upload/delete, fallback for legacy NULL names, refresh after login and reload, preserved group/base_permissions semantics, and stale avatar/auth responses after logout.
- [ ] **Step 2: Run profile/auth Vitest + E2E.** Expected RED for absent UI and payload fields.
- [ ] **Step 3: Implement self-service and shell integration**, keeping username/email/password flows unchanged, using user ID from current authenticated session and controlled blob URL lifecycle; never expose group or role editing in self-profile.
- [ ] **Step 4: Run target tests and WebKit auth-CSRF repeat.** Expected GREEN, including no session leakage across account switch.
- [ ] **Step 5: Review/commit** `feat(account): expose full name and private avatar on profile`.

### Task 11: Release verification and handoff

**Files:** Create scope-specific evidence under `docs/implementation/evidence/` and update the approved change-control/as-built tracking documentation without altering previously accepted history; include exact testing results. No unrelated refactor.

**Interfaces:** Handoff includes schema status, revision IDs, API contract diff, tests, tracked files and rollback strategy.

- [ ] **Step 1: Verify actual Git branch/worktree**, approved spec/plan, ledger/change-control preflight, distinct task commits and a restored `252/252` pre-feature E2E baseline before treating failures as regressions.
- [ ] **Step 2: Run PHP lint, all scoped PHPUnit and full PHPUnit**, migration on backed-up staging / `erp_test` only; `cd frontend && pnpm run openapi:lint && pnpm run openapi:generate:check && pnpm exec vue-tsc --noEmit && pnpm exec vitest run && pnpm exec playwright test`.
- [ ] **Step 3: Execute staging smoke tests** for account creation 0/2 groups, group-deny rollback, user name-only change, avatar upload/replace/delete, auth login/logout/CSRF, disk failure and decoder support. Record actual counts and failures, not assumptions.
- [ ] **Step 4: Review security and release rollback.** Back up DB/assets, verify `users` columns and private storage permissions, avoid dropping populated columns on downgrade, and retain files needed by old/new binaries.
- [ ] **Step 5: Commit evidence and provide a final report** with PASS/FAIL/PARTIAL per gate, actual commit hashes, API migrations, test counts and production deployment instructions; do not claim deployment without proof.

## Implementation Gates and Delivery

**Gate A (Tasks 1-3):** Shield DB/identity mutations and PBAC rollback proven on actual `erp_test`, including zero-group legacy behavior. Stop if Shield provider or connection semantics fail.

**Gate B (Tasks 4-6):** Private avatar storage, permissions and concurrency tests pass. Stop if image decoder/codec or filesystem permission requirements cannot be met safely.

**Gate C (Task 7):** OpenAPI schema, generated client and contract tests agree with backend before dependent frontend changes.

**Gate D (Tasks 8-10):** Admin/security and self-profile UI pass Vitest, `vue-tsc` and focused cross-browser Playwright including avatar failure/retry.

**Gate E (Task 11):** Full regression, staging rollout checklist and data-preserving rollback reviewed before any production deployment.

**No implementation began while writing this plan.** This plan needs a separate owner review and execution-method choice before Task 1.
