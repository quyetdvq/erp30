# DinkERP — Backend API Routing Architecture Standard v1.0.0 (PROPOSED)

> 29/10/2026 · Phụ lục dự thảo cho Frontend & SQL Standards v1.1.0. Chỉ thay đổi tài liệu, KHÔNG di chuyển route hoặc thay đổi behavior cho đến khi được phê duyệt và có migration QA. Áp dụng chuẩn cho các module mới ngay sau khi phê duyệt; refactor P1 thực hiện theo giai đoạn.

## A. Phạm vi và kết luận kiến trúc

- **Một file Routes chính cho mỗi module nghiệp vụ (bounded context)**; tên file theo domain, không dùng milestone Mx/My/Mz, không tách GET và WRITE thành các file riêng.
- Có **một `app/Config/Routes.php` điều phối** phiên bản API, CORS/OPTIONS/health và include module theo thứ tự kiểm soát. Mỗi route chỉ được đăng ký từ đúng một module chủ sở hữu.
- Module route file đặt tập trung tại `backend/app/Config/Routes/ApiV1/<Domain>Routes.php`. Mỗi file chỉ đăng ký đường dẫn tương đối nằm trong `group('api/v1', ['filter' => 'cors'], ...)` hiện hữu; không tự khai báo prefix `api/v1`, group CORS hoặc đăng ký AutoRoute.
- Không gộp tất cả module vào một mega-file; không chia file theo sprint / phiên bản phát triển. Nếu một module thật sự lớn, có thể có các file con nội bộ theo subdomain nhưng chỉ **một entrypoint `...Routes.php`** và phải ghi rõ ownership, import order, gate.
- Đổi vị trí đăng ký route là **refactor tương thích**, không là cơ hội đổi URL, HTTP method, controller action, filter, PBAC, input/output hoặc OpenAPI.

## B. Cấu trúc mục tiêu

```text
backend/app/Config/
  Routes.php
  Routes/ApiV1/
    IdentityRoutes.php
    AccessRoutes.php
    SettingsRoutes.php
    NumberingRoutes.php
    WorkflowRoutes.php
    LocationsRoutes.php
    OrganizationsRoutes.php
    PeopleRoutes.php
    ContactsRoutes.php
    ProductsRoutes.php
    PricingRoutes.php
    ImportsRoutes.php
    CustomersRoutes.php
    NotificationsRoutes.php
```

**Ví dụ điều phối (minh họa, chưa phải patch runtime):**

```php
/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->group('api/v1', ['filter' => 'cors'], static function (\CodeIgniter\Router\RouteCollection $routes): void {
    // Giữ nguyên các OPTIONS và health routes đã phê duyệt.
    $routes->options('(:any)', [\App\Shared\Http\HealthController::class, 'options']);
    $routes->options('health', [\App\Shared\Http\HealthController::class, 'options']);
    $routes->get('health', [\App\Shared\Http\HealthController::class, 'index']);

    require __DIR__ . '/Routes/ApiV1/IdentityRoutes.php';
    require __DIR__ . '/Routes/ApiV1/AccessRoutes.php';
    // ... mỗi domain được include đúng một lần, theo manifest review.
    require __DIR__ . '/Routes/ApiV1/NotificationsRoutes.php';
});
```

**Ví dụ module:**

```php
<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('products', [\App\Modules\Product\Master\ProductReadController::class, 'listProducts']);
$routes->get('products/(:num)', [\App\Modules\Product\Master\ProductReadController::class, 'getProduct']);
$routes->post('products', [\App\Modules\Product\Master\ProductMutationController::class, 'createProduct']);
```

Minh họa trên không thay thế danh sách route thực tế: khi migrate phải đưa **đầy đủ** các endpoint read/write/lookup/media/UOM/combo/relationship sang file chủ sở hữu.

## C. Bản đồ chuyển đổi từ source v1.9.23

Qua source: `Routes.php` hiện định nghĩa trực tiếp route M1–M3/M2.1 và include **20 file phụ** `MxxRoutes.php`, `CUSRoutes.php`, `WorkflowAssigneeRoutes.php`. Không thể đổi tên cơ học, vì nhiều luồng read/write khác nguồn nhưng cùng owner.

| File mục tiêu | Các endpoint/nguồn hiện tại phải gom (không chỉ đổi tên file) |
|---|---|
| `IdentityRoutes.php` | Auth, Security Users, User permissions từ `Routes.php`; User Avatar từ `M63BRoutes.php` |
| `AccessRoutes.php` | Authorization Groups, Resources, Policies, Authorization Logs GET và mutations hiện nằm trong `Routes.php` |
| `SettingsRoutes.php` | Display Settings từ `M4Routes.php` |
| `NumberingRoutes.php` | Numbering Series từ `M4Routes.php` |
| `WorkflowRoutes.php` | Workflow States/Lifecycles từ `Routes.php`; Business Workflow từ `M4Routes.php`; assignee candidates từ `WorkflowAssigneeRoutes.php`; notification assignees từ `M4Routes.php` |
| `LocationsRoutes.php` | Provinces/Wards GET từ `Routes.php` + mutations từ `M5Routes.php` |
| `OrganizationsRoutes.php` | Organizations/tree/label resolve GET/POST từ `Routes.php` + mutations từ `M52Routes.php` |
| `PeopleRoutes.php` | Employees/Assignments/Departments/Positions GET từ `Routes.php` + mutations từ `M53Routes.php`, `M54Routes.php` |
| `ContactsRoutes.php` | Contact polymorphic endpoints từ `M55Routes.php` |
| `ProductsRoutes.php` | Product Groups/UOM (`M61`), Product Master (`M62A`,`M62B`), Product Media (`M63`), Product Lookup (`M64A`), UOM Conversions (`M71`), Combo (`M72`), Product detail/relations (`M74`) |
| `PricingRoutes.php` | Price Lists và items từ `M73Routes.php` |
| `ImportsRoutes.php` | Product import start, Import Jobs/detail/rows từ `M8Routes.php` (import-owned API dù có prefix `products`) |
| `CustomersRoutes.php` | Customer Core và Interactions từ `CUSRoutes.php` |
| `NotificationsRoutes.php` | M11 Settings, Inbox, Push, My Work, Operations, Config từ `M11Routes.php` |

Quy tắc ownership: **đường dẫn quyết định tài nguyên được truy cập; module chịu trách nhiệm nghiệp vụ quyết định file đăng ký**, không bắt buộc trùng namespace Controller. Riêng `business-workflow/.../notification-assignees` thuộc workflow API surface dù handler nằm trong Notifications. Không đăng ký lại endpoint này ở `NotificationsRoutes.php`.

## D. Quy tắc bắt buộc RT-01 đến RT-16

| ID | Quy tắc |
|---|---|
| RT-01 | Một entrypoint `Routes.php` ở `Config`, một module-route owner/file và một nơi đăng ký duy nhất cho mỗi `(HTTP method, path)` |
| RT-02 | Đặt tên file theo domain PascalCase (`ProductsRoutes.php`, `SuppliersRoutes.php`), không theo milestone (`M62BRoutes.php`) |
| RT-03 | Chỉ khai báo **explicit controller method** với `$routes->get/post/patch/put/delete`; không dùng AutoRoute, implicit resource(), environment-dependent auto discovery hoặc anonymous handler ngoài ngoại lệ được review |
| RT-04 | Route file nằm trong cùng group `api/v1`, kế thừa CORS; bảo toàn các `before/after` filters, CSRF, session/auth và `requestmeta` hiện hữu; route file không tự sửa global filters |
| RT-05 | Không đổi URL/method/action, pattern placeholder `(:num)`, `(:segment)`, đối số handler (kể cả action có `/$1`) hoặc response chỉ để gom file |
| RT-06 | Static/specific patterns phải không bị route động shadow; review thứ tự đối với `products/options` vs `products/(:num)`, `organizations/tree` vs `organizations/(:num)`, `notifications/summary` vs routes động |
| RT-07 | Mỗi endpoint phải có controller owner, resource/action PBAC, authoritative scope, write gate và OpenAPI operation tương ứng; vị trí file không cấp quyền |
| RT-08 | Không xuất hiện registration trùng `(method,path)`; không mở catch-all route hoặc expose Shield vendor web routes |
| RT-09 | Mỗi route file giữ danh sách endpoint rõ ràng theo nhóm resource; comments mô tả ownership/cross-domain exception, không lấy milestone làm danh mục chính |
| RT-10 | Module mới SUP/PUR/INV/SAL/FIN/DOC có route file riêng ngay từ endpoint đầu tiên; không mở `M12Routes.php`, `M13Routes.php`... |
| RT-11 | Khi module > khoảng 80 endpoint hoặc phát sinh bounded subdomains độc lập, review tách các subfiles phía sau đúng một entrypoint; không bắt buộc một file quá dài |
| RT-12 | Thay đổi route đòi hỏi đối chiếu **method + path + handler + filters** trên compiled CodeIgniter route table trước/sau và OpenAPI; không chỉ grep string |
| RT-13 | Bảo toàn danh sách 150 frozen operations/handler footprint P1 hiện được pin bởi `backend/tools/verify_routes.py`; chỉ đổi verifier sang đọc đường dẫn mới khi đạt phép so sánh với manifest hiện tại, không đổi checksum để lách test |
| RT-14 | Các source acceptance M4–M8, M11/CUS đang pin tên file `MxxRoutes.php` phải chuyển sang gate kiểm tra bằng **route ownership và route signature**, không xóa kiểm thử hành vi hoặc phép kiểm tra bảo mật |
| RT-15 | Kiểm thử cả duplicate/shadowed routes, handler tồn tại, 404/405, CORS preflight, CSRF, PBAC actor A/B và direct-ID; giữ negative tests |
| RT-16 | Migrate route là **change riêng**, không trộn thêm CRUD/SQL/PBAC/schema/feature mới. Không migration/seeder khi chỉ chuyển file Routes. |

Ngưỡng `~80` ở RT-11 là ngưỡng **review mã nguồn** (không phải giới hạn kỹ thuật CI4 hoặc một quy tắc cứng). Số module có thể tăng khi domain được tách theo nghiệp vụ, không ép mọi route vào đúng 14 file mãi mãi.

## E. Quy trình refactor legacy không gây downtime

1. **Freeze baseline** v1.9.23: lưu `php spark routes` và phép kiểm tra chi tiết `(method,path,controller,method,filters)`. Chốt tập route đã được review; không chấp nhận mất endpoint.
2. **Đưa ra manifest route ownership** từ cả `Routes.php` và 20 file phụ; xác định mỗi route thuộc đúng một domain, có OpenAPI / controller / PBAC.
3. **Thực hiện theo nhóm nhỏ:** (a) Identity/Access/Settings/Numbering/Workflow, (b) Locations/Organizations/People/Contacts, (c) Product/Pricing/Imports, (d) Customers/Notifications. Sau mỗi nhóm chạy source acceptance + compiled route diff.
4. **Đồng bộ tooling/tests**: `verify_routes.py` cần hỗ trợ route files theo module; kiểm tra lại 150 frozen operations và toàn bộ P2/M11 routes. Chuyển test về method/path/handler/filter/owner thay vì tên `MxxRoutes.php`.
5. **So sánh route signatures và shadowing** trước/sau. Không coi `php spark routes` PASS là đủ nếu duplicate route bị che mất hoặc dynamic pattern match khác.
6. **HTTP security/UAT**: CRUD (GET/POST/PATCH/PUT/DELETE), unauthorized, CSRF, CORS, Workflow và file uploads/Push; không thay DB.
7. **Release**: một Git patch độc lập có rollback; G2/G3/G5/G7 ghi rõ PASS/FAIL/NOT_RUN; chỉ bỏ file legacy sau khi route registry/verifier thành công.

Ví dụ capture thủ công trên DEV (không thay thế bộ normalized signature checker đề xuất):

```powershell
cd D:\XAMPP\htdocs\erp\backend
php spark routes | Out-File -Encoding utf8 .\writable\routes_before.txt
# Sau khi refactor và kiểm tra đầy đủ:
php spark routes | Out-File -Encoding utf8 .\writable\routes_after.txt
```

**Vì sao không đổi ngay:** `verify_routes.py` hiện cố định 17 tên file cho 150 frozen routes, và nhiều M4–M8/CUS/M11 source tests mở trực tiếp các file cũ. Di chuyển route mà chưa nâng cấp các gate này sẽ làm fail hoặc làm mất bằng chứng về bảo mật/compatibility. Không được gỡ gate chỉ để đổi tên file.

## F. Gate đưa vào chuẩn phát triển G0–G7

- **G2 (API Contract):** endpoint ownership + file đích + method/path + OpenAPI + filters + pattern precedence review.
- **G3 (Code/Unit):** explicit route, auto-route OFF, no duplicate, compiled route signature + frozen 150 checksum + full source tests.
- **G5 (HTTP/PBAC):** 401/403/404/405, direct-ID, CORS/CSRF, actor A/B, body + metadata parity; không chỉ pure tests.
- **G7 (Release):** route diff before/after, ownership manifest, patch replay, rollback, không tăng giảm endpoint ngoài thay đổi đã duyệt.

## G. Quyết định cần phê duyệt

**Đề xuất chấp thuận:** mô hình `Config/Routes/ApiV1/<Domain>Routes.php` + `Routes.php` điều phối; áp dụng mặc định cho mọi module P2 mới; refactor route P1 **riêng một patch** và chỉ thực hiện sau khi có baseline snapshot + nâng cấp route verifier. Phụ lục này đang ở trạng thái **PROPOSED**, không phải khẳng định code P1 đã được chuyển đổi.
