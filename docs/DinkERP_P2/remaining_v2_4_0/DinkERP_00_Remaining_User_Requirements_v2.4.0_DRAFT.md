---
document_id: "REQ-DINKERP-REMAINING-001"
title: "DinkERP — User Requirements chưa triển khai (delta so với v2.4.0)"
version: "1.0.0-DRAFT"
status: "DRAFT_FOR_PRODUCT_OWNER_REVIEW"
date: "2026-10-31"
source_ur: "00_User_Requirements_v1.0.15.md"
source_ur_sha256: "97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144"
source_code: "erp_v2.4.0.zip"
source_code_sha256: "ded6726e373a6d622cc9ca84dae82d51cc37bfd0b5d9fd4394735bd77ec2fb56"
supersedes_original_ur: false
implementation_or_db_changes: false
---

# DinkERP — Yêu cầu người dùng CHƯA triển khai

> **Bản lọc phạm vi còn thiếu sau v2.4.0 — phục vụ Product Owner xem xét.** Không thay thế hoặc tự thay đổi yêu cầu `BASELINE` của `00_User_Requirements_v1.0.15.md`; không tự duyệt thiết kế P2/P3, không phải biên bản nghiệm thu. Các yêu cầu cấp nghiệp vụ được giữ đúng nghĩa theo bản gốc. Khi có mâu thuẫn, ưu tiên nguồn UR và các quyết định được Product Owner phê duyệt có hiệu lực.

## 1. Phạm vi, nguyên tắc và nguồn chứng cứ

- **Mốc phân loại:** nội dung nguồn `erp_v2.4.0.zip` (snapshot 31/10/2026), chỉ xét `backend/`, `frontend/`, canonical OpenAPI và migrations đang hoạt động; `backend_baseline/` chỉ là lịch sử.
- **Không mô tả lại:** các capability nền tảng P1 và phần CRUD/Contact/Numbering/Shared History CUS/SUP đã có mã nguồn. Chúng vẫn phải được regression và nghiệm thu trên DEV theo Gate tương ứng, nhưng không phải feature backlog mới.
- **Có mô tả:** 26 UR P2 chưa có implementation nghiệp vụ trong source, 6 hạng mục/lát cắt của CUS/SUP (Customer Usage cho Sales, Supplier Usage cho Purchase, transaction readers, Supplier bank và CUS addendum), phần Pricing mở rộng P2 nằm trong UR P1, 13 UR P3 chưa có module, và yêu cầu liên module CUS đã phê duyệt nhưng còn dependency.
- **Độ chắc chắn:** `SOURCE_ABSENT` = chưa thấy module/route/migration/UI nghiệp vụ trong snapshot; `PARTIAL_DEPENDENCY_PENDING` = đã có source phần master/history nhưng thiếu giao dịch nguồn; `SECURITY_BLOCKED` = có metadata nền nhưng chưa có quyền/hành vi sử dụng; **không có nghĩa đã kiểm tra runtime production/DEV**.
- **Chỉ dùng chức năng:** mô tả người dùng, rule/AC và phụ thuộc. Bảng, endpoint, màn hình, transaction schema, status bổ sung và SLA sẽ khóa ở FRS/ADR/DB/API/UI downstream, không được coi là đã phê duyệt từ tài liệu này.

### 1.1 Định nghĩa trạng thái triển khai

| Ký hiệu | Ý nghĩa | Điều kiện bỏ khỏi backlog |
|---|---|---|
| `SOURCE_ABSENT` | Chưa có mô-đun kinh doanh hoàn chỉnh trong runtime source v2.4.0 | Có code + test qua G0–G7, nghiệm thu theo AC |
| `PARTIAL_DEPENDENCY_PENDING` | Source của master/foundation đã có; projection hoặc read liên module chưa thể hoạt động vì thiếu source | Hợp đồng nguồn được duyệt, có source thật, permission và test integration |
| `SECURITY_BLOCKED` | Schema/catalog dự phòng tồn tại, nhưng endpoint bảo mật và approval chưa có | Security/DBA signoff + implementation + negative audit test |
| `OPEN_DESIGN` | Cần quyết định nghiệp vụ/kỹ thuật trước khi viết code; không phải chức năng đã tồn tại | ADR có owner, quyết định và ảnh hưởng traceability |
| `NOT_RUN` | Chưa chạy kiểm thử runtime DB/HTTP/Browser | Có log bài test, môi trường và kết quả |

### 1.2 Phạm vi theo nguồn v1.0.15

| Nhóm | Mức độ còn thiếu trong source v2.4.0 | Bộ UR nguồn |
|---|---|---|
| DOC | 2 UR P2, trong đó `UR-DOC-002` là SHOULD | `UR-DOC-001..002` |
| PRC | `UR-PRC-002` và lát cắt P2 của `UR-PRC-001` | `UR-PRC-001 (P2 extension)`, `UR-PRC-002` |
| PUR | 4 UR P2 | `UR-PUR-001..004` |
| INV | 6 UR P2 | `UR-INV-001..006` |
| SAL | 8 UR P2 | `UR-SAL-001..008` |
| FIN | 5 UR P2 | `UR-FIN-001..005` |
| CUS | Customer Usage/Status guard cho Sales mới; lịch sử chứng từ và reader SAL/FIN còn phụ thuộc | `UR-CUS-001..002`, CUS approved XMOD |
| SUP | Supplier Usage/Status guard cho Purchase mới; ngân hàng bảo mật và lịch sử PUR/FIN còn thiếu | `UR-SUP-001..002` (delta) |
| HRP | 9 UR P3, `UR-HRP-009` là SHOULD | `UR-HRP-001..009` |
| BOM | 4 UR P3 | `UR-BOM-001..004` |

**Kiểm soát số lượng:** 30 UR P2 gốc = **26 UR chưa có capability nguồn** + **4 UR CUS/SUP đã có nguồn từng phần**; trong 4 UR này, `UR-CUS-001` chỉ còn integration guard chứ không phải Master CRUD mới. Có **139/147 AC P2 cần tiếp tục đánh giá triển khai** theo cách phân loại source còn thiếu nêu trong CSV; 8 AC CUS/SUP còn lại không được gắn kết luận runtime PASS. P3 có **13 UR / 53 AC** mới thuộc phase riêng. Tổng số lượng AC trong CSV từ baseline gốc là **192**, chưa kể test bổ sung chưa baseline.

### 1.3 Điều đã đối chiếu để tránh phát triển lại

- CUS: `CustomersRoutes.php` có 7 core routes và `customer_interactions`; `Contact` có owner `CUSTOMER`, contacts dùng làm địa chỉ giao hàng. **Không thêm `customer_addresses`/`customer_groups` tables hoặc CRUD riêng** chỉ vì các P2 draft cũ từng đề xuất.
- CUS Group codes đã phê duyệt: `STANDARD`, `TMDT`, `VIP1`, `VIP2` (nullable). PRC P2 dùng `customer_group_code` canonical; `TMDT` không phải `EXTERNAL_CHANNEL`.
- SUP: `SuppliersRoutes.php` có 7 core routes, `Contact` hỗ trợ `SUPPLIER` và Shared History có master change events; không làm lại master CRUD hoặc audit.
- SUP ngân hàng: migration `000028` chỉ dự phòng encrypted metadata; `view_bank`/`edit_bank` là catalog actions chưa có secure endpoints. Cần Security/DBA approval; không được dùng cột cipher như tính năng đã hoàn tất.
- P1 `Pricing/PriceList` đã có mã cấu hình danh sách giá và item. **Chưa tìm thấy dịch vụ Price Resolution nghiệp vụ cho Sales trong snapshot; phải xác nhận/hoàn thiện hợp đồng giải giá P1 trước khi tích hợp PRC P2/SAL**, không tạo một Pricing store/song song không được duyệt. `Product` có ảnh đại diện, nhưng **không đồng nghĩa DOC Attachment/Versioning đã tồn tại**.
- Bộ P2 docs hiện tại dựa trên `erp_v1.9.0.zip`; trạng thái `NOT_IMPLEMENTED` của CUS/SUP trong tài liệu cũ **không phải trạng thái source v2.4.0**.

## 2. Phần còn thiếu của CUS / SUP (không phát triển lại Master)

### REM-CUS-001-SAL-GUARD — Áp dụng Customer Usage/Status vào Sales Order mới

- **Nguồn:** `UR-CUS-001`, phần còn chờ `P2-AT-CUS-001-03` và `P2-AT-CUS-001-05`. **P2 / MUST / `PARTIAL_DEPENDENCY_PENDING`.**
- **Người dùng cần:** chỉ tạo Sales Order mới khi Customer đang ACTIVE và Sales Company nằm trong All Companies/allowlist; sửa Company Usage/inactivate không xóa hay che lịch sử giao dịch cũ.
- **Không xây lại:** Customer Master CRUD, Company Usage configuration và lookup P1/Contact đã có. Tích hợp guard ở **SAL mutation thuộc Company đã được authorize**; không tin Company ID client gửi là persisted owner.
- **Phụ thuộc:** `REM-SAL-001` và quyền PBAC Sales, cần test user Company A/B, inactive Customer, order history và race khi thay Usage.

### REM-CUS-002-TX — Lịch sử giao dịch Customer từ nguồn SAL/FIN

- **Nguồn:** `UR-CUS-002`, đặc biệt `P2-AT-CUS-002-02`. **P2 / MUST / `PARTIAL_DEPENDENCY_PENDING`.**
- **Người dùng cần:** tại hồ sơ Customer xem Sales Order, Shipment, Sales Return, tiền thu có liên quan; lọc/điều hướng theo Company và thời gian với quyền VIEW từng chứng từ nguồn.
- **Không xây lại:** interaction `NOTE/CALL/EMAIL/MEETING/CORRECTION`, Shared Business History và Customer Contact đã có source.
- **Điều kiện nghiệm thu còn thiếu:** `P2-AT-CUS-002-02` — Xem được Sales Order/Shipment/Sales Return/Thu tiền liên quan trong phạm vi quyền.
- **Nguồn dữ liệu:** các bảng/Service thuộc SAL và FIN sau khi module nguồn tồn tại; không lưu bản sao tài chính hay stock ledger trong Customer.
- **Dependency:** `REM-SAL-001/005/007`, `REM-FIN-001/005`; reader chỉ bật sau khi nguồn và PBAC đã được kiểm tra trên MariaDB.

### REM-CUS-XMOD-READ — Báo cáo bán hàng và công nợ vận hành Customer (đã duyệt ở CUS addendum)

- **Nguồn có thẩm quyền:** CUS G1 approved package `docs/07_SAL_FIN_PRC_DEPENDENCY_CONTRACTS.md` (business decisions), `CUS-T-API-01` xác định 3 readers deferred: `transactions`, `sales-summary`, `receivables`. **`PARTIAL_DEPENDENCY_PENDING`.**
- **Người dùng cần:** xem `confirmed_order_value`, `fulfilled_goods_value`, `net_sales_operational` theo Company/kỳ; xem `open_due`, `overpay`, `unapplied_customer_credit` đối với Direct/Internal identified Customer hậu thanh toán theo nguồn SAL/FIN.
- **Ranh giới đã duyệt:** thu tiền POSTED không đồng nghĩa thanh toán cho một Sales Order nếu chưa có allocation; COD carrier collected không đồng nghĩa Company đã nhận tiền; không tính khoản thu hai lần. Số liệu as-of phải bao gồm sự kiện trước kỳ khi cần xác định công nợ cuối kỳ.
- **Chưa được coi là chốt thiết kế:** mốc charge/fulfillment chi tiết, partial shipment, return credit, payment application/refund và correction event là hợp đồng thuộc module nguồn, cần review tại SAL/FIN gate.
- **Dependency:** SAL order/issue/delivery/return snapshots, FIN posted allocation/refund và nguồn COD.

### REM-SUP-001-PUR-GUARD — Áp dụng Supplier Usage/Status vào Purchase mới

- **Nguồn:** `UR-SUP-001`, phần còn chờ `P2-AT-SUP-001-03` và `P2-AT-SUP-001-05`. **P2 / MUST / `PARTIAL_DEPENDENCY_PENDING`.**
- **Người dùng cần:** PO mới chỉ chọn Supplier ACTIVE và được phép dùng trong Company của PO; PO/GR/Return/Money đã tạo vẫn tra cứu được sau khi Supplier inactive hoặc thay usage.
- **Đã có nền:** `SupplierUsabilityGuard` với `AuthorizedPurchaseCompanyPort`; Purchase adapter authoritative chưa tồn tại. Không tạo guard mới cạnh tranh, chỉ hoàn tất port sau khi Purchase có persisted Company.
- **Phụ thuộc:** `REM-PUR-002`, Supplier Company Usage và PUR PBAC; test cross Company, stale status, transaction rollback và concurrency với Supplier mutation.

### REM-SUP-001-BANK — Quản lý thông tin ngân hàng Supplier an toàn

- **Nguồn:** trường `Bank Account` của `UR-SUP-001` và hồ sơ SUP-T07 Security/DBA. **P2 / MUST / `SECURITY_BLOCKED`.**
- **Người dùng cần:** người có quyền phù hợp có thể nhập, cập nhật và xem hạn chế thông tin thanh toán ngân hàng của Supplier; người không có quyền không nhận được giá trị rõ qua list/detail/export/log/history.
- **Đang có:** cột dự phòng `bank_ciphertext`, `bank_nonce`, `bank_tag`, `bank_key_version`, `bank_last4` trong migration; catalog `supplier.view_bank`/`supplier.edit_bank` nhưng không có API thực thi.
- **Tiêu chí bổ sung CHƯA baseline:** xác nhận key custody/rotation, authenticated encryption, masking, audit reveal/change, backup/restore, quyền theo actor/Group và chính sách export; test negative và no plaintext leak trước khi bật.
- **Chặn G1:** Security/DBA phê duyệt schema/crypto/audit và kiểm thử DEV thực tế; không lưu plaintext hoặc mở field vào API generic.

### REM-SUP-002-TX — Lịch sử giao dịch Supplier từ PUR/FIN

- **Nguồn:** `UR-SUP-002`, AC `P2-AT-SUP-002-01..03`. **P2 / MUST / `PARTIAL_DEPENDENCY_PENDING`.**
- **Người dùng cần:** tại Supplier xem PO, Goods Receipt, Purchase Return và thanh toán/chi liên quan theo quyền Company và resource riêng.
- **Không xây lại:** `GET /api/v1/history/suppliers/{id}` hiện là **master change history**, chưa phải lịch sử transaction PUR/FIN.
- **Tiêu chí nghiệm thu còn thiếu:** (1) Chỉ hiển thị lịch sử thuộc Company trong authorization scope; (2) điều hướng tới chứng từ nguồn chỉ khi có quyền VIEW; (3) lịch sử giao dịch vẫn đọc được khi Supplier inactive.
- **Dependency:** `REM-PUR-002..004`, `REM-FIN-001/005`. Khi triển khai phải không dùng Supplier ACTIVE filter để ẩn chứng từ lịch sử.

## 3. Phần còn thiếu P2 — Operational ERP

### 3.1 PRC extension riêng của `UR-PRC-001` (P1 có Price List CRUD; resolver cần Gate xác nhận)

#### REM-PRC-001-X — Mở rộng ngữ cảnh Price List/Price Resolution

- **Nguồn:** đoạn **P2 - Extended Pricing Context** trong `UR-PRC-001`; phần này là target P2 dù UR gốc đặt Target phase=P1. **P2 / MUST / `SOURCE_ABSENT`.**
- **Người dùng cần:** khai báo giá theo Customer cụ thể, mã Customer Group hoặc Sales Channel, đồng thời giữ Company Price List ưu tiên Group, cuối cùng default đúng Product+UOM.
- **Rule kế thừa:** trong cùng Company/Group scope, áp dụng mức đặc hiệu `Customer → Customer Group → Sales Channel`, sau đó `Priority DESC → Effective From DESC → ID ASC` khi có các candidate; mapping chính xác cần ký ADR `P2-DEC-012`. Effective window `[from,to)` theo business evaluation time.
- **Đã chốt từ CUS:** `customer_group_code` là `STANDARD/TMDT/VIP1/VIP2` (hoặc null); không tạo bảng `customer_groups` hay `customer_group_id` chỉ theo P2 draft cũ.
- **Không thay đổi:** Price List P1, Alternate UOM prices và COMBO root pricing/flattening; external marketplace `EXTERNAL_CHANNEL` không được tính lại bằng resolver.
- **Dependency:** Customer core đã có source; P1 Price List config/item tồn tại nhưng chưa xác minh có service Price Resolution, nên phải có resolver authoritative và test default/UOM trước khi thêm context P2; SAL Direct/Internal Sales dùng snapshot giá khi tạo/confirm Order.
- **AC bổ sung cần PO chốt:** code group đúng, fallback đúng UOM, tie-break deterministic, price-source trace, không repricing old orders.

### 3.2 Các yêu cầu P2 chưa có module hoàn chỉnh (theo nguyên văn UR / AC)

> Mỗi mục dưới đây chỉ chứa **UR thuộc phần chức năng còn thiếu**; tiêu chí nghiệm thu trích giữ nguyên nội dung của v1.0.15 và có ID tương ứng trong file traceability. `P2-AT-*` được kế thừa từ catalog P2, không phải ID mới.

#### Nhóm DOC — 2 yêu cầu nguồn chưa triển khai

##### REM-DOC-001 — Attachment theo đối tượng nghiệp vụ

**Nguồn:** `UR-DOC-001` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W1 (song song).

**Kết quả người dùng cần:** Upload/link/read file theo loại owner được bật; PBAC trên Document **và** owner persisted, owner disabled vẫn giữ file lịch sử.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

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

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-DOC-001-01`** — File xác định được đối tượng nghiệp vụ liên quan.
- **`P2-AT-DOC-001-02`** — Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ.

**Phụ thuộc:** Product, Employee, CUS, SUP; nguồn PUR/INV/SAL/FIN/HRP khi có.
**Quyết định cần soát:** `P2-DEC-005, P2-DEC-006`. Quyền trên owner phải được xác minh ở thời điểm thao tác; không coi Product Image đang có là hệ thống Document.

##### REM-DOC-002 — Tìm kiếm và version tài liệu

**Nguồn:** `UR-DOC-002` · **Phase:** P2 · **Ưu tiên:** SHOULD · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W1 (song song).

**Kết quả người dùng cần:** Lưu version cũ, metadata người tải/thời gian; tìm kiếm chỉ trên tập owner được xem trước phân trang.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Hỗ trợ:
- Upload/Download
- Search
- Tag/Category
- Version
- Uploaded By/At

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-DOC-002-01`** — Có thể xem version hiện tại và version trước.
- **`P2-AT-DOC-002-02`** — Tài liệu bị thay thế không làm mất lịch sử.

**Phụ thuộc:** REM-DOC-001.
**Quyết định cần soát:** `P2-DEC-005, P2-DEC-006`. Quyền trên owner phải được xác minh ở thời điểm thao tác; không coi Product Image đang có là hệ thống Document.

#### Nhóm PRC — 1 yêu cầu nguồn chưa triển khai

##### REM-PRC-002 — Promotion

**Nguồn:** `UR-PRC-002` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3.

**Kết quả người dùng cần:** Promotion/discount/special price/quantity/gift/coupon có hiệu lực, priority, combinability; Sales lưu snapshot mã khuyến mại và kết quả đã áp dụng.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

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

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-PRC-002-01`** — Promotion chỉ áp dụng trong thời gian hiệu lực và khi đủ điều kiện.
- **`P2-AT-PRC-002-02`** — Sales Order lưu được promotion/coupon đã áp dụng.
- **`P2-AT-PRC-002-03`** — Special Price được xử lý trước Discount/Promotion khác.
- **`P2-AT-PRC-002-04`** — Promotion được evaluate theo Priority và không được tạo stacking tùy ý ngoài rule đã cấu hình.
- **`P2-AT-PRC-002-05`** — Promotion có `Combinable=false` không được kết hợp với Promotion/Coupon khác trái với rule.
- **`P2-AT-PRC-002-06`** — Nếu các Promotion cùng Priority và scope có thể tạo kết quả không xác định, cấu hình phải bị từ chối hoặc được điều chỉnh trước khi active.

**Phụ thuộc:** REM-PRC-001-X, REM-SAL-001.
**Quyết định cần soát:** `P2-DEC-012, P2-DEC-013, P2-DEC-014`. Giữ P1 Company → Group → Default giá đúng UOM; không tái tính giá marketplace.

#### Nhóm PUR — 4 yêu cầu nguồn chưa triển khai

##### REM-PUR-001 — Purchase Request

**Nguồn:** `UR-PUR-001` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2.

**Kết quả người dùng cần:** PR có submit/approve/reject/cancel; Approved PR liên kết được PO, trạng thái không bypass quyền.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Cho phép tạo Purchase Request.

Lifecycle cơ bản:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- REJECTED
- CANCELLED

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-PUR-001-01`** — PR có thể Submit/Approve/Reject theo PBAC.
- **`P2-AT-PUR-001-02`** — PR Approved có thể dùng để tạo Purchase Order.

**Phụ thuộc:** Supplier, Organization, PBAC, Workflow.
**Quyết định cần soát:** `P2-DEC-015, P2-DEC-016, P2-DEC-017`. Supplier Usage, Company/Warehouse và UOM phải hợp lệ. Goods Receipt canonical khác Money Receipt.

##### REM-PUR-002 — Purchase Order

**Nguồn:** `UR-PUR-002` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2.

**Kết quả người dùng cần:** PO là document thuộc một Company; Supplier usable, destination warehouse cùng Company; trace approval và line price/tax/payment term.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

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

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-PUR-002-01`** — PO tuân theo PBAC theo Company/Resource/State/Action.
- **`P2-AT-PUR-002-02`** — UOM phải hợp lệ cho Product; Quantity phải có thể quy đổi về Base UOM khi sử dụng Alternate UOM của SIMPLE/CHILD.
- **`P2-AT-PUR-002-03`** — COMBO có thể được chọn như template để nhập nhanh Purchase line, nhưng trước receiving/inventory/cost operational processing phải expand về SIMPLE/CHILD; COMBO selling-price allocation không được tự dùng làm purchase-price allocation.
- **`P2-AT-PUR-002-04`** — Purchase price/cost của các leaf sau expansion phải được xác định theo nghiệp vụ Purchase tương ứng, không suy từ COMBO selling price nếu chưa có requirement riêng.
- **`P2-AT-PUR-002-05`** — PO giữ lịch sử phê duyệt và thay đổi quan trọng.

**Phụ thuộc:** REM-PUR-001, Supplier, Product/UOM/Numbering.
**Quyết định cần soát:** `P2-DEC-015, P2-DEC-016, P2-DEC-017`. Supplier Usage, Company/Warehouse và UOM phải hợp lệ. Goods Receipt canonical khác Money Receipt.

##### REM-PUR-003 — Goods Receipt

**Nguồn:** `UR-PUR-003` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2.

**Kết quả người dùng cần:** Nhận từng phần/nhiều lần, serial kiểm tra lúc post; cập nhật PO received và stock trong cùng transaction, không double-post.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Cho phép nhận hàng một phần hoặc nhiều lần bằng chứng từ **Goods Receipt**. `Receiving` được dùng để mô tả process; tên chứng từ canonical là Goods Receipt.

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-PUR-003-01`** — Chỉ Goods Receipt đã đạt posting state của module (`POSTED` hoặc trạng thái `CONFIRMED` được định nghĩa tương đương posting) mới làm tăng On-hand Inventory.
- **`P2-AT-PUR-003-02`** — Goods Receipt cập nhật Received Quantity của Purchase Order nguồn.
- **`P2-AT-PUR-003-03`** — Product serial-managed bắt buộc ghi Serial khi Goods Receipt được post.
- **`P2-AT-PUR-003-04`** — Không nhận vào Warehouse thuộc Company khác Purchase Order.
- **`P2-AT-PUR-003-05`** — Goods Receipt đã post không được delete/edit trực tiếp các dữ liệu làm thay đổi stock; correction phải qua reversal hoặc Inventory Adjustment phù hợp.

**Phụ thuộc:** REM-PUR-002, REM-INV-001/002/005.
**Quyết định cần soát:** `P2-DEC-015, P2-DEC-016, P2-DEC-017`. Supplier Usage, Company/Warehouse và UOM phải hợp lệ. Goods Receipt canonical khác Money Receipt.

##### REM-PUR-004 — Purchase Return

**Nguồn:** `UR-PUR-004` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2.

**Kết quả người dùng cần:** Return theo GR/PO, đúng Serial và số lượng đã nhận/chưa trả; chỉ posted return giảm stock.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Cho phép trả hàng Supplier.

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-PUR-004-01`** — Chỉ Purchase Return đã đạt posting state mới làm giảm On-hand Inventory.
- **`P2-AT-PUR-004-02`** — Serial-managed item phải xác định đúng Serial trả.
- **`P2-AT-PUR-004-03`** — Có thể truy về Goods Receipt/Purchase Order nguồn.
- **`P2-AT-PUR-004-04`** — Purchase Return đã post không được delete/edit trực tiếp làm thay đổi stock history; correction phải qua reversal/adjustment phù hợp.

**Phụ thuộc:** REM-PUR-003, REM-INV-002/005.
**Quyết định cần soát:** `P2-DEC-015, P2-DEC-016, P2-DEC-017`. Supplier Usage, Company/Warehouse và UOM phải hợp lệ. Goods Receipt canonical khác Money Receipt.

#### Nhóm INV — 6 yêu cầu nguồn chưa triển khai

##### REM-INV-001 — Inventory Balance

**Nguồn:** `UR-INV-001` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2–W3.

**Kết quả người dùng cần:** Tồn theo Company + Warehouse + SIMPLE/CHILD, source canonical Base UOM; available=onhand-reserved.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Theo dõi tồn theo:

**Company + Warehouse + Product**

và Serial khi áp dụng. Inventory balance canonical được hiểu theo Base UOM của Product; giao dịch có thể nhập bằng source UOM hợp lệ và quy đổi về Base UOM.

Các quantity vận hành tối thiểu:
- On-hand Qty
- Reserved Qty
- Available Qty = On-hand Qty - Reserved Qty

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-INV-001-01`** — Có thể xem On-hand, Reserved và Available theo từng Warehouse.
- **`P2-AT-INV-001-02`** — Inventory balance chỉ tồn tại cho SIMPLE/CHILD leaf; COMBO không có On-hand/Reserved/Available riêng.
- **`P2-AT-INV-001-03`** — On-hand chỉ phản ánh stock movement đã được post/confirm theo posting rule.
- **`P2-AT-INV-001-04`** — Có thể xem tồn Group bằng tổng hợp Company được phép.
- **`P2-AT-INV-001-05`** — Không trộn tồn giữa các Company.

**Phụ thuộc:** P1 Product, Organization/Warehouse, REM-INV-002, REM-INV-006.
**Quyết định cần soát:** `P2-DEC-018..022`. Nguồn sự thật là posted movement; không post COMBO/PARENT; cân bằng stock và reservation atomic.

##### REM-INV-002 — Inventory Movement

**Nguồn:** `UR-INV-002` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2–W3.

**Kết quả người dùng cần:** Ledger post một lần với source, actor, time, UOM/quantity nguồn và base; đã post chỉ correction/reversal có trace.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

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

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-INV-002-01`** — Chỉ movement ở posting state (`POSTED` hoặc module-defined `CONFIRMED` tương đương posting) mới làm thay đổi On-hand Qty.
- **`P2-AT-INV-002-02`** — Inventory movement chỉ post SIMPLE/CHILD leaf; nếu source document sử dụng COMBO thì phải flatten trước khi tạo/post stock movement.
- **`P2-AT-INV-002-03`** — Draft/Pending movement không làm thay đổi On-hand Qty.
- **`P2-AT-INV-002-04`** — Movement đã post không được delete hoặc sửa trực tiếp quantity/product/warehouse làm thay đổi stock history.
- **`P2-AT-INV-002-05`** — Correction sau posting phải được thực hiện bằng reversal hoặc Inventory Adjustment có traceability.
- **`P2-AT-INV-002-06`** — Mỗi posted movement truy được Source Document, User và Timestamp.

**Phụ thuộc:** REM-INV-001; nguồn PUR/SAL.
**Quyết định cần soát:** `P2-DEC-018..022`. Nguồn sự thật là posted movement; không post COMBO/PARENT; cân bằng stock và reservation atomic.

##### REM-INV-003 — Warehouse Transfer

**Nguồn:** `UR-INV-003` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2–W3.

**Kết quả người dùng cần:** Transfer giữa hai kho cùng Company, posting hai đầu nguyên tử, cấm internal transfer xuyên Company.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Cho phép chuyển hàng giữa Warehouse trong cùng Company.

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-INV-003-01`** — Source và destination Warehouse phải cùng Company.
- **`P2-AT-INV-003-02`** — Cross-Company movement không được xử lý như internal warehouse transfer.
- **`P2-AT-INV-003-03`** — Chỉ Transfer đã đạt posting state mới làm thay đổi stock location/balance.
- **`P2-AT-INV-003-04`** — Posted Transfer phải giữ trace giữa source và destination movement.

**Phụ thuộc:** REM-INV-001/002/005.
**Quyết định cần soát:** `P2-DEC-018..022`. Nguồn sự thật là posted movement; không post COMBO/PARENT; cân bằng stock và reservation atomic.

##### REM-INV-004 — Stock Count và Adjustment

**Nguồn:** `UR-INV-004` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2–W3.

**Kết quả người dùng cần:** Stock Count snapshot tại thời điểm kiểm kê, variance và Adjustment được duyệt; không âm thầm dùng snapshot stale khi post.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Hỗ trợ:
- Tạo Stock Count
- Ghi nhận actual quantity
- So sánh system quantity
- Xác nhận variance
- Tạo Inventory Adjustment

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-INV-004-01`** — Adjustment chỉ được ghi nhận theo PBAC.
- **`P2-AT-INV-004-02`** — Adjustment chỉ làm thay đổi On-hand khi đạt posting state.
- **`P2-AT-INV-004-03`** — Có thể truy từ Adjustment về Stock Count và người xác nhận.
- **`P2-AT-INV-004-04`** — Posted Adjustment không được delete/edit trực tiếp làm thay đổi stock history.

**Phụ thuộc:** REM-INV-001/002/005.
**Quyết định cần soát:** `P2-DEC-018..022`. Nguồn sự thật là posted movement; không post COMBO/PARENT; cân bằng stock và reservation atomic.

##### REM-INV-005 — Serial Inventory & Transaction Traceability

**Nguồn:** `UR-INV-005` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2–W3.

**Kết quả người dùng cần:** Serial duy nhất theo Product trong phạm vi rule đã duyệt; một serial không tồn tại đồng thời tại nhiều kho.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Đối với Product được cấu hình quản lý Serial, Inventory phải theo dõi Serial xuyên suốt các giao dịch vận hành.

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-INV-005-01`** — Serial xác định được Product, Company, Warehouse hiện tại và trạng thái.
- **`P2-AT-INV-005-02`** — Serial truy được Goods Receipt, Sales Issue, transfer, purchase return và sales return liên quan.
- **`P2-AT-INV-005-03`** — Một Serial không thể đồng thời tồn tại ở nhiều Warehouse.
- **`P2-AT-INV-005-04`** — Goods Receipt của Product serial-managed phải ghi nhận Serial trước khi posting.
- **`P2-AT-INV-005-05`** — Sales Issue của Product serial-managed phải xác định Serial xuất.
- **`P2-AT-INV-005-06`** — Warehouse Transfer phải chuyển trạng thái/location của đúng Serial.
- **`P2-AT-INV-005-07`** — Purchase Return và Sales Return phải tham chiếu đúng Serial khi áp dụng.

**Phụ thuộc:** REM-INV-002/003/004, REM-PUR-003, REM-SAL-001.
**Quyết định cần soát:** `P2-DEC-018..022`. Nguồn sự thật là posted movement; không post COMBO/PARENT; cân bằng stock và reservation atomic.

##### REM-INV-006 — Inventory Reservation

**Nguồn:** `UR-INV-006` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2–W3.

**Kết quả người dùng cần:** Reserve/Release/Consume theo leaf Sales Order line; không âm Available ở bước reserve; partial issue consume atomic.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Inventory Reservation giữ một phần Available Qty cho Sales Order đã được xác nhận mà chưa được consume thành Sales Issue.

Invariant:

**Available Qty = On-hand Qty - Reserved Qty**

Lifecycle cơ bản:

**Reserve -> Release / Consume**

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-INV-006-01`** — Reservation xác định Company, Warehouse, Product, Reserved Quantity và Source Sales Order/Order Line.
- **`P2-AT-INV-006-02`** — Reservation chỉ giữ quantity của SIMPLE/CHILD leaf; COMBO phải được flatten trước reservation.
- **`P2-AT-INV-006-03`** — Reservation được tạo khi Sales Order đạt trạng thái xác nhận vận hành theo lifecycle của module.
- **`P2-AT-INV-006-04`** — Cancel Order hoặc giảm quantity phải release phần reservation không còn cần.
- **`P2-AT-INV-006-05`** — Partial shipment/Sales Issue consume phần reservation tương ứng và giữ reservation cho phần chưa xử lý.
- **`P2-AT-INV-006-06`** — Khi Sales Issue được post, phần reservation được consume đồng thời với stock issue liên quan.
- **`P2-AT-INV-006-07`** — Reservation không tự làm thay đổi On-hand Qty.
- **`P2-AT-INV-006-08`** — Baseline không cho reservation mới làm Available Qty âm; trường hợp overselling cần requirement/change request riêng.
- **`P2-AT-INV-006-09`** — Với Product serial-managed, baseline reserve theo quantity; Serial cụ thể được chọn và validate khi Sales Issue được post.

**Phụ thuộc:** REM-INV-001/002, REM-SAL-001.
**Quyết định cần soát:** `P2-DEC-018..022`. Nguồn sự thật là posted movement; không post COMBO/PARENT; cân bằng stock và reservation atomic.

#### Nhóm SAL — 8 yêu cầu nguồn chưa triển khai

##### REM-SAL-001 — Sales Order

**Nguồn:** `UR-SAL-001` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3–W4.

**Kết quả người dùng cần:** Direct/Internal Sales giải giá P1/P2 và snapshot; marketplace lưu external price; lưu shipping snapshot độc lập với Contact.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

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

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-SAL-001-01`** — Direct/Internal Sales sử dụng Price Resolution theo Product + UOM + Company + Effective Time; Sales Order lưu giá cuối cùng, UOM và Price Source `PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP` hoặc `PRODUCT_DEFAULT`.
- **`P2-AT-SAL-001-02`** — Direct/Internal Sales cho phép chọn COMBO như template. Khi chọn COMBO, hệ thống resolve giá root COMBO, flatten về SIMPLE/CHILD, phân bổ selling amount và aggregate leaf theo UR-PRD-006 trước các bước Reservation/Inventory/Serial/Costing/Posting.
- **`P2-AT-SAL-001-03`** — Sales Order phải giữ đủ traceability giữa leaf line và COMBO nguồn khi COMBO được dùng để tạo nhanh line.
- **`P2-AT-SAL-001-04`** — Marketplace Sales từ Shopee/TikTok Shop sử dụng external channel price/discount authoritative và lưu Price Source `EXTERNAL_CHANNEL`.
- **`P2-AT-SAL-001-05`** — Sales Order có Price Source `EXTERNAL_CHANNEL` không bị ERP Price Resolution/Promotion recompute lại giá marketplace.
- **`P2-AT-SAL-001-06`** — Sales Order lưu được nguồn giá, UOM và promotion/discount reference phù hợp với loại order.
- **`P2-AT-SAL-001-07`** — Delivery address sử dụng Province/City Code và Ward/Commune Code khi áp dụng.
- **`P2-AT-SAL-001-08`** — Sales Order tuân theo PBAC theo Company/Resource/State/Action.

**Phụ thuộc:** Customer, P1 Pricing, REM-PRC-001-X/002, REM-INV-006.
**Quyết định cần soát:** `P2-DEC-023..029`. Order snapshot bất biến theo lifecycle; ngoại sàn EXTERNAL_CHANNEL không bị tái định giá.

##### REM-SAL-002 — Shopee Integration

**Nguồn:** `UR-SAL-002` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3–W4.

**Kết quả người dùng cần:** Shopee order sync với Shop→Company/Warehouse; error/retry idempotent, external price authoritative.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Hệ thống nhận và quản lý đơn từ Shopee.

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-SAL-002-01`** — Lưu Shopee Order ID và Shop/Account source.
- **`P2-AT-SAL-002-02`** — Mapping SKU/Product.
- **`P2-AT-SAL-002-03`** — Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
- **`P2-AT-SAL-002-04`** — Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
- **`P2-AT-SAL-002-05`** — Giá/discount từ Shopee là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
- **`P2-AT-SAL-002-06`** — Đồng bộ trạng thái cần thiết.
- **`P2-AT-SAL-002-07`** — Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.

**Phụ thuộc:** REM-SAL-008, REM-SAL-001, provider sandbox.
**Quyết định cần soát:** `P2-DEC-023..029`. Order snapshot bất biến theo lifecycle; ngoại sàn EXTERNAL_CHANNEL không bị tái định giá.

##### REM-SAL-003 — TikTok Shop Integration

**Nguồn:** `UR-SAL-003` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3–W4.

**Kết quả người dùng cần:** TikTok Shop tương tự Shopee nhưng adapter/provider contract độc lập.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Hệ thống nhận và quản lý đơn từ TikTok Shop.

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-SAL-003-01`** — Lưu TikTok Shop Order ID và Shop/Account source.
- **`P2-AT-SAL-003-02`** — Mapping SKU/Product.
- **`P2-AT-SAL-003-03`** — Shop/Account phải resolve được Company và fulfillment Warehouse theo UR-SAL-008 trước khi tạo ERP Sales Order.
- **`P2-AT-SAL-003-04`** — Tạo hoặc liên kết ERP Sales Order theo idempotency rule của UR-SAL-008.
- **`P2-AT-SAL-003-05`** — Giá/discount từ TikTok Shop là external authoritative price và được lưu với Price Source `EXTERNAL_CHANNEL`.
- **`P2-AT-SAL-003-06`** — Đồng bộ trạng thái cần thiết.
- **`P2-AT-SAL-003-07`** — Lỗi mapping/sync phải được ghi nhận để xử lý/retry mà không tạo duplicate Sales Order.

**Phụ thuộc:** REM-SAL-008, REM-SAL-001, provider sandbox.
**Quyết định cần soát:** `P2-DEC-023..029`. Order snapshot bất biến theo lifecycle; ngoại sàn EXTERNAL_CHANNEL không bị tái định giá.

##### REM-SAL-004 — Inventory dùng chung cho đa kênh

**Nguồn:** `UR-SAL-004` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3–W4.

**Kết quả người dùng cần:** Mọi kênh dùng chung một inventory per Company/Warehouse, không tạo tồn riêng theo marketplace.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Shopee và TikTok Shop sử dụng Inventory của ERP làm nguồn tồn vận hành.

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-SAL-004-01`** — Không tạo kho logic riêng cạnh tranh chỉ vì khác sales channel.
- **`P2-AT-SAL-004-02`** — Order phải kiểm tra Available Qty theo Company/Warehouse phù hợp.
- **`P2-AT-SAL-004-03`** — Order được xác nhận phải reserve stock vào Inventory chung theo UR-INV-006.
- **`P2-AT-SAL-004-04`** — Release/consume reservation phải phản ánh vào Inventory chung.
- **`P2-AT-SAL-004-05`** — Sales Issue đã post làm giảm On-hand theo stock posting rule.

**Phụ thuộc:** REM-INV-001/002/006, REM-SAL-001.
**Quyết định cần soát:** `P2-DEC-023..029`. Order snapshot bất biến theo lifecycle; ngoại sàn EXTERNAL_CHANNEL không bị tái định giá.

##### REM-SAL-005 — Shipment và Carrier

**Nguồn:** `UR-SAL-005` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3–W4.

**Kết quả người dùng cần:** Shipment/carrier/tracking/label/fee/COD; request retry không tự tạo duplicate label hoặc xóa lịch sử.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

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

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-SAL-005-01`** — Shipment thuộc cùng Company với Sales Order nguồn.
- **`P2-AT-SAL-005-02`** — Shipment truy được Sales Order và Warehouse fulfillment liên quan.
- **`P2-AT-SAL-005-03`** — Tracking Number và delivery status được lưu khi carrier trả về.
- **`P2-AT-SAL-005-04`** — Hủy vận đơn không tự xóa Shipment hoặc lịch sử carrier interaction.
- **`P2-AT-SAL-005-05`** — Shipping Fee và COD Amount được lưu để phục vụ COD Reconciliation khi áp dụng.
- **`P2-AT-SAL-005-06`** — Carrier integration error phải được ghi nhận để retry/xử lý.

**Phụ thuộc:** REM-SAL-001/004, carrier contract.
**Quyết định cần soát:** `P2-DEC-023..029`. Order snapshot bất biến theo lifecycle; ngoại sàn EXTERNAL_CHANNEL không bị tái định giá.

##### REM-SAL-006 — COD Reconciliation

**Nguồn:** `UR-SAL-006` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3–W4.

**Kết quả người dùng cần:** Đối soát mỗi shipment với provider statement, chênh lệch, payout và phí; chỉ liên kết/tạo Money Receipt qua giao dịch Finance có quyền.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Theo từng Shipment xác định:
- COD phải thu
- COD carrier báo đã thu
- Shipping fee
- Other fee
- Net amount carrier phải chuyển
- Amount thực nhận
- Difference
- Reconciliation status

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-SAL-006-01`** — Có thể lọc các shipment chưa đối soát.
- **`P2-AT-SAL-006-02`** — Chênh lệch phải xác định được amount và trạng thái xử lý.
- **`P2-AT-SAL-006-03`** — Khoản COD đã đối soát có thể tạo/liên kết Money Receipt.

**Phụ thuộc:** REM-SAL-005, REM-FIN-001/005.
**Quyết định cần soát:** `P2-DEC-023..029`. Order snapshot bất biến theo lifecycle; ngoại sàn EXTERNAL_CHANNEL không bị tái định giá.

##### REM-SAL-007 — Sales Return

**Nguồn:** `UR-SAL-007` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3–W4.

**Kết quả người dùng cần:** Trả hàng và hoàn tiền phải phân biệt commercial credit, physical returned stock và posted cash refund.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Hỗ trợ:
- Return Request
- Receive Returned Goods
- Serial validation khi áp dụng
- Inventory update
- Refund/Thu-Chi reference khi áp dụng

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-SAL-007-01`** — Sales Return truy được Sales Order/Shipment nguồn khi có.
- **`P2-AT-SAL-007-02`** — Returned goods chỉ làm tăng On-hand khi inventory return movement được post/confirm theo posting rule.
- **`P2-AT-SAL-007-03`** — Product serial-managed phải validate đúng Serial trả lại.
- **`P2-AT-SAL-007-04`** — Refund hoặc giao dịch Thu/Chi liên quan được reference, không tự tạo double-entry accounting.
- **`P2-AT-SAL-007-05`** — Return đã post không được xóa/sửa trực tiếp làm thay đổi stock history.

**Phụ thuộc:** REM-SAL-001/005, REM-INV-002/005, REM-FIN-005.
**Quyết định cần soát:** `P2-DEC-023..029`. Order snapshot bất biến theo lifecycle; ngoại sàn EXTERNAL_CHANNEL không bị tái định giá.

##### REM-SAL-008 — Marketplace Account Mapping & Order Idempotency

**Nguồn:** `UR-SAL-008` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W3–W4.

**Kết quả người dùng cần:** Unique key (sales_channel, shop_account, external_order_id); mapping effective Company/Warehouse trước create ERP order.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Marketplace Shop/Account là identity nguồn để map đơn hàng ngoài vào đúng Company và fulfillment context của ERP.

Unique business key của marketplace order:

**Sales Channel + Shop/Account + External Order ID**

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-SAL-008-01`** — Mỗi Shop/Account xác định được Sales Channel/Platform.
- **`P2-AT-SAL-008-02`** — Mỗi Shop/Account map đúng một Company tại một thời điểm hiệu lực.
- **`P2-AT-SAL-008-03`** — Shop/Account có fulfillment Warehouse mặc định hoặc mapping Warehouse rõ ràng trước khi tạo Sales Order.
- **`P2-AT-SAL-008-04`** — `Sales Channel + Shop/Account + External Order ID` là unique business key.
- **`P2-AT-SAL-008-05`** — Retry/sync lại cùng external order không được tạo duplicate ERP Sales Order.
- **`P2-AT-SAL-008-06`** — Sync/retry phải idempotent đối với create/link order.
- **`P2-AT-SAL-008-07`** — Mapping Company/Warehouse phải hoàn tất trước khi ERP Sales Order được tạo.
- **`P2-AT-SAL-008-08`** — Lỗi mapping hoặc sync được giữ trạng thái/lý do để reprocess.

**Phụ thuộc:** REM-SAL-001, provider adapter.
**Quyết định cần soát:** `P2-DEC-023..029`. Order snapshot bất biến theo lifecycle; ngoại sàn EXTERNAL_CHANNEL không bị tái định giá.

#### Nhóm FIN — 5 yêu cầu nguồn chưa triển khai

##### REM-FIN-001 — Ghi nhận Thu/Chi

**Nguồn:** `UR-FIN-001` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2 (core)/W4 (integrations).

**Kết quả người dùng cần:** Thu/Chi phải thuộc cùng Company với account; post/correction nguồn giữ bất biến số tiền; không double post.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

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

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-FIN-001-01`** — Mỗi Money Receipt/Money Expense thuộc đúng một Company.
- **`P2-AT-FIN-001-02`** — Amount phải lớn hơn 0.
- **`P2-AT-FIN-001-03`** — Account sử dụng phải thuộc cùng Company với transaction.
- **`P2-AT-FIN-001-04`** — Chỉ transaction đã `POSTED` mới tham gia operational balance.
- **`P2-AT-FIN-001-05`** — Transaction đã post không được xóa/sửa trực tiếp amount/account làm thay đổi history; correction phải qua reversal/adjustment có traceability.

**Phụ thuộc:** Company, PBAC, Numbering, Workflow, REM-FIN-003.
**Quyết định cần soát:** `P2-DEC-030..032`. Thu/Chi vận hành VND; không tạo sổ cái GL/double-entry và không tự post từ COD.

##### REM-FIN-002 — Danh mục Thu/Chi

**Nguồn:** `UR-FIN-002` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2 (core)/W4 (integrations).

**Kết quả người dùng cần:** Danh mục Thu và Chi có status; inactive không được chọn mới nhưng dữ liệu lịch sử vẫn hiển thị.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Cho phép cấu hình category như:
- Thu bán hàng
- Thu COD
- Thu khác
- Thanh toán Supplier
- Chi vận chuyển
- Chi lương
- Chi văn phòng
- Chi khác

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-FIN-002-01`** — Category xác định được loại `RECEIPT` hoặc `EXPENSE`.
- **`P2-AT-FIN-002-02`** — Category inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.
- **`P2-AT-FIN-002-03`** — Có thể lọc/tổng hợp Thu/Chi theo category.

**Phụ thuộc:** REM-FIN-001.
**Quyết định cần soát:** `P2-DEC-030..032`. Thu/Chi vận hành VND; không tạo sổ cái GL/double-entry và không tự post từ COD.

##### REM-FIN-003 — Cash và Bank Account

**Nguồn:** `UR-FIN-003` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2 (core)/W4 (integrations).

**Kết quả người dùng cần:** Quỹ tiền mặt/tài khoản ngân hàng operational thuộc Company; opening date/balance; inactive không dùng cho transaction mới.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Quản lý danh sách:
- Cash Fund
- Bank Account

Mỗi account có tối thiểu:
- Company
- Opening Balance
- Opening Date
- Status

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-FIN-003-01`** — Mỗi account thuộc một Company.
- **`P2-AT-FIN-003-02`** — Opening Balance và Opening Date được xác định trước khi tính operational balance từ thời điểm đó.
- **`P2-AT-FIN-003-03`** — User chỉ thao tác account trong PBAC scope.
- **`P2-AT-FIN-003-04`** — Account inactive không dùng cho giao dịch mới nhưng lịch sử vẫn tra cứu được.

**Phụ thuộc:** Organization, PBAC.
**Quyết định cần soát:** `P2-DEC-030..032`. Thu/Chi vận hành VND; không tạo sổ cái GL/double-entry và không tự post từ COD.

##### REM-FIN-004 — Tổng hợp Thu/Chi

**Nguồn:** `UR-FIN-004` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2 (core)/W4 (integrations).

**Kết quả người dùng cần:** Balance as-of = opening + posted receipt − posted expense; báo cáo PBAC-scope không suy ra GL.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

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

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-FIN-004-01`** — Chỉ posted Money Receipt/Money Expense được tính vào operational balance.
- **`P2-AT-FIN-004-02`** — Opening/Closing Balance không suy diễn từ General Ledger.
- **`P2-AT-FIN-004-03`** — Có thể tính balance theo từng Cash/Bank Account và tổng hợp các account trong Company theo filter.
- **`P2-AT-FIN-004-04`** — Report theo nhiều Company chỉ tổng hợp Company User có quyền xem.
- **`P2-AT-FIN-004-05`** — Operational Balance không được mô tả như bank reconciliation hoặc statutory accounting balance.

**Phụ thuộc:** REM-FIN-001/002/003.
**Quyết định cần soát:** `P2-DEC-030..032`. Thu/Chi vận hành VND; không tạo sổ cái GL/double-entry và không tự post từ COD.

##### REM-FIN-005 — Liên kết nguồn giao dịch

**Nguồn:** `UR-FIN-005` · **Phase:** P2 · **Ưu tiên:** MUST · **Trạng thái mã nguồn:** `SOURCE_ABSENT` · **Wave dự kiến:** W2 (core)/W4 (integrations).

**Kết quả người dùng cần:** Thu/Chi reference nguồn, có thể allocate receipt/refund khi hợp đồng được duyệt; không phát sinh bút toán kế toán kép.

**Yêu cầu gốc trong phạm vi chưa triển khai:**

Thu/Chi có thể liên kết với:
- Sales Order
- Shipment/COD
- Purchase Order
- Supplier
- Customer
- Payroll

Liên kết phục vụ traceability/tổng hợp, không tạo double-entry accounting.

**Acceptance criteria nguồn (giữ nguyên v1.0.15):**

- **`P2-AT-FIN-005-01`** — Có thể truy từ Money Receipt/Money Expense về Source Reference khi nguồn còn tồn tại và User có quyền.
- **`P2-AT-FIN-005-02`** — Một source reference không tự tạo double-entry journal.
- **`P2-AT-FIN-005-03`** — COD đã reconciliation có thể tạo/liên kết Money Receipt mà vẫn giữ Shipment/COD reference.
- **`P2-AT-FIN-005-04`** — Payroll payment có thể liên kết Money Expense theo đúng Company.

**Phụ thuộc:** REM-FIN-001; các nguồn SAL/PUR/HRP khi có.
**Quyết định cần soát:** `P2-DEC-030..032`. Thu/Chi vận hành VND; không tạo sổ cái GL/double-entry và không tự post từ COD.

## 4. Phần còn thiếu P3 — HCM/Payroll và BOM

P3 là phase riêng theo tài liệu gốc, **không được gộp vào P2 release scope**. `People/Employee`, Product, Workflow đã có từ P1 chỉ là nền, không thay thế các capability HRP/BOM bên dưới. Bản v2.4.0 chưa có domain module HRP/Payroll/BOM tương ứng. Tiêu chí `P3-SRC-AC-*` ở đây là **mã theo dõi do bản delta tạo** cho các AC nguồn P3 (UR gốc chưa cấp test ID ở P2 catalog); không giả mạo mã approved.

### Nhóm HRP — 9 UR / 37 AC nguồn

#### REM-HRP-001 — Hồ sơ HR, hợp đồng và Work Calendar

**Nguồn:** `UR-HRP-001` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Hợp đồng/effective HR info và Work Calendar là source để tính công theo ngày.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Quản lý dữ liệu HR mở rộng của Employee:
- Hợp đồng
- Thuộc tính nhân sự
- Thuộc tính tiền lương
- Effective period

Employee Work Calendar gồm:
- Ca/lịch làm việc chuẩn
- Số giờ chuẩn/ngày
- Quy tắc quy đổi giờ sang ngày công
- Ngày nghỉ tuần
- Ngày nghỉ theo HR policy

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-001-01`** — Lịch sử hợp đồng/điều chuyển/trạng thái được bảo toàn.
- **`P3-SRC-AC-HRP-001-02`** — Mỗi ngày xác định được working day, weekly off hoặc HR holiday.
- **`P3-SRC-AC-HRP-001-03`** — Standard working hours/days của kỳ resolve được từ calendar có hiệu lực.

**Phụ thuộc:** P1 Employee, Assignment, Organization.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

#### REM-HRP-002 — Attendance

**Nguồn:** `UR-HRP-002` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Nhật ký chấm công có giờ/mô tả và audit, không tự biến giờ vượt chuẩn thành OT.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Nhân viên có thể ghi nhận:
- Working date
- Working hours
- Work description

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-002-01`** — Giữ Created/Updated By/At.
- **`P3-SRC-AC-HRP-002-02`** — Điều chỉnh sau thời hạn hoặc sau khi kỳ khóa phải được kiểm soát.
- **`P3-SRC-AC-HRP-002-03`** — Giờ nhân viên tự ghi không tự động trở thành overtime.

**Phụ thuộc:** REM-HRP-001.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

#### REM-HRP-003 — Leave

**Nguồn:** `UR-HRP-003` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Leave theo ngày/phần ngày và policy số phép; chỉ approved phản ánh vào timesheet.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Nhân viên có thể gửi yêu cầu nghỉ theo ngày hoặc phần ngày.

Thông tin:
- Leave type
- Period
- Reason
- Status
- Approver

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-003-01`** — Approved Leave tự động phản ánh vào Timesheet.
- **`P3-SRC-AC-HRP-003-02`** — Rejected/Cancelled leave không tính là nghỉ hợp lệ.
- **`P3-SRC-AC-HRP-003-03`** — Có thể quản lý leave balance, paid/unpaid leave và carry-forward theo policy.
- **`P3-SRC-AC-HRP-003-04`** — Điều chỉnh sau duyệt giữ lịch sử.

**Phụ thuộc:** REM-HRP-001, REM-HRP-005.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

#### REM-HRP-004 — Overtime

**Nguồn:** `UR-HRP-004` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Chỉ approved overtime dùng payroll/timesheet; hệ số/giới hạn và approver phải truy ra.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Quản lý Overtime theo:
- Type
- Hours
- Rate/Factor
- Limit
- Approval

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-004-01`** — Chỉ approved overtime được tính vào Timesheet/Payroll.
- **`P3-SRC-AC-HRP-004-02`** — Giờ Attendance vượt chuẩn không tự động trở thành approved overtime.
- **`P3-SRC-AC-HRP-004-03`** — OT truy được ngày, loại, hệ số và approver.

**Phụ thuộc:** REM-HRP-001/002/005.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

#### REM-HRP-005 — Monthly Timesheet

**Nguồn:** `UR-HRP-005` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Timesheet theo Employee + Period + Company; primary Assignment theo từng ngày, split Company trong kỳ, lock trước Payroll.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Tổng hợp bảng công tháng từ:
- Work Calendar
- Attendance
- Approved Leave
- Approved Overtime
- Approved Adjustment
- Primary Assignment có hiệu lực tại từng ngày

Timesheet ownership baseline:

**Employee + Payroll Period + Company**

Bảng công thể hiện tối thiểu:
- Days present
- Working hours
- Converted working days
- Standard working days
- Paid leave
- Unpaid leave
- Approved overtime
- Calendar holidays/off days
- Missing attendance days

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-005-01`** — Mỗi ngày công được gán Company bằng cách resolve `COMPANY` từ Organization Context của Primary Assignment có hiệu lực tại ngày đó.
- **`P3-SRC-AC-HRP-005-02`** — Nếu Company resolve từ Primary Assignment thay đổi trong cùng kỳ thì hệ thống tạo/tổng hợp Timesheet tách theo Company; không lấy Company cuối tháng cho toàn kỳ.
- **`P3-SRC-AC-HRP-005-03`** — Một Employee có thể có nhiều Company Timesheet trong cùng Payroll Period khi có transfer hợp lệ.
- **`P3-SRC-AC-HRP-005-04`** — Ngày nghỉ hợp lệ không bị xem là missing attendance.
- **`P3-SRC-AC-HRP-005-05`** — Days present và converted working days là hai chỉ tiêu riêng.
- **`P3-SRC-AC-HRP-005-06`** — Chỉ Timesheet đã khóa được dùng làm nguồn chính thức cho Payroll.

**Phụ thuộc:** REM-HRP-001..004, P1 Assignment.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

#### REM-HRP-006 — Compensation

**Nguồn:** `UR-HRP-006` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Compensation theo Company/effective period, history immutable; formula nội bộ không là statutory payroll.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Quản lý:
- Salary
- Allowance
- Bonus
- Penalty
- Deduction
- Other earning/deduction

Mỗi thành phần có:
- Company context khi áp dụng
- Effective period
- Calculation method: manual amount hoặc internal formula khi được cấu hình
- Lịch sử thay đổi

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-006-01`** — Tại một thời điểm xác định được Compensation component có hiệu lực.
- **`P3-SRC-AC-HRP-006-02`** — Thay đổi component không ghi đè làm mất lịch sử trước.
- **`P3-SRC-AC-HRP-006-03`** — Payroll chỉ sử dụng component có hiệu lực và thuộc Company context phù hợp.
- **`P3-SRC-AC-HRP-006-04`** — Internal formula baseline không được hiểu là statutory payroll compliance engine.

**Phụ thuộc:** P1 Employee, REM-HRP-007.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

#### REM-HRP-007 — Payroll Lifecycle

**Nguồn:** `UR-HRP-007` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Calculate/Review/Approve/Lock, chỉ dùng locked Timesheet; Payroll chia Company.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Payroll sử dụng:
- Locked Monthly Timesheet theo Company
- Effective Compensation
- Approved Adjustments

Lifecycle:
- Calculate
- Review
- Approve
- Lock

Kết quả tối thiểu:
- Company
- Gross
- Allowance/Bonus
- Deduction
- Net Pay

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-007-01`** — Payroll không tính trực tiếp từ raw attendance khi Timesheet chưa khóa.
- **`P3-SRC-AC-HRP-007-02`** — Payroll thuộc đúng một Company.
- **`P3-SRC-AC-HRP-007-03`** — Nếu Employee có Timesheet ở nhiều Company trong cùng kỳ thì Payroll được split theo Company tương ứng.
- **`P3-SRC-AC-HRP-007-04`** — Payroll của Company A không bao gồm ngày công thuộc Company B.
- **`P3-SRC-AC-HRP-007-05`** — Chỉ locked payroll period được xem là số liệu lương chính thức.
- **`P3-SRC-AC-HRP-007-06`** — Có thể trace từ Payroll về Timesheet và căn cứ liên quan.

**Phụ thuộc:** REM-HRP-005/006/008.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

#### REM-HRP-008 — Payroll Adjustment

**Nguồn:** `UR-HRP-008` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Truy vết retro/back pay/offcycle/termination/transfer, không sửa Timesheet/Payroll đã khóa.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Hỗ trợ:
- Retroactive adjustment
- Back pay
- Termination
- Company/Unit transfer
- Off-cycle payroll

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-008-01`** — Adjustment liên kết kỳ gốc và Company liên quan.
- **`P3-SRC-AC-HRP-008-02`** — Không sửa ngược raw attendance hoặc locked Timesheet.
- **`P3-SRC-AC-HRP-008-03`** — Adjustment phải qua PBAC/approval phù hợp.
- **`P3-SRC-AC-HRP-008-04`** — Adjustment liên quan nhiều Company phải được tách thành các phần thuộc đúng Company; không tạo một transaction sở hữu đồng thời nhiều Company.

**Phụ thuộc:** REM-HRP-007.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

#### REM-HRP-009 — Labor Cost Allocation

**Nguồn:** `UR-HRP-009` · **Phase:** P3 · **Ưu tiên:** SHOULD · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Tùy chọn SHOULD: phân bổ labor cost theo Department và Company; không là prerequisite Payroll.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Cho phép phân bổ approved labor cost theo:
- Department

Capability này là tùy chọn và không phải prerequisite để Payroll hoạt động.

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-HRP-009-01`** — Chỉ approved/locked labor cost được dùng làm nguồn phân bổ.
- **`P3-SRC-AC-HRP-009-02`** — Allocation truy được Department và Company liên quan.
- **`P3-SRC-AC-HRP-009-03`** — Tổng amount phân bổ không vượt source labor cost nếu không có adjustment hợp lệ.
- **`P3-SRC-AC-HRP-009-04`** — Payroll vẫn hoạt động khi Labor Cost Allocation không được triển khai.

**Phụ thuộc:** REM-HRP-007.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. Kỳ công/lương chia đúng Company theo Assignment effective từng ngày; tách Payroll khỏi P2.

### Nhóm BOM — 4 UR / 16 AC nguồn

#### REM-BOM-001 — BOM

**Nguồn:** `UR-BOM-001` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** BOM component/quantity/UOM/waste; BOM không được nhập nhằng với COMBO template.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Một Product có thể có BOM.

BOM gồm:
- Finished Product
- Material/Component
- Required Quantity
- UOM
- Waste Rate khi áp dụng

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-BOM-001-01`** — Finished Product và Material/Component phải là Product hợp lệ.
- **`P3-SRC-AC-BOM-001-02`** — Required Quantity phải lớn hơn 0.
- **`P3-SRC-AC-BOM-001-03`** — UOM phải hợp lệ cho Product/Component tương ứng.
- **`P3-SRC-AC-BOM-001-04`** — Waste Rate không được âm.
- **`P3-SRC-AC-BOM-001-05`** — BOM giữ được danh sách component phục vụ Material Requirement và Estimated Finished Product Cost.

**Phụ thuộc:** P1 Product/UOM, có thể dùng sau PUR.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. BOM P3 khác Product COMBO P1; tính giá thành dự kiến không phải kế toán sản xuất.

#### REM-BOM-002 — BOM Version

**Nguồn:** `UR-BOM-002` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Version và effective date, resolve BOM mặc định deterministically, lưu version lịch sử.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Hỗ trợ nhiều phiên bản BOM.

Thông tin:
- Version
- Effective Date
- Status

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-BOM-002-01`** — Tại một thời điểm xác định được BOM mặc định.
- **`P3-SRC-AC-BOM-002-02`** — Version cũ vẫn tra cứu được.

**Phụ thuộc:** REM-BOM-001.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. BOM P3 khác Product COMBO P1; tính giá thành dự kiến không phải kế toán sản xuất.

#### REM-BOM-003 — Material Requirement

**Nguồn:** `UR-BOM-003` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Calculate material demand theo quantity thành phẩm, waste và version snapshot.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Hệ thống tính nhu cầu vật tư theo BOM và số lượng thành phẩm cần tính.

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-BOM-003-01`** — Kết quả chỉ ra quantity từng component.
- **`P3-SRC-AC-BOM-003-02`** — Waste Rate được áp dụng khi có.
- **`P3-SRC-AC-BOM-003-03`** — Có thể truy lại BOM version đã dùng.

**Phụ thuộc:** REM-BOM-001/002.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. BOM P3 khác Product COMBO P1; tính giá thành dự kiến không phải kế toán sản xuất.

#### REM-BOM-004 — Estimated Finished Product Cost

**Nguồn:** `UR-BOM-004` · **Phase:** P3 · **Ưu tiên:** MUST · **Source:** `SOURCE_ABSENT` · **Wave dự kiến:** W5 / P3.

**Kết quả người dùng cần:** Cost source LAST_PURCHASE_PRICE hoặc MANUAL_STANDARD_COST theo Company; snapshot source,value,time,version.

**Yêu cầu gốc thuộc chức năng chưa triển khai:**

Tính giá thành dự kiến theo Company context từ:
- BOM
- Material Quantity
- Material Cost Source

Material Cost Source baseline gồm:
- `LAST_PURCHASE_PRICE`
- `MANUAL_STANDARD_COST`

Mỗi lần costing phải snapshot tối thiểu:
- Cost Source
- Cost Value
- Source Timestamp/Effective Time
- Company
- BOM Version

**Acceptance criteria nguồn:**

- **`P3-SRC-AC-BOM-004-01`** — Kết quả thể hiện cost từng component và tổng estimated product cost.
- **`P3-SRC-AC-BOM-004-02`** — Với `LAST_PURCHASE_PRICE`, hệ thống sử dụng giá mua gần nhất có thể xác định trong Company context theo transaction nguồn hợp lệ.
- **`P3-SRC-AC-BOM-004-03`** — Với `MANUAL_STANDARD_COST`, hệ thống sử dụng standard cost đã cấu hình cho Product/Material.
- **`P3-SRC-AC-BOM-004-04`** — Mỗi component lưu được source, value và timestamp/effective time đã dùng.
- **`P3-SRC-AC-BOM-004-05`** — Có thể truy lại BOM version và Company context của lần costing.
- **`P3-SRC-AC-BOM-004-06`** — Đây là management estimate, không phải production accounting.

**Phụ thuộc:** REM-BOM-001/002/003, PUR hoặc Product manual cost.
**G0 cần chốt:** P3-DEC cần lập ở G0-P3. BOM P3 khác Product COMBO P1; tính giá thành dự kiến không phải kế toán sản xuất.

## 5. Các ràng buộc bắt buộc khi phát triển chức năng còn thiếu (không phải tính năng cần làm lại)

1. **Ownership:** một Group, nhiều Company; mọi transaction PUR/INV/SAL/FIN/Payroll phải thuộc đúng một Company. Warehouse dưới Company authority; không dùng client-supplied Company để đổi persisted transaction owner.
2. **Authorization:** PBAC/Base Permission + Policy/State + assignment inheritance trên server; `view`,`edit`,`approve`,`post`,`export` là action độc lập. SQL-first scope cho COUNT/ROWS/detail/report/lookup/export, không filter sau pagination và không rò `meta.total`.
3. **Product, UOM, COMBO:** transactionable leaf chỉ `SIMPLE`/`CHILD`; `PARENT` không selectable; `COMBO` chỉ là template, bắt buộc flatten và giữ trace. Giá root COMBO bán được phân bổ theo công thức P1; **không** tự áp công thức giá bán vào purchase cost.
4. **Posting và lịch sử:** Draft/Pending không tác động stock/cash; once POSTED không xóa/sửa destructive; reversal/adjustment có nguồn, audit và idempotency; atomic transaction và locking khi nhiều user/worker đồng thời.
5. **Inventory Reservation:** giữ stock theo order leaf; Available = On-hand − Reserved; reserve mới không được tạo Available âm; Sales Issue consume reservation và stock trong cùng transaction.
6. **Commerce/Finance:** external Shopee/TikTok price authoritative; unique business key có Shop Account; COD status không tự sinh Money Receipt POSTED; payroll money expense khi có vẫn thuộc Company.
7. **Import:** P1 framework đang bật riêng `PRODUCT`. Target P2/P3 mới **chưa được định danh/duyệt**; chỉ mở từng resource khi có yêu cầu, template, import action và row-level PBAC. Không tự sinh CRUD import đại trà.
8. **Presentation:** vi/en; frontend có module-scope list/table/pagination chuẩn, trạng thái Not Available khi thiếu dữ liệu nguồn; DECIMAL tiền/quantity dạng canonical string, VND-only; display timezone không làm authority business date.
9. **Security vận hành:** ký/verify webhook, bảo vệ credential/token, audit bank reveal, private storage/malware policy, không bypass System Admin. Các policy chưa duyệt phải BLOCK không tự chấp thuận.

## 6. Thứ tự triển khai đề xuất theo phụ thuộc (không phải cam kết lịch)

| Wave | Phần việc CHƯA có | Điều kiện vào wave | Mốc nghiệm thu |
|---|---|---|---|
| **W0 — đóng thiết kế/rủi ro** | Khóa kiến trúc INV/stock, giao dịch FIN, external worker, Supplier banking security, nguồn CUS/SUP readers | PO/Architecture/DBA/Security phê duyệt ADR | G0/G1 của mỗi module, rõ owner+state+posting+test |
| **W1 — DOC (song song)** | Attachment/version/search + owner permission | DOC storage/ACL/retention quyết định | Upload/download/previous versions, negative owner auth |
| **W2 — xương sống vận hành** | INV ledger/on-hand/serial; PUR PR/PO/GR/Return; FIN account/category/Money core | Supplier usable, Product/UOM, Company/Warehouse; rule posting | Atomic GR/Return và posted-only money, stock negative/race tests |
| **W3 — bán hàng nội bộ** | P2 Pricing/Promotion; SAL Direct/Internal; Inventory reservation/issue; Sales Return | CUS core, INV posting và PRC/Promo ADR | Price snapshot/COMBO allocation, reservation consume, return trace |
| **W4 — kênh ngoài và giao vận** | Shop mapping/idempotency; Shopee, TikTok; carrier/shipment; COD & FIN links; CUS/SUP transaction history và KPI | Provider fixtures, Shop→Company mapping, SAL/INV/FIN sources | Retry/out-of-order, duplicate prevention, COD→money reconciliation, source-visible history |
| **W5 — P3** | HR Contract/Calendar → Attendance/Leave/OT → Timesheet/Payroll; BOM/Material/Estimated Cost | Phê duyệt scope P3; Company split; PUR cost source khi dùng | Locked payroll per Company; BOM versioned costing snapshot |

**Lưu ý trình tự không tuyến tính cứng:** INV foundation phải được thiết kế trước GR nhưng code GR và movement posting cần tích hợp cùng gate; FIN core có thể chạy song song PUR/INV, còn COD integration phải chờ W4. DOC có thể chạy song song nhưng không bật owner adapters cho module chưa có nguồn.

## 7. Quyết định cần hoàn tất trước khi tạo tài liệu triển khai

Các ID dưới đây tham chiếu bản P2 `14_P2_GAPS_AND_OPEN_DECISIONS.md` (DRAFT trên v1.9.0). **Không dùng mọi OPEN từ bản đó nguyên trạng:** CUS G1 đã chốt nhóm khách hàng và Contact; SUP source đã triển khai Contacts. Những phần đã có quyết định sau phải được đồng bộ, không tái mở mặc định.

| Nhóm | Cần chốt (tóm tắt không tự duyệt) | Nguồn / gate |
|---|---|---|
| Common | Service/workers PBAC; P2 ResourceAction; error/idempotency contract; DECIMAL rounding/tax | `P2-DEC-001..004` / G0 |
| DOC | Owner ACL, storage/MIME/scan/checksum, version retention, unlink and delete | `P2-DEC-005..006` / G1 |
| SUP | Crypto key custody, reveal logging, role grants, masking, migration review | SUP Security/DBA gate; phần còn OPEN của `P2-DEC-011,032` |
| PRC | Customer/Group/Channel specificity, promotion stacking/discount/coupon/gift and rounding | `P2-DEC-012..014` / G3; CUS Group codes đã khóa |
| PUR | Approval lifecycle, Combo Purchase leaf cost, partial/over receipt, return corrections | `P2-DEC-015..017` / G2 |
| INV | Opening stock, negative balance, serial state, count snapshot, reservation concurrency | `P2-DEC-018..022` / G2–G3 |
| SAL | Order transitions, partial fulfillment, shop effective mapping, real provider callbacks, carrier labels | `P2-DEC-023..028` / G3–G4 |
| COD / FIN | Payout allocation, account opening, reversal/refund, sensitive bank data, typed money application | `P2-DEC-029..032` + approved CUS XMOD / G4 |
| Cross-module | Chọn target import rõ ràng, audit retention, privacy, restore/capacity và vi/en | `P2-DEC-033..035` / theo gate |
| HRP / BOM P3 | Leave policy/standard hours; payroll splitting and correction; BOM cost source/effective version | **G0-P3: lập ADR mới**, không tự coi là signed P2 |

**Chống xung đột tài liệu:** `P2-DEC-007` về bảng Customer Group kiểu draft đã được thay thế về mặt CUS bởi quyết định `CUS-T-GROUP-01`: dùng `CUSTOMER_GROUP()` và `customer_group_code`, **không** tạo customer group table. `P2-DEC-009` về Contact `CUSTOMER` đã có code; phần Contact `SUPPLIER` của `P2-DEC-011` đã có code. Các review bảo mật, DB, và runtime còn lại vẫn là gate thật.

## 8. Kế hoạch tạo bộ tài liệu triển khai đầy đủ ở vòng sau

Tài liệu hiện tại chỉ khóa **backlog yêu cầu chưa triển khai**; không sinh bảng, API hay migration ở đây. Khi Product Owner review, triển khai downstream từng module theo bộ mẫu sau:

| Thứ tự | Artifact đề xuất | Chức năng | Gate |
|---|---|---|---|
| 1 | `01_<MODULE>_SCOPE_AND_REQUIREMENTS.md` | Lọc UR/AC và các dependency đúng v2.4.0 | G0 |
| 2 | `02_<MODULE>_FUNCTIONAL_SPEC.md` | User flows, validation, lifecycle, UAT examples | G0 |
| 3 | `03_<MODULE>_DATA_AND_TRANSACTION_DESIGN.md` | Company source-of-truth, schema, mutex/CAS, migrations, reversal | G1 |
| 4 | `04_<MODULE>_PBAC_AND_SECURITY.md` | Resource/action, State/Org, SQL COUNT/ROWS/detail, secret access | G1 |
| 5 | `05_<MODULE>_API_OPENAPI.md` + YAML | Request/response/error/idempotency, generate client | G2 |
| 6 | `06_<MODULE>_UI_UX.md` | List/Table First, detail, form, permission hints, vi/en | G2 |
| 7 | `07_<MODULE>_IMPLEMENTATION_AND_MIGRATION.md` | Kế hoạch coding, apply/reverse, preflight/backup | G3–G4 |
| 8 | `08_<MODULE>_TEST_AND_UAT_MATRIX.csv` | UR → FR → tests, actor A/B, DB/HTTP/concurrency/browser | G3–G6 |
| 9 | `09_<MODULE>_OPERATIONS_AND_SIGNOFF.md` | Runbook monitoring, retry, rollback, G7 sign-off | G7 |

Các tài liệu P2 `docs/{03..16}` và module specs v1.0.0 hiện có chỉ nên được dùng làm **DRAFT reference** và phải review lại với source v2.4.0, CUS approved decisions và SUP existing code trước khi nâng thành contract. Mỗi module phải đạt `MOD-GATE-01` từ G0 tới G7, ghi `PASS/FAIL/NOT_RUN` cùng artifact & log; không gọi `CLOSED` chỉ dựa vào source/pure test.

## 9. Traceability và quy tắc nghiệm thu

- **File nguồn:** `docs/DinkERP_P2/reference/00_User_Requirements_v1.0.15.md` — SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`.
- **Archive source:** `erp_v2.4.0.zip` — SHA-256 `ded6726e373a6d622cc9ca84dae82d51cc37bfd0b5d9fd4394735bd77ec2fb56`.
- **CSV đi kèm:** `DinkERP_Remaining_Requirements_Traceability_v2.4.0.csv` có từng AC còn phụ thuộc, mã UR/feature tương ứng, nguồn, wave, trạng thái code/evidence; `P2-AT-*` giữ nguyên catalog approved UR/P2 draft, P3-SRC-AC là mã dẫn xuất phi chuẩn.
- **Nghiệm thu:** mỗi source AC phải có test riêng và bằng chứng trên clone + browser khi áp dụng; code tồn tại không đồng nghĩa AC PASS. Nếu PO hoãn MUST thì cần waiver hoặc change control, không xóa dòng.
- **Nguồn kiểm tra source:** `backend/app/Modules/`, `backend/app/Config/Routes/ApiV1/`, `backend/app/Database/Migrations/` (tối đa `000028`), `frontend/src/features/`, `contracts/openapi/openapi.yaml`.
- **Nguồn quyết định:** `docs/DinkERP_P2/` (draft P2 và CUS packages), `docs/p2-sup/` (SUP triển khai/bảo mật), `docs/development-standards/03_MODULE_DELIVERY_GATES.md`.
- **Giới hạn xác thực:** tài liệu được lập qua source inspection, không kết nối DB DEV/live, không chạy MariaDB/HTTP/Playwright cho module tương lai; các status hoàn thành trước đó không bị thay đổi bởi bản DRAFT này.

## 10. Product Owner review checklist

- [ ] Xác nhận W1–W4 bao phủ đúng phạm vi Mini ERP vận hành P2; có/không thay đổi ưu tiên theo nhu cầu Dinktech.
- [ ] Xác nhận W5 (P3 HRP/BOM) vẫn là backlog giai đoạn sau, chưa nhập P2.
- [ ] Phê duyệt CUS XMOD reader scope theo addendum và các mốc nghiệp vụ SAL/FIN còn chờ chốt.
- [ ] Chốt Supplier bank security gating trước khi được đưa vào UI/API.
- [ ] Chỉ định owner cho các ADR blocking còn mở; không coi đề xuất trong draft là approval.
- [ ] Sau khi rà soát, ban hành version `APPROVED` theo change-control, cập nhật traceability và bắt đầu module G0.
