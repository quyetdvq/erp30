# M11.4 — Operations Audit / Module Closeout (26/10/2026)

**Mục tiêu:** chuẩn hóa vận hành và bàn giao module Notifications sau M11.1A–M11.3C. M11.4 không thêm bảng, migration, HTTP route, permission, SMTP/Push recipient, và **không bật bất kỳ công tắc nào**.

## 1. Phạm vi đã nghiệm thu và chưa nghiệm thu

| Chức năng | Bằng chứng ở DEV (do người vận hành xác nhận) | Giới hạn |
| --- | --- | --- |
| Inbox/Outbox/Import | Inbox hiển thị thông báo thực, Outbox xử lý và không nhân đôi | Chưa chứng minh đồng thời mọi workload production |
| Workflow | PHPUnit MariaDB và Reliability regressions | Chỉ In-App đối với workflow trong phạm vi M11 hiện tại |
| Email | PHPUnit giả lập gửi, opt-in, retry, inactive/identity, rollback | **Chưa nghiệm thu gửi/nhận SMTP thật**; không được ghi “Email live PASS” |
| Browser Push | PHPUnit, Chromium Headless/Headed, real E2E user #2 / Import #11, provider `SENT:1`, người dùng xác nhận notification/click Inbox | Không bảo đảm hiển thị trên mọi thiết bị; Edge/WNS chưa hỗ trợ |
| Runtime config | Chín công tắc qua `/system/configuration-settings` với PBAC/CAS/audit | SMTP password, VAPID private key, endpoint key và Cursor Secret **vẫn trong `.env`** |

**Quyết định đề xuất:** CODE/QA đạt phạm vi M11; **EMAIL LIVE DELIVERY = PENDING**. Không đánh đồng “module được triển khai” với “sẵn sàng kích hoạt Email trên production”. Chỉ đóng phần Email sau kiểm thử SMTP thực tế có kiểm soát, backup cấu hình và kiểm tra worker scheduling.

## 2. Một lệnh đọc toàn bộ vận hành

Từ `D:\XAMPP\htdocs\erp\backend` (đảm bảo `.env` trỏ đúng database, không sử dụng lệnh trên hệ thống không được phép):

```powershell
php spark notifications:ops-audit
php spark notifications:ops-audit --json
php spark notifications:ops-audit --strict
```

- **Chỉ đọc**: kiểm tra chín bảng Notifications và năm bảng phụ thuộc; chín cờ nguồn database hoặc legacy; nguyên tắc system default Off và phiên bản policy hợp lệ của Email/Push (quản trị viên vẫn có quyền khóa override bằng `allow_user_override=0`); secret **đủ điều kiện hình thức** (không in giá trị); số thiết bị active; trạng thái PENDING/RETRY/LEASED/DEAD/SENT/PROCESSED; expired leases và pending đã tới hạn trên 15 phút.
- Các phép kiểm tra SMTP dùng **đúng bộ quy tắc** của `NotificationSmtpSender`, không mở socket và không gửi email. VAPID P-256, subject, khóa mã hóa và một mẫu endpoint đang hoạt động được kiểm tra trong bộ nhớ, **không gửi HTTP**. Với nhiều subscriptions, chạy `notifications:push-acceptance --stage=preflight` ở môi trường có thể dừng kênh để kiểm tra toàn bộ endpoint.
- `--json`: một JSON object, không bao gồm password, token, email người nhận, endpoint hay dữ liệu Import. Có thể dùng để ghi nhận báo cáo trên máy vận hành được ủy quyền.
- `--strict`: lỗi cấu hình bắt buộc gây exit code khác 0; **cảnh báo (`warnings`) vẫn cần người vận hành xem xét**, không tự kích hoạt Worker. Nếu đang tắt Email/Push, thiếu khóa riêng của kênh đó được ghi là `notes` thay vì lỗi chung. `OPS CONFIGURATION PASS` **không thay thế** xác nhận SMTP/Browser E2E hoặc quyết định production rollout.
- Lệnh không tự claim/requeue, không tự sửa flag, không migration, không tạo subscription và không gửi Push/Email.

### Ý nghĩa cảnh báo

| Code | Cách xử lý |
| --- | --- |
| `INBOX_CURSOR_SECRET_MISSING_OR_SHORT` | Khôi phục Cursor Secret ổn định trong `.env` trước khi mở Inbox |
| `INVALID_RUNTIME_FLAG:*`, `MISSING_TABLE:*`, `CONSENT_POLICY_INVALID:*` | Dừng rollout; kiểm tra cấu hình/schema/audit; không tự sửa trực tiếp DB |
| `EMAIL_SEND_ON_WITHOUT_VALID_SMTP_CONFIG` | Tắt Email Send, sửa `Config\Email`/`.env`, chạy lại audit, thử mailbox riêng |
| `PUSH_ENABLED_WITHOUT_VALID_CRYPTO_OR_ENDPOINT` | Tắt Push Queue/Send; khôi phục key nguyên bản, không tự xoay khóa khi đã có thiết bị |
| `DEAD_LETTERS:*`, `RETRY_BACKLOG:*` | Rà lại nguyên nhân, policy, tài khoản/consent; không xóa hàng đợi để che lỗi |
| `EXPIRED_LEASES:*` | Kiểm tra Worker crash, lease/retry, đồng hồ UTC và tiến trình chạy đồng thời |
| `OVERDUE_READY_15M:*` | Kiểm tra lịch Worker hoặc chế độ tạm dừng; đây là cảnh báo nếu kênh cố ý tắt |
| `RUNTIME_FLAG_NOT_PERSISTED:*` | Xem xét lưu tất cả cờ vào DB qua trang hệ thống; `.env` chỉ là fallback |

## 3. Quy trình khai thác an toàn

1. Trước khi thay đổi công tắc: backup database `erp`, backup `.env` qua nơi an toàn ngoài Git, ghi lại trạng thái cờ; xác nhận server đang trỏ DEV hay production. **Không upload/báo log chứa secret.**
2. Mặc định Email Send/Push Send `Off`. Nếu chưa có SMTP pilot hoặc muốn ngừng Push, Email Queue/Push Queue cũng Off. Dừng lịch worker khi không muốn nhận tác vụ mới.
3. Chỉ vận hành `notifications:dispatch` khi In-App Delivery được cấp phép. Email và Push là **worker riêng** (`notifications:email-dispatch`, `notifications:push-dispatch`), mỗi lệnh dùng `--once --batch=N`; cần scheduler chuyên biệt nếu được cho phép. Không đặt worker trong HTTP request hoặc transaction Import.
4. Trước pilot mỗi kênh, chạy `notifications:ops-audit`, giải quyết blocker và đọc warning; chỉ bật Queue khi user đã opt-in. Với Push đã có E2E, thử một Job/thiết bị bằng cổng `notifications:push-acceptance --stage=created/queued/sent` và tắt Send ngay sau khi chạy.
5. Với SMTP **chưa nghiệm thu thực tế**: thiết lập `Config\Email` protocol SMTP, host/user/password, TLS/SSL, From, timeout; đăng ký đúng một mailbox opt-in và tạo Import DEV phù hợp. Chỉ gửi từ Worker Email khi người vận hành xác nhận sẽ gửi ra ngoài; kiểm tra hộp thư và `SENT` cùng khả năng hủy sau opt-out. Không dùng địa chỉ của người dùng thật không đồng ý nhận.
6. Quan sát ít nhất: PENDING/RETRY/LEASED theo tuổi, DEAD, expired leases, retry backoff, cảnh báo nếu scheduler dừng lâu; so khớp số lượng deliveries và consent. `notifications:inspect`, `notifications:push-status --user-id=N` và `notifications:ops-audit` đều là lệnh quan sát, không thay thế HTTP E2E.
7. Khi rollback **không DROP các bảng**: đưa Send và Queue về Off; dừng scheduler; giữ Outbox/receipt cho lần xử lý sau. Không xoay VAPID/endpoint key mà không có kế hoạch di chuyển subscriptions. SMTP/Push delivery là **at-least-once**, có thể trùng nếu provider chấp nhận rồi ACK DB thất bại.

## 4. Kiểm thử và điều kiện đóng Module

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
php spark notifications:ops-audit
php spark notifications:ops-audit --json
```

- M11.4 bổ sung PHPUnit Unit cho parser, ma trận blockers/warnings và SMTP config, Integration cho schema, policy, counters cùng tính **read-only** trên `erp_test`. **Không cần PREPARE, migration hoặc seeder mới** nếu Migration 019–022 đã có.
- `notifications:ops-audit` luôn đọc database default của phiên đang chạy; không giả định là `erp_test` khi chạy bằng Spark bình thường. PHPUnit sử dụng guard bắt buộc `database.tests=erp_test`.
- Sau khi xem xét bằng chứng trên DEV: ghi rõ người chịu trách nhiệm, thời gian, cấu hình thực tế, quyết định bật kênh và kết quả pilot. Chỉ đóng phần Email live sau khi có bằng chứng SMTP real, không tự nâng kết quả giả lập thành nghiệm thu thực.

**Quyết định release:** M11.4 là bản công cụ audit và runbook. Nó không bật chức năng và cũng không chứng nhận production readiness thay cho người vận hành.
