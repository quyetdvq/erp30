---
document_id: "DBD-MINI-ERP-P1-001"
title: "P1 Database Design - Dinktech Mini ERP"
version: "1.13"
status: "baseline"
date: "2026-10-07"
scope: "P1 - Foundation, Master Data & Authorization"
---

# P1 Database Design - Dinktech Mini ERP

## 1. Mục đích tài liệu

Tài liệu này định nghĩa thiết kế database cho Phase P1 của Dinktech Mini ERP, bao gồm Foundation, Organization, Shared Contact, Employee, Authorization/PBAC, Auto Numbering, Workflow/Lifecycle cơ bản, Product, UOM, Core Pricing và Import Framework.

Tài liệu này là thiết kế triển khai database cho P1. FRS/implementation phải sử dụng thiết kế này làm boundary kỹ thuật và không tự tạo thêm persistence trái với tài liệu này nếu chưa có change request/additive design được phê duyệt.

Revision v1.13 là Database Design baseline đồng bộ với `00_User_Requirements_v1.0.14.md`, `01_P1_Functional_Requirements_Specification_v1.10.md` và `03_P1_Implementation_Contract_v1.8.md`; bổ sung `CHANGE-DISPLAY-SETTING-001` theo hướng presentation-only, tái sử dụng CodeIgniter Settings persistence và mở rộng canonical Resource matrix lên 21 Resource.

### 1.1 Trọng tâm revision v1.10

Revision này là baseline P1 và khóa các quyết định triển khai sau:

- Audit actor `created_by`/`updated_by` cho mutable master/config P1.
- Lifecycle transition deterministic theo `lifecycle + from_state + action`.
- Price List effective period `[effective_from, effective_to)`.
- Product default price lưu tại `products.price` cho Base UOM và `product_uom_conversions.price` cho Alternate UOM của SIMPLE/CHILD; không dùng bảng `product_price`.
- Price List item định giá theo `Product + UOM`; COMBO chỉ được định giá theo Base UOM.
- Pricing fallback `Company -> Group -> default price đúng UOM`.
- COMBO là composition/template chọn nhanh có Base UOM, default price và Price List riêng; COMBO không có Serial và không có UOM Conversion.
- COMBO được phép chứa SIMPLE, CHILD hoặc COMBO khác; graph composition phải acyclic và mọi nghiệp vụ downstream phải flatten về SIMPLE/CHILD.
- Giá của COMBO được resolve ở COMBO được chọn trực tiếp, sau đó phân bổ xuống leaf theo trọng số `leaf quantity × leaf default price`; nested COMBO price không được resolve lại.
- Cùng một leaf xuất hiện với nhiều allocated price trong một giao dịch được aggregate bằng tổng quantity/tổng amount; final unit price là weighted average theo quantity.
- Product semantic fields được bảo vệ sau khi có transaction history.
- Product Import resolve dependency multi-pass/dependency graph, không phụ thuộc thứ tự dòng nguồn và phải phát hiện Combo cycle.
- Authorization multi-assignment dùng full Organization hierarchy từ `GROUP` root; Primary Assignment chỉ resolve default actor context. GROUP Assignment có thể áp dụng xuống descendant; Company-scoped evaluation vẫn partition độc lập theo Company. PBAC resolve inheritance trước, apply QT1 trong từng lane, sau đó merge các tập quyền bị cấm bằng intersection; applicable `ABSOLUTE_DENY` được propagate vào mọi lane con chịu scope trước intersection.
- System Admin không có bypass: dùng Shield User + Employee + GROUP Assignment (Department IT; Position khi áp dụng), Base Permission và PBAC/`ABSOLUTE_DENY`/Workflow như User khác; initial privileged membership được provision qua bootstrap/deployment mechanism.
- Existing Resource derive Evaluation Organization từ persisted target; create derive từ owner/parent/scope canonical. Organization create evaluate tại parent; Employee Assignment mutation tại Assignment Organization.
- Mọi securable data/capability, kể cả `organization`, `user`, `authorization_group`, `resource`, `policy`, được biểu diễn bằng Resource Type; Resource Type không phải Resource Instance.
- Import Framework chỉ enable target theo Resource contract; P1 baseline enable `PRODUCT`. `import_jobs` persistence execution `mode` (`CREATE_ONLY`/`UPDATE_ONLY`/`UPSERT`).
- Business timezone được lấy từ Company-scoped CodeIgniter Shield/Settings integration mechanism; P1 không thêm timezone column/table riêng trong ERP schema chỉ cho convention này.
- Auto Numbering được persistence bằng `auto_numbering_series` + `auto_numbering_counters` liên kết `resources`; sequence hỗ trợ scope `GROUP`/`COMPANY`/`ORGANIZATION`, generation mode `AUTO_REQUIRED`/`AUTO_IF_EMPTY`, reset `NEVER`/`YEARLY`/`MONTHLY`/`DAILY`, và counter update phải atomic với business create để rollback không tiêu số chưa commit.
- Auto Numbering baseline giữ nguyên và khóa thêm uniqueness namespace validation ở Resource adapter/domain, skip generated candidate đã bị manual/existing identifier chiếm, immutable series identity/scope/reset sau committed issuance và no-hard-delete đối với series đã phát hành number.
- Product business identifier mapping được khóa rõ: database/domain field `products.code`, API/import alias `product_code`, max length 50, uniqueness namespace `GLOBAL`.
- Localization P1 hỗ trợ `vi`/`en` ở application/API presentation layer; không tạo translation table, locale column hay persistence dịch business record trong ERP schema. Locale preference baseline là workspace/client concern; server-side preference nếu bổ sung sau phải qua additive design.

### 1.2 Trọng tâm revision v1.12 - Resource Domain authorization scope

`resources.domain` được nâng từ metadata module tùy chọn thành canonical authorization-scope classification bắt buộc. P1 chỉ dùng ba giá trị `SYSTEM`, `MASTER`, `ORGANIZATION`; `COMPANY` không còn là Resource Domain. `SYSTEM`/`MASTER` evaluate tại `GROUP`; `ORGANIZATION` bắt buộc derive authoritative Organization context từ Resource contract. Domain của canonical Resource đã tồn tại là immutable trong normal runtime P1; thay đổi domain phải qua change control.

Revision này không thêm Organization ownership column vào shared master và không thay PBAC schema/algorithm.

### 1.3 Trọng tâm revision v1.13 - `CHANGE-DISPLAY-SETTING-001`

- Bổ sung Resource `display_setting`, Domain `ORGANIZATION`, actions `view`/`edit`.
- Display Setting definition nằm trong source-controlled registry; không persistence dynamic definition/key.
- Override persistence server-side tái sử dụng CodeIgniter Settings `settings` integration hiện hành; P1 không tạo bảng business `display_settings` riêng.
- Canonical context string của capability này chỉ là `system` và `organization:<id>`.
- Organization inheritance dùng canonical `organizations.parent_id` path leaf -> GROUP -> system -> registry default.
- Locale/User workspace preference không persistence server-side qua capability này.
- Display timezone/format/decimal setting không được dùng làm Business Timezone/canonical data/calculation authority.
- Audit mutation phải dùng approved technical audit sink của repository; change này không mặc định coi `authorization_logs` là general mutation audit và không tự tạo audit table ngoài DBD.

## 2. Phạm vi P1

P1 gồm các domain database sau:

1. Administrative Location.
2. Shared Contact.
3. Organization Context.
4. Department, Position, Employee và Employee Assignment.
5. Identity bridge với CodeIgniter Shield.
6. Authorization Group, Resource/Action, PBAC Policy và Authorization Audit.
7. Workflow State, Lifecycle và Lifecycle Transition cơ bản.
8. Product Master.
9. Unit of Measure.
10. Core Pricing.
11. Auto Numbering theo Resource catalog.
12. Import Framework; P1 baseline enabled target: Product.
13. Display Settings persistence integration qua CodeIgniter Settings; không tạo business settings table riêng.

Không thuộc P1 database design này:

- Customer/Supplier persistence.
- Promotion.
- Purchase/Inventory/Sales transaction tables.
- Serial Inventory instance table.
- HCM/Payroll transaction tables.
- BOM tables.
- Resource Instance Authorization.
- Arbitrary business condition rule.
- Policy version/effective dating.
- Permission table riêng.
- Persisted Profile table.
- Delegation/Temporary Permission.
- Multi-level approval engine.
- Multi-currency.

## 3. Quyết định thiết kế đã khóa

### 3.1 Organization và business scope

- Database dùng chung cho toàn hệ thống.
- Business scope vận hành một Organization Context type `GROUP` root và nhiều `COMPANY`.
- Organization Context type canonical: `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`.
- Product là Group Master và dùng chung cho toàn bộ Company; P1 không có `product_company_usages`.
- Business transaction ở phase sau phải resolve về đúng một Company owner.

### 3.2 Authorization

- Policy participant canonical dùng `GROUP`, không dùng `ROLE` cho dữ liệu mới.
- `groups.code` là canonical alias kết nối với Shield group membership.
- Direct user permission từ Shield `auth_permissions_users` được tính là Allow addition trong Effective Profile.
- Effective Profile là khái niệm runtime, không có bảng `profiles`.
- Policy không tạo permission mới.
- Base Permission vẫn là prerequisite: không có Base Permission thì DENY trước PBAC.
- QT1 trong từng authorization lane:

```text
ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH
NO_MATCH => ALLOW
```

- Evaluation Organization có thể là `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`; inheritance dùng exact path `GROUP root -> ... -> Evaluation Organization`.
- Effective Assignment tại current Organization hoặc ancestor có thể tạo DEPARTMENT/POSITION lane; GROUP Assignment được kế thừa xuống mọi descendant, còn Assignment ở sibling/Company khác không tham gia.
- Multi-role/multi-assignment merge dùng **denied-set intersection** sau khi resolve inheritance:
  - `KQ[Organization] = INTERSECTION(KQ[user], all KQ[assignment])`.
  - Khi cần Company summary: `KQ[COMPANY-summary] = INTERSECTION(all KQ[Organization] in Company)`.
- Applicable `ABSOLUTE_DENY` tại `GROUP`/ancestor/current Organization được propagate vào mọi lane con chịu scope trước intersection.
- System Admin không có bypass; không có special root path trong AuthorizationService. Initial membership được bootstrap/provision ngoài runtime authorization, không phải bypass.
- Resource Domain canonical là `SYSTEM` / `MASTER` / `ORGANIZATION`. `SYSTEM` và `MASTER` evaluate tại `GROUP`; `ORGANIZATION` derive authoritative Evaluation Organization từ persisted target/owner hoặc canonical owner/parent/scope của create. Missing/unknown domain hoặc unresolved required context fail closed.
- Client context không override authoritative context; Primary Assignment không được dùng làm generic fallback cho Resource Domain resolution. `organization.create` evaluate tại Parent Organization; `employee_assignment` mutation evaluate tại Assignment Organization.
- Resource catalog biểu diễn securable data/capability type, không biểu diễn từng record instance; `user`, `authorization_group`, `resource`, `policy`, `organization` đều là Resource Type hợp lệ.
- Canonical sensitive assignment actions gồm `authorization_group.assign_member`, `authorization_group.assign_permission`, `user.assign_permission`; direct user permission vẫn persistence trong identity mechanism hiện hành.
- `policies.state_id = NULL` nghĩa là Policy áp dụng cho mọi Workflow State của Resource phù hợp.
- Không có Resource Instance Authorization và không có condition rule theo amount/value hoặc actor-resource relation.

### 3.3 Workflow

- Workflow transition được persistence bằng `lifecycle_transitions`.
- Lifecycle/transition xác định transition hợp lệ; Authorization xác định User có quyền thực hiện action tương ứng hay không.
- P1 chỉ có transition catalog đơn giản, không phải enterprise workflow designer.

### 3.4 Product và Pricing

- Product Model V2 chỉ có một business entity `products`; Variant không tồn tại như entity/table riêng.
- Mọi Product/SKU được biểu diễn bằng một record trong `products`.
- Quan hệ Product cha/con dùng `products.parent_product_id`; độ sâu tối đa 1.
- `product_type` chỉ gồm `STANDARD` và `COMBO`. Các role `SIMPLE`, `PARENT`, `CHILD`, `COMBO` được suy ra động và không persistence thành role column.
- `SIMPLE` và `CHILD` là leaf transactionable Product. `COMBO` là selectable composition/template và phải được flatten về leaf trước Inventory/Serial/Costing/Posting. `PARENT` không selectable và không transactionable.
- Root/SIMPLE/PARENT/COMBO dùng `product_name`. CHILD dùng `product_child_name`; tên đầy đủ của CHILD được suy ra từ `parent.product_name + product_child_name`, không lưu `full_name`.
- COMBO bắt buộc có `base_uom_id` và `products.price`, được phép có `product_price_list_items`, nhưng không có Serial và không có `product_uom_conversions`.
- `product_combo_items` cho phép component là SIMPLE, CHILD hoặc COMBO; PARENT không hợp lệ. Nested COMBO được phép nhưng composition graph phải acyclic.
- Giá mặc định của Base UOM lưu tại `products.price`.
- Giá mặc định của Alternate UOM chỉ áp dụng cho SIMPLE/CHILD và lưu tại `product_uom_conversions.price`; giá theo từng UOM là giá cụ thể, không bắt buộc bằng Base price nhân `conversion_factor`.
- `product_price` không tồn tại trong canonical P1 schema.
- Pricing có thể khai báo ở scope `GROUP` hoặc `COMPANY` thông qua Organization Context.
- Price List item định giá cụ thể theo `Product + UOM`; đối với COMBO, `uom_id` bắt buộc bằng `products.base_uom_id`.
- Khi resolve giá cho Product được chọn trực tiếp, input canonical là `Product + UOM + Company + Effective Time`:

```text
1. Company Price List matching Product + UOM
2. Group Price List matching Product + UOM
3. Default price của đúng UOM
```

- Nếu Company có Price List nhưng không có matching item cho `Product + UOM`, resolution vẫn tiếp tục tìm Group Price List.
- Default price cuối cùng:

```text
Base UOM của SIMPLE/CHILD/COMBO -> products.price
Alternate UOM của SIMPLE/CHILD  -> product_uom_conversions.price
```

- Trong cùng một scope, candidate được chọn theo:

```text
Priority DESC
-> Effective From DESC
-> Price List ID ASC
```

- Nếu COMBO được chọn trực tiếp, chỉ giá của COMBO root đó được resolve. Giá/default price/Price List của nested COMBO chỉ đóng vai trò dữ liệu khi nested COMBO được chọn trực tiếp ở giao dịch khác; không được resolve lại trong quá trình flatten.
- COMBO phải flatten đệ quy đến SIMPLE/CHILD. Quantity leaf là tích quantity trên đường đi và được aggregate khi cùng leaf xuất hiện qua nhiều nhánh.
- Giá bán cuối của COMBO được phân bổ xuống leaf theo trọng số `leaf quantity × products.price` của leaf. Price List của leaf không tham gia xác định trọng số allocation.
- Tổng trọng số allocation bằng 0 là validation error; không tự chia đều.
- Allocation dùng precision cao, monetary result làm tròn về `DECIMAL(18,2)`; residual rounding phải được gán deterministic để bảo đảm tổng allocated leaf amount bằng đúng COMBO selling amount.
- Trong cùng một giao dịch, khi cùng một leaf nhận nhiều allocated price do xuất hiện trong nhiều COMBO, hệ thống aggregate `final_quantity = SUM(quantity)` và `final_amount = SUM(allocated_amount)`; `final_unit_price = final_amount / final_quantity`, tương đương weighted average theo quantity.
- Price List effective period dùng half-open interval `[effective_from, effective_to)`; `effective_to = NULL` nghĩa là open-ended.
- Effective period có thể overlap; resolution order phải luôn deterministic.
- Currency P1 là VND duy nhất.
- Monetary columns dùng `DECIMAL(18,2)`.

### 3.5 Import

- Import Framework có persistence lịch sử.
- Import target không tự bật cho mọi master. P1 baseline enable `PRODUCT`; target khác cần change control và Resource Action/template riêng.
- Import phải lưu được job summary, execution mode và kết quả từng dòng.
- Product Import có source-local Product identity để dependency graph không phụ thuộc vào generated `product_code` khi Auto Numbering áp dụng.

### 3.6 Auto Numbering

- Auto Numbering là foundation persistence gắn với `resources.id`; không thêm counter/pattern trực tiếp vào bảng `resources`.
- Một Resource có thể có nhiều numbering series nhưng tối đa một active default series.
- Series scope: `GROUP`, `COMPANY`, `ORGANIZATION`.
- Generation mode: `AUTO_REQUIRED`, `AUTO_IF_EMPTY`.
- Reset policy: `NEVER`, `YEARLY`, `MONTHLY`, `DAILY`.
- Auto Number sinh business identifier; primary key kỹ thuật vẫn là `BIGINT id`.
- Resource adapter/domain khai báo business identifier uniqueness namespace canonical `GLOBAL`/`COMPANY`/`ORGANIZATION`; metadata này không persistence thêm vào `resources` hoặc numbering tables trong P1.
- Series `scope_type + reset_policy + pattern` phải tương thích uniqueness namespace; counter partition hẹp hơn uniqueness scope hoặc reset theo period phải được final identifier/domain unique key phân biệt đầy đủ.
- Counter identity canonical là `numbering_series_id + scope_organization_id + period_key`.
- `AUTO_IF_EMPTY` manual identifier không advance counter. Generated candidate đã tồn tại phải được skip bằng sequence advancement trong cùng serialized transaction; `last_value` phản ánh sequence thực tế đã phát hành.
- Generate number và business create phải cùng transaction; rollback transaction rollback counter. Number đã commit không được tái sử dụng bởi business logic.
- Sau committed issuance đầu tiên, `resource_id`, `series_code`, `scope_type`, `reset_policy` của series là immutable; `start_number` chỉ ảnh hưởng counter identity mới chưa tồn tại. Series đã phát hành number không hard-delete, chỉ chuyển `INACTIVE`.
- Quản trị numbering config được bảo vệ bằng `resource.configure_numbering`.
- Pattern/config thay đổi hợp lệ chỉ áp dụng generation về sau; không renumber record lịch sử.

### 3.7 Localization / i18n persistence boundary

- Supported UI/API locale baseline: `vi`, `en`; default `vi`.
- Locale resolution, translation resources và `Accept-Language` negotiation thuộc application/runtime layer.
- P1 **không** thêm `locale`, `language` hoặc translation table vào business master chỉ để phục vụ UI đa ngôn ngữ.
- Business data do User nhập không tự có bản dịch trong P1.
- Canonical code/enum/API/import key không thay đổi theo locale.
- Nếu phase sau cần per-user server-side locale preference hoặc multilingual business-content persistence thì phải có additive requirement/design riêng.

### 3.8 Display Settings persistence boundary

Display Settings là configuration capability presentation-only.

Canonical persistence authority:
- Definitions/validation/default/allowed scope nằm trong application source registry.
- Override value dùng CodeIgniter Settings persistence hiện hành.
- ERP schema **không** tạo `display_settings` hoặc `user_display_settings` table trong P1.

Canonical contexts:

```text
system
organization:<organizations.id>
```

`company:<id>` và `user:<id>` không phải canonical Display Setting context P1.

Organization context reference phải resolve tới `organizations.id`; application validate existence và hierarchy bằng Organization service. Đây là application-managed reference trong Settings context string, không tạo FK mới từ external Settings package table tới `organizations`.

Effective resolution:

```text
organization:leaf
-> organization:parent
-> ...
-> organization:GROUP
-> system
-> source registry default
```

Rules:
- display setting row không làm thay đổi Organization ownership/business context;
- stale/unknown setting key trong external settings storage không được trở thành runtime capability nếu registry không còn khai báo;
- locale `vi`/`en` và workspace local preference không persistence bằng server Display Settings trong P1;
- `Display.timezone` là presentation value, không thay Company Business Timezone Settings integration ở mục 4.4;
- server mutation actor/timestamp phải được ghi qua approved general configuration audit mechanism của repository. Nếu implementation inventory không tìm thấy audit sink phù hợp, task phải BLOCKED/change-control thay vì tự tạo persistence ngoài DBD.

## 4. Naming và conventions

### 4.1 Quy ước chung

- Table name: `snake_case`; mặc định dùng plural. Tên canonical đã được phê duyệt là ngoại lệ khi tài liệu chỉ định rõ.
- Primary key: `id BIGINT`.
- Foreign key column: `<entity>_id`.
- Timestamp: `created_at`, `updated_at`; immutable audit/result table có thể chỉ có `created_at`.
- Mutable master/config P1 lưu `created_by`, `updated_by` dưới dạng logical Shield user reference; các field này có thể NULL cho seed/system migration.
- Không tạo physical FK từ audit actor (`created_by`, `updated_by`) tới Shield nếu migration owner không kiểm soát physical Shield schema.
- Status code dùng `VARCHAR(20)` trừ khi table có nhu cầu khác.
- Không hard delete record đã được business data hoặc audit tham chiếu; chuyển trạng thái khi phù hợp.
- Không tạo FK cho polymorphic reference.
- User reference tới Shield là logical identity reference; không tạo local user/password/session table.

### 4.2 Boolean

Boolean logical dùng kiểu database tương thích MySQL/MariaDB (`BOOLEAN`/`TINYINT(1)`).

### 4.3 Tiền

- `DECIMAL(18,2)`.
- Currency ngầm định P1: VND.
- Không tạo FX rate hoặc currency conversion table trong P1.

### 4.4 Business timezone

- Evaluation Time được truyền như instant thống nhất.
- Với Company-scoped business `DATE`, application lấy timezone từ Company-scoped CodeIgniter Shield/Settings integration mechanism được phê duyệt và derive local `EvaluationDate`.
- Không thêm `timezone` column/table riêng vào P1 ERP schema chỉ từ convention này.
- Không dùng browser timezone làm authority cho effective-date validation.

## 5. Domain map

```text
provinces
  -> wards
  -> contacts

organizations
  -> organizations (parent_id)
  -> contacts (polymorphic)
  -> employee_assignments
  -> price_lists

employees
  -> employees (manager_employee_id)
  -> contacts (polymorphic)
  -> employee_assignments

departments
  -> departments (parent_id)
  -> employee_assignments

positions
  -> employee_assignments

lifecycles
  -> lifecycle_states -> workflow_states
  -> lifecycle_transitions

resources
  -> resource_actions
  -> auto_numbering_series
      -> auto_numbering_counters
  -> policies
  -> authorization_logs

Shield auth_groups_users
  -> groups
  -> group_permissions
  -> resource_actions

Shield auth_permissions_users
  -> resource.action catalog validation

policies
  -> policy_actions
  -> resource_actions

product_groups
  -> product_groups (parent_id; arbitrary depth, acyclic)
product_uoms
products
  -> products (parent_product_id; depth max 1)
  -> product_groups
  -> product_uoms (base_uom_id)
  -> product_uom_conversions (alternate UOM + default UOM price)
  -> product_combo_items (combo/component; nested COMBO allowed, acyclic)
  -> product_price_list_items

price_lists
  -> organizations (GROUP/COMPANY only)
  -> product_price_list_items

import_jobs
  -> import_job_rows
```

# 6. Foundation / Location Domain

## 6.1 `provinces`

Mục đích: danh mục Province/City dùng chung.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| code | VARCHAR(20) | No | Unique |
| name | VARCHAR(255) | No |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(name)`
- `INDEX(status)`

## 6.2 `wards`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| province_id | BIGINT | No | FK -> provinces.id |
| code | VARCHAR(20) | No | Unique |
| name | VARCHAR(255) | No |  |
| name_full | VARCHAR(255) | Yes | Display/search snapshot |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(province_id)`
- `INDEX(name)`
- `INDEX(status)`

Validation:

- Ward mới chỉ được tham chiếu Province active.
- Province/Ward inactive không được chọn cho dữ liệu mới nhưng không làm mất reference lịch sử.

# 7. Shared Contact Domain

## 7.1 `contacts`

Contact dùng polymorphic reference.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| contact_type | VARCHAR(50) | No | P1: `EMPLOYEE`, `ORGANIZATION` |
| contact_ref_id | BIGINT | No | Polymorphic ref, no FK |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE`/`CLOSED` |
| name | VARCHAR(255) | Yes | Contact name |
| email | VARCHAR(255) | Yes |  |
| phone | VARCHAR(50) | Yes |  |
| mobile | VARCHAR(50) | Yes |  |
| address | TEXT | Yes | Detailed address |
| province_id | BIGINT | Yes | FK -> provinces.id |
| ward_id | BIGINT | Yes | FK -> wards.id |
| province_name | VARCHAR(255) | Yes | Historical/display snapshot |
| ward_name | VARCHAR(255) | Yes | Historical/display snapshot |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes:

- `PRIMARY KEY(id)`
- `INDEX(contact_type, contact_ref_id)`
- `INDEX(email)`
- `INDEX(phone)`
- `INDEX(mobile)`
- `INDEX(province_id)`
- `INDEX(ward_id)`
- `INDEX(status)`

Rules:

- Không tạo FK từ `contact_ref_id` đến `employees` hoặc `organizations`.
- Application validation phải xác nhận record được tham chiếu tồn tại theo `contact_type`.
- Nếu cả Province và Ward được khai báo, Ward phải thuộc Province tương ứng.

# 8. Organization Domain

## 8.1 `organizations`

Organization Context chung cho GROUP/COMPANY/BRANCH/PLANT/WAREHOUSE.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| parent_id | BIGINT | Yes | Self FK -> organizations.id |
| type | VARCHAR(30) | No | Canonical type |
| code | VARCHAR(50) | No | Unique |
| name | VARCHAR(255) | No |  |
| legal_name | VARCHAR(255) | Yes | P1 required for Company when applicable |
| tax_code | VARCHAR(50) | Yes | Company tax code |
| address | TEXT | Yes | Detailed address |
| province_id | BIGINT | Yes | FK -> provinces.id |
| ward_id | BIGINT | Yes | FK -> wards.id |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE`/`CLOSED` |
| active_from | DATE | Yes |  |
| active_to | DATE | Yes |  |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(parent_id)`
- `INDEX(type)`
- `INDEX(status)`
- `INDEX(tax_code)`
- `INDEX(province_id)`
- `INDEX(ward_id)`

### 8.1.1 Organization type rules

Allowed types:

- `GROUP`
- `COMPANY`
- `BRANCH`
- `PLANT`
- `WAREHOUSE`

Business P1 có một GROUP root. Quy tắc một GROUP được enforce ở application/service layer.

### 8.1.2 Parent matrix

| Parent | Allowed child |
|---|---|
| ROOT | GROUP |
| GROUP | COMPANY |
| COMPANY | BRANCH, PLANT, WAREHOUSE |
| BRANCH | PLANT, WAREHOUSE |
| PLANT | WAREHOUSE |
| WAREHOUSE | none |

Không cho same-type nesting trong P1.

Ví dụ hợp lệ:

```text
GROUP -> COMPANY -> BRANCH -> PLANT -> WAREHOUSE
GROUP -> COMPANY -> BRANCH -> WAREHOUSE
GROUP -> COMPANY -> PLANT -> WAREHOUSE
GROUP -> COMPANY -> WAREHOUSE
```

Validation bắt buộc:

- Không self-parent.
- Không cycle.
- Không parent-child trái matrix.
- Company/Branch/Plant/Warehouse dùng cho nghiệp vụ phải resolve được về đúng một Company ancestor.
- `active_to >= active_from` khi cả hai có giá trị.
- Ward phải thuộc Province khi cả hai được khai báo.

# 9. Employee Domain

## 9.1 `departments`

Department là master độc lập, không có `organization_id`.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| parent_id | BIGINT | Yes | Self FK |
| code | VARCHAR(50) | No | Unique |
| name | VARCHAR(255) | No |  |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE`/`CLOSED` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(parent_id)`
- `INDEX(status)`

Rules:

- Không self-parent/cycle.
- Không gắn Organization ownership trực tiếp.

## 9.2 `positions`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| code | VARCHAR(50) | No | Unique |
| name | VARCHAR(255) | No |  |
| level | INT | Yes | Position level |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE`/`CLOSED` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(level)`
- `INDEX(status)`

## 9.3 `employees`

Employee Master. P1 không tạo full version history cho Employee Master.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| user_id | BIGINT | Yes | Unique logical ref to Shield user |
| employee_code | VARCHAR(50) | No | Unique |
| employee_name | VARCHAR(255) | No | Họ và tên |
| gender | VARCHAR(20) | Yes | Value catalog do application quản lý |
| date_of_birth | DATE | Yes |  |
| place_of_origin | VARCHAR(255) | Yes | Quê quán |
| hire_date | DATE | Yes | Ngày vào làm |
| manager_employee_id | BIGINT | Yes | Self FK, quản lý trực tiếp hiện tại |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes/constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(user_id)`; multiple NULL allowed
- `UNIQUE(employee_code)`
- `INDEX(employee_name)`
- `INDEX(manager_employee_id)`
- `INDEX(status)`

Rules:

- Một Shield User tối đa mapping một Employee.
- Employee không bắt buộc có User.
- `manager_employee_id != id`.
- Không cho phép management cycle như `A -> B -> C -> A`; service phải kiểm tra ancestor chain trước khi gán manager.
- Employee `INACTIVE` có thể vẫn được giữ trong historical/current reference cũ nhưng không được chọn làm manager mới.
- P1 lưu direct manager hiện tại bằng self-reference; không version lịch sử manager riêng.
- Địa chỉ/liên hệ của Employee dùng Shared Contact; không tạo contact store riêng trong `employees`.

> Design assumption cần review ở FRS: nếu business cần lịch sử direct manager theo effective period, bổ sung design riêng thay vì tự version toàn bộ Employee Master.

## 9.4 `employee_assignments`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| employee_id | BIGINT | No | FK -> employees.id |
| organization_id | BIGINT | No | FK -> organizations.id |
| department_id | BIGINT | Yes | FK -> departments.id |
| position_id | BIGINT | Yes | FK -> positions.id |
| is_primary | BOOLEAN | No | Default false |
| effective_from | DATE | No |  |
| effective_to | DATE | Yes |  |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes:

- `PRIMARY KEY(id)`
- `INDEX(employee_id)`
- `INDEX(organization_id)`
- `INDEX(department_id)`
- `INDEX(position_id)`
- `INDEX(employee_id, is_primary, effective_from, effective_to)`
- `INDEX(status)`

Service-layer invariants:

- `effective_to >= effective_from` nếu có.
- Một Employee có thể nhiều Assignment active.
- Tại một thời điểm chỉ có tối đa một Primary Assignment effective cho một Employee.
- Không cho Primary Assignment overlap về thời gian.
- Organization/Department/Position inactive không được dùng cho Assignment mới.
- Primary Assignment chỉ là nguồn resolve actor Organization Context **mặc định** khi operation không có Evaluation Organization Context rõ ràng.
- Authorization có thể dùng bất kỳ Assignment effective nào có Organization bằng Evaluation Organization hoặc là ancestor của nó trên exact path từ `GROUP`; Primary flag không phải điều kiện duy nhất để DEPARTMENT/POSITION tham gia PBAC.
- Assignment tại `GROUP` có thể cung cấp Department/Position lane cho mọi descendant Organization.
- Khi operation đã có Evaluation Organization Context và cần transaction Company owner, resolve Company từ chính context đó tới self/ancestor `COMPANY` gần nhất; `GROUP` không phải Company owner.
- Assignment thuộc sibling Organization hoặc subtree Company khác không được trộn vào authorization calculation hiện tại.

# 10. Shield Identity Integration

P1 không tạo bảng identity nội bộ cạnh tranh với CodeIgniter Shield.

Các bảng Shield là external schema dependency:

- `users`
- `auth_groups_users`
- `auth_permissions_users`

Rules:

- Authentication/session/token do Shield quản lý.
- `employees.user_id` là logical reference tới Shield User.
- User-to-Authorization-Group membership lấy từ `auth_groups_users`.
- Direct User Permission lấy từ `auth_permissions_users` và chỉ được tính nếu permission code map được vào Resource Action catalog.
- Direct User Permission là Allow addition; không bypass Policy và không bypass `ABSOLUTE_DENY`.
- Mutation direct User Permission qua Mini ERP, khi expose, dùng action `user.assign_permission` và ghi vào Shield/identity permission mechanism hiện hành; không tạo local override table.
- System Admin không có bypass trong P1: phải map Shield User -> Employee -> effective GROUP Assignment và sử dụng Base Permission/PBAC như User khác. Không tạo database column, FK exception, special policy row hoặc hard-coded root permission path.

# 11. Resource / Authorization Group Domain

Resource catalog biểu diễn **loại dữ liệu/capability cần bảo vệ**, không phải từng record instance. Mọi securable domain/configuration object phải map vào Resource Type canonical, gồm tối thiểu các nhóm như `organization`, `user`, `authorization_group`, `resource`, `policy`, `employee`, `employee_assignment`, `product`, `price_list`, `import_job` khi capability tương ứng tồn tại. `authorization_group` được dùng thay cho resource type mơ hồ `group` để phân biệt với Organization type `GROUP`.

P1 không tạo một `resources` row cho từng User/Product/Organization record và không triển khai Resource Instance Authorization; row/data scope tiếp tục được xác định bằng Evaluation Organization Context + PBAC.

## 11.1 `resources`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| lifecycle_id | BIGINT | Yes | FK -> lifecycles.id |
| type | VARCHAR(100) | No | Unique canonical resource type |
| name | VARCHAR(255) | No | Display name |
| domain | VARCHAR(100) | No | Canonical authorization-scope classification: `SYSTEM` / `MASTER` / `ORGANIZATION` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(type)`
- `INDEX(domain)`
- `INDEX(lifecycle_id)`

### 11.1.1 Canonical Resource Domain semantics

`resources.domain` là security-relevant metadata xác định **cách resolve Evaluation Organization**, không phải Resource Instance scope và không tự cấp Permission.

```text
SYSTEM
    -> Group-wide system/security/configuration Resource
    -> Evaluation Organization = GROUP

MASTER
    -> Group-wide shared business master/reference
    -> Evaluation Organization = GROUP

ORGANIZATION
    -> authoritative Organization context derived from target/owner/parent/persisted context
    -> may resolve to GROUP / COMPANY / BRANCH / PLANT / WAREHOUSE
```

Rules:

- `COMPANY` không phải Resource Domain canonical.
- Missing/unknown domain hoặc unresolved `ORGANIZATION` context phải fail closed ở runtime.
- Domain của canonical existing Resource immutable trong normal runtime P1; thay đổi domain là authorization semantic change và phải qua change control.
- Không thêm `organization_id` vào shared master chỉ để phục vụ authorization.

Canonical P1 matrix:

| Resource | Domain | Context source |
|---|---|---|
| `province` | `MASTER` | `GROUP` |
| `ward` | `MASTER` | `GROUP` |
| `department` | `MASTER` | `GROUP` |
| `position` | `MASTER` | `GROUP` |
| `employee` | `MASTER` | `GROUP` |
| `product_group` | `MASTER` | `GROUP` |
| `product_uom` | `MASTER` | `GROUP` |
| `product` | `MASTER` | `GROUP` |
| `user` | `SYSTEM` | `GROUP` |
| `authorization_group` | `SYSTEM` | `GROUP` |
| `resource` | `SYSTEM` | `GROUP` |
| `workflow_state` | `SYSTEM` | `GROUP` |
| `lifecycle` | `SYSTEM` | `GROUP` |
| `organization` | `ORGANIZATION` | existing target / create parent |
| `contact` | `ORGANIZATION` | canonical owner |
| `employee_assignment` | `ORGANIZATION` | `organization_id` |
| `policy` | `ORGANIZATION` | `organization_id` |
| `authorization_log` | `ORGANIZATION` | persisted evaluated `organization_id`; `NULL -> GROUP` |
| `price_list` | `ORGANIZATION` | `organization_id` |
| `import_job` | `ORGANIZATION` | enabled import-target canonical scope |
| `display_setting` | `ORGANIZATION` | `system -> GROUP`; `organization:<id> -> target Organization` |

## 11.2 `resource_actions`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| resource_id | BIGINT | No | FK -> resources.id |
| code | VARCHAR(50) | No | Action code |
| description | VARCHAR(255) | Yes |  |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(resource_id, code)`
- `INDEX(code)`

Canonical permission code runtime:

```text
<resource.type>.<resource_actions.code>
```

Default action catalog có thể gồm:

`view`, `create`, `edit`, `delete`, `submit`, `approve`, `reject`, `cancel`, `post`, `close`, `export`, `print`, `setstate`; additional canonical actions khi capability áp dụng gồm `import`, `assign_member`, `assign_permission`, `configure_numbering`.

## 11.3 `groups`

Authorization Group, không phải Organization type GROUP.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| code | VARCHAR(100) | No | Canonical Shield group alias |
| name | VARCHAR(100) | No | Display name |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(status)`

Bridge rule:

```text
groups.code <-> auth_groups_users.group
```

## 11.4 `group_permissions`

| Column | Type | Null | Rule |
|---|---|---:|---|
| group_id | BIGINT | No | FK -> groups.id |
| resource_action_id | BIGINT | No | FK -> resource_actions.id |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Constraints:

- `PRIMARY KEY(group_id, resource_action_id)`
- `INDEX(resource_action_id)`

Group Permission không có Organization Scope.


## 11.5 Auto Numbering

Auto Numbering configuration tách khỏi Resource catalog để `resources` chỉ giữ metadata/capability identity, còn sequence state được quản lý riêng.

### 11.5.1 `auto_numbering_series`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| resource_id | BIGINT | No | FK -> resources.id |
| series_code | VARCHAR(100) | No | Stable code unique trong Resource |
| name | VARCHAR(255) | No | Display name |
| scope_type | VARCHAR(20) | No | `GROUP`/`COMPANY`/`ORGANIZATION` |
| pattern | VARCHAR(255) | No | Canonical pattern, validated by application |
| reset_policy | VARCHAR(20) | No | `NEVER`/`YEARLY`/`MONTHLY`/`DAILY` |
| generation_mode | VARCHAR(30) | No | `AUTO_REQUIRED`/`AUTO_IF_EMPTY` |
| start_number | BIGINT | No | >= 0; baseline default 1 |
| increment_by | INT | No | > 0; baseline default 1 |
| is_default | BOOLEAN | No | Default false |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints/indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(resource_id, series_code)`
- `INDEX(resource_id, status)`
- `INDEX(scope_type, status)`

Service invariants:

- Một Resource có tối đa một `ACTIVE` series với `is_default = true`; conditional uniqueness này được service/transaction enforce.
- Resource adapter/domain phải khai báo canonical business identifier field, max length và uniqueness namespace `GLOBAL`/`COMPANY`/`ORGANIZATION`; metadata này nằm ở application/domain contract, không thêm column vào bảng này.
- Pattern phải parse được theo canonical token set của Implementation Contract và chỉ có một sequence token hợp lệ.
- Trước khi series được activate hoặc configuration phát hành-number được sửa, service validate `scope_type + reset_policy + pattern` tương thích với uniqueness namespace; các partition Company/Organization/period không được tạo cùng identifier trong cùng domain uniqueness key.
- `increment_by > 0`; `start_number >= 0`. Counter mới phát hành giá trị đầu tiên đúng bằng `start_number`; các lần sau tăng theo `increment_by`.
- Sau khi tồn tại committed counter cho series: `resource_id`, `series_code`, `scope_type`, `reset_policy` immutable; `start_number` chỉ áp dụng counter key mới chưa tồn tại. `pattern`, `increment_by`, `generation_mode`, default/status chỉ được thay đổi prospective khi validation mới vẫn pass.
- Series đã có committed counter/issuance không được hard-delete; chỉ được chuyển `INACTIVE`.
- Thay configuration hợp lệ không update business identifier đã lưu trên record cũ.
- Resource không có active series không tự bị ép dùng Auto Number.

### 11.5.2 `auto_numbering_counters`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| numbering_series_id | BIGINT | No | FK -> auto_numbering_series.id |
| scope_organization_id | BIGINT | No | FK -> organizations.id; GROUP root / Company / exact Organization theo scope |
| period_key | VARCHAR(20) | No | `ALL`, `YYYY`, `YYYYMM`, hoặc `YYYYMMDD` theo reset policy |
| last_value | BIGINT | No | Last committed sequence value |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints/indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(numbering_series_id, scope_organization_id, period_key)`
- `INDEX(scope_organization_id)`

Rules:

- `NEVER` dùng `period_key = ALL`.
- `YEARLY` dùng `YYYY`; `MONTHLY` dùng `YYYYMM`; `DAILY` dùng `YYYYMMDD`.
- COMPANY/ORGANIZATION period được derive từ business timezone của Company owner. GROUP series có date token hoặc date-based reset chỉ được active khi Group/default business timezone đã được cấu hình qua Settings.
- Counter row là mutable runtime state, không phải audit/business document.
- `AUTO_IF_EMPTY` manual identifier không tạo/update counter. Khi candidate render từ next sequence đã tồn tại, allocator tiếp tục tăng theo `increment_by` dưới cùng serialization cho tới candidate hợp lệ; khi commit, `last_value` lưu sequence thực tế được phát hành.
- Allocation counter và create business record phải cùng transaction. Lock/concurrency strategy được Implementation Contract khóa; rollback transaction không được để counter tăng riêng.

# 12. Workflow / Lifecycle Domain

## 12.1 `lifecycles`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| code | VARCHAR(50) | No | Unique |
| name | VARCHAR(255) | No |  |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(status)`

## 12.2 `workflow_states`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| code | VARCHAR(50) | No | Unique |
| name | VARCHAR(255) | No |  |
| description | TEXT | Yes |  |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(status)`

## 12.3 `lifecycle_states`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| lifecycle_id | BIGINT | No | FK -> lifecycles.id |
| state_id | BIGINT | No | FK -> workflow_states.id |
| sequence | INT | No | Display/process sequence |
| is_initial | BOOLEAN | No | Default false |
| is_final | BOOLEAN | No | Default false |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(lifecycle_id, state_id)`
- `INDEX(lifecycle_id, sequence)`

Service validation:

- Một lifecycle chỉ có một initial state active tại một thời điểm cấu hình.
- Final state có thể nhiều.

## 12.4 `lifecycle_transitions`

P1 persistence cho legal state transition.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| lifecycle_id | BIGINT | No | FK -> lifecycles.id |
| from_state_id | BIGINT | No | FK -> workflow_states.id |
| to_state_id | BIGINT | No | FK -> workflow_states.id |
| action_code | VARCHAR(50) | No | Canonical action triggering transition |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(lifecycle_id, from_state_id, action_code)`
- `INDEX(lifecycle_id, from_state_id, status)`

Rules:

- `from_state_id` và `to_state_id` phải đều là state thuộc lifecycle đó.
- Với cùng `lifecycle_id + from_state_id + action_code`, chỉ có một `to_state_id` hợp lệ; một action không được resolve tới nhiều destination state.
- Transition validity không tự cấp permission.
- Khi transition được gọi cho Resource, `action_code` phải map được tới Resource Action của Resource tương ứng nếu transition yêu cầu Authorization.

# 13. PBAC Policy Domain

## 13.1 `policies`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| organization_id | BIGINT | No | FK -> organizations.id |
| resource_id | BIGINT | No | FK -> resources.id |
| state_id | BIGINT | Yes | FK -> workflow_states.id; NULL = all states |
| participant_type | VARCHAR(30) | No | Canonical participant type |
| participant_ref_id | BIGINT | No | Polymorphic ref, no FK |
| created_by | BIGINT | Yes | Logical Shield user ref |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Participant values P1:

- `USER`
- `GROUP`
- `POSITION`
- `DEPARTMENT`
- `ORGANIZATION`

Indexes:

- `PRIMARY KEY(id)`
- `INDEX(organization_id, resource_id, state_id)`
- `INDEX(participant_type, participant_ref_id)`
- `INDEX(resource_id)`

Polymorphic participant mapping:

| participant_type | participant_ref_id target |
|---|---|
| USER | Shield users.id |
| GROUP | groups.id |
| POSITION | positions.id |
| DEPARTMENT | departments.id |
| ORGANIZATION | organizations.id |

Rules:

- Không tạo FK cho `participant_ref_id`.
- Application validation kiểm tra target theo participant type.
- `GROUP` là canonical value cho dữ liệu mới.
- Nếu dữ liệu legacy có `ROLE`, migration phải normalize sang `GROUP` sau khi mapping xác định được; runtime mới không ghi `ROLE`.
- `state_id = NULL` match mọi current state.
- Policy inheritance được resolve runtime theo exact ancestor path từ `GROUP` root tới Evaluation Organization; không copy physical policy xuống child.
- Inheritance phải được resolve trước denied-set intersection.
- Với bất kỳ Organization `O` thuộc `GROUP`/`COMPANY`/`BRANCH`/`PLANT`/`WAREHOUSE`, runtime policy source là toàn bộ `GROUP root -> ... -> O`; khi `O = GROUP`, source chỉ có GROUP.
- Applicable `ABSOLUTE_DENY` sau participant/resource/state/action matching tại bất kỳ node nào trên scope path phải được propagate vào mọi KQ lane con chịu scope trước intersection.

## 13.2 `policy_actions`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| policy_id | BIGINT | No | FK -> policies.id |
| resource_action_id | BIGINT | No | FK -> resource_actions.id |
| effect | VARCHAR(30) | No | `ALLOW`/`DENY`/`ABSOLUTE_DENY` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No |  |
| updated_at | DATETIME | Yes |  |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(policy_id, resource_action_id)`
- `INDEX(resource_action_id)`
- `INDEX(effect)`

Rule:

- `resource_action_id` phải thuộc cùng Resource với `policies.resource_id`.

## 13.3 Authorization evaluation

Effective base permission là union của:

1. `group_permissions` của các active Shield groups mà User đang là member.
2. Direct permission trong Shield `auth_permissions_users` map hợp lệ vào `resource.action` catalog.

Không có Base Permission cho `Resource + Action` thì DENY ngay; PBAC không tạo permission mới.

### 13.3.1 Authorization lane và KQ denied set

Runtime build các lane:

- Actor lane: `USER` + `GROUP` participant phù hợp.
- Assignment lane: mỗi Employee Assignment effective phù hợp, dùng `DEPARTMENT` + `POSITION`.
- `ORGANIZATION` participant là contextual restriction theo Organization scope/inheritance.

Trong từng lane, sau khi load Policy tại current scope + ancestors, áp dụng QT1:

```text
ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH
NO_MATCH => ALLOW
```

Với mỗi `Resource + Action` có Base Permission:
- lane result `DENY` hoặc `ABSOLUTE_DENY` => action thuộc `KQ[lane]`;
- lane result `ALLOW` hoặc `NO_MATCH` => action không thuộc `KQ[lane]`.

### 13.3.2 Organization scope path, Assignment eligibility và inheritance-before-intersection

```text
AUTHORIZE(User, Resource, Action, EvaluationOrganization O, State, Time):

    if no base permission(Resource, Action):
        DENY

    scope_path = GROUP root -> ... -> O

    common_context_policies[O] =
        ORGANIZATION participant policies over scope_path

    KQ[user, O] =
        QT1(actor-level USER/GROUP policies
            + common_context_policies[O]
            over scope_path)

    eligible_assignments =
        effective employee_assignments where
        organization_id = O
        OR organization_id is ancestor of O on scope_path

    FOR EACH eligible Assignment A:
        KQ[A, O] =
            QT1(DEPARTMENT/POSITION policies
                + common_context_policies[O]
                over scope_path)

    mandatory_absolute_denied[O] =
        all applicable matched ABSOLUTE_DENY on scope_path

    inject mandatory_absolute_denied[O]
        into every participating KQ lane

    KQ[O] =
        INTERSECTION(
            KQ[user, O],
            all KQ[A, O]
        )

    decision = DENY if Resource+Action in KQ[O] else ALLOW

WHEN Company summary is required:
    KQ[COMPANY-summary] =
        INTERSECTION(all KQ[O] in that Company)
```

Rules:

- Evaluation Organization có thể là `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`.
- Employee Assignment tại `GROUP` được phép tham gia descendant context; Assignment tại Company/Branch/Plant chỉ áp dụng cho chính node và descendants trên subtree tương ứng.
- Assignment ở sibling Organization hoặc Company khác không tham gia.
- Primary Assignment chỉ resolve default context; Secondary/GROUP Assignment effective có thể tạo assignment lane khi đúng scope path.
- `ORGANIZATION` participant policies applicable trên scope path là common contextual policies và phải được đưa vào mọi lane trước QT1.
- Ordinary `DENY` có thể được mở bởi lane khác có quyền cao hơn vì merge dùng intersection của denied sets.
- Một ordinary permission chỉ còn bị cấm tại Organization nếu nó nằm trong mọi KQ lane đang tham gia.
- Applicable `ABSOLUTE_DENY` không được phép mất qua intersection; service phải propagate nó vào mọi KQ lane chịu scope trước khi merge.
- Group-level administrative Resource có thể evaluate trực tiếp tại `GROUP`; Company partition chỉ áp dụng khi operation/data scope thuộc Company.
- Khi cần Company summary, Company A và Company B được tính độc lập.
- Không cần persistence mới cho KQ/lane; đây là runtime authorization calculation.

# 14. Authorization Audit Domain

## 14.1 `authorization_logs`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| user_id | BIGINT | No | Logical Shield user ref |
| organization_id | BIGINT | Yes | Evaluated Organization Context |
| resource_id | BIGINT | No | FK -> resources.id |
| action | VARCHAR(50) | No | Action code |
| state_id | BIGINT | Yes | FK -> workflow_states.id |
| decision | VARCHAR(30) | No | `ALLOW`/`DENY` |
| matched_policy_id | BIGINT | Yes | FK -> policies.id; decisive policy when applicable |
| reason | TEXT | Yes | Explainable decision |
| request_id | VARCHAR(100) | Yes | Correlation id |
| created_at | DATETIME | No |  |

Indexes:

- `PRIMARY KEY(id)`
- `INDEX(user_id, created_at)`
- `INDEX(organization_id, created_at)`
- `INDEX(resource_id, action, created_at)`
- `INDEX(request_id)`
- `INDEX(decision)`

Required P1 persisted authorization audit scope tối thiểu:

- Approve.
- Delete.
- State change.

View/List không bắt buộc persisted log.

# 15. Product Domain

### 15.0 Canonical table naming

Product Domain P1 sử dụng các tên bảng canonical sau:

- `products`
- `product_groups`
- `product_uoms`
- `product_uom_conversions`
- `product_combo_items`
- `price_lists`
- `product_price_list_items`

Không sử dụng lại các tên generic/legacy `uoms`, `foundation_product_combo_items`, `price_list_items` hoặc bảng draft `product_price` trong migration/schema canonical mới.

## 15.1 Product Model V2 principles

Product Domain dùng một entity duy nhất: `products`.

Không có table/entity `variants`, `product_variants`, `parent_products` hoặc `child_products`. Mọi Product/SKU là một Product. Quan hệ cha/con chỉ được biểu diễn bằng `products.parent_product_id`.

```text
STANDARD root + no children  -> SIMPLE
STANDARD root + children     -> PARENT
STANDARD + parent_id         -> CHILD
COMBO                         -> COMBO
```

Operational role:

```text
SIMPLE  -> selectable input + leaf transactionable
CHILD   -> selectable input + leaf transactionable
COMBO   -> selectable input + composition/template; must flatten to leaf
PARENT  -> NOT selectable + NOT transactionable
```

`COMBO` có thể được chọn trực tiếp trên giao diện/chứng từ để thao tác nhanh và có selling price riêng, nhưng không phải inventory/serial/costing/posting leaf. Trước các nghiệp vụ downstream cần quantity/stock/serial/costing/posting, COMBO phải được expand/flatten thành SIMPLE/CHILD.

Role là derived value của domain service, không persistence trong database.

### 15.1.1 Product name rule

- Root `STANDARD` không có parent và `COMBO` lưu tên tại `products.product_name`.
- `CHILD` lưu phần tên riêng tại `products.product_child_name`.
- `CHILD.product_name` phải `NULL` để không lặp tên Parent.
- `product_child_name` chỉ dùng cho CHILD; các Product không phải CHILD phải `NULL`.
- Full/display name không lưu thành column riêng.

Tên đầy đủ:

```text
SIMPLE/PARENT/COMBO:
    full_name = product_name

CHILD:
    full_name = parent.product_name + " " + child.product_child_name
```

Nếu business cần dấu phân cách khác, formatter thuộc application layer; database không persistence một `full_name` thứ hai.

## 15.2 `product_groups`

Product Group là configurable shared Group master dùng adjacency-list hierarchy nhiều cấp.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| parent_id | BIGINT | Yes | Self FK -> product_groups.id; NULL là root |
| code | VARCHAR(50) | No | Unique toàn business Group |
| name | VARCHAR(255) | No | |
| description | TEXT | Yes | |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(parent_id)`
- `INDEX(status)`
- `FOREIGN KEY(parent_id) REFERENCES product_groups(id)` với restrictive/default semantics, không cascade delete.

Service-enforced invariants:

- `parent_id = NULL` là root; parent non-null phải tồn tại;
- parent không được là chính node;
- hierarchy adjacency-list có arbitrary depth và không có cycle trực tiếp/gián tiếp;
- reparent phải cycle-safe;
- Product có thể reference Product Group ở mọi cấp; Product mới chỉ chọn được node có effective ACTIVE ancestor path;
- đổi status không cascade descendants và không làm mất historical Product reference.

Không có `level`, `depth`, `path`, `materialized_path`, nested-set left/right, closure table, `organization_id`, `company_id` hoặc Product Company Usage Scope.

## 15.3 `product_uoms`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| code | VARCHAR(30) | No | Unique |
| name | VARCHAR(100) | No | |
| description | VARCHAR(255) | Yes | |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(status)`

## 15.4 `products`

Product là Group Master dùng chung cho toàn bộ Company. Variant không tồn tại như business entity riêng.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| code | VARCHAR(50) | No | Unique toàn Group |
| sku | VARCHAR(100) | Yes | Unique khi khai báo; required cho SIMPLE/CHILD/COMBO selectable Product |
| barcode | VARCHAR(100) | Yes | Unique khi khai báo |
| parent_product_id | BIGINT | Yes | Self FK -> products.id |
| product_name | VARCHAR(255) | Yes | Required cho root STANDARD và COMBO; NULL cho CHILD |
| product_child_name | VARCHAR(255) | Yes | Required cho CHILD; NULL cho non-CHILD |
| product_group_id | BIGINT | Yes | FK -> product_groups.id |
| product_type | VARCHAR(20) | No | `STANDARD` or `COMBO` |
| base_uom_id | BIGINT | Yes | FK -> product_uoms.id; required cho SIMPLE/CHILD/COMBO; PARENT may be NULL |
| price | DECIMAL(18,2) | Yes | Default selling price của Base UOM; required cho SIMPLE/CHILD/COMBO; PARENT = NULL; VND |
| net_weight_g | DECIMAL(18,3) | Yes | Net weight của Base UOM |
| package_gross_weight_g | DECIMAL(18,3) | Yes | Gross packaged weight của Base UOM |
| package_length_mm | DECIMAL(18,3) | Yes | Base-UOM package length |
| package_width_mm | DECIMAL(18,3) | Yes | Base-UOM package width |
| package_height_mm | DECIMAL(18,3) | Yes | Base-UOM package height |
| description | TEXT | Yes | |
| manual_standard_cost | DECIMAL(18,2) | Yes | >= 0; VND; later BOM costing |
| serial_management_enabled | BOOLEAN | No | Default false |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints/indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `UNIQUE(sku)`; multiple NULL allowed
- `UNIQUE(barcode)`; multiple NULL allowed
- `FOREIGN KEY(parent_product_id) REFERENCES products(id)`
- `INDEX(parent_product_id)`
- `INDEX(product_group_id)`
- `INDEX(base_uom_id)`
- `INDEX(product_name)`
- `INDEX(product_child_name)`
- `INDEX(product_type)`
- `INDEX(status)`

Auto Numbering / transport identifier mapping cho Resource `product`:

```text
database/domain identifier field = products.code
API/import alias                = product_code
identifier_max_length           = 50
identifier_uniqueness_scope     = GLOBAL
```

`AutoNumberService.identifier` của Product phải được persistence vào `products.code`; `product_code` không phải một database column thứ hai.

### 15.4.1 Derived Product Role

Database không lưu `PARENT`, `CHILD` hoặc `SIMPLE` trong `product_type`.

Derived role:

- `COMBO`: `product_type = COMBO`.
- `CHILD`: `product_type = STANDARD AND parent_product_id IS NOT NULL`.
- `PARENT`: `product_type = STANDARD AND parent_product_id IS NULL AND EXISTS(child record)`.
- `SIMPLE`: `product_type = STANDARD AND parent_product_id IS NULL AND NOT EXISTS(child record)`.

Application dùng một domain helper/service thống nhất, ví dụ `ProductRoleService`, để cung cấp tối thiểu:

- `getRole()`
- `isParent()`
- `isChild()`
- `isSimple()`
- `isCombo()`
- `isSelectable()`
- `isLeafTransactionable()`
- `requiresComboExpansion()`
- `getFullName()`

Không module nào được tự phát minh logic role khác với rule này. Generic helper tên `isTransactionable()` nếu còn tồn tại phải được định nghĩa rõ là leaf-transactionable và không được trả `true` cho COMBO để tránh nhầm giữa "selectable" và "operational leaf".

### 15.4.2 Parent/Child invariants

Parent candidate phải:

- `product_type = STANDARD`.
- `parent_product_id IS NULL`.
- Không phải COMBO.

Child phải:

- `product_type = STANDARD`.
- `parent_product_id IS NOT NULL`.
- Parent target phải là root STANDARD.
- Không được có Child riêng.
- Không được chuyển thành COMBO.

COMBO phải:

- `product_type = COMBO`.
- `parent_product_id IS NULL`.
- Không được làm Parent hoặc Child.

Hierarchy depth tối đa bằng 1. Service phải reject self-parenting, cycle, parent-to-child nesting và COMBO parent.

### 15.4.3 Selection, leaf transaction và mutation rules

Selectable input:

- SIMPLE.
- CHILD.
- COMBO.

Leaf transactionable:

- SIMPLE.
- CHILD.

Composition/template:

- COMBO.

Không selectable / không transactionable:

- PARENT.

PARENT không được dùng cho purchase, sales, inventory receipt/issue/balance, price-list item hoặc các transaction line khác.

COMBO được phép xuất hiện ở bước nhập liệu/chọn nhanh của Sales/Purchase và các workflow tương tự, nhưng trước khi downstream nghiệp vụ tính Inventory, Reservation, Serial, Costing hoặc Posting thì COMBO phải được flatten hoàn toàn thành SIMPLE/CHILD. COMBO không được giữ On-hand, Reserved hoặc Serial riêng.

`sku`, `base_uom_id` và `price` bắt buộc ở application validation đối với SIMPLE/CHILD/COMBO selectable Product. `price >= 0`. PARENT phải có `price = NULL`; Base UOM/packaging của PARENT có thể được làm trống theo conversion operation được phê duyệt.

COMBO bắt buộc có `base_uom_id` và `price`, nhưng không được có row trong `product_uom_conversions`. COMBO chỉ có một UOM pricing trực tiếp là Base UOM của chính nó.

Một STANDARD root đang là SIMPLE chỉ được tạo Child đầu tiên và trở thành PARENT khi chưa có transaction history. Operation SIMPLE -> PARENT phải atomic và reject khi còn dependency không tương thích, tối thiểu gồm:

- Transaction history.
- `product_price_list_items` tham chiếu Product đó.
- `product_uom_conversions` còn tồn tại.
- Product đang được dùng làm `component_product_id` trong `product_combo_items`.

Service không được tự xóa dependency để ép chuyển role. Sau khi conversion thành PARENT thành công, record phải thỏa toàn bộ invariant của PARENT.

Sau khi Product đã có transaction history, các field semantic sau không được thay đổi theo cách làm sai lịch sử:

- `base_uom_id`.
- `parent_product_id`.
- `product_type`.
- `serial_management_enabled`.

Nếu business cần thay đổi các field trên sau khi có transaction, phải có nghiệp vụ/change design riêng thay vì update trực tiếp.

### 15.4.4 Serial rules

- `serial_management_enabled` chỉ được `true` cho leaf transactionable STANDARD Product, tức SIMPLE hoặc CHILD.
- PARENT không quản lý Serial.
- COMBO bắt buộc `serial_management_enabled = false`; COMBO không có Serial riêng.
- Leaf component của COMBO vẫn có thể là Product serial-managed.
- Nested COMBO không làm thay đổi Serial semantics: sau flatten, Serial chỉ áp dụng cho SIMPLE/CHILD leaf.
- P1 chưa tạo serial instance table.

## 15.5 `product_uom_conversions`

Alternate UOM luôn quy đổi trực tiếp về Base UOM; không hỗ trợ conversion chain. Mỗi Alternate UOM có thể có default selling price riêng, độc lập với `conversion_factor`.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| product_id | BIGINT | No | FK -> products.id |
| uom_id | BIGINT | No | FK -> product_uoms.id; alternate UOM |
| conversion_factor | DECIMAL(18,6) | No | Base-UOM quantity contained in 1 alternate UOM |
| price | DECIMAL(18,2) | Yes | Default selling price của Alternate UOM; VND |
| package_gross_weight_g | DECIMAL(18,3) | Yes | Packaged gross weight for this alternate UOM |
| package_length_mm | DECIMAL(18,3) | Yes | Package length |
| package_width_mm | DECIMAL(18,3) | Yes | Package width |
| package_height_mm | DECIMAL(18,3) | Yes | Package height |
| usage_type | VARCHAR(20) | No | `PURCHASE`, `SALES`, `BOTH`; default `BOTH` |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints/indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(product_id, uom_id)`
- `INDEX(uom_id)`
- `INDEX(status)`

Rules:

- `conversion_factor > 0`.
- `uom_id` không được bằng `products.base_uom_id`.
- Base UOM có implicit factor `1`; không tạo conversion row cho Base UOM.
- Mọi Alternate UOM convert trực tiếp sang Base UOM.
- Không tạo relation Alternate -> Alternate và không hỗ trợ chain `PALLET -> CARTON -> BOX -> PCS`.
- `price` là giá cụ thể của Alternate UOM, không tự tính từ `products.price * conversion_factor`.
- `usage_type IN (SALES, BOTH)` => `price` bắt buộc và `price >= 0`.
- `usage_type = PURCHASE` => `price` có thể NULL; nếu có thì phải `>= 0`.
- Không lưu `net_weight_g` riêng ở Alternate UOM; net weight được suy ra từ Base Product net weight nhân `conversion_factor`.
- Packaging gross weight và dimensions của Alternate UOM được lưu trực tiếp ở row conversion.
- Chỉ SIMPLE/CHILD mới được cấu hình Alternate UOM. COMBO và PARENT không được có row trong `product_uom_conversions`.

## 15.6 `product_combo_items`

COMBO là Product composition/template dùng để thao tác nhanh. Thành phần được lưu normalized, không lưu JSON. Bảng này thuộc Foundation Product Domain P1 và không phải BOM/Manufacturing P3. Nested COMBO được phép, nhưng mọi operational transaction phải flatten về SIMPLE/CHILD.

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| combo_product_id | BIGINT | No | FK -> products.id; owner must be COMBO |
| component_product_id | BIGINT | No | FK -> products.id; SIMPLE, CHILD or COMBO; never PARENT |
| quantity | DECIMAL(18,6) | No | Quantity in component Base UOM; > 0 |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints/indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(combo_product_id, component_product_id)`
- `INDEX(component_product_id)`

Rules:

- `combo_product_id` phải có `product_type = COMBO` và `parent_product_id IS NULL`.
- `component_product_id` được phép là SIMPLE, CHILD hoặc COMBO.
- Component không được là PARENT.
- `combo_product_id <> component_product_id`.
- Composition graph giữa các COMBO phải acyclic; service phải reject direct/indirect cycle như `A -> B -> A` hoặc `A -> B -> C -> A`.
- `quantity > 0`.
- Quantity luôn theo Base UOM của component; không lưu `uom_id` tại combo item. Với nested COMBO, quantity là số Base UOM của nested COMBO được chứa trong parent COMBO.
- Khi flatten, quantity của leaf bằng tích quantity dọc path từ COMBO root tới leaf; nếu cùng leaf xuất hiện qua nhiều path thì quantity được aggregate.
- Giá/default price/Price List của nested COMBO không được dùng khi nó chỉ là component của COMBO khác; nested COMBO chỉ đóng vai trò composition node.
- Không có `variant_id` hoặc `child_variant_id`; chỉ có `component_product_id`.

## 15.7 Selector semantics handed to application

Selector/query helper phải dựa trên derived Product Role:

- Input selector cho thao tác/chứng từ: SIMPLE, CHILD, COMBO.
- Final leaf transaction selector sau flatten: SIMPLE, CHILD.
- Combo component selector: SIMPLE, CHILD, COMBO.
- Parent selector: root STANDARD Product đủ điều kiện tạo Child.

Selector không dùng khái niệm Variant. PARENT không được chọn làm Combo component hoặc operational transaction item.

# 16. Core Pricing Domain

## 16.1 Pricing model

P1 pricing được resolve bằng input:

```text
Product + UOM + Company + Effective Time
```

Pricing scope dùng Organization Context:

- GROUP Pricing: `price_lists.organization_id` trỏ GROUP root.
- COMPANY Pricing: `price_lists.organization_id` trỏ COMPANY.

P1 không tạo generic pricing condition table và không đưa Customer/Customer Group/Sales Channel vào candidate key.

Price List item luôn định giá cụ thể cho một `Product + UOM`. Với SIMPLE/CHILD, UOM có thể là Base UOM hoặc Alternate UOM hợp lệ. Với COMBO, Price List chỉ được định giá tại Base UOM vì COMBO không có `product_uom_conversions`. Giá của từng UOM độc lập; hệ thống không bắt buộc suy giá Alternate UOM từ Base UOM bằng `conversion_factor`.

## 16.2 `price_lists`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| code | VARCHAR(50) | No | Unique toàn hệ thống |
| name | VARCHAR(255) | No | |
| organization_id | BIGINT | No | FK -> organizations.id; only GROUP/COMPANY |
| effective_from | DATETIME | No | Inclusive |
| effective_to | DATETIME | Yes | Exclusive; NULL = open-ended |
| priority | INT | No | Default 0 |
| status | VARCHAR(20) | No | `ACTIVE`/`INACTIVE` |
| description | TEXT | Yes | |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints/indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(code)`
- `INDEX(organization_id, status, effective_from, effective_to)`
- `INDEX(priority)`

Rules:

- `organization_id` chỉ được trỏ tới Organization type `GROUP` hoặc `COMPANY`.
- Effective interval canonical: `effective_from <= evaluation_time < effective_to`.
- Nếu `effective_to IS NULL`, interval không có upper bound.
- `effective_to > effective_from` nếu `effective_to` có giá trị.
- Overlap effective period được phép.

## 16.3 `product_price_list_items`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| price_list_id | BIGINT | No | FK -> price_lists.id |
| product_id | BIGINT | No | FK -> products.id |
| uom_id | BIGINT | No | FK -> product_uoms.id |
| price | DECIMAL(18,2) | No | Exact selling price cho Product + UOM; >= 0; VND |
| created_by | BIGINT | Yes | Logical Shield user ref; nullable for seed/system migration |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Constraints:

- `PRIMARY KEY(id)`
- `UNIQUE(price_list_id, product_id, uom_id)`
- `INDEX(product_id, uom_id)`
- `INDEX(uom_id)`

Rules:

- `product_id` chỉ được trỏ tới SIMPLE, CHILD hoặc COMBO; không được trỏ PARENT.
- Với SIMPLE/CHILD, `uom_id` phải là `products.base_uom_id` hoặc một Alternate UOM tồn tại trong `product_uom_conversions` của chính Product.
- Với COMBO, `uom_id` bắt buộc bằng `products.base_uom_id`; COMBO không có Alternate UOM/`product_uom_conversions`.
- Nếu `uom_id` là Alternate UOM của SIMPLE/CHILD dùng cho selling pricing, conversion phải có `usage_type = SALES` hoặc `BOTH` và còn active.
- `price` là giá cụ thể của UOM đó; không derive từ Base price hoặc `conversion_factor`.
- Price List của COMBO chỉ được resolve khi COMBO đó được chọn trực tiếp; nếu COMBO chỉ là nested component thì giá của nó không tham gia allocation của parent COMBO.

## 16.4 Price Resolution Algorithm

Input:

```text
product_id
uom_id
company_organization_id
evaluation_time
```

Precondition:

- `company_organization_id` phải là Organization type COMPANY.
- Company phải resolve được về GROUP root business scope.
- Product phải active và là SIMPLE, CHILD hoặc COMBO; PARENT không hợp lệ.
- Với SIMPLE/CHILD, `uom_id` phải là Base UOM hoặc Alternate UOM sales-capable hợp lệ của Product.
- Với COMBO, `uom_id` bắt buộc bằng `products.base_uom_id`.

Candidate Price List active tại `evaluation_time` khi:

```text
status = ACTIVE
AND effective_from <= evaluation_time
AND (effective_to IS NULL OR evaluation_time < effective_to)
```

Algorithm:

```text
1. Tìm Company Price List candidate:
   - organization_id = company_organization_id
   - active tại evaluation_time
   - có product_price_list_items matching đúng product_id + uom_id

2. Nếu có Company candidate:
   ORDER BY priority DESC,
            effective_from DESC,
            id ASC
   lấy candidate đầu tiên.

3. Nếu không có matching Company candidate,
   kể cả khi Company có Price List khác nhưng thiếu item Product + UOM:
   resolve GROUP root và tìm Group candidate theo cùng rule.

4. Nếu có Group candidate:
   ORDER BY priority DESC,
            effective_from DESC,
            id ASC
   lấy candidate đầu tiên.

5. Nếu không có matching Group candidate:
   - nếu Product là COMBO: dùng products.price;
   - nếu SIMPLE/CHILD và uom_id = products.base_uom_id: dùng products.price;
   - nếu SIMPLE/CHILD và là Alternate UOM: dùng product_uom_conversions.price của đúng Product + UOM.
```

Default price rules:

- `products.price` bắt buộc đối với SIMPLE, CHILD và COMBO.
- COMBO chỉ có default selling price tại Base UOM và không có Alternate UOM.
- Alternate UOM `SALES`/`BOTH` của SIMPLE/CHILD bắt buộc có `product_uom_conversions.price`.
- Alternate UOM `PURCHASE` của SIMPLE/CHILD có thể không có price và không dùng làm selling-price default khi price NULL.

Price Source runtime:

- `PRICE_LIST_COMPANY`
- `PRICE_LIST_GROUP`
- `PRODUCT_DEFAULT`

Kết quả resolution nên trả tối thiểu final price, `uom_id`, Price Source và `price_list_id` khi source là Price List. P1 database không persistence riêng Price Resolution result; transaction table phase sau phải snapshot final price/source/UOM khi nghiệp vụ yêu cầu.

## 16.5 COMBO Expansion, Price Allocation và Weighted Aggregation

COMBO được chọn trực tiếp là pricing root. Hệ thống resolve selling price của root COMBO đúng một lần theo Section 16.4, sau đó flatten composition tree xuống SIMPLE/CHILD. Nested COMBO không được resolve giá lại.

### 16.5.1 Flatten quantity

Với một root COMBO được chọn với quantity `Q_root`, quantity leaf được tính bằng tích quantity trên mọi path từ root tới leaf. Nếu cùng leaf xuất hiện nhiều path, aggregate quantity trước allocation.

Ví dụ:

```text
COMBO A x2
  -> X x1
  -> COMBO B x3
       -> X x2
       -> Y x4

Flatten:
X = 2 * 1 + 2 * 3 * 2 = 14
Y = 2 * 3 * 4         = 24
```

Canonical leaf aggregation key ở Product Domain tối thiểu là `product_id + base_uom_id`. Transaction module phase sau có thể bổ sung Company/Warehouse/Tax hoặc dimension khác khi business semantics yêu cầu tách line.

### 16.5.2 Allocation weight

Allocation weight của mỗi leaf dùng default Base-UOM price của chính leaf, không dùng Price List của leaf:

```text
leaf_weight = leaf_quantity * leaf.products.price
total_weight = SUM(leaf_weight)
```

Rules:

- Leaf phải là SIMPLE hoặc CHILD.
- `leaf.products.price` là default price của Base UOM.
- Price List Company/Group của leaf không tham gia tính weight.
- Giá/default price/Price List của nested COMBO không tham gia tính weight.
- `total_weight <= 0` là validation error; hệ thống không tự chia đều allocation.

### 16.5.3 Allocate COMBO selling amount

Với root COMBO selling unit price đã resolve là `combo_unit_price`:

```text
combo_total_amount = combo_unit_price * Q_root
raw_leaf_amount    = combo_total_amount * leaf_weight / total_weight
```

Calculation phải dùng precision cao hơn monetary storage trong các bước trung gian. Final monetary leaf amount được round về `DECIMAL(18,2)`. Sau rounding, residual phải được điều chỉnh deterministic để bảo đảm invariant:

```text
SUM(final_leaf_allocated_amount) = combo_total_amount
```

Canonical deterministic rounding order là các leaf đã aggregate sắp theo `product_id ASC`; residual cuối cùng được cộng/trừ vào leaf cuối cùng trong thứ tự đó.

### 16.5.4 Aggregate cùng leaf từ nhiều COMBO trong một transaction

Nếu cùng một leaf Product nhận nhiều allocated price do xuất hiện trong nhiều COMBO của cùng transaction, không lấy trung bình đơn giản của unit price. Hệ thống aggregate từ quantity và amount:

```text
final_quantity   = SUM(leaf_occurrence_quantity)
final_amount     = SUM(leaf_occurrence_allocated_amount)
final_unit_price = final_amount / final_quantity
```

Công thức trên tương đương weighted average của occurrence unit price với trọng số là quantity. Không được tính `final_unit_price` từ các occurrence unit price đã rounded nếu cách đó làm sai `final_amount`. Invariant ưu tiên là tổng amount sau aggregate phải bằng tổng allocated amount của các COMBO nguồn.

### 16.5.5 Downstream operational boundary

Sau khi flatten/allocation/aggregation hoàn tất, các nghiệp vụ Inventory, Reservation, Serial, Costing, Posting, Return và trace quantity phải vận hành trên SIMPLE/CHILD leaf. COMBO không tạo stock balance, serial instance hoặc cost layer riêng. Transaction design phase sau phải giữ đủ traceability để biết leaf line được sinh từ COMBO nào khi business/audit yêu cầu, nhưng P1 Product schema không tạo transaction table cho trace này.

# 17. Import Framework Domain

## 17.1 `import_jobs`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| entity_type | VARCHAR(50) | No | P1 enabled baseline: `PRODUCT`; additional target requires approved contract change |
| mode | VARCHAR(20) | No | `CREATE_ONLY`/`UPDATE_ONLY`/`UPSERT` |
| source_format | VARCHAR(20) | No | CSV/XLSX |
| source_file_name | VARCHAR(255) | Yes | Original filename |
| source_file_hash | VARCHAR(128) | Yes | Optional integrity/idempotency support |
| status | VARCHAR(30) | No | Job status |
| total_rows | INT | No | Default 0 |
| success_rows | INT | No | Default 0 |
| error_rows | INT | No | Default 0 |
| created_by | BIGINT | Yes | Logical Shield user ref |
| updated_by | BIGINT | Yes | Logical Shield user ref |
| started_at | DATETIME | Yes | |
| completed_at | DATETIME | Yes | |
| created_at | DATETIME | No | |
| updated_at | DATETIME | Yes | |

Recommended job status values:

- `PENDING`
- `PROCESSING`
- `COMPLETED`
- `COMPLETED_WITH_ERRORS`
- `FAILED`

Indexes:

- `PRIMARY KEY(id)`
- `INDEX(entity_type, created_at)`
- `INDEX(mode, created_at)`
- `INDEX(status, created_at)`
- `INDEX(created_by, created_at)`
- `INDEX(source_file_hash)`

## 17.2 `import_job_rows`

| Column | Type | Null | Rule |
|---|---|---:|---|
| id | BIGINT | No | PK |
| import_job_id | BIGINT | No | FK -> import_jobs.id |
| row_number | INT | No | Source row number |
| row_status | VARCHAR(20) | No | `SUCCESS`/`ERROR`/`SKIPPED` |
| entity_key | VARCHAR(255) | Yes | e.g. product code / employee code |
| target_ref_id | BIGINT | Yes | Logical target record id according to import job entity_type; no physical FK |
| error_code | VARCHAR(100) | Yes | Application error code |
| error_message | TEXT | Yes | Human-readable validation/import error |
| created_at | DATETIME | No | |

Constraints/indexes:

- `PRIMARY KEY(id)`
- `UNIQUE(import_job_id, row_number)`
- `INDEX(import_job_id, row_status)`
- `INDEX(entity_key)`

Rules:

- Row ERROR không được coi là persisted business data thành công.
- Job counts phải reconcile với row results.
- `target_ref_id` là typed/logical reference theo `import_jobs.entity_type`; không tạo physical FK vì target table thay đổi theo entity type.
- Không bắt buộc lưu raw source row payload trong DB; đặc biệt không persistence dữ liệu nhạy cảm chỉ để phục vụ import log.
- Import service phải tuân cùng uniqueness/validation rule như API/UI mutation.
- `mode` là thuộc tính persisted của Job để audit/replay/debug; mode không làm bypass canonical mutation semantics. UPSERT chỉ mutation key có mặt trong file; absence không đồng nghĩa delete/inactivate.
- Product Import không phụ thuộc thứ tự dòng trong file. Product row có source-local `source_key` để dependency graph tham chiếu Product create mới trước khi generated `product_code` tồn tại. Service phải parse dependency graph và xử lý multi-pass/topological order để Parent được resolve trước Child và dependency COMBO được resolve trước owner COMBO khi cần. Nested COMBO phải có cycle detection; mọi direct/indirect cycle phải làm các row liên quan fail validation thay vì tạo composition graph không hợp lệ.
- Import không được silently reorder business meaning; row-level result vẫn phải giữ đúng `row_number` gốc của file.

# 18. Foreign Keys và polymorphic boundaries

## 18.1 Physical FK bắt buộc trong P1

Các quan hệ chuẩn phải có FK khi không phải polymorphic, ví dụ:

- wards.province_id -> provinces.id
- contacts.province_id -> provinces.id
- contacts.ward_id -> wards.id
- organizations.parent_id -> organizations.id
- organizations.province_id -> provinces.id
- organizations.ward_id -> wards.id
- departments.parent_id -> departments.id
- product_groups.parent_id -> product_groups.id
- employees.manager_employee_id -> employees.id
- employee_assignments.employee_id -> employees.id
- employee_assignments.organization_id -> organizations.id
- employee_assignments.department_id -> departments.id
- employee_assignments.position_id -> positions.id
- resources.lifecycle_id -> lifecycles.id
- resource_actions.resource_id -> resources.id
- auto_numbering_series.resource_id -> resources.id
- auto_numbering_counters.numbering_series_id -> auto_numbering_series.id
- auto_numbering_counters.scope_organization_id -> organizations.id
- group_permissions.group_id -> groups.id
- group_permissions.resource_action_id -> resource_actions.id
- lifecycle_states.lifecycle_id -> lifecycles.id
- lifecycle_states.state_id -> workflow_states.id
- lifecycle_transitions.lifecycle_id -> lifecycles.id
- lifecycle_transitions.from_state_id -> workflow_states.id
- lifecycle_transitions.to_state_id -> workflow_states.id
- policies.organization_id -> organizations.id
- policies.resource_id -> resources.id
- policies.state_id -> workflow_states.id
- policy_actions.policy_id -> policies.id
- policy_actions.resource_action_id -> resource_actions.id
- authorization_logs.resource_id -> resources.id
- authorization_logs.state_id -> workflow_states.id
- authorization_logs.matched_policy_id -> policies.id
- products.parent_product_id -> products.id
- products.product_group_id -> product_groups.id
- products.base_uom_id -> product_uoms.id
- product_uom_conversions.product_id -> products.id
- product_uom_conversions.uom_id -> product_uoms.id
- product_combo_items.combo_product_id -> products.id
- product_combo_items.component_product_id -> products.id
- price_lists.organization_id -> organizations.id
- product_price_list_items.price_list_id -> price_lists.id
- product_price_list_items.product_id -> products.id
- product_price_list_items.uom_id -> product_uoms.id
- import_job_rows.import_job_id -> import_jobs.id

## 18.2 Không tạo physical FK

Không tạo FK cho:

- contacts.contact_ref_id
- policies.participant_ref_id
- import_job_rows.target_ref_id
- Shield identity references khi physical Shield schema compatibility không được guaranteed bởi migration owner, gồm các audit `user_id`, `created_by`, `updated_by`

Các reference này được application validation.

# 19. Transaction boundaries

Các mutation sau phải dùng transaction:

- Tạo/sửa Employee Assignment + các kiểm tra Primary overlap liên quan.
- Tạo/sửa Policy + Policy Actions.
- Thay đổi Authorization Group permission matrix theo batch.
- Tạo/sửa Lifecycle cùng state/transition mapping theo batch.
- Tạo/sửa Product cùng Parent/Child relation, UOM conversions, default UOM pricing hoặc Combo Items khi mutation có nhiều bảng; update nested Combo composition phải validate cycle trong cùng transaction.
- Tạo/sửa Price List cùng Product+UOM items theo batch.
- Auto Number generation + create business record; counter update/candidate-skip không được commit tách khỏi business mutation.
- Thay đổi active/default Auto Numbering series của một Resource phải serialize để giữ invariant tối đa một active default series.
- Import từng row khi có nhiều table mutation; row lỗi phải rollback row đó.
- Import Job summary update phải nhất quán với row result.

Không được ghi partial policy/action, partial assignment hoặc partial import row success.

# 20. Validation boundaries

## 20.1 Database-enforced

Database chịu trách nhiệm tối thiểu:

- PK/FK.
- Unique code/key.
- Product code/SKU/barcode uniqueness.
- Product parent self-FK integrity.
- Combo item owner/component FK integrity and duplicate prevention.
- Group code uniqueness.
- Resource + Action uniqueness.
- Auto Numbering series code uniqueness theo `resource_id + series_code`.
- Auto Numbering counter uniqueness theo `numbering_series_id + scope_organization_id + period_key`.
- Lifecycle state uniqueness.
- Transition duplicate uniqueness.
- Price List code uniqueness.
- Price List Product+UOM duplicate prevention theo `UNIQUE(price_list_id, product_id, uom_id)`.
- Product-UOM conversion duplicate prevention.

## 20.2 Service-enforced

Service layer chịu trách nhiệm cho invariant không thể biểu diễn đơn giản bằng FK/unique constraint:

- Organization cycle và parent matrix.
- Một GROUP business root.
- Organization type restriction cho Pricing scope.
- Ward belongs to selected Province.
- Employee Primary Assignment temporal overlap.
- Employee direct-manager self-reference, cycle prevention và active-manager rule.
- Policy polymorphic participant validation.
- Authorization Company partition, eligible Assignment resolution, ancestor inheritance, QT1 per lane, `ABSOLUTE_DENY` propagation và denied-set intersection.
- Contact polymorphic owner validation.
- Policy Action Resource consistency.
- State membership trong Lifecycle Transition và uniqueness `lifecycle + from_state + action`.
- Shield group alias bridge validation.
- Shield direct permission mapping tới Resource Action catalog.
- Auto Numbering: tối đa một active default series/Resource, valid scope/generation/reset/pattern, business identifier uniqueness-namespace compatibility, authoritative scope Organization resolution, timezone-derived period key, occupied-candidate skipping, post-issuance immutability/no-hard-delete và no-reuse-after-commit semantics.
- Product Model V2 role/invariant validation: depth 1, root/child naming rule, selectable-vs-leaf semantics, Parent/Child/Combo restrictions và `COMBO.parent_product_id IS NULL`.
- Product Full Name derivation; không persistence duplicate `full_name`.
- Product UOM direct-to-base conversion rules và default price riêng theo từng UOM; `product_uom_conversions` chỉ áp dụng SIMPLE/CHILD, không áp dụng COMBO/PARENT.
- Combo owner/component role validation, nested COMBO acyclic graph, recursive flatten quantity và component quantity theo Base UOM.
- Price Resolution theo `Product + UOM + Company + Effective Time`, deterministic ordering, half-open effective interval và chỉ nhận Product/UOM hợp lệ; COMBO chỉ price theo Base UOM.
- COMBO price allocation theo leaf default-price weight, zero-weight rejection, deterministic rounding residual và quantity-weighted aggregation khi cùng leaf xuất hiện nhiều COMBO.
- Import business validations bằng multi-pass/dependency graph; không yêu cầu thứ tự Parent/Child/Combo trong file nguồn và phải detect nested Combo cycle.

# 21. Seed / bootstrap data

P1 deployment nên seed tối thiểu:

### 21.1 Workflow states

Các state dùng bởi module P1/approval baseline khi cần:

- DRAFT
- PENDING_APPROVAL
- APPROVED
- REJECTED
- CANCELLED

Các state khác chỉ seed khi resource P1 thực sự cần.

### 21.2 Resource actions

Action catalog không tồn tại như table chung; mỗi Resource có Resource Action riêng. Seeder tạo action theo approved Resource/Action contract, tối thiểu lưu ý:

- P1 chỉ seed `product.import` cho import target baseline; không seed `import` cho mọi master.
- `authorization_group` có `assign_member` và `assign_permission` tách biệt.
- `user` có `assign_permission` khi Mini ERP expose direct permission administration.
- `resource` có `configure_numbering` để quản trị Auto Numbering configuration; `resource.edit` không thay thế action này.

- Resource seeder phải persist đúng canonical domain matrix tại mục 11.1.1; không seed `COMPANY` như Resource Domain.
- Canonical Resource đã tồn tại không được đổi domain qua business Resource edit flow; reconciliation domain chỉ đi qua approved change-control migration/seed.

### 21.3 Authorization groups và security bootstrap

Seeder/bootstrap có thể khởi tạo Authorization Group hệ thống cần thiết. Group code phải đồng bộ với Shield group aliases.

- Phải có một privileged Authorization Group canonical được phê duyệt cho initial administration, ví dụ `SYSTEM_ADMIN`.
- Initial System Admin membership được provision qua CLI/deployment/bootstrap mechanism để tránh bootstrap deadlock; đây không phải runtime authorization bypass.
- Sau bootstrap, Group tồn tại và được quản trị như Authorization Group bình thường qua PBAC + `assign_member`/`assign_permission`.

### 21.4 Organization root

Business deployment khởi tạo một Organization Context type `GROUP` root trước khi tạo Company.

# 22. Migration dependency order

Tài liệu này chỉ khóa dependency/order; không khóa migration filename/number vật lý.

Recommended dependency order:

```text
A. Shield prerequisite migrations

B. Foundation
   provinces
   wards

C. Organization / Contact
   organizations
   contacts

D. Employee structure
   departments
   positions
   employees
   employee_assignments

E. Workflow
   lifecycles
   workflow_states
   lifecycle_states
   lifecycle_transitions

F. Resource / Authorization / Auto Numbering
   resources
   resource_actions
   auto_numbering_series
   auto_numbering_counters
   groups
   group_permissions
   policies
   policy_actions
   authorization_logs

G. Product / UOM / Combo Foundation
   product_groups
   product_uoms
   products
   product_uom_conversions
   product_combo_items

H. Pricing
   price_lists
   product_price_list_items

I. Import
   import_jobs
   import_job_rows
```

Nếu hệ thống hiện tại đã có các bảng baseline, migration P1 phải là forward-only additive/alter migration; không rewrite migration đã apply.

# 23. Existing-schema harmonization migrations

Nếu database đã tồn tại theo baseline cũ, P1 cần các migration harmonization tương đương:

1. Add `legal_name`, `tax_code`, `address`, `province_id`, `ward_id` vào `organizations`.
2. Add Employee P1 fields vào `employees`: `gender`, `date_of_birth`, `place_of_origin`, `hire_date`, `manager_employee_id`; mở rộng `employee_name` phù hợp nếu schema hiện tại ngắn hơn yêu cầu.
3. Add `code` vào `groups`, backfill canonical aliases, sau đó áp dụng unique constraint.
4. Cho phép `policies.state_id` NULL nếu schema hiện tại đang NOT NULL.
5. Normalize legacy Policy participant `ROLE` sang `GROUP` sau khi mapping sang `groups.id` được xác minh.
6. Tạo `lifecycle_transitions`.
7. Tạo Product Model V2/UOM tables: `product_groups`, `product_uoms`, `products`, `product_uom_conversions`, `product_combo_items`; `product_groups.parent_id` là nullable self-reference adjacency-list cho hierarchy arbitrary-depth và phải được bổ sung bằng forward migration nếu table cũ chưa có; `products.price` là Base-UOM default price cho SIMPLE/CHILD/COMBO, `product_uom_conversions.price` chỉ là Alternate-UOM default price cho SIMPLE/CHILD; COMBO không có UOM conversion; không tạo Variant table hoặc `product_price`.
8. Tạo Pricing tables: `price_lists`, `product_price_list_items`; item phải có `uom_id` và unique theo `price_list_id + product_id + uom_id`; COMBO chỉ được có Price List item tại Base UOM.
9. Tạo `auto_numbering_series` và `auto_numbering_counters`; bổ sung Resource Action `resource.configure_numbering`.
10. Tạo Import tables; `import_jobs` có `mode` persisted và P1 baseline chỉ enable entity type `PRODUCT`; Product importer hỗ trợ source-local key cho create row có generated Product Code.
11. Bổ sung `created_by`/`updated_by` cho mutable master/config P1 theo audit convention baseline hiện hành, bằng forward-only migration phù hợp với schema hiện hữu.
12. Normalize `resources.domain` theo `CHANGE-AUTH-DOMAIN-001`: `contact`/`price_list` từ legacy `COMPANY` -> `ORGANIZATION`; `employee_assignment` từ `MASTER` -> `ORGANIZATION`; `organization`/`policy`/`authorization_log`/`import_job` từ `SYSTEM` -> `ORGANIZATION`; giữ nguyên canonical `MASTER`/`SYSTEM` rows còn lại. Sau preflight xác nhận mọi P1 Resource đã phân loại explicit, enforce `resources.domain NOT NULL`; unknown/non-P1 Resource không được tự đoán domain.

Không xóa legacy role/permission tables trong cùng migration harmonization nếu chúng đã tồn tại và còn cần cho rollback/forensics; runtime P1 chỉ dùng schema canonical mới.

# 24. P1 authorization cache invalidation inputs

Authorization decision cache, nếu có, phải invalidated khi thay đổi:

- Shield group membership.
- Direct Shield user permission.
- groups.
- group_permissions.
- resource_actions.
- policies.
- policy_actions.
- employee_assignments.
- organizations hierarchy/status.
- positions/departments nếu chúng tham gia Policy Participant.

Cache không được làm bypass `ABSOLUTE_DENY`.

# 25. P1 data retention và delete behavior

- Organization inactive/closed không bị xóa khi đã được tham chiếu.
- Employee inactive không bị xóa để giữ lịch sử Assignment/Contact/Audit.
- Contact inactive/closed không bị xóa khỏi lịch sử.
- Product inactive không dùng cho giao dịch mới nhưng giữ identity/history. Parent/Child relation không được hard-delete nếu đã được tham chiếu.
- Product UOM/Product Group inactive không dùng cho cấu hình mới nếu validation yêu cầu.
- Price List hết effective period hoặc inactive không được chọn cho price resolution mới.
- Auto Numbering series đã có committed counter/issuance không được hard-delete; chỉ chuyển `INACTIVE`. Counter history/runtime state của series đó không bị xóa theo business operation thông thường.
- Authorization logs là evidence; delete/retention policy production cần operational approval riêng.
- Import job history được giữ theo retention policy vận hành sẽ được xác định sau; P1 schema không tự hard-delete.

# 26. P1 ERD rút gọn

```text
provinces 1 --- N wards
provinces 1 --- N contacts
wards     1 --- N contacts

organizations 1 --- N organizations
organizations 1 --- N employee_assignments
organizations 1 --- N price_lists

employees 1 --- N employee_assignments
employees 1 --- N employees (manager)
departments 1 --- N employee_assignments
positions   1 --- N employee_assignments

lifecycles 1 --- N lifecycle_states N --- 1 workflow_states
lifecycles 1 --- N lifecycle_transitions

lifecycles 1 --- N resources
resources  1 --- N resource_actions
resources  1 --- N auto_numbering_series 1 --- N auto_numbering_counters
organizations 1 --- N auto_numbering_counters (scope_organization_id)

groups N --- N resource_actions via group_permissions

organizations 1 --- N policies
resources     1 --- N policies
workflow_states 1 --- N policies (nullable scope)
policies 1 --- N policy_actions N --- 1 resource_actions

product_groups 1 --- N product_groups (parent_id)
product_groups 1 --- N products
product_uoms 1 --- N products (base_uom)
products 1 --- N products (parent_product_id; max depth 1)
products N --- N product_uoms via product_uom_conversions
products 1 --- N product_combo_items (as COMBO owner)
products 1 --- N product_combo_items (as component; SIMPLE/CHILD/COMBO, recursive COMBO composition must be acyclic)

price_lists 1 --- N product_price_list_items N --- 1 products
product_uoms 1 --- N product_price_list_items

import_jobs 1 --- N import_job_rows
```

# 27. Functional Requirement handoff rules

FRS P1 và mọi implementation downstream phải tuân các rule sau:

1. Không tạo persisted Profile table.
2. Không tạo Permission table riêng.
3. Không tạo Product Company Usage Scope.
4. Không tạo Variant entity/table; mọi Product/SKU là row trong `products`.
5. Product role phải derive từ `product_type`, `parent_product_id` và children; không lưu `SIMPLE`/`PARENT`/`CHILD`.
6. CHILD full name = Parent `product_name` + Child `product_child_name`; không persistence `full_name`.
7. SIMPLE/CHILD là leaf transactionable; COMBO là selectable composition/template phải flatten về leaf; PARENT không selectable/transactionable.
8. COMBO có `base_uom_id`, `products.price` và được phép có `product_price_list_items` tại Base UOM; COMBO bắt buộc không Serial và không `product_uom_conversions`.
9. UOM conversion của SIMPLE/CHILD luôn direct-to-base.
10. Base-UOM default selling price dùng `products.price`; Alternate-UOM default selling price của SIMPLE/CHILD dùng `product_uom_conversions.price`; không tạo bảng `product_price`.
11. Giá Alternate UOM là giá cụ thể, không tự suy ra bằng `products.price * conversion_factor`.
12. COMBO components dùng `product_combo_items`; component được phép SIMPLE/CHILD/COMBO, không được PARENT; nested COMBO phải acyclic.
13. Quantity component luôn theo component Base UOM; nested quantity được nhân dọc path và leaf quantity được aggregate sau flatten.
14. Khi COMBO root được chọn trực tiếp, resolve giá root một lần; không resolve lại giá/default price/Price List của nested COMBO.
15. COMBO selling amount được phân bổ xuống SIMPLE/CHILD theo weight `leaf quantity × leaf products.price`; Price List của leaf không dùng làm allocation weight.
16. Nếu tổng allocation weight bằng 0 thì reject; không tự chia đều.
17. Allocation phải bảo đảm sau rounding `SUM(leaf allocated amount) = COMBO total selling amount`; residual dùng deterministic ordering.
18. Cùng leaf nhận nhiều allocated price từ nhiều COMBO trong một transaction phải aggregate bằng `SUM(quantity)` và `SUM(amount)`; final unit price = total amount / total quantity, tức weighted average theo quantity.
19. Không thay `GROUP` Policy participant bằng `ROLE` trong thiết kế mới.
19a. Primary Assignment chỉ resolve default actor Organization Context; PBAC DEPARTMENT/POSITION phải hỗ trợ mọi effective Assignment đúng exact ancestor path, bao gồm Assignment tại `GROUP` khi evaluate descendant.
19b. Authorization phải resolve scope path từ `GROUP`, inheritance trước merge, áp dụng QT1 trong từng lane, propagate applicable `ABSOLUTE_DENY`, sau đó intersection KQ Organization; Company-scoped operation phải partition độc lập theo Company và chỉ tính KQ Company summary khi chức năng cần.
19c. System Admin không có bypass; phải đi qua Shield User -> Employee -> GROUP Assignment + Base Permission + PBAC như mọi User khác. Initial privileged membership chỉ được provision qua bootstrap/deployment mechanism.
19d. Mọi securable data/capability phải map Resource Type; User/Authorization Group/Resource/Policy/Organization cũng là Resource. Không tạo Resource Instance Authorization.
19e. Resource Domain canonical gồm `SYSTEM`/`MASTER`/`ORGANIZATION`: `SYSTEM`/`MASTER` evaluate tại `GROUP`; `ORGANIZATION` derive authoritative Evaluation Organization từ target/owner/parent/persisted context. `organization.create` evaluate tại Parent Organization; Employee Assignment mutation evaluate tại Assignment Organization; client context/Primary Assignment không override hoặc fallback domain-derived scope; missing/unknown/unresolved context fail closed.
19f. Security assignment action phải tách `authorization_group.assign_member`, `authorization_group.assign_permission`, `user.assign_permission`; không dùng generic `assign` cho cả membership và permission matrix.
19g. Company business timezone lấy từ CodeIgniter Shield/Settings integration; P1 ERP schema không thêm timezone persistence riêng từ convention này.
20. Không đưa Customer/Customer Group/Sales Channel vào Pricing P1.
21. Pricing P1 input canonical là `Product + UOM + Company + Effective Time`; COMBO chỉ cho Base UOM.
22. Pricing fallback canonical là `Company matching Product+UOM -> Group matching Product+UOM -> default price đúng UOM`.
23. Price List item unique theo `price_list_id + product_id + uom_id`.
24. Price List effective period dùng `[effective_from, effective_to)`; `effective_to = NULL` là open-ended.
25. Không thêm arbitrary PBAC condition.
26. Không thêm Resource Instance Authorization.
27. Workflow transition phải sử dụng `lifecycle_transitions`; một `lifecycle + from_state + action` chỉ resolve một destination; Authorization vẫn tách biệt.
28. Employee Organization/Department/Position relation chỉ qua `employee_assignments`.
29. Contact owner vẫn polymorphic qua `contact_type + contact_ref_id`.
30. Direct manager phải tránh self-reference/cycle; Employee inactive không được chọn làm manager mới.
31. Mutable master/config P1 phải ghi actor audit theo `created_by`/`updated_by` khi schema quy định.
32. Import Framework phải ghi được job + row-level result + persisted mode; P1 baseline chỉ enable `PRODUCT`, không mặc định Employee/Province/Ward import.
33. Product import phải resolve dependency bằng multi-pass/dependency graph, không yêu cầu thứ tự Parent/Child/Combo trong file nguồn, phải reject Combo cycle và không được bypass canonical Product mutation authorization.
34. Auto Numbering phải dùng `auto_numbering_series` + `auto_numbering_counters`, không thêm mutable counter trực tiếp vào `resources`; generated business number không thay `BIGINT id`.
35. UI/API/Import/background create path của Resource có numbering phải dùng cùng AutoNumberService semantics; `resource.configure_numbering` là permission riêng.
36. Product Import phải hỗ trợ source-local key khi Product Code có thể được Auto Number sinh server-side.
37. Sau khi Product có transaction history, không được trực tiếp thay đổi các semantic field đã khóa bởi Database Design.
38. Transaction module phase sau phải xử lý stock/serial/costing/posting trên SIMPLE/CHILD leaf sau flatten; nếu cần truy nguồn COMBO thì phải snapshot/trace bằng transaction design riêng, không biến COMBO thành stock entity.

# 28. Open assumptions cần theo dõi trong FRS

Các điểm dưới đây không làm block schema P1 nhưng FRS phải định nghĩa cách dùng cụ thể:

1. Canonical application values của `employees.gender`.
2. Direct manager hiện được persistence dạng current pointer `employees.manager_employee_id`; nếu business yêu cầu historical manager effective dating thì phải có additive design.
3. Formatting separator khi render CHILD full name; canonical dữ liệu vẫn là `parent.product_name + product_child_name` và không lưu `full_name`.
4. Retention period của `authorization_logs`/`import_jobs`. Visibility scope đã khóa: Authorization Log dùng persisted evaluated Organization (`NULL -> GROUP`); P1 Import Job target PRODUCT evaluate tại `GROUP`.
System Admin behavior không còn là open assumption: P1 đã khóa **không bypass**, dùng GROUP Assignment + Base Permission + PBAC như User khác.

Participant matching với Secondary/GROUP Assignment không còn là open assumption: P1 đã khóa exact ancestor-path eligibility + inheritance-before-intersection như mục 13.3.

# 29. Definition of Done cho Database Design P1

Database implementation P1 chỉ được xem là hoàn thành khi:

- Schema/migration triển khai đúng dependency order.
- Fresh database migration thành công.
- Existing database forward migration thành công nếu áp dụng.
- PK/FK/unique/index được kiểm tra thực tế.
- Audit actor `created_by`/`updated_by` được kiểm tra trên mutable master/config theo schema.
- Organization parent matrix/cycle validation có automated test.
- Employee Primary Assignment overlap có automated test.
- Employee manager self-reference/cycle/inactive-manager rule có automated test.
- Shield group bridge và direct user permission mapping có runtime test.
- QT1 per-lane có test cho NO_PERMISSION, NO_MATCH, DENY, ALLOW+DENY, ABSOLUTE_DENY.
- Multi-assignment authorization có test cho GROUP Assignment inheritance, Company partition, Secondary Assignment eligibility và sibling isolation.
- Denied-set intersection có test cho `ALLOW` + `DENY` giữa hai role => effective ALLOW; `DENY` + `DENY` => effective DENY.
- Inherited `ABSOLUTE_DENY` từ GROUP/ancestor có test chứng minh vẫn tồn tại sau Organization/Company intersection.
- KQ Company summary intersection có test khi permission bị cấm ở một Organization nhưng được phép ở Organization khác.
- System Admin có test chứng minh không bypass Base Permission/PBAC/ABSOLUTE_DENY; bootstrap initial membership không tạo runtime bypass.
- Authoritative Evaluation Organization có test: existing target derive persisted owner, client mismatch reject, `organization.create` dùng parent, Employee Assignment mutation dùng Assignment Organization.
- Group-level Resource authorization có test tại Evaluation Organization `GROUP`.
- Security action catalog có test cho `authorization_group.assign_member`, `authorization_group.assign_permission`, `user.assign_permission`.
- Resource catalog có test/seed validation cho security/configuration resource như `user`, `authorization_group`, `resource`, `policy`, `organization` mà không tạo resource instance per row.
- `resources.domain` NOT NULL, chỉ dùng `SYSTEM`/`MASTER`/`ORGANIZATION`; canonical 21-Resource matrix đúng và không còn Resource Domain `COMPANY`.
- Forward migration domain normalization chạy an toàn; unknown/non-P1 Resource không bị auto-classify.
- Domain classification/runtime handoff test chứng minh `SYSTEM`/`MASTER` -> GROUP và `ORGANIZATION` dùng authoritative context resolver, fail closed khi missing/unresolved.
- Auto Numbering schema có test cho `auto_numbering_series`/`auto_numbering_counters`, FK và unique counter identity.
- Auto Numbering service có test cho zero/multi-series, tối đa một active default, `GROUP`/`COMPANY`/`ORGANIZATION` scope, uniqueness namespace compatibility, `AUTO_REQUIRED`/`AUTO_IF_EMPTY`, occupied-candidate skipping, reset period/timezone, post-issuance immutability/no-hard-delete và `resource.configure_numbering`.
- Concurrent Auto Number allocation có test cho existing counter và first-counter race; business rollback phải rollback counter, committed number không reuse.
- `state_id NULL` Policy có test.
- Workflow transition có test cho state membership và uniqueness `lifecycle + from_state + action`.
- Product code/SKU/barcode uniqueness có test.
- Product identifier mapping có test/contract assertion: `product_code` transport/import alias map duy nhất vào `products.code`, max length 50 và uniqueness namespace `GLOBAL`.
- Product Model V2 role derivation có test cho SIMPLE/PARENT/CHILD/COMBO.
- Parent/Child depth=1, `COMBO.parent_product_id IS NULL`, COMBO-not-parent và Child-not-parent có test.
- CHILD full name derivation từ Parent `product_name` + `product_child_name` có test.
- PARENT non-selectable/non-transactionable và COMBO selectable-but-not-leaf-transactionable có test.
- SIMPLE có transaction history hoặc dependency không tương thích không thể chuyển thành PARENT.
- Product semantic fields đã khóa không thể bị thay đổi trực tiếp sau khi có transaction history.
- Base-UOM price dùng `products.price` cho SIMPLE/CHILD/COMBO; Alternate-UOM price chỉ dùng `product_uom_conversions.price` cho SIMPLE/CHILD.
- UOM conversion factor > 0, direct-to-base-only và COMBO/PARENT không có `product_uom_conversions` có test.
- `SALES`/`BOTH` Alternate UOM bắt buộc có price; `PURCHASE` cho phép price NULL.
- COMBO component SIMPLE/CHILD/COMBO, quantity theo Base UOM, nested flatten và cycle rejection có test.
- `product_price_list_items` unique theo Price List + Product + UOM; SIMPLE/CHILD validate Base/Alternate UOM hợp lệ và COMBO chỉ cho Base UOM.
- Pricing Company -> Group -> đúng default Product/UOM có test deterministic; nested COMBO price không được resolve lại.
- Pricing fallback sang Group vẫn hoạt động khi Company có Price List nhưng không có matching Product+UOM item.
- Price List effective period `[from,to)` và open-ended NULL có boundary test.
- COMBO allocation có test cho default-price weight, zero-weight rejection, rounding residual và invariant tổng leaf amount bằng COMBO amount.
- Cùng leaf xuất hiện trong nhiều COMBO có test weighted-average theo quantity từ tổng amount/tổng quantity.
- `import_jobs.mode` persistence có test cho `CREATE_ONLY`/`UPDATE_ONLY`/`UPSERT`; P1 seed/enable import chỉ cho Product.
- Import Product có test với file Child trước Parent/Combo dependency đảo thứ tự và nested Combo cycle; dependency graph vẫn resolve đúng hoặc reject cycle bằng multi-pass/topological processing.
- Product import authorization có test hai tầng: `product.import` + per-row Product mutation action.
- Product import có test source-local `source_key`/parent/combo references khi `product_code` được Auto Number generate; successful row phải report generated code.
- Import row rollback/error reporting có test.
- Authorization audit cho required actions có runtime evidence.
