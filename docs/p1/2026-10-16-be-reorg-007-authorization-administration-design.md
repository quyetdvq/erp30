# BE-REORG-007 — Authorization Administration Design

**Date:** 2026-10-16
**Status:** Written spec — awaiting explicit written-spec approval
**Selected approach:** A — physically relocate Authorization Group / Policy administration; retain the PBAC decision engine
**Input checkpoint:** BE-REORG-006 + FIX01 + FIX02 (accepted)
**Accepted runtime baseline:** PHPUnit **866 tests / 7,740 assertions**, all existing gates executed without skips; PHPStan **121 inherited diagnostics**, exit code 1. The complete diagnostic block must remain byte-identical, not merely have the same error count.

## 1. Purpose and non-goals

Move existing Authorization Group and Policy administrative application logic, validation, and persistence into a coherent canonical module without changing any authorization decision, DB concurrency rule, HTTP contract, Shield integration, or workflow behavior. This is a **physical reorganization**, not a permissions redesign or an API expansion.

The old public `App\Services`, `App\Repositories`, and `App\Authorization` class names remain as explicit compatibility entrypoints. In particular, `App\Authorization\AuthorizationService` must retain its source and constructor wiring. Existing callers continue to instantiate the legacy concrete `PolicyRepository` and `AuthorizationGroupRepository` unchanged.

## 2. Concrete source boundary

### 2.1 In scope (canonical implementation ownership)

| Current class | Proposed canonical ownership | Existing role |
|---|---|---|
| `App\Services\AuthorizationGroupService` | `Modules/Authorization/Administration/Groups/Application/AuthorizationGroupService.php` | Group create/edit/inactivate, membership and permission mutation, direct user permissions, authorized user-creation group assignment |
| `App\Services\PolicyAdministrationService` | `Modules/Authorization/Administration/Policies/Application/PolicyAdministrationService.php` | Authorized Policy create/update/delete with action validation |
| `App\Authorization\PolicyValidator` | `Modules/Authorization/Administration/Policies/Validation/PolicyValidator.php` | Policy fields, resource/state and action/effect validation |
| `App\Authorization\PolicyParticipantValidator` | `Modules/Authorization/Administration/Policies/Validation/PolicyParticipantValidator.php` | Participant type / target checks |
| `App\Repositories\AuthorizationGroupRepository` | `Modules/Authorization/Administration/Groups/Infrastructure/Persistence/SqlAuthorizationGroupRepository.php` | `groups`, `group_permissions`, Shield membership-table primitives |
| `App\Repositories\PolicyRepository` | `Modules/Authorization/Administration/Policies/Infrastructure/Persistence/SqlPolicyRepository.php` | Policy and action reads/writes **including read methods currently called by the PBAC decision engine** |

The `PolicyRepository` currently contains both administrative mutations and **PBAC decision-time reads** (`findApplicablePolicyRows`, policy-action lookups, SQL policy pagination, state-scoped policy checks). These methods must move mechanically with the same SQL to canonical persistence and stay available through the **unchanged legacy public repository**. This design does **not** claim that PolicyRepository is exclusively an administration reader; it is the shared persistence bridge. Moving it is allowed, changing the decision algorithm or query semantics is not.

### 2.2 Target file structure

```text
backend/app/
├── Bootstrap/
│   └── AuthorizationAdministrationFactory.php
└── Modules/Authorization/Administration/
    ├── Groups/
    │   ├── Application/
    │   │   ├── AuthorizationGroupService.php
    │   │   └── Ports/GroupRepositoryPort.php
    │   └── Infrastructure/Persistence/
    │       └── SqlAuthorizationGroupRepository.php
    └── Policies/
        ├── Application/
        │   ├── PolicyAdministrationService.php
        │   └── Ports/PolicyAdministrationRepositoryPort.php
        ├── Validation/
        │   ├── PolicyValidator.php
        │   └── PolicyParticipantValidator.php
        └── Infrastructure/Persistence/
            └── SqlPolicyRepository.php
```

Each canonical service uses narrow repository ports for the **administration** operations it consumes. The SQL implementation may expose the wider legacy PolicyRepository read/write surface, since authorization callers already require it; do not move the PBAC decision algorithm into this module or create a duplicate copy of it.

### 2.3 Retained legacy compatibility classes

These six `final` classes keep their exact namespace, class name, `public` constructor parameter names/order/types/defaults, named-argument compatibility, constants, public method names/signatures/return types, error mapping, and observable lazy construction behavior:

```text
App\Services\AuthorizationGroupService
App\Services\PolicyAdministrationService
App\Authorization\PolicyValidator
App\Authorization\PolicyParticipantValidator
App\Repositories\AuthorizationGroupRepository
App\Repositories\PolicyRepository
```

Use **explicit method-by-method delegation** to composition-owned canonical instances. No `__call`, concrete inheritance, class aliases, reflection dispatch, re-creating an injected collaborator with a different DB, or changing the public constructor's concrete type hints. A wrapper constructor must not perform additional SQL/authorization/Shield writes compared with the old constructor. Preserve the existing default/lazy collaborator behavior; validate against existing tests, including supplied collaborators.

Existing constants should resolve to the same values with no extra public names. Check method and constant parity via Reflection on old and new source snapshots and with unit tests; compare callable semantics, not whether a parameter was implemented as a promoted property.

## 3. Integration boundaries that must not change

**Byte-identical retained source (excluding freshly added tests and the BE007 runner):**

```text
backend/app/Authorization/AuthorizationService.php
backend/app/Authorization/ShieldIdentityAdapter.php
backend/app/Authorization/EffectiveProfileResolver.php
backend/app/Authorization/EvaluationOrganizationResolver.php
backend/app/Services/Api030AuthorizationWorkflowService.php
backend/app/Services/ShieldUserIdentityService.php
backend/app/Services/ResourceWorkflowStateResolver.php
backend/app/Services/Api050ProductCatalogService.php
backend/app/Services/Api060ProductImportService.php
backend/app/Controllers/Api/AuthorizationWorkflow.php
backend/app/Config/Routes.php
backend/app/Config/Services.php
backend/app/Database/Migrations/*
backend/app/Database/Seeds/*
frontend/*
API/OpenAPI contracts/*
```

Do not alter `AuthorizationService`'s evaluation order, allow/deny precedence, base permission checks, employee/assignment eligibility, resource-state checks, read-only per-request batching, scope intersection, or `ABSOLUTE_DENY` behavior. Any newly discovered coupling that requires modifying these callers is a design blocker to resolve before touching out-of-scope source, not tacit permission to widen the patch.

The existing API-030 surface remains identical, including `authorization-groups`, `policies`, membership and permission routes, policy actions, set-state, and dependent user/Shield routes. Do not add or remove HTTP operations, query filters, response fields, status codes, error codes, request IDs, or authorization actions. User identity/password/avatar administration and Workflow/Lifecycle are outside scope despite sharing the API030 controller/facade.

## 4. Canonical ports and factory rules

### 4.1 `GroupRepositoryPort`

Expose exactly the application-level Group operations consumed by its service:

```php
find(int $id): ?array
findForUpdate(int $id): ?array
findByCode(string $code): ?array
insert(array $data, ?OperationContext $operation = null): int
update(int $id, array $data, ?OperationContext $operation = null): bool
findPermissions(int $groupId): array
findResourceAction(int $resourceActionId): ?array
userExists(int $userId): bool
replacePermissions(int $groupId, array $resourceActionIds, ?OperationContext $operation = null): bool
```

Legacy repository and canonical SQL repository implement this port. Preserve all *additional* legacy repository methods, including `findAll`, `findMembers`, and `replaceMembers`, on both legacy facade and canonical SQL repository because API-030 calls them directly. There is no requirement to add those methods to the narrow application port.

### 4.2 `PolicyAdministrationRepositoryPort`

Expose the application/validator operations it actually consumes:

```php
find(int $id): ?array
assertScopeAvailable(array $scope, ?int $excludePolicyId = null, bool $lock = false): void
participantTargetExists(string $participantType, int $participantRefId): bool
insertWithActions(array $policy, array $actions, ?OperationContext $operation = null): int
update(int $id, array $data, ?OperationContext $operation = null): bool
replaceWithActions(int $policyId, array $policy, array $actions, ?OperationContext $operation = null): bool
deleteWithActions(int $policyId, ?OperationContext $operation = null): bool
```

The canonical SQL class also retains **every other** existing legacy public `PolicyRepository` method, including decision-engine reads, `setStatus`, policy-action CRUD, SQL policy lists, and low-level mutations. Keep those methods off the narrow application port unless an existing application dependency requires them. Validate the complete public facade through Reflection inventory and legacy tests. If actual method signatures differ from this summary, the original source signatures prevail; the implementation plan must copy them exactly, including PHPDoc contracts.

### 4.3 `AuthorizationAdministrationFactory`

Use a stateless composition factory, not a singleton or global mutable container. Factory methods assemble canonical Group/Policy services and repositories with a **single supplied `ConnectionInterface`**, preserving any explicitly supplied legacy collaborator instances and `MutationContext`, `Clock`, `AuthorizationService`, `ShieldIdentityAdapter`, `PolicyValidator`, and `PolicyParticipantValidator` in their appropriate compatibility boundary.

When `db` is absent, retain current `db_connect()` default semantics. **Do not** accidentally instantiate an injected repository against a new connection or turn a caller-provided validator into a new default validator. Authorization still delegates decisions to the existing `AuthorizationService`. Shield identity/membership/direct-permission changes still go through the existing `ShieldIdentityAdapter` where the service currently uses it.

Avoid circular factory creation: canonical services may depend on the existing authorization evaluator and on repository ports; authorization evaluator continues to depend on legacy `PolicyRepository`; the legacy repository delegates to SQL persistence **without** instantiating authorization application services.

## 5. Immutable business, error, authorization, and DB semantics

### 5.1 Authorization Group

- Preserve `code` uppercase/trim normalization, uniqueness checking, `name` and `status` validation, default `ACTIVE`, audit columns, non-empty update behavior, and exact error codes/constants.
- A denied operation must leave Group, Shield memberships, `group_permissions`, and direct user permissions unchanged. Keep existing ordering of authorization vs reads/validation where observable.
- `assignMember()` uses Shield identity adapter's `setGroupMembership`; `assignDirectPermission()` uses its `setDirectPermission` with `<lower-resource>.<lower-action>` mapping. Do not replace these with independent direct SQL or conflate with the repository's `replaceMembers()` primitive.
- `assertAssignableForUserCreation(..., lock: true)` must lock the Group row with the same `FOR UPDATE` behavior inside the caller's user-creation transaction, preventing concurrent inactivation.
- `replacePermissions()` remains one transaction: lock Group row `FOR UPDATE`, clear-and-reinsert deduplicated action IDs, full rollback on error, no partial permission matrix, and the same audit/error semantics. Existing real-DB two-process concurrency coverage remains an acceptance gate.
- `replaceMembers()` preserves its current transaction, Shield `groups_users` mapping, lowercase group code, audit timestamps, and rollback/error handling.

### 5.2 Policy and actions

- Preserve five-dimensional scope: `(organization_id, resource_id, state_id, participant_type, participant_ref_id)`; `NULL` state means all states. The generated `normalized_state_id` database column is never emitted to API callers nor accepted in mutation payloads.
- Preserve scope-check query and `FOR UPDATE` behavior inside `insertWithActions`, `replaceWithActions`; the existing unique database constraint is the final concurrency guard. Duplicate-scope exceptions must map to the same `POLICY_SCOPE_ALREADY_EXISTS` domain error.
- `insertWithActions`, `replaceWithActions`, and `deleteWithActions` remain atomic transactions with the original order of read/lock, update/delete, action insertion, commit/rollback, `resetTransStatus`, and exception mapping. Partial Action persistence is forbidden.
- Preserve participant type vocabulary `USER / GROUP / POSITION / DEPARTMENT / ORGANIZATION`, target table checks and normalized positive IDs; do not introduce a `ROLE` alias or change missing-target semantics.
- Preserve resource/action membership validation and effect vocabulary `ALLOW / DENY / ABSOLUTE_DENY`, including normalization and exact errors. `ABSOLUTE_DENY` semantics stay owned by the decision engine.
- Policy set-state CAS via `setStatus(..., $expectedStatus)` preserves affected-rows concurrency detection, audit writes and `ConcurrencyException` behavior.
- Preserve `PolicyRepository`'s SQL-list branch, visibility buckets, `BINARY status` handling, State-filter behavior, count-before-pagination, authorized-read batching and policy-action hydration ordering. These are sensitive PBAC **reads**, even though the repository is being relocated.
- Preserve `findApplicablePolicyRows`, `findPolicyActionsForPolicies` and all other public decision reads byte-semantic parity: filters, joins, `ORDER BY`, null behavior and return shapes cannot change.

### 5.3 No new platform capabilities

No tables/indexes/migrations, policy actions, audit providers, background jobs, caches, authorization bypass, new locking strategies, configuration files, or frontend changes. Avoid recording sensitive credentials in test logs. Do not weaken global PHPStan configuration or PHPUnit coverage gates.

## 6. Verification and regression design

### 6.1 Existing authoritative test families

Retain and run in the **full backend suite**:

```text
P1Auth001PolicyRepositoryTest
P1Auth002AuthorizationServiceTest
P1Auth003AuthorizationDecisionTest
P1Auth004AssignmentEligibilityTest
P1Auth004AuthorizationAuditTest
P1Auth005SecurityAdministrationTest
P1AuthorizationMandatoryRegressionTest
P1Perf004ReadOnlyAuthorizationContextTest
P1Perf005AuthorizedBucketListTest
ChangeAuthDomain001AuthorizationTest
ChangePolicyScopeUniqueness001MigrationTest
P1Seed003PrivilegedAuthorizationGroupTest
P1Db050ResourceAuthorizationNumberingTest
```

Preserve all other existing tests, including API030, Product, Numbering, Settings, Foundation/Organization/Contact/EmployeeStructure, and the existing two-process Group permission contention test under `P1Auth005SecurityAdministrationTest`. Do not run database-refreshing tests concurrently against `127.0.0.1:3306/erp_test` (`dinktech_` prefix).

### 6.2 Only BE007-specific new tests

Add a small bounded set for:

1. Module path/namespace and no dependency on API/controller source, and no PBAC evaluation logic moved into the module.
2. Legacy six-class constructors, named arguments, full method signatures, constants, and injected-collaborator delegation parity.
3. Same-DB factory wiring, lazy construction/no hidden writes, and fail-closed authorization port.
4. Canonical SQL repository versus legacy repository parity for critical Policy decision reads, including state/null/scope, SQL pagination and action ordering.
5. **Real in-process HTTP** (not fake service invocation): Group list/create, member/permission mutation, Policy create/list/update/delete or state paths, denied actor paths. Use correct GROUP organization root and effective employee assignment via `EligibleActorFixture`, not just `group_permissions`; assert exact HTTP status/response envelope and absence of unauthorized writes.
6. No new DB locks beyond existing methods; retain existing real concurrent permission test, policy scope uniqueness and rollback tests rather than duplicate them.

The plan must inventory the exact preexisting test cases before choosing the small number of new cases. No test-count reduction or transitional-test retirement in BE007; consolidation belongs to the final dedicated phase.

### 6.3 Guarded runner and acceptance

Add `backend/tools/test-authorization-administration.php`, modeled on the accepted BE006 runner. Require `--confirm-test-db`, enforce the exact test connection/prefix and serial execution, run **one full PHPUnit process** and only after it passes **one full PHPStan process**, and report coverage gates for BE007 and all prior checkpoints. A failed test stops before PHPStan. Do not silently mark missing/unexecuted tests as passing.

- **Before BE007:** 866/866 tests and 7,740 assertions; no skipped cases in existing gates.
- **After BE007:** 866 + the actual, inventoried number of new BE007 cases; 0 PHPUnit failures/errors/skips on all required gates.
- PHPStan: the **same exact 121 inherited diagnostics** as BE006_FIX02, no new or removed diagnostics; PHPStan exits 1 and must be reported honestly.
- Full in-process HTTP + real MySQL test DB lock/rollback behavior are required; this does not claim browser/Apache UAT.
- PHP lint of every modified/new PHP file, `git diff --check`, constructor/public method/constant parity, forbidden-file byte-hash scan and applying/reversing incremental patch on an independently reconstructed BE006_FIX02 checkpoint must pass.
- The deliverable patch is **incremental** relative to `BE-REORG-006_FIX02`; never reapply old BE006 patches or overwrite user modifications.

## 7. Risks and explicit mitigations

1. **PBAC reads share `PolicyRepository` with admin writes.** Migrate entire SQL persistence body mechanically; preserve exact public legacy façade and read semantics. Verify against `AuthorizationService` and SQL-list regression tests.
2. **Group Shield integration differs from repository membership replacement.** Keep both original flows, avoid moving ShieldIdentityAdapter, preserve lowercase mapping and transactional semantics.
3. **Lazy collaborator instantiation and DB ownership.** Test explicit injection, default constructors and mixed supplied/default collaborators to prevent unintended connections/side effects.
4. **Concurrent membership/permission and Policy scope writes.** Retain exact `FOR UPDATE`, transaction boundaries, unique-constraint mapping and real MySQL concurrency tests.
5. **HTTP test fixture context.** Seed PBAC eligibility completely (group root, employee/assignment), and assert API030's actual JSON structure, not invented envelopes.

## 8. Delivery process and approval gates

1. Approve this **written spec** separately; approach A approval authorizes writing this spec only.
2. Create a task-by-task implementation plan with exact source signatures, RED→GREEN tests, tasks, and Windows runtime commands. Review and approve that plan and execution method (Native was used previously but is not implicitly a new plan approval).
3. Implement in an isolated workspace with per-task evidence and whole-branch review; produce minimal incremental BE007 patch and package.
4. Apply on Windows accepted BE006_FIX02 checkpoint; verify full PHPUnit + PHPStan exact diagnostics; fix any regression with scoped FIX patches before declaring BE007 accepted.

**No production implementation is authorized by the present spec-writing step.**
