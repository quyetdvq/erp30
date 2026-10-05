---
document_id: "API-MINI-ERP-P1-001"
title: "P1 API Design - Dinktech Mini ERP"
version: "1.1"
status: "draft"
owner: "Product / Architecture / Engineering"
date: "2026-10-05"
scope: "P1 API contract by rolling vertical slice"
authority:
  ur: "00_User_Requirements_v1.0.12.md"
  frs: "01_P1_Functional_Requirements_Specification_v1.7.md"
  dbd: "02_P1_Database_Design_v1.10.md"
  implementation_contract: "03_P1_Implementation_Contract_v1.5.md"
  implementation_plan: "04_P1_Implementation_Plan_v1.3.md"
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

## 1.1 Revision v1.1 - Language negotiation

- Supported locale `vi`, `en`; default/fallback `vi`.
- Frontend gửi `Accept-Language` theo current UI locale.
- API localized human-readable text trả `Content-Language`.
- Machine fields/error codes remain locale-independent.
- Media contract vẫn JSON; revision này không mở HTML/XML API representation.

---

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

## 2.2 Change rule

Sau khi endpoint đã `CONTRACT_READY`:

- breaking route/field/status semantics phải bump API Design version;
- nếu thay business semantics/schema thì upstream artifact tương ứng cũng phải change-control;
- OpenAPI phải thay trong cùng change;
- generated frontend artifact phải regenerate.

## 2.3 Rolling slice rule

```text
Service READY
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

- client `evaluation_organization_id` không override authoritative context;
- existing target context derive từ persisted target/owner;
- create derive từ canonical owner/parent/scope;
- group-level shared/admin Resource dùng `GROUP`;
- invisible target => 404;
- visible but action denied => 403.

---

# 7. Slice 1 - Foundation / Organization / Contact

Status: `PLANNED`

## 7.1 Province API

Resource: `province`

| Method | Route | Action | Contract |
|---|---|---|---|
| GET | `/provinces` | `province.view` | READY |
| POST | `/provinces` | `province.create` | READY |
| GET | `/provinces/{id}` | `province.view` | READY |
| PATCH | `/provinces/{id}` | `province.edit` | READY |
| POST | `/provinces/{id}/actions/set-state` | `province.setstate` | READY |
| GET | `/provinces/export` | `province.export` | export format pending |

Fields:

```text
code
name
status
```

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

Evaluation Organization: `GROUP`.

## 7.3 Organization API

Resource: `organization`

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

Owner is authoritative. Create/update authorization derives from owner.

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

Authorization:

```text
create/edit -> Evaluation Organization = assignment.organization_id
```

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

Baseline locks Resource/Action behavior but does not define canonical Shield User create/edit field set.

```text
GET /users
GET /users/{id}
```

Read contract can become READY after `P1-ENV-001` confirms Shield adapter mapping.

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
domain?
lifecycle_id?
```

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

## 9.6 Workflow State API

Resource: `workflow_state`

```text
GET    /workflow-states
POST   /workflow-states
GET    /workflow-states/{id}
PATCH  /workflow-states/{id}
POST   /workflow-states/{id}/actions/set-state
```

## 9.7 Lifecycle API

Resource: `lifecycle`

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

## 11.2 Product UOM master API

Resource: `product_uom`

```text
GET    /product-uoms
POST   /product-uoms
GET    /product-uoms/{id}
PATCH  /product-uoms/{id}
POST   /product-uoms/{id}/actions/set-state
GET    /product-uoms/export
```

## 11.3 Product API

Resource: `product`

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

Response `201` returns Import Job summary.

## 13.2 Import Job list/detail

Resource: `import_job`

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

Filters:

```text
entity_type
mode
status
created_by
created_from
created_to
```

## 13.3 Product import identity

```text
Persistent Target Key           = product_code -> products.code
Source-local Key                = source_key
Generated Business Identifier   = product_code generated into products.code
```

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
| COMBO preview endpoints | service exists; public API not baseline | optional UI preview |
| export file format per Resource | baseline grants export but not exact format | final export media type |
| production import size/row limits | Operations config | deployment only |

No Dev task may resolve these by introducing unreviewed business behavior.
