---
document_id: "FRS-ERP-P1-001"
title: "Functional Requirements Specification - Dinktech Mini ERP V2.0 - P1 Core"
phase: "Functional Requirements Definition"
status: "draft"
version: "0.1.0"
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

## 6. Downstream Package Sequence

Để hoàn tất decomposition P1 mà không phá vỡ traceability contract của User Requirements, package downstream nên được phát hành theo các artifact class sau:

1. `FRS-ERP-P1-001` — Functional Requirements Specification (tài liệu này), chứa `FR-*` cho 32 P1 Business Requirements.
2. P1 Non-functional Specification — chứa 7 `NFS-*` tương ứng với P1 NFR.
3. P1 Design/Architecture Specification — realize 3 P1 `AR-*` và các `FR-*` đã approved.
4. P1 UAT Specification — `UAT-*` cho Business Requirements/Functional Requirements.
5. P1 NFR Verification Specification — `NFR-TEST-*` cho các NFR P1.
6. Sau khi từng artifact được approved/configuration-controlled, cập nhật actual traceability trong `REQ-ERP-001` từ planned references sang actual references tương ứng.

## 7. Draft Review Gates

- **Coverage gate:** đủ 32/32 P1 BR có parent `FR-*` và không có FR orphan.
- **Scope gate:** không biến P2/P3 reference thành P1 hard dependency hoặc acceptance obligation.
- **Governance gate:** mọi `governed_by`/architecture invariant của upstream được giữ.
- **Acceptance gate:** mọi upstream `success_criteria` có ít nhất một child FR traceable.
- **Traceability gate:** mọi planned UAT/NFS/DES ID được giữ đúng class; actual traceability chỉ cập nhật sau approval/configuration control.
- **Semantic gate:** không thay đổi canonical ownership, transaction ownership, authorization semantics, pricing precedence, P1 ATP, Moving Average Cost, FX, audit hoặc other invariant đã chốt trong User Requirements.

## 8. Revision Notes

### v0.1.0 — Initial P1 FRS decomposition draft

- Created from `REQ-ERP-001 v1.0.2` P1_CORE baseline.
- Covered all 32 P1 Business Requirements with one parent `FR-*` each.
- Preserved routing for all 7 P1 NFR and 3 P1 Architectural Rules.
- Added draft child FR handles from upstream success criteria without introducing new business scope.
