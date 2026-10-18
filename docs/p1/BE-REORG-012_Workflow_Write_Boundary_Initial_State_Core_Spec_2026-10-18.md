# BE-REORG-012 — Workflow Write Boundary & Initial State Core

**Date:** 2026-10-18
**Status:** DESIGN SPECIFICATION — AWAITING OWNER APPROVAL
**Scope decision:** Option A approved: extract the existing Workflow Write Boundary and Initial State rules, without changing their externally observable behavior. This approval authorizes **this specification only**, not an implementation plan or production patch.
**Reference Windows checkpoint:** `b29e7179dc1f1bd3dfcdcf25742734c86bc61470`, branch `checkpoint/be009-be011-20261018`.
**Verified inherited baseline:** BE011 + FIX01: **1,012/1,012 PHPUnit tests**, 9,960 assertions, no failed/errored/skipped cases, 17 Foundation guard checks; **120 inherited PHPStan diagnostics** with zero new identities versus BE010. PHPStan raw exit 1 is **not a clean static-analysis run**.
**Source inspected:** ERP v1.1.7 plus BE009–BE011 candidate source, with BE011_FIX01 affecting only an InnoDB test; compare exact user Windows tree against the checkpoint before implementation. Source anchors are in §13.

## 1. Objective, boundaries and definition of done

Move the **decision logic** currently owned by three `App\Services` classes into the canonical `App\Modules\Workflow\WriteBoundary` module, preserving the public entrypoints as compatibility façades:

1. `BusinessWorkflowWriteBoundary`: initial create normalization for *Lifecycle-bound* resources and prevention of direct `status` updates from business/application services (including imports and non-HTTP callers).
2. `WorkflowBusinessStatus`: vocabulary validation against active Lifecycle members or the *caller-provided* native vocabulary.
3. `InitialCreateStateNormalizer`: resolution of persisted initial State and a pure, database-free `withInitial()` validation/defaulting path.

**Ownership refactor only.** No new status values, action codes, lifecycle assignments, grants, HTTP routes, response envelopes, schemas, migrations, retry policies, transaction wrappers, SQL, or front-end changes. BE010 State Resolution remains the authority for Resource/Lifecycle membership and initial state; BE009 and BE011 remain the authorities for transition execution, transaction/row locking, audit and action authorization. Do **not** move `ResourceNativeStateCatalog` or alter its ten independent Resources.

**Definition of done:** the canonical module owns all four use-case operations plus the pure initial-state rule; the three original classes keep their exact signatures and constants; no canonical Application class imports a legacy Workflow façade, CodeIgniter DB or concrete repository (the existing read-only `ResourceNativeStateCatalog` authority is the sole `App\Services` dependency allowed for the pure rule); tests demonstrate branch-by-branch behavioral parity, including error code and context, native fallbacks, unknown-resource fail-closed and no unauthorized state mutation; inherited and 36 new tests execute on Windows; no *new diagnostic identities* relative to BE011; independent review and real Windows Git checkpoint are required before `ACCEPTED`.

## 2. Current code contracts (frozen, source-backed)

### 2.1 `BusinessWorkflowWriteBoundary.php` (55 lines)

`final class App\Services\BusinessWorkflowWriteBoundary` has **no declared public constructor or public constant**. It has exactly two public *static* operations:

```php
/** @param array<string,mixed> $data @return array<string,mixed> */
public static function create(string $resource, array $data, ConnectionInterface $db): array;

/** @param array<string,mixed> $current @param array<string,mixed> $changes */
public static function assertNoDirectStateMutation(
    string $resource, array $current, array $changes, ConnectionInterface $db,
): void;
```

The present `create()` calls `lifecycleId($resource)` first. **If it is null, it returns `$data` as-is**, even when there is no `status`; it does **not** insert `ACTIVE`. If bound, it resolves `initialCode()` using an `InitialCreateStateNormalizer` and validates/normalizes the client's hint. Unknown Resources and a forbidden Lifecycle association on any of the ten independent Resources raise the existing BE010 errors; they are not treated as safely unbound.

The present `assertNoDirectStateMutation()`:

- Returns immediately if the key `status` is absent from `$changes` (does not query the Resource).
- Returns immediately when `$changes['status']` is a string and `strtoupper(trim(changed)) === strtoupper((string) (current.status ?? ''))`; **trim is applied to the new value, not the current value**.
- On a true change, calls `lifecycleId($resource)`; if bound throws `DomainException('WF_TRANSITION_REQUIRED', ['resource' => $resource])`; if unbound it returns without additional validation. In particular, a non-string status update on an unbound Resource is not rejected by *this guard* (downstream validation remains responsible).
- A true change for an unknown Resource cannot bypass catalog validation. Do not accidentally move this lookup before either early return or make unbound resources reject updates here.

### 2.2 `WorkflowBusinessStatus.php` (44 lines)

`final class App\Services\WorkflowBusinessStatus` has the following public static signature **in this exact argument order and with the same null default**:

```php
/** @param list<string> $nativeCodes */
public static function normalize(
    string $type,
    mixed $value,
    array $nativeCodes,
    string $errorCode,
    ?ConnectionInterface $db = null,
): string;
```

- Reject non-string `$value` with `DomainException($errorCode, ['status' => $value])`, retaining the **raw original type/value**.
- For strings compute `$code = strtoupper(trim($value))`; reject empty string and `strlen($code) > 50` with `['status' => $code]`; maintain byte-length (not character-length) behavior.
- **Only after syntactic validation**, resolve `lifecycleId($type)`. If bound, allowed values come from BE010 `allowedPolicyStateCodes($type)`, i.e. active Lifecycle members; ignore `$nativeCodes` in this branch. If unbound, **use the caller-supplied `$nativeCodes` unchanged** (do not replace with `ResourceNativeStateCatalog::codes()` or normalize/sort/deduplicate the allowlist).
- Perform strict `in_array($code, $allowed, true)` and return `$code` if allowed; otherwise throw the same supplied `$errorCode` and `['status' => $code]`. Invalid/missing Resource metadata propagates BE010 errors without native fallback.

### 2.3 `InitialCreateStateNormalizer.php` (68 lines)

`final class App\Services\InitialCreateStateNormalizer` retains **exactly one public constant**:

```php
public const ERROR_UNSUPPORTED_INITIAL = 'WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE';
```

It retains the nullable, **concrete legacy resolver injection**, public instance and public static signatures:

```php
public function __construct(?ResourceWorkflowStateResolver $states = null);

/** @param array<string,mixed> $payload @return array<string,mixed> */
public function normalize(string $resource, array $payload): array;

/** @param array<string,mixed> $payload @return array<string,mixed> */
public static function withInitial(
    string $resource, array $payload, ?string $initial,
): array;
```

- `normalize()` resolves `initialCode($resource)` from the **exact injected resolver instance when provided**, otherwise a fresh resolver on first use, then invokes the pure rule.
- `withInitial()` with null `$initial`: only if `status` key is **absent** and `ResourceNativeStateCatalog::codes($resource)` includes `ACTIVE`, inject `'status' => 'ACTIVE'`; otherwise return payload exactly as supplied, including an explicit native `status` of any type. It does not query the DB or authorize a native status.
- With non-null `$initial`: require `preg_match('/\A[A-Z][A-Z0-9_:-]{0,49}\z/D', $initial) === 1`, without silently uppercasing `$initial`; otherwise `DomainException('WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE', ['resource' => $resource, 'initial_status' => $initial])`.
- If `status` is supplied, it must be a string for which `strtoupper(trim(status)) === $initial`; mismatch/non-string raises `DomainException('WF_INITIAL_STATE_INVALID', ['resource' => $resource, 'initial_status' => $initial])`. On success the payload `status` becomes exactly `$initial`.
- This pure method is **not** a Resource-membership validator: passing an explicit hypothetical `$initial = 'DRAFT'` is a pure input contract even for native Resources. Resource and Lifecycle authority are enforced by `normalize()` and the BE010 resolver.

### 2.4 Upstream/downstream consumers

Current direct production consumers (do not edit) include `Modules/Foundation/Application/LocationService`, `Modules/Organization/Application/OrganizationService`, `Modules/Contact/Application/ContactService`, Employee Structure `DepartmentService`, `PositionService`, `EmployeeService`, Product `ProductGroupService`, `ProductUomMasterService`, `ProductMasterService`, Pricing `PriceListService`, and existing `Api010FoundationOrganizationContactService`, `Api020EmployeeStructureService`, `Api050ProductCatalogService`. The consumers may call either the write-boundary static façade or the initial normalizer directly. Preserve exact class names, argument names/order, static vs instance method, return type, default and exception shape.

Current source tests to inherit intact: `P1WorkflowCreateInitialStateTest`, `P1WorkflowCreateLifecycleIntegrationTest`, `BusinessWorkflowTenNativeBoundaryTest` and BE001–BE011 suites. No consumer/controller rewrite is approved.

## 3. Approved target architecture

```text
Existing CRUD services / imports / API010 / API020 / API050
        | existing class names and signatures
        v
App\Services\BusinessWorkflowWriteBoundary       (static compatibility facade)
App\Services\WorkflowBusinessStatus              (static compatibility facade)
App\Services\InitialCreateStateNormalizer        (instance + pure static facade)
        | delegate, no business algorithm
        v
App\Bootstrap\WorkflowWriteBoundaryFactory      (stateless composition)
        |
        v
Modules/Workflow/WriteBoundary/Application/WorkflowWriteBoundaryService
        | implements WorkflowWriteBoundaryPort
        | uses InitialStateRules::withInitial(...) for pure behavior
        v
Application/Ports/WorkflowStateLookupPort         (3 READ methods)
        ^
        |
Infrastructure/Adapters/ExistingStateResolutionLookupAdapter
        | wraps ResourceStateResolutionPort (BE010 default)
        |   OR injected ResourceWorkflowStateResolver (legacy constructor)
        v
BE010 State Resolution / existing repositories (unchanged)
```

**Rationale for the adapter:** the BE010 `ResourceStateResolutionService` implements `ResourceStateResolutionPort`, but the publicly injectible `ResourceWorkflowStateResolver` façade currently does **not** implement that interface, despite exposing the same operations. Do not break `InitialCreateStateNormalizer(?ResourceWorkflowStateResolver $states)` or modify BE010's public façade to force an interface. A three-method read bridge in Infrastructure covers both sources without introducing SQL, new caches or an eleven-method fake implementation. `Infrastructure/Persistence` is intentionally **not created**: BE012 adds no persistence logic; its existing persistence dependency is BE010.

### 3.1 Strict production file allowlist — 10 files

| Action | File | Ownership |
|---|---|---|
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Application/Ports/WorkflowWriteBoundaryPort.php` | Four canonical use-case method signatures |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Application/Ports/WorkflowStateLookupPort.php` | Exactly three read-only state lookup operations |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Application/WorkflowWriteBoundaryService.php` | Bound/unbound write guards and status normalization; delegates pure initial rules |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Application/InitialStateRules.php` | The single authoritative pure `withInitial` algorithm |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Contracts/WorkflowWriteBoundaryErrors.php` | Stable unsupported-initial and BE010 alias error codes |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Infrastructure/Adapters/ExistingStateResolutionLookupAdapter.php` | Three-method bridge to existing BE010 port or injected BE010 legacy façade |
| CREATE | `backend/app/Bootstrap/WorkflowWriteBoundaryFactory.php` | Stateless composition; default BE010 factory; honor injected resolver |
| MODIFY | `backend/app/Services/BusinessWorkflowWriteBoundary.php` | Exactly two static delegating methods, no logic/SQL |
| MODIFY | `backend/app/Services/WorkflowBusinessStatus.php` | Exact legacy static normalize signature, delegating only |
| MODIFY | `backend/app/Services/InitialCreateStateNormalizer.php` | Exact constructor, instance normalize, pure static withInitial, constant |

**No other production files** in BE012. No changes to BE009, BE010, BE011 canonical modules, `ResourceNativeStateCatalog`, authorization services, consumer modules, migrations, routes or OpenAPI. New tests, one test fixture, BE012 guarded runner, and the inherited BE011 runner's **test-total comparison only** are allowed outside this production budget.

### 3.2 Canonical interfaces — frozen for Implementation Plan

```php
namespace App\Modules\Workflow\WriteBoundary\Application\Ports;

interface WorkflowWriteBoundaryPort
{
    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function create(string $resource, array $data): array;

    /** @param array<string,mixed> $current @param array<string,mixed> $changes */
    public function assertNoDirectStateMutation(
        string $resource, array $current, array $changes,
    ): void;

    /** @param list<string> $nativeCodes */
    public function normalizeStatus(
        string $type, mixed $value, array $nativeCodes, string $errorCode,
    ): string;

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function normalizeInitial(string $resource, array $payload): array;
}

interface WorkflowStateLookupPort
{
    public function lifecycleId(string $type): ?int;

    /** @return list<string> */
    public function allowedPolicyStateCodes(string $type): array;

    public function initialCode(string $type): ?string;
}
```

`WorkflowWriteBoundaryService::__construct(WorkflowStateLookupPort $states)` implements the four operations in `WorkflowWriteBoundaryPort`. Do not add an `OperationContext` or generic mutation bypass switch; a write boundary is a guard, not a grant or transition executor. `InitialStateRules` is a final, non-instantiable class exposing only `public static function withInitial(string $resource, array $payload, ?string $initial): array`; it must have no DB/service dependency. The canonical service must not reference `ResourceWorkflowStateResolver`, `ConnectionInterface`, CodeIgniter service locator, SQL queries, repositories or `new LifecycleService`. `InitialStateRules` may reference the existing pure `ResourceNativeStateCatalog::codes($resource)` authority only; do not duplicate its vocabulary or treat it as a DB dependency.

`WorkflowWriteBoundaryErrors` is a final constants-only class with **exactly these three constants**:

```php
public const ERROR_UNSUPPORTED_INITIAL = 'WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE';
public const ERROR_INITIAL_STATE_INVALID = ResourceStateResolutionErrors::ERROR_INITIAL_STATE_INVALID;
public const ERROR_TRANSITION_REQUIRED = ResourceStateResolutionErrors::ERROR_TRANSITION_REQUIRED;
```

`ResourceStateResolutionErrors` is the **already existing** BE010 Contracts class. This is an alias, not an alternate validation vocabulary. The legacy `InitialCreateStateNormalizer` façade re-exports **only** `ERROR_UNSUPPORTED_INITIAL`. Preserve DomainException types, exact codes and context arrays; do not convert them to generic HTTP errors.

### 3.3 Adapter and factory

`ExistingStateResolutionLookupAdapter` is `final`, implements `WorkflowStateLookupPort`, and has the exact constructor:

```php
public function __construct(
    ResourceStateResolutionPort|ResourceWorkflowStateResolver $states,
);
```

Only forward `lifecycleId`, `allowedPolicyStateCodes`, and `initialCode`; do not maintain instance/static caches, do SQL, normalize errors, or swallow exceptions. Legacy reference is permitted **only** in this Infrastructure adapter and the legacy façades/Bootstrap. Injected legacy object identity must remain unchanged. No BE010 class is modified to satisfy the bridge.

`WorkflowWriteBoundaryFactory` is a `final` stateless class, no public constructor; public composition API is:

```php
/** @param ConnectionInterface<mixed,mixed>|null $db */
public static function create(
    ?ConnectionInterface $db = null,
    ?ResourceWorkflowStateResolver $states = null,
): WorkflowWriteBoundaryPort;
```

If `$states !== null`, the adapter wraps that exact instance and **does not** create a default BE010 resolver/engine. Otherwise create `WorkflowStateResolutionFactory::create(db: $db)` and wrap it. Each call returns a new canonical service and a new adapter, without sharing caches across calls/requests. When a DB is provided, pass that exact `ConnectionInterface` into the BE010 factory; when null, preserve the current default DB behavior. No eager metadata read, transaction, static singleton, process cache or connection switch.

Legacy delegation strategy:

- `BusinessWorkflowWriteBoundary::create()` and `assertNoDirectStateMutation()` call `WorkflowWriteBoundaryFactory::create(db: $db)` and the corresponding port method.
- `WorkflowBusinessStatus::normalize()` calls the same factory with its optional `$db` and forwards *all* original arguments (including the exact `$nativeCodes` and dynamic `$errorCode`).
- `InitialCreateStateNormalizer::__construct()` keeps storing the original nullable resolver; `normalize()` creates a new per-call engine via `WorkflowWriteBoundaryFactory::create(states: $this->states)`, invokes `normalizeInitial`, and retains the lazy default lookup behavior. `withInitial()` delegates directly to `InitialStateRules::withInitial()`; **no DB/service construction or Resource lookup on the static path**.
- A façade may initialize a stateless factory object before an early return, but **no DB lookup** may occur before the early return branches described in §2.

## 4. Exact observable behavior to preserve

| Operation / input | Outcome; must not change |
|---|---|
| Boundary `create`, Lifecycle unbound | Return incoming payload byte-equivalent as PHP array; no implicit `ACTIVE` |
| Boundary `create`, Lifecycle bound, payload missing status | Resolve authoritative `initialCode` and insert it |
| Boundary `create`, Lifecycle bound, matching client hint | Canonicalize persisted `status` to authoritative initial code |
| Boundary `create`, Lifecycle bound, mismatching/non-string client hint | `WF_INITIAL_STATE_INVALID`, context `resource,initial_status`; no write |
| Boundary, Resource absent or independent resource attached to Lifecycle | Existing BE010 fail-closed error; no fallback to native |
| Direct mutation: `changes` without `status` | Return immediately; no State/Resource read |
| Direct mutation: case-equivalent trimmed incoming string | Return immediately; no State/Resource read |
| Direct mutation: true change, Lifecycle-bound | `WF_TRANSITION_REQUIRED`, context `resource`; no update or audit |
| Direct mutation: true change, unbound | Guard permits; downstream validations remain unchanged |
| Status normalize: non-string input | Dynamic supplied error code; `['status' => raw value]` |
| Status normalize: blank or over 50 bytes | Dynamic supplied error code; `['status' => uppercase-trimmed code]` |
| Status normalize: Lifecycle-bound | Only active Lifecycle State codes from BE010, strict membership |
| Status normalize: Lifecycle-unbound | Only exact caller-supplied native codes; no catalog substitution |
| Pure `withInitial`, null initial and ACTIVE in native catalog | Default only if `status` key absent |
| Pure `withInitial`, null initial and explicit `status` | Preserve supplied value verbatim, even if invalid; downstream validates |
| Pure `withInitial`, non-null malformed initial | `WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE` with `resource,initial_status` |
| Pure `withInitial`, non-null valid initial | Client hint (if present) must match after trim/uppercase; output exact initial |

**Ordering matters:** syntactic status validation precedes any lifecycle query; no-status/no-change mutation returns precede lifecycle queries; Lifecycle existence checks precede `initialCode` on `create`. Preserve error priority for invalid inputs, including unregistered Resource types, and do not change case normalization of `$type` or current-state values. Do not silently broaden the mutation guard to reject all native state updates: they remain governed by their existing business services and PBAC checks.

## 5. Ten native exceptions and Lifecycle safety

Keep `ResourceNativeStateCatalog::INDEPENDENT_RESOURCES` unchanged:

```text
authorization_group, authorization_log, display_setting,
employee_assignment, import_job, lifecycle, policy, user,
workflow_state, resource
```

Ten means **exactly ten**. Two state-less exceptions (`authorization_log`, `display_setting`) have no native codes. Other business Resources may be *temporarily unbound* and use fallback vocabularies; they are not additional independent exceptions. BE010 enforces rejection of invalid/forbidden Lifecycle associations and disabled/non-member Lifecycle states. BE012 neither bypasses nor extends that authority.

`InitialCreateStateNormalizer::withInitial()` is deliberately pure and does not independently enforce the ten-Resource prohibition when the caller supplies a synthetic `$initial`. Restricting this helper would break existing characterization tests and incorrectly combine pure normalization with persisted metadata validation.

## 6. Security, transaction and integration invariants

- The guard must protect **service-level entrypoints** (imports/internal calls), not just HTTP. No `trusted=true`, `skipWorkflow`, or `bypassState` parameters and no fallback that treats a BE010 metadata error as native/unbound.
- `assertNoDirectStateMutation` does **not** itself authorize a state transition; it only rejects disallowed direct writes. All legitimate Lifecycle mutations continue through BE009/BE011 transition executors and their PBAC, target locking, concurrency and audit logic.
- The canonical WriteBoundary service and the adapter introduce **no** `transBegin()`, `FOR UPDATE`, writes, audit or SQL. No new observable transaction isolation guarantees; keep existing upstream transaction scopes and error propagation.
- `WorkflowBusinessStatus::normalize` may be used downstream for native-status validation; it is not a substitute for an `AuthorizationService` decision.
- No controller, route, request envelope, CSRF filter, actor binding, query pagination or error-to-HTTP mapping changes. Existing security tests are inherited; BE011's dedicated **secure-runtime CSRF negative-path verification and independent code review remain separate pending release gates**, not silently declared completed by BE012.
- No cross-request memoization. BE010 per-instance caches remain encapsulated in BE010, and each new factory call creates a fresh resolution engine unless the caller explicitly passes a legacy instance; injected instances may carry their existing per-instance cache state.

## 7. Test strategy and exact new regression inventory (36 cases)

**Baseline: 1,012** Windows PHPUnit tests, **new BE012: 36**, target **1,048 exact tests**. Names below are frozen for source/JUnit inventory and must not be changed or silently supplemented without a revised spec. Keep existing test files unchanged; the only inherited tooling change is the BE011 total-test threshold described in §8.

### Unit/Boundary — 5 cases
`backend/tests/unit/BeReorg012WriteBoundaryBoundaryTest.php`

1. `testApplicationPortDefinesFourCanonicalOperations`
2. `testReadPortContainsOnlyThreeStateLookups`
3. `testApplicationDoesNotImportLegacyServicesOrSql`
4. `testAdapterWrapsBothBe010PortAndLegacyFacade`
5. `testErrorsPreserveLegacyCodesAndContexts`

### Unit/Compatibility — 5 cases
`backend/tests/unit/BeReorg012WriteBoundaryCompatibilityTest.php`

1. `testBoundaryFacadeRetainsTwoStaticSignatures`
2. `testStatusFacadeRetainsNormalizeSignatureAndDefaults`
3. `testInitialNormalizerRetainsConstructorTwoMethodsAndConstant`
4. `testFacadesDelegateWithoutDuplicatedAlgorithms`
5. `testExistingConsumersAndRoutesRemainUnchanged`

### Unit/Factory and adapter — 5 cases
`backend/tests/unit/BeReorg012WriteBoundaryFactoryTest.php`

1. `testFactoryCreatesFreshEngineWithoutSharedCache`
2. `testFactoryPassesProvidedDbToBe010StateResolution`
3. `testFactoryReusesExactInjectedLegacyResolver`
4. `testAdapterDelegatesThreeReadsWithoutMutation`
5. `testFactoryDoesNotReadDatabaseOnConstruction`

### Unit/Pure Initial State — 7 cases
`backend/tests/unit/BeReorg012InitialStateRulesTest.php`

1. `testNativeMissingStatusDefaultsActiveWhenCatalogPermits`
2. `testNativeWithoutActiveDefaultLeavesPayloadUnchanged`
3. `testNativeExplicitStatusIsPreservedVerbatim`
4. `testLifecycleInitialAcceptsArbitraryValidCode`
5. `testMatchingClientHintIsCanonicalizedToInitial`
6. `testMismatchingOrNonStringClientHintIsRejected`
7. `testUnsupportedInitialCodeIsRejectedWithExistingContext`

### Unit/Domain boundary — 7 cases
`backend/tests/unit/BeReorg012WriteBoundaryDomainTest.php`

1. `testUpdateWithoutStatusReturnsBeforeStateLookup`
2. `testSameStatusCaseAndWhitespaceRequiresNoStateLookup`
3. `testBoundDirectStatusMutationThrowsTransitionRequired`
4. `testUnboundDirectStatusMutationRemainsPassThrough`
5. `testUnboundCreateReturnsPayloadUnchanged`
6. `testBoundCreateUsesPersistedInitialAndRejectsOverride`
7. `testStatusNormalizationUsesMemberOrNativeVocabulary`

### Database characterization — 5 cases
`backend/tests/database/BeReorg012WriteBoundaryDatabaseTest.php`

1. `testUnknownResourceOnReachablePathsFailsClosed`
2. `testTenNativeResourcesRemainIndependentAndRejectLinkage`
3. `testBoundInitialStatePersistsWithoutClientOverride`
4. `testInactiveOrNonmemberWorkflowStateIsRejected`
5. `testNativeStatusCodesAndUnboundUpdatesRemainCompatible`

### Existing API HTTP regression — 2 cases
`backend/tests/database/BeReorg012WriteBoundaryHttpTest.php`

1. `testApiCreateUsesConfiguredInitialAndRejectsForgedStatus`
2. `testApiUpdateRejectsDirectStatusMutationWithoutAudit`

Exercise existing Foundation or Employee Structure POST/PATCH routes through in-process CodeIgniter feature tests with real `erp_test` fixtures and authorized/denied actors as appropriate; do not change API endpoints to make tests pass. Assert persisted DB row and audit counts before/after rejected mutations. A `SecurityException` from an **enabled** CSRF filter is a valid secure-runtime rejection, not a controller response; do not substitute a disabled-filter test and claim secure-runtime CSRF coverage. Avoid adding a redundant concurrency test to BE012 because no transactional write or lock code is introduced; inherit BE009/BE011 real DB lock gates.

**Test fixture:** `backend/tests/_support/Workflow/BeReorg012WriteBoundaryFixture.php` implements `WorkflowStateLookupPort`, with call counters to prove no-query early returns and configurable results/errors. No code in production depends on this fixture.

## 8. Guarded runner and inherited gates

Create `backend/tools/test-workflow-write-boundary.php`, requiring the sole flag `--confirm-test-db`, and retaining `--help` and invalid/no-flag refusal (`exit 0` for help, `2` for missing/invalid confirmation). It must call the inherited **BE011 runner once**, not independently re-run the suite or PHPStan; protect the existing disposable **`127.0.0.1:3306/erp_test`**, prefix **`dinktech_`**, and refuse operation without explicit confirmation. No parallel DB tests and no unconditional destructive operations.

The BE011 runner currently checks **exactly 1,012** JUnit testcases. Permit only this tooling-only edit to `backend/tools/test-business-workflow-actions.php`: change the total-case predicate from **exactly 1,012** to **at least 1,012**; keep all 34 BE011 exact test identities, no skip/failure/error and inherited BE010/BE009/BE008/BE007/PBAC/Foundation gates unchanged. Do not relax any coverage checks, security assertions or exit handling.

The BE012 runner must check a **fresh JUnit file containing exactly 1,048 distinct, named testcases**, none failed/errored/skipped; verify **all 36 new exact method names** in seven classes, along with inherited gates (34 BE011, 30 BE010, 32 BE009, 26 BE008, 24 BE007, PBAC 91 and Foundation 17 checks). It must reject missing/stale/malformed JUnit, wrong methods/counts and concurrent/incorrect DB target. Ensure older runners' log/JUnit cleanup cannot cause a false success or drop BE012 runner output. Keep a durable log at the project's `test-result.log` and JUnit under `backend/build/logs/` (e.g. `be-reorg-012.xml`). The runner may report overall exit `1` when PHPUnit PASSES but inherited PHPStan remains exit `1`; **do not mask it as clean**.

PHPStan acceptance is **identity parity**, not just an aggregate count: compare file, line, diagnostic ID and message to BE011's 120-diagnostic baseline. Existing diagnostics inside `BusinessWorkflowWriteBoundary.php` may move or disappear after extraction; both kinds require documented attribution. Reject genuinely new diagnostics even if the total is unchanged or lower. Do not edit `phpstan.neon`, suppressions or ignore baselines to make a count pass.

## 9. Execution constraints and delivery contract

- **TDD / RED–GREEN** at each task; characterize the current behavior before removing code from the three legacy classes. Verify a failing test on the candidate when the port/service is absent. Do not mutate inherited tests to sidestep failures.
- **Worktree:** create a new isolated BE012 working tree based on the real Windows checkpoint once verified. Do not edit/clean the user's working tree or claim a local synthetic commit is the Windows SHA.
- **Source limits:** seven new production files + three façade modifications; one fixture, seven BE012 test classes, one new runner and one inherited runner's total-check edit. Do not touch routes, controllers, migrations, BE009–BE011 production modules, old tests, frontend or unrelated docs.
- **Acceptance:** PHP `-l` each changed PHP file; `git diff --check`; unit/domain cases; real MySQL DB + HTTP cases; 1,048-test full suite no skips; runner guard; PHPStan diff identity; independent code review; manifest/hash; patch apply/reverse on a clean BE011 Windows checkpoint snapshot; actual Windows runtime evidence.
- **Release status:** `CANDIDATE` until the Windows full suite, static parity and independent review gates succeed; `ACCEPTED` only after an authoritative Windows Git checkpoint and any inherited pending security gates are resolved or explicitly documented as exceptions by the owner. No invented green tests or blanket claim that PHPStan passes.
- **Patch format:** incremental from BE011 + FIX01 checkpoint. A standalone source overlay may accompany a patch, but it is not permission to overwrite unrelated Windows files.

## 10. Non-goals and intentional exclusions

Do not revise what is a Lifecycle-bound Resource; do not broaden the list of independent Resources; do not add new PBAC decisions or grant status changes; do not add HTTP or Web UI functions; do not extend `InitialCreateStateNormalizer::withInitial()` into a DB resolver; do not normalize caller-supplied native allowlists automatically; do not replace `strlen()` with multibyte length; do not tighten the write boundary for missing/unchanged status; do not rework BE011's action/transition execution or its CSRF test as a hidden BE012 change. Each is a separate behavior change requiring a new specification.

## 11. Review risks and required characterization

| Risk | Mitigation / test reference |
|---|---|
| `create()` gets native `ACTIVE` injected when it previously returned unchanged | `testUnboundCreateReturnsPayloadUnchanged` |
| Early no-status / same-status path erroneously queries Resource or throws for unknown type | `testUpdateWithoutStatusReturnsBeforeStateLookup`, `testSameStatusCaseAndWhitespaceRequiresNoStateLookup` |
| Legacy injected `ResourceWorkflowStateResolver` ignored, replaced or read through another DB connection | `testFactoryReusesExactInjectedLegacyResolver`, `testFactoryPassesProvidedDbToBe010StateResolution` |
| Native `normalize()` silently uses central catalog instead of caller list | `testStatusNormalizationUsesMemberOrNativeVocabulary` |
| Invalid Lifecycle assignment/State allowed through because reader exception is swallowed | `testUnknownResourceOnReachablePathsFailsClosed`, `testInactiveOrNonmemberWorkflowStateIsRejected` |
| Context of `WF_INITIAL_STATE_INVALID` or `WF_TRANSITION_REQUIRED` changes | `testMismatchingOrNonStringClientHintIsRejected`, `testBoundDirectStatusMutationThrowsTransitionRequired` |
| DB query on pure `withInitial` or factory constructor | `testFactoryDoesNotReadDatabaseOnConstruction`, `testNativeMissingStatusDefaultsActiveWhenCatalogPermits` |
| Hidden HTTP mutation or audit despite write-guard rejection | `testApiUpdateRejectsDirectStatusMutationWithoutAudit` |
| Old guarded BE011 suite stops accepting extra BE012 tests | §8 total-check only, preserve 34 inherited identities |

## 12. Acceptance decision gate

This document is a **design specification for review**, not approval to write code. On owner approval, author a detailed `BE-REORG-012` Implementation Plan (interfaces, RED–GREEN steps, exact file paths, tests and safe Windows commands). Request separate approval of that plan and execution mode before modifying product code. The historical default is Native execution with independent code review, but no method is implicitly authorized by this scope approval alone.

## 13. Source anchors and provenance

Anchors inspected in the reconstructed BE011 source tree (`backend/` paths relative to repo root):

- `app/Services/BusinessWorkflowWriteBoundary.php:14-54` — both static methods and early return order.
- `app/Services/WorkflowBusinessStatus.php:15-43` — dynamic error/status shape, native allowlist behavior, 50-byte limit.
- `app/Services/InitialCreateStateNormalizer.php:14-67` — injectable constructor, pure static rules, exact exceptions.
- `app/Services/ResourceWorkflowStateResolver.php:1-132` — BE010 legacy façade, nullable DB and 11-method public surface.
- `app/Modules/Workflow/StateResolution/Application/Ports/ResourceStateResolutionPort.php` — BE010 resolution interface.
- `app/Bootstrap/WorkflowStateResolutionFactory.php` — stateless BE010 constructor.
- `app/Modules/Workflow/StateResolution/Contracts/ResourceStateResolutionErrors.php` — error vocabulary.
- `app/Services/ResourceNativeStateCatalog.php` — 10 independent Resource list and native vocabularies.
- `app/Config/Routes.php:24-44` — existing POST/PATCH Foundation and Employee Structure endpoints.
- `tests/unit/P1WorkflowCreateInitialStateTest.php`, `tests/unit/P1WorkflowCreateLifecycleIntegrationTest.php`, `tests/unit/BusinessWorkflowTenNativeBoundaryTest.php` — inherited native/initial/DB parity.
- `tools/test-business-workflow-actions.php` — inherited 1,012 JUnit exact gate.

**Source qualification:** local review is based on a reconstructed candidate tree and the separately provided Windows PHPUnit/Git logs; the **real** Windows checkpoint `b29e7179dc1f1bd3dfcdcf25742734c86bc61470` was reported by the user. This specification does not claim a fresh Windows source checkout, a fresh 1,048-test execution, or an independent reviewer.
