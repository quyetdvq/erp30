# DinkERP Backend v2 — M3.4B One-Command Local MariaDB Sandbox

## Mục tiêu và ranh giới

M3.4B là bộ **integration test cho Backend v2 thật**. Nó thay thế cấu hình thủ công hai HTTP server + hai database clone của M3.4A trong vòng phát triển thường ngày. Không thay đổi Frontend, `backend_basline`, 70 API, phân quyền runtime hay `.env` của production. Chỉ bổ sung một CLI nội bộ `m34b:attest`, công cụ `backend/tools/m34b`, ba bài test và tài liệu.

**Không đánh đồng:** M3.4B không chứng minh HTTP parity trực tiếp với baseline đang chạy. OpenAPI nguồn v1.2.4 được khóa SHA-256 và các bộ fixture baseline hiện có tiếp tục được chạy offline. Golden-master/dual-backend parity và concurrency vẫn là những cổng nghiệm thu sau này.

## Cách chạy — một lệnh

Vào thư mục gốc `D:\XAMPP\htdocs\erp` trong PowerShell:

```powershell
php backend\tools\m34b_run.php
```

**Không cần** `clones.local.json`, `run_id`, hai HTTP URL, hai PHP servers hay đăng nhập ERP thật. Không dùng `api.monmin.net` hoặc `apibase.monmin.net`.

### Điều kiện tối thiểu

1. Chạy với PHP XAMPP có `pdo_mysql`, `mysqli`, `openssl`, và backend đã cài `vendor/` qua Composer.
2. MariaDB chạy trên `127.0.0.1:3306`.
3. Ưu tiên schema **thử nghiệm** `erp_test` với cấu trúc bảng baseline v1.2.4. **Từ M3.4B.2**, nếu `erp_test` thiếu bảng/cột bắt buộc, runner tự động lấy **DDL/metadata** từ schema `erp` **trên MariaDB localhost**, không đọc hoặc sao chép hàng dữ liệu người dùng và không sửa schema nguồn. Nếu bạn không muốn dùng metadata `erp`, chỉ định một schema thử nghiệm đầy đủ bằng `M34B_SOURCE_SCHEMA`.
4. Tài khoản MySQL tại localhost có quyền đọc metadata của `erp_test` và `CREATE DATABASE`, `CREATE TABLE`, `INSERT`, `SELECT`, `UPDATE`, `DELETE`, `TRIGGER`, `DROP` trên schema sandbox tự tạo. XAMPP mặc định thường có `root` trống mật khẩu; nếu đã bảo vệ tài khoản root thì sử dụng account thử nghiệm có đủ quyền, không dùng credentials production.

Nếu MariaDB/PHP dùng tài khoản/cổng khác, khai báo **biến môi trường trong tiến trình PowerShell** (không bỏ mật khẩu vào JSON hay command arguments): `M34B_MYSQL_USER`, `M34B_MYSQL_PASSWORD`, `M34B_MYSQL_PORT`. Để chỉ định schema thử nghiệm khác, dùng `M34B_SOURCE_SCHEMA` với tên kết thúc `_test`, `_clone` hoặc `_sandbox`. Không thể nhập `erp` trực tiếp bằng biến này: `erp` chỉ được phép làm **metadata-only fallback tự động** sau khi phát hiện schema mặc định `erp_test` thiếu cấu trúc, theo M3.4B.2.

Nếu chỉ muốn chạy toàn bộ fixture/Pure Tests mà chưa có MariaDB:

```powershell
php backend\tools\m34b_run.php --offline
```

`--offline` trả `PASS_OFFLINE_ONLY`, **không** phải PASS integration HTTP/DB.

## Cơ chế tự động

1. Kiểm tra SHA-256 OpenAPI 1.2.4; xác minh đủ chính xác **29 mutation operations** và route CI4 explicit.
2. Chạy toàn bộ pure/fixture regressions từ M1 tới M3.4B. Nếu có FAIL, dừng trước khi tạo database.
3. Xác minh tên schema nguồn và các metadata bảng/cột; không kết nối đến máy chủ MySQL ngoài loopback.
4. Tạo schema mới `erp_m34b_<nonce>_sandbox` bằng CSPRNG. Nếu đã tồn tại, **từ chối tái sử dụng**. Sao chép **DDL thuần túy**, từ chối FK cross-schema; dựng `m34b_sandbox_guard` với nonce.
5. Seed dữ liệu **tổng hợp**: một tổ chức GROUP root, một nhân viên và assignment ACTIVE, một user Shield ngẫu nhiên, group ERP/Shield, Resource Action catalog, State definitions. Không sao chép email, mật khẩu hay bất kỳ bản ghi người dùng nào từ `erp_test`.
6. Fork CLI attestation để chứng minh CI4 trỏ đúng schema sandbox, rồi chạy PHP built-in server chỉ tại `127.0.0.1:<random_port>`. Router xác thực schema và nonce **trên từng request**, nếu thất bại trả 503 trước khi vào CI4.
7. Thử 29 routes không đăng nhập ở hai locale (**58 probes**), 29 requests sai CSRF, 29 requests đã đăng nhập nhưng không hợp lệ trong `en/vi` (**58 probes**), sau đó **29 mutation thành công** theo đúng thứ tự phụ thuộc. Mỗi mutation 2xx phải gây thay đổi ít nhất một trong 17 bảng Access/Shield được theo dõi.
8. Xác nhận `ABSOLUTE_DENY` thực tế chặn mutation, kiểm tra password hash sau reset của Shield bằng `password_verify`, rồi cố tình gây lỗi `INSERT policy_actions` bằng **trigger chỉ tồn tại trong sandbox** để chứng minh rollback SQL trên MariaDB (fingerprint 17 bảng không đổi).
9. Xuất JSON report không chứa mật khẩu ở `backend/writable/m34b/reports/`. Nếu toàn bộ real tests PASS, dọn sandbox đã được tự kiểm chứng; thêm `--keep` nếu muốn giữ sandbox phục vụ QA. Nếu FAIL/BLOCKED, giữ sandbox để phân tích; không tự xóa hoặc thay đổi schema nguồn.

## Chặn an toàn

- Phần mutation production **vẫn** `DINKERP_M33B_WRITE_MODE=off`. Child test server mới có `enabled` trong môi trường riêng, không ghi `.env`.
- Chỉ `127.0.0.1`, không dùng API public, proxy domain hay kết nối MariaDB từ xa.
- Hàng rào DB độc lập gồm nonce, schema name, marker, CI4 child attestation và per-request router attestation.
- Không xóa bất kỳ schema đã tồn tại trước lượt chạy; `DROP DATABASE` chỉ áp dụng lên schema tự tạo, xác minh marker đúng nonce của tiến trình.
- Chỉ fingerprint 17 bảng quan trọng được liệt kê trong nguồn (`users`, `auth_identities`, permissions, groups, policies, states, lifecycle, v.v.); **không khẳng định mọi bảng ngoài danh sách không đổi**. Session file và log hoạt động có thể thay đổi khi đăng nhập.
- Nếu bất kỳ điều kiện chuẩn bị không đạt: `BLOCKED_ENV` (exit 2); test thất bại: `FAIL_*`/`NEEDS_FIX_OR_VERIFY` (exit 1); `PASS` (exit 0) chỉ khi các kiểm thử thật, 29 mutation, Shield và rollback cùng PASS.

## Báo cáo và xử lý sự cố

Mẫu trạng thái (không phải kết quả đã được kiểm thử trên máy người dùng):

```text
M3.4B: pinned 29/29 mutation operations.
M3.4B OFFLINE: .../... suites PASS.
M3.4B: checking local schema-only template erp_test, creating erp_m34b_..._sandbox.
M3.4B REAL: 29/29 successful, 58/58 unauth, 29/29 CSRF; rollback=PASS_DB_ATOMICITY_NOT_BASELINE_PARITY
M3.4B report: .../backend/writable/m34b/reports/m34b_....json
```

Nếu báo **`pdo_mysql required`**, kiểm tra `D:\XAMPP\php\php.exe -m`. Nếu **`vendor missing`**, chạy Composer trong `backend/` mà không sửa baseline. Nếu **`erp_test` thiếu bảng/cột**, M3.4B.2 sẽ thử **đọc metadata/DDL** từ `erp` localhost rồi tạo sandbox mới. Nếu `erp` cũng thiếu cấu trúc hoặc thiếu quyền đọc metadata, công cụ sẽ BLOCKED trước khi tạo sandbox; bạn có thể cung cấp một schema test hoàn chỉnh bằng `M34B_SOURCE_SCHEMA` hoặc chỉ chạy các bộ offline/DB-READ. Công cụ không chạy migrations lên production. Nếu **`CREATE DATABASE access denied`**, dùng MySQL account thử nghiệm có CREATE/DROP schema, hoặc chỉ `--offline` cho tới khi có điều kiện; không tự cấp quyền trên production. Nếu **`Clone login rejected`** hoặc `M34B REAL ... <29/29`, mở report và `backend/writable/m34b/run_<nonce>/http_stderr.log` để xem chẩn đoán. Mọi FAIL phải được điều tra trước khi bật production.

**Chưa được nghiệm thu tại thời điểm đóng gói:** HTTP/SQL thực tế trên MariaDB XAMPP của người dùng, so sánh baseline sống, concurrency/race, toàn bộ tác dụng phụ ngoài 17 bảng. **M3.4C sau bước này**: tách User Management về Identity và Workflow/Lifecycle về Workflow, thu gọn Access, giữ nguyên API contract.
