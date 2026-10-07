# P1-FE-020 Employee/Assignment UI Assessment

## Outcome

Implemented the Slice 2 UI only: Departments, Positions, Employees, and
Employee Assignments. The implementation reuses FE-001/002/003 shell, typed
generated API client, DSP-005 presentation runtime, and FE-010 ContactList.
No schema, API/OpenAPI, manager-history, Employee-import, or FE-030 work was
added. The Departments and Positions screens now follow the
`settings/locations/wards` list/table toolbar pattern, and Employee detail
opens as a large responsive modal matching the supplied visual direction.

## Screens

- `/people/departments`: hierarchical Department list with parent, create/edit,
  and state actions; no Organization owner field.
- `/people/positions`: Position list with level, create/edit, and state
  actions; no Department/Organization owner field.
- `/people/employees`: server-side q/status/organization/department/position
  filters, Employee form/detail, manager selector, canonical gender mapping,
  and state actions.
- Employee detail tabs: Overview, Assignments, Contacts, Audit.
- Employee detail opens from the list action in a large modal with an explicit
  accessible close action; nested Employee/Assignment forms remain operable
  above the detail modal.
- Assignments: active/history views, Organization/Department/Position,
  Primary/Secondary, effective dates, status, create/edit/state actions, and
  Primary overlap hint. Assignment calls remain Organization-authorized API
  operations; Secondary rows remain visible and are not described as ignored
  for authorization.

## Invariants

- Gender options are exactly `MALE`, `FEMALE`, `OTHER`, and `null`, rendered as
  `Nam / Male`, `Nữ / Female`, `Khác / Other`, and
  `Chưa khai báo / Not specified`.
- Manager self-selection, inactive-manager selection, and local cycle paths are
  blocked before submit; backend validation remains authoritative.
- Primary effective-period overlap is surfaced locally and rejected before
  submit; backend overlap/concurrency validation remains authoritative.
- Employee organization/department/position filters are query data filters;
  the UI does not send an evaluation-organization override for Employee
  MASTER/GROUP operations.
- Canonical IDs and `YYYY-MM-DD` date inputs are passed through unchanged.

## Validation

| Command/check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php --batch P1-API-020 P1-DSP-090 P1-FE-020` | PASS; `AUTHORITY_CACHE: VALID`, DEC-P1-001 resolved |
| `pnpm exec vue-tsc --noEmit -p tsconfig.app.json` | PASS |
| `pnpm run lint:eslint -- --no-cache` | PASS |
| `pnpm run format:check` | PASS |
| `pnpm run test:unit -- --run` | PASS; 11 files / 24 tests |
| `pnpm run build` | PASS |
| `pnpm run openapi:generate:check` | PASS; generated artifacts unchanged |
| `vendor/bin/phpunit --no-coverage tests/unit/P1Api020EmployeeStructureTest.php tests/unit/P1Emp003EmployeeAssignmentServiceTest.php tests/unit/P1AuthorizationMandatoryRegressionTest.php` | PASS; 18 tests / 108 assertions |
| `pnpm exec playwright test e2e/fe001-shell.spec.ts e2e/fe010-slice1.spec.ts e2e/dsp006-display-settings.spec.ts e2e/fe020-employee.spec.ts --project=chromium` | PASS; 11 tests |
| `pnpm exec vitest run src/modules/__tests__/employeeRules.spec.ts` | PASS; 2 tests |
| `git diff --check` | PASS |

### Visual-refinement revalidation

| Command/check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-FE-020` | PASS; `AUTHORITY_CACHE: VALID`, `P1-API-020`/`P1-DSP-090` dependencies DONE, DEC-P1-001 resolved |
| `pnpm exec prettier --check src/views/PeopleDepartmentsView.vue src/views/PeoplePositionsView.vue src/views/PeopleEmployeesView.vue e2e/fe020-employee.spec.ts` | PASS |
| `pnpm exec vue-tsc --noEmit -p tsconfig.app.json` | PASS |
| `pnpm exec eslint src/views/PeopleDepartmentsView.vue src/views/PeoplePositionsView.vue src/views/PeopleEmployeesView.vue --no-cache` | PASS |
| `pnpm run build` | PASS |
| `pnpm run lint:eslint -- --no-cache` | PASS |
| `pnpm run test:unit -- --run` | PASS; 11 files / 24 tests |
| `pnpm run openapi:generate:check` | PASS; generated artifacts unchanged |
| `pnpm exec playwright test e2e/fe020-employee.spec.ts --project=chromium` | PASS; 2 tests |
| `pnpm exec playwright test e2e/fe001-shell.spec.ts e2e/fe010-slice1.spec.ts e2e/dsp006-display-settings.spec.ts e2e/fe020-employee.spec.ts --project=chromium` | PASS; 11 tests |
| `php vendor/bin/phpunit --no-coverage tests/unit/P1Api020EmployeeStructureTest.php tests/unit/P1Emp003EmployeeAssignmentServiceTest.php tests/unit/P1AuthorizationMandatoryRegressionTest.php` (from `backend/`) | PASS; 18 tests / 108 assertions |
| `pnpm run format:check` | PASS; normalized formatting in the FE-020 manager-invariant test |
| `git diff --check` | PASS |

## Contract / scope notes

- DB: no changes.
- Authorization: UI sends only generated API operations; backend remains the
  authority for 403/404, GROUP Employee master scope, and Assignment
  Organization scope.
- API/OpenAPI: no public contract changes; generated-client ownership check
  passed.
- UI/i18n: vi/en parity was maintained for all new keys; business identifiers
  and enum codes remain canonical.
- `P1-FE-030` was not started.
