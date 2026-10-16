# BE-REORG-007 Authorization Administration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Physically relocate the existing Authorization Group and Policy administration/services, validators, and SQL persistence into `App\Modules\Authorization\Administration` with explicit legacy delegation, preserving all PBAC decision reads, Shield mutations, API-030 behavior, concurrency invariants, and inherited PHPStan diagnostics.

**Architecture:** Canonical Group/Policy application code depends on narrow repository ports; canonical SQL repositories retain **all** legacy read/write methods. Six legacy final classes remain explicit method-by-method facades with the same public constructors, signatures and constant values. A stateless factory composes services against a single DB without changing the PBAC evaluator or API callers.

**Tech Stack:** PHP 8.3 target (Windows runtime PHP 8.3.30), CodeIgniter 4 + Shield, MySQL/MariaDB, PHPUnit 10.5.65, PHPStan. Implementation snapshot may use a different local PHP version for lint; only the guarded Windows run establishes runtime acceptance.

**Spec:** `docs/superpowers/specs/2026-10-16-be-reorg-007-authorization-administration-design.md`

## Global Constraints

- Source checkpoint: **BE-REORG-006 + FIX01 + FIX02**, accepted. Baseline: **866 PHPUnit tests, 7,740 assertions; exactly 121 inherited PHPStan diagnostics (exit 1)**. Do not confuse diagnostic-count equality with exact diagnostic-set equality.
- No feature changes, schema/migrations/seeds, new locks, new policies/actions, authorization bypasses, alternate Shield writes, caches, or changes to API-030/Numbering/Product/Workflow/Frontend behavior.
- These six legacy `final` classes retain every public method, constructor parameter name/order/type/default, named arguments, public constant value, PHPDoc typing, and lazy dependency semantics: `App\Services\AuthorizationGroupService`, `App\Services\PolicyAdministrationService`, `App\Authorization\PolicyValidator`, `App\Authorization\PolicyParticipantValidator`, `App\Repositories\AuthorizationGroupRepository`, `App\Repositories\PolicyRepository`. Use explicit delegates, **not** inheritance, `__call`, aliases, or reflection dispatch. Canonical method bodies must preserve behavior, not merely satisfy signatures.
- Retain original `App\Authorization\AuthorizationService` and PBAC decision logic byte-identical. Crucially, its concrete `PolicyRepository` constructor injection and **all decision-time repository methods** continue working.
- Preserve five-part Policy scope `(organization_id, resource_id, state_id, participant_type, participant_ref_id)`, `NULL` state, generated `normalized_state_id` exclusion, `BINARY status` comparisons, policy/action ordering, SQL pagination total-before-limit, `ABSOLUTE_DENY`, and `POLICY_SCOPE_ALREADY_EXISTS` duplicate mapping.
- Preserve Group normalization and uniqueness, Shield `setGroupMembership`/`setDirectPermission`, direct-permission naming, `replaceMembers`, `replacePermissions` atomicity and `FOR UPDATE`, user-create `findForUpdate`, Policy scope-lock/transaction/CAS `setStatus`, rollback and `resetTransStatus` exactly.
- Factory is stateless; caller-supplied collaborators are never replaced or rebound to another DB. Defaults retain `db_connect()` and laziness as observable in the six legacy classes. Authorization always flows through the existing evaluator, never a permissive default.
- Byte-identical retained sources: `app/Authorization/AuthorizationService.php`, `ShieldIdentityAdapter.php`, `EffectiveProfileResolver.php`, `EvaluationOrganizationResolver.php`; `app/Services/Api030AuthorizationWorkflowService.php`, `ShieldUserIdentityService.php`, `ResourceWorkflowStateResolver.php`, `Api050ProductCatalogService.php`, `Api060ProductImportService.php`; `app/Controllers/Api/AuthorizationWorkflow.php`; `app/Config/Routes.php`, `Services.php`; full `app/Database/Migrations/`, `app/Database/Seeds/`, frontend and API/OpenAPI contract trees. Only the six explicit legacy facades, canonical module/factory, BE007-specific tests and guarded runner may change.
- Real test DB is loopback `127.0.0.1:3306/erp_test` with `dinktech_` table prefix. `DatabaseTestTrait` can refresh tables: **never run concurrent PHPUnit/database test processes**. Real two-process contention *inside the existing designated test* must still execute.
- Baseline test families are authoritative; add only **24 BE007 relocation-specific tests** (4 boundary + 6 compatibility + 4 SQL parity + 4 factory + 6 HTTP), not duplicate PBAC business characterization. Full suite expected **890** if no unrelated additions. Count/skip gate must check JUnit evidence, not just stdout totals.
- Never weaken PHPStan configuration/baseline/ignores, PHPUnit flags, security, domain invariants, or HTTP assertions to get green. The inherited 121 diagnostics may remain but the normalized diagnostic block must match BE006_FIX02 **byte-for-byte**.
- Local source archive lacks `backend/vendor` and uses PHP 8.4 for lint. A static/local probe is useful evidence but is **not** a substitute for PHP 8.3 + real MySQL PHPUnit/PHPStan verification on Windows. Deliver a **candidate**, not an accepted checkpoint, until those logs are checked.

## Review Focus

1. **Injected collaborators and lazy evaluation** — supplied `PolicyValidator`, repository, `AuthorizationService`, `ShieldIdentityAdapter`, `MutationContext` and `Clock` must remain intact; default constructors must not perform extra SQL/mutations. Pin in Task 6: `testInjectedCollaboratorsRemainOwnedAndAreNotRebuilt` and `testFactoryDefaultsAreLazyAndFailClosed`.
2. **Mixed DB connections** — supplying one connection must not compose Group/Policy/participant/catalog/state reads against `db_connect()` from another group. Pin in Task 6: `testFactoryUsesOneSuppliedConnectionAcrossDefaults`.
3. **Read-time PBAC policy query drift** — legacy evaluator still sees exact Policy rows, `NULL` state handling, `BINARY ACTIVE` and ordered effects. Pin in Task 2: `testApplicablePoliciesMatchLegacyAcrossNullAndConcreteState` plus mandatory P1Auth002/P1Perf005 tests in Task 7.
4. **Transaction partial writes/locks** — simultaneous Group permission replacements must serialize; Policy duplicate-scope/action failures must roll back. Pin with unchanged P1Auth005 concurrent test and ChangePolicyScopeUniqueness tests in Task 7's mandatory JUnit gate; Task 2 parity cases additionally exercise live policy-action reads.
5. **API-030 PBAC fixture and transport mistakes** — actor requires GROUP organization root, active employee/effective assignment; collection `meta.total` is **flat**, not `meta.pagination.total`; denied responses must have correct status/error with zero writes. Pin in Task 7's six real-route tests.

---

## File Map (relative to repo root)

**Create canonical (nine implementation files):**

- `backend/app/Modules/Authorization/Administration/Groups/Application/Ports/GroupRepositoryPort.php` — only nine Group service operations listed in Task 1.
- `backend/app/Modules/Authorization/Administration/Policies/Application/Ports/PolicyAdministrationRepositoryPort.php` — only seven Policy application/validation operations listed in Task 1.
- `backend/app/Modules/Authorization/Administration/Groups/Infrastructure/Persistence/SqlAuthorizationGroupRepository.php` — all 12 original public Group SQL methods; original private SQL helpers copied mechanically.
- `backend/app/Modules/Authorization/Administration/Policies/Infrastructure/Persistence/SqlPolicyRepository.php` — all 25 original public Policy SQL methods including PBAC read-time methods; original private SQL helpers copied mechanically.
- `backend/app/Modules/Authorization/Administration/Policies/Validation/PolicyParticipantValidator.php` — canonical participant vocabulary/lookup validation.
- `backend/app/Modules/Authorization/Administration/Policies/Validation/PolicyValidator.php` — canonical resource, state and action/effect validation.
- `backend/app/Modules/Authorization/Administration/Groups/Application/AuthorizationGroupService.php` — canonical application group mutations.
- `backend/app/Modules/Authorization/Administration/Policies/Application/PolicyAdministrationService.php` — canonical Policy mutations.
- `backend/app/Bootstrap/AuthorizationAdministrationFactory.php` — stateless assembly of both application roots and validation/repository dependencies.

**Modify exactly six legacy facades:**

- `backend/app/Repositories/AuthorizationGroupRepository.php`
- `backend/app/Repositories/PolicyRepository.php`
- `backend/app/Authorization/PolicyParticipantValidator.php`
- `backend/app/Authorization/PolicyValidator.php`
- `backend/app/Services/AuthorizationGroupService.php`
- `backend/app/Services/PolicyAdministrationService.php`

**Create BE007 tests and runner:**

- `backend/tests/unit/BeReorg007AuthorizationBoundaryTest.php` — 4 cases.
- `backend/tests/unit/BeReorg007AuthorizationCompatibilityTest.php` — 6 cases.
- `backend/tests/database/BeReorg007AuthorizationPolicySqlParityTest.php` — 4 cases.
- `backend/tests/unit/BeReorg007AuthorizationFactoryTest.php` — 4 cases (use guarded real DB only where necessary).
- `backend/tests/database/BeReorg007AuthorizationHttpTest.php` — 6 cases.
- `backend/tools/test-authorization-administration.php` — one launcher that registers BE007 inventory and includes `test-foundation.php` once.
- Modify `backend/tools/test-foundation.php` solely to add BE007 preflight, 24-case/JUnit existing-91 gate, version label/header and coverage reporting; **no additional PHPUnit/PHPStan subprocess**.

**Exact baseline public method inventory to preserve:** Group repository **12**, Policy repository **25**, Group service **8**, Policy service **3**, Policy validator **3**, Participant validator **2**, plus the six constructors. Reflection must capture method visibility/static/type, constructor names/order/defaults, and 6+10+6+3+2+1 public error constants; counts are informational, compare actual complete inventories rather than relying on counts alone. `PolicyRepository::findApplicablePolicyRows` carries `@param list<int> $ancestorOrganizationIds` and returns `list<array{policy: array<string,mixed>,effect:string}>`; preserve this and all other PHPDocs to avoid PHPStan drift.

## Existing authoritative case inventory (source checked)

The following count is **test methods found in the 13 specified preexisting classes**, not an inferred complete-application test count; any new dataset-expanded JUnit count must be independently inventoried rather than invented:

| Existing test class | Cases | File under `backend/tests/` |
|---|---:|---|
| `P1Auth001PolicyRepositoryTest` | 14 | `unit/` |
| `P1Auth002AuthorizationServiceTest` | 17 | `unit/` |
| `P1Auth003AuthorizationDecisionTest` | 3 | `unit/` |
| `P1Auth004AssignmentEligibilityTest` | 11 | `unit/` |
| `P1Auth004AuthorizationAuditTest` | 5 | `unit/` |
| `P1Auth005SecurityAdministrationTest` | 11 | `unit/` |
| `P1AuthorizationMandatoryRegressionTest` | 9 | `unit/` |
| `P1Perf004ReadOnlyAuthorizationContextTest` | 4 | `unit/` |
| `P1Perf005AuthorizedBucketListTest` | 3 | `unit/` |
| `ChangeAuthDomain001AuthorizationTest` | 4 | `unit/` |
| `ChangePolicyScopeUniqueness001MigrationTest` | 2 | `database/` |
| `P1Seed003PrivilegedAuthorizationGroupTest` | 4 | `database/` |
| `P1Db050ResourceAuthorizationNumberingTest` | 4 | `database/` |
| **Subtotal** | **91** | Existing authority gate |

`P1Auth005SecurityAdministrationTest::testGroupPermissionReplacementSerializesTwoIndependentConnections` is the existing designated real-DB contention test. The full suite **also** includes other API030, Product, Workflow, Numbering, Settings and previous BE-REORG families; do not replace it with this 91-case subset.

---

### Task 1: Narrow canonical ports and module boundary

**Files:** Create both `*/Application/Ports/*RepositoryPort.php`; create `backend/tests/unit/BeReorg007AuthorizationBoundaryTest.php` (4 tests).

**Interfaces — produces:**

```php
// Groups/Application/Ports/GroupRepositoryPort.php
public function find(int $id): ?array;
public function findForUpdate(int $id): ?array;
public function findByCode(string $code): ?array;
public function insert(array $data, ?OperationContext $operation = null): int;
public function update(int $id, array $data, ?OperationContext $operation = null): bool;
public function findPermissions(int $groupId): array;
public function findResourceAction(int $resourceActionId): ?array;
public function userExists(int $userId): bool;
public function replacePermissions(int $groupId, array $resourceActionIds, ?OperationContext $operation = null): bool;
// Policies/Application/Ports/PolicyAdministrationRepositoryPort.php
public function find(int $id): ?array;
public function assertScopeAvailable(array $scope, ?int $excludePolicyId = null, bool $lock = false): void;
public function participantTargetExists(string $participantType, int $participantRefId): bool;
public function insertWithActions(array $policy, array $actions, ?OperationContext $operation = null): int;
public function update(int $id, array $data, ?OperationContext $operation = null): bool;
public function replaceWithActions(int $policyId, array $policy, array $actions, ?OperationContext $operation = null): bool;
public function deleteWithActions(int $policyId, ?OperationContext $operation = null): bool;
```

Use original repository PHPDocs on the port methods (`@param array<string,mixed>`, `@return array<string,mixed>|null`, lists, etc.). No wider public admin port, no PBAC evaluator methods on the port. Canonical SQL and both legacy repository facades will implement their respective port in Task 2.

- [ ] **Step 1 — Write four failing unit tests** in `BeReorg007AuthorizationBoundaryTest`:
  - `testCanonicalPortsMatchSpecAndExistingSignatures`: `ReflectionClass` asserts exact method name/signature/default inventory of both ports; 9 and 7 operations respectively; `OperationContext` types and defaults unchanged.
  - `testCanonicalModuleDoesNotEmbedAuthorizationDecisionEngine`: recursively scan module PHP files (use `RecursiveDirectoryIterator`, not nonrecursive `glob('**')`); exclude legitimate `AuthorizationService` references but reject copied `AuthorizationDecision`, evaluation precedence/`ABSOLUTE_DENY` implementation or controller/API030 declarations.
  - `testPolicySharedReadBoundaryRemainsExplicitlyOutsideApplicationPorts`: `!method_exists(PolicyAdministrationRepositoryPort::class,'findApplicablePolicyRows')`, `!method_exists(...,'findAuthorizedPolicyPage')`; decision evaluator source hash matches checkpoint.
  - `testOutOfScopeCallerPathsRemainOutsideModule`: assert module files contain no controller/routes/Shield adapter reimplementation; expected retained paths exist (full byte-hash in Task 8).
- [ ] **Step 2 — RED**: `cd backend && php vendor/bin/phpunit --configuration phpunit.dist.xml tests/unit/BeReorg007AuthorizationBoundaryTest.php`; expected FAIL (canonical ports absent), not a bootstrap/DB failure.
- [ ] **Step 3 — Implement ports** with exact method signatures above and return/parameter annotations from source; do not create repositories or factories yet.
- [ ] **Step 4 — GREEN**: rerun same 4 cases; expected 4/4 PASS; lint new ports (`php -l path/to/file.php`).
- [ ] **Step 5 — Commit** only these files: `git commit -m "refactor(authorization): define administration ports"`.

### Task 2: SQL repository relocation with full decision-read parity

**Files:** Create both canonical SQL repositories; modify both legacy repositories; create `backend/tests/database/BeReorg007AuthorizationPolicySqlParityTest.php` (4) and start `backend/tests/unit/BeReorg007AuthorizationCompatibilityTest.php` (2 of eventual 6).

**Interfaces — consumes:** Task 1 ports. **Produces:**

```php
new SqlAuthorizationGroupRepository(?ConnectionInterface $db = null);
new SqlPolicyRepository(?ConnectionInterface $db = null);
new AuthorizationGroupRepository(?ConnectionInterface $db = null); // unchanged concrete facade
new PolicyRepository(?ConnectionInterface $db = null);             // unchanged concrete facade
```

Legacy Group retains all 12 public functions including `findAll(array $filters = []): array`, `findMembers(string $groupCode): array`, `replaceMembers(string $groupCode, array $userIds, ?OperationContext $operation = null): bool`. Legacy Policy retains **all 25** public functions; pin exact source signatures including `findApplicablePolicyRows(int $resourceId, array $ancestorOrganizationIds, int $resourceActionId, ?int $stateId): array`, `findPolicyVisibilityBuckets(int $maxBuckets = 64): ?array`, `supportsSqlPolicyList(CommonListQuery $query): bool`, `findAuthorizedPolicyPage(CommonListQuery $query, array $allowedBuckets): array`, `findPolicyActionsForPolicies(array $policyIds, int $resourceActionId): array`, `setStatus(int $policyId, string $status, OperationContext $operation, ?string $expectedStatus = null): bool`, and every CRUD alias. Preserve PHPDoc precisely.

- [ ] **Step 1 — Write six failing tests before changing repositories.** Two compatibility cases:
  - `testLegacyGroupRepositoryKeepsAllMethodsConstantsAndExplicitDelegation`: baseline Reflection snapshot of 12 names, parameters/types/defaults, six public constants and `findAll`/`findMembers`/`replaceMembers`; verify canonical class and concrete delegation exist.
  - `testLegacyPolicyRepositoryKeepsAllDecisionReadsAndConstants`: baseline Reflection snapshot of 25 names, parameters/types/defaults, ten public constants, `findApplicablePolicyRows`/`findAuthorizedPolicyPage`/`setStatus`; verify class is a real explicit delegate.
  Four real-DB SQL parity cases:
  - `testApplicablePoliciesMatchLegacyAcrossNullAndConcreteState`: insert state-NULL/state-specific cases, compare legacy and canonical results for matching and nonmatching `stateId`; assert exact `policy`+`effect` fields and stable ID/action ordering.
  - `testVisibilityBucketsAndStatusBytesMatchLegacy`: compare `findPolicyVisibilityBuckets()` incl malformed-case status fallback to `null`; no case-insensitive authorization broadening.
  - `testAuthorizedSqlListMatchesTotalFiltersAndPagination`: build real `CommonListQuery` using actual `CommonListQueryAdapter`/allowlist; compare `supportsSqlPolicyList`/`findAuthorizedPolicyPage` with ID, status, state filters and `page/pageSize`; verify `normalized_state_id` absent and `"01"` filter disallowed.
  - `testPolicyActionHydrationAndParticipantTargetReadsMatchLegacy`: compare `findPolicyActionsForPolicies`, `findAllPolicyActionsForPolicies`, `participantTargetExists` across both delegates; assert sorted action rows and the five canonical participant types.
- [ ] **Step 2 — RED**: run `php vendor/bin/phpunit --configuration phpunit.dist.xml tests/database/BeReorg007AuthorizationPolicySqlParityTest.php tests/unit/BeReorg007AuthorizationCompatibilityTest.php`; expected failures because canonical repositories/delegation absent.
- [ ] **Step 3 — Copy original SQL bodies mechanically** to canonical `Sql*Repository` files, changing only namespace/use/interface. Convert both legacy final repository classes to explicitly forward *every* public method, keeping constructor `?ConnectionInterface $db = null` and its original DB resolution timing, all constants and original method PHPDocs. No `__call`, no alternate query rewriting, no standalone SQL bodies left in facades. Ensure both canonical and legacy implement their port. `FOR UPDATE`, scope uniqueness, transactions, error mapping, audit and `resetTransStatus` unchanged. Existing PBAC evaluator continues instantiating concrete `PolicyRepository`.
- [ ] **Step 4 — GREEN**: rerun six BE007 cases and existing `P1Auth001PolicyRepositoryTest`, `P1Auth002AuthorizationServiceTest`, `P1Perf005AuthorizedBucketListTest`, `ChangePolicyScopeUniqueness001MigrationTest`; expected PASS (especially SQL-list decision-time behavior).
- [ ] **Step 5 — Lint + commit**: `git commit -m "refactor(authorization): relocate full group and policy SQL persistence"`.

### Task 3: Relocate Policy validators, preserve injected repository and resource-state semantics

**Files:** Create canonical `Policies/Validation/PolicyParticipantValidator.php` and `Policies/Validation/PolicyValidator.php`; change legacy `app/Authorization/PolicyParticipantValidator.php` and `PolicyValidator.php` to explicit facades; add two tests to `BeReorg007AuthorizationCompatibilityTest.php` (total now 4/6).

**Interfaces:** canonical participant validator constructor takes `PolicyAdministrationRepositoryPort $repository`. Canonical main validator constructor takes `PolicyAdministrationRepositoryPort $repository`, `?\App\Authorization\PolicyParticipantValidator $participants = null`, `?ResourceCatalogRepository $catalog = null`, `?ResourceWorkflowStateResolver $workflowStates = null`. Retain legacy concrete constructors **exactly**:

```php
new \App\Authorization\PolicyParticipantValidator(PolicyRepository $repository);
new \App\Authorization\PolicyValidator(
    PolicyRepository $repository,
    ?\App\Authorization\PolicyParticipantValidator $participants = null,
    ?ResourceCatalogRepository $catalog = null,
    ?ResourceWorkflowStateResolver $workflowStates = null,
);
```

Use canonical participant as default within canonical main validator only at method invocation; accept supplied legacy participants without rebuilding. Canonical validators do not evaluate PBAC (only validate input). No new public compatibility methods/const names.

- [ ] **Step 1 — Write two RED compatibility tests**:
  - `testParticipantValidatorKeepsTypeVocabularyAndNamedConstructor`: assert exact constructor `repository` name/type, methods `validate`/`validatePolicy`, `ERROR_INVALID_PARTICIPANT`, `USER|GROUP|POSITION|DEPARTMENT|ORGANIZATION` accepted where targets exist, `ROLE` rejected.
  - `testPolicyValidatorKeepsActionEffectsAndDependencies`: assert constructor name/type/default parity and `validatePolicy`/`validateAction`/`validateActions`; effect normalization `ALLOW`, `DENY`, `ABSOLUTE_DENY`; supplied `participants`, `catalog`, `workflowStates` identities preserved (through behavior), not replaced.
- [ ] **Step 2 — RED**: run `php vendor/bin/phpunit --configuration phpunit.dist.xml tests/unit/BeReorg007AuthorizationCompatibilityTest.php`; new tests fail because canonical validators/wrappers absent.
- [ ] **Step 3 — Mechanically relocate original validators**; legacy final facades hold explicit canonical delegates and forward each public method. Retain constants and PHPDoc return shapes exactly. Preserve `PositiveId`, participant-target lookup, resource-action membership check, state resolution error mapping and validation order. Default collaboration remains lazy. Keep original `PolicyValidator` injected concrete repository argument (named `repository`).
- [ ] **Step 4 — GREEN**: rerun compatibility cases plus existing `P1Auth001PolicyRepositoryTest` and `P1Auth005SecurityAdministrationTest`; expected PASS.
- [ ] **Step 5 — Lint + commit**: `git commit -m "refactor(authorization): relocate policy validation"`.

### Task 4: Relocate Group administrative application service

**Files:** Create canonical `Groups/Application/AuthorizationGroupService.php`; modify `app/Services/AuthorizationGroupService.php` to explicit facade; add `testLegacyGroupServiceRetainsAllMutationAndAuthorizationSignatures` to `BeReorg007AuthorizationCompatibilityTest.php` (now 5/6).

**Interfaces — consumes:** `GroupRepositoryPort` and legacy concrete `AuthorizationService`, `ShieldIdentityAdapter`, `MutationContext`, `Clock`; preserves legacy ctor:

```php
public function __construct(
    ?AuthorizationGroupRepository $repository = null,
    ?AuthorizationService $authorization = null,
    ?ShieldIdentityAdapter $identities = null,
    ?ConnectionInterface $db = null,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
);
```

Canonical constructor follows the **same parameter names/order** but accepts `?GroupRepositoryPort $repository` in position 1. Canonical `repo(): GroupRepositoryPort` lazily instantiates `SqlAuthorizationGroupRepository($this->db)`. Public canonical/legacy mutation methods (8): `create`, `update`, `inactivate`, `assignMember`, `assertAssignableForUserCreation`, `replacePermissions`, `assignPermission`, `assignDirectPermission` with exact baseline signatures as recorded from `app/Services/AuthorizationGroupService.php:44–174`.

- [ ] **Step 1 — Write RED test** `testLegacyGroupServiceRetainsAllMutationAndAuthorizationSignatures`: full Reflection parity (six constants, constructor including named args, eight methods), supplied actor/Shield collaborator behavior, `assertAssignableForUserCreation(..., lock: true)` uses Group row lock, denial causes zero Shield/group permission writes (reuse P1Auth005 for broader cases).
- [ ] **Step 2 — RED**: run `php vendor/bin/phpunit --configuration phpunit.dist.xml tests/unit/BeReorg007AuthorizationCompatibilityTest.php`; expected newly added test FAIL before canonical service exists.
- [ ] **Step 3 — Copy authorization/mutation body mechanically** to canonical Group service and make legacy facade delegate all eight methods. Keep original ordering of `operation()`, normalization, PBAC calls, duplicate check, `setGroupMembership`, `setDirectPermission`, `FOR UPDATE`; no repository `replaceMembers` substitution for Shield adapter membership operations. Preserve old `db_connect()` timing; when injected repository is passed use that exact instance. Any proposed change in construction timing is a review blocker, not permission to erase a test.
- [ ] **Step 4 — GREEN**: rerun compatibility plus full `P1Auth005SecurityAdministrationTest` including two-process permission replacement and `P1AuthorizationMandatoryRegressionTest`; expect PASS.
- [ ] **Step 5 — Lint + commit**: `git commit -m "refactor(authorization): relocate group administration service"`.

### Task 5: Relocate Policy administrative application service

**Files:** Create canonical `Policies/Application/PolicyAdministrationService.php`; modify `app/Services/PolicyAdministrationService.php` into explicit facade; add `testLegacyPolicyServicePreservesValidatorInjectionAndMutationOrder` to `BeReorg007AuthorizationCompatibilityTest.php` (now 6/6).

**Interfaces — consumes:** `PolicyAdministrationRepositoryPort` and existing `AuthorizationService`; supplied `\App\Authorization\PolicyValidator` stays supported without replacement. Canonical main validator may be used for defaults; preserve original validators' callable contract and lazy construction. Legacy constructor **exact**:

```php
public function __construct(
    ?PolicyRepository $repository = null,
    ?PolicyValidator $validator = null,
    ?AuthorizationService $authorization = null,
    ?ConnectionInterface $db = null,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
);
```

Canonical constructor mirrors the same order/names/defaults, using `?PolicyAdministrationRepositoryPort $repository` and `?PolicyValidator $validator` (the injected legacy validator; do not silently replace). For its default validator path, instantiate canonical `Policies/Validation/PolicyValidator` lazily with the *same* port repository and `ResourceCatalogRepository($this->db)` / `ResourceWorkflowStateResolver($this->db)`; do **not** eagerly create them in the legacy wrapper constructor. Use a private return type of `\App\Authorization\PolicyValidator|\App\Modules\Authorization\Administration\Policies\Validation\PolicyValidator` if no separate validation interface is needed; avoid new public ports outside approved structure. Public methods exactly `create(array $policy, array $actions, ?OperationContext $operation = null): int`, `update(int $id, array $policy, ?array $actions = null, ?OperationContext $operation = null): bool`, `delete(int $id, ?OperationContext $operation = null): bool`.

- [ ] **Step 1 — Write RED** `testLegacyPolicyServicePreservesValidatorInjectionAndMutationOrder`: original constructor names/parameter semantics, constants, 3 method signatures; missing Actions reject with `POLICY_ACTIONS_REQUIRED` before writes, authorization denial preserves `POLICY_AUTHORIZATION_DENIED` and zero policy/action writes, supplied validator remains honored.
- [ ] **Step 2 — RED**: run compatibility test file; expected FAIL because canonical application service/delegate absent.
- [ ] **Step 3 — Move original create/update/delete/authorize/shape body** to canonical service, replacing only storage access with policy port. Keep validation and authorization order; original `assertScopeAvailable` checks, optional action-null updates, transactional `insertWithActions`/`replaceWithActions`/`deleteWithActions`, and all error codes intact. Legacy facade explicitly delegates all three public methods, no new side effects.
- [ ] **Step 4 — GREEN**: rerun compatibility, P1Auth001, P1Auth005, ChangePolicyScopeUniqueness tests; expect PASS.
- [ ] **Step 5 — Lint + commit**: `git commit -m "refactor(authorization): relocate policy administration service"`.

### Task 6: Stateless `AuthorizationAdministrationFactory` with one-connection ownership

**Files:** Create `backend/app/Bootstrap/AuthorizationAdministrationFactory.php`; create `backend/tests/unit/BeReorg007AuthorizationFactoryTest.php` (4 tests).

**Interfaces — produces (all stateless static methods):**

```php
public static function group(
    ?ConnectionInterface $db = null,
    ?GroupRepositoryPort $repository = null,
    ?AuthorizationService $authorization = null,
    ?ShieldIdentityAdapter $identities = null,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
): \App\Modules\Authorization\Administration\Groups\Application\AuthorizationGroupService;

public static function policy(
    ?ConnectionInterface $db = null,
    ?PolicyAdministrationRepositoryPort $repository = null,
    ?\App\Authorization\PolicyValidator $validator = null,
    ?AuthorizationService $authorization = null,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
): \App\Modules\Authorization\Administration\Policies\Application\PolicyAdministrationService;

public static function groupRepository(?ConnectionInterface $db = null): SqlAuthorizationGroupRepository;
public static function policyRepository(?ConnectionInterface $db = null): SqlPolicyRepository;
public static function policyValidator(
    PolicyAdministrationRepositoryPort $repository,
    ?\App\Authorization\PolicyParticipantValidator $participants = null,
    ?ResourceCatalogRepository $catalog = null,
    ?ResourceWorkflowStateResolver $workflowStates = null,
    ?ConnectionInterface $db = null,
): \App\Modules\Authorization\Administration\Policies\Validation\PolicyValidator;
```

Do not cache instances in statics; `private __construct()` prevents factory instance creation. Factory methods pass the provided DB to defaults **without** replacing explicit collaborators. If `db` is omitted, retain `db_connect()` behavior. For a DB-supplied validator factory, default `ResourceCatalogRepository($db)` and `ResourceWorkflowStateResolver($db)` are composed against the same connection; do not instantiate them if the caller supplied replacements. Canonical services must not create new `AuthorizationService()` without the supplied connection.

- [ ] **Step 1 — Write four failing tests**:
  - `testFactoryUsesOneSuppliedConnectionAcrossDefaults`: supplied test DB used by Group/Policy SQL, default authorization evaluator and validator catalog/state readers; no off-target DB connection or test fixture mutation in constructor.
  - `testInjectedCollaboratorsRemainOwnedAndAreNotRebuilt`: factory methods preserve the exact supplied legacy repositories/validators/authorization/Shield/Clock/MutationContext through real observable behavior.
  - `testFactoryDefaultsAreLazyAndFailClosed`: invoking denied actor mutation through a default factory returns domain DENIED and has no writes to groups/policies/group_permissions/Shield tables; constructor itself has no mutation side effect.
  - `testFactoryIsStatelessAndLegacyConstructorsKeepNamedArgs`: each factory invocation produces distinct canonical root; instantiate all six legacy facades with known named constructor args and assert no new default collaborator creation/writes before method call.
- [ ] **Step 2 — RED**: run `php vendor/bin/phpunit --configuration phpunit.dist.xml tests/unit/BeReorg007AuthorizationFactoryTest.php`; expected 4 failures because factory absent.
- [ ] **Step 3 — Create factory and wire canonical defaults** using the signatures above; resolve one `ConnectionInterface` per factory root. Do not implement a separate authorization port with a permissive fallback, singleton or mutable service locator; pass real `AuthorizationService` to canonical roots or let them lazily construct it using their owned DB.
- [ ] **Step 4 — GREEN**: run factory (4), compatibility (6), P1Auth005 and P1Auth002; expected all PASS.
- [ ] **Step 5 — Lint + commit**: `git commit -m "refactor(authorization): add stateless administration factory"`.

### Task 7: Real API-030 HTTP contract + single-run guarded verification

**Files:** Create `backend/tests/database/BeReorg007AuthorizationHttpTest.php` (6); create `backend/tools/test-authorization-administration.php`; minimally extend `backend/tools/test-foundation.php` to recognize BE007 and enforce JUnit inventory. Leave `Controllers/Api/AuthorizationWorkflow.php`, routes, API030 service and all PBAC source byte-identical.

**Interfaces and tested real routes:**

```text
GET    /api/v1/authorization-groups
POST   /api/v1/authorization-groups
GET    /api/v1/authorization-groups/{id}/members
PUT    /api/v1/authorization-groups/{id}/members
GET    /api/v1/authorization-groups/{id}/permissions
PUT    /api/v1/authorization-groups/{id}/permissions
POST   /api/v1/policies
GET    /api/v1/policies
PATCH  /api/v1/policies/{id}
DELETE /api/v1/policies/{id}
POST   /api/v1/policies/{id}/actions/set-state
```

Use exact API-030 payloads: Group `{code,name,status?}`, members `{user_ids:[...]}`, permissions `{resource_action_ids:[...]}`, Policy `{organization_id,resource_id,state_id,participant_type,participant_ref_id,actions:[{resource_action_id,effect}]}`, policy update with Actions optional, set-state `{status:'INACTIVE'}`. For policy `DELETE`, expect **204 No Content**, not JSON. Successful collections have `meta.page`, `meta.page_size`, `meta.total` and `meta.request_id`; other success responses have `data` and `meta.request_id`. Error mapping comes from `ApiErrorMapper` (`DENIED` -> 403, code stable). Never hardcode `meta.pagination`.

- [ ] **Step 1 — Write six RED HTTP tests** in `BeReorg007AuthorizationHttpTest`:
  - `testGroupListAndCreateHaveRealHttpEnvelopes`: HTTP GET 200 flat `meta.total`; HTTP POST 201 and normalized `data.code` persisted. Pin Request-ID propagation.
  - `testGroupMembersAndPermissionsMutateThroughAuthorizedRoutes`: authorized PUT/GET members and permissions return 200; assert expected `auth_groups_users` lowercased group code and `group_permissions` DB rows; validate permission replacement rather than additive behavior.
  - `testPolicyCreateAndListPreserveActionsAndFlatMeta`: POST 201, GET 200, `data.actions` effect `ALLOW`, no generated `normalized_state_id`, collection total correct.
  - `testPolicyUpdateAndDeleteAreAtomicThroughRoutes`: PATCH 200 with updated actions, DELETE 204 empty body; assert policy/action rows removed, no orphan Actions.
  - `testPolicySetStateReturnsOnlyIdAndStatus`: POST 200 `data` exactly `id,status`, no leaked full row; DB `status` now `INACTIVE` (CAS itself covered by existing tests).
  - `testDeniedActorCannotWriteGroupsMembersPermissionsOrPolicies`: actor lacking action permissions gets 403 with established domain error code; compare pre/post row counts for Groups, Policy/Actions, Shield memberships and permissions to prove zero writes.
- [ ] **Step 2 — RED**: run `php vendor/bin/phpunit --configuration phpunit.dist.xml tests/database/BeReorg007AuthorizationHttpTest.php`; before final wiring, the tests must expose missing module behavior or contract drift; do not accept a RED caused by incorrect fixtures. Use `RuntimeGuard::assertConfiguration`, `FeatureTestTrait`, `AuthenticationTesting`, `RequestContext::reset`, `resetServices`, JSON/CSRF header, and `EligibleActorFixture::assignAtOrganization()`; create one `organizations` GROUP root (ACTIVE, `parent_id` NULL) per fixture, active employee/effective assignment and accurately seeded resource_actions/memberships. Derive statuses/envelopes from actual `Api030AuthorizationWorkflowService`/`ApiTransport`, not guesses.
- [ ] **Step 3 — Resolve any code defect in the canonical layer only**; do not fix a fixture by broadening authorization, and do not change retained API030/controller/routes. If all HTTP tests pass before a production edit, record them as regression/contract assertions; do not force a synthetic failure.
- [ ] **Step 4 — GREEN**: run six HTTP tests and then the 13 existing authority families **serially in one PHPUnit process**. They must pass without skips. Add/extend runner below and verify `php -l`.
- [ ] **Step 5 — Wire BE007 JUnit gate**:

```php
// backend/tools/test-authorization-administration.php (include BE006 constants exactly as in test-numbering.php)
const BE_REORG_AUTHORIZATION_ADMIN_CASES = [
    'BeReorg007AuthorizationBoundaryTest' => 4,
    'BeReorg007AuthorizationCompatibilityTest' => 6,
    'BeReorg007AuthorizationPolicySqlParityTest' => 4,
    'BeReorg007AuthorizationFactoryTest' => 4,
    'BeReorg007AuthorizationHttpTest' => 6,
];
const BE_REORG_AUTHORIZATION_EXISTING_CASES = [
    'P1Auth001PolicyRepositoryTest' => 14, 'P1Auth002AuthorizationServiceTest' => 17,
    'P1Auth003AuthorizationDecisionTest' => 3, 'P1Auth004AssignmentEligibilityTest' => 11,
    'P1Auth004AuthorizationAuditTest' => 5, 'P1Auth005SecurityAdministrationTest' => 11,
    'P1AuthorizationMandatoryRegressionTest' => 9, 'P1Perf004ReadOnlyAuthorizationContextTest' => 4,
    'P1Perf005AuthorizedBucketListTest' => 3, 'ChangeAuthDomain001AuthorizationTest' => 4,
    'ChangePolicyScopeUniqueness001MigrationTest' => 2,
    'P1Seed003PrivilegedAuthorizationGroupTest' => 4, 'P1Db050ResourceAuthorizationNumberingTest' => 4,
];
```

Use `define()` rather than `const` if consistent with existing runner; **copy** all previous `BE_REORG_*_CASES` definitions from `tools/test-numbering.php`, then `require __DIR__ . '/test-foundation.php'` exactly once. In `test-foundation.php`, add BE007-specific prerequisite files, first-priority label/header and **both** `foundationCheckJUnit($junit, BE_REORG_AUTHORIZATION_ADMIN_CASES)` + `foundationCheckJUnit($junit, BE_REORG_AUTHORIZATION_EXISTING_CASES)` after the existing gates. Verify runner has **exactly one** `foundationRun(... vendor/bin/phpunit ...)` and **one** `foundationRun(... vendor/bin/phpstan ...)`, both kept in foundation tool; no parallel runners or changing environment to non-test DB.
- [ ] **Step 6 — Runner checks + commit**: `php tools/test-authorization-administration.php --help` displays usage; without `--confirm-test-db` exits 2; wrong target blocked; `php -l` both runner files; on Windows run `php tools/test-authorization-administration.php --confirm-test-db` only after confirming disposable DB; `git commit -m "test(authorization): guard BE007 API and existing PBAC contracts"`.

### Task 8: Full acceptance, whole-branch review and incremental patch

**Files:** Review every changed file from Tasks 1–7; create final patch/package/evidence **outside** tracked production tree. No additional business features, no test-count changes.

**Interfaces — consumes:** all canonical classes/facades/runner and the exact `BE-REORG-006_FIX02` source checkpoint. **Produces:** incremental `BE-REORG-007` patch, SHA-256s, scope/parity evidence, Windows invocation and pending runtime state.

- [ ] **Step 1 — Run static gates**: `php -l` every modified/new PHP; `git diff --check`; use separate baseline copy from the exact BE006_FIX02 snapshot to compare six legacy public constructors/constants/all methods and their signature/return/PHPDoc semantics; verify both port methods, 12 Group + 25 Policy repository public delegates, 8 Group + 3 Policy application methods, all validator methods and no extra legacy public surface. Verify all original SQL private helpers moved once with same error/transaction clauses.
- [ ] **Step 2 — Byte-integrity scan**: hash every explicitly retained file and complete migrations/seeds/frontend/API/OpenAPI contract trees before/after. Detect forbidden writes. Check DB `FOR UPDATE`, Group permission and membership transactions/rollback, Policy `setStatus` CAS, scope uniqueness and ordered decision read queries against original source; don't use superficial string-only matching as proof of runtime equivalence.
- [ ] **Step 3 — Inventory/JUnit gate**: validate 4+6+4+4+6 = **24** actual new PHPUnit cases, 91 existing authority methods, previous family gates untouched, baseline 866 + 24 = **890** expected. If a count differs, diagnose test discovery, don't loosen JUnit matching or silently edit accepted baseline.
- [ ] **Step 4 — Whole-branch code review**: independent reviewer if tool exists; otherwise explicitly state `self-review only` and run separate hostile inspection against spec and Review Focus. Fix Critical/Important findings with RED→GREEN and rerun affected tests, record all rulings/deferred minors.
- [ ] **Step 5 — Build and round-trip incremental patch** against **BE006_FIX02**, not the original ZIP alone. `git apply --check` patch on independent checkpoint copy; `git apply`, hash all touched resulting files against candidate; `git apply -R`, hash every restored file against baseline; `git diff --check` and only allowed path diff. Do not bundle preexisting unrelated modifications or auto-apply patches on user's Windows repo.
- [ ] **Step 6 — Windows mandatory runtime acceptance**:

```powershell
Set-Location D:\XAMPP\htdocs\erp
$patch = '.\erp_v1.1.5_BE-REORG-007_authorization_administration_2026-10-16.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'Patch khong khop: dung lai.' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'Apply that bai: dung lai.' }
Set-Location .\backend
php tools/test-authorization-administration.php --confirm-test-db
```

Accept only full **890/890 PHPUnit PASS**, 24 new and all 91 existing Authorization/PBAC authority cases without skips, all previous BE001–BE006 gates, no new locks, and PHPStan diagnostic block **exactly equal** to accepted BE006_FIX02's 121 diagnostics. PHPStan exit 1 is inherited and must remain visible. If a runtime test fails, stop before PHPStan, debug root cause, emit a narrowly scoped FIX patch and rerun. **Do not claim green/accepted from local lint/static parity.**
- [ ] **Step 7 — Record final provenance**: checksums of patch+package, local static-gate results, actual reviewer type, 0 new PHPStan issues if and only if Windows log confirms exact comparison. Candidate handoff is not integration/merge; wait for Windows evidence before accepting checkpoint.

## Self-Review / Handoff Checks

- Every requirement in spec §§2–7 belongs to a task: ports (1), complete SQL read/write (2), validators (3), Group app (4), Policy app (5), DB/factory/authorization (6), API/JUnit (7), byte-integrity/PHPStan/patch (8).
- The five Review Focus failure modes each have a specific new test or preexisting **mandatory JUnit** case; no empty safety claim.
- Six public legacy facades keep their concrete constructor type hints. Only canonical constructors may take repository ports; evaluator/API callers remain source-identical. Default dependencies are created lazily at the same logical call site, with supplied DB.
- Case inventory comes from exact source; JUnit gate must confirm actual discovered cases (no missing, skipped, failed, or dataset-expanded mismatch).
- There is no prerequisite to rerun old BE001–BE006 patches on Windows; only BE007 incremental patch applies to accepted BE006_FIX02.
- **Execution method is not yet approved for this new plan.** Review and obtain explicit confirmation of this plan and choice of Native/Subagent-driven before running any implementation task.
