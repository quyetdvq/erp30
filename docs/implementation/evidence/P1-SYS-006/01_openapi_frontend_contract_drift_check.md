# P1-SYS-006 - OpenAPI/frontend contract drift check

Date: 2026-10-09
Task: `P1-SYS-006`
Outcome: **DONE**

## Preflight

- `task-context.php P1-SYS-006`: `AUTHORITY_CACHE: VALID`; catalog and ledger
  validators PASS; live ledger `102/107 DONE`.
- HEAD: `6fa87ab99e2e79e2b197a7a60a1cf0b1c794095f`; branch `main`.
- Gate 7: `15/15 DONE`; Gate 7A foundation is closed (`P1-DSP-001..005
  DONE`); Gate 8 prerequisites are recorded DONE (`P1-FE-001`, `P1-FE-002`,
  `P1-DSP-006`, `P1-DSP-090`, and the feature UI lane).
- `P1-SYS-005` remains **BLOCKED** for the missing authoritative populated
  legacy baseline and standalone Spark `SUPPORTPATH` defect. This task did not
  alter or conceal that blocker.
- Pre-existing unstaged work preserved: the two Pricing files, the SYS-005
  ledger/evidence changes, and no development database was modified.

Authority read: API Design v1.8 §§15-18, Implementation Contract v1.10,
Implementation Plan v1.10 §17, and approved Change Controls 08/09/10/11.
`CONTRACT_PENDING` export media, public Price Resolution, COMBO preview, and
production import-size limits remain excluded.

## Operation-level drift matrix

The matrix compares the normalized CodeIgniter route inventory, canonical
OpenAPI `operationId`, and generated `sdk.gen.ts` export. `PASS` means the
route/method and generated operation are both present; `DRIFT` would be a
missing or extra operation. Inventory: **133 OpenAPI / 133 runtime / 133
generated operations; 0 missing; 0 extra; 0 drift**.

| Method and route | operationId | Runtime | Generated | Drift |
|---|---|---|---|---|
| DELETE `/api/v1/policies/{id}` | `deletePolicy` | PASS | PASS | 0 |
| DELETE `/api/v1/resources/{id}` | `deleteResource` | PASS | PASS | 0 |
| DELETE `/api/v1/resources/{id}/actions/{id}` | `deleteResourceAction` | PASS | PASS | 0 |
| GET `/api/v1/auth/me` | `getCurrentUser` | PASS | PASS | 0 |
| GET `/api/v1/authorization-groups` | `listAuthorizationGroups` | PASS | PASS | 0 |
| GET `/api/v1/authorization-groups/{id}` | `getAuthorizationGroup` | PASS | PASS | 0 |
| GET `/api/v1/authorization-groups/{id}/members` | `listAuthorizationGroupMembers` | PASS | PASS | 0 |
| GET `/api/v1/authorization-groups/{id}/permissions` | `listAuthorizationGroupPermissions` | PASS | PASS | 0 |
| GET `/api/v1/authorization-logs` | `listAuthorizationLogs` | PASS | PASS | 0 |
| GET `/api/v1/authorization-logs/{id}` | `getAuthorizationLog` | PASS | PASS | 0 |
| GET `/api/v1/contacts` | `listContacts` | PASS | PASS | 0 |
| GET `/api/v1/contacts/{id}` | `getContact` | PASS | PASS | 0 |
| GET `/api/v1/departments` | `listDepartments` | PASS | PASS | 0 |
| GET `/api/v1/departments/{id}` | `getDepartment` | PASS | PASS | 0 |
| GET `/api/v1/display-settings/definitions` | `listDisplaySettingDefinitions` | PASS | PASS | 0 |
| GET `/api/v1/display-settings/runtime` | `getDisplaySettingsRuntime` | PASS | PASS | 0 |
| GET `/api/v1/display-settings/values` | `listDisplaySettingValues` | PASS | PASS | 0 |
| GET `/api/v1/employee-assignments` | `listEmployeeAssignments` | PASS | PASS | 0 |
| GET `/api/v1/employee-assignments/{id}` | `getEmployeeAssignment` | PASS | PASS | 0 |
| GET `/api/v1/employees` | `listEmployees` | PASS | PASS | 0 |
| GET `/api/v1/employees/{id}` | `getEmployee` | PASS | PASS | 0 |
| GET `/api/v1/health` | `getHealth` | PASS | PASS | 0 |
| GET `/api/v1/import-jobs` | `listImportJobs` | PASS | PASS | 0 |
| GET `/api/v1/import-jobs/{id}` | `getImportJob` | PASS | PASS | 0 |
| GET `/api/v1/import-jobs/{id}/rows` | `listImportJobRows` | PASS | PASS | 0 |
| GET `/api/v1/lifecycles` | `listLifecycles` | PASS | PASS | 0 |
| GET `/api/v1/lifecycles/{id}` | `getLifecycle` | PASS | PASS | 0 |
| GET `/api/v1/lifecycles/{id}/states` | `listLifecycleStates` | PASS | PASS | 0 |
| GET `/api/v1/lifecycles/{id}/transitions` | `listLifecycleTransitions` | PASS | PASS | 0 |
| GET `/api/v1/organizations` | `listOrganizations` | PASS | PASS | 0 |
| GET `/api/v1/organizations/{id}` | `getOrganization` | PASS | PASS | 0 |
| GET `/api/v1/organizations/tree` | `getOrganizationTree` | PASS | PASS | 0 |
| GET `/api/v1/policies` | `listPolicies` | PASS | PASS | 0 |
| GET `/api/v1/policies/{id}` | `getPolicy` | PASS | PASS | 0 |
| GET `/api/v1/positions` | `listPositions` | PASS | PASS | 0 |
| GET `/api/v1/positions/{id}` | `getPosition` | PASS | PASS | 0 |
| GET `/api/v1/price-lists` | `listPriceLists` | PASS | PASS | 0 |
| GET `/api/v1/price-lists/{id}` | `getPriceList` | PASS | PASS | 0 |
| GET `/api/v1/price-lists/{id}/items` | `listPriceListItems` | PASS | PASS | 0 |
| GET `/api/v1/product-groups` | `listProductGroups` | PASS | PASS | 0 |
| GET `/api/v1/product-groups/{id}` | `getProductGroup` | PASS | PASS | 0 |
| GET `/api/v1/product-uoms` | `listProductUoms` | PASS | PASS | 0 |
| GET `/api/v1/product-uoms/{id}` | `getProductUom` | PASS | PASS | 0 |
| GET `/api/v1/products` | `listProducts` | PASS | PASS | 0 |
| GET `/api/v1/products/{id}` | `getProduct` | PASS | PASS | 0 |
| GET `/api/v1/products/{id}/combo-items` | `listComboItems` | PASS | PASS | 0 |
| GET `/api/v1/products/{id}/uom-conversions` | `listProductUomConversions` | PASS | PASS | 0 |
| GET `/api/v1/provinces` | `listProvinces` | PASS | PASS | 0 |
| GET `/api/v1/provinces/{id}` | `getProvince` | PASS | PASS | 0 |
| GET `/api/v1/resources` | `listResources` | PASS | PASS | 0 |
| GET `/api/v1/resources/{id}` | `getResource` | PASS | PASS | 0 |
| GET `/api/v1/resources/{id}/actions` | `listResourceActions` | PASS | PASS | 0 |
| GET `/api/v1/resources/{id}/numbering-series` | `listNumberingSeries` | PASS | PASS | 0 |
| GET `/api/v1/resources/{id}/numbering-series/{id}` | `getNumberingSeries` | PASS | PASS | 0 |
| GET `/api/v1/security/users` | `listSecurityUsers` | PASS | PASS | 0 |
| GET `/api/v1/users/{id}/permissions` | `getUserPermissions` | PASS | PASS | 0 |
| GET `/api/v1/wards` | `listWards` | PASS | PASS | 0 |
| GET `/api/v1/wards/{id}` | `getWard` | PASS | PASS | 0 |
| GET `/api/v1/workflow-states` | `listWorkflowStates` | PASS | PASS | 0 |
| GET `/api/v1/workflow-states/{id}` | `getWorkflowState` | PASS | PASS | 0 |
| PATCH `/api/v1/auth/profile` | `updateAuthProfile` | PASS | PASS | 0 |
| PATCH `/api/v1/authorization-groups/{id}` | `updateAuthorizationGroup` | PASS | PASS | 0 |
| PATCH `/api/v1/contacts/{id}` | `updateContact` | PASS | PASS | 0 |
| PATCH `/api/v1/departments/{id}` | `updateDepartment` | PASS | PASS | 0 |
| PATCH `/api/v1/display-settings` | `updateDisplaySettings` | PASS | PASS | 0 |
| PATCH `/api/v1/employee-assignments/{id}` | `updateEmployeeAssignment` | PASS | PASS | 0 |
| PATCH `/api/v1/employees/{id}` | `updateEmployee` | PASS | PASS | 0 |
| PATCH `/api/v1/lifecycles/{id}` | `updateLifecycle` | PASS | PASS | 0 |
| PATCH `/api/v1/organizations/{id}` | `updateOrganization` | PASS | PASS | 0 |
| PATCH `/api/v1/policies/{id}` | `updatePolicy` | PASS | PASS | 0 |
| PATCH `/api/v1/positions/{id}` | `updatePosition` | PASS | PASS | 0 |
| PATCH `/api/v1/price-lists/{id}` | `updatePriceList` | PASS | PASS | 0 |
| PATCH `/api/v1/product-groups/{id}` | `updateProductGroup` | PASS | PASS | 0 |
| PATCH `/api/v1/product-uoms/{id}` | `updateProductUom` | PASS | PASS | 0 |
| PATCH `/api/v1/products/{id}` | `updateProduct` | PASS | PASS | 0 |
| PATCH `/api/v1/provinces/{id}` | `updateProvince` | PASS | PASS | 0 |
| PATCH `/api/v1/resources/{id}` | `updateResource` | PASS | PASS | 0 |
| PATCH `/api/v1/resources/{id}/actions/{id}` | `updateResourceAction` | PASS | PASS | 0 |
| PATCH `/api/v1/resources/{id}/numbering-series/{id}` | `updateNumberingSeries` | PASS | PASS | 0 |
| PATCH `/api/v1/users/{id}` | `updateUser` | PASS | PASS | 0 |
| PATCH `/api/v1/wards/{id}` | `updateWard` | PASS | PASS | 0 |
| PATCH `/api/v1/workflow-states/{id}` | `updateWorkflowState` | PASS | PASS | 0 |
| POST `/api/v1/auth/login` | `login` | PASS | PASS | 0 |
| POST `/api/v1/auth/logout` | `logout` | PASS | PASS | 0 |
| POST `/api/v1/authorization-groups` | `createAuthorizationGroup` | PASS | PASS | 0 |
| POST `/api/v1/authorization-groups/{id}/actions/set-state` | `setAuthorizationGroupState` | PASS | PASS | 0 |
| POST `/api/v1/contacts` | `createContact` | PASS | PASS | 0 |
| POST `/api/v1/contacts/{id}/actions/set-state` | `setContactState` | PASS | PASS | 0 |
| POST `/api/v1/departments` | `createDepartment` | PASS | PASS | 0 |
| POST `/api/v1/departments/{id}/actions/set-state` | `setDepartmentState` | PASS | PASS | 0 |
| POST `/api/v1/display-settings/actions/reset` | `resetDisplaySettings` | PASS | PASS | 0 |
| POST `/api/v1/employee-assignments` | `createEmployeeAssignment` | PASS | PASS | 0 |
| POST `/api/v1/employee-assignments/{id}/actions/set-state` | `setEmployeeAssignmentState` | PASS | PASS | 0 |
| POST `/api/v1/employees` | `createEmployee` | PASS | PASS | 0 |
| POST `/api/v1/employees/{id}/actions/set-state` | `setEmployeeState` | PASS | PASS | 0 |
| POST `/api/v1/lifecycles` | `createLifecycle` | PASS | PASS | 0 |
| POST `/api/v1/lifecycles/{id}/actions/set-state` | `setLifecycleState` | PASS | PASS | 0 |
| POST `/api/v1/organizations` | `createOrganization` | PASS | PASS | 0 |
| POST `/api/v1/organizations/{id}/actions/set-state` | `setOrganizationState` | PASS | PASS | 0 |
| POST `/api/v1/policies` | `createPolicy` | PASS | PASS | 0 |
| POST `/api/v1/positions` | `createPosition` | PASS | PASS | 0 |
| POST `/api/v1/positions/{id}/actions/set-state` | `setPositionState` | PASS | PASS | 0 |
| POST `/api/v1/price-lists` | `createPriceList` | PASS | PASS | 0 |
| POST `/api/v1/price-lists/{id}/actions/set-state` | `setPriceListState` | PASS | PASS | 0 |
| POST `/api/v1/product-groups` | `createProductGroup` | PASS | PASS | 0 |
| POST `/api/v1/product-groups/{id}/actions/set-state` | `setProductGroupState` | PASS | PASS | 0 |
| POST `/api/v1/product-uoms` | `createProductUom` | PASS | PASS | 0 |
| POST `/api/v1/product-uoms/{id}/actions/set-state` | `setProductUomState` | PASS | PASS | 0 |
| POST `/api/v1/products` | `createProduct` | PASS | PASS | 0 |
| POST `/api/v1/products/{id}/actions/set-state` | `setProductState` | PASS | PASS | 0 |
| POST `/api/v1/products/import-jobs` | `startProductImport` | PASS | PASS | 0 |
| POST `/api/v1/provinces` | `createProvince` | PASS | PASS | 0 |
| POST `/api/v1/provinces/{id}/actions/set-state` | `setProvinceState` | PASS | PASS | 0 |
| POST `/api/v1/resources` | `createResource` | PASS | PASS | 0 |
| POST `/api/v1/resources/{id}/actions` | `createResourceAction` | PASS | PASS | 0 |
| POST `/api/v1/resources/{id}/numbering-series` | `createNumberingSeries` | PASS | PASS | 0 |
| POST `/api/v1/resources/{id}/numbering-series/{id}/actions/set-state` | `setNumberingSeriesState` | PASS | PASS | 0 |
| POST `/api/v1/resources/{id}/numbering-series/actions/validate-config` | `validateNumberingSeriesConfig` | PASS | PASS | 0 |
| POST `/api/v1/users` | `createUser` | PASS | PASS | 0 |
| POST `/api/v1/users/{id}/actions/reset-password` | `resetUserPassword` | PASS | PASS | 0 |
| POST `/api/v1/users/{id}/actions/set-state` | `setUserState` | PASS | PASS | 0 |
| POST `/api/v1/wards` | `createWard` | PASS | PASS | 0 |
| POST `/api/v1/wards/{id}/actions/set-state` | `setWardState` | PASS | PASS | 0 |
| POST `/api/v1/workflow-states` | `createWorkflowState` | PASS | PASS | 0 |
| POST `/api/v1/workflow-states/{id}/actions/set-state` | `setWorkflowStateState` | PASS | PASS | 0 |
| PUT `/api/v1/authorization-groups/{id}/members` | `replaceAuthorizationGroupMembers` | PASS | PASS | 0 |
| PUT `/api/v1/authorization-groups/{id}/permissions` | `replaceAuthorizationGroupPermissions` | PASS | PASS | 0 |
| PUT `/api/v1/lifecycles/{id}/states` | `configureLifecycleStates` | PASS | PASS | 0 |
| PUT `/api/v1/lifecycles/{id}/transitions` | `configureLifecycleTransitions` | PASS | PASS | 0 |
| PUT `/api/v1/price-lists/{id}/items` | `replacePriceListItems` | PASS | PASS | 0 |
| PUT `/api/v1/products/{id}/combo-items` | `replaceComboItems` | PASS | PASS | 0 |
| PUT `/api/v1/products/{id}/uom-conversions` | `replaceProductUomConversions` | PASS | PASS | 0 |
| PUT `/api/v1/users/{id}/permissions` | `replaceUserPermissions` | PASS | PASS | 0 |

## Contract semantic checks

| Contract | Evidence | Result |
|---|---|---|
| BIGINT/DECIMAL strings, snake_case, success/error envelopes | `P1Api001TransportTest`, `P1Api003ListQueryTest`, `P1Api090ContractRegressionTest` | PASS |
| Request ID and locale headers | `P1Api001TransportTest` (UUID fallback, `X-Request-ID`, `Content-Language`, vi fallback) | PASS |
| 400/401/403/404/409/422 stable mapping and non-leaking errors | `P1Api001TransportTest`, `P1Api090ContractRegressionTest` | PASS |
| Authorization-safe pagination and totals | `P1Api003ListQueryTest`, `P1Api090ContractRegressionTest` | PASS |
| Base Permission snapshot and Shield-only User/direct permission boundary | `P1Auth005SecurityAdministrationTest`, frontend `basePermission.spec.ts` | PASS |
| Policy scope uniqueness/409 and atomic action replacement | `P1Auth001PolicyRepositoryTest`, `P1Auth005SecurityAdministrationTest`, `P1AuthorizationMandatoryRegressionTest` | PASS |
| `GET /resources?include=actions` additive representation | OpenAPI schema/query inspection and `P1Api090ContractRegressionTest` | PASS |
| Display Settings runtime/admin set-reset boundary | `P1Dsp004DisplaySettingsApiTest` | PASS |
| Product-only Import, two-gate authorization, pending surfaces excluded | `P1Api060ProductImportTest`, `P1Api090ContractRegressionTest` | PASS |

## Validation

| Command | Result |
|---|---|
| `php vendor/bin/phpunit --no-coverage` | PASS - 440 tests, 3,909 assertions |
| Focused API/overlay PHPUnit lane | PASS - 37 tests, 298 assertions |
| Focused Policy/Shield/mandatory authorization lane | PASS - 21 tests, 102 assertions |
| `php vendor/bin/phpstan analyse --configuration phpstan.neon --level=8 --no-progress` | PASS |
| `pnpm run openapi:lint` | PASS (Redocly) |
| `pnpm run openapi:generate:check` | PASS - deterministic; 17 generated files tool-owned |
| `pnpm run type-check` | PASS |
| `pnpm run lint` | PASS (Oxlint + ESLint) |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS - Vite production build |
| `pnpm run test:unit -- --run` | PASS - 15 files, 40 tests |
| `architecture-guard.php` | PASS - 47 application files |
| `validate-context-cache.php` / `validate-ledger.php` | PASS - 107 tasks, 102 DONE |
| `git diff --check` | PASS |

No API/OpenAPI/frontend source drift was found, so no production code or
generated client was changed. No development database or migration was
modified. P1-SYS-005 remains BLOCKED and P1-SYS-007 is the next release task;
P1-SYS-006 does not authorize starting it.
