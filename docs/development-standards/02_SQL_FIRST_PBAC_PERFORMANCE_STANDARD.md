# DinkERP — Quy chuẩn SQL-first, PBAC và hiệu năng cho module mới

> **Mã:** SQL-STD-01 · **Phiên bản:** 1.1.0 · **Ngày:** 2026-10-29
> **Trạng thái:** **TECHNICALLY REVIEWED v1.1.0 — mọi thay đổi UR/PBAC/schema đã khóa cần duyệt riêng**; không tự thay đổi PBAC/lifecycle/schema P1 đã khóa.
> **Áp dụng đề xuất:** Tất cả danh sách, chi tiết, lookup, báo cáo, export, command và event tích hợp của SUP, DOC, PUR, INV, SAL, FIN và các module về sau.
> **Đã đối chiếu source:** `CustomerSql`, `ContactSql`, `PeopleMasterSql`, `ProductCatalogSql`, `AuthorizedSqlScope`, `AuthorizationSqlScopeCompiler`, `EffectiveRowActionHints`, migration Contacts/Customer và `CustomerService` đến v1.9.22; bản P1-refactor v1.9.23 là implementation candidate, chưa có MariaDB/HTTP/Browser UAT.

## 0. Quy tắc cốt lõi — thứ tự bắt buộc

**Xác thực → phạm vi sở hữu có thẩm quyền → PBAC trong SQL → điều kiện filter/search → COUNT và page/query báo cáo → `allowed_actions` → response.**

Bất kỳ hành vi nào thực hiện `SELECT` không giới hạn rồi mới `array_filter` quyền ở PHP/JavaScript sau `LIMIT/OFFSET` là **KHÔNG ĐẠT**. `allowed_actions` chỉ là gợi ý hiển thị, **không thay thế kiểm tra quyền cuối ở endpoint mutation**.

## 1. Ranh giới quyền và quyền sở hữu

### SQL-01 — Phạm vi sở hữu từ dữ liệu đã lưu, không từ client [REQUIRED — PROPOSED]

- Existing record: xác định Company/Organization/Group từ **persisted owner** hoặc quan hệ nghiệp vụ có thẩm quyền. Không tin `company_id`, `organization_id`, `owner_type` do client gửi để thay đổi phạm vi đánh giá của một ID đã tồn tại.
- Create: input Company chỉ là đề nghị; phải xác thực Company, owner scope, quan hệ Warehouse/Account/Customer/Supplier và quyền tạo trước khi ghi.
- `MASTER` scope và `ORGANIZATION` scope là hai miền khác nhau, không tráo đổi. Customer Group Master có Company Usage để hạn chế nơi sử dụng trong giao dịch; **Company Usage không tự cấp quyền VIEW Customer**.
- Contact polymorphic theo `contact_type` và `contact_ref_id`: Organization Contact dùng owner Organization; Employee Contact dùng primary assignment hợp lệ tại thời điểm; Customer Contact dùng Customer MASTER và quyền `customer_contact`. Không có `contacts.organization_id` giả định.
- Với scope không có quyền hoặc thiếu dữ liệu tin cậy, **fail closed** (`1=0` hoặc 403/404 có kiểm soát). Không dùng current Company hay quyền Group thay thế. Không dùng organization id trong query param làm nguồn cấp quyền.

### SQL-02 — Bắt buộc SQL-scope trước COUNT, LIST, DETAIL, AGGREGATE [REQUIRED — PROPOSED]

- Mọi resource có PBAC phải có `ScopeProvider` được review, biên dịch qua cơ chế `AuthorizationSqlScopeCompiler` / `AuthorizedSqlScope` hoặc phần mở rộng được duyệt.
- Cùng một tập predicate đã chuẩn hóa cho `COUNT(*)` và `SELECT ... LIMIT ...`; không được lọc quyền sau pagination hoặc điều chỉnh `meta.total` bằng số dòng sau lọc PHP.
- `GET /{id}` và `lookup by IDs`, tổng hợp/aggregate/report/export phải có **cùng scope**. Không cho xem detail/export chỉ vì đoán đúng ID từ page khác.
- Khi tìm scope thấy mismatch giữa SQL và PBAC canonical, fail safe và test như một lỗi nghiêm trọng, **không tự bỏ điều kiện PBAC để UI khỏi lỗi**.
- Có thể kiểm tra `canView` trên bản ghi đã lấy như **assertion parity**, nhưng không được dùng nó làm bước lọc quyền chính sau `LIMIT/OFFSET`.

### SQL-03 — Không sao chép giới hạn tạm thời của P1 thành kiến trúc P2 [REVIEW]

**[SOURCE]** Trên baseline P1 v1.9.22, `ContactScopeProvider` có trần metadata **512 Organization** và fail-closed. Implementation candidate v1.9.23 chuyển sang đọc metadata bằng **keyset theo batch 256** với trần **4.096** và chặn khi vượt giới hạn; slot PBAC có trần **16.384**. Đây là *giới hạn an toàn trung gian*, không phải giải pháp cho Organization quy mô không giới hạn. Module lớn cần thiết kế scope dựa trên quyền ở DB/authorized relation hoặc vật hóa có audit, và kiểm tra parity; tuyệt đối không tắt kiểm tra overflow hay nhận danh sách quyền từ request.

## 2. Hợp đồng API danh sách và truy vấn

### SQL-04 — Một hợp đồng list thống nhất [REQUIRED — PROPOSED]

Đề nghị cho **module mới** sử dụng:

```http
GET /api/v1/{resources}?page=1&page_size=15&q=abc&sort=-id&filter[status]=ACTIVE
```

**Có thể bỏ filter[status]** để lấy mọi trạng thái mà actor được quyền VIEW. Trường hợp module P1/CUS cũ đang dùng filter phẳng thì giữ compatibility adapter, không âm thầm breaking change.

Response tối thiểu:

```json
{
  "data": [
    {
      "id": "42", "code": "EXAMPLE-42", "name": "Tên bản ghi", "status": "DRAFT",
      "allowed_actions": { "edit": false, "setstate": true }
    }
  ],
  "meta": { "page": 1, "page_size": 15, "total": 86 }
}
```

- Các `page`, `page_size` là positive integers, offset không overflow. `page=1`, page size UI mặc định 15 theo component chung. FE dùng `listPageSizePolicy.ts` đồng bộ toolbar/pager theo cap API. Mặc định đề xuất cho **API giao dịch mới**: cap `100`; các read-only catalog đã có có thể cap `200` theo hợp đồng riêng. Cap cuối phải được khóa trong OpenAPI + FE filter options + test.
- `q`: trim, giới hạn độ dài/tần suất/đặc tính wildcard theo resource; lỗi tham số lạ/tên sort lạ trả 400, không tạo SQL không scope hoặc âm thầm bỏ qua query nguy hiểm.
- `filter`: chỉ whitelist field, enum, kiểu dữ liệu, số lượng giá trị. Mảng rỗng phải xử lý có chủ đích (không cho SQL `IN ()`). Không dùng `filter` để vượt persisted owner.
- `sort`: chỉ whitelist cột hoặc biểu thức SQL đã review; `-field` = DESC, `field` = ASC, thêm **tie-break `id`**; không chuyển nguyên chuỗi `sort` của client vào `ORDER BY`.
- Không chấp nhận SELECT table name, alias, function, direction từ request. Table/DBPrefix là identifier có allowlist/validate, mọi **giá trị** dùng bind.
- `meta.total` = COUNT trên **tập bản ghi có VIEW + filter/search**, trước LIMIT; không phải `count(data)` của một page.

### SQL-05 — Mẫu SQL list/counter [REQUIRED — PROPOSED]

Ví dụ một bảng tài liệu Company-owned. Đây là **mẫu minh họa**, không thay thế compiler PBAC thực tế và không dùng trực tiếp cho Group Master:

```sql
-- Binds phải xuất phát từ server-side PBAC + validated input.
-- Hai câu cùng dùng một bộ FROM/WHERE/PBAC.
SELECT COUNT(*) AS total
FROM <company_owned_table> x
WHERE (<reviewed_authorized_company_scope_for_actor>)
  AND x.status = ?;

SELECT x.id, x.code, x.name, x.status, x.updated_at
FROM <company_owned_table> x
WHERE (<reviewed_authorized_company_scope_for_actor>)
  AND x.status = ?
ORDER BY x.updated_at DESC, x.id DESC
LIMIT ? OFFSET ?;
```

Ví dụ một Customer Group Master có lọc khả dụng trong Company:

```sql
-- Chỉ dùng SAU khi đã có GROUP MASTER PBAC predicate cho Customer.
SELECT c.id, c.customer_code, c.name, c.status
FROM <customers_table> c
WHERE (<group_customer_view_scope>)
  AND (c.all_companies = 1 OR EXISTS (
    SELECT 1 FROM <customer_company_usages_table> u
    WHERE u.customer_id = c.id AND u.company_organization_id = ?
  ))
ORDER BY c.id DESC
LIMIT ? OFFSET ?;
```

Giữ `EXISTS` thay vì `JOIN` 1:N khi mục tiêu chỉ là xác định tính khả dụng. `JOIN` nhiều dòng làm sai COUNT, pagination và thậm chí trả trùng bản ghi.

### SQL-06 — `COUNT` và `SELECT` phải có cùng điều kiện và tính nhất quán thời điểm rõ ràng [REQUIRED — PROPOSED]

- Dùng cùng plan/parameters PBAC + filter/search. Tại `READ-COMMITTED`, hai statement riêng có thể quan sát snapshot khác khi concurrent writes; không cam kết `total` và rows **luôn đồng nhất tuyệt đối trong mọi race**.
- Cho giao diện nghiệp vụ bình thường: chấp nhận khác biệt thời điểm nhỏ nếu đã định nghĩa, đảm bảo stable sort và refresh. Với báo cáo đối soát, xuất kho, tài chính/as-of cần snapshot/transaction semantics cụ thể và test concurrency theo nghiệp vụ.
- Nếu `total=0`, có thể bỏ qua page query; **không được trả `total=0` do SQL error**. Query lỗi phải phát ra mã lỗi có kiểm soát.

## 3. Quy tắc nâng hiệu năng SQL

### SQL-07 — Chỉ chọn cột cần hiển thị [REQUIRED — PROPOSED]

- List query SELECT projection hẹp (`id`, mã, tên, trạng thái, owner/Company, timestamp…) thay vì `SELECT *` cho các bảng giao dịch nhiều cột JSON/text. Detail có thể tải phần rộng hơn với quyền riêng.
- Liên kết phụ: ưu tiên page-then-batch enrichment (labels, counts, permissions) cho tối đa số dòng của trang. Không gọi một query riêng cho mỗi dòng và không trả các trường PII/secret chỉ để hiển thị bảng.
- Nếu phải hiển thị nhiều child lines per order, nên trả aggregate/batch trong repository hoặc endpoint chi tiết; không N+1.

### SQL-08 — Tạo index theo predicate thật, không theo cảm giác [REQUIRED — PROPOSED]

- Phân tích thứ tự `WHERE` equality `(company_id, status)` và `ORDER BY (created_at DESC, id DESC)` để đề xuất index hỗ trợ; không tự động tạo tất cả tổ hợp.
- Dùng khóa UNIQUE cho business key và idempotency; foreign key và composite keys cho owner+source. Kiểm tra index hiện hữu trước khi thêm migration additive.
- **[SOURCE]** P1 Contact đã có index `(contact_type, contact_ref_id)`; Customer có `(status, name)`, Group/Usage, Interaction indexes. Không tạo lại index đã có.
- Với filter date range, dùng `>= :from AND < :to` trên cột instant UTC lưu chuẩn; tránh `DATE(column) = ...` trong WHERE nếu có thể dùng range.
- Index được duyệt sau `EXPLAIN` trên dữ liệu đại diện, kiểm tra write amplification và ảnh hưởng migration. Chỉ số dòng được estimate rất thấp trên DEV rỗng **không chứng minh** query sẽ nhanh ở Production.

### SQL-09 — Tìm kiếm text phải được đánh giá như một loại workload riêng [REQUIRED — PROPOSED]

**[SOURCE]** Một số planner P1/CUS dùng `LOWER(COALESCE(column,'')) LIKE '%term%'`, một số sort `LOWER(column)` và filter `CAST(column AS CHAR)`. Chúng phục vụ semantics/collation hiện tại, nhưng thường **không sử dụng tốt B-tree index** cho search/sort, nhất là leading `%` hoặc function trên cột.

**Quy tắc cho module mới:**

1. Tìm `code`, SKU, order number: ưu tiên exact hoặc prefix (`code = ?`, `code LIKE 'ABC%'`) có index và kế hoạch đo.
2. Tìm tên/email mô tả: lựa chọn collation hợp lệ, normalized lookup column, FULLTEXT hoặc cấu trúc tìm kiếm phù hợp sau review và benchmark; không tự đổi nghĩa tìm kiếm tiếng Việt để “nhanh”.
3. Escape wildcard `%`, `_` và escape char theo SQL engine khi tìm literal. Giới hạn độ dài q và số cột OR.
4. Không áp dụng `LOWER`, `UPPER(TRIM)`, `COALESCE`, `CAST` trên cột index trong hot path mới nếu có thể lưu canonical data hoặc dùng indexed predicate **mà vẫn giữ semantics**.
5. Thay đổi search semantics của P1 cần regression/i18n và dữ liệu đối chiếu trước khi rollout.

### SQL-10 — Tối ưu pagination và các trang sâu [REQUIRED — PROPOSED]

- `LIMIT/OFFSET` là lựa chọn chuẩn cho danh sách nhỏ/vừa, hỗ trợ page number UI. Luôn deterministic sort và cap page size.
- `OFFSET` rất lớn vẫn có thể tốn thời gian vì database phải đi qua nhiều dòng. Trên ledger/audit/webhook history lớn, xem xét **keyset/cursor pagination** theo `(created_at, id)` hoặc `(posted_at, id)` với hợp đồng API riêng, không ép mọi module dùng OFFSET vĩnh viễn.
- Export hàng chục nghìn dòng **không gọi page_size vô hạn**; dùng export permission + batch/chunking + job nếu cần, audit và limit tải.

### SQL-11 — Lookup, label resolution và quyền theo dòng không được N+1 [REQUIRED — PROPOSED]

- Location, User, Organization label, Product/Supplier/Customer selector: `q+page_size+scoped filters`, tra cứu chi tiết theo ID khi mở edit. Bulk resolve labels bằng request đã xác thực, giới hạn số IDs và đúng body OpenAPI.
- `allowed_actions` tính từ **persisted row** sau khi VIEW proven, trả boolean thuần: `edit`, `setstate` (và action khác nếu contract hỗ trợ). Dùng `EffectiveRowActionHints`/cơ chế chuẩn được review; không query PBAC trong loop `N` lần đến DB.
- Chỉ cache hint theo `resource + actor's permission snapshot + owner + status + các thuộc tính thực sự tham gia quyết định`; **MASTER có thể cache theo trạng thái khi đã chứng minh quyết định không phụ thuộc row**, Organization scope phải đánh giá **theo từng row**. Không cache toàn cục xuyên user/request.
- Quyền trả từ API là hint. POST/PATCH/SetState phải chạy check quyền lại trong transaction theo persisted owner.

## 4. Mutation, workflow, dữ liệu và sự kiện

### SQL-12 — Mọi thay đổi nghiệp vụ qua service + transaction [REQUIRED — PROPOSED]

- Controller nhận input đã kiểm tra, service lấy bản ghi gốc, derive owner/Company, kiểm PBAC và Workflow, lock/select-for-update hoặc optimistic concurrency khi cần, ghi mutation + audit trong transaction, COMMIT rồi publish outbox theo cơ chế đã phê duyệt.
- Các hành động `submit/approve/reject/post/reverse/setstate` không được cập nhật trạng thái bằng PATCH CRUD trực tiếp. Không coi `allowed_actions=true` là bảo đảm action sẽ thành công sau race; server phải tái kiểm tra.
- Operations POSTED/ledger immutable, sử dụng reversal và idempotency key; concurrency không được double stock/money.
- HTTP GET không có side effect; nếu cần đồng bộ notification khi F5, dùng endpoint POST riêng được xác thực/CSRF, giới hạn actor/scope/rate và không biến HTTP thành worker toàn hệ thống.
- Không thêm bảng riêng để lưu bản sao tổng doanh số/công nợ khi số liệu có thể tính an toàn từ canonical SAL/FIN, trừ khi có yêu cầu aggregate/materialization được phê duyệt + reconciliation.

### SQL-13 — Reports và as-of [REQUIRED — PROPOSED]

- Báo cáo Group có thể tổng hợp nhiều Company **chỉ trên các Company được actor xem**, không lấy toàn bộ rồi mới lọc.
- Phải nêu rõ `as_of`, timezone, period `[from,to)`, trạng thái tham gia (confirmed/fulfilled/posted/reversed), semantics return/refund/COD; không đánh đồng order amount và receivable hay cash.
- Mỗi aggregate SQL có `WHERE` PBAC tương đương list/detail, tránh `SUM` trên join 1:N gây nhân đôi tiền/số lượng.

## 5. Kiểm chứng hiệu năng bằng chứng cứ thật

### SQL-14 — Bắt buộc EXPLAIN và benchmark theo dataset [REQUIRED — PROPOSED]

Cho **mỗi truy vấn list/lookup/report quan trọng**, lưu tối thiểu:

| Chứng cứ | Điều kiện |
|---|---|
| SQL plan | `COUNT` và `ROWS` với bind mẫu; không log dữ liệu nhạy cảm |
| Cấu trúc | MariaDB version, DBPrefix, index hiện hữu, volume rows & distribution |
| EXPLAIN | `EXPLAIN FORMAT=JSON` (hoặc `EXPLAIN` tương đương phù hợp MariaDB đang dùng) |
| Số đo | p50, p95, số request/query, rows returned, số queries cho một page |
| Phạm vi quyền | VIEW all/partial/none; Group/Company khác nhau; owner polymorphic |
| Scenarios | không filter; filter status; q phổ biến và q không có kết quả; first/middle/deep page; sort khác |
| Regression | đối chiếu `data`, `total`, `allowed_actions` và SQL PBAC với canonical decision |

**Chưa khóa SLA số mili-giây:** chưa có benchmark chuẩn về phần cứng/dataset của SUP/PUR/INV/SAL/FIN. Trước khi duyệt triển khai, mỗi module phải đăng ký budget p95 và fixture-volume cụ thể. Không ghi “tối ưu” chỉ vì câu SQL dùng `LIMIT`.

**Chú ý:** `EXPLAIN` là phân tích execution plan; đo thời gian thực tế cần benchmark truy vấn hoặc instrumentation API. Không đo Production bằng `ANALYZE` có tác động tài nguyên nếu chưa được phép.

### SQL-15 — Bộ kiểm thử SQL/PBAC bắt buộc [REQUIRED — PROPOSED]

1. **Functional:** page 1/2, size cap, stable sort khi giá trị trùng, q unicode/escape/wildcard, blank filter, unknown key, malformed ID, overflow, `total` chính xác.
2. **Security:** user không có VIEW; DENY policy; actor thuộc Company A hỏi Company B; guessed detail ID; cross-owner Contact/Document; count/report/export không rò số lượng; fail-closed khi owner dữ liệu hỏng.
3. **Hints:** `edit=true/setstate=false`, `edit=false/setstate=true`, cả hai false, hint thiếu, hai owner/scope có cùng status nhưng quyền khác; action trực tiếp vẫn bị chặn nếu không có quyền.
4. **Lifecycle:** nhiều trạng thái, tùy biến, transition hợp lệ/không hợp lệ, concurrent state change, audit/idempotency.
5. **Performance:** trang 15/50/100 (nếu được phép), dữ liệu đại diện, EXPLAIN COUNT/ROWS/lookup, xác nhận không có N+1 và plan không mất index ngoài dự kiến.
6. **Compatibility:** DBPrefix != rỗng, MariaDB cấu hình thật, collation/Unicode/UTC, nested transaction, snapshots và các bài hồi quy P1/CUS.
7. **API:** Envelope/mã lỗi/validation và OpenAPI schema khớp, đặc biệt payload JSON dạng object vs array (bài học `organizations/labels/resolve`).

## 6. Ma trận anti-pattern — cấm trong module mới

| Anti-pattern | Vì sao không đạt | Thay bằng |
|---|---|---|
| `SELECT *` tất cả bản ghi → PHP filter → array slice | Scope/count/page sai, tải DB lớn | SQL-scope + COUNT + SELECT/LIMIT |
| Query không có PBAC rồi filter frontend | Rò dữ liệu | Scope tại SQL trước response |
| `JOIN` owner/child 1:N để lọc tồn tại | Nhiều dòng trùng, COUNT sai | `EXISTS` hoặc distinct strategy được đo |
| Raw `ORDER BY $request['sort']` | SQL injection, sort không xác định | Identifier whitelist + tie-break id |
| `LIMIT` không `ORDER BY` ổn định | Page nhảy/trùng dòng | `ORDER BY ..., id` |
| Per row SQL lookup label/authorization | N+1, tăng tuyến tính | Batch page-local, PBAC snapshot |
| `LIKE '%term%'` trên nhiều cột lớn không đo | Scan/sort tốn chi phí | Search plan riêng + EXPLAIN |
| `UPPER(TRIM(indexed_field))` khắp hot path | Ngăn index thông thường | Canonical data/normalized index sau review |
| Tự suy owner từ Company đang chọn | Rò/leo quyền | Persisted owner + canonical relationship |
| Tạo quyền từ `allowed_actions` frontend | Bypass PBAC | Mutate endpoint tái kiểm tra |
| Coi `COUNT=0` sau exception DB | Che lỗi và dữ liệu giả | HTTP error chuẩn + không làm mất stale page |
| Chạy cron/Push worker trên mọi GET | Gây side-effect và khuếch đại tải | POST scoped, bounded + cron canonical |

## 7. Cấu trúc code và lệnh QA hiện có

**[SOURCE] Backend tham khảo:**

- `backend/app/Modules/Access/Query/AuthorizedSqlScope.php`
- `backend/app/Modules/Access/Query/AuthorizationSqlScopeCompiler.php`
- `backend/app/Modules/Access/Query/EffectiveRowActionHints.php`
- `backend/app/Modules/Customer/CustomerSql.php`, `CustomerScopeProvider.php`, `CustomerService.php`
- `backend/app/Modules/Contact/ContactSql.php`, `ContactScopeProvider.php`
- `backend/app/Modules/People/Read/PeopleMasterSql.php`
- `backend/app/Modules/Product/Catalog/ProductCatalogSql.php`

**Đề xuất cấu trúc module:**

```text
backend/app/Modules/<Resource>/
  <Resource>Controller.php         # HTTP schema, envelope, errors
  <Resource>Service.php            # canonical business operations
  <Resource>Rules.php              # pure validation/domain
  <Resource>Sql.php                # scoped SQL list/detail plans
  <Resource>ScopeProvider.php      # reviewed authoritative PBAC
```

Các tên file là **mẫu đề xuất**, không ép module phức tạp phải là một Service duy nhất.

**[SOURCE] Lệnh kiểm tra sẵn có:**

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN CUS-UNIT  # khi sửa/shared CUS/PBAC/Contact
# Chạy thêm suite chuyên biệt module và SQL/PBAC negative tests của module mới.
```

`CHECK`/pure test **không thay** cho thực thi API/MariaDB trên DEV clone, benchmark EXPLAIN, PBAC positive/negative UAT, concurrency test và browser validation.

## 8. Điều kiện No-Go cho module SQL/API

Từ chối giao module nếu có bất kỳ điểm nào:

- COUNT và rows không dùng cùng authorized predicate; có dữ liệu xuất hiện sau filter PHP/client.
- Quyền owner/Company được suy từ client thay persisted source; Contact polymorphic lẫn owner; mọi `403/404` được biến thành dữ liệu `[]` giả.
- Có dynamic SQL identifier không whitelist hoặc SQL binds không kiểm tra.
- Không có `meta.total`, page size cap, deterministic sort hoặc allowed_actions khi UI có mutation.
- Lookup/report/export bỏ qua PBAC, dùng `JOIN` làm nhân đôi totals.
- Có N+1 rõ ràng hoặc `EXPLAIN` cho hot query chưa được xem.
- Chưa kiểm tra trên DB/HTTP thật mà báo `SQL runtime PASS`.

**Quy chuẩn này không phải lời xác nhận mọi truy vấn P1 hiện đạt hiệu năng tối ưu. Một số pattern P1 là hiện trạng cần đánh giá lại khi mở rộng dữ liệu; muốn sửa P1 phải có migration/regression riêng.**


## 9. Revision v1.1.0 — nguyên tắc tối ưu không đổi kết quả nghiệp vụ

### PERF-R1 — Ưu tiên tương đương dữ liệu trước thay đổi biểu thức

- Không tự động thay `LOWER(...)` hay `CAST(...)` trên tất cả truy vấn. Phân biệt `ID` số **canonical** với chuỗi mã/nhãn dùng so sánh exact-string (case, dấu tiếng Việt, leading zeros). `CAST()` trên cột numeric có thể phá index, nhưng bỏ nó có thể thay đổi ý nghĩa so khớp chuỗi legacy.
- V1.9.23 sử dụng predicate `province_id IN (?,...)` và `contact_ref_id IN (?,...)` **chỉ khi mọi ID đầu vào canonical**; yêu cầu legacy phi chuẩn được giữ riêng (đúng chức năng nhưng cần đo scan). Các cột text có `LOWER/LIKE` cần workload/profile và chỉ chuyển sang generated normalized column/fulltext khi phê duyệt schema + test Unicode/collation parity.
- Giữ trạng thái SQL PBAC tương đương canonical Lifecycle (bao gồm whitespace/lowercase legacy nếu source đang chuẩn hóa), không bỏ `UPPER(TRIM(status))` chỉ để ép index scan nhanh.

### PERF-R2 — Giới hạn scope phải đo được và fail-closed

- Scope Organization tối đa **4.096 Organization**/actor và **16.384 `(Org,State)` slots** trong implementation candidate; vượt thì báo lỗi có kiểm soát và tạo issue scale-up, **không cắt bớt danh sách quyền rồi tiếp tục**.
- Dùng keyset để đọc **trusted Organization metadata**. **Không đồng nhất** keyset metadata với cursor danh sách nghiệp vụ; page list hiện vẫn `LIMIT/OFFSET` có tie-break ID.
- `AuthorizedSqlScope` có thể nhóm cặp quyền theo State thành các `IN` lists (vẫn kiểm tra **chính xác cặp Org/State**); chú ý kích thước SQL và số bind param khi số Organization lớn. 4.096 là cap kỹ thuật, chưa phải benchmark p95.
- Cần kế hoạch nâng cấp sang SQL-native effective-scope cho workload lớn, nhưng chỉ sau khi mô hình DENY/ancestor/policy parity được chứng minh.

### PERF-R3 — SQL PBAC cho mọi đường đọc

- API danh sách/chi tiết/lookup/label/report/export phải xác định **persisted owner scope** rồi áp PBAC **trước** `COUNT`, aggregate, order, pagination. Với Customer parent, một trang Contact được phép VIEW cần lấy parent theo `WHERE id IN (...)` một lần, kiểm tra quyền parent riêng, không N+1.
- V1.9.23 đưa `province`/`ward` sang COUNT/ROWS SQL-first có PBAC, trả `allowed_actions` trên từng dòng đã xem hợp lệ. `organizationLabels` từ P1 đã SQL-PBAC; **chưa có chứng cứ đã cải tiến tất cả báo cáo P1**. Mọi report mới phải review riêng `SUM`, `GROUP BY`, quyền Company, timezone, as-of, reversals, no unauthorized aggregates.
- **Tương thích response P1:** `LocationMasterSql` v1.9.23 còn `SELECT l.*` trong list để không loại bỏ các trường metadata mà client legacy có thể đang dùng. Đây là ngoại lệ tương thích đã ghi nhận, **không phải** khuyến nghị cho endpoint mới; giảm cột yêu cầu review OpenAPI/client consumers và HTTP UAT trước.
- `allowed_actions` phục vụ UI và không có quyền thay thế backend mutation enforcement. Nếu scope SQL và `canView()` bất đồng thì fail toàn request, không lọc rớt dòng sau khi phân trang.

### PERF-R4 — Bằng chứng/đo đạc bắt buộc

1. Ghi kế hoạch query và cấu hình server MariaDB trước/sau (`EXPLAIN FORMAT=JSON` hoặc EXPLAIN có `rows`, `key`, temp/filesort); đặc biệt filter canonical và legacy, hot page/COUNT, slot org nhiều.
2. Ghi bộ fixture đại diện (Company count; Contacts; Province/Ward; active/inactive state; DENY lanes) và load p50/p95 **trong môi trường cho phép**, đối chiếu đúng quyền và total.
3. Nếu chưa có EXPLAIN/HTTP PBAC thực tế thì trạng thái phải ghi **PENDING**, dù SQL source/pure test PASS. Không tuyên bố đã tăng tốc bao nhiêu phần trăm từ kiểm tra tĩnh.
4. Legacy golden-master đã pin checksum chỉ thay bằng change record được review; không cập nhật snapshot làm đẹp báo cáo.
