# DinkERP P2-SUP — Cross-document & Source Feasibility Audit

> **Doc:** P2-SUP-AUD-001 · **Version:** v1.0.1 · **Date:** 2026-10-29  
> **Review type:** Static documentation/source verification only, **no implementation**.  
> **Scope:** SUP design pack v1.0.0 (5 Markdown files), `docs/DinkERP_P2/modules/P2_SUP_INDEPENDENT_MODULE_SPEC.md` §1 and original eight AC; `docs/development-standards/01,02,03,05,06`; ERP hotfix ZIP `erp_v2.0.0_customer_required_phone_fixed.zip` SHA-256 `9af6a01ec451400c8d3cd580396b1e6d3b294c0073b4c0675532a9752bb4aa28`.  
> **Decision governance:** 12/12 Product decisions remain **APPROVED**; this review **does not** change business scope. Engineering signoffs/runtime gates remain **NOT_EVIDENCED / NOT_RUN**.

## 1. Tóm tắt phát hiện và xử lý

| ID | Severity | Phát hiện trong hồ sơ v1.0.0 / source thực tế | Hiệu đính v1.0.1 / trạng thái |
|---|---|---|---|
| **A01** | HIGH | SUP tài liệu đề xuất list `filter[status]`/`filter[company_id]`; Customer backend `CustomerSql::params()` chấp nhận **flat** `status`, `company_id`, `customer_group_code` và reject unknown key. Giao diện `ListQuery.filter` là state nội bộ, không nhất thiết query wire. | **FIXED IN DESIGN**: chọn flat filter cho SUP; adapter Vue chuyển `ListQuery.filter`; không hỗ trợ hai format ngầm. Khóa OpenAPI G2. |
| **A02** | MEDIUM | SUP default `page_size=15` khác `CustomerSql::params()` default **25**; UI chung có 10/15/20/25/50/100/200 nhưng CUS cap 100. | **FIXED**: SUP default 25, max 100; frontend dùng shared page-size policy <=100. |
| **A03** | HIGH | Approved DEC-05 manual code **khi không có default**; `CustomerNumberIssuer::issue()` hiện vẫn cho manual khi default ACTIVE `AUTO_IF_EMPTY`. Copy CUS sẽ **vi phạm quyết định SUP**. | **FIXED**: xác định precedence riêng; default cấu hình (kể cả inactive/lỗi) không cho fallback manual; thêm regression. Exact Numbering locking/series still **PC-04 PENDING**. |
| **A04** | HIGH | Dynamic initial State khác seeder CUS: `CustomerCoreSeeder::ensureLifecycle()` tạo/kiểm tra ACTIVE initial cố định. Copy seeder cho SUP có thể phủ định Lifecycle tùy biến. | **FIXED**: default SUP seed chỉ khi resource chưa có; custom binding giữ nguyên; conflict fail-closed, không rewrite State graph. **PC-05 PENDING**. |
| **A05** | HIGH | `ResourceStateRepository::transitionTarget()` gọi `ResourceStateValidator::transitionTarget()` chọn target chỉ khi có **đúng 1 transition** cho `(from, action)`; Lifecycle có nhiều nhánh `setstate` không vận hành tự động. | **FIXED / LIMIT DOCUMENTED**: SUP `set-state` fail-closed/409 khi ambiguous; hỗ trợ branching thực sự cần reviewed canonical Workflow change, **không** tự bypass. |
| **A06** | HIGH | DEC-08 ở v1.0.0 ghi CAS `setstate` là “nếu áp dụng”; ngân hàng PUT chưa có clear semantics. `CustomerService::setState()` hiện **không dùng expected_version**, nên copy behavior sẽ mâu thuẫn với SUP `row_version`. | **FIXED**: SUP SetState, PATCH, usage và bank PUT/clear đều require `expected_version`, một shared row_version, 409 stale. Không thay API Customer. |
| **A07** | MEDIUM | Một Bank optional nhưng bank endpoint cũ chỉ GET/PUT set, không định nghĩa xóa tài khoản đã nhập. Mask/reveal wire mơ hồ. | **FIXED**: PUT `{clear:true,expected_version}`; GET masked vs `?reveal=1` riêng, quyền, no-store, audit. Exact DTO **PC-07 PENDING**. |
| **A08** | **BLOCKER** | v1.0 hứa bank reveal/edit được audit nhưng chưa xác định durable sink phù hợp. Source `configuration_audit_events` là **configuration audit**, không có bằng chứng đây là store an toàn cho business bank PII events. | **NOT TECHNICALLY CLOSED**: PC-03 bắt buộc Security/DBA chọn canonical durable audit hoặc ADR kỹ thuật phạm vi hẹp cho bảng mới. Không có audit thì không release G1 bank. |
| **A09** | HIGH | SUP-DEC-10 chốt **hai bảng Core**, nhưng để audit bank chuẩn có thể cần bảng thứ ba — một xung đột schema tiềm ẩn nếu tự thêm mà không ADR. | **CONTROLLED BLOCKER**: không tự tạo bảng thứ ba; T01 dừng phần bank until Security/DBA chứng minh existing sink hoặc ký ADR bổ sung bảo mật. Không cần mở lại 12 lựa chọn Product khi không đổi nghiệp vụ. |
| **A10** | MEDIUM | Cùng chữ “Group”: Organization GROUP (PBAC/unique scope) và `supplier_group_code=STANDARD` (classification) dễ bị nhầm FK/scope. | **FIXED**: server-derived `group_organization_id` (canonical root), composite unique `(group_organization_id,supplier_code)`, `supplier_group_code` chỉ catalog; preflight single root. Không hứa multi-group PBAC. |
| **A11** | MEDIUM | Company Usage yêu cầu false ≥1 Company nhưng một số mô tả v0.1 chỉ bắt buộc khi status ACTIVE; G1 v1.0 nói Company Usage required khi tạo, kể cả DRAFT. | **FIXED**: `all_companies` boolean + `company_ids` explicit ngay create; true=>[], false=>1..512 unique ACTIVE COMPANY, cho mọi Supplier status. |
| **A12** | HIGH | P1 Contact runtime đã có ORG/EMP/CUS; `ContactSql` và M8.7 SQL audit cần owner Supplier mới. Trước đây từng có sai khác runtime/audit khi thiếu Customer scope. | **FIXED DESIGN + PENDING CODE**: bốn owner scopes ở runtime/audit, parent Supplier VIEW AND Contact VIEW, no COUNT/direct-ID leak, hồi quy 3 owner cũ; không sửa frozen nine-resource audit catalog chỉ vì SUP owner. |
| **A13** | MEDIUM | `status VARCHAR(20)` nhưng Workflow State catalog cho phép code tới 50 ký tự; nếu không validate trước có nguy cơ MariaDB truncation/lỗi 500. | **FIXED**: State code regex/byte <=20 trên create/filter/setstate, reject with stable domain response, no DB truncation. |
| **A14** | MEDIUM | G1 bank data optional bị hiểu nhầm bank capability optional; History còn ở P2 MUST, nhưng T10/T11 chờ PUR/FIN. | **FIXED CLARITY**: bản ghi Supplier không bắt buộc bank; **G1 release vẫn yêu cầu bank capability + security evidence**. History 3 AC **DEPENDENCY_DEFERRED**, không tuyên bố whole SUP CLOSED. |
| **A15** | MEDIUM | Source Customer Phone uniqueness `000025` và Type `ORGANIZATION` không phù hợp chính sách SUP (`Phone non-unique`; Type `COMPANY`). | **ALREADY CORRECT, PROTECTED**: không copy Customer DB unique/enum, thêm negative tests; SUP migration phải lấy ID mới sau `000025` trên DEV. |
| **A16** | MEDIUM | Numbering seeder CUS là opt-in và `BackendBootstrapSeeder` có call CUS theo bảng tồn tại. SUP plan cũ chưa diễn đạt rõ operator ownership của mặc định. | **FIXED**: resource/lifecycle seed idempotent, default series là **opt-in**; không auto-create trên migration/implicit bootstrap, không auto grant permissions. |
| **A17** | MEDIUM | Khi thiếu khóa bank, lỗi ngân hàng có thể vô tình chặn cả các thao tác Supplier không liên quan nếu crypto service được load bắt buộc. | **FIXED**: bank operations fail-closed nhưng Supplier không có bank vẫn tạo/xem/sửa được; no secret in logs/errors. |
| **A18** | MEDIUM | SUP-G1 guard có thể được viết/test bằng stub trước PUR nhưng `UR-SUP-001-03/05` đòi bằng chứng transaction thực và UR-SUP-002 đòi nguồn lịch sử thật. | **FIXED**: ghi `CORE_READY_FOR_PUR`, không đánh dấu 8/8 AC PASS; supplier guard interface chỉ nhận persisted Company từ trusted future Purchase command. |

**Severity:** HIGH = nguy cơ tạo code sai/hợp đồng lệch; BLOCKER = không được tiếp tục phần liên quan khi thiếu phê duyệt kỹ thuật; MEDIUM = rõ ràng hóa data/contract/QA. Các trạng thái **FIXED** trong bảng là **đã sửa tài liệu**, **không có nghĩa source runtime đã được sửa/kiểm thử**.

## 2. Nguồn đối chiếu trực tiếp (không suy đoán)

- **Approved P2 baseline:** `docs/DinkERP_P2/modules/P2_SUP_INDEPENDENT_MODULE_SPEC.md` §1/§6 định nghĩa `UR-SUP-001` 5 AC và `UR-SUP-002` 3 AC; §3–§5 chỉ là `P2-DRAFT`, không mặc nhiên là DDL/API có hiệu lực.
- **Frontend:** `docs/development-standards/01_FRONTEND_UI_STANDARD.md` FE-02/03/09 quy định cùng một bảng, PBAC SQL, `ListQuery` với adapter xử lý khác biệt legacy query; `src/components/lists/listPageSizePolicy.ts` có `CUSTOMER_PAGE_SIZE_OPTIONS` tới 100.
- **API thực tế:** `backend/app/Modules/Customer/CustomerSql.php`, `params()` whitelist `status`, `company_id`, `customer_group_code`, default `page_size=25`, max 100.
- **Numbering:** `backend/app/Modules/Customer/CustomerNumberIssuer.php` xử lý `AUTO_IF_EMPTY` manual, resource `customer`, pattern `{RESOURCE}=customer`, scope `GROUP`; `backend/app/Database/Seeds/CustomerDefaultNumberingSeeder.php` explicit opt-in.
- **Workflow:** `backend/app/Modules/Access/ResourceStateRepository.php::initialState/transitionTarget`, `ResourceStateValidator::transitionTarget` (must have exactly one candidate); `backend/app/Database/Seeds/CustomerCoreSeeder.php` có default ACTIVE initial; `backend/app/Modules/Customer/CustomerService.php::setState()` không có CAS version.
- **Bank audit:** `backend/app/Database/Migrations/2026-10-05-000011_CreateConfigurationAuditEvents.php` (schema audit cấu hình), `backend/app/Modules/Notifications/NotificationRuntimeConfigService.php` (dùng audit cho notification setting, chưa chứng minh tương thích ngân hàng).
- **Shared Contact:** `backend/app/Modules/Contact/ContactRules.php`, `ContactScope.php`, `ContactSql.php`, `ContactService.php`; các P1/Customer-owner scopes phải tiếp tục được bảo vệ.
- **Routing/SQL gates:** `docs/development-standards/02_SQL_FIRST_PBAC_PERFORMANCE_STANDARD.md`, `03_MODULE_DELIVERY_GATES.md`, `05_DEV_SQL_HTTP_QA.md`, `06_ROUTE_ARCHITECTURE_STANDARD.md`; `Config/Routes/ApiV1/CustomersRoutes.php` là entrypoint domain, SUP chỉ được thêm route mới, không đổi 150 frozen P1.

## 3. Phê duyệt, rủi ro còn lại và bước tiếp theo

| Gate/Area | Trạng thái sau audit | Điều kiện đóng |
|---|---|---|
| Product SUP-DEC-01..12 | **APPROVED 12/12, unchanged** | Không hỏi lại PO nếu không có scope change/security violation thực sự. |
| Document consistency | **STATIC REVIEW COMPLETE** | Links/decision-count/AC count/patch checks ghi trong QA packaging manifest. |
| **PC-03 Bank Security & Audit sink** | **BLOCKED TECH REVIEW** | Security/DBA sign cipher/key/rotation, masked/reveal/clear/audit store, migration plan. |
| PC-04/05 Numbering/Workflow | **TECH REVIEW PENDING** | Source-specific issuer and Lifecycle contracts, auto/manual and multi-target negative tests. |
| PC-06 Contact & M8.7 runtime/audit | **TECH REVIEW PENDING** | Four-owner SQL scopes/parent VIEW and unchanged prior audit resource definitions. |
| PC-07 API/UI | **TECH REVIEW PENDING** | OpenAPI final wire DTO and typed FE consumer tests. |
| G3–G7 source/DB/UAT/release | **NOT_RUN** | Source implementation, DEV MariaDB, performance, operator-run browser+Network evidence. |

**Recommended next work:** complete **SUP-T00 Engineering Preflight** on exact current Git/DEV tree (bank Security ADR + DDL, Numbering, Lifecycle, Contact SQL & API contract). Then T01 under the applicable MOD-G1/G2 gates; **do not** run MariaDB migration from this docs-only package.
