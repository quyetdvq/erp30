# ADR M11.2 — Workflow notifications (triển khai bổ sung)

**26/10/2026 · Trạng thái: code hoàn thiện cho DEV rehearsal, chưa nghiệm thu MariaDB/HTTP E2E**
**Nền source:** ERP v1.6.0 + Migration/Seeder port + M11.1A + M11.1B + Hotfix 01/02/03. M11.1B Import → Outbox → Inbox và mark-read đã được người dùng xác nhận PASS.

## 1. Quyết định

Workflow hiện có Lifecycle/transition nhưng **không có nguồn phân công người duyệt tin cậy**. Không coi `resource.view` hoặc quyền nhóm chung là một queue phê duyệt, không gửi cho tất cả User có quyền đọc.

M11.2 bổ sung **phân công trực tiếp theo record** qua hai endpoint:

- `GET /api/v1/business-workflow/{resource}/{id}/notification-assignees`
- `PUT /api/v1/business-workflow/{resource}/{id}/notification-assignees`

Chỉ chủ thể có **cả quyền VIEW và SETSTATE PBAC trên record hiện tại** được đọc và thay danh sách. `PUT` có `{ "expected_revision": "0", "user_ids": ["1", "2"] }`, tối đa 25 User ID Shield, phải active/chưa bị khóa/xóa. Version là BIGINT decimal string; request cũ trả `409`, duplicate/không hợp lệ trả `422`. Không cấp ngầm bất kỳ quyền RBAC/PBAC nào cho người được gán. GET khi chưa gán trả `{revision:"0",user_ids:[]}`. Việc gán user **không tự cấp quyền thao tác Workflow**.

**Persistence:** Migration 020 tạo `workflow_notification_assignment_sets` (1 row/target, revision/CAS) và `workflow_notification_assignees` (PK resource+target+recipient), dùng DB prefix hiện hành. `PUT` khóa row nghiệp vụ trước các row assignment; transaction cũng ghi audit vào `configuration_audit_events` với `configuration_type=WORKFLOW_NOTIFICATION_ASSIGNMENT`, `context_type=RESOURCE`. Khi không có thay đổi, không bump revision/audit. Không migration hoặc chạy Seeder mới cho 4 bảng M11.1.

## 2. Producer và recipient snapshot

`BusinessWorkflowService::execute()` cập nhật trạng thái trong transaction đã có và gọi producer **trước COMMIT**. Producer chỉ chạy khi `DINKERP_M11_WORKFLOW_PRODUCER_ENABLED=enabled` (mặc định off), và chỉ ghi sự kiện nếu:

1. Trạng thái đích tồn tại trong Lifecycle và còn active outgoing transition (có việc tiếp theo).
2. Record đã được gán ít nhất một User trong bảng phân công.
3. Event schema version 2 hợp lệ với `resource_type`, `from_state`, `to_state`, `action`, và snapshot `recipient_user_ids` (tối đa 25).

`event_key=workflow:{resource}:{id}:{random_hex32}` là bất biến và khác nhau cho mỗi lần chuyển trạng thái (kể cả quay lại state cũ). `source_type=workflow_transition`; không dùng client-supplied recipient/state. Lúc gán đồng thời chuyển trạng thái, cả `PUT` và `execute()` đều khóa **business row trước** nên snapshot xác định theo transaction nào giành khóa trước. Nếu không có người gán, không ghi event và không broadcast.

## 3. Dispatcher và quyền tại thời điểm phát

Worker M11.1B giữ nguyên claim/lease/retry/ACK. Nhánh M11.2:

- Xác thực event key, source, payload, target/subtype; `SELECT ... FOR UPDATE` row đích.
- Nếu state hiện tại khác `to_state` của event, `SUPPRESSED` với reason `NOTIFICATION_WORKFLOW_STATE_CHANGED` (tránh yêu cầu hành động đã lỗi thời).
- Một JOIN SQL đọc System policy `workflow.action_required.in_app` + các User preferences trong một snapshot `READ-COMMITTED`.
- Với mỗi User thuộc **snapshot tại lúc transition**: chỉ xử lý nếu User còn active, policy có hiệu lực, và chính User còn **VIEW + ít nhất một next action SETSTATE** theo `BusinessWorkflowService::options()` (PBAC/tình trạng thực).
- Tạo Inbox qua unique `(recipient_user_id,event_key)`; **mọi Inbox của event + ACK Outbox trong cùng transaction**. Nếu một INSERT/DB operation lỗi, rollback toàn bộ. Không thể ACK một phần recipient. User hết quyền hoặc bị vô hiệu hóa không nhận tin. Sự kiện hợp lệ nhưng không người nhận đủ điều kiện được `SUPPRESSED`.
- Vòng đời Inbox giữ lại lịch sử đã giao; mở trang đích vẫn phải qua PBAC riêng. Chỉ truyền allowlisted resource/state, không truyền nội dung chứng từ nhạy cảm. Không thay thế task queue/approval engine thực thụ.

Giới hạn MVP: tối đa **25 assignees/record**, không dùng batching phân phối giữa nhiều transaction. Nếu muốn hàng trăm/nghìn recipient, cần delivery ledger/partition mới, không tăng MAX một cách tùy tiện. Permission có thể đổi đồng thời với các câu SQL PBAC; E2E/concurrency cần kiểm thử thực tế trên MariaDB 10.4; không tuyên bố snapshot quyền tuyệt đối cho cả transaction READ-COMMITTED.

## 4. Bật thử có kiểm soát

```ini
DINKERP_M11_WORKFLOW_WRITE_MODE=off
DINKERP_M11_WORKFLOW_PRODUCER_ENABLED=off
DINKERP_NOTIFICATIONS_DELIVERY_ENABLED=enabled
```

- `WORKFLOW_WRITE_MODE=enabled` **chỉ** mở PUT assignees và endpoint `BusinessWorkflowController::execute()` (route Lifecycle), không bật tất cả M33B mutations.
- `WORKFLOW_PRODUCER_ENABLED=enabled` cho phép producer mới tạo event, **không tự replay Workflow cũ**.
- `DELIVERY_ENABLED` vẫn là switch toàn cục cho Worker; Import producer giữ cờ cũ không đổi.
- UI phân công theo ID được thêm vào dialog `BusinessWorkflowAction` khi Vite flag `VITE_M11_NOTIFICATIONS_ENABLED=true` và mode `LIFECYCLE`. Đây là form vận hành DEV; bước sau có thể thay bằng user picker/search theo PBAC.
- Catalog `workflow.action_required.in_app` chuyển `AVAILABLE`; policy row cũ được giữ **nguyên giá trị quản trị**. `security.permission_changed.in_app` và email vẫn `UNAVAILABLE`; không có security event producer ở đợt này.

## 5. Acceptance trên DEV clone

1. Backup database, `php spark migrate` chạy Migration `2026-10-26-000020`, đối chiếu 2 bảng mới; không chạy lại M11.1 Seeder.
2. Thử GET/PUT dưới tài khoản không đủ VIEW/SETSTATE → 403; POST/PUT không có CSRF → bị chặn bởi HTTP filter. PUT cùng revision từ hai phiên → một lần PASS, lần còn lại 409; PUT no-op giữ revision và không thêm audit; >25 recipient, user mất hiệu lực, duplicate/ID không hợp lệ → 422 toàn bộ, không ghi dở.
3. Tại một Lifecycle có `State A → State B → State C` và 2 User được gán: đảm bảo cả hai User thực sự có `view` và action tiếp theo trên State B. Gán bằng dialog, chuyển A→B; kiểm tra Outbox PENDING, chụp snapshot, chạy `notifications:dispatch --once --batch=25`. Kỳ vọng 1 event `PROCESSED`, 2 Inbox (mỗi User một), Worker chạy lại không thêm row. Inbox UI hiện thông báo Workflow với link được allowlist và mark-read hoạt động.
4. Chuyển B→C trước khi dispatch A→B → event cũ `SUPPRESSED`, không phát nhiệm vụ đã lỗi thời. Thu hồi quyền của một User trước dispatch hoặc inactive User → người đó không nhận, người còn đủ quyền vẫn nhận. Đổi danh sách gán **sau khi event đã ghi** không đổi snapshot của event cũ.
5. Fault injection trong fan-out multi-recipient: giả lập lỗi INSERT của recipient thứ hai, xác nhận **không có Inbox thứ nhất đã commit** và Outbox không ACK `PROCESSED`; dùng retry/backoff. Thử hai workers/expired lease; không duplicate, không cross-user leak. Các bước này chưa được chạy trong môi trường tác giả PATCH.
6. Kiểm tra regression Import Job → Outbox → Inbox cũ sau khi bật Workflow.

## 6. Phạm vi chưa triển khai

- Thông báo từ mutation/native `set-state` không đi qua `BusinessWorkflowService::execute()`, security.permission_changed, email/push và các sự kiện chung khác.
- UI tìm kiếm người nhận theo tên, quản trị nhiệm vụ/approval queue, auto-assignment theo cơ cấu tổ chức hoặc fallback tự động broadcast.
- Nhắc hạn/retention/archival, bảng delivery receipt phân đoạn cho fan-out lớn.

Không đổi fingerprint của các gate viết lại backend lịch sử; nghiệm thu bằng regression hiện hành, API/PBAC trên DEV và kiểm thử transaction thực tế.
