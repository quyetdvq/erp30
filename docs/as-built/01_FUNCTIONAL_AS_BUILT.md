# 01 — Functional Specification (code-first / hiện có)

**Mức chứng cứ:** [S], trừ trường hợp nêu rõ. Mỗi mục mô tả chức năng **có implementation entrypoint**, không phải yêu cầu phát triển mới hoặc nghiệm thu UAT.

## 1. Danh mục chức năng theo module

| Module | Đầu vào / thao tác hiện có | Service/endpoint chính |
|---|---|---|
| Identity | Đăng nhập email/username + password, phiên Shield, logout, me, sửa profile | [`Auth.php`](../../backend/app/Controllers/Api/Auth.php), `/api/v1/auth/*` |
| Foundation | CRUD/đổi trạng thái Province, Ward | [`Api010FoundationOrganizationContactService.php`](../../backend/app/Services/Api010FoundationOrganizationContactService.php), `/provinces`, `/wards` |
| Organization | Danh mục tổ chức nhiều cấp, tree, resolve label, CRUD/đổi trạng thái | [`OrganizationService.php`](../../backend/app/Services/OrganizationService.php), `/organizations*` |
| Contacts | CRUD/đổi trạng thái, chủ sở hữu Organization hoặc Employee; tách resource PBAC theo owner | [`ContactService.php`](../../backend/app/Services/ContactService.php), [`ContactAuthorizationRegistry.php`](../../backend/app/Authorization/ContactAuthorizationRegistry.php) |
| HR structure | Department, Position, Employee và assignment theo org/role/hiệu lực; CRUD/đổi trạng thái | [`Api020EmployeeStructureService.php`](../../backend/app/Services/Api020EmployeeStructureService.php) |
| Security admin | User, direct permission, authorization group member/permission; resource/actions; policies; logs | [`Api030AuthorizationWorkflowService.php`](../../backend/app/Services/Api030AuthorizationWorkflowService.php) |
| Workflow | Workflow state, lifecycle, cấu hình state/transition; query & execute business workflow | [`LifecycleService.php`](../../backend/app/Services/LifecycleService.php), [`BusinessWorkflowActions.php`](../../backend/app/Services/BusinessWorkflowActions.php) |
| Auto-numbering | Quản trị series theo Resource, validate, cấp mã qua allocator/adapter | [`Api040AutoNumberingService.php`](../../backend/app/Services/Api040AutoNumberingService.php) |
| Product group | Phân cấp group (`parent_id`), create/edit/status | [`ProductGroupService.php`](../../backend/app/Modules/Product/ProductGroup/ProductGroupService.php) |
| Product/UOM | Master UOM, product, base/sales UOM conversions, vai trò SIMPLE/PARENT/CHILD/COMBO, composition | [`ProductMasterService.php`](../../backend/app/Modules/Product/Product/ProductMasterService.php), [`ProductRoleService.php`](../../backend/app/Modules/Product/Product/ProductRoleService.php) |
| Product lookups | Bounded options, batch resolve, relations preview, sales-UOM resolve (PBAC-filtered) | [`ProductLookupService.php`](../../backend/app/Services/ProductLookupService.php) |
| Product image | Một ảnh đại diện, tải lên/thay/xóa/trả binary, quyền view/edit | [`ProductImageService.php`](../../backend/app/Services/ProductImageService.php) |
| Pricing | Quản lý price lists/items; hàm resolve giá theo cấp ưu tiên | [`PriceListService.php`](../../backend/app/Modules/Pricing/PriceList/PriceListService.php), [`PricingService.php`](../../backend/app/Modules/Pricing/Resolution/PricingService.php) |
| Product import | Start upload, CSV/XLSX, create-only/update-only/upsert, result jobs + row results | [`Api060ProductImportService.php`](../../backend/app/Services/Api060ProductImportService.php) |
| Display settings | Runtime/definitions/values/update/reset; nguồn System/Organization; sở thích hiển thị local per user | [`ApiDisplaySettingsService.php`](../../backend/app/Services/ApiDisplaySettingsService.php), [`displaySettings.ts`](../../frontend/src/stores/displaySettings.ts) |

> `CRUD` tại đây là tập thao tác *list / create / read / update* và đôi khi *set-state*, **không có nghĩa** endpoint DELETE có sẵn. Danh sách HTTP thực: [reference/API_ENDPOINTS.md](reference/API_ENDPOINTS.md).

## 2. Organization, HR và Contact

**Organization:** `organizations.parent_id` định nghĩa cây; `EvaluationOrganizationResolver` nhận tổ chức theo persisted target/đầu vào/hint nhưng phải qua resolver từng resource; danh sách `organizations/tree` chịu phạm vi truy cập. Các loại được resolver chấp nhận: `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`. Tránh giả định mọi đường dẫn tổ chức đều có chiều sâu cố định. Nguồn: [`EvaluationOrganizationResolver.php`](../../backend/app/Authorization/EvaluationOrganizationResolver.php), [`OrganizationService.php`](../../backend/app/Services/OrganizationService.php).

**Employee:** `employees.user_id` kết nối với Shield user khi có; `employee_assignments` gắn employee với organization/department/position và thời gian `effective_from/effective_to`. Không suy luận quyền từ username đơn thuần. Eligibility và scope phụ thuộc employee ACTIVE + assignment effective: xem [07](07_IAM_AND_WORKFLOW.md). Nguồn: [`EmployeeAssignmentService.php`](../../backend/app/Services/EmployeeAssignmentService.php).

**Contact:** một bảng `contacts` có `contact_type`/`contact_ref_id`; quyền **không còn** qua resource chung `contact`. Owner `ORGANIZATION` dùng `organization_contact`, `EMPLOYEE` dùng `employee_contact`. Migration 000015 giữ lịch sử grant/policy có chủ ý và đưa legacy contact về inactive. Nguồn: [`ContactAuthorizationRegistry.php`](../../backend/app/Authorization/ContactAuthorizationRegistry.php), [`2026-10-11-000015...php`](../../backend/app/Database/Migrations/2026-10-11-000015_SplitContactAuthorizationResources.php).

## 3. Security & Permission

`auth/me` trả `user` và `base_permissions` snapshot. Quyền cơ sở hình thành từ trực tiếp + group; quyền truy cập bản ghi thực tế đi tiếp qua PBAC theo ngữ cảnh tổ chức, employee effective assignment, policy participant, state và effect. `User` API có create/update/setstate/reset-password/replace direct permissions; security group có member & permission endpoints; policy/resource có trạng thái ACTIVE/INACTIVE độc lập. Nguồn: [`Auth.php`](../../backend/app/Controllers/Api/Auth.php), [`AuthorizationService.php`](../../backend/app/Authorization/AuthorizationService.php).

## 4. Workflow / Lifecycle

**Native independent resources** là `authorization_group`, `authorization_log`, `display_setting`, `employee_assignment`, `import_job`, `lifecycle`, `policy`, `user`, `workflow_state`, `resource`. Các resource khác thuộc business có thể chọn Lifecycle; khi đã được gắn Lifecycle, mã trạng thái hợp lệ đến từ các state active trong lifecycle, **không mặc định** ép ACTIVE/INACTIVE/CLOSED. Nếu chưa gắn, dịch vụ dùng tập trạng thái native tương ứng. Nguồn: [`ResourceNativeStateCatalog.php`](../../backend/app/Services/ResourceNativeStateCatalog.php), [`ResourceWorkflowStateResolver.php`](../../backend/app/Services/ResourceWorkflowStateResolver.php).

Read `business-workflow/{resource}/states` phục vụ metadata; `/{id}/options` trả những transition actor có quyền tại current persisted state; execute kiểm tra `setstate` và action chuyên biệt (nếu lifecycle), đồng thời khóa state transition theo boundary. Không cho client tự khẳng định state hiện tại để vượt PBAC. Nguồn: [`BusinessWorkflowActions.php`](../../backend/app/Services/BusinessWorkflowActions.php).

## 5. Product, combo, UOM và pricing

**Role được suy ra**, không phải trường `role` lưu vật lý: `product_type = 'COMBO'` → COMBO nếu cấu trúc hợp lệ; `STANDARD` + `parent_product_id` → CHILD; root STANDARD có child → PARENT; root STANDARD không child → SIMPLE. CHILD không có con; COMBO không thuộc cây parent/child. `full_name` của CHILD là tên cha + ` - ` + child name. Role SIMPLE/CHILD là `leaf_transactionable`, SIMPLE/CHILD/COMBO là `selectable` khi ACTIVE. Nguồn: [`ProductRoleService.php`](../../backend/app/Modules/Product/Product/ProductRoleService.php).

**Conversions và composition:** `product_uom_conversions` lưu conversion/loại UOM cho product; `product_combo_items` lưu component; mutation theo API PUT replace toàn bộ collection; các service chuyên biệt kiểm tra vai trò, tham chiếu và thành phần. Nguồn: [`ProductUomService.php`](../../backend/app/Modules/Product/Uom/ProductUomService.php), [`ComboCompositionService.php`](../../backend/app/Modules/Product/Combo/ComboCompositionService.php).

**Pricing order:** pricing resolver chọn price list phù hợp của **COMPANY** trước, sau đó **GROUP**; nếu không có dùng giá mặc định trên Product ở base UOM hoặc giá của UOM conversion ở selling UOM. Đây là *hàm resolve giá* trong backend, không phải public API riêng cho sales order. Nguồn: [`PricingService.php`](../../backend/app/Modules/Pricing/Resolution/PricingService.php).

**Ảnh đại diện sản phẩm:** hỗ trợ **một** ảnh/product, JPEG/PNG/WebP, tối đa **3 MiB**, cạnh ảnh ≤ 5000 px; file nằm dưới `WRITEPATH/uploads/product-images`, filename random dạng hex, không dùng public path; GET đòi `product.view`, POST/DELETE đòi `product.edit`. Nguồn: [`ProductImageStorage.php`](../../backend/app/Services/ProductImageStorage.php), [`ProductImageService.php`](../../backend/app/Services/ProductImageService.php).

## 6. Import là synchronous call, không phải queue worker

`POST /api/v1/products/import-jobs` nhận multipart `file` và `mode`, nội dung CSV/XLSX, 10 MiB/file và 5000 rows theo mặc định; mode `CREATE_ONLY`, `UPDATE_ONLY`, `UPSERT`. `ProductImportAuthorizationService::run()` ngay trong request: authorize → create job → mark PROCESSING → parse/validate/build dependency graph → mutate rows → persist row results → reconcile status. Thành công tổng thể là `COMPLETED` hoặc `COMPLETED_WITH_ERRORS`; exception đánh `FAILED` và trả lỗi. Không ghi thành tài liệu “upload rồi xử lý worker” khi code chưa có queue dispatch. Nguồn: [`ProductImportAuthorizationService.php`](../../backend/app/Modules/Product/Import/ProductImportAuthorizationService.php), [`Config/Import.php`](../../backend/app/Config/Import.php).

## 7. Display settings và mức ưu tiên

Registry có **15** key nguồn kiểm soát (timezone, date/time, number separators/precision, money formatting...). Server resolve Organization hiện tại → các ancestor → System → default; UI còn áp dụng user local preference từ trình duyệt theo `userId`. Các thiết lập này dành cho *presentation*, không sửa giá trị tiền/số lưu DB. Server storage sử dụng `CodeIgniter\\Settings` table `settings` do dependency cung cấp, **không thuộc 31 local tables**; các thay đổi setting ghi `configuration_audit_events`. Nguồn: [`DisplaySettingRegistry.php`](../../backend/app/Infrastructure/Settings/DisplaySettingRegistry.php), [`DisplaySettingsResolver.php`](../../backend/app/Infrastructure/Settings/DisplaySettingsResolver.php), [`CodeIgniterDisplaySettingsStorage.php`](../../backend/app/Infrastructure/Settings/CodeIgniterDisplaySettingsStorage.php), [`displaySettings.ts`](../../frontend/src/stores/displaySettings.ts).

## 8. Ranh giới tính năng chưa xác thực

Chưa chứng minh end-to-end các luồng bằng test runtime; chưa có hồ sơ đáp ứng performance, accessibility production hoặc UAT ký nhận tại snapshot này. Mỗi yêu cầu tính năng mới phải bổ sung use-case/acceptance mới dưới change request thay vì âm thầm “sửa” FRS theo P1 cũ. Xem [04](04_EVOLUTION_RULES.md) và [09](09_VERIFICATION_MATRIX.md).
