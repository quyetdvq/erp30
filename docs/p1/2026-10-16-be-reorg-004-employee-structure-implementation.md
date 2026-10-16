# BE-REORG-004 Employee Structure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move Department, Position, Employee and Employee Assignment implementation behind the canonical `App\\Modules\\EmployeeStructure` boundary without changing accepted runtime behavior.

**Architecture:** Introduce four focused Employee Structure subdomains plus a stateless `EmployeeStructureFactory`; preserve all eight legacy services/repositories as final explicit composition wrappers. API-020, Authorization/IAM, Contact, workflow infrastructure, migrations and routes stay outside the module and continue to consume legacy entrypoints.

**Tech Stack:** PHP 8.3, CodeIgniter 4, PHPUnit 10, MySQLi test DB, PHPStan.

**Spec:** `docs/superpowers/specs/2026-10-16-be-reorg-004-employee-structure-design.md`

## Global Constraints

- Accepted baseline: 743 PHPUnit tests / 6788 assertions; Foundation 9 HTTP + 4 DB-lock, Organization 37 cases and Contact 32 cases green with no skips; 121 inherited PHPStan diagnostics.
- Behavior-preserving physical relocation only. No schema/migration/route/controller/OpenAPI/frontend/IAM/Contact/workflow behavior change.
- Preserve all eight legacy constructors, parameter names/defaults, public constants, methods, return types and named-argument compatibility.
- Preserve existing lock keys only: `department-tree`, `employee-manager-tree`, `employee-assignment:<employeeId>`; Position remains application-lock-free.
- Canonical services depend on module ports. Canonical Assignment depends on Organization's existing `OrganizationRepositoryInterface`, never legacy Organization persistence.
- If a repository and DB are both supplied, the repository remains authoritative while the supplied DB remains authoritative for workflow/transaction/lock logic.
- Factory retains no connection, service, repository, actor, clock, mutation context or lock instance across calls.
- Full runtime uses guarded disposable `127.0.0.1:3306/erp_test` with `dinktech_` prefix and no concurrent test process.
- Test fixtures that create Contact authorization rows must clean them before `DatabaseTestTrait` rollback; production migration guards are not weakened.
- Acceptance requires exact PHPStan diagnostic-set equality with the inherited 121-diagnostic checkpoint; count-only comparison is insufficient.

## Review Focus

- Assignment canonical wiring must use `EmployeeRepositoryInterface` + Organization port and preserve supplied legacy repository objects without rebinding — Tasks 3 and 5.
- Department/Employee hierarchy error precedence and lock selectivity must remain unchanged for ordinary non-hierarchy updates — Tasks 4 and 6.
- Position `level` must keep nullable and negative-integer support; no positive-only rule may be introduced — Task 4.
- Assignment overlap/date/reference precedence and historical `end()` behavior must remain unchanged when referenced rows later become inactive — Task 5.
- API-020/Authorization/Contact callers must remain byte-identical and migration-safe test teardown must prevent `CONTACT_AUTH_ROLLBACK_REQUIRES_REVIEW` recurrence — Tasks 7 and 8.

---

### Task 1: Freeze legacy contracts and introduce module contracts/ports

**Files:**
- Create: `backend/tests/unit/BeReorg004EmployeeStructureBoundaryTest.php`
- Create: `backend/tests/unit/BeReorg004EmployeeStructureCompatibilityTest.php`
- Create: four `Contracts/*Errors.php`
- Create: four `Application/Ports/*RepositoryInterface.php`

**Interfaces:**
- Consumes: accepted eight legacy classes at BE-REORG-003_FIX01.
- Produces: Department/Position/Employee/Assignment error catalogs and repository ports with 5/5/6/12 methods respectively.

- [ ] **Step 1: Write fixed Reflection expectations for all eight legacy classes.** Hard-code constructor parameter names/defaults, public methods/return types and constant strings from the checkpoint; do not derive expectations from module classes.
- [ ] **Step 2: Write six boundary tests.** Assert exact port method sets; canonical namespaces contain no API/IAM/Contact dependencies; Assignment is allowed Organization port only; factory has no retained state; wrappers must end as final explicit delegates with no inheritance/`__call`; excluded API-020/IAM/Contact production files remain outside the module.
- [ ] **Step 3: Run both new unit files and verify RED.** Expected: missing module ports/errors/factory/wrapper-boundary assertions fail while fixed checkpoint Reflection expectations pass.
- [ ] **Step 4: Create the four error catalogs** by moving/re-exporting the exact current service/repository code strings with no new identity.
- [ ] **Step 5: Create repository ports** with exact existing public repository signatures and PHPDoc shapes: Department 5, Position 5, Employee 6, Assignment 12.
- [ ] **Step 6: Extend compatibility tests** to assert exact error-code sets and port method signatures; rerun the two unit files. Wrapper/factory assertions may remain RED until later tasks.
- [ ] **Step 7: Commit.** `test(employee-structure): freeze BE-REORG-004 contracts`.

---

### Task 2: Isolate Department and Position SQL persistence

**Files:**
- Create: `backend/app/Modules/EmployeeStructure/Department/Infrastructure/Persistence/SqlDepartmentRepository.php`
- Create: `backend/app/Modules/EmployeeStructure/Position/Infrastructure/Persistence/SqlPositionRepository.php`
- Modify: `backend/app/Repositories/DepartmentRepository.php`
- Modify: `backend/app/Repositories/PositionRepository.php`
- Modify: compatibility/boundary tests

**Interfaces:**
- Consumes: Task 1 Department/Position ports and error catalogs.
- Produces: canonical SQL repositories; legacy repository wrappers implementing those ports.

- [ ] **Step 1: Add failing compatibility tests** for five-method explicit delegation, constructor/named-argument preservation, validation-before-DB, empty-update/error mapping and deterministic `id ASC` reads.
- [ ] **Step 2: Run compatibility tests and verify RED** because legacy repositories still own SQL.
- [ ] **Step 3: Create `SqlDepartmentRepository` and `SqlPositionRepository`.** Relocate current table names, selected columns, payload allowlists, required fields, exception mapping, insert ID and ordering without SQL/validation change.
- [ ] **Step 4: Replace both legacy repositories with final explicit composition wrappers** implementing their ports; preserve constructors, constants and public signatures.
- [ ] **Step 5: Run** BE-REORG-004 unit files plus existing Department/Position API/database tests.
- [ ] **Step 6: Token/AST compare repository methods to checkpoint** after normalizing namespace/class/error-alias/wiring changes; any SQL/order/validation delta blocks progress.
- [ ] **Step 7: Commit.** `refactor(employee-structure): isolate department position persistence`.

---

### Task 3: Isolate Employee and Assignment SQL persistence

**Files:**
- Create: `backend/app/Modules/EmployeeStructure/Employee/Infrastructure/Persistence/SqlEmployeeRepository.php`
- Create: `backend/app/Modules/EmployeeStructure/Assignment/Infrastructure/Persistence/SqlEmployeeAssignmentRepository.php`
- Modify: `backend/app/Repositories/EmployeeRepository.php`
- Modify: `backend/app/Repositories/EmployeeAssignmentRepository.php`
- Modify: compatibility/boundary tests

**Interfaces:**
- Consumes: Task 1 Employee/Assignment ports and error catalogs.
- Produces: canonical Employee/Assignment SQL repositories; legacy wrappers implementing ports and remaining injectable into existing Authorization/IAM.

- [ ] **Step 1: Add failing tests** for Employee six-method and Assignment twelve-method delegation, Employee filters/order, effective/batch/overlap queries and distinct existence-versus-active checks.
- [ ] **Step 2: Run compatibility tests and verify RED.**
- [ ] **Step 3: Create canonical SQL repositories** by relocating current SQL exactly, including Assignment date/effective predicates and overlap ordering.
- [ ] **Step 4: Replace legacy repositories with explicit wrappers** preserving constructors/constants/signatures and port implementation.
- [ ] **Step 5: Run** BE-REORG-004 unit files plus Authorization tests that inject Employee/Assignment repositories and existing `P1Api020EmployeeStructureTest`.
- [ ] **Step 6: Token/AST compare canonical persistence to checkpoint.** No join/index/query-count/predicate change is permitted.
- [ ] **Step 7: Commit.** `refactor(employee-structure): isolate employee assignment persistence`.

---

### Task 4: Move Department/Position application services and characterize behavior

**Files:**
- Create: Department/Position canonical application services
- Modify: legacy `DepartmentService.php`, `PositionService.php`
- Create: `backend/tests/database/BeReorg004DepartmentPositionCharacterizationTest.php`
- Modify: compatibility/boundary tests

**Interfaces:**
- Consumes: Department/Position ports, cross-cutting lock/mutation/clock/workflow utilities.
- Produces: canonical Department/Position services; legacy service wrappers; ten DB characterization cases.

- [ ] **Step 1: Write five Department characterization tests:** create/defaults/audit; duplicate/missing-parent precedence; self/cycle/broken-chain rejection; direct-state guard; `department-tree` lock selectivity on hierarchy-sensitive versus ordinary update.
- [ ] **Step 2: Write five Position characterization tests:** create/defaults/audit; duplicate code; nullable/negative/string-integer `level`; invalid level/status/direct-state guard; repository payload/error precedence and no application lock.
- [ ] **Step 3: Establish RED honestly** against missing canonical services/wrapper delegation while fixed behavior expectations pass on the accepted legacy path.
- [ ] **Step 4: Create canonical Department and Position services** with port-typed repositories and supplied DB/cross-cutting dependencies; relocate method bodies/order without semantic change.
- [ ] **Step 5: Replace legacy services with final explicit composition wrappers** preserving exact accepted constructors/constants/public methods.
- [ ] **Step 6: Run** unit tests, the ten characterization cases and existing Department/Position API tests.
- [ ] **Step 7: Token/AST compare canonical application method bodies to checkpoint** ignoring only namespace/error-alias/port wiring.
- [ ] **Step 8: Commit.** `refactor(employee-structure): isolate department position services`.

---

### Task 5: Move Employee/Assignment application services and add factory

**Files:**
- Create: Employee/Assignment canonical application services
- Create: `backend/app/Bootstrap/EmployeeStructureFactory.php`
- Modify: legacy `EmployeeService.php`, `EmployeeAssignmentService.php`
- Create: `backend/tests/database/BeReorg004EmployeeCharacterizationTest.php`
- Create: `backend/tests/database/BeReorg004AssignmentCharacterizationTest.php`
- Modify: compatibility/boundary tests

**Interfaces:**
- Consumes: Employee/Assignment ports, Organization port, DB, lock, mutation context, clock.
- Produces: canonical Employee/Assignment services, stateless factory, legacy wrappers, ten Employee + twelve Assignment characterization cases.

- [ ] **Step 1: Write ten Employee characterization tests** covering create/update/audit, code/user uniqueness, gender/status normalization, manager set/remove, self/missing/inactive manager precedence, cycle/broken-chain rejection, inactivation and manager-lock selectivity.
- [ ] **Step 2: Write twelve Assignment characterization tests** covering create/update/end, immutable employee, missing versus inactive refs, date validation, primary overlap/open-ended dates, effective/batch reads, primary resolution, eligible ancestor scope, default actor context, organization broken/cycle/inactive fail-closed behavior, audit and assignment-lock selectivity.
- [ ] **Step 3: Add failing factory/wiring tests** proving fresh instances, no state retention, supplied repository authority, supplied DB authority and Assignment dependency on Employee/Organization ports only.
- [ ] **Step 4: Run new unit/characterization tests and verify RED** for missing canonical services/factory/wrapper delegation.
- [ ] **Step 5: Create canonical Employee and Assignment services** by relocating current behavior/order exactly; Assignment constructor is port-based and uses Organization's existing port.
- [ ] **Step 6: Create `EmployeeStructureFactory`** with fresh constructors for all four repositories/services, resolving one connection per call and never rebinding supplied repositories.
- [ ] **Step 7: Replace legacy Employee/Assignment services with explicit composition wrappers** preserving exact accepted constructors including `employeeRepository` and `organizationRepository` named arguments.
- [ ] **Step 8: Run** all BE-REORG-004 unit/characterization tests plus Authorization eligibility/context tests and existing Assignment tests.
- [ ] **Step 9: Token/AST compare canonical service methods to checkpoint**; lock keys, workflow guards, audit ordering, reference checks and organization-path logic must be unchanged.
- [ ] **Step 10: Commit.** `refactor(employee-structure): isolate employee assignment services`.

---

### Task 6: Prove API-020 and Authorization integration compatibility

**Files:**
- Create: `backend/tests/database/BeReorg004EmployeeStructureHttpTest.php`
- Create or extend test-only Employee Structure runtime fixture under `backend/tests/_support/EmployeeStructure/`

**Interfaces:**
- Consumes: unchanged API-020/controller/Authorization/IAM paths and all eight legacy wrappers.
- Produces: five in-process HTTP regression cases with rollback-safe test cleanup.

- [ ] **Step 1: Add five HTTP cases:** Department hierarchy CRUD/list; Position CRUD/list; Employee create/update/manager/filter visibility; Assignment create/update/end/effective/eligibility; authorization denial + hidden rows + visibility-before-count/pagination + canonical error/state envelopes.
- [ ] **Step 2: Ensure fixture teardown removes authorization/policy/audit rows that would block Contact auth migration rollback** before `DatabaseTestTrait` refresh; do not weaken migrations.
- [ ] **Step 3: Run the HTTP file** plus existing `P1Api020EmployeeStructureTest`, assignment-eligibility tests and Employee Contact scope tests. Any behavior mismatch is investigated against the accepted checkpoint before touching production logic.
- [ ] **Step 4: Verify `Api020EmployeeStructureService`, controllers, Authorization/IAM, Contact and workflow production files remain byte-identical.**
- [ ] **Step 5: Commit.** `test(employee-structure): lock API020 integration`.

---

### Task 7: Prove the three existing real DB lock contracts

**Files:**
- Create: `backend/tests/database/BeReorg004EmployeeStructureLockTest.php`

**Interfaces:**
- Consumes: real `MariaDbDomainLockService`, independent DB connections and canonical/legacy wrapper paths.
- Produces: three lock cases; no new lock key.

- [ ] **Step 1: Add one Department test** proving `department-tree` contention while held, success after release and release after representative failure.
- [ ] **Step 2: Add one Employee test** proving `employee-manager-tree` contention/release and that ordinary non-manager update does not acquire it.
- [ ] **Step 3: Add one Assignment test** proving `employee-assignment:<employeeId>` contention/release and employee-specific isolation.
- [ ] **Step 4: Run the three tests serially** on guarded `erp_test`; no sleeps as the sole proof and no parallel test process.
- [ ] **Step 5: Commit.** `test(employee-structure): preserve lock contracts`.

---

### Task 8: Add BE-REORG-004 guarded runner, coverage gate and delivery evidence

**Files:**
- Create: `backend/tools/test-employee-structure.php`
- Modify: `backend/tools/test-foundation.php`
- Create: `docs/changes/BE-REORG-004/README.md`

**Interfaces:**
- Consumes: current Foundation/Organization/Contact runner behavior and all BE-REORG-004 tests.
- Produces: one guarded full-suite command, fresh JUnit manifest gates and root `test-result.log`.

- [ ] **Step 1: Add static runner assertions** that only `--confirm-test-db` enables runtime, full PHPUnit executes once, existing three gates remain mandatory, JUnit freshness/no-skip checks are preserved and PHPStan config/baseline/ignore files stay unchanged.
- [ ] **Step 2: Verify RED** because Employee Structure runner/gate support does not exist.
- [ ] **Step 3: Create `test-employee-structure.php`** declaring exactly **64 BE-REORG-004 cases**: Boundary 6, Compatibility 18, Department+Position Characterization 10, Employee Characterization 10, Assignment Characterization 12, HTTP 5, Lock 3.
- [ ] **Step 4: Extend `test-foundation.php` minimally** to label BE-REORG-004, validate Employee Structure prerequisites and gate Foundation + Organization + Contact + Employee Structure from the same fresh JUnit manifest before PHPStan. No second PHPUnit run.
- [ ] **Step 5: Document** scope, non-goals, patch order, DB safety, rollback-safe fixtures and browser/Apache UAT separation in `docs/changes/BE-REORG-004/README.md`.
- [ ] **Step 6: Local checks:** `php -l` on every new/modified PHP file, `git diff --check`, method/signature parity probes and excluded-file byte hashes.
- [ ] **Step 7: Operator runtime:** `cd D:\\XAMPP\\htdocs\\erp\\backend; php tools/test-employee-structure.php --confirm-test-db`.
- [ ] **Step 8: Acceptance:** absent unrelated new tests, full suite = **807 tests**; PHPUnit exit `0`; Foundation gate 9 HTTP + 4 DB-lock; Organization 37; Contact 32; Employee Structure 64; zero skips.
- [ ] **Step 9: PHPStan acceptance:** normalize and compare complete diagnostics with the accepted checkpoint; expected inherited set remains exactly 121 with zero new diagnostics even if global PHPStan exits `1`.
- [ ] **Step 10: Patch integrity:** apply-check/apply/hash/reverse against exact BE-REORG-003_FIX01 checkpoint; verify every excluded production file is byte-identical and no unrelated tree drift exists.
- [ ] **Step 11: Commit.** `test(employee-structure): add BE-REORG-004 runtime gate`.

---

## Self-review

- Spec coverage maps to Tasks 1-8: four module boundaries, compatibility wrappers, SQL relocation, service relocation, Organization-port dependency, API/Authorization boundary, three real lock contracts, rollback-safe fixtures and guarded acceptance.
- Shared interfaces are consistent: Task 1 ports/errors → Tasks 2-3 persistence → Tasks 4-5 services/factory → Tasks 6-7 runtime integration → Task 8 gate/package.
- Review Focus risks each have an explicit owning test task.
- The 64-case gate is internally consistent: 6 + 18 + 10 + 10 + 12 + 5 + 3 = 64; accepted total becomes 743 + 64 = 807 if no unrelated tests are added.
- Scope explicitly excludes API-020/IAM/Contact/workflow/migration behavior changes, new locks, schema work, Composer work and opportunistic cleanup of the inherited 121 PHPStan diagnostics.
- Plan records decisions, signatures, case counts and commands without embedding production method bodies.
