# Domain And Data Architecture

**Status:** Official implementation design

## 1. Domain map

```text
provinces -> wards -> contacts

organizations
departments
positions
employees -> employee_assignments -> organizations/departments/positions

lifecycles -> lifecycle_states -> workflow_states
resources -> resource_actions
roles -> role_permissions -> resource_actions
policies -> policy_actions -> resource_actions
authorization_logs
```

## 2. Organization Context

`organizations` is the Organization Context aggregate. `parent_id` is a
self-reference and must not create a cycle. `type` is fixed to the five
approved values. `code` is unique. `status`, `active_from` and `active_to`
preserve historical organizations without deleting business history.

## 3. Employee and assignment

`employees` is the employee master and maps at most one Shield user through
unique `user_id`. An employee can have multiple assignments and multiple active
assignments. `is_primary` identifies the primary assignment at a point in time;
the service layer must enforce the temporal rule because the v1.2 schema does
not define a database exclusion constraint.

## 4. Independent masters

Departments form their own hierarchy through `departments.parent_id`.
Positions have an integer `level`. Neither table has `organization_id`; a
contextual relationship is represented only by `employee_assignments`.

## 5. Polymorphic references

Contacts and policy participants use typed references. The database must not
create a foreign key for `contacts.contact_ref_id` or
`policies.participant_ref_id`, because the referenced table depends on the type.
Application validation must verify the referenced entity for the selected type.

## 6. Resource and lifecycle

`resources.lifecycle_id` attaches a resource type to a lifecycle.
`lifecycle_states` maps a lifecycle to the independent workflow state catalog.
`resource_actions` is the permission catalog source and is unique per resource
and action code.

## 7. Policy and audit

Policies are scoped by Organization Context, Resource Type, lifecycle state and
participant. Policy actions point to resource actions and carry the effect.
Authorization logs capture decision context and are not a general view/list
access log.
