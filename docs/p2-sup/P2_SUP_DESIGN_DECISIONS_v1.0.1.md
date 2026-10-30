# DinkERP P2-SUP — Decision Register v1.0.1

> **Document ID:** P2-SUP-ADR-REGISTER · **Date:** 2026-10-29  · **Version:** 1.0.0
> **Decision status:** **12/12 APPROVED (Business/Product decision; unchanged in v1.0.1)**. Người yêu cầu đã xác nhận: “Đồng ý: 01, 02, 03, 04, 05, 06, 07, 08, 09, 10, 11, 12; Chưa đồng ý/cần sửa: Không có; Ý kiến bổ sung: Đồng ý”.
> **Implementation status:** **NOT_IMPLEMENTED; technical release/gate sign-offs NOT_YET_EVIDENCED**. Product approval không tự thay thế xác minh Security/DBA, OpenAPI freeze, migration preflight hoặc UAT.
> **Approved scope:** **Supplier Core giống Customer G1**; không nhận 8 năng lực Supplier ERP nâng cao từng được tham khảo. Giữ nguyên 8 AC trong `docs/DinkERP_P2/modules/P2_SUP_INDEPENDENT_MODULE_SPEC.md`.
> **Related docs:** [Technical Design](P2_SUP_TECHNICAL_DESIGN_v1.0.1.md) · [Implementation Plan](P2_SUP_IMPLEMENTATION_PLAN_v1.0.1.md) · [Pre-code Readiness](P2_SUP_PRECODE_READINESS_v1.0.1.md).

## 1. Quyết định nghiệp vụ đã chốt — source of truth

| ID | Status | Quyết định cuối cùng của G1 | Ghi chú bắt buộc khi code |
|---|---|---|---|
| **SUP-DEC-01** / P2-DEC-010 | **APPROVED** | Supplier Group là catalog mã kiểm soát; mã mặc định **`STANDARD`**. Không tạo Group CRUD/table riêng trong G1. | Group không phải một organization mới. UI chọn catalog hợp lệ, backend kiểm tra, code chuẩn theo hiện hành. |
| **SUP-DEC-02** / P2-DEC-010 | **APPROVED** | Payment Term catalog ban đầu: **`IMMEDIATE`, `NET15`, `NET30`, `NET60`**; optional trên Supplier. Chưa có công nợ, due-date engine hay schedule. | Các code chỉ là dữ liệu tham chiếu; xử lý công nợ thuộc FIN. Không suy diễn ngày đến hạn trong SUP. |
| **SUP-DEC-03** / P2-DEC-011 | **APPROVED** | **Tối đa một** thông tin tài khoản ngân hàng/Supplier; optional. **Mã hóa khi lưu, masking mặc định**, không hiện plaintext trên list/detail/log; phân quyền riêng **`supplier.view_bank` / `supplier.edit_bank`**. | Security phải xác minh authenticated-encryption, key storage ngoài repo/DB, masking, reveal/audit và thất bại fail-closed trước phần code bank. Không có multiple-bank/G1 approval workflow. |
| **SUP-DEC-04** / P2-DEC-011 | **APPROVED** | Một Supplier có **nhiều Contact**, sử dụng Shared Contacts P1 (`contact_type=SUPPLIER`, `contact_ref_id=supplier.id`), resource **`supplier_contact`**; không tạo bảng Contact riêng. | Quyền Contact AND Supplier cha; giữ nguyên ORG/EMP/CUS scopes và kiểm thử hồi quy âm. |
| **SUP-DEC-05** | **APPROVED** | Code dùng **P1 Auto Numbering** theo Series hợp lệ; cho phép nhập code thủ công **chỉ khi không tồn tại Series mặc định cấu hình cho Supplier** (kể cả Series mặc định đang INACTIVE); có default thì phải dùng luồng Numbering hợp lệ, không dùng manual để vượt qua default; validate và UNIQUE theo Group. | Không tự sinh code từ client Company. Không kế thừa nhánh `AUTO_IF_EMPTY` cho phép manual khi đã có default của `CustomerNumberIssuer`; đây là khác biệt SUP đã duyệt. Requested series phải hợp lệ, không dùng để vượt default lỗi. Chốt source config/series qua preflight. |
| **SUP-DEC-06** | **APPROVED** | Supplier **Group Master dùng chung**, `all_companies=true` hoặc allowlist Company cụ thể; Company Usage = đủ điều kiện sử dụng, **không phải quyền PBAC**. | `all_companies=true` đi cùng list rỗng; nếu false phải có ít nhất một Company ACTIVE hợp lệ. Backend xác minh persisted `type=COMPANY`. |
| **SUP-DEC-07** | **APPROVED** | **Lifecycle động giống Customer đã hotfix**; trạng thái đầu do Lifecycle của resource `supplier` quyết định; chuyển bằng Workflow `setstate`, không hardcode `ACTIVE` khi create. | `INACTIVE` cấm Purchase mới; **chỉ ACTIVE được phép** ở guard mặc định, trạng thái khác cấm đến khi có chính sách được duyệt. History vẫn đọc theo quyền dù inactive. |
| **SUP-DEC-08** | **APPROVED** | Lock DB transaction + optimistic CAS `row_version` / `expected_version`, **bắt buộc** cho PATCH master, PUT usage, POST set-state và PUT/clear bank; HTTP 409 khi stale. | Không làm lost update; tăng version atomically; frontend giữ draft khi conflict. |
| **SUP-DEC-09** | **APPROVED** | **DEFER Notes**; **DEFER Supplier History implementation** đến khi có PO/GR/Return/PAYMENT thật từ PUR/INV/FIN; không mock rows/counts. | `UR-SUP-002` vẫn MUST nhưng phụ thuộc nguồn, chưa được CLOSED ở G1. |
| **SUP-DEC-10** | **APPROVED** | Core schema **`suppliers` + `supplier_company_usages`**, profile giống CUS; **Supplier Name, Supplier Type, Supplier Group, Phone, Main Address, Province, Ward, Company Usage** bắt buộc khi create. | Code khi lưu; Type `INDIVIDUAL`/`COMPANY`; email/MST/Payment Term/bank optional; Phone phải đúng định dạng, **không UNIQUE** giữa các Supplier. Province–Ward hợp lệ; check merged PATCH. |
| **SUP-DEC-11** | **APPROVED** | API/UI cùng kiến trúc Customer: Supplier List/Detail/Create/Edit, Usage, Contacts, Workflow actions, SQL-first list (`q/page/page_size/sort/filters`), `meta.total`, `allowed_actions`, i18n, một bảng theo tất cả states. Page cap **100**. | Chỉ tạo route P2 trong `SuppliersRoutes.php`, giữ P1 frozen; GET list dùng **tham số phẳng như CUS** (`status`, `company_id`, `supplier_group_code`, `supplier_type`), mặc định `page_size=25`, cap 100; frontend `ListQuery.filter` ánh xạ tại adapter. Khóa OpenAPI tại MOD-G2. |
| **SUP-DEC-12** | **APPROVED** | History dùng **persisted source Company + per-source Resource.Action VIEW**, chỉ tạo link nếu được xem; `supplier.view` không cấp quyền nguồn PUR/FIN. | Không dùng allowlist hiện tại để ẩn lịch sử cũ; không tiết lộ tổng số unauthorized. |

**Quy định thực hiện:** Không tự sửa nội dung 12 quyết định đã được PO duyệt. Mọi khác biệt với bảng này cần change request/ADR revision trước khi code. P2 baseline (`P2-DEC-010/011` trong catalog draft gốc) **không bị sửa file** bởi package v1.0 này; register hiện tại là implementation approval của SUP-G1.

## 2. Các chi tiết đi kèm chốt SUP-DEC-10 (form Create/Edit)

| Field | On Create | Canonical rule |
|---|---|---|
| `supplier_code` | Bắt buộc có **sau** Numbering hoặc explicit manual fallback | Uppercase/trim, UNIQUE trong Group; không nhất thiết bắt buộc người dùng nhập khi có Series. |
| `name` | REQUIRED | Trim, không trống. |
| `supplier_group_code` | REQUIRED | Default `STANDARD`; code trong danh mục duyệt. |
| `supplier_type` | REQUIRED | `INDIVIDUAL` / `COMPANY`; lưu ý CUS hiện dùng `INDIVIDUAL` / `ORGANIZATION`, **không copy enum CUS**. |
| `phone` | REQUIRED | Kiểm tra format, normalize cho validation/search nếu cần; **không đặt DB unique phone**. |
| `address` | REQUIRED | Main Address không được trống. |
| `province_id`, `ward_id` | REQUIRED | Ward phải thuộc Province; dữ liệu hợp lệ từ P1. |
| `email`, `tax_code`, `payment_term_code`, `bank_account` | OPTIONAL | Email đúng format; terms từ catalog; bank chỉ đi qua bank-secure contract. |
| `all_companies`, `company_ids` | REQUIRED/conditional | true → `[]`; false → nonempty validated COMPANY list. |
| `status` | SERVER CONTROLLED | Lấy từ Lifecycle `initialState('supplier')`; không chấp nhận create/PATCH status từ client. |

**Phone policy is intentionally different from Customer:** Customer đã có unique normalized phone, SUP **không** kế thừa unique constraint vì số tổng đài dùng chung là hợp lệ.

## 3. Những gì **không** được phê duyệt để làm trong SUP-G1

- Không tạo Supplier Site/multiple addresses, multiple bank accounts, onboarding portal, qualification/scorecard/risk, bank change approval workflow riêng, supplier-products price catalog, purchase order, payment ledger, AP engine, import/export hàng loạt, Supplier/Customer unified identity.
- Không tạo CRUD Supplier Group/Payment Term trong G1; chỉ read-only catalog hợp lệ.
- Không triển khai `POST /suppliers/{id}/notes`, lịch sử giả hoặc tài khoản ngân hàng plaintext.
- Không thay schema/route của CUS hay các 150 frozen P1 operations để “đồng nhất” một cách breaking.

## 4. Phân biệt phê duyệt và điều kiện kỹ thuật chưa hoàn tất

| Phân loại | Trạng thái | Ý nghĩa |
|---|---|---|
| **Business decision 01–12** | **12/12 APPROVED** | Không cần xin chọn lại 12 điểm trừ khi phát hiện xung đột UR/security. |
| **PO design approval** | **ACKNOWLEDGED 2026-10-29** | Được xác nhận ngay trong hội thoại, không tự gán tên hoặc chữ ký cụ thể. |
| **Tech Lead/Security/DBA signoff** | **NOT_EVIDENCED** | Chưa có bằng chứng về final DDL, secret/key handling, cơ chế Numbering/PBAC/Workflow trên clone. |
| **MOD-G0 business scope** | **APPROVED** | Cho phép hoàn tất engineering design/preflight. |
| **MOD-G1/G2 technical freeze** | **PENDING** | Chốt schema/PBAC/OpenAPI sau khi xác minh tương thích trên source/DEV trước code. |
| **MOD-G3–G7** | **NOT_RUN** | Chưa viết SUP code, chưa migrate, chưa UAT, chưa release. |

Các điểm cụ thể phải xác minh, cách đóng và người chịu trách nhiệm có tại [Pre-code Readiness](P2_SUP_PRECODE_READINESS_v1.0.1.md). **Không dùng trạng thái 12/12 APPROVED để tuyên bố đã đủ điều kiện triển khai ngân hàng hoặc release.**

## 5. Change log so với v0.1

- 01,02: đóng catalog `STANDARD` và bốn Payment Terms, không mở CRUD.
- 03: đóng một bank account được mã hóa với hai quyền riêng; phạm vi advanced multi-bank không được chấp nhận.
- 04–06,08–09,11–12: giữ đề xuất cơ bản nhưng nâng thành quyết định đã được PO duyệt.
- 07: **thay thiết kế `ACTIVE` hardcoded bằng Lifecycle động**.
- 10: **form Supplier bắt buộc Phone/Address/Province/Ward + Type, Group**, nhưng Phone không unique; xác định baseline source sau Customer hotfix.
- Đính chính baseline: ZIP có migration Customer Phone **`2026-10-29-000025_AddCustomerPhoneUniqueness`** (v0.1 dùng source trước hotfix).

## 6. Phụ lục hiệu đính v1.0.1 — không thay 12 quyết định PO

- **SUP-DEC-05 / Numbering:** `CustomerNumberIssuer::issue()` hiện cho phép `manualCode` khi default `AUTO_IF_EMPTY`; SUP không được copy nhánh đó vì quyết định đã chốt chỉ chấp nhận manual **khi không có Series mặc định**. Kiểm tra cả default inactive/misconfigured; không mở fallback im lặng. Counter và Supplier INSERT phải nằm cùng transaction; unique DB xử lý race.
- **SUP-DEC-07 / Lifecycle:** Resource `supplier` có thể khởi tạo từ DRAFT/APPROVED theo cấu hình. Nhưng P1 `ResourceStateRepository::transitionTarget()` hiện chỉ chọn được **đúng một target** cho `(from_state, action=setstate)`; phân nhánh cùng action là trường hợp cấu hình chưa được hỗ trợ, phải từ chối an toàn và ghi vào PC-05 trước code, không tự sửa Workflow/P1. Chỉ `ACTIVE` đủ điều kiện Purchase; Lifecycle thiếu ACTIVE vẫn cho tạo Supplier nhưng không được dùng cho Purchase.
- **SUP-DEC-08 / CAS:** Khác Customer hiện tại, SUP yêu cầu `expected_version` cho cả SetState và Bank; mọi write thành công (bao gồm bank clear) tăng cùng một `row_version`, không tạo các bộ đếm riêng.
- **SUP-DEC-10 / dữ liệu:** `supplier_group_code=STANDARD` là **phân loại Supplier**, không phải Organization GROUP root; `group_organization_id` (nếu persist) luôn do server lấy từ GROUP root. Phone được kiểm tra format nhưng không dùng `CustomerPhone` làm domain policy/unique của SUP. Company Usage false luôn có ≥1 Company ACTIVE; true luôn `[]`, cả ở DRAFT.
- **SUP-DEC-03 / audit:** Quyền `view_bank/edit_bank` và audit reveal/update là **bắt buộc**. Source có `configuration_audit_events` nhưng đây là schema cấu hình, chưa có bằng chứng phù hợp cho sự kiện Bank. Không tự gán bảng đó làm bank audit. PC-03 yêu cầu Security/DBA chốt phương án durable audit; nếu cần bảng audit riêng, lập **ADR kỹ thuật nêu rõ phần schema bổ sung vì yêu cầu bảo mật** trước code, không lặng lẽ thêm bảng thứ ba vào `SUP-DEC-10`.
- **SUP-DEC-11 / API:** `GET /api/v1/suppliers?status=DRAFT&company_id=2&page=1&page_size=25` là shape đề xuất tương thích Customer; frontend `ListQuery.filter` chuyển thành tham số phẳng qua adapter, không gửi đồng thời `filter[status]` và `status`. Bank chỉ qua endpoint riêng, response `Cache-Control: no-store`; precise DTO phải freeze G2.
- **Không phát sinh phê duyệt mới:** hiệu đính này làm rõ kỹ thuật để hiện thực 12 quyết định đã duyệt, không mở Supplier Group CRUD, Notes, History giả, AP/Purchase hay nhiều Bank. Tech/Security/DBA signoffs vẫn `NOT_EVIDENCED`.
