# 09 — Verification / Traceable QA Matrix

**Mục tiêu:** không đánh đồng **test tồn tại** với **test đã PASS**. Trạng thái tại lần đầu dựng bộ docs: `STATIC-SCANNED` cho inventory; runtime PHPUnit/Vitest/Playwright/DB/API vẫn `NOT-RUN`.

## 1. Test inventory (kiểm kê tĩnh)

| Hạng mục | Số lượng source files | Đánh giá |
|---|---:|---|
| PHPUnit `*Test.php` | **102** | Có test source; chưa chứng minh pass trong môi trường dependencies/DB |
| Vitest `*.spec.ts` dưới `frontend/src` | **26** | Có test source; chưa chứng minh pass |
| Playwright `frontend/e2e/**/*.spec.ts` | **17** | Có E2E files, gồm live variant; không chứng minh pass hoặc server authorization |
| Backend /api/v1 vs OpenAPI | **149/149** operation signatures | Static equal; **không** xác thực schemas/payloads |
| Frontend named routes | **29** | Static discovered; **không** xác thực render/browser |
| DB local migration schema | **17 files / 31 tables** | Static discovered; **không** xác thực DDL trên DB thật |

Liệt kê test từng file và thư mục: [TEST_FILES.md](reference/TEST_FILES.md). Máy đọc và số liệu: [inventory.json](reference/inventory.json).

## 2. Test matrix theo rủi ro

| ID | Miền / behavior cần xác nhận | Evidence nguồn | Runtime gate bắt buộc |
|---|---|---|---|
| QA-001 | Health/Shield login/logout/csrf/expired session | `Api/Auth.php`, `services/api.ts` | HTTP cookie, CSRF rotate, credentialed CORS |
| QA-002 | BIGINT/decimal & list envelope + pagination | `ApiTransport`, `CommonListQueryAdapter` | Large ID, decimal string, authorized total, max page |
| QA-003 | Employee ACTIVE/effective assignment/org ancestry | `AuthorizationService`, HR services | Cross-org positive/negative, no assignment, disable employee |
| QA-004 | PBAC policy precedence, no-match, ABSOLUTE_DENY, inactive Resource | `AuthorizationService`, policy repository | Test mỗi lane, conflict policies, read/write isolation |
| QA-005 | Contact owner split 000015, legacy grants | migration 000015, `ContactAuthorizationRegistry` | Existing policy/grant data rehearsal; no unauthorized owner access |
| QA-006 | Workflow state native/lifecycle, transition | `ResourceWorkflowStateResolver`, `BusinessWorkflowActions` | Actor action+setstate, persisted from state, custom code lengths |
| QA-007 | Numbering series/counter uniqueness/concurrency | `Api040AutoNumberingService`, numbering repository | Concurrent allocate and restart/rollback |
| QA-008 | Product SIMPLE/PARENT/CHILD/COMBO, UOM replace | `ProductRoleService`, `ComboCompositionService`, UOM service | Invalid cycles/combinations, unit precision, PBAC edit |
| QA-009 | Price resolver company → group → fallback | `PricingService`, price list service | Price/UOM combinations and organizational policy |
| QA-010 | Product lookup/batch resolve visibility | `ProductLookupService`, `EffectiveListBucketCompiler` | Unauthorized option omission, same pagination semantics |
| QA-011 | Import CSV/XLSX/modes/row reconciliation | `ProductImportAuthorizationService`, `Config/Import` | Partial failure, 5000 rows, 10 MiB, retry/timeout |
| QA-012 | Product image upload/read/delete (private) | `ProductImageStorage`, `ProductImages` | Valid/invalid mime, 3 MiB/5000px, inaccessible file/direct URL |
| QA-013 | Display settings fallback + audit + local preferences | `DisplaySettingsResolver`, `stores/displaySettings.ts` | Org ancestors, reset, locale/timezone and audit |
| QA-014 | Vue route guard/tab dirty/permission hints | `router/index.ts`, `AppShell.vue` | Navigation, stale 403, session-change requests, responsive UI |
| QA-015 | Migration compatibility and rollback safety | 000012–000017 + vendor migrations | DB clone with legacy grants, check schema/index/rollback guard |
| QA-016 | OpenAPI contract vs HTTP payload | `contracts/openapi`, controllers, generated SDK | Schema validation including multipart/binary and all error codes |

Các file test source liên quan có thể tồn tại, nhưng trạng thái tất cả runtime gates ở bảng này đang **PENDING**. Để tuyên bố pass phải có command, timestamp, environment/database identity, test count, exit code, log/CI artifact và reviewer.

## 3. Phân biệt các tầng verification

- **S — Static scanned:** quét route, migration, types và file text. Không biết DB đã migrate hay hoạt động logic.
- **L — Syntax/lint:** PHP syntax / script parse / Markdown links; không kết nối API hoặc DB.
- **T — Automated tests executed:** PHPUnit/Vitest/Playwright có log pass/fail và fixtures.
- **R — Runtime integration:** backend, database, browser, infrastructure thực và smoke.
- **U — User Acceptance:** scenario được product owner ký, bao gồm nghiệp vụ pricing/workflow/contact.

Chỉ nâng trạng thái khi có evidence tương ứng; kiểm thử mock frontend không tự chứng minh backend PBAC, match OpenAPI path không chứng minh body, và scanner không chứng minh DDL thực.

## 4. Commands để tái kiểm

```sh
# Static catalogues, thực hiện từ root
python tools/docs/rebuild_asbuilt_catalogs.py

# PHP syntax: chạy được nếu PHP CLI cài (không cần composer dependencies)
find backend/app -type f -name '*.php' -print0 | xargs -0 -n 1 php -l

# Dependencies cần cài trước:
(cd backend && vendor/bin/phpunit)
(cd frontend && pnpm run type-check && pnpm run lint && pnpm run test:unit -- --run)
(cd frontend && pnpm run openapi:lint && pnpm run openapi:generate:check && pnpm run build)
(cd frontend && pnpm run test:e2e)
```

Sau mỗi lần release, lưu evidence (CI URL/logs), cập nhật ngày và source SHA ở [00](00_SCOPE_AND_BASELINE.md), đối chiếu drift [10](10_P1_DRIFT_REGISTER.md).

## 5. Kết quả lần dựng baseline

| Verification | Kết quả ngày 2026-10-14 | Ý nghĩa |
|---|---|---|
| Static catalog generator `python tools/docs/rebuild_asbuilt_catalogs.py` | **PASS**; 17 migrations, 31 tables, 355 cols, 46 FK, 152 backend API method paths, 149 OpenAPI ops, 29 frontend routes | Source inspection / signature comparison |
| `php -l` toàn bộ PHP dưới `backend/app` | **PASS: 359/359 PHP files**, PHP CLI 8.4.24 | Chỉ syntax; không chạy framework hoặc DB |
| `python tools/docs/check_asbuilt_docs.py` | **PASS_STATIC**, 17 Markdown docs tại mốc 1.0 (lịch sử) | Existence of targets and manifest fingerprints; không verify in-page anchor rendering |
| `vendor/bin/phpunit` | **NOT-RUN** — thiếu `backend/vendor` | Không tuyên bố backend tests pass |
| `pnpm run test:unit` / type-check / lint / build | **NOT-RUN** — thiếu `frontend/node_modules` và pnpm CLI | Không tuyên bố FE tests/build pass |
| `pnpm run test:e2e` | **NOT-RUN** — thiếu browser/FE dependencies/test backend | Không tuyên bố end-to-end pass |
| Runtime migrations / API requests / browser UAT | **NOT-RUN** — chưa có DB, dependency, deployment test | Chưa xác thực production state |

Script kiểm tra tài liệu: [`tools/docs/check_asbuilt_docs.py`](../../tools/docs/check_asbuilt_docs.py). Nếu chạy lại ở source khác, kết quả hiện tại chỉ là lịch sử ngày chốt, không thay cho QA phiên bản mới.

## 6. Phần bổ sung: Frontend refactor v1 (2026-10-14)

- `frontend/scripts/refactor/check-equivalence.cjs`: **PASS 6/6 contract sections** (29 route, navigation, 789/789 locale leaves, 28 view SHA, shell/guard/locale runtime logic, critical TS/API checksums).
- `frontend/scripts/check-imports.cjs`: **PASS, 0 unresolved local imports** (536 edges trên 141 TS/Vue files trong lần rà soát).
- `node --experimental-strip-types --test frontend/scripts/build-output-names.test.mjs`: **PASS 3/3** test helper chia bundle.
- `tools/docs/check_asbuilt_docs.py`: **PASS_STATIC**, 18 Markdown docs, 743 links ở lần kiểm trước khi đóng gói.
- Unit Vitest source tăng từ 24 lên 26 do bổ sung regression về route và navigation (chưa chạy các test này).
- **NOT-RUN:** `pnpm install`, `vue-tsc`, Vitest, ESLint/Oxlint, Vite build, Playwright/UAT; thiếu dependencies, npm registry DNS lỗi.

Không được coi static-equivalence hoặc cú pháp hợp lệ là chứng minh build/runtime đã tương đương. Xem [12_FRONTEND_REFACTOR_V1.md](12_FRONTEND_REFACTOR_V1.md) để chuẩn bị gate khi có môi trường test.

## 7. Kết quả Windows từ người dùng và hotfix v1.1 (2026-10-14)

Log Windows (Node 24.21.0/pnpm 10.18.2) **trước hotfix** xác nhận `pnpm install` thành công, `check:imports` PASS 536 edges, bundle-naming tests PASS 3/3, Vitest có 98 tests PASS nhưng 2 suites lỗi load, và Vite compiler hoàn thành trong khi command `build` thất bại vì TypeScript. Các lỗi tập trung ở test/tooling mới của v1.

Hotfix v1.1 **đã** chạy tại máy biên soạn: Node tests chuẩn hóa path 3/3 PASS và static equivalence 6/6 PASS. `pnpm run type-check`, lint, format, Vitest/build **sau hotfix** và Playwright vẫn là **PENDING**; không coi chúng đạt chỉ vì code được sửa. Xem [13](13_FRONTEND_REFACTOR_V1_1_HOTFIX.md) để chạy lại toàn bộ gate.


## 8. Log Windows sau v1.1 và scope hotfix v1.2 (2026-10-14)

Từ log Windows do người dùng cung cấp: `pnpm run type-check` **PASS**; `pnpm run lint:oxlint` **PASS**; ESLint **FAIL 13 errors** ở lệnh `require()` thuộc scripts `.cjs` (cộng 3 non-blocking E2E warnings); `format:check` **FAIL 1 file** `navigation.spec.ts`; `test:unit` có **102 tests PASS thuộc 26 suites**, nhưng tổng FAIL vì Vitest thu thập thêm một test dành cho `node:test`. `check:refactor-baseline` **5/6** vì backend routes và OpenAPI trên máy người dùng khác hash đã khóa. Chưa có log `build` sau v1.1 hoặc Playwright.

Bản v1.2 (xem [14](14_FRONTEND_REFACTOR_V1_2_VALIDATION.md)) đã sửa cách tổ chức công cụ kiểm chứng mà không đổi application runtime. Máy biên soạn đã chạy Node path/hash tests **7/7**, import gate **536/536**, bundle naming **3/3**, frontend source equivalence **6/6**, và thử nghiệm âm tính strict-external/frontend drift. **PENDING rerun on Windows**: ESLint/Prettier/Vitest/type-check/build; **PENDING runtime**: Playwright/UAT FE-BE-DB. Chênh lệch backend/OpenAPI trên máy người dùng được cảnh báo tường minh, không tự động cập nhật manifest hoặc kết luận tương thích API.

## 9. Evidence Windows sau v1.2 + checkpoint để mở V2 (2026-10-14)

**Nguồn:** log chạy lệnh trực tiếp trên Windows và log Playwright do người dùng cung cấp trong cuộc hội thoại. Kết quả `[UT]` (user-reported test execution), không phải `[T]` do môi trường biên soạn tự chạy.

- FE `check:imports`: **536/536**; `check:refactor-baseline`: **6/6 FE contract sections** + **WARN external** cho Routes.php/OpenAPI; TypeScript **PASS**; Oxlint **PASS**; ESLint **0 error, 3 warning** (file Playwright đã tồn tại); Prettier **PASS**.
- Unit: **26/26 files**, **102/102** tests PASS; production build: **PASS** (Vite 4.277 modules).
- Playwright mặc định: **243/243** tests PASS, **8,8 phút**, 1 worker. Bằng chứng E2E ở các test được thiết kế, không tự xác nhận coverage với DB/live backend hoặc production setup.
- Frontend V2 source checkpoint: `FE-V2-START-2026-10-14`; chạy `pnpm run check:v2-baseline` để xác nhận byte-level snapshot trên máy thực tế. Chỉ tool + docs được bổ sung, không refactor UI/behavior mới ở bước baseline.
- **Open:** đối chiếu hai file backend/OpenAPI khác hash trên Windows; backend PHPUnit, live integration/API schema, migrate DB/UAT production chưa được cung cấp.

Tham khảo [15_FRONTEND_V2_START_BASELINE.md](15_FRONTEND_V2_START_BASELINE.md).

## Frontend V2.1 — AppShell composition (14/10/2026)

V2.1 tách logic AppShell thành bốn composable và chuyển stylesheet nguồn sang `<style scoped src=...>`. Check tại máy biên soạn: template SHA-256 và CSS SHA-256 trùng V2 Start; **72/72 AST script statements** được giữ; **4/4 Node contract tests** và **546/546 import** PASS. Chưa có kết quả type-check/Vitest/build/Playwright sau V2.1 trên Windows; không sử dụng thành tích v1.2 để tự động đánh dấu V2.1 runtime PASS. Xem [16](16_FRONTEND_V2_1_APPSHELL_REFACTOR.md).


## Frontend V2.1 user Windows runtime acceptance (14/10/2026)

The user executed V2.1 gates on Windows: `check:v2-1-shell` **4/4**, import validation **546/546**, `vue-tsc` **PASS**, Oxlint **0 errors**, ESLint **0 errors / 3 pre-existing Playwright warnings**, Prettier **PASS**, Vitest **26/26 files and 102/102 tests**, Vite production build **PASS** (4,281 modules), Playwright **243/243 tests PASS (9.2 min)**. This is evidence supplied by the user, not tests executed in the archive-building container. The V2.1 source was not independently compared against that machine's backend/OpenAPI deviations.

## Frontend V2.2 build-workspace verification (14/10/2026)

Product Editor organization-only refactor: `check:v2-2-product-editor` **6/6**, import gate **573/573**, prior AppShell tests **4/4**, V2.1-vs-V2.2 deterministic payload/validation comparisons **2,250/2,250**. New Vitest unit tests were added but could not be executed here; `vue-tsc`, ESLint, Prettier, Vite build and Playwright are **PENDING Windows execution**. See [17_FRONTEND_V2_2_PRODUCT_EDITOR_REFACTOR.md](17_FRONTEND_V2_2_PRODUCT_EDITOR_REFACTOR.md).


## Frontend V2.3 build-workspace verification (14/10/2026)

Employees organization-only refactor: `check:v2-3-employees` **5/5**, prior guards V2.1 + V2.2 **10/10**, frontend import scanner **609/609**, differential probe comparing V2.2.1 to V2.3 forms/payload/manager validation **10,000/10,000**, isolated TypeScript semantic probe with module stubs **PASS**. Auth/route/API/template/styles source not modified. **18 new Vitest tests written but not executed**. Full `vue-tsc`, ESLint/Prettier, Vitest/Vite and Playwright **PENDING Windows execution**. See [18](18_FRONTEND_V2_3_EMPLOYEES_REFACTOR.md).

## 14. Frontend V2.4 security-policy source acceptance (2026-10-14)

V2.3 was user-reported green on Windows: 135/135 Vitest, 243/243 Playwright, type-check, lint with three inherited nonblocking warnings, format, build. V2.4 modifies Security Policies only and adds 28 Vitest tests. Builder static checks passed: source guard **6/6**, accumulated guards **21/21**, 653/653 imports, 15,000-case differential oracle, isolated TS module/test type probe. **All V2.4 Windows runtime gates are pending**, so do not transfer V2.3 green status. See [19](19_FRONTEND_V2_4_POLICIES_PBAC_REFACTOR.md).
