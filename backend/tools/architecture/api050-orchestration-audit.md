# BE-REORG-014E — API-050 Remaining Orchestration Audit

**Mã:** `BE-REORG-014E` · **Ngày:** 22/10/2026 · **Nền:** ERP `v1.2.2` + `BE-ARCH-001` + `BE-REORG-014C` + `BE-REORG-014D`.
**Chuẩn tham chiếu:** `DINKERP-BE-ARCH-001 v1.1.1` (§0.1, §3, §6–9, §15, §17–20), trạng thái **PROPOSED BASELINE**.
**Loại:** `SOURCE-OBSERVED` / MDR chuyển tiếp `DRAFT`, **không phải** approval kiến trúc/API/DB và **không** chứng nhận runtime/performance.

## 1. Kết luận kiểm kê

- **26/26** đường HTTP `Api\ProductCatalog` được khai báo trong `backend/app/Config/Routes.php`, được Controller gọi vào `Api050ProductCatalogService`, và có `operationId` trong `contracts/openapi/openapi.yaml`.
- **19 operations thuộc Product**, **7 operations thuộc Pricing**. Hai collection operations **đã tách** orchestration: `listProducts` → `ProductListQueryService` (**014C**), `listPriceLists` → `PriceListQueryService` (**014D**).
- **Cả 26 operations vẫn đi qua legacy API facade** để giữ tương thích Controller. **24 operations chưa được chuyển query orchestration bởi 014C/014D**; *không đồng nghĩa có 24 logic phải di chuyển*: `replaceConversions`, `get/replaceComboItems`, `get/replacePriceListItems` đã delegate xuống module owner; các class Product/Pricing đang đảm nhận domain validation/PBAC/locks/SQL.
- Hai public PHP entrypoints **không thuộc 26 HTTP operations** là `productReadAccess()` (caller: `Bootstrap/ProductMediaFactory`) và `productForImage()` (caller: `Controllers/Api/ProductImages`); phải giữ compatibility cho Product Media.
- `createProduct()` với `combo_items` là **KIR-001/P0**: facade mở transaction trước khi gọi `ProductMasterService::create`, còn `AutoNumberingCounterAllocator::allocate` từ chối `transDepth > 0`. Chưa có test matrix xác minh đầy đủ; **KHÔNG chuyển transaction owner** trong 014E.
- `Api050::operation()` sinh `OperationContext` từ `MutationContext::begin()` khi gọi; một command tạo Product/COMBO có thể capture nhiều `evaluationTime`. **Đây là gap đã ghi trong Standard**, chưa kết luận hỏng dữ liệu production; cần characterization tests trước khi sửa.

**Thay đổi của 014E:** chỉ thêm báo cáo, manifest JSON, gate kiểm kê vendor-free và link MDR. **Không sửa** `backend/app`, SQL, auth rules, routes, OpenAPI, migration hay API behavior.

## 2. Bảng ánh xạ đầy đủ 26 HTTP operations

`facade_method :NN` là dòng phương thức trong `backend/app/Services/Api050ProductCatalogService.php` tại snapshot sau 014D; số dòng có thể dịch chuyển ở PR sau. `refactor_risk` là **đánh giá rủi ro của việc di chuyển boundary**, không phải chứng nhận bug đang xảy ra.

| # | Route | OpenAPI `operationId` | Facade method/dòng | Owner/capability | Loại | Hiện trạng điều phối | Rủi ro tách |
|---:|---|---|---|---|---|---|---|
| 01 | `GET` `/api/v1/product-groups` | `listProductGroups` | `listGroups` :60 | Product / ProductGroup | Đọc | `LEGACY_FACADE_LIST` | `HIGH` |
| 02 | `POST` `/api/v1/product-groups` | `createProductGroup` | `createGroup` :88 | Product / ProductGroup | Ghi | `FACADE_NORMALIZE_OWNER_CREATE` | `MEDIUM` |
| 03 | `GET` `/api/v1/product-groups/(:num)` | `getProductGroup` | `getGroup` :96 | Product / ProductGroup | Đọc | `FACADE_AUTHORIZED_SHOW` | `MEDIUM` |
| 04 | `PATCH` `/api/v1/product-groups/(:num)` | `updateProductGroup` | `updateGroup` :106 | Product / ProductGroup | Ghi | `OWNER_COMMAND_FACADE_READBACK` | `MEDIUM` |
| 05 | `POST` `/api/v1/product-groups/(:num)/actions/set-state` | `setProductGroupState` | `setGroupState` :113 | Product / ProductGroup | Ghi | `OWNER_WORKFLOW_FACADE_READBACK` | `HIGH` |
| 06 | `GET` `/api/v1/product-uoms` | `listProductUoms` | `listUoms` :122 | Product / Uom | Đọc | `LEGACY_FACADE_LIST` | `HIGH` |
| 07 | `POST` `/api/v1/product-uoms` | `createProductUom` | `createUom` :144 | Product / Uom | Ghi | `FACADE_NORMALIZE_OWNER_CREATE` | `MEDIUM` |
| 08 | `GET` `/api/v1/product-uoms/(:num)` | `getProductUom` | `getUom` :152 | Product / Uom | Đọc | `FACADE_AUTHORIZED_SHOW` | `MEDIUM` |
| 09 | `PATCH` `/api/v1/product-uoms/(:num)` | `updateProductUom` | `updateUom` :162 | Product / Uom | Ghi | `OWNER_COMMAND_FACADE_READBACK` | `MEDIUM` |
| 10 | `POST` `/api/v1/product-uoms/(:num)/actions/set-state` | `setProductUomState` | `setUomState` :169 | Product / Uom | Ghi | `OWNER_WORKFLOW_FACADE_READBACK` | `HIGH` |
| 11 | `GET` `/api/v1/products` | `listProducts` | `listProducts` :178 | Product / Product | Đọc | `DELEGATED_014C` | `LOW` |
| 12 | `POST` `/api/v1/products` | `createProduct` | `createProduct` :203 | Product / Product | Ghi | `FACADE_MULTI_STEP_CREATE` | `STOP_KIR_001` |
| 13 | `GET` `/api/v1/products/(:num)` | `getProduct` | `getProduct` :260 | Product / Product | Đọc | `FACADE_AUTHORIZED_DECORATED_SHOW` | `MEDIUM` |
| 14 | `PATCH` `/api/v1/products/(:num)` | `updateProduct` | `updateProduct` :279 | Product / Product | Ghi | `OWNER_COMMAND_FACADE_READBACK` | `HIGH` |
| 15 | `POST` `/api/v1/products/(:num)/actions/set-state` | `setProductState` | `setProductState` :286 | Product / Product | Ghi | `OWNER_WORKFLOW_FACADE_READBACK` | `HIGH` |
| 16 | `GET` `/api/v1/products/(:num)/uom-conversions` | `listProductUomConversions` | `getConversions` :293 | Product / UomConversion | Đọc | `FACADE_VIEW_GATE_OWNER_READ` | `MEDIUM` |
| 17 | `PUT` `/api/v1/products/(:num)/uom-conversions` | `replaceProductUomConversions` | `replaceConversions` :303 | Product / UomConversion | Ghi | `DELEGATED_OWNER_COMMAND` | `HIGH` |
| 18 | `GET` `/api/v1/products/(:num)/combo-items` | `listComboItems` | `getComboItems` :379 | Product / Combo | Đọc | `DELEGATED_OWNER_AUTHORIZED_READ` | `MEDIUM` |
| 19 | `PUT` `/api/v1/products/(:num)/combo-items` | `replaceComboItems` | `replaceComboItems` :387 | Product / Combo | Ghi | `DELEGATED_OWNER_ATOMIC_REPLACE` | `HIGH` |
| 20 | `GET` `/api/v1/price-lists` | `listPriceLists` | `listPriceLists` :312 | Pricing / PriceList | Đọc | `DELEGATED_014D` | `LOW` |
| 21 | `POST` `/api/v1/price-lists` | `createPriceList` | `createPriceList` :332 | Pricing / PriceList | Ghi | `FACADE_NORMALIZE_OWNER_CREATE_READBACK` | `MEDIUM` |
| 22 | `GET` `/api/v1/price-lists/(:num)` | `getPriceList` | `getPriceList` :340 | Pricing / PriceList | Đọc | `FACADE_AUTHORIZED_SHOW` | `MEDIUM` |
| 23 | `PATCH` `/api/v1/price-lists/(:num)` | `updatePriceList` | `updatePriceList` :351 | Pricing / PriceList | Ghi | `OWNER_COMMAND_FACADE_READBACK` | `HIGH` |
| 24 | `POST` `/api/v1/price-lists/(:num)/actions/set-state` | `setPriceListState` | `setPriceListState` :358 | Pricing / PriceList | Ghi | `OWNER_WORKFLOW_FACADE_READBACK` | `HIGH` |
| 25 | `GET` `/api/v1/price-lists/(:num)/items` | `listPriceListItems` | `getPriceListItems` :365 | Pricing / PriceListItems | Đọc | `DELEGATED_OWNER_AUTHORIZED_READ` | `LOW` |
| 26 | `PUT` `/api/v1/price-lists/(:num)/items` | `replacePriceListItems` | `replacePriceListItems` :373 | Pricing / PriceListItems | Ghi | `DELEGATED_OWNER_ATOMIC_REPLACE` | `HIGH` |

**Quy ước manifest:** `DELEGATED_014C/014D` = hai query orchestration đã đưa về module; `DELEGATED_OWNER_*` = facade đã gọi service owner nhưng giữ transport/context seam; `FACADE_*` hoặc `LEGACY_FACADE_LIST` = còn logic ở facade; `STOP_KIR_001` = không đụng transaction/create orchestration nếu thiếu ADR/POC/tests.

## 3. Trách nhiệm, PBAC và transaction thực tế (đọc từ source)

| Nhóm/các entrypoint | Authority và evaluation context | Dữ liệu/chủ ghi | Transaction/lock quan sát được | Cần bảo vệ khi tách |
|---|---|---|---|---|
| `listGroups`, `listUoms` | `resource.view`; `requiresRowStateAuthorization()` phát hiện lifecycle/state policy; nếu cần dùng per-row `decision(..., persistedTarget)` trước `paginate()`; nếu không check base `view` | Product; repositories `product_groups`, `product_uoms` | `listAll()` toàn tập rồi lọc và phân trang trên PHP; **facade không chủ động read snapshot** | `meta.total` chỉ tính authorized rows; Unicode/search/sort, error, status, unknown state; không áp `COUNT/LIMIT` SQL theo suy đoán |
| Group create/update/state | ProductGroupService thực thi PBAC `create/edit/setstate`; facade chuẩn hóa initial state trước `create`; `update/setstate` gọi `getGroup()` và PBAC `view` sau command | Product, `product_groups` | `create` có parent và reparent dùng `product-group-tree` lock; `setState` dùng Workflow hoặc transaction riêng; root insert/update đơn có thể là atomic statement | Lock/reparent/cycle, initial state, audit; read-after-write và thứ tự error |
| UOM create/update/state | ProductUomMasterService kiểm tra PBAC; facade chuẩn hóa create; update/state tiếp tục `getUom()` + `view` | Product, `product_uoms` | Create/update thẳng repository; setState dùng Workflow hoặc local transaction | active refs và historical read, status không đi qua ordinary edit, audit và readback |
| `getProduct` / `productForImage` | `AuthorizedProductReadService::authorizedRow` dùng persisted Product `view`/`edit`; facade trang trí role/full_name/selectable, image redaction | Product, `products` | Read, không mở transaction ghi | Error precedence (invalid action / not found / denied), projection và media actor/context injection |
| Product update/state | ProductMasterService dùng PBAC `edit/setstate`, state transition và validate role/hierarchy; facade tiếp tục `getProduct()` + `view` | Product, `products` | Update tác động structure: `product-structure` lock + transaction; set-state Workflow hoặc owner transaction | Re-read dưới lock, domain-history/role, lock order, read-after-write |
| `createProduct` | ProductMasterService `product.create`, COMBO component replacement còn cần `product.edit`, cuối cùng facade `getProduct()` gọi `view` | Product/Numbering (theo use case) | Không có items: Product/Numbering tạo đơn; có `combo_items`: facade mở outer transaction, Product create có AUTO allocator — **KIR-001** | P0: ADR + MariaDB/Shield + rollback/collision/concurrency trước mọi thay đổi |
| UOM conversion read/replace | `getConversions` check `product.view` tại facade trước khi gọi `ProductUomService::listByProduct` (read method tự nó không PBAC); `replaceByProduct` thực thi `product.edit` | Product, `product_uom_conversions` | Replace giữ `product-structure` lock, `findForUpdate`/transaction tại UOM service | Không gọi thẳng `listByProduct` mà bỏ quyền facade; exact decimal/usage/status, lock/recheck |
| COMBO items read/replace | `ComboCompositionService` tự PBAC `product.view/edit` từ owner persisted | Product, `product_combo_items` | Replace giữ `combo-graph` và product-structure root locks theo thứ tự, trong transaction owner | Nested/composite graph không cycle, quantity Decimal, row audit, cross-call Numbering |
| Price List create/show/update/state | `PriceListService` PBAC create/edit/setstate; facade `getPriceList` PBAC view theo persisted `organization_id`; initial normalizer ở facade; create/update/state có readback | Pricing, `price_lists` | Create đơn repository; update/setState lock row + transaction, hoặc Workflow | Evaluation Organization authoritative, owner recheck, effective interval, read-after-write/error status |
| Price List items read/replace | `PriceListService::listItems` kiểm tra `price_list.view`, `replaceItems` kiểm tra `edit` rồi validate Product/UOM | Pricing, `product_price_list_items` | Replace: lock parent, revalidate owner, set-diff, audit theo transaction owner Pricing | Decimal string, uniqueness Product/UOM, rollback và Pricing→Product read-only ownership |
| `listProducts`, `listPriceLists` | Truy vấn `ProductListQueryService`/`PriceListQueryService` qua read-only PBAC adapter hiện có | Product / Pricing | `readSnapshot` với các nhánh SQL được chứng minh; fallback nguyên trạng | Giữ test 014C/014D, NO-DROP/UNIT-STANDARD; không merge generic list framework |

**Đánh giá ownership:** bảng trên mô tả owner **dự kiến theo Standard và repository thực tế**; chưa thay phê duyệt DBD/MDR chính thức. Một HTTP facade dùng chung Product/Pricing không đồng nghĩa dữ liệu có đồng chủ sở hữu.

## 4. Dependency / compatibility còn sống

| Boundary | Evidence hiện có | Quyết định 014E |
|---|---|---|
| HTTP | `Config/Routes.php` → `Controllers/Api/ProductCatalog.php` → legacy facade | Giữ nguyên Controller/routes/signatures. Không tách controller trong 014E. |
| Product List | `Api050::listProducts` → `ProductListQueryService` + `Bootstrap/ProductListAuthorizationAdapter` | 014C đã xử lý; không rework SQL/role projection. |
| Pricing List | `Api050::listPriceLists` → `PriceListQueryService` + `Bootstrap/PriceListListAuthorizationAdapter` | 014D đã xử lý; không rework authorized buckets/fallback. |
| Product Media | `ProductMediaFactory::create($catalog)` → `$catalog->productReadAccess()`; `ProductImages` → `productForImage` | Hai public PHP entrypoint là compatibility consumers thật; không xóa dù không thuộc 26 HTTP API-050. |
| Authorization/Workflow | Facade vẫn import `AuthorizationService`, `EvaluationOrganizationRequest`, `ResourceWorkflowStateResolver`, `PolicyRepository` ở các read/show/list paths và initial state normalizer | Không đẩy import legacy mới vào canonical Product/Pricing khi refactor tiếp; adapter/port khi có nhu cầu thật. |
| Pricing → Product read | `PriceListService` dùng Product role/UOM checks cho item validation | Candidate Product public subset phải chốt trong MDR, không tự tạo wrapper hoặc import repository Product. |
| OperationContext | `Api050::operation()` gọi `MutationContext::begin()`; các method đa bước gọi nhiều lần | Không tạo ContextManager chung; test same-instance/evaluationTime theo command trước khi thay. |

## 5. Các rủi ro và khoảng trống cần được xử lý

### KIR-001 / P0 — Product COMBO + AUTO: STOP transaction refactor

**Điều kiện source:** `createProduct()` thấy khóa `combo_items` (kể cả mảng rỗng), mở `BaseConnection::transBegin()`; sau đó gọi `ProductMasterService::create()`. Khi AUTO series có hiệu lực, đường tạo có thể đi vào `AutoNumberingCounterAllocator::allocate()` và allocator từ chối active transaction (`transDepth > 0`). Cả việc service con tự mở transaction cho COMBO replacement cũng cần kiểm tra bằng integration, không sửa dựa trên phỏng đoán.

**Matrix trước ADR/POC:** `combo_items` absent/present/empty; `SIMPLE/COMBO`; manual identifier, `AUTO_IF_EMPTY` manual/blank, `AUTO_REQUIRED`; có/không có series; invalid child, collision, permission deny, insert lỗi sau parent, transaction rollback; counter rollback/reuse, DB session identity, advisory lock, simultaneous creates. **Không tự sửa nested transaction** hoặc đổi callback Numbering trong một cleanup 014E.

### CXT-001 / P1 — Một command, một OperationContext

`createProduct()` gọi `operation()` cho bước create và COMBO replace; `getProduct()` → `authorizedRow` cũng gọi lại `operation()`. `createPriceList`, `update*`, `set*State` và Group/UOM readback tương tự. Khi có `MutationContext`, mỗi `begin()` chụp EvaluationTime mới. Chưa kết luận behavior hỏng, nhưng **không đạt mục tiêu one-context-per-command** nếu các participant dùng context khác nhau. Cần test spy/assert identity, actor, time và audit; refactor riêng sau khi test ghi nhận behavior và đúng hợp đồng.

### LIST-001 / P1 — Hai collection còn materialize toàn bảng

`listGroups()` và `listUoms()` gọi `listAll()` trước khi lọc quyền/trang; tuy hiện filter state PBAC trước `paginate()` nên `total` có nguồn authorized, nhưng có nguy cơ O(N) memory, query count và latency khi dữ liệu tăng. Đây là **quan sát kiến trúc, không phải benchmark hay lỗ hổng dữ liệu đã được chứng minh**. Đường refactor đầu tiên chỉ di chuyển orchestration đúng owner, **không** đổi SQL để giảm rủi ro; tối ưu SQL là task khác, cần authorized `COUNT`/page tương đương.

### VIEW-001 / P1 — Read-after-write, authorization và response error

Group/UOM/Product update/state và Price List create/update/state đều gọi một `get...()` sau mutation; `getPriceList` và Product/Group/UOM reads kiểm tra `view`. Vì vậy lỗi readback có thể xuất hiện **sau khi bước mutation đã hoàn thành** (tùy transaction của owner). Không loại bỏ bước đọc hoặc đổi `403/404` để "đơn giản hóa" mà chưa đối chiếu approved API contract; cần test authorization create/edit/setstate khác với view, error precedence và persisted outcome.

### COMPAT-001 / P2 — Public consumer ngoài Controller

`ProductMediaFactory` có caller-injected facade đọc theo đúng dependencies; `ProductImages` gọi `productForImage('edit')`. Thay public read path phải bảo vệ Product Media (bao gồm not-found/denied, image metadata redaction, actor/request context) và đếm callers ngoài routes/tests/CLI trước khi xóa facade.

## 6. Backlog phân lát đề nghị (mỗi PR một boundary)

| Đợt tiếp | Mục tiêu | Đầu vào/test tối thiểu | Stop rule |
|---|---|---|---|
| **014F — Product UOM List Ownership** | Chuyển `listUoms` orchestration vào `Modules/Product/Uom` thông qua seam hẹp; API-050 delegate | Source characterization cho independent/lifecycle state, active policy, filter/sort/Unicode/total; `P1Prd002`, WF006/007, API contract tests | Không đổi PBAC/count semantics; không tạo SQL fast path trong cùng patch |
| **014G — Product Group List Ownership** | Tương tự `listGroups`, giữ hierarchy/parent filters và row-state PBAC | WF006 `testProductGroupListFiltersStatePoliciesBeforePaginationAndTotal`, Group hierarchy/authorization, large dataset benchmark khi tối ưu | Không thay Product Group tree lock, reparent, cycle behavior |
| **014H — Pricing get/show/readback boundary** | Đưa `getPriceList` authorization vào Pricing owner, sau đó từng command wrapper nếu cần | persisted owner scope, 403/404 status + machine code, view-after-create/update/setstate, tenant scope, injected auth parity | Không di chuyển transaction opener của PriceListService hoặc đổi effective period/Decimal |
| **014I — Product single read & Media compatibility** | Cân nhắc dùng canonical `AuthorizedProductReadService::publicProduct`, giảm duplicate projection | byte/shape parity, image redaction, invalid action precedence, injected catalog consumers | Không xóa `productReadAccess`/`productForImage` khi caller còn tồn tại |
| **014J — Conversions/COMBO read facade cleanup** | Chỉ refactor read-only seams sau khi bảo vệ PBAC parent Product | view gate, item read, error codes, media tests | Không tách replaceItems/lock hoặc createProduct KIR-001 cùng patch |
| **Riêng KIR-001 / ADR** | Xác nhận và quyết định transaction owner COMBO+AUTO | MariaDB/Shield POC + concurrency/rollback matrix đầy đủ | Không triển khai sửa behavior khi chưa có approval |
| **Riêng CXT-001** | Chuẩn hóa one OperationContext per command | Actor/evaluationTime/requestId identity tests, audit semantics + runtime | Không dùng global context/singleton/cache đa request |

**Chọn 014F trước 014G:** UOM list ít ràng buộc hierarchy hơn Product Group; có thể đóng một seam source-only rõ ràng, rồi áp pattern có kiểm chứng cho Group. Dù tương tự, **không tạo một GenericCrudQueryService** cho cả hai nếu chưa chứng minh giá trị.

## 7. Bằng chứng, tests và trạng thái các gate

| Gate | Trạng thái cho 014E | Bằng chứng / giới hạn |
|---|---|---|
| Source manifest vs 26 routes, Controller delegates, OpenAPI, 2 inter-consumers | `PASS` nếu chạy `php backend/tools/test-be-reorg-014e-api050-audit.php` | 11 source-only assertions/check groups; **không thực thi business runtime** |
| BE-ARCH-001 dependency scanner và fixtures | Chạy lại sau khi áp patch | `php backend/tools/architecture-scan.php` và `test-architecture-conformance.php`; kết luận chỉ thuộc scanner-covered static edges |
| 014C + 014D source characterization | Chạy lại sau khi áp patch | `test-be-reorg-014c-product-list.php` / `test-be-reorg-014d-price-list.php` |
| `UNIT-STANDARD` | **PASS theo log người vận hành gửi trước 014E:** 123 tests / 1.017 assertions | **Không chạy lại ở ZIP hiện tại**; chỉ chứng minh baseline 014D, không thay thế test KIR/composition |
| `NO-DROP` | **PASS theo log người vận hành gửi trước 014E:** 506 tests / 3.829 assertions | Verified test DB `erp_test` 41 tables; cohort-level data attestation, grant check skipped theo cấu hình, không chứng minh concurrency/performance |
| Test SQL/API/numbering runtime mới từ patch 014E | `N/A` — chỉ docs/tool audit, không sửa runtime/SQL/API | Nếu PR sau đổi query/transaction, bắt buộc integration, PBAC, concurrency, EXPLAIN theo diện tác động |
| KIR-001 MariaDB/Shield specific matrix | `NOT_RUN` | Không khẳng định COMBO+AUTO pass hay fail toàn cục |
| `EXPLAIN`, p95/memory, high-volume Group/UOM | `NOT_RUN` | Cần dataset đại diện, đo trước/sau nếu sửa query |
| Architecture conformance 12 modules | `NOT_VERIFIED` | Standard còn `PROPOSED`, owner/contract approval và exception registry chưa hoàn tất |

**Existing tests có liên quan:** `tests/unit/P1Api050ProductCatalogTest.php` (surface 26); `tests/unit/P1Api050ProductSqlPaginationTest.php` (15); `tests/unit/P1Perf008PriceListSqlPaginationTest.php` (NO-DROP); `tests/unit/P1Wf006StateAwarePbacAndLocationTransitionsTest.php`, `P1Wf007NativeStateContractTest.php`; `tests/Modules/Product/Uom/P1Prd002ProductUomMasterServiceTest.php`; Product/Combo/Pricing-specific tests. Mỗi test chỉ bảo vệ phạm vi được assert, không suy diễn mọi invariant đều được thử.

## 8. Không đổi source runtime — fingerprints trước 014E

| Nguồn | SHA-256 (ở nền 014D) |
|---|---|
| `backend/app/Services/Api050ProductCatalogService.php` | `671e2742021a9d11c1798fcad6bf7fd3a5038785cefbc3e74df67b80693682c7` |
| `backend/app/Modules/Product/Product/Application/ProductListQueryService.php` | `767d936df89302aac6b7774a4cfdeefae869d5c7ab8351824d24c4bf1c458ea1` |
| `backend/app/Modules/Pricing/PriceList/Application/PriceListQueryService.php` | `2871c37d9553d844151a16de785f8d40b2feb30d215a6475506b9d58c6b2b463` |
| `backend/app/Modules/Product/Product/ProductMasterService.php` | `9c6e6d208838498fcd7f6b84f6ee2baedb57fb3a1563305b2b370ed67cd0586c` |
| `backend/app/Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php` | `519bdfccb26a5803aee7e05409e80588500fe55d64f054d90a9f2d6d2a81d9ed` |

**Rollback:** Đợt 014E chỉ gồm manifest/report/test gate và dòng liên kết MDR, có thể reverse patch độc lập sau 014D; không động database. **Definition of Done:** route/controller/OpenAPI inventory PASS, architecture scan/source regressions PASS, mọi gap thuộc KIR/Performance/Owner được ghi trạng thái đúng. Không gọi đây là architecture compliance toàn backend.


## 014F current-state overlay — Product UOM list (22/10/2026)

> The 26-operation table and original source line numbers above are the **014E historical snapshot** after 014D. This overlay records the **intentional** BE-REORG-014F transition, not a retroactive change to the audit evidence.

- `listUoms` (`GET /api/v1/product-uoms`): `LEGACY_FACADE_LIST` → `DELEGATED_014F`. Current `Services/Api050ProductCatalogService::listUoms()` remains the existing HTTP/compatibility facade. New Product owner: `Modules/Product/Uom/Application/ProductUomListQueryService::list`.
- Bootstrap wiring: `ProductUomListAuthorizationAdapter` implements Product-internal `ProductUomListAuthorizationPort`; it delegates to the **unchanged** facade's lifecycle/policy detection, `product_uom.view` Base Permission and per-row persisted-target PBAC. No public authorization API, skip flag or dependency cycle introduced.
- Full UOM materialization persists: `ProductUomMasterService::listAll()` → state policy detection → full-row authorization **before** `CommonListQueryAdapter::paginate()`. Same PHP search/filter/sort/Unicode semantics, `items`, `page`, `page_size`, decimal-string `total` and failure precedence. **No SQL COUNT/PAGE optimization, read snapshot, schema or business mutation** in 014F.
- Updated machine-readable inventory records **three** delegated HTTP list operations (`listProducts`, `listPriceLists`, `listUoms`) and **23** other facade HTTP operations. All 26 routes, 19 Product/7 Pricing owners, Product Media non-HTTP consumers and `KIR-001`/`CXT-001` remain unchanged.
- Run `php backend/tools/test-be-reorg-014f-uom-list.php` for vendor-free parity plus `php backend/tools/test-be-reorg-014e-api050-audit.php` for source inventory/route guard. Runtime/PBAC test DB, concurrency and performance evidence are **not** supplied by source-only tests; require operator `UNIT-STANDARD`/`NO-DROP` after DB backup and verified target.

## 014G current-state overlay — Product Group list ownership (22/10/2026)

> The original 26-operation API-050 table above is a **historical 014E audit**, not a live source line-number map. 014F and 014G overlays document two successive ownership moves; do not silently revise original observed evidence.

- `listGroups` (`GET /api/v1/product-groups`): `LEGACY_FACADE_LIST` → **`DELEGATED_014G`**. API-050 facade keeps the same public method; `Modules/Product/ProductGroup/Application/ProductGroupListQueryService::list` now owns collection orchestration. Authorization remains authoritative through `Bootstrap/ProductGroupListAuthorizationAdapter` and a Product-owned narrow read port.
- **Four** operations now delegate their list logic: `listProducts` (014C), `listPriceLists` (014D), `listUoms` (014F) and `listGroups` (014G); **22** other HTTP operations still route via the facade. Still 26 routes and 19 Product/7 Pricing owners.
- Group list stays **fully materialized on PHP**: `ProductGroupService::listAll()` → state policy/lifecycle branch → row PBAC or base view → `CommonListQueryAdapter::paginate()` on `AuthorizedArrayListSource`. No SQL COUNT/page, read snapshot, caching, migration, route or OpenAPI change.
- Hierarchy is not projected on this endpoint: `parent_id` is returned **verbatim**. No auto pruning a child if its parent is denied, no cycle/reparent side effect, no induced ancestor availability. Group tree validation and `product-group-tree` locks are exclusively in owner command paths and were not changed.
- Query allowlists and error ordering are unchanged. Vendor-free `test-be-reorg-014g-product-group-list.php` protects 34 cases including denied ancestor/visible descendants, multi-depth hierarchy, exact `parent_id` filter including leading zeros and null root, stable page/sort, the canonical machine error and real facade callback order.
- **Not verified in source ZIP:** MariaDB/Shield/PHPUnit rerun post-014G, API response contract/404–403 parity, large-tree peak-memory and p95. The pre-014G 629 PHPUnit cases reported by the user are prior checkpoint evidence only; do not relabel them as new results.
- `KIR-001` (COMBO/AUTO transaction), `CXT-001`, `LIST-001` (PHP materialization costs) and `VIEW-001` remain open and are **not** remediated by 014G.
