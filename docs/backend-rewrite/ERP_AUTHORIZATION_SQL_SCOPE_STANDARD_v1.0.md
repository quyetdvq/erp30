# DinkERP — Chuẩn Authorization SQL Scope & Effective Action Hints

**Mã tài liệu:** ERP-ARCH-AUTH-QUERY-001  
**Phiên bản:** 1.0 · 25/10/2026  
**Phạm vi:** Backend CodeIgniter 4, Frontend Vue, toàn bộ resource hiện hành và tương lai  
**Trạng thái:** Chuẩn thiết kế đề xuất áp dụng toàn dự án; **chưa phải báo cáo triển khai mã nguồn**  
**Tương thích mục tiêu:** OpenAPI v1.2.4 (150/150 operations), không tự ý thay đổi response contract

## 1. Quyết định kiến trúc

1. **Mọi truy vấn đọc danh sách phải áp dụng quyền `VIEW` ở SQL `WHERE`/`JOIN` trước `COUNT`, tìm kiếm, sắp xếp, `LIMIT` và `OFFSET`.** Compiler phải tái hiện đầy đủ kết quả PBAC canonical; không được giả lập quyền chỉ bằng `ALLOW JOIN`, `status` hay `organization_id` đơn lẻ.
2. **Tiếp tục trả `allowed_actions` theo từng bản ghi.** Đây là *effective action hint* cho frontend, không phải chính sách PBAC gốc và không thay thế kiểm tra quyền tại mutation endpoint.
3. **Tối ưu việc tính `allowed_actions` bằng cách nhóm các bản ghi có *toàn bộ ngữ cảnh phân quyền tương đương*, rồi cache quyết định trong cùng request.** Với các hành động phụ thuộc thuộc tính riêng của từng bản ghi, vẫn phải đánh giá từng bản ghi nhưng chỉ trên những dòng của trang kết quả.
4. **Không áp dụng object `policies` ở cấp response chỉ khóa theo State** (`ACTIVE`, `INACTIVE`, `DRAFT`) cho API chứa nhiều công ty. Cùng State nhưng công ty khác nhau có thể có quyền khác nhau. Duy trì hợp đồng trả về hiện tại và chỉ cân nhắc một cơ chế profile tham chiếu khi có phiên bản API mới và benchmark chứng minh lợi ích.
5. **Fail closed:** nếu không thể chứng minh predicate SQL tương đương PBAC, không được chạy SELECT không giới hạn quyền hoặc phân trang rồi lọc quyền trong PHP. Phải trả lỗi có kiểm soát, log/metric nội bộ và chờ triển khai scope provider phù hợp.

**Phân biệt hai mục tiêu:** SQL Scope bảo vệ *tập bản ghi có thể xem*; `allowed_actions` giúp UI quyết định *các thao tác có thể thử trên từng bản ghi đã được phép xem*. Hai tầng có ngữ nghĩa và thời điểm áp dụng khác nhau.

## 2. Căn cứ từ mã nguồn hiện có

| Thành phần | Quan sát từ source/patch hiện tại | Yêu cầu chuẩn hóa |
|---|---|---|
| `UnifiedAccessService` / `PbacDecision` | Quyết định theo actor, resource/action, State, ancestry tổ chức, assignment, group và policy lanes; có `ALLOW`, `DENY`, `ABSOLUTE_DENY`. | Là nguồn chân lý duy nhất; compiler phải cho kết quả tương đương. |
| `ProductReadService::list()` | Đọc toàn bộ `products` qua `allRows()`, rồi `canView()` và `withAllowedActions()` cho từng Product, mới gọi `AuthorizedListPage::paginate()`. | Chuyển SQL-first; tính hint sau khi lấy trang. |
| M6.4A.1 — Product Lookup | `viewableMasterLifecycleStatuses()` biên dịch quyền VIEW theo State cho `product`/`product_uom` thuộc `MASTER`. | Tái sử dụng cho các resource phù hợp, nhưng không mặc định áp dụng toàn bộ resource. |
| M7.3 — Pricing | `price_list` thuộc `ORGANIZATION`, lọc SQL theo cặp `(organization_id, State)`; hiện có giới hạn tối đa 512 bucket và phát hiện bucket từ dữ liệu bảng. | Chuyển về compiler chung có khả năng mở rộng; không dựa vĩnh viễn vào `GROUP BY` toàn bảng. |
| `ProductReadService::withAllowedActions()` | Hiện trả `edit` và `setstate` bằng `decideMutation()` trên mỗi dòng được VIEW. | Giữ semantics/shape khi tối ưu. |
| `AuthorizedListPage` | Là helper in-memory cho các dòng **đã được authorize**; không phải cơ chế SQL pagination. | Các API list có dữ liệu lớn dùng repository SQL-scoped; helper cũ chỉ giữ cho kiểm thử/đường đọc nhỏ đã được duyệt. |

Các file tham chiếu: `backend/app/Modules/Access/{UnifiedAccessService,AccessDecisionCore,PbacDecision,RuntimeActorReader,RuntimePolicyReader,ResourceStateRepository}.php`, `backend/app/Modules/Product/Master/{ProductReadService,ProductReadRules}.php`, `backend/app/Shared/Http/AuthorizedListPage.php`; các patch M6.4A.1, M7.3 và tài liệu QA đi kèm. **Các mô tả thiết kế bên dưới là đích cần triển khai, không có nghĩa repository hiện đã dùng chuẩn mới cho mọi API.**

## 3. Khái niệm và bất biến bắt buộc

- **Actor snapshot:** user Shield đã xác thực; base permissions; ERP groups đang ACTIVE; employee/assignments còn hiệu lực ở thời điểm UTC đã chốt; phạm vi ancestry của tổ chức. Không nhận `user_id`, group, assignment hoặc scope từ JSON của client.
- **Persisted resource facts:** `id`, State canonical, `organization_id` hoặc scope resolver tin cậy, parent/owner/lock/workflow facts cần cho quyết định. State và tổ chức phải lấy từ database chứ không lấy từ filter người dùng.
- **Canonical decision:** `UnifiedAccessService` + `AccessDecisionCore` + `PbacDecision` và State/Policy repositories. Phải giữ nguyên *chính xác* luật các policy lanes hiện có; không thay thế bằng giả định “DENY luôn thắng” hoặc “có ALLOW là được”.
- **Authorized SQL Scope:** predicate/bộ bảng quan hệ được backend biên dịch để lấy đúng các record mà canonical VIEW cho phép, không lộ ID, đếm hay quan hệ của record bị ẩn.
- **Effective action hint:** boolean `allowed_actions` cho một record đã VIEW. Đó là gợi ý hiển thị tại thời điểm response; quyền thực thi vẫn được kiểm tra lại trên dữ liệu mới nhất tại mutation endpoint.

Bất biến cốt lõi cần kiểm chứng với cùng actor và cùng snapshot:

```text
For every persisted row r of resource R:
    SQL_SCOPE_VIEW(R, actor, snapshot, r)
      == UnifiedAccessService.canView(R, r)

COUNT = count(rows satisfying SQL_SCOPE_VIEW AND user filters)
PAGE  = deterministic_order(rows satisfying SQL_SCOPE_VIEW AND user filters)
        followed by LIMIT/OFFSET
```

Khi SQL trả về trang dữ liệu, vẫn thực hiện **bounded `canView()` cross-check** với từng bản ghi của trang. Nếu phát hiện sai lệch, **fail closed toàn response** và ghi metric; không tự bỏ dòng bị sai lệch rồi trả `total`/trang không nhất quán.

## 4. Domain và chiến lược SQL Scope

| Resource domain | Ngữ cảnh PBAC phải xét | Chiến lược SQL | Điều cấm |
|---|---|---|---|
| `SYSTEM` | Root/actor/action/State theo resource; các ngoại lệ scope riêng. | Profile được duyệt riêng theo resource; query bằng State/context khi có chứng minh tương đương. | Không mặc định mọi SYSTEM record đều có cùng quyền chỉ vì cùng State. |
| `MASTER` | Root `GROUP`, actor/action, canonical State. | Có thể `WHERE status IN (allowed states)` **chỉ khi** resource không có điều kiện phân quyền theo từng record. | Không mang tối ưu State-only sang `ORGANIZATION` hoặc resource MASTER về sau có record-level rules. |
| `ORGANIZATION` | `organization_id` của record (hoặc `id` đối với Organization), ancestry `GROUP → COMPANY → ...`, effective assignments, participant policies và State. | Biên dịch **cặp tổ chức/State được phép** rồi áp vào SQL qua indexed JOIN/EXISTS hoặc derived relation. | Không dùng `WHERE status IN (...)` đơn độc; không coi quyền ở công ty A suy ra quyền ở B. |
| Resource tham chiếu/tổng hợp | Scope của record cha **và** của record liên quan được đưa vào projection/aggregate. | Áp riêng scope cho từng nguồn dữ liệu nhạy cảm **trước** `COUNT`, `GROUP BY`, window functions và LIMIT. | Không để child bị ẩn đóng góp vào total, relation count hoặc xuất hiện gián tiếp trong DTO. |
| Resource có resolver đặc biệt | Ví dụ Contact đa hình, Authorization Log với scope tổ chức null, record mà scope phải suy ra từ quan hệ. | Bắt buộc `ResourceScopeProvider` khai báo mapping tin cậy, có unit tests. | Không suy diễn `organization_id` từ query parameter hoặc dữ liệu nhập client. |

**Nhiều công ty:** snapshot của người dùng có thể chứa nhiều assignment ở các nhánh tổ chức khác nhau; compiler phải đánh giá chính sách cho từng ngữ cảnh ancestry cụ thể. `ORGANIZATION` không được thực hiện phép hợp (`OR`) vô điều kiện các assignment rồi bỏ qua luật policy lanes.

**Khả năng mở rộng:** cặp `(organization_id, canonical State)` phù hợp làm dạng trung gian khi lượng tổ chức/State có hạn. Đối với hệ thống nhiều công ty và hàng nghìn scope, provider phải chuyển sang quan hệ `authorized_scope_slots` được biên dịch từ metadata tổ chức/assignment/policy (JOIN, CTE an toàn hoặc bảng tạm theo request), **không quét toàn bộ bảng nghiệp vụ để suy ra bucket**. Khi chưa có phương án có chứng minh tương đương, lỗi `AUTH_SCOPE_COMPILATION_UNSUPPORTED` thay cho nới quyền.

## 5. Thiết kế framework dùng chung

```text
Authenticated HTTP request
  → ActorAuthorizationSnapshot (request-scoped, trusted)
  → ResourceScopeProvider (SYSTEM/MASTER/ORGANIZATION/special)
  → AuthorizationSqlScopeCompiler (READ/VIEW)
  → ScopedRepository (WHERE + query filters)
       ├─ COUNT authorized rows
       └─ SELECT authorized rows ORDER BY ... LIMIT ... OFFSET ...
  → bounded canonical PBAC cross-check for returned page
  → EffectiveActionHints (policy-context grouping + request cache)
  → Resource DTO projection and API responder

Mutation request
  → persisted row reload / transaction lock
  → canonical decideMutation + business/workflow checks
  → perform mutation + audit atomically when applicable
```

### 5.1 Các interface/contract đề xuất (pseudo-PHP)

```php
interface ResourceScopeProvider {
    public function domain(): ResourceDomain;
    public function trustedScopeFields(): array; // persisted DB columns only
    public function compileView(
        ActorAuthorizationSnapshot $actor,
        ResourceStateDefinition $states,
        DatabaseMetadata $db
    ): AuthorizedSqlScope;
}

interface AuthorizedSqlScope {
    public function applyTo(ScopedQueryBuilder $query, string $alias): void;
    public function fingerprint(): string;   // request-local; not public
    public function isProvenEquivalent(): bool;
}

interface EffectiveActionHintProvider {
    public function hintsForPage(
        ActorAuthorizationSnapshot $actor,
        string $resource,
        array $authorizedPersistedRows,
        array $actions
    ): array; // map by record ID, preserve per-row output shape
}
```

Các interface là **quy chuẩn thiết kế**, không phải class/API đã tồn tại. `AuthorizationSqlScopeCompiler` phải thuộc module **Access**, còn `ResourceScopeProvider` của từng resource thuộc module sở hữu nghiệp vụ. `ScopedRepository` được module đó sử dụng; Frontend không biết câu SQL hay policy IDs.

### 5.2 Trình tự bắt buộc cho LIST/DETAIL/AGGREGATE

1. Xác thực actor; chốt snapshot của identity, assignment, group, State/policy và thời gian đánh giá. Kiểm tra base `resource.view` và trạng thái resource.
2. Resolver xác định domain và các trường scope chuẩn từ DB; compiler dùng canonical PBAC để tạo predicate/bảng phạm vi, bind tham số, deny-by-default khi bất thường.
3. SQL áp **scope ∧ filters của người dùng**; `total`, sort, pagination lấy trên cùng tập kết quả, ổn định theo ID làm tie-breaker.
4. SELECT chỉ những cột cần thiết, JOIN/EXISTS có index để dựng role/labels; không dùng `SELECT *` toàn bảng chỉ để tính metadata cho 15 record.
5. Cross-check 15 (hoặc `page_size` giới hạn) bản ghi đã lấy bằng canonical `canView()`. Sai lệch là **lỗi bảo mật**, không phải một bộ lọc hậu kỳ có thể bỏ qua.
6. Tính `allowed_actions` trên page rồi build response theo OpenAPI, không phát tán internal policy IDs/scope profile keys.
7. Các API relation, search, export, aggregate và selector phải áp dụng cùng nguyên tắc cho **mọi nguồn dữ liệu** trước khi đếm hoặc cộng gộp.

Truy vấn `COUNT` và SELECT trang phải nhìn vào **cùng snapshot dữ liệu/quyền**; dùng transaction read-only với mức isolation phù hợp hoặc cơ chế snapshot/version đã được duyệt. Nếu quyền đổi giữa request, không được dùng kết quả cache từ request cũ. Import/job dài hạn phải kiểm tra lại quyền trước mỗi mutation/transaction.

## 6. Phương án cuối cho `allowed_actions`

| Phương án | Chính xác với nhiều công ty? | Chi phí | Quyết định |
|---|---|---|---|
| Kiểm tra từng dòng **trước** phân trang | Có thể đúng | O(N) theo toàn catalog; không tối ưu | Loại bỏ khỏi LIST lớn. |
| `policies` ở response chỉ khóa theo `status` | **Không** | Payload nhỏ | **Không áp dụng**. |
| `policies` khóa theo `(company, status)` | Chỉ đúng nếu không có điều kiện record-specific và assignment bổ sung | Phải thay contract/UI; cấu trúc phức tạp | Chưa cần cho API hiện tại. |
| **`allowed_actions` theo từng row, tính theo nhóm ngữ cảnh tin cậy trên page** | **Có**, nếu nhóm đúng toàn bộ dependency và fallback per-record | Bounded theo page, request-cache | **Chọn làm chuẩn**. |

### 6.1 Quy tắc nhóm quyết định hiệu quả

Không nhất thiết phải gọi `decideMutation()` hai lần cho mỗi record nếu các record thực sự có cùng ngữ cảnh. Nhưng **chìa khóa cache không được chỉ là State**. Nó phải bao gồm *tất cả* thành phần có thể thay đổi kết quả quyền:

```text
actor/request snapshot + resource type + action + domain
+ canonical State + authoritative evaluation organization
+ ancestor-chain/assignment-context + policy revision
+ resource-specific authorization facts (when applicable)
```

- Với `MASTER` hiện tại như `product`, PBAC grant có thể nhóm theo `State` trong cùng actor/request **sau khi** provider xác nhận không có predicate riêng theo Product. Nếu có business/workflow conditions riêng, kiểm tra chúng từng record.
- Với `ORGANIZATION` như `price_list`, ít nhất phải nhóm theo **tổ chức hiệu lực + State + action**; ancestry, assignment và các participant lanes phải đúng. Nếu quyền phụ thuộc owner/department/resource flags, mở rộng signature hoặc đánh giá riêng.
- Với workflow: `setstate: true` trong contract hiện tại là **quyền thử action**, không hứa chắc một transition hợp lệ đang tồn tại; server vẫn kiểm tra transitions, locks và business guards khi thực thi. Nếu frontend cần danh sách transitions thực sự hợp lệ, thiết kế contract riêng, không đổi âm thầm semantics `allowed_actions`.
- Cache chỉ trong request/transaction và chỉ khi có bằng chứng nhóm tương đương. Không cache toàn cục theo `status`, `user_id` hoặc `company_id` mà thiếu invalidation cho policy/assignment changes.

**Phần payload tiết kiệm được bằng việc thay 2–3 boolean mỗi row bằng `policies` ở cấp response là rất nhỏ so với việc giảm đọc toàn bảng và PBAC trên hàng nghìn bản ghi.** Không đáng đánh đổi độ đúng quyền và độ ổn định hợp đồng API.

### 6.2 Ví dụ hai công ty với cùng `ACTIVE`

Một người dùng có quyền `price_list.view` ở **Công ty A** và **Công ty B**, nhưng chỉ được `edit` ở A. Cả hai bảng giá đều `ACTIVE`:

```json
{
  "data": [
    {
      "id": "101",
      "organization_id": "10",
      "status": "ACTIVE",
      "allowed_actions": { "edit": true, "setstate": true }
    },
    {
      "id": "202",
      "organization_id": "20",
      "status": "ACTIVE",
      "allowed_actions": { "edit": false, "setstate": false }
    }
  ],
  "meta": { "page": 1, "page_size": 15, "total": 2 }
}
```

Đây là **ví dụ đề xuất về hình thức hint**, không khẳng định API Price List hiện tại đã trả đủ các key minh họa. Một object `"policies": {"ACTIVE": {"edit": true}}` không biểu diễn nổi hai record trên. Nếu client dựa vào nó, icon Edit có thể xuất hiện sai trên Công ty B dù server vẫn từ chối lúc submit.

**Lưu ý đối với Product:** theo domain mapping hiện tại, `product` là `MASTER` với scope GROUP root, không phải mỗi Product có một `organization_id` để biên dịch quyền công ty. Kịch bản đa công ty điển hình nằm ở `price_list`, `organization`, `employee_assignment`, `policy`, `import_job` và những resource `ORGANIZATION`. Nếu Product sau này trở thành company-specific, phải thay đổi resolver/domain contract theo thiết kế có phiên bản, không âm thầm tận dụng State-only.

### 6.3 Response contract được chọn

```json
{
  "data": [
    {
      "id": "1",
      "status": "ACTIVE",
      "allowed_actions": { "edit": false, "setstate": false }
    }
  ],
  "meta": {
    "page": 1,
    "page_size": 15,
    "total": 1469,
    "request_id": "..."
  }
}
```

- Giữ `data[].allowed_actions` theo OpenAPI của từng resource; action nào có sẵn phải trả boolean đúng, không phát minh `delete` cho Product nếu contract chưa định nghĩa.
- Không gửi đối tượng top-level `policies` chỉ theo State vào response hiện tại; từ `policies` cũng dễ nhầm với policy definitions nội bộ.
- Frontend **chỉ dùng hint để ẩn/hiện/disable icon**; backend luôn kiểm tra lại quyền và dữ kiện nghiệp vụ khi nhận lệnh. Không coi hint là quyền đã được cấp vĩnh viễn.
- Nếu trong tương lai payload thực sự lớn, có thể thiết kế `capability_profiles` + `capability_ref` *với phiên bản hợp đồng mới*, mỗi profile đã là **effective decision** theo ngữ cảnh, không phải một PBAC policy trần. Chỉ thay đổi sau benchmark và kiểm thử đa công ty/thu hồi quyền.

## 7. Quy tắc SQL chuẩn và các trường hợp đặc biệt

**MASTER (chỉ với provider đã chứng minh quyền VIEW phụ thuộc State):**

```sql
SELECT p.id, p.code, p.status
FROM products p
WHERE BINARY p.status IN (/* bound allowed canonical States */)
ORDER BY p.id DESC
LIMIT ? OFFSET ?;
```

**ORGANIZATION (minh họa quan hệ scope có các cặp đã được PBAC cho phép):**

```sql
SELECT p.id, p.organization_id, p.status, p.name
FROM price_lists p
JOIN authorized_scope_slots s
  ON s.organization_id = p.organization_id
 AND s.state_code = p.status
WHERE /* other bound user filters */
ORDER BY p.id DESC
LIMIT ? OFFSET ?;
```

`authorized_scope_slots` là **một quan hệ logic đã biên dịch từ PBAC**, không giả định có bảng vật lý cùng tên. Nếu triển khai bằng derived table/CTE hoặc temporary table, cần bind tham số, bảo đảm unique `(organization_id, state_code)` để tránh nhân bản record và giữ chính xác `COUNT`. `BINARY`/collation phải được kiểm thử để bảo toàn trạng thái canonical **và** không làm mất khả năng dùng index một cách không cần thiết.

Yêu cầu bổ sung:

- Chỉ bind value; whitelist identifier, sort fields và table prefix từ cấu hình backend. Không nhúng SQL từ policy text/query string của client.
- User filter không được nới rộng authorization filter. Đếm trước hoặc sau khi áp scope sai thứ tự sẽ rò rỉ số lượng record ẩn.
- `JOIN` với các resource khác phải áp scope VIEW tương ứng trước projection; aggregate và window count phải loại dữ liệu bị ẩn **trước** `GROUP BY`, `COUNT`, `ROW_NUMBER`.
- `GET /resource/{id}` cần điều kiện `id = ? AND authorized_scope`; không gọi full-catalog chỉ để tính full name/parent-role.
- Tên file ảnh, thông tin nhạy cảm, liên kết tới record cấm xem phải đi qua projection/field masking được duyệt; SQL VIEW một mình chưa đủ bảo vệ field-level data.
- Sắp xếp tất định với `id` tie-breaker. Trường `full_name` và search tiếng Việt phải kiểm thử parity giữa SQL và cách tính/so sánh trước đây; `LIKE '%q%'` và `UPPER(TRIM(column))` có thể làm index khó sử dụng.

## 8. Hiệu năng, tính nhất quán và telemetry

**Mục tiêu** là làm chi phí đọc/authorize của LIST phụ thuộc chủ yếu vào số dòng trả về và kích thước scope được phép, thay vì số Product/Employee/Price List toàn hệ thống. Không mặc định tăng tốc một tỷ lệ cụ thể khi chưa đo.

| Chỉ số cần đo | Yêu cầu nghiệm thu |
|---|---|
| DB rows examined / query | Không còn quét toàn catalog trong PHP; xem `EXPLAIN`/`EXPLAIN ANALYZE` ở dataset có đại diện. |
| `COUNT` + page query | Cùng scope và user filters, cùng snapshot; `total` chính xác hậu PBAC. |
| Application memory | Không tỷ lệ tuyến tính với toàn bộ catalog cho endpoint phân trang. |
| Query count | Không N+1 cho lookup parent, child, allowed_actions, policy mỗi dòng. |
| P50/P95 latency | Benchmark trước/sau trên cùng dữ liệu và quyền, có warm-up và các bộ lọc/sort điển hình. |
| Unauthorized leaks | Zero ID, relation count hoặc aggregate chứa record không được VIEW. |
| Scope compiler cost | Quan sát số scope slots, thời gian compile, số chính sách; có ngưỡng cảnh báo và cách mở rộng có kiểm soát. |
| Index & collation | Kiểm tra `products(id,status,parent_product_id)`, `price_lists(organization_id,status,id)` và index tương ứng cho các resource lớn; chỉ thêm index sau EXPLAIN. |

Không cache kết quả quyền giữa requests nếu không có versioning/invalidation cho RBAC, PBAC, assignments, workflow states và tổ chức. Không đưa internal policy IDs hay phân tích ABSOLUTE_DENY vào response cho user bình thường. Mọi metric/log có thể ghi `resource`, action, elapsed, scope-size và mismatch count, nhưng không ghi dữ liệu bí mật hoặc toàn bộ row.

## 9. Ma trận kiểm thử bắt buộc

| ID | Kịch bản | Kết quả đúng |
|---|---|---|
| AUTH-SQL-01 | Không có base `resource.view` | 403 theo contract hoặc danh sách rỗng theo contract cụ thể; không lộ total. |
| AUTH-SQL-02 | Resource INACTIVE hoặc employee không hợp lệ | Fail closed như canonical engine. |
| AUTH-SQL-03 | `ACTIVE`, `INACTIVE`, `DRAFT`, custom Lifecycle State | SQL scope == `canView` trên mọi State hợp lệ. |
| AUTH-SQL-04 | ALLOW + DENY cùng nhóm/lane; ABSOLUTE_DENY | Kết quả bằng `PbacDecision` canonical, không tự định nghĩa precedence khác. |
| AUTH-SQL-05 | Hai công ty A/B cùng State, edit khác nhau | VIEW/COUNT và `allowed_actions` đúng trên từng row. |
| AUTH-SQL-06 | User có nhiều assignments và scope ancestor `GROUP→COMPANY→BRANCH` | Đánh giá riêng mỗi ngữ cảnh; không mở rộng scope ngoài assignment. |
| AUTH-SQL-07 | Tổ chức bị di chuyển, quyền bị thu hồi, assignment hết hạn | Request sau phản ánh quyền mới; mutation re-check. |
| AUTH-SQL-08 | Pagination page 1/2, nhiều bản ghi bị DENY | `total` hậu PBAC; trang đủ số dòng khi còn dữ liệu; stable sort. |
| AUTH-SQL-09 | `GET /id` record ẩn/không tồn tại | Không tiết lộ dữ liệu hay existence ngoài contract. |
| AUTH-SQL-10 | Parent/Child/COMBO, aggregate/count, PriceList với Product ẩn | Không rò ID, nhãn hay đếm record bị ẩn. |
| AUTH-SQL-11 | Query sai/SQL injection, sort không whitelist | 400; không thể sửa scope và SQL binding. |
| AUTH-SQL-12 | Compile quá phức tạp hoặc cross-check khác nhau | Fail closed; telemetry; tuyệt đối không chuyển sang query unscoped. |
| AUTH-SQL-13 | 50k–500k records, nhiều công ty/trạng thái | EXPLAIN, memory, query count, P95 được đo và so sánh. |
| AUTH-SQL-14 | Frontend icon & mutation racing/revoke | Hint có thể cũ; server từ chối đúng khi action không còn hợp lệ. |
| AUTH-SQL-15 | Scope của resource `SYSTEM`, `MASTER`, `ORGANIZATION` và đặc biệt | Provider duyệt rõ resource-domain; unit fixtures parity. |

Cần đối chiếu **Oracle = `UnifiedAccessService` trên các record persisted** với SQL scope trong DEV fixture đa công ty. Sau đó chạy HTTP authenticated bằng user có quyền khác nhau, và cuối cùng đo trên MariaDB thật. Source-only tests không thay cho các bằng chứng này.

## 10. Lộ trình áp dụng toàn dự án

| Giai đoạn | Deliverable | Điều kiện hoàn tất |
|---|---|---|
| **A — Shared Access infrastructure** | Actor snapshot, `ResourceScopeProvider`, compiler, request cache, reusable scoped query helpers. | Parity tests canonical VIEW theo ba domain và mẫu đa công ty. |
| **B — M8.5 Product List/Detail** | Thay `allRows()`/in-memory pagination bằng SQL-scoped COUNT + SELECT, JOIN/EXISTS projection, post-page hints. | `GET /products` + `GET /products/{id}` đúng OpenAPI, PBAC, DTO và EXPLAIN. |
| **C — Audit các module đã có** | Inventory mọi `list()/show()/resolve()/export()/aggregate()`: Organization, People, Contact, Catalog, Pricing, M8 jobs, Access Management... | Mỗi resource có provider được duyệt hoặc documented exception; không unscoped paging. |
| **D — Frontend validation** | Giữ `allowed_actions` theo row, dùng hints hiển thị; không gọi API permission per icon. | Cross-company UI tests, contract snapshots, mutation reauthorization. |
| **E — Quality gates & rollout** | Static source gate, PBAC parity suite, HTTP/MariaDB, EXPLAIN, logs/alerts. | No-leak; total/count parity; runtime checks; production gate được quyết định riêng. |
| **F — Resource mới** | Template module yêu cầu khai báo domain, scope provider, row facts, permitted actions và test matrix. | PR không được merge nếu chưa có authorization-scope acceptance. |

**Các API đã có SQL pushdown cần rà soát, không xóa bỏ:** M6.4A.1 Product Lookups, M7.3 Pricing, M7.4 Relationship Lookups. Đặc biệt M7.3 hiện sử dụng bucket discovery từ bảng nghiệp vụ (giới hạn 512); nên chuyển về compiler dùng chung có chiến lược scale-out, không nhân rộng cách đó nguyên xi.

## 11. Checklist bắt buộc khi review PR cho mọi resource

- [ ] `resource/domain` và source của organization/State được mô tả rõ, lấy từ persisted facts.
- [ ] `VIEW` được áp chính xác trong SQL trước COUNT/page/aggregate; không có hậu lọc để “vá” trang.
- [ ] Scope compiler dùng cùng semantic PBAC canonical, gồm assignments, policy lanes, `ABSOLUTE_DENY`, parent organizations.
- [ ] Không có fallback SELECT unscoped nếu compile thất bại; có metric mismatch.
- [ ] Query bind an toàn, sort/identifier whitelist, stable tie-breaker, snapshot consistency.
- [ ] `allowed_actions` được tính **chỉ cho các row VIEW trên trang**, cache request-local theo full authorization-context hoặc fallback per-record.
- [ ] UI chỉ dùng hint, mọi mutation kiểm tra lại trên persisted/locked row, không tin vào hint.
- [ ] Cross-company test có cùng State nhưng quyền khác nhau; negative tests không lộ count/ID.
- [ ] EXPLAIN + index review + benchmark với dataset có đại diện, không tuyên bố nhanh hơn nếu chưa đo.
- [ ] OpenAPI compatibility và baseline parity được bảo toàn, mọi thay đổi schema/response có versioning độc lập.

## 12. Quyết định triển khai và giới hạn hiện tại

**Chốt lựa chọn:** `SQL VIEW scope` tập trung tại Access + `ResourceScopeProvider` theo domain/owner module + **`allowed_actions` per record** với phép tính/caching theo nhóm ngữ cảnh hoàn chỉnh **sau pagination**. **Không** chuyển sang `policies` chỉ theo State ở response cấp danh sách.

Tài liệu này định nghĩa chuẩn cho **M8.5 và toàn bộ các module hiện tại/tương lai**. Nó **không tự thay đổi mã nguồn**, không chứng minh runtime SQL/PBAC đã PASS, và không mở khóa production. Các ngoại lệ QA trước đó (ví dụ concurrency/avatar đã miễn) vẫn phải được ghi đúng trạng thái, không đổi thành PASS.
