# BE-REORG-014L — Authorization ↔ EmployeeStructure PBAC read boundary

**Guidance:** `docs/DinkERP_Backend_System_Architecture_Standard_v1.1.1.md` §§2.3, 3.1, 5.3, 15.2, 17; `docs/DinkERP_Backend_Architecture_Blueprint_Lean_v1.2.2.md` §§4–5. These are proposed architecture standards, not approvals to alter the existing DB, Resource/Action or API contract.

**Patch state:** `SOURCE_VERIFIED / MARIA_DB_SHIELD_HTTP_PENDING`. No API route, payload, SQL predicate, permission, schema or migration changes. Only the Authorization decision engine read seam is refactored; other legacy Authorization context/owner resolvers are deliberately outside this slice.

## 1. Inventory and observed problem

| Element | Previous dependency | Correct boundary after 014L |
|---|---|---|
| `Modules/Authorization/Decision/Application/PbacDecisionEngine` | `App\Repositories\EmployeeRepository` AND `App\Services\EmployeeAssignmentService` (legacy CRUD-capable compatibility service) | `Ports/EmployeeScopeReadPort`: `findByUserId`, `findEffective` only |
| `Bootstrap/AuthorizationDecisionFactory` | Passed legacy employee repository/service into decision engine | Connects the Authorization port to EmployeeStructure-owned read methods on **one supplied DB connection** |
| Employee/Assignment persisted ownership | `EmployeeStructure` owns `employees` and `employee_assignments` | **Unchanged**: canonical EmployeeStructure repositories perform the same reads |
| `AuthorizationService` (legacy public facade) | Ten nullable positional/named collaborators | **Unchanged** ten-argument signature; injected `employees`/`assignments` are passed to the Bootstrap bridge for compatibility |
| `EvaluationOrganizationResolver` / `EmployeeContactScopeResolver` | Existing legacy repository dependencies, scope semantics | **Not modified**; inventoried as a separate potential follow-up, not silently broadened |

Before, the security-critical PBAC pipeline held a **mutable** `EmployeeAssignmentService` just to call its `findEffective` read. That service also exposes `create`, `update` and `end` and internally delegates through EmployeeStructure. This is both unnecessary coupling to the legacy facade and a potential runtime call-cycle risk if later modifications were to authorize its reads.

## 2. Minimal implementation

The consumer-owned `EmployeeScopeReadPort` has precisely two privileged **internal** read methods. `Bootstrap/AuthorizationEmployeeScopeReadAdapter` wires it to the existing `EmployeeStructureFactory` read repositories; the canonical decision engine never imports EmployeeStructure internals, Bootstrap, or the legacy mutable assignment service. The adapter calls only `EmployeeRepositoryInterface::findByUserId` and `EmployeeAssignmentRepositoryInterface::findEffective`; no generic `skipAuthorization` flag, query/command bus, new repository/SQL or event layer is introduced.

The existing compatibility constructor injection is supported **only in Bootstrap**: explicit legacy `EmployeeRepository` and `EmployeeAssignmentService` still work through the adapter. Default wiring uses canonical EmployeeStructure repositories on the same connection, which perform the same persisted SQL reads. The port intentionally exposes no write method to the decision engine. The adapter is not a public employee lookup/endpoint and must not be reused by other consumers without a review.

PBAC still performs employee **ACTIVE** checks, assignment emptiness and ancestor scope intersection itself, **after** Base Permission and before policy lane evaluation. `ReadOnlyAuthorizationContext` still caches the same `employee` and `assignments` buckets for the same operation. For effective assignment dates, the adapter passes `evaluationTime->businessDate(UTC)` to the canonical EmployeeStructure repository, matching the previous `EmployeeAssignmentService::findEffective()` behavior. Row shape, ordering, `department_id`, `position_id` and assignment IDs are unchanged.

**Runtime recursion guard:** while executing either employee-scope port call, a reentrant `PbacDecisionEngine::authorize()` on the same engine throws `LogicException` (never an implicit ALLOW). The guard is reset in `finally` on both success and error. The default adapter does not invoke authorization at all. Further recursive chains involving **different** engine instances are not exhaustively proven by this guard; the adapter's no-authorization rule remains mandatory.

The canonical engine's internal constructor now requires `EmployeeScopeReadPort`; direct construction must pass this port. Its supported public composition point remains `AuthorizationDecisionFactory`, and `AuthorizationService` retains its exact legacy constructor. Three direct **internal test** engine instantiations were migrated to factory calls; no production caller other than Bootstrap constructed the engine.

## 3. Verification and invariants

| Invariant | Evidence / expected gate |
|---|---|
| PBAC mandatory base permission; employee ACTIVE; effective UTC assignments; GROUP/COMPANY scope and primary/participant precedence | `BeReorg008DecisionFactoryTest`, `BeReorg008DecisionParityTest`, other pre-existing Authorization/NO-DROP tests on real MariaDB + Shield **pending operator runtime** |
| Same connection used for all default reads | Existing `BeReorg008DecisionFactoryTest::testDefaultCollaboratorsUseOneSuppliedConnection`, now asserts the two provider repositories inside the adapter |
| Legacy `AuthorizationService` ten positional/named args remain | `BeReorg008DecisionCompatibilityTest` unchanged |
| Adapter reads the real provider shape/UTC date, no mutation, errors propagate | `tools/test-be-reorg-014l-employee-scope-read.php` (vendor-free behavior with provider test doubles) |
| No runtime read -> authorize recursive path | Vendor-free reentrancy/finally check **and** negative runtime spy added inside existing `BeReorg008DecisionFactoryTest`, without adding a test class/method to NO-DROP |
| No new module cycle / cross-owner mutation | `architecture-scan.php` (staged new-edge only), `test-architecture-conformance.php`; dynamic SQL not covered |
| Existing Product/Pricing/Numbering behavior | 014J/K, BE-REORG-014C/D/H/I, KIR-001 source gates; operator `UNIT-STANDARD`, `KIR-001 RUN`, `NO-DROP` still required |

Run source-only from root:

```powershell
php backend/tools/test-be-reorg-014l-employee-scope-read.php
php backend/tools/architecture-scan.php
php backend/tools/test-architecture-conformance.php
php backend/tools/test-unit-standard-source-gate.php
php backend/tools/test-unit-no-drop-source-gate.php
```

From `backend/` **after backing up `erp_test` and verifying the test DB target**, run:

```powershell
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-kir001.ps1 RUN
.\tools\test-unit.ps1 RUN NO-DROP
```

The NO-DROP lane intentionally skips MySQL grant verification when requested; schema/data cohort attestation does not prove that a historical DB snapshot is preserved. Authenticated HTTP/PBAC parity across every endpoint and specialized Shield integration remain `NOT_RUN` in the distributed patch. The provided package has no `backend/vendor`, so do not label these runtime gates PASS based on source tests.

## 4. Risk, rollback, follow-ups

- The canonical engine constructor becomes explicitly port-injected. Source search found only Bootstrap and the migrated three internal test instantiations. External direct constructors (if any outside repository) must use `AuthorizationDecisionFactory` or explicitly supply the port.
- Injection of the historical mutable assignment service survives for compatibility **in Bootstrap only** and is not a new recommended dependency. The default path calls read-only repository methods; no change to SQL or transaction session/owner is intended.
- Rollback: `git apply -R BE-REORG-014L-authorization-employee-read.patch` on an unmodified applied working tree. No data/schema rollback.
- **Out of scope:** legacy `EvaluationOrganizationResolver`, `EmployeeContactScopeResolver`, `EffectiveOrganizationScopeIndex` and `OwnerAwareListBucketCompiler` still read employee/assignment data through their existing permitted compatibility paths. Their scope and SQL equivalence need a separate inventory/parity slice rather than being bundled here. Other employee command entrypoints and Organization ancestor reads remain unchanged.
