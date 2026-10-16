# BE-REORG-001 — Đặc tả baseline và pilot Foundation

Ngày: 16/10/2026  
Nguồn: `erp_v1.1.5.zip`, root `erp30-main/`  
Trạng thái: **Đặc tả sẵn sàng rà soát; chưa triển khai code.**

## 1. Mục tiêu và giới hạn

Người dùng đã đồng ý hướng modular monolith và lộ trình từng module. Gói này cụ thể hóa phần đầu: khóa baseline và chuyển một lát Foundation sang cấu trúc mới. Đây không phải đặc tả viết lại toàn bộ backend.

Thành công nghĩa là logic Province/Ward có nơi sở hữu rõ hơn, các caller hiện tại vẫn hoạt động, và có bằng chứng hành vi trước/sau. Không lấy số lượng class hay số dòng giảm được làm thước đo chính.

**Trong phạm vi:** service Province/Ward, repository Province/Ward, adapter lifecycle của hai resource này, factory khởi tạo, cầu tương thích namespace cũ, kiểm thử bổ sung và bằng chứng baseline.

**Ngoài phạm vi:** đổi API/schema/migration, nâng dependency, thay framework, sửa frontend, tách microservices, đưa import sang queue, tối ưu SQL, thay đổi policy/workflow, và di chuyển hàng loạt các module khác.

Để giới hạn rủi ro, list/get/authorization/pagination ở facade API-010 vẫn ở nguyên chỗ trong pilot. Vì vậy, hoàn tất pilot **không** có nghĩa là toàn bộ Foundation đã độc lập với IAM/Workflow.

## 2. Baseline đã xác minh

SHA-256 của ZIP:

```text
f007fbf3fb783182cbd38aaad975080067b72919a3a3b14a246f7d5828357426
```

| Kiểm tra trong phiên này | Kết quả |
|---|---|
| CRC archive | Không có entry lỗi |
| Đối chiếu từng tệp | 1.018/1.018 khớp ZIP |
| Thay đổi source | 0 thêm, 0 sửa, 0 xóa |
| `php -l` | 504/504 tệp PHP đạt |
| Route/OpenAPI method-path, phân tích tĩnh | 152/152 khớp; chưa chứng minh payload/runtime |
| Province/Ward | 10 operations HTTP |
| Migration hiện có | 18 tệp, giữ nguyên |
| PHPUnit/PHPStan/HTTP/DB/frontend tests | Chưa chạy |

Dữ liệu gốc nằm trong `evidence/source_manifest.json`; kết quả kiểm tra nằm trong `evidence/baseline_summary.json`. Phân tích routes dùng bộ quét hiện có, có mở rộng vòng lặp tĩnh; không phải route dump từ HTTP runtime.

Môi trường hiện tại có PHP CLI 8.4.24, chưa có `backend/vendor`, Composer trong PATH, hay DB test được thiết lập. Các extension còn thiếu được liệt kê trong `evidence/environment.json`; trong đó có `intl`, `mbstring`, `mysqli` và `bcmath`. Không cài dependency, không kết nối DB trong vòng rà soát này.

**Giai đoạn 0 chưa đủ điều kiện nghiệm thu runtime.** Việc thiếu môi trường không phải kết quả test FAIL; cũng không được ghi PASS.

## 3. Đối chiếu source quyết định phạm vi

Ba class hiện có đều là `final`:

| Class | Nội dung cần giữ |
|---|---|
| `App\Services\FoundationLocationService` | 8 phương thức public ngoài constructor; validation, workflow boundary, audit và ward reparent |
| `App\Repositories\FoundationLocationRepository` | 9 phương thức public ngoài constructor; query, allowlist field, error identity, row lock |
| `App\Services\FoundationLocationLifecycleAdapter` | 5 phương thức public ngoài constructor; snapshot, transaction, reload/lock, state mutation |

Constructor service hiện nhận `repository, db, mutationContext, clock, workflowStates`; constructor adapter nhận `type, db, repository, states`. Tên tham số là một phần của tương thích vì source/tests có named arguments. Hợp đồng tĩnh đã lưu tại `evidence/legacy_public_contracts.json`.

Các chi tiết không được bỏ sót:

- Ward thuộc Province vừa bị inactivate vẫn được sửa thông tin lịch sử; chỉ lựa chọn Province mới phải ACTIVE [S02, S10].
- Ward reparent khóa dòng và kiểm tra địa chỉ trong `organizations`/`contacts` cùng transaction [S03, S05].
- Facade cấm field `status` trong PATCH thường, kể cả no-op; thứ tự kiểm tra quyền và validation phải giữ [S07]. Direct service có nhánh khác khi resource chưa gắn lifecycle [S11].
- Tìm kiếm/sắp xếp có nhánh PHP Unicode và nhánh SQL narrowing; không thay bằng `LIKE`/`ORDER BY` tùy tiện [S08].
- Một số test trong `tests/unit` sử dụng `DatabaseTestTrait`; tên thư mục không bảo đảm test không đụng DB [S10].

[Sxx] trỏ về tệp, dòng và trích đoạn trong `evidence/source_excerpts.json`.

## 4. Thiết kế đích của pilot

```text
backend/app/
  Modules/Foundation/
    Contracts/LocationErrors.php
    Application/
      Ports/LocationRepositoryInterface.php
      LocationService.php
    Infrastructure/Persistence/SqlLocationRepository.php
  Integration/Foundation/LocationLifecycleAdapter.php
  Bootstrap/FoundationFactory.php
```

Không tạo `Domain/` trống hoặc thêm DTO/interface không có nhu cầu. Logic hiện có vẫn gắn DB và workflow, nên đặt ở Application, không gọi đó là domain thuần.

`LocationRepositoryInterface` mô tả đúng các thao tác repository Foundation hiện cần, không là generic repository cho toàn ERP. `LocationErrors` là nơi chứa chuỗi mã lỗi bảo toàn từ source, bao gồm `WF_TRANSITION_REQUIRED`. Nhờ đó Application không cần import facade API hay repository cụ thể chỉ để lấy constant.

`SqlLocationRepository` sở hữu logic persistence được chuyển nguyên hành vi. `LocationService` sở hữu logic nghiệp vụ hiện có; transaction reparent vẫn ở vị trí tương đương, không đưa vào generic transaction manager trong pilot.

`Integration/Foundation/LocationLifecycleAdapter` nối Foundation với workflow/authorization hiện tại. Lớp này tiếp tục trả đúng `LifecycleTargetSnapshot` và implement `LifecycleTargetAdapter` hiện có, không tạo hệ DTO thứ hai.

`FoundationFactory` khởi tạo và nối dependency. Luồng do factory quản lý sử dụng đúng connection được truyền vào cho service/repository/adapter. Không thêm cache, singleton hoặc global shared service; không giữ actor/request context qua request.

### Cầu tương thích

Giữ nguyên ba FQCN cũ và tệp cũ, nhưng thay nội dung bằng wrapper **composition/delegation tường minh**. Không kế thừa class `final`, không dùng `__call`, không `class_alias`, không giữ hai bản logic nghiệp vụ.

Wrapper repository implement port mới; wrapper service/adapter chấp nhận các dependency theo đúng constructor cũ. Public constants, parameter names/order/types/defaults, return types, exception class/code/context và tính `final` của lớp cũ được bảo toàn.

```text
Controller / facade / workflow caller hiện có
  -> wrapper Foundation tại namespace cũ
  -> triển khai chính tại module / Integration
  -> DB và workflow hiện có
```

Legacy caller truyền dependency tường minh phải được tôn trọng. Không thêm quy tắc validation constructor hoặc tự sửa cặp repository/connection do caller cung cấp. Nếu phát hiện caller ghép sai connection, ghi thành vấn đề riêng thay vì sửa ngầm.

Giữ cách xử lý `MutationContext`, `Clock`, thời điểm `begin()` và lazy workflow resolver. Không gộp operation mới trong facade và operation mutation chỉ để giảm lời gọi.

## 5. Quy tắc phụ thuộc và ngoại lệ chuyển tiếp

Application được phụ thuộc port/contracts của module, không import concrete SQL repository, controller, facade API hay ba wrapper cũ. Factory và triển khai chính không gọi ngược wrapper, tránh vòng lặp delegation.

Ngoại lệ Application được giữ trong pilot, theo tên cụ thể:

```text
App\Services\BusinessWorkflowWriteBoundary
App\Services\WorkflowBusinessStatus
App\Services\ResourceWorkflowStateResolver
App\Exceptions\DomainException
App\Exceptions\PersistenceException
App\Infrastructure\Id\PositiveId
App\Infrastructure\Mutation\MutationContext
App\Infrastructure\Mutation\OperationContext
App\Infrastructure\Time\Clock
CodeIgniter\Database\ConnectionInterface
CodeIgniter\Database\BaseConnection
```

Lời gọi clock hiện có được giữ để không đổi vòng đời. Adapter tại Integration được dùng các interface/DTO workflow và resolver authorization hiện có; danh sách import được kiểm tra tương ứng, không mở ngoại lệ `App\Services\*` cho toàn module.

Danh sách dependency nghiệp vụ legacy cụ thể cho adapter Integration (ngoài exception/ID/operation/DB đã nêu):

```text
App\Authorization\EvaluationOrganizationRequest
App\Authorization\EvaluationOrganizationResolver
App\Exceptions\ConcurrencyException
App\Services\LifecycleTargetAdapter
App\Services\LifecycleTargetSnapshot
App\Services\LifecycleService
App\Services\ResourceWorkflowStateResolver
```

Factory chỉ nối implementation/port mới với connection, mutation context, clock và workflow state resolver; không import ba wrapper cũ. Phạm vi cho phép này không mở rộng sang các facade khác.

Query kiểm tra địa chỉ trên `organizations` và `contacts` là ngoại lệ đọc chéo có chủ đích. Giữ SQL, thứ tự kiểm tra và transaction; chưa thay bằng các service call. Tái thiết kế giao diện đọc này thuộc giai đoạn Organization/Contact.

Ngoại lệ workflow được xử lý ở giai đoạn IAM/Workflow. Việc xóa wrapper chỉ diễn ra sau khi đối chiếu không còn caller và hồi quy đạt; không để wrapper trở thành nơi viết tính năng mới.

## 6. Danh sách tệp được phép thay đổi khi triển khai

| Nhóm | Phạm vi |
|---|---|
| 3 tệp hiện có được sửa | `Services/FoundationLocationService.php`, `Repositories/FoundationLocationRepository.php`, `Services/FoundationLocationLifecycleAdapter.php`, tương đối với `backend/app/` |
| 6 tệp production mới | Đúng cấu trúc tại mục 4 |
| Test mới | Characterization, compatibility, module parity và boundary checks cho BE-REORG-001 |
| Tài liệu/evidence | Đặc tả, kế hoạch sau khi duyệt, log và manifest |

Không sửa facade `Api010FoundationOrganizationContactService`, `BusinessLifecycleTransitions`, `BusinessWorkflowActions`, `Config/Services.php` hay autoload trong pilot. Namespace `App` hiện tại đã bao phủ cấu trúc này; không thêm cơ chế discover module mới.

Toàn bộ tệp gốc khác phải khớp hash baseline. Tập bảo vệ riêng gồm 566 tệp đặc biệt như frontend, OpenAPI, migrations/seeds, controllers, config, language, lockfile và tests hiện có. Tập 566 này không phải giấy phép sửa các tệp còn lại.

Nếu cần sửa thêm tệp, phải nêu lý do và cập nhật phạm vi trước; không mở rộng ngầm. Không đổi version thành v1.1.6 khi chưa có quyết định release.

## 7. Bất biến và ma trận nghiệm thu

| ID | Hành vi cần kiểm chứng |
|---|---|
| C01 | FQCN/constructor named arguments/constants/return types cũ vẫn dùng được; wrapper giữ `final` |
| C02 | Tạo/sửa/đọc Province/Ward giữ field allowlist, null/omitted/empty và ID semantics |
| C03 | Duplicate code, missing field, unsupported field, not-found trả đúng exception class/code/context hiện có |
| C04 | Ward mới hoặc đổi Province yêu cầu Province ACTIVE; sửa Ward lịch sử giữ nguyên Province vẫn hợp lệ |
| C05 | Reparent không bị tham chiếu thành công; cả organization và contact conflict đều bị chặn |
| C06 | Sửa code và Province cùng lúc vẫn giữ lỗi duplicate chuẩn; không ghi dữ liệu dở dang |
| C07 | Dòng bị đổi/xóa giữa read và lock sinh lỗi concurrency đúng; rollback đúng connection |
| C08 | Lỗi begin/commit/query không bị đổi thành not-found, success hoặc loại exception khác |
| C09 | PATCH thường không lách `status`; quyền edit được kiểm tra đúng thứ tự trước chi tiết validation |
| C10 | Configured initial state, status normalization và nhánh chưa gắn lifecycle giữ nguyên |
| C11 | Transition dùng persisted source state; client không ghi đè đích đã cấu hình |
| C12 | DENY/ABSOLUTE_DENY/unknown state giữ kết quả và không gây side effect bất hợp lệ |
| C13 | Lock/reload/recheck/mutate/commit đúng thứ tự, giữ predicate persisted status |
| C14 | Audit actor/request/time đúng; caller không giả mạo trường audit |
| C15 | List/filter/search/sort/total/pagination và fallback Unicode không đổi |
| C16 | 10 endpoint Foundation giữ method/path, request, response, HTTP status/error và authorization |
| C17 | Test hiện có chạy lại nguyên assertion; không hạ PHPStan level hay thêm ignore rộng |
| C18 | Full backend regression và smoke với backend thật không có hồi quy ngoài Foundation |
| C19 | Kiểm tra import/FQCN trong module, Integration và factory không tạo dependency trái quy tắc |
| C20 | Hash phần ngoài phạm vi và diff allowlist đạt; rollback code không cần rollback schema |

Không đồng nhất hóa exception cho đẹp: repository hiện có các nhánh `DomainException` và `PersistenceException` khác nhau. Giữ nguyên hành vi theo từng nhánh.

## 8. Chiến lược kiểm thử

Trước khi di chuyển logic, characterization tests phải chạy trên implementation gốc. Expected values được khóa từ baseline, không tự cập nhật theo kết quả refactor. Dùng fixture và clock kiểm soát được; không bỏ qua trường audit/quyền để làm snapshot khớp.

So sánh wrapper với implementation mới chỉ kiểm tra delegation. **Hai bên cùng gọi một implementation có thể cùng sai**, nên parity test không thay thế baseline characterization.

Các test cũ trọng tâm:

```text
backend/tests/database/P1Db010FoundationLocationTest.php
backend/tests/database/P1Db020OrganizationContactTest.php
backend/tests/unit/P1Loc010OptimizationTest.php
backend/tests/unit/P1Wf006StateAwarePbacAndLocationTransitionsTest.php
backend/tests/unit/P1Api010FoundationOrganizationContactApiTest.php
backend/tests/unit/P1Perf005AuthorizedBucketListTest.php
backend/tests/unit/P1Wf007NativeStateContractTest.php
backend/tests/unit/P1Con001ContactServiceTest.php
backend/tests/unit/HardenP1Test.php
```

Test bổ sung dự kiến:

```text
backend/tests/database/BeReorg001FoundationCharacterizationTest.php
backend/tests/database/BeReorg001FoundationModuleParityTest.php
backend/tests/unit/BeReorg001FoundationCompatibilityTest.php
backend/tests/unit/BeReorg001FoundationBoundaryTest.php
```

Concurrency cần có bằng chứng trên DB của runtime bằng các connection độc lập và điểm đồng bộ kiểm soát được; không dùng mock/SQLite để tuyên bố đã chứng minh row lock MySQL/MariaDB. Fault injection chỉ bổ sung kiểm tra đường lỗi.

Full Playwright mặc định loại `e2e/live`; kết quả xanh của nó không tự chứng minh backend tương thích [S14]. Smoke API thật phải bao phủ tạo/sửa, denied mutation, list/search và transition Province/Ward.

## 9. An toàn khi lấy baseline runtime

Dùng dependency đúng `composer.lock`; không `composer update`. Lockfile hiện tại ghi CI4 v4.7.4, Shield v1.4.1, PHPUnit 10.5.65 và PHPStan 2.2.17. Đây là phiên bản trong archive, không phải khuyến nghị nâng cấp.

Trước khi chạy test DB, xác minh cả host/port và database, không chỉ nhìn tên `erp_test`. Chỉ dùng instance test biệt lập với dữ liệu có thể tái tạo. Harness hiện có kiểm tra environment/database nhưng không tự chứng minh server không phải production [S12].

`DatabaseTestTrait` có thể refresh/migrate dữ liệu ngay khi chạy test. Không có `--yes` không đồng nghĩa là an toàn cho DB thật. Không chạy reset/fresh/rollback để khắc phục test đỏ trên môi trường nghiệp vụ.

Các lệnh kiểm chứng sau đây là tiêu chí cho bước triển khai; **chưa được chạy trong phiên này**. Thư mục là `backend/`, và các điều kiện an toàn nêu trên phải đạt trước:

```powershell
php tools/test-db-harness.php inventory
if ($LASTEXITCODE -ne 0) { throw 'Stop: test DB inventory failed.' }
# Chi tiep tuc sau khi xac minh host/port/database test biet lap.
php tools/test-db-harness.php full-tests
if ($LASTEXITCODE -ne 0) { throw 'Stop: backend tests failed.' }
php vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
if ($LASTEXITCODE -ne 0) { throw 'Stop: PHPStan failed.' }
```

Mỗi lệnh cần lưu exit code/stdout/stderr riêng; nếu bước trước thất bại thì dừng, không xem lệnh cuối thành công là cả chuỗi thành công. Log cần loại secret; không đóng gói `.env` hay dữ liệu cá nhân.

## 10. Điều kiện chuyển giai đoạn và bàn giao

Giai đoạn 0 chỉ đạt khi có baseline runtime trên source gốc, cấu hình đủ rõ để lặp lại và phân loại test fail/skip/warning. Lỗi có sẵn phải ghi riêng; không gộp fix vào patch tổ chức code. Lỗi chặn hợp đồng Foundation phải được xử lý riêng trước khi nghiệm thu pilot.

Pilot chỉ đạt khi ma trận C01–C20 có bằng chứng, full regression không có hồi quy mới, và không có cập nhật bên ngoài allowlist. Test skip hoặc chưa chạy ở hợp đồng bắt buộc không được tính là đạt.

Bản code sau này cần bàn giao patch dựa trên v1.1.5, source archive, danh sách file đổi, log test trước/sau và hướng dẫn rollback. Bản baseline dùng kiểm thử phải là bản nguồn đã xác minh, không dùng source local có thay đổi chưa kiểm soát.

Rollback thuần code: trở lại artifact/commit trước pilot, không rollback migration hay xóa dữ liệu. Khi triển khai code thực tế, phải bảo đảm wrapper và implementation mới được đưa lên cùng artifact; không để request chạy giữa hai bản code.

Sau pilot: Organization/Contact/HR/Settings từng gói; rồi IAM/Workflow/Numbering; sau đó chuẩn hóa Product/Pricing; cuối cùng dọn wrapper và tài liệu. Không tự chạy sang giai đoạn kế tiếp khi gói trước chưa đạt.

## 11. Kết quả tự rà soát đặc tả

Đã đối chiếu mục tiêu/phạm vi, file map, hợp đồng public, ranh giới dependency, ngoại lệ chuyển tiếp, ma trận test và rollback. Không có placeholder thiết kế chưa chọn. Các mục chưa chạy là trạng thái bằng chứng runtime, không phải cam kết test đã đạt.

Đặc tả này là tài liệu rà soát trước kế hoạch thay đổi chi tiết. Gói hiện tại chỉ chứa tài liệu và evidence; **không có patch production để áp dụng**.
