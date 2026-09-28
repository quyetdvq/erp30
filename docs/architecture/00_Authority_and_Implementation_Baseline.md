# Mini ERP Implementation Baseline

**Status:** Official implementation baseline

**Version:** 1.0

**Effective date:** 2026-09-28

## 1. Authority

This document set is derived from two approved source documents:

1. `docs/Idea_Organization_Employee_Authorization_v1.7.md` — business and authorization baseline.
2. `docs/Mini_ERP_Authorization_Database_Design_v1.2.md` — database schema authority.

The database design is authoritative for table names, columns, data types,
nullability, keys, indexes, foreign keys and migration order. The v1.7 idea
document is authoritative for business intent only where it does not conflict
with the database design.

The documents previously stored in the root `docs` directory are working notes
and are not implementation authority.

## 2. Locked decisions

- One shared database supports multiple Organization Contexts.
- Organization Context types are fixed: `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`.
- Organization hierarchy is represented by `organizations.parent_id`.
- Department and Position are independent masters. Neither has `organization_id`.
- Employee-to-Organization/Department/Position linkage is through `employee_assignments`.
- Contacts use `contact_type` plus `contact_ref_id`; `contact_ref_id` is polymorphic and has no FK to employees or organizations.
- Permission catalog is derived from `resources` and `resource_actions`; there is no separate permission table.
- Policy is resource-type and workflow-state scoped. There is no resource-instance authorization and no condition rule.
- Workflow state catalog is separate from authorization decision logic.
- Policy effects are `ALLOW`, `DENY`, and `ABSOLUTE_DENY`.
- Policy does not create permission; it restricts an already effective profile permission.
- Delegation, temporary permission and permission effective dates are out of scope.

## 3. Explicit non-decisions

The source documents do not define persistence for User Override, exact HTTP
route names, multilingual tables, or application-level cycle validation. These
must not be invented in implementation. Each requires an approved additive
design decision before code or migration work is added.

## 4. Change control

Any change to a locked decision requires updating this baseline and the affected
architecture contract before implementation. A migration must not introduce a
resource-instance column, condition table, Department/Position organization
FK, or local identity/permission store without an explicit approved revision.
