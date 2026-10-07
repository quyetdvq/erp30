---
document_id: "CHANGE-USER-IDENTITY-MUTATION-001"
title: "Shield User Identity Mutation Change Control"
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

# CHANGE-USER-IDENTITY-MUTATION-001

## Canonical persistence and identity contract

The repository inspection confirms CodeIgniter Shield is authoritative:
`users`, `auth_identities` (`type=email_password`, email in `name`, password
hash in `secret`), `auth_groups_users`, and `auth_permissions_users`. No local
ERP User/password store is permitted. The adapter's operational projection is
`active=true` and a status not in `INACTIVE`, `LOCKED`, `SUSPENDED`, `BANNED`,
or `DISABLED`.

Mutable User fields are username, email identity, and approved display identity
fields. IDs, identity IDs, timestamps, deletion markers, active/status derived
fields, and password hashes are server-managed/read-only. Username and email
are normalized and Shield-unique.

## Admin routes and actions

```text
POST  /api/v1/users
PATCH /api/v1/users/{id}
POST  /api/v1/users/{id}/actions/set-state
POST  /api/v1/users/{id}/actions/reset-password
GET   /api/v1/users/{id}/permissions
PUT   /api/v1/users/{id}/permissions
```

Create accepts username/email/password/state. PATCH excludes password. State
vocabulary is `ACTIVE`, `INACTIVE`, `LOCKED`, `SUSPENDED`, `BANNED`, `DISABLED`.
Reset accepts `new_password` (never a pre-hash), invokes Shield validators, and
requires dedicated `user.reset_password`; it is not implied by `user.edit`.
Self-service change is out of scope.

Stable failures include `USER_NOT_FOUND`, `USER_IDENTITY_ALREADY_EXISTS`,
`USER_STATE_INVALID`, `USER_PASSWORD_POLICY_INVALID`,
`USER_PASSWORD_ACTION_REQUIRED`, and `CONCURRENT_MODIFICATION`.

## Group membership and direct permissions

User UI membership is the canonical Shield membership projection. Mutations use
`authorization_group.assign_member`; group names never infer authorization.
Existing direct permission endpoints remain ALLOW-only, use
`user.assign_permission`, accept only catalog-backed `resource.action` values,
write Shield `auth_permissions_users`, and cannot bypass PBAC or
`ABSOLUTE_DENY`. UI action visibility uses the Base Permission snapshot while
the backend remains final authority.

## Scope and readiness

Affected completed tasks: P1-IAM-001, P1-IAM-002, P1-AUTH-005, P1-API-030,
P1-FE-030; P1-FE-040 remains DONE and is not reopened. The current catalog
contains no approved password action, so this change reserves
`user.reset_password` as the dedicated canonical action. The change is outside
the 107-task registry and does not start P1-FE-050. Documentation
synchronization is complete; implementation is unblocked only for a later
scoped Shield adapter/API/UI/regression task.
