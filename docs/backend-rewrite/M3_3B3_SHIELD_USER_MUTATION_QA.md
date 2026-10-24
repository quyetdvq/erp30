# DinkERP Backend v2 — M3.3B3 Security Users / Shield Mutation

Implementation base: M3.3B2.1 (Policy Index Audit Hotfix). Original contract source: `erp_v1.2.4.zip` (SHA-256 pinned snapshots in `backend/tests/fixtures/baseline_v1_2_4/shield_user`).

## Scope and safety

Five **explicit** operations are now registered behind `DINKERP_M33B_WRITE_MODE=off`:

| HTTP | Endpoint | Baseline success |
| --- | --- | ---: |
| POST | `/api/v1/users` | 201 |
| PATCH | `/api/v1/users/{id}` | 200 |
| POST | `/api/v1/users/{id}/actions/set-state` | 200 |
| POST | `/api/v1/users/{id}/actions/reset-password` | 200 |
| PUT | `/api/v1/users/{id}/permissions` | 200 |

The prior **57 operations are retained**, resulting in 62 registered non-OPTIONS operations. `GET /api/v1/security/users` and `GET /api/v1/users/{id}/permissions` continue to use the M3.3A read service.

**No live mutations have been tested. Do not enable writes against the working MariaDB database.** The flag is shared with B1/B2 mutation endpoints: `enabled` on a test clone enables *all* reviewed mutation endpoints, not only the five User endpoints.

## Behavioral contract

- User is one of the fixed Native State exceptions (no Lifecycle). The six Shield presentation states are `ACTIVE`, `INACTIVE`, `LOCKED`, `SUSPENDED`, `BANNED`, `DISABLED`; PBAC reduces them to canonical `ACTIVE`/`INACTIVE` using authoritative status **and** active flag.
- Creation requires username, email, password and state. Optional `name` and `group_ids` match baseline normalization; Group IDs must be a list of distinct positive canonical decimal strings. **Omission/empty list must create no Shield group memberships**, independent of Shield's default group.
- Creating with explicit Groups checks each persisted Group's VIEW and ASSIGN_MEMBER rights and ACTIVE status, locks the Group rows in stable order, then saves user identity and memberships in one transaction. Invalid group references / denied assignment roll back the entire creation.
- `PATCH /users/{id}` accepts `username`, `email`, `name`, optional `row_version` (retained as a baseline-compatible accepted field; no optimistic row check in baseline). Reject unrelated fields, including state/password/audit.
- `set-state` and `reset-password` are distinct operations. `reset-password` uses **user.reset_password**, never `user.edit`. Passwords are checked through `service('passwords')->check()`, set with the **Shield User entity**, and saved by the existing `App\Modules\Identity\UserModel`; no direct SQL stores password hashes.
- User target lookup is filtered by deleted-at and checked for **VIEW** first; outside-scope targets respond as `USER_NOT_FOUND` (HTTP 404). Mutation action checks are separate, including `ABSOLUTE_DENY` vetoes.
- `PUT /users/{id}/permissions` validates only known `resource.action` tokens in the *persisted* ERP catalog and replaces direct Shield grants atomically; an omitted or malformed list is rejected. Empty list clears direct grants.
- Self-deactivation logs the current actor out only after successful DB transaction. Response never exposes password hash, private avatar filename or raw Shield internals. API error mapper, CSRF, language headers and locale files are unchanged.
- Resource State/Lifecycle 10-exception classification, all prior routes and Frontend code remain untouched.

## Installation (from M3.3B2.1)

Extract `DinkERP_Backend_v2_M3.3B3_ShieldUser_Overlay.zip` over `D:\XAMPP\htdocs\erp\` (the ZIP contains `backend/` and `docs/` paths relative to the ERP root). Do not overwrite local `.env`, DB, `frontend/`, `backend_basline/`, `backend/vendor`, or `backend/public/upload` contents.

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m33b3_rules_pure.php
php backend\tests\m33b3_contract_pure.php
php backend\tests\m33b3_runtime_pure.php
php backend\tests\m33b3_transaction_fixture.php
php backend\tests\m33b3_verify_pure.php

cd backend
php spark m3:audit
php spark m33b:verify
php spark m33b2:verify
php spark m33b3:verify
php spark m33b3:verify 20 50
php spark routes
```

`m33b3:verify` checks the existing Shield tables, User resource/actions, valid persisted State fields, and samples actor-specific User mutation authorization decisions. It performs **no SELECT of passwords or password hashes, no DML, no migration, and no test user creation**. It does **not** establish HTTP, actual Shield hashing, or MariaDB write-transaction parity.

## Integration acceptance gate (separate MariaDB CLONE only)

Only after creating an independent DB clone, pointing test `.env` to the clone, and confirming that no production database is reachable should an operator temporarily set `DINKERP_M33B_WRITE_MODE=enabled` and check:

1. HTTP status/envelopes for all five operations in **both** `Accept-Language: vi` and `en`, including 401, 403, 404, 409, 422.
2. Shield `auth_identities` email/password authentication following create, update and reset-password; user login and session handling after status changes.
3. GROUP assignment with VIEW/ASSIGN_MEMBER and inactive/hidden Groups; no automatic default membership; rollback on one invalid Group.
4. Direct permission replacement with known and unknown tokens; direct grants not confused with Group permissions; rollback on SQL/uniqueness faults.
5. PBAC ACTIVE/inactive decisions and `ABSOLUTE_DENY`; action separation and target invisibility; concurrent edits / duplicates; no unexpected leak of `avatar_filename` or credentials.
6. Turn write mode back to `off` after tests and confirm that all mutation endpoints are inaccessible for writing.

## Test categories and limitations

| Test | Coverage | Limitation |
| --- | --- | --- |
| `m33b3_rules_pure.php` | Baseline reference checksums, normalization and invalid input | No HTTP/SQL |
| `m33b3_contract_pure.php` | Pinned OpenAPI statuses, exact 62-route allowlist, write gates | Static contract/guard checks |
| `m33b3_runtime_pure.php` | Real `UnifiedAccessService` PBAC on in-memory fixture, including `user.reset_password` | Not live MariaDB PBAC |
| `m33b3_transaction_fixture.php` | Real new User service/repository on in-memory Shield & SQL doubles | Password hash is **test-only SHA-256**. Real Shield uses its configured password hasher; not proven here. |
| `m33b3_verify_pure.php` | Command read-only SQL selections, missing schema/actions/state rejection | Fake database |
| `php spark m33b3:verify` | Real MariaDB read-only eligibility/decision sampling | Must run on user's XAMPP; zero writes |

No schema migration and no production data changes are included.
