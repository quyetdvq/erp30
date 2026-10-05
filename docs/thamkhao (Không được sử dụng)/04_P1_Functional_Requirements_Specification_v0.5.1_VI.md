---
document_id: "FRS-ERP-P1-001"
title: "Đặc tả Yêu cầu Chức năng - Dinktech Mini ERP V2.0 - P1 Core"
phase: "Đặc tả Yêu cầu Chức năng"
status: "draft"
version: "0.5.1"
owner: "Chủ sở hữu Sản phẩm/Yêu cầu"
readiness_state: "detailed_draft"
authority: "Phân rã downstream theo mô hình Controlled Parallel cho REQ-ERP-001 P1_CORE; có thể đạt mức provisional implementation-ready để phục vụ thiết kế/ước lượng/prototype, nhưng chỉ trở thành baseline triển khai có kiểm soát sau khi upstream áp dụng được phê duyệt và FRS được phê duyệt/đưa dưới configuration control."
source_baseline: "REQ-ERP-001 v1.0.2"
planning_baseline: "PLAN-ERP-001 v1.0.1"
target_phase: "P1_CORE"
---

# Đặc tả Yêu cầu Chức năng - Dinktech Mini ERP V2.0 - P1 Core

## 0. Quy ước ngôn ngữ

Tiếng Việt là ngôn ngữ trình bày chính của tài liệu. Các định danh, ID, enum, tên artifact, tên field YAML và các thuật ngữ kỹ thuật/canonical như `FR`, `BR`, `UAT`, `DES`, `NFR`, `Policy`, `Workflow`, `Permission`, `Profile`, `Group`, `Company`, `Resource/Action`, `ALLOW`, `DENY`, `TBD` được giữ nguyên khi việc dịch có thể làm giảm tính traceability hoặc thay đổi nghĩa chuẩn.

## 1. Hợp đồng tài liệu

Tài liệu này là bản decomposition chức năng đầu tiên cho phạm vi `P1_CORE` của `REQ-ERP-001 v1.0.2`. Nội dung chỉ được suy ra từ User Requirements nguồn; không tự mở rộng capability, dependency hoặc acceptance obligation ngoài baseline nguồn.

- `BR-*` thuộc P1 được phân rã thành `FR-*` theo `planned_implemented_by` đã khai báo trong User Requirements.
- `AR-*` thuộc P1 không được chuyển thành Functional Requirement; chúng được route sang Design/Architecture realization (`DES-*`) và được liệt kê trong tài liệu này để bảo toàn scope/governance.
- `NFR-*` thuộc P1 không được chuyển thành Functional Requirement; chúng được route sang Đặc tả phi chức năng (`NFS-*`) và Đặc tả Verification NFR (`NFR-TEST-*`).
- `depends_on` giữ nguyên ý nghĩa Hard Prerequisite từ User Requirements. `governed_by`, `references` và `applies_to` không được biến thành lifecycle dependency.
- Forward reference tới capability phase sau vẫn là conditional reference; không tạo runtime/acceptance obligation cho P1 nếu capability đó chưa nằm trong phạm vi đã phê duyệt.
- Các child ID dạng `FR-XXX-NNN.nn` trong draft này là decomposition handle nội bộ của FRS. Chúng chỉ trở thành controlled functional requirement sau khi FRS được approved/configuration-controlled.
- User Requirements chỉ nên chuyển reference từ `planned_implemented_by` sang `implemented_by` sau khi FRS này được approved và đưa dưới configuration control.


### 1.1 Mô hình trạng thái sẵn sàng

FRS này áp dụng mô hình **Controlled Parallel** với ba mức readiness độc lập với `status` tài liệu:

1. `detailed_draft` — được phép tiếp tục decomposition/refinement khi upstream còn draft.
2. `provisional_implementation_ready` — đủ chi tiết để hỗ trợ DES, estimate và prototype có kiểm soát, nhưng **không phải controlled implementation baseline và không cho phép release build dựa trên FRS này như nguồn requirement đã phê duyệt**.
3. `controlled_implementation_ready` — chỉ đạt khi requirement upstream áp dụng đã approved/configuration-controlled, FRS đã approved/configuration-controlled và các gate áp dụng đã đạt.

Tại revision `v0.5.1`, `REQ-ERP-001 v1.0.2` vẫn đang ở trạng thái draft, vì vậy FRS này giữ `readiness_state=detailed_draft`. Việc một Wave đạt provisional gate không tự nâng toàn bộ tài liệu lên controlled implementation baseline.

### 1.2 Hợp đồng thẩm quyền và lineage

Authority được xác định theo **field/semantic ownership**, không theo nguyên tắc "artifact mới hơn thắng":

| Loại artifact | Thẩm quyền canonical trong chuỗi này |
|---|---|
| `PLAN-ERP-001` | `target_phase`, phase allocation và specification-wave ordering. |
| `REQ-ERP-001` | Requirement semantics, `depends_on`, `governed_by`, `references`, `applies_to`, priority và success criteria. |
| `FRS-ERP-P1-001` | Functional decomposition của BR đã được upstream quyết định; không được tự thay đổi upstream scope/semantic. |
| `DES-*` / `NFS-*` / `UAT-*` / `NFR-TEST-*` | Realization hoặc verification; không tạo requirement authority cạnh tranh. |

Nếu revision của REQ thay đổi `depends_on`, dependency semantics theo REQ nhưng dependency/phase closure phải được validate lại. Nếu thay đổi đó làm `target_phase` hoặc dependency closure của Phase Plan không còn hợp lệ thì phải reconcile/revise controlled Phase Plan; không được âm thầm giữ hai baseline mâu thuẫn.

### 1.3 Hợp đồng bằng chứng acceptance

Mỗi acceptance-oriented child `FR-*.nn` phải resolve được tới ít nhất một **explicit verification evidence path**. Một evidence có thể cover nhiều child FR và một child FR có thể được cover bởi nhiều evidence; FRS không áp dụng quy tắc máy móc `1 child FR = 1 test case`.

- Business-observable behavior mặc định route tới `UAT-*`/acceptance scenario.
- Structural/canonical-ownership invariant có thể route tới `DES-REVIEW` hoặc integration/design review khi UAT không phải evidence class phù hợp.
- Child FR chỉ tồn tại mà chưa có evidence path không đủ để pass Acceptance Gate.

### 1.4 Hợp đồng quản trị TBD

TBD register sử dụng bốn class: `BUSINESS_DECISION`, `DESIGN_DECISION`, `VERIFICATION_DECISION`, `DEFERRED_OUT_OF_SCOPE`. Mỗi TBD bắt buộc có `class`, `owner`, `target_artifact`, `due_gate` và `status`.

| Class | Quy tắc blocking mặc định |
|---|---|
| `BUSINESS_DECISION` | Phải đạt `APPROVED` trước `PROVISIONAL_READY`; implementation không được tự chọn business behavior. |
| `DESIGN_DECISION` | Có thể còn open tại provisional FRS gate nếu business obligation đã rõ và owner/target/due gate đầy đủ; phải `APPROVED` trước `BEFORE_BUILD` của capability liên quan. |
| `VERIFICATION_DECISION` | Phải `APPROVED` trước `BEFORE_UAT` hoặc NFR acceptance gate tương ứng. |
| `DEFERRED_OUT_OF_SCOPE` | Chỉ non-blocking khi có explicit scope evidence rằng P1 không phụ thuộc vào decision đó. |

Status vocabulary của register là `OPEN`, `RESOLVED`, `APPROVED`, `DEFERRED`. Gate logic được tính từ `class + status + due_gate + target_artifact`; **không hard-code danh sách TBD ID làm nguồn quyết định**. Tại mỗi review, reviewer có thể sinh blocking-ID snapshot từ register để làm audit evidence; snapshot chỉ là report tại thời điểm review và không thay thế register làm source of truth.

## 2. Baseline phạm vi P1

P1 Core gồm **42 controlled items**: **32 Business Requirements**, **7 Non-functional Requirements** và **3 Architectural Rules**. FRS này trực tiếp đặc tả 32 Business Requirements; 10 item còn lại được giữ trong companion-routing matrix để không mất scope.

| Loại controlled item | Số lượng P1 | Loại artifact downstream |
|---|---:|---|
| Business Requirement | 32 | `FR-*` trong FRS này |
| Non-functional Requirement | 7 | `NFS-*` + `NFR-TEST-*` |
| Architectural Rule | 3 | `DES-*` + `DES-REVIEW-*` |
| **Total** | **42** |  |

### 2.1 Phân bổ miền chức năng

| Miền nguồn | Số BR P1 |
|---|---:|
| 4.1 Tổ chức & Cấu trúc doanh nghiệp | 3 |
| 4.2 Nhân sự & Cấu trúc tổ chức | 2 |
| 4.3 Phân quyền & Bảo mật | 4 |
| 4.4 Kiểm soát liên miền & Workflow | 3 |
| 4.5 Audit & Traceability | 1 |
| 4.6 Nền tảng hệ thống | 1 |
| 4.8 Dữ liệu chủ & Quản trị dữ liệu | 3 |
| 4.9 Product | 3 |
| 4.11 Customer & CRM | 1 |
| 4.12 Pricing & Promotion | 1 |
| 4.13 Supplier | 1 |
| 4.14 Purchase | 3 |
| 4.15 Inventory | 3 |
| 4.17 Sales | 2 |
| 4.18 Vận hành tài chính | 1 |

### 2.2 Ma trận traceability chức năng P1

| BR upstream | Artifact chức năng | UAT dự kiến | Miền nguồn |
|---|---|---|---|
| `BR-ORG-001` | `FR-ORG-001` | `UAT-ORG-001` | 4.1 Tổ chức & Cấu trúc doanh nghiệp |
| `BR-ORG-002` | `FR-ORG-002` | `UAT-ORG-002` | 4.1 Tổ chức & Cấu trúc doanh nghiệp |
| `BR-ORG-004` | `FR-ORG-004` | `UAT-ORG-004` | 4.1 Tổ chức & Cấu trúc doanh nghiệp |
| `BR-EMP-001` | `FR-EMP-001` | `UAT-EMP-001` | 4.2 Nhân sự & Cấu trúc tổ chức |
| `BR-EMP-002` | `FR-EMP-002` | `UAT-EMP-002` | 4.2 Nhân sự & Cấu trúc tổ chức |
| `BR-IAM-001` | `FR-IAM-001` | `UAT-IAM-001` | 4.3 Phân quyền & Bảo mật |
| `BR-IAM-002` | `FR-IAM-002` | `UAT-IAM-002` | 4.3 Phân quyền & Bảo mật |
| `BR-IAM-003` | `FR-IAM-003` | `UAT-IAM-003` | 4.3 Phân quyền & Bảo mật |
| `BR-IAM-005` | `FR-IAM-005` | `UAT-IAM-005` | 4.3 Phân quyền & Bảo mật |
| `BR-CTL-001` | `FR-CTL-001` | `UAT-CTL-001` | 4.4 Kiểm soát liên miền & Workflow |
| `BR-CTL-002` | `FR-CTL-002` | `UAT-CTL-002` | 4.4 Kiểm soát liên miền & Workflow |
| `BR-CTL-004` | `FR-CTL-004` | `UAT-CTL-004` | 4.4 Kiểm soát liên miền & Workflow |
| `BR-AUD-001` | `FR-AUD-001` | `UAT-AUD-001` | 4.5 Audit & Traceability |
| `BR-SYS-001` | `FR-SYS-001` | `UAT-SYS-001` | 4.6 Nền tảng hệ thống |
| `BR-MDM-001` | `FR-MDM-001` | `UAT-MDM-001` | 4.8 Dữ liệu chủ & Quản trị dữ liệu |
| `BR-MDM-002` | `FR-MDM-002` | `UAT-MDM-002` | 4.8 Dữ liệu chủ & Quản trị dữ liệu |
| `BR-MDM-003` | `FR-MDM-003` | `UAT-MDM-003` | 4.8 Dữ liệu chủ & Quản trị dữ liệu |
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
| `BR-FIN-005` | `FR-FIN-005` | `UAT-FIN-005` | 4.18 Vận hành tài chính |

### 2.3 Routing companion cho kiến trúc P1

| Architectural Rule | Design Realization dự kiến | Design Review dự kiến |
|---|---|---|
| `AR-DATA-OWNERSHIP-001` | `DES-AR-DATA-OWNERSHIP-001` | `DES-REVIEW-AR-DATA-OWNERSHIP-001` |
| `AR-DOM-001` | `DES-AR-DOM-001` | `DES-REVIEW-AR-DOM-001` |
| `AR-TXN-001` | `DES-AR-TXN-001` | `DES-REVIEW-AR-TXN-001` |

### 2.4 Routing companion cho phi chức năng P1

| NFR | NFS dự kiến | Verification dự kiến |
|---|---|---|
| `NFR-SEC-001` | `NFS-SEC-001` | `NFR-TEST-SEC-001` |
| `NFR-AVL-001` | `NFS-AVL-001` | `NFR-TEST-AVL-001` |
| `NFR-PER-001` | `NFS-PER-001` | `NFR-TEST-PER-001` |
| `NFR-AUD-001` | `NFS-AUD-001` | `NFR-TEST-AUD-001` |
| `NFR-DAT-001` | `NFS-DAT-001` | `NFR-TEST-DAT-001` |
| `NFR-USE-001` | `NFS-USE-001` | `NFR-TEST-USE-001` |
| `NFR-PLT-001` | `NFS-PLT-001` | `NFR-TEST-PLT-001` |

## 3. Quy tắc định nghĩa Functional Requirement

1. Mỗi parent `FR-*` trong tài liệu này trace trực tiếp về đúng một `BR-*` P1 theo baseline hiện hành.
2. `Functional intent` giữ nguyên nội dung `description` của BR nguồn.
3. `Source-derived functional rules` tách các rule có trong description để hỗ trợ thiết kế/implementation nhưng không thay đổi nghĩa nguồn.
4. `Acceptance-oriented child requirements` được tạo từ từng `success_criteria` nguồn; wording của criterion được giữ nguyên để tránh semantic drift.
5. Dependency ở cấp FR được suy ra chỉ khi upstream `depends_on` trỏ tới một BR P1 đã có planned FR. Upstream dependency khác vẫn được giữ nguyên bằng ID nguồn.
6. Các reference tới P2/P3/FUTURE không được dùng như điều kiện bắt buộc để P1 chạy hoặc được acceptance, trừ khi User Requirements nguồn quy định rõ capability đó đã nằm trong phạm vi đã phê duyệt.
7. Mỗi acceptance-oriented child requirement phải có explicit verification evidence path theo Section 1.3; một evidence có thể cover nhiều child FR và không bắt buộc mọi child FR phải có test case riêng.
8. Design/canonical-ownership invariant có thể dùng `DES-REVIEW` hoặc integration/design review thay cho business UAT khi đó là evidence class phù hợp; routing này không làm thay đổi nội dung child FR nguồn.

## 4. Functional Requirements P1

### 4.1 Tổ chức & Cấu trúc doanh nghiệp

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

**Ý định chức năng**

Hệ thống hỗ trợ quản lý nhiều Group độc lập trên cùng một nền tảng. Mỗi Group có dữ liệu, cơ cấu tổ chức và phạm vi quản trị riêng.
Mỗi Group được đại diện bởi một Group Business Entity duy nhất, là business identity chuẩn của Group và lưu các thông tin nghiệp vụ/quản trị của Group.
Organization Context `type=GROUP` chỉ dùng để biểu diễn Group đó trong hierarchy và phạm vi tổ chức. Mỗi Group Business Entity có đúng một Organization Context `type=GROUP` tương ứng theo quan hệ 1:1; Organization Context này không phải một Group master riêng.
Business Transaction không thuộc trực tiếp Group. Mỗi Business Transaction phải thuộc đúng một Company; Group của transaction được xác định thông qua Company sở hữu transaction đó.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-ORG-001-R01` — Hệ thống hỗ trợ quản lý nhiều Group độc lập trên cùng một nền tảng.
- `FR-ORG-001-R02` — Mỗi Group có dữ liệu, cơ cấu tổ chức và phạm vi quản trị riêng.
- `FR-ORG-001-R03` — Mỗi Group được đại diện bởi một Group Business Entity duy nhất, là business identity chuẩn của Group và lưu các thông tin nghiệp vụ/quản trị của Group.
- `FR-ORG-001-R04` — Organization Context `type=GROUP` chỉ dùng để biểu diễn Group đó trong hierarchy và phạm vi tổ chức.
- `FR-ORG-001-R05` — Mỗi Group Business Entity có đúng một Organization Context `type=GROUP` tương ứng theo quan hệ 1:1; Organization Context này không phải một Group master riêng.
- `FR-ORG-001-R06` — Business Transaction không thuộc trực tiếp Group.
- `FR-ORG-001-R07` — Mỗi Business Transaction phải thuộc đúng một Company; Group của transaction được xác định thông qua Company sở hữu transaction đó.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-ORG-001.01` | Có thể quản lý nhiều Group độc lập trên cùng một nền tảng mà dữ liệu giữa các Group không bị trộn lẫn. |
| `FR-ORG-001.02` | Mỗi Group có đúng một Group Business Entity làm business identity chuẩn. |
| `FR-ORG-001.03` | Mỗi Group Business Entity có đúng một Organization Context `type=GROUP` tương ứng theo quan hệ 1:1. |
| `FR-ORG-001.04` | Không được tạo Organization Context `type=GROUP` nếu không liên kết với một Group Business Entity. |
| `FR-ORG-001.05` | Không được tạo nhiều Group Business Entity cho cùng một Organization Context `type=GROUP`. |
| `FR-ORG-001.06` | Thông tin nghiệp vụ và quản trị của Group được lưu tại Group Business Entity; Organization Context chỉ quản lý hierarchy và organizational scope. |
| `FR-ORG-001.07` | Mỗi Business Transaction thuộc đúng một Company; Group tương ứng được xác định thông qua Company và không được gán làm transaction owner trực tiếp. |
| `FR-ORG-001.08` | Có thể tổng hợp dữ liệu của các Company thuộc cùng một Group mà vẫn giữ đúng Company ownership của từng transaction. |

**Ghi chú traceability**

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

**Ý định chức năng**

Hệ thống quản lý Organization Context bằng tập type cố định gồm GROUP, COMPANY, BRANCH, PLANT và WAREHOUSE.
Organization Context `type=GROUP` chỉ là biểu diễn của Group trong hierarchy và organizational scope. Nó phải tham chiếu đúng một Group Business Entity theo quan hệ 1:1 và không phải một Group master riêng.
Mỗi Business Transaction thuộc đúng một Company. Branch, Plant và Warehouse được tổ chức trong hierarchy của Company tương ứng.
Hierarchy không bắt buộc phải có đầy đủ mọi cấp. Có thể bỏ qua cấp trung gian khi mô hình vận hành cho phép, ví dụ Company có thể quản lý Warehouse trực tiếp mà không bắt buộc phải có Branch hoặc Plant ở giữa.
Department và Position không phải Organization Context. Hai đối tượng này thuộc Employee domain. Tuy nhiên hệ thống phải cho phép hiển thị chúng cùng Organization Context trong một organizational view thống nhất, dựa trên Employee Assignment đang có hiệu lực, để người dùng có thể xem cơ cấu theo Group, Company, đơn vị vận hành, Department, Position và Employee.
Company, Branch, Plant và Warehouse phải có lifecycle/state và thời gian hiệu lực. Department có lifecycle riêng do Employee domain quản lý.
Quan hệ parent-child giữa các Organization Context phải được kiểm tra theo controlled artifact `ORG-CONTEXT-HIERARCHY-POLICY`. Artifact này là nguồn business rule chính thức cho các parent-child combination được phép hoặc không được phép. FRS/DES chỉ triển khai và áp dụng policy này, không được hard-code một hierarchy rule khác làm nguồn quyết định cạnh tranh.

**Các quy tắc chức năng suy ra từ nguồn**

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

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
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
| `FR-ORG-002.16` | `ORG-CONTEXT-HIERARCHY-POLICY` xác định owner, version, thời gian hiệu lực và các parent-child combination được phép hoặc không được phép. |
| `FR-ORG-002.17` | Parent-child combination không được policy cho phép phải bị từ chối, trừ khi policy hiện hành cho phép một ngoại lệ được kiểm soát. |
| `FR-ORG-002.18` | FRS/DES không được hard-code hierarchy rule khác làm nguồn business rule cạnh tranh với `ORG-CONTEXT-HIERARCHY-POLICY`. |

**Ghi chú traceability**

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

**Ý định chức năng**

Quản lý business timezone, business/base/functional currency, Company Business Calendar và business-period/calendar settings theo từng Company. Các giá trị này quyết định business meaning của ngày làm việc, thời điểm và số tiền; locale/display formatting thuộc BR-SYS-001 và không được thay đổi các giá trị nghiệp vụ này. Company Business Calendar là canonical source để xác định business day/non-business day, Company holiday và các business cutoff/deadline cần tính theo ngày làm việc. Calendar này thuộc Organization/Foundation và không thay thế Employee Work Calendar của HR. Financial period, period close/reopen và periodic allocation không thuộc ownership của requirement P1 này; khi capability đó nằm trong phạm vi đã phê duyệt, Company Business Calendar chỉ cung cấp calendar/business-day context và tham chiếu financial-period/close context do BR-FIN-006 quản lý.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-ORG-004-R01` — Quản lý business timezone, business/base/functional currency, Company Business Calendar và business-period/calendar settings theo từng Company.
- `FR-ORG-004-R02` — Các giá trị này quyết định business meaning của ngày làm việc, thời điểm và số tiền; locale/display formatting thuộc BR-SYS-001 và không được thay đổi các giá trị nghiệp vụ này.
- `FR-ORG-004-R03` — Company Business Calendar là canonical source để xác định business day/non-business day, Company holiday và các business cutoff/deadline cần tính theo ngày làm việc.
- `FR-ORG-004-R04` — Calendar này thuộc Organization/Foundation và không thay thế Employee Work Calendar của HR.
- `FR-ORG-004-R05` — Financial period, period close/reopen và periodic allocation không thuộc ownership của requirement P1 này; khi capability đó nằm trong phạm vi đã phê duyệt, Company Business Calendar chỉ cung cấp calendar/business-day context và tham chiếu financial-period/close context do BR-FIN-006 quản lý.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-ORG-004.01` | Mỗi chứng từ xác định được Company policy về business timezone và business/base/functional currency có hiệu lực tại thời điểm nghiệp vụ. |
| `FR-ORG-004.02` | Thay đổi locale/display setting không làm thay đổi business currency, canonical business timestamp/business date hoặc monetary value đã ghi nhận. |
| `FR-ORG-004.03` | Mỗi Company xác định được Business Calendar có thời gian hiệu lực và phân loại được business day/non-business day cùng Company holiday áp dụng. |
| `FR-ORG-004.04` | Các deadline/cutoff dùng khái niệm ngày làm việc có thể resolve cùng một Company Business Calendar canonical. |
| `FR-ORG-004.05` | Employee Work Calendar có thể bổ sung lịch theo Assignment/Branch/Plant/employee group mà không tạo định nghĩa Company business day cạnh tranh. |
| `FR-ORG-004.06` | Financial period và close/reopen chỉ tạo acceptance obligation khi BR-FIN-006 nằm trong phạm vi đã phê duyệt; BR-ORG-004 không sở hữu lifecycle của financial period. |

**Ghi chú traceability**

- Upstream: `BR-ORG-004` (source line ~301).
- Planned verification artifact from UR baseline: `UAT-ORG-004`.
- Non-hard references: `BR-SYS-001`, `BR-FIN-006`.

### 4.2 Nhân sự & Cấu trúc tổ chức

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

**Ý định chức năng**

Quản lý Employee độc lập với User Identity. Employee domain là owning domain của Employee và Employee Assignment. Employee là Group Master canonical trong Group; Employee Assignment là Company Master và biểu diễn quan hệ làm việc của Employee trong một Company bằng cách tham chiếu Organization Context, Department, Position và thời gian hiệu lực. HRP và IAM chỉ mở rộng hoặc tham chiếu các đối tượng này, không tạo Employee/Employee Assignment master cạnh tranh.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-EMP-001-R01` — Quản lý Employee độc lập với User Identity.
- `FR-EMP-001-R02` — Employee domain là owning domain của Employee và Employee Assignment.
- `FR-EMP-001-R03` — Employee là Group Master canonical trong Group; Employee Assignment là Company Master và biểu diễn quan hệ làm việc của Employee trong một Company bằng cách tham chiếu Organization Context, Department, Position và thời gian hiệu lực.
- `FR-EMP-001-R04` — HRP và IAM chỉ mở rộng hoặc tham chiếu các đối tượng này, không tạo Employee/Employee Assignment master cạnh tranh.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-EMP-001.01` | Mỗi Employee xác định được Group sở hữu và có thể được tham chiếu bởi các Company trong Group theo quyền được cấp. |
| `FR-EMP-001.02` | Mỗi Employee Assignment xác định được đúng một Company sở hữu, Organization Context, Department, Position và thời gian hiệu lực. |
| `FR-EMP-001.03` | Một Employee có thể có nhiều Assignment theo Company và theo thời gian mà vẫn dùng cùng Employee canonical. |
| `FR-EMP-001.04` | Lịch sử thay đổi Organization Context, Department, Position và thời gian hiệu lực của Assignment được bảo toàn. |
| `FR-EMP-001.05` | Có thể xác định Employee/Employee Assignment canonical mà HRP và IAM đang tham chiếu. |

**Ghi chú traceability**

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

**Ý định chức năng**

Department và Position là master thuộc Employee domain. Department là Company Master, có hierarchy, lifecycle/state và thời gian hiệu lực riêng trong Company. Position là Group Master thể hiện chức danh và level dùng chung trong Group. Department/Position được Employee Assignment tham chiếu theo thời gian hiệu lực và không tự sinh Permission hoặc Organization Scope.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-EMP-002-R01` — Department và Position là master thuộc Employee domain.
- `FR-EMP-002-R02` — Department là Company Master, có hierarchy, lifecycle/state và thời gian hiệu lực riêng trong Company.
- `FR-EMP-002-R03` — Position là Group Master thể hiện chức danh và level dùng chung trong Group.
- `FR-EMP-002-R04` — Department/Position được Employee Assignment tham chiếu theo thời gian hiệu lực và không tự sinh Permission hoặc Organization Scope.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-EMP-002.01` | Mỗi Department xác định được Company sở hữu, parent trong hierarchy khi áp dụng, lifecycle/state và thời gian hiệu lực. |
| `FR-EMP-002.02` | Department ngừng hiệu lực không được dùng cho Employee Assignment mới nhưng lịch sử Assignment cũ vẫn được bảo toàn. |
| `FR-EMP-002.03` | Mỗi Position xác định được Group sở hữu, chức danh/level, trạng thái và thời gian hiệu lực. |
| `FR-EMP-002.04` | Employee Assignment chỉ tham chiếu Department thuộc đúng Company của Assignment và Position thuộc Group tương ứng đang có hiệu lực. |
| `FR-EMP-002.05` | Department hoặc Position không tự tạo Permission hay Organization Scope. |

**Ghi chú traceability**

- Upstream: `BR-EMP-002` (source line ~389).
- Planned verification artifact from UR baseline: `UAT-EMP-002`.

### 4.3 Phân quyền & Bảo mật

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

**Ý định chức năng**

Quản lý User Identity như authentication principal ở platform level, độc lập với Employee/Employee Assignment master. User Identity xác định loại identity, trạng thái xác thực/truy cập và lifecycle ở cấp nền tảng. IAM không sở hữu hoặc tạo Employee/Employee Assignment master thay thế. Quản lý explicit Group Access Membership để một User Identity được truy cập từng Group. Ordinary ERP User membership phải reference Employee canonical thuộc chính Group đó; membership không trỏ cứng vào một Employee Assignment. Khi authorization cần Company/Organization Context, active Employee Assignment(s) của Employee được resolve theo Company/context và thời gian hiệu lực tại thời điểm đánh giá. System Admin/service identity là ngoại lệ có kiểm soát: nếu không reference Employee thì loại identity, scope và căn cứ ngoại lệ phải explicit và audit được. Dữ liệu giữa các Group phải được cách ly. User Identity chỉ được truy cập Group theo explicit Group Access Membership hoặc ngoại lệ được kiểm soátal identity scope; membership/quyền của Group này không được suy ra thành quyền ở Group khác.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-IAM-001-R01` — Quản lý User Identity như authentication principal ở platform level, độc lập với Employee/Employee Assignment master.
- `FR-IAM-001-R02` — User Identity xác định loại identity, trạng thái xác thực/truy cập và lifecycle ở cấp nền tảng.
- `FR-IAM-001-R03` — IAM không sở hữu hoặc tạo Employee/Employee Assignment master thay thế.
- `FR-IAM-001-R04` — Quản lý explicit Group Access Membership để một User Identity được truy cập từng Group.
- `FR-IAM-001-R05` — Ordinary ERP User membership phải reference Employee canonical thuộc chính Group đó; membership không trỏ cứng vào một Employee Assignment.
- `FR-IAM-001-R06` — Khi authorization cần Company/Organization Context, active Employee Assignment(s) của Employee được resolve theo Company/context và thời gian hiệu lực tại thời điểm đánh giá.
- `FR-IAM-001-R07` — System Admin/service identity là ngoại lệ có kiểm soát: nếu không reference Employee thì loại identity, scope và căn cứ ngoại lệ phải explicit và audit được.
- `FR-IAM-001-R08` — Dữ liệu giữa các Group phải được cách ly.
- `FR-IAM-001-R09` — User Identity chỉ được truy cập Group theo explicit Group Access Membership hoặc ngoại lệ được kiểm soátal identity scope; membership/quyền của Group này không được suy ra thành quyền ở Group khác.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
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

**Ghi chú traceability**

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

**Ý định chức năng**

Hệ thống quản lý Permission theo cặp Resource/Action. Mỗi Permission xác định người dùng được phép thực hiện hành động nào trên loại Resource nào và là đơn vị quyền cơ sở của hệ thống Authorization.
Profile là tập hợp các Permission. Người dùng nhận quyền thông qua Profile; Policy Rule không được tạo thêm Permission ngoài các Permission đã được định nghĩa và cấp qua Profile.
Mỗi Resource/Action Permission có thuộc tính canonical `policy_required`. Thuộc tính này quyết định cách xử lý khi không có Policy Rule nào match trong Authorization Evaluation. `policy_required` thuộc Permission và có cùng giá trị bất kể Permission đó được cấp qua Profile nào.
Business Group được dùng để hỗ trợ quản trị phân quyền bằng cách ánh xạ người dùng hoặc nhóm nghiệp vụ tới Profile phù hợp. Business Group là khái niệm của Authorization và hoàn toàn khác với Group Business Entity trong Organization Model.
Business Group không thay thế Profile, Permission hoặc Policy Rule và không tự tạo thêm quyền cho người dùng.

**Các quy tắc chức năng suy ra từ nguồn**

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

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
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

**Ghi chú traceability**

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

**Ý định chức năng**

Phân quyền theo mô hình Profile Permission kết hợp Policy Rule trong Group/Company/Organization Context đã resolve qua BR-IAM-001. Permission được định nghĩa theo Resource/Action và được cấp thông qua Profile; Policy Rule chỉ giới hạn quyền hiệu lực theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect. Authorization Evaluation không cấp thêm quyền ngoài Profile; Authorization Evaluation phải deterministic theo các rule của capability này. Quản lý Policy Rule để giới hạn Permission theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect. Department không trở thành Organization Context và không tự tạo Permission/Scope; khi Department condition được dùng, Actor Department phải được resolve từ Employee Assignment có hiệu lực còn Resource Responsible Department phải lấy từ ownership/responsibility của resource. Condition luôn được đánh giá trong đúng Company context. Quản lý Policy theo version, thời gian hiệu lực và lịch sử thay đổi để quyết định Authorization có thể được giải thích theo chính sách áp dụng tại thời điểm đánh giá. Áp dụng một Authorization Evaluation thống nhất: Profile Permission là gate quyền cơ sở; applicable Policy Rules chỉ được evaluate sau khi Permission tồn tại; Effect aggregate theo `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; NO_MATCH được quyết định bởi canonical `policy_required` của Resource/Action Permission. Policy không bao giờ tạo Permission mới. Quản lý quyền truy cập theo từng loại hành động trên cùng một đối tượng nghiệp vụ, bao gồm xem, tạo hoặc gửi yêu cầu, phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu và quản trị. Quyền đối với từng hành động phải được xác định độc lập; việc được cấp một quyền không mặc nhiên tạo ra quyền thực hiện hành động khác trên cùng đối tượng.

**Các quy tắc chức năng suy ra từ nguồn**

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

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
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

**Ghi chú traceability**

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

**Ý định chức năng**

Bảo vệ dữ liệu nhạy cảm như lương, thông tin định danh, giá vốn, chiết khấu và tài khoản ngân hàng theo phạm vi công việc. Truy cập hoặc xuất dữ liệu nhạy cảm chỉ được thực hiện khi User có Resource/Action Permission tương ứng và Authorization cuối cùng Evaluation theo BR-IAM-003 trả về ALLOW trong đúng Group/Company/Organization Context. Policy Rule không tự tạo quyền và không được dùng thay cho Resource/Action Permission.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-IAM-005-R01` — Bảo vệ dữ liệu nhạy cảm như lương, thông tin định danh, giá vốn, chiết khấu và tài khoản ngân hàng theo phạm vi công việc.
- `FR-IAM-005-R02` — Truy cập hoặc xuất dữ liệu nhạy cảm chỉ được thực hiện khi User có Resource/Action Permission tương ứng và Authorization cuối cùng Evaluation theo BR-IAM-003 trả về ALLOW trong đúng Group/Company/Organization Context.
- `FR-IAM-005-R03` — Policy Rule không tự tạo quyền và không được dùng thay cho Resource/Action Permission.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-IAM-005.01` | User không có Resource/Action Permission tương ứng hoặc có Authorization cuối cùng Evaluation khác ALLOW không xem, sửa hoặc xuất được dữ liệu nhạy cảm cho hành động đó. |
| `FR-IAM-005.02` | User chỉ xem được dữ liệu nhạy cảm khi Permission của hành động xem tồn tại và Authorization cuối cùng Evaluation = ALLOW trong context hiện tại. |
| `FR-IAM-005.03` | Quyền export dữ liệu nhạy cảm được đánh giá độc lập với quyền xem; quyền xem không mặc nhiên cấp quyền export. |

**Ghi chú traceability**

- Upstream: `BR-IAM-005` (source line ~550).
- Planned verification artifact from UR baseline: `UAT-IAM-005`.

### 4.4 Kiểm soát liên miền & Workflow

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

**Ý định chức năng**

Quản lý trạng thái chung cho chứng từ và phân biệt rõ Nháp, Chờ xem xét, Đã duyệt, Đã ghi nhận chính thức, Hoàn tất, Hủy và Đảo/Điều chỉnh. Các module có thể mở rộng trạng thái nghiệp vụ riêng nhưng không được làm sai ý nghĩa trạng thái chung. Quản lý số chứng từ theo loại, công ty, chi nhánh, kỳ và chính sách đánh số được phê duyệt. Thể hiện quan hệ giữa chứng từ nguồn, chứng từ phát sinh, chứng từ thay thế, chứng từ điều chỉnh và chứng từ đảo.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-CTL-001-R01` — Quản lý trạng thái chung cho chứng từ và phân biệt rõ Nháp, Chờ xem xét, Đã duyệt, Đã ghi nhận chính thức, Hoàn tất, Hủy và Đảo/Điều chỉnh.
- `FR-CTL-001-R02` — Các module có thể mở rộng trạng thái nghiệp vụ riêng nhưng không được làm sai ý nghĩa trạng thái chung.
- `FR-CTL-001-R03` — Quản lý số chứng từ theo loại, công ty, chi nhánh, kỳ và chính sách đánh số được phê duyệt.
- `FR-CTL-001-R04` — Thể hiện quan hệ giữa chứng từ nguồn, chứng từ phát sinh, chứng từ thay thế, chứng từ điều chỉnh và chứng từ đảo.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-CTL-001.01` | Ý nghĩa trạng thái chung được thống nhất giữa các miền và có thể ánh xạ trạng thái module về trạng thái chung khi cần báo cáo/kiểm soát. |
| `FR-CTL-001.02` | Số đã cấp chính thức không bị tái sử dụng. |
| `FR-CTL-001.03` | Người kiểm tra biết chứng từ nào tạo ra hoặc thay đổi số liệu hiện tại. |

**Ghi chú traceability**

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

**Ý định chức năng**

Truy xuất người tạo, người phê duyệt, người ghi nhận, người điều chỉnh, thời điểm, lý do và tài liệu liên quan. Bảo đảm tổng số lượng, tổng tiền, tổng công nợ và tổng tồn giữa các chứng từ liên quan có thể đối chiếu khi các source capability tương ứng nằm trong phạm vi đã phê duyệt. Ghi nhận các sự kiện nghiệp vụ quan trọng của capability đang nằm trong phạm vi đã phê duyệt, ví dụ bản ghi chuẩn được công bố, đơn được xác nhận, nhận hàng và xuất kho trong P1; các event class như production completion, invoice, payment, return hoặc payroll lock chỉ trở thành acceptance obligation khi source capability tương ứng được approved.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-CTL-002-R01` — Truy xuất người tạo, người phê duyệt, người ghi nhận, người điều chỉnh, thời điểm, lý do và tài liệu liên quan.
- `FR-CTL-002-R02` — Bảo đảm tổng số lượng, tổng tiền, tổng công nợ và tổng tồn giữa các chứng từ liên quan có thể đối chiếu khi các source capability tương ứng nằm trong phạm vi đã phê duyệt.
- `FR-CTL-002-R03` — Ghi nhận các sự kiện nghiệp vụ quan trọng của capability đang nằm trong phạm vi đã phê duyệt, ví dụ bản ghi chuẩn được công bố, đơn được xác nhận, nhận hàng và xuất kho trong P1; các event class như production completion, invoice, payment, return hoặc payroll lock chỉ trở thành acceptance obligation khi source capability tương ứng được approved.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-CTL-002.01` | Mẫu giao dịch quan trọng thuộc capability đang trong phạm vi đã phê duyệt có hồ sơ trách nhiệm đầy đủ. |
| `FR-CTL-002.02` | Đối với reconciliation class có source capability đang trong phạm vi đã phê duyệt, chênh lệch có tổng kiểm soát, chủ sở hữu và trạng thái xử lý. |
| `FR-CTL-002.03` | Mỗi event class thuộc acceptance scope xác định được thời điểm, đối tượng, chủ sở hữu và kết quả nghiệp vụ. |
| `FR-CTL-002.04` | Source capability chưa nằm trong phạm vi đã phê duyệt không buộc phải tạo event hoặc dữ liệu giả chỉ để thỏa BR-CTL-002. |

**Ghi chú traceability**

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

**Ý định chức năng**

Một khung business approval dùng chung có thể áp dụng theo loại chứng từ, giá trị, rủi ro, Company, đơn vị và vai trò, bao gồm một hoặc nhiều cấp, từ chối, trả lại để chỉnh sửa, hủy, chuyển cấp, ủy quyền và lịch sử quyết định. Business approval semantics và Shared Workflow Engine được quản lý thống nhất trong capability này. Một Shared Workflow Engine dùng chung để thực thi business approval cần governance/audit, gồm điều kiện phê duyệt, participant/người phê duyệt, trạng thái workflow, ủy quyền và lịch sử quyết định. State transition thuần túy của module không bắt buộc dùng Workflow. Approval Workflow không cấp Permission truy cập. Phân tách trách nhiệm quản trị Workflow: Developer tạo Workflow Type; System Admin triển khai và quản trị Workflow; Business Owner cấu hình Rule và Participant trong phạm vi được cho phép.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-CTL-004-R01` — Một khung business approval dùng chung có thể áp dụng theo loại chứng từ, giá trị, rủi ro, Company, đơn vị và vai trò, bao gồm một hoặc nhiều cấp, từ chối, trả lại để chỉnh sửa, hủy, chuyển cấp, ủy quyền và lịch sử quyết định.
- `FR-CTL-004-R02` — Business approval semantics và Shared Workflow Engine được quản lý thống nhất trong capability này.
- `FR-CTL-004-R03` — Một Shared Workflow Engine dùng chung để thực thi business approval cần governance/audit, gồm điều kiện phê duyệt, participant/người phê duyệt, trạng thái workflow, ủy quyền và lịch sử quyết định.
- `FR-CTL-004-R04` — State transition thuần túy của module không bắt buộc dùng Workflow.
- `FR-CTL-004-R05` — Approval Workflow không cấp Permission truy cập.
- `FR-CTL-004-R06` — Phân tách trách nhiệm quản trị Workflow: Developer tạo Workflow Type; System Admin triển khai và quản trị Workflow; Business Owner cấu hình Rule và Participant trong phạm vi được cho phép.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-CTL-004.01` | Mỗi chứng từ thuộc diện business approval xác định được rule/cấp phê duyệt, người quyết định, kết quả, thời điểm, lý do và tình trạng hiệu lực của quyết định. |
| `FR-CTL-004.02` | Mỗi giao dịch thuộc diện business approval xác định được workflow, rule áp dụng, participant/người duyệt, trạng thái và lịch sử quyết định. |
| `FR-CTL-004.03` | Quyết định workflow không tạo Permission mới ngoài quyền đã được Authorization cho phép. |
| `FR-CTL-004.04` | Mỗi Workflow Type xác định được nguồn tạo, phạm vi triển khai, người quản trị và Business Owner phụ trách rule/participant. |
| `FR-CTL-004.05` | Business Owner không thay đổi Workflow Type ở mức developer và System Admin không thay đổi rule nghiệp vụ ngoài phạm vi được giao mà không có lịch sử. |

**Ghi chú traceability**

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

**Ý định chức năng**

Một Central Audit Trail cho thay đổi dữ liệu và nghiệp vụ quan trọng, gồm Before Value, After Value, Reason, Approval, Attachment, người thực hiện và thời điểm thay đổi. Central Audit chịu trách nhiệm business/data-change audit; IAM audit chỉ chịu trách nhiệm các sự kiện xác thực, truy cập và quản trị Authorization. BR-CTL-002 có thể cung cấp consumer/event/reconciliation context nhưng không phải Hard Prerequisite để Central Audit Trail tồn tại.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-AUD-001-R01` — Một Central Audit Trail cho thay đổi dữ liệu và nghiệp vụ quan trọng, gồm Before Value, After Value, Reason, Approval, Attachment, người thực hiện và thời điểm thay đổi.
- `FR-AUD-001-R02` — Central Audit chịu trách nhiệm business/data-change audit; IAM audit chỉ chịu trách nhiệm các sự kiện xác thực, truy cập và quản trị Authorization.
- `FR-AUD-001-R03` — BR-CTL-002 có thể cung cấp consumer/event/reconciliation context nhưng không phải Hard Prerequisite để Central Audit Trail tồn tại.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-AUD-001.01` | Có thể truy vết dữ liệu trước và sau thay đổi đối với nghiệp vụ quan trọng. |
| `FR-AUD-001.02` | Audit record xác định được actor/User, thời điểm, đối tượng, thay đổi, lý do và approval/attachment khi áp dụng. |
| `FR-AUD-001.03` | Có thể phân biệt rõ IAM security event với business/data-change audit event. |

**Ghi chú traceability**

- Upstream: `BR-AUD-001` (source line ~684).
- Planned verification artifact from UR baseline: `UAT-AUD-001`.
- Non-hard references: `BR-CTL-002`.

### 4.6 Nền tảng hệ thống

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

**Ý định chức năng**

Quản lý cấu hình dùng chung về locale/display như timezone hiển thị mặc định, định dạng ngày giờ, ký hiệu/định dạng tiền tệ, dấu phân cách và số thập phân hiển thị, ngôn ngữ và quy tắc hiển thị. Business/base/functional currency và business timezone của Company thuộc BR-ORG-004 và không được thay đổi business meaning hoặc monetary value.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-SYS-001-R01` — Quản lý cấu hình dùng chung về locale/display như timezone hiển thị mặc định, định dạng ngày giờ, ký hiệu/định dạng tiền tệ, dấu phân cách và số thập phân hiển thị, ngôn ngữ và quy tắc hiển thị.
- `FR-SYS-001-R02` — Business/base/functional currency và business timezone của Company thuộc BR-ORG-004 và không được thay đổi business meaning hoặc monetary value.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-SYS-001.01` | Cấu hình locale/display có phạm vi áp dụng, người quản lý và lịch sử thay đổi. |
| `FR-SYS-001.02` | Thay đổi locale/display không làm đổi Company business currency, business timestamp/kỳ hoặc giá trị tiền đã ghi nhận. |

**Ghi chú traceability**

- Upstream: `BR-SYS-001` (source line ~708).
- Planned verification artifact from UR baseline: `UAT-SYS-001`.

### 4.8 Dữ liệu chủ & Quản trị dữ liệu

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

**Ý định chức năng**

Quản lý các standard, governance rules và common primitives dùng chung như đơn vị tính, nhóm/phân loại dùng chung, quốc gia, địa bàn, mã HS, thuế suất và các danh mục nền tảng khác. Ownership của reference/common data phải tuân thủ AR-DATA-OWNERSHIP-001. Product, Customer, Supplier và Employee là business entity do domain tương ứng sở hữu; MDM không trở thành owning domain thay thế. Áp dụng chuẩn định danh và quy tắc mã thống nhất cho Customer, Supplier và địa điểm. Customer và Supplier do domain tương ứng sở hữu; MDM chỉ cung cấp standard/governance cho định danh và không sở hữu business entity. Employee không thuộc phạm vi business entity của requirement này. RD Code là specialization của chuẩn định danh cho vật tư, bán thành phẩm, thành phẩm và Supplier; nó không tạo business entity/master identity cạnh tranh. Mỗi entity vẫn có internal identity, business code, RD Code khi áp dụng và optional external/partner codes. Quản lý thống nhất địa chỉ của khách hàng, nhà cung cấp, nhân viên và các đối tượng liên quan bằng controlled internal reference datasets đã được Dinktech phê duyệt, hiện gồm hai bảng `provinces` và `wards`; `wards` bao phủ đơn vị Phường/Xã theo dữ liệu được phê duyệt. Schema/field chi tiết của hai bảng được xác định ở database design và không thuộc User Requirement này. Nếu trong tương lai một danh mục được thay thế hoặc xác lập từ external legal/regulatory/official normative source, quy tắc `source_refs` tại Section 2.3 áp dụng trước khi normative rule tương ứng được approved.

**Các quy tắc chức năng suy ra từ nguồn**

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

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
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
| `FR-MDM-001.11` | Có thể xác định owner, approval status và version/thời gian hiệu lực của controlled address datasets. Hiện tại P1 datasets `provinces`/`wards` là internal approved data và không yêu cầu `source_refs`; nếu sau này dùng external normative source thì controlled source reference phải resolve theo Section 2.3 trước approval của rule tương ứng. |

**Ghi chú traceability**

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

**Ý định chức năng**

Áp dụng đúng phạm vi chia sẻ, sử dụng và chỉnh sửa dữ liệu dựa trên ownership level System Master, Group Master, Company Master và Transaction Data. Người dùng chỉ được xem hoặc thay đổi dữ liệu trong phạm vi được ủy quyền. Phân loại ownership của dữ liệu theo bốn mức System Master, Group Master, Company Master và Transaction Data; Transaction Data phải thuộc đúng một Company. Reference/common data tuân thủ AR-DATA-OWNERSHIP-001. Doanh nghiệp áp dụng Field Level Override theo nguyên tắc default deny: Company chỉ được override field của Group Master khi owning-domain requirement/policy khai báo rõ field đó là overrideable; các field không được khai báo tiếp tục sử dụng giá trị Group Master. Field Level Override không tạo một business entity master mới ở Company và không thay đổi owning domain của entity. Cơ chế này không được dùng để Company sửa trực tiếp Group Pricing Policy hoặc Product Default Selling Price; khác biệt giá ở Company được biểu diễn bằng Company Pricing Policy theo BR-PRC-001; Product Default Selling Price tiếp tục được quản lý theo BR-PRD-005.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-MDM-002-R01` — Áp dụng đúng phạm vi chia sẻ, sử dụng và chỉnh sửa dữ liệu dựa trên ownership level System Master, Group Master, Company Master và Transaction Data.
- `FR-MDM-002-R02` — Người dùng chỉ được xem hoặc thay đổi dữ liệu trong phạm vi được ủy quyền.
- `FR-MDM-002-R03` — Phân loại ownership của dữ liệu theo bốn mức System Master, Group Master, Company Master và Transaction Data; Transaction Data phải thuộc đúng một Company.
- `FR-MDM-002-R04` — Reference/common data tuân thủ AR-DATA-OWNERSHIP-001.
- `FR-MDM-002-R05` — Doanh nghiệp áp dụng Field Level Override theo nguyên tắc default deny: Company chỉ được override field của Group Master khi owning-domain requirement/policy khai báo rõ field đó là overrideable; các field không được khai báo tiếp tục sử dụng giá trị Group Master.
- `FR-MDM-002-R06` — Field Level Override không tạo một business entity master mới ở Company và không thay đổi owning domain của entity.
- `FR-MDM-002-R07` — Cơ chế này không được dùng để Company sửa trực tiếp Group Pricing Policy hoặc Product Default Selling Price; khác biệt giá ở Company được biểu diễn bằng Company Pricing Policy theo BR-PRC-001; Product Default Selling Price tiếp tục được quản lý theo BR-PRD-005.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
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

**Ghi chú traceability**

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

**Ý định chức năng**

Kiểm soát trạng thái, ngày hiệu lực và lý do thay đổi của bản ghi chuẩn. Cơ chế phân công và phê duyệt thay đổi đối với thuộc tính dữ liệu nhạy cảm như mã thuế, tài khoản ngân hàng, điều kiện thanh toán và các manual override thuộc diện policy cho phép. Group-level Product Moving Average Cost được hệ thống tính/cập nhật theo BR-INV-001 là system-derived value, không phải manual master-data change và không tự kích hoạt approval workflow của requirement này. Nếu một manual override đối với cost được phê duyệt ở capability/policy tương lai, override đó phải đi qua controlled sensitive-change rule riêng. Business approval thuộc requirement này sử dụng Shared Workflow để giữ quyết định và lịch sử.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-MDM-003-R01` — Kiểm soát trạng thái, ngày hiệu lực và lý do thay đổi của bản ghi chuẩn.
- `FR-MDM-003-R02` — Cơ chế phân công và phê duyệt thay đổi đối với thuộc tính dữ liệu nhạy cảm như mã thuế, tài khoản ngân hàng, điều kiện thanh toán và các manual override thuộc diện policy cho phép.
- `FR-MDM-003-R03` — Group-level Product Moving Average Cost được hệ thống tính/cập nhật theo BR-INV-001 là system-derived value, không phải manual master-data change và không tự kích hoạt approval workflow của requirement này.
- `FR-MDM-003-R04` — Nếu một manual override đối với cost được phê duyệt ở capability/policy tương lai, override đó phải đi qua controlled sensitive-change rule riêng.
- `FR-MDM-003-R05` — Business approval thuộc requirement này sử dụng Shared Workflow để giữ quyết định và lịch sử.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-MDM-003.01` | Bản ghi hết hiệu lực không được dùng cho giao dịch mới; lịch sử vẫn được bảo toàn. |
| `FR-MDM-003.02` | Thay đổi nhạy cảm thuộc diện manual/controlled change xác định được chủ sở hữu, người đề nghị, người phê duyệt, ngày hiệu lực, quyết định và bằng chứng. |
| `FR-MDM-003.03` | Không áp dụng chính thức thay đổi thuộc diện phải phê duyệt khi workflow chưa đạt trạng thái cho phép. |
| `FR-MDM-003.04` | System-derived Product Moving Average Cost từ BR-INV-001 không bị xử lý như manual sensitive-master update chỉ vì giá trị thay đổi sau eligible inbound transaction. |

**Ghi chú traceability**

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

**Ý định chức năng**

Quản lý Product Master như business entity thuộc Product domain và là Group Master dùng chung cho các Company trong cùng Group. Product Master bao gồm vật tư sản xuất, vật tư tiêu hao, bán thành phẩm, thành phẩm, sản phẩm vừa sản xuất vừa bán, hàng hóa thương mại, combo, quà tặng và các item vận hành phù hợp. Trong P1, `item_type=COMBO` và `item_type=GIFT` được phép dùng để phân loại Product Master; composition, component consumption, stock treatment, revenue allocation và gift semantics chỉ áp dụng khi BR-PRD-004 nằm trong phạm vi đã phê duyệt. Product Master không phải đối tượng thiết kế kỹ thuật của PLM. Quản lý các thuộc tính vận hành của Product Master gồm mã, tên, mô tả, nhóm, quy cách, đơn vị tính, trọng lượng, kích thước, trạng thái kinh doanh và các thuộc tính cần thiết cho mua hàng, tồn kho, bán hàng, giao nhận hoặc sản xuất. Product Master giữ Group-level Product Moving Average Cost như một system-derived operational cost attribute được BR-INV-001 tính lại sau eligible inbound transaction; thuộc tính này không phải Company override và không tự tạo configurable Inventory Valuation Policy. Quản lý phạm vi sử dụng Product Master theo Company, kênh bán, thị trường và nhóm phân loại giá khi áp dụng. Product Master vẫn là Group Master; việc hạn chế hoặc mở phạm vi sử dụng cho Company/kênh không thay đổi ownership của Product Master. Nhóm phân loại giá chỉ là thuộc tính phân nhóm để Pricing & Promotion tham chiếu, không tự tạo mức giá hoặc chính sách giá. Quản lý trạng thái vận hành của Product Master từ nháp, đang hoạt động/kinh doanh, tạm ngừng đến ngừng sử dụng. Đây là trạng thái sử dụng trong ERP, không phải Product Development Lifecycle của PLM. Quản lý mã vạch, mã QR, mã đối tác và mã thay thế của sản phẩm.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-PRD-001-R01` — Quản lý Product Master như business entity thuộc Product domain và là Group Master dùng chung cho các Company trong cùng Group.
- `FR-PRD-001-R02` — Product Master bao gồm vật tư sản xuất, vật tư tiêu hao, bán thành phẩm, thành phẩm, sản phẩm vừa sản xuất vừa bán, hàng hóa thương mại, combo, quà tặng và các item vận hành phù hợp.
- `FR-PRD-001-R03` — Trong P1, `item_type=COMBO` và `item_type=GIFT` được phép dùng để phân loại Product Master; composition, component consumption, stock treatment, revenue allocation và gift semantics chỉ áp dụng khi BR-PRD-004 nằm trong phạm vi đã phê duyệt.
- `FR-PRD-001-R04` — Product Master không phải đối tượng thiết kế kỹ thuật của PLM.
- `FR-PRD-001-R05` — Quản lý các thuộc tính vận hành của Product Master gồm mã, tên, mô tả, nhóm, quy cách, đơn vị tính, trọng lượng, kích thước, trạng thái kinh doanh và các thuộc tính cần thiết cho mua hàng, tồn kho, bán hàng, giao nhận hoặc sản xuất.
- `FR-PRD-001-R06` — Product Master giữ Group-level Product Moving Average Cost như một system-derived operational cost attribute được BR-INV-001 tính lại sau eligible inbound transaction; thuộc tính này không phải Company override và không tự tạo configurable Inventory Valuation Policy.
- `FR-PRD-001-R07` — Quản lý phạm vi sử dụng Product Master theo Company, kênh bán, thị trường và nhóm phân loại giá khi áp dụng.
- `FR-PRD-001-R08` — Product Master vẫn là Group Master; việc hạn chế hoặc mở phạm vi sử dụng cho Company/kênh không thay đổi ownership của Product Master.
- `FR-PRD-001-R09` — Nhóm phân loại giá chỉ là thuộc tính phân nhóm để Pricing & Promotion tham chiếu, không tự tạo mức giá hoặc chính sách giá.
- `FR-PRD-001-R10` — Quản lý trạng thái vận hành của Product Master từ nháp, đang hoạt động/kinh doanh, tạm ngừng đến ngừng sử dụng.
- `FR-PRD-001-R11` — Đây là trạng thái sử dụng trong ERP, không phải Product Development Lifecycle của PLM.
- `FR-PRD-001-R12` — Quản lý mã vạch, mã QR, mã đối tác và mã thay thế của sản phẩm.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
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
| `FR-PRD-001.12` | P1 cho phép phân loại Product là COMBO/GIFT nhưng không tạo acceptance obligation về composition, component consumption, stock/revenue treatment trước khi BR-PRD-004 nằm trong phạm vi đã phê duyệt. |
| `FR-PRD-001.13` | Product Moving Average Cost xác định được Group, giá trị hiện hành, thời điểm cập nhật và nguồn inbound transaction gần nhất đã làm thay đổi giá trị; Company không tạo field override cạnh tranh. |

**Ghi chú traceability**

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

**Ý định chức năng**

Hỗ trợ nhiều đơn vị tính và quy đổi có kiểm soát cho mua, tồn, sản xuất và bán. Mỗi Product có đúng một base UOM trong một thời điểm hiệu lực. Conversion phải khai báo rõ from-UOM, to-UOM, chiều quy đổi, hệ số dương và thời gian hiệu lực; các effective period cạnh tranh cho cùng conversion context không được overlap. Precision/rounding của quantity tuân thủ NFR-DAT-001 và không làm mất source quantity trước bước rounding được công bố.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-PRD-002-R01` — Hỗ trợ nhiều đơn vị tính và quy đổi có kiểm soát cho mua, tồn, sản xuất và bán.
- `FR-PRD-002-R02` — Mỗi Product có đúng một base UOM trong một thời điểm hiệu lực.
- `FR-PRD-002-R03` — Conversion phải khai báo rõ from-UOM, to-UOM, chiều quy đổi, hệ số dương và thời gian hiệu lực; các effective period cạnh tranh cho cùng conversion context không được overlap.
- `FR-PRD-002-R04` — Precision/rounding của quantity tuân thủ NFR-DAT-001 và không làm mất source quantity trước bước rounding được công bố.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-PRD-002.01` | Mỗi Product có đúng một base UOM đang có hiệu lực tại một thời điểm. |
| `FR-PRD-002.02` | Mỗi conversion xác định được from-UOM, to-UOM, chiều, hệ số > 0 và thời gian hiệu lực. |
| `FR-PRD-002.03` | Không tồn tại hai conversion record đang cùng hiệu lực và cạnh tranh cho cùng Product/from-UOM/to-UOM/conversion context. |
| `FR-PRD-002.04` | Quy đổi bảo toàn source quantity và sử dụng precision/rounding theo NFR-DAT-001; kết quả có thể truy lại source quantity, conversion factor và converted quantity. |
| `FR-PRD-002.05` | Conversion hết hiệu lực không được dùng cho giao dịch mới nhưng giao dịch lịch sử vẫn giữ conversion context đã áp dụng. |

**Ghi chú traceability**

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

**Ý định chức năng**

Quản lý Product Default Selling Price thuộc Product domain ở Group level để làm giá cơ sở cuối cùng cho giao dịch bán khi không có Company hoặc Group Pricing Policy/Price List hợp lệ áp dụng. Mỗi default price xác định currency. Các Company trong cùng Group sử dụng giá mặc định này theo phạm vi giao dịch; không tồn tại Company default-price field override mặc định. Khác biệt giá theo Company được quản lý bằng Company Pricing Policy theo BR-PRC-001. Product không bắt buộc phải được đưa vào một bảng giá hoặc Pricing Policy riêng. BR-MDM-002 cung cấp governance/ownership semantics liên quan nhưng không phải Hard Prerequisite để Product Default Selling Price tồn tại.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-PRD-005-R01` — Quản lý Product Default Selling Price thuộc Product domain ở Group level để làm giá cơ sở cuối cùng cho giao dịch bán khi không có Company hoặc Group Pricing Policy/Price List hợp lệ áp dụng.
- `FR-PRD-005-R02` — Mỗi default price xác định currency.
- `FR-PRD-005-R03` — Các Company trong cùng Group sử dụng giá mặc định này theo phạm vi giao dịch; không tồn tại Company default-price field override mặc định.
- `FR-PRD-005-R04` — Khác biệt giá theo Company được quản lý bằng Company Pricing Policy theo BR-PRC-001.
- `FR-PRD-005-R05` — Product không bắt buộc phải được đưa vào một bảng giá hoặc Pricing Policy riêng.
- `FR-PRD-005-R06` — BR-MDM-002 cung cấp governance/ownership semantics liên quan nhưng không phải Hard Prerequisite để Product Default Selling Price tồn tại.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-PRD-005.01` | Product Master chưa thuộc Pricing Policy có hiệu lực vẫn xác định được giá bán cơ sở từ Product Default Selling Price của Group. |
| `FR-PRD-005.02` | Khi có Company hoặc Group Pricing Policy hợp lệ áp dụng, giá theo policy được ưu tiên theo precedence của BR-PRC-001 và nguồn giá cuối cùng có thể truy vết. |
| `FR-PRD-005.03` | Product Default Selling Price xác định được Group, currency, thời gian hiệu lực và lịch sử thay đổi. |
| `FR-PRD-005.04` | Không tạo Company-specific default-price field override; khác biệt giá theo Company được biểu diễn bằng Company Pricing Policy. |

**Ghi chú traceability**

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

**Ý định chức năng**

Quản lý Customer như business entity thuộc Customer domain và là Group Master dùng chung cho các Company trong cùng Group. Customer có thể là khách lẻ, đại lý, doanh nghiệp hoặc nhà phân phối và có thông tin liên hệ, địa chỉ, mã số thuế, nhóm khách hàng và trạng thái phù hợp. Quản lý thông tin khách hàng theo danh mục, nhóm, địa chỉ giao dịch, nhiều đầu mối liên hệ, trạng thái và vòng đời từ tiềm năng đến ngừng giao dịch.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-CUS-001-R01` — Quản lý Customer như business entity thuộc Customer domain và là Group Master dùng chung cho các Company trong cùng Group.
- `FR-CUS-001-R02` — Customer có thể là khách lẻ, đại lý, doanh nghiệp hoặc nhà phân phối và có thông tin liên hệ, địa chỉ, mã số thuế, nhóm khách hàng và trạng thái phù hợp.
- `FR-CUS-001-R03` — Quản lý thông tin khách hàng theo danh mục, nhóm, địa chỉ giao dịch, nhiều đầu mối liên hệ, trạng thái và vòng đời từ tiềm năng đến ngừng giao dịch.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-CUS-001.01` | Mỗi Customer có Group sở hữu, mã duy nhất trong phạm vi áp dụng và chủ sở hữu nghiệp vụ. |
| `FR-CUS-001.02` | Các Company trong Group có thể sử dụng cùng Customer theo quyền được cấp mà không phải tạo Customer master cạnh tranh. |
| `FR-CUS-001.03` | Mỗi khách hàng có hồ sơ thống nhất, trạng thái hiện tại, lịch sử trạng thái và các địa chỉ/đầu mối còn hiệu lực. |

**Ghi chú traceability**

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

**Ý định chức năng**

Pricing Policy/Price List được quản lý ở Group hoặc Company theo điều kiện, currency và thời gian hiệu lực. Company không sửa trực tiếp Group Pricing Policy mà tạo Company Pricing Policy riêng; policy chỉ cần khai báo các trường hợp khác với Product Default Selling Price. Canonical price resolution áp dụng precedence: Company Pricing Policy hợp lệ > Group Pricing Policy hợp lệ > Product Default Selling Price. Pricing Policy có thể tồn tại mà chưa tham chiếu Customer cụ thể; Customer-specific condition chỉ trở thành applicable khi BR-CUS-001/customer context tương ứng có trong scope. Shared Workflow không phải Hard Prerequisite của Pricing; khi approval rule/policy yêu cầu business approval, lifecycle của Pricing Policy phải tham chiếu BR-CTL-004 và chỉ có hiệu lực sau trạng thái workflow cho phép. Price source và transaction giữ currency; khi currency conversion áp dụng phải dùng exchange-rate context theo BR-FIN-005 và giữ original value/currency, FX rate/source/thời gian hiệu lực và converted value.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-PRC-001-R01` — Pricing Policy/Price List được quản lý ở Group hoặc Company theo điều kiện, currency và thời gian hiệu lực.
- `FR-PRC-001-R02` — Company không sửa trực tiếp Group Pricing Policy mà tạo Company Pricing Policy riêng; policy chỉ cần khai báo các trường hợp khác với Product Default Selling Price.
- `FR-PRC-001-R03` — Canonical price resolution áp dụng precedence: Company Pricing Policy hợp lệ > Group Pricing Policy hợp lệ > Product Default Selling Price.
- `FR-PRC-001-R04` — Pricing Policy có thể tồn tại mà chưa tham chiếu Customer cụ thể; Customer-specific condition chỉ trở thành applicable khi BR-CUS-001/customer context tương ứng có trong scope.
- `FR-PRC-001-R05` — Shared Workflow không phải Hard Prerequisite của Pricing; khi approval rule/policy yêu cầu business approval, lifecycle của Pricing Policy phải tham chiếu BR-CTL-004 và chỉ có hiệu lực sau trạng thái workflow cho phép.
- `FR-PRC-001-R06` — Price source và transaction giữ currency; khi currency conversion áp dụng phải dùng exchange-rate context theo BR-FIN-005 và giữ original value/currency, FX rate/source/thời gian hiệu lực và converted value.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
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
| `FR-PRC-001.10` | Khi currency conversion được áp dụng, giao dịch giữ được original price/value, original currency, FX rate/source/thời gian hiệu lực và converted value theo BR-FIN-005; conversion không tạo một Pricing Policy cạnh tranh. |
| `FR-PRC-001.11` | Pricing Policy không yêu cầu Customer cụ thể để tồn tại; customer-specific rule chỉ được evaluate khi customer context tương ứng tồn tại. |
| `FR-PRC-001.12` | Khi approval rule yêu cầu Shared Workflow, Pricing Policy không có hiệu lực trước khi workflow đạt trạng thái cho phép; khi approval rule không yêu cầu, không tạo workflow giả chỉ để thỏa capability. |

**Ghi chú traceability**

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

**Ý định chức năng**

Supplier là Group Master thuộc Supplier domain; Purchase chỉ consume Supplier trong sourcing, Purchase Order, receiving và supplier evaluation, không tạo Supplier master cạnh tranh. Kiểm soát thay đổi tài khoản ngân hàng, mã số thuế và điều khoản thanh toán của nhà cung cấp như sensitive master-data change. Thay đổi thuộc phạm vi này phải tuân thủ cơ chế phê duyệt tại BR-MDM-003 và đi qua Shared Workflow trước khi có hiệu lực.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-SUP-001-R01` — Supplier là Group Master thuộc Supplier domain; Purchase chỉ consume Supplier trong sourcing, Purchase Order, receiving và supplier evaluation, không tạo Supplier master cạnh tranh.
- `FR-SUP-001-R02` — Kiểm soát thay đổi tài khoản ngân hàng, mã số thuế và điều khoản thanh toán của nhà cung cấp như sensitive master-data change.
- `FR-SUP-001-R03` — Thay đổi thuộc phạm vi này phải tuân thủ cơ chế phê duyệt tại BR-MDM-003 và đi qua Shared Workflow trước khi có hiệu lực.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-SUP-001.01` | Mỗi Supplier có Group sở hữu, immutable internal identity, business code/RD Code theo policy áp dụng và trạng thái hợp tác. |
| `FR-SUP-001.02` | Các Company trong Group có thể sử dụng cùng Supplier theo quyền được cấp mà không phải tạo Supplier master cạnh tranh. |
| `FR-SUP-001.03` | Purchase sử dụng Supplier Master canonical và không trở thành owning domain của Supplier. |
| `FR-SUP-001.04` | Supplier ngừng hợp tác không phát sinh cam kết mới. |
| `FR-SUP-001.05` | Thay đổi nhạy cảm xác định được người đề nghị, người xác minh/phê duyệt, quyết định, ngày hiệu lực và lịch sử trước/sau. |
| `FR-SUP-001.06` | Thay đổi thuộc diện phê duyệt không có hiệu lực khi Shared Workflow chưa đạt trạng thái cho phép. |

**Ghi chú traceability**

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

**Ý định chức năng**

Ghi nhận Purchase Request cho vật tư, hàng hóa, dịch vụ và chi phí với lý do, đơn vị yêu cầu và thời điểm cần. Purchase Request có thể được tạo trước khi chọn Supplier. Mỗi purchase line được phân loại `Stock Item`, `Non-stock Item`, `Service` hoặc `Expense`; Product Master chỉ bắt buộc khi policy của loại dòng yêu cầu tham chiếu Product và không bắt buộc cho mọi Service/Expense line. Thu thập và so sánh báo giá theo giá, chất lượng, thời hạn, điều kiện giao và điều khoản thanh toán khi policy/threshold/category mua hàng yêu cầu RFQ hoặc quotation comparison. Trường hợp policy cho phép direct purchase không bắt buộc phải tạo bước quotation comparison.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-PUR-001-R01` — Ghi nhận Purchase Request cho vật tư, hàng hóa, dịch vụ và chi phí với lý do, đơn vị yêu cầu và thời điểm cần.
- `FR-PUR-001-R02` — Purchase Request có thể được tạo trước khi chọn Supplier.
- `FR-PUR-001-R03` — Mỗi purchase line được phân loại `Stock Item`, `Non-stock Item`, `Service` hoặc `Expense`; Product Master chỉ bắt buộc khi policy của loại dòng yêu cầu tham chiếu Product và không bắt buộc cho mọi Service/Expense line.
- `FR-PUR-001-R04` — Thu thập và so sánh báo giá theo giá, chất lượng, thời hạn, điều kiện giao và điều khoản thanh toán khi policy/threshold/category mua hàng yêu cầu RFQ hoặc quotation comparison.
- `FR-PUR-001-R05` — Trường hợp policy cho phép direct purchase không bắt buộc phải tạo bước quotation comparison.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-PUR-001.01` | Mỗi nhu cầu xác định được người yêu cầu, Company/đơn vị chịu chi phí, thời điểm cần, loại purchase line và trạng thái quyết định. |
| `FR-PUR-001.02` | Purchase Request có thể được ghi nhận khi chưa chọn Supplier; Supplier được bổ sung trước khi hình thành cam kết mua khi nghiệp vụ yêu cầu. |
| `FR-PUR-001.03` | Service/Expense line có thể được ghi nhận không cần Product Master; line thuộc loại/policy yêu cầu Product phải tham chiếu Product Master hợp lệ. |
| `FR-PUR-001.04` | Khi policy yêu cầu quotation comparison, quyết định lựa chọn Supplier có báo giá/căn cứ so sánh và người chịu trách nhiệm. |
| `FR-PUR-001.05` | Khi policy cho phép direct purchase, nghiệp vụ có thể tiếp tục mà không tạo RFQ/quotation giả chỉ để thỏa luồng. |

**Ghi chú traceability**

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

**Ý định chức năng**

Quản lý cam kết mua theo Purchase Order, Supplier, loại purchase line, số lượng hoặc giá trị phù hợp với loại dòng, giá, thuế và điều kiện giao hàng/thực hiện. Purchase Order có thể được tạo theo direct-purchase policy hoặc từ quotation comparison khi policy yêu cầu. Budget không thuộc phạm vi requirement này; kiểm soát ngân sách chỉ được bổ sung khi có Budget Domain hoặc Integration Requirement riêng được phê duyệt.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-PUR-002-R01` — Quản lý cam kết mua theo Purchase Order, Supplier, loại purchase line, số lượng hoặc giá trị phù hợp với loại dòng, giá, thuế và điều kiện giao hàng/thực hiện.
- `FR-PUR-002-R02` — Purchase Order có thể được tạo theo direct-purchase policy hoặc từ quotation comparison khi policy yêu cầu.
- `FR-PUR-002-R03` — Budget không thuộc phạm vi requirement này; kiểm soát ngân sách chỉ được bổ sung khi có Budget Domain hoặc Integration Requirement riêng được phê duyệt.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-PUR-002.01` | Cam kết mua đối chiếu được với Purchase Request, Supplier, loại purchase line, số lượng/giá trị, giá, thuế và điều kiện giao hàng/thực hiện đã xác nhận. |
| `FR-PUR-002.02` | Báo giá/lựa chọn Supplier được liên kết khi policy yêu cầu; direct PO được nhận diện và chỉ được dùng khi policy cho phép. |

**Ghi chú traceability**

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

**Ý định chức năng**

Kiểm soát việc nhận hàng theo Purchase Order/cam kết mua, số lượng thực nhận, partial/over/under receiving, basic quality/status và chênh lệch so với cam kết. Lot/serial chỉ bắt buộc khi policy/capability quản lý lot/serial tương ứng nằm trong phạm vi đã phê duyệt. Chi phí phát sinh/landed cost chi tiết chỉ tạo acceptance obligation khi capability tương ứng nằm trong phạm vi đã phê duyệt. Purchase Receipt hợp lệ là một nguồn eligible inbound transaction để BR-INV-001 cập nhật Group-level Product Moving Average Cost.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-PUR-003-R01` — Kiểm soát việc nhận hàng theo Purchase Order/cam kết mua, số lượng thực nhận, partial/over/under receiving, basic quality/status và chênh lệch so với cam kết.
- `FR-PUR-003-R02` — Lot/serial chỉ bắt buộc khi policy/capability quản lý lot/serial tương ứng nằm trong phạm vi đã phê duyệt.
- `FR-PUR-003-R03` — Chi phí phát sinh/landed cost chi tiết chỉ tạo acceptance obligation khi capability tương ứng nằm trong phạm vi đã phê duyệt.
- `FR-PUR-003-R04` — Purchase Receipt hợp lệ là một nguồn eligible inbound transaction để BR-INV-001 cập nhật Group-level Product Moving Average Cost.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-PUR-003.01` | Mỗi receipt line đối chiếu được với Purchase Order/cam kết mua, Product khi applicable, quantity ordered, quantity previously received, quantity received lần này và cumulative received quantity. |
| `FR-PUR-003.02` | Partial receiving được hỗ trợ; over/under receiving được nhận diện và xử lý theo policy/threshold có hiệu lực. |
| `FR-PUR-003.03` | Basic quality/status của hàng nhận được ghi nhận đủ để quyết định usable/non-usable inventory trong P1. |
| `FR-PUR-003.04` | Chênh lệch được phân loại, có chủ sở hữu và quyết định xử lý. |
| `FR-PUR-003.05` | Lot/serial hoặc landed-cost detail chỉ là acceptance obligation khi capability/policy tương ứng nằm trong phạm vi đã phê duyệt; capability chưa approved không buộc phải tạo dữ liệu giả. |
| `FR-PUR-003.06` | Purchase Receipt được ghi nhận chính thức tạo source reference để BR-INV-001 tính lại Product Moving Average Cost; receipt bị hủy/điều chỉnh phải giữ traceability tới tác động cost tương ứng. |

**Ghi chú traceability**

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

**Ý định chức năng**

Quản lý canonical inventory quantity theo Company, Branch/Plant/Warehouse, location, Product và basic inventory status. Giữ hàng cho Sales Order hoặc mục đích được phê duyệt; reservation phải tách khỏi usable on-hand. P1 có thể lưu lot, serial, expiry và quality-related attributes/basic status khi dữ liệu có sẵn nhưng không yêu cầu end-to-end lot/serial/expiry traceability trước khi BR-INV-004 và BR-PRD-003 nằm trong phạm vi đã phê duyệt. P1 duy trì Group-level Product Moving Average Cost như system-derived operational cost baseline: sau mỗi eligible Purchase Receipt được ghi nhận chính thức và, khi Manufacturing nằm trong phạm vi đã phê duyệt, mỗi eligible Production Finished-Goods Receipt, hệ thống tính lại quantity-weighted moving average và cập nhật Product Moving Average Cost ở Group level cùng source/effective timestamp. Moving Average Cost P1 không phải configurable Inventory Valuation Policy và không tạo Company cost override; configurable inventory valuation thuộc BR-INV-005 khi capability đó nằm trong phạm vi đã phê duyệt.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-INV-001-R01` — Quản lý canonical inventory quantity theo Company, Branch/Plant/Warehouse, location, Product và basic inventory status.
- `FR-INV-001-R02` — Giữ hàng cho Sales Order hoặc mục đích được phê duyệt; reservation phải tách khỏi usable on-hand.
- `FR-INV-001-R03` — P1 có thể lưu lot, serial, expiry và quality-related attributes/basic status khi dữ liệu có sẵn nhưng không yêu cầu end-to-end lot/serial/expiry traceability trước khi BR-INV-004 và BR-PRD-003 nằm trong phạm vi đã phê duyệt.
- `FR-INV-001-R04` — P1 duy trì Group-level Product Moving Average Cost như system-derived operational cost baseline: sau mỗi eligible Purchase Receipt được ghi nhận chính thức và, khi Manufacturing nằm trong phạm vi đã phê duyệt, mỗi eligible Production Finished-Goods Receipt, hệ thống tính lại quantity-weighted moving average và cập nhật Product Moving Average Cost ở Group level cùng source/effective timestamp.
- `FR-INV-001-R05` — Moving Average Cost P1 không phải configurable Inventory Valuation Policy và không tạo Company cost override; configurable inventory valuation thuộc BR-INV-005 khi capability đó nằm trong phạm vi đã phê duyệt.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-INV-001.01` | Canonical inventory quantity theo từng chiều P1 cộng đúng lên tổng Company scope; P1 không yêu cầu canonical inventory valuation ledger trước khi BR-INV-005 nằm trong phạm vi đã phê duyệt. |
| `FR-INV-001.02` | Có thể biết usable on-hand, reserved quantity, non-usable/quarantine quantity và in-transit quantity khi trạng thái tương ứng áp dụng. |
| `FR-INV-001.03` | Hàng ở trạng thái non-usable/quarantine không được cấp phát cho mục đích thông thường. |
| `FR-INV-001.04` | Lot/serial/expiry có thể được lưu như optional/basic attributes trong P1 nhưng không tạo acceptance obligation về end-to-end genealogy/traceability trước khi capability tương ứng nằm trong phạm vi đã phê duyệt. |
| `FR-INV-001.05` | Mỗi Product thuộc phạm vi costing xác định được một Product Moving Average Cost hiện hành ở Group level, thời điểm cập nhật và inbound transaction làm thay đổi gần nhất. |
| `FR-INV-001.06` | Sau eligible Purchase Receipt được ghi nhận chính thức, Moving Average Cost được tính lại theo quantity-weighted moving-average rule trên eligible quantity/value đã chuẩn hóa về UOM/currency costing context. |
| `FR-INV-001.07` | Khi Manufacturing capability nằm trong phạm vi đã phê duyệt, eligible Production Finished-Goods Receipt tham gia cùng Group-level Moving Average Cost rule; khi Manufacturing chưa nằm trong scope, không tạo production receipt giả để thỏa requirement. |
| `FR-INV-001.08` | Company không tạo Moving Average Cost override cạnh tranh; P1 Moving Average Cost không được diễn giải là configurable Inventory Valuation Policy của BR-INV-005. |

**Ghi chú traceability**

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

**Ý định chức năng**

Ghi nhận các inventory movement thuộc source capability đang trong phạm vi đã phê duyệt, gồm nhập hàng, xuất bán và các movement P1 khác; movement từ Production, Warranty/Returns hoặc capability khác chỉ trở thành acceptance obligation khi source capability tương ứng được approved. Quản lý chuyển kho nội bộ và phân biệt hàng đang vận chuyển với hàng đã nhận. Movement giữa Branch/Plant/Warehouse thuộc cùng một Company là intra-company inventory transfer và không được ghi nhận như intercompany transaction. Movement giữa hai Company khác nhau không thuộc scope của transfer nội bộ; trong P1 phải bị chặn như unsupported cross-company movement. Khi BR-INV-006 nằm trong phạm vi đã phê duyệt, cross-company movement được route theo intercompany capability đó.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-INV-002-R01` — Ghi nhận các inventory movement thuộc source capability đang trong phạm vi đã phê duyệt, gồm nhập hàng, xuất bán và các movement P1 khác; movement từ Production, Warranty/Returns hoặc capability khác chỉ trở thành acceptance obligation khi source capability tương ứng được approved.
- `FR-INV-002-R02` — Quản lý chuyển kho nội bộ và phân biệt hàng đang vận chuyển với hàng đã nhận.
- `FR-INV-002-R03` — Movement giữa Branch/Plant/Warehouse thuộc cùng một Company là intra-company inventory transfer và không được ghi nhận như intercompany transaction.
- `FR-INV-002-R04` — Movement giữa hai Company khác nhau không thuộc scope của transfer nội bộ; trong P1 phải bị chặn như unsupported cross-company movement.
- `FR-INV-002-R05` — Khi BR-INV-006 nằm trong phạm vi đã phê duyệt, cross-company movement được route theo intercompany capability đó.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-INV-002.01` | Mỗi biến động thuộc acceptance scope có chứng từ nguồn, người chịu trách nhiệm và lý do. |
| `FR-INV-002.02` | Kho nguồn, kho đích, số lượng và trạng thái chuyển được đối chiếu. |
| `FR-INV-002.03` | Transfer giữa các Organization Context thuộc cùng một Company giữ nguyên Company sở hữu và không tự tạo intercompany transaction. |
| `FR-INV-002.04` | Trong P1, attempt movement giữa hai Company khác nhau bị từ chối và không được ngụy trang thành chuyển kho/chi nhánh nội bộ. |
| `FR-INV-002.05` | Khi BR-INV-006 nằm trong phạm vi đã phê duyệt, cross-company movement được route theo intercompany flow tương ứng; BR-INV-006 không phải runtime prerequisite của P1. |
| `FR-INV-002.06` | Movement class của capability chưa nằm trong phạm vi đã phê duyệt không buộc phải được tạo hoặc giả lập chỉ để thỏa BR-INV-002. |

**Ghi chú traceability**

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

**Ý định chức năng**

Kiểm kê định kỳ hoặc theo rủi ro, ghi nhận chênh lệch và phê duyệt điều chỉnh. Kiểm soát tồn âm và điều chỉnh hồi tố theo policy P1. Control đối với inventory movement sau financial-period close chỉ trở thành acceptance obligation khi BR-FIN-006/financial closing capability nằm trong phạm vi đã phê duyệt; BR-ORG-004 không được diễn giải là owner của financial period.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-INV-003-R01` — Kiểm kê định kỳ hoặc theo rủi ro, ghi nhận chênh lệch và phê duyệt điều chỉnh.
- `FR-INV-003-R02` — Kiểm soát tồn âm và điều chỉnh hồi tố theo policy P1.
- `FR-INV-003-R03` — Control đối với inventory movement sau financial-period close chỉ trở thành acceptance obligation khi BR-FIN-006/financial closing capability nằm trong phạm vi đã phê duyệt; BR-ORG-004 không được diễn giải là owner của financial period.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-INV-003.01` | Chênh lệch có nguyên nhân, người đếm, người kiểm tra và tác động quantity; tác động cost/value được giải thích theo Product Moving Average Cost P1 hoặc valuation capability đang có hiệu lực. |
| `FR-INV-003.02` | Ngoại lệ bị chặn hoặc được phê duyệt, có chứng từ điều chỉnh và lý do. |
| `FR-INV-003.03` | Khi financial closing chưa nằm trong phạm vi đã phê duyệt, P1 không phải tạo closed financial period giả để test inventory adjustment. |
| `FR-INV-003.04` | Khi BR-FIN-006 nằm trong phạm vi đã phê duyệt, movement/adjustment sau period close tuân thủ close/reopen/retrospective-adjustment policy do Finance quản lý. |

**Ghi chú traceability**

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

**Ý định chức năng**

Tiếp nhận/capture Sales Order và Quotation với source/channel attribution như website, sàn, điểm bán, điện thoại, nhân viên kinh doanh, đại lý hoặc nhà phân phối. Trong P1, source/channel có thể được ghi nhận thủ công hoặc từ capability đang có; việc liệt kê website/sàn không tạo Hard Prerequisite cho external integration. Quản lý báo giá, điều kiện bán, thời hạn hiệu lực, base price và các giá trị discount/tax cần lưu trên chứng từ. Giá cơ sở phải được xác định bằng canonical price-resolution rule tại BR-PRC-001. P1 không mặc nhiên cung cấp Promotion/Discount Policy Engine hoặc operational Tax Engine; khi BR-PRC-002 hoặc BR-FIN-004 nằm trong phạm vi đã phê duyệt, discount/tax calculation áp dụng rule của capability tương ứng.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-SAL-001-R01` — Tiếp nhận/capture Sales Order và Quotation với source/channel attribution như website, sàn, điểm bán, điện thoại, nhân viên kinh doanh, đại lý hoặc nhà phân phối.
- `FR-SAL-001-R02` — Trong P1, source/channel có thể được ghi nhận thủ công hoặc từ capability đang có; việc liệt kê website/sàn không tạo Hard Prerequisite cho external integration.
- `FR-SAL-001-R03` — Quản lý báo giá, điều kiện bán, thời hạn hiệu lực, base price và các giá trị discount/tax cần lưu trên chứng từ.
- `FR-SAL-001-R04` — Giá cơ sở phải được xác định bằng canonical price-resolution rule tại BR-PRC-001.
- `FR-SAL-001-R05` — P1 không mặc nhiên cung cấp Promotion/Discount Policy Engine hoặc operational Tax Engine; khi BR-PRC-002 hoặc BR-FIN-004 nằm trong phạm vi đã phê duyệt, discount/tax calculation áp dụng rule của capability tương ứng.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-SAL-001.01` | Đơn hàng có source/channel, mã tham chiếu, Customer hoặc thông tin khách lẻ phù hợp. |
| `FR-SAL-001.02` | P1 có thể capture source/channel mà không yêu cầu external integration; khi integration capability chưa nằm trong phạm vi đã phê duyệt, source/channel có thể được nhập theo controlled business process. |
| `FR-SAL-001.03` | Giá cơ sở của báo giá truy được nguồn theo BR-PRC-001 và điều kiện được giải thích theo chính sách áp dụng tại thời điểm xác nhận. |
| `FR-SAL-001.04` | Discount/tax value được lưu cùng chứng từ khi áp dụng, nhưng việc tự tính theo promotion/tax policy chỉ là acceptance obligation khi BR-PRC-002/BR-FIN-004 tương ứng nằm trong phạm vi đã phê duyệt. |

**Ghi chú traceability**

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

**Ý định chức năng**

Kiểm tra khả năng đáp ứng và fulfillment commitment dựa trên inventory hiện có. P1 Available-to-Promise (ATP) được xác định bằng usable on-hand trừ reservation hiện hành trong đúng Company/inventory scope. Incoming Purchase Order, planned Production, forecast hoặc future supply khác không làm tăng ATP P1. Hệ thống vẫn có thể ghi nhận expected delivery date/commitment và phần chưa đáp ứng nhưng future supply chỉ được dùng trong ATP khi capability/phương pháp tương lai được approved.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-SAL-002-R01` — Kiểm tra khả năng đáp ứng và fulfillment commitment dựa trên inventory hiện có.
- `FR-SAL-002-R02` — P1 Available-to-Promise (ATP) được xác định bằng usable on-hand trừ reservation hiện hành trong đúng Company/inventory scope.
- `FR-SAL-002-R03` — Incoming Purchase Order, planned Production, forecast hoặc future supply khác không làm tăng ATP P1.
- `FR-SAL-002-R04` — Hệ thống vẫn có thể ghi nhận expected delivery date/commitment và phần chưa đáp ứng nhưng future supply chỉ được dùng trong ATP khi capability/phương pháp tương lai được approved.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-SAL-002.01` | ATP P1 = usable on-hand - reservation trong đúng Company/inventory scope và không bao gồm future supply. |
| `FR-SAL-002.02` | Nhân viên biết rõ số có thể cam kết, số đã giữ và phần chưa đáp ứng. |
| `FR-SAL-002.03` | Incoming PO, planned Production hoặc forecast không làm tăng ATP P1 chỉ vì tồn tại trong hệ thống. |
| `FR-SAL-002.04` | Expected delivery date/commitment được lưu tách biệt với ATP quantity và không làm thay đổi usable on-hand hoặc reservation. |

**Ghi chú traceability**

- Upstream: `BR-SAL-002` (source line ~1681).
- Planned verification artifact from UR baseline: `UAT-SAL-002`.

### 4.18 Vận hành tài chính

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

**Ý định chức năng**

Quản lý nhiều đồng tiền và canonical Exchange Rate Master cho currency pair, rate type, rate value, source, thời gian hiệu lực và precedence khi nhiều rate có thể áp dụng. Mọi conversion phải giữ original amount/currency, exchange-rate reference, converted amount/currency và effective context. Missing-rate behavior phải deterministic: giao dịch yêu cầu conversion không được ghi nhận chính thức nếu không resolve được một rate hợp lệ theo precedence/policy đang có hiệu lực, trừ khi ngoại lệ được kiểm soát rule riêng được phê duyệt. P1 requirement này cung cấp exchange-rate/conversion baseline; realized/unrealized FX accounting revaluation không tự động thuộc P1 nếu chưa có Finance capability riêng được approved.

**Các quy tắc chức năng suy ra từ nguồn**

- `FR-FIN-005-R01` — Quản lý nhiều đồng tiền và canonical Exchange Rate Master cho currency pair, rate type, rate value, source, thời gian hiệu lực và precedence khi nhiều rate có thể áp dụng.
- `FR-FIN-005-R02` — Mọi conversion phải giữ original amount/currency, exchange-rate reference, converted amount/currency và effective context.
- `FR-FIN-005-R03` — Missing-rate behavior phải deterministic: giao dịch yêu cầu conversion không được ghi nhận chính thức nếu không resolve được một rate hợp lệ theo precedence/policy đang có hiệu lực, trừ khi ngoại lệ được kiểm soát rule riêng được phê duyệt.
- `FR-FIN-005-R04` — P1 requirement này cung cấp exchange-rate/conversion baseline; realized/unrealized FX accounting revaluation không tự động thuộc P1 nếu chưa có Finance capability riêng được approved.

**Các child requirement hướng acceptance**

| Child FR | Nghĩa vụ suy ra từ success criterion upstream |
|---|---|
| `FR-FIN-005.01` | Mỗi exchange-rate record xác định được from currency, to currency, rate type, rate value, source, effective-from/thời gian hiệu lực, lifecycle/status và owner. |
| `FR-FIN-005.02` | Khi có nhiều rate cùng có thể áp dụng, hệ thống resolve một rate deterministic theo controlled precedence/policy và lưu được rate record đã sử dụng. |
| `FR-FIN-005.03` | Số tiền gốc, original currency, tỷ giá/rate source áp dụng, converted amount, target currency và thời gian hiệu lực đều truy xuất được. |
| `FR-FIN-005.04` | Giao dịch cần conversion nhưng không resolve được rate hợp lệ bị chặn khỏi official posting hoặc đi qua ngoại lệ được kiểm soát rule nếu rule đó được phê duyệt; không tự suy diễn rate. |
| `FR-FIN-005.05` | Exchange-rate lịch sử không bị ghi đè làm mất rate từng có hiệu lực. |
| `FR-FIN-005.06` | P1 Exchange Rate Master không mặc nhiên tạo accounting requirement về realized/unrealized FX revaluation ngoài capability được approved. |

**Ghi chú traceability**

- Upstream: `BR-FIN-005` (source line ~1863).
- Planned verification artifact from UR baseline: `UAT-FIN-005`.

## 5. Nghĩa vụ companion P1 không được mở rộng trong FRS này

> **Informative routing mirror.** Nội dung AR/NFR trong Section 5 được giữ để bảo toàn P1 scope, routing và review context; nó không tạo normative duplicate. Canonical requirement semantics vẫn thuộc upstream `REQ-ERP-001`, còn controlled realization/verification thuộc `DES-*`/`DES-REVIEW-*` và `NFS-*`/`NFR-TEST-*` tương ứng. Nếu mirror này khác controlled source, controlled source theo authority contract tại Section 1.2 phải được dùng và discrepancy phải được reconcile.

### 5.1 Architectural Rules

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
  - Mỗi business transaction thuộc đúng một Company sở hữu; Group chỉ được suy ra qua Company và không sở hữu transaction trực tiếp.
  - Cross-company process có thể phối hợp nhiều transaction thuộc các Company khác nhau nhưng từng transaction thành phần vẫn thuộc đúng một Company sở hữu.
  - `intercompany_reference` chỉ là correlation/reference key giữa các transaction hai phía; không phải transaction owner.
  - Mỗi business transaction xác định được business/effective timestamp, lifecycle state và actor chịu trách nhiệm/User hoặc system context phù hợp.

### 5.2 Non-functional Requirements

#### NFR-SEC-001 — Bảo vệ dữ liệu khỏi truy cập, sửa đổi hoặc xuất trái thẩm quyền

- Planned NFS: `NFS-SEC-001`.
- Planned verification: `NFR-TEST-SEC-001`.
- Acceptance baseline:
  - Mẫu kiểm tra chéo phạm vi trên các capability/surface thuộc phạm vi đã phê duyệt không truy cập được dữ liệu ngoài quyền.
  - Mọi màn hình, API/data access, report, document hoặc export surface đang nằm trong phạm vi đã phê duyệt áp dụng cùng Resource/Action Permission và Authorization cuối cùng Evaluation tương ứng.
  - Report/document/export capability chưa nằm trong phạm vi đã phê duyệt không buộc phải được triển khai giả chỉ để test NFR-SEC-001; khi capability được approved, security test tương ứng trở thành acceptance obligation.

#### NFR-AVL-001 — Đảm bảo availability trong khung giờ vận hành

- Planned NFS: `NFS-AVL-001`.
- Planned verification: `NFR-TEST-AVL-001`.
- Acceptance baseline:
  - Monthly availability trong configured operating window đã công bố đạt tối thiểu 99.5%.
  - Measurement window, timezone, thời điểm bắt đầu/kết thúc downtime và các khoảng planned maintenance được ghi nhận đủ để tái tính availability.
  - Planned maintenance chỉ được loại khỏi phép đo khi đã được phê duyệt và thông báo trước tối thiểu 48 giờ theo policy; downtime không đáp ứng cả hai điều kiện này được tính vào availability.
  - Mỗi Company/service scope thuộc acceptance xác định được operating window có hiệu lực dùng cho phép đo; thay đổi operating window có version/thời gian hiệu lực và không hồi tố làm thay đổi kết quả đã công bố.

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
  - Không có bước xử lý ngoại lệ hợp lệ nào làm mất audit lịch sử đã phát sinh trước đó.
  - Từ mẫu giao dịch và, khi capability tương ứng nằm trong phạm vi đã phê duyệt, mẫu report/decision reference có thể truy ra chuỗi audit evidence và tái lập được ngữ cảnh gồm thời điểm, phạm vi và actor liên quan.
  - Evidence vẫn queryable trong toàn bộ retention baseline/schedule áp dụng và không phụ thuộc vào việc bản ghi nghiệp vụ còn ở trạng thái hiện hành.
  - Capability/report/decision surface chưa nằm trong phạm vi đã phê duyệt không buộc phải được tạo giả chỉ để tạo audit evidence cho NFR này.

#### NFR-DAT-001 — Bảo toàn precision và rounding của dữ liệu định lượng

- Planned NFS: `NFS-DAT-001`.
- Planned verification: `NFR-TEST-DAT-001`.
- Acceptance baseline:
  - Quantity hỗ trợ tối thiểu 6 decimal places khi product/UOM policy yêu cầu và không làm mất giá trị nguồn trước bước rounding được công bố.
  - Unit price, exchange rate và rate khác giữ đủ internal precision để kết quả posting sau cùng đáp ứng currency/financial rounding policy.
  - Monetary posting được làm tròn theo minor unit của currency áp dụng; số tiền gốc và giá trị trước/sau rounding có thể giải thích khi cần đối soát.
  - Khi tax capability nằm trong phạm vi đã phê duyệt, tax calculation tuân thủ effective tax-rounding policy do Finance quản lý tại BR-FIN-004 và có thể truy vết policy áp dụng.
  - Khi Payroll hoặc capability chịu quy tắc pháp lý chuyên biệt nằm trong phạm vi đã phê duyệt, số liệu tương ứng tuân theo policy chuyên biệt đang có hiệu lực; capability chưa approved không tạo acceptance obligation cho P1.
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

## 6. Đặc tả chức năng chi tiết Wave A

### 6.1 Hợp đồng đặc tả chi tiết

Wave A tuân theo thứ tự đặc tả đã được phê duyệt trong Phase Planning: Kiến trúc & Tổ chức, bao phủ ba Architectural Rule của P1 cùng với các capability Organization, Employee và IAM nền tảng. Phần này tinh chỉnh các parent `FR-*` hiện có mà không tạo capability nghiệp vụ mới.

Các quy ước sử dụng bên dưới:

- **Có căn cứ nguồn** nghĩa là rule được hỗ trợ trực tiếp bởi `REQ-ERP-001 v1.0.2` hoặc bởi một architectural invariant P1 được requirement đó tham chiếu.
- **Thao tác suy ra** nghĩa là thao tác CRUD/lifecycle là biểu hiện vận hành cần thiết của capability nguồn, nhưng User Requirements không quy định UI, API, screen flow hoặc implementation kỹ thuật.
- **TBD** đánh dấu một quyết định downstream mà baseline nguồn chưa định nghĩa. TBD không phải requirement ngầm và implementation không được tự âm thầm quyết định.
- Không tự đặt tên role của actor. Khi User Requirements không nêu một business role cụ thể, đặc tả này sử dụng **User được ủy quyền** và yêu cầu quyết định Authorization cuối cùng theo Resource/Action phải tuân theo `FR-IAM-003`/`FR-IAM-005` khi áp dụng.
- FRS này không định nghĩa database schema, endpoint path, UI layout, storage technology, authentication protocol, password/MFA policy, masking algorithm hoặc catalog permission code cụ thể, trừ khi nguồn upstream yêu cầu rõ.

### 6.2 Các invariant kiến trúc Wave A

Các invariant sau áp dụng cho mọi FR chi tiết trong Wave A và vẫn là nghĩa vụ thiết kế thay vì Functional Requirement:

| Invariant | Hệ quả đối với Wave A |
|---|---|
| `AR-DOM-001` | Mỗi business concept/entity được quản trị chỉ có một canonical owning domain; consumer domain có thể tham chiếu nhưng không được tạo master/SSOT cạnh tranh. |
| `AR-TXN-001` | Mỗi Business Transaction thuộc đúng một Company sở hữu; Group được suy ra thông qua Company và không bao giờ trở thành transaction owner. |
| `AR-DATA-OWNERSHIP-001` | Reference/common data phải được phân loại ownership theo System/Group/Company trước khi sử dụng; mọi ngoại lệ phải có lý do owning-domain rõ ràng. |

Chi tiết architecture realization vẫn được dự kiến trong `DES-AR-DOM-001`, `DES-AR-TXN-001` và `DES-AR-DATA-OWNERSHIP-001`.

### 6.3 Tổ chức & Cấu trúc doanh nghiệp

#### 6.3.1 FR-ORG-001 — Đặc tả chi tiết: Quản lý nhiều Group và Company

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | UR không nêu một role cụ thể. Mọi thao tác tạo/thay đổi hướng tới người dùng được thực hiện bởi User được ủy quyền; mã Resource/Action cụ thể còn TBD trong authorization catalog. |
| Trigger | Các thao tác suy ra: thiết lập Group Business Entity, liên kết canonical Group Organization Context, thiết lập Company membership dưới một Group, hoặc truy xuất cấu trúc Group/Company. |
| Hard prerequisite | Không có ở cấp business requirement. |
| Các invariant chi phối | `AR-DOM-001`, `AR-TXN-001`. |
| Các business object chính | Group Business Entity, Company, Organization Context `type=GROUP`, Business Transaction ownership context. |
| Chi tiết effective-time/state | Chưa được định nghĩa đầy đủ bởi `BR-ORG-001`; lifecycle của Company/Organization Context được chi tiết trong `FR-ORG-002`. Không bổ sung Group lifecycle state tại đây. |

**Đầu vào và context**

Thông tin tối thiểu có căn cứ nguồn cần để hiện thực capability này gồm: canonical Group identity; Group Business Entity; Organization Context `type=GROUP` tương ứng; quan hệ Company-to-Group; và khi đánh giá transaction ownership thì phải có Company sở hữu. Bộ field, định dạng code và cơ chế sinh identifier cụ thể còn TBD cho thiết kế downstream.

**Quy tắc xử lý và validation**

1. Platform phải hỗ trợ nhiều hơn một Group độc lập.
2. Mỗi Group phải có đúng một canonical Group Business Entity nắm giữ business/administrative identity của Group.
3. Mỗi Group Business Entity phải được biểu diễn trong organizational hierarchy/scope bằng đúng một Organization Context `type=GROUP`.
4. Group Organization Context không được trở thành Group master thứ hai và không được nắm giữ canonical business identity cạnh tranh.
5. Việc tạo Organization Context `type=GROUP` mà không liên kết Group Business Entity phải bị từ chối.
6. Việc liên kết nhiều hơn một Group Business Entity vào cùng Group Organization Context phải bị từ chối.
7. Business Transaction ownership phải được gán cho đúng một Company. Việc gán Group làm owner trực tiếp của Business Transaction phải bị từ chối.
8. Khi một transaction cần Group context, Group phải được suy ra thông qua Company sở hữu transaction đó.
9. Phải bảo toàn cách ly dữ liệu cross-Group; tổng hợp ở cấp Group có thể tổng hợp dữ liệu các Company nhưng vẫn phải giữ Company ownership của từng transaction.

**Đầu ra và bằng chứng được lưu giữ**

Capability phải cho phép resolve: canonical Group identity, canonical Group Organization Context, các Company thuộc Group, và Company sở hữu của từng Business Transaction. Khi tạo dữ liệu tổng hợp ở cấp Group, Company ownership của các transaction bên dưới vẫn phải nhìn thấy/resolve được. Cách trình bày aggregation/report cụ thể không được FR này quy định.

**Nghĩa vụ Authorization và audit**

Nguồn yêu cầu quản trị Group độc lập và không trộn dữ liệu cross-Group. Các administrative Permission code cụ thể còn TBD. Mọi truy cập hướng tới người dùng về sau phải được đánh giá theo IAM rule dùng chung; FR này không tạo đường Authorization thay thế. Phạm vi audit cho thay đổi business/data chịu sự quản trị của capability Central Audit P1 khi change class tương ứng được cấu hình để audit; taxonomy audit event cụ thể còn TBD trong `FR-AUD-001`/design.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-ORG-001-01` | Group A và Group B cùng tồn tại trên một platform | User truy cập dữ liệu trong scope Group A | Dữ liệu Group B không bị trộn vào scope Group A | `FR-ORG-001.01` |
| `SCN-ORG-001-02` | Một Group Business Entity tồn tại | Resolve Group Organization Context của nó | Đúng một context `type=GROUP` đại diện Group đó và context này không phải Group master cạnh tranh | `.02`, `.03`, `.06` |
| `SCN-ORG-001-03` | Không có Group Business Entity được liên kết | Có yêu cầu tạo Organization Context `type=GROUP` | Thao tác bị từ chối | `.04` |
| `SCN-ORG-001-04` | Một context `type=GROUP` đã được gắn với một Group Business Entity | Có yêu cầu liên kết Group Business Entity thứ hai vào cùng context | Thao tác bị từ chối | `.05` |
| `SCN-ORG-001-05` | Một Business Transaction được tạo | Ownership được gán | Đúng một Company sở hữu transaction; Group được suy ra từ Company đó và không thể là owner trực tiếp | `.07` |
| `SCN-ORG-001-06` | Nhiều Company thuộc cùng một Group và sở hữu transaction | Yêu cầu tổng hợp ở cấp Group | Có thể tổng hợp mà không thay đổi hoặc làm mất Company ownership của các transaction bên dưới | `.08` |

**TBD / các quyết định downstream**

- Định dạng code/numbering của Group và Company.
- Vocabulary lifecycle/state của Group chưa được nêu trong `BR-ORG-001`.
- Identifier của administrative Resource/Action Permission.
- Cơ chế cách ly dữ liệu vật lý và realization database/storage.

#### 6.3.2 FR-ORG-002 — Đặc tả chi tiết: Quản lý Organization Context và hierarchy vận hành

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | User được ủy quyền; UR không nêu tên role cụ thể. |
| Trigger | Các thao tác suy ra: tạo/thay đổi/deactivate Organization Context, thay đổi quan hệ parent-child, resolve Company ownership, hoặc render organizational view thống nhất. |
| Hard prerequisite | `FR-ORG-001`. |
| Các invariant chi phối | `AR-DOM-001`, `AR-TXN-001`. |
| Reference được kiểm soát | `ORG-CONTEXT-HIERARCHY-POLICY`. |
| Các loại Organization Context được phép | Chỉ `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`. |

**Đầu vào và context**

Đối với mỗi Organization Context, nguồn yêu cầu type, Company sở hữu/liên quan khi áp dụng, parent relation khi áp dụng, lifecycle/state và thời gian hiệu lực. Context `GROUP` bổ sung việc resolve đúng một Group Business Entity. Hierarchy policy tối thiểu phải cung cấp owner, version, thời gian hiệu lực và các tổ hợp parent-child được phép/bị cấm. Schema cụ thể và toàn bộ policy matrix không được UR định nghĩa.

**Quy tắc xử lý và validation**

1. Business configuration không được thêm Organization Context type ngoài tập cố định gồm năm type.
2. Context `GROUP` phải tham chiếu đúng một canonical Group Business Entity và không được trở thành Group master.
3. Mỗi Branch, Plant và Warehouse phải resolve tới đúng một Company sở hữu.
4. Quan hệ parent-child phải được validate theo `ORG-CONTEXT-HIERARCHY-POLICY` đang có hiệu lực.
5. Hierarchy có thể bỏ qua cấp trung gian khi policy cho phép; FRS không được hard-code một chuỗi bắt buộc thay thế.
6. Tổ hợp parent-child không được policy hiện hành cho phép phải bị từ chối, trừ khi chính policy đó định nghĩa controlled exception.
7. Mỗi Business Transaction vẫn thuộc Company sở hữu bất kể Organization Context được sử dụng trong vận hành.
8. Lifecycle/state và thời gian hiệu lực của Company, Branch, Plant và Warehouse phải được lưu giữ. Context inactive/không còn hiệu lực không được sử dụng cho hoạt động mới, trong khi dữ liệu lịch sử vẫn truy vấn được.
9. Department và Position vẫn là master của Employee domain, không phải Organization Context.
10. Organizational view có thể hiển thị Department, Position và Employee bên cạnh Organization Context bằng cách resolve dữ liệu Employee Assignment đang có hiệu lực.
11. Việc hiển thị Department hoặc Position trong organizational view không được tạo Permission, Organization Scope hoặc transaction ownership.
12. Một Position có thể xuất hiện trong nhiều tổ hợp Department/Organization Context thông qua các Employee Assignment đang có hiệu lực.

**Hành vi lifecycle/state**

Nguồn yêu cầu lifecycle/state và thời gian hiệu lực nhưng không định nghĩa canonical state enumeration cho Company/Branch/Plant/Warehouse. Thiết kế downstream phải định nghĩa một controlled state model mà không mâu thuẫn với invariant rõ ràng rằng context không còn active/effective không được phát sinh dữ liệu mới và lịch sử sử dụng vẫn truy vấn được.

**Ngoại lệ và ranh giới**

- Ngoại lệ hierarchy do policy định nghĩa chỉ được phép nếu tồn tại trong `ORG-CONTEXT-HIERARCHY-POLICY` đang có hiệu lực; FRS này không tạo free-form implementation bypass.
- Hierarchy/lifecycle của Department và Position vẫn nằm ngoài ownership của Organization.
- Việc di chuyển hoặc hiển thị context trong hierarchy không tạo cross-company transaction ownership.

**Nghĩa vụ Authorization và audit**

Permission cụ thể cho quản trị hierarchy còn TBD. Quyền truy cập dữ liệu Organization của User vẫn chịu common IAM evaluation. Lịch sử lifecycle/effective-time và version của controlled policy phải có khả năng được bảo toàn; mapping Central Audit event cụ thể vẫn là công việc downstream.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-ORG-002-01` | Business configuration sẵn có | User cố gắng thêm Organization Context type mới | Type bị từ chối nếu không thuộc GROUP/COMPANY/BRANCH/PLANT/WAREHOUSE | `.01`, `.02` |
| `SCN-ORG-002-02` | Một Branch/Plant/Warehouse được tạo | Resolve ownership của nó | Xác định được đúng một Company owner | `.04` |
| `SCN-ORG-002-03` | Hierarchy policy đang hiệu lực cho phép Company -> Warehouse | Warehouse được đặt trực tiếp dưới Company | Quan hệ được chấp nhận ngay cả khi không có cấp Branch/Plant trung gian | `.05`, `.06`, `.15` |
| `SCN-ORG-002-04` | Hierarchy policy đang hiệu lực cấm một tổ hợp parent-child và không có ngoại lệ | Quan hệ được submit | Quan hệ bị từ chối | `.15`, `.16`, `.17` |
| `SCN-ORG-002-05` | Organization Context không còn hiệu lực | Một hoạt động nghiệp vụ mới cố gắng sử dụng context đó | Việc sử dụng mới bị từ chối trong khi record lịch sử vẫn truy vấn được | `.09` |
| `SCN-ORG-002-06` | Các Employee Assignment đang hiệu lực tham chiếu Department và Position | Organizational view được render | Department/Position/Employee có thể xuất hiện, nhưng không tạo Organization Scope, Permission hoặc ownership mới | `.10`-`.14` |
| `SCN-ORG-002-07` | Một transaction sử dụng context Branch/Plant/Warehouse | Kiểm tra transaction ownership | Company sở hữu không thay đổi và vẫn được xác định duy nhất | `.07`, `.08` |
| `SCN-ORG-002-08` | Một canonical Group Business Entity tồn tại | Organizational representation của nó được tạo hoặc resolve | Đúng một Organization Context `type=GROUP` tham chiếu canonical Group Business Entity đó và không tạo Group master cạnh tranh | `.03` |

**Routing bằng chứng verification**

| Child FR | Loại bằng chứng | Nghĩa vụ bằng chứng |
|---|---|---|
| `FR-ORG-002.18` | `DES-REVIEW` | Review xác nhận implementation FRS/DES sử dụng `ORG-CONTEXT-HIERARCHY-POLICY` đang có hiệu lực và không hard-code một nguồn hierarchy rule cạnh tranh. |

**TBD / các quyết định downstream**

- Toàn bộ matrix `ORG-CONTEXT-HIERARCHY-POLICY` và cấu trúc controlled exception.
- Vocabulary lifecycle/state và transition table cho Company/Branch/Plant/Warehouse.
- Administrative screen/API và permission code.

#### 6.3.3 FR-ORG-004 — Đặc tả chi tiết: Business timezone, currency và Company Business Calendar

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | User được ủy quyền; nguồn không nêu role quản trị tổ chức cụ thể. |
| Trigger | Các thao tác suy ra: thiết lập/thay đổi Company timezone/currency/calendar setting có hiệu lực; resolve business timestamp/date/currency/calendar cho transaction hoặc deadline. |
| Hard prerequisite | `FR-ORG-002`. |
| Các reference không-hard | `BR-SYS-001`, `BR-FIN-006`. |
| Chủ sở hữu canonical | Organization/Foundation đối với Company Business Calendar và Company business timezone/currency context. |

**Đầu vào và context**

Context có căn cứ nguồn gồm Company; business timezone; business/base/functional currency; Company Business Calendar; phân loại business day/non-business day; Company holiday; thời gian hiệu lực; và business cutoff/deadline cần được tính theo business day. Việc phân biệt cụ thể giữa business/base/functional currency khi cấu hình nhiều giá trị, code list, biểu diễn calendar recurrence và UI là quyết định downstream.

**Quy tắc xử lý và validation**

1. Transaction phải resolve Company business timezone và business/base/functional currency đang có hiệu lực tại business time của transaction.
2. Locale/display configuration không được làm thay đổi canonical business timezone/date/currency hoặc monetary value đã ghi nhận trước đó.
3. Mỗi Company phải có một Business Calendar đang hiệu lực có thể xác định được và có khả năng phân loại business day, non-business day và Company holiday.
4. Mọi deadline/cutoff được định nghĩa theo business day phải resolve thông qua canonical Company Business Calendar.
5. Employee Work Calendar trong phạm vi HR tương lai có thể consume và mở rộng Company calendar, nhưng không được tạo định nghĩa Company-business-day cạnh tranh.
6. Financial period lifecycle, close/reopen và periodic allocation nằm ngoài P1 ownership của FR này. Chúng chỉ trở thành nghĩa vụ khi `BR-FIN-006` nằm trong phạm vi đã phê duyệt.

**Hành vi state/effective-time**

Thay đổi business timezone, currency hoặc calendar setting phải nhận biết effective-time vì nguồn yêu cầu dùng policy áp dụng tại thời điểm transaction. Nguồn không định nghĩa rule cho retroactive change, overlap hoặc approval workflow của các setting này; các nội dung đó vẫn là TBD và không được tự tạo thành P1 acceptance obligation.

**Đầu ra và context được lưu giữ**

Transaction hoặc rule tiêu thụ capability này có thể resolve canonical business timestamp/date, Company currency context liên quan và phân loại business-day từ Company policy/calendar đang có hiệu lực. Business meaning lịch sử của transaction phải giữ ổn định khi locale/display setting thay đổi về sau.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-ORG-004-01` | Transaction thuộc một Company | Business context của transaction được resolve | Xác định được Company business timezone và currency policy đang có hiệu lực | `.01` |
| `SCN-ORG-004-02` | Một transaction đã được ghi nhận | User locale/display được thay đổi | Canonical business timestamp/date, business currency và monetary value không thay đổi | `.02` |
| `SCN-ORG-004-03` | Company Business Calendar đang có hiệu lực | Một date được evaluate | Date có thể được phân loại là business day/non-business day/Company holiday khi áp dụng | `.03` |
| `SCN-ORG-004-04` | Deadline được định nghĩa bằng business day | Tính deadline | Company Business Calendar đang có hiệu lực là canonical source | `.04` |
| `SCN-ORG-004-05` | Capability HR work-calendar tương lai hiện diện | Áp dụng extension calendar riêng của HR | Extension không định nghĩa lại Company Business Calendar bên ngoài scope HR | `.05` |
| `SCN-ORG-004-06` | `BR-FIN-006` không nằm trong P1 scope | Thực thi P1 acceptance | FR này không yêu cầu lifecycle financial-period close/reopen | `.06` |

**TBD / các quyết định downstream**

- Rule về overlap/retroactivity cho effective-dating của timezone/currency/calendar setting.
- Phân biệt semantic cụ thể và tổ hợp được phép của các field business/base/functional currency.
- Calendar storage model và cú pháp biểu diễn cutoff.

### 6.4 Nhân sự & Cấu trúc tổ chức

#### 6.4.1 FR-EMP-002 — Đặc tả chi tiết: Department, Position và organizational view

`FR-EMP-002` được đặc tả trước `FR-EMP-001` trong Wave A vì `FR-EMP-001` khai báo nó là Hard Prerequisite.

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | User được ủy quyền; UR không nêu role HR/tổ chức cụ thể. |
| Trigger | Các thao tác suy ra: thiết lập/thay đổi/deactivate Department hoặc Position, hoặc resolve chúng cho Employee Assignment/organizational view. |
| Hard prerequisite | `FR-ORG-002`. |
| Canonical ownership | Department = Company Master trong Employee domain; Position = Group Master trong Employee domain. |

**Đầu vào và context**

Department yêu cầu Company sở hữu, optional parent khi áp dụng hierarchy, lifecycle/state và thời gian hiệu lực. Position yêu cầu Group sở hữu, title/level, status và thời gian hiệu lực. UR không cung cấp code cụ thể, level taxonomy, hierarchy depth hoặc state vocabulary.

**Quy tắc xử lý và validation**

1. Department và Position phải được Employee domain quản lý, không phải Organization Context.
2. Một Department phải thuộc đúng một Company.
3. Department hierarchy, khi được sử dụng, phải lưu parent relation, lifecycle/state và thời gian hiệu lực.
4. Department không còn hiệu lực không được chọn cho Employee Assignment mới, trong khi Assignment lịch sử vẫn được giữ nguyên.
5. Position phải thuộc một Group và cung cấp title/level, status và thời gian hiệu lực.
6. Employee Assignment chỉ được tham chiếu Department khi Department thuộc Company của Assignment và có hiệu lực tại thời điểm liên quan.
7. Employee Assignment chỉ được tham chiếu Position khi Position thuộc Group tương ứng và có hiệu lực tại thời điểm liên quan.
8. Department và Position không được tự tạo Permission hoặc Organization Scope độc lập.

**Đầu ra và bằng chứng được lưu giữ**

Consumer có thể resolve canonical Department và Position, owner scope và effective status của chúng. Tham chiếu lịch sử từ Assignment vẫn phải resolve được sau khi Department/Position inactive hoặc bị supersede.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-EMP-002-01` | Một Department tồn tại | Đọc metadata của Department | Xác định được Company sở hữu, parent khi áp dụng, lifecycle/state và thời gian hiệu lực | `.01` |
| `SCN-EMP-002-02` | Department không còn hiệu lực | Assignment mới cố gắng tham chiếu Department đó | Tham chiếu mới bị từ chối; các Assignment lịch sử vẫn giữ nguyên | `.02` |
| `SCN-EMP-002-03` | Một Position tồn tại | Đọc metadata của Position | Xác định được Group sở hữu, title/level, status và thời gian hiệu lực | `.03` |
| `SCN-EMP-002-04` | Company/Group context của Assignment đã được resolve | Chọn Department hoặc Position | Department phải khớp Company, Position phải khớp Group tương ứng và cả hai phải đang có hiệu lực | `.04` |
| `SCN-EMP-002-05` | Department/Position tồn tại | Evaluate Authorization scope | Chỉ sự tồn tại của chúng không tạo Permission hoặc Organization Scope | `.05` |

**TBD / các quyết định downstream**

- Vocabulary lifecycle state và transition control của Department và Position.
- Policy về hierarchy depth/cycle validation của Department; nguồn chỉ quy định Department có hierarchy.
- Position level taxonomy và code format.

#### 6.4.2 FR-EMP-001 — Đặc tả chi tiết: Employee và Employee Assignment

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | User được ủy quyền; UR không nêu role HR/employee-master cụ thể. |
| Trigger | Các thao tác suy ra: thiết lập/cập nhật Employee, tạo hoặc thay đổi Employee Assignment, hoặc resolve Assignment đang có hiệu lực cho các consumer như IAM. |
| Hard prerequisites | `FR-ORG-002`, `FR-EMP-002`. |
| Canonical ownership | Employee = Group Master; Employee Assignment = Company Master; cả hai thuộc Employee domain. |
| Ranh giới cross-domain | IAM và HRP tương lai tham chiếu/mở rộng canonical record và không được tạo Employee/Assignment master cạnh tranh. |

**Đầu vào và context**

Employee phải resolve Group sở hữu. Employee Assignment phải resolve đúng một Company sở hữu cùng với Organization Context, Department, Position và thời gian hiệu lực. Nguồn không định nghĩa employee-code format, personal-data schema, assignment type, FTE, manager relation hoặc vocabulary assignment lifecycle state; các nội dung đó không được đưa thêm vào đây.

**Quy tắc xử lý và validation**

1. Employee identity độc lập với User Identity.
2. Một canonical Employee có thể được nhiều Company trong cùng Group tham chiếu, tùy theo Authorization.
3. Một Employee có thể có nhiều Assignment theo Company và thời gian trong khi vẫn giữ một canonical Employee identity.
4. Mỗi Assignment phải thuộc đúng một Company.
5. Organization Context của Assignment phải resolve trong context của Company sở hữu.
6. Department của Assignment phải thuộc cùng Company và đang có hiệu lực.
7. Position của Assignment phải thuộc Group tương ứng và đang có hiệu lực.
8. Lịch sử effective-time của Assignment phải được bảo toàn khi Organization Context, Department, Position hoặc effective period thay đổi.
9. Consumer như IAM/HRP phải có khả năng xác định canonical Employee và Assignment mà chúng tham chiếu; không được thay thế bằng parallel master.

**Ranh giới overlap và concurrency**

UR cho phép rõ ràng một Employee có nhiều Assignment theo Company và thời gian, nhưng không định nghĩa việc có cho phép các Assignment overlap trong cùng Company hay không, Assignment nào là primary, hoặc conflict được resolve thế nào. Các rule này còn TBD và phải được chốt trước implementation/UAT nếu liên quan đến việc resolve Authorization của P1.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-EMP-001-01` | Một Employee tồn tại trong Group | Một Company được phép trong Group đó tham chiếu Employee | Sử dụng cùng canonical Employee, không tạo bản sao Employee theo Company | `.01`, `.03` |
| `SCN-EMP-001-02` | Một Employee Assignment mới được tạo | Assignment được validate | Resolve được đúng một Company, một Organization Context, Department, Position và thời gian hiệu lực | `.02` |
| `SCN-EMP-001-03` | Organization/Department/Position của Assignment thay đổi theo thời gian | Truy vấn lịch sử | Các giá trị/effective period trước đó vẫn được bảo toàn | `.04` |
| `SCN-EMP-001-04` | IAM hoặc HRP tham chiếu dữ liệu Employee | Resolve canonical reference | Xác định được Employee/Assignment do Employee domain sở hữu và không tạo master cạnh tranh | `.05` |

**TBD / các quyết định downstream**

- Rule cho Assignment overlap/nhiều Assignment đồng thời và cách chọn primary Assignment.
- Vocabulary lifecycle-state của Employee/Assignment ngoài lịch sử effective-time.
- Các field dữ liệu cá nhân chi tiết của Employee và phân loại dữ liệu.

### 6.5 Phân quyền & Bảo mật

#### 6.5.1 FR-IAM-001 — Đặc tả chi tiết: User Identity và Group Access Membership

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Việc quản trị Identity/IAM được thực hiện bởi User được ủy quyền; role admin/permission code cụ thể chưa được nguồn nêu. Runtime subject là một User Identity. |
| Trigger | Các thao tác suy ra: tạo/thay đổi/lock/deactivate User Identity; grant/revoke Group Access Membership; resolve Group/Company/Organization Context trước Authorization. |
| Hard prerequisites | `FR-ORG-001`, `FR-EMP-001`. |
| Ranh giới canonical | User Identity là authentication principal ở platform level và độc lập với Employee/Employee Assignment master. |

**Đầu vào và context**

User Identity yêu cầu unique identity, identity type, trạng thái access/authentication và lifecycle. Group membership của ordinary ERP User yêu cầu target Group và canonical Employee thuộc chính Group đó. System Admin/service identity ngoại lệ không có Employee yêu cầu identity type, scope, owner và căn cứ ngoại lệ có thể audit. Nguồn không định nghĩa credential, password/MFA rule, authentication protocol, session duration hoặc SSO mechanism.

**Quy tắc xử lý và validation**

1. Mỗi User Identity phải unique ở platform level và có identity type, status và effective lifecycle.
2. User bị lock hoặc inactive không thể thực hiện transaction mới.
3. User Identity không được hard-reference một Employee Assignment và không được trở thành Company membership.
4. IAM không được tạo Employee, Department, Position hoặc Employee Assignment master.
5. Ordinary ERP User muốn truy cập một Group phải có explicit Group Access Membership tham chiếu canonical Employee thuộc Group đó.
6. Membership tại Group A không được tạo permission hoặc data scope tại Group B.
7. Cross-Group access yêu cầu explicit membership/scope cho từng Group liên quan.
8. Khi cần Company/Organization Context, hệ thống resolve effective Employee Assignment(s) của canonical Employee trong membership tại thời điểm evaluate; membership không pin cứng một Assignment.
9. Permission/Policy evaluation phải sử dụng Group và Company/Organization Context đã resolve.
10. System Admin/service identity không có Employee chỉ được phép như controlled exception với identity type, scope, owner và audit evidence tường minh.
11. Cross-Group data isolation vẫn áp dụng ngay cả khi một platform identity có membership trong nhiều Group; từng Group context phải được resolve tường minh.

**Hành vi trạng thái**

Nguồn phân biệt rõ trạng thái locked/inactive là trạng thái ngăn hoạt động transaction mới nhưng không định nghĩa đầy đủ User Identity state machine, authentication status enum, membership state model hoặc thời điểm revocation. Đây là các quyết định downstream.

**Đầu ra và bằng chứng**

Runtime resolution phải có khả năng cung cấp: User Identity; Group membership đã chọn/được authorize hoặc exceptional scope; canonical Employee khi áp dụng; effective Employee Assignment(s) dùng để resolve Company/context; và bằng chứng đủ cho việc giải thích Authorization về sau.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-IAM-001-01` | Một User Identity tồn tại | Kiểm tra identity metadata | Unique ID, identity type, status và lifecycle đều có sẵn | `.01` |
| `SCN-IAM-001-02` | User bị locked hoặc inactive | Thực hiện action tạo transaction mới | Action không thể tiếp tục | `.02` |
| `SCN-IAM-001-03` | Ordinary ERP User yêu cầu truy cập Group A | Membership được cấp | Membership tham chiếu tường minh Group A và canonical Employee thuộc Group A, không tham chiếu cố định một Assignment | `.03`, `.05`, `.07` |
| `SCN-IAM-001-04` | User chỉ có membership tại Group A | Yêu cầu data/action của Group B | Không suy ra permission/data scope của Group B; truy cập yêu cầu explicit Group B membership/scope | `.06`, `.10`, `.11` |
| `SCN-IAM-001-05` | Authorization cần Company context | Context được resolve | Effective Assignment(s) của canonical Employee được resolve theo thời gian/context thay vì sử dụng hard-pinned Assignment | `.07`, `.08` |
| `SCN-IAM-001-06` | Service/System Admin identity không có Employee | Cấu hình exceptional identity access | Bắt buộc có identity type, explicit scope, owner và audit evidence | `.09` |

**Routing bằng chứng verification**

| Child FR | Loại bằng chứng | Nghĩa vụ bằng chứng |
|---|---|---|
| `FR-IAM-001.04` | `DES/integration review` | Review xác nhận IAM tham chiếu các record canonical Employee/Department/Position/Assignment của Employee domain và không tạo master/SSOT cạnh tranh. |

**TBD / các quyết định downstream**

- Authentication mechanism, credential và MFA/session policy.
- Complete identity và membership lifecycle state model.
- Rule chọn giữa nhiều Employee Assignment đồng thời có hiệu lực khi context không được chỉ định rõ bằng cách khác.
- Administrative Resource/Action permission code cụ thể.

#### 6.5.2 FR-IAM-002 — Đặc tả chi tiết: Permission, Profile và Business Group

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Quản trị viên IAM được ủy quyền; tên role và permission code cụ thể còn TBD. |
| Trigger | Các thao tác suy ra: định nghĩa/thay đổi Permission metadata, cấu thành Profile, duy trì Business Group, gán mapping và resolve Profile/Permission set của User. |
| Hard prerequisite | Không có. |
| Đơn vị Authorization cốt lõi | Permission = canonical pair `Resource/Action` có identifier và `policy_required`. |

**Đầu vào và context**

Thông tin có căn cứ nguồn gồm Permission identifier, Resource, Action, `policy_required`; Profile và Permission set của nó; Business Group; mapping Business Group-to-Profile; và lịch sử mapping. Resource catalog, Action catalog, naming convention, cơ chế gán Profile trực tiếp cho User hay thông qua group, và lifecycle state của mapping không được nguồn định nghĩa.

**Quy tắc xử lý và validation**

1. Mỗi Permission phải xác định một tổ hợp Resource/Action, một identifier và canonical value `policy_required`.
2. `policy_required` thuộc về chính Permission; giá trị này không được thay đổi theo Profile.
3. Profile là tập hợp các Permission đã được định nghĩa trước.
4. User nhận base rights thông qua Profile; Policy Rule không được tạo hoặc cấp Permission không tồn tại trong Profile-derived set.
5. Business Group có thể map User/business grouping tới Profile để hỗ trợ quản trị.
6. Business Group là khái niệm Authorization và không bao giờ được hiểu là Group Business Entity của Organization.
7. Business Group không thay thế Profile, Permission hoặc Policy Rule và không thể tự tạo quyền.
8. Thay đổi mapping Business Group-to-Profile phải bảo toàn lịch sử.
9. Effective user rights phải trace được về chuỗi Business Group/Profile/Permission tham gia quyết định.

**Đầu ra và bằng chứng được lưu giữ**

Capability phải resolve effective Profile set và union các Permission tương ứng cho User context, đồng thời bảo toàn canonical metadata của từng Permission. Capability phải lưu đủ lịch sử mapping để giải thích configuration trước đó. Kết quả ALLOW/DENY cuối cùng vẫn thuộc trách nhiệm của `FR-IAM-003`.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-IAM-002-01` | Một Permission được cấu hình | Đọc metadata của Permission | Resource, Action, identifier và `policy_required` đều có sẵn | `.01` |
| `SCN-IAM-002-02` | Cùng một Permission xuất hiện trong nhiều Profile | Kiểm tra `policy_required` | Trả về cùng một canonical value trong mọi Profile context | `.04` |
| `SCN-IAM-002-03` | Policy Rule chứa điều kiện ALLOW nhưng User không có Profile Permission tương ứng | Resolve base entitlement | Policy không tạo Permission mới | `.05` |
| `SCN-IAM-002-04` | Business Group được map tới Profile | Trace quyền của User | Xác định được chuỗi Business Group, Profile và Permission; Business Group không bị nhầm với Group Business Entity | `.06`-`.08` |
| `SCN-IAM-002-05` | Mapping Business Group-to-Profile thay đổi | Truy vấn lịch sử | Mapping trước đó vẫn xác định được; bản thân thay đổi không tạo Permission | `.09`, `.10` |
| `SCN-IAM-002-06` | Profile chứa một tập Permission được kiểm soát và đóng góp vào effective Profile set của User | Resolve và trace effective rights | Profile cung cấp Permission set của nó và effective rights của User trace được về Profile và Permission đóng góp | `.02`, `.03` |

**TBD / các quyết định downstream**

- Canonical Resource/Action catalog và identifier naming convention.
- User/Profile assignment model và effective-time semantics chưa được quy định rõ.
- Lifecycle/state của Profile và Business Group.

#### 6.5.3 FR-IAM-003 — Đặc tả chi tiết: Policy Based Authorization Evaluation

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Runtime subject | User Identity trong một Group đã resolve và, khi cần, Company/Organization Context đã resolve. |
| Trigger | Mọi protected Resource/Action request trong phạm vi P1. |
| Hard prerequisites | `FR-IAM-001`, `FR-IAM-002`, `FR-ORG-002`. |
| Đầu ra quyết định | `ALLOW` hoặc `DENY` cuối cùng, kèm explainability evidence. |
| Determinism | Cùng effective input/policy context phải tạo cùng một authorization decision. |

**Đầu vào bắt buộc cho evaluation**

Tối thiểu, khi áp dụng, phải có: User Identity; Group membership/exceptional scope; Company/Organization Context đã resolve; effective Employee Assignment(s); Resource và Action đích; resource type/state đích; participant relationship; actor Department được resolve từ effective Assignment; responsible Department của resource lấy từ responsibility/ownership của chính resource; canonical Permission metadata gồm `policy_required`; effective Profile Permission set; Policy version/rule đang áp dụng; và rule Effect.

**Thuật toán evaluation canonical**

1. **Resolve context.** Resolve Group và Company/Organization Context thông qua `FR-IAM-001`; permission evaluation không được giả định context từ Group khác.
2. **Resolve base entitlement.** Xây dựng effective Permission set từ các Profile active/effective. Nhiều Profile đóng góp union của Permission.
3. **Permission gate.** Nếu requested Resource/Action Permission không tồn tại, trả `DENY`. Policy processing không được tạo Permission còn thiếu.
4. **Resolve policy conditions.** Chỉ sau khi Permission gate pass mới evaluate các effective Policy Rule áp dụng, sử dụng Group/Company/Organization Context hiện tại và các điều kiện được hỗ trợ như Resource Type, State, Participant, Department/business Condition.
5. **Department semantics.** Actor Department được resolve từ effective Employee Assignment. Resource Responsible Department được đọc từ ownership/responsibility của resource. Department không bao giờ trở thành Organization Context hoặc nguồn Permission/Scope độc lập.
6. **Aggregate Effect.** Áp dụng thứ tự canonical `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`.
7. **Resolve NO_MATCH.** Nếu không có rule match, trả `DENY` khi `policy_required=true`; trả `ALLOW` khi `policy_required=false`.
8. **Action independence.** Evaluate view, create/submit, approve, official posting, adjust, export và administer như các Resource/Action Permission độc lập; action này không hàm ý action khác.
9. **Explain decision.** Lưu giữ/trả về đủ bằng chứng để xác định membership/context, Profile Permission, canonical `policy_required`, Policy version/rule áp dụng và Effect đã dùng.

**Rule lifecycle/lịch sử của Policy**

Policy phải có version, thời gian hiệu lực và lịch sử được bảo toàn. Cập nhật Policy không được ghi đè version đã từng có hiệu lực. Policy authoring workflow, syntax, condition language và approval lifecycle cụ thể không được UR này định nghĩa và vẫn là quyết định thiết kế downstream.

**Bảng quyết định canonical**

| Base Profile Permission | Aggregate Effect áp dụng | `policy_required` | Quyết định cuối cùng |
|---|---|---|---|
| Không có | Bất kỳ, kể cả ALLOW | bất kỳ | `DENY` |
| Có | `ABSOLUTE_DENY` | bất kỳ | `DENY` |
| Có | `ALLOW` và không có `ABSOLUTE_DENY` | bất kỳ | `ALLOW` |
| Có | Chỉ `DENY` | bất kỳ | `DENY` |
| Có | `NO_MATCH` | `true` | `DENY` |
| Có | `NO_MATCH` | `false` | `ALLOW` |

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-IAM-003-01` | User không có requested Profile Permission nhưng tồn tại ALLOW Policy Rule | Evaluate Authorization | Kết quả cuối cùng là `DENY` | `.08` |
| `SCN-IAM-003-02` | User có requested Permission và có `ABSOLUTE_DENY` áp dụng | Evaluate Authorization | Kết quả cuối cùng là `DENY` | `.09` |
| `SCN-IAM-003-03` | User có Permission; có ALLOW và DENY thông thường áp dụng; không có `ABSOLUTE_DENY` | Evaluate Authorization | Kết quả cuối cùng là `ALLOW` | `.10` |
| `SCN-IAM-003-04` | User có Permission và chỉ có DENY thông thường áp dụng | Evaluate Authorization | Kết quả cuối cùng là `DENY` | `.11` |
| `SCN-IAM-003-05` | User có Permission và không có Policy Rule nào match | Evaluate `policy_required` | `true` => DENY; `false` => ALLOW | `.12` |
| `SCN-IAM-003-06` | User có nhiều effective Profile | Resolve Permission set | Effective base Permission set là union của các Profile và vẫn chịu Policy | `.13` |
| `SCN-IAM-003-07` | Department condition áp dụng | Evaluate Authorization | Actor Department lấy từ effective Assignment; Resource Department lấy từ responsibility của resource; Department không tạo scope bổ sung | `.04`, `.05` |
| `SCN-IAM-003-08` | Một quyết định được trả về | Yêu cầu explainability | Có thể trace Membership/context, Profile Permission, `policy_required`, policy version/rule và Effect đã áp dụng | `.02`, `.03`, `.14`, `.18` |
| `SCN-IAM-003-09` | User được phép view một resource | User cố gắng export/approve/adjust/admin | Mỗi action riêng yêu cầu Permission và policy decision riêng; quyền view không hàm ý action khác | `.15`-`.17` |
| `SCN-IAM-003-10` | User chỉ được authorize trong một Group/Company/Organization Context đã resolve | User yêu cầu protected data/action ngoài authorized scope đó | Request bị từ chối và không expose data/action ngoài scope | `.01` |
| `SCN-IAM-003-11` | Một Policy version từng có hiệu lực, sau đó version mới có hiệu lực | Truy vấn policy context hiện tại và lịch sử Authorization | Xác định được version có hiệu lực tại từng thời điểm yêu cầu và version trước đó không bị ghi đè | `.06`, `.07` |

**TBD / các quyết định downstream**

- Policy condition DSL/data structure và authoring interface.
- Semantics cụ thể cho applicability/tie của rule trong cùng Effect class khi nhiều rule cùng match; upstream chỉ cố định precedence giữa các Effect class.
- Resource/action catalog và mapping resource-responsibility field cho từng domain.
- Caching strategy, decision-token format hoặc implementation kỹ thuật của policy engine.

#### 6.5.4 FR-IAM-005 — Đặc tả chi tiết: Bảo vệ dữ liệu nhạy cảm

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Runtime subject | User thực hiện sensitive-data action. |
| Trigger | View, modify hoặc export dữ liệu được phân loại sensitive trong P1 resource context áp dụng. |
| Hard prerequisite | `FR-IAM-003`. |
| Các ví dụ sensitive được upstream nêu rõ | Salary, identifying information, cost, discount và bank account information. |

**Quy tắc xử lý và validation**

1. Sensitive-data access là action-specific và không được dựa vào generic right đối với surrounding business object.
2. Đối với sensitive-data action được yêu cầu, Resource/Action Permission tương ứng phải tồn tại trong base entitlement của User.
3. Authorization Evaluation cuối cùng từ `FR-IAM-003` phải trả `ALLOW` trong đúng Group/Company/Organization Context.
4. Nếu Permission không tồn tại hoặc kết quả cuối cùng khác `ALLOW`, action view/modify/export đó phải bị từ chối.
5. View và export là các quyền độc lập; permission view sensitive data không cấp quyền export.
6. Policy Rule không thể thay thế hoặc tạo Permission bắt buộc.
7. FR này không đưa masking/redaction vào như cơ chế thay thế Authorization vì nguồn không quy định hành vi đó.

**Ranh giới phân loại sensitive data**

Các ví dụ upstream không phải field catalog đầy đủ. Danh sách cụ thể các sensitive Resource/Action pair và owner của catalog đó phải được định nghĩa downstream trước khi UAT coverage được coi là đầy đủ. FRS này không tự động tuyên bố field chưa liệt kê là sensitive hoặc non-sensitive.

**Đầu ra và bằng chứng**

Protected operation nhận quyết định ALLOW/DENY cuối cùng từ common Authorization. Action bị từ chối không được expose protected data qua action surface đó. Explainability tuân theo `FR-IAM-003`. Security-event monitoring cụ thể thuộc P2 (`BR-IAM-006`) và không được biến thành P1 requirement tại đây.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-IAM-005-01` | User thiếu sensitive-data Resource/Action Permission liên quan | User cố gắng view/modify/export | Action được yêu cầu bị từ chối | `.01` |
| `SCN-IAM-005-02` | User có view Permission nhưng Authorization cuối cùng không phải ALLOW | User cố gắng xem sensitive data | Quyền xem bị từ chối | `.01`, `.02` |
| `SCN-IAM-005-03` | User có view Permission và Authorization cuối cùng = ALLOW | User xem sensitive data trong context đã resolve | Quyền xem được cho phép cho action đó | `.02` |
| `SCN-IAM-005-04` | User có thể xem sensitive data nhưng không có export Permission | User cố gắng export | Export bị từ chối; view permission không được tái sử dụng như export permission | `.03` |

**TBD / các quyết định downstream**

- Controlled field/resource classification của sensitive data cho các P1 domain.
- Resource/Action ID cụ thể cho view/modify/export và cách quản trị catalog đó.
- Việc có bổ sung masking/redaction về sau như requirement được phê duyệt riêng hay không; source hiện tại không yêu cầu.

### 6.6 Register quyết định chưa chốt của Wave A

Các item dưới đây được chủ ý để mở vì baseline nguồn hiện tại chưa định nghĩa. Chúng phải được resolve trong controlled downstream artifact hoặc thông qua requirements revision đã được phê duyệt; chúng không phải permission để implementation tự đặt hành vi khi coding. Classification tuân theo Section 1.4 và bản thân classification không resolve business/design decision.

| ID | Quyết định chưa chốt | FR bị ảnh hưởng | Class | Owner | Artifact đích | Gate đến hạn | Trạng thái |
|---|---|---|---|---|---|---|---|
| `WA-TBD-001` | Hoàn thiện matrix `ORG-CONTEXT-HIERARCHY-POLICY`, exception model, biểu diễn owner/version/effective-time | `FR-ORG-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Controlled policy + FRS/DES reference | `PROVISIONAL_READY` | `OPEN` |
| `WA-TBD-002` | Lifecycle/state enumeration và allowed transition cho Company/Branch/Plant/Warehouse | `FR-ORG-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS hoặc controlled policy + DES | `PROVISIONAL_READY` | `OPEN` |
| `WA-TBD-003` | Rule overlap/retroactivity của effective-dating cho Company timezone/currency/calendar | `FR-ORG-004` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/business policy | `PROVISIONAL_READY` | `OPEN` |
| `WA-TBD-004` | Rule cycle/depth của Department hierarchy và vocabulary lifecycle của Department/Position | `FR-EMP-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/business policy | `PROVISIONAL_READY` | `OPEN` |
| `WA-TBD-005` | Cách resolve concurrent/overlapping Employee Assignment và primary Assignment | `FR-EMP-001`, `FR-IAM-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/business policy | `PROVISIONAL_READY` | `OPEN` |
| `WA-TBD-006` | Complete lifecycle của User Identity/Group Membership và authentication mechanism | `FR-IAM-001` | `DESIGN_DECISION` | Solution Architecture/Design Owner | Security design; requirements revision chỉ khi thay đổi business obligation | `BEFORE_BUILD` | `OPEN` |
| `WA-TBD-007` | Canonical Resource/Action catalog, Permission identifier và admin permission code | `FR-IAM-002`, `FR-IAM-003`, `FR-IAM-005` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS controlled catalog + DES | `PROVISIONAL_READY` | `OPEN` |
| `WA-TBD-008` | Biểu diễn/DSL của Policy Rule và conflict semantics trong cùng Effect | `FR-IAM-003` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/DES controlled policy model | `PROVISIONAL_READY` | `OPEN` |
| `WA-TBD-009` | Catalog field/resource sensitive-data của P1 | `FR-IAM-005` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Controlled security/data-classification catalog | `PROVISIONAL_READY` | `OPEN` |

### 6.7 Gate provisional implementation-readiness của Wave A

Wave A có thể chuyển từ **detailed draft** sang **provisional implementation-ready** chỉ khi:

1. Cả chín parent FR trong Organization/Employee/IAM vẫn trace về upstream BR tương ứng và mọi acceptance-oriented child FR đều có explicit verification evidence path theo Section 1.3.
2. Không còn Wave A TBD nào có `class=BUSINESS_DECISION`, `due_gate=PROVISIONAL_READY` và `status!=APPROVED`. Blocking logic được tính từ TBD register thay vì danh sách ID hard-code.
3. `DESIGN_DECISION` chỉ có thể còn open tại gate này khi owner, target artifact và due gate đã rõ; nó không được âm thầm thay đổi business obligation; và phải được đóng trước due gate riêng của nó, trước build.
4. Ba architecture invariant có corresponding controlled Design realization draft mà không thay đổi canonical ownership hoặc transaction ownership.
5. Không capability P2/P3 nào được đưa thành P1 prerequisite; cụ thể SoD/delegation review, IAM security-event monitoring và HR work-calendar behavior vẫn ngoài P1 trừ khi được promote bằng controlled revision riêng.
6. Có thể viết UAT/DES-review evidence từ canonical rule mà không dựa vào implementation assumption chưa được nêu.
7. Review phải sinh blocking-ID snapshot từ register như non-normative audit evidence.

**v0.5.1 blocking snapshot (chỉ là report):** `WA-TBD-001`, `WA-TBD-002`, `WA-TBD-003`, `WA-TBD-004`, `WA-TBD-005`, `WA-TBD-007`, `WA-TBD-008`, `WA-TBD-009`. `WA-TBD-006` được phân loại `DESIGN_DECISION` với due gate `BEFORE_BUILD`; nó không phải provisional FRS blocker trừ khi resolution của nó làm thay đổi business obligation.
## 7. Đặc tả chức năng chi tiết Wave B

### 7.1 Hợp đồng đặc tả chi tiết

Wave B tuân theo thứ tự đặc tả đã được phê duyệt trong Phase Planning: Shared Controls & MDM. Wave này tinh chỉnh các parent requirement P1 hiện có `FR-CTL-001`, `FR-CTL-002`, `FR-CTL-004`, `FR-AUD-001`, `FR-SYS-001`, `FR-MDM-001`, `FR-MDM-002` và `FR-MDM-003` mà không tạo capability nghiệp vụ mới.

Wave B kế thừa các quy ước đặc tả chi tiết của Wave A. Ngoài ra:

- Nghĩa vụ reconciliation/event chỉ tồn tại khi source capability của nó nằm trong phạm vi đã phê duyệt. Không được giả lập source thuộc phase sau chỉ để thỏa `FR-CTL-002`.
- Central Audit vẫn có thể được hiện thực độc lập với `FR-CTL-002`; event/reconciliation context có thể làm giàu audit evidence nhưng không phải hard prerequisite của `FR-AUD-001`.
- Shared Workflow chỉ bắt buộc khi business rule chi phối yêu cầu business approval. State transition thông thường của module không tự động trở thành workflow.
- MDM quản trị common standard, ownership classification và identification rule; MDM không trở thành owner cạnh tranh của Product, Customer, Supplier hoặc Employee.
- Hiện tại các dataset `provinces` và `wards` của P1 được coi là controlled data nội bộ đã được phê duyệt. Kiểm soát external normative source chỉ áp dụng nếu rule/dataset tương lai dựa trên nguồn pháp lý, regulatory hoặc official-standard.
- Database schema, UI layout, API path, physical audit-storage mechanism, workflow-engine technology và cú pháp code-format vẫn là quyết định thiết kế downstream trừ khi nguồn ràng buộc rõ.

### 7.2 Các invariant và ranh giới chi phối Wave B

| Invariant / ranh giới | Hệ quả đối với Wave B |
|---|---|
| `AR-DATA-OWNERSHIP-001` | Mọi common/reference dataset phải được phân loại là System, Group hoặc Company Master trước khi publish; ngoại lệ yêu cầu owning-domain rationale rõ ràng. |
| `AR-DOM-001` | MDM hoặc Shared Controls không được tạo master/SSOT cạnh tranh cho entity do domain khác sở hữu. |
| `AR-TXN-001` | Transaction Data luôn resolve đúng một Company sở hữu; shared control và override không được chuyển transaction ownership lên Group. |
| `FR-IAM-003` | Các thao tác control, workflow, audit-query và master-data hướng tới User vẫn chịu common Resource/Action Authorization decision. |
| Ranh giới P1 | `BR-CTL-003`, `BR-IAM-006`, `BR-DOC-001`, finance posting/close và các capability phase sau khác không được promote vào P1 bởi đặc tả này. |
| Controlled-source rule | Một statement normative về legal/regulatory/official không được approved nếu `source_refs` của nó chưa resolve tới controlled source có `status=VERIFIED`. |

### 7.3 Kiểm soát liên miền & Workflow

#### 7.3.1 FR-CTL-001 — Đặc tả chi tiết: lifecycle, numbering và quan hệ chứng từ

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | User được ủy quyền đối với thao tác chứng từ hướng tới người dùng; system process có thể áp dụng lifecycle/numbering rule khi được trigger bởi business operation đã được authorize. Resource/Action ID cụ thể chưa được upstream định nghĩa. |
| Trigger | Các thao tác suy ra: tạo hoặc tiến hành business document, gán official document number, map module state sang common lifecycle, hoặc thiết lập quan hệ source/derived/replacement/adjustment/reversal. |
| Hard prerequisites | `FR-ORG-002`, `FR-ORG-004`. |
| Các business concept chính | Common document lifecycle, module-state mapping, document numbering policy, document relation. |
| Các common state được upstream định nghĩa rõ | Nháp, Chờ xem xét, Đã duyệt, Đã ghi nhận chính thức, Hoàn tất, Hủy, Đảo/Điều chỉnh. |

**Đầu vào và context**

Nguồn yêu cầu đủ context để xác định document type, Company, Branch khi áp dụng, business period/calendar context, approved numbering policy đang áp dụng, module-specific state và document relation. Nguồn không định nghĩa cú pháp numbering cụ thể, sequence-reset behavior, thời điểm cấp số hoặc toàn bộ module-state mapping.

**Quy tắc xử lý và validation**

1. Mọi business document trong scope tham gia common lifecycle phải có thể biểu diễn bằng một trong các common lifecycle meaning đã định nghĩa ở trên.
2. Module có thể duy trì domain-specific state bổ sung, nhưng mỗi mapping dùng cho common control/reporting phải bảo toàn ý nghĩa common state và không được định nghĩa lại ý nghĩa đó.
3. Việc gán số phải resolve approved numbering policy theo document type và organizational/time context áp dụng do nguồn mô tả: type, Company, Branch, period và approved numbering policy khi áp dụng.
4. Khi một số đã được phát hành chính thức, số đó không được tái sử dụng như official document number khác trong numbering context áp dụng.
5. Hệ thống phải lưu đủ relation information để phân biệt ít nhất quan hệ source, generated/derived, replacement, adjustment và reversal giữa các chứng từ khi các quan hệ đó áp dụng.
6. Relation dùng để giải thích business value hiện tại phải vẫn trace được về document đã tạo, thay thế, điều chỉnh hoặc đảo giá trị trước đó.
7. FR này không tự tạo mandatory workflow cho mọi state transition; business approval được quản trị riêng bởi `FR-CTL-004`.
8. FR này không định nghĩa financial-period close behavior; financial close nằm ngoài P1 trừ khi được phê duyệt riêng.

**Ranh giới lifecycle và numbering**

Upstream requirement cố định ý nghĩa common state nhưng không định nghĩa universal state-transition graph. Nguồn cũng yêu cầu official number không được tái sử dụng nhưng không định nghĩa chính xác event nào làm cho một number trở thành “official”. Các quyết định đó phải được kiểm soát trước trạng thái implementation-ready.

**Đầu ra và bằng chứng được lưu giữ**

Đối với document trong scope, hệ thống phải có khả năng resolve common lifecycle meaning, module-specific state khi áp dụng, official number/policy context khi đã gán, và các relation source/replacement/adjustment/reversal liên quan. Lịch sử relation phải đủ để reviewer xác định document nào đã tạo hoặc thay đổi current value.

**Nghĩa vụ Authorization và audit**

Document operation vẫn chịu `FR-IAM-003`; lifecycle/numbering rule không cấp Permission. Thay đổi business/data thuộc Central Audit coverage set được xử lý bởi `FR-AUD-001`. Permission ID và audit coverage cụ thể là controlled decision downstream.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-CTL-001-01` | Hai module sử dụng các domain-specific state khác nhau | Map state để phục vụ common control/reporting | Mapping resolve ra common lifecycle meaning nhất quán mà không định nghĩa lại ý nghĩa đó | `FR-CTL-001.01` |
| `SCN-CTL-001-02` | Official number đã được phát hành trong numbering context áp dụng | Document khác cố gắng tái sử dụng official number đó | Việc tái sử dụng bị từ chối | `.02` |
| `SCN-CTL-001-03` | Document bị replace, adjust hoặc reverse | Reviewer kiểm tra current value | Relation chain xác định document đã tạo hoặc thay đổi current value | `.03` |

**TBD / các quyết định downstream**

- Common-state transition matrix và module-to-common-state mapping theo từng P1 domain.
- Numbering policy model, uniqueness scope, reset rule và event chính xác làm cho number trở thành official.
- Cardinality/cycle constraint và identifier cho document relation.

#### 7.3.2 FR-CTL-002 — Đặc tả chi tiết: responsibility, event và reconciliation xuyên chứng từ

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Business responsibility được gán cho creator/approver/poster/adjuster liên quan hoặc system context. Query/reconciliation action được thực hiện bởi User được ủy quyền; upstream không quy định tên role cụ thể. |
| Trigger | Một transaction/event quan trọng trong scope được tạo hoặc hoàn thành, hoặc reconciliation trong scope được thực hiện giữa các document/data liên quan. |
| Hard prerequisites | `FR-CTL-001`, `FR-IAM-003`. |
| Rule phạm vi có điều kiện | Nghĩa vụ event/reconciliation chỉ áp dụng khi source capability tương ứng nằm trong phạm vi đã phê duyệt. |
| Ví dụ P1 được upstream nêu rõ | Master record publication, order confirmation, receiving và goods issue. Các ví dụ này không tạo event taxonomy đầy đủ. |

**Đầu vào và context**

Responsibility evidence có thể bao gồm creator, approver, official poster/recorder, adjuster, timestamp, reason và evidence/reference liên quan khi áp dụng. Reconciliation yêu cầu các source record liên quan cùng control total phù hợp với class trong scope. Nguồn không quy định một universal reconciliation formula hoặc universal event taxonomy duy nhất.

**Quy tắc xử lý và validation**

1. Một important transaction sample trong capability thuộc scope phải lưu đủ responsibility information để xác định actor liên quan, time và reason/evidence theo business context.
2. Đối với mỗi reconciliation class được kích hoạt trong P1 acceptance scope, hệ thống phải tính hoặc lưu applicable control total, xác định discrepancy khi total không reconcile, và gắn discrepancy với owner cùng handling status.
3. Quantity, amount, receivable/payable hoặc inventory control total chỉ bắt buộc khi source capability và dữ liệu tương ứng thực sự tồn tại trong phạm vi đã phê duyệt.
4. Mọi event class nằm trong acceptance scope phải xác định tối thiểu event time, business object, owner/responsible context và business result.
5. Source capability không thuộc phạm vi đã phê duyệt không được bị khởi tạo bằng transaction/event giả chỉ để thỏa FR này.
6. P1 không được tạo event class cho production completion, invoice, payment, return hoặc payroll lock chỉ vì các ví dụ đó được nêu cho khả năng áp dụng tương lai.
7. Related-document evidence có thể tham chiếu evidence sẵn có mà không biến P2 Document capability thành P1 hard prerequisite.
8. Authorization thực hiện underlying action đến từ `FR-IAM-003`; bản thân việc ghi event không cấp action đó.

**Ranh giới reconciliation**

Nguồn cố định conditional applicability, ownership và status expectation cho discrepancy, nhưng không định nghĩa đầy đủ P1 reconciliation catalog, formula, tolerance hoặc discrepancy status vocabulary. Các nội dung đó phải được kiểm soát theo từng class.

**Đầu ra và bằng chứng được lưu giữ**

Đối với mỗi event trong acceptance scope, hệ thống phải làm cho event time, object, owner/responsible context và result trace được. Đối với mỗi reconciliation class đã kích hoạt, control total và discrepancy owner/status phải truy xuất được. Source record ngoài scope vẫn vắng mặt thay vì được giả lập.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-CTL-002-01` | Một P1 transaction class được chọn để sampling responsibility | Kiểm tra sample transaction | Có thể trace creator/approver/poster/adjuster/time/reason evidence bắt buộc khi áp dụng cho transaction đó | `FR-CTL-002.01` |
| `SCN-CTL-002-02` | Một P1 reconciliation class đã được phê duyệt có source record liên quan | Chạy reconciliation | Sinh control total; chênh lệch có owner và handling status | `.02` |
| `SCN-CTL-002-03` | Một event class như order confirmation hoặc receiving trong scope nằm trong acceptance scope | Event xảy ra | Time, object, owner/responsible context và business result được ghi nhận/resolve | `.03` |
| `SCN-CTL-002-04` | Source capability tương lai như Payroll hoặc Invoice không thuộc P1 | Thực thi P1 acceptance | Không yêu cầu event/data giả cho source đó | `.04` |

**TBD / các quyết định downstream**

- Controlled P1 event-class catalog và event ownership/responsibility mapping.
- Controlled P1 reconciliation-class catalog, formula/control total, tolerance rule và discrepancy state.
- Cơ chế evidence-reference cụ thể khi related file/document tồn tại nhưng `BR-DOC-001` không thuộc P1.

#### 7.3.3 FR-CTL-004 — Đặc tả chi tiết: business approval và Shared Workflow

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Các tác nhân nghiệp vụ được upstream nêu rõ | Developer, System Admin, Business Owner, workflow participant/approver. |
| Trigger | Một business object được rule/policy chi phối phân loại là cần business approval. |
| Hard prerequisites | `FR-CTL-001`, `FR-IAM-003`. |
| Các chiều approval được upstream nêu | Document type, value, risk, Company, organizational unit và role. |
| Capability semantics bắt buộc | Một hoặc nhiều level; reject; return for correction; cancel; escalate; delegate; lịch sử quyết định. |

**Đầu vào và context**

Một workflow instance yêu cầu Workflow Type áp dụng, business object/context, approval Rule, cách resolve participant/approver và workflow state hiện tại. Nguồn yêu cầu governance/audit và lịch sử quyết định có hiệu lực nhưng không quy định workflow-state enumeration, rule language, participant algorithm hoặc escalation timer.

**Quy tắc xử lý và validation**

1. Shared Workflow chỉ được invoke khi business rule/policy áp dụng yêu cầu business approval; ordinary module state transition không tự động yêu cầu Workflow.
2. Approval Rule áp dụng có thể xét các dimension có căn cứ nguồn được liệt kê ở trên và có thể định nghĩa một hoặc nhiều approval level.
3. Workflow phải hỗ trợ các decision/handling semantics có căn cứ nguồn: approve khi áp dụng, reject, return for correction, cancel, escalate và delegate, đồng thời bảo toàn lịch sử quyết định.
4. Mỗi business object đang approval phải resolve workflow instance, rule/level áp dụng, participant/approver, trạng thái hiện tại và lịch sử quyết định.
5. Mỗi quyết định phải lưu decision maker, result, time, reason khi áp dụng, và việc quyết định còn effective/current đối với business object hay không.
6. Workflow decision không được tạo Resource/Action Permission. Trước khi approver thực hiện approval action, action phải được `FR-IAM-003` cho phép trong context đã resolve.
7. Developer sở hữu việc tạo Workflow Type; System Admin triển khai/quản trị Workflow; Business Owner cấu hình Rule và Participant chỉ trong phạm vi được phép.
8. Business Owner không được sửa developer-level Workflow Type definition thông qua business configuration thông thường.
9. System Admin không được âm thầm thay đổi business rule ngoài delegated administrative scope; các thay đổi đó yêu cầu lịch sử/bằng chứng.
10. Lịch sử Workflow configuration/decision phải trace được đủ để giải thích rule và participant configuration nào chi phối một quyết định.

**Ranh giới quản trị**

Phân tách role do upstream quy định là normative, nhưng technical privilege cụ thể, workflow deployment lifecycle và rule-edit approval process không được quy định. Các chi tiết đó phải bảo toàn sự phân tách đã nêu mà không tự tạo business role mới.

**Đầu ra và bằng chứng được lưu giữ**

Đối với object thuộc phạm vi đã phê duyệt và cần approval, hệ thống phải cung cấp Workflow Type/instance, rule và level áp dụng, participant/approver, trạng thái hiện tại, decision/result, time, reason khi áp dụng, delegation/escalation context khi được sử dụng, và lịch sử. Bằng chứng này không thay thế IAM Authorization evidence.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-CTL-004-01` | Một business object khớp approval rule | Kiểm tra xử lý approval | Rule/level, decision maker, result, time, reason và effective decision status đều trace được | `FR-CTL-004.01` |
| `SCN-CTL-004-02` | Một workflow instance tồn tại | Kiểm tra lifecycle | Workflow, rule, participant/approver, trạng thái hiện tại và lịch sử quyết định đều trace được | `.02` |
| `SCN-CTL-004-03` | Participant thiếu approval Resource/Action Permission | Workflow rule lẽ ra chọn participant đó | Workflow không tạo/bypass Permission còn thiếu | `.03` |
| `SCN-CTL-004-04` | Workflow Type được deploy | Kiểm tra governance data | Xác định được source/creator, deployment/admin scope và Business Owner chịu trách nhiệm cho rule/participant | `.04` |
| `SCN-CTL-004-05` | Business Owner hoặc System Admin cố gắng thay đổi ngoài upstream role split | Thực hiện configuration change | Thay đổi không được hỗ trợ phải bị ngăn hoặc được kiểm soát để không thể xảy ra âm thầm mà không có lịch sử | `.05` |

**TBD / các quyết định downstream**

- Workflow-state model và allowed state transition.
- Rule representation/precedence, participant-resolution algorithm và multi-level progression semantics.
- Rule/timer chi tiết cho delegation, escalation, cancellation và return-for-correction.
- Administrative Resource/Action Permission cụ thể và deployment/change-control mechanism.

### 7.4 Audit & Traceability

#### 7.4.1 FR-AUD-001 — Đặc tả chi tiết: Central Audit Trail

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân ghi nhận | Hệ thống ghi audit evidence cho các thay đổi business/data trong scope đã cấu hình; actor/User chịu trách nhiệm hoặc system context được lưu giữ. |
| Tác nhân truy vấn | User được ủy quyền; nguồn không nêu role auditor/administrator cụ thể. |
| Trigger | Một thay đổi business/data được phân loại quan trọng và thuộc controlled Central Audit coverage set xảy ra. |
| Hard prerequisite | `FR-IAM-001`. |
| Reference không-hard | `FR-CTL-002` có thể cung cấp event/reconciliation context nhưng không bắt buộc để Central Audit tồn tại. |

**Đầu vào và nội dung audit**

Đối với audited change, nguồn yêu cầu Before Value, After Value, Reason, Approval, Attachment khi áp dụng, actor/User và timestamp, cùng với object/change context bị ảnh hưởng. Nguồn không quy định record schema, serialization format và attachment-reference mechanism cụ thể.

**Quy tắc xử lý và validation**

1. Central Audit phải ghi business/data-change evidence cho các change class nằm trong P1 audit coverage set.
2. Audited change phải lưu đủ thông tin để reconstruct before/after value của subject được audit ở granularity yêu cầu.
3. Audit record phải xác định actor/User hoặc system context, time, affected object, change, reason và approval/attachment khi các thành phần đó áp dụng.
4. Central Audit sở hữu business/data-change audit semantics; IAM security/authentication/access/authorization-administration event vẫn tách biệt về mặt khái niệm.
5. Audit consumer phải phân biệt được Central Audit business/data-change record với IAM security event class.
6. Context từ `FR-CTL-002` có thể được link khi sẵn có, nhưng việc thiếu context đó không được ngăn Central Audit ghi nhận configured audited change.
7. FR này không tạo capability P2 IAM security-event monitoring (`BR-IAM-006`).
8. Constraint về integrity/immutability, retention và evidence-queryability tiếp tục chịu companion P1 `NFR-AUD-001`; functional FR này không thay thế NFR đó.

**Ranh giới coverage**

Nguồn yêu cầu audit các thay đổi business/data quan trọng nhưng không liệt kê đầy đủ P1 audited object/change catalog hoặc field-level granularity. Vì vậy coverage catalog là controlled downstream decision cần được chốt trước khi có thể xác lập UAT completeness.

**Đầu ra và bằng chứng được lưu giữ**

Authorized audit consumer phải trace được audited business object/change tới before/after data và responsible context. Audit classification phải làm cho IAM security event phân biệt được với Central Audit data-change evidence.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-AUD-001-01` | Một configured important business/data change xảy ra | Truy vấn audit evidence | Before/after value của audited change trace được | `FR-AUD-001.01` |
| `SCN-AUD-001-02` | Một audit record tồn tại | Kiểm tra evidence | Xác định được Actor/User, time, object, change, reason và approval/attachment khi áp dụng | `.02` |
| `SCN-AUD-001-03` | Đồng thời có Central Audit record và IAM security event | Phân loại/truy vấn record | Business/data-change evidence phân biệt được với IAM security-event evidence | `.03` |
| `SCN-AUD-001-04` | Không có `FR-CTL-002` event/reconciliation context cho audited change | Audited change xảy ra | Central Audit vẫn ghi nhận được change vì CTL-002 không phải hard prerequisite | Source boundary |

**TBD / các quyết định downstream**

- P1 Central Audit coverage catalog theo object/change class và field granularity.
- Biểu diễn audit record, change-diff format và attachment/reference representation.
- Query permission và operational audit-search/filter model.
- Chi tiết realization về integrity/retention của companion `NFS-AUD-001`.

### 7.5 Nền tảng hệ thống

#### 7.5.1 FR-SYS-001 — Đặc tả chi tiết: locale và display configuration

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | User được ủy quyền chịu trách nhiệm locale/display configuration; upstream không định nghĩa tên role cụ thể. |
| Trigger | Tạo/thay đổi locale/display configuration được phép hoặc render data bằng display configuration áp dụng. |
| Hard prerequisite | `FR-ORG-004`. |
| Các display attribute được upstream nêu | Default display timezone, date/time format, currency symbol/format, separators/decimal display, language và display rule. |

**Đầu vào và context**

Mỗi configuration yêu cầu application scope, manager/owner chịu trách nhiệm và lịch sử thay đổi. Nguồn không định nghĩa scope-precedence hierarchy như platform/Group/Company/User, cũng không liệt kê supported locale code hoặc language pack.

**Quy tắc xử lý và validation**

1. Locale/display configuration có thể điều khiển cách trình bày các display attribute có căn cứ nguồn mà không thay đổi canonical business data.
2. Company business timezone và business/base/functional currency vẫn thuộc `FR-ORG-004` và không được display setting ghi đè.
3. Thay đổi display-timezone có thể thay đổi cách hiển thị timestamp nhưng không được thay đổi canonical business timestamp hoặc business-period meaning đã ghi nhận.
4. Currency symbol/format và decimal/separator presentation không được thay đổi underlying monetary value hoặc business currency.
5. Mỗi configuration phải xác định application scope, manager/owner chịu trách nhiệm và lịch sử thay đổi.
6. Nếu có nhiều display configuration cùng áp dụng, resolution phải deterministic theo controlled scope/precedence rule; exact precedence chưa được upstream định nghĩa và vẫn là TBD.

**Đầu ra và bằng chứng được lưu giữ**

Hệ thống phải có khả năng xác định locale/display configuration nào áp dụng cho một presentation context và lưu configuration-change history. Canonical Company business currency/timezone/timestamp/value vẫn phải resolve độc lập với displayed representation.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-SYS-001-01` | Locale/display configuration được lưu | Kiểm tra governance | Xác định được application scope, manager/owner chịu trách nhiệm và lịch sử thay đổi | `FR-SYS-001.01` |
| `SCN-SYS-001-02` | Transaction có canonical Company business timestamp/currency/value | Thay đổi locale/display setting | Hiển thị có thể thay đổi nhưng canonical business timestamp/period, currency và monetary value vẫn không đổi | `.02` |

**TBD / các quyết định downstream**

- Supported locale/language/display-option catalog.
- Configuration scope level và deterministic precedence khi có nhiều setting áp dụng.
- Exact configuration-management Permission ID.

### 7.6 Dữ liệu chủ & Quản trị dữ liệu

#### 7.6.1 FR-MDM-001 — Đặc tả chi tiết: common reference data và chuẩn định danh

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | User được ủy quyền chịu trách nhiệm dữ liệu reference/identification được quản trị; nguồn không nêu một business role MDM duy nhất. |
| Trigger | Định nghĩa/publish common primitive/reference dataset, gán ownership classification, duy trì identification standard/RD Code rule, hoặc duy trì controlled address data. |
| Hard prerequisite | `FR-ORG-002`. |
| Invariant chi phối | `AR-DATA-OWNERSHIP-001`. |
| Ranh giới business entity tường minh | Product, Customer, Supplier và Employee giữ canonical owning domain của mình; MDM không sở hữu replacement master. |

**Đầu vào và context**

Capability có thể quản trị common primitive như unit of measure, shared group/classification, country, geography, HS code, tax rate và foundation list khác. Mỗi common/reference dataset được publish yêu cầu ownership classification. Identification governance bao phủ Customer, Supplier, location và RD Code khi áp dụng. Address control hiện tại sử dụng các dataset nội bộ đã được phê duyệt `provinces` và `wards`.

**Quy tắc xử lý và validation**

1. Trước khi publish/sử dụng, mọi common primitive/reference dataset phải được phân loại là System Master, Group Master hoặc Company Master theo `AR-DATA-OWNERSHIP-001`.
2. Các domain phải consume một controlled definition cho cùng common primitive và không được tạo standard list cạnh tranh cho cùng ý nghĩa.
3. Ngoại lệ đối với default ownership rule phải xác định owning domain và business rationale.
4. MDM không được trở thành canonical owner của Product, Customer, Supplier hoặc Employee chỉ vì MDM quản trị standard mà các entity đó sử dụng.
5. Customer, Supplier và location phải tuân theo approved identification/code rule, và code sinh ra phải vẫn trace được tới related transaction khi các transaction đó tồn tại.
6. MDM identification rule không được tạo Customer hoặc Supplier master trùng lặp.
7. RD Code là specialization của identification standard cho context material/semi-finished/finished Product và Supplier khi áp dụng; RD Code phải link về cùng canonical entity thay vì tạo business identity mới.
8. Với object thuộc RD Code scope, RD Code phải unique theo approved rule, xác định được type/group, và governing code rule phải có owner cùng effective date. RD Code đã sử dụng không được tái sử dụng như một approved code khác theo rule đó.
9. Hệ thống phải phân biệt internal system identity, business code, RD Code khi áp dụng và optional external/partner code của cùng canonical entity.
10. Address mới trong P1 model hiện tại phải tham chiếu record đang hiệu lực từ controlled dataset `provinces` và `wards`. Historical business data phải bảo toàn address code/name được dùng tại transaction/effective time ngay cả khi controlled dataset thay đổi về sau.
11. Controlled address dataset phải cung cấp owner, approval status và version/thời gian hiệu lực.
12. Dữ liệu `provinces`/`wards` P1 hiện tại được phê duyệt nội bộ và không yêu cầu `source_refs` chỉ vì được sử dụng trong P1 hiện tại.
13. Nếu reference dataset/rule tương lai dựa trên external legal/regulatory/official-standard source, consuming normative rule không được approved cho đến khi controlled source reference tương ứng resolve với `status=VERIFIED`.
14. Database schema/field detail của `provinces` và `wards` là mối quan tâm của database design và không được FRS này định nghĩa.

**Hướng dẫn quyết định ownership suy ra từ `AR-DATA-OWNERSHIP-001`**

| Ý nghĩa reference/common-data | Cách xử lý ownership mặc định |
|---|---|
| Standard meaning độc lập với một enterprise cụ thể và cần nhất quán toàn platform | System Master |
| Taxonomy/classification/coding/policy do doanh nghiệp định nghĩa và dùng chung giữa các Company trong Group | Group Master |
| Dữ liệu chỉ có ý nghĩa/hiệu lực trong một Company, khi owning-domain requirement yêu cầu Company specificity rõ ràng | Company Master |
| Ngoại lệ | Phải xác định rõ owning domain và business rationale trước khi publish |

**Đầu ra và bằng chứng được lưu giữ**

Hệ thống phải làm cho controlled definition, ownership classification, quan hệ code/identity, rule owner/effective date và governance của controlled address dataset có thể resolve. Khi external normative source tương lai áp dụng, controlled `source_id` tương ứng và verified status phải trace được trước approval.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-MDM-001-01` | Hai domain cần cùng một common primitive | Primitive được publish | Cả hai dùng một controlled definition; không tạo standard list cạnh tranh | `FR-MDM-001.01` |
| `SCN-MDM-001-02` | Một common/reference dataset mới được đề xuất | Thực hiện publish | Dataset phải có System/Group/Company ownership classification trước | `.02` |
| `SCN-MDM-001-03` | Reference dataset lệch khỏi default ownership rule | Dataset được approved/publish | Xác định được owning domain và business rationale | `.03` |
| `SCN-MDM-001-04` | Product/Customer/Supplier/Employee data được MDM standard quản trị | Kiểm tra canonical ownership | Business entity vẫn do source domain sở hữu, không phải MDM | `.04`, `.06` |
| `SCN-MDM-001-09` | Customer, Supplier hoặc governed location được định danh theo approved coding standard | Kiểm tra code và related business reference | Approved code xác định cùng canonical entity/location và related transaction trace được khi tồn tại | `.05`, `.06` |
| `SCN-MDM-001-05` | Object thuộc RD Code scope | Kiểm tra identification | RD Code unique theo rule, biết type/group và rule owner/effective date, đồng thời link tới canonical Product/Supplier | `.07`, `.08`, `.09` |
| `SCN-MDM-001-06` | Address mới được tạo | Validate address reference | Address dùng `provinces`/`wards` đang hiệu lực; historical address code/name vẫn được bảo toàn sau thay đổi dataset về sau | `.10` |
| `SCN-MDM-001-07` | Kiểm tra internal address dataset P1 hiện tại | Chạy source-reference validation | Không yêu cầu external `source_refs` chỉ vì đây là các P1 dataset nội bộ hiện tại đã được phê duyệt | `.11` |
| `SCN-MDM-001-08` | Future normative reference rule dựa trên official external source | Thực hiện approval | Approval bị chặn cho đến khi controlled source reference resolve là `VERIFIED` | `.11` |

**TBD / các quyết định downstream**

- Complete P1 reference/common-data catalog và ownership-classification register.
- Identification code format, RD Code uniqueness scope và code-generation/allocation mechanic.
- Governance workflow để publish/update internally approved reference dataset.
- Physical schema và indexing của `provinces`/`wards`, thuộc database design thay vì FRS này.

#### 7.6.2 FR-MDM-002 — Đặc tả chi tiết: ownership, sharing và field-level override

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | User được ủy quyền consume hoặc duy trì governed data trong ownership/authorization scope đã resolve. |
| Trigger | Đọc/thay đổi governed data, phân loại data ownership, hoặc tạo/thay đổi Company-level override cho Group Master field đã khai báo overrideable. |
| Hard prerequisites | `FR-ORG-001`, `FR-ORG-002`, `FR-MDM-001`. |
| Các invariant chi phối | `AR-DATA-OWNERSHIP-001`, `AR-TXN-001`. |
| Functional reference không-hard | `FR-PRC-001`, `FR-PRD-005`. |

**Các canonical P1 ownership classification được upstream cố định rõ**

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
| Business Transaction | Transaction Data thuộc đúng một Company |

Product Moving Average Cost cũng được cố định là Group Master trong canonical ownership matrix và vẫn là Product attribute do hệ thống suy ra theo Product/Inventory rule; đây không phải Company override.

**Quy tắc xử lý và validation**

1. System, Group và Company Master data chỉ được share/visible theo ownership scope và effective authorization của User.
2. Nhu cầu access cục bộ không được giải quyết bằng cách tạo competing master copy khi canonical ownership model đã cung cấp shared data.
3. Mỗi Transaction Data record phải resolve đúng một Company sở hữu và không được Group sở hữu trực tiếp.
4. User không được thay đổi governed data ngoài cả data ownership scope và Authorization của User.
5. Mọi governed data type phải có ownership level đã biết và access rule tương ứng trước khi sử dụng.
6. Field Level Override theo default deny: Company chỉ được tạo override cho Group Master field được owning-domain requirement/policy khai báo rõ là overrideable.
7. Nếu field không được khai báo overrideable, ordinary configuration không được tạo Company override và Group Master value vẫn là authoritative value của field đó.
8. Override hợp lệ phải lưu Company, source Group value, override value và thời gian hiệu lực để effective value có thể được giải thích.
9. Việc áp dụng override không được mutate Group Master value dùng bởi Company khác.
10. Field override không được tạo canonical business entity/master thứ hai ở Company level và không được thay đổi owning domain của entity.
11. Khác biệt pricing theo Company phải được biểu diễn bằng Company Pricing Policy theo `FR-PRC-001`, không phải direct field override của Group Pricing Policy hoặc Product Default Selling Price.
12. Product Default Selling Price vẫn chịu `FR-PRD-005`; đây không phải generic Company override field.

**Ranh giới effective-value**

Nguồn thiết lập default-deny và fallback về Group Master value đối với field không overrideable. Nguồn không định nghĩa đầy đủ overlap/precedence khi nhiều time-effective override record cho cùng Company/field có thể tồn tại. Ambiguity đó phải được ngăn bằng controlled downstream rule thay vì implementation tự đoán.

**Đầu ra và bằng chứng được lưu giữ**

Đối với governed data, hệ thống phải có khả năng resolve ownership level, canonical owner/entity, Company context áp dụng, Authorization context và, khi override áp dụng, source Group value, Company override value cùng thời gian hiệu lực. Hệ thống cũng phải giải thích được rằng pricing difference đến từ Pricing Policy thay vì generic field override.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-MDM-002-01` | Shared Group Master data được nhiều Company sử dụng | Một Company consume data | Không cần competing local master chỉ để access | `FR-MDM-002.01` |
| `SCN-MDM-002-02` | Transaction Data record được tạo | Resolve ownership | Đúng một Company sở hữu; Group không phải direct owner | `.02`, `.07` |
| `SCN-MDM-002-03` | User nằm ngoài ownership/authorization scope | Thực hiện modification | Modification bị từ chối | `.03` |
| `SCN-MDM-002-04` | Kiểm tra canonical data type P1 | Resolve ownership | Group/Company classification khớp upstream ownership matrix | `.04`-`.06` |
| `SCN-MDM-002-05` | Một field được khai báo overrideable | Company override có hiệu lực | Trace được Company, Group source value, override value và thời gian hiệu lực | `.08` |
| `SCN-MDM-002-06` | Field không được khai báo overrideable | Cố gắng tạo Company override | Override bị từ chối và Group Master vẫn authoritative | `.09` |
| `SCN-MDM-002-07` | Company A override một field được phép | Company B đọc cùng Group Master | Group Master value của Company B không thay đổi | `.10`, `.11` |
| `SCN-MDM-002-08` | Cần selling price riêng theo Company | Cấu hình Pricing | Khác biệt được biểu diễn bằng Company Pricing Policy, không ghi đè Group Policy/default price | `.12` |

**TBD / các quyết định downstream**

- Controlled overrideable-field catalog theo owning domain.
- Rule về overlap/precedence và end-dating của effective Company override.
- Resource/Action mapping cho ownership-classification và override administration.

#### 7.6.3 FR-MDM-003 — Đặc tả chi tiết: lifecycle và sensitive master-data change

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Proposer/change actor, owner và approver được ủy quyền theo controlled-change policy áp dụng; upstream không liệt kê tên role cụ thể. |
| Trigger | Tạo/thay đổi lifecycle/effective data cho governed master record, hoặc yêu cầu manual sensitive/controlled change. |
| Hard prerequisites | `FR-MDM-001`, `FR-IAM-003`, `FR-CTL-004`. |
| Ví dụ sensitive được upstream nêu rõ | Tax code, bank account, payment terms và permitted manual override. |
| Reference không-hard | `FR-INV-001` đối với system-derived Group-level Product Moving Average Cost. |

**Đầu vào và context**

Governed master record yêu cầu lifecycle/status và effective-date context đủ để xác định record có thể được dùng cho transaction mới hay không. Sensitive manual change yêu cầu policy classification áp dụng cùng owner, proposer, approver, effective date, decision và evidence. Nguồn không định nghĩa một universal master-state vocabulary hoặc complete sensitive-field catalog.

**Quy tắc xử lý và validation**

1. Governed master record phải lưu state/lifecycle, effective date và reason cho controlled change.
2. Record không còn hiệu lực không được chọn/sử dụng cho transaction mới, trong khi historical reference vẫn được bảo toàn/truy vấn.
3. Manual change được policy phân loại là sensitive/controlled phải xác định business owner, proposer, approver, effective date, decision và evidence mà policy yêu cầu.
4. Nếu rule áp dụng yêu cầu approval, change phải sử dụng Shared Workflow `FR-CTL-004` và không được có official effect trước khi workflow đạt state cho phép hiệu lực.
5. Workflow history phải bảo toàn decision trail của sensitive change.
6. Group-level Product Moving Average Cost do `FR-INV-001` tính/cập nhật là system-derived value và không được xem là manual sensitive-master update chỉ vì giá trị thay đổi sau eligible inbound transaction.
7. System-derived Moving Average Cost change không được tự động khởi động sensitive-change approval workflow được định nghĩa ở đây.
8. Future manual cost override nằm ngoài current P1 acceptance trừ khi capability/policy riêng được phê duyệt cho phép; nếu về sau được phê duyệt, nó cần controlled sensitive-change rule riêng.
9. Authorization để propose/approve/apply change vẫn chịu `FR-IAM-003`; Workflow không cấp underlying Permission.

**Ranh giới lifecycle/approval**

Nguồn yêu cầu lifecycle, effective date và change reason nhưng không định nghĩa universal master state, allowed transition, retroactive-change rule hoặc complete sensitive-change trigger catalog. Các nội dung này phải được kiểm soát theo từng master domain/policy.

**Đầu ra và bằng chứng được lưu giữ**

Đối với governed master record, current/effective status và historical state/change evidence phải resolve được. Đối với sensitive manual change, hệ thống phải làm cho owner, proposer, approver, effective date, decision, evidence và workflow history trace được. System-derived Moving Average Cost update vẫn phải phân biệt được với manual controlled change.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-MDM-003-01` | Master record không còn hiệu lực | Transaction mới cố gắng sử dụng record | Việc sử dụng bị từ chối trong khi historical reference vẫn có sẵn | `FR-MDM-003.01` |
| `SCN-MDM-003-02` | Manual change được phân loại sensitive/controlled | Kiểm tra change evidence | Trace được owner, proposer, approver, effective date, decision và evidence | `.02` |
| `SCN-MDM-003-03` | Sensitive change yêu cầu approval | Workflow chưa đạt effective/approved state | Change chưa có official effect | `.03` |
| `SCN-MDM-003-04` | Eligible inbound transaction làm thay đổi Product Moving Average Cost thông qua `FR-INV-001` | Hệ thống cập nhật system-derived cost | Update không được xem là manual sensitive-master change và không tự khởi động approval workflow này | `.04` |

**TBD / các quyết định downstream**

- Lifecycle/state vocabulary, allowed transition và effective-dating rule cho từng governed P1 master.
- Controlled sensitive-field/change catalog và policy xác định manual change nào yêu cầu approval.
- Evidence requirement và exact Workflow Type/Rule mapping cho từng sensitive-change class.
- Future manual cost-override policy nếu có; không thuộc P1 hiện tại trừ khi được phê duyệt riêng.

### 7.7 Register quyết định chưa chốt của Wave B

Các item dưới đây được chủ ý để mở vì nguồn hiện tại chưa định nghĩa. Chúng phải được resolve bằng controlled policy, FRS refinement hoặc design theo chỉ định; coding không được tự âm thầm chọn business behavior. Classification tuân theo Section 1.4 và bản thân classification không resolve decision.

| ID | Quyết định chưa chốt | FR bị ảnh hưởng | Class | Owner | Artifact đích | Gate đến hạn | Trạng thái |
|---|---|---|---|---|---|---|---|
| `WB-TBD-001` | Common document transition model và module-to-common-state mapping | `FR-CTL-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/control policy | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-002` | Numbering policy model, uniqueness context, reset rule và official-number allocation point | `FR-CTL-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/control policy + DES | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-003` | Document-relation cardinality/cycle rule và relation identifier | `FR-CTL-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/DES | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-004` | P1 event/reconciliation catalog, formula/control total, tolerance, owner và discrepancy-state model | `FR-CTL-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS controlled catalog | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-005` | Workflow state model, rule representation/precedence, participant resolution, multi-level progression, delegation/escalation/cancellation semantics | `FR-CTL-004` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/workflow policy + DES | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-006` | Workflow administration Resource/Action Permission cụ thể và deployment/change-control mechanism | `FR-CTL-004` | `DESIGN_DECISION` | Solution Architecture/Design Owner | Authorization catalog + DES | `BEFORE_BUILD` | `OPEN` |
| `WB-TBD-007` | P1 Central Audit coverage catalog, field granularity, change-diff/attachment representation và query permission | `FR-AUD-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS audit catalog + NFS/DES | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-008` | Locale/display supported-option catalog, scope level và precedence | `FR-SYS-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FRS/configuration policy | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-009` | Complete P1 common/reference-data catalog và ownership-classification register | `FR-MDM-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Controlled MDM catalog | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-010` | Identification/RD Code format, uniqueness scope và allocation mechanic | `FR-MDM-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Identification policy + DES | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-011` | Overrideable-field catalog và effective-dating overlap/precedence rule | `FR-MDM-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Owning-domain policy + FRS | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-012` | Master lifecycle vocabulary và sensitive-change catalog/approval policy | `FR-MDM-003` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Owning-domain policy + FRS/Workflow config | `PROVISIONAL_READY` | `OPEN` |
| `WB-TBD-013` | Governance process cho publication/version/effective-time của internally approved `provinces`/`wards` dataset | `FR-MDM-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Controlled MDM policy | `PROVISIONAL_READY` | `OPEN` |

### 7.8 Gate provisional implementation-readiness của Wave B

Wave B có thể chuyển từ **detailed draft** sang **provisional implementation-ready** chỉ khi:

1. Cả tám parent FR Wave B vẫn trace về upstream BR và mọi acceptance-oriented child FR đều có explicit verification evidence path theo Section 1.3.
2. Không còn Wave B TBD nào có `class=BUSINESS_DECISION`, `due_gate=PROVISIONAL_READY` và `status!=APPROVED`; gate được evaluate từ register metadata, không từ blocker list được duy trì thủ công.
3. Mọi `DESIGN_DECISION` còn open đều có owner/target/due gate rõ, không thiết lập business semantics mới và được đóng trước build gate riêng.
4. Workflow administration bảo toàn phân tách Developer/System Admin/Business Owner nêu trong `BR-CTL-004`, và Workflow không bao giờ bypass `FR-IAM-003`.
5. Central Audit coverage được định nghĩa mà không biến `FR-CTL-002` thành hard prerequisite và không kéo P2 IAM security-event monitoring vào P1.
6. MDM catalog bảo toàn canonical owning domain và upstream ownership matrix; không local copy hoặc field override nào tạo competing master.
7. Governance hiện tại của internal `provinces`/`wards` được kiểm soát, trong khi future external normative source tuân theo verified `source_refs` rule trước approval.
8. Không later-phase source capability nào được giả lập chỉ để thỏa event, reconciliation, document, financial hoặc compliance behavior trong P1.
9. Có thể viết UAT/DES-review evidence từ controlled rule thay vì unstated implementation assumption.
10. Review phải sinh blocking-ID snapshot từ register như non-normative audit evidence.

**v0.5.1 blocking snapshot (chỉ là report):** `WB-TBD-001`, `WB-TBD-002`, `WB-TBD-003`, `WB-TBD-004`, `WB-TBD-005`, `WB-TBD-007`, `WB-TBD-008`, `WB-TBD-009`, `WB-TBD-010`, `WB-TBD-011`, `WB-TBD-012`, `WB-TBD-013`. `WB-TBD-006` được phân loại `DESIGN_DECISION` với due gate `BEFORE_BUILD`; nó không phải provisional FRS blocker trừ khi resolution của nó làm thay đổi business obligation.
## 8. Đặc tả chức năng chi tiết Wave C

### 8.1 Hợp đồng đặc tả chi tiết

Wave C tuân theo thứ tự đặc tả đã được phê duyệt cho **Core Masters**. Wave này tinh chỉnh các parent requirement P1 hiện có `FR-PRD-001`, `FR-PRD-002`, `FR-PRD-005`, `FR-CUS-001`, `FR-SUP-001` và `FR-PRC-001` mà không tạo capability Product, Customer, Supplier hoặc Pricing mới.

Wave C kế thừa document contract và các quy ước đặc tả chi tiết đã thiết lập ở Wave A và B. Ngoài ra:

- Product, Customer và Supplier vẫn là canonical business entity do domain tương ứng sở hữu. MDM cung cấp standard/governance nhưng không trở thành master owner.
- Product vẫn là Group Master. Company/channel/market usage scope chỉ giới hạn nơi cùng Product có thể được sử dụng; không tạo Product duplicate thuộc Company.
- `item_type=COMBO` và `item_type=GIFT` chỉ là classification trong P1. Composition, component consumption, stock treatment, revenue allocation và gift semantics nằm ngoài P1 cho đến khi `BR-PRD-004` thuộc phạm vi đã phê duyệt.
- Group-level Product Moving Average Cost được hệ thống suy ra thông qua `FR-INV-001`; không phải Company override và không thiết lập configurable valuation capability đã defer sang `BR-INV-005`.
- Product Default Selling Price là Group-level fallback source. Khác biệt giá theo Company được biểu diễn bằng Company Pricing Policy thay vì Company override đối với Product default price.
- Canonical price resolution chỉ dùng precedence đã được upstream phê duyệt rõ: **applicable Company Pricing Policy > applicable Group Pricing Policy > applicable Product Default Selling Price**. Nguồn không định nghĩa tie-break giữa nhiều policy cùng lúc áp dụng ở cùng ownership level; implementation không được tự âm thầm tạo rule này.
- Pricing Policy có thể tồn tại mà không có Customer-specific condition. Customer context chỉ được evaluate khi tồn tại customer-specific condition và context tương ứng có sẵn.
- Shared Workflow là conditional đối với Pricing Policy: chỉ được sử dụng khi approval rule/policy áp dụng yêu cầu business approval.
- UI layout, API shape, physical schema, search/index technology, barcode/QR encoding format và code-generation mechanic vẫn là quyết định thiết kế downstream trừ khi upstream ràng buộc rõ.

### 8.2 Các invariant và ranh giới chi phối Wave C

| Invariant / ranh giới | Hệ quả đối với Wave C |
|---|---|
| `AR-DOM-001` | Product, Customer, Supplier và Pricing mỗi loại giữ một canonical owning domain; downstream consumer không được tạo master cạnh tranh cho cùng ý nghĩa. |
| `AR-DATA-OWNERSHIP-001` / `FR-MDM-002` | Product, Product Default Selling Price, Customer và Supplier là Group Master; Company Pricing Policy là Company Master; Group Pricing Policy là Group Master. |
| `FR-MDM-001` | Approved identification/reference standard được Product/Customer/Supplier consume mà không chuyển canonical ownership vào MDM. |
| `FR-MDM-003` | Master lifecycle/effective-date rule được áp dụng; sensitive Supplier change sử dụng controlled approval theo upstream requirement. |
| `FR-IAM-003` | Create, change, publish, query và administrative action vẫn chịu Resource/Action Authorization trong context đã resolve. |
| `NFR-DAT-001` | Product quantity conversion bảo toàn source quantity và áp dụng approved precision/rounding semantics. |
| `FR-FIN-005` | Khi pricing sử dụng currency conversion, exchange-rate context và original/converted value phải vẫn trace được. |
| Ranh giới Product P1 | PLM lifecycle, end-to-end lot/serial control, configurable valuation policy và COMBO/GIFT operational semantics vẫn ngoài P1 trừ khi được phê duyệt riêng. |
| Ranh giới Pricing P1 | Promotion/discount/coupon engine không thuộc `FR-PRC-001`; `BR-PRC-002` vẫn ở phase sau. |

### 8.3 Product

#### 8.3.1 FR-PRD-001 — Đặc tả chi tiết: Product Master và lifecycle vận hành

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Product-domain User/business owner được ủy quyền để duy trì và publish master; User/process tiêu thụ trong Purchase, Inventory và Sales có thể tham chiếu Product đang hiệu lực. Upstream không định nghĩa tên role cụ thể. |
| Trigger | Tạo hoặc thay đổi Product Master, thay đổi operational lifecycle hoặc usage scope, đăng ký approved alternate identifier, hoặc consume Product trong transaction thuộc scope. |
| Hard prerequisites | `FR-MDM-001`, `FR-MDM-002`, `FR-MDM-003`. |
| Canonical ownership | Product domain; Group Master. |
| Các reference không-hard | `FR-INV-001`, future `BR-PRD-004`, future `BR-INV-005`. |

**Đầu vào và context**

Product Master phải lưu đủ controlled information để resolve owning Group, item type, business code/approved identifier, name/description, grouping/classification, specification, base/operational UOM context, weight/dimension khi áp dụng, operational status, usage scope và các operational attribute khác mà approved P1 consumer yêu cầu. Nguồn không định nghĩa complete mandatory-field matrix theo item type hoặc transaction type.

**Quy tắc xử lý và validation**

1. Mỗi Product Master phải resolve đúng một owning Group và vẫn là Group Master được các Company được authorize trong Group đó dùng chung.
2. Product item classification phải hỗ trợ các operational category được upstream nêu, gồm production material, consumable, semi-finished, finished, make-and-sell product, trading goods, COMBO, GIFT và approved operational item type khác.
3. Mỗi Product phải xác định purchase, inventory, production, sales hoặc usage policy/context áp dụng cần thiết cho approved consumer; exact policy catalog chưa được upstream quy định.
4. Company phải tham chiếu canonical Group Product thay vì tạo competing Product master chỉ cho local use.
5. Product Master độc lập với Engineering Product/Development Product trong PLM. Product lifecycle change không được hiểu là PLM development-stage change, và PLM change không được âm thầm rewrite ERP Product Master.
6. Trước khi Product được publish/available cho operational use, thông tin Product bắt buộc cho approved context phải hiện diện. Nguồn yêu cầu Product code unique nhưng không định nghĩa exact code format hoặc uniqueness scope ngoài applicable master context.
7. Weight, dimension và UOM attribute, khi áp dụng, phải được lưu/sử dụng nhất quán đủ cho Inventory, Sales và delivery-related consumer trong scope; detailed unit convention vẫn là controlled data/design decision.
8. Product usage scope phải resolve được trước khi transaction mới sử dụng Product. Scope có thể gồm Company, sales channel, market và pricing classification khi áp dụng.
9. Thay đổi usage scope không được tạo canonical Product Master khác và không được thay đổi Group ownership.
10. Pricing classification trên Product chỉ là classification. Việc gán/thay đổi classification không được trực tiếp sửa Product Default Selling Price hoặc tạo Pricing Policy.
11. Product operational status phải hỗ trợ tối thiểu các ý nghĩa upstream: draft, active/in-business, temporarily suspended và discontinued. Product discontinued/không còn hiệu lực không được sử dụng cho transaction mới, trong khi historical reference vẫn truy vấn được.
12. Approved barcode, QR, partner code và alternate code identifier phải resolve tới cùng canonical Product và không được tạo duplicate Product identity.
13. Trong P1, classification `COMBO` và `GIFT` không tạo mandatory component structure, component consumption, inventory treatment, revenue allocation hoặc gift-treatment behavior. Các nghĩa vụ đó chỉ bắt đầu khi capability tương ứng ở phase sau được phê duyệt.
14. Group-level Product Moving Average Cost phải được xem là system-derived Product operational cost attribute và được `FR-INV-001` cập nhật sau eligible inbound transaction. Product phải lưu current cost, update/effective timestamp và source inbound transaction reference đủ cho traceability.
15. Company-specific field override không được tạo competing Product Moving Average Cost. P1 Moving Average Cost không được biểu diễn như configurable inventory valuation policy.
16. Action create/change/publish/use Product vẫn chịu `FR-IAM-003`; controlled sensitive change áp dụng thông qua `FR-MDM-003` không bypass Authorization.

**Ranh giới lifecycle và scope**

Nguồn upstream nêu các Product operational state chính nhưng không định nghĩa complete transition matrix, approval requirement cho từng transition, required-field matrix theo item type, policy catalog theo operational use, hoặc uniqueness rule cho mọi alternate identifier. Đây vẫn là controlled decision của Wave C.

**Đầu ra và bằng chứng được lưu giữ**

Hệ thống phải làm cho canonical Product identity, owning Group, current operational status, item type, applicable usage scope, approved identifier và relevant operational attribute có thể resolve. Lịch sử status/scope change phải vẫn trace được theo upstream master-lifecycle rule. Với Product Moving Average Cost, current value, Group, effective/update time và latest inbound transaction làm thay đổi value phải trace được.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-PRD-001-01` | Một Product được duy trì trong Group | Kiểm tra Product identity | Xác định được owning Group, item type và operational policy/context áp dụng | `FR-PRD-001.01` |
| `SCN-PRD-001-02` | Hai Company trong cùng Group được authorize sử dụng một Product | Mỗi Company tham chiếu Product | Cả hai dùng cùng canonical Group Product; không cần competing local Product | `.02`, `.07` |
| `SCN-PRD-001-03` | ERP Product và PLM Engineering Product cùng tồn tại | Một trong hai lifecycle thay đổi | Không tạo implicit cross-domain lifecycle equivalence hoặc silent overwrite | `.03`, `.10` |
| `SCN-PRD-001-04` | Product thiếu required publication attribute hoặc vi phạm approved code-uniqueness rule | Thực hiện publish/use | Publish/use bị từ chối | `.04` |
| `SCN-PRD-001-05` | Weight, dimension hoặc UOM áp dụng | Inventory/Sales consumer đọc dữ liệu | Cùng approved Product attribute được consume nhất quán | `.05` |
| `SCN-PRD-001-06` | Product nằm ngoài applicable Company/channel/market scope | Transaction mới cố gắng sử dụng Product | Việc sử dụng bị từ chối theo controlled usage-scope rule mà không tạo Product master mới | `.06`, `.07` |
| `SCN-PRD-001-07` | Pricing classification được gán/thay đổi | Kiểm tra Product pricing data | Default Selling Price không thay đổi chỉ do classification | `.08` |
| `SCN-PRD-001-08` | Product discontinued/không còn hiệu lực | Transaction mới tham chiếu Product | Việc sử dụng mới bị từ chối trong khi historical reference vẫn có sẵn | `.09` |
| `SCN-PRD-001-09` | Có approved alternate identifier | Resolve bằng bất kỳ approved identifier nào | Tất cả resolve về cùng canonical Product | `.11` |
| `SCN-PRD-001-10` | P1 Product có item type COMBO hoặc GIFT | Thực thi P1 acceptance | Hỗ trợ classification mà không yêu cầu composition/stock/revenue/gift semantics | `.12` |
| `SCN-PRD-001-11` | Eligible inbound transaction cập nhật Moving Average Cost thông qua `FR-INV-001` | Kiểm tra Product cost evidence | Trace được Group, current value, update time và source inbound transaction; không tạo Company cost override | `.13` |

**TBD / các quyết định downstream**

- Controlled Product item-type catalog và required operational attribute/policy theo item type.
- Product lifecycle transition matrix và approval requirement theo từng transition nếu có.
- Product code uniqueness scope/format và barcode/QR/partner/alternate-code uniqueness/collision rule.
- Usage-scope model và precedence khi Company/channel/market scope giao nhau.
- Controlled unit convention cho weight/dimension và validation rule tương ứng.

#### 8.3.2 FR-PRD-002 — Đặc tả chi tiết: Product UOM và conversion

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Product-domain User được ủy quyền để duy trì UOM/conversion; transaction process trong scope consume effective conversion. |
| Trigger | Gán/thay đổi Product base UOM, tạo/thay đổi/end-date Product conversion, hoặc convert transaction quantity. |
| Hard prerequisite | `FR-PRD-001`. |
| Quality reference chi phối | `NFR-DAT-001` đối với quantity precision/rounding. |
| Invariant cốt lõi | Tại một thời điểm, một Product có đúng một base UOM đang hiệu lực. |

**Đầu vào và context**

Conversion record phải xác định Product, from-UOM, to-UOM, conversion direction, positive conversion factor, conversion context khi áp dụng và effective period. Conversion operation phải lưu source quantity cùng đủ context để xác định factor đã sử dụng. Nguồn không định nghĩa reciprocal factor có được auto-derived hay không, chained conversion có được phép hay không, hoặc complete conversion-context taxonomy.

**Quy tắc xử lý và validation**

1. Product phải có đúng một effective base UOM tại mọi thời điểm.
2. Conversion không được có hiệu lực nếu chưa định nghĩa from-UOM, to-UOM, direction, factor `> 0` và thời gian/period hiệu lực.
3. Hai conversion record không được overlap effective period khi chúng cạnh tranh cho cùng Product/from-UOM/to-UOM/conversion context.
4. Transaction mới chỉ được resolve conversion đang hiệu lực cho transaction/effective context.
5. Conversion expired/end-dated không được dùng cho transaction mới, trong khi historical transaction phải lưu conversion context đã áp dụng ban đầu.
6. Conversion phải bảo toàn source quantity trước rounding và làm cho source quantity, conversion factor và converted quantity trace được.
7. Quantity precision và rounding phải tuân theo `NFR-DAT-001`; FR này không được âm thầm loại bỏ source precision trước approved rounding step.
8. Thay đổi Product base UOM hoặc conversion configuration không được rewrite historical transaction quantity/conversion evidence.
9. Authorization để duy trì conversion data vẫn chịu `FR-IAM-003` thông qua common Authorization model; FR này không định nghĩa permission system mới.

**Ranh giới conversion**

Nguồn không định nghĩa reciprocal-factor generation, precedence direct-vs-chained conversion, conversion-context taxonomy, factor precision ngoài quan hệ với NFR, hoặc cách migrate base-UOM change cho open transaction. Các nội dung này cần controlled decision trước trạng thái implementation-ready.

**Đầu ra và bằng chứng được lưu giữ**

Đối với mỗi converted transaction quantity, hệ thống phải làm cho source quantity, source UOM, conversion factor/record, converted quantity, target UOM và effective conversion context trace được. Lịch sử sử dụng conversion hết hạn phải vẫn reproducible từ retained evidence.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-PRD-002-01` | Có Product UOM record trong một thời gian hiệu lực | Resolve base UOM | Đúng một base UOM đang hiệu lực | `FR-PRD-002.01` |
| `SCN-PRD-002-02` | Một conversion được đề xuất | Chạy validation | from-UOM, to-UOM, direction, factor `> 0` và effective period đều bắt buộc | `.02` |
| `SCN-PRD-002-03` | Một effective conversion đã tồn tại cho cùng competing context | Đề xuất một conversion khác có effective period overlap | Competing record overlap bị từ chối | `.03` |
| `SCN-PRD-002-04` | Quantity được convert | Kiểm tra conversion evidence | Source quantity, factor và converted quantity được lưu với precision/rounding behavior theo `NFR-DAT-001` | `.04` |
| `SCN-PRD-002-05` | Conversion đã hết hiệu lực | Transaction mới cố gắng sử dụng và transaction cũ được review | Việc sử dụng mới bị từ chối; historical conversion context vẫn có sẵn | `.05` |

**TBD / các quyết định downstream**

- Conversion-context taxonomy và matching key.
- Reciprocal conversion behavior và việc reciprocal record được lưu hay suy ra.
- Hỗ trợ/precedence của direct-vs-chained conversion.
- Base-UOM change control cho transaction hiện có/open và historical data.
- Internal factor precision và exact rounding point phù hợp `NFR-DAT-001`.

#### 8.3.3 FR-PRD-005 — Đặc tả chi tiết: Product Default Selling Price

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Product/Pricing-adjacent User được ủy quyền duy trì Product default selling-price record; Sales/Pricing resolution consume record này như fallback. Upstream không quy định thêm ownership role cụ thể ngoài Product-domain ownership. |
| Trigger | Tạo/thay đổi/end-date Product Default Selling Price hoặc resolve base selling price khi không tồn tại Pricing Policy có precedence cao hơn đang áp dụng. |
| Hard prerequisites | `FR-PRD-001`, `FR-ORG-004`. |
| Canonical ownership | Product domain, Group level. |
| Các reference không-hard | `FR-MDM-002`, `FR-PRC-001`. |

**Đầu vào và context**

Default selling-price record phải resolve Product, owning Group, price value, currency và thời gian/period hiệu lực. Nguồn yêu cầu history nhưng không định nghĩa overlap được phép, việc một Product có thể đồng thời có nhiều default currency hay không, hoặc exact publication/approval process.

**Quy tắc xử lý và validation**

1. Product Default Selling Price phải được duy trì ở Group level dưới Product-domain ownership.
2. Product không bắt buộc phải thuộc Price List/Pricing Policy mới có default selling price sử dụng được.
3. Mỗi default selling-price record phải xác định currency và thời gian/period hiệu lực, đồng thời các value trước đó vẫn phải trace được trong lịch sử.
4. Các Company được authorize trong Group có thể consume cùng Group default price khi price này applicable với transaction/pricing context.
5. Không được tạo Company-specific default-price field override. Khác biệt giá theo Company phải được biểu diễn bằng Company Pricing Policy theo `FR-PRC-001`.
6. Canonical resolution phải ưu tiên applicable Company hoặc Group Pricing Policy trước Product Default Selling Price theo `FR-PRC-001`.
7. Nếu không có applicable Company/Group Pricing Policy, applicable Product Default Selling Price phải có sẵn như final base-price fallback.
8. Final resolved price source phải trace được tới Product default record cụ thể khi fallback này được sử dụng.
9. `FR-MDM-002` quản trị ownership/override semantics như một reference nhưng không được biến thành hard prerequisite mới tại đây.

**Ranh giới effective-price**

Nguồn không định nghĩa hành vi khi không tồn tại applicable Pricing Policy **và** cũng không tồn tại applicable Product Default Selling Price. Nguồn cũng không định nghĩa cách resolve overlap cùng Product/cùng currency hoặc cardinality của multi-currency default price. Đây là các quyết định mở.

**Đầu ra và bằng chứng được lưu giữ**

Hệ thống phải làm cho Group, Product, price, currency, thời gian hiệu lực và lịch sử default price trace được. Khi default price được dùng trong canonical price resolution, resolved source phải trỏ tới default-price record cụ thể.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-PRD-005-01` | Product không có applicable Pricing Policy nhưng có applicable Group default price | Resolve base price | Product Default Selling Price được trả về như fallback | `FR-PRD-005.01` |
| `SCN-PRD-005-02` | Có applicable Company hoặc Group Pricing Policy | Resolve base price | Policy price thắng theo `FR-PRC-001`, và final source trace được | `.02` |
| `SCN-PRD-005-03` | Có historical default price | Kiểm tra point-in-time/default record | Trace được Group, currency, thời gian hiệu lực và historical value | `.03` |
| `SCN-PRD-005-04` | Company cần selling price khác | Thực hiện configuration | Sử dụng Company Pricing Policy; không tạo Company-specific Product default-price field override | `.04` |

**TBD / các quyết định downstream**

- Default-price cardinality theo Product/currency và rule cho overlapping effective period.
- Việc/cách nhiều default currency cùng tồn tại và cách match transaction currency trước FX conversion.
- Publication/change approval policy cho default price khi không được controlled master-change rule khác bao phủ.
- Xử lý tường minh khi không resolve được applicable Company Policy, Group Policy hoặc Product Default Selling Price.

### 8.4 Customer & CRM

#### 8.4.1 FR-CUS-001 — Đặc tả chi tiết: Customer Master, hồ sơ và lifecycle

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Customer-domain User/business owner được ủy quyền duy trì Customer; Company/process được authorize trong Group có thể consume canonical Customer. |
| Trigger | Tạo/thay đổi Customer, quản lý address/contact/status data, hoặc tham chiếu Customer trong approved P1 business process. |
| Hard prerequisites | `FR-MDM-001`, `FR-MDM-002`. |
| Canonical ownership | Customer domain; Group Master. |
| Customer category được upstream nêu rõ | Retail customer, agent, enterprise/business customer, distributor. |

**Đầu vào và context**

Customer record phải lưu owning Group, unique code trong applicable scope, business owner, customer category/group, contact information liên quan, transactional address, tax code khi áp dụng, status/lifecycle và nhiều effective contact/address. Address reference phải consume controlled address dataset/rule từ `FR-MDM-001` khi áp dụng. Nguồn không định nghĩa complete Customer-state transition model, uniqueness scope ngoài applicable policy, hoặc mandatory-field matrix theo customer type.

**Quy tắc xử lý và validation**

1. Customer phải được duy trì như canonical Group Master do Customer domain sở hữu.
2. Customer phải xác định owning Group, unique code trong approved applicable scope và business owner.
3. Các Company được authorize trong cùng Group phải tái sử dụng cùng Customer Master thay vì tạo competing local Customer chỉ để sử dụng tại Company đó.
4. Customer profile phải hỗ trợ các customer category upstream và lưu contact, address, tax, grouping và status information phù hợp mà category/process áp dụng yêu cầu.
5. Nhiều contact và transaction address có thể liên kết với Customer; record current/effective phải phân biệt được với record historical/non-effective.
6. Lịch sử Customer lifecycle/status phải được lưu từ prospect/potential status đến cessation of trading; nguồn không định nghĩa mọi intermediate state hoặc transition.
7. Customer profile query phải trình bày một unified canonical record có trạng thái hiện tại cùng lịch sử được lưu và effective address/contact.
8. Action create/change/use Customer vẫn chịu `FR-IAM-003`; FR này không tạo Company ownership chỉ vì Company consume Group Master.
9. P1 FR này không kích hoạt P2 credit terms/limits, Customer interaction history, returns/warranty hoặc P3 consent/privacy capability chỉ vì các capability phase sau đó tham chiếu Customer.

**Ranh giới lifecycle**

Nguồn thiết lập lifecycle từ potential Customer đến discontinued trading nhưng không định nghĩa complete state machine, approval model, merge/duplicate-resolution behavior, default-contact/address rule hoặc mandatory field theo Customer category. Đây vẫn là controlled decision.

**Đầu ra và bằng chứng được lưu giữ**

Hệ thống phải làm cho canonical Customer identity, owning Group, code, business owner, trạng thái hiện tại, status history, effective address và effective contact trace được. Việc sử dụng bởi Company được authorize phải tham chiếu cùng canonical Customer identity.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-CUS-001-01` | Một Customer record tồn tại | Kiểm tra identity/governance | Xác định được owning Group, unique code trong applicable scope và business owner | `FR-CUS-001.01` |
| `SCN-CUS-001-02` | Hai Company trong Group giao dịch với cùng Customer | Chọn Customer | Cả hai tham chiếu cùng canonical Customer; không cần competing local master | `.02` |
| `SCN-CUS-001-03` | Customer status/contact/address thay đổi theo thời gian | Review profile | Phân biệt và trace được status hiện tại, status history và contact/address đang hiệu lực | `.03` |

**TBD / các quyết định downstream**

- Customer lifecycle state/transition và publication/inactivation approval policy.
- Customer code format và uniqueness scope.
- Mandatory field theo Customer category/type.
- Contact/address cardinality, effective-dating và default/primary-selection rule.
- Duplicate Customer detection/merge nằm ngoài P1 trừ khi được phê duyệt riêng qua MDM capability phase sau.

### 8.5 Supplier

#### 8.5.1 FR-SUP-001 — Đặc tả chi tiết: Supplier Master và sensitive attributes

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Supplier-domain User/business owner được ủy quyền; proposer và verifier/approver cho sensitive change; Purchase consumer tham chiếu canonical Supplier. Upstream không định nghĩa tên role cụ thể. |
| Trigger | Tạo/thay đổi Supplier, thay đổi collaboration status, tham chiếu Supplier trong Purchase, hoặc đề xuất sensitive change đối với bank account, tax code hoặc payment terms. |
| Hard prerequisites | `FR-MDM-001`, `FR-MDM-002`, `FR-MDM-003`. |
| Canonical ownership | Supplier domain; Group Master. |
| Sensitive attribute upstream bắt buộc rõ | Bank account, tax code, payment terms. |

**Đầu vào và context**

Supplier phải lưu owning Group, immutable internal identity, business code/RD Code theo approved identification policy, collaboration status và các operational attribute mà P1 procurement yêu cầu. Sensitive change yêu cầu proposer, verifier/approver, decision, effective date và before/after history/evidence thông qua controlled master-change/workflow mechanism.

**Quy tắc xử lý và validation**

1. Supplier phải được duy trì như canonical Group Master do Supplier domain sở hữu.
2. Supplier phải có immutable internal identity và business code/RD Code theo approved identification policy.
3. Các Company được authorize trong cùng Group phải tái sử dụng cùng canonical Supplier thay vì tạo competing Company Supplier master chỉ để phục vụ procurement.
4. Purchase phải consume Supplier trong sourcing, Purchase Order và receiving; Purchase không được trở thành canonical owner của Supplier.
5. Collaboration status phải xác định Supplier có được dùng cho commitment mới hay không. Supplier đã ngừng collaboration không được dùng để tạo procurement commitment mới, trong khi historical reference vẫn có sẵn.
6. Thay đổi bank account, tax code hoặc payment terms của Supplier phải được xem là sensitive master-data change trong phạm vi upstream quy định rõ.
7. Mỗi sensitive change như vậy phải tuân theo `FR-MDM-003`, gồm controlled approval bắt buộc và Shared Workflow trước khi changed value có hiệu lực.
8. Sensitive-change evidence phải xác định proposer, verifier/approver, decision, effective date và historical before/after value/evidence đủ để trace change.
9. Nếu Workflow chưa đạt state cho phép hiệu lực, proposed sensitive change không được dùng như effective Supplier value cho business processing mới.
10. Supplier maintenance và approval operation vẫn chịu `FR-IAM-003`; Shared Workflow không cấp underlying permission.
11. Capability P2 supplier qualification/approved-supplier-list và performance evaluation không được kích hoạt bởi P1 Supplier Master specification này.

**Ranh giới lifecycle/approval**

Nguồn yêu cầu collaboration status và sensitive-change approval nhưng không định nghĩa complete Supplier lifecycle, workflow level, verification role separation, evidence checklist hoặc việc field Supplier nào khác là sensitive. Đây vẫn là controlled decision; ba nhóm sensitive attribute được nêu là mandatory P1 coverage.

**Đầu ra và bằng chứng được lưu giữ**

Hệ thống phải làm cho Supplier canonical identity, Group, business/RD code, collaboration status và Company usage trace được. Đối với thay đổi bank-account, tax-code và payment-term, proposer, verifier/approver, decision, effective date và before/after history phải truy xuất được cùng Workflow decision trail liên quan.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-SUP-001-01` | Một Supplier tồn tại | Kiểm tra Supplier identity | Xác định được Group, immutable internal identity, approved business/RD code và collaboration status | `FR-SUP-001.01` |
| `SCN-SUP-001-02` | Nhiều Company trong Group sử dụng một Supplier | Procurement tham chiếu Supplier | Sử dụng cùng canonical Group Supplier mà không tạo competing local master | `.02`, `.03` |
| `SCN-SUP-001-03` | Supplier đã ngừng collaboration | Cố gắng tạo procurement commitment mới | Commitment mới bị từ chối trong khi historical transaction vẫn có sẵn | `.04` |
| `SCN-SUP-001-04` | Bank account, tax code hoặc payment terms được thay đổi | Kiểm tra change evidence | Trace được proposer, verifier/approver, decision, effective date và before/after history | `.05` |
| `SCN-SUP-001-05` | Named sensitive change yêu cầu approval và Workflow chưa effective/approved | Business processing mới cố gắng dùng proposed value | Proposed value chưa có hiệu lực | `.06` |

**TBD / các quyết định downstream**

- Complete Supplier collaboration lifecycle và allowed transition.
- Supplier code/RD Code allocation rule theo approved identification policy chuyên biệt.
- Workflow Type/rule, approval level và evidence checklist cho ba sensitive attribute class được nêu.
- Việc field Supplier bổ sung có được phân loại sensitive trong P1 qua controlled policy riêng hay không.

### 8.6 Pricing & Promotion

#### 8.6.1 FR-PRC-001 — Đặc tả chi tiết: Pricing Policy và canonical price resolution

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Tác nhân nghiệp vụ | Pricing-domain User/business owner được ủy quyền duy trì Group/Company Pricing Policy; transaction process được authorize yêu cầu price resolution. Approval actor chỉ áp dụng khi approval rule liên quan yêu cầu Workflow. |
| Trigger | Tạo/thay đổi/end-date Pricing Policy/Price List, evaluate applicable policy cho pricing context, hoặc resolve base price cho Sales transaction. |
| Hard prerequisite | `FR-PRD-005`. |
| Các reference không-hard | `FR-CUS-001`, `FR-CTL-004`, `FR-FIN-005`. |
| Canonical precedence upstream cố định rõ | Company Pricing Policy > Group Pricing Policy > Product Default Selling Price. |

**Đầu vào và context**

Price resolution phải có đủ context để resolve Product, owning Group, transaction Company, currency, effective date/time và business condition được applicable Pricing Policy khai báo. Customer context chỉ được đưa vào khi condition yêu cầu và context đó tồn tại. Khi cần currency conversion, exchange-rate context từ `FR-FIN-005` là bắt buộc cho conversion operation đó. Nguồn không định nghĩa complete Pricing condition catalog, same-level tie-break logic, overlap rule hoặc hành vi khi không resolve được price source.

**Quy tắc xử lý và validation**

1. Pricing Policy/Price List phải có thể được duy trì ở Group hoặc Company ownership level, với owner, applicability scope/condition, currency, thời gian hiệu lực và lịch sử.
2. Company không được edit/overwrite Group Pricing Policy object để thể hiện local pricing. Khác biệt theo Company phải được biểu diễn bằng Company Pricing Policy riêng.
3. Pricing Policy chỉ cần khai báo các trường hợp khác Product Default Selling Price; Product không cần dedicated policy record để fallback tồn tại.
4. Trước khi policy tham gia resolution, policy phải valid/effective cho pricing context, currency và declared business condition.
5. Canonical resolution phải xét applicable Company Pricing Policy trước, sau đó applicable Group Pricing Policy, rồi applicable Product Default Selling Price.
6. Nếu resolve được ít nhất một applicable Company Pricing Policy cho pricing context/currency, Group Pricing Policy không được chọn cho cùng price decision.
7. Nếu không resolve được applicable Company Pricing Policy nhưng resolve được applicable Group Pricing Policy, Group Pricing Policy phải được chọn trước Product Default Selling Price.
8. Nếu không resolve được applicable Company/Group Pricing Policy, hệ thống phải dùng Product Default Selling Price khi default đó applicable với pricing context/currency.
9. Final price decision phải lưu đủ explanation để xác định selected source object và condition/context khiến source được áp dụng.
10. Pricing Policy có thể tồn tại mà không có Customer reference. Customer-specific condition chỉ được evaluate khi pricing context cung cấp Customer context liên quan.
11. Shared Workflow không phải hard prerequisite vô điều kiện của Pricing. Nếu approval rule/policy áp dụng yêu cầu business approval, Pricing Policy không được có hiệu lực trước khi Workflow `FR-CTL-004` đạt state cho phép hiệu lực. Nếu không yêu cầu approval, không được tạo dummy Workflow instance chỉ để thỏa FR này.
12. Price source và transaction phải lưu currency. Khi áp dụng conversion, `FR-FIN-005` cung cấp exchange-rate context; original price/value, original currency, FX rate/source/effective time và converted value/currency phải trace được.
13. Currency conversion không được tạo hoặc mutate competing Pricing Policy; đây là conversion của selected price/value trong transaction context.
14. Transaction phải phân biệt được canonical base price/source với discount/promotion processing về sau. Behavior promotion/discount/coupon của `BR-PRC-002` nằm ngoài P1 FR này.
15. Pricing administration và price resolution access vẫn chịu `FR-IAM-003` khi áp dụng user-facing hoặc protected action.

**Bảng quyết định canonical price resolution**

| Applicable Company Policy | Applicable Group Policy | Applicable Product Default Price | Kết quả canonical source |
|---|---|---|---|
| Có | Bất kỳ | Bất kỳ | Company Pricing Policy |
| Không | Có | Bất kỳ | Group Pricing Policy |
| Không | Không | Có | Product Default Selling Price |
| Không | Không | Không | **Upstream chưa định nghĩa; cần controlled behavior trước trạng thái implementation-ready** |

Bảng chỉ cố định cross-level precedence. Bảng **không** chọn giữa nhiều applicable Company Policy hoặc giữa nhiều applicable Group Policy. Same-level ambiguity phải được resolve bằng controlled rule trước implementation.

**Đầu ra và bằng chứng được lưu giữ**

Resolved price phải xác định final price/value, selected source type và source record, applicable ownership level, currency, effective context và condition explanation liên quan. Khi áp dụng FX, original/converted monetary context cùng rate record/source/effective time phải được lưu. Lịch sử Pricing Policy phải trace được qua các effective period.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-PRC-001-01` | Một Pricing Policy tồn tại | Kiểm tra governance/effective data | Trace được ownership level, owner, scope/condition, currency, thời gian hiệu lực và lịch sử | `FR-PRC-001.01` |
| `SCN-PRC-001-02` | Company-specific pricing khác Group | Cấu hình Policy | Tạo Company Pricing Policy riêng; Group Policy không bị ghi đè | `.02` |
| `SCN-PRC-001-03` | Cả applicable Company và Group Policy cùng tồn tại | Resolve price | Chọn Company Pricing Policy | `.03`, `.06` |
| `SCN-PRC-001-04` | Không có Company Policy áp dụng nhưng có applicable Group Policy | Resolve price | Chọn Group Pricing Policy | `.07` |
| `SCN-PRC-001-05` | Không có Company/Group Policy áp dụng và có applicable Product Default Selling Price | Resolve price | Chọn Product Default Selling Price | `.04`, `.08` |
| `SCN-PRC-001-06` | Bất kỳ canonical source nào được chọn | Kiểm tra decision evidence | Giải thích được selected source record và applicable condition/context | `.05`, `.09` |
| `SCN-PRC-001-07` | Selected source currency cần conversion | Convert transaction price | Original value/currency, FX rate/source/effective time và converted value vẫn trace được; không tạo competing policy | `.10` |
| `SCN-PRC-001-08` | Pricing Policy không có Customer-specific condition | Customer context không có | Policy vẫn có thể tồn tại và được evaluate theo non-Customer condition | `.11` |
| `SCN-PRC-001-09` | Pricing Policy sử dụng Customer-specific condition | Chạy price resolution | Condition đó chỉ được evaluate khi Customer context liên quan tồn tại | `.11` |
| `SCN-PRC-001-10` | Approval rule yêu cầu Workflow | Workflow chưa đạt effective state | Pricing Policy không có hiệu lực | `.12` |
| `SCN-PRC-001-11` | Không approval rule nào yêu cầu Workflow | Pricing Policy thỏa các effective condition khác | Policy có thể có hiệu lực mà không cần dummy Workflow | `.12` |

**TBD / các quyết định downstream**

- Complete Pricing condition catalog và matching semantics.
- Same-level tie-break/precedence khi nhiều Company Policy cùng match hoặc nhiều Group Policy cùng match pricing context/currency.
- Rule cho overlapping effective period và việc ambiguity bị ngăn tại configuration time hay được resolve deterministic.
- Hành vi khi không tồn tại Company Policy, Group Policy hoặc applicable Product Default Selling Price.
- Pricing Policy approval-rule catalog và Workflow mapping khi cần approval.
- Currency matching/conversion trigger và target-currency rule quanh `FR-FIN-005`.

### 8.7 Register quyết định chưa chốt của Wave C

Các item sau được chủ ý để mở vì nguồn hiện tại chưa định nghĩa. Chúng phải được resolve bằng controlled policy, FRS refinement hoặc design; implementation không được tự âm thầm thiết lập business semantics mới. Classification tuân theo Section 1.4 và bản thân classification không resolve decision.

| ID | Quyết định chưa chốt | FR bị ảnh hưởng | Class | Owner | Artifact đích | Gate đến hạn | Trạng thái |
|---|---|---|---|---|---|---|---|
| `WC-TBD-001` | Product item-type catalog cùng required attribute và operational policy matrix theo item type | `FR-PRD-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Product policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-002` | Product lifecycle transition matrix và approval requirement | `FR-PRD-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Product policy / FRS / Workflow config nếu áp dụng | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-003` | Product code và alternate-identifier uniqueness/collision rule | `FR-PRD-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Identification policy + DES | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-004` | Product usage-scope model và precedence cho giao nhau Company/channel/market | `FR-PRD-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Product policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-005` | UOM conversion-context taxonomy, reciprocal/chained conversion behavior và base-UOM-change control | `FR-PRD-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Product/UOM policy + DES | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-006` | Default-price cardinality, effective-period overlap rule và multi-currency behavior | `FR-PRD-005` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Product/Pricing policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-007` | Xử lý tường minh khi không resolve được canonical price source | `FR-PRD-005`, `FR-PRC-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Pricing policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-008` | Customer lifecycle state/transition và publication/inactivation approval rule | `FR-CUS-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Customer policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-009` | Customer code scope, mandatory field theo category, contact/address effective/default rule | `FR-CUS-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Customer policy / FRS + DES | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-010` | Supplier collaboration lifecycle và new-commitment eligibility transition | `FR-SUP-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Supplier policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-011` | Supplier sensitive-change Workflow mapping, level và evidence checklist | `FR-SUP-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Supplier policy + Workflow config | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-012` | Complete Pricing condition catalog và matching semantics | `FR-PRC-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Pricing policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-013` | Same-level Pricing Policy tie-break và overlapping-policy ambiguity control | `FR-PRC-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Pricing policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-014` | Pricing Policy approval-rule catalog và conditional Workflow mapping | `FR-PRC-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Pricing policy + Workflow config | `PROVISIONAL_READY` | `OPEN` |
| `WC-TBD-015` | Pricing currency matching/conversion trigger và target-currency rule quanh `FR-FIN-005` | `FR-PRC-001`, `FR-PRD-005` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Pricing/FX policy / FRS | `PROVISIONAL_READY` | `OPEN` |

### 8.8 Gate provisional implementation-readiness của Wave C

Wave C có thể chuyển từ **detailed draft** sang **provisional implementation-ready** chỉ khi:

1. Cả sáu parent FR Wave C vẫn trace về upstream BR và mọi acceptance-oriented child FR đều có explicit verification evidence path theo Section 1.3.
2. Không còn Wave C TBD nào có `class=BUSINESS_DECISION`, `due_gate=PROVISIONAL_READY` và `status!=APPROVED`; blocker set được sinh từ controlled register.
3. Product/Customer/Supplier vẫn là canonical Group Master của owning domain; không Company consumer hoặc MDM control nào tạo competing master.
4. P1 COMBO/GIFT vẫn chỉ là classification và không kéo composition, inventory/revenue hoặc gift semantics từ `BR-PRD-004` vào P1.
5. Product Moving Average Cost vẫn được hệ thống suy ra từ `FR-INV-001`, ở Group level và không override được ở Company level; configurable valuation policy từ phase sau không được kéo vào.
6. Thay đổi Supplier bank-account, tax-code và payment-term không được có hiệu lực trước controlled approval/Workflow decision bắt buộc.
7. Pricing implementation phải enforce approved cross-level precedence mà không tự âm thầm tạo same-level policy precedence; mọi same-level rule phải controlled và reviewable.
8. Pricing phải hoạt động được không có Customer-specific condition và không có unconditional Workflow, đồng thời consume Customer/Workflow đúng khi condition khai báo yêu cầu.
9. FX conversion, khi dùng, phải bảo toàn original/converted monetary context theo `FR-FIN-005` và không mutate selected Pricing Policy.
10. Có thể viết UAT/DES-review evidence từ controlled rule thay vì unstated implementation assumption.
11. Review phải sinh blocking-ID snapshot từ register như non-normative audit evidence.

**v0.5.1 blocking snapshot (chỉ là report):** `WC-TBD-001`, `WC-TBD-002`, `WC-TBD-003`, `WC-TBD-004`, `WC-TBD-005`, `WC-TBD-006`, `WC-TBD-007`, `WC-TBD-008`, `WC-TBD-009`, `WC-TBD-010`, `WC-TBD-011`, `WC-TBD-012`, `WC-TBD-013`, `WC-TBD-014`, `WC-TBD-015`.
## 9. Đặc tả chức năng chi tiết Wave D

### 9.1 Hợp đồng đặc tả chi tiết

Wave D tuân theo thứ tự đặc tả đã được phê duyệt cho **Giao dịch vận hành (Operational Transactions)** và khép lại phần chi tiết chức năng còn thiếu của P1. Wave này chi tiết hóa các parent requirement P1 hiện có gồm `FR-PUR-001`, `FR-PUR-002`, `FR-PUR-003`, `FR-INV-001`, `FR-INV-002`, `FR-INV-003`, `FR-SAL-001`, `FR-SAL-002` và requirement nền tảng P1 hỗ trợ là `FR-FIN-005`.

Wave D không tạo phase mới và không mở rộng P1 thành Finance đầy đủ, Manufacturing, Logistics, Integration, Returns, truy xuất genealogy lot/serial hoặc định giá tồn kho có thể cấu hình. Wave D kế thừa hợp đồng tài liệu và các quy ước đã thiết lập ở Wave A-C. Ngoài ra:

- Các Business Transaction P1 tiếp tục thuộc sở hữu của đúng một Company theo `AR-TXN-001`; Group được suy ra thông qua Company sở hữu và không bao giờ trở thành chủ sở hữu trực tiếp của transaction.
- Purchase P1 kết thúc tại receiving được kiểm soát. Supplier invoice matching, phân bổ landed cost và Accounts Payable nằm ngoài P1.
- Inventory P1 cung cấp hành vi chuẩn về quantity/status/reservation/movement/count-adjustment cùng Product Moving Average Cost ở cấp Group, do hệ thống sinh. P1 không thiết lập capability định giá tồn kho có thể cấu hình của phase sau.
- Sales P1 cung cấp capture Quotation/Sales Order và availability/ATP dựa trên tồn kho hiện tại. Credit control, fulfillment lifecycle, returns/RMA/warranty và Promotion/Discount Policy Engine nằm ngoài P1.
- P1 ATP được định nghĩa chặt là `usable on-hand - current reservation` trong Company/inventory scope đã resolve. Incoming PO, planned Production, forecast và các nguồn cung tương lai khác không làm tăng P1 ATP.
- Lot/serial/expiry có thể được lưu như thuộc tính tùy chọn/cơ bản khi có dữ liệu, nhưng P1 không yêu cầu genealogy/traceability end-to-end.
- Purchase Receipt là một nguồn inbound hợp lệ cho Product Moving Average Cost cấp Group khi đã được ghi nhận chính thức. Các chi tiết về eligibility/value normalization mà nguồn hiện tại chưa định nghĩa vẫn là quyết định được kiểm soát, không phải giả định implementation.
- Currency conversion trong P1 chỉ là dịch vụ nền tảng. Nó không hàm ý realized/unrealized FX accounting, AR/AP, tax, financial close hoặc financial posting nếu các capability đó chưa được phê duyệt riêng.
- Các chi tiết chính xác về UI screen, API contract, persistence schema, document-number format, reservation allocation algorithm, negative-stock threshold, sourcing threshold, receiving tolerance và exchange-rate precedence value tiếp tục là quyết định downstream được kiểm soát, trừ khi nguồn đã quy định rõ.

### 9.2 Các invariant và ranh giới chi phối Wave D

| Invariant / ranh giới | Hệ quả đối với Wave D |
|---|---|
| `AR-TXN-001` | Mỗi Business Transaction thuộc Purchase, Inventory và Sales phải resolve đúng một Company sở hữu, business/effective timestamp, lifecycle state và actor/system context chịu trách nhiệm. |
| `FR-ORG-002` | Branch/Plant/Warehouse dùng trong transaction phải resolve bên trong hierarchy của Company sở hữu; movement cross-Company không được ngụy trang thành internal transfer. |
| `FR-ORG-004` | Company business timezone/currency/calendar cung cấp business-date, currency và business-day context; ownership của financial period không được kéo vào P1. |
| `FR-IAM-003` | Các action view/create/submit/approve/record/adjust/export/administer yêu cầu Resource/Action authorization tương ứng trong context đã resolve. |
| `FR-CTL-001` | Các P1 document duy trì lifecycle, numbering và quan hệ source/replacement/adjustment được kiểm soát khi áp dụng. |
| `FR-CTL-002` | Responsibility, bằng chứng business event quan trọng và reconciliation áp dụng cho các source capability P1; source class ở phase sau không làm phát sinh dummy event. |
| `FR-CTL-004` | Approval được dùng khi policy P1 áp dụng yêu cầu; Workflow không bao giờ cấp access Permission. |
| `FR-AUD-001` | Các thay đổi business/data quan trọng phải truy vết được actor, timestamp, before/after context, reason và approval/attachment khi áp dụng. |
| `FR-PRD-002` / `NFR-DAT-001` | Quantity normalization/conversion phải giữ source quantity và áp dụng precision/rounding được kiểm soát. |
| `FR-PRC-001` | Sales base price được resolve theo canonical Pricing precedence, không bằng logic ad-hoc ở transaction. |
| `FR-FIN-005` | Currency conversion phải giữ original và converted amount/currency cùng rate reference/effective context thực tế đã dùng. |
| Ranh giới Purchase P1 | Invoice matching, landed cost và AP không phải nghĩa vụ P1. |
| Ranh giới Inventory P1 | Genealogy lot/serial end-to-end, configurable valuation policy và intercompany movement không phải nghĩa vụ P1. |
| Ranh giới Sales P1 | Credit control, returns/RMA, fulfillment lifecycle và automated promotion/tax engine không phải nghĩa vụ P1. |

### 9.3 Purchase

#### 9.3.1 FR-PUR-001 — Đặc tả chi tiết: Purchase Request và sourcing

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | Requester được ủy quyền; User sourcing/procurement được ủy quyền; approver được ủy quyền khi policy áp dụng yêu cầu approval. Tên role và approval level cụ thể chưa được upstream định nghĩa. |
| Trigger | Một nhu cầu nghiệp vụ đối với Stock Item, Non-stock Item, Service hoặc Expense được ghi nhận; sourcing comparison có thể được yêu cầu theo policy/threshold/category áp dụng. |
| Hard prerequisites | `FR-ORG-002`, `FR-SUP-001`. |
| Conditional consumers | Product Master chỉ khi purchase-line type/policy yêu cầu Product; Shared Workflow chỉ khi control áp dụng yêu cầu approval. |
| Explicit exclusions | Budget control không thuộc FR này; RFQ/quotation comparison không bắt buộc khi direct-purchase policy cho phép bỏ qua. |

**Đầu vào và context**

Purchase Request phải lưu đủ context để xác định requester, Company sở hữu/cost-bearing organizational context, need date/time, business reason, purchase-line type và decision/lifecycle status. Supplier có thể chưa có tại thời điểm tạo request. Product reference chỉ bắt buộc theo line type/policy. Khi sourcing comparison được yêu cầu, request/sourcing context phải lưu quotation hoặc comparison evidence cần thiết để giải thích quyết định chọn Supplier.

**Quy tắc xử lý và validation**

1. Mỗi Purchase Request phải resolve đúng một Company sở hữu. Organization Context dùng cho requesting/cost responsibility phải resolve bên trong Company đó theo `FR-ORG-002`.
2. Mỗi request line phải được phân loại thành một trong các upstream line class: `Stock Item`, `Non-stock Item`, `Service` hoặc `Expense`.
3. Purchase Request có thể được tạo trước khi chọn Supplier. Việc chưa có Supplier lúc tạo request không tự làm request không hợp lệ.
4. Supplier phải có trước khi hình thành purchasing commitment nếu commitment yêu cầu Supplier; việc hình thành commitment được quản lý bởi `FR-PUR-002`.
5. Product Master chỉ bắt buộc khi line-type/policy áp dụng yêu cầu Product. Service/Expense line phải được phép không có Product Master, trừ khi controlled policy quy định rõ ngược lại.
6. Nếu line tham chiếu Product, Product phải là canonical Product hợp lệ/đang hiệu lực và được phép dùng trong Company/usage context đã resolve theo `FR-PRD-001`.
7. Sourcing policy có thể yêu cầu RFQ/quotation comparison theo controlled policy, threshold hoặc purchase category. Ma trận threshold/category chi tiết chưa được upstream định nghĩa.
8. Khi quotation comparison được yêu cầu, hệ thống phải lưu đủ comparison evidence về price, quality, delivery time, delivery conditions và payment terms theo mức áp dụng cho quyết định.
9. Quyết định chọn Supplier khi bắt buộc comparison phải xác định được người chịu trách nhiệm ra quyết định và evidence/basis đã dùng.
10. Khi controlled direct-purchase policy cho phép mua trực tiếp, quy trình phải tiếp tục mà không tạo RFQ/quotation giả chỉ để thỏa một workflow chung.
11. Direct-purchase eligibility phải xác định được từ controlled policy; điều kiện policy cụ thể là một quyết định mở của Wave D.
12. Các action create/change/submit/decide của Purchase Request phải tuân theo `FR-IAM-003`; approval khi cần phải dùng cấu hình `FR-CTL-004` áp dụng và không được cấp thêm access.
13. Các thay đổi quan trọng của request phải giữ audit/history evidence theo `FR-AUD-001` và các rule lifecycle/numbering của `FR-CTL-001` khi áp dụng.
14. FR này không được hàm ý budget approval/control. Nếu một Budget Domain/Integration Requirement tương lai được phê duyệt, nghĩa vụ của nó bắt đầu từ scope được kiểm soát đó, không phải bằng cách diễn giải lại P1 Purchase Request.

**Bảng quyết định sourcing**

| Sourcing policy áp dụng | Quotation comparison evidence | P1 path được phép |
|---|---|---|
| Yêu cầu RFQ/comparison | Có và decision basis được lưu giữ | Tiếp tục sang chọn Supplier / commitment, tùy các control khác |
| Yêu cầu RFQ/comparison | Thiếu | Không coi việc chọn Supplier là policy-compliant; cần controlled resolution |
| Cho phép direct purchase | Không có | Direct path có thể tiếp tục; không tạo RFQ/comparison giả |
| Không resolve được policy applicability | Bất kỳ | **Chưa được upstream định nghĩa; phải có controlled behavior trước trạng thái implementation-ready** |

**Đầu ra và bằng chứng được lưu giữ**

Purchase Request phải cung cấp request identity/reference, Company sở hữu/context, requester, reason, need date, line classification, Product khi bắt buộc, Supplier đã chọn khi có, sourcing/comparison basis khi bắt buộc, decision/lifecycle status và actor/timestamp history liên quan.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-PUR-001-01` | User được ủy quyền ghi nhận một nhu cầu nghiệp vụ | Purchase Request được lưu | Xác định được requester, Company/cost context, need time, line type và decision status | `FR-PUR-001.01` |
| `SCN-PUR-001-02` | Supplier chưa được chọn | Request được tạo | Request có thể tồn tại mà chưa có Supplier; Supplier được hoãn tới khi commitment yêu cầu | `.02` |
| `SCN-PUR-001-03` | Policy của Service/Expense line không yêu cầu Product | Line được ghi nhận | Line được chấp nhận mà không có Product Master | `.03` |
| `SCN-PUR-001-04` | Line policy yêu cầu Product | Line được ghi nhận nhưng không có Product hợp lệ | Line không đáp ứng yêu cầu Product của policy | `.03` |
| `SCN-PUR-001-05` | Sourcing policy yêu cầu quotation comparison | Supplier được chọn | Comparison/basis và người chịu trách nhiệm ra quyết định được lưu giữ | `.04` |
| `SCN-PUR-001-06` | Direct-purchase policy áp dụng | Request chuyển sang purchase commitment | Quy trình tiếp tục mà không cần RFQ/quotation comparison giả | `.05` |

**TBD / các quyết định downstream**

- Ma trận purchase-line policy xác định trường hợp Product bắt buộc theo `Stock Item`/`Non-stock Item`/`Service`/`Expense`.
- Sourcing policy applicability, RFQ threshold/category và minimum comparison evidence.
- Direct-purchase eligibility, exception handling và approval mapping nếu có.
- Ma trận transition của Purchase Request lifecycle và rule cancellation/change.
- Required fields và quantity/value semantics theo purchase-line class.

#### 9.3.2 FR-PUR-002 — Đặc tả chi tiết: Purchase Order và cam kết mua

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | Procurement User được ủy quyền; approver được ủy quyền khi policy yêu cầu; Supplier là business party được tham chiếu. |
| Trigger | Một purchasing commitment được ủy quyền được tạo từ direct-purchase path hợp lệ hoặc từ quotation/Supplier-selection evidence bắt buộc. |
| Hard prerequisites | `FR-PUR-001`, `FR-SUP-001`. |
| Explicit exclusions | Budget control; Supplier invoice matching; landed-cost allocation; Accounts Payable. |

**Đầu vào và context**

Purchase Order phải lưu Supplier, Company sở hữu, purchase-line type, source Purchase Request khi áp dụng, quotation/Supplier-selection evidence khi policy yêu cầu, quantity hoặc value phù hợp với line type, price, tax value/context đã capture, và delivery/performance terms. Nguồn chưa định nghĩa tax-calculation automation, budget checking, tolerance rule, PO change-order semantics hoặc ma trận mandatory field chính xác ở line level.

**Quy tắc xử lý và validation**

1. Mỗi Purchase Order phải resolve đúng một Company sở hữu và một Supplier cho commitment context.
2. Supplier phải tham chiếu canonical Supplier Master và đủ điều kiện tạo commitment mới theo Supplier lifecycle/state hiện hành được định nghĩa bởi `FR-SUP-001`.
3. PO line phải lưu purchase-line classification và quantity/value semantics phù hợp với line đó. Semantics cụ thể theo line class vẫn là quyết định được kiểm soát.
4. Khi PO được tạo từ Purchase Request, source linkage phải được giữ đủ để reconcile commitment với nhu cầu ban đầu.
5. Direct PO phải được nhận diện rõ là direct-purchase path và chỉ được phép khi controlled sourcing policy áp dụng cho phép.
6. Khi sourcing policy yêu cầu quotation comparison/Supplier selection, PO phải giữ linkage/evidence bắt buộc trước khi commitment được coi là compliant.
7. PO price, tax value/context và delivery/performance terms phải được lưu như một phần của commitment đã xác nhận. P1 không vì vậy mà tạo operational Tax Engine; automated tax rule vẫn phụ thuộc capability Finance tax được phê duyệt sau này.
8. Hệ thống không được suy ra budget availability hoặc budget approval từ PO approval/creation. Budget nằm ngoài requirement P1 này.
9. Các action thuộc PO lifecycle phải tuân theo `FR-CTL-001`; mọi business approval áp dụng phải sử dụng `FR-CTL-004` và vẫn chịu `FR-IAM-003`.
10. Các thay đổi/cancellation/replacement ảnh hưởng commitment hiện hữu phải giữ relationship/history đủ cho receiving và audit downstream; cơ chế amendment cụ thể chưa được upstream định nghĩa.
11. Receiving phải consume PO/commitment đã xác nhận thông qua `FR-PUR-003`; receipt quantity/value effect không được âm thầm viết lại historical commitment nếu không có controlled change evidence.

**Bảng đường đi của commitment**

| Source path | Điều kiện policy | Commitment evidence bắt buộc |
|---|---|---|
| Quotation-comparison path | Bắt buộc comparison | Purchase Request/source context + Supplier đã chọn + comparison/decision evidence bắt buộc |
| Direct-purchase path | Cho phép direct purchase | Purchase Request/source context khi áp dụng + Supplier + nhận diện direct-purchase rõ ràng/policy basis |
| Direct-purchase path | Không cho phép direct purchase | Commitment path không policy-compliant |
| Không resolve được path/policy | Không xác định | **Phải có controlled resolution; implementation không được tự chọn path** |

**Đầu ra và bằng chứng được lưu giữ**

PO phải cung cấp Company sở hữu, Supplier, source/request relationship khi áp dụng, line class, quantity/value, price, tax/context, delivery/performance terms, sourcing path/evidence và commitment status/history hiện tại.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-PUR-002-01` | Có purchase need hợp lệ và Supplier | PO được xác nhận | Commitment có thể reconcile về source need, Supplier, line type, quantity/value, price, tax/context và delivery/performance terms | `FR-PUR-002.01` |
| `SCN-PUR-002-02` | Policy yêu cầu quotation comparison | PO được hình thành | Quotation/Supplier-selection evidence bắt buộc vẫn được liên kết | `.02` |
| `SCN-PUR-002-03` | Direct-purchase policy áp dụng | Direct PO được hình thành | Direct path được nhận diện rõ và không yêu cầu comparison giả | `.02` |
| `SCN-PUR-002-04` | Chưa có Budget capability được phê duyệt | PO được xử lý | Không suy ra nghĩa vụ acceptance về budget control từ FR này | Boundary |

**TBD / các quyết định downstream**

- PO lifecycle/change-order/cancellation matrix và numbering relationship với document bị thay thế/điều chỉnh.
- Mandatory field và quantity-versus-value semantics theo purchase-line class.
- Direct-PO evidence/approval mapping và sourcing-policy reference model.
- Tax value capture/rounding behavior trong P1 trước khi operational Tax Engine vào scope.
- Delivery/performance term catalog và hành vi partial commitment amendment.

#### 9.3.3 FR-PUR-003 — Đặc tả chi tiết: receiving và chênh lệch nhận hàng

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | Receiving User được ủy quyền; reviewer/decision maker được ủy quyền cho receiving exception khi policy yêu cầu. |
| Trigger | Hàng hóa/dịch vụ/item được nhận hoặc performance được ghi nhận theo Purchase Order/commitment trong phạm vi receiving P1 đã phê duyệt. |
| Hard prerequisites | `FR-PUR-002`, `FR-INV-001`. |
| Các reference không-hard | `BR-INV-004` về lot/serial traceability trong tương lai và `BR-PUR-004` về invoice matching/landed cost trong tương lai. |
| Hệ quả tồn kho P1 | Purchase Receipt hợp lệ và được ghi nhận chính thức cập nhật inventory quantity/status và cung cấp source evidence cho Product Moving Average Cost cấp Group. |

**Đầu vào và context**

Với mỗi receipt line áp dụng, hệ thống phải lưu source PO/commitment, Product khi áp dụng, ordered/committed quantity, previously received quantity, quantity received now, cumulative received quantity, basic quality/inventory status, Company sở hữu và receiving inventory context. Đối với purchase class không dựa trên quantity, receiving/performance evidence cụ thể vẫn là quyết định được kiểm soát. Lot/serial và detailed landed-cost data không bắt buộc, trừ khi capability/policy tương ứng được phê duyệt riêng.

**Quy tắc xử lý và validation**

1. Receipt line phải tham chiếu PO/commitment áp dụng và nằm trong cùng Company ownership transaction context.
2. Khi Product áp dụng, receipt phải tham chiếu cùng canonical Product mà commitment dự kiến và resolve quantity/UOM context theo `FR-PRD-002`.
3. Hệ thống phải tính hoặc thể hiện được ordered/committed quantity, previously received quantity, current receipt quantity và cumulative received quantity đối với receipt line dựa trên quantity.
4. Phải hỗ trợ partial receiving. PO/line không bắt buộc phải nhận đủ trong một receipt, trừ khi controlled policy quy định khác.
5. Phải phát hiện được over-receiving và under-receiving so với commitment, và xử lý theo controlled policy/threshold. Nguồn chưa định nghĩa tolerance value hoặc approval level.
6. Receiving exception phải lưu classification, owner và disposition/decision. Nếu policy yêu cầu approval thì áp dụng `FR-CTL-004`; approval không tạo access right.
7. Phải ghi nhận basic quality/status đủ để phân biệt usable inventory với non-usable/quarantine inventory trong P1.
8. Inventory không được đánh dấu usable không được coi là normally allocatable stock theo `FR-INV-001`.
9. Purchase Receipt hợp lệ và được ghi nhận chính thức phải tạo inventory inbound source evidence cần cho `FR-INV-001`.
10. Khi receipt là costing inbound transaction hợp lệ, `FR-INV-001` phải tính lại Product Moving Average Cost cấp Group theo quantity-weighted moving-average rule được kiểm soát sau khi quantity/value được normalize về costing UOM/currency context áp dụng.
11. Receipt cancellation/reversal/adjustment không được xóa original receipt hoặc cost impact. Phải giữ traceability giữa original inbound transaction, corrective transaction/action và resulting cost effect.
12. P1 không được yêu cầu lot/serial genealogy hoặc detailed landed-cost data chỉ để hoàn thành receiving nếu các capability phase sau đó chưa thuộc approved scope.
13. Receiving phải giữ actor chịu trách nhiệm, timestamp, reason/exception evidence và source relationship theo `FR-CTL-002`/`FR-AUD-001` khi áp dụng.

**Quan hệ quantity của receiving**

Với PO line dựa trên quantity, receipt context phải hỗ trợ quan hệ:

`cumulative_received_after = previously_received + quantity_received_now`

Nguồn yêu cầu nhận diện over/under receiving nhưng chưa định nghĩa việc đóng commitment phụ thuộc vào exact equality, tolerance, explicit close-short decision hay controlled disposition khác. Quy tắc closure này vẫn là quyết định Wave D.

**Đầu ra và bằng chứng được lưu giữ**

Mỗi receipt phải cung cấp source PO/line, Product khi áp dụng, ordered/committed quantity, prior/current/cumulative receipt quantity, receiving status, usable/non-usable decision, exception classification/owner/disposition, inventory inbound reference và cost-impact traceability khi Moving Average Cost bị ảnh hưởng.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-PUR-003-01` | PO line đã có prior receipt | Một receipt khác được ghi nhận | Ordered, previously received, current receipt và cumulative received quantity đều truy vết được | `FR-PUR-003.01` |
| `SCN-PUR-003-02` | Chỉ một phần committed quantity đến | Receipt được ghi nhận | Partial receiving được hỗ trợ mà không ép full completion | `.02` |
| `SCN-PUR-003-03` | Receipt vượt hoặc thấp hơn kỳ vọng được kiểm soát | Receipt được đánh giá | Variance được nhận diện và xử lý theo policy/threshold áp dụng | `.02`, `.04` |
| `SCN-PUR-003-04` | Stock nhận về không đạt basic usability decision | Receipt được post | Quantity được giữ ở non-usable/quarantine status và không normally allocatable | `.03` |
| `SCN-PUR-003-05` | Lot/serial/landed-cost capability chưa được phê duyệt | Receipt được xử lý | Không yêu cầu dummy lot/serial hoặc landed-cost detail | `.05` |
| `SCN-PUR-003-06` | Product receipt hợp lệ được ghi nhận chính thức | Inventory/cost update chạy | Receipt source được giữ và Product Moving Average Cost được tính lại theo `FR-INV-001` | `.06` |
| `SCN-PUR-003-07` | Posted receipt sau đó bị cancel/adjust | Corrective action được ghi nhận | Original receipt và cost impact có traceability được bảo toàn | `.06` |

**TBD / các quyết định downstream**

- Receiving tolerance/threshold matrix và authority cho over/under acceptance.
- Rule close/close-short/reopen của PO line sau partial hoặc under receiving.
- Basic receiving quality/status catalog và transition rule.
- Semantics của receiving/service-performance evidence cho Service/Expense/Non-stock.
- Định nghĩa chính xác costing eligibility và value component của Purchase Receipt trong P1 Moving Average Cost.
- Cơ chế receipt cancellation/reversal/adjustment và cách xử lý costing tương ứng.

### 9.4 Inventory

#### 9.4.1 FR-INV-001 — Đặc tả chi tiết: inventory balance, status, reservation và Moving Average Cost

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | Inventory User và transaction process trong scope được ủy quyền; Sales có thể consume availability/reservation; Purchase Receipt cung cấp eligible inbound evidence. |
| Trigger | Một inventory movement/status change/reservation trong scope hoặc eligible inbound costing event được ghi nhận chính thức. |
| Hard prerequisites | `FR-ORG-002`, `FR-PRD-001`. |
| Các reference không-hard | `FR-PUR-003`; Manufacturing, lot/serial traceability và configurable valuation capability trong tương lai. |
| Canonical quantity dimensions | Company, Branch/Plant/Warehouse, location, Product và basic inventory status. |
| Cost ownership | Product Moving Average Cost hiện tại ở cấp Group, do hệ thống sinh và Company không được override. |

**Đầu vào và context**

Inventory quantity phải gắn được với một Company sở hữu và các dimension Organization Context/location/Product/basic status áp dụng. Reservation phải được lưu/evaluate tách biệt khỏi usable on-hand. Optional lot/serial/expiry/basic quality attribute có thể được giữ khi có. Eligible inbound costing yêu cầu Product, normalized quantity/value, costing UOM/currency context và source inbound transaction evidence.

**Quy tắc xử lý và validation**

1. Mỗi inventory quantity record/effect phải resolve đúng một Company sở hữu; Group được suy ra qua Company đó.
2. Inventory detail phải gắn được, khi áp dụng, theo Company, Branch/Plant/Warehouse, location, Product và basic inventory status.
3. Canonical detail quantity phải reconcile lên Company-scope quantity của cùng Product/status context mà không double count.
4. Inventory phải cung cấp tối thiểu usable on-hand, reserved quantity, non-usable/quarantine quantity và in-transit quantity khi các status này áp dụng.
5. Reservation phải luôn tách biệt về logic khỏi usable on-hand. Reservation không được âm thầm tạo hoặc làm mất physical on-hand quantity.
6. Non-usable/quarantine quantity không được phân bổ cho nhu cầu sử dụng/sales fulfillment thông thường.
7. P1 có thể lưu lot/serial/expiry/basic quality attribute khi có, nhưng không được yêu cầu traceability/genealogy end-to-end trước khi các capability phase sau được phê duyệt.
8. Với mỗi Product trong costing scope, hệ thống phải cung cấp một Product Moving Average Cost hiện tại ở cấp Group, kèm update/effective timestamp và inbound transaction gần nhất đã làm thay đổi cost.
9. Sau khi eligible Purchase Receipt được ghi nhận chính thức, Moving Average Cost phải được tính lại bằng quantity-weighted moving-average rule trên eligible quantity/value đã normalize về UOM/currency costing context áp dụng.
10. Khi Manufacturing chưa thuộc approved scope, không được tạo hoặc mô phỏng Production Finished-Goods Receipt chỉ để thỏa costing rule.
11. Khi Manufacturing được phê duyệt trong tương lai, eligible Production Finished-Goods Receipt có thể tham gia theo cùng khái niệm Moving Average Cost cấp Group, tùy controlled value/eligibility policy tương ứng.
12. Không được tồn tại Company-specific Moving Average Cost override. Không được suy ra configurable valuation method/policy từ baseline P1 này.
13. Inventory change phải giữ source transaction/reason/actor evidence đủ cho `FR-CTL-002` và `FR-AUD-001` khi áp dụng.
14. Inventory action và visibility vẫn phải chịu `FR-IAM-003` trong Company/Organization Context đã resolve.

**Các invariant về quantity/status**

- Physical/state classification và reservation là hai khái niệm riêng: `reserved quantity` không phải một physical on-hand balance thứ hai.
- P1 ATP được `FR-SAL-002` sử dụng được định nghĩa là `usable on-hand - current reservation` trong cùng Company/inventory scope đã resolve.
- Không cộng future supply quantity vào P1 ATP.
- Cross-Company quantity movement không được xử lý như internal transfer trong P1.

**Invariant khái niệm của Moving Average Cost**

Đối với một eligible inbound event, kết quả phải là quantity-weighted moving average sử dụng controlled eligible pre-event quantity/value và eligible inbound quantity/value sau UOM/currency normalization cần thiết. Nguồn hiện tại chưa định nghĩa edge-case policy cho zero/negative eligible quantity, excluded status, tax/freight component, retrospective correction hoặc same-timestamp ordering; các điểm này phải được kiểm soát trước trạng thái implementation-ready.

**Đầu ra và bằng chứng được lưu giữ**

Hệ thống phải thể hiện canonical quantity theo các dimension/status được hỗ trợ, reservation tách khỏi usable on-hand, và Product Moving Average Cost hiện tại ở cấp Group cùng source/effective context. Movement/source reference phải cho phép reconcile từ quantity change về P1 source transaction.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-INV-001-01` | Inventory tồn tại trên các dimension được hỗ trợ | Detail được aggregate | Detail quantity reconcile với Company-scope total trong cùng context | `FR-INV-001.01` |
| `SCN-INV-001-02` | Product có usable, reserved, quarantine và in-transit quantity | Availability được kiểm tra | Phân biệt được từng quantity áp dụng | `.02` |
| `SCN-INV-001-03` | Quantity ở trạng thái quarantine/non-usable | Thực hiện normal allocation | Quantity không được phân bổ cho sử dụng thông thường | `.03` |
| `SCN-INV-001-04` | Lot/serial/expiry capability chưa được phê duyệt | P1 inventory được xử lý | Optional attribute có thể tồn tại nhưng không phát sinh nghĩa vụ traceability end-to-end | `.04` |
| `SCN-INV-001-05` | Product thuộc P1 costing scope | Cost context được kiểm tra | Xác định được một Group-level current Moving Average Cost, timestamp và inbound source gần nhất làm thay đổi cost | `.05`, `.08` |
| `SCN-INV-001-06` | Eligible Purchase Receipt được ghi nhận chính thức | Cost update chạy | Quantity-weighted moving average được tính lại trên normalized eligible quantity/value | `.06` |
| `SCN-INV-001-07` | Manufacturing chưa được phê duyệt | Costing acceptance chạy | Không yêu cầu Production Receipt giả | `.07` |
| `SCN-INV-001-08` | Company cố tạo local cost override | Override được đánh giá | Không tạo Company Moving Average Cost cạnh tranh | `.08` |

**TBD / các quyết định downstream**

- Canonical inventory status catalog và rule transition chính xác giữa các quantity bucket.
- Rule create/release/consume/expire reservation và allocation priority.
- Inventory-location model và việc location có bắt buộc theo Warehouse/Product context hay không.
- Eligible quantity/status set dùng trong Moving Average Cost weighting.
- Eligible inbound value component, điểm currency normalization và cách xử lý tax/freight trước khi landed-cost capability tồn tại.
- Edge case của Moving Average Cost: zero/negative stock, return/correction, backdated receipt, same-timestamp ordering và reversal behavior.
- Costing-scope inclusion/exclusion theo Product/item type.

#### 9.4.2 FR-INV-002 — Đặc tả chi tiết: inventory movement và intra-company transfer

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | Inventory User/process được ủy quyền sở hữu movement; actor của source capability khi movement phát sinh từ approved source transaction. |
| Trigger | Một inventory movement trong scope được khởi tạo/ghi nhận chính thức, bao gồm Purchase inbound, Sales outbound hoặc P1 internal transfer/movement. |
| Hard prerequisites | `FR-INV-001`, `FR-ORG-002`. |
| Reference không-hard | `BR-INV-006` về intercompany movement trong tương lai. |

**Đầu vào và context**

Mỗi movement phải lưu source document/capability, actor/process chịu trách nhiệm, reason, Company sở hữu, Product, quantity/UOM context, source/destination Warehouse/location/context khi áp dụng và movement state. Với internal transfer, source và destination Organization Context phải thuộc cùng Company. Nguồn chưa định nghĩa đầy đủ P1 movement-type catalog hoặc tên transfer lifecycle state ngoài việc phân biệt in-transit và received.

**Quy tắc xử lý và validation**

1. Movement chỉ được chấp nhận cho source capability/movement class nằm trong approved scope; Production, Warranty/Returns hoặc movement class ở phase sau không làm phát sinh dummy transaction trong P1.
2. Mỗi movement phải giữ source evidence, actor/process chịu trách nhiệm và business reason đủ để giải thích quantity effect.
3. Với intra-company transfer, source và destination Organization Context phải resolve về cùng Company sở hữu.
4. Intra-company transfer không được tạo intercompany transaction và không được thay đổi Company sở hữu chỉ vì inventory di chuyển giữa Warehouse/Branch/Plant trong cùng Company.
5. Transfer phải phân biệt quantity dispatched/in-transit với quantity received để reconcile được source/destination quantity và status.
6. Movement giữa hai Company khác nhau phải bị từ chối vì P1 chưa hỗ trợ cross-company movement và không được ngụy trang thành internal Warehouse/Branch transfer.
7. `BR-INV-006` trong tương lai có thể route cross-company movement qua controlled intercompany flow khi được phê duyệt; capability tương lai đó không phải runtime prerequisite của P1.
8. Movement quantity phải dùng conversion context của `FR-PRD-002` khi transaction UOM khác canonical inventory UOM.
9. Movement chỉ cập nhật canonical inventory quantity/status theo `FR-INV-001` khi đạt transaction/lifecycle state được kiểm soát áp dụng. Exact posting state chưa được upstream định nghĩa.
10. Movement reversal/correction phải bảo toàn original source/movement relationship và audit evidence thay vì âm thầm ghi đè historical movement.
11. Authorization và approval khi áp dụng phải tuân theo `FR-IAM-003`/`FR-CTL-004`; nguồn không quy định mọi movement đều cần approval.

**Bảng quyết định về ownership của transfer**

| Source Company | Destination Company | P1 classification/result |
|---|---|---|
| Cùng Company | Cùng Company | Được phép như intra-company transfer, tùy policy và Organization Context hợp lệ |
| Company A | Company B, A != B | Từ chối vì P1 chưa hỗ trợ cross-company movement |
| Không resolve được Company của một trong hai phía | Bất kỳ | Ownership không hợp lệ/chưa resolve; không coi là internal transfer |

**Đầu ra và bằng chứng được lưu giữ**

Mỗi movement phải cung cấp movement identity, source transaction/reference, Company sở hữu, Product/quantity/UOM, source/destination context, transfer/movement status hiện tại, actor/reason chịu trách nhiệm và quantity/status effect. Internal transfer phải hỗ trợ reconcile giữa source dispatch, in-transit và destination receipt khi áp dụng.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-INV-002-01` | Có P1 movement trong scope | Movement được ghi nhận | Source document, actor chịu trách nhiệm và reason truy vết được | `FR-INV-002.01` |
| `SCN-INV-002-02` | Same-Company internal transfer | Transfer tiến triển | Source, destination, quantity và transfer status reconcile được, gồm in-transit khi áp dụng | `.02`, `.03` |
| `SCN-INV-002-03` | Branch/Warehouse context thuộc cùng một Company | Transfer được ghi nhận | Company sở hữu không đổi và không tạo intercompany transaction | `.03` |
| `SCN-INV-002-04` | Source thuộc Company A, destination thuộc Company B | Thực hiện P1 transfer | Movement bị từ chối vì là unsupported cross-company transfer | `.04` |
| `SCN-INV-002-05` | Intercompany capability không thuộc P1 | P1 acceptance chạy | Không yêu cầu intercompany route phải tồn tại | `.05` |
| `SCN-INV-002-06` | Movement class ở phase sau chưa được phê duyệt | P1 inventory được test | Không tạo dummy movement cho class đó | `.06` |

**TBD / các quyết định downstream**

- P1 movement-type catalog và posting/official-recording state theo từng type.
- Internal transfer lifecycle state và semantics dispatch/receipt/cancel/reversal.
- Approval threshold hoặc class, nếu có, cho internal transfer và các P1 movement khác.
- Thời điểm UOM normalization và precision rule khi posting movement.
- Yêu cầu source/destination location theo movement type.

#### 9.4.3 FR-INV-003 — Đặc tả chi tiết: kiểm kê, điều chỉnh và tồn bất thường

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | Counter được ủy quyền; checker/reviewer được ủy quyền; approver được ủy quyền cho adjustment/exception khi bắt buộc. |
| Trigger | Scheduled/risk-based count, discrepancy được phát hiện, negative-inventory condition hoặc retrospective adjustment request theo P1 policy. |
| Hard prerequisites | `FR-INV-001`, `FR-CTL-004`, `FR-ORG-004`. |
| Reference không-hard | `BR-FIN-006` về financial period/closing trong tương lai. |

**Đầu vào và context**

Count/adjustment evidence phải lưu Company sở hữu/inventory scope, Product, counted quantity, system quantity/reference point, discrepancy, reason, counter, checker, resulting quantity effect và approval/disposition khi áp dụng. Giải thích cost/value phải dùng Product Moving Average Cost hiện hành của P1 hoặc valuation capability thực tế đang có hiệu lực khi capability định giá phase sau đã được phê duyệt.

**Quy tắc xử lý và validation**

1. Inventory count có thể theo periodic hoặc risk-triggered; scheduling/risk policy cụ thể chưa được upstream định nghĩa.
2. Count result phải xác định counter và checker như hai evidence field/role riêng; việc hai role này có thể do cùng một người thực hiện trong trường hợp nào hay không chưa được định nghĩa tại đây và vẫn phụ thuộc controlled authorization/approval design.
3. Hệ thống phải tính/thể hiện quantity discrepancy giữa controlled system quantity/reference point và counted quantity.
4. Mỗi discrepancy phải có reason và controlled disposition trước khi resulting adjustment được coi là chính thức.
5. Adjustment phải cung cấp quantity effect; cost/value effect phải giải thích được bằng P1 Product Moving Average Cost hoặc approved valuation capability đang có hiệu lực.
6. Negative inventory phải được kiểm soát theo P1 policy. Nguồn chưa định nghĩa việc tất cả negative stock đều bị block hay exception/approval threshold nào áp dụng.
7. Retrospective/backdated adjustment phải được kiểm soát theo P1 policy và phải giữ history/audit evidence thay vì viết lại prior state không trace.
8. Exception phải bị block hoặc được approve theo policy áp dụng, đồng thời giữ adjustment document/reference và reason.
9. P1 không được tạo closed financial period giả để acceptance inventory adjustment vì financial close không nằm trong approved P1 scope.
10. `FR-ORG-004` chỉ cung cấp Company calendar/time context; không được coi là owner của financial-period close/reopen semantics.
11. Khi `BR-FIN-006` sau này vào approved scope, movement/adjustment sau financial-period close phải tuân theo Finance-owned close/reopen/retrospective-adjustment policy đó.
12. Count/adjustment action vẫn phải chịu `FR-IAM-003` và approval bắt buộc thông qua `FR-CTL-004`.
13. Count và adjustment history vẫn phải traceable theo `FR-AUD-001`/`FR-CTL-002` khi áp dụng.
**Đầu ra và bằng chứng được lưu giữ**

Hệ thống phải thể hiện count identity/reference, inventory scope, Product, system/reference quantity, counted quantity, discrepancy, reason, counter, checker, decision/approval, resulting quantity effect, adjustment source/reference và cost/value effect có thể giải thích.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-INV-003-01` | Count khác system quantity | Count được review | Discrepancy, reason, counter, checker và quantity effect truy vết được; cost/value effect giải thích được | `FR-INV-003.01` |
| `SCN-INV-003-02` | Adjustment vi phạm P1 policy áp dụng | Adjustment được thực hiện | Adjustment bị block hoặc được route qua controlled approval/exception path, giữ reason/document evidence | `.02` |
| `SCN-INV-003-03` | Financial close không thuộc P1 | Inventory-adjustment acceptance chạy | Không yêu cầu closed financial period giả | `.03` |
| `SCN-INV-003-04` | Finance closing được phê duyệt trong tương lai | Adjustment nhắm vào closed period | Finance-owned close/reopen/retrospective policy chi phối action | `.04` |

**TBD / các quyết định downstream**

- Count scheduling/risk-trigger policy và phương pháp freeze/snapshot cho comparison quantity.
- Negative-inventory policy, threshold, block/override authority và evidence.
- Adjustment approval matrix và retrospective/backdated adjustment limit.
- Rule về phân tách counter/checker và exception được phép nếu có.
- Cách xử lý cost/value effect của adjustment dưới P1 Moving Average Cost, đặc biệt với retrospective correction.

### 9.5 Sales

#### 9.5.1 FR-SAL-001 — Đặc tả chi tiết: Quotation và Sales Order capture

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | Sales User/order-capture actor được ủy quyền; Customer hoặc guest-customer context khi áp dụng. External channel integration không bắt buộc trong P1. |
| Trigger | Quotation hoặc Sales Order được capture từ source/channel đã phê duyệt thông qua manual input hoặc system input sẵn có. |
| Hard prerequisites | `FR-CUS-001`, `FR-PRD-001`, `FR-PRC-001`. |
| Các reference không-hard | Promotion/Discount Engine trong tương lai, operational Tax Engine và external Integration capability. |

**Đầu vào và context**

Quotation/Sales Order phải lưu Company sở hữu, source/channel attribution, source/reference code khi áp dụng, Customer hoặc guest-customer information phù hợp, Product line, base-price source/context, sales conditions, validity/commitment date khi áp dụng và discount/tax value đã capture khi áp dụng. Nguồn chưa định nghĩa đầy đủ guest-customer minimum-data set, channel catalog, tax/discount entry rule hoặc cơ chế Quotation-to-Sales-Order conversion.

**Quy tắc xử lý và validation**

1. Mỗi Quotation/Sales Order phải resolve đúng một Company sở hữu và business timestamp/context hợp lệ theo `AR-TXN-001`/`FR-ORG-004`.
2. Phải lưu source/channel attribution. Ví dụ upstream nêu website, marketplace, point of sale, phone, salesperson, agent và distributor; danh sách này tự thân không yêu cầu external integration.
3. P1 phải hỗ trợ manual capture được kiểm soát cho source/channel khi Integration capability chưa được phê duyệt/chưa có.
4. Sales Order phải lưu source/reference identifier phù hợp với capture process; uniqueness/format semantics chưa được upstream định nghĩa.
5. Khi sử dụng Customer record, Customer phải tham chiếu canonical Customer Master. Hệ thống có thể lưu guest-customer information phù hợp khi P1 business process cho phép guest/retail customer; minimum data cụ thể là quyết định Wave D.
6. Product line phải tham chiếu canonical Product hợp lệ/đang hiệu lực trong Company/usage scope áp dụng.
7. Base price phải được resolve thông qua `FR-PRC-001`. Transaction phải giữ base-price source đã chọn và đủ context để giải thích vì sao source đó áp dụng tại thời điểm confirmation.
8. Quotation/sales condition và quotation validity/effective period phải được lưu khi áp dụng. Exact field catalog và expiry behavior chưa được upstream định nghĩa.
9. Discount và tax value có thể được lưu trên transaction khi áp dụng.
10. P1 không được hàm ý automated Promotion/Discount Policy Engine. Automated promotion/discount calculation chỉ trở thành nghĩa vụ khi capability phase sau tương ứng được phê duyệt.
11. P1 không được hàm ý operational Tax Engine. Automated tax calculation chỉ trở thành nghĩa vụ khi Finance tax capability phase sau được phê duyệt.
12. Manual/captured discount/tax value trong P1 phải phân biệt được với canonical base price/source để downstream user không hiểu nhầm chúng là kết quả của Pricing Policy.
13. Các action create/change/confirm của Quotation/Sales Order phải tuân theo `FR-IAM-003`, `FR-CTL-001` và rule `FR-CTL-004` áp dụng, mà không kéo credit-control capability phase sau vào P1.
14. Sales capture phải giữ audit/responsibility evidence cho P1 event liên quan theo `FR-CTL-002`/`FR-AUD-001`.

**Đầu ra và bằng chứng được lưu giữ**

Transaction phải cung cấp Company sở hữu, source/channel, source/reference ID, Customer hoặc guest-customer context, Product line, canonical base price/source đã chọn, sales condition đã capture, validity/commitment context và discount/tax value đã lưu khi áp dụng.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-SAL-001-01` | Một order được capture | Sales Order được kiểm tra | Source/channel, reference và Customer hoặc guest-customer information phù hợp được lưu | `FR-SAL-001.01` |
| `SCN-SAL-001-02` | External integration chưa được phê duyệt | Source/channel được capture thủ công | P1 order capture vẫn hợp lệ mà không cần external integration | `.02` |
| `SCN-SAL-001-03` | Product có canonical pricing source áp dụng | Quotation/order base price được xác nhận | Selected source và pricing condition/context áp dụng truy vết được về `FR-PRC-001` | `.03` |
| `SCN-SAL-001-04` | Discount/tax value được capture trong P1 nhưng engine chưa được phê duyệt | Transaction được lưu | Value được giữ mà không hàm ý automated promotion/tax-policy calculation | `.04` |

**TBD / các quyết định downstream**

- Controlled source/channel catalog và source-reference format/uniqueness rule.
- Guest-customer eligibility và minimum required information.
- Quotation lifecycle, validity expiry và Quotation-to-Sales-Order conversion rule.
- Authorization, validation và rounding cho manual discount/tax entry trước khi các engine phase sau vào scope.
- Sales Order confirmation/cancellation/change matrix và P1 approval class nếu có.

#### 9.5.2 FR-SAL-002 — Đặc tả chi tiết: availability và fulfillment commitment

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | Sales User/process được ủy quyền sử dụng inventory availability và ghi fulfillment commitment; Inventory cung cấp canonical usable/reserved quantity. |
| Trigger | Availability được yêu cầu cho Sales Order/line hoặc reservation/commitment decision được thực hiện trong P1. |
| Hard prerequisites | `FR-SAL-001`, `FR-INV-001`. |
| Canonical P1 ATP | `usable on-hand - current reservation` trong cùng Company/inventory scope đã resolve. |
| Explicit exclusions | Incoming PO, planned Production, forecast và future supply khác không làm tăng P1 ATP. |

**Đầu vào và context**

Availability evaluation phải resolve Company sở hữu, Product, inventory scope liên quan và current usable on-hand/reservation từ `FR-INV-001`. Expected delivery date/commitment và unfulfilled/backordered quantity có thể được lưu, nhưng nguồn chưa định nghĩa future-supply promise algorithm, reservation allocation priority hoặc thuật toán chọn inventory scope chính xác.

**Quy tắc xử lý và validation**

1. P1 ATP chỉ được tính từ current inventory trong Company/inventory scope đã resolve.
2. Công thức chuẩn là `ATP_P1 = usable_on_hand - current_reservation` cho cùng resolved scope.
3. Incoming Purchase Order, planned Production, forecast và future supply khác không được làm tăng P1 ATP chỉ vì chúng tồn tại trong hệ thống.
4. Sales User/process phải phân biệt được tối thiểu available-to-promise quantity, quantity đã reserved và phần demand hiện chưa fulfilled.
5. Expected delivery date/commitment có thể được lưu độc lập với ATP quantity.
6. Việc ghi/thay đổi expected delivery date tự thân không được tăng usable on-hand, giảm/tăng reservation hoặc thay đổi inventory quantity.
7. Future supply chỉ có thể tham gia ATP sau khi capability/methodology tương lai được phê duyệt rõ; P1 không được âm thầm triển khai projected ATP.
8. Reservation effect, nếu được tạo từ Sales Order processing, phải cập nhật reservation bucket theo `FR-INV-001` mà không thay đổi physical on-hand. Rule create/release/consume reservation cụ thể vẫn là quyết định được kiểm soát.
9. Availability không được lấy quantity xuyên Company ownership boundary, trừ khi capability cross-company được phê duyệt sau này quy định rõ hành vi đó.
10. Non-usable/quarantine inventory không được đóng góp vào P1 usable on-hand/ATP.
11. Availability và reservation action vẫn phải chịu authorization và transaction lifecycle control phù hợp.

**Bảng quyết định ATP**

| Quantity/source | Tính vào P1 ATP? | Lý do |
|---|---|---|
| Current usable on-hand trong resolved scope | Có, thành phần dương | Định nghĩa P1 rõ ràng |
| Current reservation trong resolved scope | Có, trừ đi | Định nghĩa P1 rõ ràng |
| Quarantine/non-usable quantity | Không | Không usable cho normal allocation |
| Incoming Purchase Order | Không | Loại future supply |
| Planned Production | Không | Loại future supply |
| Forecast | Không | Loại future supply |
| Future supply khác | Không | Future methodology chưa được phê duyệt trong P1 |

**Đầu ra và bằng chứng được lưu giữ**

Availability result phải cung cấp Company/inventory scope đã resolve, Product, usable on-hand, current reservation, ATP đã tính, requested/commitment quantity khi áp dụng, unfulfilled quantity và expected delivery/commitment date được lưu riêng khi sử dụng.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-SAL-002-01` | Có usable on-hand và reservation trong một resolved scope | ATP được yêu cầu | `ATP = usable on-hand - reservation` trong scope đó | `FR-SAL-002.01` |
| `SCN-SAL-002-02` | Demand vượt P1 ATP | Availability được đánh giá | User phân biệt được ATP, reserved quantity và unfulfilled portion | `.02` |
| `SCN-SAL-002-03` | Có incoming PO/planned Production/forecast | ATP được tính | Future supply không làm tăng P1 ATP | `.03` |
| `SCN-SAL-002-04` | Expected delivery date được nhập | Commitment được cập nhật | Date được lưu riêng và inventory/reservation không bị thay đổi chỉ vì date | `.04` |
| `SCN-SAL-002-05` | Có quarantine stock | ATP được tính | Quarantine quantity không đóng góp vào usable on-hand/ATP | Boundary from `FR-INV-001` |

**TBD / các quyết định downstream**

- Rule chọn inventory scope cho ATP theo Warehouse/Branch/location và việc user có thể chọn/aggregate scope hay không.
- Rule create/release/consume/expire reservation và allocation priority giữa các Sales Order.
- Hành vi khi `ATP_P1` âm và việc display floor ở zero hay hiển thị negative availability.
- Lifecycle của backorder/unfulfilled quantity và ownership/approval rule của expected-delivery-date.
- Concurrency/locking semantics cần để tránh over-reservation khi có simultaneous commitment.
### 9.6 Finance foundation hỗ trợ các P1 transaction

#### 9.6.1 FR-FIN-005 — Đặc tả chi tiết: multi-currency và canonical Exchange Rate Master

**Hợp đồng vận hành**

| Khía cạnh | Đặc tả chi tiết |
|---|---|
| Business actors | FX/reference-data administrator được ủy quyền để bảo trì rate; các P1 business process được ủy quyền sử dụng conversion. Tên role cụ thể chưa được upstream định nghĩa. |
| Trigger | Exchange-rate record được tạo/thay đổi hoặc một P1 transaction cần conversion giữa các currency. |
| Hard prerequisite | `FR-ORG-004`. |
| Ranh giới P1 | Chỉ cung cấp canonical rate resolution/conversion context; không tạo realized/unrealized FX accounting hoặc Finance posting rộng hơn. |

**Đầu vào và context**

Exchange-rate record phải lưu from currency, to currency, rate type, rate value, source, effective-from/effective time, lifecycle/status và owner. Conversion context phải lưu original amount/currency, target currency, resolved rate record/source/effective time và converted amount. Nguồn yêu cầu deterministic precedence khi có nhiều rate có thể áp dụng nhưng chưa định nghĩa precedence table thực tế, inverse-rate use, triangulation hoặc rate-type default.

**Quy tắc xử lý và validation**

1. Exchange Rate Master phải là canonical P1 source cho approved currency conversion.
2. Mỗi rate record phải xác định from currency, to currency, rate type, rate value dương/hợp lệ theo controlled policy, source, effective time, lifecycle/status và owner. Nguồn không quy định rõ thêm sign/domain validation của rate value ngoài yêu cầu đây phải là usable rate.
3. Khi nhiều rate record có thể áp dụng, hệ thống phải resolve đúng một rate theo cách deterministic dựa trên controlled precedence/policy.
4. Rate record thực tế đã chọn phải được lưu cùng conversion evidence; historical transaction không được âm thầm recompute lại bằng rate mới hơn.
5. Mỗi conversion phải lưu original amount, original currency, target currency, resolved rate reference/source/effective context và converted amount.
6. Transaction cần currency conversion không được ghi nhận/post chính thức nếu không resolve được valid rate, trừ khi một controlled approved exception rule riêng cho phép exception path.
7. Hệ thống không được tự tạo exchange rate khi không có valid rate.
8. Exchange-rate history phải được bảo toàn; thay đổi rate policy hoặc thêm rate mới không được ghi đè record đã từng có hiệu lực/được sử dụng.
9. Conversion precision/rounding phải tuân theo `NFR-DAT-001`; internal precision phải đủ để giải thích final currency-rounded result.
10. Company business/base/functional currency context phải đến từ `FR-ORG-004`; locale/display formatting không được làm thay đổi currency meaning hoặc canonical monetary value.
11. P1 Exchange Rate Master không được hiểu là approval cho realized/unrealized FX revaluation, remeasurement, FX gain/loss posting hoặc accounting capability khác khi chưa có approved Finance requirement.
12. Rate maintenance và protected conversion action vẫn phải chịu `FR-IAM-003` cùng audit/history requirement.

**Bảng ranh giới rate resolution**

| Tình huống | Hành vi P1 bắt buộc |
|---|---|
| Đúng một valid rate resolve theo policy | Dùng rate đó và lưu rate record/reference |
| Nhiều rate có thể áp dụng | Resolve một rate theo cách deterministic bằng controlled precedence/policy và lưu selected record |
| Không resolve được valid rate và không có approved exception | Block official transaction recording/posting cần conversion |
| Không resolve được valid rate nhưng có controlled approved exception | Đi theo explicit exception path và lưu evidence tương ứng |
| Historical transaction đã dùng một rate | Bảo toàn used rate reference; không âm thầm thay bằng rate mới hơn |

**Đầu ra và bằng chứng được lưu giữ**

Conversion result phải cung cấp original amount/currency, target currency, converted amount, selected rate value/reference, rate type/source, effective context và approved exception evidence nếu có. Exchange-rate master history phải tiếp tục query được.

**Các scenario acceptance**

| Scenario | Điều kiện ban đầu | Khi | Kết quả mong đợi | Trace |
|---|---|---|---|---|
| `SCN-FIN-005-01` | Có một rate record | Record được kiểm tra | Xác định được from/to currency, rate type/value, source, effective time, lifecycle/status và owner | `FR-FIN-005.01` |
| `SCN-FIN-005-02` | Nhiều valid rate có thể áp dụng | Conversion được yêu cầu | Đúng một rate được resolve theo controlled precedence và selected record được lưu | `.02` |
| `SCN-FIN-005-03` | Conversion thành công | Evidence được kiểm tra | Original amount/currency, rate/source, converted amount/currency và effective time đều truy vết được | `.03` |
| `SCN-FIN-005-04` | Cần conversion nhưng không resolve được valid rate | Thực hiện official recording | Transaction bị block trừ khi controlled approved exception áp dụng; không tự tạo rate | `.04` |
| `SCN-FIN-005-05` | Historical rate đã được sử dụng | Rate mới được publish sau đó | Historical rate record/evidence vẫn tồn tại và không đổi | `.05` |
| `SCN-FIN-005-06` | P1 Exchange Rate Master được triển khai | Finance scope được đánh giá | Không suy ra nghĩa vụ realized/unrealized FX accounting | `.06` |

**TBD / các quyết định downstream**

- Rate-type catalog, default rate type theo transaction context và controlled precedence khi nhiều rate áp dụng.
- Direct/inverse pair rule, inversion precision và việc có cho phép triangulation/cross-rate derivation hay không.
- Effective-time lookup semantics, timezone/boundary behavior và overlap prevention.
- Source trust/approval model và rule approve cho rate-entry/change.
- Controlled missing-rate exception policy nếu có.
- Conversion rounding point/target-currency rule ngoài baseline `NFR-DAT-001`.

### 9.7 Register quyết định chưa chốt của Wave D

Các mục sau được chủ ý để mở vì nguồn hiện tại chưa định nghĩa. Chúng phải được giải quyết bằng controlled policy, FRS refinement, NFS hoặc design; implementation không được âm thầm thiết lập business semantics mới. Classification tuân theo Section 1.4 và tự thân không giải quyết quyết định.

| ID | Quyết định chưa chốt | FR bị ảnh hưởng | Class | Owner | Artifact đích | Gate đến hạn | Trạng thái |
|---|---|---|---|---|---|---|---|
| `WD-TBD-001` | Ma trận purchase-line policy về yêu cầu Product và quantity/value semantics theo line class | `FR-PUR-001`, `FR-PUR-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Purchase policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-002` | Sourcing policy: RFQ/quotation threshold, category, comparison evidence và direct-purchase eligibility | `FR-PUR-001`, `FR-PUR-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Purchase policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-003` | Purchase Request/PO lifecycle, quan hệ change/cancel/replace và approval mapping | `FR-PUR-001`, `FR-PUR-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Purchase policy / FRS / Workflow config | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-004` | Semantics capture/validation tax value P1 trước khi operational Tax Engine được phê duyệt | `FR-PUR-002`, `FR-SAL-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Transaction policy / FRS + `NFR-DAT-001` alignment | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-005` | Receiving tolerance/threshold matrix, close-short/reopen semantics và exception authority | `FR-PUR-003` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Receiving policy / FRS / Workflow config | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-006` | Basic receiving quality/status catalog và service/non-stock receipt/performance evidence | `FR-PUR-003`, `FR-INV-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Receiving/Inventory policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-007` | Định nghĩa eligible inbound quantity/value và value component cho P1 Moving Average Cost | `FR-PUR-003`, `FR-INV-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Inventory costing policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-008` | Moving Average Cost edge-case policy: zero/negative stock, correction/reversal, backdated receipt và same-time ordering | `FR-INV-001`, `FR-PUR-003`, `FR-INV-003` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Inventory costing policy / FRS + DES | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-009` | Canonical P1 inventory status catalog và status-transition rule | `FR-INV-001`, `FR-PUR-003`, `FR-INV-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Inventory policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-010` | Reservation creation/release/consume/expiry, allocation priority và concurrency protection | `FR-INV-001`, `FR-SAL-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Inventory/Sales policy / FRS + DES | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-011` | P1 movement-type catalog, internal-transfer lifecycle và posting state | `FR-INV-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Inventory movement policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-012` | Negative-inventory và retrospective-adjustment policy cùng approval threshold | `FR-INV-003` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Inventory control policy / FRS / Workflow config | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-013` | Count snapshot/freeze method, checker separation và count/adjustment approval model | `FR-INV-003` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Inventory count policy / FRS + DES | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-014` | Controlled Sales source/channel catalog, guest-customer rule và source-reference format | `FR-SAL-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Sales policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-015` | Quotation/Sales Order lifecycle, conversion/change/cancel rule và manual discount/tax authorization | `FR-SAL-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Sales policy / FRS / Workflow config | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-016` | ATP inventory-scope selection, negative ATP display behavior và backorder/expected-delivery semantics | `FR-SAL-002` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | Sales/Inventory policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-017` | Rate-type catalog, same-context precedence và default rate-type selection | `FR-FIN-005` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FX policy / FRS | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-018` | Inverse/triangulated rate rule, effective-time overlap semantics và missing-rate exception policy | `FR-FIN-005` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FX policy / FRS + DES | `PROVISIONAL_READY` | `OPEN` |
| `WD-TBD-019` | Currency conversion rounding point và target-currency rule ngoài NFR baseline | `FR-FIN-005`, `FR-PRC-001` | `BUSINESS_DECISION` | Chủ sở hữu Sản phẩm/Yêu cầu | FX/Data Integrity policy / FRS + NFS | `PROVISIONAL_READY` | `OPEN` |

### 9.8 Gate provisional implementation-readiness của Wave D

Wave D có thể chuyển từ **detailed draft** sang **provisional implementation-ready** chỉ khi:

1. Cả chín parent FR của Wave D giữ traceability tới upstream BR và mọi acceptance-oriented child FR đều có explicit verification evidence path theo Section 1.3.
2. Purchase chạy được cả hai sourcing path policy-compliant: quotation comparison bắt buộc và controlled direct purchase, mà không tạo sourcing evidence giả.
3. Receiving hỗ trợ partial receipt, nhận diện over/under variance, ghi basic usable/non-usable status và giữ source/cost-impact traceability mà không kéo lot/serial genealogy hoặc landed-cost scope vào P1.
4. Inventory quantity reconcile được trên các dimension đã phê duyệt; reservation tách khỏi usable on-hand; quarantine/non-usable quantity bị loại khỏi ordinary allocation.
5. Internal transfer giữ một Company sở hữu; cross-Company movement bị từ chối trong P1 và không được ngụy trang thành intra-company transfer.
6. Count/adjustment evidence gồm discrepancy, reason, counter/checker, decision và quantity effect; P1 không tạo giả financial-period close semantics.
7. P1 Moving Average Cost tiếp tục ở cấp Group, do hệ thống sinh và quantity-weighted trên controlled eligible inbound quantity/value; không tạo Company cost override hoặc configurable valuation policy.
8. Sales capture giữ source/channel, Customer hoặc controlled guest context, Product và canonical base-price source, đồng thời không hàm ý external Integration, Promotion Engine hoặc Tax Engine.
9. P1 ATP chứng minh được là `usable on-hand - current reservation` trong Company/inventory scope đã resolve và loại toàn bộ future supply.
10. Exchange-rate resolution deterministic theo controlled policy; nếu thiếu required rate thì block official recording, trừ khi có controlled approved exception; historical rate evidence được bảo toàn.
11. Không còn Wave D TBD nào có `class=BUSINESS_DECISION`, `due_gate=PROVISIONAL_READY` và `status!=APPROVED`; blocker được tính từ register thay vì explicit ID list.
12. Authorization, lifecycle, approval, audit và responsibility evidence từ Wave A/B tiếp tục áp dụng cho Wave D transaction thay vì được tái triển khai thành rule cạnh tranh.
13. Review tạo blocking-ID snapshot từ register như audit evidence không mang tính normative.
14. Sau gate này, FRS có detailed specification coverage cho toàn bộ **32/32 P1 Business Requirements**; phần decomposition P1 còn lại tiếp tục trong companion NFS/DES/UAT/NFR verification và không tạo thêm functional-BR wave.

**v0.5.1 blocking snapshot (chỉ là báo cáo):** `WD-TBD-001`, `WD-TBD-002`, `WD-TBD-003`, `WD-TBD-004`, `WD-TBD-005`, `WD-TBD-006`, `WD-TBD-007`, `WD-TBD-008`, `WD-TBD-009`, `WD-TBD-010`, `WD-TBD-011`, `WD-TBD-012`, `WD-TBD-013`, `WD-TBD-014`, `WD-TBD-015`, `WD-TBD-016`, `WD-TBD-017`, `WD-TBD-018`, `WD-TBD-019`.
## 10. Điều phối package downstream và trình tự song song

Các artifact downstream của P1 được phát triển theo mô hình **Controlled Parallel**; danh sách artifact class dưới đây không phải một waterfall sequence. Authority và việc promotion sang controlled baseline tuân thủ Section 1.1-1.2.

1. `FRS-ERP-P1-001` tiếp tục functional decomposition/refinement cho 32 P1 Business Requirements theo Wave A-D.
2. P1 Đặc tả Phi chức năng (`NFS-*`) và Đặc tả Verification NFR (`NFR-TEST-*`) được viết **song song từ Wave A**, derive từ NFR source phù hợp; chúng không hard-depend vào FRS approval.
3. Wave E có synchronization checkpoint sau Wave A, B, C và D để tiếp nhận functional context mới mà không biến FR behavior thành NFR hoặc ngược lại.
4. P1 Đặc tả Thiết kế/Kiến trúc (`DES-*`/`DES-REVIEW-*`) hiện thực 3 P1 `AR-*` và các FR/policy context đủ ổn định; provisional artifact có thể dùng cho design/estimate/prototype nhưng không trở thành controlled release baseline khi upstream/FRS chưa approved.
5. P1 Đặc tả UAT (`UAT-*`) được xây từ explicit evidence path của Section 1.3; một UAT scenario có thể cover nhiều child FR khi trace được khai báo rõ.
6. Sau khi từng artifact thực sự được approved/configuration-controlled, cập nhật actual traceability trong `REQ-ERP-001` từ planned reference sang actual reference tương ứng.
7. P1 chỉ đạt exit khi baseline NFR có NFS và verification evidence đạt acceptance, đồng thời thỏa các functional exit criterion của Phase Plan.

## 11. Các gate review bản draft

- **Coverage gate:** đủ 32/32 P1 BR có parent `FR-*`, không có FR orphan và không thay đổi P1 scope allocation.
- **Scope gate:** không biến P2/P3 reference thành P1 hard dependency hoặc acceptance obligation.
- **Authority/lineage gate:** PLAN/REQ/FRS/downstream artifact tuân thủ field/semantic ownership tại Section 1.2; dependency change phải chạy lại dependency/phase validation và conflict phải được reconcile, không dùng nguyên tắc "latest wins".
- **Acceptance-evidence gate:** mọi acceptance-oriented child FR có ít nhất một explicit evidence path; việc child FR chỉ tồn tại đơn thuần không đủ để pass gate. Evidence có thể là UAT hoặc DES/integration review theo Section 1.3.
- **TBD gate:** blocker được tính từ `class + status + due_gate + target_artifact`; mọi `BUSINESS_DECISION` đến hạn `PROVISIONAL_READY` phải có trạng thái `APPROVED`. Blocking-ID snapshot được sinh khi review và chỉ là report/audit evidence.
- **Readiness gate:** `provisional_implementation_ready` không phải controlled implementation baseline. `controlled_implementation_ready` yêu cầu upstream áp dụng và FRS đều approved/configuration-controlled cùng các gate liên quan.
- **Traceability gate:** mọi planned UAT/NFS/DES ID được giữ đúng class; actual traceability chỉ được cập nhật sau approval/configuration control của artifact thực tế.
- **NFR parallelism gate:** Wave E NFS/NFR verification chạy song song từ Wave A và synchronize sau Wave A/B/C/D; không được diễn giải Section 10 thành waterfall bắt buộc hoàn tất FRS trước khi bắt đầu NFR work.
- **Governance mirror gate:** Section 5 AR/NFR chỉ là informative routing mirror và không trở thành normative duplicate cạnh tranh với controlled source.
- **Semantic gate:** không thay đổi canonical ownership, transaction ownership, authorization semantics, pricing precedence, P1 ATP, Moving Average Cost, FX, audit hoặc các invariant đã chốt trong User Requirements.

## 12. Ghi chú revision

### v0.5.1 — Hiệu chỉnh governance, evidence và readiness theo Controlled Parallel

- Áp dụng decision baseline Controlled Parallel `DEC-051-01..12` mà không thay đổi bất kỳ nội dung nào trong 32 parent FR, 216 acceptance-oriented child FR hoặc P1 scope allocation.
- Gán `Chủ sở hữu Sản phẩm/Yêu cầu` làm accountable owner của FRS và bổ sung semantics readiness `detailed_draft -> provisional_implementation_ready -> controlled_implementation_ready`.
- Bổ sung rule authority theo field/semantic cho PLAN, REQ, FRS và các downstream artifact DES/NFS/UAT, bao gồm bắt buộc revalidate dependency/phase và reconcile thay vì dùng rule "latest wins".
- Siết acceptance governance để mọi child FR đều resolve tới một explicit evidence path; sửa tám evidence gap của Wave A bằng sáu UAT scenario trace và hai DES/integration-review route.
- Chuyển toàn bộ 56 TBD của Wave A-D thành controlled register có `class`, `owner`, `target_artifact`, `due_gate` và `status`; gate logic giờ derive từ metadata của register và blocking snapshot tại thời điểm review thay vì hard-coded blocker list.
- Phân loại TBD hiện tại của v0.5.1 thành 54 `BUSINESS_DECISION` và 2 `DESIGN_DECISION` (`WA-TBD-006`, `WB-TBD-006`); revision này không tự giải quyết nội dung business của bất kỳ TBD nào.
- Làm rõ nội dung AR/NFR tại Section 5 chỉ là informative routing mirror, không phải normative duplicate.
- Chuyển downstream package sequencing sang Controlled Parallel: Wave E NFS/NFR verification bắt đầu từ Wave A với synchronization checkpoint sau Wave A/B/C/D và không hard-depend vào FRS approval.
- Bảo toàn toàn bộ P1 semantics bị giới hạn bởi source, later-phase conditional reference và rule promotion actual traceability.

### v0.5.0 — Bản draft đặc tả chức năng chi tiết Wave D

- Mở rộng các parent FR về Purchase, Inventory, Sales và phần nền tảng Exchange Rate còn lại của P1 thành detailed operational contract.
- Bổ sung sourcing/direct-purchase path bị giới hạn bởi source, PO commitment, partial/over/under receiving, inventory quantity/status/reservation, internal transfer, count/adjustment, current-inventory ATP và deterministic FX rate-resolution behavior.
- Bổ sung các ranh giới P1 transaction rõ ràng để invoice matching/landed cost/AP, configurable inventory valuation, lot/serial genealogy, intercompany movement, credit/returns, Promotion/Tax engine và realized/unrealized FX accounting không bị âm thầm kéo vào P1.
- Bổ sung decision table cho sourcing path, transfer ownership, ATP component và exchange-rate resolution, đồng thời giữ các threshold, lifecycle rule, reservation algorithm, costing edge case và rate precedence chưa được source định nghĩa trong `WD-TBD-*`.
- Bổ sung 19 controlled `WD-TBD-*` decision và Wave D implementation-readiness gate.
- Detailed functional coverage đạt toàn bộ 32/32 P1 Business Requirements; phần P1 decomposition còn lại là companion NFS/DES/UAT/NFR verification.

### v0.4.0 — Bản draft đặc tả chức năng chi tiết Wave C

- Mở rộng các parent FR về Product, Product UOM/conversion, Product Default Selling Price, Customer, Supplier và Pricing thành detailed operational contract.
- Bổ sung lifecycle/scope bị giới hạn bởi source, canonical master ownership, UOM effective conversion, Supplier sensitive-change approval và canonical price-resolution behavior.
- Bổ sung canonical pricing decision table rõ ràng, chỉ giữ precedence cross-level đã approved: Company Pricing Policy > Group Pricing Policy > Product Default Selling Price.
- Bổ sung `WC-TBD-*` decision cho same-level pricing tie, no-price-source behavior, Product/Customer/Supplier lifecycle rule, UOM conversion semantics, default-price cardinality và FX matching thay vì tự tạo rule không có trong source.
- Giữ ranh giới P1: COMBO/GIFT chỉ là classification, Product Moving Average Cost tiếp tục do hệ thống sinh, còn configurable inventory valuation/PLM/promotion capability nằm ngoài Wave C.
- Bảo toàn toàn bộ 32 P1 parent FR; Wave A và B không thay đổi, Wave D tiếp tục là decomposition baseline.

### v0.3.0 — Bản draft đặc tả chức năng chi tiết Wave B

- Mở rộng các parent FR thuộc Shared Controls, Central Audit, Nền tảng hệ thống và MDM thành detailed operational contract.
- Bổ sung document lifecycle/numbering bị giới hạn bởi source, conditional reconciliation/event scope, Shared Workflow governance, Central Audit boundary, locale/display separation, reference-data governance, ownership/override rule và sensitive-master change semantics.
- Bổ sung explicit Wave B acceptance scenario và `WB-TBD-*` register thay vì tự tạo numbering, workflow, audit, reconciliation, code-format, override hoặc master-lifecycle rule mà source chưa định nghĩa.
- Bảo toàn conditional applicability: event source phase sau, Document capability, IAM security-event monitoring và full Finance control không được promote vào P1.
- Bảo toàn toàn bộ 32 P1 parent FR; Wave A không thay đổi và Wave C-D tiếp tục là decomposition baseline.

### v0.2.0 — Bản draft đặc tả chức năng chi tiết Wave A

- Mở rộng các parent FR thuộc Organization, Employee và IAM base thành detailed operational contract.
- Bổ sung actor/trigger bị giới hạn bởi source, input/context, processing và validation rule, lifecycle/effective-time boundary, authorization/audit note và acceptance scenario.
- Bổ sung canonical authorization decision algorithm và decision table trực tiếp từ `BR-IAM-003`.
- Bổ sung explicit Wave A TBD register thay vì tự tạo hierarchy matrix, lifecycle enum, Assignment-overlap rule, Resource/Action catalog, Policy DSL hoặc sensitive-field catalog mà source chưa định nghĩa.
- Bảo toàn toàn bộ 32 P1 parent FR và companion routing cho 7 NFR cùng 3 Architectural Rules; tại v0.2.0, Wave B-D vẫn là decomposition baseline.

### v0.1.0 — Bản draft decomposition P1 FRS ban đầu

- Được tạo từ baseline `REQ-ERP-001 v1.0.2` P1_CORE.
- Bao phủ toàn bộ 32 P1 Business Requirements với đúng một parent `FR-*` cho mỗi requirement.
- Bảo toàn routing cho toàn bộ 7 P1 NFR và 3 P1 Architectural Rules.
- Bổ sung draft child FR handle từ upstream success criteria mà không đưa thêm business scope mới.
