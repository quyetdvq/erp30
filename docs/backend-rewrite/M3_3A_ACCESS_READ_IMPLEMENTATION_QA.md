# DinkERP v2 M3.3A — Access Management Read APIs

**Status:** implemented; static tests passed; MariaDB and Frontend HTTP verification pending on the customer's XAMPP. **This is M3.3A (READ ONLY), not completed M3.3 write administration.**

## Boundary and baseline fidelity

- Source: ERP v1.2.4 `Api030AuthorizationWorkflowService`, baseline group/resource/policy repositories, `ShieldUserIdentityService::permissions`, and OpenAPI.
- Backend code is in `backend/`; `backend_basline/` is unchanged. Frontend source is unchanged. No migration, no file storage change, no write route.
- Existing 22 operations from M1–M3.2.2 remain registered; 19 GET operations are added. **41 non-OPTIONS operations total.**
- New endpoints use `UnifiedAccessService` for VIEW on **each persisted object**, before search/count/page. Restricted records return a 404 detail response. A missing/unauthorized Shield user permissions target returns `USER_NOT_FOUND` (404) as in baseline.
- Do not trust `status`, `organization_id`, `state_id` passed in the URL or query as authorization facts. Stored State and the exact 10 independent Resource rules are used. `authorization_log` with a persisted NULL `organization_id` uses the GROUP root, as in baseline.
- All new GET handlers reuse the current response envelope, `Accept-Language`/`Content-Language` and `X-Request-ID`; the 74 baseline bilingual catalog files remain untouched.
- `DINKERP_M32_READ_MODE` (legacy/shadow/enforce) for previously released read APIs is **not changed**. These *new* protected Access GET endpoints call `UnifiedAccessService` directly and need their own runtime acceptance tests before production use.

## Added GET operations

| Resource | Endpoint |
|---|---|
| Security Users | `/api/v1/security/users` |
| Direct Shield user permissions | `/api/v1/users/{id}/permissions` |
| Authorization Groups | `/api/v1/authorization-groups`, `/api/v1/authorization-groups/{id}` |
| Group relations | `/api/v1/authorization-groups/{id}/members`, `/api/v1/authorization-groups/{id}/permissions` |
| Resources | `/api/v1/resources`, `/api/v1/resources/{id}`, `/api/v1/resources/{id}/actions` |
| Policies | `/api/v1/policies`, `/api/v1/policies/{id}` |
| Authorization Logs | `/api/v1/authorization-logs`, `/api/v1/authorization-logs/{id}` |
| Workflow States | `/api/v1/workflow-states`, `/api/v1/workflow-states/{id}` |
| Lifecycles | `/api/v1/lifecycles`, `/api/v1/lifecycles/{id}` |
| Lifecycle relations | `/api/v1/lifecycles/{id}/states`, `/api/v1/lifecycles/{id}/transitions` |

Read-contract details:

- Group members and group permissions are shown only after VIEW of the parent group. Shield group members exclude soft-deleted users.
- Resource lists only attach `actions` when `include=actions`; resource details include `policy_state_codes` plus the full action objects. Policy lists and details contain **all** policy-action rows, not only actions used for permission evaluation. Relations are loaded only for the authorized page/parent.
- Authorization log filters `created_from`/`created_to` follow the baseline date-string validation and filter already-authorized rows.
- Security Users never expose `avatar_filename`, password hashes or full Shield identities. Direct user permissions are intersected with Resource/Action catalog and contain only direct tokens (not inherited groups).
- Paging metadata `page`, `page_size`, `total` reflects the authorized subset; sorting has deterministic `id` tie-breaking. Max `page_size=200`.

## Installation — overlay

1. Back up any locally modified files in `D:\XAMPP\htdocs\erp\backend\`.
2. Extract the **Overlay ZIP** into `D:\XAMPP\htdocs\erp\` (the zip begins with `backend/` and `docs/`).
3. Do not replace `.env`, `vendor/`, upload data, Frontend or the `backend_basline` directory.
4. Run in PowerShell:

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m33_contract_pure.php
php backend\tests\m33_routes_pure.php
php backend\tests\m33_repository_pure.php
php backend\tests\m33_scope_pure.php
php backend\tests\m322_baseline_fidelity_pure.php

cd backend
php spark m3:audit
php spark m33:verify
php spark routes
```

For a larger bounded sample of *real* users and catalog rows:

```powershell
php spark m33:verify 20 75
```

`m33:verify` is **read-only**; it samples persisted SQL rows and PBAC decisions, without adding routes, migrations or writes. It is not a full HTTP parity test against a separately running baseline. Some test sections may SKIP if the corresponding database table is empty.

## Frontend acceptance checklist

- Log in and load **Security Users**, Authorization Groups, Resources, Policies, Authorization Logs, Workflow States, and Lifecycles. In Network DevTools, confirm endpoints return real `data` and `meta` (and that `/lifecycles?page=1&page_size=200&sort=-code` no longer returns route 404 to an eligible authenticated actor).
- Compare success payload and errors in both `Accept-Language: vi` and `en`, including `Content-Language` and `meta.request_id`.
- Test administrator, assignment-restricted actor, inactive employee, missing Base Permission, State-specific DENY and ABSOLUTE_DENY, and an ID outside the organization scope.
- For list/count, confirm hidden IDs are not counted, searchable, or leaked via nested child relations. Validate `include=actions` and user direct permission tokens against baseline.
- Confirm all create/edit/delete/set-state buttons remain intentionally unavailable or their API calls return 404; **do not use this partial backend to perform security management writes**.
- Verify the existing M1 login and M2 read screens still work; Frontend files were not changed.

## Not completed in M3.3A

All mutation endpoints (groups, members, permissions, resources/actions, policies, users, workflow states, lifecycles) are intentionally **not implemented** in this overlay. Their implementation belongs to M3.3B, after baseline validation, atomic transaction and write-PBAC design/testing. This prevents an accidental over-permissive or partially implemented security administration API.

No live MariaDB/HTTP calls were possible in the packaging environment because the supplied archives do not include the running customer's `.env`, Composer vendor libraries and MariaDB service. The customer should report the output of `m33:verify` and the Frontend read-page results before this milestone is formally accepted.
