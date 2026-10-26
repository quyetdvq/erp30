# M11.7 — NOTIFICATION OPERATIONS CENTER

**Tài liệu thiết kế triển khai kỹ thuật**  
**DinkERP | CodeIgniter 4.7.5 · MariaDB 10.4 · Vue 3 · Shared Hosting**

| Thuộc tính | Giá trị |
|---|---|
| Mã tài liệu | DINKERP-M11.7-SDD-001 |
| Phiên bản | 1.0 — Đề xuất thẩm định |
| Ngày lập | 26/10/2026 |
| Loại tài liệu | Software Design & Implementation Specification |
| Phạm vi | Giám sát Email Queue, M11 Tasks, Cron Heartbeat, lịch sử thực thi |
| Trạng thái | **CHỜ PHÊ DUYỆT** — chưa tạo migration, API hoặc patch ERP |
| Nguồn kiểm tra | ERP v1.7.0 + chuỗi patch M11.4A → M11.6C và log vận hành do operator cung cấp |

> **Quyết định kiến trúc đề xuất:** Xây dựng một trang frontend **chỉ đọc**, dựa trên API giám sát an toàn và telemetry bền vững cho ba worker M11. Phân biệt rõ (a) task đã cấu hình, (b) Cron thực sự kích hoạt, (c) worker đã chạy, và (d) email đã được SMTP chấp nhận. Không thay thế queue, không gửi lại email từ UI.

## 1. Tóm tắt điều hành

M11 Email đã đạt kiểm thử nghiệp vụ thực tế: **4/4** receipt `import.completed` chuyển `SENT`, không còn backlog trong log cuối, và người vận hành xác nhận **4/4 email đã đến đúng Inbox**. Các bài kiểm thử gần nhất đạt **141 unit + 47 integration + 7 reliability = 195 backend tests**, frontend 5/5 Vitest, type-check và production build PASS. Ba task được đăng ký mỗi phút, nhưng bằng chứng hiện tại là **chạy `tasks:run` thủ công**, chưa phải nghiệm thu Cron tự động trên hosting.

Thiết kế M11.7 bổ sung khả năng quan sát mà không can thiệp vào logic xử lý: **Tổng quan**, **Email Queue**, **Tasks & Cron**. Để tránh báo xanh sai, phải ghi nhận *Cron tick* riêng và *worker execution* riêng. API chỉ đọc, có phân quyền backend; dữ liệu SMTP, người nhận và nội dung thông báo không được trả về.

**Cổng phê duyệt bắt buộc trước coding:** (G0) phê duyệt hai bảng telemetry bổ sung và migration forward-only; (G1) phê duyệt Resource/Action PBAC SYSTEM mới hoặc phương án quyền thay thế; (G2) chốt OpenAPI contract; (G3) kiểm tra shell/CLI/Cron thực tế trên hosting.

## 2. Kết quả kiểm tra lại nguồn hiện hành

### 2.1 Bằng chứng đã kiểm tra

| Hạng mục | Hiện trạng đã xác minh |
|---|---|
| Email storage | `notification_email_deliveries` có `id`, `event_type`, `status`, `attempts`, `available_at`, `leased_until`, `last_error_code`, `created_at`, `sent_at`; có ràng buộc trạng thái và index claim/lease. |
| Trạng thái Email | `PENDING`, `LEASED`, `RETRY`, `SENT`, `CANCELLED`, `DEAD`. **Không dùng `PROCESSING`** trong API Email. |
| Worker Email | Chạy SMTP ngoài transaction, lease 120 giây, kiểm tra consent/identity tại thời điểm gửi, có retry/dead/cancel. Có rủi ro at-least-once khi SMTP đã nhận nhưng ACK DB thất bại. |
| Runtime switches | 12 công tắc đã quản lý qua `configuration-settings/notifications`; các task In-App, Push, Email hiện được bật theo log operator cuối. |
| `Config/Tasks.php` | `$logPerformance = false`; mỗi task dùng `command()->everyMinute()->named()->singleInstance(180)`. |
| Task definitions | In-App `--batch=25`; Push `--batch=5`; Email `--batch=1` và chỉ đăng ký nếu Email Task ON + Email Send ON + SMTP ready. |
| Cron wrapper | `backend/tools/m115_tasks_cron.sh` kết thúc bằng `exec php spark tasks:run ...` vào log ngày. Chưa có heartbeat start/finish trong DB. |
| Operations audit | Reader đọc 9 bảng M11 **lõi** và các dependency; queue aggregate có status, expired lease và overdue 15 phút. |
| CLI backlog | `notifications:email-backlog` chỉ xem tối đa 50 receipt PENDING/RETRY, không lộ user/email/payload. |
| PBAC hiện hữu | `notification_setting` là Resource SYSTEM, có `view/edit`; chưa có Resource `notification_operations` trong mã nguồn rà soát. |
| Frontend | `/system/configuration-settings` là giao diện điều khiển có CAS/audit, chưa có Operations dashboard. |

### 2.2 Phân biệt nguồn thông tin

- **Từ code:** schema, gating, batch, lease, retry, route, PBAC và hành vi Cron wrapper.
- **Từ log operator:** 4 email SENT, queue rỗng, ba task đăng ký, kiểm thử frontend/backend PASS; người vận hành xác nhận 4/4 Inbox. Không có kết nối đọc trực tiếp MariaDB hoặc server Cron production trong lần rà soát tài liệu này.
- **Không được suy diễn:** `tasks:list` có lịch là Cron đang chạy; `SENT` là email đã vào Inbox; `claimed=0` là worker lỗi; `Last Run=--` là bằng chứng worker chưa từng chạy.

### 2.3 Chỉnh sửa quan trọng so với phương án sơ bộ

1. Không tái sử dụng endpoint `notifications:ops-audit` làm polling API trực tiếp: reader hiện kiểm tra nhiều dependency, đọc cấu hình và probe khóa Push; dashboard nên có **lightweight snapshot query** riêng.
2. Không bật `$logPerformance` rồi xem đó là telemetry đủ dùng. CodeIgniter Tasks hỗ trợ log performance vào Settings, nhưng không đáp ứng đầy đủ `claimed/sent/retry/dead`, origin Cron và rủi ro mất heartbeat.
3. Không tạo hàng loạt dữ liệu lịch sử giả từ email đã SENT. **Telemetry chỉ có hiệu lực từ thời điểm triển khai.**
4. Không tự tạo PBAC Resource/Action, migration hoặc OpenAPI endpoint khi chưa qua thay đổi hợp đồng kiến trúc DinkERP.
5. Giữ nguyên 9 bảng M11 lõi trong Operations Audit; các bảng telemetry mới là **bảng mở rộng**, phải có phép kiểm tra schema riêng.

## 3. Mục tiêu và phạm vi

### 3.1 Mục tiêu có thể nghiệm thu

| ID | Yêu cầu |
|---|---|
| FR-01 | Tổng quan Email/Outbox/Push: tổng theo trạng thái, overdue ready 15 phút, expired leases, thời gian snapshot UTC. |
| FR-02 | Danh sách Email Queue: lọc trạng thái, event_type, khoảng thời gian, pagination ổn định; xem thông tin vận hành không nhạy cảm. |
| FR-03 | Danh sách task M11: cấu hình enabled, registered, lịch, thời điểm gần nhất, kết quả và counters theo từng lượt chạy. |
| FR-04 | Ghi nhận *Cron wrapper tick* start/finish/failure, phân biệt với người vận hành chạy Spark thủ công. |
| FR-05 | Phân loại HEALTHY/DEGRADED/FAILED/PAUSED/BLOCKED/UNKNOWN bằng thuật toán có reason code, không báo xanh khi thiếu dữ liệu. |
| FR-06 | Người có quyền VIEW xem được dashboard; không thể điều khiển worker, queue hay xem bí mật. |
| FR-07 | Telemetry bị lỗi không làm thay đổi kết quả xử lý nghiệp vụ, retry hoặc trạng thái SMTP. |
| FR-08 | Tối ưu Shared Hosting: single Cron, batch giới hạn, retention, phân trang, kiểm thử rollback an toàn. |

### 3.2 Ngoài phạm vi của phiên bản 1

Không có nút **Run Now / Retry / Requeue / Cancel / Delete / Export raw**. Không gửi email, không bật/tắt task trực tiếp trên dashboard, không chạy shell qua HTTP, không xử lý WebSocket, không lưu email address/subject/body, không cam kết đo Inbox delivery tự động, không thay thư viện CodeIgniter Tasks hoặc các queue hiện hữu. Trang cấu hình vẫn là `/system/configuration-settings` với PBAC và CAS/audit như hiện tại.

Phạm vi task ban đầu là ba task M11; nếu bổ sung task dọn telemetry, UI cần hiển thị rõ nó là **task bảo trì bổ sung** chứ không tuyên bố hệ thống chỉ có ba task mãi mãi.

## 4. Kiến trúc mục tiêu

```text
Vue /system/notification-operations  (READ ONLY)
       |
       v  HTTPS + Shield + PBAC + no-store
/api/v1/notification-operations/*
       |
       +--> OperationsReader (SELECT aggregate, email list, runs, ticks)
       |       +--> 9 bảng M11 lõi (read only)
       |       +--> notification_task_runs  (telemetry)
       |       +--> notification_cron_ticks (heartbeat)
       |
       +--> TaskHealthEvaluator (pure policy, reason codes)

cPanel Cron --> m115_tasks_cron.sh  [tick-start; tasks:run; tick-finish]
                        |                  |
                  cron_ticks         CodeIgniter Tasks
                                           |
                             existing In-App / Push / Email commands
                                           |
                               worker.runOnce() + TaskRunRecorder
                                           |
                                      task_runs
```

**Nguyên tắc:** API đọc và scheduler ghi telemetry dùng cùng DB nhưng các giao dịch telemetry **không được nằm trong transaction xử lý nghiệp vụ**. Task telemetry phải *best-effort*: ghi log thất bại không được làm phát sinh retry gửi lại email đã thành công.

### 4.1 Đường đi của dữ liệu

1. Cron cPanel gọi wrapper duy nhất, thường mỗi phút.
2. Wrapper sinh `tick_id` ngẫu nhiên, ghi `STARTED` (best effort), gắn biến môi trường tiến trình `DINKERP_M11_CRON_TICK_ID`, chạy lệnh `php spark tasks:run` và ghi `FINISHED`/`FAILED` khi kết thúc.
3. CodeIgniter Tasks giữ nguyên việc chọn task theo cấu hình và khóa `singleInstance(180)`.
4. Mỗi lệnh worker hiện hữu ghi `RUNNING`, thực thi chính `runOnce()`, lưu counters/result/duration rồi trả điều khiển theo cơ chế cũ; ngoại lệ thực được rethrow sau khi cố gắng lưu mã lỗi đã chuẩn hóa.
5. API chỉ đọc trạng thái nguồn và telemetry, tính health tại thời điểm query, cung cấp danh sách có pagination cho frontend.
6. Người xem không làm phát sinh job, SMTP, Push hay cập nhật queue.

## 5. Thiết kế cơ sở dữ liệu bổ sung — PROPOSED

### 5.1 `notification_task_runs`

Bảng mở rộng, dự kiến migration kế tiếp sau `000022`, theo quy ước `dinktech_` prefix tại runtime; **không chỉnh migration đã áp dụng**.

| Cột | Kiểu đề xuất | Ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | Dùng string ở JSON transport |
| `tick_id` | CHAR(32) NULL | Liên kết Cron tick; NULL cho CLI thủ công/không xác định |
| `task_name` | VARCHAR(64) NOT NULL | Allowlist task M11 (và maintenance nếu phê duyệt) |
| `origin` | VARCHAR(16) NOT NULL | `CRON`, `MANUAL`, `UNKNOWN` |
| `status` | VARCHAR(16) NOT NULL | `RUNNING`, `SUCCESS`, `NO_WORK`, `PAUSED`, `WARNING`, `FAILED` |
| `started_at` / `finished_at` | DATETIME(6) / NULL | UTC, dùng DB time nhất quán |
| `duration_ms` | INT UNSIGNED NULL | Đo monotonic elapsed trong cùng process |
| `stats_json` | LONGTEXT NULL, JSON_VALID CHECK | Chỉ JSON numeric counters allowlist, không có payload |
| `error_code` | VARCHAR(100) NULL | Mã đã allowlist; không chứa exception message/SMTP detail |

Index tối thiểu: `(task_name, started_at, id)`; `(started_at, id)`; `(status, started_at)`; `(tick_id)`.

Không FK bắt buộc `tick_id` trong phase 1 nếu việc purge tick/run cần độc lập; nếu thêm FK phải chứng minh không chặn cleanup và không làm worker thất bại. CHECK cho `origin/status` theo MariaDB 10.4 và migration pattern hiện hữu.

### 5.2 `notification_cron_ticks`

| Cột | Kiểu đề xuất | Ghi chú |
|---|---|---|
| `tick_id` | CHAR(32) PRIMARY KEY | Random `bin2hex(random_bytes(16))`, không chứa hostname/path |
| `started_at` | DATETIME(6) NOT NULL | UTC khi wrapper bắt đầu |
| `finished_at` | DATETIME(6) NULL | NULL khi chưa hoàn tất hoặc crash |
| `status` | VARCHAR(16) NOT NULL | `STARTED`, `FINISHED`, `FAILED` |
| `runner_exit_code` | SMALLINT UNSIGNED NULL | Exit code runner, không đủ thay cho per-worker status |
| `schema_version` | SMALLINT UNSIGNED NOT NULL | Phiên bản telemetry, mặc định 1 |

Index tối thiểu: `(started_at, tick_id)` và `(status, started_at)`. Không lưu stdout/stderr thô hoặc thông tin môi trường vào DB.

### 5.3 Sizing, retention và an toàn migration

- 3 worker × 1.440 phút = tối đa khoảng **4.320 task-run rows/ngày**; Cron thêm **1.440 tick rows/ngày** nếu mỗi phút một lần.
- Retention khuyến nghị khởi điểm **7 ngày**: khoảng **40.320 dòng telemetry** khi mỗi task đều được chạy mỗi phút; tải thực đo được sẽ khác do khóa, điều kiện task và Cron.
- Không delete theo request web. Việc dọn dữ liệu chạy bởi lệnh CLI bảo trì có điều kiện và giới hạn batch; nếu được đăng ký qua Tasks thì hiển thị task thứ tư (`m11-ops-telemetry-prune`) trong dashboard.
- Quy trình purge phải dùng index, giới hạn thời gian/batch, không xóa `notification_email_deliveries`, `notification_outbox`, `configuration_audit_events` hoặc lịch sử pháp lý khác.
- Migration chỉ thêm bảng/index (forward-only). Rollback production bằng **tắt feature + giữ bảng**, không tự DROP dữ liệu; việc xóa bảng yêu cầu quyết định riêng.
- `NotificationOperationsAuditReader` vẫn kỳ vọng **9/9 bảng M11 lõi**; telemetry có `available/health` độc lập. Việc thêm hai bảng không được làm báo cáo core chuyển thành 11/11 một cách âm thầm.

## 6. Ghi telemetry và Cron heartbeat

### 6.1 Adapter trong CLI workers

Không đổi `runOnce()` hoặc schema queue. Thêm một recorder mỏng quanh `NotificationsDispatch`, `NotificationsPushDispatch`, `NotificationsEmailDispatch`. Recorder thu thống kê trả về đã có (`claimed`, `sent`, `processed`, `retry`, `dead`, `cancelled`, `paused`, `lost_lease`) và chỉ lưu các khóa định lượng allowlist.

| Tình huống | Kết quả telemetry |
|---|---|
| `claimed=0`, command hoàn tất bình thường | `NO_WORK` (không phải lỗi) |
| Có xử lý, counters không báo bất thường | `SUCCESS` |
| Chỉ báo worker đang tạm dừng theo flag | `PAUSED` |
| Có `retry`/`dead`/`lost_lease` nhưng command hoàn tất | `WARNING` kèm counters |
| PHP exception / command thất bại | `FAILED` + mã lỗi đã chuẩn hóa |
| Có `RUNNING` quá TTL, không có `finished_at` | Hiển thị `INTERRUPTED`/`STALE_RUN` **dẫn xuất**, không tự gán thành `SUCCESS` |

Trường hợp `lost_lease` phải cảnh báo vì Email/Push là **at-least-once**: đã gửi ra ngoài nhưng ACK DB có thể không thành công, nguy cơ gửi trùng. Không dùng thông điệp “exactly-once email delivery”.

**Yêu cầu bất biến:** nếu lưu telemetry trước/sau worker gặp lỗi, không ném thêm ngoại lệ làm biến đổi kết quả đã xử lý. Đặt telemetry ngoài transaction của worker, ghi từ kết quả trong `finally`/after-run, kiểm soát lỗi riêng và không log bí mật. Tests phải chứng minh điều này, kể cả trường hợp SMTP đã accept nhưng telemetry update fail.

### 6.2 Cron wrapper đáng tin cậy

Thay `exec php spark tasks:run` hiện có bằng wrapper có thể chạy logic sau lệnh. Thiết kế shell (pseudocode, **chưa phải script triển khai**):

```sh
RUN_ID=$($PHP_BIN -r 'echo bin2hex(random_bytes(16));')
export DINKERP_M11_CRON_TICK_ID="$RUN_ID"
$PHP_BIN spark notifications:cron-tick --start --id="$RUN_ID" || true
$PHP_BIN spark tasks:run >> "$LOG_FILE" 2>&1
RC=$?
$PHP_BIN spark notifications:cron-tick --finish --id="$RUN_ID" --exit="$RC" || true
exit "$RC"
```

Khi triển khai phải xử lý **`set -e` đúng cách** để lệnh finish vẫn chạy nếu `tasks:run` trả khác 0; thêm trap khả dụng cho `TERM/INT/EXIT`, quoting, path/PHP_BIN và kiểm tra log directory như wrapper hiện tại. Nếu tiến trình bị `SIGKILL` hoặc máy chủ mất điện, bản ghi STARTED có thể không được finish: dashboard dùng TTL nhận diện. `cron-tick` là Spark CLI nội bộ, không expose HTTP.

- Lần chạy **trực tiếp** `php spark tasks:run` không sinh Cron tick; dashboard chỉ ghi worker runs có nguồn `MANUAL`/`UNKNOWN`. Nó không được cập nhật Cron health.
- Có `tick_id` hợp lệ do wrapper tạo thì worker run có thể liên kết về tick; phải kiểm tra tick tồn tại và trong khoảng thời gian hợp lý, không chỉ tin biến môi trường tùy ý.
- `runner_exit_code=0` chỉ xác nhận lệnh cha, không tự chứng minh cả ba worker không có lỗi: kết hợp với per-worker history.
- Nếu hosting không cho Cron chạy mỗi phút, phải hiệu chỉnh lịch đã được phê duyệt; không hiển thị “mỗi phút tự chạy” nếu Cron thực tế chỉ chạy mỗi 5 phút.

### 6.3 Cơ chế khi telemetry lỗi

Mức ưu tiên: **Email/Push/In-App delivery > telemetry**. Lỗi insert/update bảng telemetry phải được ghi một mã vận hành an toàn vào log và làm dashboard `UNKNOWN/DEGRADED`, nhưng **không chặn worker, không tự retry gửi và không ghi trạng thái queue sai**. Mọi thao tác chỉ đọc frontend vẫn phản hồi có lý do `TELEMETRY_UNAVAILABLE` thay vì giả vờ healthy.

## 7. Thuật toán Task Health

### 7.1 Các tín hiệu đầu vào độc lập

1. `configured_enabled`: từ 12 runtime switches DB; không tin trạng thái frontend cache.
2. `registered`: diễn giải đúng `NotificationTasksPlan::definitions()` cùng các điều kiện của Email; không chạy `tasks:list` shell từ HTTP.
3. `cron_observed`: tick bắt đầu/hoàn tất gần nhất từ **Cron wrapper**.
4. `worker_observed`: task-run gần nhất, kết quả và counters.
5. `expected_interval_seconds`: cấu hình triển khai đã đối chiếu Cron thực tế (mặc định 60 giây nếu cPanel cho phép).
6. Nếu có cơ chế `tasks:disable` toàn cục của thư viện, chỉ hiển thị sau khi xác nhận được đúng API/setting key cho bản v1.0.1; không tự đoán trạng thái.

### 7.2 Thứ tự phân loại (precedence)

| Điều kiện | Health | Reason code gợi ý |
|---|---|---|
| Task tắt chủ đích | `PAUSED` | `TASK_DISABLED` |
| Task ON nhưng Email Send/SMTP không đạt, không đăng ký | `BLOCKED` | `PREREQUISITE_NOT_MET` |
| Chưa có tick Cron từ khi bật telemetry | `UNKNOWN` | `CRON_NOT_OBSERVED` |
| Tick Cron quá ngưỡng 3 chu kỳ + jitter | `DEGRADED` | `CRON_STALE` |
| Tick mới nhưng runner hoặc worker thất bại | `FAILED` | `RUN_FAILED` |
| Có retry/dead/lost_lease hoặc queue overdue tăng | `DEGRADED` | `DELIVERY_WARNING` |
| Cron đủ mới, worker có NO_WORK hoặc SUCCESS | `HEALTHY` | `NO_WORK` / `WORK_DONE` |
| Thiếu bảng/đọc telemetry thất bại | `UNKNOWN` | `TELEMETRY_UNAVAILABLE` |

Trạng thái `HEALTHY` chỉ áp dụng khi có bằng chứng **Cron đủ mới và worker phù hợp**; không bao giờ suy từ task ON, không chỉ dựa `Last Run` của `tasks:list`. `NO_WORK` là kết quả worker bình thường; `UNKNOWN` không được hiển thị xanh.

Ngưỡng cảnh báo đề xuất: `stale_after = 3 × actual_cron_interval + jitter`, với jitter khởi đầu 30 giây; phải chốt sau khi đo hosting. Nếu job bị skip do `singleInstance`, không có worker run mới: chưa đủ bằng chứng khẳng định `FAILED`; thể hiện `NOT_OBSERVED` hoặc khóa đang chạy khi có dữ liệu đáng tin.

## 8. Thiết kế REST API — contract đề xuất chờ duyệt

API cùng namespace và envelope hiện có, chỉ cho phép GET. Đăng ký explicit trong `backend/app/Config/M11Routes.php` thuộc group `/api/v1`; cấm auto-route. `Cache-Control: private, no-store`, session Shield, PBAC ở server, binding parameter và field allowlist.

| Method + path | Mô tả |
|---|---|
| `GET /api/v1/notification-operations/overview` | Summary queue + Cron health + thời điểm snapshot, nhẹ hơn ops-audit. |
| `GET /api/v1/notification-operations/email-deliveries` | Danh sách Email receipts, filter + cursor pagination. |
| `GET /api/v1/notification-operations/tasks` | Task catalog, điều kiện đăng ký, tình trạng Cron và last run. |
| `GET /api/v1/notification-operations/task-runs` | Lịch sử task theo tên, khoảng thời gian và trạng thái. |

### 8.1 `overview`: ví dụ schema dữ liệu

```json
{
  "data": {
    "as_of_utc": "2026-10-26T12:52:31.000000Z",
    "queues": {
      "email": {"status_counts": {"SENT": 4}, "overdue_ready_15m": 0, "expired_leases": 0},
      "outbox": {"status_counts": {"PROCESSED": 7}, "overdue_ready_15m": 0, "expired_leases": 0},
      "push": {"status_counts": {"SENT": 1}, "overdue_ready_15m": 0, "expired_leases": 0}
    },
    "cron": {"health": "UNKNOWN", "reason": "CRON_NOT_OBSERVED", "last_tick_at": null},
    "telemetry_available": false
  },
  "meta": {"request_id": "..."}
}
```

Đây là **hợp đồng mẫu minh họa**, không phải response đang tồn tại. Trường `UNKNOWN` phản ánh chưa có Cron heartbeat, không mâu thuẫn với log thủ công 4/4 Email đã nhận.

### 8.2 `email-deliveries`

Filter cho phép: `status`, `event_type` (allowlist), `from_utc`, `to_utc`, `page_size` mặc định 20 tối đa 50, `cursor` đã ký; sort cố định `(created_at DESC, id DESC)`. Cursor HMAC ràng buộc người xem, bộ lọc, page_size, boundary và thời hạn; thay đổi filter phải reset cursor. Không dùng OFFSET sâu cho bảng lớn.

Fields trả về allowlist: `id` (string), `event_type`, `status`, `attempts` (number), `created_at_utc`, `available_at_utc`, `sent_at_utc` (nullable), `last_error_code` (nullable), `overdue` (boolean dẫn xuất), `leased_until_utc` nếu `LEASED` và có nhu cầu vận hành. **Không SELECT `*` đưa ra API, không đưa `recipient_user_id`, `event_key`, `params_json`, địa chỉ, tên, token.**

Envelope danh sách: `{data:{items:[...],next_cursor:null|string},meta:{request_id:"..."}}`. Nếu chưa có receipt, trả `items: []`, không báo lỗi.

### 8.3 `tasks` và `task-runs`

`tasks.items[]`: `name`, `schedule`, `configured_enabled`, `registered`, `registration_reason`, `health`, `health_reason`, `last_started_at_utc`, `last_finished_at_utc`, `last_result`, `last_counters`, `last_cron_tick_at_utc`. Task OFF vẫn xuất hiện để phân biệt **disabled** với **missing**.

`task-runs.items[]`: `id`, `task_name`, `origin`, `started_at_utc`, `finished_at_utc`, `duration_ms`, `status`, `counters`, `error_code`. Có filter và cursor tương tự Email Queue. Cron ticks chi tiết giai đoạn đầu có thể chỉ hiển thị trong Task detail qua `tasks`, không cần endpoint mở riêng.

### 8.4 Lỗi và tiêu chuẩn response

- `401` chưa đăng nhập; `403` PBAC không cho VIEW; `400/422` filter/cursor không hợp lệ; `503` không thể đọc telemetry trọng yếu. Mã lỗi mới phải được chốt trong OpenAPI và bảng dịch vi/en, tránh in SQL/stack trace.
- Do dữ liệu nhạy cảm về vận hành, cấm response caching, cấm URL ngoài allowlist và cấm debug endpoint public.
- Các câu hỏi nhanh theo Overview chỉ dùng aggregate query và không gọi SMTP/Push provider, không giải mã endpoint Push, không chạy tasks CLI.

## 9. Thiết kế PBAC & security gate

**Đề xuất (chưa được phê duyệt):** Resource `notification_operations` (domain `SYSTEM`, state mode độc lập `ALL_STATES`), Action `view`, Base Permission `notification_operations.view`. Quyền chỉ cấp qua quy trình quản trị truy cập hiện hữu; không auto-grant cho admin hoặc mọi user.

Để triển khai Resource mới phải cập nhật đồng bộ **Resource catalog/migration**, `UnifiedAccessService::APPROVED_READ_DOMAINS`, `ResourceStateCatalog`, fixture, OpenAPI và frontend `hasBasePermission`/navigation. Việc có Base Permission vẫn phải qua PBAC; không tạo shortcut admin.

Nếu Change Control chưa phê duyệt Resource mới, **dừng chức năng UI/API** hoặc xin phê duyệt tái dùng `notification_setting.view` tạm thời. Không tự cho phép quyền từ client, không tự thêm Resource/Action chỉ để vượt test.

Threat model tối thiểu: IDOR vượt quyền, cursor giả/tamper, query rộng quá giới hạn, tiết lộ email/user/payload/SMTP exception, cache thông tin operator, race hiển thị khi session thay đổi, shell command injection, health spoof bằng manual CLI. API phải fail closed; telemetry write CLI có thể best effort nhưng phải có mã chẩn đoán an toàn.

## 10. Thiết kế frontend

### 10.1 Vị trí và hành vi

Route: `/system/notification-operations`; menu nhóm Hệ thống, sau Configuration Settings; quyền hiển thị `notification_operations.view` nếu được phê duyệt. Title VI: **Giám sát thông báo và tác vụ**; EN: **Notification Operations**. Dùng các thành phần/list/table/design tokens sẵn có của Vue, không tạo hệ CSS thứ hai.

**Tab Tổng quan:** 6 ô chỉ số (Email PENDING, RETRY, DEAD, OVERDUE, SENT hôm nay, Cron health), nhóm trạng thái In-App/Push/Email. Có `as_of_utc` và nhãn “snapshot”; không biến câu “SMTP accepted” thành “Inbox delivered”.

**Tab Email Queue:** bảng ID, event type, status, attempts, available, sent, error code; lọc, phân trang, responsive; trạng thái `LEASED` phải hiển thị rõ; tooltip mã lỗi đã chuẩn hóa. Không có drawer hiển thị PII trong v1.

**Tab Tasks & Cron:** lịch khai báo và điều kiện đăng ký, bảng lần chạy gần nhất, trạng thái `NO_WORK`, counters, cửa sổ lịch sử 7 ngày, timestamp Cron heartbeat; ghi rõ “manual executions không chứng minh Cron”. Task bảo trì mới phải được nhận diện riêng nếu có.

Refresh mặc định mỗi 30 giây **chỉ khi trang đang active/visible**; mọi request có `AbortController`, chặn response cũ theo `auth.getSessionGeneration()`; chuyển tab/filter/đăng xuất hủy request. Không tạo WebSocket. User có nút **Làm mới**; nút **Cấu hình** chỉ link trang hiện có nếu có quyền tương ứng.

### 10.2 UI states

Loading skeleton; empty result; API unavailable/telemetry disabled; 403 forbidden; stale data banner; cursor expired (reset filters); network error retry. Mọi trạng thái health phải có nhãn và lý do bằng chữ, không chỉ khác biệt màu sắc. UTC lưu/đọc nhất quán; giao diện định dạng theo timezone người dùng và có thể xem UTC.

### 10.3 File/Module dự kiến

| Nhóm | Vị trí đề xuất |
|---|---|
| Backend Controller | `backend/app/Modules/Notifications/NotificationOperationsController.php` |
| Reader | `.../NotificationOperationsReadService.php` và repository chỉ SELECT |
| Telemetry | `.../NotificationTaskRunRecorder.php`, `.../NotificationCronTickRecorder.php` |
| Rules | `.../NotificationTaskHealthRules.php` |
| CLI | `backend/app/Commands/NotificationsCronTick.php` và lệnh prune an toàn |
| Routes/OpenAPI | `backend/app/Config/M11Routes.php`, `contracts/openapi/openapi.yaml` |
| Frontend | `frontend/src/features/notifications/pages/NotificationOperationsView.vue` |
| FE Service | `frontend/src/services/notificationOperations.ts` |
| i18n/Nav | `frontend/src/i18n/locales/{vi,en}.ts`, `frontend/src/app/navigation.ts`, `frontend/src/router/routes.ts` |
| Tests | `backend/tests/m11/{Unit,Integration,Reliability}` và Vitest page/service tests |

Đây là **gợi ý tên file**, không phải cam kết cấu trúc mới đã tồn tại. Trước coding cần chọn lớp/namespace tuân theo quy tắc module và controller/repository hiện tại.

## 11. Kế hoạch triển khai có cổng nghiệm thu

| Phase | Thay đổi | Gate bắt buộc |
|---|---|---|
| **G0 — Contract/Change Control** | Phê duyệt Resource, 2 bảng telemetry, DTO, API, status enums, retention, Cron capabilities. | Có quyết định chính thức; không `CONTRACT_PENDING`. |
| **M11.7A — Read API** | Queue summary, Email list, filters, signed cursor, PBAC, OpenAPI, integration tests. | GET read-only; không lộ PII; source queue không đổi. |
| **M11.7B — Telemetry** | 2 bảng + instrumentation worker + Cron wrapper + bounded retention/health. | Lỗi telemetry không đổi luồng gửi; manual vs Cron phân biệt; restart/crash test. |
| **M11.7C — Frontend** | 3 tab, i18n, nav, filters, paging, auto refresh, health semantics. | Vitest, type-check, OpenAPI generate/lint/check, production build PASS. |
| **M11.7D — Operational Acceptance** | Deploy DEV clone → Staging → Hosting, Cron thật, log rotation, cleanup, operator runbook. | 3–5 tick tự động liên tiếp có heartbeat; no false healthy; no unplanned sends. |

### 11.1 Thứ tự migration / deployment

1. Backup DB và file config; xác minh đúng môi trường và `database.tests=erp_test` trước integration/reliability.
2. Migration **additive** tạo bảng telemetry; verify index và prefix, không sửa bảng Email. Migration Resource/Action tách riêng nếu được phê duyệt; **không cấp quyền tự động**.
3. Deploy backend API chỉ đọc với telemetry default OFF; kiểm tra 401/403 và queue snapshot.
4. Bật telemetry trong staging, sau đó thay Cron wrapper có heartbeat; chạy test manual trước, kiểm tra 1 tick Cron thật sau.
5. Deploy frontend, cấp quyền cụ thể cho nhóm operator; xác minh không ai có quyền ngoài dự kiến.
6. Bật retention cleanup, đo kích thước bảng và tải MariaDB; nghiệm thu production rồi ghi biên bản.

### 11.2 Rollback và feature gates

Dùng deployment switches server-side mặc định OFF cho telemetry/Operations UI (tên cụ thể chốt trong contract). Khi xảy ra lỗi, rollback **API/UI/telemetry instrumentation** về phiên bản đã nghiệm thu, trả Cron wrapper về lệnh `tasks:run` cũ nếu cần; giữ hai bảng đã thêm để tránh data loss và migration rollback nguy hiểm. Tắt telemetry không được tự tắt Email/Push/In-App. Queue đang SENT/PENDING không bị thay đổi bởi rollback.

**Cảnh báo:** không dùng `php spark migrate:rollback` để xóa bảng telemetry trên production; không chạy test ghi fixture trên DB ERP thật.

## 12. Ma trận kiểm thử và tiêu chí chấp nhận

| Test ID | Tình huống | Kỳ vọng |
|---|---|---|
| AT-01 | GET Email list với VIEW đúng | 200 + cột allowlist, không có user ID/email/payload |
| AT-02 | Không có Base Permission / ABSOLUTE_DENY | 403, không leak count/list |
| AT-03 | Filter sai, page size >50, cursor bị sửa | 400/422, không SQL injection hoặc full scan |
| AT-04 | Empty Email Queue | 200 `items=[]`, không FAILED |
| AT-05 | `LEASED` quá hạn / PENDING overdue | Dashboard hiển thị đúng derived flags |
| AT-06 | Chỉ chạy `php spark tasks:run` thủ công | Worker run có thể hiện; **Cron UNKNOWN** |
| AT-07 | Cron wrapper chạy một tick thật | Tick STARTED/FINISHED; worker runs liên kết tick |
| AT-08 | Cron bị dừng > ngưỡng | `DEGRADED/CRON_STALE`, không HEALTHY |
| AT-09 | Worker `claimed=0` | `NO_WORK`, không đánh dấu FAILED |
| AT-10 | SMTP accepted, ghi telemetry fail | Email chỉ gửi một lần theo worker logic; không retry do telemetry |
| AT-11 | Worker có retry/dead/lost_lease | `WARNING` và counters chính xác; không hiển thị raw exception |
| AT-12 | Process chết sau tick STARTED / run RUNNING | Stale detection, không ghi giả SUCCESS |
| AT-13 | Task OFF hoặc Email thiếu prerequisite | PAUSED hoặc BLOCKED; không coi là service outage |
| AT-14 | Hai worker đồng thời / singleInstance contention | Không trùng nghiệp vụ; chỉ kết luận skip khi có chứng cứ |
| AT-15 | CLI prune 7 ngày và migration rollback drill | Không xóa queue/audit; retention bounded; disable feature an toàn |
| AT-16 | Refresh 30s, tab change, logout | Abort/stale-session guard; không hiển thị data phiên cũ |
| AT-17 | No HTTP mutating operations | Không có Run/Retry/Delete; GET không gửi SMTP/Push hay ghi DB |
| AT-18 | Cron hosting 3–5 tick liên tiếp | Heartbeat và worker runs cùng mốc; logs/DB tương hợp |

Bắt buộc chạy đầy đủ bộ **M11 unit/integration/reliability** và **frontend Vitest/type-check/build/OpenAPI** ở mỗi phase ảnh hưởng. AT-10 là regression gate cao nhất vì telemetry không được làm tăng nguy cơ duplicate external send. Audit cuối vẫn cần `notifications:ops-audit --strict` PASS.

### 12.1 Ngân sách tải ban đầu (cần benchmark)

- Page size 20, tối đa 50; không dùng API không giới hạn.
- Poll 30 giây cho trang active; ẩn tab thì dừng; không dùng polling trên mọi màn hình ERP.
- Đặt mục tiêu p95 GET overview dưới 800 ms ở bộ dữ liệu kiểm thử đủ lớn, **chỉ là tiêu chí đề xuất, chưa đo trên Shared Hosting**.
- Kiểm thử tải với ít nhất 100.000 Email receipt lịch sử và khoảng 40.000 telemetry rows; đối chiếu EXPLAIN, indexes và query count.
- Lệnh Cron wrapper vẫn phải hoàn thành trong ngân sách shared hosting; giữ `--once`, batch và `singleInstance()` như code hiện tại.

## 13. Rủi ro, quyết định chờ duyệt và câu hỏi triển khai

| Mã | Rủi ro / phụ thuộc | Biện pháp |
|---|---|---|
| R-01 | Thông tin Cron bị suy ra sai từ scheduler registration | Heartbeat độc lập từ wrapper; `UNKNOWN` khi chưa có tick. |
| R-02 | Ghi telemetry gây retry Email sau SMTP | Best-effort, độc lập transaction, AT-10 và fault injection. |
| R-03 | Bảng telemetry phình to | 7-day retention, purge theo batch và chỉ số dung lượng. |
| R-04 | PBAC chưa có Resource mới | Gate G0/G1 bắt buộc; không auto-grant / không bypass admin. |
| R-05 | Hosting chỉ hỗ trợ Cron 5 phút | Điều chỉnh lịch và health threshold theo khả năng thực; không giả định 1 phút. |
| R-06 | Crash/kill giữa tick start và finish | RUNNING stale TTL và origin check. |
| R-07 | M11 core audit bị thay đổi ý nghĩa 9/9 | Không sửa tập bảng lõi; telemetry kiểm riêng. |
| R-08 | Báo email delivery “đến Inbox” khi chỉ `SENT` | Phân biệt SMTP accepted với provider-delivery; xác minh Inbox ngoài hệ thống. |

**Cần phê duyệt trước khi bắt đầu code:** tên Resource/Action và mô hình cấp quyền; hai bảng telemetry/index; 4 API GET và DTO; retention 7 ngày; wrapper Cron có quyền ghi DB; tần suất Cron hosting thực tế; có hay không thêm task bảo trì trong scheduler. Khi các quyết định này được ký, thiết kế đủ chi tiết để phân chia patch M11.7A–D và tiêu chí đóng module.

## 14. Phụ lục — vết kiểm chứng và tài liệu tham khảo

**Nguồn mã DinkERP đã rà soát** (ERP v1.7.0 + các patch M11.4A–M11.6C):

- **Schema:** `backend/app/Database/Migrations/2026-10-26-000019`, `000021`, `000022` (các tệp CreateNotification…).
- **Scheduler:** `backend/app/Config/Tasks.php`, `backend/app/Modules/Notifications/NotificationTasksPlan.php`, `backend/tools/m115_tasks_cron.sh`.
- **Workers & audit:** `NotificationEmailWorker.php`, `NotificationOperationsAuditReader.php` và các command `Notifications{Dispatch,EmailDispatch,PushDispatch,EmailBacklog}.php` trong `backend/app`.
- **PBAC / routes:** `backend/app/Modules/Access/{UnifiedAccessService,ResourceStateCatalog}.php`; `backend/app/Config/M11Routes.php`.
- **Frontend:** `frontend/src/features/settings/pages/ConfigurationSettingsView.vue`, `frontend/src/app/navigation.ts`, `frontend/src/router/routes.ts`.
- **Change control:** `.codex/skills/dinkerp-implementation/references/project-invariants.md`.

**Tài liệu upstream:** CodeIgniter Tasks [configuration](https://tasks.codeigniter.com/1.0/configuration/), [CLI commands](https://tasks.codeigniter.com/1.0/cli-commands/), [basic usage](https://tasks.codeigniter.com/1.0/basic-usage/); package [codeigniter4/tasks v1.0.1](https://packagist.org/packages/codeigniter4/tasks).

**Hạn chế:** thiết kế đối chiếu bản mã ghép từ release và patch đã cung cấp, không phải kiểm tra trực tiếp working tree Windows, DB hoặc Cron hosting. API, Resource và hai bảng telemetry đều là **đề xuất chưa triển khai**.
