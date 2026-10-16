# BE-REORG-008 Authorization Decision Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the existing PBAC decision pipeline and short-lived read-only batch ownership into `App\Modules\Authorization\Decision` while retaining `App\Authorization\AuthorizationService` as a compatible final composition facade, with no changes to decisions, query behavior, exceptions, HTTP contracts, or consumers.

**Architecture:** A new `PbacDecisionPort` exposes exactly `authorize()` and `withReadOnlyBatch()`. `PbacDecisionEngine` mechanically owns the BE007_FIX03 algorithm, collaborators, constants and per-instance read-only context; `AuthorizationDecisionFactory::create()` builds one fresh engine; the final legacy facade stores one engine and delegates both operations to it. The existing decision/request DTOs, read-only memoizer, authorization legacy ports, BE007 Administration module, policy repository and every caller remain unchanged.

**Tech Stack:** PHP >=8.2 (accepted Windows runtime PHP 8.3.30), CodeIgniter 4.7 + Shield 1.4, MariaDB/MySQL `erp_test`, PHPUnit 10.5.65, PHPStan 2.x, Git incremental patches, PowerShell on Windows.

**Spec:** `docs/superpowers/specs/2026-10-16-be-reorg-008-authorization-decision-engine-design.md` (approved 2026-10-16).

## Global Constraints

- **Source checkpoint:** accepted **BE-REORG-007 + FIX01 + FIX02 + FIX03**; full PHPUnit baseline **890 tests / 8,554 assertions**, mandatory gates with zero skipped cases. PHPStan reports **121 inherited diagnostics** and raw exit code **1**; BE008 may introduce **zero** new diagnostics.
- **Changed production paths only:** `backend/app/Authorization/AuthorizationService.php`, plus three new files: `backend/app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php`, `backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php`, and `backend/app/Bootstrap/AuthorizationDecisionFactory.php`. Do not edit the existing BE007 Administration subtree/factory, PBAC DTOs/context helpers, any existing callers/ports, controllers, routes, models, migrations, seeds, frontend or PHPStan configuration.
- `AuthorizationService` stays `final`, continues implementing `LifecycleAuthorizationPort` and `NumberingAuthorizationPort`, and keeps **ten** optional constructor parameters (in this order, all default `null`): `$profiles`, `$contexts`, `$policies`, `$employees`, `$assignments`, `$organizations`, `$identities`, `$db`, `$catalog`, `$workflowStates`. Preserve concrete types, PHPDoc generics, names, named-argument and positional compatibility.
- Keep all **15 public constants** with their exact accepted literal values: `DECISION_ALLOW=ALLOW`, `DECISION_DENY=DENY`, `REASON_NO_BASE_PERMISSION=AUTH_NO_BASE_PERMISSION`, `REASON_EMPLOYEE_INELIGIBLE=AUTH_EMPLOYEE_INELIGIBLE`, `REASON_NO_EFFECTIVE_ASSIGNMENT=AUTH_NO_EFFECTIVE_ASSIGNMENT`, `REASON_OUT_OF_ASSIGNMENT_SCOPE=AUTH_OUT_OF_ASSIGNMENT_SCOPE`, `REASON_POLICY_DENIED=AUTH_POLICY_DENIED`, `REASON_ABSOLUTE_DENY=AUTH_ABSOLUTE_DENY`, `REASON_NO_MATCH=AUTH_NO_MATCH_ALLOW`, `REASON_POLICY_ALLOWED=AUTH_POLICY_ALLOWED`, `REASON_RESOURCE_INACTIVE=AUTH_RESOURCE_INACTIVE`, `REASON_CONTEXT_REQUIRED=AUTH_CONTEXT_REQUIRED`, `ERROR_RESOURCE_INVALID=AUTH_RESOURCE_INVALID`, `ERROR_ACTION_INVALID=AUTH_ACTION_INVALID`, `ERROR_POLICY_INVALID=AUTH_POLICY_INVALID`.
- Preserve public methods and their return types/PHPDoc: `authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision` and `withReadOnlyBatch(OperationContext $operation, callable $read): mixed` (`@template T`, `@param callable(): T`, `@return T`). New `PbacDecisionPort` has only these two methods.
- The canonical engine, **not the facade**, owns all existing private methods and mutable `?ReadOnlyAuthorizationContext` state; the facade never creates separate engines per operation. Keep `withReadOnlyBatch()` try/finally, nested-batch exception message, cache scope/limits, non-`view` invalidation, permission/eligibility/context checks, policy precedence, decision evidence and typed exceptions mechanically identical. `ReadOnlyAuthorizationContext`, `AuthorizationDecision`, `EvaluationOrganizationRequest` remain at their existing paths and are byte-unchanged.
- Constructor defaults must resolve `$db ?? db_connect()` exactly **once per canonical engine** and create all missing collaborators using that one connection. Supplied collaborators must retain identity; no process-wide cache, extra public `engine` argument, implicit permissive fallback or recursive factories. Engine imports may not reference `AuthorizationService` or API/Administration application services; facade constants may alias engine constants.
- Reuse real `erp_test` only after explicit `--confirm-test-db` and Foundation 17-check guard; prefix `dinktech_`, loopback `127.0.0.1:3306`. Never run two test processes concurrently against it. DB fixtures insert required `created_at`, create active GROUP roots and effective employee assignment, and clean up **only BE008-owned** INACTIVE policies before `DatabaseTestTrait` migration rollback. Do not weaken the native migration rollback guard or blanket-update policy statuses.
- Add the **26 BE008 tests inventoried below** (not filler): 4 boundary, 4 compatibility, 4 factory, 7 decision parity, 5 snapshot isolation, 2 real HTTP. Full-suite target **916 tests** if the repository remains otherwise unchanged. Keep BE007's 24 tests, the existing 91 PBAC authority tests, and all earlier Foundation/Organization/Contact/Employee/Settings/Numbering gates.
- **Runner restriction:** create `backend/tools/test-authorization-decision.php` but leave both `test-foundation.php` and `test-authorization-administration.php` unchanged. The new wrapper invokes the accepted BE007 runner once (which launches **one** full PHPUnit process and **one** PHPStan process, only after PHPUnit success), then independently checks the fresh JUnit evidence for BE008 cases. Since the BE007 runner exits internally, do **not** `require` it and expect to continue; launch it as one child CLI process. Preserve its lock, guard, stdout, JUnit and `test-result.log`. Report PHPStan exit code 1 honestly; do not mistake 121 inherited diagnostics for a clean PHPStan run.
- Local snapshot lacks `backend/vendor`; use PHP lint, static inventory, source comparison and `git apply --check`/apply/reverse round-trip locally. Genuine PHPUnit, database, PHPStan and HTTP acceptance is based on the user's Windows runtime log; no browser/Apache UAT claim.

## Review Focus

Five high-risk conditions the spec implies and the tests must pin to the task that owns them:

1. **System actor without a user ID:** it must throw `AUTH_CONTEXT_REQUIRED`, never inherit a system-admin bypass. Task 2 `testInvalidInputsEffectsAndPersistenceFailuresStayTypedAndFailClosed` checks the exact `DomainException` error and context.
2. **A supplied DB plus only some supplied collaborators:** omitted collaborators must use the supplied connection, and supplied objects must remain the same instances. Task 3 `testDefaultCollaboratorsUseOneSuppliedConnection` and `testFactoryPreservesSuppliedCollaboratorIdentity` inspect the resulting engine and perform live decisions.
3. **Identical actor/request but differing sub-second evaluation instant:** read-only cache must miss rather than share decisions from a different context. Task 4 `testOperationChangeBypassesCacheAndKeepsEvaluationInstant` compares SQL lookups and DTO evidence.
4. **Inactive resource recovery via `resource.setstate`:** ordinary actions deny, while recovery is still checked by base permission and assignment eligibility. Task 2 `testInactiveResourceAndSetstateRecoveryMatch` compares complete results and restores any modified test-owned resource status.
5. **DELETE without a body and denied actor:** CSRF still applies; status 204 must not be rejected solely for one framework-added line ending; a 403 must write nothing. Task 5 HTTP tests check the actual controller/filter/database boundary.

---

## File Map and Ownership

**Create production files:**

- `backend/app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php` - two-method canonical application contract; no storage/HTTP imports.
- `backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php` - single owner of the original PBAC and read-only batch implementation, original ten-parameter constructor and original private helpers.
- `backend/app/Bootstrap/AuthorizationDecisionFactory.php` - stateless final composition root with one `create(...)` taking the exact ten optional constructor dependencies and returning `PbacDecisionPort`.

**Modify production:** `backend/app/Authorization/AuthorizationService.php` - final legacy facade, constants, ten-parameter constructor, two methods, one private readonly engine reference. **No other existing production file may change.**

**Create test-only files:**

- `backend/tests/_support/Authorization/BeReorg008DecisionFixture.php` - one reusable, tracked **test-only** database fixture; explicit inserts/cleanup, no global resets.
- `backend/tests/unit/BeReorg008DecisionBoundaryTest.php` - 4.
- `backend/tests/unit/BeReorg008DecisionCompatibilityTest.php` - 4.
- `backend/tests/unit/BeReorg008DecisionFactoryTest.php` - 4.
- `backend/tests/database/BeReorg008DecisionParityTest.php` - 7.
- `backend/tests/database/BeReorg008DecisionBatchTest.php` - 5.
- `backend/tests/database/BeReorg008DecisionHttpTest.php` - 2.

**Create launcher:** `backend/tools/test-authorization-decision.php` - exact 26-case JUnit inventory and old-runner delegation, plus fail-closed preflight.

### Frozen source signatures and implementation anchors

**Port, in namespace `App\Modules\Authorization\Decision\Application\Ports`:**

```php
interface PbacDecisionPort
{
    public function authorize(
        OperationContext $operation,
        string $resourceType,
        string $action,
        EvaluationOrganizationRequest $contextRequest,
        mixed $stateId = null,
    ): AuthorizationDecision;

    /** @template T @param callable(): T $read @return T */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed;
}
```

Imports are `App\Infrastructure\Mutation\OperationContext`, `App\Authorization\EvaluationOrganizationRequest`, and `App\Authorization\AuthorizationDecision`.

**Engine, in namespace `App\Modules\Authorization\Decision\Application`:**

```php
final class PbacDecisionEngine implements PbacDecisionPort
{
    // Preserve all 15 literals from BE007_FIX03 AuthorizationService.
    public function __construct(
        ?EffectiveProfileResolver $profiles = null,
        ?EvaluationOrganizationResolver $contexts = null,
        ?PolicyRepository $policies = null,
        ?EmployeeRepository $employees = null,
        ?EmployeeAssignmentService $assignments = null,
        ?OrganizationService $organizations = null,
        ?ShieldIdentityAdapter $identities = null,
        ?ConnectionInterface $db = null,
        ?ResourceCatalogRepository $catalog = null,
        ?ResourceWorkflowStateResolver $workflowStates = null,
    );
    // Implements the port methods above; moves all existing private helpers unchanged.
}
```

Import legacy `EffectiveProfileResolver`, `EffectiveProfile`, `EvaluationOrganizationResolver`, `EvaluationOrganizationRequest`, `AuthorizationDecision`, `ReadOnlyAuthorizationContext`, `ResourceDomain`, `ShieldIdentityAdapter` explicitly from `App\Authorization`; preserve legacy repository/service/exception imports. Its existing private helpers are `readThrough`, `loadPolicies`, `matching`, `evaluateLane`, `uniqueSorted`, `token`, `decision`; do not extract a second algorithm or modify evaluation order.

**Factory, namespace `App\Bootstrap`:**

```php
final class AuthorizationDecisionFactory
{
    private function __construct() {}

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public static function create(
        ?EffectiveProfileResolver $profiles = null,
        ?EvaluationOrganizationResolver $contexts = null,
        ?PolicyRepository $policies = null,
        ?EmployeeRepository $employees = null,
        ?EmployeeAssignmentService $assignments = null,
        ?OrganizationService $organizations = null,
        ?ShieldIdentityAdapter $identities = null,
        ?ConnectionInterface $db = null,
        ?ResourceCatalogRepository $catalog = null,
        ?ResourceWorkflowStateResolver $workflowStates = null,
    ): PbacDecisionPort;
}
```

Factory forwards arguments by **name**, constructs a new `PbacDecisionEngine`, and does not call `db_connect()` itself; the engine constructor performs the single default resolution and preserves provided collaborator identity.

**Facade:**

```php
final class AuthorizationService implements LifecycleAuthorizationPort, NumberingAuthorizationPort
{
    // All 15 constants exposed as literals or aliases to PbacDecisionEngine.
    private readonly PbacDecisionPort $engine;
    public function __construct(/* exact ten parameters and null defaults above */);
    public function authorize(OperationContext $operation, string $resourceType, string $action,
        EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision;
    /** @template T @param callable(): T $read @return T */
    public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed;
}
```

No `__call`, inheritance compatibility, public engine parameter, private PBAC helpers or `ReadOnlyAuthorizationContext` property remain in the facade. In the facade constructor, call `AuthorizationDecisionFactory::create(profiles: $profiles, contexts: $contexts, policies: $policies, employees: $employees, assignments: $assignments, organizations: $organizations, identities: $identities, db: $db, catalog: $catalog, workflowStates: $workflowStates)` **once**; each public method delegates to `$this->engine` **without replacing the callback**.

### Exact existing authority inventory (read from accepted BE007 runner)

| Existing class | Cases | Existing class | Cases |
|---|---:|---|---:|
| `P1Auth001PolicyRepositoryTest` | 14 | `P1Auth002AuthorizationServiceTest` | 17 |
| `P1Auth003AuthorizationDecisionTest` | 3 | `P1Auth004AssignmentEligibilityTest` | 11 |
| `P1Auth004AuthorizationAuditTest` | 5 | `P1Auth005SecurityAdministrationTest` | 11 |
| `P1AuthorizationMandatoryRegressionTest` | 9 | `P1Perf004ReadOnlyAuthorizationContextTest` | 4 |
| `P1Perf005AuthorizedBucketListTest` | 3 | `ChangeAuthDomain001AuthorizationTest` | 4 |
| `ChangePolicyScopeUniqueness001MigrationTest` | 2 | `P1Seed003PrivilegedAuthorizationGroupTest` | 4 |
| `P1Db050ResourceAuthorizationNumberingTest` | 4 | **Total** | **91** |

BE007 new cases: `BeReorg007AuthorizationBoundaryTest=4`, `BeReorg007AuthorizationCompatibilityTest=6`, `BeReorg007AuthorizationPolicySqlParityTest=4`, `BeReorg007AuthorizationFactoryTest=4`, `BeReorg007AuthorizationHttpTest=6`, total **24**. Earlier checkpoint gates are registered in `test-authorization-administration.php` -> `test-foundation.php`; preserve them without copying or changing their source.

---

### Task 1: Define the canonical two-operation port

**Files:** Create `backend/app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php`; start `backend/tests/unit/BeReorg008DecisionBoundaryTest.php` (method 1 of 4).

**Interfaces:** Produces exactly `PbacDecisionPort::authorize(...)` and `::withReadOnlyBatch(...)` defined above; consumed by Tasks 2-4.

- [ ] **Step 1 - RED test:** add `testDecisionPortDeclaresOnlyTwoOperations` to `BeReorg008DecisionBoundaryTest`: `ReflectionClass(PbacDecisionPort::class)` must report an interface with exactly `authorize` and `withReadOnlyBatch`, named arguments/types/defaults from the legacy public contract; assert DTO/operation parameter types and return types. Do not test the not-yet-implemented engine in this step.
- [ ] **Step 2 - Verify RED:** `cd backend && php vendor/bin/phpunit --no-coverage tests/unit/BeReorg008DecisionBoundaryTest.php --filter testDecisionPortDeclaresOnlyTwoOperations`; expected FAIL because `PbacDecisionPort` does not exist (correct missing-symbol failure, not a DB/bootstrap exception).
- [ ] **Step 3 - Implement:** write the new port with exactly the two method declarations, correct imports, `@template T`/`@param callable(): T`/`@return T` on batch, no other public methods and no constructor.
- [ ] **Step 4 - Verify GREEN:** rerun the focused case with `--no-coverage`, expect **1/1** PASS and exit code **0**. Lint `php -l app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php`.
- [ ] **Step 5 - Commit:** `git add backend/app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php backend/tests/unit/BeReorg008DecisionBoundaryTest.php && git commit -m "refactor(authorization): define decision port"` (run from repo root).

### Task 2: Relocate the PBAC pipeline mechanically and characterize decisions

**Files:** Create `backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php`, `backend/tests/_support/Authorization/BeReorg008DecisionFixture.php`, and `backend/tests/database/BeReorg008DecisionParityTest.php` (7); add methods 2-3 to `BeReorg008DecisionBoundaryTest.php`. Do **not** change the legacy service yet: it remains an independent BE007 behavioral oracle for side-by-side comparison.

**Interfaces:** Consumes Task 1 port and current BE007 `AuthorizationService`; produces the exact `PbacDecisionEngine` constructor, 15 constants, private method inventory and public operations in File Map. The test fixture is `Tests\Support\Authorization\BeReorg008DecisionFixture` (under the existing `Tests\Support\` dev autoload). Required test-only methods:

```php
public function __construct(BaseConnection $db);
/** @return array{user_id:int, group_id:int, root_id:int, company_id:int, sibling_id:?int, employee_id:?int, assignment_ids:list<int>} */
public function seedActor(bool $grantView = true, bool $withSibling = false, bool $withAssignment = true): array;
public function grant(int $groupId, string $resourceType, string $action): void;
public function addAssignment(int $employeeId, int $organizationId, ?int $departmentId = null, ?int $positionId = null): int;
public function addPolicy(int $organizationId, string $participantType, int $participantRefId,
    string $effect, string $resourceType = 'price_list', string $action = 'view', ?int $stateId = null): int;
public function operation(int $userId, string $requestId = 'be008-parity'): OperationContext;
public function request(int $organizationId): EvaluationOrganizationRequest;
public function trackInactivePolicy(int $policyId): void;
public function restoreTrackedInactivePolicies(): void;
public function trackResourceStatus(int $resourceId, string $priorStatus): void;
public function restoreTrackedResourceStatuses(): void;
```

`seedActor` runs the approved `ResourceActionSeeder` when needed, inserts a fresh `GROUP` root + `COMPANY` (optional sibling), Shield user/group/membership and optional active employee + effective assignment with required `created_at`, and (when `$grantView`) only the approved `price_list.view` group permission. It is NOT a privilege bootstrap for negative cases. Return IDs exactly as documented. `addPolicy` uses the existing `PolicyRepository::insertWithActions` (no duplicated SQL business logic); `grant` resolves a resource-action ID, does not introduce unapproved permissions; `restoreTrackedInactivePolicies` changes only tracked policy IDs with status `INACTIVE` to `ACTIVE` before parent `tearDown()`. `trackResourceStatus`/`restoreTrackedResourceStatuses` restores only resource IDs modified by BE008 tests to their recorded original status, also before parent `tearDown()`. Call both cleanup functions from `tearDown()` even after a failing assertion; do not relax migrations or reset unrelated data.

- [ ] **Step 1 - Write the failing boundary cases:** `testEngineImplementsPortAndOwnsPrivatePipeline` requires a final canonical `PbacDecisionEngine`, implements `PbacDecisionPort`, has the seven existing private helpers, and owns `?ReadOnlyAuthorizationContext`. `testDecisionModuleHasNoLegacyFacadeControllerOrAdministrationImports` inspects canonical PHP source for forbidden facade/API/controller/Administration service dependencies (do not forbid `App\Repositories\PolicyRepository`, the explicitly allowed legacy read seam). Both FAIL until the engine exists.
- [ ] **Step 2 - Write seven real-DB parity cases, comparing full `AuthorizationDecision::toArray()` of `new AuthorizationService(db: $this->db)` with `new PbacDecisionEngine(db: $this->db)` on the SAME fixtures, OperationContext, request and evaluation instant; compare exception class, `getErrorCode()` and `getContext()` for error cases:**
  - `testNoBasePermissionAndEligibleNoMatchReturnSameEvidence` - with/without Shield base permission; assert `AUTH_NO_BASE_PERMISSION` and `AUTH_NO_MATCH_ALLOW`, including request/time/source/assignment IDs.
  - `testAllowDenyAndAbsoluteDenyHaveSameLaneEvidence` - deny+allow for actor/group, absolute deny inherited from GROUP root; assert decisive/matched IDs and precedence, not only allow bit.
  - `testAssignmentIntersectionAndInheritedScopeMatch` - two department lanes with DENY, then sibling-only policy; ensure assignment IDs, actor-lane intersection and ancestor-only scope match.
  - `testEmployeeEligibilityAndSystemMasterScopeMatch` - no employee, inactive employee, no assignment; use actual seeded SYSTEM (`user`) and MASTER (`product`) resource/actions and grant their matching base permissions to confirm any effective Group assignment is eligible, while ORGANIZATION cannot borrow sibling assignment.
  - `testInactiveResourceAndSetstateRecoveryMatch` - inactive normal resource denies with `AUTH_RESOURCE_INACTIVE`, `resource.setstate` still requires ordinary base permission/eligible actor. Track the affected `resources.id` and its original status, and restore it before parent teardown.
  - `testTrustedStateAndClientContextMismatchErrorsMatch` - supplied untrusted workflow state and conflicting `clientEvaluationOrganizationId` use existing typed errors, never client hints as trusted context; compare codes/contexts.
  - `testInvalidInputsEffectsAndPersistenceFailuresStayTypedAndFailClosed` - null actor including system flag, invalid action/resource, evaluated action with malformed policy effect, and injected failing repository lookup: same typed Domain/Persistence failures, never ALLOW or a fake not-found. For the repository fault, use the existing `P1Auth001PolicyRepositoryTest` pattern: mock a `ConnectionInterface::table()` exception, pass `new PolicyRepository($failingConnection)` to both decision implementations while other dependencies use real `$this->db`. Never drop/rename test DB tables. For a malformed effect, alter only a BE008-owned `policy_actions` row and restore its original effect in a `finally` block.
- [ ] **Step 3 - Verify RED:** `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg008DecisionBoundaryTest.php tests/database/BeReorg008DecisionParityTest.php`; expect new engine missing. Record that existing legacy baseline cases still pass; missing-symbol failures are intentional, unrelated fixture errors are not.
- [ ] **Step 4 - Implement engine:** copy the BE007_FIX03 `AuthorizationService.php` decision body mechanically into `PbacDecisionEngine.php`; change namespace/imports, class name and implemented interface only. Move (not rewrite) the ten-argument constructor/default dependency construction, 15 constants, `authorize()`, `withReadOnlyBatch()`, seven private helpers, read-only context property and all PHPDocs. Keep `self::` constants pointing to the canonical class; do not call the facade.
- [ ] **Step 5 - Verify GREEN:** run the same cases `--no-coverage` serially; expect **3 boundary cases + 7 parity cases** PASS. Run `P1Auth002AuthorizationServiceTest.php` (17 baseline cases) as comparison. Lint all three new PHP files and fixture. Compare the decision method/helper statement blocks against the BE007 source; only required namespace/interface/import changes are allowed.
- [ ] **Step 6 - Commit:** `git add backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php backend/tests/_support/Authorization/BeReorg008DecisionFixture.php backend/tests/database/BeReorg008DecisionParityTest.php backend/tests/unit/BeReorg008DecisionBoundaryTest.php && git commit -m "refactor(authorization): relocate PBAC decision engine"`.

### Task 3: Add the stateless factory and prove collaborator ownership

**Files:** Create `backend/app/Bootstrap/AuthorizationDecisionFactory.php`, `backend/tests/unit/BeReorg008DecisionFactoryTest.php` (3 initial cases; Task 4 adds the fourth).

**Interfaces:** Consumes Task 2 engine; produces `AuthorizationDecisionFactory::create(...10 args...): PbacDecisionPort` exactly as in File Map.

- [ ] **Step 1 - Write three tests (RED):**
  - `testFactoryCreatesIndependentCanonicalEngines` - two `create(db: $this->db)` results are different objects, both implement `PbacDecisionPort`, neither lives in a static factory property.
  - `testFactoryPreservesSuppliedCollaboratorIdentity` - construct selected `EffectiveProfileResolver`, `PolicyRepository`, `EmployeeRepository`, `ShieldIdentityAdapter`, `ResourceCatalogRepository` against `$this->db`; pass with named arguments and inspect corresponding canonical engine properties to confirm strict `assertSame` identity. Also verify a mixed supplied/default case.
  - `testDefaultCollaboratorsUseOneSuppliedConnection` - with a supplied `BaseConnection`, inspect newly constructed default collaborators (or their known delegates) and exercise a live negative decision to prove no default DB group leak; verify original ten-parameter order/defaults via reflection.
- [ ] **Step 2 - Verify RED:** run `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg008DecisionFactoryTest.php`; expect missing factory symbol, not an unrelated DB error.
- [ ] **Step 3 - Implement:** final factory with private constructor and static `create(...)`; forward all ten collaborators **by name** to `new PbacDecisionEngine(...)`, return `PbacDecisionPort`. Factory does not choose default DB; `PbacDecisionEngine` resolves it once and owns defaults.
- [ ] **Step 4 - Verify GREEN:** rerun the three factory cases, expect **3/3** PASS; run PHP lint. Do not add the fourth test until Task 4 so this commit has a completely green focused test file.
- [ ] **Step 5 - Commit:** `git add backend/app/Bootstrap/AuthorizationDecisionFactory.php backend/tests/unit/BeReorg008DecisionFactoryTest.php && git commit -m "refactor(authorization): add stateless decision factory"`.

### Task 4: Cut over the final legacy facade and verify one-engine batch lifetime

**Files:** Modify `backend/app/Authorization/AuthorizationService.php`; create `backend/tests/unit/BeReorg008DecisionCompatibilityTest.php` (4), `backend/tests/database/BeReorg008DecisionBatchTest.php` (5); complete `BeReorg008DecisionBoundaryTest.php` (method 4) and `BeReorg008DecisionFactoryTest.php` (method 4).

**Interfaces:** Consumes factory/port/engine from Tasks 1-3. Produces the unchanged public `AuthorizationService` shape and its private readonly `PbacDecisionPort $engine`.

- [ ] **Step 1 - Write the four failing compatibility cases:**
  - `testLegacyConstructorRetainsTenOptionalNamedParameters` - reflection asserts exact names/order/class types/null defaults, including `$db: ?ConnectionInterface`; instantiates with named and positional collaborators.
  - `testLegacyFifteenConstantsRetainValues` - compare all 15 literal values listed in Global Constraints to both facade and canonical engine, not only their count.
  - `testLegacyPortsAndAuthorizeBatchSignaturesMatchBaseline` - implements both `LifecycleAuthorizationPort` and `NumberingAuthorizationPort`; public `authorize()`/`withReadOnlyBatch()` parameter/return signatures and PHPDoc generic contract unchanged.
  - `testPositionalAndNamedConstructionPreservesDtoContracts` - verify `AuthorizationDecision::toArray()` keys/types and `EvaluationOrganizationRequest` named/positional fields; also assert the facade has one private `PbacDecisionPort` instance and no shadow snapshot.
- [ ] **Step 2 - Add two RED cases:** `testLegacyFacadeDelegatesInsteadOfEmbeddingPbacRules` (fourth boundary method) uses reflection/source inspection to prove the facade is `final`, calls `AuthorizationDecisionFactory::create` exactly once, delegates both operations to one engine, and no longer contains `matching`, `evaluateLane`, `loadPolicies` or `ReadOnlyAuthorizationContext` state. It rejects dependencies on Administration application services. `testFacadeOwnsOneEngineAndNoImplicitAllow` (fourth factory method) checks that the facade retains one canonical engine across both operations and never grants an actor lacking base permission. Both FAIL before facade cutover.
- [ ] **Step 3 - Write five batch cases against `AuthorizationService` with real `DatabaseTestTrait` fixtures; do not alter `ReadOnlyAuthorizationContext.php`:**
  - `testFacadeCallbackSharesEngineAndBatchesViewQueries` - a callback calling `$service->authorize()` twice for the same `OperationContext` and target returns byte-identical `toArray()`, and DBQuery listener count for policy-action queries is smaller than independent calls.
  - `testOperationChangeBypassesCacheAndKeepsEvaluationInstant` - same actor but different request ID/system flag or microsecond `EvaluationTime` must not reuse cached IAM/assignment/policy reads; `toArray()['evaluation_time']` and request evidence remain correct.
  - `testNestedBatchThrowsWithoutDiscardingOuterContext` - inner batch throws exact `LogicException('Nested authorization read batches are not supported.')`; outer callback may continue using its still-valid batch; outer context is released afterward.
  - `testCallbackExceptionReleasesSnapshotAndDoesNotMemoizeFailure` - callback throws a typed exception; later independent call reloads DB and exception is never cached.
  - `testNonViewInvalidatesSnapshotAndIndependentCallsNeverCacheDecisions` - denied/exceptional non-`view` action clears active snapshot; later `view` reloads, and a second independent request after batch completion sees changed base permission/policy. No final `AuthorizationDecision` memoization.
- [ ] **Step 4 - Verify architecture RED before cutover:** run boundary final case plus facade-ownership assertion (Task 3 fourth case), expect FAIL because facade still owns PBAC helpers. Existing behavior-characterization batch cases may already pass against old service; that is valid baseline characterization, not a fabricated RED claim. Any unexpected fixture error must be fixed before cutover.
- [ ] **Step 5 - Implement minimal facade:** retain original public constants (literal or canonical aliases), ten original constructor parameters, two legacy implemented ports and exactly two public operations; initialize one `private readonly PbacDecisionPort $engine` via factory named arguments in constructor; `authorize()` and `withReadOnlyBatch()` immediately delegate without changing arguments, `read` closure or return value. Remove all original private PBAC algorithm and read-only cache state from the facade; canonical engine remains the single owner.
- [ ] **Step 6 - Verify GREEN:** run the 4 boundary + 4 compatibility + 4 factory + 7 parity + 5 batch cases serially using `php vendor/bin/phpunit --no-coverage ...` (all **24** non-HTTP BE008 cases) and re-run legacy `P1Auth002AuthorizationServiceTest.php`, `P1Perf004ReadOnlyAuthorizationContextTest.php`, `P1AuthorizationMandatoryRegressionTest.php` and `P1Auth005SecurityAdministrationTest.php`. Expect all PASS; lint facade, factory and engine. Every failure is a blocker, not a reason to alter an accepted caller.
- [ ] **Step 7 - Commit:** `git add backend/app/Authorization/AuthorizationService.php backend/tests/unit/BeReorg008DecisionCompatibilityTest.php backend/tests/database/BeReorg008DecisionBatchTest.php backend/tests/unit/BeReorg008DecisionBoundaryTest.php backend/tests/unit/BeReorg008DecisionFactoryTest.php && git commit -m "refactor(authorization): delegate legacy service to one decision engine"`.

### Task 5: Real API-030 HTTP contract parity

**Files:** Create `backend/tests/database/BeReorg008DecisionHttpTest.php` (2). No production controller/routes modification.

**Interfaces:** Consumes unchanged API routes, legacy facade and the test-only `BeReorg008DecisionFixture` helper. HTTP test class uses `DatabaseTestTrait`, `FeatureTestTrait`, `AuthenticationTesting` and a request helper with `UserModel`, `User`, `RequestContext::reset`, proper feature-test service resets and a CSRF hash for POST/PUT/PATCH/**bodyless DELETE**, following the accepted `BeReorg007AuthorizationHttpTest` pattern.

- [ ] **Step 1 - Write tests before any HTTP-related change:**
  - `testAuthorizedPolicyCreateViewDeletePreservesCsrfAndEnvelopes` - active GROUP admin + effective assignment + `policy.view/create/delete` permission, real `POST /api/v1/policies` => 201 JSON with trusted `meta.request_id`, `GET /api/v1/policies` => 200 with flat `meta.page`, `meta.page_size`, `meta.total`, and `DELETE /api/v1/policies/{id}` (empty body + CSRF) => 204, body in `['', "\n", "\r\n"]`, policy/actions no longer present.
  - `testDeniedPolicyMutationReturns403WithoutDatabaseChanges` - user with active employee assignment but no policy rights posts the same valid policy payload, receives 403 and existing machine error, and `policies`, `policy_actions`, `group_permissions`, `auth_permissions_users` counts remain unchanged. Confirm denied actor is not silently treated as unauthorized due to missing employee eligibility.
- [ ] **Step 2 - Run baseline characterization:** `php vendor/bin/phpunit --no-coverage tests/database/BeReorg008DecisionHttpTest.php` on disposable `erp_test`, **never concurrently**. These are intended to validate unchanged HTTP contracts; they can pass immediately after Task 4. Fix incorrect fixtures/assertions, not application filters/controllers. Validate every INSERT has required `created_at` and that `tearDown()` restores only tracked INACTIVE policies before `DatabaseTestTrait` teardown.
- [ ] **Step 3 - Re-run and verify:** expect **2/2** PASS, zero errors/failures, exit 0 (use `--no-coverage` to avoid absent code coverage driver warning). Check 204 line endings without accepting a response payload. Run `php -l tests/database/BeReorg008DecisionHttpTest.php`.
- [ ] **Step 4 - Commit:** `git add backend/tests/database/BeReorg008DecisionHttpTest.php && git commit -m "test(authorization): pin BE008 approved and denied HTTP contracts"`.

### Task 6: New BE008 guarded runner without modifying inherited runners

**Files:** Create `backend/tools/test-authorization-decision.php`; no modifications to `backend/tools/test-foundation.php` or `backend/tools/test-authorization-administration.php`.

**Interfaces:** CLI `php tools/test-authorization-decision.php --confirm-test-db`. The BE008 runner delegates once to the accepted BE007 CLI, which owns Foundation DB target checks, lock, JUnit creation, one full PHPUnit, PHPStan and `test-result.log`. After its child exits, BE008 runner checks the **fresh** `backend/build/logs/be-reorg-001a.xml` (NOT a stale file), and reports BE008 coverage; do not rerun PHPUnit/PHPStan.

**Exact new JUnit inventory:**

```php
$be008Required = [
    'BeReorg008DecisionBoundaryTest'      => 4,
    'BeReorg008DecisionCompatibilityTest' => 4,
    'BeReorg008DecisionFactoryTest'       => 4,
    'BeReorg008DecisionParityTest'        => 7,
    'BeReorg008DecisionBatchTest'         => 5,
    'BeReorg008DecisionHttpTest'          => 2,
]; // 26 new tests, 916 expected if suite unchanged
```

- [ ] **Step 1 - Write a runner preflight probe** (can be a temporary CLI test harness; do not add test-only probe to production): missing arguments and unknown flags must exit 2 before any DB interaction; `--help` must exit 0, listing test DB target and side effects; missing any of the canonical files or six new test files must stop before launching the BE007 runner. Static source inventory must find exactly 26 `public function test...()` methods and match the class table above.
- [ ] **Step 2 - Verify RED:** `php tools/test-authorization-decision.php --help` currently fails because the launcher is absent; record the expected missing-file error. Prepare (but do not yet claim verified) synthetic JUnit XML variants with a skipped/failed/absent/duplicate BE008 case to exercise the new verifier after implementation. Do not run against a business DB.
- [ ] **Step 3 - Implement launcher:** make its pure `be008AssertJUnit(string $path, array $required): void` function includable without launching the CLI (guard execution using direct-script detection), so temporary synthetic-JUnit probes exercise the actual validator. Define the one confirmation flag; validate canonical paths/tests; invoke `[PHP_BINARY, 'tools/test-authorization-administration.php', '--confirm-test-db']` as a single child via `proc_open` with Windows-safe `bypass_shell`, joined output stream and verbatim log retention. Wait for exit, read the accepted runner's `test-result.log`, and distinguish `PHPUnit exit code: 0` from `PHPStan exit code: 1`. Parse the newly written JUnit with `DOMDocument`/`DOMXPath`, require 4/4/4/7/5/2 testcases with zero errors/failures/skips; verify baseline BE007 coverage lines are present. **Never** accept a stale JUnit produced before this run. Append a BE008 gate summary to the existing log, including the child exit code and PHPStan caveat.
- [ ] **Step 4 - Verify CLI safety and JUnit probe:** `php -l tools/test-authorization-decision.php`, `php tools/test-authorization-decision.php --help` (0), `php tools/test-authorization-decision.php` (2), `php tools/test-authorization-decision.php --bogus` (2). Test synthetic JUnit rejection of absent/duplicate/skipped/failed methods and success with exact inventory. Do not treat raw PHPStan exit 1 as an accepted checkpoint or forge a 0 exit status; print `PHPUnit PASS; PHPStan baseline comparison required` if that is what the child log says.
- [ ] **Step 5 - Commit:** `git add backend/tools/test-authorization-decision.php && git commit -m "test(authorization): guard BE008 full-suite and decision coverage"`.

### Task 7: Whole-branch verification, candidate patch, and Windows acceptance handoff

**Files:** No application edits planned; produce an incremental patch relative to accepted **BE007_FIX03**, README and verification evidence outside the repo (e.g. `/mnt/data/BE-REORG-008_package_2026-10-16.zip`). Include the approved spec and this approved plan in delivery; do not reapply any BE001-BE007 patches.

- [ ] **Step 1 - Validate baseline and scope:** record `git status --short`, accepted BE007_FIX03 commit/hash, `git diff --name-status <BE007_FIX03>...HEAD`, and compare out-of-scope source hashes. Only four allowed production paths + six tests + one test fixture + one runner may differ in implementation patch. Documentation is delivered separately. Ensure neither DTO nor BE007 Administration nor API/controllers/migrations/schema/frontend/PHPStan config changed.
- [ ] **Step 2 - Run static gates:** PHP lint on every touched PHP file; `git diff --check <BE007_FIX03>...HEAD`; method/constant/constructor reflection snapshot against BE007 source; static engine helper/body parity check; `PbacDecisionPort` method inventory. No `@phpstan-ignore`, baseline changes, `__call`, broad service-locator or permission fallback may appear.
- [ ] **Step 3 - Runtime RED/GREEN evidence:** for focused tests use `cd backend && php vendor/bin/phpunit --no-coverage <test-file>` on user Windows; run them sequentially, not parallel. Before the full run, ensure no BE008-created policy remains INACTIVE. Then use **one** `php tools/test-authorization-decision.php --confirm-test-db` process. Expected full PHPUnit **916/916** and required coverage gates (91 old PBAC + 24 BE007 + 26 BE008), zero skips/errors/failures; assertions will be the actual measured value rather than an invented count. PHPStan runs once afterward only if PHPUnit succeeds.
- [ ] **Step 4 - Exact PHPStan baseline review:** compare all 121 diagnostics against the accepted BE007_FIX03 log by normalized `(path, line, identifier, full message)`. Permit only the documented relocation of the **single** `AuthorizationService.php:368 argument.type` for `PolicyRepository::findApplicablePolicyRows()` to `PbacDecisionEngine::loadPolicies` (its new line number may differ). Keep BE007's inherited `SqlPolicyRepository.php:397 affectedRows() method.notFound` unchanged. Every other diagnostic must match; any added, missing/unexplained or altered diagnostic is a blocker. Raw PHPStan exit 1 is **not** PHPStan PASS.
- [ ] **Step 5 - Build/apply/reverse candidate:** `git diff --binary <BE007_FIX03>...HEAD -- backend/... > erp_v1.1.5_BE-REORG-008_authorization_decision_2026-10-16.patch`; verify `git apply --check`, apply then reverse on a clean **BE007_FIX03** checkout/worktree, compare all changed file bytes and compute SHA-256. Do not include the unrelated documentation commits or other baseline patches.
- [ ] **Step 6 - Review and deliver:** record file list, test method inventory, command transcripts, byte-integrity evidence and SHA-256. Deliver patch + package + exact PowerShell apply command. Mark `BE-REORG-008 CANDIDATE` until the user's actual Windows log passes all gates and the normalized PHPStan set is checked; only then mark `ACCEPTED`. No browser/Apache UAT claim.

## Windows Handoff (after Task 7, not executed during planning)

```powershell
Set-Location D:\XAMPP\htdocs\erp
$patch = '.\erp_v1.1.5_BE-REORG-008_authorization_decision_2026-10-16.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'Patch does not match BE007_FIX03.' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'Patch apply failed.' }
Set-Location .\backend
php tools/test-authorization-decision.php --confirm-test-db
# Send D:\XAMPP\htdocs\erp\test-result.log for PHPStan baseline comparison.
```

## Self-Review / Execution Gate

- **Spec coverage:** canonical decision ownership (Tasks 1-2), stateless DI/factory (3), final composition compatibility and shared snapshot (4), HTTP security contracts (5), inherited runner gates (6), PHPStan normalized diagnostic parity and patch round-trip (7).
- **All new case names/counts:** Boundary 4, Compatibility 4, Factory 4, Parity 7, Batch 5, HTTP 2 = **26**; baseline 890 + 26 = **916**. Existing BE007 24 / PBAC 91 explicitly preserved.
- **Hard boundary:** BE008 only, no unrelated reorganization; output remains a candidate until user Windows verification. No source placeholders or unspecified public interfaces.
- **Approval:** The user has approved the **design spec** and requested this plan. Implementation remains gated on explicit review/approval of this **written plan**, with the execution method confirmed at handoff.
