# P1-API-090 API Contract Regression Assessment

## Outcome

Status: `DONE`.

The canonical Gate 7 API contract regression suite now inventories the actual
CONTRACT_READY runtime surface from CodeIgniter's loaded route collection and
compares normalized path/verb pairs with the canonical OpenAPI document. It
also guards the shared transport, error, query, localization, serialization,
and CONTRACT_PENDING absence rules for API-001..060. The implementation test
commit is `e9d5ee2ed7b669edba3bf44144da262e9d39a971`.

## Contract matrix

| Contract area | Coverage | Result |
|---|---|---|
| Runtime/OpenAPI inventory | Dynamic route collection versus OpenAPI path/verb set; no expected total hard-coded | PASS; 117 runtime operations, 117 OpenAPI operations |
| Operation IDs/client parity | Duplicate `operationId` guard plus generated-client ownership/drift check | PASS; 117 unique IDs, 17 generated TypeScript files synchronized |
| Transport/envelope | `/api/v1` surface, success/error envelopes, snake_case, request ID, `Content-Language`, BIGINT/DECIMAL strings | PASS |
| Authentication/authorization | Slice regression coverage for authentication, Resource/Action, domain scope, GROUP/ORGANIZATION resolution, client-context rejection, no System Admin bypass, and invisible/denied target behavior | PASS |
| Query/count safety | Allowlisted `q`, `page`, `page_size`, `sort`, `filter`; malformed input `400`; deterministic `id ASC`; authorized total before pagination | PASS |
| Error taxonomy | `400`, `401`, `403`, `404`, `409`, `422`; stable machine codes; SQL/path/secret leakage absent | PASS |
| Localization | `vi`, `en`, unsupported-locale fallback to `vi`, stable code across locales, `Content-Language` | PASS |
| Serialization | BIGINT identifiers and decimal domain values remain JSON strings | PASS |
| CONTRACT_PENDING absence | Shield User writes, strict Employee gender enum, export media routes, public Price Resolution, COMBO preview, selector/options endpoints | PASS; all absent |

## Validation

| Check | Result |
|---|---|
| `task-context.php P1-API-090` | PASS; `AUTHORITY_CACHE: VALID`, HIGH/api-contract, dependencies P1-API-001..060 |
| `test-db-harness.php inventory` | PASS; `CI_ENVIRONMENT=testing`, database `erp_test`, prefix `dinktech_`, isolation guard PASS |
| `test-db-harness.php rebuild --yes` | PASS twice; framework, Shield/Settings package, and application migrations rebuilt canonical `erp_test`; production `erp` untouched |
| Dedicated `P1Api090ContractRegressionTest.php --no-coverage` | PASS; 5 tests, 56 assertions |
| API slice regression suite (API-001, API-003, API-010, API-020, API-040, API-050, API-060, API-090) | PASS; 35 tests, 265 assertions |
| Authorization/domain regression (`ChangeAuthDomain001AuthorizationTest`, `P1AuthorizationMandatoryRegressionTest`) | PASS; 13 tests, 97 assertions |
| Product/UOM/Pricing/COMBO/Import regression suites | PASS; 115 tests, 784 assertions |
| Full PHPUnit (`test-db-harness.php full-tests`) | PASS; 403 tests, 3654 assertions |
| PHPStan level 8 | PASS; no errors |
| `pnpm run openapi:lint` | PASS |
| `pnpm run openapi:generate:check` | PASS; generated client ownership and drift check passed |
| Frontend type/lint/format/unit/build | PASS; `vue-tsc`, Oxlint/ESLint, Prettier, 2 Vitest tests, production build |
| Frontend E2E | PASS; 3/3 browser smoke tests |
| Context cache, decision/module-map/task registry guards | PASS; `AUTHORITY_CACHE: VALID`, dependency graph acyclic |
| Architecture guard | PASS; 44 application files checked |
| Ledger validator | PASS after closure; 79 DONE tasks, Gate 7 10/10 |
| `git diff --check` | PASS |

## Slice coverage

- API-010 Foundation/Organization/Contact: PASS.
- API-020 Employee Structure, including source-open gender behavior: PASS.
- API-030 Authorization/Workflow: PASS through authorization and full-suite
  regression coverage.
- API-040 Auto Numbering: PASS.
- API-050 Product/UOM/Pricing/COMBO: PASS.
- API-060 Product Import: PASS.
- CHANGE-AUTH-DOMAIN-001 authorization regressions: PASS.

No genuine API contract defect was found, so no runtime/API/OpenAPI/schema,
Resource, Action, migration, or business semantic repair was required. The
only initial failure was caused by concurrently running database PHPUnit
processes against the shared `erp_test`; the guarded canonical rebuild and
serial rerun passed.

## Closure

- Implementation/regression commit: `e9d5ee2ed7b669edba3bf44144da262e9d39a971`.
- Evidence and ledger closure are committed separately after this assessment.
- The authority package remains the older manifest-declared seven-file set.
- The 12 pre-existing newer/draft `docs/architecture/*` files remain
  untouched and unstaged.
- No P1-FE task or Gate 8 task was started.
