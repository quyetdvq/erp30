# BE-REORG-011 - Business Workflow Actions & Execution Core Architecture Specification

**Date:** 2026-10-17
**Status:** DESIGN SPECIFICATION - AWAITING PROJECT OWNER REVIEW
**Scope decision:** Approved option A (Business Workflow Actions & Execution Core). Approval of option A authorizes this specification only, not an implementation plan or production modifications.
**Requested architecture:** Follow the established Dinktech ERP module layout: `Application/Ports`, `Contracts`, `Infrastructure/Persistence`; compose through `Bootstrap` and preserve legacy facade entrypoints.

**Last Windows acceptance evidence:** BE-REORG-010 + FIX01: 978/978 PHPUnit tests, 9,592 assertions, no failed/errored/skipped test; 30/30 BE010 cases; PHPStan 120 remaining diagnostics, none newly introduced compared with BE009. PHPStan raw exit 1 is inherited, not a clean report. The BE009/BE010 independent code reviews and authoritative Windows Git checkpoint are not yet confirmed and remain prerequisites to production implementation/acceptance.

**Source snapshot:** `/mnt/data/erp_v1.1.7.zip`, with BE009 candidate/FIX01 and BE010 candidate/FIX01 applied to a separate analysis snapshot. The snapshot does not establish the user's Windows Git SHA or clean working tree. Source anchors are listed in Appendix A. This document is source-backed design work; it does not imply that any BE011 code or tests have been run.

## 1. Objective and non-goals

Move the business workflow action discovery, authorization orchestration and lifecycle transition dispatch from `App\Services\BusinessWorkflowActions` and `App\Services\BusinessLifecycleTransitions` into one canonical `App\Modules\Workflow\BusinessActions\Application\BusinessWorkflowActionService`. Both existing classes remain backward-compatible entrypoints (composition facades). Preserve the HTTP API and business CRUD helper behavior byte-for-byte at the observable contract level. Enforce the existing DB record allowlist through a read-only repository port and persistence adapter.

This is an **ownership refactor**, not a new workflow feature. No new action vocabulary, permission semantics, lifecycle requirement for unbound business resources, cache strategy, DB schema, migration, policy grant, endpoint, or authorization bypass is authorized. State Resolution (BE010) and Lifecycle Core (BE009) retain canonical ownership of state mapping and transactional execution/locking/audit. The business action module orchestrates their existing legacy-compatible adapters; it does not reimplement them.

**Definition of done:** Both facades have no business algorithms, one canonical application service owns all four behaviors, a read-only port prevents arbitrary table access, compatibility tests lock old signatures and error responses, DB/HTTP/lock regression tests pass, the full inherited suite and all BE011 new tests run on Windows, and PHPStan has no new diagnostic identities relative to the 120-error BE010 baseline (moved diagnostics reviewed individually). Independent code review and a verified Git checkpoint are required before status `ACCEPTED`.

## 2. Verified as-is contracts

### 2.1 `App\Services\BusinessWorkflowActions`

Source: `backend/app/Services/BusinessWorkflowActions.php` (187 lines in the analyzed snapshot).

- Class: `final BusinessWorkflowActions`; no public constants.
- Exact public constructor: `__construct(?CodeIgniter\Database\ConnectionInterface $db = null)`.
- Exact three public operations and typed return values:

```php
/** @return array{mode:string,initial:string|null,states:list<string>} */
public function states(string $resource, OperationContext $operation): array;

/** @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>} */
public function options(string $resource, int $id, OperationContext $operation): array;

/** @return array{status:string,action_code:string} */
public function execute(string $resource, int $id, string $actionCode, OperationContext $operation): array;
```

- Existing dependencies: one connection (`$db ?? db_connect()`), `ResourceWorkflowStateResolver($db)`, `AuthorizationService(db: $db)`, `LifecycleRepository($db)`, an in-method `EffectiveProfileResolver($db)` for base view access, and on execution `LifecycleService(adapters: [$adapter], db: $db)`.
- `TABLES` is a **private** allowlist of exactly 12 business resource types (see section 4); it is not user-editable configuration.
- No generic write operation or native fallback mutation is exposed by this class.

### 2.2 `App\Services\BusinessLifecycleTransitions`

Source: `backend/app/Services/BusinessLifecycleTransitions.php` (37 lines). The `final` class is a static helper without a public constructor declaration. Its existing **public static** method must retain exact order, types and default:

```php
/** @param ConnectionInterface<mixed,mixed>|null $db */
public static function applyIfBound(
    string $type,
    int $id,
    string $destination,
    OperationContext $operation,
    ?ConnectionInterface $db = null,
): bool;
```

Observed order: obtain `$db ?? db_connect()`; create a fresh `ResourceWorkflowStateResolver($db)`; check `lifecycleId($type)`; return `false` immediately if `null`; otherwise choose target adapter; resolve `stateIdForCode($type, $destination)`; call `LifecycleService(...)->execute($type, $id, 'setstate', $operation, $toStateId)`; then return `true`. Failures throw; they do not return `false` except for an unbound Lifecycle. **Do not route this helper through the HTTP-oriented `execute()` operation**, because the helper supplies an expected destination State ID, while HTTP execution intentionally does not.

Current direct consumers include `Api020EmployeeStructureService`, Foundation-adjacent business services, Product/ProductGroup/ProductUom, and PriceList. They must not be modified by BE011. Existing `BusinessWorkflowWriteBoundary` is a separate mutation guard and is frozen.

### 2.3 HTTP and authentication contract

The controller `backend/app/Controllers/Api/BusinessWorkflow.php` constructs `new BusinessWorkflowActions()` for each action and obtains the trusted operation from the authenticated Shield actor via `MutationContext` and `RequestContext`. `Config/Routes.php` defines exactly these surfaces:

| HTTP method | Path | Controller/service method |
|---|---|---|
| GET | `/api/v1/business-workflow/{resource}/states` | `states` |
| GET | `/api/v1/business-workflow/{resource}/{id}/options` | `options` |
| POST | `/api/v1/business-workflow/{resource}/{id}/actions/{action_code}` | `execute` |

Routes use `(:segment)` for resource/action and `(:num)` for id. `ApiController::respondData` owns envelopes and `ApiExceptionHandler` owns errors. Keep method, route, response data shape, status mapping, error code/context, headers, `meta.request_id`, language handling, authenticated actor and CSRF filter behavior unchanged. OpenAPI already describes the three routes; BE011 does not modify it.

Existing public response data shapes are exactly:

```json
{"mode":"NATIVE|LIFECYCLE","initial":"CODE|null","states":["CODE"]}
{"mode":"NATIVE|LIFECYCLE","current":"CODE","options":[{"action_code":"code","status":"CODE"}]}
{"status":"CODE","action_code":"code"}
```

The strings in the first example describe allowed values, not one literal runtime `mode` value. `initial` can be null and `options` can be empty; no new keys, sorting or custom response wrapper are introduced.

### 2.4 Compatibility dependencies and stable error vocabulary

- Existing specialized lifecycle target adapters, **unchanged:** `FoundationLocationLifecycleAdapter` for `province`/`ward`, `ContactLifecycleAdapter` for `organization_contact`/`employee_contact`, `BusinessLifecycleTargetAdapter` for all other supported business resources.
- These adapters currently accept **concrete** `ResourceWorkflowStateResolver` objects. Do not substitute the BE010 `ResourceStateResolutionService` or change their constructors; the canonical action orchestration may depend on the BE010 legacy facade until a separate approved adapter-boundary refactor.
- `LifecycleService` (BE009 facade) owns `execute`, authorization recheck, row-level lock/reload, atomic mutation, concurrency exceptions and audit. Do not duplicate transaction boundaries or write directly in the new business-action service.
- `LifecycleRepository::findTransitions($lifecycleId, 'ACTIVE')` supplies ordered active transition rows for options. Do not change repository query, order, status filtering, or action codes.
- The following emitted error values must stay unchanged: `WF_TARGET_ADAPTER_UNAVAILABLE`, `WF_TARGET_NOT_FOUND`, `AUTH_POLICY_DENIED`, `WF_INVALID_TRANSITION`, `WF_TRANSITION_REQUIRED`. Domain errors from State Resolution, Contact authorization and Lifecycle Core continue to propagate unchanged, including their context payloads. No new public facade constants are allowed.

## 3. Target architecture and ownership

```text
backend/app/
  Modules/Workflow/BusinessActions/
    Application/
      BusinessWorkflowActionService.php
      Ports/
        BusinessWorkflowActionPort.php
        BusinessWorkflowRecordPort.php
    Contracts/
      BusinessWorkflowActionErrors.php
    Infrastructure/
      Persistence/
        SqlBusinessWorkflowRecordReader.php
  Bootstrap/
    BusinessWorkflowActionFactory.php
  Services/
    BusinessWorkflowActions.php                 # MODIFY: explicit delegation facade
    BusinessLifecycleTransitions.php            # MODIFY: static delegation facade
```

**Production allowlist: 8 files total (6 new, 2 modified).** No empty folders or speculative extension points. The application service contains both the HTTP action behavior and the CRUD-compatible `applyIfBound` operation; a single private target-adapter selector is shared by both execution paths. This addresses duplicated adapter dispatch without introducing another public subsystem or modifying existing adapters.

| File | Exact responsibility |
|---|---|
| `Application/Ports/BusinessWorkflowActionPort.php` | Four application operations; no SQL, transport dependencies or mutable state. |
| `Application/Ports/BusinessWorkflowRecordPort.php` | Validate a business resource name against the existing allowlist and load its persisted record by ID; read-only. |
| `Application/BusinessWorkflowActionService.php` | Canonical `states`, `options`, `execute`, `applyIfBound`, authorization checks and adapter selection; use BE009/BE010 unchanged. |
| `Contracts/BusinessWorkflowActionErrors.php` | Existing error values as constants; no new domain behavior. |
| `Infrastructure/Persistence/SqlBusinessWorkflowRecordReader.php` | Only fixed-table record lookup, PositiveId normalization and polymorphic Contact ownership verification; no writes/cache. |
| `Bootstrap/BusinessWorkflowActionFactory.php` | Stateless per-call dependency composition preserving the same DB connection for every default collaborator. |
| `Services/BusinessWorkflowActions.php` | Retain constructor and three public methods; delegate explicitly through a private readonly action port. |
| `Services/BusinessLifecycleTransitions.php` | Retain final class and exact static method signature; delegate through a newly constructed action port with supplied/default DB. |

### 3.1 Exact application and record ports

```php
namespace App\Modules\Workflow\BusinessActions\Application\Ports;

use App\Infrastructure\Mutation\OperationContext;

interface BusinessWorkflowActionPort
{
    /** @return array{mode:string,initial:string|null,states:list<string>} */
    public function states(string $resource, OperationContext $operation): array;

    /** @return array{mode:string,current:string,options:list<array{action_code:string,status:string}>} */
    public function options(string $resource, int $id, OperationContext $operation): array;

    /** @return array{status:string,action_code:string} */
    public function execute(string $resource, int $id, string $actionCode, OperationContext $operation): array;

    public function applyIfBound(string $type, int $id, string $destination, OperationContext $operation): bool;
}

interface BusinessWorkflowRecordPort
{
    public function assertBusinessResource(string $resource): void;

    /** @return array<string,mixed> */
    public function record(string $resource, int $id): array;
}
```

Application methods use the record port only for the existing `assertBusinessResource` / `record` calls. `applyIfBound` must **not** introduce an additional record query, resource allowlist assertion or per-action preauthorization that did not exist in the legacy static helper. The lifecycle layer remains the actual authorized mutation boundary.

### 3.2 Composition and constructor boundaries

- `BusinessWorkflowActionFactory::create` returns `BusinessWorkflowActionPort` with this exact signature; the four optional collaborators exist for test composition and must not change the public legacy facade constructors:

```php
public static function create(
    ?ConnectionInterface $db = null,
    ?BusinessWorkflowRecordPort $records = null,
    ?ResourceWorkflowStateResolver $states = null,
    ?AuthorizationService $authorization = null,
    ?LifecycleRepository $lifecycles = null,
): BusinessWorkflowActionPort;
```

- `BusinessWorkflowActionService` takes these exact constructor parameters in this order; it does not instantiate its own defaults:

```php
public function __construct(
    ConnectionInterface $db,
    BusinessWorkflowRecordPort $records,
    ResourceWorkflowStateResolver $states,
    AuthorizationService $authorization,
    LifecycleRepository $lifecycles,
);
```
- When DB is null, resolve one `db_connect()` before constructing the default reader, `ResourceWorkflowStateResolver`, `AuthorizationService` and `LifecycleRepository`, so all default collaborators use the **same connection**. When DB is injected, pass its exact instance unchanged. Avoid changing the existing injection behavior for explicitly supplied collaborators.
- The canonical application's resolver is the **legacy-compatible BE010 facade**, not a new global cache. Existing target adapters require it. Create one resolver per action engine; no process-level, static or cross-request sharing.
- Constructing the action engine must not perform DB queries or mutations. `states()` may resolve a base permission profile; `options()` and `execute()` resolve record-specific PBAC. A helper call that returns `false` for an unbound lifecycle must not trigger mutation/audit.
- The action application's private adapter selector preserves the exact three-way match shown in section 2.4 and uses the same DB and resolver as the engine. Its result implements `LifecycleTargetAdapter`. No user-controlled table name enters this selector.
- The `BusinessWorkflowActions` facade keeps exactly **three** public methods beyond its one-parameter constructor, no extra exposed method and no new public constants. It must not make action decisions or touch tables directly. The static `BusinessLifecycleTransitions` facade keeps its existing one static method and calls the canonical port's `applyIfBound`.

### 3.3 Read-only persistence and error contracts

The record reader maintains this exact map (12 resource types, 11 distinct SQL tables):

```php
private const TABLES = [
    'province' => 'provinces',
    'ward' => 'wards',
    'organization' => 'organizations',
    'department' => 'departments',
    'position' => 'positions',
    'employee' => 'employees',
    'product_group' => 'product_groups',
    'product_uom' => 'product_uoms',
    'product' => 'products',
    'price_list' => 'price_lists',
    'organization_contact' => 'contacts',
    'employee_contact' => 'contacts',
];
```

`assertBusinessResource` rejects absent keys and `ResourceNativeStateCatalog::isIndependent($resource)` with `WF_TARGET_ADAPTER_UNAVAILABLE` and context `['resource' => $resource]`; it does **not** silently normalize case or trim resource names. `record` first validates supported type, then calls `PositiveId::normalize($id, 'target_id', 'WF_TARGET_NOT_FOUND')`, then reads only `id` from the fixed table using the existing CI query builder. Missing rows or a mismatched owner-specific contact resource throw `WF_TARGET_NOT_FOUND` with `['target_id' => $id]`. For a contact row whose `contact_type` is malformed or unsupported, preserve any `ContactAuthorizationRegistry` exception (do not turn it into a generic not-found). The two contact Resources never authorize each other's row.

`BusinessWorkflowActionErrors` publishes **only these existing codes**, with exact values and no new HTTP/status mapping:

```php
public const ERROR_TARGET_ADAPTER_UNAVAILABLE = 'WF_TARGET_ADAPTER_UNAVAILABLE';
public const ERROR_TARGET_NOT_FOUND = 'WF_TARGET_NOT_FOUND';
public const ERROR_AUTHORIZATION_DENIED = 'AUTH_POLICY_DENIED';
public const ERROR_INVALID_TRANSITION = 'WF_INVALID_TRANSITION';
public const ERROR_TRANSITION_REQUIRED = 'WF_TRANSITION_REQUIRED';
```

Use the constants inside the canonical code/reader, while retaining existing exceptions emitted by BE009, BE010, Contact and PBAC. `Contracts` must not depend on repositories or the HTTP controller.

## 4. Mandatory behavior parity

### 4.1 States metadata (base permission only)

1. `states` validates resource against the fixed allowlist first; an independent/unknown resource cannot expose metadata.
2. Create `EffectiveProfileResolver($db)` and resolve the supplied trusted operation. Require base `view` permission for `$resource`; if absent, `AUTH_POLICY_DENIED` with `['resource' => $resource, 'action' => 'view']`. This endpoint intentionally does **not** promise record-level PBAC access to every State or record.
3. Read the trusted `lifecycleId` and `allowedPolicyStateCodes` from the same BE010 resolver.
4. Return `mode` as `NATIVE` if no lifecycle or `LIFECYCLE` if bound; `initial` as `initialCode` or (`ACTIVE` if in state codes), otherwise null; return `states` as an ordered list. Do not sort/dedupe/uppercase/reformat codes beyond existing behavior.

### 4.2 Record-specific options (read authority never becomes write authority)

1. `record` first checks allowlist/ID/contact ownership; `persistedStateId` is computed from the **persisted row** and `current` from `strtoupper((string) $row['status'])` as currently written. Do not trust client-supplied state or organizational hints.
2. Require record-level PBAC `view` via `AuthorizationService::authorize($operation, $resource, 'view', new EvaluationOrganizationRequest(persistedTarget: $row), $stateId)`. An unauthorized target fails closed with `AUTH_POLICY_DENIED`.
3. Check `setstate` independently. If not authorized, return `options: []` even when view is allowed.
4. For bound lifecycle, examine `LifecycleRepository::findTransitions($lifecycleId, 'ACTIVE')` in existing order. Offer only transitions from the persisted current State whose destination resolves through `codeForStateId`, whose destination differs from the current State, and for which the actor also has the **specific transition action**. Retain lowercase action normalization as-is. Skip invalid destination definitions on caught `DomainException`, not all errors.
5. `allowedAction` catches `DomainException` from PBAC and treats it as not actionable, but must not convert arbitrary persistence failures into success. The exception and failure ordering must match the old class.
6. For unbound business lifecycle only, if `setstate` is allowed, enumerate `ResourceNativeStateCatalog::codes($resource)` other than the persisted current status and emit action `setstate`. This is a **read-side legacy preview**; it does not enable native mutation through generic POST.
7. Return unchanged `mode`, `current`, `options` keys and order.

### 4.3 HTTP generic action execution

1. `execute` validates the fixed business resource allowlist, then rejects an unbound Lifecycle with `WF_TRANSITION_REQUIRED` and context `['resource' => $resource]` **before** record lookup; this ordering is part of the compatibility requirement.
2. Load target via read port. Require record-specific `setstate` PBAC against the persisted State, not a caller hint; keep denial code/context exact.
3. Only **after** the authorization step, normalize `actionCode` with `strtolower(trim($actionCode))` and validate regex `\A[a-z][a-z0-9_:-]*\z/D`. Invalid code: `WF_INVALID_TRANSITION` with `['action_code' => $actionCode]`.
4. Select the specialized adapter by resource, execute `LifecycleService(adapters: [$adapter], db: $db)->execute($resource, $id, $actionCode, $operation)` **without** an expected destination ID; Lifecycle Core revalidates the transition and actor permission under its existing boundaries.
5. Return the `status` resolved from `result['transition']['to_state_id']` through BE010 and normalized `action_code`; do not accept/request a body-supplied to-state ID, lifecycle ID, organization scope or authorization decision.
6. No direct status update, transaction, retry, bypass, or audit duplication is permitted in this service.

### 4.4 Static set-state helper parity

1. Keep `BusinessLifecycleTransitions::applyIfBound` as a static, five-parameter legacy surface. It creates a fresh factory engine using the same nullable/default connection and delegates once.
2. Canonical `applyIfBound` must check `lifecycleId` **before** adapter creation or destination lookup, returning `false` without mutation for unbound business resources. This does not imply unknown/invalid resources are accepted; BE010 resolver errors remain.
3. If bound, use the same specialized adapter mapping, map `$destination` to an expected State ID through `stateIdForCode`, and invoke `LifecycleService(...)->execute($type, $id, 'setstate', $operation, $toStateId)` with the expected destination argument. Return `true` **only after successful execution**; preserve all errors and DB rollback behavior.
4. Do not redirect this helper to the generic action `execute`: the helper requires the `setstate` action and an explicit expected destination validated by Lifecycle Core. Existing product/employee/org/foundation integrations and native-specific fallbacks stay unchanged.

### 4.5 Security, locking, and error invariants

- All PBAC decisions use the authenticated/operation actor, trusted persisted target row, and trusted State ID; base `view` grants metadata only and never implies `setstate`.
- Independent Resources (ten exact exceptions defined by BE010) cannot reach the generic business-action boundary. The old native fallback for **unbound business** Resources remains, but only where currently implemented.
- Only fixed resource/table names may be used in SQL; no interpolation of request-provided table or status/transition fields. Business-owned Contact IDs must not be accepted via the wrong resource type.
- Keep BE009 InnoDB `SELECT ... FOR UPDATE` behavior, lock lifetime, optimistic/stale-state checks, rollback and audit identity. No second transaction around `LifecycleService`.
- Keep `workflow_states.status` (catalog enable flag) distinct from persisted `business_record.status` (business State code).
- Keep exact error precedence: unsupported resource before record lookup; generic execute checks lifecycle binding before record lookup; record-specific `setstate` before validating action syntax; `applyIfBound` checks lifecycle before destination mapping.
- Keep HTTP POST CSRF enabled/verified by the existing CI global filter. In CI FeatureTestTrait, a missing token can raise `CodeIgniter\Security\Exceptions\SecurityException` (code 403) instead of returning a `TestResponse`: tests must handle this without weakening CSRF requirements and prove no write occurred.

## 5. Freeze list, scope, and compatibility policy

**Allowed production files:** Only the 8 paths in section 3; no repository, PBAC, Lifecycle, State Resolution, controller, route, `Config/Services`, adapter, schema or frontend changes. The existing `ResourceNativeStateCatalog`, `BusinessWorkflowWriteBoundary`, `WorkflowBusinessStatus`, Product and employee services are frozen.

**Allowed new test-only files:** `backend/tests/unit/BeReorg011BusinessActionsBoundaryTest.php`, `...CompatibilityTest.php`, `...FactoryTest.php`, `...ApplicationTest.php`, `backend/tests/database/BeReorg011BusinessActionsHttpTest.php`, `...DatabaseTest.php`, `...LockTest.php`, optional namespaced fixture under `backend/tests/_support/Workflow/`, and `backend/tools/test-business-workflow-actions.php` (guarded test runner). No changes to test DB schema.

**One justified inherited-runner exception:** `backend/tools/test-resource-state-resolution.php` currently enforces exactly 978 JUnit cases. To allow BE011 to extend the test suite, replace **only that total-count condition** with *at least* 978 (and update corresponding explanation), retaining the **exact 30 BE010 method/class gate** and no-failure/error/skip checks. `backend/tools/test-workflow-lifecycle.php` already accepts at least 948 and requires no changes. The BE011 runner alone requires the exact new total. Additional production/test legacy changes require explicit variance documentation before inclusion; do not silently expand scope.

**Not permitted:** Adding a general `BusinessActions` ORM/table resolver, changing all business CRUD state mutations, enforcing Lifecycle for currently unbound business resources, adding an authorization cache, new repository SQL writes, modifying migration/factory in BE009/BE010, changing HTTP response semantics, broad policy cleanup, or introducing new application error codes.

## 6. Verification and BE011 test inventory

**Inherited, evidence-backed Windows baseline:** 978 PHPUnit tests/9,592 assertions, BE010 30/30, BE009 32/32, BE008 26/26, BE007 24/24, existing PBAC 91/91, Foundation 17 guarded checks; PHPStan 120 inherited diagnostics. Assertions count for BE011 must be reported from the actual run, not predicted.

**Planned new BE011 tests: 34 tests in 7 named classes; projected complete suite 1,012 (= 978 + 34).** The implementation plan must retain or explicitly re-approve this inventory, not silently change counts.

| Test class | Count | Exact required test methods |
|---|---:|---|
| `BeReorg011BusinessActionsBoundaryTest` (unit) | 5 | `testApplicationPortDefinesFourOperations`; `testRecordPortExposesReadOnlyOperations`; `testModuleLayersAndContractsAreIsolated`; `testNoRouteOrAuthorizationInfrastructureChanges`; `testErrorConstantsPreserveLegacyValues` |
| `BeReorg011BusinessActionsCompatibilityTest` (unit) | 5 | `testActionFacadeRetainsConstructorAndThreeSignatures`; `testLifecycleTransitionsRetainsStaticSignatureAndReturnType`; `testExplicitDelegationAndNoLocalOrchestration`; `testFactoryReturnsFreshEngineWithSameConnection`; `testLegacyConsumersStillReferenceUnchangedFacades` |
| `BeReorg011BusinessActionsFactoryTest` (unit) | 4 | `testDefaultsReuseOneConnection`; `testInjectedRecordPortUsedAsIs`; `testFactoryDoesNotCacheBetweenInvocations`; `testAdapterSelectionUsesCorrectExistingLegacyTypes` |
| `BeReorg011BusinessActionsApplicationTest` (database) | 7 | `testStatesRequiresBaseViewBeforeMetadata`; `testStatesNativeAndLifecycleMetadataParity`; `testOptionsRequiresTargetViewAndSetstateSeparately`; `testOptionsFiltersInactiveInvalidAndUnauthorizedTransitions`; `testExecuteRejectsNativeResourceWithoutMutation`; `testExecutePreservesPersistedStateSetstateAndActionCodeValidation`; `testApplyIfBoundFalseForUnboundAndExpectedDestinationForBound` |
| `BeReorg011BusinessActionsHttpTest` (database) | 6 | `testStatesMetadataBasePermissionAndResponseEnvelope`; `testOptionsPersistedStateAndActorPermission`; `testPostExecuteAuthorizedLifecycleTransitionAndAudit`; `testPostExecuteDeniedCannotWrite`; `testPostExecuteInvalidActionAndForgedDestinationRejected`; `testPostExecuteMissingCsrfRejectedWithoutWrites` |
| `BeReorg011BusinessActionsDatabaseTest` (database) | 5 | `testTwelveResourceAllowlistAndPositiveIdGuard`; `testContactSplitMismatchCannotResolveOtherOwner`; `testNativeFallbackOptionsAndStaticFalseForUnbound`; `testExecutionReportsUnknownAndMissingTargetsAsBefore`; `testInactiveTransitionOrDestinationNeverOffered` |
| `BeReorg011BusinessActionsLockTest` (database) | 2 | `testRealInnoDbLockTimeoutDoesNotMutateOrAudit`; `testStaleStateConcurrentMutationRollsBack` |
| **TOTAL** | **34** | **1,012 suite total targeted** |

**Test requirements:**

1. Unit tests prove the ports, error values, constructor signatures, explicit delegation, default DB instance identity, and no static/singleton caching. The ApplicationTest class uses real database/identity fixtures so actual PBAC authorization ordering, native/lifecycle metadata and transition selection are tested with the real services. Avoid tests that merely read source strings as a substitute for runtime behavior.
2. Real-DB and HTTP tests use only the disposable `127.0.0.1:3306/erp_test` database with prefix `dinktech_`, real Shield actors, group/permission fixtures and genuine Lifecycle membership. Create record codes within actual migration column constraints; do not weaken constraints to make tests pass.
3. HTTP tests cover the three **generic business workflow endpoints** directly, not just API030 configuration or resource-specific set-state routes. Verify 200 responses, expected data envelope, 403 denial, not-found/invalid responses as currently mapped, CSRF failure/no writes, action-specific permission, and persisted-state-based options.
4. Lock tests use InnoDB and at least two independent DB connections, with explicit rollback/cleanup and no optimistic assumptions about timeout response. Assert no unauthorized state/audit writes after stale-state/lock failure. Do not run tests concurrently on the shared test DB.
5. Characterization tests must first establish original behavior where practicable. All test-only failures/fixes are incremental, retain assertions and are recorded as FIX0x rather than silently changing product behavior.
6. New runner preflight validates the exact 7 classes/34 method names and required source paths **before touching DB**; `--help` returns exit 0, missing/incorrect `--confirm-test-db` exits 2 without DB access. One child invocation of the BE010 runner only, no duplicate full-suite process. Require a fresh JUnit with **exactly 1,012 cases**, none failed/errored/skipped, exact BE011 names, inherited BE010 30/BE009 32 gates, and Foundation/BE007/BE008 coverage retained. Report raw PHPStan status honestly; compare full diagnostic identities with BE010 baseline, not only error count. Do not modify PHPStan ignore/baseline configuration.
7. Static gates: PHP syntax on all changed PHP files, Reflection compatibility signatures, `git diff --check`, no migrations/schema/route/frontend/authorization engine modifications, source parity on relocated routines, no new DB writes in the record adapter, and incremental patch `git apply --check` / apply / reverse on a *clean BE010+FIX01* source.
8. A full Windows run with the exact user's Git baseline, independent reviewer with no unresolved Important/Critical findings, and explicit checkpoint commit are separate release gates. **Never call BE011 ACCEPTED on the basis of an offline harness or green PHPUnit alone.**

## 7. Delivery order (not authorized until plan approval)

1. Validate actual Windows BE010 Git checkpoint/working tree and complete outstanding independent reviews before applying BE011 production changes. The analysis ZIP alone does not establish that precondition.
2. Write a TDD implementation plan from this approved specification, with the 8-file production allowlist, 34 exact test names, test fixture design, runner adjustment and independent review plan.
3. Implement `BusinessWorkflowRecordPort` and its fixed-table read adapter with negative resource/ID/contact-ownership tests first; add `Contracts` and `BusinessWorkflowActionPort`.
4. Relocate `states`, `options`, `execute`, `applyIfBound` into the canonical service; consolidate adapter dispatch in one private selector and verify behavior against baseline.
5. Add factory and replace two legacy classes with explicit delegation; verify old PHP public API byte-for-byte and existing consumer call sites.
6. Add direct HTTP/DB/real-lock characterization tests and guarded BE011 runner; make only the documented BE010 JUnit total-count compatibility change.
7. Perform source/static/diff checks, create a BE010+FIX01-relative incremental patch, self-review and a truly independent code review where available; do not claim independent review if only self-review was possible.
8. Have the user apply patch and run the guarded Windows full suite; reconcile each PHPStan diagnostic identity, not count. Close with a Git checkpoint only after all review and acceptance gates.

## 8. Risks and explicit decisions

| Risk / decision | Binding resolution |
|---|---|
| Dual entrypoints could fork adapter logic | Both delegate to one canonical service and private adapter selector, but retain different public signatures and expected-destination semantics. |
| New read port might become a generic SQL gateway | Only `assertBusinessResource` and `record`; the SQL allowlist stays fixed and read-only. |
| Contact record could be accessed with another owner's permission | Match the persisted contact's owner to the requested `organization_contact` or `employee_contact` resource before PBAC or mutation. |
| `view` leaks mutation authority | Metadata has base view only; options requires record view and setstate plus transition action; execute revalidates setstate and Lifecycle Core's authorization. |
| Native option list mistaken for a generic native mutation endpoint | Native fallback remains read-only here; generic execute rejects absent Lifecycle. Resource-specific native state paths remain frozen. |
| Helper's explicit destination silently lost | Keep `applyIfBound` with `stateIdForCode` and expected destination argument to LifecycleService. |
| SecurityException in FeatureTestTrait hides actual no-CSRF denial | Catch exact CI exception (403) when appropriate, verify persisted target/audit unchanged; no CSRF disabling. |
| Old `ResourceWorkflowStateResolver` concrete type required by adapters | Pass BE010 legacy facade; do not refactor specialized adapter signatures in BE011. |
| BE010 runner rejects added tests | Expand only inherited total threshold to at least 978, retain exact 30 BE010 method gate; BE011 demands exact 1,012. |
| Reconstructed snapshot differs from Windows commit | Incremental patch must pass `git apply --check` on the user's checkout; stop and reconcile if not. |
| PHPStan reports relocation rather than new faults | Compare (file, line, identifier, message) and trace moved diagnostics explicitly; 120 is the BE010 inherited baseline, not a clean target. |

## Appendix A - Source anchors reviewed

- `backend/app/Services/BusinessWorkflowActions.php` (constructor, 3 public methods, TABLES, record lookup, adapter switch and PBAC helper).
- `backend/app/Services/BusinessLifecycleTransitions.php` (static five-parameter helper, early false return and expected destination argument).
- `backend/app/Controllers/Api/BusinessWorkflow.php` and `backend/app/Config/Routes.php` (3 generic endpoints, operation context).
- `backend/app/Services/{LifecycleService,ResourceWorkflowStateResolver,ResourceNativeStateCatalog,BusinessWorkflowWriteBoundary}.php`.
- `backend/app/Services/{FoundationLocationLifecycleAdapter,ContactLifecycleAdapter,BusinessLifecycleTargetAdapter}.php`.
- `backend/app/Authorization/{AuthorizationService,EffectiveProfileResolver,ContactAuthorizationRegistry}.php`.
- `backend/app/Repositories/LifecycleRepository.php` (active transition read).
- `backend/app/Modules/Workflow/{Lifecycle,StateResolution}/` (existing BE009/BE010 canonical module layout).
- `backend/app/Modules/Product/{Product,ProductGroup,Uom}/`, `backend/app/Modules/Pricing/PriceList/`, `backend/app/Services/Api020EmployeeStructureService.php` (legacy static-helper callers).
- `contracts/openapi/openapi.yaml` (3 path descriptions and 200/401/403/404/409/422 response documentation).
- `docs/as-built/{05_API_CONTRACT,07_IAM_AND_WORKFLOW,20_RESOURCE_STATE_LIFECYCLE_CONTRACT}.md` (business semantics and known native fallback gap).
- `backend/tools/test-resource-state-resolution.php` (`=== 978` in JUnit assertion); `backend/tools/test-workflow-lifecycle.php` (`>= 948`).
- BE010 source patch, FIX01 CSRF test patch, and Windows acceptance evidence (978 tests and 120 existing PHPStan diagnostics).

## Appendix B - Review checklist

- [ ] Owner agrees that option A includes *both* facades and a single four-operation application port.
- [ ] Owner agrees to **exactly 8** allowed production files (6 new, 2 modified), no adapter/controller/route/PBAC changes.
- [ ] Owner agrees native fallback and static `applyIfBound` semantics must be preserved, not modernized in this task.
- [ ] Owner agrees the justified BE010 test-runner total-count exception and the target of **34 new tests / 1,012 total**.
- [ ] Owner has reviewed this written specification before authorizing an implementation plan.
