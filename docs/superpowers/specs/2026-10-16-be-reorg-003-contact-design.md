# BE-REORG-003: Contact module

## Scope and baseline
Continue the approved modular-monolith roadmap on the checkpoint containing BE-REORG-001, 001A, FIX01, FIX02, 001B, BE-REORG-002 and BE-REORG-002_FIX01. The operator baseline reports 711 PHPUnit tests / 6503 assertions passing, all Foundation and Organization coverage gates executed without skips, and 121 inherited PHPStan diagnostics. This document does not claim a fresh local runtime pass.

Move only the implementations currently owned by `App\Services\ContactService` and `App\Repositories\ContactRepository`. Preserve those two classes as legacy public entrypoints because API-010, lifecycle integration and existing tests still type against them. No business-rule fixes, schema, migration, dependency, OpenAPI, route, controller, frontend, authorization/IAM, generic workflow, lifecycle-adapter or PHPStan-configuration changes belong in this package.

`App\Services\ContactLifecycleAdapter`, `App\Services\Api010FoundationOrganizationContactService`, `App\Authorization\ContactAuthorizationRegistry`, `App\Authorization\EmployeeContactScopeResolver`, evaluation-organization resolvers and all workflow infrastructure remain in place and consume the compatibility entrypoints exactly as before.

## Architecture
Canonical implementation will be introduced as:

- `App\Modules\Contact\Application\ContactService`: existing Contact use cases, owner validation, location validation, audit handling and direct-state workflow guards.
- `App\Modules\Contact\Application\Ports\ContactRepositoryInterface`: the seven existing repository operations used by the application and integration callers: `find`, `insert`, `update`, `findByOwner`, `search`, `ownerExists`, and `wardBelongsToProvince`. The interface retains explicit array/list PHPDoc and scalar filter shapes.
- `App\Modules\Contact\Infrastructure\Persistence\SqlContactRepository`: existing SQL, field allowlist, owner immutability, payload validation, exception mapping and deterministic `id ASC` ordering.
- `App\Modules\Contact\Contracts\ContactErrors`: the fourteen existing service/repository error codes, exposed unchanged through legacy wrappers and canonical classes.
- `App\Bootstrap\ContactFactory`: constructs fresh canonical repositories/services; no cached instance, actor, clock, mutation context or connection state.
- `App\Services\ContactService` and `App\Repositories\ContactRepository`: explicit final composition wrappers. They retain the existing public constructors, named parameter names/defaults, public methods, return types and constants. They do not use inheritance, `__call`, service-locator caching or hidden shared state.

The canonical application service depends on `ContactRepositoryInterface`, the supplied CodeIgniter database connection, `MutationContext`, `Clock`, `PositiveId` and the existing `BusinessWorkflowWriteBoundary`. Those are explicit cross-cutting dependencies. It must not depend on `App\Repositories\ContactRepository` or on the API facade/lifecycle adapter.

The compatibility repository implements `ContactRepositoryInterface`, so unchanged callers that inject `App\Repositories\ContactRepository` remain valid inputs to the canonical service through the factory. If a caller explicitly supplies a repository and also supplies a different database connection, the repository instance remains authoritative and is not silently reconstructed or rebound; the supplied connection continues to be the one used by workflow-boundary logic, matching current constructor semantics.

## Public compatibility contract
`App\Services\ContactService` remains `final` and preserves its constructor shape exactly:

`__construct(?ContactRepository $repository = null, ?ConnectionInterface $db = null, ?MutationContext $mutationContext = null, ?Clock $clock = null)`.

It preserves the eight current error constants and these public methods without signature or named-argument changes: `create(array $data): int`, `update(int $id, array $data): bool`, `inactivate(int $id, string $status = 'INACTIVE'): bool`, `listForOwner(string $contactType, int $contactRefId, ?string $status = null): array`, and `search(array $filters = []): array`.

`App\Repositories\ContactRepository` remains `final`, preserves `__construct(?ConnectionInterface $db = null)`, the six repository error constants, and the seven current public methods: `find`, `insert`, `update`, `findByOwner`, `search`, `ownerExists`, and `wardBelongsToProvince`.

Compatibility wrappers contain delegation/wiring only. New Contact behavior belongs in the module implementation, not in legacy wrappers.

## Behavioral invariants
Preserve both owner kinds exactly: `EMPLOYEE` and `ORGANIZATION`. Type normalization remains uppercase; unsupported owner kinds fail closed. Missing/invalid owner references, nonexistent owners and unsupported owner transfer retain current error codes and error precedence. There is no new polymorphic owner type in this package.

Preserve owner immutability on update. A caller cannot change either `contact_type` or `contact_ref_id` after creation. Repository-level owner immutability and service-level owner-transfer rejection both remain, with the same exception identity and context.

Preserve province/ward semantics: validation runs only when both fields are present and non-null; positive-ID normalization and ward-to-province matching retain current behavior. No new requirement is introduced for nullable location values.

Preserve server-owned audit semantics. Incoming `created_by`, `updated_by`, `created_at` and `updated_at` are stripped by the application service; audit values come from the current `MutationContext`/`OperationContext`. Fresh factory calls must not reuse a prior actor, request ID or evaluation time.

Preserve direct lifecycle-write protection. Creation continues through `BusinessWorkflowWriteBoundary::create()` using logical resource `organization_contact` or `employee_contact` according to the persisted owner kind. Update continues through `assertNoDirectStateMutation()` before location validation. `inactivate()` continues to accept only `INACTIVE` and `CLOSED`; lifecycle-bound resources still require their existing state-transition route when the boundary says so. This refactor must not reorder existing workflow/error precedence.

Preserve repository persistence behavior: table `contacts`, current field allowlist, required fields on insert, unsupported-field rejection, empty-update rejection, persistence exception mapping, `insertID()` handling and current success/failure semantics. `findByOwner()` and `search()` keep deterministic `id ASC` ordering. `search()` keeps current filters (`status`, `contact_type`, `query`) and query matching across `name`, `email`, `phone` and `mobile` only.

Preserve API/IAM behavior outside this module. Visibility, PBAC, `ABSOLUTE_DENY`, employee primary-scope resolution, organization evaluation scope, authorized count-before-pagination, wire envelopes, HTTP status mapping and Contact resource selection remain owned by their current components and must be byte-identical unless a test-only/runtime-runner file is intentionally added.

## Lifecycle and concurrency boundary
This package does not move or redesign `ContactLifecycleAdapter` and does not add a new Contact domain lock. Contact application CRUD has no existing named-lock contract to preserve, so inventing one would be a behavior change.

Verification must prove that the unchanged lifecycle adapter continues to work through the legacy `ContactRepository` wrapper for both `organization_contact` and `employee_contact`, including persisted-resource identity and state transitions. Existing workflow transaction/row-lock behavior remains authoritative. Any added concurrency characterization must exercise the real existing lifecycle path and database locking; it must not require production hooks, sleeps as proof, or a new lock key.

The two inherited PHPStan diagnostics currently reported in `ContactLifecycleAdapter` are outside this package unless moving Contact reveals a new direct regression. They must not be hidden by ignore rules, level changes or baseline regeneration.

## Verification design
1. Capture Reflection contracts for both legacy entrypoints from the accepted checkpoint before implementation. Store fixed expected contract data so compatibility is not validated only by comparing two changed classes.
2. Add boundary tests proving the module service depends on the repository port rather than legacy persistence, the SQL repository implements the port, wrappers are explicit delegates, the factory has no retained state, and no prohibited dependency on API/IAM/lifecycle classes enters the module.
3. Add fixed-value characterization for both owner types: create/update/read/list/search, owner normalization and missing-owner failures, owner-transfer rejection, ward/province validation, server-owned audit, repository payload errors, deterministic ordering and workflow-bound direct-state rejection. Existing tests remain unchanged.
4. Add in-process HTTP regression focused on existing Contact API behavior: authorized Organization Contact and Employee Contact reads/writes/lists, visibility before count/pagination, denied/hidden access, invalid direct status mutation versus dedicated lifecycle transition, and unchanged JSON/error envelopes. Reuse the proven request helper behavior from the Foundation/Organization verification path rather than inventing a second HTTP harness.
5. Exercise lifecycle compatibility through the unchanged adapter using the real compatibility repository. Where real locking is characterized, use independently verified MySQL connections and existing workflow transactions; do not claim general race freedom.
6. Reuse the guarded single-command runtime runner pattern: full PHPUnit exactly once, fresh JUnit evidence for all new Contact cases and existing Foundation/Organization gates, then PHPStan. A skipped/filtered/old JUnit file is failure. PHPStan remains FAIL while inherited diagnostics remain, but exact diagnostics are compared to the accepted 121-diagnostic baseline.
7. Compare canonical method bodies/SQL against the checkpoint after normalizing namespace, error-constant and wiring-only changes. Verify patch apply/reverse against the exact BE-REORG-002_FIX01 checkpoint and hash all files so unrelated changes are detected.

## Operational safety
Runtime database testing is permitted only when `ENVIRONMENT=testing` and the effective guarded connection is the previously confirmed disposable loopback MySQLi `erp_test` target with the expected prefix. `DatabaseTestTrait` may refresh tables. Never run another test process against that database concurrently and never substitute the development/business database.

No Composer update, migration, seed, schema reset or generated-client regeneration is part of this refactor. Existing locked dependencies are reused. A missing vendor/extension/database in the assistant environment is reported as an environment blocker, not as a passing or failing application suite.

Browser/Apache/reverse-proxy UAT remains separate. In-process HTTP tests do not prove Vue rendering, XAMPP routing, CSRF behavior through a real web server or production concurrency schedules.

## Delivery contract
Deliver one incremental patch after BE-REORG-002_FIX01 and one small evidence package. The operator runs one guarded command for BE-REORG-003 and returns one project-root `test-result.log`. Do not reapply earlier patches or manually copy individual production files.

The runtime acceptance gate is: full PHPUnit success; all newly declared Contact cases executed with none skipped; all existing Foundation and Organization coverage gates still executed; no new PHPStan diagnostic after exact baseline comparison. The inherited 121 diagnostics are not reclassified as success and are not fixed opportunistically inside this package.

Rollback reverses only the BE-REORG-003 patch on a tree with no later overlapping edits. No database rollback or migration command is associated with this code-only reorganization.

## Explicit non-goals
- Moving or rewriting `ContactLifecycleAdapter`.
- Moving API-010, controllers, authorization registries/resolvers or employee-scope logic.
- Adding Contact domain locks, new transactions or new owner types.
- Changing Contact schema, lifecycle configuration, resource catalog, status vocabulary or wire contracts.
- Fixing unrelated PHPStan diagnostics or changing static-analysis configuration.
- Refactoring HR, Settings, Product, Pricing or other modules in the same patch.
