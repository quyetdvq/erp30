# 06 — P2 REST API Design & OpenAPI Draft

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. API authority và trạng thái

- P1 có `contracts/openapi/openapi.yaml` (OpenAPI 3.1.0), `/api/v1`, Shield session + CSRF mutation, vi/en language negotiation; **P2 endpoints dưới đây chưa có trong baseline**.
- File machine-readable đi kèm: `contracts/openapi/p2.draft.openapi.yaml` là **separate draft**, không replace/merge P1 source contract.
- `P2-DRAFT` cho tên path/method/action/schema, không phải `APPROVED` hay `IMPLEMENTED`. Schema auto-generated từ Data Dictionary để hỗ trợ review; một số action-specific body hiện chỉ là candidate và phải qua ADR.
- Không sử dụng placeholder server `https://example.invalid` khi triển khai thật.

## 2. Canonical HTTP rules kế thừa P1

- Query paging, allowlisted sort, field filters và authorized SQL-scope **trước** LIMIT/OFFSET; stable tie-break `id`.
- Response `error.code` ổn định theo máy, `message` negotiate vi/en; không parse message; 401/403/404 chống leak.
- IDs as string to prevent JS integer loss; DECIMAL as string; timestamps ISO 8601; business DATE `YYYY-MM-DD` theo Company timezone. `Accept-Language`, request/correlation ID, session cookie & CSRF apply as P1.
- Mutation use server-owned Company context and `expected_revision`/idempotency where approved; status/action cannot be arbitrarily PATCH-ed to bypass transition.
- List `EXPORT` là permission độc lập; không suy từ `view`.
- Webhook operations **không** dùng session browser; sign/verify provider event, anti-replay to restricted inbox only; authenticated service-side business mutation requires separate approved design (P1 service account not automatically authorized).
- Routes grouping final must merge through `backend/app/Config/Routes.php` with `setAutoRoute(false)`, align OpenAPI generator; `frontend/src/api/generated` must be regenerated, not hand edited.

## 3. Proposed endpoint catalog (each path is NOT IMPLEMENTED)

| HTTP | Path | Resource.Action candidate | Entity | Purpose |
|---|---|---|---|---|
| GET | `/api/v1/documents` | `document.view` | `documents` | List filtered, authorized and paginated |
| GET | `/api/v1/documents/{id}` | `document.view` | `documents` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/documents` | `document.create` | `documents` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/documents/{id}` | `document.edit` | `documents` | Update mutable fields only, never posted ledger fields |
| GET | `/api/v1/document-tags` | `document.view` | `document_tags` | List filtered, authorized and paginated |
| GET | `/api/v1/document-tags/{id}` | `document.view` | `document_tags` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/document-tags` | `document.create` | `document_tags` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/document-tags/{id}` | `document.edit` | `document_tags` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/document-tags/{id}/actions/set-state` | `document.setstate` | `document_tags` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/customers` | `customer.view` | `customers` | List filtered, authorized and paginated |
| GET | `/api/v1/customers/{id}` | `customer.view` | `customers` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/customers` | `customer.create` | `customers` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/customers/{id}` | `customer.edit` | `customers` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/customers/{id}/actions/set-state` | `customer.setstate` | `customers` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/customer-groups` | `customer.view` | `customer_groups` | List filtered, authorized and paginated |
| GET | `/api/v1/customer-groups/{id}` | `customer.view` | `customer_groups` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/customer-groups` | `customer.create` | `customer_groups` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/customer-groups/{id}` | `customer.edit` | `customer_groups` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/customer-groups/{id}/actions/set-state` | `customer.setstate` | `customer_groups` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/suppliers` | `supplier.view` | `suppliers` | List filtered, authorized and paginated |
| GET | `/api/v1/suppliers/{id}` | `supplier.view` | `suppliers` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/suppliers` | `supplier.create` | `suppliers` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/suppliers/{id}` | `supplier.edit` | `suppliers` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/suppliers/{id}/actions/set-state` | `supplier.setstate` | `suppliers` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/promotions` | `promotion.view` | `promotions` | List filtered, authorized and paginated |
| GET | `/api/v1/promotions/{id}` | `promotion.view` | `promotions` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/promotions` | `promotion.create` | `promotions` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/promotions/{id}` | `promotion.edit` | `promotions` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/promotions/{id}/actions/set-state` | `promotion.setstate` | `promotions` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/coupons` | `promotion.view` | `coupons` | List filtered, authorized and paginated |
| GET | `/api/v1/coupons/{id}` | `promotion.view` | `coupons` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/coupons` | `promotion.create` | `coupons` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/coupons/{id}` | `promotion.edit` | `coupons` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/coupons/{id}/actions/set-state` | `promotion.setstate` | `coupons` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/sales-channels` | `sales_channel.view` | `sales_channels` | List filtered, authorized and paginated |
| GET | `/api/v1/sales-channels/{id}` | `sales_channel.view` | `sales_channels` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/sales-channels` | `sales_channel.create` | `sales_channels` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/sales-channels/{id}` | `sales_channel.edit` | `sales_channels` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/sales-channels/{id}/actions/set-state` | `sales_channel.setstate` | `sales_channels` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/purchase-requests` | `purchase_request.view` | `purchase_requests` | List filtered, authorized and paginated |
| GET | `/api/v1/purchase-requests/{id}` | `purchase_request.view` | `purchase_requests` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/purchase-requests` | `purchase_request.create` | `purchase_requests` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/purchase-requests/{id}` | `purchase_request.edit` | `purchase_requests` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/purchase-requests/{id}/actions/submit` | `purchase_request.submit` | `purchase_requests` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/purchase-requests/{id}/actions/approve` | `purchase_request.approve` | `purchase_requests` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/purchase-requests/{id}/actions/reject` | `purchase_request.reject` | `purchase_requests` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/purchase-requests/{id}/actions/cancel` | `purchase_request.cancel` | `purchase_requests` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/purchase-orders` | `purchase_order.view` | `purchase_orders` | List filtered, authorized and paginated |
| GET | `/api/v1/purchase-orders/{id}` | `purchase_order.view` | `purchase_orders` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/purchase-orders` | `purchase_order.create` | `purchase_orders` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/purchase-orders/{id}` | `purchase_order.edit` | `purchase_orders` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/purchase-orders/{id}/actions/submit` | `purchase_order.submit` | `purchase_orders` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/purchase-orders/{id}/actions/approve` | `purchase_order.approve` | `purchase_orders` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/purchase-orders/{id}/actions/reject` | `purchase_order.reject` | `purchase_orders` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/purchase-orders/{id}/actions/cancel` | `purchase_order.cancel` | `purchase_orders` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/goods-receipts` | `goods_receipt.view` | `goods_receipts` | List filtered, authorized and paginated |
| GET | `/api/v1/goods-receipts/{id}` | `goods_receipt.view` | `goods_receipts` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/goods-receipts` | `goods_receipt.create` | `goods_receipts` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/goods-receipts/{id}` | `goods_receipt.edit` | `goods_receipts` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/goods-receipts/{id}/actions/post` | `goods_receipt.post` | `goods_receipts` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/goods-receipts/{id}/actions/cancel` | `goods_receipt.cancel` | `goods_receipts` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/purchase-returns` | `purchase_return.view` | `purchase_returns` | List filtered, authorized and paginated |
| GET | `/api/v1/purchase-returns/{id}` | `purchase_return.view` | `purchase_returns` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/purchase-returns` | `purchase_return.create` | `purchase_returns` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/purchase-returns/{id}` | `purchase_return.edit` | `purchase_returns` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/purchase-returns/{id}/actions/post` | `purchase_return.post` | `purchase_returns` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/purchase-returns/{id}/actions/cancel` | `purchase_return.cancel` | `purchase_returns` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/inventory-balances` | `inventory_balance.view` | `inventory_balances` | List filtered, authorized and paginated |
| GET | `/api/v1/inventory-balances/{id}` | `inventory_balance.view` | `inventory_balances` | Fetch detail with authoritative persisted organization |
| GET | `/api/v1/inventory-movements` | `inventory_movement.view` | `inventory_movements` | List filtered, authorized and paginated |
| GET | `/api/v1/inventory-movements/{id}` | `inventory_movement.view` | `inventory_movements` | Fetch detail with authoritative persisted organization |
| GET | `/api/v1/inventory-serials` | `inventory_serial.view` | `inventory_serials` | List filtered, authorized and paginated |
| GET | `/api/v1/inventory-serials/{id}` | `inventory_serial.view` | `inventory_serials` | Fetch detail with authoritative persisted organization |
| GET | `/api/v1/inventory-reservations` | `inventory_reservation.view` | `inventory_reservations` | List filtered, authorized and paginated |
| GET | `/api/v1/inventory-reservations/{id}` | `inventory_reservation.view` | `inventory_reservations` | Fetch detail with authoritative persisted organization |
| GET | `/api/v1/warehouse-transfers` | `warehouse_transfer.view` | `warehouse_transfers` | List filtered, authorized and paginated |
| GET | `/api/v1/warehouse-transfers/{id}` | `warehouse_transfer.view` | `warehouse_transfers` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/warehouse-transfers` | `warehouse_transfer.create` | `warehouse_transfers` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/warehouse-transfers/{id}` | `warehouse_transfer.edit` | `warehouse_transfers` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/warehouse-transfers/{id}/actions/post` | `warehouse_transfer.post` | `warehouse_transfers` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/warehouse-transfers/{id}/actions/cancel` | `warehouse_transfer.cancel` | `warehouse_transfers` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/stock-counts` | `stock_count.view` | `stock_counts` | List filtered, authorized and paginated |
| GET | `/api/v1/stock-counts/{id}` | `stock_count.view` | `stock_counts` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/stock-counts` | `stock_count.create` | `stock_counts` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/stock-counts/{id}` | `stock_count.edit` | `stock_counts` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/stock-counts/{id}/actions/submit` | `stock_count.submit` | `stock_counts` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/stock-counts/{id}/actions/approve` | `stock_count.approve` | `stock_counts` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/stock-counts/{id}/actions/cancel` | `stock_count.cancel` | `stock_counts` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/inventory-adjustments` | `inventory_adjustment.view` | `inventory_adjustments` | List filtered, authorized and paginated |
| GET | `/api/v1/inventory-adjustments/{id}` | `inventory_adjustment.view` | `inventory_adjustments` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/inventory-adjustments` | `inventory_adjustment.create` | `inventory_adjustments` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/inventory-adjustments/{id}` | `inventory_adjustment.edit` | `inventory_adjustments` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/inventory-adjustments/{id}/actions/post` | `inventory_adjustment.post` | `inventory_adjustments` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/inventory-adjustments/{id}/actions/cancel` | `inventory_adjustment.cancel` | `inventory_adjustments` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/sales-orders` | `sales_order.view` | `sales_orders` | List filtered, authorized and paginated |
| GET | `/api/v1/sales-orders/{id}` | `sales_order.view` | `sales_orders` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/sales-orders` | `sales_order.create` | `sales_orders` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/sales-orders/{id}` | `sales_order.edit` | `sales_orders` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/sales-orders/{id}/actions/submit` | `sales_order.submit` | `sales_orders` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/sales-orders/{id}/actions/confirm` | `sales_order.setstate` | `sales_orders` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/sales-orders/{id}/actions/cancel` | `sales_order.cancel` | `sales_orders` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/marketplace-accounts` | `marketplace_account.view` | `marketplace_accounts` | List filtered, authorized and paginated |
| GET | `/api/v1/marketplace-accounts/{id}` | `marketplace_account.view` | `marketplace_accounts` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/marketplace-accounts` | `marketplace_account.create` | `marketplace_accounts` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/marketplace-accounts/{id}` | `marketplace_account.edit` | `marketplace_accounts` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/marketplace-accounts/{id}/actions/set-state` | `marketplace_account.setstate` | `marketplace_accounts` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/marketplace-orders` | `marketplace_sync.view` | `marketplace_order_inbox` | List filtered, authorized and paginated |
| GET | `/api/v1/marketplace-orders/{id}` | `marketplace_sync.view` | `marketplace_order_inbox` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/marketplace-orders/{id}/actions/reprocess` | `marketplace_sync.reprocess` | `marketplace_order_inbox` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/shipments` | `shipment.view` | `shipments` | List filtered, authorized and paginated |
| GET | `/api/v1/shipments/{id}` | `shipment.view` | `shipments` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/shipments` | `shipment.create` | `shipments` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/shipments/{id}` | `shipment.edit` | `shipments` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/shipments/{id}/actions/book-label` | `shipment.post` | `shipments` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/shipments/{id}/actions/cancel-label` | `shipment.cancel` | `shipments` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/carrier-accounts` | `carrier_account.view` | `carrier_accounts` | List filtered, authorized and paginated |
| GET | `/api/v1/carrier-accounts/{id}` | `carrier_account.view` | `carrier_accounts` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/carrier-accounts` | `carrier_account.create` | `carrier_accounts` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/carrier-accounts/{id}` | `carrier_account.edit` | `carrier_accounts` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/carrier-accounts/{id}/actions/set-state` | `carrier_account.setstate` | `carrier_accounts` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/sales-issues` | `sales_issue.view` | `sales_issues` | List filtered, authorized and paginated |
| GET | `/api/v1/sales-issues/{id}` | `sales_issue.view` | `sales_issues` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/sales-issues` | `sales_issue.create` | `sales_issues` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/sales-issues/{id}` | `sales_issue.edit` | `sales_issues` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/sales-issues/{id}/actions/post` | `sales_issue.post` | `sales_issues` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/cod-reconciliations` | `cod_reconciliation.view` | `cod_reconciliations` | List filtered, authorized and paginated |
| GET | `/api/v1/cod-reconciliations/{id}` | `cod_reconciliation.view` | `cod_reconciliations` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/cod-reconciliations` | `cod_reconciliation.create` | `cod_reconciliations` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/cod-reconciliations/{id}` | `cod_reconciliation.edit` | `cod_reconciliations` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/cod-reconciliations/{id}/actions/reconcile` | `cod_reconciliation.reconcile` | `cod_reconciliations` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/sales-returns` | `sales_return.view` | `sales_returns` | List filtered, authorized and paginated |
| GET | `/api/v1/sales-returns/{id}` | `sales_return.view` | `sales_returns` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/sales-returns` | `sales_return.create` | `sales_returns` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/sales-returns/{id}` | `sales_return.edit` | `sales_returns` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/sales-returns/{id}/actions/post` | `sales_return.post` | `sales_returns` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/sales-returns/{id}/actions/cancel` | `sales_return.cancel` | `sales_returns` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/money-accounts` | `money_account.view` | `cash_bank_accounts` | List filtered, authorized and paginated |
| GET | `/api/v1/money-accounts/{id}` | `money_account.view` | `cash_bank_accounts` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/money-accounts` | `money_account.create` | `cash_bank_accounts` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/money-accounts/{id}` | `money_account.edit` | `cash_bank_accounts` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/money-accounts/{id}/actions/set-state` | `money_account.setstate` | `cash_bank_accounts` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/money-categories` | `money_category.view` | `money_categories` | List filtered, authorized and paginated |
| GET | `/api/v1/money-categories/{id}` | `money_category.view` | `money_categories` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/money-categories` | `money_category.create` | `money_categories` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/money-categories/{id}` | `money_category.edit` | `money_categories` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/money-categories/{id}/actions/set-state` | `money_category.setstate` | `money_categories` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/money-receipts` | `money_receipt.view` | `money_transactions` | List filtered, authorized and paginated |
| GET | `/api/v1/money-receipts/{id}` | `money_receipt.view` | `money_transactions` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/money-receipts` | `money_receipt.create` | `money_transactions` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/money-receipts/{id}` | `money_receipt.edit` | `money_transactions` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/money-receipts/{id}/actions/post` | `money_receipt.post` | `money_transactions` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/money-receipts/{id}/actions/reverse` | `money_receipt.reverse` | `money_transactions` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/money-expenses` | `money_expense.view` | `money_transactions` | List filtered, authorized and paginated |
| GET | `/api/v1/money-expenses/{id}` | `money_expense.view` | `money_transactions` | Fetch detail with authoritative persisted organization |
| POST | `/api/v1/money-expenses` | `money_expense.create` | `money_transactions` | Create transaction/master; check PBAC+validation in same boundary |
| PATCH | `/api/v1/money-expenses/{id}` | `money_expense.edit` | `money_transactions` | Update mutable fields only, never posted ledger fields |
| POST | `/api/v1/money-expenses/{id}/actions/post` | `money_expense.post` | `money_transactions` | Lifecycle/business action; server PBAC and state guard, no double commit |
| POST | `/api/v1/money-expenses/{id}/actions/reverse` | `money_expense.reverse` | `money_transactions` | Lifecycle/business action; server PBAC and state guard, no double commit |
| GET | `/api/v1/documents/{id}/versions` | `document.view` | `document_versions` | Version list |
| POST | `/api/v1/documents/{id}/versions` | `document.edit` | `document_versions` | Upload version + content hash + safe storage |
| GET | `/api/v1/documents/{id}/download` | `document.view` | `document_versions` | Authorized current version file streaming |
| GET | `/api/v1/documents/{id}/links` | `document.view` | `document_links` | List permitted owners only |
| POST | `/api/v1/documents/{id}/links` | `document.edit` | `document_links` | Link owner after PBAC owner check |
| DELETE | `/api/v1/documents/{id}/links/{linkId}` | `document.edit` | `document_links` | Unlink only; keep version history |
| GET | `/api/v1/customers/{id}/history` | `customer.view` | `customer_notes` | Authorized Company history |
| POST | `/api/v1/customers/{id}/notes` | `customer.edit` | `customer_notes` | Append note with actor and Company |
| GET | `/api/v1/customers/{id}/company-usages` | `customer.view` | `customer_company_usages` | Company allowlist snapshot |
| PUT | `/api/v1/customers/{id}/company-usages` | `customer.edit` | `customer_company_usages` | Replace explicit Company Usage Scope transactionally |
| GET | `/api/v1/suppliers/{id}/history` | `supplier.view` | `supplier_notes` | Authorized Purchase/Payment history |
| POST | `/api/v1/suppliers/{id}/notes` | `supplier.edit` | `supplier_notes` | Append note, avoid cross-company leak |
| GET | `/api/v1/suppliers/{id}/company-usages` | `supplier.view` | `supplier_company_usages` | Company allowlist snapshot |
| PUT | `/api/v1/suppliers/{id}/company-usages` | `supplier.edit` | `supplier_company_usages` | Replace explicit Company Usage Scope |
| GET | `/api/v1/price-lists/{id}/items/{itemId}/contexts` | `price_list.view` | `product_price_list_item_contexts` | P2 context conditions on P1 price item |
| PUT | `/api/v1/price-lists/{id}/items/{itemId}/contexts` | `price_list.edit` | `product_price_list_item_contexts` | Replace deterministic P2 conditions; do not alter P1 item key |
| POST | `/api/v1/pricing/resolve-p2` | `price_list.view` | `product_price_list_item_contexts` | P2 pricing preview no write |
| POST | `/api/v1/promotions/evaluate` | `promotion.view` | `promotion_rules` | Preview eligible promo and conflict resolution |
| GET | `/api/v1/purchase-orders/{id}/receipts` | `purchase_order.view` | `goods_receipts` | Partial receipts/received quantity |
| GET | `/api/v1/inventory-balances/reconciliation` | `inventory_balance.view` | `inventory_balances` | Reconcile immutable movements to balance; read-only |
| POST | `/api/v1/sales-orders/price-preview` | `sales_order.view` | `sales_order_lines` | Resolve internal sales/COMBO without persisting |
| GET | `/api/v1/sales-orders/{id}/reservations` | `sales_order.view` | `inventory_reservations` | Read reservation states for order |
| GET | `/api/v1/shipments/{id}/tracking` | `shipment.view` | `carrier_tracking_events` | Carrier tracking read |
| POST | `/api/v1/shipments/{id}/actions/refresh-tracking` | `shipment.edit` | `carrier_interactions` | Provider API refresh requires retry rate limit |
| GET | `/api/v1/finance/cod-outstanding` | `cod_reconciliation.view` | `cod_reconciliations` | COD unmatched report scoped by Company |
| GET | `/api/v1/money-reports/balance` | `money_report.view` | `money_transactions` | Opening + posted receipts - posted expenses |
| GET | `/api/v1/money-reports/transactions` | `money_report.view` | `money_transactions` | Authorized operational transaction aggregates |
| POST | `/api/v1/integrations/marketplaces/{provider}/webhooks` | `integration_webhook.receive` | `marketplace_order_inbox` | Signed provider callback; not sessionAuth; only durable inbox ingestion |
| POST | `/api/v1/integrations/carriers/{provider}/webhooks` | `integration_webhook.receive` | `carrier_tracking_events` | Signed provider callback; not sessionAuth; no direct stock/finance mutation |

## 4. Detailed request/response examples (P2-DRAFT)

### 4.1 Create Purchase Order — Company ownership

```http
POST /api/v1/purchase-orders
Content-Type: application/json
Accept-Language: vi

{
  "company_id": "2", "supplier_id": "19", "receiving_warehouse_id": "34",
  "expected_receipt_date": "2026-11-10", "lines": [
    {"product_id":"101", "uom_id":"5", "quantity":"12.000000", "unit_price":"150000.00"}
  ]
}
```

**Validation:** Company is Organization type COMPANY; Warehouse ancestor Company = PO Company; Supplier active and usable; product SIMPLE/CHILD leaf after optional COMBO expansion; Base UOM/qty validated; PO number issued server-side from Numbering config if configured. Caller cannot override authoritative organization via `evaluation_organization_id`.

### 4.2 Confirm Sales Order — pricing versus marketplace

`POST /api/v1/sales-orders/{id}/actions/confirm` proposes action `sales_order.setstate` (approval of exact transition required). Internal sales resolve Company→Group→default and COMBO allocation; Marketplace uses stored `EXTERNAL_CHANNEL` price snapshots and must not reprice. Confirm must create reservations atomically, no Available<0.

### 4.3 Post Sales Issue — idempotent reserve consume and inventory ledger

```http
POST /api/v1/sales-issues/777/actions/post
Idempotency-Key: so-42-issue-777

{"expected_revision":"3"}
```

**Expected:** single transaction: validate legal lifecycle + PBAC + serial, consume reservation, insert negative immutable stock movement(s), update stock balance/serial and change issue status. Retry with same key cannot double-decrease stock; conflict of different payload with same key must error.

### 4.4 Money Receipt — no double-entry

`POST /api/v1/money-receipts/{id}/actions/post`: verify Company/Account/Category and amount>0; post only on valid state, account active; same ID cannot post twice. `GET /api/v1/money-reports/balance` only includes posted Money Receipt/Expense, opening balance, filter and allowed Company set.

## 5. Stable error catalog — proposed, not baseline codes

| HTTP | `error.code` candidate | Trigger |
|---:|---|---|
| 403 | `P2_PBAC_DENIED` | No effective permission in authoritative context |
| 409 | `P2_COMPANY_SCOPE_MISMATCH` | Source/warehouse/transaction Company mismatch |
| 409 | `P2_DUPLICATE_EXTERNAL_ORDER` | Existing `(channel,shop,external_order_id)` conflict with unequal identity |
| 409 | `P2_ALREADY_POSTED` | Duplicate posting or immutable change |
| 409 | `P2_REVISION_CONFLICT` | Lost update detected |
| 422 | `P2_INSUFFICIENT_AVAILABLE_QTY` | Reservation/issue would make Available negative |
| 422 | `P2_INVALID_SERIAL_LOCATION` | Serial not at expected warehouse/state |
| 422 | `P2_INVALID_PRODUCT_ROLE` | PARENT or COMBO directly posted as stock leaf |
| 422 | `P2_NO_PROMOTION_DETERMINISM` | Equal-priority conflicting offers without declared winner |
| 422 | `P2_INVALID_LIFECYCLE_TRANSITION` | State transition illegal even when permission exists |
| 422 | `P2_REFERENCE_NOT_USABLE` | Inactive / invalid Company usage master for new transaction |
| 503 | `P2_PROVIDER_TEMPORARY_FAILURE` | Carrier/marketplace external failure; retry logged, no duplicate |

Before coding, map proposed errors to existing P1 error taxonomy and OpenAPI response envelope. A code above is **not** claimed present in v1.9.0.

## 6. Gates for contract freeze

1. Freeze resource catalog & authorization per action, webhook/service identity boundary, Company resolution source.
2. Freeze lifecycle state/action, expected_revision / Idempotency-Key policy per action, stable error codes.
3. Replace generic action-body placeholders with typed schemas, enumerate validation constraints and list filters (including max page size).
4. Align P2 Draft with P1 OpenAPI without breaking existing operation IDs; run spec lint, generate TS, check drift.
5. Integration HTTP tests cover every endpoint + permission, cross-company, malformed refs, race and rollback before marking `APPROVED`/`IMPLEMENTED`.
