---
document_id: "CHANGE-PERMISSION-AWARE-UI-001"
title: "Permission-aware UI Base Permission Snapshot - Change Control"
version: "1.0"
status: "approved-for-implementation"
date: "2026-10-08"
scope: "Authenticated Base Permission snapshot and retrofit contract for completed frontend slices"
---

# CHANGE-PERMISSION-AWARE-UI-001 - Permission-aware UI Base Permission snapshot

## 1. Decision

The authenticated frontend may receive a runtime **Base Permission snapshot**
from the existing authenticated session/bootstrap contract. This is a
presentation capability hint only. It is not final Authorization and it does
not create a new Profile, permission store, PBAC endpoint, or resource-instance
decision surface.

The current UI Design v1.8 statement that the UI does not create a
permission-summary endpoint is **superseded for this bounded capability**. It
remains true that no standalone permission-summary route is created: the
snapshot is an additive field on the existing authenticated user DTO.

## 2. Current authority and implementation inventory

The current authority revisions are:

```text
00_User_Requirements_v1.0.14.md
01_P1_Functional_Requirements_Specification_v1.11.md
02_P1_Database_Design_v1.14.md
03_P1_Implementation_Contract_v1.9.md
04_P1_Implementation_Plan_v1.9.md
05_P1_API_Design_v1.7.md
06_P1_UI_Design_v1.8.md
07_CHANGE_DISPLAY_SETTING_001_v1.2.md
```

The repository inventory found a suitable existing authenticated seam:

- `backend/app/Config/Routes.php` exposes `POST /api/v1/auth/login`,
  `GET /api/v1/auth/me`, `PATCH /api/v1/auth/profile`, and
  `POST /api/v1/auth/logout`.
- `backend/app/Controllers/Api/Auth.php` returns the same `data.user` shape
  from successful login, `me`, and profile responses.
- `frontend/src/services/auth.ts` consumes that user payload and
  `frontend/src/stores/auth.ts` hydrates it through `/auth/me` during session
  bootstrap; login stores the login response directly.
- `backend/app/Authorization/EffectiveProfileResolver.php` resolves active
  Authorization Group Base Permissions and catalog-valid direct Shield user
  permission additions, unions them deterministically, and returns canonical
  `resource.action` keys through `EffectiveProfile`.
- The resolver is runtime-only. It does not persist a Profile and intentionally
  does not evaluate PBAC, Organization-path policy, Workflow State,
  resource-instance decisions, or `ABSOLUTE_DENY`.

The implementation now exposes those keys additively through the existing
authenticated user responses; no standalone permission-summary endpoint is
introduced.

## 3. Approved route and DTO

No new route is approved. Extend the existing successful authenticated user
responses on these routes:

```text
POST  /api/v1/auth/login
GET   /api/v1/auth/me
PATCH /api/v1/auth/profile
```

The existing envelope, status codes, locale handling, request correlation
metadata, and existing user fields remain unchanged. Add this field to
`data.user`:

```json
{
  "base_permissions": [
    "authorization_group.view",
    "display_setting.view",
    "display_setting.edit",
    "product.view"
  ]
}
```

DTO rules:

- `base_permissions` is always present for an authenticated user and is a
  sorted, unique array of lowercase canonical `resource.action` keys.
- Keys must resolve to an existing Resource Action catalog entry. Invalid or
  unknown direct Shield permission tokens are omitted by the existing resolver
  boundary.
- The field contains keys only. It does not contain Resource Action IDs,
  Group membership detail, policy rows, policy effects, Organization paths,
  Workflow State, assignment evidence, row/resource-instance decisions,
  `ABSOLUTE_DENY`, or a final allow/deny decision.
- An authenticated user with no resolved Base Permission receives an empty
  array. An unauthenticated request keeps the existing `401` behavior.
- There is no `include` query parameter, no separate permission-summary route,
  and no new persistence.

The backend remains the final authority. A present key permits the frontend to
render an action, but the request can still receive the existing `403` or
`404` result after full server-side Authorization and visibility evaluation.

## 4. Snapshot semantics

The snapshot source is exactly:

```text
active Authorization Group permissions
UNION
valid direct user permission additions from the current identity mechanism
```

The following are explicitly outside the snapshot:

```text
PBAC / Organization-path policy
Workflow State conditions
row/resource-instance decisions
ABSOLUTE_DENY results
System Admin bypass
```

System Admin receives the same normal group/direct-permission resolution path
as every other user. No `isSystemAdmin => ALLOW` behavior is introduced.

## 5. Refresh and staleness rules

- Resolution is request-time and user-specific. No cross-request permission
  cache or persisted snapshot is introduced by this change.
- Login and `/auth/me` hydration replace the complete stored snapshot. A
  successful profile response replaces it as well because those responses use
  the same user DTO.
- After a successful current-session permission-affecting administration
  mutation (Authorization Group membership/permission or direct user
  permission), the frontend refreshes `/auth/me` once to replace its snapshot.
- Logout clears the snapshot with the rest of the authenticated session.
- A protected operation that returns `403` or `404` is handled as a backend
  authorization/visibility result, never as proof that the local snapshot was
  authoritative. The frontend may mark the snapshot stale and perform one
  non-mutating `/auth/me` refresh for subsequent rendering, but it must not
  automatically retry an unsafe mutation.
- While the snapshot is loading, unavailable, or stale, the shared helper is
  conservative: it returns `false` for action visibility and does not invent
  an allow. A failed refresh preserves the backend error boundary and does not
  grant access.

## 6. Shared frontend helper contract

All permission-aware UI must consume one shared authenticated-session helper;
feature views must not parse user groups or reimplement the resolver.

```text
hasBasePermission(resource: string, action: string): boolean
hasBasePermissionKey(key: string): boolean
permissionSnapshotState: loading | ready | stale | unavailable
refreshBasePermissions(): Promise<void>
```

The helper normalizes the two components to lowercase, accepts exactly one
`resource.action` separator, and returns `false` for malformed, missing, or
unknown keys. It is a UX filter only. It must not be named or documented as
`authorize`, must not evaluate Organization context or policy, and must not
change API error handling.

## 7. Retrofit scope

The following completed tasks are affected by this contract. They remain
`DONE`; this change control does not reopen or silently reimplement them.

| Completed task | Retrofit boundary |
|---|---|
| `P1-FE-001` | Session bootstrap/login state owns the snapshot lifecycle and shared helper source. The five-mode shell may hide/disable protected navigation actions when the key is absent. |
| `P1-FE-002` | Typed auth adapter carries the additive `base_permissions` DTO field and exposes the refresh operation through the existing API client/error boundary. |
| `P1-DSP-006` | Administrative Display Settings navigation and SET/RESET controls use `display_setting.view` / `display_setting.edit`. Runtime Display Settings and My Display Preferences remain authenticated presentation behavior and are not gated by administrative `view`. |
| `P1-FE-010` | Location/Organization/Contact list, create, edit, state, and export controls use the corresponding `province`, `ward`, `organization`, and `contact` action keys. |
| `P1-FE-020` | Department, Position, Employee, and Employee Assignment list, create, edit, state, and export controls use the corresponding resource/action keys. |
| `P1-FE-030` | Security Users, Authorization Groups, Resources, Policies, Authorization Logs, and their member/permission/detail mutations use their existing `user`, `authorization_group`, `resource`, `policy`, and `authorization_log` action keys. The helper only filters UX controls; the backend remains authoritative. |

`P1-FE-040` is not started and is outside this change. No workflow/lifecycle
UI implementation is authorized by this document.

## 8. API/UI conflict resolution and delivery boundary

The following sentence in UI Design v1.8 §16:

```text
This document does not create a new permission-summary endpoint.
```

is retained as a prohibition on a standalone route but superseded by this
change control for the additive authenticated DTO field. The immediately
following fallback rule is replaced with:

```text
Use the Base Permission snapshot from the authenticated session/bootstrap
contract when present; if a key is missing, hide/disable the UX action; if a
key is present, render may proceed; all protected requests still rely on
backend 403/404 enforcement.
```

The API Design route remains `/api/v1/auth/*`; no new Resource Type, Action,
schema object, permission/Profile persistence, or public authorization
semantics are added. OpenAPI and generated frontend types must be synchronized
when the future runtime implementation is authorized.

## 9. Readiness and exclusions

This change-control remains bounded to the authenticated Base Permission
snapshot and completed-slice retrofit. Its implementation does not change the
P1 task registry or task status, and it does not start `P1-FE-040`.

The implementation is unblocked with the exact DTO and helper contract above.
It does not add persistence, a standalone permission-summary route, PBAC or
resource-instance evaluation, or any System Admin bypass.
