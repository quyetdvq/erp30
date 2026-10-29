# DinkERP — Checklist triển khai, test và nghiệm thu module mới

> **Mã:** MOD-GATE-01 · **Phiên bản:** 1.1.0 · **Ngày:** 2026-10-29
> **Trạng thái:** TECHNICALLY REVIEWED v1.1.0. Checklist áp dụng theo version của module; không thay gate P1 đã đóng nếu thiếu quyết định thay đổi chính thức.

## 1. Luồng triển khai chuẩn

| Gate | Bằng chứng bắt buộc | BLOCK nếu thiếu |
|---|---|---|
| G0 — Phê duyệt nghiệp vụ | UR/AC của module, owner Company/Group, lifecycle, nguồn dữ liệu và phụ thuộc chéo | Chưa chốt nguồn nghiệp vụ hoặc ý nghĩa status/số liệu |
| G1 — Khóa dữ liệu và PBAC | Sơ đồ bảng/FK/index; Resource/Action; ScopeProvider; SQL plan COUNT/ROWS/detail; negative test design | Chưa chứng minh owner/PBAC hoặc có risk truy cập chéo Company |
| G2 — Khóa API/UI | OpenAPI input/output/error, `meta.total`, `allowed_actions`, filter/sort allowlist, screen parity `/people/employees` | FE/BE không khớp query hoặc payload |
| G3 — Code/Unit | Backend Rules/SQL contract, migration/seeder DEV nếu có, Vue components dùng chung, Vitest; tất cả lint/type/build | Fail tĩnh hoặc bỏ qua gate |
| G4 — DB/Performance | DEV clone migration, EXPLAIN COUNT/ROWS/lookup, test phân trang/filter và đo p95 với dữ liệu đại diện | Chưa có EXPLAIN cho query trọng yếu hoặc có N+1 |
| G5 — HTTP/PBAC | API có session/CSRF; user đủ và không đủ quyền; scope, direct-ID, conflict/race | Chỉ có pure test; chưa kiểm tra MariaDB/HTTP |
| G6 — Browser UAT | Desktop/mobile; Modal, dropdown remote, bản ghi nhiều status trong 1 bảng; SetState; vi/en; lỗi/empty/refresh | UI lệch chuẩn hoặc browser chức năng FAIL |
| G7 — Release/Closure | Git patch, PowerShell apply/reverse, changelog, DB rollback plan, kết quả PASS/FAIL/NOT_RUN, PO sign-off | Không có bằng chứng hoặc kiểm thử chưa chạy bị ghi PASS |

> **[REVIEW]** Chỉ tiêu p95 latency, số lượng bản ghi fixture và khả năng dùng keyset sẽ khóa riêng trong G1/G4 theo workload thực tế, không mặc định số ms cho mọi module.

## 2. Checklist kiểm tra code — frontend

- [ ] Dùng đúng AppShell, breadcrumb, header, modal chuẩn; tham chiếu `/people/employees`.
- [ ] Danh sách cùng resource có **một bảng và một pagination**; mặc định status=ALL; status options từ Lifecycle; không trộn nhiều trang.
- [ ] `ListQueryControls + ListShell + ListTable + ListPagination + useListState`.
- [ ] API list: q/page/page_size/sort/filter tương thích; `meta.total` hiển thị từ backend.
- [ ] Trang đầu reset khi đổi q/filter/sort/page_size; tránh race/cross-account stale response.
- [ ] Cột trạng thái có icon `TableStatus` + nhãn rõ ràng.
- [ ] Row actions dùng `authorization-mode=effective`, `allowed_actions` boolean; không suy quyền từ base permission.
- [ ] Modal Workflow dùng chung, không tràn khi mở trong bảng, không gọi PATCH status trực tiếp.
- [ ] Dropdown nhỏ `SearchableSelect`, lớn `RemoteSearchableSelect`, dependent lookup đúng owner/Tỉnh và giữ lựa chọn khi sửa.
- [ ] Đầy đủ loading/refresh/error/empty/no permission; trường hợp 409 giữ draft, 422 map lỗi.
- [ ] vi/en, aria, keyboard/focus, responsive desktop/mobile, format tiền/decimal/date đúng.
- [ ] Tab/module chưa có dữ liệu canonical hiển thị `NOT_AVAILABLE`, không hiển thị `0` giả.

## 3. Checklist kiểm tra code — backend/SQL

- [ ] Resource ownership Group/Company/Organization được chốt và truy từ dữ liệu persisted.
- [ ] ScopeProvider + SQL planner + authorization compiler đúng domain; fail-closed.
- [ ] `COUNT` và `ROWS` dùng cùng filter + SQL PBAC; detail/lookup/report/export dùng cùng bảo vệ.
- [ ] Không post-filter sau LIMIT/OFFSET. Không gửi record unauthorized tới FE.
- [ ] `q`/filter/sort/page/page_size validate, whitelist; bind values và validate DBPrefix.
- [ ] `ORDER BY` có `id` tie-break; cap size; index được `EXPLAIN` kiểm tra.
- [ ] `allowed_actions.edit/setstate` tính trên persisted rows theo effective PBAC; không N+1.
- [ ] Một bảng chỉ lấy projection hẹp; join 1:N dùng EXISTS/aggregation đúng cardinality.
- [ ] No N+1 lookup cho labels/permissions/related counts.
- [ ] Có test count leak, direct-ID leak, cross-company owner, no VIEW, DENY policy, wrong lifecycle, concurrency.
- [ ] Create/POST/PATCH/SetState qua canonical service/transaction/CSRF, log audit; xử lý lỗi chuẩn 400/403/404/409/422/500.
- [ ] Báo cáo tiền/kho/serial có as-of/posting/reversal/idempotency rõ, không aggregate sai join.

## 4. Mẫu hồ sơ module gửi review

```text
MODULE: <mã>
VERSION: <v>
UR/AC STATUS: APPROVED | OPEN
OWNER: MASTER GROUP | ORGANIZATION | COMPANY | OTHER APPROVED
RESOURCE.ACTION: ...
STATE GRAPH: ...
TABLES/INDEXES/MIGRATIONS: ...
LIST API: GET ...?page=1&page_size=15&sort=-id
QUERY WHITELIST: q/filter/sort/page limits...
AUTHORIZED SQL SCOPE: ...
COUNT/PAGE/DETAIL/LOOKUP SQL PLANS: ...
ALLOWED ACTIONS: edit / setstate / extra...
FRONTEND: route, list, modal, workflow, lookups
PERFORMANCE: dataset volume, EXPLAIN, p50, p95, N+1 count
TESTS: PURE, HTTP-PBAC, MariaDB, CONCURRENCY, VITEST, E2E
RELEASE: patch, PowerShell, migration/seed/rollback if applicable
RESULT: PASS | FAIL | NOT_RUN | BLOCKED (theo từng gate)
OPEN DECISIONS: ...
```

## 5. Quy trình bàn giao Git Patch / PowerShell

**Yêu cầu mọi lần sửa code:** một Git patch tăng dần so với baseline triển khai xác định, release notes nêu chính xác file thay đổi, commands ngay trong chat, lệnh rollback/verification, không trộn nhiều phiên bản patch; không tự áp dụng lại patch đã cài.

```powershell
cd D:\XAMPP\htdocs\erp
$patch = ".\<FILENAME>.patch"

git status --short
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw "STOP: Patch khong khop baseline" }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw "STOP: Apply failed" }
git diff --check
if ($LASTEXITCODE -ne 0) { throw "STOP: Diff failed" }
```

Để hoàn tác an toàn trên source vẫn khớp patch:

```powershell
git apply -R --check $patch
if ($LASTEXITCODE -ne 0) { throw "STOP: Khong rollback an toan" }
git apply -R $patch
```

Không dùng `--force`/ghi đè file chưa xác định. Nếu có migration hoặc dữ liệu posted, rollback Git **không tương đương rollback dữ liệu**; phải có kế hoạch DB riêng.

**Bộ QA thường dùng [SOURCE]:**

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
# Sau đó chạy các pure/integration tests chuyên biệt module.

cd D:\XAMPP\htdocs\erp\frontend
pnpm exec vitest run
pnpm run type-check
pnpm run lint
pnpm run check:imports
pnpm run build
# Khi thay hợp đồng OpenAPI:
pnpm run openapi:lint
pnpm run openapi:generate:check
```

Các bài `CHECK`, pure, Vitest, lint và build chỉ là **technical static/offline gate**. Chưa có UAT HTTP/Browser/DB thì phải ghi `NOT_RUN`, không ghi module `CLOSED`.

## 6. Review No-Go nhanh

**P0 — Block ngay:** leak PBAC, count leak, owner spoof, dynamic SQL chưa whitelist, double post, tiền/kho sai, 500 do dữ liệu thiếu owner, migration destructive không sao lưu, E2E fail mà báo PASS.

**P1 — Block trước RC/UAT:** SQL N+1; không EXPLAIN; thiếu row hints; sai page size cap; UI nhiều danh sách cho 1 resource status; form không có lookup searchable; modal overflow; bug 400 do contract FE/BE không khớp.

**P2 — Cho phép có hạn nếu được chấp thuận:** tối ưu CSS nhỏ, nhãn phụ, test bổ sung không ảnh hưởng bảo mật/độ đúng; có ticket và deadline.

## 7. Chốt trách nhiệm

- **PO/Business:** UR, workflow và meaning of metrics.
- **Security/Backend:** persisted scope, PBAC, SQL plans, invariants, idempotency, audit.
- **Frontend:** đồng bộ component, states, typed client, UX, accessible, i18n, không lộ data.
- **DBA/Backend:** schema/index, EXPLAIN, concurrency, benchmark, migration.
- **QA:** negative scenarios, test source/API, Browser UAT, log PASS/FAIL/NOT_RUN.
- **Release Owner:** Git patch, DB backup/runbook, source baseline, deployment sign-off.


## 8. Bổ sung checklist v1.1.0 — cải tiến P1 có kiểm chứng

| Gate | Tài liệu/chứng cứ | Kết quả không đủ để PASS |
|---|---|---|
| G0 — Baseline | Ghi commit/release v1.9.22; so sánh diff, xác nhận không sửa P1 pinned fixtures | Chỉ có file ZIP không rõ nền |
| G1 — Security | Các cặp `(Organization,State)` chính xác, số org vượt cap fail-closed, Contact parent/Company scope | Chỉ test user có quyền |
| G2 — Contract | Frontend & API cùng cap, SQL response `meta.total`, hints trên VIEWed rows, legacy search parity | Chỉ screenshot UI |
| G3 — Automated | Backend pure + regression P1; Vue Vitest/type/lint/build; xác nhận count bài test mới | Chỉ test-unit-check chạy qua |
| G4 — DB Performance | EXPLAIN COUNT/ROWS/lookup, test p95/query count thật, fixture đại diện, compare legacy & new | Chỉ source kiểm tra `LIMIT` |
| G5 — HTTP Security | Actor A/B, VIEW denied, State denied, ID không canonical, Company khác, list/detail/labels | Chỉ curl không có auth |
| G6 — Browser UAT | Employees, Organizations, Customer, Location search, Contacts, Workflow, AJAX lỗi + retry | Chỉ Vitest PASS |
| G7 — Patch & Closure | Git patch incremental, `git apply --check`/reverse, SHA file, QA PASS/FAIL/NOT_RUN, bảo đảm no migration nếu không đổi schema | Chỉ ZIP source |

**Không đủ để CLOSE P1-IMPROVEMENT:** các bài unit/pure thành công chưa thay thế được MariaDB `EXPLAIN`, HTTP PBAC và browser regression. Nếu một phép thử không chạy ở môi trường tạo patch, ghi `NOT_RUN`/`DEV_PENDING` và cung cấp lệnh chạy cho người dùng.

**Chốt ngoại lệ:** Các điều chỉnh tương đương về planner cần lưu test parity/decision riêng; không tự động thay hợp đồng, thêm migration/index hoặc đổi checksum pinned fixture. Mọi thay đổi performance có nguy cơ làm lỏng DENY phải coi là P0 blocker và rollback.


## 9. Phụ lục đề xuất — Route Architecture (chờ phê duyệt)

Xem [06_ROUTE_ARCHITECTURE_STANDARD.md](06_ROUTE_ARCHITECTURE_STANDARD.md). Khi được duyệt, đưa các route gates RT-01–RT-16 vào G2/G3/G5/G7: **mỗi module nghiệp vụ một route entrypoint**, một owner cho mỗi HTTP method/path, auto-route OFF, không đổi filters/handler/contract, compiled route fingerprint và manifest 150 frozen P1 phải tương đương. Các test cũ đang pin tên `MxxRoutes.php` chỉ được nâng cấp sang kiểm tra route signature/ownership; không được vô hiệu hóa security checks.

**Trạng thái:** PROPOSED; chưa refactor route runtime, chưa thay version của 3 chuẩn v1.1.0 đã duyệt.
