---
document_id: "CHANGE-RESOURCE-ACTIONS-BULK-API-001"
title: "Bulk Resource Actions Representation Change Control"
version: "1.1"
status: "approved"
owner: "Product / Architecture / Engineering"
date: "2026-10-08"
authority:
  ur: "00_User_Requirements_v1.0.15.md"
  frs: "01_P1_Functional_Requirements_Specification_v1.12.md"
  dbd: "02_P1_Database_Design_v1.15.md"
  implementation_contract: "03_P1_Implementation_Contract_v1.10.md"
  implementation_plan: "04_P1_Implementation_Plan_v1.10.md"
  api_design: "05_P1_API_Design_v1.8.md"
  ui_design: "06_P1_UI_Design_v1.9.md"
  implementation: "CHANGE-USER-IDENTITY-MUTATION-001"
---

# CHANGE-RESOURCE-ACTIONS-BULK-API-001

## Disposition

This approved additive change-control overlay revises the transport selected
for the Direct Permissions N+1 optimization. The seven baseline authority
documents remain unchanged. The optimization is an additive representation
option on the canonical Resource collection, not a new public route.

## Canonical representation

```text
GET /api/v1/resources?include=actions
```

Without `include=actions`, the existing Resource collection response and query
behavior remain unchanged and Resource Actions are not loaded. With the exact
optional value `include=actions`, each returned Resource includes:

```text
actions[].id           BIGINT serialized as string
actions[].code         existing Resource Action code
actions[].description  nullable existing Resource Action description
```

The nested representation intentionally omits `resource_id`; the parent
Resource supplies that association. The page's visible Resource IDs are read
from `resource_actions` in one bulk query, grouped in application memory, and
ordered deterministically by `resource_id ASC, code ASC, id ASC`. The existing
`GET /api/v1/resources/{id}/actions` route remains available for compatibility
and single-Resource administration.

```text
Resource                 = resource
Authorization Action     = resource.view
Evaluation Organization  = GROUP
```

The Resource collection evaluates visibility through the canonical
authorization service before the bulk action query. Authorization remains:

```text
Resource                 = resource
Authorization Action     = resource.view
Evaluation Organization  = GROUP
```

No `resource_action` Resource Type, `resource_action.view` Action, local
persistence, or alternate User-membership endpoint is introduced. The
representation does not alter Shield User, Group Membership, direct ALLOW
permission, PBAC, or System Admin semantics. The superseded
`GET /api/v1/resource-actions` route is not part of the approved contract.

## Closure boundary

The OpenAPI Resource collection option and generated client are covered by the
implementation closure for `CHANGE-USER-IDENTITY-MUTATION-001`. This overlay
is documentation/authority only. It does not create a P1 task, reopen a
completed task, or start `P1-FE-050`.
