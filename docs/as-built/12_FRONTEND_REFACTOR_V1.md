# 12 — Frontend Refactor v1: Source-preserving Module Organization

**Ngày:** 2026-10-14. **Trạng thái:** `STRUCTURAL-REFACTOR / STATIC-EQUIVALENCE-PASS / RUNTIME-NOT-VERIFIED`.

**Nguồn:** code baseline trong `erp30_baseline_with_asbuilt_2026-10-14.zip`; đây là refactor trên bản snapshot ZIP, **chưa xác thực đồng nhất với HEAD GitHub**. Bộ P1 là hồ sơ lịch sử, không làm authority cho hành vi đang triển khai.

## 1. Nguyên tắc bảo toàn chức năng

Chỉ dịch chuyển source view và khai báo cấu hình; **không** sửa logic trong các `<script>`, `<template>`, `<style>` của view; không sửa stores, services, composables, OpenAPI generated SDK, API transport, backend, database/migrations. Không đổi route path/name/meta, lazy-loading entry, menu hierarchy/permission hint, nhãn song ngữ hay hành vi router guard. Các nút ẩn/hiện qua Base Permission tiếp tục chỉ là **UI hint**, server PBAC có thẩm quyền.

Bằng chứng bất biến nằm tại [`frontend/scripts/refactor/baseline-2026-10-14.json`](../../frontend/scripts/refactor/baseline-2026-10-14.json), được thu **trước** refactor. Kiểm tra bằng [`check-equivalence.cjs`](../../frontend/scripts/refactor/check-equivalence.cjs), không sửa baseline này chỉ để làm hết lỗi. **Không bật gate đối chiếu baseline này như một lệnh cấm mọi thay đổi tính năng trong tương lai**; khi cố ý sửa hành vi phải cập nhật contract/test kèm change control mới.

## 2. Sơ đồ thư mục sau tổ chức

```text
frontend/src/
├── app/
│   ├── navigation.ts             # menu/sidebar/recent route hints
│   └── pages/NotFoundView.vue
├── features/
│   ├── account/pages/            # hồ sơ, preference
│   ├── auth/pages/               # đăng nhập
│   ├── catalog/pages/            # sản phẩm, UOM, giá, nhóm
│   ├── organization/pages/       # organization, locations
│   ├── people/pages/             # nhân sự
│   ├── product-import/pages/     # wizard, jobs, row detail
│   ├── security/pages/           # users, resources, policies, logs
│   ├── settings/pages/           # display settings, connectivity
│   ├── workflow/pages/           # states, lifecycles, auto-numbering
│   └── workspace/pages/          # dashboard
├── router/
│   ├── routes.ts                 # named routes, meta, lazy imports
│   ├── index.ts                  # history + auth/dirty guards
│   └── breadcrumbs.ts
├── i18n/
│   ├── index.ts                  # public facade, persistence
│   └── locales/{vi,en}.ts       # exact locale payloads
├── api/generated/               # generated, do not hand-edit
├── services/                    # existing API/domain adapters (unchanged)
├── stores/                      # existing Pinia state (unchanged)
├── modules/                     # existing pure domain helpers (unchanged)
├── composables/                 # reusable state/logic (unchanged)
└── components/                  # shared reusable forms/lists/domain UI (unchanged)
```

Các bài test cấp page ở `src/views/__tests__` đã được đặt bên cạnh các feature tương ứng (`organization/__tests__`, `product-import/__tests__`); path import trong bài test import wizard được cập nhật. Các unit/E2E test khác giữ nguyên.

## 3. Ma trận bảo toàn hành vi và bằng chứng

| Ranh giới | Baseline | Sau refactor | Cách kiểm chứng |
|---|---|---|---|
| View UI/logic | 28 file Vue | 28 file Vue ở module mới | SHA-256 **của từng file** trùng từng byte |
| Router | 29 named routes | 29 named routes | AST snapshot: path, name, meta, thứ tự, component basename và nested tree giống hệt |
| Navigation | Menu + sidebar + recent hints | `app/navigation.ts` | AST snapshot: icon, label, permission, tree, path sets giống hệt |
| VI/EN | 789 leaf messages mỗi ngôn ngữ | 789 leaf messages mỗi ngôn ngữ | SHA-256 từ cây key/value đã canonicalize giống hệt |
| API/services/stores/composables | Snapshot ban đầu | Không đổi | SHA-256 từng TS source/API spec/backend route |
| Build naming | `page-*` cho `src/views/*` | `page-*` cho `src/features/*/pages/*` và `src/app/pages/*` | `node --test scripts/build-output-names.test.mjs` |
| Imports TS/Vue | Alias `@/*`, relative imports | Mọi target đều tồn tại | `node scripts/check-imports.cjs` |

> **Cảnh báo chứng cứ:** SHA/AST và syntax chỉ chứng minh mức tĩnh. Việc đổi location module có thể ảnh hưởng build chunks/CSS/module graph; phải chạy build, unit tests, Playwright/UAT trên môi trường đầy đủ trước khi khẳng định chắc chắn tương đương runtime.

## 4. Lệnh kiểm chứng và điều kiện merge

Từ `frontend/` với Node thỏa `engines` và `pnpm` đúng `packageManager`:

```bash
pnpm install --frozen-lockfile
pnpm run check:imports
pnpm run check:refactor-baseline
node --experimental-strip-types --test scripts/build-output-names.test.mjs
pnpm run type-check
pnpm run lint
pnpm run format:check
pnpm run test:unit -- --run
pnpm run build
pnpm run test:e2e
```

Trong môi trường biên soạn lần này **npm registry bị lỗi DNS, chưa có `node_modules`**: chỉ chạy được script static dùng TypeScript được cung cấp riêng trong môi trường và Node built-in test chunk, chưa chạy `pnpm type-check`, `lint`, `format:check`, Vitest, Vite build, Playwright. Chưa kết nối BE/DB nên không test lại chức năng end-to-end. Trước deploy cần xác minh nhất là login/logout + CSRF/401, hydration, dirty tabs, menu permissions, product CRUD/image, Import Wizard, employee assignment, chính sách IAM, 403/404 và vi/en.

## 5. Quy ước phát triển kể từ bản này

1. Feature mới: tạo `features/<domain>/pages/`, route import lazy trong `router/routes.ts`, UI shell menu ở `app/navigation.ts` nếu thực sự cần; luôn thêm localization cho VI/EN.
2. `components/lists` và form controls là shared UI; đừng sao chép Table First, pagination, status/action patterns vào từng page.
3. Giữ luồng `page → service adapter → API SDK/axios`, không nhét auth/network vào presentational components và không gọi API trực tiếp từ template. Server vẫn thực thi PBAC.
4. Không thêm business code vào `src/api/generated/`; sửa OpenAPI rồi generate client nếu contract thực sự thay đổi.
5. Không đổi `stores` session logic, API CSRF concurrency, decimal/string money formatting, dirty state khi chỉ refactor presentation.
6. Khi tách `AppShell` template, các view lớn hoặc domain services tiếp: làm theo từng module, bổ sung unit/e2e regression trước khi thay đổi tương tác hoặc async flow. **Chưa làm** các đợt đó ở refactor v1 vì thiếu môi trường runtime.
7. Cập nhật catalogue tại root: `python tools/docs/rebuild_asbuilt_catalogs.py` và `python tools/docs/check_asbuilt_docs.py` trước merge; chú ý catalogue static không kiểm chứng runtime.

## 6. Phạm vi không làm

Không thay đổi giao diện, thông báo, validation, UX/permissions, API request payload, navigation timing, số bảng/trường, nghiệp vụ pricing/import/workflow. Không đổi UI semantics có chủ đích. Không tuyên bố CI/CD xanh hoặc đã so sánh screenshot; cần staging chạy đủ test trước khi triển khai sản xuất.
