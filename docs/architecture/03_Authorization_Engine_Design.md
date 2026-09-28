# Authorization Engine Design

**Status:** Official implementation design

## 1. Inputs

The decision engine receives an authenticated user, an action in
`resource.action` form, the current Organization Context and the current
workflow state. Resource Instance and business-value condition inputs are not
accepted by this baseline.

## 2. Resolution flow

1. Authenticate the user through Shield.
2. Resolve the single employee mapping; an unlinked ERP user has no ERP resource access.
3. Load active employee assignments and available Organization Contexts.
4. Resolve role permissions through `roles`, `role_permissions` and `resource_actions`.
5. Apply only approved User Override additions if an approved persistence contract exists.
6. Resolve the resource type and workflow state.
7. Load policies matching Organization Context, resource, state and participant.
8. Apply inherited parent-Organization policies during evaluation.
9. Evaluate effects using `ABSOLUTE_DENY > ALLOW > DENY`.
10. Record an authorization log for the configured auditable actions.
11. Return the final decision.

## 3. Decision rules

- Profile permission is the prerequisite; policy cannot grant a permission that
  is absent from the profile.
- A matched `ABSOLUTE_DENY` always denies.
- A matching `ALLOW` outranks a matching `DENY` under the approved effect order.
- If no restrictive policy matches, an existing profile permission remains allowed.
- System Admin bypass behavior is an identity/authorization service rule and
  must not be implemented as a database foreign-key exception.

## 4. Participant types

Supported participant types are `USER`, `ROLE`, `POSITION`, `DEPARTMENT` and
`ORGANIZATION`. Group, Branch, Plant and Warehouse are Organization Context
types, not additional participant types.

## 5. Cache invalidation

Authorization decisions may be cached. Role, resource action, policy,
employee-assignment or Organization Context changes must invalidate affected
decisions. Cache behavior must never bypass the current absolute-deny rule.
