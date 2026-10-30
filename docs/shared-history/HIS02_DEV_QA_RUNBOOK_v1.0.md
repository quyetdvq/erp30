# HIS-02 DEV deployment & QA runbook

> **Chỉ chạy trên DEV có backup và đã triển khai HIS-01.** Không sửa data bằng tay; không chạy migration không rõ nguồn. User thực hiện HTTP/browser UAT.

## 1. Apply patch và kiểm tra trước database

```powershell
cd D:\XAMPP\htdocs\erp
$patch = '.\DinkERP_HIS02_Actor_Format_System_Monitor_Hotfix.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'Patch mismatch' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'Apply failed' }
git diff --check
if ($LASTEXITCODE -ne 0) { throw 'Diff check failed' }

cd .\backend
php spark migrate:status
```

Xác nhận HIS-01 migration `2026-10-29-000026` đã hoàn thành; chỉ có migration HIS-02 `000027` và các migration được DBA duyệt mới được thực hiện. Sao lưu database trước khi chạy `php spark migrate`. DDL có thể lock index một thời gian; triển khai trong cửa sổ bảo trì phù hợp. Sau đó `php spark migrate:status` để kiểm tra `000027`.

## 2. Chuẩn bị quyền giám sát

```powershell
php spark history:monitor-provision CONFIRM_MONITOR_CATALOG_ONLY
```

Lệnh chỉ tạo Resource `business_history_monitor` (SYSTEM, ACTIVE, không lifecycle) và action `view`. Không cấp quyền nào. Tại trang Security, quản trị viên Security được ủy quyền cấp **Base Permission** `business_history_monitor.view` cho user/role kiểm toán và cấu hình **Policy PBAC SYSTEM** cho quyền tương ứng; không cấp đại trà. Đăng xuất/đăng nhập lại người kiểm toán để cập nhật `base_permissions`. Nếu chưa cấp hoặc bị từ chối bởi Policy, cả menu và HTTP đều không được đọc được.

## 3. Source/offline tests

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 RUN HIS-UNIT
.\tools\test-unit.ps1 RUN CUS-UNIT
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN ROUTES
.\tools\test-unit.ps1 RUN P1-STANDARDS

cd D:\XAMPP\htdocs\erp\frontend
pnpm run type-check
pnpm run build
pnpm run check:imports
```

## 4. User UAT

1. Đăng nhập user có quyền `customer.view`, mở Customer → History. Actor USER hiển thị `username (Họ Tên)` khi Shield Users có full_name; thời gian theo Display Preferences đang có hiệu lực, không ghi UTC thô.
2. Đổi User Preference timezone sang `Asia/Ho_Chi_Minh`, dateTimeFormat khác. Reload/điều hướng lại và kiểm tra cùng event giữ nguyên instant nhưng giờ hiển thị đổi đúng; chỉnh System setting khi không có User override và tải lại page.
3. Với account không được cấp `business_history_monitor.view`, `/system/history` không xuất hiện và truy cập HTTP trực tiếp `GET /api/v1/history/monitor` phải bị chặn (không chỉ ẩn menu).
4. Với tài khoản auditor đã cấp Base Permission + PBAC Policy, mở System → Giám sát thay đổi, lọc theo khoảng thời gian UTC, resource, event, actor ID; xem chi tiết và phân trang, không có nút sửa/xóa.
5. Thử filter sai (page_size > 50, 91 ngày, resource_id không có resource_type, query injection) và xác minh HTTP 400, không lộ SQL hoặc data.
6. Đối chiếu hai tài khoản (auditor / ordinary) để xác minh không rò rỉ Total/ID/diff; kiểm tra không có raw số điện thoại, ngân hàng, địa chỉ trong UI/API.
7. Tạo sự kiện Customer mới sau khi migrate, kiểm tra actor JOIN và không nhân bản event; các event trước HIS-01/other modules không xuất hiện vì không được ghi.
8. Nếu thiếu `username`/`full_name` (user đã xóa hoặc thông tin cũ), UI fallback `Người dùng #ID` mà không lỗi.

## 5. DB query review

Thực hiện read-only `EXPLAIN` trên truy vấn monitor với số bản ghi DEV thực tế, lưu lại plan và kích thước bảng (không chèn/xóa data). Kiểm tra plan COUNT/PAGE có cùng scope, index time/resource, projection actor JOIN chỉ trên page. Chính sách lưu giữ, lưu trữ dài hạn và DB-level UPDATE/DELETE deny thuộc release gate, không tự đánh dấu PASS.

## 6. Rollback

Git patch chỉ rollback khi code + schema/cấp quyền được DBA/Security đánh giá tương thích. Index `000027` có `down()` chặn; không tự xóa index trên production hoặc drop `business_history_events`. Có thể thu hồi Base Permission/Policy để tắt monitor ngay mà không xóa dữ liệu.
