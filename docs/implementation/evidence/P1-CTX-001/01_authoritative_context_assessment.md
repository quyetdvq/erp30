# P1-CTX-001 Assessment

Task: P1-CTX-001 - Authoritative Evaluation Organization resolver
Risk: HIGH
Impact: backend-domain
Dependencies: P1-ORG-002 DONE; P1-EMP-003 DONE
Authority cache: VALID

## Scope

Implemented the bounded resolver contract only. Existing authorization decision
evaluation, PBAC policy matching, API routes, UI, migrations, and CTX-002 are
out of scope.

## Contract checks

- Existing targets resolve from persisted target ownership.
- `organization.create` resolves from canonical `parent_id`.
- `employee_assignment` mutations resolve from assignment `organization_id`.
- `policy` and `price_list` existing targets resolve from persisted
  `organization_id`.
- Group-level shared resources resolve the single persisted GROUP root.
- Primary Assignment is used only when the request explicitly permits fallback
  and no authoritative target/scope is present.
- Client evaluation hints are normalized and rejected on mismatch.
- Missing, inactive, malformed, or ambiguous context fails closed.
- Repository persistence failures remain persistence failures.

## Verification evidence

Focused tests cover persisted-target precedence, hint mismatch, parent and
assignment context, explicit primary fallback, required-context rejection,
GROUP resolution, sibling isolation, multi-company primary selection, and
persistence failure propagation. The resolver receives trusted
`OperationContext`/`EvaluationTime` and uses repository/service boundaries.

No schema, public API, Resource/Action, frontend, or `AuthorizationService`
behavior changed.
