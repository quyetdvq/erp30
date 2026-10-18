# BE-REORG-010 — Resource State Resolution Core Architecture Design

**Ngày:** 2026-10-17

**Trạng thái:** DESIGN SPECIFICATION — CHỜ CHỦ DỰ ÁN DUYỆT; chưa cho phép viết Implementation Plan hoặc sửa production code

**Phương án đã chốt:** A — Resource State Resolution Core (canonical ownership + legacy composition façade). **Hiệu chỉnh thiết kế v2:** tuân theo layout module chuẩn `Application/Ports`, `Contracts`, `Infrastructure/Persistence` như `Organization`/`Contact`; chưa phê duyệt Implementation Plan.

**Baseline runtime gần nhất:** BE-REORG-009 + FIX01, Windows/XAMPP PHP 8.3.30; PHPUnit **948 tests / 9.223 assertions**, không failure/error/skip ở các gate; PHPStan **121 diagnostics kế thừa, 0 mới** so với BE008 (đã đối chiếu toàn bộ báo cáo). PHPStan raw exit code vẫn là 1.

**Điều kiện còn mở của BE009:** Independent code review và Git checkpoint trên Windows chưa có bằng chứng hoàn tất; chỉ có thể coi **backend gates verified**, không tự nhận BE009 đã được đóng toàn bộ.

> **Nguồn đối chiếu:** `erp_v1.1.7.zip` và hai patch BE009 candidate/FIX01 đã từng được dùng trong nghiệm thu Windows; các file `backend/app/Services/ResourceWorkflowStateResolver.php`, `ResourceNativeStateCatalog.php`, các repository/factory/consumer, test BE007/BE008, `docs/as-built/20_RESOURCE_STATE_LIFECYCLE_CONTRACT.md`, BE009 spec/runner và acceptance evidence. Đây là snapshot được tái dựng cho **thiết kế**, không phải xác nhận SHA/`git status` của Windows. ZIP không có `.git` và `backend/vendor`; không tuyên bố chạy PHPUnit/PHPStan tại bước lập spec.

## 1. Mục đích, kết quả và giới hạn

Chuyển **quyền sở hữu thuật toán** ánh xạ trạng thái Resource, trạng thái được phép gắn Policy, xác minh Lifecycle membership, kiểm tra thay đổi Lifecycle và xác định State đáng tin cậy cho PBAC từ `App\Services\ResourceWorkflowStateResolver` sang module `App\Modules\Workflow\StateResolution`. Giữ lớp cũ là `final` compatibility façade bằng **composition và ủy quyền tường minh**. Mọi caller sử dụng lớp cũ tiếp tục hoạt động mà không đổi signature, quyền truy cập hoặc semantics.

**Định nghĩa hoàn thành:** Một canonical engine sở hữu hoàn toàn 11 thao tác nghiệp vụ và ba cache theo vòng đời instance; một adapter persistence/read-only sở hữu duy nhất việc gọi ba legacy repositories qua port đọc; error codes nằm tại `Contracts`. Lớp cũ không còn logic ánh xạ State; 8 hằng số/public constructor/11 methods được giữ chính xác; default/injected repository dùng cùng connection như trước; 948 kiểm thử kế thừa + bộ BE010 mới PASS; không thêm diagnostic PHPStan ngoài baseline (trừ lỗi cũ đã được chứng minh di chuyển vị trí); kiểm tra độc lập, patch apply/reverse và nghiệm thu Windows.

**Không thuộc phạm vi:** Chuyển `ResourceNativeStateCatalog`, nâng cấp nghiệp vụ Lifecycle/Transition, xóa legacy native fallback, sửa PBAC decision engine/Authorization Administration, thay đổi `LifecycleRepository`/`LifecycleApplicationService` của BE009, thay đổi API030/WorkflowBusinessStatus/adapter, đổi cache sang Redis/global, thêm DB lock, schema/migration/seeder, routes/CSRF/OpenAPI/frontend hoặc các Resource State mới. BE010 **không phải** migration để buộc mọi business Resource có `lifecycle_id`.

## 2. Baseline source và hợp đồng bất biến

### 2.1 Public API của legacy resolver

`backend/app/Services/ResourceWorkflowStateResolver.php` là lớp `final`. Giữ nguyên **8 constants** (tên và value):

| Constant | Value |
|---|---|
| `ERROR_STATE_NOT_MAPPED` | `WF_RESOURCE_STATE_NOT_MAPPED` |
| `ERROR_RESOURCE_LIFECYCLE_INVALID` | `WF_RESOURCE_LIFECYCLE_INVALID` |
| `ERROR_INITIAL_STATE_INVALID` | `WF_INITIAL_STATE_INVALID` |
| `ERROR_POLICY_STATE_INVALID` | `POLICY_STATE_NOT_IN_RESOURCE_LIFECYCLE` |
| `ERROR_STATE_CONTEXT_MISMATCH` | `WF_AUTHORIZATION_STATE_MISMATCH` |
| `ERROR_LIFECYCLE_SELF_REFERENCE` | `WF_LIFECYCLE_SELF_REFERENCE` |
| `ERROR_INITIAL_STATE_REQUIRED` | `WF_INITIAL_STATE_REQUIRED` |
| `ERROR_TRANSITION_REQUIRED` | `WF_TRANSITION_REQUIRED` |

Giữ **đúng bốn** tham số constructor, thứ tự, tên, kiểu và mặc định `null`, để không vỡ named/positional arguments:

```php
/** @param ConnectionInterface<mixed,mixed>|null $db */
public function __construct(
    ?ConnectionInterface $db = null,
    ?ResourceCatalogRepository $resources = null,
    ?LifecycleRepository $lifecycles = null,
    ?PolicyRepository $policies = null,
);
```

Giữ **đúng 11 public methods ngoài constructor**, với các tham số/return types sau; không thêm method public vào façade:

```php
/** @return array<string,mixed> */
public function resource(string $type): array;
public function lifecycleId(string $type): ?int;
/** @param array<string,mixed> $row */
public function persistedStateId(string $type, array $row): ?int;
public function stateIdForCode(string $type, mixed $code): ?int;
public function codeForStateId(string $type, int $stateId): string;
/** @return list<string> */
public function allowedPolicyStateCodes(string $type): array;
public function assertLifecycleReassignmentSafe(string $type, ?int $nextLifecycleId): void;
public function initialCode(string $type): ?string;
public function assertLifecycleStateCompatibility(string $type, int $lifecycleId): void;
public function assertPolicyState(int $resourceId, int $stateId): void;
/**
 * @param array<string,mixed>|null $persistedTarget
 * @param array<string,mixed> $input
 */
public function stateForAuthorization(
    string $type,
    ?array $persistedTarget,
    ?int $explicitStateId,
    ?array $trustedCatalogRow = null,
    string $action = '',
    array $input = [],
): ?int;
```

Giữ nguyên `DomainException` loại, mã lỗi, ngữ cảnh payload và thứ tự kiểm tra trong những ca fail-closed. Giữ chuẩn PHPDoc cho các array shape có sẵn; thay đổi phương thức tư hữu không phải public contract nhưng phải đảm bảo cache behavior. Không dùng inheritance (`final`), `__call()`, reflection dispatch, `class_alias` hay global service registry.

### 2.2 Các dependency và consumer đã kiểm tra

Resolver hiện dựng hoặc nhận **chính xác** ba concrete collaborator:

- `App\Repositories\ResourceCatalogRepository` (`findResourceByType`, `findResource`).
- `App\Repositories\LifecycleRepository` (legacy BE009 façade; truy cập Lifecycle/State và members qua các method đã có).
- `App\Repositories\PolicyRepository` (`findAll` với `resource_id`, **không** lọc policy theo status).

Nếu caller cung cấp `$db`, các default repositories đều dùng đúng connection đó; nếu caller cung cấp bất kỳ repository nào, giữ nguyên **instance identity**, không tự xây lại, không cưỡng ép đổi DB bên trong. Nếu `$db === null`, bảo toàn cách repository hiện tại tự quyết định kết nối; không tự tạo kết nối thứ hai hoặc truy vấn SQL trong factory.

Kiểm kê source có **28 file PHP ứng dụng chứa tên `ResourceWorkflowStateResolver` (gồm chính file định nghĩa lớp), tức 27 file consumer**, gồm `PbacDecisionEngine`, `AuthorizationDecisionFactory`, Authorization Administration, Foundation, Contact/Employee, Product/Import và Workflow adapters. Các consumer vẫn nhận concrete `App\Services\ResourceWorkflowStateResolver`; **không sửa chúng** để thay type hint sang port trong BE010.

Hai test hiện dùng Reflection truy cập `resolver->resources` — đường đi private sẽ đổi khi có façade:

1. `backend/tests/unit/BeReorg007AuthorizationFactoryTest.php::testFactoryUsesOneSuppliedConnectionAcrossDefaults`.
2. `backend/tests/unit/BeReorg008DecisionFactoryTest.php::testDefaultCollaboratorsUseOneSuppliedConnection`.

Cho phép sửa **chỉ những đoạn assertion Reflection** để theo `resolver->engine->reader->resources` (các thuộc tính đều private, truy cập qua helper Reflection hiện có); vẫn kiểm tra chính xác identity của `ResourceCatalogRepository` và `$db`, không xóa/skip test, không duy trì private `$resources` giả trên façade chỉ để vượt test. `reader` là adapter bất biến của một engine, không tạo bản sao repository. Kết quả kiểm thử BE007/BE008 phải được giữ nguyên. Mọi Reflection khác phải được tìm lại trên checkpoint Windows trước khi triển khai.

## 3. Kiến trúc đích và quyền sở hữu

**Quy ước module hóa:** BE010 tuân theo module `Organization` và `Contact`: `Application` sở hữu use-case logic; `Application/Ports` định nghĩa interfaces; `Contracts` tập trung error codes ổn định; `Infrastructure/Persistence` triển khai port truy cập dữ liệu. `StateResolution` là bounded context con của `Workflow`, tương đương `Workflow/Lifecycle` của BE009. Không tạo lớp/thuộc tính chỉ để thỏa cây thư mục.

```text
Existing callers / BE007 / BE008 / API030 / adapters
    |
    v
App\Services\ResourceWorkflowStateResolver (legacy final façade; unchanged API)
    |
    v
App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateResolutionPort
    ^
    |
App\Modules\Workflow\StateResolution\Application\ResourceStateResolutionService
    |   11 operations, 3 per-instance caches; stable error codes via Contracts
    v
App\Modules\Workflow\StateResolution\Application\Ports\ResourceStateReadPort
    ^
    |
App\Modules\Workflow\StateResolution\Infrastructure\Persistence\ExistingRepositoryStateReadAdapter
    +--> existing ResourceCatalogRepository
    +--> existing LifecycleRepository (BE009 façade)
    +--> existing PolicyRepository

Application also reads existing ResourceNativeStateCatalog static authority (unchanged).
App\Bootstrap\WorkflowStateResolutionFactory::create(...) => ResourceStateResolutionPort
```

**Chỉ một canonical implementation:** Không copy thuật toán vào façade hoặc adapter. `ResourceNativeStateCatalog` được sử dụng nguyên trạng làm authority cho 10 ngoại lệ và fallback vocabularies; không duplicate bảng mã State. `ResourceStateResolutionService` không tham chiếu façade hoặc lớp repository concrete. `ExistingRepositoryStateReadAdapter` không viết SQL mới và không tạo transaction; chỉ ủy quyền các read operations tới đúng ba repository hiện hữu. Đây là adapter cho persistence legacy, **không** đặt tên `Sql...Repository` vì lớp này không tự thực thi SQL.

### 3.1 Cây thư mục và file production được phép sửa/tạo

```text
backend/app/Modules/Workflow/StateResolution/
├── Application/
│   ├── ResourceStateResolutionService.php
│   └── Ports/
│       ├── ResourceStateResolutionPort.php
│       └── ResourceStateReadPort.php
├── Contracts/
│   └── ResourceStateResolutionErrors.php
└── Infrastructure/
    └── Persistence/
        └── ExistingRepositoryStateReadAdapter.php

backend/app/Bootstrap/WorkflowStateResolutionFactory.php
backend/app/Services/ResourceWorkflowStateResolver.php  (legacy façade)
```

| Operation | File | Trách nhiệm |
|---|---|---|
| **Modify** | `backend/app/Services/ResourceWorkflowStateResolver.php` | Giữ đúng 8 public constants, 4-arg constructor, 11 explicit forwards tới `ResourceStateResolutionPort` |
| **Create** | `backend/app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateResolutionPort.php` | Public application contract: chính xác 11 operations và PHPDoc tương thích |
| **Create** | `backend/app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateReadPort.php` | Read-only contract: đúng 8 thao tác đọc thô, không cache/authorization/mutation |
| **Create** | `backend/app/Modules/Workflow/StateResolution/Application/ResourceStateResolutionService.php` | Canonical owner: 11 operations, 3 instance caches, chỉ phụ thuộc read port + stable Contracts + các shared primitives |
| **Create** | `backend/app/Modules/Workflow/StateResolution/Contracts/ResourceStateResolutionErrors.php` | 8 error codes public hiện có và hằng số nội bộ `RESOURCE_LIFECYCLE_POLICY_CONFLICT`; façade re-export đúng 8 constants |
| **Create** | `backend/app/Modules/Workflow/StateResolution/Infrastructure/Persistence/ExistingRepositoryStateReadAdapter.php` | Implements read port bằng cách chuyển tiếp đúng 8 thao tác qua 3 injected repositories; không chứa SQL/policy filtering/cache |
| **Create** | `backend/app/Bootstrap/WorkflowStateResolutionFactory.php` | Stateless factory: dựng repository defaults, adapter và engine theo đúng constructor injection hiện hữu |

**Tổng production scope v2: 7 files (6 mới, 1 sửa).** Thay đổi so với v1 là bổ sung một read port, một adapter persistence và một Contracts class; không sửa ba repository hiện hữu, BE009 canonical classes hoặc consumers. Test mới, chỉ sửa hai test Reflection nêu ở §2.2, fixture riêng, runner mới và tài liệu/checksum được phép. Không tạo thư mục rỗng, không thêm port hay SQL repository thứ ba ngoài freeze list.

### 3.2 Hợp đồng read port, factory và dependency ownership

`ResourceStateReadPort` phải có đúng **8 phương thức đọc**, với các shape `array<string,mixed>|null` / `list<array<string,mixed>>` tương ứng với repository hiện tại:

```php
/** @return array<string,mixed>|null */
public function findResourceByType(string $type): ?array;
/** @return array<string,mixed>|null */
public function findResource(int $id): ?array;
/** @return array<string,mixed>|null */
public function findLifecycle(int $id): ?array;
/** @return list<array<string,mixed>> */
public function findLifecycleStates(int $lifecycleId): array;
/** @return array<string,mixed>|null */
public function findActiveWorkflowState(int $id): ?array;
/** @return array<string,mixed>|null */
public function findActiveWorkflowStateByCode(string $code): ?array;
/** @return array<string,mixed>|null */
public function findWorkflowStateByCode(string $code): ?array;
/** @return list<array<string,mixed>> */
public function findAllPoliciesForResource(int $resourceId): array;
```

Adapter forward 7 lookup methods không sửa arguments/return values; method cuối phải gọi đúng `PolicyRepository::findAll(['resource_id' => $resourceId])`, **không lọc `status`**, không tái chuẩn hóa IDs hoặc loại policy INACTIVE. `ResourceStateResolutionService` giữ nguyên thứ tự query/caching/exception của legacy resolver và thay thao tác đọc concrete tương ứng bằng read port; không tự nạp lazy khác thời điểm. Các default repositories được tạo ngay khi factory tạo engine (như constructor legacy), nhưng không được có eager database query.

`ResourceStateResolutionErrors` là final constants-only class cùng pattern `OrganizationErrors`, giữ byte-identical các giá trị của 8 error codes trong §2.1 và thêm một hằng `ERROR_LIFECYCLE_POLICY_CONFLICT = 'RESOURCE_LIFECYCLE_POLICY_CONFLICT'` chỉ dùng nội bộ; façade **không** được xuất hằng thứ chín. Engine dùng Contracts thay vì hardcode lại literals; không làm thay đổi `DomainException` messages/context.

`WorkflowStateResolutionFactory::create(?ConnectionInterface $db = null, ?ResourceCatalogRepository $resources = null, ?LifecycleRepository $lifecycles = null, ?PolicyRepository $policies = null): ResourceStateResolutionPort` dùng **đúng thứ tự** của legacy constructor và tạo **một bộ** default repositories còn thiếu (bảo toàn thứ tự khởi tạo), một `ExistingRepositoryStateReadAdapter` bất biến, một engine mới mỗi call. `ResourceStateResolutionService::__construct(ResourceStateReadPort $reader)` không tự dựng concrete dependencies. Legacy façade truyền đủ bốn argument bằng đúng tên cho factory, giữ một `private readonly ResourceStateResolutionPort $engine` và không có cache riêng. Factory không có mutable properties, DB query, service locator hay static cache.

**Quan trọng:** `ResourceCatalogRepository`, BE009 `LifecycleRepository` và `PolicyRepository` có các persistence paths khác nhau; không bypass legacy BE009 façade sang `SqlLifecycleRepository`. Giữ *instance identity* của mọi collaborator được inject và cùng `ConnectionInterface` cho tất cả default repositories khi `$db` được truyền. Adapter giữ ba property private `$resources`, `$lifecycles`, `$policies` để verification theo đường `resolver → engine → reader → repository`. Với `$db === null`, không tự ép các repository dùng chung một connection mới (giữ semantics cũ).

### 3.3 Cache ownership, consistency và isolation

Chuyển nguyên ba cache private của resolver sang canonical engine:

- `resourceCache: array<string,array<string,mixed>>` — `resource()` chuẩn hóa key bằng `strtolower(trim($type))`, cache row Resource Catalog trên instance.
- `memberCodes: array<int,array<int,string>>` — membership của từng Lifecycle, chỉ gồm các Workflow State `ACTIVE` và chỉ khi Lifecycle `ACTIVE`.
- `nativeIds: array<string,int>` — index theo khóa mode `ANY:` riêng cho `workflow_state`, `ACTIVE:` cho mọi Resource khác. Không dùng cache `ANY` để bypass ACTIVE-only lookup.

Mọi cache được khởi tạo rỗng khi tạo canonical service, **không static** và không chia sẻ giữa các façade, request hoặc instance factory. Một instance đã cache có thể giữ snapshot cũ trong cùng request — đây là as-built semantics, không hứa tự refresh tức thì trong cùng instance. Tạo resolver mới ở request khác phải đọc dữ liệu mới, bao gồm Lifecycle reassignment, State enable/disable và policy changes. Không thêm TTL, eager preload, invalidate thủ công hoặc cache across transactions nếu chưa có spec khác.

`stateForAuthorization()` hiện có nhánh `trustedCatalogRow` đẩy row vào `resourceCache[$type]` trước khi gọi `lifecycleId()`. **Không được thay thứ tự hoặc tự thay key-normalization** khi chuyển code, vì có thể đổi behavior của các caller hiện hữu. Cần khóa parity bằng test của cùng input và cùng lifecycle.

## 4. Hành vi bắt buộc giữ nguyên

### 4.1 Catalog, Lifecycle, native State

1. `resource(type)` chuẩn hóa tên, dùng `findResourceByType`, và ném `WF_RESOURCE_LIFECYCLE_INVALID` nếu không có catalog entry; không trả fallback im lặng cho resource lạ.
2. `lifecycleId(type)` đọc giá trị trusted `resources.lifecycle_id`, từ chối binding trên mười independent Resources với `WF_LIFECYCLE_SELF_REFERENCE`; trả `null` khi không gắn. Với ID dương thì validate lifecycle ACTIVE + member ACTIVE trước khi trả.
3. `ResourceNativeStateCatalog::INDEPENDENT_RESOURCES` phải giữ **chính xác mười**: `authorization_group`, `authorization_log`, `display_setting`, `employee_assignment`, `import_job`, `lifecycle`, `policy`, `user`, `workflow_state`, `resource`. Hai Resources `authorization_log`, `display_setting` **All States-only** không có concrete PBAC State.
4. `allowedPolicyStateCodes`: với Lifecycle-bound nhận tất cả ACTIVE member codes theo thứ tự hiện tại, kể cả `DRAFT`, `APPROVED`, không giới hạn enum native; với native/legacy-unbound nhận code catalog. Không thêm ngoại lệ thứ 11.
5. Khi non-exception Resource chưa có Lifecycle, nhánh **legacy native fallback** vẫn được giữ (như `docs/as-built/20_RESOURCE_STATE_LIFECYCLE_CONTRACT.md`). Đây là khoảng trống migration dữ liệu đã được ghi nhận, **không** phải chính sách nghiệp vụ mới.
6. `members`: lifecycle không tồn tại/INACTIVE hoặc member Workflow State không còn ACTIVE phải fail-closed với `WF_RESOURCE_LIFECYCLE_INVALID`; giữ `PositiveId` field/error mappings và thứ tự iteration.

### 4.2 Canonical mapping và trường hợp đặc biệt

1. `persistedStateId(type,row)` trả `null` với All States-only mà không đòi `status`; những Resource cần status nhưng thiếu key `status` thì `WF_RESOURCE_STATE_NOT_MAPPED`.
2. Với Resource `user` (Shield), kết hợp persisted `status` và persisted `active` chỉ chấp nhận `true`, `false`, `0`, `1`, `'0'`, `'1'`; `ACTIVE` chỉ khi `status` chính xác ACTIVE (sau uppercase/trim) **và** flag enabled; trạng thái khác ánh xạ `INACTIVE`. Không dùng client flag làm thẩm quyền.
3. `stateIdForCode` chỉ nhận nonempty string sau trim + uppercase thuộc vocab được phép; canonical state ID được xác định qua Lifecycle ACTIVE membership hoặc native catalog. Với `workflow_state`, lookup theo code được phép đọc cả định nghĩa State đã disabled để tự re-activate; các Resource khác dùng **active-only** lookup. Cache key `ANY:<CODE>` và `ACTIVE:<CODE>` phải độc lập.
4. `codeForStateId` với Lifecycle-bound chỉ nhận member ID; với native/unbound chỉ cho State catalog đang ACTIVE và thuộc native code list; unknown ID fail-closed.
5. `initialCode`: Lifecycle-unbound => `null`; Lifecycle-bound phải có đúng một initial member được đánh dấu `is_initial` (chấp nhận `true`, `1`, `'1'`) và mapping hợp lệ; nếu không `WF_INITIAL_STATE_INVALID`.
6. `assertLifecycleStateCompatibility`: independent Resource không được attach Lifecycle; bound candidate Lifecycle phải có đúng một initial State và từng mã đạt regex `\A[A-Z][A-Z0-9_:-]{0,49}\z` / `D`; mã dài tối đa 50, mọi member ACTIVE. Không đổi field/error context.

### 4.3 Policy safety, reassignment và PBAC input trust

1. `assertLifecycleReassignmentSafe`: nếu current == proposed thì no-op; reject independent binding; validate candidate compatibility; khi tháo binding dùng mã native và State definition ACTIVE để tính ID được phép. Query **tất cả** Policy của Resource, bao gồm `INACTIVE`, không lọc status. Policy có `state_id !== null` không còn hợp lệ thì ném `RESOURCE_LIFECYCLE_POLICY_CONFLICT` với `resource` và `policy_id`. `state_id === null` (All States) không bị ảnh hưởng.
2. `assertPolicyState(resourceId,stateId)`: chỉ chấp nhận State thuộc Lifecycle ACTIVE member hoặc native code hiện hành; resource không có/type sai và lỗi State mapping đều được chuyển thành `POLICY_STATE_NOT_IN_RESOURCE_LIFECYCLE` với field context đang dùng. Không nới rộng catch hoặc swallow exception khác.
3. `stateForAuthorization` dùng **persistedTarget đáng tin cậy** để tính PBAC State. `explicitStateId` là assertion tương ứng, **không phải** nguồn chọn nhánh Policy. `trustedCatalogRow` chỉ do caller trusted cung cấp, không chấp nhận request payload làm row thẩm quyền.
4. `persistedTarget === null` thì không có current State (trả `null` cho create/list); với Lifecycle-bound `action==='create'`, status trong input phải khớp chính xác initial Code đã xác nhận, nếu không `WF_INITIAL_STATE_REQUIRED`.
5. Context chỉ có scope (không `id`, không `status`) trả `null`, chỉ All States có thể match; nếu client cung cấp `explicitStateId` thì `WF_AUTHORIZATION_STATE_MISMATCH`. Một record đã định danh nhưng thiếu required status phải fail-closed, không suy luận từ input.
6. Nếu explicit State khác canonical persisted State => `WF_AUTHORIZATION_STATE_MISMATCH`. Lifecycle-bound `edit` mang `status` => `WF_TRANSITION_REQUIRED`; `setstate` không có explicit source State => `WF_TRANSITION_REQUIRED`; status hint nếu có được validate nhưng không được chọn PBAC source State. Giữ nguyên precedence/order of checks.
7. Không sửa `AUTH_ABSOLUTE_DENY`, Base Permission, policy matching, Shield, Organization scope và các quyết định của BE007/BE008. BE010 không tự cấp quyền, không thay action vocabulary hay biến denied request thành no-op/success.

### 4.4 Error/DB semantics

Toàn bộ `DomainException` và error context của legacy sẽ được đối chiếu theo từng method; `ResourceStateResolutionErrors` chỉ định danh mã lỗi, không đổi exception class, payload hay thứ tự throw; null vs exception, string normalization, exact status handling và return shape phải giữ. Không tự thêm transaction hoặc `SELECT ... FOR UPDATE` vào các read path hiện hữu; backend repository/BE009 tiếp tục sở hữu các giao dịch/lock đang có. Cấu hình Lifecycle/Policy là mutation bên ngoài BE010. Các bài kiểm thử thực DB phải kiểm tra các thao tác đó vẫn không bị state validation bỏ qua; test không tự sửa schema/migrations.

## 5. Freeze list, thay đổi test và phạm vi tài liệu

**Không thay đổi** các production paths sau:

```text
backend/app/Services/{ResourceNativeStateCatalog,WorkflowBusinessStatus,
  BusinessWorkflowActions,BusinessLifecycleTransitions,
  InitialCreateStateNormalizer,Api030AuthorizationWorkflowService,
  BusinessLifecycleTargetAdapter,ContactLifecycleAdapter,
  FoundationLocationLifecycleAdapter,Api010FoundationOrganizationContactService,
  Api020EmployeeStructureService,Api050ProductCatalogService,
  Api060ProductImportService}.php
backend/app/Repositories/{ResourceCatalogRepository,LifecycleRepository,PolicyRepository}.php
backend/app/Modules/Workflow/Lifecycle/**
backend/app/Modules/Authorization/**
backend/app/Authorization/**
backend/app/Bootstrap/{AuthorizationAdministrationFactory,AuthorizationDecisionFactory,FoundationFactory,WorkflowLifecycleFactory}.php
backend/app/Config/{Routes,Services}.php
backend/app/Controllers/**
backend/app/Database/{Migrations,Seeds}/**
backend/app/Models/**
frontend/**
contracts/**
backend/phpstan.neon
backend/phpunit.dist.xml
```

Chỉ cho phép chỉnh sửa **hai test Reflection** ở §2.2, và thêm các test/fixture/guarded runner BE010; không xóa hoặc hạ cấp assertion. Nếu checkout post-BE009 trên Windows khác snapshot v1.1.7 + BE009/FIX01, dừng trước khi xuất production patch và rà soát lại scope/diff.

## 6. Verification gates và coverage BE010

**Runtime baseline:** 948 tests gồm 32 BE009, 26 BE008, 24 BE007, 91 PBAC authority cùng các gate BE001–BE006; Foundation guard 17 checks. Kế hoạch BE010 yêu cầu **30 tests mới trong 7 nhóm**; Implementation Plan sẽ khóa *từng tên method* và assert cụ thể:

| BE010 suite dự kiến | Cases | Nội dung bắt buộc |
|---|---:|---|
| Boundary | 5 | Canonical ownership; application port 11 methods + read port 8 methods; Contracts stable codes; façade delegate; adapter không chứa business logic/SQL và không vòng dependency |
| Legacy compatibility | 5 | 4 constructor args named/positional; 8 façade constants; 11 methods; exact types/returns/error context; Reflection qua engine→reader vẫn kiểm tra connection và repository identity |
| Factory / dependency identity | 4 | Stateless/fresh engine+reader; adapter forwards đúng 8 repository reads, policy filter không loại INACTIVE; supplied collaborator identity; one DB across defaults/BE009 façade; no eager queries |
| Domain resolution parity | 8 | Ten independent and two All States-only; bound/unbound vocab; Shield flags; disabled workflow_state exception; policy reassignment including INACTIVE policy; initial/membership validation; `stateForAuthorization` fail closed and status mutation guards |
| Cache isolation | 4 | Resource cache per instance; membership cache per instance; `ANY:` vs `ACTIVE:` same-instance isolation; trustedCatalogRow and cache scope/mixed type parity |
| In-process HTTP integration | 2 | API030 Resource/Lifecycle administration error envelope + CSRF; existing PBAC denied/allowed state route unchanged, no unauthorized writes |
| Real DB integration | 2 | Policy INACTIVE conflict & All States policy behavior; after Lifecycle/State administrative changes a freshly constructed resolver observes new DB state without stale cross-request cache |
| **Total BE010** | **30** | Không skipped, failures, errors |

**Full test target:** `948 + 30 = 978` cases trên đúng checkpoint đã xác nhận, không tự đưa số lượng assertions dự kiến. Ngoài các test BE010 mới, chạy lại các suite nghiệp vụ hiện hữu (đặc biệt P1Wf006, P1Wf007, P1WorkflowCreate*, BE007, BE008, BE009, PBAC). Bắt buộc kiểm tra `ResourceNativeStateCatalog` đúng 10 entries; không nới rộng allowlist để làm test PASS.

**Guarded runner:** Thêm `backend/tools/test-resource-state-resolution.php` với chỉ một tham số thực thi `--confirm-test-db` và tùy chọn an toàn `--help`. Runner gọi `tools/test-workflow-lifecycle.php` (BE009) **một lần**; kế thừa preflight `127.0.0.1:3306/erp_test` / `dinktech_` và Foundation 17 checks; không cho chạy song song các test DB; ghi fresh JUnit `backend/build/logs/be-reorg-010.xml`, verify từng BE010 testcase/class/method và toàn bộ 978 case thực thi với không lỗi/skip. Không đặt test DB runner ở controller/production.

**PHPStan:** So sánh danh tính lỗi mới với baseline BE009 đã xác nhận (121 diagnostics, raw exit 1). Source hiện có hai diagnostic trên legacy resolver: `return.unusedType` tại `stateIdForCode()` và `missingType.iterableValue` tại `stateForAuthorization($trustedCatalogRow)`. Nếu thuật toán và PHPDoc được chuyển, lỗi kế thừa có thể **di chuyển sang đường dẫn/lớp canonical hoặc biến mất**. Quyết định nghiệm thu phải có mapping `old → new` rõ ràng cho từng relocation; không dùng `121 == 121` làm bằng chứng và không tự whitelist lỗi mới cùng identifier. Không thay đổi PHPStan config/baseline, không bỏ qua diagnostics, không báo raw exit 1 là clean.

**Các gate hoàn tất:** PHP lint mọi file mới/đổi, Reflection signature parity dựa trên source, kiểm tra phương thức thuật toán không bị nhân đôi, `git diff --check`, patch `git apply --check` + apply/reverse trên *đúng* post-BE009+FIX01 Windows checkpoint, SHA-256/manifest, focused DB/HTTP suites, full 978 tests, 0 new PHPStan diagnostic, independent code review, Git checkpoint và chứng cứ Windows. Không thay đổi API contract nên không phát sinh OpenAPI/client regeneration; bất kỳ thực sự thay đổi API nào đều phải dừng và thiết kế lại.

**Scope thử nghiệm:** guarded runner bao gồm PHPUnit, in-process HTTP và DB integration, **không** chứng minh browser/Apache UAT hoặc dữ liệu production; không tuyên bố các gate ấy PASS trước khi có log chạy thực trên Windows.

## 7. Trình tự triển khai dự kiến (chưa được ủy quyền)

1. Hoàn tất independent review và chốt Git checkpoint BE009; ghi SHA/status, snapshot 948-test JUnit và PHPStan BE009 121-diagnostic baseline; xác minh v1.1.7 + BE009 + FIX01 bằng diff với source hiện tại.
2. Viết 30 characterization/compatibility tests **trước** production code theo RED → GREEN. Kiểm tra hai test reflection BE007/BE008 ở baseline để xác nhận lỗi xuất phát từ private implementation relocation, không đổi business assertions.
3. Tạo Application port 11 methods, read port 8 methods, Contracts và reader adapter; viết characterization tests cho adapter (raw return, call order, policy INACTIVE). Thêm canonical service cùng ba caches và stateless factory; chuyển legacy resolver thành 11 explicit delegations; chỉ sửa hai test introspection đúng chỗ. Không đưa SQL/transaction mới vào adapter.
4. Chạy focused DB/HTTP và toàn bộ inherited BE001–BE009 suites; so sánh Reflection, algorithm parity và PHPStan theo mapping. Không chạy hai test process đồng thời trên `erp_test`.
5. Đóng gói một incremental patch từ đúng checkpoint Windows, apply/reverse proof, checksum, verification report; Windows acceptance trước khi chốt `BE-REORG-010 ACCEPTED`.

## 8. Rủi ro và quyết định kiến trúc

- **Private reflection tests:** Đây là test cấu trúc nội bộ, không phải public API; chuyển đường dẫn test sang `$engine->reader->resources` nhưng không xóa các điều kiện kiểm tra connection. Đây là rủi ro đã từng xảy ra ở BE008, được xác định sớm.
- **Cache per-instance:** Đổi sang static/singleton hoặc factory reuse sẽ khiến chính sách/lifecycle bị stale qua request, rủi ro phân quyền. Không thay đổi thời điểm invalidate trong request.
- **`workflow_state` disabled catalog:** Mất phân biệt `ANY:`/`ACTIVE:` có thể vô tình cho phép Resource khác sử dụng State đã INACTIVE. Đây là case bảo mật ưu tiên cao.
- **Legacy fallback:** Xóa ngay nhánh native fallback nghe có vẻ nhất quán hơn rule mục tiêu nhưng phá runtime/DB chưa migrate. Tách riêng khỏi BE010.
- **Repository injection/adapter:** `LifecycleRepository` đã là BE009 façade, và `PolicyRepository` có canonical delegate BE007. Adapter bắt buộc nhận nguyên ba repository instances, không bypass sang SQL, không đổi thứ tự truy vấn, không lọc policy `INACTIVE`; sai bất kỳ điều nào có thể gây sai PBAC hoặc cache stale.
- **PHPStan relocation:** Lỗi cũ chuyển đường dẫn không được tính nhầm thành regression hoặc bị che giấu bằng số tổng; bổ sung mapping chặt chẽ.
- **Baseline code review:** BE009 backend VERIFIED không tương đương final Git checkpoint; bắt đầu production BE010 khi chưa khóa SHA có thể tạo patch lệch baseline.

**Lựa chọn mô hình tầng:** Không chỉ thêm folder rỗng hoặc một class `Sql...` giả; thay vào đó dùng Contracts thật và read port + adapter thật vì resolver có 11 lời gọi đọc ở 8 method repository khác nhau. Không tách ba SQL repository mới, không thay DB schema/queries.

**Các phương án loại bỏ:** (B) ưu tiên Business Workflow Execution không hợp thứ tự module hóa khi State Resolver chưa có canonical owner; (C) chia `Api030AuthorizationWorkflowService` có surface API/authorization lớn hơn và khó khoanh vùng regression. Chuyển legacy resolver bằng subclass, global cache hoặc thay mọi caller đồng loạt đều bị loại do không bảo toàn contract/transaction/cache.

## Appendix — Source anchors và kiểm kê

- `backend/app/Services/ResourceWorkflowStateResolver.php`: constants L23–30, 4-arg ctor L45–55, public methods L58–361, `members()` L365–389; 3 private caches L36–43.
- `backend/app/Services/ResourceNativeStateCatalog.php`: 10 entries và native/legacy fallback codes; public `supportsLifecycleState`, `isIndependent`, `codes`.
- `backend/app/Repositories/{ResourceCatalogRepository,LifecycleRepository,PolicyRepository}.php` sau BE009 candidate+FIX01; BE009 `WorkflowLifecycleFactory.php` và `Modules/Workflow/Lifecycle/**`.
- `backend/tests/unit/{BeReorg007AuthorizationFactoryTest,BeReorg008DecisionFactoryTest,P1Wf006StateAwarePbacAndLocationTransitionsTest,P1Wf007NativeStateContractTest}.php`.
- `backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php` và `backend/app/Modules/Authorization/Administration/Policies/**`.
- `docs/as-built/20_RESOURCE_STATE_LIFECYCLE_CONTRACT.md`; `docs/p1/BE-REORG-009_Workflow_Lifecycle_Core_Spec_2026-10-17.md`; `backend/tools/test-workflow-lifecycle.php`.

**Quyết định tiếp theo:** Chủ dự án duyệt hoặc yêu cầu sửa **Specification v2 (module layout chuẩn)**. Phê duyệt hiện tại mới là *phương án A*, chưa phải phê duyệt tài liệu; chỉ sau khi Specification được duyệt mới lập Implementation Plan BE010. Chưa có production code BE010 và chưa tạo commit trên Windows.
