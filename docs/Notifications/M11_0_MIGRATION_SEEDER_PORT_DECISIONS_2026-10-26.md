# M11.0 — Quyết định phát triển và bàn giao Migration/Seeder

**Ngày:** 26/10/2026  
**Nguồn:** `erp_v1.6.0.zip` → checkpoint port Migration/Seeder, chưa triển khai runtime Notifications.  
**Tài liệu thiết kế liên quan:** `ERP_M11_0_NOTIFICATIONS_DESIGN_IMPLEMENTATION_v1.3.md` (xem tài liệu này cùng addendum; những quy tắc đóng băng từ giai đoạn rewrite không còn là gate của M11).

## 1. Quyết định người dùng đã chốt

1. Các test, fingerprint và bộ quy tắc cũ được viết để chứng minh việc viết lại Backend từ `backend_baseline/`. Công việc rewrite đã hoàn thành sứ mệnh đó.
2. M11 là **phát triển tính năng mới**, không phải tiếp tục kiểm tra parity hoặc khóa 150 API cũ. Không bắt buộc chạy/cập nhật các gate rewrite cũ; khi thay đổi chức năng/API có thể viết lại test theo kiến trúc mới.
3. Hệ thống vẫn đang trong quá trình xây dựng, **chưa phải bản phát hành production**.
4. Cho phép refactor và chỉnh các tính năng đã xây dựng nếu cần cho kiến trúc tương lai, nhưng luôn bảo toàn tính toàn vẹn dữ liệu và quyền truy cập.
5. `backend/` là code chính; Migration/Seeder nghiệp vụ phải được đặt tại `backend/app/Database/`, `backend_baseline/` giữ vai trò tham khảo lịch sử.

## 2. Đã thực hiện trên source

| Hạng mục | Kết quả | Ghi chú |
|---|---|---|
| Port Migration 001–018 | **18 file** → `backend/app/Database/Migrations/` | Giữ nguyên code DDL, tên class, timestamp; chưa chạy DB |
| Port 4 Seeder | **4 file** → `backend/app/Database/Seeds/` | Không thay đổi danh sách action/state được seed ở bước này |
| Dependency migration 018 | `backend/app/Database/UserDisplayMetadataResetPolicy.php` | Helper port nguyên bản |
| Dependency `GroupRootSeeder` | Native MariaDB lock trùng tên live Organization | Bỏ phụ thuộc lock service không tồn tại trong backend hiện hành |
| An toàn tái chạy Seeder | Có | Không ghi đè domain PBAC, không mở lại quyền `SYSTEM_ADMIN` đã thu hồi |
| Seeder tổng | `BackendBootstrapSeeder.php` | Chạy theo thứ tự Workflow → Resource → Group root → System Admin |
| Chẩn đoán DB an toàn | `php spark db:legacy-preflight` | Chỉ đọc, kiểm tra 31 bảng nền và history, không migrate hay sửa ledger |
| Hướng dẫn chi tiết | `backend/app/Database/README.md` | Quy trình đối chiếu database có dữ liệu |

### Vì sao không xóa các bản trong `backend_baseline/`?

Chuyển ownership source không đồng nghĩa xóa lịch sử. Bản tham khảo cũ được giữ nguyên byte-for-byte, còn các file tại `backend/app/Database/` mới là các file CI4 sử dụng khi chạy backend hiện hành.

### Điểm cần chú ý về dữ liệu thật

- `php spark migrate` **không thể chạy an toàn theo mặc định** trên database đã có các bảng `dinktech_*` nếu bảng lịch sử migration của backend hiện hành không ghi nhận 18 migration cũ. Migrate 001 dùng `CREATE TABLE`, không tự bỏ qua bảng tồn tại.
- Một database có đủ 31 bảng nghiệp vụ **vẫn chưa đủ** để khẳng định 18 migration cũ đã được áp dụng đầy đủ: 009–018 chứa ALTER/DML/authorization change. Phải diff schema, dữ liệu catalog và migration history trước khi adopt.
- `php spark db:legacy-preflight` chỉ là kiểm tra sơ bộ, không thay thế kiểm tra `SHOW CREATE TABLE`, các index/FK, trạng thái Catalog, collision/duplicates và thử rollback trên DEV clone.
- `DBPrefix` runtime có thể khác config mặc định. Prefix vật lý `dinktech_` phải được xác nhận đúng trước mọi migration của M11.
- `BackendBootstrapSeeder` có side effect: có thể tạo root, Resource Action, Workflow State hoặc group `SYSTEM_ADMIN`. Không tự chạy khi chưa xác định môi trường DB và chủ thể sở hữu quyền.

## 3. Điều chỉnh đánh giá M11.0 v1.3

**Hủy yêu cầu bắt buộc:** giữ fingerprint cũ của 150 routes/OpenAPI, giữ nguyên số lượng resource từ các test rewrite, phải PASS các release gate M3–M9 trước M11, hoặc cần phê duyệt đặc biệt để refactor code đã viết lại. Không cần tạo cơ chế "150 + 8" chỉ để vượt qua các kiểm thử lịch sử.

**Giữ lại các yêu cầu kỹ thuật thực sự:** phải có hợp đồng API rõ ràng và SDK tương ứng, schema migration có thể khôi phục, PBAC fail-closed, isolation/transaction hợp lý, user ownership của Inbox, không làm thất lạc outbox event, quản lý worker/retention, kiểm thử concurrency và smoke test UI. Những yêu cầu này vẫn có giá trị độc lập với quá trình rewrite.

**Chưa thay đổi trong lần port:** 4 bảng Notifications mới, 8 operations, logic Notification policy/worker, sửa business Import terminal transaction, `notification_setting` PBAC resource và frontend. Đây là các hạng mục M11.1A/M11.1B tiếp theo, không được đánh dấu hoàn thành.

## 4. Lộ trình triển khai tiếp

1. Trên XAMPP **DEV clone**, chạy `php spark db:legacy-preflight` và `php spark migrate:status`, xác nhận schema và migration ledger. Thiết lập phương án adopt migration lịch sử có backup/reconcile, tránh `CREATE TABLE` trùng hoặc reset dữ liệu.
2. Kiểm tra bootstrap Seeder trên DEV clone (đặc biệt `SYSTEM_ADMIN`, root `GROUP_DINKTECH`), chạy lại lần hai và so sánh catalog/permissions trước–sau; xác thực không hồi sinh quyền bị thu hồi.
3. Viết migration CI4 mới cho 4 bảng Notifications, seed registry + resource/actions riêng, kiểm tra FK với `dinktech_users.id INT UNSIGNED` và audit dùng chung.
4. M11.1A: policy resolver + admin/user settings và 4 API; M11.1B: Import terminal outbox transaction, dispatcher, Inbox và 4 API còn lại.
5. Nghiệm thu theo **test hiện đại đúng tính năng**: migration/seed idempotency, SQL transaction và failure injection, policy/PBAC, HTTP/CSRF, OpenAPI/types, worker concurrency, frontend build/E2E. Không phụ thuộc gate parity cũ.

## 5. Phạm vi xác minh bản bàn giao

**Đã xác minh được:** số lượng file source, dependency thiếu trong backend, cú pháp PHP của các file mới/được port, đối chiếu cấu trúc và xác nhận không thay đổi bản tham chiếu `backend_baseline/`.

**Chưa xác minh:** kết nối XAMPP, Composer/vendor, `php spark migrate`, bootstrap Seeder đối với database, metadata thực tế, migration history trên hệ thống của bạn, hay runtime Notifications. Các việc này cần môi trường DEV clone thực tế.
