# P1-FE-030 Security / Authorization UI Assessment

## Outcome

`P1-FE-030` implements the contract-ready Security and Authorization
surfaces only. `P1-FE-040` and `P1-FE-050` were not started.

Implemented routes:

- `/security/users` — Table First read-only user list backed by the dedicated `GET /api/v1/security/users` aggregate endpoint. The backend returns each visible Shield User once with the full name from `auth_identities.name`, email, status, Authorization Group summaries, and direct-permission count, then applies the standard search/filter/sort/pagination contract. The frontend makes one generated-client request for the table and opens detail from the loaded row; it does not call `auth/me`, join Employee data, or call any per-group Members endpoint to build the list. No Shield User create/edit/state or Direct Permission writes are presented because those API contracts remain pending.
- `/security/authorization-groups` — Table First Authorization Group CRUD/state, modal detail tabs, complete backend Members rendering and all-user member replacement, plus canonical Resource Action permission replacement with Resource/Action/ALLOW detail rows.
- `/security/resources` — Resource CRUD and Resource Action CRUD. New Domain choices are exactly `SYSTEM`, `MASTER`, and `ORGANIZATION`; existing Domain is immutable/read-only and `COMPANY` is absent.
- `/security/policies` — Organization scope, Resource, optional all-state selection, exact participant types `USER|GROUP|POSITION|DEPARTMENT|ORGANIZATION`, and `ALLOW|DENY|ABSOLUTE_DENY` action/effect rows. UI text states that Policy does not grant missing Base Permission.
- `/security/authorization-logs` — read-only backend-scoped list/detail with Request-ID copy support.

The frontend delegates all authorization, Evaluation Organization, visibility,
403/404 handling, and effect semantics to the backend. It does not evaluate
PBAC, infer ALLOW/DENY, widen Organization scope, or special-case System Admin.
The generated client is used through the FE-002 typed transport adapter for all
API-030 surfaces. The dedicated Security Users endpoint is read-only and
authorizes each returned row through the canonical `user.view` PBAC action; no
new `/api/v1/users` or `/api/v1/admin/users` endpoint was introduced.
The existing `/admin/users` administration route remains on its pre-task
component; the read-only contract surface is isolated at `/security/users`.

## Validation

| Check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-FE-030` | PASS; `AUTHORITY_CACHE: VALID` |
| Ledger dependency check | PASS; `P1-API-030 DONE`, current Gate-8 prerequisites DONE, `P1-FE-040 NOT_STARTED` |
| `php -l` changed backend files | PASS; repository, service, controller, and routes |
| `pnpm run openapi:lint` | PASS |
| `pnpm run openapi:generate:check` | PASS; 17 generated TypeScript files verified |
| `php vendor/bin/phpunit --testsuite App --filter 'P1Auth|P1Api' --no-progress --no-coverage` (from `backend`) | PASS; 80 tests, 499 assertions |
| `pnpm run type-check` | PASS |
| `pnpm run lint:eslint` | PASS |
| `pnpm run format:check` | PASS |
| `pnpm run test:unit` | PASS; 12 files, 26 tests |
| `pnpm run build` | PASS |
| `pnpm exec oxlint <all touched frontend files>` | PASS |
| `pnpm run lint:oxlint` | NOT PASS; pre-existing untouched `src/components/forms/__tests__/SearchableSelect.spec.ts:10` unused `wrapper` |
| `pnpm exec playwright test e2e/fe030-security.spec.ts --project=chromium` | PASS; 4 critical Table First/modal/member/permission/vocabulary/log checks, aggregate-user request assertion, and scoped Axe audit |
| `pnpm exec playwright test --project=chromium` | PASS; 18/18 Chromium regression tests |
| `php vendor/bin/phpstan analyse --memory-limit=1G` (from `backend`) | PASS; 262 files |
| `git diff --check` | PASS |

The Playwright suite uses route fixtures for UI/contract smoke and is not
authenticated runtime proof for backend mutation success. Backend/API
authorization regressions above provide the security regression evidence.

## Impact

- DB: none.
- Authorization: frontend controls remain UX only; API-030 remains the authority.
- API/OpenAPI: added contract-ready `GET /api/v1/security/users` with backend aggregation and generated client/types; the projection reads `auth_identities.name` and avoids the unused Employee JOIN. Shield User writes/password/state remain API-contract pending.
- UI/i18n/accessibility: added vi/en key parity, Security navigation/routes,
  accessible labels, scoped Axe coverage, and shared delete row action support.
