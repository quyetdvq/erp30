---
document_id: REQ-ERP-001
title: "User Requirements - Dinktech Mini ERP V2.0"
phase: "Requirements Definition"
status: "draft"
version: "1.0.0"
owner: "Chủ sở hữu sản phẩm"
authority: "SSOT độc lập cho User Requirements trong phạm vi được nêu; requirement ở trạng thái approved có hiệu lực normative cho downstream artifacts."
change_type: "compatible"
depends_on: []
consumed_by:
  - "FRS-ERP - Functional Requirements Specification"
  - "NFS-ERP - Non-functional Specification"
  - "DES-ERP - Solution Design Specification"
  - "UAT-ERP - User Acceptance Test Specification"
  - "NFR-TEST-ERP - Non-functional Verification Specification"
tags:
  - user-requirements
  - erp
  - dinktech
last_reviewed: "2026-10-01"
---

## Revision History

| Version | Nội dung thay đổi chính | Traceability chính |
|---|---|---|
| **0.3.0** | Đồng bộ định hướng multi-group, Organization Context, PBAC, Finance Operations là nguồn dữ liệu vận hành và yêu cầu truy vết. | `BR-ORG-GROUP-001`, `BR-ORG-001`, `BR-IAM-002`, `BR-FIN-010`, `BR-IAM-008` |
| **0.4.0** | Chuẩn hóa ownership theo Company, phạm vi Authorization, giảm các miền ngoài trọng tâm và bổ sung các năng lực nền tảng. | `BR-MDM-OWNERSHIP-001`, `BR-IAM-002`, `BR-SYS-001`, `BR-WF-001`, `BR-AUD-001` |
| **0.6.0** | Tách Organization Context khỏi Department/Position; chuẩn hóa Employee Assignment, Data Ownership, Authorization/Approval, phạm vi CRM, Manufacturing/MES và Finance Operations. | `BR-ORG-001`, `BR-EMP-001`, `BR-EMP-002`, `BR-IAM-002`, `BR-IAM-009`, `BR-IAM-010`, `BR-WF-001`, `BR-MFG-*`, `BR-FIN-*` |
| **0.7.0** | Bổ sung Business Group, Workflow Core, Central Audit Trail và System Foundation dưới dạng requirement có traceability. | `BR-IAM-011`, `BR-WF-001`, `BR-AUD-001`, `BR-SYS-001` |
| **0.9.0** | Chuẩn hóa các requirement nền tảng dùng chung và chuẩn bị traceability sang FRS/UAT. | `BR-IAM-011`, `BR-WF-001`, `BR-AUD-001`, `BR-SYS-001` |
| **0.9.2** | Clean merge Vision v0.5.3: Group Business Entity, fixed Organization Context, ownership/override, Permission definition, Policy versioning, conflict resolution, Workflow Governance; sửa trace Organization, chuẩn hóa traceability Group Isolation, chuẩn hóa Business Group, chuyển HR/Payroll vào Phase 1, chuẩn hóa Employee Self Attendance + Daily Work Report + Leave Approval + Monthly Timesheet, làm rõ OEE là future/MES-sourced KPI; đồng thời tách rõ Product Master khỏi PLM/Engineering và chuẩn hóa cơ chế giá bán mặc định với Pricing & Promotion dạng override. | `BR-ORG-GROUP-002`, `BR-ORG-CONTEXT-002`, `BR-MDM-OWNERSHIP-001`, `BR-MDM-OVERRIDE-001`, `BR-IAM-PERMISSION-001`, `BR-IAM-POLICY-VERSION-001`, `BR-IAM-CONFLICT-001`, `BR-WF-GOV-001`, `BR-HRP-002`, `BR-HRP-003`, `BR-HRP-004`, `BR-HRP-005`, `BR-HRP-007`, `BR-HRP-011`, `BR-BI-006`, `BR-PRD-009`, `BR-PLM-003`, `BR-SAL-009`, `BR-MFG-001` |
| **0.9.3** | Hoàn thiện User Requirements như SSOT độc lập: bổ sung lifecycle và `target_phase`; chuẩn hóa domain ownership/MDM boundary; chốt Product, Customer, Supplier và Product Default Selling Price ở Group level; chuẩn hóa Employee foundation; loại hard dependency Manufacturing -> PLM; chuẩn hóa Shared Workflow cho business approval; bổ sung Ownership Matrix; sửa title/scope bị cụt hoặc lệch; đánh dấu TBD bắt buộc trước approval; chuẩn hóa traceability readiness cho FRS/NFS/UAT. | `BR-EMP-*`, `BR-MDM-*`, `BR-PRD-*`, `BR-CUS-*`, `BR-PUR-*`, `BR-MFG-009`, `BR-WF-001`, `NFR-*` |
| **0.9.4** | Chuẩn hóa consistency/traceability nội bộ: chốt ownership Employee/Assignment/Department/Position; bổ sung Architectural Rule cho ownership reference/common data; chốt Group/Company Pricing Policy và precedence; chuẩn hóa hard dependency/approval gate; tách Department lifecycle khỏi Organization; tách IAM audit khỏi Central Audit; thu hẹp Organization Context governance; bỏ Budget khỏi Purchase hiện tại; chuẩn hóa canonical price resolution; tách NFS/NFR-TEST artifact model; giữ `target_phase: null` và các NFR acceptance target còn TBD. | `AR-DATA-OWNERSHIP-001`, `BR-EMP-*`, `BR-MDM-*`, `BR-CUS-003`, `BR-SAL-002`, `BR-SAL-009`, `BR-ORG-008`, `BR-ORG-CONTEXT-002`, `BR-IAM-008`, `BR-AUD-001`, `BR-PUR-004`, `NFR-*` |
| **0.9.5** | Đóng toàn bộ TBD còn lại bằng acceptance values đã được Product Owner xác nhận làm **proposed baseline**: traceability sampling, availability/continuity, RPO/RTO, performance/concurrency, near-real-time SLA, capacity profile, precision/rounding, retention governance, finance/manufacturing freshness và browser support baseline. Các giá trị này chỉ có hiệu lực normative khi requirement tương ứng đạt `approved`; giữ `target_phase: null` và lifecycle `draft` cho đến bước review/phase planning tiếp theo. | `BR-INV-007`, `NFR-AVL-001`, `NFR-AVL-002`, `NFR-AVL-004`, `NFR-PER-001`, `NFR-PER-002`, `NFR-PER-003`, `NFR-DAT-001`, `NFR-CMP-001`, `NFR-FRESH-002`, `NFR-FRESH-003`, `NFR-PLT-001` |
| **0.9.6** | Clean baseline NFR/NFS theo ranh giới BR/FR = business capability/rule, NFR/NFS = quality attribute/measurable constraint, AR = architecture invariant và DES = realization. Retire 7 NFR/NFS trùng hoặc sai lớp; merge continuity reconciliation, data-quality validation, legal hold và payroll official-state về owner phù hợp; chuyển transaction/domain-boundary invariant thành controlled AR; rewrite Configurability và Audit quality; tách rõ Report Freshness khỏi Source Freshness; chuẩn hóa controlled `source_refs`/Source & Decision Register; xác lập Pricing là canonical owning domain; tách intra-company transfer khỏi hai loại `INTERCOMPANY_TRANSFER`/`INTERCOMPANY_SALE`; chuẩn hóa notification title, locale/business-currency boundary và conditional Customer credit/payment approval. Giữ `target_phase: null` và lifecycle `draft` để review/phase planning tiếp theo. | `NFR-SEC-003`, `NFR-AVL-003`, `NFR-DAT-002`, `NFR-DAT-003`, `NFR-MNT-003`, `NFR-CMP-002`, `NFR-FRESH-004`, `NFR-MNT-001`, `NFR-AUD-*`, `NFR-PER-002`, `NFR-FRESH-*`, `BR-CUS-002`, `BR-CUS-003`, `BR-PUR-009`, `BR-SAL-009`, `BR-INV-012`, `BR-INV-014`, `BR-FIN-017`, `BR-INF-007`, `BR-ORG-005`, `BR-SYS-001`, `AR-DATA-OWNERSHIP-001`, `AR-DOM-001`, `AR-TXN-001`, `DEC-*`, `REG-*` |
| **0.9.7** | Content-clean consistency/traceability release theo các quyết định review D01-D20: chuẩn hóa Company-only transaction ownership; Purchase item model hỗ trợ Stock/Non-stock/Service/Expense, Supplier không bắt buộc ở Purchase Request và RFQ/quotation là conditional; tách Reporting và Business Continuity khỏi hard dependency Data Exchange; chuẩn hóa optional MRP sources và conditional labor-cost allocation targets; bổ sung Pricing currency/FX semantics; refactor business semantics/locale/work-calendar boundary; generic hóa các requirement chưa có controlled legal source; formalize default-deny Group Master override; bổ sung explicit review/approval gates và phase taxonomy `P1_CORE`/`P2_EXTENDED`/`P3_ADVANCED`/`FUTURE`. Giữ `target_phase: null`; sau full integrity audit, controlled items đạt review gate chuyển sang `reviewed` trong khi document vẫn `draft`. | `BR-ORG-GROUP-001`, `BR-PUR-002`, `BR-PUR-003`, `BR-PUR-004`, `BR-BI-001`, `NFR-AVL-002`, `BR-MFG-003`, `BR-HRP-009`, `BR-PRD-009`, `BR-CUS-003`, `BR-SAL-009`, `NFR-USE-003`, `BR-MDM-OVERRIDE-001`, `BR-MDM-010`, `BR-FIN-015`, `BR-FIN-016`, `BR-HRP-002`, `BR-HRP-011`, `BR-INF-004`, `BR-DQ-005`, `DEC-*` |
| **0.9.8** | Đóng finding D21-D32 trước Phase Planning: chốt intercompany process theo component transaction Company-owned và `intercompany_reference`; canonical owner Supplier; Group Business Entity ↔ GROUP Context 1:1; User Group Access Membership và dynamic Assignment resolution; Department condition trong PBAC; deterministic Authorization với `policy_required`; reporting theo approved phase scope; Company Business Calendar; RD Code là specialization của generic identifier; harden Source & Decision Register; retire `NFR-SEC-004` sai lớp và thay bằng `BR-SEC-MON-001`; chuẩn hóa retired lifecycle/TBD rule. Giữ document `draft`, active controlled items `reviewed`, `target_phase: null` để bắt đầu Phase Planning sau integrity audit. | `AR-TXN-001`, `BR-INV-014`, `BR-FIN-017`, `BR-PUR-001`, `BR-ORG-GROUP-002`, `BR-ORG-CONTEXT-002`, `BR-IAM-001`, `BR-IAM-002`, `BR-IAM-010`, `BR-IAM-012`, `BR-IAM-PERMISSION-001`, `BR-IAM-CONFLICT-001`, `BR-BI-001`, `BR-ORG-009`, `BR-HRP-002`, `NFR-FRESH-005`, `BR-MDM-009`, `BR-SEC-MON-001`, `NFR-SEC-004`, `DEC-*` |
| **0.9.9** | Đóng finding D33-D39 và tạo Content-Clean Candidate trước Phase Planning: tách rõ `Introduced in` / `Decision date` / `Effective from` / `Source version` trong Source & Decision Register; chuẩn hóa hard dependency so với conditional applicability/source; thêm hard dependency Finance cho Sales credit control; gỡ coupling POS fallback với Data Exchange và áp dụng continuity semantics độc lập; gỡ Audit NFR khỏi Document/BI prerequisite; chuyển tax-rounding business rule về Finance và giữ Data NFR ở lớp precision/integrity; chuẩn hóa canonical Pricing terminology; xác lập ID prefix là historical traceability namespace, không phải ownership authority. Giữ document `draft`, active controlled items `reviewed`, `target_phase: null`; đủ điều kiện bắt đầu Phase Planning sau khi integrity audit v0.9.9 đạt gate. | `BR-CUS-004`, `BR-SAL-004`, `BR-SAL-008`, `BR-FIN-012`, `BR-COM-007`, `NFR-AVL-002`, `NFR-AUD-002`, `NFR-AUD-003`, `BR-FIN-006`, `NFR-DAT-001`, `BR-CUS-003`, `BR-PUR-001`, `DEC-CONTINUITY-001` |

> Revision History chỉ mô tả thay đổi và liên kết tới Requirement ID. Các từ như “chốt/xác nhận” trong lịch sử chỉ mô tả việc ghi nhận quyết định tại thời điểm sửa tài liệu và **không thay thế lifecycle**; Requirement/AR chỉ có hiệu lực normative khi `status: approved`. BR/NFR phải có đầy đủ `status`, `priority`, `target_phase`, `success_criteria`, `depends_on`, `implemented_by` và `verified_by`; AR tuân theo metadata contract riêng với `realized_by` thay cho `implemented_by` và không bắt buộc `priority`. Requirement có TBD bắt buộc cho acceptance hoặc boundary decision không được chuyển sang `reviewed` hoặc `approved`.

# User Requirements - Dinktech Mini ERP V2.0

## 1. Hợp đồng tài liệu

| Thuộc tính | Nội dung |
|---|---|
| **Mục đích** | Xác định tập User Requirements có ID, trạng thái, mức ưu tiên, target phase, tiêu chí thành công và traceability để làm cơ sở phân rã chức năng, thiết kế giải pháp và nghiệm thu. |
| **Phạm vi** | Các yêu cầu người dùng/nghiệp vụ và yêu cầu phi chức năng ở góc nhìn doanh nghiệp cho Dinktech Mini ERP V2.0 trong phạm vi được mô tả bởi chính tài liệu này. |
| **Đầu vào** | Nhu cầu người dùng, mục tiêu nghiệp vụ, quyết định sản phẩm và các năng lực quản trị được ghi nhận trực tiếp trong quá trình Requirements Definition; authority normative vẫn do lifecycle của controlled BR/NFR/AR quyết định. |
| **Đầu ra** | Bộ User Requirements độc lập làm cơ sở cho FRS, NFS, thiết kế, triển khai, UAT và NFR Verification. |
| **Phụ thuộc tài liệu** | Tài liệu này không có normative dependency vào Vision hoặc tài liệu thiết kế. Các tài liệu cấp cao khác được đồng bộ theo User Requirements sau khi requirement tương ứng được chốt. |
| **Phụ thuộc requirement** | `depends_on` chỉ biểu diễn Hard Dependency / Hard Prerequisite giữa các requirement. Requirement downstream không được `approved` trước dependency; trường này không mặc định là thứ tự triển khai. Nếu thiếu source capability làm requirement không thể thực hiện bản chất cốt lõi thì source đó phải là hard dependency; nếu requirement vẫn hoạt động với subset source trong approved scope thì đó là conditional applicability/source và phải mô tả ngoài `depends_on`. |
| **SSOT / Authority** | Requirement item ở trạng thái `approved` là SSOT normative cho yêu cầu đó. Requirement `draft` hoặc `reviewed` chưa phải baseline cuối cùng. Document-level `status` chỉ chuyển sang `approved` khi toàn bộ requirement trong phạm vi tài liệu đã được chốt. |
| **Traceability** | `implemented_by` và `verified_by` là danh sách traceability và có thể mở rộng quan hệ 1:N. Business Requirement thường phân rã sang `FR-*`/FRS và xác minh bằng `UAT-*`; Non-functional Requirement phân rã sang `NFS-*`/NFS và xác minh bằng `NFR-TEST-*`/NFR-TEST. Placeholder hiện tại không giới hạn một requirement chỉ được ánh xạ tới một artifact downstream. |
| **Backward traceability / `source_refs`** | `source` là nhãn mô tả nguồn nghiệp vụ; `source_refs` là danh sách controlled references và là optional với requirement thông thường. `source_refs` bắt buộc khi requirement dựa trên legal/regulatory baseline, product/architecture decision, approved business policy, quantitative acceptance baseline có quyết định nguồn, external standard/contract, hoặc cross-document architectural decision như ADR. Mỗi reference phải resolve tới Source & Decision Register dưới đây hoặc một controlled external artifact có version/locator rõ. |
| **Architectural Rules** | `AR-*` là controlled architecture constraint, không phải Business Requirement hoặc Non-functional Requirement. AR dùng cùng lifecycle `draft -> reviewed -> approved -> superseded`, có `target_phase`, `depends_on`, `realized_by` và `verified_by`; AR được hiện thực trong DES và không sinh `FR-*` hoặc `NFS-*`. |

### 1.1 Requirement lifecycle và phase governance

Requirement sử dụng vòng đời thống nhất:

`draft -> reviewed -> approved -> superseded`

- `draft`: đang phân tích hoặc còn quyết định chưa chốt.
- `reviewed`: đã đạt content review gate về nội dung, consistency, boundary và traceability nhưng chưa trở thành baseline normative; requirement ở trạng thái này có thể vẫn có `target_phase: null` trước Phase Planning.
- `approved`: requirement đã được gán `target_phase`, dependency closure đã đạt và requirement đã được chốt làm nguồn bắt buộc cho FRS/NFS/DES/UAT tương ứng.
- `superseded`: requirement không còn hiệu lực do được thay thế; ID cũ vẫn được bảo toàn để giữ traceability lịch sử.

Architectural Rule (`AR-*`) sử dụng cùng lifecycle. AR ở trạng thái `approved` là normative architecture constraint cho DES và các artifact triển khai liên quan; AR `draft`/`reviewed` chưa phải baseline cuối cùng. AR đã phát hành không được tái sử dụng ID khi bị superseded.

Mỗi requirement có trường `target_phase`. Trường này xác định **phase đầu tiên requirement phải được đáp ứng**; requirement tiếp tục có hiệu lực ở các phase sau trừ khi bị superseded. Trong v0.9.9, `target_phase` tiếp tục để `null` trong content-clean release và chỉ được gán ở bước Phase Planning sau khi requirement đạt `reviewed`.

Flow governance chuẩn là:

`Content Clean -> reviewed -> Phase Planning -> assign target_phase -> dependency closure -> approved theo phase`

Một phase có thể có approved baseline để triển khai FRS/NFS/DES/UAT trong khi requirement của phase khác còn `draft` hoặc `reviewed`.

Requirement có `TBD` trong nội dung bắt buộc để nghiệm thu **không được chuyển sang `reviewed` hoặc `approved`**. TBD phải được giải quyết hoặc được chuyển thành một quyết định/requirement có tiêu chí nghiệm thu rõ trước khi vượt review gate.

`depends_on` chỉ biểu diễn **Hard Dependency / Hard Prerequisite**. Một requirement chỉ được chuyển sang `approved` khi toàn bộ requirement được liệt kê trong `depends_on` đã ở trạng thái `approved`. Nếu dependency đã `superseded`, replacement có hiệu lực phải được xác định và đạt `approved` trước khi requirement downstream được approved. Quan hệ tham khảo, integration tùy chọn hoặc dependency chỉ phát sinh khi một capability khác được triển khai không được đưa vào `depends_on`; các quan hệ đó phải được mô tả bằng applicability/description tương ứng.

#### 1.1.1 Gate `draft -> reviewed`

Một controlled BR/NFR/AR chỉ được chuyển sang `reviewed` khi đồng thời đáp ứng:

1. ID, type và schema metadata hợp lệ.
2. Không còn TBD bắt buộc cho acceptance hoặc boundary decision.
3. Canonical owning domain/responsibility được xác định khi requirement có khái niệm cần ownership.
4. `depends_on` chỉ còn Hard Dependency / Hard Prerequisite thực sự; relation conditional/optional được mô tả bằng applicability/description.
5. Không có dangling reference, duplicate ID hoặc dependency cycle.
6. Success criteria đủ rõ để downstream artifact có thể phân rã và xác minh mà không phải tự tạo thêm business rule cốt lõi.
7. Legal/regulatory baseline, external standard/contract, product/architecture decision, approved business policy hoặc quantitative acceptance decision có `source_refs` theo quy tắc của tài liệu.
8. Không contradiction với controlled BR/NFR/AR khác.
9. Cross-domain boundary đủ rõ để không tạo competing SSOT hoặc ownership ngầm định.

#### 1.1.2 Gate `reviewed -> approved`

Một controlled BR/NFR/AR chỉ được chuyển từ `reviewed` sang `approved` khi:

1. `target_phase` đã được gán một giá trị hợp lệ trong taxonomy của tài liệu.
2. Toàn bộ Hard Dependency trong `depends_on` đã `approved`, hoặc replacement approved hợp lệ đã được xác định cho dependency superseded.
3. Internal controlled decision được requirement tham chiếu phải có `status: approved`; legal/external reference phải còn hiệu lực và được tái xác minh tại thời điểm approval khi source yêu cầu. Source ở trạng thái `proposed` chỉ đủ hỗ trợ `draft`/`reviewed`, không đủ làm authority cho normative approval.
4. Scope owner/Product Owner xác nhận requirement thuộc approved scope của phase tương ứng.

Document-level `status` vẫn có thể là `draft` trong khi một phần controlled items đã `reviewed` hoặc `approved`; document chỉ chuyển `approved` khi toàn bộ requirement trong phạm vi tài liệu đã được chốt theo contract.

### 1.2 Priority và target phase

`priority` và `target_phase` là hai chiều độc lập:

- `MUST`: bắt buộc khi capability/requirement đó nằm trong scope đã được phê duyệt.
- `SHOULD`: giá trị cao và nên thực hiện nếu không có lý do được chấp thuận để trì hoãn.
- `COULD`: có thể thực hiện khi có giá trị và nguồn lực phù hợp.
- `target_phase`: phase đầu tiên requirement phải được đáp ứng; không được suy ra trực tiếp từ priority.

Taxonomy `target_phase` chính thức:

| Giá trị | Ý nghĩa |
|---|---|
| `P1_CORE` | Nền tảng và năng lực core bắt buộc cho baseline vận hành đầu tiên. |
| `P2_EXTENDED` | Năng lực mở rộng vận hành sau core. |
| `P3_ADVANCED` | Năng lực nâng cao sau khi các nền tảng phụ thuộc đã ổn định. |
| `FUTURE` | Requirement đã được công nhận nhưng chưa cam kết vào ba phase triển khai đầu. |

`null` chỉ được dùng trước hoặc trong Phase Planning; controlled item không được chuyển sang `approved` khi `target_phase` còn `null`. Requirement tiếp tục có hiệu lực ở các phase sau phase đầu tiên của nó trừ khi bị `superseded`.

### 1.3 Source & Decision Register

`source_refs` cung cấp backward traceability tới quyết định/policy/nguồn có kiểm soát. Register này không tự làm requirement trở thành normative; authority của requirement vẫn do lifecycle `status` quyết định. Khi một quyết định kiến trúc được ban hành ở tài liệu khác (ví dụ ADR), requirement/AR chịu ảnh hưởng phải tham chiếu trực tiếp ID/version của ADR đó trong `source_refs` và register phải lưu locator tương ứng.

`Introduced in` là version của tài liệu User Requirements mà reference lần đầu được ghi nhận có kiểm soát; `Decision date` là ngày quyết định nội bộ được phê duyệt; `Effective from` là ngày policy/decision/source bắt đầu có hiệu lực và không được dùng để lưu document version; `Source version` là version/edition của nguồn hoặc decision artifact. Với legacy reference không đủ bằng chứng để xác định first-introduction chính xác, `Introduced in` để `—` thay vì suy đoán.

| Ref ID | Type | Status | Owner | Approved by | Introduced in | Decision date | Effective from | Source version | Supersedes | Locator | Summary |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `DEC-ERP-FOUNDATION-001` | Product / architecture | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Baseline Group/Company, Organization Context, ownership levels, IAM/Workflow. Business Transaction thuộc đúng một Company; Group chỉ suy ra qua Company. Group Business Entity là canonical identity; `GROUP` Organization Context là representation 1:1 cho hierarchy/scope. Supplier canonical owner là `Supplier`. Group Master override áp dụng default deny. |
| `DEC-PRICING-001` | Product / domain | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | `Pricing` là canonical owning domain của Group/Company Pricing Policy; precedence `Company Pricing Policy > Group Pricing Policy > Product Default Selling Price`; mỗi price source có currency và conversion giữ original value/currency/rate/converted value. |
| `DEC-INTERCO-001` | Business / domain | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Intra-company transfer khác intercompany process. Intercompany có `INTERCOMPANY_TRANSFER` và `INTERCOMPANY_SALE`; mỗi component Business Transaction vẫn thuộc đúng một Company và các transaction hai phía được correlation bằng `intercompany_reference`, không có một transaction chung sở hữu bởi hai Company. |
| `DEC-SENSITIVE-MDM-001` | Business policy | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Sensitive master-data changes sử dụng policy/rule để xác định approval; Supplier bank/tax/payment changes bắt buộc Shared Workflow; Customer Payment Terms/Credit Limit dùng Shared Workflow khi rule/threshold/policy yêu cầu. |
| `DEC-PURCHASE-001` | Business / domain | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Purchase Request có thể tạo trước Supplier; purchase line hỗ trợ `Stock Item`, `Non-stock Item`, `Service`, `Expense`; Product Master không bắt buộc cho mọi line; RFQ/quotation conditional theo policy và direct PO được phép khi policy cho phép. |
| `DEC-REPORTING-001` | Business / domain | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Reporting dùng shared capability cho các domain nằm trong approved scope của phase; external integration chỉ là conditional source, và domain chưa nằm trong scope không chặn report của domain đã sẵn sàng. |
| `DEC-CONTINUITY-001` | Business / continuity | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Controlled fallback và reconciliation sau downtime là continuity control độc lập, không hard-depend external Data Exchange/Integration. |
| `DEC-MFG-PLANNING-001` | Business / domain | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Manufacturing planning dùng BOM, capacity/work calendar, inventory và demand/supply source có hiệu lực; Sales/Purchase là conditional source, không phải Hard Prerequisite. |
| `DEC-HRP-ALLOCATION-001` | Business / domain | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Labor-cost allocation target `Department`, `Project`, `Production Order` là các target conditional độc lập; dependency chỉ phát sinh khi target tương ứng được sử dụng. |
| `DEC-SYS-LOCALE-001` | Product / architecture | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Company sở hữu business/base/functional currency và business timezone; System/User Settings chỉ sở hữu locale/display formatting; Employee Work Calendar không phải locale/display setting. |
| `DEC-IAM-AUTHZ-001` | Product / authorization | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | User Identity là platform principal. Ordinary ERP User truy cập Group qua explicit Group Access Membership reference Employee của Group; active Employee Assignment được resolve theo Company/context tại authorization time. Department là Policy Condition, không phải Organization Context. Canonical Resource/Action Permission sở hữu `policy_required`; Authorization dùng Permission gate, Policy effects và deterministic NO_MATCH/default behavior. |
| `DEC-ORG-CALENDAR-001` | Business / organization | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Company Business Calendar thuộc Organization/Foundation và xác định business day/non-business day, Company holiday và deadline/cutoff calculation. Employee Work Calendar thuộc HR và có thể bổ sung lịch theo employee/assignment policy. |
| `DEC-MDM-IDENTIFIER-001` | Business / data governance | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Generic business-code governance thuộc `BR-MDM-002`; RD Code là specialization của identifier standard, không tạo master identity cạnh tranh. Supplier thuộc phạm vi RD Code theo requirement hiện hành. |
| `DEC-SEC-MON-001` | Business / security | `approved` | Chủ sở hữu sản phẩm | Chủ sở hữu sản phẩm | — | 2026-10-01 | 2026-10-01 | 0.9.8 | — | `REQ-ERP-001 §1.3` | Security event detection/alert/evidence là business capability và được quản lý bằng BR/FR; quantitative quality target nếu phát sinh phải dùng NFR ID mới, không hồi sinh `NFR-SEC-004`. |
| `DEC-NFR-BASELINE-001` | Product Owner acceptance | `proposed` | Chủ sở hữu sản phẩm | — | 0.9.5 | — | — | 0.9.8 | — | `REQ-ERP-001 §1.3` | Quantitative acceptance values được xác nhận làm proposed baseline; chỉ có thể hỗ trợ normative approval sau khi decision này được nâng thành `approved`. |
| `REG-VN-ACC-RET-001` | Legal / regulatory | `external-reference` | Cơ quan ban hành | — | 0.9.6 | — | — | `41/VBHN-VPQH (2026)` / `NĐ 174/2016` | — | `NFR-CMP-001`; locator/version/effectiveness phải revalidate trước compliance approval | Baseline lưu trữ tài liệu kế toán Việt Nam được trích trong `NFR-CMP-001`; locator/version/effectiveness phải được tái xác minh khi phê duyệt compliance baseline hoặc khi pháp luật thay đổi. |

**Status taxonomy:** `proposed` / `approved` / `superseded` / `external-reference`.

**Authority rule:** Internal decision ở trạng thái `approved` có thể làm controlled authority cho requirement `approved`. Decision ở trạng thái `proposed` chỉ hỗ trợ `draft`/`reviewed`. Legal/external reference chỉ hỗ trợ approval sau khi locator/version/effectiveness được revalidate theo governance tương ứng.
## 2. Data & Domain Ownership Principles

### 2.1 Ownership levels

| Entity Level | Ownership |
|---|---|
| System Master | Dữ liệu dùng chung toàn hệ thống, không thuộc một Organization cụ thể. |
| Group Master | Dữ liệu dùng chung cho các Company trong cùng Group. |
| Company Master | Dữ liệu phục vụ riêng một Company. |
| Transaction Data | Thuộc đúng một Company và chịu phân quyền theo Organization Context. |

### 2.2 Domain ownership principle

Business entity phải có đúng một owning domain ở cấp nghiệp vụ. MDM không trở thành owner thay thế của Product, Customer, Supplier hoặc Employee; MDM chịu trách nhiệm cho standard, governance, common primitives, coding/identity rules, data-quality rules và cơ chế chia sẻ/override dùng chung.

Field Level Override đối với Group Master áp dụng theo nguyên tắc **default deny**: Company chỉ được override field khi owning-domain requirement/policy khai báo rõ field đó là overrideable. Việc Company sử dụng Group Master không tự tạo quyền override; override hợp lệ không tạo master cạnh tranh và không thay đổi canonical owning domain.

Các quyết định ownership được ghi nhận trong v0.9.4 và được quản trị bởi controlled BR/AR bên dưới:

| Business Entity / Data Type | Owning Domain | Ownership Level | Company usage / override | Governing Requirement |
|---|---|---|---|---|
| Product Master | Product | Group Master | Company trong Group sử dụng; field override chỉ khi policy cho phép. | `BR-PRD-001`, `BR-MDM-OVERRIDE-001` |
| Product Default Selling Price | Product | Group Master | Company sử dụng giá mặc định Group khi không có Company/Group Pricing Policy phù hợp; không có Company default-price field override mặc định. | `BR-PRD-009`, `BR-SAL-009` |
| Customer | Customer | Group Master | Company trong Group sử dụng; field override chỉ khi policy cho phép. | `BR-CUS-001`, `BR-MDM-OVERRIDE-001` |
| Supplier | Supplier | Group Master | Company trong Group sử dụng; Purchase là consuming/process domain; field override chỉ khi policy cho phép. | `BR-PUR-001`, `BR-MDM-OVERRIDE-001` |
| Employee | Employee | Group Master | Employee là canonical master dùng trong Group; HRP và IAM chỉ mở rộng/tham chiếu. | `BR-EMP-001` |
| Employee Assignment | Employee | Company Master | Mỗi Assignment thuộc một Company, tham chiếu Organization Context, Department, Position và thời gian hiệu lực. | `BR-EMP-001` |
| Department | Employee | Company Master | Department có hierarchy/lifecycle riêng trong Company; không tự sinh Organization Scope hoặc Permission. | `BR-EMP-002` |
| Position | Employee | Group Master | Position là danh mục chức danh/level dùng chung trong Group; được Assignment tham chiếu. | `BR-EMP-002` |
| Warehouse | Organization | Company Master | Thuộc Organization Context của Company. | `BR-ORG-002` |
| Business Transaction | Domain phát sinh giao dịch | Transaction Data | Thuộc đúng một Company; Group chỉ tổng hợp qua Company. | `BR-ORG-GROUP-002`, `BR-MDM-OWNERSHIP-001` |
| Group Pricing Policy / Price List | Pricing | Group Master | Dùng chung cho các Company trong Group; Company không sửa trực tiếp Group Pricing Policy. | `BR-CUS-003`, `BR-SAL-009` |
| Company Pricing Policy / Price List | Pricing | Company Master | Chỉ áp dụng trong Company sở hữu và có precedence cao hơn Group Pricing Policy khi cùng thỏa điều kiện. | `BR-CUS-003`, `BR-SAL-009` |

`Pricing` là canonical owning domain của Group/Company Pricing Policy. `BR-CUS-003` giữ ID lịch sử để bảo toàn traceability nhưng khai báo `owning_domain: Pricing`; Sales chỉ consume Pricing decision khi xác định giá bán.

**Historical ID namespace rule:** Prefix trong Requirement ID là traceability namespace lịch sử và ổn định, **không xác định canonical owning domain**. Khi prefix khác owner hiện hành, `owning_domain` và Ownership Matrix là authority. Không rename ID chỉ để khớp owner mới. Downstream FRS phải giữ Requirement ID gốc để traceability và mang `owning_domain` metadata (hoặc field ownership tương đương có kiểm soát) khi prefix có thể gây hiểu nhầm về owner. `BR-CUS-003` (`owning_domain: Pricing`) và `BR-PUR-001` (`owning_domain: Supplier`) là hai trường hợp chuẩn của rule này.

### 2.3 Controlled Architectural Rules

Các `AR-*` dưới đây là **controlled architecture constraints**. Chúng có lifecycle riêng, được quản trị trong User Requirements để tránh lệch giữa Requirements và Architecture, nhưng không được phân rã sang FRS/NFS. `realized_by` trỏ tới artifact DES dự kiến; `verified_by` trỏ tới bằng chứng review/conformance dự kiến.

```yaml
id: AR-DATA-OWNERSHIP-001
title: "Reference/Common Data Ownership Rule"
type: architecture-rule
status: reviewed
target_phase: null
source: "Data & Domain Ownership Principles"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
description: "Reference/common data phải được phân loại ownership theo ý nghĩa và phạm vi sử dụng; MDM cung cấp governance nhưng không tự trở thành owning domain của business entity."
constraints:
  - "Reference/common data có ý nghĩa chuẩn độc lập với một doanh nghiệp cụ thể và cần dùng thống nhất toàn hệ thống được quản lý ở System Master."
  - "Taxonomy, classification, coding set hoặc policy/reference data do doanh nghiệp định nghĩa và chia sẻ giữa các Company trong cùng Group được quản lý ở Group Master."
  - "Dữ liệu chỉ có ý nghĩa hoặc hiệu lực trong một Company được quản lý ở Company Master khi requirement của owning domain xác định rõ nhu cầu Company-specific."
  - "Mỗi common primitive/reference data phải được phân loại ownership trước khi được công bố sử dụng; ngoại lệ phải được owning domain ghi rõ cùng lý do nghiệp vụ."
depends_on: []
realized_by:
  - "DES-AR-DATA-OWNERSHIP-001"
verified_by:
  - "DES-REVIEW-AR-DATA-OWNERSHIP-001"
```

```yaml
id: AR-DOM-001
title: "Domain Boundary and Canonical Ownership Rule"
type: architecture-rule
status: reviewed
target_phase: null
source: "Architecture Governance"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
description: "Mỗi governed business concept phải có canonical owning domain và ranh giới trách nhiệm rõ; cross-domain use không được tạo SSOT cạnh tranh hoặc thay đổi ownership một cách ngầm định."
constraints:
  - "Mỗi governed business concept hoặc business entity xác định đúng một canonical owning domain ở cấp nghiệp vụ."
  - "Domain khác có thể tham chiếu hoặc tiêu thụ dữ liệu nhưng không tạo master/SSOT cạnh tranh cho cùng ý nghĩa."
  - "Cross-domain dependency và responsibility boundary phải được mô tả rõ trong requirement/design liên quan."
  - "Thay đổi trong một domain không được âm thầm thay đổi ownership, semantic hoặc invariant của domain khác."
depends_on: []
realized_by:
  - "DES-AR-DOM-001"
verified_by:
  - "DES-REVIEW-AR-DOM-001"
```

```yaml
id: AR-TXN-001
title: "Business Transaction Context Invariant"
type: architecture-rule
status: reviewed
target_phase: null
source: "Transaction Foundation"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
  - "DEC-INTERCO-001"
description: "Mọi business transaction thuộc phạm vi ERP phải mang đủ context tối thiểu để xác định ownership, thời điểm nghiệp vụ, lifecycle state và trách nhiệm thực hiện. Cross-company business process có thể phối hợp nhiều transaction nhưng không thay đổi invariant Company ownership của từng transaction thành phần."
constraints:
  - "Mỗi business transaction thuộc đúng một owning Company; Group chỉ được suy ra qua Company và không sở hữu transaction trực tiếp."
  - "Một cross-company business process có thể phối hợp nhiều Business Transaction thuộc các Company khác nhau, nhưng mỗi Business Transaction thành phần vẫn thuộc đúng một owning Company."
  - "`intercompany_reference` chỉ là correlation/reference key giữa các transaction hai phía; nó không phải Business Transaction và không trở thành owner của tồn kho, công nợ hoặc financial transaction."
  - "Mỗi business transaction xác định được business timestamp hoặc accounting/operational effective time phù hợp với loại giao dịch."
  - "Mỗi business transaction xác định được lifecycle state có hiệu lực."
  - "Mỗi business transaction xác định được responsible actor/User hoặc system context phù hợp với loại giao dịch."
depends_on: []
realized_by:
  - "DES-AR-TXN-001"
verified_by:
  - "DES-REVIEW-AR-TXN-001"
```

## 3. Baseline Principles of Dinktech ERP V2.0

Các nguyên tắc dưới đây là **tóm tắt dẫn xuất** từ các controlled BR/NFR/AR trong tài liệu để hỗ trợ đọc nhanh; chúng không tạo một nguồn normative song song. Khi có khác biệt, requirement/rule có ID và lifecycle tương ứng là nguồn có thẩm quyền. Tài liệu không phụ thuộc vào Vision để các controlled item có hiệu lực:

- Hệ thống hỗ trợ nhiều Group độc lập trong cùng một nền tảng quản trị.
- Organization Context sử dụng tập loại cố định: GROUP, COMPANY, BRANCH, PLANT, WAREHOUSE; hierarchy có thể bỏ qua cấp trung gian khi mô hình vận hành cho phép.
- Group là Business Entity phục vụ quản trị/tổng hợp nhưng không sở hữu transaction trực tiếp; mọi transaction nghiệp vụ thuộc đúng một Company.
- Phân biệt Permission, phạm vi dữ liệu, trạng thái dữ liệu và điều kiện truy cập; Authorization dùng Profile Permission kết hợp Policy Rule và Policy không tạo thêm quyền ngoài Profile.
- Business approval cần governance/audit sử dụng Shared Workflow; state transition thuần túy của module không bắt buộc trở thành workflow. Workflow không cấp Permission truy cập.
- Domain sở hữu business entity; MDM sở hữu standard/governance/common primitives và cơ chế dữ liệu dùng chung. Ownership của reference/common data tuân thủ `AR-DATA-OWNERSHIP-001`.
- Ranh giới domain và canonical ownership tuân thủ `AR-DOM-001`; cross-domain use không tạo SSOT cạnh tranh.
- Mọi business transaction tuân thủ context invariant tại `AR-TXN-001`, gồm owning Company, business timestamp, lifecycle state và responsible actor/system context.
- Employee là Group Master; Employee Assignment là Company Master. Department là Company Master, Position là Group Master; Employee Assignment tham chiếu các master có hiệu lực để biểu diễn quan hệ làm việc.
- Product Master là Group Master thuộc Product domain và độc lập với Engineering Product/Development Product của PLM.
- Customer và Supplier mặc định là Group Master do domain tương ứng sở hữu; Company trong Group sử dụng các master này.
- Product Default Selling Price là Group Master thuộc Product domain và là giá cơ sở cuối cùng khi không có Company hoặc Group Pricing Policy/Price List hợp lệ áp dụng.
- Pricing hỗ trợ Group Pricing Policy và Company Pricing Policy dưới dạng các policy object riêng; Company không sửa trực tiếp Group Pricing Policy. Precedence xác định giá cơ sở là `Company Pricing Policy > Group Pricing Policy > Product Default Selling Price`.
- PLM / Engineering là miền độc lập quản lý dữ liệu kỹ thuật, version/revision và vòng đời thiết kế-phát triển. Manufacturing/Production phải vận hành được độc lập với PLM; liên kết ECN/ECO chỉ áp dụng khi integration tương ứng được triển khai.
- ERP là nguồn dữ liệu chính cho tài chính vận hành trong phạm vi được requirement phê duyệt; phạm vi kế toán tổng hợp hoặc tuân thủ pháp lý chỉ có hiệu lực khi có requirement tương ứng.
- Traceability, auditability và khả năng giải thích dữ liệu là yêu cầu xuyên miền đối với các nghiệp vụ quan trọng.

### Ngoài phạm vi
- Không chứa thiết kế cơ sở dữ liệu, API, giao diện hoặc mã nguồn triển khai.
- Không quy định chi tiết kỹ thuật triển khai, framework hoặc cấu trúc nội bộ của phần mềm.
- Không thay thế tài liệu quy trình nghiệp vụ chi tiết, thiết kế giải pháp hoặc kế hoạch kiểm thử.
- Không gán `target_phase` trong v0.9.9; việc phân phase chỉ thực hiện sau khi requirement đạt `reviewed` và bước Phase Planning bắt đầu.
- Không tự tạo thêm business capability mới ngoài các requirement item hiện có nếu chưa có quyết định yêu cầu tương ứng.


## 4. Requirement items

```yaml
id: BR-ORG-GROUP-001
title: "Quản lý nhiều Group độc lập trên cùng nền tảng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
description: "Hệ thống cần hỗ trợ nhiều Group độc lập. Mỗi Group có hệ thống tổ chức, dữ liệu và phạm vi quản trị riêng. Business Transaction không thuộc ownership trực tiếp của Group mà thuộc đúng một Company; Group tương ứng được suy ra qua Company."
success_criteria:
  - "Có thể quản lý nhiều Group trong cùng nền tảng mà không trộn dữ liệu."
  - "Mỗi giao dịch xác định được đúng một owning Company; Group tương ứng được suy ra qua Company và không được gán làm owner transaction trực tiếp."
depends_on: []
implemented_by:
  - "FR-ORG-GROUP-001"
verified_by:
  - "UAT-ORG-GROUP-001"
```

```yaml
id: BR-ORG-GROUP-002
title: "Quản lý Group như Business Entity không sở hữu transaction trực tiếp"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "ERP Product Baseline Decision"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
description: "Doanh nghiệp cần quản lý Group Business Entity như canonical business identity có thông tin quản trị riêng. Organization Context `type=GROUP` là organizational-scope representation 1:1 của chính Group Business Entity đó, không phải Group master độc lập. Group không sở hữu transaction trực tiếp; mọi transaction nghiệp vụ phải thuộc đúng một Company."
success_criteria:
  - "Có thể quản lý thông tin quản trị của Group và tổng hợp dữ liệu từ các Company thuộc Group."
  - "Mỗi Group Business Entity có đúng một Group identity và Organization Context `type=GROUP` reference identity đó theo quan hệ 1:1."
  - "Không tạo `GROUP` Organization Context không gắn Group Business Entity và không tạo hai Group Business Entity cho cùng một Group Context."
  - "Business attributes của Group thuộc Group Business Entity; Organization Context chỉ chịu trách nhiệm hierarchy/scope."
  - "Mọi transaction xác định được đúng một Company sở hữu; Group được suy ra qua Company và không được gán làm chủ sở hữu transaction trực tiếp."
depends_on:
  - "BR-ORG-GROUP-001"
implemented_by:
  - "FR-ORG-GROUP-002"
verified_by:
  - "UAT-ORG-GROUP-002"
```

```yaml
id: BR-ORG-001
title: "Quản lý cây Organization Context theo Group, Company, Branch, Plant và Warehouse"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
description: "Doanh nghiệp cần quản lý Organization Context bằng tập loại cố định gồm GROUP, COMPANY, BRANCH, PLANT và WAREHOUSE. Group Business Entity là canonical business identity; Organization Context `type=GROUP` là organizational-scope representation 1:1 của Group đó và không phải Group master độc lập. Giao dịch nghiệp vụ thuộc đúng một Company sở hữu. Hierarchy không bắt buộc phải có đủ mọi cấp và có thể bỏ qua cấp trung gian khi phù hợp mô hình vận hành."
success_criteria:
  - "Có thể xác định Company sở hữu của mọi giao dịch và báo cáo không trộn dữ liệu ngoài phạm vi."
  - "Organization Context chỉ sử dụng các type GROUP, COMPANY, BRANCH, PLANT, WAREHOUSE và cho phép hierarchy hợp lệ bỏ qua cấp trung gian."
  - "Organization Context `type=GROUP` reference đúng một Group Business Entity canonical theo quan hệ 1:1 và không tạo Group master cạnh tranh."
depends_on: []
implemented_by:
  - "FR-ORG-001"
verified_by:
  - "UAT-ORG-001"
```

```yaml
id: BR-ORG-CONTEXT-002
title: "Governance loại Organization Context và validation hierarchy"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "ERP Product Baseline Decision"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
description: "Doanh nghiệp cần governance tập Organization Context Type cố định gồm GROUP, COMPANY, BRANCH, PLANT và WAREHOUSE. Business configuration thông thường không được tạo thêm Organization Context Type. Hệ thống phải validation hierarchy theo type và cho phép các cấu trúc hợp lệ bỏ qua cấp trung gian khi mô hình vận hành cho phép. `GROUP` Context chỉ là organizational-scope representation 1:1 của Group Business Entity canonical, không phải một Group master độc lập. Requirement này quản lý governance/validation; BR-ORG-001 quản lý business capability của cây Organization Context."
success_criteria:
  - "Không tạo Organization Context Type ngoài tập GROUP, COMPANY, BRANCH, PLANT, WAREHOUSE bằng business configuration thông thường."
  - "Quan hệ parent-child không hợp lệ theo governance rule bị từ chối hoặc yêu cầu quyết định cấu hình có thẩm quyền."
  - "Các hierarchy hợp lệ như Company -> Warehouse có thể được quản lý mà không bắt buộc tạo Branch hoặc Plant trung gian."
  - "Mọi Organization Context `type=GROUP` reference đúng một Group Business Entity canonical; không tồn tại GROUP Context như master cạnh tranh."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-ORG-CONTEXT-002"
verified_by:
  - "UAT-ORG-CONTEXT-002"
```
```yaml
id: BR-EMP-002
title: "Quản lý Department và Position như master của Employee domain"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị nhân viên và cơ cấu tổ chức"
description: "Department và Position là master thuộc Employee domain. Department là Company Master, có hierarchy, lifecycle/state và thời gian hiệu lực riêng trong Company. Position là Group Master thể hiện chức danh và level dùng chung trong Group. Department/Position được Employee Assignment tham chiếu theo thời gian hiệu lực và không tự sinh Permission hoặc Organization Scope."
success_criteria:
  - "Mỗi Department xác định được Company sở hữu, parent trong hierarchy khi áp dụng, lifecycle/state và thời gian hiệu lực."
  - "Department ngừng hiệu lực không được dùng cho Employee Assignment mới nhưng lịch sử Assignment cũ vẫn được bảo toàn."
  - "Mỗi Position xác định được Group sở hữu, chức danh/level, trạng thái và thời gian hiệu lực."
  - "Employee Assignment chỉ tham chiếu Department thuộc đúng Company của Assignment và Position thuộc Group tương ứng đang có hiệu lực."
  - "Department hoặc Position không tự tạo Permission hay Organization Scope."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-EMP-002"
verified_by:
  - "UAT-EMP-002"
```

```yaml
id: BR-ORG-002
title: "Quản lý Branch, Plant và Warehouse thuộc Organization Context của Company"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
description: "Doanh nghiệp cần quản lý Branch, Plant và Warehouse trong Organization Context hierarchy của Company. Department và Position thuộc Employee domain và không trở thành canonical Organization Context. Tuy nhiên hệ thống phải cho phép trình bày một organizational view hợp nhất trong đó Department và Position được hiển thị theo Organization Context và Employee Assignment có hiệu lực, để người dùng có thể xem cơ cấu tổ chức theo Group, Company, địa điểm/đơn vị vận hành, Department, Position và Employee."
success_criteria:
  - "Mỗi Branch, Plant và Warehouse xác định được Company sở hữu và vị trí hợp lệ trong Organization Context hierarchy."
  - "Có thể hiển thị Department và Position trong organizational view theo Employee Assignment đang có hiệu lực mà không thay đổi canonical ownership của Department, Position hoặc Organization Context."
  - "Một Position có thể xuất hiện tại nhiều Department hoặc Organization Context khác nhau khi các Employee Assignment có hiệu lực tạo ra các placement tương ứng."
  - "Việc hiển thị Department/Position trong organizational view không tự tạo Organization Scope, Permission hoặc transaction ownership mới."
depends_on:
  - "BR-ORG-001"
  - "BR-EMP-002"
implemented_by:
  - "FR-ORG-002"
verified_by:
  - "UAT-ORG-002"
```

```yaml
id: BR-EMP-001
title: "Quản lý Employee và Employee Assignment như nền tảng nhân sự tổ chức"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị nhân viên và cơ cấu tổ chức"
description: "Doanh nghiệp cần quản lý Employee độc lập với User Identity. Employee domain là owning domain của Employee và Employee Assignment. Employee là Group Master canonical trong Group; Employee Assignment là Company Master và biểu diễn quan hệ làm việc của Employee trong một Company bằng cách tham chiếu Organization Context, Department, Position và thời gian hiệu lực. HRP và IAM chỉ mở rộng hoặc tham chiếu các đối tượng này, không tạo Employee/Employee Assignment master cạnh tranh."
success_criteria:
  - "Mỗi Employee xác định được Group sở hữu và có thể được tham chiếu bởi các Company trong Group theo quyền được cấp."
  - "Mỗi Employee Assignment xác định được đúng một Company sở hữu, Organization Context, Department, Position và thời gian hiệu lực."
  - "Một Employee có thể có nhiều Assignment theo Company và theo thời gian mà vẫn dùng cùng Employee canonical."
  - "Lịch sử thay đổi Organization Context, Department, Position và thời gian hiệu lực của Assignment được bảo toàn."
  - "Có thể xác định Employee/Employee Assignment canonical mà HRP và IAM đang tham chiếu."
depends_on:
  - "BR-ORG-002"
  - "BR-EMP-002"
implemented_by:
  - "FR-EMP-001"
verified_by:
  - "UAT-EMP-001"
```

```yaml
id: BR-ORG-008
title: "Theo dõi trạng thái hoạt động của Company, Branch, Plant và Warehouse"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
description: "Doanh nghiệp cần theo dõi lifecycle/state và thời gian hiệu lực của Company, Branch, Plant và Warehouse thuộc Organization Context. Department không thuộc requirement này và được quản lý trong Employee domain."
success_criteria:
  - "Organization Context ngừng hoạt động không phát sinh dữ liệu mới nhưng lịch sử vẫn tra cứu được."
  - "Lifecycle/state của Department không được quản lý như Organization Context."
depends_on:
  - "BR-ORG-002"
implemented_by:
  - "FR-ORG-008"
verified_by:
  - "UAT-ORG-008"
```

```yaml
id: BR-ORG-003
title: "Xem số liệu theo một công ty, một chi nhánh, nhóm chi nhánh hoặc toàn doanh nghiệp theo quyền được cấp"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
description: "Doanh nghiệp cần xem số liệu theo một công ty, một chi nhánh, nhóm chi nhánh hoặc toàn doanh nghiệp theo quyền được cấp."
success_criteria:
  - "Tổng theo các phạm vi hợp lệ khớp với tổng chi tiết."
depends_on:
  - "BR-ORG-001"
  - "BR-ORG-002"
  - "BR-IAM-002"
implemented_by:
  - "FR-ORG-003"
verified_by:
  - "UAT-ORG-003"
```

```yaml
id: BR-ORG-004
title: "Hỗ trợ dự án hoặc đối tượng tập hợp chi phí/doanh thu có chủ sở hữu, thời hạn và trạng thái riêng"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
description: "Doanh nghiệp cần hỗ trợ dự án hoặc đối tượng tập hợp chi phí/doanh thu có chủ sở hữu, thời hạn và trạng thái riêng."
success_criteria:
  - "Giao dịch mới chỉ được gán cho đối tượng đang hiệu lực và thuộc đúng công ty."
depends_on:
  - "BR-ORG-001"
  - "BR-ORG-002"
implemented_by:
  - "FR-ORG-004"
verified_by:
  - "UAT-ORG-004"
```

```yaml
id: BR-ORG-005
title: "Quản lý lịch tài chính, kỳ kinh doanh, múi giờ nghiệp vụ và đồng tiền nghiệp vụ theo công ty"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
source_refs:
  - "DEC-SYS-LOCALE-001"
description: "Doanh nghiệp cần quản lý lịch tài chính, kỳ kinh doanh, business timezone và business/base/functional currency theo từng Company. Các giá trị này quyết định business meaning của kỳ, thời điểm và số tiền; locale/display formatting thuộc BR-SYS-001 và không được thay đổi các giá trị nghiệp vụ này."
success_criteria:
  - "Mỗi chứng từ xác định được Company policy về kỳ, business timezone và business/base/functional currency có hiệu lực tại thời điểm nghiệp vụ."
  - "Thay đổi locale/display setting không làm thay đổi business currency, kỳ nghiệp vụ hoặc monetary value đã ghi nhận."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-ORG-005"
verified_by:
  - "UAT-ORG-005"
```

```yaml
id: BR-ORG-009
title: "Quản lý Company Business Calendar cho business day và deadline nghiệp vụ"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
source_refs:
  - "DEC-ORG-CALENDAR-001"
description: "Doanh nghiệp cần quản lý Company Business Calendar làm canonical source để xác định business day/non-business day, Company holiday và các business cutoff/deadline cần tính theo ngày làm việc. Calendar này thuộc Organization/Foundation và không thay thế Employee Work Calendar của HR. Khi deadline liên quan kỳ tài chính, calendar tham chiếu kỳ/close context do BR-ORG-005/BR-FIN-008 quản lý nhưng không sở hữu financial period."
success_criteria:
  - "Mỗi Company xác định được Business Calendar có thời gian hiệu lực và phân loại được business day/non-business day cùng Company holiday áp dụng."
  - "Các deadline/cutoff dùng khái niệm ngày làm việc có thể resolve cùng một Company Business Calendar canonical."
  - "Employee Work Calendar có thể bổ sung lịch theo Assignment/Branch/Plant/employee group mà không tạo định nghĩa Company business day cạnh tranh."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-ORG-009"
verified_by:
  - "UAT-ORG-009"
```

```yaml
id: BR-ORG-006
title: "Quản lý các chính sách dùng chung và chính sách riêng theo công ty hoặc chi nhánh"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
description: "Doanh nghiệp cần quản lý các chính sách dùng chung và chính sách riêng theo công ty hoặc chi nhánh."
success_criteria:
  - "Người có thẩm quyền phân biệt được giá trị dùng chung với giá trị địa phương và ngày hiệu lực."
depends_on:
  - "BR-ORG-001"
  - "BR-ORG-002"
implemented_by:
  - "FR-ORG-006"
verified_by:
  - "UAT-ORG-006"
```

```yaml
id: BR-ORG-007
title: "Chỉ định chủ sở hữu dữ liệu và người thay thế cho từng miền dữ liệu quan trọng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
description: "Doanh nghiệp cần chỉ định chủ sở hữu dữ liệu và người thay thế cho từng miền dữ liệu quan trọng."
success_criteria:
  - "Không có đối tượng quan trọng nào thiếu chủ sở hữu hoặc ngày hiệu lực trách nhiệm."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-ORG-007"
verified_by:
  - "UAT-ORG-007"
```

```yaml
id: BR-ORG-GROUP-ISOLATION-001
title: "Cách ly dữ liệu giữa các Group"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
source_refs:
  - "DEC-IAM-AUTHZ-001"
description: "Dữ liệu giữa các Group phải được cách ly. User Identity chỉ được truy cập Group theo explicit Group Access Membership hoặc controlled exceptional identity scope; membership/quyền của Group này không được suy ra thành quyền ở Group khác."
success_criteria:
  - "Kiểm tra chéo không truy cập được dữ liệu ngoài Group khi không có membership/scope explicit tương ứng."
  - "Membership Group A không tạo quyền hoặc data scope trong Group B."
depends_on:
  - "BR-ORG-GROUP-001"
  - "BR-IAM-012"
implemented_by:
  - "FR-ORG-GROUP-ISOLATION-001"
verified_by:
  - "UAT-ORG-GROUP-ISOLATION-001"
```

```yaml
id: BR-ORG-CONSOLIDATION-001
title: "Tổng hợp báo cáo quản trị theo Group"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị tổ chức và doanh nghiệp"
description: "Hệ thống cần hỗ trợ tổng hợp báo cáo quản trị theo Group từ dữ liệu sở hữu bởi các Company thuộc Group đó. Đây không phải hợp nhất kế toán (Consolidation Accounting)."
success_criteria:
  - "Báo cáo quản trị Group tổng hợp đúng dữ liệu Company thuộc Group và được phân biệt rõ với báo cáo hợp nhất kế toán."
depends_on:
  - "BR-ORG-GROUP-001"
implemented_by:
  - "FR-ORG-CONSOLIDATION-001"
verified_by:
  - "UAT-ORG-CONSOLIDATION-001"
```

```yaml
id: BR-SYS-001
title: "Quản lý cấu hình hệ thống dùng chung"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nền tảng ERP"
source_refs:
  - "DEC-SYS-LOCALE-001"
description: "Doanh nghiệp cần quản lý cấu hình dùng chung về locale/display như timezone hiển thị mặc định, định dạng ngày giờ, ký hiệu/định dạng tiền tệ, dấu phân cách và số thập phân hiển thị, ngôn ngữ và quy tắc hiển thị. Business/base/functional currency và business timezone của Company thuộc BR-ORG-005; BR-SYS-001 không thay đổi business meaning hoặc monetary value."
success_criteria:
  - "Cấu hình locale/display có phạm vi áp dụng, người quản lý và lịch sử thay đổi."
  - "Thay đổi locale/display không làm đổi Company business currency, business timestamp/kỳ hoặc giá trị tiền đã ghi nhận."
depends_on:
  - "BR-ORG-005"
implemented_by:
  - "FR-SYS-001"
verified_by:
  - "UAT-SYS-001"
```

```yaml
id: NFR-PLT-001
title: "Cung cấp ERP trên nền tảng web"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Nền tảng ứng dụng"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Hệ thống ERP được cung cấp trên nền tảng web. Acceptance baseline hỗ trợ Google Chrome và Microsoft Edge trên desktop với current stable và 2 major versions gần nhất, trên Windows 10/11 hoặc phiên bản Windows desktop còn được Dinktech hỗ trợ. Mobile/tablet không thuộc acceptance baseline nếu không có requirement riêng."
success_criteria:
  - "Các chức năng thuộc phạm vi ERP vận hành trên Google Chrome và Microsoft Edge desktop ở current stable và 2 major versions gần nhất tại thời điểm acceptance."
  - "Acceptance test chạy trên Windows 10/11 hoặc Windows desktop version còn nằm trong support baseline của Dinktech."
  - "Không coi mobile/tablet là môi trường được hỗ trợ chính thức nếu chưa có requirement riêng phê duyệt phạm vi đó."
  - "Danh sách browser/OS version cụ thể dùng cho mỗi đợt acceptance được ghi lại trong NFR-TEST để tái kiểm thử."
depends_on: []
implemented_by:
  - "NFS-PLT-001"
verified_by:
  - "NFR-TEST-PLT-001"
```

```yaml
id: BR-IAM-001
title: "Quản lý User Identity như authentication principal ở platform level"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
source_refs:
  - "DEC-IAM-AUTHZ-001"
description: "Doanh nghiệp cần quản lý User Identity như authentication principal ở platform level, độc lập với Employee/Employee Assignment master. User Identity xác định loại identity, trạng thái xác thực/truy cập và lifecycle ở cấp nền tảng; việc ordinary ERP User được truy cập một Group và reference Employee của Group đó thuộc BR-IAM-012. IAM không sở hữu hoặc tạo Employee/Employee Assignment master thay thế."
success_criteria:
  - "Mỗi User Identity có định danh duy nhất, loại identity, trạng thái và lifecycle có hiệu lực ở platform level."
  - "User bị khóa hoặc ngừng hiệu lực không thể thực hiện giao dịch mới."
  - "User Identity không trỏ cứng vào một Employee Assignment và không biến platform identity thành Company membership."
  - "IAM không tạo Employee, Department, Position hoặc Employee Assignment master cạnh tranh."
depends_on: []
implemented_by:
  - "FR-IAM-001"
verified_by:
  - "UAT-IAM-001"
```


```yaml
id: BR-IAM-012
title: "Quản lý User Group Access Membership"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
source_refs:
  - "DEC-IAM-AUTHZ-001"
description: "Doanh nghiệp cần quản lý explicit Group Access Membership để một User Identity được truy cập từng Group. Ordinary ERP User membership phải reference Employee canonical thuộc chính Group đó; membership không trỏ cứng vào một Employee Assignment. Khi authorization cần Company/Organization Context, active Employee Assignment(s) của Employee được resolve theo Company/context và thời gian hiệu lực tại thời điểm đánh giá. System Admin/service identity là ngoại lệ có kiểm soát: nếu không reference Employee thì loại identity, scope và căn cứ ngoại lệ phải explicit và audit được."
success_criteria:
  - "Mỗi ordinary ERP User truy cập Group có explicit membership tới đúng Group và Employee canonical thuộc Group đó."
  - "Membership Group A không tự tạo quyền ở Group B; cross-Group access yêu cầu membership/scope explicit cho từng Group liên quan."
  - "Khi authorization theo Company/context, Employee Assignment được resolve từ các Assignment đang hiệu lực của Employee; membership không trở thành Company membership do tham chiếu cứng một Assignment."
  - "Permission/Policy được evaluate trong Group và Company/Organization Context đã resolve tương ứng."
  - "System Admin/service identity ngoại lệ không có Employee chỉ được phép khi identity type, scope, owner và audit evidence được kiểm soát."
depends_on:
  - "BR-IAM-001"
  - "BR-ORG-GROUP-001"
  - "BR-EMP-001"
implemented_by:
  - "FR-IAM-012"
verified_by:
  - "UAT-IAM-012"
```

```yaml
id: BR-IAM-002
title: "Phân quyền theo Policy Based Access Control với Profile Permission và Policy Rule"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
source_refs:
  - "DEC-IAM-AUTHZ-001"
description: "Doanh nghiệp cần phân quyền theo mô hình Profile Permission kết hợp Policy Rule trong Group/Company/Organization Context đã resolve qua BR-IAM-012. Permission được định nghĩa theo Resource/Action và được cấp thông qua Profile; Policy Rule chỉ giới hạn quyền hiệu lực theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect. Authorization Evaluation không cấp thêm quyền ngoài Profile và tuân thủ deterministic evaluation tại BR-IAM-CONFLICT-001."
success_criteria:
  - "Người dùng chỉ thấy và tác động đến dữ liệu được ủy quyền trong Group/Company/Organization Context đã resolve."
  - "Quyết định Authorization truy được Profile Permission, canonical Permission metadata, applicable Policy Rules và membership/context đã dùng."
depends_on:
  - "BR-IAM-012"
  - "BR-ORG-002"
implemented_by:
  - "FR-IAM-002"
verified_by:
  - "UAT-IAM-002"
```

```yaml
id: BR-IAM-009
title: "Quản lý Profile Permission làm cơ sở quyền hiệu lực"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
description: "Doanh nghiệp cần quản lý Profile là tập Permission hiệu lực. Permission xác định hành động trên Resource; Policy không tạo thêm quyền ngoài Profile."
success_criteria:
  - "Có thể xác định nguồn gốc quyền của người dùng từ Profile Permission."
depends_on:
  - "BR-IAM-002"
implemented_by:
  - "FR-IAM-009"
verified_by:
  - "UAT-IAM-009"
```

```yaml
id: BR-IAM-PERMISSION-001
title: "Định nghĩa Permission cố định theo Resource và Action"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "ERP Product Baseline Decision"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
  - "DEC-IAM-AUTHZ-001"
description: "Doanh nghiệp cần sử dụng Permission được định nghĩa cố định theo cặp Resource/Action làm đơn vị quyền cơ sở. Permission được đưa vào Profile để hình thành quyền hiệu lực và không được sinh động bởi Policy Rule. Thuộc tính canonical `policy_required` thuộc Resource/Action Permission, không thuộc từng Profile, để cùng một Permission có semantic NO_MATCH nhất quán toàn hệ thống."
success_criteria:
  - "Mỗi Permission xác định được Resource, Action, mã định danh, `policy_required` và Profile đang sử dụng."
  - "Cùng một Resource/Action Permission có cùng giá trị `policy_required` bất kể Permission đó được cấp qua Profile nào."
  - "Policy Rule không tạo Permission mới ngoài Permission đã được định nghĩa và cấp qua Profile."
depends_on:
  - "BR-IAM-009"
implemented_by:
  - "FR-IAM-PERMISSION-001"
verified_by:
  - "UAT-IAM-PERMISSION-001"
```

```yaml
id: BR-IAM-011
title: "Quản lý Business Group Mapping tới Profile Permission"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng và phân quyền"
description: "Doanh nghiệp cần quản lý Business Group để ánh xạ tới Profile Permission phục vụ quản trị phân quyền. Business Group là khái niệm của Authorization, khác với Group Business Entity trong Organization Model, và không thay thế Permission hoặc Policy Rule."
success_criteria:
  - "Có thể xác định Business Group, Profile, Permission và Policy áp dụng cho người dùng."
  - "Thay đổi mapping Business Group - Profile có lịch sử và không tự tạo Permission mới."
depends_on:
  - "BR-IAM-009"
implemented_by:
  - "FR-IAM-011"
verified_by:
  - "UAT-IAM-011"
```

```yaml
id: BR-IAM-010
title: "Quản lý Policy Rule theo Organization Context, Resource, State và Condition"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
source_refs:
  - "DEC-IAM-AUTHZ-001"
description: "Doanh nghiệp cần quản lý Policy Rule để giới hạn Permission theo Organization Context, Resource Type, State, Participant, Department/business Condition và Effect. Department không trở thành Organization Context và không tự tạo Permission/Scope; khi Department condition được dùng, Actor Department phải được resolve từ Employee Assignment có hiệu lực còn Resource Responsible Department phải lấy từ ownership/responsibility của resource. Condition luôn được đánh giá trong đúng Company context."
success_criteria:
  - "Quyết định Authorization có thể giải thích được theo Policy áp dụng."
  - "Department condition không tạo Organization Scope/Permission riêng và Department được evaluate trong đúng Company."
  - "Actor Department được resolve từ Employee Assignment đang hiệu lực; Resource Department được lấy từ owning/responsible Department của resource thay vì suy diễn từ User."
depends_on:
  - "BR-IAM-009"
implemented_by:
  - "FR-IAM-010"
verified_by:
  - "UAT-IAM-010"
```

```yaml
id: BR-IAM-POLICY-VERSION-001
title: "Quản lý version và thời gian hiệu lực của Policy"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "ERP Product Baseline Decision"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
description: "Doanh nghiệp cần quản lý Policy theo version, thời gian hiệu lực và lịch sử thay đổi để quyết định Authorization có thể được giải thích theo chính sách áp dụng tại thời điểm đánh giá."
success_criteria:
  - "Có thể xác định version Policy có hiệu lực tại một thời điểm và truy lại các version trước."
  - "Thay đổi Policy không ghi đè làm mất nội dung đã từng có hiệu lực."
depends_on:
  - "BR-IAM-010"
implemented_by:
  - "FR-IAM-POLICY-VERSION-001"
verified_by:
  - "UAT-IAM-POLICY-VERSION-001"
```

```yaml
id: BR-IAM-CONFLICT-001
title: "Đánh giá Authorization deterministic theo Permission, Policy Effect và policy_required"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "ERP Product Baseline Decision"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
  - "DEC-IAM-AUTHZ-001"
description: "Doanh nghiệp cần áp dụng một Authorization Evaluation thống nhất: Profile Permission là gate quyền cơ sở; applicable Policy Rules chỉ được evaluate sau khi Permission tồn tại; Effect aggregate theo `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; NO_MATCH được quyết định bởi canonical `policy_required` của Resource/Action Permission. Policy không bao giờ tạo Permission mới."
success_criteria:
  - "Không có Profile Permission thì kết quả luôn DENY, kể cả khi có ALLOW Policy."
  - "Có Profile Permission và có `ABSOLUTE_DENY` applicable thì kết quả DENY."
  - "Có Profile Permission và có `ALLOW` applicable, không có `ABSOLUTE_DENY`, thì ALLOW thắng DENY thông thường."
  - "Có Profile Permission và chỉ có `DENY` applicable thì kết quả DENY."
  - "Có Profile Permission nhưng NO_MATCH: `policy_required=true` thì DENY; `policy_required=false` thì ALLOW."
  - "Permission set của nhiều Profile là union của các Profile đang có hiệu lực; Profile bổ sung Permission nhưng không bypass Policy."
  - "Quyết định cho phép/từ chối có thể giải thích được từ Membership/Context, Profile Permission, `policy_required`, applicable Policy version/rule và Effect đã áp dụng."
depends_on:
  - "BR-IAM-PERMISSION-001"
  - "BR-IAM-010"
implemented_by:
  - "FR-IAM-CONFLICT-001"
verified_by:
  - "UAT-IAM-CONFLICT-001"
```

```yaml
id: BR-IAM-003
title: "Phân biệt quyền thực hiện từng loại hành động trên cùng đối tượng nghiệp vụ"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
description: "Doanh nghiệp cần quản lý quyền truy cập theo từng loại hành động trên cùng một đối tượng nghiệp vụ, bao gồm xem, tạo hoặc gửi yêu cầu, phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu và quản trị. Quyền đối với từng hành động phải được xác định độc lập; việc được cấp một quyền không mặc nhiên tạo ra quyền thực hiện hành động khác trên cùng đối tượng."
success_criteria:
  - "Đối với cùng một đối tượng nghiệp vụ, có thể cấu hình và xác định độc lập quyền xem, tạo/gửi yêu cầu, phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu và quản trị."
  - "Người dùng chỉ thực hiện được hành động khi Permission tương ứng được cấp và các Policy Rule áp dụng cho context hiện tại cho phép."
  - "Việc có quyền xem hoặc tạo yêu cầu không mặc nhiên cấp quyền phê duyệt, ghi nhận chính thức, điều chỉnh, xuất dữ liệu hoặc quản trị."
  - "Có thể truy được Permission và Policy Rule đã được sử dụng để quyết định cho phép hoặc từ chối một hành động."
depends_on:
  - "BR-IAM-002"
implemented_by:
  - "FR-IAM-003"
verified_by:
  - "UAT-IAM-003"
```

```yaml
id: BR-IAM-004
title: "Bảo đảm người tạo, người phê duyệt, người thực hiện và người đối soát không mặc định là cùng một người trong các nghiệp vụ có rủi ro"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
description: "Doanh nghiệp cần bảo đảm người tạo, người phê duyệt, người thực hiện và người đối soát không mặc định là cùng một người trong các nghiệp vụ có rủi ro."
success_criteria:
  - "Giao dịch vi phạm tách biệt trách nhiệm bị nhận diện và yêu cầu ngoại lệ có thẩm quyền."
depends_on:
  - "BR-IAM-003"
implemented_by:
  - "FR-IAM-004"
verified_by:
  - "UAT-IAM-004"
```

```yaml
id: BR-IAM-005
title: "Quản lý người thay thế, ủy quyền có thời hạn và quyền khẩn cấp có hậu kiểm"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
description: "Doanh nghiệp cần quản lý người thay thế, ủy quyền có thời hạn và quyền khẩn cấp có hậu kiểm."
success_criteria:
  - "Mọi quyền thay thế có người cấp, lý do, thời hạn và lịch sử sử dụng."
depends_on:
  - "BR-IAM-002"
implemented_by:
  - "FR-IAM-005"
verified_by:
  - "UAT-IAM-005"
```

```yaml
id: BR-IAM-006
title: "Rà soát quyền định kỳ và khi nhân viên chuyển vị trí, công ty hoặc chi nhánh"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
description: "Doanh nghiệp cần rà soát quyền định kỳ và khi nhân viên chuyển vị trí, công ty hoặc chi nhánh."
success_criteria:
  - "Có bằng chứng rà soát, người xác nhận và danh sách quyền bị thu hồi hoặc thay đổi."
depends_on:
  - "BR-IAM-001"
  - "BR-IAM-002"
implemented_by:
  - "FR-IAM-006"
verified_by:
  - "UAT-IAM-006"
```

```yaml
id: BR-IAM-007
title: "Bảo vệ dữ liệu nhạy cảm theo phạm vi công việc và mục đích sử dụng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
description: "Doanh nghiệp cần bảo vệ dữ liệu nhạy cảm như lương, thông tin định danh, giá vốn, chiết khấu và tài khoản ngân hàng theo phạm vi công việc."
success_criteria:
  - "Người không có quyền không xem hoặc xuất được dữ liệu nhạy cảm."
depends_on:
  - "BR-IAM-002"
implemented_by:
  - "FR-IAM-007"
verified_by:
  - "UAT-IAM-007"
```

```yaml
id: BR-IAM-008
title: "Ghi nhận sự kiện xác thực, truy cập và quản trị Authorization"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị người dùng, quyền truy cập và tách biệt trách nhiệm"
description: "Doanh nghiệp cần ghi nhận các sự kiện IAM gồm đăng nhập, đăng xuất, xác thực thất bại, truy cập bị từ chối và hành động quản trị User/Profile/Permission/Policy. Lịch sử thay đổi dữ liệu nghiệp vụ và business audit trail không thuộc ownership của IAM mà được quản lý bởi Central Audit Trail."
success_criteria:
  - "Có thể tra cứu người/User, thời điểm, phạm vi, loại sự kiện và kết quả của hành động IAM."
  - "Thay đổi Profile, Permission hoặc Policy xác định được người thực hiện và nội dung quản trị liên quan."
  - "IAM audit không được dùng làm master thay thế cho lịch sử thay đổi dữ liệu nghiệp vụ."
depends_on:
  - "BR-IAM-001"
  - "BR-IAM-002"
implemented_by:
  - "FR-IAM-008"
verified_by:
  - "UAT-IAM-008"
```

```yaml
id: BR-SEC-MON-001
title: "Phát hiện Security Event theo rule, tạo cảnh báo và lưu bằng chứng xử lý"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "An toàn và kiểm soát truy cập"
source_refs:
  - "DEC-SEC-MON-001"
description: "Doanh nghiệp cần rule-based security monitoring để phát hiện security event thuộc tập detection rule được phê duyệt, tạo alert, lưu evidence và quản lý trạng thái xử lý. Capability này không yêu cầu hệ thống tự phát hiện mọi anomaly bằng AI; quantitative latency/coverage target nếu được phê duyệt phải được quản lý bằng một NFR ID mới."
success_criteria:
  - "Detection rule thuộc acceptance scope sinh alert khi điều kiện test tương ứng xảy ra."
  - "Baseline event class có thể bao gồm repeated authentication failure, denied cross-scope/cross-Group access, administrative change đối với Profile/Permission/Policy, attempted sensitive-data access/export bị từ chối và security-control bypass attempt khi có rule xác định."
  - "Mỗi alert xác định được detection rule, actor/User, timestamp, resource/scope, severity, evidence, owner và handling status."
  - "Detection rule có owner, lifecycle và effective time; alert không tự cấp hoặc quản lý Permission."
depends_on:
  - "BR-IAM-008"
  - "BR-CTRL-007"
implemented_by:
  - "FR-SEC-MON-001"
verified_by:
  - "UAT-SEC-MON-001"
```

```yaml
id: NFR-SEC-001
title: "Bảo vệ khỏi truy cập, sửa đổi hoặc xuất trái thẩm quyền theo công ty, chi nhánh, kho, phòng ban và vai trò"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "An toàn và kiểm soát truy cập"
description: "Dữ liệu phải được bảo vệ khỏi truy cập, sửa đổi hoặc xuất trái thẩm quyền theo công ty, chi nhánh, kho, phòng ban và vai trò."
success_criteria:
  - "Mẫu kiểm tra chéo phạm vi không truy cập được dữ liệu ngoài quyền."
depends_on:
  - "BR-IAM-002"
implemented_by:
  - "NFS-SEC-001"
verified_by:
  - "NFR-TEST-SEC-001"
```

```yaml
id: NFR-SEC-002
title: "Dữ liệu nhạy cảm phải được hạn chế theo mục đích công việc và người nhận"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "An toàn và kiểm soát truy cập"
description: "Dữ liệu nhạy cảm phải được hạn chế theo mục đích công việc và người nhận."
success_criteria:
  - "Báo cáo, tài liệu và bản xuất đều áp dụng cùng phạm vi bảo vệ."
depends_on:
  - "BR-IAM-007"
implemented_by:
  - "NFS-SEC-002"
verified_by:
  - "NFR-TEST-SEC-002"
```

```yaml
id: BR-CTRL-001
title: "Quản lý vòng đời trạng thái chung của chứng từ"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần quản lý trạng thái chung cho chứng từ và phân biệt rõ Nháp, Chờ xem xét, Đã duyệt, Đã ghi nhận chính thức, Hoàn tất, Hủy và Đảo/Điều chỉnh. Các module có thể mở rộng trạng thái nghiệp vụ riêng nhưng không được làm sai ý nghĩa trạng thái chung."
success_criteria:
  - "Ý nghĩa trạng thái chung được thống nhất giữa các miền và có thể ánh xạ trạng thái module về trạng thái chung khi cần báo cáo/kiểm soát."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-CTRL-001"
verified_by:
  - "UAT-CTRL-001"
```

```yaml
id: BR-CTRL-002
title: "Quản lý số chứng từ theo loại, công ty, chi nhánh, kỳ và chính sách đánh số được phê duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần quản lý số chứng từ theo loại, công ty, chi nhánh, kỳ và chính sách đánh số được phê duyệt."
success_criteria:
  - "Số đã cấp chính thức không bị tái sử dụng."
depends_on:
  - "BR-ORG-001"
  - "BR-ORG-005"
implemented_by:
  - "FR-CTRL-002"
verified_by:
  - "UAT-CTRL-002"
```

```yaml
id: BR-CTRL-003
title: "Thể hiện quan hệ truy xuất giữa chứng từ nguồn, phát sinh, thay thế, điều chỉnh và đảo"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần thể hiện quan hệ giữa chứng từ nguồn, chứng từ phát sinh, chứng từ thay thế, chứng từ điều chỉnh và chứng từ đảo."
success_criteria:
  - "Người kiểm tra biết chứng từ nào tạo ra hoặc thay đổi số liệu hiện tại."
depends_on:
  - "BR-CTRL-001"
implemented_by:
  - "FR-CTRL-003"
verified_by:
  - "UAT-CTRL-003"
```

```yaml
id: BR-CTRL-004
title: "Truy xuất người tạo, người phê duyệt, người ghi nhận, người điều chỉnh, thời điểm, lý do và tài liệu liên quan"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần truy xuất người tạo, người phê duyệt, người ghi nhận, người điều chỉnh, thời điểm, lý do và tài liệu liên quan."
success_criteria:
  - "Mẫu giao dịch quan trọng có hồ sơ trách nhiệm đầy đủ."
depends_on:
  - "BR-CTRL-001"
  - "BR-IAM-003"
implemented_by:
  - "FR-CTRL-004"
verified_by:
  - "UAT-CTRL-004"
```

```yaml
id: BR-CTRL-005
title: "Bảo đảm tổng số lượng, tổng tiền, tổng công nợ và tổng tồn giữa các chứng từ liên quan có thể đối chiếu"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần bảo đảm tổng số lượng, tổng tiền, tổng công nợ và tổng tồn giữa các chứng từ liên quan có thể đối chiếu."
success_criteria:
  - "Chênh lệch có tổng kiểm soát, chủ sở hữu và trạng thái xử lý."
depends_on:
  - "BR-CTRL-003"
implemented_by:
  - "FR-CTRL-005"
verified_by:
  - "UAT-CTRL-005"
```

```yaml
id: BR-CTRL-006
title: "Ghi nhận các sự kiện nghiệp vụ quan trọng như bản ghi chuẩn được công bố"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần ghi nhận các sự kiện nghiệp vụ quan trọng như bản ghi chuẩn được công bố, đơn được xác nhận, nhận hàng, xuất kho, lệnh sản xuất hoàn tất, hóa đơn, thanh toán, trả hàng và kỳ lương bị khóa."
success_criteria:
  - "Mỗi sự kiện có thời điểm, đối tượng, chủ sở hữu và kết quả nghiệp vụ."
depends_on:
  - "BR-CTRL-001"
  - "BR-INF-001"
implemented_by:
  - "FR-CTRL-006"
verified_by:
  - "UAT-CTRL-006"
```

```yaml
id: BR-CTRL-007
title: "Quản lý ngoại lệ xuyên miền với mức độ, tác động, người xử lý, thời hạn và quyết định cuối"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần quản lý ngoại lệ xuyên miền với mức độ, tác động, người xử lý, thời hạn và quyết định cuối. Việc ghi nhận business event tổng quát tại BR-CTRL-006 có thể bổ sung context nhưng không phải hard prerequisite để quản lý ngoại lệ."
success_criteria:
  - "Ngoại lệ quá hạn được nhắc và chuyển cấp theo chính sách."
depends_on:
  - "BR-CTRL-001"
implemented_by:
  - "FR-CTRL-007"
verified_by:
  - "UAT-CTRL-007"
```

```yaml
id: BR-CTRL-008
title: "Theo dõi đào tạo, năng lực tối thiểu, hỗ trợ tại đơn vị và mức sử dụng ERP"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần theo dõi mức hoàn thành đào tạo, năng lực tối thiểu, người hỗ trợ tại từng đơn vị và mức sử dụng ERP sau khi vận hành."
success_criteria:
  - "Có chỉ số, ngưỡng mục tiêu, chủ sở hữu và hành động cải thiện khi không đạt."
depends_on:
  - "BR-IAM-001"
implemented_by:
  - "FR-CTRL-008"
verified_by:
  - "UAT-CTRL-008"
```


```yaml
id: BR-CTRL-009
title: "Định nghĩa khung business approval dùng chung theo loại chứng từ, giá trị, rủi ro và phạm vi"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kiểm soát nghiệp vụ xuyên miền"
description: "Doanh nghiệp cần một khung business approval dùng chung có thể áp dụng theo loại chứng từ, giá trị, rủi ro, Company, đơn vị và vai trò, bao gồm một hoặc nhiều cấp, từ chối, trả lại để chỉnh sửa, hủy, chuyển cấp, ủy quyền và lịch sử quyết định. Requirement này mô tả business approval semantics; BR-WF-001 cung cấp Shared Workflow Engine thực thi các semantics đó."
success_criteria:
  - "Mỗi chứng từ thuộc diện business approval xác định được rule/cấp phê duyệt, người quyết định, kết quả, thời điểm, lý do và tình trạng hiệu lực của quyết định."
depends_on:
  - "BR-CTRL-001"
  - "BR-IAM-003"
implemented_by:
  - "FR-CTRL-009"
verified_by:
  - "UAT-CTRL-009"
```

```yaml
id: BR-WF-001
title: "Quản lý Approval Workflow Engine dùng chung"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Approval và kiểm soát nghiệp vụ"
description: "Doanh nghiệp cần một Shared Workflow Engine dùng chung để thực thi business approval cần governance/audit, gồm điều kiện phê duyệt, participant/người phê duyệt, trạng thái workflow, ủy quyền và lịch sử quyết định. State transition thuần túy của module không bắt buộc dùng Workflow. Approval Workflow không cấp Permission truy cập."
success_criteria:
  - "Mỗi giao dịch thuộc diện business approval xác định được workflow, rule áp dụng, participant/người duyệt, trạng thái và lịch sử quyết định."
  - "Quyết định workflow không tạo Permission mới ngoài quyền đã được Authorization cho phép."
depends_on:
  - "BR-IAM-002"
  - "BR-CTRL-009"
implemented_by:
  - "FR-WF-001"
verified_by:
  - "UAT-WF-001"
```

```yaml
id: BR-WF-GOV-001
title: "Quản trị Workflow theo trách nhiệm Developer, System Admin và Business Owner"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "ERP Product Baseline Decision"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
description: "Doanh nghiệp cần phân tách trách nhiệm quản trị Workflow: Developer tạo Workflow Type; System Admin triển khai và quản trị Workflow; Business Owner cấu hình Rule và Participant trong phạm vi được cho phép."
success_criteria:
  - "Mỗi Workflow Type xác định được nguồn tạo, phạm vi triển khai, người quản trị và Business Owner phụ trách rule/participant."
  - "Business Owner không thay đổi Workflow Type ở mức developer và System Admin không thay đổi rule nghiệp vụ ngoài phạm vi được giao mà không có lịch sử."
depends_on:
  - "BR-WF-001"
implemented_by:
  - "FR-WF-GOV-001"
verified_by:
  - "UAT-WF-GOV-001"
```


```yaml
id: BR-AUD-001
title: "Quản lý Central Audit Trail cho dữ liệu và nghiệp vụ quan trọng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Audit và truy vết"
description: "Doanh nghiệp cần một Central Audit Trail cho thay đổi dữ liệu và nghiệp vụ quan trọng, gồm Before Value, After Value, Reason, Approval, Attachment, người thực hiện và thời điểm thay đổi. Central Audit chịu trách nhiệm business/data-change audit; IAM audit chỉ chịu trách nhiệm các sự kiện xác thực, truy cập và quản trị Authorization."
success_criteria:
  - "Có thể truy vết dữ liệu trước và sau thay đổi đối với nghiệp vụ quan trọng."
  - "Audit record xác định được actor/User, thời điểm, đối tượng, thay đổi, lý do và approval/attachment khi áp dụng."
  - "Có thể phân biệt rõ IAM security event với business/data-change audit event."
depends_on:
  - "BR-IAM-001"
  - "BR-CTRL-004"
implemented_by:
  - "FR-AUD-001"
verified_by:
  - "UAT-AUD-001"
```

```yaml
id: NFR-AUD-001
title: "Audit trail phải bảo toàn tính toàn vẹn và phát hiện được sửa đổi trái phép"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Khả năng kiểm toán và truy xuất"
description: "Audit evidence do Central Audit Trail tạo ra phải được bảo toàn khỏi sửa đổi hoặc xóa trái phép; mọi can thiệp quản trị hợp lệ lên audit evidence phải được kiểm soát và để lại bằng chứng đủ để phát hiện tampering hoặc thay đổi ngoài policy."
success_criteria:
  - "Không thể sửa hoặc xóa audit evidence bằng luồng nghiệp vụ thông thường."
  - "Mọi thay đổi/quản trị đặc quyền được policy cho phép đối với audit evidence xác định được actor, thời điểm, lý do và kết quả."
  - "Kiểm thử integrity phát hiện được mẫu thay đổi trái phép hoặc thiếu chuỗi bằng chứng theo tiêu chí NFR-TEST."
depends_on:
  - "BR-AUD-001"
implemented_by:
  - "NFS-AUD-001"
verified_by:
  - "NFR-TEST-AUD-001"
```

```yaml
id: NFR-AUD-002
title: "Audit evidence phải được bảo toàn khi chứng từ bị hủy, thay thế hoặc điều chỉnh"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Khả năng kiểm toán và truy xuất"
description: "Audit evidence phải có durability xuyên suốt vòng đời đối tượng nghiệp vụ và không bị mất khi đối tượng bị hủy, thay thế, đảo, điều chỉnh hoặc xử lý ngoại lệ. Document lifecycle/common-state capability là applicability case khi nằm trong approved scope, không phải prerequisite của audit durability."
success_criteria:
  - "Bằng chứng trước/sau và liên kết tới đối tượng cũ/mới vẫn truy cập được sau khi hủy, thay thế hoặc điều chỉnh theo thời hạn lưu giữ áp dụng."
  - "Không có bước xử lý ngoại lệ hợp lệ nào làm mất audit history đã phát sinh trước đó."
depends_on:
  - "BR-AUD-001"
implemented_by:
  - "NFS-AUD-002"
verified_by:
  - "NFR-TEST-AUD-002"
```

```yaml
id: NFR-AUD-003
title: "Audit evidence phải truy vấn được và tái lập được ngữ cảnh trong thời gian lưu giữ"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Khả năng kiểm toán và truy xuất"
description: "Trong thời gian retention có hiệu lực, audit evidence của giao dịch, báo cáo hoặc quyết định phải có thể được truy vấn và tái lập ngữ cảnh cần thiết để giải thích nguồn, thời điểm, phạm vi và actor liên quan. Report snapshot/decision report là applicability cases khi capability tương ứng nằm trong approved scope, không phải prerequisite để audit evidence queryable."
success_criteria:
  - "Từ mẫu giao dịch và, khi capability tương ứng nằm trong approved scope, mẫu báo cáo/decision reference có thể truy ra chuỗi audit evidence và tái lập được ngữ cảnh gồm thời điểm, phạm vi và actor liên quan."
  - "Evidence vẫn queryable trong toàn bộ retention period áp dụng và không phụ thuộc vào việc bản ghi nghiệp vụ còn ở trạng thái hiện hành."
depends_on:
  - "BR-AUD-001"
implemented_by:
  - "NFS-AUD-003"
verified_by:
  - "NFR-TEST-AUD-003"
```

```yaml
id: BR-MDM-OWNERSHIP-001
title: "Phân loại dữ liệu theo System Master, Group Master, Company Master và Transaction Data"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "ERP Product Baseline Decision"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
  - "DEC-PRICING-001"
description: "Doanh nghiệp cần phân loại ownership của dữ liệu theo bốn mức System Master, Group Master, Company Master và Transaction Data; Transaction Data phải thuộc đúng một Company. Reference/common data tuân thủ AR-DATA-OWNERSHIP-001."
success_criteria:
  - "Mỗi loại dữ liệu thuộc phạm vi quản trị xác định được ownership level và quy tắc truy cập tương ứng."
  - "Product Master, Product Default Selling Price, Customer, Supplier, Employee và Position được phân loại Group Master."
  - "Employee Assignment, Department và Company Pricing Policy được phân loại Company Master; Group Pricing Policy được phân loại Group Master."
  - "Không có Transaction Data thiếu Company sở hữu hoặc thuộc trực tiếp Group."
depends_on:
  - "BR-ORG-GROUP-002"
  - "BR-ORG-001"
implemented_by:
  - "FR-MDM-OWNERSHIP-001"
verified_by:
  - "UAT-MDM-OWNERSHIP-001"
```

```yaml
id: BR-MDM-001
title: "Quản lý standard, governance và common primitives dùng chung giữa các miền nghiệp vụ"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
description: "Doanh nghiệp cần quản lý các standard, governance rules và common primitives dùng chung như đơn vị tính, nhóm/phân loại dùng chung, quốc gia, địa bàn, mã HS, thuế suất và các danh mục nền tảng khác. Ownership của reference/common data phải tuân thủ AR-DATA-OWNERSHIP-001. Product, Customer, Supplier và Employee là business entity do domain tương ứng sở hữu; MDM không trở thành owning domain thay thế."
success_criteria:
  - "Các miền sử dụng cùng định nghĩa common primitive và không tạo danh mục chuẩn cạnh tranh cho cùng ý nghĩa."
  - "Mỗi common primitive/reference data được phân loại System Master, Group Master hoặc Company Master theo AR-DATA-OWNERSHIP-001 trước khi được công bố sử dụng."
  - "Ngoại lệ với ownership rule mặc định xác định được owning domain và căn cứ nghiệp vụ."
  - "Mỗi business entity Product, Customer, Supplier và Employee vẫn xác định được owning domain riêng."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-MDM-001"
verified_by:
  - "UAT-MDM-001"
```

```yaml
id: BR-MDM-002
title: "Chuẩn hóa mã định danh dùng chung cho Customer, Supplier và địa điểm"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
description: "Doanh nghiệp cần áp dụng chuẩn định danh và quy tắc mã thống nhất cho Customer, Supplier và địa điểm. Customer và Supplier do domain tương ứng sở hữu; MDM chỉ cung cấp standard/governance cho định danh và không sở hữu business entity. Employee không thuộc phạm vi business entity của requirement này."
success_criteria:
  - "Mỗi Customer, Supplier và địa điểm có mã chuẩn phù hợp với quy tắc định danh được phê duyệt và có thể truy ra các giao dịch liên quan."
  - "Chuẩn định danh không tạo master Customer/Supplier cạnh tranh trong MDM."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-MDM-002"
verified_by:
  - "UAT-MDM-002"
```

```yaml
id: BR-MDM-009
title: "Áp dụng RD Code như specialization của chuẩn định danh cho vật tư, bán thành phẩm, thành phẩm và Supplier"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
source_refs:
  - "DEC-MDM-IDENTIFIER-001"
description: "Doanh nghiệp cần áp dụng RD Code thống nhất cho vật tư, bán thành phẩm, thành phẩm và Supplier trong toàn bộ phạm vi được phê duyệt. `BR-MDM-002` là generic identifier governance; RD Code là một specialization của identifier standard và không tạo business entity/master identity khác. Mỗi entity vẫn có immutable internal system identity, business code theo policy, RD Code khi thuộc phạm vi và optional partner/external codes khi áp dụng."
success_criteria:
  - "Mỗi đối tượng thuộc phạm vi RD Code có RD Code duy nhất, xác định được loại và nhóm; quy tắc mã có chủ sở hữu, ngày hiệu lực và không tái sử dụng mã đã dùng."
  - "RD Code liên kết tới cùng Product/Supplier canonical và không tạo duplicate Product/Supplier master."
  - "Có thể phân biệt internal system identity, business code, RD Code và external/partner code của cùng business entity."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-002"
implemented_by:
  - "FR-MDM-009"
verified_by:
  - "UAT-MDM-009"
```

```yaml
id: BR-MDM-010
title: "Chuẩn hóa địa chỉ theo danh mục Tỉnh/Phường/Xã được phê duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
description: "Doanh nghiệp cần quản lý thống nhất địa chỉ của khách hàng, nhà cung cấp, nhân viên và các đối tượng liên quan theo danh mục Tỉnh/Phường/Xã controlled reference data được Dinktech phê duyệt để phục vụ liên hệ và giao nhận hàng hóa. Khi danh mục này được xác lập từ external official standard/source, source/version/locator phải được đăng ký theo quy tắc `source_refs` trước khi requirement liên quan được approved."
success_criteria:
  - "Địa chỉ mới phải chọn mã Tỉnh/Phường/Xã còn hiệu lực từ danh mục được phê duyệt; dữ liệu lịch sử vẫn giữ được mã và tên địa chỉ đã sử dụng tại thời điểm phát sinh."
  - "Có thể xác định owner, version/effective time của danh mục; nếu dùng external official source thì controlled source reference phải resolve được trước approval."
depends_on:
  - "BR-MDM-002"
implemented_by:
  - "FR-MDM-010"
verified_by:
  - "UAT-MDM-010"
```

```yaml
id: BR-MDM-003
title: "Áp dụng phạm vi chia sẻ và quyền chỉnh sửa theo ownership level"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
description: "Doanh nghiệp cần áp dụng đúng phạm vi chia sẻ, sử dụng và chỉnh sửa dữ liệu dựa trên ownership level System Master, Group Master, Company Master và Transaction Data. Người dùng chỉ được xem hoặc thay đổi dữ liệu trong phạm vi được ủy quyền."
success_criteria:
  - "Dữ liệu System/Group/Company được chia sẻ đúng phạm vi và không tạo bản sao cạnh tranh chỉ để phục vụ truy cập địa phương."
  - "Transaction Data luôn xác định được đúng một Company sở hữu."
  - "Người dùng không sửa được dữ liệu ngoài ownership scope và Authorization được cấp."
depends_on:
  - "BR-ORG-001"
  - "BR-MDM-OWNERSHIP-001"
  - "BR-MDM-001"
  - "BR-MDM-002"
implemented_by:
  - "FR-MDM-003"
verified_by:
  - "UAT-MDM-003"
```

```yaml
id: BR-MDM-OVERRIDE-001
title: "Hỗ trợ Field Level Override cho dữ liệu Group Master theo chính sách"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "ERP Product Baseline Decision"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
  - "DEC-PRICING-001"
description: "Doanh nghiệp áp dụng Field Level Override theo nguyên tắc default deny: Company chỉ được override field của Group Master khi owning-domain requirement/policy khai báo rõ field đó là overrideable; các field không được khai báo tiếp tục sử dụng giá trị Group Master. Field Level Override không tạo một business entity master mới ở Company và không thay đổi owning domain của entity. Cơ chế này không được dùng để Company sửa trực tiếp Group Pricing Policy hoặc Product Default Selling Price; khác biệt giá ở Company được biểu diễn bằng Company Pricing Policy theo BR-CUS-003 và BR-SAL-009."
success_criteria:
  - "Có thể xác định field nào được khai báo overrideable, Company nào áp dụng, giá trị Group gốc, giá trị override và thời gian hiệu lực."
  - "Field không được owning-domain requirement/policy cho phép override không thể tạo Company override bằng configuration thông thường."
  - "Override không làm thay đổi giá trị Group Master của các Company khác."
  - "Không tạo master cạnh tranh ở Company chỉ vì một field được override."
  - "Pricing override ở Company được truy về Company Pricing Policy, không ghi đè Group Pricing Policy hoặc Product Default Selling Price."
depends_on:
  - "BR-MDM-OWNERSHIP-001"
  - "BR-MDM-003"
implemented_by:
  - "FR-MDM-OVERRIDE-001"
verified_by:
  - "UAT-MDM-OVERRIDE-001"
```

```yaml
id: BR-MDM-004
title: "Kiểm soát trạng thái, ngày hiệu lực và lý do thay đổi của bản ghi chuẩn"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
description: "Doanh nghiệp cần kiểm soát trạng thái, ngày hiệu lực và lý do thay đổi của bản ghi chuẩn."
success_criteria:
  - "Bản ghi hết hiệu lực không được dùng cho giao dịch mới; lịch sử vẫn được bảo toàn."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-002"
implemented_by:
  - "FR-MDM-004"
verified_by:
  - "UAT-MDM-004"
```

```yaml
id: BR-MDM-005
title: "Phân công và phê duyệt thay đổi thuộc tính dữ liệu nhạy cảm"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
source_refs:
  - "DEC-SENSITIVE-MDM-001"
description: "Doanh nghiệp cần cơ chế phân công và phê duyệt thay đổi đối với thuộc tính dữ liệu nhạy cảm như mã thuế, tài khoản ngân hàng, giá vốn và điều kiện thanh toán. Business approval thuộc requirement này sử dụng Shared Workflow để giữ quyết định và lịch sử."
success_criteria:
  - "Thay đổi nhạy cảm xác định được chủ sở hữu, người đề nghị, người phê duyệt, ngày hiệu lực, quyết định và bằng chứng."
  - "Không áp dụng chính thức thay đổi thuộc diện phải phê duyệt khi workflow chưa đạt trạng thái cho phép."
depends_on:
  - "BR-MDM-002"
  - "BR-IAM-003"
  - "BR-WF-001"
implemented_by:
  - "FR-MDM-005"
verified_by:
  - "UAT-MDM-005"
```

```yaml
id: BR-MDM-006
title: "Nhận diện bản ghi trùng và hỗ trợ hợp nhất có kiểm soát mà không làm mất lịch sử giao dịch"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
description: "Doanh nghiệp cần nhận diện bản ghi trùng và hỗ trợ hợp nhất có kiểm soát mà không làm mất lịch sử giao dịch."
success_criteria:
  - "Bản ghi hợp nhất giữ được liên kết tới mã cũ và người phê duyệt."
depends_on:
  - "BR-MDM-002"
  - "BR-MDM-004"
  - "BR-WF-001"
implemented_by:
  - "FR-MDM-006"
verified_by:
  - "UAT-MDM-006"
```

```yaml
id: BR-MDM-007
title: "Đánh giá chất lượng dữ liệu theo tính đầy đủ, hợp lệ, duy nhất, nhất quán và kịp thời"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Quản trị dữ liệu dùng chung"
description: "Doanh nghiệp cần đánh giá chất lượng dữ liệu theo tính đầy đủ, hợp lệ, duy nhất, nhất quán và kịp thời."
success_criteria:
  - "Có điểm chất lượng, danh sách lỗi, chủ sở hữu và hạn xử lý."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-002"
implemented_by:
  - "FR-MDM-007"
verified_by:
  - "UAT-MDM-007"
```

```yaml
id: BR-MDM-008
title: "Tra cứu nguồn, người sở hữu và lịch sử thay đổi của mọi bản ghi chuẩn"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị dữ liệu dùng chung"
description: "Doanh nghiệp cần tra cứu nguồn, người sở hữu và lịch sử thay đổi của mọi bản ghi chuẩn thông qua cơ chế Central Audit đối với các thay đổi thuộc diện phải audit."
success_criteria:
  - "Người kiểm tra truy được nguồn, người sở hữu và các phiên bản/thay đổi trước theo phạm vi audit được phê duyệt."
depends_on:
  - "BR-MDM-004"
  - "BR-AUD-001"
implemented_by:
  - "FR-MDM-008"
verified_by:
  - "UAT-MDM-008"
```

```yaml
id: BR-PRD-001
title: "Quản lý Product Master ở Group level cho các item vận hành ERP"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
description: "Doanh nghiệp cần quản lý Product Master như business entity thuộc Product domain và là Group Master dùng chung cho các Company trong cùng Group. Product Master bao gồm vật tư sản xuất, vật tư tiêu hao, bán thành phẩm, thành phẩm, sản phẩm vừa sản xuất vừa bán, hàng hóa thương mại, combo, quà tặng và các item vận hành phù hợp. Product Master không phải đối tượng thiết kế kỹ thuật của PLM."
success_criteria:
  - "Mỗi Product Master xác định được Group sở hữu, loại item và chính sách mua, tồn, sản xuất, bán hoặc sử dụng phù hợp."
  - "Các Company trong Group có thể tham chiếu cùng Product Master theo phạm vi được phép mà không phải tạo Product master cạnh tranh."
  - "Product Master được quản lý độc lập với Engineering Product/Development Product trong PLM."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-OWNERSHIP-001"
implemented_by:
  - "FR-PRD-001"
verified_by:
  - "UAT-PRD-001"
```

```yaml
id: BR-PRD-002
title: "Quản lý thuộc tính vận hành của Product Master gồm mã, tên, nhóm, quy cách, đơn vị tính, trọng lượng, kích thước và trạng thái"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
description: "Doanh nghiệp cần quản lý các thuộc tính vận hành của Product Master gồm mã, tên, mô tả, nhóm, quy cách, đơn vị tính, trọng lượng, kích thước, trạng thái kinh doanh và các thuộc tính cần thiết cho mua hàng, tồn kho, bán hàng, giao nhận hoặc sản xuất."
success_criteria:
  - "Product Master có mã duy nhất và thiếu thông tin bắt buộc thì không được công bố sử dụng."
  - "Trọng lượng, kích thước và đơn vị tính khi áp dụng có thể được sử dụng nhất quán bởi các nghiệp vụ kho, bán hàng và giao nhận."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-009"
implemented_by:
  - "FR-PRD-002"
verified_by:
  - "UAT-PRD-002"
```

```yaml
id: BR-PRD-003
title: "Hỗ trợ nhiều đơn vị tính và quy đổi có kiểm soát cho mua, tồn, sản xuất và bán"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
description: "Doanh nghiệp cần hỗ trợ nhiều đơn vị tính và quy đổi có kiểm soát cho mua, tồn, sản xuất và bán."
success_criteria:
  - "Quy đổi có chiều, hệ số, ngày hiệu lực và không làm sai số lượng gốc."
depends_on:
  - "BR-PRD-002"
implemented_by:
  - "FR-PRD-003"
verified_by:
  - "UAT-PRD-003"
```

```yaml
id: BR-PRD-004
title: "Đánh dấu sản phẩm yêu cầu quản lý lô, serial, hạn dùng, kiểm tra chất lượng hoặc điều kiện bảo quản"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
description: "Doanh nghiệp cần đánh dấu sản phẩm yêu cầu quản lý lô, serial, hạn dùng, kiểm tra chất lượng hoặc điều kiện bảo quản."
success_criteria:
  - "Giao dịch liên quan bắt buộc có thông tin truy xuất tương ứng."
depends_on:
  - "BR-PRD-002"
implemented_by:
  - "FR-PRD-004"
verified_by:
  - "UAT-PRD-004"
```

```yaml
id: BR-PRD-005
title: "Quản lý phạm vi sử dụng Product Master theo công ty, kênh, thị trường và nhóm phân loại giá"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
description: "Doanh nghiệp cần quản lý phạm vi sử dụng Product Master theo Company, kênh bán, thị trường và nhóm phân loại giá khi áp dụng. Product Master vẫn là Group Master; việc hạn chế hoặc mở phạm vi sử dụng cho Company/kênh không thay đổi ownership của Product Master. Nhóm phân loại giá chỉ là thuộc tính phân nhóm để Pricing & Promotion tham chiếu, không tự tạo mức giá hoặc chính sách giá."
success_criteria:
  - "Phạm vi sử dụng Product Master được xác định rõ trước khi phát sinh giao dịch."
  - "Thay đổi phạm vi sử dụng không tạo Product Master mới chỉ để phục vụ một Company."
  - "Việc gán nhóm phân loại giá không làm thay đổi Product Default Selling Price nếu không có Pricing Policy có hiệu lực."
depends_on:
  - "BR-PRD-002"
  - "BR-ORG-001"
implemented_by:
  - "FR-PRD-005"
verified_by:
  - "UAT-PRD-005"
```

```yaml
id: BR-PRD-006
title: "Quản lý combo và quà tặng với quan hệ cấu thành, chính sách doanh thu và tồn kho riêng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
description: "Doanh nghiệp cần quản lý combo và quà tặng với quan hệ cấu thành, chính sách doanh thu và tồn kho riêng."
success_criteria:
  - "Báo cáo phân biệt sản phẩm bán, thành phần tiêu hao và quà tặng không tính doanh thu."
depends_on:
  - "BR-PRD-002"
  - "BR-PRD-003"
implemented_by:
  - "FR-PRD-006"
verified_by:
  - "UAT-PRD-006"
```

```yaml
id: BR-PRD-007
title: "Quản lý trạng thái sử dụng của Product Master từ nháp, đang hoạt động, tạm ngừng đến ngừng sử dụng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
description: "Doanh nghiệp cần quản lý trạng thái vận hành của Product Master từ nháp, đang hoạt động/kinh doanh, tạm ngừng đến ngừng sử dụng. Đây là trạng thái sử dụng trong ERP, không phải Product Development Lifecycle của PLM."
success_criteria:
  - "Product Master ngừng sử dụng không được phát sinh giao dịch mới nhưng lịch sử vẫn xem được."
  - "Trạng thái Product Master không được hiểu hoặc đồng bộ ngầm thành giai đoạn thiết kế/phát triển của PLM."
depends_on:
  - "BR-PRD-002"
  - "BR-MDM-004"
implemented_by:
  - "FR-PRD-007"
verified_by:
  - "UAT-PRD-007"
```

```yaml
id: BR-PRD-008
title: "Quản lý mã vạch, mã QR, mã đối tác và mã thay thế của sản phẩm"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
description: "Doanh nghiệp cần quản lý mã vạch, mã QR, mã đối tác và mã thay thế của sản phẩm."
success_criteria:
  - "Có thể truy ra cùng một sản phẩm từ mỗi mã được phê duyệt."
depends_on:
  - "BR-PRD-002"
implemented_by:
  - "FR-PRD-008"
verified_by:
  - "UAT-PRD-008"
```

```yaml
id: BR-PRD-009
title: "Quản lý Product Default Selling Price ở Group level làm giá cơ sở"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị sản phẩm và danh mục hàng hóa"
source_refs:
  - "DEC-PRICING-001"
description: "Doanh nghiệp cần quản lý Product Default Selling Price thuộc Product domain ở Group level để làm giá cơ sở cuối cùng cho giao dịch bán khi không có Company hoặc Group Pricing Policy/Price List hợp lệ áp dụng. Mỗi default price xác định currency. Các Company trong cùng Group sử dụng giá mặc định này theo phạm vi giao dịch; không tồn tại Company default-price field override mặc định. Khác biệt giá theo Company được quản lý bằng Company Pricing Policy theo BR-CUS-003/BR-SAL-009. Product không bắt buộc phải được đưa vào một bảng giá hoặc Pricing Policy riêng."
success_criteria:
  - "Product Master chưa thuộc Pricing Policy có hiệu lực vẫn xác định được giá bán cơ sở từ Product Default Selling Price của Group."
  - "Khi có Company hoặc Group Pricing Policy hợp lệ áp dụng, giá theo policy được ưu tiên theo precedence của BR-SAL-009 và nguồn giá cuối cùng có thể truy vết."
  - "Product Default Selling Price xác định được Group, currency, thời gian hiệu lực và lịch sử thay đổi."
  - "Không tạo Company-specific default-price field override; khác biệt giá theo Company được biểu diễn bằng Company Pricing Policy."
depends_on:
  - "BR-PRD-002"
  - "BR-ORG-005"
  - "BR-MDM-OWNERSHIP-001"
implemented_by:
  - "FR-PRD-009"
verified_by:
  - "UAT-PRD-009"
```

```yaml
id: BR-PLM-001
title: "Quản lý vòng đời Engineering Product/Development Product trong quá trình thiết kế và phát triển"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị vòng đời sản phẩm"
description: "Doanh nghiệp cần quản lý Engineering Product/Development Product trong PLM từ ý tưởng, phát triển, mẫu thử, xác minh/xác nhận, sản xuất thử hoặc phát hành kỹ thuật đến ngừng phát triển và lỗi thời. Đối tượng PLM là dữ liệu thiết kế/phát triển độc lập với Product Master vận hành ERP."
success_criteria:
  - "Mỗi Engineering Product/Development Product có giai đoạn vòng đời, chủ sở hữu, trạng thái, mốc quyết định và điều kiện chuyển giai đoạn được xác định."
  - "Có thể tạo và quản lý Engineering Product/Development Product mà không yêu cầu phải tồn tại Product Master tương ứng."
depends_on: []
implemented_by:
  - "FR-PLM-001"
verified_by:
  - "UAT-PLM-001"
```

```yaml
id: BR-PLM-002
title: "Quản lý version/revision của Engineering Product và dữ liệu kỹ thuật"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị vòng đời sản phẩm"
description: "Doanh nghiệp cần quản lý version/revision của Engineering Product/Development Product theo mục đích, trạng thái, phạm vi áp dụng, ngày hiệu lực kỹ thuật và lịch sử thay đổi trong PLM. Version/revision PLM không phải version của Product Master ERP."
success_criteria:
  - "Có thể xác định version/revision kỹ thuật hiện hành và truy lại đầy đủ các version/revision trước."
  - "Thay đổi revision không ghi đè làm mất dữ liệu kỹ thuật đã từng có hiệu lực."
depends_on:
  - "BR-PLM-001"
implemented_by:
  - "FR-PLM-002"
verified_by:
  - "UAT-PLM-002"
```

```yaml
id: BR-PLM-003
title: "Quản lý dữ liệu kỹ thuật của Engineering Product độc lập với Product Master ERP"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị vòng đời sản phẩm"
description: "Doanh nghiệp cần quản lý dữ liệu kỹ thuật của Engineering Product/Development Product trong PLM, gồm tài liệu kỹ thuật, bản vẽ, specification, BOM/cấu trúc kỹ thuật, tiêu chuẩn và kết quả thử nghiệm/xác nhận khi áp dụng. Dữ liệu PLM không được hiểu là thuộc tính vận hành của Product Master ERP."
success_criteria:
  - "Mỗi dữ liệu kỹ thuật xác định được Engineering Product, version/revision, chủ sở hữu, trạng thái và lịch sử thay đổi."
  - "Việc thay đổi dữ liệu kỹ thuật trong PLM không tự động sửa Product Master, giá bán, tồn kho hoặc dữ liệu giao dịch ERP."
depends_on:
  - "BR-PLM-002"
implemented_by:
  - "FR-PLM-003"
verified_by:
  - "UAT-PLM-003"
```

```yaml
id: BR-ENG-001
title: "Quản lý yêu cầu thay đổi kỹ thuật (ECR) cho Engineering Product trong PLM"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị thay đổi kỹ thuật"
description: "Doanh nghiệp cần quản lý yêu cầu thay đổi kỹ thuật (ECR) với lý do, nguồn đề nghị, Engineering Product/revision bị ảnh hưởng, mức độ ưu tiên và hồ sơ lịch sử."
success_criteria:
  - "Mỗi ECR có chủ sở hữu, phạm vi kỹ thuật, trạng thái và quyết định chấp thuận, từ chối hoặc hoãn."
depends_on:
  - "BR-PLM-002"
  - "BR-PLM-003"
  - "BR-WF-001"
implemented_by:
  - "FR-ENG-001"
verified_by:
  - "UAT-ENG-001"
```

```yaml
id: BR-ENG-002
title: "Đánh giá tác động của thay đổi kỹ thuật trong phạm vi PLM"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị thay đổi kỹ thuật"
description: "Doanh nghiệp cần đánh giá tác động của thay đổi kỹ thuật đến Engineering Product, version/revision, BOM/cấu trúc kỹ thuật, bản vẽ, specification, tiêu chuẩn, tài liệu và kết quả thử nghiệm trong phạm vi PLM."
success_criteria:
  - "Hồ sơ thay đổi thể hiện các đối tượng kỹ thuật bị ảnh hưởng, rủi ro, hành động chuyển tiếp và người xác nhận."
depends_on:
  - "BR-ENG-001"
  - "BR-PLM-003"
implemented_by:
  - "FR-ENG-002"
verified_by:
  - "UAT-ENG-002"
```

```yaml
id: BR-ENG-003
title: "Phát hành thông báo thay đổi kỹ thuật (ECN) và lệnh thay đổi kỹ thuật (ECO) sau khi thay đổi được phê duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị thay đổi kỹ thuật"
description: "Doanh nghiệp cần phát hành thông báo thay đổi kỹ thuật (ECN) và lệnh thay đổi kỹ thuật (ECO) sau khi thay đổi được phê duyệt, có ngày hiệu lực kỹ thuật và phạm vi Engineering Product/revision áp dụng rõ ràng."
success_criteria:
  - "ECN/ECO liên kết được với ECR, quyết định phê duyệt, revision trước, revision mới và các dữ liệu kỹ thuật bị ảnh hưởng."
depends_on:
  - "BR-ENG-001"
  - "BR-ENG-002"
implemented_by:
  - "FR-ENG-003"
verified_by:
  - "UAT-ENG-003"
```

```yaml
id: BR-ENG-004
title: "Kiểm soát sửa đổi BOM kỹ thuật, bản vẽ, specification và tiêu chuẩn trong PLM mà không làm mất revision trước"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị thay đổi kỹ thuật"
description: "Doanh nghiệp cần kiểm soát việc sửa đổi BOM/cấu trúc kỹ thuật, bản vẽ, specification, tiêu chuẩn và tài liệu kỹ thuật liên quan đến Engineering Change mà không làm mất lịch sử revision trước."
success_criteria:
  - "Mỗi sửa đổi có căn cứ, người chịu trách nhiệm, ngày hiệu lực kỹ thuật và quan hệ truy xuất tới Engineering Product/revision tương ứng."
depends_on:
  - "BR-ENG-003"
  - "BR-PLM-002"
  - "BR-PLM-003"
implemented_by:
  - "FR-ENG-004"
verified_by:
  - "UAT-ENG-004"
```

```yaml
id: BR-ENG-005
title: "Kiểm soát thời điểm hiệu lực và việc áp dụng thay đổi kỹ thuật trong vòng đời phát triển sản phẩm"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị thay đổi kỹ thuật"
description: "Doanh nghiệp cần kiểm soát thời điểm hiệu lực và phạm vi áp dụng của thay đổi kỹ thuật đối với các giai đoạn phát triển, mẫu thử, thử nghiệm/xác nhận và hồ sơ phát hành kỹ thuật trong PLM."
success_criteria:
  - "Mỗi thay đổi xác định được revision bị thay thế, revision mới, mốc hiệu lực, phạm vi áp dụng và hành động chuyển tiếp đối với hồ sơ phát triển đang mở."
depends_on:
  - "BR-ENG-003"
  - "BR-PLM-001"
  - "BR-PLM-002"
implemented_by:
  - "FR-ENG-005"
verified_by:
  - "UAT-ENG-005"
```

```yaml
id: BR-ENG-006
title: "Lưu hồ sơ thay đổi kỹ thuật xuyên suốt vòng đời Engineering Product trong PLM"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị thay đổi kỹ thuật"
description: "Doanh nghiệp cần lưu hồ sơ thay đổi kỹ thuật xuyên suốt vòng đời Engineering Product để truy xuất từ ECR/ECN/ECO đến version/revision, tài liệu kỹ thuật, bản vẽ, specification, BOM/cấu trúc kỹ thuật và kết quả thử nghiệm liên quan."
success_criteria:
  - "Có thể truy xuôi và truy ngược lịch sử thay đổi của một Engineering Product hoặc revision trong phạm vi PLM."
depends_on:
  - "BR-ENG-003"
  - "BR-PLM-003"
implemented_by:
  - "FR-ENG-006"
verified_by:
  - "UAT-ENG-006"
```

```yaml
id: BR-CUS-001
title: "Quản lý Customer như Group Master thuộc Customer domain"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị khách hàng và chính sách thương mại"
description: "Doanh nghiệp cần quản lý Customer như business entity thuộc Customer domain và là Group Master dùng chung cho các Company trong cùng Group. Customer có thể là khách lẻ, đại lý, doanh nghiệp hoặc nhà phân phối và có thông tin liên hệ, địa chỉ, mã số thuế, nhóm khách hàng và trạng thái phù hợp."
success_criteria:
  - "Mỗi Customer có Group sở hữu, mã duy nhất trong phạm vi áp dụng và chủ sở hữu nghiệp vụ."
  - "Các Company trong Group có thể sử dụng cùng Customer theo quyền được cấp mà không phải tạo Customer master cạnh tranh."
depends_on:
  - "BR-MDM-002"
  - "BR-MDM-OWNERSHIP-001"
implemented_by:
  - "FR-CUS-001"
verified_by:
  - "UAT-CUS-001"
```

```yaml
id: BR-CUS-007
title: "Quản lý hồ sơ, phân loại, địa chỉ, đầu mối và vòng đời Customer"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị khách hàng và chính sách thương mại"
description: "Doanh nghiệp cần quản lý thông tin khách hàng theo danh mục, nhóm, địa chỉ giao dịch, nhiều đầu mối liên hệ, trạng thái và vòng đời từ tiềm năng đến ngừng giao dịch."
success_criteria:
  - "Mỗi khách hàng có hồ sơ thống nhất, trạng thái hiện tại, lịch sử trạng thái và các địa chỉ/đầu mối còn hiệu lực."
depends_on:
  - "BR-CUS-001"
implemented_by:
  - "FR-CUS-007"
verified_by:
  - "UAT-CUS-007"
```

```yaml
id: BR-CUS-009
title: "Ghi nhận lịch sử liên lạc và nội dung trao đổi"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Quản trị khách hàng và chính sách thương mại"
description: "Doanh nghiệp cần ghi nhận lịch sử liên lạc và nội dung trao đổi với khách hàng theo mục đích kinh doanh, người tham gia và đối tượng liên quan."
success_criteria:
  - "Lịch sử liên lạc được truy xuất theo khách hàng, đầu mối, cơ hội hoặc đơn hàng trong phạm vi quyền."
depends_on:
  - "BR-CUS-007"
implemented_by:
  - "FR-CUS-009"
verified_by:
  - "UAT-CUS-009"
```

```yaml
id: BR-CUS-002
title: "Quản lý điều kiện thanh toán, hạn mức tín dụng, người phụ trách và trạng thái khách hàng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị khách hàng và chính sách thương mại"
source_refs:
  - "DEC-SENSITIVE-MDM-001"
description: "Doanh nghiệp cần quản lý điều kiện thanh toán, hạn mức tín dụng, người phụ trách và trạng thái khách hàng. Thay đổi Payment Terms hoặc Credit Limit phải được đánh giá theo approval rule/threshold/policy có hiệu lực; khi rule yêu cầu approval, thay đổi phải sử dụng Shared Workflow theo BR-MDM-005 trước khi có hiệu lực. Thay đổi không thuộc diện approval vẫn phải giữ ngày hiệu lực và lịch sử phù hợp."
success_criteria:
  - "Giao dịch mới phản ánh chính sách có hiệu lực của khách hàng."
  - "Nếu approval rule/threshold/policy được kích hoạt, Payment Terms hoặc Credit Limit mới không có hiệu lực trước khi Shared Workflow đạt trạng thái cho phép."
  - "Có thể xác định rule/policy nào đã quyết định yêu cầu hoặc miễn approval cho thay đổi và truy được lịch sử trước/sau."
depends_on:
  - "BR-CUS-001"
  - "BR-MDM-005"
implemented_by:
  - "FR-CUS-002"
verified_by:
  - "UAT-CUS-002"
```

```yaml
id: BR-CUS-004
title: "Theo dõi lịch sử mua, công nợ, khiếu nại, bảo hành và trả hàng của khách hàng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị khách hàng và chính sách thương mại"
description: "Doanh nghiệp cần theo dõi lịch sử mua, công nợ, khiếu nại, bảo hành và trả hàng của khách hàng từ các source capability tương ứng khi các capability đó nằm trong approved scope. Customer là foundation bắt buộc; Sales, Finance, Returns, Warranty và các source capability liên quan là conditional sources, không trở thành Hard Prerequisite chỉ vì dữ liệu của chúng được hiển thị trong customer history."
success_criteria:
  - "Nhân viên được phân quyền xem lịch sử từ mọi source capability đang nằm trong approved scope và có dữ liệu liên quan tới khách hàng."
  - "Source capability chưa nằm trong approved scope không chặn customer history đối với các source đã sẵn sàng."
depends_on:
  - "BR-CUS-001"
implemented_by:
  - "FR-CUS-004"
verified_by:
  - "UAT-CUS-004"
```

```yaml
id: BR-CUS-006
title: "Quản lý thông tin đồng ý liên lạc, phân loại riêng tư và yêu cầu hạn chế sử dụng dữ liệu khách hàng"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Quản trị khách hàng và chính sách thương mại"
description: "Doanh nghiệp cần quản lý thông tin đồng ý liên lạc, phân loại riêng tư và yêu cầu hạn chế sử dụng dữ liệu khách hàng."
success_criteria:
  - "Có thể chứng minh mục đích sử dụng và phạm vi chia sẻ dữ liệu."
depends_on:
  - "BR-CUS-001"
  - "BR-IAM-007"
implemented_by:
  - "FR-CUS-006"
verified_by:
  - "UAT-CUS-006"
```

```yaml
id: BR-CUS-005
title: "Kiểm soát việc vượt hạn mức, bán chịu, chiết khấu ngoài chính sách và miễn phí vận chuyển"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Quản trị khách hàng và chính sách thương mại"
description: "Doanh nghiệp cần kiểm soát việc vượt hạn mức, bán chịu, chiết khấu ngoài chính sách và miễn phí vận chuyển."
success_criteria:
  - "Ngoại lệ có người phê duyệt, lý do và thời hạn."
depends_on:
  - "BR-CUS-002"
  - "BR-CUS-003"
  - "BR-WF-001"
implemented_by:
  - "FR-CUS-005"
verified_by:
  - "UAT-CUS-005"
```

```yaml
id: BR-CUS-003
title: "Quản lý Group và Company Pricing Policy/Price List theo điều kiện, currency và thời gian hiệu lực"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Pricing"
source_refs:
  - "DEC-PRICING-001"
owning_domain: "Pricing"
description: "Doanh nghiệp cần quản lý Pricing Policy/Price List khác với Product Default Selling Price theo khách hàng, nhóm khách hàng, kênh, số lượng, hợp đồng, currency và thời gian hiệu lực ở hai ownership level: Group Pricing Policy là Group Master dùng chung trong Group; Company Pricing Policy là Company Master chỉ áp dụng trong Company sở hữu. Company không sửa trực tiếp Group Pricing Policy mà tạo Company Pricing Policy riêng. Pricing Policy không bắt buộc phải liệt kê toàn bộ Product Master; chỉ các sản phẩm hoặc trường hợp cần thay đổi chính sách giá mới cần được định nghĩa. Thay đổi policy thuộc diện business approval phải sử dụng Shared Workflow. `BR-CUS-003` giữ ID lịch sử nhưng canonical owning domain của Pricing Policy là Pricing."
success_criteria:
  - "Mỗi Pricing Policy xác định được ownership level Group hoặc Company, chủ sở hữu, phạm vi áp dụng, điều kiện, currency, thời gian hiệu lực và lịch sử thay đổi."
  - "Company Pricing Policy không ghi đè nội dung của Group Pricing Policy mà tồn tại như policy object riêng trong Company."
  - "Khi đồng thời có nhiều policy thỏa điều kiện cho cùng pricing context/currency, Company Pricing Policy có precedence cao hơn Group Pricing Policy."
  - "Sản phẩm không thuộc Company hoặc Group Pricing Policy có hiệu lực vẫn sử dụng Product Default Selling Price của Group khi default price applicable cho pricing context/currency."
  - "Nguồn giá cuối cùng có thể truy vết về Company Pricing Policy, Group Pricing Policy hoặc Product Default Selling Price cụ thể."
depends_on:
  - "BR-CUS-001"
  - "BR-PRD-009"
  - "BR-WF-001"
implemented_by:
  - "FR-CUS-003"
verified_by:
  - "UAT-CUS-003"
```

```yaml
id: BR-PUR-001
title: "Quản lý Supplier như Group Master thuộc Supplier domain"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
source_refs:
  - "DEC-ERP-FOUNDATION-001"
  - "DEC-MDM-IDENTIFIER-001"
owning_domain: "Supplier"
description: "Doanh nghiệp cần quản lý Supplier như business entity thuộc Supplier domain và là Group Master dùng chung cho các Company trong cùng Group. Purchase là consuming/process domain sử dụng Supplier Master trong sourcing, RFQ, Purchase Order, receiving và supplier evaluation, không tạo Supplier master cạnh tranh. Supplier có loại, ngành hàng, phạm vi Company sử dụng, điều kiện thanh toán, tiền tệ, tình trạng hợp tác và RD Code theo identifier governance hiện hành."
success_criteria:
  - "Mỗi Supplier có Group sở hữu, immutable internal identity, business code/RD Code theo policy áp dụng và trạng thái hợp tác."
  - "Các Company trong Group có thể sử dụng cùng Supplier theo quyền được cấp mà không phải tạo Supplier master cạnh tranh."
  - "Purchase sử dụng Supplier Master canonical và không trở thành owning domain của Supplier."
  - "Supplier ngừng hợp tác không phát sinh cam kết mới."
depends_on:
  - "BR-MDM-002"
  - "BR-MDM-009"
  - "BR-MDM-OWNERSHIP-001"
implemented_by:
  - "FR-PUR-001"
verified_by:
  - "UAT-PUR-001"
```

```yaml
id: BR-PUR-009
title: "Kiểm soát thay đổi tài khoản ngân hàng, mã số thuế và điều khoản thanh toán của nhà cung cấp"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
source_refs:
  - "DEC-SENSITIVE-MDM-001"
description: "Doanh nghiệp cần kiểm soát thay đổi tài khoản ngân hàng, mã số thuế và điều khoản thanh toán của nhà cung cấp như sensitive master-data change. Thay đổi thuộc phạm vi này phải tuân thủ cơ chế phê duyệt tại BR-MDM-005 và đi qua Shared Workflow trước khi có hiệu lực."
success_criteria:
  - "Thay đổi nhạy cảm xác định được người đề nghị, người xác minh/phê duyệt, quyết định, ngày hiệu lực và lịch sử trước/sau."
  - "Thay đổi thuộc diện phê duyệt không có hiệu lực khi Shared Workflow chưa đạt trạng thái cho phép."
depends_on:
  - "BR-PUR-001"
  - "BR-MDM-005"
implemented_by:
  - "FR-PUR-009"
verified_by:
  - "UAT-PUR-009"
```

```yaml
id: BR-PUR-010
title: "Thẩm tra hồ sơ nhà cung cấp, chứng nhận, xung đột lợi ích và người phê duyệt trước khi đưa vào danh sách hợp tác"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
description: "Doanh nghiệp cần thẩm tra hồ sơ nhà cung cấp, chứng nhận, xung đột lợi ích và người phê duyệt trước khi đưa vào danh sách hợp tác."
success_criteria:
  - "Kết quả thẩm tra, ngày hiệu lực và quyết định được lưu cùng hồ sơ nhà cung cấp."
depends_on:
  - "BR-PUR-001"
  - "BR-WF-001"
implemented_by:
  - "FR-PUR-010"
verified_by:
  - "UAT-PUR-010"
```

```yaml
id: BR-PUR-011
title: "Duy trì danh sách nhà cung cấp được phép cung ứng theo từng sản phẩm"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
description: "Doanh nghiệp cần duy trì danh sách nhà cung cấp được phép cung ứng theo từng sản phẩm, công ty hoặc nhà máy khi chính sách yêu cầu."
success_criteria:
  - "Việc mua ngoài danh sách được nhận diện, có lý do và được phê duyệt; danh sách có ngày hiệu lực và người sở hữu."
depends_on:
  - "BR-PUR-001"
  - "BR-PRD-002"
  - "BR-WF-001"
implemented_by:
  - "FR-PUR-011"
verified_by:
  - "UAT-PUR-011"
```

```yaml
id: BR-PUR-008
title: "Đánh giá nhà cung cấp theo chất lượng, giao đúng hạn, giá, phản hồi và tỷ lệ khiếu nại"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
description: "Doanh nghiệp cần đánh giá nhà cung cấp theo chất lượng, giao đúng hạn, giá, phản hồi và tỷ lệ khiếu nại."
success_criteria:
  - "Điểm đánh giá có kỳ, tiêu chí, chủ sở hữu và bằng chứng."
depends_on:
  - "BR-PUR-005"
implemented_by:
  - "FR-PUR-008"
verified_by:
  - "UAT-PUR-008"
```

```yaml
id: BR-PUR-002
title: "Ghi nhận nhu cầu mua vật tư, hàng hóa, dịch vụ và chi phí với lý do, đơn vị yêu cầu và thời điểm cần"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
source_refs:
  - "DEC-PURCHASE-001"
description: "Doanh nghiệp cần ghi nhận Purchase Request cho vật tư, hàng hóa, dịch vụ và chi phí với lý do, đơn vị yêu cầu và thời điểm cần. Purchase Request có thể được tạo trước khi chọn Supplier. Mỗi purchase line được phân loại `Stock Item`, `Non-stock Item`, `Service` hoặc `Expense`; Product Master chỉ bắt buộc khi policy của loại dòng yêu cầu tham chiếu Product và không bắt buộc cho mọi Service/Expense line."
success_criteria:
  - "Mỗi nhu cầu xác định được người yêu cầu, Company/đơn vị chịu chi phí, thời điểm cần, loại purchase line và trạng thái quyết định."
  - "Purchase Request có thể được ghi nhận khi chưa chọn Supplier; Supplier được bổ sung trước khi hình thành cam kết mua khi nghiệp vụ yêu cầu."
  - "Service/Expense line có thể được ghi nhận không cần Product Master; line thuộc loại/policy yêu cầu Product phải tham chiếu Product Master hợp lệ."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "FR-PUR-002"
verified_by:
  - "UAT-PUR-002"
```

```yaml
id: BR-PUR-003
title: "Thu thập và so sánh báo giá khi policy mua hàng yêu cầu"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
source_refs:
  - "DEC-PURCHASE-001"
description: "Doanh nghiệp cần thu thập và so sánh báo giá theo giá, chất lượng, thời hạn, điều kiện giao và điều khoản thanh toán khi policy/threshold/category mua hàng yêu cầu RFQ hoặc quotation comparison. Trường hợp policy cho phép direct purchase không bắt buộc phải tạo bước quotation comparison."
success_criteria:
  - "Khi policy yêu cầu quotation comparison, quyết định lựa chọn Supplier có báo giá/căn cứ so sánh và người chịu trách nhiệm."
  - "Khi policy cho phép direct purchase, nghiệp vụ có thể tiếp tục mà không tạo RFQ/quotation giả chỉ để thỏa luồng."
depends_on:
  - "BR-PUR-002"
  - "BR-PUR-001"
implemented_by:
  - "FR-PUR-003"
verified_by:
  - "UAT-PUR-003"
```

```yaml
id: BR-PUR-004
title: "Quản lý cam kết mua theo đơn mua, loại dòng, Supplier, số lượng/giá trị, giá, thuế và giao hàng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
source_refs:
  - "DEC-PURCHASE-001"
description: "Doanh nghiệp cần quản lý cam kết mua theo Purchase Order, Supplier, loại purchase line, số lượng hoặc giá trị phù hợp với loại dòng, giá, thuế và điều kiện giao hàng/thực hiện. Purchase Order có thể được tạo theo direct-purchase policy hoặc từ quotation comparison khi policy yêu cầu. Budget không thuộc phạm vi requirement này; kiểm soát ngân sách chỉ được bổ sung khi có Budget Domain hoặc Integration Requirement riêng được phê duyệt."
success_criteria:
  - "Cam kết mua đối chiếu được với Purchase Request, Supplier, loại purchase line, số lượng/giá trị, giá, thuế và điều kiện giao hàng/thực hiện đã xác nhận."
  - "Báo giá/lựa chọn Supplier được liên kết khi policy yêu cầu; direct PO được nhận diện và chỉ được dùng khi policy cho phép."
depends_on:
  - "BR-PUR-002"
  - "BR-PUR-001"
implemented_by:
  - "FR-PUR-004"
verified_by:
  - "UAT-PUR-004"
```

```yaml
id: BR-PUR-005
title: "Kiểm soát nhận hàng theo số lượng, chất lượng, lô/serial và chênh lệch so với cam kết"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
description: "Doanh nghiệp cần kiểm soát việc nhận hàng theo số lượng, chất lượng, lô/serial, chi phí phát sinh và chênh lệch so với cam kết."
success_criteria:
  - "Chênh lệch được phân loại, có chủ sở hữu và quyết định xử lý."
depends_on:
  - "BR-PUR-004"
  - "BR-INV-001"
  - "BR-PRD-004"
implemented_by:
  - "FR-PUR-005"
verified_by:
  - "UAT-PUR-005"
```

```yaml
id: BR-PUR-006
title: "Hỗ trợ đối chiếu giữa cam kết mua, hàng đã nhận và hóa đơn nhà cung cấp khi chính sách áp dụng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
description: "Doanh nghiệp cần hỗ trợ đối chiếu giữa cam kết mua, hàng đã nhận và hóa đơn nhà cung cấp khi chính sách áp dụng."
success_criteria:
  - "Hóa đơn sai số lượng, giá hoặc điều kiện được nhận diện trước khi thanh toán."
depends_on:
  - "BR-PUR-004"
  - "BR-PUR-005"
  - "BR-FIN-002"
implemented_by:
  - "FR-PUR-006"
verified_by:
  - "UAT-PUR-006"
```

```yaml
id: BR-PUR-007
title: "Phân bổ chi phí vận chuyển, bảo hiểm, thuế nhập và chi phí liên quan vào giá trị hàng theo chính sách được duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Mua hàng và quản lý nhà cung cấp"
description: "Doanh nghiệp cần phân bổ chi phí vận chuyển, bảo hiểm, thuế nhập và chi phí liên quan vào giá trị hàng theo chính sách được duyệt."
success_criteria:
  - "Chi phí phân bổ có căn cứ, tổng phân bổ khớp chứng từ nguồn."
depends_on:
  - "BR-PUR-005"
implemented_by:
  - "FR-PUR-007"
verified_by:
  - "UAT-PUR-007"
```

```yaml
id: BR-INV-001
title: "Quản lý số lượng và giá trị tồn theo công ty"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần quản lý số lượng và giá trị tồn theo công ty, chi nhánh, kho, vị trí, sản phẩm, lô, serial và trạng thái chất lượng."
success_criteria:
  - "Số dư theo từng chiều cộng đúng lên tổng phạm vi."
depends_on:
  - "BR-ORG-002"
  - "BR-PRD-002"
implemented_by:
  - "FR-INV-001"
verified_by:
  - "UAT-INV-001"
```

```yaml
id: BR-INV-002
title: "Ghi nhận nhập hàng, nhập thành phẩm, xuất bán, xuất sản xuất, xuất bảo hành, trả hàng, phế phẩm và thanh lý"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần ghi nhận nhập hàng, nhập thành phẩm, xuất bán, xuất sản xuất, xuất bảo hành, trả hàng, phế phẩm và thanh lý."
success_criteria:
  - "Mỗi biến động có chứng từ nguồn, người chịu trách nhiệm và lý do."
depends_on:
  - "BR-INV-001"
  - "BR-PRD-004"
implemented_by:
  - "FR-INV-002"
verified_by:
  - "UAT-INV-002"
```

```yaml
id: BR-INV-003
title: "Quản lý chuyển kho nội bộ và phân biệt hàng đang vận chuyển với hàng đã nhận"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần quản lý chuyển kho nội bộ và phân biệt hàng đang vận chuyển với hàng đã nhận."
success_criteria:
  - "Kho nguồn, kho đích, số lượng và trạng thái chuyển được đối chiếu."
depends_on:
  - "BR-INV-001"
  - "BR-ORG-002"
implemented_by:
  - "FR-INV-003"
verified_by:
  - "UAT-INV-003"
```

```yaml
id: BR-INV-012
title: "Phân loại chuyển hàng nội bộ trong cùng Company và giao dịch liên công ty"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
source_refs:
  - "DEC-INTERCO-001"
description: "Doanh nghiệp cần phân loại rõ movement giữa Branch/Plant/Warehouse thuộc cùng một Company là intra-company inventory transfer và không được ghi nhận như intercompany transaction. Movement giữa hai Company khác nhau không thuộc scope của transfer nội bộ và phải được quản lý theo BR-INV-014."
success_criteria:
  - "Transfer giữa các Organization Context thuộc cùng một Company giữ nguyên owning Company và không tự tạo intercompany transaction."
  - "Movement giữa hai Company khác nhau không được phân loại là chuyển kho/chi nhánh nội bộ và được route sang intercompany flow theo BR-INV-014."
depends_on:
  - "BR-INV-003"
  - "BR-ORG-001"
implemented_by:
  - "FR-INV-012"
verified_by:
  - "UAT-INV-012"
```

```yaml
id: BR-INV-014
title: "Phân loại và phối hợp chuyển giao hàng tồn liên công ty"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
source_refs:
  - "DEC-INTERCO-001"
description: "Doanh nghiệp cần quản lý movement hàng tồn giữa hai Company trong cùng Group như một cross-company business process gồm các Business Transaction thành phần, mỗi transaction thuộc đúng một owning Company và được correlation bằng cùng `intercompany_reference`. `INTERCOMPANY_TRANSFER` tạo tối thiểu outbound inventory transaction thuộc Sending Company và inbound inventory transaction thuộc Receiving Company, không mặc định tạo sale/purchase, AR/AP hoặc invoice. `INTERCOMPANY_SALE` liên kết inventory outbound/inbound với sales/purchase và financial transaction tương ứng theo responsibility của từng Company và BR-FIN-017. `intercompany_reference` chỉ là coordination/reference key, không phải transaction owner."
success_criteria:
  - "Mỗi cross-company movement được phân loại rõ `INTERCOMPANY_TRANSFER` hoặc `INTERCOMPANY_SALE` và có `intercompany_reference` chung cho các transaction hai phía."
  - "Outbound Inventory Transaction thuộc đúng Sending Company; Inbound Inventory Transaction thuộc đúng Receiving Company; không có một inventory transaction duy nhất do hai Company cùng sở hữu."
  - "Hai phía đối chiếu được số lượng, trạng thái, thời điểm và ownership qua `intercompany_reference` mà không ghi nhận trùng hoặc nhầm với transfer nội bộ cùng Company."
  - "`INTERCOMPANY_TRANSFER` không tự tạo sale/purchase, AR/AP hoặc invoice nếu policy áp dụng không yêu cầu."
  - "`INTERCOMPANY_SALE` liên kết được inventory transaction với sales/purchase/financial transaction tương ứng mà vẫn giữ Company ownership riêng của từng transaction."
depends_on:
  - "BR-INV-001"
  - "BR-ORG-001"
implemented_by:
  - "FR-INV-014"
verified_by:
  - "UAT-INV-014"
```

```yaml
id: BR-INV-004
title: "Giữ hàng cho đơn bán, lệnh sản xuất hoặc mục đích được phê duyệt; số giữ phải tách khỏi tồn có thể cấp phát"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần giữ hàng cho đơn bán, lệnh sản xuất hoặc mục đích được phê duyệt; số giữ phải tách khỏi tồn có thể cấp phát."
success_criteria:
  - "Có thể biết số khả dụng, số đã giữ, số cách ly và số đang vận chuyển."
depends_on:
  - "BR-INV-001"
implemented_by:
  - "FR-INV-004"
verified_by:
  - "UAT-INV-004"
```

```yaml
id: BR-INV-005
title: "Kiểm kê định kỳ hoặc theo rủi ro, ghi nhận chênh lệch và phê duyệt điều chỉnh"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần kiểm kê định kỳ hoặc theo rủi ro, ghi nhận chênh lệch và phê duyệt điều chỉnh."
success_criteria:
  - "Chênh lệch có nguyên nhân, người đếm, người kiểm tra và tác động giá trị."
depends_on:
  - "BR-INV-001"
  - "BR-WF-001"
implemented_by:
  - "FR-INV-005"
verified_by:
  - "UAT-INV-005"
```

```yaml
id: BR-INV-006
title: "Hỗ trợ hàng lỗi, cách ly, chờ kiểm tra, hết hạn và hàng được phép sử dụng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần hỗ trợ hàng lỗi, cách ly, chờ kiểm tra, hết hạn và hàng được phép sử dụng."
success_criteria:
  - "Hàng không đạt không được cấp phát cho mục đích thông thường."
depends_on:
  - "BR-INV-001"
  - "BR-PRD-004"
implemented_by:
  - "FR-INV-006"
verified_by:
  - "UAT-INV-006"
```

```yaml
id: BR-INV-013
title: "Quản lý vòng đời lô/batch từ khi hình thành hoặc tiếp nhận đến khi sử dụng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần quản lý vòng đời lô/batch từ khi hình thành hoặc tiếp nhận đến khi sử dụng, chuyển giao, giao khách, bảo hành hoặc thu hồi."
success_criteria:
  - "Mỗi lô xác định được sản phẩm, nguồn, thời điểm, trạng thái, hạn dùng khi áp dụng và toàn bộ chứng từ liên quan."
depends_on:
  - "BR-INV-001"
  - "BR-PRD-004"
implemented_by:
  - "FR-INV-013"
verified_by:
  - "UAT-INV-013"
```

```yaml
id: BR-INV-007
title: "Truy xuất lô/serial từ nguồn mua hoặc sản xuất đến tồn, giao, khách hàng, bảo hành và thu hồi"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Doanh nghiệp cần truy xuất lô/serial từ nguồn mua hoặc sản xuất đến tồn, giao, khách hàng, bảo hành và thu hồi. Acceptance traceability sử dụng phương pháp lấy mẫu phân tầng theo nguồn Purchase/Production và kiểu Lot/Serial khi các nhóm đó áp dụng."
success_criteria:
  - "100% mẫu acceptance được chọn phải truy xuất thành công cả xuôi và ngược qua chuỗi nghiệp vụ liên quan."
  - "Mẫu được lấy phân tầng để bao phủ nguồn Purchase, nguồn Production, Lot và Serial khi các nhóm đó có trong phạm vi; tối thiểu 5 mẫu cho mỗi nhóm áp dụng, hoặc kiểm tra toàn bộ nếu population của nhóm nhỏ hơn 5."
  - "Một mẫu không truy xuất đầy đủ được xem là acceptance failure và phải được xử lý trước khi requirement được chấp nhận."
depends_on:
  - "BR-INV-002"
  - "BR-PRD-004"
implemented_by:
  - "FR-INV-007"
verified_by:
  - "UAT-INV-007"
```

```yaml
id: BR-INV-008
title: "Áp dụng chính sách xuất kho và định giá tồn theo công ty, nhóm hàng hoặc loại nghiệp vụ"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần áp dụng chính sách xuất kho và định giá tồn theo công ty, nhóm hàng hoặc loại nghiệp vụ."
success_criteria:
  - "Giá trị xuất và tồn giải thích được theo chính sách có hiệu lực."
depends_on:
  - "BR-INV-001"
  - "BR-ORG-006"
implemented_by:
  - "FR-INV-008"
verified_by:
  - "UAT-INV-008"
```

```yaml
id: BR-INV-009
title: "Kiểm soát tồn âm, điều chỉnh hồi tố và biến động sau khi kỳ đã chốt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần kiểm soát tồn âm, điều chỉnh hồi tố và biến động sau khi kỳ đã chốt."
success_criteria:
  - "Ngoại lệ bị chặn hoặc được phê duyệt, có chứng từ điều chỉnh và lý do."
depends_on:
  - "BR-INV-001"
  - "BR-ORG-005"
  - "BR-WF-001"
implemented_by:
  - "FR-INV-009"
verified_by:
  - "UAT-INV-009"
```

```yaml
id: BR-INV-010
title: "Hỗ trợ mã vạch hoặc QR để nhận diện vật tư, thành phẩm, lô, serial và vị trí khi áp dụng"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần hỗ trợ mã vạch hoặc QR để nhận diện vật tư, thành phẩm, lô, serial và vị trí khi áp dụng."
success_criteria:
  - "Mã quét dẫn đến đúng đối tượng nghiệp vụ và không tạo biến động trùng."
depends_on:
  - "BR-INV-001"
  - "BR-PRD-008"
implemented_by:
  - "FR-INV-010"
verified_by:
  - "UAT-INV-010"
```

```yaml
id: BR-INV-011
title: "Quản lý hạn sử dụng và ưu tiên xuất phù hợp với hàng có hạn"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Kho và tồn kho"
description: "Doanh nghiệp cần quản lý hạn sử dụng và ưu tiên xuất phù hợp với hàng có hạn."
success_criteria:
  - "Hàng gần hết hạn được cảnh báo và xử lý theo chính sách."
depends_on:
  - "BR-INV-001"
  - "BR-PRD-004"
implemented_by:
  - "FR-INV-011"
verified_by:
  - "UAT-INV-011"
```

```yaml
id: BR-MFG-001
title: "Quản lý nhiều phiên bản định mức sản xuất cho Product Master theo mục đích và thời gian hiệu lực"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần quản lý nhiều phiên bản BOM/định mức sản xuất dùng cho Product Master trong vận hành Manufacturing, theo mục đích và thời gian hiệu lực. BOM sản xuất thuộc miền Production và không phụ thuộc vào version/revision của Engineering Product trong PLM."
success_criteria:
  - "Tại một thời điểm chỉ có phiên bản BOM/định mức mặc định phù hợp cho mục đích đã chọn."
  - "Production có thể vận hành với Product Master và BOM/định mức sản xuất mà không yêu cầu phải có Engineering Product trong PLM."
depends_on:
  - "BR-PRD-002"
implemented_by:
  - "FR-MFG-001"
verified_by:
  - "UAT-MFG-001"
```

```yaml
id: BR-MFG-002
title: "Quản lý công đoạn, trung tâm làm việc, lịch làm việc, năng lực và tiêu chuẩn đầu ra"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần quản lý công đoạn, trung tâm làm việc, lịch làm việc, năng lực và tiêu chuẩn đầu ra."
success_criteria:
  - "Sản phẩm và lệnh sản xuất có thể xác định nơi và tiêu chuẩn thực hiện."
depends_on:
  - "BR-ORG-002"
  - "BR-PRD-002"
implemented_by:
  - "FR-MFG-002"
verified_by:
  - "UAT-MFG-002"
```

```yaml
id: BR-MFG-003
title: "Lập nhu cầu vật tư và năng lực từ các nguồn nhu cầu/cung ứng có hiệu lực"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
source_refs:
  - "DEC-MFG-PLANNING-001"
description: "Doanh nghiệp cần lập nhu cầu vật tư và năng lực dựa trên BOM/định mức, năng lực/lịch sản xuất, tồn kho và các nguồn demand/supply có hiệu lực. Sales demand, Purchase Order hoặc nguồn dự báo/manual demand được sử dụng khi capability tương ứng có trong scope; Sales/Purchase không phải Hard Prerequisite để Manufacturing Planning capability tồn tại."
success_criteria:
  - "Kết quả chỉ ra thiếu vật tư, dư cung hoặc quá tải cần quyết định và truy được các nguồn demand/supply đã dùng cho lần tính."
  - "Planning có thể chạy với bộ nguồn đầu vào được phê duyệt mà không yêu cầu tạo Sales/Purchase transaction giả khi các capability đó chưa nằm trong scope."
depends_on:
  - "BR-MFG-001"
  - "BR-MFG-002"
  - "BR-INV-001"
implemented_by:
  - "FR-MFG-003"
verified_by:
  - "UAT-MFG-003"
```

```yaml
id: BR-MFG-004
title: "Quản lý lệnh sản xuất với sản phẩm, số lượng, định mức, công đoạn, ngày dự kiến và người phụ trách"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần quản lý lệnh sản xuất với sản phẩm, số lượng, định mức, công đoạn, ngày dự kiến và người phụ trách."
success_criteria:
  - "Lệnh sản xuất có trạng thái, nguồn nhu cầu và phạm vi nhà máy rõ ràng."
depends_on:
  - "BR-MFG-001"
  - "BR-MFG-002"
implemented_by:
  - "FR-MFG-004"
verified_by:
  - "UAT-MFG-004"
```

```yaml
id: BR-MFG-009
title: "Kiểm soát thay đổi Production BOM, công đoạn hoặc tiêu chuẩn đối với lệnh sản xuất đang mở"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần kiểm soát thay đổi Production BOM/định mức, công đoạn hoặc tiêu chuẩn đối với lệnh sản xuất đang mở. Cơ chế change control này thuộc Production và phải vận hành độc lập với PLM. Khi thay đổi có nguồn từ ECR/ECN/ECO và integration PLM được áp dụng, Production lưu được tham chiếu nguồn thay đổi nhưng không phụ thuộc PLM để thực hiện change control nội bộ."
success_criteria:
  - "Ảnh hưởng về số lượng, chất lượng, giá trị và truy xuất được đánh giá trước khi thay đổi có hiệu lực trên lệnh sản xuất đang mở."
  - "Production change control vận hành được khi không có Engineering Product hoặc PLM."
  - "Khi có nguồn thay đổi từ PLM, có thể lưu tham chiếu tới ECR/ECN/ECO mà không làm PLM trở thành dependency bắt buộc của Production."
depends_on:
  - "BR-MFG-001"
  - "BR-MFG-002"
implemented_by:
  - "FR-MFG-009"
verified_by:
  - "UAT-MFG-009"
```

```yaml
id: BR-MFG-005
title: "Theo dõi vật tư đã cấp, sản lượng đạt, sản phẩm dở dang, phế phẩm, sản phẩm phụ và thời gian lao động"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần theo dõi vật tư đã cấp, sản lượng đạt, sản phẩm dở dang, phế phẩm, sản phẩm phụ và thời gian lao động."
success_criteria:
  - "Số lượng đầu vào và đầu ra được đối chiếu theo lệnh sản xuất."
depends_on:
  - "BR-MFG-004"
  - "BR-INV-002"
implemented_by:
  - "FR-MFG-005"
verified_by:
  - "UAT-MFG-005"
```

```yaml
id: BR-MFG-006
title: "Quản lý tỷ lệ hao hụt, sản lượng đạt, làm lại và phế phẩm theo sản phẩm, công đoạn và nguyên nhân"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần quản lý tỷ lệ hao hụt, sản lượng đạt, làm lại và phế phẩm theo sản phẩm, công đoạn và nguyên nhân."
success_criteria:
  - "Vượt ngưỡng được cảnh báo và có quyết định xử lý."
depends_on:
  - "BR-MFG-005"
implemented_by:
  - "FR-MFG-006"
verified_by:
  - "UAT-MFG-006"
```

```yaml
id: BR-MFG-007
title: "Kiểm tra chất lượng tại các điểm phù hợp trước khi cho phép sử dụng hoặc nhập thành phẩm"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần kiểm tra chất lượng tại các điểm phù hợp trước khi cho phép sử dụng hoặc nhập thành phẩm. Kết quả quality/lab có thể có trạng thái preliminary/confirmed theo policy; chỉ kết quả đã được xác nhận bởi người có thẩm quyền mới được dùng cho quyết định yêu cầu dữ liệu chính thức."
success_criteria:
  - "Kết quả đạt, không đạt, cách ly và quyết định xử lý được lưu cùng đối tượng."
  - "Kết quả quality/lab nhận diện được trạng thái preliminary/confirmed khi áp dụng và chỉ kết quả confirmed theo policy được sử dụng cho quyết định yêu cầu dữ liệu chính thức."
depends_on:
  - "BR-MFG-004"
  - "BR-PRD-004"
implemented_by:
  - "FR-MFG-007"
verified_by:
  - "UAT-MFG-007"
```

```yaml
id: BR-MFG-008
title: "Truy xuất quan hệ giữa nguyên vật liệu, công đoạn, người thực hiện, thiết bị, lô đầu ra và khách hàng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần truy xuất quan hệ giữa nguyên vật liệu, công đoạn, người thực hiện, thiết bị, lô đầu ra và khách hàng."
success_criteria:
  - "Chủ sở hữu chất lượng có thể lập hồ sơ truy xuất cho một lô hoặc serial."
depends_on:
  - "BR-MFG-005"
  - "BR-MFG-007"
  - "BR-INV-007"
implemented_by:
  - "FR-MFG-008"
verified_by:
  - "UAT-MFG-008"
```

```yaml
id: BR-MFG-010
title: "Tính và phân bổ giá thành sản xuất, WIP, chi phí vật tư, nhân công và chi phí chung theo chính sách"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần tính và phân bổ giá thành sản xuất, WIP, chi phí vật tư, nhân công và chi phí chung theo chính sách."
success_criteria:
  - "Giá thành và WIP khớp tổng kiểm soát được Tài chính và Sản xuất xác nhận."
depends_on:
  - "BR-MFG-005"
  - "BR-INV-008"
implemented_by:
  - "FR-MFG-010"
verified_by:
  - "UAT-MFG-010"
```

```yaml
id: BR-MFG-012
title: "Quản lý nhà cung cấp gia công"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần quản lý nhà cung cấp gia công, phạm vi công đoạn thuê ngoài, vật tư cấp cho gia công và trách nhiệm đối với sản phẩm dở dang tại bên nhận gia công."
success_criteria:
  - "Mỗi hoạt động gia công ngoài xác định được nhà cung cấp, sản phẩm, vật tư đã cấp, số lượng, thời hạn và lệnh sản xuất liên quan."
depends_on:
  - "BR-MFG-004"
  - "BR-PUR-001"
  - "BR-INV-001"
implemented_by:
  - "FR-MFG-012"
verified_by:
  - "UAT-MFG-012"
```

```yaml
id: BR-MFG-013
title: "Quản lý việc nhận thành phẩm từ gia công ngoài"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Sản xuất"
description: "Doanh nghiệp cần quản lý việc nhận thành phẩm từ gia công ngoài, chi phí gia công, kiểm tra chất lượng và quyết toán với nhà cung cấp gia công."
success_criteria:
  - "Số lượng nhận, kết quả chất lượng, chi phí và số liệu quyết toán đối chiếu được với vật tư đã cấp và cam kết gia công."
depends_on:
  - "BR-MFG-012"
  - "BR-PUR-004"
  - "BR-MFG-007"
implemented_by:
  - "FR-MFG-013"
verified_by:
  - "UAT-MFG-013"
```

```yaml
id: BR-SAL-001
title: "Tiếp nhận đơn từ website, sàn, điểm bán, điện thoại, nhân viên kinh doanh, đại lý và nhà phân phối"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần tiếp nhận đơn từ website, sàn, điểm bán, điện thoại, nhân viên kinh doanh, đại lý và nhà phân phối."
success_criteria:
  - "Đơn hàng có nguồn, mã tham chiếu, khách hàng hoặc thông tin khách lẻ."
depends_on:
  - "BR-CUS-001"
  - "BR-PRD-002"
implemented_by:
  - "FR-SAL-001"
verified_by:
  - "UAT-SAL-001"
```

```yaml
id: BR-SAL-009
title: "Xác định giá bán theo Company Pricing Policy, Group Pricing Policy và Product Default Selling Price"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
source_refs:
  - "DEC-PRICING-001"
description: "Doanh nghiệp cần xác định giá bán cơ sở bằng cách consume Pricing decision theo precedence thống nhất: Company Pricing Policy/Price List hợp lệ được ưu tiên trước Group Pricing Policy/Price List hợp lệ; nếu không có policy phù hợp thì sử dụng Product Default Selling Price ở Group level. Sales không sở hữu Pricing Policy; Sales áp dụng kết quả Pricing cho giao dịch bán. Price source và transaction phải xác định currency; khi cần conversion theo policy, Sales consume canonical FX rule thuộc Finance/Organization và giữ đủ dữ liệu nguồn để giải thích giá trị quy đổi."
success_criteria:
  - "Nếu có Company Pricing Policy hợp lệ áp dụng cho pricing context/currency, hệ thống dùng Company Pricing Policy và không dùng Group Pricing Policy cho cùng quyết định giá."
  - "Nếu không có Company Pricing Policy phù hợp nhưng có Group Pricing Policy hợp lệ cho pricing context/currency, hệ thống dùng Group Pricing Policy."
  - "Nếu không có Company hoặc Group Pricing Policy phù hợp, hệ thống dùng Product Default Selling Price của Group khi applicable."
  - "Có thể giải thích điều kiện áp dụng và nguồn giá cuối cùng là Company Pricing Policy, Group Pricing Policy hay Product Default Selling Price cụ thể."
  - "Khi currency conversion được áp dụng, giao dịch giữ được original price/value, original currency, FX rate/source/effective time và converted value; conversion không tạo một Pricing Policy cạnh tranh."
depends_on:
  - "BR-PRD-009"
  - "BR-CUS-003"
implemented_by:
  - "FR-SAL-009"
verified_by:
  - "UAT-SAL-009"
```

```yaml
id: BR-SAL-002
title: "Quản lý báo giá, điều kiện bán, thời hạn hiệu lực, giá, chiết khấu, thuế và giao hàng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần quản lý báo giá, điều kiện bán, thời hạn hiệu lực, giá, chiết khấu, thuế và giao hàng. Giá cơ sở của báo giá phải được xác định bằng canonical price-resolution rule tại BR-SAL-009."
success_criteria:
  - "Giá cơ sở của báo giá truy được nguồn theo BR-SAL-009 và điều kiện được giải thích theo chính sách áp dụng tại thời điểm xác nhận."
depends_on:
  - "BR-SAL-001"
  - "BR-SAL-009"
implemented_by:
  - "FR-SAL-002"
verified_by:
  - "UAT-SAL-002"
```

```yaml
id: BR-SAL-010
title: "Quản lý chính sách chiết khấu và chương trình khuyến mại theo quy tắc"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần quản lý chính sách chiết khấu và chương trình khuyến mại theo quy tắc, thời hạn, kênh, khách hàng, sản phẩm, giá trị tối thiểu và hình thức combo. Chiết khấu/khuyến mại được áp dụng trên giá cơ sở đã được xác định theo BR-SAL-009 và không thay thế khái niệm Product Default Selling Price."
success_criteria:
  - "Doanh nghiệp phân biệt được giá cơ sở, nguồn giá cơ sở, giá sau chiết khấu, hàng tặng, combo và khoản giảm trừ theo từng chương trình."
depends_on:
  - "BR-SAL-009"
  - "BR-PRD-006"
implemented_by:
  - "FR-SAL-010"
verified_by:
  - "UAT-SAL-010"
```

```yaml
id: BR-SAL-013
title: "Hỗ trợ coupon như một hình thức mở rộng của chương trình khuyến mại khi có quyết định áp dụng và chủ sở hữu chính sách"
type: business
status: reviewed
priority: COULD
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp có thể hỗ trợ coupon như một hình thức mở rộng của chương trình khuyến mại khi có quyết định áp dụng và chủ sở hữu chính sách."
success_criteria:
  - "Coupon được nhận diện theo chương trình, điều kiện, thời hạn và trạng thái sử dụng; không làm thay đổi giá trị doanh thu ngoài chính sách."
depends_on:
  - "BR-SAL-010"
implemented_by:
  - "FR-SAL-013"
verified_by:
  - "UAT-SAL-013"
```

```yaml
id: BR-SAL-003
title: "Kiểm tra khả năng đáp ứng, số lượng đã giữ, thời gian giao dự kiến và phần chưa đáp ứng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần kiểm tra khả năng đáp ứng, số lượng đã giữ, thời gian giao dự kiến và phần chưa đáp ứng."
success_criteria:
  - "Nhân viên biết rõ số có thể cam kết và phần chờ bổ sung."
depends_on:
  - "BR-SAL-001"
  - "BR-INV-001"
implemented_by:
  - "FR-SAL-003"
verified_by:
  - "UAT-SAL-003"
```

```yaml
id: BR-SAL-004
title: "Kiểm soát hạn mức tín dụng, công nợ quá hạn và các ngoại lệ bán chịu"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần kiểm soát hạn mức tín dụng, công nợ quá hạn và các ngoại lệ bán chịu. Trạng thái công nợ/quá hạn từ Accounts Receivable là input bắt buộc của control này."
success_criteria:
  - "Đơn vượt chính sách không được xác nhận nếu thiếu phê duyệt ngoại lệ."
  - "Quyết định credit control sử dụng được trạng thái công nợ và quá hạn hiện hành từ capability phải thu."
depends_on:
  - "BR-SAL-001"
  - "BR-CUS-002"
  - "BR-FIN-001"
  - "BR-WF-001"
implemented_by:
  - "FR-SAL-004"
verified_by:
  - "UAT-SAL-004"
```

```yaml
id: BR-SAL-005
title: "Quản lý giao từng phần, đơn chờ, hủy, thay thế, trả hàng và hoàn tiền"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần quản lý giao từng phần, đơn chờ, hủy, thay thế, trả hàng và hoàn tiền."
success_criteria:
  - "Số lượng và công nợ sau mỗi ngoại lệ khớp với đơn gốc."
depends_on:
  - "BR-SAL-001"
  - "BR-INV-004"
implemented_by:
  - "FR-SAL-005"
verified_by:
  - "UAT-SAL-005"
```

```yaml
id: BR-SAL-011
title: "Quản lý yêu cầu trả hàng của khách hàng theo hồ sơ RMA"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần quản lý yêu cầu trả hàng của khách hàng theo hồ sơ RMA, giao dịch bán hoặc lý do độc lập, nguyên nhân, số lượng, lô/serial và trạng thái phê duyệt."
success_criteria:
  - "Mỗi yêu cầu trả hàng có căn cứ, người chịu trách nhiệm, quyết định phê duyệt và liên kết tới hàng hoặc giao dịch liên quan."
depends_on:
  - "BR-SAL-001"
  - "BR-SAL-005"
  - "BR-WF-001"
implemented_by:
  - "FR-SAL-011"
verified_by:
  - "UAT-SAL-011"
```

```yaml
id: BR-SAL-012
title: "Quản lý kiểm tra và quyết định xử lý hàng trả"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần quản lý kết quả kiểm tra hàng trả và quyết định xử lý gồm nhập lại, thay thế, sửa chữa, loại bỏ, hoàn tiền hoặc phát hành chứng từ giảm trừ/ghi có."
success_criteria:
  - "Kết quả xử lý phản ánh đúng tình trạng hàng, giá trị ảnh hưởng, công nợ và bằng chứng kiểm tra."
depends_on:
  - "BR-SAL-011"
  - "BR-INV-006"
implemented_by:
  - "FR-SAL-012"
verified_by:
  - "UAT-SAL-012"
```

```yaml
id: BR-SAL-006
title: "Quản lý bảo hành, đổi trả và khiếu nại theo sản phẩm, lô/serial, khách hàng và nguyên nhân"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần quản lý bảo hành, đổi trả và khiếu nại theo sản phẩm, lô/serial, khách hàng và nguyên nhân."
success_criteria:
  - "Có thể truy lại giao dịch bán và quyết định xử lý."
depends_on:
  - "BR-SAL-005"
  - "BR-INV-007"
implemented_by:
  - "FR-SAL-006"
verified_by:
  - "UAT-SAL-006"
```

```yaml
id: BR-SAL-007
title: "Phân biệt doanh thu, quà tặng, hàng khuyến mại, phí giao và các khoản thu hộ"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần phân biệt doanh thu, quà tặng, hàng khuyến mại, phí giao và các khoản thu hộ."
success_criteria:
  - "Báo cáo doanh thu và công nợ không gộp sai bản chất khoản tiền."
depends_on:
  - "BR-SAL-001"
  - "BR-PRD-006"
implemented_by:
  - "FR-SAL-007"
verified_by:
  - "UAT-SAL-007"
```

```yaml
id: BR-SAL-008
title: "Theo dõi chỉ tiêu bán hàng, biên lợi nhuận, đơn đúng hạn, tỷ lệ trả và công nợ theo nhân viên, kênh và khách hàng"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Bán hàng"
description: "Doanh nghiệp cần theo dõi chỉ tiêu bán hàng theo nhân viên, kênh và khách hàng. Các KPI như biên lợi nhuận, tỷ lệ trả và công nợ được áp dụng khi source capability tương ứng nằm trong approved scope; Finance/Cost/Returns và các source liên quan là conditional sources, không phải Hard Prerequisite của Sales KPI capability."
success_criteria:
  - "Mỗi KPI có định nghĩa, công thức và dữ liệu nguồn xác định được."
  - "Bộ KPI được cung cấp theo subset source capability nằm trong approved scope; source chưa được approved không chặn KPI của các source đã sẵn sàng."
depends_on:
  - "BR-SAL-001"
  - "BR-BI-001"
implemented_by:
  - "FR-SAL-008"
verified_by:
  - "UAT-SAL-008"
```

```yaml
id: BR-FIN-001
title: "Quản lý phải thu theo khách hàng, hóa đơn, đơn bán, khoản thu, hạn thanh toán và trạng thái quá hạn"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý phải thu theo khách hàng, hóa đơn, đơn bán, khoản thu, hạn thanh toán và trạng thái quá hạn."
success_criteria:
  - "Số dư khách hàng khớp với các chứng từ nguồn và khoản đã phân bổ."
depends_on:
  - "BR-SAL-001"
  - "BR-CUS-001"
implemented_by:
  - "FR-FIN-001"
verified_by:
  - "UAT-FIN-001"
```

```yaml
id: BR-FIN-002
title: "Quản lý phải trả theo nhà cung cấp, hóa đơn, đơn mua, khoản trả và hạn thanh toán"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý phải trả theo nhà cung cấp, hóa đơn, đơn mua, khoản trả và hạn thanh toán."
success_criteria:
  - "Số dư nhà cung cấp giải thích được theo chứng từ chưa thanh toán."
depends_on:
  - "BR-PUR-004"
  - "BR-PUR-001"
implemented_by:
  - "FR-FIN-002"
verified_by:
  - "UAT-FIN-002"
```

```yaml
id: BR-FIN-003
title: "Quản lý thu, chi, chuyển tiền, tạm ứng, hoàn ứng và chi phí theo phạm vi quản trị"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý thu, chi, chuyển tiền, tạm ứng, hoàn ứng và chi phí theo Company, Organization Context và Department có hiệu lực. Khi capability dự án/đối tượng tập hợp chi phí tại BR-ORG-004 được triển khai, giao dịch tài chính có thể tham chiếu dự án theo phạm vi được áp dụng; BR-ORG-004 không phải hard prerequisite của requirement này."
success_criteria:
  - "Mọi khoản thu/chi xác định được Company, phạm vi quản trị liên quan, mục đích, người chịu trách nhiệm và bằng chứng."
  - "Khi gán Department, Department phải thuộc đúng Company và đang có hiệu lực."
  - "Khi capability dự án được áp dụng, dự án/đối tượng tập hợp chi phí được tham chiếu theo trạng thái và Company sở hữu của đối tượng đó."
depends_on:
  - "BR-ORG-001"
  - "BR-EMP-002"
  - "BR-IAM-003"
implemented_by:
  - "FR-FIN-003"
verified_by:
  - "UAT-FIN-003"
```

```yaml
id: BR-FIN-004
title: "Quản lý quỹ tiền mặt, tài khoản ngân hàng và ví điện tử với số dư theo đồng tiền"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý quỹ tiền mặt, tài khoản ngân hàng và ví điện tử với số dư theo đồng tiền."
success_criteria:
  - "Số dư chỉ bao gồm giao dịch đã được ghi nhận chính thức."
depends_on:
  - "BR-FIN-003"
  - "BR-ORG-005"
implemented_by:
  - "FR-FIN-004"
verified_by:
  - "UAT-FIN-004"
```

```yaml
id: BR-FIN-005
title: "Đối soát sao kê ngân hàng theo một-một, một-nhiều, nhiều-một và đối soát một phần"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần đối soát sao kê ngân hàng theo một-một, một-nhiều, nhiều-một và đối soát một phần."
success_criteria:
  - "Dòng chưa khớp có chủ sở hữu, lý do, tuổi và quyết định xử lý."
depends_on:
  - "BR-FIN-004"
implemented_by:
  - "FR-FIN-005"
verified_by:
  - "UAT-FIN-005"
```

```yaml
id: BR-FIN-006
title: "Quản lý VAT, giao dịch không chịu thuế, thuế đầu vào/đầu ra và thời gian áp dụng chính sách thuế"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý VAT, giao dịch không chịu thuế, thuế đầu vào/đầu ra, tax rounding policy và thời gian áp dụng chính sách thuế. Tax rounding mặc định thực hiện theo từng dòng rồi tổng hợp; nếu controlled legal/regulatory source hoặc tax policy được phê duyệt quy định khác thì áp dụng policy có hiệu lực tương ứng."
success_criteria:
  - "Báo cáo thuế tách đúng loại giao dịch và kỳ."
  - "Tax rounding policy xác định được phương pháp làm tròn, thời gian hiệu lực và nguồn/policy áp dụng; default là rounding từng dòng rồi tổng hợp khi không có policy có hiệu lực quy định khác."
depends_on:
  - "BR-ORG-005"
  - "BR-MDM-001"
implemented_by:
  - "FR-FIN-006"
verified_by:
  - "UAT-FIN-006"
```

```yaml
id: BR-FIN-007
title: "Quản lý nhiều đồng tiền, tỷ giá, ngày hiệu lực, chênh lệch tỷ giá và số tiền gốc"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý nhiều đồng tiền, tỷ giá, ngày hiệu lực, chênh lệch tỷ giá và số tiền gốc."
success_criteria:
  - "Số tiền gốc, tỷ giá áp dụng và giá trị quy đổi đều truy xuất được."
depends_on:
  - "BR-ORG-005"
implemented_by:
  - "FR-FIN-007"
verified_by:
  - "UAT-FIN-007"
```

```yaml
id: BR-FIN-008
title: "Quản lý kỳ tài chính, khóa kỳ, mở lại kỳ có thẩm quyền và điều chỉnh hồi tố"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý kỳ tài chính, khóa kỳ, mở lại kỳ có thẩm quyền và điều chỉnh hồi tố."
success_criteria:
  - "Giao dịch sau khi khóa tuân thủ chính sách điều chỉnh và giữ liên kết kỳ gốc."
depends_on:
  - "BR-ORG-005"
  - "BR-WF-001"
implemented_by:
  - "FR-FIN-008"
verified_by:
  - "UAT-FIN-008"
```

```yaml
id: BR-FIN-010
title: "Xác định nguồn số liệu chính thức cho tài chính vận hành và đối soát"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần xác định ERP là nguồn dữ liệu chính cho tính toán tài chính vận hành trong phạm vi triển khai, đồng thời xác định nguồn số liệu chính thức cho tồn kho, công nợ, thuế, hóa đơn, lương và quyết toán đối tác."
success_criteria:
  - "Mỗi nhóm số liệu xác định được nguồn chính, owning domain, chủ sở hữu đối soát và cách xử lý chênh lệch."
depends_on:
  - "BR-FIN-001"
  - "BR-FIN-002"
  - "BR-INV-001"
implemented_by:
  - "FR-FIN-010"
verified_by:
  - "UAT-FIN-010"
```

```yaml
id: BR-FIN-011
title: "Quản lý chi phí trả trước, phân bổ, tích lũy và chi phí định kỳ khi chính sách yêu cầu"
type: business
status: reviewed
priority: COULD
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý chi phí trả trước, phân bổ, tích lũy và chi phí định kỳ khi chính sách yêu cầu."
success_criteria:
  - "Số tiền phân bổ có kỳ, căn cứ và người xác nhận."
depends_on:
  - "BR-FIN-003"
implemented_by:
  - "FR-FIN-011"
verified_by:
  - "UAT-FIN-011"
```

```yaml
id: BR-FIN-012
title: "Bảo đảm mọi giao dịch tài chính có thể truy về nghiệp vụ nguồn và ngược lại"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần bảo đảm mọi giao dịch tài chính có thể truy về nghiệp vụ nguồn và ngược lại đối với các source domain nằm trong approved scope. Manufacturing, Payroll, Payment và các source domain khác là conditional applicability khi capability tương ứng được approved, không trở thành Hard Prerequisite chỉ để requirement này được phê duyệt."
success_criteria:
  - "Với từng source domain nằm trong approved scope, mẫu giao dịch tương ứng (ví dụ mua, bán, kho, sản xuất, lương hoặc thanh toán khi áp dụng) truy được hai chiều giữa nghiệp vụ nguồn và giao dịch tài chính."
  - "Source domain chưa nằm trong approved scope không chặn traceability đối với các source domain đã sẵn sàng."
depends_on:
  - "BR-FIN-001"
  - "BR-FIN-002"
  - "BR-INV-002"
implemented_by:
  - "FR-FIN-012"
verified_by:
  - "UAT-FIN-012"
```

```yaml
id: BR-FIN-015
title: "Quản lý vòng đời hóa đơn theo chính sách áp dụng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần quản lý vòng đời hóa đơn theo Company/policy áp dụng, gồm phát hành, gửi, chấp nhận, từ chối, hủy, thay thế và điều chỉnh. Requirement này xác định business capability; quy tắc pháp lý cụ thể chỉ trở thành normative khi có controlled legal/regulatory source reference tương ứng."
success_criteria:
  - "Số hóa đơn, trạng thái, lý do và liên kết hóa đơn gốc/thay thế/điều chỉnh được bảo toàn."
  - "Nếu một trạng thái hoặc rule được áp dụng do legal/regulatory baseline, requirement/rule consuming phải resolve được controlled source reference trước approval."
depends_on:
  - "BR-FIN-006"
  - "BR-SAL-001"
  - "BR-PUR-004"
implemented_by:
  - "FR-FIN-015"
verified_by:
  - "UAT-FIN-015"
```

```yaml
id: BR-FIN-016
title: "Đối chiếu số lượng hóa đơn, doanh thu, thuế và trạng thái hóa đơn giữa nguồn phát hành và số liệu tài chính"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
description: "Doanh nghiệp cần đối chiếu số lượng hóa đơn, doanh thu, thuế và trạng thái hóa đơn giữa nguồn phát hành và số liệu tài chính. Các rule pháp lý cụ thể về trạng thái/chấp nhận chỉ áp dụng khi có controlled legal/regulatory source tương ứng."
success_criteria:
  - "Chênh lệch có chủ sở hữu, kỳ, nguyên nhân và quyết định xử lý."
  - "Đối chiếu phân biệt business status với rule pháp lý được kích hoạt từ controlled source khi có."
depends_on:
  - "BR-FIN-015"
  - "BR-FIN-001"
  - "BR-FIN-002"
implemented_by:
  - "FR-FIN-016"
verified_by:
  - "UAT-FIN-016"
```

```yaml
id: BR-FIN-017
title: "Quản lý financial transaction của giao dịch mua bán nội bộ giữa các Company"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Tài chính vận hành và kiểm soát tiền"
source_refs:
  - "DEC-INTERCO-001"
description: "Doanh nghiệp cần quản lý financial consequence của `INTERCOMPANY_SALE` như một cross-company business process. Sales/AR transaction phía bán thuộc Selling Company; Purchase/AP transaction phía mua thuộc Buying Company; inventory outbound/inbound tương ứng thuộc Company của từng phía. Các transaction được correlation bằng cùng `intercompany_reference`; không tồn tại một financial Business Transaction duy nhất do hai Company cùng sở hữu. `INTERCOMPANY_TRANSFER` không tự động phát sinh các nghĩa vụ tài chính này nếu policy áp dụng không yêu cầu."
success_criteria:
  - "Sales/AR phía bán xác định Selling Company owner; Purchase/AP phía mua xác định Buying Company owner."
  - "Doanh thu, chi phí, công nợ, thuế và số liệu quyết toán hai phía được đối chiếu qua cùng `intercompany_reference` nhưng giữ transaction identity/ownership riêng."
  - "Khi phát sinh từ `INTERCOMPANY_SALE`, financial transaction truy được inventory/sales/purchase transaction liên quan mà không tạo bản ghi trùng hoặc dual-Company ownership."
depends_on:
  - "BR-ORG-001"
  - "BR-FIN-001"
  - "BR-FIN-002"
implemented_by:
  - "FR-FIN-017"
verified_by:
  - "UAT-FIN-017"
```

```yaml
id: BR-LOG-001
title: "Quản lý danh mục nhà vận chuyển và dịch vụ theo khu vực, loại hàng, SLA và điều kiện thu hộ"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Giao nhận và vận chuyển"
description: "Doanh nghiệp cần quản lý danh mục nhà vận chuyển và dịch vụ theo khu vực, loại hàng, SLA và điều kiện thu hộ."
success_criteria:
  - "Chỉ đơn vị đang được phê duyệt mới được dùng cho giao mới."
depends_on:
  - "BR-MDM-002"
  - "BR-ORG-001"
implemented_by:
  - "FR-LOG-001"
verified_by:
  - "UAT-LOG-001"
```

```yaml
id: BR-LOG-002
title: "Quản lý thông tin vận đơn, kiện hàng, phí, người nhận, bằng chứng giao và trạng thái giao"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Giao nhận và vận chuyển"
description: "Doanh nghiệp cần quản lý thông tin vận đơn, kiện hàng, phí, người nhận, bằng chứng giao và trạng thái giao."
success_criteria:
  - "Vận đơn liên kết được với đơn bán và số lượng đã xuất."
depends_on:
  - "BR-SAL-001"
  - "BR-INV-002"
  - "BR-LOG-001"
implemented_by:
  - "FR-LOG-002"
verified_by:
  - "UAT-LOG-002"
```

```yaml
id: BR-LOG-003
title: "Theo dõi giao thất bại, hoàn hàng, mất hàng, hư hỏng, sai phí và khiếu nại"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Giao nhận và vận chuyển"
description: "Doanh nghiệp cần theo dõi giao thất bại, hoàn hàng, mất hàng, hư hỏng, sai phí và khiếu nại."
success_criteria:
  - "Mỗi ngoại lệ có chủ sở hữu, lý do, giá trị ảnh hưởng và kết quả xử lý."
depends_on:
  - "BR-LOG-002"
implemented_by:
  - "FR-LOG-003"
verified_by:
  - "UAT-LOG-003"
```

```yaml
id: BR-LOG-004
title: "Đối soát COD, cước, phụ phí và khoản khấu trừ với bảng kê của nhà vận chuyển"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Giao nhận và vận chuyển"
description: "Doanh nghiệp cần đối soát COD, cước, phụ phí và khoản khấu trừ với bảng kê của nhà vận chuyển."
success_criteria:
  - "Số tiền đã nhận, còn thiếu và phí được phân loại rõ."
depends_on:
  - "BR-LOG-002"
  - "BR-FIN-004"
implemented_by:
  - "FR-LOG-004"
verified_by:
  - "UAT-LOG-004"
```

```yaml
id: BR-LOG-005
title: "Theo dõi tỷ lệ giao đúng hạn, giao đủ, giao thất bại và thời gian xử lý hoàn"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Giao nhận và vận chuyển"
description: "Doanh nghiệp cần theo dõi tỷ lệ giao đúng hạn, giao đủ, giao thất bại và thời gian xử lý hoàn."
success_criteria:
  - "Chỉ số có thời điểm bắt đầu, kết thúc và phạm vi đo."
depends_on:
  - "BR-LOG-002"
  - "BR-LOG-003"
implemented_by:
  - "FR-LOG-005"
verified_by:
  - "UAT-LOG-005"
```

```yaml
id: BR-COM-001
title: "Quản lý bản đồ mã sản phẩm, khách hàng, kho và trạng thái giữa ERP và từng kênh bán"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thương mại điện tử và bán tại quầy"
description: "Doanh nghiệp cần quản lý bản đồ mã sản phẩm, khách hàng, kho và trạng thái giữa ERP và từng kênh bán."
success_criteria:
  - "Mã bên ngoài liên kết được với mã chuẩn và không tạo bản ghi trùng."
depends_on:
  - "BR-PRD-002"
  - "BR-CUS-001"
  - "BR-INV-001"
implemented_by:
  - "FR-COM-001"
verified_by:
  - "UAT-COM-001"
```

```yaml
id: BR-COM-002
title: "Tiếp nhận đơn đa kênh, nhận biết đơn trùng và theo dõi trạng thái xử lý"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thương mại điện tử và bán tại quầy"
description: "Doanh nghiệp cần tiếp nhận đơn đa kênh, nhận biết đơn trùng và theo dõi trạng thái xử lý."
success_criteria:
  - "Một đơn bên ngoài chỉ tạo một nghĩa vụ bán tương ứng."
depends_on:
  - "BR-COM-001"
  - "BR-SAL-001"
implemented_by:
  - "FR-COM-002"
verified_by:
  - "UAT-COM-002"
```

```yaml
id: BR-COM-003
title: "Phản ánh tồn có thể bán và trạng thái giao hàng cho các kênh được phê duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thương mại điện tử và bán tại quầy"
description: "Doanh nghiệp cần phản ánh tồn có thể bán và trạng thái giao hàng cho các kênh được phê duyệt."
success_criteria:
  - "Số công bố không vượt chính sách tồn có thể bán."
depends_on:
  - "BR-COM-001"
  - "BR-INV-004"
implemented_by:
  - "FR-COM-003"
verified_by:
  - "UAT-COM-003"
```

```yaml
id: BR-COM-004
title: "Quản lý ca bán hàng, người phụ trách, tiền đầu ca, phát sinh, hoàn tiền và tiền cuối ca"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thương mại điện tử và bán tại quầy"
description: "Doanh nghiệp cần quản lý ca bán hàng, người phụ trách, tiền đầu ca, phát sinh, hoàn tiền và tiền cuối ca."
success_criteria:
  - "Chênh lệch ca có lý do, người xác nhận và trạng thái quyết định."
depends_on:
  - "BR-ORG-002"
  - "BR-IAM-001"
implemented_by:
  - "FR-COM-004"
verified_by:
  - "UAT-COM-004"
```

```yaml
id: BR-COM-005
title: "Đối soát doanh thu, phí sàn, phiếu khuyến mại, thuế, COD, hoàn tiền và khoản truy thu/hoàn trả"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thương mại điện tử và bán tại quầy"
description: "Doanh nghiệp cần đối soát doanh thu, phí sàn, phiếu khuyến mại, thuế, COD, hoàn tiền và khoản truy thu/hoàn trả."
success_criteria:
  - "Bảng kê kênh được phân loại thành khớp, lệch, chờ xử lý hoặc tranh chấp."
depends_on:
  - "BR-COM-002"
  - "BR-LOG-004"
  - "BR-FIN-001"
implemented_by:
  - "FR-COM-005"
verified_by:
  - "UAT-COM-005"
```

```yaml
id: BR-COM-006
title: "Lưu mã tham chiếu và bằng chứng của giao dịch bên ngoài để phục vụ khiếu nại"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thương mại điện tử và bán tại quầy"
description: "Doanh nghiệp cần lưu mã tham chiếu và bằng chứng của giao dịch bên ngoài để phục vụ khiếu nại."
success_criteria:
  - "Có thể truy từ giao dịch ERP tới bảng kê và ngược lại."
depends_on:
  - "BR-COM-002"
  - "BR-INF-001"
implemented_by:
  - "FR-COM-006"
verified_by:
  - "UAT-COM-006"
```

```yaml
id: BR-COM-007
title: "Hỗ trợ bán tại quầy trong điều kiện gián đoạn ngắn theo chính sách được phê duyệt"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Thương mại điện tử và bán tại quầy"
source_refs:
  - "DEC-CONTINUITY-001"
description: "Doanh nghiệp cần hỗ trợ bán tại quầy trong điều kiện gián đoạn ngắn theo chính sách được phê duyệt. Continuity/reconciliation semantics của `NFR-AVL-002` áp dụng cho fallback này; external Data Exchange/Integration không phải Hard Prerequisite."
success_criteria:
  - "Giao dịch phát sinh trong thời gian gián đoạn được ghi nhận, đánh dấu và đối soát sau đó theo controlled fallback/reconciliation của NFR-AVL-002."
  - "Fallback bán tại quầy có thể vận hành và được kiểm thử ngay cả khi external Data Exchange/Integration chưa nằm trong approved scope."
depends_on:
  - "BR-COM-004"
implemented_by:
  - "FR-COM-007"
verified_by:
  - "UAT-COM-007"
```

```yaml
id: BR-HRP-001
title: "Quản lý hồ sơ nhân sự và hợp đồng trên nền tảng Employee/Employee Assignment"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần quản lý hồ sơ nhân sự và hợp đồng bằng cách mở rộng Employee/Employee Assignment do Employee domain sở hữu. HRP quản lý dữ liệu như hợp đồng và các thuộc tính nhân sự/tiền lương có hiệu lực; HRP không tạo Employee, Department, Position hoặc Employee Assignment master song song."
success_criteria:
  - "Lịch sử hợp đồng, điều chuyển và trạng thái làm việc được bảo toàn và truy về Employee/Employee Assignment canonical."
  - "Không tồn tại Employee master riêng trong HRP cạnh tranh với Employee domain."
depends_on:
  - "BR-EMP-001"
  - "BR-EMP-002"
implemented_by:
  - "FR-HRP-001"
verified_by:
  - "UAT-HRP-001"
```

```yaml
id: BR-HRP-002
title: "Quản lý Employee Work Calendar, ngày nghỉ tuần và ngày nghỉ theo chính sách áp dụng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
source_refs:
  - "DEC-ORG-CALENDAR-001"
description: "Doanh nghiệp cần quản lý Employee Work Calendar gồm ca/lịch làm việc chuẩn, số giờ chuẩn mỗi ngày, quy tắc quy đổi giờ sang ngày công, ngày nghỉ tuần và ngày nghỉ theo HR policy áp dụng. Employee Work Calendar có thể consume Company Business Calendar tại BR-ORG-009 làm baseline business day/Company holiday, sau đó bổ sung lịch theo Branch/Plant/employee group/Employee Assignment và HR-specific policy. Nếu một loại ngày nghỉ bắt buộc được xác lập từ legal/regulatory baseline, controlled source reference phải được đăng ký trước approval của rule tương ứng."
success_criteria:
  - "Mỗi ngày trong kỳ chấm công xác định được là ngày làm việc tiêu chuẩn, ngày nghỉ tuần hoặc ngày nghỉ theo Employee Work Calendar/policy có hiệu lực."
  - "Mỗi ngày nghỉ theo lịch có tên, loại, phạm vi áp dụng và thời gian hiệu lực; ngày nghỉ hợp lệ không bị hiển thị như ngày chưa chấm công."
  - "Số giờ và ngày công tiêu chuẩn của kỳ được xác định từ lịch làm việc có hiệu lực của Employee Assignment."
  - "Khi consume Company Business Calendar, HR-specific override/bổ sung không tạo một định nghĩa Company business day cạnh tranh ngoài phạm vi HR."
depends_on:
  - "BR-HRP-001"
  - "BR-ORG-002"
  - "BR-ORG-006"
implemented_by:
  - "FR-HRP-002"
verified_by:
  - "UAT-HRP-002"
```

```yaml
id: BR-HRP-003
title: "Nhân viên tự ghi nhận thời gian làm việc và báo cáo công việc hằng ngày làm cơ sở chấm công"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần cho phép nhân viên tự ghi nhận chấm công theo từng ngày bằng số giờ làm việc và nội dung công việc đã thực hiện. Bản ghi công việc hằng ngày là một căn cứ để tổng hợp ngày công, đồng thời phải giữ được người ghi, ngày làm việc, thời điểm ghi hoặc sửa và lịch sử thay đổi khi có điều chỉnh."
success_criteria:
  - "Nhân viên có thể ghi nhận ngày làm việc, số giờ làm và mô tả công việc đã thực hiện theo chính sách áp dụng."
  - "Hệ thống phân biệt rõ ngày đã có bản ghi chấm công, ngày chưa chấm công, ngày nghỉ theo lịch và ngày nghỉ phép đã được phê duyệt."
  - "Số giờ làm việc được quy đổi sang ngày công theo lịch làm việc và chính sách có hiệu lực của nhân viên."
  - "Việc sửa bản ghi sau thời hạn cho phép hoặc sau khi kỳ đã khóa phải được kiểm soát và giữ lịch sử."
  - "Số giờ do nhân viên tự ghi nhận không tự động được xem là giờ tăng ca được duyệt."
depends_on:
  - "BR-HRP-001"
  - "BR-HRP-002"
implemented_by:
  - "FR-HRP-003"
verified_by:
  - "UAT-HRP-003"
```

```yaml
id: BR-HRP-004
title: "Nhân viên gửi yêu cầu nghỉ phép và quản lý phê duyệt làm căn cứ chấm công"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần cho phép nhân viên tự gửi yêu cầu nghỉ theo ngày hoặc một phần ngày, lựa chọn loại nghỉ, lý do và thông tin cần thiết; người quản lý có thẩm quyền xem xét và phê duyệt hoặc từ chối. Yêu cầu nghỉ đã được phê duyệt phải tự động trở thành căn cứ chấm công, đồng thời hệ thống quản lý số dư phép, nghỉ có lương/không lương và chuyển số dư theo chính sách."
success_criteria:
  - "Mỗi yêu cầu nghỉ xác định được nhân viên, khoảng thời gian, loại nghỉ, lý do, trạng thái, người phê duyệt và thời điểm quyết định."
  - "Yêu cầu nghỉ đã được phê duyệt tự động phản ánh vào bảng chấm công; nhân viên không phải tạo thêm bản ghi chấm công cho cùng khoảng nghỉ."
  - "Chỉ yêu cầu nghỉ có hiệu lực và đã được phê duyệt mới được tính là nghỉ hợp lệ; yêu cầu bị từ chối hoặc hủy không được tính công nghỉ."
  - "Tác động đến số dư phép trước và sau giao dịch có thể truy vết được."
  - "Điều chỉnh hoặc hủy yêu cầu sau khi đã phê duyệt phải giữ lịch sử quyết định."
depends_on:
  - "BR-HRP-001"
  - "BR-HRP-002"
  - "BR-WF-001"
implemented_by:
  - "FR-HRP-004"
verified_by:
  - "UAT-HRP-004"
```

```yaml
id: BR-HRP-005
title: "Quản lý tăng ca theo loại, hệ số, giới hạn và phê duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần quản lý tăng ca theo loại, hệ số, giới hạn và phê duyệt. Số giờ nhân viên tự ghi nhận trong báo cáo công việc hằng ngày chỉ phản ánh thời gian làm việc thực tế và không tự động trở thành giờ tăng ca; giờ tăng ca chỉ có hiệu lực khi đáp ứng chính sách và được phê duyệt."
success_criteria:
  - "Chỉ giờ tăng ca hợp lệ và đã được phê duyệt mới được tính vào bảng chấm công và kỳ lương."
  - "Số giờ báo cáo công việc vượt giờ chuẩn không tự động tạo hoặc làm tăng số giờ tăng ca được duyệt."
  - "Giờ tăng ca có thể truy về ngày làm việc, loại tăng ca, hệ số, người phê duyệt và quyết định áp dụng."
depends_on:
  - "BR-HRP-002"
  - "BR-HRP-003"
  - "BR-WF-001"
implemented_by:
  - "FR-HRP-005"
verified_by:
  - "UAT-HRP-005"
```

```yaml
id: BR-HRP-011
title: "Tổng hợp và khóa bảng chấm công tháng theo nhân viên"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần tổng hợp bảng chấm công tháng theo từng nhân viên từ lịch làm việc, bản ghi thời gian và báo cáo công việc do nhân viên tự ghi nhận, yêu cầu nghỉ đã được phê duyệt và giờ tăng ca đã được phê duyệt. Bảng chấm công phải thể hiện rõ trạng thái của từng ngày và có cơ chế khóa kỳ để làm nguồn chính thức cho Payroll."
success_criteria:
  - "Mỗi ngày trong bảng chấm công thể hiện được trạng thái phù hợp như đã chấm công, chưa chấm công, nghỉ phép có lương, nghỉ phép không lương, ngày nghỉ tuần, ngày nghỉ theo work-calendar/policy hoặc ngoại lệ đã được phê duyệt."
  - "Bảng tổng hợp thể hiện tối thiểu số ngày có đi làm, tổng số giờ làm, số ngày công quy đổi, ngày công tiêu chuẩn, nghỉ có lương, nghỉ không lương, giờ tăng ca đã duyệt, ngày nghỉ theo lịch và số ngày còn thiếu chấm công."
  - "Số ngày có đi làm và số ngày công quy đổi được quản lý như hai chỉ tiêu riêng và có thể giải thích theo dữ liệu nguồn."
  - "Ngày nghỉ hợp lệ theo lịch hoặc yêu cầu nghỉ đã được phê duyệt không bị xem là ngày chưa chấm công."
  - "Chỉ bảng chấm công đã khóa mới được dùng làm nguồn chính thức cho Payroll; thay đổi sau khi khóa phải được kiểm soát và giữ lịch sử."
depends_on:
  - "BR-HRP-002"
  - "BR-HRP-003"
  - "BR-HRP-004"
  - "BR-HRP-005"
implemented_by:
  - "FR-HRP-011"
verified_by:
  - "UAT-HRP-011"
```

```yaml
id: BR-HRP-006
title: "Quản lý thành phần lương, phụ cấp, thưởng, phạt, khấu trừ và ngày hiệu lực"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần quản lý thành phần lương, phụ cấp, thưởng, phạt, khấu trừ và ngày hiệu lực."
success_criteria:
  - "Mỗi thay đổi có phiên bản, chủ sở hữu và lý do."
depends_on:
  - "BR-HRP-001"
implemented_by:
  - "FR-HRP-006"
verified_by:
  - "UAT-HRP-006"
```

```yaml
id: BR-HRP-007
title: "Tính, xem xét, phê duyệt và khóa kỳ lương theo lịch đã công bố"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần tính, xem xét, phê duyệt và khóa kỳ lương theo lịch đã công bố. Payroll phải sử dụng bảng chấm công tháng đã được tổng hợp và khóa làm nguồn chính thức cho ngày công, giờ công, nghỉ có lương, nghỉ không lương, tăng ca đã duyệt, ngày nghỉ lễ/chính sách và các điều chỉnh được phê duyệt; không tự suy diễn trực tiếp từ bản ghi chấm công thô."
success_criteria:
  - "Kỳ lương chỉ được tính từ bảng chấm công tháng đã khóa và các thành phần lương có hiệu lực."
  - "Kỳ lương chỉ được phát hành sau khi tổng lương, khấu trừ, thực nhận và ngoại lệ được xác nhận."
  - "Chỉ payroll period đã khóa mới được xem là số liệu lương chính thức; bản chưa khóa phải được nhận diện là đang xử lý/chưa chính thức."
  - "Có thể truy từ kết quả lương về bảng chấm công tháng và các căn cứ chấm công liên quan."
depends_on:
  - "BR-HRP-006"
  - "BR-HRP-011"
  - "BR-WF-001"
implemented_by:
  - "FR-HRP-007"
verified_by:
  - "UAT-HRP-007"
```

```yaml
id: BR-HRP-008
title: "Quản lý điều chỉnh hồi tố, truy lĩnh, thôi việc, chuyển đơn vị và kỳ lương ngoài lịch"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần quản lý điều chỉnh hồi tố, truy lĩnh, thôi việc, chuyển đơn vị và kỳ lương ngoài lịch."
success_criteria:
  - "Điều chỉnh liên kết kỳ gốc, có người duyệt và không sửa bản ghi công thô."
depends_on:
  - "BR-HRP-007"
implemented_by:
  - "FR-HRP-008"
verified_by:
  - "UAT-HRP-008"
```

```yaml
id: BR-HRP-009
title: "Phân bổ chi phí nhân công theo target được chính sách cho phép"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Nhân sự và tiền lương"
source_refs:
  - "DEC-HRP-ALLOCATION-001"
description: "Doanh nghiệp cần phân bổ chi phí nhân công cho Department, Project hoặc Production Order khi chính sách áp dụng. Các allocation target là conditional độc lập: Project chỉ yêu cầu BR-ORG-004 khi phân bổ theo Project; Production Order chỉ yêu cầu Manufacturing capability khi phân bổ theo Production Order; Department allocation không phụ thuộc Project hoặc Manufacturing."
success_criteria:
  - "Tổng phân bổ khớp tổng chi phí nhân công đã duyệt."
  - "Mỗi allocation line xác định được target type, target reference, giá trị/phương pháp phân bổ và chỉ yêu cầu capability của target đang được sử dụng."
depends_on:
  - "BR-HRP-007"
implemented_by:
  - "FR-HRP-009"
verified_by:
  - "UAT-HRP-009"
```

```yaml
id: BR-HRP-010
title: "Hạn chế truy cập và xuất thông tin lương, định danh và tài khoản cá nhân"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Nhân sự và tiền lương"
description: "Doanh nghiệp cần hạn chế truy cập và xuất thông tin lương, định danh và tài khoản cá nhân."
success_criteria:
  - "Chỉ người có vai trò phù hợp xem được dữ liệu nhạy cảm."
depends_on:
  - "BR-HRP-001"
  - "BR-IAM-007"
implemented_by:
  - "FR-HRP-010"
verified_by:
  - "UAT-HRP-010"
```

```yaml
id: BR-INF-001
title: "Gắn tài liệu, hình ảnh, biên bản, báo giá, hợp đồng, hóa đơn, chứng nhận, ECR/ECN/ECO, BOM, bản vẽ, tiêu chuẩn và bằng chứng giao vào đối tượng nghiệp vụ liên quan"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần gắn tài liệu, hình ảnh, biên bản, báo giá, hợp đồng, hóa đơn, chứng nhận, ECR/ECN/ECO, BOM, bản vẽ, tiêu chuẩn và bằng chứng giao vào đối tượng nghiệp vụ liên quan."
success_criteria:
  - "Người có quyền truy cập đối tượng xem được tài liệu đúng phạm vi và biết tài liệu nào là căn cứ hiện hành."
depends_on:
  - "BR-IAM-002"
  - "BR-ORG-001"
implemented_by:
  - "FR-INF-001"
verified_by:
  - "UAT-INF-001"
```

```yaml
id: BR-INF-002
title: "Quản lý phiên bản, ngày hiệu lực, người sở hữu, trạng thái và thời hạn của tài liệu sản phẩm, tài liệu chất lượng, tài liệu sản xuất và tài liệu thương mại"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần quản lý phiên bản, ngày hiệu lực, người sở hữu, trạng thái và thời hạn của tài liệu sản phẩm, tài liệu chất lượng, tài liệu sản xuất và tài liệu thương mại."
success_criteria:
  - "Phiên bản cũ được giữ để tra cứu, không bị nhầm là phiên bản hiện hành và liên kết được với thay đổi liên quan."
depends_on:
  - "BR-INF-001"
implemented_by:
  - "FR-INF-002"
verified_by:
  - "UAT-INF-002"
```

```yaml
id: BR-INF-003
title: "Phân loại tài liệu theo mức độ công khai, nội bộ, mật hoặc hạn chế"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần phân loại tài liệu theo mức độ công khai, nội bộ, mật hoặc hạn chế."
success_criteria:
  - "Việc xem, chia sẻ và xuất tài liệu tuân theo phân loại."
depends_on:
  - "BR-INF-001"
  - "BR-IAM-007"
implemented_by:
  - "FR-INF-003"
verified_by:
  - "UAT-INF-003"
```

```yaml
id: BR-INF-008
title: "Kiểm soát việc chia sẻ, in hoặc xuất tài liệu hạn chế bằng phân loại, dấu nhận biết, người nhận và thời hạn sử dụng"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần kiểm soát việc chia sẻ, in hoặc xuất tài liệu hạn chế bằng phân loại, dấu nhận biết, người nhận và thời hạn sử dụng."
success_criteria:
  - "Có thể xác định tài liệu đã được cấp cho ai, mục đích nào và đã thu hồi hay chưa."
depends_on:
  - "BR-INF-003"
  - "BR-IAM-007"
implemented_by:
  - "FR-INF-008"
verified_by:
  - "UAT-INF-008"
```

```yaml
id: BR-INF-004
title: "Giữ tài liệu thuộc retention hold hoặc nghĩa vụ lưu giữ được phê duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần giữ tài liệu thuộc retention hold, tranh chấp, kiểm toán, thuế, lương, chất lượng hoặc nghĩa vụ lưu giữ được phê duyệt trong thời hạn áp dụng. Legal/regulatory minimum cụ thể chỉ normative khi requirement/policy liên quan có controlled source reference tương ứng."
success_criteria:
  - "Tài liệu đang thuộc retention hold không bị xóa hoặc thay thế trái policy; hold xác định được căn cứ, phạm vi, người có thẩm quyền và thời điểm release khi áp dụng."
depends_on:
  - "BR-INF-002"
implemented_by:
  - "FR-INF-004"
verified_by:
  - "UAT-INF-004"
```

```yaml
id: NFR-CMP-001
title: "Hồ sơ thuế, hóa đơn, thanh toán, lương, hợp đồng, chất lượng và giao nhận phải được lưu theo nghĩa vụ pháp lý hoặc chính sách nội bộ dài hơn"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Tuân thủ và lưu giữ hồ sơ"
source_refs:
  - "REG-VN-ACC-RET-001"
  - "DEC-NFR-BASELINE-001"
description: "Hồ sơ thuế, hóa đơn, thanh toán, lương, hợp đồng, chất lượng và giao nhận phải tuân thủ retention schedule theo phân loại hồ sơ. Baseline kế toán Việt Nam được xác định theo Luật Kế toán hiện hành (Văn bản hợp nhất 41/VBHN-VPQH năm 2026, Điều 41) và Nghị định 174/2016/NĐ-CP về lưu trữ tài liệu kế toán: tối thiểu 5 năm cho tài liệu kế toán dùng cho quản lý/điều hành hoặc chứng từ không sử dụng trực tiếp để ghi sổ và lập báo cáo tài chính; tối thiểu 10 năm cho chứng từ sử dụng trực tiếp để ghi sổ/lập báo cáo tài chính, sổ kế toán và báo cáo tài chính; tài liệu kế toán có tính sử liệu hoặc ý nghĩa quan trọng theo quy định áp dụng được lưu vĩnh viễn. Nếu pháp luật chuyên ngành hoặc internal policy yêu cầu thời hạn dài hơn thì áp dụng thời hạn dài hơn. Legal hold luôn có precedence đối với tiêu hủy."
success_criteria:
  - "Mỗi record class có retention category, retention period, mốc bắt đầu tính, chủ sở hữu và điều kiện hủy được cấu hình/phê duyệt trong retention schedule."
  - "Đối với hồ sơ thuộc phạm vi kế toán Việt Nam, retention schedule không thấp hơn baseline 5 năm, 10 năm hoặc vĩnh viễn theo phân loại pháp lý tương ứng."
  - "Hồ sơ quality, giao nhận, hợp đồng hoặc hồ sơ khác không tự động được gán cùng một mốc; hệ thống áp dụng max(applicable legal minimum, approved internal retention policy) theo record class."
  - "Hồ sơ đang legal hold, tranh chấp, kiểm toán hoặc điều tra không được hủy cho đến khi hold được giải phóng theo thẩm quyền."
  - "Mỗi legal hold xác định được thời điểm bắt đầu, căn cứ/người có thẩm quyền, phạm vi hồ sơ và bằng chứng giải phóng hold khi kết thúc."
  - "Việc thay đổi retention policy có version, ngày hiệu lực, căn cứ và không làm rút ngắn trái phép thời hạn của hồ sơ đã phát sinh."
depends_on:
  - "BR-INF-002"
  - "BR-INF-004"
implemented_by:
  - "NFS-CMP-001"
verified_by:
  - "NFR-TEST-CMP-001"
```


```yaml
id: NFR-CMP-003
title: "Có khả năng cung cấp bằng chứng phù hợp cho kiểm toán nội bộ, kiểm toán độc lập và cơ quan có thẩm quyền"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Tuân thủ và lưu giữ hồ sơ"
description: "Doanh nghiệp phải có khả năng cung cấp bằng chứng phù hợp cho kiểm toán nội bộ, kiểm toán độc lập và cơ quan có thẩm quyền."
success_criteria:
  - "Bộ hồ sơ mẫu được xác nhận về tính đầy đủ và phạm vi."
depends_on:
  - "NFR-AUD-001"
  - "NFR-CMP-001"
implemented_by:
  - "NFS-CMP-003"
verified_by:
  - "NFR-TEST-CMP-003"
```

```yaml
id: BR-INF-009
title: "Quản lý vòng đời tài liệu và chứng từ từ Nháp đến Hết hiệu lực/Không còn áp dụng"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần quản lý vòng đời tài liệu và chứng từ từ Nháp, Xem xét, Phê duyệt, Phát hành, Sửa đổi, Lưu trữ đến Hết hiệu lực/Không còn áp dụng; mỗi phiên bản phải giữ được quan hệ truy xuất với phiên bản trước và tài liệu liên quan. Bước business approval thuộc vòng đời này sử dụng Shared Workflow khi áp dụng."
success_criteria:
  - "Có thể xác định phiên bản hiện hành, lịch sử sửa đổi, người chịu trách nhiệm, thời điểm chuyển trạng thái và lý do tài liệu hết hiệu lực."
  - "Tài liệu thuộc diện phải phê duyệt chỉ được phát hành khi workflow tương ứng cho phép."
depends_on:
  - "BR-INF-001"
  - "BR-INF-002"
  - "BR-WF-001"
implemented_by:
  - "FR-INF-009"
verified_by:
  - "UAT-INF-009"
```


```yaml
id: BR-INF-005
title: "Trao đổi và ghi nhận quyết định liên quan đến chứng từ"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần trao đổi và ghi nhận quyết định liên quan đến chứng từ, sản phẩm, khách hàng, nhà cung cấp và lệnh sản xuất."
success_criteria:
  - "Trao đổi có người tham gia, thời điểm, trạng thái giải quyết và liên kết đối tượng."
depends_on:
  - "BR-INF-001"
  - "BR-IAM-002"
implemented_by:
  - "FR-INF-005"
verified_by:
  - "UAT-INF-005"
```

```yaml
id: BR-INF-006
title: "Gắn thẻ và tra cứu thống nhất theo mã, tên, tham chiếu, đối tác, lô, serial và chứng từ"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần gắn thẻ và tra cứu thống nhất theo mã, tên, tham chiếu, đối tác, lô, serial và chứng từ."
success_criteria:
  - "Kết quả tra cứu không vượt phạm vi quyền và có thể mở về bản ghi nguồn."
depends_on:
  - "BR-INF-001"
implemented_by:
  - "FR-INF-006"
verified_by:
  - "UAT-INF-006"
```

```yaml
id: BR-INF-007
title: "Nhận và quản lý thông báo nghiệp vụ cần xử lý"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Thông tin, tài liệu và chứng từ nghiệp vụ"
description: "Doanh nghiệp cần nhận thông báo nghiệp vụ cần xử lý từ Shared Workflow, ngoại lệ, tồn thấp, công nợ quá hạn, giao thất bại và kỳ cần khóa. Khả năng trao đổi/thảo luận theo BR-INF-005 là capability bổ sung, không phải hard prerequisite của thông báo."
success_criteria:
  - "Thông báo có người nhận, loại nguồn, mức ưu tiên, thời điểm và trạng thái đã xử lý; thông báo approval liên kết được với workflow tương ứng."
depends_on:
  - "BR-IAM-003"
  - "BR-WF-001"
implemented_by:
  - "FR-INF-007"
verified_by:
  - "UAT-INF-007"
```

```yaml
id: BR-DQ-001
title: "Trao đổi dữ liệu giữa ERP và các hệ thống/nguồn bên ngoài được phê duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Chất lượng và trao đổi dữ liệu"
description: "Doanh nghiệp cần tiếp nhận và cung cấp dữ liệu giữa ERP với website, sàn, vận chuyển, ngân hàng, ví điện tử, máy chấm công và hệ thống kế toán được phê duyệt."
success_criteria:
  - "Mỗi nguồn có chủ sở hữu, phạm vi, tần suất và cách đối soát."
depends_on:
  - "BR-MDM-001"
  - "BR-MDM-002"
implemented_by:
  - "FR-DQ-001"
verified_by:
  - "UAT-DQ-001"
```

```yaml
id: BR-DQ-002
title: "Nhận biết bản ghi đã tiếp nhận, khớp, trùng, thiếu, bị từ chối hoặc chờ xử lý"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Chất lượng và trao đổi dữ liệu"
description: "Doanh nghiệp cần nhận biết bản ghi đã tiếp nhận, khớp, trùng, thiếu, mâu thuẫn, bị từ chối hoặc chờ xử lý trước khi dữ liệu không hợp lệ trở thành số liệu chính thức."
success_criteria:
  - "Không có bản ghi thất bại bị bỏ quên mà không có chủ sở hữu."
  - "Bản ghi không hợp lệ, trùng, thiếu hoặc mâu thuẫn được đánh dấu nguyên nhân và trạng thái xử lý trước khi được chấp nhận thành dữ liệu chính thức."
depends_on:
  - "BR-DQ-001"
implemented_by:
  - "FR-DQ-002"
verified_by:
  - "UAT-DQ-002"
```

```yaml
id: BR-DQ-003
title: "Bảo toàn mã tham chiếu bên ngoài, thời điểm, trạng thái và bằng chứng trao đổi để phục vụ đối soát"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Chất lượng và trao đổi dữ liệu"
description: "Doanh nghiệp cần bảo toàn mã tham chiếu bên ngoài, thời điểm, trạng thái và bằng chứng trao đổi để phục vụ đối soát."
success_criteria:
  - "Có thể đi từ bản ghi ERP đến bằng chứng nguồn và ngược lại."
depends_on:
  - "BR-DQ-001"
implemented_by:
  - "FR-DQ-003"
verified_by:
  - "UAT-DQ-003"
```

```yaml
id: BR-DQ-004
title: "Hỗ trợ nhập dữ liệu hàng loạt từ nguồn đã phê duyệt với kiểm soát phạm vi, chủ sở hữu, lỗi và kết quả"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Chất lượng và trao đổi dữ liệu"
description: "Doanh nghiệp cần hỗ trợ nhập dữ liệu hàng loạt từ nguồn đã phê duyệt với kiểm soát phạm vi, chủ sở hữu, lỗi và kết quả."
success_criteria:
  - "Bản ghi hợp lệ và không hợp lệ được tách rõ; số lượng tổng được xác nhận."
depends_on:
  - "BR-DQ-001"
implemented_by:
  - "FR-DQ-004"
verified_by:
  - "UAT-DQ-004"
```

```yaml
id: BR-DQ-005
title: "Xuất dữ liệu cho vận hành, đối soát, báo cáo hoặc yêu cầu compliance được phê duyệt"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Chất lượng và trao đổi dữ liệu"
description: "Doanh nghiệp cần xuất dữ liệu cho vận hành, đối soát, báo cáo hoặc yêu cầu compliance được phê duyệt theo quyền và phân loại dữ liệu. Legal/regulatory export requirement cụ thể chỉ áp dụng khi có controlled source reference tương ứng."
success_criteria:
  - "Dữ liệu xuất không chứa trường ngoài mục đích hoặc phạm vi người nhận."
  - "Nếu export được yêu cầu bởi legal/regulatory baseline, có thể truy được controlled source/policy quyết định phạm vi dữ liệu xuất."
depends_on:
  - "BR-DQ-001"
  - "BR-IAM-002"
implemented_by:
  - "FR-DQ-005"
verified_by:
  - "UAT-DQ-005"
```


```yaml
id: BR-DQ-006
title: "Xử lý sai lệch dữ liệu bằng điều chỉnh có liên kết, không thay thế âm thầm số liệu đã ghi nhận"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Chất lượng và trao đổi dữ liệu"
description: "Doanh nghiệp cần xử lý sai lệch dữ liệu bằng điều chỉnh có liên kết, không thay thế âm thầm số liệu đã ghi nhận."
success_criteria:
  - "Mọi điều chỉnh có lý do, chủ sở hữu, phê duyệt và tác động được xác định."
depends_on:
  - "BR-DQ-002"
  - "BR-DQ-003"
  - "BR-WF-001"
implemented_by:
  - "FR-DQ-006"
verified_by:
  - "UAT-DQ-006"
```

```yaml
id: BR-BI-001
title: "Cung cấp Reporting dùng chung cho các domain thuộc approved scope của phase"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Báo cáo và chỉ số quản trị"
source_refs:
  - "DEC-REPORTING-001"
description: "Doanh nghiệp cần một shared Reporting capability để cung cấp báo cáo cho các domain nằm trong approved scope của phase tương ứng. Các reporting domain dự kiến gồm doanh thu, lợi nhuận, công nợ, tồn kho, mua hàng, bán hàng, sản xuất, chất lượng, thuế, nhân sự và tiền lương; một domain chưa nằm trong approved scope không ngăn Reporting được triển khai cho domain đã sẵn sàng. Reporting phải vận hành được từ dữ liệu ERP nội bộ; external integration chỉ là conditional source khi capability tương ứng có hiệu lực."
success_criteria:
  - "Mỗi domain nằm trong approved scope có thể định nghĩa report tương ứng với chủ sở hữu, định nghĩa, kỳ, phạm vi và nguồn dữ liệu."
  - "Report chỉ bắt buộc khi source domain/capability của report nằm trong approved scope; không yêu cầu tạo dữ liệu/domain giả chỉ để thỏa BR-BI-001."
  - "Report mới có thể được bổ sung ở phase sau mà không thay đổi canonical reporting framework."
  - "Reporting không hard-depend một external integration nếu các nguồn ERP nội bộ cần thiết cho report đã tồn tại."
depends_on:
  - "BR-IAM-002"
implemented_by:
  - "FR-BI-001"
verified_by:
  - "UAT-BI-001"
```

```yaml
id: BR-BI-002
title: "Xem chỉ số theo thời gian, công ty, chi nhánh, kho, nhà máy, sản phẩm, nhóm hàng, khách hàng, nhà cung cấp, kênh và nhân viên"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Báo cáo và chỉ số quản trị"
description: "Doanh nghiệp cần xem chỉ số theo thời gian, công ty, chi nhánh, kho, nhà máy, sản phẩm, nhóm hàng, khách hàng, nhà cung cấp, kênh và nhân viên."
success_criteria:
  - "Bộ lọc không làm thay đổi định nghĩa chỉ số và tổng chi tiết khớp tổng."
depends_on:
  - "BR-BI-001"
  - "BR-ORG-002"
implemented_by:
  - "FR-BI-002"
verified_by:
  - "UAT-BI-002"
```

```yaml
id: BR-BI-003
title: "Biết thời điểm dữ liệu, kỳ cắt, múi giờ, trạng thái hiện tại hay sơ bộ và thời hạn cập nhật của từng báo cáo"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Báo cáo và chỉ số quản trị"
description: "Doanh nghiệp cần biết thời điểm dữ liệu, kỳ cắt, múi giờ, trạng thái hiện tại hay sơ bộ và thời hạn cập nhật của từng báo cáo."
success_criteria:
  - "Báo cáo quá hạn cập nhật được đánh dấu rõ và không bị hiểu là số liệu hiện tại."
depends_on:
  - "BR-BI-001"
implemented_by:
  - "FR-BI-003"
verified_by:
  - "UAT-BI-003"
```

```yaml
id: BR-BI-004
title: "Truy từ chỉ số tổng hợp về các giao dịch nguồn trong phạm vi quyền"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Báo cáo và chỉ số quản trị"
description: "Doanh nghiệp cần truy từ chỉ số tổng hợp về các giao dịch nguồn trong phạm vi quyền. Khả năng search/tag thống nhất tại BR-INF-006 có thể hỗ trợ trải nghiệm tra cứu nhưng không phải hard prerequisite của drill-through."
success_criteria:
  - "Mẫu KPI có thể giải thích đến chứng từ hoặc dữ liệu nguồn."
depends_on:
  - "BR-BI-001"
implemented_by:
  - "FR-BI-004"
verified_by:
  - "UAT-BI-004"
```


```yaml
id: BR-BI-005
title: "Quản lý phiên bản định nghĩa KPI, công thức kinh doanh, chủ sở hữu và ngày hiệu lực"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Báo cáo và chỉ số quản trị"
description: "Doanh nghiệp cần quản lý phiên bản định nghĩa KPI, công thức kinh doanh, chủ sở hữu và ngày hiệu lực."
success_criteria:
  - "Thay đổi KPI tạo phiên bản mới và giữ được kết quả đã công bố trước đó."
depends_on:
  - "BR-BI-001"
implemented_by:
  - "FR-BI-005"
verified_by:
  - "UAT-BI-005"
```


```yaml
id: BR-BI-006
title: "Theo dõi các KPI trọng yếu như doanh thu"
type: business
status: reviewed
priority: SHOULD
target_phase: null
source: "Báo cáo và chỉ số quản trị"
description: "Doanh nghiệp cần theo dõi các KPI trọng yếu như doanh thu, biên lợi nhuận, độ chính xác tồn, OTIF, tỷ lệ lỗi, vòng quay tồn, DSO, DPO, thời gian đóng kỳ và tỷ lệ áp dụng ERP. OEE là KPI mở rộng trong tương lai và chỉ được sử dụng khi có nguồn dữ liệu MES phù hợp; ERP Core không có trách nhiệm tự tính OEE trước khi MES được triển khai."
success_criteria:
  - "Mỗi KPI có ngưỡng, mục tiêu, đơn vị, kỳ và chủ sở hữu."
  - "Khi OEE được sử dụng, báo cáo phải xác định rõ nguồn dữ liệu MES và không diễn giải OEE như KPI do ERP Core tự tính."
depends_on:
  - "BR-BI-005"
implemented_by:
  - "FR-BI-006"
verified_by:
  - "UAT-BI-006"
```

```yaml
id: BR-BI-007
title: "Xuất báo cáo theo định dạng được phê duyệt và kiểm soát người nhận, mục đích sử dụng và dữ liệu nhạy cảm"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Báo cáo và chỉ số quản trị"
description: "Doanh nghiệp cần xuất báo cáo theo định dạng được phê duyệt và kiểm soát người nhận, mục đích sử dụng và dữ liệu nhạy cảm."
success_criteria:
  - "Bản xuất có thời điểm, phạm vi, người xuất và mức phân loại."
depends_on:
  - "BR-BI-001"
  - "BR-IAM-007"
implemented_by:
  - "FR-BI-007"
verified_by:
  - "UAT-BI-007"
```

```yaml
id: BR-BI-008
title: "Chụp và giữ báo cáo đã ký hoặc đã dùng cho quyết định, kiểm toán và đối soát"
type: business
status: reviewed
priority: MUST
target_phase: null
source: "Báo cáo và chỉ số quản trị"
description: "Doanh nghiệp cần chụp và giữ báo cáo đã ký hoặc đã dùng cho quyết định, kiểm toán và đối soát để bảo toàn đúng ngữ cảnh dữ liệu tại thời điểm sử dụng."
success_criteria:
  - "Có thể chứng minh báo cáo nào đã được dùng tại thời điểm quyết định và xác định được thời điểm, phạm vi dữ liệu, người công bố/người ký khi áp dụng."
depends_on:
  - "BR-BI-001"
  - "BR-INF-002"
implemented_by:
  - "FR-BI-008"
verified_by:
  - "UAT-BI-008"
```

```yaml
id: NFR-AVL-001
title: "Sẵn sàng trong khung giờ vận hành đã công bố"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Tính sẵn sàng và liên tục vận hành"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Các năng lực nghiệp vụ trọng yếu phải đạt availability tối thiểu 99.5% trong khung giờ vận hành đã công bố, đo theo tháng. Planned maintenance chỉ được loại khỏi downtime khi đã được phê duyệt và thông báo trước theo policy vận hành."
success_criteria:
  - "Monthly availability trong khung giờ vận hành đã công bố đạt tối thiểu 99.5%."
  - "Measurement window, thời điểm bắt đầu/kết thúc downtime và các khoảng planned maintenance được ghi nhận đủ để tái tính availability."
  - "Planned maintenance chỉ được loại khỏi phép đo khi đáp ứng điều kiện phê duyệt và thông báo trước theo policy; downtime ngoài điều kiện này được tính vào availability."
depends_on: []
implemented_by:
  - "NFS-AVL-001"
verified_by:
  - "NFR-TEST-AVL-001"
```


```yaml
id: NFR-AVL-002
title: "Có phương án tiếp tục nghiệp vụ tối thiểu khi hệ thống tạm thời không sử dụng được"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Tính sẵn sàng và liên tục vận hành"
source_refs:
  - "DEC-CONTINUITY-001"
description: "Khi ERP tạm thời không sử dụng được, doanh nghiệp phải có controlled fallback để tiếp tục các nghiệp vụ không thể dừng gồm nhận hàng, xuất/giao hàng, ghi nhận sản xuất và kiểm tra chất lượng khi capability tương ứng đang vận hành. Thay đổi master data hoặc policy nhạy cảm không được thực hiện trong chế độ tạm. Sau phục hồi, dữ liệu fallback phải được reconciliation để chứng minh không mất hoặc ghi nhận trùng nghiệp vụ. Cơ chế continuity/reconciliation này không phụ thuộc việc external Data Exchange/Integration đã được triển khai hay chưa."
success_criteria:
  - "Mỗi nghiệp vụ fallback có biểu mẫu/bằng chứng tạm, người chịu trách nhiệm, thời điểm phát sinh và người phê duyệt hoặc xác nhận theo policy."
  - "Sau khi ERP phục hồi, dữ liệu tạm được nhập bù hoặc đồng bộ lại, đánh dấu nguồn phát sinh trong thời gian gián đoạn, đối soát và xác nhận để không tạo giao dịch trùng hoặc mất nghiệp vụ."
  - "Control totals và transaction reconciliation trước/sau khôi phục khớp; mọi chênh lệch phải có nguyên nhân, người chịu trách nhiệm và disposition được phê duyệt."
  - "Master data hoặc policy nhạy cảm không được thay đổi bằng quy trình fallback; trường hợp ngoại lệ chỉ được xử lý bằng cơ chế được phê duyệt riêng và có audit trail."
  - "Fallback và reconciliation có thể được kiểm thử ngay cả khi không có external Data Exchange/Integration trong scope."
depends_on:
  - "NFR-AVL-001"
implemented_by:
  - "NFS-AVL-002"
verified_by:
  - "NFR-TEST-AVL-002"
```

```yaml
id: NFR-AVL-004
title: "Mức mất dữ liệu tối đa có thể chấp nhận và thời gian khôi phục cho từng nhóm nghiệp vụ phải được Ban điều hành phê duyệt"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Tính sẵn sàng và liên tục vận hành"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Business capability được phân loại recovery tier. Tier 1 gồm giao dịch vận hành trọng yếu với RPO không quá 15 phút và RTO không quá 4 giờ. Tier 2 gồm chức năng quản trị và báo cáo thông thường với RPO không quá 4 giờ và RTO không quá 8 giờ. Capability ngoài hai nhóm này phải có recovery target được phê duyệt trong recovery plan trước go-live."
success_criteria:
  - "Mọi capability trọng yếu được gán recovery tier hoặc recovery target được phê duyệt và có chủ sở hữu xác nhận."
  - "Kết quả kiểm thử khôi phục Tier 1 đạt RPO <= 15 phút và RTO <= 4 giờ."
  - "Kết quả kiểm thử khôi phục Tier 2 đạt RPO <= 4 giờ và RTO <= 8 giờ."
  - "Recovery plan lưu phạm vi, dữ liệu, dependency, người chịu trách nhiệm và kết quả test để chứng minh target đạt được."
depends_on:
  - "NFR-AVL-001"
implemented_by:
  - "NFS-AVL-004"
verified_by:
  - "NFR-TEST-AVL-004"
```

```yaml
id: NFR-PER-001
title: "Tra cứu giao dịch vận hành thông thường phải phản hồi trong thời gian phù hợp với nhịp công việc"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Hiệu năng và khả năng mở rộng"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Hiệu năng acceptance được đánh giá theo p95 dưới workload đại diện 100 concurrent users. Operation thông thường được phân thành tra cứu/điều hướng, tạo/cập nhật giao dịch và truy vấn nghiệp vụ phức tạp; tác vụ dài có thể chạy bất đồng bộ khi có trạng thái tiến trình rõ ràng."
success_criteria:
  - "Tra cứu và điều hướng thông thường đạt p95 response time <= 2 giây dưới workload acceptance."
  - "Tạo hoặc cập nhật giao dịch thông thường đạt p95 response time <= 3 giây dưới workload acceptance."
  - "Truy vấn nghiệp vụ phức tạp đạt p95 response time <= 5 giây dưới workload acceptance."
  - "Performance test sử dụng 100 concurrent users với workload mix và dữ liệu đại diện được ghi lại để có thể tái kiểm thử."
  - "Tác vụ dự kiến vượt response-time class tương tác có thể chạy async nếu người dùng nhận được trạng thái tiến trình, kết quả và lỗi rõ ràng."
depends_on:
  - "BR-ORG-001"
implemented_by:
  - "NFS-PER-001"
verified_by:
  - "NFR-TEST-PER-001"
```


```yaml
id: NFR-PER-002
title: "Báo cáo vận hành gần thời gian thực phải đáp ứng tuổi dữ liệu theo SLA đã công bố"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Hiệu năng và khả năng mở rộng"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Requirement này quản lý report/dashboard publication freshness, tách biệt với source/domain freshness tại NFR-FRESH-*. Mọi báo cáo hoặc dashboard được phân loại near-real-time phải có data age không quá 15 phút so với dữ liệu nguồn thuộc phạm vi công bố; source có SLA chặt hơn vẫn phải tuân thủ NFR-FRESH tương ứng."
success_criteria:
  - "Near-real-time report/dashboard có data age <= 15 phút trong điều kiện vận hành bình thường."
  - "Báo cáo hiển thị data timestamp/cutoff và cảnh báo rõ khi data age vượt 15 phút."
  - "Report không được phân loại near-real-time nếu refresh/cutoff được quản lý theo publication period khác."
  - "NFR-PER-002 không thay thế source/domain freshness target tại NFR-FRESH-*; test evidence phải phân biệt source timestamp và report/dashboard publication timestamp."
depends_on:
  - "BR-BI-003"
implemented_by:
  - "NFS-PER-002"
verified_by:
  - "NFR-TEST-PER-002"
```

```yaml
id: NFR-PER-003
title: "Việc tăng số công ty, chi nhánh, sản phẩm, giao dịch và người dùng không làm mất khả năng kiểm soát hoặc đối soát"
type: non-functional
status: reviewed
priority: SHOULD
target_phase: null
source: "Hiệu năng và khả năng mở rộng"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Capacity assessment sử dụng planning horizon 3 năm với baseline tối thiểu 10 Company, 100 concurrent users, 100000 Product và 1000000 business transactions mỗi năm. Việc tăng đến baseline này không được làm mất khả năng kiểm soát, đối soát hoặc vi phạm performance target bắt buộc."
success_criteria:
  - "Có capacity assessment cho planning horizon 3 năm trên baseline 10 Company, 100 concurrent users, 100000 Product và 1000000 business transactions/năm."
  - "Tại baseline capacity, các kiểm soát dữ liệu, reconciliation và isolation vẫn hoạt động đúng."
  - "Tại workload acceptance liên quan, performance bắt buộc tiếp tục được đánh giá theo NFR-PER-001; bottleneck và headroom được ghi nhận trong NFS/capacity report."
depends_on:
  - "BR-ORG-001"
  - "NFR-PER-001"
implemented_by:
  - "NFS-PER-003"
verified_by:
  - "NFR-TEST-PER-003"
```

```yaml
id: NFR-DAT-001
title: "Số lượng, tiền, thuế, công nợ, giá vốn và lương phải bảo toàn độ chính xác theo đơn vị và chính sách làm tròn được công bố"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Toàn vẹn và chất lượng dữ liệu"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Số lượng, tiền, thuế, công nợ, giá vốn và lương phải bảo toàn độ chính xác theo data type, currency minor unit và policy làm tròn có hiệu lực. Quantity hỗ trợ tối thiểu 6 chữ số thập phân khi nghiệp vụ yêu cầu; unit price/rate phải giữ đủ precision nội bộ để không tích lũy sai số trước khi posting. Monetary posting được làm tròn theo minor unit của currency. Engine phải tuân thủ effective rounding policy của owning business domain; NFR này không sở hữu business tax-rounding rule."
success_criteria:
  - "Quantity hỗ trợ tối thiểu 6 decimal places khi product/UOM policy yêu cầu và không làm mất giá trị nguồn trước bước rounding được công bố."
  - "Unit price, exchange rate và rate khác giữ đủ internal precision để kết quả posting sau cùng đáp ứng currency/financial rounding policy."
  - "Monetary posting được làm tròn theo minor unit của currency áp dụng; số tiền gốc và giá trị trước/sau rounding có thể giải thích khi cần đối soát."
  - "Khi tax capability nằm trong approved scope, tax calculation tuân thủ effective tax-rounding policy do Finance quản lý tại BR-FIN-006 và có thể truy vết policy áp dụng."
  - "Payroll và số liệu chịu quy tắc pháp lý chuyên biệt tuân theo policy tương ứng."
  - "Reconciliation tolerance mặc định không vượt quá 1 minor unit của currency trên một chứng từ, trừ khi policy chuyên biệt được phê duyệt quy định khác."
depends_on:
  - "BR-MDM-001"
  - "BR-FIN-007"
implemented_by:
  - "NFS-DAT-001"
verified_by:
  - "NFR-TEST-DAT-001"
```

```yaml
id: NFR-USE-001
title: "Hiển thị thuật ngữ, trạng thái, số liệu, thời điểm và cảnh báo dễ hiểu, nhất quán"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Khả dụng và tính nhất quán sử dụng"
description: "Người dùng phải hiểu được tên, trạng thái, số tiền, số lượng, thời điểm và cảnh báo của nghiệp vụ trong ngôn ngữ được phê duyệt."
success_criteria:
  - "Người dùng đại diện xác nhận thuật ngữ và đơn vị hiển thị."
depends_on:
  - "BR-CTRL-001"
implemented_by:
  - "NFS-USE-001"
verified_by:
  - "NFR-TEST-USE-001"
```

```yaml
id: NFR-USE-002
title: "Hỗ trợ tiếng Việt và khả năng mở rộng ngôn ngữ khác mà không làm thay đổi ý nghĩa dữ liệu"
type: non-functional
status: reviewed
priority: SHOULD
target_phase: null
source: "Khả dụng và tính nhất quán sử dụng"
description: "Hệ thống phải hỗ trợ tiếng Việt và khả năng mở rộng ngôn ngữ khác mà không làm thay đổi ý nghĩa dữ liệu."
success_criteria:
  - "Thuật ngữ nghiệp vụ có bảng chuẩn hóa và người sở hữu."
depends_on:
  - "NFR-USE-001"
implemented_by:
  - "NFS-USE-002"
verified_by:
  - "NFR-TEST-USE-002"
```

```yaml
id: NFR-USE-003
title: "Business time/currency và locale/display không được làm thay đổi ý nghĩa nghiệp vụ"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Khả dụng và tính nhất quán sử dụng"
source_refs:
  - "DEC-SYS-LOCALE-001"
description: "Business timezone, business/base/functional currency và kỳ nghiệp vụ thuộc Company semantics theo BR-ORG-005. Locale/display formatting như timezone hiển thị, định dạng ngày giờ, ký hiệu/định dạng tiền tệ và số thập phân thuộc BR-SYS-001 và không được thay đổi business meaning/value. Work calendar/holiday thuộc HR/operational calendar theo BR-HRP-002 khi capability đó áp dụng và không được suy ra từ locale/display setting."
success_criteria:
  - "Thay đổi locale/display setting không làm đổi business timestamp, kỳ nghiệp vụ, business currency hoặc monetary value đã ghi nhận."
  - "Cùng một transaction có thể hiển thị theo locale được phép nhưng vẫn truy được canonical business timestamp/currency/value."
  - "Khi HR work calendar áp dụng, ngày làm việc/ngày nghỉ được xác định từ work-calendar policy có hiệu lực và không thay đổi chỉ do user locale/display setting."
depends_on:
  - "BR-ORG-005"
  - "BR-SYS-001"
implemented_by:
  - "NFS-USE-003"
verified_by:
  - "NFR-TEST-USE-003"
```

```yaml
id: NFR-MNT-001
title: "Business configuration thường thay đổi phải cập nhật được mà không sửa source code hoặc redeploy hệ thống"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Khả năng duy trì và cấu hình"
description: "Các business configuration được xác định là configurable như giá, thuế, hạn mức, approval rule, tham số lương, định mức và KPI phải có thể được người có thẩm quyền cập nhật trong phạm vi cho phép mà không cần sửa source code hoặc redeploy ứng dụng. Governance về owner, effective date, approval và history vẫn do business requirement tương ứng kiểm soát."
success_criteria:
  - "Với mẫu configuration thuộc danh mục configurable đã phê duyệt, người có thẩm quyền có thể thay đổi giá trị/rule bằng configuration mechanism mà không sửa source code."
  - "Thay đổi configuration trong phạm vi hỗ trợ không yêu cầu redeploy application để có hiệu lực theo effective policy."
  - "Configuration change vẫn bảo toàn owner, effective date, approval/history theo business requirement tương ứng."
depends_on:
  - "BR-ORG-006"
implemented_by:
  - "NFS-MNT-001"
verified_by:
  - "NFR-TEST-MNT-001"
```

```yaml
id: NFR-MNT-002
title: "Việc thêm công ty, chi nhánh, sản phẩm, kênh hoặc nhà cung cấp không được yêu cầu sao chép dữ liệu lịch sử"
type: non-functional
status: reviewed
priority: SHOULD
target_phase: null
source: "Khả năng duy trì và cấu hình"
description: "Việc thêm công ty, chi nhánh, sản phẩm, kênh hoặc nhà cung cấp không được yêu cầu sao chép dữ liệu lịch sử."
success_criteria:
  - "Đơn vị mới có thể dùng dữ liệu chuẩn được chia sẻ theo quyền."
depends_on:
  - "BR-ORG-002"
implemented_by:
  - "NFS-MNT-002"
verified_by:
  - "NFR-TEST-MNT-002"
```

```yaml
id: NFR-FRESH-001
title: "Độ mới của tồn khả dụng và đơn hàng"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Độ mới và tính kịp thời của dữ liệu"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Thông tin tồn khả dụng và đơn hàng phải có tuổi dữ liệu không quá 5 phút trong thời gian vận hành."
success_criteria:
  - "Tuổi dữ liệu không quá 5 phút trong thời gian vận hành; chủ sở hữu: Kho/Bán hàng."
depends_on:
  - "BR-INV-001"
  - "BR-SAL-001"
implemented_by:
  - "NFS-FRESH-001"
verified_by:
  - "NFR-TEST-FRESH-001"
```

```yaml
id: NFR-FRESH-002
title: "Độ mới của tiền, công nợ và đối soát"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Độ mới và tính kịp thời của dữ liệu"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Dữ liệu tiền/giao dịch thu-chi và số dư AR/AP phục vụ vận hành phải có data age không quá 15 phút. Báo cáo đối soát theo batch, closing, báo cáo kỳ và số liệu chính thức được quản lý theo published cutoff/publication period tương ứng thay vì mặc định target 15 phút."
success_criteria:
  - "Dữ liệu tiền/giao dịch thu-chi và số dư AR/AP vận hành có data age <= 15 phút trong điều kiện vận hành bình thường."
  - "Báo cáo đối soát theo batch, closing hoặc báo cáo kỳ hiển thị published cutoff/publication period và không bị diễn giải là near-real-time khi chưa đến kỳ công bố."
  - "Mỗi báo cáo tài chính vận hành hiển thị data timestamp/cutoff và chủ sở hữu dữ liệu để người dùng xác định độ mới của số liệu."
depends_on:
  - "BR-FIN-001"
  - "BR-FIN-002"
  - "BR-FIN-005"
implemented_by:
  - "NFS-FRESH-002"
verified_by:
  - "NFR-TEST-FRESH-002"
```

```yaml
id: NFR-FRESH-003
title: "Độ mới của sản xuất và chất lượng"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Độ mới và tính kịp thời của dữ liệu"
source_refs:
  - "DEC-NFR-BASELINE-001"
description: "Transaction sản xuất và chất lượng gồm material issue/receipt, production quantity, WIP movement, scrap và quality status phải phản ánh khi giao dịch được ghi nhận chính thức. Chỉ số hoặc tổng hợp theo ca phải được công bố không quá 30 phút sau khi kết thúc ca. Requirement này chỉ quản lý freshness/timeliness; lifecycle preliminary/confirmed của quality result thuộc BR-MFG-007."
success_criteria:
  - "Material issue/receipt, production quantity, WIP movement, scrap và quality status phản ánh dữ liệu mới khi transaction tương ứng được ghi nhận chính thức."
  - "Shift summary/KPI được cập nhật hoặc công bố không quá 30 phút sau thời điểm kết thúc ca."
  - "Mỗi màn hình/báo cáo liên quan hiển thị timestamp hoặc cutoff đủ để nhận biết độ mới của dữ liệu."
depends_on:
  - "BR-MFG-005"
  - "BR-MFG-007"
implemented_by:
  - "NFS-FRESH-003"
verified_by:
  - "NFR-TEST-FRESH-003"
```

```yaml
id: NFR-FRESH-005
title: "Thời điểm có báo cáo quản trị tháng"
type: non-functional
status: reviewed
priority: MUST
target_phase: null
source: "Độ mới và tính kịp thời của dữ liệu"
source_refs:
  - "DEC-NFR-BASELINE-001"
  - "DEC-ORG-CALENDAR-001"
description: "Báo cáo quản trị tháng phải có vào business day tiếp theo sau khi chốt kỳ, trong đó business day được resolve theo Company Business Calendar canonical tại BR-ORG-009."
success_criteria:
  - "Báo cáo quản trị tháng có vào business day tiếp theo sau khi chốt kỳ theo BR-ORG-009; chủ sở hữu: Giám đốc tài chính/Thông tin quản trị."
depends_on:
  - "BR-BI-001"
  - "BR-FIN-008"
  - "BR-ORG-009"
implemented_by:
  - "NFS-FRESH-005"
verified_by:
  - "NFR-TEST-FRESH-005"
```


## 4.1 Retired / Superseded Requirement Trace Register

Active NFR baseline sau cleanup v1.0.0 gồm **24 NFR**, tương ứng 24 planned active `NFS-*` artifacts. Retired register gồm **8 requirement IDs**. Retired `NFS-*`/`NFR-TEST-*` không được tạo mới hoặc hồi sinh; nếu phát sinh measurable quality target mới cho security monitoring phải tạo NFR ID mới.

Active controlled baseline v1.0.0 gồm **220 items = 193 BR + 24 NFR + 3 AR**.


## 5. Quy tắc đặc tả và traceability
- Không chứa thiết kế DB/API/UI.
- Mô tả *cần gì* và *tại sao*, không mô tả *code như thế nào*.
- `status` sử dụng lifecycle `draft -> reviewed -> approved -> superseded`; ID đã từng phát hành không bị tái sử dụng hoặc xóa khỏi traceability lịch sử.
- `target_phase` xác định phase đầu tiên requirement phải được đáp ứng; v1.0.0 để `null` cho đến bước Phase Planning sau content review.
- `priority` không đồng nghĩa với phase triển khai.
- Requirement có `TBD` bắt buộc cho acceptance hoặc boundary decision không được chuyển sang `reviewed` hoặc `approved`.
- `depends_on` chỉ biểu diễn Hard Dependency / Hard Prerequisite. Requirement downstream chỉ được `approved` khi mọi dependency đã `approved` hoặc replacement approved đã được xác định cho dependency superseded. `depends_on` không mặc định là thứ tự triển khai; quan hệ tùy chọn/conditional không được đưa vào trường này.
- Quy tắc phân loại cross-domain: nếu thiếu source capability làm requirement không thể thực hiện bản chất cốt lõi thì đó là Hard Dependency và phải nằm trong `depends_on`; nếu requirement vẫn tồn tại/hoạt động với subset source capability trong approved scope thì source đó là conditional applicability/source và phải được nêu trong `description`/`success_criteria`, không đưa vào `depends_on`.
- `source` là nhãn mô tả; `source_refs` là controlled backward traceability. `source_refs` bắt buộc cho legal/regulatory baseline, product/architecture decision, approved business policy, quantitative acceptance baseline có quyết định nguồn, external standard/contract và cross-document architectural decision/ADR; reference phải resolve tới Source & Decision Register hoặc controlled external artifact/version.
- `FR-*` thuộc FRS-ERP; `NFS-*` thuộc NFS-ERP; `UAT-*` thuộc UAT-ERP; `NFR-TEST-*` thuộc NFR-TEST-ERP. `AR-*` là controlled architecture constraint và được hiện thực qua `DES-*`/design conformance evidence, không sinh `FR-*` hoặc `NFS-*`. Các artifact downstream có thể mở rộng quan hệ 1:N khi phân rã chi tiết.
- Prefix của Requirement ID là historical traceability namespace, không phải canonical ownership authority. Khi prefix khác `owning_domain`/Ownership Matrix, downstream artifact phải giữ ID gốc và mang ownership metadata tương ứng; không rename requirement để đồng bộ prefix với owner.
- Business approval cần governance/audit sử dụng `BR-WF-001`; state transition thuần túy không bắt buộc dùng Workflow. Approval không cấp Permission.
- `implemented_by` là danh sách traceability dự kiến cho đặc tả triển khai và có thể mở rộng 1:N; việc tạo artifact tương ứng được thực hiện ở bước đặc tả tiếp theo.
- Requirement/NFR đã `absorbed_by` hoặc `replaced_by` được loại khỏi active baseline nhưng ID và retired downstream placeholders phải được giữ trong traceability history; ID retired không được tái sử dụng.
- Architectural Rule sử dụng `realized_by` thay cho `implemented_by`; `realized_by` trỏ tới DES artifact dự kiến và `verified_by` trỏ tới design review/conformance evidence.
- Với Business Requirement và Non-functional Requirement, `verified_by` là danh sách traceability dự kiến cho nghiệm thu/kiểm thử và có thể mở rộng 1:N; tiêu chí chi tiết của từng test case được xác định ở tài liệu kiểm thử. Với Architectural Rule, `verified_by` tuân theo design review/conformance evidence như quy tắc AR ở trên.
