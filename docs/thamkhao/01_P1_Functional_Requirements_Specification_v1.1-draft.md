---
document_id: "FRS-MINI-ERP-P1-001"
title: "P1 Functional Requirements Specification - Dinktech Mini ERP"
version: "1.1-draft"
status: "draft"
owner: "Product / Architecture"
date: "2026-10-04"
scope: "P1 - Foundation, Master Data & Authorization"
---

# P1 Functional Requirements Specification - Dinktech Mini ERP

## 1. Mục đích tài liệu

Tài liệu này mô tả yêu cầu chức năng chi tiết cho Phase P1 của Dinktech Mini ERP. FRS chuyển các User Requirements P1 thành hành vi hệ thống có thể thiết kế UI/service, kiểm thử và triển khai, đồng thời tuân thủ technical boundary đã được khóa trong Database Design P1.

FRS trả lời các câu hỏi chính:

- Người dùng hoặc hệ thống có thể thực hiện chức năng nào trong P1.
- Điều kiện trước khi thực hiện là gì.
- Hệ thống phải validate dữ liệu như thế nào.
- Kết quả thành công hoặc thất bại phải thể hiện ra sao.
- Authorization, Workflow, Audit và Organization Context tác động tới chức năng như thế nào.
- Những hành vi nào phải được dùng lại bởi các module P2/P3 nhưng không được tạo transaction persistence trong P1.

FRS không thay thế Database Design, không định nghĩa migration và không được tạo persistence mới trái với Database Design P1 baseline.

## 1.1 Nguồn và thứ tự authority

FRS này sử dụng các nguồn sau:

1. `00_User_Requirements_v1.0.7-draft.md` - business intent, user need, priority và acceptance criteria P1.


User Requirements v1.0.7-draft đã được harmonize với Database Design v1.4 cho Product Model V2, COMBO, UOM, Core Pricing, Authorization, Workflow và Import. FRS phải giữ nguyên business intent của User Requirements và không tạo persistence/capability trái Database Design. Nếu phát sinh xung đột mới giữa business requirement và technical boundary, xung đột phải được ghi nhận và xử lý qua change control; FRS không tự âm thầm chọn một mô hình thứ ba.

### 1.1.1 Trạng thái harmonization hiện tại

Tại thời điểm hoàn thiện revision này, không còn delta đã biết giữa User Requirements v1.0.7-draft

- SIMPLE/CHILD là selectable leaf transactionable Product.
- COMBO là selectable composition/template có Base UOM, default price và Price List riêng nhưng không phải inventory/serial/costing/posting leaf.
- COMBO không có Serial và không có UOM Conversion; nested COMBO được phép nhưng graph phải acyclic.
- COMBO root được resolve giá một lần, flatten về SIMPLE/CHILD, phân bổ selling amount theo default-price weight và aggregate cùng leaf bằng tổng quantity/tổng amount.
- Core Pricing dùng Product + UOM + Company + Effective Time với fallback Company -> Group -> default đúng UOM.
- Policy, Workflow và Import sử dụng đúng semantics đã harmonize trong hai nguồn trên.

## 1.2 Phạm vi P1

P1 bao gồm các capability chức năng sau:

1. Administrative Location.
2. Shared Contact Management.
3. Organization Context.
4. Department, Position, Employee và Employee Assignment.
5. Identity bridge và Effective Profile.
6. Resource/Action, Authorization Group và PBAC Policy.
7. Authorization Audit.
8. Workflow State, Lifecycle và Lifecycle Transition cơ bản.
9. Product Model V2.
10. Product Group và Product UOM.
11. Alternate UOM cho SIMPLE/CHILD.
12. COMBO Composition, flatten và allocation service.
13. Core Pricing theo Product + UOM + Company + Effective Time.
14. Import Framework cho master P1.
15. Search/Filter/Sort/Pagination/Export dùng chung.
16. Audit actor/timestamp cho mutable master/config P1.

## 1.3 Ngoài phạm vi P1

Các capability sau không được FRS này tự triển khai persistence hoặc business module đầy đủ:

- Customer/Supplier master persistence.
- Purchase transaction tables.
- Inventory transaction/balance/reservation tables.
- Sales Order/Shipment/Return transaction tables.
- Promotion engine.
- Serial instance inventory.
- HCM/Payroll transaction tables.
- BOM/Manufacturing tables.
- Multi-level approval.
- Resource Instance Authorization.
- Arbitrary business condition rule.
- Policy version/effective dating.
- Persisted Profile.
- Mini ERP User Override store riêng.
- Delegation/Temporary Permission.
- Multi-currency và FX.
- Generic Pricing Condition cho Customer/Customer Group/Sales Channel.

Các module P2/P3 phải tái sử dụng invariant P1 đã khóa thay vì tạo mô hình cạnh tranh.

## 1.4 Quy ước mức độ yêu cầu

- **MUST**: bắt buộc để P1 được chấp nhận.
- **SHOULD**: nên có trong P1 nhưng có thể triển khai sau nếu không phá data contract và được chấp thuận.
- **MAY**: tùy chọn, không được làm thay đổi invariant baseline.

## 1.5 Actors

Các actor chức năng chính:

- **System Admin**: root identity theo authority baseline; exact bypass mechanism thuộc application/security layer và chưa được FRS này tự phát minh.
- **ERP User**: User có Employee mapping và context phù hợp.
- **Business Administrator**: User có quyền quản trị Organization/Employee/Product/Pricing theo PBAC.
- **Authorization Administrator**: User có quyền quản trị Resource/Action/Group/Policy.
- **Data Import Operator**: User có quyền import master tương ứng.
- **Background/System Process**: seed, migration hoặc process hệ thống; actor audit có thể NULL/system theo convention đã phê duyệt.

---

# 2. Quy tắc chức năng dùng chung

## FRS-P1-GEN-001 - Organization Context bắt buộc khi chức năng cần scope

**Priority:** MUST

Hệ thống phải phân biệt Organization Context với Authorization Group.

Organization Context canonical gồm:

- GROUP
- COMPANY
- BRANCH
- PLANT
- WAREHOUSE

Khi một chức năng cần Company owner, hệ thống phải resolve từ Organization Context hiện tại tới chính COMPANY hoặc COMPANY ancestor gần nhất.

**Acceptance criteria**

- Context BRANCH/PLANT/WAREHOUSE resolve được đúng một Company ancestor trước khi dùng cho nghiệp vụ yêu cầu Company.
- GROUP không được dùng như transaction owner trực tiếp.
- Authorization Group không được dùng thay Organization Context.

## FRS-P1-GEN-002 - Active/inactive selection rule

**Priority:** MUST

Master/config inactive hoặc closed vẫn được giữ để đọc lịch sử nhưng không được chọn cho cấu hình/giao dịch mới trừ khi requirement cụ thể cho phép.

**Acceptance criteria**

- Record inactive vẫn có thể hiển thị trong lịch sử/reference cũ.
- Selector tạo mới mặc định chỉ trả record đủ điều kiện active.
- Không hard delete record đã có business/audit reference nếu baseline yêu cầu giữ lịch sử.

## FRS-P1-GEN-003 - Audit actor/timestamp

**Priority:** MUST

Mutable master/config P1 phải ghi được Created By/At và Updated By/At theo schema đã khóa.

**Functional behavior**

- Create thành công ghi actor tạo và thời điểm tạo.
- Update thành công ghi actor sửa và thời điểm sửa.
- Seed/system migration có thể dùng actor NULL/system theo technical convention.
- Hệ thống không được giả mạo một business User cho seed/migration.

**Acceptance criteria**

- Có thể truy actor/timestamp của bản ghi mutable P1.
- Update không ghi audit actor nếu transaction bị rollback.

## FRS-P1-GEN-004 - Authorization trước mutation/export

**Priority:** MUST

Mọi operation protected phải evaluate Resource/Action authorization trước khi thực hiện mutation hoặc export.

**Acceptance criteria**

- VIEW không suy ra EDIT/DELETE/EXPORT.
- EXPORT được kiểm tra như action độc lập.
- Authorization DENY không được ghi partial business mutation.
- Khi action gắn với lifecycle transition, cả transition validity và authorization đều phải pass.

## FRS-P1-GEN-005 - Validation failure và transaction boundary

**Priority:** MUST

Mutation nhiều bảng hoặc mutation có invariant liên quan phải atomic.

**Acceptance criteria**

- Validation fail không để lại partial Policy/Policy Action.
- Validation fail không để lại partial Assignment.
- Validation fail không để lại partial Product composition/UOM/Price List batch.
- Import row nhiều mutation phải rollback toàn row khi row lỗi.
- Exact HTTP status/error-code catalog không được tự đặt trong FRS này; service phải trả semantic error đủ rõ để UI/API map theo contract sau.

## FRS-P1-GEN-006 - Search, filter, sort, pagination

**Priority:** MUST

Danh sách master/config P1 phải hỗ trợ search/filter/sort/pagination phù hợp với domain.

**Acceptance criteria**

- Query chỉ trả dữ liệu trong scope User được phép xem.
- Pagination áp dụng sau authorization/data-scope filtering logic phù hợp.
- Search không làm lộ record ngoài scope thông qua count/autocomplete ngoài quyền.

## FRS-P1-GEN-007 - Currency và monetary precision P1

**Priority:** MUST

P1 sử dụng VND là currency duy nhất. Multi-currency và FX conversion nằm ngoài phạm vi baseline. Monetary output/persistence phải tuân precision tiền đã khóa trong Database Design; các phép tính trung gian cần độ chính xác cao hơn, đặc biệt COMBO allocation, không được round sớm làm sai tổng amount.

**Acceptance criteria**

- Product default price, Alternate-UOM selling price, Price List price và COMBO allocated amount trong P1 được hiểu là VND.
- P1 không yêu cầu chọn currency hoặc FX rate khi tạo Product/Price List.
- Monetary result cuối tuân precision 2 chữ số thập phân của baseline.
- COMBO allocation phải giữ precision trung gian đủ cao và chỉ round ở bước monetary result cuối theo rule allocation.

---

# 3. Administrative Location

## FRS-P1-LOC-001 - Quản lý Province/City

**Priority:** MUST
**Actors:** Business Administrator

Hệ thống cho phép xem, tạo, sửa và chuyển trạng thái Province/City.

**Thông tin chức năng**

- Code.
- Name.
- Status ACTIVE/INACTIVE.

**Validation**

- Code bắt buộc và duy nhất.
- Record inactive không dùng cho dữ liệu mới.

**Acceptance criteria**

- Tạo Province/City với code trùng bị reject.
- Inactivate Province không xóa Ward/Contact/Organization lịch sử đang tham chiếu.
- List/search theo code, name, status.

## FRS-P1-LOC-002 - Quản lý Ward/Commune

**Priority:** MUST
**Actors:** Business Administrator

Hệ thống cho phép quản lý Ward/Commune thuộc một Province/City.

**Thông tin chức năng**

- Province/City.
- Code.
- Name.
- Full Name khi cần display/search.
- Status.

**Validation**

- Province bắt buộc và phải tồn tại.
- Ward mới chỉ được chọn Province active.
- Code duy nhất.

**Acceptance criteria**

- Không tạo Ward với Province không tồn tại.
- Selector Ward có thể filter theo Province.
- Ward inactive vẫn hiển thị ở dữ liệu lịch sử.

---

# 4. Shared Contact Management

## FRS-P1-CON-001 - Quản lý Contact dùng chung

**Priority:** MUST
**Actors:** Business Administrator, ERP User có quyền phù hợp

P1 hỗ trợ Contact cho tối thiểu:

- EMPLOYEE
- ORGANIZATION

Một owner có thể có nhiều Contact.

**Thông tin Contact**

- Description.
- Status.
- Name.
- Email.
- Phone.
- Mobile.
- Address.
- Province/City.
- Ward/Commune.
- Province/Ward display snapshot khi áp dụng.

**Functional behavior**

- Create Contact từ màn hình owner hoặc qua service với `contact_type + contact_ref_id`.
- Update Contact không thay đổi owner âm thầm nếu operation không được thiết kế là transfer.
- Inactivate/close Contact thay vì hard delete khi cần giữ lịch sử.

**Validation**

- Owner phải tồn tại theo contact type.
- P1 chỉ chấp nhận contact type đã hỗ trợ.
- Nếu cả Province và Ward được nhập, Ward phải thuộc Province tương ứng.

**Acceptance criteria**

- Employee có thể có nhiều Contact.
- Organization có thể có nhiều Contact.
- Contact của Employee inactive vẫn tra cứu được.
- Search Contact hỗ trợ tối thiểu theo Name, Email, Phone hoặc Mobile trong authorization scope.
- Không cần bảng Contact riêng cho Employee hoặc Organization.

## FRS-P1-CON-002 - Contact selector/history

**Priority:** MUST

**Functional behavior**

- Cho phép xem Contact active và history Contact inactive/closed của owner.
- UI mặc định ưu tiên active Contact nhưng phải có filter history.

**Acceptance criteria**

- Inactivate owner không tự hard delete Contact.
- Search Contact không trả owner ngoài authorization scope.

---

# 5. Organization Management

## FRS-P1-ORG-001 - Quản lý Organization Context

**Priority:** MUST
**Actors:** Business Administrator

Hệ thống cho phép quản lý Organization Context với type canonical:

- GROUP
- COMPANY
- BRANCH
- PLANT
- WAREHOUSE

**Thông tin chung**

- Parent Organization.
- Type.
- Code.
- Name.
- Description.
- Status.
- Active From/To.

**Thông tin Company khi áp dụng**

- Legal Name.
- Tax Code.
- Address.
- Province/City.
- Ward/Commune.

**Acceptance criteria**

- Code duy nhất.
- Business scope chỉ có một GROUP root.
- Tạo/sửa parent phải tuân parent matrix.
- Không self-parent hoặc cycle.
- Active To không trước Active From.
- Ward phải thuộc Province khi cùng khai báo.

## FRS-P1-ORG-002 - Parent matrix

**Priority:** MUST

Allowed hierarchy:

| Parent | Allowed child |
|---|---|
| ROOT | GROUP |
| GROUP | COMPANY |
| COMPANY | BRANCH, PLANT, WAREHOUSE |
| BRANCH | PLANT, WAREHOUSE |
| PLANT | WAREHOUSE |
| WAREHOUSE | none |

Same-type nesting không được phép trong P1.

**Acceptance criteria**

- GROUP -> COMPANY hợp lệ.
- COMPANY -> WAREHOUSE hợp lệ.
- COMPANY -> COMPANY bị reject.
- WAREHOUSE -> bất kỳ child nào bị reject.
- Re-parent gây cycle bị reject.

## FRS-P1-ORG-003 - Resolve Company ancestor

**Priority:** MUST

System service phải có khả năng resolve Company owner từ Organization Context.

**Functional behavior**

- Nếu node hiện tại là COMPANY, trả chính node đó.
- Nếu BRANCH/PLANT/WAREHOUSE, traverse ancestor tới COMPANY gần nhất.
- Nếu không resolve được COMPANY trong operation bắt buộc Company, operation fail.

**Acceptance criteria**

- Warehouse thuộc Company A không resolve sang Company B.
- GROUP không resolve thành Company.

## FRS-P1-ORG-004 - Organization tree view

**Priority:** SHOULD

UI nên hiển thị cây Organization đúng parent relation và status.

**Acceptance criteria**

- Có thể expand/collapse từ GROUP tới Warehouse.
- Không đưa Department/Position vào như Organization node.

## FRS-P1-ORG-005 - Company-scope view và multi-company aggregation

**Priority:** MUST

Khi một list/report/chức năng hỗ trợ Company scope, User phải có thể chọn một hoặc nhiều Company trong tập Company mà Authorization cho phép. Group-level view chỉ là phép tổng hợp dữ liệu từ các Company được phép, không biến GROUP thành transaction owner.

**Functional behavior**

- Single-company view/filter chỉ dùng Company User được phép xem.
- Multi-company view/filter nhận một tập Company được authorize và aggregate kết quả theo chức năng tương ứng.
- Nếu request chứa Company ngoài authorization scope, hệ thống phải reject hoặc loại khỏi result theo contract chức năng đã định nghĩa; không được làm lộ dữ liệu ngoài quyền.
- Transaction ownership của từng record nguồn vẫn giữ nguyên Company; aggregation không merge/chuyển ownership.

**Acceptance criteria**

- User có quyền Company A và B có thể xem/tổng hợp A+B khi chức năng hỗ trợ multi-company.
- User không có quyền Company C không thấy dữ liệu C trong result/count/export của cùng chức năng.
- Group-level summary bằng tổng hợp các Company được phép, không đọc GROUP như một transaction owner.

---

# 6. Employee, Department và Position

## FRS-P1-EMP-001 - Quản lý Department

**Priority:** MUST
**Actors:** Business Administrator

Department là master độc lập với Organization.

**Thông tin**

- Parent Department.
- Code.
- Name.
- Description.
- Status.

**Validation**

- Code unique.
- Không self-parent/cycle.
- Không có Organization ownership trực tiếp.

**Acceptance criteria**

- Một Department có thể được dùng trong Assignment thuộc nhiều Organization khi business cho phép.
- Department inactive không được dùng cho Assignment mới.

## FRS-P1-EMP-002 - Quản lý Position

**Priority:** MUST

**Thông tin**

- Code.
- Name.
- Level khi áp dụng.
- Description.
- Status.

**Validation/Acceptance**

- Code unique.
- Position không sở hữu Organization hoặc Department trực tiếp.
- Position inactive không được dùng cho Assignment mới.

## FRS-P1-EMP-003 - Quản lý Employee Master

**Priority:** MUST

**Thông tin tối thiểu**

- User mapping khi có.
- Employee Code.
- Họ tên.
- Gender.
- Date of Birth.
- Place of Origin.
- Hire Date.
- Direct Manager hiện tại.
- Description.
- Status.
- Contacts dùng Shared Contact.

**Functional behavior**

- Employee có thể tồn tại mà không có User.
- Một User tối đa map một Employee.
- Employee inactive không bị hard delete.

**Validation**

- Employee Code unique.
- User mapping unique khi có.
- Manager không được là chính Employee.
- Gán manager phải reject nếu tạo management cycle.
- Employee inactive không được chọn làm manager mới.

**Acceptance criteria**

- Có thể quản lý Employee chưa có tài khoản đăng nhập.
- Không thể map cùng User vào hai Employee.
- A -> B -> C -> A bị reject.
- Direct manager history effective-dated không được tự tạo trong P1.

## FRS-P1-EMP-004 - Quản lý Employee Assignment

**Priority:** MUST

Assignment liên kết Employee với:

- Organization Context.
- Department khi áp dụng.
- Position khi áp dụng.
- Primary flag.
- Effective From/To.
- Status.

**Functional behavior**

- Employee có thể có nhiều Assignment active, kể cả trong nhiều Company.
- Tại một thời điểm chỉ một Primary Assignment effective.
- Primary Assignment chỉ là nguồn mặc định để resolve actor Organization Context khi operation không có Evaluation Organization Context rõ ràng.
- Secondary Assignment effective có thể tham gia Department/Position authorization lane khi Assignment đó thuộc Company/Organization scope đang evaluate.

**Validation**

- Effective To >= Effective From nếu có.
- Organization/Department/Position mới chọn phải active.
- Primary Assignment không được overlap với Primary Assignment khác của cùng Employee.

**Acceptance criteria**

- Có thể chuyển Employee sang Organization/Department/Position khác bằng Assignment mới mà không tạo Employee mới.
- Secondary Assignment không thay Primary default context.
- Employee có nhiều Assignment ở nhiều Company phải được partition đúng Company khi Authorization.
- Lịch sử Assignment vẫn tra cứu được sau khi kết thúc.

## FRS-P1-EMP-005 - Resolve actor context

**Priority:** MUST

FRS phân biệt:

```text
Default Actor Organization Context
    = User -> Employee -> Primary Assignment tại Evaluation Time
```

và:

```text
Evaluation Organization Context
    = Organization Context của operation/resource đang authorize
```

Nếu operation không cung cấp Evaluation Organization Context rõ ràng thì dùng Default Actor Organization Context. Khi cần Company:

```text
Evaluation Organization Context -> self/ancestor COMPANY
```

**Acceptance criteria**

- Primary Assignment chỉ dùng để resolve default context khi operation không cung cấp context rõ ràng.
- Secondary Assignment không thay Primary default context.
- Secondary Assignment effective được phép tham gia Department/Position participant matching khi cùng Company và thuộc Organization scope đang evaluate.
- Assignment thuộc Company khác không được tham gia participant matching.
- Không có context cần thiết cho operation thì không tự tạo ALLOW.

---

# 7. Identity, Permission và Authorization Group

## FRS-P1-IAM-001 - Shield Identity integration

**Priority:** MUST

Authentication/session/token do identity system hiện hành quản lý. Mini ERP không tạo local password/session store cạnh tranh.

**Functional behavior**

- Employee có thể map tới Shield User.
- User group membership đọc từ Shield group membership.
- Direct permission additions đọc từ identity permission mechanism hiện hành.

**Acceptance criteria**

- User inactive/locked không được thực hiện operation mới theo identity policy.
- Mini ERP không tạo bảng User identity thay thế Shield.

## FRS-P1-IAM-002 - Resource và Action catalog

**Priority:** MUST

Permission runtime canonical:

```text
<resource.type>.<action.code>
```

Baseline action có thể gồm:

- view
- create
- edit
- delete
- submit
- approve
- reject
- cancel
- post
- close
- export
- print
- setstate

**Functional behavior**

- Quản trị Resource Type.
- Quản trị Action thuộc Resource.
- Mỗi Resource + Action là một permission unit.

**Validation**

- Resource type unique.
- Action code unique trong Resource.

**Acceptance criteria**

- `product.view` không đồng nghĩa `product.edit`.
- Không tạo Permission table riêng.

## FRS-P1-IAM-003 - Authorization Group

**Priority:** MUST

Authorization Group là nhóm quyền, không phải Organization GROUP.

**Functional behavior**

- Create/Edit/Inactivate Authorization Group.
- `groups.code` là canonical alias map tới Shield membership group string.
- Gán/bỏ Resource Action cho Group.

**Validation**

- Group code unique.
- Group permission không có Organization scope riêng.

**Acceptance criteria**

- Membership trong Shield group map đúng Group code.
- Inactive Group không được tính như active authorization source theo runtime rule.

## FRS-P1-IAM-004 - Effective Profile resolution

**Priority:** MUST

Base Permission của User là union của:

1. Group Permission từ các active Authorization Group mà User là member.
2. Direct user permission addition map hợp lệ vào `resource.action` catalog.

**Rules**

- Direct permission chỉ là ALLOW addition.
- Không có direct DENY trong Effective Profile.
- Effective Profile không persistence thành Profile table.
- Direct permission không bypass Policy/ABSOLUTE_DENY.

**Acceptance criteria**

- User có permission từ bất kỳ Group active hợp lệ nào thì permission đó vào Base Permission.
- Direct permission không map được vào catalog thì không tạo permission ERP hợp lệ.

---

# 8. PBAC Policy Engine

## FRS-P1-PBAC-001 - Quản lý Policy

**Priority:** MUST
**Actors:** Authorization Administrator

Một Policy xác định:

- Organization Context.
- Resource.
- Workflow State hoặc all states.
- Participant Type.
- Participant Reference.
- Một hoặc nhiều Policy Action/effect phù hợp.

Participant type P1:

- USER
- GROUP
- POSITION
- DEPARTMENT
- ORGANIZATION

**Rules**

- GROUP là canonical participant value cho Authorization Group.
- Dữ liệu mới không ghi ROLE.
- State NULL nghĩa là all states của Resource phù hợp.
- Participant reference được validate theo participant type.

**Acceptance criteria**

- Không thể gán GROUP participant tới Organization id.
- Policy Action phải thuộc cùng Resource với Policy.
- Policy không tạo Base Permission mới.

## FRS-P1-PBAC-002 - Participant matching

**Priority:** MUST

Khi evaluate Policy, system phải partition actor theo Company và build các authorization lane phù hợp với Evaluation Organization Context.

**Participant inputs**

- `USER`: current Shield User.
- `GROUP`: active Authorization Groups current User là member; tham gia actor-level lane.
- `POSITION`: Position từ Employee Assignment effective phù hợp.
- `DEPARTMENT`: Department từ Employee Assignment effective phù hợp.
- `ORGANIZATION`: contextual participant của Organization đang evaluate hoặc ancestor applicable theo inheritance.

**Assignment eligibility**

Với Evaluation Organization Context `O`:
- resolve đúng một Company owner của `O`;
- chỉ xét Assignment effective của Employee thuộc Company đó;
- Assignment ở Organization nằm trên path phù hợp tới `O` có thể cung cấp DEPARTMENT/POSITION participant theo business scope đã cấu hình;
- Assignment thuộc Company khác hoặc sibling Organization ngoài path không được trộn vào lane hiện tại;
- Primary flag không phải điều kiện duy nhất để Assignment tham gia PBAC; Primary chỉ quyết định default actor context.

**Acceptance criteria**

- Employee có nhiều Assignment trong nhiều Company được partition đúng Company.
- Một User có nhiều Department/Position trong cùng scope có nhiều assignment lane độc lập trước bước merge.
- Policy participant không match thì Policy đó không tham gia QT1 của lane tương ứng.
- Không dùng participant như arbitrary business condition.

## FRS-P1-PBAC-003 - Policy inheritance theo Organization

**Priority:** MUST

Inheritance được resolve **trước** multi-lane intersection.

Với một Company và Evaluation Organization `O` thuộc `BRANCH`/`PLANT`/`WAREHOUSE`, Policy source canonical:

```text
COMPANY
+ ancestors của O nằm trên path trong Company
+ O
```

**Rules**

- Department/Position lane tại `O` phải xét Policy applicable kế thừa từ Organization phía trên.
- Actor-level lane tại `O` cũng phải xét Policy applicable theo cùng ancestor chain.
- `ORGANIZATION` participant rule applicable tại Company/ancestor/current Organization là contextual restriction chung cho scope tương ứng.
- Không copy Policy vật lý xuống child; inheritance được resolve runtime.
- Hierarchy depth không phải Policy Priority.
- Applicable `ABSOLUTE_DENY` tại Company/ancestor/current Organization phải được propagate vào mọi KQ lane con chịu scope trước intersection.

**Acceptance criteria**

- Company Policy có thể tác động tới Branch/Plant/Warehouse descendant nếu các dimension/participant còn lại match.
- Branch Policy có thể tác động tới Plant/Warehouse descendant nằm dưới Branch đó.
- Policy của sibling Organization không được kế thừa ngang.
- Child Policy không tự vô hiệu Parent Policy chỉ vì nằm sâu hơn.
- `ABSOLUTE_DENY` inherited không bị mất sau multi-role intersection.

## FRS-P1-PBAC-004 - Canonical authorization evaluation và multi-role merge

**Priority:** MUST

QT1 trong từng lane:

```text
ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH
NO_MATCH => ALLOW
```

`KQ[x]` là tập `Resource + Action` bị cấm sau khi lane `x` đã resolve inheritance và QT1.

Canonical algorithm:

```text
if no base permission(Resource, Action):
    DENY
else:
    partition evaluation by Company

    FOR EACH Organization O
        where O.type in [BRANCH, PLANT, WAREHOUSE]:

        inherited_scope =
            COMPANY
            + ancestors of O
            + O

        common_context_policies[O] =
            resolve ORGANIZATION participant policies
            over inherited_scope

        KQ[user, O] =
            resolve actor-level policies (USER/GROUP)
            + common_context_policies[O]
            over inherited_scope
            by QT1

        FOR EACH eligible effective Employee Assignment A:
            KQ[A, O] =
                resolve DEPARTMENT/POSITION policies
                + common_context_policies[O]
                over inherited_scope
                by QT1

        absolute_denied[O] =
            all applicable ABSOLUTE_DENY matched
            at COMPANY/ancestor/O

        inject absolute_denied[O]
            into every KQ lane participating in O

        KQ[O] =
            INTERSECTION(
                KQ[user, O],
                all KQ[A, O]
            )

    KQ[COMPANY] =
        INTERSECTION(
            all KQ[O] in that Company
        )
```

**Semantic rules**

- Ordinary `DENY` là restriction của lane; giữa nhiều lane, User hưởng quyền cao nhất. Permission chỉ còn bị ordinary-denied nếu nằm trong giao của mọi KQ lane liên quan.
- `ALLOW` hoặc `NO_MATCH` trong một lane làm permission không thuộc KQ denied của lane.
- `ORGANIZATION` participant policies applicable tại Company/ancestor/current Organization được xem là contextual rules dùng chung và được đưa vào mọi lane trước QT1.
- Applicable `ABSOLUTE_DENY` là mandatory inherited restriction; phải xuất hiện trong mọi KQ lane chịu scope trước intersection.
- Company A và Company B được evaluate độc lập.
- Base Permission vẫn là prerequisite toàn cục; intersection không được tạo permission chưa có trong Effective Profile.

**Acceptance criteria**

- No Base Permission => DENY dù mọi lane đều không cấm.
- Trong một lane: Base + NO_MATCH => ALLOW.
- Trong một lane: Base + DENY => permission vào KQ lane.
- Trong một lane: Base + ALLOW + DENY => permission không vào KQ lane.
- Trong một lane: Base + ABSOLUTE_DENY + effect khác => permission vào mandatory denied set.
- Hai role cùng scope: role A `ALLOW`, role B `DENY` => permission không thuộc KQ Organization nếu không có `ABSOLUTE_DENY`.
- Hai role cùng scope đều `DENY` => permission thuộc KQ Organization.
- Applicable inherited `ABSOLUTE_DENY` => permission thuộc KQ Organization và không bị role khác mở lại.
- Permission bị cấm ở Organization 1 nhưng được phép ở Organization 2 => không thuộc giao KQ[COMPANY].

## FRS-P1-PBAC-005 - Explainable decision

**Priority:** MUST

Authorization service phải trả decision và reason đủ để debug.

**Output tối thiểu khi phù hợp**

- User.
- Organization Context.
- Resource.
- Action.
- State.
- Decision.
- Matched decisive Policy khi có.
- Reason.
- Correlation/Request id khi có.

**Acceptance criteria**

- Runtime caller phân biệt được DENY do thiếu Base Permission và DENY do Policy.
- NO_MATCH + Base Permission phải giải thích được là ALLOW theo default rule.

## FRS-P1-PBAC-006 - Authorization Audit

**Priority:** MUST

Persisted authorization decision log tối thiểu cho:

- Approve.
- Delete.
- State change.

View/List không bắt buộc persisted log.

**Acceptance criteria**

- Log chứa User, Resource, Action, Decision và timestamp.
- Khi có context/state/policy/request id, log giữ các field schema hỗ trợ.
- Log là evidence; UI không cho business user tùy ý sửa.

## FRS-P1-PBAC-007 - System Admin behavior

**Priority:** MUST

System Admin/root identity có thể được áp dụng cơ chế quản trị đặc biệt theo application/security rule được phê duyệt. Database Design không persistence special bypass row/column và User Requirements không mặc định rằng System Admin được bypass mọi Policy hoặc legal Workflow Transition.

**FRS boundary**

- FRS không tự định nghĩa claim/group/code cụ thể để nhận diện System Admin.
- Implementation phải dùng cơ chế và phạm vi bypass được security authority phê duyệt.
- System Admin có thể được miễn Employee mapping khi rule được phê duyệt cho phép; không tự suy diễn miễn các validation nghiệp vụ khác.
- Không mô phỏng root bypass bằng Policy ALLOW hoặc database flag tự phát minh.

---

# 9. Workflow, Lifecycle và Approval

## FRS-P1-WF-001 - Workflow State catalog

**Priority:** MUST

Hệ thống cho phép quản lý Workflow State dùng chung.

Baseline state có thể gồm:

- DRAFT
- PENDING_APPROVAL
- APPROVED
- REJECTED
- CANCELLED

State khác chỉ cấu hình khi Resource thực sự cần.

**Acceptance criteria**

- State code unique.
- Inactive state không được chọn cho cấu hình mới.

## FRS-P1-WF-002 - Lifecycle management

**Priority:** MUST

Lifecycle gồm:

- Lifecycle master.
- Các State thành viên.
- Sequence display/process.
- Initial flag.
- Final flag.
- Transition catalog.

**Rules**

- Một lifecycle chỉ có một initial state active tại một thời điểm cấu hình.
- Có thể có nhiều final state.

**Acceptance criteria**

- Không gắn Transition tới State không thuộc Lifecycle.

## FRS-P1-WF-003 - Deterministic transition

**Priority:** MUST

Mỗi combination:

```text
Lifecycle + From State + Action
```

chỉ resolve một To State.

**Acceptance criteria**

- Cấu hình hai destination khác nhau cho cùng lifecycle/from/action bị reject.
- Transition inactive không dùng cho state change mới.

## FRS-P1-WF-004 - State change execution

**Priority:** MUST

Khi caller yêu cầu state change:

1. Xác định Resource và current State.
2. Xác định Lifecycle.
3. Validate legal Transition cho Action.
4. Evaluate Authorization cho Resource/Action/current State/context.
5. Chỉ khi cả Transition và Authorization pass mới thực hiện state change.
6. Ghi audit theo requirement liên quan.

**Acceptance criteria**

- Authorization ALLOW nhưng transition không tồn tại => reject.
- Transition tồn tại nhưng Authorization DENY => reject.
- State change thành công không được đi tới destination khác với transition catalog.

## FRS-P1-WF-005 - Single-step approval baseline

**Priority:** MUST

Approval flow chuẩn:

```text
DRAFT -> PENDING_APPROVAL -> APPROVED / REJECTED
```

**Rules**

- Một transaction có tối đa một approval stage PENDING_APPROVAL tại một thời điểm.
- Không có multi-level approval matrix.
- Không có amount-threshold routing.
- Không bắt buộc assigned approver persistence.

**Acceptance criteria**

- Approve/Reject chỉ thành công nếu action authorized và transition hợp lệ.
- Maker/checker hoặc self-approval restriction không được tự suy diễn nếu chưa có requirement/change design.

---

# 10. Product Master V2

## FRS-P1-PRD-001 - Product identity và Product Type

**Priority:** MUST

P1 chỉ có một Product entity. Không có Variant entity/table.

Product Type persisted:

- STANDARD
- COMBO

Derived Role:

- SIMPLE = STANDARD root không có child.
- PARENT = STANDARD root có child.
- CHILD = STANDARD có parent.
- COMBO = Product Type COMBO.

Operational role:

| Role | Selectable input | Leaf transactionable | Composition |
|---|---:|---:|---:|
| SIMPLE | Yes | Yes | No |
| CHILD | Yes | Yes | No |
| COMBO | Yes | No | Yes |
| PARENT | No | No | No |

**Acceptance criteria**

- Không persistence role SIMPLE/PARENT/CHILD thành column riêng.
- Module không được tự định nghĩa role khác ProductRoleService canonical.

## FRS-P1-PRD-002 - Product create/edit

**Priority:** MUST

**Thông tin Product**

- Code.
- SKU khi selectable.
- Barcode khi áp dụng.
- Parent Product khi CHILD.
- Product Name hoặc Product Child Name.
- Product Group.
- Product Type.
- Base UOM khi SIMPLE/CHILD/COMBO.
- Default Base-UOM Price khi SIMPLE/CHILD/COMBO.
- Net Weight/Packaging fields.
- Description.
- Manual Standard Cost khi áp dụng.
- Serial Management flag.
- Status.

**Validation**

- Code unique.
- SKU unique khi khai báo; SIMPLE/CHILD/COMBO phải có SKU.
- Barcode unique khi khai báo.
- Price >= 0 khi có.
- Manual Standard Cost >= 0 khi có.

**Acceptance criteria**

- PARENT có price NULL và không được chọn làm transaction input.
- Product inactive không selectable cho operation mới.

## FRS-P1-PRD-003 - Product naming

**Priority:** MUST

Naming rule:

- SIMPLE/PARENT/COMBO: dùng Product Name.
- CHILD: lưu Product Child Name; Product Name của child không lặp Parent name.

Full name:

```text
SIMPLE/PARENT/COMBO -> product_name
CHILD -> parent.product_name + separator + product_child_name
```

Exact display separator chưa được source baseline khóa. FRS yêu cầu formatter tạo tên đầy đủ từ hai dữ liệu canonical và không persistence `full_name`; UI style/separator được quyết định ở UI specification mà không làm thay đổi dữ liệu.

**Acceptance criteria**

- Rename Parent làm display name của Child phản ánh tên mới.
- Không update hàng loạt full_name persisted vì không có field đó.

## FRS-P1-PRD-004 - Parent/Child hierarchy

**Priority:** MUST

**Rules**

- Parent candidate phải root STANDARD.
- CHILD phải STANDARD và có parent root STANDARD.
- CHILD không được có child.
- COMBO không được Parent hoặc Child.
- Depth tối đa 1.
- Không self-parent/cycle.

**Acceptance criteria**

- Parent -> Child hợp lệ.
- Parent -> Child -> Child bị reject.
- COMBO làm parent bị reject.
- CHILD chuyển thành COMBO bị reject.

## FRS-P1-PRD-005 - SIMPLE to PARENT conversion

**Priority:** MUST

STANDARD root SIMPLE chỉ trở thành PARENT khi tạo child đầu tiên và đáp ứng conversion eligibility.

**Reject khi còn tối thiểu**

- Transaction history.
- Product Price List Item reference.
- Product UOM Conversion.
- Product đang được dùng làm component trong COMBO.

System không được tự xóa dependency để ép conversion.

**Acceptance criteria**

- Conversion là atomic.
- Nếu bất kỳ dependency validation fail, child không được tạo partial.
- Sau conversion, Product thỏa invariant PARENT.

## FRS-P1-PRD-006 - Semantic-field lock sau transaction history

**Priority:** MUST

Sau khi Product có transaction history, không được sửa trực tiếp theo cách làm sai lịch sử các field:

- Base UOM.
- Parent relation.
- Product Type.
- Serial Management policy.

P1 chưa có transaction tables nhưng service/domain contract phải giữ extension point cho các module P2/P3 cung cấp history check.

**Acceptance criteria**

- Khi history provider báo có transaction, mutation semantic bị reject.

---

# 11. Product Group và UOM

## FRS-P1-UOM-001 - Product Group

**Priority:** MUST

Quản lý Product Group với Code, Name, Description, Status.

**Acceptance criteria**

- Code unique.
- Inactive group không dùng cho Product mới nếu selector yêu cầu active.

## FRS-P1-UOM-002 - Product UOM catalog

**Priority:** MUST

Quản lý UOM dùng chung với Code, Name, Description, Status.

**Acceptance criteria**

- UOM Code unique.
- Inactive UOM không dùng cho cấu hình mới.

## FRS-P1-UOM-003 - Base UOM

**Priority:** MUST

SIMPLE, CHILD và COMBO phải có Base UOM.

**Rules**

- Base UOM implicit conversion factor = 1.
- Không tạo conversion row cho Base UOM.
- PARENT có thể không có Base UOM.

**Acceptance criteria**

- Product selectable thiếu Base UOM bị reject.

## FRS-P1-UOM-004 - Alternate UOM cho SIMPLE/CHILD

**Priority:** MUST

Chỉ SIMPLE/CHILD được cấu hình Alternate UOM.

**Thông tin conversion**

- Alternate UOM.
- Conversion factor về Base UOM.
- Default Alternate-UOM selling price khi áp dụng.
- Packaging gross weight/dimensions.
- Usage Type: PURCHASE, SALES, BOTH.
- Status.

**Validation**

- Conversion factor > 0.
- Alternate UOM != Base UOM.
- Unique Product + UOM.
- Direct-to-base only.
- SALES/BOTH => price required, price >= 0.
- PURCHASE => price optional; nếu có >= 0.

**Acceptance criteria**

- Không hỗ trợ PALLET -> CARTON -> BOX -> PCS chain.
- Alternate price không tự tính từ Base price × factor.

## FRS-P1-UOM-005 - COMBO không có UOM Conversion

**Priority:** MUST

COMBO chỉ sử dụng Base UOM và không được có Product UOM Conversion.

**Acceptance criteria**

- Create conversion cho COMBO bị reject.
- Price List item của COMBO chỉ dùng Base UOM.

---

# 12. Serial Management Policy

## FRS-P1-SER-001 - Serial flag

**Priority:** MUST

Serial Management chỉ có thể bật cho SIMPLE/CHILD.

**Rules**

- PARENT: serial false.
- COMBO: serial false.
- SIMPLE/CHILD: có thể true/false.

**Acceptance criteria**

- COMBO bật serial bị reject.
- PARENT bật serial bị reject.
- Component leaf của COMBO vẫn có thể serial-managed.

## FRS-P1-SER-002 - Downstream serial boundary

**Priority:** MUST

P1 chưa persistence serial instance. Khi P2 Inventory triển khai, serial phải theo SIMPLE/CHILD leaf sau COMBO flatten.

**Acceptance criteria**

- Không tạo serial cho COMBO root hoặc nested COMBO.

---

# 13. COMBO Composition Service

## FRS-P1-CMB-001 - Quản lý COMBO components

**Priority:** MUST

COMBO có normalized component list.

Component được phép:

- SIMPLE.
- CHILD.
- COMBO.

Không được:

- PARENT.
- Chính COMBO owner.

Quantity component luôn theo Base UOM của component và phải > 0.

**Acceptance criteria**

- Cùng component không lặp nhiều row trong một owner COMBO.
- PARENT component bị reject.
- Self component bị reject.
- Nested COMBO được chấp nhận nếu không cycle.

## FRS-P1-CMB-002 - COMBO cycle detection

**Priority:** MUST

Mỗi create/update/import composition phải validate graph acyclic.

**Ví dụ reject**

```text
A -> B -> A
A -> B -> C -> A
```

**Acceptance criteria**

- Cycle trực tiếp và gián tiếp đều bị reject.
- Validation chạy trước commit mutation.

## FRS-P1-CMB-003 - Flatten COMBO

**Priority:** MUST

COMBO root được chọn với quantity `Q_root` phải flatten đệ quy đến SIMPLE/CHILD.

Leaf quantity trên mỗi path:

```text
Q_leaf_path = Q_root * product(quantity trên tất cả edge của path)
```

Nếu cùng leaf xuất hiện nhiều path trong cùng root COMBO, quantity được aggregate trước allocation.

Canonical aggregation key ở Product Domain tối thiểu:

```text
product_id + base_uom_id
```

**Acceptance criteria**

- Output flatten chỉ chứa SIMPLE/CHILD.
- Nested COMBO không xuất hiện trong final leaf set.
- Quantity chính xác theo tích path.

---

# 14. Core Pricing

## FRS-P1-PRC-001 - Price List management

**Priority:** MUST

Price List thuộc Organization type GROUP hoặc COMPANY.

**Thông tin**

- Code unique toàn hệ thống.
- Name.
- Organization scope.
- Effective From.
- Effective To.
- Priority.
- Status.
- Description.

**Effective interval**

```text
effective_from <= evaluation_time < effective_to
```

`effective_to = NULL` => open-ended.

**Acceptance criteria**

- Price List không được scope BRANCH/PLANT/WAREHOUSE.
- Effective To phải > Effective From khi có.
- Overlap được phép.

## FRS-P1-PRC-002 - Product Price List Item

**Priority:** MUST

Price List Item định giá cụ thể theo Product + UOM.

**Rules**

- SIMPLE/CHILD: UOM có thể Base UOM hoặc active sales-capable Alternate UOM.
- COMBO: UOM bắt buộc = Base UOM.
- PARENT: không được có Price List Item.
- Price >= 0.
- Unique trong cùng Price List theo Product + UOM.

**Acceptance criteria**

- COMBO item dùng Alternate UOM bị reject.
- Alternate UOM PURCHASE-only không được dùng cho selling Price List.

## FRS-P1-PRC-003 - Resolve selling price

**Priority:** MUST

Input canonical:

```text
product_id
uom_id
company_organization_id
evaluation_time
```

Preconditions:

- Company id phải là COMPANY.
- Product active/selectable.
- UOM hợp lệ cho Product.
- COMBO chỉ cho Base UOM.

Resolution:

```text
1. Matching Company Price List + Product + UOM
2. Nếu không có, matching Group Price List + Product + UOM
3. Nếu không có, default price đúng UOM
```

Within same scope order:

```text
priority DESC
-> effective_from DESC
-> price_list_id ASC
```

Default:

```text
Base UOM SIMPLE/CHILD/COMBO -> products.price
Alternate UOM SIMPLE/CHILD  -> product_uom_conversions.price
```

**Acceptance criteria**

- Company có Price List nhưng thiếu item vẫn fallback Group.
- Inactive/out-of-period Price List không phải candidate.
- Kết quả deterministic khi overlap.

## FRS-P1-PRC-004 - Price resolution output

**Priority:** MUST

Output tối thiểu:

- Final Unit Price.
- UOM.
- Price Source.
- Price List reference khi source là Price List.

Price Source:

- PRICE_LIST_COMPANY
- PRICE_LIST_GROUP
- PRODUCT_DEFAULT

P1 không persistence riêng Price Resolution Result.

---

# 15. COMBO Pricing, Allocation và Weighted Aggregation

## FRS-P1-CMBPRC-001 - Resolve giá root COMBO đúng một lần

**Priority:** MUST

Khi COMBO được chọn trực tiếp:

1. Resolve giá của root COMBO theo Core Pricing.
2. Không resolve lại giá của nested COMBO trong tree đó.
3. Flatten composition về SIMPLE/CHILD.
4. Phân bổ total selling amount của root xuống leaf.

**Acceptance criteria**

- Nested COMBO default price/Price List không ảnh hưởng allocation khi nested dưới root khác.
- Nếu nested COMBO được chọn trực tiếp trong một operation khác, nó trở thành root và được resolve giá bình thường.

## FRS-P1-CMBPRC-002 - Allocation weight theo default price của leaf

**Priority:** MUST

Sau flatten và aggregate quantity trong một root COMBO:

```text
leaf_weight = leaf_quantity * leaf.products.price
total_weight = SUM(leaf_weight)
```

Rules:

- Leaf chỉ SIMPLE/CHILD.
- Dùng default Base-UOM `products.price` của leaf.
- Không dùng Company/Group Price List của leaf để làm weight.
- Không dùng giá của nested COMBO.

**Validation**

- `total_weight <= 0` => reject allocation.
- Không tự chia đều khi weight bằng 0.

**Acceptance criteria**

- Leaf có default price 0 có weight 0; nếu toàn bộ leaf weight 0 thì error.

## FRS-P1-CMBPRC-003 - Allocate COMBO amount

**Priority:** MUST

Với:

```text
combo_total_amount = combo_unit_price * Q_root
raw_leaf_amount = combo_total_amount * leaf_weight / total_weight
```

Calculation trung gian dùng precision cao. Monetary result cuối round về 2 decimal.

Residual rounding được xử lý deterministic:

- Sort aggregated leaves theo `product_id ASC`.
- Residual cuối cùng cộng/trừ vào leaf cuối cùng.

Invariant bắt buộc:

```text
SUM(final_leaf_allocated_amount) = combo_total_amount
```

**Acceptance criteria**

- Không mất/nhân thêm tiền do rounding.
- Cùng input trả cùng kết quả allocation.

## FRS-P1-CMBPRC-004 - Aggregate cùng leaf từ nhiều COMBO

**Priority:** MUST

Trong cùng một transaction calculation context, nếu cùng leaf Product nhận allocation từ nhiều root COMBO hoặc nhiều occurrence do composition:

```text
final_quantity = SUM(occurrence_quantity)
final_amount = SUM(occurrence_allocated_amount)
final_unit_price = final_amount / final_quantity
```

`final_unit_price` tương đương weighted average theo quantity.

**Rules**

- Không lấy arithmetic mean đơn giản của occurrence unit price.
- Không ưu tiên unit price đã rounded nếu làm sai total amount.
- Amount là invariant ưu tiên.

**Acceptance criteria**

Ví dụ:

```text
X từ Combo A: qty 2, amount 180,000
X từ Combo B: qty 3, amount 240,000

final qty = 5
final amount = 420,000
final unit price = 84,000
```

## FRS-P1-CMBPRC-005 - Downstream operational boundary

**Priority:** MUST

Sau flatten/allocation/aggregation, downstream modules phải vận hành trên SIMPLE/CHILD leaf cho:

- Inventory.
- Reservation.
- Serial.
- Costing.
- Posting.
- Return.
- Quantity trace.

COMBO không tạo stock balance, serial instance hoặc cost layer riêng.

P1 không tạo transaction persistence cho source trace. P2 transaction design phải giữ đủ trace để biết leaf phát sinh từ COMBO khi business/audit yêu cầu.

---

# 16. Import Framework

User Requirements có `UR-COM-005` ở mức SHOULD, nhưng `UR-CORE-004` yêu cầu khả năng import/cập nhật danh mục địa chỉ và Database Design v1.4 khóa Import Framework cùng persisted Job/Row Result trong P1. Để FRS P1 có thể kiểm thử nhất quán với technical baseline, các invariant import dưới đây là bắt buộc khi triển khai P1 master import; thay đổi delivery priority phải được phản ánh lại trong User Requirements/phase plan qua change control.

## FRS-P1-IMP-001 - Import Job

**Priority:** MUST

P1 cung cấp import CSV/XLSX cho tối thiểu:

- PRODUCT
- EMPLOYEE
- PROVINCE
- WARD

Mỗi Import Job giữ:

- Entity Type.
- Source Format.
- Original File Name khi có.
- File Hash khi áp dụng.
- Status.
- Total/Success/Error counts.
- Actor/timestamps.

Job status baseline:

- PENDING
- PROCESSING
- COMPLETED
- COMPLETED_WITH_ERRORS
- FAILED

**Acceptance criteria**

- Job counts reconcile với row result.
- User xem được trạng thái và summary import trong scope được phép.

## FRS-P1-IMP-002 - Row-level result

**Priority:** MUST

Mỗi source row phải có result:

- Row Number gốc.
- SUCCESS/ERROR/SKIPPED.
- Entity Key khi có.
- Logical target reference khi success.
- Error information khi fail.

**Acceptance criteria**

- ERROR row không được tính success.
- Original row number được giữ dù importer xử lý dependency theo thứ tự khác.

## FRS-P1-IMP-003 - Import validation parity

**Priority:** MUST

Import phải dùng cùng business validation với UI/API mutation.

**Acceptance criteria**

- Unique rule, parent matrix, Product invariant, UOM rule, Policy rule không được bypass qua import.
- Một row mutation nhiều bảng fail phải rollback row đó.

## FRS-P1-IMP-004 - Product dependency resolution

**Priority:** MUST

Product import không phụ thuộc thứ tự row nguồn.

Importer phải resolve dependency bằng multi-pass/dependency graph cho:

- Parent/Child.
- Product Group/UOM references.
- COMBO owner/component.
- Nested COMBO dependency.

**Rules**

- Child có thể xuất hiện trước Parent trong file.
- COMBO có thể xuất hiện trước component.
- Nested COMBO phải cycle-detect.
- Source row order vẫn giữ nguyên trong row result.

**Acceptance criteria**

- File có dependency hợp lệ nhưng thứ tự ngược vẫn import được.
- Graph cycle làm các row liên quan fail với error có thể giải thích; không commit composition cycle.

## FRS-P1-IMP-005 - Import authorization

**Priority:** MUST

Import là action protected. User phải có permission phù hợp với entity/import operation theo catalog được triển khai.

**Acceptance criteria**

- User không có quyền không thể chạy import.
- Import không mở đường bypass authorization cho mutation master.

---

# 17. Search, Filter và Export

## FRS-P1-QRY-001 - Common list behavior

**Priority:** MUST

Các màn hình danh sách chính P1 hỗ trợ:

- Search.
- Filter.
- Sort.
- Pagination.

Domain-specific examples:

- Organization: code/name/type/status.
- Contact: name/email/phone/mobile/status.
- Employee: code/name/status/manager.
- Product: code/SKU/barcode/name/type/derived role/status/group.
- Price List: code/name/scope/status/effective time.
- Policy: organization/resource/state/participant.
- Import Job: entity/status/date/actor.

**Acceptance criteria**

- Derived Product Role có thể dùng cho filter/query application dù không persistence thành role column.

## FRS-P1-QRY-002 - Export

**Priority:** MUST

Export chỉ được thực hiện khi User có EXPORT action trên Resource tương ứng.

**Acceptance criteria**

- VIEW-only User không export được.
- Export áp dụng cùng scope/filter authorization như list.
- Sensitive resource vẫn cần permission riêng.

---

# 18. Functional Service Contracts ở mức nghiệp vụ

Phần này định nghĩa capability service logic, không khóa HTTP route, DTO wire-format hoặc framework class name.

## FRS-P1-SVC-001 - OrganizationService

Phải hỗ trợ tối thiểu:

- Create/update/inactivate Organization.
- Validate Parent Matrix.
- Detect hierarchy cycle.
- Resolve Company ancestor.
- Resolve ancestor chain phục vụ Policy inheritance.

## FRS-P1-SVC-002 - EmployeeAssignmentService

Phải hỗ trợ:

- Create/update/end Assignment.
- Validate date range.
- Validate Primary overlap.
- Resolve Primary Assignment tại Evaluation Time cho default actor context.
- Resolve effective Assignments theo Company + Evaluation Organization Context phục vụ PBAC.
- Resolve actor default Organization/Company context và Assignment scope path.

## FRS-P1-SVC-003 - AuthorizationService

Phải hỗ trợ:

- Resolve Base Permission.
- Partition evaluation theo Company.
- Resolve ancestor chain và Policy inheritance trước merge.
- Build actor-level lane (`USER`/`GROUP`).
- Build assignment lanes (`DEPARTMENT`/`POSITION`) từ effective eligible Assignments.
- Apply QT1 trong từng lane.
- Propagate applicable `ABSOLUTE_DENY` vào mọi lane con chịu scope.
- Merge KQ Organization bằng intersection.
- Merge KQ Company bằng intersection các KQ Organization.
- Trả Decision + Reason kèm lane/inheritance evidence đủ debug.
- Ghi Authorization Audit khi action thuộc persisted-audit scope.

## FRS-P1-SVC-004 - LifecycleService

Phải hỗ trợ:

- Resolve current Lifecycle/State.
- Resolve một legal destination theo From State + Action.
- Validate State membership.
- Phối hợp Authorization trước state change.

## FRS-P1-SVC-005 - ProductRoleService

Phải hỗ trợ tối thiểu:

- getRole().
- isParent().
- isChild().
- isSimple().
- isCombo().
- isSelectable().
- isLeafTransactionable().
- requiresComboExpansion().
- getFullName().

Tất cả module phải dùng cùng rule role canonical.

## FRS-P1-SVC-006 - ProductUomService

Phải hỗ trợ:

- Validate Base UOM.
- Validate Alternate UOM direct-to-base.
- Convert source quantity về Base Quantity cho SIMPLE/CHILD.
- Reject UOM Conversion cho COMBO.

## FRS-P1-SVC-007 - PricingService

Phải hỗ trợ:

- Resolve Product + UOM + Company + Effective Time.
- Company -> Group -> Default fallback.
- Enforce effective interval `[effective_from, effective_to)` và deterministic ordering.
- Validate COMBO chỉ pricing tại Base UOM.
- Trả price source và Price List reference.

## FRS-P1-SVC-008 - ComboExpansionService

Phải hỗ trợ:

- Validate acyclic composition.
- Flatten nested COMBO.
- Aggregate leaf quantity theo canonical key tối thiểu `product_id + base_uom_id`.
- Không resolve nested COMBO price khi xử lý root.

## FRS-P1-SVC-009 - ComboAllocationService

Phải hỗ trợ:

- Tính leaf weight theo quantity × default Base-UOM price.
- Reject total weight <= 0.
- Allocate root COMBO total amount.
- Deterministic residual rounding.
- Aggregate leaf occurrence từ nhiều COMBO bằng SUM(quantity)/SUM(amount).
- Derive weighted-average final unit price.

## FRS-P1-SVC-010 - ImportService

Phải hỗ trợ:

- Create Job.
- Parse file.
- Validate row.
- Build dependency graph khi Product import.
- Multi-pass processing.
- Row-level atomic mutation.
- Row result/report.
- Final Job reconciliation.

ImportService là capability P1 theo Database Design; nếu phase planning thay đổi delivery priority, các validation/persistence contract đã khóa không được bị bypass bởi đường import thay thế.

---

# 19. Error và Validation Semantics

Exact HTTP/error-code catalog chưa được source authority khóa. FRS chỉ định semantic error class tối thiểu để UI/API có thể mapping nhất quán sau này.

## 19.1 Validation categories

Hệ thống phải phân biệt được tối thiểu:

- Required field missing.
- Duplicate unique key.
- Invalid reference.
- Inactive reference not selectable.
- Invalid Organization hierarchy.
- Hierarchy cycle.
- Department cycle.
- Employee manager cycle.
- Primary Assignment overlap.
- Unsupported participant type.
- Policy resource/action mismatch.
- No Base Permission.
- Authorization denied by Policy.
- Invalid lifecycle transition.
- Product role invariant violation.
- Product semantic field locked by history.
- Invalid UOM conversion.
- COMBO cycle.
- Invalid COMBO component role.
- Pricing candidate not found and default unavailable.
- COMBO allocation total weight zero.
- Import row validation failure.

## 19.2 Error output expectation

Error response ở functional level nên cho caller biết:

- Operation bị từ chối.
- Field/entity/context liên quan khi phù hợp.
- Human-readable reason.
- Correlation id khi infrastructure hỗ trợ.

FRS không khóa HTTP status, JSON envelope hoặc exact string/error code.

---

# 20. Functional Flows P1

## 20.1 Tạo Company mới

```text
User có organization.create
  -> nhập Company data
  -> chọn GROUP parent
  -> validate code/type/parent/date/address
  -> authorize
  -> create Organization
  -> ghi audit actor/timestamp
  -> Company sẵn sàng làm context cho Pricing/Assignment
```

## 20.2 Tạo Employee và Primary Assignment

```text
Create Employee
  -> optional link Shield User
  -> validate unique employee/user mapping
  -> create Contacts nếu có
  -> create Primary Assignment
  -> validate active refs + date + no overlap
  -> resolve default Organization Context
```

## 20.3 Evaluate authorization

```text
User + Resource + Action + Evaluation Organization + State
  -> identity active check
  -> resolve Group memberships/direct permission
  -> build Base Permission
  -> if none: DENY

  -> resolve Company boundary
  -> resolve COMPANY + ancestor path + current Organization
  -> resolve applicable Policy inheritance

  -> resolve common ORGANIZATION participant policies
       over COMPANY + ancestor path + current Organization

  -> build KQ[user, Organization]
       using USER/GROUP + common ORGANIZATION rules + QT1

  -> resolve eligible effective Employee Assignments
       in same Company / Organization scope

  -> build KQ[assignment, Organization]
       using DEPARTMENT/POSITION + common ORGANIZATION rules + QT1

  -> propagate applicable ABSOLUTE_DENY
       into every child lane before merge

  -> KQ[Organization]
       = intersection(user lane, all assignment lanes)

  -> KQ[Company]
       = intersection(all Organization KQ)

  -> Resource+Action in effective KQ => DENY
     otherwise => ALLOW

  -> decision/reason
  -> persist audit when required
```

## 20.4 Tạo SIMPLE Product

```text
Create STANDARD root
  -> no parent
  -> no children
  -> required SKU/Base UOM/Price
  -> derived role SIMPLE
  -> optional Alternate UOM
  -> optional Serial flag
```

## 20.5 Tạo CHILD Product

```text
Select root STANDARD parent
  -> validate parent eligible
  -> create STANDARD child
  -> required Product Child Name/SKU/Base UOM/Price
  -> if first child, parent SIMPLE -> PARENT atomically
  -> parent no longer selectable transaction input
```

## 20.6 Tạo nested COMBO

```text
Create COMBO A
  -> Base UOM + Price required
  -> Serial=false
  -> no UOM conversions
  -> add SIMPLE/CHILD/COMBO components
  -> validate no PARENT/self/duplicate
  -> traverse graph
  -> reject cycle
  -> save composition atomically
```

## 20.7 Resolve và allocate COMBO

```text
Input: COMBO A, qty, Company, time
  -> resolve A price at Base UOM
  -> flatten A recursively
  -> nested COMBO price ignored
  -> aggregate leaf qty within A
  -> weight each leaf = qty * leaf default Base price
  -> total weight must > 0
  -> allocate A total amount
  -> deterministic rounding
  -> output SIMPLE/CHILD leaf amounts
```

## 20.8 Nhiều COMBO trong cùng transaction calculation

```text
Resolve/allocate each selected root COMBO independently
  -> collect leaf occurrences
  -> group same leaf by product_id + base_uom_id
  -> final qty = sum qty
  -> final amount = sum allocated amount
  -> final unit price = final amount / final qty
```

## 20.9 Price Resolution

```text
Input Product + UOM + Company + Time
  -> validate Product/UOM
  -> Company Price Lists with matching item
  -> order priority/effective_from/id
  -> if none: Group matching item
  -> if none: default price đúng UOM
  -> return unit price + source + list reference
```

## 20.10 Product Import dependency graph

```text
Parse all rows
  -> normalize entity keys
  -> resolve Product Group/UOM refs
  -> build Parent/Child dependencies
  -> build COMBO dependencies
  -> detect cycles
  -> process valid dependency order/multi-pass
  -> preserve original row_number result
  -> rollback failed row atomically
  -> reconcile job counts
```

---

# 21. Security và Data Protection Functional Rules

## FRS-P1-SEC-001 - Sensitive Resource authorization

**Priority:** MUST

Sensitive data protection baseline dùng Resource/Action authorization, không field-level masking.

Các domain nhạy cảm tương lai gồm Payroll, Compensation, Cost, Bank, Finance và Employee identity information.

**Acceptance criteria**

- VIEW/EDIT/EXPORT là permission độc lập.
- FRS P1 không tự tạo field masking persistence.

## FRS-P1-SEC-002 - No authorization bypass through import/export

**Priority:** MUST

- Import phải authorize trước mutation.
- Export phải authorize độc lập.
- Background/system process phải chạy dưới identity/system policy được phê duyệt, không giả làm business User.

## FRS-P1-SEC-003 - Authorization cache invalidation

**Priority:** MUST nếu có cache

Cache decision phải invalidated khi thay đổi input ảnh hưởng decision, tối thiểu:

- Shield group membership.
- Direct Shield permission.
- Authorization Group.
- Group Permission.
- Resource Action.
- Policy/Policy Action.
- Employee Assignment.
- Organization hierarchy/status.
- Position/Department khi tham gia Policy participant.

**Acceptance criteria**

- Cache cũ không được làm bypass ABSOLUTE_DENY.

---

# 22. Audit, History và Retention

## FRS-P1-AUD-001 - Master/config change audit

**Priority:** MUST

Create/update mutable master/config phải giữ actor/timestamp theo Database Design.

FRS không yêu cầu full field-by-field version history nếu schema chưa định nghĩa.

## FRS-P1-AUD-002 - Inactive record history

**Priority:** MUST

- Organization inactive/closed giữ history.
- Employee inactive giữ Assignment/Contact reference.
- Contact inactive/closed giữ history.
- Product inactive giữ identity/reference.
- Price List hết hiệu lực giữ cấu hình lịch sử.

## FRS-P1-AUD-003 - Retention policy open item

Retention period và quyền xem `authorization_logs`/`import_jobs` chưa được source baseline xác định đầy đủ.

FRS yêu cầu:

- Không tự hard-delete trong business flow chuẩn.
- Production retention phải được Security/Operations phê duyệt trước khi triển khai purge.

---

# 23. Traceability Matrix - User Requirement sang Functional Requirement

Bảng dưới đây bao phủ toàn bộ User Requirement có Target Phase P1 trong `00_User_Requirements_v1.0.7-draft.md`.

| User Requirement | FRS Coverage |
|---|---|
| UR-CORE-001 Một Group, nhiều Company | FRS-P1-ORG-001..005 |
| UR-CORE-002 Transaction thuộc Company | FRS-P1-GEN-001, FRS-P1-ORG-003, FRS-P1-ORG-005 |
| UR-CORE-003 Master data dùng chung trong Group | FRS-P1-PRD-001..006, FRS-P1-UOM-001..005 |
| UR-CORE-004 Administrative Location | FRS-P1-LOC-001..002, FRS-P1-IMP-001..005 |
| UR-CORE-005 Shared Contact | FRS-P1-CON-001..002 |
| UR-ORG-001 Company | FRS-P1-ORG-001..003, FRS-P1-CON-001 |
| UR-ORG-002 Branch/Plant/Warehouse | FRS-P1-ORG-001..004 |
| UR-ORG-003 Xem/tổng hợp theo Company | FRS-P1-ORG-005, FRS-P1-QRY-001..002 |
| UR-EMP-001 Employee | FRS-P1-EMP-003, FRS-P1-CON-001..002 |
| UR-EMP-002 Department/Position | FRS-P1-EMP-001..002 |
| UR-EMP-003 Assignment | FRS-P1-EMP-004..005 |
| UR-IAM-001 Identity | FRS-P1-IAM-001, FRS-P1-PBAC-007 |
| UR-IAM-002 Resource/Action | FRS-P1-IAM-002 |
| UR-IAM-003 Effective Profile | FRS-P1-IAM-004 |
| UR-IAM-004 Authorization Group | FRS-P1-IAM-003 |
| UR-IAM-005 Policy | FRS-P1-PBAC-001..003 |
| UR-IAM-006 Actor Context | FRS-P1-EMP-005 |
| UR-IAM-007 Resource Authorization | FRS-P1-PBAC-001..004 |
| UR-IAM-008 Workflow State Authorization | FRS-P1-PBAC-001, FRS-P1-WF-004 |
| UR-IAM-009 Effect Precedence | FRS-P1-PBAC-004 |
| UR-IAM-010 NO_MATCH | FRS-P1-PBAC-004 |
| UR-IAM-011 Policy Inheritance | FRS-P1-PBAC-003 |
| UR-IAM-012 Explainable Authorization | FRS-P1-PBAC-005..006 |
| UR-IAM-013 Sensitive Data | FRS-P1-SEC-001..002 |
| UR-PRD-001 Product Master V2 | FRS-P1-PRD-001..006 |
| UR-PRD-002 Product Naming | FRS-P1-PRD-003 |
| UR-PRD-003 UOM & Packaging | FRS-P1-UOM-002..005 |
| UR-PRD-004 Serial Policy | FRS-P1-SER-001..002 |
| UR-PRD-005 Combo Composition | FRS-P1-CMB-001..003 |
| UR-PRD-006 Combo Expansion & Selling Price Allocation | FRS-P1-CMB-003, FRS-P1-CMBPRC-001..005 |
| UR-PRC-001 Core Pricing | FRS-P1-PRC-001..004, FRS-P1-CMBPRC-001..005, FRS-P1-GEN-007 |
| UR-COM-001 Lifecycle | FRS-P1-WF-001..004 |
| UR-COM-002 Approval | FRS-P1-WF-005 |
| UR-COM-003 Audit | FRS-P1-GEN-003, FRS-P1-AUD-001..003 |
| UR-COM-004 Search/Filter/Export | FRS-P1-GEN-006, FRS-P1-QRY-001..002 |
| UR-COM-005 Import | FRS-P1-IMP-001..005 |

---

# 24. Database Boundary Mapping

FRS không định nghĩa schema mới nhưng các capability P1 phải dùng các persistence boundary đã khóa:

| Functional Domain | Canonical persistence |
|---|---|
| Administrative Location | provinces, wards |
| Shared Contact | contacts |
| Organization | organizations |
| Employee Structure | departments, positions, employees, employee_assignments |
| Identity membership/direct permission | Shield users/auth_groups_users/auth_permissions_users |
| Authorization catalog | resources, resource_actions, groups, group_permissions |
| Workflow | lifecycles, workflow_states, lifecycle_states, lifecycle_transitions |
| PBAC | policies, policy_actions |
| Authorization Audit | authorization_logs |
| Product | product_groups, product_uoms, products, product_uom_conversions, product_combo_items |
| Pricing | price_lists, product_price_list_items |
| Import | import_jobs, import_job_rows |

FRS không được tự tạo:

- variants/product_variants.
- product_company_usages.
- product_price.
- profiles.
- permissions table riêng.
- generic pricing condition table P1.
- serial instance table P1.
- COMBO stock/cost table P1.

---

# 25. Open Assumptions / Decisions chưa khóa

Các điểm này không được FRS tự suy diễn thành persistence mới:

1. **Employee Gender value set:** canonical values chưa được source authority khóa. UI/application phải dùng value catalog được Product Owner phê duyệt trước production; FRS không tự đặt enum.
2. **Direct Manager history:** P1 chỉ lưu current pointer. Nếu cần effective-dated manager history phải có additive design.
3. **CHILD display separator:** data canonical là Parent Name + Child Name; exact UI separator chưa khóa và không persistence.
4. **Authorization/Import retention:** retention period và quyền xem log/job cần Security/Operations decision.
5. **Wire-level API error contract:** HTTP status, error envelope và exact code catalog chưa khóa.
6. **System Admin exact bypass mechanism:** phải do application/security layer phê duyệt; không persistence special row/column.
7. **P2 transaction trace của COMBO:** P2 phải quyết định persistence để trace leaf line về root/nested COMBO; P1 chỉ khóa algorithm và product schema.
8. **Direct leaf line + COMBO-generated leaf aggregation:** Database baseline chỉ khóa weighted aggregation khi cùng leaf nhận allocation từ nhiều COMBO. Nếu P2 muốn gộp thêm direct standalone leaf line với COMBO-generated leaf line, P2 FRS phải định nghĩa rõ pricing/trace semantics trước khi persistence.

---

# 26. Acceptance Test Catalog tối thiểu

## 26.1 Organization

- Tạo GROUP root duy nhất.
- Reject GROUP thứ hai trong business scope.
- Tạo COMPANY dưới GROUP.
- Tạo WAREHOUSE trực tiếp dưới COMPANY.
- Reject same-type nesting.
- Reject hierarchy cycle.
- Resolve Company từ Warehouse.
- User có quyền Company A+B có thể filter/tổng hợp A+B; Company C ngoài scope không xuất hiện trong result/count/export.

## 26.2 Contact và Employee

- Search Contact theo Name/Email/Phone/Mobile không trả owner ngoài authorization scope.
- User map tối đa một Employee.
- Employee không User vẫn tạo được.
- Reject self-manager.
- Reject manager cycle.
- Reject new manager inactive.
- Reject Primary Assignment overlap.
- Resolve Primary Assignment đúng Evaluation Time.

## 26.3 Authorization

- Base Permission từ Group.
- Base Permission từ direct identity permission.
- No Base => DENY trước PBAC.
- Trong một lane: Base + NO_MATCH => ALLOW.
- Trong một lane: Base + DENY => denied.
- Trong một lane: Base + ALLOW + DENY => ALLOW.
- Trong một lane: Base + ABSOLUTE_DENY => denied.
- Policy state NULL match mọi state phù hợp.
- Company/ancestor Policy inheritance được resolve trước intersection.
- Branch Policy chỉ kế thừa xuống descendant, không sang sibling.
- Employee có Assignment ở Company A và Company B được partition độc lập.
- Secondary Assignment cùng Company/scope tham gia DEPARTMENT/POSITION lane dù không phải Primary.
- Hai role cùng Organization: `ALLOW` + `DENY` => effective Organization ALLOW nếu không có `ABSOLUTE_DENY`.
- Hai role cùng Organization: `DENY` + `DENY` => effective Organization DENY.
- Company/ancestor `ABSOLUTE_DENY` applicable được propagate vào mọi lane con và không mất sau intersection.
- KQ Organization = intersection KQ user + các KQ assignment.
- KQ Company = intersection các KQ Organization.
- Permission denied ở Organization 1 nhưng allowed ở Organization 2 không thuộc KQ Company.
- GROUP participant mapping.

## 26.4 Workflow

- Một lifecycle có một initial state.
- Reject transition state ngoài lifecycle.
- Reject duplicate lifecycle/from/action tới destination khác.
- Authorization ALLOW + invalid transition => reject.
- Valid transition + Authorization DENY => reject.

## 26.5 Product Role

- SIMPLE derivation.
- PARENT derivation khi có child.
- CHILD derivation.
- COMBO derivation.
- Reject child-of-child.
- Reject COMBO parent/child.
- Reject transaction/select PARENT.
- SIMPLE to PARENT reject khi dependency còn tồn tại.

## 26.6 UOM

- Base UOM required SIMPLE/CHILD/COMBO.
- Alternate UOM direct-to-base.
- Reject factor <= 0.
- SALES/BOTH price required.
- PURCHASE price nullable.
- Reject UOM Conversion cho COMBO.

## 26.7 COMBO

- COMBO component SIMPLE hợp lệ.
- COMBO component CHILD hợp lệ.
- Nested COMBO hợp lệ.
- Reject PARENT component.
- Reject self component.
- Reject duplicate component row.
- Reject cycle A -> B -> A.
- Reject deep cycle A -> B -> C -> A.
- Flatten nested quantity đúng.

## 26.8 Pricing

- P1 price/amount dùng VND và không yêu cầu FX/currency selection.
- Company Price List thắng Group.
- Company Price List thiếu item fallback Group.
- Group thiếu item fallback default.
- Overlap list deterministic theo priority/effective_from/id.
- Boundary time đúng `[from,to)`.
- COMBO Price List chỉ Base UOM.

## 26.9 COMBO Allocation

- Root price resolve một lần.
- Nested COMBO price không được dùng.
- Weight = leaf qty × leaf default price.
- Leaf Price List không ảnh hưởng weight.
- Total weight = 0 => reject.
- Sum allocated amount = root combo total.
- Residual deterministic theo product_id order.
- Cùng leaf qua nhiều path aggregate quantity.
- Cùng leaf từ nhiều COMBO aggregate amount/quantity và weighted-average unit price.

## 26.10 Import

- Row success/error counts reconcile.
- Failed row rollback.
- Child trước Parent vẫn import được khi dependency hợp lệ.
- COMBO trước component vẫn resolve được.
- Nested COMBO dependency resolve được.
- Combo cycle bị reject.
- Original row number giữ nguyên trong report.

---

# 27. Definition of Done cho FRS P1

FRS P1 được xem là đủ điều kiện chuyển sang design/implementation planning khi:

- Mọi capability P1 có requirement functional và acceptance criteria.
- Không có persistence mới trái Database Design v1.4.
- Authorization semantics nhất quán với Base Permission + QT1 per-lane + inheritance-before-intersection + multi-role denied-set intersection.
- Workflow transition và Authorization được tách rõ.
- Organization/Employee context resolution được mô tả đủ để implement/test.
- Product Model V2 không có Variant và role derivation nhất quán.
- COMBO semantics theo baseline v1.4 được mô tả đầy đủ: nested, acyclic, no serial, no UOM conversion, root-price-only, flatten, allocation, rounding và weighted aggregation.
- Core Pricing Company -> Group -> Default deterministic.
- Import dependency graph và row-level history đủ để test.
- Open assumptions không bị biến thành schema hoặc behavior ngầm.
- Traceability bao phủ toàn bộ P1 User Requirements trong `00_User_Requirements_v1.0.7-draft.md`.
- Không còn delta đã biết giữa User Requirements v1.0.7-draft và Database Design v1.4 đối với invariant P1; mọi xung đột mới phải đi qua change control thay vì được FRS tự suy diễn.
