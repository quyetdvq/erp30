# BE-REORG-003 Contact Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move Contact service/repository implementation behind a canonical Contact module while preserving the accepted BE-REORG-002_FIX01 behavior exactly.

**Architecture:** Add `App\Modules\Contact` plus stateless `App\Bootstrap\ContactFactory`; keep `App\Services\ContactService` and `App\Repositories\ContactRepository` as final explicit composition wrappers. API-010, authorization and `ContactLifecycleAdapter` stay in place and continue using those wrappers.

**Tech Stack:** PHP 8.3, CodeIgniter 4, PHPUnit 10, MySQLi test DB, PHPStan.

**Spec:** `docs/superpowers/specs/2026-10-16-be-reorg-003-contact-design.md`

## Global Constraints

- Accepted baseline: 711 PHPUnit tests / 6503 assertions; Foundation and Organization gates green; 121 inherited PHPStan diagnostics.
- Behavior-preserving relocation only. No schema/migration/route/controller/frontend/IAM/workflow/lifecycle-adapter change.
- Preserve all legacy constructors, named parameters/defaults, public methods, 14 error strings, SQL/order/filter semantics and error precedence.
- No Contact named/domain lock, new transaction model, Composer update, migration or seed.
- Factory retains no service/repository/connection/actor/clock/request state.
- When both repository and DB are supplied, repository calls keep the supplied repository while workflow-boundary logic uses the supplied DB.
- Full runtime uses only guarded disposable loopback `erp_test`; no concurrent test process.
- Acceptance requires zero new PHPStan diagnostic by exact comparison; inherited 121 remain reported honestly.

## Review Focus

- Repository + different DB must not be silently rebound — Task 3.
- Nullable province/ward semantics must remain unchanged — Task 4.
- Missing/unsupported owner and owner-transfer error precedence must remain unchanged — Task 4.
- Fresh services must not reuse audit actor/time — Task 4.
- ORGANIZATION and EMPLOYEE lifecycle resource identities must not cross-load — Task 5.

---

### Task 1: Freeze contracts and create Contact contracts/port

**Files:**
- Create: `backend/tests/unit/BeReorg003ContactBoundaryTest.php`
- Create: `backend/tests/unit/BeReorg003ContactCompatibilityTest.php`
- Create: `backend/app/Modules/Contact/Contracts/ContactErrors.php`
- Create: `backend/app/Modules/Contact/Application/Ports/ContactRepositoryInterface.php`

**Interfaces:**
- Consumes: accepted legacy `ContactService` and `ContactRepository`.
- Produces: `ContactErrors`; seven-method `ContactRepositoryInterface`.

- [ ] **Step 1: Write fixed Reflection expectations for both legacy classes.** Hard-code constructors, parameter names/defaults, public methods/return types and constant string values; do not derive expectations from new classes.
- [ ] **Step 2: Write four boundary tests.** Assert module application depends on the port/cross-cutting services only; factory has no retained state/prohibited imports; persistence/contracts avoid API/IAM/lifecycle dependencies; legacy classes must end as final explicit delegates without inheritance or magic dispatch.
- [ ] **Step 3: Run the two new unit files and verify RED.** Expected: missing module/factory/wrapper assertions fail; fixed legacy Reflection contract passes against the checkpoint.
- [ ] **Step 4: Create `ContactErrors` with the exact 14 current strings.** Service: owner-transfer, status, type-required, owner-reference-required, type-unsupported, owner-not-found, ward/province mismatch, not-found. Repository: persistence-failed, connection-invalid, owner-immutable, update-empty, field-unsupported, field-required.
- [ ] **Step 5: Create `ContactRepositoryInterface` with exactly:** `find`, `insert`, `update`, `findByOwner`, `search`, `ownerExists`, `wardBelongsToProvince`; preserve current PHPDoc array/list shapes.
- [ ] **Step 6: Add compatibility assertions that `ContactErrors` exposes exactly 14 codes and the port exactly seven methods; rerun unit files.** Wrapper/factory tests remain RED until Tasks 2-3.
- [ ] **Step 7: Commit.** `test(contact): freeze BE-REORG-003 contracts`.

---

### Task 2: Move SQL persistence and keep repository compatibility wrapper

**Files:**
- Create: `backend/app/Modules/Contact/Infrastructure/Persistence/SqlContactRepository.php`
- Modify: `backend/app/Repositories/ContactRepository.php`
- Modify: `backend/tests/unit/BeReorg003ContactCompatibilityTest.php`

**Interfaces:**
- Consumes: Task 1 port/errors.
- Produces: canonical SQL repository; legacy repository wrapper implementing the port.

- [ ] **Step 1: Add failing tests** for explicit seven-method delegation, validation-before-DB, and read/query failures remaining `CONTACT_PERSISTENCE_FAILED` rather than becoming “not found”.
- [ ] **Step 2: Run `BeReorg003ContactCompatibilityTest.php`; verify RED** because legacy repository still owns SQL.
- [ ] **Step 3: Create `SqlContactRepository`.** Relocate current table/field allowlist, insert required fields, owner immutability, empty-update behavior, exception mapping, `insertID()`, `id ASC`, filters (`status`, `contact_type`, `query`), query columns (`name`,`email`,`phone`,`mobile`), owner existence and ward/province checks. Alias its six public error constants to `ContactErrors`.
- [ ] **Step 4: Replace legacy repository with explicit composition.** Preserve `__construct(?ConnectionInterface $db = null)`, `final`, six constants and seven public signatures; no inheritance/`__call`.
- [ ] **Step 5: Run:** `BeReorg003ContactBoundaryTest`, `BeReorg003ContactCompatibilityTest`, `P1Con001ContactServiceTest`. Existing Contact service tests must remain green.
- [ ] **Step 6: Token/AST-compare canonical SQL methods to checkpoint, ignoring only namespace/class/error-alias/wiring changes.** Any SQL/validation/order delta blocks progress.
- [ ] **Step 7: Commit.** `refactor(contact): isolate SQL repository`.

---

### Task 3: Move application service and add stateless factory/service wrapper

**Files:**
- Create: `backend/app/Modules/Contact/Application/ContactService.php`
- Create: `backend/app/Bootstrap/ContactFactory.php`
- Modify: `backend/app/Services/ContactService.php`
- Modify: both BE-REORG-003 unit test files

**Interfaces:**
- Consumes: `ContactRepositoryInterface`, `ContactErrors`, existing MutationContext/Clock/PositiveId/BusinessWorkflowWriteBoundary.
- Produces: canonical service; stateless factory; legacy service wrapper.

- [ ] **Step 1: Add failing tests** for supplied port + different DB, legacy named arguments, fresh factory instances/no static mutable state, and service error-constant aliases.
- [ ] **Step 2: Run the two new unit files; verify RED** for missing canonical service/factory/wrapper delegation.
- [ ] **Step 3: Create canonical service** with constructor `(ContactRepositoryInterface $repository, ConnectionInterface $db, ?MutationContext $mutationContext = null, ?Clock $clock = null)` and relocate current behavior/order for `create`, `update`, `inactivate`, `listForOwner`, `search` without semantic change.
- [ ] **Step 4: Create `ContactFactory`.** `repository(?ConnectionInterface): SqlContactRepository`; `service(?ContactRepositoryInterface, ?ConnectionInterface, ?MutationContext, ?Clock): ModuleContactService`. Resolve one connection per call; keep supplied repository unchanged; no caching.
- [ ] **Step 5: Replace legacy service with explicit composition wrapper.** Preserve exact accepted constructor `(?ContactRepository $repository = null, ?ConnectionInterface $db = null, ?MutationContext $mutationContext = null, ?Clock $clock = null)`, eight constants and five public methods.
- [ ] **Step 6: Run:** the two BE-REORG-003 unit files plus `P1Con001ContactServiceTest`, `P1Api010FoundationOrganizationContactApiTest`, `P1ContactAuth001OwnerAwareAuthorizationTest`.
- [ ] **Step 7: Token/AST-compare canonical service methods to checkpoint.** Workflow guard, owner validation, location validation and audit ordering must be unchanged.
- [ ] **Step 8: Commit.** `refactor(contact): isolate application service`.

---

### Task 4: Add fixed-value database characterization

**Files:**
- Create: `backend/tests/database/BeReorg003ContactCharacterizationTest.php`

**Interfaces:**
- Consumes: legacy wrapper path, canonical factory path and guarded real DB.
- Produces: nine deterministic DB cases.

- [ ] **Step 1: Write exactly nine tests:**
  1. Organization + Employee create normalize owner and persist expected fields.
  2. Missing/unsupported/unknown owners keep exact codes and do not write.
  3. Owner transfer and missing update target keep current precedence.
  4. Province/ward validation preserves missing/null/mismatch semantics.
  5. Audit fields are server-owned and fresh services use their own actor/time.
  6. Repository payload errors and owner immutability keep exact codes.
  7. Owner listing/search retain supported filters, query columns and `id ASC`.
  8. Workflow initial status/direct-state guard remain unchanged.
  9. `inactivate()` accepts only `INACTIVE`/`CLOSED` and retains current workflow/error precedence.
- [ ] **Step 2: Establish RED/GREEN honestly.** Prefer a clean accepted-checkpoint worktree for fixed expectations; otherwise revert module wiring temporarily rather than weakening assertions.
- [ ] **Step 3: Run** the new characterization file plus `P1Db020OrganizationContactTest` and `ChangeContactAuth001DraftLifecycleTest` on guarded `erp_test`.
- [ ] **Step 4: Commit.** `test(contact): characterize module behavior`.

---

### Task 5: Prove HTTP/IAM and lifecycle compatibility

**Files:**
- Create: `backend/tests/database/BeReorg003ContactHttpTest.php`
- Create: `backend/tests/database/BeReorg003ContactLifecycleTest.php`

**Interfaces:**
- Consumes: existing `/api/v1/contacts` routes, API-010, IAM, legacy wrappers and unchanged `ContactLifecycleAdapter`.
- Produces: five HTTP + four lifecycle cases; no new production behavior.

- [ ] **Step 1: Add five HTTP cases using the proven Foundation/Organization request helper pattern:** Organization CRUD/list wire contract; Employee CRUD/list wire contract; anonymous/read-only/hidden no-mutation/no-leak; visibility before count/pagination; direct status PATCH versus dedicated set-state/error-envelope behavior.
- [ ] **Step 2: Run the HTTP file.** If any expectation differs, compare with accepted checkpoint before changing production code.
- [ ] **Step 3: Add four lifecycle cases through the unchanged adapter:** Organization identity/cross-load rejection; Employee identity/cross-load rejection; Organization real set-state persists/audits; Employee real set-state persists/audits. Do not add sleeps or a new lock key.
- [ ] **Step 4: Run** both new files plus `ChangeContactAuth001DraftLifecycleTest`, `P1ContactAuth001OwnerAwareAuthorizationTest`, `P1Api010FoundationOrganizationContactApiTest`.
- [ ] **Step 5: Verify `ContactLifecycleAdapter`, API-010, controllers, authorization and workflow production files remain byte-identical.**
- [ ] **Step 6: Commit.** `test(contact): lock HTTP and lifecycle compatibility`.

---

### Task 6: Add BE-REORG-003 guarded runner and acceptance package

**Files:**
- Create: `backend/tools/test-contact.php`
- Modify: `backend/tools/test-foundation.php`
- Create: `docs/changes/BE-REORG-003/README.md`

**Interfaces:**
- Consumes: existing Foundation runner and Organization case map.
- Produces: one guarded command, one fresh JUnit evidence file, one project-root `test-result.log`.

- [ ] **Step 1: Add static/unit assertions** that the Contact runner requires only `--confirm-test-db`, launches full PHPUnit once, preserves Foundation/Organization gates, checks fresh JUnit/no skips and does not change PHPStan config/ignore/baseline.
- [ ] **Step 2: Verify RED** because Contact runner/gate support do not exist.
- [ ] **Step 3: Create `test-contact.php`** declaring existing Organization cases plus exactly 32 Contact cases: Boundary 4, Compatibility 10, Characterization 9, HTTP 5, Lifecycle 4; then reuse `test-foundation.php` rather than duplicate safety/subprocess logic.
- [ ] **Step 4: Extend `test-foundation.php` minimally** so Contact mode labels `BE-REORG-003`, checks Contact prerequisites, prints Contact-aware banner, calls JUnit gates for Foundation + Organization + Contact, then runs the unchanged PHPStan command. No second PHPUnit run.
- [ ] **Step 5: Document** scope/non-goals, patch order, guarded command, DB warning, rollback and browser/Apache UAT separation in `docs/changes/BE-REORG-003/README.md`.
- [ ] **Step 6: Local checks:** `php -l` on all new/modified PHP files and `git diff --check`.
- [ ] **Step 7: Runtime command on operator environment:** `cd D:\XAMPP\htdocs\erp\backend; php tools/test-contact.php --confirm-test-db`.
- [ ] **Step 8: Acceptance:** if no unrelated tests were added, full suite = **743 cases**; PHPUnit exit `0`; Foundation gate 9 HTTP + 4 DB-lock; Organization gate 37; Contact gate 32; no skips. PHPStan may still exit `1`, but exact normalized diagnostic set must equal the accepted 121-diagnostic baseline with zero new diagnostic.
- [ ] **Step 9: Patch integrity:** apply-check with whitespace errors enabled on an exact BE-REORG-002_FIX01 copy, apply/hash candidate, reverse-check/reverse, verify checkpoint byte-for-byte and confirm no unrelated production file changed.
- [ ] **Step 10: Commit.** `test(contact): add BE-REORG-003 runtime gate`.

---

## Self-review

- Spec coverage: architecture, compatibility, behavior, lifecycle boundary, operational safety and delivery contract all map to Tasks 1-6.
- Shared interfaces are consistent: Task 1 port/errors → Task 2 persistence → Task 3 service/factory → Tasks 4-5 behavior proof → Task 6 runtime gate.
- All five Review Focus risks have explicit tests.
- Scope excludes HR/Settings/Product/Pricing, API/IAM/lifecycle moves, new locks and opportunistic PHPStan cleanup.
- Plan contains decisions/test names/commands rather than production method bodies.
