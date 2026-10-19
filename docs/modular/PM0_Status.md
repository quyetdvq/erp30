# DinkERP — Product Media PM-0: kết quả baseline

**Ngày:** 19/10/2026.  
**Phạm vi:** Product Media Pilot v0.1 đã được phê duyệt.  
**Trạng thái PM-0:** PARTIAL — còn gate runtime.  
**Source:** `erp_v1.1.8.zip`; SHA-256 `59835ace6f4dd45d207f10db4e598c1acaae3f85779e924974f8e2dbd9d0831d`.

## 1. Kết quả thực sự đã chạy

| Kiểm tra | Kết quả | Giới hạn |
|---|---|---|
| Đối chiếu source với ZIP | 1.250/1.250 nguyên vẹn | Không xác minh artifact trên máy triển khai |
| PHP lint | 695/695 PASS | PHP CLI 8.4.24; không thay PHPUnit |
| Foundation safety guard | 17/17 PASS | Không kết nối DB |
| Storage characterization | 33/33 PASS | Class gốc, standalone, không boot CodeIgniter |
| Product image API declarations | 3/3 method/path/handler khớp | Static; chưa gửi HTTP request |
| Architecture guard | FAIL, exit 2, 10 findings | Baseline debt; không nới checker |
| As-built checker | FAIL_STATIC, exit 1, 3 findings | Fingerprint và migration catalogue |
| Full PHPUnit/PHPStan | Chưa chạy lại | Thiếu vendor và runtime prerequisites |
| Service/DB/HTTP/PBAC | Chưa xác minh runtime | Các scenario pending trong matrix |

Logs trong `evidence/`; manifest source và đối chiếu sau kiểm tra được lưu riêng. Không lấy log Windows cũ làm kết quả phiên này. Không gộp 33 standalone checks vào tổng PHPUnit.

## 2. Storage checks đã xác minh gì?

Runner gọi trực tiếp `App\Services\ProductImageStorage` và `DomainException` từ source gốc. Không dùng class giả thay implementation, không boot framework hoặc kết nối DB. `WRITEPATH` trỏ đến thư mục tạm ngẫu nhiên và đã cleanup sau run.

Phạm vi bao gồm JPEG/PNG/WebP, GIF bị từ chối, file rỗng/giả/quá dung lượng, ngưỡng 3 MiB và 5000 pixels, path traversal, filename do server sinh, lưu/đọc bytes/xóa, missing-file no-op và lỗi tạo thư mục.

Fixture ảnh tự tạo, không có dữ liệu người dùng. Test exact-byte-limit dùng PNG padding để cô lập ngưỡng byte; không chứng minh full decoding, không là chứng nhận an toàn image parser.

## 3. Điểm hành vi cần giữ

Controller upload kiểm quyền `edit` trước lỗi upload validation. Service kiểm quyền lại trên persisted Product. Upload/remove sau khi ghi metadata đều gọi `getProduct()` và kiểm `view`; không được bỏ lần đọc lại này khi trích xuất seam.

Source cho thấy write/cleanup diễn ra trước response read. Trường hợp `edit` được phép nhưng `view` bị từ chối sau mutation cần test DB để chốt side effects; không tự sửa thành rollback hoặc bypass quyền trong structural patch.

Metadata error ở tầng service là `PRODUCT_PERSISTENCE_FAILED`; mapper HTTP che diagnostic 500 thành `INTERNAL_SERVER_ERROR`. Không nhầm mã lỗi nội bộ với public API error. Đây là quan sát source, chưa phải HTTP test.

Evidence có line number trong `source_evidence.md`; consumer inventory là lexical search, không phải call graph hoặc bằng chứng không có dynamic consumers.

## 4. Phần còn lại trước khi đóng PM-0

Cần chốt artifact/checkpoint của môi trường triển khai, tái lập PHPUnit/PHPStan trên runtime đủ dependency và viết/chạy các characterization service/DB/HTTP/PBAC còn pending. Matrix là danh sách phải kiểm chứng, không phải các test đã được implementation.

Môi trường hiện tại không có vendor/Composer, BCMath, MySQLi và một số extension theo lock. Chi tiết trong `runtime_prerequisites.json`; đây không phải kết luận về server production. Chưa sửa composer.json/lock, chưa cài hoặc nâng dependency.

DB tests chỉ chạy qua guard trên disposable `erp_test`, `testing`, group `tests`, loopback MySQLi; phải xác minh actual target. Không dùng DB `erp`, không làm yếu safety gate để vượt lỗi môi trường.

## 5. Phạm vi thay đổi và rollback

Gói này chỉ có decision record, test harness, synthetic fixtures và evidence. Không thay file source, API, schema, frontend, storage layout hoặc business logic. Chưa chạy PM-1/PM-2/PM-3, chưa commit Git và chưa deploy. Có thể bỏ gói kiểm tra này mà không cần rollback dữ liệu.

Sau refactor, giữ danh tính scenario và review runner wiring khi chuyển class. Không dùng hash toàn bộ source làm điều kiện cấm mọi thay đổi hợp lệ; hash chỉ định danh baseline.
