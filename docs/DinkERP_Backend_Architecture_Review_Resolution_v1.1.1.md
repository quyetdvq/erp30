# DinkERP — Architecture Review Resolution (v1.1 → v1.1.1)

**Record ID:** DINKERP-BE-ARCH-REV-002  
**Ngày:** 22/10/2026  
**Đầu vào:** `Markdown đã dán (1).md` (bản rà soát v1.1), `DinkERP_Backend_System_Architecture_Standard_v1.1.md`, `DinkERP_Module_Design_Record_Template_v1.1.md`, mã nguồn `erp_v1.2.2.zip`.  
**Đầu ra:** `DinkERP_Backend_System_Architecture_Standard_v1.1.1.md`, `DinkERP_Module_Design_Record_Template_v1.1.1.md`.  
**Trạng thái:** **DOCUMENT CHANGES COMPLETED — PROPOSED BASELINE**; chưa phê duyệt chính thức, chưa chứng nhận implementation conformance.

## 1. Quyết định tổng thể

Giữ nguyên **Pragmatic Modular Monolith**; một backend CodeIgniter 4 + MariaDB, tổ chức theo ownership, mặc định Controller → Module Service → Repository khi có HTTP/persistence. Không thêm tầng mới, framework DI, generic UnitOfWork, EventBus hay DDD/CQRS bắt buộc. Hai tài liệu v1.1.1 sửa các lỗ hổng diễn đạt và quy trình kiểm chứng, **không thay đổi mã nguồn, OpenAPI, DBD hoặc business contract**.

## 2. Bảng xử lý từng phát hiện

| ID | Ưu tiên | Nhận xét nguồn | Thay đổi đã thực hiện | Bằng chứng/tình trạng còn lại |
|---|---|---|---|---|
| R-01 | P0 | Quy tắc ẩn bản ghi `404` xung đột Product legacy `403` | Standard §6.3/§7.3/§11.3/§19 và MDR §4/§6: **approved operation API contract có ưu tiên**, ghi current vs approved status + machine code, refactor không âm thầm đổi response | Source đọc được Product denial → `PRODUCT_AUTHORIZATION_DENIED` → `403`; **contract approval phải đối chiếu** |
| R-02 | P0 | COMBO + AUTO có thể lồng transaction | Standard §9.6 `KIR-001`, §20.2 và MDR §5: known risk, owner-role, input matrix, ADR/POC/concurrency gate | Chưa có runtime MariaDB/Shield; **không kết luận lỗi runtime hoặc áp dụng patch** |
| R-03 | P1 | Authorization-only read port có thể tái nhập PBAC | Standard §3.1/§5.3: wiring minh họa, giới hạn trường/consumer, cấm gọi ngược decision; negative runtime recursion test | Test chưa triển khai |
| R-04 | P1 | Guard chưa bảo đảm module boundary | Standard §15.2: `ARCH-01..07` registry, DAG, write ownership ledger, exceptions, bootstrap tests, positive/negative fixtures, CI evidence | **Chưa xây scanner mới**; static SQL scan không chứng minh mọi dynamic SQL |
| R-05 | P2 | Lifecycle status thiếu `IMPLEMENTING` | Standard §0.4 nêu canonical status; bỏ biểu mẫu Phụ lục A trùng, template v1.1.1 giữ `IMPLEMENTING` | Đã thống nhất văn bản |
| R-06 | P2 | `READY` vs `CONTRACT_READY` | MDR §4 và §1 chuẩn hóa `CONTRACT_READY/CONTRACT_PENDING/N/A` theo operation | Đã thống nhất văn bản |
| R-07 | P2 | Test gate không có `N/A` | Standard §0.4/§15.2/§19 và MDR §7: có `N/A` + lý do, phân biệt `NOT_RUN`, `BLOCKED_ENV` | Đã thống nhất văn bản |
| R-08 | P2 | New capability chưa chỉ nơi ghi MDR | Standard §3.2/Phụ lục A, MDR hướng dẫn: cập nhật `MODULE.md` của root hiện hữu | Đã thống nhất văn bản |
| R-09 | P2 | Mức M không có `MODULE.md` | Standard §4.2: root MDR + capability subfolder | Đã chỉnh sơ đồ |
| R-10 | P2 | Phụ lục C ép mọi chức năng có Controller và Repository | Standard Phụ lục C: quyết định riêng HTTP và persistence, không tạo lớp rỗng | Đã chỉnh sơ đồ |
| R-11 | P2 | Phụ lục A/Template tạo hai nguồn MDR | Standard Phụ lục A chỉ nêu **template MDR v1.1.1 là nguồn chuẩn duy nhất**; liệt kê fields tối thiểu | Đã loại bản biểu mẫu thứ hai |
| R-12 | P2 | Mọi cross-module JOIN bị hiểu cần ADR | Standard §2.3/§18.1 + MDR §3: approval projection trong MDR của hai data owners, **ADR khi mở/đổi boundary/semantics** | Owner approvals cho từng projection vẫn cần thực hiện |
| R-13 | P1 | Nhiều `OperationContext` trong một command | Standard §6.1–6.2, MDR §4: một context tại entrypoint, truyền cùng object vào participants/callbacks | Retrofit Api050 và tests **chưa thực hiện** |
| R-14 | P2 | Error code → HTTP đang suy diễn tên | Standard §11.3, MDR §6: mapping tường minh cho error mới, contract test, không tạo error framework mới | Legacy mapper hiện tại không đổi |
| R-15 | P1 | PBAC snapshot/fallback thiếu consistency/performance budget | Standard §6.4, MDR §6: xác định quyền theo snapshot operation; metrics rows/memory/queries/latency; không trả trang thiếu/tổng sai dưới dạng success | Chưa benchmark dataset thật |
| R-16 | P1 | Pricing gọi Product class nhưng chưa public registry | Standard Phụ lục E5 và §20.2: inventory `ProductRoleService`, `ProductUomService`, `ComboExpansionService`, yêu cầu duyệt method/consumer thực | `MODULE.md` Product hoặc central MDR chưa được author phê duyệt |

**Ý nghĩa `Đã thực hiện`:** văn bản đã được chỉnh. Không có nghĩa code/runtime, approval của business owner hoặc merge gate đã hoàn tất.

## 3. Bằng chứng thực tế từ source

### 3.1. 403 của Product

- `backend/app/Modules/Product/Product/Application/AuthorizedProductReadService.php` phát sinh `DomainException('PRODUCT_AUTHORIZATION_DENIED', ...)` khi PBAC từ chối.
- `backend/app/Infrastructure/Api/ApiErrorMapper.php` có heuristic `DENIED`/`FORBIDDEN` → HTTP `403`.
- Kết luận giới hạn: đường code **có thể** trả 403; chưa kiểm chứng toàn bộ HTTP contract hoặc môi trường triển khai.

### 3.2. KIR-001 COMBO + AUTO

- `backend/app/Services/Api050ProductCatalogService.php`: `createProduct()` mở `transBegin()` khi có `combo_items`; gọi `products()->create(...)` trong transaction.
- `backend/app/Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php`: `allocate()` kiểm tra `transDepth > 0` và từ chối nested transaction; nếu đủ điều kiện, allocator tự mở và commit transaction.
- Kết luận giới hạn: **đường gọi có điều kiện** có khả năng xung đột, chưa chứng nhận lỗi runtime cho mọi COMBO.

### 3.3. OperationContext

- `backend/app/Infrastructure/Mutation/MutationContext.php`: `begin()` tạo `OperationContext` mới với `EvaluationTime::capture(...)` trừ khi caller truyền evaluation time.
- `backend/app/Services/Api050ProductCatalogService.php`: `operation()` có thể gọi `mutationContext->begin()` nhiều lần trong một high-level command.
- Kết luận giới hạn: cần test parity/truyền cùng context; chưa suy luận rằng dữ liệu production đã sai.

### 3.4. Product dependency từ Pricing

- `backend/app/Modules/Pricing/ComboAllocation/ComboAllocationService.php`
- `backend/app/Modules/Pricing/Resolution/PricingService.php`
- `backend/app/Modules/Pricing/PriceList/PriceListService.php`

Các class này tham chiếu `ProductRoleService`, `ProductUomService` và/hoặc `ComboExpansionService`. Không tạo adapter mới chỉ để hợp thức hóa reference; trước tiên phải xác định chính xác các public methods/semantics trong MDR của Product.

## 4. Checklist trước khi phê duyệt v1.1.1 làm policy baseline

| Cửa kiểm soát | Yêu cầu | Trạng thái tại lúc xuất bản bộ tài liệu |
|---|---|---|
| Architecture owner/approver | Chỉ định người có quyền chốt AR-01..13, MDR schema và ngoại lệ | `PENDING_APPROVAL` |
| API/security contract 404/403 | Xác minh approved behavior từng operation nhạy cảm, ghi conflict nếu khác legacy | `CONTRACT_REVIEW_PENDING` |
| KIR-001 | Giao owner Product/Numbering, approve ADR sau POC MariaDB + concurrency | `OPEN / NOT_RUNTIME_VERIFIED` |
| Authorization read seam | Thiết kế consumer port, kiểm tra không recursive authorize, wiring test | `DESIGN_READY / CODE_PENDING` |
| Architecture tooling | Xây ARCH-01..07, classify 7 findings guard, positive/negative fixtures | `SPECIFIED / IMPLEMENTATION_PENDING` |
| Public Product API | Duyệt `class/method` và consumers từ Pricing, cập nhật MDR | `OWNER_REVIEW_PENDING` |
| MDR/CI rollout | Áp dụng template v1.1.1; new module có root MDR, legacy central register theo mốc | `POLICY_DEFINED / ROLLOUT_PENDING` |
| Runtime and performance evidence | CI MariaDB/Shield, query/p95/memory baseline khi cần | `NOT_VERIFIED` |

**Không buộc xử lý xong toàn bộ legacy/KIR để phê duyệt tiêu chuẩn kiến trúc.** Cần phân biệt ba quyết định: (a) *policy acceptance*, (b) *executable gate readiness*, (c) *module implementation conformance*.

## 5. Phạm vi và giới hạn kiểm tra tài liệu

- Đã đối chiếu nội dung gốc v1.1, bản paste review, module template và các source PHP được nêu trên trong ZIP.
- Tài liệu mới được kiểm tra cấu trúc/định danh/version/điểm tham chiếu; không thay code, route, database schema, dependencies.
- Kết quả lint 511/511, source-only tests 5/5, guard 7 findings trong bản rà soát được xem là **historical evidence của ZIP**, không phải tuyên bố vừa chạy toàn bộ CI trong đợt chỉnh sửa này.
- **Chưa chạy** PHPUnit/MariaDB/Shield integration, transaction concurrency, architecture scanner mới, benchmark production; không ghi nhận test tương ứng `PASS`.

## 6. Thứ tự hành động đề xuất

1. Chấp thuận nội dung v1.1.1 ở cấp **Architecture Policy** và xác nhận owner/effective date.
2. Đối chiếu approved API behavior cho Product read và quản lý `KIR-001` như rủi ro có điều kiện; chưa đổi code.
3. Đưa `ARCH-01..07` vào backlog/gate implementation, phân loại findings cụ thể trước khi bật required CI.
4. Hoàn thành MDR root/central register và Product public read API inventory.
5. Tiến hành refactor từng use case có parity tests, transaction/concurrency tests và rollback plan phù hợp.

---

**Quyết định trình duyệt:** `PROPOSED BASELINE v1.1.1` — **ready for architecture review**, **not an implementation certification**.
