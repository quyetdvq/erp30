# HIS-02 — Identity, Display Settings & System-wide History Monitor

> **Phiên bản:** v1.0 · 2026-10-29 · **Phạm vi:** thiết kế và source candidate đã triển khai; DEV MariaDB/HTTP/UAT chưa nghiệm thu.
> **Nguồn:** `docs/development-standards/`, HIS-01 Architecture, P2-SUP G1 v1.0.1, HIS-POL-001.

## 1. Những yêu cầu được thực hiện

1. Khi có actor USER, lịch sử thể hiện `username (full_name)` lấy từ dữ liệu Shield Users hiện thời, ví dụ `thangdv (Nguyễn Văn A)`. Actor SYSTEM/JOB hiển thị nhãn hệ thống, user đã xóa/thiếu tên fallback `Người dùng #ID`. Không lưu lại bản sao họ tên ở mỗi history row.
2. `created_at` DB của HIS-01 vẫn là UTC `DATETIME`, REST trả `YYYY-MM-DDTHH:mm:ssZ`. Giao diện dùng `useFormatters().formatDateTime()` với `useDisplaySettingsStore`, không tự format ở backend, không lấy timezone từ trình duyệt.
3. Route bảo vệ `GET /api/v1/history/monitor`, UI `/system/history`, filter ngày UTC 30 ngày mặc định/90 ngày tối đa mỗi lần, resource/event/actor/resource-id, page<=1000, size<=50. Không có public History create/edit/delete/export.
4. Resource PBAC độc lập `business_history_monitor` thuộc `SYSTEM` với duy nhất `view`; phải có Base Permission **và** policy hệ thống hợp lệ. Không auto grant kể cả admin. Lệnh provision chỉ tạo catalog (Resource + Action).

## 2. Kiến trúc và phân quyền

`BusinessHistoryController::monitor` → `HistoryMonitorReadService::listing` → canonical `UnifiedAccessService::decidePersisted('business_history_monitor','view', SYSTEM resource)` → `HistorySql::monitorParams()` → SQL COUNT và PAGE trên `business_history_events`. SQL dùng cùng phạm vi filter và bind, transaction `REPEATABLE READ`, sort ổn định `(created_at DESC, id DESC)` và `LEFT JOIN` Shield users chỉ trong truy vấn page (tránh N+1).

**Phạm vi quản trị:** `business_history_monitor.view` là quyền nhạy cảm **xem tất cả sự kiện trong bảng History**, không tương đương `customer.view` hay quyền Company. Phải cấp riêng cho người làm kiểm toán cấp hệ thống qua Security Resource/Policy. User thông thường chỉ đọc History của Customer được PBAC cho phép. Không trao quyền monitor cho role rộng như mọi employee.

### Chiến lược data minimization

- History chung giữ diff/checkpoint **đã loại trừ dữ liệu nhạy cảm** theo HIS-POL-001, không lưu số điện thoại, địa chỉ, token, mật khẩu hoặc số tài khoản ngân hàng nguyên văn.
- `actor_username`/`actor_full_name` là giá trị hiện tại từ `users` cho mục tiêu hiển thị, không phải snapshot tên tại thời điểm sự kiện; audit authority vẫn là `actor_user_id` bền vững.
- Request ID có thể xuất hiện trong chi tiết monitor để đối soát; không hiển thị cho Customer thường.
- **Không backfill** thay đổi của module chưa tích hợp. HIS-02 monitor chỉ liệt kê các event **đã được recorder ghi**; tại thời điểm này, pilot Customer là nguồn chính. Supplier và các module khác sẽ tích hợp sau.

## 3. Hợp đồng format System → Organization → User

- Lưu trữ UTC. Frontend phải parse instant có `Z` rồi format bằng `useFormatters` chung; định dạng, timezone lấy từ `Display Settings` hợp lệ, không hardcode ở History.
- `useDisplaySettingsStore` thực hiện server runtime (SYSTEM; hoặc SYSTEM→ORGANIZATION khi được truyền ngữ cảnh Organization có kiểm tra VIEW), rồi overlay User Preferences từ localStorage theo user.
- AppShell tự động tải server **SYSTEM** runtime khi user đăng nhập/khôi phục phiên. Trước đây chỉ trang System Display Settings tự gọi load; vì vậy ở các màn hình khác có thể rơi về default. HIS-02 sửa thiếu sót này.
- **Giới hạn ngữ cảnh:** `Customer`/`Supplier` là Group MASTER và bản thân app hiện không có ngữ cảnh Company được lựa chọn toàn cục sau login (`AuthUser` không có current_organization_id). Vì vậy không được tự suy đoán một Company. Tại màn hình History và System Monitor, thứ tự khả dụng là **SYSTEM → USER** nếu không có Organization context; quy tắc **SYSTEM → ORGANIZATION → USER** được dùng khi đã chọn Organization xác định qua runtime API. Muốn Organization áp dụng xuyên ứng dụng cần feature chốt current Organization trong session; không có trong HIS-02, và không nên giả định ngầm.
- Cấu hình mới được tải khi AppShell mount; khi đổi System Display Settings, màn hình settings tiếp tục gọi reload. Các máy khác cần tải lại trang hoặc tái đăng nhập để lấy runtime mới.

## 4. Migration & catalog

- Migration mới `2026-10-29-000027_AddHistoryMonitorIndexes` thêm index `ix_history_monitor_time(created_at,id)` và `ix_history_monitor_resource_time(resource_type,created_at,id)` vào bảng HIS-01 `000026`. `up` kiểm tra schema/index, không xóa dữ liệu, `down` chặn do yêu cầu review DBA.
- Không sửa fixture P1 180 routes. HIS-01 GET Customer + HIS-02 GET Monitor = 2 authorized extension routes, 182 thực tế. P1 architecture ALL_STATES giữ năm resource gốc và thêm đúng một extension `business_history_monitor`.
- Lệnh `php spark history:monitor-provision CONFIRM_MONITOR_CATALOG_ONLY` không cấp quyền. Security phải chốt user/group, Base Permission và Policy hệ thống; test quyền âm tính trước khi chấp nhận.
- Có thể dùng prefix `dinktech_`; tên bảng Users lấy từ Shield Auth config và kiểm soát identifier trước khi JOIN.

## 5. Giới hạn chưa nghiệm thu

Migration, provisioning, authenticated HTTP deny/allow, two-session PBAC, UI datetime/timezone, actor JOIN MariaDB/EXPLAIN và frontend type-check/build **cần chạy trên máy DEV XAMPP**. DB-level immutability, retention, Security Audit cho hành vi bank reveal vẫn là gate bảo mật độc lập, không được đánh dấu PASS chỉ vì có màn hình monitor.
