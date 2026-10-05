---
document_id: "PLAN-ERP-001"
title: "Phase Planning - Dinktech Mini ERP V2.0"
phase: "Phase Planning"
status: "approved"
version: "1.0.1"
source_document: "REQ-ERP-001"
source_version: "1.0.1"
source_sha256: "c33e4e17608290666c2b1c3bbd5fcd3e0ae0178b6fff9f840776ad30cc610ce4"
owner: "Chủ sở hữu sản phẩm"
authority: "SSOT cho target_phase sau khi Phase Planning được approved và đồng bộ trở lại User Requirements."
last_reviewed: "2026-10-02"
approved_on: "2026-10-02"
---

# Phase Planning - Dinktech Mini ERP V2.0

## 1. Mục đích và nguyên tắc điều chỉnh

Revision `1.0.1` thu hẹp `P1_CORE` sau review scope. Bản `1.0.0` đặt 62/111 controlled items vào P1, về thực chất gần với một broad production go-live hơn là first core increment.

P1 mới được định nghĩa là **minimum operational core / controlled pilot**: đủ foundation và dependency để chạy hai endpoint nghiệp vụ `Purchase -> Receiving` và `Sales Order -> Availability`, cùng baseline Security/Audit/Performance. P1 **không đồng nghĩa full Finance/statutory go-live**.

Quy tắc phase:

- `P1_CORE`: minimum operational core; foundation + master + basic procurement/inventory/sales + baseline NFR.
- `P2_EXTENDED`: complete operational rollout; finance, broader controls, manufacturing, logistics, integration, reporting/document governance.
- `P3_ADVANCED`: specialized/advanced lanes: PLM, omnichannel/POS, HCM/payroll, intercompany và một số capability chuyên sâu.
- `FUTURE`: chủ động defer ngoài roadmap hiện tại; revision này chưa gán item nào.

Hard dependency rule: với mọi `A depends_on B`, phải thỏa `phase(B) <= phase(A)`. `governed_by`, `references`, `applies_to` không kéo phase.

## 2. Vì sao P1 không thể giảm tùy ý

User Requirements có dependency closure khá sâu. Nếu P1 vẫn phải có đồng thời:

- `BR-PUR-003` — receiving;
- `BR-SAL-002` — sales availability/commitment;
- baseline `NFR-SEC-001`, `NFR-AVL-001`, `NFR-PER-001`, `NFR-AUD-001`, `NFR-DAT-001`, `NFR-USE-001`, `NFR-PLT-001`;
- ba Architectural Rules;

thì union của chính các endpoint và toàn bộ Hard Prerequisite đã là **39 controlled items**. Revision này thêm `BR-PRD-002`, `BR-INV-002`, `BR-INV-003` để P1 có UOM conversion, stock movement và inventory adjustment, đưa P1 lên **42 items**.

Muốn P1 nhỏ hơn đáng kể (~30-35 items) phải thực hiện ít nhất một trong hai quyết định: (a) bỏ một trong hai business stream Purchase hoặc Sales khỏi P1; hoặc (b) review lại Hard Prerequisite trong User Requirements.

## 3. Validation snapshot

- Controlled items: **111**.
- Hard dependency edges: **225**.
- Duplicate ID: **0**.
- Dangling dependency: **0**.
- Dependency cycle: **0**.
- Maximum dependency depth: **14**.
- Cross-phase dependency violation: **0**.

## 4. Revised phase summary

| Phase | Total | BR | NFR | AR | MUST | SHOULD |
|---|---:|---:|---:|---:|---:|---:|
| `P1_CORE` | 42 | 32 | 7 | 3 | 39 | 0 |
| `P2_EXTENDED` | 51 | 45 | 6 | 0 | 46 | 5 |
| `P3_ADVANCED` | 18 | 18 | 0 | 0 | 15 | 3 |
| `FUTURE` | 0 | 0 | 0 | 0 | 0 | 0 |

### 4.1 Delta so với v1.0.0

- P1: **62 -> 42** (`-20`).
- P2: **44 -> 51**.
- P3: **5 -> 18**.

20 items chuyển khỏi P1 sang P2:

- `BR-BI-001` — Cung cấp shared reporting, slicing và drill-through
- `BR-CTL-003` — Quản lý ngoại lệ xuyên miền
- `BR-CUS-002` — Quản lý điều kiện thương mại và tín dụng của Customer
- `BR-DOC-001` — Gắn, tìm kiếm và truy cập tài liệu nghiệp vụ
- `BR-FIN-001` — Quản lý Accounts Receivable
- `BR-FIN-002` — Quản lý cash, payment, expense và treasury account
- `BR-FIN-004` — Quản lý operational tax và invoice lifecycle
- `BR-FIN-006` — Quản lý financial period, closing và periodic allocation
- `BR-FIN-007` — Quản lý nguồn số liệu tài chính vận hành và traceability
- `BR-FIN-009` — Quản lý Accounts Payable
- `BR-IAM-004` — Quản lý tách biệt trách nhiệm, ủy quyền và rà soát quyền
- `BR-IAM-006` — Ghi nhận và giám sát sự kiện IAM/Security
- `BR-INV-005` — Quản lý chính sách xuất kho và định giá tồn
- `BR-OPS-001` — Theo dõi đào tạo, hỗ trợ và mức sử dụng ERP
- `BR-ORG-003` — Tổng hợp và xem dữ liệu theo phạm vi tổ chức
- `BR-ORG-005` — Quản lý policy và trách nhiệm dữ liệu theo phạm vi tổ chức
- `BR-SAL-003` — Kiểm soát credit và commercial exception
- `NFR-AVL-002` — Đảm bảo business continuity và disaster recovery
- `NFR-FRESH-001` — Đảm bảo freshness theo từng domain nghiệp vụ
- `NFR-MNT-001` — Hỗ trợ configuration và mở rộng tổ chức mà không nhân bản dữ liệu

### 4.2 Domain distribution

| Domain | P1_CORE | P2_EXTENDED | P3_ADVANCED | FUTURE |
|---|---:|---:|---:|---:|
| Controlled Architectural Rules | 3 | 0 | 0 | 0 |
| Organization & Enterprise Structure | 3 | 3 | 0 | 0 |
| Employee & Organizational Structure | 2 | 0 | 0 | 0 |
| Authorization & Security | 4 | 2 | 0 | 0 |
| Cross-domain Controls & Workflow | 3 | 1 | 0 | 0 |
| Audit & Traceability | 1 | 0 | 0 | 0 |
| System Foundation | 1 | 0 | 0 | 0 |
| Operational Readiness | 0 | 1 | 0 | 0 |
| Master Data & Data Governance | 3 | 1 | 0 | 0 |
| Product | 3 | 2 | 0 | 0 |
| PLM & Engineering | 0 | 0 | 3 | 0 |
| Customer & CRM | 1 | 2 | 1 | 0 |
| Pricing & Promotion | 1 | 0 | 1 | 0 |
| Supplier | 1 | 2 | 0 | 0 |
| Purchase | 3 | 1 | 0 | 0 |
| Inventory | 3 | 2 | 1 | 0 |
| Manufacturing | 0 | 7 | 0 | 0 |
| Sales | 2 | 3 | 0 | 0 |
| Finance Operations | 1 | 7 | 1 | 0 |
| Logistics & Delivery | 0 | 3 | 0 | 0 |
| Commerce / Omnichannel / POS | 0 | 0 | 4 | 0 |
| HCM & Payroll | 0 | 0 | 4 | 0 |
| Document & Information | 0 | 3 | 2 | 0 |
| Reporting & BI | 0 | 2 | 1 | 0 |
| Integration & Data Exchange | 0 | 3 | 0 | 0 |
| Security | 1 | 0 | 0 | 0 |
| Availability & Continuity | 1 | 1 | 0 | 0 |
| Performance & Scalability | 1 | 2 | 0 | 0 |
| Auditability | 1 | 0 | 0 | 0 |
| Data Integrity | 1 | 0 | 0 | 0 |
| Usability & Localization | 1 | 0 | 0 | 0 |
| Maintainability & Configurability | 0 | 1 | 0 | 0 |
| Compliance & Retention | 0 | 1 | 0 | 0 |
| Data Freshness | 0 | 1 | 0 | 0 |
| Application Platform | 1 | 0 | 0 | 0 |

## 5. P1_CORE revised scope

### 5.1 Included

- **Architecture/Foundation:** 3 AR; Group/Company/Organization Context; timezone/currency/calendar; Employee/Assignment; base authorization.
- **Controls:** common lifecycle/numbering, cross-document responsibility, Shared Workflow, Central Audit.
- **Master Data:** common reference/ownership/lifecycle; Product; UOM; default selling price; Customer; Supplier; canonical Pricing.
- **Operations:** Purchase Request/PO/Receiving; Inventory balance/movement/count-adjustment; Sales Order capture/availability.
- **Finance foundation only:** exchange-rate capability required by Data Integrity NFR; AR/AP/cash/tax/close are not P1.
- **Baseline NFR:** Security, Availability, Interaction Performance, Auditability, Data Integrity, Usability/Localization, Web Desktop Platform.

### 5.2 Explicitly excluded from P1

- AR/AP, cash/payment, operational tax/invoice lifecycle, financial close, financial traceability.
- Credit control and commercial exception.
- Inventory valuation policy and lot/serial end-to-end traceability.
- SoD/delegation review and security-event monitoring.
- Cross-domain exception management.
- Reporting/BI, document attachment/search, readiness/adoption tracking.
- DR/continuity, configurability, freshness acceptance.
- Manufacturing, logistics, integration, omnichannel/POS, HCM/payroll, PLM, intercompany.

P1 do đó là một controlled core increment, không phải statutory/finance production cutover.

## 6. Phase intent

### 6.1 P2_EXTENDED

P2 hoàn thiện operating ERP cho broader production rollout: AR/AP, cash/payment, tax/invoice, close, finance traceability, credit/returns, inventory valuation/lot-serial, advanced security/control, manufacturing, logistics, integration, document/reporting governance và các NFR cần trước broader production rollout.

### 6.2 P3_ADVANCED

P3 gom các lane có thể triển khai độc lập sau core: PLM, Omnichannel/POS, HCM/Payroll, intercompany, privacy/promotion và selected advanced information capabilities.

## 7. Full phase assignment matrix

| ID | Title | Domain | Priority | Target phase | Direct hard dependencies | Closure | Rationale | Gate / note |
|---|---|---|---|---|---|---:|---|---|
| `AR-DATA-OWNERSHIP-001` | Reference/Common Data Ownership Rule | Controlled Architectural Rules | — | `P1_CORE` | — | 0 PASS | Architecture invariant phải có trước mọi downstream design. | — |
| `AR-DOM-001` | Domain Boundary and Canonical Ownership Rule | Controlled Architectural Rules | — | `P1_CORE` | — | 0 PASS | Architecture invariant phải có trước mọi downstream design. | — |
| `AR-TXN-001` | Business Transaction Context Invariant | Controlled Architectural Rules | — | `P1_CORE` | — | 0 PASS | Architecture invariant phải có trước mọi downstream design. | — |
| `BR-ORG-001` | Quản lý mô hình Multi-Group, Group và Company | Organization & Enterprise Structure | MUST | `P1_CORE` | — | 0 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-ORG-002` | Quản lý Organization Context và hierarchy vận hành | Organization & Enterprise Structure | MUST | `P1_CORE` | `BR-ORG-001` | 1 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | Hierarchy policy phải được controlled trong FRS/DES. |
| `BR-ORG-003` | Tổng hợp và xem dữ liệu theo phạm vi tổ chức | Organization & Enterprise Structure | MUST | `P2_EXTENDED` | `BR-ORG-001`, `BR-ORG-002`, `BR-IAM-003` | 7 PASS | Reporting/policy/project extensions không cần cho P1 transaction pilot. | — |
| `BR-ORG-004` | Quản lý business timezone, currency, calendar và kỳ theo Company | Organization & Enterprise Structure | MUST | `P1_CORE` | `BR-ORG-002` | 2 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-ORG-005` | Quản lý policy và trách nhiệm dữ liệu theo phạm vi tổ chức | Organization & Enterprise Structure | MUST | `P2_EXTENDED` | `BR-ORG-002` | 2 PASS | Reporting/policy/project extensions không cần cho P1 transaction pilot. | — |
| `BR-ORG-006` | Quản lý Project hoặc đối tượng tập hợp chi phí/doanh thu | Organization & Enterprise Structure | SHOULD | `P2_EXTENDED` | `BR-ORG-002` | 2 PASS | Reporting/policy/project extensions không cần cho P1 transaction pilot. | — |
| `BR-EMP-001` | Quản lý Employee và Employee Assignment | Employee & Organizational Structure | MUST | `P1_CORE` | `BR-ORG-002`, `BR-EMP-002` | 3 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-EMP-002` | Quản lý Department, Position và organizational view | Employee & Organizational Structure | MUST | `P1_CORE` | `BR-ORG-002` | 2 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-IAM-001` | Quản lý User Identity và Group Access Membership | Authorization & Security | MUST | `P1_CORE` | `BR-ORG-001`, `BR-EMP-001` | 4 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-IAM-002` | Quản lý Permission, Profile và Business Group | Authorization & Security | MUST | `P1_CORE` | — | 0 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-IAM-003` | Đánh giá Authorization theo Policy Based Access Control | Authorization & Security | MUST | `P1_CORE` | `BR-IAM-001`, `BR-IAM-002`, `BR-ORG-002` | 6 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-IAM-004` | Quản lý tách biệt trách nhiệm, ủy quyền và rà soát quyền | Authorization & Security | MUST | `P2_EXTENDED` | `BR-IAM-003` | 7 PASS | Advanced control/monitoring sau baseline authorization. | — |
| `BR-IAM-005` | Bảo vệ dữ liệu nhạy cảm theo mục đích và phạm vi công việc | Authorization & Security | MUST | `P1_CORE` | `BR-IAM-003` | 7 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-IAM-006` | Ghi nhận và giám sát sự kiện IAM/Security | Authorization & Security | MUST | `P2_EXTENDED` | `BR-IAM-001`, `BR-IAM-003`, `BR-CTL-003` | 10 PASS | Advanced control/monitoring sau baseline authorization. | — |
| `BR-CTL-001` | Chuẩn hóa lifecycle, numbering và quan hệ giữa chứng từ | Cross-domain Controls & Workflow | MUST | `P1_CORE` | `BR-ORG-002`, `BR-ORG-004` | 3 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-CTL-002` | Bảo đảm trách nhiệm, truy xuất và đối chiếu xuyên chứng từ | Cross-domain Controls & Workflow | MUST | `P1_CORE` | `BR-CTL-001`, `BR-IAM-003` | 9 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-CTL-003` | Quản lý ngoại lệ xuyên miền | Cross-domain Controls & Workflow | MUST | `P2_EXTENDED` | `BR-CTL-001` | 4 PASS | Cross-domain exception management sau transaction core. | — |
| `BR-CTL-004` | Quản lý business approval và Shared Workflow | Cross-domain Controls & Workflow | MUST | `P1_CORE` | `BR-CTL-001`, `BR-IAM-003` | 9 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-AUD-001` | Quản lý Central Audit Trail | Audit & Traceability | MUST | `P1_CORE` | `BR-IAM-001`, `BR-CTL-002` | 10 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-SYS-001` | Quản lý locale và display configuration dùng chung | System Foundation | MUST | `P1_CORE` | `BR-ORG-004` | 3 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-OPS-001` | Theo dõi đào tạo, hỗ trợ và mức sử dụng ERP | Operational Readiness | SHOULD | `P2_EXTENDED` | `BR-IAM-001` | 5 PASS | Go-live/readiness control cho broader rollout. | — |
| `BR-MDM-001` | Quản lý common reference data và chuẩn định danh | Master Data & Data Governance | MUST | `P1_CORE` | `BR-ORG-002` | 2 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | External official source phải resolve trước approval của normative rule tương ứng. |
| `BR-MDM-002` | Quản lý ownership, sharing và field-level override | Master Data & Data Governance | MUST | `P1_CORE` | `BR-ORG-001`, `BR-ORG-002`, `BR-MDM-001` | 3 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-MDM-003` | Quản lý lifecycle và thay đổi master data nhạy cảm | Master Data & Data Governance | MUST | `P1_CORE` | `BR-MDM-001`, `BR-IAM-003`, `BR-CTL-004` | 11 PASS | Hard foundation trực tiếp của operational core hoặc baseline control. | — |
| `BR-MDM-004` | Quản lý duplicate, data quality và lineage của master data | Master Data & Data Governance | MUST | `P2_EXTENDED` | `BR-MDM-001`, `BR-MDM-003`, `BR-AUD-001` | 14 PASS | Extended governance/operational depth. | — |
| `BR-PRD-001` | Quản lý Product Master và lifecycle vận hành | Product | MUST | `P1_CORE` | `BR-MDM-001`, `BR-MDM-002`, `BR-MDM-003` | 13 PASS | Product/UOM/default price cần cho mua-kho-bán. | — |
| `BR-PRD-002` | Quản lý đơn vị tính và quy đổi Product | Product | MUST | `P1_CORE` | `BR-PRD-001` | 14 PASS | Product/UOM/default price cần cho mua-kho-bán. | — |
| `BR-PRD-003` | Quản lý chính sách lot/serial, hạn dùng, chất lượng và bảo quản | Product | MUST | `P2_EXTENDED` | `BR-PRD-001` | 14 PASS | Extended governance/operational depth. | — |
| `BR-PRD-004` | Quản lý combo và quà tặng | Product | MUST | `P2_EXTENDED` | `BR-PRD-001`, `BR-PRD-002` | 15 PASS | Extended governance/operational depth. | — |
| `BR-PRD-005` | Quản lý Product Default Selling Price | Product | MUST | `P1_CORE` | `BR-PRD-001`, `BR-ORG-004`, `BR-MDM-002` | 14 PASS | Product/UOM/default price cần cho mua-kho-bán. | — |
| `BR-PLM-001` | Quản lý vòng đời Engineering Product | PLM & Engineering | MUST | `P3_ADVANCED` | — | 0 PASS | Independent engineering lifecycle. | — |
| `BR-PLM-002` | Quản lý revision và dữ liệu kỹ thuật | PLM & Engineering | MUST | `P3_ADVANCED` | `BR-PLM-001` | 1 PASS | Independent engineering lifecycle. | — |
| `BR-PLM-003` | Quản lý Engineering Change từ ECR đến ECN/ECO | PLM & Engineering | MUST | `P3_ADVANCED` | `BR-PLM-001`, `BR-PLM-002`, `BR-CTL-004` | 12 PASS | Independent engineering lifecycle. | — |
| `BR-CUS-001` | Quản lý Customer Master, hồ sơ và lifecycle | Customer & CRM | MUST | `P1_CORE` | `BR-MDM-001`, `BR-MDM-002` | 4 PASS | Customer master tối thiểu cho sales. | — |
| `BR-CUS-002` | Quản lý điều kiện thương mại và tín dụng của Customer | Customer & CRM | MUST | `P2_EXTENDED` | `BR-CUS-001`, `BR-MDM-003` | 14 PASS | Extended governance/operational depth. | — |
| `BR-CUS-003` | Theo dõi Customer history và lịch sử tương tác | Customer & CRM | MUST | `P2_EXTENDED` | `BR-CUS-001` | 5 PASS | Extended governance/operational depth. | — |
| `BR-CUS-004` | Quản lý consent, privacy và hạn chế sử dụng dữ liệu Customer | Customer & CRM | SHOULD | `P3_ADVANCED` | `BR-CUS-001`, `BR-IAM-005` | 11 PASS | Specialized/advanced capability. | — |
| `BR-PRC-001` | Quản lý Pricing Policy và canonical price resolution | Pricing & Promotion | MUST | `P1_CORE` | `BR-CUS-001`, `BR-PRD-005`, `BR-CTL-004` | 16 PASS | Canonical base-price resolution là dependency của Sales Order. | — |
| `BR-PRC-002` | Quản lý discount, promotion và coupon | Pricing & Promotion | MUST | `P3_ADVANCED` | `BR-PRC-001`, `BR-PRD-004` | 19 PASS | Specialized/advanced capability. | — |
| `BR-SUP-001` | Quản lý Supplier Master và thuộc tính nhạy cảm | Supplier | MUST | `P1_CORE` | `BR-MDM-001`, `BR-MDM-002`, `BR-MDM-003` | 13 PASS | Supplier master tối thiểu cho procurement. | — |
| `BR-SUP-002` | Quản lý qualification và approved supplier list | Supplier | SHOULD | `P2_EXTENDED` | `BR-SUP-001`, `BR-CTL-004`, `BR-PRD-001` | 15 PASS | Extended governance/operational depth. | — |
| `BR-SUP-003` | Đánh giá hiệu suất Supplier | Supplier | SHOULD | `P2_EXTENDED` | `BR-SUP-001`, `BR-PUR-003` | 19 PASS | Extended governance/operational depth. | — |
| `BR-PUR-001` | Quản lý Purchase Request và sourcing | Purchase | MUST | `P1_CORE` | `BR-ORG-002`, `BR-SUP-001` | 14 PASS | P1 endpoint: procure-to-receive cơ bản. | — |
| `BR-PUR-002` | Quản lý Purchase Order và cam kết mua | Purchase | MUST | `P1_CORE` | `BR-PUR-001`, `BR-SUP-001` | 15 PASS | P1 endpoint: procure-to-receive cơ bản. | — |
| `BR-PUR-003` | Quản lý receiving và chênh lệch nhận hàng | Purchase | MUST | `P1_CORE` | `BR-PUR-002`, `BR-INV-001` | 18 PASS | P1 endpoint: procure-to-receive cơ bản. | — |
| `BR-PUR-004` | Quản lý invoice matching và landed cost | Purchase | MUST | `P2_EXTENDED` | `BR-PUR-002`, `BR-PUR-003`, `BR-FIN-009` | 20 PASS | Extended governance/operational depth. | — |
| `BR-INV-001` | Quản lý số dư, trạng thái và reservation tồn kho | Inventory | MUST | `P1_CORE` | `BR-ORG-002`, `BR-PRD-001` | 14 PASS | P1 stock quantity/movement/counting core. | — |
| `BR-INV-002` | Quản lý inventory movement và intra-company transfer | Inventory | MUST | `P1_CORE` | `BR-INV-001`, `BR-ORG-002` | 15 PASS | P1 stock quantity/movement/counting core. | — |
| `BR-INV-003` | Quản lý kiểm kê, điều chỉnh và kiểm soát tồn bất thường | Inventory | MUST | `P1_CORE` | `BR-INV-001`, `BR-CTL-004`, `BR-ORG-004` | 15 PASS | P1 stock quantity/movement/counting core. | — |
| `BR-INV-004` | Quản lý lot/serial, expiry và traceability | Inventory | MUST | `P2_EXTENDED` | `BR-INV-001`, `BR-PRD-003` | 16 PASS | Valuation/traceability extension. | — |
| `BR-INV-005` | Quản lý chính sách xuất kho và định giá tồn | Inventory | MUST | `P2_EXTENDED` | `BR-INV-001`, `BR-ORG-005` | 16 PASS | Valuation/traceability extension. | — |
| `BR-INV-006` | Quản lý intercompany inventory movement | Inventory | MUST | `P3_ADVANCED` | `BR-INV-001`, `BR-ORG-002` | 15 PASS | Cross-company processing after single-company flows stabilize. | — |
| `BR-MFG-001` | Quản lý BOM, routing, work center và năng lực sản xuất | Manufacturing | MUST | `P2_EXTENDED` | `BR-PRD-001`, `BR-ORG-002` | 14 PASS | Production vertical trên foundation P1. | — |
| `BR-MFG-002` | Lập kế hoạch nhu cầu vật tư và năng lực | Manufacturing | MUST | `P2_EXTENDED` | `BR-MFG-001`, `BR-INV-001` | 16 PASS | Production vertical trên foundation P1. | — |
| `BR-MFG-003` | Quản lý Production Order, WIP, output, yield và scrap | Manufacturing | MUST | `P2_EXTENDED` | `BR-MFG-001`, `BR-INV-001` | 16 PASS | Production vertical trên foundation P1. | — |
| `BR-MFG-004` | Quản lý quality control và production genealogy | Manufacturing | MUST | `P2_EXTENDED` | `BR-MFG-003`, `BR-INV-004`, `BR-PRD-003` | 19 PASS | Production vertical trên foundation P1. | — |
| `BR-MFG-005` | Kiểm soát thay đổi Production BOM và process đang mở | Manufacturing | MUST | `P2_EXTENDED` | `BR-MFG-001` | 15 PASS | Production vertical trên foundation P1. | — |
| `BR-MFG-006` | Tính và phân bổ giá thành sản xuất | Manufacturing | MUST | `P2_EXTENDED` | `BR-MFG-003`, `BR-INV-005` | 19 PASS | Production vertical trên foundation P1. | — |
| `BR-MFG-007` | Quản lý subcontract manufacturing | Manufacturing | MUST | `P2_EXTENDED` | `BR-MFG-003`, `BR-SUP-001`, `BR-INV-001`, `BR-PUR-002`, `BR-MFG-004` | 23 PASS | Production vertical trên foundation P1. | — |
| `BR-SAL-001` | Quản lý quotation và sales order capture | Sales | MUST | `P1_CORE` | `BR-CUS-001`, `BR-PRD-001`, `BR-PRC-001` | 17 PASS | P1 endpoint: order capture và availability. | — |
| `BR-SAL-002` | Quản lý availability và fulfillment commitment | Sales | MUST | `P1_CORE` | `BR-SAL-001`, `BR-INV-001` | 19 PASS | P1 endpoint: order capture và availability. | — |
| `BR-SAL-003` | Kiểm soát credit và commercial exception | Sales | MUST | `P2_EXTENDED` | `BR-SAL-001`, `BR-CUS-002`, `BR-FIN-001`, `BR-CTL-004`, `BR-PRC-001` | 20 PASS | Credit/fulfillment/return controls. | — |
| `BR-SAL-004` | Quản lý lifecycle fulfillment và thành phần doanh thu của Sales Order | Sales | MUST | `P2_EXTENDED` | `BR-SAL-001`, `BR-INV-001`, `BR-PRD-004` | 21 PASS | Credit/fulfillment/return controls. | — |
| `BR-SAL-005` | Quản lý return, RMA, warranty và complaint | Sales | MUST | `P2_EXTENDED` | `BR-SAL-001`, `BR-INV-001`, `BR-INV-004`, `BR-CTL-004` | 21 PASS | Credit/fulfillment/return controls. | — |
| `BR-FIN-001` | Quản lý Accounts Receivable | Finance Operations | MUST | `P2_EXTENDED` | `BR-SAL-001`, `BR-CUS-001` | 18 PASS | Complete operational finance sau khi transaction core ổn định. | — |
| `BR-FIN-002` | Quản lý cash, payment, expense và treasury account | Finance Operations | MUST | `P2_EXTENDED` | `BR-ORG-002`, `BR-EMP-002`, `BR-IAM-003`, `BR-ORG-004` | 8 PASS | Complete operational finance sau khi transaction core ổn định. | — |
| `BR-FIN-003` | Đối soát ngân hàng | Finance Operations | MUST | `P2_EXTENDED` | `BR-FIN-002` | 9 PASS | Complete operational finance sau khi transaction core ổn định. | — |
| `BR-FIN-004` | Quản lý operational tax và invoice lifecycle | Finance Operations | MUST | `P2_EXTENDED` | `BR-ORG-004`, `BR-MDM-001`, `BR-SAL-001`, `BR-PUR-002`, `BR-FIN-001`, `BR-FIN-009` | 23 PASS | Complete operational finance sau khi transaction core ổn định. | Tax/legal rule cụ thể cần VERIFIED source trước normative approval. |
| `BR-FIN-005` | Quản lý multi-currency và exchange rate | Finance Operations | MUST | `P1_CORE` | `BR-ORG-004` | 3 PASS | Foundation exchange-rate/precision dependency; không phải full Finance go-live. | — |
| `BR-FIN-006` | Quản lý financial period, closing và periodic allocation | Finance Operations | MUST | `P2_EXTENDED` | `BR-ORG-004`, `BR-CTL-004`, `BR-FIN-002` | 11 PASS | Complete operational finance sau khi transaction core ổn định. | — |
| `BR-FIN-007` | Quản lý nguồn số liệu tài chính vận hành và traceability | Finance Operations | MUST | `P2_EXTENDED` | `BR-FIN-001`, `BR-FIN-009`, `BR-INV-001`, `BR-INV-002` | 25 PASS | Complete operational finance sau khi transaction core ổn định. | — |
| `BR-FIN-008` | Quản lý financial consequence của intercompany sale | Finance Operations | MUST | `P3_ADVANCED` | `BR-ORG-002`, `BR-FIN-001`, `BR-FIN-009` | 23 PASS | Cross-company processing after single-company flows stabilize. | — |
| `BR-FIN-009` | Quản lý Accounts Payable | Finance Operations | MUST | `P2_EXTENDED` | `BR-PUR-002`, `BR-SUP-001` | 16 PASS | Complete operational finance sau khi transaction core ổn định. | — |
| `BR-LOG-001` | Quản lý carrier và delivery service | Logistics & Delivery | MUST | `P2_EXTENDED` | `BR-MDM-001`, `BR-ORG-002` | 3 PASS | Production-operational extension sau core. | — |
| `BR-LOG-002` | Quản lý shipment, delivery status và exception | Logistics & Delivery | MUST | `P2_EXTENDED` | `BR-SAL-001`, `BR-INV-002`, `BR-LOG-001` | 21 PASS | Production-operational extension sau core. | — |
| `BR-LOG-003` | Đối soát COD, freight và carrier charge | Logistics & Delivery | MUST | `P2_EXTENDED` | `BR-LOG-002`, `BR-FIN-002` | 23 PASS | Production-operational extension sau core. | — |
| `BR-COM-001` | Quản lý mapping dữ liệu với kênh bán | Commerce / Omnichannel / POS | MUST | `P3_ADVANCED` | `BR-PRD-001`, `BR-CUS-001`, `BR-INV-001` | 16 PASS | Channel-specific integration/operations after core and logistics. | — |
| `BR-COM-002` | Đồng bộ đơn hàng, trạng thái và sellable inventory đa kênh | Commerce / Omnichannel / POS | MUST | `P3_ADVANCED` | `BR-COM-001`, `BR-SAL-001`, `BR-INV-001` | 20 PASS | Channel-specific integration/operations after core and logistics. | — |
| `BR-COM-003` | Quản lý POS shift và controlled offline fallback | Commerce / Omnichannel / POS | MUST | `P3_ADVANCED` | `BR-ORG-002`, `BR-IAM-001` | 5 PASS | Channel-specific integration/operations after core and logistics. | — |
| `BR-COM-004` | Đối soát kênh bán và external transaction evidence | Commerce / Omnichannel / POS | MUST | `P3_ADVANCED` | `BR-COM-002`, `BR-LOG-003`, `BR-FIN-001`, `BR-DOC-001` | 28 PASS | Channel-specific integration/operations after core and logistics. | — |
| `BR-HRP-001` | Quản lý hồ sơ HR, hợp đồng và Employee Work Calendar | HCM & Payroll | MUST | `P3_ADVANCED` | `BR-EMP-001`, `BR-EMP-002`, `BR-ORG-002`, `BR-ORG-004` | 5 PASS | Separate enterprise domain; not hard prerequisite of core ERP transactions. | Legal/regulatory HR baseline nếu áp dụng cần controlled source. |
| `BR-HRP-002` | Quản lý attendance, leave, overtime và monthly timesheet | HCM & Payroll | MUST | `P3_ADVANCED` | `BR-HRP-001`, `BR-CTL-004` | 11 PASS | Separate enterprise domain; not hard prerequisite of core ERP transactions. | — |
| `BR-HRP-003` | Quản lý compensation và payroll lifecycle | HCM & Payroll | MUST | `P3_ADVANCED` | `BR-HRP-001`, `BR-HRP-002`, `BR-CTL-004` | 12 PASS | Separate enterprise domain; not hard prerequisite of core ERP transactions. | — |
| `BR-HRP-004` | Phân bổ labor cost theo target nghiệp vụ | HCM & Payroll | SHOULD | `P3_ADVANCED` | `BR-HRP-003` | 13 PASS | Separate enterprise domain; not hard prerequisite of core ERP transactions. | — |
| `BR-DOC-001` | Gắn, tìm kiếm và truy cập tài liệu nghiệp vụ | Document & Information | MUST | `P2_EXTENDED` | `BR-IAM-003`, `BR-ORG-002` | 7 PASS | Operational governance/reporting cần cho broader go-live. | — |
| `BR-DOC-002` | Quản lý version và lifecycle tài liệu | Document & Information | MUST | `P2_EXTENDED` | `BR-DOC-001`, `BR-CTL-004` | 11 PASS | Operational governance/reporting cần cho broader go-live. | — |
| `BR-DOC-003` | Phân loại và kiểm soát phân phối tài liệu | Document & Information | MUST | `P3_ADVANCED` | `BR-DOC-001`, `BR-IAM-005` | 9 PASS | Specialized/advanced capability. | — |
| `BR-DOC-004` | Quản lý retention hold và nghĩa vụ lưu giữ | Document & Information | MUST | `P2_EXTENDED` | `BR-DOC-002` | 12 PASS | Operational governance/reporting cần cho broader go-live. | Retention legal minimum nếu áp dụng cần controlled source. |
| `BR-DOC-005` | Quản lý trao đổi và notification nghiệp vụ | Document & Information | SHOULD | `P3_ADVANCED` | `BR-DOC-001`, `BR-IAM-003`, `BR-CTL-004` | 11 PASS | Specialized/advanced capability. | — |
| `BR-BI-001` | Cung cấp shared reporting, slicing và drill-through | Reporting & BI | MUST | `P2_EXTENDED` | `BR-IAM-003`, `BR-ORG-002` | 7 PASS | Operational governance/reporting cần cho broader go-live. | — |
| `BR-BI-002` | Quản lý KPI definition và operational dashboard | Reporting & BI | MUST | `P2_EXTENDED` | `BR-BI-001` | 8 PASS | Operational governance/reporting cần cho broader go-live. | — |
| `BR-BI-003` | Quản lý report export và decision snapshot | Reporting & BI | MUST | `P3_ADVANCED` | `BR-BI-001`, `BR-IAM-005`, `BR-DOC-002` | 14 PASS | Specialized/advanced capability. | — |
| `BR-INT-001` | Trao đổi dữ liệu bên ngoài với reference và evidence | Integration & Data Exchange | MUST | `P2_EXTENDED` | `BR-MDM-001` | 3 PASS | Production-operational extension sau core. | — |
| `BR-INT-002` | Kiểm soát validation, reconciliation và correction dữ liệu trao đổi | Integration & Data Exchange | MUST | `P2_EXTENDED` | `BR-INT-001`, `BR-CTL-004` | 12 PASS | Production-operational extension sau core. | — |
| `BR-INT-003` | Quản lý bulk import và controlled export | Integration & Data Exchange | MUST | `P2_EXTENDED` | `BR-INT-001`, `BR-IAM-003` | 9 PASS | Production-operational extension sau core. | Regulatory export rule nếu áp dụng phải trace được controlled source. |
| `NFR-SEC-001` | Bảo vệ dữ liệu khỏi truy cập, sửa đổi hoặc xuất trái thẩm quyền | Security | MUST | `P1_CORE` | `BR-IAM-003`, `BR-IAM-005` | 8 PASS | Baseline acceptance constraint cho P1 pilot/core. | — |
| `NFR-AVL-001` | Đảm bảo availability trong khung giờ vận hành | Availability & Continuity | MUST | `P1_CORE` | — | 0 PASS | Baseline acceptance constraint cho P1 pilot/core. | — |
| `NFR-AVL-002` | Đảm bảo business continuity và disaster recovery | Availability & Continuity | MUST | `P2_EXTENDED` | `NFR-AVL-001` | 1 PASS | NFR activated before broader production rollout. | — |
| `NFR-PER-001` | Đáp ứng hiệu năng tương tác vận hành | Performance & Scalability | MUST | `P1_CORE` | `BR-ORG-002` | 2 PASS | Baseline acceptance constraint cho P1 pilot/core. | — |
| `NFR-PER-002` | Đáp ứng freshness của near-real-time reporting | Performance & Scalability | MUST | `P2_EXTENDED` | `BR-BI-001` | 8 PASS | NFR activated before broader production rollout. | — |
| `NFR-PER-003` | Đáp ứng capacity và scalability baseline | Performance & Scalability | SHOULD | `P2_EXTENDED` | `BR-ORG-002`, `NFR-PER-001` | 3 PASS | NFR activated before broader production rollout. | — |
| `NFR-AUD-001` | Bảo toàn, truy vấn và tái lập audit evidence | Auditability | MUST | `P1_CORE` | `BR-AUD-001` | 11 PASS | Baseline acceptance constraint cho P1 pilot/core. | — |
| `NFR-DAT-001` | Bảo toàn precision và rounding của dữ liệu định lượng | Data Integrity | MUST | `P1_CORE` | `BR-MDM-001`, `BR-FIN-005` | 5 PASS | Baseline acceptance constraint cho P1 pilot/core. | — |
| `NFR-USE-001` | Đảm bảo usability, localization và semantic consistency | Usability & Localization | MUST | `P1_CORE` | `BR-CTL-001`, `BR-ORG-004`, `BR-SYS-001` | 5 PASS | Baseline acceptance constraint cho P1 pilot/core. | — |
| `NFR-MNT-001` | Hỗ trợ configuration và mở rộng tổ chức mà không nhân bản dữ liệu | Maintainability & Configurability | MUST | `P2_EXTENDED` | `BR-ORG-005`, `BR-ORG-002` | 3 PASS | NFR activated before broader production rollout. | — |
| `NFR-CMP-001` | Đảm bảo retention và cung cấp compliance evidence | Compliance & Retention | MUST | `P2_EXTENDED` | `BR-DOC-002`, `BR-DOC-004`, `NFR-AUD-001` | 16 PASS | NFR activated before broader production rollout. | `source_refs` phải resolve trước approval legal-retention baseline. |
| `NFR-FRESH-001` | Đảm bảo freshness theo từng domain nghiệp vụ | Data Freshness | MUST | `P2_EXTENDED` | — | 0 PASS | NFR activated before broader production rollout. | NFS phải đăng ký dataset/freshness class, owner và measurement point. |
| `NFR-PLT-001` | Cung cấp ERP trên nền tảng web desktop | Application Platform | MUST | `P1_CORE` | — | 0 PASS | Baseline acceptance constraint cho P1 pilot/core. | — |

## 8. P1 specification waves

Các wave là thứ tự đặc tả, không phải phase mới:

1. **Wave A — Architecture & Organization:** ARs, Organization, Employee, IAM base.
2. **Wave B — Shared Controls & MDM:** lifecycle/numbering, workflow, audit, common reference/ownership/master lifecycle.
3. **Wave C — Core Masters:** Product/UOM/default price, Customer, Supplier, Pricing.
4. **Wave D — Operational Transactions:** Purchase -> Receiving; Inventory; Sales Order -> Availability.
5. **Wave E — Baseline NFR:** NFS/verification được viết song song từ Wave A, không chờ implementation xong.

## 9. P1 exit criteria

P1 chỉ được coi là complete khi:

1. Hai business endpoint Purchase->Receiving và Sales Order->Availability chạy end-to-end với Company ownership đúng.
2. Inventory balance/movement/adjustment reconcile được.
3. Authorization, sensitive-data protection và Central Audit áp dụng được cho các transaction P1.
4. Master-data lifecycle/approval cần cho Product/Customer/Supplier/Pricing hoạt động.
5. Baseline NFR P1 có NFS và verification evidence đạt acceptance.
6. Không phát sinh expectation rằng P1 đã cung cấp AR/AP, statutory invoice/tax, close hoặc full financial posting.

## 10. Phase Planning approval decision

Baseline này được **approved** với giả định P1 là **core/pilot increment**, không phải first statutory production go-live. Nếu sau này first release được đổi thành bắt buộc chạy kế toán/thuế/thanh toán thực tế, các Finance items tương ứng phải được promote lại P1 bằng một controlled revision và dependency closure phải được validate lại.

Hành động tiếp theo là đồng bộ `target_phase` trở lại User Requirements ở revision kế tiếp (`REQ-ERP-001 v1.0.2`) trước khi bắt đầu decomposition P1 sang FRS/NFS/DES/UAT.

## 11. Change log

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-10-02 | Initial allocation: P1 62, P2 44, P3 5. |
| 1.0.1 | 2026-10-02 | Approved baseline: reduced P1 to minimum operational core (42); redistributed finance/governance/specialized lanes while preserving dependency closure. |
