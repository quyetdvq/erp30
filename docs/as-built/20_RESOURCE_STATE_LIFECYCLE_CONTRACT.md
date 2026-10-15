# 20 — Quy tắc Resource State và Lifecycle (chốt 2026-10-15)

**Nguồn quy tắc:** xác nhận nghiệp vụ của chủ dự án ngày 2026-10-15. Đây là hợp đồng State cần hướng tới, **không dựa vào tài liệu P1 cũ**. Khi mô tả *hành vi đã triển khai*, phải kiểm chứng source/runtime tương ứng, không suy luận từ tài liệu này rằng môi trường production đã được migrate.

## 1. Chính xác 10 Resource không sử dụng Lifecycle/Transition

| Resource | Cơ chế State | Lifecycle |
|---|---|---|
| `authorization_group` | Native State | Không sử dụng |
| `authorization_log` | Chỉ **All States** (không có State cụ thể) | Không sử dụng |
| `display_setting` | Chỉ **All States** (không có State cụ thể) | Không sử dụng |
| `employee_assignment` | Native State | Không sử dụng |
| `import_job` | Native State | Không sử dụng |
| `lifecycle` | Native State (tự quản lý) | Không sử dụng |
| `policy` | Native State `ACTIVE` / `INACTIVE` | Không sử dụng |
| `user` | Native State (Shield; trạng thái PBAC còn phụ thuộc `active`) | Không sử dụng |
| `workflow_state` | Native State (tự quản lý) | Không sử dụng |
| `resource` | Native State `ACTIVE` / `INACTIVE` | Không sử dụng |

Định nghĩa trong code: `backend/app/Services/ResourceNativeStateCatalog.php`, hằng `INDEPENDENT_RESOURCES`. **Không thêm Resource thứ 11 vào danh sách ngoại lệ** chỉ vì bảng có cột `status` hoặc đang chưa được gắn Lifecycle. `workflow_states.status` là cờ kích hoạt **định nghĩa** State, không phải State của đối tượng được phân quyền; hai khái niệm không được đánh đồng.

Các Resource All States-only không được tạo hoặc so khớp `state_id` cụ thể trong PBAC. Resource Native State cũng phải kiểm tra trạng thái đối tượng từ dữ liệu đáng tin cậy, không nhận hint `state_id` từ client làm nguồn thẩm quyền.

## 2. Tất cả Resource còn lại tuân theo Lifecycle và Transition

**Quy tắc nghiệp vụ đã chốt:** Resource không thuộc mười ngoại lệ được quản lý bằng Lifecycle; State của record thuộc các Workflow State thành viên **đang ACTIVE** trong Lifecycle đang gắn với Resource. Lifecycle xác định State khởi tạo; thay đổi State phải đi qua Transition hợp lệ và kiểm tra quyền (`setstate` và action tương ứng). Ví dụ: `province`, `ward`, `organization`, `organization_contact`, `employee_contact`, `department`, `position`, `employee`, `product_group`, `product_uom`, `product`, `price_list`.

Không giới hạn Lifecycle State của các resource nghiệp vụ vào enum `ACTIVE`/`INACTIVE` hay các native code cũ: Lifecycle có thể dùng `DRAFT`, `APPROVED` hoặc mã State hợp lệ khác. Không suy luận một Resource là native-independent chỉ vì có mã State giống các Resource Native.

`contact` cũ là Resource đã ngừng cấp quyền (legacy authorization key); cần kiểm tra dữ liệu lịch sử và migration Contact split riêng trước khi cấu hình Lifecycle hoặc dọn sạch resource này.

## 3. Tình trạng runtime v1.1.3 — điểm lệch cần quản lý

**Đã xác nhận tĩnh trên source:** danh sách mười ngoại lệ đang đúng. Tuy nhiên, dữ liệu `resources.lifecycle_id` vẫn là nullable và `ResourceActionSeeder` không tự gắn Lifecycle cho các resource nghiệp vụ. Trong tình huống non-exception resource **chưa có** `lifecycle_id`, một số luồng `ResourceWorkflowStateResolver`, `WorkflowBusinessStatus`, `BusinessWorkflowActions`, service `set-state` vẫn chạy theo nhánh *legacy native fallback*. Các test legacy cũng ghi nhận hành vi này (ví dụ `P1Wf006StateAwarePbacAndLocationTransitionsTest::testResourceWithoutLifecycleKeepsLegacyStateMutation`).

**Đây là chênh lệch giữa quy tắc mục tiêu và implementation/configuration hiện có, không phải ngoại lệ thứ 11.** Không thể chỉ ép non-exception `lifecycle_id` NOT NULL, xóa native fallback hoặc tự gán một Lifecycle tuỳ tiện: có rủi ro phá dữ liệu State hiện hữu, policy theo State, quyền chuyển State, cũng như các luồng nhập liệu đang chạy. Cần chốt kế hoạch chuyển đổi dữ liệu/transition cho từng resource và chạy regression trước khi tắt fallback.

Bản sửa PHPUnit ngày 2026-10-15:

- `P1Emp001EmployeeRepositoryTest` tạo Resource Catalog + Lifecycle `employee` thực tế để test manager. Trạng thái manager INACTIVE được thiết lập qua fixture repository **chỉ trong test**, không mở đường bypass Lifecycle tại service.
- `P1Api090ContractRegressionTest` cho phép endpoint `business-workflow/{resource}/{id}/options` đã hiện diện trong Routes và OpenAPI.
- `BusinessWorkflowWriteBoundary` dùng chung `ResourceWorkflowStateResolver::lifecycleId()`, đảm bảo thiếu Resource Catalog ném `WF_RESOURCE_LIFECYCLE_INVALID` thay vì ngầm coi là native/unbound; đồng thời vẫn từ chối lifecycle gắn sai vào 10 ngoại lệ.
- `erp:workflow-state-audit` (read-only) kiểm kê **toàn bộ** Resource và cảnh báo `LIFECYCLE_NOT_CONFIGURED` đối với mọi non-exception Resource chưa gắn Lifecycle; không tự thay đổi DB.

## 4. Quy trình chuẩn bị loại bỏ legacy fallback (CHƯA triển khai tự động)

1. Chạy `php spark erp:workflow-state-audit` trên môi trường kiểm thử/snapshot dữ liệu. Xác định các Resource chưa có Lifecycle, State của record không thuộc membership, State inactive và PBAC policy bị ảnh hưởng.
2. Với từng non-exception Resource, định nghĩa Lifecycle, initial State, membership ACTIVE và các Transition + action permissions đúng nghiệp vụ. Không tự gán `ACTIVE` cho mọi bản ghi.
3. Đối chiếu trạng thái các record hiện hữu và policy (kể cả policy INACTIVE có thể kích hoạt lại). Giải quyết mismatch trước khi gắn/chuyển Lifecycle, kiểm tra định danh owner cho Contact split.
4. Chạy migration có kiểm soát, có backup/rollback; chỉ sau đó mới thay đổi runtime để cấm native fallback cho toàn bộ Resource còn lại và cập nhật các legacy tests tương ứng.
5. Chạy toàn bộ PHPUnit và kiểm thử integration/API/FE cho create, edit, list/PBAC, setstate, workflow options và import/background jobs.

**Giới hạn xác minh:** thay đổi code được phân tích và lint từ bản ZIP. Không suy diễn kết quả PHPUnit, migration và dữ liệu production nếu chưa chạy trên môi trường có Composer `vendor` và database test.
