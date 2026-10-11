---
document_id: "FRS-MINI-ERP-P1-001"
title: "P1 Functional Requirements Specification - Dinktech Mini ERP"
version: "1.10"
status: "baseline"
owner: "Product / Architecture"
date: "2026-10-07"
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

Revision v1.10 là FRS baseline sau `CHANGE-DISPLAY-SETTING-001`. Revision này bổ sung Display Settings presentation-only foundation và Resource `display_setting` trước Gate 8; đồng thời kế thừa nguyên trạng `CHANGE-AUTH-DOMAIN-001`, `CHANGE-PRD-001`, PBAC precedence, denied-set intersection và Resource Instance Authorization boundary.

## 1.1 Nguồn và thứ tự authority

FRS này sử dụng các authority input sau:

1. `00_User_Requirements_v1.0.14.md` - business intent, user need, priority và acceptance criteria P1.
2. `02_P1_Database_Design_v1.13.md` - persistence/schema invariant và technical database boundary.

`03_P1_Implementation_Contract_v1.8.md` là downstream authority cho wire/runtime convention (HTTP status, error envelope/code, serialization, locking convention...). FRS có thể dẫn chiếu contract đó nhưng không dùng nó để thay đổi business/functional semantics đã khóa ở UR/FRS/DBD.

User Requirements v1.0.14 đã được harmonize với Database Design v1.13 cho Product Model V2, Product Group hierarchy, COMBO, UOM, Core Pricing, Authorization, Workflow và Import. FRS phải giữ nguyên business intent của User Requirements và không tạo persistence/capability trái Database Design. Nếu phát sinh xung đột mới giữa business requirement và technical boundary, xung đột phải được ghi nhận và xử lý qua change control; FRS không tự âm thầm chọn một mô hình thứ ba.

### 1.1.1 Trạng thái harmonization hiện tại

Tại thời điểm hoàn thiện revision này, không còn delta đã biết giữa User Requirements v1.0.14 và Database Design v1.13.

- SIMPLE/CHILD là selectable leaf transactionable Product.
- COMBO là selectable composition/template có Base UOM, default price và Price List riêng nhưng không phải inventory/serial/costing/posting leaf.
- COMBO không có Serial và không có UOM Conversion; nested COMBO được phép nhưng graph phải acyclic.
- COMBO root được resolve giá một lần, flatten về SIMPLE/CHILD, phân bổ selling amount theo default-price weight và aggregate cùng leaf bằng tổng quantity/tổng amount.
- Core Pricing dùng Product + UOM + Company + Effective Time với fallback Company -> Group -> default đúng UOM.
- Policy, Workflow và Import sử dụng đúng semantics đã harmonize trong hai nguồn trên.
- System Admin không có bypass: là User/Employee có Assignment tại `GROUP`, cần Base Permission và chịu PBAC/`ABSOLUTE_DENY`/Workflow như mọi User khác; initial privileged membership được provision qua bootstrap/deployment mechanism.
- Resource Domain canonical gồm `SYSTEM`, `MASTER`, `ORGANIZATION`. `SYSTEM` và `MASTER` resolve Evaluation Organization tại `GROUP`; `ORGANIZATION` phải derive authoritative context từ target/owner/parent/persisted context theo Resource contract. `COMPANY` không còn là Resource Domain canonical.
- Evaluation Organization authoritative của existing `ORGANIZATION` Resource derive từ persisted target/owner; create derive từ owner/parent/scope canonical. `organization.create` evaluate tại parent và `employee_assignment` tại Assignment Organization. Missing/unknown domain hoặc unresolved required context phải fail closed; client context và Primary Assignment không được dùng để override/fallback Resource scope.
- Policy/Assignment inheritance dùng full Organization path `GROUP -> ... -> Evaluation Organization`; `SYSTEM`/`MASTER` Resource evaluate trực tiếp tại `GROUP`.
- Mọi securable data/capability, kể cả `user`, `authorization_group`, `resource`, `policy`, `organization`, phải map vào Resource Type; P1 vẫn không có Resource Instance Authorization.
- Security assignment actions tách `authorization_group.assign_member`, `authorization_group.assign_permission`, `user.assign_permission`. Direct User Permission vẫn dùng identity mechanism hiện hành.
- Import Framework tồn tại ở P1 nhưng target phải explicit enable; baseline hiện tại chỉ `PRODUCT`, với Import Job mode được persistence.
- Business timezone được cấu hình theo từng Company qua CodeIgniter Shield/Settings integration mechanism; business DATE derive từ Evaluation Time theo timezone Company.
- Auto Numbering là foundation capability theo Resource catalog: nhiều series/resource, tối đa một active default, scope `GROUP`/`COMPANY`/`ORGANIZATION`, generation mode `AUTO_REQUIRED`/`AUTO_IF_EMPTY`, reset `NEVER`/`YEARLY`/`MONTHLY`/`DAILY`; Product Import hỗ trợ source-local key khi Product Code được sinh tự động.
- Auto Numbering activation phải tương thích với business identifier uniqueness namespace do Resource adapter/domain khai báo (`GLOBAL`/`COMPANY`/`ORGANIZATION`). Manual identifier của `AUTO_IF_EMPTY` không consume counter; generated candidate đã bị chiếm phải được bỏ qua an toàn. Series đã phát hành number có các field identity/scope/reset bị khóa và không được hard-delete.
- Display Settings là presentation-only capability: server override `SYSTEM`/`ORGANIZATION`, exact Organization ancestor inheritance, local User preference phía frontend, locale `vi`/`en` vẫn theo workspace preference; `Display.timezone` không thay Business Timezone.

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
14. Auto Numbering theo Resource catalog.
15. Import Framework với explicit Resource enablement; P1 baseline enable Product import.
16. Search/Filter/Sort/Pagination/Export dùng chung.
17. Audit actor/timestamp cho mutable master/config P1.
18. Giao diện đa ngôn ngữ baseline `vi`/`en` với API language negotiation.
19. Display Settings / Presentation Settings dùng chung trước Gate 8 Frontend P1.

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

- **System Admin**: Shield User có Employee mapping và Employee Assignment tại Organization `GROUP` với Department IT và Position khi áp dụng; có Base Permission và chịu cùng PBAC/`ABSOLUTE_DENY`/Workflow như mọi User khác, không có bypass.
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
- FRS không tự định nghĩa wire-level HTTP status/error-code catalog; service phải trả semantic error đủ rõ và API mapping phải tuân `03_P1_Implementation_Contract_v1.8.md`.

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

## FRS-P1-GEN-008 - Business timezone theo Company

**Priority:** MUST

Evaluation Time là instant thống nhất của operation. Khi business rule dùng giá trị `DATE` trong Company scope, application phải derive `EvaluationDate` theo timezone cấu hình của Company bằng CodeIgniter Shield/Settings integration mechanism được phê duyệt.

**Acceptance criteria**

- Không dùng browser timezone làm authority cho effective-date business rule.
- Không hard-code một timezone chung nếu Company đã có timezone setting riêng.
- Group-level operation không có Company owner chỉ dùng Company timezone khi functional context chỉ định rõ Company; nếu không, không tự chọn một Company tùy ý.
- Cùng một operation phải dùng cùng Evaluation Time để derive date cho các kiểm tra Assignment/Pricing/effective period liên quan.

## FRS-P1-GEN-009 - Giao diện đa ngôn ngữ và Locale Resolution

**Priority:** MUST

P1 hỗ trợ locale `vi` và `en`; default locale là `vi`. Locale là presentation/request-language concern, không phải business-data translation.

**Frontend behavior**
- Vue UI dùng `vue-i18n` làm source cho application-controlled text.
- Language selector trên Top Application Bar đổi locale runtime và cập nhật `document.documentElement.lang`.
- Locale preference được giữ theo workspace/user-browser preference; P1 không bắt buộc server-side locale profile persistence.
- API client gửi `Accept-Language` bằng locale hiện tại sau mỗi lần đổi locale.
- Route path, field key, enum/code và business identifier không đổi theo locale.
- User-entered business data không tự dịch.

**Backend behavior**
- API resolve request locale từ `Accept-Language` bằng CodeIgniter language negotiation trong supported locales `[vi, en]`.
- Unsupported/no-match locale fallback `vi`.
- Human-readable API `error.message` và `details[].message` được localize khi translation tồn tại; stable machine `error.code` và `details[].code` không đổi.
- Response có human-readable localized content phải phản ánh negotiated locale bằng `Content-Language`.
- API media contract vẫn là JSON; language negotiation không mở thêm HTML/XML API representation.

**Presentation rules**
- Date/number/currency có thể được format theo locale ở UI, nhưng API/persistence canonical format, business timezone và decimal semantics không thay đổi.
- Status/effect/role code được translate ở display layer nhưng raw value vẫn canonical, ví dụ `ACTIVE`, `ABSOLUTE_DENY`, `COMBO`.
- Import template/header canonical không localize.

**Acceptance criteria**
- Chuyển `vi -> en -> vi` không cần reload và cập nhật shell/menu/common form text.
- API validation cùng lỗi có stable error code giống nhau ở hai locale và localized message khác nhau khi translation có sẵn.
- `Accept-Language: fr` fallback `vi`.
- Missing frontend translation fallback `vi` và bị test/lint phát hiện trong development/test.
- Locale switch không đổi Evaluation Organization, business timezone, permission decision hoặc persisted record.

---

## FRS-P1-DSP-001 - Display Setting Registry và presentation-only boundary

**Priority:** MUST
**Actors:** ERP User (runtime consumption), Business Administrator có quyền `display_setting.view/edit`

Display Settings P1 là source-controlled registry cho presentation configuration. Registry key không phải business data và client không được tự tạo key mới.

Canonical server keys P1:

```text
Display.timezone
Display.dateFormat
Display.dateTimeFormat
Display.timeFormat
Display.firstDayOfWeek
Display.number.decimalSeparator
Display.number.groupSeparator
Display.quantityDecimals
Display.percentageDecimals
Display.trailingZeros
Display.money.decimalSeparator
Display.money.groupSeparator
Display.money.symbolMode
Display.money.symbolPosition
Display.money.decimalsMode
```

Canonical defaults:

```text
Display.timezone                 Asia/Ho_Chi_Minh
Display.dateFormat               DD/MM/YYYY
Display.dateTimeFormat           DD/MM/YYYY HH:mm
Display.timeFormat               HH:mm
Display.firstDayOfWeek           MONDAY
Display.number.decimalSeparator  .
Display.number.groupSeparator    ,
Display.quantityDecimals         3
Display.percentageDecimals       3
Display.trailingZeros            false
Display.money.decimalSeparator   .
Display.money.groupSeparator     ,
Display.money.symbolMode         SYMBOL
Display.money.symbolPosition     AUTO
Display.money.decimalsMode       CURRENCY_DEFAULT
```

Validation/allowed values phải do registry định nghĩa. `Display.timezone` phải là IANA timezone hợp lệ. Separator không được biến canonical API number thành localized input contract.

**Presentation-only rules**
- Resolver/formatter Display Settings không được gọi như business-domain authority.
- `Display.timezone` chỉ phục vụ display conversion của instant/datetime; `BusinessTimezoneResolver` theo Company vẫn độc lập.
- Quantity/Money/Percentage decimals là display precision; calculation sử dụng canonical precision/domain rule, không sử dụng giá trị đã display-round.
- Locale không thuộc registry này; locale vẫn canonical `vi`/`en` theo FRS-P1-GEN-009.
- Cả 15 canonical Display Setting key đều `user_overridable = true` ở **local frontend preference layer**; P1 không persistence các override này trên server.
- `Workspace.pageSize`, `Workspace.density`, sidebar/tabs/favorites/recent là client workspace preference, không phải server Display Settings trong P1.

**Acceptance criteria**
- Unknown key bị reject.
- Invalid data type/enum/timezone bị reject.
- Không có setting nào trong registry này thay đổi business validation, authorization hoặc persistence semantics.

## FRS-P1-DSP-002 - Server scope và effective resolution

**Priority:** MUST

Server persistence scope chỉ gồm:

```text
system
organization:<organization_id>
```

Không có `company:<id>` hoặc `user:<id>` server scope trong P1 Display Settings.

Với Organization context `O`, effective value resolve theo exact ancestor path:

```text
O
-> parent(O)
-> ...
-> GROUP
-> system
-> registry default
```

**Functional behavior**
- Resolver phải validate Organization tồn tại và dùng canonical `organizations` hierarchy.
- Override gần leaf nhất thắng.
- Sibling/out-of-path Organization không tham gia.
- Không có Organization context thì resolve `system -> default`.
- Runtime resolution chỉ đọc key/scope được registry cho phép; legacy/invalid DB row không được override registry semantics.

**Acceptance criteria**
- Warehouse override thắng Plant/Company/GROUP/System.
- Khi Warehouse không override, fallback đúng Plant/Branch/Company/GROUP theo actual path rồi SYSTEM/default.
- Company là Organization type `COMPANY`, không có pseudo-scope riêng.
- Re-parent Organization làm runtime path follow hierarchy hiện tại; setting row vẫn gắn đúng Organization id.

## FRS-P1-DSP-003 - Quản trị override, Authorization và audit

**Priority:** MUST

Resource Type: `display_setting`.

```text
Domain: ORGANIZATION
Actions: view, edit
```

Authoritative Evaluation Organization:
- context `system` -> `GROUP` root;
- context `organization:<id>` -> chính target Organization.

**Functional behavior**
- View definitions/override/effective-source metadata cần `display_setting.view` tại authoritative context khi endpoint là administrative surface.
- Runtime effective snapshot tối thiểu cần authentication và chỉ trả non-sensitive presentation values; runtime bootstrap không bắt mọi ERP User phải có administrative `display_setting.view`. Nếu caller yêu cầu Organization context, Organization visibility phải được enforce trước khi snapshot được trả.
- Set/reset override cần `display_setting.edit` và phải đi qua canonical AuthorizationService; không dùng `auth()->user()->can()` như authorization substitute.
- Batch set/reset phải validate toàn bộ request trước mutation và atomic đối với settings mutation thuộc cùng request.
- Reset xóa override để inheritance tiếp tục; không ghi registry default thành override giả.
- Mutable server setting phải tạo audit evidence actor/timestamp/request correlation theo technical audit sink đã được repository/baseline phê duyệt. Không dùng `authorization_logs` như general mutation audit.

**Acceptance criteria**
- User có Base Permission nhưng PBAC DENY không sửa được Display Settings.
- System context evaluate tại GROUP; Organization context không dùng actor Primary Assignment để override target.
- Không visible target trả contract 404; visible nhưng thiếu `edit` trả 403.
- Mutation fail không để partial batch.

## FRS-P1-DSP-004 - Runtime snapshot, local User overlay và formatter boundary

**Priority:** MUST

Backend cung cấp effective **server display snapshot**. Frontend overlay local User preference đối với key được phép override và dùng một formatter layer dùng chung.

P1 local preferences gồm:
- `ui.locale` (`vi`/`en`);
- local override cho toàn bộ 15 canonical Display Setting key;
- workspace page size/density và shell preference theo UI Design.

Local display override áp dụng global theo authenticated User/browser workspace và thắng server effective display value; không scope local override theo Organization trong P1.

Local preference không được gửi như authoritative Evaluation Organization hoặc business timezone.

Formatter layer tối thiểu:

```text
formatDate
formatDateTime
formatTime
formatQuantity
formatPercentage
formatMoney
```

**Canonical-value rule**
- Formatter nhận canonical API value và trả display text.
- Form/edit model phải giữ canonical value; không lấy display-formatted text làm source để submit lại API.
- BIGINT vẫn là string; DECIMAL/quantity/money vẫn là decimal string trong API/domain model.

**Acceptance criteria**
- Đổi display setting làm list/detail rerender nhưng không mutate business record.
- Edit-save sau display formatting vẫn gửi canonical value.
- Đổi local timezone không thay Pricing/Assignment effective-date result.
- Đổi locale không thay Display Setting server inheritance hoặc business timezone.

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
- Contact là Resource Domain `ORGANIZATION`; authoritative Evaluation Organization luôn derive từ owner canonical, không nhận client context làm authority.
- Owner `ORGANIZATION` -> Evaluation Organization là chính Organization owner. Owner `EMPLOYEE` -> Employee là `MASTER` Resource nên Evaluation Organization = `GROUP`.
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
- Employee là Resource Domain `MASTER`; Employee master authorization evaluate tại Organization `GROUP`.
- Organization/Department/Position relationship của Employee chỉ tồn tại qua Employee Assignment; filter/projection từ Assignment không biến Employee thành Organization-owned record.
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
- `employee.view/create/edit/setstate/export` evaluate tại `GROUP`; Organization/Department/Position filters chỉ là data filters qua effective Assignment.

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

## FRS-P1-EMP-005 - Resolve actor và Evaluation Organization context

**Priority:** MUST

FRS phân biệt:

```text
Default Actor Organization Context
    = User -> Employee -> Primary Assignment tại Evaluation Time
```

và:

```text
Evaluation Organization Context
    = Organization Context authoritative của operation/resource đang authorize
```

Evaluation Organization Context có thể là `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`.

### Resource Domain resolution

Mọi protected Resource operation phải resolve canonical Resource Domain trước khi authorize:

```text
SYSTEM
    -> Evaluation Organization = GROUP

MASTER
    -> Evaluation Organization = GROUP

ORGANIZATION
    -> derive từ authoritative target / owner / parent / persisted context

missing/unknown domain
    -> fail closed

ORGANIZATION nhưng không resolve được authoritative context
    -> fail closed
```

`ORGANIZATION` là authorization-scope classification; nó **không** có nghĩa Evaluation Organization bắt buộc có type `COMPANY`. Resolver có thể trả `GROUP`, `COMPANY`, `BRANCH`, `PLANT` hoặc `WAREHOUSE` tùy Resource contract.

**Authoritative context resolution:**

- Existing `ORGANIZATION` Resource/record: derive từ persisted target/owner canonical; client-supplied context không được override và mismatch phải reject.
- Create `ORGANIZATION` Resource: derive từ owner/parent/scope canonical trong business payload.
- `organization.create`: Evaluation Organization = Parent Organization.
- `employee_assignment.create/edit`: Evaluation Organization = Assignment Organization.
- `policy`: Evaluation Organization = `policy.organization_id`.
- `price_list`: Evaluation Organization = `price_list.organization_id`.
- `authorization_log`: persisted evaluated `organization_id`; `NULL` được xem là Group-level evidence và evaluate tại `GROUP`.
- `import_job`: inherit canonical scope của enabled import target; P1 target `PRODUCT` là `MASTER` nên Import Job evaluate tại `GROUP`.
- Primary Assignment **không** được dùng làm fallback sau khi Resource Domain đã xác định authorization scope.

Default Actor Organization Context vẫn được phép dùng cho use-case được explicit contract cho phép khi cần chọn business context và không có Resource target/owner authoritative; nó không được dùng để mở rộng hoặc hoàn tất một Resource authorization context đang thiếu.

Scope path authorization:

```text
GROUP root -> ... -> Evaluation Organization Context
```

Một Assignment effective eligible khi Assignment organization bằng Evaluation Organization hoặc là ancestor của nó trên scope path. Assignment tại `GROUP` vì vậy có thể tham gia mọi descendant context; Assignment thuộc sibling hoặc Company khác không tham gia.

Khi nghiệp vụ cần Company owner:

```text
Evaluation Organization Context -> self/ancestor COMPANY
```

`GROUP` không resolve thành Company owner.

**Acceptance criteria**

- `SYSTEM` và `MASTER` Resource luôn authorize tại `GROUP`.
- `ORGANIZATION` Resource phải có authoritative context resolver theo Resource contract; không resolve được thì fail closed.
- `COMPANY` không phải Resource Domain canonical.
- Domain chỉ xác định Evaluation Organization; không tự cấp Base Permission và không bypass PBAC/`ABSOLUTE_DENY`/Workflow.
- Primary Assignment chỉ dùng để resolve default actor context khi use-case explicit cho phép; không override domain-derived scope.
- Primary flag không phải điều kiện duy nhất để Assignment tham gia Authorization.
- Secondary/Group Assignment effective được phép tham gia Department/Position participant matching khi Assignment nằm trên exact ancestor path của Evaluation Organization.
- Assignment ở Company/nhánh khác không được tham gia participant matching.
- Existing target không đổi Authorization context chỉ vì client gửi Organization id khác.
- Create Company dưới GROUP evaluate `organization.create` tại GROUP; create Branch/Plant/Warehouse evaluate tại parent tương ứng.
- Employee Master evaluate tại `GROUP`; Employee Assignment mutation evaluate tại Organization của Assignment.
- Không có context cần thiết cho operation thì không tự tạo ALLOW.

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
- import (chỉ Resource được explicit enable)
- assign_member
- assign_permission
- configure_numbering

**Functional behavior**

- Quản trị Resource Type.
- Quản trị Action thuộc Resource.
- Mỗi Resource + Action là một permission unit.
- Mọi loại dữ liệu/capability cần bảo vệ đều phải có Resource Type canonical, gồm cả configuration/security data như `organization`, `user`, `authorization_group`, `resource`, `policy`.
- `authorization_group` là Resource Type của nhóm phân quyền; không dùng `group` làm canonical resource type để tránh nhầm với Organization type `GROUP`.
- Resource Type biểu diễn loại dữ liệu/capability, không phải từng business record instance.

**Validation**

- Resource type unique.
- Action code unique trong Resource.

**Acceptance criteria**

- `product.view` không đồng nghĩa `product.edit`.
- Quản trị User/Authorization Group/Resource/Policy/Organization cũng phải qua Resource/Action authorization, không có implicit admin access.
- Không tạo Permission table riêng.
- Không tạo một Resource row cho từng User/Product/Organization record; P1 vẫn không có Resource Instance Authorization.
- `resource.configure_numbering` là action độc lập để quản trị Auto Numbering configuration; `resource.edit` không tự cấp quyền này.

### Resource Domain - authorization-scope classification

Mỗi Resource Type P1 phải có đúng một canonical `domain`. Resource Domain là security-relevant metadata dùng để resolve Evaluation Organization, không chỉ là UI/module grouping.

Canonical values:

```text
SYSTEM
MASTER
ORGANIZATION
```

Rules:

- `SYSTEM`: system/security/configuration Resource dùng chung toàn Group; Evaluation Organization = `GROUP`.
- `MASTER`: business master/reference dùng chung toàn Group; Evaluation Organization = `GROUP`.
- `ORGANIZATION`: Resource có authorization/data scope derive từ authoritative target/owner/parent/persisted context.
- `ORGANIZATION` không đồng nghĩa Organization type `COMPANY`; resolver có thể trả bất kỳ canonical Organization Context nào.
- `COMPANY` không phải Resource Domain canonical.
- Missing/unknown domain hoặc unresolved required `ORGANIZATION` context phải fail closed.
- Resource Domain không tạo Permission, không bypass Base Permission/PBAC/`ABSOLUTE_DENY`/Workflow.
- Domain của canonical existing Resource là immutable trong P1; thay đổi domain là authorization-semantic change và phải qua change control.

Canonical P1 matrix:

| Resource | Domain | Evaluation Organization |
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
| `employee_assignment` | `ORGANIZATION` | `assignment.organization_id` |
| `policy` | `ORGANIZATION` | `policy.organization_id` |
| `authorization_log` | `ORGANIZATION` | persisted evaluated Organization; `NULL -> GROUP` |
| `price_list` | `ORGANIZATION` | `price_list.organization_id` |
| `import_job` | `ORGANIZATION` | enabled import-target canonical scope |
| `display_setting` | `ORGANIZATION` | `system -> GROUP`; `organization:<id> -> target Organization` |

**Acceptance criteria bổ sung**

- Tất cả 21 Resource P1 có domain đúng matrix trên.
- Không tồn tại canonical Resource domain `COMPANY`.
- Runtime không được dùng actor Primary Assignment làm generic fallback cho Resource Domain resolution.

## FRS-P1-IAM-003 - Authorization Group

**Priority:** MUST

Authorization Group là nhóm quyền, không phải Organization GROUP.

**Functional behavior**

- Create/Edit/Inactivate Authorization Group.
- `groups.code` là canonical alias map tới Shield membership group string.
- Gán/bỏ User membership cho Group qua action `authorization_group.assign_member`.
- Gán/bỏ Resource Action cho Group qua action `authorization_group.assign_permission`.

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
- Khi Mini ERP expose quản trị direct permission, mutation phải dùng action `user.assign_permission` và ghi vào identity permission mechanism hiện hành; không tạo local override persistence.

**Acceptance criteria**

- User có permission từ bất kỳ Group active hợp lệ nào thì permission đó vào Base Permission.
- Direct permission không map được vào catalog thì không tạo permission ERP hợp lệ.

---


## FRS-P1-NUM-001 - Auto Numbering configuration theo Resource

**Priority:** MUST

Auto Numbering được cấu hình theo Resource Type trong Resource catalog; configuration không tạo Resource Instance Authorization.

**Functional behavior**
- Một Resource có thể có zero hoặc nhiều numbering series.
- Một Resource có tối đa một active default series; non-default series chỉ được dùng khi use-case chọn series hợp lệ.
- Series định nghĩa `scope_type`, `pattern`, `reset_policy`, `generation_mode`, `start_number`, `increment_by`, default flag và status.
- `scope_type`: `GROUP`, `COMPANY`, `ORGANIZATION`.
- `generation_mode`: `AUTO_REQUIRED`, `AUTO_IF_EMPTY`.
- `reset_policy`: `NEVER`, `YEARLY`, `MONTHLY`, `DAILY`.
- Resource adapter/domain phải khai báo canonical business identifier field và uniqueness namespace `GLOBAL`/`COMPANY`/`ORGANIZATION`; đây là runtime/domain contract, không yêu cầu thêm column vào Resource catalog.
- Khi activate/update series, hệ thống phải validate `scope_type + reset_policy + pattern` tương thích với uniqueness namespace để các counter partition khác nhau không phát hành cùng business identifier trong cùng uniqueness namespace.
- Sau khi series đã có ít nhất một committed counter/issuance, `resource`, `series_code`, `scope_type` và `reset_policy` không được đổi; series đã phát hành number không được hard-delete mà chỉ có thể chuyển `INACTIVE`.
- Quản trị configuration yêu cầu `resource.configure_numbering`.

**Acceptance criteria**
- Resource không có active/default series không bị tự động áp numbering.
- Không được có hai active default series cho cùng Resource.
- Series có configuration không đủ phân biệt Company/Organization/period theo uniqueness rule bị reject trước khi active.
- `AUTO_REQUIRED` reject manual business identifier trên create path.
- `AUTO_IF_EMPTY` chỉ generate khi business identifier trống.
- Series đã phát hành number không thể đổi scope/reset identity hoặc bị hard-delete.
- Chỉnh configuration hợp lệ không renumber record lịch sử.

## FRS-P1-NUM-002 - Generate number, scope và transaction semantics

**Priority:** MUST

Auto Number được generate server-side qua một service dùng chung cho UI/API/Import/background create path.

**Scope resolution**
- `GROUP`: counter thuộc GROUP root.
- `COMPANY`: counter thuộc Company owner/self Company được resolve từ authoritative Organization Context.
- `ORGANIZATION`: counter thuộc exact authoritative Evaluation Organization Context.

**Rules**
- Pattern hỗ trợ canonical sequence/date/context token theo Implementation Contract; không execute script/expression tùy ý.
- Reset `YEARLY`/`MONTHLY`/`DAILY` tính period theo Company business timezone khi scope nằm trong Company. Group-scope series dùng date/reset chỉ hợp lệ khi Group/default business timezone đã được cấu hình qua Settings.
- Với reset khác `NEVER`, final identifier phải chứa đủ period discriminator tương ứng để không lặp lại identifier giữa các period trong cùng business uniqueness namespace.
- Nếu counter được partition hẹp hơn business identifier uniqueness namespace, final identifier phải chứa context discriminator tương ứng hoặc uniqueness key của domain phải tự bao gồm dimension đó.
- Generate number và create business record phải nằm trong cùng transaction boundary. Nếu transaction rollback, counter rollback cùng transaction và number chưa commit có thể được cấp lại.
- `AUTO_IF_EMPTY` manual identifier hợp lệ không consume counter. Nếu generated candidate trùng một business identifier đã tồn tại, AutoNumberService phải advance sequence và thử candidate kế tiếp trong cùng serialized transaction cho tới khi tìm được identifier hợp lệ; counter commit ở sequence thực tế đã phát hành.
- Number đã commit không được tái sử dụng do cancel/inactivate/delete logic sau đó.
- Concurrent create trong cùng series/scope/period phải serialize để không phát hành duplicate number.

**Acceptance criteria**
- Company A và Company B có counter độc lập đối với Company-scoped series.
- Series/reset configuration có nguy cơ lặp identifier trong cùng uniqueness namespace bị reject.
- Manual `AUTO_IF_EMPTY` value trùng sequence candidate tương lai không làm Auto Number bị kẹt; generator bỏ qua identifier đã tồn tại.
- Hai concurrent create cùng counter không commit cùng number.
- Rollback không tạo gap do counter commit riêng khỏi business mutation.
- Committed number không bị reuse.

## FRS-P1-NUM-003 - Product Import với Auto Numbering

**Priority:** MUST

Product Import phải hỗ trợ source-local identity để dependency graph không phụ thuộc vào generated `product_code`.

**Identifier semantics**
- **Persistent Target Key:** `product_code`, dùng để lookup Product đã tồn tại cho `UPDATE_ONLY`/UPSERT-existing.
- **Source-local Key:** `source_key`, chỉ có hiệu lực trong file/job hiện tại để nhận diện Product row và resolve Parent/UOM/COMBO dependency.
- **Generated Business Identifier:** `product_code` do AutoNumberService phát hành cho create path khi numbering yêu cầu/áp dụng.
- Canonical mapping baseline: transport/import field `product_code` maps to database/domain field `products.code`; max length 50; uniqueness namespace `GLOBAL`.

**Rules**
- Mỗi Product row có `source_key` duy nhất trong file.
- Create row khi Product dùng `AUTO_REQUIRED` có thể để `product_code` trống; code được AutoNumberService sinh trong mutation transaction.
- Parent/Child, UOM Conversion và COMBO Item có thể reference Product cùng file bằng source-local key; Product đã tồn tại có thể reference bằng persistent `product_code`.
- `UPDATE_ONLY` cần `product_code` tồn tại để resolve target.
- Với `UPSERT`, row thiếu `product_code` đi theo create path; row có `product_code` đi theo lookup/update-or-create semantics nhưng vẫn phải tuân generation mode. `AUTO_REQUIRED` không được dùng một manual code chưa tồn tại để bypass generation.

**Acceptance criteria**
- Child có thể tham chiếu Parent create mới qua `parent_source_key` dù Parent chưa có generated code tại parse time.
- COMBO Item có thể tham chiếu owner/component create mới bằng source key.
- Generated Product Code vẫn unique và được ghi vào row result/entity key khi row thành công.

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

Khi evaluate Policy, system build các authorization lane phù hợp với Evaluation Organization Context và exact ancestor path từ `GROUP` root. Với Company-scoped operation, từng Company được evaluate độc lập.

**Participant inputs**

- `USER`: current Shield User.
- `GROUP`: active Authorization Groups current User là member; tham gia actor-level lane.
- `POSITION`: Position từ Employee Assignment effective eligible.
- `DEPARTMENT`: Department từ Employee Assignment effective eligible.
- `ORGANIZATION`: contextual participant tại node trên scope path đang applicable.

**Assignment eligibility**

Với Evaluation Organization Context `O`:
- `O` có thể là `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`;
- build `ScopePath(O) = GROUP root -> ... -> O`;
- chỉ xét Assignment effective của Employee có `organization_id = O` hoặc Organization là ancestor của `O` trên `ScopePath(O)`;
- Assignment tại `GROUP` được phép cung cấp DEPARTMENT/POSITION participant cho mọi descendant;
- Assignment thuộc sibling Organization hoặc subtree Company khác không được trộn vào lane hiện tại;
- Primary flag không phải điều kiện duy nhất để Assignment tham gia PBAC; Primary chỉ quyết định default actor context.

**Acceptance criteria**

- Employee có GROUP Assignment được kế thừa role xuống descendant Organization.
- Employee có nhiều Assignment trong nhiều Company được partition đúng Company khi operation ở Company scope.
- Một User có nhiều Department/Position trong cùng scope có nhiều assignment lane độc lập trước bước merge.
- Policy participant không match thì Policy đó không tham gia QT1 của lane tương ứng.
- Không dùng participant như arbitrary business condition.

## FRS-P1-PBAC-003 - Policy inheritance theo Organization

**Priority:** MUST

Inheritance được resolve **trước** multi-lane intersection.

Với bất kỳ Evaluation Organization `O` thuộc `GROUP`/`COMPANY`/`BRANCH`/`PLANT`/`WAREHOUSE`, Policy source canonical là:

```text
GROUP root
-> ...
-> O
```

Tức là toàn bộ node trên exact ancestor path từ `GROUP` tới `O`. Khi `O = GROUP`, path chỉ có `GROUP`.

**Rules**

- Department/Position lane tại `O` phải xét Policy applicable kế thừa từ toàn bộ Organization phía trên, kể cả `GROUP`.
- Actor-level lane tại `O` cũng phải xét Policy applicable theo cùng scope path.
- `ORGANIZATION` participant rule applicable trên scope path là contextual restriction chung cho scope tương ứng.
- Không copy Policy vật lý xuống child; inheritance được resolve runtime.
- Hierarchy depth không phải Policy Priority.
- Applicable `ABSOLUTE_DENY` tại bất kỳ node nào trên scope path phải được propagate vào mọi KQ lane con chịu scope trước intersection.

**Acceptance criteria**

- GROUP Policy có thể tác động tới mọi descendant nếu các dimension/participant còn lại match.
- Company Policy có thể tác động tới Branch/Plant/Warehouse descendant trong Company đó.
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
AUTHORIZE(User, Resource, Action, EvaluationOrganization O, State, Time):

    if no base permission(Resource, Action):
        DENY

    scope_path = GROUP root -> ... -> O

    common_context_policies[O] =
        resolve ORGANIZATION participant policies over scope_path

    KQ[user, O] =
        resolve actor-level policies (USER/GROUP)
        + common_context_policies[O]
        over scope_path
        by QT1

    eligible_assignments =
        effective assignments where
        assignment.organization = O
        OR assignment.organization is ancestor of O on scope_path

    FOR EACH eligible Assignment A:
        KQ[A, O] =
            resolve DEPARTMENT/POSITION policies
            + common_context_policies[O]
            over scope_path
            by QT1

    mandatory_absolute_denied[O] =
        all applicable ABSOLUTE_DENY matched on scope_path

    inject mandatory_absolute_denied[O]
        into every participating KQ lane

    KQ[O] =
        INTERSECTION(
            KQ[user, O],
            all KQ[A, O]
        )

    if Resource+Action in KQ[O]:
        DENY
    else:
        ALLOW

FOR EACH COMPANY C when company summary is required:
    evaluate C and descendant Organizations in C independently
    KQ[C-summary] = INTERSECTION(all KQ[O] in C)
```

**Semantic rules**

- Ordinary `DENY` là restriction của lane; giữa nhiều lane, User hưởng quyền cao nhất. Permission chỉ còn bị ordinary-denied nếu nằm trong giao của mọi KQ lane liên quan.
- `ALLOW` hoặc `NO_MATCH` trong một lane làm permission không thuộc KQ denied của lane.
- `ORGANIZATION` participant policies applicable trên `GROUP -> ... -> O` được xem là contextual rules dùng chung và được đưa vào mọi lane trước QT1.
- Applicable `ABSOLUTE_DENY` là mandatory inherited restriction; phải xuất hiện trong mọi KQ lane chịu scope trước intersection.
- Assignment tại `GROUP` có thể tạo DEPARTMENT/POSITION lane cho descendant; Assignment ở subtree Company khác không tham gia.
- Group-level administrative Resource có thể được evaluate trực tiếp tại `GROUP`; không bắt buộc phải có Company owner.
- Company A và Company B được evaluate độc lập đối với Company-scoped operation.
- Base Permission vẫn là prerequisite toàn cục; intersection không được tạo permission chưa có trong Effective Profile.

**Acceptance criteria**

- No Base Permission => DENY dù mọi lane đều không cấm.
- Trong một lane: Base + NO_MATCH => ALLOW.
- Trong một lane: Base + DENY => permission vào KQ lane.
- Trong một lane: Base + ALLOW + DENY => permission không vào KQ lane.
- Trong một lane: Base + ABSOLUTE_DENY + effect khác => permission vào mandatory denied set.
- Hai role cùng scope: role A `ALLOW`, role B `DENY` => permission không thuộc KQ Organization nếu không có `ABSOLUTE_DENY`.
- Hai role cùng scope đều `DENY` => permission thuộc KQ Organization.
- GROUP/ancestor inherited `ABSOLUTE_DENY` => permission thuộc KQ Organization và không bị role khác mở lại.
- GROUP Assignment Department/Position được dùng khi evaluate descendant context.
- Assignment thuộc Company A không tham gia khi evaluate Company B.
- `SYSTEM` Resource (`user`, `authorization_group`, `resource`, `workflow_state`, `lifecycle`) và `MASTER` Resource authorize tại Evaluation Organization `GROUP`; `policy` là `ORGANIZATION` Resource và authorize tại `policy.organization_id`.

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

System Admin không có bypass trong P1. System Admin là User bình thường trong cùng Authorization model.

**Canonical rule**

- Có Shield User và Employee mapping.
- Employee có Employee Assignment effective tại Organization `GROUP`; baseline tổ chức của System Admin thuộc Department IT; Position được cấu hình khi áp dụng.
- Có Base Permission từ Authorization Group và/hoặc approved direct permission addition như User khác.
- Chịu cùng PBAC Policy, QT1, denied-set intersection và `ABSOLUTE_DENY` như mọi User khác.
- Chịu Workflow/Lifecycle và business/data validation như mọi User khác.
- Không có hard-coded `isSystemAdmin => ALLOW`, special root flag, special bypass Policy row hoặc special permission semantics.
- Initial System Admin/privileged membership được provision bằng bootstrap/deployment mechanism ngoài runtime PBAC; sau bootstrap, quản trị membership/permission phải đi qua Resource/Action/PBAC bình thường.

**Acceptance criteria**

- System Admin thiếu Base Permission cho `Resource + Action` => DENY.
- System Admin match `ABSOLUTE_DENY` => DENY.
- GROUP Assignment của System Admin có thể cung cấp Department IT và Position participant khi Position được khai báo, cho descendant Organization theo inheritance.
- System Admin quản trị `user`, `authorization_group`, `resource`, `policy`, `organization` chỉ khi có Resource/Action permission và PBAC cho phép.

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

Quản lý Product Group với Code, Name, Description, Status và optional parent Product Group. Product Group là shared GROUP-level master dùng adjacency-list nhiều cấp; không có hard-coded depth, Company scope hay Product Company Usage Scope.

**Rules**

- `parent_id = NULL` là root; parent non-null phải tồn tại.
- Service phải reject self-parent, direct/indirect cycle và chỉ cho reparent khi hierarchy sau mutation hợp lệ.
- Code unique toàn business Group, không unique theo parent.
- Product được reference node ở mọi cấp. Effective selectability cho Product mới yêu cầu target và toàn bộ ancestor path ACTIVE.
- Status lưu theo node; INACTIVE không cascade descendants và không làm mất historical Product reference.
- Read-only tree traversal không tự yêu cầu lock; hierarchy mutation tuân thủ Implementation Contract lock `product-group-tree`.

**Acceptance criteria**

- Code unique.
- Inactive group hoặc node có ancestor INACTIVE không dùng cho Product mới.
- Root/child và hierarchy sâu hơn hai cấp đều được hỗ trợ.
- Self-parent, direct cycle và indirect/deep cycle bị reject; valid reparent được chấp nhận.
- Existing Product reference vẫn đọc được sau ancestor INACTIVE và không có status cascade.

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

Import Framework là capability dùng chung của P1 nhưng **không mặc định áp dụng cho mọi master**. Target phải được explicit enable theo User Requirements + Resource/Action catalog. Baseline P1 hiện enable import cho `PRODUCT`; Employee/Province/Ward không phải P1 import target nếu chưa có change control bổ sung.

## FRS-P1-IMP-001 - Import Job

**Priority:** MUST

P1 cung cấp CSV/XLSX import cho target đã enable:

- PRODUCT

Mỗi Import Job giữ:

- Entity Type.
- Mode (`CREATE_ONLY`/`UPDATE_ONLY`/`UPSERT`).
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
- Import Job là Resource Domain `ORGANIZATION` và inherit authorization scope từ enabled import target. P1 chỉ enable `PRODUCT`; Product là `MASTER`, vì vậy `import_job.view/export` và row visibility evaluate tại `GROUP`.
- User xem được trạng thái và summary import trong scope được phép.
- Nếu phase sau enable import target `ORGANIZATION`, target đó phải có additive contract để Job persistence/derivation giữ được authoritative context; không được fallback Primary Assignment.

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

- Unique rule, Product invariant, UOM rule và COMBO rule không được bypass qua Product import. Resource import khác nếu được enable sau phải dùng cùng validation với API/UI của Resource đó.
- Một row mutation nhiều bảng fail phải rollback row đó.

## FRS-P1-IMP-004 - Product dependency resolution

**Priority:** MUST

Product import không phụ thuộc thứ tự row nguồn.

Importer phải resolve dependency bằng multi-pass/dependency graph cho:

- Parent/Child.
- Product Group/UOM references.
- COMBO owner/component.
- Nested COMBO dependency.
- Source-local Product references (`source_key`) khi Product Code được Auto Number sinh sau.

**Rules**

- Child có thể xuất hiện trước Parent trong file.
- COMBO có thể xuất hiện trước component.
- Nested COMBO phải cycle-detect.
- Source row order vẫn giữ nguyên trong row result.
- Product row dùng `source_key` unique trong file; dependency tới Product create mới có thể dùng source key thay vì generated Product Code.

**Acceptance criteria**

- File có dependency hợp lệ nhưng thứ tự ngược vẫn import được.
- Graph cycle làm các row liên quan fail với error có thể giải thích; không commit composition cycle.

## FRS-P1-IMP-005 - Import authorization hai tầng

**Priority:** MUST

Import là action protected theo hai gate bắt buộc:

1. **Start gate:** User phải có `<target_resource>.import` tại Evaluation Organization canonical của import operation.
2. **Row mutation gate:** từng logical row vẫn phải pass canonical mutation permission tương ứng (`create`, `edit`, `setstate` khi áp dụng) tại authoritative Evaluation Organization của target row/resource.

**Acceptance criteria**

- User thiếu `product.import` không thể start Product import.
- Có `product.import` nhưng thiếu `product.create`/`product.edit` tương ứng thì row mutation bị DENY; import không trở thành đường bypass CRUD/PBAC.
- Product là Group Master nên Product import baseline evaluate tại `GROUP` cho Resource scope; row vẫn dùng canonical Product mutation action.
- Resource không được explicit enable action `import` không được expose import UI/API.

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
- Resolve effective Assignments theo exact `GROUP -> ... -> Evaluation Organization` scope path phục vụ PBAC.
- Resolve actor default Organization/Company context và Assignment scope path.

## FRS-P1-SVC-003 - AuthorizationService

Phải hỗ trợ:

- Resolve Base Permission.
- Resolve Evaluation Organization ở `GROUP`/`COMPANY`/`BRANCH`/`PLANT`/`WAREHOUSE`.
- Partition Company-scoped evaluation theo Company; hỗ trợ Group-level evaluation trực tiếp tại `GROUP`.
- Resolve ancestor chain từ `GROUP` root và Policy inheritance trước merge.
- Build actor-level lane (`USER`/`GROUP`).
- Build assignment lanes (`DEPARTMENT`/`POSITION`) từ effective eligible Assignments tại current Organization hoặc ancestor, bao gồm GROUP Assignment khi applicable.
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

## FRS-P1-SVC-010 - AutoNumberService

Phải hỗ trợ:

- Resolve active/default numbering series theo Resource.
- Resolve counter scope `GROUP`/`COMPANY`/`ORGANIZATION` từ authoritative Organization Context.
- Resolve period key theo reset policy và business timezone.
- Render pattern từ canonical token set và next sequence value.
- Serialize concurrent counter allocation.
- Generate number trong cùng transaction với business create để rollback counter cùng mutation.
- Enforce `AUTO_REQUIRED`/`AUTO_IF_EMPTY`.
- Validate series scope/reset/pattern theo business identifier uniqueness namespace của Resource adapter/domain.
- Khi generated candidate đã tồn tại (ví dụ do manual `AUTO_IF_EMPTY`), advance sequence và thử candidate kế tiếp trong cùng serialized transaction.
- Enforce immutability/no-hard-delete rule của series sau committed issuance.
- Với Resource `product`, dùng metadata canonical `identifier_field = products.code`, transport/import alias `product_code`, `identifier_max_length = 50`, `identifier_uniqueness_scope = GLOBAL`.
- Không renumber record cũ khi configuration thay đổi.

## FRS-P1-SVC-011 - ImportService

Phải hỗ trợ:

- Create Job.
- Parse file.
- Validate row.
- Build dependency graph khi Product import, bao gồm source-local Product references khi Auto Numbering áp dụng.
- Multi-pass processing.
- Row-level atomic mutation.
- Row result/report.
- Final Job reconciliation.

ImportService là capability P1 theo Database Design; nếu phase planning thay đổi delivery priority, các validation/persistence contract đã khóa không được bị bypass bởi đường import thay thế.

---

# 19. Error và Validation Semantics

FRS khóa semantic error class; wire-level HTTP status, JSON error envelope và canonical public error-code mapping được khóa tại `03_P1_Implementation_Contract_v1.8.md`. FRS không được định nghĩa một wire contract cạnh tranh.

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

FRS không tự định nghĩa HTTP status/JSON envelope/public error code; API implementation phải dùng `03_P1_Implementation_Contract_v1.8.md` và không được làm thay đổi semantic error class của FRS.

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
  -> validate unique source_key for Product rows
  -> normalize persistent product_code when supplied
  -> resolve Product Group/UOM refs
  -> build Parent/Child dependencies by source_key/product_code
  -> build COMBO dependencies by source_key/product_code
  -> detect cycles
  -> process valid dependency order/multi-pass
  -> generate Product Code through AutoNumberService for create rows when required
  -> preserve original row_number result
  -> rollback failed row and allocated number atomically
  -> reconcile job counts
```

## 20.11 Auto Number generation

```text
Resolve Resource + active/default Series
  -> resolve authoritative Organization Context
  -> resolve counter scope Organization
  -> derive period key from reset policy + business timezone
  -> lock/serialize counter
  -> next sequence
  -> render canonical pattern
  -> assign business identifier
  -> persist business record + counter in same transaction
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

Retention period của `authorization_logs`/`import_jobs` vẫn chưa được source baseline xác định đầy đủ. Visibility scope đã được khóa bởi `CHANGE-AUTH-DOMAIN-001`:

- `authorization_log.organization_id != NULL` -> authorize tại persisted evaluated Organization.
- `authorization_log.organization_id = NULL` -> authorize tại `GROUP`.
- P1 `import_job` target `PRODUCT` -> authorize tại `GROUP`.

FRS yêu cầu:

- Không tự hard-delete trong business flow chuẩn.
- Production retention phải được Security/Operations phê duyệt trước khi triển khai purge.

---

# 23. Traceability Matrix - User Requirement sang Functional Requirement

Bảng dưới đây bao phủ toàn bộ User Requirement có Target Phase P1 trong `00_User_Requirements_v1.0.14.md`.

| User Requirement | FRS Coverage |
|---|---|
| UR-CORE-001 Một Group, nhiều Company | FRS-P1-ORG-001..005 |
| UR-CORE-002 Transaction thuộc Company | FRS-P1-GEN-001, FRS-P1-ORG-003, FRS-P1-ORG-005 |
| UR-CORE-003 Master data dùng chung trong Group | FRS-P1-PRD-001..006, FRS-P1-UOM-001..005 |
| UR-CORE-004 Administrative Location | FRS-P1-LOC-001..002 |
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
| UR-PRD-007 Multi-level Product Group Hierarchy | FRS-P1-UOM-001 |
| UR-PRC-001 Core Pricing | FRS-P1-PRC-001..004, FRS-P1-CMBPRC-001..005, FRS-P1-GEN-007 |
| UR-COM-001 Lifecycle | FRS-P1-WF-001..004 |
| UR-COM-002 Approval | FRS-P1-WF-005 |
| UR-COM-003 Audit | FRS-P1-GEN-003, FRS-P1-AUD-001..003 |
| UR-COM-004 Search/Filter/Export | FRS-P1-GEN-006, FRS-P1-QRY-001..002 |
| UR-COM-005 Import | FRS-P1-IMP-001..005 |
| UR-COM-006 Auto Numbering | FRS-P1-NUM-001..003, FRS-P1-SVC-010 |

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
| Auto Numbering | auto_numbering_series, auto_numbering_counters |
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
5. **P2 transaction trace của COMBO:** P2 phải quyết định persistence để trace leaf line về root/nested COMBO; P1 chỉ khóa algorithm và product schema.
6. **Direct leaf line + COMBO-generated leaf aggregation:** Database baseline chỉ khóa weighted aggregation khi cùng leaf nhận allocation từ nhiều COMBO. Nếu P2 muốn gộp thêm direct standalone leaf line với COMBO-generated leaf line, P2 FRS phải định nghĩa rõ pricing/trace semantics trước khi persistence.

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
- GROUP/Company/ancestor Policy inheritance được resolve trước intersection.
- GROUP Policy kế thừa xuống descendant đúng scope.
- Branch Policy chỉ kế thừa xuống descendant, không sang sibling.
- Employee có Assignment tại GROUP cung cấp Department/Position lane cho descendant context.
- Employee có Assignment ở Company A và Company B được partition độc lập.
- Secondary Assignment cùng Company/scope tham gia DEPARTMENT/POSITION lane dù không phải Primary.
- Hai role cùng Organization: `ALLOW` + `DENY` => effective Organization ALLOW nếu không có `ABSOLUTE_DENY`.
- Hai role cùng Organization: `DENY` + `DENY` => effective Organization DENY.
- Company/ancestor `ABSOLUTE_DENY` applicable được propagate vào mọi lane con và không mất sau intersection.
- KQ Organization = intersection KQ user + các KQ assignment.
- KQ Company summary = intersection các KQ Organization khi chức năng yêu cầu Company summary.
- Permission denied ở Organization 1 nhưng allowed ở Organization 2 không thuộc KQ Company summary.
- GROUP participant mapping.
- System Admin không có bypass: thiếu Base Permission hoặc match ABSOLUTE_DENY đều DENY.
- `SYSTEM` và `MASTER` Resource authorize tại Evaluation Organization `GROUP`.
- Employee Master (`MASTER`) authorize tại `GROUP`; Organization/Department/Position filter không đổi scope này.
- `organization` existing/create resolve target/parent context; `employee_assignment` resolve `assignment.organization_id`.
- Contact owner EMPLOYEE resolve `GROUP`; Contact owner ORGANIZATION resolve Organization owner.
- Policy resolve `policy.organization_id`; Price List resolve `price_list.organization_id`.
- Authorization Log persisted Organization resolve đúng row context; NULL resolve `GROUP`.
- P1 Import Job inherit PRODUCT `MASTER` scope và authorize tại `GROUP`.
- Missing/unknown Resource Domain hoặc unresolved `ORGANIZATION` context fail closed.
- Primary Assignment không override/fallback domain-derived Resource scope.
- Quản trị security/configuration resource không có implicit allow.

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

## 26.10 Auto Numbering

- Resource không cấu hình series không bị ép Auto Number.
- Reject hai active default series cho cùng Resource.
- `AUTO_REQUIRED` reject manual identifier trên create.
- `AUTO_IF_EMPTY` giữ manual identifier hợp lệ mà không consume counter; blank thì generate.
- Generated candidate đã bị manual/existing identifier chiếm được skip bằng cách advance sequence trong cùng lock/transaction.
- Scope `GROUP`/`COMPANY`/`ORGANIZATION` resolve đúng counter và không bleed giữa scope.
- Uniqueness namespace `GLOBAL`/`COMPANY`/`ORGANIZATION` reject series pattern/scope/reset không đủ phân biệt các counter partition/period.
- Reset `NEVER`/`YEARLY`/`MONTHLY`/`DAILY` resolve period key đúng business timezone.
- GROUP date/reset series thiếu Group/default timezone bị reject.
- Concurrent generate không commit duplicate number; first-counter race vẫn chỉ tạo một counter identity.
- Business rollback rollback counter và cho phép reuse number chưa commit.
- Number đã commit không được reuse sau inactive/cancelled.
- Pattern/config change không renumber historical record.
- Series đã có committed issuance không đổi `resource/series_code/scope_type/reset_policy` và không hard-delete; `start_number` chỉ ảnh hưởng counter identity mới.
- `resource.configure_numbering` được authorize độc lập với `resource.edit`.
- Product Import source-key dependency vẫn resolve được trước khi generated Product Code tồn tại.

## 26.11 Import

- Row success/error counts reconcile.
- Failed row rollback, bao gồm generated Product Code/counter nếu row create thất bại.
- Child trước Parent vẫn import được khi dependency hợp lệ, kể cả reference bằng source key.
- COMBO trước component vẫn resolve được, kể cả source-key owner/component.
- Nested COMBO dependency resolve được.
- Combo cycle bị reject.
- Successful create row ghi được generated `product_code` vào result/entity key khi Auto Numbering áp dụng.
- Original row number giữ nguyên trong report.

---

## 26.12 Display Settings

- Registry chỉ nhận canonical keys đã khóa; unknown key reject.
- Server context chỉ `system`/`organization:<id>`; không có server `company`/`user` pseudo-scope.
- Exact Organization ancestor fallback đúng và sibling không leak.
- `display_setting.view/edit` dùng canonical AuthorizationService/PBAC.
- Batch set/reset atomic và reset trả về inheritance.
- Runtime snapshot chỉ chứa presentation data, không business authority.
- `Display.timezone` thay đổi render nhưng Business Timezone Company/effective-date test không đổi.
- Number/Money display settings không thay decimal-string API hoặc authoritative calculation.
- Frontend formatter giữ canonical form model; round/format chỉ ở display output.
- Locale vẫn `vi`/`en` local workspace preference và không trở thành server Display Setting.

## 26.13 Localization / i18n

- Default locale là `vi`.
- UI chuyển `vi`/`en` runtime.
- Menu/breadcrumb/button/field label/common validation không hard-code ngoài translation resource.
- API client gửi `Accept-Language` theo locale hiện tại.
- Backend negotiate `vi`/`en`; unsupported locale fallback `vi`.
- `error.code` ổn định giữa các locale; human-readable message localize.
- `Content-Language` phản ánh negotiated locale khi applicable.
- Business data/code/import header không bị dịch.
- Locale switch không ảnh hưởng timezone/authorization/business persistence.

---

# 27. Definition of Done cho FRS P1

FRS P1 được xem là đủ điều kiện chuyển sang design/implementation planning khi:

- Mọi capability P1 có requirement functional và acceptance criteria.
- Không có persistence mới trái Database Design v1.13.
- Authorization semantics nhất quán với Base Permission + QT1 per-lane + inheritance-before-intersection + multi-role denied-set intersection.
- Workflow transition và Authorization được tách rõ.
- Organization/Employee context resolution được mô tả đủ để implement/test.
- Product Model V2 không có Variant và role derivation nhất quán.
- COMBO semantics theo Database Design v1.10 baseline được mô tả đầy đủ: nested, acyclic, no serial, no UOM conversion, root-price-only, flatten, allocation, rounding và weighted aggregation.
- Core Pricing Company -> Group -> Default deterministic.
- Auto Numbering theo Resource có series/counter scope, uniqueness-namespace validation, occupied-candidate skipping, post-issuance series immutability/no-hard-delete, concurrency/rollback semantics, generation mode, reset policy, independent `resource.configure_numbering` authorization và Product Import source-local/persistent/generated identifier semantics đủ để test.
- Import Framework + Product enabled target, persisted mode, dependency graph/source-key resolution, row-level history và two-gate authorization đủ để test.
- Localization `vi`/`en` và API language negotiation có acceptance coverage; locale không bị trộn với business timezone/data translation.
- Display Settings có registry/scope/inheritance/Authorization/formatter acceptance coverage; presentation setting không được trở thành business authority.
- Resource Domain matrix `SYSTEM`/`MASTER`/`ORGANIZATION` được dùng nhất quán trong FRS/DBD/Contract/API; `COMPANY` không còn là Resource Domain canonical và missing/unresolved context fail closed.
- Open assumptions không bị biến thành schema hoặc behavior ngầm.
- Traceability bao phủ toàn bộ P1 User Requirements trong `00_User_Requirements_v1.0.14.md`.
- Không còn delta đã biết giữa User Requirements v1.0.14 và Database Design v1.13 đối với invariant P1; mọi xung đột mới phải đi qua change control thay vì được FRS tự suy diễn.
