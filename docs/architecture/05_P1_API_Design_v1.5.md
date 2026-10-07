---
document_id: "API-MINI-ERP-P1-001"
title: "P1 API Design - Dinktech Mini ERP"
version: "1.5"
status: "draft"
owner: "Product / Architecture / Engineering"
date: "2026-10-07"
scope: "P1 API contract by rolling vertical slice"
authority:
  ur: "00_User_Requirements_v1.0.14.md"
  frs: "01_P1_Functional_Requirements_Specification_v1.10.md"
  dbd: "02_P1_Database_Design_v1.13.md"
  implementation_contract: "03_P1_Implementation_Contract_v1.8.md"
  implementation_plan: "04_P1_Implementation_Plan_v1.7.md"
---

# P1 API Design - Dinktech Mini ERP

## 1. Mục đích

Tài liệu này thiết kế public HTTP API P1 theo mô hình **rolling vertical slice**.

Mục tiêu:

- khóa route/request/response/action contract đủ để backend và frontend phát triển song song;
- giữ API nhất quán với FRS/DBD/Implementation Contract;
- cho phép từng slice chuyển độc lập qua `PLANNED -> CONTRACT_READY -> IMPLEMENTED -> VERIFIED`;
- không ép API phải chờ toàn bộ P1 backend hoàn tất;
- cung cấp source cho OpenAPI + `@hey-api/openapi-ts`.

Tài liệu này **không tạo business semantics mới**. Khi source baseline chưa đủ để khóa payload cụ thể, endpoint phải được đánh dấu `CONTRACT_PENDING`.

## 1.1 Revision v1.2 - Product Group hierarchy

- Product Group transport includes nullable `parent_id`.
- `null` means root; non-null means parent Product Group; PATCH mutation is reparent.
- Flat records with `id`, `parent_id`, `code`, `name`, `description`, `status` are canonical; no calculated path/depth or recursive nested payload is accepted.
- Server validates parent existence, self-parent and direct/indirect cycle. Stable errors include `PRODUCT_GROUP_PARENT_NOT_FOUND`, `PRODUCT_GROUP_SELF_PARENT`, `PRODUCT_GROUP_HIERARCHY_CYCLE`, `PRODUCT_GROUP_INACTIVE_EFFECTIVE_PATH`, and `CONCURRENT_MODIFICATION`.

## 1.2 Revision v1.1 - Language negotiation

- Supported locale `vi`, `en`; default/fallback `vi`.
- Frontend gửi `Accept-Language` theo current UI locale.
- API localized human-readable text trả `Content-Language`.
- Machine fields/error codes remain locale-independent.
- Media contract vẫn JSON; revision này không mở HTML/XML API representation.

## 1.3 Revision v1.3 - Pre-Gate-7 contract reconciliation

- Contract status is a closed set: `PLANNED`, `CONTRACT_PENDING`,
  `CONTRACT_READY`, `IMPLEMENTED`, `VERIFIED`, and `BLOCKED`.
- Gate 7 readiness is recorded per API task in the matrix below. A
  `CONTRACT_READY` task exposes only the approved surface listed in this
  document; an excluded pending sub-capability is not an implementation
  placeholder.
- Product Import row responses are restricted to the canonical
  `import_job_rows` persistence DTO. `record_type`, `source_key`, and raw row
  payload are input/dependency concepts only and are never row-result fields.
- Shield User writes, exact export media type, public Price Resolution, and
  COMBO preview APIs remain `CONTRACT_PENDING`.

---

## 1.4 Revision v1.4 - Resource Domain authorization scope

- Canonical Resource Domain values are `SYSTEM`, `MASTER`, `ORGANIZATION`; `COMPANY` is removed as a Resource Domain.
- `SYSTEM`/`MASTER` endpoints authorize at `GROUP`; `ORGANIZATION` endpoints derive authoritative context from target/owner/parent/persisted context.
- Missing/unknown domain or unresolved `ORGANIZATION` context fails closed; client context and actor Primary Assignment cannot override/fallback Resource scope.
- Employee is a `MASTER` Resource at GROUP scope; Employee Assignment is `ORGANIZATION` scoped by `organization_id`.
- Resource `domain` becomes required on create and read-only after creation in P1.
- Authorization Log visibility, Contact polymorphic owner scope, Price List scope and P1 Import Job scope are made explicit.

## 1.5 Revision v1.5 - `CHANGE-DISPLAY-SETTING-001`

- Bổ sung Pre-Gate-8 Display Settings API contract.
- Resource `display_setting`, Domain `ORGANIZATION`, actions `view`/`edit`.
- Context canonical chỉ `SYSTEM` và `ORGANIZATION`; transport context dùng explicit typed fields, không nhận `company:<id>`/`user:<id>` server scope.
- Runtime/effective response là presentation data và không được dùng làm business timezone/calculation input.
- Locale không nằm trong Display Settings API; tiếp tục qua `Accept-Language` + frontend `ui.locale`.
- Mutation dùng batch set/reset, registry-key allowlist, authorization và atomic behavior.

# 2. API design governance

## 2.1 Contract status

```text
PLANNED          endpoint đã nhận diện nhưng contract chưa đủ để code
CONTRACT_PENDING thiếu source decision; không được Dev tự suy diễn
CONTRACT_READY   route + input + output + errors + auth đã đủ để code
IMPLEMENTED      backend đã implement và OpenAPI đã cập nhật
VERIFIED         contract/integration tests pass
BLOCKED          có baseline/change decision đang chờ
```

No other API contract status is valid. `READY`, `PENDING`, or `DESIGN_READY`
must not be used as API contract statuses.

## 2.2 Gate 7 readiness matrix (P1-API-001..P1-API-060)

The matrix is a documentation readiness view; it does not change the task
registry, dependency graph, Gate 7 count, or implementation status.

| Task | Approved surface | Dependencies | Contract status | Boundary / remaining note |
|---|---|---|---|---|
| P1-API-001 | Common `/api/v1` transport, envelopes, request ID, locale and status mapping | P1-INF-002, P1-AUTH-002 | `CONTRACT_READY` | No endpoint implementation is started by this document. |
| P1-API-002 | OpenAPI source, Redocly lint, and generated-client pipeline | P1-API-001, P1-ENV-003 | `CONTRACT_READY` | Generated artifacts remain tool-owned. |
| P1-API-003 | Allowlisted query, sort, filter, pagination, and authorization-safe totals | P1-API-001 | `CONTRACT_READY` | Uses the common query contract in section 4. |
| P1-API-010 | Foundation, Organization, and Contact routes/actions in section 7 | Gate 2 | `CONTRACT_READY` | Export media type remains pending where noted. |
| P1-API-020 | Employee and Assignment routes/actions in section 8 | Gate 2 | `CONTRACT_READY` | `employees.gender` enum remains `CONTRACT_PENDING` and is excluded from any ready enum schema. Employee MASTER/GROUP scope and Assignment ORGANIZATION scope are now canonical under CHANGE-AUTH-DOMAIN-001. |
| P1-API-030 | Resource, Authorization Group, Policy, Log, Workflow, and Lifecycle routes/actions in section 9 | Gate 3 | `CONTRACT_READY` | Shield User writes remain excluded and pending. |
| P1-API-040 | Auto Numbering series and non-consuming validation routes in section 10 | Gate 4 | `CONTRACT_READY` | Uses `resource.configure_numbering`; no new Resource/Action. |
| P1-API-050 | Product Group, UOM, Product, Price List, and COMBO composition routes in sections 11-12 | Gate 5 | `CONTRACT_READY` | Public Price Resolution and COMBO preview APIs are excluded and pending. |
| P1-API-060 | Product Import job start/list/detail/rows/report routes in section 13 | Gate 6 | `CONTRACT_READY` | Row DTO and filters are limited to canonical `import_job_rows`; no persisted Record Type/Source Key/raw payload. |

`P1-API-090` remains the separate Gate 7 regression/closure task and is not
treated as an implementation of any endpoint in this matrix.

## 2.2A Pre-Gate-8 Display Settings readiness

`P1-DSP-004` là API task của Gate 7A và có status `CONTRACT_READY` theo section 6A của tài liệu này. Gate 8 không được mở cho tới `P1-DSP-090` closure.

| Task | Approved surface | Dependencies | Contract status |
|---|---|---|---|
| `P1-DSP-004` | Display Settings runtime/definitions/values/batch set/reset | `P1-DSP-003`, `P1-API-001`, `P1-API-002` | `CONTRACT_READY` |

## 2.3 Change rule

Sau khi endpoint đã `CONTRACT_READY`:

- breaking route/field/status semantics phải bump API Design version;
- nếu thay business semantics/schema thì upstream artifact tương ứng cũng phải change-control;
- OpenAPI phải thay trong cùng change;
- generated frontend artifact phải regenerate.

## 2.4 Rolling slice rule

```text
Service prerequisite satisfied
-> API contract CONTRACT_READY
-> OpenAPI lint
-> implementation
-> contract tests
-> VERIFIED
```

---

# 3. Common transport contract

```text
Base URL: http://api.monmin.net
Prefix:   /api/v1
JSON:     snake_case
BIGINT:   JSON string
DECIMAL:  JSON decimal string
```

## 3.1 Success envelope

Single:

```json
{
  "data": {},
  "meta": {
    "request_id": "uuid"
  }
}
```

Collection:

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "page_size": 50,
    "total": 0,
    "request_id": "uuid"
  }
}
```

## 3.2 Error envelope

```json
{
  "error": {
    "code": "DOMAIN_REASON",
    "message": "Human-readable message.",
    "details": []
  },
  "meta": {
    "request_id": "uuid"
  }
}
```

## 3.3 HTTP status

```text
200 read/update/action success with body
201 create success
204 success without body
400 malformed request/query
401 unauthenticated/invalid authentication
403 visible target but action denied
404 not found OR target not visible
409 conflict/concurrency/unique/current-state conflict
422 semantic validation
500 unexpected server error
```

## 3.4 Request ID

- accept `X-Request-ID`;
- invalid/missing -> server generates UUID;
- echo response header;
- include `meta.request_id`;
- propagate to authorization audit/application log.

## 3.5 Language negotiation

Client request:

```http
Accept-Language: vi
```

Supported:

```text
vi (default/fallback)
en
```

Rules:

- backend negotiate language using CodeIgniter request language negotiation;
- unsupported/missing locale -> `vi`;
- localized response sends `Content-Language`;
- `error.message`, `details[].message` may translate;
- `error.code`, `details[].code`, `details[].field`, JSON keys, enum/codes, Resource/Action, IDs do not translate;
- API remains `application/json`;
- business record values are returned exactly as stored unless endpoint explicitly defines a derived localized display field; P1 defines none for multilingual business content.

OpenAPI must define optional/common `Accept-Language` request header and `Content-Language` response header for API operations using localized human-readable text.

---

# 4. Common list/query contract

Canonical query:

```text
q=
page=
page_size=
sort=
filter[field]=
filter[field][]=...
```

Defaults:

```text
page = 1
page_size = 50
max page_size = 200
```

Rules:

- allowlist search/filter/sort per endpoint;
- unknown sort syntax/field -> 400;
- authorization/data-scope filtering before `total`;
- deterministic tie-breaker defaults to `id ASC`;
- selector/list mode must not expose inactive records unless requested/allowed.

---

# 5. Mutation pattern

## 5.1 Standard CRUD route

Canonical when action exists:

```text
GET    /api/v1/<resources>
POST   /api/v1/<resources>
GET    /api/v1/<resources>/{id}
PATCH  /api/v1/<resources>/{id}
```

`DELETE` chỉ tồn tại khi canonical Resource Action `delete` có ý nghĩa.

## 5.2 State mutation

Resource có `setstate` dùng:

```text
POST /api/v1/<resources>/{id}/actions/set-state
```

Request:

```json
{
  "status": "ACTIVE"
}
```

Nếu state change thuộc Lifecycle thì phải đi qua LifecycleService.

## 5.3 Export

Khi Resource có `export`:

```text
GET /api/v1/<resources>/export
```

- dùng cùng `q/filter/sort` allowlist như list khi phù hợp;
- authorization `resource.export`;
- file response không bọc JSON envelope.

Exact export media type là `CONTRACT_PENDING` cho tới khi slice tương ứng khóa format.
Every `/export` route below is therefore a capability boundary: the
Resource/Action authorization is known, but the public file/media contract is
not implementation-ready until it is explicitly promoted to `CONTRACT_READY`.

---

# 6. Authorization contract per endpoint

Mỗi endpoint protected phải khai báo:

```text
resource_type
action
authoritative_evaluation_organization_source
visibility requirement
```

Rules:

- resolve canonical Resource Domain before authorization;
- `SYSTEM` / `MASTER` -> authoritative Evaluation Organization = `GROUP`;
- `ORGANIZATION` -> derive from canonical target/owner/parent/persisted context defined by the Resource contract;
- `ORGANIZATION` does not mean Organization type must be `COMPANY`; any canonical Organization Context may be returned;
- `COMPANY` is not a Resource Domain;
- client `evaluation_organization_id` does not override authoritative context;
- actor Primary Assignment is not a generic fallback for Resource authorization scope;
- missing/unknown domain or unresolved required `ORGANIZATION` context -> fail closed;
- invisible target => 404;
- visible but action denied => 403.

For lists:

- `SYSTEM`/`MASTER`: authorize Resource view at `GROUP`;
- `ORGANIZATION`: row authorization/data-scope filtering occurs before `total` and pagination; client Organization filters can narrow but never widen visibility.

---

# 6A. Pre-Gate-8 Display Settings API

Resource: `display_setting`  
Resource Domain: `ORGANIZATION`

Canonical actions:

```text
view
edit
```

Canonical context object:

```json
{ "type": "SYSTEM" }
```

or:

```json
{ "type": "ORGANIZATION", "organization_id": "123" }
```

No `COMPANY` or `USER` server context type is accepted.

## 6A.1 Runtime effective snapshot

```text
GET /api/v1/display-settings/runtime?organization_id=<optional BIGINT>
```

Authorization / visibility:
- caller must be authenticated; this runtime bootstrap endpoint does **not** require administrative `display_setting.view`;
- omitted `organization_id` -> return SYSTEM/default effective presentation snapshot;
- provided `organization_id` -> backend must validate target Organization visibility using canonical Organization data-scope/visibility rules before returning effective snapshot;
- response contains values only, never override row/source/audit metadata.

Response `data`:

```json
{
  "timezone": "Asia/Ho_Chi_Minh",
  "date": {
    "format": "DD/MM/YYYY",
    "date_time_format": "DD/MM/YYYY HH:mm",
    "time_format": "HH:mm",
    "first_day_of_week": "MONDAY"
  },
  "number": {
    "decimal_separator": ".",
    "group_separator": ",",
    "quantity_decimals": 3,
    "percentage_decimals": 3,
    "trailing_zeros": false
  },
  "money": {
    "decimal_separator": ".",
    "group_separator": ",",
    "symbol_mode": "SYMBOL",
    "symbol_position": "AUTO",
    "decimals_mode": "CURRENCY_DEFAULT"
  }
}
```

Runtime snapshot contains no locale and no business timezone field.

## 6A.2 Definitions

```text
GET /api/v1/display-settings/definitions
```

Action: `display_setting.view` at GROUP.

Returns registered keys and UI-safe metadata:

```text
key
category
data_type
default
allowed_values?
min?
max?
user_overridable
```

Does not expose arbitrary internal class/property mapping.

## 6A.3 Administrative values/effective source

```text
GET /api/v1/display-settings/values?context_type=SYSTEM
GET /api/v1/display-settings/values?context_type=ORGANIZATION&organization_id=123
```

Response per key:

```json
{
  "key": "Display.dateFormat",
  "override_value": null,
  "effective_value": "DD/MM/YYYY",
  "source": {
    "type": "SYSTEM",
    "organization_id": null
  },
  "inherited": true
}
```

Organization effective resolution includes ancestor Organization path. API must not ask client to supply parent/Company path.

## 6A.4 Batch set override

```text
PATCH /api/v1/display-settings
```

Action: `display_setting.edit` at context authority.

Request:

```json
{
  "context": {
    "type": "ORGANIZATION",
    "organization_id": "123"
  },
  "changes": [
    { "key": "Display.dateFormat", "value": "YYYY-MM-DD" },
    { "key": "Display.number.groupSeparator", "value": "." }
  ]
}
```

Rules:
- duplicate key in same request -> 422;
- unknown key -> 422 `DISPLAY_SETTING_NOT_FOUND`;
- invalid context -> 422 `DISPLAY_SETTING_CONTEXT_INVALID`;
- scope/value invalid -> 422 stable display-setting error;
- validate all changes before mutation;
- mutation atomic;
- success returns `200` success envelope containing the changed keys with `override_value`, `effective_value`, `source`, and `inherited`; P1 does not use `204` for this batch endpoint.

## 6A.5 Batch reset override

```text
POST /api/v1/display-settings/actions/reset
```

Request:

```json
{
  "context": {
    "type": "ORGANIZATION",
    "organization_id": "123"
  },
  "keys": [
    "Display.dateFormat",
    "Display.number.groupSeparator"
  ]
}
```

Reset removes only the requested override and reveals inherited value. Missing override is an idempotent no-op. Success returns `200` success envelope containing the requested keys with post-reset `override_value = null`, `effective_value`, `source`, and `inherited = true`.

## 6A.6 Security and presentation boundary

- client-provided Organization is the requested target, not proof of authorization;
- backend resolves authoritative Organization and uses AuthorizationService;
- runtime values are non-sensitive presentation values, but inaccessible Organization context must not be used to probe hidden Organization existence;
- no endpoint mutates local User preference/locale;
- no endpoint returns a value labeled Business Timezone;
- frontend must not send formatted money/quantity/date strings back as canonical business values because of this API.

---

# 7. Slice 1 - Foundation / Organization / Contact

Status: `PLANNED`

## 7.1 Province API

Resource: `province`

| Method | Route | Action | Contract |
|---|---|---|---|
| GET | `/provinces` | `province.view` | CONTRACT_READY |
| POST | `/provinces` | `province.create` | CONTRACT_READY |
| GET | `/provinces/{id}` | `province.view` | CONTRACT_READY |
| PATCH | `/provinces/{id}` | `province.edit` | CONTRACT_READY |
| POST | `/provinces/{id}/actions/set-state` | `province.setstate` | CONTRACT_READY |
| GET | `/provinces/export` | `province.export` | CONTRACT_PENDING |

Fields:

```text
code
name
status
```

Resource Domain: `MASTER`.
Evaluation Organization: `GROUP`.

## 7.2 Ward API

Resource: `ward`

```text
GET    /wards
POST   /wards
GET    /wards/{id}
PATCH  /wards/{id}
POST   /wards/{id}/actions/set-state
GET    /wards/export
```

Fields:

```text
province_id
code
name
name_full?
status
```

Filters:

```text
province_id
code
name
status
```

Resource Domain: `MASTER`.
Evaluation Organization: `GROUP`.

## 7.3 Organization API

Resource: `organization`
Resource Domain: `ORGANIZATION`.

```text
GET    /organizations
POST   /organizations
GET    /organizations/{id}
PATCH  /organizations/{id}
POST   /organizations/{id}/actions/set-state
GET    /organizations/export
GET    /organizations/tree
```

Create/update fields:

```text
parent_id
type
code
name
legal_name?
tax_code?
address?
province_id?
ward_id?
description?
status
active_from?
active_to?
```

Authorization:

```text
create          -> Evaluation Organization = parent organization
existing target -> Evaluation Organization = target organization
```

`GET /organizations/tree` only returns nodes in view scope.

## 7.4 Contact API

Resource: `contact`

```text
GET    /contacts
POST   /contacts
GET    /contacts/{id}
PATCH  /contacts/{id}
POST   /contacts/{id}/actions/set-state
GET    /contacts/export
```

Fields:

```text
contact_type = EMPLOYEE | ORGANIZATION
contact_ref_id
description?
status
name?
email?
phone?
mobile?
address?
province_id?
ward_id?
```

Resource Domain: `ORGANIZATION`. Owner is authoritative. Create/update/read authorization derives from owner.

```text
contact_type = ORGANIZATION -> Evaluation Organization = owner Organization
contact_type = EMPLOYEE     -> Employee domain MASTER -> Evaluation Organization = GROUP
```

Client `evaluation_organization_id` is not accepted as authority.

Owner filters:

```text
filter[contact_type]
filter[contact_ref_id]
filter[status]
q
```

---

# 8. Slice 2 - Employee Structure

Status: `PLANNED`

## 8.1 Department API

Resource: `department`

```text
GET    /departments
POST   /departments
GET    /departments/{id}
PATCH  /departments/{id}
POST   /departments/{id}/actions/set-state
GET    /departments/export
```

Fields:

```text
parent_id?
code
name
description?
status
```

Resource Domain: `MASTER`.
Evaluation Organization: `GROUP`.

## 8.2 Position API

Resource: `position`

```text
GET    /positions
POST   /positions
GET    /positions/{id}
PATCH  /positions/{id}
POST   /positions/{id}/actions/set-state
GET    /positions/export
```

Fields:

```text
code
name
level?
description?
status
```

Resource Domain: `MASTER`.
Evaluation Organization: `GROUP`.

## 8.3 Employee API

Resource: `employee`

```text
GET    /employees
POST   /employees
GET    /employees/{id}
PATCH  /employees/{id}
POST   /employees/{id}/actions/set-state
GET    /employees/export
```

Fields:

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

`gender` value set remains source-open; OpenAPI must not hard-code enum before `DEC-P1-001`.

Resource Domain: `MASTER`.
Evaluation Organization: `GROUP` for list/view/create/edit/set-state/export.

Minimum list filters:

```text
q
filter[status]
filter[organization_id]
filter[department_id]
filter[position_id]
```

Organization/Department/Position filters are data filters through effective Employee Assignment and do not redefine Employee authorization scope.

List/detail may include the smallest server-derived current Primary Assignment projection required by UI:

```text
current_primary_organization?
current_primary_department?
current_primary_position?
```

The projection uses existing effective `resolvePrimary()` semantics at Evaluation Time and is not persisted as Employee ownership.

## 8.4 Employee Assignment API

Resource: `employee_assignment`

```text
GET    /employee-assignments
POST   /employee-assignments
GET    /employee-assignments/{id}
PATCH  /employee-assignments/{id}
POST   /employee-assignments/{id}/actions/set-state
```

Fields:

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

Resource Domain: `ORGANIZATION`.

Authorization:

```text
create                     -> request assignment.organization_id
existing detail/edit/state -> persisted assignment.organization_id
list                       -> each row persisted assignment.organization_id
```

Client Organization filter can narrow list results but is not the authorization authority.

Filters minimum:

```text
employee_id
organization_id
department_id
position_id
is_primary
status
effective_at
```

---

# 9. Slice 3 - Identity / Authorization / Workflow

Status: `PLANNED`

## 9.1 User API

Resource: `user`
Resource Domain: `SYSTEM`; Evaluation Organization = `GROUP`.

Baseline locks Resource/Action behavior but does not define canonical Shield User create/edit field set.

```text
GET /users
GET /users/{id}
```

Read contract can become `CONTRACT_READY` after `P1-ENV-001` confirms Shield adapter mapping.

Write API remains:

```text
POST  /users                           CONTRACT_PENDING
PATCH /users/{id}                      CONTRACT_PENDING
POST  /users/{id}/actions/set-state    CONTRACT_PENDING
```

Direct permissions:

```text
GET /users/{id}/permissions
PUT /users/{id}/permissions
```

Authorization:

```text
GET -> user.view
PUT -> user.assign_permission
Evaluation Organization -> GROUP
```

PUT writes to identity mechanism, not Mini ERP local override table.

## 9.2 Authorization Group API

Resource: `authorization_group`
Resource Domain: `SYSTEM`.

```text
GET    /authorization-groups
POST   /authorization-groups
GET    /authorization-groups/{id}
PATCH  /authorization-groups/{id}
POST   /authorization-groups/{id}/actions/set-state

GET    /authorization-groups/{id}/members
PUT    /authorization-groups/{id}/members

GET    /authorization-groups/{id}/permissions
PUT    /authorization-groups/{id}/permissions
```

Actions:

```text
group CRUD/state -> authorization_group.view/create/edit/setstate
members PUT      -> authorization_group.assign_member
permissions PUT  -> authorization_group.assign_permission
```

Fields:

```text
code
name
description?
status
```

Evaluation Organization: `GROUP`.

## 9.3 Resource/Action catalog API

Resource: `resource`
Resource Domain: `SYSTEM`; Evaluation Organization = `GROUP`.

```text
GET    /resources
POST   /resources
GET    /resources/{id}
PATCH  /resources/{id}
DELETE /resources/{id}

GET    /resources/{id}/actions
POST   /resources/{id}/actions
PATCH  /resources/{id}/actions/{action_id}
DELETE /resources/{id}/actions/{action_id}
```

Resource fields:

```text
type
name
domain = SYSTEM | MASTER | ORGANIZATION
lifecycle_id?
```

`domain` is required on create and returned on read. For an existing canonical Resource it is read-only in P1; changing domain is an authorization-semantic change handled only by approved change control/migration. `COMPANY` is rejected as a Resource Domain.

Action fields:

```text
code
description?
```

No separate `resource_action` Resource Type is introduced.

## 9.4 Policy API

Resource: `policy`

```text
GET    /policies
POST   /policies
GET    /policies/{id}
PATCH  /policies/{id}
DELETE /policies/{id}
```

Policy create/update is atomic with action/effect rows.

Payload shape:

```json
{
  "organization_id": "123",
  "resource_id": "45",
  "state_id": null,
  "participant_type": "GROUP",
  "participant_ref_id": "67",
  "actions": [
    {
      "resource_action_id": "89",
      "effect": "ALLOW"
    }
  ]
}
```

Resource Domain: `ORGANIZATION`.
Evaluation Organization = `policy.organization_id`.

Participant values:

```text
USER
GROUP
POSITION
DEPARTMENT
ORGANIZATION
```

`ROLE` rejected for new data.

## 9.5 Authorization Log API

Resource: `authorization_log`

```text
GET /authorization-logs
GET /authorization-logs/{id}
GET /authorization-logs/export
```

Read-only to business clients.

Filters:

```text
user_id
resource_type/resource_id
action
decision
organization_id
request_id
created_from
created_to
```

Resource Domain: `ORGANIZATION`.

```text
row.organization_id != null -> Evaluation Organization = persisted organization_id
row.organization_id == null -> Evaluation Organization = GROUP
```

List authorization is row-scoped before total/pagination. Null-context log rows are visible only through GROUP evaluation. Retention duration remains an Operations/Security decision.

## 9.6 Workflow State API

Resource: `workflow_state`
Resource Domain: `SYSTEM`; Evaluation Organization = `GROUP`.

```text
GET    /workflow-states
POST   /workflow-states
GET    /workflow-states/{id}
PATCH  /workflow-states/{id}
POST   /workflow-states/{id}/actions/set-state
```

## 9.7 Lifecycle API

Resource: `lifecycle`
Resource Domain: `SYSTEM`; Evaluation Organization = `GROUP`.

```text
GET    /lifecycles
POST   /lifecycles
GET    /lifecycles/{id}
PATCH  /lifecycles/{id}
POST   /lifecycles/{id}/actions/set-state

GET /lifecycles/{id}/states
PUT /lifecycles/{id}/states

GET /lifecycles/{id}/transitions
PUT /lifecycles/{id}/transitions
```

Nested state/transition batch mutations use `lifecycle.edit` and are atomic.

---

# 10. Slice 4 - Auto Numbering

Status: `PLANNED`

Auto Numbering is configured under Resource administration.

Resource action: `resource.configure_numbering`.

## 10.1 Numbering series routes

```text
GET   /resources/{resource_id}/numbering-series
POST  /resources/{resource_id}/numbering-series
GET   /resources/{resource_id}/numbering-series/{series_id}
PATCH /resources/{resource_id}/numbering-series/{series_id}
POST  /resources/{resource_id}/numbering-series/{series_id}/actions/set-state
```

No hard-delete endpoint for issued series.

Create payload:

```json
{
  "series_code": "DEFAULT",
  "name": "Default Product Number",
  "scope_type": "GROUP",
  "pattern": "PRD-{SEQ:6}",
  "reset_policy": "NEVER",
  "generation_mode": "AUTO_REQUIRED",
  "start_number": "1",
  "increment_by": 1,
  "is_default": true,
  "status": "ACTIVE"
}
```

`start_number` is a JSON string because it maps to BIGINT.

After committed issuance, PATCH rejects changes to:

```text
resource_id
series_code
scope_type
reset_policy
```

## 10.2 Configuration validation endpoint

Non-consuming validation:

```text
POST /resources/{resource_id}/numbering-series/actions/validate-config
```

Action: `resource.configure_numbering`.

Response:

```json
{
  "data": {
    "valid": true,
    "normalized_pattern": "PRD-{SEQ:6}",
    "validation_messages": []
  },
  "meta": {
    "request_id": "uuid"
  }
}
```

Must not allocate/advance a real sequence.

---

# 11. Slice 5 - Product / UOM

Status: `PLANNED`

## 11.1 Product Group API

Resource: `product_group`

```text
GET    /product-groups
POST   /product-groups
GET    /product-groups/{id}
PATCH  /product-groups/{id}
POST   /product-groups/{id}/actions/set-state
GET    /product-groups/export
```

Canonical fields for create/update/read:

```text
id
parent_id: BIGINT string | null
code
name
description
status
```

`parent_id` is the only hierarchy identity submitted by clients. The server remains authoritative for cycle validation and effective-active ancestor checks when a new Product references a group. Existing Product Group routes remain unchanged.
Resource Domain: `MASTER`.
Evaluation Organization: `GROUP` (shared Product Group master).

## 11.2 Product UOM master API

Resource: `product_uom`
Resource Domain: `MASTER`.

```text
GET    /product-uoms
POST   /product-uoms
GET    /product-uoms/{id}
PATCH  /product-uoms/{id}
POST   /product-uoms/{id}/actions/set-state
GET    /product-uoms/export
```

Evaluation Organization: `GROUP`.

## 11.3 Product API

Resource: `product`
Resource Domain: `MASTER`.

```text
GET    /products
POST   /products
GET    /products/{id}
PATCH  /products/{id}
POST   /products/{id}/actions/set-state
GET    /products/export
```

Transport identifier:

```text
product_code -> products.code
max length 50
uniqueness namespace GLOBAL
```

Fields:

```text
product_code?
sku?
barcode?
parent_product_id?
product_name?
product_child_name?
product_group_id?
product_type = STANDARD | COMBO
base_uom_id?
price?
net_weight_g?
package_gross_weight_g?
package_length_mm?
package_width_mm?
package_height_mm?
description?
manual_standard_cost?
serial_management_enabled
status
```

Create behavior follows Auto Number generation mode.

Read response includes derived fields:

```text
role = SIMPLE | PARENT | CHILD | COMBO
full_name
selectable
leaf_transactionable
```

Derived fields are not separate persistence.
Evaluation Organization: `GROUP` for Product master operations; existing target
context remains server-derived from the persisted Product/owner boundary.

## 11.4 Product UOM conversions

No separate Resource Type; authorize as Product view/edit.

```text
GET /products/{id}/uom-conversions
PUT /products/{id}/uom-conversions
```

Item:

```text
uom_id
conversion_factor
price?
package_gross_weight_g?
package_length_mm?
package_width_mm?
package_height_mm?
usage_type
status
```

PUT is atomic for the submitted set and must preserve DBD history/delete constraints.

## 11.5 Parent/Child create

Child uses:

```text
POST /products
```

with `parent_product_id`.

Server performs SIMPLE->PARENT eligibility atomically.

---

# 12. Slice 6 - Pricing / COMBO

Status: `PLANNED`

## 12.1 Price List API

Resource: `price_list`
Resource Domain: `ORGANIZATION`.

```text
GET    /price-lists
POST   /price-lists
GET    /price-lists/{id}
PATCH  /price-lists/{id}
POST   /price-lists/{id}/actions/set-state
GET    /price-lists/export
```

Fields:

```text
code
name
organization_id
effective_from
effective_to?
priority
status
description?
```

Evaluation Organization:

```text
existing -> price_list.organization_id
create   -> request organization_id
```

## 12.2 Price List Items

Authorized through `price_list.view/edit`.

```text
GET /price-lists/{id}/items
PUT /price-lists/{id}/items
```

Item:

```text
product_id
uom_id
price
```

## 12.3 Price resolution

Proposed service endpoint:

```text
POST /price-resolutions
```

Contract status: `CONTRACT_PENDING`.

Reason: baseline khóa PricingService calculation nhưng chưa khóa public authorization action riêng cho một price-resolution API. Không tạo `price_resolution` Resource Type hoặc Action mới chỉ để phục vụ endpoint này.

Request shape đề xuất:

```json
{
  "product_id": "123",
  "uom_id": "10",
  "company_organization_id": "20",
  "evaluation_time": "2026-10-05T01:00:00Z"
}
```

Response:

```json
{
  "data": {
    "product_id": "123",
    "uom_id": "10",
    "company_organization_id": "20",
    "unit_price": "125000.00",
    "price_source": "PRICE_LIST_COMPANY",
    "price_list_id": "88"
  },
  "meta": {
    "request_id": "uuid"
  }
}
```

No new `price_resolution` Resource Type is introduced. Trước khi endpoint chuyển `CONTRACT_READY`, API design phải chốt cách public caller được authorize bằng Resource/Action đã baseline; nếu cần một dedicated action mới thì phải change-control upstream.

## 12.4 COMBO composition

No separate Resource Type; authorize by Product.

```text
GET /products/{id}/combo-items
PUT /products/{id}/combo-items
```

Item:

```text
component_product_id
quantity
```

PUT cycle-checks full graph atomically.

## 12.5 Optional preview APIs

```text
POST /products/{id}/actions/preview-combo-expansion
POST /products/{id}/actions/preview-combo-allocation
```

Status: `CONTRACT_PENDING`.

Reason: baseline defines calculation service but does not require public preview endpoints.

---

# 13. Slice 7 - Product Import

Status: `PLANNED`

## 13.1 Start Product Import

```text
POST /products/import-jobs
Content-Type: multipart/form-data
```

Fields:

```text
file
mode = CREATE_ONLY | UPDATE_ONLY | UPSERT
```

Authorization:

```text
Gate 1: product.import at GROUP
```

Product Resource Domain: `MASTER`; authoritative Evaluation Organization: `GROUP`.
Import Job Resource Domain: `ORGANIZATION`; start scope inherits the enabled target Resource scope, therefore P1 PRODUCT Import Job scope is also `GROUP`.

Response `201` returns Import Job summary.

## 13.2 Import Job list/detail

Resource: `import_job`
Resource Domain: `ORGANIZATION`. P1 persists `entity_type = PRODUCT`, so list/detail/rows/export authorize at the PRODUCT target scope = `GROUP`. Import Job rows inherit the Job scope.

```text
GET /import-jobs
GET /import-jobs/{id}
GET /import-jobs/{id}/rows
GET /import-jobs/{id}/export
```

Actions:

```text
list/detail/rows -> import_job.view
export           -> import_job.export
```

Job list filters (backed by `import_jobs` columns):

```text
mode
status
created_by
created_from
created_to
```

Row list filters (backed by `import_job_rows` columns/indexes):

```text
filter[row_status] = SUCCESS | ERROR | SKIPPED
q = entity_key search
```

Rows use the common `page`, `page_size`, and deterministic `sort` contract;
the default order is `row_number ASC`. No `record_type`, `source_key`, raw
payload, or unpersisted field is a row filter.

Canonical row DTO for `GET /import-jobs/{id}/rows`:

```text
row_number
row_status
entity_key
target_ref_id
error_code
error_message
created_at
```

Transport notes:

- `row_number` is the original source row number and remains stable after
  dependency planning/reordering.
- `target_ref_id` is nullable and serializes BIGINT as a JSON string.
- `created_at` is the persisted UTC timestamp.
- `record_type`, `source_key`, and raw source payload are not persisted in
  `import_job_rows` and are not returned by this DTO.

## 13.3 Product import identity

```text
Persistent Target Key           = product_code -> products.code
Source-local Key                = source_key
Generated Business Identifier   = product_code generated into products.code
```

`source_key` is an input-only dependency identity for the Product import
graph. It is not a business identifier and is not persisted in
`import_job_rows`. Likewise, the input/template `record_type` is not a row
result field or persisted row column.

Per-row mutation authorization:

```text
create -> product.create
edit   -> product.edit
state  -> product.setstate
```

Failed row rolls back row mutation and generated numbering allocation.

---

# 14. Common selector strategy

Do not create separate unauthenticated lookup endpoints.

Standard authorized collection endpoints are reused for selector search.

Example:

```text
GET /product-uoms?page_size=50&filter[status]=ACTIVE&q=...
```

If future performance requires `/options`, it must preserve the same authorization/data-scope semantics and be added to this document before implementation.

---

# 15. OpenAPI structure

Exact filesystem location is confirmed by `P1-ENV-001`.

Logical structure:

```text
openapi/
  openapi.yaml
  components/
    common/
    foundation/
    employee/
    authorization/
    workflow/
    numbering/
    product/
    pricing/
    import/
  paths/
    foundation/
    employee/
    authorization/
    workflow/
    numbering/
    product/
    pricing/
    import/
```

Rules:

- Redocly lint pass.
- Operation ID stable and unique.
- Generated frontend files are not hand-edited.
- Common success/error schemas reused.
- BIGINT/DECIMAL represented as string.

---

# 16. API contract test checklist

For each `CONTRACT_READY` endpoint:

```text
[ ] authentication
[ ] resource/action authorization
[ ] authoritative Evaluation Organization
[ ] invisible target -> 404
[ ] visible denied -> 403
[ ] malformed -> 400
[ ] semantic validation -> 422
[ ] conflict -> 409
[ ] BIGINT string
[ ] DECIMAL string
[ ] request_id
[ ] Accept-Language vi/en + unsupported fallback vi
[ ] Content-Language for localized response
[ ] stable machine error code across locales
[ ] authorization-safe total/count
[ ] runtime matches OpenAPI
```

Mutation additionally:

```text
[ ] transaction atomic
[ ] audit/evidence when required
[ ] no partial mutation on authorization/validation failure
```

---

# 17. Slice promotion criteria

A slice API becomes `CONTRACT_READY` when:

- fields trace to FRS/DBD;
- route/action/context source explicit;
- request/response/error semantics complete;
- no source-open field silently enumerated;
- authorization exposure agreed;
- OpenAPI can be written without correctness placeholders.

A slice becomes `VERIFIED` when runtime contract tests and generated-client smoke tests pass.

---

# 18. Known contract-pending items

| Item | Reason | Blocking |
|---|---|---|
| Shield User create/edit payload | upstream does not define canonical identity fields | User write API |
| `employees.gender` enum | source-open decision | strict OpenAPI enum |
| exact export media type per Resource | baseline grants export but not exact file/media format | final export media type |
| Price Resolution public API | internal calculation is implemented, but public authorization/action exposure is not baseline | public Price Resolution route |
| COMBO preview endpoints | service exists; public API is not baseline-required | optional UI preview |
| production import size/row limits | Operations config | deployment only |

No Dev task may resolve these by introducing unreviewed business behavior.
