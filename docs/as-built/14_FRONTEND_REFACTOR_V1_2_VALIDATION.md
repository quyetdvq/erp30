# 14 — Frontend Refactor v1.2: Validation Gate Repair

**Ngày:** 2026-10-14. **Đầu vào:** frontend v1.1 đã áp dụng trên Windows (PowerShell, Node 24.21.0, pnpm 10.18.2). **Phạm vi:** tooling, test và tài liệu, không đổi code nghiệp vụ, tuyến điều hướng, API client, UI hoặc backend.

## 1. Kết quả quan sát trên Windows sau khi áp dụng v1.1

| Gate | Evidence từ log thực tế của người dùng | Chẩn đoán |
|---|---|---|
| `pnpm run check:refactor-baseline` | Node path tests 3/3 PASS; 5/6 section PASS; `backend/app/Config/Routes.php` và `contracts/openapi/openapi.yaml` không khớp hash trong manifest | Máy người dùng có hai file **khác** mốc snapshot. **Không có diff nội dung**, nên không suy diễn ai thay đổi và có tương thích API hay không. Đây không phải bằng chứng phần refactor frontend đã làm đổi hai file. |
| `pnpm run type-check` | Không lỗi, exit code 0 | PASS trên Windows v1.1 |
| `pnpm run lint` | Oxlint PASS; ESLint FAIL do 13 lệnh `require()` trong các `.cjs` của tooling; 3 cảnh báo test E2E | `@typescript-eslint/no-require-imports` không phù hợp đối với những maintenance scripts chủ động dùng CommonJS. Ba warning E2E không chặn lint và thuộc phạm vi khác. |
| `pnpm run format:check` | Chỉ báo `src/app/__tests__/navigation.spec.ts` | Test mới chưa được format nhất quán với Prettier. |
| `pnpm run test:unit -- --run` | 102 test PASS thuộc 26 suites, nhưng 1 suite FAIL: `scripts/refactor/paths.test.cjs` | Vitest tự phát hiện file `node:test` (không phải Vitest test), nên thông báo *No test suite found*. |
| `pnpm run build` | Không có log mới sau v1.1 | PENDING; không suy diễn PASS từ `type-check`. |

## 2. Thay đổi v1.2

1. **ESLint** (`frontend/eslint.config.ts`): áp dụng ngoại lệ `@typescript-eslint/no-require-imports: off` **chỉ cho** `scripts/**/*.cjs`. TypeScript/Vue app và các script `.ts` không thay đổi quy tắc. Không đổi các file `.cjs` sang ESM một cách không cần thiết.
2. **Vitest** (`frontend/vitest.config.ts`): bỏ thu thập mọi `scripts/**` trong Vitest. Bộ kiểm thử Node của scripts vẫn chạy độc lập từ `check:refactor-baseline`; không làm mất các test ứng dụng dưới `src/**`.
3. **Format + trùng lặp test** (`src/app/__tests__/navigation.spec.ts`): đưa logic tìm child routes vào `getChildRoutes()`; không đổi assertions hay route metadata.
4. **Baseline policy** (`scripts/refactor/check-equivalence.cjs`): giữ nguyên manifest gốc, khóa hash frontend; riêng `backend/app/Config/Routes.php` và `contracts/openapi/openapi.yaml` ghi cảnh báo **WARN externalDependencyHashes** khi khác mốc. Hai file này là dependencies dùng chung, không thuộc phạm vi frontend refactor. **Không coi WARN này là bằng chứng chúng tương thích với FE.**
5. **Strict audit**: chạy `pnpm run check:refactor-external` để so lại đầy đủ với hai hash ngoài frontend và trả exit code khác 0 nếu chúng khác. `--strict-external` hữu ích khi audit baseline gốc hoặc cần đánh giá API compatibility trước release.
6. **Regression coverage**: 4 bài `node:test` mới cho hash policy; tập test baseline tăng lên tổng 7 (3 paths + 4 policy).

Không sinh lại hoặc tự động ghi đè `frontend/scripts/refactor/baseline-2026-10-14.json`. Nếu backend/OpenAPI đã thay đổi có chủ đích, cần đối chiếu API route, schemas, generated SDK và kiểm thử integration. Một snapshot cũ không thể thay thế việc review đó.

## 3. Lệnh chạy lại trên Windows

Tại thư mục gốc `D:\XAMPP\htdocs\erp` nơi đã áp dụng **v1.1**:

```powershell
git apply --check .\erp30_frontend_refactor_v1_2_hotfix.patch
git apply .\erp30_frontend_refactor_v1_2_hotfix.patch
cd frontend
pnpm run check:imports
pnpm run check:refactor-baseline
pnpm run type-check
pnpm run lint
pnpm run format:check
pnpm run test:unit -- --run
pnpm run build
```

Nếu muốn đánh giá chênh lệch backend/OpenAPI so với manifest cũ, chạy thêm:

```powershell
pnpm run check:refactor-external
# Truy tìm thay đổi ngoài frontend khi cần:
cd ..
git status --short -- backend/app/Config/Routes.php contracts/openapi/openapi.yaml
git diff -- backend/app/Config/Routes.php contracts/openapi/openapi.yaml
git log -p -3 -- backend/app/Config/Routes.php contracts/openapi/openapi.yaml
```

Không tự chạy `--capture` hoặc sửa hash trong manifest cho đến khi đã review. File khác do thay đổi đã **commit** sẽ không xuất hiện trong `git diff` working tree, nên cần đối chiếu lịch sử hoặc snapshot nguồn ban đầu. Sau cùng cần chạy Playwright và UAT FE+BE+DB để kiểm chứng giữ nguyên hành vi.

## 4. Evidence thực thi trên máy biên soạn

- `node --test scripts/refactor/paths.test.cjs scripts/refactor/hash-policy.test.cjs`: **PASS 7/7**.
- `NODE_PATH=<global TypeScript> node scripts/refactor/check-equivalence.cjs`: **PASS 6/6 frontend sections**, external hashes match trong snapshot được dùng để xây bản vá.
- `NODE_PATH=<global TypeScript> node scripts/check-imports.cjs`: **PASS 536/536 local imports**.
- `node --experimental-strip-types --test scripts/build-output-names.test.mjs`: **PASS 3/3**.
- Bài thử âm tính có phục hồi source: sửa giả lập hai file external → frontend gate **WARN và exit 0**, strict audit **FAIL và exit 1**; sửa giả lập `frontend/src/api/client.ts` → frontend gate **FAIL và exit 1**. Các file đều được phục hồi sau test.
- **Chưa chạy tại máy biên soạn:** lint bằng ESLint CLI, Prettier CLI, Vitest, vue-tsc, Vite build, Playwright do thiếu dependency npm. Cần log Windows sau v1.2 để chốt tất cả gate.

Các kết quả trên không thay thế việc kiểm chứng sự tương thích API thật khi hai file external đã thay đổi. Đối chiếu thêm tại [09 — Verification Matrix](09_VERIFICATION_MATRIX.md) và lịch sử [13 — Hotfix v1.1](13_FRONTEND_REFACTOR_V1_1_HOTFIX.md).
