# DinkERP — Product Media Pilot: phạm vi kỹ thuật v0.1

**Ngày:** 19/10/2026  
**Owner:** M12 Product / feature Media.  
**Quyết định nền:** kiến trúc và lựa chọn pilot đã được phê duyệt trong `P1_Architecture_Approval_2026-10-19.md`.  
**Trạng thái chi tiết dưới đây:** phân rã triển khai, chưa phải implementation hoặc nghiệm thu.  
**Mã PM-0…PM-3:** nhãn công việc cục bộ của tài liệu, không tự cấp số BE-REORG.

## 1. Mục tiêu

Tạo slice mẫu đi trọn API → application → ports → persistence/storage, bảo toàn hành vi Product Image hiện hữu và loại phụ thuộc ngược vào Api050. Không mở thêm chức năng Media.

## 2. Hiện trạng đã đối chiếu trực tiếp với ZIP

| Nguồn | Quan sát |
|---|---|
| `app/Controllers/Api/ProductImages.php` | `show`, `upload`, `delete`; upload kiểm quyền trước lỗi upload validation |
| `app/Services/ProductImageService.php` | Gọi `Api050::productForImage()` và `Api050::getProduct()`; tự dựng connection/storage; ghi SQL metadata |
| `app/Services/ProductImageStorage.php` | Private storage; validate kích thước/MIME/nội dung; tên file do server tạo |
| `app/Services/Api050ProductCatalogService.php` | `productForImage()` kiểm action, lấy persisted row và authorize |
| `app/Config/Routes.php` | GET, POST, DELETE `/api/v1/products/{id}/image` |
| `tests/unit/ProductImageStorageTest.php` | Test storage hiện hữu trong snapshot |

Trích đoạn exact source, line và hash nằm trong `Product_Media_Source_Excerpt.md`. Việc đọc source này không thay cho test runtime hoặc khẳng định toàn bộ test coverage.

## 3. Phạm vi thay đổi

Canonical Product Media application nhận collaborator tường minh. Trích xuất contract đọc Product đã kiểm quyền dùng chung với façade Api050; cả kiểm quyền ảnh và đọc DTO Product sau mutation không còn đi ngược về Api050.

SQL ghi `image_filename`, `image_mime`, `image_size_bytes`, `updated_at`, `updated_by` chuyển vào adapter thuộc Product. File I/O vào storage adapter; application vẫn sở hữu flow authorize → validate/store → metadata → cleanup/result.

Chuẩn hóa lấy actor/time qua collaborator phù hợp. Trong pure structural patch, phải giữ giá trị và semantics đã được characterization; nếu chuyển sang OperationContext/Clock làm thay đổi timestamp, timezone, actor hoặc thời điểm đánh giá, tách phần đổi hành vi thành change có acceptance rõ.

Tên class đích dưới đây là đề xuất, không yêu cầu tạo một interface cho mọi class:

```text
Modules/Product/Media/
    Application/
        ProductImageService.php
        Ports/
            ProductImageMetadataPort.php
            ProductImageStoragePort.php
            ProductMediaAccessPort.php
    Infrastructure/
        Persistence/SqlProductImageMetadataRepository.php
        Storage/LocalProductImageStorage.php

Modules/Product/Product/
    Application/...            # Product access/read capability dùng chung

Bootstrap/...
Controllers/Api/ProductImages.php
Services/ProductImageService.php   # Façade nếu compatibility cần giữ
Services/ProductImageStorage.php   # Chỉ giữ khi có consumer/contract được kiểm kê
```

Giữ public HTTP controller/route trong pilot; không cần chuyển mọi controller sang module để chứng minh boundary. Wrapper cũ chỉ được giữ bằng ngoại lệ cụ thể có test, không nới guard cho cả namespace.

## 4. Hành vi phải bảo toàn

| Nhóm | Contract cần test |
|---|---|
| Quyền | `view` khi đọc; `edit` khi upload/remove; persisted target/context; giữ thứ tự authorize và upload validation |
| HTTP | Route/method, multipart field `image`, response DTO/errors; binary headers và không public storage |
| Nội dung ảnh | JPEG/PNG/WebP; tối đa 3 × 1024 × 1024 bytes; chiều rộng/cao tối đa 5000 theo source baseline |
| Path | Server-generated filename; path validation; cùng private storage location |
| Upload | Ghi file mới, metadata failure thì cleanup file mới; chỉ cleanup file cũ tại điểm được baseline cho phép |
| Remove | Cập nhật metadata trước khi xóa file; giữ output/error semantics |
| Product output | Giữ read/authorization semantics của việc đọc Product sau mutation; không bỏ lần kiểm tra chỉ vì trước đó đã có quyền edit |
| Persistence | Giữ injected connection, error wrapping và actor/timestamp fields; không claim filesystem và DB là một transaction |
| Compatibility | Kiểm consumer, signatures, named arguments/constants và tests trước khi bỏ path cũ |

Chỉ nêu những semantics có trong Product Image baseline. Product Image hiện không được chứng minh có CAS như Avatar; không tự thêm CAS hoặc đổi chiến lược concurrency trong patch di chuyển.

## 5. Ngoài phạm vi pilot

Không tách cả Api030/Api050; không sửa toàn Product/Pricing; không tái tổ chức frontend; không thay schema/resource/actions/PBAC/workflow; không thêm thumbnail, resize, CDN, queue hoặc media library; không đổi storage key/layout; không benchmark/optimization nhập chung structural patch; không thêm module thứ 14.

Chỉ được sửa các consumer/shared seam cần thiết để pilot không còn gọi ngược Api050.

## 6. Thứ tự thực hiện và gate

### PM-0 — Baseline và characterization

Kiểm source/artifact được triển khai; dependency/runtime prerequisite; test guard và diagnostic baseline; kiểm caller và API contract. Characterization cho ảnh có/không có, unauthorized actor, malformed upload, DB/storage failure và response sau mutation.

Không dùng tổng 1.048 tests hoặc Windows evidence cũ làm kết quả mới. Runner phải giữ danh tính scenario bắt buộc, cho phép thêm test có review. DB tests chỉ chạy qua guard trên disposable `erp_test`; không dùng DB production.

### PM-1 — Tách persistence/storage và composition

Đưa SQL/I/O về adapter, inject dependency và giữ behavior. Từng patch có old→new file map, dependency delta và rollback; giữ source contracts khi cần.

### PM-2 — Trích xuất Product access/read seam

Loại cả hai đường gọi ngược `productForImage()` và `getProduct()` từ canonical Media. Giữ upload authorization trước transport validation; không xóa các lần authorize/revalidation vì thấy có vẻ trùng lặp.

Đây là thay đổi hẹp có thể chạm Api050 để delegate, không phải tái cấu trúc toàn bộ Api050.

### PM-3 — Kiểm thử, review và nghiệm thu pilot

Unit/application tests với fake ports; integration metadata/storage; HTTP multipart/binary; PBAC/state regressions; factory connection/actor/time; failure cleanup và consumer compatibility. Chạy architecture guard/PHPStan/full regression trên môi trường đầy đủ theo gate.

Bằng chứng tối thiểu: raw logs, danh tính test, exact artifact/checkpoint, API/schema diff, residual risks và rollback. Không tuyên bố clean nếu raw tools vẫn lỗi.

## 7. Tiêu chí chuyển sang platform boundaries

Pilot chỉ trở thành pattern chuẩn khi canonical Media không phụ thuộc Api050, không tự SQL/file I/O/ambient actor/time, contract giữ nguyên, negative/failure tests đạt và review xác nhận không làm yếu security/transaction semantics.

Sau pilot, hoàn thiện composition và Identity/Avatar, ResourceCatalog, Audit, trusted read/context seams theo các patch riêng; rồi mới nhân rộng orchestration Api030/Api050. Không tự cho rằng platform boundaries đã đạt chỉ vì controller đã mỏng.

## 8. Bàn giao và rollback

Bàn giao candidate patch, test/evidence bundle, file/dependency map, compatibility register và rollback note. Pure code patch rollback bằng artifact/commit tương thích, giữ nguyên file ảnh/key layout; không xóa dữ liệu người dùng.

Tài liệu này không chứa patch source; chưa tạo Git branch/commit và chưa chạy test runtime mới.
