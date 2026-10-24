# M3.4B.2 — One-command schema-only fallback

## Vấn đề đã quan sát

Trên XAMPP, `CHECK` PASS, `RUN` đạt 39/39, `RUN DB-READ` PASS, nhưng `RUN INTEGRATION` dừng tại:

```text
M3.4B BLOCKED: Local schema template is missing required table users; no sandbox was created.
```

`erp_test` chưa được migrate đầy đủ. Đây là `BLOCKED_ENV`, không chứng minh API mutation bị lỗi. M3.4B.2 loại bỏ thao tác thủ công phải xây dựng `erp_test` từ đầu, **không bỏ qua** việc xác minh bảng/cột trước khi dựng sandbox.

## Cách sử dụng

Không cần chỉnh `.env`, không cần nhập JSON, không cần tạo/migrate `erp_test`. Sau khi áp dụng Overlay vào `D:\XAMPP\htdocs\erp`, chạy:

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN DB-READ
.\tools\test-unit.ps1 RUN INTEGRATION
```

`RUN` vẫn offline, `DB-READ` chỉ đọc kết nối hiện có. Chỉ `INTEGRATION` mới tạo một schema `erp_m34b_<nonce>_sandbox`, seed dữ liệu tổng hợp và thực hiện HTTP mutations **trong sandbox**.

## Quy tắc mới khi chạy INTEGRATION

1. Ưu tiên schema nguồn `erp_test` có đủ cấu trúc.
2. Nếu schema mặc định thiếu bảng hoặc cột bắt buộc, tự kiểm tra **metadata schema `erp` trên localhost**: `information_schema.TABLES`, `information_schema.COLUMNS`, và sau khi đã chứng thực đầy đủ mới dùng `SHOW CREATE TABLE`.
3. Schema `erp` tuyệt đối không trở thành **Database đích**. Chỉ có `CREATE DATABASE erp_m34b_<nonce>_sandbox` khi cấu trúc nguồn đầy đủ; không có `SELECT *`, sao chép dữ liệu, `INSERT`, `UPDATE`, `DELETE`, `ALTER` hoặc `DROP` đối với `erp`.
4. Bỏ `AUTO_INCREMENT=<n>` từ định nghĩa bảng được sao chép, để không mang counter của hệ thống thật sang sandbox.
5. Nếu `erp_test` hợp lệ: không cần đọc schema `erp`. Nếu `M34B_SOURCE_SCHEMA` được chỉ định: chỉ sử dụng schema test/clone/sandbox đó; **không tự động fallback**.
6. Nếu thiếu quyền đọc metadata, schema `erp` không hợp lệ hoặc gặp bất kỳ lỗi SQL khác: **BLOCKED trước khi CREATE DATABASE**. Không được bỏ qua đối chiếu schema.

**Giới hạn bảo mật:** `erp` có thể đang phục vụ production và chỉ được đọc **DDL/schema metadata** từ MariaDB localhost. Đây là một thay đổi có chủ đích so với M3.4B.1, vốn cấm cả việc tham chiếu `erp` làm schema nguồn. Việc đọc metadata có thể gây metadata lock ngắn; không sao chép bản ghi hoặc mật khẩu. Nếu không muốn đọc metadata từ `erp` đang chạy, cung cấp schema thử nghiệm hoàn chỉnh bằng `M34B_SOURCE_SCHEMA=<your_test_schema>` hoặc chỉ chạy `RUN`/`DB-READ`.

## Nghiệm thu

- `backend/tests/m34b2_template_pure.php`: 21 kiểm tra tình huống nguồn hợp lệ, thiếu bảng, thiếu cột, SQL lỗi, explicit source, tên schema cấm, không chuyển nhầm database đích, trật tự kiểm tra và quyền child process.
- Chạy `php backend/tools/m34b_run.php --offline`: **40/40** bộ pure/fixture PASS tại môi trường đóng gói.
- `php backend/tools/test-unit-check.php`: **40 scripts, 29 mutation operations, 7 DB-READ audit commands**.
- M3.4B.2 **chưa** được chạy trên MariaDB thật hoặc Windows PowerShell trong môi trường đóng gói (không có `pdo_mysql`, Composer vendor/). Kết quả `RUN INTEGRATION` trên XAMPP là điều kiện nghiệm thu tiếp theo.

## Không thay đổi

- Không thay đổi `.env` hoặc bật write mode production.
- Không sửa `frontend/`, `backend_basline/`, API routes, PBAC service hoặc transaction nghiệp vụ.
- Không thay thế cổng baseline HTTP parity thực tế. `INTEGRATION` chỉ chứng minh Backend v2 với dữ liệu tổng hợp trong một sandbox.
- M3.4C tiếp theo: chuyển Security User sang Identity và Lifecycle/Workflow sang Workflow, thu gọn Access mà giữ nguyên hành vi/contract.
