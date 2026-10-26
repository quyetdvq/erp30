# M11.4A — Consent fixture isolation and Email operations remediation (26/10/2026)

**Baseline:** `erp_v1.7.0.zip`. **Phạm vi:** khắc phục PHPUnit Integration M11.4 phụ thuộc dữ liệu seed, và hướng dẫn gỡ chặn Email vận hành theo cơ chế quản trị hiện có. Không có migration, DDL, route, quyền mới hoặc thao tác gửi.

## 1. Nguyên nhân và thay đổi mã nguồn

Trong báo cáo M11.4, 117/117 Unit và 7/7 Reliability PASS; Integration lỗi tại `NotificationOperationsAuditIntegrationTest.php:43` vì `policy_guards['import.completed.email']` là `false`. Logic Audit chỉ đọc `notification_settings`, yêu cầu có policy Email/Push, `default_value=0`, `allow_user_override=0|1` và `revision` / `override_epoch` hợp lệ. Tuy nhiên:

- Migration 019 chỉ tạo bảng `notification_settings`, **không chèn policy Email**.
- Migration 021 tạo bảng Email receipts, **không chèn policy Email**.
- Migration 022 chèn policy Push, vì vậy DB test có thể có Push hợp lệ nhưng Email thiếu; `NotificationSeeder` là bước riêng và không nhất thiết đã chạy trên `erp_test`.
- Test cũ không chuẩn bị policy mà giả định dữ liệu `erp_test` đã được seed trước. Điều này làm FAIL cả khi logic Audit hoạt động đúng.

**Sửa tại test, không làm lỏng Audit:** mỗi test liên quan chính sách tự tạo hoặc cập nhật Email/Push policy hợp lệ **bên trong outer transaction** được kết nối qua `M11DatabaseGuard`, test snapshot, sau đó rollback. Thêm assert chứng minh Audit không đổi các policy/audit event và negative test cho `default_value=1`, `override_epoch=0`. `NotificationOperationsAuditReader` và `NotificationOperationsAuditRules` giữ nguyên: dữ liệu production thiếu/corrupt vẫn bị chặn; không tự sửa seeder hoặc dữ liệu production.

## 2. Blocker Email: thao tác vận hành, KHÔNG phải mã nguồn

Từ audit do operator cung cấp:

```text
DINKERP_M11_EMAIL_QUEUE_ENABLED=on
DINKERP_M11_EMAIL_SEND_ENABLED=on
configured_smtp=no
queue_email: PENDING=4, overdue_ready_15m=4
BLOCKERS: EMAIL_SEND_ON_WITHOUT_VALID_SMTP_CONFIG
```

M11.4 yêu cầu cố ý giữ blocker này nếu SEND bật nhưng SMTP chưa hợp lệ. Worker đã kiểm tra cấu hình SMTP trước khi claim queue; không được vá Audit thành PASS giả hoặc tự ghi flag vào DB. **Chọn một trong hai luồng bên dưới, theo quyết định của người vận hành:**

### A. Chưa triển khai SMTP — khuyến nghị hiện tại

1. Xác nhận môi trường `erp` (DEV/production), sao lưu DB và `.env` tại chỗ an toàn. Không gửi secret qua chat, Git hoặc log.
2. Đăng nhập bằng tài khoản có quyền `notification_setting.view/edit`, mở `/system/configuration-settings`, tìm nhóm Notifications / Email.
3. Đặt **Email Send = OFF** và **Email Queue = OFF** rồi lưu qua giao diện để giữ PBAC, CAS revision và configuration audit. Nếu muốn vẫn tích lũy Email trong khi chờ SMTP, có thể giữ Queue ON, nhưng cần chấp nhận backlog và cảnh báo bổ sung; không khuyến nghị trong trạng thái hiện tại.
4. Không sửa trực tiếp `dinktech_settings`, không xóa 4 receipt PENDING, không bật Email worker hoặc kích hoạt gửi hàng loạt để "xả hàng".
5. Chạy lại `php spark notifications:ops-audit --json` và `php spark notifications:ops-audit --strict`. Với hai cờ OFF, thiếu SMTP chỉ còn là note. `OVERDUE_READY_15M:email` có thể vẫn còn như warning; `--strict` chỉ block nếu có blocker khác.
6. Kiểm tra scheduler/log giám sát và ghi trạng thái 4 receipt. Khi bật lại sau này, các receipt cũ **có thể được gửi**; kiểm tra tuổi, policy và consent trước pilot.

### B. Chuẩn bị gửi SMTP thật (chỉ khi được phép)

1. Trước hết để Send OFF, cấu hình `Config\Email` và `DINKERP_M11_EMAIL_FROM` trong `.env` theo SMTP của tổ chức (host, user, password, TLS/SSL, port và timeout); không hardcode secret. Kiểm tra `configured_smtp=yes` bằng Audit.
2. Chỉ trên DEV hoặc môi trường pilot được phê duyệt, chuẩn bị một mailbox test đã opt-in, hạn chế Queue/Send theo quy trình điều khiển bằng giao diện. Kiểm tra 4 receipt tồn trước khi cho worker claim.
3. Chạy Email worker `php spark notifications:email-dispatch --once --batch=1` **chỉ sau khi được phép gửi thật**. Xác minh mailbox nhận thư, receipt `SENT`, opt-out và tình trạng retry, không suy ra từ `configured_smtp=yes`.
4. Sau pilot, tắt Send, quyết định duy trì Queue hay không, lưu audit và tình trạng scheduler.

## 3. Áp dụng patch và kiểm thử ở XAMPP

Từ root ERP với `git status --short` sạch hoặc đã bảo quản thay đổi đang làm:

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\ERP_M11_4A_Consent_Fixture_Operations_Remediation.patch
git apply .\ERP_M11_4A_Consent_Fixture_Operations_Remediation.patch
git diff --check

cd .\backend
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
php spark notifications:ops-audit --json
php spark notifications:ops-audit --strict
```

- `RUN M11`: kỳ vọng 117 Unit và **44 Integration** (43 cũ + 1 negative). Cần xác nhận lại số thực tế và 0 failures; không khẳng định PASS trước khi chạy trên MariaDB `erp_test`.
- `RUN M11-RELIABILITY`: kỳ vọng 7/7; chạy riêng, serial, không có worker khác dùng `erp_test`.
- `--strict`: chỉ có thể PASS sau khi operator khắc phục cờ SMTP/Send hoặc cấu hình SMTP; patch này **không thay đổi runtime flags**.
- Không cần `PREPARE M11` nếu migrations 019–022 đã tồn tại. Không dùng `migrate:refresh`, `db:seed` trên production để chữa một assertion của PHPUnit.

## 4. Điều kiện nghiệm thu

- Patch apply sạch, `git diff --check` sạch.
- Unit, Integration, Reliability đều PASS trên XAMPP với guard `erp_test` và không gửi SMTP/Push thật.
- `notifications:ops-audit --strict` không còn blockers sau thao tác operator; warning 4 Email quá hạn được xử lý/ghi nhận riêng, không tự động xóa.
- Lịch worker và logging được xác minh; Email live vẫn **PENDING** cho tới khi có bằng chứng gửi/nhận thực tế.
