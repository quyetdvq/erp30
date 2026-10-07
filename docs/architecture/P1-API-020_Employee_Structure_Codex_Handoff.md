# P1-API-020 — Employee Structure API — Codex Implementation Handoff

Status: READY FOR IMPLEMENTATION WITH MANDATORY PREFLIGHT

Authority baseline:
- UR `00_User_Requirements_v1.0.13.md`
- FRS `01_P1_Functional_Requirements_Specification_v1.9.md`
- DBD `02_P1_Database_Design_v1.12.md`
- Implementation Contract `03_P1_Implementation_Contract_v1.7.md`
- Implementation Plan `04_P1_Implementation_Plan_v1.6.md`
- API Design `05_P1_API_Design_v1.4.md`
- UI Design `06_P1_UI_Design_v1.5.md`

Repository prerequisite already completed:
- authority package commit: `3b3e169`
- CHANGE-AUTH-DOMAIN-001 implementation commit: `49a9a3b`

This task must be implemented against the real repository state at or after `49a9a3b`. The older `backend_v1.0.0.zip` snapshot is useful only for gap analysis; it is not the implementation authority.

---

## 1. Goal

Implement the CONTRACT_READY P1 Slice 2 HTTP API for:

- Department
- Position
- Employee
- Employee Assignment

Use the existing `/api/v1` transport, common list/query infrastructure, request-id/locale/error handling, and the post-`49a9a3b` catalog-driven Authorization flow.

Do not globally reorganize the backend. Do not start P1-API-030. Do not change Resource Domain semantics.

---

## 2. Mandatory preflight before editing

Inspect the real repository first.

Verify:

1. `HEAD` contains `3b3e169` and `49a9a3b` in history.
2. Worktree changes unrelated to this task are left untouched.
3. Inspect the current versions of:
   - `app/Config/Routes.php`
   - API base controller / API transport / error mapper
   - `CommonListQueryAdapter` and authorized list source abstractions
   - `AuthorizationService`
   - post-change `EvaluationOrganizationRequest`
   - `ResourceCatalogRepository`
   - Employee Assignment ORGANIZATION resolver
   - `EmployeeRepository`
   - `EmployeeService`
   - `EmployeeAssignmentRepository`
   - `EmployeeAssignmentService`
   - API-010 controller/service/tests as the current HTTP pattern
   - canonical OpenAPI source and generated-client pipeline
4. Do not copy the pre-CHANGE-AUTH request constructor or any removed Primary Assignment authorization fallback from old code.
5. Locate the real OpenAPI source and commands used by P1-API-002. If the OpenAPI source/lint/codegen pipeline is genuinely absent or not runnable in the current repository, stop and report that prerequisite rather than inventing a new spec location/toolchain.

---

## 3. Source traceability

Primary business/functional sources:

- UR-EMP-001 — Employee
- UR-EMP-002 — Department and Position
- UR-EMP-003 — Employee Assignment
- FRS-P1-EMP-001 — Department
- FRS-P1-EMP-002 — Position
- FRS-P1-EMP-003 — Employee Master
- FRS-P1-EMP-004 — Employee Assignment
- FRS-P1-EMP-005 — Default Actor Context vs authoritative Evaluation Organization
- FRS-P1-GEN-003 — audit actor/timestamp
- FRS-P1-GEN-004 — authorization before mutation/export
- FRS-P1-GEN-005 — validation / atomic mutation
- FRS-P1-GEN-006 — search/filter/sort/pagination
- FRS-P1-SEC-001..002 — sensitive Resource action separation / no export bypass
- FRS-P1-SVC-002 — EmployeeAssignmentService

Persistence/runtime:

- DBD 9.1 `departments`
- DBD 9.2 `positions`
- DBD 9.3 `employees`
- DBD 9.4 `employee_assignments`
- DBD 19 transaction boundaries
- DBD 20 service-enforced validation boundaries
- Implementation Contract 3 runtime conventions
- Implementation Contract 15 repository/service boundary
- Implementation Contract 16 validation/error ordering
- Implementation Contract 18.3 mandatory API tests
- Implementation Contract 18.4 concurrency tests

Wire/API:

- API Design 3 — common transport
- API Design 4 — common query
- API Design 5 — mutation pattern
- API Design 6 — endpoint authorization
- API Design 8.1..8.4 — P1-API-020 surface
- API Design open-item register — export media type remains pending; gender is resolved by DEC-P1-001

Downstream consumer:

- UI Design 9 — Employee Structure screens

---

## 4. Approved API surface

Prefix: `/api/v1`

### 4.1 Department — Resource `department`, Domain `MASTER`, Evaluation Organization `GROUP`

Implement:

```text
GET    /departments
POST   /departments
GET    /departments/{id}
PATCH  /departments/{id}
POST   /departments/{id}/actions/set-state
```

Do not implement `/departments/export` yet. Export capability/action exists, but exact public media type remains CONTRACT_PENDING.

Business fields:

```text
parent_id?
code
name
description?
status
```

Required invariants:

- code unique;
- no self-parent;
- no direct or indirect Department cycle;
- no Organization ownership field;
- inactive/closed Department remains historical data;
- inactive Department cannot be newly selected for Assignment.

### 4.2 Position — Resource `position`, Domain `MASTER`, Evaluation Organization `GROUP`

Implement:

```text
GET    /positions
POST   /positions
GET    /positions/{id}
PATCH  /positions/{id}
POST   /positions/{id}/actions/set-state
```

Do not implement `/positions/export` yet.

Business fields:

```text
code
name
level?
description?
status
```

Required invariants:

- code unique;
- no direct Department/Organization ownership;
- inactive/closed Position remains historical data;
- inactive Position cannot be newly selected for Assignment.

### 4.3 Employee — Resource `employee`, Domain `MASTER`, Evaluation Organization `GROUP`

Implement:

```text
GET    /employees
POST   /employees
GET    /employees/{id}
PATCH  /employees/{id}
POST   /employees/{id}/actions/set-state
```

Do not implement `/employees/export` yet.

Business fields:

```text
user_id?
employee_code
employee_name
gender?
date_of_birth?
place_of_origin?
hire_date?
manager_employee_id?
description?
status
```

Required invariants:

- employee_code unique;
- `user_id` unique when non-null;
- Employee may exist without User;
- manager cannot be self;
- manager mutation cannot create a direct/indirect management cycle;
- newly selected manager must be ACTIVE;
- Employee inactive is retained; no hard delete;
- Employee must not gain organization_id / department_id / position_id ownership fields;
- Direct Manager remains current pointer only; do not invent effective-dated manager history.

`gender` is part of the payload/read surface and is resolved by DEC-P1-001:
the canonical values are `MALE`, `FEMALE`, `OTHER`, and `NULL`. Validate the
same values in the service/API path; labels are presentation-only and the
existing `VARCHAR(20)` column remains unchanged.

### 4.4 Employee Assignment — Resource `employee_assignment`, Domain `ORGANIZATION`

Implement:

```text
GET    /employee-assignments
POST   /employee-assignments
GET    /employee-assignments/{id}
PATCH  /employee-assignments/{id}
POST   /employee-assignments/{id}/actions/set-state
```

No export route is approved for Assignment in API Design 8.4.

Business fields:

```text
employee_id
organization_id
department_id?
position_id?
is_primary
effective_from
effective_to?
description?
status
```

Required invariants:

- `effective_to >= effective_from` when present;
- multiple active Assignments are allowed;
- at most one effective Primary Assignment per Employee at any instant/date;
- Primary overlap must be rejected under concurrency;
- create/new reference selection must validate the Employee reference exists; Organization/Department/Position newly selected for an Assignment must be ACTIVE. Preserve any stricter Employee-active rule only if it is already part of the approved current service contract/tests; do not invent a new rule in the API layer;
- Secondary Assignment remains valid and may participate in PBAC lanes;
- Assignment history remains readable after end/inactivation;
- Employee identity of an existing Assignment must remain consistent with the existing service contract;
- no hard delete.

Important existing-service review:

The older snapshot revalidated every Assignment reference as ACTIVE even when merely ending/inactivating an existing Assignment. That is too strong if it still exists: the baseline says newly selected references / new Assignments must be active, while historical references must remain valid/readable. If the current post-`49a9a3b` service still has this behavior, fix it at the domain service boundary so that:

- create and changed/new references are validated as active;
- transition to/re-activation as ACTIVE validates required references;
- simply ending/inactivating a historical Assignment does not fail only because an already-referenced Organization/Department/Position/Employee later became inactive;
- no historical FK/reference is rewritten.

Keep the existing concurrency/transaction semantics for Primary overlap.

---

## 5. Authorization contract

All protected use-cases must use the current `AuthorizationService` public entry point.

Do not create a second authorization facade.

Do not reintroduce Resource-name hard-coding, generic Organization probing, or Primary Assignment fallback.

### 5.1 MASTER resources

For `department`, `position`, and `employee`:

```text
Resource Domain = MASTER
Authoritative Evaluation Organization = GROUP
```

Actions:

```text
list/detail -> view
create      -> create
update      -> edit
set-state   -> setstate
```

Employee filters by Organization/Department/Position are DATA FILTERS ONLY. They must never become `evaluation_organization_id`, never change Employee authorization scope, and must not require `employee_assignment.view` simply to filter the Employee MASTER list.

### 5.2 Employee Assignment

```text
create                     -> authoritative context from request assignment.organization_id
existing detail/edit/state -> authoritative context from persisted assignment.organization_id
list                       -> each row uses persisted assignment.organization_id
```

Client `organization_id` filters may only narrow Assignment list results. They never override persisted row context.

Primary Assignment must not be used as Resource authorization context fallback.

For detail/update/state:

1. load persisted target;
2. evaluate `employee_assignment.view` for visibility using persisted target context;
3. invisible target -> 404;
4. if visible, evaluate requested mutation action;
5. denied mutation -> 403;
6. only then execute domain mutation.

For list:

- row authorization/data-scope filtering must occur before `total` and pagination;
- denied rows must not leak through count/search/filter;
- cache/batch a `view` authorization decision by unique persisted `organization_id` within one request when safe, to avoid repeated identical PBAC work;
- do not redesign the global authorization/cache subsystem for this task.

### 5.3 Assignment organization change review

API Design states that existing edit authorization is derived from the persisted Assignment Organization. Do not silently invent a new authorization rule for a candidate/new `organization_id`.

If current service permits changing `organization_id`, preserve the documented persisted-target authorization semantics for P1-API-020 and add a regression proving that a client-supplied organization cannot replace the persisted authorization context. If Engineering believes cross-Organization reassignment requires dual-scope authorization or `organization_id` immutability, report it as a separate change-control/design issue rather than changing baseline semantics inside this task.

---

## 6. Application/service structure

Preferred targeted shape, adapting to the current repository conventions rather than creating parallel architecture:

```text
app/Controllers/Api/EmployeeStructure.php
app/Services/Api020EmployeeStructureService.php
```

Reuse/extend current:

```text
EmployeeRepository
EmployeeService
EmployeeAssignmentRepository
EmployeeAssignmentService
CommonListQueryAdapter
AuthorizationService
```

### Department/Position prerequisite gap

The older snapshot contained no Department/Position repository/service boundary even though FRS requires Department cycle validation and Position/Department mutation semantics. The Plan Gate 2 registry also does not have a separate Department/Position service task.

At preflight, verify current repo reality.

If these boundaries are still absent, implement the smallest required domain support inside P1-API-020 without adding a new P1 Task ID and without global refactoring. A reasonable current-structure fit is:

```text
app/Repositories/DepartmentRepository.php
app/Services/DepartmentService.php
app/Repositories/PositionRepository.php
app/Services/PositionService.php
```

Alternative naming/layout is allowed only if it follows an already-established equivalent repository/service pattern.

Controller must remain thin. Department cycle, manager cycle, active-reference rules, Primary overlap, and audit mutation semantics belong in service/domain boundaries, not controller code.

Do not move unrelated existing Services/Repositories into Modules.

---

## 7. List/query behavior

Reuse the canonical common query contract:

```text
q
page
page_size
sort
filter[field]
filter[field][]
```

Defaults:

```text
page = 1
page_size = 50
max page_size = 200
id ASC deterministic tie-breaker
```

Unknown query/sort/filter syntax or fields -> 400.

Authorization/data visibility must be applied before `total`.

### 7.1 Employee required filters

Implement at minimum exactly the API Design minimum:

```text
q
filter[status]
filter[organization_id]
filter[department_id]
filter[position_id]
```

`q` should use the canonical Employee identifiers/display fields supported by the current repository contract (at minimum employee code/name if current common conventions permit).

Organization/Department/Position filters must query through effective Employee Assignment, not through invented ownership columns.

Use the same effective-time semantics as the existing `EmployeeAssignmentService`/Evaluation Time behavior. Do not duplicate a competing date-resolution rule in the controller.

### 7.2 Assignment required filters

Implement at minimum:

```text
employee_id
organization_id
department_id
position_id
is_primary
status
effective_at
```

`effective_at` means an Assignment whose effective period contains the supplied date under the canonical inclusive Assignment effective-date semantics already used by the Employee Assignment domain service. Validate date syntax; do not use browser time as authority.

### 7.3 Department/Position allowlists

Do not invent a large public query surface.

Use only fields justified by approved UI/common-query needs and the current OpenAPI conventions. At minimum support list/search needed by approved selectors and administration screens; keep allowlists explicit. If current canonical API spec already defines more precise Department/Position allowlists, that spec wins.

---

## 8. Employee Primary Assignment projection

API Design permits list/detail to include the smallest server-derived current Primary Assignment projection required by UI:

```text
current_primary_organization?
current_primary_department?
current_primary_position?
```

These are Employee MASTER read projections, not Employee ownership and not authorization scope inputs.

Rules:

- use existing `resolvePrimary()` semantics at the operation Evaluation Time;
- do not persist projection fields;
- do not use projection values to authorize Employee;
- do not require `employee_assignment.view` solely to expose the projection if the approved Employee API contract treats it as part of Employee read data;
- do not invent a new DTO shape if the repository/OpenAPI contract has not defined one.

Because API Design says the projection “may include”, it may be deferred if the current canonical OpenAPI has no approved object/reference shape. If deferred, explicitly report that in the implementation evidence; do not invent an incompatible shape just to satisfy future UI.

---

## 9. Mutation rules

### 9.1 Request fields

Do not accept client-controlled audit fields as authoritative:

```text
created_by
updated_by
created_at
updated_at
```

Use the existing `MutationContext` / operation actor/time behavior.

Reject unsupported fields rather than silently persisting them.

### 9.2 Status/state

Use the standard set-state request:

```json
{
  "status": "ACTIVE"
}
```

Canonical status values come from DBD per entity:

- Department: `ACTIVE | INACTIVE | CLOSED`
- Position: `ACTIVE | INACTIVE | CLOSED`
- Employee: `ACTIVE | INACTIVE`
- Employee Assignment: `ACTIVE | INACTIVE`

Do not create DELETE endpoints.

Do not invent Lifecycle transitions for these masters if no lifecycle is configured by baseline.

### 9.3 Duplicate/conflict/error translation

Expected unique conflicts such as duplicate Department code, Position code, Employee code, or non-null Employee `user_id` must be translated to stable domain/API conflict semantics rather than leaking SQL/database exceptions.

Preserve canonical distinctions:

- malformed query/request -> 400;
- unauthenticated -> 401;
- visible but action denied -> 403;
- invisible existing target -> 404;
- duplicate/concurrency conflict -> 409 when the existing error taxonomy classifies it as conflict;
- semantic validation -> 422;
- unexpected persistence/infrastructure failure -> 500 without SQL/stack/secret leakage.

Do not change global error-code semantics unless a real contract defect requires change control.

---

## 10. OpenAPI

Update the canonical OpenAPI source in the same implementation change for every CONTRACT_READY endpoint implemented here.

OpenAPI requirements:

- `/api/v1` paths above;
- common success/error envelopes;
- BIGINT values serialized as JSON strings;
- snake_case fields;
- query allowlists;
- request/response schemas;
- authorization-relevant 401/403/404/409/422 responses as applicable;
- nullable `gender` enum is exactly `MALE`, `FEMALE`, `OTHER`, or `null`;
- no Department/Position/Employee export operation until export media contract becomes CONTRACT_READY;
- no invented nested `/employees/{id}/assignments` route; Employee Detail can use `/employee-assignments?filter[employee_id]=...` unless a later contract adds a nested route.

Run the repository-approved Redocly lint and frontend client generation from P1-API-002. Generated artifacts remain tool-owned; do not hand-edit them.

---

## 11. Tests

Add API/application/repository tests sufficient to prove the following.

### 11.1 Common transport

- canonical success envelope;
- collection meta page/page_size/total/request_id;
- BIGINT id/reference values are JSON strings;
- request id propagated;
- `Accept-Language` vi/en and fallback behavior remains intact;
- unknown sort/filter/query -> 400;
- no stack/SQL/secret in error response.

### 11.2 Department

- create/list/detail/update/set-state;
- unique code conflict;
- self-parent reject;
- indirect cycle reject;
- reparent cycle reject;
- no Organization owner field accepted;
- MASTER authorization at GROUP;
- invisible detail -> 404; visible edit denied -> 403.

### 11.3 Position

- create/list/detail/update/set-state;
- unique code conflict;
- no Organization/Department owner field accepted;
- inactive Position cannot be newly used in Assignment;
- MASTER authorization at GROUP.

### 11.4 Employee

- create without User;
- duplicate employee code conflict;
- duplicate non-null user mapping conflict;
- self-manager reject;
- direct/indirect manager cycle reject;
- inactive manager reject for new manager selection;
- Employee can become inactive while historical references remain;
- Organization/Department/Position filters use effective Assignment but Employee authorization remains GROUP;
- filter values do not become Evaluation Organization inputs;
- optional current Primary projection, if implemented, uses `resolvePrimary()` and does not persist ownership;
- the DEC-P1-001 gender catalog is enforced without schema or lookup-table changes.

### 11.5 Employee Assignment

- create/detail/update/set-state;
- create authorization from request `organization_id`;
- detail/edit/state authorization from persisted `organization_id`;
- client Organization filter only narrows list;
- row authorization occurs before total/pagination;
- denied rows absent from data and count;
- missing/unresolved context fail closed;
- Primary Assignment is not Resource authorization fallback;
- date range validation;
- inactive newly selected Organization/Department/Position rejected; Employee reference existence validated without inventing a new active-status rule beyond the current approved service contract;
- Primary overlap rejected;
- concurrent overlapping Primary create/update cannot both commit;
- Secondary Assignment allowed;
- history readable after end/inactivation;
- ending/inactivating an existing Assignment is not blocked solely because an already-referenced Organization/Department/Position/Employee later became inactive;
- `effective_at` filter boundary cases;
- existing edit authorization uses persisted Assignment context even if payload contains another `organization_id`.

### 11.6 Regression

Re-run at minimum:

- full PHPUnit;
- PHPStan level 8;
- CHANGE-AUTH-DOMAIN-001 focused regression;
- API-001 transport tests;
- API-003 list-query tests;
- API-010 regression tests;
- Employee/Assignment domain/concurrency tests.

No schema migration is expected for P1-API-020. If implementation discovers that a schema change is required, stop and report a baseline/design conflict before creating a migration.

---

## 12. Non-goals / forbidden changes

Do not:

- start P1-API-030 or later API tasks;
- globally reorganize backend folders;
- rewrite applied migrations;
- add Employee `organization_id`, `department_id`, or `position_id` columns;
- add Organization ownership to Department/Position;
- create historical manager persistence;
- change the DEC-P1-001 Gender catalog or introduce localized values in persistence;
- implement export file routes/media formats while still CONTRACT_PENDING;
- create nested Employee Assignment routes not in API Design;
- create DELETE endpoints;
- create Resource Instance Authorization;
- use client `evaluation_organization_id` as authority;
- use Primary Assignment as Resource scope fallback;
- bypass `AuthorizationService`;
- hand-edit generated API client code;
- mix unrelated cleanup/refactors into this change.

---

## 13. Suggested changed-file boundary

Exact file names may differ after current-repo inspection, but the change should remain close to:

```text
backend/app/Config/Routes.php
backend/app/Controllers/Api/EmployeeStructure.php
backend/app/Services/Api020EmployeeStructureService.php
backend/app/Repositories/DepartmentRepository.php           # only if still absent
backend/app/Services/DepartmentService.php                  # only if still absent
backend/app/Repositories/PositionRepository.php             # only if still absent
backend/app/Services/PositionService.php                    # only if still absent
backend/app/Repositories/EmployeeRepository.php             # query/API support as needed
backend/app/Services/EmployeeService.php                    # only targeted invariant/API support
backend/app/Repositories/EmployeeAssignmentRepository.php   # list/filter support as needed
backend/app/Services/EmployeeAssignmentService.php          # targeted historical-reference fix if still present
backend/tests/... P1-API-020 contract/regression tests
OpenAPI canonical source
Generated frontend API artifact(s)
```

Do not force files into this list if the current repository already has an equivalent abstraction.

---

## 14. Definition of Done

P1-API-020 can be reported COMPLETE only when:

1. All 20 CONTRACT_READY non-export CRUD/state endpoints across Department, Position, Employee, and Employee Assignment are implemented.
2. Department/Position missing domain-boundary gap, if still present, is fixed without controller-side invariant duplication.
3. Department/Position/Employee authorize as MASTER at GROUP.
4. Employee Assignment create/existing/list authorization follows the canonical ORGANIZATION context rules.
5. Employee Assignment row filtering is authorization-safe before totals/pagination.
6. Employee assignment filters do not alter Employee MASTER scope.
7. Primary Assignment does not reappear as authorization fallback.
8. Manager-cycle and Primary-overlap invariants remain concurrency-safe.
9. Historical Assignment can be ended/read without requiring already-referenced entities to remain active, while new/changed references still require ACTIVE status.
10. No export media route is implemented prematurely.
11. The DEC-P1-001 gender catalog is not expanded or localized in persistence.
12. OpenAPI updated and linted.
13. Generated client regenerated by the approved pipeline.
14. Full PHPUnit passes.
15. PHPStan level 8 passes.
16. `git diff --check` passes.
17. No `.env`, cache, build output, logs, dumps, coverage or generated junk enters the commit.
18. P1-API-030 remains untouched.
19. Final report includes changed-file inventory and exact test/lint/codegen evidence.

---

## 15. Codex execution instruction

Implement `P1-API-020` exactly against the repository state after commits `3b3e169` and `49a9a3b`.

First inspect the real repository and reconcile it with this handoff. Do not assume the old backend snapshot reflects the current Authorization request shape.

Keep the task targeted to Department, Position, Employee, and Employee Assignment API surfaces. Reuse the existing common transport/query infrastructure and the catalog-driven AuthorizationService. If Department/Position repository/service boundaries are still missing, add only the minimum domain support required by the approved FRS; do not create a new P1 Task ID or perform a global architecture refactor.

Do not implement export endpoints whose media type remains CONTRACT_PENDING. Use only the DEC-P1-001 Employee gender values. Do not add Employee organization ownership. Do not use Primary Assignment as Resource authorization fallback.

Before declaring completion, run and report the full required PHPUnit/PHPStan/API/OpenAPI/codegen gates, authorization negative-path coverage, manager/Assignment concurrency regressions, `git diff --check`, changed-file inventory, and confirmation that P1-API-030 was not started.
