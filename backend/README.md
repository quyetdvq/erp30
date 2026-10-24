# DinkERP Backend v2 — source chính trong `erp/backend/`

- Đây là codebase PHP/CodeIgniter 4 + Shield **mới**, không sử dụng module kinh doanh v1.
- Backend v1 nằm trong thư mục anh em `../backend_basline/`; chỉ để tham chiếu.
- Frontend vẫn ở `../frontend/` và không thay đổi.
- `./public/upload/` (tính từ thư mục **backend**) là **duy nhất một root lưu hình và file upload**.
- Public URL ảnh **vẫn là** `/uploads/<year>/<filename>` theo hợp đồng Frontend v1.2.4,
  và ánh xạ đến `public/upload/<year>/<filename>` bằng rewrite nội bộ trong
  `public/.htaccess`. Không cần cấu hình Apache Alias, XAMPP VirtualHost riêng hay symlink.
- Các file upload khác sẽ có chính sách đường dẫn/phân quyền khi module sở hữu được xây;
  không tự động coi mọi file đặt trong thư mục public là tài liệu được phép chia sẻ.
- Chưa có endpoint upload mới ở M1/M2; `UploadPaths` chỉ chuẩn bị đường dẫn vật lý an toàn theo
  format image filenames và root lưu trữ. Không viết file hay thay DB trong lần chỉnh layout này.

## Kiểm thử tĩnh

```bash
php tests/m1_pure.php
python tools/verify_routes.py
python tools/verify_baseline.py --v1-root ..
python tools/verify_layout.py
```

Xem `../docs/backend-rewrite/IMPLEMENTATION_STATUS.md` và `DEPLOYMENT_AND_QA.md`.

## M2 — Internal read models

Added: `Modules/Locations`, `Modules/Organization`, `Modules/People`,
`Modules/Access/ScopeFactsReader`, `Shared/Read` plus CLI `m2:verify`.
M2 was originally private; M2.1 later adds separate PBAC-guarded read-only routes. No migrations.

```bash
php tests/m2_pure.php
php spark m2:verify  # after Composer and DB are installed; read-only
```

See `../docs/backend-rewrite/M2_IMPLEMENTATION_AND_QA.md`.


## M2.1 — Frontend GET routes (read-only)

M2 read repositories remain private. `Modules/ReadApi/ReadCatalogController` exposes
**15 GET + 1 POST lookup** operations already declared in the frozen OpenAPI; each
record goes through `Modules/Access/ReadViewGate` before filtering, count,
pagination, and tree projection. `POST /organizations/labels/resolve` is
read-only but still uses the existing session CSRF protocol.

No CRUD write routes are added. Access M3 still requires full runtime and
differential PBAC tests before considering production readiness.

```bash
php tests/m21_pure.php
python tools/verify_routes.py
php spark m21:verify      # requires configured DB; no writes
php spark routes          # check registered GET/POST lookup routes
```

See `../docs/backend-rewrite/M21_READ_ROUTES_QA.md` for acceptance criteria.


## M2.1.1 - route binding and catalog check hotfix

Replaces the 14 erroneous resource GET string handlers with explicit array handlers,
and adds a fail-safe PBAC resource/action lookup and `m211_routes_pure.php`.
Run `php tests/m211_routes_pure.php`, `php spark m21:verify` and `php spark routes`
after deploying the M2.1.1 overlay. See
`../docs/backend-rewrite/M21_READ_ROUTES_QA.md` for acceptance criteria.

## M3.1 — Canonical State and PBAC core (internal; no HTTP routes)

The 10 independent Resource types are in `Modules/Access/ResourceStateCatalog.php`.
`ResourceStateRepository` rejects business Resources missing Lifecycle and checks
all specific-State policies, including inactive ones. `PbacDecision` preserves
actor/assignment precedence and exposes matched policy IDs for future audit.
Run `php tests/m3_pure.php`, `php tests/m3_repository_pure.php`, and
`php tests/m3_lane_parity_pure.php`; on the local CI4+MariaDB runtime run
`php spark m3:audit` (read-only). **Do not enable strict M3 authorization on
M2.1.1 public reads before this audit and actor-equivalence testing.**
See `../docs/backend-rewrite/M3_1_IMPLEMENTATION_AND_QA.md`.
