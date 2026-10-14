# 08 — Runtime, Environment, Build, Migration & Recovery

**Trạng thái:** runbook **dựa trên code/config đang có**; không phải bản runbook production đã được chạy thử hoặc được vận hành phê duyệt. Không đưa thông tin mật vào docs hoặc commit `.env`.

## 1. Prerequisites từ manifest

| Thành phần | Yêu cầu code | Nguồn |
|---|---|---|
| PHP | `^8.2`, các extension của CI4/PhpSpreadsheet tùy lock & môi trường | [`backend/composer.json`](../../backend/composer.json) |
| PHP frameworks | CodeIgniter `^4.7`, Shield `^1.4`, PhpSpreadsheet `^5.10` | [`composer.json`](../../backend/composer.json) |
| Database | MySQL/MariaDB compatible MySQLi driver | [`Database.php`](../../backend/app/Config/Database.php), migrations |
| Node | `^22.18.0 || >=24.12.0` | [`frontend/package.json`](../../frontend/package.json) |
| Package manager | pnpm `10.18.2` | [`frontend/package.json`](../../frontend/package.json) |
| Frontend | Vue 3, Vite 8, Vue Router, Pinia | [`frontend/package.json`](../../frontend/package.json) |

PHP extensions, DB engine version, secure cookies/proxy/hosting cần kiểm tra khi deploy; manifest không mô tả đầy đủ OS packages/infra.

## 2. Cấu hình môi trường đáng kiểm

| Variable/config | Ý nghĩa và ràng buộc trong code |
|---|---|
| `CI_ENVIRONMENT` | `production` kích hoạt điều kiện bảo mật/validation nghiêm ngặt |
| `app.baseURL` | **HTTPS bắt buộc trong production**; cấu hình mặc định trong `App.php` dùng HTTP và phải override khi deploy |
| `app.forceGlobalSecureRequests` | Bật HTTPS runtime gating trong Filters và secure headers/CSRF theo config |
| DB vars | Host/user/password/database phù hợp Config/Database; production cấm username root/blank password và `DBDebug=true` |
| `security.csrfEnabled` | Production không được tắt session CSRF theo `Security.php` |
| `cors.allowedOrigins` | Production phải khai báo explicit origins theo [`Cors.php`](../../backend/app/Config/Cors.php) |
| `VITE_API_BASE_URL` | Frontend API URL khi build (fallback hardcoded `https://api.monmin.net`) |
| `VITE_HMR_HOST`, `VITE_HMR_PROTOCOL`, `VITE_HMR_PORT` | Phục vụ HMR **development**, không nhất thiết đúng môi trường local |
| `import.max_file_bytes`, `import.max_rows` | Mặc định lần lượt 10 MiB và 5000, kiểm `upload_max_filesize` / `post_max_size` PHP |
| `WRITEPATH` | Writable directories, session/cache/log và `uploads/product-images` cần quyền ghi an toàn |

Nguồn: [`App.php`](../../backend/app/Config/App.php), [`Database.php`](../../backend/app/Config/Database.php), [`Security.php`](../../backend/app/Config/Security.php), [`Cors.php`](../../backend/app/Config/Cors.php), [`Import.php`](../../backend/app/Config/Import.php), [`api.ts`](../../frontend/src/services/api.ts), [`vite.config.ts`](../../frontend/vite.config.ts).

## 3. Thiết lập môi trường developer (tham khảo, **chưa thực thi**)

```sh
# Backend: từ root repository
cd backend
composer install
# Cấu hình environment / database trong .env riêng tư trước khi migrate
php spark migrate
# Seed theo dependency/phê duyệt, không chạy bừa trên DB có dữ liệu thật:
# php spark db:seed 'App\\Database\\Seeds\\ResourceActionSeeder'
# php spark serve

# Frontend: từ root repository
cd frontend
corepack enable
pnpm install --frozen-lockfile
pnpm run dev
```

Với CI4 Shield và CodeIgniter Settings, các migrations vendor/initialization cần được đưa vào kế hoạch triển khai thực tế theo docs dependency. **Không khẳng định** lệnh ở trên tự tạo đầy đủ mọi vendor table trong mọi deployment. Không chạy seed IAM trên production trước khi phân tích effects và baseline grants. Seeder trong repo: [`GroupRootSeeder.php`](../../backend/app/Database/Seeds/GroupRootSeeder.php), [`PrivilegedAuthorizationGroupSeeder.php`](../../backend/app/Database/Seeds/PrivilegedAuthorizationGroupSeeder.php), [`ResourceActionSeeder.php`](../../backend/app/Database/Seeds/ResourceActionSeeder.php), [`WorkflowStateSeeder.php`](../../backend/app/Database/Seeds/WorkflowStateSeeder.php).

## 4. Build/test commands được repo khai báo

```sh
# Backend (sau composer install + DB test setup)
cd backend
vendor/bin/phpunit
# Khi cần framework/bootstrap đã sẵn:
php spark migrate:status

# Frontend (sau pnpm install --frozen-lockfile)
cd frontend
pnpm run type-check
pnpm run lint
pnpm run test:unit -- --run
pnpm run openapi:lint
pnpm run openapi:generate:check
pnpm run build
pnpm run test:e2e
```

**Chú ý:** Một số test e2e được mock, một số `e2e/live` phụ thuộc API thật. Phải phân biệt hai loại khi đưa evidence; không dùng E2E mock để chứng minh phân quyền backend. PHPUnit test config hướng tới group database `tests`/`erp_test`; không được chạy trên production database.

## 5. Checklist phát hành an toàn — đề xuất trước migrate production

1. Khóa **source commit + schema migration target**, review `composer.lock`/`pnpm-lock.yaml` và backend/FE env; lấy bản sao dữ liệu để rehearsals, kiểm restore đã thử.
2. Chạy migrations 000001–000017 trên DB clone tương đương; review policy scope uniqueness (000012), trạng thái resource/policy (000014), Contact split (000015), length status (000016), product image metadata (000017). Migration **000015 có rollback safety guard**: không tự đảo chiều khi có quyền/lịch sử liên quan.
3. Kiểm soát phiên Shield, database least-privileged user, secure cookie, TLS/proxy headers, credentialed CORS exact allowed origins, CSRF and headers, log/monitoring.
4. Deploy backend với private writable upload path, bảo đảm **không public** images; deploy SPA build với history fallback và đúng `VITE_API_BASE_URL`.
5. Smoke health/login/CSRF, 401/403 handling, org-scope PBAC, security list, product pricing/lookup/import/image, display setting, locale, timestamps.
6. Chỉ chuyển traffic nếu smoke + DB migration + recovery evidence đạt; lưu deployment ID và người signoff trong release record.

## 6. Backup/restore tối thiểu

Phải backup **cùng một mốc nhất quán**: (a) ứng dụng DB bao gồm vendor Shield/settings tables; (b) `WRITEPATH/uploads/product-images`; (c) application env/secrets qua secret manager; (d) code artifact/lockfiles/schema migration status. Quy trình restore có thể cần bảo đảm không để DB trỏ tới ảnh đã mất hoặc private file thừa. Không đưa passwords/session tokens vào bản ZIP docs; không chạy rollback migration permission nếu mất trace dữ liệu.

## 7. Rủi ro vận hành chưa được chứng minh giải quyết

Chưa có evidence về topology/worker, scheduled backup, blue-green, monitoring alerts, log retention, file-volume replication, transaction isolation và throughput. `ProductImportAuthorizationService::run()` chạy **synchronously trong HTTP request**, vì vậy file lớn có rủi ro request timeout; trước khi chuyển thành background worker phải thiết kế protocol, idempotency, state và authorization, không sửa chỉ để qua timeout.
