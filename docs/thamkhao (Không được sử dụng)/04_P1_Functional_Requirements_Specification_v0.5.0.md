---
document_id: "FRS-ERP-P1-001"
title: "Functional Requirements Specification - Dinktech Mini ERP V2.0 - P1 Core"
phase: "Functional Requirements Definition"
status: "draft"
version: "0.5.0"
owner: "TBD"
authority: "Draft downstream decomposition of REQ-ERP-001 P1_CORE; becomes controlled implementation evidence only after approval/configuration control."
source_baseline: "REQ-ERP-001 v1.0.2"
target_phase: "P1_CORE"
---

# Functional Requirements Specification - Dinktech Mini ERP V2.0 - P1 Core

## 1. Document Contract

Tài liệu này là bản decomposition chức năng đầu tiên cho phạm vi `P1_CORE` của `REQ-ERP-001 v1.0.2`. Nội dung chỉ được suy ra từ User Requirements nguồn; không tự mở rộng capability, dependency hoặc acceptance obligation ngoài baseline nguồn.

- `BR-*` thuộc P1 được phân rã thành `FR-*` theo `planned_implemented_by` đã khai báo trong User Requirements.
- `AR-*` thuộc P1 không được chuyển thành Functional Requirement; chúng được route sang Design/Architecture realization (`DES-*`) và được liệt kê trong tài liệu này để bảo toàn scope/governance.
- `NFR-*` thuộc P1 không được chuyển thành Functional Requirement; chúng được route sang Non-functional Specification (`NFS-*`) và NFR Verification Specification (`NFR-TEST-*`).
- `depends_on` giữ nguyên ý nghĩa Hard Prerequisite từ User Requirements. `governed_by`, `references` và `applies_to` không được biến thành lifecycle dependency.
- Forward reference tới capability phase sau vẫn là conditional reference; không tạo runtime/acceptance obligation cho P1 nếu capability đó chưa nằm trong approved scope.
- Các child ID dạng `FR-XXX-NNN.nn` trong draft này là decomposition handle nội bộ của FRS. Chúng chỉ trở thành controlled functional requirement sau khi FRS được approved/configuration-controlled.
- User Requirements chỉ nên chuyển reference từ `planned_implemented_by` sang `implemented_by` sau khi FRS này được approved và đưa dưới configuration control.

## 2. P1 Scope Baseline

P1 Core gồm **42 controlled items**: **32 Business Requirements**, **7 Non-functional Requirements** và **3 Architectural Rules**. FRS này trực tiếp đặc tả 32 Business Requirements; 10 item còn lại được giữ trong companion-routing matrix để không mất scope.

| Controlled Item Type | P1 Count | Downstream Artifact Class |
|---|---:|---|
| Business Requirement | 32 | `FR-*` in this FRS |
| Non-functional Requirement | 7 | `NFS-*` + `NFR-TEST-*` |
| Architectural Rule | 3 | `DES-*` + `DES-REVIEW-*` |
| **Total** | **42** |  |

### 2.1 Functional domain allocation

| Source Domain | P1 BR Count |
|---|---:|
| 4.1 Organization & Enterprise Structure | 3 |
| 4.2 Employee & Organizational Structure | 2 |
| 4.3 Authorization & Security | 4 |
| 4.4 Cross-domain Controls & Workflow | 3 |
| 4.5 Audit & Traceability | 1 |
| 4.6 System Foundation | 1 |
| 4.8 Master Data & Data Governance | 3 |
| 4.9 Product | 3 |
| 4.11 Customer & CRM | 1 |
| 4.12 Pricing & Promotion | 1 |
| 4.13 Supplier | 1 |
| 4.14 Purchase | 3 |
| 4.15 Inventory | 3 |
| 4.17 Sales | 2 |
| 4.18 Finance Operations | 1 |

### 2.2 P1 functional traceability matrix

| Upstream BR | Functional Artifact | Planned UAT | Source Domain |
|---|---|---|---|
| `BR-ORG-001` | `FR-ORG-001` | `UAT-ORG-001` | 4.1 Organization & Enterprise Structure |
| `BR-ORG-002` | `FR-ORG-002` | `UAT-ORG-002` | 4.1 Organization & Enterprise Structure |
| `BR-ORG-004` | `FR-ORG-004` | `UAT-ORG-004` | 4.1 Organization & Enterprise Structure |
| `BR-EMP-001` | `FR-EMP-001` | `UAT-EMP-001` | 4.2 Employee & Organizational Structure |
| `BR-EMP-002` | `FR-EMP-002` | `UAT-EMP-002` | 4.2 Employee & Organizational Structure |
| `BR-IAM-001` | `FR-IAM-001` | `UAT-IAM-001` | 4.3 Authorization & Security |
| `BR-IAM-002` | `FR-IAM-002` | `UAT-IAM-002` | 4.3 Authorization & Security |
| `BR-IAM-003` | `FR-IAM-003` | `UAT-IAM-003` | 4.3 Authorization & Security |
| `BR-IAM-005` | `FR-IAM-005` | `UAT-IAM-005` | 4.3 Authorization & Security |
| `BR-CTL-001` | `FR-CTL-001` | `UAT-CTL-001` | 4.4 Cross-domain Controls & Workflow |
| `BR-CTL-002` | `FR-CTL-002` | `UAT-CTL-002` | 4.4 Cross-domain Controls & Workflow |
| `BR-CTL-004` | `FR-CTL-004` | `UAT-CTL-004` | 4.4 Cross-domain Controls & Workflow |
| `BR-AUD-001` | `FR-AUD-001` | `UAT-AUD-001` | 4.5 Audit & Traceability |
| `BR-SYS-001` | `FR-SYS-001` | `UAT-SYS-001` | 4.6 System Foundation |
| `BR-MDM-001` | `FR-MDM-001` | `UAT-MDM-001` | 4.8 Master Data & Data Governance |
| `BR-MDM-002` | `FR-MDM-002` | `UAT-MDM-002` | 4.8 Master Data & Data Governance |
| `BR-MDM-003` | `FR-MDM-003` | `UAT-MDM-003` | 4.8 Master Data & Data Governance |
| `BR-PRD-001` | `FR-PRD-001` | `UAT-PRD-001` | 4.9 Product |
| `BR-PRD-002` | `FR-PRD-002` | `UAT-PRD-002` | 4.9 Product |
| `BR-PRD-005` | `FR-PRD-005` | `UAT-PRD-005` | 4.9 Product |
| `BR-CUS-001` | `FR-CUS-001` | `UAT-CUS-001` | 4.11 Customer & CRM |
| `BR-PRC-001` | `FR-PRC-001` | `UAT-PRC-001` | 4.12 Pricing & Promotion |
| `BR-SUP-001` | `FR-SUP-001` | `UAT-SUP-001` | 4.13 Supplier |
| `BR-PUR-001` | `FR-PUR-001` | `UAT-PUR-001` | 4.14 Purchase |
| `BR-PUR-002` | `FR-PUR-002` | `UAT-PUR-002` | 4.14 Purchase |
| `BR-PUR-003` | `FR-PUR-003` | `UAT-PUR-003` | 4.14 Purchase |
| `BR-INV-001` | `FR-INV-001` | `UAT-INV-001` | 4.15 Inventory |
| `BR-INV-002` | `FR-INV-002` | `UAT-INV-002` | 4.15 Inventory |
| `BR-INV-003` | `FR-INV-003` | `UAT-INV-003` | 4.15 Inventory |
| `BR-SAL-001` | `FR-SAL-001` | `UAT-SAL-001` | 4.17 Sales |
| `BR-SAL-002` | `FR-SAL-002` | `UAT-SAL-002` | 4.17 Sales |
| `BR-FIN-005` | `FR-FIN-005` | `UAT-FIN-005` | 4.18 Finance Operations |

### 2.3 P1 architecture companion routing

| Architectural Rule | Planned Design Realization | Planned Design Review |
|---|---|---|
| `AR-DATA-OWNERSHIP-001` | `DES-AR-DATA-OWNERSHIP-001` | `DES-REVIEW-AR-DATA-OWNERSHIP-001` |
| `AR-DOM-001` | `DES-AR-DOM-001` | `DES-REVIEW-AR-DOM-001` |
| `AR-TXN-001` | `DES-AR-TXN-001` | `DES-REVIEW-AR-TXN-001` |

### 2.4 P1 non-functional companion routing

| NFR | Planned NFS | Planned Verification |
|---|---|---|
| `NFR-SEC-001` | `NFS-SEC-001` | `NFR-TEST-SEC-001` |
| `NFR-AVL-001` | `NFS-AVL-001` | `NFR-TEST-AVL-001` |
| `NFR-PER-001` | `NFS-PER-001` | `NFR-TEST-PER-001` |
| `NFR-AUD-001` | `NFS-AUD-001` | `NFR-TEST-AUD-001` |
| `NFR-DAT-001` | `NFS-DAT-001` | `NFR-TEST-DAT-001` |
| `NFR-USE-001` | `NFS-USE-001` | `NFR-TEST-USE-001` |
| `NFR-PLT-001` | `NFS-PLT-001` | `NFR-TEST-PLT-001` |

## 3. Functional Requirement Definition Rules

1. Mỗi parent `FR-*` trong tài liệu này trace trực tiếp về đúng một `BR-*` P1 theo baseline hiện hành.
2. `Functional intent` giữ nguyên nội dung `description` của BR nguồn.
3. `Source-derived functional rules` tách các rule có trong description để hỗ trợ thiết kế/implementation nhưng không thay đổi nghĩa nguồn.
4. `Acceptance-oriented child requirements` được tạo từ từng `success_criteria` nguồn; wording của criterion được giữ nguyên để tránh semantic drift.
5. Dependency ở cấp FR được suy ra chỉ khi upstream `depends_on` trỏ tới một BR P1 đã có planned FR. Upstream dependency khác vẫn được giữ nguyên bằng ID nguồn.
6. Các reference tới P2/P3/FUTURE không được dùng như điều kiện bắt buộc để P1 chạy hoặc được acceptance, trừ khi User Requirements nguồn quy định rõ capability đó đã nằm trong approved scope.

## 4. P1 Functional Requirements

### 4.1 Organization & Enterprise Structure

#### FR-ORG-001 — Quản lý nhiều Group và Company

```yaml
id: "FR-ORG-001"
title: "Quản lý nhiều Group và Company"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-ORG-001"
upstream_depends_on: []
functional_depends_on: []
governed_by: ["AR-DOM-001", "AR-TXN-001"]
planned_verified_by: ["UAT-ORG-001"]
```

**Functional intent**

Hệ thống hỗ trợ quản lý nhiều Group độc lập trên cùng một nền tảng. Mỗi Group có dữ liệu, cơ cấu tổ chức và phạm vi quản trị riêng.
Mỗi Group được đại diện bởi một Group Business Entity duy nhất, là business identity chuẩn của Group và lưu các thông tin nghiệp vụ/quản trị của Group.
Organization Context `type=GROUP` chỉ dùng để biểu diễn Group đó trong hierarchy và phạm vi tổ chức. Mỗi Group Business Entity có đúng một Organization Context `type=GROUP` tương ứng theo quan hệ 1:1; Organization Context này không phải một Group master riêng.
Business Transaction không thuộc trực tiếp Group. Mỗi Business Transaction phải thuộc đúng một Company; Group của transaction được xác định thông qua Company sở hữu transaction đó.

**Source-derived functional rules**

- `FR-ORG-001-R01` — Hệ thống hỗ trợ quản lý nhiều Group độc lập trên cùng một nền tảng.
- `FR-ORG-001-R02` — Mỗi Group có dữ liệu, cơ cấu tổ chức và phạm vi quản trị riêng.
- `FR-ORG-001-R03` — Mỗi Group được đại diện bởi một Group Business Entity duy nhất, là business identity chuẩn của Group và lưu các thông tin nghiệp vụ/quản trị của Group.
- `FR-ORG-001-R04` — Organization Context `type=GROUP` chỉ dùng để biểu diễn Group đó trong hierarchy và phạm vi tổ chức.
- `FR-ORG-001-R05` — Mỗi Group Business Entity có đúng một Organization Context `type=GROUP` tương ứng theo quan hệ 1:1; Organization Context này không phải một Group master riêng.
- `FR-ORG-001-R06` — Business Transaction không thuộc trực tiếp Group.
- `FR-ORG-001-R07` — Mỗi Business Transaction phải thuộc đúng một Company; Group của transaction được xác định thông qua Company sở hữu transaction đó.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-ORG-001.01` | Có thể quản lý nhiều Group độc lập trên cùng một nền tảng mà dữ liệu giữa các Group không bị trộn lẫn. |
| `FR-ORG-001.02` | Mỗi Group có đúng một Group Business Entity làm business identity chuẩn. |
| `FR-ORG-001.03` | Mỗi Group Business Entity có đúng một Organization Context `type=GROUP` tương ứng theo quan hệ 1:1. |
| `FR-ORG-001.04` | Không được tạo Organization Context `type=GROUP` nếu không liên kết với một Group Business Entity. |
| `FR-ORG-001.05` | Không được tạo nhiều Group Business Entity cho cùng một Organization Context `type=GROUP`. |
| `FR-ORG-001.06` | Thông tin nghiệp vụ và quản trị của Group được lưu tại Group Business Entity; Organization Context chỉ quản lý hierarchy và organizational scope. |
| `FR-ORG-001.07` | Mỗi Business Transaction thuộc đúng một Company; Group tương ứng được xác định thông qua Company và không được gán làm transaction owner trực tiếp. |
| `FR-ORG-001.08` | Có thể tổng hợp dữ liệu của các Company thuộc cùng một Group mà vẫn giữ đúng Company ownership của từng transaction. |

**Traceability note**

- Upstream: `BR-ORG-001` (source line ~157).
- Planned verification artifact from UR baseline: `UAT-ORG-001`.
- Governed by: `AR-DOM-001`, `AR-TXN-001`.

#### FR-ORG-002 — Quản lý Organization Context và hierarchy vận hành

```yaml
id: "FR-ORG-002"
title: "Quản lý Organization Context và hierarchy vận hành"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-ORG-002"
upstream_depends_on: ["BR-ORG-001"]
functional_depends_on: ["FR-ORG-001"]
governed_by: ["AR-DOM-001", "AR-TXN-001"]
references: ["ORG-CONTEXT-HIERARCHY-POLICY"]
planned_verified_by: ["UAT-ORG-002"]
```

**Functional intent**

Hệ thống quản lý Organization Context bằng tập type cố định gồm GROUP, COMPANY, BRANCH, PLANT và WAREHOUSE.
Organization Context `type=GROUP` chỉ là biểu diễn của Group trong hierarchy và organizational scope. Nó phải tham chiếu đúng một Group Business Entity theo quan hệ 1:1 và không phải một Group master riêng.
Mỗi Business Transaction thuộc đúng một Company. Branch, Plant và Warehouse được tổ chức trong hierarchy của Company tương ứng.
Hierarchy không bắt buộc phải có đầy đủ mọi cấp. Có thể bỏ qua cấp trung gian khi mô hình vận hành cho phép, ví dụ Company có thể quản lý Warehouse trực tiếp mà không bắt buộc phải có Branch hoặc Plant ở giữa.
Department và Position không phải Organization Context. Hai đối tượng này thuộc Employee domain. Tuy nhiên hệ thống phải cho phép hiển thị chúng cùng Organization Context trong một organizational view thống nhất, dựa trên Employee Assignment đang có hiệu lực, để người dùng có thể xem cơ cấu theo Group, Company, đơn vị vận hành, Department, Position và Employee.
Company, Branch, Plant và Warehouse phải có lifecycle/state và thời gian hiệu lực. Department có lifecycle riêng do Employee domain quản lý.
Quan hệ parent-child giữa các Organization Context phải được kiểm tra theo controlled artifact `ORG-CONTEXT-HIERARCHY-POLICY`. Artifact này là nguồn business rule chính thức cho các parent-child combination được phép hoặc không được phép. FRS/DES chỉ triển khai và áp dụng policy này, không được hard-code một hierarchy rule khác làm nguồn quyết định cạnh tranh.

**Source-derived functional rules**

- `FR-ORG-002-R01` — Hệ thống quản lý Organization Context bằng tập type cố định gồm GROUP, COMPANY, BRANCH, PLANT và WAREHOUSE.
- `FR-ORG-002-R02` — Organization Context `type=GROUP` chỉ là biểu diễn của Group trong hierarchy và organizational scope.
- `FR-ORG-002-R03` — Nó phải tham chiếu đúng một Group Business Entity theo quan hệ 1:1 và không phải một Group master riêng.
- `FR-ORG-002-R04` — Mỗi Business Transaction thuộc đúng một Company.
- `FR-ORG-002-R05` — Branch, Plant và Warehouse được tổ chức trong hierarchy của Company tương ứng.
- `FR-ORG-002-R06` — Hierarchy không bắt buộc phải có đầy đủ mọi cấp.
- `FR-ORG-002-R07` — Có thể bỏ qua cấp trung gian khi mô hình vận hành cho phép, ví dụ Company có thể quản lý Warehouse trực tiếp mà không bắt buộc phải có Branch hoặc Plant ở giữa.
- `FR-ORG-002-R08` — Department và Position không phải Organization Context.
- `FR-ORG-002-R09` — Hai đối tượng này thuộc Employee domain.
- `FR-ORG-002-R10` — Tuy nhiên hệ thống phải cho phép hiển thị chúng cùng Organization Context trong một organizational view thống nhất, dựa trên Employee Assignment đang có hiệu lực, để người dùng có thể xem cơ cấu theo Group, Company, đơn vị vận hành, Department, Position và Employee.
- `FR-ORG-002-R11` — Company, Branch, Plant và Warehouse phải có lifecycle/state và thời gian hiệu lực.
- `FR-ORG-002-R12` — Department có lifecycle riêng do Employee domain quản lý.
- `FR-ORG-002-R13` — Quan hệ parent-child giữa các Organization Context phải được kiểm tra theo controlled artifact `ORG-CONTEXT-HIERARCHY-POLICY`.
- `FR-ORG-002-R14` — Artifact này là nguồn business rule chính thức cho các parent-child combination được phép hoặc không được phép.
- `FR-ORG-002-R15` — FRS/DES chỉ triển khai và áp dụng policy này, không được hard-code một hierarchy rule khác làm nguồn quyết định cạnh tranh.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-ORG-002.01` | Organization Context chỉ sử dụng các type GROUP, COMPANY, BRANCH, PLANT và WAREHOUSE. |
| `FR-ORG-002.02` | Business configuration thông thường không được tạo thêm Organization Context Type ngoài tập type đã quy định. |
| `FR-ORG-002.03` | Organization Context `type=GROUP` tham chiếu đúng một Group Business Entity canonical theo quan hệ 1:1 và không tạo Group master cạnh tranh. |
| `FR-ORG-002.04` | Mỗi Branch, Plant và Warehouse xác định được Company sở hữu. |
| `FR-ORG-002.05` | Mỗi Branch, Plant và Warehouse có vị trí hợp lệ trong Organization Context hierarchy của Company. |
| `FR-ORG-002.06` | Hierarchy hợp lệ có thể bỏ qua cấp trung gian, ví dụ Company -> Warehouse, khi `ORG-CONTEXT-HIERARCHY-POLICY` cho phép. |
| `FR-ORG-002.07` | Mỗi Business Transaction xác định được đúng Company sở hữu; Organization Context không làm thay đổi transaction ownership. |
| `FR-ORG-002.08` | Dữ liệu và báo cáo theo Organization Context không làm trộn dữ liệu ngoài phạm vi Company/Group được phép. |
| `FR-ORG-002.09` | Company, Branch, Plant và Warehouse có lifecycle/state và thời gian hiệu lực; Organization Context ngừng hoạt động không được phát sinh dữ liệu mới nhưng lịch sử vẫn tra cứu được. |
| `FR-ORG-002.10` | Department và Position không được tạo như canonical Organization Context. |
| `FR-ORG-002.11` | Có thể hiển thị Department, Position và Employee trong organizational view dựa trên Employee Assignment đang có hiệu lực. |
| `FR-ORG-002.12` | Một Position có thể xuất hiện tại nhiều Department hoặc Organization Context khác nhau thông qua các Employee Assignment đang có hiệu lực. |
| `FR-ORG-002.13` | Việc hiển thị Department hoặc Position trong organizational view không tự tạo Organization Scope, Permission hoặc transaction ownership mới. |
| `FR-ORG-002.14` | Lifecycle/state của Department tiếp tục do Employee domain quản lý và không được quản lý như Organization Context. |
| `FR-ORG-002.15` | Quan hệ parent-child của Organization Context được validate theo `ORG-CONTEXT-HIERARCHY-POLICY` đang có hiệu lực. |
| `FR-ORG-002.16` | `ORG-CONTEXT-HIERARCHY-POLICY` xác định owner, version, effective time và các parent-child combination được phép hoặc không được phép. |
| `FR-ORG-002.17` | Parent-child combination không được policy cho phép phải bị từ chối, trừ khi policy hiện hành cho phép một controlled exception. |
| `FR-ORG-002.18` | FRS/DES không được hard-code hierarchy rule khác làm nguồn business rule cạnh tranh với `ORG-CONTEXT-HIERARCHY-POLICY`. |

**Traceability note**

- Upstream: `BR-ORG-002` (source line ~205).
- Planned verification artifact from UR baseline: `UAT-ORG-002`.
- Governed by: `AR-DOM-001`, `AR-TXN-001`.
- Non-hard references: `ORG-CONTEXT-HIERARCHY-POLICY`.

#### FR-ORG-004 — Quản lý business timezone, currency và Company Business Calendar

```yaml
id: "FR-ORG-004"
title: "Quản lý business timezone, currency và Company Business Calendar"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-ORG-004"
upstream_depends_on: ["BR-ORG-002"]
functional_depends_on: ["FR-ORG-002"]
references: ["BR-SYS-001", "BR-FIN-006"]
planned_verified_by: ["UAT-ORG-004"]
```

**Functional intent**

Quản lý business timezone, business/base/functional currency, Company Business Calendar và business-period/calendar settings theo từng Company. Các giá trị này quyết định business meaning của ngày làm việc, thời điểm và số tiền; locale/display formatting thuộc BR-SYS-001 và không được thay đổi các giá trị nghiệp vụ này. Company Business Calendar là canonical source để xác định business day/non-business day, Company holiday và các business cutoff/deadline cần tính theo ngày làm việc. Calendar này thuộc Organization/Foundation và không thay thế Employee Work Calendar của HR. Financial period, period close/reopen và periodic allocation không thuộc ownership của requirement P1 này; khi capability đó nằm trong approved scope, Company Business Calendar chỉ cung cấp calendar/business-day context và tham chiếu financial-period/close context do BR-FIN-006 quản lý.

**Source-derived functional rules**

- `FR-ORG-004-R01` — Quản lý business timezone, business/base/functional currency, Company Business Calendar và business-period/calendar settings theo từng Company.
- `FR-ORG-004-R02` — Các giá trị này quyết định business meaning của ngày làm việc, thời điểm và số tiền; locale/display formatting thuộc BR-SYS-001 và không được thay đổi các giá trị nghiệp vụ này.
- `FR-ORG-004-R03` — Company Business Calendar là canonical source để xác định business day/non-business day, Company holiday và các business cutoff/deadline cần tính theo ngày làm việc.
- `FR-ORG-004-R04` — Calendar này thuộc Organization/Foundation và không thay thế Employee Work Calendar của HR.
- `FR-ORG-004-R05` — Financial period, period close/reopen và periodic allocation không thuộc ownership của requirement P1 này; khi capability đó nằm trong approved scope, Company Business Calendar chỉ cung cấp calendar/business-day context và tham chiếu financial-period/close context do BR-FIN-006 quản lý.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-ORG-004.01` | Mỗi chứng từ xác định được Company policy về business timezone và business/base/functional currency có hiệu lực tại thời điểm nghiệp vụ. |
| `FR-ORG-004.02` | Thay đổi locale/display setting không làm thay đổi business currency, canonical business timestamp/business date hoặc monetary value đã ghi nhận. |
| `FR-ORG-004.03` | Mỗi Company xác định được Business Calendar có thời gian hiệu lực và phân loại được business day/non-business day cùng Company holiday áp dụng. |
| `FR-ORG-004.04` | Các deadline/cutoff dùng khái niệm ngày làm việc có thể resolve cùng một Company Business Calendar canonical. |
| `FR-ORG-004.05` | Employee Work Calendar có thể bổ sung lịch theo Assignment/Branch/Plant/employee group mà không tạo định nghĩa Company business day cạnh tranh. |
| `FR-ORG-004.06` | Financial period và close/reopen chỉ tạo acceptance obligation khi BR-FIN-006 nằm trong approved scope; BR-ORG-004 không sở hữu lifecycle của financial period. |

**Traceability note**

- Upstream: `BR-ORG-004` (source line ~301).
- Planned verification artifact from UR baseline: `UAT-ORG-004`.
- Non-hard references: `BR-SYS-001`, `BR-FIN-006`.

### 4.2 Employee & Organizational Structure

#### FR-EMP-001 — Quản lý Employee và Employee Assignment

```yaml
id: "FR-EMP-001"
title: "Quản lý Employee và Employee Assignment"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-EMP-001"
upstream_depends_on: ["BR-ORG-002", "BR-EMP-002"]
functional_depends_on: ["FR-ORG-002", "FR-EMP-002"]
planned_verified_by: ["UAT-EMP-001"]
```

**Functional intent**

Quản lý Employee độc lập với User Identity. Employee domain là owning domain của Employee và Employee Assignment. Employee là Group Master canonical trong Group; Employee Assignment là Company Master và biểu diễn quan hệ làm việc của Employee trong một Company bằng cách tham chiếu Organization Context, Department, Position và thời gian hiệu lực. HRP và IAM chỉ mở rộng hoặc tham chiếu các đối tượng này, không tạo Employee/Employee Assignment master cạnh tranh.

**Source-derived functional rules**

- `FR-EMP-001-R01` — Quản lý Employee độc lập với User Identity.
- `FR-EMP-001-R02` — Employee domain là owning domain của Employee và Employee Assignment.
- `FR-EMP-001-R03` — Employee là Group Master canonical trong Group; Employee Assignment là Company Master và biểu diễn quan hệ làm việc của Employee trong một Company bằng cách tham chiếu Organization Context, Department, Position và thời gian hiệu lực.
- `FR-EMP-001-R04` — HRP và IAM chỉ mở rộng hoặc tham chiếu các đối tượng này, không tạo Employee/Employee Assignment master cạnh tranh.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-EMP-001.01` | Mỗi Employee xác định được Group sở hữu và có thể được tham chiếu bởi các Company trong Group theo quyền được cấp. |
| `FR-EMP-001.02` | Mỗi Employee Assignment xác định được đúng một Company sở hữu, Organization Context, Department, Position và thời gian hiệu lực. |
| `FR-EMP-001.03` | Một Employee có thể có nhiều Assignment theo Company và theo thời gian mà vẫn dùng cùng Employee canonical. |
| `FR-EMP-001.04` | Lịch sử thay đổi Organization Context, Department, Position và thời gian hiệu lực của Assignment được bảo toàn. |
| `FR-EMP-001.05` | Có thể xác định Employee/Employee Assignment canonical mà HRP và IAM đang tham chiếu. |

**Traceability note**

- Upstream: `BR-EMP-001` (source line ~366).
- Planned verification artifact from UR baseline: `UAT-EMP-001`.

#### FR-EMP-002 — Quản lý Department, Position và organizational view

```yaml
id: "FR-EMP-002"
title: "Quản lý Department, Position và organizational view"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-EMP-002"
upstream_depends_on: ["BR-ORG-002"]
functional_depends_on: ["FR-ORG-002"]
planned_verified_by: ["UAT-EMP-002"]
```

**Functional intent**

Department và Position là master thuộc Employee domain. Department là Company Master, có hierarchy, lifecycle/state và thời gian hiệu lực riêng trong Company. Position là Group Master thể hiện chức danh và level dùng chung trong Group. Department/Position được Employee Assignment tham chiếu theo thời gian hiệu lực và không tự sinh Permission hoặc Organization Scope.

**Source-derived functional rules**

- `FR-EMP-002-R01` — Department và Position là master thuộc Employee domain.
- `FR-EMP-002-R02` — Department là Company Master, có hierarchy, lifecycle/state và thời gian hiệu lực riêng trong Company.
- `FR-EMP-002-R03` — Position là Group Master thể hiện chức danh và level dùng chung trong Group.
- `FR-EMP-002-R04` — Department/Position được Employee Assignment tham chiếu theo thời gian hiệu lực và không tự sinh Permission hoặc Organization Scope.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-EMP-002.01` | Mỗi Department xác định được Company sở hữu, parent trong hierarchy khi áp dụng, lifecycle/state và thời gian hiệu lực. |
| `FR-EMP-002.02` | Department ngừng hiệu lực không được dùng cho Employee Assignment mới nhưng lịch sử Assignment cũ vẫn được bảo toàn. |
| `FR-EMP-002.03` | Mỗi Position xác định được Group sở hữu, chức danh/level, trạng thái và thời gian hiệu lực. |
| `FR-EMP-002.04` | Employee Assignment chỉ tham chiếu Department thuộc đúng Company của Assignment và Position thuộc Group tương ứng đang có hiệu lực. |
| `FR-EMP-002.05` | Department hoặc Position không tự tạo Permission hay Organization Scope. |

**Traceability note**

- Upstream: `BR-EMP-002` (source line ~389).
- Planned verification artifact from UR baseline: `UAT-EMP-002`.

### 4.3 Authorization & Security

#### FR-IAM-001 — Quản lý User Identity và Group Access Membership

```yaml
id: "FR-IAM-001"
title: "Quản lý User Identity và Group Access Membership"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-IAM-001"
upstream_depends_on: ["BR-ORG-001", "BR-EMP-001"]
functional_depends_on: ["FR-ORG-001", "FR-EMP-001"]
planned_verified_by: ["UAT-IAM-001"]
```

**Functional intent**

Quản lý User Identity như authentication principal ở platform level, độc lập với Employee/Employee Assignment master. User Identity xác định loại identity, trạng thái xác thực/truy cập và lifecycle ở cấp nền tảng. IAM không sở hữu hoặc tạo Employee/Employee Assignment master thay thế. Quản lý explicit Group Access Membership để một User Identity được truy cập từng Group. Ordinary ERP User membership phải reference Employee canonical thuộc chính Group đó; membership không trỏ cứng vào một Employee Assignment. Khi authorization cần Company/Organization Context, active Employee Assignment(s) của Employee được resolve theo Company/context và thời gian hiệu lực tại thời điểm đánh giá. System Admin/service identity là ngoại lệ có kiểm soát: nếu không reference Employee thì loại identity, scope và căn cứ ngoại lệ phải explicit và audit được. Dữ liệu giữa các Group phải được cách ly. User Identity chỉ được truy cập Group theo explicit Group Access Membership hoặc controlled exceptional identity scope; membership/quyền của Group này không được suy ra thành quyền ở Group khác.

**Source-derived functional rules**

- `FR-IAM-001-R01` — Quản lý User Identity như authentication principal ở platform level, độc lập với Employee/Employee Assignment master.
- `FR-IAM-001-R02` — User Identity xác định loại identity, trạng thái xác thực/truy cập và lifecycle ở cấp nền tảng.
- `FR-IAM-001-R03` — IAM không sở hữu hoặc tạo Employee/Employee Assignment master thay thế.
- `FR-IAM-001-R04` — Quản lý explicit Group Access Membership để một User Identity được truy cập từng Group.
- `FR-IAM-001-R05` — Ordinary ERP User membership phải reference Employee canonical thuộc chính Group đó; membership không trỏ cứng vào một Employee Assignment.
- `FR-IAM-001-R06` — Khi authorization cần Company/Organization Context, active Employee Assignment(s) của Employee được resolve theo Company/context và thời gian hiệu lực tại thời điểm đánh giá.
- `FR-IAM-001-R07` — System Admin/service identity là ngoại lệ có kiểm soát: nếu không reference Employee thì loại identity, scope và căn cứ ngoại lệ phải explicit và audit được.
- `FR-IAM-001-R08` — Dữ liệu giữa các Group phải được cách ly.
- `FR-IAM-001-R09` — User Identity chỉ được truy cập Group theo explicit Group Access Membership hoặc controlled exceptional identity scope; membership/quyền của Group này không được suy ra thành quyền ở Group khác.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-IAM-001.01` | Mỗi User Identity có định danh duy nhất, loại identity, trạng thái và lifecycle có hiệu lực ở platform level. |
| `FR-IAM-001.02` | User bị khóa hoặc ngừng hiệu lực không thể thực hiện giao dịch mới. |
| `FR-IAM-001.03` | User Identity không trỏ cứng vào một Employee Assignment và không biến platform identity thành Company membership. |
| `FR-IAM-001.04` | IAM không tạo Employee, Department, Position hoặc Employee Assignment master cạnh tranh. |
| `FR-IAM-001.05` | Mỗi ordinary ERP User truy cập Group có explicit membership tới đúng Group và Employee canonical thuộc Group đó. |
| `FR-IAM-001.06` | Membership Group A không tự tạo quyền ở Group B; cross-Group access yêu cầu membership/scope explicit cho từng Group liên quan. |
| `FR-IAM-001.07` | Khi authorization theo Company/context, Employee Assignment được resolve từ các Assignment đang hiệu lực của Employee; membership không trở thành Company membership do tham chiếu cứng một Assignment. |
| `FR-IAM-001.08` | Permission/Policy được evaluate trong Group và Company/Organization Context đã resolve tương ứng. |
| `FR-IAM-001.09` | System Admin/service identity ngoại lệ không có Employee chỉ được phép khi identity type, scope, owner và audit evidence được kiểm soát. |
| `FR-IAM-001.10` | Kiểm tra chéo không truy cập được dữ liệu ngoài Group khi không có membership/scope explicit tương ứng. |
| `FR-IAM-001.11` | Membership Group A không tạo quyền hoặc data scope trong Group B. |

**Traceability note**

- Upstream: `BR-IAM-001` (source line ~413).
- Planned verification artifact from UR baseline: `UAT-IAM-001`.

#### FR-IAM-002 — Quản lý Permission, Profile và Business Group

```yaml
id: "FR-IAM-002"
title: "Quản lý Permission, Profile và Business Group"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-IAM-002"
upstream_depends_on: []
functional_depends_on: []
planned_verified_by: ["UAT-IAM-002"]
```

**Functional intent**

Hệ thống quản lý Permission theo cặp Resource/Action. Mỗi Permission xác định người dùng được phép thực hiện hành động nào trên loại Resource nào và là đơn vị quyền cơ sở của hệ thống Authorization.
Profile là tập hợp các Permission. Người dùng nhận quyền thông qua Profile; Policy Rule không được tạo thêm Permission ngoài các Permission đã được định nghĩa và cấp qua Profile.
Mỗi Resource/Action Permission có thuộc tính canonical `policy_required`. Thuộc tính này quyết định cách xử lý khi không có Policy Rule nào match trong Authorization Evaluation. `policy_required` thuộc Permission và có cùng giá trị bất kể Permission đó được cấp qua Profile nào.
Business Group được dùng để hỗ trợ quản trị phân quyền bằng cách ánh xạ người dùng hoặc nhóm nghiệp vụ tới Profile phù hợp. Business Group là khái niệm của Authorization và hoàn toàn khác với Group Business Entity trong Organization Model.
Business Group không thay thế Profile, Permission hoặc Policy Rule và không tự tạo thêm quyền cho người dùng.

**Source-derived functional rules**

- `FR-IAM-002-R01` — Hệ thống quản lý Permission theo cặp Resource/Action.
- `FR-IAM-002-R02` — Mỗi Permission xác định người dùng được phép thực hiện hành động nào trên loại Resource nào và là đơn vị quyền cơ sở của hệ thống Authorization.
- `FR-IAM-002-R03` — Profile là tập hợp các Permission.
- `FR-IAM-002-R04` — Người dùng nhận quyền thông qua Profile; Policy Rule không được tạo thêm Permission ngoài các Permission đã được định nghĩa và cấp qua Profile.
- `FR-IAM-002-R05` — Mỗi Resource/Action Permission có thuộc tính canonical `policy_required`.
- `FR-IAM-002-R06` — Thuộc tính này quyết định cách xử lý khi không có Policy Rule nào match trong Authorization Evaluation.
- `FR-IAM-002-R07` — `policy_required` thuộc Permission và có cùng giá trị bất kể Permission đó được cấp qua Profile nào.
- `FR-IAM-002-R08` — Business Group được dùng để hỗ trợ quản trị phân quyền bằng cách ánh xạ người dùng hoặc nhóm nghiệp vụ tới Profile phù hợp.
- `FR-IAM-002-R09` — Business Group là khái niệm của Authorization và hoàn toàn khác với Group Business Entity trong Organization Model.
- `FR-IAM-002-R10` — Business Group không thay thế Profile, Permission hoặc Policy Rule và không tự tạo thêm quyền cho người dùng.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-IAM-002.01` | Mỗi Permission xác định được Resource, Action, mã định danh và giá trị `policy_required`. |
| `FR-IAM-002.02` | Mỗi Profile xác định được tập Permission đang được cấp qua Profile đó. |
| `FR-IAM-002.03` | Có thể truy được quyền hiệu lực của người dùng về Profile và Permission đã cấp. |
| `FR-IAM-002.04` | Cùng một Resource/Action Permission có cùng giá trị `policy_required` bất kể Permission đó được sử dụng trong Profile nào. |
| `FR-IAM-002.05` | Policy Rule không được tạo Permission mới hoặc cấp thêm quyền ngoài Permission đã được cấp qua Profile. |
| `FR-IAM-002.06` | Business Group có thể được ánh xạ tới Profile để hỗ trợ quản trị phân quyền. |
| `FR-IAM-002.07` | Có thể xác định Business Group, Profile, Permission và Policy Rule đang tham gia vào việc xác định quyền của người dùng. |
| `FR-IAM-002.08` | Business Group trong Authorization không được hiểu là Group Business Entity trong Organization Model. |
| `FR-IAM-002.09` | Thay đổi mapping giữa Business Group và Profile phải giữ được lịch sử. |
| `FR-IAM-002.10` | Thay đổi Business Group hoặc mapping Business Group - Profile không tự tạo Permission mới. |

**Traceability note**

- Upstream: `BR-IAM-002` (source line ~442).
- Planned verification artifact from UR baseline: `UAT-IAM-002`.

#### FR-IAM-003 — Đánh giá Authorization theo Policy Based Access Control

```yaml
id: "FR-IAM-003"
title: "Đánh giá Authorization theo Policy Based Access Control"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-IAM-003"
upstream_depends_on: ["BR-IAM-001", "BR-IAM-002", "BR-ORG-002"]
functional_depends_on: ["FR-IAM-001", "FR-IAM-002", "FR-ORG-002"]
planned_verified_by: ["UAT-IAM-003"]
```

**Functional intent**

Phân quyền theo mô hình Profile Permission kết hợp Policy Rule trong Group/Company/Organization Context đã resolve qua BR-IAM-001. Permission được định nghĩa theo Resource/Action và được cấp thông qua Profile; Policy Rule chỉ giới hạn quyền hiệu lực theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect. Authorization Evaluation không cấp thêm quyền ngoài Profile; Authorization Evaluation phải deterministic theo các rule của capability này. Quản lý Policy Rule để giới hạn Permission theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect. Department không trở thành Organization Context và không tự tạo Permission/Scope; khi Department condition được dùng, Actor Department phải được resolve từ Employee Assignment có hiệu lực còn Resource Responsible Department phải lấy từ ownership/responsibility của resource. Condition luôn được đánh giá trong đúng Company context. Quản lý Policy theo version, thời gian hiệu lực và lịch sử thay đổi để quyết định Authorization có thể được giải thích theo chính sách áp dụng tại thời điểm đánh giá. Áp dụng một Authorization Evaluation thống nhất: Profile Permission là gate quyền cơ sở; applicable Policy Rules chỉ được evaluate sau khi Permission tồn tại; Effect aggregate theo `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; NO_MATCH được quyết định bởi canonical `policy_required` của Resource/Action Permission. Policy không bao giờ tạo Permission mới. Quản lý quyền truy cập theo từng loại hành động trên cùng một đối tượng nghiệp vụ, bao gồm xem, tạo hoặc gửi yêu cầu, phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu và quản trị. Quyền đối với từng hành động phải được xác định độc lập; việc được cấp một quyền không mặc nhiên tạo ra quyền thực hiện hành động khác trên cùng đối tượng.

**Source-derived functional rules**

- `FR-IAM-003-R01` — Phân quyền theo mô hình Profile Permission kết hợp Policy Rule trong Group/Company/Organization Context đã resolve qua BR-IAM-001.
- `FR-IAM-003-R02` — Permission được định nghĩa theo Resource/Action và được cấp thông qua Profile; Policy Rule chỉ giới hạn quyền hiệu lực theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect.
- `FR-IAM-003-R03` — Authorization Evaluation không cấp thêm quyền ngoài Profile; Authorization Evaluation phải deterministic theo các rule của capability này.
- `FR-IAM-003-R04` — Quản lý Policy Rule để giới hạn Permission theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect.
- `FR-IAM-003-R05` — Department không trở thành Organization Context và không tự tạo Permission/Scope; khi Department condition được dùng, Actor Department phải được resolve từ Employee Assignment có hiệu lực còn Resource Responsible Department phải lấy từ ownership/responsibility của resource.
- `FR-IAM-003-R06` — Condition luôn được đánh giá trong đúng Company context.
- `FR-IAM-003-R07` — Quản lý Policy theo version, thời gian hiệu lực và lịch sử thay đổi để quyết định Authorization có thể được giải thích theo chính sách áp dụng tại thời điểm đánh giá.
- `FR-IAM-003-R08` — Áp dụng một Authorization Evaluation thống nhất: Profile Permission là gate quyền cơ sở; applicable Policy Rules chỉ được evaluate sau khi Permission tồn tại; Effect aggregate theo `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; NO_MATCH được quyết định bởi canonical `policy_required` của Resource/Action Permission.
- `FR-IAM-003-R09` — Policy không bao giờ tạo Permission mới.
- `FR-IAM-003-R10` — Quản lý quyền truy cập theo từng loại hành động trên cùng một đối tượng nghiệp vụ, bao gồm xem, tạo hoặc gửi yêu cầu, phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu và quản trị.
- `FR-IAM-003-R11` — Quyền đối với từng hành động phải được xác định độc lập; việc được cấp một quyền không mặc nhiên tạo ra quyền thực hiện hành động khác trên cùng đối tượng.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-IAM-003.01` | Người dùng chỉ thấy và tác động đến dữ liệu được ủy quyền trong Group/Company/Organization Context đã resolve. |
| `FR-IAM-003.02` | Quyết định Authorization truy được Profile Permission, canonical Permission metadata, applicable Policy Rules và membership/context đã dùng. |
| `FR-IAM-003.03` | Quyết định Authorization có thể giải thích được theo Policy áp dụng. |
| `FR-IAM-003.04` | Department condition không tạo Organization Scope/Permission riêng và Department được evaluate trong đúng Company. |
| `FR-IAM-003.05` | Actor Department được resolve từ Employee Assignment đang hiệu lực; Resource Department được lấy từ owning/responsible Department của resource thay vì suy diễn từ User. |
| `FR-IAM-003.06` | Có thể xác định version Policy có hiệu lực tại một thời điểm và truy lại các version trước. |
| `FR-IAM-003.07` | Thay đổi Policy không ghi đè làm mất nội dung đã từng có hiệu lực. |
| `FR-IAM-003.08` | Không có Profile Permission thì kết quả luôn DENY, kể cả khi có ALLOW Policy. |
| `FR-IAM-003.09` | Có Profile Permission và có `ABSOLUTE_DENY` applicable thì kết quả DENY. |
| `FR-IAM-003.10` | Có Profile Permission và có `ALLOW` applicable, không có `ABSOLUTE_DENY`, thì ALLOW thắng DENY thông thường. |
| `FR-IAM-003.11` | Có Profile Permission và chỉ có `DENY` applicable thì kết quả DENY. |
| `FR-IAM-003.12` | Có Profile Permission nhưng NO_MATCH: `policy_required=true` thì DENY; `policy_required=false` thì ALLOW. |
| `FR-IAM-003.13` | Permission set của nhiều Profile là union của các Profile đang có hiệu lực; Profile bổ sung Permission nhưng không bypass Policy. |
| `FR-IAM-003.14` | Quyết định cho phép/từ chối có thể giải thích được từ Membership/Context, Profile Permission, `policy_required`, applicable Policy version/rule và Effect đã áp dụng. |
| `FR-IAM-003.15` | Đối với cùng một đối tượng nghiệp vụ, có thể cấu hình và xác định độc lập quyền xem, tạo/gửi yêu cầu, phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu và quản trị. |
| `FR-IAM-003.16` | Người dùng chỉ thực hiện được hành động khi Permission tương ứng được cấp và các Policy Rule áp dụng cho context hiện tại cho phép. |
| `FR-IAM-003.17` | Việc có quyền xem hoặc tạo yêu cầu không mặc nhiên cấp quyền phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu hoặc quản trị. |
| `FR-IAM-003.18` | Có thể truy được Permission và Policy Rule đã được sử dụng để quyết định cho phép hoặc từ chối một hành động. |

**Traceability note**

- Upstream: `BR-IAM-003` (source line ~493).
- Planned verification artifact from UR baseline: `UAT-IAM-003`.

#### FR-IAM-005 — Bảo vệ dữ liệu nhạy cảm theo mục đích và phạm vi công việc

```yaml
id: "FR-IAM-005"
title: "Bảo vệ dữ liệu nhạy cảm theo mục đích và phạm vi công việc"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-IAM-005"
upstream_depends_on: ["BR-IAM-003"]
functional_depends_on: ["FR-IAM-003"]
planned_verified_by: ["UAT-IAM-005"]
```

**Functional intent**

Bảo vệ dữ liệu nhạy cảm như lương, thông tin định danh, giá vốn, chiết khấu và tài khoản ngân hàng theo phạm vi công việc. Truy cập hoặc xuất dữ liệu nhạy cảm chỉ được thực hiện khi User có Resource/Action Permission tương ứng và final Authorization Evaluation theo BR-IAM-003 trả về ALLOW trong đúng Group/Company/Organization Context. Policy Rule không tự tạo quyền và không được dùng thay cho Resource/Action Permission.

**Source-derived functional rules**

- `FR-IAM-005-R01` — Bảo vệ dữ liệu nhạy cảm như lương, thông tin định danh, giá vốn, chiết khấu và tài khoản ngân hàng theo phạm vi công việc.
- `FR-IAM-005-R02` — Truy cập hoặc xuất dữ liệu nhạy cảm chỉ được thực hiện khi User có Resource/Action Permission tương ứng và final Authorization Evaluation theo BR-IAM-003 trả về ALLOW trong đúng Group/Company/Organization Context.
- `FR-IAM-005-R03` — Policy Rule không tự tạo quyền và không được dùng thay cho Resource/Action Permission.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-IAM-005.01` | User không có Resource/Action Permission tương ứng hoặc có final Authorization Evaluation khác ALLOW không xem, sửa hoặc xuất được dữ liệu nhạy cảm cho hành động đó. |
| `FR-IAM-005.02` | User chỉ xem được dữ liệu nhạy cảm khi Permission của hành động xem tồn tại và final Authorization Evaluation = ALLOW trong context hiện tại. |
| `FR-IAM-005.03` | Quyền export dữ liệu nhạy cảm được đánh giá độc lập với quyền xem; quyền xem không mặc nhiên cấp quyền export. |

**Traceability note**

- Upstream: `BR-IAM-005` (source line ~550).
- Planned verification artifact from UR baseline: `UAT-IAM-005`.

### 4.4 Cross-domain Controls & Workflow

#### FR-CTL-001 — Chuẩn hóa lifecycle, numbering và quan hệ giữa chứng từ

```yaml
id: "FR-CTL-001"
title: "Chuẩn hóa lifecycle, numbering và quan hệ giữa chứng từ"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-CTL-001"
upstream_depends_on: ["BR-ORG-002", "BR-ORG-004"]
functional_depends_on: ["FR-ORG-002", "FR-ORG-004"]
planned_verified_by: ["UAT-CTL-001"]
```

**Functional intent**

Quản lý trạng thái chung cho chứng từ và phân biệt rõ Nháp, Chờ xem xét, Đã duyệt, Đã ghi nhận chính thức, Hoàn tất, Hủy và Đảo/Điều chỉnh. Các module có thể mở rộng trạng thái nghiệp vụ riêng nhưng không được làm sai ý nghĩa trạng thái chung. Quản lý số chứng từ theo loại, công ty, chi nhánh, kỳ và chính sách đánh số được phê duyệt. Thể hiện quan hệ giữa chứng từ nguồn, chứng từ phát sinh, chứng từ thay thế, chứng từ điều chỉnh và chứng từ đảo.

**Source-derived functional rules**

- `FR-CTL-001-R01` — Quản lý trạng thái chung cho chứng từ và phân biệt rõ Nháp, Chờ xem xét, Đã duyệt, Đã ghi nhận chính thức, Hoàn tất, Hủy và Đảo/Điều chỉnh.
- `FR-CTL-001-R02` — Các module có thể mở rộng trạng thái nghiệp vụ riêng nhưng không được làm sai ý nghĩa trạng thái chung.
- `FR-CTL-001-R03` — Quản lý số chứng từ theo loại, công ty, chi nhánh, kỳ và chính sách đánh số được phê duyệt.
- `FR-CTL-001-R04` — Thể hiện quan hệ giữa chứng từ nguồn, chứng từ phát sinh, chứng từ thay thế, chứng từ điều chỉnh và chứng từ đảo.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-CTL-001.01` | Ý nghĩa trạng thái chung được thống nhất giữa các miền và có thể ánh xạ trạng thái module về trạng thái chung khi cần báo cáo/kiểm soát. |
| `FR-CTL-001.02` | Số đã cấp chính thức không bị tái sử dụng. |
| `FR-CTL-001.03` | Người kiểm tra biết chứng từ nào tạo ra hoặc thay đổi số liệu hiện tại. |

**Traceability note**

- Upstream: `BR-CTL-001` (source line ~598).
- Planned verification artifact from UR baseline: `UAT-CTL-001`.

#### FR-CTL-002 — Bảo đảm trách nhiệm, truy xuất và đối chiếu xuyên chứng từ

```yaml
id: "FR-CTL-002"
title: "Bảo đảm trách nhiệm, truy xuất và đối chiếu xuyên chứng từ"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-CTL-002"
upstream_depends_on: ["BR-CTL-001", "BR-IAM-003"]
functional_depends_on: ["FR-CTL-001", "FR-IAM-003"]
planned_verified_by: ["UAT-CTL-002"]
```

**Functional intent**

Truy xuất người tạo, người phê duyệt, người ghi nhận, người điều chỉnh, thời điểm, lý do và tài liệu liên quan. Bảo đảm tổng số lượng, tổng tiền, tổng công nợ và tổng tồn giữa các chứng từ liên quan có thể đối chiếu khi các source capability tương ứng nằm trong approved scope. Ghi nhận các sự kiện nghiệp vụ quan trọng của capability đang nằm trong approved scope, ví dụ bản ghi chuẩn được công bố, đơn được xác nhận, nhận hàng và xuất kho trong P1; các event class như production completion, invoice, payment, return hoặc payroll lock chỉ trở thành acceptance obligation khi source capability tương ứng được approved.

**Source-derived functional rules**

- `FR-CTL-002-R01` — Truy xuất người tạo, người phê duyệt, người ghi nhận, người điều chỉnh, thời điểm, lý do và tài liệu liên quan.
- `FR-CTL-002-R02` — Bảo đảm tổng số lượng, tổng tiền, tổng công nợ và tổng tồn giữa các chứng từ liên quan có thể đối chiếu khi các source capability tương ứng nằm trong approved scope.
- `FR-CTL-002-R03` — Ghi nhận các sự kiện nghiệp vụ quan trọng của capability đang nằm trong approved scope, ví dụ bản ghi chuẩn được công bố, đơn được xác nhận, nhận hàng và xuất kho trong P1; các event class như production completion, invoice, payment, return hoặc payroll lock chỉ trở thành acceptance obligation khi source capability tương ứng được approved.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-CTL-002.01` | Mẫu giao dịch quan trọng thuộc capability đang trong approved scope có hồ sơ trách nhiệm đầy đủ. |
| `FR-CTL-002.02` | Đối với reconciliation class có source capability đang trong approved scope, chênh lệch có tổng kiểm soát, chủ sở hữu và trạng thái xử lý. |
| `FR-CTL-002.03` | Mỗi event class thuộc acceptance scope xác định được thời điểm, đối tượng, chủ sở hữu và kết quả nghiệp vụ. |
| `FR-CTL-002.04` | Source capability chưa nằm trong approved scope không buộc phải tạo event hoặc dữ liệu giả chỉ để thỏa BR-CTL-002. |

**Traceability note**

- Upstream: `BR-CTL-002` (source line ~619).
- Planned verification artifact from UR baseline: `UAT-CTL-002`.

#### FR-CTL-004 — Quản lý business approval và Shared Workflow

```yaml
id: "FR-CTL-004"
title: "Quản lý business approval và Shared Workflow"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-CTL-004"
upstream_depends_on: ["BR-CTL-001", "BR-IAM-003"]
functional_depends_on: ["FR-CTL-001", "FR-IAM-003"]
planned_verified_by: ["UAT-CTL-004"]
```

**Functional intent**

Một khung business approval dùng chung có thể áp dụng theo loại chứng từ, giá trị, rủi ro, Company, đơn vị và vai trò, bao gồm một hoặc nhiều cấp, từ chối, trả lại để chỉnh sửa, hủy, chuyển cấp, ủy quyền và lịch sử quyết định. Business approval semantics và Shared Workflow Engine được quản lý thống nhất trong capability này. Một Shared Workflow Engine dùng chung để thực thi business approval cần governance/audit, gồm điều kiện phê duyệt, participant/người phê duyệt, trạng thái workflow, ủy quyền và lịch sử quyết định. State transition thuần túy của module không bắt buộc dùng Workflow. Approval Workflow không cấp Permission truy cập. Phân tách trách nhiệm quản trị Workflow: Developer tạo Workflow Type; System Admin triển khai và quản trị Workflow; Business Owner cấu hình Rule và Participant trong phạm vi được cho phép.

**Source-derived functional rules**

- `FR-CTL-004-R01` — Một khung business approval dùng chung có thể áp dụng theo loại chứng từ, giá trị, rủi ro, Company, đơn vị và vai trò, bao gồm một hoặc nhiều cấp, từ chối, trả lại để chỉnh sửa, hủy, chuyển cấp, ủy quyền và lịch sử quyết định.
- `FR-CTL-004-R02` — Business approval semantics và Shared Workflow Engine được quản lý thống nhất trong capability này.
- `FR-CTL-004-R03` — Một Shared Workflow Engine dùng chung để thực thi business approval cần governance/audit, gồm điều kiện phê duyệt, participant/người phê duyệt, trạng thái workflow, ủy quyền và lịch sử quyết định.
- `FR-CTL-004-R04` — State transition thuần túy của module không bắt buộc dùng Workflow.
- `FR-CTL-004-R05` — Approval Workflow không cấp Permission truy cập.
- `FR-CTL-004-R06` — Phân tách trách nhiệm quản trị Workflow: Developer tạo Workflow Type; System Admin triển khai và quản trị Workflow; Business Owner cấu hình Rule và Participant trong phạm vi được cho phép.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-CTL-004.01` | Mỗi chứng từ thuộc diện business approval xác định được rule/cấp phê duyệt, người quyết định, kết quả, thời điểm, lý do và tình trạng hiệu lực của quyết định. |
| `FR-CTL-004.02` | Mỗi giao dịch thuộc diện business approval xác định được workflow, rule áp dụng, participant/người duyệt, trạng thái và lịch sử quyết định. |
| `FR-CTL-004.03` | Quyết định workflow không tạo Permission mới ngoài quyền đã được Authorization cho phép. |
| `FR-CTL-004.04` | Mỗi Workflow Type xác định được nguồn tạo, phạm vi triển khai, người quản trị và Business Owner phụ trách rule/participant. |
| `FR-CTL-004.05` | Business Owner không thay đổi Workflow Type ở mức developer và System Admin không thay đổi rule nghiệp vụ ngoài phạm vi được giao mà không có lịch sử. |

**Traceability note**

- Upstream: `BR-CTL-004` (source line ~659).
- Planned verification artifact from UR baseline: `UAT-CTL-004`.

### 4.5 Audit & Traceability

#### FR-AUD-001 — Quản lý Central Audit Trail

```yaml
id: "FR-AUD-001"
title: "Quản lý Central Audit Trail"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-AUD-001"
upstream_depends_on: ["BR-IAM-001"]
functional_depends_on: ["FR-IAM-001"]
references: ["BR-CTL-002"]
planned_verified_by: ["UAT-AUD-001"]
```

**Functional intent**

Một Central Audit Trail cho thay đổi dữ liệu và nghiệp vụ quan trọng, gồm Before Value, After Value, Reason, Approval, Attachment, người thực hiện và thời điểm thay đổi. Central Audit chịu trách nhiệm business/data-change audit; IAM audit chỉ chịu trách nhiệm các sự kiện xác thực, truy cập và quản trị Authorization. BR-CTL-002 có thể cung cấp consumer/event/reconciliation context nhưng không phải Hard Prerequisite để Central Audit Trail tồn tại.

**Source-derived functional rules**

- `FR-AUD-001-R01` — Một Central Audit Trail cho thay đổi dữ liệu và nghiệp vụ quan trọng, gồm Before Value, After Value, Reason, Approval, Attachment, người thực hiện và thời điểm thay đổi.
- `FR-AUD-001-R02` — Central Audit chịu trách nhiệm business/data-change audit; IAM audit chỉ chịu trách nhiệm các sự kiện xác thực, truy cập và quản trị Authorization.
- `FR-AUD-001-R03` — BR-CTL-002 có thể cung cấp consumer/event/reconciliation context nhưng không phải Hard Prerequisite để Central Audit Trail tồn tại.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-AUD-001.01` | Có thể truy vết dữ liệu trước và sau thay đổi đối với nghiệp vụ quan trọng. |
| `FR-AUD-001.02` | Audit record xác định được actor/User, thời điểm, đối tượng, thay đổi, lý do và approval/attachment khi áp dụng. |
| `FR-AUD-001.03` | Có thể phân biệt rõ IAM security event với business/data-change audit event. |

**Traceability note**

- Upstream: `BR-AUD-001` (source line ~684).
- Planned verification artifact from UR baseline: `UAT-AUD-001`.
- Non-hard references: `BR-CTL-002`.

### 4.6 System Foundation

#### FR-SYS-001 — Quản lý locale và display configuration dùng chung

```yaml
id: "FR-SYS-001"
title: "Quản lý locale và display configuration dùng chung"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-SYS-001"
upstream_depends_on: ["BR-ORG-004"]
functional_depends_on: ["FR-ORG-004"]
planned_verified_by: ["UAT-SYS-001"]
```

**Functional intent**

Quản lý cấu hình dùng chung về locale/display như timezone hiển thị mặc định, định dạng ngày giờ, ký hiệu/định dạng tiền tệ, dấu phân cách và số thập phân hiển thị, ngôn ngữ và quy tắc hiển thị. Business/base/functional currency và business timezone của Company thuộc BR-ORG-004 và không được thay đổi business meaning hoặc monetary value.

**Source-derived functional rules**

- `FR-SYS-001-R01` — Quản lý cấu hình dùng chung về locale/display như timezone hiển thị mặc định, định dạng ngày giờ, ký hiệu/định dạng tiền tệ, dấu phân cách và số thập phân hiển thị, ngôn ngữ và quy tắc hiển thị.
- `FR-SYS-001-R02` — Business/base/functional currency và business timezone của Company thuộc BR-ORG-004 và không được thay đổi business meaning hoặc monetary value.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-SYS-001.01` | Cấu hình locale/display có phạm vi áp dụng, người quản lý và lịch sử thay đổi. |
| `FR-SYS-001.02` | Thay đổi locale/display không làm đổi Company business currency, business timestamp/kỳ hoặc giá trị tiền đã ghi nhận. |

**Traceability note**

- Upstream: `BR-SYS-001` (source line ~708).
- Planned verification artifact from UR baseline: `UAT-SYS-001`.

### 4.8 Master Data & Data Governance

#### FR-MDM-001 — Quản lý common reference data và chuẩn định danh

```yaml
id: "FR-MDM-001"
title: "Quản lý common reference data và chuẩn định danh"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-MDM-001"
upstream_depends_on: ["BR-ORG-002"]
functional_depends_on: ["FR-ORG-002"]
governed_by: ["AR-DATA-OWNERSHIP-001"]
planned_verified_by: ["UAT-MDM-001"]
```

**Functional intent**

Quản lý các standard, governance rules và common primitives dùng chung như đơn vị tính, nhóm/phân loại dùng chung, quốc gia, địa bàn, mã HS, thuế suất và các danh mục nền tảng khác. Ownership của reference/common data phải tuân thủ AR-DATA-OWNERSHIP-001. Product, Customer, Supplier và Employee là business entity do domain tương ứng sở hữu; MDM không trở thành owning domain thay thế. Áp dụng chuẩn định danh và quy tắc mã thống nhất cho Customer, Supplier và địa điểm. Customer và Supplier do domain tương ứng sở hữu; MDM chỉ cung cấp standard/governance cho định danh và không sở hữu business entity. Employee không thuộc phạm vi business entity của requirement này. RD Code là specialization của chuẩn định danh cho vật tư, bán thành phẩm, thành phẩm và Supplier; nó không tạo business entity/master identity cạnh tranh. Mỗi entity vẫn có internal identity, business code, RD Code khi áp dụng và optional external/partner codes. Quản lý thống nhất địa chỉ của khách hàng, nhà cung cấp, nhân viên và các đối tượng liên quan bằng controlled internal reference datasets đã được Dinktech phê duyệt, hiện gồm hai bảng `provinces` và `wards`; `wards` bao phủ đơn vị Phường/Xã theo dữ liệu được phê duyệt. Schema/field chi tiết của hai bảng được xác định ở database design và không thuộc User Requirement này. Nếu trong tương lai một danh mục được thay thế hoặc xác lập từ external legal/regulatory/official normative source, quy tắc `source_refs` tại Section 2.3 áp dụng trước khi normative rule tương ứng được approved.

**Source-derived functional rules**

- `FR-MDM-001-R01` — Quản lý các standard, governance rules và common primitives dùng chung như đơn vị tính, nhóm/phân loại dùng chung, quốc gia, địa bàn, mã HS, thuế suất và các danh mục nền tảng khác.
- `FR-MDM-001-R02` — Ownership của reference/common data phải tuân thủ AR-DATA-OWNERSHIP-001.
- `FR-MDM-001-R03` — Product, Customer, Supplier và Employee là business entity do domain tương ứng sở hữu; MDM không trở thành owning domain thay thế.
- `FR-MDM-001-R04` — Áp dụng chuẩn định danh và quy tắc mã thống nhất cho Customer, Supplier và địa điểm.
- `FR-MDM-001-R05` — Customer và Supplier do domain tương ứng sở hữu; MDM chỉ cung cấp standard/governance cho định danh và không sở hữu business entity.
- `FR-MDM-001-R06` — Employee không thuộc phạm vi business entity của requirement này.
- `FR-MDM-001-R07` — RD Code là specialization của chuẩn định danh cho vật tư, bán thành phẩm, thành phẩm và Supplier; nó không tạo business entity/master identity cạnh tranh.
- `FR-MDM-001-R08` — Mỗi entity vẫn có internal identity, business code, RD Code khi áp dụng và optional external/partner codes.
- `FR-MDM-001-R09` — Quản lý thống nhất địa chỉ của khách hàng, nhà cung cấp, nhân viên và các đối tượng liên quan bằng controlled internal reference datasets đã được Dinktech phê duyệt, hiện gồm hai bảng `provinces` và `wards`; `wards` bao phủ đơn vị Phường/Xã theo dữ liệu được phê duyệt.
- `FR-MDM-001-R10` — Schema/field chi tiết của hai bảng được xác định ở database design và không thuộc User Requirement này.
- `FR-MDM-001-R11` — Nếu trong tương lai một danh mục được thay thế hoặc xác lập từ external legal/regulatory/official normative source, quy tắc `source_refs` tại Section 2.3 áp dụng trước khi normative rule tương ứng được approved.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-MDM-001.01` | Các miền sử dụng cùng định nghĩa common primitive và không tạo danh mục chuẩn cạnh tranh cho cùng ý nghĩa. |
| `FR-MDM-001.02` | Mỗi common primitive/reference data được phân loại System Master, Group Master hoặc Company Master theo AR-DATA-OWNERSHIP-001 trước khi được công bố sử dụng. |
| `FR-MDM-001.03` | Ngoại lệ với ownership rule mặc định xác định được owning domain và căn cứ nghiệp vụ. |
| `FR-MDM-001.04` | Mỗi business entity Product, Customer, Supplier và Employee vẫn xác định được owning domain riêng. |
| `FR-MDM-001.05` | Mỗi Customer, Supplier và địa điểm có mã chuẩn phù hợp với quy tắc định danh được phê duyệt và có thể truy ra các giao dịch liên quan. |
| `FR-MDM-001.06` | Chuẩn định danh không tạo master Customer/Supplier cạnh tranh trong MDM. |
| `FR-MDM-001.07` | Mỗi đối tượng thuộc phạm vi RD Code có RD Code duy nhất, xác định được loại và nhóm; quy tắc mã có chủ sở hữu, ngày hiệu lực và không tái sử dụng mã đã dùng. |
| `FR-MDM-001.08` | RD Code liên kết tới cùng Product/Supplier canonical và không tạo duplicate Product/Supplier master. |
| `FR-MDM-001.09` | Có thể phân biệt internal system identity, business code, RD Code và external/partner code của cùng business entity. |
| `FR-MDM-001.10` | Địa chỉ mới phải tham chiếu bản ghi còn hiệu lực trong controlled datasets `provinces` và `wards`; dữ liệu lịch sử vẫn giữ được mã và tên địa chỉ đã sử dụng tại thời điểm phát sinh. |
| `FR-MDM-001.11` | Có thể xác định owner, approval status và version/effective time của controlled address datasets. Current P1 datasets `provinces`/`wards` là internal approved data và không yêu cầu `source_refs`; nếu sau này dùng external normative source thì controlled source reference phải resolve theo Section 2.3 trước approval của rule tương ứng. |

**Traceability note**

- Upstream: `BR-MDM-001` (source line ~749).
- Planned verification artifact from UR baseline: `UAT-MDM-001`.
- Governed by: `AR-DATA-OWNERSHIP-001`.

#### FR-MDM-002 — Quản lý ownership, sharing và field-level override

```yaml
id: "FR-MDM-002"
title: "Quản lý ownership, sharing và field-level override"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-MDM-002"
upstream_depends_on: ["BR-ORG-001", "BR-ORG-002", "BR-MDM-001"]
functional_depends_on: ["FR-ORG-001", "FR-ORG-002", "FR-MDM-001"]
governed_by: ["AR-DATA-OWNERSHIP-001", "AR-TXN-001"]
references: ["BR-PRC-001", "BR-PRD-005"]
planned_verified_by: ["UAT-MDM-002"]
```

**Functional intent**

Áp dụng đúng phạm vi chia sẻ, sử dụng và chỉnh sửa dữ liệu dựa trên ownership level System Master, Group Master, Company Master và Transaction Data. Người dùng chỉ được xem hoặc thay đổi dữ liệu trong phạm vi được ủy quyền. Phân loại ownership của dữ liệu theo bốn mức System Master, Group Master, Company Master và Transaction Data; Transaction Data phải thuộc đúng một Company. Reference/common data tuân thủ AR-DATA-OWNERSHIP-001. Doanh nghiệp áp dụng Field Level Override theo nguyên tắc default deny: Company chỉ được override field của Group Master khi owning-domain requirement/policy khai báo rõ field đó là overrideable; các field không được khai báo tiếp tục sử dụng giá trị Group Master. Field Level Override không tạo một business entity master mới ở Company và không thay đổi owning domain của entity. Cơ chế này không được dùng để Company sửa trực tiếp Group Pricing Policy hoặc Product Default Selling Price; khác biệt giá ở Company được biểu diễn bằng Company Pricing Policy theo BR-PRC-001; Product Default Selling Price tiếp tục được quản lý theo BR-PRD-005.

**Source-derived functional rules**

- `FR-MDM-002-R01` — Áp dụng đúng phạm vi chia sẻ, sử dụng và chỉnh sửa dữ liệu dựa trên ownership level System Master, Group Master, Company Master và Transaction Data.
- `FR-MDM-002-R02` — Người dùng chỉ được xem hoặc thay đổi dữ liệu trong phạm vi được ủy quyền.
- `FR-MDM-002-R03` — Phân loại ownership của dữ liệu theo bốn mức System Master, Group Master, Company Master và Transaction Data; Transaction Data phải thuộc đúng một Company.
- `FR-MDM-002-R04` — Reference/common data tuân thủ AR-DATA-OWNERSHIP-001.
- `FR-MDM-002-R05` — Doanh nghiệp áp dụng Field Level Override theo nguyên tắc default deny: Company chỉ được override field của Group Master khi owning-domain requirement/policy khai báo rõ field đó là overrideable; các field không được khai báo tiếp tục sử dụng giá trị Group Master.
- `FR-MDM-002-R06` — Field Level Override không tạo một business entity master mới ở Company và không thay đổi owning domain của entity.
- `FR-MDM-002-R07` — Cơ chế này không được dùng để Company sửa trực tiếp Group Pricing Policy hoặc Product Default Selling Price; khác biệt giá ở Company được biểu diễn bằng Company Pricing Policy theo BR-PRC-001; Product Default Selling Price tiếp tục được quản lý theo BR-PRD-005.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-MDM-002.01` | Dữ liệu System/Group/Company được chia sẻ đúng phạm vi và không tạo bản sao cạnh tranh chỉ để phục vụ truy cập địa phương. |
| `FR-MDM-002.02` | Transaction Data luôn xác định được đúng một Company sở hữu. |
| `FR-MDM-002.03` | Người dùng không sửa được dữ liệu ngoài ownership scope và Authorization được cấp. |
| `FR-MDM-002.04` | Mỗi loại dữ liệu thuộc phạm vi quản trị xác định được ownership level và quy tắc truy cập tương ứng. |
| `FR-MDM-002.05` | Product Master, Product Default Selling Price, Customer, Supplier, Employee và Position được phân loại Group Master. |
| `FR-MDM-002.06` | Employee Assignment, Department và Company Pricing Policy được phân loại Company Master; Group Pricing Policy được phân loại Group Master. |
| `FR-MDM-002.07` | Không có Transaction Data thiếu Company sở hữu hoặc thuộc trực tiếp Group. |
| `FR-MDM-002.08` | Có thể xác định field nào được khai báo overrideable, Company nào áp dụng, giá trị Group gốc, giá trị override và thời gian hiệu lực. |
| `FR-MDM-002.09` | Field không được owning-domain requirement/policy cho phép override không thể tạo Company override bằng configuration thông thường. |
| `FR-MDM-002.10` | Override không làm thay đổi giá trị Group Master của các Company khác. |
| `FR-MDM-002.11` | Không tạo master cạnh tranh ở Company chỉ vì một field được override. |
| `FR-MDM-002.12` | Pricing override ở Company được truy về Company Pricing Policy, không ghi đè Group Pricing Policy hoặc Product Default Selling Price. |

**Traceability note**

- Upstream: `BR-MDM-002` (source line ~779).
- Planned verification artifact from UR baseline: `UAT-MDM-002`.
- Governed by: `AR-DATA-OWNERSHIP-001`, `AR-TXN-001`.
- Non-hard references: `BR-PRC-001`, `BR-PRD-005`.

#### FR-MDM-003 — Quản lý lifecycle và thay đổi master data nhạy cảm

```yaml
id: "FR-MDM-003"
title: "Quản lý lifecycle và thay đổi master data nhạy cảm"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-MDM-003"
upstream_depends_on: ["BR-MDM-001", "BR-IAM-003", "BR-CTL-004"]
functional_depends_on: ["FR-MDM-001", "FR-IAM-003", "FR-CTL-004"]
references: ["BR-INV-001"]
planned_verified_by: ["UAT-MDM-003"]
```

**Functional intent**

Kiểm soát trạng thái, ngày hiệu lực và lý do thay đổi của bản ghi chuẩn. Cơ chế phân công và phê duyệt thay đổi đối với thuộc tính dữ liệu nhạy cảm như mã thuế, tài khoản ngân hàng, điều kiện thanh toán và các manual override thuộc diện policy cho phép. Group-level Product Moving Average Cost được hệ thống tính/cập nhật theo BR-INV-001 là system-derived value, không phải manual master-data change và không tự kích hoạt approval workflow của requirement này. Nếu một manual override đối với cost được phê duyệt ở capability/policy tương lai, override đó phải đi qua controlled sensitive-change rule riêng. Business approval thuộc requirement này sử dụng Shared Workflow để giữ quyết định và lịch sử.

**Source-derived functional rules**

- `FR-MDM-003-R01` — Kiểm soát trạng thái, ngày hiệu lực và lý do thay đổi của bản ghi chuẩn.
- `FR-MDM-003-R02` — Cơ chế phân công và phê duyệt thay đổi đối với thuộc tính dữ liệu nhạy cảm như mã thuế, tài khoản ngân hàng, điều kiện thanh toán và các manual override thuộc diện policy cho phép.
- `FR-MDM-003-R03` — Group-level Product Moving Average Cost được hệ thống tính/cập nhật theo BR-INV-001 là system-derived value, không phải manual master-data change và không tự kích hoạt approval workflow của requirement này.
- `FR-MDM-003-R04` — Nếu một manual override đối với cost được phê duyệt ở capability/policy tương lai, override đó phải đi qua controlled sensitive-change rule riêng.
- `FR-MDM-003-R05` — Business approval thuộc requirement này sử dụng Shared Workflow để giữ quyết định và lịch sử.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-MDM-003.01` | Bản ghi hết hiệu lực không được dùng cho giao dịch mới; lịch sử vẫn được bảo toàn. |
| `FR-MDM-003.02` | Thay đổi nhạy cảm thuộc diện manual/controlled change xác định được chủ sở hữu, người đề nghị, người phê duyệt, ngày hiệu lực, quyết định và bằng chứng. |
| `FR-MDM-003.03` | Không áp dụng chính thức thay đổi thuộc diện phải phê duyệt khi workflow chưa đạt trạng thái cho phép. |
| `FR-MDM-003.04` | System-derived Product Moving Average Cost từ BR-INV-001 không bị xử lý như manual sensitive-master update chỉ vì giá trị thay đổi sau eligible inbound transaction. |

**Traceability note**

- Upstream: `BR-MDM-003` (source line ~816).
- Planned verification artifact from UR baseline: `UAT-MDM-003`.
- Non-hard references: `BR-INV-001`.

### 4.9 Product

#### FR-PRD-001 — Quản lý Product Master và lifecycle vận hành

```yaml
id: "FR-PRD-001"
title: "Quản lý Product Master và lifecycle vận hành"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-PRD-001"
upstream_depends_on: ["BR-MDM-001", "BR-MDM-002", "BR-MDM-003"]
functional_depends_on: ["FR-MDM-001", "FR-MDM-002", "FR-MDM-003"]
references: ["BR-INV-001", "BR-PRD-004", "BR-INV-005"]
planned_verified_by: ["UAT-PRD-001"]
```

**Functional intent**

Quản lý Product Master như business entity thuộc Product domain và là Group Master dùng chung cho các Company trong cùng Group. Product Master bao gồm vật tư sản xuất, vật tư tiêu hao, bán thành phẩm, thành phẩm, sản phẩm vừa sản xuất vừa bán, hàng hóa thương mại, combo, quà tặng và các item vận hành phù hợp. Trong P1, `item_type=COMBO` và `item_type=GIFT` được phép dùng để phân loại Product Master; composition, component consumption, stock treatment, revenue allocation và gift semantics chỉ áp dụng khi BR-PRD-004 nằm trong approved scope. Product Master không phải đối tượng thiết kế kỹ thuật của PLM. Quản lý các thuộc tính vận hành của Product Master gồm mã, tên, mô tả, nhóm, quy cách, đơn vị tính, trọng lượng, kích thước, trạng thái kinh doanh và các thuộc tính cần thiết cho mua hàng, tồn kho, bán hàng, giao nhận hoặc sản xuất. Product Master giữ Group-level Product Moving Average Cost như một system-derived operational cost attribute được BR-INV-001 tính lại sau eligible inbound transaction; thuộc tính này không phải Company override và không tự tạo configurable Inventory Valuation Policy. Quản lý phạm vi sử dụng Product Master theo Company, kênh bán, thị trường và nhóm phân loại giá khi áp dụng. Product Master vẫn là Group Master; việc hạn chế hoặc mở phạm vi sử dụng cho Company/kênh không thay đổi ownership của Product Master. Nhóm phân loại giá chỉ là thuộc tính phân nhóm để Pricing & Promotion tham chiếu, không tự tạo mức giá hoặc chính sách giá. Quản lý trạng thái vận hành của Product Master từ nháp, đang hoạt động/kinh doanh, tạm ngừng đến ngừng sử dụng. Đây là trạng thái sử dụng trong ERP, không phải Product Development Lifecycle của PLM. Quản lý mã vạch, mã QR, mã đối tác và mã thay thế của sản phẩm.

**Source-derived functional rules**

- `FR-PRD-001-R01` — Quản lý Product Master như business entity thuộc Product domain và là Group Master dùng chung cho các Company trong cùng Group.
- `FR-PRD-001-R02` — Product Master bao gồm vật tư sản xuất, vật tư tiêu hao, bán thành phẩm, thành phẩm, sản phẩm vừa sản xuất vừa bán, hàng hóa thương mại, combo, quà tặng và các item vận hành phù hợp.
- `FR-PRD-001-R03` — Trong P1, `item_type=COMBO` và `item_type=GIFT` được phép dùng để phân loại Product Master; composition, component consumption, stock treatment, revenue allocation và gift semantics chỉ áp dụng khi BR-PRD-004 nằm trong approved scope.
- `FR-PRD-001-R04` — Product Master không phải đối tượng thiết kế kỹ thuật của PLM.
- `FR-PRD-001-R05` — Quản lý các thuộc tính vận hành của Product Master gồm mã, tên, mô tả, nhóm, quy cách, đơn vị tính, trọng lượng, kích thước, trạng thái kinh doanh và các thuộc tính cần thiết cho mua hàng, tồn kho, bán hàng, giao nhận hoặc sản xuất.
- `FR-PRD-001-R06` — Product Master giữ Group-level Product Moving Average Cost như một system-derived operational cost attribute được BR-INV-001 tính lại sau eligible inbound transaction; thuộc tính này không phải Company override và không tự tạo configurable Inventory Valuation Policy.
- `FR-PRD-001-R07` — Quản lý phạm vi sử dụng Product Master theo Company, kênh bán, thị trường và nhóm phân loại giá khi áp dụng.
- `FR-PRD-001-R08` — Product Master vẫn là Group Master; việc hạn chế hoặc mở phạm vi sử dụng cho Company/kênh không thay đổi ownership của Product Master.
- `FR-PRD-001-R09` — Nhóm phân loại giá chỉ là thuộc tính phân nhóm để Pricing & Promotion tham chiếu, không tự tạo mức giá hoặc chính sách giá.
- `FR-PRD-001-R10` — Quản lý trạng thái vận hành của Product Master từ nháp, đang hoạt động/kinh doanh, tạm ngừng đến ngừng sử dụng.
- `FR-PRD-001-R11` — Đây là trạng thái sử dụng trong ERP, không phải Product Development Lifecycle của PLM.
- `FR-PRD-001-R12` — Quản lý mã vạch, mã QR, mã đối tác và mã thay thế của sản phẩm.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-PRD-001.01` | Mỗi Product Master xác định được Group sở hữu, loại item và chính sách mua, tồn, sản xuất, bán hoặc sử dụng phù hợp. |
| `FR-PRD-001.02` | Các Company trong Group có thể tham chiếu cùng Product Master theo phạm vi được phép mà không phải tạo Product master cạnh tranh. |
| `FR-PRD-001.03` | Product Master được quản lý độc lập với Engineering Product/Development Product trong PLM. |
| `FR-PRD-001.04` | Product Master có mã duy nhất và thiếu thông tin bắt buộc thì không được công bố sử dụng. |
| `FR-PRD-001.05` | Trọng lượng, kích thước và đơn vị tính khi áp dụng có thể được sử dụng nhất quán bởi các nghiệp vụ kho, bán hàng và giao nhận. |
| `FR-PRD-001.06` | Phạm vi sử dụng Product Master được xác định rõ trước khi phát sinh giao dịch. |
| `FR-PRD-001.07` | Thay đổi phạm vi sử dụng không tạo Product Master mới chỉ để phục vụ một Company. |
| `FR-PRD-001.08` | Việc gán nhóm phân loại giá không làm thay đổi Product Default Selling Price nếu không có Pricing Policy có hiệu lực. |
| `FR-PRD-001.09` | Product Master ngừng sử dụng không được phát sinh giao dịch mới nhưng lịch sử vẫn xem được. |
| `FR-PRD-001.10` | Trạng thái Product Master không được hiểu hoặc đồng bộ ngầm thành giai đoạn thiết kế/phát triển của PLM. |
| `FR-PRD-001.11` | Có thể truy ra cùng một sản phẩm từ mỗi mã được phê duyệt. |
| `FR-PRD-001.12` | P1 cho phép phân loại Product là COMBO/GIFT nhưng không tạo acceptance obligation về composition, component consumption, stock/revenue treatment trước khi BR-PRD-004 nằm trong approved scope. |
| `FR-PRD-001.13` | Product Moving Average Cost xác định được Group, giá trị hiện hành, thời điểm cập nhật và nguồn inbound transaction gần nhất đã làm thay đổi giá trị; Company không tạo field override cạnh tranh. |

**Traceability note**

- Upstream: `BR-PRD-001` (source line ~865).
- Planned verification artifact from UR baseline: `UAT-PRD-001`.
- Non-hard references: `BR-INV-001`, `BR-PRD-004`, `BR-INV-005`.

#### FR-PRD-002 — Quản lý đơn vị tính và quy đổi Product

```yaml
id: "FR-PRD-002"
title: "Quản lý đơn vị tính và quy đổi Product"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-PRD-002"
upstream_depends_on: ["BR-PRD-001"]
functional_depends_on: ["FR-PRD-001"]
references: ["NFR-DAT-001"]
planned_verified_by: ["UAT-PRD-002"]
```

**Functional intent**

Hỗ trợ nhiều đơn vị tính và quy đổi có kiểm soát cho mua, tồn, sản xuất và bán. Mỗi Product có đúng một base UOM trong một thời điểm hiệu lực. Conversion phải khai báo rõ from-UOM, to-UOM, chiều quy đổi, hệ số dương và thời gian hiệu lực; các effective period cạnh tranh cho cùng conversion context không được overlap. Precision/rounding của quantity tuân thủ NFR-DAT-001 và không làm mất source quantity trước bước rounding được công bố.

**Source-derived functional rules**

- `FR-PRD-002-R01` — Hỗ trợ nhiều đơn vị tính và quy đổi có kiểm soát cho mua, tồn, sản xuất và bán.
- `FR-PRD-002-R02` — Mỗi Product có đúng một base UOM trong một thời điểm hiệu lực.
- `FR-PRD-002-R03` — Conversion phải khai báo rõ from-UOM, to-UOM, chiều quy đổi, hệ số dương và thời gian hiệu lực; các effective period cạnh tranh cho cùng conversion context không được overlap.
- `FR-PRD-002-R04` — Precision/rounding của quantity tuân thủ NFR-DAT-001 và không làm mất source quantity trước bước rounding được công bố.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-PRD-002.01` | Mỗi Product có đúng một base UOM đang có hiệu lực tại một thời điểm. |
| `FR-PRD-002.02` | Mỗi conversion xác định được from-UOM, to-UOM, chiều, hệ số > 0 và thời gian hiệu lực. |
| `FR-PRD-002.03` | Không tồn tại hai conversion record đang cùng hiệu lực và cạnh tranh cho cùng Product/from-UOM/to-UOM/conversion context. |
| `FR-PRD-002.04` | Quy đổi bảo toàn source quantity và sử dụng precision/rounding theo NFR-DAT-001; kết quả có thể truy lại source quantity, conversion factor và converted quantity. |
| `FR-PRD-002.05` | Conversion hết hiệu lực không được dùng cho giao dịch mới nhưng giao dịch lịch sử vẫn giữ conversion context đã áp dụng. |

**Traceability note**

- Upstream: `BR-PRD-002` (source line ~901).
- Planned verification artifact from UR baseline: `UAT-PRD-002`.
- Non-hard references: `NFR-DAT-001`.

#### FR-PRD-005 — Quản lý Product Default Selling Price

```yaml
id: "FR-PRD-005"
title: "Quản lý Product Default Selling Price"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-PRD-005"
upstream_depends_on: ["BR-PRD-001", "BR-ORG-004"]
functional_depends_on: ["FR-PRD-001", "FR-ORG-004"]
references: ["BR-MDM-002", "BR-PRC-001"]
planned_verified_by: ["UAT-PRD-005"]
```

**Functional intent**

Quản lý Product Default Selling Price thuộc Product domain ở Group level để làm giá cơ sở cuối cùng cho giao dịch bán khi không có Company hoặc Group Pricing Policy/Price List hợp lệ áp dụng. Mỗi default price xác định currency. Các Company trong cùng Group sử dụng giá mặc định này theo phạm vi giao dịch; không tồn tại Company default-price field override mặc định. Khác biệt giá theo Company được quản lý bằng Company Pricing Policy theo BR-PRC-001. Product không bắt buộc phải được đưa vào một bảng giá hoặc Pricing Policy riêng. BR-MDM-002 cung cấp governance/ownership semantics liên quan nhưng không phải Hard Prerequisite để Product Default Selling Price tồn tại.

**Source-derived functional rules**

- `FR-PRD-005-R01` — Quản lý Product Default Selling Price thuộc Product domain ở Group level để làm giá cơ sở cuối cùng cho giao dịch bán khi không có Company hoặc Group Pricing Policy/Price List hợp lệ áp dụng.
- `FR-PRD-005-R02` — Mỗi default price xác định currency.
- `FR-PRD-005-R03` — Các Company trong cùng Group sử dụng giá mặc định này theo phạm vi giao dịch; không tồn tại Company default-price field override mặc định.
- `FR-PRD-005-R04` — Khác biệt giá theo Company được quản lý bằng Company Pricing Policy theo BR-PRC-001.
- `FR-PRD-005-R05` — Product không bắt buộc phải được đưa vào một bảng giá hoặc Pricing Policy riêng.
- `FR-PRD-005-R06` — BR-MDM-002 cung cấp governance/ownership semantics liên quan nhưng không phải Hard Prerequisite để Product Default Selling Price tồn tại.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-PRD-005.01` | Product Master chưa thuộc Pricing Policy có hiệu lực vẫn xác định được giá bán cơ sở từ Product Default Selling Price của Group. |
| `FR-PRD-005.02` | Khi có Company hoặc Group Pricing Policy hợp lệ áp dụng, giá theo policy được ưu tiên theo precedence của BR-PRC-001 và nguồn giá cuối cùng có thể truy vết. |
| `FR-PRD-005.03` | Product Default Selling Price xác định được Group, currency, thời gian hiệu lực và lịch sử thay đổi. |
| `FR-PRD-005.04` | Không tạo Company-specific default-price field override; khác biệt giá theo Company được biểu diễn bằng Company Pricing Policy. |

**Traceability note**

- Upstream: `BR-PRD-005` (source line ~962).
- Planned verification artifact from UR baseline: `UAT-PRD-005`.
- Non-hard references: `BR-MDM-002`, `BR-PRC-001`.

### 4.11 Customer & CRM

#### FR-CUS-001 — Quản lý Customer Master, hồ sơ và lifecycle

```yaml
id: "FR-CUS-001"
title: "Quản lý Customer Master, hồ sơ và lifecycle"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-CUS-001"
upstream_depends_on: ["BR-MDM-001", "BR-MDM-002"]
functional_depends_on: ["FR-MDM-001", "FR-MDM-002"]
planned_verified_by: ["UAT-CUS-001"]
```

**Functional intent**

Quản lý Customer như business entity thuộc Customer domain và là Group Master dùng chung cho các Company trong cùng Group. Customer có thể là khách lẻ, đại lý, doanh nghiệp hoặc nhà phân phối và có thông tin liên hệ, địa chỉ, mã số thuế, nhóm khách hàng và trạng thái phù hợp. Quản lý thông tin khách hàng theo danh mục, nhóm, địa chỉ giao dịch, nhiều đầu mối liên hệ, trạng thái và vòng đời từ tiềm năng đến ngừng giao dịch.

**Source-derived functional rules**

- `FR-CUS-001-R01` — Quản lý Customer như business entity thuộc Customer domain và là Group Master dùng chung cho các Company trong cùng Group.
- `FR-CUS-001-R02` — Customer có thể là khách lẻ, đại lý, doanh nghiệp hoặc nhà phân phối và có thông tin liên hệ, địa chỉ, mã số thuế, nhóm khách hàng và trạng thái phù hợp.
- `FR-CUS-001-R03` — Quản lý thông tin khách hàng theo danh mục, nhóm, địa chỉ giao dịch, nhiều đầu mối liên hệ, trạng thái và vòng đời từ tiềm năng đến ngừng giao dịch.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-CUS-001.01` | Mỗi Customer có Group sở hữu, mã duy nhất trong phạm vi áp dụng và chủ sở hữu nghiệp vụ. |
| `FR-CUS-001.02` | Các Company trong Group có thể sử dụng cùng Customer theo quyền được cấp mà không phải tạo Customer master cạnh tranh. |
| `FR-CUS-001.03` | Mỗi khách hàng có hồ sơ thống nhất, trạng thái hiện tại, lịch sử trạng thái và các địa chỉ/đầu mối còn hiệu lực. |

**Traceability note**

- Upstream: `BR-CUS-001` (source line ~1055).
- Planned verification artifact from UR baseline: `UAT-CUS-001`.

### 4.12 Pricing & Promotion

#### FR-PRC-001 — Quản lý Pricing Policy và canonical price resolution

```yaml
id: "FR-PRC-001"
title: "Quản lý Pricing Policy và canonical price resolution"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-PRC-001"
upstream_depends_on: ["BR-PRD-005"]
functional_depends_on: ["FR-PRD-005"]
references: ["BR-CUS-001", "BR-CTL-004", "BR-FIN-005"]
planned_verified_by: ["UAT-PRC-001"]
```

**Functional intent**

Pricing Policy/Price List được quản lý ở Group hoặc Company theo điều kiện, currency và thời gian hiệu lực. Company không sửa trực tiếp Group Pricing Policy mà tạo Company Pricing Policy riêng; policy chỉ cần khai báo các trường hợp khác với Product Default Selling Price. Canonical price resolution áp dụng precedence: Company Pricing Policy hợp lệ > Group Pricing Policy hợp lệ > Product Default Selling Price. Pricing Policy có thể tồn tại mà chưa tham chiếu Customer cụ thể; Customer-specific condition chỉ trở thành applicable khi BR-CUS-001/customer context tương ứng có trong scope. Shared Workflow không phải Hard Prerequisite của Pricing; khi approval rule/policy yêu cầu business approval, lifecycle của Pricing Policy phải tham chiếu BR-CTL-004 và chỉ có hiệu lực sau trạng thái workflow cho phép. Price source và transaction giữ currency; khi currency conversion áp dụng phải dùng exchange-rate context theo BR-FIN-005 và giữ original value/currency, FX rate/source/effective time và converted value.

**Source-derived functional rules**

- `FR-PRC-001-R01` — Pricing Policy/Price List được quản lý ở Group hoặc Company theo điều kiện, currency và thời gian hiệu lực.
- `FR-PRC-001-R02` — Company không sửa trực tiếp Group Pricing Policy mà tạo Company Pricing Policy riêng; policy chỉ cần khai báo các trường hợp khác với Product Default Selling Price.
- `FR-PRC-001-R03` — Canonical price resolution áp dụng precedence: Company Pricing Policy hợp lệ > Group Pricing Policy hợp lệ > Product Default Selling Price.
- `FR-PRC-001-R04` — Pricing Policy có thể tồn tại mà chưa tham chiếu Customer cụ thể; Customer-specific condition chỉ trở thành applicable khi BR-CUS-001/customer context tương ứng có trong scope.
- `FR-PRC-001-R05` — Shared Workflow không phải Hard Prerequisite của Pricing; khi approval rule/policy yêu cầu business approval, lifecycle của Pricing Policy phải tham chiếu BR-CTL-004 và chỉ có hiệu lực sau trạng thái workflow cho phép.
- `FR-PRC-001-R06` — Price source và transaction giữ currency; khi currency conversion áp dụng phải dùng exchange-rate context theo BR-FIN-005 và giữ original value/currency, FX rate/source/effective time và converted value.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-PRC-001.01` | Mỗi Pricing Policy xác định được ownership level Group hoặc Company, chủ sở hữu, phạm vi áp dụng, điều kiện, currency, thời gian hiệu lực và lịch sử thay đổi. |
| `FR-PRC-001.02` | Company Pricing Policy không ghi đè nội dung của Group Pricing Policy mà tồn tại như policy object riêng trong Company. |
| `FR-PRC-001.03` | Khi đồng thời có nhiều policy thỏa điều kiện cho cùng pricing context/currency, Company Pricing Policy có precedence cao hơn Group Pricing Policy. |
| `FR-PRC-001.04` | Sản phẩm không thuộc Company hoặc Group Pricing Policy có hiệu lực vẫn sử dụng Product Default Selling Price của Group khi default price applicable cho pricing context/currency. |
| `FR-PRC-001.05` | Nguồn giá cuối cùng có thể truy vết về Company Pricing Policy, Group Pricing Policy hoặc Product Default Selling Price cụ thể. |
| `FR-PRC-001.06` | Nếu có Company Pricing Policy hợp lệ áp dụng cho pricing context/currency, hệ thống dùng Company Pricing Policy và không dùng Group Pricing Policy cho cùng quyết định giá. |
| `FR-PRC-001.07` | Nếu không có Company Pricing Policy phù hợp nhưng có Group Pricing Policy hợp lệ cho pricing context/currency, hệ thống dùng Group Pricing Policy. |
| `FR-PRC-001.08` | Nếu không có Company hoặc Group Pricing Policy phù hợp, hệ thống dùng Product Default Selling Price của Group khi applicable. |
| `FR-PRC-001.09` | Có thể giải thích điều kiện áp dụng và nguồn giá cuối cùng là Company Pricing Policy, Group Pricing Policy hay Product Default Selling Price cụ thể. |
| `FR-PRC-001.10` | Khi currency conversion được áp dụng, giao dịch giữ được original price/value, original currency, FX rate/source/effective time và converted value theo BR-FIN-005; conversion không tạo một Pricing Policy cạnh tranh. |
| `FR-PRC-001.11` | Pricing Policy không yêu cầu Customer cụ thể để tồn tại; customer-specific rule chỉ được evaluate khi customer context tương ứng tồn tại. |
| `FR-PRC-001.12` | Khi approval rule yêu cầu Shared Workflow, Pricing Policy không có hiệu lực trước khi workflow đạt trạng thái cho phép; khi approval rule không yêu cầu, không tạo workflow giả chỉ để thỏa capability. |

**Traceability note**

- Upstream: `BR-PRC-001` (source line ~1138).
- Planned verification artifact from UR baseline: `UAT-PRC-001`.
- Non-hard references: `BR-CUS-001`, `BR-CTL-004`, `BR-FIN-005`.

### 4.13 Supplier

#### FR-SUP-001 — Quản lý Supplier Master và thuộc tính nhạy cảm

```yaml
id: "FR-SUP-001"
title: "Quản lý Supplier Master và thuộc tính nhạy cảm"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-SUP-001"
upstream_depends_on: ["BR-MDM-001", "BR-MDM-002", "BR-MDM-003"]
functional_depends_on: ["FR-MDM-001", "FR-MDM-002", "FR-MDM-003"]
planned_verified_by: ["UAT-SUP-001"]
```

**Functional intent**

Supplier là Group Master thuộc Supplier domain; Purchase chỉ consume Supplier trong sourcing, Purchase Order, receiving và supplier evaluation, không tạo Supplier master cạnh tranh. Kiểm soát thay đổi tài khoản ngân hàng, mã số thuế và điều khoản thanh toán của nhà cung cấp như sensitive master-data change. Thay đổi thuộc phạm vi này phải tuân thủ cơ chế phê duyệt tại BR-MDM-003 và đi qua Shared Workflow trước khi có hiệu lực.

**Source-derived functional rules**

- `FR-SUP-001-R01` — Supplier là Group Master thuộc Supplier domain; Purchase chỉ consume Supplier trong sourcing, Purchase Order, receiving và supplier evaluation, không tạo Supplier master cạnh tranh.
- `FR-SUP-001-R02` — Kiểm soát thay đổi tài khoản ngân hàng, mã số thuế và điều khoản thanh toán của nhà cung cấp như sensitive master-data change.
- `FR-SUP-001-R03` — Thay đổi thuộc phạm vi này phải tuân thủ cơ chế phê duyệt tại BR-MDM-003 và đi qua Shared Workflow trước khi có hiệu lực.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-SUP-001.01` | Mỗi Supplier có Group sở hữu, immutable internal identity, business code/RD Code theo policy áp dụng và trạng thái hợp tác. |
| `FR-SUP-001.02` | Các Company trong Group có thể sử dụng cùng Supplier theo quyền được cấp mà không phải tạo Supplier master cạnh tranh. |
| `FR-SUP-001.03` | Purchase sử dụng Supplier Master canonical và không trở thành owning domain của Supplier. |
| `FR-SUP-001.04` | Supplier ngừng hợp tác không phát sinh cam kết mới. |
| `FR-SUP-001.05` | Thay đổi nhạy cảm xác định được người đề nghị, người xác minh/phê duyệt, quyết định, ngày hiệu lực và lịch sử trước/sau. |
| `FR-SUP-001.06` | Thay đổi thuộc diện phê duyệt không có hiệu lực khi Shared Workflow chưa đạt trạng thái cho phép. |

**Traceability note**

- Upstream: `BR-SUP-001` (source line ~1193).
- Planned verification artifact from UR baseline: `UAT-SUP-001`.

### 4.14 Purchase

#### FR-PUR-001 — Quản lý Purchase Request và sourcing

```yaml
id: "FR-PUR-001"
title: "Quản lý Purchase Request và sourcing"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-PUR-001"
upstream_depends_on: ["BR-ORG-002", "BR-SUP-001"]
functional_depends_on: ["FR-ORG-002", "FR-SUP-001"]
planned_verified_by: ["UAT-PUR-001"]
```

**Functional intent**

Ghi nhận Purchase Request cho vật tư, hàng hóa, dịch vụ và chi phí với lý do, đơn vị yêu cầu và thời điểm cần. Purchase Request có thể được tạo trước khi chọn Supplier. Mỗi purchase line được phân loại `Stock Item`, `Non-stock Item`, `Service` hoặc `Expense`; Product Master chỉ bắt buộc khi policy của loại dòng yêu cầu tham chiếu Product và không bắt buộc cho mọi Service/Expense line. Thu thập và so sánh báo giá theo giá, chất lượng, thời hạn, điều kiện giao và điều khoản thanh toán khi policy/threshold/category mua hàng yêu cầu RFQ hoặc quotation comparison. Trường hợp policy cho phép direct purchase không bắt buộc phải tạo bước quotation comparison.

**Source-derived functional rules**

- `FR-PUR-001-R01` — Ghi nhận Purchase Request cho vật tư, hàng hóa, dịch vụ và chi phí với lý do, đơn vị yêu cầu và thời điểm cần.
- `FR-PUR-001-R02` — Purchase Request có thể được tạo trước khi chọn Supplier.
- `FR-PUR-001-R03` — Mỗi purchase line được phân loại `Stock Item`, `Non-stock Item`, `Service` hoặc `Expense`; Product Master chỉ bắt buộc khi policy của loại dòng yêu cầu tham chiếu Product và không bắt buộc cho mọi Service/Expense line.
- `FR-PUR-001-R04` — Thu thập và so sánh báo giá theo giá, chất lượng, thời hạn, điều kiện giao và điều khoản thanh toán khi policy/threshold/category mua hàng yêu cầu RFQ hoặc quotation comparison.
- `FR-PUR-001-R05` — Trường hợp policy cho phép direct purchase không bắt buộc phải tạo bước quotation comparison.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-PUR-001.01` | Mỗi nhu cầu xác định được người yêu cầu, Company/đơn vị chịu chi phí, thời điểm cần, loại purchase line và trạng thái quyết định. |
| `FR-PUR-001.02` | Purchase Request có thể được ghi nhận khi chưa chọn Supplier; Supplier được bổ sung trước khi hình thành cam kết mua khi nghiệp vụ yêu cầu. |
| `FR-PUR-001.03` | Service/Expense line có thể được ghi nhận không cần Product Master; line thuộc loại/policy yêu cầu Product phải tham chiếu Product Master hợp lệ. |
| `FR-PUR-001.04` | Khi policy yêu cầu quotation comparison, quyết định lựa chọn Supplier có báo giá/căn cứ so sánh và người chịu trách nhiệm. |
| `FR-PUR-001.05` | Khi policy cho phép direct purchase, nghiệp vụ có thể tiếp tục mà không tạo RFQ/quotation giả chỉ để thỏa luồng. |

**Traceability note**

- Upstream: `BR-PUR-001` (source line ~1260).
- Planned verification artifact from UR baseline: `UAT-PUR-001`.

#### FR-PUR-002 — Quản lý Purchase Order và cam kết mua

```yaml
id: "FR-PUR-002"
title: "Quản lý Purchase Order và cam kết mua"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-PUR-002"
upstream_depends_on: ["BR-PUR-001", "BR-SUP-001"]
functional_depends_on: ["FR-PUR-001", "FR-SUP-001"]
planned_verified_by: ["UAT-PUR-002"]
```

**Functional intent**

Quản lý cam kết mua theo Purchase Order, Supplier, loại purchase line, số lượng hoặc giá trị phù hợp với loại dòng, giá, thuế và điều kiện giao hàng/thực hiện. Purchase Order có thể được tạo theo direct-purchase policy hoặc từ quotation comparison khi policy yêu cầu. Budget không thuộc phạm vi requirement này; kiểm soát ngân sách chỉ được bổ sung khi có Budget Domain hoặc Integration Requirement riêng được phê duyệt.

**Source-derived functional rules**

- `FR-PUR-002-R01` — Quản lý cam kết mua theo Purchase Order, Supplier, loại purchase line, số lượng hoặc giá trị phù hợp với loại dòng, giá, thuế và điều kiện giao hàng/thực hiện.
- `FR-PUR-002-R02` — Purchase Order có thể được tạo theo direct-purchase policy hoặc từ quotation comparison khi policy yêu cầu.
- `FR-PUR-002-R03` — Budget không thuộc phạm vi requirement này; kiểm soát ngân sách chỉ được bổ sung khi có Budget Domain hoặc Integration Requirement riêng được phê duyệt.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-PUR-002.01` | Cam kết mua đối chiếu được với Purchase Request, Supplier, loại purchase line, số lượng/giá trị, giá, thuế và điều kiện giao hàng/thực hiện đã xác nhận. |
| `FR-PUR-002.02` | Báo giá/lựa chọn Supplier được liên kết khi policy yêu cầu; direct PO được nhận diện và chỉ được dùng khi policy cho phép. |

**Traceability note**

- Upstream: `BR-PUR-002` (source line ~1283).
- Planned verification artifact from UR baseline: `UAT-PUR-002`.

#### FR-PUR-003 — Quản lý receiving và chênh lệch nhận hàng

```yaml
id: "FR-PUR-003"
title: "Quản lý receiving và chênh lệch nhận hàng"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-PUR-003"
upstream_depends_on: ["BR-PUR-002", "BR-INV-001"]
functional_depends_on: ["FR-PUR-002", "FR-INV-001"]
references: ["BR-INV-004", "BR-PUR-004"]
planned_verified_by: ["UAT-PUR-003"]
```

**Functional intent**

Kiểm soát việc nhận hàng theo Purchase Order/cam kết mua, số lượng thực nhận, partial/over/under receiving, basic quality/status và chênh lệch so với cam kết. Lot/serial chỉ bắt buộc khi policy/capability quản lý lot/serial tương ứng nằm trong approved scope. Chi phí phát sinh/landed cost chi tiết chỉ tạo acceptance obligation khi capability tương ứng nằm trong approved scope. Purchase Receipt hợp lệ là một nguồn eligible inbound transaction để BR-INV-001 cập nhật Group-level Product Moving Average Cost.

**Source-derived functional rules**

- `FR-PUR-003-R01` — Kiểm soát việc nhận hàng theo Purchase Order/cam kết mua, số lượng thực nhận, partial/over/under receiving, basic quality/status và chênh lệch so với cam kết.
- `FR-PUR-003-R02` — Lot/serial chỉ bắt buộc khi policy/capability quản lý lot/serial tương ứng nằm trong approved scope.
- `FR-PUR-003-R03` — Chi phí phát sinh/landed cost chi tiết chỉ tạo acceptance obligation khi capability tương ứng nằm trong approved scope.
- `FR-PUR-003-R04` — Purchase Receipt hợp lệ là một nguồn eligible inbound transaction để BR-INV-001 cập nhật Group-level Product Moving Average Cost.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-PUR-003.01` | Mỗi receipt line đối chiếu được với Purchase Order/cam kết mua, Product khi applicable, quantity ordered, quantity previously received, quantity received lần này và cumulative received quantity. |
| `FR-PUR-003.02` | Partial receiving được hỗ trợ; over/under receiving được nhận diện và xử lý theo policy/threshold có hiệu lực. |
| `FR-PUR-003.03` | Basic quality/status của hàng nhận được ghi nhận đủ để quyết định usable/non-usable inventory trong P1. |
| `FR-PUR-003.04` | Chênh lệch được phân loại, có chủ sở hữu và quyết định xử lý. |
| `FR-PUR-003.05` | Lot/serial hoặc landed-cost detail chỉ là acceptance obligation khi capability/policy tương ứng nằm trong approved scope; capability chưa approved không buộc phải tạo dữ liệu giả. |
| `FR-PUR-003.06` | Purchase Receipt được ghi nhận chính thức tạo source reference để BR-INV-001 tính lại Product Moving Average Cost; receipt bị hủy/điều chỉnh phải giữ traceability tới tác động cost tương ứng. |

**Traceability note**

- Upstream: `BR-PUR-003` (source line ~1303).
- Planned verification artifact from UR baseline: `UAT-PUR-003`.
- Non-hard references: `BR-INV-004`, `BR-PUR-004`.

### 4.15 Inventory

#### FR-INV-001 — Quản lý số dư, trạng thái, reservation và Moving Average Cost

```yaml
id: "FR-INV-001"
title: "Quản lý số dư, trạng thái, reservation và Moving Average Cost"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-INV-001"
upstream_depends_on: ["BR-ORG-002", "BR-PRD-001"]
functional_depends_on: ["FR-ORG-002", "FR-PRD-001"]
references: ["BR-PUR-003", "BR-MFG-003", "BR-PRD-003", "BR-INV-004", "BR-INV-005"]
planned_verified_by: ["UAT-INV-001"]
```

**Functional intent**

Quản lý canonical inventory quantity theo Company, Branch/Plant/Warehouse, location, Product và basic inventory status. Giữ hàng cho Sales Order hoặc mục đích được phê duyệt; reservation phải tách khỏi usable on-hand. P1 có thể lưu lot, serial, expiry và quality-related attributes/basic status khi dữ liệu có sẵn nhưng không yêu cầu end-to-end lot/serial/expiry traceability trước khi BR-INV-004 và BR-PRD-003 nằm trong approved scope. P1 duy trì Group-level Product Moving Average Cost như system-derived operational cost baseline: sau mỗi eligible Purchase Receipt được ghi nhận chính thức và, khi Manufacturing nằm trong approved scope, mỗi eligible Production Finished-Goods Receipt, hệ thống tính lại quantity-weighted moving average và cập nhật Product Moving Average Cost ở Group level cùng source/effective timestamp. Moving Average Cost P1 không phải configurable Inventory Valuation Policy và không tạo Company cost override; configurable inventory valuation thuộc BR-INV-005 khi capability đó nằm trong approved scope.

**Source-derived functional rules**

- `FR-INV-001-R01` — Quản lý canonical inventory quantity theo Company, Branch/Plant/Warehouse, location, Product và basic inventory status.
- `FR-INV-001-R02` — Giữ hàng cho Sales Order hoặc mục đích được phê duyệt; reservation phải tách khỏi usable on-hand.
- `FR-INV-001-R03` — P1 có thể lưu lot, serial, expiry và quality-related attributes/basic status khi dữ liệu có sẵn nhưng không yêu cầu end-to-end lot/serial/expiry traceability trước khi BR-INV-004 và BR-PRD-003 nằm trong approved scope.
- `FR-INV-001-R04` — P1 duy trì Group-level Product Moving Average Cost như system-derived operational cost baseline: sau mỗi eligible Purchase Receipt được ghi nhận chính thức và, khi Manufacturing nằm trong approved scope, mỗi eligible Production Finished-Goods Receipt, hệ thống tính lại quantity-weighted moving average và cập nhật Product Moving Average Cost ở Group level cùng source/effective timestamp.
- `FR-INV-001-R05` — Moving Average Cost P1 không phải configurable Inventory Valuation Policy và không tạo Company cost override; configurable inventory valuation thuộc BR-INV-005 khi capability đó nằm trong approved scope.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-INV-001.01` | Canonical inventory quantity theo từng chiều P1 cộng đúng lên tổng Company scope; P1 không yêu cầu canonical inventory valuation ledger trước khi BR-INV-005 nằm trong approved scope. |
| `FR-INV-001.02` | Có thể biết usable on-hand, reserved quantity, non-usable/quarantine quantity và in-transit quantity khi trạng thái tương ứng áp dụng. |
| `FR-INV-001.03` | Hàng ở trạng thái non-usable/quarantine không được cấp phát cho mục đích thông thường. |
| `FR-INV-001.04` | Lot/serial/expiry có thể được lưu như optional/basic attributes trong P1 nhưng không tạo acceptance obligation về end-to-end genealogy/traceability trước khi capability tương ứng nằm trong approved scope. |
| `FR-INV-001.05` | Mỗi Product thuộc phạm vi costing xác định được một Product Moving Average Cost hiện hành ở Group level, thời điểm cập nhật và inbound transaction làm thay đổi gần nhất. |
| `FR-INV-001.06` | Sau eligible Purchase Receipt được ghi nhận chính thức, Moving Average Cost được tính lại theo quantity-weighted moving-average rule trên eligible quantity/value đã chuẩn hóa về UOM/currency costing context. |
| `FR-INV-001.07` | Khi Manufacturing capability nằm trong approved scope, eligible Production Finished-Goods Receipt tham gia cùng Group-level Moving Average Cost rule; khi Manufacturing chưa nằm trong scope, không tạo production receipt giả để thỏa requirement. |
| `FR-INV-001.08` | Company không tạo Moving Average Cost override cạnh tranh; P1 Moving Average Cost không được diễn giải là configurable Inventory Valuation Policy của BR-INV-005. |

**Traceability note**

- Upstream: `BR-INV-001` (source line ~1353).
- Planned verification artifact from UR baseline: `UAT-INV-001`.
- Non-hard references: `BR-PUR-003`, `BR-MFG-003`, `BR-PRD-003`, `BR-INV-004`, `BR-INV-005`.

#### FR-INV-002 — Quản lý inventory movement và intra-company transfer

```yaml
id: "FR-INV-002"
title: "Quản lý inventory movement và intra-company transfer"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-INV-002"
upstream_depends_on: ["BR-INV-001", "BR-ORG-002"]
functional_depends_on: ["FR-INV-001", "FR-ORG-002"]
references: ["BR-INV-006"]
planned_verified_by: ["UAT-INV-002"]
```

**Functional intent**

Ghi nhận các inventory movement thuộc source capability đang trong approved scope, gồm nhập hàng, xuất bán và các movement P1 khác; movement từ Production, Warranty/Returns hoặc capability khác chỉ trở thành acceptance obligation khi source capability tương ứng được approved. Quản lý chuyển kho nội bộ và phân biệt hàng đang vận chuyển với hàng đã nhận. Movement giữa Branch/Plant/Warehouse thuộc cùng một Company là intra-company inventory transfer và không được ghi nhận như intercompany transaction. Movement giữa hai Company khác nhau không thuộc scope của transfer nội bộ; trong P1 phải bị chặn như unsupported cross-company movement. Khi BR-INV-006 nằm trong approved scope, cross-company movement được route theo intercompany capability đó.

**Source-derived functional rules**

- `FR-INV-002-R01` — Ghi nhận các inventory movement thuộc source capability đang trong approved scope, gồm nhập hàng, xuất bán và các movement P1 khác; movement từ Production, Warranty/Returns hoặc capability khác chỉ trở thành acceptance obligation khi source capability tương ứng được approved.
- `FR-INV-002-R02` — Quản lý chuyển kho nội bộ và phân biệt hàng đang vận chuyển với hàng đã nhận.
- `FR-INV-002-R03` — Movement giữa Branch/Plant/Warehouse thuộc cùng một Company là intra-company inventory transfer và không được ghi nhận như intercompany transaction.
- `FR-INV-002-R04` — Movement giữa hai Company khác nhau không thuộc scope của transfer nội bộ; trong P1 phải bị chặn như unsupported cross-company movement.
- `FR-INV-002-R05` — Khi BR-INV-006 nằm trong approved scope, cross-company movement được route theo intercompany capability đó.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-INV-002.01` | Mỗi biến động thuộc acceptance scope có chứng từ nguồn, người chịu trách nhiệm và lý do. |
| `FR-INV-002.02` | Kho nguồn, kho đích, số lượng và trạng thái chuyển được đối chiếu. |
| `FR-INV-002.03` | Transfer giữa các Organization Context thuộc cùng một Company giữ nguyên owning Company và không tự tạo intercompany transaction. |
| `FR-INV-002.04` | Trong P1, attempt movement giữa hai Company khác nhau bị từ chối và không được ngụy trang thành chuyển kho/chi nhánh nội bộ. |
| `FR-INV-002.05` | Khi BR-INV-006 nằm trong approved scope, cross-company movement được route theo intercompany flow tương ứng; BR-INV-006 không phải runtime prerequisite của P1. |
| `FR-INV-002.06` | Movement class của capability chưa nằm trong approved scope không buộc phải được tạo hoặc giả lập chỉ để thỏa BR-INV-002. |

**Traceability note**

- Upstream: `BR-INV-002` (source line ~1385).
- Planned verification artifact from UR baseline: `UAT-INV-002`.
- Non-hard references: `BR-INV-006`.

#### FR-INV-003 — Quản lý kiểm kê, điều chỉnh và kiểm soát tồn bất thường

```yaml
id: "FR-INV-003"
title: "Quản lý kiểm kê, điều chỉnh và kiểm soát tồn bất thường"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-INV-003"
upstream_depends_on: ["BR-INV-001", "BR-CTL-004", "BR-ORG-004"]
functional_depends_on: ["FR-INV-001", "FR-CTL-004", "FR-ORG-004"]
references: ["BR-FIN-006"]
planned_verified_by: ["UAT-INV-003"]
```

**Functional intent**

Kiểm kê định kỳ hoặc theo rủi ro, ghi nhận chênh lệch và phê duyệt điều chỉnh. Kiểm soát tồn âm và điều chỉnh hồi tố theo policy P1. Control đối với inventory movement sau financial-period close chỉ trở thành acceptance obligation khi BR-FIN-006/financial closing capability nằm trong approved scope; BR-ORG-004 không được diễn giải là owner của financial period.

**Source-derived functional rules**

- `FR-INV-003-R01` — Kiểm kê định kỳ hoặc theo rủi ro, ghi nhận chênh lệch và phê duyệt điều chỉnh.
- `FR-INV-003-R02` — Kiểm soát tồn âm và điều chỉnh hồi tố theo policy P1.
- `FR-INV-003-R03` — Control đối với inventory movement sau financial-period close chỉ trở thành acceptance obligation khi BR-FIN-006/financial closing capability nằm trong approved scope; BR-ORG-004 không được diễn giải là owner của financial period.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-INV-003.01` | Chênh lệch có nguyên nhân, người đếm, người kiểm tra và tác động quantity; tác động cost/value được giải thích theo Product Moving Average Cost P1 hoặc valuation capability đang có hiệu lực. |
| `FR-INV-003.02` | Ngoại lệ bị chặn hoặc được phê duyệt, có chứng từ điều chỉnh và lý do. |
| `FR-INV-003.03` | Khi financial closing chưa nằm trong approved scope, P1 không phải tạo closed financial period giả để test inventory adjustment. |
| `FR-INV-003.04` | Khi BR-FIN-006 nằm trong approved scope, movement/adjustment sau period close tuân thủ close/reopen/retrospective-adjustment policy do Finance quản lý. |

**Traceability note**

- Upstream: `BR-INV-003` (source line ~1411).
- Planned verification artifact from UR baseline: `UAT-INV-003`.
- Non-hard references: `BR-FIN-006`.

### 4.17 Sales

#### FR-SAL-001 — Quản lý quotation và sales order capture

```yaml
id: "FR-SAL-001"
title: "Quản lý quotation và sales order capture"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-SAL-001"
upstream_depends_on: ["BR-CUS-001", "BR-PRD-001", "BR-PRC-001"]
functional_depends_on: ["FR-CUS-001", "FR-PRD-001", "FR-PRC-001"]
references: ["BR-PRC-002", "BR-FIN-004", "BR-INT-001"]
planned_verified_by: ["UAT-SAL-001"]
```

**Functional intent**

Tiếp nhận/capture Sales Order và Quotation với source/channel attribution như website, sàn, điểm bán, điện thoại, nhân viên kinh doanh, đại lý hoặc nhà phân phối. Trong P1, source/channel có thể được ghi nhận thủ công hoặc từ capability đang có; việc liệt kê website/sàn không tạo Hard Prerequisite cho external integration. Quản lý báo giá, điều kiện bán, thời hạn hiệu lực, base price và các giá trị discount/tax cần lưu trên chứng từ. Giá cơ sở phải được xác định bằng canonical price-resolution rule tại BR-PRC-001. P1 không mặc nhiên cung cấp Promotion/Discount Policy Engine hoặc operational Tax Engine; khi BR-PRC-002 hoặc BR-FIN-004 nằm trong approved scope, discount/tax calculation áp dụng rule của capability tương ứng.

**Source-derived functional rules**

- `FR-SAL-001-R01` — Tiếp nhận/capture Sales Order và Quotation với source/channel attribution như website, sàn, điểm bán, điện thoại, nhân viên kinh doanh, đại lý hoặc nhà phân phối.
- `FR-SAL-001-R02` — Trong P1, source/channel có thể được ghi nhận thủ công hoặc từ capability đang có; việc liệt kê website/sàn không tạo Hard Prerequisite cho external integration.
- `FR-SAL-001-R03` — Quản lý báo giá, điều kiện bán, thời hạn hiệu lực, base price và các giá trị discount/tax cần lưu trên chứng từ.
- `FR-SAL-001-R04` — Giá cơ sở phải được xác định bằng canonical price-resolution rule tại BR-PRC-001.
- `FR-SAL-001-R05` — P1 không mặc nhiên cung cấp Promotion/Discount Policy Engine hoặc operational Tax Engine; khi BR-PRC-002 hoặc BR-FIN-004 nằm trong approved scope, discount/tax calculation áp dụng rule của capability tương ứng.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-SAL-001.01` | Đơn hàng có source/channel, mã tham chiếu, Customer hoặc thông tin khách lẻ phù hợp. |
| `FR-SAL-001.02` | P1 có thể capture source/channel mà không yêu cầu external integration; khi integration capability chưa nằm trong approved scope, source/channel có thể được nhập theo controlled business process. |
| `FR-SAL-001.03` | Giá cơ sở của báo giá truy được nguồn theo BR-PRC-001 và điều kiện được giải thích theo chính sách áp dụng tại thời điểm xác nhận. |
| `FR-SAL-001.04` | Discount/tax value được lưu cùng chứng từ khi áp dụng, nhưng việc tự tính theo promotion/tax policy chỉ là acceptance obligation khi BR-PRC-002/BR-FIN-004 tương ứng nằm trong approved scope. |

**Traceability note**

- Upstream: `BR-SAL-001` (source line ~1654).
- Planned verification artifact from UR baseline: `UAT-SAL-001`.
- Non-hard references: `BR-PRC-002`, `BR-FIN-004`, `BR-INT-001`.

#### FR-SAL-002 — Quản lý availability và fulfillment commitment

```yaml
id: "FR-SAL-002"
title: "Quản lý availability và fulfillment commitment"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-SAL-002"
upstream_depends_on: ["BR-SAL-001", "BR-INV-001"]
functional_depends_on: ["FR-SAL-001", "FR-INV-001"]
planned_verified_by: ["UAT-SAL-002"]
```

**Functional intent**

Kiểm tra khả năng đáp ứng và fulfillment commitment dựa trên inventory hiện có. P1 Available-to-Promise (ATP) được xác định bằng usable on-hand trừ reservation hiện hành trong đúng Company/inventory scope. Incoming Purchase Order, planned Production, forecast hoặc future supply khác không làm tăng ATP P1. Hệ thống vẫn có thể ghi nhận expected delivery date/commitment và phần chưa đáp ứng nhưng future supply chỉ được dùng trong ATP khi capability/phương pháp tương lai được approved.

**Source-derived functional rules**

- `FR-SAL-002-R01` — Kiểm tra khả năng đáp ứng và fulfillment commitment dựa trên inventory hiện có.
- `FR-SAL-002-R02` — P1 Available-to-Promise (ATP) được xác định bằng usable on-hand trừ reservation hiện hành trong đúng Company/inventory scope.
- `FR-SAL-002-R03` — Incoming Purchase Order, planned Production, forecast hoặc future supply khác không làm tăng ATP P1.
- `FR-SAL-002-R04` — Hệ thống vẫn có thể ghi nhận expected delivery date/commitment và phần chưa đáp ứng nhưng future supply chỉ được dùng trong ATP khi capability/phương pháp tương lai được approved.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-SAL-002.01` | ATP P1 = usable on-hand - reservation trong đúng Company/inventory scope và không bao gồm future supply. |
| `FR-SAL-002.02` | Nhân viên biết rõ số có thể cam kết, số đã giữ và phần chưa đáp ứng. |
| `FR-SAL-002.03` | Incoming PO, planned Production hoặc forecast không làm tăng ATP P1 chỉ vì tồn tại trong hệ thống. |
| `FR-SAL-002.04` | Expected delivery date/commitment được lưu tách biệt với ATP quantity và không làm thay đổi usable on-hand hoặc reservation. |

**Traceability note**

- Upstream: `BR-SAL-002` (source line ~1681).
- Planned verification artifact from UR baseline: `UAT-SAL-002`.

### 4.18 Finance Operations

#### FR-FIN-005 — Quản lý multi-currency và canonical Exchange Rate Master

```yaml
id: "FR-FIN-005"
title: "Quản lý multi-currency và canonical Exchange Rate Master"
type: functional
status: draft
target_phase: "P1_CORE"
priority: MUST
source_requirement: "BR-FIN-005"
upstream_depends_on: ["BR-ORG-004"]
functional_depends_on: ["FR-ORG-004"]
planned_verified_by: ["UAT-FIN-005"]
```

**Functional intent**

Quản lý nhiều đồng tiền và canonical Exchange Rate Master cho currency pair, rate type, rate value, source, effective time và precedence khi nhiều rate có thể áp dụng. Mọi conversion phải giữ original amount/currency, exchange-rate reference, converted amount/currency và effective context. Missing-rate behavior phải deterministic: giao dịch yêu cầu conversion không được ghi nhận chính thức nếu không resolve được một rate hợp lệ theo precedence/policy đang có hiệu lực, trừ khi controlled exception rule riêng được phê duyệt. P1 requirement này cung cấp exchange-rate/conversion baseline; realized/unrealized FX accounting revaluation không tự động thuộc P1 nếu chưa có Finance capability riêng được approved.

**Source-derived functional rules**

- `FR-FIN-005-R01` — Quản lý nhiều đồng tiền và canonical Exchange Rate Master cho currency pair, rate type, rate value, source, effective time và precedence khi nhiều rate có thể áp dụng.
- `FR-FIN-005-R02` — Mọi conversion phải giữ original amount/currency, exchange-rate reference, converted amount/currency và effective context.
- `FR-FIN-005-R03` — Missing-rate behavior phải deterministic: giao dịch yêu cầu conversion không được ghi nhận chính thức nếu không resolve được một rate hợp lệ theo precedence/policy đang có hiệu lực, trừ khi controlled exception rule riêng được phê duyệt.
- `FR-FIN-005-R04` — P1 requirement này cung cấp exchange-rate/conversion baseline; realized/unrealized FX accounting revaluation không tự động thuộc P1 nếu chưa có Finance capability riêng được approved.

**Acceptance-oriented child requirements**

| Child FR | Obligation derived from upstream success criterion |
|---|---|
| `FR-FIN-005.01` | Mỗi exchange-rate record xác định được from currency, to currency, rate type, rate value, source, effective-from/effective time, lifecycle/status và owner. |
| `FR-FIN-005.02` | Khi có nhiều rate cùng có thể áp dụng, hệ thống resolve một rate deterministic theo controlled precedence/policy và lưu được rate record đã sử dụng. |
| `FR-FIN-005.03` | Số tiền gốc, original currency, tỷ giá/rate source áp dụng, converted amount, target currency và effective time đều truy xuất được. |
| `FR-FIN-005.04` | Giao dịch cần conversion nhưng không resolve được rate hợp lệ bị chặn khỏi official posting hoặc đi qua controlled exception rule nếu rule đó được phê duyệt; không tự suy diễn rate. |
| `FR-FIN-005.05` | Exchange-rate history không bị ghi đè làm mất rate từng có hiệu lực. |
| `FR-FIN-005.06` | P1 Exchange Rate Master không mặc nhiên tạo accounting requirement về realized/unrealized FX revaluation ngoài capability được approved. |

**Traceability note**

- Upstream: `BR-FIN-005` (source line ~1863).
- Planned verification artifact from UR baseline: `UAT-FIN-005`.

## 5. Companion P1 Obligations Not Expanded in this FRS

### 5.1 Architectural rules

#### AR-DATA-OWNERSHIP-001 — Reference/Common Data Ownership Rule

- Planned realization: `DES-AR-DATA-OWNERSHIP-001`.
- Planned verification: `DES-REVIEW-AR-DATA-OWNERSHIP-001`.
- Constraints:
  - Reference/common data có ý nghĩa chuẩn độc lập với một doanh nghiệp cụ thể và cần dùng thống nhất toàn hệ thống được quản lý ở System Master.
  - Taxonomy, classification, coding set hoặc policy/reference data do doanh nghiệp định nghĩa và chia sẻ giữa các Company trong cùng Group được quản lý ở Group Master.
  - Dữ liệu chỉ có ý nghĩa hoặc hiệu lực trong một Company được quản lý ở Company Master khi requirement của owning domain xác định rõ nhu cầu Company-specific.
  - Mỗi common primitive/reference data phải được phân loại ownership trước khi được công bố sử dụng; ngoại lệ phải được owning domain ghi rõ cùng lý do nghiệp vụ.

#### AR-DOM-001 — Domain Boundary and Canonical Ownership Rule

- Planned realization: `DES-AR-DOM-001`.
- Planned verification: `DES-REVIEW-AR-DOM-001`.
- Constraints:
  - Mỗi governed business concept hoặc business entity xác định đúng một canonical owning domain ở cấp nghiệp vụ.
  - Domain khác có thể tham chiếu hoặc tiêu thụ dữ liệu nhưng không tạo master/SSOT cạnh tranh cho cùng ý nghĩa.
  - Cross-domain dependency và responsibility boundary phải được mô tả rõ trong requirement/design liên quan.
  - Thay đổi trong một domain không được âm thầm thay đổi ownership, semantic hoặc invariant của domain khác.

#### AR-TXN-001 — Business Transaction Context Invariant

- Planned realization: `DES-AR-TXN-001`.
- Planned verification: `DES-REVIEW-AR-TXN-001`.
- Constraints:
  - Mỗi business transaction thuộc đúng một owning Company; Group chỉ được suy ra qua Company và không sở hữu transaction trực tiếp.
  - Cross-company process có thể phối hợp nhiều transaction thuộc các Company khác nhau nhưng từng transaction thành phần vẫn thuộc đúng một owning Company.
  - `intercompany_reference` chỉ là correlation/reference key giữa các transaction hai phía; không phải transaction owner.
  - Mỗi business transaction xác định được business/effective timestamp, lifecycle state và responsible actor/User hoặc system context phù hợp.

### 5.2 Non-functional requirements

#### NFR-SEC-001 — Bảo vệ dữ liệu khỏi truy cập, sửa đổi hoặc xuất trái thẩm quyền

- Planned NFS: `NFS-SEC-001`.
- Planned verification: `NFR-TEST-SEC-001`.
- Acceptance baseline:
  - Mẫu kiểm tra chéo phạm vi trên các capability/surface thuộc approved scope không truy cập được dữ liệu ngoài quyền.
  - Mọi màn hình, API/data access, report, document hoặc export surface đang nằm trong approved scope áp dụng cùng Resource/Action Permission và final Authorization Evaluation tương ứng.
  - Report/document/export capability chưa nằm trong approved scope không buộc phải được triển khai giả chỉ để test NFR-SEC-001; khi capability được approved, security test tương ứng trở thành acceptance obligation.

#### NFR-AVL-001 — Đảm bảo availability trong khung giờ vận hành

- Planned NFS: `NFS-AVL-001`.
- Planned verification: `NFR-TEST-AVL-001`.
- Acceptance baseline:
  - Monthly availability trong configured operating window đã công bố đạt tối thiểu 99.5%.
  - Measurement window, timezone, thời điểm bắt đầu/kết thúc downtime và các khoảng planned maintenance được ghi nhận đủ để tái tính availability.
  - Planned maintenance chỉ được loại khỏi phép đo khi đã được phê duyệt và thông báo trước tối thiểu 48 giờ theo policy; downtime không đáp ứng cả hai điều kiện này được tính vào availability.
  - Mỗi Company/service scope thuộc acceptance xác định được operating window có hiệu lực dùng cho phép đo; thay đổi operating window có version/effective time và không hồi tố làm thay đổi kết quả đã công bố.

#### NFR-PER-001 — Đáp ứng hiệu năng tương tác vận hành

- Planned NFS: `NFS-PER-001`.
- Planned verification: `NFR-TEST-PER-001`.
- Acceptance baseline:
  - Tra cứu và điều hướng thông thường đạt p95 response time <= 2 giây dưới workload acceptance.
  - Tạo hoặc cập nhật giao dịch thông thường đạt p95 response time <= 3 giây dưới workload acceptance.
  - Truy vấn nghiệp vụ phức tạp đạt p95 response time <= 5 giây dưới workload acceptance.
  - Performance test sử dụng 100 concurrent users với workload mix được kiểm soát và data profile tối thiểu: 100000 Product, 50000 Customer, 10000 Supplier, 500000 Inventory Transaction, 250000 Sales Order và 100000 Purchase Order.
  - NFS-PER-001 ghi lại version của dataset/profile, workload mix, test environment và measurement method để cùng baseline có thể được tái kiểm thử.
  - Tác vụ dự kiến vượt response-time class tương tác có thể chạy async nếu người dùng nhận được trạng thái tiến trình, kết quả và lỗi rõ ràng.

#### NFR-AUD-001 — Bảo toàn, truy vấn và tái lập audit evidence

- Planned NFS: `NFS-AUD-001`.
- Planned verification: `NFR-TEST-AUD-001`.
- Acceptance baseline:
  - Không thể sửa hoặc xóa audit evidence bằng luồng nghiệp vụ thông thường.
  - Mọi thay đổi/quản trị đặc quyền được policy cho phép đối với audit evidence xác định được actor, thời điểm, lý do và kết quả.
  - Kiểm thử integrity phát hiện được mẫu thay đổi trái phép hoặc thiếu chuỗi bằng chứng theo tiêu chí kiểm thử NFR tương ứng.
  - Bằng chứng trước/sau và liên kết tới đối tượng cũ/mới vẫn truy cập được sau khi hủy, thay thế hoặc điều chỉnh trong retention baseline/schedule đang có hiệu lực.
  - Không có bước xử lý ngoại lệ hợp lệ nào làm mất audit history đã phát sinh trước đó.
  - Từ mẫu giao dịch và, khi capability tương ứng nằm trong approved scope, mẫu report/decision reference có thể truy ra chuỗi audit evidence và tái lập được ngữ cảnh gồm thời điểm, phạm vi và actor liên quan.
  - Evidence vẫn queryable trong toàn bộ retention baseline/schedule áp dụng và không phụ thuộc vào việc bản ghi nghiệp vụ còn ở trạng thái hiện hành.
  - Capability/report/decision surface chưa nằm trong approved scope không buộc phải được tạo giả chỉ để tạo audit evidence cho NFR này.

#### NFR-DAT-001 — Bảo toàn precision và rounding của dữ liệu định lượng

- Planned NFS: `NFS-DAT-001`.
- Planned verification: `NFR-TEST-DAT-001`.
- Acceptance baseline:
  - Quantity hỗ trợ tối thiểu 6 decimal places khi product/UOM policy yêu cầu và không làm mất giá trị nguồn trước bước rounding được công bố.
  - Unit price, exchange rate và rate khác giữ đủ internal precision để kết quả posting sau cùng đáp ứng currency/financial rounding policy.
  - Monetary posting được làm tròn theo minor unit của currency áp dụng; số tiền gốc và giá trị trước/sau rounding có thể giải thích khi cần đối soát.
  - Khi tax capability nằm trong approved scope, tax calculation tuân thủ effective tax-rounding policy do Finance quản lý tại BR-FIN-004 và có thể truy vết policy áp dụng.
  - Khi Payroll hoặc capability chịu quy tắc pháp lý chuyên biệt nằm trong approved scope, số liệu tương ứng tuân theo policy chuyên biệt đang có hiệu lực; capability chưa approved không tạo acceptance obligation cho P1.
  - Reconciliation tolerance mặc định không vượt quá 1 minor unit của currency trên một chứng từ, trừ khi policy chuyên biệt được phê duyệt quy định khác.

#### NFR-USE-001 — Đảm bảo usability, localization và semantic consistency

- Planned NFS: `NFS-USE-001`.
- Planned verification: `NFR-TEST-USE-001`.
- Acceptance baseline:
  - Người dùng đại diện xác nhận thuật ngữ và đơn vị hiển thị.
  - Thuật ngữ nghiệp vụ có bảng chuẩn hóa và người sở hữu.
  - Thay đổi locale/display setting không làm đổi business timestamp, kỳ nghiệp vụ, business currency hoặc monetary value đã ghi nhận.
  - Cùng một transaction có thể hiển thị theo locale được phép nhưng vẫn truy được canonical business timestamp/currency/value.
  - Khi HR work calendar áp dụng, ngày làm việc/ngày nghỉ được xác định từ work-calendar policy có hiệu lực và không thay đổi chỉ do user locale/display setting.

#### NFR-PLT-001 — Cung cấp ERP trên nền tảng web desktop

- Planned NFS: `NFS-PLT-001`.
- Planned verification: `NFR-TEST-PLT-001`.
- Acceptance baseline:
  - Các chức năng thuộc phạm vi ERP vận hành trên Google Chrome và Microsoft Edge desktop ở current stable và 2 major versions gần nhất tại thời điểm acceptance.
  - Acceptance test chạy trên Windows 10/11 hoặc Windows desktop version còn nằm trong support baseline của Dinktech.
  - Không coi mobile/tablet là môi trường được hỗ trợ chính thức nếu chưa có requirement riêng phê duyệt phạm vi đó.
  - Danh sách browser/OS version cụ thể dùng cho mỗi đợt acceptance được ghi lại trong artifact kiểm thử NFR để tái kiểm thử.

## 6. Wave A Detailed Functional Specification

### 6.1 Detailed-specification contract

Wave A follows the specification order approved in Phase Planning: Architecture & Organization, covering the three P1 Architectural Rules together with Organization, Employee and base IAM capabilities. This section refines the existing parent `FR-*` without creating a new business capability.

Conventions used below:

- **Source-backed** means the rule is directly supported by `REQ-ERP-001 v1.0.2` or by a P1 architectural invariant referenced by that requirement.
- **Derived operation** means the CRUD/lifecycle operation is a necessary operational expression of the source capability, but the User Requirements do not prescribe UI, API, screen flow or technical implementation.
- **TBD** marks a downstream decision that the source baseline does not define. A TBD is not an implicit requirement and must not be silently fixed by implementation.
- Actor role names are not invented. When the User Requirements do not name a business role, this specification uses **authorized User** and requires the final Resource/Action authorization decision to follow `FR-IAM-003`/`FR-IAM-005` where applicable.
- This FRS does not define database schema, endpoint path, UI layout, storage technology, authentication protocol, password/MFA policy, masking algorithm, or concrete permission-code catalog unless the upstream source explicitly requires them.

### 6.2 Wave A architecture invariants

The following invariants apply to every detailed FR in Wave A and remain design obligations rather than Functional Requirements:

| Invariant | Wave A consequence |
|---|---|
| `AR-DOM-001` | Every governed business concept/entity has one canonical owning domain; consumer domains may reference it but must not create a competing master/SSOT. |
| `AR-TXN-001` | Every Business Transaction belongs to exactly one owning Company; Group is derived through Company and never becomes the transaction owner. |
| `AR-DATA-OWNERSHIP-001` | Reference/common data must be classified into System/Group/Company ownership before use; an exception must have an explicit owning-domain rationale. |

The exact architecture realization remains planned in `DES-AR-DOM-001`, `DES-AR-TXN-001` and `DES-AR-DATA-OWNERSHIP-001`.

### 6.3 Organization & Enterprise Structure

#### 6.3.1 FR-ORG-001 — Detailed specification: Quản lý nhiều Group và Company

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | The UR does not name a concrete role. Any user-facing create/change operation is performed by an authorized User; the exact Resource/Action code is TBD in the authorization catalog. |
| Trigger | Derived operations: establish a Group Business Entity, associate its canonical Group Organization Context, establish Company membership under a Group, or retrieve Group/Company structure. |
| Hard prerequisite | None at business-requirement level. |
| Governing invariants | `AR-DOM-001`, `AR-TXN-001`. |
| Primary business objects | Group Business Entity, Company, Organization Context `type=GROUP`, Business Transaction ownership context. |
| Effective-time/state detail | Not fully defined by `BR-ORG-001`; lifecycle of Company/Organization Context is detailed by `FR-ORG-002`. No additional Group lifecycle state is invented here. |

**Inputs and context**

The minimum source-backed information required to realize this capability is: canonical Group identity; the Group Business Entity; the corresponding Organization Context `type=GROUP`; Company-to-Group association; and, when transaction ownership is evaluated, the owning Company. The exact field set, code format and identifier generation mechanism are TBD for downstream design.

**Processing and validation rules**

1. The platform shall support more than one independent Group.
2. Each Group shall have exactly one canonical Group Business Entity holding the Group's business/administrative identity.
3. Each Group Business Entity shall be represented in organizational hierarchy/scope by exactly one Organization Context `type=GROUP`.
4. A Group Organization Context shall not become a second Group master and shall not hold competing canonical business identity.
5. Creation of an Organization Context `type=GROUP` without a linked Group Business Entity shall be rejected.
6. Linking more than one Group Business Entity to the same Group Organization Context shall be rejected.
7. Business Transaction ownership shall be assigned to exactly one Company. Direct Group ownership of a Business Transaction shall be rejected.
8. When Group context is needed for a transaction, the Group shall be derived through the transaction's owning Company.
9. Cross-Group data isolation shall be preserved; a Group aggregation may summarize its Companies while retaining each transaction's owning Company.

**Outputs and retained evidence**

The capability shall make it possible to resolve: canonical Group identity, canonical Group Organization Context, Companies belonging to the Group, and owning Company for each Business Transaction. Where aggregate data is produced for a Group, Company ownership of underlying transactions remains visible/resolvable. Exact aggregation/report presentation is not specified by this FR.

**Authorization and audit obligations**

The source requires independent Group administration and no cross-Group data mixing. The exact administrative Permission codes are TBD. Any user-facing access must later be evaluated by the common IAM rules; this FR does not create an alternate authorization path. Business/data-change audit coverage is governed by the P1 Central Audit capability where the corresponding change class is configured for audit; the exact audit event taxonomy is TBD in `FR-AUD-001`/design.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-ORG-001-01` | Group A and Group B exist on the same platform | A user accesses data in Group A scope | Group B data is not mixed into Group A scope | `FR-ORG-001.01` |
| `SCN-ORG-001-02` | A Group Business Entity exists | Its Group Organization Context is resolved | Exactly one `type=GROUP` context represents that Group and it is not a competing Group master | `.02`, `.03`, `.06` |
| `SCN-ORG-001-03` | No Group Business Entity is linked | An attempt is made to create a `type=GROUP` Organization Context | The operation is rejected | `.04` |
| `SCN-ORG-001-04` | A `type=GROUP` context is already bound to one Group Business Entity | A second Group Business Entity is associated to the same context | The operation is rejected | `.05` |
| `SCN-ORG-001-05` | A Business Transaction is created | Ownership is assigned | Exactly one Company owns it; Group is derived from that Company and cannot be direct owner | `.07` |
| `SCN-ORG-001-06` | Multiple Companies belong to one Group and own transactions | Group-level aggregation is requested | Aggregation is possible without changing or losing Company ownership of the underlying transactions | `.08` |

**TBD / downstream decisions**

- Group and Company code/numbering formats.
- Group lifecycle/state vocabulary not stated in `BR-ORG-001`.
- Administrative Resource/Action Permission identifiers.
- Physical data-isolation mechanism and database/storage realization.

#### 6.3.2 FR-ORG-002 — Detailed specification: Quản lý Organization Context và hierarchy vận hành

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized User; concrete role name is not stated by the UR. |
| Trigger | Derived operations: create/change/deactivate an Organization Context, change a parent-child relation, resolve Company ownership, or render the unified organizational view. |
| Hard prerequisite | `FR-ORG-001`. |
| Governing invariants | `AR-DOM-001`, `AR-TXN-001`. |
| Controlled reference | `ORG-CONTEXT-HIERARCHY-POLICY`. |
| Allowed Organization Context types | `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE` only. |

**Inputs and context**

For each Organization Context the source requires type, owning/related Company where applicable, parent relation as applicable, lifecycle/state, and effective time. A `GROUP` context additionally resolves exactly one Group Business Entity. The hierarchy policy must expose at least owner, version, effective time, and permitted/forbidden parent-child combinations. Exact schema and the complete policy matrix are not defined in the UR.

**Processing and validation rules**

1. Business configuration shall not add Organization Context types beyond the fixed five-type set.
2. A `GROUP` context must reference exactly one canonical Group Business Entity and must not become a Group master.
3. Every Branch, Plant and Warehouse must resolve to exactly one owning Company.
4. Parent-child relationships must be validated against the effective `ORG-CONTEXT-HIERARCHY-POLICY`.
5. The hierarchy may skip intermediate levels when the policy allows it; the FRS must not hard-code an alternate mandatory chain.
6. Parent-child combinations not allowed by the effective policy shall be rejected unless that same policy defines a controlled exception.
7. Each Business Transaction remains owned by its Company regardless of the Organization Context used operationally.
8. Company, Branch, Plant and Warehouse lifecycle/state and effective time must be retained. An inactive/non-effective context cannot be used for new activity while historical data remains queryable.
9. Department and Position remain Employee-domain masters, not Organization Contexts.
10. The organizational view may display Department, Position and Employee beside Organization Context by resolving effective Employee Assignment data.
11. Displaying Department or Position in the organizational view shall not create Permission, Organization Scope or transaction ownership.
12. A Position may appear under multiple Department/Organization Context combinations through effective Employee Assignments.

**Lifecycle/state behavior**

The source requires lifecycle/state and effective time but does not define a canonical state enumeration for Company/Branch/Plant/Warehouse. Downstream design must define a controlled state model without contradicting the explicit invariant that a context that is no longer active/effective cannot generate new data and historical use remains queryable.

**Exceptions and boundaries**

- A policy-defined hierarchy exception is allowed only if present in the effective `ORG-CONTEXT-HIERARCHY-POLICY`; no free-form implementation bypass is created by this FRS.
- Department and Position hierarchy/lifecycle stay outside Organization ownership.
- Cross-company transaction ownership is not created by moving or viewing a context in the hierarchy.

**Authorization and audit obligations**

Exact permissions for hierarchy administration are TBD. User access to organization data remains subject to common IAM evaluation. History of lifecycle/effective-time and controlled policy version must be preservable; exact Central Audit event mapping remains downstream work.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-ORG-002-01` | Business configuration is available | A user attempts to add a new Organization Context type | The type is rejected unless it is one of GROUP/COMPANY/BRANCH/PLANT/WAREHOUSE | `.01`, `.02` |
| `SCN-ORG-002-02` | A Branch/Plant/Warehouse is created | Its ownership is resolved | Exactly one Company owner is identifiable | `.04` |
| `SCN-ORG-002-03` | The effective hierarchy policy permits Company -> Warehouse | A Warehouse is placed directly under a Company | The relation is accepted even without Branch/Plant intermediate nodes | `.05`, `.06`, `.15` |
| `SCN-ORG-002-04` | The effective hierarchy policy forbids a parent-child combination and has no exception | The relation is submitted | The relation is rejected | `.15`, `.16`, `.17` |
| `SCN-ORG-002-05` | An Organization Context is no longer effective | A new business activity attempts to use it | New use is rejected while historical records remain queryable | `.09` |
| `SCN-ORG-002-06` | Effective Employee Assignments reference Department and Position | The organizational view is rendered | Department/Position/Employee may appear, but no new Organization Scope, Permission or ownership is created | `.10`-`.14` |
| `SCN-ORG-002-07` | A transaction uses a Branch/Plant/Warehouse context | Transaction ownership is checked | The owning Company is unchanged and remains uniquely identifiable | `.07`, `.08` |

**TBD / downstream decisions**

- Full `ORG-CONTEXT-HIERARCHY-POLICY` matrix and controlled exception structure.
- Lifecycle/state vocabulary and transition table for Company/Branch/Plant/Warehouse.
- Administrative screen/API and permission codes.

#### 6.3.3 FR-ORG-004 — Detailed specification: Business timezone, currency và Company Business Calendar

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized User; exact organizational-administration role is not named by the source. |
| Trigger | Derived operations: establish/change effective Company timezone/currency/calendar settings; resolve business timestamp/date/currency/calendar for a transaction or deadline. |
| Hard prerequisite | `FR-ORG-002`. |
| Non-hard references | `BR-SYS-001`, `BR-FIN-006`. |
| Canonical owner | Organization/Foundation for Company Business Calendar and Company business timezone/currency context. |

**Inputs and context**

Source-backed context includes Company; business timezone; business/base/functional currency; Company Business Calendar; business day/non-business day classification; Company holiday; effective time; and business cutoff/deadline that must be calculated by business day. Exact distinction among business/base/functional currencies where multiple values are configured, code list, calendar recurrence representation and UI are downstream decisions.

**Processing and validation rules**

1. A transaction must resolve the effective Company business timezone and business/base/functional currency applicable to its business time.
2. Locale/display configuration shall never mutate canonical business timezone/date/currency or previously recorded monetary value.
3. Each Company must have an identifiable effective Business Calendar capable of classifying business day, non-business day and Company holiday.
4. Any deadline/cutoff defined in business days must resolve through the canonical Company Business Calendar.
5. Employee Work Calendar may consume and extend the Company calendar in future HR scope, but shall not create a competing Company-business-day definition.
6. Financial period lifecycle, close/reopen and periodic allocation are outside P1 ownership of this FR. They become obligations only when `BR-FIN-006` is in approved scope.

**State/effective-time behavior**

Changes to business timezone, currency or calendar settings must be effective-time aware because the source requires the policy applicable at the transaction time. The source does not define retroactive-change rules, overlap rules, or approval workflow for these settings; these remain TBD and must not be invented as P1 acceptance obligations.

**Outputs and retained context**

A consuming transaction or rule can resolve canonical business timestamp/date, relevant Company currency context, and business-day classification from the effective Company policy/calendar. Historical transaction meaning must remain stable when locale/display settings later change.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-ORG-004-01` | A transaction belongs to a Company | Its business context is resolved | Effective Company business timezone and currency policy are identifiable | `.01` |
| `SCN-ORG-004-02` | A recorded transaction exists | User locale/display is changed | Canonical business timestamp/date, business currency and monetary value do not change | `.02` |
| `SCN-ORG-004-03` | A Company Business Calendar is effective | A date is evaluated | It can be classified as business day/non-business day/Company holiday as applicable | `.03` |
| `SCN-ORG-004-04` | A deadline is defined using business days | Deadline is calculated | The effective Company Business Calendar is the canonical source | `.04` |
| `SCN-ORG-004-05` | Future HR work-calendar capability is present | HR-specific calendar extensions apply | They do not redefine the Company Business Calendar outside HR scope | `.05` |
| `SCN-ORG-004-06` | `BR-FIN-006` is not in P1 scope | P1 acceptance is executed | No financial-period close/reopen lifecycle is required from this FR | `.06` |

**TBD / downstream decisions**

- Effective-dating overlap/retroactivity rules for timezone/currency/calendar settings.
- Exact semantic distinction and allowed combinations of business/base/functional currency fields.
- Calendar storage model and cutoff-expression syntax.

### 6.4 Employee & Organizational Structure

#### 6.4.1 FR-EMP-002 — Detailed specification: Department, Position và organizational view

`FR-EMP-002` is specified before `FR-EMP-001` in Wave A because `FR-EMP-001` has it as a Hard Prerequisite.

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized User; concrete HR/organization role is not named by the UR. |
| Trigger | Derived operations: establish/change/deactivate Department or Position, or resolve them for Employee Assignment/organizational view. |
| Hard prerequisite | `FR-ORG-002`. |
| Canonical ownership | Department = Company Master in Employee domain; Position = Group Master in Employee domain. |

**Inputs and context**

Department requires owning Company, optional parent when hierarchy applies, lifecycle/state and effective time. Position requires owning Group, title/level, status and effective time. Exact codes, level taxonomy, hierarchy depth and state vocabulary are not supplied by the UR.

**Processing and validation rules**

1. Department and Position shall be maintained by the Employee domain, not Organization Context.
2. A Department must belong to exactly one Company.
3. Department hierarchy, when used, must retain parent relation, lifecycle/state and effective time.
4. A Department that is no longer effective cannot be selected for a new Employee Assignment, while historical assignments remain intact.
5. A Position must belong to a Group and expose title/level, status and effective time.
6. An Employee Assignment may reference a Department only when the Department belongs to the Assignment's Company and is effective for the relevant time.
7. An Employee Assignment may reference a Position only when the Position belongs to the corresponding Group and is effective for the relevant time.
8. Department and Position shall not independently create Permission or Organization Scope.

**Outputs and retained evidence**

Consumers can resolve the canonical Department and Position, their owner scope and effective status. Historical assignment references remain resolvable after a Department/Position becomes inactive or is superseded.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-EMP-002-01` | A Department exists | Its metadata is read | Owning Company, parent when applicable, lifecycle/state and effective time are identifiable | `.01` |
| `SCN-EMP-002-02` | A Department is no longer effective | A new Assignment attempts to reference it | The new reference is rejected; historical Assignments remain intact | `.02` |
| `SCN-EMP-002-03` | A Position exists | Its metadata is read | Owning Group, title/level, status and effective time are identifiable | `.03` |
| `SCN-EMP-002-04` | Assignment Company/Group context is resolved | Department or Position is selected | Department must match Company and Position must match corresponding Group and both must be effective | `.04` |
| `SCN-EMP-002-05` | Department/Position exists | Authorization scope is evaluated | Their existence alone creates neither Permission nor Organization Scope | `.05` |

**TBD / downstream decisions**

- Department and Position lifecycle state vocabulary and transition controls.
- Department hierarchy depth/cycle-validation policy; the source only states that Department has a hierarchy.
- Position level taxonomy and code format.

#### 6.4.2 FR-EMP-001 — Detailed specification: Employee và Employee Assignment

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized User; exact HR/employee-master role is not named by the UR. |
| Trigger | Derived operations: establish/update Employee, create or change an Employee Assignment, or resolve effective Assignment(s) for consumers such as IAM. |
| Hard prerequisites | `FR-ORG-002`, `FR-EMP-002`. |
| Canonical ownership | Employee = Group Master; Employee Assignment = Company Master; both are owned by Employee domain. |
| Cross-domain boundary | IAM and future HRP reference/extend the canonical records and must not create competing Employee/Assignment masters. |

**Inputs and context**

Employee must resolve owning Group. Employee Assignment must resolve exactly one owning Company plus Organization Context, Department, Position and effective time. The source does not define employee-code format, personal-data schema, assignment type, FTE, manager relation or assignment lifecycle state vocabulary; those are not introduced here.

**Processing and validation rules**

1. Employee identity is independent from User Identity.
2. One canonical Employee may be referenced by multiple Companies in the same Group, subject to authorization.
3. An Employee may have multiple Assignments across Company and time while retaining one canonical Employee identity.
4. Every Assignment must belong to exactly one Company.
5. Assignment Organization Context must resolve within the owning Company context.
6. Assignment Department must belong to the same Company and be effective.
7. Assignment Position must belong to the corresponding Group and be effective.
8. Assignment effective-time history must be preserved when Organization Context, Department, Position or effective period changes.
9. Consumers such as IAM/HRP must be able to identify the canonical Employee and Assignment they reference; they cannot replace them with a parallel master.

**Overlap and concurrency boundary**

The UR explicitly allows multiple Assignments by Company and time but does not define whether overlapping Assignments in the same Company are allowed, whether one is primary, or how conflicts are resolved. These rules are TBD and must be settled before implementation/UAT if relevant to P1 authorization resolution.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-EMP-001-01` | An Employee exists in a Group | A permitted Company in that Group references the Employee | The same canonical Employee is used, not a Company duplicate | `.01`, `.03` |
| `SCN-EMP-001-02` | A new Employee Assignment is created | It is validated | Exactly one Company, an Organization Context, Department, Position and effective time are resolvable | `.02` |
| `SCN-EMP-001-03` | Assignment organization/department/position changes over time | History is queried | Previous values/effective periods remain preserved | `.04` |
| `SCN-EMP-001-04` | IAM or HRP references employee data | Canonical reference is resolved | The owning Employee-domain Employee/Assignment is identifiable and no competing master is created | `.05` |

**TBD / downstream decisions**

- Rules for overlapping/multiple concurrent Assignments and primary Assignment selection.
- Employee/Assignment lifecycle-state vocabulary beyond effective-time history.
- Detailed employee personal-data fields and classification.

### 6.5 Authorization & Security

#### 6.5.1 FR-IAM-001 — Detailed specification: User Identity và Group Access Membership

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Identity/IAM administration is performed by an authorized User; exact admin role/permission codes are not stated. Runtime subject is a User Identity. |
| Trigger | Derived operations: create/change/lock/deactivate User Identity; grant/revoke Group Access Membership; resolve Group/Company/Organization Context before authorization. |
| Hard prerequisites | `FR-ORG-001`, `FR-EMP-001`. |
| Canonical boundary | User Identity is platform-level authentication principal and is independent from Employee/Employee Assignment master. |

**Inputs and context**

A User Identity requires unique identity, identity type, access/authentication status and lifecycle. Ordinary ERP User Group membership requires target Group and canonical Employee in that same Group. Exceptional System Admin/service identity without Employee requires explicit identity type, scope, owner and auditable exception basis. The source does not define credentials, password/MFA rules, authentication protocol, session duration or SSO mechanism.

**Processing and validation rules**

1. Each User Identity must be unique at platform level and have identity type, status and effective lifecycle.
2. A locked or inactive User cannot perform new transactions.
3. User Identity must not hard-reference one Employee Assignment and must not become Company membership.
4. IAM must not create Employee, Department, Position or Employee Assignment masters.
5. Ordinary ERP User access to a Group requires explicit Group Access Membership referencing a canonical Employee belonging to that Group.
6. Membership in Group A shall not create permission or data scope in Group B.
7. Cross-Group access requires explicit membership/scope for every Group involved.
8. When Company/Organization Context is needed, the system resolves effective Employee Assignment(s) for the membership's canonical Employee at evaluation time; the membership does not pin a single Assignment.
9. Permission/Policy evaluation must use the resolved Group and Company/Organization Context.
10. System Admin/service identity without Employee is permitted only as a controlled exception with explicit identity type, scope, owner and audit evidence.
11. Cross-Group data isolation applies even when one platform identity has memberships in multiple Groups; each Group context must be explicitly resolved.

**State behavior**

The source explicitly distinguishes locked/inactive state as preventing new transaction activity but does not define the full User Identity state machine, authentication status enum, membership state model or revocation timing. Those are downstream decisions.

**Outputs and evidence**

Runtime resolution must be able to provide: User Identity; selected/authorized Group membership or exceptional scope; canonical Employee when applicable; effective Employee Assignment(s) used for Company/context resolution; and evidence sufficient for later authorization explanation.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-IAM-001-01` | A User Identity exists | Identity metadata is inspected | Unique ID, identity type, status and lifecycle are available | `.01` |
| `SCN-IAM-001-02` | User is locked or inactive | A new transaction action is attempted | The action cannot proceed | `.02` |
| `SCN-IAM-001-03` | Ordinary ERP User requests Group A access | Membership is granted | Membership explicitly references Group A and an Employee canonical belonging to Group A, not one fixed Assignment | `.03`, `.05`, `.07` |
| `SCN-IAM-001-04` | User has membership only in Group A | Group B data/action is requested | No Group B permission/data scope is inferred; access requires explicit Group B membership/scope | `.06`, `.10`, `.11` |
| `SCN-IAM-001-05` | Authorization needs a Company context | Context is resolved | Effective Assignment(s) of the canonical Employee are resolved for that time/context instead of using a hard-pinned Assignment | `.07`, `.08` |
| `SCN-IAM-001-06` | A service/System Admin identity has no Employee | Exceptional identity access is configured | Identity type, explicit scope, owner and audit evidence are required | `.09` |

**TBD / downstream decisions**

- Authentication mechanism, credential and MFA/session policy.
- Complete identity and membership lifecycle state model.
- Rule for choosing among multiple simultaneously effective Employee Assignments when context is not otherwise explicit.
- Concrete admin Resource/Action permission codes.

#### 6.5.2 FR-IAM-002 — Detailed specification: Permission, Profile và Business Group

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized IAM administrator; exact role name and permission codes are TBD. |
| Trigger | Derived operations: define/change Permission metadata, compose Profile, maintain Business Group, assign mappings and resolve a User's Profile/Permission set. |
| Hard prerequisite | None. |
| Core authorization unit | Permission = canonical `Resource/Action` pair with identifier and `policy_required`. |

**Inputs and context**

Source-backed information includes Permission identifier, Resource, Action, `policy_required`; Profile and its Permission set; Business Group; Business Group-to-Profile mapping; and mapping history. The exact Resource catalog, Action catalog, naming convention, Profile assignment mechanism to individual users versus groups, and mapping lifecycle state are not defined by the source.

**Processing and validation rules**

1. Each Permission shall identify one Resource/Action combination, an identifier and canonical `policy_required` value.
2. `policy_required` belongs to the Permission itself; the value cannot vary by Profile.
3. A Profile is a set of already-defined Permissions.
4. Users receive base rights through Profile; Policy Rule cannot create or grant a Permission absent from the Profile-derived set.
5. Business Group may map users/business groupings to Profiles for administration.
6. Business Group is an authorization concept and must never be interpreted as Organization's Group Business Entity.
7. Business Group does not replace Profile, Permission or Policy Rule and cannot create rights by itself.
8. Changes to Business Group-to-Profile mapping must preserve history.
9. Effective user rights must be traceable back to the Business Group/Profile/Permission chain participating in the decision.

**Outputs and retained evidence**

The capability shall resolve an effective Profile set and its union of Permissions for a User context, while preserving the canonical metadata for each Permission. It shall retain enough mapping history to explain prior configuration. Final ALLOW/DENY remains the responsibility of `FR-IAM-003`.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-IAM-002-01` | A Permission is configured | Its metadata is read | Resource, Action, identifier and `policy_required` are all available | `.01` |
| `SCN-IAM-002-02` | The same Permission appears in multiple Profiles | `policy_required` is inspected | The same canonical value is returned in every Profile context | `.04` |
| `SCN-IAM-002-03` | A Policy Rule contains an ALLOW condition but the User has no corresponding Profile Permission | Base entitlement is resolved | No new Permission is created by the Policy | `.05` |
| `SCN-IAM-002-04` | A Business Group is mapped to a Profile | User rights are traced | Business Group, Profile and Permission chain are identifiable and Business Group is not confused with Group Business Entity | `.06`-`.08` |
| `SCN-IAM-002-05` | A Business Group-to-Profile mapping changes | History is queried | Previous mapping remains identifiable; the change itself creates no Permission | `.09`, `.10` |

**TBD / downstream decisions**

- Canonical Resource and Action catalogs and identifier naming convention.
- User/Profile assignment model and effective-time semantics not explicitly specified.
- Profile lifecycle/state and Business Group lifecycle/state.

#### 6.5.3 FR-IAM-003 — Detailed specification: Policy Based Authorization Evaluation

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Runtime subject | User Identity in a resolved Group and, when required, Company/Organization Context. |
| Trigger | Every protected Resource/Action request in P1 scope. |
| Hard prerequisites | `FR-IAM-001`, `FR-IAM-002`, `FR-ORG-002`. |
| Decision output | Final `ALLOW` or `DENY`, plus explainability evidence. |
| Determinism | Same effective inputs/policy context must produce the same authorization decision. |

**Required evaluation inputs**

At minimum, where applicable: User Identity; Group membership/exceptional scope; resolved Company/Organization Context; effective Employee Assignment(s); target Resource and Action; target resource type/state; participant relationship; actor Department resolved from effective Assignment; resource responsible Department from the resource's own responsibility/ownership; canonical Permission metadata including `policy_required`; effective Profile Permission set; applicable Policy version/rules; and rule Effect.

**Canonical evaluation algorithm**

1. **Resolve context.** Resolve Group and Company/Organization Context through `FR-IAM-001`; no permission evaluation may assume a context from another Group.
2. **Resolve base entitlement.** Build the effective Permission set from active/effective Profiles. Multiple Profiles contribute the union of Permissions.
3. **Permission gate.** If the requested Resource/Action Permission is absent, return `DENY`. Policy processing must not create the missing Permission.
4. **Resolve policy conditions.** Only after the Permission gate passes, evaluate applicable effective Policy Rules using the current Group/Company/Organization Context and supported conditions such as Resource Type, State, Participant, Department/business Condition.
5. **Department semantics.** Actor Department is resolved from effective Employee Assignment. Resource Responsible Department is read from resource ownership/responsibility. Department never becomes Organization Context or an independent Permission/Scope source.
6. **Aggregate Effect.** Apply the canonical order `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`.
7. **Resolve NO_MATCH.** If no rule matches, return `DENY` when `policy_required=true`; return `ALLOW` when `policy_required=false`.
8. **Action independence.** Evaluate view, create/submit, approve, official posting, adjust, export and administer as independent Resource/Action permissions; one action never implies another.
9. **Explain decision.** Retain/return enough evidence to identify membership/context, Profile Permission, canonical `policy_required`, applicable Policy version/rules and applied Effect.

**Policy lifecycle/history rules**

Policy must have version, effective time and preserved history. A policy update cannot overwrite a version that was previously effective. Exact policy authoring workflow, syntax, condition language and approval lifecycle are not defined by this UR and remain downstream design decisions.

**Canonical decision table**

| Base Profile Permission | Applicable aggregate Effect | `policy_required` | Final decision |
|---|---|---|---|
| Absent | Any, including ALLOW | any | `DENY` |
| Present | `ABSOLUTE_DENY` | any | `DENY` |
| Present | `ALLOW` and no `ABSOLUTE_DENY` | any | `ALLOW` |
| Present | `DENY` only | any | `DENY` |
| Present | `NO_MATCH` | `true` | `DENY` |
| Present | `NO_MATCH` | `false` | `ALLOW` |

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-IAM-003-01` | User has no requested Profile Permission but an ALLOW Policy Rule exists | Authorization is evaluated | Final result is `DENY` | `.08` |
| `SCN-IAM-003-02` | User has requested Permission and an applicable `ABSOLUTE_DENY` | Authorization is evaluated | Final result is `DENY` | `.09` |
| `SCN-IAM-003-03` | User has Permission; applicable ALLOW and ordinary DENY exist; no `ABSOLUTE_DENY` | Authorization is evaluated | Final result is `ALLOW` | `.10` |
| `SCN-IAM-003-04` | User has Permission and only applicable ordinary DENY | Authorization is evaluated | Final result is `DENY` | `.11` |
| `SCN-IAM-003-05` | User has Permission and no Policy Rule matches | `policy_required` is evaluated | `true` => DENY; `false` => ALLOW | `.12` |
| `SCN-IAM-003-06` | User has multiple effective Profiles | Permission set is resolved | Effective base Permission set is their union, still subject to Policy | `.13` |
| `SCN-IAM-003-07` | Department condition applies | Authorization is evaluated | Actor Department comes from effective Assignment; Resource Department comes from resource responsibility; Department creates no extra scope | `.04`, `.05` |
| `SCN-IAM-003-08` | A decision is returned | Explainability is requested | Membership/context, Profile Permission, `policy_required`, policy version/rules and applied Effect are traceable | `.02`, `.03`, `.14`, `.18` |
| `SCN-IAM-003-09` | User is allowed to view a resource | User attempts export/approve/adjust/admin action | Each separate action requires its own Permission and policy decision; view does not imply another action | `.15`-`.17` |

**TBD / downstream decisions**

- Policy condition DSL/data structure and authoring interface.
- Exact rule applicability/tie semantics inside the same Effect class when multiple rules match; upstream only fixes the Effect-class precedence.
- Resource/action catalog and resource-responsibility field mapping for each domain.
- Caching strategy, decision-token format or technical policy engine implementation.

#### 6.5.4 FR-IAM-005 — Detailed specification: Bảo vệ dữ liệu nhạy cảm

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Runtime subject | User performing a sensitive-data action. |
| Trigger | View, modify or export of data classified as sensitive in the applicable P1 resource context. |
| Hard prerequisite | `FR-IAM-003`. |
| Sensitive examples explicitly named upstream | Salary, identifying information, cost, discount and bank account information. |

**Processing and validation rules**

1. Sensitive-data access is action-specific and cannot rely on a generic right to the surrounding business object.
2. For the requested sensitive-data action, the corresponding Resource/Action Permission must exist in the User's base entitlement.
3. The final Authorization Evaluation from `FR-IAM-003` must return `ALLOW` in the correct Group/Company/Organization Context.
4. If the Permission is absent or the final result is anything other than `ALLOW`, view/modify/export for that action shall be denied.
5. View and export are independent rights; permission to view sensitive data does not grant export.
6. Policy Rule cannot substitute for or generate the required Permission.
7. This FR does not introduce masking/redaction as a substitute for authorization because the source does not specify such behavior.

**Sensitive-data classification boundary**

The upstream examples are not a complete field catalog. The exact list of sensitive Resource/Action pairs and the owner of that catalog must be defined downstream before UAT coverage is complete. No unlisted field is automatically declared sensitive or non-sensitive by this FRS.

**Outputs and evidence**

The protected operation receives a final ALLOW/DENY decision from common authorization. Denied actions must not expose the protected data through that action surface. Explainability follows `FR-IAM-003`. Exact security-event monitoring is P2 (`BR-IAM-006`) and is not made a P1 requirement here.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-IAM-005-01` | User lacks the relevant sensitive-data Resource/Action Permission | User attempts view/modify/export | The requested action is denied | `.01` |
| `SCN-IAM-005-02` | User has view Permission but final Authorization is not ALLOW | User attempts to view sensitive data | View is denied | `.01`, `.02` |
| `SCN-IAM-005-03` | User has view Permission and final Authorization = ALLOW | User views sensitive data in the resolved context | View is allowed for that action | `.02` |
| `SCN-IAM-005-04` | User can view sensitive data but has no export Permission | User attempts export | Export is denied; view permission is not reused as export permission | `.03` |

**TBD / downstream decisions**

- Controlled field/resource classification of sensitive data for P1 domains.
- Exact Resource/Action IDs for view/modify/export and administration of that catalog.
- Whether masking/redaction is added later as a separate approved requirement; it is not required by the current source.

### 6.6 Wave A unresolved decisions before implementation-ready status

The following items are intentionally open because they are not defined by the approved source baseline. They must be resolved in controlled downstream artifacts or by an approved requirements revision; they are not permission to invent behavior during coding.

| ID | Open decision | Affected FR | Required destination |
|---|---|---|---|
| `WA-TBD-001` | Complete `ORG-CONTEXT-HIERARCHY-POLICY` matrix, exception model, owner/version/effective-time representation | `FR-ORG-002` | Controlled policy + FRS/DES reference |
| `WA-TBD-002` | Lifecycle/state enumerations and allowed transitions for Company/Branch/Plant/Warehouse | `FR-ORG-002` | FRS or controlled policy + DES |
| `WA-TBD-003` | Effective-dating overlap/retroactivity rules for Company timezone/currency/calendar | `FR-ORG-004` | FRS/business policy |
| `WA-TBD-004` | Department hierarchy cycle/depth rule and Department/Position lifecycle vocabulary | `FR-EMP-002` | FRS/business policy |
| `WA-TBD-005` | Concurrent/overlapping Employee Assignment and primary-assignment resolution | `FR-EMP-001`, `FR-IAM-001` | FRS/business policy |
| `WA-TBD-006` | User Identity/Group Membership complete lifecycle and authentication mechanism | `FR-IAM-001` | Security design; requirements revision only if it changes business obligations |
| `WA-TBD-007` | Canonical Resource/Action catalog, Permission identifiers and admin permission codes | `FR-IAM-002`, `FR-IAM-003`, `FR-IAM-005` | FRS controlled catalog + DES |
| `WA-TBD-008` | Policy Rule representation/DSL and same-Effect conflict semantics | `FR-IAM-003` | FRS/DES controlled policy model |
| `WA-TBD-009` | P1 sensitive-data field/resource catalog | `FR-IAM-005` | Controlled security/data-classification catalog |

### 6.7 Wave A implementation-readiness gate

Wave A may move from **detailed draft** to **implementation-ready candidate** only when:

1. All nine parent FRs in Organization/Employee/IAM retain traceability to their upstream BR and all existing success criteria remain covered.
2. `WA-TBD-001`, `002`, `005`, `007`, `008` and `009` are resolved sufficiently to remove ambiguity from hierarchy validation, Assignment resolution and authorization evaluation.
3. The three architecture invariants have corresponding controlled Design realization drafts without changing canonical ownership or transaction ownership.
4. No P2/P3 capability is introduced as a P1 prerequisite; specifically SoD/delegation review, IAM security-event monitoring and HR work-calendar behavior remain outside P1 unless separately promoted by controlled revision.
5. UAT scenarios can be written from the canonical rules without relying on unstated implementation assumptions.

## 7. Wave B Detailed Functional Specification

### 7.1 Detailed-specification contract

Wave B follows the specification order approved in Phase Planning: Shared Controls & MDM. It refines the existing P1 parent requirements `FR-CTL-001`, `FR-CTL-002`, `FR-CTL-004`, `FR-AUD-001`, `FR-SYS-001`, `FR-MDM-001`, `FR-MDM-002` and `FR-MDM-003` without creating a new business capability.

Wave B inherits the detailed-specification conventions from Wave A. In addition:

- A reconciliation/event obligation exists only when its source capability is inside the approved scope. A later-phase source must not be simulated merely to satisfy `FR-CTL-002`.
- Central Audit remains independently realizable from `FR-CTL-002`; event/reconciliation context may enrich audit evidence but is not a hard prerequisite of `FR-AUD-001`.
- Shared Workflow is required only where the governing business rule requires business approval. A module's ordinary state transition does not automatically become a workflow.
- MDM governs common standards, ownership classification and identification rules; it does not become a competing owner of Product, Customer, Supplier or Employee.
- Current P1 `provinces` and `wards` datasets are treated as internally approved controlled data. External normative-source controls apply only if a future rule/dataset is based on a legal, regulatory or official-standard source.
- Database schema, UI layout, API path, physical audit-storage mechanism, workflow-engine technology and code-format syntax remain downstream design decisions unless the source explicitly constrains them.

### 7.2 Wave B governing invariants and boundaries

| Invariant / boundary | Wave B consequence |
|---|---|
| `AR-DATA-OWNERSHIP-001` | Every common/reference dataset must be classified as System, Group or Company Master before publication; an exception requires explicit owning-domain rationale. |
| `AR-DOM-001` | MDM or Shared Controls must not create a competing master/SSOT for an entity owned by another domain. |
| `AR-TXN-001` | Transaction Data always resolves exactly one owning Company; shared controls and overrides cannot move transaction ownership to Group. |
| `FR-IAM-003` | User-facing control, workflow, audit-query and master-data operations remain subject to the common Resource/Action authorization decision. |
| P1 scope boundary | `BR-CTL-003`, `BR-IAM-006`, `BR-DOC-001`, finance posting/close and other later-phase capabilities are not promoted into P1 by this specification. |
| Controlled-source rule | A normative legal/regulatory/official statement cannot be approved unless its `source_refs` resolve to a controlled source with `status=VERIFIED`. |

### 7.3 Cross-domain Controls & Workflow

#### 7.3.1 FR-CTL-001 — Detailed specification: lifecycle, numbering và quan hệ chứng từ

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized User for user-facing document operations; a system process may apply lifecycle/numbering rules when triggered by an authorized business operation. Concrete Resource/Action IDs are not defined upstream. |
| Trigger | Derived operations: create or progress a business document, assign an official document number, map a module state to the common lifecycle, or establish a source/derived/replacement/adjustment/reversal relation. |
| Hard prerequisites | `FR-ORG-002`, `FR-ORG-004`. |
| Primary business concepts | Common document lifecycle, module-state mapping, document numbering policy, document relation. |
| Common states explicitly defined upstream | Nháp, Chờ xem xét, Đã duyệt, Đã ghi nhận chính thức, Hoàn tất, Hủy, Đảo/Điều chỉnh. |

**Inputs and context**

The source requires enough context to determine document type, Company, Branch when applicable, business period/calendar context, applicable approved numbering policy, module-specific state, and document relations. Exact numbering syntax, sequence-reset behavior, numbering allocation moment and complete module-state mapping are not defined by the source.

**Processing and validation rules**

1. Every in-scope business document that participates in the common lifecycle shall be representable by one of the common lifecycle meanings defined above.
2. A module may maintain additional domain-specific states, but each mapping used for common control/reporting shall preserve the meaning of the common state and must not redefine it.
3. Number assignment shall resolve the approved numbering policy for the applicable document type and organizational/time context described by the source: type, Company, Branch, period and approved numbering policy as applicable.
4. Once a number has been officially issued, that number shall not be reused as another official document number within the applicable numbering context.
5. The system shall retain enough relation information to distinguish at least source, generated/derived, replacement, adjustment and reversal relationships between documents when those relationships apply.
6. A relation used to explain the current business value shall remain traceable to the document that created, replaced, adjusted or reversed the prior value.
7. This FR does not invent a mandatory workflow for each state transition; business approval is governed separately by `FR-CTL-004`.
8. This FR does not define financial-period close behavior; financial close is outside P1 unless separately approved.

**Lifecycle and numbering boundary**

The upstream requirement fixes the common state meanings but does not define a universal state-transition graph. It also requires official numbers not to be reused but does not define the exact event at which a number becomes "official." Those decisions must be controlled before implementation-ready status.

**Outputs and retained evidence**

For an in-scope document, the system shall be able to resolve its common lifecycle meaning, module-specific state where applicable, official number/policy context where assigned, and relevant source/replacement/adjustment/reversal relations. Historical relations must remain sufficient for a reviewer to identify which document produced or changed the current value.

**Authorization and audit obligations**

Document operations remain subject to `FR-IAM-003`; lifecycle/numbering rules do not grant Permission. Business/data changes that are in the Central Audit coverage set are handled by `FR-AUD-001`. Exact Permission IDs and audit coverage are downstream controlled decisions.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-CTL-001-01` | Two modules use different domain-specific states | Their states are mapped for common control/reporting | The mapping resolves consistent common lifecycle meanings without redefining those meanings | `FR-CTL-001.01` |
| `SCN-CTL-001-02` | An official number has already been issued in its applicable numbering context | Another document attempts to reuse that official number | Reuse is rejected | `.02` |
| `SCN-CTL-001-03` | A document is replaced, adjusted or reversed | A reviewer inspects the current value | The relation chain identifies the document that created or changed the current value | `.03` |

**TBD / downstream decisions**

- Common-state transition matrix and module-to-common-state mappings per P1 domain.
- Numbering policy model, uniqueness scope, reset rules and the exact event that makes a number official.
- Cardinality/cycle constraints and identifiers for document relations.

#### 7.3.2 FR-CTL-002 — Detailed specification: responsibility, event và reconciliation xuyên chứng từ

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Business responsibility is attributed to the relevant creator/approver/poster/adjuster or system context. Query/reconciliation actions are performed by an authorized User; exact role names are not specified upstream. |
| Trigger | An in-scope important transaction/event is created or completed, or an in-scope reconciliation is performed between related documents/data. |
| Hard prerequisites | `FR-CTL-001`, `FR-IAM-003`. |
| Conditional scope rule | Event/reconciliation obligations apply only when the corresponding source capability is in the approved scope. |
| P1 examples explicitly named upstream | Master record publication, order confirmation, receiving and goods issue. These examples do not create an exhaustive event taxonomy. |

**Inputs and context**

Responsibility evidence may include creator, approver, official poster/recorder, adjuster, timestamps, reason and related evidence/reference where applicable. Reconciliation requires the related source records plus the control totals relevant to the in-scope class. The source does not prescribe one universal reconciliation formula or one universal event taxonomy.

**Processing and validation rules**

1. An important transaction sample within an in-scope capability shall retain enough responsibility information to identify the relevant actor(s), time and reason/evidence required by the business context.
2. For every reconciliation class activated in the P1 acceptance scope, the system shall calculate or retain the applicable control total(s), identify a discrepancy when totals do not reconcile, and associate the discrepancy with an owner and handling status.
3. Quantity, amount, receivable/payable or inventory control totals are only required where the corresponding source capabilities and data actually exist in the approved scope.
4. Every event class included in acceptance scope shall identify at least event time, business object, owner/responsible context and business result.
5. Source capability not in approved scope shall not be instantiated with fake transactions/events merely to satisfy this FR.
6. P1 shall not create event classes for production completion, invoice, payment, return or payroll lock solely because those examples are named for future applicability.
7. Related-document evidence may reference available evidence without making the P2 Document capability a P1 hard prerequisite.
8. Authorization to perform the underlying action comes from `FR-IAM-003`; event recording itself does not grant the action.

**Reconciliation boundary**

The source fixes conditional applicability, ownership and status expectations for discrepancies, but it does not define the complete P1 reconciliation catalog, formulas, tolerances or discrepancy status vocabulary. Those must be controlled per class.

**Outputs and retained evidence**

For each acceptance-scope event, the system shall make event time, object, owner/responsible context and result traceable. For each activated reconciliation class, control totals and any discrepancy owner/status shall be retrievable. Source records not in scope remain absent rather than simulated.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-CTL-002-01` | A P1 transaction class is selected for responsibility sampling | A sample transaction is inspected | Required creator/approver/poster/adjuster/time/reason evidence is traceable as applicable to that transaction | `FR-CTL-002.01` |
| `SCN-CTL-002-02` | An approved P1 reconciliation class has related source records | Reconciliation is run | Control total(s) are produced; a difference has owner and handling status | `.02` |
| `SCN-CTL-002-03` | An event class such as an in-scope order confirmation or receiving event is in acceptance scope | The event occurs | Time, object, owner/responsible context and business result are recorded/resolvable | `.03` |
| `SCN-CTL-002-04` | A future source capability such as Payroll or Invoice is not in P1 scope | P1 acceptance is executed | No fake event/data is required for that source | `.04` |

**TBD / downstream decisions**

- Controlled P1 event-class catalog and event ownership/responsibility mapping.
- Controlled P1 reconciliation-class catalog, formulas/control totals, tolerance rules and discrepancy states.
- Exact evidence-reference mechanism where a related file/document exists but `BR-DOC-001` is not in P1.

#### 7.3.3 FR-CTL-004 — Detailed specification: business approval và Shared Workflow

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors explicitly named upstream | Developer, System Admin, Business Owner, workflow participant/approver. |
| Trigger | A business object is classified by its governing rule/policy as requiring business approval. |
| Hard prerequisites | `FR-CTL-001`, `FR-IAM-003`. |
| Approval dimensions named upstream | Document type, value, risk, Company, organizational unit and role. |
| Required capability semantics | One or more levels; reject; return for correction; cancel; escalate; delegate; decision history. |

**Inputs and context**

A workflow instance requires an applicable Workflow Type, business object/context, approval Rule, participant/approver resolution and current workflow state. The source requires governance/audit and effective decision history but does not prescribe a workflow-state enumeration, rule language, participant algorithm or escalation timer.

**Processing and validation rules**

1. Shared Workflow shall be invoked only when the applicable business rule/policy requires business approval; ordinary module state transitions do not automatically require Workflow.
2. Applicable approval Rules may consider the source-backed dimensions listed above and may define one or multiple approval levels.
3. The workflow shall support the source-backed decision/handling semantics: approve as applicable, reject, return for correction, cancel, escalate and delegate, while preserving decision history.
4. Each business object under approval shall resolve the workflow instance, applicable rule/level, participant/approver, current status and decision history.
5. Every decision shall retain the decision maker, result, time, reason when applicable, and whether the decision remains effective/current for the business object.
6. A workflow decision shall not create a Resource/Action Permission. Before an approver performs an approval action, the action must be allowed by `FR-IAM-003` in the resolved context.
7. Developer owns creation of Workflow Type; System Admin deploys/administers Workflow; Business Owner configures Rules and Participants only inside the allowed scope.
8. Business Owner shall not modify developer-level Workflow Type definition through ordinary business configuration.
9. System Admin shall not silently change business rules outside delegated administrative scope; such changes require history/evidence.
10. Workflow configuration/decision history must remain traceable enough to explain which rule and participant configuration governed a decision.

**Administration boundary**

The upstream role split is normative, but exact technical privileges, workflow deployment lifecycle and rule-edit approval process are not specified. Those details must preserve the stated separation without inventing new business roles.

**Outputs and retained evidence**

For an approved-scope object requiring approval, the system shall expose the Workflow Type/instance, applicable rule and level, participant/approver, current state, decision/result, time, reason where applicable, delegation/escalation context where used, and history. This evidence does not substitute for IAM authorization evidence.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-CTL-004-01` | A business object matches an approval rule | Approval processing is inspected | Rule/level, decision maker, result, time, reason and effective decision status are traceable | `FR-CTL-004.01` |
| `SCN-CTL-004-02` | A workflow instance exists | Its lifecycle is inspected | Workflow, rule, participant/approver, current status and decision history are traceable | `.02` |
| `SCN-CTL-004-03` | A participant lacks the approval Resource/Action Permission | A workflow rule would otherwise select that participant | Workflow does not create/bypass the missing Permission | `.03` |
| `SCN-CTL-004-04` | A Workflow Type is deployed | Governance data is inspected | Source/creator, deployment/admin scope and responsible Business Owner for rules/participants can be identified | `.04` |
| `SCN-CTL-004-05` | Business Owner or System Admin attempts a change outside the upstream role split | Configuration change is attempted | The unsupported change is prevented or controlled so it cannot occur silently without history | `.05` |

**TBD / downstream decisions**

- Workflow-state model and allowed state transitions.
- Rule representation/precedence, participant-resolution algorithm and multi-level progression semantics.
- Delegation, escalation, cancellation and return-for-correction detailed rules/timers.
- Exact administrative Resource/Action Permissions and deployment/change-control mechanism.

### 7.4 Audit & Traceability

#### 7.4.1 FR-AUD-001 — Detailed specification: Central Audit Trail

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Recording actor | The system records audit evidence for configured in-scope business/data changes; the responsible actor/User or system context is retained. |
| Query actor | Authorized User; concrete auditor/administrator role is not named by the source. |
| Trigger | A business/data change classified as important and within the controlled Central Audit coverage set occurs. |
| Hard prerequisite | `FR-IAM-001`. |
| Non-hard reference | `FR-CTL-002` may provide event/reconciliation context but is not required for Central Audit to exist. |

**Inputs and audit content**

For an audited change, the source requires Before Value, After Value, Reason, Approval, Attachment when applicable, actor/User and timestamp, together with the affected object/change context. The exact record schema, serialization format and attachment-reference mechanism are not prescribed.

**Processing and validation rules**

1. Central Audit shall record business/data-change evidence for change classes included in the P1 audit coverage set.
2. An audited change shall retain enough information to reconstruct the before and after values of the audited subject at the required audit granularity.
3. The audit record shall identify actor/User or system context, time, affected object, change, reason, and approval/attachment when those elements apply.
4. Central Audit owns business/data-change audit semantics; IAM security/authentication/access/authorization-administration events remain conceptually separate.
5. An audit consumer shall be able to distinguish a Central Audit business/data-change record from an IAM security event class.
6. `FR-CTL-002` context may be linked where available, but absence of that context shall not prevent Central Audit recording for a configured audited change.
7. This FR does not create the P2 IAM security-event monitoring capability (`BR-IAM-006`).
8. Integrity/immutability, retention and evidence-queryability constraints are further governed by the companion P1 `NFR-AUD-001`; this functional FR does not replace that NFR.

**Coverage boundary**

The source requires audit for important business/data changes but does not enumerate the complete P1 audited object/change catalog or field-level granularity. The coverage catalog is therefore a controlled downstream decision needed before UAT completeness can be established.

**Outputs and retained evidence**

An authorized audit consumer shall be able to trace an audited business object/change to its before/after data and responsible context. The audit classification shall make IAM security events distinguishable from Central Audit data-change evidence.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-AUD-001-01` | A configured important business/data change occurs | Audit evidence is queried | Before and after values for the audited change are traceable | `FR-AUD-001.01` |
| `SCN-AUD-001-02` | An audit record exists | Its evidence is inspected | Actor/User, time, object, change, reason and approval/attachment when applicable are identifiable | `.02` |
| `SCN-AUD-001-03` | Central Audit records and IAM security events both exist | Records are classified/queried | Business/data-change evidence is distinguishable from IAM security-event evidence | `.03` |
| `SCN-AUD-001-04` | No `FR-CTL-002` event/reconciliation context exists for an audited change | The audited change occurs | Central Audit can still record the change because CTL-002 is not a hard prerequisite | Source boundary |

**TBD / downstream decisions**

- P1 Central Audit coverage catalog by object/change class and field granularity.
- Audit record representation, change-diff format and attachment/reference representation.
- Query permissions and operational audit-search/filter model.
- Companion `NFS-AUD-001` integrity/retention realization details.

### 7.5 System Foundation

#### 7.5.1 FR-SYS-001 — Detailed specification: locale và display configuration

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized User responsible for locale/display configuration; exact role name is not defined upstream. |
| Trigger | Create/change an allowed locale/display configuration or render data using an applicable display configuration. |
| Hard prerequisite | `FR-ORG-004`. |
| Display attributes named upstream | Default display timezone, date/time format, currency symbol/format, separators/decimal display, language and display rules. |

**Inputs and context**

Each configuration requires its scope of application, responsible manager/owner and change history. The source does not define a scope-precedence hierarchy such as platform/Group/Company/User, nor does it enumerate supported locale codes or language packs.

**Processing and validation rules**

1. Locale/display configuration may control presentation of the source-backed display attributes without changing canonical business data.
2. Company business timezone and business/base/functional currency remain owned by `FR-ORG-004` and shall not be overwritten by display settings.
3. A display-timezone change may change how a timestamp is shown but shall not change the canonical business timestamp or business-period meaning already recorded.
4. Currency symbol/format and decimal/separator presentation shall not change the underlying monetary value or business currency.
5. Each configuration shall identify its application scope, responsible manager/owner and change history.
6. If multiple display configurations can apply, resolution must be deterministic under a controlled scope/precedence rule; the exact precedence is not defined upstream and remains TBD.

**Outputs and retained evidence**

The system shall be able to identify which locale/display configuration was applicable to a presentation context and retain configuration-change history. Canonical Company business currency/timezone/timestamp/value remain separately resolvable from the displayed representation.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-SYS-001-01` | A locale/display configuration is stored | Its governance is inspected | Application scope, responsible manager/owner and change history are identifiable | `FR-SYS-001.01` |
| `SCN-SYS-001-02` | A transaction has canonical Company business timestamp/currency/value | Locale/display settings are changed | Display may change, but canonical business timestamp/period, currency and monetary value remain unchanged | `.02` |

**TBD / downstream decisions**

- Supported locale/language/display-option catalog.
- Configuration scope levels and deterministic precedence when more than one setting applies.
- Exact configuration-management Permission IDs.

### 7.6 Master Data & Data Governance

#### 7.6.1 FR-MDM-001 — Detailed specification: common reference data và chuẩn định danh

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized User responsible for governed reference/identification data; the source does not name a single MDM business role. |
| Trigger | Define/publish a common primitive/reference dataset, assign its ownership classification, maintain an identification standard/RD Code rule, or maintain controlled address data. |
| Hard prerequisite | `FR-ORG-002`. |
| Governing invariant | `AR-DATA-OWNERSHIP-001`. |
| Explicit business-entity boundary | Product, Customer, Supplier and Employee keep their canonical owning domains; MDM does not own replacement masters. |

**Inputs and context**

The capability may govern common primitives such as units of measure, shared groups/classifications, countries, geography, HS code, tax rate and other foundation lists. Each published common/reference dataset requires an ownership classification. Identification governance covers Customer, Supplier, location and RD Code where applicable. Current address control uses approved internal datasets `provinces` and `wards`.

**Processing and validation rules**

1. Before publication/use, every common primitive/reference dataset shall be classified as System Master, Group Master or Company Master according to `AR-DATA-OWNERSHIP-001`.
2. Domains shall consume one controlled definition for the same common primitive and shall not create a competing standard list for the same meaning.
3. An exception to the default ownership rule shall identify the owning domain and business rationale.
4. MDM shall not become a canonical owner of Product, Customer, Supplier or Employee merely because it governs standards used by those entities.
5. Customer, Supplier and location shall follow approved identification/code rules, and the resulting code shall remain traceable to related transactions where those transactions exist.
6. MDM identification rules shall not create a duplicate Customer or Supplier master.
7. RD Code is a specialization of identification standards for applicable material/semi-finished/finished Product and Supplier contexts; it shall link back to the same canonical entity rather than create a new business identity.
8. For an object in RD Code scope, the RD Code shall be unique under the approved rule, identify its type/group, and the governing code rule shall have an owner and effective date. An RD Code already used shall not be reused as a different approved code under that rule.
9. The system shall distinguish internal system identity, business code, RD Code when applicable, and optional external/partner code for the same canonical entity.
10. New addresses in the current P1 model shall reference effective records from controlled `provinces` and `wards` datasets. Historical business data shall preserve the address code/name used at the transaction/effective time even if the controlled dataset later changes.
11. The controlled address datasets shall expose owner, approval status and version/effective time.
12. Current P1 `provinces`/`wards` data is internally approved and does not require `source_refs` solely for existing P1 use.
13. If a future reference dataset/rule is based on an external legal/regulatory/official-standard source, the consuming normative rule shall not be approved until the corresponding controlled source reference resolves with `status=VERIFIED`.
14. Database schema/field detail of `provinces` and `wards` remains a database-design concern and is not defined by this FRS.

**Ownership decision guide derived from `AR-DATA-OWNERSHIP-001`**

| Reference/common-data meaning | Default ownership treatment |
|---|---|
| Standard meaning independent of a specific enterprise and required consistently platform-wide | System Master |
| Enterprise-defined taxonomy/classification/coding/policy shared across Companies in a Group | Group Master |
| Data meaningful/effective only inside one Company, where the owning-domain requirement explicitly requires Company specificity | Company Master |
| Exception | Must explicitly identify owning domain and business rationale before publication |

**Outputs and retained evidence**

The system shall make the controlled definition, ownership classification, code/identity relationships, rule owner/effective date, and controlled address-dataset governance resolvable. Where a future normative external source applies, the corresponding controlled `source_id` and verified status must be traceable before approval.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-MDM-001-01` | Two domains need the same common primitive | The primitive is published | Both use one controlled definition; no competing standard list is created | `FR-MDM-001.01` |
| `SCN-MDM-001-02` | A new common/reference dataset is proposed | Publication is attempted | It has System/Group/Company ownership classification first | `.02` |
| `SCN-MDM-001-03` | A reference dataset deviates from the default ownership rule | It is approved/published | Owning domain and business rationale are identifiable | `.03` |
| `SCN-MDM-001-04` | Product/Customer/Supplier/Employee data is governed by an MDM standard | Canonical ownership is inspected | The business entity remains owned by its source domain, not MDM | `.04`, `.06` |
| `SCN-MDM-001-09` | A Customer, Supplier or governed location is identified under the approved coding standard | Its code and related business references are inspected | The approved code identifies the same canonical entity/location and related transactions can be traced when they exist | `.05`, `.06` |
| `SCN-MDM-001-05` | An object is in RD Code scope | Its identification is inspected | RD Code is unique under its rule, type/group and rule owner/effective date are known, and it links to the canonical Product/Supplier | `.07`, `.08`, `.09` |
| `SCN-MDM-001-06` | A new address is created | Address references are validated | It uses effective `provinces`/`wards`; historical address code/name remains preserved after later dataset change | `.10` |
| `SCN-MDM-001-07` | Current P1 internal address datasets are inspected | Source-reference validation runs | No external `source_refs` is required solely because they are current internally approved P1 datasets | `.11` |
| `SCN-MDM-001-08` | A future normative reference rule is based on an official external source | Approval is attempted | Approval is blocked until controlled source reference resolves as `VERIFIED` | `.11` |

**TBD / downstream decisions**

- Complete P1 reference/common-data catalog and ownership-classification register.
- Identification code formats, RD Code uniqueness scope and code-generation/allocation mechanics.
- Governance workflow for publishing/updating internally approved reference datasets.
- Physical `provinces`/`wards` schema and indexing, which belong in database design rather than this FRS.

#### 7.6.2 FR-MDM-002 — Detailed specification: ownership, sharing và field-level override

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actor | Authorized User consuming or maintaining governed data within the resolved ownership/authorization scope. |
| Trigger | Read/change governed data, classify data ownership, or create/change a Company-level override for a Group Master field declared overrideable. |
| Hard prerequisites | `FR-ORG-001`, `FR-ORG-002`, `FR-MDM-001`. |
| Governing invariants | `AR-DATA-OWNERSHIP-001`, `AR-TXN-001`. |
| Non-hard functional references | `FR-PRC-001`, `FR-PRD-005`. |

**Canonical P1 ownership classifications explicitly fixed upstream**

| Data / entity | Ownership |
|---|---|
| Product Master | Group Master |
| Product Default Selling Price | Group Master |
| Customer | Group Master |
| Supplier | Group Master |
| Employee | Group Master |
| Position | Group Master |
| Group Pricing Policy / Price List | Group Master |
| Employee Assignment | Company Master |
| Department | Company Master |
| Company Pricing Policy / Price List | Company Master |
| Business Transaction | Transaction Data owned by exactly one Company |

Product Moving Average Cost is also fixed as Group Master in the canonical ownership matrix and remains a system-derived Product attribute under the Product/Inventory rules; it is not a Company override.

**Processing and validation rules**

1. System, Group and Company Master data shall be shared/visible only according to its ownership scope and the User's effective authorization.
2. Local access needs shall not be solved by creating a competing master copy where the canonical ownership model already provides shared data.
3. Every Transaction Data record shall resolve exactly one owning Company and shall not be owned directly by Group.
4. A User shall not modify governed data outside both the data's ownership scope and the User's authorization.
5. Every governed data type shall have a known ownership level and corresponding access rule before use.
6. Field Level Override follows default deny: a Company may create an override only for a Group Master field explicitly declared overrideable by the owning-domain requirement/policy.
7. If a field is not declared overrideable, ordinary configuration shall not create a Company override and the Group Master value remains authoritative for that field.
8. A valid override shall retain the Company, source Group value, override value and effective time so that the effective value can be explained.
9. Applying an override shall not mutate the Group Master value used by other Companies.
10. A field override shall not create a second canonical business entity/master at Company level and shall not change the entity's owning domain.
11. Company-specific pricing differences shall be represented by Company Pricing Policy under `FR-PRC-001`, not by direct field override of Group Pricing Policy or Product Default Selling Price.
12. Product Default Selling Price remains governed by `FR-PRD-005`; it is not a generic Company override field.

**Effective-value boundary**

The source establishes default-deny and fallback to the Group Master value for non-overrideable fields. It does not fully define overlap/precedence when multiple time-effective override records for the same Company/field could exist. Such ambiguity must be prevented by a controlled downstream rule rather than guessed by implementation.

**Outputs and retained evidence**

For governed data, the system shall be able to resolve ownership level, canonical owner/entity, applicable Company context, authorization context and, where override applies, source Group value, Company override value and effective time. The system shall also explain that pricing differences are sourced from Pricing Policy rather than a generic field override.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-MDM-002-01` | Shared Group Master data is used by multiple Companies | A Company consumes the data | No competing local master is required solely for access | `FR-MDM-002.01` |
| `SCN-MDM-002-02` | A Transaction Data record is created | Ownership is resolved | Exactly one Company owns it; Group is not direct owner | `.02`, `.07` |
| `SCN-MDM-002-03` | A User is outside ownership/authorization scope | Modification is attempted | The modification is denied | `.03` |
| `SCN-MDM-002-04` | P1 canonical data types are inspected | Ownership is resolved | Group/Company classifications match the upstream ownership matrix | `.04`-`.06` |
| `SCN-MDM-002-05` | A field is explicitly overrideable | A Company override is effective | Company, Group source value, override value and effective time are traceable | `.08` |
| `SCN-MDM-002-06` | A field is not declared overrideable | Company override is attempted | Override is rejected and Group Master remains authoritative | `.09` |
| `SCN-MDM-002-07` | Company A overrides an allowed field | Company B reads the same Group Master | Company B's Group Master value is unchanged | `.10`, `.11` |
| `SCN-MDM-002-08` | Company-specific selling price is needed | Pricing is configured | Difference is represented by Company Pricing Policy, not by overwriting Group Policy/default price | `.12` |

**TBD / downstream decisions**

- Controlled overrideable-field catalog by owning domain.
- Effective-dating overlap/precedence and end-dating rules for Company overrides.
- Resource/Action mapping for ownership-classification and override administration.

#### 7.6.3 FR-MDM-003 — Detailed specification: lifecycle và sensitive master-data change

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized proposer/change actor, owner and approver as required by the applicable controlled-change policy; exact role names are not enumerated upstream. |
| Trigger | Create/change lifecycle/effective data for a governed master record, or request a manual sensitive/controlled change. |
| Hard prerequisites | `FR-MDM-001`, `FR-IAM-003`, `FR-CTL-004`. |
| Sensitive examples explicitly named upstream | Tax code, bank account, payment terms and permitted manual overrides. |
| Non-hard reference | `FR-INV-001` for system-derived Group-level Product Moving Average Cost. |

**Inputs and context**

A governed master record requires lifecycle/status and effective-date context sufficient to determine whether it may be used for a new transaction. A sensitive manual change requires applicable policy classification plus owner, proposer, approver, effective date, decision and evidence. The source does not define one universal master-state vocabulary or the complete sensitive-field catalog.

**Processing and validation rules**

1. Governed master records shall retain state/lifecycle, effective date and reason for controlled changes.
2. A record that is no longer effective shall not be selectable/usable for new transactions, while its historical references remain preserved/queryable.
3. A manual change classified by policy as sensitive/controlled shall identify the business owner, proposer, approver, effective date, decision and evidence required by that policy.
4. If the applicable rule requires approval, the change shall use `FR-CTL-004` Shared Workflow and shall not take official effect before the workflow reaches a state that permits effectiveness.
5. Workflow history shall preserve the decision trail for the sensitive change.
6. Group-level Product Moving Average Cost calculated/updated by `FR-INV-001` is a system-derived value and shall not be treated as a manual sensitive-master update merely because its value changes after an eligible inbound transaction.
7. The system-derived Moving Average Cost change shall not automatically start the sensitive-change approval workflow defined here.
8. A future manual cost override is outside current P1 acceptance unless a separate approved capability/policy authorizes it; if later approved, it requires its own controlled sensitive-change rule.
9. Authorization to propose/approve/apply the change remains subject to `FR-IAM-003`; Workflow does not grant the underlying Permission.

**Lifecycle/approval boundary**

The source requires lifecycle, effective date and change reason but does not define universal master states, allowed transitions, retroactive-change rules or the complete sensitive-change trigger catalog. These must be controlled per master domain/policy.

**Outputs and retained evidence**

For a governed master record, current/effective status and historical state/change evidence shall be resolvable. For a sensitive manual change, the system shall make owner, proposer, approver, effective date, decision, evidence and workflow history traceable. System-derived Moving Average Cost updates remain distinguishable from manual controlled changes.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-MDM-003-01` | A master record is no longer effective | A new transaction attempts to use it | Use is rejected while historical references remain available | `FR-MDM-003.01` |
| `SCN-MDM-003-02` | A manual change is classified as sensitive/controlled | Change evidence is inspected | Owner, proposer, approver, effective date, decision and evidence are traceable | `.02` |
| `SCN-MDM-003-03` | A sensitive change requires approval | Workflow has not reached an effective/approved state | The change does not take official effect | `.03` |
| `SCN-MDM-003-04` | An eligible inbound transaction changes Product Moving Average Cost through `FR-INV-001` | The system-derived cost is updated | The update is not treated as a manual sensitive-master change and does not auto-start this approval workflow | `.04` |

**TBD / downstream decisions**

- Lifecycle/state vocabulary, allowed transitions and effective-dating rules for each governed P1 master.
- Controlled sensitive-field/change catalog and policy determining which manual changes require approval.
- Evidence requirements and exact Workflow Type/Rule mapping for each sensitive-change class.
- Any future manual cost-override policy; it is not part of current P1 unless approved separately.

### 7.7 Wave B unresolved decisions before implementation-ready status

The following items remain intentionally open because the approved source does not define them. They must be resolved by controlled policy, FRS refinement or design as indicated; coding must not silently choose business behavior.

| ID | Open decision | Affected FR | Required destination |
|---|---|---|---|
| `WB-TBD-001` | Common document transition model and module-to-common-state mappings | `FR-CTL-001` | FRS/control policy |
| `WB-TBD-002` | Numbering policy model, uniqueness context, reset rules and official-number allocation point | `FR-CTL-001` | FRS/control policy + DES |
| `WB-TBD-003` | Document-relation cardinality/cycle rules and relation identifiers | `FR-CTL-001` | FRS/DES |
| `WB-TBD-004` | P1 event/reconciliation catalogs, formulas/control totals, tolerances, owners and discrepancy-state model | `FR-CTL-002` | FRS controlled catalog |
| `WB-TBD-005` | Workflow state model, rule representation/precedence, participant resolution, multi-level progression, delegation/escalation/cancellation semantics | `FR-CTL-004` | FRS/workflow policy + DES |
| `WB-TBD-006` | Exact Workflow administration Resource/Action permissions and deployment/change-control mechanism | `FR-CTL-004` | Authorization catalog + DES |
| `WB-TBD-007` | P1 Central Audit coverage catalog, field granularity, change-diff/attachment representation and query permissions | `FR-AUD-001` | FRS audit catalog + NFS/DES |
| `WB-TBD-008` | Locale/display supported-option catalog, scope levels and precedence | `FR-SYS-001` | FRS/configuration policy |
| `WB-TBD-009` | Complete P1 common/reference-data catalog and ownership-classification register | `FR-MDM-001` | Controlled MDM catalog |
| `WB-TBD-010` | Identification/RD Code formats, uniqueness scope and allocation mechanics | `FR-MDM-001` | Identification policy + DES |
| `WB-TBD-011` | Overrideable-field catalog and effective-dating overlap/precedence rules | `FR-MDM-002` | Owning-domain policies + FRS |
| `WB-TBD-012` | Master lifecycle vocabularies and sensitive-change catalog/approval policy | `FR-MDM-003` | Owning-domain policies + FRS/Workflow config |
| `WB-TBD-013` | Governance process for publication/version/effective-time of internally approved `provinces`/`wards` datasets | `FR-MDM-001` | Controlled MDM policy |

### 7.8 Wave B implementation-readiness gate

Wave B may move from **detailed draft** to **implementation-ready candidate** only when:

1. All eight Wave B parent FRs retain traceability to their upstream BR and every upstream success criterion remains covered by at least one acceptance scenario/child FR.
2. `WB-TBD-001`, `002`, `004`, `005`, `007`, `009`, `011` and `012` are resolved sufficiently to remove ambiguity from document control, reconciliation/event scope, Workflow, audit coverage, ownership classification, override resolution and sensitive-master approval.
3. Workflow administration preserves the Developer/System Admin/Business Owner separation stated by `BR-CTL-004`, and workflow never bypasses `FR-IAM-003`.
4. Central Audit coverage is defined without turning `FR-CTL-002` into a hard prerequisite and without importing P2 IAM security-event monitoring into P1.
5. MDM catalogs preserve canonical owning domains and the upstream ownership matrix; no local copy or field override creates a competing master.
6. Current internal `provinces`/`wards` governance is controlled, while any future external normative source follows the verified `source_refs` rule before approval.
7. No later-phase source capability is simulated merely to satisfy event, reconciliation, document, financial or compliance behavior in P1.
8. UAT can be written from controlled rules rather than unstated implementation assumptions.

## 8. Wave C Detailed Functional Specification

### 8.1 Detailed-specification contract

Wave C follows the approved specification order for **Core Masters**. It refines the existing P1 parent requirements `FR-PRD-001`, `FR-PRD-002`, `FR-PRD-005`, `FR-CUS-001`, `FR-SUP-001` and `FR-PRC-001` without creating new Product, Customer, Supplier or Pricing capability.

Wave C inherits the document contract and the detailed-specification conventions established in Waves A and B. In addition:

- Product, Customer and Supplier remain canonical business entities owned by their respective domains. MDM provides standards/governance but does not become the master owner.
- Product remains a Group Master. Company/channel/market usage scope limits where the same Product may be used; it does not create a Company-owned duplicate Product.
- `item_type=COMBO` and `item_type=GIFT` are classification-only in P1. Composition, component consumption, stock treatment, revenue allocation and gift semantics are outside P1 until `BR-PRD-004` is in approved scope.
- Group-level Product Moving Average Cost is system-derived through `FR-INV-001`; it is not a Company override and does not establish the configurable valuation capability deferred to `BR-INV-005`.
- Product Default Selling Price is a Group-level fallback source. Company-specific price differences are represented by Company Pricing Policy rather than a Company override of the Product default price.
- Canonical price resolution uses only the precedence explicitly approved upstream: **applicable Company Pricing Policy > applicable Group Pricing Policy > applicable Product Default Selling Price**. The source does not define a tie-break between multiple simultaneously applicable policies at the same ownership level; implementation must not invent one silently.
- Pricing Policy may exist without Customer-specific conditions. Customer context is evaluated only when a customer-specific condition is present and the context exists.
- Shared Workflow is conditional for Pricing Policy: it is used only when an applicable approval rule/policy requires business approval.
- UI layout, API shape, physical schema, search/index technology, barcode/QR encoding format and code-generation mechanics remain downstream design decisions unless explicitly constrained upstream.

### 8.2 Wave C governing invariants and boundaries

| Invariant / boundary | Wave C consequence |
|---|---|
| `AR-DOM-001` | Product, Customer, Supplier and Pricing each retain one canonical owning domain; no downstream consumer may create a competing master for the same meaning. |
| `AR-DATA-OWNERSHIP-001` / `FR-MDM-002` | Product, Product Default Selling Price, Customer and Supplier are Group Master; Company Pricing Policy is Company Master; Group Pricing Policy is Group Master. |
| `FR-MDM-001` | Approved identification/reference standards are consumed by Product/Customer/Supplier without moving canonical ownership into MDM. |
| `FR-MDM-003` | Master lifecycle/effective-date rules apply; sensitive Supplier changes use controlled approval as required upstream. |
| `FR-IAM-003` | Create, change, publish, query and administrative actions remain subject to Resource/Action authorization in the resolved context. |
| `NFR-DAT-001` | Product quantity conversion preserves source quantity and applies approved precision/rounding semantics. |
| `FR-FIN-005` | Where pricing currency conversion is used, exchange-rate context and original/converted values must remain traceable. |
| P1 Product boundary | PLM lifecycle, lot/serial end-to-end control, configurable valuation policy and COMBO/GIFT operational semantics remain outside P1 unless separately approved. |
| P1 Pricing boundary | Promotion/discount/coupon engine is not part of `FR-PRC-001`; `BR-PRC-002` remains later phase. |

### 8.3 Product

#### 8.3.1 FR-PRD-001 — Detailed specification: Product Master và lifecycle vận hành

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Product-domain User/business owner for master maintenance and publication; authorized consuming Users/processes in Purchase, Inventory and Sales may reference an effective Product. Exact role names are not defined upstream. |
| Trigger | Create or change a Product Master, change its operational lifecycle or usage scope, register an approved alternate identifier, or consume the Product in an in-scope transaction. |
| Hard prerequisites | `FR-MDM-001`, `FR-MDM-002`, `FR-MDM-003`. |
| Canonical ownership | Product domain; Group Master. |
| Non-hard references | `FR-INV-001`, future `BR-PRD-004`, future `BR-INV-005`. |

**Inputs and context**

The Product Master shall retain enough controlled information to resolve owning Group, item type, business code/approved identifiers, name/description, grouping/classification, specification, base/operational UOM context, weight/dimensions where applicable, operational status, usage scope and other operational attributes required by approved P1 consumers. The source does not define a complete mandatory-field matrix by item type or transaction type.

**Processing and validation rules**

1. Every Product Master shall resolve exactly one owning Group and remain a Group Master shared by authorized Companies in that Group.
2. Product item classification shall support the operational categories named upstream, including production material, consumable, semi-finished, finished, make-and-sell product, trading goods, COMBO, GIFT and other approved operational item types.
3. Each Product shall identify the applicable purchase, inventory, production, sales or usage policy/context necessary for approved consumers; the exact policy catalog is not specified upstream.
4. A Company shall reference the canonical Group Product rather than create a competing Product master solely for local use.
5. Product Master is independent from Engineering Product/Development Product in PLM. Product lifecycle changes shall not be interpreted as PLM development-stage changes, and PLM changes shall not silently rewrite the ERP Product Master.
6. Before a Product is published/available for operational use, the required Product information for its approved context shall be present. The source requires a unique Product code but does not define the exact code format or uniqueness scope beyond the applicable master context.
7. Weight, dimensions and UOM attributes, when applicable, shall be stored/used consistently enough for in-scope Inventory, Sales and delivery-related consumers; detailed unit conventions remain controlled data/design decisions.
8. Product usage scope shall be resolvable before a new transaction uses the Product. Scope may include Company, sales channel, market and pricing classification when applicable.
9. Changing usage scope shall not create another canonical Product Master and shall not change Group ownership.
10. Pricing classification on Product is classification only. Assigning/changing the classification shall not directly modify Product Default Selling Price or create a Pricing Policy.
11. Product operational status shall support at least the upstream meanings: draft, active/in-business, temporarily suspended and discontinued. A discontinued/non-effective Product shall not be used for new transactions, while historical references remain queryable.
12. Approved barcode, QR, partner code and alternate code identifiers shall resolve to the same canonical Product and shall not create duplicate Product identity.
13. In P1, `COMBO` and `GIFT` classification shall not create mandatory component structure, component consumption, inventory treatment, revenue allocation or gift-treatment behavior. Those obligations begin only when the corresponding later capability is approved.
14. Group-level Product Moving Average Cost shall be treated as a system-derived Product operational cost attribute updated by `FR-INV-001` after eligible inbound transactions. The Product shall retain current cost, update/effective timestamp and source inbound transaction reference sufficient for traceability.
15. Company-specific field override shall not create a competing Product Moving Average Cost. P1 Moving Average Cost shall not be represented as a configurable inventory valuation policy.
16. Product create/change/publish/use actions remain subject to `FR-IAM-003`; any controlled sensitive change applicable through `FR-MDM-003` does not bypass authorization.

**Lifecycle and scope boundary**

The upstream source names the principal Product operational states but does not define the complete transition matrix, approval requirements for each transition, required-field matrix by item type, policy catalog by operational use, or uniqueness rules for every alternate identifier. These remain controlled Wave C decisions.

**Outputs and retained evidence**

The system shall make resolvable the canonical Product identity, owning Group, current operational status, item type, applicable usage scope, approved identifiers and relevant operational attributes. Historical status/scope changes must remain traceable according to upstream master-lifecycle rules. For Product Moving Average Cost, the current value, Group, effective/update time and latest inbound transaction that changed the value shall be traceable.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-PRD-001-01` | A Product is maintained in a Group | Product identity is inspected | Owning Group, item type and applicable operational policy/context are identifiable | `FR-PRD-001.01` |
| `SCN-PRD-001-02` | Two Companies in the same Group are authorized to use a Product | Each Company references it | Both use the same canonical Group Product; no local competing Product is required | `.02`, `.07` |
| `SCN-PRD-001-03` | An ERP Product and a PLM Engineering Product exist | Either lifecycle changes | No implicit cross-domain lifecycle equivalence or silent overwrite is created | `.03`, `.10` |
| `SCN-PRD-001-04` | A Product is missing a required publication attribute or violates the approved code-uniqueness rule | Publication/use is attempted | Publication/use is rejected | `.04` |
| `SCN-PRD-001-05` | Weight, dimensions or UOM are applicable | Inventory/Sales consumer reads them | The same approved Product attributes are consumed consistently | `.05` |
| `SCN-PRD-001-06` | A Product is outside an applicable Company/channel/market scope | A new transaction attempts to use it | Use is rejected according to the controlled usage-scope rule without creating a new Product master | `.06`, `.07` |
| `SCN-PRD-001-07` | A pricing classification is assigned/changed | Product pricing data is inspected | Default Selling Price does not change solely because of the classification | `.08` |
| `SCN-PRD-001-08` | A Product is discontinued/non-effective | A new transaction references it | New use is rejected while historical references remain available | `.09` |
| `SCN-PRD-001-09` | Approved alternate identifiers exist | Any approved identifier is resolved | All resolve to the same canonical Product | `.11` |
| `SCN-PRD-001-10` | A P1 Product has item type COMBO or GIFT | P1 acceptance is executed | Classification is supported without requiring composition/stock/revenue/gift semantics | `.12` |
| `SCN-PRD-001-11` | An eligible inbound transaction updates Moving Average Cost through `FR-INV-001` | Product cost evidence is inspected | Group, current value, update time and source inbound transaction are traceable and no Company cost override is created | `.13` |

**TBD / downstream decisions**

- Controlled Product item-type catalog and required operational attributes/policies by item type.
- Product lifecycle transition matrix and any approval requirements by transition.
- Product code uniqueness scope/format and barcode/QR/partner/alternate-code uniqueness/collision rules.
- Usage-scope model and precedence when Company/channel/market scopes intersect.
- Controlled unit conventions for weight/dimensions and their validation rules.

#### 8.3.2 FR-PRD-002 — Detailed specification: Product UOM và conversion

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Product-domain User for UOM/conversion maintenance; in-scope transaction processes consume the effective conversion. |
| Trigger | Assign/change Product base UOM, create/change/end-date a Product conversion, or convert a transaction quantity. |
| Hard prerequisite | `FR-PRD-001`. |
| Governing quality reference | `NFR-DAT-001` for quantity precision/rounding. |
| Core invariant | Exactly one base UOM is effective for a Product at a given time. |

**Inputs and context**

A conversion record shall identify Product, from-UOM, to-UOM, conversion direction, positive conversion factor, conversion context where applicable and effective period. A conversion operation shall retain source quantity and enough context to identify the factor used. The source does not define whether reciprocal factors are auto-derived, whether chained conversions are allowed, or the complete conversion-context taxonomy.

**Processing and validation rules**

1. A Product shall have exactly one effective base UOM at any point in time.
2. A conversion shall not become effective unless from-UOM, to-UOM, direction, factor `> 0` and effective time/period are defined.
3. Two conversion records shall not have overlapping effective periods when they compete for the same Product/from-UOM/to-UOM/conversion context.
4. A new transaction shall resolve only a conversion that is effective for the transaction/effective context.
5. An expired/end-dated conversion shall not be used for a new transaction, while historical transactions shall retain the conversion context originally applied.
6. A conversion shall preserve source quantity before rounding and shall make source quantity, conversion factor and converted quantity traceable.
7. Quantity precision and rounding shall follow `NFR-DAT-001`; this FR shall not silently discard source precision before the approved rounding step.
8. Changing Product base UOM or conversion configuration shall not rewrite historical transaction quantities/conversion evidence.
9. Authorization to maintain conversion data remains subject to `FR-IAM-003` through the common authorization model; this FR does not define a new permission system.

**Conversion boundary**

The source does not define reciprocal-factor generation, direct-vs-chained conversion precedence, conversion-context taxonomy, factor precision beyond the NFR relationship, or how base-UOM changes are migrated for open transactions. These require controlled decisions before implementation-ready status.

**Outputs and retained evidence**

For each converted transaction quantity, the system shall make the source quantity, source UOM, conversion factor/record, converted quantity, target UOM and effective conversion context traceable. Historical use of an expired conversion shall remain reproducible from retained evidence.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-PRD-002-01` | Product UOM records exist for an effective time | Base UOM is resolved | Exactly one base UOM is effective | `FR-PRD-002.01` |
| `SCN-PRD-002-02` | A conversion is proposed | Validation runs | from-UOM, to-UOM, direction, factor `> 0` and effective period are all required | `.02` |
| `SCN-PRD-002-03` | An effective conversion already exists for the same competing context | Another overlapping conversion is proposed | The overlapping competing record is rejected | `.03` |
| `SCN-PRD-002-04` | A quantity is converted | Conversion evidence is inspected | Source quantity, factor and converted quantity are retained with `NFR-DAT-001` precision/rounding behavior | `.04` |
| `SCN-PRD-002-05` | A conversion has expired | A new transaction attempts to use it and an old transaction is reviewed | New use is rejected; historical conversion context remains available | `.05` |

**TBD / downstream decisions**

- Conversion-context taxonomy and matching key.
- Reciprocal conversion behavior and whether reciprocal records are stored or derived.
- Direct-vs-chained conversion support/precedence.
- Base-UOM change controls for existing/open transactions and historical data.
- Internal factor precision and exact rounding point consistent with `NFR-DAT-001`.

#### 8.3.3 FR-PRD-005 — Detailed specification: Product Default Selling Price

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Product/Pricing-adjacent User maintaining the Product default selling-price record; Sales/Pricing resolution consumes it as fallback. Exact role ownership for maintenance is not further specified upstream beyond Product-domain ownership. |
| Trigger | Create/change/end-date Product Default Selling Price or resolve a base selling price when no applicable higher-precedence Pricing Policy exists. |
| Hard prerequisites | `FR-PRD-001`, `FR-ORG-004`. |
| Canonical ownership | Product domain, Group level. |
| Non-hard references | `FR-MDM-002`, `FR-PRC-001`. |

**Inputs and context**

A default selling-price record shall resolve Product, owning Group, price value, currency and effective time/period. The source requires history but does not define allowed overlap, whether multiple default currencies may coexist for one Product, or the exact publication/approval process.

**Processing and validation rules**

1. Product Default Selling Price shall be maintained at Group level under Product-domain ownership.
2. A Product is not required to belong to a Price List/Pricing Policy in order to have a usable default selling price.
3. Each default selling-price record shall identify currency and effective time/period, and prior values shall remain historically traceable.
4. Authorized Companies in the Group may consume the same Group default price when it is applicable to the transaction/pricing context.
5. Company-specific default-price field override shall not be created. A Company-specific price difference shall be represented by Company Pricing Policy under `FR-PRC-001`.
6. Canonical resolution shall use an applicable Company or Group Pricing Policy ahead of the Product Default Selling Price according to `FR-PRC-001`.
7. If no applicable Company/Group Pricing Policy exists, the applicable Product Default Selling Price shall be available as the final base-price fallback.
8. The final resolved price source shall remain traceable to the specific Product default record when this fallback is used.
9. `FR-MDM-002` governs ownership/override semantics as a reference but is not converted into a new hard prerequisite here.

**Effective-price boundary**

The source does not define the behavior when no applicable Pricing Policy **and** no applicable Product Default Selling Price exist. It also does not define same-Product/same-currency overlap resolution or multi-currency default-price cardinality. These are open decisions.

**Outputs and retained evidence**

The system shall make Group, Product, price, currency, effective time and history of the default price traceable. When used in canonical price resolution, the resolved source shall point to the specific default-price record.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-PRD-005-01` | A Product has no applicable Pricing Policy but has an applicable Group default price | Base price is resolved | The Product Default Selling Price is returned as fallback | `FR-PRD-005.01` |
| `SCN-PRD-005-02` | An applicable Company or Group Pricing Policy exists | Base price is resolved | The policy price wins according to `FR-PRC-001`, and the final source is traceable | `.02` |
| `SCN-PRD-005-03` | Default price history exists | A point-in-time/default record is inspected | Group, currency, effective time and historical value are traceable | `.03` |
| `SCN-PRD-005-04` | A Company requires a different selling price | Configuration is performed | A Company Pricing Policy is used; no Company-specific Product default-price field override is created | `.04` |

**TBD / downstream decisions**

- Default-price cardinality by Product/currency and rules for overlapping effective periods.
- Whether/how multiple default currencies coexist and how applicable transaction currency is matched before any FX conversion.
- Publication/change approval policy for default price where not otherwise covered by controlled master-change rules.
- Explicit handling when no applicable Company Policy, Group Policy or Product Default Selling Price can be resolved.

### 8.4 Customer & CRM

#### 8.4.1 FR-CUS-001 — Detailed specification: Customer Master, hồ sơ và lifecycle

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Customer-domain User/business owner for Customer maintenance; authorized Companies/processes in the Group may consume the canonical Customer. |
| Trigger | Create/change Customer, manage address/contact/status data, or reference Customer in an approved P1 business process. |
| Hard prerequisites | `FR-MDM-001`, `FR-MDM-002`. |
| Canonical ownership | Customer domain; Group Master. |
| Customer categories explicitly named upstream | Retail customer, agent, enterprise/business customer, distributor. |

**Inputs and context**

A Customer record shall retain owning Group, unique code in the applicable scope, business owner, customer category/group, relevant contact information, transactional addresses, tax code when applicable, status/lifecycle and multiple effective contacts/addresses. Address references must consume the controlled address datasets/rules from `FR-MDM-001` when applicable. The source does not define the complete Customer-state transition model, uniqueness scope beyond the applicable policy, or mandatory-field matrix by customer type.

**Processing and validation rules**

1. Customer shall be maintained as a canonical Group Master owned by the Customer domain.
2. A Customer shall identify owning Group, a unique code within the approved applicable scope and a business owner.
3. Authorized Companies in the same Group shall reuse the same Customer Master rather than create a competing local Customer solely for use in that Company.
4. Customer profile shall support the upstream customer categories and retain appropriate contact, address, tax, grouping and status information required by the applicable category/process.
5. Multiple contacts and transaction addresses may be associated with a Customer; current/effective records shall be distinguishable from historical/non-effective records.
6. Customer lifecycle/status history shall be retained from prospect/potential status through cessation of trading; the source does not define every intermediate state or transition.
7. A Customer profile query shall present a unified canonical record with current status plus retained history and effective addresses/contacts.
8. Customer create/change/use actions remain subject to `FR-IAM-003`; this FR does not create Company ownership merely because a Company consumes the Group Master.
9. This P1 FR does not activate P2 credit terms/limits, Customer interaction history, returns/warranty or P3 consent/privacy capability solely because those later capabilities reference Customer.

**Lifecycle boundary**

The source establishes a lifecycle from potential Customer to discontinued trading but does not define a complete state machine, approval model, merge/duplicate-resolution behavior, default-contact/address rules or mandatory fields by Customer category. These remain controlled decisions.

**Outputs and retained evidence**

The system shall make canonical Customer identity, owning Group, code, business owner, current status, status history, effective addresses and effective contacts traceable. Authorized Company use shall reference this same canonical Customer identity.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-CUS-001-01` | A Customer record exists | Identity/governance is inspected | Owning Group, unique code in applicable scope and business owner are identifiable | `FR-CUS-001.01` |
| `SCN-CUS-001-02` | Two Companies in the Group transact with the same Customer | Customer is selected | Both reference the same canonical Customer; no competing local master is required | `.02` |
| `SCN-CUS-001-03` | Customer status/contact/address changes over time | The profile is reviewed | Current status, status history and currently effective contacts/addresses are distinguishable and traceable | `.03` |

**TBD / downstream decisions**

- Customer lifecycle states/transitions and publication/inactivation approval policy.
- Customer code format and uniqueness scope.
- Mandatory fields by Customer category/type.
- Contact/address cardinality, effective-dating and default/primary-selection rules.
- Duplicate Customer detection/merge remains outside P1 unless separately approved through later MDM capability.

### 8.5 Supplier

#### 8.5.1 FR-SUP-001 — Detailed specification: Supplier Master và sensitive attributes

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Supplier-domain User/business owner; proposer and verifier/approver for sensitive changes; Purchase consumers reference the canonical Supplier. Exact role names are not defined upstream. |
| Trigger | Create/change Supplier, change collaboration status, reference Supplier in Purchase, or propose a sensitive change to bank account, tax code or payment terms. |
| Hard prerequisites | `FR-MDM-001`, `FR-MDM-002`, `FR-MDM-003`. |
| Canonical ownership | Supplier domain; Group Master. |
| Sensitive attributes explicitly required upstream | Bank account, tax code, payment terms. |

**Inputs and context**

A Supplier shall retain owning Group, immutable internal identity, business code/RD Code according to approved identification policy, collaboration status and the operational attributes required by P1 procurement. Sensitive changes require proposer, verifier/approver, decision, effective date and before/after history/evidence through the controlled master-change/workflow mechanism.

**Processing and validation rules**

1. Supplier shall be maintained as a canonical Group Master owned by the Supplier domain.
2. Supplier shall have an immutable internal identity and business code/RD Code according to the approved identification policy.
3. Authorized Companies in the same Group shall reuse the same canonical Supplier rather than create a competing Company Supplier master solely for procurement use.
4. Purchase shall consume Supplier in sourcing, Purchase Order and receiving; Purchase shall not become the canonical owner of Supplier.
5. Collaboration status shall determine whether a Supplier may be used for a new commitment. A Supplier that has stopped collaboration shall not be used to create a new procurement commitment, while historical references remain available.
6. A change to Supplier bank account, tax code or payment terms shall be treated as a sensitive master-data change within the scope explicitly stated upstream.
7. Each such sensitive change shall follow `FR-MDM-003`, including the required controlled approval and Shared Workflow before the changed value becomes effective.
8. Sensitive-change evidence shall identify proposer, verifier/approver, decision, effective date and historical before/after values/evidence sufficient to trace the change.
9. If the Workflow has not reached a state that permits effectiveness, the sensitive change shall not be used as the effective Supplier value for new business processing.
10. Supplier maintenance and approval operations remain subject to `FR-IAM-003`; Shared Workflow does not grant underlying permission.
11. P2 supplier qualification/approved-supplier-list and performance-evaluation capability are not activated by this P1 Supplier Master specification.

**Lifecycle/approval boundary**

The source requires collaboration status and sensitive-change approval but does not define the complete Supplier lifecycle, workflow levels, verification role separation, evidence checklist or whether other Supplier fields are sensitive. These remain controlled decisions; the three named sensitive attribute classes are mandatory P1 coverage.

**Outputs and retained evidence**

The system shall make Supplier canonical identity, Group, business/RD code, collaboration status and Company usage traceable. For bank-account, tax-code and payment-term changes, the proposer, verifier/approver, decision, effective date and before/after history shall be retrievable with the associated Workflow decision trail.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-SUP-001-01` | A Supplier exists | Supplier identity is inspected | Group, immutable internal identity, approved business/RD code and collaboration status are identifiable | `FR-SUP-001.01` |
| `SCN-SUP-001-02` | Multiple Companies in the Group use a Supplier | Procurement references the Supplier | The same canonical Group Supplier is used without a competing local master | `.02`, `.03` |
| `SCN-SUP-001-03` | A Supplier has stopped collaboration | A new procurement commitment is attempted | The new commitment is rejected while historical transactions remain available | `.04` |
| `SCN-SUP-001-04` | Bank account, tax code or payment terms are changed | Change evidence is inspected | Proposer, verifier/approver, decision, effective date and before/after history are traceable | `.05` |
| `SCN-SUP-001-05` | A named sensitive change requires approval and Workflow is not effective/approved | New business processing attempts to use the proposed value | The proposed value is not effective | `.06` |

**TBD / downstream decisions**

- Complete Supplier collaboration lifecycle and allowed transitions.
- Supplier code/RD Code allocation rules as specialized from the approved identification policy.
- Workflow Type/rules, approval levels and evidence checklist for the three named sensitive attribute classes.
- Whether additional Supplier fields are classified as sensitive in P1 through a separately controlled policy.

### 8.6 Pricing & Promotion

#### 8.6.1 FR-PRC-001 — Detailed specification: Pricing Policy và canonical price resolution

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Pricing-domain User/business owner for Group/Company Pricing Policy maintenance; authorized transaction process requests price resolution. Approval actors apply only when the relevant approval rule requires Workflow. |
| Trigger | Create/change/end-date a Pricing Policy/Price List, evaluate applicable policy for a pricing context, or resolve a base price for a Sales transaction. |
| Hard prerequisite | `FR-PRD-005`. |
| Non-hard references | `FR-CUS-001`, `FR-CTL-004`, `FR-FIN-005`. |
| Canonical precedence explicitly fixed upstream | Company Pricing Policy > Group Pricing Policy > Product Default Selling Price. |

**Inputs and context**

Price resolution shall have enough context to resolve Product, owning Group, transaction Company, currency, effective date/time and the business conditions declared by applicable Pricing Policies. Customer context is included only when a condition requires it and such context exists. When currency conversion is required, `FR-FIN-005` exchange-rate context is required for that conversion operation. The source does not define the full Pricing condition catalog, same-level tie-break logic, overlap rules or behavior when no price source can be resolved.

**Processing and validation rules**

1. Pricing Policy/Price List shall be maintainable at Group or Company ownership level, with owner, applicability scope/conditions, currency, effective time and history.
2. Company shall not edit/overwrite the Group Pricing Policy object to express local pricing. A Company-specific difference shall be represented by a separate Company Pricing Policy.
3. A Pricing Policy only needs to declare cases that differ from the Product Default Selling Price; a Product does not need a dedicated policy record for the fallback to exist.
4. Before a policy participates in resolution, it shall be valid/effective for the pricing context, currency and declared business conditions.
5. Canonical resolution shall first consider applicable Company Pricing Policy, then applicable Group Pricing Policy, then applicable Product Default Selling Price.
6. If at least one applicable Company Pricing Policy is resolved for the pricing context/currency, a Group Pricing Policy shall not be selected for the same price decision.
7. If no applicable Company Pricing Policy is resolved but an applicable Group Pricing Policy is resolved, the Group Pricing Policy shall be selected ahead of Product Default Selling Price.
8. If no applicable Company/Group Pricing Policy is resolved, the system shall use the Product Default Selling Price when that default is applicable to the pricing context/currency.
9. The final price decision shall retain enough explanation to identify the selected source object and the conditions/context that caused the source to apply.
10. Pricing Policy may exist without a Customer reference. A customer-specific condition shall be evaluated only when the pricing context provides the relevant Customer context.
11. Shared Workflow is not an unconditional hard prerequisite for Pricing. If the applicable approval rule/policy requires business approval, the Pricing Policy shall not become effective before `FR-CTL-004` Workflow reaches a state permitting effectiveness. If no approval is required, no dummy Workflow instance shall be created solely to satisfy this FR.
12. Price source and transaction shall retain currency. Where conversion is applied, `FR-FIN-005` shall provide the exchange-rate context; original price/value, original currency, FX rate/source/effective time and converted value/currency shall remain traceable.
13. Currency conversion shall not create or mutate a competing Pricing Policy; it is a conversion of the selected price/value in the transaction context.
14. A transaction shall be able to distinguish the canonical base price/source from any later discount/promotion processing. `BR-PRC-002` promotion/discount/coupon behavior remains outside this P1 FR.
15. Pricing administration and price resolution access remain subject to `FR-IAM-003` where user-facing or protected actions apply.

**Canonical price-resolution decision table**

| Applicable Company Policy | Applicable Group Policy | Applicable Product Default Price | Canonical source result |
|---|---|---|---|
| Yes | Any | Any | Company Pricing Policy |
| No | Yes | Any | Group Pricing Policy |
| No | No | Yes | Product Default Selling Price |
| No | No | No | **Not defined by upstream; controlled behavior required before implementation-ready status** |

The table fixes only cross-level precedence. It does **not** choose among multiple applicable Company Policies or among multiple applicable Group Policies. Same-level ambiguity must be resolved by a controlled rule before implementation.

**Outputs and retained evidence**

A resolved price shall identify final price/value, selected source type and source record, applicable ownership level, currency, effective context and relevant condition explanation. When FX is applied, original and converted monetary context plus the rate record/source/effective time shall be retained. Pricing Policy history shall remain traceable over effective periods.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-PRC-001-01` | A Pricing Policy exists | Governance/effective data is inspected | Ownership level, owner, scope/conditions, currency, effective time and history are traceable | `FR-PRC-001.01` |
| `SCN-PRC-001-02` | Company-specific pricing differs from Group | Policy is configured | A separate Company Pricing Policy is created; Group Policy is not overwritten | `.02` |
| `SCN-PRC-001-03` | Applicable Company and Group Policies both exist | Price is resolved | Company Pricing Policy is selected | `.03`, `.06` |
| `SCN-PRC-001-04` | No Company Policy applies but an applicable Group Policy exists | Price is resolved | Group Pricing Policy is selected | `.07` |
| `SCN-PRC-001-05` | No Company/Group Policy applies and applicable Product Default Selling Price exists | Price is resolved | Product Default Selling Price is selected | `.04`, `.08` |
| `SCN-PRC-001-06` | Any canonical source is selected | Decision evidence is inspected | The selected source record and applicable condition/context are explainable | `.05`, `.09` |
| `SCN-PRC-001-07` | The selected source currency requires conversion | Transaction price is converted | Original value/currency, FX rate/source/effective time and converted value remain traceable; no competing policy is created | `.10` |
| `SCN-PRC-001-08` | A Pricing Policy has no Customer-specific condition | Customer context is absent | The policy can still exist and be evaluated on its non-Customer conditions | `.11` |
| `SCN-PRC-001-09` | A Pricing Policy uses a Customer-specific condition | Price resolution runs | That condition is evaluated only when the relevant Customer context exists | `.11` |
| `SCN-PRC-001-10` | An approval rule requires Workflow | Workflow has not reached an effective state | Pricing Policy does not become effective | `.12` |
| `SCN-PRC-001-11` | No approval rule requires Workflow | Pricing Policy otherwise meets effective conditions | Policy may become effective without a dummy Workflow | `.12` |

**TBD / downstream decisions**

- Complete Pricing condition catalog and matching semantics.
- Same-level tie-break/precedence when multiple Company Policies match or multiple Group Policies match the same pricing context/currency.
- Rules for overlapping effective periods and whether ambiguity is prevented at configuration time or resolved deterministically.
- Behavior when no Company Policy, Group Policy or applicable Product Default Selling Price exists.
- Pricing Policy approval-rule catalog and Workflow mapping when approval is required.
- Currency matching/conversion trigger and target-currency rules around `FR-FIN-005`.

### 8.7 Wave C unresolved decisions before implementation-ready status

The following items remain intentionally open because the approved source does not define them. They must be resolved by controlled policy, FRS refinement or design; implementation must not silently establish new business semantics.

| ID | Open decision | Affected FR | Required destination |
|---|---|---|---|
| `WC-TBD-001` | Product item-type catalog plus required attributes and operational policy matrix by item type | `FR-PRD-001` | Product policy / FRS |
| `WC-TBD-002` | Product lifecycle transition matrix and approval requirements | `FR-PRD-001` | Product policy / FRS / Workflow config if applicable |
| `WC-TBD-003` | Product code and alternate-identifier uniqueness/collision rules | `FR-PRD-001` | Identification policy + DES |
| `WC-TBD-004` | Product usage-scope model and precedence for Company/channel/market intersections | `FR-PRD-001` | Product policy / FRS |
| `WC-TBD-005` | UOM conversion-context taxonomy, reciprocal/chained conversion behavior and base-UOM-change controls | `FR-PRD-002` | Product/UOM policy + DES |
| `WC-TBD-006` | Default-price cardinality, effective-period overlap rules and multi-currency behavior | `FR-PRD-005` | Product/Pricing policy / FRS |
| `WC-TBD-007` | Explicit handling when no canonical price source can be resolved | `FR-PRD-005`, `FR-PRC-001` | Pricing policy / FRS |
| `WC-TBD-008` | Customer lifecycle states/transitions and publication/inactivation approval rules | `FR-CUS-001` | Customer policy / FRS |
| `WC-TBD-009` | Customer code scope, mandatory fields by category, contact/address effective/default rules | `FR-CUS-001` | Customer policy / FRS + DES |
| `WC-TBD-010` | Supplier collaboration lifecycle and new-commitment eligibility transitions | `FR-SUP-001` | Supplier policy / FRS |
| `WC-TBD-011` | Supplier sensitive-change Workflow mapping, levels and evidence checklist | `FR-SUP-001` | Supplier policy + Workflow config |
| `WC-TBD-012` | Complete Pricing condition catalog and matching semantics | `FR-PRC-001` | Pricing policy / FRS |
| `WC-TBD-013` | Same-level Pricing Policy tie-break and overlapping-policy ambiguity controls | `FR-PRC-001` | Pricing policy / FRS |
| `WC-TBD-014` | Pricing Policy approval-rule catalog and conditional Workflow mapping | `FR-PRC-001` | Pricing policy + Workflow config |
| `WC-TBD-015` | Pricing currency matching/conversion trigger and target-currency rules around `FR-FIN-005` | `FR-PRC-001`, `FR-PRD-005` | Pricing/FX policy / FRS |

### 8.8 Wave C implementation-readiness gate

Wave C may move from **detailed draft** to **implementation-ready candidate** only when:

1. All six Wave C parent FRs retain traceability to their upstream BR and every upstream success criterion remains covered by at least one acceptance scenario/child FR.
2. `WC-TBD-001`, `002`, `005`, `006`, `007`, `008`, `010`, `011`, `012`, `013` and `015` are resolved sufficiently to remove material ambiguity from Product publication/use, UOM conversion, default price, Customer/Supplier lifecycle and canonical price resolution.
3. Product/Customer/Supplier remain canonical Group Masters of their owning domains; no Company consumer or MDM control creates a competing master.
4. P1 COMBO/GIFT remains classification-only and no composition, inventory/revenue or gift semantics from `BR-PRD-004` are imported into P1.
5. Product Moving Average Cost remains system-derived from `FR-INV-001`, Group-level and non-overridable at Company level; configurable valuation policy from later phase is not imported.
6. Supplier bank-account, tax-code and payment-term changes cannot become effective before the required controlled approval/Workflow decision.
7. Pricing implementation enforces the approved cross-level precedence without silently inventing same-level policy precedence; any same-level rule is controlled and reviewable.
8. Pricing can function without Customer-specific conditions and without unconditional Workflow, while correctly consuming Customer/Workflow only when their declared conditions apply.
9. FX conversion, when used, preserves original and converted monetary context under `FR-FIN-005` and does not mutate the selected Pricing Policy.
10. UAT can be written from controlled rules rather than unstated implementation assumptions.

## 9. Wave D Detailed Functional Specification

### 9.1 Detailed-specification contract

Wave D follows the approved specification order for **Operational Transactions** and closes the remaining P1 functional-detail gap. It refines the existing P1 parent requirements `FR-PUR-001`, `FR-PUR-002`, `FR-PUR-003`, `FR-INV-001`, `FR-INV-002`, `FR-INV-003`, `FR-SAL-001`, `FR-SAL-002` and the supporting P1 foundation requirement `FR-FIN-005`.

Wave D does not create a new phase or expand P1 into full Finance, Manufacturing, Logistics, Integration, Returns, lot/serial genealogy or configurable inventory valuation. It inherits the document contract and conventions established in Waves A-C. In addition:

- P1 business transactions remain owned by exactly one Company under `AR-TXN-001`; Group is derived through the owning Company and never becomes the direct transaction owner.
- Purchase P1 ends at controlled receiving. Supplier invoice matching, landed-cost allocation and Accounts Payable remain outside P1.
- Inventory P1 provides canonical quantity/status/reservation/movement/count-adjustment behavior plus Group-level system-derived Product Moving Average Cost. It does not establish the later configurable inventory valuation capability.
- Sales P1 provides Quotation/Sales Order capture and current-inventory availability/ATP. Credit control, fulfillment lifecycle, returns/RMA/warranty and Promotion/Discount Policy Engine remain outside P1.
- P1 ATP is strictly `usable on-hand - current reservation` in the resolved Company/inventory scope. Incoming PO, planned Production, forecast and other future supply do not increase P1 ATP.
- Lot/serial/expiry may be carried as optional/basic attributes when available, but P1 does not require end-to-end genealogy/traceability.
- Purchase Receipt is an eligible inbound source for Group-level Product Moving Average Cost when officially recorded. Eligibility/value normalization details not defined by the approved source remain controlled decisions rather than implementation assumptions.
- Exchange-rate conversion is a P1 foundation service only. It does not imply realized/unrealized FX accounting, AR/AP, tax, financial close or financial posting capabilities not otherwise approved.
- Exact UI screens, API contracts, persistence schema, document-number formats, reservation allocation algorithms, negative-stock thresholds, sourcing thresholds, receiving tolerances and exchange-rate precedence values remain downstream controlled decisions unless explicitly fixed by the source.

### 9.2 Wave D governing invariants and boundaries

| Invariant / boundary | Wave D consequence |
|---|---|
| `AR-TXN-001` | Every Purchase, Inventory and Sales business transaction resolves exactly one owning Company, business/effective timestamp, lifecycle state and responsible actor/system context. |
| `FR-ORG-002` | Branch/Plant/Warehouse used in a transaction must resolve inside the owning Company hierarchy; cross-Company movement cannot masquerade as internal transfer. |
| `FR-ORG-004` | Company business timezone/currency/calendar provide business-date, currency and business-day context; financial-period ownership is not imported into P1. |
| `FR-IAM-003` | View/create/submit/approve/record/adjust/export/administer actions require their corresponding Resource/Action authorization in the resolved context. |
| `FR-CTL-001` | P1 documents retain controlled lifecycle, numbering and source/replacement/adjustment relationships where applicable. |
| `FR-CTL-002` | Responsibility, important business-event evidence and reconciliation apply to P1 source capabilities; later-phase source classes do not require dummy events. |
| `FR-CTL-004` | Approval is used when an applicable P1 policy requires it; Workflow never grants access Permission. |
| `FR-AUD-001` | Material business/data changes remain traceable with actor, timestamp, before/after context, reason and approval/attachment when applicable. |
| `FR-PRD-002` / `NFR-DAT-001` | Quantity normalization/conversion preserves source quantity and applies controlled precision/rounding. |
| `FR-PRC-001` | Sales base price is resolved through canonical Pricing precedence, not ad-hoc transaction logic. |
| `FR-FIN-005` | Currency conversion retains original and converted amounts/currencies plus the actual rate reference/effective context. |
| P1 Purchase boundary | Invoice matching, landed cost and AP are not P1 obligations. |
| P1 Inventory boundary | End-to-end lot/serial genealogy, configurable valuation policy and intercompany movement are not P1 obligations. |
| P1 Sales boundary | Credit control, returns/RMA, fulfillment lifecycle and automated promotion/tax engines are not P1 obligations. |

### 9.3 Purchase

#### 9.3.1 FR-PUR-001 — Detailed specification: Purchase Request và sourcing

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized requester; authorized sourcing/procurement User; authorized approver when an applicable policy requires approval. Exact role names and approval levels are not defined upstream. |
| Trigger | A business need for Stock Item, Non-stock Item, Service or Expense is recorded; sourcing comparison may be required by an applicable policy/threshold/category. |
| Hard prerequisites | `FR-ORG-002`, `FR-SUP-001`. |
| Conditional consumers | Product Master only where the purchase-line type/policy requires Product; Shared Workflow only where the applicable control requires approval. |
| Explicit exclusions | Budget control is not part of this FR; RFQ/quotation comparison is not mandatory when direct-purchase policy permits bypass. |

**Inputs and context**

A Purchase Request shall retain enough context to identify requester, owning Company/cost-bearing organizational context, need date/time, business reason, purchase-line type and decision/lifecycle status. Supplier may be absent at request creation. Product reference is conditional by line type/policy. When sourcing comparison is required, the request/sourcing context shall retain the quotations or comparison evidence needed to explain the Supplier-selection decision.

**Processing and validation rules**

1. Every Purchase Request shall resolve exactly one owning Company. Any Organization Context used for requesting/cost responsibility shall resolve within that Company under `FR-ORG-002`.
2. Each request line shall be classified as one of the upstream line classes: `Stock Item`, `Non-stock Item`, `Service` or `Expense`.
3. A Purchase Request may be created before Supplier selection. Absence of Supplier at request creation shall not by itself invalidate the request.
4. Supplier shall be present before a purchasing commitment is formed whenever the commitment requires a Supplier; commitment formation is governed by `FR-PUR-002`.
5. Product Master shall be mandatory only when the applicable line-type/policy requires Product. Service/Expense lines shall be allowed without Product Master unless a controlled policy explicitly requires one.
6. If a line references Product, the Product shall be a valid/effective canonical Product allowed for the resolved Company/usage context under `FR-PRD-001`.
7. Sourcing policy may require RFQ/quotation comparison according to controlled policy, threshold or purchase category. The exact threshold/category matrix is not defined upstream.
8. When quotation comparison is required, the system shall retain sufficient comparison evidence for price, quality, delivery time, delivery conditions and payment terms as applicable to the decision.
9. A Supplier-selection decision under required comparison shall identify the responsible decision maker and the evidence/basis used.
10. When controlled direct-purchase policy permits direct purchase, the process shall proceed without creating fake RFQ/quotation records merely to satisfy a generic workflow.
11. Direct-purchase eligibility shall be determinable from a controlled policy; the exact policy conditions are a Wave D open decision.
12. Purchase Request create/change/submit/decide actions shall respect `FR-IAM-003`; approval, where required, shall use the applicable `FR-CTL-004` configuration without granting new access.
13. Material request changes shall retain audit/history evidence according to `FR-AUD-001` and document lifecycle/numbering rules under `FR-CTL-001` where applicable.
14. No budget approval/control shall be implied by this FR. If a future Budget Domain/Integration Requirement is approved, its obligation begins through that controlled scope, not by reinterpretation of P1 Purchase Request.

**Sourcing decision table**

| Applicable sourcing policy | Quotation comparison evidence | Allowed P1 path |
|---|---|---|
| Requires RFQ/comparison | Present and decision basis retained | Continue toward Supplier selection / commitment subject to other controls |
| Requires RFQ/comparison | Missing | Do not treat selection as policy-compliant; controlled resolution required |
| Allows direct purchase | Not present | Direct path may continue; do not generate dummy RFQ/comparison |
| Policy applicability cannot be resolved | Any | **Not defined upstream; controlled behavior required before implementation-ready status** |

**Outputs and retained evidence**

The Purchase Request shall expose request identity/reference, owning Company/context, requester, reason, need date, line classification, Product when required, selected Supplier when available, sourcing/comparison basis when required, decision/lifecycle status and relevant actor/timestamp history.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-PUR-001-01` | An authorized User records a business need | Purchase Request is saved | Requester, Company/cost context, need time, line type and decision status are identifiable | `FR-PUR-001.01` |
| `SCN-PUR-001-02` | Supplier has not yet been selected | Request is created | Request can exist without Supplier; Supplier is deferred until commitment requires it | `.02` |
| `SCN-PUR-001-03` | A Service/Expense line policy does not require Product | Line is recorded | Line is accepted without Product Master | `.03` |
| `SCN-PUR-001-04` | A line policy requires Product | Line is recorded with no valid Product | Line cannot satisfy the policy-required Product reference | `.03` |
| `SCN-PUR-001-05` | Sourcing policy requires quotation comparison | Supplier is selected | Comparison/basis and responsible decision maker are retained | `.04` |
| `SCN-PUR-001-06` | Direct-purchase policy applies | Request proceeds to purchase commitment | Process continues without a dummy RFQ/quotation comparison | `.05` |

**TBD / downstream decisions**

- Purchase-line policy matrix determining when Product is mandatory by `Stock Item`/`Non-stock Item`/`Service`/`Expense`.
- Sourcing policy applicability, RFQ thresholds/categories and minimum comparison evidence.
- Direct-purchase eligibility, exception handling and any approval mapping.
- Purchase Request lifecycle transition matrix and cancellation/change rules.
- Required fields and quantity/value semantics by purchase-line class.

#### 9.3.2 FR-PUR-002 — Detailed specification: Purchase Order và cam kết mua

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized procurement User; authorized approver when policy requires approval; Supplier as referenced business party. |
| Trigger | An authorized purchasing commitment is created from an allowed direct-purchase path or from required quotation/Supplier-selection evidence. |
| Hard prerequisites | `FR-PUR-001`, `FR-SUP-001`. |
| Explicit exclusions | Budget control; Supplier invoice matching; landed-cost allocation; Accounts Payable. |

**Inputs and context**

A Purchase Order shall retain Supplier, owning Company, purchase-line type, source Purchase Request when applicable, quotation/Supplier-selection evidence when policy requires it, quantity or value as appropriate to the line type, price, tax value/context as captured, and delivery/performance terms. The source does not define tax-calculation automation, budget checking, tolerance rules, PO change-order semantics or exact line-level mandatory-field matrix.

**Processing and validation rules**

1. Every Purchase Order shall resolve exactly one owning Company and one Supplier for the commitment context.
2. Supplier shall reference the canonical Supplier Master and be eligible for new commitment under the current Supplier lifecycle/state defined by `FR-SUP-001`.
3. A PO line shall retain its purchase-line classification and the quantity/value semantics appropriate to that line. Exact semantics by line class remain controlled decisions.
4. Where a PO is derived from a Purchase Request, source linkage shall be retained sufficiently to reconcile the commitment to the originating need.
5. Direct PO shall be explicitly identifiable as direct-purchase path and shall be allowed only when the applicable controlled sourcing policy permits it.
6. When sourcing policy requires quotation comparison/Supplier selection, the PO shall retain the required linkage/evidence before the commitment is treated as compliant.
7. PO price, tax value/context and delivery/performance terms shall be retained as part of the confirmed commitment. P1 does not thereby create an operational Tax Engine; automated tax rules remain conditional on the later approved Finance tax capability.
8. The system shall not imply budget availability or budget approval from PO approval/creation. Budget is outside this P1 requirement.
9. PO lifecycle actions shall comply with `FR-CTL-001`; any applicable business approval shall use `FR-CTL-004` while remaining subject to `FR-IAM-003`.
10. Changes/cancellation/replacement that affect an existing commitment shall retain relationship/history sufficient for downstream receiving and audit; exact amendment mechanics are not defined upstream.
11. Receiving shall consume the confirmed PO/commitment through `FR-PUR-003`; receipt quantity/value effects shall not silently rewrite the historical commitment without controlled change evidence.

**Commitment path table**

| Source path | Policy condition | Required commitment evidence |
|---|---|---|
| Quotation-comparison path | Comparison required | Purchase Request/source context + selected Supplier + required comparison/decision evidence |
| Direct-purchase path | Direct purchase allowed | Purchase Request/source context as applicable + Supplier + explicit direct-purchase identification/policy basis |
| Direct-purchase path | Direct purchase not allowed | Commitment path is not policy-compliant |
| Path/policy unresolved | Unknown | **Controlled resolution required; implementation must not silently select a path** |

**Outputs and retained evidence**

The PO shall expose owning Company, Supplier, source/request relationship when applicable, line class, quantity/value, price, tax/context, delivery/performance terms, sourcing path/evidence and current commitment status/history.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-PUR-002-01` | A valid purchase need and Supplier are available | PO is confirmed | Commitment can be reconciled to source need, Supplier, line type, quantity/value, price, tax/context and delivery/performance terms | `FR-PUR-002.01` |
| `SCN-PUR-002-02` | Policy required quotation comparison | PO is formed | Required quotation/Supplier-selection evidence remains linked | `.02` |
| `SCN-PUR-002-03` | Direct-purchase policy applies | Direct PO is formed | Direct path is explicitly identified and no fake comparison is required | `.02` |
| `SCN-PUR-002-04` | No Budget capability is approved | PO is processed | No budget-control acceptance obligation is inferred from this FR | Boundary |

**TBD / downstream decisions**

- PO lifecycle/change-order/cancellation matrix and numbering relationship to replaced/adjusted documents.
- Mandatory fields and quantity-versus-value semantics by purchase-line class.
- Direct-PO evidence/approval mapping and sourcing-policy reference model.
- Tax value capture/rounding behavior in P1 before operational Tax Engine is in scope.
- Delivery/performance term catalog and partial commitment amendment behavior.

#### 9.3.3 FR-PUR-003 — Detailed specification: receiving và chênh lệch nhận hàng

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized receiving User; authorized reviewer/decision maker for receiving exceptions when policy requires. |
| Trigger | Goods/services/items are received or performance is recorded against a Purchase Order/commitment in the approved P1 receiving scope. |
| Hard prerequisites | `FR-PUR-002`, `FR-INV-001`. |
| Non-hard references | Future `BR-INV-004` lot/serial traceability and future `BR-PUR-004` invoice matching/landed cost. |
| P1 inventory consequence | An officially recorded eligible Purchase Receipt updates inventory quantity/status and provides source evidence for Group-level Product Moving Average Cost. |

**Inputs and context**

For each applicable receipt line, the system shall retain source PO/commitment, Product where applicable, ordered/committed quantity, previously received quantity, quantity received now, cumulative received quantity, basic quality/inventory status, owning Company and receiving inventory context. For non-quantity purchase classes, exact receiving/performance evidence remains a controlled decision. Lot/serial and detailed landed-cost data are not mandatory unless their capabilities/policies are separately approved.

**Processing and validation rules**

1. A receipt line shall reference the applicable PO/commitment and remain within the same owning Company transaction context.
2. Where Product applies, the receipt shall reference the same canonical Product intended by the commitment and resolve quantity/UOM context under `FR-PRD-002`.
3. The system shall calculate or otherwise expose ordered/committed quantity, previously received quantity, current receipt quantity and cumulative received quantity for quantity-based receipt lines.
4. Partial receiving shall be allowed. A PO/line need not be fully received in one receipt unless a controlled policy says otherwise.
5. Over-receiving and under-receiving shall be detectable relative to the commitment and processed under controlled policy/threshold. The source does not define the tolerance values or approval levels.
6. Receiving exception shall retain classification, owner and disposition/decision. If policy requires approval, `FR-CTL-004` applies; the approval does not create access rights.
7. Basic quality/status shall be recorded sufficiently to distinguish usable inventory from non-usable/quarantine inventory in P1.
8. Inventory not marked usable shall not be treated as normally allocatable stock under `FR-INV-001`.
9. An officially recorded valid Purchase Receipt shall create the inventory inbound source evidence needed by `FR-INV-001`.
10. When the receipt is an eligible costing inbound transaction, `FR-INV-001` shall recalculate Group-level Product Moving Average Cost using the controlled quantity-weighted moving-average rule after quantity/value are normalized into the applicable costing UOM/currency context.
11. Receipt cancellation/reversal/adjustment shall not erase the original receipt or cost impact. It shall retain traceability between the original inbound transaction, corrective transaction/action and resulting cost effect.
12. P1 shall not require lot/serial genealogy or detailed landed-cost data solely to complete receiving if those later capabilities are not in approved scope.
13. Receiving shall retain responsible actor, timestamps, reason/exception evidence and source relationship under `FR-CTL-002`/`FR-AUD-001` as applicable.

**Receiving quantity relation**

For a quantity-based PO line, the receipt context shall support the relation:

`cumulative_received_after = previously_received + quantity_received_now`

The source requires over/under receiving to be identified but does not define whether commitment closure depends on exact equality, tolerance, explicit close-short decision or another controlled disposition. That closure rule remains a Wave D decision.

**Outputs and retained evidence**

Each receipt shall expose source PO/line, Product when applicable, ordered/committed quantity, prior/current/cumulative receipt quantity, receiving status, usable/non-usable decision, exception classification/owner/disposition, inventory inbound reference and cost-impact traceability when Moving Average Cost is affected.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-PUR-003-01` | A PO line has prior receipts | Another receipt is recorded | Ordered, previously received, current receipt and cumulative received quantities are traceable | `FR-PUR-003.01` |
| `SCN-PUR-003-02` | Only part of committed quantity arrives | Receipt is recorded | Partial receiving is supported without forcing full completion | `.02` |
| `SCN-PUR-003-03` | Receipt exceeds or falls short of controlled expectation | Receipt is evaluated | Variance is identified and handled by the applicable policy/threshold | `.02`, `.04` |
| `SCN-PUR-003-04` | Received stock fails basic usability decision | Receipt is posted | Quantity is retained in non-usable/quarantine status and is not normally allocatable | `.03` |
| `SCN-PUR-003-05` | Lot/serial/landed-cost capabilities are not approved | Receipt is processed | No dummy lot/serial or landed-cost detail is required | `.05` |
| `SCN-PUR-003-06` | An eligible Product receipt is officially recorded | Inventory/cost update runs | Receipt source is retained and Product Moving Average Cost is recalculated under `FR-INV-001` | `.06` |
| `SCN-PUR-003-07` | A posted receipt is later cancelled/adjusted | Corrective action is recorded | Original receipt and traceable cost impact are preserved | `.06` |

**TBD / downstream decisions**

- Receiving tolerance/threshold matrix and authority for over/under acceptance.
- PO-line close/close-short/reopen rules after partial or under receiving.
- Basic receiving quality/status catalog and transition rules.
- Service/Expense/Non-stock receiving or service-performance evidence semantics.
- Exact costing-eligibility definition and value components for Purchase Receipt in P1 Moving Average Cost.
- Receipt cancellation/reversal/adjustment mechanics and their costing treatment.

### 9.4 Inventory

#### 9.4.1 FR-INV-001 — Detailed specification: inventory balance, status, reservation và Moving Average Cost

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Inventory Users and in-scope transaction processes; Sales may consume availability/reservation; Purchase Receipt supplies eligible inbound evidence. |
| Trigger | An approved in-scope inventory movement/status change/reservation or eligible inbound costing event is officially recorded. |
| Hard prerequisites | `FR-ORG-002`, `FR-PRD-001`. |
| Non-hard references | `FR-PUR-003`; future Manufacturing, lot/serial traceability and configurable valuation capabilities. |
| Canonical quantity dimensions | Company, Branch/Plant/Warehouse, location, Product and basic inventory status. |
| Cost ownership | Current Product Moving Average Cost is Group-level, system-derived, non-Company-overridable. |

**Inputs and context**

Inventory quantity shall be attributable to one owning Company and the applicable Organization Context/location/Product/basic status dimensions. Reservation shall be stored/evaluated separately from usable on-hand. Optional lot/serial/expiry/basic quality attributes may be retained when available. Eligible inbound costing requires Product, normalized quantity/value, costing UOM/currency context and source inbound transaction evidence.

**Processing and validation rules**

1. Every inventory quantity record/effect shall resolve exactly one owning Company; Group is derived through that Company.
2. Inventory detail shall be attributable, as applicable, by Company, Branch/Plant/Warehouse, location, Product and basic inventory status.
3. Canonical detail quantities shall reconcile upward to the Company-scope quantity for the same Product/status context without double counting.
4. Inventory shall expose at least usable on-hand, reserved quantity, non-usable/quarantine quantity and in-transit quantity when those statuses apply.
5. Reservation shall remain logically separate from usable on-hand. Reservation shall not silently create or destroy physical on-hand quantity.
6. Non-usable/quarantine quantity shall not be allocated for normal use/sales fulfillment.
7. P1 may retain lot/serial/expiry/basic quality attributes when available, but shall not require end-to-end genealogy/traceability before the later capabilities are approved.
8. For each Product in costing scope, the system shall expose one current Group-level Product Moving Average Cost with update/effective timestamp and latest inbound transaction that changed it.
9. After an eligible Purchase Receipt is officially recorded, Moving Average Cost shall be recalculated using a quantity-weighted moving-average rule over eligible quantity/value normalized to the applicable UOM/currency costing context.
10. When Manufacturing is not in approved scope, Production Finished-Goods Receipt shall not be created or simulated merely to satisfy the costing rule.
11. When Manufacturing later becomes approved, eligible Production Finished-Goods Receipt may participate under the same Group-level Moving Average Cost concept, subject to its controlled value/eligibility policy.
12. Company-specific Moving Average Cost override shall not exist. Configurable valuation method/policy shall not be inferred from this P1 baseline.
13. Inventory changes shall retain source transaction/reason/actor evidence sufficient for `FR-CTL-002` and `FR-AUD-001` where applicable.
14. Inventory actions and visibility shall remain subject to `FR-IAM-003` in the resolved Company/Organization Context.

**Quantity/status invariants**

- Physical/state classification and reservation are distinct concepts: `reserved quantity` is not a second physical on-hand balance.
- P1 ATP consumed by `FR-SAL-002` is defined as `usable on-hand - current reservation` in the same resolved Company/inventory scope.
- No future supply quantity is added to P1 ATP.
- Cross-Company quantity movement is not handled as internal transfer in P1.

**Moving Average Cost conceptual invariant**

For an eligible inbound event, the result shall be a quantity-weighted moving average using the controlled eligible pre-event quantity/value and eligible inbound quantity/value after required UOM/currency normalization. The approved source does not define edge-case policy for zero/negative eligible quantity, excluded statuses, tax/freight components, retrospective correction or same-timestamp ordering; these must be controlled before implementation-ready status.

**Outputs and retained evidence**

The system shall expose canonical quantity by supported dimensions/status, reservation separately from usable on-hand, and the current Group-level Product Moving Average Cost with source/effective context. Movement/source references shall permit reconciliation from quantity change to the P1 source transaction.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-INV-001-01` | Inventory exists across supported dimensions | Detail is aggregated | Detail quantities reconcile to Company-scope totals for the same context | `FR-INV-001.01` |
| `SCN-INV-001-02` | Product has usable, reserved, quarantine and in-transit quantities | Availability is inspected | Each applicable quantity is distinguishable | `.02` |
| `SCN-INV-001-03` | Quantity is quarantine/non-usable | Normal allocation is attempted | Quantity is not allocatable for ordinary use | `.03` |
| `SCN-INV-001-04` | Lot/serial/expiry capability is not approved | P1 inventory is processed | Optional attributes may exist but no end-to-end traceability obligation is imposed | `.04` |
| `SCN-INV-001-05` | Product is in P1 costing scope | Cost context is inspected | One Group-level current Moving Average Cost, timestamp and latest changing inbound source are identifiable | `.05`, `.08` |
| `SCN-INV-001-06` | An eligible Purchase Receipt is officially recorded | Cost update runs | Quantity-weighted moving average is recalculated on normalized eligible quantity/value | `.06` |
| `SCN-INV-001-07` | Manufacturing is not approved | Costing acceptance executes | No fake Production Receipt is required | `.07` |
| `SCN-INV-001-08` | A Company attempts a local cost override | Override is evaluated | Competing Company Moving Average Cost is not created | `.08` |

**TBD / downstream decisions**

- Canonical inventory status catalog and exact quantity-bucket transitions.
- Reservation create/release/consume/expire rules and allocation priority.
- Inventory-location model and whether location is mandatory by Warehouse/Product context.
- Eligible quantity/status set used in Moving Average Cost weighting.
- Eligible inbound value components, currency normalization point and treatment of tax/freight before landed-cost capability exists.
- Moving Average Cost edge cases: zero/negative stock, returns/corrections, backdated receipt, same-timestamp ordering and reversal behavior.
- Costing-scope inclusion/exclusion by Product/item type.

#### 9.4.2 FR-INV-002 — Detailed specification: inventory movement và intra-company transfer

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Inventory User/process owning the movement; source capability actors where movement is generated by an approved source transaction. |
| Trigger | An in-scope inventory movement is officially initiated/recorded, including Purchase inbound, Sales outbound or P1 internal transfer/movement. |
| Hard prerequisites | `FR-INV-001`, `FR-ORG-002`. |
| Non-hard reference | Future `BR-INV-006` intercompany movement. |

**Inputs and context**

Each movement shall retain source document/capability, responsible actor/process, reason, owning Company, Product, quantity/UOM context, source/destination Warehouse/location/context as applicable, and movement state. For internal transfer, source and destination Organization Contexts must belong to the same Company. The source does not define the full P1 movement-type catalog or transfer lifecycle state names beyond distinguishing in-transit from received.

**Processing and validation rules**

1. A movement shall be accepted only for source capability/movement class within the approved scope; later-phase Production, Warranty/Returns or other movement classes do not require dummy transactions in P1.
2. Every movement shall retain source evidence, responsible actor/process and business reason sufficient to explain the quantity effect.
3. For intra-company transfer, source and destination Organization Contexts shall resolve to the same owning Company.
4. Intra-company transfer shall not create an intercompany transaction and shall not change the owning Company merely because inventory changes Warehouse/Branch/Plant within that Company.
5. Transfer shall distinguish quantity dispatched/in-transit from quantity received so source/destination quantities and status can be reconciled.
6. Attempted movement between two different Companies shall be rejected as unsupported P1 cross-company movement and shall not be disguised as internal Warehouse/Branch transfer.
7. Future `BR-INV-006` may route cross-company movement through controlled intercompany flows when approved; that future capability is not a P1 runtime prerequisite.
8. Movement quantity shall use `FR-PRD-002` conversion context where transaction UOM differs from canonical inventory UOM.
9. Movement shall update canonical inventory quantity/status under `FR-INV-001` only when it reaches the applicable controlled transaction/lifecycle state. Exact posting state is not specified upstream.
10. Movement reversal/correction shall preserve the original source/movement relationship and audit evidence rather than silently overwrite historical movement.
11. Authorization and approval, when applicable, shall respect `FR-IAM-003`/`FR-CTL-004`; the source does not state that every movement requires approval.

**Transfer ownership decision table**

| Source Company | Destination Company | P1 classification/result |
|---|---|---|
| Same Company | Same Company | Allowed as intra-company transfer subject to policy and valid Organization Context |
| Company A | Company B, A != B | Reject as unsupported cross-company movement in P1 |
| Company cannot be resolved for either side | Any | Invalid/unresolved ownership; do not treat as internal transfer |

**Outputs and retained evidence**

Each movement shall expose movement identity, source transaction/reference, owning Company, Product/quantity/UOM, source/destination context, current transfer/movement status, responsible actor/reason and quantity/status effects. Internal transfer shall support reconciliation between source dispatch, in-transit and destination receipt as applicable.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-INV-002-01` | An in-scope P1 movement occurs | Movement is recorded | Source document, responsible actor and reason are traceable | `FR-INV-002.01` |
| `SCN-INV-002-02` | Same-Company internal transfer | Transfer progresses | Source, destination, quantity and transfer status reconcile including in-transit where applicable | `.02`, `.03` |
| `SCN-INV-002-03` | Branch/Warehouse contexts belong to one Company | Transfer is recorded | Owning Company remains unchanged and no intercompany transaction is created | `.03` |
| `SCN-INV-002-04` | Source belongs to Company A and destination to Company B | P1 transfer is attempted | Movement is rejected as unsupported cross-company transfer | `.04` |
| `SCN-INV-002-05` | Intercompany capability is not in P1 | P1 acceptance runs | No intercompany route is required to exist | `.05` |
| `SCN-INV-002-06` | A later-phase movement class is not approved | P1 inventory is tested | No dummy movement is created for that class | `.06` |

**TBD / downstream decisions**

- P1 movement-type catalog and posting/official-recording state per type.
- Internal transfer lifecycle states and dispatch/receipt/cancel/reversal semantics.
- Approval thresholds or classes, if any, for internal transfer and other P1 movements.
- UOM normalization timing and precision rules at movement posting.
- Inventory source/destination location requirements by movement type.

#### 9.4.3 FR-INV-003 — Detailed specification: kiểm kê, điều chỉnh và tồn bất thường

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized counter; authorized checker/reviewer; authorized approver for adjustments/exceptions when required. |
| Trigger | Scheduled/risk-based count, detected discrepancy, negative-inventory condition or retrospective adjustment request under P1 policy. |
| Hard prerequisites | `FR-INV-001`, `FR-CTL-004`, `FR-ORG-004`. |
| Non-hard reference | Future `BR-FIN-006` financial period/closing. |

**Inputs and context**

Count/adjustment evidence shall retain owning Company/inventory scope, Product, counted quantity, system quantity/reference point, discrepancy, reason, counter, checker, resulting quantity effect and approval/disposition where applicable. Cost/value explanation shall use current P1 Product Moving Average Cost or the valuation capability actually in effect when a later valuation capability is approved.

**Processing and validation rules**

1. Inventory count may be periodic or risk-triggered; the exact scheduling/risk policy is not defined upstream.
2. Count result shall identify counter and checker as distinct evidence fields/roles; whether they may be the same person under any policy is not defined here and remains subject to controlled authorization/approval design.
3. The system shall calculate/expose quantity discrepancy between controlled system quantity/reference point and counted quantity.
4. Each discrepancy shall have a reason and controlled disposition before the resulting adjustment is treated as official.
5. Adjustment shall expose quantity effect; cost/value effect shall be explainable using P1 Product Moving Average Cost or the approved valuation capability in effect.
6. Negative inventory shall be controlled according to P1 policy. The source does not define whether all negative stock is blocked or which exception/approval thresholds apply.
7. Retrospective/backdated adjustment shall be controlled according to P1 policy and shall preserve history/audit evidence rather than rewrite prior state without trace.
8. Exception shall be either blocked or approved under applicable policy, with adjustment document/reference and reason retained.
9. P1 shall not create a fake closed financial period for inventory-adjustment acceptance because financial close is not in approved P1 scope.
10. `FR-ORG-004` provides Company calendar/time context only; it shall not be treated as owner of financial-period close/reopen semantics.
11. When `BR-FIN-006` later enters approved scope, movement/adjustment after financial-period close shall follow that Finance-owned close/reopen/retrospective-adjustment policy.
12. Count/adjustment actions shall remain subject to `FR-IAM-003` and required approval through `FR-CTL-004`.
13. Count and adjustment history shall remain traceable under `FR-AUD-001`/`FR-CTL-002` as applicable.

**Outputs and retained evidence**

The system shall expose count identity/reference, inventory scope, Product, system/reference quantity, counted quantity, discrepancy, reason, counter, checker, decision/approval, resulting quantity effect, adjustment source/reference and explainable cost/value effect.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-INV-003-01` | A count differs from system quantity | Count is reviewed | Discrepancy, reason, counter, checker and quantity effect are traceable; cost/value effect is explainable | `FR-INV-003.01` |
| `SCN-INV-003-02` | An adjustment violates applicable P1 policy | Adjustment is attempted | It is blocked or routed through the controlled approval/exception path with reason/document evidence | `.02` |
| `SCN-INV-003-03` | Financial close is not in P1 | Inventory-adjustment acceptance is run | No fake closed financial period is required | `.03` |
| `SCN-INV-003-04` | Finance closing becomes approved later | Adjustment targets a closed period | Finance-owned close/reopen/retrospective policy governs the action | `.04` |

**TBD / downstream decisions**

- Count scheduling/risk-trigger policy and freeze/snapshot method for comparison quantity.
- Negative-inventory policy, thresholds, block/override authority and evidence.
- Adjustment approval matrix and retrospective/backdated adjustment limits.
- Rules for count/checker separation and any allowed exception.
- Cost/value effect treatment for adjustment under P1 Moving Average Cost, especially retrospective corrections.

### 9.5 Sales

#### 9.5.1 FR-SAL-001 — Detailed specification: Quotation và Sales Order capture

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Sales User/order-capture actor; Customer or guest-customer context as applicable. External channel integration is not required in P1. |
| Trigger | A Quotation or Sales Order is captured from an approved source/channel through manual or available system input. |
| Hard prerequisites | `FR-CUS-001`, `FR-PRD-001`, `FR-PRC-001`. |
| Non-hard references | Future Promotion/Discount Engine, operational Tax Engine and external Integration capability. |

**Inputs and context**

Quotation/Sales Order shall retain owning Company, source/channel attribution, source/reference code where applicable, Customer or appropriate guest-customer information, Product lines, base-price source/context, sales conditions, validity/commitment dates as applicable, and captured discount/tax values when applicable. The source does not define the full guest-customer minimum-data set, channel catalog, tax/discount entry rules or Quotation-to-Sales-Order conversion mechanics.

**Processing and validation rules**

1. Every Quotation/Sales Order shall resolve exactly one owning Company and valid business timestamp/context under `AR-TXN-001`/`FR-ORG-004`.
2. Source/channel attribution shall be retained. Examples named upstream include website, marketplace, point of sale, phone, salesperson, agent and distributor; this list does not itself require external integration.
3. P1 shall support controlled manual capture of source/channel when Integration capability is not approved/available.
4. Sales Order shall retain a source/reference identifier appropriate to the capture process; uniqueness/format semantics are not defined upstream.
5. Customer shall reference canonical Customer Master when a Customer record is used. The system may instead retain appropriate guest-customer information when the P1 business process allows a guest/retail customer; exact minimum data is a Wave D decision.
6. Product lines shall reference valid/effective canonical Product in the applicable Company/usage scope.
7. Base price shall be resolved through `FR-PRC-001`. The transaction shall retain the selected base-price source and enough context to explain why it applied at confirmation.
8. Quotation/sales conditions and quotation validity/effective period shall be retained as applicable. Exact field catalog and expiry behavior are not defined upstream.
9. Discount and tax values may be stored on the transaction when applicable.
10. P1 shall not imply an automated Promotion/Discount Policy Engine. Automated promotion/discount calculation becomes an obligation only when the corresponding later capability is approved.
11. P1 shall not imply an operational Tax Engine. Automated tax calculation becomes an obligation only when the later Finance tax capability is approved.
12. Manual/captured discount/tax values in P1 shall remain distinguishable from canonical base price/source so downstream users do not misinterpret them as the Pricing Policy result.
13. Quotation/Sales Order create/change/confirm actions shall comply with `FR-IAM-003`, `FR-CTL-001` and applicable `FR-CTL-004` rules without importing later credit-control capability.
14. Sales capture shall retain audit/responsibility evidence for relevant P1 events under `FR-CTL-002`/`FR-AUD-001`.

**Outputs and retained evidence**

The transaction shall expose owning Company, source/channel, source/reference ID, Customer or guest-customer context, Product lines, selected canonical base price/source, captured sales conditions, validity/commitment context and stored discount/tax values when applicable.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-SAL-001-01` | An order is captured | Sales Order is inspected | Source/channel, reference and Customer or appropriate guest-customer information are retained | `FR-SAL-001.01` |
| `SCN-SAL-001-02` | External integration is not approved | Source/channel is manually captured | P1 order capture remains valid without external integration | `.02` |
| `SCN-SAL-001-03` | Product has an applicable canonical pricing source | Quotation/order base price is confirmed | Selected source and applicable pricing condition/context are traceable to `FR-PRC-001` | `.03` |
| `SCN-SAL-001-04` | Discount/tax values are captured in P1 but engines are not approved | Transaction is saved | Values are retained without implying automated promotion/tax-policy calculation | `.04` |

**TBD / downstream decisions**

- Controlled source/channel catalog and source-reference format/uniqueness rules.
- Guest-customer eligibility and minimum required information.
- Quotation lifecycle, validity expiry and Quotation-to-Sales-Order conversion rules.
- Manual discount/tax entry authorization, validation and rounding before later engines are in scope.
- Sales Order confirmation/cancellation/change matrix and any P1 approval classes.

#### 9.5.2 FR-SAL-002 — Detailed specification: availability và fulfillment commitment

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized Sales User/process consuming inventory availability and recording fulfillment commitment; Inventory supplies canonical usable/reserved quantities. |
| Trigger | Availability is requested for a Sales Order/line or a reservation/commitment decision is made in P1. |
| Hard prerequisites | `FR-SAL-001`, `FR-INV-001`. |
| Canonical P1 ATP | `usable on-hand - current reservation` in the same resolved Company/inventory scope. |
| Explicit exclusions | Incoming PO, planned Production, forecast and other future supply do not increase P1 ATP. |

**Inputs and context**

Availability evaluation shall resolve owning Company, Product, relevant inventory scope and current usable on-hand/reservation from `FR-INV-001`. Expected delivery date/commitment and unfulfilled/backordered quantity may be recorded, but the source does not define a future-supply promise algorithm, reservation allocation priority or exact inventory-scope selection algorithm.

**Processing and validation rules**

1. P1 ATP shall be computed only from current inventory in the resolved Company/inventory scope.
2. The canonical formula is `ATP_P1 = usable_on_hand - current_reservation` for the same resolved scope.
3. Incoming Purchase Order, planned Production, forecast and any other future supply shall not increase P1 ATP merely because it exists in the system.
4. Sales User/process shall be able to distinguish at least available-to-promise quantity, quantity already reserved and the portion of demand not currently fulfilled.
5. Expected delivery date/commitment may be stored independently from ATP quantity.
6. Recording/changing expected delivery date shall not itself increase usable on-hand, decrease/increase reservation or otherwise mutate inventory quantity.
7. Future supply may participate in ATP only after a future capability/methodology is explicitly approved; P1 shall not silently implement projected ATP.
8. Reservation effects, if created from Sales Order processing, shall update the reservation bucket under `FR-INV-001` without changing physical on-hand. Exact reservation creation/release/consume rules remain controlled decisions.
9. Availability shall never draw quantity across Company ownership boundaries unless a later approved cross-company capability explicitly provides that behavior.
10. Non-usable/quarantine inventory shall not contribute to P1 usable on-hand/ATP.
11. Availability and reservation actions shall remain subject to authorization and appropriate transaction lifecycle controls.

**ATP decision table**

| Quantity/source | Included in P1 ATP? | Reason |
|---|---|---|
| Current usable on-hand in resolved scope | Yes, positive component | Explicit P1 definition |
| Current reservation in resolved scope | Yes, subtract | Explicit P1 definition |
| Quarantine/non-usable quantity | No | Not usable for normal allocation |
| Incoming Purchase Order | No | Future supply excluded |
| Planned Production | No | Future supply excluded |
| Forecast | No | Future supply excluded |
| Other future supply | No | Future methodology not approved in P1 |

**Outputs and retained evidence**

Availability result shall expose resolved Company/inventory scope, Product, usable on-hand, current reservation, computed ATP, requested/commitment quantity as applicable, unfulfilled quantity and separately stored expected delivery/commitment date when used.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-SAL-002-01` | Usable on-hand and reservations exist in a resolved scope | ATP is requested | `ATP = usable on-hand - reservation` in that scope | `FR-SAL-002.01` |
| `SCN-SAL-002-02` | Demand exceeds P1 ATP | Availability is evaluated | User can distinguish ATP, reserved quantity and unfulfilled portion | `.02` |
| `SCN-SAL-002-03` | Incoming PO/planned Production/forecast exists | ATP is computed | Future supply does not increase P1 ATP | `.03` |
| `SCN-SAL-002-04` | Expected delivery date is entered | Commitment is updated | Date is retained separately and inventory/reservation is not mutated solely by the date | `.04` |
| `SCN-SAL-002-05` | Quarantine stock exists | ATP is computed | Quarantine quantity does not contribute to usable on-hand/ATP | Boundary from `FR-INV-001` |

**TBD / downstream decisions**

- Inventory-scope selection rules for ATP by Warehouse/Branch/location and whether user may choose/aggregate scopes.
- Reservation create/release/consume/expire rules and allocation priority among Sales Orders.
- Behavior when `ATP_P1` would be negative and whether display is floored at zero or shows negative availability.
- Backorder/unfulfilled quantity lifecycle and expected-delivery-date ownership/approval rules.
- Concurrency/locking semantics needed to prevent over-reservation under simultaneous commitments.

### 9.6 Finance foundation supporting P1 transactions

#### 9.6.1 FR-FIN-005 — Detailed specification: multi-currency và canonical Exchange Rate Master

**Operational contract**

| Aspect | Detailed specification |
|---|---|
| Business actors | Authorized FX/reference-data administrator for rate maintenance; authorized P1 business processes consuming a conversion. Exact role names are not defined upstream. |
| Trigger | Exchange-rate record is created/changed or a P1 transaction requires conversion between currencies. |
| Hard prerequisite | `FR-ORG-004`. |
| P1 boundary | Provides canonical rate resolution/conversion context only; does not create realized/unrealized FX accounting or broader Finance posting. |

**Inputs and context**

An exchange-rate record shall retain from currency, to currency, rate type, rate value, source, effective-from/effective time, lifecycle/status and owner. Conversion context shall retain original amount/currency, target currency, resolved rate record/source/effective time and converted amount. The source requires deterministic precedence when multiple rates may apply but does not define the actual precedence table, inverse-rate use, triangulation or rate-type defaults.

**Processing and validation rules**

1. Exchange Rate Master shall be the canonical P1 source for approved currency conversion.
2. Every rate record shall identify from currency, to currency, rate type, positive/valid rate value as allowed by controlled policy, source, effective time, lifecycle/status and owner. The source does not explicitly define rate-value sign/domain validation beyond being a usable rate.
3. When multiple rate records could apply, the system shall resolve exactly one rate deterministically according to a controlled precedence/policy.
4. The actual rate record selected shall be retained with the conversion evidence; rate resolution shall not be silently recomputed later using a newer rate for historical transactions.
5. Every conversion shall retain original amount, original currency, target currency, resolved rate reference/source/effective context and converted amount.
6. A transaction that requires currency conversion shall not be officially recorded/posted if no valid rate can be resolved, unless a separately approved controlled exception rule explicitly permits an exception path.
7. The system shall not invent an exchange rate when no valid rate is available.
8. Exchange-rate history shall be preserved; changing rate policy or adding a newer rate shall not overwrite the record that was effective/used previously.
9. Conversion precision/rounding shall conform to `NFR-DAT-001`; internal precision shall be sufficient to explain the final currency-rounded result.
10. Company business/base/functional currency context shall come from `FR-ORG-004`; locale/display formatting shall not change currency meaning or the canonical monetary values.
11. P1 Exchange Rate Master shall not be interpreted as approval for realized/unrealized FX revaluation, remeasurement, FX gain/loss posting or any other accounting capability absent an approved Finance requirement.
12. Rate maintenance and protected conversion actions shall remain subject to `FR-IAM-003` and audit/history requirements.

**Rate-resolution boundary table**

| Situation | Required P1 behavior |
|---|---|
| Exactly one valid rate resolves under policy | Use it and retain that rate record/reference |
| Multiple rates could apply | Resolve one deterministically using controlled precedence/policy and retain the selected record |
| No valid rate resolves and no approved exception exists | Block official transaction recording/posting requiring conversion |
| No valid rate resolves but an approved controlled exception exists | Follow that explicit exception path and retain its evidence |
| Historical transaction already used a rate | Preserve the used rate reference; do not silently replace it with a newer rate |

**Outputs and retained evidence**

The conversion result shall expose original amount/currency, target currency, converted amount, selected rate value/reference, rate type/source, effective context and any approved exception evidence. Exchange-rate master history shall remain queryable.

**Acceptance scenarios**

| Scenario | Given | When | Then | Trace |
|---|---|---|---|---|
| `SCN-FIN-005-01` | A rate record exists | Record is inspected | From/to currency, rate type/value, source, effective time, lifecycle/status and owner are identifiable | `FR-FIN-005.01` |
| `SCN-FIN-005-02` | Multiple valid rates could apply | Conversion is requested | Exactly one rate is resolved by controlled precedence and the selected record is retained | `.02` |
| `SCN-FIN-005-03` | Conversion succeeds | Evidence is inspected | Original amount/currency, rate/source, converted amount/currency and effective time are traceable | `.03` |
| `SCN-FIN-005-04` | Conversion is required but no valid rate resolves | Official recording is attempted | Transaction is blocked unless a controlled approved exception applies; no rate is invented | `.04` |
| `SCN-FIN-005-05` | A historical rate has been used | A new rate is later published | Historical rate record/evidence remains available and unchanged | `.05` |
| `SCN-FIN-005-06` | P1 Exchange Rate Master is implemented | Finance scope is assessed | No realized/unrealized FX accounting obligation is inferred | `.06` |

**TBD / downstream decisions**

- Rate-type catalog, default rate type by transaction context and controlled precedence when multiple rates apply.
- Direct/inverse pair rules, inversion precision and whether triangulation/cross-rate derivation is permitted.
- Effective-time lookup semantics, timezone/boundary behavior and overlap prevention.
- Source trust/approval model and rate-entry/change approval rules.
- Controlled missing-rate exception policy, if any.
- Conversion rounding point/target-currency rules beyond `NFR-DAT-001` baseline.

### 9.7 Wave D unresolved decisions before implementation-ready status

The following items remain intentionally open because the approved source does not define them. They must be resolved by controlled policy, FRS refinement, NFS or design; implementation must not silently establish new business semantics.

| ID | Open decision | Affected FR | Required destination |
|---|---|---|---|
| `WD-TBD-001` | Purchase-line policy matrix for Product requirement and quantity/value semantics by line class | `FR-PUR-001`, `FR-PUR-002` | Purchase policy / FRS |
| `WD-TBD-002` | Sourcing policy: RFQ/quotation thresholds, categories, comparison evidence and direct-purchase eligibility | `FR-PUR-001`, `FR-PUR-002` | Purchase policy / FRS |
| `WD-TBD-003` | Purchase Request/PO lifecycle, change/cancel/replace relationships and approval mapping | `FR-PUR-001`, `FR-PUR-002` | Purchase policy / FRS / Workflow config |
| `WD-TBD-004` | P1 tax-value capture/validation semantics before operational Tax Engine is approved | `FR-PUR-002`, `FR-SAL-001` | Transaction policy / FRS + `NFR-DAT-001` alignment |
| `WD-TBD-005` | Receiving tolerance/threshold matrix, close-short/reopen semantics and exception authority | `FR-PUR-003` | Receiving policy / FRS / Workflow config |
| `WD-TBD-006` | Basic receiving quality/status catalog and service/non-stock receipt/performance evidence | `FR-PUR-003`, `FR-INV-001` | Receiving/Inventory policy / FRS |
| `WD-TBD-007` | Eligible inbound quantity/value definition and value components for P1 Moving Average Cost | `FR-PUR-003`, `FR-INV-001` | Inventory costing policy / FRS |
| `WD-TBD-008` | Moving Average Cost edge-case policy: zero/negative stock, correction/reversal, backdated receipt and same-time ordering | `FR-INV-001`, `FR-PUR-003`, `FR-INV-003` | Inventory costing policy / FRS + DES |
| `WD-TBD-009` | Canonical P1 inventory status catalog and status-transition rules | `FR-INV-001`, `FR-PUR-003`, `FR-INV-002` | Inventory policy / FRS |
| `WD-TBD-010` | Reservation creation/release/consume/expiry, allocation priority and concurrency protection | `FR-INV-001`, `FR-SAL-002` | Inventory/Sales policy / FRS + DES |
| `WD-TBD-011` | P1 movement-type catalog, internal-transfer lifecycle and posting state | `FR-INV-002` | Inventory movement policy / FRS |
| `WD-TBD-012` | Negative-inventory and retrospective-adjustment policy plus approval thresholds | `FR-INV-003` | Inventory control policy / FRS / Workflow config |
| `WD-TBD-013` | Count snapshot/freeze method, checker separation and count/adjustment approval model | `FR-INV-003` | Inventory count policy / FRS + DES |
| `WD-TBD-014` | Controlled Sales source/channel catalog, guest-customer rules and source-reference format | `FR-SAL-001` | Sales policy / FRS |
| `WD-TBD-015` | Quotation/Sales Order lifecycle, conversion/change/cancel rules and manual discount/tax authorization | `FR-SAL-001` | Sales policy / FRS / Workflow config |
| `WD-TBD-016` | ATP inventory-scope selection, negative ATP display behavior and backorder/expected-delivery semantics | `FR-SAL-002` | Sales/Inventory policy / FRS |
| `WD-TBD-017` | Rate-type catalog, same-context precedence and default rate-type selection | `FR-FIN-005` | FX policy / FRS |
| `WD-TBD-018` | Inverse/triangulated rate rules, effective-time overlap semantics and missing-rate exception policy | `FR-FIN-005` | FX policy / FRS + DES |
| `WD-TBD-019` | Currency conversion rounding point and target-currency rules beyond the NFR baseline | `FR-FIN-005`, `FR-PRC-001` | FX/Data Integrity policy / FRS + NFS |

### 9.8 Wave D implementation-readiness gate

Wave D may move from **detailed draft** to **implementation-ready candidate** only when:

1. All nine Wave D parent FRs retain traceability to their upstream BR and every upstream success criterion is covered by at least one acceptance scenario/child FR.
2. Purchase can run both policy-compliant sourcing paths: required quotation comparison and controlled direct purchase, without creating dummy sourcing evidence.
3. Receiving supports partial receipt, identifies over/under variance, records basic usable/non-usable status and retains source/cost-impact traceability without importing lot/serial genealogy or landed-cost scope.
4. Inventory quantity reconciles across approved dimensions; reservation is separated from usable on-hand; quarantine/non-usable quantity is excluded from ordinary allocation.
5. Internal transfer preserves one owning Company; cross-Company movement is rejected in P1 and not disguised as intra-company transfer.
6. Count/adjustment evidence includes discrepancy, reason, counter/checker, decision and quantity effect; P1 does not fabricate financial-period close semantics.
7. P1 Moving Average Cost remains Group-level, system-derived and quantity-weighted on controlled eligible inbound quantity/value; no Company cost override or configurable valuation policy is introduced.
8. Sales capture retains source/channel, Customer or controlled guest context, Product and canonical base-price source while not implying external Integration, Promotion Engine or Tax Engine.
9. P1 ATP is demonstrably `usable on-hand - current reservation` within resolved Company/inventory scope and excludes all future supply.
10. Exchange-rate resolution is deterministic under a controlled policy; missing required rate blocks official recording unless a controlled approved exception exists; historical rate evidence is preserved.
11. `WD-TBD-001`, `002`, `005`, `007`, `008`, `009`, `010`, `011`, `012`, `014`, `015`, `016`, `017`, `018` and `019` are resolved sufficiently to remove material implementation ambiguity.
12. Authorization, lifecycle, approval, audit and responsibility evidence from Waves A/B remain applied to Wave D transactions rather than reimplemented as competing rules.
13. After this gate, the FRS has detailed specification coverage for all **32/32 P1 Business Requirements**; the remaining P1 decomposition work is companion NFS/DES/UAT/NFR verification rather than another functional-BR wave.


## 10. Downstream Package Sequence

Để hoàn tất decomposition P1 mà không phá vỡ traceability contract của User Requirements, package downstream nên được phát hành theo các artifact class sau:

1. `FRS-ERP-P1-001` — Functional Requirements Specification (tài liệu này), chứa `FR-*` cho 32 P1 Business Requirements.
2. P1 Non-functional Specification — chứa 7 `NFS-*` tương ứng với P1 NFR.
3. P1 Design/Architecture Specification — realize 3 P1 `AR-*` và các `FR-*` đã approved.
4. P1 UAT Specification — `UAT-*` cho Business Requirements/Functional Requirements.
5. P1 NFR Verification Specification — `NFR-TEST-*` cho các NFR P1.
6. Sau khi từng artifact được approved/configuration-controlled, cập nhật actual traceability trong `REQ-ERP-001` từ planned references sang actual references tương ứng.

## 11. Draft Review Gates

- **Coverage gate:** đủ 32/32 P1 BR có parent `FR-*` và không có FR orphan.
- **Scope gate:** không biến P2/P3 reference thành P1 hard dependency hoặc acceptance obligation.
- **Governance gate:** mọi `governed_by`/architecture invariant của upstream được giữ.
- **Acceptance gate:** mọi upstream `success_criteria` có ít nhất một child FR traceable.
- **Traceability gate:** mọi planned UAT/NFS/DES ID được giữ đúng class; actual traceability chỉ cập nhật sau approval/configuration control.
- **Semantic gate:** không thay đổi canonical ownership, transaction ownership, authorization semantics, pricing precedence, P1 ATP, Moving Average Cost, FX, audit hoặc other invariant đã chốt trong User Requirements.

## 12. Revision Notes

### v0.5.0 — Wave D detailed functional specification draft

- Expanded Purchase, Inventory, Sales and the remaining P1 Exchange Rate foundation parent FRs into detailed operational contracts.
- Added source-bounded sourcing/direct-purchase paths, PO commitment, partial/over/under receiving, inventory quantity/status/reservation, internal transfer, count/adjustment, current-inventory ATP and deterministic FX rate-resolution behavior.
- Added explicit P1 transaction boundaries so invoice matching/landed cost/AP, configurable inventory valuation, lot/serial genealogy, intercompany movement, credit/returns, Promotion/Tax engines and realized/unrealized FX accounting are not silently imported into P1.
- Added decision tables for sourcing path, transfer ownership, ATP components and exchange-rate resolution, while leaving source-undefined thresholds, lifecycle rules, reservation algorithms, costing edge cases and rate precedence in `WD-TBD-*`.
- Added 19 controlled `WD-TBD-*` decisions and a Wave D implementation-readiness gate.
- Detailed functional coverage now reaches all 32/32 P1 Business Requirements; remaining P1 decomposition work is companion NFS/DES/UAT/NFR verification.

### v0.4.0 — Wave C detailed functional specification draft

- Expanded Product, Product UOM/conversion, Product Default Selling Price, Customer, Supplier and Pricing parent FRs into detailed operational contracts.
- Added source-bounded lifecycle/scope, canonical master ownership, UOM effective conversion, Supplier sensitive-change approval and canonical price-resolution behavior.
- Added an explicit canonical pricing decision table preserving only the approved cross-level precedence: Company Pricing Policy > Group Pricing Policy > Product Default Selling Price.
- Added `WC-TBD-*` decisions for same-level pricing ties, no-price-source behavior, Product/Customer/Supplier lifecycle rules, UOM conversion semantics, default-price cardinality and FX matching instead of inventing unstated rules.
- Preserved P1 scope boundaries: COMBO/GIFT remain classification-only, Product Moving Average Cost remains system-derived, configurable inventory valuation/PLM/promotion capabilities remain outside Wave C.
- Preserved all 32 P1 parent FRs; Waves A and B remain unchanged and Wave D remains decomposition baseline.

### v0.3.0 — Wave B detailed functional specification draft

- Expanded Shared Controls, Central Audit, System Foundation and MDM parent FRs into detailed operational contracts.
- Added source-bounded document lifecycle/numbering, conditional reconciliation/event scope, Shared Workflow governance, Central Audit boundaries, locale/display separation, reference-data governance, ownership/override rules and sensitive-master change semantics.
- Added explicit Wave B acceptance scenarios and a `WB-TBD-*` register instead of inventing numbering, workflow, audit, reconciliation, code-format, override or master-lifecycle rules not defined by the source.
- Preserved conditional applicability: later-phase event sources, Document capability, IAM security-event monitoring and full Finance controls are not promoted into P1.
- Preserved all 32 P1 parent FRs; Wave A remains unchanged and Wave C-D remain decomposition baseline.

### v0.2.0 — Wave A detailed functional specification draft

- Expanded Organization, Employee and base IAM parent FRs into detailed operational contracts.
- Added source-bounded actors/triggers, inputs/context, processing and validation rules, lifecycle/effective-time boundaries, authorization/audit notes and acceptance scenarios.
- Added canonical authorization decision algorithm and decision table directly from `BR-IAM-003`.
- Added explicit Wave A TBD register instead of inventing hierarchy matrices, lifecycle enums, Assignment-overlap rules, Resource/Action catalog, Policy DSL or sensitive-field catalog not defined by the source.
- Preserved all 32 P1 parent FRs and companion routing for 7 NFR and 3 Architectural Rules; Wave B-D were still decomposition baseline at v0.2.0.

### v0.1.0 — Initial P1 FRS decomposition draft

- Created from `REQ-ERP-001 v1.0.2` P1_CORE baseline.
- Covered all 32 P1 Business Requirements with one parent `FR-*` each.
- Preserved routing for all 7 P1 NFR and 3 P1 Architectural Rules.
- Added draft child FR handles from upstream success criteria without introducing new business scope.
