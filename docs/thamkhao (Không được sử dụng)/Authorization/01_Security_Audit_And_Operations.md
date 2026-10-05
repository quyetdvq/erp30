# Security, Audit And Operations

**Status:** Official implementation design

## 1. Identity boundary

CodeIgniter Shield remains the authority for authentication, identity,
session/token and base permission integration. The ERP domain must not create a
second user, password, session or local permission store.

## 2. Authorization boundary

The ERP extension adds Organization Context, Employee Assignment, Resource,
Workflow State and Policy evaluation. Role is a permission grouping mechanism,
not a Department, Position or Organization Scope.

## 3. Audit record

`authorization_logs` records user, Organization Context, resource, action,
state, decision, matched policy, reason and request identifier. It is for
authorization evidence and debugging. View/List requests are outside the
required log scope.

## 4. Operational controls

- Run migrations forward-only; do not rewrite applied migrations.
- Use the configured database prefix and logical table names through CodeIgniter Forge/Query Builder.
- Keep test database configuration separate from the development database.
- Invalidate authorization cache after changes to roles, actions, policies or assignments.
- Protect request identifiers and audit data from unauthorized modification.

## 5. Security gaps requiring approval

User Override persistence, exact System Admin bypass mechanism, API error
contract, retention policy and audit access permissions are not fully defined
by the source documents. They must be approved before production implementation.
