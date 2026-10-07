# CHANGE-PERMISSION-AWARE-UI-001 implementation evidence

Date: 2026-10-08

## Contract and authority

- Change authority: `06084c1` (`CHANGE-PERMISSION-AWARE-UI-001: approve Base Permission snapshot contract`).
- Authority cache: `VALID`.
- Current authority revisions are recorded in `docs/architecture/08_CHANGE_PERMISSION_AWARE_UI_001_v1.0.md`.
- The 107-task registry remains unchanged; `P1-FE-040` remains `NOT_STARTED`.

## Runtime contract

`POST /api/v1/auth/login`, `GET /api/v1/auth/me`, and `PATCH /api/v1/auth/profile`
return the existing `data.user` object with sorted, unique,
lowercase `base_permissions: string[]` canonical `resource.action` keys.
The resolver unions active Authorization Group permissions with valid direct
Shield user additions and omits unknown or malformed direct tokens.

The snapshot is presentation-only. It contains no PBAC or Organization-path
policy, Workflow State, row/resource-instance decision, `ABSOLUTE_DENY`, or
System Admin bypass result. No Profile persistence or new route is added.
The backend remains authoritative and may still return 403/404 for a rendered
action.

## Refresh and frontend behavior

Login, session hydrate, profile update, and explicit `refreshBasePermissions()`
replace the complete snapshot. Group member/permission administration refreshes
the current session once after success. Logout clears it. A backend 403/404
marks a present snapshot stale without retrying the request. The shared
`useBasePermission()` helper returns false while loading, stale, or unavailable
and exposes `hasBasePermission()`, `hasBasePermissionKey()`, state, and refresh.

## Retrofit surfaces

The shell and completed FE-001/002/010/020/030 and DSP-006 surfaces now filter
navigation, list row actions, create/edit/state/delete/detail controls, group
member/permission saves, security administration controls, and Display Settings
SET/RESET controls with canonical keys. Runtime display preferences remain
authenticated presentation behavior.

## Validation record

Commands and results are recorded in the completion response and final Git
commit. Unrelated pre-existing worktree files were intentionally not staged.
