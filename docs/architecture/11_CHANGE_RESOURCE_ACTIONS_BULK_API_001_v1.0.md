---
document_id: "CHANGE-RESOURCE-ACTIONS-BULK-API-001"
title: "Bulk Resource Actions API Change Control"
version: "1.0"
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

This approved additive change-control overlay reconciles the implemented bulk
transport route with API Design v1.8. The seven baseline authority documents
remain unchanged. The route is required to remove the Direct Permissions N+1
request pattern in the Shield User administration UI.

## Canonical route

```text
GET /api/v1/resource-actions
```

The route is a bulk transport collection over existing `resource_actions`.
It does not create a new Resource Type, Action code, table, column, index,
foreign key, or authorization semantic.

```text
Resource                 = resource
Authorization Action     = resource.view
Evaluation Organization  = GROUP
```

The endpoint evaluates visibility for each existing Resource through the
canonical authorization service, excludes invisible Resources, then reads the
actions for the visible Resource IDs in one repository query. Multi-resource
visibility filtering is therefore applied before transport output.

Items contain exactly:

```text
id            BIGINT serialized as string
resource_id   BIGINT serialized as string
code          existing Resource Action code
description   nullable existing Resource Action description
```

Rows are ordered deterministically by `resource_id ASC, id ASC`. The existing
`GET /api/v1/resources/{id}/actions` route remains available for compatibility.

No `resource_action` Resource Type, `resource_action.view` Action, local
persistence, or alternate User-membership endpoint is introduced. The route
does not alter Shield User, Group Membership, direct ALLOW permission, PBAC,
or System Admin semantics.

## Closure boundary

The OpenAPI route and generated client are covered by the implementation
commit `5c1dcbb`. This overlay is documentation/authority only. It does not
create a P1 task, reopen a completed task, or start `P1-FE-050`.
