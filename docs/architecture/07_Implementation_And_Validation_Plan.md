# Implementation And Validation Plan

**Status:** Official implementation plan

## 1. Sequence

1. Confirm this document set and the synchronized v1.7 baseline.
2. Validate migration filenames and schema against Database Design v1.2.
3. Implement Organization Context and independent Department/Position masters.
4. Implement Employee and Employee Assignment history.
5. Implement lifecycle/state and resource/action catalogs.
6. Implement role-permission and policy-action resolution.
7. Implement authorization decision logging.
8. Add API/UI contracts only after the missing wire-level decisions are approved.

## 2. Required checks

- Fresh test database migration in the documented order.
- Primary key, foreign key, unique key and index inspection.
- Organization cycle and invalid-type validation.
- Employee assignment temporal and primary-assignment validation.
- Polymorphic contact and participant type validation.
- Profile prerequisite and policy effect-priority tests.
- Workflow transition and state-sensitive authorization tests.
- Authorization audit evidence for approve, delete and state-change actions.

## 3. Release gates

Implementation is not ready for release until:

- All required source decisions are traceable to an official document.
- No resource-instance, condition-rule or local identity schema has been introduced.
- Authenticated allow/deny behavior is tested against a real test database.
- Migration status, rollback/forward strategy and test database isolation are recorded.
- The API error and User Override gaps listed in the architecture contracts are resolved or explicitly deferred.

## 4. Evidence convention

Each completed gate must record the source document, code path, command or test
used, database target and result. Static schema inspection must remain distinct
from authenticated runtime proof.
