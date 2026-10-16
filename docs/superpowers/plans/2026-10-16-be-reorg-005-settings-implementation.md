# BE-REORG-005 Settings / Display Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Relocate the existing Display Settings core into `App\Modules\Settings\Display` while preserving every accepted runtime/API/authorization/audit behavior and all legacy entrypoints.

**Architecture:** Introduce two narrow application ports, module-owned storage/audit persistence, canonical resolver/mutation services, and a stateless `SettingsFactory`. Legacy Settings classes remain explicit composition wrappers; `DisplaySettingRegistry`, `DisplaySettingsContext`, business timezone, API facade/controller/routes/OpenAPI, IAM, Organization behavior, and Auto Numbering remain in place.

**Tech Stack:** PHP 8.3, CodeIgniter 4, CodeIgniter Settings, PHPUnit 10.5, PHPStan, MySQL/MariaDB test database.

**Spec:** `docs/superpowers/specs/2026-10-16-be-reorg-005-settings-design.md`

## Global Constraints

- Start from accepted `BE-REORG-004_FIX01`: 807 PHPUnit tests / 7,214 assertions and exactly 121 inherited PHPStan diagnostics.
- This is physical relocation only: no schema, migration, route, OpenAPI, frontend, IAM, Organization, Workflow, Auto Numbering, or business-timezone behavior changes.
- Keep `DisplaySettingRegistry` and `DisplaySettingsContext` in their existing namespaces and behavior.
- `ApiDisplaySettingsService`, `App\Controllers\Api\DisplaySettings`, `backend/app/Config/Routes.php`, and `contracts/openapi/openapi.yaml` must remain byte-identical to the accepted checkpoint.
- Keep all legacy constructor parameter names/defaults and public method signatures exactly; wrappers use explicit delegation only, never inheritance or `__call`.
- Preserve exactly 15 registry definitions and only SYSTEM/ORGANIZATION contexts.
- Preserve Organization resolution order: target → parent chain → group → system → registry default; sibling overrides never participate.
- Preserve full-batch validation, PBAC `display_setting/edit`, strict canonical no-op detection, idempotent reset, one DB transaction for settings + audit, trusted operation fields, deterministic `id ASC` audit reads, and zero new locks.
- `Display.timezone` remains presentation-only and does not replace `App.businessTimezone`.
- Do not change PHPStan config/baseline or hide diagnostics; acceptance is exact normalized equality with the inherited 121-diagnostic set.
- Runtime tests may only target confirmed `127.0.0.1:3306/erp_test` with `dinktech_` prefix and must not run concurrently.
- No Composer update, manual migration/seed/reset, client generation, browser/Apache UAT, or destructive business-DB command.

## File Structure

**Create production:**
- `backend/app/Bootstrap/SettingsFactory.php` — stateless wiring for module storage/resolver/audit/mutation.
- `backend/app/Modules/Settings/Display/Contracts/DisplaySettingsErrors.php` — exact three Display mutation error codes.
- `backend/app/Modules/Settings/Display/Contracts/ConfigurationAuditErrors.php` — exact three audit error codes.
- `backend/app/Modules/Settings/Display/Application/Ports/DisplaySettingsStorageInterface.php` — four-method storage port.
- `backend/app/Modules/Settings/Display/Application/Ports/ConfigurationAuditEventWriterInterface.php` — one-method audit writer port.
- `backend/app/Modules/Settings/Display/Application/DisplaySettingsResolver.php` — canonical inheritance resolver.
- `backend/app/Modules/Settings/Display/Application/DisplaySettingsMutationService.php` — canonical authorized atomic mutation boundary.
- `backend/app/Modules/Settings/Display/Infrastructure/CodeIgniterDisplaySettingsStorage.php` — canonical CodeIgniter Settings adapter.
- `backend/app/Modules/Settings/Display/Infrastructure/SqlConfigurationAuditEventRepository.php` — canonical insert-only SQL audit sink.

**Modify legacy production wrappers:**
- `backend/app/Infrastructure/Settings/CodeIgniterDisplaySettingsStorage.php`
- `backend/app/Infrastructure/Settings/DisplaySettingsResolver.php`
- `backend/app/Services/DisplaySettingsMutationService.php`
- `backend/app/Repositories/ConfigurationAuditEventRepository.php`
- `backend/app/Repositories/ConfigurationAuditEventWriter.php`
- `backend/tools/test-foundation.php`

**Create verification/runtime support:**
- `backend/tests/unit/BeReorg005SettingsBoundaryTest.php`
- `backend/tests/unit/BeReorg005SettingsCompatibilityTest.php`
- `backend/tests/database/BeReorg005SettingsStorageResolverCharacterizationTest.php`
- `backend/tests/database/BeReorg005SettingsMutationAuditCharacterizationTest.php`
- `backend/tests/database/BeReorg005SettingsHttpTest.php`
- `backend/tests/_support/Settings/RuntimeFixture.php`
- `backend/tools/test-settings.php`
- `docs/changes/BE-REORG-005/README.md`

**Must remain byte-identical:**
- `backend/app/Infrastructure/Settings/DisplaySettingRegistry.php`
- `backend/app/Infrastructure/Settings/DisplaySettingsContext.php`
- `backend/app/Infrastructure/Settings/BusinessTimezoneProvider.php`
- `backend/app/Infrastructure/Settings/BusinessTimezoneResolver.php`
- `backend/app/Services/ApiDisplaySettingsService.php`
- `backend/app/Controllers/Api/DisplaySettings.php`
- `backend/app/Config/Routes.php`
- `contracts/openapi/openapi.yaml`

## Canonical Interfaces Locked by This Plan

`DisplaySettingsStorageInterface`:

```php
public function get(string $key, DisplaySettingsContext|string $context): mixed;
public function set(string $key, mixed $value, DisplaySettingsContext|string $context): void;
public function forget(string $key, DisplaySettingsContext|string $context): void;
/** @return array{exists: bool, value: mixed} */
public function persistedOverride(string $key, DisplaySettingsContext|string $context): array;
```

`ConfigurationAuditEventWriterInterface`:

```php
/** @param array<string, mixed> $event */
public function insert(array $event): int;
```

Canonical resolver:

```php
public function __construct(
    DisplaySettingsStorageInterface $storage,
    OrganizationService $organizations,
)
public function resolve(string $key, int|string|null $organizationId = null): mixed;
/** @return array<string, mixed> */
public function resolveAll(int|string|null $organizationId = null): array;
/** @return list<DisplaySettingsContext> */
public function contextsFor(int|string|null $organizationId = null): array;
```

Canonical mutation service:

```php
public function __construct(
    DisplaySettingsStorageInterface $storage,
    AuthorizationService $authorization,
    ConfigurationAuditEventWriterInterface $audit,
    OrganizationService $organizations,
    OrganizationRepositoryInterface $organizationRepository,
    BaseConnection $db,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
)
/** @param array<array-key, mixed> $values @return list<string> */
public function setBatch(string|DisplaySettingsContext $context, array $values, ?OperationContext $operation = null): array;
/** @param array<array-key, mixed> $keys @return list<string> */
public function resetBatch(string|DisplaySettingsContext $context, array $keys, ?OperationContext $operation = null): array;
```

`SqlConfigurationAuditEventRepository` additionally keeps:

```php
/** @return list<array<string, mixed>> */
public function findAll(): array;
public static function encodeValue(mixed $value): ?string;
```

`SettingsFactory` exposes stateless static factories:

```php
public static function storage(?Settings $settings = null, ?BaseConnection $db = null): ModuleStorage;
public static function resolver(?DisplaySettingsStorageInterface $storage = null, ?OrganizationService $organizations = null, ?BaseConnection $db = null): ModuleResolver;
public static function audit(?ConnectionInterface $db = null): SqlConfigurationAuditEventRepository;
public static function mutation(
    ?DisplaySettingsStorageInterface $storage = null,
    ?AuthorizationService $authorization = null,
    ?ConfigurationAuditEventWriterInterface $audit = null,
    ?OrganizationService $organizations = null,
    ?OrganizationRepositoryInterface $organizationRepository = null,
    ?BaseConnection $db = null,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
): ModuleMutationService;
```

Factory rule: resolve one `$db` for each mutation call and use it for all default collaborators plus the transaction; any explicitly supplied collaborator remains authoritative and is never rebound.

## Test Inventory / Runtime Gate

Add exactly **39 BE-REORG-005 cases**:

- `BeReorg005SettingsBoundaryTest` — 5
- `BeReorg005SettingsCompatibilityTest` — 11
- `BeReorg005SettingsStorageResolverCharacterizationTest` — 8
- `BeReorg005SettingsMutationAuditCharacterizationTest` — 10
- `BeReorg005SettingsHttpTest` — 5

Also explicitly gate the existing Settings suites:

- `P1Dsp001DisplaySettingsTest` — 11
- `P1Dsp002DisplaySettingsResolverTest` — 7
- `P1Dsp003DisplaySettingsMutationServiceTest` — 5
- `P1Dsp004DisplaySettingsApiTest` — 5
- `P1Db003ConfigurationAuditEventsTest` — 1

If no unrelated test is added concurrently, expected full suite is **846 tests** (`807 + 39`). The runner is authoritative if the repository changes in parallel.

## Review Focus

- **Injected collaborator + separate `$db`:** supplied storage/audit/auth/Organization instances must remain authoritative while `$db` still owns the transaction/default collaborators; Task 5 compatibility test pins this.
- **Missing/ambiguous GROUP root:** SYSTEM mutation must fail closed and produce zero setting/audit writes; Task 5 characterization pins this.
- **Rollback-sensitive authorization fixture:** HTTP/mutation fixtures that create Contact-style authorization rows must clean them before `DatabaseTestTrait` refresh; Task 6 fixture test/teardown pins this.
- **Invalid persisted legacy values:** an invalid nearest override must be skipped in favor of a valid inherited/system/default value, never normalized into a write; Task 4 characterization pins this.
- **Strict canonical no-op equality:** equal value and equal PHP type is a no-op; type-different values are not silently treated as equal; Task 5 characterization pins this.

---

### Task 1: Freeze module boundaries, ports, and error vocabulary

**Files:**
- Create: `backend/app/Modules/Settings/Display/Contracts/DisplaySettingsErrors.php`
- Create: `backend/app/Modules/Settings/Display/Contracts/ConfigurationAuditErrors.php`
- Create: `backend/app/Modules/Settings/Display/Application/Ports/DisplaySettingsStorageInterface.php`
- Create: `backend/app/Modules/Settings/Display/Application/Ports/ConfigurationAuditEventWriterInterface.php`
- Create: `backend/tests/unit/BeReorg005SettingsBoundaryTest.php`

**Interfaces:**
- Consumes: retained `DisplaySettingsContext`.
- Produces: exact ports/error constants consumed by Tasks 2–5.

- [ ] **Step 1: Write five failing boundary tests**

Create tests proving: (1) exact four storage-port methods/signatures, (2) exact audit-writer method/signature, (3) exact three Display errors, (4) exact three audit errors, (5) module tree does not define/move `DisplaySettingRegistry`, `DisplaySettingsContext`, `BusinessTimezoneProvider`, or `BusinessTimezoneResolver` and does not reference Auto Numbering classes.

- [ ] **Step 2: Run boundary test RED**

Run: `php vendor/bin/phpunit tests/unit/BeReorg005SettingsBoundaryTest.php --colors=never`
Expected: FAIL because module ports/contracts do not exist.

- [ ] **Step 3: Implement minimal ports and final error catalogs**

Use separate PHPDoc lines for iterable value types so PHPStan recognizes all generics; do not introduce aliases or extra methods.

- [ ] **Step 4: Run boundary test GREEN**

Run: `php vendor/bin/phpunit tests/unit/BeReorg005SettingsBoundaryTest.php --colors=never`
Expected: `OK (5 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Modules/Settings/Display/Contracts backend/app/Modules/Settings/Display/Application/Ports backend/tests/unit/BeReorg005SettingsBoundaryTest.php
git commit -m "test(settings): freeze BE-REORG-005 contracts"
```

### Task 2: Relocate storage and configuration-audit persistence

**Files:**
- Create: `backend/app/Modules/Settings/Display/Infrastructure/CodeIgniterDisplaySettingsStorage.php`
- Create: `backend/app/Modules/Settings/Display/Infrastructure/SqlConfigurationAuditEventRepository.php`
- Modify: `backend/app/Infrastructure/Settings/CodeIgniterDisplaySettingsStorage.php`
- Modify: `backend/app/Repositories/ConfigurationAuditEventRepository.php`
- Modify: `backend/app/Repositories/ConfigurationAuditEventWriter.php`
- Create/extend: `backend/tests/unit/BeReorg005SettingsCompatibilityTest.php` (first 4 of final 11 cases)

**Interfaces:**
- Consumes: Task 1 ports/errors and retained Registry/Context.
- Produces: canonical storage/audit implementations and legacy explicit delegates for Tasks 3–5.

- [ ] **Step 1: Write four failing compatibility tests**

Pin: legacy storage exact constructor + four public methods + port implementation; legacy audit repository exact constructor/methods/constants + writer compatibility; legacy writer interface extends the module writer port; legacy wrappers are final, have no parent class and no `__call`.

- [ ] **Step 2: Run compatibility RED**

Run: `php vendor/bin/phpunit tests/unit/BeReorg005SettingsCompatibilityTest.php --colors=never`
Expected: FAIL because canonical persistence classes/delegation do not exist.

- [ ] **Step 3: Move bodies mechanically into module persistence**

`Module\...\CodeIgniterDisplaySettingsStorage` keeps the existing storage algorithm/body, Registry/Context usage, `Settings` dependency, nullable `BaseConnection`, decode behavior and `persistedOverride()` shape. `SqlConfigurationAuditEventRepository` keeps insert validation, immutable insert-only behavior, `findAll()` `id ASC`, and `encodeValue()` flags/exception mapping.

- [ ] **Step 4: Replace legacy classes/interfaces with explicit delegates**

Keep every legacy constructor/method/constant exactly. Legacy `ConfigurationAuditEventWriter` extends `ConfigurationAuditEventWriterInterface`. Legacy repository static `encodeValue()` forwards explicitly to the module repository. No inheritance or magic dispatch.

- [ ] **Step 5: Verify GREEN and body parity**

Run: `php vendor/bin/phpunit tests/unit/BeReorg005SettingsCompatibilityTest.php --colors=never`
Expected: first 4 compatibility cases PASS.

Run a source parity probe comparing old canonical bodies for `get/set/forget/persistedOverride/coordinates/decode/context` and `insert/findAll/encodeValue` against their relocated module bodies after namespace/type substitutions.
Expected: all behavioral bodies match.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Settings/Display/Infrastructure backend/app/Infrastructure/Settings/CodeIgniterDisplaySettingsStorage.php backend/app/Repositories/ConfigurationAuditEventRepository.php backend/app/Repositories/ConfigurationAuditEventWriter.php backend/tests/unit/BeReorg005SettingsCompatibilityTest.php
git commit -m "refactor(settings): isolate display persistence"
```

### Task 3: Relocate the resolution application service

**Files:**
- Create: `backend/app/Modules/Settings/Display/Application/DisplaySettingsResolver.php`
- Modify: `backend/app/Infrastructure/Settings/DisplaySettingsResolver.php`
- Extend: `backend/tests/unit/BeReorg005SettingsCompatibilityTest.php` (add 2; cumulative 6/11)
- Create: `backend/tests/database/BeReorg005SettingsStorageResolverCharacterizationTest.php` (8 cases)

**Interfaces:**
- Consumes: `DisplaySettingsStorageInterface`; retained `OrganizationService`, Registry, Context.
- Produces: canonical resolver and legacy resolver delegate used by API/runtime callers.

- [ ] **Step 1: Write two compatibility + eight characterization tests RED**

Compatibility: exact legacy resolver constructor/methods; explicit final delegate with no inheritance/magic dispatch.

Characterization cases: storage set/get/forget/persisted shape; nearest ancestor wins; siblings excluded; system/default fallback; invalid nearest persisted value skipped; reparenting immediately changes path; unknown key/target fails closed; `Display.timezone` remains independent of business timezone.

- [ ] **Step 2: Run RED**

Run: `php vendor/bin/phpunit tests/unit/BeReorg005SettingsCompatibilityTest.php tests/database/BeReorg005SettingsStorageResolverCharacterizationTest.php --colors=never`
Expected: new cases FAIL because canonical resolver/delegate do not exist.

- [ ] **Step 3: Move resolver body to canonical application service**

Use only the storage port plus retained `OrganizationService`, Registry and Context. Preserve `array_reverse(resolveAncestorChain())`, invalid-value skip, registry default, and exact context order.

- [ ] **Step 4: Replace legacy resolver with explicit delegate**

Exact constructor remains `__construct(?CodeIgniterDisplaySettingsStorage $storage = null, ?OrganizationService $organizations = null)`; defaults must preserve current behavior and the supplied legacy storage stays authoritative.

- [ ] **Step 5: Run GREEN**

Run: same PHPUnit command.
Expected: cumulative 6 compatibility + 8 characterization cases PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Modules/Settings/Display/Application/DisplaySettingsResolver.php backend/app/Infrastructure/Settings/DisplaySettingsResolver.php backend/tests/unit/BeReorg005SettingsCompatibilityTest.php backend/tests/database/BeReorg005SettingsStorageResolverCharacterizationTest.php
git commit -m "refactor(settings): isolate display resolution"
```

### Task 4: Relocate mutation logic and introduce `SettingsFactory`

**Files:**
- Create: `backend/app/Modules/Settings/Display/Application/DisplaySettingsMutationService.php`
- Create: `backend/app/Bootstrap/SettingsFactory.php`
- Modify: `backend/app/Services/DisplaySettingsMutationService.php`
- Extend: `backend/tests/unit/BeReorg005SettingsCompatibilityTest.php` (add final 5; 11 total)
- Create: `backend/tests/database/BeReorg005SettingsMutationAuditCharacterizationTest.php` (10 cases)
- Create: `backend/tests/_support/Settings/RuntimeFixture.php`

**Interfaces:**
- Consumes: both Task 1 ports, module persistence from Task 2, Organization `OrganizationRepositoryInterface`, retained `AuthorizationService`/`OrganizationService`, mutation context/clock.
- Produces: canonical authorized mutation service, stateless factory, unchanged legacy mutation surface, shared Settings fixture for Task 5.

- [ ] **Step 1: Write five compatibility + ten characterization tests RED**

Compatibility cases pin: exact legacy mutation constructor parameter names/order/defaults; exact three constants and two methods; explicit final delegate; factory has no instance/static mutable state and fresh calls produce fresh canonical objects; factory default mutation collaborators share one resolved DB; injected storage/audit/auth/Organization/OrganizationRepository collaborators remain authoritative even when a different `$db` owns the transaction.

Characterization cases pin: authorized multi-key set writes ordered changed keys + canonical audit; full input validation occurs before any write; equal-value/equal-type set is no-op while type-different input is not collapsed by wrapper logic; missing reset override is no-op/no-audit; reset existing override audits canonical old→null; missing Organization fails closed; missing/ambiguous GROUP root fails closed; DENY/ABSOLUTE_DENY yields zero writes; audit insert failure rolls back every setting write; audit repository validates fields, JSON-encodes canonically, trusts operation actor/request/time, and `findAll()` is `id ASC`.

- [ ] **Step 2: Run RED**

Run: `php vendor/bin/phpunit tests/unit/BeReorg005SettingsCompatibilityTest.php tests/database/BeReorg005SettingsMutationAuditCharacterizationTest.php --colors=never`
Expected: new cases FAIL because module mutation/factory/delegate do not exist.

- [ ] **Step 3: Move mutation body mechanically to canonical application service**

Replace concrete storage/audit dependencies with ports. Replace direct legacy `OrganizationRepository` creation in `groupId()` with injected `OrganizationRepositoryInterface`. Preserve exact validation, `sameCanonical()`, event payload, PBAC request, transaction order, rollback and error mapping.

- [ ] **Step 4: Implement stateless `SettingsFactory`**

Use `private __construct()` and no properties. For `mutation()`, resolve one `BaseConnection` (`$db ?? db_connect()`), construct only missing collaborators on that connection, and pass explicitly supplied collaborators unchanged. Use the existing Organization factory/port patterns rather than copying Organization persistence.

- [ ] **Step 5: Replace legacy mutation service with explicit delegate**

Keep exact constructor and named arguments. Alias constants to `DisplaySettingsErrors`. Build the canonical service through `SettingsFactory::mutation(...)` and forward only `setBatch()` / `resetBatch()`.

- [ ] **Step 6: Ensure rollback-sensitive fixture cleanup**

`RuntimeFixture` may seed authorization rows required for Display Settings HTTP/mutation cases, but `tearDown` cleanup must delete only test-owned rollback-sensitive authorization records before `DatabaseTestTrait` refresh, following the accepted Contact FIX01 pattern.

- [ ] **Step 7: Run GREEN**

Run: same PHPUnit command.
Expected: `BeReorg005SettingsCompatibilityTest` has exactly 11 passing cases and mutation/audit characterization has exactly 10.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Modules/Settings/Display/Application/DisplaySettingsMutationService.php backend/app/Bootstrap/SettingsFactory.php backend/app/Services/DisplaySettingsMutationService.php backend/tests/unit/BeReorg005SettingsCompatibilityTest.php backend/tests/database/BeReorg005SettingsMutationAuditCharacterizationTest.php backend/tests/_support/Settings/RuntimeFixture.php
git commit -m "refactor(settings): isolate display mutation"
```

### Task 5: Freeze five in-process HTTP operations and external compatibility

**Files:**
- Create: `backend/tests/database/BeReorg005SettingsHttpTest.php`
- Test-only read/hash: API facade/controller/routes/OpenAPI and Registry/Context/business-timezone files listed under Must remain byte-identical.

**Interfaces:**
- Consumes: legacy public Settings entrypoints after Tasks 2–4 and `RuntimeFixture`.
- Produces: five HTTP regression cases and byte-identical external-boundary evidence.

- [ ] **Step 1: Write five failing in-process HTTP tests**

Exactly one test per operation family: runtime GET authentication + Organization visibility; definitions GET admin metadata; values GET SYSTEM/ORGANIZATION shape and hidden Organization behavior; PATCH set stable validation/error envelope + changed values; POST reset idempotency + persisted/audit behavior. Exercise real routes/controllers/services rather than direct service calls.

- [ ] **Step 2: Run HTTP RED/GREEN against the relocation**

Run: `php vendor/bin/phpunit tests/database/BeReorg005SettingsHttpTest.php --colors=never`
Expected after Tasks 2–4: `OK (5 tests, ...)`. If RED reveals behavior drift, fix production code rather than weakening expectations.

- [ ] **Step 3: Verify byte-identical retained production files**

Hash/diff the eight retained external files against the BE-REORG-004_FIX01 checkpoint.
Expected: 8/8 byte-identical.

- [ ] **Step 4: Commit**

```bash
git add backend/tests/database/BeReorg005SettingsHttpTest.php
git commit -m "test(settings): lock HTTP compatibility"
```

### Task 6: Add guarded BE-REORG-005 runner and coverage gates

**Files:**
- Create: `backend/tools/test-settings.php`
- Modify: `backend/tools/test-foundation.php`
- Create: `docs/changes/BE-REORG-005/README.md`

**Interfaces:**
- Consumes: all 39 new test cases plus existing P1 Settings suites.
- Produces: one-command operator verification with one full PHPUnit run and unchanged PHPStan invocation.

- [ ] **Step 1: Write runner/gate source assertions RED**

Assert exact maps:

```php
BE_REORG_SETTINGS_CASES = [
    'BeReorg005SettingsBoundaryTest' => 5,
    'BeReorg005SettingsCompatibilityTest' => 11,
    'BeReorg005SettingsStorageResolverCharacterizationTest' => 8,
    'BeReorg005SettingsMutationAuditCharacterizationTest' => 10,
    'BeReorg005SettingsHttpTest' => 5,
];
BE_REORG_SETTINGS_EXISTING_CASES = [
    'P1Dsp001DisplaySettingsTest' => 11,
    'P1Dsp002DisplaySettingsResolverTest' => 7,
    'P1Dsp003DisplaySettingsMutationServiceTest' => 5,
    'P1Dsp004DisplaySettingsApiTest' => 5,
    'P1Db003ConfigurationAuditEventsTest' => 1,
];
```

Assert `test-foundation.php` still invokes full PHPUnit exactly once and PHPStan with the unchanged command/config.

- [ ] **Step 2: Implement `test-settings.php`**

Accept only `--confirm-test-db` / help, define Organization → Contact → Employee Structure → Settings maps, then require `test-foundation.php`.

- [ ] **Step 3: Extend `test-foundation.php` minimally**

Priority label becomes BE-REORG-005 when Settings map exists; add Settings prerequisites, full-suite banner, JUnit gates for both the 39 new and 29 existing Settings cases, and message `Settings coverage gate: all 39 new cases executed, none skipped (5 HTTP; no new lock).` Do not add a second PHPUnit process or alter the PHPStan command.

- [ ] **Step 4: Verify runner help and foundation guard**

Run: `php backend/tools/test-settings.php --help`
Expected: usage text only, exit 0.

Run: `cd backend && php tools/test-foundation-guard.php`
Expected: `Foundation guard: 17 checks PASS.`

- [ ] **Step 5: Commit**

```bash
git add backend/tools/test-settings.php backend/tools/test-foundation.php docs/changes/BE-REORG-005/README.md
git commit -m "test(settings): add BE-REORG-005 runtime gate"
```

### Task 7: Full runtime acceptance, PHPStan drift check, and patch integrity

**Files:**
- Verify all changed files; no feature expansion.
- Produce evidence/package artifacts outside production tree as needed.

**Interfaces:**
- Consumes: completed branch from Tasks 1–6.
- Produces: operator-ready incremental patch after BE-REORG-004_FIX01.

- [ ] **Step 1: Run changed-PHP lint and whitespace verification**

Run `php -l` for every changed PHP file and `git diff --check`.
Expected: all lint exit 0; no whitespace errors.

- [ ] **Step 2: Run complete guarded runtime once**

Run from `backend/`:

```bash
php tools/test-settings.php --confirm-test-db
```

Expected on the operator PHP 8.3 environment: full PHPUnit exit 0; all prior gates no-skip; Settings 39-new + 29-existing gates no-skip. Conditional expected suite count: **846 tests** if no unrelated test was added.

- [ ] **Step 3: Compare PHPStan diagnostics to accepted baseline**

Normalize and compare the complete diagnostic set, not only `[ERROR] Found N errors`.
Expected: exactly the inherited 121 diagnostics, zero new/disappeared Settings diagnostics. Global PHPStan still exits 1 and must be reported honestly.

- [ ] **Step 4: Verify exact compatibility/parity**

Re-run Reflection/source signature checks, persistence/application body-parity probes, 8 retained-file hashes, 39-case static counts, and factory statelessness/injected-authority checks.
Expected: every gate PASS.

- [ ] **Step 5: Generate incremental patch and round-trip it**

Generate diff strictly from BE-REORG-004_FIX01 → BE-REORG-005 candidate. On a clean reconstructed checkpoint: `git apply --check`, apply, verify every target file byte-for-byte against candidate, `git apply -R --check`, reverse apply, verify base restored.
Expected: all changed targets exact; no unrelated file in patch.

- [ ] **Step 6: Final whole-branch review**

Review against spec Review Focus. If no independent reviewer/subagent exists, record `Final review: self-review (no subagent tool)` explicitly. Any Critical/Important finding gets one TDD fix pass and green suite before delivery.

- [ ] **Step 7: Deliver operator package**

Package patch + README + spec + plan + evidence. README applies patch from repo root and runs only:

```powershell
Set-Location D:\XAMPP\htdocs\erp
$patch = '.\erp_v1.1.5_BE-REORG-005_settings_2026-10-16.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'Patch khong khop. Dung lai va gui loi.' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'Apply that bai. Dung lai va gui loi.' }
Set-Location .\backend
php tools/test-settings.php --confirm-test-db
```

Do not claim runtime/PHPStan acceptance until the operator log proves it.
