# P1-API-030 Authorization/Workflow API Assessment

## Scope

Implementation commit: `8267f31`

Implemented the CONTRACT_READY API surface for Authorization Group and its
members/permissions, Resource and Resource Actions, Policy, Authorization Log,
Workflow State, and Lifecycle state/transition configuration. Shield User write
APIs, authorization-log export, Resource Instance Authorization, and P1-API-040+
surfaces remain out of scope.

The implementation delegates PBAC decisions to `AuthorizationService` and
reuses `AuthorizationGroupService`, `PolicyAdministrationService`,
`LifecycleService`, and their repositories. SYSTEM and MASTER resources resolve
at GROUP; Policy uses `policy.organization_id`; Authorization Log uses its
persisted organization with NULL falling back to GROUP. Authorization is
performed for every candidate log row before list filters, totals, and
pagination. Resource creation requires `domain`, rejects `COMPANY`, and domain
is immutable on update. Policy action replacement and Lifecycle configuration
remain transactional through the existing services.

## Validation

| Check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-API-030` | PASS; `AUTHORITY_CACHE: VALID` |
| `php vendor/bin/phpunit --testsuite App --filter 'P1Auth|P1Wf|P1Api' --no-progress` | PASS; 77 tests, 387 assertions |
| `vendor/bin/phpunit --testsuite App --no-progress` | PASS; 386 tests, 3,484 assertions |
| `vendor/bin/phpstan analyse --level=8 --no-progress` | PASS; no errors |
| `pnpm run openapi:lint` | PASS |
| `pnpm run openapi:generate:check` | PASS; 17 generated TypeScript files owned by generator |
| `pnpm run type-check` | PASS |
| `pnpm run lint:oxlint` | PASS |
| `pnpm run lint:eslint` | PASS |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS |
| `git diff --check` | PASS |

The full PHPUnit run emitted the repository's existing missing coverage-driver
warning; the test result itself passed.

## Files

- `backend/app/Config/Routes.php`
- `backend/app/Controllers/Api/AuthorizationWorkflow.php`
- `backend/app/Services/Api030AuthorizationWorkflowService.php`
- `contracts/openapi/openapi.yaml`
- regenerated `frontend/src/api/generated/*`
- this evidence file and `docs/implementation/P1_EXECUTION_LEDGER.md`

No migration or authority document was changed. P1-API-040 was not started.
