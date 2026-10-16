# BE-REORG-006 — Numbering Design

**Date:** 2026-10-16  
**Status:** Proposed for implementation after written-spec approval  
**Input checkpoint:** BE-REORG-005 + BE-REORG-005_FIX01  
**Baseline runtime:** 846 PHPUnit tests / 7,446 assertions; Foundation, Organization, Contact, Employee Structure, and Settings coverage gates all pass; PHPStan has exactly 121 inherited diagnostics and exits 1.

## 1. Purpose

BE-REORG-006 physically reorganizes the existing Auto Numbering implementation into a bounded `App\Modules\Numbering` module while preserving all current runtime behavior and all existing public integration surfaces.

This is an architectural relocation, not a feature change. The goal is to make Numbering's configuration, pattern validation, scope/period resolution, business-timezone lookup, counter allocation, and issuance orchestration independently understandable and testable without forcing API-040, Product, Authorization, routes, OpenAPI, or database schema to move in the same patch.

The change must preserve the accepted BE-REORG-005_FIX01 checkpoint exactly except where the Numbering relocation requires explicit compatibility wrappers/factory wiring.

## 2. Scope

### 2.1 In scope

The canonical implementation moves into:

```text
backend/app/Modules/Numbering/
├── Configuration/
│   ├── Application/
│   │   ├── AutoNumberingConfigurationService.php
│   │   └── Ports/
│   │       └── NumberingSeriesRepositoryInterface.php
│   └── Infrastructure/Persistence/
│       └── SqlNumberingSeriesRepository.php
├── Allocation/
│   ├── Application/
│   │   ├── AutoNumberingCounterAllocator.php
│   │   └── Ports/
│   │       └── NumberingCounterRepositoryInterface.php
│   └── Infrastructure/Persistence/
│       └── SqlNumberingCounterRepository.php
├── Scope/
│   ├── Application/
│   │   ├── AutoNumberingScopePeriodResolver.php
│   │   └── Ports/
│   │       └── BusinessTimezoneProviderInterface.php
│   └── Infrastructure/Settings/
│       └── CodeIgniterBusinessTimezoneResolver.php
├── Pattern/
│   └── Application/
│       ├── CanonicalNumberingPatternValidator.php
│       └── Ports/
│           ├── NumberingPatternValidatorInterface.php
│           └── NumberingPatternCompatibilityValidatorInterface.php
├── Issuance/
│   └── Application/
│       └── AutoNumberService.php
└── Authorization/
    └── Application/Ports/
        └── NumberingAuthorizationPortInterface.php

backend/app/Bootstrap/NumberingFactory.php
```

The exact file split may use adjacent `Contracts` files only if required to preserve constants without duplication. It must not create additional layers or abstractions not required by this spec.

### 2.2 Legacy compatibility entrypoints retained

These existing class/interface names remain available to all current callers:

```text
App\Services\AutoNumberService
App\Services\AutoNumberingConfigurationService
App\Services\AutoNumberingCounterAllocator
App\Services\AutoNumberingScopePeriodResolver
App\Services\CanonicalNumberingPatternValidator
App\Services\NumberingAuthorizationPort
App\Services\NumberingPatternValidator
App\Services\NumberingPatternCompatibilityValidator

App\Repositories\AutoNumberingSeriesRepository
App\Repositories\AutoNumberingCounterRepository

App\Infrastructure\Settings\BusinessTimezoneProvider
App\Infrastructure\Settings\BusinessTimezoneResolver
```

Concrete legacy classes become explicit compatibility wrappers around canonical module classes. Repository wrappers delegate explicitly to canonical SQL repositories. Legacy interfaces extend the corresponding canonical module ports so existing implementers, especially `AuthorizationService`, remain valid inputs to canonical services without changing their source.

No `__call`, reflection forwarding, service locator magic, inheritance from concrete implementations, or runtime class aliases are allowed.

## 3. Explicitly out of scope

The following remain in their current locations and must stay byte-identical unless an implementation impossibility is demonstrated before code is changed:

```text
backend/app/Services/Api040AutoNumberingService.php
backend/app/Controllers/Api/AutoNumbering.php
backend/app/Config/Routes.php
API/OpenAPI contracts for API-040

backend/app/Authorization/AuthorizationService.php
backend/app/Authorization/CompanyOwnerResolver.php

backend/app/Modules/Product/Product/ProductMasterService.php
backend/app/Modules/Product/Integration/Numbering/ProductIdentifierAdapter.php
backend/app/Modules/Product/Integration/Numbering/ProductIdentifierUniqueViolation.php

backend/app/Services/ResourceIdentifierAdapter.php
backend/app/Services/ResourceIdentifierAdapterRegistry.php
backend/app/Services/ResourceIdentifierBusinessMutation.php
backend/app/Services/ResourceIdentifierMetadata.php

backend/app/Services/NumberingScopePeriodContext.php
backend/app/Services/ParsedNumberingPattern.php
backend/app/Services/AutoNumberingAllocationResult.php
backend/app/Services/AutoNumberingBusinessMutation.php
backend/app/Services/AutoNumberingIdentifierCollision.php

backend/app/Database/Migrations/*
frontend/*
```

The value objects and integration contracts above intentionally remain shared compatibility types for this phase. Moving them would force type changes through Product/API callers and is deferred until legacy-wrapper retirement.

BE-REORG-006 must not add or alter migrations, tables, columns, indexes, routes, request/response payloads, authorization actions, feature flags, cache policy, or frontend behavior.

## 4. Canonical ports

### 4.1 `NumberingSeriesRepositoryInterface`

The canonical configuration/issuance layer consumes a repository interface covering the existing persistence surface needed by current behavior:

```php
public function find(mixed $id): ?array;
public function findForUpdate(mixed $id): ?array;
public function findByResourceAndSeriesCode(mixed $resourceId, string $seriesCode): ?array;
public function findByResource(mixed $resourceId): array;
public function findActiveByResource(mixed $resourceId): array;
public function findActiveDefaultsByResource(mixed $resourceId): array;
public function findActiveDefaultByResource(mixed $resourceId): ?array;
public function findResource(mixed $resourceId): ?array;
public function findResourceByType(string $resourceType): ?array;
public function lockResource(mixed $resourceId): ?array;
public function counterExists(mixed $seriesId): bool;
public function issued(mixed $seriesId): bool;
public function insert(array $data, ?OperationContext $operation = null): int;
public function create(array $data, ?OperationContext $operation = null): int;
public function update(mixed $id, array $data, ?OperationContext $operation = null): bool;
public function updateSeries(mixed $id, array $data, ?OperationContext $operation = null): bool;
public function delete(mixed $id): bool;
public function transactional(callable $callback, string $operation = 'transaction'): mixed;
```

Return PHPDoc/value types must match the current repository semantics closely enough that BE006 introduces no PHPStan diagnostic.

### 4.2 `NumberingCounterRepositoryInterface`

```php
public function find(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): ?array;
public function findForUpdate(mixed $seriesId, mixed $scopeOrganizationId, string $periodKey): ?array;
public function insert(
    mixed $seriesId,
    mixed $scopeOrganizationId,
    string $periodKey,
    mixed $lastValue,
    ?OperationContext $operation = null,
): int;
public function updateLastValue(mixed $counterId, mixed $lastValue, ?OperationContext $operation = null): void;
```

### 4.3 `BusinessTimezoneProviderInterface`

```php
public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone;
```

The existing `App\Infrastructure\Settings\BusinessTimezoneProvider` extends this port. Existing external callers therefore remain source-compatible.

### 4.4 Pattern ports

```php
interface NumberingPatternValidatorInterface
{
    public function validate(string $pattern, array $series): void;
}

interface NumberingPatternCompatibilityValidatorInterface extends NumberingPatternValidatorInterface
{
    public function validateSeriesSet(array $series): void;
}
```

Existing legacy pattern interfaces extend these canonical interfaces. The canonical pattern validator implements the canonical compatibility interface; the legacy concrete class explicitly delegates `parse()`, `validate()`, and `validateSeriesSet()`.

### 4.5 `NumberingAuthorizationPortInterface`

The canonical interface preserves the current exact method contract:

```php
public function authorize(
    OperationContext $operation,
    string $resourceType,
    string $action,
    EvaluationOrganizationRequest $contextRequest,
    mixed $stateId = null,
): AuthorizationDecision;
```

The existing `App\Services\NumberingAuthorizationPort` extends this interface. `AuthorizationService` remains unchanged.

## 5. Legacy constructor compatibility

The following constructor parameter names, types, order, and defaults are public compatibility contracts and must remain callable exactly as today.

### 5.1 Configuration service

```php
public function __construct(
    ?AutoNumberingSeriesRepository $repository = null,
    ?NumberingAuthorizationPort $authorization = null,
    ?NumberingPatternValidator $patternValidator = null,
    ?ConnectionInterface $db = null,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
    ?ResourceIdentifierAdapterRegistry $identifierAdapters = null,
)
```

### 5.2 Counter allocator

```php
public function __construct(
    ?ConnectionInterface $db = null,
    ?AutoNumberingCounterRepository $counters = null,
    ?DomainLockService $domainLock = null,
)
```

### 5.3 Scope/period resolver

```php
public function __construct(
    ?OrganizationRepository $organizations = null,
    ?CompanyOwnerResolver $companyOwners = null,
    ?BusinessTimezoneProvider $timezones = null,
    ?ConnectionInterface $db = null,
)
```

### 5.4 Issuance service

```php
public function __construct(
    ?ResourceIdentifierAdapterRegistry $adapters = null,
    ?AutoNumberingSeriesRepository $series = null,
    ?AutoNumberingCounterAllocator $allocator = null,
    ?CanonicalNumberingPatternValidator $patterns = null,
    ?AutoNumberingScopePeriodResolver $scopeResolver = null,
    ?ConnectionInterface $db = null,
)
```

### 5.5 Repositories

Both legacy repositories retain:

```php
public function __construct(?ConnectionInterface $db = null)
```

### 5.6 Business timezone resolver

The legacy resolver retains:

```php
public function __construct(Settings $settings)
```

`Config\Services::businessTimezone()` must remain source-compatible and return the legacy `BusinessTimezoneResolver` type; its current shared/non-shared behavior is preserved.

## 6. Factory and dependency ownership

`App\Bootstrap\NumberingFactory` is a stateless composition root used only by compatibility wrappers/default construction.

Rules:

1. A caller-supplied dependency is used as supplied and is never silently replaced or rebound.
2. When a DB connection is supplied, all default SQL repositories, organization dependencies, and domain-lock implementation created for the same operation use that connection.
3. When no DB is supplied, default behavior remains `db_connect()` at the same effective construction boundary as today.
4. The factory must not introduce shared mutable singleton state.
5. The factory must not call API services/controllers.
6. The factory must not change `Config\Services::businessTimezone()` semantics.
7. Lazy/default collaborator behavior that is externally observable through existing tests must be preserved. BE005 demonstrated that eager creation can alter shared-service/cache behavior; BE006 must not repeat that class of drift.

## 7. Behavioral invariants

### 7.1 Series configuration

All current validation, normalization, authorization, error codes, and precedence remain unchanged.

Preserve exactly:

- `scope_type`: `GROUP | COMPANY | ORGANIZATION`.
- `reset_policy`: `NEVER | YEARLY | MONTHLY | DAILY`.
- `generation_mode`: `AUTO_REQUIRED | AUTO_IF_EMPTY`.
- `status`: `ACTIVE | INACTIVE`.
- create/update allowlists and audit-field stripping.
- resource existence checks.
- `series_code` normalization and uniqueness per resource.
- active-default uniqueness semantics.
- active pattern validation and cross-series compatibility checks.
- identifier metadata integration through the existing adapter registry.
- issued-series immutability for `resource_id`, `series_code`, `scope_type`, and `reset_policy`.
- hard-delete rejection after issuance.
- all existing public aliases (`createSeries`, `updateSeries`, etc.).
- authorization resource/action semantics, including action `configure_numbering`.
- current error codes and error ordering.

### 7.2 Configuration locking and transaction behavior

Configuration mutations retain the existing database serialization strategy. No new domain/advisory lock is introduced for series configuration.

- create locks the Resource row using the current `lockResource()`/`FOR UPDATE` behavior before duplicate/default checks and insert.
- update locks all affected Resource rows in ascending numeric order, then locks the series row using current `findForUpdate()` behavior.
- delete locks the Resource row and series row before issued-counter checks.
- the repository's transaction semantics, rollback behavior, DB exception mapping, duplicate detection, and audit columns remain unchanged.
- concurrent attempts to create competing active defaults must continue to serialize and preserve one-active-default semantics.

### 7.3 Pattern grammar

`CanonicalNumberingPatternValidator` remains behavior-identical.

Supported tokens remain:

```text
{SEQ:n}
{YYYY}
{YY}
{MM}
{DD}
{COMPANY_CODE}
{ORG_CODE}
{RESOURCE}
```

Preserve exactly:

- one required sequence token.
- positive sequence width.
- brace/token validation.
- reset-policy date discriminator requirements.
- scope/discriminator compatibility.
- identifier uniqueness namespace compatibility.
- minimum rendered length checks against identifier metadata.
- deterministic collision signature and active/selectable series-set collision detection.
- all `NUM_PATTERN_*` errors and error precedence.

BE006 must not invent new tokens or normalize pattern text differently.

### 7.4 Scope and period resolution

The canonical resolver preserves the current authoritative organization behavior and may depend on BE-REORG-002 canonical Organization components internally, but this must not change observable errors/results.

Preserve:

- `GROUP` scope resolves exactly one active root GROUP with a nonblank code.
- `COMPANY` scope resolves the company owner of the evaluation organization.
- `ORGANIZATION` scope uses the authoritative evaluation organization and resolves a company owner when required by timezone/date or `{COMPANY_CODE}`.
- non-GROUP scope requires an evaluation organization context.
- authoritative organization id/type/status checks.
- missing, inactive, mismatched, broken, or unresolved organization behavior and current `NUM_SCOPE_*`/company errors.
- pattern metadata requirement when a series carries `pattern`.
- resource type normalization behavior.
- `NEVER` period key is exactly `ALL`.
- `YEARLY` key is `YYYY`.
- `MONTHLY` key is `YYYYMM`.
- `DAILY` key is `YYYYMMDD`.
- `OperationContext::evaluationTime` remains the source of business date; system wall-clock reads are not introduced into this resolver.

### 7.5 Business timezone

Business timezone moves with Numbering because its only business role in this boundary is numbering period resolution. Display Settings remain separate.

Resolution order remains exactly:

1. `App.businessTimezone` at `company:<companyId>` when company id is available.
2. `App.businessTimezone` at `group:<groupId>` when group id is available.
3. system `App.businessTimezone`.
4. if group id exists and no configured value exists, fallback to `Asia/Ho_Chi_Minh`.
5. otherwise return `null`.

Preserve:

- invalid timezone names throw `InvalidArgumentException` at the resolver boundary and are mapped by scope resolution to `NUM_BUSINESS_TIMEZONE_INVALID`.
- Settings `DatabaseException` while reading is treated as no configured value, as today.
- Display setting `Display.timezone` must not replace `App.businessTimezone`.
- `Config\Services::businessTimezone()` shared behavior remains unchanged.

### 7.6 Counter allocation

Counter allocation is concurrency-sensitive and must be relocated mechanically.

Preserve exactly:

- `BaseConnection` requirement.
- allocator rejection when called inside an already-open transaction.
- allocator opens one transaction before counter lookup and business mutation.
- existing counters are selected with `FOR UPDATE`.
- first-counter creation uses the advisory/domain-lock key:

```text
auto-number:<seriesId>:<scopeOrganizationId>:<periodKey>
```

- after obtaining that lock, the counter is re-read with `FOR UPDATE` before first insertion.
- default lock implementation remains `MariaDbDomainLockService` on the allocator DB connection.
- lock failures remain `NUM_COUNTER_LOCK_FAILED`.
- business mutation and counter insert/update remain in the same transaction.
- rollback must not consume a sequence.
- committed sequence values are not decremented or reused.
- `start_number` may be zero; `increment_by` must remain positive.
- integer/overflow/error validation remains unchanged.
- candidate collision retry limit remains exactly `1000`.
- occupied-candidate checks and final unique-guard collision retries retain their current order.
- candidate exhaustion remains `NUM_COUNTER_CANDIDATE_EXHAUSTED` with attempts `1000`.
- transaction begin/commit/rollback/reset semantics remain unchanged.

No new lock key, cache, batch allocator, sequence prefetch, or performance optimization is allowed in BE006.

### 7.7 Identifier issuance

`AutoNumberService` remains behavior-identical.

Preserve:

- resource type canonicalization and resource lookup.
- explicit series-code selection behavior, including blank/missing/inactive errors.
- default-series selection and ambiguity handling.
- no selected series: nonblank manual identifier is persisted and result reports `generated = false`; otherwise current empty/manual result semantics remain.
- adapter resource identity checks.
- identifier max length and uniqueness scope metadata injection before pattern parsing.
- `AUTO_REQUIRED`: supplied manual identifier is rejected.
- `AUTO_IF_EMPTY`: supplied manual identifier is length-checked, adapter-validated, persisted directly, and does not consume a counter.
- generated allocation delegates to the canonical counter allocator with the existing `ResourceIdentifierBusinessMutation` bridge.
- public `issue()` and `issueIdentifier()` behavior remains identical.

## 8. API and external integration invariants

API-040 remains an external caller of the compatibility surface. No route/controller/facade relocation occurs in this phase.

The six existing API-040 operations remain unchanged:

```text
GET    /resources/{resourceId}/numbering-series
POST   /resources/{resourceId}/numbering-series
GET    /resources/{resourceId}/numbering-series/{seriesId}
PATCH  /resources/{resourceId}/numbering-series/{seriesId}
POST   /resources/{resourceId}/numbering-series/{seriesId}/actions/set-state
POST   /resources/{resourceId}/numbering-series/actions/validate-config
```

Product and Product Import continue using existing legacy `AutoNumberService` / `AutoNumberingSeriesRepository` entrypoints. Their source is not rewritten merely to point at canonical module namespaces.

`AuthorizationService` remains unchanged and continues implementing the legacy `NumberingAuthorizationPort`, which bridges to the canonical port through interface extension.

## 9. Existing tests are the behavioral authority

The current checkpoint contains 68 directly Numbering-related tests:

```text
P1Num001AutoNumberingTest                         9
P1Num002NumberingPatternValidatorTest             9
P1Num003ScopePeriodResolverTest                  12
P1Num004CounterAllocatorTest                     10
P1Num005ResourceIdentifierAdapterTest             6
P1Num006ProductIdentifierAdapterTest              7
P1Api040AutoNumberingTest                         6
P1AutoNumberingMandatoryRegressionTest            5
P1Db050ResourceAuthorizationNumberingTest         4
---------------------------------------------------
Existing Numbering characterization total        68
```

These tests include real DB transaction/rollback and process-level concurrency coverage. They are reused; BE006 must not duplicate their business assertions under new filenames.

New BE006 tests should be limited to reorganization-specific guarantees that the existing tests do not prove, such as:

- canonical module/port boundaries.
- exact legacy constructor and method compatibility.
- explicit legacy-to-canonical delegation.
- factory dependency ownership/same-DB wiring.
- canonical-vs-legacy behavior parity at selected seams.
- real in-process HTTP route/controller integration if the existing API040 tests do not exercise the real route stack.
- relocation lock key/DB-lock contract only where existing tests cannot prove canonical code is the code actually executing.
- byte-integrity of explicitly out-of-scope callers.

The implementation plan must prefer data providers/shared contract tests over one-test-per-wrapper expansion.

## 10. Verification runner

BE006 adds a guarded runner consistent with prior reorganization packages, tentatively `backend/tools/test-numbering.php`.

It must:

1. require explicit test-DB confirmation.
2. verify the target is `127.0.0.1:3306/erp_test` with prefix `dinktech_`.
3. warn against concurrent test processes on the same DB.
4. execute exactly one full PHPUnit process.
5. prove all existing reorganization coverage gates execute and are not skipped.
6. prove all 68 existing Numbering tests execute and are not skipped.
7. prove all new BE006 relocation cases execute and are not skipped.
8. run PHPStan only after PHPUnit passes.
9. report PHPStan exit 1 honestly when inherited diagnostics remain.
10. compare diagnostics to the accepted 121-diagnostic baseline by diagnostic identity/content, not count alone.

No test process may run concurrently with another process that uses `erp_test`, except subprocesses intentionally created by the existing Numbering concurrency tests themselves.

## 11. PHPStan acceptance

Accepted baseline before BE006:

```text
121 inherited PHPStan diagnostics
PHPStan exit code: 1
```

BE006 acceptance requires:

```text
new BE006 diagnostics: 0
removed/changed inherited diagnostics: 0 unless separately justified and approved
```

The exact diagnostic set must match the accepted BE-REORG-005_FIX01 baseline. Merely reporting `121` is insufficient.

No `phpstan.neon` weakening, ignore additions, baseline generation, `@phpstan-ignore`, or type-erasing workaround is allowed to make the count appear stable.

## 12. Patch and delivery acceptance

The final BE006 candidate must satisfy all of the following before operator runtime testing:

- patch is incremental from the exact BE-REORG-005_FIX01 checkpoint.
- `git apply --check` succeeds on that checkpoint.
- reverse apply restores that checkpoint byte-for-byte for every touched file.
- PHP lint passes for every changed/new PHP file.
- `git diff --check` passes.
- out-of-scope byte-integrity checks pass.
- legacy constructor/method signature probes pass.
- no migration/schema/routes/OpenAPI/frontend changes.
- no unrelated refactor.
- final package records changed files, hashes, local verification evidence, and any rulings.

Operator acceptance then requires full runtime evidence from `php tools/test-numbering.php --confirm-test-db`.

The correct acceptance wording is not "all checks green" while PHPStan exits 1. It must say that PHPUnit and coverage gates pass while PHPStan still exits 1 solely because the exact 121 inherited diagnostics remain unchanged, with zero BE006 diagnostics.

## 13. Migration sequence

Implementation should proceed in this order:

1. Add canonical ports and relocation-specific boundary tests.
2. Move series persistence behind `NumberingSeriesRepositoryInterface`; convert legacy repository to explicit wrapper.
3. Move counter persistence behind `NumberingCounterRepositoryInterface`; convert legacy repository to explicit wrapper.
4. Move pattern validator and bridge legacy pattern interfaces/classes.
5. Move business-timezone provider/resolver and bridge legacy Settings entrypoints without changing `Config\Services` behavior.
6. Move scope/period resolver, using canonical Organization dependencies internally while preserving legacy constructor injection.
7. Move counter allocator with exact transaction/locking behavior.
8. Move configuration service with exact resource-row locking/authorization/pattern behavior.
9. Move issuance service with Product adapter/value types left in place.
10. Add `NumberingFactory` default composition and compatibility wrappers.
11. Add minimal relocation/HTTP/factory tests and guarded runner.
12. Run full verification, exact PHPStan comparison, self/fresh review as available, and patch round-trip verification.

Each behavioral move must be characterization-first/TDD. Production behavior may not be "cleaned up" while moving it.

## 14. Completion criteria

BE-REORG-006 is complete only when all of these are true:

1. Canonical Numbering implementation resides under `App\Modules\Numbering` as defined above.
2. Existing public Numbering entrypoints remain callable with compatible signatures and constants.
3. API040, Product, Authorization, routes, OpenAPI, migrations, and frontend remain outside the relocation scope.
4. Business timezone is owned canonically by Numbering while legacy Settings entrypoints remain compatible.
5. Configuration Resource-row locking is unchanged.
6. Counter `FOR UPDATE` and first-counter advisory lock behavior is unchanged.
7. Sequence rollback/retry/collision semantics are unchanged.
8. The 68 existing Numbering tests all execute and pass.
9. All existing Foundation/Organization/Contact/Employee Structure/Settings gates remain green.
10. New BE006 relocation tests execute and pass without duplicating existing business characterization unnecessarily.
11. PHPStan diagnostic set is exactly the accepted 121 inherited diagnostics, with zero BE006 diagnostic.
12. Incremental patch apply/reverse round-trip is exact.
