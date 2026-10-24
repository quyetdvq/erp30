# M3.4D — Final Regression & Architecture Acceptance

**Nền mã nguồn:** DinkERP Backend v2 **M3.4C** (chủ hệ thống đã báo cáo `RUN`, `DB-READ` và `RUN INTEGRATION` PASS trên XAMPP: 45/45 offline suites, 29/29 successful HTTP mutations, 58/58 unauthenticated, 29/29 CSRF, SQL atomicity PASS).

**Phạm vi M3.4D:** thêm **gate nghiệm thu, bằng chứng máy đọc được và kiểm tra kiến trúc**, **không** mở thêm API; **không** sửa nghiệp vụ `backend/app`; **không** chỉnh Frontend, `backend_basline`, `.env`, chính sách PBAC hoặc schema thực tế. **M3.4D không đồng nghĩa đã đạt live-baseline HTTP parity.**

## 1. Kết quả nghiệm thu / ranh giới bằng chứng

| Gate | Phạm vi | Trạng thái mong đợi khi RUN | Chú thích |
|---|---|---|---|
| `OPENAPI_70` | Đối chiếu 70 method/path/handler với 150 operation OpenAPI v1.2.4 SHA-256 pinned; chính xác 29 mutation | `PASS` (static) | Cũng khóa fingerprint từ M3.4B.5, kiểm tra cả controller `public function` và 2 route OPTIONS |
| `MODULE_GRAPH` | Graph tham chiếu module bằng token PHP; kiểm tra không dependency vòng, tách `Identity`, `Access`, `Workflow` | `PASS` (static) | Không đặt User/Workflow mutation dưới Access; Identity và Workflow gọi Access PBAC |
| `PBAC_STATE` | Centralized `UnifiedAccessService`, `ABSOLUTE_DENY` và 8 Native / 2 All States | `PASS` (static) | Truy vấn dữ liệu/authorization thật vẫn do `DB-READ` và `INTEGRATION` kiểm chứng |
| `SHIELD_WORKFLOW` | Shield User model, password setter, transaction rollback; Lifecycle configure chung, constraints | `PASS` (static) | Hash/password, rollback và veto thật cần report M3.4B |
| `HTTP_SECURITY` | Routing explicit, CSRF session/filter, JSON error envelope, write-gate default OFF | `PASS` (static) | HTTP thật được kiểm chứng riêng |
| `BASELINE_LOCALES` | 74 locale files v1.2.4 SHA-256 pinned, 23 `en` + 51 `vi`, các key lỗi API | `PASS` (static) | Phải chạy negative bilingual trên sandbox |
| `LOCAL_SANDBOX_HTTP_DB` | 29 positive, 58 unauth bilingual, 29 bad-CSRF, 58 authenticated invalid bilingual; real Shield hash, ABSOLUTE_DENY, SQL rollback | `NOT_SUPPLIED` ở RUN; `PASS` sau RUN INTEGRATION thực | Chỉ chấp nhận JSON *của phiên bản runtime hiện tại* (SHA-256 source tree) |
| `LIVE_BASELINE_HTTP_PARITY` | Baseline v1.2.4 chạy độc lập với Backend v2 | `NOT_TESTED` | Chưa có independent live request/response + DB side-effect differential evidence |
| `TRANSACTION_ISOLATION_PARITY` | Concurrency/locking/Isolation-level parity | `NOT_TESTED` | Forced MariaDB rollback trong sandbox chưa thay thế baseline concurrency parity |
| `ALL_70_LIVE_RESPONSE_PARITY` | HTTP responses của cả 70 API đã đăng ký | `NOT_TESTED` | Có 70/70 *source contract*, 29/29 *local mutation HTTP*, không phải 70/70 live HTTP |

**Cổng production:** `BLOCKED` kể cả khi 6/6 static gates và local sandbox đều PASS. Production phải giữ **`DINKERP_M33B_WRITE_MODE=off`**. Các phần còn thiếu được liệt kê trong báo cáo, không tự chuyển thành PASS.

### Tính chặt của report M3.4B

Kể từ M3.4D, `m34b_run.php` ghi `backend_source_sha256` tính trên **toàn bộ PHP runtime dưới `backend/app`**, theo path tương đối và checksum byte. M3.4D chỉ nhận `--integration-report` nếu:

- Nằm chính xác trong `backend/writable/m34b/reports/`, đúng định dạng tên JSON và khai báo `production_mutations=NEVER`, `baseline_live_parity=NOT_TESTED`.
- `backend_source_sha256` khớp runtime hiện tại; report M3.4C cũ không có hash và **không tự được nâng cấp thành bằng chứng của phiên bản khác**.
- Có đủ **29 distinct positive response receipts** với đúng status từ OpenAPI pinned **và ít nhất một bảng vật lý thay đổi** sau mỗi mutation.
- `unauthenticated_bilingual_probes=58`, `invalid_csrf_probes=29`, `authenticated_invalid_bilingual_probes=58`, có ít nhất một cặp thông báo dịch khác nhau.
- `child_database_attested=true`, `shield_session_authenticated=true`, rollback=`PASS_DB_ATOMICITY_NOT_BASELINE_PARITY`, danh sách lỗi rỗng, tên schema khớp `erp_m34b_<nonce>_sandbox`.
- Có ba bằng chứng độc lập `shield_hash_after_reset`, `real_absolute_deny_veto`, `forced_mariadb_policy_action_insert_rollback`.

Đây là **cross-run content-version check**, không phải chữ ký mật mã chống việc người có quyền ghi local JSON làm giả report. Kiểm chứng độc lập baseline vẫn cần một lane riêng.

## 2. Cài đặt Overlay trên M3.4C

Overlay ZIP có root `erp/`; giải nén vào `D:\XAMPP\htdocs\` để cập nhật `D:\XAMPP\htdocs\erp\`. Nếu dùng patch, chạy từ `D:\XAMPP\htdocs\erp\`:

```powershell
# Chọn một cách: git apply HOẶC Overlay ZIP
cd D:\XAMPP\htdocs\erp
git apply --check .\M3.4D.patch
git apply .\M3.4D.patch
```

**Không áp dụng patch và ZIP cùng lúc.** Bản nâng cấp M3.4D không xóa hoặc đổi tên file runtime.

## 3. Quy trình kiểm thử đơn giản, không cấu hình clone thủ công

Từ `D:\XAMPP\htdocs\erp\backend`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN DB-READ
.\tools\test-unit.ps1 RUN INTEGRATION
```

- `CHECK`: chỉ kiểm kê source/test/OpenAPI; **không DB/HTTP**.
- `RUN`: chạy **48/48** pure/fixture regressions **không DB**, tiếp đó chạy 6 M3.4D source acceptance gates, xuất report offline.
- `RUN DB-READ`: 7 lệnh kiểm tra persisted PBAC, State, Shield, Workflow **chỉ đọc**, dùng `.env` hiện tại; không phải live-baseline parity.
- `RUN INTEGRATION`: chỉ chế độ này tạo MariaDB sandbox mới, chạy 29 mutation và negative/security probes rồi M3.4D tự lấy **đúng report của lệnh vừa chạy** (không dùng latest đoán mò), so khớp fingerprint runtime, và xuất final acceptance JSON. **Không ghi vào `erp` hay `erp_test`**; schema nguồn chỉ được đọc cấu trúc. Không dùng Database production làm target.
- Có thể chạy ` .\tools\test-unit.ps1 RUN ACCEPTANCE ` để chỉ kiểm các source/golden-master gate của M3.4D mà không chạm DB.

Report M3.4D nằm tại `backend/writable/m34d/reports/m34d_YYYYMMDD_HHMMSS_<random>.json`. Nếu một gate deterministic FAIL, command trả mã lỗi nonzero. `NOT_TESTED` của baseline live không bị giấu và **không làm offline gate giả vờ FAIL**, nhưng production cutover vẫn `BLOCKED`.

Trạng thái thành công có chủ đích:

- `SOURCE_ACCEPTED_INTEGRATION_NOT_SUPPLIED` khi static audit PASS nhưng chưa gắn bằng chứng HTTP của phiên bản mới.
- `LOCAL_INTEGRATION_ACCEPTED_LIVE_PARITY_OPEN` khi static audit + bằng chứng MariaDB sandbox được chấp nhận. **Không phải** `FULL_BASELINE_PARITY_PASS`.

## 4. Chi tiết kiểm thử mới

- `m34d_contract_pure.php`: 15 assertion (đúng 70/150, 29 mutations, routes/tham chiếu SHA, reject route mới/sai method/handler/bị comment hoặc duplicate, no autoroute).
- `m34d_architecture_pure.php`: 18 assertion (6 static gates, DAG từ token PHP, cấm edge Access -> Identity/Workflow, detect cycle, loại comment/string, pinned locale, 10 Resource State exception).
- `m34d_evidence_fixture.php`: 22 assertion cho **báo cáo giả lập hoàn toàn in-memory**; kiểm tra cả thiếu 1 success/CSRF/translation/Shield/rollback hoặc fingerprint mismatch đều bị chặn. Chúng **không phải** HTTP/MariaDB thật.
- Tổng 48 pure/fixture suites PASS offline ở môi trường đóng gói. Hai bộ Shield/Workflow transaction fixture cũ vẫn thuộc bộ 48.

**Các giới hạn không thể bỏ qua:** không có live-baseline side-effect diff, không có parity concurrency/isolation, chưa test mọi HTTP response của 70 operations. Trước khi bật mutation production, vẫn phải vượt qua các gate độc lập này và bảo đảm toàn bộ legacy parity. Có thể phát triển milestone M4 tiếp theo với mutation bị tắt trong khi thực hiện những cổng đối chiếu này; việc phát triển M4 không tự động đóng các gate M3.4D còn mở.

## 5. Tác động thay đổi

Chỉ thay đổi công cụ, test và tài liệu: `backend/tools/test-unit.ps1`, `backend/tools/test-unit-check.php`, `backend/tools/m34b_run.php`; thêm `backend/tools/m34d_accept.php`, `backend/tools/m34d/` và 3 file test mới. **Không có migration, API mới, sửa runtime `backend/app`, Frontend hoặc `backend_basline`.** Đường dẫn upload được giữ nguyên tại `backend/public/upload/`.
