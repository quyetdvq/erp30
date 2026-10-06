# Validation Profiles

Profiles select the smallest required gate for a task. Replace placeholders
with the repository command established by `P1-ENV-001..004`; do not claim a
check that was not run.

## Impact-aware gate selection

The task context `impact` selects the gate family in addition to the registry
profile. Unexpected changed paths escalate validation; they are never silently
skipped.

| Impact | Minimum gates | Explicit exclusions unless escalated/required |
|---|---|---|
| `db` | targeted DB/migration and backend gates | frontend lint/type/unit/build, Playwright, accessibility, OpenAPI |
| `backend-domain` | focused backend PHPUnit/PHPStan and relevant integration/concurrency checks | frontend gates, Playwright/accessibility, OpenAPI generation/lint |
| `backend-security` | backend plus PBAC/security negative paths and concurrency where applicable | frontend gates unless changed paths or task registry require them |
| `api-contract` | backend API/integration, serialization/locale, OpenAPI lint and typed generation when source changes | unrelated frontend UI gates and Playwright unless changed paths require them |
| `frontend` | affected frontend type/lint/format/unit/build and critical UI checks when required | unrelated DB/migration gates |
| `cross-layer` | relevant backend, API, frontend, and critical UI gates | none beyond task scope |
| `release` | full release evidence set | none |

Backend-only repository/service work must not trigger frontend lint/build/unit,
Playwright, accessibility, or OpenAPI gates by default. DB-only work must not
trigger frontend gates. API work includes backend and OpenAPI-relevant gates.

## `docs`

Validate authority links, headings, scope, spelling/format, and
`git diff --check`. Confirm no architecture baseline changed.

## `db-migration`

Run targeted schema/integrity tests, one fresh rehearsal, one real forward
rehearsal, and `git diff --check`. Do not run the entire regression suite for
each migration task. At DB batch end, run full fresh migration, sequential
forward verification, full PHPUnit, PHPStan level 8, and migration
isolation/inventory checks.

## `seed`

Run the seed twice against the same database, targeted catalog/bootstrap tests,
and exact idempotency assertions. Include authorization bootstrap negative
paths where applicable.

## `service-standard`

Run focused unit/integration tests for the changed service/repository, relevant
negative paths, and applicable PHPStan/PHPUnit or frontend type/lint/unit
checks. Include API/OpenAPI regeneration only when the public contract changes.

## `high-risk`

Run the full contract-required deep checks for the risk class: PBAC/context
precedence and 401/403/404 paths; transaction/DomainLock/concurrency; Auto
Number collision/rollback/no-reuse; exact Pricing fallback; nested/acyclic
COMBO expansion and decimal allocation; Import parser, dependency graph,
two-gate authorization, row atomicity, and number rollback; security bootstrap
no-bypass; or release evidence. Add PHPStan/PHPUnit and API/UI gates as
applicable. Static checks alone are insufficient.

## `api`

Run focused API/integration/contract tests, error/status/serialization,
authorization-context, visibility, collection-total, and locale checks. Run the
architecture/context guards. For public contract changes, update OpenAPI in the
same task, run `npm run openapi:lint`, regenerate typed frontend artifacts, and
run the generated-client drift/smoke check. Never implement a `CONTRACT_PENDING`
surface merely to make a gate pass.

## `frontend`

Run the affected Vitest/Playwright or accessibility checks plus `npm run
type-check`, `npm run lint`, `npm run format:check`, and `npm run build` when
the task's contract requires the full frontend gate. Keep critical journey
evidence separate from mocked/unit-only proof.

## `batch-full`

Run the union of impact-aware gates for the completed batch. Add all gates only
when the batch contains `cross-layer` or `release`, an unexpected changed path
is found, or a task registry explicitly requires them. Include migration
isolation and security/concurrency checks only when those domains are in the
batch.

## `release`

Run the Plan's Gate 9 evidence set: full FRS acceptance catalog, security and
concurrency regressions, fresh and existing-schema forward rehearsals,
OpenAPI/frontend drift, production configuration review, UAT package, and
release-candidate evidence pack. Release readiness requires every dependency
to be VERIFIED; unresolved evidence remains BLOCKED.
