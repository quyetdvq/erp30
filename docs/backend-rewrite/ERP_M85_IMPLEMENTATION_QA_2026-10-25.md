# M8.5 — Shared Authorization SQL Scope & Product List/Detail Optimization

**Ngày:** 25/10/2026
**Nền cài đặt:** M8, 150/150 OpenAPI v1.2.4 operations
**Chuẩn bắt buộc:** `ERP_AUTHORIZATION_SQL_SCOPE_STANDARD_v1.0.md` (tài liệu chuẩn đã bàn giao riêng)
**Trạng thái:** SOURCE ACCEPTED / HTTP–MariaDB & đa công ty chưa nghiệm thu / PRODUCTION BLOCKED.

## 1. Phạm vi đã triển khai

| Thành phần | Trạng thái | Ghi chú |
| --- | --- | --- |
| `Access/Query/ResourceScopeProvider` | Đã tạo | Mỗi module sở hữu provider riêng; Access không phụ thuộc Product/Pricing. |
| `Access/Query/AuthorizationSqlScopeCompiler` | Đã tạo | Entry point fail-closed, chỉ chấp nhận resource/domain được review. |
| `Access/Query/AuthorizedSqlScope` | Đã tạo | MASTER State predicate; ORGANIZATION `(organization_id,State)` predicate, bind value. |
| `Product/Master/ProductScopeProvider` | Đã tích hợp | `product`/MASTER, dùng canonical `viewableMasterLifecycleStatuses('product')`. |
| `Pricing/PriceList/PriceListScopeProvider` | Sẵn sàng thử nghiệm, chưa chuyển runtime | Đọc tối đa 512 Organization IDs từ catalog có thẩm quyền, PBAC theo từng tổ chức/State. Chưa thay `PriceListService::authorizedBuckets()`. |
| `Access/Query/EffectiveActionHintCache` | Đã tích hợp Product | Cache trong **một instance/request**, chỉ nhóm Product MASTER theo canonical State; không áp dụng State-only cho multi-company. |
| `ProductReadSql` | Đã tích hợp | SQL PBAC trước COUNT, tìm kiếm, filter và LIMIT/OFFSET; sort theo whitelist. |
| `ProductReadRules::describeJoined` | Đã tích hợp | DTO từ Product page + parent JOIN + child EXISTS, không đọc full catalog. |
| `ProductReadService::{list,show}` | Đã thay thế | COUNT/page/detail trong REPEATABLE READ; bounded `canView()` cross-check; HTTP response shape cũ. Chi tiết gọi trong Product Master transaction **mượn transaction của caller**, không commit/rollback. |
| Inventory read-scope | Đã lập | `php backend/tools/m85_scope_audit.php` chạy source-only, không DB. |

**Không thay đổi** URL, endpoint count, OpenAPI, schema database, mutation controllers, frontend, API output `data[].allowed_actions`, trạng thái QA miễn trước đây.

## 2. Trình tự GET /api/v1/products?page=1&page_size=15&sort=-product_id

1. CI4 Shield xác thực actor; `ProductReadSql::normalize` xác minh query (whitelist, giới hạn 200 dòng/trang).
2. Mở một transaction đọc REPEATABLE READ và tạo `UnifiedAccessService` từ snapshot actor trong transaction.
3. Base `product.view` phải tồn tại; Product provider tính các canonical State được PBAC `VIEW` (kể cả các lane ALLOW, DENY, ABSOLUTE_DENY).
4. `AuthorizationSqlScopeCompiler` tạo SQL predicate `UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?,...)` với **bind**, không inline State.
5. SQL `COUNT(*)` sau scope và filters; một truy vấn riêng `ORDER BY p.id DESC LIMIT 15 OFFSET 0` với **cùng scope và snapshot**. Không còn `allRows()` hoặc `array_slice()` toàn catalog trong PHP.
6. Dữ liệu trang được bổ sung `LEFT JOIN` Product cha và `EXISTS` Product con; Product cha **được PBAC-scope riêng** khi dùng tên để trình bày. Nếu Product con được VIEW nhưng cha không được VIEW, trả riêng tên con, không tiết lộ tên cha qua `full_name` hay điều kiện tìm kiếm.
7. Sau khi lấy trang, kiểm tra `canView('product', $row)` tối đa page_size lần. **Nếu khác SQL scope, lỗi toàn response**; không post-filter silently khiến total/page sai.
8. DTO gồm Product fields cũ, `role`, `full_name`, `selectable`, `leaf_transactionable`, `image_available`, `image_url`, `product_code`. `allowed_actions` vẫn được trả theo row.
9. `allowed_actions` chỉ tính cho các State khác nhau trên trang, được cache request-local; mutation endpoint luôn kiểm lại quyền trên row hiện hành.

### Lưu ý tương thích

- `sort=product_id/-product_id` map về PK `id` như trước. Default sort `id ASC` (theo helper cũ); filter và q vẫn được hỗ trợ.
- Việc so khớp tiếng Việt, Unicode/collation và sort các chuỗi có thể khác với `mb_strtolower`/`strcmp` trước đây. **Phải kiểm thử HTTP parity trên dữ liệu thật**, đặc biệt `q`, `full_name`, `product_code` dạng số, dấu tiếng Việt, NULL và sort nhiều cột. Chưa tự khẳng định parity hoàn toàn.
- `UPPER(TRIM(status))` bảo toàn semantics chuẩn hóa State của `ResourceStateValidator`, nhưng có thể giảm hiệu quả index. Không thêm index/đổi collation khi chưa có `EXPLAIN`.
- PARENT/CHILD/COMBO bị dữ liệu sai cấu trúc chỉ bị phát hiện khi hàng liên quan được chiếu; đây là thay đổi có chủ đích để tránh toàn bảng. Công cụ integrity audit dữ liệu cần được thực hiện riêng.
- Khi Parent Product ẩn theo PBAC nhưng Child Product được VIEW, tên đầy đủ của Child có thể thay đổi thành `product_child_name` riêng; đó là **field-level privacy masking**, cần chốt trên HTTP/UI trước production.
- Consistent snapshot yêu cầu DB hỗ trợ transaction (InnoDB). Product `create/update/setstate` gọi `show()` ngay trong transaction đang mở; `show()` dùng transaction đó không thay đổi isolation và không tự commit/rollback. `list()` từ chối mượn transaction. Không có bằng chứng runtime hoặc EXPLAIN từ MariaDB của người dùng ở giai đoạn source này.

## 3. Vì sao `allowed_actions` theo dòng nhưng tính theo nhóm?

Product hiện thuộc MASTER, vì vậy canonical `decideMutation('product', action, row)` dùng actor/request, State, GROUP ancestry và policies; không dùng Product ID để tính PBAC. Trên trang gồm 15 Product với 2 State, có thể chỉ cần tối đa **4** lần canonical mutation decision (2 State × 2 actions) thay vì 30. Mỗi dòng vẫn trả `allowed_actions` đúng format cũ.

**Không áp dụng mẹo này cho Price List hoặc resource ORGANIZATION**: cùng `ACTIVE` ở hai công ty nhưng quyền khác nhau. Provider ORGANIZATION biên dịch tổ chức/State riêng, tôn trọng assignment, ancestry và policy lanes. Grouping cho `allowed_actions` ORGANIZATION chỉ được bật khi có test chứng minh full context equivalence; nếu không, đánh giá từng row trong page.

## 4. Multi-company & trạng thái ORGANIZATION

`PriceListScopeProvider` đọc Organization IDs từ bảng `organizations` (metadata, không quét bảng `price_lists` để khám phá các bucket). `UnifiedAccessService::viewableOrganizationStateBuckets()` dùng canonical decision cho từng `(organization, State)`. SQL scope dùng cả hai cột; không merge A và B theo State. Những trường hợp vượt 512 organizations / 512 authorized slots **dừng fail-closed** để buộc chuyển sang derived scope relation/CTE hoặc bảng tạm có kiểm soát, không tự chạy query unscoped.

**Giới hạn hiện tại:** M8.5 KHÔNG chuyển production PriceListService sang provider mới, vì chưa có live MariaDB PBAC parity trên user đa công ty. Thao tác cũ của Pricing vẫn dùng `authorizedBuckets()` khám phá tối đa 512 bucket từ bảng `price_lists`. Đây là mục backlog cần giải quyết sau khi có bằng chứng thực tế.

## 5. Kết quả offline & nguồn bằng chứng

- `php tests/m85_sql_scope_pure.php`: fail-closed, domain, slots hai công ty, bind, request-local hints.
- `php tests/m85_product_sql_pure.php`: query parser, scope before COUNT/page, SQL bind/whitelist, Parent scoped JOIN, no grant.
- `php tests/m85_product_projection_fixture.php`: DTO parity SIMPLE/PARENT/CHILD/COMBO, ảnh, hidden-parent masking và missing parent visibility fail-closed.
- `php tests/m85_product_snapshot_fixture.php`: kiểm chứng transaction của Product create/update/setstate không bị `show()` mở/commit/rollback; standalone read vẫn có REPEATABLE READ và rollback khi lỗi.
- `php tools/m85_accept.php`: Source acceptance không sửa 150/150 API.
- `php tools/m85_scope_audit.php`: inventory read-only các query site còn backlog.
- `php tools/m34b_run.php --offline` + `php tools/m34d_accept.php --offline`: toàn bộ regression và architecture.

## 6. Audit các module đã tồn tại

| Resource/luồng | Phân loại sau M8.5 | Việc tiếp theo |
| --- | --- | --- |
| Product Master list/detail | `SQL_VIEW_PUSHDOWN_M85` | HTTP/PBAC parity, EXPLAIN, query latency/memory, negative parent masking. |
| Product Selector / Relationships | Existing SQL pushdown qua `UnifiedAccessService` | Đưa về shared compiler; rà soát masking các parent/child links, aggregate. |
| Pricing Price Lists | Existing SQL pushdown, bucket discovery từ bảng nghiệp vụ | Chạy đa công ty, chuyển provider khi parity và khả năng scale đã được chứng minh. |
| Product Group/UOM catalog | In-memory PBAC + pagination | Viết Product Catalog provider (MASTER), SQL `COUNT`/page. |
| Locations/Organizations/People | In-memory controller/repository | Tách provider theo Resource Domain, ancestry, ownership; test cross-company. |
| Contacts | In-memory, polymorphic owner | Trước hết thiết kế owner scope union; không áp `organization_id` trực tiếp lên contacts. |
| Access, Shield Users, Workflow | In-memory read services | Provider riêng theo SYSTEM/ORGANIZATION/Master; tránh quyền lộ khi JOIN liên kết. |
| Import Jobs | Existing SQL/group-root scope | Kiểm tra đồng nhất với provider ORGANIZATION trên multi-company, tránh leak. |

`M85 scope audit` là **source inventory**, không phải kết luận các module backlog đã tối ưu hoặc được nghiệm thu runtime.

## 7. Điều kiện nghiệm thu XAMPP trước rollout

Từ `D:\XAMPP\htdocs\erp\backend`, chạy các gate offline và check-only trước, **không có mutation**:

```powershell
php tools\m85_accept.php
php tools\m85_scope_audit.php
php tests\m85_sql_scope_pure.php
php tests\m85_product_sql_pure.php
php tests\m85_product_projection_fixture.php
php tests\m85_product_snapshot_fixture.php
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Các bước live cần fixture user tối thiểu: 1 actor VIEW cả ACTIVE/DRAFT, 1 actor chỉ VIEW một State, actor có ABSOLUTE_DENY và quyền hành động theo State, 2 công ty có cùng State nhưng grants khác, một child có parent bị ẩn. Đối chiếu **exact IDs, `total`, `role`, `full_name`, `allowed_actions` và thời gian xử lý** giữa source trước/sau; chạy `EXPLAIN` cho query Count/page và kiểm tra bảng `products` là InnoDB. Không có live test => tiếp tục PRODUCTION BLOCKED.

**Chính sách rollout:** M8.5 là thay đổi source GET và compiler; không bật production nếu chưa kiểm thử parity với dữ liệu và quyền thực trên DEV. Không tự sửa engine hoặc chạy write QA lên `erp`.
