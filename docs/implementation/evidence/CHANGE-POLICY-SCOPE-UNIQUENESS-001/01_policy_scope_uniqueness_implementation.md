# CHANGE-POLICY-SCOPE-UNIQUENESS-001 Implementation Evidence

Authority: UR v1.0.15, FRS v1.12, DBD v1.15, Implementation Contract v1.10,
Implementation Plan v1.10, API Design v1.8, and UI Design v1.9.

## Migration and reconciliation

`2026-10-08-000012_EnforcePolicyScopeUniqueness` performs a proof-first
duplicate audit over the normalized five-dimensional scope. It blocks before
schema/data cleanup when participant mapping, Action-to-Resource ownership,
canonical Effect values, or authorization-log equivalence cannot be proven.
Proven groups retain the lowest `policies.id`; Action rows are unioned by
`resource_action_id` using `ABSOLUTE_DENY > ALLOW > DENY`, then duplicate
Action/Policy rows are deleted in one transaction. The stored generated
`normalized_state_id = COALESCE(state_id, 0)` column and
`uq_policies_scope` key are added forward-only after the audit.

The focused migration test proves lowest-ID survival, Action union/QT1
precedence, NULL versus concrete state scopes, and preservation of duplicate
rows when an unknown Effect blocks proof. No duplicate scopes were present in
the test database before the reconciliation fixture.

## Runtime/API/UI

Policy create and scope-changing update perform a repository/service scope
preflight inside the existing atomic Policy + Action transaction. The database
unique-key race is translated to `POLICY_SCOPE_ALREADY_EXISTS`; the API mapper
returns HTTP 409 with localized messages and `scope` details. OpenAPI documents
nullable `state_id` (`NULL` = `ALL_STATES`) and PATCH 409 behavior; generated
client types were regenerated.

FE-030 preserves the dirty Policy draft and Action rows on the duplicate code,
shows a localized scope-specific notice, and offers a reload of the canonical
Policy list without auto-merging or discarding the draft.

## Affected completed tasks

`P1-DB-050`, `P1-AUTH-001`, `P1-AUTH-005`, `P1-API-030`, and `P1-FE-030`.
The 107-task registry is unchanged. `P1-FE-050` was not started.
