# DinkERP — Development checkpoint (26/10/2026)

**Hướng phát triển mới:** `backend/` là code CI4 chính để phát triển tính năng từ M11. `backend_baseline/` chỉ là tham chiếu lịch sử của giai đoạn rewrite. Các gate fingerprint, kiểm thử so sánh parity với baseline và yêu cầu giữ cố định 150 routes trước đây đã hoàn thành mục đích và **không còn là điều kiện bắt buộc** khi phát triển tính năng mới. Có thể sửa kiến trúc, API, PBAC và chức năng cũ khi cần; thay bằng kiểm thử dữ liệu, bảo mật, migration, giao dịch, API và UI đúng theo tính năng đang làm.

**Migration/Seeder port:** 18 migration + 4 seeder nghiệp vụ hiện nằm trong `backend/app/Database/`; có Seeder tổng, dependency helper và preflight chỉ đọc. **Chưa có thay đổi database thực tế**. Không chạy trực tiếp 18 migration trên DB ERP có bảng sẵn nếu chưa reconcile lịch sử. Xem [`backend/app/Database/README.md`](backend/app/Database/README.md) và [`docs/Notifications/M11_0_MIGRATION_SEEDER_PORT_DECISIONS_2026-10-26.md`](docs/Notifications/M11_0_MIGRATION_SEEDER_PORT_DECISIONS_2026-10-26.md).

Các phần README bên dưới là lịch sử milestone M0–M9, giữ làm tài liệu tham khảo, không phải policy áp dụng cho M11.

---

# DinkERP — Current Source Checkpoint (M9.3 candidate, 2026-10-25)

- Source base: `erp_v1.5.0.zip` + accepted **M9.1** Employee optimization and E2E correction + **M9.2** read-only QA. **Do not apply the stale M9.0 patch**; the M9.3 source fingerprint incorporates accepted M9.1 changes.
- `python backend/tools/m93_release_preflight.py` runs safe, no-DB source checks: 281 reviewed frontend files, frozen v1.2.4 OpenAPI, 150 API routes, backend baseline/upload layout and public templates.
- After `cd frontend && pnpm build`, run `python backend/tools/m93_release_preflight.py --require-build` from ERP root to check the built SPA artifact. No database mutation or HTTP calls occur.
- M8.7 functional PASS; M9.1 CLOSED/PASS; M9.2 CLOSED with explicit live-audit/concurrency **waivers**; M9.3 release source checks are **not** production authorization. See `docs/backend-rewrite/M9_3_RELEASE_READINESS.md`.

The historical milestone history below reflects older checkpoints; follow the M9.3 note above for current source status.

---

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

## M4.1 — Source-parity hardening (2026-10-24)

Incremental M4 Numbering/Workflow corrections plus a pinned baseline pattern differential suite; 54 offline suites and the unchanged 84/150 route acceptance pass. **No M4 HTTP/SQL parity is claimed**; production write mode stays `off`. See `docs/backend-rewrite/M4_1_OFFLINE_HARDENING_QA.md`.

## M4.2A — Separate local M4 HTTP/SQL integration (runner; unverified on MariaDB)

`RUN INTEGRATION` still tests M3's existing 29 operations. A NEW opt-in `cd backend && .\tools\test-unit.ps1 RUN M4-INTEGRATION` creates a nonce-owned disposable MariaDB schema and probes **14 M4 endpoints** using synthetic Shield/PBAC/Settings/Numbering/Workflow fixtures, HTTP sessions, CSRF, transaction snapshots and forced rollback triggers. This runner is offline/source-verified only in the author environment; it has **not** yet been executed on XAMPP MariaDB. The new offline inventory is **55 suites**. Both independent baseline HTTP parity and two-writer concurrency are OPEN; production write mode remains **off**. See `docs/backend-rewrite/M4_2A_LOCAL_HTTP_MARIADB_QA.md`.

## M4.2A.1 — Isolated M4 bootstrap hotfix (2026-10-24)

After M4.2A, apply `M4.2A.1-off-mode-bootstrap-fix.patch` to correct the M4-only
sandbox child paths (`writable/m42/run_<nonce>`) and allow write-mode `off` HTTP
bootstrap **only inside the M4 nonce-attested router**. M3.4B's existing
`enabled`-only router and production write defaults are unchanged. Source/offline
regression 55/55 PASS, M4 safety assertions 41 PASS; local HTTP/MariaDB still
requires operator verification with `RUN M4-INTEGRATION`. See the M4.2A QA doc.

## M4.2B — Opt-in two-writer Numbering config race (2026-10-24)

After applying M4.2A.1, `cd backend && .\tools\test-unit.ps1 RUN M4-CONCURRENCY` runs a **separate** nonce-owned local MariaDB sandbox test with two authenticated, independent HTTP servers/writers contending on the same resource row. Pass requires two observed InnoDB lock-wait connections before releasing the parent lock, then one HTTP `201`, one HTTP `409 NUM_SERIES_DUPLICATE`, and exactly one persisted active/default series with no counter allocation. Offline/source validated; the operator subsequently reported XAMPP `PASS_LOCAL_M4_TWO_WRITER` on 2026-10-24 (`m42b_20261024_031653_ddb00959.json`), with 56/56 prior offline suites. This tests *configuration*, not number issuance. Baseline live parity and production write remain **BLOCKED**. See `docs/backend-rewrite/M4_2B_TWO_WRITER_CONFIG_RACE_QA.md`.

### M4.2C — paired baseline HTTP clone parity (opt-in)

With M4.2A.1 local M4 HTTP and M4.2B two-writer *configuration* checks separately completed, `php backend/tools/m42c_parity.php inventory` verifies the immutable 14-operation M4 baseline footprint without database access. The `preflight`, `negative` and `read` subcommands require **two manually marked, same-snapshot disposable DB clones and two independent operator-hosted local HTTP servers**. No successful HTTP mutations are sent; 8 read/dry-run and 6 denial-route comparisons are available. See `docs/backend-rewrite/M4_2C_PAIRED_BASELINE_READ_DENIAL_QA.md` for setup, explicit confirmation and the `ready=false` scenario template. This does **not** prove authorized write parity, numbering issuance, or production readiness.

### M5.1 - Protected Locations CRUD (first M5 slice, DEV)

M5.1 adds **six baseline Province/Ward mutation routes** (`POST`, `PATCH`, `POST .../actions/set-state` for each) behind M3 PBAC + default-off mutation gate, reaching **90/150** installed routes. Province/Ward M2 GETs stay unchanged. The M5 roadmap also includes **Organization, People and Contacts**, which are **not yet implemented** by this slice. No Frontend, baseline, Apache or DB migration change. `backend/tools/m5_accept.php` and the source/offline suite are acceptance-only; **DEV HTTP/MariaDB, successful-write parity and record permissions are not yet evidenced**. See `docs/backend-rewrite/M5_1_PROTECTED_LOCATIONS_QA.md`.

### M5.3 - Employee / Employee Assignment Protected CRUD (DEV)

Six pinned API-020 write operations extend the backend to **99/150** routes. Read-only Employee and Assignment endpoints remain unchanged. This source/offline slice checks Employee Lifecycle State, manager DAG cycles, independent Native Assignment State, inclusive effective dates, primary overlap, PBAC and transactional rollback boundaries. DEV HTTP/SQL and record-specific PBAC are **not yet verified**. See `docs/backend-rewrite/M5_3_PROTECTED_PEOPLE_QA.md`.

## M5.4.1 — Module ownership consolidation (Backend Rewrite)

The M5.1–M5.4 write implementations now live under their existing business
owners (`Organization/Mutation`, `People/Mutation`, `People/Structure/Mutation`),
not under separate `*Management` root modules. The M2.1 multi-domain read
compatibility adapter is now `app/Controllers/Api/ReadCatalogController.php`.
Access consumes narrow read-only facts through its own ports and a Bootstrap
wiring adapter to prevent `Access ↔ Organization/People` module cycles.

This is a **structure-only** change: 105/150 routes; no migration or frontend
change. See `docs/backend-rewrite/M5_4_1_MODULE_OWNERSHIP_QA.md` and the
`MODULE.md` files under Access, Organization, People and Locations for scope
and explicit compatibility exceptions.

## Trạng thái mới nhất: M6.3B.2 + QA Consolidation (2026-10-24)

Các mục M0-M5 ở trên là **lịch sử từng milestone**, không phải trạng thái nghiệm thu mới nhất.
Theo snapshot ERP v1.4.0, backend đã khai báo **129/150 HTTP operations** trong OpenAPI v1.2.4.
Kiểm thử offline và source acceptance PASS, nhưng **chưa đủ bằng chứng để production cutover**.

Các công cụ QA trong lần cập nhật này kiểm tra SHA-256 Frontend (277 file), Backend baseline
(806 file), OpenAPI (2 file), 129 route/handler và 107 pure/fixture suites. Từ root ERP:

```powershell
python backend\tools\verify_baseline.py --v1-root .
python backend\tools\verify_layout.py
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

**M6.3B.2:** Không tái sử dụng user ID `3` làm fixture nếu `avatar_filename` không rỗng.
Lệnh `php spark m63b1:db-audit 3 EMPTY` dừng là cơ chế bảo vệ dữ liệu, không phải lỗi
cần sửa bằng cách xóa avatar. Chỉ sử dụng user DEV riêng và kiểm tra database trước
khi thử ghi. Không tự động chạy `--apply`, reset DB hoặc thay đổi dữ liệu thật.

Xem [M6.3B.2 Admin Delegation Avatar QA](docs/backend-rewrite/M6_3B_2_ADMIN_DELEGATION_AVATAR_QA.md),
[QA Consolidation](docs/backend-rewrite/M6_QA_CONSOLIDATION_2026-10-24.md),
[Implementation Status](docs/backend-rewrite/IMPLEMENTATION_STATUS.md) và
[Next Work](docs/backend-rewrite/NEXT_WORK.md).
