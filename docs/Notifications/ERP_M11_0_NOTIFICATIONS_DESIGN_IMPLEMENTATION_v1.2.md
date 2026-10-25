# M11.0 — THIẾT KẾ & KẾ HOẠCH TRIỂN KHAI NOTIFICATIONS

**DinkERP · Design / Implementation Specification · v1.2 · 25/10/2026**  
**Trạng thái:** DESIGN REVIEWED / DB-COMPATIBILITY UPDATED — đã ghi nhận MariaDB 10.4.32 và cấu trúc cột `dinkitech_users` từ phpMyAdmin; vẫn cần kiểm tra `SHOW CREATE TABLE`, engine/FK, isolation và chạy DEV clone trước migration; chưa triển khai Notifications.  
**Nền đối chiếu:** ERP v1.5.0 và các milestone M10 đã trao đổi (M10.4A: type-check, 14/14 Vitest và 33/33 Playwright đã PASS trong log người dùng; production build M10.4A chưa được xác nhận).

## 0. Tóm tắt quyết định

**Mục tiêu:** xây dựng Notifications dùng chung cho ERP, bảo đảm người nhận đúng, không lộ dữ liệu, không mất sự kiện khi nghiệp vụ commit, thao tác Inbox nhanh và có cấu hình cá nhân chịu sự điều khiển của chính sách hệ thống.

**Các quyết định đã thống nhất:**

1. **Vị trí cấu hình:** thêm tab `Thông báo` tại `/system/display-settings?tab=notifications` và `/profile/display-preferences?tab=notifications`. Giữ nguyên URL và tab `display` mặc định. Trang `/notifications` là Inbox, không phải trang cấu hình mới.
2. **Mỗi khóa cấu hình có hai trường quản trị:** `default_value` (Bật/Tắt) và `allow_user_override` (Có/Không). User có thể đặt `true`, `false` hoặc `null` (kế thừa) khi chính sách cho phép; PATCH dùng khóa phiên bản theo từng User/key để tránh lost update.
3. **Giá trị có hiệu lực:** tính ở backend, không tin frontend. Kênh chưa được triển khai luôn tắt; cấu hình bắt buộc chỉ có hiệu lực khi producer/kênh đã sẵn sàng. Không hồi sinh override cũ sau chu kỳ khóa/mở khóa (`override_epoch`).
4. **Bảo mật:** Shield cung cấp User ID; UnifiedAccessService/PBAC kiểm tra quyền quản trị `notification_setting.view/edit` và quyền đối tượng đích. Resource mới phải đăng ký `ALL_STATES` + `SYSTEM` trong catalog, không chỉ seed quyền. Inbox/Preferences cá nhân ràng buộc theo User đăng nhập.
5. **Hạ tầng MVP:** MariaDB **10.4.32** (theo môi trường XAMPP được cung cấp), InnoDB + Transactional Outbox + Spark worker + REST polling 60 giây khi tab hoạt động. Worker dùng `SELECT ... FOR UPDATE` trong transaction; **không dùng `SKIP LOCKED` trên 10.4**. Không bắt buộc Redis, WebSocket, Email hoặc Push.
6. **Sự kiện đầu tiên:** Import Job hoàn tất (bao gồm thành công, có lỗi và thất bại). Workflow được tích hợp sau, không giả định đã có hàng đợi người phê duyệt.
7. **API là phần mở rộng mới:** không thay đổi ngầm các operation đã đóng băng của v1.2.4; đăng ký hợp đồng OpenAPI, codegen và route verification mới. Bản này giữ đúng **8 operations** đã dự kiến.
8. **Tương thích Identity DB:** `dinkitech_users.id` là `INT UNSIGNED AUTO_INCREMENT`, không phải `BIGINT`; các cột Notification tham chiếu Shield User phải dùng `INT UNSIGNED` cùng sign, và không cascade xóa Inbox/Preferences ngoài ý muốn.

**Ba ranh giới không được đánh đồng:** Notification không cấp quyền truy cập tài liệu; `read_at` không phải Audit Log; `allow_user_override` là quyền tùy chỉnh kênh/loại thông báo, **không phải quyền PBAC**.

## 1. Rà soát source và hiệu chỉnh thiết kế

| Thành phần đã kiểm tra | Bằng chứng trong source | Tác động thiết kế |
|---|---|---|
| AppShell Notification | `frontend/src/components/app-shell/useShellHeader.ts` có 2 thông báo mẫu và `notificationsRead` trong bộ nhớ | Thay mock bằng Pinia store; không dùng trạng thái mock như dữ liệu thật |
| System Display Settings | `frontend/src/features/settings/pages/DisplaySettingsView.vue` có `SYSTEM`/`ORGANIZATION`; quyền `display_setting.view/edit` | Tab Notification dùng service/API/quyền riêng; MVP chỉ SYSTEM, không áp dụng ngầm override tổ chức |
| Personal Display Preferences | `frontend/src/features/account/pages/DisplayPreferencesView.vue` và `stores/displaySettings.ts` lưu tùy chọn hiển thị cục bộ | Notifications Preferences phải lưu DB; worker không thể đọc LocalStorage |
| API contract | `contracts/openapi/openapi.yaml`, `frontend/package.json` có `openapi:lint`, `openapi:generate` | Thêm hợp đồng mở rộng, sinh SDK; giữ envelope `{data, meta}` |
| PBAC | `UnifiedAccessService.php`, `ResourceStateCatalog.php`, các kiểm thử catalog cố định | `notification_setting` phải được thêm vào nhóm `ALL_STATES`, khai báo domain `SYSTEM`, seed resource/action và cập nhật các kiểm thử hard-code 10 resource độc lập |
| Import Job | `backend/app/Modules/Import/Core/ImportJobService.php` ghi trạng thái cuối qua `setJob()` riêng | Khi tích hợp phải đặt final-status UPDATE + Outbox INSERT trong **cùng transaction mới**; không bao cả import nhiều dòng trong một transaction |
| Workflow | `BusinessWorkflowService::execute()` có transaction và khóa hàng nghiệp vụ | Ghi outbox sau UPDATE, trước COMMIT, bảo toàn rollback |
| HTTP | `ApiResponder` / `ApiEnvelope` chuẩn hóa lỗi, `X-Request-ID`, `Content-Language` | Tuân thủ envelope/errors/header và CSRF hiện có |
| Audit cấu hình hiện hữu | `DisplaySettingsService.php` ghi vào `configuration_audit_events` | Có hạ tầng audit sẵn; **không tự nhận** bảng đó tương thích schema Notifications khi chưa xem DDL trên DEV |
| Routing cài đặt | `routes.ts` có hai trang riêng; `DisplaySettingsView.vue` hiện fetch Display lúc mount và kiểm tra `display_setting.view` ở cấp trang | Bắt buộc tách render/fetch/guard theo tab để User chỉ có `notification_setting.view` truy cập được tab Thông báo |
| Cơ sở dữ liệu triển khai | Người dùng xác nhận **MariaDB 10.4.32** và cung cấp ảnh phpMyAdmin bảng `erp.dinkitech_users` với PK `id INT UNSIGNED` | Đã xác định family/version và kiểu khóa chính User; chốt worker không dùng `SKIP LOCKED`. Chưa có `SHOW CREATE TABLE`/engine/isolation cho DB hiện hữu |
| Mismatch ID trong nguồn Import | Migration `2026-10-05-000008_CreateImportTables.php` định nghĩa `import_jobs.created_by BIGINT UNSIGNED NULL`, trong khi `dinkitech_users.id INT UNSIGNED` | Resolver phải kiểm tra phạm vi giá trị trước khi tìm User; không ép kiểu âm thầm hay tạo FK giữa hai cột khác kiểu |

**Quyết định về bảng audit:** dự kiến **5 bảng mới** (2 phân phối, 2 cấu hình, 1 `notification_setting_audit`). ERP đã có `configuration_audit_events`, nhưng chưa có DDL trên DEV clone để chứng minh phù hợp với `revision`, `override_epoch` và payload before/after của Notifications. **Không ghi trùng cùng một lần thay đổi vào hai bảng.** Nếu quyết định tái sử dụng audit chung sau khi xác minh DDL, phải lập ADR/schema change và cập nhật con số bảng/migration trước khi code, không tự đổi ngầm trong M11.1A.

### 1.1 Bằng chứng DB mới và giới hạn xác minh (25/10/2026)

**Bằng chứng trực tiếp từ người dùng:** phpMyAdmin đang truy cập `127.0.0.1:3306`, database `erp`, bảng **`dinkitech_users`**; MariaDB **10.4.32**. Ảnh chụp là giao diện *Cấu trúc bảng*, không phải kết quả `SHOW CREATE TABLE`: đủ chứng minh tên/cột/thuộc tính nhìn thấy, **chưa đủ** kết luận storage engine, foreign keys, triggers hay collation ở cấp bảng.

| Cột thực tế `dinkitech_users` | Kiểu/nullable/default thể hiện trên ảnh |
|---|---|
| `id` | `INT(11) UNSIGNED NOT NULL AUTO_INCREMENT`, khóa chính (`(11)` chỉ độ rộng hiển thị, không phải 11 byte) |
| `username` | `VARCHAR(30) NULL` |
| `status` / `status_message` | `VARCHAR(255) NULL` / `VARCHAR(255) NULL` |
| `active` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `last_active` / `created_at` / `updated_at` / `deleted_at` | `DATETIME NULL` cho từng cột |
| `full_name` / `avatar_filename` | `VARCHAR(255) NULL` / `VARCHAR(128) NULL` |

Các cột chữ hiển thị `utf8mb4_general_ci`. Source migration ERP cũng dùng `config('Auth')->tables['users']` để mở rộng `full_name`/`avatar_filename`; phải **resolve tên bảng vật lý** từ Shield/CI và DB prefix khi viết migration, không hard-code tên `users`.

**Ảnh hưởng đã chốt cho M11:**

- `notification_user_preferences.user_id` và `user_notifications.recipient_user_id`: **`INT UNSIGNED NOT NULL`**, cùng kiểu/signedness với PK Shield; `notification_settings.updated_by` và `notification_setting_audit.actor_user_id` nếu lưu ID actor: **`INT UNSIGNED NULL`**. Không tự chuyển toàn bộ ID sang `BIGINT`.
- `notification_outbox.source_id` tham chiếu nguồn đa hình, với `import_jobs.id` được migration source khai báo `BIGINT UNSIGNED`; không lập FK đa hình tới `dinkitech_users`. `import_jobs.created_by BIGINT UNSIGNED NULL` là khác kiểu với User PK: phải kiểm tra `1..4294967295`, giá trị là số nguyên hợp lệ, rồi mới truy vấn User; tuyệt đối không cast/truncate im lặng.
- `active=1 AND deleted_at IS NULL` là **điều kiện tối thiểu** để worker xem User là khả dụng. `status`/`status_message` nullable, ý nghĩa nghiệp vụ chưa được chứng minh từ ảnh; mọi ràng buộc bổ sung phải theo Shield/Identity service hiện hành, không suy đoán bằng chuỗi trạng thái. Không dùng `username`/`full_name` làm khóa định danh hoặc coi chúng luôn non-null.
- Worker có thể **SUPPRESSED** nếu job được tạo bởi hệ thống (`created_by IS NULL`) hoặc User có thật nhưng đã inactive/soft-deleted. `created_by` khác NULL nhưng ngoài phạm vi `INT UNSIGNED`, không phải số nguyên, hoặc không tồn tại User phải được ghi nhận là lỗi nguồn/resolver (`RETRY`/`DEAD` theo chính sách), không chuyển thành SUPPRESSED một cách âm thầm.
- MariaDB **10.4.32 không hỗ trợ `SELECT ... SKIP LOCKED`** (được thêm từ nhánh 10.6). DDL ở 10.4 cũng **chưa có bảo đảm atomic/crash-safe DDL của 10.6**. Vì vậy migration phải rehearsal/backup và worker claim mặc định khóa hàng bằng transaction thuần.
- Trong 10.4, `JSON` là alias của `LONGTEXT` (có `JSON_VALID` check theo phiên bản này), không phải MySQL binary JSON. Kiểm tra migration thực tế và giới hạn payload JSON; không phụ thuộc `JSON_TABLE` (từ 10.6) hay khả năng index JSON như MySQL 8.

**Rủi ro vận hành:** Theo lịch duy trì chính thức của MariaDB, dòng **10.4 hết hỗ trợ ngày 18/06/2024**. M11 vẫn có phương án tương thích 10.4 cho môi trường đang dùng; trước khi đưa lên production nên lập kế hoạch nâng cấp DB riêng, không tự động nâng cấp XAMPP trong migration Notifications.

### 1.2 Những mâu thuẫn được xử lý trong v1.1

1. Resource PBAC mới nếu chỉ seed sẽ bị hiểu là Lifecycle thay vì ALL_STATES; chỉ rõ thay đổi catalog/domain và regression bắt buộc.
2. Kiểm tra `override_epoch` không ngăn hai phiên User ghi đè cùng key; bổ sung `user_revision`, CAS và bản ghi reset có `override_value=null`.
3. Tình huống ghi Outbox thất bại trong `finalize()` không được rơi vào `catch` rồi vô tình đổi job đã xử lý xong thành `FAILED`; tách rõ xử lý lỗi nghiệp vụ và lỗi finalize.
4. Outbox event có thể dành cho nhiều người ở M11.2; cần giao dịch phát **toàn bộ người nhận + ACK** theo đơn vị event, không ACK sau khi chỉ ghi một phần.
5. `mark-all-read` dùng cutoff thời gian không tuyệt đối bảo đảm thứ tự **commit** giữa hai transaction; thu hẹp bảo đảm về thời điểm tạo, nêu rõ ngoại lệ cạnh tranh.
6. Giữ tab Notifications độc lập quyền/lifecycle fetch của trang Display cũ; không chỉ thêm tab vào template.
7. Đồng bộ phạm vi 5 bảng, 8 API, mã HTTP, quy tắc kênh tương lai và các bài kiểm thử theo một hợp đồng duy nhất.

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

| Setting key (event + channel) | Mặc định | User ghi đè | Bắt buộc | Có thể cấu hình từ | Phát event từ |
|---|---:|---:|---:|---|---|
| `import.completed.in_app` | true | true | Không | M11.1A | M11.1B |
| `import.completed_with_errors.in_app` | true | true | Không | M11.1A | M11.1B |
| `import.failed.in_app` | true | true | Không | M11.1A | M11.1B |
| `workflow.action_required.in_app` | true | false | Không | M11.2 | M11.2 khi có resolver |
| `security.permission_changed.in_app` | true | false | Có | M11.2 | M11.2 khi có producer |
| `import.completed.email` | false | true | Không | M11.3 | M11.3 khi có transport |

- **Registry server** phân biệt **`capability` (cấu hình key đã được hỗ trợ)** với **`producer_state` (`INACTIVE|ACTIVE`, có phát sự kiện thật hay chưa)**. GET cho phép xem key tương lai nhưng PATCH của cả admin/User đối với `capability=UNAVAILABLE` trả 422. Không tạo Outbox khi producer chưa ACTIVE.
- **M11.1A**: các key Import In-App có `capability=AVAILABLE`, `producer_state=INACTIVE`, có thể cấu hình và test truth-table/HTTP nhưng **chưa phát thông báo**. **M11.1B**: sau khi worker, Inbox và feature flag đã sẵn sàng, đổi `producer_state=ACTIVE`; không tự tạo lại sự kiện xảy ra trước đó. Key Workflow/Security và Email vẫn `UNAVAILABLE` đến milestone của chúng.
- `mandatory=true` chỉ hợp lệ với `default_value=true`, `allow_user_override=false`; khi `capability=UNAVAILABLE` resolver trả `false` và không phát. Khi `producer_state=INACTIVE`, giá trị effective của **cấu hình** vẫn tính được nhưng không có event gửi đến User, tránh đánh đồng cấu hình hợp lệ với một producer đang chạy.
- `allow_user_override` kiểm soát quyền chọn giá trị, không phải quyền bật kênh chưa triển khai, không thay thế cơ chế cấp quyền PBAC. Phát event theo nghiệp vụ có ý nghĩa, không theo mọi thao tác CRUD thông thường.

## 4. Quy tắc System → User → Effective

### 4.1 Quy tắc hiệu lực

Mỗi key có `P(key) = (default_value, allow_user_override, revision, override_epoch)` trong DB và các thuộc tính **do registry kiểm soát** `(mandatory, capability, producer_state)`. Mỗi người dùng có `U(user_id,key) = (override_value, override_epoch, user_revision)`; `override_value` cho phép `NULL` để biểu diễn reset nhưng vẫn giữ revision. Nếu chưa có row User, GET trả `user_override=null, user_revision=0`; nếu đã reset, DB vẫn có row `override_value=NULL` và `user_revision>0`.

| Mặc định | User được ghi đè | User override hợp lệ | Effective | Nguồn |
|---|---|---|---|---|
| true | false | bất kỳ | true | SYSTEM_LOCKED |
| false | false | bất kỳ | false | SYSTEM_LOCKED |
| true | true | null / không tồn tại | true | SYSTEM_DEFAULT |
| true | true | false | false | USER |
| false | true | true | true | USER |
| false | true | null / không tồn tại | false | SYSTEM_DEFAULT |

`mandatory=true` yêu cầu `default=true`, `allow_user_override=false`; sự kiện chỉ được phát khi `capability=AVAILABLE` **và** `producer_state=ACTIVE`. Key chưa AVAILABLE có `effective=false`, `source=CHANNEL_UNAVAILABLE` và không thể sửa qua API; key có capability AVAILABLE nhưng producer INACTIVE vẫn resolve được giá trị cấu hình, song chưa tạo event.

### 4.2 Pseudocode bắt buộc

```text
resolve(user, key):
  def = catalog.requireKnown(key)
  if def.capability != AVAILABLE: return (false, CHANNEL_UNAVAILABLE)
  policy = settings.requireValidSeededRow(key)  // fail closed nếu thiếu/corrupt
  if def.mandatory: return (true, MANDATORY)
  if !policy.allow_user_override: return (policy.default_value, SYSTEM_LOCKED)
  pref = user_preferences.find(user.id, key)
  if pref == null OR pref.override_value == null
      OR pref.override_epoch != policy.override_epoch:
    return (policy.default_value, SYSTEM_DEFAULT)
  return (pref.override_value, USER)
```

Không được tự dùng giá trị registry mặc định nếu row System đã seed bị thiếu hoặc không hợp lệ; đó là **lỗi cấu hình cần giám sát**, tránh tự phát thông báo ngoài ý muốn.

### 4.3 Khóa/mở khóa và revision

- Khi admin đổi `allow_user_override: true → false`: **tăng `override_epoch` một lần**, đồng thời tăng `revision` và ghi audit trong cùng transaction. `false → true` **không khôi phục** các override thuộc epoch trước.
- Thay `default_value` khi vẫn cho ghi đè **không tăng epoch**, vì lựa chọn cá nhân còn hiệu lực. Thay đổi không làm khác dữ liệu (no-op) không tăng `revision`/epoch hoặc tạo audit. **Mọi thay đổi chính sách thực sự** tăng `revision` đúng một lần, kể cả `false → true`.
- PATCH admin gửi `expected_revision` cho **từng key**. Backend khóa các row theo thứ tự key cố định, xác minh tất cả revision và mandatory/capability trước khi ghi; mismatch → 409, toàn bộ request rollback.
- PATCH User gửi **cả `expected_override_epoch` và `expected_user_revision`**. Khi chưa có row sở thích, `user_revision=0`; khi đã có row, revision tăng 1 trên mọi thay đổi được chấp nhận, kể cả reset về `null`. Reset không xóa row đã có: giữ `NULL` như tombstone để chống stale write.
- Hai phiên cùng sửa một User/key: phiên đến sau dùng revision cũ → 409. Khi tạo row lần đầu, dùng UNIQUE `(user_id,setting_key)` và xử lý duplicate race thành 409, không dùng UPSERT ghi đè mù.
- PATCH User phải bắt đầu transaction, khóa `notification_settings` row của key (theo thứ tự key), kiểm tra epoch/capability/lock, sau đó khóa hoặc tạo preference với CAS revision; đảm bảo không có cửa sổ admin khóa giữa kiểm tra và ghi.
- Thứ tự lỗi: unknown/unavailable/invalid payload → 422; `expected_override_epoch` cũ → 409; key đang khóa → 403; `expected_user_revision` cũ → 409. Người không đăng nhập → 401. Backend tự tính lại effective sau commit.
- Bulk PATCH trên một API là **nguyên tử toàn bộ**, trùng key → 422; transaction rollback không thay đổi row/audit nào. Giới hạn danh sách key và payload trong hợp đồng OpenAPI.

**Ví dụ:** User A tắt Import khi epoch=4. Admin khóa key và tăng epoch=5, default=true. Sau đó mở khóa, A vẫn nhận `true` (SYSTEM_DEFAULT) cho đến khi tự lưu lại tại epoch=5. User khác không bị mất tùy chọn hợp lệ khi admin chỉ đổi `default_value`.

### 4.4 Ranh giới thời điểm áp dụng

- Resolver của Worker đọc giá trị hiệu lực tại **transaction phân phối**, không tại lúc tạo outbox; kết quả Inbox đã commit không được xóa hồi tố khi User đổi Preferences.
- Nếu sự kiện bị chặn do preference, Worker kết thúc event theo trạng thái suppressed, không tự phát lại khi bật key. Trong MVP mỗi Import event chỉ có một recipient; các sự kiện nhiều recipient phải có kết quả nhất quán theo mục 8.
- Dispatcher chỉ tiếp nhận event có producer ACTIVE; nếu policy/capability không còn khả dụng trong khoảng chờ, không tự chuyển sang mandatory. Cả capability và producer_state đều được kiểm tra tại thời điểm phân phối, không coi `effective=true` là bằng chứng producer đã bật.

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

**Quy tắc giao dịch:** Không gửi HTTP/Email trong transaction nghiệp vụ. Worker chỉ xử lý những event đã commit. Mỗi event có `event_key` bất biến; mỗi người nhận chỉ có một notification theo `(recipient_user_id, event_key)` trong vòng đời lưu trữ.

## 6. Mô hình dữ liệu mục tiêu (5 bảng mới)

Đây là **logical schema**, chưa phải SQL migration. **Đã xác minh qua ảnh**: Shield User PK hiện tại là `dinkitech_users.id INT UNSIGNED`; MariaDB 10.4.32. **Chưa xác minh qua `SHOW CREATE TABLE`**: InnoDB engine/FK, bảng Import thực tế, database prefix, timezone/isolation và precision timestamp. MariaDB 10.4 cho phép `DATETIME(6)`, nhưng migration phải chọn precision nhất quán dựa trên kết quả DEV clone; tuyệt đối không thiết kế dựa trên `SKIP LOCKED`/`JSON_TABLE`.


| Bảng | Trường chủ yếu | Khóa và index |
|---|---|---|
| `notification_settings` | `setting_key`, `default_value` TINYINT(1), `allow_user_override` TINYINT(1), `revision` BIGINT UNSIGNED, `override_epoch` BIGINT UNSIGNED, `updated_by` **INT UNSIGNED NULL**, `updated_at` | PRIMARY KEY `(setting_key)`; seed mọi key trong registry, kể cả UNAVAILABLE; row SYSTEM duy nhất mỗi key |
| `notification_user_preferences` | `id`, `user_id` **INT UNSIGNED NOT NULL**, `setting_key`, `override_value` TINYINT(1) NULL, `override_epoch` BIGINT UNSIGNED, `user_revision` BIGINT UNSIGNED, `updated_at` | UNIQUE `(user_id,setting_key)`; index theo `user_id`; FK về Shield physical User PK sau khi engine/FK được xác minh. Row NULL là reset tombstone |
| `notification_outbox` | `id` BIGINT UNSIGNED, `event_key`, `event_type`, `source_type`, `source_id` BIGINT UNSIGNED (source đa hình), `payload_json`, `status`, `attempts`, `available_at`, `lease_token`, `leased_until`, `last_error_code`, `created_at`, `processed_at` | UNIQUE `(event_key)`; index claim `(status,available_at,id)`, recovery `(status,leased_until,id)`, lookup batch `(status,lease_token,id)` |
| `user_notifications` | `id` BIGINT UNSIGNED, `recipient_user_id` **INT UNSIGNED NOT NULL**, `event_key`, `type`, `priority`, `title_key`, `body_key`, `params_json`, `target_type`, `target_id`, `read_at`, `created_at`, `expires_at` | UNIQUE `(recipient_user_id,event_key)`; index Inbox `(recipient_user_id,created_at,id)`; index unread `(recipient_user_id,read_at,created_at,id)`; FK Shield sau khi xác minh |
| `notification_setting_audit` | `id`, `setting_key`, `operation`, `before_json`, `after_json`, `actor_user_id` **INT UNSIGNED NULL**, `request_id`, `created_at` | Index `(setting_key,created_at,id)`; append-only; ghi cùng transaction với policy; không xóa lịch sử khi hard-delete User |

### 6.1 Ràng buộc triển khai vật lý trên MariaDB 10.4.32

1. **Khóa ngoại Shield:** sử dụng `config('Auth')->tables['users']` và hàm prefix của kết nối để trỏ tới đúng bảng vật lý `dinkitech_users` của môi trường này; kiểu FK phải là `INT UNSIGNED` chính xác. Chỉ tạo FK sau khi `SHOW CREATE TABLE` chứng minh bảng cha và bảng con dùng InnoDB, index và charset phù hợp. Không dùng `ON DELETE CASCADE` cho `user_notifications`/`notification_user_preferences`; đề xuất `RESTRICT/NO ACTION` và quy trình hard-delete/retention riêng. `notification_setting_audit.actor_user_id` nên giữ dấu vết ID bất biến, tránh cascade làm mất audit.
2. **ID phân phối:** giữ `user_notifications.id`/`notification_outbox.id` kiểu `BIGINT UNSIGNED` nếu cần mở rộng số bản ghi; điều đó **không làm** `recipient_user_id` thành BIGINT. Các foreign key đến `setting_key` phải cùng độ dài/charset/collation giữa bảng Settings và Preferences; nên dùng `VARCHAR` ASCII/collation ổn định được chốt khi migrate.
3. **JSON và thời gian:** JSON trong 10.4 là `LONGTEXT` có kiểm tra `JSON_VALID` khi khai báo `JSON` alias; DB + application đều validate schema/size. Chọn `DATETIME(6)` theo cùng precision ở Inbox/Outbox nếu được xác minh trên DEV và thống nhất clock `UTC_TIMESTAMP(6)`; không pha `DATETIME` giây với microsecond trong cutoff/lease. Không dùng `JSON_TABLE`.
4. **Migration 10.4:** các lệnh DDL không có cơ chế atomic/crash-safe như từ 10.6. Mỗi migration phải idempotent theo trạng thái hiện hữu, sao lưu trước, diễn tập lỗi giữa chừng trên DEV clone rồi kiểm tra/khôi phục schema; không coi `ROLLBACK` transaction của PHP là có thể hoàn nguyên `CREATE TABLE`/`ALTER TABLE`.
5. **Xóa User:** khi soft-delete (`deleted_at` khác NULL) hoặc `active=0`, worker không phân phối mới; Inbox đã lưu vẫn theo retention/ownership. Khi cần hard-delete, phải giải quyết FK và retention theo thủ tục riêng, không làm mất audit hoặc âm thầm cascade Inbox.

### 6.2 Quy ước và bất biến dữ liệu


- Tên field **DB** là `setting_key` để tránh dùng từ dành riêng `KEY` trong MariaDB; trường **API** vẫn là `key` và service phải map rõ ràng. `user_id` và recipient lấy từ Shield hoặc nguồn nghiệp vụ tin cậy, không nhận từ request cá nhân. Worker resolve người tạo Import bằng `import_jobs.created_by` đọc từ DB.
- `event_key` do backend tạo; với Import MVP dùng duy nhất `import-job:{id}:terminal`. `event_type` xác định chính xác `COMPLETED`, `COMPLETED_WITH_ERRORS` hoặc `FAILED` sau transition hợp lệ. Import Job chỉ được terminal **một lần** (guard trạng thái và UNIQUE event_key). Migration nguồn khai báo `import_jobs.created_by BIGINT UNSIGNED NULL`; trước khi lấy `User.id INT UNSIGNED` phải kiểm tra kiểu/range và sự tồn tại, không làm FK bằng phép chuyển kiểu mù.
- `target_type` phải thuộc registry route an toàn; `target_id` từ dữ liệu tin cậy. Không lưu URL ngoài, HTML, PII nhạy cảm, token/mật khẩu hoặc số liệu tài chính chi tiết trong notification params/outbox payload.
- Outbox status hợp lệ: `PENDING`, `LEASED`, `RETRY`, `PROCESSED`, `SUPPRESSED`, `DEAD`; `attempts` tính **số lần claim xử lý**, terminal status không requeue tự động.
- `notification_settings` là nguồn chính sách System; không dùng bảng `settings` của Display. Trạng thái `capability`/`producer_state` lấy từ registry/feature gate backend, không cho client sửa; không dùng `producer_state` như một giá trị ghi đè User. `notification_setting_audit` được chọn làm **nguồn audit duy nhất cho Notification policy** tại MVP. Không ghi thêm sự kiện trùng vào `configuration_audit_events`; quyết định reuse chỉ được thay bằng ADR sau DB audit.
- `dismissed_at` **không có trong MVP** vì chưa có API thao tác dismiss; `expires_at` dùng để loại các mục đã hết hạn khỏi list, summary và read mutations theo cùng điều kiện. Nếu không có TTL riêng thì `expires_at = NULL`.
- DB lưu timestamp UTC, response ISO 8601. MariaDB 10.4.32 hỗ trợ độ chính xác microsecond; nếu chọn `DATETIME(6)` thì `UTC_TIMESTAMP(6)` và kiểu cột phải nhất quán trên DEV clone; không lấy timestamp từ frontend.
- Retention đề xuất: Inbox thường 90 ngày, outbox terminal 30 ngày, audit tối thiểu 365 ngày. Không tự xóa thông báo an ninh/cần xử lý bắt buộc khi chưa chốt SLA; cần quy tắc retention theo loại và chính sách vận hành. Worker cleanup chạy batch và không xóa event chưa terminal/DEAD chưa xử lý.
- Khi User disabled/deleted, không phân phối tiếp. Danh tính người dùng cũ bị vô hiệu hóa phải được xử lý theo quy định retention/FK, không phá toàn bộ bảng thông báo do cascade không mong muốn.

## 7. API Contract — 8 operations dự kiến

Tất cả đường dẫn dưới đây có tiền tố `/api/v1`, dùng session Shield, CSRF với PATCH/POST, chuẩn `ApiResponder`/`ApiEnvelope` hiện có. **Đây là hợp đồng đề xuất, chưa tồn tại trong source.** Giữ 8 operations, không bổ sung endpoint quản trị User khác, không có POST phát Notification tùy ý.

| Method | Path | Kết quả chính | Authorization |
|---|---|---|---|
| GET | `/notification-settings` | Registry + chính sách SYSTEM, version | `notification_setting.view` + PBAC |
| PATCH | `/notification-settings` | Bulk update atomic + audit | `notification_setting.edit` + PBAC |
| GET | `/notifications/preferences` | System/User/Effective của chính User | Shield session |
| PATCH | `/notifications/preferences` | Bulk set/reset override, CAS | Shield session + policy hiện hành |
| GET | `/notifications/summary` | `unread_count` | Shield session |
| GET | `/notifications` | Inbox lọc + cursor | Shield session |
| POST | `/notifications/{id}/actions/mark-read` | Mark-read một mục của mình, idempotent | Shield session + ownership |
| POST | `/notifications/actions/mark-all-read` | Mark-read các mục hiện tại trước cutoff DB | Shield session + ownership |

### 7.1 GET/PATCH chính sách hệ thống

```json
{
  "data": [
    {
      "key": "import.completed.in_app",
      "default_value": true,
      "allow_user_override": true,
      "mandatory": false,
      "capability": "AVAILABLE",
      "producer_state": "INACTIVE",
      "revision": 3,
      "override_epoch": 2
    }
  ],
  "meta": {"request_id": "..."}
}
```

```json
{
  "changes": [
    {
      "key": "import.completed.in_app",
      "default_value": false,
      "allow_user_override": false,
      "expected_revision": 3
    }
  ]
}
```

- `PATCH` là partial update theo từng key nhưng **mỗi change phải có hai trường chính sách** (`default_value`, `allow_user_override`) và `expected_revision`; không nhận `mandatory`, `capability`, `producer_state`, `revision` hay `override_epoch` từ client.
- Kết quả `PATCH` trả `{data:[updated rows],meta:{request_id}}`. Key không hợp lệ, chưa AVAILABLE, duplicate, mandatory bị vi phạm → 422. Thiếu quyền → 403. Revision cũ → 409; lỗi database → response lỗi chuẩn, không trả success giả.
- GET chỉ trả thông tin System cho người có quyền quản trị; trang Preferences User có endpoint riêng để nhận default và cờ khóa của các key khả dụng. Không dùng `display_setting.edit` thay thế quyền mới.
- Response có `Cache-Control: private, no-store`; chỉ load một lần khi mở tab, invalidate khi lưu.

### 7.2 GET/PATCH Preferences cá nhân

```json
{
  "data": [
    {
      "key": "import.completed.in_app",
      "system_default": true,
      "allow_user_override": true,
      "user_override": false,
      "effective_value": false,
      "effective_source": "USER",
      "override_epoch": 2,
      "user_revision": 5,
      "locked": false,
      "capability": "AVAILABLE",
      "producer_state": "INACTIVE"
    }
  ],
  "meta": {"request_id": "..."}
}
```

```json
{
  "changes": [
    {
      "key": "import.completed.in_app",
      "value": null,
      "expected_override_epoch": 2,
      "expected_user_revision": 5
    }
  ]
}
```

- `value=null` chuyển về kế thừa **nhưng giữ row có `override_value=NULL` và tăng `user_revision`**; không xóa vật lý làm mất khả năng phát hiện phiên cũ. Nếu không có row, GET trả `user_revision=0`; PATCH phải thực hiện conditional insert/CAS.
- Nếu có override ở epoch cũ, GET trả `user_override=null` (không còn hiệu lực) **nhưng vẫn trả `user_revision` hiện có**, để User có thể ghi quyết định mới mà không tái kích hoạt giá trị cũ.
- Không nhận các trường `user_id`, `system_default`, `effective_value`, `capability`, `producer_state`, `locked`; backend lấy danh tính từ Shield session và kiểm tra epoch+revision trong transaction theo mục 4.3.
- Lỗi: key chưa khả dụng/không biết → 422; epoch stale → 409; chính sách đang khóa → 403; user revision stale → 409; chưa xác thực → 401. Các lỗi cung cấp `code` ổn định để frontend hiển thị refresh/retry, không rò rỉ dữ liệu người khác.
- `PATCH` trả các row mới với effective/source/revision. Bulk update atomic; giới hạn số key theo catalog và payload size. Phải kiểm tra request concurrent trên **cùng User/key** và trên hai key khác nhau.

### 7.3 Inbox, Summary và Read Status

```json
{
  "data": {
    "items": [
      {
        "id": "581",
        "type": "import.completed_with_errors",
        "priority": "NORMAL",
        "title_key": "notifications.import.completedWithErrors.title",
        "body_key": "notifications.import.completedWithErrors.body",
        "params": {"error_rows": 8},
        "target": {"type": "import_job", "id": "125"},
        "read_at": null,
        "created_at": "2026-10-25T09:00:00Z"
      }
    ],
    "next_cursor": null
  },
  "meta": {"request_id": "..."}
}
```

- `GET /notifications?filter=unread&page_size=20&cursor=...`: filter chỉ `all|unread`, `page_size` mặc định 20, tối đa 50. Cursor opaque/signed, ràng buộc User + filter + sort; sắp xếp `created_at DESC, id DESC`; cursor sai, hết hạn hoặc không cùng filter → 422. Khóa sắp xếp gồm ID để không bị bỏ sót khi trùng timestamp.
- `GET /notifications/summary` trả `{"data":{"unread_count":N},"meta":{...}}`. List, summary, mark-read và mark-all áp dụng **cùng điều kiện** `recipient_user_id=currentUser` và `(expires_at IS NULL OR expires_at > DB_NOW)`, tránh số đếm khác danh sách. GET list filter `unread` bổ sung `read_at IS NULL`.
- `mark-read`: UPDATE với điều kiện `id`, `recipient_user_id` của phiên đăng nhập **và chưa expired**. Mục đã read nhưng còn hiệu lực trả thành công 200/idempotent, không đổi `read_at`; ID không thuộc User, không tồn tại **hoặc đã expired** → 404. Phản hồi chuẩn envelope, chứa `id`, `read_at`.
- `mark-all-read`: lấy `cutoff` bằng đồng hồ DB và cập nhật các row **đã tạo trước cutoff** của cùng User, `read_at IS NULL`, còn hiệu lực; response trả `{updated_count, cutoff}`. Các row được tạo **sau** cutoff vẫn unread. Hợp đồng này **không hứa linearizability theo commit order** với transaction tạo notification đã mở trước cutoff nhưng commit sau đó. Nếu nghiệp vụ yêu cầu “mọi notification commit sau lần nhấn đều chưa đọc”, cần cơ chế khóa/sequence theo recipient riêng và thiết kế bổ sung (không tự nhận cutoff timestamp đã bảo đảm điều đó).
- `mark-all-read` phải dùng DB timestamp đúng precision với cột `created_at`; không lấy cutoff từ browser. Test concurrency cả hai trường hợp: tạo sau cutoff và transaction in-flight từ trước cutoff.
- Inbox trống trả `items=[]`, `unread_count=0`. Không trộn dữ liệu mẫu AppShell với dữ liệu thật. Không có thao tác dismiss/delete ở MVP; schema cũng chưa có `dismissed_at`.

### 7.4 Error/caching/compatibility

Mã lỗi đề xuất: `NOTIFICATION_SETTING_UNKNOWN`, `NOTIFICATION_SETTING_UNAVAILABLE`, `NOTIFICATION_SETTING_LOCKED`, `NOTIFICATION_SETTING_REVISION_CONFLICT`, `NOTIFICATION_PREFERENCE_STALE`, `NOTIFICATION_PREFERENCE_REVISION_CONFLICT`, `NOTIFICATION_NOT_FOUND`, `NOTIFICATION_SERVICE_UNAVAILABLE`. Mỗi `code` ánh xạ duy nhất sang HTTP status, OpenAPI và i18n `vi/en`. Mọi GET chứa dữ liệu người dùng đều `Cache-Control: private, no-store`; không dùng shared cache/quyết định quyền xuyên phiên.

## 8. Transactional Outbox và xử lý Worker

### 8.1 Import Job: giao dịch terminal riêng, không bao toàn bộ Import

Source `ImportJobService::start()` hiện ghi từng row/journal và cập nhật terminal qua `setJob()` trong `try/catch`. Khi bổ sung Outbox phải **tách lỗi nghiệp vụ ra khỏi lỗi finalize**, nếu không lỗi ghi Outbox có thể vô tình làm `catch` thử chuyển Job đã xử lý thành `FAILED` rồi che nguyên nhân.

Đề xuất refactor thành phương thức terminal có guard trạng thái, dùng cùng `BaseConnection`/transaction InnoDB:

```text
finalizeImport(jobId, desiredTerminal, counters):
  BEGIN
    SELECT status, created_by FROM import_jobs WHERE id=jobId FOR UPDATE
    REQUIRE status=PROCESSING
    UPDATE import_jobs SET status=desiredTerminal,
        completed_at=DB_NOW, counters=..., updated_at=DB_NOW
      WHERE id=jobId AND status=PROCESSING
    REQUIRE affected_rows=1
    INSERT notification_outbox(
      event_key='import-job:{id}:terminal',
      event_type=validatedTerminalEvent(desiredTerminal),
      source_type='import_job', source_id=jobId, status=PENDING, ...)
  COMMIT
```

- Không dùng frontend làm nguồn event/status/recipient. `created_by` lấy từ bản ghi `import_jobs` có thật. `event_key` UNIQUE, việc chạy lại finalize với terminal hiện có không tạo event thứ hai.
- Kết quả xử lý từng row và giao dịch nghiệp vụ đã commit **không được rollback lại** chỉ vì Outbox hỏng. Nếu final-status+outbox transaction thất bại, bản ghi vẫn `PROCESSING` (hoặc trạng thái trước finalize), báo lỗi vận hành và đưa vào **reconciliation**; tuyệt đối không gọi fallback `finalize(FAILED)` chỉ vì lỗi Outbox của bước finalize `COMPLETED`.
- Nếu bản thân quá trình import có lỗi nghiệp vụ, code phân loại `FAILED` rồi gọi **cùng một** `finalizeImport(...FAILED...)` để ghi trạng thái lỗi và outbox trong một transaction. Nếu DB không thể ghi, giữ nhật ký có sẵn để operator điều tra; không tuyên bố job đã terminal.
- Reconciliation chỉ được thao tác bởi command vận hành có audit: khóa row, đọc journal/counters, phân biệt job đang xử lý thật với job treo, quyết định terminal hợp lệ, rồi gọi đường finalize chuẩn. Không tự đổi `PROCESSING` thành `COMPLETED` theo timeout.
- Không mở transaction xung quanh toàn bộ file import; không tạo thêm các event phụ trước terminal ở MVP.

### 8.2 Workflow (M11.2)

Trong `BusinessWorkflowService::execute()`, INSERT outbox **sau** update trạng thái chứng từ và **trước** `transCommit()`. Event phải mang state/action/event_key đủ bất biến; resolver người nhận cần dựa vào giao việc hoặc phân công tin cậy, không broadcast bừa cho tất cả User có PBAC view. Với thay đổi assignment giữa lúc tạo và phân phối, phải chốt snapshot/recipient policy bằng ADR M11.2 trước code.

### 8.3 Trạng thái Outbox và claim protocol

Các trạng thái: `PENDING` → `LEASED` → `PROCESSED|SUPPRESSED`; lỗi tạm thời `LEASED` → `RETRY` → `LEASED`; quá giới hạn/không phục hồi → `DEAD`. `SUPPRESSED` dành cho event hợp lệ nhưng không người nhận/kênh có hiệu lực, **không** dùng để che payload lỗi hoặc resolver không xác định được nguồn User.

Command MVP `php spark notifications:dispatch --once --batch=25` (batch có thể cấu hình sau benchmark). **Mặc định chỉ một worker định kỳ** trên XAMPP/MariaDB 10.4.32; vẫn bắt buộc kiểm thử khi hai worker cùng chạy để chứng minh không phát trùng. Mỗi worker:

1. Claim các event sẵn sàng (`PENDING|RETRY` hoặc `LEASED` đã hết hạn) trong **transaction ngắn có `SELECT ... FOR UPDATE` (không `SKIP LOCKED`)**. Chọn ID có `ORDER BY id LIMIT :batch`, khóa row trong cùng transaction, rồi UPDATE theo **danh sách ID đã khóa** với điều kiện trạng thái còn hợp lệ, gán lease token ngẫu nhiên, `leased_until` theo UTC DB clock, tăng `attempts`; commit trước khi xử lý. Trong worker có thể phân 2 lượt chọn `PENDING|RETRY` và lease hết hạn để tận dụng hai index. Bounded lock-wait/deadlock retry có jitter; không giữ transaction khi xử lý nhiều event.
2. Đọc event/schema/nguồn nghiệp vụ và tính danh sách người nhận tin cậy. Với Import, `created_by BIGINT UNSIGNED NULL` phải được kiểm tra phạm vi `INT UNSIGNED` trước khi lookup Shield User; `created_by=NULL` (Job hệ thống) hoặc User hợp lệ nhưng inactive/soft-deleted → không phát và kết thúc `SUPPRESSED` có lý do; `created_by` ngoài phạm vi hoặc trỏ đến User không tồn tại → lỗi nguồn/resolver `DEAD`/retry theo chính sách, không `SUPPRESSED` âm thầm. Sai schema, key không biết hay Job không tồn tại cũng phải có mã lỗi vận hành.
3. Mở **transaction phân phối**, khóa lại outbox row và xác minh `status=LEASED`, `lease_token` còn sở hữu và lease chưa hết; kiểm tra `producer_state=ACTIVE` và policy trên snapshot nhất quán. Với **toàn bộ người nhận của event** trong giới hạn batch cho phép, đọc capability + effective preference trên snapshot nhất quán, INSERT Inbox theo UNIQUE `(recipient_user_id,event_key)`, sau đó UPDATE chính outbox row thành `PROCESSED` (có Inbox) hoặc `SUPPRESSED` (không có Inbox) **trong cùng transaction**. Không gửi HTTP/Email trong transaction.
4. Nếu bất kỳ INSERT/UPDATE nào thất bại: rollback **cả Inbox insert lẫn ACK**, điều phối retry theo backoff. Không tồn tại trạng thái “một phần recipient được ghi nhưng event đã ACK”. Với M11.1B Import chỉ một recipient nên đơn giản; M11.2 phải xác định ngưỡng fanout và tách delivery ledger nếu quy mô vượt khả năng transaction hữu hạn.
5. ACK/RETRY/DEAD luôn có điều kiện đúng `lease_token`; worker hết hạn token không được đổi trạng thái hay ACK event đã được worker khác claim. Worker crash → lease hết hạn → claim lại; UNIQUE Inbox và ACK atomic chống tạo trùng.

**Claim protocol đã chốt cho MariaDB 10.4.32:** dùng **`SELECT ... FOR UPDATE` trong transaction và UPDATE chỉ các ID đang khóa**; không gửi `SKIP LOCKED` vì 10.4 không hỗ trợ. Pseudocode cho một batch:

```sql
START TRANSACTION;
SELECT id FROM notification_outbox
 WHERE status IN ('PENDING', 'RETRY') AND available_at <= UTC_TIMESTAMP(6)
 ORDER BY id LIMIT 25 FOR UPDATE;
-- Nếu không có ID, có thể thử một lượt riêng với status='LEASED'
-- AND leased_until <= UTC_TIMESTAMP(6), cũng FOR UPDATE.
-- Với đúng các ID đã khóa (bind tham số, không nối chuỗi từ HTTP):
UPDATE notification_outbox
 SET status='LEASED', lease_token=:random_token,
     leased_until=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL :lease_seconds SECOND),
     attempts=attempts+1
 WHERE id IN (:locked_ids) AND status IN ('PENDING', 'RETRY');
COMMIT;
-- Sau COMMIT: đọc batch bằng (status='LEASED', lease_token=:random_token).
```

Pseudocode chỉ minh họa **thuật toán**, không phải SQL copy-paste: thư viện phải bind danh sách ID và `INTERVAL` phù hợp MariaDB 10.4. `lease_token` là chuỗi ngẫu nhiên đủ entropy; không truy vấn batch theo token rỗng. Luồng recovery cho `LEASED` hết hạn dùng **predicate phù hợp riêng** trong cả SELECT và UPDATE. Tránh `UPDATE ... ORDER BY ... LIMIT` như một shortcut không được kiểm định vì MariaDB cảnh báo statement này có thể không an toàn khi replication theo statement. Bắt buộc thử hai worker, crash/lease expiry, khóa chờ và deadlock trên DEV 10.4; worker thứ hai có thể phải chờ row lock, không kỳ vọng throughput ngang `SKIP LOCKED`. Không `SELECT` event ngoài transaction rồi `UPDATE` không điều kiện; không chạy xử lý lâu trong web request.


**Retry định lượng:** tối đa **6 lần xử lý** (lần đầu + 5 retry), khoảng đợi sau thất bại lần lượt 1/5/15/60/240 phút. Sau 6 lần không thành công → `DEAD`, có metric/log và Spark command requeue có xác thực vận hành; không mở HTTP requeue cho User thường. Cần giám sát số `PENDING`, event cũ nhất, `RETRY`, `DEAD`, lease expired và latency.

### 8.4 Định nghĩa tính bảo đảm

- **At-least-once processing** của worker, **effectively-once Inbox rows trong thời gian lưu giữ** cho mỗi `(recipient,event_key)` nhờ unique key và giao dịch atomic; không tuyên bố exactly-once delivery cho Email/Push tương lai. Không tự replay event `PROCESSED|SUPPRESSED` sau khi Inbox/outbox đã được dọn; nếu cần replay lịch sử phải bổ sung receipt/idempotency archive và ADR riêng.
- Preferences được chốt tại transaction phân phối thành công; event `SUPPRESSED` không được tự phát lại sau khi người dùng bật cấu hình.
- Event được tạo sau business COMMIT không hợp lệ: chỉ business transaction ghi outbox cùng lúc; không dùng `afterCommit` callback làm nguồn duy nhất.
- Windows Task Scheduler có thể chạy Spark `--once` theo lịch trên XAMPP; đo độ trễ gồm chu kỳ worker + polling frontend, không quảng cáo real-time.

## 9. Bảo mật, PBAC và nguồn dữ liệu tin cậy

| Thao tác | Điều kiện bắt buộc |
|---|---|
| GET/PATCH Notification System Settings | `notification_setting.view/edit`: base permission **và** UnifiedAccessService/PBAC trên row System tin cậy |
| GET/PATCH Preferences cá nhân | Shield session + known key + policy hiện hành; `user_id` lấy từ session, không áp dụng quyền admin |
| GET/POST Inbox | Ràng buộc `recipient_user_id = currentShieldUserId` trong **mọi SELECT/UPDATE**, kể cả summary/cursor |
| Mở chứng từ đích | Map `target_type` sang route nội bộ allowlist; API của chứng từ **luôn** tái kiểm tra PBAC hiện hành |
| Worker | CLI/service identity với DB permission tối thiểu, không chấp nhận event/recipient tùy ý qua HTTP |
| PATCH System policy | Revision CAS + transactional audit + trusted actor/request ID; mọi lỗi rollback |

### 9.1 Đăng ký Resource mới đúng mô hình ERP

`notification_setting` **chưa nằm trong** `ResourceStateCatalog::CODES` ở source gốc. Nếu chỉ seed resource, hệ thống sẽ mặc định hiểu đây là `LIFECYCLE` và yêu cầu lifecycle_id, trái với bản chất cấu hình. Việc triển khai M11.1A **bắt buộc**:

1. Bổ sung `notification_setting => []` vào `ResourceStateCatalog` để dùng `ALL_STATES`, không có Lifecycle/Native State.
2. Bổ sung `notification_setting => 'SYSTEM'` vào danh sách approved read domains của `UnifiedAccessService`; Resource seeder khai báo domain `SYSTEM`, các actions `view`, `edit`.
3. Gọi `decidePersisted('notification_setting','view', persistedSettingsRow)` và `decideMutation('notification_setting','edit', lockedPersistedSettingsRow)` trên **row thật đã đọc từ `notification_settings`**, không tạo mock target tùy tiện và không dựa trên cờ frontend. Với domain `SYSTEM`, PBAC dùng GROUP root nội bộ như đã thiết kế; không đọc organization_id từ request.
4. Cập nhật kiểm thử resource catalog/native count hiện đang cố định (10 loại độc lập, trong đó 2 ALL_STATES), tương ứng thành 11 loại độc lập với 3 ALL_STATES; thêm parity/mutation denial regression và seed idempotency.
5. Đảm bảo Resource/Action chưa được cấp quyền mặc định từ chối. Quyền quản trị cho actor pilot phải được cấp bằng quy trình PBAC kiểm soát, không sửa implicit allow trong code.

`allow_user_override` **không phải** một permission code PBAC. User không có `notification_setting.edit` vẫn được cập nhật Preferences của mình nếu key cho phép. Không cung cấp API quản trị Preferences của người khác trong MVP.

### 9.2 Bảo mật dữ liệu và phiên

- Chỉ lưu `title_key`, `body_key`, params tối thiểu không nhạy cảm; text được render qua i18n template allowlist và escaping, không chấp nhận HTML/script/URL từ payload. Đặt giới hạn payload size, độ dài key và số params.
- Nếu quyền đọc chứng từ đích đã bị thu hồi, Inbox có thể còn một thông báo tối giản, nhưng click vào target phải bị chặn bằng 403/404 tại API đích. Không dùng Notification để xác nhận quyền sửa/chuyển trạng thái.
- CSRF với PATCH/POST; có rate limit cho thao tác bulk; token/session expired trả 401; truy cập ID notification của User khác trả 404 như không tồn tại. Có kiểm thử cache không lộ dữ liệu khác User.
- Quản trị tab Notifications cần quyền riêng ngay ở route/tab. Người có `notification_setting.view` nhưng không có `display_setting.view` **không** được fetch dữ liệu Display Settings khi mở deep-link `?tab=notifications`.
- Đồng bộ request generation/AbortController khi logout/đổi User. Server luôn ràng buộc ownership, dù UI gửi request cũ trước lúc logout.

## 10. Thiết kế frontend và UX

### 10.1 `/system/display-settings?tab=notifications`

- Giữ URL cũ và `tab=display` mặc định. **Refactor container trang thành tab-router**: chỉ mount/call Display services khi tab `display` được chọn và qua `display_setting.view`; chỉ mount/call Notification services khi tab `notifications` được chọn và qua `notification_setting.view`. Không để `onMounted()` hiện hữu của Display tự fetch khi đang mở Notifications.
- Cấu hình Notifications ở MVP chỉ có scope `SYSTEM`. Không hiển thị bộ chọn Organization trong tab này; backend **từ chối** body/query có scope Organization, không im lặng bỏ qua.
- Mỗi key AVAILABLE có hai control: `Mặc định` và `Cho User ghi đè`. Key mandatory hiện khóa `true/false` cố định; key UNAVAILABLE read-only kèm lý do; key AVAILABLE nhưng producer INACTIVE vẫn cấu hình được với nhãn “Chưa phát thông báo”. Thao tác Lưu yêu cầu base permission và PBAC backend; User chỉ có view nhìn thấy dữ liệu nhưng không có control lưu.
- `expected_revision` lấy từ GET; khi 409, thông báo xung đột và tùy chọn tải lại, không tự ghi đè. Khi tắt override, hiển thị rõ mọi User sẽ dùng default hiện hành; hiệu lực được tính ở backend, không tính cục bộ để quyết định quyền.
- Hiển thị thông báo audit đã ghi nhận **chỉ sau khi API thành công**. Lỗi không làm mất draft trong UI, nhưng không biến draft thành persisted.

### 10.2 `/profile/display-preferences?tab=notifications`

- Tab `display` tiếp tục lưu LocalStorage; tab `notifications` dùng REST/server state. Không lưu Notification preferences trong LocalStorage hoặc giả định User có một session duy nhất.
- Các lựa chọn `Kế thừa / Bật / Tắt` chỉ enabled khi `capability=AVAILABLE` và `allow_user_override=true`; hiển thị `effective_value`, nguồn `SYSTEM_DEFAULT|USER|SYSTEM_LOCKED|MANDATORY|CHANNEL_UNAVAILABLE` và `producer_state` để cảnh báo “Chưa có thông báo thực tế” trong M11.1A.
- PATCH phải gửi cả `expected_override_epoch` và `expected_user_revision` theo row đã GET; `value=null` nghĩa reset, **không xóa row phiên bản**. Nếu policy thay đổi hoặc User khác phiên cập nhật, backend trả 409/403; UI làm mới row rồi để User xác nhận lại.
- Giới hạn số request: mở tab tối đa một GET Preferences; không fetch danh mục User/Group hay gọi GET trên từng key. Những key tương lai chỉ hiển thị read-only/ẩn có chú thích rõ ràng.

### 10.3 Chuông AppShell và Inbox

- Khi backend feature flag cho phép bật Notifications: bỏ **cả hai** mục mock khỏi AppShell, dùng Pinia store cho unread summary và list. Khi feature flag tắt, không pha trộn thông báo mẫu với dữ liệu thật để tránh sai số.
- Khi đã xác thực và `document.visibilityState === 'visible'`, poll summary 60 giây; khi focus/mở chuông refresh ngay nhưng deduplicate request đang chạy. Mở popover load danh sách một lần, không GET detail từng notification, không mark-read tự động.
- Khi logout/đổi User: AbortController + session generation, clear store, invalidation query, không chấp nhận response cũ. Khi nhận 401, ngừng polling cho đến phiên mới.
- Inbox `/notifications` có filter `all|unread`, cursor pagination 20 mục; hiển thị rõ loading/empty/error, keyboard, aria-label, focus, dark mode và i18n vi/en. `expires_at` được backend lọc đồng nhất giữa count/list.
- Route target từ registry nội bộ (không lấy URL tùy ý từ payload), click không tự bypass PBAC; mở target có thể bị từ chối nếu quyền bị thu hồi. Click vào chuông **không** đánh dấu mọi thứ đã đọc.

## 11. Kế hoạch triển khai tuần tự và rollback

| Mốc | Backend | Frontend / hợp đồng | Gate |
|---|---|---|---|
| **M11.0 – Review** | Kiểm tra DEV schema, PBAC resource/Action, audit decision, Import failure-path | Chốt tài liệu v1.2, API shape, error mapping, wireframe | Kiến trúc và các giả định DEV được duyệt |
| **M11.1A – Core + Settings** | Migrate 5 bảng; registry (Import keys configurable, producer INACTIVE), policy resolver + user revision CAS, audit; 4 settings/preferences APIs; PBAC ALL_STATES/SYSTEM | 2 tab độc lập, OpenAPI/codegen, feature flag | Truth table + concurrency + PBAC/transaction PASS |
| **M11.1B – Inbox + Worker** | 4 Inbox APIs; outbox worker, lease/ACK transaction, Import terminal finalize; activate Import producers | AppShell bell, `/notifications`, Pinia + polling | Retry/idempotency, Import rollback & browser E2E PASS |
| **M11.2 – Events** | Workflow outbox + trusted recipient resolver + multi-recipient policy | Target links + status UX | PBAC/recipient/assignment race + fanout PASS |
| **M11.3 – Channels** | Email/Push transport + khả năng bật kênh, opt-in | Chỉ bật controls đã deploy | Delivery/retry/opt-in/consent PASS |

### 11.1 Các thay đổi code dự kiến

- Backend `app/Modules/Notifications/{Catalog,Settings,Preferences,Delivery,Inbox}`, API controllers/repositories, `app/Commands/NotificationsDispatch.php` và command reconciliation/requeue có kiểm soát.
- Update `ResourceStateCatalog.php` (`ALL_STATES`) và `UnifiedAccessService.php` (approved domain `SYSTEM`), ResourceAction seeder cùng pure tests hard-code số lượng các loại độc lập. **Không** đổi ngầm policy precedence hoặc bỏ guard cũ.
- Frontend `src/features/notifications/`, `src/stores/notifications.ts`, chuông AppShell, tabs settings độc lập, route Inbox và SDK sinh từ OpenAPI.
- Test: pure policy resolver, repository/SQL integration với DEV clone, controller HTTP/CSRF, worker concurrency và Playwright ba trình duyệt.

### 11.2 Quy trình release

1. **DEV audit bắt buộc:** Đã có bằng chứng **MariaDB 10.4.32, `dinkitech_users.id INT UNSIGNED`** từ người dùng; vẫn cần xác minh qua SQL read-only `SHOW CREATE TABLE` cho Shield Users, `import_jobs`, `configuration_audit_events`, resources & actions; kiểm tra DB prefix, FK, isolation, engine/charset, timezone precision và index. Không chạy migration trên DB `erp` đang dùng trước khi clone/backup. **Không thử `SKIP LOCKED` trên 10.4.**
2. **Migration/seed:** thêm 5 bảng trên **MariaDB 10.4** với `INT UNSIGNED` cho các cột Shield User FK, không ép thành BIGINT; chạy backup và test phục hồi migration DDL bị dừng giữa chừng (**10.4 chưa có atomic DDL như 10.6**). Seed registry keys **kể cả các key tương lai**; Import In-App AVAILABLE để cấu hình từ M11.1A nhưng producer INACTIVE đến M11.1B, các key sau M11.1B vẫn UNAVAILABLE/read-only; revision/epoch khởi tạo nhất quán; seed `notification_setting` + `view/edit` idempotent. Chặn key chưa AVAILABLE ở cả admin/user PATCH. Test clean migrate và migrate lần hai không tạo trùng.
3. **Contracts:** cập nhật OpenAPI/SDK/route manifest để thêm 8 operations mà không làm sai 150 operation cũ. Không xóa/ghi đè fingerprint hợp đồng cũ nếu không có phê duyệt thay đổi version.
4. **Feature flags & vận hành:** deploy backend với `notifications.enabled=false`, worker dừng; kiểm tra contract/PBAC/CSRF/migration. Bật từng bước API settings → Inbox/worker → chuông pilot; không có dữ liệu Notifications giả trên production.
5. **DEV/Pilot:** xác minh retry/lease, cross-user denial, 409 concurrency, effective preference, request budget; đo metrics; rollback drill trước khi full rollout.
6. **Rollback:** tắt flag và dừng worker. Không drop bảng/Audit chứa dữ liệu, không tạo event trùng khi bật lại. Data migration rollback phá dữ liệu chỉ chạy theo quy trình backup và duyệt riêng.

### 11.3 Bộ lệnh xác minh DB chỉ đọc trên DEV clone

Các lệnh dưới đây dùng để **đối chiếu**, không phải migration; cần chạy trên bản sao DEV đã xác nhận, không tiết lộ nội dung User/PII:

```sql
SELECT VERSION() AS mariadb_version, DATABASE() AS active_database,
       @@tx_isolation AS transaction_isolation, @@time_zone AS session_time_zone;
SHOW CREATE TABLE `dinkitech_users`;
SHOW CREATE TABLE `import_jobs`;
SHOW CREATE TABLE `configuration_audit_events`;
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('dinkitech_users', 'import_jobs', 'configuration_audit_events');
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dinkitech_users'
ORDER BY ORDINAL_POSITION;
```

Khi `SHOW CREATE TABLE` xác nhận khác hình chụp (schema đã thay đổi), **dừng tạo migration** và cập nhật tài liệu theo DDL hiện hành. Đừng dùng cột `active`/`deleted_at` hoặc `created_by` với giả định khác DB thật.

**Ghi chú phiên bản v1.2:** MariaDB và User ID đã được xác nhận từ thông tin do người dùng cung cấp; engine/DDL/FK/isolation/Import table trên DB thật vẫn cần xác minh. Các command, migration và QA runtime chưa được thực thi.


## 12. Kế hoạch QA / Acceptance Criteria

| ID | Kịch bản | Kỳ vọng |
|---|---|---|
| CFG-01 | Default=true, locked | Effective=true, SYSTEM_LOCKED |
| CFG-02 | Default=false, locked | Effective=false, SYSTEM_LOCKED |
| CFG-03 | Default=true, User=false hợp lệ | Effective=false, USER |
| CFG-04 | Default=false, User=true hợp lệ | Effective=true, USER |
| CFG-05 | User reset `null` | Effective theo System, row NULL giữ revision |
| CFG-06 | Override → admin khóa → mở lại | `override_epoch` tăng; override cũ không sống lại |
| CFG-07 | Đổi default khi override còn cho phép | Không tăng epoch; User override tiếp tục hiệu lực |
| CFG-08 | Mandatory bị admin PATCH tắt/cho ghi đè | 422, DB và audit không đổi |
| CFG-09 | PATCH settings với stale revision | 409, atomic rollback, không ghi audit sai |
| CFG-10 | PATCH User khi key khóa hoặc epoch stale | 403/409 đúng thứ tự; không ghi user row |
| CFG-11 | Hai phiên PATCH cùng User/key, cùng epoch | Chỉ một thành công, một 409 user_revision |
| CFG-12 | User reset rồi phiên cũ PATCH | Tombstone revision chặn stale write |
| CFG-13 | Concurrent admin lock và User PATCH | Policy row lock/epoch CAS không có cửa sổ ghi lậu |
| CFG-14 | Key UNAVAILABLE kể cả mandatory tương lai | Effective=false, cả hai PATCH trả 422 |
| CFG-14A | M11.1A cấu hình Import khi producer còn INACTIVE | GET/PATCH/truth-table hoạt động; không phát Outbox trước M11.1B |
| CFG-15 | GET Preferences khi stored epoch cũ | `user_override=null`, `user_revision` vẫn phản ánh row |
| CFG-16 | User có Notification view, không có Display view | Chỉ tab Notifications fetch, không gọi Display APIs |
| CFG-17 | Backend resource `notification_setting` ALL_STATES/SYSTEM | Pure catalog/PBAC/seed + denial tests PASS |
| CFG-18 | Audit write lỗi trong PATCH admin | Rollback policy+audit, không có success giả |
| OUT-01 | Import job PROCESSING → terminal và Outbox | Cùng commit; đúng một event_key |
| OUT-02 | INSERT Outbox thất bại khi terminal thành công | Final update rollback; **không** fallback đổi job thành FAILED |
| OUT-03 | Import nghiệp vụ lỗi | Finalize FAILED + Outbox nếu DB sẵn sàng; journal không mất |
| OUT-04 | Import chưa terminal / rollback | Không tạo notification thành công giả |
| OUT-05 | Hai workers claim một event | Token/lease, chỉ 1 worker có thể ACK |
| OUT-06 | Worker crash trước COMMIT phân phối | Inbox và ACK cùng rollback; retry không trùng |
| OUT-07 | Worker crash sau COMMIT phân phối | Event terminal, Inbox không trùng; không gửi lại |
| OUT-08 | Expired lease / worker cũ xử lý muộn | Token cũ không thể ghi/ACK; recovery thành công |
| OUT-09 | Pref tắt trước dispatch | SUPPRESSED, không replay khi bật lại |
| OUT-10 | Payload hỏng/recipient resolver lỗi | DEAD/retry có mã lỗi, không SUPPRESSED giả |
| OUT-11 | Đa recipient M11.2, một insert thất bại | Không ACK một phần; transaction toàn bộ rollback |
| OUT-12 | 6 lần xử lý lỗi liên tiếp | 1 initial + 5 retries, sau đó DEAD + metric |
| OUT-13 | Retention xóa Inbox/outbox terminal | Không tự replay event terminal; không tạo thông báo cũ mới |
| INB-01 | Mark-read hai lần | Idempotent, read_at bất biến |
| INB-02 | Mark-all + notification được tạo sau cutoff DB | Mục mới vẫn unread; phản hồi có cutoff |
| INB-03 | Mark-all vs transaction đã tạo trước nhưng commit sau | Ghi nhận đúng contract cutoff, **không** giả định linearizable |
| INB-04 | Cursor với timestamp trùng + trang mới | Thứ tự ổn định `(created_at,id)`, không trùng/bỏ mục |
| INB-05 | 50k notifications/User | Index/cursor, summary không N+1, benchmark theo mục tiêu |
| SEC-01 | User A truy cập notification ID của User B | 404, không lộ nội dung qua count/list/mark-read |
| SEC-02 | Logout/đổi User khi HTTP chưa xong | Store cũ không render ở phiên mới |
| SEC-03 | Quyền đích bị thu hồi sau Notification | API đích từ chối; notification không vượt PBAC |
| SEC-04 | CSRF/rate-limit/invalid payload | Fail closed, code và envelope chuẩn |
| E2E-01 | Chromium/Firefox/WebKit: Settings tabs + bell + Inbox | PASS cả 3, including deep link |
| E2E-02 | Vi/en, dark mode, keyboard/a11y, unavailable keys | Không regression |
| API-01 | OpenAPI lint/generate/route verify | 8 routes mới, old operation contracts không đổi |
| REL-01 | DEV migration + rollback rehearsal | Không mất dữ liệu, audit/lease có thể phục hồi |
| DB-01 | MariaDB 10.4.32: claim worker qua `SELECT FOR UPDATE` | Không phát SQL `SKIP LOCKED`; hai worker không claim trùng, có backoff khi lock wait/deadlock |
| DB-02 | User FK type | `recipient_user_id` và `user_id` INT UNSIGNED khớp `dinkitech_users.id`; migration/FK hợp lệ |
| DB-03 | Audit/updated_by actor types | `actor_user_id`/`updated_by` INT UNSIGNED NULL; audit giữ được khi xóa mềm User |
| DB-04 | `import_jobs.created_by BIGINT UNSIGNED` → Shield `INT UNSIGNED` | Null/inactive/deleted được xử lý đúng; số ngoài range, không tồn tại bị cảnh báo, không truncate |
| DB-05 | `JSON` alias MariaDB 10.4 + schema validation | JSON sai bị chặn; không dùng `JSON_TABLE` hoặc MySQL binary JSON |
| DB-06 | Interrupted DDL migration trên 10.4 | Có backup, kiểm tra partial schema và restore; không giả định transaction ROLLBACK hoàn nguyên DDL |
| DB-07 | `active=0` hoặc `deleted_at IS NOT NULL` tại thời điểm dispatch | Không tạo Inbox mới; trạng thái SUPPRESSED hợp lệ, Inbox cũ giữ theo retention |
| DB-08 | Clock UTC và precision của Outbox/Inbox | Lease, cutoff và ordering nhất quán, qua test trên DEV MariaDB 10.4 |

**Mục tiêu hiệu năng đề xuất (CHƯA đo):** Inbox p95 < 300 ms, summary p95 < 150 ms trên QA dataset 50k/User; mở popover tối đa 2 HTTP calls (summary + list); tab Settings trực tiếp tối đa 1 GET theo tab; không phát sinh request chi tiết N+1. Đặt SLA cuối cùng sau khi benchmark trên DEV clone cấu hình XAMPP thật.

**Gate CLOSED cho M11.1A/B:** migration rehearsal và restore PASS; pure/DB/HTTP/worker PBAC, ownership, concurrency PASS; OpenAPI/SDK/route PASS; frontend type-check/Vitest/Playwright Chromium+Firefox+WebKit/build PASS; kiểm tra Network + UAT DEV và không còn P0/P1. M11.0 chỉ là cột mốc design, không được đánh dấu code PASS.

## 13. Rủi ro và quyết định còn cần xác minh ở DEV

| Vấn đề | Quy tắc đang chốt | Bằng chứng/gate cần thêm |
|---|---|---|
| MariaDB version/claim protocol | **10.4.32 đã được cung cấp; không có `SKIP LOCKED`**. Claim dùng transaction `SELECT FOR UPDATE` + UPDATE ID đã khóa, single-worker mặc định | DEV xác minh `@@tx_isolation`, lock wait, 2-worker integration và indexes |
| Shield User ID, FK/engine/collation | **`dinkitech_users.id INT UNSIGNED`** được thấy trên ảnh; mọi recipient/preference FK cùng kiểu | `SHOW CREATE TABLE`, engine/FK/prefix và migration rehearsal |
| Source Import User FK mismatch | Migration source `import_jobs.created_by BIGINT UNSIGNED NULL`; User PK INT UNSIGNED | DEV DDL Import, invalid/overflow recipient tests |
| MariaDB 10.4 DDL & lifecycle | Không kỳ vọng atomic DDL; MariaDB 10.4 hết hỗ trợ từ 18/06/2024 | Backup/restore partial DDL; kế hoạch nâng cấp DB độc lập trước production |
| `configuration_audit_events` hiện hữu | Không ghi audit trùng; chọn dedicated table trong v1.1 | DEV audit DDL; muốn reuse phải có ADR đổi schema |
| `notification_setting` Resource unknown hiện tại | Bắt buộc thêm ALL_STATES và SYSTEM-domain pin | Source/pure test và integration PBAC |
| Import finalize lỗi sau row commit | Không fallback FAILED do lỗi Outbox; operator reconcile | Controlled failure injection + journal verification |
| Workflow recipient fanout | Không tự suy từ PBAC; không ACK một phần | ADR M11.2 về assignment snapshot và batch |
| `SYSTEM`/`ORGANIZATION` | MVP chỉ SYSTEM, giữ Display org như cũ | Tab isolation + backend reject org scope |
| Notification mandatory chưa producer | UNAVAILABLE cho tới khi producer triển khai | Registry/feature-gate QA |
| Mark-all và transaction in-flight | Contract cutoff theo created_at, không commit-order linearizable | DB concurrency test, ADR nếu cần strong guarantee |
| M10.4A production build | Log build chưa xuất hiện trong thông tin đang đối chiếu | Chạy `pnpm build` khi chốt baseline cho M11 |

**Các fact đã có bằng chứng:** MariaDB 10.4.32 và danh sách cột/kiểu Shield Users trên ảnh phpMyAdmin. **Các fact còn phụ thuộc DEV DB:** engine/FK/DDL, isolation, tình trạng bảng Import/Audit và kế hoạch rollback. Kết quả source review không thay thế migration thử, HTTP/PBAC thực tế hoặc concurrency trên clone.

## 14. Nguồn và tài liệu tham khảo

**Nguồn đối chiếu nội bộ (đường dẫn tương đối của ERP):**

- `frontend/src/components/app-shell/useShellHeader.ts`, `frontend/src/components/AppShell.vue`
- `frontend/src/features/settings/pages/DisplaySettingsView.vue`
- `frontend/src/features/account/pages/DisplayPreferencesView.vue`, `frontend/src/stores/displaySettings.ts`
- `backend/app/Modules/Settings/Display/DisplaySettingsService.php`
- `backend/app/Modules/Import/Core/ImportJobService.php`
- `backend/app/Modules/Workflow/Execution/BusinessWorkflowService.php`
- `backend/app/Modules/Access/UnifiedAccessService.php`, `backend/app/Modules/Access/ResourceStateCatalog.php`, `backend/app/Modules/Access/ResourceStateValidator.php`
- `backend/app/Shared/Http/ApiResponder.php`, `ApiEnvelope.php`; `contracts/openapi/openapi.yaml`
- `frontend/package.json`, `frontend/src/stores/auth.ts`, `frontend/src/router/routes.ts`

**Nguồn DB thực tế mới:** ảnh phpMyAdmin cấu trúc `erp.dinkitech_users` và phiên bản MariaDB 10.4.32 do người dùng cung cấp ngày 25/10/2026.

**Tham khảo kỹ thuật bên ngoài (dùng để kiểm tra nguyên tắc, không thay thế kết quả DEV DB audit):**

- CodeIgniter 4 Transactions: https://codeigniter4.github.io/userguide/database/transactions.html
- CodeIgniter 4 Spark Commands: https://codeigniter4.github.io/CodeIgniter4/cli/spark_commands.html
- MariaDB `FOR UPDATE`: https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update
- MariaDB 10.6 change notes (`SKIP LOCKED` và Atomic DDL mới từ 10.6): https://mariadb.com/docs/release-notes/community-server/10.6/what-is-mariadb-106
- MariaDB 10.4 JSON alias: https://mariadb.com/docs/server/reference/data-types/string-data-types/json
- MariaDB `UPDATE`/`LIMIT` notes: https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/changing-deleting-data/update
- MariaDB versions and 10.4 EOL: https://mariadb.org/about/
- OWASP Authorization Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

---

**Kết luận M11.0 v1.2:** tài liệu đã được cập nhật theo MariaDB **10.4.32** và bảng **`dinkitech_users.id INT UNSIGNED`**; loại bỏ giả định `SKIP LOCKED`/atomic DDL trên phiên bản này, chốt kiểu tham chiếu User và xử lý `created_by` khác cỡ. Đây là nền để review/gate M11.1A; vẫn phải xác minh `SHOW CREATE TABLE`/engine/FK/isolation và thực hiện DEV clone rehearsal trước migration. Chưa có runtime implementation hay performance benchmark.
