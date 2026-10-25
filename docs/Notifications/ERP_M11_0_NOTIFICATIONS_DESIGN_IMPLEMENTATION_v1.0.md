# M11.0 — THIẾT KẾ & KẾ HOẠCH TRIỂN KHAI NOTIFICATIONS

**DinkERP · Design / Implementation Specification · v1.0 · 25/10/2026**  
**Trạng thái:** DESIGN READY FOR REVIEW — chưa có migration hoặc mã Notifications được triển khai.  
**Nền đối chiếu:** ERP v1.5.0 và các milestone M10 đã trao đổi (M10.4A: type-check, 14/14 Vitest và 33/33 Playwright đã PASS trong log người dùng; production build M10.4A chưa được xác nhận).

## 0. Tóm tắt quyết định

**Mục tiêu:** xây dựng Notifications dùng chung cho ERP, bảo đảm người nhận đúng, không lộ dữ liệu, không mất sự kiện khi nghiệp vụ commit, thao tác Inbox nhanh và có cấu hình cá nhân chịu sự điều khiển của chính sách hệ thống.

**Các quyết định đã thống nhất:**

1. **Vị trí cấu hình:** thêm tab `Thông báo` tại `/system/display-settings?tab=notifications` và `/profile/display-preferences?tab=notifications`. Giữ nguyên URL và tab `display` mặc định. Trang `/notifications` là Inbox, không phải trang cấu hình mới.
2. **Mỗi khóa cấu hình có hai trường quản trị:** `default_value` (Bật/Tắt) và `allow_user_override` (Có/Không). User chỉ được đặt `true`, `false` hoặc `null` (kế thừa) khi được cho phép.
3. **Giá trị có hiệu lực:** tính ở backend, không tin giá trị frontend. Cấu hình bắt buộc/khóa chính sách được áp dụng trước tùy chọn cá nhân. Không tự kích hoạt lại lựa chọn cũ sau một chu kỳ khóa rồi mở khóa.
4. **Bảo mật:** Shield cung cấp User ID; UnifiedAccessService/PBAC cấp quyền quản trị `notification_setting.view/edit` và quyền đối tượng đích. Inbox và Preferences cá nhân luôn được ràng buộc theo User đăng nhập.
5. **Hạ tầng MVP:** MariaDB/InnoDB + Transactional Outbox + Spark worker + REST polling 60 giây khi tab đang hoạt động. Không bắt buộc Redis, WebSocket, Email hoặc Push.
6. **Sự kiện đầu tiên:** Import Job hoàn tất (bao gồm thành công, có lỗi và thất bại). Workflow được tích hợp sau, không giả định đã có hàng đợi người phê duyệt.
7. **API là phần mở rộng mới:** không thay đổi ngầm các operation đã đóng băng của v1.2.4; đăng ký hợp đồng OpenAPI, codegen và route verification mới.

**Hai ranh giới không được đánh đồng:** Notification không phải quyền truy cập tài liệu; `read_at`/`dismissed_at` không phải Audit Log. `allow_user_override` là **quyền tùy chỉnh nhận thông báo**, không phải quyền PBAC.

## 1. Rà soát source và hiệu chỉnh thiết kế

| Thành phần đã kiểm tra | Bằng chứng trong source | Tác động thiết kế |
|---|---|---|
| AppShell Notification | `frontend/src/components/app-shell/useShellHeader.ts` có 2 thông báo mẫu và `notificationsRead` trong bộ nhớ | Thay mock bằng Pinia store; không dùng trạng thái mock như dữ liệu thật |
| System Display Settings | `frontend/src/features/settings/pages/DisplaySettingsView.vue` có `SYSTEM`/`ORGANIZATION`; quyền `display_setting.view/edit` | Tab Notification dùng service/API/quyền riêng; MVP chỉ SYSTEM, không áp dụng ngầm override tổ chức |
| Personal Display Preferences | `frontend/src/features/account/pages/DisplayPreferencesView.vue` và `stores/displaySettings.ts` lưu tùy chọn hiển thị cục bộ | Notifications Preferences phải lưu DB; worker không thể đọc LocalStorage |
| API contract | `contracts/openapi/openapi.yaml`, `frontend/package.json` có `openapi:lint`, `openapi:generate` | Thêm hợp đồng mở rộng, sinh SDK; giữ envelope `{data, meta}` |
| PBAC | `backend/app/Modules/Access/UnifiedAccessService.php`, `BasePermissionReader.php` | Đăng ký resource/action thật, không chỉ kiểm tra cờ frontend |
| Import Job | `backend/app/Modules/Import/Core/ImportJobService.php` ghi trạng thái cuối qua `setJob()` riêng | Khi tích hợp phải đặt final-status UPDATE + Outbox INSERT trong **cùng transaction mới**; không bao cả import nhiều dòng trong một transaction |
| Workflow | `BusinessWorkflowService::execute()` có transaction và khóa hàng nghiệp vụ | Ghi outbox sau UPDATE, trước COMMIT, bảo toàn rollback |
| HTTP | `ApiResponder` / `ApiEnvelope` chuẩn hóa lỗi, `X-Request-ID`, `Content-Language` | Tuân thủ envelope/errors/header và CSRF hiện có |
| Cơ sở dữ liệu triển khai | Không có kết quả `SHOW CREATE TABLE` hay phiên bản MariaDB trong bộ source | DDL/index/FK và phương pháp claim worker cần xác minh trên DEV clone trước áp dụng |

**Điểm điều chỉnh sau lần review cuối:** đề xuất cũ nói “hai bảng lõi” chỉ phù hợp cho delivery. Khi chốt cả System Settings, User Preferences và Audit, bản thiết kế đầy đủ cần **năm bảng**: 2 bảng phân phối, 2 bảng cấu hình và 1 bảng audit. Không sửa bảng Display Settings hiện có.

## 2. Phạm vi nghiệp vụ

### 2.1 Trong MVP M11.1

- Chuông AppShell: số chưa đọc, 10 thông báo gần nhất, đánh dấu đã đọc; không tự đánh dấu khi mở.
- Inbox `/notifications`: lọc `all` / `unread`, cursor pagination; mở đối tượng đích qua route registry.
- Tab cấu hình quản trị: mặc định Bật/Tắt và cho phép/cấm ghi đè **theo từng khóa** của kênh In-App.
- Tab cá nhân: trạng thái `Kế thừa / Bật / Tắt`; khóa điều khiển nếu admin không cho ghi đè; nút `Dùng mặc định`.
- Worker bền vững + retry + chống tạo trùng; sự kiện kết thúc Import gửi tới người tạo Import Job (nếu hợp lệ và còn khả dụng).
- Dashboard/số đếm đúng theo tài khoản; clear state sau logout/đổi User.

### 2.2 Ngoài MVP

- Email, browser Push, SMS, SSE/WebSocket; các công tắc kênh chưa hỗ trợ không được hiển thị như tính năng đang hoạt động.
- Broadcast toàn hệ thống; nhắc việc theo lịch; thông báo cho nhóm phê duyệt chưa có resolver nghiệp vụ tin cậy.
- Người dùng chọn tần suất/digest, thời gian im lặng hoặc ngôn ngữ riêng cho Email; có thể bổ sung khi ra mắt kênh tương ứng.
- Cấu hình Notification theo Organization. Nếu cần sau này: tách thiết kế scope `SYSTEM → ORGANIZATION → USER` thành ADR khác, không áp dụng quy tắc Display Settings hiện có một cách tự động.

## 3. Event Catalog và khả năng phân phối

| Setting key (event + channel) | Mặc định | User ghi đè | Bắt buộc? | Kỳ |
|---|---:|---:|---:|---|
| `import.completed.in_app` | true | true | Không | M11.1 |
| `import.completed_with_errors.in_app` | true | true | Không | M11.1 |
| `import.failed.in_app` | true | true | Không | M11.1 |
| `workflow.action_required.in_app` | true | false | Không | M11.2, chỉ khi xác định chắc chắn người nhận |
| `security.permission_changed.in_app` | true | false | Có | M11.2, khi có nguồn sự kiện phù hợp |
| `import.completed.email` | false | true | Không | M11.3; `capability=UNAVAILABLE` đến khi bật Email |

- Các khóa được **đăng ký trong server-side registry**: kiểu, event, channel, có hỗ trợ hay chưa, bắt buộc hay không, mặc định được đề xuất; không cho tạo key tùy ý qua PATCH.
- Một số cảnh báo bảo mật bắt buộc có `default_value=true`, `allow_user_override=false`. Backend từ chối tắt chúng, kể cả qua API quản trị.
- “Cho phép User ghi đè” không đồng nghĩa “bật kênh”. Kênh chưa triển khai hoặc bị tắt ở tầng capability luôn có hiệu lực `false`.
- Thông báo chỉ được phát theo **sự kiện có ý nghĩa**, không cho mọi thao tác CRUD thông thường.

## 4. Quy tắc System → User → Effective

### 4.1 Định nghĩa

Mỗi cấu hình có chính sách hệ thống `P(key) = (default_value, allow_user_override, revision, override_epoch, mandatory)` và tùy chọn user `U(user_id,key) = (override_value, override_epoch)`; không có bản ghi user tương đương `null`.

| Default | Cho ghi đè | Giá trị user | Effective | Nguồn |
|---|---|---|---|---|
| true | false | bất kỳ | true | SYSTEM_LOCKED |
| false | false | bất kỳ | false | SYSTEM_LOCKED |
| true | true | null | true | SYSTEM_DEFAULT |
| true | true | false | false | USER |
| false | true | true | true | USER |
| false | true | null | false | SYSTEM_DEFAULT |

Nếu `mandatory=true`, dữ liệu hợp lệ chỉ là `default_value=true` và `allow_user_override=false`. Nếu `capability=UNAVAILABLE`, `effective_value=false`, nguồn `CHANNEL_UNAVAILABLE` (không nhận thay đổi người dùng).

### 4.2 Pseudocode chuẩn

```text
resolve(user, key):
  def = catalog.requireKnown(key)
  if not def.channelAvailable: return (false, CHANNEL_UNAVAILABLE)
  policy = settings.requireValidRow(key)
  if def.mandatory: return (true, MANDATORY)
  if not policy.allow_user_override:
      return (policy.default_value, SYSTEM_LOCKED)
  pref = user_preferences.find(user.id, key)
  if pref is absent OR pref.override_epoch != policy.override_epoch:
      return (policy.default_value, SYSTEM_DEFAULT)
  return (pref.override_value, USER)
```

### 4.3 Ngăn khôi phục ghi đè cũ khi mở khóa

Khi admin đổi `allow_user_override: true → false`, tăng `override_epoch` của key trong **cùng transaction** cập nhật chính sách và audit. Bản ghi tùy chọn cũ có thể giữ để điều tra nhưng epoch cũ sẽ không còn hợp lệ. Khi chuyển lại `false → true`, hệ thống vẫn kế thừa default cho tới khi User tự lưu lại cấu hình tại epoch hiện tại. Đổi `default_value` trong khi vẫn mở ghi đè **không** xóa lựa chọn cá nhân đang hợp lệ.

**Ví dụ:** User A đã `false`, admin khóa với default `true`, sau đó mở khóa. A tiếp tục nhận `true` (kế thừa), không tự chuyển về `false`. A có thể chủ động tắt lại.

### 4.4 Đồng thời và tính nhất quán

- PATCH quản trị yêu cầu `expected_revision` theo từng key; backend khóa row, so sánh revision và commit thay đổi + audit cùng transaction. Lệch revision → **409**, không ghi đè dữ liệu người khác.
- PATCH cá nhân yêu cầu `expected_override_epoch`; kiểm tra policy hiện hành tại thời điểm ghi, epoch phải khớp; khóa → **403** (hoặc 409 khi epoch stale), không phụ thuộc trạng thái nút trong UI.
- Bulk PATCH nguyên tử: **tất cả thành công hoặc tất cả rollback**; trùng key trong một request → 422.
- Worker đọc effective tại **thời điểm phân phối**; thay đổi sở thích không hồi tố xóa Inbox đã phân phối. Sự kiện bị chặn tại thời điểm dispatch được đánh dấu `SUPPRESSED`, không tự phát lại khi bật cấu hình.

## 5. Kiến trúc ứng dụng

```text
                  ERP business services
                (Import Job / Workflow / ...)
                           |
                  BUSINESS TRANSACTION
                  update domain + outbox
                           |
               notification_outbox (DB)
                           |
                 Spark Worker (leased)
              validate event + recipient
               load effective preferences
                           |
                  user_notifications
                           |
                  REST (Shield session)
                summary / list / mark-read
                           |
                   Pinia notificationStore
                  /                     \
            AppShell bell            /notifications

        Admin policy tab → notification_settings → Audit
        User preference tab → notification_user_preferences
```

**Quy tắc giao dịch:** Không gửi HTTP/Email trong transaction nghiệp vụ. Worker chỉ xử lý những event đã commit. Mỗi event có `event_key` bất biến; mỗi người nhận chỉ có một notification theo `(recipient_user_id, event_key)`.

## 6. Mô hình dữ liệu mục tiêu (5 bảng)

**Lưu ý:** đây là *logical schema*, chưa phải SQL migration chạy trên môi trường thật. Tên cột, độ dài FK, collation và kiểu ID phải đối chiếu `SHOW CREATE TABLE` của Shield và MariaDB DEV clone trước khi viết migration.

| Bảng | Trường chủ yếu | Ràng buộc/index cần có |
|---|---|---|
| `notification_settings` | `key`, `default_value`, `allow_user_override`, `revision`, `override_epoch`, `updated_by`, timestamps | PK/UNIQUE `(key)`; rule mandatory được service kiểm tra |
| `notification_user_preferences` | `id`, `user_id`, `key`, `override_value`, `override_epoch`, `updated_at` | UNIQUE `(user_id,key)`; FK Shield User nếu DB tương thích |
| `notification_outbox` | `id`, `event_key`, `event_type`, `source_type`, `source_id`, `payload_json`, `status`, `attempts`, `available_at`, `lease_token`, `leased_until`, `last_error_code`, timestamps | UNIQUE `event_key`; index `(status,available_at,id)`; lease recoverable |
| `user_notifications` | `id`, `recipient_user_id`, `event_key`, `type`, `priority`, `title_key`, `body_key`, `params_json`, `target_type`, `target_id`, `read_at`, `dismissed_at`, `created_at`, `expires_at` | UNIQUE `(recipient_user_id,event_key)`; index `(recipient_user_id,created_at,id)`, `(recipient_user_id,read_at,created_at,id)` |
| `notification_setting_audit` | `id`, `setting_key`, `operation`, `before_json`, `after_json`, `actor_user_id`, `request_id`, `created_at` | index `(setting_key,created_at)`; immutable/append-only |

### 6.1 Quy tắc dữ liệu

- ID người nhận lấy từ Shield User hiện hành; không dùng `recipient_user_id` trong request của User.
- Chuỗi định danh `event_key` được tính tại backend, ví dụ `import-job:{jobId}:terminal`; không lấy từ form.
- `target_type` là enum/registry route được cho phép; `target_id` là ID từ nguồn tin cậy; không lưu URL tùy ý.
- `params_json` chỉ chứa dữ liệu phi nhạy cảm hoặc ID tham chiếu tối thiểu, giới hạn kích thước. Không lưu token, mật khẩu, dữ liệu tài chính chi tiết hay HTML.
- Timestamp lưu UTC, định dạng trao đổi API ISO 8601; `DATETIME(6)` là đề xuất cần kiểm tra tương thích.
- Chính sách retention khởi điểm đề xuất: Inbox 90 ngày, Outbox terminal 30 ngày và Audit ≥365 ngày; xác nhận lại cùng yêu cầu pháp lý/vận hành trước triển khai.
- Không xóa lịch sử thông báo khi người dùng tắt một key. Khi User bị vô hiệu hóa, không gửi tiếp và không hiển thị Inbox qua phiên không hợp lệ.

## 7. API Contract — 8 operations dự kiến

Tất cả URI có tiền tố `/api/v1`, dùng Shield session, CSRF cho mutation, `ApiResponder`/`ApiEnvelope` hiện có. Phần dưới là **đề xuất hợp đồng M11**, không phải endpoint đã tồn tại.

| Method | Path | Nội dung | Quyền |
|---|---|---|---|
| GET | `/notification-settings` | Catalog + chính sách System | `notification_setting.view` + PBAC |
| PATCH | `/notification-settings` | Sửa nhiều key nguyên tử và audit | `notification_setting.edit` + PBAC |
| GET | `/notifications/preferences` | Giá trị System, User và Effective của chính mình | Shield session |
| PATCH | `/notifications/preferences` | Đặt/Reset override của chính mình | Shield session + policy hiện hành |
| GET | `/notifications/summary` | Số chưa đọc, trạng thái cập nhật | Shield session |
| GET | `/notifications` | Inbox lọc/cursor/page_size | Shield session |
| POST | `/notifications/{id}/actions/mark-read` | Đọc một thông báo của mình; idempotent | Shield session + ownership |
| POST | `/notifications/actions/mark-all-read` | Đọc các thông báo hiện hữu trước cutoff | Shield session + ownership |

### 7.1 GET/PATCH quản trị

```json
{
  "data": [{
    "key": "import.completed.in_app",
    "default_value": true,
    "allow_user_override": true,
    "mandatory": false,
    "capability": "AVAILABLE",
    "revision": 3,
    "override_epoch": 2
  }],
  "meta": {"request_id": "..."}
}
```

```json
{
  "changes": [{
    "key": "import.completed.in_app",
    "default_value": false,
    "allow_user_override": false,
    "expected_revision": 3
  }]
}
```

PATCH trả danh sách row mới + `revision` tăng. Mỗi thay đổi thực sự ghi `notification_setting_audit`. Key lạ/duplicate/vi phạm mandatory → 422; không có quyền → 403; revision cũ → 409. `display_setting.edit` không được dùng thay thế `notification_setting.edit`.

### 7.2 GET/PATCH cá nhân

```json
{
  "data": [{
    "key": "import.completed.in_app",
    "system_default": true,
    "allow_user_override": true,
    "user_override": false,
    "effective_value": false,
    "effective_source": "USER",
    "override_epoch": 2,
    "locked": false
  }],
  "meta": {"request_id": "..."}
}
```

```json
{
  "changes": [{
    "key": "import.completed.in_app",
    "value": null,
    "expected_override_epoch": 2
  }]
}
```

`value=null` xóa override đang có hiệu lực và trở về kế thừa. Không cho client gửi `user_id`, `effective_value`, `allow_user_override` hay `default_value` tới API cá nhân. Trả 403 khi key đang khóa, 409 khi epoch không còn hợp lệ, 422 khi key/kênh không hợp lệ.

### 7.3 Inbox / Summary

```json
{
  "data": {
    "items": [{
      "id": "581",
      "type": "import.completed_with_errors",
      "priority": "NORMAL",
      "title_key": "notifications.import.completedWithErrors.title",
      "body_key": "notifications.import.completedWithErrors.body",
      "params": {"error_rows": 8},
      "target": {"type": "import_job", "id": "125"},
      "read_at": null,
      "created_at": "2026-10-25T09:00:00Z"
    }],
    "next_cursor": null
  },
  "meta": {"request_id": "..."}
}
```

- `GET /notifications?filter=unread&page_size=20&cursor=...`; `page_size` mặc định 20, tối đa 50; cursor opaque/signed theo `(created_at,id)` và ràng buộc người dùng/bộ lọc. Không offset toàn bảng.
- `GET /notifications/summary` trả `{unread_count: N}`; đếm bằng DB, không gọi GET từng notification.
- `mark-read`: update `WHERE id=? AND recipient_user_id=currentUser AND read_at IS NULL`, đã đọc thì trả thành công idempotent; ID ngoài phạm vi trả 404 để không lộ sự tồn tại.
- `mark-all-read`: lấy `cutoff = CURRENT_TIMESTAMP(6)` từ DB lúc nhận request, chỉ update thông báo có `created_at < cutoff` và thuộc chính User; thông báo tạo sau cutoff không bị đánh dấu. Trong triển khai phải có test cạnh tranh và clock precision.
- Inbox trả 0 item và summary 0 khi chưa có dữ liệu; không dùng hai thông báo mẫu trong môi trường production.

### 7.4 Error envelope, caching và tương thích

Mã lỗi đề xuất: `NOTIFICATION_SETTING_UNKNOWN`, `NOTIFICATION_SETTING_LOCKED`, `NOTIFICATION_SETTING_REVISION_CONFLICT`, `NOTIFICATION_PREFERENCE_STALE`, `NOTIFICATION_NOT_FOUND`, `NOTIFICATION_SERVICE_UNAVAILABLE`. Mã lỗi, HTTP status, i18n (`vi/en`) và OpenAPI response phải đồng nhất. Với dữ liệu cá nhân, response `Cache-Control: private, no-store`; không dùng shared cache giữa Users.

## 8. Transactional Outbox và xử lý worker

### 8.1 Import Job — điểm tích hợp cần sửa có kiểm soát

`ImportJobService::start()` đang xử lý từng dòng có journal; **không được** mở một transaction bao toàn bộ import. Chỉ refactor ghi trạng thái `PROCESSING → terminal` thành một transaction cục bộ:

```text
BEGIN
  UPDATE import_jobs
     SET status = :terminal, completed_at = now, counters = ...
   WHERE id = :id AND status = 'PROCESSING'
  REQUIRE affected_rows = 1
  INSERT notification_outbox(event_key='import-job:{id}:terminal', ...)
COMMIT
```

- Nếu transaction rollback: không có terminal-event giả.
- Nếu không thể ghi outbox: terminal update **cùng rollback**, đưa lỗi ra đường xử lý an toàn, không tuyên bố đã hoàn tất.
- Đường `catch` khi job `FAILED` cũng phải dùng phương thức finalize thống nhất; trường hợp DB hỏng/PROCESSING còn treo cần quy trình reconciliation, không tự tạo thông báo thành công.
- Recipient của Import lấy từ `import_jobs.created_by` (trusted DB); loại event phụ thuộc terminal status, không do frontend quyết định.

### 8.2 Workflow (M11.2)

Trong `BusinessWorkflowService::execute()`, ghi outbox **sau khi** thay đổi trạng thái chứng từ và **trước** `transCommit()`, tránh gửi thông báo khi nghiệp vụ rollback. Resolver người nhận phải xác định từ phân công/hồ sơ nghiệp vụ tồn tại, không broadcast tùy tiện theo Group/PBAC.

### 8.3 Spark Dispatcher

Đề xuất command `php spark notifications:dispatch --once --batch=100`; Worker loop (khi được triển khai) gồm:

1. Claim các event sẵn sàng bằng transaction + lease; không giữ khóa SQL trong thời gian thực hiện công việc dài.
2. Parse payload theo schema và catalog; xác định User từ DB; đọc settings + preferences; áp dụng mandatory/capability.
3. Với mỗi recipient hợp lệ và kênh In-App bật, INSERT vào `user_notifications` bằng unique key. Không tạo N+1 API ở frontend.
4. Kết thúc event bằng `PROCESSED`/`SUPPRESSED`; lỗi tạm thời → retry theo backoff, lỗi vĩnh viễn → `DEAD` có mã lỗi để vận hành kiểm tra.
5. Nếu worker chết giữa chừng: lease hết hạn và event được claim lại; unique keys giữ kết quả **at-least-once nhưng không tạo trùng Inbox**.

**Chiến lược claim:** xác minh MariaDB DEV và isolation. Có thể dùng transaction `SELECT ... FOR UPDATE SKIP LOCKED` nếu engine/version hỗ trợ; nếu không, dùng conditional atomic UPDATE + `lease_token`/`leased_until` đã kiểm chứng bằng test hai workers. Không giả định version database của XAMPP từ source ZIP.

**Retry khởi điểm đề xuất:** 5 lần; backoff 1/5/15/60/240 phút; phải có giám sát dead-letter và lệnh requeue do admin vận hành (không mở public HTTP endpoint). Không dùng worker vô hạn trong request web.

## 9. Bảo mật & phân quyền

| Thao tác | Bắt buộc kiểm tra |
|---|---|
| Đọc/Sửa Notification System Settings | Resource/action mới `notification_setting.view/edit`; base permission **và** PBAC UnifiedAccessService |
| Đọc/Sửa Preferences của mình | Shield auth + whitelist key + policy backend; không client-supplied User ID |
| Đọc/Đánh dấu Inbox | `recipient_user_id = auth()->user()->id`; không sử dụng ID của tài khoản khác |
| Mở đối tượng đích | Điều hướng qua allowlisted route registry; API đích tái kiểm tra PBAC mỗi request |
| Xử lý Worker | Chạy dưới danh tính dịch vụ/CLI có quyền DB tối thiểu, không nhận thao tác client |
| Ghi System Policy | Transaction + immutable audit + request_id + actor |

- Content minimal: tránh đưa tên chứng từ nhạy cảm, trị giá, nội dung bảo mật vào title/body hoặc params. Notification có thể tồn tại khi quyền xem đích đã bị thu hồi; click phải bị 403/404 an toàn.
- Không dùng notification để “cấp quyền” hay xác nhận người dùng được phép sửa bản ghi; UI phải kiểm tra trạng thái business hiện tại khi điều hướng.
- Kiểm tra CSRF cho POST/PATCH; giới hạn kích thước JSON, lọc key, rate limit hợp lý, không cho HTML/JS từ template params.
- Tách bảo vệ cấp tab: User có quyền `notification_setting.view` nhưng không có `display_setting.view` vẫn được vào tab Thông báo; không được fetch Display Settings trái quyền khi tab notification được mở trực tiếp.
- Có kiểm thử phản hồi 401 khi hết phiên, 403 cho admin actions không đủ quyền, 404 cross-user Inbox, và 409 concurrency.

## 10. Thiết kế frontend và UX

### 10.1 System Settings

Tại `/system/display-settings?tab=notifications`:

- Tab `Hiển thị` hoạt động như cũ, bao gồm SYSTEM/ORGANIZATION và các permission Display Settings.
- Tab `Thông báo`: **chỉ** System scope trong M11; bảng mỗi key gồm loại, kênh, `Mặc định`, `Cho User ghi đè`, `Bắt buộc`, trạng thái khả dụng, thao tác Lưu/Reset nếu được phép.
- User không có `notification_setting.view` nhìn thấy thông báo 403 tại tab, không thấy dữ liệu chính sách; không tự dùng `display_setting.edit` làm quyền.
- Khi admin tắt `allow_user_override`, giao diện cảnh báo việc người dùng sẽ quay về mặc định System; hiển thị rõ `override_epoch`/revision gián tiếp qua cảnh báo xung đột nếu màn hình cũ.
- Nút Lưu vô hiệu trong quá trình request; stale revision hiển thị reload action; lỗi không làm mất draft.

### 10.2 Personal Preferences

Tại `/profile/display-preferences?tab=notifications`:

- Tab `Hiển thị` tiếp tục dùng LocalStorage theo cơ chế hiện hành.
- Tab `Thông báo` dùng API/server state, với mỗi key có `Kế thừa`, `Bật`, `Tắt`; khóa lựa chọn khi policy không cho ghi đè hoặc kênh chưa hỗ trợ.
- Hiển thị trạng thái hiệu lực và lý do: `Theo hệ thống`, `Bạn đã tùy chỉnh`, `Quản trị viên khóa`, `Kênh chưa khả dụng`.
- Nút `Dùng mặc định` gửi `value: null` nếu được phép. Nếu policy đổi khi User đang mở form: backend 409/403, UI refresh và giải thích; không áp đặt giả dữ liệu cũ.

### 10.3 AppShell và Inbox

- `useShellHeader` bỏ hai thông báo mẫu khi feature flag backend đã bật; nối `useNotificationStore` (Pinia) với `summary`/`items`.
- Poll summary 60 giây **khi đang đăng nhập và document.visible**; khi focus hoặc mở chuông thì refresh ngay, giới hạn tối đa một request summary đang chạy.
- Khi logout/đổi User: tăng session generation, AbortController/cancel request, clear unread/Inbox, không trộn response giữa hai phiên.
- Popover hiển thị 10–20 mục; Inbox hiển thị 20/cursor, có empty/error/loading, keyboard, aria-label, focus management, i18n vi/en và dark mode.
- Bấm vào mục không thay thế cơ chế kiểm tra quyền trang đích. Mở chuông **không** đồng nghĩa đã đọc tất cả.

## 11. Mốc triển khai theo thứ tự

| Mốc | Backend | Frontend / hợp đồng | Gate |
|---|---|---|---|
| M11.0 — Design | Chốt schema/permission/event catalog, kiểm tra DB clone | API contract, wireframe, ADR, test plan | Review và ký duyệt design |
| M11.1A — Core + Settings | Migration 5 bảng, registry, policy resolver, audit, API settings/preferences, PBAC | 2 tab Settings, codegen OpenAPI | Resolver truth-table, ownership, write/409, transaction PASS |
| M11.1B — Inbox + Worker | Outbox, worker, Inbox API, Import Job terminal integration | Bell, `/notifications`, Pinia + polling | Retry/idempotency/Import rollback + E2E PASS |
| M11.2 — Business Events | Workflow outbox, recipient resolver, nghiệp vụ có người nhận xác thực | Notification route link phù hợp | PBAC + cross-user + concurrency PASS |
| M11.3 — Channels | Email/Push transport, user channel preferences, suppression | Chỉ hiện công tắc đã deploy | Opt-in, delivery/retry, rate limit PASS |

### 11.1 Danh sách module dự kiến

- Backend `app/Modules/Notifications/{Catalog,Settings,Preferences,Delivery,Inbox}` (service/repository/controller tách theo trách nhiệm), `app/Commands/NotificationsDispatch.php`.
- Backend migration/seed resource `notification_setting` + actions `view/edit`, route register file M11 mới; i18n errors ở `vi/en`.
- Frontend `src/features/notifications/`, `src/stores/notifications.ts`, AppShell component, 2 Settings tabs, `/notifications` route, generated SDK.
- Test: backend pure/repository/integration; frontend Vitest + Playwright; codegen route guard/contract checks.

### 11.2 Thứ tự rollout và tương thích

1. Kiểm toán DEV DB: `SELECT VERSION()`, engine, charset, Shield user PK/FK, DB prefix, migrations, bảng `resources/resource_actions`, chính sách PBAC.
2. Migration thêm bảng **trước** triển khai API; seed key bất biến và resource/action **idempotent**; mọi quyền mới mặc định deny cho tới khi admin cấp đúng.
3. Bổ sung OpenAPI operations **mới**, SDK generate/check, route verification cập nhật expectation; không sửa hash hợp đồng cũ khi chưa có quy trình version extension.
4. Deploy backend bằng feature flag `notifications.enabled=false`, worker tắt. Chạy checks và kiểm tra rollback/restore trên DEV clone.
5. Bật API/In-App cho pilot Users, bật worker, kiểm tra metrics; sau đó thay mock chuông và phát hành toàn bộ.
6. Rollback bằng **tắt feature flag và dừng worker**, không tự drop các bảng có dữ liệu hay xóa audit. Bản migration rollback phá dữ liệu chỉ được chạy sau backup và quyết định vận hành riêng.

**Lưu ý:** tên feature flag, seeder, command, migration ID và threshold được chốt khi viết patch; tài liệu này chưa thực thi chúng.

## 12. Kế hoạch QA / Acceptance Criteria

| ID | Kịch bản | Kỳ vọng |
|---|---|---|
| CFG-01 | Default=true, không cho override | Effective=true với mọi User |
| CFG-02 | Default=false, không cho override | Effective=false với mọi User |
| CFG-03 | Default=true, cho override, User=false | Effective=false, source USER |
| CFG-04 | Default=false, cho override, User=true | Effective=true, source USER |
| CFG-05 | User reset `null` | Quay về default, không xóa audit System |
| CFG-06 | Bật override → khóa → mở lại | Giá trị user cũ không tự sống lại |
| CFG-07 | Đổi System default khi override đang hợp lệ | User override tiếp tục hiệu lực |
| CFG-08 | Mandatory bị admin PATCH tắt | 422 + DB không đổi |
| CFG-09 | PATCH settings với revision cũ | 409; audit không ghi sai |
| CFG-10 | User PATCH khi key bị khóa / epoch cũ | 403/409; không ghi dữ liệu |
| CFG-11 | User A gọi Preferences/Inbox của User B | Không thể truy cập; không có `user_id` request |
| CFG-12 | User có quyền notification_setting, không có display_setting | Vào tab Notifications đúng, không tải Display APIs trái quyền |
| OUT-01 | Import terminal update + outbox insert cùng commit | Có chính xác 1 event |
| OUT-02 | Lỗi ghi outbox khi finalize | Rollback cả final-status write |
| OUT-03 | Import nghiệp vụ rollback hoặc chưa terminal | Không có thông báo hoàn thành giả |
| OUT-04 | Hai worker claim cùng event | Chỉ một Inbox item/recipient |
| OUT-05 | Worker chết sau INSERT, trước ACK | Retry không tạo trùng |
| OUT-06 | DB lỗi/lease hết hạn | Event được retry, eventual DEAD có log |
| OUT-07 | User tắt key trước dispatch | Suppressed, không phát lại tự động |
| INB-01 | Đánh dấu read 2 lần | Idempotent, không tăng/decrement sai |
| INB-02 | Mark all + notification mới phát sinh | Mục mới vẫn unread |
| INB-03 | 50k notifications/User | Index/cursor, không N+1, kế hoạch benchmark |
| SEC-01 | Đổi User/logout giữa hai request | Store cũ không xuất hiện ở phiên mới |
| SEC-02 | Quyền đích bị thu hồi sau notification | Click bị backend đích chặn |
| E2E-01 | Chromium, Firefox, WebKit: tabs, bell, mark-read | PASS cả 3 trình duyệt |
| E2E-02 | Vi/en, dark mode, keyboard/accessibility | Không regression |
| API-01 | OpenAPI lint/codegen/route verification | Khớp số operation mới; old contracts không đổi |

**Non-functional targets đề xuất để benchmark trên DEV clone, không phải kết quả đã đo:** Inbox p95 < 300 ms, summary p95 < 150 ms với dataset QA 50k/User; một lượt mở popover tối đa 2 request (summary + list); settings mở trực tiếp tối đa 1 request catalog/policy; không có N+1 GET theo mỗi thông báo. Các mục tiêu phải điều chỉnh theo cấu hình XAMPP thật.

**Điều kiện CLOSED:** migration rehearsal + restore PASS; backend/worker integration & PBAC/ownership PASS; OpenAPI/route & codegen PASS; frontend type-check/Vitest/Playwright 3 browsers/build PASS; manual DEV/UAT và Network budget được xác nhận; không còn lỗi P0/P1.

## 13. Rủi ro và các điểm cần chốt trước khi viết patch

| Rủi ro / câu hỏi | Quyết định hiện tại | Bước kiểm tra |
|---|---|---|
| Phiên bản MariaDB, `SKIP LOCKED`, độ chính xác thời gian | Không giả định từ ZIP | DEV SQL audit trên clone |
| Kiểu khóa ngoại Shield Users / bảng hiện tại | Phải khớp DB thực | `SHOW CREATE TABLE`, engine/collation |
| Source Import terminal không có transaction bao outbox | Refactor finalize cục bộ, không toàn job | Test rollback/failure-path |
| Policy `SYSTEM` vs `ORGANIZATION` | MVP chỉ SYSTEM | UI tách tab, backend từ chối scope khác |
| Có cần bắt buộc `security.*` ngay M11.1? | Chỉ seed khi có producer đáng tin cậy | M11.2 catalog review |
| Admin dùng quyền Display Settings hiện tại | Không tái sử dụng | Seed resource/action mới, PBAC test |
| Outbox khi người dùng tắt thông báo | Đánh giá khi dispatch, không replay | Test suppression và audit metrics |
| Build M10.4A | Chưa có log kết quả trong hội thoại | Build baseline trước khi bắt đầu M11 |

## 14. Nguồn và tài liệu tham khảo

**Nguồn đối chiếu nội bộ (đường dẫn tương đối của ERP):**

- `frontend/src/components/app-shell/useShellHeader.ts`, `frontend/src/components/AppShell.vue`
- `frontend/src/features/settings/pages/DisplaySettingsView.vue`
- `frontend/src/features/account/pages/DisplayPreferencesView.vue`, `frontend/src/stores/displaySettings.ts`
- `backend/app/Modules/Settings/Display/DisplaySettingsService.php`
- `backend/app/Modules/Import/Core/ImportJobService.php`
- `backend/app/Modules/Workflow/Execution/BusinessWorkflowService.php`
- `backend/app/Modules/Access/UnifiedAccessService.php`
- `backend/app/Shared/Http/ApiResponder.php`, `ApiEnvelope.php`; `contracts/openapi/openapi.yaml`
- `frontend/package.json`, `frontend/src/stores/auth.ts`, `frontend/src/router/routes.ts`

**Tham khảo kỹ thuật bên ngoài (dùng để kiểm tra nguyên tắc, không thay thế kết quả DEV DB audit):**

- CodeIgniter 4 Transactions: https://codeigniter4.github.io/userguide/database/transactions.html
- CodeIgniter 4 Spark Commands: https://codeigniter4.github.io/CodeIgniter4/cli/spark_commands.html
- MariaDB `FOR UPDATE`: https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update
- OWASP Authorization Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

---

**Kết luận M11.0:** thiết kế được đề xuất đủ rõ để bắt đầu code theo hai nhánh M11.1A (System/User Settings) và M11.1B (Outbox/Inbox). Mọi thay đổi runtime/migration phải triển khai dưới patch riêng và qua các gate DEV đã nêu; tài liệu này không tự chứng minh hiệu năng hay tương thích schema thực tế.
