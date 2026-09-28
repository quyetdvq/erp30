# Application And API Contract

**Status:** Official implementation boundary

## 1. Application services

The implementation must expose service boundaries for:

- Organization Context tree and status management.
- Employee master and Employee Assignment history.
- Department and Position masters.
- Resource and Resource Action catalog.
- Lifecycle and Workflow State catalog.
- Role permissions.
- Policy and Policy Action management.
- Authorization decision and audit recording.

Exact HTTP route names, payload envelopes, pagination and error identifiers are
not defined by the two source documents. They must be specified in an approved
API revision before route implementation; this document intentionally does not
invent them.

## 2. Validation requirements

- Reject unknown Organization Context types.
- Reject organization self-parenting and hierarchy cycles.
- Enforce unique organization, department, position, employee and resource codes as defined by the schema.
- Validate typed polymorphic references before persistence.
- Validate assignment dates and primary-assignment rules in the service layer.
- Reject unsupported resource-instance and condition-rule fields.
- Validate policy participant and effect enumerations.

## 3. Transaction boundary

Mutations that change authorization inputs and their audit event must use a
transaction boundary. Failed validation must not write a partial assignment,
policy action or decision log.

## 4. Error contract gap

The source documents do not define HTTP status mapping or wire-level error
format. This is an explicit implementation blocker for API certification, not a
permission to infer a contract.
