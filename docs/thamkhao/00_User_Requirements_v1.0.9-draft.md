---
document_id: "REQ-MINI-ERP-001"
title: "User Requirements - Dinktech Mini ERP"
version: "1.0.9-draft"
status: "draft"
owner: "Chủ sở hữu sản phẩm"
date: "2026-10-04"

---


# User Requirements - Dinktech Mini ERP


## 1. Mục đích tài liệu

Tài liệu này mô tả yêu cầu người dùng cho Dinktech Mini ERP theo hướng đơn giản, thực dụng và có thể triển khai theo từng nhóm nghiệp vụ.

Hệ thống phục vụ **một Group có nhiều Company**. Các master data chính được dùng chung trong Group khi phù hợp; mọi giao dịch nghiệp vụ phải xác định Company sở hữu.

Tài liệu này tập trung vào câu hỏi **"người dùng cần hệ thống làm được gì"**. Các nội dung chi tiết về database schema, API, UI component, deployment architecture và implementation design được xác định ở tài liệu downstream.

Baseline này thay thế định hướng ERP phức tạp trước đây bằng một mô hình Mini ERP, nhưng **giữ PBAC/Policy Engine cho Authorization** vì đây là capability được xác nhận cần thiết.


### 1.1 Trọng tâm revision v1.0.9-draft

- Organization Context sử dụng các type canonical `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`; business scope vẫn là một Group với nhiều Company.
- Product là Group Master dùng chung cho toàn bộ Company; P1 không có Product Company Usage Scope.
- Product Model V2 sử dụng một entity Product duy nhất; không có Variant business entity/table riêng. Quan hệ cha/con dùng Product self-reference và chỉ cho phép depth tối đa 1.
- Product role `SIMPLE`, `PARENT`, `CHILD`, `COMBO` là derived role. `SIMPLE`/`CHILD` là leaf transactionable; `COMBO` là selectable composition/template phải flatten về leaf; `PARENT` không selectable và không transactionable.
- COMBO có Base UOM, default selling price và Price List tại Base UOM; COMBO không có Serial và không có Alternate UOM/UOM Conversion.
- COMBO được phép chứa `SIMPLE`, `CHILD` hoặc COMBO khác; nested COMBO được phép nhưng composition graph phải acyclic.
- Khi COMBO được chọn trực tiếp trong nghiệp vụ bán, chỉ giá của root COMBO được resolve; nested COMBO không resolve giá lại. COMBO được flatten về SIMPLE/CHILD trước Inventory/Serial/Costing/Posting.
- Selling amount của COMBO được phân bổ xuống leaf theo trọng số `leaf quantity × leaf default Base-UOM price`; tổng trọng số bằng 0 là validation error.
- Allocation phải bảo toàn tổng amount sau rounding. Khi cùng một leaf nhận nhiều allocated price trong cùng giao dịch, final unit price được tính bằng tổng amount / tổng quantity, tương đương weighted average theo quantity.
- CHILD có tên đầy đủ suy ra từ Parent Product Name + Product Child Name; không persistence full name riêng.
- SIMPLE/CHILD có thể có Alternate UOM; COMBO chỉ có Base UOM. Giá Alternate UOM của SIMPLE/CHILD không bắt buộc bằng Base price nhân conversion factor.
- Core Pricing P1 resolve theo `Product + UOM + Company + Effective Time`, ưu tiên Company Price List, sau đó Group Price List, cuối cùng default price đúng UOM; COMBO chỉ price theo Base UOM.
- Price List effective period dùng `[effective_from, effective_to)` và candidate resolution deterministic.
- Primary Assignment chỉ là nguồn resolve **Organization Context mặc định** khi operation không chỉ định context rõ ràng. Authorization multi-assignment dùng Organization hierarchy đầy đủ từ `GROUP -> COMPANY -> BRANCH -> PLANT -> WAREHOUSE`: Assignment effective tại Evaluation Organization hoặc ancestor của nó có thể tạo Department/Position lane; Assignment ở `GROUP` được kế thừa xuống mọi descendant, còn Assignment của một Company/nhánh không được chảy ngang sang Company/nhánh khác.
- Department và Position là master độc lập; quan hệ Employee với Organization/Department/Position đi qua Employee Assignment.
- Contact là shared/core capability dùng polymorphic owner; P1 áp dụng tối thiểu cho Employee và Organization.
- Effective Profile runtime là union của Authorization Group Permission và direct user permission addition từ identity mechanism hiện hành; không có persisted Profile riêng và không tạo Mini ERP User Override store mới.
- Policy participant canonical dùng `GROUP`; Policy chỉ giới hạn Permission đã tồn tại và không có Resource Instance Authorization/arbitrary condition rule. Policy inheritance runtime đi từ `GROUP` root xuống Evaluation Organization theo đúng ancestor path.
- System Admin không có Authorization bypass: là Shield User có Employee mapping, có Employee Assignment tại Organization `GROUP` (Department IT; Position khi áp dụng), có Base Permission và chịu PBAC/`ABSOLUTE_DENY`/Workflow như mọi User khác. Initial System Admin membership được provision bằng bootstrap/deployment mechanism thay vì runtime bypass.
- Existing Resource lấy Evaluation Organization authoritative từ chính persisted target; Create operation lấy context từ owner/parent/scope canonical. Client-provided context không được thay thế authoritative context và mismatch phải bị reject. `organization.create` evaluate tại Parent Organization; `employee_assignment` evaluate tại Organization của Assignment.
- Mọi loại dữ liệu/capability cần bảo vệ đều được đăng ký thành Resource Type, gồm cả `organization`, `user`, `authorization_group`, `resource`, `policy` và các master/business resource khác; Resource Type biểu diễn loại dữ liệu/capability, không phải từng record instance.
- Quyền security administration tách biệt: `authorization_group.assign_member`, `authorization_group.assign_permission`, và `user.assign_permission`; direct user permission vẫn dùng identity mechanism hiện hành, không tạo Mini ERP User Override persistence.
- Business timezone được cấu hình theo từng `COMPANY` qua CodeIgniter Shield/Settings integration mechanism được phê duyệt; Evaluation Time là instant, còn business `DATE` được suy ra theo timezone của Company khi operation thuộc Company scope.
- Policy precedence canonical trong từng authorization lane: `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; `NO_MATCH => ALLOW`. Base Permission là prerequisite. Khi một User có nhiều Department/Position/Assignment trong cùng Company, các **tập quyền bị cấm** sau khi resolve inheritance được merge bằng phép giao để User hưởng quyền cao nhất giữa các vai trò; mọi applicable `ABSOLUTE_DENY` phải được propagate vào mọi lane con chịu scope trước phép giao.
- Workflow/Lifecycle transition được xác định tách biệt với Authorization; cùng Lifecycle + From State + Action chỉ resolve một destination state.
- Approval baseline là single-step; không triển khai enterprise workflow designer/multi-level approval trong P1.
- Import Framework P1 có persisted job/row result nhưng **không tự áp dụng cho mọi master**. Resource chỉ có import khi được explicit enable; baseline P1 hiện enable `PRODUCT`. Product import resolve dependency graph/multi-pass, không phụ thuộc thứ tự Parent/Child/Combo trong file nguồn và phải detect Combo cycle.
- Mutable master/config P1 phải giữ actor/timestamp audit theo technical boundary được phê duyệt.
- Mọi functional `UR-*` phải có Acceptance Criteria.

### 1.2 Boundary với implementation authority

Các capability sau vẫn **không được tự suy diễn thêm** nếu chưa có requirement/change request và additive design được phê duyệt:
- Resource Instance Authorization.
- Condition Rule theo amount/value hoặc quan hệ actor-resource.
- Permission metadata kiểu `policy_required`.
- Policy version/effective-dating persistence.
- Persisted Profile table.
- Mini ERP User Override store riêng; direct user permission addition chỉ sử dụng identity mechanism hiện hành đã được phê duyệt.
- Delegation Permission, Temporary Permission hoặc Permission effective dating.
- Generic `Location/Site` entity hoặc `Location Type` taxonomy tách khỏi Organization Context `PLANT`.
- Multilingual persistence table hoặc cơ chế tương đương.
- Product Variant entity/table riêng.
- Product Company Usage Scope trong P1.
- Generic Pricing Condition table cho Customer/Customer Group/Sales Channel trong P1.

Business requirement P2/P3 có thể cần persistence mới, nhưng persistence đó phải được thiết kế ở phase tương ứng và không được sửa ngược các invariant P1 nếu chưa qua change control.

### 1.3 Harmonization decisions đã khóa cho P1

Các quyết định sau đã được xác nhận và được sử dụng làm đầu vào cho Functional Requirements:
- **Một Group trong business scope:** một `GROUP` root với nhiều `COMPANY`; underlying platform capability không tự mở rộng thành Multi-Group business operation.
- **Plant/Site:** dùng canonical Organization Context `PLANT`; không tạo Location/Site taxonomy riêng trong baseline.
- **Policy semantics:** không có Base Permission thì DENY. Khi có Base Permission, từng authorization lane resolve `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; `NO_MATCH` giữ quyền ALLOW hiện có. Multi-role/multi-assignment dùng giao các tập quyền bị cấm **sau khi** áp dụng inheritance; applicable `ABSOLUTE_DENY` được kế thừa/propagate vào mọi lane con trong scope trước phép giao.
- **Effective Profile:** union của Group Permission và direct user permission addition được identity mechanism hiện hành cung cấp; không có persisted Profile riêng.
- **Policy participant:** dữ liệu mới dùng `GROUP`, không dùng `ROLE` như canonical value.
- **System Admin:** không có bypass. System Admin là User/Employee bình thường trong Authorization model, có Assignment ở `GROUP` với Department IT và Position khi áp dụng, cần Base Permission và chịu PBAC, `ABSOLUTE_DENY`, Lifecycle và business validation như mọi User khác. Initial privileged membership được provision bằng bootstrap/deployment mechanism; sau bootstrap vẫn dùng cùng Authorization pipeline.
- **Authoritative Evaluation Organization:** existing target derive context từ persisted Resource; create derive từ owner/parent/scope canonical. `organization.create` evaluate tại parent; `employee_assignment` evaluate tại Assignment Organization. Client context không phải authority.
- **Resource boundary:** mọi securable data/capability phải map vào Resource Type; `user`, `authorization_group`, `resource`, `policy`, `organization` cũng là Resource. Resource Type không đồng nghĩa Resource Instance Authorization.
- **Security assignment actions:** membership và permission matrix của Authorization Group dùng action tách biệt `assign_member`/`assign_permission`; direct User permission dùng `user.assign_permission` qua identity mechanism hiện hành.
- **GROUP inheritance:** Evaluation Organization có thể là `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`; Policy/Assignment inheritance đi theo exact ancestor path từ `GROUP` tới context hiện tại trước denied-set intersection.
- **Business timezone:** timezone cấu hình theo từng Company qua CodeIgniter Shield/Settings integration mechanism; business DATE dùng local date của Company từ cùng Evaluation Time.
- **Workflow:** legal transition được quản lý riêng với Authorization; một action tại một from-state trong cùng lifecycle chỉ có một destination.
- **Product:** Product Model V2 không có Variant entity; Parent/Child depth tối đa 1 và role được suy ra động. SIMPLE/CHILD là leaf transactionable; COMBO là selectable composition/template; PARENT không selectable/transactionable.
- **COMBO:** có Base UOM, default selling price và Price List Base-UOM; không có Serial/UOM Conversion; được phép nested COMBO nhưng composition graph phải acyclic và phải flatten về SIMPLE/CHILD trước operational processing.
- **COMBO selling allocation:** root COMBO được chọn trực tiếp resolve giá một lần; selling amount phân bổ theo `leaf quantity × leaf default price`, bảo toàn tổng amount sau rounding; cùng leaf từ nhiều COMBO được aggregate bằng tổng quantity/tổng amount.
- **Product usage:** Product dùng chung cho tất cả Company trong Group; không có `product_company_usages` ở P1.
- **Core Pricing:** giá được resolve theo Product + UOM; Company Price List ưu tiên Group Price List, sau đó fallback default price đúng UOM; COMBO chỉ có Base-UOM pricing.
- **Import:** Import Framework có lịch sử job/row nhưng target phải explicit enable theo Resource. P1 baseline chỉ enable `PRODUCT`; không mặc định Employee/Province/Ward hay mọi master đều có import. Import Job giữ mode `CREATE_ONLY`/`UPDATE_ONLY`/`UPSERT`; Product import không phụ thuộc thứ tự dòng nguồn và nested COMBO dependency phải detect cycle.

Các thay đổi về sau đối với các quyết định trên phải qua change control thay vì được tự thay đổi trong FRS.

---


## 2. Phạm vi chức năng

Hệ thống gồm 14 nhóm chức năng:

1. Tổ chức & Cấu trúc doanh nghiệp
2. Nhân sự & Cấu trúc tổ chức
3. Phân quyền & Bảo mật
4. Quản lý tài liệu
5. Product
6. Customer & CRM
7. Pricing & Promotion
8. Supplier
9. Purchase
10. Inventory
11. Sales đa kênh & Vận chuyển
12. Chấm công & Quản lý tiền lương
13. BOM & Quản lý dữ liệu sản phẩm đơn giản
14. Vận hành tài chính


---


### 2.1 Phân kỳ triển khai

### P1 - Foundation, Master Data & Authorization

P1 triển khai các capability nền tảng cần thiết để vận hành ERP:
- Nguyên tắc dữ liệu lõi và danh mục địa chỉ hành chính
- Organization
- Shared Contact Management cho Employee và Organization
- Employee
- PBAC/Policy Engine
- Workflow/Lifecycle transition cơ bản
- Product Model V2, Product Group, UOM, Alternate UOM và Combo composition/flattening foundation
- Core Pricing và Price Resolution theo `Product + UOM + Company + Effective Time`
- Import capability/framework có lịch sử job/row; P1 chỉ triển khai import cho Resource được explicit enable, baseline hiện tại là Product
- Các yêu cầu dùng chung cần thiết cho trạng thái, approval, audit, search/filter/export

Mục tiêu P1 là hình thành nền tảng tổ chức, nhân sự, phân quyền, sản phẩm và pricing cơ bản mà **không phụ thuộc Customer, Customer Group hoặc Sales Channel**.

### P2 - Operational ERP

P2 triển khai các module vận hành còn lại, ngoại trừ BOM và HCM/Payroll:
- Document
- Customer & CRM
- Pricing context mở rộng theo Customer, Customer Group và Sales Channel
- Promotion
- Supplier
- Purchase
- Inventory, bao gồm Inventory Reservation
- Sales đa kênh
- Shopee
- TikTok Shop
- Marketplace Account Mapping & Order Idempotency
- Carrier, Tracking và COD Reconciliation
- Vận hành tài chính Thu/Chi
- Import cho các master P2

### P3 - HCM/Payroll & BOM

P3 triển khai:
- Work Calendar
- Attendance
- Leave
- Overtime
- Monthly Timesheet
- Compensation
- Payroll
- Payroll Adjustment
- Labor Cost Allocation khi áp dụng
- BOM
- Material Requirement
- Estimated Finished Product Cost


## 3. Nguyên tắc nghiệp vụ chung

### UR-CORE-001 - Một Group, nhiều Company
**Priority:** MUST
**Target phase:** P1

Hệ thống quản lý một Group duy nhất và nhiều Company thuộc Group đó.

**Acceptance criteria**
- Có thể tạo và quản lý nhiều Company trong cùng Group.
- Mỗi Company có mã, tên, thông tin pháp lý, trạng thái và thông tin liên hệ.
- Group không phải transaction owner trực tiếp.

### UR-CORE-002 - Transaction thuộc đúng một Company
**Priority:** MUST
**Target phase:** P1

Mỗi business transaction phải thuộc đúng một Company.

**Acceptance criteria**
- Purchase Order, Sales Order, Inventory Transaction, Shipment, Thu/Chi, Timesheet và Payroll đều xác định được Company sở hữu.
- Transaction của Company A không tự trở thành transaction của Company B.
- Báo cáo toàn Group chỉ là tổng hợp dữ liệu từ các Company mà người dùng có quyền xem.

### UR-CORE-003 - Master data dùng chung trong Group
**Priority:** MUST
**Target phase:** P1

Các master data nền tảng được dùng chung trong Group khi phù hợp.

Đối với P1:
- Product là Group Master dùng chung cho **tất cả Company** trong business Group.
- Product UOM và Product Group là danh mục dùng chung.
- P1 không có Product Company Usage Scope và không nhân bản Product theo Company.

Customer/Supplier được triển khai ở P2 và vẫn có thể có Company Usage Scope theo requirement riêng của từng master.

**Acceptance criteria**
- Nhiều Company tham chiếu cùng Product identity mà không cần nhân bản Product.
- Product active và selectable/operationally valid có thể được sử dụng bởi mọi Company trong Group khi các rule nghiệp vụ/authorization khác cho phép.
- Không tồn tại rule P1 yêu cầu cấu hình Company Usage Scope cho Product.
- Customer/Supplier Company Usage Scope ở P2 không được suy diễn ngược thành Product Usage Scope.
- Dữ liệu giao dịch vẫn phải giữ đúng Company ownership dù Product là shared Group Master.

---


### 3.1 Baseline Assumptions & Business Invariants

- **Currency:** VND là base currency và transaction currency duy nhất trong baseline. Monetary value P1 dùng precision phù hợp với Database Design; Multi-currency và FX conversion nằm ngoài phạm vi baseline.
- **Business Group scope:** Baseline vận hành một `GROUP` root với nhiều `COMPANY`. Platform capability không tự mở rộng business scope thành Multi-Group operation.
- **Organization Context:** type canonical là `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`; hierarchy dùng parent-child và phải tuân parent matrix P1.
- **Transaction ownership:** mỗi business transaction phải resolve đúng một Company owner; context thấp hơn Company resolve về `COMPANY` ancestor phù hợp.
- **Product Group Master:** Product dùng chung cho tất cả Company; P1 không có Product Company Usage Scope.
- **Product Model V2:** mọi SKU selectable/operational là một Product record; không có Variant entity. Parent/Child chỉ depth 1. Role `SIMPLE`/`PARENT`/`CHILD`/`COMBO` được suy ra động.
- **Product operational role:** `SIMPLE` và `CHILD` là selectable leaf transactionable; `COMBO` là selectable composition/template phải flatten về SIMPLE/CHILD; `PARENT` chỉ là logical grouping và không selectable/transactionable.
- **Product naming:** Root STANDARD/COMBO dùng Product Name; CHILD dùng Product Child Name và full name được suy ra từ Parent Product Name + Product Child Name.
- **UOM:** SIMPLE/CHILD có Base UOM và có thể có Alternate UOM direct-to-base. COMBO bắt buộc có Base UOM nhưng không có Alternate UOM/UOM Conversion. Giá mặc định có thể khác nhau theo từng UOM của SIMPLE/CHILD; COMBO chỉ có Base-UOM price.
- **COMBO composition:** COMBO có thể chứa SIMPLE, CHILD hoặc COMBO khác; nested COMBO được phép nhưng graph phải acyclic. Operational quantity/stock/serial/costing/posting luôn chạy trên SIMPLE/CHILD sau flatten.
- **COMBO selling allocation:** khi COMBO được chọn trực tiếp để bán, resolve giá root một lần, flatten toàn bộ tree, phân bổ selling amount theo `leaf quantity × leaf products.price`, reject khi total weight = 0, và bảo toàn tổng amount sau rounding. Cùng leaf từ nhiều COMBO trong một giao dịch được aggregate bằng tổng quantity/tổng amount.
- **Core Pricing:** input P1 là `Product + UOM + Company + Effective Time`; fallback `Company Price List -> Group Price List -> default price đúng UOM`; COMBO chỉ được price theo Base UOM.
- **Price List period:** effective interval canonical là `[effective_from, effective_to)`; overlap được phép nhưng resolution phải deterministic.
- **Employee structure:** Department và Position là master độc lập; Employee liên kết Organization Context, Department và Position thông qua Employee Assignment. Direct manager P1 là current relationship, không phải effective-dated history.
- **Shared Contact:** Contact dùng polymorphic owner; P1 hỗ trợ Employee và Organization, Customer/Supplier có thể tái sử dụng ở P2.
- **Authorization boundary:** Permission sinh từ Resource/Action. Effective Profile runtime gồm Group Permission và approved direct user permission additions; Policy chỉ giới hạn Permission đã tồn tại.
- **Policy scope:** chỉ dùng Organization Context + Resource Type + Workflow State + Participant + Action; không có Resource Instance Authorization hoặc arbitrary Condition Rule.
- **Workflow separation:** Lifecycle/transition xác định legal state change; Authorization xác định actor có quyền thực hiện action. Một lifecycle + from-state + action chỉ resolve một destination.
- **Audit:** mutable master/config P1 phải truy được actor và timestamp theo technical boundary; Authorization Audit là decision audit, không phải general view/list history.
- **Import:** P1 có Import Framework/job-row persistence nhưng import target không mặc định cho mọi master; baseline hiện tại enable Product. Import Job persistence giữ execution mode; Product import resolve dependency nhiều pass và không phụ thuộc thứ tự dòng file.
- **Physical site model:** `PLANT` là canonical Organization Context cho plant/factory/site-level context; không tự tạo Manufacturing execution capability.
- **Functional UR quality gate:** mọi functional `UR-*` phải có Acceptance Criteria trước khi scope được khóa.

---


## 4. Tổ chức & Cấu trúc doanh nghiệp

### UR-CORE-004 - Danh mục địa chỉ hành chính
**Priority:** MUST
**Target phase:** P1

Hệ thống quản lý danh mục địa chỉ hành chính dùng chung trong Group theo mã.

Danh mục tối thiểu gồm:

**Province/City**
- Province/City Code
- Tên Tỉnh/Thành phố
- Status

**Ward/Commune**
- Ward/Commune Code
- Tên Phường/Xã
- Province/City Code
- Status

**Acceptance criteria**
- Mỗi Province/City Code là duy nhất.
- Mỗi Ward/Commune Code là duy nhất.
- Mỗi Ward/Commune phải thuộc đúng một Province/City.
- Các địa chỉ nghiệp vụ sử dụng Province/City Code và Ward/Commune Code khi áp dụng.
- Phần địa chỉ chi tiết như số nhà, tên đường, tòa nhà được lưu riêng với mã địa giới hành chính.
- Đơn vị hành chính ngừng sử dụng không được chọn cho dữ liệu mới nhưng các tham chiếu lịch sử vẫn phải giữ được.
- Province/City và Ward/Commune chỉ có import khi Resource tương ứng được explicit enable theo Import capability; P1 baseline không bắt buộc import hai danh mục này.

### UR-CORE-005 - Shared Contact Management
**Priority:** MUST
**Target phase:** P1

Contact là capability dùng chung cho nhiều loại đối tượng nghiệp vụ và sử dụng mô hình polymorphic reference. Một đối tượng có thể có nhiều Contact.

P1 áp dụng tối thiểu cho:
- Employee
- Organization Context

P2 có thể tái sử dụng cùng capability cho:
- Customer
- Supplier

Quan hệ logic:

```text
Business Entity
    |
    | 1:N
    |
 Contacts
```

Thông tin Contact baseline gồm tối thiểu:
- Contact Type (`contact_type`)
- Entity Reference ID (`contact_ref_id`)
- Description
- Status (`ACTIVE`/`INACTIVE`/`CLOSED`)
- Contact Name
- Email
- Phone
- Mobile
- Address
- Province/City Reference
- Ward/Commune Reference
- Province/City Name Snapshot
- Ward/Commune Name Snapshot
- Created At
- Updated At

`contact_type + contact_ref_id` xác định đối tượng sở hữu Contact. `contact_ref_id` là polymorphic reference và không yêu cầu foreign key trực tiếp tới Employee, Organization, Customer hoặc Supplier.

**Acceptance criteria**
- Một Employee có thể có nhiều Contact.
- Một Organization Context có thể có nhiều Contact.
- Khi Customer/Supplier được triển khai, cùng Shared Contact capability có thể được tái sử dụng mà không cần tạo mô hình Contact riêng chỉ vì khác loại business entity.
- Mỗi Contact xác định được `contact_type` và `contact_ref_id` của đối tượng sở hữu.
- P1 hỗ trợ tối thiểu `EMPLOYEE` và `ORGANIZATION` như các loại đối tượng sử dụng Contact.
- Có thể tạo, xem, chỉnh sửa và thay đổi trạng thái Contact trong phạm vi authorization của đối tượng sở hữu.
- Contact `INACTIVE` hoặc `CLOSED` không bị xóa khỏi lịch sử chỉ vì không còn được sử dụng cho dữ liệu mới.
- Province/City và Ward/Commune reference của Contact, khi được khai báo, phải tham chiếu danh mục địa chỉ hành chính hợp lệ.
- Có thể lưu snapshot tên Province/City và Ward/Commune trên Contact để giữ ngữ cảnh hiển thị/lịch sử theo database authority.
- Search Contact tối thiểu hỗ trợ theo Contact Name, Email, Phone hoặc Mobile trong phạm vi authorization được phép.
- Requirement này không tạo foreign key từ `contact_ref_id` tới từng bảng entity; persistence phải tuân theo polymorphic model của implementation authority.

### UR-ORG-001 - Quản lý Company
**Priority:** MUST
**Target phase:** P1

Quản lý danh sách Company thuộc Group.

Thông tin tối thiểu:
- Company Code
- Tên Company
- Tên pháp lý
- Mã số thuế
- Địa chỉ chi tiết
- Province/City Code
- Ward/Commune Code
- Contact(s) thông qua Shared Contact Management
- Trạng thái hoạt động

**Acceptance criteria**
- Company có thể có nhiều Contact thông qua Shared Contact Management.
- Contact của Company sử dụng Organization Context tương ứng làm đối tượng sở hữu.
- Không phát sinh giao dịch mới cho Company đã ngừng hoạt động.
- Lịch sử giao dịch của Company ngừng hoạt động vẫn tra cứu được.

### UR-ORG-002 - Quản lý Branch, Plant/Site và Warehouse
**Priority:** MUST
**Target phase:** P1

Organization Context sử dụng các type canonical:
- `GROUP`
- `COMPANY`
- `BRANCH`
- `PLANT`
- `WAREHOUSE`

Trong business scope của baseline này:
- **Branch** là đơn vị tổ chức/kinh doanh trong cây Organization Context của một Company.
- **Plant/Site** sử dụng canonical type `PLANT` và biểu diễn địa điểm vận hành cấp plant/factory/site. `PLANT` không đồng nghĩa với Manufacturing execution module.
- **Warehouse** là Organization Context phục vụ quản lý tồn kho và phải resolve được về đúng một Company ancestor.

Hierarchy của các node được quản lý bằng parent-child Organization Context. Baseline không tạo một entity `Location/Site` riêng hoặc `Location Type` taxonomy riêng chỉ để mô tả OFFICE/FACTORY/STORE/DISTRIBUTION_CENTER. Nếu business cần taxonomy đó, phải có additive design decision trước khi bổ sung persistence.

**Acceptance criteria**
- Company được biểu diễn bằng Organization Context type `COMPANY` thuộc Group baseline.
- Parent-child matrix P1 hỗ trợ: `GROUP -> COMPANY`; `COMPANY -> BRANCH/PLANT/WAREHOUSE`; `BRANCH -> PLANT/WAREHOUSE`; `PLANT -> WAREHOUSE`; `WAREHOUSE` không có child.
- P1 không hỗ trợ same-type nesting như `BRANCH -> BRANCH` hoặc `WAREHOUSE -> WAREHOUSE`.
- Mỗi Branch/Plant/Warehouse dùng cho nghiệp vụ của một Company phải resolve được về đúng Company ancestor đó.
- Branch/Plant/Warehouse của Company A không được dùng như context nội bộ của Company B.
- Warehouse dùng cho Inventory phải thuộc cây Organization Context của Company sở hữu transaction.
- Không cho self-parent, hierarchy cycle hoặc parent-child trái matrix.
- Có thể kích hoạt/ngừng hoạt động Organization Context mà không làm mất dữ liệu lịch sử đã tham chiếu.
- Một Company có thể có nhiều Branch, nhiều `PLANT` và nhiều `WAREHOUSE` khi phù hợp với hierarchy được cấu hình.
- Khai báo `PLANT` không làm phát sinh các capability Manufacturing nằm ngoài phạm vi baseline.
- Không tạo thêm Organization Context Type ngoài danh sách canonical nếu chưa qua change control.


### UR-ORG-003 - Xem và tổng hợp dữ liệu theo Company
**Priority:** MUST
**Target phase:** P1

Người dùng có thể xem dữ liệu theo một hoặc nhiều Company trong phạm vi được PBAC cho phép.

**Acceptance criteria**
- Có thể lọc báo cáo theo Company.
- Có thể tổng hợp nhiều Company khi người dùng có quyền.
- Không hiển thị dữ liệu của Company ngoài authorization scope.


---


## 5. Nhân sự & Cấu trúc tổ chức

### UR-EMP-001 - Quản lý Employee
**Priority:** MUST
**Target phase:** P1

Quản lý Employee Master gồm tối thiểu:
- Employee Code
- Họ và tên
- Giới tính khi khai báo
- Ngày tháng năm sinh khi khai báo
- Quê quán khi khai báo
- User linkage khi Employee có tài khoản hệ thống
- Contact(s) thông qua Shared Contact Management
- Ngày vào làm
- Trạng thái làm việc
- Quản lý trực tiếp hiện tại

**Acceptance criteria**
- Employee Code là duy nhất.
- Một User tối đa liên kết với một Employee; Employee không bắt buộc phải có User.
- Employee có thể có nhiều Contact thông qua Shared Contact Management.
- Employee nghỉ việc/inactive không bị hard delete khỏi lịch sử.
- Direct manager không được là chính Employee, không được tạo management cycle và Employee inactive không được chọn làm manager mới.
- P1 lưu quản lý trực tiếp hiện tại; không yêu cầu historical manager effective dating.
- P1 không yêu cầu full version history cho mọi field của Employee Master. Lịch sử Organization/Department/Position được giữ qua Employee Assignment; Contact history được giữ qua Shared Contact.

### UR-EMP-002 - Quản lý Department và Position
**Priority:** MUST
**Target phase:** P1

Quản lý:
- Department
- Cấu trúc Department
- Position/Chức vụ
- Level của Position khi áp dụng
- Danh sách Employee theo Department/Position thông qua Assignment

Department và Position là master độc lập với Organization Context. Department có hierarchy riêng; Position có thể có level. Quan hệ thực tế của Department/Position với Company hoặc Organization Context được xác định thông qua Employee Assignment, không bằng cách gắn trực tiếp Organization FK vào Department/Position.

**Acceptance criteria**
- Department không bắt buộc thuộc trực tiếp một Company và không yêu cầu `organization_id` riêng trong master Department.
- Position không bắt buộc thuộc trực tiếp một Company/Department và có thể được dùng lại qua nhiều Assignment khi phù hợp.
- Có thể xem Employee theo Organization Context + Department + Position bằng dữ liệu Employee Assignment.
- Department hoặc Position không tự tạo Permission.
- Không tạo quan hệ Organization ownership trực tiếp cho Department/Position nếu implementation baseline chưa được thay đổi.


### UR-EMP-003 - Employee Assignment
**Priority:** MUST
**Target phase:** P1

Employee Assignment biểu diễn việc Employee làm việc trong một Organization Context, Department và Position trong một khoảng thời gian.

Assignment tối thiểu gồm:
- Employee
- Organization Context (`GROUP`/`COMPANY`/`BRANCH`/`PLANT`/`WAREHOUSE`)
- Department khi áp dụng
- Position khi áp dụng
- Primary flag (`is_primary`)
- Effective From/To
- Status

**Acceptance criteria**
- Employee không liên kết trực tiếp Organization/Department/Position ngoài cơ chế Employee Assignment.
- Một Employee có thể chuyển Organization Context/Department/Position mà không tạo Employee mới.
- Lịch sử Assignment được giữ lại bằng effective period và status.
- Với mỗi Employee và mỗi thời điểm, không được có hai Primary Assignment cùng hiệu lực.
- Hệ thống không cho tạo/chỉnh effective period làm các Primary Assignment bị overlap.
- Primary Assignment đang hiệu lực là nguồn chính để resolve **Organization Context mặc định** của Employee khi operation không cung cấp Evaluation Organization Context rõ ràng.
- Khi transaction/operation đã có Evaluation Organization Context, Company scope được resolve từ chính Evaluation Organization Context tới self/ancestor `COMPANY` gần nhất.
- Nếu context dùng cho transaction không resolve được đúng một Company owner thì context được xem là không đủ để thực hiện transaction đó.
- Secondary Assignment không thay đổi Organization Context mặc định của actor, nhưng Assignment effective phù hợp với Company/Organization đang evaluate được phép tham gia resolve Department/Position authorization lane.
- Một Employee có thể có nhiều Assignment active, kể cả ở nhiều Company, khi business cần, miễn chỉ có một Primary Assignment tại cùng thời điểm.
- Authorization của Company A và Company B được tính độc lập; Assignment/Department/Position thuộc Company khác không được trộn vào cùng phép tính.


---


## 6. Phân quyền & Bảo mật

**6.1 Mô hình Authorization**

Authorization sử dụng mô hình logic:

**User Identity -> Authorization Group Membership/Direct Permission Addition -> Effective Profile -> Permission(Resource/Action) -> Policy Rule -> Authorization Evaluation**

Trong đó:
- Permission catalog được hình thành từ `Resource + Action`/Resource Action; không có Permission table riêng.
- Authorization Group là nhóm quyền, không phải Organization Group, Company, Department hoặc Position.
- Effective Profile là tập Permission runtime được resolve từ Group Permission và approved direct user permission additions của identity mechanism hiện hành; không có persisted `profiles` table.
- Direct user permission chỉ là ALLOW addition vào Base Permission; không tự tạo Policy restriction và không bypass `ABSOLUTE_DENY`.
- Policy Rule chỉ giới hạn/cân bằng việc sử dụng Permission đã tồn tại; Policy không được tạo Permission mới.
- Workflow/Lifecycle xác định state và transition hợp lệ; Authorization chỉ trả lời actor có quyền thực hiện action/transition đó hay không.

### UR-IAM-001 - User Identity
**Priority:** MUST
**Target phase:** P1

Quản lý User Identity tách khỏi Employee nhưng có thể liên kết một User với tối đa một Employee.

Thông tin chính:
- User ID
- Authentication status
- Access status
- Employee reference khi áp dụng

**Acceptance criteria**
- User Locked/Inactive không thực hiện giao dịch mới.
- Employee không bắt buộc phải có User.
- User nghiệp vụ muốn truy cập ERP Resource phải resolve được Employee mapping và Organization Context phù hợp theo rule của operation.
- Một User tối đa liên kết với một Employee.
- System Admin không phải bypass identity. System Admin phải có Shield User, Employee mapping và Employee Assignment effective tại Organization `GROUP`; baseline business assignment của System Admin thuộc Department IT; Position được cấu hình khi áp dụng.
- System Admin có Base Permission như User khác và phải đi qua cùng PBAC Policy, `ABSOLUTE_DENY`, Workflow/Lifecycle và business/data validation; không có special root bypass trong P1.
- Không tạo special database flag, special Policy row hoặc hard-coded Authorization branch để cho phép System Admin đi vòng qua PBAC.
- Service Account access vào ERP business resource không được tự suy diễn từ requirement này; nếu cần phải có additive design decision được phê duyệt.

### UR-IAM-002 - Permission theo Resource/Action
**Priority:** MUST
**Target phase:** P1

Permission là đơn vị quyền cơ sở theo cặp:

**Resource + Action**

Permission code canonical có dạng `resource.action`. Baseline action catalog có thể gồm:
- `view`
- `create`
- `edit`
- `delete`
- `submit`
- `approve`
- `reject`
- `cancel`
- `post`
- `close`
- `export`
- `print`
- `setstate`
- `import` khi Resource được explicit enable import
- `assign_member` khi Resource hỗ trợ membership assignment
- `assign_permission` khi Resource hỗ trợ permission assignment

Module có thể cần additional Resource Action đã được requirement phê duyệt, nhưng vẫn phải đi qua cùng Resource Action catalog; không tạo Permission store riêng.

**Resource semantics**
- Mọi loại dữ liệu/capability cần Authorization phải được đăng ký thành một Resource Type canonical.
- Resource Type bao gồm cả dữ liệu quản trị nền tảng như `organization`, `user`, `authorization_group`, `resource`, `policy`, bên cạnh `employee`, `product`, `price_list`, `import_job` và các business resource ở phase sau.
- `authorization_group` là Resource Type của nhóm phân quyền; không dùng tên `group` mơ hồ vì `GROUP` đồng thời là Organization Context type.
- Một row nghiệp vụ cụ thể không tự trở thành Resource record riêng. P1 vẫn không có Resource Instance Authorization; row/data scope được xác định qua Evaluation Organization Context + Policy.

**Acceptance criteria**
- Quyền `view` không tự tạo quyền `edit`/`approve`/`export`.
- Mỗi Resource Type là duy nhất trong Resource catalog.
- Mỗi Action code là duy nhất trong phạm vi một Resource.
- Permission hiệu lực được tham chiếu bằng Resource Action tương ứng.
- Không tạo bảng Permission độc lập nếu implementation baseline không được thay đổi.
- User/Authorization Group/Resource/Policy/Organization management đều phải đi qua Resource + Action authorization tương ứng; không có implicit admin access chỉ vì dữ liệu thuộc security/configuration domain.
- Không tạo một Resource catalog row cho từng User/Employee/Product/Organization record chỉ để biểu diễn quyền trên record cụ thể.

### UR-IAM-003 - Effective Profile
**Priority:** MUST
**Target phase:** P1

Effective Profile là tập Permission runtime của User sau khi resolve Authorization Group membership, Group Permission và approved direct user permission additions từ identity mechanism hiện hành.

**Acceptance criteria**
- User có thể thuộc một hoặc nhiều Authorization Group theo identity/group membership hiện hành.
- Base Permission là union của Resource Action được cấp qua active Group và direct user permission addition map hợp lệ vào Resource Action catalog.
- Direct user permission chỉ bổ sung ALLOW; không có direct DENY trong Effective Profile.
- Effective Profile không bypass Policy Rule hoặc `ABSOLUTE_DENY`.
- `Profile` là khái niệm logic/runtime; không tạo persisted Profile table.
- Không tạo Mini ERP User Override persistence riêng. Việc sử dụng direct permission của identity mechanism hiện hành không được diễn giải thành một User Override table mới.
- Quản trị direct user permission qua Mini ERP, khi được expose, phải dùng Resource Action `user.assign_permission` và ghi vào identity permission mechanism hiện hành; operation này vẫn chịu PBAC như Resource khác.

### UR-IAM-004 - Authorization Group
**Priority:** MUST
**Target phase:** P1

Authorization Group hỗ trợ gom Permission và gán membership cho User.

**Acceptance criteria**
- Authorization Group không phải Organization Context type `GROUP`.
- Authorization Group không phải Company.
- Authorization Group không phải Department hoặc Position.
- Group Permission map Group tới Resource Action.
- Gán/bỏ User membership dùng permission `authorization_group.assign_member`; thay đổi Group Permission matrix dùng `authorization_group.assign_permission`. Hai capability không dùng chung một generic `assign`.
- User-to-Group membership sử dụng identity/group mechanism hiện hành; Mini ERP không tạo local identity store cạnh tranh.

### UR-IAM-005 - Policy Rule
**Priority:** MUST
**Target phase:** P1

Policy Rule giới hạn Permission đã tồn tại theo context được implementation baseline hỗ trợ.

Baseline dimension gồm:
- Organization Context
- Resource Type
- Workflow State
- Participant
- Action
- Effect

Participant type baseline gồm:
- `USER`
- `GROUP`
- `POSITION`
- `DEPARTMENT`
- `ORGANIZATION`

`GROUP` là participant canonical cho Authorization Group trong dữ liệu mới; không dùng `ROLE` làm canonical participant type mới.

**Acceptance criteria**
- Policy chỉ được evaluate sau khi User có Permission cơ sở tương ứng.
- Policy có thể scope theo Organization Context, Resource Type và Workflow State; Policy không chỉ định state được hiểu là áp dụng cho mọi Workflow State phù hợp.
- Department/Position chỉ tham gia Policy thông qua Participant type phù hợp và Employee Assignment effective trong Company/Organization đang evaluate, không phải arbitrary condition column.
- `USER`/`GROUP` hình thành actor-level lane; `DEPARTMENT`/`POSITION` hình thành assignment/department lane; `ORGANIZATION` là contextual rule theo Organization scope.
- Policy không cấp thêm Permission chưa tồn tại trong Effective Profile.
- Baseline không hỗ trợ Resource Instance Authorization.
- Baseline không hỗ trợ arbitrary Business Condition theo amount/value hoặc actor-resource relation.

### UR-IAM-006 - Resolve Organization Context và Assignment scope
**Priority:** MUST
**Target phase:** P1

Authorization phân biệt **Default Actor Organization Context** và **Evaluation Organization Context**.

Khi operation không cung cấp Organization Context rõ ràng, hệ thống resolve default context:

**User -> Employee -> Primary Assignment tại Evaluation Time -> Default Organization Context**

Khi operation/resource có Organization Context rõ ràng, context đó là **Evaluation Organization Context** và có thể là `GROUP`, `COMPANY`, `BRANCH`, `PLANT` hoặc `WAREHOUSE`.

Scope path canonical:

**GROUP root -> ... -> Evaluation Organization Context**

Một Employee Assignment effective được phép tạo Department/Position authorization lane khi `assignment.organization_id` bằng Evaluation Organization hoặc là ancestor của Evaluation Organization trên cùng path. Vì vậy Assignment tại `GROUP` có thể áp dụng xuống mọi descendant Company/Organization; Assignment thuộc Company/nhánh khác không được chảy ngang sang context đang evaluate.

**Authoritative context rule:**
- Existing Resource/record có Organization scope phải derive Evaluation Organization từ persisted target/owner canonical; context do client gửi chỉ là selector/hint và mismatch phải bị reject.
- Create operation derive Evaluation Organization từ owner/parent/scope canonical trong payload.
- Group-level shared/admin Resource dùng `GROUP` khi canonical scope là Group.
- `organization.create` evaluate tại Parent Organization của node mới.
- `employee_assignment.create/edit` evaluate tại `assignment.organization_id`.

Khi transaction yêu cầu Company owner, hệ thống tiếp tục resolve:

**Evaluation Organization Context -> self/ancestor `COMPANY`**

`GROUP` không phải transaction owner và không resolve thành một Company duy nhất.

**Acceptance criteria**
- Primary Assignment đang hiệu lực chỉ xác định default Organization Context khi operation không có context rõ ràng.
- Primary flag không phải điều kiện duy nhất để Assignment tham gia PBAC.
- Assignment effective tại `GROUP` được phép cung cấp Department/Position lane khi evaluate bất kỳ descendant Organization trong Group.
- Assignment tại Company A hoặc descendant của Company A không tạo Department/Position lane cho Company B hoặc descendant của Company B.
- Assignment ở sibling Branch/Plant/Warehouse ngoài ancestor path của Evaluation Organization không được tham gia.
- Group-level administrative/shared Resource có canonical scope `GROUP` được authorize với `Evaluation Organization Context = GROUP` mà không cần tạo Company giả.
- Existing target không được phép đổi Authorization context chỉ bằng cách client gửi một `evaluation_organization_id` khác.
- Tạo Company dưới GROUP authorize `organization.create` tại GROUP; tạo Branch/Plant/Warehouse authorize tại Parent Organization tương ứng.
- Tạo/sửa Employee Assignment authorize tại Organization của Assignment.
- Khi transaction yêu cầu Company owner, Evaluation Organization Context phải resolve được đúng một Company; `GROUP` không hợp lệ làm transaction owner.
- Nếu không resolve được context cần thiết cho operation thì không được tự tạo ALLOW từ context bị thiếu.

### UR-IAM-007 - Authorization theo loại nghiệp vụ
**Priority:** MUST
**Target phase:** P1

Policy có thể giới hạn Permission theo Resource Type.

Ví dụ:
- Organization
- User
- Authorization Group
- Resource/Action Catalog
- Policy
- Employee/Employee Assignment
- Product/Price List
- Purchase Order
- Goods Receipt
- Inventory Adjustment
- Sales Order
- Shipment
- Payroll
- Money Receipt/Money Expense

**Acceptance criteria**
- Permission trên Purchase Order không tạo quyền trên Sales Order.
- Có thể áp dụng Policy riêng cho từng Resource Type.
- Resource Instance cụ thể không trở thành policy scope riêng trong baseline.

### UR-IAM-008 - Authorization theo Workflow State
**Priority:** MUST
**Target phase:** P1

Policy có thể giới hạn Action theo Workflow State của Resource.

Ví dụ:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- REJECTED
- CONFIRMED
- POSTED
- PROCESSING
- COMPLETED
- CANCELLED

**Acceptance criteria**
- Có thể cho phép `edit` ở DRAFT nhưng giới hạn ở APPROVED/POSTED theo Policy.
- Có thể cho phép `approve` khi Resource ở PENDING_APPROVAL.
- COMPLETED/CANCELLED/POSTED có thể bị khóa Action thay đổi theo Policy và lifecycle rule.
- Module-specific state có thể được dùng khi state đó tồn tại trong State/Lifecycle catalog phù hợp.
- Policy không chỉ định Workflow State có thể áp dụng cho mọi state của Resource phù hợp; state-specific Policy chỉ match state tương ứng.
- Workflow transition validity và Authorization decision là hai kiểm tra tách biệt.

### UR-IAM-009 - Policy Effect và Evaluation precedence
**Priority:** MUST
**Target phase:** P1

Policy Rule hỗ trợ:
- `ABSOLUTE_DENY`
- `ALLOW`
- `DENY`

Precedence canonical trong **từng authorization lane sau khi đã load Policy tại scope hiện tại + ancestor**:

**ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH**

Trong đó lane result cho một `Resource + Action` được biểu diễn thành **tập quyền bị cấm**:
- `ABSOLUTE_DENY` hoặc `DENY` sau QT1 => permission nằm trong KQ denied của lane.
- `ALLOW` hoặc `NO_MATCH` (`NO_MATCH => ALLOW`) => permission không nằm trong KQ denied của lane.

Khi một User có nhiều lane hợp lệ trong cùng Organization, effective denied set dùng phép **giao** các KQ lane sau inheritance. Vì vậy một `DENY` thông thường có thể được quyền cao hơn từ lane khác mở lại; một quyền chỉ còn bị cấm bởi ordinary `DENY` khi bị cấm đồng thời ở mọi lane liên quan.

Applicable `ABSOLUTE_DENY` tại `GROUP`/ancestor/current Evaluation Organization phải được propagate vào mọi KQ lane con chịu scope **trước phép giao**, vì vậy không thể bị một role khác mở lại.

**Acceptance criteria**
- Không có Base Permission thì kết quả luôn DENY; Policy không thể tạo Permission mới.
- Trong một lane, `ABSOLUTE_DENY` luôn thắng `ALLOW`/`DENY` thông thường.
- Trong một lane, `ALLOW` thắng `DENY` thông thường khi cả hai cùng match và không có `ABSOLUTE_DENY`.
- Trong một lane, `DENY` có hiệu lực khi là effect match cao nhất còn lại.
- Giữa nhiều Department/Position/Assignment lane cùng hợp lệ, User hưởng quyền cao nhất: permission chỉ nằm trong effective denied set nếu còn nằm trong giao của mọi KQ lane sau inheritance.
- Applicable `ABSOLUTE_DENY` từ bất kỳ node nào trên scope path `GROUP -> ... -> Evaluation Organization` không được mất qua phép giao vì phải được propagate vào mọi KQ lane con trước merge.
- Không sử dụng Policy Priority hoặc hierarchy depth để thay thế effect precedence baseline.

### UR-IAM-010 - Default rule khi NO_MATCH
**Priority:** MUST
**Target phase:** P1

Baseline không sử dụng Permission metadata `policy_required`.

**Acceptance criteria**
- Không có Base Permission => DENY.
- Có Base Permission + không có Policy match trong một lane => lane đó ALLOW và permission không thuộc KQ denied của lane.
- Có Base Permission + Policy match trong một lane => resolve theo precedence `ABSOLUTE_DENY > ALLOW > DENY`.
- Multi-lane merge chỉ thực hiện sau khi inheritance và QT1 của từng lane đã hoàn tất.
- Không tạo column/metadata `policy_required` chỉ từ User Requirements này.

### UR-IAM-011 - Policy inheritance trong Organization Context
**Priority:** MUST
**Target phase:** P1

Policy được evaluate theo Evaluation Organization Context hiện tại cùng các Policy kế thừa từ ancestor context theo hierarchy. **Inheritance phải được resolve trước khi thực hiện phép giao các tập quyền bị cấm.**

Với bất kỳ Evaluation Organization `O` thuộc `GROUP`/`COMPANY`/`BRANCH`/`PLANT`/`WAREHOUSE`, Policy source canonical là toàn bộ exact path:

**GROUP root -> ... -> O**

Ví dụ khi `O = WAREHOUSE`: `GROUP + COMPANY + BRANCH/PLANT ancestor khi có + WAREHOUSE`. Khi `O = GROUP`, scope path chỉ gồm `GROUP`.

**Acceptance criteria**
- Policy của Parent Organization Context có thể được áp dụng khi evaluate Child Organization Context nếu các dimension/participant còn lại match.
- Policy tại `GROUP` có thể kế thừa xuống mọi descendant Organization trong Group.
- Quyền Department/Position tại Child phải xét các Policy applicable được kế thừa từ toàn bộ ancestor path, kể cả `GROUP`.
- Policy của sibling Organization không được kế thừa ngang.
- KQ của mỗi Organization phải resolve toàn bộ inheritance trước khi merge các lane.
- Applicable `ABSOLUTE_DENY` từ `GROUP` hoặc bất kỳ ancestor nào phải hiện diện trong mọi KQ lane con chịu scope trước intersection, nên không bị role khác mở lại.
- Không copy vật lý Policy từ Parent xuống Child chỉ để biểu diễn inheritance; inheritance được resolve runtime.
- Khi Parent và Child cùng có Policy match, không dùng độ sâu hierarchy như một Policy Priority mặc định.
- Policy version/effective-dating persistence không thuộc baseline hiện hành; nếu cần phải có additive design decision.

### UR-IAM-012 - Explainable Authorization
**Priority:** MUST
**Target phase:** P1

Authorization decision phải có reason đủ để debug và kiểm tra. Với các Action được audit, persisted authorization log phải dùng các field được implementation baseline hỗ trợ.

**Acceptance criteria**
Có thể giải thích tối thiểu bằng các thông tin phù hợp với context/log hiện hành:
- User
- Organization Context
- Resource Type
- Action
- Workflow State khi áp dụng
- Matched Policy khi có
- Decision
- Reason
- Request/Correlation identifier khi áp dụng
- Timestamp đối với persisted log

Ngoài ra:
- Employee mapping và Group Permission có thể được dùng khi debug/evaluate nhưng không bắt buộc phải snapshot thành các column riêng trong authorization log.
- View/List không bắt buộc phải persisted log nếu implementation baseline không yêu cầu; runtime vẫn phải trả về decision/reason phù hợp.
- Không yêu cầu `Policy Version` trong log khi baseline chưa có Policy version persistence.

### UR-IAM-013 - Bảo vệ dữ liệu nhạy cảm
**Priority:** MUST
**Target phase:** P1

Dữ liệu nhạy cảm gồm tối thiểu:
- Payroll
- Compensation
- Giá vốn/standard cost
- Tài khoản ngân hàng
- Thu chi
- Thông tin định danh nhân viên

Baseline bảo vệ dữ liệu nhạy cảm ở mức **Resource/Action authorization**. Field-level masking/redaction không thuộc phạm vi baseline.

**Acceptance criteria**
- `view`, `edit` và `export` được đánh giá như các Permission độc lập.
- `view` không tự tạo quyền `export`.
- Chỉ cho phép Action khi final Authorization = ALLOW.
- Việc không triển khai field-level masking không được làm bypass Resource/Action authorization.

**6.2 PBAC Decision Table minh họa**

| Base Permission | Applicable Policy Effects | Final Result | Giải thích |
| --- | --- | --- | --- |
| Không có | bất kỳ | DENY | Policy không thể tạo Permission mới. |
| Có | NO_MATCH | ALLOW | Base Permission được phép khi không có Policy match. |
| Có | DENY | DENY | DENY là effect match cao nhất còn lại. |
| Có | ALLOW + DENY | ALLOW | ALLOW thắng DENY thông thường. |
| Có | ABSOLUTE_DENY + ALLOW/DENY | DENY | ABSOLUTE_DENY luôn thắng. |

Precedence canonical trong từng lane: **ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH**.

### 6.3 Canonical PBAC multi-assignment algorithm

Với mỗi `Resource + Action` đã có Base Permission, Authorization evaluate theo một **Evaluation Organization Context** cụ thể. Context có thể là `GROUP`, `COMPANY`, `BRANCH`, `PLANT` hoặc `WAREHOUSE`. Với Company-scoped operation, các Company được evaluate độc lập.

```text
AUTHORIZE(User, Resource, Action, EvaluationOrganization O, State, Time):

    1. Resolve Base Permission(Resource, Action).
       Nếu không có -> DENY.

    2. Resolve ScopePath(O):
       GROUP root -> ... -> O

    3. Resolve CommonContextPolicies[O]:
       - match ORGANIZATION participant applicable trên ScopePath(O)
       - contextual rules dùng chung cho mọi lane tại O

    4. Tính KQ[user, O]:
       - match actor-level participant USER/GROUP
       - cộng CommonContextPolicies[O]
       - load Policy trên toàn ScopePath(O)
       - áp dụng QT1
       -> tập Resource + Action bị cấm của actor lane

    5. Resolve eligible effective Employee Assignments A:
       - A.organization = O; hoặc
       - A.organization là ancestor của O trên ScopePath(O)
       - Assignment ở GROUP được kế thừa xuống mọi descendant
       - Assignment ở sibling/Company khác không tham gia

    6. Với từng eligible Assignment A:
       - resolve DEPARTMENT/POSITION participant
       - cộng CommonContextPolicies[O]
       - load inheritance trên ScopePath(O)
       - áp dụng QT1
       -> KQ[A, O]

    7. Trước phép giao, mọi applicable ABSOLUTE_DENY
       matched trên ScopePath(O) phải được đưa vào tất cả
       KQ lane con chịu scope.

    8. KQ[O] =
       INTERSECTION(
           KQ[user, O],
           all KQ[A, O]
       )

    9. Permission thuộc KQ[O] -> DENY; ngược lại -> ALLOW.

FOR EACH COMPANY C khi cần Company summary:
    evaluate C và các descendant Organization thuộc C độc lập;
    KQ[C-summary] = INTERSECTION(all KQ[O] trong Company C).
```

Business meaning:
- `KQ[x]` là tập `Resource + Action` bị cấm sau khi lane `x` đã resolve inheritance và QT1.
- Phép giao thể hiện **highest privilege wins** giữa nhiều vai trò hợp lệ: ordinary `DENY` chỉ còn hiệu lực nếu permission bị cấm đồng thời ở mọi lane đang tham gia.
- `ABSOLUTE_DENY` vẫn tuyệt đối vì được áp dụng/propagate từ `GROUP`/ancestor/current Organization trước intersection.
- Assignment tại `GROUP` có thể cung cấp Department/Position role cho mọi descendant; Assignment ở Company/nhánh cụ thể chỉ có hiệu lực trong subtree tương ứng.
- Group-level administrative Resource có thể evaluate trực tiếp tại `GROUP`; không bắt buộc partition qua Company nếu operation không có Company owner.
- Company A và Company B được evaluate độc lập; dữ liệu/quyền của một Company không được trộn vào Company khác.

## 7. Quản lý tài liệu

### UR-DOC-001 - Attachment theo đối tượng nghiệp vụ
**Priority:** MUST
**Target phase:** P2

Cho phép đính kèm file vào:
- Product
- Customer
- Supplier
- Employee
- Purchase
- Sales
- Inventory
- Finance
- Payroll

**Acceptance criteria**
- File xác định được đối tượng nghiệp vụ liên quan.
- Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ.

### UR-DOC-002 - Tìm kiếm và version tài liệu
**Priority:** SHOULD
**Target phase:** P2

Hỗ trợ:
- Upload/Download
- Search
- Tag/Category
- Version
- Uploaded By/At

**Acceptance criteria**
- Có thể xem version hiện tại và version trước.
- Tài liệu bị thay thế không làm mất lịch sử.


---


## 8. Product

### UR-PRD-001 - Product Master V2
**Priority:** MUST
**Target phase:** P1

Product là Group Master dùng chung cho tất cả Company. Hệ thống sử dụng **một business entity Product duy nhất**; Variant không tồn tại như business entity riêng.

Mọi SKU selectable/operational được biểu diễn bằng một Product record. Quan hệ cha/con của STANDARD Product được biểu diễn bằng Product parent-child với độ sâu tối đa 1.

Product Type canonical:
- `STANDARD`
- `COMBO`

Product Role không được cấu hình độc lập mà được suy ra:
- `SIMPLE`: STANDARD root không có Child.
- `PARENT`: STANDARD root có Child.
- `CHILD`: STANDARD có Parent.
- `COMBO`: Product Type COMBO.

Operational role:
- `SIMPLE`: selectable input + leaf transactionable.
- `CHILD`: selectable input + leaf transactionable.
- `COMBO`: selectable input + composition/template; phải flatten về leaf trước operational processing.
- `PARENT`: không selectable và không transactionable.

Thông tin tối thiểu của Product gồm:
- Product Code
- SKU khi Product selectable
- Barcode khi áp dụng
- Product Name hoặc Product Child Name theo role
- Product Group
- Product Type
- Base UOM khi Product selectable
- Default Selling Price của Base UOM khi Product selectable
- Net Weight
- Packaging Gross Weight
- Packaging Length/Width/Height
- Description
- Manual Standard Cost khi áp dụng cho BOM costing
- Serial Management flag theo rule
- Status

**Acceptance criteria**
- Product Code duy nhất trong Group.
- SKU duy nhất khi khai báo; SIMPLE, CHILD và COMBO selectable phải có SKU.
- Barcode duy nhất khi khai báo.
- Product dùng chung cho tất cả Company; không có Product Company Usage Scope trong P1.
- Quan hệ Parent/Child chỉ cho phép `Parent -> Child`; Child không được có Child; depth luôn tối đa 1.
- Parent candidate phải là root STANDARD; COMBO không được làm Parent hoặc Child.
- Root STANDARD không có Child được xem là SIMPLE; khi có Child đầu tiên thì trở thành PARENT nếu đáp ứng conversion rule.
- PARENT không được dùng cho purchase, sales, inventory, price-list item hoặc transaction line.
- COMBO có thể được chọn ở bước nhập liệu/chọn nhanh nhưng không phải inventory/serial/costing/posting leaf; trước các nghiệp vụ downstream tương ứng phải flatten hoàn toàn về SIMPLE/CHILD.
- SIMPLE chỉ được chuyển thành PARENT khi chưa có transaction history và không còn dependency transactionable không tương thích; hệ thống không tự xóa dependency để ép chuyển role.
- Sau khi Product có transaction history, các semantic field như Base UOM, Parent relation, Product Type và Serial Management policy không được thay đổi trực tiếp theo cách làm sai lịch sử.
- Product inactive không dùng cho giao dịch mới nhưng identity/history vẫn được giữ.

### UR-PRD-002 - Product Naming
**Priority:** MUST
**Target phase:** P1

Quy tắc tên Product:
- SIMPLE/PARENT/COMBO sử dụng `Product Name`.
- CHILD sử dụng `Product Child Name` cho phần tên riêng của Child.
- Full name của CHILD được suy ra từ **Parent Product Name + Product Child Name**.

**Acceptance criteria**
- CHILD không cần lưu lặp Product Name của Parent.
- Không cần persistence `full_name` riêng chỉ để lưu giá trị có thể suy ra.
- Khi Product Name của Parent thay đổi, full/display name của Child phản ánh tên Parent mới.
- Formatter có thể bổ sung dấu cách/dấu phân cách ở application layer nhưng không làm thay đổi dữ liệu canonical.

### UR-PRD-003 - Unit of Measure & Packaging
**Priority:** MUST
**Target phase:** P1

SIMPLE và CHILD có một Base UOM và có thể khai báo nhiều Alternate UOM. COMBO bắt buộc có Base UOM nhưng **không có Alternate UOM/UOM Conversion**. PARENT không cần UOM để tham gia transaction vì không selectable/transactionable.

Mọi Alternate UOM của SIMPLE/CHILD phải quy đổi **trực tiếp về Base UOM**. Không hỗ trợ conversion chain Alternate -> Alternate.

Mỗi Alternate UOM có thể được đánh dấu dùng cho:
- PURCHASE
- SALES
- BOTH

Packaging của Base UOM được quản lý tại Product. Packaging của Alternate UOM được quản lý theo chính Alternate UOM đó.

**Acceptance criteria**
- Conversion factor phải lớn hơn 0.
- Base UOM có implicit conversion factor = 1 và không cần một Alternate-UOM conversion record riêng.
- Alternate UOM không được bằng Base UOM.
- Không hỗ trợ chain kiểu `PALLET -> CARTON -> BOX -> PCS`; mỗi conversion phải đi thẳng về Base UOM.
- Giao dịch với SIMPLE/CHILD phải giữ được UOM nguồn và quantity quy đổi về Base UOM khi áp dụng Alternate UOM.
- Alternate UOM `SALES`/`BOTH` của SIMPLE/CHILD có default selling price riêng và price này không bắt buộc bằng Base-UOM price nhân conversion factor.
- Alternate UOM `PURCHASE` của SIMPLE/CHILD có thể không có default selling price.
- Packaging Alternate UOM có thể có Gross Weight và Length/Width/Height riêng.
- COMBO chỉ sử dụng Base UOM của chính COMBO; không được cấu hình UOM Conversion hoặc Alternate UOM cho COMBO.

### UR-PRD-004 - Serial Management Policy
**Priority:** MUST
**Target phase:** P1

Serial Management chỉ áp dụng cho leaf transactionable STANDARD Product:
- SIMPLE
- CHILD

PARENT không quản lý Serial. COMBO không có Serial riêng.

**Acceptance criteria**
- Serial Management flag được xác định tại Product Master.
- Product STANDARD đã bật Serial phải yêu cầu Serial trong các Inventory nghiệp vụ liên quan khi capability đó được triển khai.
- Serial Number phải duy nhất trong phạm vi Product theo rule của Inventory phase.
- PARENT và COMBO không được bật Serial Management cho chính Product đó.
- Leaf component của COMBO vẫn có thể là Product serial-managed.
- Nested COMBO không làm thay đổi Serial semantics; sau flatten Serial chỉ áp dụng cho SIMPLE/CHILD leaf.
- Sau khi Product có transaction history, không cho thay đổi Serial Management policy trực tiếp theo cách phá lịch sử.

### UR-PRD-005 - Combo Product Composition
**Priority:** MUST
**Target phase:** P1

COMBO là một Product selectable dùng như **composition/template chọn nhanh**, không phải Variant và không phải BOM/Manufacturing entity. COMBO có Base UOM, default selling price và có thể được khai báo trong Price List tại chính Base UOM đó, nhưng không giữ stock/serial/cost layer riêng.

COMBO có danh sách component Product normalized. Component được phép là:
- SIMPLE
- CHILD
- COMBO

Quantity component được khai báo theo **Base UOM của component**. Nested COMBO được phép để tái sử dụng composition, nhưng graph COMBO phải acyclic.

**Acceptance criteria**
- COMBO không được làm Parent hoặc Child.
- Component không được là PARENT.
- Component có thể là SIMPLE, CHILD hoặc COMBO.
- Không cho self-reference hoặc direct/indirect cycle như `A -> B -> A` hoặc `A -> B -> C -> A`.
- Một component không được lặp nhiều row trong cùng Combo; quantity phải lớn hơn 0.
- Quantity của nested component được nhân dọc path khi flatten; cùng một leaf xuất hiện qua nhiều path phải aggregate quantity.
- Không lưu component bằng JSON và không sử dụng `variant_id`/`child_variant_id`.
- Combo composition P1 là commercial Product composition foundation, không thay thế BOM P3.
- COMBO không có Serial và không có Alternate UOM/UOM Conversion.

### UR-PRD-006 - Combo Expansion & Selling Price Allocation
**Priority:** MUST
**Target phase:** P1

Khi COMBO được chọn trực tiếp trong nghiệp vụ bán, COMBO đó là **pricing root**. Hệ thống resolve selling price của root COMBO đúng một lần, sau đó recursive flatten toàn bộ composition tree đến SIMPLE/CHILD.

Giá/default price/Price List của nested COMBO chỉ có ý nghĩa khi nested COMBO đó được chọn trực tiếp trong một giao dịch khác; không được resolve lại khi nó chỉ là component của parent COMBO.

Sau flatten, selling amount của root COMBO được phân bổ xuống leaf dựa trên trọng số:

**Leaf Weight = Leaf Quantity × Default Base-UOM Price của Leaf**

Trong đó Default Base-UOM Price của leaf là giá mặc định tại Product Master; Price List của leaf không tham gia xác định trọng số allocation.

**Acceptance criteria**
- Root COMBO selling price được resolve theo Core Pricing hiện hành tại Base UOM của COMBO.
- Nested COMBO không được resolve giá lại trong cùng expansion tree.
- Flatten phải kết thúc bằng SIMPLE/CHILD; PARENT không thể xuất hiện như final leaf.
- Leaf quantity bằng tích quantity trên toàn path từ root COMBO đến leaf; cùng leaf qua nhiều path được aggregate trước allocation.
- Allocation weight dùng `leaf quantity × leaf default Base-UOM price`; không dùng Company/Group Price List của leaf làm weight.
- Nếu tổng allocation weight bằng 0 thì reject; hệ thống không tự chia đều.
- Calculation trung gian dùng precision đủ cao; monetary amount cuối làm tròn theo precision tiền P1.
- Rounding residual phải được xử lý deterministic để bảo đảm `SUM(final leaf allocated amount) = COMBO total selling amount`.
- Trong cùng một giao dịch, nếu cùng leaf nhận nhiều allocated price do xuất hiện trong nhiều COMBO, hệ thống aggregate `final_quantity = SUM(quantity)` và `final_amount = SUM(allocated_amount)`; `final_unit_price = final_amount / final_quantity`, tương đương weighted average theo quantity.
- Không được tính final unit price từ trung bình đơn giản của các unit price occurrence nếu làm sai tổng amount.
- Sau expansion/allocation/aggregation, Inventory, Reservation, Serial, Costing, Posting, Return và các phép tính operational khác phải sử dụng SIMPLE/CHILD leaf; COMBO không tạo stock balance, serial instance hoặc cost layer riêng.
- Transaction design ở phase vận hành phải giữ đủ traceability để xác định leaf được sinh từ COMBO nguồn khi business/audit cần.

## 9. Customer & CRM

### UR-CUS-001 - Customer Master
**Priority:** MUST
**Target phase:** P2

Customer là Group Master dùng chung trong Group.

Thông tin tối thiểu:
- Customer Code
- Tên
- Loại/nhóm Customer
- Điện thoại
- Email
- Tax Code
- Địa chỉ chi tiết
- Province/City Code
- Ward/Commune Code
- Shipping Address chi tiết
- Shipping Province/City Code
- Shipping Ward/Commune Code
- Contact Person
- Company Usage Scope hoặc `All Companies`
- Status

**Acceptance criteria**
- Customer Code là duy nhất trong Group.
- Customer có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng.
- Company ngoài Customer Usage Scope không được dùng Customer cho giao dịch mới.
- Khi triển khai Customer ở P2, Customer có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Customer.
- Customer inactive không được chọn cho giao dịch mới nhưng lịch sử giao dịch/liên hệ vẫn tra cứu được.

### UR-CUS-002 - Customer History
**Priority:** MUST
**Target phase:** P2

CRM baseline chỉ cần hồ sơ Customer và lịch sử giao dịch/liên hệ.

**Acceptance criteria**
- Lưu được note/lịch sử liên hệ.
- Xem được Sales Order/Shipment/Sales Return/Thu tiền liên quan trong phạm vi quyền.
- Không yêu cầu Lead Management hoặc Opportunity Pipeline.


---


## 10. Pricing & Promotion

### UR-PRC-001 - Price List và Price Resolution
**Priority:** MUST
**Target phase:** P1

Price Resolution P1 sử dụng input canonical:

**Product + UOM + Company + Effective Time**

Pricing scope P1 gồm:
- Company Price List.
- Group Price List.
- Default price của đúng Product + UOM.

Default price:
- SIMPLE/CHILD tại Base UOM dùng default selling price của Product.
- SIMPLE/CHILD tại Alternate UOM dùng default selling price được cấu hình riêng cho Alternate UOM đó.
- COMBO chỉ có Base UOM và dùng default selling price của COMBO Product.

Alternate-UOM price của SIMPLE/CHILD là giá cụ thể, không bắt buộc bằng Base-UOM price nhân conversion factor.

Resolution P1:

**Company matching Product+UOM -> Group matching Product+UOM -> Default price đúng UOM**

Nếu Company có Price List đang hiệu lực nhưng không có item matching đúng Product + UOM, hệ thống vẫn tiếp tục tìm Group Price List.

Trong cùng một pricing scope, candidate được chọn deterministic theo:

**Priority DESC -> Effective From DESC -> Price List ID ASC**

Effective period canonical:

**`effective_from <= evaluation_time < effective_to`**

`effective_to = NULL` nghĩa là không có upper bound. Overlap effective period được phép nếu resolution order vẫn cho một kết quả duy nhất.

Đối với COMBO được chọn trực tiếp:
- `uom_id` phải là Base UOM của COMBO.
- Resolve giá root COMBO đúng một lần theo rule trên.
- Sau đó dùng UR-PRD-006 để flatten và phân bổ selling amount xuống SIMPLE/CHILD.
- Nested COMBO không resolve lại giá trong cùng expansion tree.

**P2 - Extended Pricing Context**
- Sales Channel condition.
- Customer Group condition.
- Customer-specific condition.

Khi P2 bổ sung các condition này, UOM vẫn là dimension bắt buộc và Company scope vẫn được ưu tiên trước Group scope. Trong cùng scope, specificity có thể mở rộng theo thứ tự:

**Customer condition -> Customer Group condition -> Sales Channel condition**

sau đó mới dùng Priority/Effective From/ID để tie-break. Exact persistence của P2 condition phải được thiết kế ở P2, không tạo generic pricing condition table trong P1.

**Acceptance criteria**
- P1 Price Resolution chạy được chỉ với Product, UOM, Company và Effective Time; không cần Customer/Sales Channel tồn tại.
- SIMPLE, CHILD và COMBO được phép resolve selling price; PARENT không được dùng cho Pricing.
- Với SIMPLE/CHILD, UOM phải là Base UOM hoặc Alternate UOM hợp lệ; Alternate UOM dùng cho selling phải sales-capable.
- Với COMBO, UOM bắt buộc là Base UOM và COMBO không có Alternate UOM.
- Price List item xác định giá cụ thể cho Product + UOM; COMBO Price List item chỉ hợp lệ tại Base UOM.
- Company Price List được tìm trước Group Price List.
- Company thiếu matching Product+UOM item không chặn fallback sang Group.
- Group thiếu matching item thì fallback đúng default price của requested UOM.
- Giá default Alternate UOM không được tự suy ra từ conversion factor nếu đã có giá cấu hình riêng.
- Kết quả luôn deterministic kể cả khi effective period overlap.
- Kết quả trả về giá cuối cùng, UOM và Price Source tối thiểu là `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP` hoặc `PRODUCT_DEFAULT`; khi dùng Price List phải truy được Price List tạo ra kết quả.
- Price List hết hiệu lực/inactive không dùng cho giao dịch mới.
- Với COMBO root, Price Resolution không được resolve lại giá của nested COMBO hoặc leaf trong quá trình allocation; leaf default price chỉ được dùng làm trọng số theo UR-PRD-006.

### UR-PRC-002 - Promotion
**Priority:** MUST
**Target phase:** P2

Hỗ trợ promotion cơ bản:
- Giảm %
- Giảm số tiền
- Giá đặc biệt (Special Price)
- Quantity-based discount
- Tặng Product
- Coupon

Mỗi Promotion có tối thiểu:
- Effective Period
- Priority
- Eligibility/condition
- `Combinable = true/false`

Baseline **không cho stacking tự do**. Thứ tự xử lý canonical:
1. Xác định Special Price đủ điều kiện khi có.
2. Xử lý Discount/Promotion theo Priority.
3. Coupon chỉ được kết hợp nếu rule và `Combinable` cho phép.

**Acceptance criteria**
- Promotion chỉ áp dụng trong thời gian hiệu lực và khi đủ điều kiện.
- Sales Order lưu được promotion/coupon đã áp dụng.
- Special Price được xử lý trước Discount/Promotion khác.
- Promotion được evaluate theo Priority và không được tạo stacking tùy ý ngoài rule đã cấu hình.
- Promotion có `Combinable=false` không được kết hợp với Promotion/Coupon khác trái với rule.
- Nếu các Promotion cùng Priority và scope có thể tạo kết quả không xác định, cấu hình phải bị từ chối hoặc được điều chỉnh trước khi active.


---


## 11. Supplier

### UR-SUP-001 - Supplier Master
**Priority:** MUST
**Target phase:** P2

Supplier là Group Master dùng chung trong Group.

Thông tin tối thiểu:
- Supplier Code
- Tên
- Nhóm
- Contact
- Điện thoại/Email
- Địa chỉ chi tiết
- Province/City Code
- Ward/Commune Code
- Tax Code
- Bank Account
- Payment Term
- Company Usage Scope hoặc `All Companies`
- Status

**Acceptance criteria**
- Supplier Code là duy nhất trong Group.
- Supplier có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng.
- Company ngoài Supplier Usage Scope không được dùng Supplier cho giao dịch mới.
- Khi triển khai Supplier ở P2, Supplier có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Supplier.
- Supplier inactive không được chọn cho giao dịch mới nhưng lịch sử vẫn tra cứu được.

### UR-SUP-002 - Supplier History
**Priority:** MUST
**Target phase:** P2

Từ Supplier có thể xem lịch sử:
- Purchase Order
- Goods Receipt
- Purchase Return
- Chi/Thanh toán liên quan

**Acceptance criteria**
- Chỉ hiển thị lịch sử thuộc Company nằm trong authorization scope của User.
- Có thể điều hướng từ Supplier tới chứng từ nguồn liên quan khi User có quyền VIEW chứng từ đó.
- Lịch sử vẫn tra cứu được khi Supplier đã inactive.


---


## 12. Purchase

### UR-PUR-001 - Purchase Request
**Priority:** MUST
**Target phase:** P2

Cho phép tạo Purchase Request.

Lifecycle cơ bản:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- REJECTED
- CANCELLED

**Acceptance criteria**
- PR có thể Submit/Approve/Reject theo PBAC.
- PR Approved có thể dùng để tạo Purchase Order.

### UR-PUR-002 - Purchase Order
**Priority:** MUST
**Target phase:** P2

PO gồm tối thiểu:
- Company
- Supplier
- Product
- UOM
- Quantity
- Base Quantity quy đổi khi áp dụng
- Unit Price
- Discount
- Tax khi áp dụng
- Expected Receipt Date
- Receiving Warehouse
- Payment Term
- Status

**Acceptance criteria**
- PO tuân theo PBAC theo Company/Resource/State/Action.
- UOM phải hợp lệ cho Product; Quantity phải có thể quy đổi về Base UOM khi sử dụng Alternate UOM của SIMPLE/CHILD.
- COMBO có thể được chọn như template để nhập nhanh Purchase line, nhưng trước receiving/inventory/cost operational processing phải expand về SIMPLE/CHILD; COMBO selling-price allocation không được tự dùng làm purchase-price allocation.
- Purchase price/cost của các leaf sau expansion phải được xác định theo nghiệp vụ Purchase tương ứng, không suy từ COMBO selling price nếu chưa có requirement riêng.
- PO giữ lịch sử phê duyệt và thay đổi quan trọng.

### UR-PUR-003 - Goods Receipt
**Priority:** MUST
**Target phase:** P2

Cho phép nhận hàng một phần hoặc nhiều lần bằng chứng từ **Goods Receipt**. `Receiving` được dùng để mô tả process; tên chứng từ canonical là Goods Receipt.

**Acceptance criteria**
- Chỉ Goods Receipt đã đạt posting state của module (`POSTED` hoặc trạng thái `CONFIRMED` được định nghĩa tương đương posting) mới làm tăng On-hand Inventory.
- Goods Receipt cập nhật Received Quantity của Purchase Order nguồn.
- Product serial-managed bắt buộc ghi Serial khi Goods Receipt được post.
- Không nhận vào Warehouse thuộc Company khác Purchase Order.
- Goods Receipt đã post không được delete/edit trực tiếp các dữ liệu làm thay đổi stock; correction phải qua reversal hoặc Inventory Adjustment phù hợp.

### UR-PUR-004 - Purchase Return
**Priority:** MUST
**Target phase:** P2

Cho phép trả hàng Supplier.

**Acceptance criteria**
- Chỉ Purchase Return đã đạt posting state mới làm giảm On-hand Inventory.
- Serial-managed item phải xác định đúng Serial trả.
- Có thể truy về Goods Receipt/Purchase Order nguồn.
- Purchase Return đã post không được delete/edit trực tiếp làm thay đổi stock history; correction phải qua reversal/adjustment phù hợp.


---


## 13. Inventory

### UR-INV-001 - Inventory Balance
**Priority:** MUST
**Target phase:** P2

Theo dõi tồn theo:

**Company + Warehouse + Product**

và Serial khi áp dụng. Inventory balance canonical được hiểu theo Base UOM của Product; giao dịch có thể nhập bằng source UOM hợp lệ và quy đổi về Base UOM.

Các quantity vận hành tối thiểu:
- On-hand Qty
- Reserved Qty
- Available Qty = On-hand Qty - Reserved Qty

**Acceptance criteria**
- Có thể xem On-hand, Reserved và Available theo từng Warehouse.
- Inventory balance chỉ tồn tại cho SIMPLE/CHILD leaf; COMBO không có On-hand/Reserved/Available riêng.
- On-hand chỉ phản ánh stock movement đã được post/confirm theo posting rule.
- Có thể xem tồn Group bằng tổng hợp Company được phép.
- Không trộn tồn giữa các Company.

### UR-INV-002 - Inventory Movement
**Priority:** MUST
**Target phase:** P2

Hỗ trợ:
- Goods Receipt
- Sales Issue
- Transfer
- Adjustment
- Purchase Return
- Sales Return
- Reversal khi áp dụng

Mỗi movement lưu:
- Company
- Warehouse
- Product
- Source UOM
- Source Quantity
- Base Quantity quy đổi
- Serial khi áp dụng
- Source Document
- User
- Timestamp
- Posting Status

**Acceptance criteria**
- Chỉ movement ở posting state (`POSTED` hoặc module-defined `CONFIRMED` tương đương posting) mới làm thay đổi On-hand Qty.
- Inventory movement chỉ post SIMPLE/CHILD leaf; nếu source document sử dụng COMBO thì phải flatten trước khi tạo/post stock movement.
- Draft/Pending movement không làm thay đổi On-hand Qty.
- Movement đã post không được delete hoặc sửa trực tiếp quantity/product/warehouse làm thay đổi stock history.
- Correction sau posting phải được thực hiện bằng reversal hoặc Inventory Adjustment có traceability.
- Mỗi posted movement truy được Source Document, User và Timestamp.

### UR-INV-003 - Warehouse Transfer
**Priority:** MUST
**Target phase:** P2

Cho phép chuyển hàng giữa Warehouse trong cùng Company.

**Acceptance criteria**
- Source và destination Warehouse phải cùng Company.
- Cross-Company movement không được xử lý như internal warehouse transfer.
- Chỉ Transfer đã đạt posting state mới làm thay đổi stock location/balance.
- Posted Transfer phải giữ trace giữa source và destination movement.

### UR-INV-004 - Stock Count và Adjustment
**Priority:** MUST
**Target phase:** P2

Hỗ trợ:
- Tạo Stock Count
- Ghi nhận actual quantity
- So sánh system quantity
- Xác nhận variance
- Tạo Inventory Adjustment

**Acceptance criteria**
- Adjustment chỉ được ghi nhận theo PBAC.
- Adjustment chỉ làm thay đổi On-hand khi đạt posting state.
- Có thể truy từ Adjustment về Stock Count và người xác nhận.
- Posted Adjustment không được delete/edit trực tiếp làm thay đổi stock history.


---


### UR-INV-005 - Serial Inventory & Transaction Traceability
**Priority:** MUST
**Target phase:** P2

Đối với Product được cấu hình quản lý Serial, Inventory phải theo dõi Serial xuyên suốt các giao dịch vận hành.

**Acceptance criteria**
- Serial xác định được Product, Company, Warehouse hiện tại và trạng thái.
- Serial truy được Goods Receipt, Sales Issue, transfer, purchase return và sales return liên quan.
- Một Serial không thể đồng thời tồn tại ở nhiều Warehouse.
- Goods Receipt của Product serial-managed phải ghi nhận Serial trước khi posting.
- Sales Issue của Product serial-managed phải xác định Serial xuất.
- Warehouse Transfer phải chuyển trạng thái/location của đúng Serial.
- Purchase Return và Sales Return phải tham chiếu đúng Serial khi áp dụng.


### UR-INV-006 - Inventory Reservation
**Priority:** MUST
**Target phase:** P2

Inventory Reservation giữ một phần Available Qty cho Sales Order đã được xác nhận mà chưa được consume thành Sales Issue.

Invariant:

**Available Qty = On-hand Qty - Reserved Qty**

Lifecycle cơ bản:

**Reserve -> Release / Consume**

**Acceptance criteria**
- Reservation xác định Company, Warehouse, Product, Reserved Quantity và Source Sales Order/Order Line.
- Reservation chỉ giữ quantity của SIMPLE/CHILD leaf; COMBO phải được flatten trước reservation.
- Reservation được tạo khi Sales Order đạt trạng thái xác nhận vận hành theo lifecycle của module.
- Cancel Order hoặc giảm quantity phải release phần reservation không còn cần.
- Partial shipment/Sales Issue consume phần reservation tương ứng và giữ reservation cho phần chưa xử lý.
- Khi Sales Issue được post, phần reservation được consume đồng thời với stock issue liên quan.
- Reservation không tự làm thay đổi On-hand Qty.
- Baseline không cho reservation mới làm Available Qty âm; trường hợp overselling cần requirement/change request riêng.
- Với Product serial-managed, baseline reserve theo quantity; Serial cụ thể được chọn và validate khi Sales Issue được post.


## 14. Sales đa kênh & Vận chuyển

### UR-SAL-001 - Sales Order
**Priority:** MUST
**Target phase:** P2

Sales Order gồm:
- Company
- Customer khi áp dụng
- Sales Channel
- Product
- UOM
- Quantity
- Base Quantity quy đổi khi áp dụng
- Price
- Promotion/Discount
- Price Source
- Shipping Fee
- Warehouse
- Delivery Address chi tiết
- Delivery Province/City Code
- Delivery Ward/Commune Code
- Payment Method
- Status

`Price Source` baseline gồm:
- `EXTERNAL_CHANNEL`
- `PRICE_LIST_COMPANY`
- `PRICE_LIST_GROUP`
- `PRODUCT_DEFAULT`

**Acceptance criteria**
- Direct/Internal Sales sử dụng Price Resolution theo Product + UOM + Company + Effective Time; Sales Order lưu giá cuối cùng, UOM và Price Source `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP` hoặc `PRODUCT_DEFAULT`.
- Direct/Internal Sales cho phép chọn COMBO như template. Khi chọn COMBO, hệ thống resolve giá root COMBO, flatten về SIMPLE/CHILD, phân bổ selling amount và aggregate leaf theo UR-PRD-006 trước các bước Reservation/Inventory/Serial/Costing/Posting.
- Sales Order phải giữ đủ traceability giữa leaf line và COMBO nguồn khi COMBO được dùng để tạo nhanh line.
- Marketplace Sales từ Shopee/TikTok Shop sử dụng external channel price/discount authoritative và lưu Price Source `EXTERNAL_CHANNEL`.
- Sales Order có Price Source `EXTERNAL_CHANNEL` không bị ERP Price Resolution/Promotion recompute lại giá marketplace.
- Sales Order lưu được nguồn giá, UOM và promotion/discount reference phù hợp với loại order.
- Delivery address sử dụng Province/City Code và Ward/Commune Code khi áp dụng.
- Sales Order tuân theo PBAC theo Company/Resource/State/Action.

### UR-SAL-002 - Shopee Integration
**Priority:** MUST
**Target phase:** P2

Hệ thống nhận và quản lý đơn từ Shopee.

**Acceptance criteria**
- Lưu Shopee Order ID và Shop/Account source.
- Mapping SKU/Product.
- Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
- Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
- Giá/discount từ Shopee là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
- Đồng bộ trạng thái cần thiết.
- Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.

### UR-SAL-003 - TikTok Shop Integration
**Priority:** MUST
**Target phase:** P2

Hệ thống nhận và quản lý đơn từ TikTok Shop.

**Acceptance criteria**
- Lưu TikTok Shop Order ID và Shop/Account source.
- Mapping SKU/Product.
- Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
- Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
- Giá/discount từ TikTok Shop là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
- Đồng bộ trạng thái cần thiết.
- Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.

### UR-SAL-004 - Inventory dùng chung cho đa kênh
**Priority:** MUST
**Target phase:** P2

Shopee và TikTok Shop sử dụng Inventory của ERP làm nguồn tồn vận hành.

**Acceptance criteria**
- Không tạo kho logic riêng cạnh tranh chỉ vì khác sales channel.
- Order phải kiểm tra Available Qty theo Company/Warehouse phù hợp.
- Order được xác nhận phải reserve stock vào Inventory chung theo UR-INV-006.
- Release/consume reservation phải phản ánh vào Inventory chung.
- Sales Issue đã post làm giảm On-hand theo stock posting rule.

### UR-SAL-005 - Shipment và Carrier
**Priority:** MUST
**Target phase:** P2

Shipment quản lý:
- Company
- Warehouse/Fulfillment source
- Carrier
- Service
- Tracking Number
- Shipping Fee
- COD Amount
- Delivery Status

Carrier integration hỗ trợ:
- Lấy phí giao hàng
- Tạo vận đơn
- Nhận tracking number
- In label
- Tracking status
- Hủy vận đơn
- Nhận delivery status
- Nhận COD status/data khi carrier hỗ trợ

**Acceptance criteria**
- Shipment thuộc cùng Company với Sales Order nguồn.
- Shipment truy được Sales Order và Warehouse fulfillment liên quan.
- Tracking Number và delivery status được lưu khi carrier trả về.
- Hủy vận đơn không tự xóa Shipment hoặc lịch sử carrier interaction.
- Shipping Fee và COD Amount được lưu để phục vụ COD Reconciliation khi áp dụng.
- Carrier integration error phải được ghi nhận để retry/xử lý.

### UR-SAL-006 - COD Reconciliation
**Priority:** MUST
**Target phase:** P2

Theo từng Shipment xác định:
- COD phải thu
- COD carrier báo đã thu
- Shipping fee
- Other fee
- Net amount carrier phải chuyển
- Amount thực nhận
- Difference
- Reconciliation status

**Acceptance criteria**
- Có thể lọc các shipment chưa đối soát.
- Chênh lệch phải xác định được amount và trạng thái xử lý.
- Khoản COD đã đối soát có thể tạo/liên kết Money Receipt.

### UR-SAL-007 - Sales Return
**Priority:** MUST
**Target phase:** P2

Hỗ trợ:
- Return Request
- Receive Returned Goods
- Serial validation khi áp dụng
- Inventory update
- Refund/Thu-Chi reference khi áp dụng

**Acceptance criteria**
- Sales Return truy được Sales Order/Shipment nguồn khi có.
- Returned goods chỉ làm tăng On-hand khi inventory return movement được post/confirm theo posting rule.
- Product serial-managed phải validate đúng Serial trả lại.
- Refund hoặc giao dịch Thu/Chi liên quan được reference, không tự tạo double-entry accounting.
- Return đã post không được xóa/sửa trực tiếp làm thay đổi stock history.


### UR-SAL-008 - Marketplace Account Mapping & Order Idempotency
**Priority:** MUST
**Target phase:** P2

Marketplace Shop/Account là identity nguồn để map đơn hàng ngoài vào đúng Company và fulfillment context của ERP.

Unique business key của marketplace order:

**Sales Channel + Shop/Account + External Order ID**

**Acceptance criteria**
- Mỗi Shop/Account xác định được Sales Channel/Platform.
- Mỗi Shop/Account map đúng một Company tại một thời điểm hiệu lực.
- Shop/Account có fulfillment Warehouse mặc định hoặc mapping Warehouse rõ ràng trước khi tạo Sales Order.
- `Sales Channel + Shop/Account + External Order ID` là unique business key.
- Retry/sync lại cùng external order không được tạo duplicate ERP Sales Order.
- Sync/retry phải idempotent đối với create/link order.
- Mapping Company/Warehouse phải hoàn tất trước khi ERP Sales Order được tạo.
- Lỗi mapping hoặc sync được giữ trạng thái/lý do để reprocess.

---


## 15. Chấm công & Quản lý tiền lương

Phạm vi HCM/Payroll giữ lại các capability nghiệp vụ chính của baseline trước, nhưng được viết lại theo cấu trúc Mini ERP.

### UR-HRP-001 - Hồ sơ HR, hợp đồng và Work Calendar
**Priority:** MUST
**Target phase:** P3

Quản lý dữ liệu HR mở rộng của Employee:
- Hợp đồng
- Thuộc tính nhân sự
- Thuộc tính tiền lương
- Effective period

Employee Work Calendar gồm:
- Ca/lịch làm việc chuẩn
- Số giờ chuẩn/ngày
- Quy tắc quy đổi giờ sang ngày công
- Ngày nghỉ tuần
- Ngày nghỉ theo HR policy

**Acceptance criteria**
- Lịch sử hợp đồng/điều chuyển/trạng thái được bảo toàn.
- Mỗi ngày xác định được working day, weekly off hoặc HR holiday.
- Standard working hours/days của kỳ resolve được từ calendar có hiệu lực.

### UR-HRP-002 - Attendance
**Priority:** MUST
**Target phase:** P3

Nhân viên có thể ghi nhận:
- Working date
- Working hours
- Work description

**Acceptance criteria**
- Giữ Created/Updated By/At.
- Điều chỉnh sau thời hạn hoặc sau khi kỳ khóa phải được kiểm soát.
- Giờ nhân viên tự ghi không tự động trở thành overtime.

### UR-HRP-003 - Leave
**Priority:** MUST
**Target phase:** P3

Nhân viên có thể gửi yêu cầu nghỉ theo ngày hoặc phần ngày.

Thông tin:
- Leave type
- Period
- Reason
- Status
- Approver

**Acceptance criteria**
- Approved Leave tự động phản ánh vào Timesheet.
- Rejected/Cancelled leave không tính là nghỉ hợp lệ.
- Có thể quản lý leave balance, paid/unpaid leave và carry-forward theo policy.
- Điều chỉnh sau duyệt giữ lịch sử.

### UR-HRP-004 - Overtime
**Priority:** MUST
**Target phase:** P3

Quản lý Overtime theo:
- Type
- Hours
- Rate/Factor
- Limit
- Approval

**Acceptance criteria**
- Chỉ approved overtime được tính vào Timesheet/Payroll.
- Giờ Attendance vượt chuẩn không tự động trở thành approved overtime.
- OT truy được ngày, loại, hệ số và approver.

### UR-HRP-005 - Monthly Timesheet
**Priority:** MUST
**Target phase:** P3

Tổng hợp bảng công tháng từ:
- Work Calendar
- Attendance
- Approved Leave
- Approved Overtime
- Approved Adjustment
- Primary Assignment có hiệu lực tại từng ngày

Timesheet ownership baseline:

**Employee + Payroll Period + Company**

Bảng công thể hiện tối thiểu:
- Days present
- Working hours
- Converted working days
- Standard working days
- Paid leave
- Unpaid leave
- Approved overtime
- Calendar holidays/off days
- Missing attendance days

**Acceptance criteria**
- Mỗi ngày công được gán Company bằng cách resolve `COMPANY` từ Organization Context của Primary Assignment có hiệu lực tại ngày đó.
- Nếu Company resolve từ Primary Assignment thay đổi trong cùng kỳ thì hệ thống tạo/tổng hợp Timesheet tách theo Company; không lấy Company cuối tháng cho toàn kỳ.
- Một Employee có thể có nhiều Company Timesheet trong cùng Payroll Period khi có transfer hợp lệ.
- Ngày nghỉ hợp lệ không bị xem là missing attendance.
- Days present và converted working days là hai chỉ tiêu riêng.
- Chỉ Timesheet đã khóa được dùng làm nguồn chính thức cho Payroll.

### UR-HRP-006 - Compensation
**Priority:** MUST
**Target phase:** P3

Quản lý:
- Salary
- Allowance
- Bonus
- Penalty
- Deduction
- Other earning/deduction

Mỗi thành phần có:
- Company context khi áp dụng
- Effective period
- Calculation method: manual amount hoặc internal formula khi được cấu hình
- Lịch sử thay đổi

**Acceptance criteria**
- Tại một thời điểm xác định được Compensation component có hiệu lực.
- Thay đổi component không ghi đè làm mất lịch sử trước.
- Payroll chỉ sử dụng component có hiệu lực và thuộc Company context phù hợp.
- Internal formula baseline không được hiểu là statutory payroll compliance engine.

### UR-HRP-007 - Payroll Lifecycle
**Priority:** MUST
**Target phase:** P3

Payroll sử dụng:
- Locked Monthly Timesheet theo Company
- Effective Compensation
- Approved Adjustments

Lifecycle:
- Calculate
- Review
- Approve
- Lock

Kết quả tối thiểu:
- Company
- Gross
- Allowance/Bonus
- Deduction
- Net Pay

**Acceptance criteria**
- Payroll không tính trực tiếp từ raw attendance khi Timesheet chưa khóa.
- Payroll thuộc đúng một Company.
- Nếu Employee có Timesheet ở nhiều Company trong cùng kỳ thì Payroll được split theo Company tương ứng.
- Payroll của Company A không bao gồm ngày công thuộc Company B.
- Chỉ locked payroll period được xem là số liệu lương chính thức.
- Có thể trace từ Payroll về Timesheet và căn cứ liên quan.

### UR-HRP-008 - Payroll Adjustment
**Priority:** MUST
**Target phase:** P3

Hỗ trợ:
- Retroactive adjustment
- Back pay
- Termination
- Company/Unit transfer
- Off-cycle payroll

**Acceptance criteria**
- Adjustment liên kết kỳ gốc và Company liên quan.
- Không sửa ngược raw attendance hoặc locked Timesheet.
- Adjustment phải qua PBAC/approval phù hợp.
- Adjustment liên quan nhiều Company phải được tách thành các phần thuộc đúng Company; không tạo một transaction sở hữu đồng thời nhiều Company.

### UR-HRP-009 - Labor Cost Allocation
**Priority:** SHOULD
**Target phase:** P3

Cho phép phân bổ approved labor cost theo:
- Department

Capability này là tùy chọn và không phải prerequisite để Payroll hoạt động.

**Acceptance criteria**
- Chỉ approved/locked labor cost được dùng làm nguồn phân bổ.
- Allocation truy được Department và Company liên quan.
- Tổng amount phân bổ không vượt source labor cost nếu không có adjustment hợp lệ.
- Payroll vẫn hoạt động khi Labor Cost Allocation không được triển khai.


---


## 16. BOM & Quản lý dữ liệu sản phẩm đơn giản

### UR-BOM-001 - BOM
**Priority:** MUST
**Target phase:** P3

Một Product có thể có BOM.

BOM gồm:
- Finished Product
- Material/Component
- Required Quantity
- UOM
- Waste Rate khi áp dụng

**Acceptance criteria**
- Finished Product và Material/Component phải là Product hợp lệ.
- Required Quantity phải lớn hơn 0.
- UOM phải hợp lệ cho Product/Component tương ứng.
- Waste Rate không được âm.
- BOM giữ được danh sách component phục vụ Material Requirement và Estimated Finished Product Cost.

### UR-BOM-002 - BOM Version
**Priority:** MUST
**Target phase:** P3

Hỗ trợ nhiều phiên bản BOM.

Thông tin:
- Version
- Effective Date
- Status

**Acceptance criteria**
- Tại một thời điểm xác định được BOM mặc định.
- Version cũ vẫn tra cứu được.

### UR-BOM-003 - Material Requirement
**Priority:** MUST
**Target phase:** P3

Hệ thống tính nhu cầu vật tư theo BOM và số lượng thành phẩm cần tính.

**Acceptance criteria**
- Kết quả chỉ ra quantity từng component.
- Waste Rate được áp dụng khi có.
- Có thể truy lại BOM version đã dùng.

### UR-BOM-004 - Estimated Finished Product Cost
**Priority:** MUST
**Target phase:** P3

Tính giá thành dự kiến theo Company context từ:
- BOM
- Material Quantity
- Material Cost Source

Material Cost Source baseline gồm:
- `LAST_PURCHASE_PRICE`
- `MANUAL_STANDARD_COST`

Mỗi lần costing phải snapshot tối thiểu:
- Cost Source
- Cost Value
- Source Timestamp/Effective Time
- Company
- BOM Version

**Acceptance criteria**
- Kết quả thể hiện cost từng component và tổng estimated product cost.
- Với `LAST_PURCHASE_PRICE`, hệ thống sử dụng giá mua gần nhất có thể xác định trong Company context theo transaction nguồn hợp lệ.
- Với `MANUAL_STANDARD_COST`, hệ thống sử dụng standard cost đã cấu hình cho Product/Material.
- Mỗi component lưu được source, value và timestamp/effective time đã dùng.
- Có thể truy lại BOM version và Company context của lần costing.
- Đây là management estimate, không phải production accounting.


---


## 17. Vận hành tài chính

Finance trong Mini ERP chỉ phục vụ **tổng hợp Thu/Chi vận hành**, không phải hệ thống kế toán đầy đủ.

### UR-FIN-001 - Ghi nhận Thu/Chi
**Priority:** MUST
**Target phase:** P2

Finance dùng thuật ngữ **Money Receipt** cho giao dịch Thu và **Money Expense** cho giao dịch Chi để phân biệt với Goods Receipt.

Mỗi giao dịch gồm:
- Company
- Date
- Money Receipt/Money Expense Type
- Amount
- Cash/Bank Account
- Related Customer/Supplier/Employee khi có
- Description
- Source Reference
- Attachment
- Recorded By
- Posting Status

**Acceptance criteria**
- Mỗi Money Receipt/Money Expense thuộc đúng một Company.
- Amount phải lớn hơn 0.
- Account sử dụng phải thuộc cùng Company với transaction.
- Chỉ transaction đã `POSTED` mới tham gia operational balance.
- Transaction đã post không được xóa/sửa trực tiếp amount/account làm thay đổi history; correction phải qua reversal/adjustment có traceability.

### UR-FIN-002 - Danh mục Thu/Chi
**Priority:** MUST
**Target phase:** P2

Cho phép cấu hình category như:
- Thu bán hàng
- Thu COD
- Thu khác
- Thanh toán Supplier
- Chi vận chuyển
- Chi lương
- Chi văn phòng
- Chi khác

**Acceptance criteria**
- Category xác định được loại `RECEIPT` hoặc `EXPENSE`.
- Category inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
- Có thể lọc/tổng hợp Thu/Chi theo category.

### UR-FIN-003 - Cash và Bank Account
**Priority:** MUST
**Target phase:** P2

Quản lý danh sách:
- Cash Fund
- Bank Account

Mỗi account có tối thiểu:
- Company
- Opening Balance
- Opening Date
- Status

**Acceptance criteria**
- Mỗi account thuộc một Company.
- Opening Balance và Opening Date được xác định trước khi tính operational balance từ thời điểm đó.
- User chỉ thao tác account trong PBAC scope.
- Account inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.

### UR-FIN-004 - Tổng hợp Thu/Chi
**Priority:** MUST
**Target phase:** P2

Operational Balance được tính theo công thức:

**Balance(t) = Opening Balance + Sum(Posted Money Receipts) - Sum(Posted Money Expenses)**

trong khoảng từ Opening Date đến thời điểm `t`.

Báo cáo:
- Total Money Receipt
- Total Money Expense
- Receipt - Expense
- Opening Balance
- Closing Balance

Filter:
- Company
- Date Range
- Category
- Cash/Bank Account
- Customer
- Supplier

**Acceptance criteria**
- Chỉ posted Money Receipt/Money Expense được tính vào operational balance.
- Opening/Closing Balance không suy diễn từ General Ledger.
- Có thể tính balance theo từng Cash/Bank Account và tổng hợp các account trong Company theo filter.
- Report theo nhiều Company chỉ tổng hợp Company User có quyền xem.
- Operational Balance không được mô tả như bank reconciliation hoặc statutory accounting balance.

### UR-FIN-005 - Liên kết nguồn giao dịch
**Priority:** MUST
**Target phase:** P2

Thu/Chi có thể liên kết với:
- Sales Order
- Shipment/COD
- Purchase Order
- Supplier
- Customer
- Payroll

Liên kết phục vụ traceability/tổng hợp, không tạo double-entry accounting.

**Acceptance criteria**
- Có thể truy từ Money Receipt/Money Expense về Source Reference khi nguồn còn tồn tại và User có quyền.
- Một source reference không tự tạo double-entry journal.
- COD đã reconciliation có thể tạo/liên kết Money Receipt mà vẫn giữ Shipment/COD reference.
- Payroll payment có thể liên kết Money Expense theo đúng Company.


---


## 18. Yêu cầu dùng chung

### UR-COM-001 - Trạng thái chứng từ và Lifecycle Transition
**Priority:** MUST
**Target phase:** P1

Các Resource/chứng từ quan trọng có lifecycle rõ ràng. Lifecycle xác định legal transition; Authorization xác định User có quyền thực hiện Action tương ứng hay không.

Baseline state có thể gồm:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- REJECTED
- CONFIRMED khi module cần operational confirmation
- POSTED khi module có posting semantics
- PROCESSING
- COMPLETED
- CANCELLED

Module có thể dùng tập state riêng phù hợp với business meaning; không bắt buộc dùng mọi state trong danh sách.

**Acceptance criteria**
- Mỗi Resource sử dụng lifecycle phải xác định state hợp lệ và transition hợp lệ.
- Trong cùng một lifecycle, cùng `From State + Action` chỉ được resolve tới một destination state.
- Authorization ALLOW không tự thực hiện state transition; transition vẫn phải hợp lệ theo lifecycle.
- State không dùng trong module không bắt buộc phải triển khai chỉ vì có trong baseline list.
- CANCELLED/COMPLETED/POSTED state phải có rule rõ về Action còn được phép.
- Stock/finance impact chỉ xảy ra tại posting state được module xác định.
- P1 không yêu cầu enterprise workflow designer hoặc multi-level routing engine.

### UR-COM-002 - Approval
**Priority:** MUST
**Target phase:** P1

Approval baseline sử dụng **single-step approval**. Authorization cho Approve/Reject dựa trên các dimension baseline hỗ trợ:
- Base Permission/Resource Action
- Organization Context
- Resource Type
- Workflow State
- Participant khi áp dụng

Lifecycle approval chuẩn:

**DRAFT -> PENDING_APPROVAL -> APPROVED / REJECTED**

Lifecycle/transition rule xác định transition nào hợp lệ; Authorization xác định User có quyền thực hiện transition/action đó hay không. Baseline không yêu cầu enterprise workflow designer hoặc routing engine tổng quát.

**Acceptance criteria**
- Một transaction có tối đa một approval stage `PENDING_APPROVAL` tại một thời điểm.
- Chỉ User có Base Permission phù hợp và final Authorization = ALLOW mới có thể Approve/Reject transaction đang `PENDING_APPROVAL`.
- Baseline không có multi-level approval, approval matrix nhiều cấp hoặc amount-threshold routing.
- Không bắt buộc assigned approver riêng trên từng transaction.
- Maker/checker, self-approval restriction hoặc rule dựa trên quan hệ actor-resource/amount không được tự mô hình hóa thành arbitrary Business Condition trong PBAC baseline; nếu cần phải có requirement và additive design decision riêng.
- Approve/Reject phải giữ được actor và timestamp trong business history/audit phù hợp.


### UR-COM-003 - History/Audit cơ bản
**Priority:** MUST
**Target phase:** P1

Mutable master/config P1 và dữ liệu nghiệp vụ quan trọng phải giữ được actor/timestamp phù hợp với technical boundary.

Tối thiểu đối với mutable master/config P1:
- Created By/At
- Updated By/At

Các Action nghiệp vụ quan trọng phải có history khi module yêu cầu:
- Approve
- Reject
- Cancel
- Inventory Posting/Reversal/Adjustment
- Payroll Lock
- Thu/Chi posting/adjustment

Authorization decision audit sử dụng Authorization Audit cho các action cần log; View/List không bắt buộc persisted decision log.

**Acceptance criteria**
- Có thể xác định actor và timestamp của create/update đối với mutable master/config P1 theo schema được phê duyệt.
- Seed/system migration có thể sử dụng system/null actor theo technical convention, không giả mạo business user.
- Có thể xác định actor và timestamp của Action nghiệp vụ quan trọng.
- History không bị mất chỉ vì record chuyển inactive/cancelled.
- Posted transaction correction phải giữ trace từ transaction gốc tới reversal/adjustment.
- Authorization decision log có thể truy decision, matched policy và reason theo các field baseline hỗ trợ.
- Requirement này không tự tạo Policy Version, Permission Version hoặc full field-versioning schema chưa được phê duyệt.

### UR-COM-004 - Search/Filter/Export
**Priority:** MUST
**Target phase:** P1

Các màn hình danh sách chính hỗ trợ:
- Search
- Filter
- Sort
- Pagination
- Export khi PBAC cho phép

**Acceptance criteria**
- Search/Filter không được trả dữ liệu ngoài authorization scope.
- Sort/Pagination áp dụng nhất quán trên tập dữ liệu User được phép xem.
- EXPORT được kiểm tra như Permission độc lập, không suy ra từ VIEW.
- Export chỉ chứa dữ liệu trong Organization/Company scope được Authorization cho phép.

### UR-COM-005 - Import Capability & Explicit Resource Enablement
**Priority:** SHOULD
**Target phase:** P1

P1 cung cấp Import Framework dùng chung cho CSV/XLSX và giữ lịch sử ở mức Job + Row Result. **Import không tự áp dụng cho mọi master.** Mỗi Resource muốn import phải được explicit enable bằng requirement/Resource Action tương ứng.

**P1 enabled import target baseline**
- `PRODUCT`

Employee, Province/City, Ward/Commune và các master khác **không mặc định có import trong P1**. Target mới chỉ được bổ sung qua change control và phải có template/validation/authorization contract riêng.

**Acceptance criteria**
- Import Framework có từ P1 và có thể tái sử dụng cho Resource được enable ở phase sau.
- Mỗi Import Job xác định được entity type, execution mode (`CREATE_ONLY`/`UPDATE_ONLY`/`UPSERT`), source file metadata/status, tổng số dòng, số success/error và actor/timestamp phù hợp.
- Import report chỉ rõ success/error theo từng row gốc và giữ được row number nguồn.
- Start import cần `<resource>.import`; từng logical row vẫn phải pass canonical mutation permission tương ứng như `create`/`edit`/`setstate` tại đúng Evaluation Organization. Import không được bypass PBAC/business validation.
- Row lỗi không được ghi nhận như business data thành công; mutation nhiều bảng của một row phải rollback khi row thất bại.
- Import phải tuân cùng uniqueness/business validation như API/UI mutation.
- Product import không yêu cầu Parent xuất hiện trước Child hoặc Component xuất hiện trước Combo trong file. Importer phải resolve dependency theo multi-pass/dependency graph/topological order.
- Nested COMBO import phải detect direct/indirect cycle và reject các row liên quan thay vì tạo composition graph không hợp lệ.
- Product import phải tuân Product Model V2, UOM, pricing default, selectable-vs-leaf semantics và Combo invariant; không tạo Variant entity hoặc Product Company Usage Scope.
- Resource không có canonical action `import` không được expose import UI/API chỉ vì Import Framework tồn tại.

---


## 19. Luồng nghiệp vụ end-to-end

### 19.1 Purchase
Supplier  
-> Purchase Request  
-> Purchase Order / optional COMBO selection template  
-> COMBO expansion to SIMPLE/CHILD when applicable  
-> Goods Receipt  
-> Inventory  
-> Money Expense khi phát sinh

### 19.2 Marketplace Sales
Shopee / TikTok Shop  
-> External Price/Discount  
-> ERP Sales Order (`Price Source = EXTERNAL_CHANNEL`)  
-> Inventory Reservation  
-> Shipment  
-> Sales Issue  
-> Carrier  
-> Delivery  
-> COD  
-> COD Reconciliation  
-> Money Receipt

### 19.3 Direct/Internal Sales
Customer  
-> Sales Order / optional COMBO selection  
-> Price Resolution / Promotion theo rule áp dụng  
-> COMBO flatten + selling amount allocation + leaf aggregation trước operational downstream khi có COMBO  
-> Inventory Reservation  
-> Shipment  
-> Sales Issue  
-> Money Receipt

### 19.4 Inventory
Goods Receipt  
-> On-hand  
-> Reservation / Transfer / Adjustment  
-> Sales Issue  
-> Return / Reversal khi áp dụng

### 19.5 HR & Payroll
Employee  
-> Primary Assignment by day  
-> Work Calendar  
-> Attendance / Leave / Overtime  
-> Monthly Timesheet by Company  
-> Timesheet Lock  
-> Payroll by Company  
-> Payroll Approval  
-> Payroll Lock  
-> Money Expense chi lương

### 19.6 BOM Costing
Product  
-> BOM  
-> Material Requirement  
-> Cost Source (`LAST_PURCHASE_PRICE` / `MANUAL_STANDARD_COST`)  
-> Estimated Finished Product Cost


---


## 20. Ngoài phạm vi baseline

Baseline này không bao gồm:

**Organization / Platform**
- Multi-Group business operation trong baseline này; underlying Organization Context model không tự mở rộng business scope.
- Intercompany automation
- Multi-currency / FX conversion
- Generic `Location/Site` entity hoặc `Location Type` taxonomy tách khỏi canonical Organization Context `PLANT` nếu chưa có additive design decision.

**Approval / Workflow**
- Enterprise Workflow Engine/Designer tổng quát
- Multi-level approval workflow
- Amount-threshold approval routing/matrix
- Maker/checker hoặc self-approval rule cần actor-resource condition nhưng chưa có additive design decision.

**Product / Manufacturing extensions**
- Product Variant entity/table riêng; Product Model V2 dùng Product self-reference thay cho Variant entity.
- Product Company Usage Scope trong P1.
- Cyclic COMBO composition; nested COMBO hợp lệ phải luôn acyclic.
- Generic pricing condition table cho Customer/Customer Group/Sales Channel trong P1.
- PLM
- Engineering Change Management
- Engineering BOM
- MRP
- Production Order
- Routing
- Work Center
- Capacity Planning
- Production Scheduling
- WIP

**CRM / Analytics / Governance**
- Advanced CRM Lead/Opportunity
- Enterprise Data Governance framework
- Enterprise BI/Data Warehouse
- AI anomaly detection

**Finance / Accounting**
- General Ledger
- Full Chart of Accounts
- Double-entry Accounting
- Journal Entry
- Trial Balance
- Balance Sheet
- Statutory P&L
- Full AR Ledger
- Full AP Ledger
- Financial Close
- Tax Accounting
- Consolidation Accounting
- Bank reconciliation engine

**Payroll compliance**
- Tự động tính/đảm bảo statutory deductions hoặc statutory payroll compliance như BHXH/PIT và các nghĩa vụ pháp luật tương ứng.
- Baseline Payroll chỉ xử lý Compensation component được cấu hình thủ công hoặc internal formula; không cam kết tự động tuân thủ pháp luật payroll/tax.

**Authorization / Security extensions**
- Resource Instance Authorization.
- Arbitrary Condition Rule theo amount/value hoặc quan hệ actor-resource.
- Permission metadata `policy_required`.
- Mini ERP User Override persistence riêng, Delegation Permission, Temporary Permission và Permission effective dating nếu chưa có additive design decision; approved direct user permission addition của identity mechanism hiện hành vẫn thuộc baseline.
- Policy version/effective-dating persistence nếu chưa có additive design decision.
- Field-level masking/redaction. Baseline bảo vệ dữ liệu nhạy cảm bằng Resource/Action Authorization.

PBAC/Policy Engine **nằm trong phạm vi baseline** và là cơ chế Authorization chuẩn của hệ thống trong boundary Organization Context + Resource Type + Workflow State + Participant + Action.


---


## 21. Ghi chú kiểm soát phạm vi

- Mỗi functional `UR-*` trong baseline này được gán `Priority`, `Target phase` và có `Acceptance criteria`.
- P1 tập trung Foundation, Organization, Shared Contact Management, Employee, PBAC/Policy Engine, basic Workflow/Lifecycle, Product Model V2/UOM/Combo composition + flattening/allocation, Core Pricing và shared Import Framework.
- P2 triển khai các module vận hành còn lại ngoại trừ HCM/Payroll và BOM; đồng thời có thể mở rộng Pricing theo Customer/Customer Group/Sales Channel mà không phá P1 Product+UOM và Company->Group->Default semantics.
- P3 triển khai HCM/Payroll và BOM/Estimated Product Costing.
- Phase Planning chi tiết có thể bổ sung dependency, milestone và delivery sequencing nhưng không được tự thay đổi scope/invariant đã khóa.
- Functional Requirements được phân rã từ từng `UR-*` và phải giữ nguyên các invariant Product/Pricing/Authorization/Workflow đã được harmonize.
- Scope Exclusion, Assumption, Glossary và Business Invariant không được tự chuyển thành capability mới nếu không có functional UR hoặc change request được phê duyệt.
- Không thêm Variant entity, Product Company Usage Scope, persisted Profile, Permission table riêng, Resource Instance Authorization hoặc arbitrary PBAC condition trong FRS P1.
- Revision `1.0.9-draft` là harmonization revision theo Database Design P1 v1.6 và Implementation Contract v1.1 baseline; chỉ đổi status khi review cuối xác nhận không còn conflict business requirement chưa xử lý.

---


## 22. Thuật ngữ chuẩn

- **Goods Receipt:** tên chứng từ inbound stock từ Purchase. `Receiving` chỉ mô tả process.
- **Money Receipt / Money Expense:** giao dịch Thu/Chi trong Finance; dùng để tránh nhầm với Goods Receipt.
- **Price List / Price Resolution:** thuật ngữ pricing chuẩn. Không dùng `Pricing Policy` vì `Policy` được dành cho PBAC/Authorization.
- **Price Source:** `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP`, `PRODUCT_DEFAULT`, hoặc `EXTERNAL_CHANNEL` đối với marketplace authoritative price.
- **Organization Context:** node tổ chức canonical trong shared database. Type: `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`.
- **Organization Group:** Organization Context type `GROUP`; business scope có một Group root.
- **Authorization Group:** nhóm quyền/User membership phục vụ Effective Profile; không phải Organization Group, Company, Department hoặc Position.
- **Branch:** Organization Context type `BRANCH`.
- **Plant/Site:** cách gọi nghiệp vụ cho Organization Context type `PLANT`; không tạo Location/Site entity riêng trong baseline.
- **Warehouse:** Organization Context type `WAREHOUSE`, dùng làm context tồn kho và phải resolve Company ancestor phù hợp.
- **Department / Position:** master độc lập; quan hệ với Employee/Organization qua Employee Assignment.
- **Shared Contact:** capability dùng chung, liên kết polymorphic bằng owner type + owner reference; P1 dùng cho Employee/Organization.
- **Effective Profile:** tập Base Permission runtime sau khi resolve Authorization Group Permission và approved direct user permission additions; không hàm ý persisted Profile table.
- **Permission:** quyền cơ sở dạng `resource.action`, hình thành từ Resource + Resource Action.
- **Policy:** PBAC restriction trên Permission đã tồn tại theo Organization Context + Resource Type + Workflow State + Participant + Action; không tạo Permission mới.
- **Authorization Lane:** nhánh đánh giá quyền theo actor (`USER`/`GROUP`) hoặc theo một Employee Assignment (`DEPARTMENT`/`POSITION`) tại một Evaluation Organization Context.
- **KQ Denied Set:** tập `Resource + Action` bị cấm của một lane/Organization/Company sau khi áp dụng inheritance và các rule merge tương ứng.
- **Workflow State:** trạng thái lifecycle của Resource; legal transition tách khỏi Authorization decision.
- **Posting State:** trạng thái làm phát sinh stock/finance impact; module xác định state tương đương posting.
- **Product Model V2:** mô hình một Product entity duy nhất; mọi SKU selectable/operational là Product, không có Variant entity/table.
- **SIMPLE:** STANDARD root Product không có Child; selectable và leaf transactionable.
- **PARENT:** STANDARD root Product có Child; logical grouping Product, không selectable và không transactionable.
- **CHILD:** STANDARD Product có Parent; selectable và leaf transactionable; full name suy ra từ Parent Product Name + Product Child Name.
- **COMBO:** Product type `COMBO`; selectable composition/template có Base UOM và selling price riêng, có thể chứa SIMPLE/CHILD/COMBO khác nhưng phải acyclic; không phải inventory/serial/costing/posting leaf và không phải BOM P3.
- **Combo Root:** COMBO được người dùng chọn trực tiếp; selling price của Combo Root được resolve đúng một lần trước khi flatten.
- **Combo Flattening:** recursive expansion COMBO tree đến SIMPLE/CHILD; leaf quantity được nhân theo path và aggregate khi cùng leaf xuất hiện nhiều nhánh.
- **Combo Price Allocation:** phân bổ selling amount của Combo Root xuống leaf theo trọng số `leaf quantity × leaf default Base-UOM price`, bảo toàn tổng amount sau rounding.
- **Weighted Leaf Price:** final unit price của cùng leaf khi nhận nhiều allocated amount = tổng allocated amount / tổng quantity.
- **Base UOM:** đơn vị chuẩn của SIMPLE/CHILD/COMBO selectable; COMBO chỉ có Base UOM, còn SIMPLE/CHILD có thể có Alternate UOM.
- **Alternate UOM:** UOM của SIMPLE/CHILD quy đổi trực tiếp về Base UOM; có thể có default selling price và packaging riêng; không áp dụng cho COMBO.
- **Group Price List:** Price List ở Organization scope GROUP.
- **Company Price List:** Price List ở Organization scope COMPANY; được ưu tiên trước Group Price List.
