# 13 — Frontend Refactor v1.1: Validation Hotfix (Windows + Vitest)

**Ngày:** 2026-10-14. **Trạng thái:** `SOURCE-PATCHED / STATIC-EQUIVALENCE-PASS / WINDOWS-RERUN-PENDING`.

Đợt hotfix này xử lý lỗi phát hiện khi áp dụng `erp30_frontend_refactor_v1.patch` trên Windows / PowerShell, Node 24.21.0, pnpm 10.18.2. **Không đổi route, nghiệp vụ, API, UI, backend, schema hoặc nội dung translation.** Đây không phải một baseline nghiệp vụ mới; dùng nguyên manifest `frontend/scripts/refactor/baseline-2026-10-14.json`.

## 1. Chẩn đoán từ log người dùng

| Gate | Evidence ban đầu trên Windows | Nguyên nhân | Thay đổi v1.1 |
|---|---|---|---|
| `check:imports` | PASS 536 imports | Không có lỗi | Giữ nguyên |
| `check:refactor-baseline` | ENOENT `D:\...\erp\D:\...\erp\frontend\src\api\client.ts` | Script chỉ nhận diện absolute path bắt đầu bằng `/`; Windows drive-absolute path bị ghép hai lần | Dùng `path.isAbsolute` + `path.relative`, chuẩn hóa slash để khớp manifest; thêm test Windows/POSIX |
| `type-check` | TS2550 ở `routesStructure.spec.ts` | `.at(-1)` không thuộc `lib` TypeScript cấu hình | Dùng `children[children.length - 1]`, không nâng target/lib |
| `lint:oxlint` | Hai lỗi `vitest(valid-expect)` | `expect(actual, diagnostic)` có 2 arguments không hợp lệ | So sánh danh sách đường dẫn/tên route thiếu với `[]` |
| `test:unit` | 98 tests passed, 2 suites failed at import | Test route import nguyên `AppShell.vue` và các public assets trong Node/Vitest | Mock riêng `AppShell.vue` trong 2 suite metadata; không sửa router hoặc shell production |
| `test:unit` warnings | `EyeOutline`, `NIcon`, `Refresh` chưa được resolve trong 1 component test | `main.ts` có đăng ký global icons, test mount độc lập chưa đăng ký | Đăng ký 3 component trong mount của test, không chỉnh production component |
| `format:check` | Warn `navigation.spec.ts`, `locales/en.ts`, `locales/vi.ts`, E2E FE090 | Layout source chưa đúng Prettier. E2E FE090 đã tồn tại và không thay đổi từ baseline trước v1 | Chuẩn hóa test và các chỗ xuống dòng trong locale/E2E; cần chạy Prettier để xác minh chính xác |
| `build` | Vite transform 4277 modules và sinh `dist`, command tổng FAIL | `pnpm run build` còn chạy `type-check` song song và TypeScript failed | Loại bỏ lỗi TypeScript; cần chạy lại gate tổng trên Windows |

## 2. Phạm vi hotfix

- `scripts/refactor/paths.cjs` cung cấp chuẩn hóa đường dẫn cho cả Windows/POSIX, từ path thực tế tới manifest keys dạng `frontend/src/...`. Từ chối đường dẫn thoát repository.
- `scripts/refactor/paths.test.cjs`: 3 regression tests dùng `node:test`, không cần npm packages. Lệnh `check:refactor-baseline` chạy path tests trước rồi so 6 nhóm contract.
- Hai suite `src/app/__tests__/navigation.spec.ts` và `src/router/__tests__/routesStructure.spec.ts` chỉ kiểm tra config, không load `AppShell` hay các side effect trình duyệt.
- Test focus modal đăng ký đúng các icon tương đương bootstrap trong `src/main.ts`.
- Chỉnh format text trong locale/E2E, không sửa chuỗi dịch, assertion hay biểu thức tác vụ.

**Quan trọng:** chạy `git diff` trước commit để chắc chắn không chèn thêm thay đổi nghiệp vụ từ nhánh địa phương. File manifest baseline không bị viết lại nhằm làm xanh kiểm tra.

## 3. Lệnh chạy lại trên Windows (từ thư mục `frontend/`)

```powershell
pnpm install --frozen-lockfile
pnpm run check:imports
pnpm run check:refactor-baseline
node --experimental-strip-types --test scripts/build-output-names.test.mjs
pnpm run type-check
pnpm run lint
pnpm run format:check
pnpm run test:unit -- --run
pnpm run build
```

Nếu Prettier vẫn báo sai khác về xuống dòng, dùng Prettier theo đúng version trong `pnpm-lock.yaml` để chuẩn hóa bốn file (không sửa nội dung):

```powershell
pnpm exec prettier --write src/app/__tests__/navigation.spec.ts src/i18n/locales/en.ts src/i18n/locales/vi.ts e2e/fe090-lookup-batch-regression.spec.ts
pnpm run format:check
```

Sau đó chạy lại `pnpm run check:refactor-baseline`: phép so locale dựa trên object key/value nên các thay đổi whitespace không làm đổi contract, và manifest không cần cập nhật. Cần tiếp tục Playwright/UAT với FE+BE+DB trước deploy; Vitest mock không thay thế browser test.

## 4. Evidence và giới hạn

- **PASS nội bộ sau hotfix**: 3/3 path tests với `path.win32`/`path.posix` dưới Node; 6/6 baseline static contract sections (29 named routes, UI shell logic, 28 view SHA, 789+789 translation leafs, critical API/service/store/backend hashes).
- **Từ Windows log trước hotfix**: `check:imports` 536 edges PASS, bundle-naming 3/3 PASS, Vitest 98 tests PASS nhưng 2 suites không load được, Vite bundler PASS nhưng `pnpm run build` tổng thất bại. Không coi đó là kiểm thử phiên bản v1.1 thành công.
- **CHƯA xác nhận sau hotfix**: `pnpm run type-check`, `lint`, `format:check`, Vitest, Vite build, Playwright và runtime/UAT trên Windows vì máy biên soạn không có dependencies và npm registry DNS không truy cập được.

Tham chiếu: [12 — Refactor v1](12_FRONTEND_REFACTOR_V1.md), [09 — Verification Matrix](09_VERIFICATION_MATRIX.md).
