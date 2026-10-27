# DinkERP — Thiết kế triển khai cải tiến Notifications theo nghiệp vụ

**Tài liệu:** M11 Functional Upgrade — Functional Gap Remediation & Implementation Design  
**Phiên bản tài liệu:** 1.0 · **Ngày:** 27/10/2026  
**Baseline bắt buộc:** `erp_v1.8.0.zip` (thư mục nguồn `erp30-main/`)  
**SHA-256 baseline:** `fde25bf21254c13b744645cddcbddf63d847c878712076e137753d60710fe907`  
**Nguồn thực thi:** `backend/` (CodeIgniter 4) và `frontend/` (Vue); `backend_baseline/` chỉ là tham chiếu lịch sử.  
**Trạng thái:** ĐỀ XUẤT THIẾT KẾ — chưa lập trình, chưa migration, chưa thay đổi DB hoặc cấu hình chạy thật.  
**Vị trí khuyến nghị khi nhập repository:** `docs/Notifications/DinkERP_M11_Functional_Upgrade_Implementation_Design_v1.8.0.md`

> **Quyết định kiến trúc:** Giữ nguyên M11 Delivery Engine đã nghiệm thu. Ưu tiên bổ sung trải nghiệm thông báo, điều hướng đúng đối tượng, “Công việc của tôi” và khả năng mở rộng sự kiện nghiệp vụ. Tách Monitoring của IT khỏi Inbox của nhân viên. Không lấy việc có nhiều worker/bảng dữ liệu làm thước đo hoàn thiện chức năng.

---

## 1. Mục đích và phạm vi

### 1.1 Mục tiêu sản phẩm

Đưa Notifications từ trạng thái **“gửi và lưu thông báo đúng”** lên **“giúp người dùng biết điều gì xảy ra, điều gì cần làm, mở được đối tượng và hoàn tất việc theo quyền hiện tại”**.

Mục tiêu vận hành tiếp tục được bảo toàn: không gửi nhầm người, không tự bật Email/Push, không phá cơ chế idempotency/retry/lease, không thay đổi ngầm các công tắc chạy worker và không lộ dữ liệu khác phạm vi quyền.

### 1.2 Trong phạm vi

1. **NFX-A · Notification UX Foundation (P0):** Chuông và Inbox dùng được ngay, liên kết an toàn, lọc/tìm theo các chiều có kiểm soát, trạng thái đã đọc, xử lý target không còn truy cập được.
2. **NFX-B · My Work / Actionable Workflow (P0):** Danh sách đối tượng đã được giao và hiện có thao tác Workflow hợp lệ; tách hoàn toàn *read* khỏi *actionable/completed*.
3. **NFX-C · Business Event Registry (P1):** Chuẩn hóa cách bổ sung event, recipient, target, nội dung và kênh phân phối mà không nhân thêm nhánh trong dispatcher lõi.
4. **NFX-D · Preferences & Policy UX (P1):** Cấu hình dễ hiểu cho người dùng và admin nghiệp vụ, giữ nguyên consent và CAS/audit.
5. **NFX-E · Operations Monitoring (P2, triển khai có thể song song ở mức read-only):** Xem queue/task cơ bản, sau đó thực hiện telemetry/Cron heartbeat theo tài liệu M11.7 hiện có.

### 1.3 Ngoài phạm vi phiên bản đầu

- Không viết lại Outbox, Email/Push worker, CodeIgniter Tasks hay mô hình quyền hiện hữu.
- Không làm workflow designer mới, bảng Task/Approval tổng quát, deadline/SLA/escalation khi nguồn nghiệp vụ chưa có.
- Không xây rule engine thực thi SQL/PHP/JavaScript do quản trị viên nhập, không cho giao diện chạy Spark command, retry/requeue/cancel tùy ý.
- Không thay đổi policy Email/Push thành opt-in mặc định; không suy diễn SMTP `SENT` nghĩa là Inbox delivery được xác nhận tự động.
- Không thêm hoặc gộp bảng chỉ để giảm/tăng tổng số bảng.

### 1.4 Phân biệt bằng chứng

| Loại | Thông tin |
|---|---|
| **Đã kiểm chứng từ ZIP** | Schema, route, DTO, catalog event, producer, dispatcher, Email/Push Tasks, frontend Inbox/chuông, PBAC Workflow, feature flag. |
| **Đã được operator xác nhận trước đó** | Bốn email `import.completed` đến đúng Inbox; các lệnh Tasks/Email chạy thủ công và audit PASS. |
| **Chưa kiểm chứng ở lần lập tài liệu này** | Database runtime hiện tại, lịch Cron tự động trên hosting, hiệu năng ở dữ liệu lớn, cấu hình production đã deploy. |
| **Đề xuất mới** | Toàn bộ NFX-A đến NFX-E, API/màn hình bổ sung và chỉ số nghiệm thu tương lai. |

---

## 2. Kiểm kê thực tế baseline v1.8.0

### 2.1 Chín bảng dữ liệu M11 hiện hữu

Định nghĩa trong các migration `2026-10-26-000019` đến `000022`; tên dưới đây là **tên logic** (DB thực tế thêm tiền tố `dinktech_` theo cấu hình).

| STT | Bảng logic | Trách nhiệm thực tế |
|---:|---|---|
| 1 | `notification_settings` | Policy theo key, `default_value`, `allow_user_override`, `revision`, `override_epoch`. |
| 2 | `notification_user_preferences` | Override/consent theo người dùng và epoch. |
| 3 | `notification_outbox` | Event, retry, lease, ACK và chống phát trùng. |
| 4 | `user_notifications` | Inbox In-App; có `priority`, `target_type`, `target_id`, `read_at`, `expires_at`. |
| 5 | `workflow_notification_assignment_sets` | Revision của danh sách người được chỉ định theo đối tượng. |
| 6 | `workflow_notification_assignees` | Người được chỉ định cho từng đối tượng Workflow; **không phải bảng Task có deadline**. |
| 7 | `notification_email_deliveries` | SMTP receipt, trạng thái và retry (`PENDING/LEASED/RETRY/SENT/CANCELLED/DEAD`). |
| 8 | `notification_push_subscriptions` | Đăng ký thiết bị Push, endpoint được bảo vệ. |
| 9 | `notification_push_deliveries` | Push receipt và retry. |

**Quyết định:** NFX-A, NFX-B bản đầu, NFX-C và NFX-D ưu tiên **0 bảng mới**. Tối ưu index có thể cần migration bổ sung sau khi EXPLAIN/benchmark, nhưng vẫn giữ 9 bảng. Chỉ NFX-E telemetry đầy đủ mới *dự kiến* thêm `notification_task_runs` và `notification_cron_ticks` theo thiết kế M11.7 (khi được phê duyệt, tổng 11 bảng M11 + telemetry); không mặc nhiên triển khai hai bảng này.

### 2.2 Những chức năng đã có

- **In-App:** `GET /api/v1/notifications/summary`, `GET /api/v1/notifications`, `POST .../{id}/actions/mark-read`, `POST .../actions/mark-all-read`; mọi request gắn Shield user hiện tại.
- **Inbox pagination:** `filter=all|unread`, `page_size` tối đa 50, keyset cursor ký HMAC gắn user/filter/page size, UTC timestamp và id.
- **Inbox UI:** `NotificationsInboxView.vue` có danh sách, tải thêm, liên kết target, đã đọc/tất cả đã đọc; chưa có filter theo loại hay thời gian.
- **Chuông:** `AppShell.vue` hiển thị 5 mục và unread badge; `useShellHeader.ts` refresh count mỗi 60 giây khi app còn hiển thị; mục trong popup chưa có thao tác mở target riêng.
- **Target:** `notificationInbox.ts` có allowlist route; Import và Product có detail route, nhiều resource Workflow khác chỉ điều hướng đến trang danh sách.
- **Workflow:** `BusinessWorkflowService::options(type,id)` kiểm tra quyền xem + quyền setstate + transition/action hiện hành; dispatcher kiểm tra điều kiện khi tạo Inbox. Chưa dùng dịch vụ này để phân loại thông báo cũ là còn hành động hay đã hết hiệu lực **ở thời điểm người dùng xem**.
- **Email/Push:** chỉ `import.completed` có channel Email/Push; cả hai yêu cầu chính sách/consent rõ ràng. In-App hiện hỗ trợ bốn event ở mục 2.3.
- **Scheduler:** ba task `m11-inapp-dispatch`, `m11-push-dispatch`, `m11-email-dispatch`; Email yêu cầu đồng thời Email Tasks ON, Email Send ON và SMTP ready; batch tương ứng 25/5/1, `singleInstance(180)`; `Config/Tasks.php` hiện `logPerformance=false`.
- **Vận hành:** 12 runtime flags qua `/api/v1/configuration-settings/notifications`, quản lý qua frontend có CAS/audit; CLI audit và Email backlog có sẵn.
- **Rollout:** `frontend/src/features/notifications/featureFlags.ts` chỉ bật Inbox khi `VITE_M11_NOTIFICATIONS_ENABLED === 'true'`.

### 2.3 Catalog event đúng với code

| Event nghiệp vụ | In-App | Email | Web Push |
|---|---|---|---|
| `import.completed` | Có | Có, opt-in | Có, opt-in |
| `import.completed_with_errors` | Có | Chưa có | Chưa có |
| `import.failed` | Có | Chưa có | Chưa có |
| `workflow.action_required` | Có | Chưa có | Chưa có |
| `security.permission_changed` | **UNAVAILABLE** (có định nghĩa policy nhưng producer chưa sẵn sàng) | Chưa có | Chưa có |

**Lưu ý hiện trạng:** `NotificationInboxItem` trong OpenAPI/TypeScript chỉ cho phép `priority=NORMAL`. Tuy bảng `user_notifications` đã có cột `priority`, hệ thống chưa thực sự có phân mức HIGH/CRITICAL nghiệp vụ. Giao diện mới không được gắn nhãn khẩn cấp dựa vào suy đoán; chỉ mở rộng priority sau khi chốt semantics, enum và test.

### 2.4 Khoảng trống và ưu tiên

| ID | Khoảng trống | Hệ quả đối với người dùng | Ưu tiên |
|---|---|---|---|
| GAP-01 | Mục trong popup chuông không nhấp mở trực tiếp | Phải mở Inbox rồi tìm lại | P0 |
| GAP-02 | Nhiều Workflow target chỉ về màn hình danh sách | Mất ngữ cảnh đối tượng cần xử lý | P0 |
| GAP-03 | `read_at` bị dùng như chỉ số tương tác duy nhất | Không có danh sách việc đang chờ | P0 |
| GAP-04 | Inbox cũ không đánh giá trạng thái/được phép thực hiện việc hiện tại | Có thể hiểu nhầm thông báo cũ còn hiệu lực | P0 |
| GAP-05 | Chỉ lọc `all/unread`; hiển thị nội dung còn chung chung | Khó tìm và ưu tiên | P0/P1 |
| GAP-06 | Import và Workflow chiếm phần lớn các event hỗ trợ | Giá trị chưa bao phủ các phân hệ ERP | P1 |
| GAP-07 | Thêm event phải sửa nhiều class và DTO | Dễ bỏ sót contract, test; khó bảo trì | P1 |
| GAP-08 | Policy/consent/scheduler được trình bày gần nhau | Quản trị viên nghiệp vụ khó phân biệt | P1 |
| GAP-09 | Chưa có Queue/Task dashboard cho IT | Phải dùng CLI; chưa biết Cron tự chạy không | P2 |

---

## 3. Nguyên tắc kiến trúc không được vi phạm

1. **Một nguồn sự thật:** Trạng thái nghiệp vụ thuộc record gốc + Workflow engine; `read_at` chỉ là trạng thái đọc, không có ý nghĩa đã duyệt/đã hoàn thành.
2. **Phân quyền ở backend:** Assignment chỉ xác định đối tượng liên quan, **không thay thế PBAC**. Mọi deep link và action phải kiểm tra quyền ở thời điểm truy cập.
3. **Không fan-out tùy tiện:** Recipient phải được xác định từ DB/quan hệ nghiệp vụ; không nhận danh sách arbitrary user IDs từ client để phát thông báo.
4. **Giữ outbox transactional:** Event nghiệp vụ được ghi trong transaction sở hữu; không gửi SMTP/Push ngay trong transaction nghiệp vụ.
5. **Giữ idempotency & reliability:** Không làm thay đổi unique event/recipient, lease, backoff, cancellation và suppression semantics hiện hữu.
6. **Không biến consent thành cấu hình mặc định:** Email/Push phải opt-in theo từng key/epoch và tôn trọng opt-out ở thời điểm worker xử lý.
7. **Tương thích API cũ:** Các endpoint/listing v1 có thể tiếp tục nhận `all/unread` và cursor hiện hữu; chỉ thêm field/query có versioning/gating rõ.
8. **Không mất riêng tư:** Không expose nội dung nhạy cảm, recipient khác, source ngoài phạm vi, SMTP credentials, endpoint Push hay raw payload ra frontend.
9. **Phân tách UI:** “Thông báo”, “Công việc của tôi”, “Vận hành thông báo” là ba trải nghiệm với quyền và mục tiêu khác nhau.
10. **Thay đổi từng bước:** Migration chỉ additive nếu thật sự cần, có script preflight, QA trên DEV clone, rollback bằng feature flag trước khi đụng dữ liệu.

### Kiến trúc đích

```text
  ERP Modules (Import, Workflow, Product, future producers)
                   |
                   v
       Typed Business Event Registry  [NFX-C]
         | validate | recipient | template | target
                   |
       Existing NotificationOutboxProducer
                   |
           notification_outbox
                   |
          Existing M11 Dispatchers
             /      |       \
            v       v        v
        In-App     Email     Push
            |       |        |
    user_notifications |   push_deliveries
            |           email_deliveries
            v
      Notification Inbox  [NFX-A]

  workflow_notification_assignees + current business records
                       + BusinessWorkflowService::options()
                            |
                            v
                    My Work read model [NFX-B]

  Read-only queue/Task health API --> IT Operations Center [NFX-E]
```

Tách registry/event handlers khỏi dispatch orchestration theo hướng **typed code-defined registry**, chưa tạo dynamic rules editor. Giữ Email, Push và Tasks CLI hiện hành; không tự đăng ký thêm scheduler vì một loại event mới.

---

## 4. NFX-A — Notification UX Foundation (P0, triển khai đầu tiên)

### 4.1 Người dùng cần làm được

- Nhấn chuông → thấy tối đa năm thông báo mới nhất, có thời gian, loại, trạng thái chưa đọc và hành động **Mở đối tượng** cho từng item.
- Nhấn đối tượng trên popup/Inbox → điều hướng đến route được allowlist; nếu chỉ có màn hình danh sách, ghi rõ **Mở danh sách** thay vì giả vờ mở đúng record.
- Có thể đánh dấu riêng đã đọc hoặc đánh dấu tất cả; một tác vụ mở đối tượng **không được tự động coi là hoàn thành**.
- Lọc danh sách theo `all/unread`, nhóm event và khoảng thời gian; không phá cursor cũ.
- Nếu đối tượng đã xóa/không còn quyền/không có route chi tiết, thông báo vẫn đọc được khi thuộc Inbox của chính user; giao diện thông báo **không còn truy cập được**, không lộ lý do nhạy cảm.
- Dùng tiếng Việt/Anh hiện hữu, hỗ trợ keyboard/focus và màn hình nhỏ.

### 4.2 Thiết kế chi tiết UI

| Thành phần | Yêu cầu |
|---|---|
| Bell popover | Item có target-action thật; `aria-label`, Enter/Space, trạng thái loading; click không gây double navigation; “Xem tất cả” luôn hoạt động. |
| Inbox item | Icon nhóm event, tiêu đề, mô tả i18n, thời gian UTC đổi sang locale, trạng thái đã đọc, nút mở đối tượng, mark read riêng. |
| Filters | Bản đầu `all/unread` + `event_group=all/import/workflow` và khoảng ngày; reset pagination khi đổi filter. |
| Read UX | Optimistic UI chỉ sau response hợp lệ hoặc có rollback nếu lỗi; đồng bộ badge qua summary; không tự sửa item thuộc phiên user khác. |
| Empty/error | Tách “chưa có thông báo”, “không có kết quả lọc”, “không có quyền truy cập target”, “lỗi tải”. |
| Deep links | Resource route allowlist, không bao giờ sử dụng URL tùy ý từ server/event payload. Chỉ cam kết “chi tiết” khi module thực sự có route/selector sâu hợp lệ. |
| Relevance | Gói A chưa tự đánh dấu “đã giải quyết” cho Workflow; gắn trạng thái chỉ sau khi NFX-B có check thời điểm xem. |

**Thiết kế thao tác:** `mark-read` và mở target là hai hành động độc lập. Không đổi trạng thái đọc chỉ vì router mở thất bại; không thực thi Workflow khi bấm mở thông báo. Nếu về sau có auto-mark-on-open, phải là quyết định UX riêng với kiểm thử race và rollback.

### 4.3 API và tương thích

**Giữ nguyên**:

```http
GET  /api/v1/notifications/summary
GET  /api/v1/notifications?filter=all|unread&page_size=20&cursor=...
POST /api/v1/notifications/{id}/actions/mark-read
POST /api/v1/notifications/actions/mark-all-read
```

**Đề xuất mở rộng có kiểm soát** cho listing (chỉ khi kiểm tra query/index):

```http
GET /api/v1/notifications?filter=unread&event_group=workflow&from=2026-10-01&to=2026-10-27&page_size=20
```

- Query bổ sung: `event_group` là enum `all|import|workflow`; `from/to` là ngày UTC `YYYY-MM-DD`, giới hạn khoảng theo policy và có ngày bao gồm/không bao gồm được chỉ rõ (`from` inclusive, `to` inclusive theo ngày UTC). **Không truyền điều kiện SQL tự do**.
- `event_group` được server map sang event types allowlist, không dùng `LIKE` từ client. Các request cũ không có query mới **giữ nguyên response, thứ tự và cursor v1**.
- Với tổ hợp filter mới, thiết kế **cursor v2** có HMAC ràng buộc `user_id`, filter canonical, page_size, vị trí `(created_at,id)`, expiry và version. Cursor v1 chỉ chấp nhận các request kiểu cũ; cursor sai fingerprint → 422; không tái sử dụng cursor cũ sau khi thay filter.
- Điều chỉnh index chỉ khi profiling cho thấy yêu cầu; cân nhắc `(recipient_user_id,type,created_at,id)` hoặc biến thể theo workload, không thêm index trước khi có `EXPLAIN`.
- `page_size` tối đa 50, không bỏ giới hạn; JSON ID/BIGINT luôn là chuỗi.
- Chỉ thêm enum/field vào OpenAPI sau khi code reader/validation và test tương ứng hoàn chỉnh.

### 4.4 Deep-link resolver (không mở quyền)

1. Frontend duy trì allowlist target type → route; nhập từ notification **không được dùng làm URL gốc**.
2. `import_job` mở `/products/import-jobs/{id}`; `product` mở `/products/{id}` như baseline nếu user có quyền ở trang đích.
3. Các resource còn chỉ có route danh sách được gắn label “Mở danh sách”; **không tự thêm query `?id=` nếu trang đích không hỗ trợ**. Route chi tiết/selection phải được làm cùng module chủ quản trước khi tuyên bố deep link record-level hoàn thành.
4. Target không còn tồn tại hoặc không được xem: trang đích từ chối theo PBAC, UI hiển thị thông báo trung tính; không cung cấp trạng thái hoặc nội dung bản ghi trái phép.
5. NFX-A không query rộng các bảng nguồn để làm title; chỉ hiển thị thông tin an toàn đã có (`title_key/body_key/params`) và tên nhóm. Enrichment tên chứng từ theo quyền xét ở NFX-B/C.

### 4.5 Các file dự kiến đụng tới

- `frontend/src/components/AppShell.vue`
- `frontend/src/components/app-shell/useShellHeader.ts`
- `frontend/src/features/notifications/pages/NotificationsInboxView.vue`
- `frontend/src/services/notificationInbox.ts`
- `frontend/src/stores/notifications.ts`
- `frontend/src/i18n/locales/vi.ts`, `en.ts`
- Nếu mở rộng lọc: `backend/app/Modules/Notifications/NotificationInboxService.php`, `NotificationCursor.php`, `contracts/openapi/openapi.yaml`, SDK được sinh từ hợp đồng.
- Test mới: `frontend/src/features/notifications/**/__tests__/` và backend `tests/m11/` theo cấu trúc dự án.

### 4.6 Điều kiện đóng NFX-A

- A-01: Popup item có thể mở đúng detail của Import/Product; không còn bắt buộc đi qua Inbox.
- A-02: Resource không có detail route hiển thị hành động danh sách chính xác; không mất ID bối cảnh một cách âm thầm.
- A-03: Session switch/logout không hiển thị hay giữ badge/item của người dùng trước.
- A-04: Lọc liên tiếp/append không lặp, không rò item, không trộn filter/cursor; cursor giả mạo, khác user, hết hạn → 422.
- A-05: Mark read/idempotency/unread count đúng; lỗi API có UI fallback; không xóa công việc khi đánh dấu đã đọc.
- A-06: Existing 4 event + Email/Push acceptance không regression; không sửa schema hoặc gửi thử thật khi test frontend.

---

## 5. NFX-B — My Work / Actionable Workflow (P0, sau NFX-A)

### 5.1 Phạm vi MVP phải hiểu đúng

**My Work v1 không phải “workflow task engine”** và không chứa SLA/approval chain mới. Đó là một **read model các đối tượng mà user hiện được chỉ định + còn có thể thực hiện một transition hợp lệ**.

Nguồn xác định:

1. `workflow_notification_assignees`: có assignment trực tiếp tới `user_id` đăng nhập.
2. Bản ghi business hiện tại vẫn tồn tại, đúng `resource_type/contact_type`.
3. `BusinessWorkflowService::options(type,id)` xác nhận `canView`, `setstate` và ít nhất một action hợp lệ **tại thời điểm GET**.
4. Không dựa vào `user_notifications.read_at`, cũng không đồng nhất việc nhận một notification với quyền hiện có.

### 5.2 Phân loại hiển thị

| Tình huống | Notification Inbox | My Work |
|---|---|---|
| Chưa đọc, action hiện hợp lệ | Vẫn “chưa đọc” | Có trong “Cần xử lý” |
| Đã đọc, action hiện hợp lệ | “Đã đọc” | **Vẫn có** trong “Cần xử lý” |
| Đã chuyển trạng thái và hết action | Inbox lịch sử không thay đổi | Không còn trong “Cần xử lý” |
| User mất quyền view hoặc assignment | Không tiết lộ thêm dữ liệu target khi click | Không có trong danh sách |
| User không có action `setstate` nhưng còn quyền view | Đọc được notification trước đó nếu sở hữu | Không phải công việc có thể xử lý |

**Không tự ghi “Đã hoàn thành” chỉ từ việc không còn action.** Sự vắng mặt có thể do thu hồi quyền, bị xóa, đổi người phụ trách hoặc lỗi cấu hình. Nếu cần lịch sử đã giải quyết, phải dùng sự kiện/trạng thái nghiệp vụ đáng tin cậy và hợp đồng riêng ở giai đoạn sau.

### 5.3 API đề xuất (read-only giai đoạn đầu)

```http
GET /api/v1/my-work?resource_type=all&page_size=20&cursor=...
```

**Response mẫu dự kiến, chưa phải contract đã có:**

```json
{
  "data": {
    "items": [
      {
        "resource_type": "product",
        "resource_id": "25",
        "current_state": "REVIEW",
        "available_actions": [
          { "action_code": "approve", "destination_state": "ACTIVE" }
        ],
        "assigned_at": "2026-10-26T08:37:16Z",
        "target": { "type": "workflow_target", "resource_type": "product", "id": "25" }
      }
    ],
    "next_cursor": null
  },
  "meta": { "request_id": "..." }
}
```

`available_actions` được map từ API/service hiện hữu (`options[].action_code/status`); tên hiển thị do frontend i18n. Không lấy tên/bản chất action từ payload client. ID là chuỗi. `resource_type` phải allowlist theo `BusinessWorkflowRules` và các adapter được phê duyệt.

### 5.4 Thuật toán truy vấn an toàn

```text
actor = authenticated_active_user
candidates = SELECT assignment WHERE recipient_user_id = actor.id
             ORDER BY created_at DESC, resource_type, resource_id
             [bounded keyset slices]
for each candidate in bounded batches:
    validate trusted resource adapter + source record
    options = BusinessWorkflowService(actor).options(resource_type, resource_id)
    if AUTH_POLICY_DENIED or no available options: omit (do not return reason)
    else: project sanitized id/state/available_actions/target
continue scanning until page_size eligible rows OR no more candidates
return signed cursor tied to actor + filters + candidate position
```

**Không áp dụng LIMIT 20 ứng viên rồi trả một trang 0 item nếu 20 ứng viên đều bị PBAC loại.** Phải quét có giới hạn, tiếp tục từ candidate cursor và đo số rows scanned. Nếu tới `scan_budget` mà chưa đủ phần tử, trả trang ngắn với `next_cursor` hợp lệ và không tuyên bố hết danh sách. Đề xuất bắt đầu bằng benchmark DEV clone, kiểm tra N+1 queries, chỉ cấp index bổ sung sau `EXPLAIN`.

Việc đọc trạng thái và quyết định quyền cần tránh trả thông tin đã cũ lâu; GET là snapshot best effort, còn **API execute hiện hành vẫn luôn khóa record và kiểm tra quyền lại khi mutate**. My Work không cấp quyền mới.

### 5.5 Tương tác người dùng

- Route gợi ý `/my-work`, menu cá nhân “Công việc của tôi”, có filter resource type và badge số việc **chỉ sau khi endpoint tính chính xác**.
- Mục hiển thị loại đối tượng, mã/id an toàn, trạng thái, action hợp lệ và nút **Xem và xử lý** dẫn tới module gốc.
- **Chưa có nút Approve/Reject inline** ở MVP. Nếu cần sau này, phải dùng chính mutation service và CAS/state guards hiện hữu, có xác nhận, kiểm tra lại PBAC và audit, không tạo endpoint sửa trạng thái riêng trong Notifications.
- Không hiển thị hạn hoàn thành hoặc “quá hạn” nếu resource gốc chưa có trường deadline được phê duyệt.

### 5.6 Điều kiện đóng NFX-B

- B-01: Assignment riêng nhưng không đủ PBAC → không xuất hiện My Work.
- B-02: Đã đọc notification nhưng action còn hợp lệ → vẫn hiển thị My Work.
- B-03: Chuyển trạng thái/xóa record/đổi người nhận/thu hồi quyền → lượt GET tiếp theo phản ánh đúng.
- B-04: Nhiều assignees cùng record → từng người chỉ nhìn thấy dòng họ được chỉ định và đủ quyền.
- B-05: Pagination không rò số lượng/quyền của bản ghi bị lọc; không tạo query không giới hạn trên DB.
- B-06: Direct navigation và mutation vẫn kiểm tra lại quyền; không chấp nhận target_type từ query làm tên bảng SQL.

---

## 6. NFX-C — Business Event Registry và mở rộng độ phủ nghiệp vụ (P1)

### 6.1 Vấn đề code cần giải quyết

`NotificationCatalog::definitions()` là map các key cố định; `NotificationDispatcher` chứa hai nhánh `deliverImport()/deliverWorkflow()`; `NotificationInboxService::project()` parse riêng từng dạng payload; `frontend/src/services/notificationInbox.ts` là union bốn type; Email/Push rules chỉ hỗ trợ Import complete. Mỗi event mới dễ buộc sửa nhiều nơi và gây thiếu nhất quán.

### 6.2 Mục tiêu refactor

Tạo registry **tĩnh, type-safe, triển khai cùng mã nguồn**, định nghĩa cho từng event:

| Thành phần hợp đồng | Trách nhiệm |
|---|---|
| `event_type`, `schema_version` | Định danh immutable và phiên bản payload |
| `source_type`, source resolver | Nạp dữ liệu từ record thực, theo allowlist và đúng transaction |
| `recipient_resolver` | Xác định người nhận từ quan hệ nghiệp vụ/assignment có kiểm soát |
| `policy_key_by_channel` | Policy SYSTEM/USER và consent hiện hành |
| `template_key` + params schema | Nội dung i18n, không raw HTML tuỳ ý |
| `target_mapper` | Loại target và ID đã xác thực |
| `priority` | Một enum có semantics được chốt; trước mắt vẫn `NORMAL` |
| `dedupe_key` | Khóa ổn định, chống event/recipient gửi trùng |
| `delivery_channel_capabilities` | In-App, Email, Push chỉ được bật khi code hỗ trợ và policy hợp lệ |

### 6.3 Thiết kế module đề xuất

```text
Notifications/
  Events/
    NotificationEventDefinition.php      # value object / interface
    NotificationEventRegistry.php        # allowlist và định nghĩa
    ImportTerminalEventHandler.php       # adapter hiện hữu
    WorkflowActionRequiredHandler.php    # adapter hiện hữu
    [future event handlers]
  NotificationOutboxProducer.php         # transactional API giữ nguyên
  NotificationDispatcher.php             # orchestration claim/lease/ACK
  NotificationInboxService.php           # đọc, pagination và projection chuẩn
```

Tên file là **đề xuất**, không phải code đã tồn tại. Giai đoạn refactor phải có compatibility adapter cho event và `params_json` schema v1 hiện hữu; không backfill lịch sử chỉ để đổi JSON format. Không mở rộng dynamic payload không kiểm soát hoặc nạp class từ string trong DB.

### 6.4 Độ phủ event theo từng lượt triển khai

| Lượt | Event | Kênh đầu tiên | Tiêu chí kích hoạt |
|---|---|---|---|
| C0 | Chuyển 4 event hiện hữu sang registry | Không thay đổi | Output, idempotency, policy và tests không khác baseline. |
| C1 | Kết quả Workflow: đã chuyển trạng thái / kết quả xử lý | In-App | Xác định rõ ai cần nhận và ai được xem; tránh thông báo cho mọi user có quyền VIEW. |
| C2 | Giao/đổi người phụ trách (nếu business chốt) | In-App | Producer transaction + CAS assignment; không gửi vòng lặp khi sửa thông tin không liên quan. |
| C3 | Event mới từ phân hệ ưu tiên (ví dụ Product/Inventory) | In-App | Có owner, recipient resolver và mục đích nghiệp vụ cụ thể. |
| C4 | Mở rộng Email/Push cho một số event đã chứng minh nhu cầu | Opt-in | Consent hiện hành và test opt-out sau enqueue; không tự cho phép kênh hàng loạt. |

**Không tự đổi `security.permission_changed` từ `UNAVAILABLE` sang `AVAILABLE`.** Phải có producer an toàn, policy mandatory đúng, hợp đồng event, recipient resolver và test security riêng rồi mới rollout.

### 6.5 Kiểm thử bắt buộc

- Event cũ replay fixture vẫn tạo đúng key, không tăng số email/push ngoài ý muốn.
- Payload schema sai → không fan-out, có reason code chuẩn; không đưa nội dung lỗi/raw payload vào client.
- Nguồn bị xóa/thay đổi trạng thái hoặc user inactive → suppression/failure theo quy tắc cũ; không biến công tắc tạm OFF thành terminal suppress.
- Bật/tắt kênh độc lập: In-App OFF không làm mất Email đã consent khi business rule cho phép, và ngược lại.
- Không duplicate event/recipient; transaction xử lý nhiều người nhận vẫn ACK nguyên tử như baseline.

---

## 7. NFX-D — Simplified Preferences & Policies (P1)

### 7.1 Ba tầng quyền sử dụng UI

| Khu vực | Ai sử dụng | Nội dung |
|---|---|---|
| Cá nhân → Tùy chọn thông báo | Mọi user đăng nhập | Các nhóm event khả dụng, chọn In-App/Email/Push khi cho phép; Push device của chính mình. |
| Hệ thống → Chính sách thông báo | Admin có `notification_setting.view/edit` | Default policy, allow-user-override, capability, bản sửa/revision; giải thích khi bị khóa. |
| Hệ thống → Cấu hình vận hành nâng cao | IT/operator được cấp quyền | 12 công tắc delivery/producer/scheduler và tình trạng SMTP/Cron, không hiển thị credentials. |

Không nhân đôi các switch vào màn hình nhân viên; không tự ghi đè user preference khi admin thay đổi default. Key `UNAVAILABLE` hiển thị lý do “chưa hỗ trợ”, không cho lưu giả.

### 7.2 Giữ nguyên an toàn policy

- Bảo toàn `revision`, `override_epoch`, `user_revision` dạng chuỗi decimal; ghi config theo CAS và audit transaction.
- Admin locking và `mandatory` không thể bị user override; override hết epoch không tự hồi sinh.
- Email/Push không được bật bởi system default hoặc migration/re-seed; tiếp tục yêu cầu consent hiện hành.
- Cấu hình vận hành không lẫn với consent. `DINKERP_M11_TASKS_EMAIL_ENABLED` không đồng nghĩa user cho phép nhận email.
- Nếu thêm policy keys khi NFX-C, migration/seed additive, idempotent, default an toàn, không sửa chính sách admin đã thiết lập.

### 7.3 Nghiệm thu

- D-01: User hiểu kênh nào được phép bật và vì sao bị khóa.
- D-02: Sau khi opt-out Email/Push, worker không gửi receipt mới hoặc receipt đang pending trái quyền theo cơ chế hiện hữu.
- D-03: Hai phiên cùng sửa preference/policy → 409 đúng, không mất update.
- D-04: Không có hành động nào qua UI làm lộ SMTP/VAPID private key, endpoint cipher, HMAC secret.

---

## 8. NFX-E — Operations Center (P2; kế thừa M11.7)

**Nguồn thiết kế chi tiết đã có trong baseline:** `docs/Notifications/M11_7_Notification_Operations_Center_Implementation_Design.md`.

**Không xóa hoặc thay thế tài liệu M11.7.** Điều chỉnh thực thi thành hai cấp:

| Cấp | Phạm vi | Dữ liệu |
|---|---|---|
| E1 · Read-only operations | Dashboard Email Queue/Outbox/Push aggregate, danh sách email và trạng thái cấu hình task | Dùng 9 bảng hiện tại, **0 bảng mới** |
| E2 · Telemetry & Cron health | Task run history, counters, tick/heartbeat phân biệt chạy tay với Cron | Dự kiến thêm 2 bảng telemetry, qua migration mới nếu phê duyệt |

Không được hiển thị `HEALTHY` chỉ vì `tasks:list` có task hoặc `tasks:run` chạy thủ công. Bảo toàn `NO_WORK` khi `claimed=0`; chỉ phát hiện Cron chạy tự động bằng heartbeat đáng tin cậy và lịch sử thực thi. Không có Run Now/Retry/Delete qua HTTP trong phiên bản đầu. Giao diện read-only phải dùng PBAC SYSTEM riêng nếu tạo API operations; không dùng quyền chỉnh sửa `notification_setting` để cấp quyền ngầm.

**Lưu ý:** E1 có thể triển khai song song sau khi thiết kế API bảo mật được duyệt; không nên trì hoãn UX Foundation và My Work chỉ vì chưa hoàn tất telemetry.

---

## 9. Hợp đồng API, PBAC và bảo vệ dữ liệu

### 9.1 Ma trận API

| API | Hành vi | Thay đổi dự kiến | Bảo vệ |
|---|---|---|---|
| `GET /notifications/summary` | Đếm unread của chính user | Giữ nguyên | Shield session, own-user only |
| `GET /notifications` | Own Inbox, cursor | A: optional event/date filters | Own-user, giới hạn 50, HMAC cursor, no-store |
| `POST /notifications/{id}/actions/mark-read` | Mark own read | Giữ nguyên | Own-user + id validation, idempotent |
| `POST /notifications/actions/mark-all-read` | Mark own read | Giữ nguyên | Own-user + UTC cutoff |
| `GET /my-work` | Actionable assignments | **Mới ở B** | Assignment AND persisted current PBAC/action |
| `GET /notification-operations/*` | Monitoring IT | **Mới ở E** | PBAC resource SYSTEM riêng, không secrets |
| `GET /business-workflow/{type}/{id}/options` | Current Workflow options | Tái sử dụng | Backend PBAC, không cấp quyền mới |

Prefix đầy đủ của các API trên là `/api/v1/` như router hiện hữu; endpoint `my-work`/operations chưa được triển khai.

### 9.2 Quy tắc bảo vệ bắt buộc

- Bắt buộc Shield session; endpoint cá nhân không chấp nhận `user_id` do client chọn để đọc hộ user khác.
- `ResourceStateCatalog`/`BusinessWorkflowRules` map resource → table bằng allowlist; không đưa tên bảng, SQL, path/URL từ payload vào truy vấn.
- Authorization trên từng business record trước khi trả field nhạy cảm; danh sách My Work không lộ cả bản ghi lẫn tổng số record bị deny.
- API trả `Cache-Control: private, no-store`; frontend xóa store khi logout/đổi phiên, abort request cũ.
- Chỉ trả params i18n đã validate và các field allowlist; không trả raw outbox JSON, email addresses, SMTP password, Push endpoint, stack trace, lease token.
- Vue render text escaped; không dùng `v-html` để render event/message ngoài kiểm soát.
- Thay đổi HTTP mutation phải dùng quy tắc CSRF/Auth/transaction và audit hiện hữu; không xây đường tắt mutate từ bảng Notifications.

### 9.3 OpenAPI và compatibility

- `contracts/openapi/openapi.yaml` là nguồn hợp đồng chính; cập nhật rồi chạy `pnpm run openapi:generate`, `openapi:lint`, `openapi:generate:check`, `type-check`.
- Giữ field đang có (`id`, `type`, `priority`, `title_key`, `body_key`, `params`, `target`, `read_at`, `created_at`, `next_cursor`). Field mới là additive và nullable/optional khi có lý do.
- Không đổi behavior của cursor v1 cho request cũ; cursor v2 chỉ áp dụng với filter mới, có test tampering/session-switch/filter-switch.
- Sai tham số → lỗi 422 không tiết lộ query; missing/unavailable target có message trung tính. Không dùng 403/404 khác biệt để lộ danh tính record khi caller không có quyền.

---

## 10. Database, index, migration và rollback

### 10.1 Mặc định không thay đổi DB

NFX-A/B/C/D trước hết dùng `user_notifications`, `workflow_notification_assignees`, workflow state và policy hiện hữu. Cột `priority/target/created_at/read_at/expires_at` có sẵn, nhưng **không đồng nghĩa** thông tin tên chứng từ, deadline hoặc resolved state đã có sẵn.

**Chỉ cân nhắc migration index** sau khi đo trên DEV clone:

- Inbox filter: composite index bắt đầu bằng `recipient_user_id`, tiếp theo loại/created_at/id theo pattern thực tế.
- My Work: xem xét index `(recipient_user_id, created_at, resource_type, resource_id)` cho bảng assignees sau EXPLAIN và benchmark.
- Không chỉnh sửa migration `000019–000022` đã áp dụng; mọi thay đổi làm bằng migration mới có preflight kiểm tra tên/index và schema thực.

### 10.2 Nguyên tắc nâng cấp

1. Backup DB/đối chiếu ledger và cấu trúc trên DEV clone trước DDL; MariaDB 10.4 DDL không có rollback nguyên tử xuyên nhiều CREATE.
2. Release code tương thích field cũ; rollout UI theo flag/config đã duyệt, không tự bật channel khi deploy.
3. Nếu rollout lỗi, tắt feature, quay lại frontend/backend phiên trước và **giữ nguyên dữ liệu notifications/queues/consent/audit**.
4. Không DROP bảng Notifications đang chứa dữ liệu production. Dữ liệu telemetry (nếu đã có) cũng phải có quyết định retention/rollback riêng.
5. Không chạy test tạo fixture trên DB production; các test reliability có ghi dữ liệu phải thực hiện trong DB test guard riêng.

---

## 11. Kế hoạch triển khai theo pull request / giai đoạn

| Gói | Ưu tiên | Công việc triển khai | Thành phẩm | Dependency |
|---|---|---|---|---|
| NFX-A1 | P0 | Clickable bell; target labels/fallback; test UI | Chuông dùng được | Baseline v1.8.0 |
| NFX-A2 | P0 | Inbox filters + cursor v2 + OpenAPI + backend test | Inbox có lọc ổn định | A1/thiết kế filter |
| NFX-B1 | P0 | My Work read model, PBAC, pagination, bounded scan | `GET /my-work` | QA Workflow/PBAC |
| NFX-B2 | P0 | Trang `/my-work`, điều hướng về module nghiệp vụ | Trang “Công việc của tôi” | B1 |
| NFX-C0 | P1 | Refactor event handlers với backward compatibility | Registry + regression | A/B ổn định |
| NFX-C1 | P1 | Bổ sung 1–2 event nghiệp vụ đã chốt | Event coverage có giá trị | C0 + business owner |
| NFX-D | P1 | Simplify user/admin settings, giữ consent/CAS | UI policy dễ hiểu | Catalog mới nếu có |
| NFX-E1 | P2* | Operations dashboard read-only | Email queue/task status | PBAC ops được duyệt |
| NFX-E2 | P2 | Task runs và Cron heartbeat theo M11.7 | Production observability | E1 + migration approval |

`*` E1 có thể triển khai song song sau khi NFX-A đạt thiết kế; không có lý do kỹ thuật buộc phải đợi NFX-C/D. Mỗi PR phải độc lập có rollback plan, không gộp refactor delivery với thay đổi UI quy mô lớn.

### 11.1 Definition of Done cho mọi gói

- Có ADR/thiết kế thay đổi API/schema/PBAC khi liên quan; cập nhật OpenAPI và generated SDK.
- Backend Unit + Integration + Reliability M11 giữ PASS; các lỗi không liên quan phải được nêu rõ và phân loại.
- Frontend `type-check`, Vitest chuyên biệt và production build PASS.
- Có negative test PBAC, hết phiên, stale data, cancel/opt-out, replay/idempotency theo loại thay đổi.
- Không có DDL/DML ngoài dự kiến, queue/backlog không bị xử lý ngoài ý muốn trong QA; lệnh thử SMTP gửi thật chỉ chạy khi có xác nhận riêng.
- Có evidence QA và rollback/feature flag; cập nhật tài liệu cùng source change.

---

## 12. Ma trận nghiệm thu tối thiểu

| TC | Tình huống | Kết quả mong đợi | Gói |
|---|---|---|---|
| TC-01 | Bấm thông báo Import trong popover | Mở đúng job detail, không phải vào Inbox tìm lại | A |
| TC-02 | Bấm Workflow Product | Mở detail record đúng id nếu có quyền | A |
| TC-03 | Bấm Workflow resource chỉ có list route | Thông báo rõ “Mở danh sách”, không giả mạo deep link | A |
| TC-04 | Target bị xóa/thu hồi quyền | Không lộ record, xử lý UI trung tính | A |
| TC-05 | Mark read rồi reload | Unread count giảm đúng, idempotent | A |
| TC-06 | Đổi account khi HTTP response cũ chưa trả | Không thấy notification của account cũ | A |
| TC-07 | Cursor cũ với filter all/unread | Tương thích baseline | A |
| TC-08 | Cursor v2 bị sửa/user khác/filter khác/hết hạn | 422, không rò dữ liệu | A |
| TC-09 | Lọc Import/Workflow/date | Không lặp/skip sai do thay filter; page size giới hạn | A |
| TC-10 | Notification đã đọc nhưng task còn action | My Work vẫn chứa dòng đó | B |
| TC-11 | Assignment tồn tại nhưng không đủ PBAC | My Work không trả dòng/metadata | B |
| TC-12 | Workflow chuyển state hoặc mất quyền sau khi mở trang | Lượt GET sau phản ánh tình trạng mới; mutation tái kiểm tra | B |
| TC-13 | Nhiều assignees, nhiều source records | Chỉ trả assignments hợp lệ của đúng user | B |
| TC-14 | Phần lớn candidates bị filter bởi PBAC | Có bounded scan/cursor, không trả sai “hết dữ liệu” | B |
| TC-15 | Refactor handler bốn event cũ | Event key, inbox receipt, email/push fan-out không đổi | C |
| TC-16 | Payload event lỗi/cấu hình thiếu | Fail closed, không tạo notification giả | C |
| TC-17 | User opt-out Email/Push sau enqueue | Worker tôn trọng consent, không gửi trái quyền | C/D |
| TC-18 | Hai phiên sửa policy/revision | Conflict 409, không overwrite mù | D |
| TC-19 | Ops user chỉ có quyền view | Chỉ đọc, không thấy secret, không thực thi command | E |
| TC-20 | Task đăng ký nhưng Cron không chạy | Không báo HEALTHY giả | E2 |
| TC-21 | Worker chạy `claimed=0` thành công | `NO_WORK`, không coi là FAILED | E2 |
| TC-22 | Telemetry ghi DB lỗi | Không làm email gửi lại/trùng, có log lỗi an toàn | E2 |
| TC-23 | Existing M11 automated regression | Toàn bộ suite M11 + frontend build không regression | Tất cả |

### 12.1 Lệnh kiểm thử tham chiếu từ baseline

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
php spark notifications:ops-audit --strict

cd D:\XAMPP\htdocs\erp\frontend
pnpm run openapi:generate
pnpm run openapi:lint
pnpm run openapi:generate:check
pnpm run type-check
pnpm exec vitest run src/features/settings/pages/__tests__/ConfigurationSettingsView.spec.ts
pnpm run build
```

Các test frontend **mới** cho AppShell/Inbox/My Work phải được viết và bổ sung vào pipeline; kết quả pass của test Settings hiện hữu không chứng minh UX Inbox đã đạt. Không chạy `php spark tasks:run` hoặc Email dispatcher trên môi trường có receipt chưa được duyệt chỉ để nghiệm thu UI.

### 12.2 Chỉ số chất lượng đề xuất

- **Correctness (bắt buộc):** 100% TC bảo mật/đúng người/cursor/idempotency liên quan gói đang nghiệm thu PASS; không có regression ở các channel.
- **UX (đo với test có đối tượng thật):** Không phải qua Inbox nếu người dùng nhấn target hợp lệ từ chuông; các tài nguyên thiếu detail route được biểu đạt đúng.
- **Performance:** Đo `EXPLAIN`/request latency trên DEV clone có tập dữ liệu đại diện trước khi chốt SLO; **chưa gán SLA số liệu thiếu benchmark**.
- **Ops:** Nếu triển khai E2, health chỉ chuyển HEALTHY khi có bằng chứng Cron thật theo quy tắc M11.7.

---

## 13. Rủi ro, giảm thiểu và quyết định bắt buộc

| Rủi ro | Mức | Hướng kiểm soát |
|---|---|---|
| Deep link sai hoặc mở trang ngoài phạm vi quyền | Cao | Allowlist + PBAC ở trang đích + test negative |
| NFX-B làm rò record dù có assignment | Cao | Per-row `options()`; omit denied, không tiết lộ count |
| Truy vấn My Work chậm do lọc PBAC theo từng record | Cao | Bounded slices, scan budget, benchmark, index sau EXPLAIN |
| Thêm event gây gửi trùng Email/Push | Cao | Registry compatibility, unique keys, replay test, opt-in gates |
| Sai ý nghĩa read/handled/resolved | Cao | Tách chỉ số, không tạo `completed_at` suy luận trong Inbox |
| Cursor filter mới làm vỡ client cũ | Trung bình | v1 untouched, v2 canonical fingerprint, OpenAPI/test |
| Tính priority giả dù backend chỉ có NORMAL | Trung bình | Chưa mở HIGH cho đến khi semantics + data contract được duyệt |
| UI Preferences làm user vô tình opt-in | Cao | Default off, confirm, CAS, epoch, test opt-out sau enqueue |
| Ops dashboard báo Cron khỏe giả | Trung bình | Phân biệt registered/manual/heartbeat; M11.7 health policy |
| Migration/index trên DB ERP đang hoạt động | Cao | Backup, DEV clone, additive DDL, ledger reconciliation |

### 13.1 Các quyết định cần phê duyệt trước khi lập trình

1. **Thông qua ưu tiên NFX-A → NFX-B → NFX-C/D**, Operations E1 được song song, E2 chờ khi cần monitor Cron thực tế.
2. **Chốt các resource có detail route** trong đợt A1. Không ép chức năng “mở đúng record” cho trang danh sách chưa hỗ trợ chọn record.
3. **Chốt My Work v1 chỉ là actionable assigned workflow records**, không coi là full task manager; chưa có deadline/approval inbox tổng quát.
4. **Chốt nhóm event/recipient cho NFX-C1** dựa trên owner nghiệp vụ, không mở tất cả event/Email/Push mặc định.
5. **Phê duyệt mọi API/PBAC/schema bổ sung** theo quy trình DinkERP; không tự seed grants cho user hoặc role không được chỉ định.

---

## 14. Bản đồ nguồn code phục vụ triển khai

| Nội dung | Đường dẫn xác minh trong baseline |
|---|---|
| M11 schema | `backend/app/Database/Migrations/2026-10-26-000019_CreateNotificationTables.php` đến `...000022_CreateNotificationPushTables.php` |
| Event catalog | `backend/app/Modules/Notifications/NotificationCatalog.php` |
| Outbox producer | `backend/app/Modules/Notifications/NotificationOutboxProducer.php` |
| Dispatcher và workflow routing | `backend/app/Modules/Notifications/NotificationDispatcher.php` |
| Inbox list/filter/read | `backend/app/Modules/Notifications/NotificationInboxService.php`, `NotificationInboxController.php`, `NotificationCursor.php` |
| Workflow permission/action options | `backend/app/Modules/Workflow/Execution/BusinessWorkflowService.php`, `BusinessWorkflowRules.php` |
| Assignment CRUD | `backend/app/Modules/Notifications/WorkflowNotificationAssigneesService.php` |
| Route M11 | `backend/app/Config/M11Routes.php` |
| Runtime switches | `backend/app/Modules/Notifications/NotificationRuntimeConfigRules.php` |
| Tasks | `backend/app/Modules/Notifications/NotificationTasksPlan.php`, `backend/app/Config/Tasks.php` |
| Inbox frontend | `frontend/src/features/notifications/pages/NotificationsInboxView.vue` |
| Bell/popover | `frontend/src/components/AppShell.vue`, `frontend/src/components/app-shell/useShellHeader.ts` |
| TS DTO/deep link | `frontend/src/services/notificationInbox.ts` |
| Session-aware store | `frontend/src/stores/notifications.ts` |
| Feature flag | `frontend/src/features/notifications/featureFlags.ts` |
| OpenAPI | `contracts/openapi/openapi.yaml` |
| M11.7 Operations (đã có trong ZIP) | `docs/Notifications/M11_7_Notification_Operations_Center_Implementation_Design.md` |

---

## 15. Quyết định cuối và checkpoint bàn giao

**Không đề xuất “đập đi xây lại” M11.** Bản v1.8.0 đã có hạ tầng đủ mạnh cho Delivery, nhưng sản phẩm chưa bao phủ đủ các hành động ERP thiết yếu. Mục tiêu của vòng nâng cấp là **giảm độ phức tạp khi thêm sự kiện và giúp người dùng hoàn thành công việc**, không phải giảm cơ học từ 9 xuống 5 bảng.

**Bước lập trình được chọn trước tiên:** `NFX-A1` — clickable Bell + target routing/fallback an toàn + UI tests, **không DB migration và không sửa worker**. Sau khi A1/A2 đạt nghiệm thu, triển khai My Work trên assignment + Workflow PBAC hiện có. Chỉ sau khi người dùng có trải nghiệm nền tảng tốt mới mở rộng catalog nghiệp vụ và UI chính sách.

**Trạng thái tài liệu:** Sẵn sàng làm baseline thiết kế để review/phê duyệt. **Chưa phải bằng chứng các tính năng NFX-A…E đã được triển khai hoặc kiểm thử chạy thật.**
