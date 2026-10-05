---
document_id: "PLAN-MINI-ERP-P1-001"
title: "P1 Implementation Plan - Dinktech Mini ERP"
version: "1.1"
status: "draft"
owner: "Product / Architecture / Engineering"
date: "2026-10-05"
scope: "P1 - Foundation, Master Data & Authorization"
source_baselines:
  user_requirements: "00_User_Requirements_v1.0.11.md"
  functional_requirements: "01_P1_Functional_Requirements_Specification_v1.6.md"
  database_design: "02_P1_Database_Design_v1.9.md"
  implementation_contract: "03_P1_Implementation_Contract_v1.4.md"
downstream_design_artifacts:
  api_design: "05_P1_API_Design_v1.0.md"
  ui_design: "06_P1_UI_Design_v1.0.md"
---

# P1 Implementation Plan - Dinktech Mini ERP

## 1. Mục đích

Tài liệu này chuyển bộ baseline P1 đã được phê duyệt thành kế hoạch triển khai có thể giao trực tiếp cho Engineering.

Tài liệu này dùng để:

- xác định thứ tự triển khai và dependency giữa các workstream;
- khóa environment binding và quality gate của backend/frontend;
- chia P1 thành các delivery gate có thể nghiệm thu độc lập;
- tạo backlog kỹ thuật có Task ID, source traceability, dependency, output và acceptance gate;
- bảo đảm implementation không tự thay đổi business semantics, schema invariant hoặc wire/runtime contract đã baseline;
- cung cấp Definition of Ready, Definition of Done và release gate cho P1.

Tài liệu này **không thay thế** bốn baseline authority. Khi có xung đột:

```text
Business intent / scope       -> 00_User_Requirements_v1.0.11.md
Functional semantics          -> 01_P1_Functional_Requirements_Specification_v1.6.md
Persistence/schema invariant  -> 02_P1_Database_Design_v1.9.md
Wire/runtime convention       -> 03_P1_Implementation_Contract_v1.4.md
Execution sequencing/backlog  -> 04_P1_Implementation_Plan_v1.1.md
```

Implementation Plan không được tạo capability, persistence hoặc authorization semantics mới trái các baseline trên.


### 1.1 Revision v1.1 - Rolling vertical-slice delivery

Revision v1.1 thay đổi **cách tổ chức delivery**, không thay đổi business/functional/schema/runtime baseline:

- API contract và UI design được thiết kế theo từng vertical slice thay vì chờ toàn bộ backend hoàn tất.
- Mỗi slice đi theo chuỗi `schema/service -> API contract -> API implementation -> UI -> automated test -> VERIFIED`.
- `05_P1_API_Design_v1.0.md` và `06_P1_UI_Design_v1.0.md` là downstream implementation design; hai tài liệu này không được override 4 baseline authority.
- Endpoint/screen chưa đủ source authority phải mang trạng thái `CONTRACT_PENDING`/`DESIGN_PENDING`; Dev không tự đoán.
- Gate nền tảng vẫn giữ dependency kỹ thuật bắt buộc, nhưng API/UI của slice đã sẵn sàng có thể hoàn thiện ngay mà không đợi toàn P1.

---

# 2. Baseline và change control

## 2.1 Baseline authority

P1 implementation phải dùng đúng các revision:

| Artifact | Version | Status |
|---|---:|---|
| User Requirements | 1.0.11 | baseline |
| Functional Requirements Specification | 1.6 | baseline |
| Database Design | 1.9 | baseline |
| Implementation Contract | 1.4 | baseline |

Mọi Task trong plan này phải trace được tới một hoặc nhiều source requirement/design tương ứng.

## 2.2 Change control

Nếu trong quá trình code phát hiện cần thay đổi một trong các nội dung sau thì Task phải chuyển `BLOCKED` và tạo change request trước khi tiếp tục:

- nội dung/Acceptance Criteria của một `UR-*` đã baseline;
- functional semantics của `FRS-P1-*`;
- table/column/FK/index/unique/invariant của DBD;
- Authorization pipeline, QT1, denied-set intersection hoặc `ABSOLUTE_DENY`;
- Evaluation Organization/Time semantics;
- Resource Type hoặc canonical Action code;
- Auto Numbering schema/pattern/scope/reset/generation/immutability/concurrency semantics;
- Product Import target/template/key/mode/null semantics;
- HTTP envelope/status/error code đã public;
- persistence mới ngoài Database Design v1.9.

Không được sửa migration đã apply để “khớp code”; phải dùng forward-only migration.

---

# 3. Environment binding

## 3.1 Project paths

```yaml
environment_binding:
  project_root_windows: 'D:\XAMPP\htdocs\erp'
  docs_root_relative: 'docs/'
  backend_root_relative: 'backend/'
  frontend_root_relative: 'frontend/'
  backend_url: 'http://api.monmin.net'
  frontend_url: 'http://erp.monmin.net'
  backend_runtime: 'XAMPP/Apache + CodeIgniter already running'
  php_runtime: '8.3.x'
  database_engine: 'MariaDB 10.x'
  phpstan_level: 8
```

Canonical target paths:

```text
D:\XAMPP\htdocs\erp\
├── docs\
├── backend\
└── frontend\
```

Tài liệu này phải được lưu trong repository tại:

```text
D:\XAMPP\htdocs\erp\docs\04_P1_Implementation_Plan_v1.1.md
```

## 3.2 Backend platform

```text
PHP runtime/source target: 8.3.x
CodeIgniter 4.7.x
CodeIgniter Shield
CodeIgniter Settings
MariaDB 10.4.32
PHPUnit
PHPStan
CodeIgniter PHPStan extension
PhpSpreadsheet
Smalot PdfParser
```

Backend quality requirement:

- PHP source phải tương thích PHP 8.3.x.
- PHPStan **level 8** là gate bắt buộc.
- PHPUnit là test runner canonical.
- CodeIgniter Shield là identity/authentication boundary.
- CodeIgniter Settings là settings integration boundary đã được baseline sử dụng cho business timezone/configuration phù hợp.
- MariaDB 10.4.32 là database target phải được test cho migration, row lock và advisory lock behavior.
- PhpSpreadsheet dùng cho XLSX import.
- Smalot PdfParser đã có trong platform nhưng **không tạo capability PDF P1**; chỉ dùng nếu requirement sau này được phê duyệt.

## 3.3 Frontend platform

### Runtime dependencies

```text
@vueuse/core@14.4.0
axios@1.20.0
bootstrap@5.3.8
chart.js@4.5.1
dayjs@1.11.23
naive-ui@2.45.3
pinia@4.0.3
vue@3.5.42
vue-chartjs@5.3.4
vue-i18n@11.4.12
vue-router@5.3.1
zod@4.6.5
```

### Development dependencies

```text
@axe-core/playwright@4.13.0
@hey-api/openapi-ts@0.99.0
@playwright/test@1.63.0
@redocly/cli@2.53.0
@tsconfig/node24@24.0.5
@types/jsdom@30.0.0
@types/node@24.13.4
@vitejs/plugin-vue@6.0.9
@vitest/eslint-plugin@1.6.27
@vue/eslint-config-typescript@14.9.0
@vue/test-utils@2.5.0
@vue/tsconfig@0.9.1
eslint@10.10.0
eslint-config-prettier@10.1.8
eslint-plugin-oxlint@1.82.0
eslint-plugin-playwright@2.12.0
eslint-plugin-vue@10.11.1
jiti@2.7.0
jsdom@30.1.1
npm-run-all2@9.0.3
oxlint@1.82.0
prettier@3.9.6
typescript@6.0.3
vite@8.3.0
vite-plugin-vue-devtools@8.2.1
vitest@4.1.11
vue-eslint-parser@10.4.1
vue-tsc@3.3.11
```

Frontend quality requirement:

- TypeScript type-check phải pass bằng `vue-tsc`.
- ESLint/Oxlint phải pass.
- Prettier check phải pass.
- Vitest dùng cho unit/component test.
- Playwright dùng cho critical browser flow/E2E.
- `@axe-core/playwright` dùng cho accessibility smoke test trên critical flow.
- Production build phải pass trước merge/release candidate.

## 3.4 Dependency policy

Không tự thêm framework/library mới chỉ để giải quyết một implementation detail nếu stack hiện tại có thể đáp ứng.

Nếu backend cần decimal arithmetic ngoài khả năng an toàn hiện có:

1. trước hết kiểm tra PHP extension/library đã có trong runtime;
2. không dùng binary float cho authoritative money/quantity calculation;
3. nếu cần thêm extension/library thì ghi technical decision/change trong Task trước khi thêm dependency.

---

# 4. Engineering conventions của plan

Các convention dưới đây là **implementation-plan convention**; chúng không được thay đổi upstream semantics.

## 4.1 Backend layer boundary

Target logical separation:

```text
HTTP Controller / API Adapter
        |
Application / Domain Service
        |
Repository / Persistence Adapter
        |
MariaDB
```

Responsibility:

- Controller: parse request, invoke use-case, map response; không reimplement invariant.
- Service: domain invariant, authorization placement, transaction orchestration.
- Repository: query/persistence/row-lock primitive/DB error translation; không chứa PBAC precedence.
- Integration adapter: Shield, Settings, Clock, DomainLock, file parser.

Nếu repository hiện tại đã có cấu trúc tương đương, dùng cấu trúc hiện hữu; **không tạo kiến trúc song song** chỉ để khớp tên folder trong plan.

## 4.2 Frontend layer boundary

Target logical separation:

```text
Vue page/view
   |
feature component/form
   |
Pinia/use-case composable
   |
typed API client
   |
backend /api/v1
```

Rules:

- business authorization không được implement lại ở frontend;
- UI có thể hide/disable action theo capability response nhưng backend vẫn là authority;
- form validation bằng Zod/Vue rules chỉ là UX validation; backend validation vẫn bắt buộc;
- generated API artifacts không được hand-edit.

## 4.3 API-first integration convention

Plan chọn API-first workflow để giảm drift backend/frontend:

1. API tuân `/api/v1`, JSON `snake_case`, BIGINT string, DECIMAL string theo Implementation Contract.
2. OpenAPI specification được duy trì như integration artifact của API implementation.
3. `@redocly/cli` lint spec.
4. `@hey-api/openapi-ts` sinh typed API artifact cho frontend.
5. Generated files không được chỉnh tay.
6. Frontend dùng generated type/client contract kết hợp transport wrapper hiện hành.
7. OpenAPI không được định nghĩa semantics trái FRS/DBD/Implementation Contract.

Exact generator configuration/file layout được khóa trong Task `P1-API-002` sau khi kiểm tra repository hiện tại.

## 4.4 Time, decimal và IDs

- Một operation dùng một Evaluation Time.
- DATETIME persistence theo UTC.
- Company business DATE derive theo Company timezone từ Settings.
- BIGINT qua JSON là string.
- DECIMAL/money/quantity/factor qua JSON là decimal string.
- Không dùng PHP/JavaScript binary float làm authoritative money calculation.

## 4.5 Security invariant

Mọi protected use-case phải đi qua cùng AuthorizationService.

Không được có:

```text
isSystemAdmin => ALLOW
```

Không có Resource Instance Authorization trong P1.

Không Base Permission => DENY trước PBAC.

---

# 5. Quality gates

## 5.1 Backend gate

Mọi backend change trước khi `DONE` phải thỏa khi applicable:

- PHPUnit pass.
- PHPStan level 8 pass.
- Migration test pass nếu thay schema.
- Integration test pass nếu thay repository/transaction/locking.
- Authorization negative-path test pass nếu endpoint protected.
- Không có warning/error mới bị bỏ qua bằng suppression không được review.
- Không log secret/token/password.
- Không dùng float cho authoritative decimal calculation.

## 5.2 Frontend gate

Mọi frontend change trước khi `DONE` phải thỏa khi applicable:

- TypeScript / `vue-tsc` pass.
- ESLint/Oxlint pass.
- Prettier check pass.
- Vitest pass.
- production build pass.
- Playwright pass cho critical flow liên quan.
- accessibility smoke pass cho critical forms/navigation thay đổi.

## 5.3 Database gate

- Fresh database migration pass.
- Forward migration trên snapshot schema hiện hữu pass khi áp dụng.
- Seed chạy lại không tạo duplicate.
- PK/FK/unique/index được assert.
- Race-sensitive invariant có concurrency test.
- Không rewrite migration đã apply.

## 5.4 API contract gate

- Envelope đúng contract.
- HTTP status đúng contract.
- BIGINT/DECIMAL serialization đúng contract.
- Request ID propagate.
- 404 invisible target và 403 visible-but-denied đúng contract.
- OpenAPI lint pass.
- Generated frontend API artifacts được regenerate khi contract thay đổi.

---

# 6. Task lifecycle

Canonical task status:

```text
TODO
READY
IN_PROGRESS
BLOCKED
REVIEW
DONE
```

Một Task chỉ được chuyển `READY` khi đạt Definition of Ready.

## 6.1 Definition of Ready

Task phải có:

- Task ID + title.
- Source traceability.
- Dependency đã DONE hoặc được xác định không block.
- Scope và out-of-scope rõ.
- DB impact rõ.
- Authorization impact rõ.
- Transaction/concurrency impact rõ.
- Acceptance test tối thiểu.
- Không có unresolved baseline conflict.

## 6.2 Definition of Done cho từng Task

- code/review hoàn tất;
- automated test phù hợp đã thêm và pass;
- static analysis/lint/type-check pass;
- migration/seed idempotency được test nếu applicable;
- OpenAPI/generated client cập nhật nếu public API thay đổi;
- không thêm persistence/capability ngoài baseline;
- tài liệu/task traceability cập nhật;
- không còn TODO kỹ thuật làm thay đổi correctness của task.

---

# 7. Rolling delivery model P1

## 7.1 Nguyên tắc cuốn chiếu

Sau Gate 0/1, P1 triển khai theo **vertical slice**. Một slice chỉ được coi là hoàn thiện khi backend + API + UI + automated test của chính slice đó đã đi hết vòng.

Canonical flow:

```text
Schema prerequisite
-> Domain/Application Service
-> API Contract READY
-> API Implementation
-> UI Design READY
-> UI Implementation
-> Unit/Integration/API/UI/E2E tests
-> Slice VERIFIED
```

Không chờ toàn bộ Service layer của P1 xong mới bắt đầu API/UI.

API Design và UI Design dùng trạng thái:

```text
PLANNED
CONTRACT_READY / DESIGN_READY
IMPLEMENTED
VERIFIED
BLOCKED
```

## Gate 0 - Repository & Toolchain Readiness

Mục tiêu: xác nhận codebase/toolchain thật.

Exit criteria:

- environment binding được kiểm chứng;
- backend PHPUnit/PHPStan level 8 chạy được;
- frontend lint/typecheck/test/build chạy được;
- test DB strategy được xác định;
- Shield/Settings/Clock/DomainLock/decimal mechanism được kiểm tra;
- vị trí OpenAPI source/generated client được xác định.

## Gate 1 - Schema, Migration & Bootstrap Foundation

Mục tiêu: migration/seed prerequisite đủ để mở các vertical slice.

Exit criteria:

- migration dependency bắt buộc theo DBD;
- fresh + forward migration pass cho phần đã triển khai;
- Resource/Action/Workflow seed idempotent;
- privileged bootstrap không bypass;
- GROUP root bootstrap;
- schema prerequisite của slice kế tiếp đã sẵn sàng.

Gate 1 có thể được mở rộng theo migration batch; không bắt buộc tạo mọi table P1 trước khi bắt đầu Slice 1 nếu dependency slice đã hoàn tất và forward migration order vẫn đúng.

## Slice 1 - Foundation / Organization / Contact

Scope:

```text
Province / Ward
Organization
Contact
Organization tree/context
Company resolution
```

Done when:

- schema/service pass;
- API endpoints của Slice 1 VERIFIED;
- UI list/form/tree/contact UX VERIFIED;
- authorization/list scope/error contract pass;
- E2E critical flow pass.

## Slice 2 - Employee Structure

Scope:

```text
Department
Position
Employee
Employee Assignment
Manager invariant
Default/Evaluation context support
```

Done when backend + API + UI + tests của Slice 2 VERIFIED.

## Slice 3 - Identity / Authorization / Workflow

Scope:

```text
Shield integration
Resource/Action
Authorization Group
Direct User Permission
Policy
Authorization Log
Lifecycle/Workflow
```

AuthorizationService là dependency critical; slice này phải đạt mandatory authorization regression trước khi slice business sau được coi là release-ready.

## Slice 4 - Auto Numbering

Scope:

```text
Numbering series
Pattern validation
Scope/period resolution
Counter allocator
Product identifier adapter
```

Done when concurrency/rollback/no-reuse tests pass và UI configuration được verify.

## Slice 5 - Product / UOM

Scope:

```text
Product Group
Product UOM
Product Master
Role derivation
Alternate UOM
Parent/Child
```

Product API/UI có thể ship slice sau khi Auto Number Product adapter đã contract-ready.

## Slice 6 - Pricing / COMBO

Scope:

```text
Price List
Price List Item
Price Resolution
COMBO composition
COMBO expansion/allocation
```

Done when decimal/rounding/nested COMBO regression pass và UI editor/preview flow verified.

## Slice 7 - Product Import

Scope:

```text
Import Job
CSV/XLSX
Product source-key dependency
Auto Number integration
two-gate authorization
row result/report
```

Done when import API/UI/E2E + reversed-dependency + rollback tests pass.

## Gate 9 - System Verification & Release Candidate

Mục tiêu: cross-slice verification.

Exit criteria:

- tất cả slice mục tiêu P1 `VERIFIED`;
- FRS Acceptance Test Catalog pass;
- Implementation Contract mandatory tests pass;
- fresh/forward migration rehearsal pass;
- OpenAPI/frontend contract drift check pass;
- security regression pass;
- open production decisions disposition rõ;
- release evidence pack hoàn chỉnh.

## 7.2 Slice Definition of Done

Một vertical slice chỉ `VERIFIED` khi:

```text
[ ] schema prerequisite DONE
[ ] service/domain invariant tests pass
[ ] API contract status CONTRACT_READY
[ ] API implementation + contract tests pass
[ ] OpenAPI updated/linted
[ ] generated frontend types/client refreshed
[ ] UI design status DESIGN_READY
[ ] UI implementation pass type/lint/unit/build
[ ] Playwright critical flow pass
[ ] accessibility smoke pass
[ ] authorization negative paths pass
[ ] no baseline drift
```

## 7.3 Dependency giữa slices

```text
Gate 0
  -> Gate 1 foundation

Slice 1 Organization
  -> Slice 2 Employee
      -> Slice 3 Authorization/Workflow
          -> Slice 4 Auto Numbering
              -> Slice 5 Product/UOM
                  -> Slice 6 Pricing/COMBO
                      -> Slice 7 Import
                          -> Gate 9 RC
```

Có thể parallel hóa component không phụ thuộc trực tiếp, nhưng **không được declare slice VERIFIED khi dependency invariant chưa VERIFIED**.

---

# 8. Backlog tổng thể

## 8.1 Gate 0 backlog

| Task ID | Title | Depends on | Primary output |
|---|---|---|---|
| P1-ENV-001 | Repository & runtime inventory | none | verified repository/runtime map |
| P1-ENV-002 | Backend quality command baseline | P1-ENV-001 | repeatable PHPUnit/PHPStan gate |
| P1-ENV-003 | Frontend quality command baseline | P1-ENV-001 | repeatable lint/type/test/build gate |
| P1-ENV-004 | Test database and migration harness | P1-ENV-001 | isolated DB test workflow |
| P1-INF-001 | Clock + Evaluation Time adapter | P1-ENV-001 | single-time-provider abstraction |
| P1-INF-002 | Request/Correlation ID middleware | P1-ENV-001 | request_id propagation |
| P1-INF-003 | Settings/BusinessTimezone resolver | P1-ENV-001 | Company/GROUP timezone integration |
| P1-INF-004 | DomainLockService MariaDB adapter | P1-ENV-004 | namespaced advisory-lock abstraction |
| P1-INF-005 | Decimal arithmetic readiness decision | P1-ENV-001 | approved safe decimal mechanism |

### P1-ENV-001 - Repository & runtime inventory

**Source**

- Implementation Contract v1.4 sections 3, 9, 15, 20.
- Environment binding của Implementation Plan.

**Scope**

- xác nhận actual backend/frontend folder layout;
- xác nhận CI4/Shield/Settings versions đang chạy;
- xác nhận MariaDB connection/version;
- inventory migration/seed hiện hữu;
- inventory legacy authorization schema;
- inventory composer/npm scripts hiện hữu;
- ghi nhận PHP extensions cần cho decimal/file handling.

**Out of scope**

- không sửa business schema;
- không refactor architecture.

**Acceptance**

- có repository map được commit vào task evidence;
- xác định nơi đặt migration/seed/test/API spec/generated frontend client;
- không tạo duplicate framework structure.

### P1-INF-005 - Decimal arithmetic readiness decision

**Source**

- FRS-P1-GEN-007.
- DBD monetary precision.
- Implementation Contract pricing/decimal rule.

**Acceptance**

- chứng minh authoritative money/quantity calculation không dùng binary float;
- nếu stack hiện tại thiếu safe mechanism, tạo technical decision trước khi thêm dependency/extension;
- có unit test cho precision/rounding dùng bởi Pricing/ComboAllocation.

---

## 8.2 Gate 1 backlog - Schema, migrations, bootstrap

| Task ID | Title | Depends on |
|---|---|---|
| P1-DB-001 | Migration inventory & forward-only plan | P1-ENV-004 |
| P1-DB-010 | Foundation location migrations | P1-DB-001 |
| P1-DB-020 | Organization + Contact migrations | P1-DB-010 |
| P1-DB-030 | Department/Position/Employee/Assignment migrations | P1-DB-020 |
| P1-DB-040 | Workflow/Lifecycle migrations | P1-DB-001 |
| P1-DB-050 | Resource/Auth/Auto Numbering migrations | P1-DB-030, P1-DB-040 |
| P1-DB-060 | Product/UOM/Combo migrations | P1-DB-050 |
| P1-DB-070 | Pricing migrations | P1-DB-060 |
| P1-DB-080 | Import migrations | P1-DB-060 |
| P1-SEED-001 | Resource + Action seed | P1-DB-050 |
| P1-SEED-002 | Workflow state/lifecycle bootstrap seed | P1-DB-040 |
| P1-SEED-003 | Privileged Authorization Group bootstrap | P1-SEED-001 |
| P1-SEED-004 | GROUP root bootstrap | P1-DB-020 |
| P1-DB-090 | Fresh + forward migration verification | P1-DB-010..P1-DB-080, P1-SEED-001..P1-SEED-004 |

### P1-DB-050 - Resource/Auth/Auto Numbering migrations

**Source**

- UR-IAM-*; UR-COM-006.
- FRS-P1-IAM-*, FRS-P1-PBAC-*, FRS-P1-NUM-*.
- DBD sections 11, 13, Auto Numbering.
- Implementation Contract sections 6-9.

**Must include**

```text
resources
resource_actions
auto_numbering_series
auto_numbering_counters
groups
group_permissions
policies
policy_actions
authorization_logs
```

**Acceptance**

- `resources.type` unique;
- `resource_actions(resource_id, code)` unique;
- `auto_numbering_series(resource_id, series_code)` unique;
- counter key unique:
  `numbering_series_id + scope_organization_id + period_key`;
- Policy state nullable;
- no profiles table;
- no permission table riêng;
- no resource-instance table.

### P1-SEED-001 - Resource + Action seed

Seed phải idempotent.

P1 minimum includes:

```text
product.import
authorization_group.assign_member
authorization_group.assign_permission
user.assign_permission
resource.configure_numbering
```

Không seed `employee.import`, `province.import`, `ward.import`.

### P1-SEED-003 - Privileged Authorization Group bootstrap

**Acceptance**

- có canonical privileged Authorization Group, ví dụ `SYSTEM_ADMIN`;
- initial membership provision bằng CLI/deployment/bootstrap path;
- không có runtime `isSystemAdmin => ALLOW`;
- sau bootstrap membership/permission mutation đi qua normal PBAC;
- không cấp full permission cho mọi User.

---

# 9. Gate 2 backlog - Organization, Contact, Employee

| Task ID | Title | Depends on |
|---|---|---|
| P1-ORG-001 | OrganizationRepository | P1-DB-020 |
| P1-ORG-002 | OrganizationService | P1-ORG-001, P1-INF-004 |
| P1-CON-001 | ContactRepository/Service | P1-DB-020 |
| P1-EMP-001 | EmployeeRepository + manager invariant | P1-DB-030, P1-INF-004 |
| P1-EMP-002 | EmployeeAssignmentRepository | P1-DB-030 |
| P1-EMP-003 | EmployeeAssignmentService | P1-EMP-002, P1-ORG-002, P1-INF-003, P1-INF-004 |
| P1-CTX-001 | Authoritative Evaluation Organization resolver | P1-ORG-002, P1-EMP-003 |
| P1-CTX-002 | Company owner resolver | P1-ORG-002 |

## P1-ORG-002 - OrganizationService

**Trace**

- UR-ORG-001..003.
- FRS-P1-ORG-001..005.
- FRS-P1-SVC-001.
- DBD Organization Domain.
- IMP authoritative context + lock matrix.

**Scope**

- create/update/inactivate;
- parent matrix;
- cycle detection;
- one GROUP root;
- ancestor chain;
- Company ancestor resolution.

**Tests**

- second GROUP reject;
- valid hierarchy;
- same-type reject;
- cycle reject;
- re-parent concurrency;
- Company resolution from Warehouse.

## P1-EMP-003 - EmployeeAssignmentService

**Trace**

- UR-EMP-003.
- FRS-P1-EMP-004..005.
- FRS-P1-SVC-002.

**Tests**

- date range;
- Primary overlap;
- Primary default context;
- Secondary Assignment eligibility;
- GROUP Assignment inheritance eligibility;
- sibling isolation;
- Company partition;
- concurrent overlapping Primary Assignment cannot both commit.

---

# 10. Gate 3 backlog - Identity, Authorization, Workflow

| Task ID | Title | Depends on |
|---|---|---|
| P1-IAM-001 | Shield identity/group/direct-permission adapter | P1-SEED-001 |
| P1-IAM-002 | EffectiveProfile resolver | P1-IAM-001 |
| P1-AUTH-001 | PolicyRepository + participant validation | P1-DB-050 |
| P1-AUTH-002 | AuthorizationService core | P1-IAM-002, P1-AUTH-001, P1-EMP-003, P1-CTX-001 |
| P1-AUTH-003 | Authorization decision evidence/reason codes | P1-AUTH-002 |
| P1-AUTH-004 | Authorization audit persistence | P1-AUTH-002 |
| P1-AUTH-005 | Security administration services | P1-AUTH-002 |
| P1-WF-001 | LifecycleRepository | P1-DB-040 |
| P1-WF-002 | LifecycleService | P1-WF-001, P1-AUTH-002 |
| P1-AUTH-090 | Mandatory authorization regression suite | P1-AUTH-002..P1-AUTH-005 |

## P1-AUTH-002 - AuthorizationService core

**Canonical order**

```text
Base Permission
-> authoritative Evaluation Organization
-> ScopePath(GROUP -> O)
-> actor lane
-> eligible assignment lanes
-> QT1 per lane
-> propagate ABSOLUTE_DENY
-> denied-set intersection
-> ALLOW / DENY
```

**Must not implement**

- System Admin bypass;
- Resource Instance Authorization;
- Company-summary as substitute for concrete Organization authorization.

**Mandatory tests**

- No Base Permission => DENY.
- Base + NO_MATCH => ALLOW.
- actor NO_MATCH + assignment DENY => effective ALLOW.
- role ALLOW + role DENY => effective ALLOW.
- role DENY + role DENY => DENY.
- inherited ABSOLUTE_DENY survives intersection.
- GROUP Assignment inheritance.
- Secondary Assignment eligibility.
- sibling isolation.
- Company partition.
- System Admin without Base => DENY.
- System Admin + ABSOLUTE_DENY => DENY.
- Group-level security Resource evaluates at GROUP.

## P1-WF-002 - LifecycleService

**Tests**

- one initial state;
- state membership;
- deterministic from/action destination;
- invalid transition + Authorization ALLOW => reject;
- valid transition + Authorization DENY => reject.

---

# 11. Gate 4 backlog - Auto Numbering

| Task ID | Title | Depends on |
|---|---|---|
| P1-NUM-001 | Numbering series repository/config service | P1-DB-050, P1-AUTH-002, P1-INF-003 |
| P1-NUM-002 | Pattern parser/validator | P1-NUM-001 |
| P1-NUM-003 | Scope + period resolver | P1-NUM-001, P1-CTX-001, P1-INF-003 |
| P1-NUM-004 | Counter allocator + transaction locking | P1-NUM-002, P1-NUM-003, P1-INF-004 |
| P1-NUM-005 | Resource identifier adapter contract | P1-NUM-004 |
| P1-NUM-006 | Product identifier adapter | P1-NUM-005, P1-DB-060 |
| P1-NUM-090 | Auto Number concurrency/regression suite | P1-NUM-001..P1-NUM-006 |

## P1-NUM-001 - Numbering configuration service

**Must enforce**

- zero/multiple series;
- max one ACTIVE default per Resource;
- post-issuance immutable:
  - `resource_id`
  - `series_code`
  - `scope_type`
  - `reset_policy`;
- issued series no hard-delete;
- permission `resource.configure_numbering`.

## P1-NUM-002 - Pattern validator

Canonical tokens:

```text
{SEQ:n}
{YYYY}
{YY}
{MM}
{DD}
{COMPANY_CODE}
{ORG_CODE}
{RESOURCE}
```

Must validate uniqueness namespace compatibility.

## P1-NUM-004 - Counter allocator

**Concurrency contract**

- existing row: `FOR UPDATE`;
- first counter: namespaced DomainLock + unique guard;
- allocation/business create in same DB transaction;
- occupied candidate => advance sequence under same serialization;
- rollback => counter rollback;
- committed number => never reused by business logic.

## P1-NUM-006 - Product identifier adapter

Canonical:

```text
identifier_field            = products.code
transport_import_alias      = product_code
identifier_max_length       = 50
identifier_uniqueness_scope = GLOBAL
```

---

# 12. Gate 5 backlog - Product, UOM, Pricing, COMBO

| Task ID | Title | Depends on |
|---|---|---|
| P1-PRD-001 | ProductGroup repository/service | P1-DB-060 |
| P1-PRD-002 | ProductUom master repository/service | P1-DB-060 |
| P1-PRD-003 | ProductRepository/master mutation service | P1-DB-060, P1-NUM-006 |
| P1-PRD-004 | ProductRoleService | P1-PRD-003 |
| P1-UOM-001 | ProductUomService | P1-PRD-002, P1-PRD-004 |
| P1-PRC-001 | PriceList repository/service | P1-DB-070 |
| P1-PRC-002 | PricingService | P1-PRC-001, P1-UOM-001, P1-INF-001 |
| P1-CMB-001 | Combo composition mutation service | P1-PRD-004, P1-INF-004 |
| P1-CMB-002 | ComboExpansionService | P1-CMB-001 |
| P1-CMB-003 | ComboAllocationService | P1-CMB-002, P1-PRC-002, P1-INF-005 |
| P1-PRD-090 | Product/UOM/Pricing/Combo regression suite | P1-PRD-*, P1-UOM-*, P1-PRC-*, P1-CMB-* |

## P1-PRD-004 - ProductRoleService

Must centralize:

```text
getRole()
isParent()
isChild()
isSimple()
isCombo()
isSelectable()
isLeafTransactionable()
requiresComboExpansion()
getFullName()
```

Không module nào được tự derive role theo rule khác.

## P1-UOM-001 - ProductUomService

Tests:

- Base UOM required cho SIMPLE/CHILD/COMBO.
- Alternate UOM direct-to-base.
- factor > 0.
- SALES/BOTH price required.
- PURCHASE price nullable.
- COMBO reject UOM conversion.

## P1-PRC-002 - PricingService

Canonical resolution:

```text
Product + UOM + Company + Effective Time

1. Company Price List
2. Group Price List
3. Default price đúng UOM
```

Deterministic candidate:

```text
priority DESC
effective_from DESC
price_list_id ASC
```

## P1-CMB-002 - ComboExpansionService

Tests:

- SIMPLE/CHILD component valid;
- nested COMBO valid;
- PARENT invalid;
- self reference invalid;
- cycle/deep cycle reject;
- nested quantity multiplication;
- leaf aggregation.

## P1-CMB-003 - ComboAllocationService

Tests:

- weight = leaf quantity × leaf default Base-UOM price;
- nested COMBO price ignored;
- total weight <= 0 reject;
- sum allocated = root total;
- deterministic residual;
- same leaf across COMBOs aggregate amount/quantity;
- weighted average final unit price;
- no float precision loss.

---

# 13. Gate 6 backlog - Product Import

| Task ID | Title | Depends on |
|---|---|---|
| P1-IMP-001 | ImportJob repository/service skeleton | P1-DB-080 |
| P1-IMP-002 | CSV parser | P1-IMP-001 |
| P1-IMP-003 | XLSX parser | P1-IMP-001 |
| P1-IMP-004 | Header/null/mode validation | P1-IMP-002, P1-IMP-003 |
| P1-IMP-005 | Product import model + source-key graph | P1-IMP-004, P1-PRD-004, P1-UOM-001, P1-CMB-001 |
| P1-IMP-006 | Product row mutation + Auto Number | P1-IMP-005, P1-NUM-006 |
| P1-IMP-007 | Two-gate import authorization | P1-IMP-006, P1-AUTH-002 |
| P1-IMP-008 | Row result/job reconciliation | P1-IMP-006 |
| P1-IMP-090 | Import regression suite | P1-IMP-001..P1-IMP-008 |

## P1-IMP-004 - Parser validation

Must enforce:

- CSV UTF-8/BOM;
- comma delimiter;
- header required;
- XLSX first nonempty sheet;
- formula cell reject;
- unknown header => `IMPORT_INVALID_TEMPLATE`;
- `CREATE_ONLY | UPDATE_ONLY | UPSERT`;
- blank vs `__NULL__`.

## P1-IMP-005 - Product source identity/dependency graph

Canonical terms:

```text
Persistent Target Key       = product_code -> products.code
Source-local Key            = source_key
Generated Business Identifier = product_code generated into products.code
```

Must support:

- Parent/Child dependency by source key;
- UOM Conversion owner by source key;
- COMBO owner/component by source key;
- Child before Parent;
- COMBO item before component;
- nested COMBO cycle detection.

## P1-IMP-007 - Authorization

```text
Gate 1: product.import at GROUP
Gate 2: each logical mutation requires product.create/edit/setstate as applicable
```

Import không phải authorization bypass.

---

# 14. Gate 7 backlog - HTTP API & OpenAPI

| Task ID | Title | Depends on |
|---|---|---|
| P1-API-001 | Common API response/error/request-id infrastructure | P1-INF-002, P1-AUTH-002 |
| P1-API-002 | OpenAPI source + lint + frontend generation pipeline | P1-API-001, P1-ENV-003 |
| P1-API-003 | Common list/filter/sort/pagination adapter | P1-API-001 |
| P1-API-010 | Foundation/Organization/Contact endpoints | Gate 2 |
| P1-API-020 | Employee/Assignment endpoints | Gate 2 |
| P1-API-030 | Resource/Group/Policy/Lifecycle endpoints | Gate 3 |
| P1-API-040 | Auto Numbering endpoints | Gate 4 |
| P1-API-050 | Product/UOM/Pricing/Combo endpoints | Gate 5 |
| P1-API-060 | Product Import endpoints | Gate 6 |
| P1-API-090 | API contract regression suite | P1-API-001..P1-API-060 |

## P1-API-001 - Common transport

Must implement:

- `/api/v1`;
- JSON snake_case;
- BIGINT strings;
- decimal strings;
- success/error envelope;
- request ID;
- canonical HTTP status;
- 404 invisible target / 403 visible-but-denied;
- no stack trace/SQL/secret production response.

## P1-API-002 - OpenAPI pipeline

Plan convention:

- canonical API spec under repository-controlled docs/API location determined from actual repo;
- Redocly lint mandatory;
- typed frontend artifact generated by `@hey-api/openapi-ts`;
- generated files excluded from manual editing;
- codegen change included in same change as API contract update.

No route/DTO semantics may contradict FRS/Implementation Contract.

---

# 15. Gate 8 backlog - Frontend P1

| Task ID | Title | Depends on |
|---|---|---|
| P1-FE-001 | App shell/router/session/error boundary | P1-API-001, P1-API-002 |
| P1-FE-002 | Typed API integration + common query state | P1-API-002 |
| P1-FE-003 | Common table/filter/pagination/export components | P1-FE-002, P1-API-003 |
| P1-FE-010 | Location/Organization/Contact UI | P1-API-010 |
| P1-FE-020 | Employee/Assignment UI | P1-API-020 |
| P1-FE-030 | Authorization Group/Resource/Policy UI | P1-API-030 |
| P1-FE-040 | Workflow/Lifecycle UI | P1-API-030 |
| P1-FE-050 | Auto Numbering configuration UI | P1-API-040 |
| P1-FE-060 | Product/UOM UI | P1-API-050 |
| P1-FE-070 | Pricing/COMBO UI | P1-API-050 |
| P1-FE-080 | Product Import UI/report | P1-API-060 |
| P1-FE-090 | Accessibility + critical Playwright flows | P1-FE-010..P1-FE-080 |

## Frontend rules

- không hard-code permission thay backend;
- hidden/disabled actions chỉ là UX;
- all protected mutations vẫn rely backend 403/404;
- display BIGINT IDs as strings;
- do not parse decimal strings into binary float for authoritative calculation;
- Day.js display timezone không được thay business timezone authority;
- exact CHILD UI separator vẫn là open decision và phải resolve trước Product UI sign-off.

---

# 16. Gate 9 backlog - System Verification & Release Candidate

| Task ID | Title | Depends on |
|---|---|---|
| P1-SYS-001 | Full FRS Acceptance Test Catalog run | Gates 1-8 |
| P1-SYS-002 | Authorization/security regression run | Gate 3, Gate 7 |
| P1-SYS-003 | Concurrency/locking regression run | Gates 2,4,5,6 |
| P1-SYS-004 | Fresh migration rehearsal | Gate 1 |
| P1-SYS-005 | Existing-schema forward migration rehearsal | Gate 1 |
| P1-SYS-006 | OpenAPI/frontend contract drift check | Gates 7-8 |
| P1-SYS-007 | Production configuration review | Gates 0-8 |
| P1-UAT-001 | P1 business UAT package | P1-SYS-001..P1-SYS-007 |
| P1-RC-001 | Release candidate evidence pack | P1-UAT-001 |

## P1-RC-001 evidence tối thiểu

- baseline artifact versions;
- migration list + fresh/forward results;
- seed idempotency result;
- backend static analysis result;
- backend test result;
- frontend lint/type/test/build result;
- Playwright critical result;
- Authorization mandatory test result;
- Auto Number concurrency result;
- Import regression result;
- OpenAPI lint/codegen result;
- open production decision disposition;
- known non-blocking P2/P3 items.

---

# 17. Traceability matrix theo workstream

| Workstream | UR source | FRS source | DBD/IMP source | Task range |
|---|---|---|---|---|
| Organization | UR-CORE-001..002, UR-ORG-001..003 | FRS-P1-ORG-001..005, SVC-001 | DBD Organization; IMP context/locking | P1-ORG-*, P1-CTX-* |
| Contact | UR-CORE-005 | FRS-P1-CON-001..002 | DBD contacts | P1-CON-* |
| Employee | UR-EMP-001..003 | FRS-P1-EMP-001..005, SVC-002 | DBD Employee | P1-EMP-* |
| Identity | UR-IAM-001..004 | FRS-P1-IAM-001..004 | DBD Shield/Resource | P1-IAM-* |
| PBAC | UR-IAM-005..012 | FRS-P1-PBAC-001..007, SVC-003 | DBD PBAC; IMP Auth | P1-AUTH-* |
| Workflow | UR-COM-001..002, UR-IAM-008 | FRS-P1-WF-001..005, SVC-004 | DBD Lifecycle; IMP lifecycle | P1-WF-* |
| Auto Number | UR-COM-006 | FRS-P1-NUM-001..003, SVC-010 | DBD 11.5; IMP 8 | P1-NUM-* |
| Product/UOM | UR-PRD-001..004 | FRS-P1-PRD-*, UOM-*, SVC-005..006 | DBD Product/UOM | P1-PRD-*, P1-UOM-* |
| COMBO | UR-PRD-005..006 | FRS-P1-CMB-*, CMBPRC-*, SVC-008..009 | DBD Combo; IMP decimal | P1-CMB-* |
| Pricing | UR-PRC-001 | FRS-P1-PRC-*, SVC-007 | DBD Pricing; IMP 14 | P1-PRC-* |
| Import | UR-COM-005 | FRS-P1-IMP-001..005, SVC-011 | DBD Import; IMP 11-12 | P1-IMP-* |
| API/UI | common | GEN/QRY/SEC/AUD | IMP 3-5, 15-18 | P1-API-*, P1-FE-* |

Task implementation phải ghi exact source IDs trong ticket/PR description; bảng trên là mapping cấp workstream, không thay thế task-level traceability.

---

# 18. Critical path và parallelization

## 18.1 Critical path

```text
Environment readiness
-> migration/bootstrap prerequisite
-> Slice 1 Organization/Contact
-> Slice 2 Employee
-> Slice 3 Authorization/Workflow
-> Slice 4 Auto Numbering
-> Slice 5 Product/UOM
-> Slice 6 Pricing/COMBO
-> Slice 7 Product Import
-> System/UAT/RC
```

Trong mỗi slice, API và UI được hoàn thiện cuốn chiếu ngay sau service contract tương ứng; không có bước "đợi toàn bộ backend P1 xong mới làm frontend".

## 18.2 Có thể chạy song song

Sau khi schema tương ứng ổn định:

- Contact có thể chạy song song với Employee.
- Workflow có thể phát triển song song với Authorization core sau khi Resource/Action schema sẵn sàng.
- Auto Number pattern/config service có thể phát triển song song với Product master; Product adapter chỉ merge khi `products` sẵn sàng.
- Frontend shell/common component có thể bắt đầu khi API common contract + OpenAPI pipeline ổn định.
- UI từng module chỉ bắt đầu khi API/module service tương ứng đạt contract gate.

## 18.3 Không được chạy trước dependency

- Product Import không được hoàn tất trước AutoNumber + Product/UOM/COMBO invariant.
- Frontend không được trở thành nơi bù business rule backend còn thiếu.
- Authorization admin UI không được dùng endpoint chưa đi qua chính AuthorizationService.
- Production migration không chạy trước fresh/forward rehearsal.

---

# 19. Risk register P1

| Risk | Impact | Mitigation |
|---|---|---|
| Legacy schema khác DBD | migration lỗi/data loss | inventory + forward-only harmonization + rehearsal |
| System Admin bypass vô tình | critical security | mandatory negative tests; no special branch |
| PBAC intersection implement sai | privilege/denial sai | table-driven tests từ Acceptance Catalog |
| Client chọn favorable org context | authorization bypass | authoritative server-side context resolver |
| Advisory lock dùng khác DB connection | race/deadlock | DomainLock adapter + integration test |
| Auto Number first-counter race | duplicate identifier | advisory lock + unique key + row transaction |
| AUTO_IF_EMPTY manual collision | allocator kẹt/duplicate | occupied-candidate loop under lock |
| Timezone boundary | effective-date sai | single Evaluation Time + Settings resolver tests |
| Decimal via float | pricing/allocation sai | safe decimal gate before pricing work |
| Product Import dependency order | partial/invalid graph | source-key graph + topological/multi-pass |
| Import bypass PBAC | unauthorized mutation | two-gate authorization per row |
| API/frontend drift | runtime regression | OpenAPI lint + generated client |
| Legacy role participant data | policy mismatch | explicit ROLE -> GROUP verified migration |
| Retention chưa quyết định | production ops risk | resolve before production release, not schema invention |

---

# 20. Open decision register

Các item này **không được Dev tự suy diễn**.

| Decision ID | Item | Blocker for |
|---|---|---|
| DEC-P1-001 | Canonical `employees.gender` value catalog | Employee UI production sign-off |
| DEC-P1-002 | Direct Manager effective-dated history | Not P1 blocker; current pointer remains baseline |
| DEC-P1-003 | CHILD display separator | Product UI sign-off |
| DEC-P1-004 | Retention `authorization_logs`/`import_jobs` | Production Operations sign-off |
| DEC-P2-001 | COMBO transaction leaf trace persistence | P2 only |
| DEC-P2-002 | Direct leaf + COMBO-generated leaf aggregation | P2 only |

Nếu một decision làm thay đổi baseline P1 semantics/schema thì phải đi qua change control trước code.

---

# 21. Standard Dev Task template

Mọi task implementation nên dùng template:

```text
Task ID:
Title:
Status:
Owner role:

Source:
- UR:
- FRS:
- DBD:
- Implementation Contract:

Dependencies:

Scope:

Out of scope:

Implementation requirements:

Database impact:
- none / migration / query / index / lock

Authorization impact:
- Resource:
- Action:
- Evaluation Organization:
- PBAC considerations:

Transaction / concurrency impact:

API impact:
- none / internal / public
- OpenAPI update required: yes/no

Frontend impact:

Acceptance tests:

Negative tests:

Concurrency tests:

Definition of Done:

Evidence:
- test command/result
- migration result
- static analysis result
- API spec/codegen result
```

---

# 22. Pull Request / review checklist

Mỗi PR P1 phải trả lời được:

```text
[ ] Task ID là gì?
[ ] UR/FRS/DBD/IMP source nào?
[ ] Có thay đổi schema không?
[ ] Có thay đổi public API không?
[ ] Có thay đổi Resource/Action không?
[ ] Có authorization negative test không?
[ ] Evaluation Organization lấy từ đâu?
[ ] Có transaction/race condition không?
[ ] Có dùng float cho decimal domain không?
[ ] Có cần OpenAPI regenerate không?
[ ] Backend PHPUnit pass?
[ ] PHPStan level 8 pass?
[ ] Frontend type/lint/test/build pass nếu applicable?
[ ] Có baseline drift/change request không?
```

---

# 23. Definition of Done cấp P1

P1 chỉ được xem là implementation-complete khi đồng thời:

## 23.1 Database

- dependency migration đúng DBD v1.9;
- fresh migration pass;
- forward migration pass khi áp dụng;
- seed idempotent;
- PK/FK/unique/index verified;
- không có persistence ngoài baseline.

## 23.2 Authorization/Security

- no System Admin bypass;
- authoritative Evaluation Organization không client-overridable;
- Base Permission prerequisite;
- QT1 per lane đúng;
- denied-set intersection đúng;
- inherited ABSOLUTE_DENY không thể mở lại;
- GROUP/Secondary Assignment behavior đúng;
- 404/403 visibility behavior đúng.

## 23.3 Auto Numbering

- series/counter persistence đúng;
- uniqueness namespace validation;
- scope/period/timezone đúng;
- concurrency-safe;
- occupied-candidate skip;
- rollback không consume committed counter;
- committed identifier không reuse;
- issued series immutability/no-hard-delete;
- Product mapping `product_code -> products.code`.

## 23.4 Product/Pricing/Combo

- role derivation canonical;
- UOM invariant;
- Company -> Group -> Default pricing;
- nested COMBO acyclic;
- flatten correct;
- allocation conserves total amount;
- weighted aggregation correct;
- decimal-safe.

## 23.5 Import

- chỉ Product enabled;
- strict CSV/XLSX template;
- mode persisted;
- source-key graph;
- two-gate authorization;
- per-row atomicity;
- generated Product Code reported;
- failed row rollback includes numbering.

## 23.6 API

- `/api/v1`;
- canonical envelope;
- canonical status/error;
- BIGINT/DECIMAL string;
- request ID;
- list/filter/sort/page scope-safe;
- OpenAPI lint + codegen pass.

## 23.7 Frontend

- typed integration;
- no client-side authorization authority;
- critical P1 flows implemented;
- type/lint/unit/build pass;
- Playwright critical flows pass;
- accessibility smoke pass.

## 23.8 Governance

- FRS Acceptance Test Catalog pass;
- Implementation Contract mandatory tests pass;
- open P1 production decisions disposition rõ;
- không có unresolved baseline conflict;
- Release Candidate evidence pack hoàn chỉnh.

---

# 24. Implementation start point

Task đầu tiên sau khi tài liệu này được review:

```text
P1-ENV-001 - Repository & runtime inventory
```

Sau khi `P1-ENV-001` DONE, mở đồng thời:

```text
P1-ENV-002 - Backend quality command baseline
P1-ENV-003 - Frontend quality command baseline
P1-ENV-004 - Test database and migration harness
P1-INF-001 - Clock + Evaluation Time adapter
P1-INF-002 - Request/Correlation ID middleware
P1-INF-003 - Settings/BusinessTimezone resolver
P1-INF-005 - Decimal arithmetic readiness decision
```

Migration implementation bắt đầu tại:

```text
P1-DB-001 - Migration inventory & forward-only plan
```

và phải tuân dependency order DBD v1.9 trước khi mở các service phụ thuộc schema tương ứng.

---

# 25. Baseline promotion criteria cho tài liệu 04

`04_P1_Implementation_Plan_v1.1.md` có thể chuyển từ `draft` sang `baseline` khi:

- Engineering xác nhận environment binding đúng repository thực tế;
- actual folder/tool command convention đã được xác minh bởi `P1-ENV-001`;
- không có task nào yêu cầu thay đổi baseline P1 để bắt đầu;
- task ID/dependency được chấp thuận làm backlog source-of-truth;
- `05_P1_API_Design_v1.0.md` và `06_P1_UI_Design_v1.0.md` được chấp thuận làm downstream design cho rolling slices;
- quality gates backend/frontend khả thi trên môi trường hiện tại.

Sau baseline, thay đổi dependency/gate có thể bump plan version mà không cần bump UR/FRS/DBD/IMP nếu không thay đổi semantics/schema/contract upstream.
