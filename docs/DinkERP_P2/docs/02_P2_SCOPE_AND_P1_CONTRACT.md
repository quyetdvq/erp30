# 02 — P2 Scope, Business Invariants & P1 Dependency Contract

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Định nghĩa Phase P2

**P2 = Operational ERP**, gồm: Document, Customer/CRM History, P2 Pricing conditions, Promotion, Supplier, Purchase (PR/PO/GR/Return), Inventory/Serial/Transfer/Adjustment/Reservation, Sales (Direct/Internal & Shopee/TikTok Shop), Marketplace Account Mapping/Idempotency, Carrier/Shipment/COD, Finance operational Money Receipt/Expense & reporting, và import Resource P2 khi có quyết định enable riêng.

**Không thuộc P2:** HCM/Payroll/Attendance/Leave/Overtime/BOM (P3), multi-currency, GL/double-entry, enterprise multi-level approval, generic manufacturing, advanced CRM lead/opportunity, arbitrary PBAC condition, Resource Instance Authorization. P2 không tự cho phép intercompany transfer/cross-company owner.

Các 30 UR `Target phase:P2` được giữ nguyên đầy đủ tại `01_P2_USER_REQUIREMENTS_BASELINE.md`; mỗi Criterion có FR+Test ID. Ngoài ra các đoạn P1 nhắc P2 (như Pricing extension, Contacts, Import) được kế thừa **chỉ ở mức P2 thực sự được nêu**, không nâng thành yêu cầu mới không có nguồn.

## 2. Source of truth và thứ tự áp dụng

1. **Approved UR v1.0.15**: baseline business scope/acceptance; không được design thay đổi và tuyên bố đã approve.
2. **P1 v1.9.0 code**: chính xác kỹ thuật đang hiện hữu. Backend CI4 Modules/Services, Vue TS frontend, MariaDB migrations 000001..000022, OpenAPI 3.1; các tính năng M11 Notifications là phần thực tế có trong ZIP.
3. **P2 documents v1.0.0**: `DRAFT` decisions for design; chưa có trong source, phải được duyệt và version hóa trước code.
4. **Live DB/HTTP/runtime**: không trong ZIP, phải test riêng tại DEV clone; documentation/code inspection không chứng minh production state.

## 3. Kế thừa nguyên tắc P1 bắt buộc (Normative dependency)

### 3.1 Company ownership, Organization hierarchy và master scope

- Một Group root, các Company trong Group. Business transaction P2 thuộc **đúng một Company**, không dùng GROUP làm owner; warehouse thuộc ancestor path của Company, không được mix branch khác Company.
- Organization types canonical: `GROUP`, `COMPANY`, `BRANCH`, `PLANT`, `WAREHOUSE`; parent matrix: `GROUP→COMPANY`, `COMPANY→BRANCH/PLANT/WAREHOUSE`, `BRANCH→PLANT/WAREHOUSE`, `PLANT→WAREHOUSE`; không nested cùng type, no cycles.
- Product/UOM/Product Group là Group Master shared mọi Company và **không có Product Company Usage Scope** trong P1/P2 vì đã khóa P1; Customer và Supplier có scope All Companies/allowlist riêng tại P2.
- Existing record evaluation organization phải derive từ persisted target; Create từ owner/parent/scope canonical. Client `company_id` hoặc `evaluation_organization_id` không thể override persisted authority.

### 3.2 Identity, PBAC, workflow

- Shield identity mapping, User+Employee+effective assignments; Base Permission `resource.action`; Policy restriction dimension Organization/Resource/Workflow/Participant/Action; final decision runtime, no system admin bypass.
- Assignment GROUP ancestor applies all descendants; assignment Company/Branch không leak sang sibling/Company khác. Evaluate P2 transaction at authoritative Company/Organization; no Base Permission => deny.
- QT1 lane precedence: `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`, `NO_MATCH => ALLOW` khi Base Permission có. Multi-lane denied-set intersection only after inheritance and applicable ABSOLUTE_DENY propagation.
- Lifecycle legality and PBAC are independent checks. P1 approval single-step, không tự mở enterprise routing/maker-checker/amount-based PBAC. Posting stock/finance occurs only on documented posting state.
- Every new P2 Resource/Action must be added via the Resource Catalog/Seeder and included in PBAC tests; no implicit permission because user belongs privileged group.

### 3.3 Product/COMBO/UOM and pricing

| Product role | Source select | Inventory/Serial/Costing/Posting leaf | Rule |
|---|---|---|---|
| SIMPLE | yes | yes | STANDARD root no child |
| CHILD | yes | yes | STANDARD child depth ≤1; full name derived |
| PARENT | no | no | grouping only |
| COMBO | yes as template | **no** | recursively flatten to SIMPLE/CHILD, acyclic |

- Source UOM + qty and converted Base UOM qty must be preserved for stock operational audit. Alternate UOM direct to Base UOM for SIMPLE/CHILD; COMBO only Base UOM. Alternate SALES default price independent of conversion ratio.
- Root COMBO selling total resolved once; nested COMBO and leaf Price Lists not re-resolved for allocation. Weight = leaf qty × leaf default BaseUOM price, reject total weight 0, deterministic residual, aggregate same leaf by sum qty and sum allocated amount.
- P1 price resolution Company→Group→default exact UOM with deterministic candidate tie-break and `[from,to)`; P2 specificity only additional context, not altered fallback ordering.
- Marketplace Sales `EXTERNAL_CHANNEL` must store external price/discount and not ERP recalculate it.

### 3.4 Numbering, import, time, audit, UI

- Auto Number resource-specific, organization scoped, atomic issuance; committed identifiers no reuse. Status inactive/cancelled does not free document number.
- P1 Import Framework only PRODUCT explicitly enabled. In P2, other master import requires approved UR scope, `resource.import` and separate template/validation/row-level authorization. No implicit import all masters.
- VND only. Date/time presentation timezone != Company Business Timezone. `DATE` derives from Company timezone of Evaluation Instant; API DECIMAL string, display formatting not calc authority.
- `view`,`edit`,`post`,`export` are distinct actions. Mutable masters need actor/time; stock/finance posted immutable with source + reversal/audit.
- Searches filter authorized dataset before pagination; SQL sort only allowlisted columns, stable ID tie-break; frontend cannot bypass backend scope.
- Frontend vi/en, stable `error.code`, pagination, lookup scope, accessible form state and generated client from OpenAPI.

## 4. Ownership and source-derived business rules matrix

| P2 entity type | Scope | Authority for existing target | Critical validation |
|---|---|---|---|
| Customer/Supplier | Group master; usage Company | existing master + user authorized scope | active, all_companies/allowlist, status |
| Document | Polymorphic owner | owner resource persisted Company/Group | view owner + attachment; file version auth |
| Price List extended context | P1 price_list org scope | P1 price_list.organization_id | match UOM; Company→Group fallback |
| PR/PO/GR/PurchaseReturn | Company + Warehouse | persisted business document → Company | same Company, posting-only stock |
| Inventory Balance/Movement/Serial | Company+Warehouse | persisted ledger/balance | SIMPLE/CHILD, no cross-company |
| Reservation | Company+Warehouse/SO | linked persisted Sales Order | available nonnegative, post consume |
| Sales Order/Issue/Return | Company; Warehouse; Sales Channel | persisted SO→Company | external/internal price authority, posting |
| Marketplace account/order | shop mapping → Company+Warehouse | persisted account effective mapping | idempotent source key |
| Shipment/COD | Sales Order Company | persisted Shipment→SO→Company | tracking and receipt mapping |
| Money Receipt/Expense | Company+Account | persisted money doc→Company | account same Company, POSTED-only balance |

## 5. Critical boundaries and anti-goals

- **Do not create** new `product_company_usages`, `variants`, `profiles`, independent permission storage, generic `conditions` as a P1 rewrite.
- Do not use external source order price and then call ERP internal price resolution; do not repost stock on webhook update.
- Do not implement cross-Company warehouse transfer using a single `warehouse_transfer` record without a separately approved intercompany process.
- Do not promote COD reconciliation into automatic money journal; explicit Money Receipt creation/posting with finance PBAC is mandatory.
- Do not expose import UI/API for any Resource until `import` action/target explicitly approved.
- Do not manually adjust `inventory_balances` as a substitute for posted movement/reversal; all stock corrections must be traceable.

## 6. Dependency on existing source / no-source evidence

Source contains `backend/app/Modules/{Access,Contact,Identity,Import,Locations,Notifications,Numbering,Organization,People,Pricing,Product,Settings,Workflow}`. It has no `Purchase`, `Inventory`, `Sales`, `Supplier`, `Customer`, or `Finance` module directories. P2 docs describe **future** modules. The P1 ZIP is not proof of a live backup or implemented P2 SQL tables.

## 7. Change control

- Start P2 with formal approval of architecture/DB/API/PBAC per wave. Baseline UR retains original IDs; when scope differs, create a Change ID, version P2 UR/FRS, and update all linked docs+test map rather than edit original P1 snapshot.
- Unresolved decisions logged with blocking gates in `14_P2_OPEN_DECISIONS_AND_GAP_AUDIT.md`.
- Acceptance every UR uses `P2-AT-*`, with runtime evidence on clone (and production smoke if approved), not just documentation completeness.
