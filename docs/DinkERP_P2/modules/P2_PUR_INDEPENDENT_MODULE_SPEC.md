# P2 Module PUR — Purchase to Receipt/Return (Functional + Technical Specification)

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

### UR-PUR-001 - Purchase Request
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Cho phép tạo Purchase Request.

Lifecycle cơ bản:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- REJECTED
- CANCELLED

**Acceptance criteria**
- PR có thể Submit/Approve/Reject theo PBAC.
- PR Approved có thể dùng để tạo Purchase Order.

### UR-PUR-002 - Purchase Order
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

PO gồm tối thiểu:
- Company
- Supplier
- Product
- UOM
- Quantity
- Base Quantity quy đổi khi áp dụng
- Unit Price
- Discount
- Tax khi áp dụng
- Expected Receipt Date
- Receiving Warehouse
- Payment Term
- Status

**Acceptance criteria**
- PO tuân theo PBAC theo Company/Resource/State/Action.
- UOM phải hợp lệ cho Product; Quantity phải có thể quy đổi về Base UOM khi sử dụng Alternate UOM của SIMPLE/CHILD.
- COMBO có thể được chọn như template để nhập nhanh Purchase line, nhưng trước receiving/inventory/cost operational processing phải expand về SIMPLE/CHILD; COMBO selling-price allocation không được tự dùng làm purchase-price allocation.
- Purchase price/cost của các leaf sau expansion phải được xác định theo nghiệp vụ Purchase tương ứng, không suy từ COMBO selling price nếu chưa có requirement riêng.
- PO giữ lịch sử phê duyệt và thay đổi quan trọng.

### UR-PUR-003 - Goods Receipt
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Cho phép nhận hàng một phần hoặc nhiều lần bằng chứng từ **Goods Receipt**. `Receiving` được dùng để mô tả process; tên chứng từ canonical là Goods Receipt.

**Acceptance criteria**
- Chỉ Goods Receipt đã đạt posting state của module (`POSTED` hoặc trạng thái `CONFIRMED` được định nghĩa tương đương posting) mới làm tăng On-hand Inventory.
- Goods Receipt cập nhật Received Quantity của Purchase Order nguồn.
- Product serial-managed bắt buộc ghi Serial khi Goods Receipt được post.
- Không nhận vào Warehouse thuộc Company khác Purchase Order.
- Goods Receipt đã post không được delete/edit trực tiếp các dữ liệu làm thay đổi stock; correction phải qua reversal hoặc Inventory Adjustment phù hợp.

### UR-PUR-004 - Purchase Return
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Cho phép trả hàng Supplier.

**Acceptance criteria**
- Chỉ Purchase Return đã đạt posting state mới làm giảm On-hand Inventory.
- Serial-managed item phải xác định đúng Serial trả.
- Có thể truy về Goods Receipt/Purchase Order nguồn.
- Purchase Return đã post không được delete/edit trực tiếp làm thay đổi stock history; correction phải qua reversal/adjustment phù hợp.


---


## 2. Design-level functional decomposition (P2-DRAFT)

## Domain behavior

1. Purchase Request single-step: DRAFT → PENDING_APPROVAL → APPROVED/REJECTED, CANCELLED according to approved lifecycle; only approved PR used to start PO; state/action validity checked independently of PBAC.
2. PO owns one Company, Supplier eligible, destination Warehouse in same Company; lines keep source UOM+source Qty and base Qty, price/discount/tax when applicable, ExpectedReceiptDate, PaymentTerm; approval and mutation history.
3. Optional COMBO as purchase entry template must flatten to SIMPLE/CHILD before receiving; **selling price allocation cannot determine purchase cost**. Purchase leaf price/cost rule must be separate approved design. If not defined, block combo purchase entry instead of implicitly using sale prices.
4. Goods Receipt may be partial and repeated; receiving lines point to PO lines with cumulative Received Qty. **Only POSTED/CONFIRMED defined as posting** creates inventory movement(+); serial-managed goods require serial before posting; no post into wrong Company warehouse.
5. Purchase Return from GR/PO source; posting creates movement(-), validates product/serial and original received quantity; posted GR/return immutable; correction uses reversal/adjustment with link.
6. Preserve approval actor+time, source refs, ledger entries. Bounded row locking avoids race on concurrent partial GR/returns or double-post.

## Transaction boundaries

- `postGoodsReceipt()`: lock GR, PO lines and stock rows; verify remaining qty, serial list, company; create unique posting key ledger, update PO received, balance and serial; set GR status once; commit atomic.
- `postPurchaseReturn()`: lock return/available serials, validate source and remaining returned qty; insert signed movements and status, commit atomic.

## Failure/rollback tests

Concurrent two GR together exceed PO remaining; provider retry or double click posts twice; serial count mismatch; wrong warehouse Company; inactive Supplier selected on new PO; posted GR direct PATCH -> reject; COMBO purchase with unsupported leaf cost -> validation, no silent sale price.

## Decisions

Over-receipt tolerance, partial receipt after PO cancelled/closed, landed cost/tax policy, PO approval exact state, product price/cost of purchase COMBO, return policy, reversal authorization.

## 3. Proposed database entities (P2-DRAFT)

| Entity | Purpose | Important consistency constraint |
|---|---|---|
| `purchase_requests` | Purchase Request header | UNIQUE(company_id,request_number); INDEX(company_id,status) |
| `purchase_request_lines` | PR requested leaves | UNIQUE(purchase_request_id,line_no) |
| `purchase_orders` | PO header | UNIQUE(company_id,po_number); INDEX(company_id,status,supplier_id) |
| `purchase_order_lines` | PO line | UNIQUE(purchase_order_id,line_no) |
| `purchase_request_po_links` | PR to PO trace | UNIQUE(purchase_request_line_id,purchase_order_line_id) |
| `goods_receipts` | Goods Receipt header | UNIQUE(company_id,gr_number); INDEX(company_id,status) |
| `goods_receipt_lines` | Goods Receipt line | INDEX(goods_receipt_id,purchase_order_line_id) |
| `purchase_returns` | Purchase Return header | UNIQUE(company_id,return_number) |
| `purchase_return_lines` | Purchase Return lines | INDEX(purchase_return_id) |

## 4. Proposed REST API operations (P2-DRAFT)

| Method | Path | Candidate Resource.Action |
|---|---|---|
| GET | `/api/v1/purchase-requests` | `purchase_request.view` |
| GET | `/api/v1/purchase-requests/{id}` | `purchase_request.view` |
| POST | `/api/v1/purchase-requests` | `purchase_request.create` |
| PATCH | `/api/v1/purchase-requests/{id}` | `purchase_request.edit` |
| POST | `/api/v1/purchase-requests/{id}/actions/submit` | `purchase_request.submit` |
| POST | `/api/v1/purchase-requests/{id}/actions/approve` | `purchase_request.approve` |
| POST | `/api/v1/purchase-requests/{id}/actions/reject` | `purchase_request.reject` |
| POST | `/api/v1/purchase-requests/{id}/actions/cancel` | `purchase_request.cancel` |
| GET | `/api/v1/purchase-orders` | `purchase_order.view` |
| GET | `/api/v1/purchase-orders/{id}` | `purchase_order.view` |
| POST | `/api/v1/purchase-orders` | `purchase_order.create` |
| PATCH | `/api/v1/purchase-orders/{id}` | `purchase_order.edit` |
| POST | `/api/v1/purchase-orders/{id}/actions/submit` | `purchase_order.submit` |
| POST | `/api/v1/purchase-orders/{id}/actions/approve` | `purchase_order.approve` |
| POST | `/api/v1/purchase-orders/{id}/actions/reject` | `purchase_order.reject` |
| POST | `/api/v1/purchase-orders/{id}/actions/cancel` | `purchase_order.cancel` |
| GET | `/api/v1/goods-receipts` | `goods_receipt.view` |
| GET | `/api/v1/goods-receipts/{id}` | `goods_receipt.view` |
| POST | `/api/v1/goods-receipts` | `goods_receipt.create` |
| PATCH | `/api/v1/goods-receipts/{id}` | `goods_receipt.edit` |
| POST | `/api/v1/goods-receipts/{id}/actions/post` | `goods_receipt.post` |
| POST | `/api/v1/goods-receipts/{id}/actions/cancel` | `goods_receipt.cancel` |
| GET | `/api/v1/purchase-returns` | `purchase_return.view` |
| GET | `/api/v1/purchase-returns/{id}` | `purchase_return.view` |
| POST | `/api/v1/purchase-returns` | `purchase_return.create` |
| PATCH | `/api/v1/purchase-returns/{id}` | `purchase_return.edit` |
| POST | `/api/v1/purchase-returns/{id}/actions/post` | `purchase_return.post` |
| POST | `/api/v1/purchase-returns/{id}/actions/cancel` | `purchase_return.cancel` |
| GET | `/api/v1/purchase-orders/{id}/receipts` | `purchase_order.view` |

## 5. Proposed frontend screens (P2-DRAFT)

| UI ID | Navigation | Screen behavior |
|---|---|---|
| `P2-UI-PUR-01` | `/purchase/requests` | Draft/submit/approve/reject, lines, Company, requester |
| `P2-UI-PUR-02` | `/purchase/orders` | Supplier, prices, UOM, warehouse, link to PR, partial receipts |
| `P2-UI-PUR-03` | `/purchase/goods-receipts` | Partial receive, serial capture, post status |
| `P2-UI-PUR-04` | `/purchase/returns` | Return from GR/PO, serial selection, post |

## 6. Complete acceptance registry (BASELINE criteria)

| FR | Acceptance criterion (source wording) | Test ID |
|---|---|---|
| `P2-FR-PUR-001-01` | PR có thể Submit/Approve/Reject theo PBAC. | `P2-AT-PUR-001-01` |
| `P2-FR-PUR-001-02` | PR Approved có thể dùng để tạo Purchase Order. | `P2-AT-PUR-001-02` |
| `P2-FR-PUR-002-01` | PO tuân theo PBAC theo Company/Resource/State/Action. | `P2-AT-PUR-002-01` |
| `P2-FR-PUR-002-02` | UOM phải hợp lệ cho Product; Quantity phải có thể quy đổi về Base UOM khi sử dụng Alternate UOM của SIMPLE/CHILD. | `P2-AT-PUR-002-02` |
| `P2-FR-PUR-002-03` | COMBO có thể được chọn như template để nhập nhanh Purchase line, nhưng trước receiving/inventory/cost operational processing phải expand về SIMPLE/CHILD; COMBO selling-price allocation không được tự dùng làm purchase-price allocation. | `P2-AT-PUR-002-03` |
| `P2-FR-PUR-002-04` | Purchase price/cost của các leaf sau expansion phải được xác định theo nghiệp vụ Purchase tương ứng, không suy từ COMBO selling price nếu chưa có requirement riêng. | `P2-AT-PUR-002-04` |
| `P2-FR-PUR-002-05` | PO giữ lịch sử phê duyệt và thay đổi quan trọng. | `P2-AT-PUR-002-05` |
| `P2-FR-PUR-003-01` | Chỉ Goods Receipt đã đạt posting state của module (`POSTED` hoặc trạng thái `CONFIRMED` được định nghĩa tương đương posting) mới làm tăng On-hand Inventory. | `P2-AT-PUR-003-01` |
| `P2-FR-PUR-003-02` | Goods Receipt cập nhật Received Quantity của Purchase Order nguồn. | `P2-AT-PUR-003-02` |
| `P2-FR-PUR-003-03` | Product serial-managed bắt buộc ghi Serial khi Goods Receipt được post. | `P2-AT-PUR-003-03` |
| `P2-FR-PUR-003-04` | Không nhận vào Warehouse thuộc Company khác Purchase Order. | `P2-AT-PUR-003-04` |
| `P2-FR-PUR-003-05` | Goods Receipt đã post không được delete/edit trực tiếp các dữ liệu làm thay đổi stock; correction phải qua reversal hoặc Inventory Adjustment phù hợp. | `P2-AT-PUR-003-05` |
| `P2-FR-PUR-004-01` | Chỉ Purchase Return đã đạt posting state mới làm giảm On-hand Inventory. | `P2-AT-PUR-004-01` |
| `P2-FR-PUR-004-02` | Serial-managed item phải xác định đúng Serial trả. | `P2-AT-PUR-004-02` |
| `P2-FR-PUR-004-03` | Có thể truy về Goods Receipt/Purchase Order nguồn. | `P2-AT-PUR-004-03` |
| `P2-FR-PUR-004-04` | Purchase Return đã post không được delete/edit trực tiếp làm thay đổi stock history; correction phải qua reversal/adjustment phù hợp. | `P2-AT-PUR-004-04` |

## 7. Domain test/approval handoff

- Trước thiết kế DB/API/UI: freeze OPEN-DECISION liên quan, lifecycle, permission, Company authority, immutable posting semantics.
- Trước code: review migration forward-only, tests negative/cross-company/idempotency/rollback và consistency với các P1 services.
- Trước release: chạy runtime trên DEV clone (CI4 + MariaDB + frontend), log test IDs và proof; không nhận PASS chỉ vì có file tài liệu hoặc route.

**State:** toàn bộ đề xuất DB/API/FE của module chưa tồn tại như nghiệp vụ P2 trong v1.9.0. Requirement P2 gốc trong §1 vẫn `BASELINE`.
