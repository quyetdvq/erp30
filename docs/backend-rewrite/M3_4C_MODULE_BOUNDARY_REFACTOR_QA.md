# M3.4C — Identity / Access / Workflow Module Boundary Refactor

**Nền triển khai:** DinkERP Backend v2 M3.4B.5 (đã được chủ hệ thống xác nhận `RUN INTEGRATION`: 29/29 successful, 58/58 unauthenticated, 29/29 CSRF, MariaDB atomicity PASS).
**M3.4C:** refactor cấu trúc **không bổ sung route/API**, không sửa dữ liệu, không đụng frontend hoặc `backend_basline`. **Integration trên XAMPP sau refactor: CHƯA CHẠY**; PASS M3.4B.5 không tự kế thừa.

## 1. Các thay đổi ở kiến trúc runtime

| Module owner | Controller / Service / Repository thực sự sở hữu |
|---|---|
| Identity | `Identity/Management/IdentityRead{Controller,Service,Repository}`, `Identity/Mutation/SecurityUserMutation{Controller,Service,Repository,Rules}` |
| Access | `Access/Management/AccessRead{Controller,Service,Repository,Contract}` (Group, Resource, Policy, AuthorizationLog) và PBAC trung tâm `UnifiedAccessService` |
| Workflow | `Workflow/Management/WorkflowRead{Contract,Controller,Service,Repository}`, `Workflow/Mutation/WorkflowMutation{Controller,Service,Repository,Rules}` |

- **Không có `class_alias`, facade/adapter tương thích** để giữ các class sai module. 8 file mutation cũ dưới `Access/Mutation` được **xóa** trong mã nguồn hoàn chỉnh.
- `GET /api/v1/security/users`, `GET /api/v1/users/{id}/permissions` chạy trong Identity; 6 GET Workflow/Lifecycle chạy trong Workflow. 11 GET còn lại do Access sở hữu. Số API và đường dẫn URL không thay đổi.
- 5 User mutations ở Identity; 8 Workflow/Lifecycle mutations ở Workflow; 16 Access mutations vẫn ở Access.
- Identity/Workflow gọi **duy nhất** `Access\UnifiedAccessService` cho quyết định PBAC: luôn kiểm tra persisted row trước khi trả chi tiết, membership, total hoặc thực hiện mutation.
- `WorkflowMutationRules` sử dụng chuẩn hóa `status` và `PositiveId` tại module Workflow, không phụ thuộc vào `Access\Mutation\MutationRules` chuyên xử lý Access CRUD.
- `M33Verify` vẫn đọc đủ sáu master catalogs + Shield Users nhưng từ các repository đúng sở hữu. Các lệnh `m3:audit`, `m32:verify`, `m33b*:verify` và cấu hình 10 Resource State exception **giữ nguyên**.

## 2. Bất biến và kết quả kiểm tra tại nguồn

- **70/70** non-OPTIONS HTTP verb + URI + handler method khớp **footprint SHA-256 `136b7c3c9263a4ceb3ee7c35d398c1d6cebf4e34ad0fecfc30de731339314315`** của M3.4B.5. Không chỉ đếm số route.
- **29/29** OpenAPI mutation contract vẫn được khóa SHA-256; default write gate `DINKERP_M33B_WRITE_MODE=off` không đổi.
- **45/45** pure/fixture suites PASS. Bao gồm:
  - `m34c_module_boundary_pure.php`: **41 assertions** kiểm chứng module owner, cấm class cũ, route footprint, PBAC và write gate.
  - `m34c_owner_reads_fixture.php`: **20 assertions** chạy **read service/repository thật** trên fake DB để xác nhận lọc PBAC trước `total`, projection Shield không lộ avatar filename/password, direct permissions và hidden relation, query validation trước SQL.
  - Hai bộ fixture transaction cũ: **34/34** Shield User và **41/41** Workflow PASS. Đây là **fake transaction**, không phải MariaDB thực.
- PHP lint `backend/app` phải PASS. Frontend và `backend_basline` phải giữ nguyên checksum đối chiếu M3.4B.5.

**Không thể kết luận đã nghiệm thu HTTP/DB M3.4C từ kiểm thử offline.** Cần chạy `RUN DB-READ` và `RUN INTEGRATION` trên XAMPP để xác nhận việc chuyển service/repository không thay đổi hành vi HTTP/MariaDB. Baseline live parity, concurrency/isolation và golden-master vẫn là gate riêng ở M3.4D.

## 3. Cài đặt trên M3.4B.5

### Cách A — Git patch (ưu tiên khi working tree khớp M3.4B.5)

Ở `D:\XAMPP\htdocs\erp`:

```powershell
# Đặt M3.4C.patch tại root ERP

git apply --check M3.4C.patch
git apply M3.4C.patch
```

Git patch xóa 8 class cũ ngay khi cập nhật; **không chạy** `apply-m34c-overlay.ps1` nếu đã áp dụng Git patch.

### Cách B — Overlay ZIP

Giải nén `DinkERP_Backend_v2_M3.4C_ModuleBoundary_Overlay.zip` vào `D:\XAMPP\htdocs\` (ZIP chứa `erp/`), rồi chạy:

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\apply-m34c-overlay.ps1
```

Giải nén ZIP **không tự xóa file**: PowerShell script chỉ xóa chính xác 8 class cũ nếu SHA-256 khớp M3.4B.5, và **kiểm tra mọi file nguồn/đích trước khi xóa**. Nếu code cũ đã bị người dùng sửa, script dừng an toàn, không tùy ý xóa. Script chạy lại được (idempotent). **Phải chạy cleanup trước `CHECK`**.

### Kiểm thử

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN DB-READ
.\tools\test-unit.ps1 RUN INTEGRATION
```

Đầu ra offline mong đợi: `45/45 suites PASS`. Trong `RUN INTEGRATION`, mong đợi
`29/29 successful`, `58/58 unauth`, `29/29 CSRF`, `rollback=PASS_DB_ATOMICITY_NOT_BASELINE_PARITY` **chỉ khi XAMPP thực sự trả các kết quả đó**. Mỗi lần integration tạo một sandbox mới, không sử dụng `erp` hoặc `erp_test` làm Database đích.

## 4. Cổng nghiệm thu trước milestone M3.4D

1. Tất cả source gates/fixtures/hợp đồng HTTP giữ nguyên sau cập nhật.
2. `RUN DB-READ` xác nhận không thay đổi catalog/PBAC State, Shield User và Workflow.
3. `RUN INTEGRATION` thực tế đạt 29 positive, 58 unauth, 29 CSRF, rollback trên **MariaDB sandbox** mới.
4. Sau đó M3.4D đối chiếu golden-master/HTTP và các edge cases còn thiếu; tuyệt đối không tự động bật mutation trên production.

**Production:** tiếp tục `DINKERP_M33B_WRITE_MODE=off`.
