# DinkERP v2 M3.3B1 — Group & Resource Mutation API

**Implementation status:** source and offline security/transaction fixtures completed. **NOT runtime-accepted on the customer's MariaDB.** This is **M3.3B1**, not complete M3.3B. No existing Frontend, `backend_basline`, database schema or upload path was changed.

## Authority and boundaries

Sources reviewed: `backend_basline/app/Config/Routes.php`, `Controllers/Api/AuthorizationWorkflow.php`, `Services/Api030AuthorizationWorkflowService.php`, `Modules/Authorization/Administration/Groups/Application/AuthorizationGroupService.php`, `Modules/Resource/Application/ResourceAdministrationService.php`, `Modules/Authorization/Administration/Groups/Infrastructure/Persistence/SqlAuthorizationGroupRepository.php`, OpenAPI v1.2.4, and Shield group membership tables. M3.1's **exact 10 Resource State exemptions** remain authoritative.

This tranche exposes **12 matching baseline operations** when the code is installed, in addition to the prior 41 read/auth operations (**53** non-OPTIONS routes). The new paths are always registered but execute **no writes unless `DINKERP_M33B_WRITE_MODE=enabled`**. The feature switch is `off` by default. No new migrations.

| Method | Endpoint | Auth action | Success |
|---|---|---|---|
| POST | `/api/v1/authorization-groups` | `authorization_group.create` | 201 |
| PATCH | `/api/v1/authorization-groups/{id}` | `authorization_group.edit` | 200 |
| POST | `/api/v1/authorization-groups/{id}/actions/set-state` | `authorization_group.setstate` | 200 |
| PUT | `/api/v1/authorization-groups/{id}/members` | `authorization_group.assign_member` | 200 |
| PUT | `/api/v1/authorization-groups/{id}/permissions` | `authorization_group.assign_permission` | 200 |
| POST | `/api/v1/resources` | `resource.create` | 201 |
| PATCH | `/api/v1/resources/{id}` | `resource.edit` | 200 |
| POST | `/api/v1/resources/{id}/actions/set-state` | `resource.setstate` | 200 |
| DELETE | `/api/v1/resources/{id}` | `resource.delete` | 204 |
| POST | `/api/v1/resources/{id}/actions` | `resource.edit` | 201 |
| PATCH | `/api/v1/resources/{id}/actions/{action_id}` | `resource.edit` | 200 |
| DELETE | `/api/v1/resources/{id}/actions/{action_id}` | `resource.delete` | 204 |

**Not included:** Policy CRUD/actions, Shield User create/edit/state/direct permissions/password, Workflow States and Lifecycle configuration/mutations, Audit write parity. Do **not** mark M3.3B as complete or turn on write mode in a production database yet.

## Behavioral invariants

- Mutations use a **separate approved write authorization entry point**, not a repurposed VIEW privilege. Base Permission + ACTIVE employee + effective assignment + Organization scope + State-aware PBAC (`ALLOW`, `DENY`, `ABSOLUTE_DENY`) are evaluated against trusted data and registered Resource Actions.
- The `create` candidate is validated server-side. `resource` and `authorization_group` are Native State; future business Resources **must have an active Lifecycle** (no fallback); resource Lifecycle reassignment checks even **INACTIVE Policies**.
- Group replacement manipulates Shield's canonical `groups_users` table, not the ERP `groups` table. `group_permissions` replacement validates every Resource Action before removing any existing grant.
- `FOR UPDATE` lock is acquired for target row inside one transaction; rollback preserves original group memberships/permissions when any validation or SQL write fails. Audit actor/timestamps are generated on the server, not from HTTP payload.
- Resource Action `code` cannot be changed; Resource `type` and `domain` cannot be changed. Resource `status` must be changed by `set-state`; for Resource set-state only, the baseline's narrow inactive-resource recovery applies. Resource set-state returns **only `id` and `status`**, because the new State might deny subsequent VIEW.
- Baseline auth cookies, CSRF, request ID, response envelope, `Accept-Language`, `Content-Language`, all 74 locale catalog files and upload layout are unchanged.
- Absent authentication returns 401; write feature gate disabled returns 403; business denials flow through the existing bilingual DomainException HTTP mapper.

## Local verification — read-only

Extract the overlay at `D:\XAMPP\htdocs\erp\` (paths start with `backend/` and `docs/`). Do **not** overwrite `backend/.env`, `backend/vendor`, `frontend`, `backend_basline` or `backend/public/upload`.

From PowerShell:

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m33b_rules_pure.php
php backend\tests\m33b_transaction_fixture.php
php backend\tests\m33b_runtime_pure.php
php backend\tests\m33b_contract_pure.php
php backend\tests\m33_routes_pure.php
php backend\tests\m32_runtime_pure.php
php backend\tests\m322_baseline_fidelity_pure.php

cd backend
php spark m33b:verify
php spark m3:audit
php spark routes
```

`m33b:verify` **only reads persisted rows and evaluates write-PBAC decisions**. It cannot certify successful write transactions, lock behavior, Shield mutation parity, HTTP responses or a live baseline equivalence. It never automatically enables writes.

## Controlled write acceptance — database CLONE ONLY

1. Back up production and restore **a separate MariaDB clone** (including Shield data, settings, workflows and policies). Test in a private environment; do not send test mutations to `api.monmin.net` while it points at live data.
2. In the clone-only `backend/.env`, set `DINKERP_M33B_WRITE_MODE=enabled` and confirm that the database connection points to the clone. Keep `DINKERP_M32_READ_MODE=legacy` unless Shadow has been accepted independently.
3. Test each operation in both `Accept-Language: vi` and `en` using real session cookie/CSRF via the unchanged Frontend or a test client. Verify exact response status, error code, details and `request_id` against baseline.
4. Test allowed actor, missing Base Permission, inactive employee, inactive group, out-of-scope assignment, `DENY`, `ABSOLUTE_DENY`, duplicate code, invalid State, invalid Lifecycle and invalid user/action references. Confirm denied actions leave **all tables unchanged**.
5. Force SQL/constraint failures and concurrent writes to the same Group/Resource; verify rollback and no partial grants/membership writes, including Shield tables.
6. Restore `DINKERP_M33B_WRITE_MODE=off` after the trial. Do **not** enable production writes until live baseline HTTP/DB parity tests have passed and the full M3 security milestone is accepted.

## Offline evidence

- `m33b_rules_pure.php`: 57 assertions (includes reflection parity of five Group normalization cases with baseline).
- `m33b_transaction_fixture.php`: 34 assertions against the real M3.3B1 service/repository and an in-memory transaction DB fixture (not MariaDB).
- `m33b_runtime_pure.php`: 17 write-PBAC assertions using the real UnifiedAccessService and M3.2 fixture state, including ABSOLUTE_DENY and Resource self-recovery. Also executes the 22 inherited M3.2 checks.
- `m33b_contract_pure.php`: 24 OpenAPI/guard assertions plus the 20 M3.3 route assertions.
- Existing M0–M3.3A offline tests preserved and rerun. No schema changes.

## Known differences requiring baseline-runtime confirmation

The v2 write boundary rejects unknown non-audit payload fields earlier, retains precise string BIGINT identifiers rather than coercing to signed PHP ints, and reports some duplicate action/type failures as stable DomainException codes. These behavior edges must be tested against the baseline response contract before enabling writes on real data. The code here is a **reviewable implementation candidate**, not an assertion of full 100% production parity.

**Next:** M3.3B2 Policy mutations (including scope uniqueness and policy_actions), M3.3B3 User/Shield mutations, M3.3B4 Workflow State and Lifecycle graph mutations; only then M3.4/M3.5 audit, security/E2E and enablement.

## M3.3B1.1 test reference repair (2026-10-23)

A later **test-only** overlay makes baseline Group normalization and OpenAPI operation tests self-contained with verified v1.2.4 reference snapshots when the local `backend_basline` or frozen contract inventory is incomplete. See `M3_3B1_1_TEST_REFERENCE_HOTFIX_QA.md`. This change does not enable or change any mutation endpoint; the write-mode guard remains `off` by default.
