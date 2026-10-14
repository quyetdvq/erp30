# ERP30 — Code-first As-built Documentation

**Bản tài liệu:** ASBUILT-2026-10-14 / 1.0  
**Nguồn thực tế:** snapshot `erp_v1.1.2.zip` (archive ghi commit `2cc1e9369bee76a23527cb81684497471af9166d`), được khởi tạo thành baseline Git cục bộ `d935186386e094b96d92b8fe6f4458d1610562a0`.  
**Ngày chốt phân tích:** 2026-10-14. **Trạng thái:** `CODE-INSPECTED / RUNTIME-NOT-VERIFIED`.

> **QUAN TRỌNG:** Đây là tài liệu mô tả **những gì code đang triển khai**, không phải tài liệu yêu cầu tính năng tương lai, biên bản nghiệm thu hay cam kết rằng bản triển khai sản xuất đã áp dụng migration. Bộ P1 trong `docs/architecture/` và evidence P1 trong `docs/implementation/` được **giữ làm lịch sử**, không tự động được xem là hiện trạng. Không dùng P1 để ghi đè hành vi thật của code khi xảy ra xung đột.

## Bộ tài liệu mới — đọc theo mục đích

| Mã | Tài liệu | Nội dung / thay thế nhóm tài liệu P1 |
|---|---|---|
| 00 | [00_SCOPE_AND_BASELINE.md](00_SCOPE_AND_BASELINE.md) | Phạm vi thực thi, kiểm kê, thẩm quyền và giới hạn bằng chứng (đối chiếu UR) |
| 01 | [01_FUNCTIONAL_AS_BUILT.md](01_FUNCTIONAL_AS_BUILT.md) | Chức năng hiện có và quy tắc nghiệp vụ, theo từng module (đối chiếu FRS) |
| 02 | [02_DATA_ARCHITECTURE.md](02_DATA_ARCHITECTURE.md) | Mô hình dữ liệu, migration, quan hệ, invariant (đối chiếu DBD) |
| 03 | [03_IMPLEMENTATION_ARCHITECTURE.md](03_IMPLEMENTATION_ARCHITECTURE.md) | Kiến trúc BE/FE, boundary, triển khai code (đối chiếu Implementation Contract) |
| 04 | [04_EVOLUTION_RULES.md](04_EVOLUTION_RULES.md) | Quy trình thay đổi và tái đồng bộ tài liệu (thay vai trò Plan như một baseline tiếp diễn) |
| 05 | [05_API_CONTRACT.md](05_API_CONTRACT.md) | API contract, cơ chế vận chuyển, versioning, phân quyền (đối chiếu API Design) |
| 06 | [06_FRONTEND_UI.md](06_FRONTEND_UI.md) | Routing, màn hình, menu, UX, client state (đối chiếu UI Design) |
| 07 | [07_IAM_AND_WORKFLOW.md](07_IAM_AND_WORKFLOW.md) | Shield, PBAC, context, lifecycle, transition, audit |
| 08 | [08_RUNTIME_OPERATIONS.md](08_RUNTIME_OPERATIONS.md) | Environment, migration/seed, build, deploy & restore |
| 09 | [09_VERIFICATION_MATRIX.md](09_VERIFICATION_MATRIX.md) | Test inventory, những gì đã/chưa kiểm chứng và gate nghiệm thu |
| 10 | [10_P1_DRIFT_REGISTER.md](10_P1_DRIFT_REGISTER.md) | Các sai lệch P1 ↔ code, mức độ bằng chứng, việc cần đối soát |
| 11 | [11_TRACEABILITY.md](11_TRACEABILITY.md) | Module → code → API → bảng → UI → test |
| 12 | [12_FRONTEND_REFACTOR_V1.md](12_FRONTEND_REFACTOR_V1.md) | Tái tổ chức frontend không đổi hành vi, kiểm chứng code-based, rủi ro còn lại |
| 13 | [13_FRONTEND_REFACTOR_V1_1_HOTFIX.md](13_FRONTEND_REFACTOR_V1_1_HOTFIX.md) | Hotfix kiểm chứng frontend trên Windows: path, Vitest, TypeScript, lint và format |
| 14 | [14_FRONTEND_REFACTOR_V1_2_VALIDATION.md](14_FRONTEND_REFACTOR_V1_2_VALIDATION.md) | Tách kiểm tra frontend và external API, sửa ESLint `.cjs`, Vitest node:test và Prettier |
| 15 | [15_FRONTEND_V2_START_BASELINE.md](15_FRONTEND_V2_START_BASELINE.md) | Chốt baseline frontend v1.2 cho V2: evidence Windows 102 Unit / 243 E2E, immutable source snapshot, boundary backend/API và gate V2 |
| 16 | [16_FRONTEND_V2_1_APPSHELL_REFACTOR.md](16_FRONTEND_V2_1_APPSHELL_REFACTOR.md) | V2.1: tách AppShell thành bốn composable, giữ template/CSS, đối chiếu AST và hướng dẫn quality gates trên Windows |
| 17 | [17_FRONTEND_V2_2_PRODUCT_EDITOR_REFACTOR.md](17_FRONTEND_V2_2_PRODUCT_EDITOR_REFACTOR.md) | Feature architecture of Product Editor, owned form/payload/validation/lookup/feedback and V2.2 quality gates |
| 18 | [18_FRONTEND_V2_3_EMPLOYEES_REFACTOR.md](18_FRONTEND_V2_3_EMPLOYEES_REFACTOR.md) | V2.3: People/Employees forms, payload, validation, lookups and assignments, with pinned DOM/CSS/AST and Windows verification gates |
| 19 | [19_FRONTEND_V2_4_POLICIES_PBAC_REFACTOR.md](19_FRONTEND_V2_4_POLICIES_PBAC_REFACTOR.md) | V2.4: Security Policies/PBAC form, State, participant and catalog extraction; unchanged DOM/CSS and explicit Windows acceptance gates |

## Catalogue tự động từ source

- [reference/API_ENDPOINTS.md](reference/API_ENDPOINTS.md): toàn bộ backend routes (expand `foreach`) ↔ OpenAPI operations, có file và dòng định nghĩa.
- [reference/DATABASE_SCHEMA.md](reference/DATABASE_SCHEMA.md): 31 application tables, từng cột/khóa ngoại/unique key từ 17 migration.
- [reference/FRONTEND_ROUTES.md](reference/FRONTEND_ROUTES.md): route → Vue view và gợi ý quyền ở menu.
- [reference/TEST_FILES.md](reference/TEST_FILES.md): các test files hiện có, **không** hàm ý đã chạy PASS.
- [reference/inventory.json](reference/inventory.json): bản máy đọc để làm automated diff và kiểm tra tính nhất quán.

Để tái tạo catalogue: từ **root repo**, chạy `python tools/docs/rebuild_asbuilt_catalogs.py` (Python ≥ 3.10 và `PyYAML`; cài bằng `python -m pip install pyyaml`). Script chỉ quét mã nguồn, không truy cập DB, mạng hay chạy hệ thống. Phần narrative phải rà soát bằng người khi nghiệp vụ thay đổi.

## Thứ tự ưu tiên chứng cứ

1. **Hành vi triển khai:** source tại commit baseline; kiểm thử chạy thực tế khi có môi trường phù hợp.
2. **API khai báo:** `contracts/openapi/openapi.yaml` (sẽ cần đối chiếu runtime payload), sau đó generated TS SDK.
3. **Data:** migration/seeder và truy vấn runtime (migration chưa chắc đã áp dụng trên mọi môi trường).
4. **UI:** Vue router, page, service, stores, tests.
5. **P1/Change Control/evidence cũ:** dùng để hiểu *ý định và lịch sử*, không thay thế code đang chạy.

Các mệnh đề trong bộ này mặc định mang mức chứng cứ **[S] = static source inspected**. **[T] = test executed** chỉ được dùng khi thực sự chạy và có kết quả. **[R] = runtime DB/API verified** đòi hỏi môi trường thực. **[H] = hypothesis / needs verification** không được gọi là lỗi đã xác thực.

## Cập nhật sau baseline 1.0 — Frontend structural refactor

Tài liệu số **12** ghi nhận thay đổi cấu trúc frontend, trong đó màn hình chuyển tới `frontend/src/features/*/pages/`, cấu hình route và menu tách thành các module riêng; catalogue đã tái tạo. Chứng cứ hiện tại vẫn là **static**; không suy diễn là đã chạy test browser/backend.

## Phạm vi thay đổi trong lần dựng tài liệu

Bản tài liệu as-built 1.0 ban đầu chỉ tạo Markdown/JSON/script và không sửa nguồn. Phiên bổ sung frontend refactor v1 **có thay đổi vị trí code Vue, router/nav/i18n**, nhưng không thay đổi nội dung từng view, API, backend, migrations, seed hoặc business logic theo manifest đối chiếu; xem tài liệu 12 và giới hạn kiểm thử. Để xem các vấn đề cần xác minh và điều kiện trước khi phát hành bản tài liệu chính thức, đọc [09](09_VERIFICATION_MATRIX.md) và [10](10_P1_DRIFT_REGISTER.md).

## Cập nhật hotfix v1.1 — kiểm chứng trên Windows

Xem [13](13_FRONTEND_REFACTOR_V1_1_HOTFIX.md) để biết các lỗi được phát hiện khi chạy gate trên Windows và các thay đổi chỉ áp dụng cho tooling/tests. Đây **không phải bằng chứng** rằng đã chạy lại Vitest/build/Playwright thành công sau hotfix.

## Cập nhật v1.2 — quality gates

Xem [14](14_FRONTEND_REFACTOR_V1_2_VALIDATION.md) cho log Windows sau v1.1, bản sửa v1.2 và hướng dẫn phân biệt **frontend baseline PASS** với **external contract drift (WARN / strict FAIL)**. Hai file backend/OpenAPI khác hash vẫn phải được review trước phát hành.

## Cập nhật baseline Frontend V2 (2026-10-14)

**Frontend v1.2 đã được người dùng kiểm chứng trên Windows:** static gates, type-check, lint (0 error/3 E2E warnings), format, **102 unit tests**, build và **243 Playwright E2E** đều đạt. Đây là evidence do người dùng cung cấp, không phải test chạy trực tiếp tại máy dựng tài liệu; Playwright mặc định không thay cho integration với production backend/DB.

Mốc **`FE-V2-START-2026-10-14`** khóa riêng frontend bằng manifest SHA-256 tại `frontend/scripts/refactor/v2-start-2026-10-14.json`; xem [15](15_FRONTEND_V2_START_BASELINE.md). **Không khóa/cập nhật backend và OpenAPI** trên máy người dùng vì hai file này đang khác hash và chưa có diff nội dung. Các đoạn `RUNTIME-NOT-VERIFIED` ở trên là lịch sử bản as-built 1.0, không phủ nhận những kết quả frontend V1.2 được xác nhận sau đó.

## V2.1 — AppShell composables (2026-10-14)

`src/components/AppShell.vue` được tách **logic** thành 4 composable trong `src/components/app-shell/`, giữ nguyên cấu trúc template và stylesheet nguồn. `FE-V2-START-2026-10-14` vẫn bất biến. **Không xem kết quả kiểm tra tĩnh là nghiệm thu runtime**; xem [16](16_FRONTEND_V2_1_APPSHELL_REFACTOR.md) để chạy Unit/Playwright/build trước khi merge.


## Frontend V2.3 — People / Employees (14/10/2026)

V2.3 tách các quy tắc form/payload/manager validation và các nguồn lookup/assignment khỏi `PeopleEmployeesView.vue`, giữ nguyên template và CSS từ bản V2.2.1. Lệnh `check:v2-3-employees` khóa hash DOM/CSS và các AST được di chuyển; xem [18](18_FRONTEND_V2_3_EMPLOYEES_REFACTOR.md). **Chưa có log chạy Windows của V2.3; không coi 243 E2E PASS của V2.2.1 là kết quả nghiệm thu V2.3.**

## Frontend V2.4 — Security Policies / PBAC (14/10/2026)

Security Policies view is reorganized into `policy-editor/` modules (forms/payload/validation/reference lookups/participants/State) without modifying DOM, scoped CSS, API contract, backend or authorization decision rules. Source checks **6/6** and cross-phase Node checks **21/21** passed. Full V2.4 Windows type-check/Vitest/Playwright/build are **pending**; see [19](19_FRONTEND_V2_4_POLICIES_PBAC_REFACTOR.md).
