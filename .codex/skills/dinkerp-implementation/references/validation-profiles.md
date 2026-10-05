# Validation Profiles

Profiles select the smallest required gate for a task. Replace placeholders
with the repository command established by `P1-ENV-001..004`; do not claim a
check that was not run.

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

Run focused API/integration/contract tests, error/status/serialization and
locale checks, `npm run openapi:lint`, and regenerate typed frontend artifacts
when the OpenAPI source changes.

## `frontend`

Run the affected Vitest/Playwright or accessibility checks plus `npm run
type-check`, `npm run lint`, `npm run format:check`, and `npm run build` when
the task's contract requires the full frontend gate. Keep critical journey
evidence separate from mocked/unit-only proof.

## `batch-full`

Run the full gates required by the completed batch: backend PHPUnit and PHPStan
level 8, relevant fresh/forward/inventory checks, OpenAPI lint/drift and typed
generation, frontend type/lint/format/unit/build, and critical Playwright/
accessibility checks. Include migration isolation and security/concurrency
regressions when those domains are in the batch.

## `release`

Run the Plan's Gate 9 evidence set: full FRS acceptance catalog, security and
concurrency regressions, fresh and existing-schema forward rehearsals,
OpenAPI/frontend drift, production configuration review, UAT package, and
release-candidate evidence pack. Release readiness requires every dependency
to be VERIFIED; unresolved evidence remains BLOCKED.
