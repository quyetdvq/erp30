# BE-REORG-009 Workflow / Lifecycle Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Relocate existing Lifecycle configuration, authorized transition orchestration and SQL persistence into `App\Modules\Workflow\Lifecycle`, retaining the complete legacy PHP entrypoints and behavior on the accepted BE008+FIX01 checkpoint.

**Architecture:** `LifecycleApplicationService` implements a five-method application port and consumes a nine-method repository port; `SqlLifecycleRepository` owns the unchanged 39-method SQL implementation. `WorkflowLifecycleFactory` composes a fresh application instance, while final legacy `LifecycleService`/`LifecycleRepository` delegate explicitly to one long-lived application/SQL instance respectively. Existing adapters, DTOs, authorization/audit ports and all callers remain untouched.

**Tech Stack:** PHP `^8.2` (Windows accepted: 8.3.30), CodeIgniter `^4.7`, Shield `^1.4`, MySQL/MariaDB InnoDB `127.0.0.1:3306/erp_test` (prefix `dinktech_`), PHPUnit 10.5.65, PHPStan 2.x, Git, PowerShell. Source ZIP has no `backend/vendor` and no `.git` history; runtime acceptance is performed on Windows.

**Spec:** `docs/p1/2026-10-17-be-reorg-009-workflow-lifecycle-core-design.md` (approach A and written Specification approved by the project owner on 2026-10-17).

## Global Constraints

- **Source of truth:** accepted Windows **BE-REORG-008 + FIX01**, not the older pre-FIX01 test inside `erp_v1.1.6.zip`. Before any edits, capture exact `git rev-parse HEAD`, `git status --short`, `git diff --check`, and the normalized 121-diagnostic BE008 PHPStan evidence. If the real checkpoint or FIX01 state cannot be established, STOP; do not fabricate a compatible production patch from the ZIP.
- **Existing acceptance:** **916 PHPUnit tests / 8,848 assertions**, zero failures/errors; Foundation guard **17 PASS**; BE008 **26**, BE007 **24**, PBAC **91** and all BE001–BE006 gates PASS. PHPStan **121 inherited** diagnostic identities, **zero new** verified by the owner; raw PHPStan exit code **1** remains nonzero unless the underlying diagnostics are genuinely removed.
- **Production allowlist:** only modify `backend/app/Services/LifecycleService.php`, `backend/app/Repositories/LifecycleRepository.php`; only create `backend/app/Bootstrap/WorkflowLifecycleFactory.php`, `backend/app/Modules/Workflow/Lifecycle/Application/Ports/{LifecycleApplicationPort,LifecycleRepositoryPort}.php`, `backend/app/Modules/Workflow/Lifecycle/Application/LifecycleApplicationService.php` and `backend/app/Modules/Workflow/Lifecycle/Infrastructure/Persistence/SqlLifecycleRepository.php`. No additional production edits without a revised, approved spec.
- **Public service contract:** final `LifecycleService`; eight existing constants/literals; original seven constructor parameters exactly named `repository`, `authorization`, `audit`, `adapters`, `db`, `mutationContext`, `clock` with original types/order/defaults; five public methods `registerTargetAdapter`, `validateConfiguration`, `configure`, `resolveTransition`, `execute` with original reflection signatures and PHPDoc shapes. Named and positional callers remain valid.
- **Public repository contract:** final `LifecycleRepository`; seven existing constants/literals; `__construct(?ConnectionInterface $db = null)`; **all 39** public methods beyond constructor, including aliases, with original signature, defaults, return types, error codes and PHPDocs. No `__call()`, inheritance, `class_alias`, reflective dispatch or broadening types to `mixed`.
- **Repository port:** *exactly nine* application-facing methods: `findLifecycle`, `lockLifecycle`, `findLifecycleStates`, `findTransitions`, `findActiveWorkflowState`, `findActiveTransitionByAction`, `insertLifecycleState`, `insertTransition`, `transactional`. The two repository implementations both satisfy it; no other method is added to the port.
- **Business invariants:** unchanged `PositiveId` error context; exactly one initial State for an ACTIVE Lifecycle (multiple final allowed); active workflow-state membership, duplicate detection and transition lookup precedence; `configure()` locks first, validates combined rows, inserts and revalidates **inside one transaction**; `execute()` uses trusted persisted snapshot/state/organization, checks destination **before** PBAC, audits denial without mutation, then locks/reloads/mutates/audits within **adapter-owned** transaction, returning the original snapshot. Stale data throws `ConcurrencyException('lifecycle.state_change')`; no fail-open path.
- **SQL invariants:** original tables, filters and deterministic order, audit field ownership, `SELECT ... FOR UPDATE`, `BaseConnection` identity, `transBegin/transStatus/transCommit/transRollback/resetTransStatus`, original `DomainException`/`PersistenceException` wrapping, and `LIFECYCLE_*` codes. Do not move operations between connections or create a new connection inside an active transaction.
- **Laziness:** with no explicit `$db`, preserve `$db ?? db_connect()` timing; retain supplied `LifecycleRepository`, authorization, audit, mutation context and clock instances. Default authorization/audit collaborators are lazily created, not eagerly initialized in factory/constructor; `OperationContext` is created in each operation at the same original point. Adapter registration and `execute()` share one canonical application instance per facade.
- **Frozen integration:** do not modify `LifecycleTargetAdapter`, `LifecycleTargetSnapshot`, `LifecycleAuthorizationPort`, `LifecycleAuditPort`, existing consumers, `Api030AuthorizationWorkflowService`, `ResourceWorkflowStateResolver`, PBAC/Numbering/BE007/BE008, business/resource adapters, routes, controllers, models, migrations, seeds, frontend, OpenAPI, `phpstan.neon`, `phpunit.dist.xml`. Preserve the **ten** `ResourceNativeStateCatalog::INDEPENDENT_RESOURCES` entries and existing native-state fallback for unbound business resources.
- **Test safety:** never touch a non-test DB; no parallel workers on `erp_test`; use explicit `--confirm-test-db` and existing Foundation 17-check/DB guard. Current 14 Lifecycle core tests (`P1Wf001` 6 + `P1Wf002` 8) stay unchanged. All new BE009 tests are independently named and non-skipped; **32 new**, projected **948** total. Do not invent an assertion count.
- **Runner:** new `backend/tools/test-workflow-lifecycle.php` invokes `tools/test-authorization-decision.php --confirm-test-db` **once as a child process**, never `require`s the exit-taking runner, and never changes BE007/BE008 runners. It independently enforces exact class/method JUnit inventory, inherited coverage, fresh evidence and honest PHPStan exit semantics.
- **Release:** one incremental source patch **only** from verified Windows BE008+FIX01 checkpoint, clean apply/reverse, SHA-256 and evidence; `CANDIDATE` until Windows acceptance/independent review. Never claim a Git commit or PHPUnit/PHPStan run in the snapshot-only environment.

## Review Focus

These five high-risk inputs/conditions are easy to miss during a mechanical relocation. The named tests below MUST cover them even when existing P1 tests happen to pass.

1. **A caller injects a legacy repository and a different optional `$db`:** the injected repository retains exact identity and its original connection; no surprise rebind or new session during `configure()`. Task 4: `testFactoryKeepsEverySuppliedCollaboratorByIdentity` and `testFactoryUsesOneSuppliedConnectionForOmittedCollaborators`.
2. **An adapter is passed under a mismatched string key, or registered twice after construction:** reject with `WF_TARGET_ADAPTER_REGISTRATION_INVALID` and the original context, never silently replace the adapter. Task 4: `testAdapterRegistrationUsesSameInstanceAndRejectsDuplicates`.
3. **A query returns `false` or an update targets a missing row:** use `LIFECYCLE_PERSISTENCE_FAILED` or the original `false` result, not a false successful/not-found conversion; missing audit input never authorizes caller-owned audit fields. Task 2: `testFalseQueryMissingUpdateAndInvalidPayloadKeepTypedFailures` and `testLegacyAndSqlLifecycleCrudHaveIdenticalRowsAndAudit`.
4. **Caller supplies a different destination State or a stale locked organization ID:** destination mismatch must fail before authorization; stale snapshot must throw `ConcurrencyException('lifecycle.state_change')` and trigger adapter rollback. Task 3: `testMismatchedDestinationFailsBeforeAnyAuthorization`, `testExecuteReturnsOriginalSnapshotAndRejectsStaleLockedData`.
5. **A second real SQL session competes for a Lifecycle row lock:** require distinct verified `CONNECTION_ID()` values, InnoDB, a genuine MySQL `1205` timeout while lock held, and successful acquisition after release—not a sleep/timing proxy. Task 6: `testLifecycleForUpdateLocksOnlyOnSharedSuppliedSessionUntilRelease`.

---

## File Map and Contract Handoffs

**New production files (5):**

| Path relative to `backend/` | Owner / interface |
|---|---|
| `app/Modules/Workflow/Lifecycle/Application/Ports/LifecycleApplicationPort.php` | Five public application operations; existing integration types only |
| `app/Modules/Workflow/Lifecycle/Application/Ports/LifecycleRepositoryPort.php` | Nine persistence operations consumed by the application |
| `app/Modules/Workflow/Lifecycle/Infrastructure/Persistence/SqlLifecycleRepository.php` | Canonical 39-method SQL, original private helpers/allowed-field lists, implements repository port |
| `app/Modules/Workflow/Lifecycle/Application/LifecycleApplicationService.php` | Canonical validation/configure/resolve/execute + private helpers, implements application port |
| `app/Bootstrap/WorkflowLifecycleFactory.php` | Stateless final factory, `create()` takes original seven dependencies and returns application port |

**Changed production files (2):** `app/Repositories/LifecycleRepository.php` (explicit 39-method wrapper/one SQL delegate; implements repository port) and `app/Services/LifecycleService.php` (explicit five-method wrapper/one application engine).

**New tests (7 files, exactly 32 PHPUnit methods):**

| File relative to `backend/` | Class | Count | Task |
|---|---|---:|---:|
| `tests/unit/BeReorg009LifecycleBoundaryTest.php` | `BeReorg009LifecycleBoundaryTest` | 5 | 1, 3, 4 |
| `tests/unit/BeReorg009LifecycleCompatibilityTest.php` | `BeReorg009LifecycleCompatibilityTest` | 6 | 2, 4 |
| `tests/unit/BeReorg009LifecycleFactoryTest.php` | `BeReorg009LifecycleFactoryTest` | 4 | 4 |
| `tests/database/BeReorg009LifecycleRepositoryParityTest.php` | `BeReorg009LifecycleRepositoryParityTest` | 6 | 2 |
| `tests/database/BeReorg009LifecycleApplicationParityTest.php` | `BeReorg009LifecycleApplicationParityTest` | 7 | 3 |
| `tests/database/BeReorg009LifecycleHttpTest.php` | `BeReorg009LifecycleHttpTest` | 2 | 5 |
| `tests/database/BeReorg009LifecycleLockTest.php` | `BeReorg009LifecycleLockTest` | 2 | 6 |

**New test support (permitted):** `tests/_support/Workflow/BeReorg009LifecycleFixture.php` creates uniquely named Lifecycle, State, Transition and mock/real adapter samples using the supplied `erp_test` connection; uses test-only `OperationContext` and expected audit timestamp; isolates modifications and closes independent DB peers. **Do not add test hooks in production.**

**New guarded runner:** `tools/test-workflow-lifecycle.php`. **Docs:** this file and `docs/p1/2026-10-17-be-reorg-009-workflow-lifecycle-core-design.md` (already approved). The plan source path is intentionally within `docs/p1/` to match the project's existing BE-REORG implementation-plan convention.

### Frozen interfaces for implementers

```php
namespace App\Modules\Workflow\Lifecycle\Application\Ports;

interface LifecycleApplicationPort
{
    public function registerTargetAdapter(\App\Services\LifecycleTargetAdapter $adapter): void;
    /** @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>} */
    public function validateConfiguration(int $lifecycleId): array;
    /** @param list<array<string,mixed>> $states @param list<array<string,mixed>> $transitions
     *  @return array{lifecycle:array<string,mixed>,states:list<array<string,mixed>>,transitions:list<array<string,mixed>>} */
    public function configure(int $lifecycleId, array $states, array $transitions, ?\App\Infrastructure\Mutation\OperationContext $operation = null): array;
    /** @return array<string,mixed> */
    public function resolveTransition(int $lifecycleId, int $currentStateId, string $actionCode): array;
    /** @return array{decision:\App\Authorization\AuthorizationDecision,transition:array<string,mixed>,target:\App\Services\LifecycleTargetSnapshot} */
    public function execute(string $resourceType, mixed $targetId, string $actionCode, ?\App\Infrastructure\Mutation\OperationContext $operation = null, ?int $expectedDestinationStateId = null): array;
}

interface LifecycleRepositoryPort
{
    /** @return array<string,mixed>|null */
    public function findLifecycle(mixed $id): ?array;
    /** @return array<string,mixed>|null */
    public function lockLifecycle(mixed $id): ?array;
    /** @return list<array<string,mixed>> */
    public function findLifecycleStates(mixed $lifecycleId): array;
    /** @return list<array<string,mixed>> */
    public function findTransitions(mixed $lifecycleId, ?string $status = null): array;
    /** @return array<string,mixed>|null */
    public function findActiveWorkflowState(mixed $id): ?array;
    /** @return array<string,mixed>|null */
    public function findActiveTransitionByAction(mixed $lifecycleId, mixed $fromStateId, string $actionCode): ?array;
    /** @param array<string,mixed> $data */
    public function insertLifecycleState(array $data, ?\App\Infrastructure\Mutation\OperationContext $operation = null): int;
    /** @param array<string,mixed> $data */
    public function insertTransition(array $data, ?\App\Infrastructure\Mutation\OperationContext $operation = null): int;
    /** @param callable(): mixed $callback */
    public function transactional(callable $callback, string $operation = 'transaction'): mixed;
}
```

*Existing `LifecycleService`/`LifecycleRepository` signatures are frozen by the approved Specification §2 and its Appendix A, plus source code at the recorded checkpoint. Do not reconstruct them from an invented simplified example. The application canonical constructor is identical in parameter order/names/defaults to the seven legacy service arguments except `$repository` is typed `?LifecycleRepositoryPort` instead of the public legacy `?LifecycleRepository`. `WorkflowLifecycleFactory::create(...)` retains the exact **legacy concrete** types; the factory passes the injected repository to the port without wrapping/reconnecting it. Its return type is `LifecycleApplicationPort`.*

**Detailed new test method inventory — canonical names used by runner:**

```text
BeReorg009LifecycleBoundaryTest
  testApplicationPortDeclaresFiveCanonicalOperations
  testRepositoryPortExposesOnlyNineApplicationOperations
  testCanonicalOwnersDoNotImportLegacyFacades
  testFacadesDelegateWithoutBusinessOrSqlImplementation
  testExistingAdaptersAndDtoContractsRemainUnchanged
BeReorg009LifecycleCompatibilityTest
  testLegacyServiceConstructorPreservesSevenPositionalAndNamedParameters
  testLegacyServiceRetainsEightConstantsAndFiveMethods
  testLegacyRepositoryConstructorConstantsAndThirtyNineMethods
  testRepositoryAliasesDelegateToOneSqlInstance
  testAdapterRegistrationUsesSameInstanceAndRejectsDuplicates
  testLegacyServicePreservesReturnShapesAndExceptionClasses
BeReorg009LifecycleFactoryTest
  testFactoryCreatesFreshApplicationPerCallWithoutSharedRegistry
  testFactoryKeepsEverySuppliedCollaboratorByIdentity
  testFactoryUsesOneSuppliedConnectionForOmittedCollaborators
  testFactoryRetainsLazyDefaultsAndOperationContextTiming
BeReorg009LifecycleRepositoryParityTest
  testLegacyAndSqlLifecycleCrudHaveIdenticalRowsAndAudit
  testStateCatalogCrudsAndPositiveIdsRemainEquivalent
  testMembershipOrderingFlagsAndUniquenessRemainEquivalent
  testTransitionOrderingActiveFilteringAndAliasesMatch
  testFalseQueryMissingUpdateAndInvalidPayloadKeepTypedFailures
  testTransactionalRollbackAndLockUseSuppliedConnection
BeReorg009LifecycleApplicationParityTest
  testActiveConfigHasExactlyOneInitialWithMultipleFinalAllowed
  testInvalidMembershipOrTransitionFailsClosedBeforeWrites
  testConfigureValidatesCombinedBatchAndAtomicallyRollsBack
  testResolveTransitionRequiresActiveLifecycleAndActiveDestination
  testExecuteDenialAuditsWithoutMutationUsingTrustedState
  testMismatchedDestinationFailsBeforeAnyAuthorization
  testExecuteReturnsOriginalSnapshotAndRejectsStaleLockedData
BeReorg009LifecycleHttpTest
  testLifecycleStatesAndTransitionsRoutesPreserveEnvelopeCsrfAndAudit
  testBusinessSetStateDeniedAndInvalidRequestsHaveNoWrites
BeReorg009LifecycleLockTest
  testLifecycleForUpdateLocksOnlyOnSharedSuppliedSessionUntilRelease
  testTargetAdapterStaleStateRaisesConcurrencyAndRollsBack
```

**Standard focused test command** (PowerShell, from `D:\XAMPP\htdocs\erp\backend`; never use these concurrently on `erp_test`):

```powershell
php vendor/bin/phpunit --no-coverage tests/unit/BeReorg009LifecycleBoundaryTest.php
# Replace file path with the target task's exact test file.
```

For production-moving Tasks 1–4, follow strict TDD: write named tests, run and observe a **real** failure for the intended missing contract/behavior (not missing vendor/DB), implement the minimum canonical change, rerun green and inherited tests. Tasks 5–6 add post-migration characterization/concurrency regression tests: these should already pass if migration preserved behavior; investigate any failure against the accepted BE008 behavior, never force a red result. Runner Task 7 uses no-DB negative-path checks. Every task ends with diff review and a scoped commit. Source-only environments must mark runtime steps **PENDING WINDOWS**, not PASS.

---

### Task 1: Two Narrow Ports and Baseline Capture

**Files:**
- Create: `backend/app/Modules/Workflow/Lifecycle/Application/Ports/LifecycleApplicationPort.php`
- Create: `backend/app/Modules/Workflow/Lifecycle/Application/Ports/LifecycleRepositoryPort.php`
- Modify: `backend/app/Repositories/LifecycleRepository.php` (only `implements LifecycleRepositoryPort` and import; SQL remains in place until Task 2)
- Test: `backend/tests/unit/BeReorg009LifecycleBoundaryTest.php` (first **two** methods)

**Interfaces:** Produces `LifecycleApplicationPort` with five exact methods and `LifecycleRepositoryPort` with nine exact methods above. The old repo already satisfies all nine; its declaration implements the port without altering behavior. Tasks 2–4 consume these names and type contracts.

- [ ] **Step 1: Baseline preflight (no edits).** On Windows, ensure actual BE008_FIX01 correction is present in `backend/tests/unit/BeReorg007AuthorizationFactoryTest.php` (`$authorization->engine->policies` Reflection chain); capture `git rev-parse HEAD`, `git status --short`, list of unstaged/staged edits, `git diff --check`, and frozen normalized PHPStan diagnostic identities. Require a clean or explicitly isolated source worktree; do **not** reset or discard user changes. Record baseline provenance in execution evidence.
- [ ] **Step 2: Write red unit methods** `testApplicationPortDeclaresFiveCanonicalOperations` and `testRepositoryPortExposesOnlyNineApplicationOperations`. Assert interface existence, exact sorted method names, argument names/types/defaults/return types and the array/callable PHPDoc shapes. Run `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg009LifecycleBoundaryTest.php --filter 'testApplicationPortDeclaresFiveCanonicalOperations|testRepositoryPortExposesOnlyNineApplicationOperations'`; expected initially FAIL on missing interfaces.
- [ ] **Step 3: Create both ports** with the exact signatures above and mark the unchanged legacy repository as `implements LifecycleRepositoryPort`. No implementation helpers, DB, HTTP or legacy service imports in ports.
- [ ] **Step 4: Rerun Step 2 focused tests**; expected 2 PASS. Run `php vendor/bin/phpunit --no-coverage tests/unit/P1Wf001LifecycleRepositoryTest.php tests/unit/P1Wf002LifecycleServiceTest.php`; expected existing 14 tests PASS (subject to PHPUnit CLI filename handling; if version only accepts one test path, use `--filter 'P1Wf001LifecycleRepositoryTest|P1Wf002LifecycleServiceTest'`). Run `php -l` for both ports and edited legacy repo, then `git diff --check`.
- [ ] **Step 5: Commit the task** with only the two ports, first two boundary tests, and the repository declaration. Suggested message: `refactor(be-reorg-009): define lifecycle application and repository ports`.

### Task 2: Canonical SQL Persistence and Legacy Repository Facade

**Files:**
- Create: `backend/app/Modules/Workflow/Lifecycle/Infrastructure/Persistence/SqlLifecycleRepository.php`
- Modify: `backend/app/Repositories/LifecycleRepository.php`
- Test: `backend/tests/unit/BeReorg009LifecycleCompatibilityTest.php` (two repository methods)
- Test: `backend/tests/database/BeReorg009LifecycleRepositoryParityTest.php` (six methods)
- Support: `backend/tests/_support/Workflow/BeReorg009LifecycleFixture.php`

**Interfaces:** Canonical `final SqlLifecycleRepository implements LifecycleRepositoryPort` exposes the unchanged **39** public repository methods, seven `LIFECYCLE_*` constants, private SQL helpers and constructor `__construct(?ConnectionInterface $db = null)`. Legacy `final LifecycleRepository implements LifecycleRepositoryPort` exposes the identical 39 public methods, owns `private readonly SqlLifecycleRepository $delegate`, and delegates explicitly, including `transactional(callable $callback, string $operation = 'transaction'): mixed`.

- [ ] **Step 1: Write two red compatibility tests:** `testLegacyRepositoryConstructorConstantsAndThirtyNineMethods` (full Reflection signature/parameter/default/return/constants snapshot from BE008 source) and `testRepositoryAliasesDelegateToOneSqlInstance` (`find/findLifecycle`, `findByCode/findLifecycleByCode`, `insert/insertLifecycle`, `update/updateLifecycle`, `findForUpdate/findLifecycleForUpdate`, `resolveTransition/findActiveTransitionByAction`, and `transactional` delegate to the *same* SQL object). Expect missing delegate/canonical class before implementation.
- [ ] **Step 2: Write six red database methods** in `BeReorg009LifecycleRepositoryParityTest`: `testLegacyAndSqlLifecycleCrudHaveIdenticalRowsAndAudit` (same supplied DB, trusted actor/time overrides untrusted created/updated fields, no duplicate inserts); `testStateCatalogCrudsAndPositiveIdsRemainEquivalent` (CRUD + invalid IDs/field errors); `testMembershipOrderingFlagsAndUniquenessRemainEquivalent` (sequence then ID, initial/final, duplicate rejection); `testTransitionOrderingActiveFilteringAndAliasesMatch` (from/action/ID ordering, inactive rows excluded only for active query, correct alias); `testFalseQueryMissingUpdateAndInvalidPayloadKeepTypedFailures` (`false` DB query versus missing update, `ERROR_PERSISTENCE_FAILED`, `ERROR_UPDATE_EMPTY`, `ERROR_FIELD_*`); `testTransactionalRollbackAndLockUseSuppliedConnection` (one DB/session, rollback on unexpected throwable, `ERROR_MUTATION_FAILED`, `DomainException` escapes unchanged). Avoid comparing rows from duplicate inserts with different auto-increment IDs: compare normalized field values after reading a *shared* row with both implementations. Initial red run fails for missing canonical class/delegation.
- [ ] **Step 3: Mechanically relocate all SQL methods and private helpers** from the verified `LifecycleRepository.php` to `SqlLifecycleRepository.php`; preserve field allowlists, `PositiveId`, audit timestamps, query ordering, error mapping, and `BaseConnection` guard. Do not rewrite query semantics while moving them. SQL implementation must never import the legacy repository.
- [ ] **Step 4: Replace the legacy repository body with explicit typed delegates** for **all 39** methods from Specification Appendix A (17 Lifecycle master + 8 State + 5 membership + 8 transitions + 1 transaction). Preserve seven public constant values, constructor `db` name/type/default and method PHPDocs. No SQL query, payload filter or transaction algorithm remains in facade. Keep supplied `ConnectionInterface` object; construct exactly one canonical SQL delegate.
- [ ] **Step 5: Rerun the eight focused methods** via `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg009LifecycleCompatibilityTest.php --filter 'testLegacyRepositoryConstructorConstantsAndThirtyNineMethods|testRepositoryAliasesDelegateToOneSqlInstance'` and `php vendor/bin/phpunit --no-coverage tests/database/BeReorg009LifecycleRepositoryParityTest.php`; expected 8 PASS. Run inherited `P1Wf001LifecycleRepositoryTest` (6 PASS) and `P1Db040WorkflowLifecycleTest` plus `P1Wf002LifecycleServiceTest` regression; do not claim a DB PASS in source-only environment.
- [ ] **Step 6: Verify SQL/contract parity** by comparing extracted original method bodies/private helpers with canonical SQL (excluding namespace/interface/constructor wiring) and Reflection inventory. Run `php -l` on changed/new PHP and `git diff --check`; commit only Task 2 files. Suggested message: `refactor(be-reorg-009): move lifecycle SQL into canonical repository`.

### Task 3: Canonical Lifecycle Orchestration

**Files:**
- Create: `backend/app/Modules/Workflow/Lifecycle/Application/LifecycleApplicationService.php`
- Test: `backend/tests/database/BeReorg009LifecycleApplicationParityTest.php` (seven methods)
- Test: `backend/tests/unit/BeReorg009LifecycleBoundaryTest.php` (third method)
- Reuse: `backend/tests/_support/Workflow/BeReorg009LifecycleFixture.php`

**Interfaces:** `final LifecycleApplicationService implements LifecycleApplicationPort`; constructor **seven** parameters named/order `repository` (`?LifecycleRepositoryPort`), `authorization` (`?LifecycleAuthorizationPort`), `audit` (`?LifecycleAuditPort`), `adapters` (`array=[]`), `db` (`?ConnectionInterface=null`), `mutationContext` (`?MutationContext=null`), `clock` (`?Clock=null`). Implements the exact five application port methods and retains the eight error constants. Its `repo(): LifecycleRepositoryPort` returns supplied port or on-demand `new SqlLifecycleRepository($this->db)`; `auth()` and `audit()` remain lazy and use `$this->db`.

- [ ] **Step 1: Write red boundary test** `testCanonicalOwnersDoNotImportLegacyFacades`: Reflection confirms final canonical classes implementing their ports, owns `assertConfiguration/assertBatchShape/membership/flag/actionCode/resourceType/repo/auth/audit/operation` (where originally private); canonical application imports neither `App\Services\LifecycleService` nor `App\Repositories\LifecycleRepository`, canonical SQL imports neither facade. Initially fails because canonical application does not exist.
- [ ] **Step 2: Write seven red lifecycle parity tests:** `testActiveConfigHasExactlyOneInitialWithMultipleFinalAllowed` (active exactly one initial, multiple final accepted); `testInvalidMembershipOrTransitionFailsClosedBeforeWrites` (inactive State, duplicate membership, foreign transition and exact `WF_*` contexts); `testConfigureValidatesCombinedBatchAndAtomicallyRollsBack` (lock before reading, mixed existing+new rows, reject late insert, zero partial rows); `testResolveTransitionRequiresActiveLifecycleAndActiveDestination` (inactive lifecycle, nonmember source, inactive transition, inactive catalog destination, exact error precedence); `testExecuteDenialAuditsWithoutMutationUsingTrustedState` (persisted target/state/org passed to PBAC, one denial audit, zero adapter mutation); `testMismatchedDestinationFailsBeforeAnyAuthorization` (expected destination differs, 0 auth/audit/mutate calls); `testExecuteReturnsOriginalSnapshotAndRejectsStaleLockedData` (authorized snapshot identity returned; stale locked state **or organization** yields typed concurrency error and no successful mutation, audit-on-success inside adapter-owned transaction). Initial run fails on missing canonical application class.
- [ ] **Step 3: Mechanically move the complete service algorithm** (`registerTargetAdapter`, `validateConfiguration`, `configure`, `resolveTransition`, `execute`, private helpers) from frozen legacy service into `LifecycleApplicationService`; adjust only repository type and canonical SQL instantiation. Preserve order of `OperationContext` construction, adapter key normalization, error codes/contexts, audit and transaction placement. Do **not** edit existing adapters or caller services. Temporary duplicate logic in old class is tolerated only until Task 4; it cannot ship.
- [ ] **Step 4: Run** `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg009LifecycleBoundaryTest.php --filter testCanonicalOwnersDoNotImportLegacyFacades` and `php vendor/bin/phpunit --no-coverage tests/database/BeReorg009LifecycleApplicationParityTest.php`; expected 8 PASS. Rerun inherited `P1Wf002LifecycleServiceTest` (8 PASS) and `P1Wf007NativeStateContractTest` without editing either, and `php -l`/`git diff --check`.
- [ ] **Step 5: Review ordering and commit** canonical application and test/fixture changes only. Suggested message: `refactor(be-reorg-009): introduce canonical lifecycle orchestration`.

### Task 4: Stateless Factory and Stable Legacy Service Facade

**Files:**
- Create: `backend/app/Bootstrap/WorkflowLifecycleFactory.php`
- Modify: `backend/app/Services/LifecycleService.php`
- Test: `backend/tests/unit/BeReorg009LifecycleBoundaryTest.php` (remaining two methods)
- Test: `backend/tests/unit/BeReorg009LifecycleCompatibilityTest.php` (remaining four methods)
- Test: `backend/tests/unit/BeReorg009LifecycleFactoryTest.php` (four methods)

**Interfaces:** `final WorkflowLifecycleFactory` with private constructor and

```php
public static function create(
    ?\App\Repositories\LifecycleRepository $repository = null,
    ?\App\Services\LifecycleAuthorizationPort $authorization = null,
    ?\App\Services\LifecycleAuditPort $audit = null,
    array $adapters = [],
    ?\CodeIgniter\Database\ConnectionInterface $db = null,
    ?\App\Infrastructure\Mutation\MutationContext $mutationContext = null,
    ?\App\Infrastructure\Time\Clock $clock = null,
): \App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleApplicationPort;
```

Legacy `LifecycleService` stores `private readonly LifecycleApplicationPort $engine`, constructs it **once** via `WorkflowLifecycleFactory::create(...)` with the unchanged seven public constructor arguments, and explicitly delegates the five public methods.

- [ ] **Step 1: Write four red compatibility tests**: `testLegacyServiceConstructorPreservesSevenPositionalAndNamedParameters` (Reflection and both invocation styles); `testLegacyServiceRetainsEightConstantsAndFiveMethods` (exact literal values and reflection signatures); `testAdapterRegistrationUsesSameInstanceAndRejectsDuplicates` (registry continuity from constructor + later registration; duplicate normalized name/mismatched array key throws original error/context); `testLegacyServicePreservesReturnShapesAndExceptionClasses` (same `{lifecycle,states,transitions}` / `{decision,transition,target}` shapes; `DomainException`, `PersistenceException`, `ConcurrencyException` not coerced).
- [ ] **Step 2: Write four red factory tests**: `testFactoryCreatesFreshApplicationPerCallWithoutSharedRegistry` (distinct engines and registries; stateless factory); `testFactoryKeepsEverySuppliedCollaboratorByIdentity` (injected legacy repo/auth/audit/context/clock/adapter same objects and not rebound); `testFactoryUsesOneSuppliedConnectionForOmittedCollaborators` (port and canonical SQL defaults use supplied `$db`, authorization/audit defaults use that same `$db`, no second DB session when invoked); `testFactoryRetainsLazyDefaultsAndOperationContextTiming` (construction without authorization/audit or `begin()` side effects; context generated only on API method call and matches supplied clock).
- [ ] **Step 3: Write two red boundary tests** `testFacadesDelegateWithoutBusinessOrSqlImplementation` (no `assertConfiguration`/query builder/transaction algorithm left in facades; explicit calls for five/39 methods, both final) and `testExistingAdaptersAndDtoContractsRemainUnchanged` (Reflection of the frozen signatures for `LifecycleTargetAdapter`, DTO and auth/audit ports; source byte preservation is separately checked against the Windows checkpoint in Task 8; assert `ResourceNativeStateCatalog` ten approved values). These should fail while original service still owns the algorithm.
- [ ] **Step 4: Implement `WorkflowLifecycleFactory::create()`** to return a new canonical application object, with no constructor-time SQL or `OperationContext` generation; preserve nullable/supplied dependency identity. Implement final `LifecycleService` facade retaining eight constants and seven public constructor arguments, one readonly engine and five explicit method forwards. Avoid `__call`, aliases, inheritance or adding a public engine parameter.
- [ ] **Step 5: Run** `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg009LifecycleCompatibilityTest.php`, `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg009LifecycleFactoryTest.php`, `php vendor/bin/phpunit --no-coverage tests/unit/BeReorg009LifecycleBoundaryTest.php`. Expected these files total 6+4+5 = 15 PASS (including earlier tests). Rerun the 14 original P1 Lifecycle tests and BE001/BE003 lifecycle adapter parity focused tests; review frozen external files remain unmodified.
- [ ] **Step 6: Check** `php -l` for factory and facade, `git diff --check`, complete reflection inventory, canonical owner single-instance and no legacy private Reflection dependency in inherited tests. If an existing test introspects migrated private internals, adjust **that test alone** to traverse the explicit engine, preserving its assertions—do not re-add private algorithms to facade. Commit Task 4. Suggested message: `refactor(be-reorg-009): retain lifecycle legacy service facade`.

### Task 5: In-Process HTTP / CSRF Contract Regression

**Files:**
- Create: `backend/tests/database/BeReorg009LifecycleHttpTest.php`
- Reuse: `tests/_support/Workflow/BeReorg009LifecycleFixture.php`, existing guarded FeatureTestTrait + AuthenticationTesting / HTTP exception-handler helpers

**Interfaces:** Unchanged existing `PUT /api/v1/lifecycles/{id}/states`, `PUT /api/v1/lifecycles/{id}/transitions` route (both use `AuthorizationWorkflow::configureStates`), `GET` states/transitions, and business `POST /api/v1/provinces/{id}/actions/set-state` (or documented installed business adapter route). HTTP envelopes are produced by existing `ApiTransport`, not by the new module.

- [ ] **Step 1: Add regression characterization methods** `testLifecycleStatesAndTransitionsRoutesPreserveEnvelopeCsrfAndAudit` (real authenticated policy-authorized actor for `lifecycle.view/edit`, csrf-bearing PUT, JSON `data` + existing `meta.request_id`, stored rows/audit actor and timestamp, GET ordering; PUT without CSRF is rejected without mutation) and `testBusinessSetStateDeniedAndInvalidRequestsHaveNoWrites` (real PBAC denied actor => 403 `AUTH_POLICY_DENIED`, invalid action/destination => existing 422 `WF_INVALID_TRANSITION`, no state row write, normal authorized transition still works). Use proper resource grants from existing test fixture and current `RequestContext` reset; preserve real CSRF filters.
- [ ] **Step 2: Run two tests** via `php vendor/bin/phpunit --no-coverage tests/database/BeReorg009LifecycleHttpTest.php`; inspect any failed assertion against **BE008 behavior** before assuming regression. Since this is compatibility characterization (not new feature), a test must not invent a response field/code absent from the accepted baseline. Do not modify routes, controllers or production consumer to make a wrong expectation pass.
- [ ] **Step 3: Reuse/correct test fixtures only** (test-only) until both methods and existing `BeReorg001FoundationHttpTest` / BE008 HTTP regression tests PASS. If existing contract and approved spec conflict, STOP for a decision rather than changing application semantics. Verify `git diff --check` and commit test-only Task 5. Suggested message: `test(be-reorg-009): characterize lifecycle http and csrf contracts`.

### Task 6: Real InnoDB Lock and Adapter Concurrency

**Files:**
- Create: `backend/tests/database/BeReorg009LifecycleLockTest.php`
- Reuse: `tests/_support/Workflow/BeReorg009LifecycleFixture.php`, `tests/database/BeReorg001FoundationLockTest.php` patterns

**Interfaces:** `LifecycleRepositoryPort::lockLifecycle()`/legacy alias `findLifecycleForUpdate()` use existing supplied BaseConnection; `LifecycleApplicationPort::execute()` delegates transaction ownership to target adapter and uses exact snapshot comparison.

- [ ] **Step 1: Add lock verification method** `testLifecycleForUpdateLocksOnlyOnSharedSuppliedSessionUntilRelease`: assert test DB and InnoDB engine, construct two `db_connect('tests', false)` peers, assert distinct `CONNECTION_ID()`, begin holder transaction, lock one Lifecycle row with canonical SQL, contender lock fails with genuine `1205` (from DB error or Throwable chain), holder rollback releases it and contender can then lock. Assert no sleep-only proof, set session-local 2s timeout, close/rollback both peers in `tearDown`.
- [ ] **Step 2: Add adapter concurrency regression method** `testTargetAdapterStaleStateRaisesConcurrencyAndRollsBack`: real adapter transactional closure with monitored rollback, locked State/organization mismatch from trusted snapshot, exact `ConcurrencyException` operation context `lifecycle.state_change`, no target state persistence and no success audit. Rerun a control success mutation to prove locks and rollback do not permanently poison the DB connection.
- [ ] **Step 3: Run** `php vendor/bin/phpunit --no-coverage tests/database/BeReorg009LifecycleLockTest.php` under exclusive guarded test DB; expected 2 PASS. If either fails, fix **only** test harness assumptions or report a confirmed behavior regression for the canonical migration; never weaken the lock/transaction semantics. Rerun inherited `BeReorg001FoundationLockTest` + relevant BE003 lifecycle gates.
- [ ] **Step 4: Review leaked sessions, `transStatus` reset and lock evidence; `php -l`, `git diff --check`, test-only commit**. Suggested message: `test(be-reorg-009): prove lifecycle locking and stale state rollback`.

### Task 7: BE009 Guarded Runner and Exact Inventory Gate

**Files:**
- Create: `backend/tools/test-workflow-lifecycle.php`
- Test inputs: exactly the **seven** BE009 test files and **32** method strings in File Map; inherited `backend/tools/test-authorization-decision.php` remains unmodified

**Interfaces:** `be009RequiredMethods(): array<class-string,list<string>>`, `be009Preflight(string $backend, array $required): void`, `be009AssertJUnit(string $junit, array $required): void`, `be009Run(array $argv): int`; names are internal to runner. The CLI accepts only `--help` or `--confirm-test-db`. Missing/duplicate/wrong method, skipped/failure/error, truncated/stale JUnit, unmet inherited gates, missing child PHPStan completion or unsafe DB => BLOCKED (`2`). Child exit `1` for inherited PHPStan diagnostics is **retained** and logged distinctly from PHPUnit failures; only exact identity comparison against accepted BE008 PHPStan evidence permits release acceptance.

- [ ] **Step 1: Add no-DB preflight and reject invalid CLI calls**; `php tools/test-workflow-lifecycle.php --help` exits `0` with safe instructions, `php tools/test-workflow-lifecycle.php --unsafe` and no argument exit `2` **without launching any DB process**. Verify `proc_open` and DOM extension are available before DB work.
- [ ] **Step 2: Pin exact method inventory, not counts alone**: scan seven declared classes/files for exactly 5+6+4+6+7+2+2 unique public `test...` methods matching File Map; synthetic in-memory/fresh temp JUnit with a removed, renamed, duplicate, skipped, failed or errored testcase must be rejected by `be009AssertJUnit`. Also require no skips anywhere in the full-suite report. Do not use a stale JUnit as evidence.
- [ ] **Step 3: Launch child exactly once** with `proc_open([PHP_BINARY,'tools/test-authorization-decision.php','--confirm-test-db'],..., $backend)`, stream output while capturing exit; **never** `require` it or start a second PHPUnit. Confirm child's `PHPUnit exit code: 0` and all inherited Foundation/BE001–BE008 coverage lines in its fresh log/JUnit, including 26 BE008 exact cases; check fresh JUnit full test count `948` when checkpoint unchanged and no failures/errors/skips.
- [ ] **Step 4: Preserve fresh JUnit separately** as `backend/build/logs/be-reorg-009.xml`: delete stale BE009 artifact *before* child run, verify the child regenerates `build/logs/be-reorg-001a.xml`, copy only after validation (and do not edit the inherited artifact). Append BE009 summary to `../test-result.log` without erasing the child's record. Log actual child exit/`PHPStan exit code`; raw exit `1` returns `1` even when PHPUnit and inventory PASS. Never manufacture PHPStan 0 or hide an unexpected count/new identity.
- [ ] **Step 5: Run synthetic runner checks, PHP lint and `git diff --check`** in source-only environment. On Windows run `php tools/test-workflow-lifecycle.php --confirm-test-db` under the existing single-process guard; expect PHPUnit 948/948 with required JUnit methods, raw PHPStan code per actual output. Record complete `test-result.log`, new JUnit and normalized PHPStan identities. Commit only runner code once preflight and synthetic JUnit checks PASS. Suggested message: `test(be-reorg-009): add guarded lifecycle acceptance runner`.

### Task 8: Full Verification, Independent Review and Incremental Patch

**Files:** no further production additions. Outputs: `docs/p1/` approved spec+plan, exactly five new/two modified production files, seven test files, allowed test-only fixture, guarded runner, release manifest/verification evidence and one incremental patch.

**Interfaces:** Artifact is an incremental patch to **actual BE008 + FIX01 accepted Git tree**; no previous BE009 spec-only/doc patch is implicitly reapplied. Verification release includes immutable base SHA, changed-file allowlist, SHA-256 patch and reverse proof.

- [ ] **Step 1: Run static checks**: `php -l` on all 7 changed/new production PHP, 7 new test PHP, fixture and runner; `git diff --check`; list every touched path. Compare exact original public signatures/constants via Reflection snapshots, ensure no edits to frozen files or PHPStan/CI configs. No `__call`, DB connection change, duplicate SQL/orchestration helpers or forbidden import cycles.
- [ ] **Step 2: Focused regression gates**: run seven BE009 class files, inherited `P1Wf001LifecycleRepositoryTest` **6**, `P1Wf002LifecycleServiceTest` **8**, `P1Wf007NativeStateContractTest`, real DB lock/HTTP/adapters, BE008 **26**, BE007 **24** and PBAC **91**; all zero skipped/errors/failures. Run focused steps serially because they share `erp_test`.
- [ ] **Step 3: Run full acceptance** `php tools/test-workflow-lifecycle.php --confirm-test-db` on Windows and retain complete `D:\XAMPP\htdocs\erp\test-result.log`, `backend/build/logs/be-reorg-009.xml`, PHPUnit exit and raw PHPStan exit. Enforce **948 total** (916+32) if current inventory unchanged, all 32 exact names, no `skipped`, no failures/errors. Verify Foundation guard 17 PASS and all inherited coverage. Existing raw PHPStan 1 does **not** mean 0 diagnostics; normalize path relocation and compare **identifier/message/source meaning** for every diagnostic against frozen 121 BE008 entries, not just counts. Any newly introduced diagnostic is a BLOCKER.
- [ ] **Step 4: Independent review** on touched files for constructor/named-argument parity, `FOR UPDATE`, transaction/rollback, adapter registry same instance, authorization audit and fail-closed order, no SQL/policy regressions, no disabled test, no unapproved scope. Resolve findings with focused reruns; retain review record.
- [ ] **Step 5: Build release patch** from recorded immutable BE008+FIX01 base SHA to BE009 source changes; exclude local logs, `.env`, vendor, caches, `test-result.log`, and unrelated edits. In a fresh checkout of that **exact base**, run `git apply --check`, apply, compare all changed file bytes, reverse and compare against original base exactly; calculate `Get-FileHash -Algorithm SHA256` (Windows) or `sha256sum` (POSIX). Do not claim byte-exact apply against `erp_v1.1.6.zip` as proof of compatibility with BE008+FIX01.
- [ ] **Step 6: Publish** patch, file manifest, SHA-256, verification evidence, apply/reverse instructions and status **`BE-REORG-009 CANDIDATE`**. Declare **`ACCEPTED`** only after project owner has reviewed the Windows full-suite/JUnit/PHPStan diagnostic parity and approved the final checkpoint. Commit/label only the verified checkpoint, not hypothetical source state.

## Acceptance Matrix

| Gate | Required evidence / threshold |
|---|---|
| Baseline provenance | Actual BE008+FIX01 Git SHA, Reflection inventory, 121-diagnostic normalized reference |
| Scope | Exactly 5 new + 2 modified production files; no frozen external file modified |
| Legacy service | Final; 7 constructor parameters, 8 constants, 5 public methods; positional/named calls unchanged |
| Legacy repository | Final; 1 constructor parameter, 7 constants, 39 public methods; aliases and transaction forwarded |
| Canonical boundaries | ApplicationPort exactly 5, RepositoryPort exactly 9, stateless factory, no reverse facade imports |
| New BE009 tests | Exactly **32** (5 boundary, 6 compat, 4 factory, 6 repository, 7 application, 2 HTTP, 2 lock); fresh JUnit exact names; zero skipped |
| Inherited tests | BE008 26, BE007 24, PBAC 91, BE001–006 and 14 P1 Lifecycle core PASS |
| Full PHPUnit | **948 tests** (assuming unchanged 916 baseline), zero failures/errors and no required skips; assertions measured, not assumed |
| DB/HTTP | Foundation guard 17, real InnoDB lock error 1205, request/CSRF/authorization/audit parity |
| PHPStan | Raw exit recorded honestly; **no new diagnostic identities** beyond accepted 121 baseline, even if raw exit remains 1 |
| Quality | All changed/new PHP lint PASS; `git diff --check` PASS; independent reviewer signs off |
| Delivery | Incremental patch from actual BE008+FIX01 SHA; clean apply/reverse; SHA-256; clear rollback and CANDIDATE/ACCEPTED gates |

## Recommended Execution / Handoff

**Proposed execution method:** Native, consistent with the project's BE007/BE008 workflow and the sequential dependency of port → SQL owner → application owner → factory/facades → HTTP/locks → runner. The design and *this plan* must be reviewed/approved before Task 1 production implementation. In Native mode use **superpowers:executing-plans**, commit per independently verifiable task and request an independent whole-branch review at the end. A subagent-driven alternative requires the same plan and separate review of each task.

**Not yet done:** no production changes for BE009, no 32 BE009 tests written, no BE009 runner implemented, no BE009 acceptance run and **no Git commit on the user's Windows repository** from this planning environment. A doc-only plan artifact/patch is not the BE009 production incremental patch.
