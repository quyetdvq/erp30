# 10 — P1 Documentation Drift Register (P1 ↔ Code)

**Nguyên tắc:** P1 được bảo tồn ở `docs/architecture/` và `docs/implementation/` như hồ sơ lịch sử. Register này xác định những điểm **có bằng chứng sai lệch cụ thể**, không phê phán chung chung toàn bộ P1 và không tự chuyển code thành business-approved requirements.

| ID / độ quan trọng | P1 source | Bằng chứng ở code hiện tại | Diễn giải / hành động |
|---|---|---|---|
| **DR-001 / HIGH** | [`05_P1_API_Design_v1.8.md`](../architecture/05_P1_API_Design_v1.8.md) | 3 route `GET/POST/DELETE /products/{id}/image` trong [`Routes.php`](../../backend/app/Config/Routes.php); controller [`ProductImages.php`](../../backend/app/Controllers/Api/ProductImages.php); migration 000017 | API P1 không đề cập sản phẩm image endpoints; bộ as-built/API + OpenAPI là tham chiếu triển khai |
| **DR-002 / HIGH** | [`02_P1_Database_Design_v1.15.md`](../architecture/02_P1_Database_Design_v1.15.md) | Migration [`000017`](../../backend/app/Database/Migrations/2026-10-12-000017_AddProductRepresentativeImage.php) thêm `image_filename`, `image_mime`, `image_size_bytes` | DBD P1 không có 3 field; kiểm migrations thực trước dùng DB spec |
| **DR-003 / CRITICAL (security)** | [`01_P1_Functional_Requirements_Specification_v1.12.md`](../architecture/01_P1_Functional_Requirements_Specification_v1.12.md), [`05_P1_API_Design_v1.8.md`](../architecture/05_P1_API_Design_v1.8.md) | Migration [`000015`](../../backend/app/Database/Migrations/2026-10-11-000015_SplitContactAuthorizationResources.php), [`ContactAuthorizationRegistry`](../../backend/app/Authorization/ContactAuthorizationRegistry.php) | P1 không đề cập `organization_contact` / `employee_contact`; legacy `contact` đã bị retire, phải cập nhật IAM documentation và kiểm migrate/grant |
| **DR-004 / HIGH** | [`05_P1_API_Design_v1.8.md`](../architecture/05_P1_API_Design_v1.8.md), [`06_P1_UI_Design_v1.9.md`](../architecture/06_P1_UI_Design_v1.9.md) | `/business-workflow/{resource}/states|{id}/options|.../actions/{action}`; [`BusinessWorkflowActions.php`](../../backend/app/Services/BusinessWorkflowActions.php) | P1 không mô tả generic business workflow read/execute surface; native vs lifecycle semantics phải lấy theo as-built |
| **DR-005 / HIGH** | [`02_P1_Database_Design_v1.15.md`](../architecture/02_P1_Database_Design_v1.15.md), [`03_P1_Implementation_Contract_v1.10.md`](../architecture/03_P1_Implementation_Contract_v1.10.md) | Migration [`000014`](../../backend/app/Database/Migrations/2026-10-11-000014_AddIndependentPolicyResourceStatuses.php) | Quản lý Policy/Resource `status` độc lập; đọc tương ứng `07_IAM_AND_WORKFLOW.md` |
| **DR-006 / MEDIUM** | [`02_P1_Database_Design_v1.15.md`](../architecture/02_P1_Database_Design_v1.15.md) | Migration [`000016`](../../backend/app/Database/Migrations/2026-10-12-000016_WidenBusinessWorkflowStatusCodes.php) | 11 business status columns được nới lên VARCHAR(50); xác nhận DB đang migrate ở production |
| **DR-007 / MEDIUM** | [`06_P1_UI_Design_v1.9.md`](../architecture/06_P1_UI_Design_v1.9.md) | [`ProductEditorView.vue`](../../frontend/src/features/catalog/pages/ProductEditorView.vue), [`productImage.ts`](../../frontend/src/services/productImage.ts), FE E2E `fe063-product-image-child-actions.spec.ts` | UI P1 chưa mô tả image surface; rà soát UX riêng cho upload/removal, CHILD & permission |
| **DR-008 / PROCESS** | P1 phase plan/contract | Current source có 149 OpenAPI operations khớp backend route, 29 Vue routes, 17 migrations, 3 OPTIONS; catalogue generated | Không dùng “P1 phase task missing” để suy ra feature chưa có. Dùng feature inventory và live QA để tìm gap thực |

## Cách kiểm chứng từng mục

1. **Phương pháp:** đối chiếu string/heading thực tế trong các file P1 nêu trên với migration route/service/UI có tên và code cụ thể. Các tài liệu `P1 API/DB/UI/FRS/Contract` được tra tìm literal `image_filename`, `/image`, `business-workflow`, `organization_contact`, `employee_contact`; không tìm thấy các identifier mới tương ứng.
2. **Không diễn giải quá mức:** không chứng minh cả P1 sai. P1 Database Design *có* nhắc `normalized_state_id` (000012); không được đưa 000012 vào danh sách phần bị bỏ sót hoàn toàn. P1 đã có import, product, security nền tảng; không gán các module đó là phát sinh hoàn toàn.
3. **OpenAPI là trường hợp đặc biệt:** contract [`openapi.yaml`](../../contracts/openapi/openapi.yaml) **khớp 149/149 method/path với routes**; lệch là giữa *P1 narrative design* và code/contract mới, **không** chứng minh lỗi path-level API contract hiện hành.
4. **Runtime version drift chưa xác định:** cần Git upstream commit + schema migration status + deployed artifact để phát hiện source ↔ môi trường khác nhau; ZIP header commit chưa đối chiếu remote.

## Phân biệt `DR` với bug và yêu cầu mới

`DR-*` là lệch tài liệu. Ví dụ DR-003 là thay đổi security cần mô tả, **không** đủ bằng chứng kết luận migration ở production đã an toàn; một regression khi thử thật phải mở `BUG-*`. Policy no-match/precedence có rủi ro cần review ở [07](07_IAM_AND_WORKFLOW.md), **chưa** kết luận lỗi security. Ý tưởng ngoài feature inventory là `CR-*` riêng, không làm mờ as-built.

## Quyết định sử dụng từ thời điểm baseline

- **Tra cứu chức năng hiện hành:** tài liệu [01](01_FUNCTIONAL_AS_BUILT.md) và [catalogue](reference/API_ENDPOINTS.md).
- **Thiết kế DB:** [02](02_DATA_ARCHITECTURE.md) + migrations thật, không coi P1 DBD là schema hiện hành.
- **API/UI:** [05](05_API_CONTRACT.md) + [06](06_FRONTEND_UI.md) + OpenAPI/router.
- **Security/workflow:** [07](07_IAM_AND_WORKFLOW.md) + server authorization/repository.
- **Thay đổi mới:** [04](04_EVOLUTION_RULES.md) và CR record, P1 giữ nguyên archival.
