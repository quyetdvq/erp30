# Báo cáo cải tiến code P1 — DinkERP v1.9.23 (Implementation Candidate)

> **Nguồn:** bản mã nguồn DinkERP v1.9.22 tái dựng làm baseline bất biến của patch; ngày 2026-10-29. Tài liệu này là change record, **không phải biên bản nghiệm thu MariaDB/HTTP/Production**. Không chạy migration, không cập nhật DB và không thay P1 pinned fixtures.

## 1. Ma trận 5 vấn đề được giao

| Điểm P1 phát hiện | Cách cải tiến ở v1.9.23 | Đã kiểm chứng offline | Còn phải xác nhận trên DEV |
|---|---|---|---|
| (1) ContactScopeProvider chặn quá 512 Organization | `TrustedOrganizationIdPager` keyset `id > last`, batch=256; scope metadata tối đa **4096**; PBAC `(Org,State)` slots tối đa **16384** và nhóm SQL theo State; vẫn deny/overflow fail-closed | 26/26 test mới; M8.5/M8.6B/E | EXPLAIN tổ chức lớn; độ dài SQL/bind; p95; HTTP deny/allow với nhiều owner |
| (2) `LOWER`, `CAST` có thể làm mất index | Predicate index-friendly cho **ID chuẩn canonical**: `ward.province_id` và `contact.contact_ref_id` dạng `IN (?)`; **bảo toàn nhánh legacy exact-string** với CAST, giữ text search/State normalization để không đổi semantics | 30/30 Location SQL và 61/61 Contact SQL | EXPLAIN canonical vs legacy; Unicode case-fold/collation parity; đo search LIKE |
| (3) Page-size cap không đồng nhất | `listPageSizePolicy.ts`: options đến 200 cho P1 catalog, đến 100 cho Customer. Toolbar/pager dùng cùng policy; backend giữ hợp đồng riêng, không tự nới giới hạn | Frontend policy test được viết; Vue full QA **NOT_RUN** tại môi trường tạo patch | Vitest/type/lint/build Windows, HTTP cap 100/200 và 400 oversized |
| (4) Row hints có nguy cơ không phản ánh owner | Location SQL-read mới dùng `EffectiveRowActionHints::forViewedRow` sau SQL PBAC và kiểm tra `canView`; Contact/People/Customer tiếp tục dùng chính cơ chế P1, không giả lập quyền ở FE | M10.2 row-action suite & source inventory PASS | HTTP 2 tài khoản, owner Organization, status/DENY/mutation 403 |
| (5) Lookup/report chưa chắc có SQL PBAC | **Province/Ward list+detail** đã đổi sang SQL-first COUNT+PAGE/scope; **Organization labels** đã có SQL-PBAC trước đó; Contact parent batch lookup 1 lần/trang (giảm N+1). **Không nhận là đã sửa mọi báo cáo P1** vì chưa có endpoint báo cáo nghiệp vụ tương ứng trong mã nguồn được xét | 30/30 Location, 9/9 Contact batch; source regression PASS | HTTP lookup negative tests; tất cả report mới phải review aggregate/PBAC và as-of riêng |

## 2. Các lớp và hợp đồng thay đổi

**Backend — SQL-first Location:**

- `app/Modules/Location/Read/{LocationMasterSql,LocationMasterScopeProvider,LocationMasterReadService}.php` (mới): `GET /api/v1/provinces` và `/wards` dùng `COUNT(*)` riêng, `SELECT ... LIMIT/OFFSET` có `ORDER BY id` tie-break, SQL PBAC trước cả hai, và single repeatable-read snapshot. Không thay route/API response.
- `app/Controllers/Api/ReadCatalogController.php` bỏ full-scan-then-PHP-paginate Province/Ward; cho detail dùng cùng SQL-scoped reader.
- `AuthorizationSqlScopeCompiler`, `AuthorizedSqlScope`, `UnifiedAccessService`: whitelist đúng hai MASTER resources, lifecycle state có thẩm quyền.
- Test mới `tests/m1923_location_sql_pure.php`.

**Backend — Organization scope:**

- `app/Modules/Access/Query/TrustedOrganizationIdPager.php` (mới), `ContactScopeProvider`, `OrganizationPeopleScopeProvider`: keyset metadata; overflow fail closed.
- `UnifiedAccessService`: 16.384 slot budget thay 512; `AuthorizedSqlScope` nhóm chính xác các cặp Org/State theo State để giảm OR clauses; không trộn trạng thái giữa Organization.
- Test mới `tests/m1923_org_scope_pure.php`.

**Backend — Contact:**

- `ContactService`: lấy Customer owner parent trong một truy vấn `WHERE id IN (...)` cho trang đang xem; cache quyết định parent `canView` theo Customer ID, kiểm tra thiếu owner fail-closed. Contact không phải Customer giữ flow cũ.
- `ContactSql`: dùng index trên `contact_ref_id` canonical, giữ tính tương thích filter cũ cho ID dạng chuỗi phi chuẩn.
- Test mới `tests/m1923_contact_parent_batch_pure.php`.

**Frontend — page size:**

- `src/components/lists/listPageSizePolicy.ts`, `src/components/lists/__tests__/listPageSizePolicy.spec.ts` (mới), `ListQueryControls`, `ListPagination`, `CustomersView`.
- Không đổi route hoặc API Customer; không tăng cap 100 của Customer.

**Source acceptance:** Những bài pure P1 cố định kiến trúc full-scan/512 được thay thành kiểm tra SQL-first, trusted metadata keyset và fail-closed; không xóa pin checksum M3/M4/M8.7 hoặc bỏ kiểm tra âm tính. Bài `test-unit-check.php` tính toàn bộ script hiện có.

## 3. Trạng thái và giới hạn cần công bố

1. **Không có migration/index mới.** Đã sử dụng index trên cột FK/ID nếu thực tế DB có index đúng; bắt buộc kiểm tra `SHOW INDEX` trên DEV.
2. **4096 không phải infinite scale.** Với nhiều State và deny rules, câu SQL và lượng bind vẫn có thể lớn; phải chuyển sang DB-native effective-scope/join khi workload thực tế chứng minh cần thiết.
3. **`LOWER/LIKE` còn tồn tại có chủ đích.** Tối ưu text search nâng cao cần thiết kế thêm normalized/search columns, không triển khai ở patch read-only này.
4. **Location list vẫn SELECT các cột của bản ghi (`l.*`) để giữ payload P1**; tập cột có thể tối giản tiếp nhưng không được phá client hiện hữu. **Province/Ward runtime parity còn cần chứng minh.** Trường hợp Unicode, null/empty/leading-zero và sort collation cần kiểm tra MariaDB với dữ liệu thật; test source thuần không thay thế.
5. **Report/Export P1 không bị sửa đại trà.** Báo cáo Sales/FIN còn phụ thuộc module P2 và UR; chuẩn SQL-STD-01 yêu cầu aggregate scope/period/as-of riêng khi xuất hiện.
6. **Không có benchmark/số ms được khẳng định.** Bất kỳ tuyên bố cải thiện tốc độ phải có kết quả `EXPLAIN`, p50/p95 và fixture xác thực trên DEV.

## 4. Kiểm thử nguồn ở môi trường tạo patch

| Nhóm | Kết quả |
|---|---|
| New: Location SQL/PBAC | 30/30 PASS |
| New: Trusted Org Scope | 26/26 PASS |
| New: Contact Parent Batch | 9/9 PASS |
| P1: M8.5, M8.6B–E, M10.2, M8.7 SQL | PASS theo selected suites |
| Backend inventory | PASS — 164 scripts |
| PHP syntax (modified/new) | PASS |
| Frontend Vitest / lint / build | **NOT_RUN** (không có project node_modules) |
| MariaDB EXPLAIN, HTTP PBAC, browser UAT | **NOT_RUN** (chỉ người dùng có DEV) |

## 5. Điều kiện release và rollback

Áp dụng Git patch **v1.9.23** sau v1.9.22 bằng `git apply --check`. Không chạy migration. Trước triển khai DEV thật cần backup source, lưu trạng thái Git, và có thể dùng `git apply -R --check`/`git apply -R` nếu chưa có sửa tiếp trên các file.

**Quyết định nghiệm thu:** `OFFLINE_SOURCE_QA_PASS / DEV_SQL_HTTP_UAT_PENDING` — không đánh dấu `P1 Performance CLOSED` khi chưa có EXPLAIN và kiểm thử quyền trên DB.
