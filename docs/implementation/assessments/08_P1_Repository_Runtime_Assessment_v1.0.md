---
task_id: "P1-ENV-001"
title: "Repository & Runtime Inventory"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1 Repository & Runtime Assessment

## 1. Scope and authority

This assessment covers `P1-ENV-001` only. It inventories the current repository and runtime; it does not implement P1 business functionality, refactor unrelated code, or create migrations.

The review used `AGENTS.md`, the `dinkerp-implementation` skill, and the authority documents under `docs/architecture/` in the prescribed order. The relevant Implementation Plan sections are:

- environment binding and platform targets: Implementation Plan v1.3, sections 3.1-3.3;
- Gate 0 exit criteria: section 7.1;
- `P1-ENV-001` scope and acceptance: section 8.1;
- task start/dependency sequence: section 24.

## 2. Repository map

```text
D:\XAMPP\htdocs\erp\
├── AGENTS.md
├── docs\
│   ├── architecture\
│   │   ├── 00_User_Requirements_v1.0.12.md
│   │   ├── 01_P1_Functional_Requirements_Specification_v1.7.md
│   │   ├── 02_P1_Database_Design_v1.10.md
│   │   ├── 03_P1_Implementation_Contract_v1.5.md
│   │   ├── 04_P1_Implementation_Plan_v1.3.md
│   │   ├── 05_P1_API_Design_v1.1.md
│   │   └── 06_P1_UI_Design_v1.2.md
│   ├── implementation\
│   │   └── 07_Codex_Execution_Prompts_v1.1.md
│   └── thamkhao\
├── backend\
│   ├── app\
│   │   ├── Authorization\
│   │   ├── Config\
│   │   ├── Controllers\Api\
│   │   ├── Database\Migrations\
│   │   ├── Database\Seeds\
│   │   ├── Language\en,vi\
│   │   ├── Models\
│   │   └── Views\
│   ├── tests\
│   ├── tools\phpstan-bootstrap.php
│   ├── composer.json / composer.lock
│   ├── phpunit.dist.xml
│   └── phpstan.neon
└── frontend\
    ├── src\
    │   ├── components\
    │   ├── router\
    │   ├── services\
    │   ├── stores\
    │   ├── views\
    │   └── __tests__\
    ├── e2e\
    ├── package.json / pnpm-lock.yaml
    ├── vite.config.ts
    ├── eslint.config.ts / .oxlintrc.json / .prettierrc.json
    ├── playwright.config.ts
    └── openapi-ts.config.ts
```

Current tracked/working-tree facts:

- `backend/app/Models` contains only `.gitkeep`.
- `backend/app/Repositories` and `backend/app/Services` do not exist.
- `backend/app/Authorization` contains 13 custom authorization classes.
- `backend/app/Controllers/Api` contains `Admin`, `Auth`, `Authorization`, and `Health`.
- `backend/app/Database/Migrations` contains 19 PHP migration files plus `.gitkeep`.
- `backend/app/Database/Seeds` contains only `.gitkeep`; the only seeder is the PHPUnit support example under `backend/tests/_support`.
- `backend/tests` contains example database/session tests and one health unit test; no P1 domain/integration/concurrency suite is present.
- `frontend/src` contains one shell component, one router, four service files, two Pinia stores, eight views, and one unit test file.
- `frontend/e2e` contains one starter smoke spec and its TypeScript config.
- The worktree already contained user changes (`AGENTS.md`, deleted/added architecture/prompt documents, `.codex/`, and `docs/implementation/`); they were preserved.

## 3. Backend inventory

### 3.1 Framework and runtime versions

| Component | Detected version / state | Evidence |
|---|---|---|
| PHP CLI | 8.3.30 | `php -v` |
| CodeIgniter | 4.7.4 | `php spark --version`, Composer package |
| CodeIgniter Shield | 1.4.1 | `composer show codeigniter4/shield` |
| CodeIgniter Settings | 2.4.0 | `composer show codeigniter4/settings` |
| Composer | 2.7.7 | `composer --version` |
| PHPUnit | 10.5.65 | `composer show phpunit/phpunit` |
| PHPStan | 2.2.17 | `composer show phpstan/phpstan` |
| CodeIgniter PHPStan extension | 2.1.0 | `composer show codeigniter/phpstan-codeigniter` |
| PhpSpreadsheet | 5.10.0 | `composer show phpoffice/phpspreadsheet` |
| Smalot PdfParser | 2.12.5 | `composer show smalot/pdfparser` |

The platform versions match the Implementation Plan target for PHP 8.3.x, CodeIgniter 4.7.x, Shield, Settings, MariaDB 10.4.32, PhpSpreadsheet, and PHPUnit/PHPStan.

### 3.2 Composer, test, and static-analysis conventions

`backend/composer.json` exposes only one Composer script:

```text
test -> phpunit
```

PHPUnit is configured by `backend/phpunit.dist.xml` with:

- CodeIgniter test bootstrap;
- `backend/tests` as the `App` testsuite;
- `backend/app` as the source include;
- `backend/app/Config/Routes.php` and error views excluded from source coverage;
- a separate `tests` database group configured in `app/Config/Database.php`.

PHPStan is configured in `backend/phpstan.neon` at **level 8**, using `codeigniter/phpstan-codeigniter`, `tools/phpstan-bootstrap.php`, and `app` as its analysis path. There is no Composer script wrapping PHPStan.

### 3.3 Migrations, seeds, models, repositories, services

The application migration path is `backend/app/Database/Migrations`, configured through `Config\Database::$filesPath`. The application seed path is `backend/app/Database/Seeds`. No application seeder exists.

The current migration directory has an applied-history mismatch: the databases record application migrations `000014`, `000015`, and `000021`, but those migration files are absent from the current working tree. The PHPUnit database trait consequently reports a migration gap near `2026-09-29-000021`.

Existing application architecture is controller/query-builder based for the current admin endpoints. No application Repository or Service directory exists, and no domain Model implementation exists beyond the empty Models directory.

### 3.4 API and authentication/authorization conventions

`backend/app/Config/Routes.php` uses `/api/v1` and a CORS filter. Current routes expose health, auth, authorization profile/check, and `admin/*` endpoints. The Slice 1 API routes from API Design (`/provinces`, `/wards`, `/organizations`, `/contacts`) are not present.

The current transport is not the P1 common API contract yet:

- responses are controller-specific JSON shapes rather than one common envelope;
- request IDs are only read from `X-Request-ID` inside authorization check;
- no request/correlation ID middleware generates or propagates an ID;
- no OpenAPI source or generated client is present;
- no centralized stable error/status adapter is present.

Shield is integrated as the identity/authentication boundary. `Config\Auth` extends Shield configuration and enables session, access-token, and HMAC authenticators. `Config\AuthGroups` defines legacy Shield groups such as `systemadmin`, `orgadmin`, `developer`, `user`, and `beta`.

Custom authorization code exists in `backend/app/Authorization` and is registered through `Config\Services::authorization()`. `AuthorizationService` delegates to employee context, profile, resource/action, policy, decision, and audit logger classes.

The current admin controller also has a separate `adminGuard()` that authorizes by direct membership in `orgadmin` or `systemadmin`. This is existing behavior, not the P1 PBAC implementation, and it does not establish the P1 Base Permission/Evaluation Organization contract for future work.

### 3.5 Database configuration and MariaDB

`Config\Database` uses MySQLi, port 3306, the `dinktech_` prefix, and environment-bound default database `erp`. The PHPUnit `tests` group targets `127.0.0.1`, database `erp_test`, MySQLi, strict mode, foreign keys, and the same prefix.

The XAMPP MariaDB client/server reports:

```text
mysql  Ver 15.1 Distrib 10.4.32-MariaDB
SELECT VERSION() -> 10.4.32-MariaDB
```

The server is reachable without changing state. Both `erp` and `erp_test` exist. `erp` currently contains the Shield/Settings tables plus the application tables. `erp_test` also contains the PHPUnit support/example schema and applied migration records.

`Config\Migrations::$lock` is `false`. No application migration lock or deployment rehearsal command is configured.

### 3.6 Transactions, advisory locks, decimal, timezone, locale

- Transactions: only direct `transStart()`/`transComplete()`/`transRollback()` usage was found in `Api\Admin`; no domain transaction boundary or transaction service exists.
- Advisory locks: MariaDB capability is available and was verified with `GET_LOCK()`/`RELEASE_LOCK()` on the same CLI session. No `DomainLockService`, wrapper, or application use exists.
- Decimal: PHP `bcmath` is enabled, but no application use of BCMath, Brick Math, or another decimal abstraction was found. No authoritative decimal test exists. Binary-float safety is therefore not yet established.
- Timezone: `Config\App::$appTimezone` is `UTC`. There is no Company/GROUP business-timezone resolver or Evaluation Time adapter. The Settings package and Settings migrations are installed, but no ERP settings service consumes them for timezone resolution.
- Locale: `Config\App` has `defaultLocale = en`, `negotiateLocale = false`, and `supportedLocales = ['en']`. Vietnamese language files exist, but `vi/en` negotiation and `Content-Language` response handling are not implemented.

## 4. Frontend inventory

### 4.1 Framework, package, and folder structure

The frontend is Vue 3 + TypeScript + Vite. Installed versions include Vue 3.5.42, Vite 8.3.0, Vue Router 5.3.1, Pinia 4.0.3, Axios 1.20.0, Naive UI 2.45.3, Vue I18n 11.4.12, Zod 4.6.5, TypeScript 6.0.3, Vitest 4.1.11, Playwright 1.63.0, ESLint 10.10.0, Oxlint 1.82.0, Prettier 3.9.6, and `vue-tsc` 3.3.11.

`frontend/package.json` defines:

```text
dev, build, preview, test:unit, test:e2e, build-only, type-check,
lint, lint:oxlint, lint:eslint, format
```

The `lint:*` and `format` scripts use write/fix flags. Read-only equivalents were used for this assessment.

### 4.2 Router, Pinia, Axios, UI library, and i18n

- Router: `src/router/index.ts` uses `createRouter/createWebHistory`, a login route, an `AppShell` parent, and routes for dashboard, profile, admin users/groups, organizations, approvals, products, and sales.
- Pinia: `src/main.ts` installs Pinia. `src/stores/auth.ts` is the active auth/session store; `src/stores/counter.ts` is the starter example store.
- Axios: `src/services/api.ts` creates one Axios client with an environment base URL, JSON accept header, timeout, and credentials. There is no locale/request-ID interceptor.
- Naive UI: package is installed, but no `naive-ui` import or component usage was found in `src`.
- Vue I18n: package is installed, but no `createI18n`, `useI18n`, locale resource directory, or i18n installation was found. UI text is currently hard-coded, mainly in Vietnamese.
- OpenAPI/code generation: `openapi-ts.config.ts` points to `../contracts/openapi/05A_OpenAPI_3.1.yaml` and writes to `src/api/generated`; the source file and generated directory do not exist.

### 4.3 Workspace shell

`src/components/AppShell.vue` provides a top bar, a responsive fixed sidebar, a command-palette overlay, and a computed current route tab. The current shell has four menu groups (`WORKSPACE`, `QUẢN TRỊ`, `NỀN TẢNG`, `NGHIỆP VỤ`). It does not yet implement the UI Design contract's five fixed sidebar modes, persistent workspace preferences, drag resize, collapse/expand rail, canonical i18n keys, or multi-tab state.

### 4.4 Frontend test/tooling state

- `vue-tsc --build`: PASS.
- Vitest unit run: PASS, one test file and one test.
- Vite production build: PASS.
- Direct read-only ESLint: PASS.
- Direct Oxlint: PASS.
- Prettier check: FAIL; 14 source files are not formatted according to the current Prettier configuration.
- Playwright discovery (`playwright test --list`): PASS; one starter test is discovered for Chromium, Firefox, and WebKit.
- Playwright Chromium run: FAIL before the test because the configured Vite server uses port 5174 while Playwright starts the server on port 5173 and port 5174 was already occupied. The discovered test still asserts the Vue starter text `You did it!`, so it is not a P1 critical-flow test.

## 5. Documentation integration

All seven requested authority files exist under `docs/architecture/`:

```text
00_User_Requirements_v1.0.12.md
01_P1_Functional_Requirements_Specification_v1.7.md
02_P1_Database_Design_v1.10.md
03_P1_Implementation_Contract_v1.5.md
04_P1_Implementation_Plan_v1.3.md
05_P1_API_Design_v1.1.md
06_P1_UI_Design_v1.2.md
```

The current working documents declare `04`, `05`, and `06` as `status: "draft"`. The Implementation Plan's environment-binding example still names `docs/04_P1_Implementation_Plan_v1.3.md`, while the actual authoritative path used by `AGENTS.md` and the requested task is `docs/architecture/04_P1_Implementation_Plan_v1.3.md`. This is a documentation path inconsistency; it does not require changing the authority documents in this task.

The API design is still `PLANNED` at Slice 1 level, and the UI design is still `PLANNED` at Slice 1 level. Their physical OpenAPI/generated-client locations are therefore only configured by the frontend file and not materialized in the repository.

## 6. Actual commands and results

| Command/check | Result | Relevant observation |
|---|---|---|
| `php -v` | PASS | PHP 8.3.30 |
| `php spark --version` | PASS | CodeIgniter 4.7.4 |
| `composer --version` | PASS | Composer 2.7.7 |
| `composer show ...` | PASS | Framework, Shield, Settings, PHPUnit, PHPStan, PhpSpreadsheet, PdfParser versions recorded above |
| `php spark routes` | PASS | Current `/api/v1` routes inventoried; Slice 1 contract routes absent |
| `php spark config:check App/Database/Migrations/Auth/AuthGroups` | PASS | Configurations load; current locale/timezone and DB settings recorded above |
| `php spark migrate:status` | PASS | Status listing runs, but applied history includes migration versions absent from the working tree |
| XAMPP `mysql.exe --version` and `SELECT VERSION()` | PASS | MariaDB 10.4.32 reachable |
| MariaDB `GET_LOCK`/`RELEASE_LOCK` probe | PASS | Server advisory-lock capability available |
| `vendor\bin\phpunit --testsuite App --no-coverage` | FAIL | 2 database-test errors: migration gap near `2026-09-29-000021` |
| `vendor\bin\phpstan analyse --configuration phpstan.neon --no-progress` | FAIL | 86 level-8 findings in existing authorization/controllers/migrations |
| `pnpm exec eslint . --no-cache` | PASS | Read-only ESLint run |
| `pnpm exec oxlint .` | PASS | Read-only Oxlint run |
| `pnpm exec prettier --check src/` | FAIL | 14 files require formatting |
| `pnpm run type-check` | PASS | `vue-tsc --build` |
| `pnpm run test:unit -- --run` | PASS | 1 file, 1 test |
| `pnpm run build-only` | PASS | Vite production build |
| `pnpm exec playwright test --list` | PASS | 3 browser projects list one starter spec |
| `pnpm run test:e2e -- --project=chromium` | FAIL | Vite server could not start because port 5174 was occupied; no critical P1 flow exists |

No migration, seed, schema, code, or configuration write command was run for this assessment.

## 7. Gaps against the Implementation Plan

### Gate 0 gaps

1. Backend PHPUnit is not a passing repeatable gate because the current test database migration history references missing migration files.
2. PHPStan level 8 is configured but fails with 86 existing findings.
3. Frontend typecheck, unit test, build, ESLint, and Oxlint run; Prettier fails, and Playwright cannot run with the current port/process configuration.
4. No dedicated test database reset/fresh/forward harness is documented beyond CodeIgniter's PHPUnit database trait and the hard-coded `erp_test` group.
5. Clock/Evaluation Time adapter is absent.
6. Request/Correlation ID middleware is absent.
7. Settings/BusinessTimezone resolver is absent.
8. DomainLock/advisory-lock abstraction is absent even though MariaDB `GET_LOCK` works.
9. A safe decimal library/adapter is not selected or exercised; only the PHP BCMath extension is available.
10. Backend locale negotiation is disabled and only `en` is configured; frontend i18n is not wired.
11. Canonical OpenAPI source and generated frontend client/types are absent.

### Architecture and contract gaps

- Existing migration files omit required DBD columns/indexes/constraints in several P1 tables and must not be retroactively rewritten; any repair must follow the forward-only migration rule.
- Applied migration records contain classes whose source files are absent, creating a migration-history/repository conflict.
- Current admin authorization uses direct Shield group membership rather than the P1 PBAC service boundary; it must not be treated as Slice 1 authorization verification.
- Current API response shapes and route set do not implement the API Design v1.1 common transport or Slice 1 endpoints.
- Current UI shell does not meet the five-mode/resizable/collapsible workspace contract, and the frontend does not use the required `vue-i18n` layer.

## 8. Recommended locations and conventions

These locations match the existing repository layout and the Implementation Plan; they are recommendations only and were not created by this task.

| Concern | Recommended location |
|---|---|
| Application migrations | `backend/app/Database/Migrations/` |
| Idempotent application seeds | `backend/app/Database/Seeds/` |
| Domain persistence primitives | `backend/app/Repositories/` |
| Domain/application invariants | `backend/app/Services/` |
| Thin HTTP adapters | `backend/app/Controllers/Api/` |
| Shared API transport/error/request ID | `backend/app/Filters/`, `backend/app/Libraries/`, or an existing service location after contract confirmation |
| Authorization integration | existing `backend/app/Authorization/` plus service registration in `backend/app/Config/Services.php` |
| Backend tests | `backend/tests/unit/`, `backend/tests/database/`, and additional integration/concurrency suites under `backend/tests/` |
| Canonical OpenAPI source | repository-controlled `contracts/openapi/` as referenced by `frontend/openapi-ts.config.ts`, after confirming the final location in the API task |
| Generated frontend client/types | `frontend/src/api/generated/` (generated, not manually edited) |
| Frontend API adapters | existing `frontend/src/services/` |
| Frontend state | existing `frontend/src/stores/` |
| Frontend screens/components | existing `frontend/src/views/` and `frontend/src/components/` |
| Frontend locales | a single `frontend/src/locales/` convention once i18n is approved; no parallel locale architecture |
| Frontend tests | `frontend/src/**/__tests__/` and `frontend/e2e/` |

## 9. Blockers and next dependencies

1. Resolve the applied-versus-present migration history before relying on PHPUnit database tests or performing fresh/forward migration verification. Do not recreate or rewrite an applied migration without an approved forward-only decision.
2. Complete `P1-ENV-002` after this inventory to establish passing/repeatable backend PHPUnit and PHPStan commands.
3. Complete `P1-ENV-003` for a non-mutating frontend lint/format/type/test/build baseline and correct the Vite/Playwright port contract before critical browser smoke.
4. Complete `P1-ENV-004` for an isolated, reproducible test database workflow.
5. Open the planned `P1-INF-001`, `P1-INF-002`, `P1-INF-003`, `P1-INF-004`, `P1-INF-005`, and `P1-INF-006` tasks before Slice 1 service/API work.
6. Do not mark Gate 0 or Slice 1 `VERIFIED` from the current scaffold, even though some frontend tooling commands pass.

## 10. Recommendation

`P1-ENV-001` can be marked **DONE** because the repository/runtime map, actual tool versions, current conventions, database/migration state, command results, and exact implementation locations have been recorded without changing the application.

This does **not** mean Gate 0 is ready. Gate 0 remains **BLOCKED/PARTIAL** until the PHPUnit migration gap, PHPStan findings, Prettier/Playwright baseline issues, test-database harness, infrastructure adapters, locale/i18n foundation, and OpenAPI location are resolved by their own tasks.

Task: P1-ENV-001
Status: DONE

Validation:
- `php -v`: PASS
- `php spark --version`: PASS
- `composer --version`: PASS
- Composer package inventory: PASS
- `php spark routes`: PASS
- `php spark config:check App/Database/Migrations/Auth/AuthGroups`: PASS
- `php spark migrate:status`: PASS (inventory listing; migration-history gap recorded)
- XAMPP MariaDB version/connectivity probe: PASS
- MariaDB advisory-lock capability probe: PASS
- `vendor\\bin\\phpunit --testsuite App --no-coverage`: FAIL (migration gap near `2026-09-29-000021`)
- `vendor\\bin\\phpstan analyse --configuration phpstan.neon --no-progress`: FAIL (86 findings)
- `pnpm exec eslint . --no-cache`: PASS
- `pnpm exec oxlint .`: PASS
- `pnpm exec prettier --check src/`: FAIL (14 files)
- `pnpm run type-check`: PASS
- `pnpm run test:unit -- --run`: PASS
- `pnpm run build-only`: PASS
- `pnpm exec playwright test --list`: PASS
- `pnpm run test:e2e -- --project=chromium`: FAIL (port 5174 occupied; no P1 critical flow)

## 11. Current reassessment after Gate 0 configuration

Reassessment timestamp: `2026-10-05 13:40:00 +07:00`.

Sections 1-10 are the original P1-ENV-001 inventory snapshot and retain the findings that were true before the authorized development/test reset and Gate 0 remediation. This section is the current repository/runtime state. It does not change the authority documents or introduce P1 business functionality.

### 11.1 Current repository and migration state

- `backend/app/Database/Migrations/` intentionally contains only `.gitkeep` after the authorized one-time local reset. The discarded application migration lineage, including the historical `000021` row, was not recreated or replaced with a guessed/no-op migration.
- `backend/app/Database/Seeds/` remains empty except for `.gitkeep`; no business seed was added.
- `erp` and `erp_test` remain separate databases. The test harness mutates only the explicitly approved `erp_test` database and requires `--yes` for destructive operations.
- The current source migration inventory is the four canonical CodeIgniter Shield/Settings package migrations. Final `erp_test` inventory reports four migration rows and an isolation guard PASS.
- Application fresh/forward migration for a business baseline is intentionally deferred to `P1-DB-001`; this inventory does not infer the discarded legacy migration.

### 11.2 Current runtime and infrastructure seams

The following Gate 0 seams now exist in the actual repository:

| Concern | Current implementation | Status |
|---|---|---|
| Evaluation time | `backend/app/Infrastructure/Time/Clock.php`, `SystemClock.php`, `EvaluationTime.php` | PASS |
| Request/correlation ID and locale | `backend/app/Filters/RequestContextFilter.php`, `RequestContext.php` | PASS |
| Business timezone | `backend/app/Infrastructure/Settings/BusinessTimezoneResolver.php` through CodeIgniter Settings | PASS |
| MariaDB domain lock | `DomainLockService.php` and `MariaDbDomainLockService.php` using same-session `GET_LOCK`/`RELEASE_LOCK` | PASS |
| Decimal arithmetic | `backend/app/Infrastructure/Decimal/Decimal.php` using BCMath string arithmetic and half-up rounding | PASS |
| Backend locale baseline | `vi`/`en`, fallback `vi`, `Content-Language` response header | PASS |
| Shield/Settings | canonical package migrations and existing Shield configuration | PASS |

The existing `Config\\Migrations::$lock = false` remains a framework configuration fact. Domain-level race-sensitive operations use the new application advisory-lock abstraction; no business mutation was added in this task.

### 11.3 Current API/OpenAPI and frontend integration

- Canonical Gate 0 OpenAPI source: `contracts/openapi/openapi.yaml`.
- Generated frontend client: `frontend/src/api/generated/`; generated files are excluded from manual ESLint/Prettier analysis and are regenerated by the pipeline.
- `frontend/openapi-ts.config.ts` now points to the canonical source and `frontend/package.json` provides `openapi:lint` and `openapi:generate` scripts.
- Frontend packages and shell/i18n/Naive UI setup are present; the current frontend baseline is validated independently in the Gate 0 assessment.

### 11.4 Current commands and results

Backend:

| Command | Result |
|---|---|
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpunit --no-coverage` | PASS; 10 tests, 19 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` | PASS; 0 errors |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php inventory` | PASS; exactly `erp_test`, isolation guard PASS, 4 migration rows |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php db-tests` | PASS; 2 tests, 3 assertions |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` | PASS; two consecutive fresh rebuilds |
| `D:\\XAMPP\\php\\php.exe spark migrate:status` | PASS; development package migration status available |
| `D:\\XAMPP\\php\\php.exe spark routes` | PASS; request-context filter registered around API routes |
| HTTP health smoke with `X-Request-ID`/`Accept-Language` | PASS; ID echo, `en`, and unsupported-locale fallback `vi` |

Frontend:

| Command | Result |
|---|---|
| `pnpm install --frozen-lockfile` | PASS |
| `pnpm run openapi:lint` | PASS |
| `pnpm run openapi:generate` | PASS; generated client emitted |
| `pnpm run lint` | PASS |
| `pnpm run format:check` | PASS |
| `pnpm run type-check` | PASS |
| `pnpm run test:unit -- --run` | PASS; 2 tests |
| `pnpm run build` | PASS |
| `pnpm exec playwright test --list` | PASS; 3 tests discovered |
| `pnpm exec playwright test --reporter=line` | PASS; 3/3 browsers |

### 11.5 Updated recommendation

`P1-ENV-001` remains **DONE** as the repository/runtime inventory task. The original findings above are historical and must not be read as the current Gate 0 verdict. Current Gate 0 readiness is recorded in `09_P1_Gate0_Readiness_Assessment_v1.0.md` as `DONE`; P1-ENV-004 is recorded in `10_P1_ENV_004_Test_DB_Migration_Harness_v1.0.md` as `DONE` after the authorized reset.

The next dependency is `P1-DB-001` for an approved forward-only application migration inventory and Gate 1 business schema rehearsal. No Slice 1 business functionality is declared implemented by this reassessment.

Task: P1-ENV-001 - Repository & Runtime Inventory
Status: DONE

Current validation:
- repository/runtime inventory: PASS
- backend PHPUnit/PHPStan level 8: PASS
- isolated test database harness: PASS
- frontend quality baseline: PASS
- OpenAPI lint/codegen: PASS
- request ID/locale/DomainLock/Decimal infrastructure checks: PASS
