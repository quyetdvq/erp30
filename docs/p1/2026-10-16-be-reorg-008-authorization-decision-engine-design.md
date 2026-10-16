# BE-REORG-008 — Authorization Decision Engine Design

**Date:** 2026-10-16
**Status:** Written specification for review; implementation not authorized yet
**Selected approach:** A — relocate the existing PBAC decision pipeline into a canonical Decision module with a composition-based legacy facade
**Input checkpoint:** Accepted **BE-REORG-007 + FIX01 + FIX02 + FIX03**
**Accepted Windows baseline:** PHPUnit **890/890 tests**, **8,554 assertions**, no skipped required cases; PHPStan **121 inherited diagnostics**, exit code **1**, **zero new diagnostics**. PHPStan diagnostic identity is compared after accounting for explicitly documented source relocation.

## 1. Intent, definition of done, and boundaries

BE008 completes the **application decision boundary** following BE007's Authorization Administration relocation. It changes **ownership and wiring**, not authorization semantics. The canonical class owns the existing `authorize()` flow and the short-lived `withReadOnlyBatch()` context; existing consumers continue constructing `App\Authorization\AuthorizationService` using the same positional/named arguments, interfaces, constants, return DTOs, and exception contracts.

Definition of done: the canonical decision implementation is physically under `App\Modules\Authorization\Decision`; the legacy service remains a final composition facade; existing consumers and DTO/port signatures remain unmodified; all PBAC decisions and read-only query behavior match the BE007_FIX03 baseline; the full backend tests and the normalized PHPStan diagnostic set meet acceptance.

**Explicit non-goals:** Rewriting the PBAC algorithm, changing the policy precedence model, simplifying authorization failure handling, changing SQL list filters, moving the entire Authorization namespace, adding HTTP endpoints, replacing Shield, changing database schema/migrations, introducing a process-wide cache, or refactoring unrelated services.

## 2. Verified current source (BE007_FIX03)

| Source | Current responsibility / frozen contract |
|---|---|
| `backend/app/Authorization/AuthorizationService.php` | `final` class implements `App\Services\LifecycleAuthorizationPort` and `App\Services\NumberingAuthorizationPort`; 15 public constants; 10 optional constructor arguments; two public operations `authorize()` and `withReadOnlyBatch()`. Its 498-line body owns PBAC rules, collaborator defaults, and the per-instance read-only batch pointer. |
| `backend/app/Authorization/ReadOnlyAuthorizationContext.php` | Final, short-lived memoization class. `matches()` checks actor, request ID, system actor, and exact evaluation instant; caches values including null, never exceptions, with 256 keys per bucket and 256 policy rows per key. |
| `backend/app/Authorization/AuthorizationDecision.php` | Final readonly response DTO, `isAllowed()`, `toArray()`, normalized permission source/policy/assignment IDs. |
| `backend/app/Authorization/EvaluationOrganizationRequest.php` | Existing caller request DTO; carries persisted target, input, client organization hint, evaluation time. |
| `backend/app/Infrastructure/Mutation/OperationContext.php` | Existing actor/time/request/system context and trusted audit fields. |
| `backend/app/Repositories/PolicyRepository.php` | Legacy facade now delegates to BE007 canonical `SqlPolicyRepository`; decision reads must continue via the same public API. |
| `backend/app/Modules/Authorization/Administration/` | BE007 Administration module. Entire subtree remains unchanged by BE008. |
| `backend/app/Bootstrap/AuthorizationAdministrationFactory.php` | Existing BE007 composition root; remains unchanged. |
| `backend/app/Services/LifecycleAuthorizationPort.php` | Existing interface: `authorize(OperationContext, string, string, EvaluationOrganizationRequest, mixed = null): AuthorizationDecision`. |
| `backend/app/Services/NumberingAuthorizationPort.php` and `backend/app/Modules/Numbering/Authorization/Application/Ports/NumberingAuthorizationPortInterface.php` | Existing narrow numbering authorization ports with the same `authorize()` signature. |
| `backend/tools/test-authorization-administration.php` | Guarded full-suite BE007 runner; inventories 24 BE007 cases, 91 pre-existing PBAC authority cases, and earlier checkpoint gates. |

### 2.1 Legacy public constructor — exact positional and named-argument contract

The facade must retain **all ten** optional parameters in this order, with their current names, types, and `null` defaults:

```php
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
)
```

`App\Authorization\AuthorizationService` stays **final**; inheritance-based compatibility is forbidden. Its **15 constants**, visibility, names, and literal values stay accessible and identical:

| Constant family | Names |
|---|---|
| Decision | `DECISION_ALLOW`, `DECISION_DENY` |
| Reasons | `REASON_NO_BASE_PERMISSION`, `REASON_EMPLOYEE_INELIGIBLE`, `REASON_NO_EFFECTIVE_ASSIGNMENT`, `REASON_OUT_OF_ASSIGNMENT_SCOPE`, `REASON_POLICY_DENIED`, `REASON_ABSOLUTE_DENY`, `REASON_NO_MATCH`, `REASON_POLICY_ALLOWED`, `REASON_RESOURCE_INACTIVE`, `REASON_CONTEXT_REQUIRED` |
| Errors | `ERROR_RESOURCE_INVALID`, `ERROR_ACTION_INVALID`, `ERROR_POLICY_INVALID` |

The two public methods remain:

```php
public function authorize(
    OperationContext $operation,
    string $resourceType,
    string $action,
    EvaluationOrganizationRequest $contextRequest,
    mixed $stateId = null,
): AuthorizationDecision;

/** @template T @param callable(): T $read @return T */
public function withReadOnlyBatch(OperationContext $operation, callable $read): mixed;
```

`AuthorizationDecision` and `EvaluationOrganizationRequest` keep their existing namespaces, constructors, properties, method behavior and serialization shape. The facade preserves both old ports (`LifecycleAuthorizationPort`, `NumberingAuthorizationPort`) without any edits to those interfaces.

## 3. Proposed canonical layout and dependency direction

```text
backend/app/
├── Authorization/
│   ├── AuthorizationService.php                # legacy final composition facade
│   ├── AuthorizationDecision.php               # unchanged public DTO
│   ├── EvaluationOrganizationRequest.php       # unchanged public DTO
│   └── ReadOnlyAuthorizationContext.php        # unchanged ephemeral memoizer
├── Bootstrap/
│   ├── AuthorizationAdministrationFactory.php  # unchanged BE007 root
│   └── AuthorizationDecisionFactory.php        # new stateless decision root
└── Modules/Authorization/
    ├── Administration/                         # unchanged BE007 module
    └── Decision/
        └── Application/
            ├── Ports/
            │   └── PbacDecisionPort.php         # new canonical two-operation contract
            └── PbacDecisionEngine.php           # canonical PBAC implementation
```

**Canonical ownership:** `App\Modules\Authorization\Decision\Application\PbacDecisionEngine` owns the existing authorization decision pipeline, policy/lane matching and reduction, trusted context/evidence creation, and read-only batch lifetime. The private helper methods move with the engine. No new domain-specific algorithm is introduced.

**Direction:** API/controllers, Product, Numbering, Lifecycle, Settings and Administration services continue depending on the **existing facade** and public ports; the facade depends on `PbacDecisionPort`; the factory builds the canonical engine; the engine depends on existing domain collaborators and the BE007 legacy `PolicyRepository` (which itself delegates to canonical SQL persistence). The engine **must not depend on the facade**, API030 controllers, HTTP request globals, or Authorization Administration application services. Avoid cycles: canonical implementation owns its constant values; the legacy facade can forward/alias values, not vice versa.

**Canonical port:** `PbacDecisionPort` declares precisely `authorize()` and `withReadOnlyBatch()` with the signatures and generic callback contract in §2.1. Keep the port independent of controllers/HTTP, persistence implementations, and DI containers. The engine implements this port; the existing `AuthorizationService` continues implementing the two legacy ports and delegates both public operations to **one owned port instance**.

### 3.1 Factory and construction ownership

`App\Bootstrap\AuthorizationDecisionFactory` is a stateless final composition root with no shared/global engine instance. Its `create(...)` method accepts the exact ten constructor dependencies from §2.1, with unchanged names/types/defaults, and returns a fresh `PbacDecisionPort` backed by the canonical `PbacDecisionEngine`.

The facade constructor forwards the **same actual objects** to the factory in named-argument form and saves the result in a private readonly/immutable engine reference. The factory/engine resolves `$db ?? db_connect()` **once per constructed engine**, then constructs each missing collaborator against that same connection, matching the existing `AuthorizationService` initialization semantics. Any supplied collaborator is preserved by identity; do not silently rebuild it or derive a new database connection from it. Mixed supplied/default collaborator cases must be covered, as BE007 and earlier tests rely on them.

No factory auto-discovery, process static decision cache, shared service locator instance, new `engine` parameter on the public facade constructor, second engine per method, or implicit fallback that returns ALLOW is permitted. Existing `AuthorizationAdministrationFactory` is not changed and does not recursively construct the Decision factory.

### 3.2 Batch ownership and closure identity

`withReadOnlyBatch($operation, $read)` and `authorize(...)` **must always delegate to the same engine instance** owned by one facade. The callback is executed unchanged: existing callbacks invoke `$service->authorize(...)` on the legacy facade; those nested calls must hit the engine's active batch context. The canonical engine, not the facade, owns `?ReadOnlyAuthorizationContext` and its `try/finally` lifetime.

Preserve the exact legacy behavior:

1. Nested `withReadOnlyBatch` on one engine throws `LogicException('Nested authorization read batches are not supported.')`.
2. Cache usage is opt-in for `view` and only when `ReadOnlyAuthorizationContext::matches($operation)` is true; a different actor, request, system flag or exact evaluation instant bypasses the cache.
3. Non-`view` authorization clears any currently active read-only batch before proceeding, even if that action later denies or throws.
4. The batch is discarded in `finally` on both success and exceptions; subsequent calls cannot reuse stale data.
5. `ReadOnlyAuthorizationContext` still caches null/empty results but never exceptions or final `AuthorizationDecision` objects, with its existing memory limits. No cross-request, thread, process or service-global memoization.
6. For repeated `view` decisions in the same batch, the existing SQL query reduction and **identical** `AuthorizationDecision::toArray()` evidence are required.

The existing `ReadOnlyAuthorizationContext.php` stays byte-unchanged in BE008; moving that public final class would require a separate compatibility design.

## 4. Business and security invariants (zero behavior change)

### 4.1 Input/exception precedence and context

- Reject a missing actor user ID using the same `AUTH_CONTEXT_REQUIRED` path even for the existing `OperationContext` system actor shape. Preserve strict positive ID handling and resource/action token normalization; legacy `contact` resource stays retired (`CONTACT_LEGACY_RESOURCE_RETIRED`).
- Resolve the **resource** and trusted persisted workflow state before performing a decision. Continue using `ResourceWorkflowStateResolver::stateForAuthorization`, then `EvaluationOrganizationResolver::resolveResource` with the operation's authoritative `evaluationTime`, not untrusted client state/organization overrides.
- A resource not `ACTIVE` denies its normal actions, except the existing `resource.setstate` recovery route that still requires ordinary PBAC checks. Preserve ordering of validation, context resolution, authorization and exception conversion.
- Preserve existing typed `DomainException`, `PersistenceException`, `ConcurrencyException` propagation and reason/error codes; database failures must **not** be mapped to an authorization allow or fake not-found.

### 4.2 Mandatory base permissions and employment eligibility

- No matching Shield-derived base resource/action permission → `DENY / AUTH_NO_BASE_PERMISSION`, independent of matching ALLOW policies; continue validating permission `resource_action_id` belongs to its resource.
- Require an `ACTIVE` employee record and at least one effective employee assignment at the operation's evaluation instant. `SYSTEM_ADMIN` does not bypass this eligibility.
- `SYSTEM` and `MASTER` resource domains accept a valid effective assignment from anywhere in the Group. `ORGANIZATION` domain requires an assignment whose organization belongs to the evaluation organization's **ancestor chain** (self included). Child assignments never authorize ancestors/siblings. Preserve all mismatch and ineligible reason codes.

### 4.3 Policy lanes, inheritance and evidence

- Keep Policy repository filtering by active policy status, resource ID, action ID, ancestor organization IDs and trusted state (`NULL` = all states); its SQL batching/order semantics remain untouched.
- Participant vocabulary remains `USER`, `GROUP`, `DEPARTMENT`, `POSITION`, `ORGANIZATION`. Actor lane includes user and active authorization groups; each eligible assignment adds an independent department/position lane; organization policies appear as common matches in each lane.
- A matching `ABSOLUTE_DENY` in **any** lane denies the decision. Otherwise, within a lane `ALLOW` beats ordinary `DENY`; overall denial occurs only when **all** actor/assignment lanes deny. This intersection/precedence must remain byte-semantic parity with the original private `evaluateLane()` logic.
- Normalize matched, decisive and assignment ID arrays to unique ascending integer lists. Preserve the `base_permission_source`, `reason_code`, `evaluation_organization_id`, `state_id`, UTC `evaluation_time`, and `request_id` evidence in the existing DTO. No audit log writes inside read-only authorization decisions; existing audit callers remain authoritative.
- Unknown policy action effect raises `AUTH_POLICY_INVALID`, not a best-effort allow/deny. Keep all business reason strings, including `AUTH_NO_MATCH_ALLOW` and `AUTH_RESOURCE_INACTIVE`, unchanged.

## 5. Explicit change scope

### 5.1 Create/change only as necessary

- **Create:** `backend/app/Modules/Authorization/Decision/Application/Ports/PbacDecisionPort.php`.
- **Create:** `backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php`.
- **Create:** `backend/app/Bootstrap/AuthorizationDecisionFactory.php`.
- **Modify:** `backend/app/Authorization/AuthorizationService.php` to a final compatibility facade with identical existing public reflection shape and constant values.
- **Create:** bounded BE008 boundary, compatibility, factory, decision parity and in-process HTTP tests; choose exact count after method/fixture inventory during planning.
- **Create:** `backend/tools/test-authorization-decision.php`, a guarded wrapper based on BE007 runner; the full suite must execute once with all BE001–BE007 gates plus BE008 gates, followed by PHPStan only after PHPUnit succeeds.

### 5.2 Must not change

- `backend/app/Authorization/AuthorizationDecision.php`, `EvaluationOrganizationRequest.php`, `ReadOnlyAuthorizationContext.php`, `EffectiveProfileResolver.php`, `EvaluationOrganizationResolver.php`, `ShieldIdentityAdapter.php`, and authorization scope/effective bucket helpers.
- `backend/app/Services/LifecycleAuthorizationPort.php`, `NumberingAuthorizationPort.php`, and Numbering's canonical port.
- Entire `backend/app/Modules/Authorization/Administration/` and `backend/app/Bootstrap/AuthorizationAdministrationFactory.php` trees.
- Product, Numbering, Settings, Organization, Employee, Contact, Pricing, Import, Workflow/Api030 services/controllers/routes, database models, migrations, schema and frontend.
- Existing BE001–BE007 tests, existing foundation test runner and PHPStan configuration/baseline; existing read-only snapshot and audit services stay unchanged.

If the actual migration of `AuthorizationService` would force edits outside this list, stop and re-evaluate the boundary rather than silently broadening BE008.

## 6. Test design, safety guards and acceptance

### 6.1 Existing behavioral authority

The BE007 runner already inventories **91 existing PBAC authority cases** across 13 test families, including `P1Auth002AuthorizationServiceTest`, `P1Auth003AuthorizationDecisionTest`, `P1Auth004AssignmentEligibilityTest`, `P1AuthorizationMandatoryRegressionTest`, `P1Perf004ReadOnlyAuthorizationContextTest`, `P1Auth005SecurityAdministrationTest`, real-policy SQL and seed tests. Retain all 24 BE007 module tests and every earlier BE001–BE006 gate, along with the rest of the full suite.

Other relevant existing test families include `P1Wf006StateAwarePbacAndLocationTransitionsTest`, `P1Wf007NativeStateContractTest`, `P1ContactAuth001OwnerAwareAuthorizationTest`, Product/PriceList authorized buckets, Numbering, and the real HTTP Security Administration cases. They must not lose coverage merely because they are outside the named 91-case inventory.

### 6.2 BE008-specific tests to add

1. **Boundary:** file/namespace/port visibility and exact method signatures; engine implementation must be in Decision module, not Administration; facade must be at its historical path and use composition, without embedding the PBAC lane algorithm or API/DB-specific new behavior.
2. **Legacy compatibility:** reflect the ten constructor parameters, defaults, order and types; all 15 public constant values; both legacy interfaces; two public methods and DTO serialization; positional and named-argument instantiation.
3. **Factory/DI:** supplied collaborators retain identity; defaults share the supplied database connection; absent database uses a single default connection for that engine; each factory call returns an independent engine; no recursive factories or implicit rights grant.
4. **Decision parity:** run legacy facade and canonical engine against equivalent live test-DB fixtures for base-permission absent, eligible/no-match ALLOW, nested organization scope, assignment-lane intersection, resource inactive/setstate, `ALLOW`, `DENY`, `ABSOLUTE_DENY`, malformed effects and trusted-state mismatch. Compare complete decision `toArray()` and typed exception codes/fields, not only boolean allow/deny.
5. **Batch isolation/performance:** repeated `view` reduces policy action queries, different operation context bypasses the snapshot, nested batch throws, read callback exception clears it, non-view decision invalidates it, and the callback invoking the facade shares its engine. Assert no final decision cache across calls.
6. **Real HTTP:** exercise one approved and one denied route using real controller/filter/service/database flow. Reuse established authentication + effective employee assignment fixture; attach a CSRF token for every write verb, including empty-body DELETE; honor flat `meta` and 204 body line-ending behavior. Do not create INACTIVE policies that leak across `DatabaseTestTrait` migration rollbacks: track/restore only BE008-owned policy fixtures in `tearDown()` and use distinct fixture IDs/codes.
7. **Fail-closed failures:** missing actor, invalid resource/action, missing trusted target, persistence failure, invalid effect and inactive resource must not be swallowed or converted into ALLOW. Keep existing legacy error identities.

Do not add a fixed number of trivial tests to meet an arbitrary total. The implementation plan must inventory exact BE008 case names/counts and the established 91-case PBAC list before finalizing the runner.

### 6.3 Test DB rules and runner

- Only target disposable `127.0.0.1:3306/erp_test`, prefix `dinktech_`. Require explicit `--confirm-test-db` and preserve the accepted Foundation 17-check guard.
- Do not run two PHPUnit processes simultaneously against the test DB. A focused test must use `--no-coverage` when no code-coverage driver is installed; report PHPUnit's actual exit code and warnings separately, rather than mistaking a passing assertion for a clean process exit.
- One *full* PHPUnit process under the guarded BE008 runner; run one PHPStan process **after** PHPUnit exits 0. Preserve every prior coverage gate; no skipped cases in mandatory gates.
- Fixture teardown must not leave `INACTIVE` rows in `policies`; the native migration rollback guard is intentional and **must not be weakened**. No destructive global database reset or blanket `UPDATE policies SET status = 'ACTIVE'` to mask fixture contamination.
- Verify on user Windows PHP 8.3.30, CodeIgniter 4, MariaDB/MySQL `erp_test`, and PHPUnit 10.5.65. Local PHP lint/static probes and patch round-trip verification do not replace user Windows PHPUnit and PHPStan evidence.

### 6.4 Acceptance and PHPStan diagnostic relocation

| Gate | Requirement |
|---|---|
| Pre-change baseline | BE007_FIX03: **890 tests / 8,554 assertions**, 0 full-suite failures/errors, all mandatory coverage gates executed |
| Post-change full PHPUnit | **890 + actual BE008 cases**, 0 failures, 0 errors, no skipped mandatory tests |
| Authorization authority | All **91** existing PBAC tests executed plus all BE007 **24** tests, and all BE008-new tests |
| Decision API and audit | Identical decisions, reasons, IDs, evaluation timestamps, request IDs, HTTP/JSON statuses and exception codes |
| Snapshot | Same one-engine batch context, invalidation and SQL call-count/performance invariants |
| PHPStan | **121 inherited errors** with **0 new diagnostics**. Normalize the single existing `AuthorizationService::loadPolicies` list-typing diagnostic when it moves to canonical `PbacDecisionEngine`; BE007's existing `SqlPolicyRepository::affectedRows()` relocation remains inherited. Compare every other diagnostic by path, identifier, message and relevant line, not count alone. PHPStan exit code 1 is reported honestly. |
| Static/verifiability | PHP lint all touched PHP files; `git diff --check`; reflection signature parity; diff-based out-of-scope file integrity; patch `git apply --check`/apply/reverse byte parity from **BE007_FIX03** |
| Delivery | Separate spec, approved implementation plan, incremental patch and package, Windows runtime log, no implicit reapplication of earlier BE patches |

This is **backend in-process HTTP and real test-DB validation**, not browser/Apache UAT.

## 7. Main risks and required mitigation

1. **Two decision engines accidentally built by one facade:** the callback calls `$service->authorize` while `$service->withReadOnlyBatch` is active; both methods must hit the same stored canonical engine. Include query-count and mutation-invalidation tests.
2. **Named constructor argument and constant drift:** ten optional parameters and fifteen public constants are externally consumed. Reflection-test exact shape and construct with both positional and named dependencies.
3. **Authorization semantics drift in a mechanical relocation:** use old `authorize`, `loadPolicies`, `matching`, `evaluateLane`, `decision` bodies as parity authority; test entire decision evidence and exceptions, not only `isAllowed()`.
4. **Untrusted state/organization or eligibility bypass:** preserve trusted state and root-organization resolution ordering, inherited scope, SYSTEM/MASTER exception, employment eligibility, and ABSOLUTE_DENY priority. No shortcut permission behavior in the factory.
5. **Failed test leaves DB migration rollback blocked:** implement fixture teardown before expanding the suite; run focused tests serially, then full suite on confirmed `erp_test`; never loosen migration safety.
6. **Moved PHPStan diagnostic appears "new" under another path:** record exact original `AuthorizationService` message and identifier before execution, approve path-only relocation only after inspecting emitted line and message; all other diagnostics must remain unchanged.
7. **Accidental changes to BE007 Administration or caller signatures:** diff/byte-hash scope guard must explicitly include those source files, 91 authority tests and 24 BE007 tests.

## 8. Delivery process and approval gates

1. **Current stage — written-spec review.** Approval of approach A authorized preparing this spec, not implementation or implementation-plan creation.
2. After explicit approval of **this written spec**, create `docs/superpowers/plans/2026-10-16-be-reorg-008-authorization-decision-engine-implementation.md` with exact tasks, test-first steps, file signatures, verification commands, and expected native patch outputs; submit for review and execution-method selection.
3. Only after plan approval, implement in an isolated worktree, verify through RED→GREEN cycles, review the branch and generate a minimal **BE007_FIX03-relative** incremental patch.
4. Use the Windows guarded test runner and compare the actual PHPUnit + PHPStan evidence before accepting BE008. Subsequent regressions receive scoped `BE-REORG-008_FIXxx` patches, never a silent change to permissions or previous checkpoints.

**This document changes no production code.**
