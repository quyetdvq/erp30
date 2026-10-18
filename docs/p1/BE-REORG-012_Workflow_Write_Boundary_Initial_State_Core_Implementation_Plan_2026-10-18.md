# BE-REORG-012 Workflow Write Boundary & Initial State Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Date:** 2026-10-18
**Status:** IMPLEMENTATION PLAN — OWNER REVIEW REQUIRED; NOT AUTHORIZATION FOR PRODUCTION CHANGES.
**Goal:** Extract the behavior of the three existing Workflow write/initial-state services into a canonical module while preserving their public signatures, early-return order, error contexts, native fallback and existing CRUD/HTTP behavior.
**Architecture:** A four-operation `WorkflowWriteBoundaryService` implements a narrow Application port. It uses a three-read `WorkflowStateLookupPort` bridged to BE010 by an Infrastructure adapter, and delegates the DB-free initial-state algorithm to `InitialStateRules`. A stateless Bootstrap factory composes the graph; three existing `App\Services` classes become compatibility façades without changing callers.
**Tech Stack:** PHP 8.3.30, CodeIgniter 4 + Shield, MySQL/MariaDB InnoDB, PHPUnit 10.5.65, PHPStan, Windows PowerShell, Git.
**Spec:** `docs/p1/BE-REORG-012_Workflow_Write_Boundary_Initial_State_Core_Spec_2026-10-18.md` (owner selected Option A, then authorized preparation of this Implementation Plan; the specification's original `AWAITING OWNER APPROVAL` heading is historical).
**Source authority:** Verify the user's Windows checkpoint `b29e7179dc1f1bd3dfcdcf25742734c86bc61470` before any production task. Local ZIP/reconstructed Git commits are **not** a substitute for that SHA.

## Global Constraints

- **Baseline:** 1,012/1,012 Windows PHPUnit tests, 9,960 assertions, no failures/errors/skips; 120 **inherited** PHPStan diagnostics (raw exit `1`) with zero new diagnostic identities on BE011. New BE012 inventory: **exactly 36 tests across seven classes**, expected total **1,048**. Do not claim PHPStan clean or invent a new assertion count.
- **Source allowlist:** exactly **7 new production files + 3 modified legacy façades** in the File Map. No other production edits, no new SQL/persistence, controller, route, migration, schema, BE009–BE011 canonical module, business CRUD, PBAC, frontend, OpenAPI or PHPStan-config changes.
- **Test/tool allowlist:** create seven named BE012 test classes, one `BeReorg012WriteBoundaryFixture.php`, one BE012 guarded runner, and modify **only the BE011 runner JUnit-total predicate** (`!== 1012` to `< 1012`). Preserve all inherited exact-method checks and fail/skip/exit checks.
- **Legacy API:** both `BusinessWorkflowWriteBoundary` methods remain public static with an explicit required `ConnectionInterface $db`; `WorkflowBusinessStatus::normalize(string,mixed,array,string,?ConnectionInterface=null):string` keeps its parameter names/order; `InitialCreateStateNormalizer::__construct(?ResourceWorkflowStateResolver $states=null)` and its instance `normalize` and static `withInitial` stay unchanged. Its sole public constant remains `ERROR_UNSUPPORTED_INITIAL`.
- **Error fidelity:** keep `App\Exceptions\DomainException`, exact machine codes and `getContext()` values. `WF_INITIAL_STATE_INVALID`, `WF_TRANSITION_REQUIRED` and `WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE` are not interchangeable. Preserve error-precedence and the original argument value, including wrong types.
- **No implicit native default at the write boundary:** `BusinessWorkflowWriteBoundary::create` returns an unbound Resource's incoming array *unchanged*. Only the separate `withInitial($resource,$payload,null)` pure operation may add `ACTIVE` when status is absent and the existing native catalog contains `ACTIVE`.
- **Exact update early returns:** missing `changes.status` and normalized string equality skip Resource lookup entirely. Comparison trims **incoming status only**, uppercases both; it must not trim `current.status` or turn non-string native updates into a new error.
- **Status rules:** validate type, empty and **byte length `strlen` <= 50** before a lifecycle lookup; bound Resource uses BE010 active member codes only; unbound Resource uses **exact caller-supplied** `$nativeCodes` (strict `in_array`, no sorting or normalization). Unknown/invalid resource data fails closed when lookup is reached.
- **Pure initial rule:** `withInitial` is DB-free; for non-null initial require `/\A[A-Z][A-Z0-9_:-]{0,49}\z/D`; compare matching client hint as `strtoupper(trim(...))`, then store the supplied uppercase initial verbatim. Ten independent `ResourceNativeStateCatalog::INDEPENDENT_RESOURCES` remain unchanged, including two with no native status codes.
- **Composition:** the factory is stateless; each default call builds a fresh BE010 resolver engine via `WorkflowStateResolutionFactory::create(db:$db)`; an injected legacy `ResourceWorkflowStateResolver` is wrapped **as the exact same instance**, with no default resolution engine built. Factory construction and static pure rules perform no metadata read.
- **DB safety:** only disposable `127.0.0.1:3306/erp_test` with `dinktech_` prefix; `DatabaseTestTrait` can refresh tables. Require `RuntimeGuard::assertConfiguration()` before fixtures/SQL; do not run DB suites in parallel, use a production DB or launch a guarded full runner without explicit `--confirm-test-db`.
- **Scope of security coverage:** no new transaction, locking, transition, audit or authorization mechanism; inherit BE009–BE011 InnoDB, PBAC and CSRF coverage. BE011's outstanding independent review and **secure-runtime missing-CSRF test** are separate gates, not deemed completed by BE012.
- **Release:** independent reviewer with no unresolved Critical/Important findings, exact PHPStan identity comparison (file, line, diagnostic ID and message, with documented moved findings), `git diff --check`, tested patch apply/reverse, real Windows runtime evidence and a verified Windows Git checkpoint are required before `ACCEPTED`.

## Review Focus

Five high-risk failure/input classes that ordinary happy-path parity tests could miss; each is pinned to an existing **frozen** BE012 method (do not create extra JUnit cases):

1. **Unknown Resource on an early return vs. a real status change:** `changes` without `status` or with unchanged status must not perform metadata queries, even for a nonexistent type; a real change or create must propagate BE010's missing-resource error. Tasks 1 and 4: `testUnknownResourceOnReachablePathsFailsClosed`, `testUpdateWithoutStatusReturnsBeforeStateLookup`, `testSameStatusCaseAndWhitespaceRequiresNoStateLookup`.
2. **Native update with a non-string `status` and caller-specific vocabulary:** the boundary permits an unbound Resource to reach downstream validation, while normalization retains its supplied error code and raw wrong-type context; do not silently insert ACTIVE or replace the caller's allowlist. Task 4: `testUnboundDirectStatusMutationRemainsPassThrough`, `testStatusNormalizationUsesMemberOrNativeVocabulary`.
3. **Current status has trailing spaces:** unlike the incoming value, `current.status` is not trimmed. Do not short-circuit a true bound mutation when only current has extra whitespace. Task 4: `testSameStatusCaseAndWhitespaceRequiresNoStateLookup`, `testBoundDirectStatusMutationThrowsTransitionRequired`.
4. **Malformed initial state at 50-byte boundary, synthetic valid DRAFT and explicit null native status:** accept exactly 50-byte canonical codes, reject 51 bytes/lowercase, accept synthetic `DRAFT` without querying Resource, preserve explicit `status => null` with null initial. Task 2: `testLifecycleInitialAcceptsArbitraryValidCode`, `testUnsupportedInitialCodeIsRejectedWithExistingContext`, `testNativeExplicitStatusIsPreservedVerbatim`.
5. **Error priority and forgery at real CRUD boundaries:** syntactically invalid status must fail before Resource lookup; a forged create hint and direct PATCH must leave row count, stored status and audit unchanged, including a read-only actor's edit denial. Tasks 1 and 4: `testApiCreateUsesConfiguredInitialAndRejectsForgedStatus`, `testApiUpdateRejectsDirectStatusMutationWithoutAudit`, `testStatusNormalizationUsesMemberOrNativeVocabulary`.

---

## File Map and Frozen Interfaces

### Production — exactly 10 paths

| Action | Exact repo-relative path | Responsibility |
|---|---|---|
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Application/Ports/WorkflowWriteBoundaryPort.php` | Four canonical use cases |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Application/Ports/WorkflowStateLookupPort.php` | Three read-only BE010 lookups |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Application/WorkflowWriteBoundaryService.php` | Write guards and status normalization |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Application/InitialStateRules.php` | DB-free initial state rule |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Contracts/WorkflowWriteBoundaryErrors.php` | Three inherited constant aliases |
| CREATE | `backend/app/Modules/Workflow/WriteBoundary/Infrastructure/Adapters/ExistingStateResolutionLookupAdapter.php` | Legacy/BE010 read bridge; no SQL |
| CREATE | `backend/app/Bootstrap/WorkflowWriteBoundaryFactory.php` | Stateless composition |
| MODIFY | `backend/app/Services/BusinessWorkflowWriteBoundary.php` | Two static explicit delegates |
| MODIFY | `backend/app/Services/WorkflowBusinessStatus.php` | One static explicit delegate |
| MODIFY | `backend/app/Services/InitialCreateStateNormalizer.php` | Constructor, instance normalize, pure static delegate |

**Canonical signatures** (all classes in `App\Modules\Workflow\WriteBoundary`):

```php
// Application/Ports/WorkflowWriteBoundaryPort.php
/** @param array<string,mixed> $data @return array<string,mixed> */
public function create(string $resource, array $data): array;
/** @param array<string,mixed> $current @param array<string,mixed> $changes */
public function assertNoDirectStateMutation(string $resource, array $current, array $changes): void;
/** @param list<string> $nativeCodes */
public function normalizeStatus(string $type, mixed $value, array $nativeCodes, string $errorCode): string;
/** @param array<string,mixed> $payload @return array<string,mixed> */
public function normalizeInitial(string $resource, array $payload): array;

// Application/Ports/WorkflowStateLookupPort.php
public function lifecycleId(string $type): ?int;
/** @return list<string> */
public function allowedPolicyStateCodes(string $type): array;
public function initialCode(string $type): ?string;

// Application/WorkflowWriteBoundaryService.php
public function __construct(WorkflowStateLookupPort $states);
// Implements the exact four WorkflowWriteBoundaryPort signatures above.

// Application/InitialStateRules.php (final; private constructor)
public static function withInitial(string $resource, array $payload, ?string $initial): array;

// Infrastructure/Adapters/ExistingStateResolutionLookupAdapter.php (final)
public function __construct(ResourceStateResolutionPort|ResourceWorkflowStateResolver $states);
// Implements exactly the three WorkflowStateLookupPort methods; forwards return/errors verbatim.

// Bootstrap/WorkflowWriteBoundaryFactory.php (final; private constructor)
/** @param ConnectionInterface<mixed,mixed>|null $db */
public static function create(
    ?ConnectionInterface $db = null,
    ?ResourceWorkflowStateResolver $states = null,
): WorkflowWriteBoundaryPort;
```

`WorkflowWriteBoundaryErrors` (final constants-only, private constructor) exposes **exactly**:

```php
public const ERROR_UNSUPPORTED_INITIAL = 'WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE';
public const ERROR_INITIAL_STATE_INVALID = ResourceStateResolutionErrors::ERROR_INITIAL_STATE_INVALID;
public const ERROR_TRANSITION_REQUIRED = ResourceStateResolutionErrors::ERROR_TRANSITION_REQUIRED;
```

All BE010 `ResourceStateResolutionPort` methods other than the three read lookups remain outside this module. The BE010 legacy façade is a **final** concrete `ResourceWorkflowStateResolver` and does not implement that port: do not try to make it implement the entire BE010 interface or change BE010 production. `InitialStateRules` may import **only** the existing pure `ResourceNativeStateCatalog` from `App\Services`; canonical `WorkflowWriteBoundaryService` must not import that legacy façade, any DB type, concrete repository, query builder, controller, lifecycle executor or authorization service. Factory may use BE010 composition as specified.

### Test and tooling — exactly 10 paths

| Action | Exact path | Frozen test inventory |
|---|---|---|
| CREATE | `backend/tests/unit/BeReorg012WriteBoundaryBoundaryTest.php` | 5 methods |
| CREATE | `backend/tests/unit/BeReorg012WriteBoundaryCompatibilityTest.php` | 5 methods |
| CREATE | `backend/tests/unit/BeReorg012WriteBoundaryFactoryTest.php` | 5 methods |
| CREATE | `backend/tests/unit/BeReorg012InitialStateRulesTest.php` | 7 methods |
| CREATE | `backend/tests/unit/BeReorg012WriteBoundaryDomainTest.php` | 7 methods |
| CREATE | `backend/tests/database/BeReorg012WriteBoundaryDatabaseTest.php` | 5 methods |
| CREATE | `backend/tests/database/BeReorg012WriteBoundaryHttpTest.php` | 2 methods |
| CREATE | `backend/tests/_support/Workflow/BeReorg012WriteBoundaryFixture.php` | Test-only 3-read port fake + counters/errors |
| CREATE | `backend/tools/test-workflow-write-boundary.php` | BE012 guarded runner |
| MODIFY | `backend/tools/test-business-workflow-actions.php` | One BE011 total-case predicate only |

**Total: 36 PHPUnit tests, seven test classes; target JUnit = 1,048.** Each named test remains **one JUnit testcase**: loop over variations within one test; no data providers or repeated dataset names that alter exact JUnit cardinality. The fixture is NOT a PHPUnit test class.

## Working-tree and TDD discipline

1. Start from verified Windows checkpoint `b29e7179dc1f1bd3dfcdcf25742734c86bc61470`, dedicated branch/worktree `feature/be-reorg-012-write-boundary` or a user-approved equivalent. The user's Windows `main` and other unrelated modified/untracked files must remain untouched. Check `git rev-parse HEAD`, `git status --short` before any modifications; never use `git add .`, `git reset --hard`, or `git clean` on a shared worktree. A synthetic local checkpoint has no authority over the Windows commit.
2. Run inherited baseline gate once in an isolated, confirmed **disposable test DB** before production edits; record results and existing PHPStan. `php tools/test-business-workflow-actions.php --confirm-test-db` has raw exit `1` when PHPUnit passes but inherited PHPStan fails; inspect the PHPUnit/guard/JUnit evidence rather than requiring exit 0. If baseline behavior differs or DB is not disposable, stop and reconcile before editing.
3. Use test-first RED→GREEN for each new canonical API and guard. **Task 1 explicitly characterizes existing legacy behavior first, so its tests may pass before any production refactor**; this is expected and is not a fabricated RED. Subsequent canonical-unit tests must fail because their new port/service/rule is absent, then pass after the code is written. Preserve original legacy source unchanged until Task 5.
4. At every task, stage **only listed paths** and commit on the isolated feature branch. Capture failed and successful test command/output in the task ledger. A local candidate commit is not a Windows Git checkpoint and not a merge/push approval.

---

### Task 1: Freeze existing database and HTTP behavior before extracting code

**Files:** Create `backend/tests/database/BeReorg012WriteBoundaryDatabaseTest.php` (5); create `backend/tests/database/BeReorg012WriteBoundaryHttpTest.php` (2). Reuse inherited `Tests\Support\Foundation\RuntimeFixture`, `Tests\Support\Workflow\BeReorg011BusinessActionsFixture`, `RuntimeGuard`, `DatabaseTestTrait`, `FeatureTestTrait` and `AuthenticationTesting`; do not touch their source.

**Interfaces:** Consumes existing **legacy** static/instance methods in §2 of the Spec (no canonical class needed yet). Produces seven executable behavioral snapshots for all later tasks. For live DB, use the same guarded `erp_test` setup as `BeReorg011BusinessActionsHttpTest` and `BeReorg010StateResolutionDatabaseTest`. Use unique fixture codes, save before/after row/audit counts and scoped rollback/cleanup via the inherited test trait.

- [ ] **Step 1: Write five named database characterization tests before production changes.** Implement each method with the following observable assertions:
  - `testUnknownResourceOnReachablePathsFailsClosed`: `BusinessWorkflowWriteBoundary::create('missing_resource', ['x'=>1], $db)` and true-change `assertNoDirectStateMutation` propagate the existing BE010 missing-resource DomainException; `WorkflowBusinessStatus::normalize('missing_resource','ACTIVE',['ACTIVE'],'STATUS_INVALID',$db)` also fails closed after syntactic validation. Assert exact error codes/contexts from the **current** legacy result, not a guessed replacement; no persisted change.
  - `testTenNativeResourcesRemainIndependentAndRejectLinkage`: assert `ResourceNativeStateCatalog::INDEPENDENT_RESOURCES` is exactly the Spec's ten keys; `authorization_log`, `display_setting` have no native codes; all ten return null Lifecycle in authoritative resolver. In an isolated disposable fixture, attempt to attach a forbidden Lifecycle to `policy` and assert existing BE010 `WF_LIFECYCLE_SELF_REFERENCE` (as `P1WorkflowCreateLifecycleIntegrationTest` does) rather than silently treating it as native; restore DB state via `DatabaseTestTrait`.
  - `testBoundInitialStatePersistsWithoutClientOverride`: use a bound `province` through existing `LocationService`/API010 service; create with no status and assert row uses BE010 configured initial; with a forged different hint assert `WF_INITIAL_STATE_INVALID` context and unchanged row/audit counts.
  - `testInactiveOrNonmemberWorkflowStateIsRejected`: insert or configure an inactive/nonmember state only under the disposable fixture; `WorkflowBusinessStatus::normalize` rejects it despite caller `$nativeCodes`, and the resolver's invalid metadata error is never turned into native allowance. Reassert no row/audit mutations.
  - `testNativeStatusCodesAndUnboundUpdatesRemainCompatible`: select an independent or temporarily unbound Resource; direct-change guard permits it; native normalization accepts only the passed list and returns uppercased trim; unknown native values throw supplied code; confirm unchanged persisted row until an authorized existing business service performs its normal validation/update.
- [ ] **Step 2: Write the two existing-route HTTP regression tests.** Follow `ApiExceptionHandler`/`TestResponse` and Shield fixture patterns of `BeReorg011BusinessActionsHttpTest`; use existing URLs only:
  - `testApiCreateUsesConfiguredInitialAndRejectsForgedStatus`: authenticated `POST /api/v1/provinces` with valid code/name and *no status* returns existing success envelope/HTTP `201`, persists configured initial; a second POST with mismatching `status` returns the current `WF_INITIAL_STATE_INVALID` HTTP contract, does not add a row/audit; a read-only actor cannot obtain create authority. Keep request ID and CSRF token in normal authorized flows.
  - `testApiUpdateRejectsDirectStatusMutationWithoutAudit`: authenticated `PATCH /api/v1/provinces/{id}` with status change returns existing transition-required contract (or PBAC deny for an actor without edit), and keeps stored row/status and audit count byte-equivalent. **This API already has its own status-field guard**; this test confirms HTTP parity but does *not* alone prove the service-level guard. Secure-runtime missing-CSRF negative path is explicitly out of scope.
- [ ] **Step 3: Run characterization against the old source (before creating canonical production files).**

  ```powershell
  Set-Location D:\XAMPP\htdocs\erp\backend
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg012WriteBoundaryDatabaseTest.php tests/database/BeReorg012WriteBoundaryHttpTest.php
  ```

  **Expected:** exactly **7/7 PASS** under the guarded disposable test DB. These are behavioral characterization tests, so a GREEN run *before* extraction is desirable. If an asserted expected HTTP code disagrees with the current route, fix the **test to the observed legacy contract** and document that evidence; never edit production to satisfy an invented expectation. Do not proceed while these tests fail.
- [ ] **Step 4: Commit only the two characterization test files.** `git add backend/tests/database/BeReorg012WriteBoundaryDatabaseTest.php backend/tests/database/BeReorg012WriteBoundaryHttpTest.php`; `git commit -m "test(be012): freeze workflow write boundary DB and API parity"`. Record 7/7 and exact command in the ledger.

### Task 2: Extract the pure initial-state rule and shared error vocabulary

**Files:** Create `backend/app/Modules/Workflow/WriteBoundary/Application/InitialStateRules.php`, `backend/app/Modules/Workflow/WriteBoundary/Contracts/WorkflowWriteBoundaryErrors.php`; create `backend/tests/unit/BeReorg012InitialStateRulesTest.php` (7). Do not modify any legacy façade yet.

**Interfaces:** Produces `InitialStateRules::withInitial(string,array,?string):array` and the **three** frozen constants in `WorkflowWriteBoundaryErrors`. Consumes `ResourceNativeStateCatalog::codes($resource)` (pure only), `ResourceStateResolutionErrors` (constant aliases), and `DomainException`.

- [ ] **Step 1: Write seven failing pure-rule tests** (no DB setup, `CIUnitTestCase`):
  - `testNativeMissingStatusDefaultsActiveWhenCatalogPermits`: input `('province', ['code'=>'P42'], null)` returns the same array plus `status=>'ACTIVE'`; keys/values otherwise unchanged.
  - `testNativeWithoutActiveDefaultLeavesPayloadUnchanged`: `import_job` (vocabulary lacks `ACTIVE`) and `display_setting` (stateless) with absent status return the exact original payload.
  - `testNativeExplicitStatusIsPreservedVerbatim`: `('province', ['status'=>'closed'], null)` remains `closed`, `['status'=>null]` stays null; do not normalize the explicit hint.
  - `testLifecycleInitialAcceptsArbitraryValidCode`: `('province',['name'=>'X'],'DRAFT')` yields `status=>'DRAFT'`, and an exactly 50-byte uppercase code is accepted; do not require native catalog membership.
  - `testMatchingClientHintIsCanonicalizedToInitial`: `['status'=>' inactive ']`, initial `INACTIVE`, returns exactly `INACTIVE`, with other keys intact.
  - `testMismatchingOrNonStringClientHintIsRejected`: mismatch `['status'=>'ACTIVE']` vs `INACTIVE` and non-string/null client hints throw `WF_INITIAL_STATE_INVALID`; assert `getContext()===['resource'=>'province','initial_status'=>'INACTIVE']`.
  - `testUnsupportedInitialCodeIsRejectedWithExistingContext`: lowercase `draft`, bad leading digit and 51-byte uppercase code throw `WF_INITIAL_STATE_UNSUPPORTED_FOR_RESOURCE`; assert exact context `['resource'=>'province','initial_status'=>raw initial]` and type `DomainException`.
- [ ] **Step 2: Execute the pure test RED.** `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012InitialStateRulesTest.php`. **Expected:** fails from absent `InitialStateRules` (not a syntax/fixture error); no DB used.
- [ ] **Step 3: Implement the minimal pure algorithm and exact error constants.** `final InitialStateRules`, private constructor, one static method. Copy the old `InitialCreateStateNormalizer::withInitial` branching/order exactly; do not call BE010, authorize, query metadata or upper-case the supplied initial. `WorkflowWriteBoundaryErrors` must declare only the three specified public constants, with `ResourceStateResolutionErrors` aliases, and private constructor.
- [ ] **Step 4: Run GREEN and inherited pure parity.**

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012InitialStateRulesTest.php
  php vendor/bin/phpunit --no-coverage tests/unit/P1WorkflowCreateInitialStateTest.php
  ```

  **Expected:** **7/7 BE012** PASS, inherited pure tests PASS. Compare the output of old `InitialCreateStateNormalizer::withInitial()` to the new pure rule for each case while old code still exists. Commit only 3 task files with `git commit -m "refactor(be012): extract pure initial state rules and codes"`.

### Task 3: Introduce the read-only lookup port, BE010 adapter and test fixture

**Files:** Create `backend/app/Modules/Workflow/WriteBoundary/Application/Ports/WorkflowStateLookupPort.php`, `backend/app/Modules/Workflow/WriteBoundary/Infrastructure/Adapters/ExistingStateResolutionLookupAdapter.php`, `backend/tests/_support/Workflow/BeReorg012WriteBoundaryFixture.php`; create `backend/tests/unit/BeReorg012WriteBoundaryBoundaryTest.php` initially with **two** methods (Task 4 adds the remaining three).

**Interfaces:** `WorkflowStateLookupPort` exposes exactly `lifecycleId(string):?int`, `allowedPolicyStateCodes(string):array`, `initialCode(string):?string`. The adapter's one constructor accepts `ResourceStateResolutionPort|ResourceWorkflowStateResolver`, forwards those three methods without catch/normalization/cache and implements the narrow port. Test-only fixture implements that three-read port with configurable `lifecycleId`, active-code/initial maps, invocation counters, and a configurable `DomainException` from any read.

- [ ] **Step 1: Write the two boundary/adapter tests first:**
  - `testReadPortContainsOnlyThreeStateLookups`: use Reflection to assert the interface declares **only** the three methods with exact args/nullable return types; assert there is no write/authorize method.
  - `testAdapterWrapsBothBe010PortAndLegacyFacade`: use `WorkflowStateResolutionFactory::create()` and a fresh `ResourceWorkflowStateResolver()` without executing DB reads; construct adapters for both, assert `instanceof WorkflowStateLookupPort` and exact source-object identity via Reflection; verify the constructor's union type and no constructor-side query. The other read-forwarding assertions live in Task 5's `testAdapterDelegatesThreeReadsWithoutMutation`.
- [ ] **Step 2: Run RED:** `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012WriteBoundaryBoundaryTest.php`. **Expected:** missing read interface/adapter, not unsupported DB environment.
- [ ] **Step 3: Create the narrow port, adapter and fixture.** The adapter only forwards `lifecycleId`, `allowedPolicyStateCodes`, `initialCode` to the injected object. It must never make a default BE010 engine, call lifecycle-mutating operations, catch `DomainException` or cache results. Fixture records each call as a method/resource pair; errors are injected per method/resource. Keep fixture under `Tests\Support\Workflow`, never production.
- [ ] **Step 4: GREEN:** rerun the same two-method Boundary file and Task 2 pure unit file; **2/2** boundary methods PASS and **7/7** pure methods PASS. Run `php -l` on the three new PHP support/production files. Commit only these four paths: `git commit -m "refactor(be012): bridge BE010 state reads behind narrow port"`.

### Task 4: Implement the four canonical write-boundary use cases

**Files:** Create `backend/app/Modules/Workflow/WriteBoundary/Application/Ports/WorkflowWriteBoundaryPort.php`, `backend/app/Modules/Workflow/WriteBoundary/Application/WorkflowWriteBoundaryService.php`, `backend/tests/unit/BeReorg012WriteBoundaryDomainTest.php` (7); **complete** `backend/tests/unit/BeReorg012WriteBoundaryBoundaryTest.php` (add its other 3 frozen methods). **No legacy façade modifications.**

**Interfaces:** `WorkflowWriteBoundaryService::__construct(WorkflowStateLookupPort $states)` implements `WorkflowWriteBoundaryPort` exactly as defined above. It consumes the Task 3 read port, Task 2 `InitialStateRules` and `WorkflowWriteBoundaryErrors`; its four method names/signatures are `create`, `assertNoDirectStateMutation`, `normalizeStatus`, `normalizeInitial`. It never constructs or imports a concrete BE010 resolver, DB, repository or authorization service.

- [ ] **Step 1: Write seven canonical Domain tests against the Task 3 counter fixture:**
  - `testUpdateWithoutStatusReturnsBeforeStateLookup`: with nonexistent resource and `changes=['name'=>'X']`, no exception; call counter remains empty.
  - `testSameStatusCaseAndWhitespaceRequiresNoStateLookup`: `current.status='active'`, incoming `' ACTIVE '` short-circuits with zero reads; **but** current `'ACTIVE '` (with trailing space), incoming `'ACTIVE'`, triggers a bound lookup and throws `WF_TRANSITION_REQUIRED`, proving only incoming value is trimmed.
  - `testBoundDirectStatusMutationThrowsTransitionRequired`: fixture returns Lifecycle ID for `province`, a true change causes exact `WF_TRANSITION_REQUIRED` with `['resource'=>'province']`; assert precisely one `lifecycleId` call and no `initialCode` or state-list read.
  - `testUnboundDirectStatusMutationRemainsPassThrough`: fixture returns null Lifecycle, true change returns void/no error, even for a non-string new value; no authorization/write methods are called.
  - `testUnboundCreateReturnsPayloadUnchanged`: fixture returns null Lifecycle and `['name'=>'X']` yields the exact input without ACTIVE; assert `initialCode` never called.
  - `testBoundCreateUsesPersistedInitialAndRejectsOverride`: fixture returns bound Lifecycle and `initialCode='INACTIVE'`, absent status gains `INACTIVE`; matching client hint normalized; forged `ACTIVE` or null hint produces `WF_INITIAL_STATE_INVALID` and exact context; query order is `lifecycleId` then `initialCode`.
  - `testStatusNormalizationUsesMemberOrNativeVocabulary`: bound uses only `['DRAFT']` from active BE010 list, ignores supplied native `['ACTIVE']`; unbound uses exact caller native list and strict membership. Non-string input `0`, empty/space string and 51-byte code raise supplied code with raw or normalized `['status'=>...]` before any lookup; unknown Resource on syntactically valid code propagates fixture metadata error instead of native fallback.
- [ ] **Step 2: Add the final three Boundary tests** to the file started in Task 3 (after completion it must have exactly five):
  - `testApplicationPortDefinesFourCanonicalOperations`: Reflection exact four names, argument count/order/types and returns, service implements port.
  - `testApplicationDoesNotImportLegacyServicesOrSql`: inspect canonical service dependency/constructor and imports, forbid CodeIgniter DB, `App\Services` workflow façade, concrete repositories/SQL and direct transition/audit/authorization calls; **permit** the native catalog import in Task 2 pure `InitialStateRules` only.
  - `testErrorsPreserveLegacyCodesAndContexts`: assert three error aliases equal existing BE010 values and legacy unsupported-initial code; behavioral thrown `DomainException` contexts from the service and pure rule match the Spec.
- [ ] **Step 3: Run targeted RED before creating the canonical service/port:**

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012WriteBoundaryDomainTest.php tests/unit/BeReorg012WriteBoundaryBoundaryTest.php
  ```

  **Expected:** 7 Domain tests and the 3 newly added boundary tests fail for absent canonical engine/port/contract wiring; Task 3's two boundary tests remain GREEN. A missing class is an expected RED; syntax errors or DB misconfiguration are not.
- [ ] **Step 4: Implement the canonical port and service, preserving operation order.** `create`: `lifecycleId` first, return original array when null, otherwise `initialCode` then `InitialStateRules::withInitial`. `assertNoDirectStateMutation`: no-status return, same-status return (trim incoming only), then `lifecycleId` and throw for bound else return. `normalizeStatus`: reject non-string with raw value, `strtoupper(trim())`, reject empty or `strlen>50`, then `lifecycleId`, resolve active allowed codes if bound or exact caller list if unbound; strict membership then return code. `normalizeInitial`: call `initialCode` (without additional lifecycle query), then pure rule. Never treat BE010 exceptions as null Lifecycle and never call `ResourceNativeStateCatalog::codes` for the status validator.
- [ ] **Step 5: GREEN + legacy parity while original façade algorithms still exist:**

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012WriteBoundaryDomainTest.php tests/unit/BeReorg012WriteBoundaryBoundaryTest.php
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012InitialStateRulesTest.php
  ```

  **Expected:** **7 Domain + 5 Boundary + 7 pure** BE012 cases PASS (19 unique cases); compare same inputs/errors to original methods from Task 1. Commit four task paths with `git commit -m "refactor(be012): implement canonical workflow write boundary"`.

### Task 5: Compose the factory and preserve all three legacy entrypoints

**Files:** Create `backend/app/Bootstrap/WorkflowWriteBoundaryFactory.php`, `backend/tests/unit/BeReorg012WriteBoundaryFactoryTest.php` (5), `backend/tests/unit/BeReorg012WriteBoundaryCompatibilityTest.php` (5). Modify **only** `backend/app/Services/BusinessWorkflowWriteBoundary.php`, `backend/app/Services/WorkflowBusinessStatus.php`, `backend/app/Services/InitialCreateStateNormalizer.php` among existing production classes.

**Interfaces:** Factory exact signature `create(?ConnectionInterface $db=null, ?ResourceWorkflowStateResolver $states=null):WorkflowWriteBoundaryPort`. New factory object has no public constructor, no static state/cache. `BusinessWorkflowWriteBoundary::create()`/`assertNoDirectStateMutation()` call `WorkflowWriteBoundaryFactory::create(db:$db)` and pass exact args. `WorkflowBusinessStatus::normalize()` forwards native vocabulary, dynamic error and optional DB verbatim. `InitialCreateStateNormalizer` stores its original nullable concrete injected resolver; `normalize()` calls `WorkflowWriteBoundaryFactory::create(states:$this->states)->normalizeInitial(...)`; static `withInitial()` directly calls `InitialStateRules::withInitial()` with **no** factory/DB query.

- [ ] **Step 1: Write five factory/adapter tests:**
  - `testFactoryCreatesFreshEngineWithoutSharedCache`: two default calls give distinct service/adapters/BE010 state engines; BE010 caches initially empty, no factory static properties.
  - `testFactoryPassesProvidedDbToBe010StateResolution`: guarded test DB instance reaches all BE010 repositories through new factory and BE010 reader, verified by Reflection or observational fixture, without creating another connection.
  - `testFactoryReusesExactInjectedLegacyResolver`: constructed legacy resolver is the exact object held by adapter; no default engine is constructed in parallel; `InitialCreateStateNormalizer($resolver)` calls that same resolver through the bridge.
  - `testAdapterDelegatesThreeReadsWithoutMutation`: use a PHPUnit double for `ResourceStateResolutionPort`, verify each of three reads and their values/errors are forwarded one-for-one; no other BE010 methods invoked and no state-write side effect.
  - `testFactoryDoesNotReadDatabaseOnConstruction`: with a guarded DB instrumentation/query snapshot or a mock connection, `create(db:$db)` performs no metadata query/transaction; lookups begin only on a canonical method invocation. Guard real `erp_test` before creating a `DatabaseTestTrait` fixture.
- [ ] **Step 2: Write five compatibility tests with exact signature and behavior checks:**
  - `testBoundaryFacadeRetainsTwoStaticSignatures`: Reflection public static two methods, required DB param third/fourth respectively, unchanged arg names, return `array`/`void`, final class.
  - `testStatusFacadeRetainsNormalizeSignatureAndDefaults`: Reflect `type,value,nativeCodes,errorCode,db`, static return `string`, fifth nullable DB defaults null, same dynamic error code/context in real validation.
  - `testInitialNormalizerRetainsConstructorTwoMethodsAndConstant`: nullable concrete resolver constructor, instance `normalize`, static `withInitial`, exactly one public constant with original value; no new public method/constructor parameter.
  - `testFacadesDelegateWithoutDuplicatedAlgorithms`: compare runtime native/unbound/bound behavior to golden Task 1/4, inspect façade bodies for thin explicit delegation (no duplicate regex, SQL, state catalog and metadata branching).
  - `testExistingConsumersAndRoutesRemainUnchanged`: inspect representative Foundation/Organization/Contact/Employee/Product/Pricing call sites and route registrations; compare the diff to confirm none are modified; inherited callers still resolve the three FQCNs.
- [ ] **Step 3: Run RED before adding factory and delegates:**

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012WriteBoundaryFactoryTest.php tests/unit/BeReorg012WriteBoundaryCompatibilityTest.php
  ```

  **Expected:** missing factory causes targeted failures; existing Reflection-only legacy contract tests may already PASS. Do **not** alter a correct characterization test merely to force RED.
- [ ] **Step 4: Implement factory and three explicit façades.** If `$states !== null`, wrap **that** instance in `ExistingStateResolutionLookupAdapter` without calling BE010 factory; otherwise wrap `WorkflowStateResolutionFactory::create(db:$db)`. Allocate a fresh service/adapter each invocation; no singleton, per-process cache, DB lookup or transaction. Preserve legacy `InitialCreateStateNormalizer::ERROR_UNSUPPORTED_INITIAL` as an alias to `WorkflowWriteBoundaryErrors::ERROR_UNSUPPORTED_INITIAL`. Do not instantiate a DB engine in `withInitial`.
- [ ] **Step 5: Run GREEN and compare inherited consumers:**

  ```powershell
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012WriteBoundaryFactoryTest.php tests/unit/BeReorg012WriteBoundaryCompatibilityTest.php
  php vendor/bin/phpunit --no-coverage tests/unit/P1WorkflowCreateInitialStateTest.php tests/unit/P1WorkflowCreateLifecycleIntegrationTest.php tests/unit/BusinessWorkflowTenNativeBoundaryTest.php
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg012WriteBoundaryDatabaseTest.php tests/database/BeReorg012WriteBoundaryHttpTest.php
  ```

  **Expected:** **5 Factory + 5 Compatibility** PASS, inherited initial/native suites PASS, **7 characterized DB/HTTP** cases from Task 1 still PASS. If any inherited test breaks, revert and root-cause the delegation rather than changing old tests. Commit only six task files with `git commit -m "refactor(be012): preserve write boundary entrypoints via factory"`.

### Task 6: Create BE012 acceptance runner and safely extend inherited BE011 total gate

**Files:** Create `backend/tools/test-workflow-write-boundary.php`; modify **only** the test-total condition in `backend/tools/test-business-workflow-actions.php`. No test class or production change in this task.

**Interfaces:** BE012 runner exposes `be012RequiredMethods():array`, `be012TestFiles():array`, `be012Preflight(string $backend,array $required):void`, `be012AssertJUnit(string $junit,array $required):void`, `be012Run(array $argv):int`. It calls inherited `tools/test-business-workflow-actions.php --confirm-test-db` once after preflight; inherits its fresh JUnit and root log. Do not run a second full PHPUnit/PHPStan process.

- [ ] **Step 1: Define the exact source/JUnit method inventory in the new runner**, copied from the Spec §7, as seven class-to-method-name arrays: Boundary (5), Compatibility (5), Factory (5), Pure (7), Domain (7), Database (5), HTTP (2). Require exact public `test*` names per class, and precisely 36 distinct names; reject extra/missing/duplicate class methods before DB access. Require exactly the File Map's 7 new production files, 3 façades, 1 fixture, seven tests, and inherited runner prerequisite.
- [ ] **Step 2: Write the runner's refusal/verification probes before implementing its body.** Execute `php tools/test-workflow-write-boundary.php --help`, no arguments, extra flag; while the new runner is absent expect a missing-file/nonzero RED. After creation expect `--help => exit 0`, no confirmation/invalid args => `exit 2` **without opening a DB**, and a non-DB CLI probe of method inventory. As a disposable local harness (not an eighth PHPUnit class), build synthetic XML with 1,047, 1,048 and 1,049 named JUnit cases and one skipped case: `be012AssertJUnit` must reject every incorrect count/status, and reject a correctly-sized JUnit with a missing/duplicate BE012 method. Ensure `LIBXML_NONET` and fail closed on XML parse errors.
- [ ] **Step 3: Update exactly one inherited BE011 expression** inside `be011AssertJUnit()`:

  ```php
  // BEFORE:
  if ($cases === false || $cases->length !== 1012) {
  // AFTER:
  if ($cases === false || $cases->length < 1012) {
  ```

  Leave the **34 exact BE011 method identities**, no skips/failures/errors, all BE010/BE009/BE008/BE007/PBAC/Foundation checks and exit statuses untouched. Do not modify its comments/help/error text under this narrow allowlist; the new BE012 runner documents the 1,048 requirement correctly.
- [ ] **Step 4: Implement BE012's guarded execution path.** Require exactly `['--confirm-test-db']`; verify known `erp_test`/prefix through inherited guard, `proc_open`, DOM extension, fresh unique JUnit at `backend/build/logs/be-reorg-012.xml`. Delete stale BE012-owned JUnit; execute inherited BE011 runner once, stream/capture stdout and stderr, accept child exit **only** `0` or inherited-PHPStan `1` with independent evidence. Require fresh `backend/build/logs/be-reorg-011.xml`, check exactly 1,048 globally distinct testcase identities without any skipped/failed/errored element, and fresh **entire** `D:\XAMPP\htdocs\erp\test-result.log` contents **after** child run (the BE007 child overwrites the file; do not rely on a pre-run byte offset). Verify inherited marker `PHPUnit exit code: 0`, Foundation guard 17 and BE008/009/010/011 coverage markers; call `be010AssertJUnit` and `be011AssertJUnit` plus `be012AssertJUnit`, then copy verified child JUnit to BE012-owned JUnit, append BE012 summary with 36 exact cases. Retain raw PHPStan exit code 1, no `PHPStan PASS` claim. Distinguish missing/malformed/stale JUnit as `exit 2` with explicit `BE012 BLOCKED` message.
- [ ] **Step 5: Run CLI GREEN without touching DB** and inspect exact tree diff:

  ```powershell
  php tools/test-workflow-write-boundary.php --help
  php tools/test-workflow-write-boundary.php
  php tools/test-workflow-write-boundary.php --invalid
  git diff --check
  ```

  **Expected:** `0`, `2`, `2`, whitespace PASS. Also run the local synthetic JUnit probe from Step 2, which must reject malformed, wrong-count, skipped and duplicate cases; it must accept only a correctly assembled 1,048-case JUnit with all inherited/BE012 identities. Do not launch the full runner except on confirmed disposable Windows DB. Commit only the runner files with `git commit -m "test(be012): add guarded 1048-case runner and inherited gate"`.

### Task 7: Full Windows acceptance, PHPStan identity diff, independent review and candidate handoff

**Files:** No extra production/test files. Outputs are release artifacts *outside* the patch: BE012 incremental `.patch` against BE011 checkpoint, source overlay, SHA-256 manifest, acceptance evidence, and code-review findings. The authored Specification and Plan are documentation, not permission for additional production edits.

**Interfaces:** Consumes exact 10 production, 7 tests, 1 fixture and 2 runner paths from Tasks 1–6. Produces a verifiable candidate, not an automatically accepted release.

- [ ] **Step 1: Static/style verification on the isolated feature tree**: `php -l` for all changed/new PHP files (expected **20 file paths**, counting 10 production + 7 test + 1 fixture + 2 tools); `git diff --check`, `git status --short`, exact source allowlist check; no modified controller/routes/DB schema/PBAC/BE009–BE011 production. Verify BE011 runner predicate is the only inherited runner diff. (Run PHP lint against **all 20 changed PHP paths**, not just committed tracked files.)
- [ ] **Step 2: Focused PHPUnit on disposable Windows DB** (PowerShell, serially):

  ```powershell
  Set-Location D:\XAMPP\htdocs\erp\backend
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012InitialStateRulesTest.php tests/unit/BeReorg012WriteBoundaryBoundaryTest.php tests/unit/BeReorg012WriteBoundaryDomainTest.php
  php vendor/bin/phpunit --no-coverage tests/unit/BeReorg012WriteBoundaryFactoryTest.php tests/unit/BeReorg012WriteBoundaryCompatibilityTest.php
  php vendor/bin/phpunit --no-coverage tests/database/BeReorg012WriteBoundaryDatabaseTest.php tests/database/BeReorg012WriteBoundaryHttpTest.php
  ```

  **Expected:** exact **19 + 10 + 7 = 36/36 BE012** tests PASS without skips/errors; no parallel database processes. These targeted commands must run before the full destructive-test runner, not concurrently with it.
- [ ] **Step 3: Guarded full suite on *confirmed disposable* test DB:**

  ```powershell
  php tools/test-workflow-write-boundary.php --confirm-test-db
  Write-Host "BE012 raw runner exit: $LASTEXITCODE"
  ```

  **Expected:** Foundation 17 guards PASS, full PHPUnit exactly **1,048/1,048**, all 36 BE012 + inherited BE011 34, BE010 30, BE009 32, BE008 26, BE007 24, PBAC 91, none skipped/errored/failed; verify fresh JUnit/log. Raw runner exit `1` is consistent with inherited PHPStan diagnostics and **not** a PHPUnit failure; classify from the log. A `2` is a blocked gate and must not be accepted.
- [ ] **Step 4: PHPStan diagnostic identity parity against BE011's Windows 120-error checkpoint.** Run matching PHPStan config against candidate, collect file/line/diagnostic ID/message, compare with the BE011 recorded baseline; list unchanged, moved-with-source-attribution, removed, and genuinely new findings. Goal **0 new identities**; equal error count alone does not pass. Do not adjust PHPStan ignore/baseline. Because legacy classes were converted into delegates, inherited errors in their source may move to the new canonical class or disappear: document both explicitly. Do not call PHPStan clean unless its own exit is `0`.
- [ ] **Step 5: Independent code review and one TDD repair pass**: request a reviewer other than the author (focus on native-default/early-return/status type/error ordering, exact injected legacy resolver, SQL-free bridge and HTTP/service guard precedence). Fix Critical/Important findings through RED→GREEN + complete focused/full suite; document Minor deferrals. If no independent reviewer is available, label *self-review only* and keep release pending rather than claiming the gate passed.
- [ ] **Step 6: Incremental delivery and Windows integration evidence**: produce `BE-REORG-012_CANDIDATE` patch **from verified BE011+FIX01 checkpoint**, SHA-256 manifest and source overlay. Test `git apply --check`, apply, exact-file hashes and reverse on a clean reconstructed checkpoint with no unrelated staged changes. Never invent the Windows HEAD; have the user apply and rerun full acceptance on Windows. Request a real Git checkpoint after gates; do not merge/push/delete unrelated files. Status **CANDIDATE** while independent review, Windows 1,048 tests, PHPStan identity and pending BE011 secure-runtime/CSRF release conditions are not closed; **ACCEPTED** only with documented gates or explicit owner exceptions.

## Completeness Check / Review Ledger Seed

- **Spec §2 and §4:** all three original signatures/constant/error contexts and early-return order → Tasks 2, 4 and 5; real DB/HTTP parity → Task 1.
- **Spec §3:** exact 7/3 production architecture, 4-operation + 3-read ports, dual-source adapter, injected instance identity, stateless factory → Tasks 2–5.
- **Spec §5–6:** ten native exceptions, no hidden SQL/authorization bypass/locking/audit/route edits → Tasks 1, 3, 4 and 7.
- **Spec §7:** exactly 36 BE012 tests in seven classes → Tasks 1–5; no extra PHPUnit methods → Task 6.
- **Spec §8:** inherited BE011 total only, new runner guard/fresh JUnit/raw PHPStan semantics → Task 6; Windows evidence → Task 7.
- **Spec §9–12:** patch scope, isolated worktree, independent review, real Windows checkpoint and explicit release gates → Task 7.
- **Review Focus:** all five failure classes have named assertion owners in Tasks 1, 2 or 4; no item left without a test.
- **Interface dependencies:** Task 2 `InitialStateRules` → Task 4 engine; Task 3 `WorkflowStateLookupPort` and adapter → Task 4 constructor/Task 5 factory; Task 4 `WorkflowWriteBoundaryPort` and engine → Task 5 factory/façades; Task 1 characterization → Task 5 regression; Tasks 1–5 seven test classes → Task 6 JUnit inventory. These exact names/signatures must not drift.

## Owner Review and Execution Handoff

This plan is **ready for owner review, not implementation approval**. After the owner approves it, execute in an isolated worktree with strict TDD and the chosen execution mode (historically Native). **Recommended: Native execution** for the 7 tightly coupled tasks because the four-operation service, adapter, factory and three delegates share strict interfaces; request independent final review to compensate for single-author execution. If the owner instead chooses subagent-driven execution, enforce per-task fresh reviewer gates. No new BE012 production code, hidden migration or repository-wide cleanup is authorized until this plan and method are approved.
