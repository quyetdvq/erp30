# DinkERP v1.2.4 — Báo cáo khảo sát và thiết kế Backend Rewrite

**Phiên bản đề xuất:** Architecture Proposal 1.1 (State rules addendum 2026-10-23) — 23/10/2026  
**Nguồn khảo sát:** `erp_v1.2.4.zip`, giải nén độc lập tại `/mnt/data/dinkerp_analysis/erp30-main`  
**Trạng thái:** PHÂN TÍCH / CHỜ PHÊ DUYỆT. **Không có thay đổi nào đối với mã nguồn sản phẩm, database hoặc hệ thống triển khai.**

## A. Phạm vi, phương pháp và mức độ chắc chắn

1. Kiểm kê mã nguồn Frontend, Backend, tài liệu, OpenAPI, migration, route, test và tool trong gói ZIP; phân tích luồng Frontend service → API route → Backend controller/service → persistence.
2. Phân tích toàn bộ 150 operation OpenAPI và đối chiếu 151 route HTTP v1 của Backend; 150 operation khớp route tương ứng. Ngoài ra còn một route legacy `PUT /api/v1/users/{id}/avatar` không ghi trong hợp đồng.
3. Trích xuất 31 bảng do 18 migration ứng dụng tạo và 45 khai báo khóa ngoại; các bảng Shield cần khảo sát thực tế cùng môi trường cài đặt.
4. Phân tích rule có rủi ro cao qua code service, validation, PBAC decision engine, transaction, import, media; kiểm tra các source gate sẵn có.
5. Không có `backend/vendor`, `frontend/node_modules` hay bản sao database chạy thực tế trong ZIP. Vì vậy **chưa thể chứng minh** hành vi trên MariaDB, hiệu năng, permission parity hoặc tính tương đương từng response trên môi trường thật. Đây là ranh giới của kết quả trước khi xây dựng.

Đếm file được ghi nhận: `frontend/src`: 203 file nguồn Vue/TS (~47.882 dòng), `frontend/e2e`: 24 (~5.664 dòng), `backend/app`: 544 file (~52.619 dòng PHP), `backend/tests`: 158 (~24.602 dòng), `backend/tools`: 71 (~8.446 dòng). Các con số là kiểm kê nguồn, không phải đo độ phức tạp kiến trúc.

**Ưu tiên:** Frontend hiện tại bất biến 100%; bảo vệ dữ liệu; bảo vệ tính đúng của phân quyền; hành vi API giữ nguyên; sau đó mới tối giản và tối ưu code/SQL.

## B. Hiện trạng sản phẩm và danh mục nghiệp vụ

Stack xác nhận: Vue 3 + TypeScript + Vite + Pinia + Vue Router + Naive UI + Axios, SDK sinh từ OpenAPI. Backend PHP 8.2+, CodeIgniter 4 + Shield + CodeIgniter Settings v2.4.0 (theo composer.lock), PhpSpreadsheet và các repository truy cập MySQL/MariaDB. API thống nhất `/api/v1`.

| Backend mới | Màn hình/luồng đại diện | Chức năng chính | Bảng ứng dụng chính |
|---|---|---|---|
| Identity | Đăng nhập, Profile, Users | Phiên Shield, CSRF, account, password, avatar, active/status | `users` và bảng Shield |
| Access | Nhóm quyền, Resources, Policies, Authorization Logs | Base Permission, group membership, PBAC, resource/action, policy precedence, audit | `resources`, `resource_actions`, `groups`, `group_permissions`, `policies`, `policy_actions`, `authorization_logs` + Shield permission/group mapping |
| Locations | Provinces, Wards | CRUD, trạng thái, liên kết tỉnh–phường | `provinces`, `wards` |
| Organization | Organizations | Cây GROUP/COMPANY/BRANCH/PLANT/WAREHOUSE, quan hệ cha con, hiệu lực | `organizations` |
| People | Departments, Positions, Employees | Cây phòng ban, chức danh, nhân viên, manager, assignment thời gian | `departments`, `positions`, `employees`, `employee_assignments` |
| Contacts | Contact trong các màn hình tổ chức/nhân viên | Danh bạ owner EMPLOYEE/ORGANIZATION, trạng thái, phân quyền owner | `contacts` |
| Catalog | Products, Groups, UOMs, Import, Import Jobs | Sản phẩm chuẩn, parent/child, combo, chuyển đổi UOM, lookup bulk, hình ảnh, import CSV/XLSX | `product_groups`, `product_uoms`, `products`, `product_uom_conversions`, `product_combo_items`, `import_jobs`, `import_job_rows` |
| Pricing | Price Lists | Bảng giá theo COMPANY/GROUP, thời gian, ưu tiên, giá tính toán, phân bổ combo | `price_lists`, `product_price_list_items` |
| Workflow | Workflow States, Lifecycles | State/transition, lifecycle config, business workflow options/actions | `lifecycles`, `workflow_states`, `lifecycle_states`, `lifecycle_transitions` |
| Numbering | Auto Numbering | Series, pattern, scope, reset, counter, validate, sinh mã | `auto_numbering_series`, `auto_numbering_counters` |
| Settings | Display Settings | Runtime settings global/org, định nghĩa, overrides, reset, audit | `configuration_audit_events`, `settings` (CodeIgniter Settings package) |

Lưu ý: `Settings` runtime không đồng nghĩa mọi tùy chọn giao diện đều lưu ở Backend; một số display preferences/workspace state là client-only. Dashboard Frontend không đặt thêm nghĩa vụ có endpoint dashboard chuyên biệt. Danh mục 11 module là **quyết định thiết kế đề xuất**, không phải khẳng định Backend cũ chỉ có 11 module.

### B1. Màn hình Frontend được khóa nguyên trạng

Các tuyến router được bảo toàn: `/login`, `/`, `/profile`, `/profile/display-preferences`, `/system/display-settings`; 5 màn quản trị Security; 3 màn Workflow/Auto Numbering; Products/Groups/UOMs/Price Lists/Import/Import Jobs/Create/Edit; Organizations; Provinces/Wards; Departments/Positions/Employees; và trang 404. Không thay đổi tên route, guard, store, component, form, payload, SDK, hình ảnh, cấu hình CORS hoặc lựa chọn môi trường từ Frontend.

### B2. Bất biến nghiệp vụ phải chuyển nguyên vẹn

**Tổ chức:** đúng một root GROUP theo bộ luật hiện hành; `GROUP→COMPANY`, `COMPANY→BRANCH|PLANT|WAREHOUSE`, `BRANCH→PLANT|WAREHOUSE`; chống cycle, kiểm tra ancestry, trạng thái, hiệu lực, province–ward nhất quán. Thiết kế phải giữ nguyên phản hồi cho tổ chức ngoài scope.

**Nhân sự:** account user không đồng nhất với employee; employee có thể gắn user, manager; assignment xác định vị trí công tác (organization/dept/position, primary, từ-ngày/đến-ngày). Quy tắc trùng khoảng hiệu lực của assignment chính và eligibility PBAC được giữ nguyên.

**Phân quyền:** hai lớp. (1) Có `resource.action` trong **Base Permission** từ Shield và nhóm phân quyền đang ACTIVE, không có quyền ⇒ DENY. (2) Kiểm tra user/employee/assignment, resource status, domain SYSTEM/MASTER/ORGANIZATION, phạm vi tổ chức đi xuống, chính sách trên ancestors và state đáng tin cậy từ DB. Chính sách chạy các nhánh actor (USER/GROUP/ORGANIZATION) và assignment (DEPARTMENT/POSITION). `ABSOLUTE_DENY` bất kỳ nhánh nào ⇒ DENY toàn cục; với DENY thường, chỉ DENY nếu mọi nhánh đều DENY; ALLOW ưu tiên trong chính nhánh; không match có thể cho phép khi tất cả điều kiện bắt buộc đã qua. Không có super-admin bypass mặc định. Khi GET danh sách, tổng bản ghi, nhãn tra cứu và phân trang chỉ phản ánh dữ liệu được phép nhìn thấy. Không được bỏ qua phân quyền khi import hoặc batch lookup.

**Contacts:** contact owner là EMPLOYEE hoặc ORGANIZATION; tài nguyên authorization tương ứng `employee_contact` và `organization_contact`. Legacy resource chung `contact` đã bị chặn nhằm ngăn mở quyền ngoài ý muốn. Không cho chuyển chủ sở hữu contact tùy tiện.

**Workflow:** native `ACTIVE/INACTIVE` và trạng thái do lifecycle quản lý là hai khái niệm liên quan nhưng không đồng nhất. Transition hợp lệ theo lifecycle + action permission + trạng thái persisted, cập nhật nguyên tử; không tin `state_id` từ client thay vì record thật.

**M3-STATE-001 - Owner-approved Resource State classification (2026-10-23):**

Exactly ten independent Resource types do **not** use Lifecycles or Transitions: `authorization_group`, `authorization_log`, `display_setting`, `employee_assignment`, `import_job`, `lifecycle`, `policy`, `user`, `workflow_state`, `resource`. `authorization_log` and `display_setting` are **All States only**; the other eight use Native State. **Every other Resource uses assigned Lifecycles and authorized Transitions**, even if its table has a `status` column. A missing Lifecycle binding for any non-exception is a configuration defect (fail closed), not a new Native exception. Native State still requires normal Access/PBAC enforcement. See `docs/backend-rewrite/RESOURCE_STATE_AND_LIFECYCLE_RULES.md` for State vocabularies, policy semantics, baseline compatibility issues, and mandatory acceptance tests.


**Numbering:** pattern/series/counter có scope, kỳ reset, generation mode, timezone cấp COMPANY/GROUP/global trong CodeIgniter Settings (mặc định group `Asia/Ho_Chi_Minh` khi thích hợp); cấp số trong cùng transaction tạo/cập nhật entity, đúng thứ tự khóa, uniqueness và chống race; không lấy max(code)+1.

**Catalog:** giữ một bảng `products` với STANDARD/COMBO và quan hệ parent/child; không sáng tạo schema variant mới trong lần thay Backend. Ràng buộc nesting/cycle, trạng thái, serial management, reference protections, chuyển đổi UOM, combo component và quantity/decimal. Phân loại SIMPLE, PARENT, CHILD, COMBO trong logic ứng dụng phải cho kết quả như cũ.

**Pricing:** giá ưu tiên COMPANY → GROUP → fallback theo product/UOM; kiểm tra thời gian hiệu lực, priority, độ ưu tiên trong cùng bậc và tính giá combo/allocated leaf qua công thức/rounding có thể đối chiếu chính xác. Tránh float cho tiền và hệ số chuyển đổi.

**Import:** CSV/XLSX (mặc định tối đa 10 MiB và 5.000 hàng, có thể cấu hình qua môi trường); CREATE_ONLY, UPDATE_ONLY, UPSERT; ghi lỗi từng hàng, kết quả từng hàng/job, xử lý phụ thuộc giữa bản ghi và per-row atomicity. Dù tên là import job, service hiện tại thực thi đồng bộ trong request: **không đổi sang queue asynchronous trong V1 Rewrite** nếu chưa phê duyệt đổi contract.

**Media:** POST multipart `image` (avatar có PUT compatibility) và DELETE; không có GET PHP binary được frontend sử dụng. Chỉ JPG/PNG/WebP, product tối đa 3 MiB, avatar tối đa 2 MiB, giới hạn chiều ảnh 2048 và guard ảnh lớn 40 triệu pixel khi đọc; giữ tên và URL ảnh hợp lệ với `uploadImageUrl` hiện hành. Image trả về là URL tĩnh, không được tùy ý đổi thành `/media/{uuid}` vì Frontend hiện tại sẽ từ chối.

### B3. Các đường dẫn API/Frontend đáng chú ý

- 150/150 operation OpenAPI tìm thấy route backend; cộng một PUT avatar legacy = 151 route v1 được kiểm kê (không gồm OPTIONS).
- `frontend/src/services/authorization.ts` có hai hàm gọi `GET /api/v1/authorization/profile` và `POST /api/v1/authorization/check`, nhưng chúng không có route/OpenAPI và không được module khác import. Đánh dấu **orphan/dead code cần xác minh**; không biến chúng thành điều kiện của bản rewrite mặc định.
- `ConnectivityView.vue` gọi health nhưng không được khai báo trong router; vẫn phải giữ `/api/v1/health` vì có trong contract.
- `/api/v1/organizations/labels/resolve`, `/api/v1/product-uoms/labels/resolve` và các POST product lookup quan trọng vì chúng cấp dữ liệu cho selector/option, không chỉ là CRUD.
- Tên API path không nhất thiết là module owner, ví dụ numbering lồng dưới `resources`, import start lồng dưới `products`.

## C. Đánh giá Backend cũ: giữ nghiệp vụ, bỏ gánh nặng tổ chức

Code cũ đã có những bảo vệ hữu ích (PBAC read batch, transaction/counter, image checks, source test gates); đây không phải nguồn có thể loại bỏ mù quáng. Nhưng cấu trúc gồm các lớp compatibility, alias ở `app/Services`, một số adapter ở `app/Repositories`, nhiều `Modules/*/Application/Ports/Infrastructure/...` và Bootstrap factory, khiến việc tìm đường đi của một request trải qua nhiều lớp khi nghiệp vụ không yêu cầu. **Thống kê tĩnh:** 12 module root, 20 file Bootstrap, 27 thư mục `Ports`, 11 thư mục `Compatibility`, 30 file chứa `class_alias(...)`. Không phải mọi interface là thừa, nhưng bề mặt kiến trúc lớn hơn mức cần thiết cho ERP này.

| Nhận xét | Quyết định cho Backend mới |
|---|---|
| Nhiều facade Service/Bootstrap/Ports/Compatibility phân tán | Không sao chép; tập trung mỗi module vào 1–vài Service đúng nghiệp vụ; interface chỉ nơi thực sự cần thay thế/test |
| Một số query phân trang, PBAC phải tối ưu rất kỹ | Giữ đặc tính tối ưu SQL và security semantics, không chuyển thành eager `findAll` rồi lọc PHP |
| Controller hiện có route nhóm `FoundationOrganizationContact`/`AuthorizationWorkflow` quá rộng | Chia HTTP controller theo module nhưng giữ nguyên URL và HTTP method |
| Legacy alias duy trì chuyển đổi kiến trúc cũ | New application tree không cần alias/bridge khi chưa có consumer ngoài Frontend |
| `Import Job` danh sách có lối lọc bộ nhớ ở vài service | Đưa lọc/phân trang xuống SQL khi tương đương về PBAC và sort; đo và test parity |
| Nhiều tài liệu chuẩn kiến trúc từ phiên bản trước | Dùng như bằng chứng lịch sử, không lấy quy định “không rewrite” làm giới hạn bởi yêu cầu hiện tại là rewrite |

### C1. Rủi ro không được giải quyết chỉ bằng xóa layer

1. Chính sách PBAC sai khi thay đổi thứ tự ưu tiên → rò dữ liệu/thay đổi quyền.
2. Regress danh sách PBAC: lọc sau LIMIT hoặc đếm trước lọc → lộ tổng số và pagination sai.
3. Trùng counter khi nhập nhiều request hoặc khi import gọi Numbering.
4. Sai schema hash Shield, cookies và CSRF → user mất đăng nhập, mutation lỗi.
5. Sai rounding decimal/weight/combo, thay đổi giá mà không được phát hiện.
6. Ảnh bị 404 nếu đổi URL; hoặc mở cả writable webroot dẫn đến lộ private files.
7. Dữ liệu CONTACT hoặc POLICY cũ có điều kiện migration nhạy cảm; cần audit trên bản DB thực trước khi chạy.

## D. Kiến trúc mới đề xuất — Modular Monolith thực dụng

```text
┌──────────────────────────────────────────────────────────────────────┐
│                     FRONTEND v1.2.4 — GIỮ NGUYÊN                    │
│ Vue / Pinia / Router / Axios / Generated SDK                        │
└──────────────────────────────┬───────────────────────────────────────┘
                               │ same /api/v1 contract, cookie, CSRF
┌──────────────────────────────▼───────────────────────────────────────┐
│                           HTTP boundary                              │
│ Web server + CI4 routes + filters: CORS, session, CSRF, request ID   │
│ Normalized JSON success/error + validations                         │
└──────────────────────────────┬───────────────────────────────────────┘
                               │ thin controllers
┌──────────────────────────────▼───────────────────────────────────────┐
│                        MODULAR MONOLITH                              │
│                                                                      │
│ Identity   Access      Locations    Organization    People          │
│ Contacts   Catalog     Pricing      Workflow        Numbering       │
│ Settings                                                             │
│                                                                      │
│ Module HTTP → Service (transaction/rules) → SQL Query/Repository     │
│       └── shared Access.authorize(), AuthActor, API transport        │
└─────────────────────┬──────────────────────────────┬─────────────────┘
                      │                              │
      ┌───────────────▼────────────┐    ┌────────────▼────────────────┐
      │ MariaDB/MySQL              │    │ Image storage              │
      │ 31 app tables + Shield     │    │ private source directory  │
      │ real schema kept in V1     │    │ static /uploads alias only │
      └────────────────────────────┘    └─────────────────────────────┘
```

**Framework:** đề xuất PHP 8.2+ / CodeIgniter 4 / Shield / MariaDB trước tiên vì giữ hệ sinh thái triển khai, dữ liệu login/password và front-end contract mà không phải viết lại hạ tầng framework. Đây **không** phải tiếp tục refactor project CI4 cũ: tạo codebase mới, chỉ dùng framework/library chính thức và những business rule đã kiểm chứng. Có thể đánh giá framework khác nếu người sử dụng phê duyệt, nhưng rủi ro chuyển đổi sẽ lớn hơn.

**Số lớp mặc định:** Controller (HTTP), Service (nghiệp vụ), Repository/Query (SQL) **khi cần**. Các CRUD ngắn có thể chỉ cần Controller + Service + Model/Query. Không sinh 1 interface + port + adapter + DTO + manager cho mỗi bảng. API response mapper và validator chỉ tách khi tái sử dụng thực tế.

### D1. Cây thư mục đề xuất

```text
backend-v2/
├── composer.json
├── env.example
├── public/
│   └── index.php
├── app/
│   ├── Config/
│   │   ├── Routes.php                # /api/v1 route contract, chia theo module
│   │   ├── Filters.php
│   │   ├── Cors.php
│   │   ├── Security.php
│   │   └── Auth.php                  # Shield + cookie/session policy
│   ├── Modules/
│   │   ├── Identity/
│   │   │   ├── Http/AuthController.php
│   │   │   ├── Http/UserController.php
│   │   │   ├── AuthService.php
│   │   │   └── UserService.php
│   │   ├── Access/
│   │   │   ├── Http/GroupController.php
│   │   │   ├── Http/ResourceController.php
│   │   │   ├── Http/PolicyController.php
│   │   │   ├── PermissionService.php
│   │   │   ├── AuthorizationService.php  # PBAC duy nhất
│   │   │   ├── PolicyService.php
│   │   │   ├── AuthorizationQueries.php # batch/read-only context
│   │   │   └── DecisionRules.php
│   │   ├── Locations/
│   │   ├── Organization/
│   │   ├── People/
│   │   ├── Contacts/
│   │   ├── Catalog/
│   │   │   ├── Http/ProductController.php
│   │   │   ├── Http/ProductLookupController.php
│   │   │   ├── Http/ProductImageController.php
│   │   │   ├── Http/ProductImportController.php
│   │   │   ├── ProductService.php
│   │   │   ├── ProductQueries.php
│   │   │   ├── ComboService.php
│   │   │   ├── UomService.php
│   │   │   ├── Import/
│   │   │   │   ├── ImportService.php
│   │   │   │   ├── ImportRowWriter.php
│   │   │   │   └── ImportJobQueries.php
│   │   │   └── Media/ProductImageService.php
│   │   ├── Pricing/
│   │   ├── Workflow/
│   │   ├── Numbering/
│   │   └── Settings/
│   ├── Shared/
│   │   ├── Http/ApiResponder.php
│   │   ├── Http/ApiExceptionHandler.php
│   │   ├── Http/ListQuery.php
│   │   ├── Security/ActorContext.php
│   │   ├── Database/Transactions.php
│   │   ├── Decimal/Decimal.php
│   │   ├── Media/ImageStorage.php
│   │   ├── Import/FileReader.php
│   │   ├── Logging/RequestContext.php
│   │   └── Clock/Clock.php
│   └── Database/
│       └── Migrations/                 # only NEW forward migrations
├── tests/
│   ├── Contract/
│   ├── Feature/
│   ├── Security/
│   ├── Database/
│   └── Concurrency/
└── tools/
    └── verify-contract.php
```

Đây là **cấu trúc thiết kế**, không phải cây thư mục đã tạo. `Locations`, `Organization`, `People`, ... sẽ có `Http/` + Service + Query/Repository với tên file sát chức năng; không cần tạo placeholder trống hay bắt mọi module có cùng các tầng.

### D2. Quy tắc sở hữu và phụ thuộc

| Quy tắc | Cách thực hiện |
|---|---|
| Ghi dữ liệu | Mỗi bảng có đúng một module sở hữu mutation. Module khác không UPDATE/DELETE bảng đó trực tiếp. |
| Gọi logic module khác | Dùng **public service method cụ thể**, ví dụ `authorize`, `allocateNumber`, `resolvePrice`, `applyTransition`; không import repository của nhau để ghi. |
| Đọc dữ liệu liên module | Ưu tiên query/read model hẹp và được kiểm thử; cho phép projection read-only có định nghĩa ở nơi cần (ví dụ Access phải đọc scope nhân viên/tổ chức). |
| Không vòng lặp Service | Access không gọi lại People mutation; People gọi Access để authorize. Access dùng read-only scope projection không gọi service People. Tương tự Access đọc persisted workflow state nhưng không gọi Workflow mutation. |
| Transaction | Một service/use-case sở hữu **transaction ngoài cùng**, truyền cùng DB connection qua các service Numbering/Workflow/Repo; không nested transaction độc lập. |
| Event | Không dựng event bus cho CRUD nội bộ; nếu sau này cần event domain thật (audit tích hợp, notification), thêm khi xuất hiện nhu cầu. |
| Public API nội bộ | Chỉ expose các chức năng rõ ràng; các helper/SQL/validator là private theo module. |
| Domain resource | Resource key và mapping `resource.action` là contract có phiên bản, không sinh quyền theo tên class hoặc tên route. |

**Luồng ví dụ tạo sản phẩm:** `ProductController` → `ProductService.create()` → lấy persisted/actor context → `Access.authorize(product.create)` → mở transaction → kiểm tra group/UOM/parent/combo → `Numbering.allocate(..., sameConnection)` nếu mã cần sinh → ghi product/child theo rule → commit → Response normalizer. `Access` không khởi động một use-case ghi nhân sự trong quá trình kiểm tra quyền; lần đọc PBAC chỉ dùng snapshot read-only ngắn hạn, không cache qua yêu cầu hoặc sau mutation rights.

**Luồng ví dụ xem danh sách:** authorize resource view → SQL filter candidates theo query được allowlist → PBAC batch read context → đánh giá record-level view và tính total/pagination trên tập có quyền → trả `data/meta` khớp contract. Nếu không thể đưa toàn bộ policy logic xuống SQL an toàn, dùng chunk/scan có giới hạn, định nghĩa tiêu chí phân trang và total đúng; tuyệt đối không `LIMIT` trước rồi mới lọc một cách khiến trang thiếu/sai tổng.

### D3. Shared thật sự cần, không thành module nghiệp vụ thứ 12

- `ApiResponder`, validation/error map, locale/request ID, pagination/query parser: dùng chung vì Frontend phụ thuộc nghiêm ngặt.
- `ActorContext` chứa ID phiên, evaluation time, request ID, locale; không trở thành object toàn năng chứa mọi repository.
- `Decimal`, date/time abstraction để tính tiền/số lượng ổn định.
- Transaction helper mỏng, không thay trách nhiệm transaction của service.
- `ImageStorage` xử lý kiểm tra byte/MIME/resize/filename/path; quyết định quyền upload/delete và metadata DB ở Identity/Catalog.
- File import reader CSV/XLSX dùng chung nếu sau này có module import khác; nghiệp vụ import row vẫn thuộc Catalog.
- Không trộn PBAC vào thư viện Shared; **Access sở hữu mọi thuật toán authorization**.

## E. API compatibility không sửa Frontend

**Nguồn chuẩn:** bộ ba `(1) OpenAPI snapshot, (2) Backend route+serializer+error code hiện tại, (3) request thật từ Frontend và test E2E`. Khi có mâu thuẫn, ưu tiên hành vi đang được Frontend dùng, ghi rõ trường hợp lệch hợp đồng.

### E1. Bất biến HTTP/transport

1. Giữ `/api/v1`, method, URL, path parameter, query pagination, sort/filter/search và các POST bulk-resolve.
2. Giữ session Shield cookie + `withCredentials`; username/email login, logout, `auth/me`, profile, active/status, reset password, nhóm, base_permissions.
3. Giữ CSRF GET bootstrap, header `X-CSRF-TOKEN`, token rotation trong response 2xx và error 409/422; CORS preflight OPTIONS, origin allowlist có credentials.
4. Giữ success envelope `{ "data": ..., "meta": ... }`; list `{ page, page_size, total }`. Giữ error `{ "error": { "code", "message", "details" }, "meta": { "request_id" } }`; HTTP 204 body rỗng.
5. Giữ `X-Request-ID`, `Content-Language`, `Accept-Language`, error code đã làm UI branching; ID chuỗi, decimal chuỗi, status/action code đúng case/kiểu.
6. Giữ các khả năng xử lý: duplicate conflict 409, field validation 422, unauth 401, denied 403, invisible record 404 theo logic cũ; không tự động redirect HTTP login trang HTML.
7. Giữ image `POST /products/{id}/image`, `POST|PUT /users/{id}/avatar`, DELETE, response `image_url`/`avatar_url` và đường dẫn tĩnh hợp lệ với FE.
8. Generated SDK **không regenerate trong repo Frontend** khi triển khai V1; phía Backend cần pass contract hiện có. Nếu phát hiện contract hiện tại mơ hồ, đặt regression fixtures và ghi rõ quyết định.

### E2. Kiểm thử đối chiếu (Golden Master)

- Thu 150 operation fixtures theo nhiều vai trò/scope và dữ liệu đại diện: method/path/query/request/body/headers → status, response structure, value, side effect DB, error codes. Bao gồm route PUT avatar phụ.
- **Contract test** schema, nullability, ID strings, decimal strings, sort tie-break, pagination, bulk labels, locales.
- **Security test**: account inactive, employee absent/inactive, assignment expired/non-primary, scope branch/ancestor/sibling, resource inactive, base denied, NO_MATCH, ALLOW, DENY, ABSOLUTE_DENY, state-specific policy, 403/404, totals, hidden lookup, import bypass.
- **Concurrency test**: numbering, duplicate code, update conflict (avatar), primary assignment time-window, lifecycle transition, combo cycle, import UPSERT.
- **UI E2E**: nguyên bộ Playwright Frontend 1.2.4 không đổi; smoke từng route chính, profile, resources/permissions, list selector, product editing, import, upload/delete và session refresh.
- **Performance**: so query count, EXPLAIN, p50/p95 và memory trên cùng dataset; không chấp nhận regression không giải thích trong request list, PBAC, search, import.

**Chứng nhận “Frontend không thay đổi”:** chốt Git tree hash/diff của `frontend/` trước/sau; API parity + E2E test phải pass, không chỉ build được.

## F. Database và chuyển đổi dữ liệu

### F1. Phương án ưu tiên — schema compatibility first

Dùng schema DinkERP hiện có (31 bảng ứng dụng + các bảng Shield + bảng `settings` của CodeIgniter Settings, cùng bảng phiên nếu có) thông qua codebase mới. Định nghĩa DB fixture và schema baseline từ DB staging **không chạy lại ngẫu nhiên toàn bộ migration cũ** trên một database đang có dữ liệu. Tạo migration mới khi có thay đổi thực sự bắt buộc; ưu tiên migration chỉ-additive. `users.full_name`, `users.avatar_filename`, `products.image_filename/image_mime/image_size_bytes`, `resources.status`, `policies.status`, `product_groups.parent_id`, trạng thái VARCHAR(50) và khóa scope policy đã được migration bổ sung sau tạo bảng; bản snapshot schema cuối cùng phải bao gồm chúng.

**Lưu ý trọng yếu:** `app/Modules/Settings/Display/Infrastructure/CodeIgniterDisplaySettingsStorage.php` truy cập trực tiếp bảng `settings` của gói `codeigniter4/settings`, còn `businessTimezone` đọc cấu hình qua cùng store. Bảng này **không có trong 31 bảng do application migrations tạo**, nhưng chứa dữ liệu hoạt động cần giữ nguyên. Không được bỏ sót khi backup/clone schema.

**Cần kiểm toán DB thực trước bước triển khai:** version MariaDB/MySQL, charset/collation, SQL mode, engine, timezone, Index/FK/unique, `SHOW CREATE TABLE` cho 31 bảng + Shield + `settings` và bảng phiên nếu có, migrations đã apply, bản ghi orphan, duplicate business keys, key `normalized_state_id`, nullable semantics, sequences/autoincrements, kích thước bảng, query plan và binary assets trên disk. Các bảng/dữ liệu của Shield được bảo tồn ở mức hash/identity/remember token/session theo cách tương thích.

### F2. Phương án chuyển sang DB mới nếu cần

1. Backup nhất quán database và toàn bộ image uploads kèm manifest; xác nhận khôi phục được.
2. Đưa schema mới ở mức logic tương thích vào database staging; đồng bộ PK/FK/unique/default/collation và dữ liệu Shield + `settings` (display overrides, business timezone).
3. Chạy import idempotent preserve ID, kiểm tra counts/hashes/tổng/trạng thái/scope, foreign key, max-ID/auto_increment và thử login bằng tài khoản fixture an toàn.
4. Replay API parity, UI E2E, PBAC matrix, test race; không dual-write hai backend khi chưa có cơ chế chống mất dữ liệu hoàn chỉnh.
5. Chọn cửa sổ freeze write ngắn; backup delta, đồng bộ, đổi routing Backend tới new instance; quan sát canary, có rollback về Backend+DB snapshot nếu điều kiện an toàn cho phép.
6. Sau khi bảo đảm tương đương mới tối ưu chỉ mục/schema theo số liệu thực. Những migration có nguy cơ hợp nhất record, nhất là policy scope, bắt buộc backup + proof trước.

### F3. Image static URL mà không mở toàn bộ `/writable`

Đặt ảnh trong vùng storage không phải document root. Web server phục vụ **duy nhất** alias/prefix `/uploads/20xx/<validated-name>.jpg|png|webp` mapping tới thư mục ảnh đã xác minh, không alias `writable/` tổng quát. Tắt listing, chặn PHP execution và file extension khác, bảo vệ symlink/path traversal; response URL chỉ `/uploads/YYYY/YYYYMMDDproductNNNNNNN.jpg` (hoặc `avatar`), đúng regex FE. Nếu bản ghi/ảnh cũ đang dùng `/writable/uploads/...`, giai đoạn chuyển đổi phải xử lý alias/path tương thích hoặc cập nhật metadata được xác thực để chúng vẫn hiển thị. Cơ chế này tránh truy vấn SQL mỗi lần tải ảnh và không public toàn bộ writable; nhưng **ảnh qua static URL về bản chất là public khi biết URL**, không được coi là tài nguyên riêng tư có PBAC trên GET.

## G. Kế hoạch triển khai (chỉ thực hiện sau phê duyệt)

| Giai đoạn | Nội dung | Bằng chứng hoàn thành / Gate |
|---|---|---|
| **0 — Khóa baseline** | Gắn tag ZIP/commit, hash frontend, inventory 150+1, thu golden requests/responses, DB fixture và image manifest, risk log | Có bộ contract và audit dữ liệu được người dùng duyệt |
| **1 — Skeleton + shared HTTP** | Tạo repo `backend-v2` độc lập; CI4, Shield connection, cookie/CSRF/CORS, ApiResponder, errors, logging, DB connection | Auth/login/me/profile/logout smoke và contract envelope pass; không sửa FE |
| **2 — Access/PBAC core** | Base permissions, groups/resource/actions/policies, decision engine, visibility, policy logs; read-only Organization/People projections | PBAC decision matrix và 403/404/total parity pass. Ưu tiên xây trước CRUD nhiều module |
| **3 — Master & organization/people** | Locations, Organization, People, Contacts, assignment, hierarchy | CRUD+state+scope+date rules+E2E tương ứng pass |
| **4 — Workflow, Numbering, Settings** | Lifecycle, transitions, state management, numbering series/counters, display overrides/audit | Transaction & race numbering, state-specific PBAC, config runtime pass |
| **5 — Catalog nền** | Groups, UOM, product core, parent-child, lookup, image upload/delete | Product editor, list, selector, media URLs/cleanup, domain guards pass |
| **6 — Combo + Pricing** | Conversion/combo graph, quote resolution, price list, combo allocation | Snapshot giá/tính decimal/rounding và combo parity pass |
| **7 — Product import** | CSV/XLSX, 3 modes, row/job handling, error and status parity, scalability | Template/row/error/job E2E, batch PBAC và import run pass |
| **8 — Full parity + cutover** | Chạy full API matrix, FE Playwright không đổi, PBAC/security/perf, DB checks, shadow/canary, chuyển hướng và rollback | Đạt toàn bộ tiêu chí nghiệm thu; sign-off rồi mới cutover |

Việc thiết kế và phát triển một module không cho phép bỏ qua kiểm thử liên module. Mỗi gate phải có input/output và DB diff; không coi danh sách endpoint là tiêu chí duy nhất.

## H. Tiêu chí phê duyệt và nghiệm thu

1. **Frontend:** không thay file Frontend (Git diff rỗng), màn/route/store và lookups chạy đúng.
2. **API:** 150/150 operation hợp đồng và route PUT legacy được đối chiếu; đúng shape, types, status và lỗi.
3. **Quyền:** ma trận PBAC và list visibility nhất quán, không đọc/ghi trái quyền, không leak qua count/labels/import.
4. **Dữ liệu:** đầy đủ 31 bảng ứng dụng + bảng Shield + bảng `settings` và session store có thật, integrity checks, user identities, avatar/product images không mất, không tạo lại ID.
5. **Nghiệp vụ:** combo/UOM/pricing/numbering/workflow/assignment/import đảm bảo invariant và sai số decimal trong contract.
6. **Ổn định:** transaction, race, rollback, concurrent mutations, logged-in sessions/CORS/CSRF đều được kiểm thử.
7. **Hiệu năng:** PBAC list, product select, import, pricing có benchmark trước/sau cùng dataset; tối ưu SQL theo EXPLAIN với performance budget do baseline quyết định.
8. **Dễ bảo trì:** 11 module chủ sở hữu rõ, 1 write owner/table, không vòng phụ thuộc service, không tầng trung gian chỉ để trang trí, test có thể chạy độc lập.
9. **An toàn triển khai:** có backup, rehearsal, rollback và lựa chọn cắt chuyển.

## I. Kết quả kiểm thử thực hiện khi khảo sát

- `php -l` cho **532 PHP file thuộc backend/app: không gặp lỗi cú pháp**.
- `php backend/tools/test-media-static-only.php`: PASS. Kiểm tra không có read API image binary, Frontend dùng URL tĩnh và avatar POST multipart.
- `php backend/tools/test-media-yearly-source.php`: source gate PASS; **GD processing NOT_RUN** do PHP GD extension không sẵn.
- `php backend/tools/test-identity-boundary.php`: PASS; static/smoke, không có DB.
- `php backend/tools/test-be-reorg-014l-employee-scope-read.php`: PASS; DB/Shield/HTTP NOT_RUN.
- `php backend/tools/test-architecture-conformance.php`: 19 static fixture scenarios PASS; không chạy DB/vendor.
- Không thể kết luận runtime PHP/HTTP/DB parity vì thiếu dependencies và dữ liệu thật.

## J. Các quyết định cần thống nhất trước khi viết Backend

| Mã | Quyết định đề xuất | Vì sao |
|---|---|---|
| ADR-01 | New codebase PHP/CI4 + Shield, tuyệt đối không copy kiến trúc Module/Port cũ | Phân biệt rewrite với refactor, giảm rủi ro session/DB |
| ADR-02 | 11 module business, Catalog sở hữu Import, Access sở hữu Resource Catalog | Tránh dàn trải layer và module quá nhỏ |
| ADR-03 | Giữ toàn bộ contract v1, kể cả legacy PUT avatar | Frontend và consumer tương thích |
| ADR-04 | Giữ schema logic và ID khi thay Backend lần đầu | Giảm rủi ro tổn thất dữ liệu |
| ADR-05 | PBAC hiện tại là baseline hành vi, không rút gọn RBAC | An toàn, bảo toàn logic phức tạp |
| ADR-06 | Static image `/uploads/` alias chính xác, chặn toàn bộ `/writable` công khai | FE không đổi, không SQL per image GET |
| ADR-07 | Product import vẫn synchronous v1 | Không đổi semantics HTTP |
| ADR-08 | New implementation chỉ bắt đầu sau baseline/golden tests và DB audit production/staging | Không mất khả năng so sánh và rollback |

**Khuyến nghị:** phê duyệt **nguyên tắc kiến trúc** trên trước; sau đó hoàn thiện baseline API fixtures và DB snapshot môi trường thực mới khóa chính thức từng service implementation. Không có mã nguồn Backend mới được viết trong giai đoạn phân tích này.

---

## Phụ lục và nguồn chứng cứ

Báo cáo chi tiết 150 API operations, 31 bảng và 45 FK nằm trong tệp `DinkERP_API_Database_Inventory_v1.2.4.md` cung cấp kèm. Đối chiếu giao diện tại `frontend/src/router/routes.ts`, `frontend/src/services/api.ts`, `frontend/src/services/uploadImageUrl.ts`; route tại `backend/app/Config/Routes.php`; PBAC tại `backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php`; response tại `backend/app/Infrastructure/Api/ApiTransport.php`; schema tại `backend/app/Database/Migrations/`.
