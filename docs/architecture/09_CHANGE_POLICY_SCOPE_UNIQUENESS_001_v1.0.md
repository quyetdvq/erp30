---
document_id: "CHANGE-POLICY-SCOPE-UNIQUENESS-001"
title: "Policy Scope Uniqueness Change Control"
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
---

# CHANGE-POLICY-SCOPE-UNIQUENESS-001

## Decision

Exactly one `policies` row is allowed for:

```text
organization_id + resource_id + normalized_state + participant_type + participant_ref_id
```

`normalized_state` is `state_id`, or `ALL_STATES` when `state_id IS NULL`.

## Database design

The forward-only MariaDB 10.4.32 migration adds the stored generated column
`normalized_state_id = COALESCE(state_id, 0)` and a unique key over the five
scope dimensions. `0` is reserved as the positive-ID-safe `ALL_STATES` sentinel.
This avoids MariaDB NULL-distinct unique-key behavior. The migration preflights
duplicates, participant mappings, action/resource consistency, and NULL-state
equivalence before adding the key.

## Reconciliation

For each proven-equivalent duplicate scope, keep the lowest `policies.id`.
Union `policy_actions` by `resource_action_id` and retain the effect by
`ABSOLUTE_DENY > ALLOW > DENY`. Delete only duplicate action/policy rows after
the union is transactionally complete. If equivalence, mapping, or action
ownership cannot be proven, BLOCK before any destructive step and preserve all
rows.

## Runtime/API/UI contract

Policy POST/PATCH owns the complete Action/Effect set atomically. Duplicate
scope, including a database race, returns HTTP `409` with
`POLICY_SCOPE_ALREADY_EXISTS`. FE-030 keeps draft input, identifies the
conflicting scope, and offers reload/reconcile. Regression covers NULL versus
explicit state, duplicate POST/PATCH, concurrent submit, and conflicting
effects.

## Scope and readiness

Affected completed tasks: P1-DB-050, P1-AUTH-001, P1-AUTH-005, P1-API-030,
P1-FE-030. This change does not alter the 107-task registry and does not start
P1-FE-050. Documentation synchronization is complete; implementation is
unblocked only for a later scoped migration/API/UI task with the required
regressions.
