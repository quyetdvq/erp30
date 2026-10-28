# 12 — P2 Test Strategy, UAT Matrix & Evidence Requirements

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Coverage scope and testing status

- **147/147** P2 source Acceptance Criteria indexed with `P2-AT-*`, each mapping one `P2-FR-*` and a proposed DB/API/UI component. **None of these P2 tests has been executed** on v1.9.0 because no P2 implementation is in the source.
- Additional defense-in-depth negative/concurrency cases `P2-NEG-*` are **proposed engineering tests**, not newly approved business UR.
- Test layers: pure domain unit, MariaDB integration with real transactions, HTTP+PBAC/CSRF/OpenAPI contract, Vue component/E2E, partner sandbox event fixtures, recoverability/backup and UAT.
- P1 regression is mandatory: Product, Pricing, COMBO, Numbering, PBAC, Workflow, Settings, Import, Contacts. P1 pure tests alone never replace P2 live integration.

## 2. Standard fixtures (DRAFT, no real database altered)

| Fixture | Purpose |
|---|---|
| Group G, Company A+B and Warehouses A1+A2+B1 | authorized Company scope and cross-Company negative |
| User U1 with active Group Assignment and Company A Department; User U2 in Company B | PBAC inheritance, same Company multi-role |
| User U3 with Base Permission but Group ABSOLUTE_DENY | cannot bypass with role/privileged group |
| Product SIMPLE P1 + CHILD P2, PARENT P0, COMBO C1→C2→P1 with repeated leaf | UOM/price/leaf/serial and Combo allocation |
| Alternate UOM `BOX -> PCS`, independent price; serial leaf S1/S2 | price and conversion correctness |
| Customer C_A usable only Company A, C_ALL usable all; Supplier S_A only A | Company usage tests |
| Price List Company A and Group with overlapping period/UOM | deterministic fallback/priority |
| External Shop shopee-A, shopee-B, TikTok-T; duplicate order ID | unique business key test |
| Carrier settlement with 2 shipments and variable fees | COD allocation and finance trace |
| Opening Cash A account and posted/draft receipts/expenses | balance and operational finance |

## 3. Gate-specific test plan

| Gate | Mandatory tests | Evidence |
|---|---|---|
| G1 | Customer/Supplier duplicate global code, usage, inactive, Contacts owner; Document links/version/download auth | DB snapshots + HTTP permission log + UI screenshots |
| G2 | PR approval, PO Company scope, partial receipt/return, serial uniqueness, warehouse transfer pair, ledger equality | MariaDB locking/concurrency + posting-key evidence |
| G3 | Price List UOM/context, Promo stacking, COMBO allocation/residual, confirm/reserve, partial issue/consume | Pure + DB transaction + E2E sequence |
| G4 | Marketplace duplicate parallel events, provider auth, mapping errors, Carrier label retry, COD vs posted Finance | signed provider fixture + reconciliation ledger |
| G5 | Every source AC, all high-risk negative, full P1 regression, migration backup/restore and on-call drill | UAT signed matrix and release checklist |

## 4. Concurrency and failure injection must-pass cases

- Duplicate GR/Issue/Return POST with same posting key; one physical effect only; simulated failure after partial row mutation must rollback entire transaction.
- Two Sales Orders confirm when `OnHand=10`, `Reserved=0`, each requires 7: at most one reserves 7; no oversell. Simultaneous release+issue preserves `reserved-consumed-released≥0`.
- Same Shopee `(channel,shop,externalOrderId)` ingested twice concurrently; exactly one ERP SO, one link and durable error/correlation history. Different Shops with same ID may be distinct.
- Serial Product code S1 cannot be posted to Warehouse B if currently in A without transfer; transfer pair atomic and latest location matches ledger.
- Finance posted Receipt and Expense double-click vs same source → no double increment/decrement, reversal only separate document.
- Unauthorized SQL counts/sort/pagination never include out-of-scope Company rows; field enumeration cannot leak bank/provider credentials.
- Provider webhook unknown signature, replay, expired timestamp, malformed JSON, out-of-order status, provider timeout; all contain safe status without stock/finance mutation.

## 5. Per-criterion acceptance ledger

**Evidence rule:** set `implementation_status` to `WRITTEN` only after checked-in test code; set `execution_status=PASS` only with run log, environment, schema version, commit SHA, actor and assertion outputs. `NOT_RUN` ≠ `PASS`; if blocked, use `BLOCKED` plus decision ID; if accepted waiver, link signed Change ID.

| Acceptance ID | UR | Original acceptance criterion | Evidence |
|---|---|---|---|
| `P2-AT-DOC-001-01` | `UR-DOC-001` | File xác định được đối tượng nghiệp vụ liên quan. | NOT_RUN |
| `P2-AT-DOC-001-02` | `UR-DOC-001` | Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ. | NOT_RUN |
| `P2-AT-DOC-002-01` | `UR-DOC-002` | Có thể xem version hiện tại và version trước. | NOT_RUN |
| `P2-AT-DOC-002-02` | `UR-DOC-002` | Tài liệu bị thay thế không làm mất lịch sử. | NOT_RUN |
| `P2-AT-CUS-001-01` | `UR-CUS-001` | Customer Code là duy nhất trong Group. | NOT_RUN |
| `P2-AT-CUS-001-02` | `UR-CUS-001` | Customer có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng. | NOT_RUN |
| `P2-AT-CUS-001-03` | `UR-CUS-001` | Company ngoài Customer Usage Scope không được dùng Customer cho giao dịch mới. | NOT_RUN |
| `P2-AT-CUS-001-04` | `UR-CUS-001` | Khi triển khai Customer ở P2, Customer có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Customer. | NOT_RUN |
| `P2-AT-CUS-001-05` | `UR-CUS-001` | Customer inactive không được chọn cho giao dịch mới nhưng lịch sử giao dịch/liên hệ vẫn tra cứu được. | NOT_RUN |
| `P2-AT-CUS-002-01` | `UR-CUS-002` | Lưu được note/lịch sử liên hệ. | NOT_RUN |
| `P2-AT-CUS-002-02` | `UR-CUS-002` | Xem được Sales Order/Shipment/Sales Return/Thu tiền liên quan trong phạm vi quyền. | NOT_RUN |
| `P2-AT-CUS-002-03` | `UR-CUS-002` | Không yêu cầu Lead Management hoặc Opportunity Pipeline. | NOT_RUN |
| `P2-AT-PRC-002-01` | `UR-PRC-002` | Promotion chỉ áp dụng trong thời gian hiệu lực và khi đủ điều kiện. | NOT_RUN |
| `P2-AT-PRC-002-02` | `UR-PRC-002` | Sales Order lưu được promotion/coupon đã áp dụng. | NOT_RUN |
| `P2-AT-PRC-002-03` | `UR-PRC-002` | Special Price được xử lý trước Discount/Promotion khác. | NOT_RUN |
| `P2-AT-PRC-002-04` | `UR-PRC-002` | Promotion được evaluate theo Priority và không được tạo stacking tùy ý ngoài rule đã cấu hình. | NOT_RUN |
| `P2-AT-PRC-002-05` | `UR-PRC-002` | Promotion có `Combinable=false` không được kết hợp với Promotion/Coupon khác trái với rule. | NOT_RUN |
| `P2-AT-PRC-002-06` | `UR-PRC-002` | Nếu các Promotion cùng Priority và scope có thể tạo kết quả không xác định, cấu hình phải bị từ chối hoặc được điều chỉnh trước khi active. | NOT_RUN |
| `P2-AT-SUP-001-01` | `UR-SUP-001` | Supplier Code là duy nhất trong Group. | NOT_RUN |
| `P2-AT-SUP-001-02` | `UR-SUP-001` | Supplier có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng. | NOT_RUN |
| `P2-AT-SUP-001-03` | `UR-SUP-001` | Company ngoài Supplier Usage Scope không được dùng Supplier cho giao dịch mới. | NOT_RUN |
| `P2-AT-SUP-001-04` | `UR-SUP-001` | Khi triển khai Supplier ở P2, Supplier có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Supplier. | NOT_RUN |
| `P2-AT-SUP-001-05` | `UR-SUP-001` | Supplier inactive không được chọn cho giao dịch mới nhưng lịch sử vẫn tra cứu được. | NOT_RUN |
| `P2-AT-SUP-002-01` | `UR-SUP-002` | Chỉ hiển thị lịch sử thuộc Company nằm trong authorization scope của User. | NOT_RUN |
| `P2-AT-SUP-002-02` | `UR-SUP-002` | Có thể điều hướng từ Supplier tới chứng từ nguồn liên quan khi User có quyền VIEW chứng từ đó. | NOT_RUN |
| `P2-AT-SUP-002-03` | `UR-SUP-002` | Lịch sử vẫn tra cứu được khi Supplier đã inactive. | NOT_RUN |
| `P2-AT-PUR-001-01` | `UR-PUR-001` | PR có thể Submit/Approve/Reject theo PBAC. | NOT_RUN |
| `P2-AT-PUR-001-02` | `UR-PUR-001` | PR Approved có thể dùng để tạo Purchase Order. | NOT_RUN |
| `P2-AT-PUR-002-01` | `UR-PUR-002` | PO tuân theo PBAC theo Company/Resource/State/Action. | NOT_RUN |
| `P2-AT-PUR-002-02` | `UR-PUR-002` | UOM phải hợp lệ cho Product; Quantity phải có thể quy đổi về Base UOM khi sử dụng Alternate UOM của SIMPLE/CHILD. | NOT_RUN |
| `P2-AT-PUR-002-03` | `UR-PUR-002` | COMBO có thể được chọn như template để nhập nhanh Purchase line, nhưng trước receiving/inventory/cost operational processing phải expand về SIMPLE/CHILD; COMBO selling-price allocation không được tự dùng làm purchase-price allocation. | NOT_RUN |
| `P2-AT-PUR-002-04` | `UR-PUR-002` | Purchase price/cost của các leaf sau expansion phải được xác định theo nghiệp vụ Purchase tương ứng, không suy từ COMBO selling price nếu chưa có requirement riêng. | NOT_RUN |
| `P2-AT-PUR-002-05` | `UR-PUR-002` | PO giữ lịch sử phê duyệt và thay đổi quan trọng. | NOT_RUN |
| `P2-AT-PUR-003-01` | `UR-PUR-003` | Chỉ Goods Receipt đã đạt posting state của module (`POSTED` hoặc trạng thái `CONFIRMED` được định nghĩa tương đương posting) mới làm tăng On-hand Inventory. | NOT_RUN |
| `P2-AT-PUR-003-02` | `UR-PUR-003` | Goods Receipt cập nhật Received Quantity của Purchase Order nguồn. | NOT_RUN |
| `P2-AT-PUR-003-03` | `UR-PUR-003` | Product serial-managed bắt buộc ghi Serial khi Goods Receipt được post. | NOT_RUN |
| `P2-AT-PUR-003-04` | `UR-PUR-003` | Không nhận vào Warehouse thuộc Company khác Purchase Order. | NOT_RUN |
| `P2-AT-PUR-003-05` | `UR-PUR-003` | Goods Receipt đã post không được delete/edit trực tiếp các dữ liệu làm thay đổi stock; correction phải qua reversal hoặc Inventory Adjustment phù hợp. | NOT_RUN |
| `P2-AT-PUR-004-01` | `UR-PUR-004` | Chỉ Purchase Return đã đạt posting state mới làm giảm On-hand Inventory. | NOT_RUN |
| `P2-AT-PUR-004-02` | `UR-PUR-004` | Serial-managed item phải xác định đúng Serial trả. | NOT_RUN |
| `P2-AT-PUR-004-03` | `UR-PUR-004` | Có thể truy về Goods Receipt/Purchase Order nguồn. | NOT_RUN |
| `P2-AT-PUR-004-04` | `UR-PUR-004` | Purchase Return đã post không được delete/edit trực tiếp làm thay đổi stock history; correction phải qua reversal/adjustment phù hợp. | NOT_RUN |
| `P2-AT-INV-001-01` | `UR-INV-001` | Có thể xem On-hand, Reserved và Available theo từng Warehouse. | NOT_RUN |
| `P2-AT-INV-001-02` | `UR-INV-001` | Inventory balance chỉ tồn tại cho SIMPLE/CHILD leaf; COMBO không có On-hand/Reserved/Available riêng. | NOT_RUN |
| `P2-AT-INV-001-03` | `UR-INV-001` | On-hand chỉ phản ánh stock movement đã được post/confirm theo posting rule. | NOT_RUN |
| `P2-AT-INV-001-04` | `UR-INV-001` | Có thể xem tồn Group bằng tổng hợp Company được phép. | NOT_RUN |
| `P2-AT-INV-001-05` | `UR-INV-001` | Không trộn tồn giữa các Company. | NOT_RUN |
| `P2-AT-INV-002-01` | `UR-INV-002` | Chỉ movement ở posting state (`POSTED` hoặc module-defined `CONFIRMED` tương đương posting) mới làm thay đổi On-hand Qty. | NOT_RUN |
| `P2-AT-INV-002-02` | `UR-INV-002` | Inventory movement chỉ post SIMPLE/CHILD leaf; nếu source document sử dụng COMBO thì phải flatten trước khi tạo/post stock movement. | NOT_RUN |
| `P2-AT-INV-002-03` | `UR-INV-002` | Draft/Pending movement không làm thay đổi On-hand Qty. | NOT_RUN |
| `P2-AT-INV-002-04` | `UR-INV-002` | Movement đã post không được delete hoặc sửa trực tiếp quantity/product/warehouse làm thay đổi stock history. | NOT_RUN |
| `P2-AT-INV-002-05` | `UR-INV-002` | Correction sau posting phải được thực hiện bằng reversal hoặc Inventory Adjustment có traceability. | NOT_RUN |
| `P2-AT-INV-002-06` | `UR-INV-002` | Mỗi posted movement truy được Source Document, User và Timestamp. | NOT_RUN |
| `P2-AT-INV-003-01` | `UR-INV-003` | Source và destination Warehouse phải cùng Company. | NOT_RUN |
| `P2-AT-INV-003-02` | `UR-INV-003` | Cross-Company movement không được xử lý như internal warehouse transfer. | NOT_RUN |
| `P2-AT-INV-003-03` | `UR-INV-003` | Chỉ Transfer đã đạt posting state mới làm thay đổi stock location/balance. | NOT_RUN |
| `P2-AT-INV-003-04` | `UR-INV-003` | Posted Transfer phải giữ trace giữa source và destination movement. | NOT_RUN |
| `P2-AT-INV-004-01` | `UR-INV-004` | Adjustment chỉ được ghi nhận theo PBAC. | NOT_RUN |
| `P2-AT-INV-004-02` | `UR-INV-004` | Adjustment chỉ làm thay đổi On-hand khi đạt posting state. | NOT_RUN |
| `P2-AT-INV-004-03` | `UR-INV-004` | Có thể truy từ Adjustment về Stock Count và người xác nhận. | NOT_RUN |
| `P2-AT-INV-004-04` | `UR-INV-004` | Posted Adjustment không được delete/edit trực tiếp làm thay đổi stock history. | NOT_RUN |
| `P2-AT-INV-005-01` | `UR-INV-005` | Serial xác định được Product, Company, Warehouse hiện tại và trạng thái. | NOT_RUN |
| `P2-AT-INV-005-02` | `UR-INV-005` | Serial truy được Goods Receipt, Sales Issue, transfer, purchase return và sales return liên quan. | NOT_RUN |
| `P2-AT-INV-005-03` | `UR-INV-005` | Một Serial không thể đồng thời tồn tại ở nhiều Warehouse. | NOT_RUN |
| `P2-AT-INV-005-04` | `UR-INV-005` | Goods Receipt của Product serial-managed phải ghi nhận Serial trước khi posting. | NOT_RUN |
| `P2-AT-INV-005-05` | `UR-INV-005` | Sales Issue của Product serial-managed phải xác định Serial xuất. | NOT_RUN |
| `P2-AT-INV-005-06` | `UR-INV-005` | Warehouse Transfer phải chuyển trạng thái/location của đúng Serial. | NOT_RUN |
| `P2-AT-INV-005-07` | `UR-INV-005` | Purchase Return và Sales Return phải tham chiếu đúng Serial khi áp dụng. | NOT_RUN |
| `P2-AT-INV-006-01` | `UR-INV-006` | Reservation xác định Company, Warehouse, Product, Reserved Quantity và Source Sales Order/Order Line. | NOT_RUN |
| `P2-AT-INV-006-02` | `UR-INV-006` | Reservation chỉ giữ quantity của SIMPLE/CHILD leaf; COMBO phải được flatten trước reservation. | NOT_RUN |
| `P2-AT-INV-006-03` | `UR-INV-006` | Reservation được tạo khi Sales Order đạt trạng thái xác nhận vận hành theo lifecycle của module. | NOT_RUN |
| `P2-AT-INV-006-04` | `UR-INV-006` | Cancel Order hoặc giảm quantity phải release phần reservation không còn cần. | NOT_RUN |
| `P2-AT-INV-006-05` | `UR-INV-006` | Partial shipment/Sales Issue consume phần reservation tương ứng và giữ reservation cho phần chưa xử lý. | NOT_RUN |
| `P2-AT-INV-006-06` | `UR-INV-006` | Khi Sales Issue được post, phần reservation được consume đồng thời với stock issue liên quan. | NOT_RUN |
| `P2-AT-INV-006-07` | `UR-INV-006` | Reservation không tự làm thay đổi On-hand Qty. | NOT_RUN |
| `P2-AT-INV-006-08` | `UR-INV-006` | Baseline không cho reservation mới làm Available Qty âm; trường hợp overselling cần requirement/change request riêng. | NOT_RUN |
| `P2-AT-INV-006-09` | `UR-INV-006` | Với Product serial-managed, baseline reserve theo quantity; Serial cụ thể được chọn và validate khi Sales Issue được post. | NOT_RUN |
| `P2-AT-SAL-001-01` | `UR-SAL-001` | Direct/Internal Sales sử dụng Price Resolution theo Product + UOM + Company + Effective Time; Sales Order lưu giá cuối cùng, UOM và Price Source `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP` hoặc `PRODUCT_DEFAULT`. | NOT_RUN |
| `P2-AT-SAL-001-02` | `UR-SAL-001` | Direct/Internal Sales cho phép chọn COMBO như template. Khi chọn COMBO, hệ thống resolve giá root COMBO, flatten về SIMPLE/CHILD, phân bổ selling amount và aggregate leaf theo UR-PRD-006 trước các bước Reservation/Inventory/Serial/Costing/Posting. | NOT_RUN |
| `P2-AT-SAL-001-03` | `UR-SAL-001` | Sales Order phải giữ đủ traceability giữa leaf line và COMBO nguồn khi COMBO được dùng để tạo nhanh line. | NOT_RUN |
| `P2-AT-SAL-001-04` | `UR-SAL-001` | Marketplace Sales từ Shopee/TikTok Shop sử dụng external channel price/discount authoritative và lưu Price Source `EXTERNAL_CHANNEL`. | NOT_RUN |
| `P2-AT-SAL-001-05` | `UR-SAL-001` | Sales Order có Price Source `EXTERNAL_CHANNEL` không bị ERP Price Resolution/Promotion recompute lại giá marketplace. | NOT_RUN |
| `P2-AT-SAL-001-06` | `UR-SAL-001` | Sales Order lưu được nguồn giá, UOM và promotion/discount reference phù hợp với loại order. | NOT_RUN |
| `P2-AT-SAL-001-07` | `UR-SAL-001` | Delivery address sử dụng Province/City Code và Ward/Commune Code khi áp dụng. | NOT_RUN |
| `P2-AT-SAL-001-08` | `UR-SAL-001` | Sales Order tuân theo PBAC theo Company/Resource/State/Action. | NOT_RUN |
| `P2-AT-SAL-002-01` | `UR-SAL-002` | Lưu Shopee Order ID và Shop/Account source. | NOT_RUN |
| `P2-AT-SAL-002-02` | `UR-SAL-002` | Mapping SKU/Product. | NOT_RUN |
| `P2-AT-SAL-002-03` | `UR-SAL-002` | Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order. | NOT_RUN |
| `P2-AT-SAL-002-04` | `UR-SAL-002` | Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008. | NOT_RUN |
| `P2-AT-SAL-002-05` | `UR-SAL-002` | Giá/discount từ Shopee là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`. | NOT_RUN |
| `P2-AT-SAL-002-06` | `UR-SAL-002` | Đồng bộ trạng thái cần thiết. | NOT_RUN |
| `P2-AT-SAL-002-07` | `UR-SAL-002` | Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order. | NOT_RUN |
| `P2-AT-SAL-003-01` | `UR-SAL-003` | Lưu TikTok Shop Order ID và Shop/Account source. | NOT_RUN |
| `P2-AT-SAL-003-02` | `UR-SAL-003` | Mapping SKU/Product. | NOT_RUN |
| `P2-AT-SAL-003-03` | `UR-SAL-003` | Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order. | NOT_RUN |
| `P2-AT-SAL-003-04` | `UR-SAL-003` | Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008. | NOT_RUN |
| `P2-AT-SAL-003-05` | `UR-SAL-003` | Giá/discount từ TikTok Shop là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`. | NOT_RUN |
| `P2-AT-SAL-003-06` | `UR-SAL-003` | Đồng bộ trạng thái cần thiết. | NOT_RUN |
| `P2-AT-SAL-003-07` | `UR-SAL-003` | Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order. | NOT_RUN |
| `P2-AT-SAL-004-01` | `UR-SAL-004` | Không tạo kho logic riêng cạnh tranh chỉ vì khác sales channel. | NOT_RUN |
| `P2-AT-SAL-004-02` | `UR-SAL-004` | Order phải kiểm tra Available Qty theo Company/Warehouse phù hợp. | NOT_RUN |
| `P2-AT-SAL-004-03` | `UR-SAL-004` | Order được xác nhận phải reserve stock vào Inventory chung theo UR-INV-006. | NOT_RUN |
| `P2-AT-SAL-004-04` | `UR-SAL-004` | Release/consume reservation phải phản ánh vào Inventory chung. | NOT_RUN |
| `P2-AT-SAL-004-05` | `UR-SAL-004` | Sales Issue đã post làm giảm On-hand theo stock posting rule. | NOT_RUN |
| `P2-AT-SAL-005-01` | `UR-SAL-005` | Shipment thuộc cùng Company với Sales Order nguồn. | NOT_RUN |
| `P2-AT-SAL-005-02` | `UR-SAL-005` | Shipment truy được Sales Order và Warehouse fulfillment liên quan. | NOT_RUN |
| `P2-AT-SAL-005-03` | `UR-SAL-005` | Tracking Number và delivery status được lưu khi carrier trả về. | NOT_RUN |
| `P2-AT-SAL-005-04` | `UR-SAL-005` | Hủy vận đơn không tự xóa Shipment hoặc lịch sử carrier interaction. | NOT_RUN |
| `P2-AT-SAL-005-05` | `UR-SAL-005` | Shipping Fee và COD Amount được lưu để phục vụ COD Reconciliation khi áp dụng. | NOT_RUN |
| `P2-AT-SAL-005-06` | `UR-SAL-005` | Carrier integration error phải được ghi nhận để retry/xử lý. | NOT_RUN |
| `P2-AT-SAL-006-01` | `UR-SAL-006` | Có thể lọc các shipment chưa đối soát. | NOT_RUN |
| `P2-AT-SAL-006-02` | `UR-SAL-006` | Chênh lệch phải xác định được amount và trạng thái xử lý. | NOT_RUN |
| `P2-AT-SAL-006-03` | `UR-SAL-006` | Khoản COD đã đối soát có thể tạo/liên kết Money Receipt. | NOT_RUN |
| `P2-AT-SAL-007-01` | `UR-SAL-007` | Sales Return truy được Sales Order/Shipment nguồn khi có. | NOT_RUN |
| `P2-AT-SAL-007-02` | `UR-SAL-007` | Returned goods chỉ làm tăng On-hand khi inventory return movement được post/confirm theo posting rule. | NOT_RUN |
| `P2-AT-SAL-007-03` | `UR-SAL-007` | Product serial-managed phải validate đúng Serial trả lại. | NOT_RUN |
| `P2-AT-SAL-007-04` | `UR-SAL-007` | Refund hoặc giao dịch Thu/Chi liên quan được reference, không tự tạo double-entry accounting. | NOT_RUN |
| `P2-AT-SAL-007-05` | `UR-SAL-007` | Return đã post không được xóa/sửa trực tiếp làm thay đổi stock history. | NOT_RUN |
| `P2-AT-SAL-008-01` | `UR-SAL-008` | Mỗi Shop/Account xác định được Sales Channel/Platform. | NOT_RUN |
| `P2-AT-SAL-008-02` | `UR-SAL-008` | Mỗi Shop/Account map đúng một Company tại một thời điểm hiệu lực. | NOT_RUN |
| `P2-AT-SAL-008-03` | `UR-SAL-008` | Shop/Account có fulfillment Warehouse mặc định hoặc mapping Warehouse rõ ràng trước khi tạo Sales Order. | NOT_RUN |
| `P2-AT-SAL-008-04` | `UR-SAL-008` | `Sales Channel + Shop/Account + External Order ID` là unique business key. | NOT_RUN |
| `P2-AT-SAL-008-05` | `UR-SAL-008` | Retry/sync lại cùng external order không được tạo duplicate ERP Sales Order. | NOT_RUN |
| `P2-AT-SAL-008-06` | `UR-SAL-008` | Sync/retry phải idempotent đối với create/link order. | NOT_RUN |
| `P2-AT-SAL-008-07` | `UR-SAL-008` | Mapping Company/Warehouse phải hoàn tất trước khi ERP Sales Order được tạo. | NOT_RUN |
| `P2-AT-SAL-008-08` | `UR-SAL-008` | Lỗi mapping hoặc sync được giữ trạng thái/lý do để reprocess. | NOT_RUN |
| `P2-AT-FIN-001-01` | `UR-FIN-001` | Mỗi Money Receipt/Money Expense thuộc đúng một Company. | NOT_RUN |
| `P2-AT-FIN-001-02` | `UR-FIN-001` | Amount phải lớn hơn 0. | NOT_RUN |
| `P2-AT-FIN-001-03` | `UR-FIN-001` | Account sử dụng phải thuộc cùng Company với transaction. | NOT_RUN |
| `P2-AT-FIN-001-04` | `UR-FIN-001` | Chỉ transaction đã `POSTED` mới tham gia operational balance. | NOT_RUN |
| `P2-AT-FIN-001-05` | `UR-FIN-001` | Transaction đã post không được xóa/sửa trực tiếp amount/account làm thay đổi history; correction phải qua reversal/adjustment có traceability. | NOT_RUN |
| `P2-AT-FIN-002-01` | `UR-FIN-002` | Category xác định được loại `RECEIPT` hoặc `EXPENSE`. | NOT_RUN |
| `P2-AT-FIN-002-02` | `UR-FIN-002` | Category inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được. | NOT_RUN |
| `P2-AT-FIN-002-03` | `UR-FIN-002` | Có thể lọc/tổng hợp Thu/Chi theo category. | NOT_RUN |
| `P2-AT-FIN-003-01` | `UR-FIN-003` | Mỗi account thuộc một Company. | NOT_RUN |
| `P2-AT-FIN-003-02` | `UR-FIN-003` | Opening Balance và Opening Date được xác định trước khi tính operational balance từ thời điểm đó. | NOT_RUN |
| `P2-AT-FIN-003-03` | `UR-FIN-003` | User chỉ thao tác account trong PBAC scope. | NOT_RUN |
| `P2-AT-FIN-003-04` | `UR-FIN-003` | Account inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được. | NOT_RUN |
| `P2-AT-FIN-004-01` | `UR-FIN-004` | Chỉ posted Money Receipt/Money Expense được tính vào operational balance. | NOT_RUN |
| `P2-AT-FIN-004-02` | `UR-FIN-004` | Opening/Closing Balance không suy diễn từ General Ledger. | NOT_RUN |
| `P2-AT-FIN-004-03` | `UR-FIN-004` | Có thể tính balance theo từng Cash/Bank Account và tổng hợp các account trong Company theo filter. | NOT_RUN |
| `P2-AT-FIN-004-04` | `UR-FIN-004` | Report theo nhiều Company chỉ tổng hợp Company User có quyền xem. | NOT_RUN |
| `P2-AT-FIN-004-05` | `UR-FIN-004` | Operational Balance không được mô tả như bank reconciliation hoặc statutory accounting balance. | NOT_RUN |
| `P2-AT-FIN-005-01` | `UR-FIN-005` | Có thể truy từ Money Receipt/Money Expense về Source Reference khi nguồn còn tồn tại và User có quyền. | NOT_RUN |
| `P2-AT-FIN-005-02` | `UR-FIN-005` | Một source reference không tự tạo double-entry journal. | NOT_RUN |
| `P2-AT-FIN-005-03` | `UR-FIN-005` | COD đã reconciliation có thể tạo/liên kết Money Receipt mà vẫn giữ Shipment/COD reference. | NOT_RUN |
| `P2-AT-FIN-005-04` | `UR-FIN-005` | Payroll payment có thể liên kết Money Expense theo đúng Company. | NOT_RUN |

## 6. Additional engineering negative tests (non-UR derived)

| Test | Domain | When | Expected |
|---|---|---|---|
| `P2-NEG-001` | PBAC | Actor no Base Permission -> any P2 data mutation | DENY; no business write |
| `P2-NEG-002` | PBAC | Group ABSOLUTE_DENY vs assignment ALLOW | DENY |
| `P2-NEG-003` | PBAC | Company A user query Company B balances | 0 B rows; authorized count only |
| `P2-NEG-004` | PBAC | Client evaluation_organization_id mismatches persisted target | DENY or structured mismatch; no override |
| `P2-NEG-005` | PBAC | VIEW granted but EXPORT absent | 403 export |
| `P2-NEG-006` | DOC | Guessed attachment id but owner inaccessible | deny download and metadata |
| `P2-NEG-007` | DOC | New file version upload succeeds but DB write fails | orphan object cleanup; current pointer unchanged |
| `P2-NEG-008` | CUS | Inactive customer used on new SO | reject; historic history readable |
| `P2-NEG-009` | CUS | Customer all_companies=false but Company not included | reject new SO |
| `P2-NEG-010` | SUP | Supplier Company A scope but PO Company B | reject before writing |
| `P2-NEG-011` | PRC | Company exact UOM item missing, Group item exists | fallback Group; not Company wrong UOM |
| `P2-NEG-012` | PRC | Conflicting same-scope same-priority promo combinations | reject config or deterministic approved choice |
| `P2-NEG-013` | PRC | COMBO zero allocation weight | validation reject |
| `P2-NEG-014` | PRC | Nested COMBO leaf price list re-priced | reject test, single root price only |
| `P2-NEG-015` | PUR | Two concurrent GR posts exceed PO remaining | one fails, received consistent |
| `P2-NEG-016` | PUR | GR posting fails after stock movement insert | rollback all including PO received |
| `P2-NEG-017` | PUR | PO receiving Warehouse belongs another Company | reject |
| `P2-NEG-018` | PUR | Purchase COMBO leaf purchase cost undefined | reject path until ADR |
| `P2-NEG-019` | INV | SalesOrder A+B simultaneous reserve same limited stock | no negative available; loser fails |
| `P2-NEG-020` | INV | Retry Posted GoodsReceipt | single movement key |
| `P2-NEG-021` | INV | Transfer between different Company warehouses | reject |
| `P2-NEG-022` | INV | Transfer write source movement success dest failure | rollback both |
| `P2-NEG-023` | INV | Serial issued from wrong warehouse | reject and no ledger update |
| `P2-NEG-024` | INV | Count stale snapshot then adjust without rule | reject/recount according to approved policy |
| `P2-NEG-025` | INV | POSTED movement product/qty PATCH | reject immutable |
| `P2-NEG-026` | INV | COMBO appears in inventory ledger | reject invariant |
| `P2-NEG-027` | SAL | Repeat identical external order in same Shop | one ERP SO |
| `P2-NEG-028` | SAL | Same external ID across different shops | distinct legitimate orders |
| `P2-NEG-029` | SAL | Unmapped Shop/Company or SKU | failed inbox, no ERP SO |
| `P2-NEG-030` | SAL | Marketplace EXTERNAL_CHANNEL price re-resolved via P1 | reject; source price preserved |
| `P2-NEG-031` | SAL | Cancel carrier label after issue posted | retain shipment and stock trace |
| `P2-NEG-032` | SAL | Sales Issue posted twice via retry | one reservation consume and stock decrement |
| `P2-NEG-033` | SAL | Wrong returned Serial | reject and no stock increase |
| `P2-NEG-034` | SAL | COD reconciliation marked matched without Money Receipt posting | no cash balance change |
| `P2-NEG-035` | FIN | Draft Money Receipt in cash flow report | excluded |
| `P2-NEG-036` | FIN | Money Account Company B selected on Company A receipt | reject |
| `P2-NEG-037` | FIN | Posted money amount PATCH | reject; reversal required |
| `P2-NEG-038` | FIN | Duplicate source COD receipt post | idempotent source link |
| `P2-NEG-039` | INTEGRATION | Bad HMAC/signature webhook | reject before inbox/business mutation |
| `P2-NEG-040` | INTEGRATION | Provider label times out after remote success | inspect provider ref before second label |
| `P2-NEG-041` | INTEGRATION | Out-of-order carrier delivery event | retain timeline, no rollback stock automatically |
| `P2-NEG-042` | OPERATIONS | No DB backup/restore proof before migration | block release |

## 7. Accept/reject and signoff

- UR `MUST`: all criteria PASS or documented formal approved waiver/change. UR `SHOULD` (`UR-DOC-002`): still tracked and explicitly decided, not omitted.
- Failure in PBAC Company separation, duplicate stock/money posting, serial uniqueness, reservation negative balance, external order duplication, credential leak or inability to recover is release BLOCKER.
- Attach QA run manifests: source commit, DB schema/preflight state, actor identities anonymized, fixtures, test command, timestamp+timezone, report/log, actual results, defect links and sign-off date.
- A UI screenshot or source function is **not sufficient** to certify transaction correctness; use DB+HTTP evidence with concurrency where needed.
