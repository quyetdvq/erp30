# DinkERP — MODULE DESIGN RECORD (MDR)

**Template ID:** DINKERP-BE-MODULE-TPL-001  
**Phiên bản:** 1.1 — 22/10/2026  
**Tài liệu chuẩn áp dụng:** DINKERP-BE-ARCH-001 v1.1 (Proposed Baseline; hiệu lực theo phê duyệt)  
**Mục đích:** Thiết kế root module mới hoặc hồ sơ chuyển đổi module legacy.

> Sao chép vào `backend/app/Modules/<ApprovedModule>/MODULE.md` đối với **root module mới**. Module legacy được phép dùng hồ sơ trung tâm tạm thời với đường dẫn, owner và mốc chuyển đổi. Mọi `[ ... ]` là placeholder, không được tự đặt Resource/Action, endpoint hoặc bảng để điền mẫu. Việc không áp dụng ghi `N/A` cùng lý do. Trạng thái chưa có bằng chứng là `NOT_VERIFIED`, không phải `PASS`.

## 1. Thông tin và thẩm quyền

| Trường | Giá trị |
|---|---|
| Module / capability | `[Tên đã duyệt]` |
| Type | `New root / New capability / Legacy retrofit` |
| Owner / Architecture reviewer | `[Role/person]` |
| Business purpose & owned invariants | `[Mô tả]` |
| Status | `DRAFT / ACCEPTED / IMPLEMENTING / IMPLEMENTED / DEPRECATED` |
| UR/FRS/Implementation Contract | `[Văn bản, version, phạm vi/approval]` |
| DBD/schema approval | `[Reference hoặc N/A (không có persistence)]` |
| API contract | `CONTRACT_READY [ref] / CONTRACT_PENDING / N/A (internal-only)` |
| Resource/Action/PBAC contract | `[Reference hoặc N/A hợp lệ]` |
| Approved ADR/change control | `[ID, status, approver hoặc N/A]` |

## 2. Dữ liệu: business và platform-technical ownership

| Table/object | Business/technical owner | Quyền module đang thiết kế | FK và schema | Quy tắc mutation/public write surface |
|---|---|---|---|---|
| `[object]` | `[owner]` | `Read / Write / N/A` | `[đã duyệt]` | `[method/contract]` |

**Quy tắc:** Module chỉ ghi trực tiếp dữ liệu do mình sở hữu. Bảng kỹ thuật `import_jobs`, audit, numbering,... vẫn có **technical owner**; module dùng API/repository do owner công bố. Cross-module read JOIN nếu cần phải có documented fields/visibility/schema impact/approval.

## 3. Public entrypoints và dependency

| Public class/method | Consumers | Input/output (type) | Side effects | Security/transaction expectation |
|---|---|---|---|---|
| `[method]` | `[module/CLI/worker]` | `[contract]` | `[read/write]` | `[expectations]` |

Allowed root module imports: `[list]`  
Forbidden edges / cycle resolution: `[none / consumer-owned read port + adapter]`  
Approved read-only cross-module SQL projections: `[ADR, tables/fields, visibility, tests / N/A]`  
Existing legacy aliases/facades: `[caller paths, owner, test, removal trigger / N/A]`  
Factory/composition wiring: `[entrypoint and constructed object graph]`

## 4. Use cases và bảo mật

| Operation | Contract status | Resource/Action | Resource Domain | Authoritative Evaluation Organization | Visibility / 404/403 / List | Audit |
|---|---|---|---|---|---|---|
| `[create/show/list/update]` | `[READY / N/A]` | `[đã duyệt / N/A]` | `SYSTEM / MASTER / ORGANIZATION / N/A` | `[persisted/parent/registered resolver]` | `[rules]` | `[policy]` |

**Mandatory gates cần xét khi protected:** valid/active resource, Base Permission, employee/assignment eligibility, authoritative context, exact PBAC lane precedence và combined lane semantics. Không dùng `NO_MATCH` để vượt mandatory gates, không cấp System Admin bypass, không dùng client hint làm owner.

Input validation (HTTP vs business): `[trách nhiệm]`  
Business invariants / immutable fields: `[invariants]`  
Workflow state transitions & write guard: `[contract / N/A]`  
Numbering series/mode/scope: `[contract / N/A]`  
Import/integration/decimal/time: `[contract / N/A]`

## 5. Transaction, concurrency và DB session

| Operation | Strategy (`atomicStatement`, `ownsTransaction`, `participatesInOwner`, `readSnapshot`) | Transaction opener / participants | Connection, locks, re-read | Rollback/audit & retry |
|---|---|---|---|---|
| `[operation]` | `[strategy]` | `[owner, callback]` | `[same session for locks/writes]` | `[atomicity / max retry if safe]` |

**Đặc biệt:** AUTO Numbering current allocator tự mở transaction và từ chối nested; business callback phải ghi cùng connection và không tự commit. Không sửa COMBO/AUTO multi-table semantics nếu chưa có ADR + DB integration/concurrency POC. Transaction của một statement đơn không cần `BEGIN` thủ công nếu không có invariant nhiều bước.

## 6. API, dữ liệu số, query và hiệu năng

| Endpoint/internal caller | Controller/service entrypoint | API status/machine errors | OpenAPI & generated types | API tests |
|---|---|---|---|---|
| `[method+URI hoặc internal method]` | `[class]` | `[codes/status]` | `[ref / N/A]` | `[tests]` |

- JSON `snake_case`, BIGINT/DECIMAL string nếu applicable: `[contract]`
- Protected list: approved visibility set **trước** `COUNT/page`; `items/total/page` nhất quán kể cả sau row recheck: `[strategy]`
- Search/filter/sort allowlist, stable ordering, Unicode/collation: `[details]`
- Read snapshot/no-nested behavior và N+1 prevention: `[details / N/A]`
- SQL EXPLAIN/index/benchmarks/metrics: `[measured results / NOT_VERIFIED]`

## 7. Test plan, gate evidence và release

| Gate | Applicable? | Evidence (command/test path/dataset) | Status |
|---|---|---|---|
| PHP lint/PHPStan/static dependency | `Yes` | `[paths]` | `PASS / FAIL / NOT_RUN / BLOCKED_ENV` |
| Unit/business rules | `[Yes/No]` | `[paths]` | `[...]` |
| PBAC & 404/403 security matrix | `[Yes/No]` | `[paths]` | `[...]` |
| API/OpenAPI contract | `[Yes/No]` | `[paths]` | `[...]` |
| MariaDB/Shield repository/integration | `[Yes/No]` | `[paths/env]` | `[...]` |
| Lock/concurrency/rollback | `[Yes/No]` | `[paths/env]` | `[...]` |
| Query count/EXPLAIN/p95 parity | `[Yes/No]` | `[dataset/env]` | `[...]` |
| Legacy shim/caller parity | `[Yes/No]` | `[paths]` | `[...]` |

Release strategy: `[feature-by-feature, compatibility window, migrations, monitoring]`  
Rollback/recovery plan: `[app revert / expand-backfill-contract]`  
Observability: `[request_id, DB time, PBAC, lock/numbering outcomes / N/A]`

## 8. Conformance, quyết định và sign-off

- Conformance status: `COMPLIANT / LEGACY_APPROVED / GAP_TO_FIX / NOT_VERIFIED`
- Architectural exceptions: `[exact namespace/path, rule, reason, owner, expiry/revisit, tests]`
- Open decisions (`CONTRACT_PENDING`, unresolved transactions, resource scope...): `[refs/STOP conditions]`
- Required next actions/owners: `[list]`
- Last verified in source revision: `[commit/release]`
- Approval/signature/effective date: `[approver/date/status]`

### Quy tắc nghiệm thu

1. Đúng module/data owner và không thêm dependency cycle.
2. Bảo mật được thiết kế theo persisted state + PBAC, không phụ thuộc duy nhất Controller.
3. Transaction participation/lock/rollback rõ cho mọi mutation phức tạp.
4. `items/total/page` trên protected list không làm lộ hoặc đếm sai dữ liệu.
5. API/DB/Resource metadata chỉ triển khai khi contract tương ứng có hiệu lực.
6. Các test bắt buộc có bằng chứng thật; `NOT_RUN` không tương đương `PASS`.
7. Không tạo interface/factory/repository/worker mới chỉ để đáp ứng bố cục.
