# BE-REORG-005 — Settings / Display Settings Design

## 1. Scope and baseline

BE-REORG-005 starts from the accepted checkpoint through `BE-REORG-004_FIX01`: full PHPUnit is 807 tests / 7,214 assertions, all Foundation, Organization, Contact, and Employee Structure gates execute with no skips, and PHPStan has exactly 121 inherited diagnostics.

This package is a physical architecture relocation of the existing server-side Display Settings implementation only. It must not change business behavior, database schema, routes, OpenAPI, controllers, frontend contracts, authorization semantics, or the global PHPStan configuration/baseline.

In scope for relocation:

- `App\Infrastructure\Settings\DisplaySettingsResolver`
- `App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage`
- `App\Services\DisplaySettingsMutationService`
- `App\Repositories\ConfigurationAuditEventRepository`
- `App\Repositories\ConfigurationAuditEventWriter`

Explicitly retained in place:

- `App\Infrastructure\Settings\DisplaySettingRegistry`
- `App\Infrastructure\Settings\DisplaySettingsContext`
- `App\Services\ApiDisplaySettingsService`
- `App\Controllers\Api\DisplaySettings`
- `App\Infrastructure\Settings\BusinessTimezoneProvider`
- `App\Infrastructure\Settings\BusinessTimezoneResolver`
- Authorization/IAM, Organization, Workflow, Auto Numbering, migrations, routes, OpenAPI, and frontend code.

`BusinessTimezoneProvider` / `BusinessTimezoneResolver` remain shared infrastructure because they resolve `App.businessTimezone` and are used by Auto Numbering. `Display.timezone` remains presentation-only and must not replace business timezone semantics.

## 2. Target architecture

```text
backend/app/
├── Bootstrap/
│   └── SettingsFactory.php
└── Modules/Settings/Display/
    ├── Contracts/
    │   ├── DisplaySettingsErrors.php
    │   └── ConfigurationAuditErrors.php
    ├── Application/
    │   ├── DisplaySettingsResolver.php
    │   ├── DisplaySettingsMutationService.php
    │   └── Ports/
    │       ├── DisplaySettingsStorageInterface.php
    │       └── ConfigurationAuditEventWriterInterface.php
    └── Infrastructure/
        ├── CodeIgniterDisplaySettingsStorage.php
        └── SqlConfigurationAuditEventRepository.php
```

The module owns Display Settings resolution, mutation, persistence adaptation, and configuration-audit persistence. `DisplaySettingRegistry` and `DisplaySettingsContext` remain shared public types because existing API/tests depend on their current namespaces and semantics.

The canonical application layer may continue to use the existing `AuthorizationService` and `App\Services\OrganizationService` as retained cross-boundary collaborators. Group-root reads use the existing `OrganizationRepositoryInterface` from the Organization module; no Organization implementation is copied into Settings.

## 3. Canonical ports and contracts

`DisplaySettingsStorageInterface` exposes exactly the current storage surface:

```php
get(string $key, DisplaySettingsContext|string $context): mixed
set(string $key, mixed $value, DisplaySettingsContext|string $context): void
forget(string $key, DisplaySettingsContext|string $context): void
persistedOverride(string $key, DisplaySettingsContext|string $context): array
```

`persistedOverride()` preserves the current result shape `array{exists: bool, value: mixed}`.

`ConfigurationAuditEventWriterInterface` exposes:

```php
insert(array $event): int
```

`DisplaySettingsErrors` contains the existing mutation error codes:

- `DISPLAY_SETTING_INVALID`
- `DISPLAY_SETTING_ORGANIZATION_NOT_FOUND`
- `DISPLAY_SETTING_AUTHORIZATION_DENIED`

`ConfigurationAuditErrors` contains the existing audit repository codes:

- `CONFIGURATION_AUDIT_PERSISTENCE_FAILED`
- `CONFIGURATION_AUDIT_FIELD_UNSUPPORTED`
- `CONFIGURATION_AUDIT_FIELD_REQUIRED`

No new public error code is introduced.

## 4. Compatibility entrypoints

The current classes/interfaces remain public and keep their exact constructor/method signatures and named arguments:

### `App\Infrastructure\Settings\CodeIgniterDisplaySettingsStorage`

Constructor remains:

```php
__construct(Settings $settings, ?BaseConnection $db = null)
```

Methods remain `get`, `set`, `forget`, and `persistedOverride`. The class becomes an explicit composition wrapper and implements `DisplaySettingsStorageInterface`.

### `App\Infrastructure\Settings\DisplaySettingsResolver`

Constructor remains:

```php
__construct(?CodeIgniterDisplaySettingsStorage $storage = null, ?OrganizationService $organizations = null)
```

Methods remain:

```php
resolve(string $key, int|string|null $organizationId = null): mixed
resolveAll(int|string|null $organizationId = null): array
contextsFor(int|string|null $organizationId = null): array
```

### `App\Services\DisplaySettingsMutationService`

The class remains `final`, keeps the three existing error constants, and keeps the exact constructor:

```php
__construct(
    ?CodeIgniterDisplaySettingsStorage $storage = null,
    ?AuthorizationService $authorization = null,
    ?ConfigurationAuditEventWriter $audit = null,
    ?OrganizationService $organizations = null,
    ?BaseConnection $db = null,
    ?MutationContext $mutationContext = null,
    ?Clock $clock = null,
)
```

Methods remain:

```php
setBatch(string|DisplaySettingsContext $context, array $values, ?OperationContext $operation = null): array
resetBatch(string|DisplaySettingsContext $context, array $keys, ?OperationContext $operation = null): array
```

The wrapper explicitly delegates; no inheritance and no `__call`.

### `App\Repositories\ConfigurationAuditEventRepository`

The class remains `final`, keeps its three error constants, constructor `__construct(?ConnectionInterface $db = null)`, methods `insert(array $event): int`, `findAll(): array`, and static `encodeValue(mixed $value): ?string`.

### `App\Repositories\ConfigurationAuditEventWriter`

The legacy interface remains available and extends/bridges the module writer port without changing `insert(array $event): int`.

## 5. Factory semantics

`App\Bootstrap\SettingsFactory` is stateless and retains no connection, actor, clock, authorization decision, or settings instance between calls.

It wires canonical storage, resolver, audit repository, and mutation service. For mutation wiring it resolves one database connection and uses that connection for the default storage, default audit repository, authorization/Organization integration, and transaction boundary.

An explicitly supplied storage, audit writer, authorization service, or Organization service remains authoritative and is not rebound to another connection. A separately supplied `$db` still owns the transaction and default collaborators, matching current constructor semantics.

No global singleton/cache is introduced.

## 6. Behavioral invariants

The following behavior must remain unchanged:

- Registry still contains exactly the current 15 Display Settings definitions, defaults, storage keys, validation rules, and scopes.
- Only `SYSTEM` and `ORGANIZATION` contexts are valid. Organization IDs retain current canonical positive signed-BIGINT parsing behavior.
- Resolution order for an Organization remains nearest target → parent chain → group → system → registry default. Sibling overrides never participate.
- Reparenting immediately changes the resolution path because ancestry is resolved from current Organization data.
- Invalid persisted legacy values are skipped and never override a valid inherited/default value.
- Unknown registry keys fail closed; storage cannot create arbitrary runtime settings.
- `Display.timezone` remains separate from `App.businessTimezone`.
- `setBatch()` validates the entire input before mutation, authorizes `display_setting/edit`, skips canonical no-op writes, and returns only changed keys in input order.
- `resetBatch()` is idempotent: missing overrides produce no write and no audit event.
- SYSTEM authorization evaluates at the single canonical GROUP root; missing/ambiguous root fails closed.
- Organization mutations require an existing Organization and use the current authoritative PBAC evaluation Organization.
- DENY and ABSOLUTE_DENY precedence is unchanged.
- Mutation remains one database transaction covering all setting writes/resets and all audit inserts. Any audit/persistence failure rolls back the whole batch.
- Configuration audit rows preserve configuration type, context, Organization id, key, operation, canonical JSON old/new values, trusted actor id, request id, and trusted evaluation timestamp.
- Configuration audit persistence remains insert-only; `findAll()` remains deterministic `id ASC`.
- Caller-provided audit fields cannot replace trusted `OperationContext` values.

No domain lock is added. Existing transaction semantics are retained exactly.

## 7. API and external compatibility

`ApiDisplaySettingsService`, controller, routes, and OpenAPI remain outside this package and must be byte-identical to the accepted checkpoint; production API behavior must not change.

The five existing operations remain exactly:

- `GET /api/v1/display-settings/runtime`
- `GET /api/v1/display-settings/definitions`
- `GET /api/v1/display-settings/values`
- `PATCH /api/v1/display-settings`
- `POST /api/v1/display-settings/actions/reset`

Runtime remains authenticated presentation data. Definitions/values remain administrative views. Existing error-envelope/status mapping is unchanged.

## 8. Verification design

Before implementation, freeze legacy constructors, public methods, constants, registry/context namespaces, and the five API operations with Reflection/source checks.

Add BE-REORG-005 boundary/compatibility tests proving:

- module application depends on the declared ports and retained cross-boundary services only;
- legacy classes are final explicit delegates and contain no inheritance/magic dispatch;
- legacy writer interface remains compatible with the module audit-writer port;
- `SettingsFactory` is stateless and defaults share one connection;
- injected collaborators remain authoritative.

Add fixed-value characterization for storage, resolution, mutation, and audit persistence covering all invariants in Section 6, including invalid stored values, reparenting, no-op set/reset, audit JSON encoding, authorization denial, missing target/root, and rollback on audit failure.

Add in-process HTTP regression for all five Display Settings operations, including hidden Organization behavior, count/value shape, stable validation errors, and idempotent reset. Keep the existing `P1Dsp001`–`P1Dsp004` and `P1Db003` suites in the runtime gate.

Tests using `DatabaseTestTrait` must not leave authorization state that breaks subsequent migration rollback; use the established test-only cleanup pattern when the fixture creates rollback-sensitive authorization rows.

No new concurrency/lock test is required because this package introduces no lock and preserves the existing transaction boundary.

## 9. Runtime acceptance

Provide one guarded runner `backend/tools/test-settings.php --confirm-test-db`, extending the existing Foundation runner pattern without a second full PHPUnit process.

Acceptance requires:

- full PHPUnit exit code 0;
- all existing Foundation, Organization, Contact, and Employee Structure gates still execute with no skips;
- all new BE-REORG-005 cases execute with no skips;
- PHPStan command/config remains unchanged;
- normalized PHPStan diagnostics match the accepted 121-diagnostic baseline exactly, with zero new Settings diagnostics.

The inherited 121 PHPStan diagnostics remain an honest global FAIL until separately remediated; BE-REORG-005 acceptance is zero diagnostic drift, not a claim that global PHPStan is green.

## 10. Operational safety and delivery

Tests run only against the confirmed disposable `127.0.0.1:3306/erp_test` target with the expected `dinktech_` prefix. Never run another test process concurrently on that database.

Do not run Composer update, migrations, seed/reset commands outside the guarded test lifecycle, client generation, schema changes, or browser/Apache UAT as part of this package.

Delivery is one incremental patch after `BE-REORG-004_FIX01`, plus spec/plan/evidence artifacts. The patch must pass apply-check, reverse-check, `git diff --check`, PHP lint, and byte/hash verification against the accepted checkpoint. Rollback is patch-only.

## 11. Non-goals

BE-REORG-005 does not:

- move or redesign Auto Numbering or business timezone resolution;
- move `ApiDisplaySettingsService`, controller, routes, OpenAPI, or frontend code;
- change `DisplaySettingRegistry` or `DisplaySettingsContext` public namespaces/behavior;
- change Authorization/IAM or Organization behavior;
- add locks, schema, migrations, settings, user-level overrides, or new API operations;
- repair unrelated PHPStan debt;
- begin the later IAM / Workflow / Numbering reorganization packages.
