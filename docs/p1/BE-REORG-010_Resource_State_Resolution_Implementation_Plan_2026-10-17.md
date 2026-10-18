# BE-REORG-010 Resource State Resolution Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the eleven existing Resource workflow-State resolution operations from `App\Services\ResourceWorkflowStateResolver` to the standard `Workflow/StateResolution` module while preserving all PBAC, legacy API, repository identity, error and cache semantics.

**Architecture:** `ResourceStateResolutionService` implements an eleven-operation application port, consumes an eight-operation read port and owns the three per-instance caches. A read-only `ExistingRepositoryStateReadAdapter` delegates directly to the existing Resource Catalog, BE009 Lifecycle façade and Policy repositories. `WorkflowStateResolutionFactory` wires one new engine per call; the final legacy resolver forwards eleven methods explicitly and re-exports exactly eight error codes through `Contracts`.

**Tech Stack:** PHP `^8.2` (Windows acceptance: PHP 8.3.30), CodeIgniter 4 `^4.7`, Shield `^1.4`, MySQL/MariaDB InnoDB (`127.0.0.1:3306/erp_test`, prefix `dinktech_`), PHPUnit 10.5.65, PHPStan 2.x, Git, Windows PowerShell.

**Spec:** `docs/p1/2026-10-17-be-reorg-010-resource-state-resolution-design.md` — **approved v2 content** (use the v2 document/patch; the archive's original v1 text is not authoritative).

**Plan approval required before code:** This plan proposes *one additional test-tooling-only compatibility amendment* to the approved spec; see **Runner compatibility finding** below. Approval of this plan authorizes precisely that narrow amendment; otherwise return to design review. This plan is not authorization to modify production code yet.

## Global Constraints

- **Baseline:** use the *actual* BE009+FIX01 Windows checkpoint, whose latest evidence is `948 PHPUnit tests / 9,223 assertions`, no failed/errored/skipped test, Foundation guard 17 checks, and 121 *inherited* PHPStan diagnostics (0 new). BE009 independent review and Git checkpoint remain an explicit prerequisite before production implementation; the v1.1.7 ZIP alone does not prove current Windows Git SHA or status.
- **Before any production edit:** record `git rev-parse HEAD`, `git status --short`, `git diff --check`, hashes of two BE009 FIX01 test files, fresh baseline JUnit and PHPStan diagnostic identities; check expected BE009 canonical files. On unmatched or dirty scope, STOP and reconcile the baseline before patch generation.
- **Standard module layout:** exactly `Application/`, `Application/Ports/`, `Contracts/`, `Infrastructure/Persistence/` below `app/Modules/Workflow/StateResolution/`; no empty decorative directories and no new `Sql...` duplicate repository.
- **Production allowlist:** **six new** files under StateResolution and `Bootstrap/WorkflowStateResolutionFactory.php`, **one changed** `Services/ResourceWorkflowStateResolver.php`, exactly as in File Map. No other production files or changes to `ResourceNativeStateCatalog`, PBAC, BE007/BE008, BE009 Workflow/Lifecycle, API030, routes, controllers, migrations, databases, frontend, OpenAPI or external contracts.
- **Legacy public API:** `final ResourceWorkflowStateResolver` with exactly eight constants and eleven public methods excluding constructor; constructor keeps positional/named order `db`, `resources`, `lifecycles`, `policies` with the original types/default `null`. Preserve exact return types and PHPDoc shapes, including the existing `$trustedCatalogRow` diagnostic. No inheritance, `__call`, aliases, reflection dispatch, static/global engine registry or surprise public accessor.
- **Application interface:** `ResourceStateResolutionPort` has exactly the eleven existing method signatures. `ResourceStateReadPort` has exactly eight read operations; application never uses concrete repository classes and adapter never uses SQL, transactions, authorization, caching, sorting, filtering or exception rewriting.
- **Read adapter:** keep injected Resource Catalog, legacy Lifecycle Repository (BE009 façade) and Policy Repository objects *by identity*. `findAllPoliciesForResource($id)` must call `PolicyRepository::findAll(['resource_id' => $id])` (including `INACTIVE` policies); do not add status criteria.
- **Factory:** `WorkflowStateResolutionFactory::create(?ConnectionInterface $db = null, ?ResourceCatalogRepository $resources = null, ?LifecycleRepository $lifecycles = null, ?PolicyRepository $policies = null): ResourceStateResolutionPort`; instantiate omitted repositories exactly once, in original order, on supplied `$db`; never replace an injected instance or force a new connection if `$db === null`. One fresh adapter and engine per `create()`.
- **Caches:** three private, per-instance stores named `resourceCache`, `memberCodes`, `nativeIds`, with original key normalization, `ANY:` vs `ACTIVE:` partition, initial emptiness and snapshot lifetime. Preserve the as-built raw-key assignment of `trustedCatalogRow` in `stateForAuthorization`; do not 'fix' normalization during relocation or introduce cache invalidation/TTL.
- **Business invariants:** exact ten `ResourceNativeStateCatalog::INDEPENDENT_RESOURCES`; two All States-only (`authorization_log`, `display_setting`); legacy native-state fallback for unbound business resources; ACTIVE Lifecycle/member validation; `user.status` + `active` coercion; `workflow_state` disabled-definition reactivation; no policy-state leakage from client hints; fail-closed edit/setstate rules and original exception `getErrorCode()` + `getContext()` precedence.
- **Policy safety:** Lifecycle detach/reassignment considers *all* policies including `INACTIVE`, ignores only All States (`state_id === null`), and throws `RESOURCE_LIFECYCLE_POLICY_CONFLICT` with original `resource` and `policy_id`; `assertPolicyState` still wraps mapping failures as `POLICY_STATE_NOT_IN_RESOURCE_LIFECYCLE`.
- **Tests:** add exactly 30 uniquely named BE010 cases in seven classes (5 boundary, 5 compatibility, 4 factory, 8 domain, 4 cache, 2 HTTP, 2 real DB); retain all 948 existing tests and all counts of inherited BE007 24 / BE008 26 / BE009 32 / PBAC 91. Target 978 total; no projected assertions number. No test skips, weaker assertions or concurrent processes using `erp_test`.
- **Allowed legacy test edits:** only the two Reflection statements in `BeReorg007AuthorizationFactoryTest::testFactoryUsesOneSuppliedConnectionAcrossDefaults` and `BeReorg008DecisionFactoryTest::testDefaultCollaboratorsUseOneSuppliedConnection`, following `resolver → engine → reader → resources`. All asserted connections and collaborators must remain checked.
- **PHPStan baseline:** report 121 inherited diagnostic *identities*, not 'PHPStan clean'; the two old legacy resolver diagnostics (`return.unusedType` on `stateIdForCode`, `missingType.iterableValue` on `stateForAuthorization`) can relocate to canonical engine only if explicitly mapped by identifier and full message. No ignore/baseline/config changes or new diagnostics; raw exit code 1 remains possible.
- **Acceptance scope:** PHP lint, Reflection, algorithm/behavior parity, no duplicated logic, focused tests, guarded full JUnit 978, no new PHPStan diagnostic, Windows/XAMPP verification, patch apply/reverse, SHA-256, independent whole-branch code review and Git checkpoint. No claim of browser/Apache UAT. Archive has no `backend/vendor` or genuine Windows Git history.

## Runner compatibility finding — narrow spec addendum requiring plan approval

**Verified source conflict:** existing `backend/tools/test-workflow-lifecycle.php::be009AssertJUnit()` checks `//testcase` count `!== 948` and terminates with exit 2, even if the inherited PHPUnit execution passes 978 tests. The approved BE010 v2 spec asks the BE010 runner to invoke BE009 **once**, verify 978 tests, but also freezes BE009 runner source. Those requirements cannot jointly hold. Do **not** mask exit 2, silently reinterpret `BE009 BLOCKED`, or run a second full PHPUnit process.

**Proposed minimal exception (tooling only):** modify **only** the cardinality guard inside `backend/tools/test-workflow-lifecycle.php::be009AssertJUnit`: require **at least 948** testcases instead of exactly 948, retaining its exact 32 BE009 methods, duplicate-name checks, and zero skipped/error/failure checks. The BE010 runner will independently require **exactly 978** testcases and all 30 exact methods; BE008 already uses a lower bound of 916. No production, PHPUnit configuration or inherited acceptance business gate is relaxed. This is an amendment to the tool freeze list, not a revision of any production rule. Add a no-DB RED→GREEN synthetic-JUnit test proving 978 was rejected before this fix and accepted after while 947 and any missing BE009 method still fail. If owner does not approve this one-line tooling change, **pause before implementation** and redesign/approve runner behavior separately.

## Review Focus

Five high-risk input classes and their owning acceptance tests (these are mandated additional assertions, not extra test count):

1. **An `INACTIVE` policy refers to a State excluded by proposed reassignment:** `testReassignmentRejectsInactivePolicyOutsideNextLifecycle`, plus real DB `testInactivePolicyConflictAndAllStatesPolicyRemainDistinct`; no status filter, All States remains harmless.
2. **Disabled `workflow_state.INACTIVE` is cached before another Resource resolves the same code:** `testDisabledWorkflowStateCanReactivateButCannotAuthorizeOtherNativeResource` and `testAnyAndActiveCacheKeysNeverCrossContaminate`; no broadened PBAC match.
3. **Caller presents scope-only context or forged `explicitStateId`:** `testAuthorizationUsesPersistedStateAndRejectsClientHints` and `testPbacDeniedAndAllowedStateRoutesPreserveNoUnauthorizedWrites`; neither ID hint nor input status selects policy State.
4. **Caller passes mixed injected repositories and a separate supplied `$db`:** `testFactoryPreservesEveryInjectedRepositoryIdentity` and `testFactoryDefaultsUseExactlyTheSuppliedDbConnection`; no silent rebind or new DB session.
5. **Lifecycle/State admin updates occur after a resolver caches its old snapshot:** `testMembershipCacheIsScopedToOneResolver` and `testFreshResolverObservesRealDatabaseLifecycleAndStateUpdates`; old instance behavior unchanged, new instance must reload.

---

## File Map and Interfaces

**Production changes: 7 files total — six creates, one modify.** All paths below are relative to repository root.

| Action | File | Single responsibility |
|---|---|---|
| Create | `backend/app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateResolutionPort.php` | Exactly 11 public application operations |
| Create | `backend/app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateReadPort.php` | Exactly 8 read operations |
| Create | `backend/app/Modules/Workflow/StateResolution/Contracts/ResourceStateResolutionErrors.php` | 8 stable public error values + one internal policy conflict value |
| Create | `backend/app/Modules/Workflow/StateResolution/Infrastructure/Persistence/ExistingRepositoryStateReadAdapter.php` | Typed forwards to exactly 3 existing repositories; no SQL |
| Create | `backend/app/Modules/Workflow/StateResolution/Application/ResourceStateResolutionService.php` | All 11 legacy algorithms and three private caches; implements application port |
| Create | `backend/app/Bootstrap/WorkflowStateResolutionFactory.php` | Stateless constructor composition, exact dependency identity |
| Modify | `backend/app/Services/ResourceWorkflowStateResolver.php` | Final compatibility façade; one `private readonly ... $engine`; 8 constants and 11 forwards |

**Test/tooling changes:**

| Action | File |
|---|---|
| Create | `backend/tests/unit/BeReorg010StateResolutionBoundaryTest.php` (5) |
| Create | `backend/tests/unit/BeReorg010StateResolutionCompatibilityTest.php` (5) |
| Create | `backend/tests/database/BeReorg010StateResolutionFactoryTest.php` (4) |
| Create | `backend/tests/unit/BeReorg010StateResolutionDomainTest.php` (8) |
| Create | `backend/tests/unit/BeReorg010StateResolutionCacheTest.php` (4) |
| Create | `backend/tests/database/BeReorg010StateResolutionHttpTest.php` (2) |
| Create | `backend/tests/database/BeReorg010StateResolutionDatabaseTest.php` (2) |
| Create | `backend/tests/_support/Workflow/BeReorg010StateReadFixture.php` (fake **read port** for behavior tests, no fake production SQL/authorization) |
| Create | `backend/tools/test-resource-state-resolution.php` (guarded runner, source/JUnit method inventory) |
| Modify | `backend/tests/unit/BeReorg007AuthorizationFactoryTest.php` (one Reflection chain) |
| Modify | `backend/tests/unit/BeReorg008DecisionFactoryTest.php` (one Reflection chain) |
| Proposed narrow tooling amend | `backend/tools/test-workflow-lifecycle.php` (one JUnit lower-bound guard, approval required) |
| Add | `docs/p1/BE-REORG-010_Resource_State_Resolution_Implementation_Plan_2026-10-17.md` (this plan) |

**Interface exactness:**

```php
// Namespace: App\Modules\Workflow\StateResolution\Application\Ports
// The existing legacy signatures and PHPDocs MUST be copied exactly.
interface ResourceStateResolutionPort
{
    public function resource(string $type): array; // array<string,mixed>
    public function lifecycleId(string $type): ?int;
    public function persistedStateId(string $type, array $row): ?int; // array<string,mixed>
    public function stateIdForCode(string $type, mixed $code): ?int;
    public function codeForStateId(string $type, int $stateId): string;
    public function allowedPolicyStateCodes(string $type): array; // list<string>
    public function assertLifecycleReassignmentSafe(string $type, ?int $nextLifecycleId): void;
    public function initialCode(string $type): ?string;
    public function assertLifecycleStateCompatibility(string $type, int $lifecycleId): void;
    public function assertPolicyState(int $resourceId, int $stateId): void;
    public function stateForAuthorization(string $type, ?array $persistedTarget, ?int $explicitStateId,
        ?array $trustedCatalogRow = null, string $action = '', array $input = []): ?int;
}

interface ResourceStateReadPort
{
    public function findResourceByType(string $type): ?array;
    public function findResource(int $id): ?array;
    public function findLifecycle(int $id): ?array;
    public function findLifecycleStates(int $lifecycleId): array;
    public function findActiveWorkflowState(int $id): ?array;
    public function findActiveWorkflowStateByCode(string $code): ?array;
    public function findWorkflowStateByCode(string $code): ?array;
    public function findAllPoliciesForResource(int $resourceId): array;
}
```

PHPDoc (`array<string,mixed>|null`, `list<array<string,mixed>>`, params/returns from §2.1 of approved spec) is mandatory on port, service and adapter. The reader may forward `int $id` to existing legacy methods typed `mixed`, never change legacy methods. No `update*`, `insert*`, `table()` or `trans*` surface on the read port.

**Canonical constructors:**

```php
// Service implements ResourceStateResolutionPort; do not inject concrete repositories.
public function __construct(ResourceStateReadPort $reader);

// Adapter implements ResourceStateReadPort. Retain exact property names for the two reflection assertions.
public function __construct(
    private readonly ResourceCatalogRepository $resources,
    private readonly LifecycleRepository $lifecycles,
    private readonly PolicyRepository $policies,
);

// Final WorkflowStateResolutionFactory, private constructor, no mutable properties.
public static function create(
    ?ConnectionInterface $db = null,
    ?ResourceCatalogRepository $resources = null,
    ?LifecycleRepository $lifecycles = null,
    ?PolicyRepository $policies = null,
): ResourceStateResolutionPort;
```

**Stable error code authority:** `ResourceStateResolutionErrors` (final, private constructor, constants only) defines `ERROR_STATE_NOT_MAPPED`, `ERROR_RESOURCE_LIFECYCLE_INVALID`, `ERROR_INITIAL_STATE_INVALID`, `ERROR_POLICY_STATE_INVALID`, `ERROR_STATE_CONTEXT_MISMATCH`, `ERROR_LIFECYCLE_SELF_REFERENCE`, `ERROR_INITIAL_STATE_REQUIRED`, `ERROR_TRANSITION_REQUIRED` with **byte-identical** values in approved spec §2.1, plus `ERROR_LIFECYCLE_POLICY_CONFLICT = 'RESOURCE_LIFECYCLE_POLICY_CONFLICT'`. Legacy re-exports only the first eight, engine uses those shared constants and never changes `DomainException` context.

**Existing read call mapping** (adapter ONLY, preserve raw array results/order):

| Read port method | Exact legacy repository call |
|---|---|
| `findResourceByType($type)` | `$resources->findResourceByType($type)` |
| `findResource($id)` | `$resources->findResource($id)` |
| `findLifecycle($id)` | `$lifecycles->findLifecycle($id)` |
| `findLifecycleStates($id)` | `$lifecycles->findLifecycleStates($id)` |
| `findActiveWorkflowState($id)` | `$lifecycles->findActiveWorkflowState($id)` |
| `findActiveWorkflowStateByCode($code)` | `$lifecycles->findActiveWorkflowStateByCode($code)` |
| `findWorkflowStateByCode($code)` | `$lifecycles->findWorkflowStateByCode($code)` |
| `findAllPoliciesForResource($id)` | `$policies->findAll(['resource_id' => $id])` |

**Baseline code origin:** `backend/app/Services/ResourceWorkflowStateResolver.php` from *post-BE009+FIX01 checkout*. Lift private methods and algorithm statement ordering unchanged; replace collaborator reads with the read port above, replace old `self::ERROR_*` references with `ResourceStateResolutionErrors` constants. No call sites elsewhere change. Keep `ResourceNativeStateCatalog` and `PositiveId` static use unchanged.

## Exact BE010 Test Inventory (30 methods; do not add or rename methods without re-approval)

```text
BeReorg010StateResolutionBoundaryTest (5)
  testApplicationPortDeclaresExactlyElevenOperations
  testReadPortDeclaresExactlyEightReadOnlyOperations
  testContractsKeepEightPublicErrorsAndInternalConflictCode
  testReadAdapterImplementsPortWithThreeExistingCollaborators
  testPortAndAdapterDoNotExposeMutationOrSqlSurface

BeReorg010StateResolutionCompatibilityTest (5)
  testFacadeConstructorRetainsFourPositionalAndNamedParameters
  testFacadeRetainsEightConstantsAndElevenMethodSignatures
  testFacadeKeepsSingleEngineWithExplicitDelegation
  testFacadePreservesReturnShapesAndDomainErrorContext
  testBe007AndBe008ReflectionPathsStillVerifyRepositoryConnection

BeReorg010StateResolutionFactoryTest (4)
  testFactoryCreatesFreshEngineReaderAndEmptyCaches
  testFactoryPreservesEveryInjectedRepositoryIdentity
  testFactoryDefaultsUseExactlyTheSuppliedDbConnection
  testReadAdapterPassesThroughAllEightReadsIncludingInactivePolicies

BeReorg010StateResolutionDomainTest (8)
  testTenIndependentResourcesAndTwoAllStatesOnlyBehaveExactly
  testLifecycleBoundResourceAcceptsActiveMemberCodesBeyondNativeVocabulary
  testShieldStateUsesPersistedStatusAndValidatedActiveFlag
  testDisabledWorkflowStateCanReactivateButCannotAuthorizeOtherNativeResource
  testReassignmentRejectsInactivePolicyOutsideNextLifecycle
  testInitialStateAndMemberValidationFailClosedWithStableCodes
  testStateIdAndCodeMappingRejectInvalidOrNonmemberStates
  testAuthorizationUsesPersistedStateAndRejectsClientHints

BeReorg010StateResolutionCacheTest (4)
  testResourceCacheIsNormalizedAndScopedToOneResolver
  testMembershipCacheIsScopedToOneResolver
  testAnyAndActiveCacheKeysNeverCrossContaminate
  testTrustedCatalogRowPreservesHistoricalCacheKeyAndScopeSemantics

BeReorg010StateResolutionHttpTest (2)
  testApi030ResourceAndLifecycleRoutesPreserveErrorsAndCsrf
  testPbacDeniedAndAllowedStateRoutesPreserveNoUnauthorizedWrites

BeReorg010StateResolutionDatabaseTest (2)
  testInactivePolicyConflictAndAllStatesPolicyRemainDistinct
  testFreshResolverObservesRealDatabaseLifecycleAndStateUpdates
```

**TDD rule:** write behavior/contract tests first; verify a meaningful RED (missing new port/adapter/engine/factory), not a missing vendor or uninitialized DB; then minimum implementation; verify GREEN. For extra *characterization* checks of already-working legacy behavior, the baseline pass is the proof of existing behavior: do not deliberately introduce a bug just to force RED. Every task's final gate includes its new class(es) and affected inherited tests. Use guarded DB only; `DatabaseTestTrait` may refresh data. Test fixture IDs/codes must fit real schema limits, especially `provinces.code VARCHAR(20)`.

---

### Task 1: Freeze Baseline, Define the Two Ports/Contracts and Read Adapter

**Files:**
- Create: `backend/app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateResolutionPort.php`
- Create: `backend/app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateReadPort.php`
- Create: `backend/app/Modules/Workflow/StateResolution/Contracts/ResourceStateResolutionErrors.php`
- Create: `backend/app/Modules/Workflow/StateResolution/Infrastructure/Persistence/ExistingRepositoryStateReadAdapter.php`
- Test: `backend/tests/unit/BeReorg010StateResolutionBoundaryTest.php` — exact 5 methods in inventory.

**Interfaces:** Produces `ResourceStateResolutionPort` (11 methods) and `ResourceStateReadPort` (8 methods), `ResourceStateResolutionErrors` (9 constants), and adapter `ExistingRepositoryStateReadAdapter implements ResourceStateReadPort` accepting three exact injected existing repository objects. Later tasks consume those names without inventing extra interfaces.

- [ ] **Step 1: Record the non-destructive BE009 baseline** — in Windows PowerShell from `D:\XAMPP\htdocs\erp`, record current SHA/status and `git diff --check`; require a reviewed BE009+FIX01 checkpoint and inspect the two Reflection assertions. Capture existing 948-case JUnit, full accepted 121-diagnostic PHPStan baseline, and hashes of BE009 fixture fixes. **Expected:** unchanged accepted baseline, 948 JUnit cases; no `git apply` or DB mutation at this step. If checkpoint/review unavailable, request it instead of assuming v1.1.7 is the same working tree.

```powershell
Set-Location D:\XAMPP\htdocs\erp
git rev-parse HEAD
git status --short
git diff --check
Get-FileHash .\backend\tests\database\BeReorg009LifecycleHttpTest.php -Algorithm SHA256
Get-FileHash .\backend\tests\database\BeReorg009LifecycleLockTest.php -Algorithm SHA256
```

- [ ] **Step 2: Write five failing Boundary tests**, with the inventory names above. In `testApplicationPortDeclaresExactlyElevenOperations`, Reflection compares *sorted exact method names*, method order/required/optional types against spec §2.1, not merely `count === 11`. In `testReadPortDeclaresExactlyEightReadOnlyOperations`, assert the exact eight names/argument types and no other public methods. In `testContractsKeepEightPublicErrorsAndInternalConflictCode`, assert all nine literal values from §2.1 and §3.2. In `testReadAdapterImplementsPortWithThreeExistingCollaborators`, assert adapter interface and constructor types/property identity; in `testPortAndAdapterDoNotExposeMutationOrSqlSurface`, assert neither port nor adapter has public mutation methods or direct SQL/transaction dependencies. Representative assertions:

```php
$this->assertSame('WF_AUTHORIZATION_STATE_MISMATCH', ResourceStateResolutionErrors::ERROR_STATE_CONTEXT_MISMATCH);
$this->assertSame('RESOURCE_LIFECYCLE_POLICY_CONFLICT', ResourceStateResolutionErrors::ERROR_LIFECYCLE_POLICY_CONFLICT);
$this->assertSame(8, count((new ReflectionClass(ResourceStateReadPort::class))->getMethods(ReflectionMethod::IS_PUBLIC)));
```

- [ ] **Step 3: Run Boundary RED** from `backend/`:

```powershell
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg010StateResolutionBoundaryTest.php
```

Expected: fails for the missing new port/Contracts/adapter classes (NOT missing Composer vendor or unrelated DB failure). If PHP dependencies are absent in the snapshot environment, record `PENDING WINDOWS`, not fake a RED.

- [ ] **Step 4: Implement the four files exactly as declared**, adding PHPDocs for all array types. Reader adapter delegates the eight methods without transforming arguments, filter or results; it has **private readonly** `$resources`, `$lifecycles`, `$policies`, and no `$db`. `ResourceStateResolutionErrors` is final with private constructor. Do not introduce any additional public method.

- [ ] **Step 5: Run Boundary GREEN + PHP lint**:

```powershell
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg010StateResolutionBoundaryTest.php
php -l app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateResolutionPort.php
php -l app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateReadPort.php
php -l app/Modules/Workflow/StateResolution/Contracts/ResourceStateResolutionErrors.php
php -l app/Modules/Workflow/StateResolution/Infrastructure/Persistence/ExistingRepositoryStateReadAdapter.php
```

Expected: 5/5 PASS, zero syntax errors. Commit only files created for Task 1, then rerun the affected existing characterization suite; no unauthorized production edits.

### Task 2: Canonical State Resolution Algorithm and Instance-local Cache Parity

**Files:**
- Create: `backend/app/Modules/Workflow/StateResolution/Application/ResourceStateResolutionService.php`
- Create: `backend/tests/_support/Workflow/BeReorg010StateReadFixture.php`
- Test: `backend/tests/unit/BeReorg010StateResolutionDomainTest.php` — 8 methods.
- Test: `backend/tests/unit/BeReorg010StateResolutionCacheTest.php` — 4 methods.

**Interfaces:** Consumes `ResourceStateReadPort` from Task 1; produces `ResourceStateResolutionService implements ResourceStateResolutionPort` with `__construct(ResourceStateReadPort $reader)`, **exact 11 methods** and private `members(int $lifecycleId): array`. Its private readonly property is named `$reader` for the approved Reflection chain. It owns all three empty instance caches.

- [ ] **Step 1: Create a strict read-port fixture for test behavior**, `BeReorg010StateReadFixture implements ResourceStateReadPort`, with mutable indexed input rows, append-only read call log, eight exact read methods (no fake application/SQL code). Script `findAllPoliciesForResource` so it may contain `status='INACTIVE'`. Tests assert *public outputs and domain exceptions*, never only that mock methods were invoked. Use known positive State IDs and explicit `ACTIVE`/`INACTIVE` status rows.

- [ ] **Step 2: Write the eight Domain RED tests** (names locked in inventory) with explicit behavioral assertions:

| Test | Required assertions / concrete scenario |
|---|---|
| Ten independent + All States-only | Compare entire ten-element `INDEPENDENT_RESOURCES` list in order; `authorization_log` and `display_setting` `allowedPolicyStateCodes() === []`, `persistedStateId()` returns `null` without `status`, binding rejected as `WF_LIFECYCLE_SELF_REFERENCE` with `resource` context. |
| Lifecycle members beyond native | Bound `province` ACTIVE lifecycle containing `DRAFT`, `APPROVED` yields those codes in member order; IDs resolve; unbound `province` preserves `ACTIVE`, `INACTIVE`, `CLOSED` native fallback without becoming an eleventh independent resource. |
| Shield persisted state | `status='ACTIVE'` + active `true/1/'1'` => ACTIVE; inactive `false/0/'0'` => INACTIVE; `status='LOCKED'` maps to INACTIVE; missing/noncanonical active e.g. `2` throws `WF_RESOURCE_STATE_NOT_MAPPED` with resource context. |
| Disabled `workflow_state` | `findWorkflowStateByCode('INACTIVE')` may return disabled definition and allow reactivation, but `policy`/`resource` using active-only read for same code must throw `WF_RESOURCE_STATE_NOT_MAPPED`; compare actual port read paths. |
| Reassignment & INACTIVE policy | A candidate Lifecycle excluding a policy's `state_id` throws `RESOURCE_LIFECYCLE_POLICY_CONFLICT` with exact resource/policy ID, **even when policy status is INACTIVE**; an All States (`null`) policy does not block. |
| Initial & member failures | Zero/two initial members -> `WF_INITIAL_STATE_INVALID`; inactive Lifecycle/member and malformed positive State ID -> `WF_RESOURCE_LIFECYCLE_INVALID` with the original field/context; invalid member code regex/length 51 rejected. |
| Code/ID parity | `stateIdForCode` trims/uppercases code; `codeForStateId` returns member-only code for bound resource; wrong type, blank/unknown code, nonmember/disabled ID throw `WF_RESOURCE_STATE_NOT_MAPPED` with expected keys. |
| Authorization provenance | Persisted row determines State; different explicit State throws `WF_AUTHORIZATION_STATE_MISMATCH`; scope-only context => `null` unless explicit hint supplied; create with wrong initial status => `WF_INITIAL_STATE_REQUIRED`; bound edit with `status` or setstate without explicit source => `WF_TRANSITION_REQUIRED`; no caller status becomes policy authority. |

Example core assertions:

```php
$this->assertNull($engine->persistedStateId('display_setting', ['metadata' => 'not a status']));
$this->assertSame($expectedApprovedId, $engine->stateIdForCode('province', ' approved '));
$this->assertSame('WF_TRANSITION_REQUIRED', $caught->getErrorCode());
$this->assertSame(['resource' => 'province'], $caught->getContext());
```

The `caught` exception must come from the asserted operation; never assert a stale exception from a previous try/catch. For every checked error assert `getErrorCode()` and the *actual expected* `getContext()` shape from the legacy source (not an invented context).

- [ ] **Step 3: Write four Cache RED tests** *before engine implementation*:

| Test | Required assertions |
|---|---|
| Resource cache | Same service instance normalizes `trim/lowercase` keys and reuses the first catalog row; a new service+fixture observes changed catalog data. |
| Membership cache | Repeated reads in one instance reuse a previously validated Lifecycle membership; a new resolver reads changed membership and fails closed if inactive. |
| `ANY:` / `ACTIVE:` | A cached disabled `workflow_state.INACTIVE` must not be used to make `policy.INACTIVE` succeed on the same service. Lookup logs prove distinct read port methods were used. |
| Trusted catalog row / scope | Preserve **raw type key** assignment of caller-trusted `$trustedCatalogRow`, scope-only return `null`, explicit hint mismatch; verify behavior with mixed-case input against a pre-refactor legacy resolver on the same fixture data where possible. |

- [ ] **Step 4: Run Domain+Cache RED**:

```powershell
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg010StateResolutionDomainTest.php tests/unit/BeReorg010StateResolutionCacheTest.php
```

Expected: 12 tests fail for missing canonical class/behavior, not Composer/environment failure. A case that characterizes already-existing behavior may legitimately pass against the legacy class, but must first be recorded as baseline characterization rather than an artificially broken test.

- [ ] **Step 5: Implement canonical service**: transfer all eleven original method bodies and `members()` from the accepted `ResourceWorkflowStateResolver` without reordering logic, apart from eight concrete read substitutions via `$this->reader`, shared error-constant ownership via `ResourceStateResolutionErrors` and the injected read-port constructor. Preserve `resourceCache`, `memberCodes`, `nativeIds` types/defaults and assignments exactly; do not add a fourth cache or duplicate old algorithm. Keep `ResourceNativeStateCatalog` / `PositiveId` use unchanged; no `ConnectionInterface`, `ResourceCatalogRepository`, `LifecycleRepository`, `PolicyRepository`, SQL or mutation in Application imports.

- [ ] **Step 6: Run Domain+Cache GREEN and inherited State tests**:

```powershell
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg010StateResolutionDomainTest.php tests/unit/BeReorg010StateResolutionCacheTest.php
php vendor/bin/phpunit --no-coverage tests/unit/P1Wf006StateAwarePbacAndLocationTransitionsTest.php tests/unit/P1Wf007NativeStateContractTest.php
php -l app/Modules/Workflow/StateResolution/Application/ResourceStateResolutionService.php
```

Expected: 12/12 BE010 tests PASS and existing WF006/WF007 remain PASS; zero new class/type errors. Commit Task 2 sources/tests/fixture only. A pure code-structure comparison must verify the old implementations are neither reordered nor enhanced while migrating; intentional differences are limited to collaborators/constants/ownership.

### Task 3: Stateless Factory, Legacy Facade, and Existing Reflection Contracts

**Files:**
- Create: `backend/app/Bootstrap/WorkflowStateResolutionFactory.php`
- Modify: `backend/app/Services/ResourceWorkflowStateResolver.php`
- Modify: `backend/tests/unit/BeReorg007AuthorizationFactoryTest.php` (specified assertion only)
- Modify: `backend/tests/unit/BeReorg008DecisionFactoryTest.php` (specified assertion only)
- Test: `backend/tests/unit/BeReorg010StateResolutionCompatibilityTest.php` (5)
- Test: `backend/tests/database/BeReorg010StateResolutionFactoryTest.php` (4)

**Interfaces:** Factory accepts exactly four existing constructor dependencies in original order and returns `ResourceStateResolutionPort`; facade has `private readonly ResourceStateResolutionPort $engine` assigned once and delegates eleven calls. The factory owns three exact default-repository constructions, in order `ResourceCatalogRepository`, `LifecycleRepository`, `PolicyRepository`, then adapter and canonical service. No double initialization of collaborators.

- [ ] **Step 1: Write nine tests** in the two new classes (inventory names exact). In Compatibility tests compare Reflection constructor parameter names/types/defaults, complete constants map and 11 signature map *against a frozen BE009 baseline capture*, assert façade has only `$engine` and each call delegates by output/exception context. `testBe007AndBe008ReflectionPathsStillVerifyRepositoryConnection` inspects `resolver->engine->reader->resources`, then `$resources->db` and the corresponding lifecycle/policy delegate DB identities. In Factory tests assert distinct engine+reader objects for two calls, same three supplied collaborator identities, all defaults use the **exact supplied** connection, and all eight adapter read methods return the original repository rows (including policy status `INACTIVE`). Do not test mock call counts as a substitute for returned values.

- [ ] **Step 2: Run RED**:

```powershell
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg010StateResolutionCompatibilityTest.php tests/database/BeReorg010StateResolutionFactoryTest.php
```

Expected: failure from absent `WorkflowStateResolutionFactory`/missing composition; baseline existing BE007/BE008 tests still green before façade relocation. `DatabaseTestTrait` classes must guard `erp_test`/`dinktech_` before touching DB, mirroring BE008 factory tests.

- [ ] **Step 3: Implement factory and legacy façade**. Use the exact `create()` signature in File Map. Keep legacy constructor unchanged. Re-export eight constants as references to `ResourceStateResolutionErrors`, preserve PHPDoc and typed public methods; replace private algorithm with exactly eleven explicit forwarding methods, with no `__call`, no private copy of caches/repositories and no extra public methods. Do not change concrete typehints in the 27 consumers.

- [ ] **Step 4: Adapt only two prior Reflection assertions** after confirming actual errors due to `resources` property relocation, replacing (BE007):

```php
$stateResolver = $this->value($validator, 'workflowStates');
$stateResources = $this->value($this->value($this->value($stateResolver, 'engine'), 'reader'), 'resources');
$this->assertSame($this->db, $this->value($stateResources, 'db'));
```

And (BE008) follow `workflowStates → engine → reader → resources`, retaining the final identity/`db` assertion. No other test edits. Run both prior focused methods after each change; if a different private contract broke, investigate instead of weakening tests.

- [ ] **Step 5: Run GREEN for all nine new and both inherited factory tests**:

```powershell
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg010StateResolutionCompatibilityTest.php tests/database/BeReorg010StateResolutionFactoryTest.php
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg007AuthorizationFactoryTest.php tests/unit/BeReorg008DecisionFactoryTest.php
php -l app/Bootstrap/WorkflowStateResolutionFactory.php
php -l app/Services/ResourceWorkflowStateResolver.php
```

Expected: BE010 9/9 PASS, all BE007/BE008 factory tests remain PASS; constructor signature parity exact and no authorization behavior drift. Commit only files in Task 3.

### Task 4: HTTP/CSRF and PBAC In-process Integration

**Files:**
- Create: `backend/tests/database/BeReorg010StateResolutionHttpTest.php` (exact 2 methods).
- Reuse, do not edit: `backend/tests/_support/Foundation/RuntimeFixture.php`, `backend/tests/database/BeReorg009LifecycleHttpTest.php`, `backend/tests/database/BeReorg007AuthorizationHttpTest.php`.

**Interfaces:** Consumes existing `FeatureTestTrait`, `DatabaseTestTrait`, Shield authentication helpers, API030 routes and ResourceStateResolution façade. Does not add a controller or endpoint.

- [ ] **Step 1: Write `testApi030ResourceAndLifecycleRoutesPreserveErrorsAndCsrf`** with an authenticated actor/resource fixture: GET `/api/v1/lifecycles/{id}/states` and `/api/v1/lifecycles/{id}/transitions` return existing 200/envelope; reject a request with absent/invalid CSRF exactly as existing HTTP test helpers establish, and verify DB rows unchanged. An invalid Lifecycle configuration must preserve existing error code/status and audit behavior. Use actual route definitions at `backend/app/Config/Routes.php:59-92`; do not invent `/api/v1/...` endpoints or bypass filters.

- [ ] **Step 2: Write `testPbacDeniedAndAllowedStateRoutesPreserveNoUnauthorizedWrites`** using the existing `province/{id}/actions/set-state` route: denied actor returns **403 `AUTH_POLICY_DENIED`** without State row mutation; invalid transition by authorized actor returns **422 `WF_INVALID_TRANSITION`** without writes; an allowed configured action preserves response envelope, target State and audit. Check persisted record before/after using the *same DB connection*. Generate province code of length **<= 20** (e.g. `'BE010H-' . uniqid()`), otherwise fixture setup fails before PBAC assertions.

- [ ] **Step 3: Run these tests and inspect both HTTP response bodies and DB assertions**:

```powershell
php vendor/bin/phpunit --no-coverage tests/database/BeReorg010StateResolutionHttpTest.php
php vendor/bin/phpunit --no-coverage tests/database/BeReorg009LifecycleHttpTest.php tests/database/BeReorg007AuthorizationHttpTest.php
```

Expected: BE010 HTTP 2/2 PASS and inherited HTTP cases PASS, no skipped test, no new endpoint or API envelope change. For pure regression characterization tests, a first-run PASS is acceptable only if the canonical code is already present and the assertions cover the specified HTTP/DB behavior; never weaken them to engineer a PASS. Commit new HTTP test only.

### Task 5: Real Database Policy Conflict and Per-request Refresh

**Files:**
- Create: `backend/tests/database/BeReorg010StateResolutionDatabaseTest.php` (2 methods).
- Reuse without change: `backend/tests/_support/Workflow/BeReorg009LifecycleFixture.php`, `backend/tests/_support/Foundation/RuntimeGuard.php`, `backend/app/Repositories/{LifecycleRepository,PolicyRepository,ResourceCatalogRepository}.php`.

**Interfaces:** Real MySQL/MariaDB `erp_test` only, existing repository public APIs. No mock of persisted rows/transactions. Keep test-owned cleanup before `DatabaseTestTrait::tearDown()` as required by migration guards.

- [ ] **Step 1: Write `testInactivePolicyConflictAndAllStatesPolicyRemainDistinct`**. Seed valid Resource/Lifecycle/State membership using existing fixtures, one State-scoped Policy marked `INACTIVE` and one All States Policy with `state_id=null`; call `assertLifecycleReassignmentSafe()` for candidate lifecycle excluding the inactive policy's State and assert `DomainException('RESOURCE_LIFECYCLE_POLICY_CONFLICT')` with **exact `resource`/`policy_id`**. Remove only the conflicting policy within disposable fixture, then assert the All States Policy does not block reassignment. Confirm no persisted resource/policy write caused by resolver itself, and restore any test-only `INACTIVE` record in `tearDown()` before migrations refresh.

- [ ] **Step 2: Write `testFreshResolverObservesRealDatabaseLifecycleAndStateUpdates`**. Construct resolver A, load lifecycle membership and State using real repositories on `$this->db`, make controlled legitimate admin update that changes Lifecycle assignment/member or disables an applicable State, then construct resolver B on the same supplied DB and assert it observes updated State / throws `WF_RESOURCE_LIFECYCLE_INVALID` as appropriate. Repeated A reads may use old cached snapshot; never assert cross-instance stale data is okay. Restore state before fixture teardown and verify `SELECT DATABASE()` is exactly `erp_test`. Avoid test-owned transaction nesting that could hide changes from B.

- [ ] **Step 3: Run both real DB tests and inherited workflow suites sequentially**:

```powershell
php vendor/bin/phpunit --no-coverage tests/database/BeReorg010StateResolutionDatabaseTest.php
php vendor/bin/phpunit --no-coverage tests/unit/P1Wf006StateAwarePbacAndLocationTransitionsTest.php tests/unit/P1Wf007NativeStateContractTest.php
```

Expected: 2/2 BE010 DB cases PASS, no database statuses left `INACTIVE` unintentionally, inherited State tests PASS; no migrations/schema changes. Commit new DB test only.

### Task 6: Guarded Runner and Backward-Compatible BE009 JUnit Count

**Files:**
- Create: `backend/tools/test-resource-state-resolution.php`.
- Modify **only with this plan's explicit owner approval**: `backend/tools/test-workflow-lifecycle.php::be009AssertJUnit` cardinality comparison.
- Test evidence: synthetic JUnit script run from shell and guarded runner CLI negative/help invocations (no extra PHPUnit test methods).

**Interfaces:** `be010Run($argv): int` exposed only in CLI runner file; `be010RequiredMethods()` exact 7 classes/30 method names from inventory, `be010TestFiles()` exact relative paths, `be010Preflight()` fail-before-DB, `be010AssertJUnit()` full 978 JUnit and exact names. Accept exactly `--help` (exit 0, no DB) or `--confirm-test-db` (DB guarded); all other args exit 2. Calls `tools/test-workflow-lifecycle.php --confirm-test-db` via `proc_open()` exactly **once**; does not change inherited guard or PHPStan configuration.

- [ ] **Step 1: Create a no-DB, RED synthetic JUnit cardinality proof** against the existing BE009 helper. Using a valid 948-case `be-reorg-009.xml` from accepted baseline (or build synthetic XML with the exact 32 BE009 cases), add 30 unique non-skipped BE010 testcase nodes. Call `be009AssertJUnit($synthetic978, be009RequiredMethods())`; original function must reject 978 with `must contain exactly 948`. Separately assert 947 and missing/duplicated BE009 testcase are rejected. No actual PHPUnit/DB run needed for this focused change.

- [ ] **Step 2: Make the single tooling compatibility change** after owner approves this plan:

```php
// Existing BE009 validator: allow newer full suites but keep its exact 32-case gate.
if ($all === false || $all->length < 948) {
    throw new RuntimeException('BE009 full JUnit must contain at least 948 testcases (916 baseline + 32 BE009).');
}
```

No other BE009 runner edits. Re-run synthetic 948/978/947/missing-BE009 trials: 948 and 978 accepted if all BE009 cases present and clean; 947, duplicates, skipped or missing BE009 remain rejected. This is a compatibility-only amendment; the BE010 runner's **exact 978** validation prevents count inflation from concealing missing tests.

- [ ] **Step 3: Write and execute BE010 CLI preflight RED**. Missing runner, malformed method inventory and invalid CLI argument must fail safely (no DB) before implementation. Ensure tests/source inventory is checked with `preg_match_all('/^\s*public\s+function\s+(test[A-Za-z0-9_]+)\s*\(/m', ...)` and exact names, duplicates forbidden, seven files/30 methods precisely. Reject absent `vendor` or missing prerequisites before launching inherited BE009. Treat process spawn failure / missing `DOMDocument` / missing fresh JUnit as blocked exit 2, never PASS.

- [ ] **Step 4: Implement `test-resource-state-resolution.php`**. In `--confirm-test-db` path, delete stale `backend/build/logs/be-reorg-010.xml` and relevant prior inherited JUnit before guarded run; invoke BE009 child once; verify inherited child exit 0 or 1 *only* if log confirms `PHPUnit exit code: 0`, PHPStan actually completed, Foundation 17 checks, inherited BE007/BE008/BE009 gates, and exact 978 JUnit with zero `error`/`failure`/`skipped` nodes and all 30 exact BE010 methods. Copy fresh inherited JUnit to `be-reorg-010.xml`, append honest summary to root `test-result.log`. Return raw child PHPStan code (0 or 1); never reinterpret 1 as clean; blocked/malformed/failure => exit 2. Do not execute PHPUnit twice.

- [ ] **Step 5: Run CLI safety and PHP lint**:

```powershell
php tools/test-resource-state-resolution.php --help
php tools/test-resource-state-resolution.php
php tools/test-resource-state-resolution.php --not-a-real-option
php -l tools/test-resource-state-resolution.php
php -l tools/test-workflow-lifecycle.php
```

Expected: help exit 0 and zero DB access, missing/invalid argument exit 2, lint PASS. Verify `be010RequiredMethods()` and source list equal the immutable inventory; record synthetic JUnit green results. Commit two tooling files only. **Do not call `--confirm-test-db`** until focused tests passed and DB is known disposable.

### Task 7: Full Acceptance, Static Diagnostic Parity, Review and Release

**Files:** No extra production files. Create release-only verification evidence, SHA-256 manifest and patch; save accepted plan/spec to `docs/p1`. This task does not authorize application schema/API changes.

**Interfaces:** Tests/guarded runner from Tasks 1–6; original BE009+FIX01 Git SHA as patch base. A reviewer checks the whole branch, not only a test summary.

- [ ] **Step 1: Compare public Reflection signatures with baseline** for constructor names/types/defaults, eight constants and all eleven methods, including `stateForAuthorization` six parameters and return type. Compare canonical algorithm against old source after mechanically normalizing allowed substitutions (reader calls, error constant owner, class/property names); explain every other changed statement. Confirm adapter alone owns concrete imports and engine three caches start empty; `git diff --check` PASS.

- [ ] **Step 2: Run all seven BE010 suites sequentially**, never concurrently on `erp_test`:

```powershell
Set-Location D:\XAMPP\htdocs\erp\backend
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg010StateResolutionBoundaryTest.php tests/unit/BeReorg010StateResolutionDomainTest.php tests/unit/BeReorg010StateResolutionCacheTest.php
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg010StateResolutionCompatibilityTest.php tests/database/BeReorg010StateResolutionFactoryTest.php
php vendor/bin/phpunit --no-coverage tests/database/BeReorg010StateResolutionHttpTest.php tests/database/BeReorg010StateResolutionDatabaseTest.php
```

Expected: exactly 30 BE010 cases, no errors/failures/skips; all named tests (including DB/HTTP) actually executed.

- [ ] **Step 3: Run ONE guarded full acceptance process**:

```powershell
php tools/test-resource-state-resolution.php --confirm-test-db
Write-Host "BE010 runner exit code: $LASTEXITCODE"
```

Expected: full PHPUnit `OK (978 tests, ... assertions)` with no failure/error/skip; JUnit `backend/build/logs/be-reorg-010.xml` has exactly 978 `<testcase>` elements and all 30 named BE010 cases; Foundation guard 17 checks, inherited BE007=24/BE008=26/BE009=32/PBAC=91 and BE001–BE006 gates intact. PHPStan raw 1 may legitimately make runner exit 1; never call this gate clean without the diagnostic comparison below. No browser/Apache UAT assertion.

- [ ] **Step 4: Compare PHPStan *identities* to accepted BE009 baseline**. Prefer `php vendor/bin/phpstan analyse --error-format=json --no-progress` on both frozen baseline and BE010 (or compare full normal output), normalize only expected canonical relocation of **two** preexisting diagnostics: `Services/ResourceWorkflowStateResolver.php::stateIdForCode` (`return.unusedType`) and `::stateForAuthorization` (`missingType.iterableValue`). If either disappears, document as resolved; if it moves to `Modules/Workflow/StateResolution/Application/ResourceStateResolutionService.php`, match exact message/identifier and source semantic method. **Any unmatched diagnostic fails acceptance** even if raw counts both equal 121. Do not change phpstan.neon, ignores or baselines.

- [ ] **Step 5: Independent whole-branch review before declaring acceptance**. Request a reviewer independent of the implementer if available; review explicitly: cache ANY/ACTIVE boundaries; policy INACTIVE filter; persisted-state trust; exact error precedence/context; collaborators' DB identity; 978-case runner compatibility/safety; two BE007/BE008 Reflection changes. Fix Critical/Important findings with RED→GREEN test and rerun the full suite. If only author self-review is available, label it explicitly as weaker and do not misstate it as independent.

- [ ] **Step 6: Release incremental patch from *verified* BE009+FIX01 Windows base**. `git diff --check`, `git diff --stat`, `git diff --binary <baseline-SHA> HEAD` (or a verified staged equivalent), `git apply --check` on a disposable checkout of that exact base, apply/reverse, recorded SHA-256, file manifest and evidence. Ensure patch includes exactly allowed 7 production paths, 7 new BE010 test classes, one read fixture, runner BE010, two previous Reflection assertion edits, and **the one expressly approved test-tooling compatibility guard**, with no unrelated changes. Never regenerate BE009 ZIP as the patch base or include unrelated uncommitted files.

- [ ] **Step 7: Git checkpoint and explicit acceptance** only after the owner accepts runtime evidence and independent code review: commit scoped BE010 files with descriptive `refactor(be-reorg-010): extract resource state resolution core` message; record `git log -1 --oneline` and `git status --short`. Release status is **CANDIDATE** before those gates, **ACCEPTED** only afterward.

## Acceptance Matrix and Stop Conditions

| Gate | Required proof | Owner/environment |
|---|---|---|
| BE009 base/review | Real Windows checkpoint SHA, clean scope, accepted 948-case & 121-diagnostic baseline, independent BE009 review | Windows; **must precede BE010 edits** |
| Module layering | Six new files, one legacy file; ports 11+8, Contracts 9 internal/8 façade, no extra SQL/mutation | Static Reflection/source review |
| Behavior parity | Public API exact, `DomainException` type/code/context, call ordering, policy INACTIVE, native catalog and cache semantics | BE010 5+5+4+8+4 tests + inherited WF006/007 |
| HTTP+real DB | Auth-denial, 403/422, CSRF, no unauthorized writes, inactive policy/reassignment and new-instance refresh | BE010 2 HTTP + 2 DB, guarded `erp_test` |
| Inherited regressions | 948 inherited tests, BE007/008/009/PBAC & Foundation gates | One guarded full suite |
| Full total | 978 JUnit testcases, no skipped/failure/error | `test-resource-state-resolution.php` |
| Tooling amendment | BE009 JUnit threshold `>=948` + exact 32 BE009 class/method assertions; BE010 exact 978 | Synthetic 947/948/978 RED→GREEN; owner plan approval |
| PHPStan | Zero unmatched new diagnostics vs accepted BE009; relocation evidence for up to two | Baseline/target report diff |
| Package/review | Applied/reversed cleanly on exact SHA, checksum, independent code review, Git checkpoint | Windows / reviewer |

**Hard stops:** (1) BE009 independent code review / actual SHA absent at *production implementation*; (2) unapproved BE009 runner freeze exception; (3) unknown diagnostics, authorization fail-open, altered API/DB semantics; (4) untrusted/non-disposable DB or concurrent DB runners; (5) patch checkout does not match base. Do not silently weaken gates or declare ACCEPTED while any stop remains.

## Recommended Execution and Handoff

**Recommended: Native (inline)**, because the read port, canonical engine, factory and legacy façade depend on each other's exact signatures, and the team has already used an incremental-patch/Windows-runtime-acceptance workflow successfully. Use `superpowers:executing-plans`, TDD RED→GREEN, isolate work in an approved Git worktree and maintain a per-task ledger. If a separate reviewer tool is unavailable, report **self-review only** explicitly and ask for independent review before final sign-off.

**Approval question:** Does this plan — **including the narrow BE009 JUnit tool compatibility amendment** — match the desired BE010 implementation? Confirm **`Duyệt Implementation Plan BE-REORG-010 và triển khai Native`** (or request modifications). This approval is the gate before production code; merely approving Specification v2 does not approve this tooling exception.
