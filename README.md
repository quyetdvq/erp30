# DinkERP — Backend Rewrite workspace (M0/M1/M2)

Workspace theo bố cục đã chốt ngày 23/10/2026. **Frontend và Backend baseline giữ nguyên byte-for-byte**;
Backend viết lại nằm trong `backend/`, thay vì `backend-v2/`.

```text
erp/
├── backend_basline/       # Backend v1.2.4 nguyên bản (đúng chính tả tên thư mục đã chốt)
├── backend/               # Backend v2 mới, CI4, M2 read models đã bổ sung
│   ├── app/
│   ├── public/
│   │   ├── index.php
│   │   ├── .htaccess
│   │   └── upload/        # TẤT CẢ hình ảnh / file upload về sau
│   ├── tests/
│   └── tools/
├── frontend/              # Không sửa
├── contracts/             # OpenAPI hiện tại, không sửa
├── docs/backend-rewrite/  # Kế hoạch, trạng thái, baseline, hướng dẫn
└── tools/                 # Công cụ từ repo cũ, giữ nguyên
```

**`./public/upload/` là đường dẫn tương đối với thư mục `erp/backend/`, nên đường dẫn thực là
`erp/backend/public/upload/`.** Khi được phục vụ qua document root `backend/public`, API vẫn trả
URL `/uploads/YYYY/YYYYMMDDproduct.......jpg` hoặc `/uploads/YYYY/YYYYMMDDavatar.......jpg`
cho Frontend v1.2.4; `.htaccess` trong `backend/public/` ánh xạ nội bộ sang `upload/`.
Không cần thêm Apache Alias hoặc thay VirtualHost/XAMPP, miễn là host hiện tại đã đưa API
vào `backend/public` và hỗ trợ rewrite chuẩn của CI4.

Không tự động đưa file trong `backend_basline/writable/uploads` vào kho mới. Việc di chuyển
file thực tế phải backup, kiểm kê tên/đường dẫn, copy/kiểm thử và đối chiếu metadata trước.
Trong gói `erp_v1.2.4.zip` không chứa ảnh người dùng thực tế.

## Chạy kiểm tra không cần Composer/DB

Chạy từ root `erp/`:

```bash
php backend/tests/m1_pure.php
python backend/tools/verify_routes.py
python backend/tools/verify_baseline.py --v1-root .
python backend/tools/verify_layout.py
```

M1 đã được người dùng xác nhận kiểm thử login/me/session/logout/profile/CSRF thành công tại máy của họ. M2 mới bổ sung read models nội bộ, **không thay đổi 6 HTTP API đang chạy** và chưa đủ 150 operation để thay toàn bộ Backend cũ.
Không chạy migration/schema reset, và chỉ nối DB bản sao cho runtime smoke tests.
Hướng dẫn: [docs/backend-rewrite/DEPLOYMENT_AND_QA.md](docs/backend-rewrite/DEPLOYMENT_AND_QA.md).

## M2 — Read models (no public routes)

M2 hiện có `Locations`, `Organization`, `People`, `Access/ScopeFactsReader` và command kiểm tra DB chỉ đọc.
Không có migrations/schema changes. Chạy `php backend/tests/m2_pure.php` và
`cd backend && php spark m2:verify` sau khi đã cài CI4/Shield và kết nối DB clone.
Tài liệu: [docs/backend-rewrite/M2_IMPLEMENTATION_AND_QA.md](docs/backend-rewrite/M2_IMPLEMENTATION_AND_QA.md).

## Milestone M3.3B1 — Group / Resource mutations (lịch sử)

M3.3A đã đăng ký 19 Access GET. M3.3B1 đăng ký thêm **12 HTTP mutation operations** của Authorization Groups và Resources/Resource Actions theo OpenAPI baseline v1.2.4 (**53 non-OPTIONS operations** hiện tại).

**Quan trọng:** `DINKERP_M33B_WRITE_MODE=off` là mặc định. Đừng kích hoạt ghi trên cơ sở dữ liệu production. Khi chưa bật, route ghi tồn tại nhưng trả 403, không thay đổi SQL. Chỉ thử `enabled` trên **Database clone**, sau khi chạy kiểm tra và đối chiếu HTTP/transaction với baseline.

```powershell
php backend\tests\m33b_rules_pure.php
php backend\tests\m33b_transaction_fixture.php
php backend\tests\m33b_runtime_pure.php
php backend\tests\m33b_contract_pure.php
cd backend
php spark m33b:verify
php spark routes
```

Chi tiết: [M3.3B1 — Implementation & QA](docs/backend-rewrite/M3_3B1_MUTATION_IMPLEMENTATION_QA.md).

M3.3B1 là đợt đầu của M3.3B; các đợt sau được phát triển độc lập. M3.3B4 đã bổ sung Workflow/Lifecycle writes nhưng vẫn khóa mặc định.


## Milestone mới nhất: M3.3B4 — Workflow State / Lifecycle mutations

- Đã đăng ký thêm 8 mutation operation, tổng cộng **70 non-OPTIONS** API.
- Hai URL PUT cấu hình Lifecycle đều **bổ sung (append)** States/Transitions theo baseline v1.2.4, không thay thế.
- Giữ `DINKERP_M33B_WRITE_MODE=off` và `DINKERP_M32_READ_MODE` ở chế độ đang nghiệm thu.
- Test chạy offline: `php backend/tests/m33b4_rules_pure.php`,
  `php backend/tests/m33b4_transaction_fixture.php`,
  `php backend/tests/m33b4_contract_pure.php`, `php backend/tests/m33b4_verify_pure.php`.
- DB readiness **chỉ đọc**: `cd backend && php spark m33b4:verify`.
- Chưa nghiệm thu HTTP mutation/rollback/locale parity trên MariaDB clone; không được bật ghi thật.

Tài liệu: [M3.3B4 Implementation & QA](docs/backend-rewrite/M3_3B4_WORKFLOW_LIFECYCLE_MUTATION_QA.md).

## M3.4A (2026-10-23) — Clone-only mutation HTTP parity tools

The offline-verified tools under `backend/tools/m34/` cover all **29** Access mutation operation contracts. They require independently marked disposable baseline/v2 clone databases and local loopback HTTP servers before any authenticated HTTP mutation probes. This delivery does **not** verify live MariaDB transactions or authorize enabling writes on the real deployment. See `docs/backend-rewrite/M3_4A_MUTATION_HTTP_CLONE_PARITY_QA.md`.

## M3.4C — Module Boundary Refactor (Identity / Access / Workflow)

Đã chuyển Security User (5 mutation + 2 GET) về **Identity** và Workflow State/Lifecycle
(8 mutation + 6 GET) về **Workflow**. Module **Access** giữ 16 mutation,
11 GET Groups/Resources/Policies/AuthorizationLogs cùng PBAC `UnifiedAccessService`.
HTTP verb/URI/handler method của 70 operations không đổi so với M3.4B.5.

Kiểm thử offline 45/45 bộ PASS trong môi trường nguồn. Để nghiệm thu MariaDB/HTTP
sau refactor, cần chạy lại `RUN DB-READ` và `RUN INTEGRATION` trên XAMPP;
kết quả M3.4B.5 trước refactor **không tự chuyển thành PASS M3.4C**.

Xem [M3.4C — Module Boundary Refactor & QA](docs/backend-rewrite/M3_4C_MODULE_BOUNDARY_REFACTOR_QA.md),
bao gồm hai cách cập nhật (Git patch hoặc ZIP Overlay + cleanup xác minh SHA-256).

## M3.4D — Final Regression & Architecture Acceptance (2026-10-24)

Sau M3.4C, bản này chỉ bổ sung **cổng nghiệm thu có báo cáo JSON**, không sửa runtime API,
Frontend hay `backend_basline`. Các thành phần Identity/Access/Workflow giữ đúng module owner.
Từ `erp/backend` chạy:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN DB-READ
.\tools\test-unit.ps1 RUN INTEGRATION
```

`RUN` thực hiện 48 offline suites và 6 static acceptance gates, trong đó toàn bộ 70
đường dẫn HTTP được so với 150 operations từ OpenAPI baseline v1.2.4 đã khóa SHA-256.
`RUN INTEGRATION` vẫn dùng sandbox MariaDB duy nhất (không phải production) và tự gắn
JSON của đúng lần chạy vào M3.4D, đối chiếu fingerprint mã nguồn runtime.
Có thể chạy `RUN ACCEPTANCE` để xem riêng báo cáo static, không cần Database.

M3.4D report: `backend/writable/m34d/reports/`. **Chưa có independent live baseline
HTTP/DB side-effect parity và concurrency/isolation parity**; `production_cutover=BLOCKED`
trong mọi trường hợp. Giữ `DINKERP_M33B_WRITE_MODE=off` ở production.

Chi tiết: [M3.4D Final Regression & Architecture Acceptance](docs/backend-rewrite/M3_4D_FINAL_REGRESSION_ARCHITECTURE_ACCEPTANCE_QA.md).

## M4 — Settings, Workflow Execution, Numbering (Backend v2)

M4 adds 14 explicit v1.2.4 operations (70 -> 84/150) in the owning Settings, Workflow and Numbering modules. The M3.4D.1 seventy-route footprint remains pinned. Use `backend/tools/test-unit.ps1 CHECK`, `RUN`, `RUN ACCEPTANCE` for source gates; `DB-READ` and `INTEGRATION` still cover the earlier M3 readiness / **29 M3 mutations only**. Real M4 HTTP and MariaDB rollback/concurrency parity are **NOT TESTED**; production write mode remains `off`. Details: `docs/backend-rewrite/M4_SETTINGS_WORKFLOW_NUMBERING_QA.md`.
