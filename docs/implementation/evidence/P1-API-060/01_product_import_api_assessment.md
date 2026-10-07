# P1-API-060 Product Import API Assessment

## Outcome

Status: `DONE`.

The `P1-API-060` CONTRACT_READY Product Import surface is implemented and
verified against the canonical authority package (UR v1.0.13, FRS v1.9, DBD
v1.12, Implementation Contract v1.7, Implementation Plan v1.6, API Design
v1.4, and UI Design v1.5). It exposes the four planned runtime/OpenAPI
operations, keeps Product as the only import target, and preserves the
existing Product import domain boundary and generated client ownership. The
implementation commit is
`cce76c0f2fa39a2a54d82e847efdb440b2c3e366`.

## Implemented

- Added the Product Import controller and application service under `/api/v1`.
- Added authenticated start, status, result, and download/report operations
  with stable machine errors, request IDs, envelopes, and `vi`/`en` locale
  handling.
- Enforced the two-gate import authorization boundary: `product.import` at
  the GROUP scope plus normal per-row Product mutation authorization.
- Preserved Product-only `CREATE_ONLY`, `UPDATE_ONLY`, and `UPSERT` modes,
  source-key resolution, row-level atomicity, persisted row-derived result
  reconciliation, and generated Product identifiers.
- Added the import-job repository adapter, locale catalogs, canonical OpenAPI
  definitions, and regenerated frontend client/types.
- No migration, table, column, index, foreign key, Resource, or Action was
  added by this task.

## Validation

| Check | Result |
|---|---|
| `task-context.php P1-API-060` | PASS; `AUTHORITY_CACHE: VALID`, HIGH/api-contract, Gate 6 dependency |
| Git implementation verification | PASS; `cce76c0f2fa39a2a54d82e847efdb440b2c3e366` exists in repository history and contains the API-060 implementation |
| `test-db-harness.php rebuild --yes` | PASS twice; guarded `CI_ENVIRONMENT=testing`, database `erp_test`; canonical framework/package/application migrations completed; production `erp` untouched |
| `test-db-harness.php inventory` | PASS; `erp_test`, testing environment, `dinktech_` prefix, isolation guard PASS |
| Authenticated Product Import runtime journey | PASS; all 4 runtime/OpenAPI operations exercised with authenticated Shield context |
| Authorization and visibility | PASS; GROUP-scoped import gate, per-row Product authorization, invisible target `404`, visible denied action `403` |
| Transport and locale | PASS; `vi`/`en`, `Content-Language`, request ID, envelopes, BIGINT/DECIMAL string serialization |
| Product Import behavior | PASS; Product-only target, three modes, source-key dependencies, row atomicity, generated identifiers, persisted result reconciliation, and report/download behavior |
| Focused API PHPUnit (`P1Api060ProductImportTest.php --no-coverage`) | PASS; 19 tests, 164 assertions |
| Product Import regression suite | PASS; 50 tests, 246 assertions |
| Full PHPUnit (`test-db-harness.php full-tests`) | PASS; 398 tests, 3596 assertions |
| PHPStan level 8 | PASS; no errors |
| `pnpm run openapi:lint` | PASS |
| OpenAPI generation/drift | PASS; regenerated frontend client/types are synchronized |
| Frontend checks | PASS; `vue-tsc`, Oxlint/ESLint, Prettier, Vitest, production build, and 3/3 frontend E2E smoke tests |
| Decision/module-map/task registry validators | PASS |
| Architecture guard | PASS |
| Ledger validator | PASS after closure; 78 DONE tasks, Gate 7 9/10 |
| Context cache | PASS; `AUTHORITY_CACHE: VALID` |
| `git diff --check` | PASS |

## Contract impact

- DB/schema: the guarded canonical test harness rebuilt only `erp_test`; no
  manual settings table or migration-row manipulation was used, and `erp` was
  not touched.
- Authorization: existing Shield/PBAC services remain authoritative; no
  System Admin bypass, Resource Instance Authorization, or new Resource/Action
  was introduced.
- API/OpenAPI: four operations and their schemas are synchronized with the
  canonical OpenAPI source and generated frontend artifacts.
- UI/i18n: no screen or business-data semantics changed; generated API types
  and the backend `vi`/`en` error catalogs are synchronized.

## Authority/cache reconciliation

The repository authority remains the manifest-declared older package. The
cache repair commit is
`8fa3412a8919fe8e919d1f144726cddf00804eb5`, which makes validation deterministic
by hashing manifest-declared authority files only while reporting newer
noncanonical untracked `docs/architecture/*` files. Those user files were
preserved untouched and are not approved authority for this task.

## Closure

- Implementation commit: `cce76c0f2fa39a2a54d82e847efdb440b2c3e366`.
- Evidence and ledger closure are committed separately after this assessment.
- `P1-API-090` was not started.
