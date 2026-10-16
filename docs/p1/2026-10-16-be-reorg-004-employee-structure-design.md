# BE-REORG-004: Employee Structure module

## Scope and accepted baseline

Continue the approved modular-monolith reorganization from the checkpoint containing BE-REORG-001 through BE-REORG-003_FIX01. The accepted operator baseline is PHP 8.3.30 with 743 PHPUnit tests / 6788 assertions passing, all Foundation, Organization and Contact coverage gates executed with no skips, and 121 inherited PHPStan diagnostics whose full output is unchanged from the prior accepted checkpoint.

Move only the implementations currently owned by these eight legacy classes:

- `App\Services\DepartmentService`
- `App\Services\PositionService`
- `App\Services\EmployeeService`
- `App\Services\EmployeeAssignmentService`
- `App\Repositories\DepartmentRepository`
- `App\Repositories\PositionRepository`
- `App\Repositories\EmployeeRepository`
- `App\Repositories\EmployeeAssignmentRepository`

Preserve all eight classes as legacy public entrypoints because API-020 and Authorization/IAM still type against them. This package is a physical reorganization plus explicit dependency boundaries only. It does not intentionally change business behavior.

`App\Services\Api020EmployeeStructureService`, API controllers, Authorization/IAM resolvers, Contact authorization logic, workflow infrastructure, routes, migrations, schemas, OpenAPI/contracts, frontend code and the accepted PHPStan configuration remain outside this module and continue to consume compatibility entrypoints exactly as before.

The module name is `EmployeeStructure`, not `HumanResources`, because the current scope is organization staffing structure only. Payroll, leave, attendance, recruitment, benefits and other HR domains are not implied or introduced by this reorganization.

## Architecture

Canonical implementation will be introduced under `App\Modules\EmployeeStructure` with four bounded subdomains.

### Department

- `App\Modules\EmployeeStructure\Department\Application\DepartmentService`
- `App\Modules\EmployeeStructure\Department\Application\Ports\DepartmentRepositoryInterface`
- `App\Modules\EmployeeStructure\Department\Contracts\DepartmentErrors`
- `App\Modules\EmployeeStructure\Department\Infrastructure\Persistence\SqlDepartmentRepository`

The repository port exposes the five existing operations used by the service/API facade: `find`, `findByCode`, `findAll`, `insert`, `update`.

### Position

- `App\Modules\EmployeeStructure\Position\Application\PositionService`
- `App\Modules\EmployeeStructure\Position\Application\Ports\PositionRepositoryInterface`
- `App\Modules\EmployeeStructure\Position\Contracts\PositionErrors`
- `App\Modules\EmployeeStructure\Position\Infrastructure\Persistence\SqlPositionRepository`

The repository port exposes the five existing operations: `find`, `findByCode`, `findAll`, `insert`, `update`.

### Employee

- `App\Modules\EmployeeStructure\Employee\Application\EmployeeService`
- `App\Modules\EmployeeStructure\Employee\Application\Ports\EmployeeRepositoryInterface`
- `App\Modules\EmployeeStructure\Employee\Contracts\EmployeeErrors`
- `App\Modules\EmployeeStructure\Employee\Infrastructure\Persistence\SqlEmployeeRepository`

The repository port exposes the six existing operations: `find`, `findByCode`, `findByUserId`, `findAll`, `insert`, `update`.

### Assignment

- `App\Modules\EmployeeStructure\Assignment\Application\EmployeeAssignmentService`
- `App\Modules\EmployeeStructure\Assignment\Application\Ports\EmployeeAssignmentRepositoryInterface`
- `App\Modules\EmployeeStructure\Assignment\Contracts\EmployeeAssignmentErrors`
- `App\Modules\EmployeeStructure\Assignment\Infrastructure\Persistence\SqlEmployeeAssignmentRepository`

The repository port exposes the twelve existing operations: `find`, `insert`, `update`, `findByEmployee`, `findAll`, `findEffective`, `findEffectiveForEmployees`, `findOverlappingPrimary`, `departmentIsActive`, `positionIsActive`, `departmentExists`, and `positionExists`.

### Factory and compatibility layer

Add `App\Bootstrap\EmployeeStructureFactory`. It provides explicit fresh-instance constructors for each repository and each canonical service. It retains no shared database connection, repository instance, actor, clock, mutation context or lock service.

The existing eight legacy classes remain `final` explicit composition wrappers. They preserve constructors, parameter names/defaults, public constants, public methods, return types and named-argument compatibility. They do not use inheritance, `__call`, shared-instance caching or hidden service-locator state.

Each legacy repository wrapper implements its matching repository port. Therefore existing Authorization/IAM and API-020 callers can continue injecting the legacy repository while canonical services depend only on module ports.

The canonical Assignment service depends on:

- `EmployeeAssignmentRepositoryInterface`
- `EmployeeRepositoryInterface`
- the existing `App\Modules\Organization\Application\Ports\OrganizationRepositoryInterface`
- the supplied CodeIgniter connection
- `DomainLockService`
- `MutationContext`
- `Clock`

It must not depend on legacy `App\Repositories\EmployeeRepository`, legacy `App\Repositories\OrganizationRepository`, `Api020EmployeeStructureService`, Authorization/IAM services, or Contact services.

When a caller explicitly supplies a repository object together with a database connection, the supplied repository remains authoritative and is not silently reconstructed or rebound. The supplied connection remains the connection used by workflow-boundary and transaction/lock logic, preserving existing constructor semantics.

## Public compatibility contracts

### Department service and repository

`App\Services\DepartmentService` preserves:

`__construct(?DepartmentRepository $repository = null, ?ConnectionInterface $db = null, ?DomainLockService $domainLock = null, ?MutationContext $mutationContext = null, ?Clock $clock = null)`.

It preserves five service error constants and the public methods:

- `create(array $data): int`
- `update(int $id, array $data): bool`

`App\Repositories\DepartmentRepository` preserves `__construct(?ConnectionInterface $db = null)`, five repository error constants, and the five repository methods listed above.

### Position service and repository

`App\Services\PositionService` preserves:

`__construct(?PositionRepository $repository = null, ?ConnectionInterface $db = null, ?MutationContext $mutationContext = null, ?Clock $clock = null)`.

It preserves three service error constants and:

- `create(array $data): int`
- `update(int $id, array $data): bool`

`App\Repositories\PositionRepository` preserves `__construct(?ConnectionInterface $db = null)`, five repository error constants, and the five repository methods listed above.

### Employee service and repository

`App\Services\EmployeeService` preserves:

`__construct(?EmployeeRepository $repository = null, ?ConnectionInterface $db = null, ?DomainLockService $domainLock = null, ?MutationContext $mutationContext = null, ?Clock $clock = null)`.

It preserves nine service error constants and:

- `create(array $data): int`
- `update(int $id, array $data): bool`
- `setManager(int $employeeId, ?int $managerEmployeeId): bool`
- `inactivate(int $employeeId): bool`

`App\Repositories\EmployeeRepository` preserves `__construct(?ConnectionInterface $db = null)`, five repository error constants, and the six repository methods listed above.

### Employee Assignment service and repository

`App\Services\EmployeeAssignmentService` preserves:

`__construct(?EmployeeAssignmentRepository $repository = null, ?ConnectionInterface $db = null, ?DomainLockService $domainLock = null, ?MutationContext $mutationContext = null, ?Clock $clock = null, ?EmployeeRepository $employeeRepository = null, ?OrganizationRepository $organizationRepository = null)`.

It preserves eight service error constants and these eight public methods without signature or named-argument changes:

- `create(array $data): int`
- `update(int $id, array $data): bool`
- `end(int $id, ?string $effectiveTo = null): bool`
- `findEffective(int $employeeId, ?EvaluationTime $evaluationTime = null): array`
- `findEffectiveForEmployees(array $employeeIds, ?EvaluationTime $evaluationTime = null): array`
- `resolvePrimary(int $employeeId, ?EvaluationTime $evaluationTime = null): ?array`
- `resolveEligible(int $employeeId, int $evaluationOrganizationId, ?EvaluationTime $evaluationTime = null): array`
- `resolveDefaultActorContext(int $employeeId, ?EvaluationTime $evaluationTime = null): ?array`

`App\Repositories\EmployeeAssignmentRepository` preserves `__construct(?ConnectionInterface $db = null)`, five repository error constants, and all twelve current public methods listed above.

The four module error catalogs expose all currently stable service/repository error identities unchanged. Compatibility wrappers re-export the same public constant values so existing tests and integrations observe no error-code drift.

## Department invariants

Preserve current field allowlist, required-field behavior and audit stripping. Department creation still defaults `parent_id` to `null` and status to `ACTIVE`. Status normalization remains delegated to `WorkflowBusinessStatus` for logical resource `department` with the existing `ACTIVE`, `INACTIVE`, `CLOSED` vocabulary.

Preserve global department code uniqueness, missing-parent rejection, self-parent rejection, historical-cycle detection and failure on broken parent chains. Reparenting validation must retain current error precedence.

Preserve the named lock `department-tree` and its current transaction boundary. Both create and hierarchy-sensitive update remain serialized through that lock. A non-`BaseConnection` still fails with `DEPARTMENT_MUTATION_FAILED`; lock/transaction failures preserve existing typed error behavior.

Direct lifecycle/status mutation remains guarded by `BusinessWorkflowWriteBoundary` exactly where it is today.

## Position invariants

Preserve code uniqueness, field allowlist, required `code`/`name`, nullable integer `level` behavior, server-owned audit and existing status normalization through logical resource `position` with `ACTIVE`, `INACTIVE`, `CLOSED`.

Do not invent a Position domain lock or transaction. Position retains its current direct repository write behavior and current workflow-boundary ordering.

## Employee invariants

Preserve global `employee_code` uniqueness and nullable `user_id` uniqueness behavior. Preserve accepted gender vocabulary and normalization exactly as today; no new gender values or stricter nullable rule are introduced.

Preserve manager rules and error precedence:

- employee cannot manage itself;
- manager reference must exist;
- manager must be active;
- direct and historical manager cycles are rejected;
- broken manager chains fail closed;
- setting manager to `null` remains supported where currently supported.

Preserve named lock `employee-manager-tree` for manager-tree changes and inactivation behavior that currently enters the manager-tree transaction path. Ordinary employee updates that do not change manager and do not inactivate must not acquire a new lock.

Preserve server-owned audit and `BusinessWorkflowWriteBoundary` ordering for logical resource `employee`. Preserve `ACTIVE` / `INACTIVE` status semantics and all existing repository payload/error behavior.

## Employee Assignment invariants

Preserve assignment field support, required fields, positive-ID normalization, `is_primary` boolean normalization, `ACTIVE` / `INACTIVE` status vocabulary, server-owned audit and strict `Y-m-d` date validation.

Preserve immutable `employee_id` on update. Preserve reference validation for employee, organization, department and position, including the distinction between nonexistent and inactive references. Do not replace current department/position existence checks with new service calls if that changes SQL count, error precedence or behavior.

Preserve primary-assignment overlap semantics exactly. Only active primary assignments participate in the overlap guard; inclusive effective date overlap and optional open-ended `effective_to` behavior remain unchanged.

Preserve the lock key `employee-assignment:<employeeId>` and its current transaction scope for create/update/end operations that require assignment consistency. `withAssignmentLock()` must continue resolving the current assignment first, then locking by its employee. Concurrency exceptions remain distinct from domain/persistence failures.

Preserve `findEffective`, `findEffectiveForEmployees`, `resolvePrimary`, `resolveEligible` and `resolveDefaultActorContext` semantics. `resolveEligible` continues to compare effective assignments against the authoritative ancestor path of the requested evaluation organization. The organization path is root-to-leaf, rejects missing/broken/cyclic identities, and requires the evaluation organization itself to be active. `resolveDefaultActorContext` continues to return the primary assignment, leaf organization, nearest COMPANY ancestor when available, and full organization path.

The canonical Assignment service uses the Organization module port introduced by BE-REORG-002, but this is a dependency-boundary change only. Organization SQL, Organization service behavior and legacy Organization wrappers remain untouched.

## Repository persistence invariants

All four canonical SQL repositories preserve the exact current table names, selected columns, payload allowlists, required-field checks, empty-update rejection, exception identities, insert ID handling and boolean success semantics.

Preserve deterministic ordering:

- departments: `id ASC`
- positions: `id ASC`
- employees: `id ASC`, with the current filters (`user_id`, `employee_code`, `status`, `manager_employee_id`)
- employee assignments: current `findAll` ordering plus current `findByEmployee`, effective-assignment and overlap query ordering/predicates

Do not add eager joins, ORM models, caching, N+1 remediation or new indexes in this package. SQL behavior is characterized before movement and compared after movement.

## Authorization, API-020 and Contact integration boundary

`Api020EmployeeStructureService` remains byte-identical unless a test-only/runtime-runner change explicitly requires otherwise. It continues to construct/use the legacy eight entrypoints.

The following existing Authorization/IAM components remain outside the Employee Structure module and continue to depend on legacy repository/service wrappers:

- `AuthorizationService`
- `EffectiveOrganizationScopeIndex`
- `EmployeeContactScopeResolver`
- `EmployeeContextResolver`
- `EvaluationOrganizationResolver`
- `OwnerAwareListBucketCompiler`

No Authorization resource names, action names, PBAC behavior, employee eligibility rules, secondary-assignment lanes, evaluation-organization resolution, list visibility, count-before-pagination semantics or Contact owner-scope behavior changes in BE-REORG-004.

The Contact module from BE-REORG-003 remains untouched. Employee-owned Contact behavior is verified through unchanged Authorization/API integration, not by moving Contact code into Employee Structure.

## Concurrency boundary

BE-REORG-004 must preserve three existing named-lock contracts and must not invent new ones:

1. `department-tree`
2. `employee-manager-tree`
3. `employee-assignment:<employeeId>`

Runtime verification uses independent real test database connections to prove contention and release for these exact keys. Tests must verify that unrelated operations do not accidentally acquire these locks. Any failure path tested for lock release must use the real transaction/lock implementation rather than mocks or sleeps as proof.

Position has no named-lock contract and remains lock-free at the application-service level.

## Verification design

1. Capture fixed Reflection contracts from the accepted checkpoint for all eight legacy classes before implementation. Compatibility is validated against stored checkpoint data, not merely by comparing two changed classes.
2. Add module boundary tests proving each SQL repository implements the correct port, each canonical service depends on ports rather than legacy persistence, Assignment depends on Organization's port rather than legacy Organization repository, wrappers are explicit delegates, and `EmployeeStructureFactory` retains no shared state.
3. Add legacy-vs-canonical characterization for Department: create/update, code uniqueness, parent validity, self/cycle/broken-chain handling, server-owned audit, workflow direct-state guard and `department-tree` lock usage.
4. Add Position characterization: create/update, code uniqueness, nullable/negative integer `level`, invalid level, status/workflow handling, audit behavior and repository payload errors.
5. Add Employee characterization: create/update, code/user uniqueness, gender/status validation, manager assignment/removal, self/inactive/cycle/broken-manager rejection, inactivation, audit behavior, workflow guard and manager-lock selectivity.
6. Add Assignment characterization: create/update/end, immutable employee, reference existence/inactive distinction, date validation, primary-overlap behavior, effective reads, batch effective reads, primary resolution, eligible-scope resolution, default actor context, organization ancestor traversal, audit behavior and assignment lock selectivity.
7. Add in-process API-020 regression using the existing request helper conventions. Cover Department, Position, Employee and Assignment list/get/create/update/state paths, authorization denial, hidden rows, filtering, visibility-before-count/pagination, wire/error envelopes and state-transition precedence without changing `Api020EmployeeStructureService`.
8. Add real database lock tests for the three existing named locks with independent connections. Prove contention while held, success after release and release after one representative failure path for each lock family where practical.
9. Reuse the guarded single-command runner pattern. Full PHPUnit runs exactly once, then fresh JUnit manifests are checked for the new Employee Structure cases and the existing Foundation, Organization and Contact gates, then PHPStan runs. A stale, skipped or filtered JUnit manifest is failure.
10. Test fixtures that create authorization grants/policies/audit rows for API-020 must clean those rows before `DatabaseTestTrait` attempts migration rollback. This explicitly prevents recurrence of the `CONTACT_AUTH_ROLLBACK_REQUIRES_REVIEW` contamination fixed in BE-REORG-003_FIX01. Production migrations are not weakened to accommodate tests.
11. Compare canonical method bodies and repository SQL against the accepted checkpoint after normalizing namespace, error-catalog references and dependency-wiring-only changes. Verify patch apply/reverse against the exact BE-REORG-003_FIX01 checkpoint and hash the tree to detect unrelated edits.
12. PHPStan acceptance is exact diagnostic comparison, not count-only comparison. The inherited 121 diagnostics may keep global PHPStan red; BE-REORG-004 is accepted only if it introduces zero new diagnostics.

## Operational safety

Runtime database testing is permitted only under the same existing test guard: `ENVIRONMENT=testing`, disposable loopback MySQLi target `127.0.0.1:3306/erp_test`, expected `dinktech_` prefix, and no concurrent test process against that database. `DatabaseTestTrait` may refresh tables.

No Composer update, dependency installation, migration execution outside the test runner, manual schema reset, seed rewrite or generated-client regeneration is part of this refactor. Missing local vendor/extensions/database are reported as environment blockers, never reclassified as a passing suite.

In-process HTTP verifies backend routing/controller/service behavior only. It does not prove browser/Vue rendering, Apache/XAMPP routing, reverse-proxy behavior or production concurrency schedules.

## Delivery contract

Deliver one incremental BE-REORG-004 patch on top of BE-REORG-003_FIX01 and one evidence package. The operator applies only the new patch, runs one guarded Employee Structure verification command, and returns one project-root `test-result.log`.

Runtime acceptance requires:

- full PHPUnit success;
- all new Employee Structure cases executed with none skipped;
- Foundation, Organization and Contact coverage gates still executed;
- no new PHPStan diagnostic compared with the exact accepted 121-diagnostic baseline.

The inherited 121 diagnostics are not called success and are not fixed opportunistically in this package.

Rollback reverses only the BE-REORG-004 patch on a tree with no later overlapping edits. No database rollback/migration command belongs to this code-only reorganization.

## Explicit non-goals

- Moving `Api020EmployeeStructureService`, controllers, routes or HTTP transport code into the module.
- Moving or rewriting Authorization/IAM resolvers, employee-context logic or Contact owner-scope logic.
- Changing Department, Position, Employee or Assignment schema/migrations/indexes.
- Adding new lock keys, transaction boundaries, caching or repository joins.
- Changing workflow resource names, lifecycle/status vocabularies, error precedence or HTTP wire contracts.
- Adding payroll, leave, attendance, recruitment, benefits or other HR functionality.
- Refactoring Organization or Contact implementations beyond consuming their existing public ports/wrappers.
- Fixing unrelated PHPStan diagnostics or changing static-analysis configuration.
- Refactoring Settings, Product, Pricing or other modules in the same patch.
