# DinkERP Backend Rewrite — M3.3B2 Policy Mutation

**Source of truth:** `erp_v1.2.4.zip`, particularly `Api030AuthorizationWorkflowService`, `PolicyAdministrationService`, `PolicyValidator`, `PolicyParticipantValidator`, `SqlPolicyRepository`, baseline OpenAPI and Frontend `policyPayload`.

**Scope:** Add exactly four policy mutation operations while preserving 53 earlier operations (total **57**), Frontend, session/CSRF, 74 `en`/`vi` language catalogs, upload storage and existing Database schema. The four operations are registered but are **disabled by default** with the existing `DINKERP_M33B_WRITE_MODE=off` gate.

| Method | Endpoint | Success | Operation |
|---|---|---|---|
| POST | `/api/v1/policies` | 201 | createPolicy |
| PATCH | `/api/v1/policies/{id}` | 200 | updatePolicy |
| POST | `/api/v1/policies/{id}/actions/set-state` | 200 | setPolicyState |
| DELETE | `/api/v1/policies/{id}` | 204 | deletePolicy |

There is **no standalone Policy Action mutation route** in baseline/OpenAPI: policy `actions` are supplied when creating or editing a Policy and are atomically replaced only when the PATCH includes that property. Omission preserves existing actions; `[]` is rejected. Policies must have at least one Action when created.

## Baseline rules retained

- Policy's persisted `status` is an independent **Native State** (`ACTIVE/INACTIVE`). `policy.state_id` describes the **target Resource** State and may be `NULL` to mean **All States**. PATCH with `status` is rejected; the explicit set-state action is required.
- Five-dimensional uniqueness: `(organization_id, resource_id, COALESCE(state_id, 0), participant_type, participant_ref_id)`. Check inside transaction and rely on `uq_policies_scope` as the final concurrent-insert guard; report `POLICY_SCOPE_ALREADY_EXISTS` / HTTP 409 when detected.
- Participant types: `USER`, `GROUP`, `POSITION`, `DEPARTMENT`, `ORGANIZATION`. Lookups use the appropriate baseline tables, including Shield's configured users table.
- Every `resource_action_id` supplied must belong to the selected `resource_id`. Effects are `ALLOW`, `DENY`, or `ABSOLUTE_DENY`. The canonical PBAC evaluator retains its lane-specific precedence and absolute veto.
- State validation uses M3.1's official ten Resource exceptions. Other Resources require a valid Lifecycle, including when a Policy selects All States.
- Only user-authorized `policy.create`, `policy.edit`, `policy.setstate`, and `policy.delete` can write. Persisted edits/deletes take `FOR UPDATE` locks. The Policy row, Policy Actions and server-generated audit fields are written in **one transaction**. HTTP input cannot override the audit fields.
- Set-State returns only `id` and `status` (no post-transition VIEW check), preserving baseline protection from leaking a newly invisible row. Policy create/update return a read-authorized Policy object; delete returns 204 with no body.
- Existing `Accept-Language`, `Content-Language`, localized validation details, CSRF and `meta.request_id` behavior is left unchanged.

### Baseline parity qualifications

The unit tests pin the four relevant baseline files via SHA-256 and exercise validation, mutation transactions in an in-memory DB fixture, route paths, HTTP success-status declarations and rollback. They are **not** equivalent to a direct differential HTTP test against a separately running baseline with the same MariaDB data. Existing Policy PATCH without `actions` intentionally keeps the baseline behavior of not revalidating the previously stored action-resource relation. Database foreign keys, not a new pre-validation query, enforce `organization_id` existence as in baseline.

## Install — overlay

Extract `DinkERP_Backend_v2_M3.3B2_Policy_Overlay.zip` over `D:\XAMPP\htdocs\erp\`. Keep the local `backend/.env`, `backend/vendor`, `backend/public/upload`, `backend_basline` and `frontend` unchanged. No migration required.

### Offline tests

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m33b2_rules_pure.php
php backend\tests\m33b2_transaction_fixture.php
php backend\tests\m33b_contract_pure.php
php backend\tests\m33b_rules_pure.php
php backend\tests\m33b_transaction_fixture.php
php backend\tests\m33b_runtime_pure.php
php backend\tests\m33_routes_pure.php
```

### Actual Database checks (read-only)

```powershell
cd D:\XAMPP\htdocs\erp\backend
php spark m3:audit
php spark m33b:verify
php spark m33b2:verify
php spark m33b2:verify 20 50
php spark routes
```

Expected: four Policy mutation routes, **57** non-OPTIONS business operations in total, confirmation that MariaDB has the five-column `uq_policies_scope` UNIQUE index, and `m33b2:verify` with `errors=0`. The audit queries do not issue INSERT/UPDATE/DELETE.

### Mutation HTTP acceptance — **clone Database only**

With a genuinely isolated **copy** of the database, a separate testing instance of the Backend, a test account with restricted privileges, and a restorable snapshot, temporarily set:

```dotenv
DINKERP_M33B_WRITE_MODE=enabled
```

Check create (201), edit including actions (200), edit without actions (keeps existing rows), Native State transition (200 minimal object), delete (204), duplicate five-dimensional scope (409 with localized `POLICY_SCOPE_ALREADY_EXISTS`), foreign Action validation, invalid State, unsupported participant, denial to actors without action-specific PBAC, and rollback under a controlled SQL failure. Confirm the same code, status, headers and localized messages with `Accept-Language: vi` and `en`. Verify rollback by comparing `policies` and `policy_actions` before/after a failure.

**Never enable writes against the live `api.monmin.net` database as part of these experiments.** Keep the flag `off` in that environment until runtime mutation parity, security and rollback acceptance have been completed.

## Implementation files

- `backend/app/Modules/Access/Mutation/PolicyMutationRules.php`
- `backend/app/Modules/Access/Mutation/PolicyMutationRepository.php`
- `AccessMutationService.php`, `AccessMutationController.php`, `Config/Routes.php` (incremental changes)
- `backend/app/Commands/M33B2Verify.php`
- `backend/tests/m33b2_rules_pure.php`, `m33b2_transaction_fixture.php` and four pinned baseline reference files
- Compatible adjustments to existing M3.3B1 route, contract and in-memory transaction fixtures

**Next:** M3.3B3 — Security Users / Shield mutation. This release does **not** implement or enable Security Users, Workflow States, Lifecycle mutation, Audit/E2E, or production DB writes.
