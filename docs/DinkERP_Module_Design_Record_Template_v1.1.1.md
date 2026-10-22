# DinkERP — MODULE DESIGN RECORD (MDR)

**Template ID:** DINKERP-BE-MODULE-TPL-001  
**Phiên bản:** 1.1.1 — 22/10/2026  
**Tài liệu chuẩn áp dụng:** DINKERP-BE-ARCH-001 v1.1.1 (Proposed Baseline; hiệu lực theo phê duyệt)  
**Mục đích:** Thiết kế root module mới hoặc hồ sơ chuyển đổi module legacy.

> **Nguồn biểu mẫu MDR duy nhất cho v1.1.1.** Sao chép vào `backend/app/Modules/<ApprovedModule>/MODULE.md` với **root mới**; **new capability** sửa `MODULE.md` của **root đang có**, không tạo MDR gốc song song; module legacy có thể dùng hồ sơ trung tâm tạm thời ghi owner/link/mốc chuyển đổi. Mọi `[ ... ]` là placeholder, **không** tự tạo Resource/Action/endpoint/bảng để đủ ô. Trường không áp dụng ghi `N/A` cùng lý do; chưa có bằng chứng conformance là `NOT_VERIFIED`, không phải `PASS`.

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
Approved read-only cross-module SQL projections: `[two data owners' MDR approval, query owner, tables/fields, PBAC/visibility, schema impact, tests / N/A]`; ADR `[ID / N/A]` **chỉ khi mở/thay module boundary hoặc semantics**  
Existing legacy aliases/facades: `[caller paths, owner, test, removal trigger / N/A]`  
Factory/composition wiring: `[entrypoint and constructed object graph]`  
Authorization-only read seam (nếu có): `[consumer, purpose/fields, adapter, no recursive authorize, negative test / N/A]`  
Legacy/observed Product read entrypoints consumers (nếu có): `[exact class/method, consumers, errors, owner approval / N/A]`

## 4. Use cases và bảo mật

| Operation | API contract status | Resource/Action | Resource Domain | Authoritative Evaluation Organization | HTTP status / error code / visibility / list | Audit |
|---|---|---|---|---|---|---|
| `[create/show/list/update]` | `[CONTRACT_READY / CONTRACT_PENDING / N/A]` | `[đã duyệt / N/A]` | `SYSTEM / MASTER / ORGANIZATION / N/A` | `[persisted/parent/registered resolver]` | `[current behavior; approved behavior; decision ref; list scope]` | `[policy]` |

**Đối với API legacy**: ghi riêng `Current 404/403 + machine code`, `Approved 404/403 + machine code` và trạng thái `SAME / CONFLICT_PENDING / APPROVED_CHANGE`. Không tự áp quy tắc 404 cho bản ghi ẩn nếu approved operation contract chưa yêu cầu. Với API mới: threat-model visibility cùng owner trước khi chốt mã lỗi; chỉ triển khai `CONTRACT_READY`.

**Mandatory gates cần xét khi protected:** valid/active resource, Base Permission, employee/assignment eligibility, authoritative context, exact PBAC lane precedence và combined lane semantics. Không dùng `NO_MATCH` để vượt mandatory gates, không cấp System Admin bypass, không dùng client hint làm owner.

Command `OperationContext`: `[entrypoint capture; same object propagated to participants/callbacks; evaluationTime/requestId actor; legacy retrofit gap / N/A]`  
Input validation (HTTP vs business): `[trách nhiệm]`  
Business invariants / immutable fields: `[invariants]`  
Workflow state transitions & write guard: `[contract / N/A]`  
Numbering series/mode/scope: `[contract / N/A]`  
Import/integration/decimal/time: `[contract / N/A]`

## 5. Transaction, concurrency và DB session

| Operation | Strategy (`atomicStatement`, `ownsTransaction`, `participatesInOwner`, `readSnapshot`) | Transaction opener / participants | Connection, locks, re-read | Rollback/audit & retry |
|---|---|---|---|---|
| `[operation]` | `[strategy]` | `[owner, callback]` | `[same session for locks/writes]` | `[atomicity / max retry if safe]` |

**Đặc biệt:** AUTO Numbering current allocator tự mở transaction và từ chối nested; business callback phải ghi cùng connection và không tự commit. `KIR-001 Product COMBO + AUTO`: ghi status `OPEN/NOT_RUNTIME_VERIFIED`, owner cần chỉ định, applicable input matrix, ADR + MariaDB/Shield integration/concurrency POC và rollback plan **trước khi thay transaction ownership**. Không gắn nhãn runtime PASS chỉ vì source lint thành công. Transaction của một statement đơn không cần `BEGIN` thủ công nếu không có invariant nhiều bước.

## 6. API, dữ liệu số, query và hiệu năng

| Endpoint/internal caller | Controller/service entrypoint | API status/machine errors | OpenAPI & generated types | API tests |
|---|---|---|---|---|
| `[method+URI hoặc internal method]` | `[class]` | `[explicit current + approved code/status, mapping tests]` | `[ref / N/A]` | `[tests]` |

- JSON `snake_case`, BIGINT/DECIMAL string nếu applicable: `[contract]`
- Protected list: approved visibility set **trước** `COUNT/page`; `items/total/page` nhất quán trong **cùng operation/snapshot** kể cả row recheck: `[strategy + concurrency expectation]`
- Snapshot rights: `[evaluation snapshot/time; requirement for immediate revocation if approved; test / N/A]`
- PHP fallback budget: `[max candidate rows, peak memory, query count, latency/p95; exact-count failure behavior from approved contract / N/A]`
- Search/filter/sort allowlist, stable ordering, Unicode/collation: `[details]`
- Read snapshot/no-nested behavior và N+1 prevention: `[details / N/A]`
- SQL EXPLAIN/index/benchmarks/metrics: `[measured results / NOT_VERIFIED]`

## 7. Test plan, gate evidence và release

| Gate | Applicable? | Evidence (command/test path/dataset) | Status |
|---|---|---|---|
| PHP lint/PHPStan/static dependency | `[Yes/No + why]` | `[paths]` | `PASS / FAIL / NOT_RUN / BLOCKED_ENV / N/A` |
| Unit/business rules | `[Yes/No]` | `[paths]` | `[...]` |
| PBAC & 404/403 security matrix | `[Yes/No]` | `[paths]` | `[...]` |
| API/OpenAPI contract | `[Yes/No]` | `[paths]` | `[...]` |
| MariaDB/Shield repository/integration | `[Yes/No]` | `[paths/env]` | `[...]` |
| Lock/concurrency/rollback | `[Yes/No]` | `[paths/env]` | `[...]` |
| Query count/EXPLAIN/p95 parity | `[Yes/No]` | `[dataset/env]` | `[...]` |
| Legacy shim/caller parity | `[Yes/No]` | `[paths]` | `[...]` |
| Architecture scanner public imports/DAG/write ownership | `[Yes/No]` | `[ARCH-01..07 evidence / N/A]` | `[...]` |
| Bootstrap & recursion negative test | `[Yes/No]` | `[test for read adapter / N/A]` | `[...]` |

**Gate status policy:** `PASS / FAIL / NOT_RUN / BLOCKED_ENV / N/A`. `N/A` chỉ khi không áp dụng có nêu lý do ở cột `Applicable?`; `NOT_RUN` nghĩa là áp dụng nhưng chưa chạy. Gate MariaDB/Shield/security/concurrency bị `BLOCKED_ENV` **không được** coi là đạt cho PR thay hành vi bảo mật/transaction.

Release strategy: `[feature-by-feature, compatibility window, migrations, monitoring]`  
Rollback/recovery plan: `[app revert / expand-backfill-contract]`  
Observability: `[request_id, DB time, PBAC, lock/numbering outcomes / N/A]`

## 8. Conformance, quyết định và sign-off

- Conformance status: `COMPLIANT / LEGACY_APPROVED / GAP_TO_FIX / NOT_VERIFIED`
- Architectural exceptions: `[exact namespace/path, rule, reason, owner, expiry/revisit, tests]`
- Open decisions (`CONTRACT_PENDING`, unresolved transactions, resource scope, `KIR-001`...): `[refs/STOP conditions]`
- Document version alignment: `DINKERP-BE-ARCH-001 v1.1.1` + `DINKERP-BE-MODULE-TPL-001 v1.1.1`
- Required next actions/owners: `[list]`
- Last verified in source revision: `[commit/release]`
- Approval/signature/effective date: `[approver/date/status]`

### Quy tắc nghiệm thu

1. Đúng module/data owner và không thêm dependency cycle.
2. Bảo mật được thiết kế theo persisted state + PBAC, không phụ thuộc duy nhất Controller.
3. Transaction participation/lock/rollback rõ cho mọi mutation phức tạp.
4. `items/total/page` trên protected list không làm lộ hoặc đếm sai dữ liệu.
5. API/DB/Resource metadata chỉ triển khai khi contract tương ứng có hiệu lực.
6. Các test bắt buộc có bằng chứng thật; `NOT_RUN`/`BLOCKED_ENV` không tương đương `PASS`, `N/A` cần lý do.
7. Không tạo interface/factory/repository/worker mới chỉ để đáp ứng bố cục.
