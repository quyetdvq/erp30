# 15 — P2 End-to-End Traceability (UR→FR→DB→API→UI→Test)

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Referential integrity of documentation

Authoritative P2 UR source: 30 explicit `Target phase:P2` requirements, each `BASELINE`, yielding 147 acceptance bullets. The document generator maps each criterion to `P2-FR-*` and `P2-AT-*`. Downstream DB/API/UI columns are **proposal hints, not approved designs**. The full 147-row CSV is `reference/P2_END_TO_END_TRACEABILITY.csv` (suitable for release checklists/spreadsheets), with direct status `NOT_IMPLEMENTED` and tests `NOT_RUN`.

## 2. Summary coverage

| Module | UR | AC/FR/Test | Primary dependencies |
|---|---:|---:|---|
| DOC — Document & Attachment | 2 | 4 | Attachment polymorphic theo resource; file version immutable; download và search tuân owner PBAC. |
| CUS — Customer & CRM History | 2 | 8 | Group-shared Customer; scope All Companies hoặc allowlist; lịch sử note và business documents trong authorized Company. |
| PRC — Promotion & Extended Pricing | 1 | 6 | Pricing P1 bất biến, P2 thêm điều kiện Customer/Group/Channel; promotion deterministic, không stacking tùy ý. |
| SUP — Supplier & History | 2 | 8 | Group-shared Supplier; Company Usage Scope; shared contacts; history qua Purchase và payments. |
| PUR — Purchase to Receipt/Return | 4 | 16 | Single-Company PR/PO; post Goods Receipt mới tăng stock; return post mới giảm; PO trace không bị phá. |
| INV — Inventory, Serial, Reservation | 6 | 35 | Stock ledger post-only, quantity SIMPLE/CHILD base UOM; reserved != on-hand; serial đơn nhất; Company isolation. |
| SAL — Sales, Marketplace & Shipping | 8 | 49 | Sales price source external/internal, inventory reservation, marketplace idempotency, carrier, COD và sales return. |
| FIN — Operational Finance | 5 | 21 | Money Receipt/Expense từng Company; posted-only balance; không phải GL/double-entry/accounting. |

## 3. Per-UR primary trace (representative endpoint/table/screen)

The link is **representative**, not a one-to-one assertion that one endpoint fulfills all Acceptance Criteria. Consult module doc and full API/UI catalogs for multi-step requirements.

| UR | FRS + Test range | Proposed DB | Proposed API family | Proposed UI | Stage |
|---|---|---|---|---|---|
| `UR-DOC-001` | `P2-FR-DOC-001-01`...`P2-FR-DOC-001-02` (2) | `document_links` | `/api/v1/documents/{id}/links` | `P2-UI-DOC-01` | P2-DRAFT, NOT_RUN |
| `UR-DOC-002` | `P2-FR-DOC-002-01`...`P2-FR-DOC-002-02` (2) | `document_versions` | `/api/v1/documents/{id}/versions` | `P2-UI-DOC-01` | P2-DRAFT, NOT_RUN |
| `UR-CUS-001` | `P2-FR-CUS-001-01`...`P2-FR-CUS-001-05` (5) | `customers` | `/api/v1/customers` | `P2-UI-CUS-01` | P2-DRAFT, NOT_RUN |
| `UR-CUS-002` | `P2-FR-CUS-002-01`...`P2-FR-CUS-002-03` (3) | `customer_notes` | `/api/v1/customers/{id}/history` | `P2-UI-CUS-01` | P2-DRAFT, NOT_RUN |
| `UR-PRC-002` | `P2-FR-PRC-002-01`...`P2-FR-PRC-002-06` (6) | `promotions` | `/api/v1/promotions` | `P2-UI-PRC-01` | P2-DRAFT, NOT_RUN |
| `UR-SUP-001` | `P2-FR-SUP-001-01`...`P2-FR-SUP-001-05` (5) | `suppliers` | `/api/v1/suppliers` | `P2-UI-SUP-01` | P2-DRAFT, NOT_RUN |
| `UR-SUP-002` | `P2-FR-SUP-002-01`...`P2-FR-SUP-002-03` (3) | `supplier_notes` | `/api/v1/suppliers/{id}/history` | `P2-UI-SUP-01` | P2-DRAFT, NOT_RUN |
| `UR-PUR-001` | `P2-FR-PUR-001-01`...`P2-FR-PUR-001-02` (2) | `purchase_requests` | `/api/v1/purchase-requests` | `P2-UI-PUR-01` | P2-DRAFT, NOT_RUN |
| `UR-PUR-002` | `P2-FR-PUR-002-01`...`P2-FR-PUR-002-05` (5) | `purchase_orders` | `/api/v1/purchase-orders` | `P2-UI-PUR-02` | P2-DRAFT, NOT_RUN |
| `UR-PUR-003` | `P2-FR-PUR-003-01`...`P2-FR-PUR-003-05` (5) | `goods_receipts` | `/api/v1/goods-receipts` | `P2-UI-PUR-03` | P2-DRAFT, NOT_RUN |
| `UR-PUR-004` | `P2-FR-PUR-004-01`...`P2-FR-PUR-004-04` (4) | `purchase_returns` | `/api/v1/purchase-returns` | `P2-UI-PUR-04` | P2-DRAFT, NOT_RUN |
| `UR-INV-001` | `P2-FR-INV-001-01`...`P2-FR-INV-001-05` (5) | `inventory_balances` | `/api/v1/inventory-balances` | `P2-UI-INV-01` | P2-DRAFT, NOT_RUN |
| `UR-INV-002` | `P2-FR-INV-002-01`...`P2-FR-INV-002-06` (6) | `inventory_movements` | `/api/v1/inventory-movements` | `P2-UI-INV-02` | P2-DRAFT, NOT_RUN |
| `UR-INV-003` | `P2-FR-INV-003-01`...`P2-FR-INV-003-04` (4) | `warehouse_transfers` | `/api/v1/warehouse-transfers` | `P2-UI-INV-05` | P2-DRAFT, NOT_RUN |
| `UR-INV-004` | `P2-FR-INV-004-01`...`P2-FR-INV-004-04` (4) | `stock_counts` | `/api/v1/stock-counts` | `P2-UI-INV-06` | P2-DRAFT, NOT_RUN |
| `UR-INV-005` | `P2-FR-INV-005-01`...`P2-FR-INV-005-07` (7) | `inventory_serials` | `/api/v1/inventory-serials` | `P2-UI-INV-03` | P2-DRAFT, NOT_RUN |
| `UR-INV-006` | `P2-FR-INV-006-01`...`P2-FR-INV-006-09` (9) | `inventory_reservations` | `/api/v1/inventory-reservations` | `P2-UI-INV-04` | P2-DRAFT, NOT_RUN |
| `UR-SAL-001` | `P2-FR-SAL-001-01`...`P2-FR-SAL-001-08` (8) | `sales_orders` | `/api/v1/sales-orders` | `P2-UI-SAL-01` | P2-DRAFT, NOT_RUN |
| `UR-SAL-002` | `P2-FR-SAL-002-01`...`P2-FR-SAL-002-07` (7) | `marketplace_order_inbox` | `/api/v1/marketplace-orders` | `P2-UI-MKT-02` | P2-DRAFT, NOT_RUN |
| `UR-SAL-003` | `P2-FR-SAL-003-01`...`P2-FR-SAL-003-07` (7) | `marketplace_order_inbox` | `/api/v1/marketplace-orders` | `P2-UI-MKT-02` | P2-DRAFT, NOT_RUN |
| `UR-SAL-004` | `P2-FR-SAL-004-01`...`P2-FR-SAL-004-05` (5) | `inventory_reservations` | `/api/v1/inventory-reservations` | `P2-UI-INV-04` | P2-DRAFT, NOT_RUN |
| `UR-SAL-005` | `P2-FR-SAL-005-01`...`P2-FR-SAL-005-06` (6) | `shipments` | `/api/v1/shipments` | `P2-UI-SHP-01` | P2-DRAFT, NOT_RUN |
| `UR-SAL-006` | `P2-FR-SAL-006-01`...`P2-FR-SAL-006-03` (3) | `cod_reconciliations` | `/api/v1/cod-reconciliations` | `P2-UI-COD-01` | P2-DRAFT, NOT_RUN |
| `UR-SAL-007` | `P2-FR-SAL-007-01`...`P2-FR-SAL-007-05` (5) | `sales_returns` | `/api/v1/sales-returns` | `P2-UI-SAL-04` | P2-DRAFT, NOT_RUN |
| `UR-SAL-008` | `P2-FR-SAL-008-01`...`P2-FR-SAL-008-08` (8) | `marketplace_accounts` | `/api/v1/marketplace-accounts` | `P2-UI-MKT-01` | P2-DRAFT, NOT_RUN |
| `UR-FIN-001` | `P2-FR-FIN-001-01`...`P2-FR-FIN-001-05` (5) | `money_transactions` | `/api/v1/money-receipts` | `P2-UI-FIN-03` | P2-DRAFT, NOT_RUN |
| `UR-FIN-002` | `P2-FR-FIN-002-01`...`P2-FR-FIN-002-03` (3) | `money_categories` | `/api/v1/money-categories` | `P2-UI-FIN-02` | P2-DRAFT, NOT_RUN |
| `UR-FIN-003` | `P2-FR-FIN-003-01`...`P2-FR-FIN-003-04` (4) | `cash_bank_accounts` | `/api/v1/money-accounts` | `P2-UI-FIN-01` | P2-DRAFT, NOT_RUN |
| `UR-FIN-004` | `P2-FR-FIN-004-01`...`P2-FR-FIN-004-05` (5) | `money_transactions` | `/api/v1/money-reports/balance` | `P2-UI-FIN-05` | P2-DRAFT, NOT_RUN |
| `UR-FIN-005` | `P2-FR-FIN-005-01`...`P2-FR-FIN-005-04` (4) | `money_source_links` | `/api/v1/money-receipts` | `P2-UI-FIN-03` | P2-DRAFT, NOT_RUN |

## 4. Source-controlled release maintenance

1. A change to a UR must be a new version/Change ID, never a silent rewrite of source requirements or test expected value.
2. A DB schema change updates Data Dictionary, migration/preflight plan, API response schema, UI and at least affected regression IDs.
3. A new REST operation must have resource/action, Company context, negative tests and generated client; no undocumented routes.
4. A new UI must map to API and server-scope permission; no direct access to unauthorized records.
5. Every `P2-AT` has an executable test and evidence before release sign-off; field `qa_status` changes only with evidence.
6. P1 code refactor must not alter preexisting Product/COMBO/Pricing/PBAC/Workflow/Numbering/Import semantics without approved change control.
