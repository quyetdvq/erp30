# P2 Module FIN — Operational Finance (Functional + Technical Specification)

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

### UR-FIN-001 - Ghi nhận Thu/Chi
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Finance dùng thuật ngữ **Money Receipt** cho giao dịch Thu và **Money Expense** cho giao dịch Chi để phân biệt với Goods Receipt.

Mỗi giao dịch gồm:
- Company
- Date
- Money Receipt/Money Expense Type
- Amount
- Cash/Bank Account
- Related Customer/Supplier/Employee khi có
- Description
- Source Reference
- Attachment
- Recorded By
- Posting Status

**Acceptance criteria**
- Mỗi Money Receipt/Money Expense thuộc đúng một Company.
- Amount phải lớn hơn 0.
- Account sử dụng phải thuộc cùng Company với transaction.
- Chỉ transaction đã `POSTED` mới tham gia operational balance.
- Transaction đã post không được xóa/sửa trực tiếp amount/account làm thay đổi history; correction phải qua reversal/adjustment có traceability.

### UR-FIN-002 - Danh mục Thu/Chi
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Cho phép cấu hình category như:
- Thu bán hàng
- Thu COD
- Thu khác
- Thanh toán Supplier
- Chi vận chuyển
- Chi lương
- Chi văn phòng
- Chi khác

**Acceptance criteria**
- Category xác định được loại `RECEIPT` hoặc `EXPENSE`.
- Category inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
- Có thể lọc/tổng hợp Thu/Chi theo category.

### UR-FIN-003 - Cash và Bank Account
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Quản lý danh sách:
- Cash Fund
- Bank Account

Mỗi account có tối thiểu:
- Company
- Opening Balance
- Opening Date
- Status

**Acceptance criteria**
- Mỗi account thuộc một Company.
- Opening Balance và Opening Date được xác định trước khi tính operational balance từ thời điểm đó.
- User chỉ thao tác account trong PBAC scope.
- Account inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.

### UR-FIN-004 - Tổng hợp Thu/Chi
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Operational Balance được tính theo công thức:

**Balance(t) = Opening Balance + Sum(Posted Money Receipts) - Sum(Posted Money Expenses)**

trong khoảng từ Opening Date đến thời điểm `t`.

Báo cáo:
- Total Money Receipt
- Total Money Expense
- Receipt - Expense
- Opening Balance
- Closing Balance

Filter:
- Company
- Date Range
- Category
- Cash/Bank Account
- Customer
- Supplier

**Acceptance criteria**
- Chỉ posted Money Receipt/Money Expense được tính vào operational balance.
- Opening/Closing Balance không suy diễn từ General Ledger.
- Có thể tính balance theo từng Cash/Bank Account và tổng hợp các account trong Company theo filter.
- Report theo nhiều Company chỉ tổng hợp Company User có quyền xem.
- Operational Balance không được mô tả như bank reconciliation hoặc statutory accounting balance.

### UR-FIN-005 - Liên kết nguồn giao dịch
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Thu/Chi có thể liên kết với:
- Sales Order
- Shipment/COD
- Purchase Order
- Supplier
- Customer
- Payroll

Liên kết phục vụ traceability/tổng hợp, không tạo double-entry accounting.

**Acceptance criteria**
- Có thể truy từ Money Receipt/Money Expense về Source Reference khi nguồn còn tồn tại và User có quyền.
- Một source reference không tự tạo double-entry journal.
- COD đã reconciliation có thể tạo/liên kết Money Receipt mà vẫn giữ Shipment/COD reference.
- Payroll payment có thể liên kết Money Expense theo đúng Company.


---


## 2. Design-level functional decomposition (P2-DRAFT)

## Domain behavior

1. Mini ERP Finance quản lý **operational Money Receipt / Money Expense**, **không** triển khai General Ledger/Journal/double-entry, AP/AR legal accounting. Tất cả money transaction owned by exactly one Company.
2. Cash Fund / Bank Account thuộc Company, có OpeningBalance+OpeningDate+Status. Category type RECEIPT/EXPENSE, inactive không chọn new; Account Company phải trùng transaction Company. Money amount >0, business DATE Company timezone, actor/timestamp.
3. Status DRAFT/PENDING/POSTED according to module lifecycle; only POSTED enters operational cash balance. Posted records cannot edit amount/account, must correct via explicit reversing transaction with origin ref, preserving audit trail.
4. At time t and Account A: `Balance(t) = Opening Balance + Sum(POSTED receipts from opening date to t) − Sum(POSTED expenses from opening date to t)`; do not derive from GL nor from merely reconciled COD.
5. Support Company/date/category/account/customer/supplier filters and authorized aggregation. Source refs to Sales Order/Shipment/COD/PO/Customer/Supplier/Payroll P3 only link, no implicit extra entry. History after inactive account remains readable.
6. COD match can create/link Money Receipt after explicit finance action and PBAC; idempotent source key is design proposal. Receipt of same carrier transfer cannot be double counted.

## Example

Account opening 1,000,000 VND; posted Receipt 300,000; draft Receipt 500,000; posted Expense 50,000 → closing operational balance 1,250,000, not 1,750,000. Company B receipts never contribute to Company A report.

## Negative tests

Posting twice; backdated before opening date; account Company mismatch; negative/zero amount; inactive category on create; out-of-scope aggregation; posted edit/delete; status COD RECONCILED without posted Money Receipt; source link to inaccessible Company leaks URL.

## Decisions

Receipt/Expense reversal taxonomy, allowed backdating/locked period, negative balance permitted, bank account exposure, opening balance corrections after posting, multicurrency explicitly out of scope, settlement/chargeback handling.

## 3. Proposed database entities (P2-DRAFT)

| Entity | Purpose | Important consistency constraint |
|---|---|---|
| `cash_bank_accounts` | Cash Fund and Bank Account master | UNIQUE(company_id,account_code) |
| `money_categories` | Operational receipt/expense categories | UNIQUE(category_code) |
| `money_transactions` | Money Receipt/Expense immutable post | UNIQUE(company_id,transaction_number); INDEX(company_id,account_id,business_date,status) |
| `money_source_links` | Finance source references | INDEX(source_type,source_id); INDEX(money_transaction_id) |

## 4. Proposed REST API operations (P2-DRAFT)

| Method | Path | Candidate Resource.Action |
|---|---|---|
| GET | `/api/v1/money-accounts` | `money_account.view` |
| GET | `/api/v1/money-accounts/{id}` | `money_account.view` |
| POST | `/api/v1/money-accounts` | `money_account.create` |
| PATCH | `/api/v1/money-accounts/{id}` | `money_account.edit` |
| POST | `/api/v1/money-accounts/{id}/actions/set-state` | `money_account.setstate` |
| GET | `/api/v1/money-categories` | `money_category.view` |
| GET | `/api/v1/money-categories/{id}` | `money_category.view` |
| POST | `/api/v1/money-categories` | `money_category.create` |
| PATCH | `/api/v1/money-categories/{id}` | `money_category.edit` |
| POST | `/api/v1/money-categories/{id}/actions/set-state` | `money_category.setstate` |
| GET | `/api/v1/money-receipts` | `money_receipt.view` |
| GET | `/api/v1/money-receipts/{id}` | `money_receipt.view` |
| POST | `/api/v1/money-receipts` | `money_receipt.create` |
| PATCH | `/api/v1/money-receipts/{id}` | `money_receipt.edit` |
| POST | `/api/v1/money-receipts/{id}/actions/post` | `money_receipt.post` |
| POST | `/api/v1/money-receipts/{id}/actions/reverse` | `money_receipt.reverse` |
| GET | `/api/v1/money-expenses` | `money_expense.view` |
| GET | `/api/v1/money-expenses/{id}` | `money_expense.view` |
| POST | `/api/v1/money-expenses` | `money_expense.create` |
| PATCH | `/api/v1/money-expenses/{id}` | `money_expense.edit` |
| POST | `/api/v1/money-expenses/{id}/actions/post` | `money_expense.post` |
| POST | `/api/v1/money-expenses/{id}/actions/reverse` | `money_expense.reverse` |
| GET | `/api/v1/money-reports/balance` | `money_report.view` |
| GET | `/api/v1/money-reports/transactions` | `money_report.view` |

## 5. Proposed frontend screens (P2-DRAFT)

| UI ID | Navigation | Screen behavior |
|---|---|---|
| `P2-UI-FIN-01` | `/finance/accounts` | Cash Fund/Bank Account opening date/balance |
| `P2-UI-FIN-02` | `/finance/categories` | RECEIPT/EXPENSE catalog |
| `P2-UI-FIN-03` | `/finance/receipts` | Company/account/category/source/amount/post/correction |
| `P2-UI-FIN-04` | `/finance/expenses` | Company/account/category/source/amount/post/correction |
| `P2-UI-FIN-05` | `/finance/reports` | Opening+Receipts-Expenses, filtered company/date/account |

## 6. Complete acceptance registry (BASELINE criteria)

| FR | Acceptance criterion (source wording) | Test ID |
|---|---|---|
| `P2-FR-FIN-001-01` | Mỗi Money Receipt/Money Expense thuộc đúng một Company. | `P2-AT-FIN-001-01` |
| `P2-FR-FIN-001-02` | Amount phải lớn hơn 0. | `P2-AT-FIN-001-02` |
| `P2-FR-FIN-001-03` | Account sử dụng phải thuộc cùng Company với transaction. | `P2-AT-FIN-001-03` |
| `P2-FR-FIN-001-04` | Chỉ transaction đã `POSTED` mới tham gia operational balance. | `P2-AT-FIN-001-04` |
| `P2-FR-FIN-001-05` | Transaction đã post không được xóa/sửa trực tiếp amount/account làm thay đổi history; correction phải qua reversal/adjustment có traceability. | `P2-AT-FIN-001-05` |
| `P2-FR-FIN-002-01` | Category xác định được loại `RECEIPT` hoặc `EXPENSE`. | `P2-AT-FIN-002-01` |
| `P2-FR-FIN-002-02` | Category inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được. | `P2-AT-FIN-002-02` |
| `P2-FR-FIN-002-03` | Có thể lọc/tổng hợp Thu/Chi theo category. | `P2-AT-FIN-002-03` |
| `P2-FR-FIN-003-01` | Mỗi account thuộc một Company. | `P2-AT-FIN-003-01` |
| `P2-FR-FIN-003-02` | Opening Balance và Opening Date được xác định trước khi tính operational balance từ thời điểm đó. | `P2-AT-FIN-003-02` |
| `P2-FR-FIN-003-03` | User chỉ thao tác account trong PBAC scope. | `P2-AT-FIN-003-03` |
| `P2-FR-FIN-003-04` | Account inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được. | `P2-AT-FIN-003-04` |
| `P2-FR-FIN-004-01` | Chỉ posted Money Receipt/Money Expense được tính vào operational balance. | `P2-AT-FIN-004-01` |
| `P2-FR-FIN-004-02` | Opening/Closing Balance không suy diễn từ General Ledger. | `P2-AT-FIN-004-02` |
| `P2-FR-FIN-004-03` | Có thể tính balance theo từng Cash/Bank Account và tổng hợp các account trong Company theo filter. | `P2-AT-FIN-004-03` |
| `P2-FR-FIN-004-04` | Report theo nhiều Company chỉ tổng hợp Company User có quyền xem. | `P2-AT-FIN-004-04` |
| `P2-FR-FIN-004-05` | Operational Balance không được mô tả như bank reconciliation hoặc statutory accounting balance. | `P2-AT-FIN-004-05` |
| `P2-FR-FIN-005-01` | Có thể truy từ Money Receipt/Money Expense về Source Reference khi nguồn còn tồn tại và User có quyền. | `P2-AT-FIN-005-01` |
| `P2-FR-FIN-005-02` | Một source reference không tự tạo double-entry journal. | `P2-AT-FIN-005-02` |
| `P2-FR-FIN-005-03` | COD đã reconciliation có thể tạo/liên kết Money Receipt mà vẫn giữ Shipment/COD reference. | `P2-AT-FIN-005-03` |
| `P2-FR-FIN-005-04` | Payroll payment có thể liên kết Money Expense theo đúng Company. | `P2-AT-FIN-005-04` |

## 7. Domain test/approval handoff

- Trước thiết kế DB/API/UI: freeze OPEN-DECISION liên quan, lifecycle, permission, Company authority, immutable posting semantics.
- Trước code: review migration forward-only, tests negative/cross-company/idempotency/rollback và consistency với các P1 services.
- Trước release: chạy runtime trên DEV clone (CI4 + MariaDB + frontend), log test IDs và proof; không nhận PASS chỉ vì có file tài liệu hoặc route.

**State:** toàn bộ đề xuất DB/API/FE của module chưa tồn tại như nghiệp vụ P2 trong v1.9.0. Requirement P2 gốc trong §1 vẫn `BASELINE`.
