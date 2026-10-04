# Migration Review Report

## 1. Tổng quan

**Phạm vi:** đối chiếu Database Design v1.2 với toàn bộ PHP migration trong `backend/app/Database/Migrations`.

**Tài liệu thiết kế:** path được yêu cầu `docs/Mini/_ERP/_Authorization/_Database/_Design/_v1.2.md` không tồn tại. Review dùng file thực tế `docs/Mini_ERP_Authorization_Database_Design_v1.2.md`.

**Số lượng:** 18 migration PHP (file `.gitkeep` không phải migration).

**Trạng thái tổng thể: `NOT READY - NEED ADJUSTMENT BEFORE CONTINUE`.**

Các nguyên nhân chặn:

1. Tất cả file dùng version `20260928001` ... `20260928018` (11 chữ số), không khớp regex migration của CodeIgniter 4.7.4. `php spark migrate:status` không liệt kê các migration này; output chỉ có migration Shield/Settings.
2. 15/18 bảng thiếu phần lớn column nghiệp vụ, foreign key và index của Design v1.2. Ba bảng đầu chỉ đạt một phần.
3. Chỉ có một foreign key vật lý (`wards.province_id`); các quan hệ organization, employee, lifecycle, resource, role và policy chưa được tạo.

**Kiểm tra đã chạy:**

- `php -l` trên cả 18 file: PASS.
- `php spark migrate:status`: chạy được nhưng không nhận diện file Mini ERP.
- Không chạy `migrate`, `migrate:refresh`, `migrate:rollback`; không thay đổi database.
- Checkout không có metadata Git tại `D:\XAMPP\htdocs\erp`, nên không thể ghi nhận branch/HEAD.

## 2. Các điểm đúng

- Có đủ 18 logical table name và tên file thể hiện đúng thứ tự 001–018 của Design: provinces, wards, contacts, organizations, departments, positions, employees, employee_assignments, lifecycles, workflow_states, lifecycle_states, resources, resource_actions, roles, role_permissions, policies, policy_actions, authorization_logs.
- Migration dùng logical table name và không hard-code prefix. `Config\Database::$default['DBPrefix']` mặc định rỗng; `.env` đặt `database.default.DBPrefix = dinktech_`, nên Forge có thể áp dụng prefix runtime đúng convention.
- 18 file đều khai báo `id` `BIGINT UNSIGNED` `auto_increment` và `addKey('id', true)`; mỗi file có `down()` drop đúng table; tất cả pass PHP syntax lint.
- `provinces` có các column chính và unique `code`; `wards` có column chính, FK `province_id -> provinces.id`, unique `code`.
- `contacts` có đầy đủ polymorphic columns và composite index `(contact_type, contact_ref_id)`; không tạo FK `contact_ref_id` tới employees/organizations, đúng thiết kế polymorphic.
- `departments`/`positions` hiện không có `organization_id`; `employees` hiện không có `contact_id`, phù hợp các quyết định “không có” của Design.
- Không thấy bảng `permissions`, `resource_instance_id` hoặc condition rule trong migrations hiện có, phù hợp các quyết định loại trừ của Design.

Lưu ý: nhận định “mọi bảng có id PK” không làm `role_permissions` đúng Design, vì Design chỉ khai báo `role_id` và `resource_action_id` cho bảng liên kết này và không khai báo surrogate `id`.

## 3. Các vấn đề phát hiện

### Critical

**MIG-001 — Filename không được CI4 nhận diện**

- **File:** toàn bộ `20260928001_*.php` đến `20260928018_*.php`.
- **Hiện trạng:** version 11 chữ số; `Config\Migrations::$timestampFormat` là `Y-m-d-His_`, MigrationRunner yêu cầu `YYYYMMDDHHIISS_` hoặc dạng phân cách.
- **Mong muốn:** đổi tên về format hỗ trợ, ví dụ `2026-09-28-000001_CreateProvincesTable.php` ... `2026-09-28-000018_CreateAuthorizationLogsTable.php` (hoặc `YmdHis_` đúng 14 chữ số); xác nhận lại bằng `php spark migrate:status`.
- **Ảnh hưởng:** runtime bỏ qua toàn bộ schema Mini ERP, nên dependency order không có hiệu lực.

**MIG-002 — organizations thiếu toàn bộ Organization Context**

- **File:** `20260928004_CreateOrganizationsTable.php:10-17`.
- **Table/column:** thiếu `parent_id`, `type`, `code`, `name`, `status`, `active_from`, `active_to`.
- **Hiện trạng:** chỉ có id/timestamps; thiếu self-FK `parent_id -> organizations.id`, unique `code`, index `parent_id`, `type`, `status`.
- **Mong muốn:** type chỉ `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`; code unique; hỗ trợ root `parent_id` null và status/date range theo Design.

**MIG-003 — Department/Position master chưa có schema**

- **Files:** `20260928005_CreateDepartmentsTable.php:10-17`, `20260928006_CreatePositionsTable.php:10-17`.
- **Hiện trạng:** departments thiếu `parent_id`, `code`, `name`, `description`, `status`, unique/index/self-FK; positions thiếu `code`, `name`, `level`, `description`, `status`, unique code.
- **Mong muốn:** hai master độc lập, không có `organization_id`; department có hierarchy, position có level và không mapping permission.

**MIG-004 — Employee và employee_assignments chưa có schema/quan hệ**

- **Files:** `20260928007_CreateEmployeesTable.php:10-17`, `20260928008_CreateEmployeeAssignmentsTable.php:10-17`.
- **Hiện trạng:** employees thiếu `user_id`, `employee_code`, `status`, unique employee_code và unique user mapping; assignments thiếu `employee_id`, `organization_id`, `department_id`, `position_id`, `is_primary`, `effective_from`, `effective_to`, `status` và cả 4 FK.
- **Mong muốn:** đúng 8 column assignment, hỗ trợ nhiều assignment/active assignment; có cơ chế bảo đảm primary assignment theo thời điểm; không thêm `employees.contact_id`.

**MIG-005 — Lifecycle/Workflow/Resource chưa có domain columns**

- **Files:** `20260928009_CreateLifecyclesTable.php`, `20260928010_CreateWorkflowStatesTable.php`, `20260928011_CreateLifecycleStatesTable.php`, `20260928012_CreateResourcesTable.php`, `20260928013_CreateResourceActionsTable.php` (đều chỉ có id/timestamps).
- **Hiện trạng:** thiếu lifecycle `code/name/description/status`; workflow state `code/name/description/status` và unique code; lifecycle mapping `lifecycle_id/state_id/sequence/is_initial/is_final`, 2 FK và unique composite; resources `lifecycle_id/type/name/domain`, FK lifecycle và unique type; resource_actions `resource_id/code/description`, FK và unique `(resource_id, code)`.
- **Mong muốn:** hoàn chỉnh chuỗi `Resource -> Lifecycle -> Lifecycle State -> Workflow State`; permission lấy từ resource action, không có permission table riêng.

**MIG-006 — Role domain chưa có relationship**

- **Files:** `20260928014_CreateRolesTable.php:10-17`, `20260928015_CreateRolePermissionsTable.php:10-17`.
- **Hiện trạng:** roles thiếu `name`, `description`; role_permissions thiếu `role_id`, `resource_action_id`, hai FK và composite PK/unique; actual thêm surrogate `id`, `created_at`, `updated_at` không có trong Design.
- **Mong muốn:** role có name/description; bảng liên kết có đúng hai key và không có organization scope.

**MIG-007 — Policy và policy_actions chưa có authorization contract**

- **Files:** `20260928016_CreatePoliciesTable.php:10-17`, `20260928017_CreatePolicyActionsTable.php:10-17`.
- **Hiện trạng:** policies thiếu `organization_id`, `resource_id`, `state_id`, `participant_type`, `participant_ref_id`, `created_by`, `updated_by`, các FK và participant polymorphic reference; policy_actions thiếu `policy_id`, `resource_action_id`, `effect` và hai FK.
- **Mong muốn:** policy ở resource type level; participant chỉ `USER/ROLE/POSITION/DEPARTMENT/ORGANIZATION`; effect chỉ `ALLOW/DENY/ABSOLUTE_DENY`; không có `resource_instance_id` hoặc condition rule.

**MIG-008 — authorization_logs không lưu được audit decision**

- **File:** `20260928018_CreateAuthorizationLogsTable.php:10-17`.
- **Hiện trạng:** thiếu `user_id`, `organization_id`, `resource_id`, `action`, `state_id`, `decision`, `matched_policy_id`, `reason`, `request_id`; chỉ có id/timestamps và thêm `updated_at` không có trong Design.
- **Mong muốn:** lưu approve/delete/state change (không log View/List), đủ decision context và request correlation; các FK organization/resource/state/policy phải được chốt.

### Warning

**MIG-009 — Index thiếu so với Design**

- `20260928001_CreateProvincesTable.php:18-21`: thiếu `INDEX(name)`.
- `20260928003_CreateContactsTable.php:26-29`: thiếu `INDEX(email)`, `INDEX(phone)`, `INDEX(mobile)`, `INDEX(province_id)`, `INDEX(ward_id)`.
- Các unique/search/index còn thiếu trong organizations, departments, positions, resources, resource_actions, workflow_states, lifecycle_states, role_permissions đều là hệ quả trực tiếp của MIG-002 đến MIG-007.

**MIG-010 — Foreign key coverage chưa đủ**

- Chỉ `wards.province_id` có FK trong `20260928002_CreateWardsTable.php:19-21`.
- Thiếu FK cho organizations self-reference; employee assignments (4 FK); lifecycle_states (2 FK); resources; role_permissions (2 FK); policies; policy_actions; authorization_logs.
- `contacts.province_id`/`ward_id` được Design gọi là Province/Ward Reference nhưng migration không tạo FK. Cần chốt physical FK và delete rule; tuyệt đối không tạo FK cho `contacts.contact_ref_id`.

**MIG-011 — Timestamp/nullability/extra columns chưa thống nhất**

- Design không chỉ định rõ NULL/NOT NULL, default, unsigned và delete rule cho phần lớn field; migration hiện đặt timestamps `null => true`. Cần chốt contract trước dữ liệu thật.
- `status` mới có ở provinces/wards với default `ACTIVE`; status bắt buộc của organizations, departments, positions, employees, employee_assignments, lifecycles và workflow_states đều thiếu.
- Migration thêm timestamps cho `resource_actions`, `roles`, `role_permissions`, `lifecycle_states`, `policy_actions`, `authorization_logs`, trong khi Design không khai báo các field đó (trừ `authorization_logs.created_at`). Nếu strict schema, phải bỏ hoặc cập nhật Design.

### Suggestion / clarification

**MIG-012 — Delete rule của wards và constraint value cần được chốt**

- Design chưa quy định delete rule cho `wards.province_id`; `CASCADE` hiện tại có rủi ro xóa hàng loạt master địa lý, nên xem xét `RESTRICT/NO ACTION` hoặc chỉ deactivation.
- Design ghi giá trị cố định cho organization type, status, participant type và effect nhưng chưa nói rõ DB CHECK/ENUM hay application validation. Cần quyết định nhất quán.
- Checklist review nêu `resources.status`, nhưng Design mục 6.1 không có column này. Không tự thêm column trước khi chủ sở hữu Design chốt.

## 4. Đề xuất thay đổi

Chưa thực hiện trong lượt review này.

1. Chuẩn hóa filename 18 migration và xác nhận `migrate:status` nhận đủ 18 file.
2. Hoàn thiện migrations 001–003: index, location FK/delete rule và giữ nguyên polymorphic contact reference.
3. Hoàn thiện 004–008: organization hierarchy/type/status/date, department/position master, employee, assignment và toàn bộ FK/unique/index.
4. Hoàn thiện 009–013: lifecycle/workflow catalog, mapping, resource và resource actions.
5. Hoàn thiện 014–017: roles, composite role-permission link, policies và policy actions; chốt target của `policies.state_id` và identity references.
6. Hoàn thiện 018: authorization log fields/index/FK và timestamp strictness.
7. Thêm schema/migration tests trên MySQL và test database có prefix để kiểm tra PK/FK, unique/index, nullable/default, cascade và dependency order.
8. Không sửa migration đã applied; nếu đã có history phải dùng forward migration tương ứng.

## 5. Kết luận

**Đánh giá: `NEED ADJUSTMENT BEFORE CONTINUE`.**

Migration chưa Ready for Development/Integration theo Design v1.2. Bắt buộc xử lý lỗi nhận diện filename và bổ sung schema/foreign key/index cho Organization, Employee Assignment, Lifecycle/Resource, Role/Policy và Authorization Log trước khi xây dựng service/API dựa trên schema này. Báo cáo này là review evidence; chưa có migration code nào bị chỉnh sửa.
