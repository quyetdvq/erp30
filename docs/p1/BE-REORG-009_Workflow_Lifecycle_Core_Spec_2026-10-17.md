# BE-REORG-009 — Workflow / Lifecycle Core Architecture Design

**Date:** 2026-10-17

**Status:** DESIGN SPECIFICATION FOR USER REVIEW; implementation is **not** authorized

**Selected approach:** A — Workflow / Lifecycle Core relocation with composition-based legacy facades

**Input checkpoint:** Accepted BE-REORG-008 + FIX01 on the user's Windows working tree

**Accepted runtime baseline (user verified):** PHPUnit **916 tests / 8,848 assertions**, zero failures/errors; Foundation guard **17/17**; all inherited BE001–BE008 coverage gates; PHPStan **121 inherited diagnostics, 0 new** (identity parity confirmed by the project owner). PHPStan's nonzero raw exit code must continue to be reported honestly.

> **Source provenance:** Frozen signatures and code behavior below were checked against `erp_v1.1.6.zip`, including `backend/app/Services/LifecycleService.php`, `backend/app/Repositories/LifecycleRepository.php`, related ports/adapters, the as-built Resource State contract, P1 documentation, and the BE008 guarded runner. The ZIP does **not** include the subsequently accepted BE008 FIX01 test correction. Implementation and patch creation **must** be rebased on the actual Windows checkpoint **BE008 + FIX01**. No claim is made that the ZIP is that exact checkpoint.

## 1. Purpose and decision

Move the **ownership** of existing Lifecycle configuration validation, transition resolution, authorized state-change orchestration, and SQL lifecycle persistence into the canonical `App\Modules\Workflow\Lifecycle` module. Preserve every observable API, SQL, authorization, transaction, audit, concurrency, exception, and clock behavior. `App\Services\LifecycleService` and `App\Repositories\LifecycleRepository` remain **final, explicit composition facades**, not subclass aliases.

The user has approved **the scope and approach A**, not this written specification, its later implementation plan, or production changes. Approval of this document is the next gate.

**Definition of done:** (a) one canonical owner of Lifecycle application logic and SQL persistence, with no duplicate operational algorithms in legacy facades; (b) existing constructors/constants/public methods remain usable through positional and named arguments; (c) the current adapter and PBAC/Numbering/Workflow integration points remain unchanged; (d) existing 916 PHPUnit cases plus BE009 coverage pass on guarded Windows `erp_test`; (e) no new PHPStan diagnostic identities compared with the 121 accepted BE008 diagnostics; (f) release as an incremental patch against the actual BE008+FIX01 checkpoint.

**Explicit non-goals:** Workflow/Resource/State schema design; enforcing Lifecycle for unbound business Resources; removing native-state fallback; adding or changing a State/Transition; refactoring `Api030AuthorizationWorkflowService`, `BusinessWorkflowActions`, `BusinessLifecycleTransitions`, `ResourceWorkflowStateResolver`, PBAC/BE007/BE008, resource adapters, Numbering, Product, migrations, seeders, frontend, OpenAPI, routes, CSRF, or authorization policy semantics. Do not introduce a new global/singleton service or cache.

## 2. Verified as-is inventory and compatibility surface

### 2.1 Legacy Lifecycle service

`backend/app/Services/LifecycleService.php` is `final` and contains **8 public constants**:

| Constant | Literal value |
|---|---|
| `ERROR_LIFECYCLE_NOT_FOUND` | `WF_LIFECYCLE_NOT_FOUND` |
| `ERROR_INVALID_CONFIGURATION` | `WF_INVALID_LIFECYCLE_CONFIGURATION` |
| `ERROR_STATE_NOT_IN_LIFECYCLE` | `WF_STATE_NOT_IN_LIFECYCLE` |
| `ERROR_INVALID_TRANSITION` | `WF_INVALID_TRANSITION` |
| `ERROR_AUTHORIZATION_DENIED` | `AUTH_POLICY_DENIED` |
| `ERROR_TARGET_NOT_FOUND` | `WF_TARGET_NOT_FOUND` |
| `ERROR_TARGET_ADAPTER_UNAVAILABLE` | `WF_TARGET_ADAPTER_UNAVAILABLE` |
| `ERROR_ADAPTER_REGISTRATION_INVALID` | `WF_TARGET_ADAPTER_REGISTRATION_INVALID` |

Its **seven** constructor parameters must retain these **exact names, concrete types, order, defaults, and named-argument behavior**:

```php
public function __construct(
    ?LifecycleRepository $repository = null,
    ?LifecycleAuthorizationPort $authorization = null,
    ?LifecycleAuditPort $audit = null,
    array $adapters = [],
    ?ConnectionInterface $db = null,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
)
```

The five public methods, excluding constructor, are:

```php
public function registerTargetAdapter(LifecycleTargetAdapter $adapter): void;
public function validateConfiguration(int $lifecycleId): array;
public function configure(
    int $lifecycleId,
    array $states,
    array $transitions,
    ?OperationContext $operation = null,
): array;
public function resolveTransition(
    int $lifecycleId,
    int $currentStateId,
    string $actionCode,
): array;
public function execute(
    string $resourceType,
    mixed $targetId,
    string $actionCode,
    ?OperationContext $operation = null,
    ?int $expectedDestinationStateId = null,
): array;
```

Preserve PHPDoc array shapes, notably `validateConfiguration()`/`configure()` returning `{lifecycle,states,transitions}` and `execute()` returning `{decision:AuthorizationDecision,transition:array<string,mixed>,target:LifecycleTargetSnapshot}`. Keep the current error-code and error-context behavior, `DomainException` and `ConcurrencyException` classes, and timing/order of default `OperationContext` creation.

### 2.2 Legacy Lifecycle repository

`backend/app/Repositories/LifecycleRepository.php` is `final` and has **7 public constants**:

- `ERROR_PERSISTENCE_FAILED = LIFECYCLE_PERSISTENCE_FAILED`
- `ERROR_CONNECTION_INVALID = LIFECYCLE_CONNECTION_INVALID`
- `ERROR_UPDATE_EMPTY = LIFECYCLE_UPDATE_EMPTY`
- `ERROR_FIELD_UNSUPPORTED = LIFECYCLE_FIELD_UNSUPPORTED`
- `ERROR_FIELD_REQUIRED = LIFECYCLE_FIELD_REQUIRED`
- `ERROR_INVALID_ID = LIFECYCLE_INVALID_ID`
- `ERROR_MUTATION_FAILED = LIFECYCLE_MUTATION_FAILED`

Exact legacy constructor: `__construct(?ConnectionInterface $db = null)`. It has **39 public methods beyond constructor**, listed exhaustively in Appendix A. Alias methods such as `find`, `findByCode`, `insert`, `update`, `findForUpdate`, and `resolveTransition` must remain available; don't use `__call()` or inheritance to mask missing methods. The canonical repository continues to own the unchanged 39-method SQL surface, with a narrow application port for the subset consumed by the Lifecycle application service.

### 2.3 Existing contracts and consumers — unchanged

Keep byte-identical (unless the user explicitly approves an additional scope) these existing contracts:

- `App\Services\LifecycleTargetAdapter` — `resourceType`, `load`, `transactional`, `lockAndReload`, `mutate`.
- `App\Services\LifecycleTargetSnapshot` — same final readonly DTO, six constructor properties and trusted `authorizationTarget`.
- `App\Services\LifecycleAuthorizationPort` — `authorize(OperationContext, string, string, EvaluationOrganizationRequest, mixed = null): AuthorizationDecision`.
- `App\Services\LifecycleAuditPort` — `recordIfRequired(AuthorizationDecision): ?int`.

Existing consumer boundaries include `Api030AuthorizationWorkflowService`, `Api010FoundationOrganizationContactService`, `BusinessWorkflowActions`, `BusinessLifecycleTransitions`, `ResourceWorkflowStateResolver`, Foundation/Contact lifecycle adapters, and `AuthorizationService` (via its existing Lifecycle authorization port). Preserve direct `new LifecycleService(...)`/`new LifecycleRepository(...)` call sites and their concrete type-hint compatibility. No caller rewrite is required or allowed in the core migration.

## 3. Architecture — target ownership

```text
backend/app/
  Bootstrap/
    WorkflowLifecycleFactory.php                            [NEW, stateless]
  Modules/Workflow/Lifecycle/
    Application/
      Ports/
        LifecycleApplicationPort.php                       [NEW]
        LifecycleRepositoryPort.php                        [NEW, narrow]
      LifecycleApplicationService.php                       [NEW, canonical orchestration]
    Infrastructure/Persistence/
      SqlLifecycleRepository.php                            [NEW, canonical SQL]
  Services/
    LifecycleService.php                                    [MODIFY, legacy facade]
  Repositories/
    LifecycleRepository.php                                 [MODIFY, legacy facade]
```

- `LifecycleApplicationService` is the single canonical owner of **all existing** `LifecycleService` business validation/orchestration and private helper methods. It implements `LifecycleApplicationPort`, never calls the legacy `LifecycleService` and does not own resource-specific schemas. It declares the same eight error-code literals internally, and the legacy facade exposes those values without creating a reverse dependency.
- `SqlLifecycleRepository` is the single canonical owner of all current SQL, ordering, payload/ID/audit normalization, `FOR UPDATE`, and transaction behavior from `LifecycleRepository`. It implements `LifecycleRepositoryPort` while retaining the **complete** legacy public repository method set. Its seven error-code constants remain available for the compatibility facade to alias; do not reference legacy repository constants from the SQL implementation.
- `LifecycleService` holds **one instance** of `LifecycleApplicationPort` for its lifetime and delegates its five methods explicitly. In particular, `registerTargetAdapter` and subsequent `execute` must act on the **same** application instance and adapter registry.
- `LifecycleRepository` holds **one instance** of `SqlLifecycleRepository`; delegates all 39 methods explicitly, including `transactional`. Both the legacy wrapper and SQL class implement `LifecycleRepositoryPort`.
- `WorkflowLifecycleFactory` is a **stateless** composition root. It accepts the same seven optional dependencies, with unchanged parameter names, to construct the engine. The public legacy constructor remains unchanged and passes its arguments through. No global registry or shared singleton; no circular dependency (`canonical application -> legacy service` is forbidden).
- Existing adapters and DTOs remain in `App\Services` as deliberately stable integration contracts. Their relocation is **deferred**, not silently incorporated into BE009.

### 3.1 Application port (canonical-facing facade contract)

`App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleApplicationPort` exposes **exactly five operations**, with the signatures and PHPDoc shapes in §2.1. No HTTP, authorization evaluator implementation, or SQL dependency appears in the port itself. `LifecycleApplicationService` implements it.

### 3.2 Repository port — nine actual application operations

`App\Modules\Workflow\Lifecycle\Application\Ports\LifecycleRepositoryPort` exposes **only** the operations called by current `LifecycleService`:

```php
public function findLifecycle(mixed $id): ?array;
public function lockLifecycle(mixed $id): ?array;
public function findLifecycleStates(mixed $lifecycleId): array;
public function findTransitions(mixed $lifecycleId, ?string $status = null): array;
public function findActiveWorkflowState(mixed $id): ?array;
public function findActiveTransitionByAction(
    mixed $lifecycleId,
    mixed $fromStateId,
    string $actionCode,
): ?array;
public function insertLifecycleState(array $data, ?OperationContext $operation = null): int;
public function insertTransition(array $data, ?OperationContext $operation = null): int;
public function transactional(callable $callback, string $operation = 'transaction'): mixed;
```

Preserve the original generics, `@return list<array<string,mixed>>` for lists, `@param callable(): mixed` contract, and nullable row returns as they exist in source. Do **not** widen this port to all 39 repository methods: those remain on the legacy facade and canonical SQL class for existing consumers.

### 3.3 Connection and constructor ownership

Maintain the original `LifecycleService` rule: its `$db` defaults to `db_connect()` if omitted. When a caller supplies `$repository`, retain that **exact object** and its connection; do not rebuild/rebind it to `$db`. The application factory accepts the public concrete legacy `LifecycleRepository` only at the compatibility boundary and passes it as `LifecycleRepositoryPort` to the canonical application. When a caller supplies `$db` without a repository, all created default SQL repositories/authorization/audit collaborators must use this **same** connection. A supplied authorization, audit, mutation context or clock is not replaced or eagerly copied. Keep default authorization and audit dependencies **lazy**, as in the current `auth()` and `audit()` helpers. Keep `OperationContext` generation semantics and clock lookup as-is. A constructor must not query or mutate SQL just to assemble a factory.

The old `repo()` path currently constructs a fresh wrapper on demand when no repository is injected, but each such wrapper uses the **same service connection**. The new application implementation may construct a fresh canonical SQL repository on demand; it may not create a new database connection per call or accidentally switch transactions. Any memoization/instantiation change must be proven behavior-neutral rather than assumed.

## 4. Invariants — configuration, authorization, transaction, audit

### 4.1 Configuration and transition rules

1. Positive-ID normalization retains **exact** field/error mapping.
2. `validateConfiguration`: lifecycle must exist; workflow state membership must be unique; every member State must be `ACTIVE`; an `ACTIVE` Lifecycle requires **exactly one** initial member, while multiple final members remain allowed. Transition keys `(from_state_id, action_code)` are unique after current normalization, and source/destination States must both belong to the Lifecycle.
3. `configure` creates its `OperationContext` at the same point as current code; locks the Lifecycle row **before** reading existing member/transition rows; validates the combined existing+submitted batch; inserts all rows inside **one** repository transaction; validates the stored result within the transaction; commits only on success. Database unique keys remain final concurrency protection.
4. `resolveTransition` uses an `ACTIVE` Lifecycle, validated membership, normalized action code, active-only transition lookup, and an `ACTIVE` destination workflow-state definition. Preserve deterministic query ordering, error precedence and exact returned transition row.
5. No implicit State rewrite, lifecycle auto-attachment, new Transition defaults, or alternate case-insensitive lookup is permitted.

### 4.2 Authorized execution — exact sequence

Preserve the sequence below; moving a step across another step is a **behavior change**:

1. Resolve (or create) `OperationContext`; normalize Resource type; require a registered adapter.
2. Adapter `load(targetId, operation)` returns the trusted snapshot; verify that its Resource type agrees.
3. Resolve transition from **snapshot Lifecycle ID and persisted source State**; obtain catalog destination; reject a caller-provided mismatching `expectedDestinationStateId` before authorization.
4. Normalize action code; construct `EvaluationOrganizationRequest(persistedTarget: snapshot.authorizationTarget)`; call the supplied/default `LifecycleAuthorizationPort::authorize()` with the **persisted** source State ID.
5. On denial: call `LifecycleAuditPort::recordIfRequired(decision)` then throw `AUTH_POLICY_DENIED`, without mutating the target.
6. On allow: use **adapter-owned** `transactional()`, then `lockAndReload()`; require the locked snapshot to retain **resourceType, targetId, lifecycleId, currentStateId, evaluationOrganizationId** exactly; otherwise throw `ConcurrencyException('lifecycle.state_change')`.
7. Only then call `adapter->mutate(locked, expectedSourceStateId, catalogDestinationStateId, operation)` and audit **inside the transaction**. Preserve rollback behavior on exceptions.
8. Return the existing `{decision, transition, target}` structure with the **original authorized snapshot**, not a substituted caller payload or freshly loaded snapshot.

A missing adapter, missing Lifecycle/transition, invalid destination, authorization denial or stale locked snapshot is **fail-closed**. Never turn these failures into successful/no-op execution or bypass PBAC. Do not change permission action vocabulary or `setstate` and action gating at caller boundaries.

### 4.3 Persistence exactness

Copy the existing persistence implementation mechanically into `SqlLifecycleRepository`: SQL tables `lifecycles`, `workflow_states`, `lifecycle_states`, `lifecycle_transitions`; allowed/required fields, server-owned audit columns; positive-ID handling; `findMany` filters and row ordering; transition lookup ordering; `BaseResult`/false-query failure handling; `insertID`, update false/not-found distinctions; and typed `DomainException`/`PersistenceException` mapping.

Preserve `lockLifecycle`/`findLifecycleForUpdate` using `SELECT ... FOR UPDATE` on the **same supplied `BaseConnection`**. Preserve `transactional`'s `transBegin`, `transStatus`, `transCommit`, `transRollback`, `resetTransStatus`, and exception wrapping: existing `DomainException` escapes unchanged; other throwables inside the transaction are mapped to `LIFECYCLE_MUTATION_FAILED` with their cause; lower-level query failures retain `LIFECYCLE_PERSISTENCE_FAILED` where currently used. Do not claim protection against races not covered by current locks.

### 4.4 Resource State and legacy fallback

`ResourceNativeStateCatalog::INDEPENDENT_RESOURCES` remains the **exact ten** approved entries: `authorization_group`, `authorization_log`, `display_setting`, `employee_assignment`, `import_job`, `lifecycle`, `policy`, `user`, `workflow_state`, `resource`. All States-only resource semantics remain as-built.

The documented gap remains: some business Resources without `resources.lifecycle_id` still take the **legacy native fallback** path. BE009 must not erase that path, force `lifecycle_id NOT NULL`, change the seeded catalog, or add an eleventh exception. The planned migration/audit path in `docs/as-built/20_RESOURCE_STATE_LIFECYCLE_CONTRACT.md` is out of scope.

## 5. Freeze list and change controls

The following current production files/subtrees are **not** modified by this package:

```text
backend/app/Services/{LifecycleTargetAdapter,LifecycleTargetSnapshot,
  LifecycleAuthorizationPort,LifecycleAuditPort,ResourceWorkflowStateResolver,
  ResourceNativeStateCatalog,BusinessWorkflowActions,BusinessLifecycleTransitions,
  BusinessLifecycleTargetAdapter,ContactLifecycleAdapter,
  FoundationLocationLifecycleAdapter,Api030AuthorizationWorkflowService,
  Api010FoundationOrganizationContactService}.php
backend/app/Integration/Foundation/LocationLifecycleAdapter.php
backend/app/Authorization/*
backend/app/Modules/Authorization/*
backend/app/Modules/Numbering/*
backend/app/Config/{Routes,Services}.php
backend/app/Controllers/*
backend/app/Database/{Migrations,Seeds}/*
backend/app/Models/*
frontend/*
contracts/*
backend/phpstan.neon
backend/phpunit.dist.xml
```

Only two existing production files may be edited: `Services/LifecycleService.php` and `Repositories/LifecycleRepository.php`. Only the five new production files in §3 may be created. New tests/fixtures/one guarded runner/docs are permitted. Any out-of-scope production change is a **design blocker**, not implicit permission to broaden BE009.

Preserve `final`, constructors, public constant names/values, exact public method reflection contracts (names, types, defaults, parameter order and by-ref/variadic flags), method visibility, PHPDoc return shapes, exception identity/context, and named arguments. No `__call`, inheritance, `class_alias`, reflection dispatch, silent suppression, or broadening of public types to `mixed`. New internal port signatures may be as narrow as their consumers require, provided the legacy surface remains unchanged.

## 6. Verification and mandatory tests

**Baseline to protect:** 916 accepted PHPUnit tests (including BE008's 26 cases, BE007's 24, PBAC's 91, Foundation 17 guard and all earlier BE001–BE006 gates). Current source already has `P1Wf001LifecycleRepositoryTest` (**6 tests**) and `P1Wf002LifecycleServiceTest` (**8 tests**). These **14 existing core tests** must remain and pass unmodified. Preserve `P1Wf007NativeStateContractTest` and business/adapter regression tests as well.

**BE009 incremental target: 32 new, non-skipped test methods**, grouped as follows; implementation plan will assign exact unique names to each method, and the runner must enforce the inventory:

| Planned suite | Count | Required proof |
|---|---:|---|
| Boundary | 5 | Canonical SQL/application ownership, narrow ports, no legacy orchestration/SQL import cycle, unchanged external contracts |
| Compatibility | 6 | 7 service + 1 repo constructor contracts, 8+7 constants, 5+39 public methods, positional/named args, alias delegates and adapter registry continuity |
| Factory / connection | 4 | Stateless factory, supplied object identity, one DB across omitted dependencies, lazy defaults/operation context |
| Repository parity | 6 | Lifecycle/state/membership/transition CRUD, deterministic ordering, trusted audit, FOR UPDATE, rollback/error mapping and aliases |
| Lifecycle parity | 7 | Initial/final rules, combined batch atomicity, active-only resolution, PBAC deny and audit ordering, destination guard, exact snapshot and stale-state rejection |
| In-process HTTP | 2 | Existing API030 lifecycle configuration + business workflow transition/error/CSRF contract, no route or response changes |
| Real DB concurrency | 2 | Existing Lifecycle row lock with independent verified InnoDB sessions; target-adapter stale-snapshot/concurrency rollback behavior |
| **New cases** | **32** | Explicit methods, no placeholders, no skips |

**Full-suite target:** `916 + 32 = 948` tests, if no unrelated test inventory changes. Assertion count must be measured from actual runtime, not invented. Include live DB parity for an explicitly supplied legacy repository and canonical SQL instance against the same test connection. Include denied/no-write and audit-on-success/denial checks. Reuse existing test fixtures/HTTP helpers and the proven independent-session MySQL lock harness; assert true error code `1205` for lock-wait when used, not arbitrary timing failure or sleeps as proof. Do not add production test hooks.

**Guarded runner:** Add `backend/tools/test-workflow-lifecycle.php`. It must: accept only `--confirm-test-db` for DB execution (plus safe `--help`); invoke the existing `tools/test-authorization-decision.php` **once as a child CLI process**; preserve its `erp_test` 127.0.0.1:3306 / `dinktech_` configuration check and Foundation 17-check gate; never start a second concurrent full-suite process; use a fresh JUnit artifact; verify every new BE009 testcase by **exact method name**, class and non-skipped/no-error status; verify inherited 26 BE008 tests and other coverage gates. Do not falsely convert PHPStan raw exit code 1 into success. Don't modify BE007 or BE008 runners to weaken their guarantees.

**Acceptance:** PHP lint on all changed/new PHP; `git diff --check`; full PHPUnit **948/948** (subject to immutable source baseline) with zero failures/errors/skips on required gates; all 32 BE009 and prior 916 cases independently confirmed in fresh JUnit; no regressed API/CSRF/DB-lock evidence; no new PHPStan diagnostic **identities** beyond the accepted BE008 set of 121; `git apply --check`/apply/reverse exactness for the incremental patch; checksum verification; independent review of touched files. If PHPStan raw exit 1 with the same inherited 121 issues, explain it honestly and treat it as parity-accepted only after exact diagnostic comparison. Browser/Apache UAT is outside the automated backend runner and is not claimed.

## 7. Delivery order and release protection

1. Record the **actual** Git SHA and clean `git status` of the accepted BE008+FIX01 Windows checkpoint. Preserve/capture BE008 JUnit and normalized 121-diagnostic baseline for identity comparison.
2. Implement narrow ports and characterization/compatibility tests first (red), then move SQL without changing behavior (green), then move Lifecycle orchestration (green), then add stateless factory and two legacy facades. Do not edit current callers.
3. Run focused core/adapter/HTTP/concurrency tests and guarded full backend verification on disposable `erp_test` with **one worker** and explicit DB confirmation. Do not run another test process concurrently or reset a non-test database.
4. Compare public Reflection inventory and mechanical algorithm/SQL parity to the baseline. Analyze any inherited test that introspects private implementation state before making a **test-only** compatibility update; never re-add private implementation to the facade merely to satisfy old Reflection.
5. Publish a **single incremental patch** plus provenance/checksum, verification evidence and rollback instructions against **BE008+FIX01**; verify clean apply and reverse on that exact checkpoint. Never apply BE008/BE009 out of order.
6. Mark `CANDIDATE` until Windows runner evidence and PHPStan identity comparison satisfy every gate. Record `ACCEPTED` only after user review and verification. Create an independent Git checkpoint before the next REORG task.

## 8. Risks, known limitations and decision record

- **Preceding FIX01 baseline:** the source ZIP contains pre-FIX01 `BeReorg007AuthorizationFactoryTest.php`; do not generate a production patch directly from that stale test snapshot or accidentally include its reversion.
- **Legacy concrete injection:** `?LifecycleRepository $repository` must remain the public service constructor type even though the canonical application service uses `LifecycleRepositoryPort`; the factory is the type adapter. Replacing the public type with the port would break named/typed caller compatibility.
- **Authorization laziness:** moving eager collaborator setup into the legacy constructor could introduce new behavior; preserve the current lazy defaults and fail-closed checks.
- **Shared transaction connection:** a new SQL instance is fine only when it uses the existing same DB object; changing the connection/session breaks FOR UPDATE, commit, rollback, and audit invariants.
- **Scope boundary:** removing fallback or moving resource adapters may sound architecturally cleaner but would change behavior and is excluded from this approved approach.
- **Environment:** this source snapshot has no `backend/vendor` or Windows DB, so a source-only design review is **not** PHPUnit/PHPStan acceptance. The archive has no `.git` metadata; document creation here cannot honestly be reported as a Git commit.

**Alternatives considered and rejected:**

- **B — move only `ResourceWorkflowStateResolver`:** fails to establish a canonical Lifecycle configuration/persistence owner; crosses PBAC/native-state boundaries and has a broader behavior-change surface.
- **C — decompose `Api030AuthorizationWorkflowService` first:** 903-line multi-domain API facade, substantial HTTP and authorization coupling; more risk with less isolation of the core Workflow domain.
- **Subclass-based compatibility or global service locator:** disallowed because the legacy classes are `final`, constructors/ports are stable, and global state would change lifecycle adapter registration and transaction behavior.

**Next decision requested from user:** approve or request revisions to this **written Specification**. Only after spec approval may a detailed BE009 Implementation Plan be written; implementation requires a separate plan approval and execution-method decision.

## Appendix A — Complete legacy repository public-method inventory

The 39 methods below exclude `__construct`. All exact signatures/defaults and return types are sourced from `backend/app/Repositories/LifecycleRepository.php`; no alias may be dropped.

**Lifecycle master (17):** `find`, `findLifecycle`, `findByCode`, `findLifecycleByCode`, `findActive`, `findActiveLifecycle`, `findActiveByCode`, `findActiveLifecycleByCode`, `findLifecycles`, `insert`, `createLifecycle`, `insertLifecycle`, `update`, `updateLifecycle`, `findForUpdate`, `findLifecycleForUpdate`, `lockLifecycle`.

**Workflow State catalog (8):** `findWorkflowState`, `findWorkflowStateByCode`, `findActiveWorkflowState`, `findActiveWorkflowStateByCode`, `findWorkflowStates`, `createWorkflowState`, `insertWorkflowState`, `updateWorkflowState`.

**Lifecycle membership (5):** `findLifecycleState`, `findLifecycleStates`, `createLifecycleState`, `insertLifecycleState`, `updateLifecycleState`.

**Lifecycle transitions (8):** `findTransition`, `findTransitions`, `findTransitionByAction`, `findActiveTransitionByAction`, `resolveTransition`, `createTransition`, `insertTransition`, `updateTransition`.

**Transaction (1):** `transactional`.

## Appendix B — Exact source anchors reviewed

- `backend/app/Services/LifecycleService.php`: 8 constants at lines 25–32; constructor lines 44–67; public methods 69–270; configuration checks, normalization/default methods and repository/auth/audit factories at 272–366.
- `backend/app/Repositories/LifecycleRepository.php`: constants at 25–31; constructor 61–64; public CRUD at 69–423; `transactional` at 432–458; private persistence helpers through the end of file.
- `backend/app/Services/{LifecycleTargetAdapter,LifecycleTargetSnapshot,LifecycleAuthorizationPort,LifecycleAuditPort}.php`.
- `backend/app/Services/{BusinessWorkflowActions,BusinessLifecycleTransitions,ResourceWorkflowStateResolver,ResourceNativeStateCatalog}.php`.
- `backend/tests/unit/{P1Wf001LifecycleRepositoryTest,P1Wf002LifecycleServiceTest,P1Wf007NativeStateContractTest}.php` and `backend/tests/database/BeReorg001FoundationLockTest.php`.
- `docs/as-built/20_RESOURCE_STATE_LIFECYCLE_CONTRACT.md`, `docs/p1/2026-10-16-be-reorg-008-authorization-decision-engine-{design,implementation}.md`, `backend/tools/test-authorization-decision.php`.
