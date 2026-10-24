# DinkERP Backend v2 — M3.2 PBAC Runtime Integration

**Bản cập nhật:** M3.2 (23/10/2026). **Trạng thái:** Đã viết và kiểm thử offline; chưa nghiệm thu so sánh Runtime DB và HTTP trên máy chủ của người dùng.

## Phạm vi chốt

- `backend/` là Backend v2; `backend_basline/` chỉ dùng tham chiếu.
- `frontend/`, OpenAPI, 22 HTTP operations, database/schema, quyền đã lưu, `.env`, `vendor`, `backend/public/upload/` **không thay đổi**.
- Không bổ sung CRUD, migration, seeder, background task hoặc HTTP endpoint mới.
- Bộ quy tắc M3.1 gồm **8 Native State + 2 All States Only + 13 Lifecycle trên catalog DB đã audit**; một resource mới ngoài 10 ngoại lệ bắt buộc đi qua Lifecycle.

## Đã triển khai

| Thành phần | Vai trò |
|---|---|
| `Access/RuntimeActorReader.php` | Snapshot Identity/Shield + Base Permission + ERP group ACTIVE + employee + UTC-effective assignments; lấy từ DB, không nhận actor/scope từ client |
| `Access/RuntimePolicyReader.php` | Tra cứu Resource/Action và Policy ACTIVE theo `organization ancestors`, `state_id` và action, trả policy ID chính xác; cache **chỉ trong instance** |
| `Access/UnifiedAccessService.php` | Điểm quyết định VIEW cho persisted targets, dùng Resource State Repository và AccessDecisionCore/PbacDecision M3.1; không dùng client State hint |
| `Access/ReadAccessBridge.php` | Chuyển đổi theo chế độ `legacy`/`shadow`/`enforce`, mặc định **legacy**; không fallback sang `legacy` khi `enforce` bị lỗi |
| `ReadApi/ReadCatalogController.php` | Sử dụng Bridge thay vì trực tiếp ReadViewGate tại list, detail, organization tree và label lookup; không đổi contract |
| `Commands/M32Verify.php` | So sánh quyết định `ReadViewGate` M2.1.1 ↔ `UnifiedAccessService` trên user/row thật; **SELECT only** |
| `tests/m32_runtime_pure.php` | Test Runtime Actor, scope, State, policy, deny/absolute, bridge; query-builder fixture, không yêu cầu vendor/DB |
| `tests/m32_baseline_lanes_pure.php` | Gọi chính **các hàm lane** trong `backend_basline` qua reflection để đối chiếu 1.024 ma trận policy |

**Thứ tự kiểm tra:** Resource State đã xác thực từ bản ghi DB → Base Permission → tình trạng nhân viên → assignment hiệu lực UTC → scope tổ chức → Policy lane. `ABSOLUTE_DENY` phủ quyết; `DENY` thông thường không phủ quyết khi còn nhánh hợp lệ. Không có policy match không tự cấp quyền nếu Base Permission/employee/scope chưa đạt.

## Triển khai Overlay an toàn

Giải nén file `DinkERP_Backend_v2_M3.2_Overlay.zip` vào root dự án (ví dụ `D:\XAMPP\htdocs\erp`). **Backup trước các file nếu đã sửa cục bộ.** Overlay không có `.env`, Frontend, Backend baseline hay tệp upload.

PowerShell tại `D:\XAMPP\htdocs\erp`:

```powershell
php backend\tests\m1_pure.php
php backend\tests\m2_pure.php
php backend\tests\m21_pure.php
php backend\tests\m211_routes_pure.php
php backend\tests\m3_pure.php
php backend\tests\m3_repository_pure.php
php backend\tests\m3_lane_parity_pure.php
php backend\tests\m32_runtime_pure.php
php backend\tests\m32_baseline_lanes_pure.php

cd backend
php spark m3:audit
php spark m32:verify
php spark routes
```

`m32:verify` mặc định lấy tối đa **12 user, 25 bản ghi/resource**, so sánh 7 Resource M2.1.1. Có thể mở rộng mẫu với `php spark m32:verify 50 100` (giới hạn 50 user và 100 bản ghi cho mỗi Resource). Đây là **mẫu có giới hạn**, không phải chứng nhận đầy đủ policy matrix; nếu bảng trống sẽ in NOTE. `m32:verify` báo tổng ALLOW/DENY, mismatch và lỗi; mismatch/error làm lệnh thất bại. Lệnh không ghi dữ liệu.

**Quan trọng:** `m32:verify` so sánh runtime mới với **read gate M2.1.1 đang được Frontend nghiệm thu**, *không phải* một instance HTTP độc lập của `backend_basline`. Bài test 1.024 trường hợp dùng mã baseline thật nhưng chỉ đối chiếu **thuật toán policy-lane** bằng fixture. Vì thế, **full actor/HTTP parity với backend_basline vẫn chưa được chứng minh**.

## Ba chế độ chuyển dần

Mặc định khi không cấu hình: `legacy`. Không thay đổi hành vi và không kích hoạt truy vấn M3.2 trong các GET hiện có.

Có thể đặt trong `backend/.env` (cấu hình **ứng dụng**, không phải Apache):

```dotenv
DINKERP_M32_READ_MODE=shadow
```

| Giá trị | HTTP behavior | Khi dùng |
|---|---|---|
| `legacy` | Chỉ ReadViewGate M2.1.1 (mặc định) | An toàn nhất trong lúc chưa có parity runtime |
| `shadow` | Trả kết quả của ReadViewGate M2.1.1; đánh giá thêm bằng M3.2 và ghi warning nếu khác hoặc M3.2 lỗi; giới hạn warning | Sau `m32:verify` không có lỗi, dùng để quan sát ở Frontend có dữ liệu thật |
| `enforce` | Chỉ quyết định M3.2; nếu lỗi cấu hình/State/DB, **fail closed**, không fallback | **Chưa được khuyến nghị** cho đến khi user/role/policy negative tests + E2E + baseline differential HTTP đạt |

Nếu có lỗi trong `shadow`, trả lại `legacy` và kiểm tra log `M3.2 PBAC shadow comparison`. **Không dùng `enforce` để thử lỗi**, vì sẽ thay đổi hành vi API đã nghiệm thu.

M3.2 hiện chỉ hỗ trợ persisted VIEW authorization. Gọi Action khác `view` sẽ bị từ chối ở service. Chưa mở API thêm/sửa/xóa hay Transaction/write audit.

## Offline QA trên gói code

- Hồi quy M1, M2, M2.1, M2.1.1, M3.1: PASS.
- Runtime/policy fixture M3.2: **22/22 PASS**.
- Policy-lane parity bằng thuật toán trong `backend_basline`: **1.024/1.024 PASS**.
- Policy-lane parity với M2.1.1: **729/729 PASS**.
- Không sửa Frontend/baseline (xác minh SHA-256); không có route/migration mới.
- Lint toàn bộ `backend/app` và `backend/tests`: PASS.

## Mốc nghiệm thu M3.2 runtime

1. `php spark m3:audit`: PASS (người dùng đã chạy bản M3.1).
2. `php spark m32:verify 50 100`: mismatch=0, errors=0 và tối thiểu có trường hợp ALLOW + DENY; Resource có bảng trống phải bổ sung fixture.
3. Đặt `DINKERP_M32_READ_MODE=shadow`, chạy UI trên tài khoản toàn quyền, hạn chế, không assignment, policy DENY và ABSOLUTE_DENY. Không có warning sai lệch; response list/count/detail/tree/lookup và CSRF không đổi.
4. Đối chiếu thêm với **backend_basline** khi có môi trường HTTP độc lập để xác nhận behavior; cần làm trước khi quyết định bật enforce.
5. Không yêu cầu migration hay cập nhật dữ liệu trong M3.2.

**Tiếp theo (M3.3):** Access management read API (Resources/Groups/Policy) và enforcement chung cho các resource mới. API mutation chỉ mở sau khi có authorization write-transaction/audit, không dùng service VIEW của M3.2 để cấp quyền ghi.
