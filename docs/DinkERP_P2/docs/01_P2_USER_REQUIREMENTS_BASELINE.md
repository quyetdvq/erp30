# 01 — User Requirements độc lập P2 (trích xuất nguyên văn)

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** BASELINE-UR: phần 30 UR trích nguyên văn; phần phụ lục giải thích nguồn
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Phạm vi và tiêu chí toàn vẹn

Bản trích tách **toàn bộ 30 User Requirements có `Target phase: P2`**, gồm tiêu đề, Priority, Status, mô tả, ví dụ, lifecycle, công thức và Acceptance criteria. Nội dung thân mỗi UR giữ nguyên theo văn bản gốc, không chuẩn hóa câu chữ hoặc cập nhật acceptance không được duyệt. Các section ngoài UR được thêm riêng như các ràng buộc liên pha, không trộn vào nguyên văn.

- Số UR: **30**, Acceptance Criteria dạng bullet: **147**.
- Phase P1 / P3 không được đưa thành phạm vi triển khai P2 nếu không có một yêu cầu P2/changelog tương ứng.
- **Sự thật quan trọng:** `BASELINE` của UR chỉ phê duyệt requirement, không chứng minh code đã tồn tại.

## Bảng tra cứu

| Nhóm | UR ID | Yêu cầu |
|---|---|---|
| DOC | `UR-DOC-001` | Attachment theo đối tượng nghiệp vụ |
| DOC | `UR-DOC-002` | Tìm kiếm và version tài liệu |
| CUS | `UR-CUS-001` | Customer Master |
| CUS | `UR-CUS-002` | Customer History |
| PRC | `UR-PRC-002` | Promotion |
| SUP | `UR-SUP-001` | Supplier Master |
| SUP | `UR-SUP-002` | Supplier History |
| PUR | `UR-PUR-001` | Purchase Request |
| PUR | `UR-PUR-002` | Purchase Order |
| PUR | `UR-PUR-003` | Goods Receipt |
| PUR | `UR-PUR-004` | Purchase Return |
| INV | `UR-INV-001` | Inventory Balance |
| INV | `UR-INV-002` | Inventory Movement |
| INV | `UR-INV-003` | Warehouse Transfer |
| INV | `UR-INV-004` | Stock Count và Adjustment |
| INV | `UR-INV-005` | Serial Inventory & Transaction Traceability |
| INV | `UR-INV-006` | Inventory Reservation |
| SAL | `UR-SAL-001` | Sales Order |
| SAL | `UR-SAL-002` | Shopee Integration |
| SAL | `UR-SAL-003` | TikTok Shop Integration |
| SAL | `UR-SAL-004` | Inventory dùng chung cho đa kênh |
| SAL | `UR-SAL-005` | Shipment và Carrier |
| SAL | `UR-SAL-006` | COD Reconciliation |
| SAL | `UR-SAL-007` | Sales Return |
| SAL | `UR-SAL-008` | Marketplace Account Mapping & Order Idempotency |
| FIN | `UR-FIN-001` | Ghi nhận Thu/Chi |
| FIN | `UR-FIN-002` | Danh mục Thu/Chi |
| FIN | `UR-FIN-003` | Cash và Bank Account |
| FIN | `UR-FIN-004` | Tổng hợp Thu/Chi |
| FIN | `UR-FIN-005` | Liên kết nguồn giao dịch |

---

## Toàn văn nguyên gốc 30 yêu cầu P2

### UR-DOC-001 - Attachment theo đối tượng nghiệp vụ
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Cho phép đính kèm file vào:
- Product
- Customer
- Supplier
- Employee
- Purchase
- Sales
- Inventory
- Finance
- Payroll

**Acceptance criteria**
- File xác định được đối tượng nghiệp vụ liên quan.
- Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ.

### UR-DOC-002 - Tìm kiếm và version tài liệu
**Priority:** SHOULD
**Target phase:** P2
**Status:** BASELINE

Hỗ trợ:
- Upload/Download
- Search
- Tag/Category
- Version
- Uploaded By/At

**Acceptance criteria**
- Có thể xem version hiện tại và version trước.
- Tài liệu bị thay thế không làm mất lịch sử.


---

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

### UR-PRC-002 - Promotion
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Hỗ trợ promotion cơ bản:
- Giảm %
- Giảm số tiền
- Giá đặc biệt (Special Price)
- Quantity-based discount
- Tặng Product
- Coupon

Mỗi Promotion có tối thiểu:
- Effective Period
- Priority
- Eligibility/condition
- `Combinable = true/false`

Baseline **không cho stacking tự do**. Thứ tự xử lý canonical:
1. Xác định Special Price đủ điều kiện khi có.
2. Xử lý Discount/Promotion theo Priority.
3. Coupon chỉ được kết hợp nếu rule và `Combinable` cho phép.

**Acceptance criteria**
- Promotion chỉ áp dụng trong thời gian hiệu lực và khi đủ điều kiện.
- Sales Order lưu được promotion/coupon đã áp dụng.
- Special Price được xử lý trước Discount/Promotion khác.
- Promotion được evaluate theo Priority và không được tạo stacking tùy ý ngoài rule đã cấu hình.
- Promotion có `Combinable=false` không được kết hợp với Promotion/Coupon khác trái với rule.
- Nếu các Promotion cùng Priority và scope có thể tạo kết quả không xác định, cấu hình phải bị từ chối hoặc được điều chỉnh trước khi active.


---

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

---

## Phụ lục A — Những ràng buộc P1 áp dụng khi thực hiện P2

Phụ lục dưới đây là **bản tổng hợp bằng lời khác** của các UR P1 liên quan, không thay thế nguyên văn 30 P2 UR bên trên. Chi tiết nghiệp vụ bắt buộc của P2 được triển khai nhất quán với các nguyên tắc này:

- Một Group root, nhiều Company; mọi business transaction thuộc **đúng một Company**. Product/UOM/Product Group shared Group, Customer/Supplier shared Group nhưng có usage scope Company riêng ở P2.
- Organization context `GROUP/COMPANY/BRANCH/PLANT/WAREHOUSE`, warehouse phải resolve đúng Company; existing target lấy authorization context từ persisted target; client context mismatch phải reject.
- Shield User → Employee → effective assignments; PBAC dùng Base Permission `resource.action` + Policy; không có sysadmin bypass, không tạo resource instance permission, không tạo customer-specific PBAC condition engine.
- Trong từng policy lane: `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; Base Permission vắng mặt luôn DENY; ABSOLUTE_DENY được propagate trước multi-lane intersection; assignment ancestor/group kế thừa; sibling/cross-company không kế thừa.
- P1 Product Model V2: SIMPLE/CHILD là leaf transactionable; PARENT không selectable; COMBO selectable template phải recursive flatten về SIMPLE/CHILD trước stock/serial/cost/posting, graph acyclic. COMBO root selling amount được phân bổ deterministic theo `leaf qty × leaf default Base-UOM price`, residual bảo toàn amount; nested combo không resolve price lại.
- SIMPLE/CHILD có Alternate UOM trực tiếp về Base UOM, usage PURCHASE/SALES/BOTH; COMBO chỉ Base UOM. Transaction giữ source UOM và base quantity. Không dùng client display rounding cho calculation.
- Core Pricing P1: `Product+UOM+Company+EffectiveTime`, Company matching item → Group matching item → UOM default. Tie-break `priority DESC -> effective_from DESC -> id ASC`; effective interval `[from,to)`.
- Workflow/Lifecycle transition hợp lệ là kiểm tra riêng với Authorization; baseline single-step approval, không tự thêm multi-level maker/checker/amount approval.
- Auto Numbering theo Resource Type, generation scope Group/Company/Organization, counter atomic cùng transaction; không tự reuse committed identifier, không dùng `id` làm business number.
- Import P1 chỉ explicit enabled `PRODUCT`; bất kỳ import target P2 nào cũng phải có requirement/design, catalog action `<resource>.import`, template, per-row validation, PBAC và job/row result.
- `vi/en` language negotiation và Display Settings chỉ presentation; business date theo Company timezone, decimal API canonical, VND-only baseline, UTC instant storage theo implementation contract được review.
- Mutable master/config và posting actions phải có actor/timestamp; view/export khác quyền; list/sort/pagination phải thực hiện sau PBAC SQL scope; không được giả định frontend guard là security enforcement.

## Phụ lục B — P2 cross-section trích nguồn theo chủ đề

**Phân kỳ:** P2 bao gồm Document, Customer & CRM, Pricing context mở rộng (Customer/Group/Channel), Promotion, Supplier, Purchase, Inventory/Reservation, Sales đa kênh, marketplace mapping/idempotency, Carrier/COD, vận hành Thu/Chi và import P2 explicit enabled.

**Luồng Purchase:** `Supplier -> Purchase Request -> Purchase Order -> (COMBO expansion khi có) -> Goods Receipt -> Inventory -> Money Expense khi phát sinh`.

**Marketplace Sales:** `Shopee/TikTok -> External Price/Discount -> Sales Order(EXTERNAL_CHANNEL) -> Reservation -> Shipment -> Sales Issue -> Carrier/Delivery -> COD -> COD Reconciliation -> Money Receipt`.

**Direct Sales:** `Customer -> Sales Order -> Price Resolution/Promotion -> COMBO flatten+allocation+leaf aggregation -> Reservation -> Shipment -> Sales Issue -> Money Receipt`.

**Inventory:** `Goods Receipt -> On-hand -> Reservation/Transfer/Adjustment -> Sales Issue -> Return/Reversal`.

**Extended Pricing P2 trong UR-PRC-001 (Target P1, đoạn hướng P2):** thêm Sales Channel, Customer Group, Customer-specific condition. Giữ UOM dimension và thứ tự Company trước Group; trong scope, specificity **có thể** mở rộng `Customer -> Customer Group -> Sales Channel`, sau đó Priority/EffectiveFrom/ID. Persistence chính xác phải thiết kế P2; không tự tạo generic conditions table trong P1.

**Ngoài P2:** multi-company transaction, multi-currency, GL/double-entry, enterprise approval designer, full CRM pipeline, advanced manufacturing, HR/Payroll và BOM P3, universal import, resource instance PBAC; các phần này không ngầm mở bằng P2.

## Phụ lục C — Nguồn và tính độc lập

Bản gốc cung cấp ở `reference/00_User_Requirements_v1.0.15.md` chỉ để đối chiếu và truy nguyên. Có thể review/triển khai P2 bằng chính tài liệu này mà không cần truy cập tệp P1/P3; nội dung thông tin kế thừa P1 đã được lồng rõ trong Phụ lục A và các tài liệu thiết kế P2.

## Phụ lục D — Các đoạn P2 liên chương trích NGUYÊN VĂN

Ba đoạn dưới đây được sao chép nguyên văn từ file requirements cùng SHA-256, không bỏ qua các quyết định P2 rải trong phần P1 và phần end-to-end. Phụ lục A/B trước đó là bản tóm tắt diễn giải; Phụ lục D là bằng chứng đối chiếu trực tiếp.

### Phase P2 - Operational ERP

### P2 - Operational ERP

P2 triển khai các module vận hành còn lại, ngoại trừ BOM và HCM/Payroll:
- Document
- Customer & CRM
- Pricing context mở rộng theo Customer, Customer Group và Sales Channel
- Promotion
- Supplier
- Purchase
- Inventory, bao gồm Inventory Reservation
- Sales đa kênh
- Shopee
- TikTok Shop
- Marketplace Account Mapping & Order Idempotency
- Carrier, Tracking và COD Reconciliation
- Vận hành tài chính Thu/Chi
- Import cho các master P2

### P2 - Extended Pricing Context (embedded in UR-PRC-001 / P1)

**P2 - Extended Pricing Context**
- Sales Channel condition.
- Customer Group condition.
- Customer-specific condition.

Khi P2 bổ sung các condition này, UOM vẫn là dimension bắt buộc và Company scope vẫn được ưu tiên trước Group scope. Trong cùng scope, specificity có thể mở rộng theo thứ tự:

**Customer condition -> Customer Group condition -> Sales Channel condition**

sau đó mới dùng Priority/Effective From/ID để tie-break. Exact persistence của P2 condition phải được thiết kế ở P2, không tạo generic pricing condition table trong P1.

### P2 end-to-end operational flows

### 19.1 Purchase
Supplier
-> Purchase Request
-> Purchase Order / optional COMBO selection template
-> COMBO expansion to SIMPLE/CHILD when applicable
-> Goods Receipt
-> Inventory
-> Money Expense khi phát sinh

### 19.2 Marketplace Sales
Shopee / TikTok Shop
-> External Price/Discount
-> ERP Sales Order (`Price Source = EXTERNAL_CHANNEL`)
-> Inventory Reservation
-> Shipment
-> Sales Issue
-> Carrier
-> Delivery
-> COD
-> COD Reconciliation
-> Money Receipt

### 19.3 Direct/Internal Sales
Customer
-> Sales Order / optional COMBO selection
-> Price Resolution / Promotion theo rule áp dụng
-> COMBO flatten + selling amount allocation + leaf aggregation trước operational downstream khi có COMBO
-> Inventory Reservation
-> Shipment
-> Sales Issue
-> Money Receipt

### 19.4 Inventory
Goods Receipt
-> On-hand
-> Reservation / Transfer / Adjustment
-> Sales Issue
-> Return / Reversal khi áp dụng
