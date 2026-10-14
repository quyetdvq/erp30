# 15 — ERP30 Frontend V2: baseline khởi đầu đã kiểm chứng

**Baseline ID:** `FE-V2-START-2026-10-14`
**Ngày chốt:** 2026-10-14
**Phạm vi chốt:** **frontend only** (Vue 3/TypeScript/Vite); **chưa bắt đầu refactor V2**.
**Nguồn snapshot đóng gói:** `erp30_frontend_refactor_v1_2_full_source.zip`, byte-for-byte tương đương 926/926 file với workspace v1.2 trước khi bổ sung tài liệu/tool V2.
**Mức bằng chứng:** `[S]` source snapshot + Node tests tại môi trường biên soạn; `[UT]` log frontend thực thi trên Windows **do người dùng cung cấp**, không phải log tự tạo hoặc kiểm chứng máy đích độc lập.

> **Định nghĩa baseline:** V1.2 là trạng thái code gốc cho tất cả thay đổi V2. Đây không phải lời khẳng định frontend v1.2 tương thích với mọi phiên bản backend/DB hoặc chứng chỉ production. Bản P1 vẫn chỉ là tư liệu lịch sử.

## 1. Những gì đã xác nhận cho V1.2

| Gate | Bằng chứng được cung cấp ngày 2026-10-14 | Kết luận |
|---|---|---|
| `git apply --check` / `git apply` v1.2 | Áp dụng patch thành công tại `D:\XAMPP\htdocs\erp` | PASS `[UT]` |
| `pnpm run check:imports` | 141 TS/Vue; 536 import nội bộ; 0 lỗi | PASS `[UT]` |
| `pnpm run check:refactor-baseline` | 7 Node tests và 6/6 frontend contract sections | PASS `[UT]`; **2 cảnh báo external** |
| `pnpm run type-check` | `vue-tsc --build` exit thành công | PASS `[UT]` |
| `pnpm run lint` | Oxlint 0 warning / 0 error; ESLint 0 error / 3 warning E2E | PASS, còn 3 warning `[UT]` |
| `pnpm run format:check` | `All matched files use Prettier code style!` | PASS `[UT]` |
| `pnpm run test:unit -- --run` | 26 suites; **102/102** tests | PASS `[UT]` |
| `pnpm run build` | Vite **4.277** modules transformed; build + type-check thành công | PASS `[UT]` |
| `pnpm exec playwright test` | **243/243** tests; **8,8 phút**, 1 worker | PASS `[UT]` |

**Phạm vi suy luận:** 243 Playwright tests xác nhận các ca được định nghĩa trong cấu hình Playwright mặc định. Cấu hình chạy Vite dev-server theo mặc định và có test dùng mock; kết quả **không mặc nhiên** xác nhận 243 ca với backend thật, database thật, đầy đủ security negative tests hoặc mọi trình duyệt/thiết bị. Chưa có log PHPUnit, API integration hay schema migration ở đợt chốt này.

## 2. Tệp baseline, cách đọc và cách kiểm tra

- Snapshot khóa **mã nguồn frontend**: [`frontend/scripts/refactor/v2-start-2026-10-14.json`](../../frontend/scripts/refactor/v2-start-2026-10-14.json); gồm toàn bộ `src/`, `e2e/`, `scripts/`, `public/`, và các file cấu hình build/lint/test/package đã liệt kê. Hash từng file là SHA-256, khóa theo đường dẫn tương đối POSIX.
- Script so sánh không thay đổi manifest: [`check-v2-checkpoint.cjs`](../../frontend/scripts/refactor/check-v2-checkpoint.cjs), cùng [`v2-checkpoint-lib.cjs`](../../frontend/scripts/refactor/v2-checkpoint-lib.cjs). Script **không có cờ `--capture`** để tránh lỡ tay ghi đè baseline.
- Baseline gốc trước structural-refactor vẫn nguyên vẹn: [`baseline-2026-10-14.json`](../../frontend/scripts/refactor/baseline-2026-10-14.json). Tiếp tục dùng `check:refactor-baseline` để phát hiện thay đổi route, menu, bản dịch, view và logic đã khóa ở v1.
- README và tài liệu as-built không nằm trong danh sách hash code V2 vì còn được cập nhật độc lập. Không đưa `node_modules`, `dist`, `playwright-report`, môi trường `.env*` hay secret/runtime artifact vào manifest.

Tại `frontend/`:

```powershell
# Khi bắt đầu V2 trên đúng mã nguồn v1.2, yêu cầu không có drift
pnpm run check:v2-baseline

# Khi đang refactor V2, báo cáo file thêm / đổi / xóa mà không chặn vì thay đổi có chủ đích
pnpm run diff:v2-baseline

# Hợp đồng bảo toàn hành vi từ trước refactor v1, độc lập snapshot V2
pnpm run check:refactor-baseline

# Kiểm tra external dependency nếu cần audit toàn hệ thống
pnpm run check:refactor-external
```

**Phân biệt quan trọng:** `check:v2-baseline` là kiểm tra **tính toàn vẹn byte-for-byte của checkpoint**, do đó *phải thất bại* khi một thay đổi V2 có chủ đích được áp dụng. Sau khi bắt đầu sửa, dùng `diff:v2-baseline` để ghi nhận thay đổi và các gate chức năng bên dưới. Không coi “khác hash” là bằng chứng bug; cũng **không tái sinh manifest để làm xanh pipeline**. Muốn chốt checkpoint mới phải có change record, kiểm thử và review riêng.

## 3. Boundary của baseline: khác biệt backend/OpenAPI chưa được hợp thức hóa

Hai file **ngoài phạm vi frontend** trên máy Windows của người dùng khác snapshot source v1.2. Đây là kết quả log hash; chưa có nội dung diff:

| File | SHA-256 trong source ZIP v1.2 | SHA-256 người dùng báo trên Windows | Trạng thái |
|---|---|---|---|
| `backend/app/Config/Routes.php` | `c26d7992439d492d0c6d5406efbd3a3f8d0e861b9f8bde65a665add700ee753e` | `b9a6ed448b2e95ee94da1bc8540b0da754f302ccd7ad4176a6719b46840b69a5` | `REVIEW_REQUIRED` |
| `contracts/openapi/openapi.yaml` | `0ac1c1b12af9031476ab5ae225ddf5621b257495833a88d6f48cfed2b32ac31a` | `a28234f5cfa7923361ae2b3d4ebd190ae175f4f8ea72c666046a388333325fb2` | `REVIEW_REQUIRED` |

**Không được**: cập nhật hash gốc để cho PASS, kết luận API tương thích chỉ vì UI E2E PASS, đưa hai hash Windows vào manifest nguồn ZIP mà không có chính file tương ứng. Cần `git diff`/lịch sử và đối chiếu method/path/payload/permission/generated SDK khi thay đổi FE động tới API; thay đổi backend có thể đã được commit nên `git diff` đơn lẻ có thể không thấy.

## 4. Contract và tổ chức hiện hành: điểm bắt đầu V2

- **10 domain**: `account`, `auth`, `catalog`, `organization`, `people`, `product-import`, `security`, `settings`, `workflow`, `workspace` tại `frontend/src/features/`.
- **29 named routes** và authentication/dirty-navigation guards phải giữ nguyên; route đặc thù đứng trước route tham số (ví dụ `products/new` trước `products/:id`).
- Navigation/Base Permission chỉ quyết định cách hiển thị; server vẫn là nguồn quyết định PBAC/authorization.
- **789 key dịch VI và 789 key EN** là mốc đối chiếu, bao gồm persistence, fallback/formatting và tên key.
- `src/services`, `src/stores`, `src/composables`, `src/modules`, `src/components`, `src/api/generated` giữ API và luồng dữ liệu như v1.2.
- Mã nguồn đại diện cho điểm xuất phát V2; các contract về CSRF, session, number formatting, pagination, loading/error, upload/import, workflow, UI status/action vẫn phải có coverage khi tách component.

## 5. Quy tắc thực hiện V2 (không đổi tính năng)

1. **Theo từng feature, mỗi lần một patch nhỏ.** Không cùng lúc di chuyển nhiều domain và sửa API.
2. **Tách theo trách nhiệm:** `pages/` orchestration, `components/` phần UI tái sử dụng trong feature, `composables/` state/effects, `services/` adapter FE khi có lý do rõ ràng, `types/` shape không tạo nguồn sự thật khác OpenAPI. Tránh copy/paste shared abstractions chỉ để “đúng folder”.
3. **Giữ nguyên observable contracts:** URL/name/meta route; menu permission hints, authorization boundary, request/response/cookies/CSRF, i18n keys, focus/keyboard, loading/error/empty state, list sort/filter/pagination, mutation semantics.
4. **Không tự chỉnh `src/api/generated/`, backend hoặc OpenAPI** chỉ vì refactor frontend; nếu phát hiện contract lệch, mở việc đối soát riêng.
5. **Ưu tiên module giàu tương tác/độ phức tạp** nhưng làm từng lát: `catalog/ProductEditorView`, `people/PeopleEmployeesView`, `security/SecurityPoliciesView`, `workflow/LifecyclesView`, `AppShell`. Thứ tự này là gợi ý kỹ thuật từ kích thước source, không phải kết luận về mức độ lỗi.
6. **Mỗi CR ghi**: các file được sửa, baseline ID, phạm vi UI/API, test bổ sung, chênh lệch hash được duyệt, bằng chứng kiểm chứng, rollback plan nếu có.

## 6. Gate bắt buộc trước khi merge từng lát V2

```powershell
pnpm run check:imports
pnpm run diff:v2-baseline      # xuất danh sách source thay đổi so với mốc đóng băng
pnpm run check:refactor-baseline  # có thể fail khi tái cấu trúc có chủ đích; phải review từng sai lệch
pnpm run type-check
pnpm run lint
pnpm run format:check
pnpm run test:unit -- --run
pnpm run build
pnpm exec playwright test
```

Nếu `check:refactor-baseline` FAIL vì thay đổi cấu trúc hợp lệ, **ghi rõ exception + bằng chứng tương đương hành vi**, không sửa manifest cũ để xóa cảnh báo. Playwright/Unit coverage bổ sung ở đúng feature đang thay đổi; tỷ lệ 102 + 243 cũ là mốc kiểm thử **của v1.2**, không phải quy định rằng số lượng test không thể thay đổi khi nâng cấp.

## 7. Đóng baseline vào lịch sử Git của máy phát triển

Sau khi áp dụng patch baseline V2 và các lệnh kiểm tra từ mục 2 đã PASS, có thể commit chỉ **tooling/docs** rồi gắn tag khởi điểm V2 trên đúng repository đang phát triển:

```powershell
# Từ root repo; kiểm tra thay đổi local trước, KHÔNG tự động commit các file khác
 git status --short
 git diff --stat
# Commit sau review: git add <chỉ các file baseline V2>; git commit ...
# Khi có commit chuẩn: git tag -a frontend-v2-start-2026-10-14 -m "Frontend V2 start from verified v1.2"
# Tạo nhánh làm việc từ commit/tag đã thống nhất: git switch -c refactor/frontend-v2
```

Không ghi nhận là đã push, tag hay commit trên GitHub của người dùng. Snapshot ZIP không kèm lịch sử `.git`; **tag/commit do người dùng thực hiện trên repo thực tế**.
