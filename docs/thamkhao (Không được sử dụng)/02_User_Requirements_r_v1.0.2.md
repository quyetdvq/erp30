---
document_id: REQ-ERP-001
title: "User Requirements - Dinktech Mini ERP V2.0"
phase: "Requirements Definition"
status: "draft"
version: "1.0.2"
owner: "Chủ sở hữu sản phẩm"
authority: "SSOT cho User Requirements sau khi requirement được approved."
last_reviewed: "2026-10-02"
---

# User Requirements - Dinktech Mini ERP V2.0

## 1. Hợp đồng tài liệu 

Tài liệu này là baseline User Requirements đầu tiên của ERP Dinktech V2.0. Cấu trúc được tổ chức theo functional domain và business capability; business rule chi tiết được đặt dưới capability thay vì tách thành requirement độc lập khi không có giá trị phê duyệt/triển khai riêng.

- `BR-*`: Business Requirement / business capability.
- `NFR-*`: Non-functional Requirement / measurable quality constraint.
- `AR-*`: Architectural Rule / architecture invariant.
- `priority`: `MUST`, `SHOULD`, `COULD`.
- `depends_on`: chỉ chứa Hard Prerequisite; capability không thể đáp ứng requirement nếu prerequisite chưa tồn tại. Requirement không có Hard Prerequisite phải dùng `depends_on: []`.
- `governed_by`: Architectural Rule hoặc governance invariant bắt buộc phải tuân thủ; không đồng nghĩa lifecycle dependency.
- `references`: semantic/cross-capability reference hoặc controlled policy reference; không phải Hard Prerequisite.
- `applies_to`: conditional applicability; requirement/NFR chỉ tạo acceptance obligation cho capability/domain tương ứng khi capability đó nằm trong approved scope.
- Forward reference tới controlled item ở phase sau được phép qua `references`, `applies_to` hoặc explicit conditional wording; không được dùng `depends_on` để tạo forward Hard Prerequisite từ phase sớm sang phase muộn.
- Requirement ở phase sớm không được tạo runtime/acceptance obligation bắt buộc đối với capability chưa nằm trong approved scope. Khi future capability được approved, obligation tương ứng mới có hiệu lực theo `applies_to` hoặc conditional rule đã khai báo.
- `source_refs`: reference tới controlled external normative source đã được đăng ký theo Section 2.3.
- `target_phase` chưa được gán trong tài liệu này; sẽ được bổ sung ở Phase Planning.
- `implemented_by`, `realized_by` và `verified_by` chỉ chứa reference tới downstream artifact đã thực sự tồn tại và nằm dưới configuration control. Khi downstream artifact chưa tồn tại, actual traceability field phải để rỗng.
- ID downstream dự kiến, nếu cần giữ để phục vụ planning, được ghi bằng `planned_implemented_by`, `planned_realized_by` hoặc `planned_verified_by`; các reference này không được xem là implementation/verification evidence. Khi downstream artifact được tạo và controlled, reference tương ứng được chuyển từ `planned_*` sang actual traceability field.

### 1.1 Nguyên tắc consolidation

1. Một business outcome/capability có một requirement chính; lifecycle, validation, governance và exception của cùng capability được thể hiện thành business rule/acceptance criteria.
2. Requirement chỉ tách riêng khi có canonical owner khác, có thể được phê duyệt/triển khai độc lập, hoặc có quality/architecture semantics khác loại.
3. Consumer domain không tạo requirement cạnh tranh cho rule thuộc canonical owning domain; consumer chỉ tham chiếu capability owner.
4. BR, NFR và AR không gộp lẫn nhau.

## 2. Data & Domain Ownership Principles

### 2.1 Ownership levels

| Entity Level     | Ý nghĩa                                                                             |
| ---------------- | ----------------------------------------------------------------------------------- |
| System Master    | Dữ liệu dùng chung toàn hệ thống, không thuộc một Organization cụ thể.              |
| **Group Master** | **Dữ liệu được quản trị ở cấp Group và dùng chung cho tất cả Company trong Group.** |
| Company Master   | Dữ liệu chỉ có hiệu lực hoặc được quản trị riêng trong một Company.                 |
| Transaction Data | Thuộc đúng một Company và chịu phân quyền theo Organization Context.                |


### 2.2 Canonical ownership matrix


| Business Entity / Data | Canonical Owning Domain | Ownership Scope | Primary Governing Requirement |
|---|---|---|---|
| Product Master | Product | Group Master | `BR-PRD-001` |
| Product Default Selling Price | Product | Group Master | `BR-PRD-005` |
| Product Moving Average Cost | Product | Group Master | `BR-PRD-001` |
| Customer | Customer | Group Master | `BR-CUS-001` |
| Supplier | Supplier | Group Master | `BR-SUP-001` |
| Employee | Employee | Group Master | `BR-EMP-001` |
| Employee Assignment | Employee | Company Master | `BR-EMP-001` |
| Department | Employee | Company Master | `BR-EMP-002` |
| Position | Employee | Group Master | `BR-EMP-002` |
| Warehouse | Organization | Company Master | `BR-ORG-002` |
| Group Pricing Policy / Price List | Pricing | Group Master | `BR-PRC-001` |
| Company Pricing Policy / Price List | Pricing | Company Master | `BR-PRC-001` |
| Business Transaction | Domain phát sinh | Transaction Data | `AR-TXN-001` |

### 2.3 Controlled Source References

Mọi legal, regulatory, official-standard hoặc external normative source được sử dụng để quyết định requirement/rule phải được đăng ký bằng một controlled `source_id`. Một source reference tối thiểu phải xác định được title, issuing authority, source type, version/issue date, effective time, controlled locator, status, owner và thời điểm xác minh gần nhất.

```yaml
source_id: "SRC-..."
title: ""
issuing_authority: ""
source_type: "LEGAL | REGULATORY | OFFICIAL_STANDARD | EXTERNAL_POLICY"
version_or_issue_date: null
effective_from: null
effective_to: null
controlled_locator: ""
status: "DRAFT | VERIFIED | SUPERSEDED"
owner: ""
last_verified: null
```

Một requirement/rule chứa normative statement dựa trên external legal, regulatory hoặc official standard không được approved nếu `source_refs` tương ứng chưa resolve tới controlled source có `status=VERIFIED`.

## 3. Controlled Architectural Rules

```yaml
id: "AR-DATA-OWNERSHIP-001"
title: "Reference/Common Data Ownership Rule"
type: architecture-rule
priority: null
target_phase: "P1_CORE"
description: ""
constraints:
  - "Reference/common data có ý nghĩa chuẩn độc lập với một doanh nghiệp cụ thể và cần dùng thống nhất toàn hệ thống được quản lý ở System Master."
  - "Taxonomy, classification, coding set hoặc policy/reference data do doanh nghiệp định nghĩa và chia sẻ giữa các Company trong cùng Group được quản lý ở Group Master."
  - "Dữ liệu chỉ có ý nghĩa hoặc hiệu lực trong một Company được quản lý ở Company Master khi requirement của owning domain xác định rõ nhu cầu Company-specific."
  - "Mỗi common primitive/reference data phải được phân loại ownership trước khi được công bố sử dụng; ngoại lệ phải được owning domain ghi rõ cùng lý do nghiệp vụ."
depends_on: []
planned_realized_by:
  - "DES-AR-DATA-OWNERSHIP-001"
realized_by: []
planned_verified_by:
  - "DES-REVIEW-AR-DATA-OWNERSHIP-001"
verified_by: []
```
```yaml
id: "AR-DOM-001"
title: "Domain Boundary and Canonical Ownership Rule"
type: architecture-rule
priority: null
target_phase: "P1_CORE"
description: ""
constraints:
  - "Mỗi governed business concept hoặc business entity xác định đúng một canonical owning domain ở cấp nghiệp vụ."
  - "Domain khác có thể tham chiếu hoặc tiêu thụ dữ liệu nhưng không tạo master/SSOT cạnh tranh cho cùng ý nghĩa."
  - "Cross-domain dependency và responsibility boundary phải được mô tả rõ trong requirement/design liên quan."
  - "Thay đổi trong một domain không được âm thầm thay đổi ownership, semantic hoặc invariant của domain khác."
depends_on: []
planned_realized_by:
  - "DES-AR-DOM-001"
realized_by: []
planned_verified_by:
  - "DES-REVIEW-AR-DOM-001"
verified_by: []
```
```yaml
id: "AR-TXN-001"
title: "Business Transaction Context Invariant"
type: architecture-rule
priority: null
target_phase: "P1_CORE"
description: ""
constraints:
  - "Mỗi business transaction thuộc đúng một owning Company; Group chỉ được suy ra qua Company và không sở hữu transaction trực tiếp."
  - "Cross-company process có thể phối hợp nhiều transaction thuộc các Company khác nhau nhưng từng transaction thành phần vẫn thuộc đúng một owning Company."
  - "`intercompany_reference` chỉ là correlation/reference key giữa các transaction hai phía; không phải transaction owner."
  - "Mỗi business transaction xác định được business/effective timestamp, lifecycle state và responsible actor/User hoặc system context phù hợp."
depends_on: []
planned_realized_by:
  - "DES-AR-TXN-001"
realized_by: []
planned_verified_by:
  - "DES-REVIEW-AR-TXN-001"
verified_by: []
```
## 4. Business Requirements

### 4.1 Organization & Enterprise Structure

```yaml
id: "BR-ORG-001"
title: "Quản lý nhiều Group và Company"
type: business
priority: MUST
target_phase: "P1_CORE"

description: >
  Hệ thống hỗ trợ quản lý nhiều Group độc lập trên cùng một nền tảng.
  Mỗi Group có cơ cấu tổ chức và phạm vi quản trị riêng.

  Mỗi Group được đại diện bởi một Group Business Entity duy nhất làm
  business identity chuẩn của Group.

  Mỗi Group Business Entity có đúng một Organization Context `type=GROUP`
  tương ứng theo quan hệ 1:1 để biểu diễn Group trong cấu trúc tổ chức.
  Organization Context này không phải một Group master riêng.

success_criteria:
  - "Có thể quản lý nhiều Group độc lập trên cùng một nền tảng."
  - "Mỗi Group có đúng một Group Business Entity làm business identity chuẩn."
  - "Mỗi Group Business Entity có đúng một Organization Context `type=GROUP` tương ứng theo quan hệ 1:1."
  - "Không được tạo Organization Context `type=GROUP` nếu không liên kết với một Group Business Entity."
  - "Không được tạo nhiều Group Business Entity cho cùng một Organization Context `type=GROUP`."

depends_on: []

governed_by:
  - "AR-DOM-001"

planned_implemented_by:
  - "FR-ORG-001"
implemented_by: []

planned_verified_by:
  - "UAT-ORG-001"
verified_by: []
```

```yaml
id: "BR-ORG-002"
title: "Quản lý Organization Context và hierarchy vận hành"
type: business
priority: MUST
target_phase: "P1_CORE"

description: >
  Hệ thống quản lý cấu trúc tổ chức thông qua Organization Context với
  tập type cố định gồm GROUP, COMPANY, BRANCH, PLANT và WAREHOUSE.

  Branch, Plant và Warehouse được tổ chức trong hierarchy của Company
  tương ứng. Hierarchy không bắt buộc phải có đầy đủ mọi cấp trung gian.

  Company, Branch, Plant và Warehouse có lifecycle/state và thời gian
  hiệu lực.

  Quan hệ parent-child giữa các Organization Context phải tuân theo
  controlled artifact `ORG-CONTEXT-HIERARCHY-POLICY`.

success_criteria:
  - "Organization Context chỉ sử dụng các type GROUP, COMPANY, BRANCH, PLANT và WAREHOUSE."
  - "Business configuration thông thường không được tạo thêm Organization Context Type ngoài tập type đã quy định."
  - "Organization Context `type=GROUP` tham chiếu đúng một Group Business Entity theo quan hệ 1:1."
  - "Mỗi Branch, Plant và Warehouse xác định được Company sở hữu."
  - "Mỗi Branch, Plant và Warehouse có vị trí hợp lệ trong Organization Context hierarchy của Company."
  - "Hierarchy có thể bỏ qua cấp trung gian khi `ORG-CONTEXT-HIERARCHY-POLICY` cho phép."
  - "Company, Branch, Plant và Warehouse xác định được trạng thái và thời gian hiệu lực."
  - "Organization Context hết hiệu lực không được sử dụng cho phát sinh mới nhưng lịch sử tổ chức vẫn được bảo toàn."
  - "Quan hệ parent-child phải được kiểm tra theo `ORG-CONTEXT-HIERARCHY-POLICY` đang có hiệu lực."
  - "Parent-child combination không được policy cho phép phải bị từ chối, trừ controlled exception được policy hiện hành cho phép."

depends_on:
  - "BR-ORG-001"

governed_by:
  - "AR-DOM-001"

references:
  - "ORG-CONTEXT-HIERARCHY-POLICY"

planned_implemented_by:
  - "FR-ORG-002"
implemented_by: []

planned_verified_by:
  - "UAT-ORG-002"
verified_by: []
```

```yaml
id: "BR-ORG-004"
title: "Quản lý business timezone, currency và Company Business Calendar"
type: business
priority: MUST
target_phase: "P1_CORE"

description: >
  Hệ thống quản lý business timezone, business/base/functional currency
  và Company Business Calendar theo từng Company.

  Company Business Calendar là nguồn chuẩn để xác định business day,
  non-business day, Company holiday và các quy tắc nghiệp vụ sử dụng
  khái niệm ngày làm việc của Company.

  Locale và display formatting thuộc capability cấu hình hệ thống riêng
  và không làm thay đổi các giá trị nghiệp vụ của Company.

success_criteria:
  - "Mỗi Company xác định được business timezone đang có hiệu lực."
  - "Mỗi Company xác định được business/base/functional currency đang có hiệu lực."
  - "Mỗi Company xác định được Company Business Calendar có thời gian hiệu lực."
  - "Company Business Calendar phân loại được business day, non-business day và Company holiday."
  - "Các quy tắc nghiệp vụ sử dụng ngày làm việc của Company có thể sử dụng cùng một Company Business Calendar chuẩn."
  - "Thay đổi locale hoặc display formatting không làm thay đổi business timezone hoặc business currency của Company."

depends_on:
  - "BR-ORG-002"

references:
  - "BR-SYS-001"

planned_implemented_by:
  - "FR-ORG-004"
implemented_by: []

planned_verified_by:
  - "UAT-ORG-004"
verified_by: []
```

```yaml
id: "BR-ORG-003"
title: "Tổng hợp và xem dữ liệu theo phạm vi tổ chức"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Xem số liệu theo một công ty, một chi nhánh, nhóm chi nhánh hoặc toàn doanh nghiệp theo quyền được cấp. Hỗ trợ tổng hợp báo cáo quản trị theo Group từ dữ liệu sở hữu bởi các Company thuộc Group đó. Đây không phải hợp nhất kế toán (Consolidation Accounting)."
success_criteria:
  - "Tổng theo các phạm vi hợp lệ khớp với tổng chi tiết."
  - "Báo cáo quản trị Group tổng hợp đúng dữ liệu Company thuộc Group và được phân biệt rõ với báo cáo hợp nhất kế toán."
depends_on:
  - "BR-ORG-001"
  - "BR-ORG-002"
  - "BR-IAM-003"
planned_implemented_by:
  - "FR-ORG-003"
implemented_by: []
planned_verified_by:
  - "UAT-ORG-003"
verified_by: []
```

```yaml
id: "BR-ORG-005"
title: "Quản lý policy và trách nhiệm dữ liệu theo phạm vi tổ chức"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý các chính sách dùng chung và chính sách riêng theo công ty hoặc chi nhánh. Chỉ định chủ sở hữu dữ liệu và người thay thế cho từng miền dữ liệu quan trọng."
success_criteria:
  - "Người có thẩm quyền phân biệt được giá trị dùng chung với giá trị địa phương và ngày hiệu lực."
  - "Không có đối tượng quan trọng nào thiếu chủ sở hữu hoặc ngày hiệu lực trách nhiệm."
depends_on:
  - "BR-ORG-002"
planned_implemented_by:
  - "FR-ORG-005"
implemented_by: []
planned_verified_by:
  - "UAT-ORG-005"
verified_by: []
```
```yaml
id: "BR-ORG-006"
title: "Quản lý Project hoặc đối tượng tập hợp chi phí/doanh thu"
type: business
priority: SHOULD
target_phase: "P2_EXTENDED"
description: "Hỗ trợ dự án hoặc đối tượng tập hợp chi phí/doanh thu có chủ sở hữu, thời hạn và trạng thái riêng."
success_criteria:
  - "Giao dịch mới chỉ được gán cho đối tượng đang hiệu lực và thuộc đúng công ty."
depends_on:
  - "BR-ORG-002"
planned_implemented_by:
  - "FR-ORG-006"
implemented_by: []
planned_verified_by:
  - "UAT-ORG-006"
verified_by: []
```
### 4.2 Employee & Organizational Structure

```yaml
id: "BR-EMP-001"
title: "Quản lý Employee và Employee Assignment"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý Employee độc lập với User Identity. Employee domain là owning domain của Employee và Employee Assignment. Employee là Group Master canonical trong Group; Employee Assignment là Company Master và biểu diễn quan hệ làm việc của Employee trong một Company bằng cách tham chiếu Organization Context, Department, Position và thời gian hiệu lực. HRP và IAM chỉ mở rộng hoặc tham chiếu các đối tượng này, không tạo Employee/Employee Assignment master cạnh tranh."
success_criteria:
  - "Mỗi Employee xác định được Group sở hữu và có thể được tham chiếu bởi các Company trong Group theo quyền được cấp."
  - "Mỗi Employee Assignment xác định được đúng một Company sở hữu, Organization Context, Department, Position và thời gian hiệu lực."
  - "Một Employee có thể có nhiều Assignment theo Company và theo thời gian mà vẫn dùng cùng Employee canonical."
  - "Lịch sử thay đổi Organization Context, Department, Position và thời gian hiệu lực của Assignment được bảo toàn."
  - "Có thể xác định Employee/Employee Assignment canonical mà HRP và IAM đang tham chiếu."
depends_on:
  - "BR-ORG-002"
  - "BR-EMP-002"
planned_implemented_by:
  - "FR-EMP-001"
implemented_by: []
planned_verified_by:
  - "UAT-EMP-001"
verified_by: []
```
```yaml
id: "BR-EMP-002"
title: "Quản lý Department, Position và organizational view"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Department và Position là master thuộc Employee domain. Department là Company Master, có hierarchy, lifecycle/state và thời gian hiệu lực riêng trong Company. Position là Group Master thể hiện chức danh và level dùng chung trong Group. Department/Position được Employee Assignment tham chiếu theo thời gian hiệu lực và không tự sinh Permission hoặc Organization Scope."
success_criteria:
  - "Mỗi Department xác định được Company sở hữu, parent trong hierarchy khi áp dụng, lifecycle/state và thời gian hiệu lực."
  - "Department ngừng hiệu lực không được dùng cho Employee Assignment mới nhưng lịch sử Assignment cũ vẫn được bảo toàn."
  - "Mỗi Position xác định được Group sở hữu, chức danh/level, trạng thái và thời gian hiệu lực."
  - "Employee Assignment chỉ tham chiếu Department thuộc đúng Company của Assignment và Position thuộc Group tương ứng đang có hiệu lực."
  - "Department hoặc Position không tự tạo Permission hay Organization Scope."
depends_on:
  - "BR-ORG-002"
planned_implemented_by:
  - "FR-EMP-002"
implemented_by: []
planned_verified_by:
  - "UAT-EMP-002"
verified_by: []
```
### 4.3 Authorization & Security

```yaml
id: "BR-IAM-001"
title: "Quản lý User Identity và Group Access Membership"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý User Identity như authentication principal ở platform level, độc lập với Employee/Employee Assignment master. User Identity xác định loại identity, trạng thái xác thực/truy cập và lifecycle ở cấp nền tảng. IAM không sở hữu hoặc tạo Employee/Employee Assignment master thay thế. Quản lý explicit Group Access Membership để một User Identity được truy cập từng Group. Ordinary ERP User membership phải reference Employee canonical thuộc chính Group đó; membership không trỏ cứng vào một Employee Assignment. Khi authorization cần Company/Organization Context, active Employee Assignment(s) của Employee được resolve theo Company/context và thời gian hiệu lực tại thời điểm đánh giá. System Admin/service identity là ngoại lệ có kiểm soát: nếu không reference Employee thì loại identity, scope và căn cứ ngoại lệ phải explicit và audit được. Dữ liệu giữa các Group phải được cách ly. User Identity chỉ được truy cập Group theo explicit Group Access Membership hoặc controlled exceptional identity scope; membership/quyền của Group này không được suy ra thành quyền ở Group khác."
success_criteria:
  - "Mỗi User Identity có định danh duy nhất, loại identity, trạng thái và lifecycle có hiệu lực ở platform level."
  - "User bị khóa hoặc ngừng hiệu lực không thể thực hiện giao dịch mới."
  - "User Identity không trỏ cứng vào một Employee Assignment và không biến platform identity thành Company membership."
  - "IAM không tạo Employee, Department, Position hoặc Employee Assignment master cạnh tranh."
  - "Mỗi ordinary ERP User truy cập Group có explicit membership tới đúng Group và Employee canonical thuộc Group đó."
  - "Membership Group A không tự tạo quyền ở Group B; cross-Group access yêu cầu membership/scope explicit cho từng Group liên quan."
  - "Khi authorization theo Company/context, Employee Assignment được resolve từ các Assignment đang hiệu lực của Employee; membership không trở thành Company membership do tham chiếu cứng một Assignment."
  - "Permission/Policy được evaluate trong Group và Company/Organization Context đã resolve tương ứng."
  - "System Admin/service identity ngoại lệ không có Employee chỉ được phép khi identity type, scope, owner và audit evidence được kiểm soát."
  - "Kiểm tra chéo không truy cập được dữ liệu ngoài Group khi không có membership/scope explicit tương ứng."
  - "Membership Group A không tạo quyền hoặc data scope trong Group B."
depends_on:
  - "BR-ORG-001"
  - "BR-EMP-001"
planned_implemented_by:
  - "FR-IAM-001"
implemented_by: []
planned_verified_by:
  - "UAT-IAM-001"
verified_by: []
```
```yaml
id: "BR-IAM-002"
title: "Quản lý Permission, Profile và Business Group"
type: business
priority: MUST
target_phase: "P1_CORE"

description: >
  Hệ thống quản lý Permission theo cặp Resource/Action. Mỗi Permission xác định
  người dùng được phép thực hiện hành động nào trên loại Resource nào và là
  đơn vị quyền cơ sở của hệ thống Authorization.

  Profile là tập hợp các Permission. Người dùng nhận quyền thông qua Profile;
  Policy Rule không được tạo thêm Permission ngoài các Permission đã được định
  nghĩa và cấp qua Profile.

  Mỗi Resource/Action Permission có thuộc tính canonical `policy_required`.
  Thuộc tính này quyết định cách xử lý khi không có Policy Rule nào match trong
  Authorization Evaluation. `policy_required` thuộc Permission và có cùng giá
  trị bất kể Permission đó được cấp qua Profile nào.

  Business Group được dùng để hỗ trợ quản trị phân quyền bằng cách ánh xạ
  người dùng hoặc nhóm nghiệp vụ tới Profile phù hợp. Business Group là khái
  niệm của Authorization và hoàn toàn khác với Group Business Entity trong
  Organization Model.

  Business Group không thay thế Profile, Permission hoặc Policy Rule và không
  tự tạo thêm quyền cho người dùng.

success_criteria:
  - "Mỗi Permission xác định được Resource, Action, mã định danh và giá trị `policy_required`."
  - "Mỗi Profile xác định được tập Permission đang được cấp qua Profile đó."
  - "Có thể truy được quyền hiệu lực của người dùng về Profile và Permission đã cấp."
  - "Cùng một Resource/Action Permission có cùng giá trị `policy_required` bất kể Permission đó được sử dụng trong Profile nào."
  - "Policy Rule không được tạo Permission mới hoặc cấp thêm quyền ngoài Permission đã được cấp qua Profile."
  - "Business Group có thể được ánh xạ tới Profile để hỗ trợ quản trị phân quyền."
  - "Có thể xác định Business Group, Profile, Permission và Policy Rule đang tham gia vào việc xác định quyền của người dùng."
  - "Business Group trong Authorization không được hiểu là Group Business Entity trong Organization Model."
  - "Thay đổi mapping giữa Business Group và Profile phải giữ được lịch sử."
  - "Thay đổi Business Group hoặc mapping Business Group - Profile không tự tạo Permission mới."

depends_on: []

planned_implemented_by:
  - "FR-IAM-002"
implemented_by: []

planned_verified_by:
  - "UAT-IAM-002"
verified_by: []
```
```yaml
id: "BR-IAM-003"
title: "Đánh giá Authorization theo Policy Based Access Control"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Phân quyền theo mô hình Profile Permission kết hợp Policy Rule trong Group/Company/Organization Context đã resolve qua BR-IAM-001. Permission được định nghĩa theo Resource/Action và được cấp thông qua Profile; Policy Rule chỉ giới hạn quyền hiệu lực theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect. Authorization Evaluation không cấp thêm quyền ngoài Profile; Authorization Evaluation phải deterministic theo các rule của capability này. Quản lý Policy Rule để giới hạn Permission theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect. Department không trở thành Organization Context và không tự tạo Permission/Scope; khi Department condition được dùng, Actor Department phải được resolve từ Employee Assignment có hiệu lực còn Resource Responsible Department phải lấy từ ownership/responsibility của resource. Condition luôn được đánh giá trong đúng Company context. Quản lý Policy theo version, thời gian hiệu lực và lịch sử thay đổi để quyết định Authorization có thể được giải thích theo chính sách áp dụng tại thời điểm đánh giá. Áp dụng một Authorization Evaluation thống nhất: Profile Permission là gate quyền cơ sở; applicable Policy Rules chỉ được evaluate sau khi Permission tồn tại; Effect aggregate theo `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; NO_MATCH được quyết định bởi canonical `policy_required` của Resource/Action Permission. Policy không bao giờ tạo Permission mới. Quản lý quyền truy cập theo từng loại hành động trên cùng một đối tượng nghiệp vụ, bao gồm xem, tạo hoặc gửi yêu cầu, phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu và quản trị. Quyền đối với từng hành động phải được xác định độc lập; việc được cấp một quyền không mặc nhiên tạo ra quyền thực hiện hành động khác trên cùng đối tượng."
success_criteria:
  - "Người dùng chỉ thấy và tác động đến dữ liệu được ủy quyền trong Group/Company/Organization Context đã resolve."
  - "Quyết định Authorization truy được Profile Permission, canonical Permission metadata, applicable Policy Rules và membership/context đã dùng."
  - "Quyết định Authorization có thể giải thích được theo Policy áp dụng."
  - "Department condition không tạo Organization Scope/Permission riêng và Department được evaluate trong đúng Company."
  - "Actor Department được resolve từ Employee Assignment đang hiệu lực; Resource Department được lấy từ owning/responsible Department của resource thay vì suy diễn từ User."
  - "Có thể xác định version Policy có hiệu lực tại một thời điểm và truy lại các version trước."
  - "Thay đổi Policy không ghi đè làm mất nội dung đã từng có hiệu lực."
  - "Không có Profile Permission thì kết quả luôn DENY, kể cả khi có ALLOW Policy."
  - "Có Profile Permission và có `ABSOLUTE_DENY` applicable thì kết quả DENY."
  - "Có Profile Permission và có `ALLOW` applicable, không có `ABSOLUTE_DENY`, thì ALLOW thắng DENY thông thường."
  - "Có Profile Permission và chỉ có `DENY` applicable thì kết quả DENY."
  - "Có Profile Permission nhưng NO_MATCH: `policy_required=true` thì DENY; `policy_required=false` thì ALLOW."
  - "Permission set của nhiều Profile là union của các Profile đang có hiệu lực; Profile bổ sung Permission nhưng không bypass Policy."
  - "Quyết định cho phép/từ chối có thể giải thích được từ Membership/Context, Profile Permission, `policy_required`, applicable Policy version/rule và Effect đã áp dụng."
  - "Đối với cùng một đối tượng nghiệp vụ, có thể cấu hình và xác định độc lập quyền xem, tạo/gửi yêu cầu, phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu và quản trị."
  - "Người dùng chỉ thực hiện được hành động khi Permission tương ứng được cấp và các Policy Rule áp dụng cho context hiện tại cho phép."
  - "Việc có quyền xem hoặc tạo yêu cầu không mặc nhiên cấp quyền phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu hoặc quản trị."
  - "Có thể truy được Permission và Policy Rule đã được sử dụng để quyết định cho phép hoặc từ chối một hành động."
depends_on:
  - "BR-IAM-001"
  - "BR-IAM-002"
  - "BR-ORG-002"
planned_implemented_by:
  - "FR-IAM-003"
implemented_by: []
planned_verified_by:
  - "UAT-IAM-003"
verified_by: []
```
```yaml
id: "BR-IAM-004"
title: "Quản lý tách biệt trách nhiệm, ủy quyền và rà soát quyền"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Bảo đảm người tạo, người phê duyệt, người thực hiện và người đối soát không mặc định là cùng một người trong các nghiệp vụ có rủi ro. Quản lý người thay thế, ủy quyền có thời hạn và quyền khẩn cấp có hậu kiểm. Rà soát quyền định kỳ và khi nhân viên chuyển vị trí, công ty hoặc chi nhánh."
success_criteria:
  - "Giao dịch vi phạm tách biệt trách nhiệm bị nhận diện và yêu cầu ngoại lệ có thẩm quyền."
  - "Mọi quyền thay thế có người cấp, lý do, thời hạn và lịch sử sử dụng."
  - "Có bằng chứng rà soát, người xác nhận và danh sách quyền bị thu hồi hoặc thay đổi."
depends_on:
  - "BR-IAM-003"
planned_implemented_by:
  - "FR-IAM-004"
implemented_by: []
planned_verified_by:
  - "UAT-IAM-004"
verified_by: []
```
```yaml
id: "BR-IAM-005"
title: "Bảo vệ dữ liệu nhạy cảm theo mục đích và phạm vi công việc"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Bảo vệ dữ liệu nhạy cảm như lương, thông tin định danh, giá vốn, chiết khấu và tài khoản ngân hàng theo phạm vi công việc. Truy cập hoặc xuất dữ liệu nhạy cảm chỉ được thực hiện khi User có Resource/Action Permission tương ứng và final Authorization Evaluation theo BR-IAM-003 trả về ALLOW trong đúng Group/Company/Organization Context. Policy Rule không tự tạo quyền và không được dùng thay cho Resource/Action Permission."
success_criteria:
  - "User không có Resource/Action Permission tương ứng hoặc có final Authorization Evaluation khác ALLOW không xem, sửa hoặc xuất được dữ liệu nhạy cảm cho hành động đó."
  - "User chỉ xem được dữ liệu nhạy cảm khi Permission của hành động xem tồn tại và final Authorization Evaluation = ALLOW trong context hiện tại."
  - "Quyền export dữ liệu nhạy cảm được đánh giá độc lập với quyền xem; quyền xem không mặc nhiên cấp quyền export."
depends_on:
  - "BR-IAM-003"
planned_implemented_by:
  - "FR-IAM-005"
implemented_by: []
planned_verified_by:
  - "UAT-IAM-005"
verified_by: []
```
```yaml
id: "BR-IAM-006"
title: "Ghi nhận và giám sát sự kiện IAM/Security"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Ghi nhận các sự kiện IAM gồm đăng nhập, đăng xuất, xác thực thất bại, truy cập bị từ chối và hành động quản trị User/Profile/Permission/Policy. Lịch sử thay đổi dữ liệu nghiệp vụ và business audit trail không thuộc ownership của IAM mà được quản lý bởi Central Audit Trail. Rule-based security monitoring để phát hiện security event thuộc tập detection rule được phê duyệt, tạo alert, lưu evidence và quản lý trạng thái xử lý. Capability này không yêu cầu hệ thống tự phát hiện mọi anomaly bằng AI; quantitative latency/coverage target nếu được phê duyệt phải được quản lý bằng một NFR ID mới."
success_criteria:
  - "Có thể tra cứu người/User, thời điểm, phạm vi, loại sự kiện và kết quả của hành động IAM."
  - "Thay đổi Profile, Permission hoặc Policy xác định được người thực hiện và nội dung quản trị liên quan."
  - "IAM audit không được dùng làm master thay thế cho lịch sử thay đổi dữ liệu nghiệp vụ."
  - "Detection rule thuộc acceptance scope sinh alert khi điều kiện test tương ứng xảy ra."
  - "Baseline event class có thể bao gồm repeated authentication failure, denied cross-scope/cross-Group access, administrative change đối với Profile/Permission/Policy, attempted sensitive-data access/export bị từ chối và security-control bypass attempt khi có rule xác định."
  - "Mỗi alert xác định được detection rule, actor/User, timestamp, resource/scope, severity, evidence, owner và handling status."
  - "Detection rule có owner, lifecycle và effective time; alert không tự cấp hoặc quản lý Permission."
depends_on:
  - "BR-IAM-001"
  - "BR-IAM-003"
  - "BR-CTL-003"
planned_implemented_by:
  - "FR-IAM-006"
implemented_by: []
planned_verified_by:
  - "UAT-IAM-006"
verified_by: []
```
### 4.4 Cross-domain Controls & Workflow

```yaml
id: "BR-CTL-001"
title: "Chuẩn hóa lifecycle, numbering và quan hệ giữa chứng từ"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý trạng thái chung cho chứng từ và phân biệt rõ Nháp, Chờ xem xét, Đã duyệt, Đã ghi nhận chính thức, Hoàn tất, Hủy và Đảo/Điều chỉnh. Các module có thể mở rộng trạng thái nghiệp vụ riêng nhưng không được làm sai ý nghĩa trạng thái chung. Quản lý số chứng từ theo loại, công ty, chi nhánh, kỳ và chính sách đánh số được phê duyệt. Thể hiện quan hệ giữa chứng từ nguồn, chứng từ phát sinh, chứng từ thay thế, chứng từ điều chỉnh và chứng từ đảo."
success_criteria:
  - "Ý nghĩa trạng thái chung được thống nhất giữa các miền và có thể ánh xạ trạng thái module về trạng thái chung khi cần báo cáo/kiểm soát."
  - "Số đã cấp chính thức không bị tái sử dụng."
  - "Người kiểm tra biết chứng từ nào tạo ra hoặc thay đổi số liệu hiện tại."
depends_on:
  - "BR-ORG-002"
  - "BR-ORG-004"
planned_implemented_by:
  - "FR-CTL-001"
implemented_by: []
planned_verified_by:
  - "UAT-CTL-001"
verified_by: []
```
```yaml
id: "BR-CTL-002"
title: "Bảo đảm trách nhiệm, truy xuất và đối chiếu xuyên chứng từ"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Truy xuất người tạo, người phê duyệt, người ghi nhận, người điều chỉnh, thời điểm, lý do và tài liệu liên quan. Bảo đảm tổng số lượng, tổng tiền, tổng công nợ và tổng tồn giữa các chứng từ liên quan có thể đối chiếu khi các source capability tương ứng nằm trong approved scope. Ghi nhận các sự kiện nghiệp vụ quan trọng của capability đang nằm trong approved scope, ví dụ bản ghi chuẩn được công bố, đơn được xác nhận, nhận hàng và xuất kho trong P1; các event class như production completion, invoice, payment, return hoặc payroll lock chỉ trở thành acceptance obligation khi source capability tương ứng được approved."
success_criteria:
  - "Mẫu giao dịch quan trọng thuộc capability đang trong approved scope có hồ sơ trách nhiệm đầy đủ."
  - "Đối với reconciliation class có source capability đang trong approved scope, chênh lệch có tổng kiểm soát, chủ sở hữu và trạng thái xử lý."
  - "Mỗi event class thuộc acceptance scope xác định được thời điểm, đối tượng, chủ sở hữu và kết quả nghiệp vụ."
  - "Source capability chưa nằm trong approved scope không buộc phải tạo event hoặc dữ liệu giả chỉ để thỏa BR-CTL-002."
depends_on:
  - "BR-CTL-001"
  - "BR-IAM-003"
planned_implemented_by:
  - "FR-CTL-002"
implemented_by: []
planned_verified_by:
  - "UAT-CTL-002"
verified_by: []
```
```yaml
id: "BR-CTL-003"
title: "Quản lý ngoại lệ xuyên miền"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý ngoại lệ xuyên miền với mức độ, tác động, người xử lý, thời hạn và quyết định cuối. Việc ghi nhận business event tổng quát tại BR-CTL-002 có thể bổ sung context nhưng không phải hard prerequisite để quản lý ngoại lệ."
success_criteria:
  - "Ngoại lệ quá hạn được nhắc và chuyển cấp theo chính sách."
depends_on:
  - "BR-CTL-001"
planned_implemented_by:
  - "FR-CTL-003"
implemented_by: []
planned_verified_by:
  - "UAT-CTL-003"
verified_by: []
```
```yaml
id: "BR-CTL-004"
title: "Quản lý business approval và Shared Workflow"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Một khung business approval dùng chung có thể áp dụng theo loại chứng từ, giá trị, rủi ro, Company, đơn vị và vai trò, bao gồm một hoặc nhiều cấp, từ chối, trả lại để chỉnh sửa, hủy, chuyển cấp, ủy quyền và lịch sử quyết định. Business approval semantics và Shared Workflow Engine được quản lý thống nhất trong capability này. Một Shared Workflow Engine dùng chung để thực thi business approval cần governance/audit, gồm điều kiện phê duyệt, participant/người phê duyệt, trạng thái workflow, ủy quyền và lịch sử quyết định. State transition thuần túy của module không bắt buộc dùng Workflow. Approval Workflow không cấp Permission truy cập. Phân tách trách nhiệm quản trị Workflow: Developer tạo Workflow Type; System Admin triển khai và quản trị Workflow; Business Owner cấu hình Rule và Participant trong phạm vi được cho phép."
success_criteria:
  - "Mỗi chứng từ thuộc diện business approval xác định được rule/cấp phê duyệt, người quyết định, kết quả, thời điểm, lý do và tình trạng hiệu lực của quyết định."
  - "Mỗi giao dịch thuộc diện business approval xác định được workflow, rule áp dụng, participant/người duyệt, trạng thái và lịch sử quyết định."
  - "Quyết định workflow không tạo Permission mới ngoài quyền đã được Authorization cho phép."
  - "Mỗi Workflow Type xác định được nguồn tạo, phạm vi triển khai, người quản trị và Business Owner phụ trách rule/participant."
  - "Business Owner không thay đổi Workflow Type ở mức developer và System Admin không thay đổi rule nghiệp vụ ngoài phạm vi được giao mà không có lịch sử."
depends_on:
  - "BR-CTL-001"
  - "BR-IAM-003"
planned_implemented_by:
  - "FR-CTL-004"
implemented_by: []
planned_verified_by:
  - "UAT-CTL-004"
verified_by: []
```
### 4.5 Audit & Traceability

```yaml
id: "BR-AUD-001"
title: "Quản lý Central Audit Trail"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Một Central Audit Trail cho thay đổi dữ liệu và nghiệp vụ quan trọng, gồm Before Value, After Value, Reason, Approval, Attachment, người thực hiện và thời điểm thay đổi. Central Audit chịu trách nhiệm business/data-change audit; IAM audit chỉ chịu trách nhiệm các sự kiện xác thực, truy cập và quản trị Authorization. BR-CTL-002 có thể cung cấp consumer/event/reconciliation context nhưng không phải Hard Prerequisite để Central Audit Trail tồn tại."
success_criteria:
  - "Có thể truy vết dữ liệu trước và sau thay đổi đối với nghiệp vụ quan trọng."
  - "Audit record xác định được actor/User, thời điểm, đối tượng, thay đổi, lý do và approval/attachment khi áp dụng."
  - "Có thể phân biệt rõ IAM security event với business/data-change audit event."
depends_on:
  - "BR-IAM-001"
references:
  - "BR-CTL-002"
planned_implemented_by:
  - "FR-AUD-001"
implemented_by: []
planned_verified_by:
  - "UAT-AUD-001"
verified_by: []
```
### 4.6 System Foundation

```yaml
id: "BR-SYS-001"
title: "Quản lý locale và display configuration dùng chung"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý cấu hình dùng chung về locale/display như timezone hiển thị mặc định, định dạng ngày giờ, ký hiệu/định dạng tiền tệ, dấu phân cách và số thập phân hiển thị, ngôn ngữ và quy tắc hiển thị. Business/base/functional currency và business timezone của Company thuộc BR-ORG-004 và không được thay đổi business meaning hoặc monetary value."
success_criteria:
  - "Cấu hình locale/display có phạm vi áp dụng, người quản lý và lịch sử thay đổi."
  - "Thay đổi locale/display không làm đổi Company business currency, business timestamp/kỳ hoặc giá trị tiền đã ghi nhận."
depends_on:
  - "BR-ORG-004"
planned_implemented_by:
  - "FR-SYS-001"
implemented_by: []
planned_verified_by:
  - "UAT-SYS-001"
verified_by: []
```
### 4.7 Operational Readiness

```yaml
id: "BR-OPS-001"
title: "Theo dõi đào tạo, hỗ trợ và mức sử dụng ERP"
type: business
priority: SHOULD
target_phase: "P2_EXTENDED"
description: "Theo dõi mức hoàn thành đào tạo, năng lực tối thiểu, người hỗ trợ tại từng đơn vị và mức sử dụng ERP sau khi vận hành."
success_criteria:
  - "Có chỉ số, ngưỡng mục tiêu, chủ sở hữu và hành động cải thiện khi không đạt."
depends_on:
  - "BR-IAM-001"
planned_implemented_by:
  - "FR-OPS-001"
implemented_by: []
planned_verified_by:
  - "UAT-OPS-001"
verified_by: []
```
### 4.8 Master Data & Data Governance

```yaml
id: "BR-MDM-001"
title: "Quản lý common reference data và chuẩn định danh"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý các standard, governance rules và common primitives dùng chung như đơn vị tính, nhóm/phân loại dùng chung, quốc gia, địa bàn, mã HS, thuế suất và các danh mục nền tảng khác. Ownership của reference/common data phải tuân thủ AR-DATA-OWNERSHIP-001. Product, Customer, Supplier và Employee là business entity do domain tương ứng sở hữu; MDM không trở thành owning domain thay thế. Áp dụng chuẩn định danh và quy tắc mã thống nhất cho Customer, Supplier và địa điểm. Customer và Supplier do domain tương ứng sở hữu; MDM chỉ cung cấp standard/governance cho định danh và không sở hữu business entity. Employee không thuộc phạm vi business entity của requirement này. RD Code là specialization của chuẩn định danh cho vật tư, bán thành phẩm, thành phẩm và Supplier; nó không tạo business entity/master identity cạnh tranh. Mỗi entity vẫn có internal identity, business code, RD Code khi áp dụng và optional external/partner codes. Quản lý thống nhất địa chỉ của khách hàng, nhà cung cấp, nhân viên và các đối tượng liên quan bằng controlled internal reference datasets đã được Dinktech phê duyệt, hiện gồm hai bảng `provinces` và `wards`; `wards` bao phủ đơn vị Phường/Xã theo dữ liệu được phê duyệt. Schema/field chi tiết của hai bảng được xác định ở database design và không thuộc User Requirement này. Nếu trong tương lai một danh mục được thay thế hoặc xác lập từ external legal/regulatory/official normative source, quy tắc `source_refs` tại Section 2.3 áp dụng trước khi normative rule tương ứng được approved."
success_criteria:
  - "Các miền sử dụng cùng định nghĩa common primitive và không tạo danh mục chuẩn cạnh tranh cho cùng ý nghĩa."
  - "Mỗi common primitive/reference data được phân loại System Master, Group Master hoặc Company Master theo AR-DATA-OWNERSHIP-001 trước khi được công bố sử dụng."
  - "Ngoại lệ với ownership rule mặc định xác định được owning domain và căn cứ nghiệp vụ."
  - "Mỗi business entity Product, Customer, Supplier và Employee vẫn xác định được owning domain riêng."
  - "Mỗi Customer, Supplier và địa điểm có mã chuẩn phù hợp với quy tắc định danh được phê duyệt và có thể truy ra các giao dịch liên quan."
  - "Chuẩn định danh không tạo master Customer/Supplier cạnh tranh trong MDM."
  - "Mỗi đối tượng thuộc phạm vi RD Code có RD Code duy nhất, xác định được loại và nhóm; quy tắc mã có chủ sở hữu, ngày hiệu lực và không tái sử dụng mã đã dùng."
  - "RD Code liên kết tới cùng Product/Supplier canonical và không tạo duplicate Product/Supplier master."
  - "Có thể phân biệt internal system identity, business code, RD Code và external/partner code của cùng business entity."
  - "Địa chỉ mới phải tham chiếu bản ghi còn hiệu lực trong controlled datasets `provinces` và `wards`; dữ liệu lịch sử vẫn giữ được mã và tên địa chỉ đã sử dụng tại thời điểm phát sinh."
  - "Có thể xác định owner, approval status và version/effective time của controlled address datasets. Current P1 datasets `provinces`/`wards` là internal approved data và không yêu cầu `source_refs`; nếu sau này dùng external normative source thì controlled source reference phải resolve theo Section 2.3 trước approval của rule tương ứng."
depends_on:
  - "BR-ORG-002"
governed_by:
  - "AR-DATA-OWNERSHIP-001"
planned_implemented_by:
  - "FR-MDM-001"
implemented_by: []
planned_verified_by:
  - "UAT-MDM-001"
verified_by: []
```
```yaml
id: "BR-MDM-002"
title: "Quản lý ownership, sharing và field-level override"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Áp dụng đúng phạm vi chia sẻ, sử dụng và chỉnh sửa dữ liệu dựa trên ownership level System Master, Group Master, Company Master và Transaction Data. Người dùng chỉ được xem hoặc thay đổi dữ liệu trong phạm vi được ủy quyền. Phân loại ownership của dữ liệu theo bốn mức System Master, Group Master, Company Master và Transaction Data; Transaction Data phải thuộc đúng một Company. Reference/common data tuân thủ AR-DATA-OWNERSHIP-001. Doanh nghiệp áp dụng Field Level Override theo nguyên tắc default deny: Company chỉ được override field của Group Master khi owning-domain requirement/policy khai báo rõ field đó là overrideable; các field không được khai báo tiếp tục sử dụng giá trị Group Master. Field Level Override không tạo một business entity master mới ở Company và không thay đổi owning domain của entity. Cơ chế này không được dùng để Company sửa trực tiếp Group Pricing Policy hoặc Product Default Selling Price; khác biệt giá ở Company được biểu diễn bằng Company Pricing Policy theo BR-PRC-001; Product Default Selling Price tiếp tục được quản lý theo BR-PRD-005."
success_criteria:
  - "Dữ liệu System/Group/Company được chia sẻ đúng phạm vi và không tạo bản sao cạnh tranh chỉ để phục vụ truy cập địa phương."
  - "Transaction Data luôn xác định được đúng một Company sở hữu."
  - "Người dùng không sửa được dữ liệu ngoài ownership scope và Authorization được cấp."
  - "Mỗi loại dữ liệu thuộc phạm vi quản trị xác định được ownership level và quy tắc truy cập tương ứng."
  - "Product Master, Product Default Selling Price, Customer, Supplier, Employee và Position được phân loại Group Master."
  - "Employee Assignment, Department và Company Pricing Policy được phân loại Company Master; Group Pricing Policy được phân loại Group Master."
  - "Không có Transaction Data thiếu Company sở hữu hoặc thuộc trực tiếp Group."
  - "Có thể xác định field nào được khai báo overrideable, Company nào áp dụng, giá trị Group gốc, giá trị override và thời gian hiệu lực."
  - "Field không được owning-domain requirement/policy cho phép override không thể tạo Company override bằng configuration thông thường."
  - "Override không làm thay đổi giá trị Group Master của các Company khác."
  - "Không tạo master cạnh tranh ở Company chỉ vì một field được override."
  - "Pricing override ở Company được truy về Company Pricing Policy, không ghi đè Group Pricing Policy hoặc Product Default Selling Price."
depends_on:
  - "BR-ORG-001"
  - "BR-ORG-002"
  - "BR-MDM-001"
governed_by:
  - "AR-DATA-OWNERSHIP-001"
  - "AR-TXN-001"
references:
  - "BR-PRC-001"
  - "BR-PRD-005"
planned_implemented_by:
  - "FR-MDM-002"
implemented_by: []
planned_verified_by:
  - "UAT-MDM-002"
verified_by: []
```
```yaml
id: "BR-MDM-003"
title: "Quản lý lifecycle và thay đổi master data nhạy cảm"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Kiểm soát trạng thái, ngày hiệu lực và lý do thay đổi của bản ghi chuẩn. Cơ chế phân công và phê duyệt thay đổi đối với thuộc tính dữ liệu nhạy cảm như mã thuế, tài khoản ngân hàng, điều kiện thanh toán và các manual override thuộc diện policy cho phép. Group-level Product Moving Average Cost được hệ thống tính/cập nhật theo BR-INV-001 là system-derived value, không phải manual master-data change và không tự kích hoạt approval workflow của requirement này. Nếu một manual override đối với cost được phê duyệt ở capability/policy tương lai, override đó phải đi qua controlled sensitive-change rule riêng. Business approval thuộc requirement này sử dụng Shared Workflow để giữ quyết định và lịch sử."
success_criteria:
  - "Bản ghi hết hiệu lực không được dùng cho giao dịch mới; lịch sử vẫn được bảo toàn."
  - "Thay đổi nhạy cảm thuộc diện manual/controlled change xác định được chủ sở hữu, người đề nghị, người phê duyệt, ngày hiệu lực, quyết định và bằng chứng."
  - "Không áp dụng chính thức thay đổi thuộc diện phải phê duyệt khi workflow chưa đạt trạng thái cho phép."
  - "System-derived Product Moving Average Cost từ BR-INV-001 không bị xử lý như manual sensitive-master update chỉ vì giá trị thay đổi sau eligible inbound transaction."
depends_on:
  - "BR-MDM-001"
  - "BR-IAM-003"
  - "BR-CTL-004"
references:
  - "BR-INV-001"
planned_implemented_by:
  - "FR-MDM-003"
implemented_by: []
planned_verified_by:
  - "UAT-MDM-003"
verified_by: []
```
```yaml
id: "BR-MDM-004"
title: "Quản lý duplicate, data quality và lineage của master data"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Nhận diện bản ghi trùng và hỗ trợ hợp nhất có kiểm soát mà không làm mất lịch sử giao dịch. Đánh giá chất lượng dữ liệu theo tính đầy đủ, hợp lệ, duy nhất, nhất quán và kịp thời. Tra cứu nguồn, người sở hữu và lịch sử thay đổi của mọi bản ghi chuẩn thông qua cơ chế Central Audit đối với các thay đổi thuộc diện phải audit."
success_criteria:
  - "Bản ghi hợp nhất giữ được liên kết tới mã cũ và người phê duyệt."
  - "Có điểm chất lượng, danh sách lỗi, chủ sở hữu và hạn xử lý."
  - "Người kiểm tra truy được nguồn, người sở hữu và các phiên bản/thay đổi trước theo phạm vi audit được phê duyệt."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-003"
  - "BR-AUD-001"
planned_implemented_by:
  - "FR-MDM-004"
implemented_by: []
planned_verified_by:
  - "UAT-MDM-004"
verified_by: []
```
### 4.9 Product

```yaml
id: "BR-PRD-001"
title: "Quản lý Product Master và lifecycle vận hành"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý Product Master như business entity thuộc Product domain và là Group Master dùng chung cho các Company trong cùng Group. Product Master bao gồm vật tư sản xuất, vật tư tiêu hao, bán thành phẩm, thành phẩm, sản phẩm vừa sản xuất vừa bán, hàng hóa thương mại, combo, quà tặng và các item vận hành phù hợp. Trong P1, `item_type=COMBO` và `item_type=GIFT` được phép dùng để phân loại Product Master; composition, component consumption, stock treatment, revenue allocation và gift semantics chỉ áp dụng khi BR-PRD-004 nằm trong approved scope. Product Master không phải đối tượng thiết kế kỹ thuật của PLM. Quản lý các thuộc tính vận hành của Product Master gồm mã, tên, mô tả, nhóm, quy cách, đơn vị tính, trọng lượng, kích thước, trạng thái kinh doanh và các thuộc tính cần thiết cho mua hàng, tồn kho, bán hàng, giao nhận hoặc sản xuất. Product Master giữ Group-level Product Moving Average Cost như một system-derived operational cost attribute được BR-INV-001 tính lại sau eligible inbound transaction; thuộc tính này không phải Company override và không tự tạo configurable Inventory Valuation Policy. Quản lý phạm vi sử dụng Product Master theo Company, kênh bán, thị trường và nhóm phân loại giá khi áp dụng. Product Master vẫn là Group Master; việc hạn chế hoặc mở phạm vi sử dụng cho Company/kênh không thay đổi ownership của Product Master. Nhóm phân loại giá chỉ là thuộc tính phân nhóm để Pricing & Promotion tham chiếu, không tự tạo mức giá hoặc chính sách giá. Quản lý trạng thái vận hành của Product Master từ nháp, đang hoạt động/kinh doanh, tạm ngừng đến ngừng sử dụng. Đây là trạng thái sử dụng trong ERP, không phải Product Development Lifecycle của PLM. Quản lý mã vạch, mã QR, mã đối tác và mã thay thế của sản phẩm."
success_criteria:
  - "Mỗi Product Master xác định được Group sở hữu, loại item và chính sách mua, tồn, sản xuất, bán hoặc sử dụng phù hợp."
  - "Các Company trong Group có thể tham chiếu cùng Product Master theo phạm vi được phép mà không phải tạo Product master cạnh tranh."
  - "Product Master được quản lý độc lập với Engineering Product/Development Product trong PLM."
  - "Product Master có mã duy nhất và thiếu thông tin bắt buộc thì không được công bố sử dụng."
  - "Trọng lượng, kích thước và đơn vị tính khi áp dụng có thể được sử dụng nhất quán bởi các nghiệp vụ kho, bán hàng và giao nhận."
  - "Phạm vi sử dụng Product Master được xác định rõ trước khi phát sinh giao dịch."
  - "Thay đổi phạm vi sử dụng không tạo Product Master mới chỉ để phục vụ một Company."
  - "Việc gán nhóm phân loại giá không làm thay đổi Product Default Selling Price nếu không có Pricing Policy có hiệu lực."
  - "Product Master ngừng sử dụng không được phát sinh giao dịch mới nhưng lịch sử vẫn xem được."
  - "Trạng thái Product Master không được hiểu hoặc đồng bộ ngầm thành giai đoạn thiết kế/phát triển của PLM."
  - "Có thể truy ra cùng một sản phẩm từ mỗi mã được phê duyệt."
  - "P1 cho phép phân loại Product là COMBO/GIFT nhưng không tạo acceptance obligation về composition, component consumption, stock/revenue treatment trước khi BR-PRD-004 nằm trong approved scope."
  - "Product Moving Average Cost xác định được Group, giá trị hiện hành, thời điểm cập nhật và nguồn inbound transaction gần nhất đã làm thay đổi giá trị; Company không tạo field override cạnh tranh."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-002"
  - "BR-MDM-003"
references:
  - "BR-INV-001"
  - "BR-PRD-004"
  - "BR-INV-005"
planned_implemented_by:
  - "FR-PRD-001"
implemented_by: []
planned_verified_by:
  - "UAT-PRD-001"
verified_by: []
```
```yaml
id: "BR-PRD-002"
title: "Quản lý đơn vị tính và quy đổi Product"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Hỗ trợ nhiều đơn vị tính và quy đổi có kiểm soát cho mua, tồn, sản xuất và bán. Mỗi Product có đúng một base UOM trong một thời điểm hiệu lực. Conversion phải khai báo rõ from-UOM, to-UOM, chiều quy đổi, hệ số dương và thời gian hiệu lực; các effective period cạnh tranh cho cùng conversion context không được overlap. Precision/rounding của quantity tuân thủ NFR-DAT-001 và không làm mất source quantity trước bước rounding được công bố."
success_criteria:
  - "Mỗi Product có đúng một base UOM đang có hiệu lực tại một thời điểm."
  - "Mỗi conversion xác định được from-UOM, to-UOM, chiều, hệ số > 0 và thời gian hiệu lực."
  - "Không tồn tại hai conversion record đang cùng hiệu lực và cạnh tranh cho cùng Product/from-UOM/to-UOM/conversion context."
  - "Quy đổi bảo toàn source quantity và sử dụng precision/rounding theo NFR-DAT-001; kết quả có thể truy lại source quantity, conversion factor và converted quantity."
  - "Conversion hết hiệu lực không được dùng cho giao dịch mới nhưng giao dịch lịch sử vẫn giữ conversion context đã áp dụng."
depends_on:
  - "BR-PRD-001"
references:
  - "NFR-DAT-001"
planned_implemented_by:
  - "FR-PRD-002"
implemented_by: []
planned_verified_by:
  - "UAT-PRD-002"
verified_by: []
```
```yaml
id: "BR-PRD-003"
title: "Quản lý chính sách lot/serial, hạn dùng, chất lượng và bảo quản"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Đánh dấu sản phẩm yêu cầu quản lý lô, serial, hạn dùng, kiểm tra chất lượng hoặc điều kiện bảo quản."
success_criteria:
  - "Giao dịch liên quan bắt buộc có thông tin truy xuất tương ứng."
depends_on:
  - "BR-PRD-001"
planned_implemented_by:
  - "FR-PRD-003"
implemented_by: []
planned_verified_by:
  - "UAT-PRD-003"
verified_by: []
```
```yaml
id: "BR-PRD-004"
title: "Quản lý combo và quà tặng"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý combo và quà tặng với quan hệ cấu thành, chính sách doanh thu và tồn kho riêng."
success_criteria:
  - "Báo cáo phân biệt sản phẩm bán, thành phần tiêu hao và quà tặng không tính doanh thu."
depends_on:
  - "BR-PRD-001"
  - "BR-PRD-002"
planned_implemented_by:
  - "FR-PRD-004"
implemented_by: []
planned_verified_by:
  - "UAT-PRD-004"
verified_by: []
```
```yaml
id: "BR-PRD-005"
title: "Quản lý Product Default Selling Price"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý Product Default Selling Price thuộc Product domain ở Group level để làm giá cơ sở cuối cùng cho giao dịch bán khi không có Company hoặc Group Pricing Policy/Price List hợp lệ áp dụng. Mỗi default price xác định currency. Các Company trong cùng Group sử dụng giá mặc định này theo phạm vi giao dịch; không tồn tại Company default-price field override mặc định. Khác biệt giá theo Company được quản lý bằng Company Pricing Policy theo BR-PRC-001. Product không bắt buộc phải được đưa vào một bảng giá hoặc Pricing Policy riêng. BR-MDM-002 cung cấp governance/ownership semantics liên quan nhưng không phải Hard Prerequisite để Product Default Selling Price tồn tại."
success_criteria:
  - "Product Master chưa thuộc Pricing Policy có hiệu lực vẫn xác định được giá bán cơ sở từ Product Default Selling Price của Group."
  - "Khi có Company hoặc Group Pricing Policy hợp lệ áp dụng, giá theo policy được ưu tiên theo precedence của BR-PRC-001 và nguồn giá cuối cùng có thể truy vết."
  - "Product Default Selling Price xác định được Group, currency, thời gian hiệu lực và lịch sử thay đổi."
  - "Không tạo Company-specific default-price field override; khác biệt giá theo Company được biểu diễn bằng Company Pricing Policy."
depends_on:
  - "BR-PRD-001"
  - "BR-ORG-004"
references:
  - "BR-MDM-002"
  - "BR-PRC-001"
planned_implemented_by:
  - "FR-PRD-005"
implemented_by: []
planned_verified_by:
  - "UAT-PRD-005"
verified_by: []
```
### 4.10 PLM & Engineering

```yaml
id: "BR-PLM-001"
title: "Quản lý vòng đời Engineering Product"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý Engineering Product/Development Product trong PLM từ ý tưởng, phát triển, mẫu thử, xác minh/xác nhận, sản xuất thử hoặc phát hành kỹ thuật đến ngừng phát triển và lỗi thời. Đối tượng PLM là dữ liệu thiết kế/phát triển độc lập với Product Master vận hành ERP."
success_criteria:
  - "Mỗi Engineering Product/Development Product có giai đoạn vòng đời, chủ sở hữu, trạng thái, mốc quyết định và điều kiện chuyển giai đoạn được xác định."
  - "Có thể tạo và quản lý Engineering Product/Development Product mà không yêu cầu phải tồn tại Product Master tương ứng."
depends_on: []
planned_implemented_by:
  - "FR-PLM-001"
implemented_by: []
planned_verified_by:
  - "UAT-PLM-001"
verified_by: []
```
```yaml
id: "BR-PLM-002"
title: "Quản lý revision và dữ liệu kỹ thuật"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý version/revision của Engineering Product/Development Product theo mục đích, trạng thái, phạm vi áp dụng, ngày hiệu lực kỹ thuật và lịch sử thay đổi trong PLM. Version/revision PLM không phải version của Product Master ERP. Quản lý dữ liệu kỹ thuật của Engineering Product/Development Product trong PLM, gồm tài liệu kỹ thuật, bản vẽ, specification, BOM/cấu trúc kỹ thuật, tiêu chuẩn và kết quả thử nghiệm/xác nhận khi áp dụng. Dữ liệu PLM không được hiểu là thuộc tính vận hành của Product Master ERP."
success_criteria:
  - "Có thể xác định version/revision kỹ thuật hiện hành và truy lại đầy đủ các version/revision trước."
  - "Thay đổi revision không ghi đè làm mất dữ liệu kỹ thuật đã từng có hiệu lực."
  - "Mỗi dữ liệu kỹ thuật xác định được Engineering Product, version/revision, chủ sở hữu, trạng thái và lịch sử thay đổi."
  - "Việc thay đổi dữ liệu kỹ thuật trong PLM không tự động sửa Product Master, giá bán, tồn kho hoặc dữ liệu giao dịch ERP."
depends_on:
  - "BR-PLM-001"
planned_implemented_by:
  - "FR-PLM-002"
implemented_by: []
planned_verified_by:
  - "UAT-PLM-002"
verified_by: []
```
```yaml
id: "BR-PLM-003"
title: "Quản lý Engineering Change từ ECR đến ECN/ECO"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý yêu cầu thay đổi kỹ thuật (ECR) với lý do, nguồn đề nghị, Engineering Product/revision bị ảnh hưởng, mức độ ưu tiên và hồ sơ lịch sử. Đánh giá tác động của thay đổi kỹ thuật đến Engineering Product, version/revision, BOM/cấu trúc kỹ thuật, bản vẽ, specification, tiêu chuẩn, tài liệu và kết quả thử nghiệm trong phạm vi PLM. Phát hành thông báo thay đổi kỹ thuật (ECN) và lệnh thay đổi kỹ thuật (ECO) sau khi thay đổi được phê duyệt, có ngày hiệu lực kỹ thuật và phạm vi Engineering Product/revision áp dụng rõ ràng. Kiểm soát việc sửa đổi BOM/cấu trúc kỹ thuật, bản vẽ, specification, tiêu chuẩn và tài liệu kỹ thuật liên quan đến Engineering Change mà không làm mất lịch sử revision trước. Kiểm soát thời điểm hiệu lực và phạm vi áp dụng của thay đổi kỹ thuật đối với các giai đoạn phát triển, mẫu thử, thử nghiệm/xác nhận và hồ sơ phát hành kỹ thuật trong PLM. Lưu hồ sơ thay đổi kỹ thuật xuyên suốt vòng đời Engineering Product để truy xuất từ ECR/ECN/ECO đến version/revision, tài liệu kỹ thuật, bản vẽ, specification, BOM/cấu trúc kỹ thuật và kết quả thử nghiệm liên quan."
success_criteria:
  - "Mỗi ECR có chủ sở hữu, phạm vi kỹ thuật, trạng thái và quyết định chấp thuận, từ chối hoặc hoãn."
  - "Hồ sơ thay đổi thể hiện các đối tượng kỹ thuật bị ảnh hưởng, rủi ro, hành động chuyển tiếp và người xác nhận."
  - "ECN/ECO liên kết được với ECR, quyết định phê duyệt, revision trước, revision mới và các dữ liệu kỹ thuật bị ảnh hưởng."
  - "Mỗi sửa đổi có căn cứ, người chịu trách nhiệm, ngày hiệu lực kỹ thuật và quan hệ truy xuất tới Engineering Product/revision tương ứng."
  - "Mỗi thay đổi xác định được revision bị thay thế, revision mới, mốc hiệu lực, phạm vi áp dụng và hành động chuyển tiếp đối với hồ sơ phát triển đang mở."
  - "Có thể truy xuôi và truy ngược lịch sử thay đổi của một Engineering Product hoặc revision trong phạm vi PLM."
depends_on:
  - "BR-PLM-001"
  - "BR-PLM-002"
  - "BR-CTL-004"
planned_implemented_by:
  - "FR-PLM-003"
implemented_by: []
planned_verified_by:
  - "UAT-PLM-003"
verified_by: []
```
### 4.11 Customer & CRM

```yaml
id: "BR-CUS-001"
title: "Quản lý Customer Master, hồ sơ và lifecycle"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý Customer như business entity thuộc Customer domain và là Group Master dùng chung cho các Company trong cùng Group. Customer có thể là khách lẻ, đại lý, doanh nghiệp hoặc nhà phân phối và có thông tin liên hệ, địa chỉ, mã số thuế, nhóm khách hàng và trạng thái phù hợp. Quản lý thông tin khách hàng theo danh mục, nhóm, địa chỉ giao dịch, nhiều đầu mối liên hệ, trạng thái và vòng đời từ tiềm năng đến ngừng giao dịch."
success_criteria:
  - "Mỗi Customer có Group sở hữu, mã duy nhất trong phạm vi áp dụng và chủ sở hữu nghiệp vụ."
  - "Các Company trong Group có thể sử dụng cùng Customer theo quyền được cấp mà không phải tạo Customer master cạnh tranh."
  - "Mỗi khách hàng có hồ sơ thống nhất, trạng thái hiện tại, lịch sử trạng thái và các địa chỉ/đầu mối còn hiệu lực."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-002"
planned_implemented_by:
  - "FR-CUS-001"
implemented_by: []
planned_verified_by:
  - "UAT-CUS-001"
verified_by: []
```
```yaml
id: "BR-CUS-002"
title: "Quản lý điều kiện thương mại và tín dụng của Customer"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý điều kiện thanh toán, hạn mức tín dụng, người phụ trách và trạng thái khách hàng. Thay đổi Payment Terms hoặc Credit Limit phải được đánh giá theo approval rule/threshold/policy có hiệu lực; khi rule yêu cầu approval, thay đổi phải sử dụng Shared Workflow theo BR-MDM-003 trước khi có hiệu lực. Thay đổi không thuộc diện approval vẫn phải giữ ngày hiệu lực và lịch sử phù hợp."
success_criteria:
  - "Giao dịch mới phản ánh chính sách có hiệu lực của khách hàng."
  - "Nếu approval rule/threshold/policy được kích hoạt, Payment Terms hoặc Credit Limit mới không có hiệu lực trước khi Shared Workflow đạt trạng thái cho phép."
  - "Có thể xác định rule/policy nào đã quyết định yêu cầu hoặc miễn approval cho thay đổi và truy được lịch sử trước/sau."
depends_on:
  - "BR-CUS-001"
  - "BR-MDM-003"
planned_implemented_by:
  - "FR-CUS-002"
implemented_by: []
planned_verified_by:
  - "UAT-CUS-002"
verified_by: []
```
```yaml
id: "BR-CUS-003"
title: "Theo dõi Customer history và lịch sử tương tác"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Theo dõi lịch sử mua, công nợ, khiếu nại, bảo hành và trả hàng của khách hàng từ các source capability tương ứng khi các capability đó nằm trong approved scope. Customer là foundation bắt buộc; Sales, Finance, Returns, Warranty và các source capability liên quan là conditional sources, không trở thành Hard Prerequisite chỉ vì dữ liệu của chúng được hiển thị trong customer history. Ghi nhận lịch sử liên lạc và nội dung trao đổi với khách hàng theo mục đích kinh doanh, người tham gia và đối tượng liên quan."
success_criteria:
  - "Nhân viên được phân quyền xem lịch sử từ mọi source capability đang nằm trong approved scope và có dữ liệu liên quan tới khách hàng."
  - "Source capability chưa nằm trong approved scope không chặn customer history đối với các source đã sẵn sàng."
  - "Lịch sử liên lạc được truy xuất theo khách hàng, đầu mối, cơ hội hoặc đơn hàng trong phạm vi quyền."
depends_on:
  - "BR-CUS-001"
planned_implemented_by:
  - "FR-CUS-003"
implemented_by: []
planned_verified_by:
  - "UAT-CUS-003"
verified_by: []
```
```yaml
id: "BR-CUS-004"
title: "Quản lý consent, privacy và hạn chế sử dụng dữ liệu Customer"
type: business
priority: SHOULD
target_phase: "P3_ADVANCED"
description: "Quản lý thông tin đồng ý liên lạc, phân loại riêng tư và yêu cầu hạn chế sử dụng dữ liệu khách hàng."
success_criteria:
  - "Có thể chứng minh mục đích sử dụng và phạm vi chia sẻ dữ liệu."
depends_on:
  - "BR-CUS-001"
  - "BR-IAM-005"
planned_implemented_by:
  - "FR-CUS-004"
implemented_by: []
planned_verified_by:
  - "UAT-CUS-004"
verified_by: []
```
### 4.12 Pricing & Promotion

```yaml
id: "BR-PRC-001"
title: "Quản lý Pricing Policy và canonical price resolution"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Pricing Policy/Price List được quản lý ở Group hoặc Company theo điều kiện, currency và thời gian hiệu lực. Company không sửa trực tiếp Group Pricing Policy mà tạo Company Pricing Policy riêng; policy chỉ cần khai báo các trường hợp khác với Product Default Selling Price. Canonical price resolution áp dụng precedence: Company Pricing Policy hợp lệ > Group Pricing Policy hợp lệ > Product Default Selling Price. Pricing Policy có thể tồn tại mà chưa tham chiếu Customer cụ thể; Customer-specific condition chỉ trở thành applicable khi BR-CUS-001/customer context tương ứng có trong scope. Shared Workflow không phải Hard Prerequisite của Pricing; khi approval rule/policy yêu cầu business approval, lifecycle của Pricing Policy phải tham chiếu BR-CTL-004 và chỉ có hiệu lực sau trạng thái workflow cho phép. Price source và transaction giữ currency; khi currency conversion áp dụng phải dùng exchange-rate context theo BR-FIN-005 và giữ original value/currency, FX rate/source/effective time và converted value."
success_criteria:
  - "Mỗi Pricing Policy xác định được ownership level Group hoặc Company, chủ sở hữu, phạm vi áp dụng, điều kiện, currency, thời gian hiệu lực và lịch sử thay đổi."
  - "Company Pricing Policy không ghi đè nội dung của Group Pricing Policy mà tồn tại như policy object riêng trong Company."
  - "Khi đồng thời có nhiều policy thỏa điều kiện cho cùng pricing context/currency, Company Pricing Policy có precedence cao hơn Group Pricing Policy."
  - "Sản phẩm không thuộc Company hoặc Group Pricing Policy có hiệu lực vẫn sử dụng Product Default Selling Price của Group khi default price applicable cho pricing context/currency."
  - "Nguồn giá cuối cùng có thể truy vết về Company Pricing Policy, Group Pricing Policy hoặc Product Default Selling Price cụ thể."
  - "Nếu có Company Pricing Policy hợp lệ áp dụng cho pricing context/currency, hệ thống dùng Company Pricing Policy và không dùng Group Pricing Policy cho cùng quyết định giá."
  - "Nếu không có Company Pricing Policy phù hợp nhưng có Group Pricing Policy hợp lệ cho pricing context/currency, hệ thống dùng Group Pricing Policy."
  - "Nếu không có Company hoặc Group Pricing Policy phù hợp, hệ thống dùng Product Default Selling Price của Group khi applicable."
  - "Có thể giải thích điều kiện áp dụng và nguồn giá cuối cùng là Company Pricing Policy, Group Pricing Policy hay Product Default Selling Price cụ thể."
  - "Khi currency conversion được áp dụng, giao dịch giữ được original price/value, original currency, FX rate/source/effective time và converted value theo BR-FIN-005; conversion không tạo một Pricing Policy cạnh tranh."
  - "Pricing Policy không yêu cầu Customer cụ thể để tồn tại; customer-specific rule chỉ được evaluate khi customer context tương ứng tồn tại."
  - "Khi approval rule yêu cầu Shared Workflow, Pricing Policy không có hiệu lực trước khi workflow đạt trạng thái cho phép; khi approval rule không yêu cầu, không tạo workflow giả chỉ để thỏa capability."
depends_on:
  - "BR-PRD-005"
references:
  - "BR-CUS-001"
  - "BR-CTL-004"
  - "BR-FIN-005"
planned_implemented_by:
  - "FR-PRC-001"
implemented_by: []
planned_verified_by:
  - "UAT-PRC-001"
verified_by: []
```
```yaml
id: "BR-PRC-002"
title: "Quản lý discount, promotion và coupon"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý chính sách chiết khấu và chương trình khuyến mại theo quy tắc, thời hạn, kênh, khách hàng, sản phẩm, giá trị tối thiểu và hình thức combo. Chiết khấu/khuyến mại được áp dụng trên giá cơ sở đã được xác định theo BR-PRC-001 và không thay thế khái niệm Product Default Selling Price. Doanh nghiệp có thể hỗ trợ coupon như một hình thức mở rộng của chương trình khuyến mại khi có quyết định áp dụng và chủ sở hữu chính sách."
success_criteria:
  - "Doanh nghiệp phân biệt được giá cơ sở, nguồn giá cơ sở, giá sau chiết khấu, hàng tặng, combo và khoản giảm trừ theo từng chương trình."
  - "Coupon được nhận diện theo chương trình, điều kiện, thời hạn và trạng thái sử dụng; không làm thay đổi giá trị doanh thu ngoài chính sách."
depends_on:
  - "BR-PRC-001"
  - "BR-PRD-004"
planned_implemented_by:
  - "FR-PRC-002"
implemented_by: []
planned_verified_by:
  - "UAT-PRC-002"
verified_by: []
```
### 4.13 Supplier

```yaml
id: "BR-SUP-001"
title: "Quản lý Supplier Master và thuộc tính nhạy cảm"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Supplier là Group Master thuộc Supplier domain; Purchase chỉ consume Supplier trong sourcing, Purchase Order, receiving và supplier evaluation, không tạo Supplier master cạnh tranh. Kiểm soát thay đổi tài khoản ngân hàng, mã số thuế và điều khoản thanh toán của nhà cung cấp như sensitive master-data change. Thay đổi thuộc phạm vi này phải tuân thủ cơ chế phê duyệt tại BR-MDM-003 và đi qua Shared Workflow trước khi có hiệu lực."
success_criteria:
  - "Mỗi Supplier có Group sở hữu, immutable internal identity, business code/RD Code theo policy áp dụng và trạng thái hợp tác."
  - "Các Company trong Group có thể sử dụng cùng Supplier theo quyền được cấp mà không phải tạo Supplier master cạnh tranh."
  - "Purchase sử dụng Supplier Master canonical và không trở thành owning domain của Supplier."
  - "Supplier ngừng hợp tác không phát sinh cam kết mới."
  - "Thay đổi nhạy cảm xác định được người đề nghị, người xác minh/phê duyệt, quyết định, ngày hiệu lực và lịch sử trước/sau."
  - "Thay đổi thuộc diện phê duyệt không có hiệu lực khi Shared Workflow chưa đạt trạng thái cho phép."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-002"
  - "BR-MDM-003"
planned_implemented_by:
  - "FR-SUP-001"
implemented_by: []
planned_verified_by:
  - "UAT-SUP-001"
verified_by: []
```
```yaml
id: "BR-SUP-002"
title: "Quản lý qualification và approved supplier list"
type: business
priority: SHOULD
target_phase: "P2_EXTENDED"
description: "Thẩm tra hồ sơ nhà cung cấp, chứng nhận, xung đột lợi ích và người phê duyệt trước khi đưa vào danh sách hợp tác. Duy trì danh sách nhà cung cấp được phép cung ứng theo từng sản phẩm, công ty hoặc nhà máy khi chính sách yêu cầu."
success_criteria:
  - "Kết quả thẩm tra, ngày hiệu lực và quyết định được lưu cùng hồ sơ nhà cung cấp."
  - "Việc mua ngoài danh sách được nhận diện, có lý do và được phê duyệt; danh sách có ngày hiệu lực và người sở hữu."
depends_on:
  - "BR-SUP-001"
  - "BR-CTL-004"
  - "BR-PRD-001"
planned_implemented_by:
  - "FR-SUP-002"
implemented_by: []
planned_verified_by:
  - "UAT-SUP-002"
verified_by: []
```
```yaml
id: "BR-SUP-003"
title: "Đánh giá hiệu suất Supplier"
type: business
priority: SHOULD
target_phase: "P2_EXTENDED"
description: "Đánh giá nhà cung cấp theo chất lượng, giao đúng hạn, giá, phản hồi và tỷ lệ khiếu nại."
success_criteria:
  - "Điểm đánh giá có kỳ, tiêu chí, chủ sở hữu và bằng chứng."
depends_on:
  - "BR-SUP-001"
  - "BR-PUR-003"
planned_implemented_by:
  - "FR-SUP-003"
implemented_by: []
planned_verified_by:
  - "UAT-SUP-003"
verified_by: []
```
### 4.14 Purchase

```yaml
id: "BR-PUR-001"
title: "Quản lý Purchase Request và sourcing"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Ghi nhận Purchase Request cho vật tư, hàng hóa, dịch vụ và chi phí với lý do, đơn vị yêu cầu và thời điểm cần. Purchase Request có thể được tạo trước khi chọn Supplier. Mỗi purchase line được phân loại `Stock Item`, `Non-stock Item`, `Service` hoặc `Expense`; Product Master chỉ bắt buộc khi policy của loại dòng yêu cầu tham chiếu Product và không bắt buộc cho mọi Service/Expense line. Thu thập và so sánh báo giá theo giá, chất lượng, thời hạn, điều kiện giao và điều khoản thanh toán khi policy/threshold/category mua hàng yêu cầu RFQ hoặc quotation comparison. Trường hợp policy cho phép direct purchase không bắt buộc phải tạo bước quotation comparison."
success_criteria:
  - "Mỗi nhu cầu xác định được người yêu cầu, Company/đơn vị chịu chi phí, thời điểm cần, loại purchase line và trạng thái quyết định."
  - "Purchase Request có thể được ghi nhận khi chưa chọn Supplier; Supplier được bổ sung trước khi hình thành cam kết mua khi nghiệp vụ yêu cầu."
  - "Service/Expense line có thể được ghi nhận không cần Product Master; line thuộc loại/policy yêu cầu Product phải tham chiếu Product Master hợp lệ."
  - "Khi policy yêu cầu quotation comparison, quyết định lựa chọn Supplier có báo giá/căn cứ so sánh và người chịu trách nhiệm."
  - "Khi policy cho phép direct purchase, nghiệp vụ có thể tiếp tục mà không tạo RFQ/quotation giả chỉ để thỏa luồng."
depends_on:
  - "BR-ORG-002"
  - "BR-SUP-001"
planned_implemented_by:
  - "FR-PUR-001"
implemented_by: []
planned_verified_by:
  - "UAT-PUR-001"
verified_by: []
```
```yaml
id: "BR-PUR-002"
title: "Quản lý Purchase Order và cam kết mua"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý cam kết mua theo Purchase Order, Supplier, loại purchase line, số lượng hoặc giá trị phù hợp với loại dòng, giá, thuế và điều kiện giao hàng/thực hiện. Purchase Order có thể được tạo theo direct-purchase policy hoặc từ quotation comparison khi policy yêu cầu. Budget không thuộc phạm vi requirement này; kiểm soát ngân sách chỉ được bổ sung khi có Budget Domain hoặc Integration Requirement riêng được phê duyệt."
success_criteria:
  - "Cam kết mua đối chiếu được với Purchase Request, Supplier, loại purchase line, số lượng/giá trị, giá, thuế và điều kiện giao hàng/thực hiện đã xác nhận."
  - "Báo giá/lựa chọn Supplier được liên kết khi policy yêu cầu; direct PO được nhận diện và chỉ được dùng khi policy cho phép."
depends_on:
  - "BR-PUR-001"
  - "BR-SUP-001"
planned_implemented_by:
  - "FR-PUR-002"
implemented_by: []
planned_verified_by:
  - "UAT-PUR-002"
verified_by: []
```
```yaml
id: "BR-PUR-003"
title: "Quản lý receiving và chênh lệch nhận hàng"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Kiểm soát việc nhận hàng theo Purchase Order/cam kết mua, số lượng thực nhận, partial/over/under receiving, basic quality/status và chênh lệch so với cam kết. Lot/serial chỉ bắt buộc khi policy/capability quản lý lot/serial tương ứng nằm trong approved scope. Chi phí phát sinh/landed cost chi tiết chỉ tạo acceptance obligation khi capability tương ứng nằm trong approved scope. Purchase Receipt hợp lệ là một nguồn eligible inbound transaction để BR-INV-001 cập nhật Group-level Product Moving Average Cost."
success_criteria:
  - "Mỗi receipt line đối chiếu được với Purchase Order/cam kết mua, Product khi applicable, quantity ordered, quantity previously received, quantity received lần này và cumulative received quantity."
  - "Partial receiving được hỗ trợ; over/under receiving được nhận diện và xử lý theo policy/threshold có hiệu lực."
  - "Basic quality/status của hàng nhận được ghi nhận đủ để quyết định usable/non-usable inventory trong P1."
  - "Chênh lệch được phân loại, có chủ sở hữu và quyết định xử lý."
  - "Lot/serial hoặc landed-cost detail chỉ là acceptance obligation khi capability/policy tương ứng nằm trong approved scope; capability chưa approved không buộc phải tạo dữ liệu giả."
  - "Purchase Receipt được ghi nhận chính thức tạo source reference để BR-INV-001 tính lại Product Moving Average Cost; receipt bị hủy/điều chỉnh phải giữ traceability tới tác động cost tương ứng."
depends_on:
  - "BR-PUR-002"
  - "BR-INV-001"
references:
  - "BR-INV-004"
  - "BR-PUR-004"
planned_implemented_by:
  - "FR-PUR-003"
implemented_by: []
planned_verified_by:
  - "UAT-PUR-003"
verified_by: []
```
```yaml
id: "BR-PUR-004"
title: "Quản lý invoice matching và landed cost"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Hỗ trợ đối chiếu giữa cam kết mua, hàng đã nhận và hóa đơn nhà cung cấp khi chính sách áp dụng. Phân bổ chi phí vận chuyển, bảo hiểm, thuế nhập và chi phí liên quan vào giá trị hàng theo chính sách được duyệt."
success_criteria:
  - "Hóa đơn sai số lượng, giá hoặc điều kiện được nhận diện trước khi thanh toán."
  - "Chi phí phân bổ có căn cứ, tổng phân bổ khớp chứng từ nguồn."
depends_on:
  - "BR-PUR-002"
  - "BR-PUR-003"
  - "BR-FIN-009"
planned_implemented_by:
  - "FR-PUR-004"
implemented_by: []
planned_verified_by:
  - "UAT-PUR-004"
verified_by: []
```
### 4.15 Inventory

```yaml
id: "BR-INV-001"
title: "Quản lý số dư, trạng thái, reservation và Moving Average Cost"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý canonical inventory quantity theo Company, Branch/Plant/Warehouse, location, Product và basic inventory status. Giữ hàng cho Sales Order hoặc mục đích được phê duyệt; reservation phải tách khỏi usable on-hand. P1 có thể lưu lot, serial, expiry và quality-related attributes/basic status khi dữ liệu có sẵn nhưng không yêu cầu end-to-end lot/serial/expiry traceability trước khi BR-INV-004 và BR-PRD-003 nằm trong approved scope. P1 duy trì Group-level Product Moving Average Cost như system-derived operational cost baseline: sau mỗi eligible Purchase Receipt được ghi nhận chính thức và, khi Manufacturing nằm trong approved scope, mỗi eligible Production Finished-Goods Receipt, hệ thống tính lại quantity-weighted moving average và cập nhật Product Moving Average Cost ở Group level cùng source/effective timestamp. Moving Average Cost P1 không phải configurable Inventory Valuation Policy và không tạo Company cost override; configurable inventory valuation thuộc BR-INV-005 khi capability đó nằm trong approved scope."
success_criteria:
  - "Canonical inventory quantity theo từng chiều P1 cộng đúng lên tổng Company scope; P1 không yêu cầu canonical inventory valuation ledger trước khi BR-INV-005 nằm trong approved scope."
  - "Có thể biết usable on-hand, reserved quantity, non-usable/quarantine quantity và in-transit quantity khi trạng thái tương ứng áp dụng."
  - "Hàng ở trạng thái non-usable/quarantine không được cấp phát cho mục đích thông thường."
  - "Lot/serial/expiry có thể được lưu như optional/basic attributes trong P1 nhưng không tạo acceptance obligation về end-to-end genealogy/traceability trước khi capability tương ứng nằm trong approved scope."
  - "Mỗi Product thuộc phạm vi costing xác định được một Product Moving Average Cost hiện hành ở Group level, thời điểm cập nhật và inbound transaction làm thay đổi gần nhất."
  - "Sau eligible Purchase Receipt được ghi nhận chính thức, Moving Average Cost được tính lại theo quantity-weighted moving-average rule trên eligible quantity/value đã chuẩn hóa về UOM/currency costing context."
  - "Khi Manufacturing capability nằm trong approved scope, eligible Production Finished-Goods Receipt tham gia cùng Group-level Moving Average Cost rule; khi Manufacturing chưa nằm trong scope, không tạo production receipt giả để thỏa requirement."
  - "Company không tạo Moving Average Cost override cạnh tranh; P1 Moving Average Cost không được diễn giải là configurable Inventory Valuation Policy của BR-INV-005."
depends_on:
  - "BR-ORG-002"
  - "BR-PRD-001"
references:
  - "BR-PUR-003"
  - "BR-MFG-003"
  - "BR-PRD-003"
  - "BR-INV-004"
  - "BR-INV-005"
planned_implemented_by:
  - "FR-INV-001"
implemented_by: []
planned_verified_by:
  - "UAT-INV-001"
verified_by: []
```
```yaml
id: "BR-INV-002"
title: "Quản lý inventory movement và intra-company transfer"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Ghi nhận các inventory movement thuộc source capability đang trong approved scope, gồm nhập hàng, xuất bán và các movement P1 khác; movement từ Production, Warranty/Returns hoặc capability khác chỉ trở thành acceptance obligation khi source capability tương ứng được approved. Quản lý chuyển kho nội bộ và phân biệt hàng đang vận chuyển với hàng đã nhận. Movement giữa Branch/Plant/Warehouse thuộc cùng một Company là intra-company inventory transfer và không được ghi nhận như intercompany transaction. Movement giữa hai Company khác nhau không thuộc scope của transfer nội bộ; trong P1 phải bị chặn như unsupported cross-company movement. Khi BR-INV-006 nằm trong approved scope, cross-company movement được route theo intercompany capability đó."
success_criteria:
  - "Mỗi biến động thuộc acceptance scope có chứng từ nguồn, người chịu trách nhiệm và lý do."
  - "Kho nguồn, kho đích, số lượng và trạng thái chuyển được đối chiếu."
  - "Transfer giữa các Organization Context thuộc cùng một Company giữ nguyên owning Company và không tự tạo intercompany transaction."
  - "Trong P1, attempt movement giữa hai Company khác nhau bị từ chối và không được ngụy trang thành chuyển kho/chi nhánh nội bộ."
  - "Khi BR-INV-006 nằm trong approved scope, cross-company movement được route theo intercompany flow tương ứng; BR-INV-006 không phải runtime prerequisite của P1."
  - "Movement class của capability chưa nằm trong approved scope không buộc phải được tạo hoặc giả lập chỉ để thỏa BR-INV-002."
depends_on:
  - "BR-INV-001"
  - "BR-ORG-002"
references:
  - "BR-INV-006"
planned_implemented_by:
  - "FR-INV-002"
implemented_by: []
planned_verified_by:
  - "UAT-INV-002"
verified_by: []
```
```yaml
id: "BR-INV-003"
title: "Quản lý kiểm kê, điều chỉnh và kiểm soát tồn bất thường"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Kiểm kê định kỳ hoặc theo rủi ro, ghi nhận chênh lệch và phê duyệt điều chỉnh. Kiểm soát tồn âm và điều chỉnh hồi tố theo policy P1. Control đối với inventory movement sau financial-period close chỉ trở thành acceptance obligation khi BR-FIN-006/financial closing capability nằm trong approved scope; BR-ORG-004 không được diễn giải là owner của financial period."
success_criteria:
  - "Chênh lệch có nguyên nhân, người đếm, người kiểm tra và tác động quantity; tác động cost/value được giải thích theo Product Moving Average Cost P1 hoặc valuation capability đang có hiệu lực."
  - "Ngoại lệ bị chặn hoặc được phê duyệt, có chứng từ điều chỉnh và lý do."
  - "Khi financial closing chưa nằm trong approved scope, P1 không phải tạo closed financial period giả để test inventory adjustment."
  - "Khi BR-FIN-006 nằm trong approved scope, movement/adjustment sau period close tuân thủ close/reopen/retrospective-adjustment policy do Finance quản lý."
depends_on:
  - "BR-INV-001"
  - "BR-CTL-004"
  - "BR-ORG-004"
references:
  - "BR-FIN-006"
planned_implemented_by:
  - "FR-INV-003"
implemented_by: []
planned_verified_by:
  - "UAT-INV-003"
verified_by: []
```
```yaml
id: "BR-INV-004"
title: "Quản lý lot/serial, expiry và traceability"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Truy xuất lô/serial từ nguồn mua hoặc sản xuất đến tồn, giao, khách hàng, bảo hành và thu hồi. Acceptance traceability sử dụng phương pháp lấy mẫu phân tầng theo nguồn Purchase/Production và kiểu Lot/Serial khi các nhóm đó áp dụng. Hỗ trợ mã vạch hoặc QR để nhận diện vật tư, thành phẩm, lô, serial và vị trí khi áp dụng. Quản lý hạn sử dụng và ưu tiên xuất phù hợp với hàng có hạn. Quản lý vòng đời lô/batch từ khi hình thành hoặc tiếp nhận đến khi sử dụng, chuyển giao, giao khách, bảo hành hoặc thu hồi."
success_criteria:
  - "100% mẫu acceptance được chọn phải truy xuất thành công cả xuôi và ngược qua chuỗi nghiệp vụ liên quan."
  - "Mẫu được lấy phân tầng để bao phủ nguồn Purchase, nguồn Production, Lot và Serial khi các nhóm đó có trong phạm vi; tối thiểu 5 mẫu cho mỗi nhóm áp dụng, hoặc kiểm tra toàn bộ nếu population của nhóm nhỏ hơn 5."
  - "Một mẫu không truy xuất đầy đủ được xem là acceptance failure và phải được xử lý trước khi requirement được chấp nhận."
  - "Mã quét dẫn đến đúng đối tượng nghiệp vụ và không tạo biến động trùng."
  - "Hàng gần hết hạn được cảnh báo và xử lý theo chính sách."
  - "Mỗi lô xác định được sản phẩm, nguồn, thời điểm, trạng thái, hạn dùng khi áp dụng và toàn bộ chứng từ liên quan."
depends_on:
  - "BR-INV-001"
  - "BR-PRD-003"
planned_implemented_by:
  - "FR-INV-004"
implemented_by: []
planned_verified_by:
  - "UAT-INV-004"
verified_by: []
```
```yaml
id: "BR-INV-005"
title: "Quản lý chính sách xuất kho và định giá tồn"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Áp dụng chính sách xuất kho và định giá tồn theo công ty, nhóm hàng hoặc loại nghiệp vụ."
success_criteria:
  - "Giá trị xuất và tồn giải thích được theo chính sách có hiệu lực."
depends_on:
  - "BR-INV-001"
  - "BR-ORG-005"
planned_implemented_by:
  - "FR-INV-005"
implemented_by: []
planned_verified_by:
  - "UAT-INV-005"
verified_by: []
```
```yaml
id: "BR-INV-006"
title: "Quản lý intercompany inventory movement"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý movement hàng tồn giữa hai Company trong cùng Group như một cross-company business process gồm các Business Transaction thành phần, mỗi transaction thuộc đúng một owning Company và được correlation bằng cùng `intercompany_reference`. `INTERCOMPANY_TRANSFER` tạo tối thiểu outbound inventory transaction thuộc Sending Company và inbound inventory transaction thuộc Receiving Company, không mặc định tạo sale/purchase, AR/AP hoặc invoice. `INTERCOMPANY_SALE` liên kết inventory outbound/inbound với sales/purchase và financial transaction tương ứng theo responsibility của từng Company và BR-FIN-008. `intercompany_reference` chỉ là coordination/reference key, không phải transaction owner."
success_criteria:
  - "Mỗi cross-company movement được phân loại rõ `INTERCOMPANY_TRANSFER` hoặc `INTERCOMPANY_SALE` và có `intercompany_reference` chung cho các transaction hai phía."
  - "Outbound Inventory Transaction thuộc đúng Sending Company; Inbound Inventory Transaction thuộc đúng Receiving Company; không có một inventory transaction duy nhất do hai Company cùng sở hữu."
  - "Hai phía đối chiếu được số lượng, trạng thái, thời điểm và ownership qua `intercompany_reference` mà không ghi nhận trùng hoặc nhầm với transfer nội bộ cùng Company."
  - "`INTERCOMPANY_TRANSFER` không tự tạo sale/purchase, AR/AP hoặc invoice nếu policy áp dụng không yêu cầu."
  - "`INTERCOMPANY_SALE` liên kết được inventory transaction với sales/purchase/financial transaction tương ứng mà vẫn giữ Company ownership riêng của từng transaction."
depends_on:
  - "BR-INV-001"
  - "BR-ORG-002"
governed_by:
  - "AR-TXN-001"
planned_implemented_by:
  - "FR-INV-006"
implemented_by: []
planned_verified_by:
  - "UAT-INV-006"
verified_by: []
```
### 4.16 Manufacturing

```yaml
id: "BR-MFG-001"
title: "Quản lý BOM, routing, work center và năng lực sản xuất"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý nhiều phiên bản BOM/định mức sản xuất dùng cho Product Master trong vận hành Manufacturing, theo mục đích và thời gian hiệu lực. BOM sản xuất thuộc miền Production và không phụ thuộc vào version/revision của Engineering Product trong PLM. Quản lý công đoạn, trung tâm làm việc, lịch làm việc, năng lực và tiêu chuẩn đầu ra."
success_criteria:
  - "Tại một thời điểm chỉ có phiên bản BOM/định mức mặc định phù hợp cho mục đích đã chọn."
  - "Production có thể vận hành với Product Master và BOM/định mức sản xuất mà không yêu cầu phải có Engineering Product trong PLM."
  - "Sản phẩm và lệnh sản xuất có thể xác định nơi và tiêu chuẩn thực hiện."
depends_on:
  - "BR-PRD-001"
  - "BR-ORG-002"
planned_implemented_by:
  - "FR-MFG-001"
implemented_by: []
planned_verified_by:
  - "UAT-MFG-001"
verified_by: []
```
```yaml
id: "BR-MFG-002"
title: "Lập kế hoạch nhu cầu vật tư và năng lực"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Lập nhu cầu vật tư và năng lực dựa trên BOM/định mức, năng lực/lịch sản xuất, tồn kho và các nguồn demand/supply có hiệu lực. Sales demand, Purchase Order hoặc nguồn dự báo/manual demand được sử dụng khi capability tương ứng có trong scope; Sales/Purchase không phải Hard Prerequisite để Manufacturing Planning capability tồn tại."
success_criteria:
  - "Kết quả chỉ ra thiếu vật tư, dư cung hoặc quá tải cần quyết định và truy được các nguồn demand/supply đã dùng cho lần tính."
  - "Planning có thể chạy với bộ nguồn đầu vào được phê duyệt mà không yêu cầu tạo Sales/Purchase transaction giả khi các capability đó chưa nằm trong scope."
depends_on:
  - "BR-MFG-001"
  - "BR-INV-001"
planned_implemented_by:
  - "FR-MFG-002"
implemented_by: []
planned_verified_by:
  - "UAT-MFG-002"
verified_by: []
```
```yaml
id: "BR-MFG-003"
title: "Quản lý Production Order, WIP, output, yield và scrap"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý lệnh sản xuất với sản phẩm, số lượng, định mức, công đoạn, ngày dự kiến và người phụ trách. Theo dõi vật tư đã cấp, sản lượng đạt, sản phẩm dở dang, phế phẩm, sản phẩm phụ và thời gian lao động. Quản lý tỷ lệ hao hụt, sản lượng đạt, làm lại và phế phẩm theo sản phẩm, công đoạn và nguyên nhân."
success_criteria:
  - "Lệnh sản xuất có trạng thái, nguồn nhu cầu và phạm vi nhà máy rõ ràng."
  - "Số lượng đầu vào và đầu ra được đối chiếu theo lệnh sản xuất."
  - "Vượt ngưỡng được cảnh báo và có quyết định xử lý."
depends_on:
  - "BR-MFG-001"
  - "BR-INV-001"
planned_implemented_by:
  - "FR-MFG-003"
implemented_by: []
planned_verified_by:
  - "UAT-MFG-003"
verified_by: []
```
```yaml
id: "BR-MFG-004"
title: "Quản lý quality control và production genealogy"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Kiểm tra chất lượng tại các điểm phù hợp trước khi cho phép sử dụng hoặc nhập thành phẩm. Kết quả quality/lab có thể có trạng thái preliminary/confirmed theo policy; chỉ kết quả đã được xác nhận bởi người có thẩm quyền mới được dùng cho quyết định yêu cầu dữ liệu chính thức. Truy xuất quan hệ giữa nguyên vật liệu, công đoạn, người thực hiện, thiết bị, lô đầu ra và khách hàng."
success_criteria:
  - "Kết quả đạt, không đạt, cách ly và quyết định xử lý được lưu cùng đối tượng."
  - "Kết quả quality/lab nhận diện được trạng thái preliminary/confirmed khi áp dụng và chỉ kết quả confirmed theo policy được sử dụng cho quyết định yêu cầu dữ liệu chính thức."
  - "Chủ sở hữu chất lượng có thể lập hồ sơ truy xuất cho một lô hoặc serial."
depends_on:
  - "BR-MFG-003"
  - "BR-INV-004"
  - "BR-PRD-003"
planned_implemented_by:
  - "FR-MFG-004"
implemented_by: []
planned_verified_by:
  - "UAT-MFG-004"
verified_by: []
```
```yaml
id: "BR-MFG-005"
title: "Kiểm soát thay đổi Production BOM và process đang mở"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Kiểm soát thay đổi Production BOM/định mức, công đoạn hoặc tiêu chuẩn đối với lệnh sản xuất đang mở. Cơ chế change control này thuộc Production và phải vận hành độc lập với PLM. Khi thay đổi có nguồn từ ECR/ECN/ECO và integration PLM được áp dụng, Production lưu được tham chiếu nguồn thay đổi nhưng không phụ thuộc PLM để thực hiện change control nội bộ."
success_criteria:
  - "Ảnh hưởng về số lượng, chất lượng, giá trị và truy xuất được đánh giá trước khi thay đổi có hiệu lực trên lệnh sản xuất đang mở."
  - "Production change control vận hành được khi không có Engineering Product hoặc PLM."
  - "Khi có nguồn thay đổi từ PLM, có thể lưu tham chiếu tới ECR/ECN/ECO mà không làm PLM trở thành dependency bắt buộc của Production."
depends_on:
  - "BR-MFG-001"
planned_implemented_by:
  - "FR-MFG-005"
implemented_by: []
planned_verified_by:
  - "UAT-MFG-005"
verified_by: []
```
```yaml
id: "BR-MFG-006"
title: "Tính và phân bổ giá thành sản xuất"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Tính và phân bổ giá thành sản xuất, WIP, chi phí vật tư, nhân công và chi phí chung theo chính sách."
success_criteria:
  - "Giá thành và WIP khớp tổng kiểm soát được Tài chính và Sản xuất xác nhận."
depends_on:
  - "BR-MFG-003"
  - "BR-INV-005"
planned_implemented_by:
  - "FR-MFG-006"
implemented_by: []
planned_verified_by:
  - "UAT-MFG-006"
verified_by: []
```
```yaml
id: "BR-MFG-007"
title: "Quản lý subcontract manufacturing"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý nhà cung cấp gia công, phạm vi công đoạn thuê ngoài, vật tư cấp cho gia công và trách nhiệm đối với sản phẩm dở dang tại bên nhận gia công. Quản lý việc nhận thành phẩm từ gia công ngoài, chi phí gia công, kiểm tra chất lượng và quyết toán với nhà cung cấp gia công."
success_criteria:
  - "Mỗi hoạt động gia công ngoài xác định được nhà cung cấp, sản phẩm, vật tư đã cấp, số lượng, thời hạn và lệnh sản xuất liên quan."
  - "Số lượng nhận, kết quả chất lượng, chi phí và số liệu quyết toán đối chiếu được với vật tư đã cấp và cam kết gia công."
depends_on:
  - "BR-MFG-003"
  - "BR-SUP-001"
  - "BR-INV-001"
  - "BR-PUR-002"
  - "BR-MFG-004"
planned_implemented_by:
  - "FR-MFG-007"
implemented_by: []
planned_verified_by:
  - "UAT-MFG-007"
verified_by: []
```
### 4.17 Sales

```yaml
id: "BR-SAL-001"
title: "Quản lý quotation và sales order capture"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Tiếp nhận/capture Sales Order và Quotation với source/channel attribution như website, sàn, điểm bán, điện thoại, nhân viên kinh doanh, đại lý hoặc nhà phân phối. Trong P1, source/channel có thể được ghi nhận thủ công hoặc từ capability đang có; việc liệt kê website/sàn không tạo Hard Prerequisite cho external integration. Quản lý báo giá, điều kiện bán, thời hạn hiệu lực, base price và các giá trị discount/tax cần lưu trên chứng từ. Giá cơ sở phải được xác định bằng canonical price-resolution rule tại BR-PRC-001. P1 không mặc nhiên cung cấp Promotion/Discount Policy Engine hoặc operational Tax Engine; khi BR-PRC-002 hoặc BR-FIN-004 nằm trong approved scope, discount/tax calculation áp dụng rule của capability tương ứng."
success_criteria:
  - "Đơn hàng có source/channel, mã tham chiếu, Customer hoặc thông tin khách lẻ phù hợp."
  - "P1 có thể capture source/channel mà không yêu cầu external integration; khi integration capability chưa nằm trong approved scope, source/channel có thể được nhập theo controlled business process."
  - "Giá cơ sở của báo giá truy được nguồn theo BR-PRC-001 và điều kiện được giải thích theo chính sách áp dụng tại thời điểm xác nhận."
  - "Discount/tax value được lưu cùng chứng từ khi áp dụng, nhưng việc tự tính theo promotion/tax policy chỉ là acceptance obligation khi BR-PRC-002/BR-FIN-004 tương ứng nằm trong approved scope."
depends_on:
  - "BR-CUS-001"
  - "BR-PRD-001"
  - "BR-PRC-001"
references:
  - "BR-PRC-002"
  - "BR-FIN-004"
  - "BR-INT-001"
planned_implemented_by:
  - "FR-SAL-001"
implemented_by: []
planned_verified_by:
  - "UAT-SAL-001"
verified_by: []
```
```yaml
id: "BR-SAL-002"
title: "Quản lý availability và fulfillment commitment"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Kiểm tra khả năng đáp ứng và fulfillment commitment dựa trên inventory hiện có. P1 Available-to-Promise (ATP) được xác định bằng usable on-hand trừ reservation hiện hành trong đúng Company/inventory scope. Incoming Purchase Order, planned Production, forecast hoặc future supply khác không làm tăng ATP P1. Hệ thống vẫn có thể ghi nhận expected delivery date/commitment và phần chưa đáp ứng nhưng future supply chỉ được dùng trong ATP khi capability/phương pháp tương lai được approved."
success_criteria:
  - "ATP P1 = usable on-hand - reservation trong đúng Company/inventory scope và không bao gồm future supply."
  - "Nhân viên biết rõ số có thể cam kết, số đã giữ và phần chưa đáp ứng."
  - "Incoming PO, planned Production hoặc forecast không làm tăng ATP P1 chỉ vì tồn tại trong hệ thống."
  - "Expected delivery date/commitment được lưu tách biệt với ATP quantity và không làm thay đổi usable on-hand hoặc reservation."
depends_on:
  - "BR-SAL-001"
  - "BR-INV-001"
planned_implemented_by:
  - "FR-SAL-002"
implemented_by: []
planned_verified_by:
  - "UAT-SAL-002"
verified_by: []
```
```yaml
id: "BR-SAL-003"
title: "Kiểm soát credit và commercial exception"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Kiểm soát hạn mức tín dụng, công nợ quá hạn và các ngoại lệ bán chịu. Trạng thái công nợ/quá hạn từ Accounts Receivable là input bắt buộc của control này. Kiểm soát việc vượt hạn mức, bán chịu, chiết khấu ngoài chính sách và miễn phí vận chuyển."
success_criteria:
  - "Đơn vượt chính sách không được xác nhận nếu thiếu phê duyệt ngoại lệ."
  - "Quyết định credit control sử dụng được trạng thái công nợ và quá hạn hiện hành từ capability phải thu."
  - "Ngoại lệ có người phê duyệt, lý do và thời hạn."
depends_on:
  - "BR-SAL-001"
  - "BR-CUS-002"
  - "BR-FIN-001"
  - "BR-CTL-004"
  - "BR-PRC-001"
planned_implemented_by:
  - "FR-SAL-003"
implemented_by: []
planned_verified_by:
  - "UAT-SAL-003"
verified_by: []
```
```yaml
id: "BR-SAL-004"
title: "Quản lý lifecycle fulfillment và thành phần doanh thu của Sales Order"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý giao từng phần, đơn chờ, hủy, thay thế, trả hàng và hoàn tiền. Phân biệt doanh thu, quà tặng, hàng khuyến mại, phí giao và các khoản thu hộ."
success_criteria:
  - "Số lượng và công nợ sau mỗi ngoại lệ khớp với đơn gốc."
  - "Báo cáo doanh thu và công nợ không gộp sai bản chất khoản tiền."
depends_on:
  - "BR-SAL-001"
  - "BR-INV-001"
  - "BR-PRD-004"
planned_implemented_by:
  - "FR-SAL-004"
implemented_by: []
planned_verified_by:
  - "UAT-SAL-004"
verified_by: []
```
```yaml
id: "BR-SAL-005"
title: "Quản lý return, RMA, warranty và complaint"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý bảo hành, đổi trả và khiếu nại theo sản phẩm, lô/serial, khách hàng và nguyên nhân. Quản lý yêu cầu trả hàng của khách hàng theo hồ sơ RMA, giao dịch bán hoặc lý do độc lập, nguyên nhân, số lượng, lô/serial và trạng thái phê duyệt. Quản lý kết quả kiểm tra hàng trả và quyết định xử lý gồm nhập lại, thay thế, sửa chữa, loại bỏ, hoàn tiền hoặc phát hành chứng từ giảm trừ/ghi có."
success_criteria:
  - "Có thể truy lại giao dịch bán và quyết định xử lý."
  - "Mỗi yêu cầu trả hàng có căn cứ, người chịu trách nhiệm, quyết định phê duyệt và liên kết tới hàng hoặc giao dịch liên quan."
  - "Kết quả xử lý phản ánh đúng tình trạng hàng, giá trị ảnh hưởng, công nợ và bằng chứng kiểm tra."
depends_on:
  - "BR-SAL-001"
  - "BR-INV-001"
  - "BR-INV-004"
  - "BR-CTL-004"
planned_implemented_by:
  - "FR-SAL-005"
implemented_by: []
planned_verified_by:
  - "UAT-SAL-005"
verified_by: []
```
### 4.18 Finance Operations

```yaml
id: "BR-FIN-001"
title: "Quản lý Accounts Receivable"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý phải thu theo khách hàng, hóa đơn, đơn bán, khoản thu, hạn thanh toán và trạng thái quá hạn."
success_criteria:
  - "Số dư khách hàng khớp với các chứng từ nguồn và khoản đã phân bổ."
  - "Mỗi khoản phải thu xác định được Customer, Company, chứng từ nguồn, số tiền gốc, số đã phân bổ, hạn thanh toán và trạng thái."
depends_on:
  - "BR-SAL-001"
  - "BR-CUS-001"
planned_implemented_by:
  - "FR-FIN-001"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-001"
verified_by: []
```
```yaml
id: "BR-FIN-002"
title: "Quản lý cash, payment, expense và treasury account"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý thu, chi, chuyển tiền, tạm ứng, hoàn ứng và chi phí theo Company, Organization Context và Department có hiệu lực. Khi capability dự án/đối tượng tập hợp chi phí tại BR-ORG-006 được triển khai, giao dịch tài chính có thể tham chiếu dự án theo phạm vi được áp dụng; BR-ORG-006 không phải hard prerequisite của requirement này. Quản lý quỹ tiền mặt, tài khoản ngân hàng và ví điện tử với số dư theo đồng tiền."
success_criteria:
  - "Mọi khoản thu/chi xác định được Company, phạm vi quản trị liên quan, mục đích, người chịu trách nhiệm và bằng chứng."
  - "Khi gán Department, Department phải thuộc đúng Company và đang có hiệu lực."
  - "Khi capability dự án được áp dụng, dự án/đối tượng tập hợp chi phí được tham chiếu theo trạng thái và Company sở hữu của đối tượng đó."
  - "Số dư chỉ bao gồm giao dịch đã được ghi nhận chính thức."
depends_on:
  - "BR-ORG-002"
  - "BR-EMP-002"
  - "BR-IAM-003"
  - "BR-ORG-004"
planned_implemented_by:
  - "FR-FIN-002"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-002"
verified_by: []
```
```yaml
id: "BR-FIN-003"
title: "Đối soát ngân hàng"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Đối soát sao kê ngân hàng theo một-một, một-nhiều, nhiều-một và đối soát một phần."
success_criteria:
  - "Dòng chưa khớp có chủ sở hữu, lý do, tuổi và quyết định xử lý."
depends_on:
  - "BR-FIN-002"
planned_implemented_by:
  - "FR-FIN-003"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-003"
verified_by: []
```
```yaml
id: "BR-FIN-004"
title: "Quản lý operational tax và invoice lifecycle"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý VAT, giao dịch không chịu thuế, thuế đầu vào/đầu ra, tax rounding policy và thời gian áp dụng chính sách thuế. Tax rounding mặc định thực hiện theo từng dòng rồi tổng hợp; nếu controlled legal/regulatory source hoặc tax policy được phê duyệt quy định khác thì áp dụng policy có hiệu lực tương ứng. Quản lý vòng đời hóa đơn theo Company/policy áp dụng, gồm phát hành, gửi, chấp nhận, từ chối, hủy, thay thế và điều chỉnh. Requirement này xác định business capability; quy tắc pháp lý cụ thể chỉ trở thành normative khi có controlled legal/regulatory source reference tương ứng. Đối chiếu số lượng hóa đơn, doanh thu, thuế và trạng thái hóa đơn giữa nguồn phát hành và số liệu tài chính. Các rule pháp lý cụ thể về trạng thái/chấp nhận chỉ áp dụng khi có controlled legal/regulatory source tương ứng."
success_criteria:
  - "Báo cáo thuế tách đúng loại giao dịch và kỳ."
  - "Tax rounding policy xác định được phương pháp làm tròn, thời gian hiệu lực và nguồn/policy áp dụng; default là rounding từng dòng rồi tổng hợp khi không có policy có hiệu lực quy định khác."
  - "Số hóa đơn, trạng thái, lý do và liên kết hóa đơn gốc/thay thế/điều chỉnh được bảo toàn."
  - "Nếu một trạng thái hoặc rule được áp dụng do legal/regulatory baseline, requirement/rule consuming phải resolve được controlled source reference trước approval."
  - "Chênh lệch có chủ sở hữu, kỳ, nguyên nhân và quyết định xử lý."
  - "Đối chiếu phân biệt business status với rule pháp lý được kích hoạt từ controlled source khi có."
depends_on:
  - "BR-ORG-004"
  - "BR-MDM-001"
  - "BR-SAL-001"
  - "BR-PUR-002"
  - "BR-FIN-001"
  - "BR-FIN-009"
planned_implemented_by:
  - "FR-FIN-004"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-004"
verified_by: []
```
```yaml
id: "BR-FIN-005"
title: "Quản lý multi-currency và canonical Exchange Rate Master"
type: business
priority: MUST
target_phase: "P1_CORE"
description: "Quản lý nhiều đồng tiền và canonical Exchange Rate Master cho currency pair, rate type, rate value, source, effective time và precedence khi nhiều rate có thể áp dụng. Mọi conversion phải giữ original amount/currency, exchange-rate reference, converted amount/currency và effective context. Missing-rate behavior phải deterministic: giao dịch yêu cầu conversion không được ghi nhận chính thức nếu không resolve được một rate hợp lệ theo precedence/policy đang có hiệu lực, trừ khi controlled exception rule riêng được phê duyệt. P1 requirement này cung cấp exchange-rate/conversion baseline; realized/unrealized FX accounting revaluation không tự động thuộc P1 nếu chưa có Finance capability riêng được approved."
success_criteria:
  - "Mỗi exchange-rate record xác định được from currency, to currency, rate type, rate value, source, effective-from/effective time, lifecycle/status và owner."
  - "Khi có nhiều rate cùng có thể áp dụng, hệ thống resolve một rate deterministic theo controlled precedence/policy và lưu được rate record đã sử dụng."
  - "Số tiền gốc, original currency, tỷ giá/rate source áp dụng, converted amount, target currency và effective time đều truy xuất được."
  - "Giao dịch cần conversion nhưng không resolve được rate hợp lệ bị chặn khỏi official posting hoặc đi qua controlled exception rule nếu rule đó được phê duyệt; không tự suy diễn rate."
  - "Exchange-rate history không bị ghi đè làm mất rate từng có hiệu lực."
  - "P1 Exchange Rate Master không mặc nhiên tạo accounting requirement về realized/unrealized FX revaluation ngoài capability được approved."
depends_on:
  - "BR-ORG-004"
planned_implemented_by:
  - "FR-FIN-005"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-005"
verified_by: []
```
```yaml
id: "BR-FIN-006"
title: "Quản lý financial period, closing và periodic allocation"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý kỳ tài chính, khóa kỳ, mở lại kỳ có thẩm quyền và điều chỉnh hồi tố. Quản lý chi phí trả trước, phân bổ, tích lũy và chi phí định kỳ khi chính sách yêu cầu."
success_criteria:
  - "Giao dịch sau khi khóa tuân thủ chính sách điều chỉnh và giữ liên kết kỳ gốc."
  - "Số tiền phân bổ có kỳ, căn cứ và người xác nhận."
depends_on:
  - "BR-ORG-004"
  - "BR-CTL-004"
  - "BR-FIN-002"
planned_implemented_by:
  - "FR-FIN-006"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-006"
verified_by: []
```
```yaml
id: "BR-FIN-007"
title: "Quản lý nguồn số liệu tài chính vận hành và traceability"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Xác định ERP là nguồn dữ liệu chính cho tính toán tài chính vận hành trong phạm vi triển khai, đồng thời xác định nguồn số liệu chính thức cho tồn kho, công nợ, thuế, hóa đơn, lương và quyết toán đối tác. Bảo đảm mọi giao dịch tài chính có thể truy về nghiệp vụ nguồn và ngược lại đối với các source domain nằm trong approved scope. Manufacturing, Payroll, Payment và các source domain khác là conditional applicability khi capability tương ứng được approved, không trở thành Hard Prerequisite chỉ để requirement này được phê duyệt."
success_criteria:
  - "Mỗi nhóm số liệu xác định được nguồn chính, owning domain, chủ sở hữu đối soát và cách xử lý chênh lệch."
  - "Với từng source domain nằm trong approved scope, mẫu giao dịch tương ứng (ví dụ mua, bán, kho, sản xuất, lương hoặc thanh toán khi áp dụng) truy được hai chiều giữa nghiệp vụ nguồn và giao dịch tài chính."
  - "Source domain chưa nằm trong approved scope không chặn traceability đối với các source domain đã sẵn sàng."
depends_on:
  - "BR-FIN-001"
  - "BR-FIN-009"
  - "BR-INV-001"
  - "BR-INV-002"
planned_implemented_by:
  - "FR-FIN-007"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-007"
verified_by: []
```
```yaml
id: "BR-FIN-008"
title: "Quản lý financial consequence của intercompany sale"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý financial consequence của `INTERCOMPANY_SALE` như một cross-company business process. Sales/AR transaction phía bán thuộc Selling Company; Purchase/AP transaction phía mua thuộc Buying Company; inventory outbound/inbound tương ứng thuộc Company của từng phía. Các transaction được correlation bằng cùng `intercompany_reference`; không tồn tại một financial Business Transaction duy nhất do hai Company cùng sở hữu. `INTERCOMPANY_TRANSFER` không tự động phát sinh các nghĩa vụ tài chính này nếu policy áp dụng không yêu cầu."
success_criteria:
  - "Sales/AR phía bán xác định Selling Company owner; Purchase/AP phía mua xác định Buying Company owner."
  - "Doanh thu, chi phí, công nợ, thuế và số liệu quyết toán hai phía được đối chiếu qua cùng `intercompany_reference` nhưng giữ transaction identity/ownership riêng."
  - "Khi phát sinh từ `INTERCOMPANY_SALE`, financial transaction truy được inventory/sales/purchase transaction liên quan mà không tạo bản ghi trùng hoặc dual-Company ownership."
depends_on:
  - "BR-ORG-002"
  - "BR-FIN-001"
  - "BR-FIN-009"
governed_by:
  - "AR-TXN-001"
planned_implemented_by:
  - "FR-FIN-008"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-008"
verified_by: []
```

```yaml
id: "BR-FIN-009"
title: "Quản lý Accounts Payable"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý phải trả theo nhà cung cấp, hóa đơn, đơn mua, khoản trả và hạn thanh toán."
success_criteria:
  - "Số dư nhà cung cấp giải thích được theo chứng từ chưa thanh toán."
  - "Mỗi khoản phải trả xác định được Supplier, Company, chứng từ nguồn, số tiền gốc, số đã phân bổ, hạn thanh toán và trạng thái."
depends_on:
  - "BR-PUR-002"
  - "BR-SUP-001"
planned_implemented_by:
  - "FR-FIN-009"
implemented_by: []
planned_verified_by:
  - "UAT-FIN-009"
verified_by: []
```
### 4.19 Logistics & Delivery

```yaml
id: "BR-LOG-001"
title: "Quản lý carrier và delivery service"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý danh mục nhà vận chuyển và dịch vụ theo khu vực, loại hàng, SLA và điều kiện thu hộ."
success_criteria:
  - "Chỉ đơn vị đang được phê duyệt mới được dùng cho giao mới."
depends_on:
  - "BR-MDM-001"
  - "BR-ORG-002"
planned_implemented_by:
  - "FR-LOG-001"
implemented_by: []
planned_verified_by:
  - "UAT-LOG-001"
verified_by: []
```
```yaml
id: "BR-LOG-002"
title: "Quản lý shipment, delivery status và exception"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý thông tin vận đơn, kiện hàng, phí, người nhận, bằng chứng giao và trạng thái giao. Theo dõi giao thất bại, hoàn hàng, mất hàng, hư hỏng, sai phí và khiếu nại."
success_criteria:
  - "Vận đơn liên kết được với đơn bán và số lượng đã xuất."
  - "Mỗi ngoại lệ có chủ sở hữu, lý do, giá trị ảnh hưởng và kết quả xử lý."
depends_on:
  - "BR-SAL-001"
  - "BR-INV-002"
  - "BR-LOG-001"
planned_implemented_by:
  - "FR-LOG-002"
implemented_by: []
planned_verified_by:
  - "UAT-LOG-002"
verified_by: []
```
```yaml
id: "BR-LOG-003"
title: "Đối soát COD, freight và carrier charge"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Đối soát COD, cước, phụ phí và khoản khấu trừ với bảng kê của nhà vận chuyển."
success_criteria:
  - "Số tiền đã nhận, còn thiếu và phí được phân loại rõ."
depends_on:
  - "BR-LOG-002"
  - "BR-FIN-002"
planned_implemented_by:
  - "FR-LOG-003"
implemented_by: []
planned_verified_by:
  - "UAT-LOG-003"
verified_by: []
```
### 4.20 Commerce / Omnichannel / POS

```yaml
id: "BR-COM-001"
title: "Quản lý mapping dữ liệu với kênh bán"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý bản đồ mã sản phẩm, khách hàng, kho và trạng thái giữa ERP và từng kênh bán."
success_criteria:
  - "Mã bên ngoài liên kết được với mã chuẩn và không tạo bản ghi trùng."
depends_on:
  - "BR-PRD-001"
  - "BR-CUS-001"
  - "BR-INV-001"
planned_implemented_by:
  - "FR-COM-001"
implemented_by: []
planned_verified_by:
  - "UAT-COM-001"
verified_by: []
```
```yaml
id: "BR-COM-002"
title: "Đồng bộ đơn hàng, trạng thái và sellable inventory đa kênh"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Tiếp nhận đơn đa kênh, nhận biết đơn trùng và theo dõi trạng thái xử lý. Phản ánh tồn có thể bán và trạng thái giao hàng cho các kênh được phê duyệt."
success_criteria:
  - "Một đơn bên ngoài chỉ tạo một nghĩa vụ bán tương ứng."
  - "Số công bố không vượt chính sách tồn có thể bán."
depends_on:
  - "BR-COM-001"
  - "BR-SAL-001"
  - "BR-INV-001"
planned_implemented_by:
  - "FR-COM-002"
implemented_by: []
planned_verified_by:
  - "UAT-COM-002"
verified_by: []
```
```yaml
id: "BR-COM-003"
title: "Quản lý POS shift và controlled offline fallback"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý ca bán hàng, người phụ trách, tiền đầu ca, phát sinh, hoàn tiền và tiền cuối ca. Hỗ trợ bán tại quầy trong điều kiện gián đoạn ngắn theo chính sách được phê duyệt. Continuity/reconciliation semantics của `NFR-AVL-002` áp dụng cho fallback này; external Data Exchange/Integration không phải Hard Prerequisite."
success_criteria:
  - "Chênh lệch ca có lý do, người xác nhận và trạng thái quyết định."
  - "Giao dịch phát sinh trong thời gian gián đoạn được ghi nhận, đánh dấu và đối soát sau đó theo controlled fallback/reconciliation của NFR-AVL-002."
  - "Fallback bán tại quầy có thể vận hành và được kiểm thử ngay cả khi external Data Exchange/Integration chưa nằm trong approved scope."
depends_on:
  - "BR-ORG-002"
  - "BR-IAM-001"
planned_implemented_by:
  - "FR-COM-003"
implemented_by: []
planned_verified_by:
  - "UAT-COM-003"
verified_by: []
```
```yaml
id: "BR-COM-004"
title: "Đối soát kênh bán và external transaction evidence"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Đối soát doanh thu, phí sàn, phiếu khuyến mại, thuế, COD, hoàn tiền và khoản truy thu/hoàn trả. Lưu mã tham chiếu và bằng chứng của giao dịch bên ngoài để phục vụ khiếu nại."
success_criteria:
  - "Bảng kê kênh được phân loại thành khớp, lệch, chờ xử lý hoặc tranh chấp."
  - "Có thể truy từ giao dịch ERP tới bảng kê và ngược lại."
depends_on:
  - "BR-COM-002"
  - "BR-LOG-003"
  - "BR-FIN-001"
  - "BR-DOC-001"
planned_implemented_by:
  - "FR-COM-004"
implemented_by: []
planned_verified_by:
  - "UAT-COM-004"
verified_by: []
```
### 4.21 HCM & Payroll

```yaml
id: "BR-HRP-001"
title: "Quản lý hồ sơ HR, hợp đồng và Employee Work Calendar"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý hồ sơ nhân sự và hợp đồng bằng cách mở rộng Employee/Employee Assignment do Employee domain sở hữu. HRP quản lý dữ liệu như hợp đồng và các thuộc tính nhân sự/tiền lương có hiệu lực; HRP không tạo Employee, Department, Position hoặc Employee Assignment master song song. Quản lý Employee Work Calendar gồm ca/lịch làm việc chuẩn, số giờ chuẩn mỗi ngày, quy tắc quy đổi giờ sang ngày công, ngày nghỉ tuần và ngày nghỉ theo HR policy áp dụng. Employee Work Calendar có thể consume Company Business Calendar tại BR-ORG-004 làm baseline business day/Company holiday, sau đó bổ sung lịch theo Branch/Plant/employee group/Employee Assignment và HR-specific policy. Nếu một loại ngày nghỉ bắt buộc được xác lập từ legal/regulatory baseline, controlled source reference phải được đăng ký trước approval của rule tương ứng."
success_criteria:
  - "Lịch sử hợp đồng, điều chuyển và trạng thái làm việc được bảo toàn và truy về Employee/Employee Assignment canonical."
  - "Không tồn tại Employee master riêng trong HRP cạnh tranh với Employee domain."
  - "Mỗi ngày trong kỳ chấm công xác định được là ngày làm việc tiêu chuẩn, ngày nghỉ tuần hoặc ngày nghỉ theo Employee Work Calendar/policy có hiệu lực."
  - "Mỗi ngày nghỉ theo lịch có tên, loại, phạm vi áp dụng và thời gian hiệu lực; ngày nghỉ hợp lệ không bị hiển thị như ngày chưa chấm công."
  - "Số giờ và ngày công tiêu chuẩn của kỳ được xác định từ lịch làm việc có hiệu lực của Employee Assignment."
  - "Khi consume Company Business Calendar, HR-specific override/bổ sung không tạo một định nghĩa Company business day cạnh tranh ngoài phạm vi HR."
depends_on:
  - "BR-EMP-001"
  - "BR-EMP-002"
  - "BR-ORG-002"
  - "BR-ORG-004"
planned_implemented_by:
  - "FR-HRP-001"
implemented_by: []
planned_verified_by:
  - "UAT-HRP-001"
verified_by: []
```
```yaml
id: "BR-HRP-002"
title: "Quản lý attendance, leave, overtime và monthly timesheet"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Cho phép nhân viên tự ghi nhận chấm công theo từng ngày bằng số giờ làm việc và nội dung công việc đã thực hiện. Bản ghi công việc hằng ngày là một căn cứ để tổng hợp ngày công, đồng thời phải giữ được người ghi, ngày làm việc, thời điểm ghi hoặc sửa và lịch sử thay đổi khi có điều chỉnh. Cho phép nhân viên tự gửi yêu cầu nghỉ theo ngày hoặc một phần ngày, lựa chọn loại nghỉ, lý do và thông tin cần thiết; người quản lý có thẩm quyền xem xét và phê duyệt hoặc từ chối. Yêu cầu nghỉ đã được phê duyệt phải tự động trở thành căn cứ chấm công, đồng thời hệ thống quản lý số dư phép, nghỉ có lương/không lương và chuyển số dư theo chính sách. Quản lý tăng ca theo loại, hệ số, giới hạn và phê duyệt. Số giờ nhân viên tự ghi nhận trong báo cáo công việc hằng ngày chỉ phản ánh thời gian làm việc thực tế và không tự động trở thành giờ tăng ca; giờ tăng ca chỉ có hiệu lực khi đáp ứng chính sách và được phê duyệt. Tổng hợp bảng chấm công tháng theo từng nhân viên từ lịch làm việc, bản ghi thời gian và báo cáo công việc do nhân viên tự ghi nhận, yêu cầu nghỉ đã được phê duyệt và giờ tăng ca đã được phê duyệt. Bảng chấm công phải thể hiện rõ trạng thái của từng ngày và có cơ chế khóa kỳ để làm nguồn chính thức cho Payroll."
success_criteria:
  - "Nhân viên có thể ghi nhận ngày làm việc, số giờ làm và mô tả công việc đã thực hiện theo chính sách áp dụng."
  - "Hệ thống phân biệt rõ ngày đã có bản ghi chấm công, ngày chưa chấm công, ngày nghỉ theo lịch và ngày nghỉ phép đã được phê duyệt."
  - "Số giờ làm việc được quy đổi sang ngày công theo lịch làm việc và chính sách có hiệu lực của nhân viên."
  - "Việc sửa bản ghi sau thời hạn cho phép hoặc sau khi kỳ đã khóa phải được kiểm soát và giữ lịch sử."
  - "Số giờ do nhân viên tự ghi nhận không tự động được xem là giờ tăng ca được duyệt."
  - "Mỗi yêu cầu nghỉ xác định được nhân viên, khoảng thời gian, loại nghỉ, lý do, trạng thái, người phê duyệt và thời điểm quyết định."
  - "Yêu cầu nghỉ đã được phê duyệt tự động phản ánh vào bảng chấm công; nhân viên không phải tạo thêm bản ghi chấm công cho cùng khoảng nghỉ."
  - "Chỉ yêu cầu nghỉ có hiệu lực và đã được phê duyệt mới được tính là nghỉ hợp lệ; yêu cầu bị từ chối hoặc hủy không được tính công nghỉ."
  - "Tác động đến số dư phép trước và sau giao dịch có thể truy vết được."
  - "Điều chỉnh hoặc hủy yêu cầu sau khi đã phê duyệt phải giữ lịch sử quyết định."
  - "Chỉ giờ tăng ca hợp lệ và đã được phê duyệt mới được tính vào bảng chấm công và kỳ lương."
  - "Số giờ báo cáo công việc vượt giờ chuẩn không tự động tạo hoặc làm tăng số giờ tăng ca được duyệt."
  - "Giờ tăng ca có thể truy về ngày làm việc, loại tăng ca, hệ số, người phê duyệt và quyết định áp dụng."
  - "Mỗi ngày trong bảng chấm công thể hiện được trạng thái phù hợp như đã chấm công, chưa chấm công, nghỉ phép có lương, nghỉ phép không lương, ngày nghỉ tuần, ngày nghỉ theo work-calendar/policy hoặc ngoại lệ đã được phê duyệt."
  - "Bảng tổng hợp thể hiện tối thiểu số ngày có đi làm, tổng số giờ làm, số ngày công quy đổi, ngày công tiêu chuẩn, nghỉ có lương, nghỉ không lương, giờ tăng ca đã duyệt, ngày nghỉ theo lịch và số ngày còn thiếu chấm công."
  - "Số ngày có đi làm và số ngày công quy đổi được quản lý như hai chỉ tiêu riêng và có thể giải thích theo dữ liệu nguồn."
  - "Ngày nghỉ hợp lệ theo lịch hoặc yêu cầu nghỉ đã được phê duyệt không bị xem là ngày chưa chấm công."
  - "Chỉ bảng chấm công đã khóa mới được dùng làm nguồn chính thức cho Payroll; thay đổi sau khi khóa phải được kiểm soát và giữ lịch sử."
depends_on:
  - "BR-HRP-001"
  - "BR-CTL-004"
planned_implemented_by:
  - "FR-HRP-002"
implemented_by: []
planned_verified_by:
  - "UAT-HRP-002"
verified_by: []
```
```yaml
id: "BR-HRP-003"
title: "Quản lý compensation và payroll lifecycle"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Quản lý thành phần lương, phụ cấp, thưởng, phạt, khấu trừ và ngày hiệu lực. Tính, xem xét, phê duyệt và khóa kỳ lương theo lịch đã công bố. Payroll phải sử dụng bảng chấm công tháng đã được tổng hợp và khóa làm nguồn chính thức cho ngày công, giờ công, nghỉ có lương, nghỉ không lương, tăng ca đã duyệt, ngày nghỉ lễ/chính sách và các điều chỉnh được phê duyệt; không tự suy diễn trực tiếp từ bản ghi chấm công thô. Quản lý điều chỉnh hồi tố, truy lĩnh, thôi việc, chuyển đơn vị và kỳ lương ngoài lịch."
success_criteria:
  - "Mỗi thay đổi có phiên bản, chủ sở hữu và lý do."
  - "Kỳ lương chỉ được tính từ bảng chấm công tháng đã khóa và các thành phần lương có hiệu lực."
  - "Kỳ lương chỉ được phát hành sau khi tổng lương, khấu trừ, thực nhận và ngoại lệ được xác nhận."
  - "Chỉ payroll period đã khóa mới được xem là số liệu lương chính thức; bản chưa khóa phải được nhận diện là đang xử lý/chưa chính thức."
  - "Có thể truy từ kết quả lương về bảng chấm công tháng và các căn cứ chấm công liên quan."
  - "Điều chỉnh liên kết kỳ gốc, có người duyệt và không sửa bản ghi công thô."
depends_on:
  - "BR-HRP-001"
  - "BR-HRP-002"
  - "BR-CTL-004"
planned_implemented_by:
  - "FR-HRP-003"
implemented_by: []
planned_verified_by:
  - "UAT-HRP-003"
verified_by: []
```
```yaml
id: "BR-HRP-004"
title: "Phân bổ labor cost theo target nghiệp vụ"
type: business
priority: SHOULD
target_phase: "P3_ADVANCED"
description: "Phân bổ chi phí nhân công cho Department, Project hoặc Production Order khi chính sách áp dụng. Các allocation target là conditional độc lập: Project chỉ yêu cầu BR-ORG-006 khi phân bổ theo Project; Production Order chỉ yêu cầu Manufacturing capability khi phân bổ theo Production Order; Department allocation không phụ thuộc Project hoặc Manufacturing."
success_criteria:
  - "Tổng phân bổ khớp tổng chi phí nhân công đã duyệt."
  - "Mỗi allocation line xác định được target type, target reference, giá trị/phương pháp phân bổ và chỉ yêu cầu capability của target đang được sử dụng."
depends_on:
  - "BR-HRP-003"
planned_implemented_by:
  - "FR-HRP-004"
implemented_by: []
planned_verified_by:
  - "UAT-HRP-004"
verified_by: []
```
### 4.22 Document & Information

```yaml
id: "BR-DOC-001"
title: "Gắn, tìm kiếm và truy cập tài liệu nghiệp vụ"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Gắn tài liệu, hình ảnh, biên bản, báo giá, hợp đồng, hóa đơn, chứng nhận, ECR/ECN/ECO, BOM, bản vẽ, tiêu chuẩn và bằng chứng giao vào đối tượng nghiệp vụ liên quan. Gắn thẻ và tra cứu thống nhất theo mã, tên, tham chiếu, đối tác, lô, serial và chứng từ."
success_criteria:
  - "Người có quyền truy cập đối tượng xem được tài liệu đúng phạm vi và biết tài liệu nào là căn cứ hiện hành."
  - "Kết quả tra cứu không vượt phạm vi quyền và có thể mở về bản ghi nguồn."
depends_on:
  - "BR-IAM-003"
  - "BR-ORG-002"
planned_implemented_by:
  - "FR-DOC-001"
implemented_by: []
planned_verified_by:
  - "UAT-DOC-001"
verified_by: []
```
```yaml
id: "BR-DOC-002"
title: "Quản lý version và lifecycle tài liệu"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý phiên bản, ngày hiệu lực, người sở hữu, trạng thái và thời hạn của tài liệu sản phẩm, tài liệu chất lượng, tài liệu sản xuất và tài liệu thương mại. Quản lý vòng đời tài liệu và chứng từ từ Nháp, Xem xét, Phê duyệt, Phát hành, Sửa đổi, Lưu trữ đến Hết hiệu lực/Không còn áp dụng; mỗi phiên bản phải giữ được quan hệ truy xuất với phiên bản trước và tài liệu liên quan. Bước business approval thuộc vòng đời này sử dụng Shared Workflow khi áp dụng."
success_criteria:
  - "Phiên bản cũ được giữ để tra cứu, không bị nhầm là phiên bản hiện hành và liên kết được với thay đổi liên quan."
  - "Có thể xác định phiên bản hiện hành, lịch sử sửa đổi, người chịu trách nhiệm, thời điểm chuyển trạng thái và lý do tài liệu hết hiệu lực."
  - "Tài liệu thuộc diện phải phê duyệt chỉ được phát hành khi workflow tương ứng cho phép."
depends_on:
  - "BR-DOC-001"
  - "BR-CTL-004"
planned_implemented_by:
  - "FR-DOC-002"
implemented_by: []
planned_verified_by:
  - "UAT-DOC-002"
verified_by: []
```
```yaml
id: "BR-DOC-003"
title: "Phân loại và kiểm soát phân phối tài liệu"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Phân loại tài liệu theo mức độ công khai, nội bộ, mật hoặc hạn chế. Kiểm soát việc chia sẻ, in hoặc xuất tài liệu hạn chế bằng phân loại, dấu nhận biết, người nhận và thời hạn sử dụng."
success_criteria:
  - "Việc xem, chia sẻ và xuất tài liệu tuân theo phân loại."
  - "Có thể xác định tài liệu đã được cấp cho ai, mục đích nào và đã thu hồi hay chưa."
depends_on:
  - "BR-DOC-001"
  - "BR-IAM-005"
planned_implemented_by:
  - "FR-DOC-003"
implemented_by: []
planned_verified_by:
  - "UAT-DOC-003"
verified_by: []
```
```yaml
id: "BR-DOC-004"
title: "Quản lý retention hold và nghĩa vụ lưu giữ"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Giữ tài liệu thuộc retention hold, tranh chấp, kiểm toán, thuế, lương, chất lượng hoặc nghĩa vụ lưu giữ được phê duyệt trong thời hạn áp dụng. Legal/regulatory minimum cụ thể chỉ normative khi requirement/policy liên quan có controlled source reference tương ứng."
success_criteria:
  - "Tài liệu đang thuộc retention hold không bị xóa hoặc thay thế trái policy; hold xác định được căn cứ, phạm vi, người có thẩm quyền và thời điểm release khi áp dụng."
depends_on:
  - "BR-DOC-002"
planned_implemented_by:
  - "FR-DOC-004"
implemented_by: []
planned_verified_by:
  - "UAT-DOC-004"
verified_by: []
```
```yaml
id: "BR-DOC-005"
title: "Quản lý trao đổi và notification nghiệp vụ"
type: business
priority: SHOULD
target_phase: "P3_ADVANCED"
description: "Trao đổi và ghi nhận quyết định liên quan đến chứng từ, sản phẩm, khách hàng, nhà cung cấp và lệnh sản xuất. Nhận thông báo nghiệp vụ cần xử lý từ Shared Workflow, ngoại lệ, tồn thấp, công nợ quá hạn, giao thất bại và kỳ cần khóa. Khả năng trao đổi/thảo luận là capability bổ sung và không phải hard prerequisite của notification."
success_criteria:
  - "Trao đổi có người tham gia, thời điểm, trạng thái giải quyết và liên kết đối tượng."
  - "Thông báo có người nhận, loại nguồn, mức ưu tiên, thời điểm và trạng thái đã xử lý; thông báo approval liên kết được với workflow tương ứng."
depends_on:
  - "BR-DOC-001"
  - "BR-IAM-003"
  - "BR-CTL-004"
planned_implemented_by:
  - "FR-DOC-005"
implemented_by: []
planned_verified_by:
  - "UAT-DOC-005"
verified_by: []
```
### 4.23 Reporting & BI

```yaml
id: "BR-BI-001"
title: "Cung cấp shared reporting, slicing và drill-through"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Một shared Reporting capability để cung cấp báo cáo cho các domain nằm trong approved scope của phase tương ứng. Các reporting domain dự kiến gồm doanh thu, lợi nhuận, công nợ, tồn kho, mua hàng, bán hàng, sản xuất, chất lượng, thuế, nhân sự và tiền lương; một domain chưa nằm trong approved scope không ngăn Reporting được triển khai cho domain đã sẵn sàng. Reporting phải vận hành được từ dữ liệu ERP nội bộ; external integration chỉ là conditional source khi capability tương ứng có hiệu lực. Xem chỉ số theo thời gian, công ty, chi nhánh, kho, nhà máy, sản phẩm, nhóm hàng, khách hàng, nhà cung cấp, kênh và nhân viên. Biết thời điểm dữ liệu, kỳ cắt, múi giờ, trạng thái hiện tại hay sơ bộ và thời hạn cập nhật của từng báo cáo. Truy từ chỉ số tổng hợp về các giao dịch nguồn trong phạm vi quyền. Khả năng search/tag thống nhất tại BR-DOC-001 có thể hỗ trợ trải nghiệm tra cứu nhưng không phải hard prerequisite của drill-through."
success_criteria:
  - "Mỗi domain nằm trong approved scope có thể định nghĩa report tương ứng với chủ sở hữu, định nghĩa, kỳ, phạm vi và nguồn dữ liệu."
  - "Report chỉ bắt buộc khi source domain/capability của report nằm trong approved scope; không yêu cầu tạo dữ liệu/domain giả chỉ để thỏa reporting framework."
  - "Report mới có thể được bổ sung ở phase sau mà không thay đổi canonical reporting framework."
  - "Reporting không hard-depend một external integration nếu các nguồn ERP nội bộ cần thiết cho report đã tồn tại."
  - "Bộ lọc không làm thay đổi định nghĩa chỉ số và tổng chi tiết khớp tổng."
  - "Báo cáo quá hạn cập nhật được đánh dấu rõ và không bị hiểu là số liệu hiện tại."
  - "Mẫu KPI có thể giải thích đến chứng từ hoặc dữ liệu nguồn."
depends_on:
  - "BR-IAM-003"
  - "BR-ORG-002"
planned_implemented_by:
  - "FR-BI-001"
implemented_by: []
planned_verified_by:
  - "UAT-BI-001"
verified_by: []
```
```yaml
id: "BR-BI-002"
title: "Quản lý KPI definition và operational dashboard"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Quản lý phiên bản định nghĩa KPI, công thức kinh doanh, chủ sở hữu và ngày hiệu lực. Theo dõi các KPI trọng yếu như doanh thu, biên lợi nhuận, độ chính xác tồn, OTIF, tỷ lệ lỗi, vòng quay tồn, DSO, DPO, thời gian đóng kỳ và tỷ lệ áp dụng ERP. OEE là KPI mở rộng trong tương lai và chỉ được sử dụng khi có nguồn dữ liệu MES phù hợp; ERP Core không có trách nhiệm tự tính OEE trước khi MES được triển khai. Theo dõi chỉ tiêu bán hàng theo nhân viên, kênh và khách hàng. Các KPI như biên lợi nhuận, tỷ lệ trả và công nợ được áp dụng khi source capability tương ứng nằm trong approved scope; Finance/Cost/Returns và các source liên quan là conditional sources, không phải Hard Prerequisite của Sales KPI capability. Theo dõi tỷ lệ giao đúng hạn, giao đủ, giao thất bại và thời gian xử lý hoàn."
success_criteria:
  - "Thay đổi KPI tạo phiên bản mới và giữ được kết quả đã công bố trước đó."
  - "Mỗi KPI có ngưỡng, mục tiêu, đơn vị, kỳ và chủ sở hữu."
  - "Khi OEE được sử dụng, báo cáo phải xác định rõ nguồn dữ liệu MES và không diễn giải OEE như KPI do ERP Core tự tính."
  - "Mỗi KPI có định nghĩa, công thức và dữ liệu nguồn xác định được."
  - "Bộ KPI được cung cấp theo subset source capability nằm trong approved scope; source chưa được approved không chặn KPI của các source đã sẵn sàng."
  - "Chỉ số có thời điểm bắt đầu, kết thúc và phạm vi đo."
depends_on:
  - "BR-BI-001"
planned_implemented_by:
  - "FR-BI-002"
implemented_by: []
planned_verified_by:
  - "UAT-BI-002"
verified_by: []
```
```yaml
id: "BR-BI-003"
title: "Quản lý report export và decision snapshot"
type: business
priority: MUST
target_phase: "P3_ADVANCED"
description: "Xuất báo cáo theo định dạng được phê duyệt và kiểm soát người nhận, mục đích sử dụng và dữ liệu nhạy cảm. Chụp và giữ báo cáo đã ký hoặc đã dùng cho quyết định, kiểm toán và đối soát để bảo toàn đúng ngữ cảnh dữ liệu tại thời điểm sử dụng."
success_criteria:
  - "Bản xuất có thời điểm, phạm vi, người xuất và mức phân loại."
  - "Có thể chứng minh báo cáo nào đã được dùng tại thời điểm quyết định và xác định được thời điểm, phạm vi dữ liệu, người công bố/người ký khi áp dụng."
depends_on:
  - "BR-BI-001"
  - "BR-IAM-005"
  - "BR-DOC-002"
planned_implemented_by:
  - "FR-BI-003"
implemented_by: []
planned_verified_by:
  - "UAT-BI-003"
verified_by: []
```
### 4.24 Integration & Data Exchange

```yaml
id: "BR-INT-001"
title: "Trao đổi dữ liệu bên ngoài với reference và evidence"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Tiếp nhận và cung cấp dữ liệu giữa ERP với website, sàn, vận chuyển, ngân hàng, ví điện tử, máy chấm công và hệ thống kế toán được phê duyệt. Bảo toàn mã tham chiếu bên ngoài, thời điểm, trạng thái và bằng chứng trao đổi để phục vụ đối soát."
success_criteria:
  - "Mỗi nguồn có chủ sở hữu, phạm vi, tần suất và cách đối soát."
  - "Có thể đi từ bản ghi ERP đến bằng chứng nguồn và ngược lại."
depends_on:
  - "BR-MDM-001"
planned_implemented_by:
  - "FR-INT-001"
implemented_by: []
planned_verified_by:
  - "UAT-INT-001"
verified_by: []
```
```yaml
id: "BR-INT-002"
title: "Kiểm soát validation, reconciliation và correction dữ liệu trao đổi"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Nhận biết bản ghi đã tiếp nhận, khớp, trùng, thiếu, mâu thuẫn, bị từ chối hoặc chờ xử lý trước khi dữ liệu không hợp lệ trở thành số liệu chính thức. Xử lý sai lệch dữ liệu bằng điều chỉnh có liên kết, không thay thế âm thầm số liệu đã ghi nhận."
success_criteria:
  - "Không có bản ghi thất bại bị bỏ quên mà không có chủ sở hữu."
  - "Bản ghi không hợp lệ, trùng, thiếu hoặc mâu thuẫn được đánh dấu nguyên nhân và trạng thái xử lý trước khi được chấp nhận thành dữ liệu chính thức."
  - "Mọi điều chỉnh có lý do, chủ sở hữu, phê duyệt và tác động được xác định."
depends_on:
  - "BR-INT-001"
  - "BR-CTL-004"
planned_implemented_by:
  - "FR-INT-002"
implemented_by: []
planned_verified_by:
  - "UAT-INT-002"
verified_by: []
```
```yaml
id: "BR-INT-003"
title: "Quản lý bulk import và controlled export"
type: business
priority: MUST
target_phase: "P2_EXTENDED"
description: "Hỗ trợ nhập dữ liệu hàng loạt từ nguồn đã phê duyệt với kiểm soát phạm vi, chủ sở hữu, lỗi và kết quả. Xuất dữ liệu cho vận hành, đối soát, báo cáo hoặc yêu cầu compliance được phê duyệt theo quyền và phân loại dữ liệu. Legal/regulatory export requirement cụ thể chỉ áp dụng khi có controlled source reference tương ứng."
success_criteria:
  - "Bản ghi hợp lệ và không hợp lệ được tách rõ; số lượng tổng được xác nhận."
  - "Dữ liệu xuất không chứa trường ngoài mục đích hoặc phạm vi người nhận."
  - "Nếu export được yêu cầu bởi legal/regulatory baseline, có thể truy được controlled source/policy quyết định phạm vi dữ liệu xuất."
depends_on:
  - "BR-INT-001"
  - "BR-IAM-003"
planned_implemented_by:
  - "FR-INT-003"
implemented_by: []
planned_verified_by:
  - "UAT-INT-003"
verified_by: []
```
## 5. Non-functional Requirements

### 5.1 Security

```yaml
id: "NFR-SEC-001"
title: "Bảo vệ dữ liệu khỏi truy cập, sửa đổi hoặc xuất trái thẩm quyền"
type: non-functional
priority: MUST
target_phase: "P1_CORE"
description: "NFR này áp dụng cho mọi user-facing surface và data-access/export surface thực sự nằm trong approved scope của phase. Capability/surface của phase sau không tạo acceptance obligation cho P1 chỉ vì được nhắc như future consumer; khi capability đó được approved, nó phải inherit cùng authorization/security invariant."
success_criteria:
  - "Mẫu kiểm tra chéo phạm vi trên các capability/surface thuộc approved scope không truy cập được dữ liệu ngoài quyền."
  - "Mọi màn hình, API/data access, report, document hoặc export surface đang nằm trong approved scope áp dụng cùng Resource/Action Permission và final Authorization Evaluation tương ứng."
  - "Report/document/export capability chưa nằm trong approved scope không buộc phải được triển khai giả chỉ để test NFR-SEC-001; khi capability được approved, security test tương ứng trở thành acceptance obligation."
depends_on:
  - "BR-IAM-003"
  - "BR-IAM-005"
planned_implemented_by:
  - "NFS-SEC-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-SEC-001"
verified_by: []
```
### 5.2 Availability & Continuity

```yaml
id: "NFR-AVL-001"
title: "Đảm bảo availability trong khung giờ vận hành"
type: non-functional
priority: MUST
target_phase: "P1_CORE"
description: "Availability được đo trong configured operating window đã công bố cho Company/service scope tương ứng. Operating window, timezone và measurement scope phải được version/control đủ để tái tính chỉ số."
success_criteria:
  - "Monthly availability trong configured operating window đã công bố đạt tối thiểu 99.5%."
  - "Measurement window, timezone, thời điểm bắt đầu/kết thúc downtime và các khoảng planned maintenance được ghi nhận đủ để tái tính availability."
  - "Planned maintenance chỉ được loại khỏi phép đo khi đã được phê duyệt và thông báo trước tối thiểu 48 giờ theo policy; downtime không đáp ứng cả hai điều kiện này được tính vào availability."
  - "Mỗi Company/service scope thuộc acceptance xác định được operating window có hiệu lực dùng cho phép đo; thay đổi operating window có version/effective time và không hồi tố làm thay đổi kết quả đã công bố."
depends_on: []
planned_implemented_by:
  - "NFS-AVL-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-AVL-001"
verified_by: []
```
```yaml
id: "NFR-AVL-002"
title: "Đảm bảo business continuity và disaster recovery"
type: non-functional
priority: MUST
target_phase: "P2_EXTENDED"
description: ""
success_criteria:
  - "Mỗi nghiệp vụ fallback có biểu mẫu/bằng chứng tạm, người chịu trách nhiệm, thời điểm phát sinh và người phê duyệt hoặc xác nhận theo policy."
  - "Sau khi ERP phục hồi, dữ liệu tạm được nhập bù hoặc đồng bộ lại, đánh dấu nguồn phát sinh trong thời gian gián đoạn, đối soát và xác nhận để không tạo giao dịch trùng hoặc mất nghiệp vụ."
  - "Control totals và transaction reconciliation trước/sau khôi phục khớp; mọi chênh lệch phải có nguyên nhân, người chịu trách nhiệm và disposition được phê duyệt."
  - "Master data hoặc policy nhạy cảm không được thay đổi bằng quy trình fallback; trường hợp ngoại lệ chỉ được xử lý bằng cơ chế được phê duyệt riêng và có audit trail."
  - "Fallback và reconciliation có thể được kiểm thử ngay cả khi không có external Data Exchange/Integration trong scope."
  - "Mọi capability trọng yếu được gán recovery tier hoặc recovery target được phê duyệt và có chủ sở hữu xác nhận."
  - "Kết quả kiểm thử khôi phục Tier 1 đạt RPO <= 15 phút và RTO <= 4 giờ."
  - "Kết quả kiểm thử khôi phục Tier 2 đạt RPO <= 4 giờ và RTO <= 8 giờ."
  - "Recovery plan lưu phạm vi, dữ liệu, dependency, người chịu trách nhiệm và kết quả test để chứng minh target đạt được."
depends_on:
  - "NFR-AVL-001"
planned_implemented_by:
  - "NFS-AVL-002"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-AVL-002"
verified_by: []
```
### 5.3 Performance & Scalability

```yaml
id: "NFR-PER-001"
title: "Đáp ứng hiệu năng tương tác vận hành"
type: non-functional
priority: MUST
target_phase: "P1_CORE"
description: "NFS-PER-001 phải đăng ký controlled P1 performance data profile và workload mix để acceptance có thể tái lập. Baseline P1 gồm 100000 Product, 50000 Customer, 10000 Supplier, 500000 Inventory Transaction, 250000 Sales Order, 100000 Purchase Order và 100 concurrent users."
success_criteria:
  - "Tra cứu và điều hướng thông thường đạt p95 response time <= 2 giây dưới workload acceptance."
  - "Tạo hoặc cập nhật giao dịch thông thường đạt p95 response time <= 3 giây dưới workload acceptance."
  - "Truy vấn nghiệp vụ phức tạp đạt p95 response time <= 5 giây dưới workload acceptance."
  - "Performance test sử dụng 100 concurrent users với workload mix được kiểm soát và data profile tối thiểu: 100000 Product, 50000 Customer, 10000 Supplier, 500000 Inventory Transaction, 250000 Sales Order và 100000 Purchase Order."
  - "NFS-PER-001 ghi lại version của dataset/profile, workload mix, test environment và measurement method để cùng baseline có thể được tái kiểm thử."
  - "Tác vụ dự kiến vượt response-time class tương tác có thể chạy async nếu người dùng nhận được trạng thái tiến trình, kết quả và lỗi rõ ràng."
depends_on:
  - "BR-ORG-002"
planned_implemented_by:
  - "NFS-PER-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-PER-001"
verified_by: []
```
```yaml
id: "NFR-PER-002"
title: "Đáp ứng freshness của near-real-time reporting"
type: non-functional
priority: MUST
target_phase: "P2_EXTENDED"
description: ""
success_criteria:
  - "Near-real-time report/dashboard có data age <= 15 phút trong điều kiện vận hành bình thường."
  - "Báo cáo hiển thị data timestamp/cutoff và cảnh báo rõ khi data age vượt 15 phút."
  - "Report không được phân loại near-real-time nếu refresh/cutoff được quản lý theo publication period khác."
  - "Requirement này không thay thế source/domain freshness target tại NFR-FRESH-001; test evidence phải phân biệt source timestamp và report/dashboard publication timestamp."
depends_on:
  - "BR-BI-001"
planned_implemented_by:
  - "NFS-PER-002"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-PER-002"
verified_by: []
```
```yaml
id: "NFR-PER-003"
title: "Đáp ứng capacity và scalability baseline"
type: non-functional
priority: SHOULD
target_phase: "P2_EXTENDED"
description: ""
success_criteria:
  - "Có capacity assessment cho planning horizon 3 năm trên baseline 10 Company, 100 concurrent users, 100000 Product và 1000000 business transactions/năm."
  - "Tại baseline capacity, các kiểm soát dữ liệu, reconciliation và isolation vẫn hoạt động đúng."
  - "Tại workload acceptance liên quan, performance bắt buộc tiếp tục được đánh giá theo NFR-PER-001; bottleneck và headroom được ghi nhận trong NFS/capacity report."
depends_on:
  - "BR-ORG-002"
  - "NFR-PER-001"
planned_implemented_by:
  - "NFS-PER-003"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-PER-003"
verified_by: []
```
### 5.4 Auditability

```yaml
id: "NFR-AUD-001"
title: "Bảo toàn, truy vấn và tái lập audit evidence"
type: non-functional
priority: MUST
target_phase: "P1_CORE"
description: "NFR này áp dụng cho audit evidence của capability thực sự nằm trong approved scope. Trước khi enterprise retention capability tại NFR-CMP-001 nằm trong approved scope, NFS-AUD-001 phải khai báo controlled P1 audit-evidence retention baseline dùng cho acceptance. Khi NFR-CMP-001 được approved, retention schedule tương ứng trở thành governing retention context."
success_criteria:
  - "Không thể sửa hoặc xóa audit evidence bằng luồng nghiệp vụ thông thường."
  - "Mọi thay đổi/quản trị đặc quyền được policy cho phép đối với audit evidence xác định được actor, thời điểm, lý do và kết quả."
  - "Kiểm thử integrity phát hiện được mẫu thay đổi trái phép hoặc thiếu chuỗi bằng chứng theo tiêu chí kiểm thử NFR tương ứng."
  - "Bằng chứng trước/sau và liên kết tới đối tượng cũ/mới vẫn truy cập được sau khi hủy, thay thế hoặc điều chỉnh trong retention baseline/schedule đang có hiệu lực."
  - "Không có bước xử lý ngoại lệ hợp lệ nào làm mất audit history đã phát sinh trước đó."
  - "Từ mẫu giao dịch và, khi capability tương ứng nằm trong approved scope, mẫu report/decision reference có thể truy ra chuỗi audit evidence và tái lập được ngữ cảnh gồm thời điểm, phạm vi và actor liên quan."
  - "Evidence vẫn queryable trong toàn bộ retention baseline/schedule áp dụng và không phụ thuộc vào việc bản ghi nghiệp vụ còn ở trạng thái hiện hành."
  - "Capability/report/decision surface chưa nằm trong approved scope không buộc phải được tạo giả chỉ để tạo audit evidence cho NFR này."
depends_on:
  - "BR-AUD-001"
references:
  - "NFR-CMP-001"
planned_implemented_by:
  - "NFS-AUD-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-AUD-001"
verified_by: []
```
### 5.5 Data Integrity

```yaml
id: "NFR-DAT-001"
title: "Bảo toàn precision và rounding của dữ liệu định lượng"
type: non-functional
priority: MUST
target_phase: "P1_CORE"
description: ""
success_criteria:
  - "Quantity hỗ trợ tối thiểu 6 decimal places khi product/UOM policy yêu cầu và không làm mất giá trị nguồn trước bước rounding được công bố."
  - "Unit price, exchange rate và rate khác giữ đủ internal precision để kết quả posting sau cùng đáp ứng currency/financial rounding policy."
  - "Monetary posting được làm tròn theo minor unit của currency áp dụng; số tiền gốc và giá trị trước/sau rounding có thể giải thích khi cần đối soát."
  - "Khi tax capability nằm trong approved scope, tax calculation tuân thủ effective tax-rounding policy do Finance quản lý tại BR-FIN-004 và có thể truy vết policy áp dụng."
  - "Khi Payroll hoặc capability chịu quy tắc pháp lý chuyên biệt nằm trong approved scope, số liệu tương ứng tuân theo policy chuyên biệt đang có hiệu lực; capability chưa approved không tạo acceptance obligation cho P1."
  - "Reconciliation tolerance mặc định không vượt quá 1 minor unit của currency trên một chứng từ, trừ khi policy chuyên biệt được phê duyệt quy định khác."
depends_on:
  - "BR-MDM-001"
  - "BR-FIN-005"
planned_implemented_by:
  - "NFS-DAT-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-DAT-001"
verified_by: []
```
### 5.6 Usability & Localization

```yaml
id: "NFR-USE-001"
title: "Đảm bảo usability, localization và semantic consistency"
type: non-functional
priority: MUST
target_phase: "P1_CORE"
description: ""
success_criteria:
  - "Người dùng đại diện xác nhận thuật ngữ và đơn vị hiển thị."
  - "Thuật ngữ nghiệp vụ có bảng chuẩn hóa và người sở hữu."
  - "Thay đổi locale/display setting không làm đổi business timestamp, kỳ nghiệp vụ, business currency hoặc monetary value đã ghi nhận."
  - "Cùng một transaction có thể hiển thị theo locale được phép nhưng vẫn truy được canonical business timestamp/currency/value."
  - "Khi HR work calendar áp dụng, ngày làm việc/ngày nghỉ được xác định từ work-calendar policy có hiệu lực và không thay đổi chỉ do user locale/display setting."
depends_on:
  - "BR-CTL-001"
  - "BR-ORG-004"
  - "BR-SYS-001"
planned_implemented_by:
  - "NFS-USE-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-USE-001"
verified_by: []
```
### 5.7 Maintainability & Configurability

```yaml
id: "NFR-MNT-001"
title: "Hỗ trợ configuration và mở rộng tổ chức mà không nhân bản dữ liệu"
type: non-functional
priority: MUST
target_phase: "P2_EXTENDED"
description: ""
success_criteria:
  - "Với mẫu configuration thuộc danh mục configurable đã phê duyệt, người có thẩm quyền có thể thay đổi giá trị/rule bằng configuration mechanism mà không sửa source code."
  - "Thay đổi configuration trong phạm vi hỗ trợ không yêu cầu redeploy application để có hiệu lực theo effective policy."
  - "Configuration change vẫn bảo toàn owner, effective date, approval/history theo business requirement tương ứng."
  - "Đơn vị mới có thể dùng dữ liệu chuẩn được chia sẻ theo quyền."
depends_on:
  - "BR-ORG-005"
  - "BR-ORG-002"
planned_implemented_by:
  - "NFS-MNT-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-MNT-001"
verified_by: []
```
### 5.8 Compliance & Retention

```yaml
id: "NFR-CMP-001"
title: "Đảm bảo retention và cung cấp compliance evidence"
type: non-functional
priority: MUST
target_phase: "P2_EXTENDED"
description: ""
success_criteria:
  - "Mỗi record class có retention category, retention period, mốc bắt đầu tính, chủ sở hữu và điều kiện hủy được cấu hình/phê duyệt trong retention schedule."
  - "Đối với hồ sơ thuộc phạm vi kế toán Việt Nam, retention schedule không thấp hơn baseline 5 năm, 10 năm hoặc vĩnh viễn theo phân loại pháp lý tương ứng."
  - "Hồ sơ quality, giao nhận, hợp đồng hoặc hồ sơ khác không tự động được gán cùng một mốc; hệ thống áp dụng max(applicable legal minimum, approved internal retention policy) theo record class."
  - "Hồ sơ đang legal hold, tranh chấp, kiểm toán hoặc điều tra không được hủy cho đến khi hold được giải phóng theo thẩm quyền."
  - "Mỗi legal hold xác định được thời điểm bắt đầu, căn cứ/người có thẩm quyền, phạm vi hồ sơ và bằng chứng giải phóng hold khi kết thúc."
  - "Việc thay đổi retention policy có version, ngày hiệu lực, căn cứ và không làm rút ngắn trái phép thời hạn của hồ sơ đã phát sinh."
  - "Bộ hồ sơ mẫu được xác nhận về tính đầy đủ và phạm vi."
depends_on:
  - "BR-DOC-002"
  - "BR-DOC-004"
  - "NFR-AUD-001"
source_refs: []
planned_implemented_by:
  - "NFS-CMP-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-CMP-001"
verified_by: []
```
### 5.9 Data Freshness

```yaml
id: "NFR-FRESH-001"
title: "Đảm bảo freshness theo từng domain nghiệp vụ"
type: non-functional
priority: MUST
target_phase: "P2_EXTENDED"
description: "Mỗi success criterion chỉ trở thành acceptance obligation khi source capability tương ứng nằm trong approved scope của phase. Capability chưa nằm trong approved scope không tạo Hard Prerequisite cho NFR này và không chặn acceptance của các freshness class đã áp dụng."
success_criteria:
  - "Các dataset được phân loại vào freshness class `FRESH-5M` phải có data age <= 5 phút trong thời gian vận hành bình thường. NFS-FRESH-001 phải liệt kê cụ thể dataset/source thuộc `FRESH-5M`, owner, source timestamp, measurement point và cách tính data age. Không được suy diễn một dataset thuộc `FRESH-5M` nếu chưa được đăng ký trong verification scope."
  - "Dữ liệu tiền/giao dịch thu-chi và số dư AR/AP vận hành có data age <= 15 phút trong điều kiện vận hành bình thường."
  - "Báo cáo đối soát theo batch, closing hoặc báo cáo kỳ hiển thị published cutoff/publication period và không bị diễn giải là near-real-time khi chưa đến kỳ công bố."
  - "Mỗi báo cáo tài chính vận hành hiển thị data timestamp/cutoff và chủ sở hữu dữ liệu để người dùng xác định độ mới của số liệu."
  - "Material issue/receipt, production quantity, WIP movement, scrap và quality status phản ánh dữ liệu mới khi transaction tương ứng được ghi nhận chính thức."
  - "Shift summary/KPI được cập nhật hoặc công bố không quá 30 phút sau thời điểm kết thúc ca."
  - "Mỗi màn hình/báo cáo liên quan hiển thị timestamp hoặc cutoff đủ để nhận biết độ mới của dữ liệu."
  - "Báo cáo quản trị tháng có vào business day tiếp theo sau khi chốt kỳ theo BR-ORG-004; chủ sở hữu: Giám đốc tài chính/Thông tin quản trị."
depends_on: []
applies_to:
  - "BR-INV-001"
  - "BR-SAL-001"
  - "BR-FIN-001"
  - "BR-FIN-009"
  - "BR-FIN-003"
  - "BR-MFG-003"
  - "BR-MFG-004"
  - "BR-BI-001"
  - "BR-FIN-006"
  - "BR-ORG-004"
planned_implemented_by:
  - "NFS-FRESH-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-FRESH-001"
verified_by: []
```
### 5.10 Application Platform

```yaml
id: "NFR-PLT-001"
title: "Cung cấp ERP trên nền tảng web desktop"
type: non-functional
priority: MUST
target_phase: "P1_CORE"
description: ""
success_criteria:
  - "Các chức năng thuộc phạm vi ERP vận hành trên Google Chrome và Microsoft Edge desktop ở current stable và 2 major versions gần nhất tại thời điểm acceptance."
  - "Acceptance test chạy trên Windows 10/11 hoặc Windows desktop version còn nằm trong support baseline của Dinktech."
  - "Không coi mobile/tablet là môi trường được hỗ trợ chính thức nếu chưa có requirement riêng phê duyệt phạm vi đó."
  - "Danh sách browser/OS version cụ thể dùng cho mỗi đợt acceptance được ghi lại trong artifact kiểm thử NFR để tái kiểm thử."
depends_on: []
planned_implemented_by:
  - "NFS-PLT-001"
implemented_by: []
planned_verified_by:
  - "NFR-TEST-PLT-001"
verified_by: []
```
## 6. Summary

| Controlled Item | Count |
|---|---:|
| Business Requirements | 95 |
| Non-functional Requirements | 13 |
| Architectural Rules | 3 |
| **Total** | **111** |

### 6.1 Functional domain summary

| Functional Domain | Business Requirements |
|---|---:|
| Organization & Enterprise Structure | 6 |
| Employee & Organizational Structure | 2 |
| Authorization & Security | 6 |
| Cross-domain Controls & Workflow | 4 |
| Audit & Traceability | 1 |
| System Foundation | 1 |
| Operational Readiness | 1 |
| Master Data & Data Governance | 4 |
| Product | 5 |
| PLM & Engineering | 3 |
| Customer & CRM | 4 |
| Pricing & Promotion | 2 |
| Supplier | 3 |
| Purchase | 4 |
| Inventory | 6 |
| Manufacturing | 7 |
| Sales | 5 |
| Finance Operations | 9 |
| Logistics & Delivery | 3 |
| Commerce / Omnichannel / POS | 4 |
| HCM & Payroll | 4 |
| Document & Information | 5 |
| Reporting & BI | 3 |
| Integration & Data Exchange | 3 |

### 6.2 NFR domain summary

| NFR Domain | Requirements |
|---|---:|
| Security | 1 |
| Availability & Continuity | 2 |
| Performance & Scalability | 3 |
| Auditability | 1 |
| Data Integrity | 1 |
| Usability & Localization | 1 |
| Maintainability & Configurability | 1 |
| Compliance & Retention | 1 |
| Data Freshness | 1 |
| Application Platform | 1 |

## 7. Phase Planning Baseline

`target_phase` đã được đồng bộ từ approved `PLAN-ERP-001 v1.0.1` (`03_Phase_Planning_v1.0.1.md`) theo dependency closure đã được kiểm tra. Allocation hiện hành: `P1_CORE` 42 controlled items, `P2_EXTENDED` 51, `P3_ADVANCED` 18 và `FUTURE` 0. Bước tiếp theo là decomposition P1 sang FRS/NFS/DES/UAT/NFR Verification Specification; actual downstream traceability chỉ được cập nhật khi artifact tương ứng thực sự tồn tại dưới configuration control.

## 8. Revision Notes

### v1.0.2 — Approved Phase Planning Synchronization and P1 Core Refinement

- Synchronized all 111 `target_phase` values from approved `PLAN-ERP-001 v1.0.1`.
- Phase allocation: `P1_CORE` 42, `P2_EXTENDED` 51, `P3_ADVANCED` 18, `FUTURE` 0.
- Replaced the pending Phase Planning Gate with the approved Phase Planning Baseline.
- P1 Core requirements were refined from the approved Q1-Q27 decision baseline without changing any `target_phase` allocation or priority.
- Added explicit forward-reference/conditional-applicability convention: later-phase items may be referenced, but may not be hard dependencies or mandatory runtime obligations for P1.
- Clarified Organization calendar vs financial-period ownership; independent Organization Context Hierarchy Policy; IAM sensitive-data terminology; Central Audit dependency; internal approved `provinces`/`wards` reference data; UOM invariants; Pricing conditional Customer/Workflow relations; P1 ATP; P1 availability/performance baselines; and conditional NFR applicability.
- Added Group-level Product Moving Average Cost as a system-derived P1 operational cost baseline while keeping configurable Inventory Valuation Policy in P2; clarified P1 COMBO/GIFT as classification-only until BR-PRD-004 is in scope.
- Reworked Purchase Receiving, Inventory, Sales and FX acceptance semantics to remove implicit obligations on later-phase capabilities.
- Planned downstream traceability identifiers remain planning references only; actual traceability fields remain empty until controlled downstream artifacts exist.

### v1.0.1 — Consistency and Traceability Cleanup

- Clarified planned vs actual downstream traceability.
- Normalized empty dependency representation.
- Added typed non-hard traceability relations: `governed_by`, `references`, `applies_to`.
- Added controlled external `source_refs` contract.
- Corrected ownership terminology for Transaction Data.
- Corrected Pricing cross-reference duplication.
- Removed duplicated Organization acceptance criteria.
- Split Accounts Receivable and Accounts Payable into independently traceable business capabilities.
- Refined conditional applicability of Data Freshness NFR.
- Improved Organization hierarchy and freshness testability.
- No target phase assigned; Phase Planning remains the next lifecycle gate.
