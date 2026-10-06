# PRE-API020-DOC - Employee API/UI contract reconciliation

## Outcome

Status: `BLOCKED` / `NEEDS_DECISION`.

This preflight reconciles the existing Employee and Employee Assignment
services with API Design v1.3 and UI Design v1.4. It does not implement
P1-API-020. The Employee authorization context is not defined sufficiently to
start implementation without inventing policy, so the exact upstream decision
in section 7 is required first.

## Authority and scope

The inspection used the current compiled context for `P1-API-020` and the
following exact authority revisions:

| Authority | Revision | Relevant sections |
|---|---:|---|
| Functional Requirements Specification | v1.8 | `FRS-P1-EMP-003..005` |
| Implementation Contract | v1.6 | Evaluation Organization and API transport conventions |
| Implementation Plan | v1.5 | pre-Gate-7 reconciliation and `P1-API-020` dependency |
| API Design | v1.3 | section 8, Employee and Employee Assignment routes/fields |
| UI Design | v1.4 | `UI-P1-EMP-003..004`, design-pending items |

No authority document, schema, runtime code, OpenAPI document, generated
client, task registry, or ledger was changed by this preflight.

## 1. Employee list/query contract

The UI filter labels map to the following canonical API-003 query names:

| UI input | API query key | Semantics |
|---|---|---|
| Search | `q` | Case-insensitive search over persisted `employee_code` and `employee_name`. It is not an authorization selector. No user-email search is added. |
| Status | `filter[status]` | Exact match against `employees.status`. |
| Organization | `filter[organization_id]` | Exact match against the employee's current effective Primary Assignment organization at one request Evaluation Time. |
| Department | `filter[department_id]` | Exact match against the same Primary Assignment projection. |
| Position | `filter[position_id]` | Exact match against the same Primary Assignment projection. |

The wire names are explicit IDs even though the UI labels are short names.
IDs are BIGINT transport strings. Repeated values for one filter field use
the API-003 OR-within-field rule; different filter fields are ANDed. These
filters constrain the authorized result set and never select or override an
Evaluation Organization.

The three assignment filters use one captured Evaluation Time per request and
the existing `EmployeeAssignmentService::resolvePrimary()` semantics. An
employee with no effective Primary Assignment has null primary projections and
does not match a non-null organization, department, or position filter.

Sorting and pagination must use API-003 allowlists and authorization-safe
totals. The Employee endpoint must not expose an unallowlisted sort/filter
field or count rows before authorization/data-scope filtering.

## 2. Employee list/read DTO

The smallest UI-complete read projection is:

```text
id: string                         # BIGINT transport string
employee_code: string
employee_name: string
user: null | {
  id: string                       # BIGINT transport string
  username: string
  email: string
}
current_primary_organization: null | {
  id: string                       # BIGINT transport string
  code: string
  name: string
  type: string
}
current_primary_department: null | {
  id: string                       # BIGINT transport string
  code: string
  name: string
}
current_primary_position: null | {
  id: string                       # BIGINT transport string
  code: string
  name: string
}
manager: null | {
  id: string                       # BIGINT transport string
  employee_code: string
  employee_name: string
}
status: string
```

The existing API fields remain available on detail/create/edit contracts as
already specified (`user_id`, employee identity fields, `gender`, dates,
`manager_employee_id`, description, and status). The nested `user` and
`manager` values above are read-only summaries; this reconciliation does not
add a User write contract or a manager-history contract.

All nested IDs remain BIGINT strings. No derived primary field is persisted.
The projection is assembled server-side from Employee, the current effective
Primary Assignment, referenced Organization/Department/Position records, the
persisted manager employee, and the persisted User mapping. It must be
materialized by the endpoint/service boundary (or a documented batch query),
not by per-row undocumented frontend calls.

## 3. Primary Assignment projection

`EmployeeAssignmentService::resolvePrimary()` is the canonical source. It
evaluates active, date-effective assignments at one captured Evaluation Time
and rejects an effective Primary overlap. The result is a read projection
only. The FRS explicitly permits multiple active assignments, including in
multiple Companies, and states that Primary Assignment is only the default
actor context when an operation has no authoritative operation context.

Consequences:

- Primary organization/department/position are display and query projection
  fields, not Employee ownership fields.
- Secondary assignments remain available through the Assignment API and may
  participate in authorization on an exact scope path.
- Assignment history remains readable according to existing service
  semantics.
- No Employee table columns, hidden JSON, or other persistence are introduced.

## 4. Employee authorization context

The current authority is insufficient to derive a unique Evaluation
Organization for Employee endpoints:

| Protected operation | Persisted canonical source currently available | Result |
|---|---|---|
| Employee list | Employee has no `organization_id`; assignments can span Companies | **Unresolved** |
| Employee create | Current Employee create payload has no owner/parent/scope organization | **Unresolved** |
| Employee detail/edit/state | Existing Employee has no persisted target/owner organization; Primary is derived and may change | **Unresolved** |

The server must not accept a client `organization_id` or
`evaluation_organization_id` as a substitute. It must also not make the
derived Primary Assignment authoritative by implementation convenience.

The following choices are deliberately not made here:

- treating every Employee operation as GROUP-scoped;
- treating current Primary Organization as Employee owner;
- authorizing an existing Employee if any visible assignment happens to match;
- adding an Employee organization/owner column or changing the schema.

Any of those would change business authorization semantics or persistence
outside this documentation-only task.

## 5. Employee Assignment contract (confirmed unchanged)

The existing API contract remains:

```text
GET    /api/v1/employee-assignments
POST   /api/v1/employee-assignments
GET    /api/v1/employee-assignments/{id}
PATCH  /api/v1/employee-assignments/{id}
POST   /api/v1/employee-assignments/{id}/actions/set-state
```

Create and edit evaluate authorization at the submitted/persisted
`assignment.organization_id`. The minimum list filters remain:

```text
employee_id
organization_id
department_id
position_id
is_primary
status
effective_at
```

These are API-003 allowlisted filters. `effective_at` means effective-date
selection against the existing `effective_from`/`effective_to` interval; its
wire date format must reuse the established business-date/Evaluation Time
convention when API-020 is specified, rather than inventing a second format.

The current service keeps `employee_id` immutable on edit, validates active
references, validates date ranges, and prevents overlapping effective Primary
Assignments under the existing employee lock/transaction convention.

## 6. Gender status

`employees.gender` remains `CONTRACT_PENDING` / source-open. API Design v1.3
excludes a strict enum until `DEC-P1-001`; UI Design v1.4 has a select control
but marks its options `DESIGN_PENDING`. No enum, catalog, option list, or
validation rule is invented here.

## 7. Exact upstream decision required

Upstream decision required: **YES**.

Please confirm one of the following in the authoritative contract, or define a
different canonical source:

> Is `employee` a GROUP-scoped master resource for list/create/detail/edit/
> state, with assignment-derived visibility and query filtering, or does
> Employee have another canonical persisted/target organization source?

The decision must explicitly cover:

1. Employee list when no organization filter is supplied.
2. Employee create, whose current payload contains no organization field.
3. Existing Employee detail/edit/state, whose persisted target has no owner
   organization.
4. An Employee with multiple active assignments across Companies.
5. An Employee with no Assignment.

Until this is decided, P1-API-020 implementation must not begin. Once decided,
the same authoritative context must drive visibility, 404 versus 403 behavior,
authorization-safe totals, and all Employee list/detail mutations. The client
must never supply the authority.

## 8. UI/API alignment constraints

The Employee list/detail can be implemented without undocumented N+1 calls
only if the Employee read projection above is returned in the Employee
response (or through a documented, authorized batch projection). The frontend
must not derive authorization, resolve Primary Assignment ownership, or send a
client Evaluation Organization.

The detail tabs remain Overview, Assignments, Contacts, and Audit. Assignment
rows/forms use the existing Assignment fields and routes. Selector data must
come from authorized, documented collection endpoints; hidden identities must
not leak through autocomplete or counts. User create/edit remains pending
because the canonical Shield User write fields are not defined.

## Validation

This is a documentation-only preflight. Runtime, schema, OpenAPI, generated
client, PHPUnit, PHPStan, and frontend checks are not applicable and were not
run. The following repository guards are run after this artifact is created:

```text
validate-context-cache.php   -> authority/cache, module-map coverage, task/dependency graph
validate-ledger.php          -> task/ledger integrity
architecture-guard.php       -> architecture boundary checks
git diff --check              -> whitespace/error check
```

Expected invariant: 100 registered task IDs and existing Gate dependencies
remain unchanged. The preflight identifier `PRE-API020-DOC` is not added to
the task registry or execution ledger.

## Change boundary

- Added only this evidence artifact.
- No runtime code, schema/migration, OpenAPI, generated frontend artifact, or
  architecture baseline was changed.
- No export response, Employee endpoint, Assignment endpoint, Employee API
  implementation, or P1-API-020 work was started.
- The unrelated pre-existing change under `docs/thamkhao (Không được sử dụng)`
  is preserved and is not part of the scoped commit.
