---
document_id: "IMP-MINI-ERP-P1-001"
title: "P1 Implementation Contract - Dinktech Mini ERP"
version: "1.3"
status: "baseline"
owner: "Product / Architecture / Engineering"
date: "2026-10-05"
scope: "P1 - Foundation, Master Data & Authorization"
---

# P1 Implementation Contract - Dinktech Mini ERP

## 1. Mục đích tài liệu

Tài liệu này khóa các **implementation convention** cần thiết để nhiều developer có thể triển khai P1 nhất quán từ cùng một bộ requirement/design.

Tài liệu này không thay thế:

1. `00_User_Requirements_v1.0.10-draft.md` - business intent và acceptance criteria.
2. `01_P1_Functional_Requirements_Specification_v1.5-draft.md` - functional behavior/service contract.
3. `02_P1_Database_Design_v1.8.md` - persistence/schema/technical database boundary.

Implementation Contract chỉ quyết định những điểm cần khóa ở tầng triển khai nhưng chưa được ba tài liệu trên quy định ở mức wire/runtime convention.

Khi có xung đột:

```text
Business intent / scope       -> User Requirements
Functional semantics          -> FRS
Persistence/schema invariant  -> Database Design
Wire/runtime convention       -> Implementation Contract
```

Không được dùng Implementation Contract để tạo capability/persistence trái với upstream authority.

### 1.1 Revision v1.3 decisions

Revision này kế thừa v1.2 và khóa thêm các clarification sau review Auto Numbering:

- Security bootstrap không dùng runtime bypass; initial privileged membership được provision qua bootstrap/deployment mechanism.
- Authoritative Evaluation Organization derive từ persisted target hoặc canonical owner/parent/scope; client context không phải authority.
- `organization.create` authorize tại Parent Organization; Employee Assignment mutation tại Assignment Organization.
- Group-level shared/admin Resource canonical evaluate tại `GROUP`.
- Import authorization hai tầng: `<resource>.import` + canonical row mutation permission.
- P1 Import Framework không áp dụng cho mọi master; enabled target baseline hiện tại chỉ `PRODUCT`.
- Import Job persistence `mode = CREATE_ONLY | UPDATE_ONLY | UPSERT`.
- Unknown import header và XLSX formula cell bị reject trong P1.
- Security actions tách `authorization_group.assign_member`, `authorization_group.assign_permission`, `user.assign_permission`.
- Business timezone cấu hình theo Company bằng CodeIgniter Shield/Settings integration mechanism; Evaluation Time vẫn là instant thống nhất.
- Invisible target trả 404; visible target thiếu action permission trả 403.
- Advisory lock key dùng application/environment/database namespace; retry transaction tối đa một lần khi retry-safe.
- Auto Numbering dùng `auto_numbering_series` + `auto_numbering_counters` theo Resource; hỗ trợ multi-series, scope `GROUP`/`COMPANY`/`ORGANIZATION`, `AUTO_REQUIRED`/`AUTO_IF_EMPTY`, reset `NEVER`/`YEARLY`/`MONTHLY`/`DAILY`, và `resource.configure_numbering`.
- Number allocation và business create nằm cùng transaction; rollback có thể reuse number chưa commit, committed number không reuse. Product Import dùng source-local key để dependency không phụ thuộc generated Product Code.
- Auto Numbering bổ sung business identifier uniqueness namespace validation, skip candidate đã bị manual/existing identifier chiếm, immutable series identity/scope/reset sau committed issuance, no-hard-delete issued series, và terminology rõ giữa Product Import persistent target key/source-local key/generated business identifier.
- Heading numbering của các chapter sau Auto Numbering được chuẩn hóa lại để reference tài liệu không lệch section.

---

## 2. Quyết định P1 đã được kế thừa nguyên trạng

Các invariant sau không được implementation tự thay đổi:

- Business scope có một Organization `GROUP` root và nhiều `COMPANY`.
- Organization type canonical: `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`.
- Department và Position là master độc lập; quan hệ Employee với Organization/Department/Position đi qua Employee Assignment.
- Primary Assignment chỉ resolve Default Actor Organization Context khi operation không cung cấp Evaluation Organization Context rõ ràng.
- Assignment effective tại `GROUP` hoặc ancestor phù hợp có thể tham gia Department/Position authorization lane của descendant.
- Assignment thuộc sibling/out-of-path Organization không tham gia lane hiện tại.
- System Admin không bypass: có User, Employee, GROUP Assignment, Base Permission và chịu PBAC/`ABSOLUTE_DENY`/Workflow như User khác. Initial privileged membership chỉ được provision qua bootstrap/deployment mechanism.
- Existing target derive authoritative Evaluation Organization từ persisted target; create derive từ owner/parent/scope canonical. Client context không override authority.
- Mọi securable data/capability phải có Resource Type canonical; không có implicit admin access.
- Resource có thể opt-in Auto Numbering qua series configuration; generated number là business identifier, không thay `BIGINT id`.
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
- Với Company-scoped business rule dùng `DATE`, derive `evaluation_date` từ cùng `evaluation_time` theo timezone cấu hình của Company qua CodeIgniter Shield/Settings integration mechanism. Không dùng browser timezone làm authority.

## 3.2 Evaluation Organization Context

Mọi protected operation phải xác định được một `evaluation_organization_id` khi capability có Organization scope.

Evaluation Organization có thể là:

- `GROUP`
- `COMPANY`
- `BRANCH`
- `PLANT`
- `WAREHOUSE`

Authoritative resolution:

```text
1. Existing Resource/record:
   derive từ persisted target/owner canonical.

2. Create operation:
   derive từ owner/parent/scope canonical trong request.

3. Chỉ khi operation thật sự cho phép default actor context
   và không có target scope authoritative:
   User -> Employee -> Primary Assignment tại Evaluation Time.
```

Rules:

- Client-provided `evaluation_organization_id` chỉ là selector/hint; nếu khác authoritative context => reject.
- Group-level shared/admin Resource như `product`, `product_group`, `product_uom`, `province`, `ward`, `user`, `authorization_group`, `resource`, `policy` dùng canonical `GROUP` khi functional scope là Group-level.
- `organization.create`: Evaluation Organization = Parent Organization.
- `employee_assignment.create/edit`: Evaluation Organization = Assignment Organization.
- Existing `organization`: Evaluation Organization = target Organization.
- Existing `policy`: Evaluation Organization = `policy.organization_id`.
- Existing `price_list`: Evaluation Organization = `price_list.organization_id`.
- Nếu operation bắt buộc context nhưng không resolve được authoritative context: reject; không tự tạo ALLOW.

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
| `404` | Target không tồn tại **hoặc tồn tại nhưng actor không có visibility/view scope để được phép biết target tồn tại** |
| `409` | State/concurrency/uniqueness/domain conflict với trạng thái hiện hữu |
| `422` | Request parse được nhưng vi phạm semantic validation |
| `500` | Unexpected server error |

Không map `Authorization DENY` thành `422`. Với existing target: không visible => `404`; target visible nhưng thiếu action permission => `403`.

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

NUMBERING_CONFIG_INVALID
NUMBERING_SERIES_NOT_FOUND
NUMBERING_SCOPE_INVALID
NUMBERING_PATTERN_INVALID
NUMBERING_MANUAL_VALUE_NOT_ALLOWED

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

Ngoài baseline action đã có, contract P1 phê duyệt thêm khi capability tương ứng tồn tại:

```text
import
assign_member
assign_permission
configure_numbering
```

Semantics:

- `import`: chạy importer cho Resource được explicit enable.
- `assign_member`: thay đổi membership của Authorization Group.
- `assign_permission`: thay đổi permission assignment của Authorization Group hoặc direct User Permission, tùy Resource.
- `configure_numbering`: quản trị Auto Numbering series/configuration của Resource catalog.

## 7.3 Baseline seed matrix P1

| Resource Type | Actions P1 |
|---|---|
| `province` | `view`, `create`, `edit`, `setstate`, `export` |
| `ward` | `view`, `create`, `edit`, `setstate`, `export` |
| `contact` | `view`, `create`, `edit`, `setstate`, `export` |
| `organization` | `view`, `create`, `edit`, `setstate`, `export` |
| `department` | `view`, `create`, `edit`, `setstate`, `export` |
| `position` | `view`, `create`, `edit`, `setstate`, `export` |
| `employee` | `view`, `create`, `edit`, `setstate`, `export` |
| `employee_assignment` | `view`, `create`, `edit`, `setstate` |
| `user` | `view`, `create`, `edit`, `setstate`, `assign_permission` |
| `authorization_group` | `view`, `create`, `edit`, `setstate`, `assign_member`, `assign_permission` |
| `resource` | `view`, `create`, `edit`, `delete`, `configure_numbering` |
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
- P1 baseline chỉ seed `product.import`; Resource khác không có `import` nếu chưa được explicit enable qua contract/change control. Xem lịch sử import yêu cầu `import_job.view`.
- Quản trị membership của Authorization Group yêu cầu `authorization_group.assign_member`.
- Thay đổi Group Permission matrix yêu cầu `authorization_group.assign_permission`.
- Direct User Permission mutation yêu cầu `user.assign_permission` và ghi vào identity mechanism hiện hành; không tạo local override persistence.
- Auto Numbering configuration yêu cầu `resource.configure_numbering`; `resource.edit` không tự suy ra action này.

---


# 8. Auto Numbering implementation contract

## 8.1 Series selection

- Numbering configuration persistence dùng `auto_numbering_series` và `auto_numbering_counters`; không ghi counter vào `resources`.
- Một Resource có thể có nhiều series nhưng tối đa một `ACTIVE` default series.
- Khi use-case không chỉ định series canonical, AutoNumberService dùng active default series. Explicit series selection phải được server/use-case validate; raw client value không được tự bypass Resource/scope rule.
- Resource không có active/default series tiếp tục dùng manual/domain identifier rule hiện hành; không tự phát sinh numbering.

## 8.2 Scope resolution

`scope_type` canonical:

```text
GROUP
COMPANY
ORGANIZATION
```

Resolution:

```text
GROUP        -> GROUP root
COMPANY      -> self/nearest COMPANY ancestor của authoritative Organization Context
ORGANIZATION -> exact authoritative Evaluation Organization Context
```

`scope_organization_id` trong counter luôn là Organization row canonical đã resolve ở trên.

## 8.3 Business identifier uniqueness namespace

Mỗi Resource adapter/domain dùng Auto Numbering phải khai báo metadata runtime tối thiểu:

```text
identifier_field
identifier_max_length
identifier_uniqueness_scope = GLOBAL | COMPANY | ORGANIZATION
```

Metadata này không persistence vào `resources`/numbering tables trong P1; nó mô tả unique key thực tế của business identifier để validator biết counter partition nào phải được encode vào final identifier.

Canonical validation:

- Nếu counter scope bị partition **hẹp hơn** uniqueness namespace, pattern phải encode discriminator của partition hoặc domain unique key phải tự chứa dimension đó.
- `GLOBAL` identifier + `COMPANY` counter => pattern phải phân biệt Company, baseline dùng `{COMPANY_CODE}`.
- `GLOBAL` identifier + `ORGANIZATION` counter => pattern phải phân biệt exact Organization, baseline dùng `{ORG_CODE}`.
- `COMPANY` identifier + `ORGANIZATION` counter => pattern phải phân biệt Organization trong Company, baseline dùng `{ORG_CODE}`.
- Với `YEARLY`, pattern phải có `{YYYY}` hoặc `{YY}`; với `MONTHLY`, phải có year + `{MM}`; với `DAILY`, phải có year + `{MM}` + `{DD}`. Reset period không được tạo lại cùng identifier trong cùng domain uniqueness key.
- Multiple active/selectable series của cùng Resource phải tiếp tục thỏa business unique constraint; validator phải reject deterministic namespace collision có thể nhận biết từ pattern/scope/reset. DB unique constraint của business identifier vẫn là final guard.

## 8.4 Pattern contract

Canonical token P1:

```text
{SEQ:n}
{YYYY}
{YY}
{MM}
{DD}
{COMPANY_CODE}
{ORG_CODE}
{RESOURCE}
```

Rules:

- Pattern phải có đúng một sequence token `{SEQ:n}` với `n > 0`; `n` là minimum zero-pad width, sequence lớn hơn width được render đầy đủ và không truncate.
- `{RESOURCE}` render canonical `resources.type`.
- `{ORG_CODE}` render code của `scope_organization_id`.
- `{COMPANY_CODE}` chỉ hợp lệ khi scope resolve được Company.
- Không execute arbitrary expression, PHP, SQL, template script hoặc user code.
- Rendered identifier phải fit business field length/uniqueness rule của Resource adapter; invalid config bị reject trước khi active.

## 8.5 Period key và timezone

```text
NEVER   -> ALL
YEARLY  -> YYYY
MONTHLY -> YYYYMM
DAILY   -> YYYYMMDD
```

- COMPANY/ORGANIZATION scope derive local period từ cùng Evaluation Time theo timezone của Company owner.
- GROUP scope với date token hoặc `YEARLY`/`MONTHLY`/`DAILY` yêu cầu Group/default business timezone được cấu hình qua Settings. Nếu thiếu => reject configuration/use, không tự chọn timezone của một Company.

## 8.6 Generation mode

`AUTO_REQUIRED`:

- Create path không được cung cấp manual business identifier.
- Server bắt buộc generate qua AutoNumberService.

`AUTO_IF_EMPTY`:

- Nếu caller cung cấp business identifier, service giữ giá trị đó và vẫn validate uniqueness/domain rule; counter không tăng.
- Nếu blank/null, generate qua AutoNumberService.
- Manual identifier có thể chiếm một giá trị mà sequence sẽ gặp về sau; generator không được fail/kẹt vĩnh viễn vì trường hợp này mà phải skip occupied candidate theo rule allocation bên dưới.

Update existing record không tự generate number mới trừ khi một explicit renumber capability được phê duyệt trong revision sau; P1 không có renumber operation.

## 8.7 Counter allocation và concurrency

Canonical transaction flow:

```text
BEGIN
  resolve series + scope + period_key
  serialize counter key
  SELECT counter FOR UPDATE / create counter safely
  if counter is new: next_value = start_number
  else: next_value = last_value + increment_by

  LOOP
    render identifier(next_value)
    if identifier already exists in business uniqueness namespace:
        next_value = next_value + increment_by
        CONTINUE
    attempt persist business record
    if final DB unique guard reports identifier collision:
        next_value = next_value + increment_by
        CONTINUE
    BREAK

  update counter.last_value = next_value
COMMIT
```

Rules:

- Counter update, occupied-candidate skipping và business create phải cùng DB transaction.
- Existing counter row dùng row lock `FOR UPDATE`.
- First-use/no-counter case phải serialize key bằng DomainLockService/advisory lock với canonical key `auto-number:{series_id}:{scope_organization_id}:{period_key}` trước create; unique constraint là final guard.
- `AUTO_IF_EMPTY` manual create không advance counter; nếu manual value trùng candidate tương lai, auto allocator advance sequence dưới cùng counter lock cho tới candidate chưa bị chiếm.
- `counter.last_value` chỉ được set tới sequence value thực tế phát hành khi business row commit.
- Rollback rollback cả business row và counter, nên number chưa commit có thể được sử dụng lại.
- Sau commit, business logic không reuse number dù record bị inactive/cancelled.
- Preview/validation không được advance counter.

## 8.8 Configuration change

Trước committed issuance đầu tiên, series có thể được chỉnh theo validation bình thường. Sau khi đã tồn tại committed counter/issuance:

- **Immutable:** `resource_id`, `series_code`, `scope_type`, `reset_policy`. Muốn thay các dimension này phải tạo series mới.
- `start_number` không thay đổi counter đã tồn tại; chỉ áp dụng cho counter identity mới (scope/period mới) được tạo sau thay đổi.
- `pattern`, `increment_by`, `generation_mode`, `is_default`, `status` có thể thay đổi prospective nếu toàn bộ uniqueness namespace/pattern/domain validation vẫn pass.
- Series đã phát hành number không được hard-delete; dùng `INACTIVE` để ngừng phát hành mới.
- Không update/renumber historical business records.
- Thay active/default series phải transaction/serialize để không tồn tại hai active default series cho cùng Resource.
- Quản trị configuration yêu cầu `resource.configure_numbering`.

## 8.9 AutoNumberService contract

Input nội bộ tối thiểu:

```text
resource_type
authoritative_evaluation_organization_id
evaluation_time
optional_series_code
optional_manual_identifier
```

Output:

```text
identifier
series_id?
scope_organization_id?
period_key?
sequence_value?
generated: true|false
```

Resource adapter/use-case chịu trách nhiệm map output `identifier` vào canonical business identifier field (`product_code`, `document_no`, ...); client không được chỉ định arbitrary database column để AutoNumberService ghi.

# 9. Transaction và concurrency contract

## 9.1 Nguyên tắc chung

- Multi-table mutation/invariant phải nằm trong DB transaction theo Database Design.
- Không chỉ dựa vào "check rồi insert/update" ngoài transaction cho invariant có race condition.
- Unique/FK constraint là lớp bảo vệ cuối cho invariant database-enforced.
- Service-enforced invariant có khả năng race phải dùng lock strategy bên dưới.
- P1 không bổ sung optimistic-version column chỉ để giải quyết concurrency; nếu cần versioning sau này phải qua additive design.

## 9.2 DomainLockService

Application phải có abstraction `DomainLockService` cho mutation hiếm nhưng có invariant graph/global.

Implementation MySQL/MariaDB có thể dùng advisory lock tương đương `GET_LOCK`; framework code không gọi primitive DB-specific rải rác ngoài adapter.

Lock phải:

- có timeout hữu hạn;
- release trong `finally`;
- dùng stable key có namespace `application:environment:database:<domain-key>` để tránh collision giữa deployment;
- acquire/release advisory lock trên cùng database connection/session;
- không giữ qua network/external I/O;
- acquire theo deterministic order khi cần nhiều lock để tránh deadlock.

## 9.3 Lock matrix

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
| Auto Number counter existing row | same transaction + counter row `FOR UPDATE` |
| Auto Number first counter for series/scope/period | DomainLockService key `auto-number:{series_id}:{scope_organization_id}:{period_key}` + transaction + unique constraint |
| Auto Number active/default series change | lock Resource/series set + transaction để giữ tối đa một active default |

## 9.4 Deadlock / lock timeout

- Deadlock hoặc lock timeout không được silently retry vô hạn.
- Infrastructure được phép retry **tối đa 1 lần** khi transaction/use-case được xác định retry-safe/idempotent.
- Operation không retry-safe không được tự retry. Nếu retry một lần vẫn thất bại hoặc không thể retry an toàn, trả `409 CONCURRENT_MODIFICATION`.
- Không retry mutation đã phát external side effect ngoài transaction.

---

# 10. Audit contract

## 10.1 Actor fields

Với mutable master/config có `created_by`/`updated_by`:

- business request authenticated: dùng Shield User id hiện tại;
- seed/migration/system bootstrap: có thể `NULL` theo Database Design;
- không giả mạo một User business cho migration.

## 10.2 created_at / updated_at

- ghi bằng application/DB UTC convention thống nhất;
- create: `created_at` bắt buộc, `updated_at` có thể NULL theo schema;
- update thành công: cập nhật `updated_by` + `updated_at`;
- rollback: không để audit mutation partial.

## 10.3 Authorization audit

Persist tối thiểu cho:

- approve;
- delete;
- state change.

`request_id` phải được gắn khi request có correlation id.

---

# 11. Import transport và parser contract

## 11.1 Supported formats

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
- Formula cell bị reject trong P1; importer chỉ nhận literal value để giữ deterministic semantics.

Header được normalize bằng trim + lowercase; canonical header sau normalize là `snake_case`.

Unknown column:

- reject toàn file với `IMPORT_INVALID_TEMPLATE`;
- không silently ignore typo/unknown header trong P1.

## 11.2 Import job mode

Mỗi Import Job persistence một `mode` canonical trong `import_jobs.mode`:

```text
CREATE_ONLY
UPDATE_ONLY
UPSERT
```

Semantics:

- `CREATE_ONLY`: key đã tồn tại => row error.
- `UPDATE_ONLY`: key chưa tồn tại => row error.
- `UPSERT`: tồn tại => update; chưa tồn tại => create.

Default UI/API mode: `CREATE_ONLY` để tránh update ngoài ý muốn. `mode` phải được ghi cùng Import Job để audit/replay/debug.

## 11.3 Empty/null semantics

- CREATE: blank nullable field => NULL.
- UPDATE/UPSERT-existing: blank => không thay đổi field.
- Token canonical `__NULL__` dùng để clear field nullable một cách explicit.
- Required/non-null field nhận `__NULL__` => row validation error.

## 11.4 Product Import identifier semantics

| Concept | PRODUCT field | Semantics |
|---|---|---|
| Persistent Target Key | `product_code` | Lookup Product đã tồn tại cho `UPDATE_ONLY` và UPSERT-existing |
| Source-local Key | `source_key` | Identity duy nhất trong file/job để resolve Product create mới và dependency Parent/UOM/COMBO |
| Generated Business Identifier | `product_code` | Giá trị được AutoNumberService phát hành cho create path khi numbering áp dụng |

P1 baseline chỉ enable PRODUCT. Target import mới phải bổ sung persistent target key, source-local identity rule khi cần, template và Resource Action qua revision/change control trước khi triển khai.

Persistent target key không được dùng để rename target trong cùng import. `source_key` không phải business identifier và không persistence vào Product chỉ vì phục vụ import dependency.

## 11.5 PRODUCT template v1

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
source_key
product_code
sku
barcode
parent_source_key
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
product_source_key
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
combo_source_key
combo_product_code
component_source_key
component_product_code
quantity
```

Product importer phải build dependency graph toàn file trước mutation cần dependency. `source_key` của mỗi PRODUCT row phải unique trong file. Dependency tới Product create mới có thể dùng `*_source_key`; dependency tới Product đã tồn tại có thể dùng persistent `*_product_code`. Child có thể trước Parent, COMBO item có thể trước Product/component, nested COMBO phải detect cycle.

Khi Product numbering mode là `AUTO_REQUIRED`, create row để `product_code` blank và code được generate trong row mutation transaction. `UPDATE_ONLY` yêu cầu `product_code`. Với `UPSERT`, blank `product_code` là create path; supplied code phải tuân generation mode và không được bypass `AUTO_REQUIRED`.

## 11.6 Target enablement boundary

P1 baseline hiện chỉ enable `PRODUCT`. Không có canonical EMPLOYEE/PROVINCE/WARD importer trong revision này.

Nếu Employee import được enable trong một P1 extension sau này, `ASSIGNMENT` logical row mặc định chỉ `CREATE_ONLY` trừ khi revision mới khóa stable update identity; không suy diễn UPDATE/UPSERT Assignment từ tổ hợp field không có stable key.

## 11.7 Duplicate rows trong cùng file

- PRODUCT row: `source_key` bắt buộc unique trong file. Khi `product_code` được cung cấp, code cũng không được duplicate giữa các PRODUCT row.
- UOM conversion: unique theo resolved Product identity (source key hoặc persistent product code) + `uom_code`.
- COMBO item: unique theo resolved Combo owner identity + resolved Component identity, bất kể reference dùng source key hay persistent code.
- Với `UPDATE_ONLY`/`UPSERT`, chỉ key có mặt trong file được mutation; record/component hiện hữu nhưng không xuất hiện trong file **không** bị delete/inactivate.

## 11.8 Row transaction

Mỗi logical source row có row result và mutation atomic theo FRS/DBD.

Dependency preprocessing có thể đọc toàn file trước; việc reorder processing không được thay `row_number` gốc trong `import_job_rows`.

## 11.9 File hash

`source_file_hash` dùng SHA-256 khi application tính hash.

P1 dùng hash cho integrity/duplicate warning; **không tự động coi cùng hash là idempotent success** và không tự bỏ qua rerun, vì rerun có thể có mode/context khác.

---

# 12. Import authorization

P1 baseline start import:

```text
PRODUCT -> product.import
```

Authorization bắt buộc hai tầng:

```text
Gate 1: product.import tại GROUP
Gate 2: mỗi logical row tiếp tục cần product.create / product.edit / product.setstate
        tương ứng với mutation thực tế, vẫn qua PBAC.
```

Rules:

- `product.import` chỉ cho phép sử dụng importer; không cấp quyền mutation Product thay cho canonical action.
- Product là Group Master nên authoritative Evaluation Organization của Product import là `GROUP`.
- Resource không explicit enable `import` không được expose import endpoint/UI.
- Xem danh sách/job detail yêu cầu `import_job.view`.
- Export report import yêu cầu `import_job.export`.

---

# 13. Lifecycle/action execution contract

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

# 14. Pricing/decimal implementation contract

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

# 15. Repository/service boundary

## 15.1 Repository

Repository chịu trách nhiệm:

- persistence query;
- row locking primitive;
- database-enforced error translation;
- không chứa PBAC/business precedence riêng.

## 15.2 Domain/Application service

Service chịu trách nhiệm invariant service-enforced trong Database Design, gồm:

- Organization tree/cycle.
- Employee assignment overlap/context resolution.
- Manager cycle.
- Authorization/PBAC.
- Lifecycle validity.
- Product Role/UOM/COMBO/Pricing.
- Import dependency/validation.

Controller không được tự re-implement domain invariant.

## 15.3 Authorization placement

Protected use-case phải authorize tại application/use-case boundary trước mutation/export. Repository không phải nơi quyết định User được phép làm gì.

---

# 16. Validation error ordering

Khi một request có nhiều lỗi, service nên trả tất cả validation lỗi độc lập có thể xác định an toàn trong một pass, nhưng không bắt buộc tiếp tục các bước phụ thuộc sau một lỗi prerequisite.

Canonical order cho protected existing-target/mutation:

```text
1. Request parse / shape / required transport fields.
2. Authentication.
3. Resolve minimum authoritative Authorization Context / visibility.
4. Visibility + Resource/Action Authorization.
5. Reference existence / active status được phép disclose.
6. Domain invariant.
7. Cross-record/graph validation.
8. Persistence conflict.
```

Existing target không visible phải trả 404 để không xác nhận existence; target visible nhưng thiếu action permission trả 403.

Không được dùng thứ tự error để làm thay đổi business outcome.

---

# 17. Logging và sensitive data

- Không log password/token/session secret.
- Không log raw import row chứa dữ liệu nhạy cảm nếu không cần thiết.
- Import DB cũng không bắt buộc persist raw row payload theo Database Design.
- Authorization decision log không được biến thành nơi snapshot toàn bộ Employee/Profile.
- Application log được phép giữ ids/codes/reason code/request id để debug trong phạm vi security policy.

---

# 18. Test contract

## 18.1 Test levels

P1 phải có:

- unit test cho pure domain calculation;
- integration test cho repository + DB constraints/transaction/locking;
- service test cho business invariant;
- API contract test cho envelope/status/serialization.

## 18.2 Mandatory Authorization tests

Tối thiểu:

```text
No Base Permission -> DENY
Base + NO_MATCH -> ALLOW
Actor lane NO_MATCH + Assignment lane DENY -> effective ALLOW
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
Existing target context derived from persisted owner; client mismatch rejected
organization.create authorized at parent context
employee_assignment mutation authorized at assignment organization
```

## 18.3 Mandatory API contract tests

- BIGINT id is JSON string.
- Decimal/money is JSON string.
- Error envelope stable.
- 401 vs 403 distinct.
- Existing invisible target => 404; visible target without action permission => 403.
- Validation 422 vs conflict 409 distinct.
- Pagination total respects authorization scope.
- Request id echoed and propagated.

## 18.4 Mandatory concurrency tests

- Concurrent GROUP root create cannot produce two roots.
- Concurrent overlapping Primary Assignment create/update cannot both commit.
- Concurrent Organization re-parent cannot create cycle.
- Concurrent manager mutation cannot create manager cycle.
- Concurrent COMBO composition changes cannot commit a cycle.
- Concurrent batch Group Permission update does not leave partial matrix.

## 18.5 Mandatory Auto Numbering tests

- Resource zero/multi-series behavior; tối đa một active default.
- GROUP/COMPANY/ORGANIZATION counter scope isolation.
- AUTO_REQUIRED rejects manual identifier.
- AUTO_IF_EMPTY manual value does not consume counter; blank generates; generated candidate bị manual/existing identifier chiếm phải skip tới sequence hợp lệ kế tiếp.
- NEVER/YEARLY/MONTHLY/DAILY period key; reset pattern thiếu period discriminator theo uniqueness namespace bị reject.
- Company timezone và Group/default timezone prerequisite.
- Pattern token validation; arbitrary expression rejected.
- Two concurrent create cannot commit same identifier.
- First counter creation race cannot create duplicate counter rows.
- Business rollback rolls back counter; number can be reused after rollback.
- Committed number is not reused after inactive/cancelled record.
- Uniqueness scope `GLOBAL`/`COMPANY`/`ORGANIZATION` validate đúng với counter scope/pattern.
- Series có committed issuance không đổi resource/series_code/scope/reset và không hard-delete; start_number chỉ tác động counter identity mới.
- Pattern/config change hợp lệ does not renumber history.
- `resource.configure_numbering` is independent from `resource.edit`.

## 18.6 Mandatory import tests

- CSV and XLSX header normalization.
- Unknown header => `IMPORT_INVALID_TEMPLATE`.
- XLSX formula cell => reject.
- Persisted CREATE_ONLY/UPDATE_ONLY/UPSERT behavior.
- Blank vs `__NULL__` behavior.
- Product Child before Parent, including source-local key reference when code is generated; `product_code` vẫn là persistent target key cho existing lookup.
- COMBO item before component, including source-key owner/component reference.
- Nested COMBO cycle.
- UPSERT absence does not delete/inactivate existing UOM Conversion/COMBO Item.
- Two-gate authorization: `product.import` + per-row mutation action.
- Generated Product Code is returned/persisted for successful create row; failed row rolls back allocated number and preserves original row number.
- Same source hash does not auto-skip rerun.

---

# 19. Migration / bootstrap contract

- Migration là forward-only; không rewrite migration đã applied.
- Dependency order tuân Database Design v1.8.
- Resource/Action seeder chạy idempotent. P1 seed `product.import` và `resource.configure_numbering`; không seed import action cho Resource khác nếu chưa explicit enable.
- Migration tạo `auto_numbering_series` và `auto_numbering_counters` theo Database Design v1.8 trước khi enable numbering configuration.
- Workflow state seeder chạy idempotent theo code.
- Bootstrap tạo/đảm bảo một privileged Authorization Group canonical (ví dụ `SYSTEM_ADMIN`) cùng permission matrix được phê duyệt.
- Initial System Admin membership được provision bằng CLI/deployment/bootstrap mechanism ngoài runtime PBAC để tránh bootstrap deadlock.
- Sau bootstrap, Group tồn tại như Authorization Group bình thường; membership/permission mutation đi qua `authorization_group.assign_member` / `authorization_group.assign_permission` và PBAC.
- Bootstrap không tạo runtime bypass, không hard-code `isSystemAdmin => ALLOW`, và không tự cấp full permission cho mọi User.
- Deployment phải có đúng một Organization `GROUP` root trước Company bootstrap.

---

# 20. Configuration keys tối thiểu

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

# 21. Open items không được contract này tự suy diễn

Các điểm sau vẫn mở theo upstream sources hoặc cần authority khác:

1. Canonical value set của `employees.gender`.
2. Direct Manager effective-dated history ngoài current pointer P1.
3. CHILD display separator chính xác ở UI.
4. Retention period của `authorization_logs` và `import_jobs`.
5. P2 persistence để trace COMBO source tới transaction leaf.
6. P2 rule nếu muốn aggregate direct standalone leaf với COMBO-generated leaf.

Các item này không được dev tự tạo schema/capability mới trong P1.

---

# 22. Implementation order khuyến nghị

Dependency implementation:

```text
1. Migration / schema
2. Resource + Action + Workflow bootstrap seed
3. OrganizationService
4. EmployeeAssignmentService
5. AuthorizationService
6. LifecycleService
7. AutoNumberService
8. ProductRoleService
9. ProductUomService
10. PricingService
11. ComboExpansionService
12. ComboAllocationService
13. ImportService
14. HTTP controllers/API adapters
15. UI
```

Authorization tests nên được viết song song trước/đồng thời với `AuthorizationService`.

---

# 23. Definition of Done cho Implementation Contract P1

P1 implementation được xem là tuân Contract này khi tối thiểu:

- API dùng envelope/status/serialization canonical.
- BIGINT/DECIMAL không mất precision qua JSON.
- Request correlation hoạt động xuyên API -> authorization audit/log.
- Evaluation Time và authoritative Evaluation Organization được resolve deterministic; Company business DATE dùng Company timezone từ CodeIgniter Shield/Settings integration.
- Existing target context không bị client override; organization.create dùng parent context; employee_assignment dùng Assignment Organization.
- System Admin không có code-path bypass và bootstrap không tạo runtime bypass.
- GROUP Assignment và full ancestor path hoạt động đúng.
- Resource/Action seed matrix idempotent; security/configuration data cũng được bảo vệ; `assign_member`/`assign_permission` tách biệt; `resource.configure_numbering` độc lập; P1 chỉ seed `product.import` cho import target baseline.
- Auto Numbering dùng series/counter persistence, uniqueness-namespace validation, occupied-candidate skipping, post-issuance immutability/no-hard-delete, scope resolution, pattern/reset/generation mode và atomic concurrency semantics đúng contract; rollback không consume number chưa commit và committed number không reuse.
- Transaction boundaries của Database Design được triển khai.
- Race-sensitive invariant dùng lock/serialization phù hợp và có concurrency test.
- Import parser dùng Product template, persisted mode, strict header/formula/null semantics và phân biệt rõ persistent target key/source-local key/generated Product identifier trong tài liệu này.
- Import không bypass business validation/authorization; `product.import` và per-row canonical mutation permission đều phải pass.
- Controller không re-implement domain invariant.
- Automated test bao phủ FRS Acceptance Test Catalog + các contract test ở tài liệu này.
- Không có persistence mới trái Database Design v1.8.

---

# 24. Change control

Thay đổi một trong các contract sau phải bump version tài liệu và cập nhật test:

- HTTP envelope/status mapping.
- ID/decimal serialization.
- Evaluation Organization/Time semantics.
- Authorization pipeline hoặc denied-set merge.
- Resource Type hoặc canonical Action code.
- Auto Numbering series schema, uniqueness namespace, scope/generation/reset semantics, pattern token, post-issuance mutability hoặc counter transaction semantics.
- Lock strategy ảnh hưởng invariant.
- Import enabled target, template/key/mode/null semantics.
- Error code đã public cho client.

Thay đổi schema phải đi qua Database Design/change request tương ứng; không chỉ sửa Implementation Contract.
