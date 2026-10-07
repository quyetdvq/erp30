# CHANGE-PERMISSION-AWARE-UI-001 - Permission-aware UI change-control evidence

## Outcome

Documentation-only authority synchronization for the authenticated Base
Permission snapshot. No backend, API, OpenAPI, generated-client, frontend,
schema, migration, or task-status change was made.

## Inventory evidence

- Existing authenticated routes: `POST /api/v1/auth/login`,
  `GET /api/v1/auth/me`, `PATCH /api/v1/auth/profile`, and
  `POST /api/v1/auth/logout` in `backend/app/Config/Routes.php`.
- Existing authenticated DTO producer: `backend/app/Controllers/Api/Auth.php`
  returns `data.user` for login, me, and profile.
- Existing frontend bootstrap: `frontend/src/services/auth.ts` calls
  `/api/v1/auth/me`; `frontend/src/stores/auth.ts` stores the authenticated
  user and clears it on logout/401.
- Existing Effective Profile implementation:
  `backend/app/Authorization/EffectiveProfileResolver.php`,
  `EffectiveProfile.php`, and `ShieldIdentityAdapter.php` union active Group
  Base Permissions with catalog-valid direct Shield additions and expose
  canonical keys without Profile persistence or final Authorization logic.
- Existing UI conflict: `docs/architecture/06_P1_UI_Design_v1.8.md` §16 said
  no permission-summary endpoint and fell back to conservative rendering.

## Resolved contract

`CHANGE-PERMISSION-AWARE-UI-001` approves an additive `user.base_permissions`
array on successful login, me, and profile responses. The array is sorted,
unique canonical `resource.action` keys from active Authorization Group
permissions plus valid direct permission additions only. It excludes PBAC,
Organization-path policy, Workflow State, row/resource-instance decisions,
`ABSOLUTE_DENY`, and any System Admin bypass. There is no new route or
persistence.

The shared frontend helper is `hasBasePermission(resource, action)` /
`hasBasePermissionKey(key)` with conservative loading/stale/unavailable
behavior. Completed FE-001/002, DSP-006, FE-010/020/030 are the retrofit
boundary. `P1-FE-040` remains not started.

## Validation

| Command/check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php --batch P1-FE-010 P1-FE-020 P1-FE-030` | PASS; `AUTHORITY_CACHE: VALID` before this change-control was registered |
| Auth route/controller/store/resolver inventory | PASS; existing seam and Effective Profile boundaries confirmed |
| Runtime/API/frontend implementation | NOT RUN; explicitly out of scope |
| PHPUnit, PHPStan, frontend build, Vitest, Playwright, OpenAPI/codegen | NOT RUN; explicitly out of scope |
| `P1-FE-040` start check | PASS; ledger remains `NOT_STARTED` |

## Authority synchronization

The new change-control is registered as an approved authority overlay in
`.codex/skills/dinkerp-implementation/compiled/authority-manifest.json`.
The current seven baseline revisions remain unchanged.
