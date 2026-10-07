# P1-FE-030 Security / Authorization UI Assessment

## Outcome

`P1-FE-030` implements the contract-ready Security and Authorization
surfaces only. `P1-FE-040` and `P1-FE-050` were not started.

Implemented routes:

- `/security/users` — Table First read-only user list backed by the existing admin read endpoint, with View Details modal. No Shield User create/edit/state or Direct Permission writes are presented because the current API does not expose those operations.
- `/security/authorization-groups` — Table First Authorization Group CRUD/state, modal detail tabs, complete backend Members rendering and all-user member replacement, plus canonical Resource Action permission replacement with Resource/Action/ALLOW detail rows.
- `/security/resources` — Resource CRUD and Resource Action CRUD. New Domain choices are exactly `SYSTEM`, `MASTER`, and `ORGANIZATION`; existing Domain is immutable/read-only and `COMPANY` is absent.
- `/security/policies` — Organization scope, Resource, optional all-state selection, exact participant types `USER|GROUP|POSITION|DEPARTMENT|ORGANIZATION`, and `ALLOW|DENY|ABSOLUTE_DENY` action/effect rows. UI text states that Policy does not grant missing Base Permission.
- `/security/authorization-logs` — read-only backend-scoped list/detail with Request-ID copy support.

The frontend delegates all authorization, Evaluation Organization, visibility,
403/404 handling, and effect semantics to the backend. It does not evaluate
PBAC, infer ALLOW/DENY, widen Organization scope, or special-case System Admin.
The generated client is used through the FE-002 typed transport adapter for API-030
surfaces; the existing admin read service is reused for the read-only User list.
The existing `/admin/users` administration route remains on its pre-task
component; the read-only contract surface is isolated at `/security/users`.

## Validation

| Check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-FE-030` | PASS; `AUTHORITY_CACHE: VALID` |
| Ledger dependency check | PASS; `P1-API-030 DONE`, current Gate-8 prerequisites DONE, `P1-FE-040 NOT_STARTED` |
| `php vendor/bin/phpunit --testsuite App --filter 'P1Auth|P1Api' --no-progress --no-coverage` (from `backend`) | PASS; 80 tests, 499 assertions |
| `pnpm run openapi:generate:check` | PASS; generated client ownership/drift check |
| `pnpm run type-check` | PASS |
| `pnpm run lint:eslint` | PASS |
| `pnpm run format:check` | PASS |
| `pnpm run test:unit` | PASS; 12 files, 26 tests |
| `pnpm run build` | PASS |
| `pnpm exec oxlint <all touched files>` | PASS |
| `pnpm run lint:oxlint` | NOT PASS; pre-existing untouched `src/components/forms/__tests__/SearchableSelect.spec.ts:10` unused `wrapper` |
| `pnpm exec playwright test e2e/fe030-security.spec.ts --project=chromium` | PASS; 4 critical Table First/modal/member/permission/vocabulary/log checks and scoped Axe audit |
| `pnpm exec playwright test --project=chromium` | PASS; 18/18 Chromium regression tests |
| `git diff --check` | PASS |

The Playwright suite uses route fixtures for UI/contract smoke and is not
authenticated runtime proof for backend mutation success. Backend/API
authorization regressions above provide the security regression evidence.

## Impact

- DB: none.
- Authorization: frontend controls remain UX only; API-030 remains the authority.
- API/OpenAPI: no contract change; generated client remains unchanged and existing admin read service is reused for users.
- UI/i18n/accessibility: added vi/en key parity, Security navigation/routes,
  accessible labels, scoped Axe coverage, and shared delete row action support.
