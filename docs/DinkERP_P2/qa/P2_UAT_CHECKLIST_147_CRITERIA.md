# P2 — Independent UAT Execution Workbook (Markdown)

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

**Usage:** Work through every criterion on DEV clone then UAT. Fill actual result, environment, timestamp, Test Code, SQL state and evidence link in the CSV `reference/P2_TEST_CASE_REGISTRY.csv`; this text is a printable scenario checklist, not proof of execution.

**Shared preconditions:** approved P2 API/UI code and migrations, baseline test Company A/B, Group access, actor with explicit `resource.action`, related product/serial/price/contacts fixtures and reasonable fixture dates. For each happy case run an unauthorized/cross-Company/inactive/bad-state negative variant when relevant.

## DOC — Document & Attachment

### UR-DOC-001 — Attachment theo đối tượng nghiệp vụ

- [ ] **P2-AT-DOC-001-01** — File xác định được đối tượng nghiệp vụ liên quan.
  - When: exercise relevant screen/route for `UR-DOC-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-DOC-001-02** — Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ.
  - When: exercise relevant screen/route for `UR-DOC-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-DOC-002 — Tìm kiếm và version tài liệu

- [ ] **P2-AT-DOC-002-01** — Có thể xem version hiện tại và version trước.
  - When: exercise relevant screen/route for `UR-DOC-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-DOC-002-02** — Tài liệu bị thay thế không làm mất lịch sử.
  - When: exercise relevant screen/route for `UR-DOC-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

## CUS — Customer & CRM History

### UR-CUS-001 — Customer Master

- [ ] **P2-AT-CUS-001-01** — Customer Code là duy nhất trong Group.
  - When: exercise relevant screen/route for `UR-CUS-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-CUS-001-02** — Customer có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng.
  - When: exercise relevant screen/route for `UR-CUS-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-CUS-001-03** — Company ngoài Customer Usage Scope không được dùng Customer cho giao dịch mới.
  - When: exercise relevant screen/route for `UR-CUS-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-CUS-001-04** — Khi triển khai Customer ở P2, Customer có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Customer.
  - When: exercise relevant screen/route for `UR-CUS-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-CUS-001-05** — Customer inactive không được chọn cho giao dịch mới nhưng lịch sử giao dịch/liên hệ vẫn tra cứu được.
  - When: exercise relevant screen/route for `UR-CUS-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-CUS-002 — Customer History

- [ ] **P2-AT-CUS-002-01** — Lưu được note/lịch sử liên hệ.
  - When: exercise relevant screen/route for `UR-CUS-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-CUS-002-02** — Xem được Sales Order/Shipment/Sales Return/Thu tiền liên quan trong phạm vi quyền.
  - When: exercise relevant screen/route for `UR-CUS-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-CUS-002-03** — Không yêu cầu Lead Management hoặc Opportunity Pipeline.
  - When: exercise relevant screen/route for `UR-CUS-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

## PRC — Promotion & Extended Pricing

### UR-PRC-002 — Promotion

- [ ] **P2-AT-PRC-002-01** — Promotion chỉ áp dụng trong thời gian hiệu lực và khi đủ điều kiện.
  - When: exercise relevant screen/route for `UR-PRC-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PRC-002-02** — Sales Order lưu được promotion/coupon đã áp dụng.
  - When: exercise relevant screen/route for `UR-PRC-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PRC-002-03** — Special Price được xử lý trước Discount/Promotion khác.
  - When: exercise relevant screen/route for `UR-PRC-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PRC-002-04** — Promotion được evaluate theo Priority và không được tạo stacking tùy ý ngoài rule đã cấu hình.
  - When: exercise relevant screen/route for `UR-PRC-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PRC-002-05** — Promotion có `Combinable=false` không được kết hợp với Promotion/Coupon khác trái với rule.
  - When: exercise relevant screen/route for `UR-PRC-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PRC-002-06** — Nếu các Promotion cùng Priority và scope có thể tạo kết quả không xác định, cấu hình phải bị từ chối hoặc được điều chỉnh trước khi active.
  - When: exercise relevant screen/route for `UR-PRC-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

## SUP — Supplier & History

### UR-SUP-001 — Supplier Master

- [ ] **P2-AT-SUP-001-01** — Supplier Code là duy nhất trong Group.
  - When: exercise relevant screen/route for `UR-SUP-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SUP-001-02** — Supplier có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng.
  - When: exercise relevant screen/route for `UR-SUP-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SUP-001-03** — Company ngoài Supplier Usage Scope không được dùng Supplier cho giao dịch mới.
  - When: exercise relevant screen/route for `UR-SUP-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SUP-001-04** — Khi triển khai Supplier ở P2, Supplier có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Supplier.
  - When: exercise relevant screen/route for `UR-SUP-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SUP-001-05** — Supplier inactive không được chọn cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
  - When: exercise relevant screen/route for `UR-SUP-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-SUP-002 — Supplier History

- [ ] **P2-AT-SUP-002-01** — Chỉ hiển thị lịch sử thuộc Company nằm trong authorization scope của User.
  - When: exercise relevant screen/route for `UR-SUP-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SUP-002-02** — Có thể điều hướng từ Supplier tới chứng từ nguồn liên quan khi User có quyền VIEW chứng từ đó.
  - When: exercise relevant screen/route for `UR-SUP-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SUP-002-03** — Lịch sử vẫn tra cứu được khi Supplier đã inactive.
  - When: exercise relevant screen/route for `UR-SUP-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

## PUR — Purchase to Receipt/Return

### UR-PUR-001 — Purchase Request

- [ ] **P2-AT-PUR-001-01** — PR có thể Submit/Approve/Reject theo PBAC.
  - When: exercise relevant screen/route for `UR-PUR-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-001-02** — PR Approved có thể dùng để tạo Purchase Order.
  - When: exercise relevant screen/route for `UR-PUR-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-PUR-002 — Purchase Order

- [ ] **P2-AT-PUR-002-01** — PO tuân theo PBAC theo Company/Resource/State/Action.
  - When: exercise relevant screen/route for `UR-PUR-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-002-02** — UOM phải hợp lệ cho Product; Quantity phải có thể quy đổi về Base UOM khi sử dụng Alternate UOM của SIMPLE/CHILD.
  - When: exercise relevant screen/route for `UR-PUR-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-002-03** — COMBO có thể được chọn như template để nhập nhanh Purchase line, nhưng trước receiving/inventory/cost operational processing phải expand về SIMPLE/CHILD; COMBO selling-price allocation không được tự dùng làm purchase-price allocation.
  - When: exercise relevant screen/route for `UR-PUR-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-002-04** — Purchase price/cost của các leaf sau expansion phải được xác định theo nghiệp vụ Purchase tương ứng, không suy từ COMBO selling price nếu chưa có requirement riêng.
  - When: exercise relevant screen/route for `UR-PUR-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-002-05** — PO giữ lịch sử phê duyệt và thay đổi quan trọng.
  - When: exercise relevant screen/route for `UR-PUR-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-PUR-003 — Goods Receipt

- [ ] **P2-AT-PUR-003-01** — Chỉ Goods Receipt đã đạt posting state của module (`POSTED` hoặc trạng thái `CONFIRMED` được định nghĩa tương đương posting) mới làm tăng On-hand Inventory.
  - When: exercise relevant screen/route for `UR-PUR-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-003-02** — Goods Receipt cập nhật Received Quantity của Purchase Order nguồn.
  - When: exercise relevant screen/route for `UR-PUR-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-003-03** — Product serial-managed bắt buộc ghi Serial khi Goods Receipt được post.
  - When: exercise relevant screen/route for `UR-PUR-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-003-04** — Không nhận vào Warehouse thuộc Company khác Purchase Order.
  - When: exercise relevant screen/route for `UR-PUR-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-003-05** — Goods Receipt đã post không được delete/edit trực tiếp các dữ liệu làm thay đổi stock; correction phải qua reversal hoặc Inventory Adjustment phù hợp.
  - When: exercise relevant screen/route for `UR-PUR-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-PUR-004 — Purchase Return

- [ ] **P2-AT-PUR-004-01** — Chỉ Purchase Return đã đạt posting state mới làm giảm On-hand Inventory.
  - When: exercise relevant screen/route for `UR-PUR-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-004-02** — Serial-managed item phải xác định đúng Serial trả.
  - When: exercise relevant screen/route for `UR-PUR-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-004-03** — Có thể truy về Goods Receipt/Purchase Order nguồn.
  - When: exercise relevant screen/route for `UR-PUR-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-PUR-004-04** — Purchase Return đã post không được delete/edit trực tiếp làm thay đổi stock history; correction phải qua reversal/adjustment phù hợp.
  - When: exercise relevant screen/route for `UR-PUR-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

## INV — Inventory, Serial, Reservation

### UR-INV-001 — Inventory Balance

- [ ] **P2-AT-INV-001-01** — Có thể xem On-hand, Reserved và Available theo từng Warehouse.
  - When: exercise relevant screen/route for `UR-INV-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-001-02** — Inventory balance chỉ tồn tại cho SIMPLE/CHILD leaf; COMBO không có On-hand/Reserved/Available riêng.
  - When: exercise relevant screen/route for `UR-INV-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-001-03** — On-hand chỉ phản ánh stock movement đã được post/confirm theo posting rule.
  - When: exercise relevant screen/route for `UR-INV-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-001-04** — Có thể xem tồn Group bằng tổng hợp Company được phép.
  - When: exercise relevant screen/route for `UR-INV-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-001-05** — Không trộn tồn giữa các Company.
  - When: exercise relevant screen/route for `UR-INV-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-INV-002 — Inventory Movement

- [ ] **P2-AT-INV-002-01** — Chỉ movement ở posting state (`POSTED` hoặc module-defined `CONFIRMED` tương đương posting) mới làm thay đổi On-hand Qty.
  - When: exercise relevant screen/route for `UR-INV-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-002-02** — Inventory movement chỉ post SIMPLE/CHILD leaf; nếu source document sử dụng COMBO thì phải flatten trước khi tạo/post stock movement.
  - When: exercise relevant screen/route for `UR-INV-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-002-03** — Draft/Pending movement không làm thay đổi On-hand Qty.
  - When: exercise relevant screen/route for `UR-INV-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-002-04** — Movement đã post không được delete hoặc sửa trực tiếp quantity/product/warehouse làm thay đổi stock history.
  - When: exercise relevant screen/route for `UR-INV-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-002-05** — Correction sau posting phải được thực hiện bằng reversal hoặc Inventory Adjustment có traceability.
  - When: exercise relevant screen/route for `UR-INV-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-002-06** — Mỗi posted movement truy được Source Document, User và Timestamp.
  - When: exercise relevant screen/route for `UR-INV-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-INV-003 — Warehouse Transfer

- [ ] **P2-AT-INV-003-01** — Source và destination Warehouse phải cùng Company.
  - When: exercise relevant screen/route for `UR-INV-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-003-02** — Cross-Company movement không được xử lý như internal warehouse transfer.
  - When: exercise relevant screen/route for `UR-INV-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-003-03** — Chỉ Transfer đã đạt posting state mới làm thay đổi stock location/balance.
  - When: exercise relevant screen/route for `UR-INV-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-003-04** — Posted Transfer phải giữ trace giữa source và destination movement.
  - When: exercise relevant screen/route for `UR-INV-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-INV-004 — Stock Count và Adjustment

- [ ] **P2-AT-INV-004-01** — Adjustment chỉ được ghi nhận theo PBAC.
  - When: exercise relevant screen/route for `UR-INV-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-004-02** — Adjustment chỉ làm thay đổi On-hand khi đạt posting state.
  - When: exercise relevant screen/route for `UR-INV-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-004-03** — Có thể truy từ Adjustment về Stock Count và người xác nhận.
  - When: exercise relevant screen/route for `UR-INV-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-004-04** — Posted Adjustment không được delete/edit trực tiếp làm thay đổi stock history.
  - When: exercise relevant screen/route for `UR-INV-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-INV-005 — Serial Inventory & Transaction Traceability

- [ ] **P2-AT-INV-005-01** — Serial xác định được Product, Company, Warehouse hiện tại và trạng thái.
  - When: exercise relevant screen/route for `UR-INV-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-005-02** — Serial truy được Goods Receipt, Sales Issue, transfer, purchase return và sales return liên quan.
  - When: exercise relevant screen/route for `UR-INV-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-005-03** — Một Serial không thể đồng thời tồn tại ở nhiều Warehouse.
  - When: exercise relevant screen/route for `UR-INV-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-005-04** — Goods Receipt của Product serial-managed phải ghi nhận Serial trước khi posting.
  - When: exercise relevant screen/route for `UR-INV-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-005-05** — Sales Issue của Product serial-managed phải xác định Serial xuất.
  - When: exercise relevant screen/route for `UR-INV-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-005-06** — Warehouse Transfer phải chuyển trạng thái/location của đúng Serial.
  - When: exercise relevant screen/route for `UR-INV-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-005-07** — Purchase Return và Sales Return phải tham chiếu đúng Serial khi áp dụng.
  - When: exercise relevant screen/route for `UR-INV-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-INV-006 — Inventory Reservation

- [ ] **P2-AT-INV-006-01** — Reservation xác định Company, Warehouse, Product, Reserved Quantity và Source Sales Order/Order Line.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-006-02** — Reservation chỉ giữ quantity của SIMPLE/CHILD leaf; COMBO phải được flatten trước reservation.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-006-03** — Reservation được tạo khi Sales Order đạt trạng thái xác nhận vận hành theo lifecycle của module.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-006-04** — Cancel Order hoặc giảm quantity phải release phần reservation không còn cần.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-006-05** — Partial shipment/Sales Issue consume phần reservation tương ứng và giữ reservation cho phần chưa xử lý.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-006-06** — Khi Sales Issue được post, phần reservation được consume đồng thời với stock issue liên quan.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-006-07** — Reservation không tự làm thay đổi On-hand Qty.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-006-08** — Baseline không cho reservation mới làm Available Qty âm; trường hợp overselling cần requirement/change request riêng.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-INV-006-09** — Với Product serial-managed, baseline reserve theo quantity; Serial cụ thể được chọn và validate khi Sales Issue được post.
  - When: exercise relevant screen/route for `UR-INV-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

## SAL — Sales, Marketplace & Shipping

### UR-SAL-001 — Sales Order

- [ ] **P2-AT-SAL-001-01** — Direct/Internal Sales sử dụng Price Resolution theo Product + UOM + Company + Effective Time; Sales Order lưu giá cuối cùng, UOM và Price Source `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP` hoặc `PRODUCT_DEFAULT`.
  - When: exercise relevant screen/route for `UR-SAL-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-001-02** — Direct/Internal Sales cho phép chọn COMBO như template. Khi chọn COMBO, hệ thống resolve giá root COMBO, flatten về SIMPLE/CHILD, phân bổ selling amount và aggregate leaf theo UR-PRD-006 trước các bước Reservation/Inventory/Serial/Costing/Posting.
  - When: exercise relevant screen/route for `UR-SAL-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-001-03** — Sales Order phải giữ đủ traceability giữa leaf line và COMBO nguồn khi COMBO được dùng để tạo nhanh line.
  - When: exercise relevant screen/route for `UR-SAL-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-001-04** — Marketplace Sales từ Shopee/TikTok Shop sử dụng external channel price/discount authoritative và lưu Price Source `EXTERNAL_CHANNEL`.
  - When: exercise relevant screen/route for `UR-SAL-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-001-05** — Sales Order có Price Source `EXTERNAL_CHANNEL` không bị ERP Price Resolution/Promotion recompute lại giá marketplace.
  - When: exercise relevant screen/route for `UR-SAL-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-001-06** — Sales Order lưu được nguồn giá, UOM và promotion/discount reference phù hợp với loại order.
  - When: exercise relevant screen/route for `UR-SAL-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-001-07** — Delivery address sử dụng Province/City Code và Ward/Commune Code khi áp dụng.
  - When: exercise relevant screen/route for `UR-SAL-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-001-08** — Sales Order tuân theo PBAC theo Company/Resource/State/Action.
  - When: exercise relevant screen/route for `UR-SAL-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-SAL-002 — Shopee Integration

- [ ] **P2-AT-SAL-002-01** — Lưu Shopee Order ID và Shop/Account source.
  - When: exercise relevant screen/route for `UR-SAL-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-002-02** — Mapping SKU/Product.
  - When: exercise relevant screen/route for `UR-SAL-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-002-03** — Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
  - When: exercise relevant screen/route for `UR-SAL-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-002-04** — Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
  - When: exercise relevant screen/route for `UR-SAL-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-002-05** — Giá/discount từ Shopee là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
  - When: exercise relevant screen/route for `UR-SAL-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-002-06** — Đồng bộ trạng thái cần thiết.
  - When: exercise relevant screen/route for `UR-SAL-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-002-07** — Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.
  - When: exercise relevant screen/route for `UR-SAL-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-SAL-003 — TikTok Shop Integration

- [ ] **P2-AT-SAL-003-01** — Lưu TikTok Shop Order ID và Shop/Account source.
  - When: exercise relevant screen/route for `UR-SAL-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-003-02** — Mapping SKU/Product.
  - When: exercise relevant screen/route for `UR-SAL-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-003-03** — Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
  - When: exercise relevant screen/route for `UR-SAL-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-003-04** — Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
  - When: exercise relevant screen/route for `UR-SAL-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-003-05** — Giá/discount từ TikTok Shop là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
  - When: exercise relevant screen/route for `UR-SAL-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-003-06** — Đồng bộ trạng thái cần thiết.
  - When: exercise relevant screen/route for `UR-SAL-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-003-07** — Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.
  - When: exercise relevant screen/route for `UR-SAL-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-SAL-004 — Inventory dùng chung cho đa kênh

- [ ] **P2-AT-SAL-004-01** — Không tạo kho logic riêng cạnh tranh chỉ vì khác sales channel.
  - When: exercise relevant screen/route for `UR-SAL-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-004-02** — Order phải kiểm tra Available Qty theo Company/Warehouse phù hợp.
  - When: exercise relevant screen/route for `UR-SAL-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-004-03** — Order được xác nhận phải reserve stock vào Inventory chung theo UR-INV-006.
  - When: exercise relevant screen/route for `UR-SAL-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-004-04** — Release/consume reservation phải phản ánh vào Inventory chung.
  - When: exercise relevant screen/route for `UR-SAL-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-004-05** — Sales Issue đã post làm giảm On-hand theo stock posting rule.
  - When: exercise relevant screen/route for `UR-SAL-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-SAL-005 — Shipment và Carrier

- [ ] **P2-AT-SAL-005-01** — Shipment thuộc cùng Company với Sales Order nguồn.
  - When: exercise relevant screen/route for `UR-SAL-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-005-02** — Shipment truy được Sales Order và Warehouse fulfillment liên quan.
  - When: exercise relevant screen/route for `UR-SAL-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-005-03** — Tracking Number và delivery status được lưu khi carrier trả về.
  - When: exercise relevant screen/route for `UR-SAL-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-005-04** — Hủy vận đơn không tự xóa Shipment hoặc lịch sử carrier interaction.
  - When: exercise relevant screen/route for `UR-SAL-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-005-05** — Shipping Fee và COD Amount được lưu để phục vụ COD Reconciliation khi áp dụng.
  - When: exercise relevant screen/route for `UR-SAL-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-005-06** — Carrier integration error phải được ghi nhận để retry/xử lý.
  - When: exercise relevant screen/route for `UR-SAL-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-SAL-006 — COD Reconciliation

- [ ] **P2-AT-SAL-006-01** — Có thể lọc các shipment chưa đối soát.
  - When: exercise relevant screen/route for `UR-SAL-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-006-02** — Chênh lệch phải xác định được amount và trạng thái xử lý.
  - When: exercise relevant screen/route for `UR-SAL-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-006-03** — Khoản COD đã đối soát có thể tạo/liên kết Money Receipt.
  - When: exercise relevant screen/route for `UR-SAL-006` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-SAL-007 — Sales Return

- [ ] **P2-AT-SAL-007-01** — Sales Return truy được Sales Order/Shipment nguồn khi có.
  - When: exercise relevant screen/route for `UR-SAL-007` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-007-02** — Returned goods chỉ làm tăng On-hand khi inventory return movement được post/confirm theo posting rule.
  - When: exercise relevant screen/route for `UR-SAL-007` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-007-03** — Product serial-managed phải validate đúng Serial trả lại.
  - When: exercise relevant screen/route for `UR-SAL-007` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-007-04** — Refund hoặc giao dịch Thu/Chi liên quan được reference, không tự tạo double-entry accounting.
  - When: exercise relevant screen/route for `UR-SAL-007` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-007-05** — Return đã post không được xóa/sửa trực tiếp làm thay đổi stock history.
  - When: exercise relevant screen/route for `UR-SAL-007` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-SAL-008 — Marketplace Account Mapping & Order Idempotency

- [ ] **P2-AT-SAL-008-01** — Mỗi Shop/Account xác định được Sales Channel/Platform.
  - When: exercise relevant screen/route for `UR-SAL-008` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-008-02** — Mỗi Shop/Account map đúng một Company tại một thời điểm hiệu lực.
  - When: exercise relevant screen/route for `UR-SAL-008` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-008-03** — Shop/Account có fulfillment Warehouse mặc định hoặc mapping Warehouse rõ ràng trước khi tạo Sales Order.
  - When: exercise relevant screen/route for `UR-SAL-008` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-008-04** — `Sales Channel + Shop/Account + External Order ID` là unique business key.
  - When: exercise relevant screen/route for `UR-SAL-008` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-008-05** — Retry/sync lại cùng external order không được tạo duplicate ERP Sales Order.
  - When: exercise relevant screen/route for `UR-SAL-008` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-008-06** — Sync/retry phải idempotent đối với create/link order.
  - When: exercise relevant screen/route for `UR-SAL-008` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-008-07** — Mapping Company/Warehouse phải hoàn tất trước khi ERP Sales Order được tạo.
  - When: exercise relevant screen/route for `UR-SAL-008` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-SAL-008-08** — Lỗi mapping hoặc sync được giữ trạng thái/lý do để reprocess.
  - When: exercise relevant screen/route for `UR-SAL-008` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

## FIN — Operational Finance

### UR-FIN-001 — Ghi nhận Thu/Chi

- [ ] **P2-AT-FIN-001-01** — Mỗi Money Receipt/Money Expense thuộc đúng một Company.
  - When: exercise relevant screen/route for `UR-FIN-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-001-02** — Amount phải lớn hơn 0.
  - When: exercise relevant screen/route for `UR-FIN-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-001-03** — Account sử dụng phải thuộc cùng Company với transaction.
  - When: exercise relevant screen/route for `UR-FIN-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-001-04** — Chỉ transaction đã `POSTED` mới tham gia operational balance.
  - When: exercise relevant screen/route for `UR-FIN-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-001-05** — Transaction đã post không được xóa/sửa trực tiếp amount/account làm thay đổi history; correction phải qua reversal/adjustment có traceability.
  - When: exercise relevant screen/route for `UR-FIN-001` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-FIN-002 — Danh mục Thu/Chi

- [ ] **P2-AT-FIN-002-01** — Category xác định được loại `RECEIPT` hoặc `EXPENSE`.
  - When: exercise relevant screen/route for `UR-FIN-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-002-02** — Category inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
  - When: exercise relevant screen/route for `UR-FIN-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-002-03** — Có thể lọc/tổng hợp Thu/Chi theo category.
  - When: exercise relevant screen/route for `UR-FIN-002` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-FIN-003 — Cash và Bank Account

- [ ] **P2-AT-FIN-003-01** — Mỗi account thuộc một Company.
  - When: exercise relevant screen/route for `UR-FIN-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-003-02** — Opening Balance và Opening Date được xác định trước khi tính operational balance từ thời điểm đó.
  - When: exercise relevant screen/route for `UR-FIN-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-003-03** — User chỉ thao tác account trong PBAC scope.
  - When: exercise relevant screen/route for `UR-FIN-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-003-04** — Account inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
  - When: exercise relevant screen/route for `UR-FIN-003` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-FIN-004 — Tổng hợp Thu/Chi

- [ ] **P2-AT-FIN-004-01** — Chỉ posted Money Receipt/Money Expense được tính vào operational balance.
  - When: exercise relevant screen/route for `UR-FIN-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-004-02** — Opening/Closing Balance không suy diễn từ General Ledger.
  - When: exercise relevant screen/route for `UR-FIN-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-004-03** — Có thể tính balance theo từng Cash/Bank Account và tổng hợp các account trong Company theo filter.
  - When: exercise relevant screen/route for `UR-FIN-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-004-04** — Report theo nhiều Company chỉ tổng hợp Company User có quyền xem.
  - When: exercise relevant screen/route for `UR-FIN-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-004-05** — Operational Balance không được mô tả như bank reconciliation hoặc statutory accounting balance.
  - When: exercise relevant screen/route for `UR-FIN-004` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.

### UR-FIN-005 — Liên kết nguồn giao dịch

- [ ] **P2-AT-FIN-005-01** — Có thể truy từ Money Receipt/Money Expense về Source Reference khi nguồn còn tồn tại và User có quyền.
  - When: exercise relevant screen/route for `UR-FIN-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-005-02** — Một source reference không tự tạo double-entry journal.
  - When: exercise relevant screen/route for `UR-FIN-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-005-03** — COD đã reconciliation có thể tạo/liên kết Money Receipt mà vẫn giữ Shipment/COD reference.
  - When: exercise relevant screen/route for `UR-FIN-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
- [ ] **P2-AT-FIN-005-04** — Payroll payment có thể liên kết Money Expense theo đúng Company.
  - When: exercise relevant screen/route for `UR-FIN-005` on both happy and disallowed input.
  - Then: verify the above literal expected outcome in API, DB and related user view; record evidence/test log.
