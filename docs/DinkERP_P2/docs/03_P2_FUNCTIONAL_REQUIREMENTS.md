# 03 — Functional Requirements Specification P2 (FRS)

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## Chất lượng FRS

- Mỗi `P2-FR-*` gắn **một Acceptance Criterion nguyên văn** và `P2-AT-*` làm test ID; ma trận bao phủ 147/147 tiêu chí, không nén/loại bỏ case khó.
- Functional behavior mô tả ở UR nguyên văn và trong các chương đặc tả nghiệp vụ/dữ liệu/API. Không tự suy FRS cho capability không có UR P2.
- `P2-DRAFT` là quy tắc triển khai/thiết kế mở rộng, không override UR. Quyết định chưa được khóa được theo dõi trong Open Decisions.
- Mọi create/update/post/replay/import được PBAC tại **server**; 409 concurrency và idempotency phải có regression.

## Quy ước scenario nghiệm thu

`Given` dữ liệu hợp lệ và User có đúng Base Permission + PBAC tại Company/Evaluation Organization; `When` gọi action tương ứng qua API/UI; `Then` kết quả match nguyên văn criterion, có DB evidence hoặc downstream effect. Cần thêm negative cases: thiếu quyền, cross-Company, bad state, concurrent retry, rollback, malformed payload và unknown refs. `P2-AT-*` phải được triển khai thành test thực tế ở Gate tương ứng.

## DOC — Document & Attachment

**Business objective:** Attachment polymorphic theo resource; file version immutable; download và search tuân owner PBAC.

### UR-DOC-001 — Attachment theo đối tượng nghiệp vụ

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-DOC-001-01** — File xác định được đối tượng nghiệp vụ liên quan.
  - Evidence: `P2-AT-DOC-001-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-DOC-001-02** — Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ.
  - Evidence: `P2-AT-DOC-001-02`; xác minh trên persisted state/response/UI as applicable.

### UR-DOC-002 — Tìm kiếm và version tài liệu

**Priority:** SHOULD · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-DOC-002-01** — Có thể xem version hiện tại và version trước.
  - Evidence: `P2-AT-DOC-002-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-DOC-002-02** — Tài liệu bị thay thế không làm mất lịch sử.
  - Evidence: `P2-AT-DOC-002-02`; xác minh trên persisted state/response/UI as applicable.

## CUS — Customer & CRM History

**Business objective:** Group-shared Customer; scope All Companies hoặc allowlist; lịch sử note và business documents trong authorized Company.

### UR-CUS-001 — Customer Master

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-CUS-001-01** — Customer Code là duy nhất trong Group.
  - Evidence: `P2-AT-CUS-001-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-CUS-001-02** — Customer có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng.
  - Evidence: `P2-AT-CUS-001-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-CUS-001-03** — Company ngoài Customer Usage Scope không được dùng Customer cho giao dịch mới.
  - Evidence: `P2-AT-CUS-001-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-CUS-001-04** — Khi triển khai Customer ở P2, Customer có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Customer.
  - Evidence: `P2-AT-CUS-001-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-CUS-001-05** — Customer inactive không được chọn cho giao dịch mới nhưng lịch sử giao dịch/liên hệ vẫn tra cứu được.
  - Evidence: `P2-AT-CUS-001-05`; xác minh trên persisted state/response/UI as applicable.

### UR-CUS-002 — Customer History

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-CUS-002-01** — Lưu được note/lịch sử liên hệ.
  - Evidence: `P2-AT-CUS-002-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-CUS-002-02** — Xem được Sales Order/Shipment/Sales Return/Thu tiền liên quan trong phạm vi quyền.
  - Evidence: `P2-AT-CUS-002-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-CUS-002-03** — Không yêu cầu Lead Management hoặc Opportunity Pipeline.
  - Evidence: `P2-AT-CUS-002-03`; xác minh trên persisted state/response/UI as applicable.

## PRC — Promotion & Extended Pricing

**Business objective:** Pricing P1 bất biến, P2 thêm điều kiện Customer/Group/Channel; promotion deterministic, không stacking tùy ý.

### UR-PRC-002 — Promotion

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-PRC-002-01** — Promotion chỉ áp dụng trong thời gian hiệu lực và khi đủ điều kiện.
  - Evidence: `P2-AT-PRC-002-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PRC-002-02** — Sales Order lưu được promotion/coupon đã áp dụng.
  - Evidence: `P2-AT-PRC-002-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PRC-002-03** — Special Price được xử lý trước Discount/Promotion khác.
  - Evidence: `P2-AT-PRC-002-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PRC-002-04** — Promotion được evaluate theo Priority và không được tạo stacking tùy ý ngoài rule đã cấu hình.
  - Evidence: `P2-AT-PRC-002-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PRC-002-05** — Promotion có `Combinable=false` không được kết hợp với Promotion/Coupon khác trái với rule.
  - Evidence: `P2-AT-PRC-002-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PRC-002-06** — Nếu các Promotion cùng Priority và scope có thể tạo kết quả không xác định, cấu hình phải bị từ chối hoặc được điều chỉnh trước khi active.
  - Evidence: `P2-AT-PRC-002-06`; xác minh trên persisted state/response/UI as applicable.

## SUP — Supplier & History

**Business objective:** Group-shared Supplier; Company Usage Scope; shared contacts; history qua Purchase và payments.

### UR-SUP-001 — Supplier Master

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SUP-001-01** — Supplier Code là duy nhất trong Group.
  - Evidence: `P2-AT-SUP-001-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SUP-001-02** — Supplier có thể cấu hình `All Companies` hoặc danh sách Company được phép sử dụng.
  - Evidence: `P2-AT-SUP-001-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SUP-001-03** — Company ngoài Supplier Usage Scope không được dùng Supplier cho giao dịch mới.
  - Evidence: `P2-AT-SUP-001-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SUP-001-04** — Khi triển khai Supplier ở P2, Supplier có thể sử dụng Shared Contact Management để quản lý nhiều Contact; không tạo capability Contact riêng chỉ vì là Supplier.
  - Evidence: `P2-AT-SUP-001-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SUP-001-05** — Supplier inactive không được chọn cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
  - Evidence: `P2-AT-SUP-001-05`; xác minh trên persisted state/response/UI as applicable.

### UR-SUP-002 — Supplier History

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SUP-002-01** — Chỉ hiển thị lịch sử thuộc Company nằm trong authorization scope của User.
  - Evidence: `P2-AT-SUP-002-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SUP-002-02** — Có thể điều hướng từ Supplier tới chứng từ nguồn liên quan khi User có quyền VIEW chứng từ đó.
  - Evidence: `P2-AT-SUP-002-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SUP-002-03** — Lịch sử vẫn tra cứu được khi Supplier đã inactive.
  - Evidence: `P2-AT-SUP-002-03`; xác minh trên persisted state/response/UI as applicable.

## PUR — Purchase to Receipt/Return

**Business objective:** Single-Company PR/PO; post Goods Receipt mới tăng stock; return post mới giảm; PO trace không bị phá.

### UR-PUR-001 — Purchase Request

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-PUR-001-01** — PR có thể Submit/Approve/Reject theo PBAC.
  - Evidence: `P2-AT-PUR-001-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-001-02** — PR Approved có thể dùng để tạo Purchase Order.
  - Evidence: `P2-AT-PUR-001-02`; xác minh trên persisted state/response/UI as applicable.

### UR-PUR-002 — Purchase Order

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-PUR-002-01** — PO tuân theo PBAC theo Company/Resource/State/Action.
  - Evidence: `P2-AT-PUR-002-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-002-02** — UOM phải hợp lệ cho Product; Quantity phải có thể quy đổi về Base UOM khi sử dụng Alternate UOM của SIMPLE/CHILD.
  - Evidence: `P2-AT-PUR-002-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-002-03** — COMBO có thể được chọn như template để nhập nhanh Purchase line, nhưng trước receiving/inventory/cost operational processing phải expand về SIMPLE/CHILD; COMBO selling-price allocation không được tự dùng làm purchase-price allocation.
  - Evidence: `P2-AT-PUR-002-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-002-04** — Purchase price/cost của các leaf sau expansion phải được xác định theo nghiệp vụ Purchase tương ứng, không suy từ COMBO selling price nếu chưa có requirement riêng.
  - Evidence: `P2-AT-PUR-002-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-002-05** — PO giữ lịch sử phê duyệt và thay đổi quan trọng.
  - Evidence: `P2-AT-PUR-002-05`; xác minh trên persisted state/response/UI as applicable.

### UR-PUR-003 — Goods Receipt

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-PUR-003-01** — Chỉ Goods Receipt đã đạt posting state của module (`POSTED` hoặc trạng thái `CONFIRMED` được định nghĩa tương đương posting) mới làm tăng On-hand Inventory.
  - Evidence: `P2-AT-PUR-003-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-003-02** — Goods Receipt cập nhật Received Quantity của Purchase Order nguồn.
  - Evidence: `P2-AT-PUR-003-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-003-03** — Product serial-managed bắt buộc ghi Serial khi Goods Receipt được post.
  - Evidence: `P2-AT-PUR-003-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-003-04** — Không nhận vào Warehouse thuộc Company khác Purchase Order.
  - Evidence: `P2-AT-PUR-003-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-003-05** — Goods Receipt đã post không được delete/edit trực tiếp các dữ liệu làm thay đổi stock; correction phải qua reversal hoặc Inventory Adjustment phù hợp.
  - Evidence: `P2-AT-PUR-003-05`; xác minh trên persisted state/response/UI as applicable.

### UR-PUR-004 — Purchase Return

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-PUR-004-01** — Chỉ Purchase Return đã đạt posting state mới làm giảm On-hand Inventory.
  - Evidence: `P2-AT-PUR-004-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-004-02** — Serial-managed item phải xác định đúng Serial trả.
  - Evidence: `P2-AT-PUR-004-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-004-03** — Có thể truy về Goods Receipt/Purchase Order nguồn.
  - Evidence: `P2-AT-PUR-004-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-PUR-004-04** — Purchase Return đã post không được delete/edit trực tiếp làm thay đổi stock history; correction phải qua reversal/adjustment phù hợp.
  - Evidence: `P2-AT-PUR-004-04`; xác minh trên persisted state/response/UI as applicable.

## INV — Inventory, Serial, Reservation

**Business objective:** Stock ledger post-only, quantity SIMPLE/CHILD base UOM; reserved != on-hand; serial đơn nhất; Company isolation.

### UR-INV-001 — Inventory Balance

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-INV-001-01** — Có thể xem On-hand, Reserved và Available theo từng Warehouse.
  - Evidence: `P2-AT-INV-001-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-001-02** — Inventory balance chỉ tồn tại cho SIMPLE/CHILD leaf; COMBO không có On-hand/Reserved/Available riêng.
  - Evidence: `P2-AT-INV-001-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-001-03** — On-hand chỉ phản ánh stock movement đã được post/confirm theo posting rule.
  - Evidence: `P2-AT-INV-001-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-001-04** — Có thể xem tồn Group bằng tổng hợp Company được phép.
  - Evidence: `P2-AT-INV-001-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-001-05** — Không trộn tồn giữa các Company.
  - Evidence: `P2-AT-INV-001-05`; xác minh trên persisted state/response/UI as applicable.

### UR-INV-002 — Inventory Movement

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-INV-002-01** — Chỉ movement ở posting state (`POSTED` hoặc module-defined `CONFIRMED` tương đương posting) mới làm thay đổi On-hand Qty.
  - Evidence: `P2-AT-INV-002-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-002-02** — Inventory movement chỉ post SIMPLE/CHILD leaf; nếu source document sử dụng COMBO thì phải flatten trước khi tạo/post stock movement.
  - Evidence: `P2-AT-INV-002-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-002-03** — Draft/Pending movement không làm thay đổi On-hand Qty.
  - Evidence: `P2-AT-INV-002-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-002-04** — Movement đã post không được delete hoặc sửa trực tiếp quantity/product/warehouse làm thay đổi stock history.
  - Evidence: `P2-AT-INV-002-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-002-05** — Correction sau posting phải được thực hiện bằng reversal hoặc Inventory Adjustment có traceability.
  - Evidence: `P2-AT-INV-002-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-002-06** — Mỗi posted movement truy được Source Document, User và Timestamp.
  - Evidence: `P2-AT-INV-002-06`; xác minh trên persisted state/response/UI as applicable.

### UR-INV-003 — Warehouse Transfer

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-INV-003-01** — Source và destination Warehouse phải cùng Company.
  - Evidence: `P2-AT-INV-003-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-003-02** — Cross-Company movement không được xử lý như internal warehouse transfer.
  - Evidence: `P2-AT-INV-003-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-003-03** — Chỉ Transfer đã đạt posting state mới làm thay đổi stock location/balance.
  - Evidence: `P2-AT-INV-003-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-003-04** — Posted Transfer phải giữ trace giữa source và destination movement.
  - Evidence: `P2-AT-INV-003-04`; xác minh trên persisted state/response/UI as applicable.

### UR-INV-004 — Stock Count và Adjustment

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-INV-004-01** — Adjustment chỉ được ghi nhận theo PBAC.
  - Evidence: `P2-AT-INV-004-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-004-02** — Adjustment chỉ làm thay đổi On-hand khi đạt posting state.
  - Evidence: `P2-AT-INV-004-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-004-03** — Có thể truy từ Adjustment về Stock Count và người xác nhận.
  - Evidence: `P2-AT-INV-004-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-004-04** — Posted Adjustment không được delete/edit trực tiếp làm thay đổi stock history.
  - Evidence: `P2-AT-INV-004-04`; xác minh trên persisted state/response/UI as applicable.

### UR-INV-005 — Serial Inventory & Transaction Traceability

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-INV-005-01** — Serial xác định được Product, Company, Warehouse hiện tại và trạng thái.
  - Evidence: `P2-AT-INV-005-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-005-02** — Serial truy được Goods Receipt, Sales Issue, transfer, purchase return và sales return liên quan.
  - Evidence: `P2-AT-INV-005-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-005-03** — Một Serial không thể đồng thời tồn tại ở nhiều Warehouse.
  - Evidence: `P2-AT-INV-005-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-005-04** — Goods Receipt của Product serial-managed phải ghi nhận Serial trước khi posting.
  - Evidence: `P2-AT-INV-005-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-005-05** — Sales Issue của Product serial-managed phải xác định Serial xuất.
  - Evidence: `P2-AT-INV-005-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-005-06** — Warehouse Transfer phải chuyển trạng thái/location của đúng Serial.
  - Evidence: `P2-AT-INV-005-06`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-005-07** — Purchase Return và Sales Return phải tham chiếu đúng Serial khi áp dụng.
  - Evidence: `P2-AT-INV-005-07`; xác minh trên persisted state/response/UI as applicable.

### UR-INV-006 — Inventory Reservation

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-INV-006-01** — Reservation xác định Company, Warehouse, Product, Reserved Quantity và Source Sales Order/Order Line.
  - Evidence: `P2-AT-INV-006-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-006-02** — Reservation chỉ giữ quantity của SIMPLE/CHILD leaf; COMBO phải được flatten trước reservation.
  - Evidence: `P2-AT-INV-006-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-006-03** — Reservation được tạo khi Sales Order đạt trạng thái xác nhận vận hành theo lifecycle của module.
  - Evidence: `P2-AT-INV-006-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-006-04** — Cancel Order hoặc giảm quantity phải release phần reservation không còn cần.
  - Evidence: `P2-AT-INV-006-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-006-05** — Partial shipment/Sales Issue consume phần reservation tương ứng và giữ reservation cho phần chưa xử lý.
  - Evidence: `P2-AT-INV-006-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-006-06** — Khi Sales Issue được post, phần reservation được consume đồng thời với stock issue liên quan.
  - Evidence: `P2-AT-INV-006-06`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-006-07** — Reservation không tự làm thay đổi On-hand Qty.
  - Evidence: `P2-AT-INV-006-07`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-006-08** — Baseline không cho reservation mới làm Available Qty âm; trường hợp overselling cần requirement/change request riêng.
  - Evidence: `P2-AT-INV-006-08`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-INV-006-09** — Với Product serial-managed, baseline reserve theo quantity; Serial cụ thể được chọn và validate khi Sales Issue được post.
  - Evidence: `P2-AT-INV-006-09`; xác minh trên persisted state/response/UI as applicable.

## SAL — Sales, Marketplace & Shipping

**Business objective:** Sales price source external/internal, inventory reservation, marketplace idempotency, carrier, COD và sales return.

### UR-SAL-001 — Sales Order

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SAL-001-01** — Direct/Internal Sales sử dụng Price Resolution theo Product + UOM + Company + Effective Time; Sales Order lưu giá cuối cùng, UOM và Price Source `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP` hoặc `PRODUCT_DEFAULT`.
  - Evidence: `P2-AT-SAL-001-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-001-02** — Direct/Internal Sales cho phép chọn COMBO như template. Khi chọn COMBO, hệ thống resolve giá root COMBO, flatten về SIMPLE/CHILD, phân bổ selling amount và aggregate leaf theo UR-PRD-006 trước các bước Reservation/Inventory/Serial/Costing/Posting.
  - Evidence: `P2-AT-SAL-001-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-001-03** — Sales Order phải giữ đủ traceability giữa leaf line và COMBO nguồn khi COMBO được dùng để tạo nhanh line.
  - Evidence: `P2-AT-SAL-001-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-001-04** — Marketplace Sales từ Shopee/TikTok Shop sử dụng external channel price/discount authoritative và lưu Price Source `EXTERNAL_CHANNEL`.
  - Evidence: `P2-AT-SAL-001-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-001-05** — Sales Order có Price Source `EXTERNAL_CHANNEL` không bị ERP Price Resolution/Promotion recompute lại giá marketplace.
  - Evidence: `P2-AT-SAL-001-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-001-06** — Sales Order lưu được nguồn giá, UOM và promotion/discount reference phù hợp với loại order.
  - Evidence: `P2-AT-SAL-001-06`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-001-07** — Delivery address sử dụng Province/City Code và Ward/Commune Code khi áp dụng.
  - Evidence: `P2-AT-SAL-001-07`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-001-08** — Sales Order tuân theo PBAC theo Company/Resource/State/Action.
  - Evidence: `P2-AT-SAL-001-08`; xác minh trên persisted state/response/UI as applicable.

### UR-SAL-002 — Shopee Integration

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SAL-002-01** — Lưu Shopee Order ID và Shop/Account source.
  - Evidence: `P2-AT-SAL-002-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-002-02** — Mapping SKU/Product.
  - Evidence: `P2-AT-SAL-002-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-002-03** — Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
  - Evidence: `P2-AT-SAL-002-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-002-04** — Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
  - Evidence: `P2-AT-SAL-002-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-002-05** — Giá/discount từ Shopee là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
  - Evidence: `P2-AT-SAL-002-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-002-06** — Đồng bộ trạng thái cần thiết.
  - Evidence: `P2-AT-SAL-002-06`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-002-07** — Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.
  - Evidence: `P2-AT-SAL-002-07`; xác minh trên persisted state/response/UI as applicable.

### UR-SAL-003 — TikTok Shop Integration

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SAL-003-01** — Lưu TikTok Shop Order ID và Shop/Account source.
  - Evidence: `P2-AT-SAL-003-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-003-02** — Mapping SKU/Product.
  - Evidence: `P2-AT-SAL-003-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-003-03** — Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
  - Evidence: `P2-AT-SAL-003-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-003-04** — Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
  - Evidence: `P2-AT-SAL-003-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-003-05** — Giá/discount từ TikTok Shop là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
  - Evidence: `P2-AT-SAL-003-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-003-06** — Đồng bộ trạng thái cần thiết.
  - Evidence: `P2-AT-SAL-003-06`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-003-07** — Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.
  - Evidence: `P2-AT-SAL-003-07`; xác minh trên persisted state/response/UI as applicable.

### UR-SAL-004 — Inventory dùng chung cho đa kênh

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SAL-004-01** — Không tạo kho logic riêng cạnh tranh chỉ vì khác sales channel.
  - Evidence: `P2-AT-SAL-004-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-004-02** — Order phải kiểm tra Available Qty theo Company/Warehouse phù hợp.
  - Evidence: `P2-AT-SAL-004-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-004-03** — Order được xác nhận phải reserve stock vào Inventory chung theo UR-INV-006.
  - Evidence: `P2-AT-SAL-004-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-004-04** — Release/consume reservation phải phản ánh vào Inventory chung.
  - Evidence: `P2-AT-SAL-004-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-004-05** — Sales Issue đã post làm giảm On-hand theo stock posting rule.
  - Evidence: `P2-AT-SAL-004-05`; xác minh trên persisted state/response/UI as applicable.

### UR-SAL-005 — Shipment và Carrier

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SAL-005-01** — Shipment thuộc cùng Company với Sales Order nguồn.
  - Evidence: `P2-AT-SAL-005-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-005-02** — Shipment truy được Sales Order và Warehouse fulfillment liên quan.
  - Evidence: `P2-AT-SAL-005-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-005-03** — Tracking Number và delivery status được lưu khi carrier trả về.
  - Evidence: `P2-AT-SAL-005-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-005-04** — Hủy vận đơn không tự xóa Shipment hoặc lịch sử carrier interaction.
  - Evidence: `P2-AT-SAL-005-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-005-05** — Shipping Fee và COD Amount được lưu để phục vụ COD Reconciliation khi áp dụng.
  - Evidence: `P2-AT-SAL-005-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-005-06** — Carrier integration error phải được ghi nhận để retry/xử lý.
  - Evidence: `P2-AT-SAL-005-06`; xác minh trên persisted state/response/UI as applicable.

### UR-SAL-006 — COD Reconciliation

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SAL-006-01** — Có thể lọc các shipment chưa đối soát.
  - Evidence: `P2-AT-SAL-006-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-006-02** — Chênh lệch phải xác định được amount và trạng thái xử lý.
  - Evidence: `P2-AT-SAL-006-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-006-03** — Khoản COD đã đối soát có thể tạo/liên kết Money Receipt.
  - Evidence: `P2-AT-SAL-006-03`; xác minh trên persisted state/response/UI as applicable.

### UR-SAL-007 — Sales Return

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SAL-007-01** — Sales Return truy được Sales Order/Shipment nguồn khi có.
  - Evidence: `P2-AT-SAL-007-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-007-02** — Returned goods chỉ làm tăng On-hand khi inventory return movement được post/confirm theo posting rule.
  - Evidence: `P2-AT-SAL-007-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-007-03** — Product serial-managed phải validate đúng Serial trả lại.
  - Evidence: `P2-AT-SAL-007-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-007-04** — Refund hoặc giao dịch Thu/Chi liên quan được reference, không tự tạo double-entry accounting.
  - Evidence: `P2-AT-SAL-007-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-007-05** — Return đã post không được xóa/sửa trực tiếp làm thay đổi stock history.
  - Evidence: `P2-AT-SAL-007-05`; xác minh trên persisted state/response/UI as applicable.

### UR-SAL-008 — Marketplace Account Mapping & Order Idempotency

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-SAL-008-01** — Mỗi Shop/Account xác định được Sales Channel/Platform.
  - Evidence: `P2-AT-SAL-008-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-008-02** — Mỗi Shop/Account map đúng một Company tại một thời điểm hiệu lực.
  - Evidence: `P2-AT-SAL-008-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-008-03** — Shop/Account có fulfillment Warehouse mặc định hoặc mapping Warehouse rõ ràng trước khi tạo Sales Order.
  - Evidence: `P2-AT-SAL-008-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-008-04** — `Sales Channel + Shop/Account + External Order ID` là unique business key.
  - Evidence: `P2-AT-SAL-008-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-008-05** — Retry/sync lại cùng external order không được tạo duplicate ERP Sales Order.
  - Evidence: `P2-AT-SAL-008-05`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-008-06** — Sync/retry phải idempotent đối với create/link order.
  - Evidence: `P2-AT-SAL-008-06`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-008-07** — Mapping Company/Warehouse phải hoàn tất trước khi ERP Sales Order được tạo.
  - Evidence: `P2-AT-SAL-008-07`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-SAL-008-08** — Lỗi mapping hoặc sync được giữ trạng thái/lý do để reprocess.
  - Evidence: `P2-AT-SAL-008-08`; xác minh trên persisted state/response/UI as applicable.

## FIN — Operational Finance

**Business objective:** Money Receipt/Expense từng Company; posted-only balance; không phải GL/double-entry/accounting.

### UR-FIN-001 — Ghi nhận Thu/Chi

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-FIN-001-01** — Mỗi Money Receipt/Money Expense thuộc đúng một Company.
  - Evidence: `P2-AT-FIN-001-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-001-02** — Amount phải lớn hơn 0.
  - Evidence: `P2-AT-FIN-001-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-001-03** — Account sử dụng phải thuộc cùng Company với transaction.
  - Evidence: `P2-AT-FIN-001-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-001-04** — Chỉ transaction đã `POSTED` mới tham gia operational balance.
  - Evidence: `P2-AT-FIN-001-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-001-05** — Transaction đã post không được xóa/sửa trực tiếp amount/account làm thay đổi history; correction phải qua reversal/adjustment có traceability.
  - Evidence: `P2-AT-FIN-001-05`; xác minh trên persisted state/response/UI as applicable.

### UR-FIN-002 — Danh mục Thu/Chi

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-FIN-002-01** — Category xác định được loại `RECEIPT` hoặc `EXPENSE`.
  - Evidence: `P2-AT-FIN-002-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-002-02** — Category inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
  - Evidence: `P2-AT-FIN-002-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-002-03** — Có thể lọc/tổng hợp Thu/Chi theo category.
  - Evidence: `P2-AT-FIN-002-03`; xác minh trên persisted state/response/UI as applicable.

### UR-FIN-003 — Cash và Bank Account

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-FIN-003-01** — Mỗi account thuộc một Company.
  - Evidence: `P2-AT-FIN-003-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-003-02** — Opening Balance và Opening Date được xác định trước khi tính operational balance từ thời điểm đó.
  - Evidence: `P2-AT-FIN-003-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-003-03** — User chỉ thao tác account trong PBAC scope.
  - Evidence: `P2-AT-FIN-003-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-003-04** — Account inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
  - Evidence: `P2-AT-FIN-003-04`; xác minh trên persisted state/response/UI as applicable.

### UR-FIN-004 — Tổng hợp Thu/Chi

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-FIN-004-01** — Chỉ posted Money Receipt/Money Expense được tính vào operational balance.
  - Evidence: `P2-AT-FIN-004-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-004-02** — Opening/Closing Balance không suy diễn từ General Ledger.
  - Evidence: `P2-AT-FIN-004-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-004-03** — Có thể tính balance theo từng Cash/Bank Account và tổng hợp các account trong Company theo filter.
  - Evidence: `P2-AT-FIN-004-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-004-04** — Report theo nhiều Company chỉ tổng hợp Company User có quyền xem.
  - Evidence: `P2-AT-FIN-004-04`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-004-05** — Operational Balance không được mô tả như bank reconciliation hoặc statutory accounting balance.
  - Evidence: `P2-AT-FIN-004-05`; xác minh trên persisted state/response/UI as applicable.

### UR-FIN-005 — Liên kết nguồn giao dịch

**Priority:** MUST · **Source status:** BASELINE · **P2 implementation:** NOT_IMPLEMENTED · **Master spec:** `01_P2_USER_REQUIREMENTS_BASELINE.md`

- **P2-FR-FIN-005-01** — Có thể truy từ Money Receipt/Money Expense về Source Reference khi nguồn còn tồn tại và User có quyền.
  - Evidence: `P2-AT-FIN-005-01`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-005-02** — Một source reference không tự tạo double-entry journal.
  - Evidence: `P2-AT-FIN-005-02`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-005-03** — COD đã reconciliation có thể tạo/liên kết Money Receipt mà vẫn giữ Shipment/COD reference.
  - Evidence: `P2-AT-FIN-005-03`; xác minh trên persisted state/response/UI as applicable.
- **P2-FR-FIN-005-04** — Payroll payment có thể liên kết Money Expense theo đúng Company.
  - Evidence: `P2-AT-FIN-005-04`; xác minh trên persisted state/response/UI as applicable.

## Quy tắc nghiệm thu liên module bắt buộc

| Invariant | Điểm bắt buộc kiểm tra | Negative/regression |
|---|---|---|
| Company ownership | PR/PO/GR, transfer, sales, shipment, money | cross-company references → reject trước mutation |
| COMBO leaf stock | sales/purchase order → leaf movement/serial | không được có inventory ledger row cho COMBO |
| Price authority | Internal sales P1 resolution, marketplace EXTERNAL_CHANNEL | không tự recompute external order, không dùng leaf Price List làm allocation weight |
| Posting-only | GR, return, issue, adjustment, money | draft/pending không đổi số dư, posted edit không ghi đè |
| Reservation | confirm → reserve; cancel → release; post issue → consume | không âm available; partial consume idempotent |
| ID/Mapping | channel + account + external_order_id | duplicate/retry không tạo Sales Order thứ hai |
| Authorization | P2 resource/action, actor assignments, SQL scope | no Base Permission → deny; sibling/cross-company không lọt |
| Lifecycle | legal transition + PBAC đều pass | permission có nhưng transition sai vẫn reject |
| Import | resource explicit enable mới mở | Job import không bypass create/edit/setstate per row |
| Observability | outbox/retry/COD/stock reconciliation | replay không double post/issue/receive |

## Definition of DONE cho một P2-FR

1. API và schema đã được phê duyệt, version hóa; migration forward-only và backup DEV clone.
2. Unit/Integration/HTTP E2E test cho `P2-AT-*` PASS; test âm cho permission, validation, state và idempotency nếu liên quan.
3. UI responsive vi/en, display formatting presentation-only; no hidden backend behavior.
4. Có log actor/correlation/exception; không lộ payload PII/token; dashboards/reconciliation theo module.
5. Mapping UR→FR→DB/API/UI/Test cập nhật; release note/rollback và runbook đồng bộ.
