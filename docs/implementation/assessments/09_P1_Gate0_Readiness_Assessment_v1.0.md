---
task_id: "GATE-0"
title: "P1 Repository & Toolchain Readiness"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
frontend_status: "DONE"
---

# P1 Gate 0 Readiness Assessment

## 1. Scope and authority

This report updates the Gate 0 readiness decision using the current repository state and fresh command results. It uses:

- `AGENTS.md`;
- `.codex/skills/dinkerp-implementation/SKILL.md` and its project invariants/review checklist;
- `docs/architecture/00_User_Requirements_v1.0.12.md` through `06_P1_UI_Design_v1.2.md`;
- `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, especially sections 3, 5, 7.1 and 8.1;
- historical inventory `08_P1_Repository_Runtime_Assessment_v1.0.md`.

This report does not change business semantics, database schema, migrations, API contracts, or authorization rules.

## 2. Decision

**Gate 0 overall: BLOCKED / NOT PASS.**

The frontend quality baseline now passes. The overall Gate 0 cannot pass because the mandatory backend quality baseline and migration/test-database readiness remain unresolved.

| Area | Status | Evidence |
|---|---|---|
| P1-ENV-001 repository/runtime inventory | DONE | Assessment 08 exists and records the actual map and versions |
| P1-ENV-002 backend quality command baseline | BLOCKED | PHPUnit fails at migration gap; PHPStan level 8 reports 86 errors |
| P1-ENV-003 frontend quality command baseline | DONE | All current frontend commands and browser smoke pass |
| P1-ENV-004 test database/migration harness | BLOCKED | `erp_test` exists, but migration history references missing files and no isolated fresh/forward harness is available |
| P1-INF-001..006 foundation adapters | NOT DONE | Clock, request ID, timezone resolver, DomainLock, decimal decision, and backend locale foundation are not complete |

## 3. Frontend Gate 0 result

Frontend Gate 0 is **DONE** for the current baseline.

Current frontend state:

- `vue-i18n@11.4.12` and `naive-ui@2.45.3` are installed; `pnpm install --frozen-lockfile` confirms the lockfile is complete.
- `vue-i18n` is wired with `vi`/`en`, fallback `vi`, persisted locale, document language update, and locale-key parity test.
- Axios sends the active `Accept-Language` value.
- Naive UI providers are installed at application root.
- Vite and Playwright use the same development port (`5173`).
- Shell infrastructure includes the five fixed mode tabs, sidebar collapse/resize preferences, and locale-aware labels.
- Playwright smoke covers unauthenticated root redirect, locale switch, reload persistence, `Accept-Language`, and Axe accessibility checks.

## 4. Fresh validation results

### Frontend

| Command | Result | Evidence |
|---|---|---|
| `pnpm install --frozen-lockfile` | PASS | Lockfile up to date; dependencies resolved without changes |
| `pnpm run lint` | PASS | ESLint and Oxlint both complete successfully |
| `pnpm run format:check` | PASS | All `src/` and `e2e/` files formatted |
| `pnpm run type-check` | PASS | `vue-tsc --build` succeeds |
| `pnpm run test:unit -- --run` | PASS | 2 test files, 2 tests passed |
| `pnpm run build` | PASS | Vite production build succeeds |
| `pnpm exec playwright test --list` | PASS | Chromium, Firefox, WebKit test projects discovered |
| `pnpm exec playwright test --reporter=line` | PASS | 3/3 browser projects pass; accessibility smoke included |

### Backend

| Command | Result | Current blocker |
|---|---|---|
| `D:\XAMPP\php\php.exe vendor\\bin\\phpunit --no-coverage` | FAIL | 2 database-test errors: migration gap near `2026-09-29-000021` |
| `D:\XAMPP\php\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G` | FAIL | 86 level-8 findings across authorization, controllers, and migrations |

The PHPUnit error is caused by applied migration history that references missing application migration files, including the sequence around `000021`. The report does not recreate or rewrite those applied migrations.

## 5. Gate 0 exit criteria

| Plan v1.3 criterion | Status | Reason |
|---|---|---|
| Environment binding verified | PASS | Recorded in assessment 08 |
| Backend PHPUnit/PHPStan level 8 gate | FAIL | Both commands are currently failing |
| Frontend lint/type/test/build gate | PASS | Fresh frontend validation above |
| Test database strategy identified | PARTIAL | `erp_test` exists, but no clean reproducible fresh/forward harness |
| Shield/Settings/Clock/DomainLock/decimal checked | PARTIAL | Shield/Settings present; required Clock/DomainLock/decimal application seams are not complete |
| OpenAPI source/generated client location identified | PARTIAL | Frontend config points to `contracts/openapi/05A_OpenAPI_3.1.yaml` and `src/api/generated`, but neither artifact is present |

Because the backend quality and migration/test-database criteria are not passing, Gate 0 cannot be marked `DONE`.

## 6. Blockers and next order

1. Resolve the applied-versus-present migration history with an approved forward-only decision; do not add guessed no-op migrations or rewrite applied history.
2. Complete `P1-ENV-004` with an isolated fresh/forward test database workflow.
3. Complete `P1-ENV-002` by fixing the 86 PHPStan level-8 findings and re-running the full PHPUnit suite.
4. Complete the required `P1-INF-001..006` infrastructure tasks before Slice 1 implementation is declared ready.
5. Materialize and validate the canonical OpenAPI source before API/frontend generated-client work.

## 7. Final status

Task: Gate 0 - Repository & Toolchain Readiness
Status: BLOCKED

Frontend Gate 0: DONE

Validation:

- `pnpm install --frozen-lockfile`: PASS
- `pnpm run lint`: PASS
- `pnpm run format:check`: PASS
- `pnpm run type-check`: PASS
- `pnpm run test:unit -- --run`: PASS
- `pnpm run build`: PASS
- `pnpm exec playwright test --list`: PASS
- `pnpm exec playwright test --reporter=line`: PASS
- `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpunit --no-coverage`: FAIL
- `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G`: FAIL

Recommendation: do not open Slice 1 as `VERIFIED` and do not report overall Gate 0 as PASS until the backend blockers above are resolved.

Superseded migration blocker note:
On 2026-10-05 the Product Owner explicitly discarded the obsolete application
migration lineage and authorized a clean local development/test DB reset.
The former missing migration around 2026-09-29-000021 is therefore no longer
to be recovered. Gate 0 database readiness must be reassessed from the new
empty baseline.

## 8. Current reassessment after legacy reset and Shield setup

Reassessment timestamp: `2026-10-05 13:04:27 +07:00`.

This section is an additive current-state update. The earlier sections retain the historical pre-reset Gate 0 evidence.

### Current area status

| Area | Current status | Evidence |
|---|---|---|
| P1-ENV-001 repository/runtime inventory | DONE | Assessment 08 remains valid for the repository/runtime map |
| P1-ENV-002 backend quality baseline | BLOCKED | PHPUnit now passes; PHPStan level 8 still reports 80 errors |
| P1-ENV-003 frontend quality baseline | DONE | Install, lint, format, typecheck, unit, build, Playwright discovery and 3-browser smoke all pass |
| P1-ENV-004 test DB/migration harness | DONE | Isolated `erp_test` guard, two consecutive fresh rebuilds, database PHPUnit, and final inventory pass; application migration baseline is intentionally deferred to `P1-DB-001` after the authorized reset |
| P1-INF-001 Clock/Evaluation Time | NOT DONE | No dedicated Clock/Evaluation Time adapter found |
| P1-INF-002 Request/Correlation ID | NOT DONE | No request/correlation middleware found; existing logger/request fields do not establish propagation |
| P1-INF-003 Settings/BusinessTimezone | NOT DONE | Settings tables are installed, but no ERP Company/GROUP timezone resolver exists |
| P1-INF-004 DomainLock | NOT DONE | MariaDB capability exists, but no application DomainLock abstraction/use exists |
| P1-INF-005 Decimal readiness | NOT DONE | No approved decimal arithmetic abstraction is selected or exercised |
| P1-INF-006 backend locale foundation | NOT DONE | `defaultLocale=en`, negotiation disabled, supported locales only `en` |
| OpenAPI source/generated client | PARTIAL | Frontend config points to a path, but the OpenAPI source and generated client are absent |

### Current database/migration state

`php spark shield:setup` completed without overwriting existing Auth configuration. Canonical Shield/Settings migrations were then applied to development database `erp`. The current development database has 9 package tables and 4 migration-history rows. The test harness rebuilt `erp_test` twice and produced the same 4 package migration rows; no obsolete application migration or `000021` history remains.

The application migration directory intentionally contains no PHP migrations after the Product Owner reset; only `.gitkeep` remains. Therefore the harness proves isolated package-schema rebuildability, but a P1 application fresh/forward migration chain cannot yet be validated. Forward migration remains `NOT RUN` until an approved current-baseline migration set exists.

### Current commands actually run

| Command | Result |
|---|---|
| `D:\XAMPP\php\php.exe vendor\\bin\\phpunit --no-coverage` | PASS; 5 tests, 7 assertions |
| `D:\XAMPP\php\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` | FAIL; 80 errors |
| `pnpm install --frozen-lockfile` | PASS |
| `pnpm run lint` | PASS |
| `pnpm run format:check` | PASS |
| `pnpm run type-check` | PASS |
| `pnpm run test:unit -- --run` | PASS; 2 tests |
| `pnpm run build` | PASS |
| `pnpm exec playwright test --list` | PASS; 3 tests discovered |
| `pnpm exec playwright test --reporter=line` | PASS; 3/3 browsers |
| `D:\XAMPP\php\php.exe tools\\test-db-harness.php rebuild --yes` | PASS; two consecutive `erp_test` rebuilds |
| `D:\XAMPP\php\php.exe tools\\test-db-harness.php inventory` | PASS; isolation guard resolves to `erp_test` |
| `D:\XAMPP\php\php.exe spark migrate:status` | PASS; four package migrations applied in `erp` |

### Current Gate 0 verdict

Task: Gate 0 - Repository & Toolchain Readiness
Status: BLOCKED

Frontend Gate 0: DONE

The prior migration-gap blocker is superseded by the explicit local reset. Gate 0 is still blocked by the 80 PHPStan level-8 findings, incomplete P1-INF-001..006 infrastructure foundation, absent canonical OpenAPI/generated client artifacts, and the absence of a current P1 application migration baseline needed to validate application fresh/forward migration. Slice 1 must remain closed.

Recommended next order:

1. `P1-ENV-002` — resolve the existing 80 PHPStan errors without suppressions and rerun backend quality gates.
2. `P1-ENV-004` — define the approved current-baseline migration inventory and complete application fresh/forward harness validation.
3. `P1-INF-001..006` — implement the required Clock, request ID, timezone, DomainLock, decimal, and locale foundations.
4. Materialize and validate the canonical OpenAPI source before API/client work.

## 9. Gate 0 completion after configuration and fixes

Reassessment timestamp: `2026-10-05 13:24:00 +07:00`.

The previous blocked decision is superseded by the completed Gate 0 validation below. The Product Owner reset removed the obsolete application migration lineage; no business migration was recreated or inferred. The empty application migration directory is therefore a Gate 1 prerequisite, not a Gate 0 failure.

### Completed Gate 0 work

- Fixed the 80 level-8 PHPStan findings with explicit generic types, query-result checks, typed JSON payload parsing, and Shield authenticator typing. No PHPStan suppression, baseline, or artificial cast was added.
- Added the single UTC `Clock`/`EvaluationTime` seam and business-date conversion by resolved timezone.
- Added global `RequestContextFilter` for validated/generated `X-Request-ID`, request-locale negotiation, `Content-Language`, and authorization request-id propagation.
- Added `BusinessTimezoneResolver` through CodeIgniter Settings with contextual lookup and a configuration fallback when the Settings table is not yet installed.
- Added `DomainLockService` and MariaDB `GET_LOCK`/`RELEASE_LOCK` adapter using the same connection/session, namespaced keys, finite timeout, and `finally` release.
- Added BCMath-backed `Decimal` arithmetic and half-up rounding tests; authoritative values remain decimal strings and never binary floats.
- Configured backend locales to `vi`/`en`, default/fallback `vi`.
- Materialized `contracts/openapi/openapi.yaml`, Redocly lint, and the `@hey-api/openapi-ts` generated client at `frontend/src/api/generated/`. The generation script now uses the correct `-f` config-file option.
- Added generated-client exclusions to ESLint/Prettier; generated files remain tool-owned and are not hand-edited.

### Current Gate 0 decision

| Exit criterion | Status | Evidence |
|---|---|---|
| Environment binding and repository map | PASS | Assessment 08 and this assessment |
| Backend PHPUnit baseline | PASS | 10 tests, 19 assertions |
| PHPStan level 8 | PASS | 0 errors |
| Frontend lint/typecheck/unit/build | PASS | All commands below |
| Frontend Playwright/accessibility smoke | PASS | 3/3 browser projects |
| Isolated test database workflow | PASS | `erp_test` guard PASS; two consecutive fresh rebuilds; 4 package migration rows |
| Shield and Settings integration | PASS | package migrations and runtime configuration verified |
| Clock/Evaluation Time | PASS | unit test and runtime seam |
| Request/Correlation ID and locale | PASS | HTTP smoke: header echo, generated UUID, `en`, unsupported `fr` fallback to `vi` |
| DomainLock MariaDB adapter | PASS | PHPUnit synchronized lock test on test connection |
| Decimal mechanism | PASS | BCMath precision and half-up rounding unit tests |
| OpenAPI source/lint/generated client locations | PASS | Redocly and openapi-ts commands pass |

### Commands actually passing in the final validation

Backend:

- `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpunit --no-coverage`: PASS; 10 tests, 19 assertions.
- `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress`: PASS; no errors.
- `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php db-tests`: PASS; 2 tests, 3 assertions.
- `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes`: PASS twice consecutively; fresh Shield/Settings migrations complete.
- `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php inventory`: PASS; configured database is exactly `erp_test`, migration rows `4`.
- `D:\\XAMPP\\php\\php.exe spark migrate:status`: PASS for development `erp` package migrations.
- `D:\\XAMPP\\php\\php.exe spark routes`: PASS; request-context filter is registered before/after API routes.
- HTTP smoke via `Invoke-WebRequest`: PASS for request-id echo, locale negotiation, and unsupported-locale fallback.

Frontend:

- `pnpm install --frozen-lockfile`: PASS.
- `pnpm run openapi:lint`: PASS.
- `pnpm run openapi:generate`: PASS; generated client emitted under `src/api/generated`.
- `pnpm run lint`: PASS.
- `pnpm run format:check`: PASS.
- `pnpm run type-check`: PASS.
- `pnpm run test:unit -- --run`: PASS; 2 tests.
- `pnpm run build`: PASS.
- `pnpm exec playwright test --list`: PASS; 3 tests discovered.
- `pnpm exec playwright test --reporter=line`: PASS; 3/3 browsers.

### Scope boundary after Gate 0

No P1 business functionality, business migration, seed, public Slice 1 endpoint, or authorization-policy semantics were introduced by this Gate 0 work. The next required task is the approved Gate 1 migration/bootstrap baseline (`P1-DB-001` or the exact Task ID selected from the Implementation Plan), followed by Slice 1 sequencing.

Task: GATE-0 - Repository & Toolchain Readiness
Status: DONE

Validation:
- backend quality commands: PASS
- isolated test database harness: PASS
- frontend quality commands: PASS
- OpenAPI lint/codegen: PASS
- request-id/locale HTTP smoke: PASS
