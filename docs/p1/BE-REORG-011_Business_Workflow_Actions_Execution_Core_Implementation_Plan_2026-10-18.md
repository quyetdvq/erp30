# BE-REORG-011 Business Workflow Actions & Execution Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Date:** 2026-10-18
**Status:** IMPLEMENTATION PLAN — AWAITING OWNER REVIEW; NOT AUTHORIZATION TO CHANGE PRODUCTION CODE.
**Goal:** Extract the four existing business Workflow operations into one canonical, module-owned service while preserving both legacy entrypoints, PBAC/CSRF behavior, the fixed record allowlist, and BE009/BE010 transaction and State semantics.
**Architecture:** Follow the established `Application/Ports`, `Contracts`, `Infrastructure/Persistence`, `Bootstrap` layout. Introduce one read-only record port and one application port; the existing HTTP-oriented `BusinessWorkflowActions` and static `BusinessLifecycleTransitions` become thin explicit delegates to one canonical engine. BE009 remains the only transition executor; BE010 remains the State resolver.
**Tech Stack:** PHP 8.3.30, CodeIgniter 4/Shield, MySQL/MariaDB InnoDB, PHPUnit 10.5.65, PHPStan level 8, Windows PowerShell/Git.
**Spec:** `docs/p1/BE-REORG-011_Business_Workflow_Actions_Execution_Core_Spec_2026-10-17.md` (owner approved in conversation on 2026-10-18; the existing spec file's original `AWAITING REVIEW` header is historical, not a pending design decision).

## Global Constraints

- **Baseline:** accepted Windows runtime evidence for BE010+FIX01 is 978/978 PHPUnit tests, 9,592 assertions, BE010 30, BE009 32, BE008 26, BE007 24, PBAC 91 and 120 remaining inherited PHPStan diagnostics. Assertions for BE011 are unknown until Windows runs.
- **Precondition to implementation/release:** verify the actual Windows Git SHA/status and complete outstanding independent BE009/BE010 reviews/checkpoints. The v1.1.7 ZIP and synthetic worktrees are NOT the user's authoritative Git baseline. Do not start BE011 production edits on unreviewed or dirty main/master; work in a dedicated branch/worktree.
- **Production allowlist: exactly eight paths** (six new, two modified), enumerated below. No migration/schema, controller/route/OpenAPI, adapter, PBAC/Lifecycle/StateResolution, `Config/Services`, frontend, repository, business CRUD, or PHPStan config changes.
- **Only additional modifications permitted:** the BE010 runner's exact 978-case check -> `>= 978` (retain BE010's exact 30-method gate and no-failure checks); seven new BE011 test classes, optionally one namespaced fixture under `backend/tests/_support/Workflow/`, and one new guarded BE011 runner.
- **Contracts:** legacy `BusinessWorkflowActions::__construct(?ConnectionInterface $db = null)` and its three named methods/array returns are unchanged; `BusinessLifecycleTransitions::applyIfBound(string,int,string,OperationContext,?ConnectionInterface=null):bool` is static with exactly five parameters and unchanged semantics.
- **Persistence:** fixed **12 resource keys / 11 distinct table names**, SELECT-only `BusinessWorkflowRecordPort` with two methods; no interpolated table or unbounded SQL identifier, generic update, transaction, or new cache.
- **Security:** persisted target and trusted State only; base `view` for metadata, target-level `view` for options, independent `setstate` and transition-specific PBAC, then BE009 reauthorization/lock/audit; generic POST never changes native-only/unbound resources.
- **Test DB:** exclusively disposable `127.0.0.1:3306/erp_test`, table prefix `dinktech_`; `DatabaseTestTrait` may refresh tables. Guard configuration before fixture creation; never run parallel test processes on this DB.
- **Runner:** BE011 must preflight 7 named test classes / **exactly 34** methods without DB; `--help` exit 0, absent/invalid confirmation exit 2, exactly one BE010 child, fresh JUnit **exactly 1,012 cases**, no failures/errors/skips. The child may return exit 1 solely for inherited PHPStan diagnostics; never call PHPStan clean or mark ACCEPTED from a raw runner code 1.
- **Exit gates:** full Windows PHPUnit + detailed PHPStan diagnostic identity comparison against BE010's 120, independent code review with no Critical/Important findings, and a verified owner Git checkpoint before `ACCEPTED`.

## Review Focus

The following user-visible input/failure classes are required even if a happy-path test would not expose them. The named owning tests below MUST assert them:

1. **Untrusted resource spelling or arbitrary resource name:** `product`, `Product`, ` product ` and crafted names do not silently share SQL permissions; reject unsupported values without interpolating a table (Task 1, `testTwelveResourceAllowlistAndPositiveIdGuard`).
2. **Cross-owner contact ID:** asking for an EMPLOYEE contact via `organization_contact`, or the reverse, must fail *before PBAC* with the existing not-found code/context (Task 1, `testContactSplitMismatchCannotResolveOtherOwner`).
3. **Read-only actor or forged State input:** `view` never implies `setstate`; options and POST decisions must use persisted status/organization, never client hints (Tasks 2 and 4, `testOptionsRequiresTargetViewAndSetstateSeparately`, `testOptionsPersistedStateAndActorPermission`).
4. **Malformed action code and exception precedence:** `setstate` denial must precede syntax validation; a normalized-but-invalid action must retain `WF_INVALID_TRANSITION` context (Tasks 2 and 4, `testExecutePreservesPersistedStateSetstateAndActionCodeValidation`, `testPostExecuteInvalidActionAndForgedDestinationRejected`).
5. **Concurrent state change, row lock, or absent CSRF:** deny without status/audit writes, preserve BE009 concurrency rollback, catch CI `SecurityException(403)` when the active filter throws rather than fabricating a `TestResponse` (Tasks 4–5, `testPostExecuteMissingCsrfRejectedWithoutWrites`, both lock tests).

---

## File Map and Interface Lock

### Production — precisely 8 files

| Operation | Path | Responsibility |
|---|---|---|
| CREATE | `backend/app/Modules/Workflow/BusinessActions/Application/Ports/BusinessWorkflowActionPort.php` | Exactly four application operations; no framework controllers |
| CREATE | `backend/app/Modules/Workflow/BusinessActions/Application/Ports/BusinessWorkflowRecordPort.php` | Exactly `assertBusinessResource` and `record`; read-only |
| CREATE | `backend/app/Modules/Workflow/BusinessActions/Contracts/BusinessWorkflowActionErrors.php` | Exactly five inherited error constants |
| CREATE | `backend/app/Modules/Workflow/BusinessActions/Infrastructure/Persistence/SqlBusinessWorkflowRecordReader.php` | Fixed 12-key table map; DB read and Contact ownership isolation |
| CREATE | `backend/app/Modules/Workflow/BusinessActions/Application/BusinessWorkflowActionService.php` | All four workflows, PBAC orchestration, one private adapter selector |
| CREATE | `backend/app/Bootstrap/BusinessWorkflowActionFactory.php` | Stateless, one-connection composition |
| MODIFY | `backend/app/Services/BusinessWorkflowActions.php` | `final` compatibility façade; one constructor, three explicit delegates |
| MODIFY | `backend/app/Services/BusinessLifecycleTransitions.php` | `final` static compatibility façade; one explicit delegate |

`BusinessWorkflowActionPort` (namespace `App\Modules\Workflow\BusinessActions\Application\Ports`) MUST expose these exact public methods and return types:

```php
/** @return array{mode:string,initial:string|null,states:list<string>} */
public function states(string $resource, OperationContext $operation): array;
/** @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>} */
public function options(string $resource, int $id, OperationContext $operation): array;
/** @return array{status:string,action_code:string} */
public function execute(string $resource, int $id, string $actionCode, OperationContext $operation): array;
public function applyIfBound(string $type, int $id, string $destination, OperationContext $operation): bool;
```

`BusinessWorkflowRecordPort` (same namespace) MUST expose only:

```php
public function assertBusinessResource(string $resource): void;
/** @return array<string,mixed> */
public function record(string $resource, int $id): array;
```

The canonical engine constructor in `Application/BusinessWorkflowActionService.php` is fixed (five dependencies, in order):

```php
public function __construct(
    ConnectionInterface $db,
    BusinessWorkflowRecordPort $records,
    ResourceWorkflowStateResolver $states,
    AuthorizationService $authorization,
    LifecycleRepository $lifecycles,
);
```

The stateless factory signature in `App\Bootstrap\BusinessWorkflowActionFactory` is fixed:

```php
/** @param ConnectionInterface<mixed,mixed>|null $db */
public static function create(
    ?ConnectionInterface $db = null,
    ?BusinessWorkflowRecordPort $records = null,
    ?ResourceWorkflowStateResolver $states = null,
    ?AuthorizationService $authorization = null,
    ?LifecycleRepository $lifecycles = null,
): BusinessWorkflowActionPort;
```

The reader's constructor is `__construct(ConnectionInterface $db)`; internally hold the **same** injected DB. The canonical service owns private methods for the existing `allowedAction`, `requireAction`, and one adapter selector returning existing `App\Services\LifecycleTargetAdapter`. Do not add a public adapter/factory method or extra collaborator constructor parameter.

**Fixed `SqlBusinessWorkflowRecordReader::TABLES` map:**

```php
[
  'province'=>'provinces', 'ward'=>'wards', 'organization'=>'organizations',
  'department'=>'departments', 'position'=>'positions', 'employee'=>'employees',
  'product_group'=>'product_groups', 'product_uom'=>'product_uoms',
  'product'=>'products', 'price_list'=>'price_lists',
  'organization_contact'=>'contacts', 'employee_contact'=>'contacts',
]
```

The record adapter MUST: reject unknown or independent resource with `WF_TARGET_ADAPTER_UNAVAILABLE` + `['resource'=>$resource]`; normalize with `PositiveId::normalize($id, 'target_id', 'WF_TARGET_NOT_FOUND')`; `table(self::TABLES[$resource])->where('id',$id)->get()->getRowArray()` (read full persisted row); validate contact owner with `ContactAuthorizationRegistry::resourceFor($row)`; return `WF_TARGET_NOT_FOUND` + `['target_id'=>$id]` for missing/mismatched rows; propagate malformed Contact registry errors unchanged. The map must not be made user-configurable.

`Contracts/BusinessWorkflowActionErrors.php` contains **exactly**:

```php
public const ERROR_TARGET_ADAPTER_UNAVAILABLE = 'WF_TARGET_ADAPTER_UNAVAILABLE';
public const ERROR_TARGET_NOT_FOUND = 'WF_TARGET_NOT_FOUND';
public const ERROR_AUTHORIZATION_DENIED = 'AUTH_POLICY_DENIED';
public const ERROR_INVALID_TRANSITION = 'WF_INVALID_TRANSITION';
public const ERROR_TRANSITION_REQUIRED = 'WF_TRANSITION_REQUIRED';
```

No new public error or HTTP mapping. Use `DomainException` and existing exceptions/contexts; annotating generics/array shapes is required so BE011 adds zero PHPStan diagnostics.

### Test and tooling files (not counted in production allowlist)

- CREATE `backend/tests/unit/BeReorg011BusinessActionsBoundaryTest.php` — 5 methods.
- CREATE `backend/tests/unit/BeReorg011BusinessActionsCompatibilityTest.php` — 5 methods.
- CREATE `backend/tests/unit/BeReorg011BusinessActionsFactoryTest.php` — 4 methods.
- CREATE `backend/tests/database/BeReorg011BusinessActionsApplicationTest.php` — 7 methods.
- CREATE `backend/tests/database/BeReorg011BusinessActionsHttpTest.php` — 6 methods.
- CREATE `backend/tests/database/BeReorg011BusinessActionsDatabaseTest.php` — 5 methods.
- CREATE `backend/tests/database/BeReorg011BusinessActionsLockTest.php` — 2 methods.
- OPTIONAL CREATE `backend/tests/_support/Workflow/BeReorg011BusinessActionsFixture.php` — guarded reusable fake ports/DB fixture assembly; not production.
- CREATE `backend/tools/test-business-workflow-actions.php` — BE011 guarded runner.
- MODIFY ONLY the BE010 JUnit-count condition/explanation in `backend/tools/test-resource-state-resolution.php` as defined in Task 6.

**Fixture conventions:** For database/HTTP tests, use `DatabaseTestTrait`, `RuntimeGuard::assertConfiguration(...)` **before** `parent::setUp()`, `RuntimeFixture($this->db)`, real Shield actor/group permissions and authenticated request context. Add root GROUP employee assignment when a SYSTEM-scoped Lifecycle endpoint requires it (see the BE010 HTTP fixture). Province/ward codes are `VARCHAR(20)`; use `'BE011H-' . uniqid()` / `'BE011L-' . uniqid()` (20 chars), assert length before insert. Each test must clean up peer transactions and restore changed workflow catalog State flags before DatabaseTestTrait teardown. Use no production DB.

## Exact Test Inventory: 34 tests in 7 classes

These names, counts and class locations are binding; **do not rename, silently add, omit or skip a test**. If a later TDD discovery justifies another test, seek scope approval and update this plan plus the projected JUnit total first.

| Class suffix (`BeReorg011BusinessActions...Test`) | Folder | Count | Required methods |
|---|---|---:|---|
| Boundary | `tests/unit` | 5 | `testApplicationPortDefinesFourOperations`, `testRecordPortExposesReadOnlyOperations`, `testModuleLayersAndContractsAreIsolated`, `testNoRouteOrAuthorizationInfrastructureChanges`, `testErrorConstantsPreserveLegacyValues` |
| Compatibility | `tests/unit` | 5 | `testActionFacadeRetainsConstructorAndThreeSignatures`, `testLifecycleTransitionsRetainsStaticSignatureAndReturnType`, `testExplicitDelegationAndNoLocalOrchestration`, `testFactoryReturnsFreshEngineWithSameConnection`, `testLegacyConsumersStillReferenceUnchangedFacades` |
| Factory | `tests/unit` | 4 | `testDefaultsReuseOneConnection`, `testInjectedRecordPortUsedAsIs`, `testFactoryDoesNotCacheBetweenInvocations`, `testAdapterSelectionUsesCorrectExistingLegacyTypes` |
| Application | `tests/database` | 7 | `testStatesRequiresBaseViewBeforeMetadata`, `testStatesNativeAndLifecycleMetadataParity`, `testOptionsRequiresTargetViewAndSetstateSeparately`, `testOptionsFiltersInactiveInvalidAndUnauthorizedTransitions`, `testExecuteRejectsNativeResourceWithoutMutation`, `testExecutePreservesPersistedStateSetstateAndActionCodeValidation`, `testApplyIfBoundFalseForUnboundAndExpectedDestinationForBound` |
| Http | `tests/database` | 6 | `testStatesMetadataBasePermissionAndResponseEnvelope`, `testOptionsPersistedStateAndActorPermission`, `testPostExecuteAuthorizedLifecycleTransitionAndAudit`, `testPostExecuteDeniedCannotWrite`, `testPostExecuteInvalidActionAndForgedDestinationRejected`, `testPostExecuteMissingCsrfRejectedWithoutWrites` |
| Database | `tests/database` | 5 | `testTwelveResourceAllowlistAndPositiveIdGuard`, `testContactSplitMismatchCannotResolveOtherOwner`, `testNativeFallbackOptionsAndStaticFalseForUnbound`, `testExecutionReportsUnknownAndMissingTargetsAsBefore`, `testInactiveTransitionOrDestinationNeverOffered` |
| Lock | `tests/database` | 2 | `testRealInnoDbLockTimeoutDoesNotMutateOrAudit`, `testStaleStateConcurrentMutationRollsBack` |
| **TOTAL** | | **34** | **1,012 JUnit cases (=978+34)** |

## Task 1 — Freeze Actual Baseline; Introduce Record Port, Fixed Reader and Contracts

**Files:** Create `Application/Ports/BusinessWorkflowRecordPort.php`, `Application/Ports/BusinessWorkflowActionPort.php`, `Contracts/BusinessWorkflowActionErrors.php`, `Infrastructure/Persistence/SqlBusinessWorkflowRecordReader.php` in the module paths above; create `backend/tests/database/BeReorg011BusinessActionsDatabaseTest.php` initially containing the first **two** approved methods; use optional `backend/tests/_support/Workflow/BeReorg011BusinessActionsFixture.php` only if needed for shared setup. Do not alter legacy services in this task.

**Interfaces:** Produces the exact two ports, five error constants and `SqlBusinessWorkflowRecordReader(ConnectionInterface $db) implements BusinessWorkflowRecordPort`; Tasks 2–3 consume them. Reader returns real rows and does not call PBAC or Lifecycles.

- [ ] **Step 1: Capture the non-destructive Windows baseline.** From project root run `git status --short`, `git rev-parse HEAD`, `git diff --check` and record BE010+FIX01 checkpoint/reviews. Compare accepted 978-case JUnit and 120 PHPStan diagnostics; do not overwrite existing logs. Use a dedicated BE011 branch/worktree off that verified SHA. **Expected:** independently reviewed BE010 checkpoint, no unaccounted working-tree modifications. **STOP before product edits if this gate is missing.**
- [ ] **Step 2: Characterize legacy record errors.** In the disposable test fixture, call legacy `BusinessWorkflowActions` with representative bad resources and existing/missing IDs, preserve `DomainException::getMessage()/getContext()` values, then define assertions for the new read port in the named two DB tests. Pin `WF_TARGET_ADAPTER_UNAVAILABLE` for unsupported/mixed-case/independent names; `WF_TARGET_NOT_FOUND` for absent target/ID <=0; exact contact owner split. For Contact, construct one ORGANIZATION and one EMPLOYEE owned row under a real parent with schema-valid fields; do not persist an unsupported `contact_type` if the schema forbids it.
- [ ] **Step 3: Write the first two tests before the reader.** `testTwelveResourceAllowlistAndPositiveIdGuard`: reflect fixed 12-key allowlist plus exercise `assertBusinessResource` and `record` for all supported keys with real DB fixtures where available; unknown case/spacing, `0`/negative ID, and missing positive ID throw exact legacy codes/contexts; no table interpolation. `testContactSplitMismatchCannotResolveOtherOwner`: same contacts ID requested through the other Resource always yields `WF_TARGET_NOT_FOUND` before authorization; the valid Resource loads the exact persisted row. These tests instantiate the as-yet-unimplemented `SqlBusinessWorkflowRecordReader`, and must **FAIL (RED)** for the missing class/port.
- [ ] **Step 4: Run the two DB tests RED**, on confirmed `erp_test`, never in parallel:

  ```powershell
  Set-Location D:\XAMPP\htdocs\erp\backend
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg011BusinessActionsDatabaseTest.php
  ```

  **Expected before implementation:** nonzero exit due to undefined new adapter/interface, not a DB setup/permission error. If setup fails, fix the fixture *before* proceeding; do not hide errors by skipping the tests.
- [ ] **Step 5: Implement minimal ports, contracts and fixed-table reader** with exactly the interfaces/constructor/map shown in File Map. Read-only query builder; reject resource before PositiveId; on Contact require registry owner match; propagate `CONTACT_AUTH_OWNER_TYPE_UNSUPPORTED` when generated by registry. Use `ConnectionInterface<mixed,mixed>` PHPDoc and no new SQL write or lifecycle/PBAC call.
- [ ] **Step 6: Run Task 1 GREEN**, plus PHP lint on the four new PHP files and `git diff --check`:

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg011BusinessActionsDatabaseTest.php
  ```

  **Expected:** 2/2 tests PASS; no warnings or skips. Record results; scoped commit such as `refactor(be011): introduce read-only business workflow record port`.

## Task 2 — Canonical Service: State Metadata, Options, Execution and Static Destination Semantics

**Files:** Create `backend/app/Modules/Workflow/BusinessActions/Application/BusinessWorkflowActionService.php` and `backend/tests/database/BeReorg011BusinessActionsApplicationTest.php` (7 tests). Extend `backend/tests/database/BeReorg011BusinessActionsDatabaseTest.php` with the remaining **three** approved tests. No factory/facade modifications yet.

**Interfaces:** Consumes Task 1 `BusinessWorkflowActionPort`, `BusinessWorkflowRecordPort` and error constants. Produces `BusinessWorkflowActionService implements BusinessWorkflowActionPort` with the exact 5-param constructor, four methods and one private selector returning `LifecycleTargetAdapter`. Legacy façade and static helper remain behavioral baselines until Task 3.

- [ ] **Step 1: Write seven named Application tests against the manually constructed engine**, using real `RuntimeFixture`/PBAC, explicit DB, new record reader, BE010 legacy resolver, `AuthorizationService(db: $db)`, `LifecycleRepository($db)`. Assert all of the following, comparing relevant outputs/error codes/contexts to **the original legacy methods** before extracting the code:
  - `testStatesRequiresBaseViewBeforeMetadata`: unsupported resource rejected first; reader actor without base `view` receives `AUTH_POLICY_DENIED` + `resource/action` without leaking State metadata.
  - `testStatesNativeAndLifecycleMetadataParity`: bound/unbound modes, ordered State codes, initial State and `ACTIVE` fallback/null match source exactly; no fabricated State values.
  - `testOptionsRequiresTargetViewAndSetstateSeparately`: persisted target row/State, view denial vs view-only actor returning `options=[]`, never write permission through `view`.
  - `testOptionsFiltersInactiveInvalidAndUnauthorizedTransitions`: only active outgoing transitions, valid active destination membership, normalized lowercase action and *specific* PBAC action; invalid destination `DomainException` skips only the entry, DB failure must not be silently swallowed.
  - `testExecuteRejectsNativeResourceWithoutMutation`: `WF_TRANSITION_REQUIRED` + resource context before lookup when unbound; native options are previews only, no generic mutation/audit.
  - `testExecutePreservesPersistedStateSetstateAndActionCodeValidation`: deny `setstate` from persisted State **before** checking bad syntax; valid `trim/lowercase` action normalized; bad code causes exact `WF_INVALID_TRANSITION` + normalized `action_code` when the user is otherwise authorized.
  - `testApplyIfBoundFalseForUnboundAndExpectedDestinationForBound`: unbound false before mapping/adapter/mutation; bound `setstate` passes the exact expected State ID to BE009 and returns true only on successful mutation; mismatched expected destination raises original exception without write.
- [ ] **Step 2: Append three exact Database tests:** `testNativeFallbackOptionsAndStaticFalseForUnbound` (preview native statuses except current and unchanged legacy static return false), `testExecutionReportsUnknownAndMissingTargetsAsBefore` (unsupported resource > lifecycle/record > authorization precedence, context stable), `testInactiveTransitionOrDestinationNeverOffered` (transition inactive or destination State disabled is excluded, order unchanged). Compare to already-running legacy implementation before cutover. Assert DB State and relevant audit row counts unchanged after negative paths.
- [ ] **Step 3: Run Application + Database RED** before creating the engine:

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg011BusinessActionsApplicationTest.php tests/database/BeReorg011BusinessActionsDatabaseTest.php
  ```

  **Expected:** Application tests fail from missing canonical class/port implementation. Existing two reader tests from Task 1 stay GREEN. Any purely legacy characterization test that passes before refactor is *correct characterization*, not a fabricated TDD RED.
- [ ] **Step 4: Implement `BusinessWorkflowActionService` from original source, not a redesign.** Move original `states`, `options`, `execute`, `allowedAction`, `requireAction` algorithmic bodies from `Services/BusinessWorkflowActions.php`; replace only `record`/`assertBusinessResource` with calls to injected `$records`, and error constants with `BusinessWorkflowActionErrors`. Keep `EffectiveProfileResolver($db)` local to `states`, `EvaluationOrganizationRequest(persistedTarget:$row)`, `ResourceNativeStateCatalog` native preview, `LifecycleRepository::findTransitions($lifecycleId, 'ACTIVE')` ordering, and caught `DomainException` scope. Do not catch `Throwable` around PBAC/DB.
- [ ] **Step 5: Implement `applyIfBound` as a separate fourth operation**, copying `BusinessLifecycleTransitions`' old order exactly: `lifecycleId($type)` -> early false -> private adapter selector -> `stateIdForCode($type,$destination)` -> `new LifecycleService(adapters:[$adapter],db:$db)->execute($type,$id,'setstate',$operation,$toStateId)` -> true. **NEVER** forward to HTTP `execute`; the fifth LifecycleService argument (expected destination) is a security invariant.
- [ ] **Step 6: Consolidate the private target selector:** `province|ward => FoundationLocationLifecycleAdapter($resource,$db,states:$states)`, `organization_contact|employee_contact => ContactLifecycleAdapter($resource,$db,states:$states)`, other supported business types => `BusinessLifecycleTargetAdapter($resource,$db,$states)`. No consumer-supplied table name, no additional authorization boundary or transaction.
- [ ] **Step 7: Run GREEN + inherited behavior checks**:

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg011BusinessActionsApplicationTest.php tests/database/BeReorg011BusinessActionsDatabaseTest.php
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg009LifecycleRepositoryParityTest.php tests/database/BeReorg010StateResolutionDatabaseTest.php
  ```

  **Expected:** 7 Application and 5 Database BE011 tests PASS (12 total), inherited BE009/BE010 targeted tests PASS. Preserve any legacy oddities as compatibility; record a plan variance and request authorization if a security flaw makes strict parity unsafe. Commit `refactor(be011): move business workflow orchestration into module`.

## Task 3 — Stateless Factory, Two Legacy Facades, and Boundary/Compatibility Contracts

**Files:** Create `backend/app/Bootstrap/BusinessWorkflowActionFactory.php`; MODIFY `backend/app/Services/BusinessWorkflowActions.php`, `backend/app/Services/BusinessLifecycleTransitions.php`; CREATE three unit files: `BeReorg011BusinessActionsBoundaryTest.php` (5), `...CompatibilityTest.php` (5), `...FactoryTest.php` (4).

**Interfaces:** Factory creates `BusinessWorkflowActionPort` with the exact five optional parameters. The action façade has `private readonly BusinessWorkflowActionPort $engine`, one existing constructor param and exactly **three** explicit delegated public methods. Static façade preserves existing sole static method. Default DB is resolved *once* by the factory, passed to reader, resolver, authorization and lifecycle repository.

- [ ] **Step 1: Write five Boundary tests**, first verifying their missing factory/module pieces cause RED:
  - `testApplicationPortDefinesFourOperations`: Reflection exact four names, parameter orders and types, return types; no implicit `__call`.
  - `testRecordPortExposesReadOnlyOperations`: exactly two methods; no `insert`, `update`, `delete`, raw SQL or transaction entrypoints.
  - `testModuleLayersAndContractsAreIsolated`: engine implements application port; record adapter implements read port; contracts depend on no database/controllers; Application depends on reader port, not concrete SQL reader or dynamic TABLES.
  - `testNoRouteOrAuthorizationInfrastructureChanges`: supported three route method/path signatures remain reachable, and no BE011 path imports a controller or writes auth/migration configuration. Use both CI route inspection and a file-allowlist diff gate; source-string assertions alone do not count as full coverage.
  - `testErrorConstantsPreserveLegacyValues`: the exact five values, names, number of constants; compare `DomainException` contexts from fixed error flows.
- [ ] **Step 2: Write five Compatibility tests:**
  - `testActionFacadeRetainsConstructorAndThreeSignatures`: Reflection exact optional `?ConnectionInterface $db`, three argument lists/return arrays, class final and no extra public method/constant.
  - `testLifecycleTransitionsRetainsStaticSignatureAndReturnType`: Reflection static/public/five parameters, optional DB default null and bool return; no new public methods.
  - `testExplicitDelegationAndNoLocalOrchestration`: new façades have only a private engine / static factory call; use output and exception-context parity with original, not `__call`/source grep alone.
  - `testFactoryReturnsFreshEngineWithSameConnection`: factory calls create distinct engine instances and identical explicit DB collaborator identities through Reflection or observational mocks.
  - `testLegacyConsumersStillReferenceUnchangedFacades`: representative employee/product/foundation consumers still call legacy FQCN; no required edits to their call sites or dependency types.
- [ ] **Step 3: Write four Factory tests:**
  - `testDefaultsReuseOneConnection`: supply one `ConnectionInterface` test double or guarded real tests DB; verify reader, resolver, authorization and lifecycle repository are wired to that precise DB instance; no constructor query.
  - `testInjectedRecordPortUsedAsIs`: a custom read-only port instance is retained; record output/exceptions reflect that instance rather than implicit SQL fallback.
  - `testFactoryDoesNotCacheBetweenInvocations`: two `create()` calls yield distinct engine/resolver/reader with no shared caches or static singleton.
  - `testAdapterSelectionUsesCorrectExistingLegacyTypes`: exercise `province`, `ward`, both Contact resources and one other business type; observed adapter implements existing interface and has expected `resourceType`; do not change any adapter constructors.
- [ ] **Step 4: Run 14 unit tests RED**:

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg011BusinessActionsBoundaryTest.php tests/unit/BeReorg011BusinessActionsCompatibilityTest.php tests/unit/BeReorg011BusinessActionsFactoryTest.php
  ```

  **Expected:** missing factory or still-algorithmic legacy façade causes targeted failures. Previously satisfied pure port/constant expectations may already be GREEN; do not force failure for those unrelated to the factory.
- [ ] **Step 5: Implement factory and both façades exactly.** Factory does `$db ??= db_connect()` once, then `records ??= new SqlBusinessWorkflowRecordReader($db)`, `states ??= new ResourceWorkflowStateResolver($db)`, `authorization ??= new AuthorizationService(db:$db)`, `lifecycles ??= new LifecycleRepository($db)` and returns a new canonical engine. Preserve explicitly provided collaborators. `BusinessWorkflowActions::__construct(?ConnectionInterface $db=null)` assigns one `BusinessWorkflowActionFactory::create($db)` to readonly `$engine`; `states`, `options`, `execute` explicitly forward arguments/results. `BusinessLifecycleTransitions::applyIfBound` calls fresh `BusinessWorkflowActionFactory::create($db)->applyIfBound($type,$id,$destination,$operation)`; do not instantiate two resolvers or route through HTTP `execute`. Keep all old PHPDocs/public signatures and `final` modifiers.
- [ ] **Step 6: Run GREEN + Reflection snapshot comparison**:

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg011BusinessActionsBoundaryTest.php tests/unit/BeReorg011BusinessActionsCompatibilityTest.php tests/unit/BeReorg011BusinessActionsFactoryTest.php
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg011BusinessActionsApplicationTest.php tests/database/BeReorg011BusinessActionsDatabaseTest.php
  ```

  **Expected:** unit **14/14** PASS, application+database **12/12** PASS. Compare names/types/defaults/visibility/static flags to the frozen pre-BE011 Reflection snapshot. Commit `refactor(be011): add stateless composition and legacy delegates`.

## Task 4 — Direct In-Process HTTP/CSRF and PBAC Regression

**Files:** Create `backend/tests/database/BeReorg011BusinessActionsHttpTest.php`, precisely **6** methods; optional test-only fixture additions. No controller, route or API change.

**Interfaces:** Use `FeatureTestTrait`, `DatabaseTestTrait`, Shield `AuthenticationTesting`, `RuntimeFixture`, real trusted `OperationContext` from logged-in actor; target the three original `/api/v1/business-workflow/...` endpoints directly. Follow the already verified BE010_FIX01 `SecurityException(403)` behavior in FeatureTestTrait.

- [ ] **Step 1: Write/characterize these six exact HTTP tests** against the original legacy route behavior where possible, then retain them unchanged after façade swap:
  - `testStatesMetadataBasePermissionAndResponseEnvelope`: authorized GET `.../{resource}/states` returns 200 with `data.mode/initial/states`, response `meta.request_id`, language/header shape; denied `view` -> existing `AUTH_POLICY_DENIED` without `data`.
  - `testOptionsPersistedStateAndActorPermission`: authorized GET `.../{resource}/{id}/options` from persisted State and trusted actor; 403 view denied; view-only returns options `[]` even if transition exists; forged query/body hints do not alter evaluated State.
  - `testPostExecuteAuthorizedLifecycleTransitionAndAudit`: authenticated POST `.../{resource}/{id}/actions/setstate`, actual permitted transition, 200 `{status,action_code}`, one persisted State change and appropriate audit (no duplicate write).
  - `testPostExecuteDeniedCannotWrite`: actor with only view or explicit deny -> 403 `AUTH_POLICY_DENIED`, unchanged row status and audit snapshot; `view` cannot mutate.
  - `testPostExecuteInvalidActionAndForgedDestinationRejected`: route action `1invalid` (valid `(:segment)`, invalid application regex) produces existing 422 `WF_INVALID_TRANSITION`/context and no write. Independently supply a forged `to_state_id` in an otherwise allowed POST body: the controller currently ignores it, so assert the committed State follows the configured transition rather than the forged ID; **do not invent an HTTP rejection for an ignored extra body field**. Preserve authorization-before-syntax ordering.
  - `testPostExecuteMissingCsrfRejectedWithoutWrites`: omit CSRF token on POST while the real filter is active; accept only actual 403 response or `CodeIgniter\Security\Exceptions\SecurityException` with code 403 from FeatureTestTrait; verify before/after persisted row and audit unchanged. Do not disable filter or weaken assertion to merely checking test configuration.
- [ ] **Step 2: Set up realistic authorization context.** Use the existing route, Shield actor, group permissions, root assignment when necessary; `X-Request-ID` header matched in response. Grant `view`, `setstate`, action-specific permission *independently*; use short `VARCHAR(20)` record codes. For POST send a real `Config\Security::headerName` + `service('security')->getHash()` token, never turn off CSRF globally.
- [ ] **Step 3: Run all six HTTP tests**:

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg011BusinessActionsHttpTest.php
  ```

  **Expected:** **6/6 PASS**, no skip and no uncaught SecurityException. Because this is a behavior-preserving refactor, a baseline characterization test may pass even before the code move; record this as expected, not a false TDD claim. Any differences from legacy response status/code/context require root-cause analysis; do not patch controllers/API to make the tests green.
- [ ] **Step 4: Run inherited HTTP parity** sequentially:

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg010StateResolutionHttpTest.php tests/database/BeReorg009LifecycleHttpTest.php
  ```

  **Expected:** inherited tests PASS, no CSRF or PBAC regressions. Commit `test(be011): characterize business workflow HTTP authorization`.

## Task 5 — Real InnoDB Locking and Stale-State Rollback

**Files:** Create `backend/tests/database/BeReorg011BusinessActionsLockTest.php` with precisely **2** methods; optional test-only Workflow fixture. No production transactions or adapter modifications.

**Interfaces:** Exercise generic `BusinessWorkflowActions::execute`/static `BusinessLifecycleTransitions::applyIfBound` on real persisted test rows; use two distinct MySQL sessions (`db_connect('tests', false)`) and BE009 adapter/transaction invariants. Tear down both connections/transactions unconditionally.

- [ ] **Step 1: Assert test DB identity and engine.** `RuntimeGuard` + `SELECT DATABASE() = erp_test`, InnoDB backing table check using `information_schema.TABLES`; `SELECT CONNECTION_ID()` must differ for holder and contender. Set `innodb_lock_wait_timeout = 2` or another small test-specific session value, `transStrict(false)`, and reset transaction status after expected server error 1205. Do not use `sleep()` duration as the proof of locking.
- [ ] **Step 2: Write `testRealInnoDbLockTimeoutDoesNotMutateOrAudit` before changing anything else.** Holder connection `BEGIN`/`SELECT ... FOR UPDATE` locks the same target row that another independent action would mutate; contender invokes generic action with its connection and must fail with a real lock timeout (inspect nested exception codes or `$db->error()['code']`, require MySQL **1205**), with no successful status change and no added successful state-change audit; holder releases, contender can proceed after cleanup. The action's *read-only record lookup is not a lock*, so the holder must target the row locked by the BE009 adapter, not only a workflow configuration row.
- [ ] **Step 3: Write `testStaleStateConcurrentMutationRollsBack`.** Coordinate two genuine DB sessions so the action reads an authorized ACTIVE target before its locking query, while a second session changes the row's committed status before `FOR UPDATE` can reload it. Use a test-owned child-process/barrier or observable lock-wait handshake (no arbitrary timer assertion). The engine must surface BE009 `ConcurrencyException` / `lifecycle.state_change` and leave no second mutation or successful audit. If the environment cannot provide deterministic interleaving, *report a BLOCKED test rather than pretending a mere duplicate action or sleep proves concurrency*; seek approval before adding a production seam.
- [ ] **Step 4: Run lock RED/behavior characterization then GREEN**:

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg011BusinessActionsLockTest.php
  ```

  **Expected:** **2/2 PASS** with an asserted real lock/rollback, no skips; fix only test-owned coordination if needed, never bypass locking in production. Close all peer connections before `DatabaseTestTrait` teardown. Commit `test(be011): prove real locking and stale-state rollback`.

## Task 6 — Guarded BE011 Runner and One BE010 Compatibility Adjustment

**Files:** MODIFY ONLY `backend/tools/test-resource-state-resolution.php`'s BE010 total-JUnit check/comment; CREATE `backend/tools/test-business-workflow-actions.php`. No changes to BE009 or BE008 runner.

**Interfaces:** `be011RequiredMethods(): array` maps exactly seven approved class names to 34 approved methods, `be011TestFiles(): array` maps to their exact folder paths, `be011Preflight(string $backend,array $required): void` checks all 8 production paths and seven tests before DB, `be011AssertJUnit(string $junit,array $required): void` checks exactly **1,012** nonfailed/nonerrored/nonskipped testcases and exact names, `be011Run(array $argv): int` handles CLI/child/status. Keep guarded file entrypoint analogous to BE010 (`SCRIPT_FILENAME === __FILE__`); `require_once` must not execute a second full suite.

- [ ] **Step 1: Prove BE010 runner's original count blocks extension without touching DB.** On Windows, use PHP DOM with a copy of the accepted `be-reorg-010.xml` containing 978 cases; append 34 uniquely named BE011 synthetic testcases, call `be010AssertJUnit` on the copy; expect it to reject **1,012** solely for `exactly 978`. Also assert an incomplete BE010 29-case inventory and 977-case total fail. Never overwrite the accepted JUnit fixture.
- [ ] **Step 2: Apply the sole inherited-runner change**:

  ```php
  // In be010AssertJUnit only, preserve all other gates:
  if ($all === false || $all->length < 978) {
      throw new RuntimeException('BE010 requires at least 978 JUnit testcases (948 inherited + 30 BE010).');
  }
  ```

  Update the runner's nearby exact-count comment/help string to say *at least* 978, preserving unchanged exact `be010RequiredMethods()` (30), `failure/error/skipped` rejection and its one-child behavior. Re-run synthetic cases: 1,012 accepted *only if* all exact BE010 names present; 977, duplicate/missing BE010, or skipped BE010 rejected.
- [ ] **Step 3: Write BE011 runner preflight/argument tests first (no DB).** Missing required test, mismatched class names, duplicated or renamed test methods, missing module file, invalid argument -> exit 2 without `db_connect`, `proc_open` or deleting any JUnit; `--help` -> 0. Source method regex must inspect `public function test...` and exact identities, not just total counts.
- [ ] **Step 4: Implement `test-business-workflow-actions.php`**. Parse args before DB: only `--help` or `--confirm-test-db`. For the confirmed path, call BE011 preflight; verify `DOMDocument` and `proc_open`; delete stale `backend/build/logs/be-reorg-011.xml`, inherited `...010.xml` and downstream JUnit as necessary; launch **one** inherited `php tools/test-resource-state-resolution.php --confirm-test-db` child (never spawn PHPUnit independently); spool/relay output without deadlock; reject inherited exit outside `0|1`. Inspect *fresh* root `test-result.log` (after child start marker), require `PHPUnit exit code: 0`, `PHPStan exit code: ...`, Foundation 17, BE007/BE008/BE009/BE010 gate lines, then include BE010 runner's safe functions and assert `be010AssertJUnit(fresh inherited JUnit, be010RequiredMethods())` plus `be011AssertJUnit(...)`. Copy fresh JUnit to `be-reorg-011.xml`, append honest summary and propagate raw PHPStan exit (0 or 1), or exit 2 on any block. Do not assume `exit 1` is acceptance or write a fake 0.
- [ ] **Step 5: Run safe CLI gates and lint**, with NO database process:

  ```powershell
  php tools/test-business-workflow-actions.php --help
  php tools/test-business-workflow-actions.php
  php tools/test-business-workflow-actions.php --unsafe
  php -l tools/test-business-workflow-actions.php
  php -l tools/test-resource-state-resolution.php
  ```

  **Expected:** codes respectively **0, 2, 2, 0, 0**; none of the first three may touch DB/JUnit or run PHPUnit. Run the synthetic JUnit 1,012/977/missing/skip checks. Commit `test(be011): add guarded 1012-case acceptance runner`.

## Task 7 — Static/Parity Review, Incremental Release and Windows Acceptance

**Files:** No additional production changes. Deliver a patch based on *verified* BE010+FIX01 checkpoint, a full file manifest, SHA-256, review/evidence documents and application commands. Save approved spec/plan under `docs/p1` if not already present.

**Interfaces:** Tasks 1–6 implementation, exact 34 test identities, unmodified inherited contracts and actual Windows Git SHA. A fresh reviewer checks the whole changed branch, not merely test green status.

- [ ] **Step 1: Static scope and source parity.** `git diff --check`, `git diff --name-status <BE010_SHA>..HEAD`, PHP lint **every** changed PHP file and Reflection snapshot for both legacy classes. Compare the old `states/options/execute/allowedAction/requireAction` bodies to the moved canonical versions after accounting only for port calls/error constant ownership. Compare old static `applyIfBound` order with canonical helper. Inspect all 12 resources/11 tables, adapter three-way match, positive ID, Contact ownership, error precedence/context; verify no mutation SQL in `SqlBusinessWorkflowRecordReader`. Any extra production file is out of scope.
- [ ] **Step 2: Run all 7 named suites sequentially** on the disposable DB; unit classes first, then Application/Database/HTTP/Lock. Require exactly **34/34** new BE011 methods, no skipped/errored tests. Do not run two processes on `erp_test` simultaneously.
- [ ] **Step 3: Run ONE full guarded acceptance** from `D:\XAMPP\htdocs\erp\backend`:

  ```powershell
  php tools/test-business-workflow-actions.php --confirm-test-db
  Write-Host "BE011 runner exit code: $LASTEXITCODE"
  ```

  **Expected:** fresh 1,012/1,012 JUnit testcases, 34 BE011 + 30 BE010 + 32 BE009 + 26 BE008 + 24 BE007; Foundation 17 and existing PBAC 91 retained, 0 PHPUnit failure/error/skip. Do **not** predict assertions count; capture it from PHPUnit. `exit 1` for inherited PHPStan diagnostics requires the next step, not a green PHPStan claim.
- [ ] **Step 4: PHPStan diagnostic-identity comparison.** Run PHPStan in a comparable environment at the BE010+FIX01 checkpoint and BE011 head, preferably `php vendor/bin/phpstan analyse --no-progress --error-format=json` (keep full outputs), normalize only agreed filename/line relocations. The previously inherited `Services/BusinessWorkflowActions.php` `arrayValues.list` diagnostic in `options()` can legitimately move to `Modules/Workflow/BusinessActions/Application/BusinessWorkflowActionService.php` or disappear; match identifier, message and semantic code location. **Any unmatched diagnostic is a BLOCKER** even if raw count stays 120 or drops. Do not edit `phpstan.neon`/baseline/ignore config.
- [ ] **Step 5: Independent whole-branch code review** by a reviewer other than implementer when available, explicitly inspecting: no `view`->write privilege elevation; persisted State/Contact trust; exact error ordering; destination supplied only to static helper; one DB connection; cache isolation; InnoDB rollback and audit; CSRF tests; runner safety; frozen paths. Fix Important/Critical findings via new RED->GREEN reproduction and full suite rerun, or document a blocked change requiring scope approval. If reviewer tool is not available, label self-review as **not independent**, and keep `REVIEW PENDING`.
- [ ] **Step 6: Package an incremental, BE010+FIX01-relative patch** (`git diff --binary <BASE_SHA> HEAD` on reviewed scoped commits or checked staged equivalents). Verify `git apply --check`, apply, reverse and byte-match in a disposable checkout of the **exact** base; record SHA-256, production/test/tooling manifest, and versioned evidence. Never bundle unrelated files or presume v1.1.7 ZIP equals the user's current Git checkout. If Windows checkout differs, stop and reconcile; don't force apply.
- [ ] **Step 7: Owner acceptance/commit gate.** Request Windows log / `backend/build/logs/be-reorg-011.xml`, reconcile raw PHPUnit/PHPStan and independent review, then record the Git commit hash and clean scoped status. **`BE-REORG-011 ACCEPTED` is forbidden** until all gates close. Before that, label artifacts `CANDIDATE` and report unresolved gates candidly.

## Acceptance Matrix and Explicit Stop Conditions

| Gate | PASS requirement | STOP/BLOCK if |
|---|---|---|
| Scope | Only eight production files; permitted test/runner paths | Any route, schema, auth, adapter, BE009/010 product change |
| Public API | All legacy method names/signatures/defaults/return types identical | Reflection mismatch or consumer regression |
| Behavior | Same State, options ordering, POST and static destination semantics | Native mutation, permission elevation, changed error context |
| Test inventory | 34/34 BE011 methods, exactly 7 classes | Missing, renamed, duplicated or skipped method |
| Full PHPUnit | 1,012 cases, zero errors/failures/skips | Any failed case or missing inherited gate |
| Real locks | InnoDB peer sessions, actual 1205 and stale-state rollback | Timer-only evidence, fake lock or audit write |
| PHPStan | Zero unmatched new diagnostics vs BE010 120 | Raw count-only comparison or new identifier/message |
| Security | Denials + absent CSRF cannot mutate/audit | FeatureTestTrait 403 not checked; forged hints used |
| Review | Truly independent review, no unresolved Important/Critical | Only author self-review or unresolved finding |
| Git | Known BE010+FIX01 SHA, clean apply/reverse, explicit checkpoint | ZIP-only synthetic base or unreviewed dirty worktree |

## Implementation Execution Handoff

**Current deliverable is the approved-Spec-driven plan only.** No BE011 production code or PHPUnit run is represented by this document. The execution method and final review arrangement still require owner confirmation.

**Recommended:** Native implementation on an isolated BE010+FIX01 checkpoint worktree, with TDD and one independent whole-branch review before acceptance. A subagent-driven implementation with per-task fresh reviews is also valid where the execution environment supports that capability. In either case, run actual Windows database/HTTP/lock gates before claiming runtime success.
