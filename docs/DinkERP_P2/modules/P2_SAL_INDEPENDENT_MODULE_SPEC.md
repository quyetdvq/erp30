# P2 Module SAL — Sales, Marketplace & Shipping (Functional + Technical Specification)

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

### UR-SAL-001 - Sales Order
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Sales Order gồm:
- Company
- Customer khi áp dụng
- Sales Channel
- Product
- UOM
- Quantity
- Base Quantity quy đổi khi áp dụng
- Price
- Promotion/Discount
- Price Source
- Shipping Fee
- Warehouse
- Delivery Address chi tiết
- Delivery Province/City Code
- Delivery Ward/Commune Code
- Payment Method
- Status

`Price Source` baseline gồm:
- `EXTERNAL_CHANNEL`
- `PRICE_LIST_COMPANY`
- `PRICE_LIST_GROUP`
- `PRODUCT_DEFAULT`

**Acceptance criteria**
- Direct/Internal Sales sử dụng Price Resolution theo Product + UOM + Company + Effective Time; Sales Order lưu giá cuối cùng, UOM và Price Source `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP` hoặc `PRODUCT_DEFAULT`.
- Direct/Internal Sales cho phép chọn COMBO như template. Khi chọn COMBO, hệ thống resolve giá root COMBO, flatten về SIMPLE/CHILD, phân bổ selling amount và aggregate leaf theo UR-PRD-006 trước các bước Reservation/Inventory/Serial/Costing/Posting.
- Sales Order phải giữ đủ traceability giữa leaf line và COMBO nguồn khi COMBO được dùng để tạo nhanh line.
- Marketplace Sales từ Shopee/TikTok Shop sử dụng external channel price/discount authoritative và lưu Price Source `EXTERNAL_CHANNEL`.
- Sales Order có Price Source `EXTERNAL_CHANNEL` không bị ERP Price Resolution/Promotion recompute lại giá marketplace.
- Sales Order lưu được nguồn giá, UOM và promotion/discount reference phù hợp với loại order.
- Delivery address sử dụng Province/City Code và Ward/Commune Code khi áp dụng.
- Sales Order tuân theo PBAC theo Company/Resource/State/Action.

### UR-SAL-002 - Shopee Integration
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Hệ thống nhận và quản lý đơn từ Shopee.

**Acceptance criteria**
- Lưu Shopee Order ID và Shop/Account source.
- Mapping SKU/Product.
- Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
- Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
- Giá/discount từ Shopee là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
- Đồng bộ trạng thái cần thiết.
- Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.

### UR-SAL-003 - TikTok Shop Integration
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Hệ thống nhận và quản lý đơn từ TikTok Shop.

**Acceptance criteria**
- Lưu TikTok Shop Order ID và Shop/Account source.
- Mapping SKU/Product.
- Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
- Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
- Giá/discount từ TikTok Shop là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
- Đồng bộ trạng thái cần thiết.
- Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.

### UR-SAL-004 - Inventory dùng chung cho đa kênh
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Shopee và TikTok Shop sử dụng Inventory của ERP làm nguồn tồn vận hành.

**Acceptance criteria**
- Không tạo kho logic riêng cạnh tranh chỉ vì khác sales channel.
- Order phải kiểm tra Available Qty theo Company/Warehouse phù hợp.
- Order được xác nhận phải reserve stock vào Inventory chung theo UR-INV-006.
- Release/consume reservation phải phản ánh vào Inventory chung.
- Sales Issue đã post làm giảm On-hand theo stock posting rule.

### UR-SAL-005 - Shipment và Carrier
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Shipment quản lý:
- Company
- Warehouse/Fulfillment source
- Carrier
- Service
- Tracking Number
- Shipping Fee
- COD Amount
- Delivery Status

Carrier integration hỗ trợ:
- Lấy phí giao hàng
- Tạo vận đơn
- Nhận tracking number
- In label
- Tracking status
- Hủy vận đơn
- Nhận delivery status
- Nhận COD status/data khi carrier hỗ trợ

**Acceptance criteria**
- Shipment thuộc cùng Company với Sales Order nguồn.
- Shipment truy được Sales Order và Warehouse fulfillment liên quan.
- Tracking Number và delivery status được lưu khi carrier trả về.
- Hủy vận đơn không tự xóa Shipment hoặc lịch sử carrier interaction.
- Shipping Fee và COD Amount được lưu để phục vụ COD Reconciliation khi áp dụng.
- Carrier integration error phải được ghi nhận để retry/xử lý.

### UR-SAL-006 - COD Reconciliation
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Theo từng Shipment xác định:
- COD phải thu
- COD carrier báo đã thu
- Shipping fee
- Other fee
- Net amount carrier phải chuyển
- Amount thực nhận
- Difference
- Reconciliation status

**Acceptance criteria**
- Có thể lọc các shipment chưa đối soát.
- Chênh lệch phải xác định được amount và trạng thái xử lý.
- Khoản COD đã đối soát có thể tạo/liên kết Money Receipt.

### UR-SAL-007 - Sales Return
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Hỗ trợ:
- Return Request
- Receive Returned Goods
- Serial validation khi áp dụng
- Inventory update
- Refund/Thu-Chi reference khi áp dụng

**Acceptance criteria**
- Sales Return truy được Sales Order/Shipment nguồn khi có.
- Returned goods chỉ làm tăng On-hand khi inventory return movement được post/confirm theo posting rule.
- Product serial-managed phải validate đúng Serial trả lại.
- Refund hoặc giao dịch Thu/Chi liên quan được reference, không tự tạo double-entry accounting.
- Return đã post không được xóa/sửa trực tiếp làm thay đổi stock history.

### UR-SAL-008 - Marketplace Account Mapping & Order Idempotency
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Marketplace Shop/Account là identity nguồn để map đơn hàng ngoài vào đúng Company và fulfillment context của ERP.

Unique business key của marketplace order:

**Sales Channel + Shop/Account + External Order ID**

**Acceptance criteria**
- Mỗi Shop/Account xác định được Sales Channel/Platform.
- Mỗi Shop/Account map đúng một Company tại một thời điểm hiệu lực.
- Shop/Account có fulfillment Warehouse mặc định hoặc mapping Warehouse rõ ràng trước khi tạo Sales Order.
- `Sales Channel + Shop/Account + External Order ID` là unique business key.
- Retry/sync lại cùng external order không được tạo duplicate ERP Sales Order.
- Sync/retry phải idempotent đối với create/link order.
- Mapping Company/Warehouse phải hoàn tất trước khi ERP Sales Order được tạo.
- Lỗi mapping hoặc sync được giữ trạng thái/lý do để reprocess.

---


## 2. Design-level functional decomposition (P2-DRAFT)

## Domain behavior

1. Sales Order one Company, warehouse same Company, Customer optional per UR. Direct/Internal price resolve from P1 with P2 pricing/promo when approved; **Marketplace price/discount externally authoritative** with `EXTERNAL_CHANNEL` source, not recalculated by ERP.
2. Direct Sales accepts root COMBO as template: resolve COMBO root price once; flatten recursively to SIMPLE/CHILD, weight = leaf qty × default BaseUOM product price; sum allocated amount preserves root after residual rounding; aggregate repeated leaf across different root COMBO by total qty/total amount. Keep root↔leaf origin trace rows. Do not create stock/reservation for COMBO itself.
3. Confirmed order reserves leaf base quantity. Cancel/reduced quantity release; partial Sales Issue consumes only posted part and decrements On-hand in one transaction. Shipment split and serial selection on Issue must respect remaining quantity.
4. Marketplace Account maps Shop/Account to exactly one Company at effective time and default Warehouse (or explicit mapping). Unique key `(Sales Channel, Shop/Account, External Order ID)`. Raw callback only to restricted inbox; idempotent worker create/link with unique constraint & transaction, failed mapping stays error for authorized replay, no duplicate SO.
5. Carrier/Shipment tracks Carrier, Service, Tracking, ShippingFee, COD Amount, Label, Cancel, Delivery Status, provider attempts. Cancelling label does not delete Shipment nor bypass stock/reservation transitions.
6. COD reconciliation per Shipment records due, carrier collected, shipping/other fees, net due, actual received, difference, state. Reconciling does not imply Money Receipt posted; can create/link Receipt when business verified and finance PBAC passes.
7. Sales Return traces SO/Shipment, receive/validate serial, posted Inventory Return Movement(+) only; refund/Money Expense source ref where applicable, without double-entry accounting.
8. External callback duplicates, out-of-order status and failures must be auditable and recoverable, not create repeated stock movements/finance transactions.

## Marketplace create/link pseudo-flow (design)

```
verify provider HMAC/signature/nonce -> restricted inbox only
BEGIN
  INSERT inbox unique(channel,account,external_order_id) or load existing
  resolve approved effective account Company/warehouse
  validate SKU mapping and external price snapshot (no ERP price rewrite)
  lock inbox row, find existing sales_order_link
  if link exists -> return that id without creating anything
  create SalesOrder + lines with EXTERNAL_CHANNEL price source
  insert unique link; record integration result
COMMIT
```

**Critical OPEN decision:** P1 Service Account has no implicit business authorization. Webhook cannot self-grant PBAC; service mutation execution requires reviewed explicit trusted boundary/identity design.

## Negative tests

Two concurrent identical Shop events create 1 ERP SO; same external ID in two different shops can be distinct; wrong Company mapping -> no SO; repeat delivery event cannot reissue; marketplace external discount unchanged; shipment cancel not delete; COD recon not money posting; returned serial wrong product rejected.

## Decisions

External order cancellation mapping, fulfillment warehouse override, channel/account credential model, SKU mapping effective dates, provider signed webhook, shipping SLA, carrier timeout/backoff, COD payout splits and idempotent receipt linkage.

## 3. Proposed database entities (P2-DRAFT)

| Entity | Purpose | Important consistency constraint |
|---|---|---|
| `sales_channels` | Canonical internal/marketplace channel identity | UNIQUE(code) |
| `sales_orders` | Single-Company sales order | UNIQUE(company_id,sales_order_number); INDEX(company_id,status,sales_channel_id) |
| `sales_order_lines` | Operational leaf line | UNIQUE(sales_order_id,line_no) |
| `sales_order_combo_origins` | Root COMBO selection trace | INDEX(sales_order_id) |
| `sales_order_combo_allocations` | Leaf allocations from combo root | UNIQUE(combo_origin_id,sales_order_line_id) |
| `marketplace_accounts` | Source shop/account → Company and warehouse mapping | INDEX(sales_channel_id,external_account_id,effective_from) |
| `marketplace_order_inbox` | Raw external order reference and safe digest | UNIQUE(sales_channel_id,marketplace_account_id,external_order_id) |
| `marketplace_order_links` | External business key to ERP SO | UNIQUE(marketplace_order_id); UNIQUE(sales_order_id) conditional if no multi-origin |
| `marketplace_sync_attempts` | Marketplace sync errors/retry trace | INDEX(marketplace_order_id,attempted_at) |
| `shipments` | Shipment and carrier metadata | UNIQUE(company_id,shipment_number); INDEX(company_id,tracking_number) |
| `shipment_lines` | Shipment partial fulfillment | INDEX(shipment_id) |
| `carrier_accounts` | Carrier integration connection | INDEX(company_id,carrier_code) |
| `carrier_interactions` | Label/fee/tracking/cancel attempts | UNIQUE(request_key); INDEX(shipment_id,attempted_at) |
| `carrier_tracking_events` | Deduped carrier event timeline | UNIQUE(shipment_id,provider_event_id) if provider id exists; INDEX(shipment_id,event_at) |
| `sales_issues` | Sales Issue header (inventory post source) | UNIQUE(company_id,issue_number) |
| `sales_issue_lines` | Sales Issue leaf posted quantities | INDEX(sales_issue_id) |
| `cod_reconciliations` | COD carrier versus actual receipts | INDEX(company_id,status); INDEX(shipment_id) |
| `sales_returns` | Sales Return header | UNIQUE(company_id,return_number) |
| `sales_return_lines` | Sales Return lines | INDEX(sales_return_id) |

## 4. Proposed REST API operations (P2-DRAFT)

| Method | Path | Candidate Resource.Action |
|---|---|---|
| GET | `/api/v1/sales-orders` | `sales_order.view` |
| GET | `/api/v1/sales-orders/{id}` | `sales_order.view` |
| POST | `/api/v1/sales-orders` | `sales_order.create` |
| PATCH | `/api/v1/sales-orders/{id}` | `sales_order.edit` |
| POST | `/api/v1/sales-orders/{id}/actions/submit` | `sales_order.submit` |
| POST | `/api/v1/sales-orders/{id}/actions/confirm` | `sales_order.setstate` |
| POST | `/api/v1/sales-orders/{id}/actions/cancel` | `sales_order.cancel` |
| GET | `/api/v1/marketplace-accounts` | `marketplace_account.view` |
| GET | `/api/v1/marketplace-accounts/{id}` | `marketplace_account.view` |
| POST | `/api/v1/marketplace-accounts` | `marketplace_account.create` |
| PATCH | `/api/v1/marketplace-accounts/{id}` | `marketplace_account.edit` |
| POST | `/api/v1/marketplace-accounts/{id}/actions/set-state` | `marketplace_account.setstate` |
| GET | `/api/v1/marketplace-orders` | `marketplace_sync.view` |
| GET | `/api/v1/marketplace-orders/{id}` | `marketplace_sync.view` |
| POST | `/api/v1/marketplace-orders/{id}/actions/reprocess` | `marketplace_sync.reprocess` |
| GET | `/api/v1/shipments` | `shipment.view` |
| GET | `/api/v1/shipments/{id}` | `shipment.view` |
| POST | `/api/v1/shipments` | `shipment.create` |
| PATCH | `/api/v1/shipments/{id}` | `shipment.edit` |
| POST | `/api/v1/shipments/{id}/actions/book-label` | `shipment.post` |
| POST | `/api/v1/shipments/{id}/actions/cancel-label` | `shipment.cancel` |
| GET | `/api/v1/carrier-accounts` | `carrier_account.view` |
| GET | `/api/v1/carrier-accounts/{id}` | `carrier_account.view` |
| POST | `/api/v1/carrier-accounts` | `carrier_account.create` |
| PATCH | `/api/v1/carrier-accounts/{id}` | `carrier_account.edit` |
| POST | `/api/v1/carrier-accounts/{id}/actions/set-state` | `carrier_account.setstate` |
| GET | `/api/v1/sales-issues` | `sales_issue.view` |
| GET | `/api/v1/sales-issues/{id}` | `sales_issue.view` |
| POST | `/api/v1/sales-issues` | `sales_issue.create` |
| PATCH | `/api/v1/sales-issues/{id}` | `sales_issue.edit` |
| POST | `/api/v1/sales-issues/{id}/actions/post` | `sales_issue.post` |
| GET | `/api/v1/cod-reconciliations` | `cod_reconciliation.view` |
| GET | `/api/v1/cod-reconciliations/{id}` | `cod_reconciliation.view` |
| POST | `/api/v1/cod-reconciliations` | `cod_reconciliation.create` |
| PATCH | `/api/v1/cod-reconciliations/{id}` | `cod_reconciliation.edit` |
| POST | `/api/v1/cod-reconciliations/{id}/actions/reconcile` | `cod_reconciliation.reconcile` |
| GET | `/api/v1/sales-returns` | `sales_return.view` |
| GET | `/api/v1/sales-returns/{id}` | `sales_return.view` |
| POST | `/api/v1/sales-returns` | `sales_return.create` |
| PATCH | `/api/v1/sales-returns/{id}` | `sales_return.edit` |
| POST | `/api/v1/sales-returns/{id}/actions/post` | `sales_return.post` |
| POST | `/api/v1/sales-returns/{id}/actions/cancel` | `sales_return.cancel` |
| POST | `/api/v1/sales-orders/price-preview` | `sales_order.view` |
| GET | `/api/v1/sales-orders/{id}/reservations` | `sales_order.view` |
| GET | `/api/v1/shipments/{id}/tracking` | `shipment.view` |
| POST | `/api/v1/shipments/{id}/actions/refresh-tracking` | `shipment.edit` |
| GET | `/api/v1/finance/cod-outstanding` | `cod_reconciliation.view` |
| POST | `/api/v1/integrations/marketplaces/{provider}/webhooks` | `integration_webhook.receive` |
| POST | `/api/v1/integrations/carriers/{provider}/webhooks` | `integration_webhook.receive` |

## 5. Proposed frontend screens (P2-DRAFT)

| UI ID | Navigation | Screen behavior |
|---|---|---|
| `P2-UI-SAL-01` | `/sales/orders` | Direct vs marketplace price source, COMBO origins, lifecycle, reservation |
| `P2-UI-SAL-02` | `/sales/price-preview` | Price source, root combo allocation, leaf grouping and rounding |
| `P2-UI-SAL-03` | `/sales/issues` | Shipment issue posting, serial pick, reservation consumption |
| `P2-UI-SAL-04` | `/sales/returns` | Return Request, physical receive, serial verify, refund link |
| `P2-UI-MKT-01` | `/integrations/marketplace-accounts` | Shop/Account, Company/warehouse mapping, effective dates, provider |
| `P2-UI-MKT-02` | `/integrations/marketplace-orders` | External key, sync state, mappings, retry and reason |
| `P2-UI-SHP-01` | `/shipping/shipments` | Carrier rate, label, tracking, cancel, COD amount |
| `P2-UI-SHP-02` | `/shipping/carrier-accounts` | Company Carrier config, integration health |
| `P2-UI-COD-01` | `/shipping/cod` | Due/carrier collected/fees/net/actual/difference, source receipt |

## 6. Complete acceptance registry (BASELINE criteria)

| FR | Acceptance criterion (source wording) | Test ID |
|---|---|---|
| `P2-FR-SAL-001-01` | Direct/Internal Sales sử dụng Price Resolution theo Product + UOM + Company + Effective Time; Sales Order lưu giá cuối cùng, UOM và Price Source `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP` hoặc `PRODUCT_DEFAULT`. | `P2-AT-SAL-001-01` |
| `P2-FR-SAL-001-02` | Direct/Internal Sales cho phép chọn COMBO như template. Khi chọn COMBO, hệ thống resolve giá root COMBO, flatten về SIMPLE/CHILD, phân bổ selling amount và aggregate leaf theo UR-PRD-006 trước các bước Reservation/Inventory/Serial/Costing/Posting. | `P2-AT-SAL-001-02` |
| `P2-FR-SAL-001-03` | Sales Order phải giữ đủ traceability giữa leaf line và COMBO nguồn khi COMBO được dùng để tạo nhanh line. | `P2-AT-SAL-001-03` |
| `P2-FR-SAL-001-04` | Marketplace Sales từ Shopee/TikTok Shop sử dụng external channel price/discount authoritative và lưu Price Source `EXTERNAL_CHANNEL`. | `P2-AT-SAL-001-04` |
| `P2-FR-SAL-001-05` | Sales Order có Price Source `EXTERNAL_CHANNEL` không bị ERP Price Resolution/Promotion recompute lại giá marketplace. | `P2-AT-SAL-001-05` |
| `P2-FR-SAL-001-06` | Sales Order lưu được nguồn giá, UOM và promotion/discount reference phù hợp với loại order. | `P2-AT-SAL-001-06` |
| `P2-FR-SAL-001-07` | Delivery address sử dụng Province/City Code và Ward/Commune Code khi áp dụng. | `P2-AT-SAL-001-07` |
| `P2-FR-SAL-001-08` | Sales Order tuân theo PBAC theo Company/Resource/State/Action. | `P2-AT-SAL-001-08` |
| `P2-FR-SAL-002-01` | Lưu Shopee Order ID và Shop/Account source. | `P2-AT-SAL-002-01` |
| `P2-FR-SAL-002-02` | Mapping SKU/Product. | `P2-AT-SAL-002-02` |
| `P2-FR-SAL-002-03` | Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order. | `P2-AT-SAL-002-03` |
| `P2-FR-SAL-002-04` | Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008. | `P2-AT-SAL-002-04` |
| `P2-FR-SAL-002-05` | Giá/discount từ Shopee là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`. | `P2-AT-SAL-002-05` |
| `P2-FR-SAL-002-06` | Đồng bộ trạng thái cần thiết. | `P2-AT-SAL-002-06` |
| `P2-FR-SAL-002-07` | Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order. | `P2-AT-SAL-002-07` |
| `P2-FR-SAL-003-01` | Lưu TikTok Shop Order ID và Shop/Account source. | `P2-AT-SAL-003-01` |
| `P2-FR-SAL-003-02` | Mapping SKU/Product. | `P2-AT-SAL-003-02` |
| `P2-FR-SAL-003-03` | Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order. | `P2-AT-SAL-003-03` |
| `P2-FR-SAL-003-04` | Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008. | `P2-AT-SAL-003-04` |
| `P2-FR-SAL-003-05` | Giá/discount từ TikTok Shop là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`. | `P2-AT-SAL-003-05` |
| `P2-FR-SAL-003-06` | Đồng bộ trạng thái cần thiết. | `P2-AT-SAL-003-06` |
| `P2-FR-SAL-003-07` | Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order. | `P2-AT-SAL-003-07` |
| `P2-FR-SAL-004-01` | Không tạo kho logic riêng cạnh tranh chỉ vì khác sales channel. | `P2-AT-SAL-004-01` |
| `P2-FR-SAL-004-02` | Order phải kiểm tra Available Qty theo Company/Warehouse phù hợp. | `P2-AT-SAL-004-02` |
| `P2-FR-SAL-004-03` | Order được xác nhận phải reserve stock vào Inventory chung theo UR-INV-006. | `P2-AT-SAL-004-03` |
| `P2-FR-SAL-004-04` | Release/consume reservation phải phản ánh vào Inventory chung. | `P2-AT-SAL-004-04` |
| `P2-FR-SAL-004-05` | Sales Issue đã post làm giảm On-hand theo stock posting rule. | `P2-AT-SAL-004-05` |
| `P2-FR-SAL-005-01` | Shipment thuộc cùng Company với Sales Order nguồn. | `P2-AT-SAL-005-01` |
| `P2-FR-SAL-005-02` | Shipment truy được Sales Order và Warehouse fulfillment liên quan. | `P2-AT-SAL-005-02` |
| `P2-FR-SAL-005-03` | Tracking Number và delivery status được lưu khi carrier trả về. | `P2-AT-SAL-005-03` |
| `P2-FR-SAL-005-04` | Hủy vận đơn không tự xóa Shipment hoặc lịch sử carrier interaction. | `P2-AT-SAL-005-04` |
| `P2-FR-SAL-005-05` | Shipping Fee và COD Amount được lưu để phục vụ COD Reconciliation khi áp dụng. | `P2-AT-SAL-005-05` |
| `P2-FR-SAL-005-06` | Carrier integration error phải được ghi nhận để retry/xử lý. | `P2-AT-SAL-005-06` |
| `P2-FR-SAL-006-01` | Có thể lọc các shipment chưa đối soát. | `P2-AT-SAL-006-01` |
| `P2-FR-SAL-006-02` | Chênh lệch phải xác định được amount và trạng thái xử lý. | `P2-AT-SAL-006-02` |
| `P2-FR-SAL-006-03` | Khoản COD đã đối soát có thể tạo/liên kết Money Receipt. | `P2-AT-SAL-006-03` |
| `P2-FR-SAL-007-01` | Sales Return truy được Sales Order/Shipment nguồn khi có. | `P2-AT-SAL-007-01` |
| `P2-FR-SAL-007-02` | Returned goods chỉ làm tăng On-hand khi inventory return movement được post/confirm theo posting rule. | `P2-AT-SAL-007-02` |
| `P2-FR-SAL-007-03` | Product serial-managed phải validate đúng Serial trả lại. | `P2-AT-SAL-007-03` |
| `P2-FR-SAL-007-04` | Refund hoặc giao dịch Thu/Chi liên quan được reference, không tự tạo double-entry accounting. | `P2-AT-SAL-007-04` |
| `P2-FR-SAL-007-05` | Return đã post không được xóa/sửa trực tiếp làm thay đổi stock history. | `P2-AT-SAL-007-05` |
| `P2-FR-SAL-008-01` | Mỗi Shop/Account xác định được Sales Channel/Platform. | `P2-AT-SAL-008-01` |
| `P2-FR-SAL-008-02` | Mỗi Shop/Account map đúng một Company tại một thời điểm hiệu lực. | `P2-AT-SAL-008-02` |
| `P2-FR-SAL-008-03` | Shop/Account có fulfillment Warehouse mặc định hoặc mapping Warehouse rõ ràng trước khi tạo Sales Order. | `P2-AT-SAL-008-03` |
| `P2-FR-SAL-008-04` | `Sales Channel + Shop/Account + External Order ID` là unique business key. | `P2-AT-SAL-008-04` |
| `P2-FR-SAL-008-05` | Retry/sync lại cùng external order không được tạo duplicate ERP Sales Order. | `P2-AT-SAL-008-05` |
| `P2-FR-SAL-008-06` | Sync/retry phải idempotent đối với create/link order. | `P2-AT-SAL-008-06` |
| `P2-FR-SAL-008-07` | Mapping Company/Warehouse phải hoàn tất trước khi ERP Sales Order được tạo. | `P2-AT-SAL-008-07` |
| `P2-FR-SAL-008-08` | Lỗi mapping hoặc sync được giữ trạng thái/lý do để reprocess. | `P2-AT-SAL-008-08` |

## 7. Domain test/approval handoff

- Trước thiết kế DB/API/UI: freeze OPEN-DECISION liên quan, lifecycle, permission, Company authority, immutable posting semantics.
- Trước code: review migration forward-only, tests negative/cross-company/idempotency/rollback và consistency với các P1 services.
- Trước release: chạy runtime trên DEV clone (CI4 + MariaDB + frontend), log test IDs và proof; không nhận PASS chỉ vì có file tài liệu hoặc route.

**State:** toàn bộ đề xuất DB/API/FE của module chưa tồn tại như nghiệp vụ P2 trong v1.9.0. Requirement P2 gốc trong §1 vẫn `BASELINE`.
