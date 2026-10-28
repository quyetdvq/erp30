# P2 Module SUP — Supplier & History (Functional + Technical Specification)

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Scope & mandatory requirements (verbatim UR)

Các phần dưới đây trích nguyên văn để file module **tự đọc và nghiệm thu độc lập**. Nội dung domain design từ mục 2 trở đi được đề xuất và cần phê duyệt.

### UR-SUP-001 - Supplier Master
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

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
**Status:** BASELINE

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


## 2. Design-level functional decomposition (P2-DRAFT)

## Domain behavior

1. Supplier Group shared, globally unique code; `All Companies` or explicit Company allowlist. PO mutation must check Supplier ACTIVE and usable in PO Company; history of a discontinued Supplier remains intact.
2. Support fields: Supplier Group, legal/tax, phone/email, address+Province/Ward, bank account, payment term. Payment Term normative field exists, but catalog/rules (net days/instalments) not defined; do not invent accounting engine.
3. Extend existing polymorphic Contacts for owner SUPPLIER in P2 via reviewable owner resolver, not separate Contact duplicate. Supplier history assembles PO, GR, Purchase Return and Money Expense links restricted by Company and individual resource permission.
4. Bank account and credential-like Supplier fields must not appear in debug traces or unauthorized exports. P1 resource/action PBAC protects `supplier.view/edit/export`; field-level masking as new capability is not assumed. If sensitive field needs extra authorization, record additive decision.

## Commands/queries

- `SupplierMutation`: Group catalog uniqueness, Company allowlist, status and actor audit.
- `SupplierUsabilityGuard(company, supplier, date)`: Company usage + active + authorization.
- `SupplierHistoryReader(actor,companyScope)`: SQL scope source joins + per-resource link guard.

## Negative tests

Supplier of Company A cannot be used by Company B outside usage; status inactive blocks new PO but not existing GR history; bank field excluded from unauthorized request/export/log; supplier contacts reuse generic ownership; payment term invalid -> validation.

## Decisions

Supplier Group taxonomy, payment term catalog/effective policy, banking data retention/encryption, source history pagination, usage effective dating.

## 3. Proposed database entities (P2-DRAFT)

| Entity | Purpose | Important consistency constraint |
|---|---|---|
| `suppliers` | Shared supplier master | UNIQUE(supplier_code); INDEX(status,all_companies) |
| `supplier_company_usages` | Supplier company allowlist | UNIQUE(supplier_id,company_organization_id) |
| `supplier_notes` | Supplier history note | INDEX(supplier_id,happened_at) |

## 4. Proposed REST API operations (P2-DRAFT)

| Method | Path | Candidate Resource.Action |
|---|---|---|
| GET | `/api/v1/suppliers` | `supplier.view` |
| GET | `/api/v1/suppliers/{id}` | `supplier.view` |
| POST | `/api/v1/suppliers` | `supplier.create` |
| PATCH | `/api/v1/suppliers/{id}` | `supplier.edit` |
| POST | `/api/v1/suppliers/{id}/actions/set-state` | `supplier.setstate` |
| GET | `/api/v1/suppliers/{id}/history` | `supplier.view` |
| POST | `/api/v1/suppliers/{id}/notes` | `supplier.edit` |
| GET | `/api/v1/suppliers/{id}/company-usages` | `supplier.view` |
| PUT | `/api/v1/suppliers/{id}/company-usages` | `supplier.edit` |

## 5. Proposed frontend screens (P2-DRAFT)

| UI ID | Navigation | Screen behavior |
|---|---|---|
| `P2-UI-SUP-01` | `/suppliers` | Group master, Company scope, notes, Purchase and finance history |

## 6. Complete acceptance registry (BASELINE criteria)

| FR | Acceptance criterion (source wording) | Test ID |
|---|---|---|
| `P2-FR-SUP-001-01` | Supplier Code là duy nhất trong Group. | `P2-AT-SUP-001-01` |
| `P2-FR-SUP-001-02` | Supplier có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng. | `P2-AT-SUP-001-02` |
| `P2-FR-SUP-001-03` | Company ngoài Supplier Usage Scope không được dùng Supplier cho giao dịch mới. | `P2-AT-SUP-001-03` |
| `P2-FR-SUP-001-04` | Khi triển khai Supplier ở P2, Supplier có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Supplier. | `P2-AT-SUP-001-04` |
| `P2-FR-SUP-001-05` | Supplier inactive không được chọn cho giao dịch mới nhưng lịch sử vẫn tra cứu được. | `P2-AT-SUP-001-05` |
| `P2-FR-SUP-002-01` | Chỉ hiển thị lịch sử thuộc Company nằm trong authorization scope của User. | `P2-AT-SUP-002-01` |
| `P2-FR-SUP-002-02` | Có thể điều hướng từ Supplier tới chứng từ nguồn liên quan khi User có quyền VIEW chứng từ đó. | `P2-AT-SUP-002-02` |
| `P2-FR-SUP-002-03` | Lịch sử vẫn tra cứu được khi Supplier đã inactive. | `P2-AT-SUP-002-03` |

## 7. Domain test/approval handoff

- Trước thiết kế DB/API/UI: freeze OPEN-DECISION liên quan, lifecycle, permission, Company authority, immutable posting semantics.
- Trước code: review migration forward-only, tests negative/cross-company/idempotency/rollback và consistency với các P1 services.
- Trước release: chạy runtime trên DEV clone (CI4 + MariaDB + frontend), log test IDs và proof; không nhận PASS chỉ vì có file tài liệu hoặc route.

**State:** toàn bộ đề xuất DB/API/FE của module chưa tồn tại như nghiệp vụ P2 trong v1.9.0. Requirement P2 gốc trong §1 vẫn `BASELINE`.
