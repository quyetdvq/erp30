# P2 Module CUS — Customer & CRM History (Functional + Technical Specification)

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

### UR-CUS-001 - Customer Master
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

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
**Status:** BASELINE

CRM baseline chỉ cần hồ sơ Customer và lịch sử giao dịch/liên hệ.

**Acceptance criteria**
- Lưu được note/lịch sử liên hệ.
- Xem được Sales Order/Shipment/Sales Return/Thu tiền liên quan trong phạm vi quyền.
- Không yêu cầu Lead Management hoặc Opportunity Pipeline.


---


## 2. Design-level functional decomposition (P2-DRAFT)

## Domain behavior

1. Customer là Group shared master duy nhất (code global unique), không nhân bản theo Company. `all_companies` hoặc allowlist cụ thể quyết định Company nào dùng Customer cho transaction **mới**. PBAC actor vẫn là điều kiện độc lập, không lấy Company Usage Scope thay Policy.
2. Customer inactive: không chọn cho Sales Order mới; lịch sử của Company đã sử dụng vẫn đọc trong quyền hiện hành. Đổi usage scope không rewrite hoặc xóa historic sales orders.
3. Province/Ward và shipping Province/Ward tham chiếu P1 master theo code/ID contract. Các địa chỉ dùng `address` detail riêng; validation province/ward hierarchy và status selectable.
4. Contact dùng existing Shared Contact polymorphic, không fork bảng `customer_contacts` nếu chưa có change decision. P1 Contact hiện phục vụ Employee/Organization, việc mở owner CUSTOMER phải có additive owner resolver, authorization adapter và tests.
5. Customer History chứa note/contact log plus read-only tham chiếu Sales Order, Shipment, Sales Return, Money Receipt; câu truy vấn chỉ tổng hợp Company actor được xem. History không đồng nghĩa xây CRM pipeline.
6. Customer Group dùng cho pricing P2, nhưng scope/hierarchy/unique của Group chưa được UR-PRC-001 chốt: proposal có bảng `customer_groups` nhưng phải duyệt trước migration.

## Commands/queries

- `CustomerCreate/Update`: normalize code, Company usage, province/ward; PBAC Group-level shared master; update preserving references.
- `CustomerSelectableLookup(company)`: active + usage membership + PBAC; reject invalid cross-Company selection on SO server-side.
- `CustomerHistoryReader(customer,actor,allowedCompanies)`: SQL scope joins per source, pagination, source links guarded by each source resource view permission.

## Failure/rollback tests

Duplicate global code; all_companies=false with no allowed companies; Company A permitted but B denied; inactivate customer with historical SO; contact owner wrong type; history query negative cross-company; transaction uses inactive master -> fail.

## Decisions

Customer Group semantics, contact owner type P2, usage effective-dating vs current snapshot, duplicate tax code policy, notes privacy, shared Contact rollout.

## 3. Proposed database entities (P2-DRAFT)

| Entity | Purpose | Important consistency constraint |
|---|---|---|
| `customers` | Shared customer master | UNIQUE(customer_code); INDEX(status,all_companies) |
| `customer_groups` | Customer grouping for P2 pricing | UNIQUE(group_code) |
| `customer_company_usages` | Customer company allowlist | UNIQUE(customer_id,company_organization_id) |
| `customer_notes` | CRM contact/note history | INDEX(customer_id,happened_at); INDEX(company_organization_id) |

## 4. Proposed REST API operations (P2-DRAFT)

| Method | Path | Candidate Resource.Action |
|---|---|---|
| GET | `/api/v1/customers` | `customer.view` |
| GET | `/api/v1/customers/{id}` | `customer.view` |
| POST | `/api/v1/customers` | `customer.create` |
| PATCH | `/api/v1/customers/{id}` | `customer.edit` |
| POST | `/api/v1/customers/{id}/actions/set-state` | `customer.setstate` |
| GET | `/api/v1/customer-groups` | `customer.view` |
| GET | `/api/v1/customer-groups/{id}` | `customer.view` |
| POST | `/api/v1/customer-groups` | `customer.create` |
| PATCH | `/api/v1/customer-groups/{id}` | `customer.edit` |
| POST | `/api/v1/customer-groups/{id}/actions/set-state` | `customer.setstate` |
| GET | `/api/v1/customers/{id}/history` | `customer.view` |
| POST | `/api/v1/customers/{id}/notes` | `customer.edit` |
| GET | `/api/v1/customers/{id}/company-usages` | `customer.view` |
| PUT | `/api/v1/customers/{id}/company-usages` | `customer.edit` |

## 5. Proposed frontend screens (P2-DRAFT)

| UI ID | Navigation | Screen behavior |
|---|---|---|
| `P2-UI-CUS-01` | `/customers` | Group master, Company scope, notes/history and shared Contacts |
| `P2-UI-CUS-02` | `/customer-groups` | Group catalog for pricing context |

## 6. Complete acceptance registry (BASELINE criteria)

| FR | Acceptance criterion (source wording) | Test ID |
|---|---|---|
| `P2-FR-CUS-001-01` | Customer Code là duy nhất trong Group. | `P2-AT-CUS-001-01` |
| `P2-FR-CUS-001-02` | Customer có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng. | `P2-AT-CUS-001-02` |
| `P2-FR-CUS-001-03` | Company ngoài Customer Usage Scope không được dùng Customer cho giao dịch mới. | `P2-AT-CUS-001-03` |
| `P2-FR-CUS-001-04` | Khi triển khai Customer ở P2, Customer có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Customer. | `P2-AT-CUS-001-04` |
| `P2-FR-CUS-001-05` | Customer inactive không được chọn cho giao dịch mới nhưng lịch sử giao dịch/liên hệ vẫn tra cứu được. | `P2-AT-CUS-001-05` |
| `P2-FR-CUS-002-01` | Lưu được note/lịch sử liên hệ. | `P2-AT-CUS-002-01` |
| `P2-FR-CUS-002-02` | Xem được Sales Order/Shipment/Sales Return/Thu tiền liên quan trong phạm vi quyền. | `P2-AT-CUS-002-02` |
| `P2-FR-CUS-002-03` | Không yêu cầu Lead Management hoặc Opportunity Pipeline. | `P2-AT-CUS-002-03` |

## 7. Domain test/approval handoff

- Trước thiết kế DB/API/UI: freeze OPEN-DECISION liên quan, lifecycle, permission, Company authority, immutable posting semantics.
- Trước code: review migration forward-only, tests negative/cross-company/idempotency/rollback và consistency với các P1 services.
- Trước release: chạy runtime trên DEV clone (CI4 + MariaDB + frontend), log test IDs và proof; không nhận PASS chỉ vì có file tài liệu hoặc route.

**State:** toàn bộ đề xuất DB/API/FE của module chưa tồn tại như nghiệp vụ P2 trong v1.9.0. Requirement P2 gốc trong §1 vẫn `BASELINE`.
