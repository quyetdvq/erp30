# DinkERP — Product Media PM-0: kết quả triển khai tiếp

**Ngày:** 19/10/2026.  
**Trạng thái:** TEST-CODE CANDIDATE / PM-0 CÒN GATE RUNTIME.  
**Phạm vi:** Product Media Pilot v0.1 đã được phê duyệt. Chưa triển khai PM-1/PM-2.  
**Source:** erp_v1.1.8.zip; SHA-256 `59835ace6f4dd45d207f10db4e598c1acaae3f85779e924974f8e2dbd9d0831d`.

## 1. Đã triển khai gì?

Đã tạo mã kiểm thử và runner cụ thể, không chỉ cập nhật kế hoạch. Candidate thêm **13 file** và giữ nguyên **1.250/1.250 file gốc**. Không sửa application, API, schema, frontend, lockfile, .env hoặc storage layout. Không tạo Git commit, không deploy.

| Bộ mã PHPUnit mới | Số method | Mục đích |
|---|---:|---|
| ProductMediaServiceCharacterizationTest | 27 | Luồng service, metadata, cleanup, actor/time, PBAC/state, transaction của caller |
| ProductMediaHttpCharacterizationTest | 16 | Route/controller, validation, headers, DTO/envelope, quyền và một test error mapper |
| ProductMediaCompatibilityTest | 3 | Constructor/named arguments, method signatures, storage constants |
| **Tổng** | **46** | **Đã viết, chưa chạy PHPUnit** |

Các test dùng implementation ProductImageService, ProductImageStorage và Api050 thật. Fault injection metadata dùng mock connection/builder; test HTTP trong process thay getFile()/upload provenance để CLI có thể đưa file fixture vào. Chúng **không thay thế kiểm thử multipart qua web server, browser hoặc secure-runtime CSRF**. Chưa có bằng chứng rằng cả 46 test boot/chạy được trên runtime đủ dependency.

Runner mới là `backend/tools/test-product-media.php`, kèm manifest và guarded bootstrap. Manifest giữ danh tính **1.025 test methods gốc** và 46 methods mới. Số methods này khác số runtime cases có data providers; không đồng nhất với 1.048 cases trong evidence Windows cũ. Runner so sánh danh tính case được discovery với JUnit, không chỉ kiểm tổng số.

## 2. Kết quả thật sự đã chạy trong lượt này

| Kiểm tra | Kết quả | Giới hạn |
|---|---|---|
| Source preservation | 1.250/1.250 giữ nguyên; 13 file thêm | Không xác minh checkout Windows/production |
| PHP lint candidate | **703/703 PASS** | PHP CLI 8.4.24; không phải kiểm tra type/runtime |
| PHP lint helper ngoài source | **4/4 PASS** | Applier + offline probes |
| Foundation safety guard | **17/17 PASS** | Không kết nối DB |
| Storage characterization | **33/33 PASS** | Storage thật, fixture tự tạo, thư mục tạm |
| Service flow probe cô lập | **29/29 PASS** | Service/storage thật; DB/catalog/auth giả lập |
| Runner gate self-tests | **36/36 PASS** | Logic arguments/inventory/hash; chưa chạy DOM parser hay DB bootstrap |
| Applier tests | **6/6 PASS** | Check/apply/idempotent và từ chối conflict/drift trên bản sao |
| Architecture guard | **FAIL, exit 2: 10 findings** | Nợ baseline, không nới rule để bỏ qua |
| As-built checker | **FAIL_STATIC, exit 1: 3 findings** | Nợ catalogue baseline |
| Runtime preflight | **BLOCKED, exit 2** | Thiếu extensions và vendor |
| Lệnh run có xác nhận DB | **DỪNG AN TOÀN, exit 2** | Dừng trước kết nối DB; không phải test runtime PASS |
| Full PHPUnit/PHPStan/HTTP/PBAC/DB | **CHƯA CHẠY** | Không gán kết quả offline cho các gate này |

Không cộng 33, 29, 36 và 6 thành số PHPUnit tests. Đây là các phạm vi kiểm tra khác nhau. Raw logs, exit codes và danh sách checks nằm trong `evidence/`.

## 3. Hành vi đã quan sát bằng offline probe

Với collaborator cô lập, service gốc đã cho thấy: upload ghi file mới trước metadata; metadata thất bại thì dọn file mới và giữ file cũ; remove ghi metadata trước dọn file. Việc đọc lại Product diễn ra sau các side effects. Khi collaborator đọc lại từ chối, service không tự hoàn tác những side effects đó. Đây chưa phải xác minh PBAC/transaction DB thật.

Probe cũng bao phủ malformed stored filename, fake metadata exception chain, actor fields và process-timezone timestamp. Hành vi unlink failure logging trên filesystem thật vẫn chưa được kiểm chứng. Không sửa những hành vi này thành rollback/CAS mới trong patch cấu trúc.

## 4. Cơ chế an toàn đã viết

Runner yêu cầu xác nhận rõ target loopback, port, `erp_test` và prefix; tái sử dụng Foundation RuntimeGuard. Khi đủ dependency, bootstrap sẽ kiểm actual database/port trước DatabaseTestTrait, dùng advisory lock, WRITEPATH tạm riêng và target attestation theo run token. Chỉ code của các bước DB này đã được viết/lint; chưa chạy end-to-end.

Test discovery và execution cả hai đều đi qua bootstrap. Logs tạo mới cho từng run, giữ raw exit PHPUnit/PHPStan. JUnit thiếu case, thay case cũ bằng case mới, failed/errored/skipped hoặc unknown format đều không được coi là đạt. PHPStan raw exit 1 không bị đổi thành clean.

Advisory lock chỉ phối hợp các runner PM-0 mới. Phải dừng các runner DB khác; không giả định legacy runner tuân theo lock. Chỉ dùng DB disposable vì fixtures có thể reset dữ liệu.

## 5. Điểm chặn và giới hạn

Kết nối tải Composer kiểm lại vẫn lỗi DNS, curl exit 6. PHP thiếu bcmath/curl/dom/gd/intl/mbstring/mysqli/SimpleXML/sqlite3/xml/xmlreader/xmlwriter/zip; không có vendor PHPUnit/PHPStan/CI4. Chưa cài dependency, chưa tạo .env/DB. Không được diễn giải lỗi môi trường này thành lỗi production của dự án.

Mã test mới có thể cần hiệu chỉnh khi chạy đầu tiên với dependency đúng lock. PHP lint không kiểm API framework/type compatibility. PHPStan config hiện của dự án phục vụ app; chưa có kết quả static analysis type cho test mới. Xem `TEST_COVERAGE_MATRIX.md` cho các scenario còn pending, đặc biệt H08/H10/M05/M10.

## 6. Điều kiện chuyển sang PM-1

Chạy candidate trên runtime đủ dependency và DB test cô lập, xử lý fail bằng đối chiếu baseline chứ không bỏ assertion. Hoàn tất runtime characterization còn thiếu, regression, PHPStan diagnostic baseline và evidence artifact/checkpoint. Browser/SAPI có phạm vi riêng. Chỉ khi gate liên quan đủ bằng chứng mới trích xuất persistence/storage/composition.

Không cần xin phê duyệt lại scope v0.1. Chỉ thay đổi hành vi phát sinh mới cần được tách thành change riêng. PM-0 chưa đóng; pilot chưa nghiệm thu.

## 7. Áp dụng và rollback

Đọc `README.md`. Applier chỉ thêm các file test/tooling trong overlay, từ chối source drift và file đích khác nội dung. Hash toàn bộ source chỉ là gate của PM-0 test-only, không phải lệnh cấm refactor vĩnh viễn; PM-1 cần manifest kế nhiệm được review.

Rollback an toàn nhất là bỏ checkout test riêng. Hoặc chỉ gỡ đúng file mới trong `overlay-hashes.json` khi hash vẫn khớp; không xóa cả thư mục tests/tools, không xóa file đã được người khác sửa. Chạy test có thể thay đổi DB disposable; bộ applier không cung cấp rollback dữ liệu.
