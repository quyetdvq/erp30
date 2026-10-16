# BE-REORG-006 Numbering Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Relocate the existing Auto Numbering core into `App\Modules\Numbering` while preserving every accepted runtime behavior, public legacy entrypoint, concurrency invariant, API-040 contract, Product/Authorization caller, and the exact inherited PHPStan diagnostic set.

**Architecture:** Canonical Numbering code lives under `App\Modules\Numbering` behind narrow ports. Existing `App\Services`, `App\Repositories`, and `App\Infrastructure\Settings` entrypoints become explicit wrappers or interface bridges, composed by a stateless `App\Bootstrap\NumberingFactory`. API/Product/Authorization/value-object integration stays where it is and continues calling the legacy surface.

**Tech Stack:** PHP 8.3, CodeIgniter 4, CodeIgniter Settings, MariaDB/MySQL transaction + `FOR UPDATE`, existing `DomainLockService`/`MariaDbDomainLockService`, PHPUnit 10.5, PHPStan.

**Spec:** `docs/superpowers/specs/2026-10-16-be-reorg-006-numbering-design.md`

## Global Constraints

- Input checkpoint is exactly BE-REORG-005 + BE-REORG-005_FIX01.
- Baseline runtime is 846 PHPUnit tests / 7,446 assertions; accepted PHPStan baseline is exactly 121 inherited diagnostics with exit code 1.
- This is a physical/architectural relocation, not a feature change.
- Do not change migrations, schema, routes, OpenAPI, frontend, API-040 request/response payloads, authorization actions, feature flags, cache policy, or Product integration contracts.
- Keep `Api040AutoNumberingService`, `Controllers\Api\AutoNumbering`, `AuthorizationService`, Product callers/adapters, shared Numbering DTO/value-object contracts, routes/OpenAPI, migrations, and frontend byte-identical unless an implementation impossibility is demonstrated before changing them.
- Preserve configuration Resource-row locking and series-row `FOR UPDATE` exactly.
- Preserve counter `FOR UPDATE`, allocator transaction ownership, and first-counter lock key `auto-number:<seriesId>:<scopeOrganizationId>:<periodKey>` exactly.
- Preserve collision retry limit exactly `1000`; rollback must not consume a sequence; committed sequence values are never decremented or reused.
- Preserve business timezone resolution order company -> group -> system -> `Asia/Ho_Chi_Minh` fallback when group exists; do not substitute `Display.timezone`.
- Caller-supplied collaborators are never silently rebound or replaced. When a DB is supplied, default DB-backed collaborators for that operation share it.
- Preserve lazy/default collaborator timing where current tests can observe it; do not repeat BE005 eager-construction drift.
- Existing 68 Numbering tests are the behavioral authority; do not duplicate their business assertions under BE006 filenames.
- New BE006 PHPUnit coverage is limited to 20 relocation-specific cases: Boundary 4 + Compatibility 6 + Factory/Wiring 4 + HTTP 6.
- No PHPStan ignore/baseline/config weakening or type-erasing workaround is allowed.
- Never run two independent test processes concurrently against `erp_test`.

## Review Focus

1. **Default construction accidentally changes lazy behavior** — a legacy service constructed with null collaborators must not instantiate shared Settings/DB-backed collaborators earlier than before; pinned in Task 6 factory/wrapper tests.
2. **Mixed DB ownership** — supplying one DB connection must not create repositories/locks/organization dependencies on another connection; pinned in Task 6 factory/wiring tests.
3. **Counter first-row race** — canonical allocator must still re-read `FOR UPDATE` after advisory lock and use the exact existing key; existing `P1Num004`/`P1Db050` remain mandatory and Task 7 runner verifies they execute.
4. **Timezone resolution drift** — canonical timezone resolver must keep company/group/system/fallback precedence and invalid-timezone mapping; existing `P1Num003` remains mandatory plus Task 4 compatibility seam test.
5. **Legacy interface bridge breakage** — `AuthorizationService` and pattern validator implementations must continue satisfying legacy interfaces that extend canonical ports without source changes; pinned in Task 1 boundary test and out-of-scope byte-integrity gate in Task 8.

---

## File Structure

### Canonical files to create

- `backend/app/Modules/Numbering/Configuration/Application/Ports/NumberingSeriesRepositoryInterface.php`
- `backend/app/Modules/Numbering/Configuration/Application/AutoNumberingConfigurationService.php`
- `backend/app/Modules/Numbering/Configuration/Infrastructure/Persistence/SqlNumberingSeriesRepository.php`
- `backend/app/Modules/Numbering/Allocation/Application/Ports/NumberingCounterRepositoryInterface.php`
- `backend/app/Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php`
- `backend/app/Modules/Numbering/Allocation/Infrastructure/Persistence/SqlNumberingCounterRepository.php`
- `backend/app/Modules/Numbering/Scope/Application/Ports/BusinessTimezoneProviderInterface.php`
- `backend/app/Modules/Numbering/Scope/Application/AutoNumberingScopePeriodResolver.php`
- `backend/app/Modules/Numbering/Scope/Infrastructure/Settings/CodeIgniterBusinessTimezoneResolver.php`
- `backend/app/Modules/Numbering/Pattern/Application/Ports/NumberingPatternValidatorInterface.php`
- `backend/app/Modules/Numbering/Pattern/Application/Ports/NumberingPatternCompatibilityValidatorInterface.php`
- `backend/app/Modules/Numbering/Pattern/Application/CanonicalNumberingPatternValidator.php`
- `backend/app/Modules/Numbering/Issuance/Application/AutoNumberService.php`
- `backend/app/Modules/Numbering/Authorization/Application/Ports/NumberingAuthorizationPortInterface.php`
- `backend/app/Bootstrap/NumberingFactory.php`

### Legacy files to modify into explicit wrappers/bridges

- `backend/app/Repositories/AutoNumberingSeriesRepository.php`
- `backend/app/Repositories/AutoNumberingCounterRepository.php`
- `backend/app/Services/AutoNumberingConfigurationService.php`
- `backend/app/Services/AutoNumberingCounterAllocator.php`
- `backend/app/Services/AutoNumberingScopePeriodResolver.php`
- `backend/app/Services/AutoNumberService.php`
- `backend/app/Services/CanonicalNumberingPatternValidator.php`
- `backend/app/Services/NumberingAuthorizationPort.php`
- `backend/app/Services/NumberingPatternValidator.php`
- `backend/app/Services/NumberingPatternCompatibilityValidator.php`
- `backend/app/Infrastructure/Settings/BusinessTimezoneProvider.php`
- `backend/app/Infrastructure/Settings/BusinessTimezoneResolver.php`

### New BE006 tests/tools

- `backend/tests/unit/BeReorg006NumberingBoundaryTest.php` — 4 cases
- `backend/tests/unit/BeReorg006NumberingCompatibilityTest.php` — 6 cases
- `backend/tests/unit/BeReorg006NumberingFactoryTest.php` — 4 cases
- `backend/tests/unit/BeReorg006NumberingHttpTest.php` — 6 cases
- `backend/tools/test-numbering.php`
- modify `backend/tools/test-foundation.php` only to register/report BE006 + existing Numbering coverage gates while retaining one full PHPUnit process.

Expected new test count: `20`; expected full suite if no unrelated tests are added: `866`.

---

### Task 1: Canonical ports and module boundary

**Files:**
- Create the five canonical port interfaces listed above.
- Modify legacy interfaces `NumberingAuthorizationPort.php`, `NumberingPatternValidator.php`, `NumberingPatternCompatibilityValidator.php` to extend canonical ports.
- Create/Test: `backend/tests/unit/BeReorg006NumberingBoundaryTest.php` (4 cases total).

**Interfaces:**
- Produces `NumberingSeriesRepositoryInterface` with the exact 17-method persistence surface from the spec.
- Produces `NumberingCounterRepositoryInterface` with exact `find`, `findForUpdate`, `insert`, `updateLastValue` signatures.
- Produces `BusinessTimezoneProviderInterface::resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone`.
- Produces pattern and authorization canonical interfaces exactly as specified.
- Legacy interfaces extend canonical equivalents; no method is removed or widened.

- [ ] **Step 1: Write the four failing boundary tests**
  - `testCanonicalNumberingPortsExposeExactContracts`
  - `testLegacyInterfacesExtendCanonicalPorts`
  - `testCanonicalNamespaceContainsOnlyApprovedBoundaryTypes`
  - `testOutOfScopeIntegrationTypesRemainOutsideNumberingModule`

- [ ] **Step 2: Run boundary test and verify RED**

Run: `php vendor/bin/phpunit --configuration phpunit.dist.xml tests/unit/BeReorg006NumberingBoundaryTest.php`

Expected: FAIL because canonical Numbering interfaces/namespaces do not exist yet.

- [ ] **Step 3: Add the canonical interfaces and legacy interface bridges**

Do not create concrete implementations in this task.

- [ ] **Step 4: Run boundary test and verify GREEN**

Expected: 4/4 PASS.

- [ ] **Step 5: PHP lint touched PHP files and commit**

Commit message: `refactor(numbering): define canonical ports`

---

### Task 2: Relocate series and counter persistence

**Files:**
- Create `SqlNumberingSeriesRepository.php` and `SqlNumberingCounterRepository.php` by mechanically relocating current repository behavior.
- Modify both legacy repository files into explicit delegates preserving constants, constructor `__construct(?ConnectionInterface $db = null)`, public method names, aliases, return behavior, and exception mapping.
- Extend `BeReorg006NumberingCompatibilityTest.php` with repository cases.

**Interfaces:**
- Consumes Task 1 repository ports.
- Canonical SQL repositories implement the canonical ports.
- Legacy repositories own an explicit canonical delegate; no `__call`, concrete inheritance, aliasing, or reflection dispatch.

- [ ] **Step 1: Write two failing compatibility tests**
  - `testLegacySeriesRepositorySignatureConstantsAndSelectedBehaviorMatchCanonical`
  - `testLegacyCounterRepositorySignatureConstantsAndSelectedBehaviorMatchCanonical`

Each test uses one data-provider row set for public method/signature/constant parity and selected real repository behavior; do not duplicate existing DB business cases.

- [ ] **Step 2: Verify RED**

Run the compatibility file; expected failure is missing canonical SQL repository/delegation.

- [ ] **Step 3: Move repository bodies mechanically behind ports and add wrappers**

Keep field allowlists, SQL, transaction helper, `FOR UPDATE`, duplicate/FK/error mapping, OperationContext audit handling, and method aliases byte-equivalent in behavior.

- [ ] **Step 4: Verify GREEN and run existing persistence-sensitive Numbering tests**

Run:
`php vendor/bin/phpunit --configuration phpunit.dist.xml tests/unit/P1Num001AutoNumberingTest.php tests/unit/P1Num004CounterAllocatorTest.php tests/database/P1Db050ResourceAuthorizationNumberingTest.php tests/unit/BeReorg006NumberingCompatibilityTest.php`

Expected: all selected tests PASS.

- [ ] **Step 5: Commit**

Commit message: `refactor(numbering): relocate persistence`

---

### Task 3: Relocate pattern validation

**Files:**
- Create canonical `Pattern/Application/CanonicalNumberingPatternValidator.php`.
- Modify legacy pattern validator class into explicit delegate preserving constants and public `parse`, `validate`, `validateSeriesSet` surface.
- Extend compatibility test with one pattern bridge case.

**Interfaces:**
- Consumes Task 1 pattern ports.
- Continues returning legacy shared `App\Services\ParsedNumberingPattern` objects; that DTO does not move.

- [ ] **Step 1: Write failing `testLegacyPatternValidatorDelegatesToCanonicalWithoutGrammarDrift`**

Assert constant parity, exact public signatures, representative parse output, and representative error code parity only; existing `P1Num002` remains the exhaustive grammar authority.

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Move validator body mechanically and add wrapper**

Preserve token list, width rules, reset/scope/namespace compatibility, collision signature, and error precedence exactly.

- [ ] **Step 4: Verify GREEN plus all 9 existing pattern tests**

Run: `php vendor/bin/phpunit --configuration phpunit.dist.xml tests/unit/P1Num002NumberingPatternValidatorTest.php tests/unit/BeReorg006NumberingCompatibilityTest.php`

- [ ] **Step 5: Commit**

Commit message: `refactor(numbering): relocate pattern validation`

---

### Task 4: Relocate BusinessTimezone and scope/period resolution

**Files:**
- Create canonical `CodeIgniterBusinessTimezoneResolver.php` and `AutoNumberingScopePeriodResolver.php`.
- Modify legacy `BusinessTimezoneProvider`, `BusinessTimezoneResolver`, and `AutoNumberingScopePeriodResolver` into interface bridge/explicit wrappers.
- Keep `backend/app/Config/Services.php` byte-identical.
- Extend compatibility test with two cases: timezone + scope resolver.

**Interfaces:**
- Canonical timezone resolver implements Task 1 provider port and keeps `DEFAULT_GROUP_TIMEZONE = 'Asia/Ho_Chi_Minh'`.
- Canonical scope resolver continues consuming legacy shared DTOs `NumberingScopePeriodContext`, `ParsedNumberingPattern`, `EvaluationOrganizationContext` and `OperationContext`.
- Legacy scope constructor remains exactly `(?OrganizationRepository $organizations = null, ?CompanyOwnerResolver $companyOwners = null, ?BusinessTimezoneProvider $timezones = null, ?ConnectionInterface $db = null)`.

- [ ] **Step 1: Write failing compatibility tests**
  - `testLegacyBusinessTimezoneResolverPreservesProviderAndResolutionSemantics`
  - `testLegacyScopeResolverPreservesConstructorErrorsAndRepresentativeResult`

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Relocate timezone and scope resolver bodies mechanically**

Do not change `Config\Services::businessTimezone()` source. Preserve Settings exception handling, fallback order, authoritative organization checks, period-key formatting, evaluationTime usage, and existing errors.

- [ ] **Step 4: Verify GREEN plus existing scope tests**

Run: `php vendor/bin/phpunit --configuration phpunit.dist.xml tests/unit/P1Num003ScopePeriodResolverTest.php tests/unit/BeReorg006NumberingCompatibilityTest.php`

Expected: 12 existing scope tests + compatibility tests PASS.

- [ ] **Step 5: Commit**

Commit message: `refactor(numbering): relocate scope and timezone`

---

### Task 5: Relocate counter allocator, configuration service, and issuance service

**Files:**
- Create canonical `Allocation/Application/AutoNumberingCounterAllocator.php`.
- Create canonical `Configuration/Application/AutoNumberingConfigurationService.php`.
- Create canonical `Issuance/Application/AutoNumberService.php`.
- Modify the three corresponding legacy service files into explicit compatibility wrappers.
- Extend compatibility test with one grouped service case so the file totals 6 cases.

**Interfaces:**
- Canonical services consume canonical repository/authorization/pattern/timezone ports where possible but keep shared legacy DTO/business-mutation/adapter-registry types as specified.
- Legacy constructors retain exact names/types/order/defaults from spec sections 5.1-5.4.
- Counter allocator retains `MAX_CANDIDATE_ATTEMPTS = 1000` behavior and exact lock key.

- [ ] **Step 1: Write failing `testLegacyCoreServicesPreserveSignaturesConstantsAliasesAndSelectedSeams`**

Use a data provider to cover configuration, allocator, and issuance wrappers in one PHPUnit case family without recreating business characterization. Assert exact constructor Reflection signature and selected canonical-vs-legacy seam result/error parity.

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Relocate allocator body mechanically**

Preserve BaseConnection guard, existing-transaction rejection, transaction begin/commit/rollback/reset, `findForUpdate`, exact advisory lock key, lock re-read before first insert, retry ordering, 1000-attempt exhaustion, and lock/error mapping.

- [ ] **Step 4: Relocate configuration body mechanically**

Preserve resource locks, ascending lock order, series row locks, authorization, mutation context, issued-field immutability, pattern/adapter integration, aliases, and error precedence.

- [ ] **Step 5: Relocate issuance body mechanically**

Preserve series selection, generation modes, manual path, adapter metadata, collision bridge, result DTOs, and aliases.

- [ ] **Step 6: Verify GREEN with all behavior-authority tests touching moved services**

Run all 68 existing Numbering tests plus `BeReorg006NumberingCompatibilityTest.php`.

Expected: all selected tests PASS; no skipped tests.

- [ ] **Step 7: Commit**

Commit message: `refactor(numbering): relocate core services`

---

### Task 6: Add stateless NumberingFactory and default wiring

**Files:**
- Create `backend/app/Bootstrap/NumberingFactory.php`.
- Adjust legacy wrappers only where necessary to use factory helpers for default construction while preserving observable laziness.
- Create `backend/tests/unit/BeReorg006NumberingFactoryTest.php` (4 cases).

**Interfaces:**
- Factory is stateless; no shared mutable singleton state.
- Supplied dependency always wins.
- Supplied DB is shared by all default DB-backed collaborators created for the same service composition.
- No API/controller call; no modification to `Config\Services::businessTimezone()` semantics.

- [ ] **Step 1: Write four failing factory tests**
  - `testCallerSuppliedCollaboratorsAreNeverRebound`
  - `testSuppliedDbIsSharedAcrossDefaultRepositoriesLocksAndOrganizationDependencies`
  - `testNullDefaultsPreserveLegacyLazyConstructionBoundary`
  - `testFactoryDoesNotChangeBusinessTimezoneSharedServiceSemantics`

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Implement minimal `NumberingFactory` composition helpers**

Only create helpers actually consumed by wrappers. Avoid a generic service locator/container facade.

- [ ] **Step 4: Verify GREEN and rerun the 68 existing Numbering tests**

- [ ] **Step 5: Commit**

Commit message: `refactor(numbering): add composition factory`

---

### Task 7: Add six real HTTP relocation tests and guarded runner

**Files:**
- Create `backend/tests/unit/BeReorg006NumberingHttpTest.php` (6 cases).
- Create `backend/tools/test-numbering.php`.
- Modify `backend/tools/test-foundation.php` minimally to register/report BE006 and the 68 existing Numbering cases.

**Interfaces:**
- HTTP tests use the real CodeIgniter route/controller stack (`FeatureTestTrait`/existing project pattern), not direct `Api040AutoNumberingService` calls.
- Existing API040 production files remain byte-identical.
- Runner invokes full PHPUnit exactly once, then PHPStan once only after PHPUnit success.

- [ ] **Step 1: Write six failing HTTP tests, one per API-040 operation**
  - list series
  - create series
  - get series
  - patch series
  - set-state action
  - validate-config action

Assert existing status/envelope semantics; do not invent new API behavior.

- [ ] **Step 2: Verify RED for relocation setup if canonical/wrapper path is incomplete, then GREEN through real route stack**

- [ ] **Step 3: Add runner case maps**

Define:

```text
BE_REORG_NUMBERING_CASES = 20
BE_REORG_NUMBERING_EXISTING_CASES = 68
```

Breakdown for new cases:

```text
Boundary       4
Compatibility  6
Factory        4
HTTP           6
---------------
Total         20
```

- [ ] **Step 4: Ensure runner executes exactly one full PHPUnit process**

`test-numbering.php --confirm-test-db` must reuse `test-foundation.php`; no second targeted PHPUnit process is allowed inside the runner.

- [ ] **Step 5: Gate all prior reorg suites, all 68 existing Numbering tests, and all 20 BE006 tests as executed/not-skipped**

Expected full suite when no unrelated tests were added: `866` tests.

- [ ] **Step 6: Commit**

Commit message: `test(numbering): add BE006 verification gates`

---

### Task 8: Whole-scope verification, exact PHPStan comparison, review, and patch round-trip

**Files:**
- No product behavior changes unless a verified Critical/Important review finding requires one.
- Produce evidence/package artifacts outside the repo.

**Interfaces:**
- Consumes completed Tasks 1-7.
- Produces one incremental patch applicable to exact BE-REORG-005_FIX01.

- [ ] **Step 1: Run fresh PHP lint for every changed/new PHP file**

Expected: all PASS.

- [ ] **Step 2: Run `git diff --check`**

Expected: exit 0.

- [ ] **Step 3: Verify out-of-scope byte integrity**

Hash before/after at minimum:
`Api040AutoNumberingService.php`, `Controllers/Api/AutoNumbering.php`, `Config/Routes.php`, `Authorization/AuthorizationService.php`, Product master/numbering adapter files, shared Numbering DTO/value-object files, migrations, OpenAPI/contracts touched by API040, and frontend tree.

Expected: identical.

- [ ] **Step 4: Run legacy signature/constant/interface probes**

Compare Reflection-visible constructor names/types/order/defaults and public method surfaces against pre-BE006 checkpoint. Expected: exact callable compatibility.

- [ ] **Step 5: Run full operator-equivalent gate where runtime dependencies are available**

Run: `php tools/test-numbering.php --confirm-test-db`

Expected if no unrelated tests were added:
- PHPUnit 866/866, exit 0.
- all prior coverage gates none skipped.
- BE006 20/20 none skipped.
- existing Numbering 68/68 none skipped.
- PHPStan exits 1 with exactly the accepted 121 inherited diagnostics.

If local `vendor/`/DB runtime is unavailable, do not claim runtime success; record static evidence only and require operator run.

- [ ] **Step 6: Compare PHPStan diagnostic identity/content, not only count**

Normalize away runner timestamps/footer only. Diagnostic block must match accepted BE-REORG-005_FIX01 baseline exactly; new BE006 diagnostics = 0.

- [ ] **Step 7: Whole-branch review**

Use a fresh reviewer if available; otherwise record self-review explicitly. Grade findings by runtime effect. Fix Critical/Important findings in one TDD pass; defer only Minor findings.

- [ ] **Step 8: Generate incremental patch and verify round trip**

Patch base: exact BE-REORG-005_FIX01 checkpoint.

Required checks:
- `git apply --check` PASS on base.
- applying patch reconstructs candidate byte-for-byte for all touched files.
- reverse apply restores base byte-for-byte.

- [ ] **Step 9: Package evidence**

Record patch SHA-256, package SHA-256, changed-file list, test inventory, lint/diff/signature/hash results, review mode/findings, and runtime limitation/acceptance status.

---

## Self-Review Record

- **Spec coverage:** Every migration-sequence item in spec section 13 maps to Tasks 1-7; verification/patch requirements map to Task 8.
- **Step scan:** No implementation body is prescribed except exact invariant values/keys; each test/code/verification action is separable.
- **Type consistency:** Legacy interfaces extend canonical ports; shared DTO/value objects remain in legacy namespaces throughout every task; factory consumes the same canonical ports produced by Tasks 1-4.
- **Review Focus:** All five high-risk conditions are pinned either by new relocation tests or mandatory execution of existing Numbering authority tests.
- **Proportion:** Plan deliberately adds only 20 relocation-specific PHPUnit cases and reuses all 68 existing Numbering tests; no duplicate business characterization suite is introduced.
