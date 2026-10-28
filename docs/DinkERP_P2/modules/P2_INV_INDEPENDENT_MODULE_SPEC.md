# P2 Module INV — Inventory, Serial, Reservation (Functional + Technical Specification)

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

### UR-INV-001 - Inventory Balance
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Theo dõi tồn theo:

**Company + Warehouse + Product**

và Serial khi áp dụng. Inventory balance canonical được hiểu theo Base UOM của Product; giao dịch có thể nhập bằng source UOM hợp lệ và quy đổi về Base UOM.

Các quantity vận hành tối thiểu:
- On-hand Qty
- Reserved Qty
- Available Qty = On-hand Qty - Reserved Qty

**Acceptance criteria**
- Có thể xem On-hand, Reserved và Available theo từng Warehouse.
- Inventory balance chỉ tồn tại cho SIMPLE/CHILD leaf; COMBO không có On-hand/Reserved/Available riêng.
- On-hand chỉ phản ánh stock movement đã được post/confirm theo posting rule.
- Có thể xem tồn Group bằng tổng hợp Company được phép.
- Không trộn tồn giữa các Company.

### UR-INV-002 - Inventory Movement
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Hỗ trợ:
- Goods Receipt
- Sales Issue
- Transfer
- Adjustment
- Purchase Return
- Sales Return
- Reversal khi áp dụng

Mỗi movement lưu:
- Company
- Warehouse
- Product
- Source UOM
- Source Quantity
- Base Quantity quy đổi
- Serial khi áp dụng
- Source Document
- User
- Timestamp
- Posting Status

**Acceptance criteria**
- Chỉ movement ở posting state (`POSTED` hoặc module-defined `CONFIRMED` tương đương posting) mới làm thay đổi On-hand Qty.
- Inventory movement chỉ post SIMPLE/CHILD leaf; nếu source document sử dụng COMBO thì phải flatten trước khi tạo/post stock movement.
- Draft/Pending movement không làm thay đổi On-hand Qty.
- Movement đã post không được delete hoặc sửa trực tiếp quantity/product/warehouse làm thay đổi stock history.
- Correction sau posting phải được thực hiện bằng reversal hoặc Inventory Adjustment có traceability.
- Mỗi posted movement truy được Source Document, User và Timestamp.

### UR-INV-003 - Warehouse Transfer
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Cho phép chuyển hàng giữa Warehouse trong cùng Company.

**Acceptance criteria**
- Source và destination Warehouse phải cùng Company.
- Cross-Company movement không được xử lý như internal warehouse transfer.
- Chỉ Transfer đã đạt posting state mới làm thay đổi stock location/balance.
- Posted Transfer phải giữ trace giữa source và destination movement.

### UR-INV-004 - Stock Count và Adjustment
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Hỗ trợ:
- Tạo Stock Count
- Ghi nhận actual quantity
- So sánh system quantity
- Xác nhận variance
- Tạo Inventory Adjustment

**Acceptance criteria**
- Adjustment chỉ được ghi nhận theo PBAC.
- Adjustment chỉ làm thay đổi On-hand khi đạt posting state.
- Có thể truy từ Adjustment về Stock Count và người xác nhận.
- Posted Adjustment không được delete/edit trực tiếp làm thay đổi stock history.


---

### UR-INV-005 - Serial Inventory & Transaction Traceability
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Đối với Product được cấu hình quản lý Serial, Inventory phải theo dõi Serial xuyên suốt các giao dịch vận hành.

**Acceptance criteria**
- Serial xác định được Product, Company, Warehouse hiện tại và trạng thái.
- Serial truy được Goods Receipt, Sales Issue, transfer, purchase return và sales return liên quan.
- Một Serial không thể đồng thời tồn tại ở nhiều Warehouse.
- Goods Receipt của Product serial-managed phải ghi nhận Serial trước khi posting.
- Sales Issue của Product serial-managed phải xác định Serial xuất.
- Warehouse Transfer phải chuyển trạng thái/location của đúng Serial.
- Purchase Return và Sales Return phải tham chiếu đúng Serial khi áp dụng.

### UR-INV-006 - Inventory Reservation
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Inventory Reservation giữ một phần Available Qty cho Sales Order đã được xác nhận mà chưa được consume thành Sales Issue.

Invariant:

**Available Qty = On-hand Qty - Reserved Qty**

Lifecycle cơ bản:

**Reserve -> Release / Consume**

**Acceptance criteria**
- Reservation xác định Company, Warehouse, Product, Reserved Quantity và Source Sales Order/Order Line.
- Reservation chỉ giữ quantity của SIMPLE/CHILD leaf; COMBO phải được flatten trước reservation.
- Reservation được tạo khi Sales Order đạt trạng thái xác nhận vận hành theo lifecycle của module.
- Cancel Order hoặc giảm quantity phải release phần reservation không còn cần.
- Partial shipment/Sales Issue consume phần reservation tương ứng và giữ reservation cho phần chưa xử lý.
- Khi Sales Issue được post, phần reservation được consume đồng thời với stock issue liên quan.
- Reservation không tự làm thay đổi On-hand Qty.
- Baseline không cho reservation mới làm Available Qty âm; trường hợp overselling cần requirement/change request riêng.
- Với Product serial-managed, baseline reserve theo quantity; Serial cụ thể được chọn và validate khi Sales Issue được post.


## 2. Design-level functional decomposition (P2-DRAFT)

## Domain behavior

1. Inventory balance key `Company + Warehouse + Product` (Product must SIMPLE/CHILD). Store On-hand and Reserved with Available=On-hand-Reserved; display base UOM; P1 COMBO/PARENT never stock/serial/price list leaf operations.
2. Immutable signed **posted** movement ledger, source document type/ID, user/time, Company, Warehouse, product, UOM source and base Qty; draft/pending movements do not alter balance. Reversal/Adjustment is the only approved method after posting.
3. Reservation lifecycle: `Reserve → Release / Consume`. Created at operational Sales Order confirmed state, quantity-only for serial-managed Product; consumes concurrently with posted Sales Issue. New reservations cannot make Available negative; cancel/reduction releases, partial issue consumes only issued part.
4. Goods Receipt posted adds stock; Sales Issue posted subtracts; Transfer posted writes paired deltas in same Company; Adjustment after approved count posts variance. Never cross Company warehouse transfer, which requires a different business design not in P2 baseline.
5. Serial uniqueness per Product; serial current Company/Warehouse/status, linked to each movement. No simultaneous two warehouses; serial chosen at Sales Issue posting, not reservation; return traces source serial, validate same serial.
6. Stock Count snapshot of system on-hand, user actual qty and variance; confirmed variance may produce Adjustment; performing count alone changes no stock.
7. All stock-increasing/decreasing operations are transactional with unique source posting keys, lock by `(company,warehouse,product)` canonical order; atomic ledger+balance+serial updates.

## Transaction algebra and reconciliation

```
OnHand[c,w,p] = Opening + SUM(POSTED movement.base_qty_delta)
Reserved[c,w,p] = SUM(max(0, reserved_qty - consumed_qty - released_qty))
Available[c,w,p] = OnHand[c,w,p] - Reserved[c,w,p]
```

Opening stock bootstrap, stock valuation and historical cash/cost are **not provided by current P2 UR**, so initialization requires explicit policy. Two positive/negative deltas for intra-company Transfer sum to zero across warehouses.

## Failure/rollback tests

Parallel confirm orders on same SKU insufficient available; cancel + issue competing locks; duplicate posting key; posted movement edit/delete; transfer cross-Company; missing serial or serial in wrong warehouse; stock count when stock moved during count; COMBO ledger row; offset pagination before SQL scope.

## Decisions

Opening stock/migration, movement event catalog, negative on-hand policy for authorized adjustments, count freeze/recount policy, lot/expiry outside baseline, serial normalization, reservation identity and partial shipment mapping.

## 3. Proposed database entities (P2-DRAFT)

| Entity | Purpose | Important consistency constraint |
|---|---|---|
| `inventory_movements` | Immutable stock ledger when POSTED | UNIQUE(posting_key); INDEX(company_id,warehouse_id,product_id,posted_at) |
| `inventory_balances` | Materialized stock by Company Warehouse Product | UNIQUE(company_id,warehouse_id,product_id) |
| `inventory_serials` | Serial state & current location | UNIQUE(product_id,serial_code); INDEX(company_id,warehouse_id,status) |
| `inventory_movement_serials` | Serial movement trace | UNIQUE(inventory_movement_id,serial_id) |
| `inventory_reservations` | Leaf reservation from confirmed SO | UNIQUE(reservation_key); INDEX(company_id,warehouse_id,product_id,status) |
| `warehouse_transfers` | Warehouse Transfer document | UNIQUE(company_id,transfer_number) |
| `warehouse_transfer_lines` | Transfer line | INDEX(transfer_id) |
| `stock_counts` | Stock Count header | UNIQUE(company_id,count_number) |
| `stock_count_lines` | Stock Count line | UNIQUE(stock_count_id,product_id) |
| `inventory_adjustments` | Stock adjustment header | UNIQUE(company_id,adjustment_number) |
| `inventory_adjustment_lines` | Adjustment line | INDEX(adjustment_id) |

## 4. Proposed REST API operations (P2-DRAFT)

| Method | Path | Candidate Resource.Action |
|---|---|---|
| GET | `/api/v1/inventory-balances` | `inventory_balance.view` |
| GET | `/api/v1/inventory-balances/{id}` | `inventory_balance.view` |
| GET | `/api/v1/inventory-movements` | `inventory_movement.view` |
| GET | `/api/v1/inventory-movements/{id}` | `inventory_movement.view` |
| GET | `/api/v1/inventory-serials` | `inventory_serial.view` |
| GET | `/api/v1/inventory-serials/{id}` | `inventory_serial.view` |
| GET | `/api/v1/inventory-reservations` | `inventory_reservation.view` |
| GET | `/api/v1/inventory-reservations/{id}` | `inventory_reservation.view` |
| GET | `/api/v1/warehouse-transfers` | `warehouse_transfer.view` |
| GET | `/api/v1/warehouse-transfers/{id}` | `warehouse_transfer.view` |
| POST | `/api/v1/warehouse-transfers` | `warehouse_transfer.create` |
| PATCH | `/api/v1/warehouse-transfers/{id}` | `warehouse_transfer.edit` |
| POST | `/api/v1/warehouse-transfers/{id}/actions/post` | `warehouse_transfer.post` |
| POST | `/api/v1/warehouse-transfers/{id}/actions/cancel` | `warehouse_transfer.cancel` |
| GET | `/api/v1/stock-counts` | `stock_count.view` |
| GET | `/api/v1/stock-counts/{id}` | `stock_count.view` |
| POST | `/api/v1/stock-counts` | `stock_count.create` |
| PATCH | `/api/v1/stock-counts/{id}` | `stock_count.edit` |
| POST | `/api/v1/stock-counts/{id}/actions/submit` | `stock_count.submit` |
| POST | `/api/v1/stock-counts/{id}/actions/approve` | `stock_count.approve` |
| POST | `/api/v1/stock-counts/{id}/actions/cancel` | `stock_count.cancel` |
| GET | `/api/v1/inventory-adjustments` | `inventory_adjustment.view` |
| GET | `/api/v1/inventory-adjustments/{id}` | `inventory_adjustment.view` |
| POST | `/api/v1/inventory-adjustments` | `inventory_adjustment.create` |
| PATCH | `/api/v1/inventory-adjustments/{id}` | `inventory_adjustment.edit` |
| POST | `/api/v1/inventory-adjustments/{id}/actions/post` | `inventory_adjustment.post` |
| POST | `/api/v1/inventory-adjustments/{id}/actions/cancel` | `inventory_adjustment.cancel` |
| GET | `/api/v1/inventory-balances/reconciliation` | `inventory_balance.view` |

## 5. Proposed frontend screens (P2-DRAFT)

| UI ID | Navigation | Screen behavior |
|---|---|---|
| `P2-UI-INV-01` | `/inventory/balances` | On-hand/Reserved/Available by warehouse and product |
| `P2-UI-INV-02` | `/inventory/movements` | Source transaction, posting, reversal trail |
| `P2-UI-INV-03` | `/inventory/serials` | Current company/warehouse/status and movement trail |
| `P2-UI-INV-04` | `/inventory/reservations` | Reserve/release/consume quantities and source SO |
| `P2-UI-INV-05` | `/inventory/transfers` | Two warehouses, leaves, serial list, post |
| `P2-UI-INV-06` | `/inventory/stock-counts` | Snapshot system qty, actual qty, variance, confirm |
| `P2-UI-INV-07` | `/inventory/adjustments` | Adjustment based on Count or reason, post |

## 6. Complete acceptance registry (BASELINE criteria)

| FR | Acceptance criterion (source wording) | Test ID |
|---|---|---|
| `P2-FR-INV-001-01` | Có thể xem On-hand, Reserved và Available theo từng Warehouse. | `P2-AT-INV-001-01` |
| `P2-FR-INV-001-02` | Inventory balance chỉ tồn tại cho SIMPLE/CHILD leaf; COMBO không có On-hand/Reserved/Available riêng. | `P2-AT-INV-001-02` |
| `P2-FR-INV-001-03` | On-hand chỉ phản ánh stock movement đã được post/confirm theo posting rule. | `P2-AT-INV-001-03` |
| `P2-FR-INV-001-04` | Có thể xem tồn Group bằng tổng hợp Company được phép. | `P2-AT-INV-001-04` |
| `P2-FR-INV-001-05` | Không trộn tồn giữa các Company. | `P2-AT-INV-001-05` |
| `P2-FR-INV-002-01` | Chỉ movement ở posting state (`POSTED` hoặc module-defined `CONFIRMED` tương đương posting) mới làm thay đổi On-hand Qty. | `P2-AT-INV-002-01` |
| `P2-FR-INV-002-02` | Inventory movement chỉ post SIMPLE/CHILD leaf; nếu source document sử dụng COMBO thì phải flatten trước khi tạo/post stock movement. | `P2-AT-INV-002-02` |
| `P2-FR-INV-002-03` | Draft/Pending movement không làm thay đổi On-hand Qty. | `P2-AT-INV-002-03` |
| `P2-FR-INV-002-04` | Movement đã post không được delete hoặc sửa trực tiếp quantity/product/warehouse làm thay đổi stock history. | `P2-AT-INV-002-04` |
| `P2-FR-INV-002-05` | Correction sau posting phải được thực hiện bằng reversal hoặc Inventory Adjustment có traceability. | `P2-AT-INV-002-05` |
| `P2-FR-INV-002-06` | Mỗi posted movement truy được Source Document, User và Timestamp. | `P2-AT-INV-002-06` |
| `P2-FR-INV-003-01` | Source và destination Warehouse phải cùng Company. | `P2-AT-INV-003-01` |
| `P2-FR-INV-003-02` | Cross-Company movement không được xử lý như internal warehouse transfer. | `P2-AT-INV-003-02` |
| `P2-FR-INV-003-03` | Chỉ Transfer đã đạt posting state mới làm thay đổi stock location/balance. | `P2-AT-INV-003-03` |
| `P2-FR-INV-003-04` | Posted Transfer phải giữ trace giữa source và destination movement. | `P2-AT-INV-003-04` |
| `P2-FR-INV-004-01` | Adjustment chỉ được ghi nhận theo PBAC. | `P2-AT-INV-004-01` |
| `P2-FR-INV-004-02` | Adjustment chỉ làm thay đổi On-hand khi đạt posting state. | `P2-AT-INV-004-02` |
| `P2-FR-INV-004-03` | Có thể truy từ Adjustment về Stock Count và người xác nhận. | `P2-AT-INV-004-03` |
| `P2-FR-INV-004-04` | Posted Adjustment không được delete/edit trực tiếp làm thay đổi stock history. | `P2-AT-INV-004-04` |
| `P2-FR-INV-005-01` | Serial xác định được Product, Company, Warehouse hiện tại và trạng thái. | `P2-AT-INV-005-01` |
| `P2-FR-INV-005-02` | Serial truy được Goods Receipt, Sales Issue, transfer, purchase return và sales return liên quan. | `P2-AT-INV-005-02` |
| `P2-FR-INV-005-03` | Một Serial không thể đồng thời tồn tại ở nhiều Warehouse. | `P2-AT-INV-005-03` |
| `P2-FR-INV-005-04` | Goods Receipt của Product serial-managed phải ghi nhận Serial trước khi posting. | `P2-AT-INV-005-04` |
| `P2-FR-INV-005-05` | Sales Issue của Product serial-managed phải xác định Serial xuất. | `P2-AT-INV-005-05` |
| `P2-FR-INV-005-06` | Warehouse Transfer phải chuyển trạng thái/location của đúng Serial. | `P2-AT-INV-005-06` |
| `P2-FR-INV-005-07` | Purchase Return và Sales Return phải tham chiếu đúng Serial khi áp dụng. | `P2-AT-INV-005-07` |
| `P2-FR-INV-006-01` | Reservation xác định Company, Warehouse, Product, Reserved Quantity và Source Sales Order/Order Line. | `P2-AT-INV-006-01` |
| `P2-FR-INV-006-02` | Reservation chỉ giữ quantity của SIMPLE/CHILD leaf; COMBO phải được flatten trước reservation. | `P2-AT-INV-006-02` |
| `P2-FR-INV-006-03` | Reservation được tạo khi Sales Order đạt trạng thái xác nhận vận hành theo lifecycle của module. | `P2-AT-INV-006-03` |
| `P2-FR-INV-006-04` | Cancel Order hoặc giảm quantity phải release phần reservation không còn cần. | `P2-AT-INV-006-04` |
| `P2-FR-INV-006-05` | Partial shipment/Sales Issue consume phần reservation tương ứng và giữ reservation cho phần chưa xử lý. | `P2-AT-INV-006-05` |
| `P2-FR-INV-006-06` | Khi Sales Issue được post, phần reservation được consume đồng thời với stock issue liên quan. | `P2-AT-INV-006-06` |
| `P2-FR-INV-006-07` | Reservation không tự làm thay đổi On-hand Qty. | `P2-AT-INV-006-07` |
| `P2-FR-INV-006-08` | Baseline không cho reservation mới làm Available Qty âm; trường hợp overselling cần requirement/change request riêng. | `P2-AT-INV-006-08` |
| `P2-FR-INV-006-09` | Với Product serial-managed, baseline reserve theo quantity; Serial cụ thể được chọn và validate khi Sales Issue được post. | `P2-AT-INV-006-09` |

## 7. Domain test/approval handoff

- Trước thiết kế DB/API/UI: freeze OPEN-DECISION liên quan, lifecycle, permission, Company authority, immutable posting semantics.
- Trước code: review migration forward-only, tests negative/cross-company/idempotency/rollback và consistency với các P1 services.
- Trước release: chạy runtime trên DEV clone (CI4 + MariaDB + frontend), log test IDs và proof; không nhận PASS chỉ vì có file tài liệu hoặc route.

**State:** toàn bộ đề xuất DB/API/FE của module chưa tồn tại như nghiệp vụ P2 trong v1.9.0. Requirement P2 gốc trong §1 vẫn `BASELINE`.
