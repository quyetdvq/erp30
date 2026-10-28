# 07 — P2 UI/UX Design & Frontend Contracts

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Dùng lại frontend P1

- Vue 3 + TypeScript + Pinia, page/feature boundaries, application shell, i18n `vi/en` default `vi`, Organization context, backend-provided PBAC hints và generated API client.
- Settings trình bày Date/Time/Number/Money chỉ là format; mọi POST giữ canonical ISO/decimal string.
- Table-first: server side search/filter/sort/pagination on authorized data; deterministic sort and clear empty/loading/error/permission state.
- Mỗi screen dưới đây là **P2-DRAFT**, chưa nằm trong P1 source; path FE là đề xuất, khác path REST `/api/v1`.

## 2. Screens và behavioral contract

| Screen | FE path draft | Backend Resource | Required UI capability | Negative/QA |
|---|---|---|---|---|
| `P2-UI-DOC-01` — Documents & Versions | `/documents` | `document` | Documents search, tags, linked entity selector, versions, upload/download | owner RBAC + MIME, file size, file version, denied download |
| `P2-UI-CUS-01` — Customer Master & History | `/customers` | `customer` | Group master, Company scope, notes/history and shared Contacts | history filtered Company, inactive no new order |
| `P2-UI-CUS-02` — Customer Groups | `/customer-groups` | `customer` | Group catalog for pricing context | code uniqueness, no pricing bypass |
| `P2-UI-SUP-01` — Supplier Master & History | `/suppliers` | `supplier` | Group master, Company scope, notes, Purchase and finance history | Company ownership + bank sensitive content |
| `P2-UI-PRC-01` — Promotion & Coupons | `/promotions` | `promotion` | Type, priority, effective dates, combinability, eligibility, simulation | duplicate priority conflicts cannot activate |
| `P2-UI-PRC-02` — Pricing Context P2 | `/pricing/contexts` | `price_list` | Customer/Group/Channel price-specificity preview | P1 price-list resolution remains canonical |
| `P2-UI-PUR-01` — Purchase Requests | `/purchase/requests` | `purchase_request` | Draft/submit/approve/reject, lines, Company, requester | no approve without state+PBAC |
| `P2-UI-PUR-02` — Purchase Orders | `/purchase/orders` | `purchase_order` | Supplier, prices, UOM, warehouse, link to PR, partial receipts | supplier usage and warehouse Company match |
| `P2-UI-PUR-03` — Goods Receipts | `/purchase/goods-receipts` | `goods_receipt` | Partial receive, serial capture, post status | no on-hand before post, retry no double stock |
| `P2-UI-PUR-04` — Purchase Returns | `/purchase/returns` | `purchase_return` | Return from GR/PO, serial selection, post | cannot return wrong serial or over-return |
| `P2-UI-INV-01` — Inventory Balance | `/inventory/balances` | `inventory_balance` | On-hand/Reserved/Available by warehouse and product | no cross Company, no COMBO row |
| `P2-UI-INV-02` — Movement Ledger | `/inventory/movements` | `inventory_movement` | Source transaction, posting, reversal trail | immutable posting; export independent permission |
| `P2-UI-INV-03` — Serial Trace | `/inventory/serials` | `inventory_serial` | Current company/warehouse/status and movement trail | same serial never two warehouses |
| `P2-UI-INV-04` — Reservation Ledger | `/inventory/reservations` | `inventory_reservation` | Reserve/release/consume quantities and source SO | available nonnegative, no reserve COMBO |
| `P2-UI-INV-05` — Warehouse Transfers | `/inventory/transfers` | `warehouse_transfer` | Two warehouses, leaves, serial list, post | same Company and atomic two-leg movement |
| `P2-UI-INV-06` — Stock Counts | `/inventory/stock-counts` | `stock_count` | Snapshot system qty, actual qty, variance, confirm | approval + snapshot/recount conflicts |
| `P2-UI-INV-07` — Stock Adjustments | `/inventory/adjustments` | `inventory_adjustment` | Adjustment based on Count or reason, post | cannot edit posted, link count |
| `P2-UI-SAL-01` — Sales Orders | `/sales/orders` | `sales_order` | Direct vs marketplace price source, COMBO origins, lifecycle, reservation | external price immutable, company scope |
| `P2-UI-SAL-02` — Sales Pricing/COMBO Preview | `/sales/price-preview` | `sales_order` | Price source, root combo allocation, leaf grouping and rounding | sum leaf amount equals root amount |
| `P2-UI-SAL-03` — Sales Issues | `/sales/issues` | `sales_issue` | Shipment issue posting, serial pick, reservation consumption | atomic consume + stock decrement |
| `P2-UI-SAL-04` — Sales Returns | `/sales/returns` | `sales_return` | Return Request, physical receive, serial verify, refund link | only posted return increases on-hand |
| `P2-UI-MKT-01` — Marketplace Accounts | `/integrations/marketplace-accounts` | `marketplace_account` | Shop/Account, Company/warehouse mapping, effective dates, provider | one Company per effective time; secrets not displayed |
| `P2-UI-MKT-02` — Marketplace Sync Monitor | `/integrations/marketplace-orders` | `marketplace_sync` | External key, sync state, mappings, retry and reason | reprocess no duplicate Sales Order |
| `P2-UI-SHP-01` — Shipments & Tracking | `/shipping/shipments` | `shipment` | Carrier rate, label, tracking, cancel, COD amount | carrier cancel not delete shipment |
| `P2-UI-SHP-02` — Carrier Accounts | `/shipping/carrier-accounts` | `carrier_account` | Company Carrier config, integration health | secrets protected, PBAC |
| `P2-UI-COD-01` — COD Reconciliation | `/shipping/cod` | `cod_reconciliation` | Due/carrier collected/fees/net/actual/difference, source receipt | no money posting from status alone |
| `P2-UI-FIN-01` — Cash & Bank Accounts | `/finance/accounts` | `money_account` | Cash Fund/Bank Account opening date/balance | no account cross-company |
| `P2-UI-FIN-02` — Money Categories | `/finance/categories` | `money_category` | RECEIPT/EXPENSE catalog | inactive cannot use new |
| `P2-UI-FIN-03` — Money Receipts | `/finance/receipts` | `money_receipt` | Company/account/category/source/amount/post/correction | only posted counts balance |
| `P2-UI-FIN-04` — Money Expenses | `/finance/expenses` | `money_expense` | Company/account/category/source/amount/post/correction | only posted counts balance |
| `P2-UI-FIN-05` — Operational Cash Flow Reports | `/finance/reports` | `money_report` | Opening+Receipts-Expenses, filtered company/date/account | only authorized Company aggregated |

## 3. UX shared states và editor policy

| State | UI contract | Backend authority |
|---|---|---|
| `LOADING` | Skeleton / loading rows, disable duplicate submit | none |
| `EMPTY` | Show no-record in **authorized** scope, clear filters | SQL-PBAC filtered |
| `403` | Permission-denied page/row, do not show guessed data | final PBAC |
| `404` | Not-found without revealing out-of-scope existence | canonical owner lookup |
| `409` | Preserve full draft, show conflict, provide explicit reload/reconcile | idempotency/CAS |
| `422` | Field-level validation mapped to stable code | server validation |
| `FAILED_PROVIDER` | Retriable status with history; do not submit twice blindly | provider gateway/inbox |
| `POSTED` | Read-only posting fields, show reversal/adjustment action if permitted | immutable ledger |
| `INACTIVE` | Historical reference readable; unavailable for new selection | resource status/Company usage |

**Form design:** List → detail/drawer → editor; use explicit save and guarded lifecycle actions. Before submit, clear user feedback about derived Base Qty, Company/Warehouse scope, Price Source and any COMBO root/leaf effect. After 409, never discard entered lines or re-create the order.

## 4. Cross-module interaction flows

- **Purchase:** PO editor picks active Supplier usable in Company; Warehouse options narrowed to same Company; show source UOM→base qty. Receipt editor displays `ordered vs received vs remaining`, partial acceptance and serial entry. Only separate **Post** button creates stock impact.
- **Inventory:** Balance view presents On-hand/Reserved/Available; reserved rows link to Sales Orders. Serial view shows current location, movement chronology. Transfer editor validates same Company and stock before posting.
- **Sales:** Direct order editor previews Core Price/Promotion and COMBO leaf allocation; Marketplace order detail shows External Price Source and disables ERP recalculation. Confirm shows reservation preview; Issue screens require serial selection for serial-managed leaves.
- **Shipment/COD:** Shipping view includes carrier status and attempts, label/tracking, cancellation; COD shows Due/Collected/Fee/Net/Actual/Difference with link to posted Money Receipt (not a silent posting event).
- **Finance:** Separate Receipt and Expense views; Account opening date/balance; summary by Company/account/date/category, only posted transactions. Corrections via explicit reversal and source trace.
- **Document:** Attachment panel can be embedded into Product/Employee (P1) and P2 transaction details, but owner authorization governs each download; file version metadata visible, no raw storage URL.

## 5. Permissions in UI

- Hiding create/post/reconcile controls from menu is only a convenience. Server validates `resource.action` final PBAC, Workflow State and Company on every request. `view` does not confer `export`; `edit` not `post`.
- Row actions must be based on server-side effective actions where available; no homemade frontend predicate overriding PBAC.
- URL direct access cannot bypass Organization; selected Company filter is only a filter and never an authority for existing target.

## 6. Performance, accessibility, i18n and resilience

- Pagination on server, lazy detail/attachments/serial timelines; avoid unbounded fetching of inventory transaction history.
- Label every input with accessible text, keyboard navigation in complex tables, time displays with timezone indicator when necessary; missing translation key is release blocker.
- Large list uses stable `id`, abort stale requests on filter change, display approximate freshness of stock/reconciliation without treating it as transactional truth.
- `v-model` numeric display must not be used as business calculation input; parse canonical decimal strings in service/domain layer; do not round at display precision.
- Two independent concurrent sessions: test optimistic conflict and preservation of draft; one must not post or confirm twice.

## 7. Frontend acceptance checklist

1. Every P2-UI screen has loading/empty/not-authorized/not-found/409/422/5xx states; vi/en text resource-based.
2. Deep-link guards + permission-aware navigation (no accidental resource data leak on restricted initial request).
3. Critical flows screenshot/E2E proof: GR partial POST; serial transfer; confirm/reserve; issue/consume; marketplace duplicate; COD vs money; balance report.
4. DOM does not render raw bank secrets/webhook payload/password, file signed URL, insecure HTML.
5. Existing P1 screens and generated client pass regression after adding routes; no `pnpm` generated-client hand edits.
