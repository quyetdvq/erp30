# CHANGE-AUTH-DOMAIN-001 - Canonical document update

Date: 2026-10-07
Status: APPROVED SPECIFICATION / DOCUMENT PACKAGE

Updated canonical downstream documents:

- FRS v1.9
- DBD v1.12
- Implementation Contract v1.7
- Implementation Plan v1.6
- API Design v1.4
- UI Design v1.5

User Requirements v1.0.13 remains unchanged.

## Canonical Resource Domains

```text
SYSTEM       -> GROUP-fixed system/security/configuration Resource
MASTER       -> GROUP-fixed shared business master/reference
ORGANIZATION -> authoritative target/owner/parent/persisted Organization context
```

`COMPANY` is removed as a Resource Domain. `ORGANIZATION` may resolve to GROUP, COMPANY, BRANCH, PLANT, or WAREHOUSE. Missing/unknown domain or unresolved required ORGANIZATION context fails closed. Domain determines evaluation scope only; it does not grant permission or bypass Base Permission/PBAC/ABSOLUTE_DENY/Workflow.

## Canonical 20-Resource matrix

SYSTEM: user, authorization_group, resource, workflow_state, lifecycle

MASTER: province, ward, department, position, employee, product_group, product_uom, product

ORGANIZATION: organization, contact, employee_assignment, policy, authorization_log, price_list, import_job

## Approved schema/runtime guards

- `resources.domain` becomes NOT NULL.
- Allowed values: SYSTEM / MASTER / ORGANIZATION.
- Existing canonical Resource domain is immutable in normal P1 runtime; change requires change control.
- Forward migration normalizes legacy domain values.
- Primary Assignment is not a fallback/override for domain-derived Resource authorization scope.
- `P1-API-020` remains unstarted until runtime/data reconciliation and API-010 Contact scope regression are verified.
- P1 task registry remains 100 tasks; no dependency edge is changed by this document package.
