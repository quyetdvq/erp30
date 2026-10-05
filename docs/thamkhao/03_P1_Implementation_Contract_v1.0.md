---
document_id: "IMP-MINI-ERP-P1-001"
title: "P1 Implementation Contract - Dinktech Mini ERP"
version: "1.0"
status: "draft"
owner: "Product / Architecture / Engineering"
date: "2026-10-04"
scope: "P1 - Foundation, Master Data & Authorization"
---

# P1 Implementation Contract - Dinktech Mini ERP

## 1. Mục đích tài liệu

Tài liệu này khóa các **implementation convention** cần thiết để nhiều developer có thể triển khai P1 nhất quán từ cùng một bộ requirement/design.

Tài liệu này không thay thế:

1. `00_User_Requirements_v1.0.8-draft.md` - business intent và acceptance criteria.
2. `01_P1_Functional_Requirements_Specification_v1.2-draft.md` - functional behavior/service contract.
3. `02_P1_Database_Design_v1.5.md` - persistence/schema/technical database boundary.

Implementation Contract chỉ quyết định những điểm cần khóa ở tầng triển khai nhưng chưa được ba tài liệu trên quy định ở mức wire/runtime convention.

Khi có xung đột:

```text
Business intent / scope       -> User Requirements
Functional semantics          -> FRS
Persistence/schema invariant  -> Database Design
Wire/runtime convention       -> Implementation Contract
```

Không được dùng Implementation Contract để tạo capability/persistence trái với upstream authority.

---

## 2. Quyết định P1 đã được kế thừa nguyên trạng

Các invariant sau không được implementation tự thay đổi:

- Business scope có một Organization `GROUP` root và nhiều `COMPANY`.
- Organization type canonical: `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`.
- Department và Position là master độc lập; quan hệ Employee với Organization/Department/Position đi qua Employee Assignment.
- Primary Assignment chỉ resolve Default Actor Organization Context khi operation không cung cấp Evaluation Organization Context rõ ràng.
- Assignment effective tại `GROUP` hoặc ancestor phù hợp có thể tham gia Department/Position authorization lane của descendant.
- Assignment thuộc sibling/out-of-path Organization không tham gia lane hiện tại.
- System Admin không bypass: có User, Employee, GROUP Assignment, Base Permission và chịu PBAC/`ABSOLUTE_DENY`/Workflow như User khác.
- Mọi securable data/capability phải có Resource Type canonical; không có implicit admin access.
- P1 không có Resource Instance Authorization.
- Base Permission là prerequisite; không có Base Permission => DENY.
- QT1 trong từng authorization lane:

```text
ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH
NO_MATCH => ALLOW
```

- Inheritance resolve trước denied-set intersection.
- Applicable `ABSOLUTE_DENY` phải được propagate vào mọi lane con chịu scope trước intersection.
- Multi-role/multi-assignment merge dùng giao các tập quyền bị cấm.
- Workflow transition validity và Authorization là hai kiểm tra độc lập.
- Product Model V2, UOM, COMBO, Pricing và Import phải tuân FRS/DBD hiện hành.

---

# 3. Runtime conventions

## 3.1 Evaluation Time

Application dùng một `Clock`/time provider thống nhất thay vì gọi thời gian hệ thống rải rác trong domain service.

Canonical behavior:

- Persistence `DATETIME` được ghi theo UTC.
- API datetime sử dụng ISO-8601 UTC, ví dụ `2026-10-04T12:30:45Z`.
- Business `DATE` giữ dạng `YYYY-MM-DD` và không timezone-convert.
- Khi service nhận explicit `evaluation_time`, phải dùng chính giá trị đó cho toàn operation.
- Khi caller không truyền `evaluation_time`, application lấy một lần từ Clock tại đầu request/use-case rồi truyền xuống các service liên quan.
- Không gọi `now()` nhiều lần trong cùng một authorization/pricing/assignment resolution nếu có thể làm kết quả khác nhau ở boundary time.

## 3.2 Evaluation Organization Context

Mọi protected operation phải xác định được một `evaluation_organization_id` khi capability có Organization scope.

Evaluation Organization có thể là:

- `GROUP`
- `COMPANY`
- `BRANCH`
- `PLANT`
- `WAREHOUSE`

Resolution order:

```text
1. Context explicit của operation/resource nếu có.
2. Nếu operation cho phép default actor context:
   User -> Employee -> Primary Assignment tại Evaluation Time.
3. Nếu operation bắt buộc explicit context nhưng context thiếu:
   reject; không tự tạo ALLOW.
```

Group-level administrative Resource như `user`, `authorization_group`, `resource`, `policy` có thể dùng `evaluation_organization_id = GROUP`.

## 3.3 Company owner

Khi nghiệp vụ cần Company owner:

```text
Evaluation Organization
-> self COMPANY hoặc nearest COMPANY ancestor
```

`GROUP` không phải transaction owner.

## 3.4 Request / Correlation ID

Mỗi HTTP request phải có `request_id`.

- Client có thể gửi `X-Request-ID`.
- Nếu thiếu hoặc invalid, server sinh UUID mới.
- Server echo `X-Request-ID` trong response header.
- `request_id` được đưa vào error response metadata.
- Authorization audit dùng cùng request id khi schema hỗ trợ.
- Log application của cùng request phải dùng cùng request id.

---

# 4. API transport contract

## 4.1 API version và media type

P1 HTTP API dùng prefix:

```text
/api/v1
```

JSON là representation canonical cho API application.

```text
Content-Type: application/json
Accept: application/json
```

File import/export là endpoint/file response riêng và không ép vào JSON body.

## 4.2 Naming

- JSON field: `snake_case`.
- Resource route segment dùng plural `kebab-case`; một Resource chỉ có một route canonical.
- ID trong URL là decimal string.
- Enum/code trong API dùng canonical uppercase/lowercase đúng domain definition; không translate sang display label.

## 4.3 BIGINT serialization

Mọi database `BIGINT` id trả qua JSON phải serialize thành **string** để không mất precision ở JavaScript client.

Ví dụ:

```json
{
  "id": "9223372036854770001"
}
```

Request API chấp nhận id dạng JSON string canonical. Không dựa vào JSON floating-point number cho BIGINT.

## 4.4 Decimal / monetary serialization

Các giá trị `DECIMAL`, tiền, quantity, conversion factor, weight/dimension cần bảo toàn precision phải serialize dưới dạng **decimal string**.

Ví dụ:

```json
{
  "price": "125000.00",
  "quantity": "3.500000",
  "conversion_factor": "12.000000"
}
```

Không serialize monetary/quantity domain value bằng binary float.

## 4.5 Success envelope

Single object:

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

Mutation không cần body có thể trả `204 No Content`; nếu trả representation sau mutation thì dùng success envelope.

## 4.6 Error envelope

Canonical error response:

```json
{
  "error": {
    "code": "EMP_PRIMARY_ASSIGNMENT_OVERLAP",
    "message": "Primary Assignment overlaps another effective Primary Assignment.",
    "details": [
      {
        "field": "effective_from",
        "code": "OVERLAP",
        "message": "The effective period overlaps an existing assignment."
      }
    ]
  },
  "meta": {
    "request_id": "uuid"
  }
}
```

Rules:

- `error.code` là stable machine code.
- `message` là human-readable nhưng client không được parse để quyết định logic.
- `details` có thể rỗng; field-level validation dùng `field` khi phù hợp.
- Không trả stack trace, SQL, internal path hoặc secret trong production response.

## 4.7 HTTP status mapping

| HTTP | Semantics |
|---|---|
| `200` | Read/update/action thành công có response body |
| `201` | Create thành công |
| `204` | Mutation thành công không cần body |
| `400` | Malformed request, invalid JSON, unsupported query syntax |
| `401` | Chưa authentication / authentication invalid |
| `403` | Authenticated nhưng Authorization DENY |
| `404` | Target không tồn tại hoặc endpoint quyết định che record ngoài visible scope |
| `409` | State/concurrency/uniqueness/domain conflict với trạng thái hiện hữu |
| `422` | Request parse được nhưng vi phạm semantic validation |
| `500` | Unexpected server error |

Không map `Authorization DENY` thành `422`.

## 4.8 Canonical error code convention

Format:

```text
<DOMAIN>_<REASON>
```

Initial P1 codes tối thiểu:

```text
AUTH_NO_BASE_PERMISSION
AUTH_POLICY_DENIED
AUTH_ABSOLUTE_DENY
AUTH_CONTEXT_REQUIRED

ORG_SECOND_GROUP_ROOT
ORG_INVALID_PARENT
ORG_HIERARCHY_CYCLE
ORG_COMPANY_RESOLUTION_FAILED

EMP_PRIMARY_ASSIGNMENT_OVERLAP
EMP_MANAGER_SELF_REFERENCE
EMP_MANAGER_CYCLE
EMP_MANAGER_INACTIVE

POLICY_INVALID_PARTICIPANT
POLICY_RESOURCE_ACTION_MISMATCH

WF_INVALID_TRANSITION
WF_STATE_NOT_IN_LIFECYCLE

PRODUCT_INVALID_ROLE
PRODUCT_INVALID_PARENT
PRODUCT_COMBO_CYCLE
PRODUCT_SEMANTIC_FIELD_LOCKED

UOM_INVALID_CONVERSION
PRICING_NO_VALID_PRICE

IMPORT_INVALID_TEMPLATE
IMPORT_ROW_VALIDATION_FAILED
IMPORT_DEPENDENCY_CYCLE

DUPLICATE_KEY
INVALID_REFERENCE
INACTIVE_REFERENCE
VALIDATION_ERROR
CONCURRENT_MODIFICATION
```

Domain service có thể thêm code mới nhưng phải theo convention và không đổi nghĩa code đã phát hành.

---

# 5. List/search/filter/sort/pagination contract

## 5.1 Common query parameters

Canonical parameters:

```text
q=<free text search>
page=<1-based integer>
page_size=<integer>
sort=<field,-field,...>
filter[field]=<value>
```

Defaults:

```text
page = 1
page_size = 50
max page_size = 200
```

## 5.2 Sorting

- `field` = ascending.
- `-field` = descending.
- Mỗi endpoint phải có allowlist sortable fields.
- Unknown sort field => `400`.
- Query phải có deterministic tie-breaker, mặc định `id ASC` nếu business ordering chưa đủ deterministic.

## 5.3 Filtering

- Không cho client gửi arbitrary SQL field/operator.
- Mỗi endpoint định nghĩa allowlist filter.
- Multi-value filter dùng repeated array parameter `filter[field][]=value1&filter[field][]=value2`.
- Authorization/data-scope filtering xảy ra trước khi tính `total` trả cho client.

## 5.4 Search

`q` là free-text search trên allowlist field của Resource. Search không được làm lộ record ngoài authorization scope qua result/count/autocomplete.

---

# 6. Authorization implementation contract

## 6.1 Một pipeline duy nhất

Không có branch đặc biệt cho System Admin.

```text
Authorize(User, Resource, Action, EvaluationOrganization, State, Time)
```

Pipeline canonical:

```text
1. Resolve Base Permission(Resource, Action).
   Nếu không có -> DENY.

2. Resolve Evaluation Organization O.

3. Resolve ScopePath(O):
   GROUP root -> ... -> O.

4. Build actor lane:
   USER + Authorization GROUP
   + applicable ORGANIZATION contextual policy.

5. Resolve eligible effective Employee Assignments:
   assignment.organization = O
   OR assignment.organization là ancestor của O trên exact ScopePath.

6. Build một assignment lane cho từng eligible Assignment:
   DEPARTMENT + POSITION
   + applicable ORGANIZATION contextual policy.

7. Apply QT1 trong từng lane.

8. Propagate mọi applicable ABSOLUTE_DENY vào tất cả lane con chịu scope.

9. KQ[O] = INTERSECTION(KQ[user,O], all KQ[assignment,O]).

10. Resource+Action thuộc KQ[O] => DENY; ngược lại ALLOW.
```

KQ Company summary chỉ tính khi use-case thực sự yêu cầu Company-wide summary; runtime authorization của một operation cụ thể luôn dùng Evaluation Organization cụ thể.

## 6.2 Assignment path eligibility

Ví dụ:

```text
GROUP
└── COMPANY A
    ├── BRANCH A1
    │   └── WAREHOUSE A1-W1
    └── WAREHOUSE A2
```

Khi `O = WAREHOUSE A1-W1`, eligible Assignment có thể nằm tại:

- GROUP
- COMPANY A
- BRANCH A1
- WAREHOUSE A1-W1

Không eligible:

- WAREHOUSE A2
- Organization thuộc Company B

## 6.3 Authorization result object

Internal `AuthorizationDecision` tối thiểu:

```text
decision: ALLOW | DENY
reason_code
user_id
resource_type
resource_action
evaluation_organization_id
state_id?
evaluation_time
base_permission_source[]
matched_policy_ids[]
decisive_policy_ids[]
participating_assignment_ids[]
request_id?
```

Không yêu cầu persistence toàn bộ evidence này trong `authorization_logs`; đây là runtime/debug contract.

---

# 7. Resource / Action seed contract

## 7.1 Nguyên tắc

- Seeder phải idempotent theo `resources.type` và `resource_actions(resource_id, code)`.
- Resource Type là loại dữ liệu/capability, không phải từng row.
- `authorization_group` là canonical resource type của nhóm phân quyền; không dùng `group`.
- Action chỉ seed khi operation đó có ý nghĩa đối với Resource.
- Không seed action chỉ để "đủ bộ CRUD" nếu domain không cho operation tương ứng.

## 7.2 Additional P1 actions

Ngoài baseline action đã có, contract P1 phê duyệt thêm:

```text
import
assign
```

Semantics:

- `import`: chạy import mutation cho Resource tương ứng.
- `assign`: thay đổi relationship/membership/permission assignment khi Resource đó có nghiệp vụ gán.

## 7.3 Baseline seed matrix P1

| Resource Type | Actions P1 |
|---|---|
| `province` | `view`, `create`, `edit`, `setstate`, `import`, `export` |
| `ward` | `view`, `create`, `edit`, `setstate`, `import`, `export` |
| `contact` | `view`, `create`, `edit`, `setstate`, `export` |
| `organization` | `view`, `create`, `edit`, `setstate`, `export` |
| `department` | `view`, `create`, `edit`, `setstate`, `export` |
| `position` | `view`, `create`, `edit`, `setstate`, `export` |
| `employee` | `view`, `create`, `edit`, `setstate`, `import`, `export` |
| `employee_assignment` | `view`, `create`, `edit`, `setstate` |
| `user` | `view`, `create`, `edit`, `setstate`, `assign` |
| `authorization_group` | `view`, `create`, `edit`, `setstate`, `assign` |
| `resource` | `view`, `create`, `edit`, `delete` |
| `policy` | `view`, `create`, `edit`, `delete` |
| `authorization_log` | `view`, `export` |
| `workflow_state` | `view`, `create`, `edit`, `setstate` |
| `lifecycle` | `view`, `create`, `edit`, `setstate` |
| `product_group` | `view`, `create`, `edit`, `setstate`, `export` |
| `product_uom` | `view`, `create`, `edit`, `setstate`, `export` |
| `product` | `view`, `create`, `edit`, `setstate`, `import`, `export` |
| `price_list` | `view`, `create`, `edit`, `setstate`, `export` |
| `import_job` | `view`, `export` |

Notes:

- Seeded permission không tự cấp cho User; Group/direct permission vẫn phải cấu hình.
- `delete` permission không bypass FK/business validation và không đồng nghĩa hard-delete luôn hợp lệ.
- Start import yêu cầu `<target_resource>.import`; xem lịch sử import yêu cầu `import_job.view`.
- Quản trị membership/permission map của Authorization Group yêu cầu `authorization_group.assign`.
- Gán/bỏ Authorization Group của User qua UI/API yêu cầu `authorization_group.assign`; `user.assign` dùng cho các relationship thuộc User endpoint khi triển khai.

---

# 8. Transaction và concurrency contract

## 8.1 Nguyên tắc chung

- Multi-table mutation/invariant phải nằm trong DB transaction theo Database Design.
- Không chỉ dựa vào "check rồi insert/update" ngoài transaction cho invariant có race condition.
- Unique/FK constraint là lớp bảo vệ cuối cho invariant database-enforced.
- Service-enforced invariant có khả năng race phải dùng lock strategy bên dưới.
- P1 không bổ sung optimistic-version column chỉ để giải quyết concurrency; nếu cần versioning sau này phải qua additive design.

## 8.2 DomainLockService

Application phải có abstraction `DomainLockService` cho mutation hiếm nhưng có invariant graph/global.

Implementation MySQL/MariaDB có thể dùng advisory lock tương đương `GET_LOCK`; framework code không gọi primitive DB-specific rải rác ngoài adapter.

Lock phải:

- có timeout hữu hạn;
- release trong `finally`;
- dùng stable key;
- không giữ qua network/external I/O;
- acquire theo deterministic order khi cần nhiều lock để tránh deadlock.

## 8.3 Lock matrix

| Mutation | Lock / serialization P1 |
|---|---|
| Create GROUP root | domain lock `organization-tree` + transaction |
| Organization re-parent / hierarchy mutation | domain lock `organization-tree` + transaction |
| Primary Assignment create/update/end | lock key `employee-assignment:{employee_id}` + transaction; existing relevant rows có thể `FOR UPDATE` |
| Direct Manager change | domain lock `employee-manager-tree` + transaction trước cycle check |
| Policy + Policy Actions mutation | transaction; update existing Policy row `FOR UPDATE` |
| Authorization Group permission batch | lock Group row `FOR UPDATE` + transaction |
| Lifecycle state/transition batch | lock Lifecycle row `FOR UPDATE` + transaction |
| Product Parent/Child structural mutation | lock key `product-structure:{root_product_id}`; khi root chưa biết dùng broader `product-structure` |
| COMBO composition mutation | domain lock `combo-graph` + transaction trước cycle validation |
| Price List + items batch | lock Price List row `FOR UPDATE` + transaction |
| Import target row update | transaction + target row `FOR UPDATE` khi target tồn tại; create dựa thêm vào unique constraint |
| Import Job summary reconcile | lock `import_jobs` row `FOR UPDATE` trước summary update |

## 8.4 Deadlock / lock timeout

- Deadlock hoặc lock timeout không được silently retry vô hạn.
- Infrastructure được phép retry tối đa một số hữu hạn được config cho transaction idempotent.
- Nếu không thể retry an toàn, trả `409 CONCURRENT_MODIFICATION`.
- Không retry mutation đã phát external side effect ngoài transaction.

---

# 9. Audit contract

## 9.1 Actor fields

Với mutable master/config có `created_by`/`updated_by`:

- business request authenticated: dùng Shield User id hiện tại;
- seed/migration/system bootstrap: có thể `NULL` theo Database Design;
- không giả mạo một User business cho migration.

## 9.2 created_at / updated_at

- ghi bằng application/DB UTC convention thống nhất;
- create: `created_at` bắt buộc, `updated_at` có thể NULL theo schema;
- update thành công: cập nhật `updated_by` + `updated_at`;
- rollback: không để audit mutation partial.

## 9.3 Authorization audit

Persist tối thiểu cho:

- approve;
- delete;
- state change.

`request_id` phải được gắn khi request có correlation id.

---

# 10. Import transport và parser contract

## 10.1 Supported formats

P1:

- CSV
- XLSX

CSV convention:

- UTF-8; UTF-8 BOM được chấp nhận và bỏ qua.
- Header row bắt buộc.
- Delimiter canonical: comma `,`.
- Quote canonical: double quote `"`.

XLSX convention:

- Đọc worksheet đầu tiên có dữ liệu.
- Dòng đầu là header.
- Formula cell phải được đọc theo calculated value hoặc reject nếu parser không bảo đảm deterministic value.

Header được normalize bằng trim + lowercase; canonical header sau normalize là `snake_case`.

Unknown column:

- warning ở validation preview nếu implementation có preview;
- importer không dùng unknown column cho business mutation.

## 10.2 Import job mode

Mỗi Import Job có một mode runtime, không cần persistence mới trong P1 schema:

```text
CREATE_ONLY
UPDATE_ONLY
UPSERT
```

Semantics:

- `CREATE_ONLY`: key đã tồn tại => row error.
- `UPDATE_ONLY`: key chưa tồn tại => row error.
- `UPSERT`: tồn tại => update; chưa tồn tại => create.

Default UI/API mode: `CREATE_ONLY` để tránh update ngoài ý muốn.

Mode phải được ghi vào application log/request context; nếu sau này business cần audit mode persisted phải có additive design.

## 10.3 Empty/null semantics

- CREATE: blank nullable field => NULL.
- UPDATE/UPSERT-existing: blank => không thay đổi field.
- Token canonical `__NULL__` dùng để clear field nullable một cách explicit.
- Required/non-null field nhận `__NULL__` => row validation error.

## 10.4 Canonical entity keys

| Entity | Import key |
|---|---|
| PRODUCT | `product_code` |
| EMPLOYEE | `employee_code` |
| PROVINCE | `code` |
| WARD | `code` |

Key column không được dùng để rename target trong cùng import. Rename business key nếu sau này cần phải là operation riêng.

## 10.5 PRODUCT template v1

Một PRODUCT job dùng một flat stream với `record_type` để CSV và XLSX dùng cùng semantics.

Allowed `record_type`:

```text
PRODUCT
UOM_CONVERSION
COMBO_ITEM
```

### PRODUCT row

Canonical columns:

```text
record_type
product_code
sku
barcode
parent_product_code
product_name
product_child_name
product_group_code
product_type
base_uom_code
price
net_weight_g
package_gross_weight_g
package_length_mm
package_width_mm
package_height_mm
description
manual_standard_cost
serial_management_enabled
status
```

Validation tuân Product Role canonical; field required phụ thuộc derived role.

### UOM_CONVERSION row

```text
record_type
product_code
uom_code
conversion_factor
price
package_gross_weight_g
package_length_mm
package_width_mm
package_height_mm
usage_type
status
```

### COMBO_ITEM row

```text
record_type
combo_product_code
component_product_code
quantity
```

Product importer phải build dependency graph toàn file trước mutation cần dependency; Child có thể trước Parent, COMBO item có thể trước Product/component, nested COMBO phải detect cycle.

## 10.6 EMPLOYEE template v1

Một EMPLOYEE job dùng:

```text
record_type = EMPLOYEE | ASSIGNMENT
```

### EMPLOYEE row

```text
record_type
employee_code
employee_name
gender
date_of_birth
place_of_origin
user_id
hire_date
manager_employee_code
description
status
```

`gender` không có hard-coded enum trong contract này; value phải qua application catalog đã được Product Owner phê duyệt.

### ASSIGNMENT row

```text
record_type
employee_code
organization_code
department_code
position_code
is_primary
effective_from
effective_to
description
status
```

Employee/Assignment dependency được resolve theo `employee_code` và master code references.

## 10.7 PROVINCE template v1

```text
code
name
status
```

## 10.8 WARD template v1

```text
code
province_code
name
name_full
status
```

`province_code` phải resolve được Province hợp lệ.

## 10.9 Duplicate rows trong cùng file

- PRODUCT row: `product_code` chỉ có tối đa một `record_type=PRODUCT` row.
- UOM conversion: unique theo `product_code + uom_code`.
- COMBO item: unique theo `combo_product_code + component_product_code`.
- EMPLOYEE row: `employee_code` chỉ có tối đa một `record_type=EMPLOYEE` row.
- Assignment: duplicate logical assignment row trong cùng file bị reject thay vì silently overwrite.
- Province/Ward: duplicate code trong cùng file bị reject.

## 10.10 Row transaction

Mỗi logical source row có row result và mutation atomic theo FRS/DBD.

Dependency preprocessing có thể đọc toàn file trước; việc reorder processing không được thay `row_number` gốc trong `import_job_rows`.

## 10.11 File hash

`source_file_hash` dùng SHA-256 khi application tính hash.

P1 dùng hash cho integrity/duplicate warning; **không tự động coi cùng hash là idempotent success** và không tự bỏ qua rerun, vì rerun có thể có mode/context khác.

---

# 11. Import authorization

Start import:

```text
PRODUCT  -> product.import
EMPLOYEE -> employee.import
PROVINCE -> province.import
WARD     -> ward.import
```

Ngoài permission trên, PBAC vẫn evaluate theo Evaluation Organization phù hợp.

- Group-master import có thể dùng `Evaluation Organization = GROUP` khi functional scope là Group-level.
- Employee import có Assignment ở nhiều Organization phải authorization theo operation design và không được dùng import để bypass Organization scope.
- Xem danh sách/job detail yêu cầu `import_job.view`.
- Export report import yêu cầu `import_job.export`.

---

# 12. Lifecycle/action execution contract

Với state-changing action:

```text
1. Resolve current lifecycle/state.
2. Resolve legal transition by lifecycle + from_state + action.
3. Nếu transition không hợp lệ -> WF_INVALID_TRANSITION.
4. Evaluate Authorization cho Resource + Action + current state + context.
5. Nếu DENY -> 403.
6. Mutation state trong transaction.
7. Ghi audit theo requirement.
```

Không cho Authorization ALLOW biến một transition không tồn tại thành hợp lệ.

---

# 13. Pricing/decimal implementation contract

- Không dùng PHP/JavaScript floating-point cho authoritative money/quantity calculation.
- Dùng decimal library hoặc database decimal semantics phù hợp.
- Pricing `evaluation_time` là cùng Clock value của operation.
- Candidate order phải thực thi đúng:

```text
priority DESC
-> effective_from DESC
-> price_list_id ASC
```

- Effective interval:

```text
effective_from <= evaluation_time < effective_to
```

- COMBO allocation chỉ round ở monetary result cuối theo FRS/DBD; residual xử lý deterministic theo canonical rule hiện hành.

---

# 14. Repository/service boundary

## 14.1 Repository

Repository chịu trách nhiệm:

- persistence query;
- row locking primitive;
- database-enforced error translation;
- không chứa PBAC/business precedence riêng.

## 14.2 Domain/Application service

Service chịu trách nhiệm invariant service-enforced trong Database Design, gồm:

- Organization tree/cycle.
- Employee assignment overlap/context resolution.
- Manager cycle.
- Authorization/PBAC.
- Lifecycle validity.
- Product Role/UOM/COMBO/Pricing.
- Import dependency/validation.

Controller không được tự re-implement domain invariant.

## 14.3 Authorization placement

Protected use-case phải authorize tại application/use-case boundary trước mutation/export. Repository không phải nơi quyết định User được phép làm gì.

---

# 15. Validation error ordering

Khi một request có nhiều lỗi, service nên trả tất cả validation lỗi độc lập có thể xác định an toàn trong một pass, nhưng không bắt buộc tiếp tục các bước phụ thuộc sau một lỗi prerequisite.

Recommended order:

```text
1. Request shape / required fields.
2. Reference existence / active status.
3. Authorization context prerequisites.
4. Domain invariant.
5. Cross-record/graph validation.
6. Persistence conflict.
```

Không được dùng thứ tự error để làm thay đổi business outcome.

---

# 16. Logging và sensitive data

- Không log password/token/session secret.
- Không log raw import row chứa dữ liệu nhạy cảm nếu không cần thiết.
- Import DB cũng không bắt buộc persist raw row payload theo Database Design.
- Authorization decision log không được biến thành nơi snapshot toàn bộ Employee/Profile.
- Application log được phép giữ ids/codes/reason code/request id để debug trong phạm vi security policy.

---

# 17. Test contract

## 17.1 Test levels

P1 phải có:

- unit test cho pure domain calculation;
- integration test cho repository + DB constraints/transaction/locking;
- service test cho business invariant;
- API contract test cho envelope/status/serialization.

## 17.2 Mandatory Authorization tests

Tối thiểu:

```text
No Base Permission -> DENY
Base + NO_MATCH -> ALLOW
DENY -> denied lane
ALLOW + DENY -> ALLOW lane
ABSOLUTE_DENY -> denied lane

GROUP Policy inheritance
GROUP Assignment inheritance
Company partition
Sibling isolation
Secondary Assignment eligibility
ALLOW + DENY between roles -> effective ALLOW
DENY + DENY between roles -> effective DENY
Inherited ABSOLUTE_DENY survives intersection
System Admin has no bypass
Group-level user/authorization_group/resource/policy authorization
```

## 17.3 Mandatory API contract tests

- BIGINT id is JSON string.
- Decimal/money is JSON string.
- Error envelope stable.
- 401 vs 403 distinct.
- Validation 422 vs conflict 409 distinct.
- Pagination total respects authorization scope.
- Request id echoed and propagated.

## 17.4 Mandatory concurrency tests

- Concurrent GROUP root create cannot produce two roots.
- Concurrent overlapping Primary Assignment create/update cannot both commit.
- Concurrent Organization re-parent cannot create cycle.
- Concurrent manager mutation cannot create manager cycle.
- Concurrent COMBO composition changes cannot commit a cycle.
- Concurrent batch Group Permission update does not leave partial matrix.

## 17.5 Mandatory import tests

- CSV and XLSX header normalization.
- CREATE_ONLY/UPDATE_ONLY/UPSERT behavior.
- Blank vs `__NULL__` behavior.
- Product Child before Parent.
- COMBO item before component.
- Nested COMBO cycle.
- Employee Assignment references.
- Row rollback and original row number.
- Same source hash does not auto-skip rerun.

---

# 18. Migration / bootstrap contract

- Migration là forward-only; không rewrite migration đã applied.
- Dependency order tuân Database Design v1.5.
- Resource/Action seeder chạy idempotent.
- Workflow state seeder chạy idempotent theo code.
- Authorization Group seeder phải đồng bộ Shield group alias.
- Deployment phải có đúng một Organization `GROUP` root trước Company bootstrap.
- Seeder không tự cấp full permission cho mọi User.

---

# 19. Configuration keys tối thiểu

Implementation nên gom runtime config thay vì hard-code rải rác:

```text
api.default_page_size = 50
api.max_page_size = 200

lock.timeout_seconds
lock.retry_count

import.max_file_bytes
import.max_rows
import.allowed_formats = CSV,XLSX

logging.authorization_decision_level
```

Giá trị production của `import.max_file_bytes`, `import.max_rows`, retention và lock timeout thuộc deployment/Operations configuration; không được hard-code business invariant phụ thuộc các giá trị này.

---

# 20. Open items không được contract này tự suy diễn

Các điểm sau vẫn mở theo upstream sources hoặc cần authority khác:

1. Canonical value set của `employees.gender`.
2. Direct Manager effective-dated history ngoài current pointer P1.
3. CHILD display separator chính xác ở UI.
4. Retention period của `authorization_logs` và `import_jobs`.
5. P2 persistence để trace COMBO source tới transaction leaf.
6. P2 rule nếu muốn aggregate direct standalone leaf với COMBO-generated leaf.

Các item này không được dev tự tạo schema/capability mới trong P1.

---

# 21. Implementation order khuyến nghị

Dependency implementation:

```text
1. Migration / schema
2. Resource + Action + Workflow bootstrap seed
3. OrganizationService
4. EmployeeAssignmentService
5. AuthorizationService
6. LifecycleService
7. ProductRoleService
8. ProductUomService
9. PricingService
10. ComboExpansionService
11. ComboAllocationService
12. ImportService
13. HTTP controllers/API adapters
14. UI
```

Authorization tests nên được viết song song trước/đồng thời với `AuthorizationService`.

---

# 22. Definition of Done cho Implementation Contract P1

P1 implementation được xem là tuân Contract này khi tối thiểu:

- API dùng envelope/status/serialization canonical.
- BIGINT/DECIMAL không mất precision qua JSON.
- Request correlation hoạt động xuyên API -> authorization audit/log.
- Evaluation Time và Evaluation Organization được resolve deterministic.
- System Admin không có code-path bypass.
- GROUP Assignment và full ancestor path hoạt động đúng.
- Resource/Action seed matrix idempotent và security/configuration data cũng được bảo vệ.
- Transaction boundaries của Database Design được triển khai.
- Race-sensitive invariant dùng lock/serialization phù hợp và có concurrency test.
- Import parser dùng template/mode/null semantics trong tài liệu này.
- Import không bypass business validation/authorization.
- Controller không re-implement domain invariant.
- Automated test bao phủ FRS Acceptance Test Catalog + các contract test ở tài liệu này.
- Không có persistence mới trái Database Design v1.5.

---

# 23. Change control

Thay đổi một trong các contract sau phải bump version tài liệu và cập nhật test:

- HTTP envelope/status mapping.
- ID/decimal serialization.
- Evaluation Organization/Time semantics.
- Authorization pipeline hoặc denied-set merge.
- Resource Type hoặc canonical Action code.
- Lock strategy ảnh hưởng invariant.
- Import template/key/mode/null semantics.
- Error code đã public cho client.

Thay đổi schema phải đi qua Database Design/change request tương ứng; không chỉ sửa Implementation Contract.
