# CHANGE-AUTH-DOMAIN-001 — Targeted Authorization Restructure

## Codex implementation handoff

**Status:** READY AFTER documentation correction is committed as repository authority  
**Change type:** targeted security/authorization refactor + forward migration + seed reconciliation + API-010 regression correction  
**P1 task count:** no new P1 Task ID; this is the approved precondition work before `P1-API-020`  
**Primary objective:** implement canonical Resource Domain resolution (`SYSTEM` / `MASTER` / `ORGANIZATION`) without globally reorganizing the backend.

---

# 1. Authority and prerequisites

Use the repository versions below as authority after the corrected DBD file has replaced the erroneous copy in the repo:

- `00_User_Requirements_v1.0.13.md`
- `01_P1_Functional_Requirements_Specification_v1.9.md`
- `02_P1_Database_Design_v1.12.md` **corrected**
- `03_P1_Implementation_Contract_v1.7.md`
- `04_P1_Implementation_Plan_v1.6.md`
- `05_P1_API_Design_v1.4.md`
- `06_P1_UI_Design_v1.5.md`

Before changing code, verify that the DBD correction is present:

1. `provinces` has no `parent_id`.
2. `product_groups.parent_id` is nullable self-FK to `product_groups.id`.
3. Product Group hierarchy indexes/rules are under `product_groups`, not Authorization `groups`.
4. `resources.domain` is `NOT NULL` in the target DBD and the canonical matrix contains exactly 20 P1 resources.

If the real repository has code newer than `backend_v1.0.0.zip`, reconcile against the real repository. Do not overwrite newer accepted behavior merely to match this snapshot.

---

# 2. Scope

Implement only the seam affected by `CHANGE-AUTH-DOMAIN-001`:

1. Resource Domain value type.
2. Resource catalog read boundary.
3. Domain-first Evaluation Organization resolution.
4. Explicit resolver registry for the 7 `ORGANIZATION` resources.
5. Removal of Primary Assignment fallback from Resource authorization.
6. Removal of duplicated Resource/Action fields from `EvaluationOrganizationRequest`.
7. Forward migration and exact 20-resource domain seed reconciliation.
8. API-010 Contact owner-scope correction.
9. Regression tests required to unblock `P1-API-020`.

This work must preserve the existing PBAC algorithm, precedence, inheritance, denied-set intersection, workflow behavior, Product/Pricing/Import domain logic, and public API semantics except where `CHANGE-AUTH-DOMAIN-001` explicitly changes scope semantics.

---

# 3. Explicit non-goals

Do **not** do any of the following in this change:

- Do not reorganize the entire backend into new `Application/Domain/Infrastructure` folders.
- Do not move existing `Services/*` or `Repositories/*` wholesale into `Modules/*`.
- Do not refactor large Product/UOM/Pricing/Import services for style or file size.
- Do not create a second public authorization architecture beside `AuthorizationService`.
- Do not change PBAC precedence: `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`.
- Do not change denied-set intersection or Assignment inheritance semantics.
- Do not introduce Resource Instance Authorization.
- Do not add `organization_id` to shared master tables to make authorization easier.
- Do not use Primary Assignment as Resource authorization fallback.
- Do not rewrite already-applied migrations.
- Do not stage/commit `.env`, PHPUnit cache, build logs, or local artifacts.
- Do not start `P1-API-020` in this change.

---

# 4. Current code problems to remove

The `backend_v1.0.0.zip` snapshot currently has these known issues:

## 4.1 Hard-coded Group Resource list

`app/Authorization/EvaluationOrganizationResolver.php` contains `GROUP_RESOURCES` with only:

- product
- product_group
- product_uom
- province
- ward
- user
- authorization_group
- resource

This is not the authority anymore. `resources.domain` is the authority.

Do not extend this array. Delete this model.

## 4.2 Generic field probing

The resolver currently probes fields such as:

- `organization_id`
- `owner_organization_id`
- `parent_organization_id`
- `scope_organization_id`

Generic probing must be removed. `ORGANIZATION` resources must resolve through the explicit canonical registry in section 8.

## 4.3 Primary Assignment fallback

`EvaluationOrganizationRequest` currently has `allowPrimaryAssignmentFallback`; `EvaluationOrganizationResolver` can resolve the actor Employee and Primary Assignment.

Remove this from Resource authorization completely.

Primary Assignment remains valid only for the separate Default Actor Organization Context capability already defined elsewhere. It is not an authorization-scope fallback.

## 4.4 Duplicate Resource/Action authorities

The current request contains `resource` and `operation`, while `AuthorizationService::authorize()` already receives Resource and Action.

Remove Resource and Action from `EvaluationOrganizationRequest`. There must be one authority for these values: the normalized `AuthorizationService::authorize()` parameters.

## 4.5 AuthorizationService reads Resource catalog too late

The current service resolves Evaluation Organization before `findResourceByType()`, so scope is selected without reading `resources.domain`.

Change the orchestration so Resource catalog + Domain are resolved before any PBAC decision work.

## 4.6 `PolicyRepository` mixes Resource catalog responsibility

`PolicyRepository` currently exposes:

- `findResourceByType()`
- `resourceActionBelongsToResource()`

Move Resource/Action catalog reads to a dedicated repository.

## 4.7 Inactive Organization is incorrectly rejected as context identity

`EvaluationOrganizationResolver::validatedContext()` currently requires `status = ACTIVE`.

Remove that status check from authorization-context identity resolution. An inactive/closed Organization remains a valid historical authorization context. Business services remain responsible for whether inactive records may be selected or mutated for a particular use case.

## 4.8 API-010 Employee Contact derives scope from Primary Assignment

`Api010FoundationOrganizationContactService::contactOwnerOrganizationId()` currently resolves:

`EMPLOYEE -> Primary Assignment -> organization_id`.

This is wrong under the new baseline.

Required semantics:

- `Contact owner ORGANIZATION` -> owner Organization.
- `Contact owner EMPLOYEE` -> Employee is `MASTER` -> `GROUP`.

The API layer must stop synthesizing `owner_organization_id` for authorization.

## 4.9 API-010 repeatedly constructs AuthorizationService

`auth()` currently returns the injected instance or `new AuthorizationService()` on each call. A list may create multiple authorization service graphs.

Use one AuthorizationService instance per `Api010FoundationOrganizationContactService` instance/request.

---

# 5. Target architecture

Keep the existing folder architecture. Add only a narrow authorization/catalog seam.

Recommended shape:

```text
app/Authorization/
├── ResourceDomain.php                         NEW
├── EvaluationOrganizationRequest.php          MODIFY
├── EvaluationOrganizationResolver.php         MODIFY
├── ResourceOrganizationScopeResolver.php      NEW
├── OrganizationScopeResolution.php            NEW
├── OrganizationScopeResolverRegistry.php      NEW
├── OrganizationResourceScopeResolver.php      NEW
├── ContactResourceScopeResolver.php           NEW
├── OrganizationIdResourceScopeResolver.php    NEW
├── AuthorizationLogScopeResolver.php          NEW
├── ImportJobScopeResolver.php                 NEW
└── ... existing Authorization classes

app/Repositories/
└── ResourceCatalogRepository.php              NEW
```

Exact class names may be adjusted to existing naming conventions, but the responsibilities and boundaries below are mandatory. Do not create a new top-level architecture/folder tree.

---

# 6. ResourceDomain contract

Use a PHP 8.3 backed enum or equivalent strongly constrained value type.

Canonical values only:

```text
SYSTEM
MASTER
ORGANIZATION
```

`COMPANY` is not a Resource Domain.

Required behavior:

- Missing domain -> fail closed.
- Empty domain -> fail closed.
- Unknown domain, including legacy `COMPANY` -> fail closed.
- The enum/value type must not silently coerce an unknown value.

Suggested enum:

```php
enum ResourceDomain: string
{
    case SYSTEM = 'SYSTEM';
    case MASTER = 'MASTER';
    case ORGANIZATION = 'ORGANIZATION';

    public function isGroupScoped(): bool
    {
        return $this === self::SYSTEM || $this === self::MASTER;
    }
}
```

Translate invalid catalog metadata to an existing stable authorization/domain error or a new stable code following the project convention. Do not accidentally surface a PHP `ValueError` as a 500 business response.

---

# 7. ResourceCatalogRepository contract

Create `app/Repositories/ResourceCatalogRepository.php` as the canonical read boundary for Resource/Action catalog metadata used by authorization.

Minimum responsibilities:

```php
findResourceByType(string $type): ?array
resourceActionBelongsToResource(int $resourceActionId, int $resourceId): bool
```

Optional helper methods are acceptable only if they remove repeated catalog parsing without broadening scope.

Move callers from `PolicyRepository` to this repository:

- `AuthorizationService`
- `AuthorizationAuditService`
- `PolicyValidator` for Resource Action membership validation

After migration of all callers, remove `findResourceByType()` and `resourceActionBelongsToResource()` from `PolicyRepository`.

Do **not** force unrelated Auto Numbering locking/query behavior into this repository in this change. `AutoNumberingSeriesRepository::findResourceByType()` and its row-lock responsibilities may remain where they are unless a direct correctness issue requires otherwise.

---

# 8. Canonical Resource Domain matrix

The implementation must recognize exactly this P1 matrix:

| Resource | Domain | Authoritative context |
|---|---|---|
| province | MASTER | GROUP |
| ward | MASTER | GROUP |
| department | MASTER | GROUP |
| position | MASTER | GROUP |
| employee | MASTER | GROUP |
| product_group | MASTER | GROUP |
| product_uom | MASTER | GROUP |
| product | MASTER | GROUP |
| user | SYSTEM | GROUP |
| authorization_group | SYSTEM | GROUP |
| resource | SYSTEM | GROUP |
| workflow_state | SYSTEM | GROUP |
| lifecycle | SYSTEM | GROUP |
| organization | ORGANIZATION | existing target / create parent |
| contact | ORGANIZATION | canonical owner |
| employee_assignment | ORGANIZATION | organization_id |
| policy | ORGANIZATION | organization_id |
| authorization_log | ORGANIZATION | persisted evaluated organization_id; NULL -> GROUP |
| price_list | ORGANIZATION | organization_id |
| import_job | ORGANIZATION | enabled import-target canonical scope |

Runtime code must **not** contain a second hard-coded `GROUP_RESOURCES` list duplicating the matrix. The database `resources.domain` controls whether GROUP or an ORGANIZATION resolver is used.

The explicit registry contains only the seven `ORGANIZATION` resources.

---

# 9. EvaluationOrganizationRequest contract

Change the request to contain only context material, not Resource/Action authority.

Target shape:

```php
final readonly class EvaluationOrganizationRequest
{
    /**
     * @param array<string, mixed>|null $persistedTarget
     * @param array<string, mixed> $input
     */
    public function __construct(
        public ?array $persistedTarget = null,
        public array $input = [],
        public mixed $clientEvaluationOrganizationId = null,
    ) {
    }
}
```

Remove:

- `resource`
- `operation`
- `allowPrimaryAssignmentFallback`

When updating production call sites, prefer **named constructor arguments** to reduce positional mistakes.

Example:

```php
new EvaluationOrganizationRequest(
    persistedTarget: $target,
    input: $input,
    clientEvaluationOrganizationId: $clientHint,
)
```

---

# 10. EvaluationOrganizationResolver contract

The resolver must no longer depend on actor Employee/Assignment to derive Resource scope.

It should resolve from:

- normalized Resource Type supplied by AuthorizationService;
- normalized Action supplied by AuthorizationService;
- parsed `ResourceDomain` from persisted Resource catalog metadata;
- persisted target/input/client hint from `EvaluationOrganizationRequest`.

It should **not** need `OperationContext` for Resource-scope resolution after Primary Assignment fallback is removed.

Recommended flow:

```text
Resource catalog row already loaded
-> parse ResourceDomain
-> SYSTEM / MASTER: resolve unique GROUP root
-> ORGANIZATION: lookup explicit resolver in registry
-> missing resolver: fail closed
-> resolver returns authoritative organization id + source
-> optional client hint must equal authoritative id
-> validate Organization exists and type is one of GROUP/COMPANY/BRANCH/PLANT/WAREHOUSE
-> DO NOT require Organization status ACTIVE
-> return EvaluationOrganizationContext
```

Group root resolution remains strict:

- exactly one root Organization with type `GROUP` and `parent_id = NULL`;
- no arbitrary Company fallback;
- no Primary Assignment fallback.

---

# 11. Explicit ORGANIZATION resolver registry

Create a registry that can resolve **only** the seven canonical ORGANIZATION resources. Missing registration must fail closed.

Do not use generic field-name probing.

A small reusable resolver may be registered for more than one resource when their canonical contract is identical; registration itself must still be explicit.

## 11.1 `organization`

Create:

```text
input.parent_id
```

Existing/read/edit/setstate/export row:

```text
persistedTarget.id
```

Source labels may retain current diagnostic names such as `CANONICAL_PARENT` / `PERSISTED_TARGET`.

## 11.2 `contact`

Create:

```text
input.contact_type + input.contact_ref_id
```

Existing/read/edit/setstate/export row:

```text
persistedTarget.contact_type + persistedTarget.contact_ref_id
```

Semantics:

```text
ORGANIZATION -> owner Organization id
EMPLOYEE     -> verify canonical Employee owner exists -> GROUP root
```

Do not inspect Employee Assignment. Employee status must not be required merely to identify historical Contact scope.

For existing mutation, persisted owner is authority. Input cannot transfer/override owner scope.

## 11.3 `employee_assignment`

Create:

```text
input.organization_id
```

Existing/read/edit/setstate/list row:

```text
persistedTarget.organization_id
```

## 11.4 `policy`

Create:

```text
input.organization_id
```

Existing/read/edit/delete/list row:

```text
persistedTarget.organization_id
```

## 11.5 `price_list`

Create:

```text
input.organization_id
```

Existing/read/edit/setstate/export/list row:

```text
persistedTarget.organization_id
```

## 11.6 `authorization_log`

Existing/detail/list/export row:

```text
persistedTarget.organization_id != NULL -> that id
persistedTarget.organization_id == NULL -> GROUP root
```

No create resolver is required for business API use.

## 11.7 `import_job`

P1 canonical target is Product only:

```text
persistedTarget.entity_type = PRODUCT
-> Product domain = MASTER
-> GROUP root
```

If `entity_type` is unsupported/unrecognized, fail closed. Do not infer actor context.

A future ORGANIZATION-scoped import target requires additive design; do not anticipate it with generic logic now.

---

# 12. AuthorizationService orchestration

Keep `AuthorizationService` as the single public authorization entry point used by existing protected use cases. Do not introduce a parallel public evaluator.

Keep the public method shape compatible except for the simplified request object:

```php
authorize(
    OperationContext $operation,
    string $resourceType,
    string $action,
    EvaluationOrganizationRequest $contextRequest,
    mixed $stateId = null,
): AuthorizationDecision
```

Required orchestration order:

```text
1. Validate actor_user_id.
2. Normalize Resource Type, Action, State.
3. ResourceCatalogRepository::findResourceByType(resource).
4. Missing Resource -> fail closed.
5. Parse resources.domain -> ResourceDomain; missing/unknown -> fail closed.
6. Resolve authoritative Evaluation Organization from Domain + explicit resolver contract.
7. Validate client hint mismatch if a hint exists.
8. Continue the existing Effective Profile / Base Permission / Resource Action catalog consistency checks.
9. Continue the existing ScopePath, policy loading, actor lane, Assignment lanes, QT1, ABSOLUTE_DENY propagation and denied-set intersection unchanged.
10. Build AuthorizationDecision exactly as before, using the new authoritative Evaluation Organization.
```

The important architectural condition is that PBAC never decides scope from resource-name hard coding, generic fields, or Primary Assignment.

Do not alter the PBAC section simply because this file is being edited.

---

# 13. Production caller migration

The snapshot has 12 production files constructing `EvaluationOrganizationRequest` and they must be updated to the simplified request contract:

```text
app/Modules/Pricing/PriceList/PriceListService.php
app/Modules/Product/Combo/ComboCompositionService.php
app/Modules/Product/Import/ProductImportAuthorizationService.php
app/Modules/Product/Product/ProductMasterService.php
app/Modules/Product/ProductGroup/ProductGroupService.php
app/Modules/Product/Uom/ProductUomMasterService.php
app/Modules/Product/Uom/ProductUomService.php
app/Services/Api010FoundationOrganizationContactService.php
app/Services/AuthorizationGroupService.php
app/Services/AutoNumberingConfigurationService.php
app/Services/LifecycleService.php
app/Services/PolicyAdministrationService.php
```

Rules for caller changes:

- Do not change business payloads merely to feed authorization.
- Remove synthetic `owner_organization_id` authorization fields.
- MASTER/SYSTEM callers should not manufacture GROUP ids; Domain resolution handles GROUP.
- Existing ORGANIZATION operations pass persisted target.
- Create ORGANIZATION operations pass canonical input needed by the registered resolver.
- A client `evaluation_organization_id` remains only a mismatch-detection hint, never authority.

Existing narrow interfaces such as `LifecycleAuthorizationPort` and `NumberingAuthorizationPort` may retain their method signatures because they still accept `EvaluationOrganizationRequest`; only request contents change.

---

# 14. API-010 correction

Modify `Api010FoundationOrganizationContactService` as part of the same change because the old service manufactures incorrect Contact scope.

## 14.1 Delete API-level scope derivation

Remove:

- `authorizationTarget()` Contact mutation that adds `owner_organization_id`.
- `contactOwnerOrganizationId()`.
- Employee Primary Assignment lookup used only for Contact authorization.
- `EmployeeRepository` / `EmployeeAssignmentService` constructor dependencies if no longer used elsewhere in this class.

For Contact authorization:

- existing row: pass the persisted Contact row unchanged;
- create: pass the Contact payload unchanged as `input`;
- resolver derives owner scope from `contact_type + contact_ref_id`.

## 14.2 One AuthorizationService per service/request

Do not implement:

```php
return $this->authorization ?? new AuthorizationService();
```

on every authorization call.

Initialize/cache one instance in the service object and return it for all row checks in the request.

Do not expand this change into a general dependency-container rewrite.

---

# 15. Forward-only Resource Domain migration

Do not edit `2026-10-05-000005_CreateResourceAuthorizationNumberingTables.php`.

Add a new forward migration after the current latest migration (current snapshot latest is `000009_AddProductGroupHierarchy`). Use the repository's migration naming convention; a logical name is equivalent to:

```text
...000010_NormalizeResourceDomains.php
```

Required `up()` semantics:

1. Update the exact canonical 20 `resources.type` rows to the exact Domain matrix in section 8.
2. Do not infer domain from arbitrary names or data columns.
3. Verify that no existing Resource row remains with:
   - `domain IS NULL`,
   - empty domain,
   - domain outside `SYSTEM|MASTER|ORGANIZATION`.
4. If unknown/noncanonical rows prevent safe normalization, fail the migration clearly rather than guessing a domain.
5. Alter `resources.domain` to `VARCHAR(100) NOT NULL`.
6. Preserve existing Resource ids, lifecycle links, actions, permissions, policies, numbering series, and audit references.

Required `down()` behavior:

- Follow repository rollback convention.
- It may restore column nullability if rollback is supported.
- Do not erase/rewrite historical Resource/Action records merely to recreate legacy null domains.

The migration must work both:

- on a fresh schema path where `resources` may still be empty before seeding;
- on an existing snapshot with the 20 canonical rows and legacy null domain values.

---

# 16. ResourceActionSeeder reconciliation

Extend `ResourceActionSeeder` so every canonical Resource has its exact canonical Domain.

Recommended structure:

```php
private const DOMAINS = [
    'province' => 'MASTER',
    // ... exact 20-resource mapping
];
```

Requirements:

- Missing resource row: insert with exact `domain`.
- Existing row: do not leave domain null.
- Prefer migration as the approved reconciliation mechanism for an existing database.
- On normal repeated seed after migration, existing canonical domain must remain exact.
- Do not seed `COMPANY`.
- Do not add Resource types or Actions outside the approved existing matrix.
- Preserve seed idempotency.

If Codex chooses to make the seeder repair a mismatching existing domain, that behavior must be explicitly justified as part of this approved change-control implementation and covered by a test; silent normal-runtime/domain-edit behavior remains prohibited.

---

# 17. Resource domain immutability boundary

`resources.domain` is immutable for canonical existing Resource during normal P1 Resource CRUD.

This change does not need to invent Resource CRUD that does not exist yet, but any existing/current mutation path that can edit `resources.domain` must reject it. Future API-030 implementation must treat Domain as required on create and read-only after creation.

Migration/approved seed reconciliation is not normal Resource CRUD and is allowed to set the canonical baseline.

---

# 18. Tests — mandatory coverage

Do not consider this change complete with only unit tests for the enum. The purpose is authorization-scope regression safety.

## 18.1 ResourceDomain/catalog tests

Add tests for:

- exactly three accepted values;
- missing/empty domain fails closed;
- legacy `COMPANY` fails closed;
- unknown domain fails closed;
- Resource catalog lookup persistence error is not converted to not-found/ALLOW;
- Resource Action membership remains correct through `ResourceCatalogRepository`.

## 18.2 Exact 20-resource matrix test

Seed the catalog and assert all 20 Resource Types have the exact expected Domain.

Do not only count 20 rows; compare the full type -> domain map.

## 18.3 SYSTEM/MASTER resolution

At minimum assert all 13 SYSTEM/MASTER Resource Types resolve to the unique GROUP root from persisted `resources.domain`, without a hard-coded Group Resource array.

Client hint:

- GROUP hint -> accepted;
- non-GROUP hint -> mismatch rejected.

## 18.4 Seven ORGANIZATION resolver tests

Cover each canonical registry resource:

1. organization
2. contact
3. employee_assignment
4. policy
5. authorization_log
6. price_list
7. import_job

Missing registry entry must fail closed.

## 18.5 Contact regression tests

Mandatory cases:

```text
ORGANIZATION Contact -> owner Organization
EMPLOYEE Contact -> GROUP
```

Critical regression case:

- Employee has Primary Assignment at Company A.
- Employee Contact is authorized.
- Evaluation Organization must still be GROUP, not Company A.

Also test Employee with no Primary Assignment: Contact scope still resolves GROUP if the Employee exists.

## 18.6 No Primary Assignment fallback

Delete/replace the old test that expects explicit Resource Primary Assignment fallback.

Add a negative test proving a missing ORGANIZATION Resource context cannot be rescued by actor Primary Assignment.

## 18.7 Client hint mismatch

For an existing ORGANIZATION target, mismatching client hint must still reject.

For SYSTEM/MASTER, a non-GROUP client hint must reject.

## 18.8 Inactive Organization context regression

Create an inactive Organization that is still a canonical Organization row and resolve an existing protected target scoped to it.

Evaluation Organization identity resolution must succeed. Whether the subsequent business operation is allowed due to record status is a separate service/business rule.

## 18.9 Authorization Log resolver

- `organization_id = <id>` -> that Organization.
- `organization_id = NULL` -> GROUP.

## 18.10 Import Job resolver

- `entity_type = PRODUCT` -> GROUP.
- unsupported entity type -> fail closed.

## 18.11 Migration tests

Required evidence:

- fresh migration pass;
- forward migration from legacy nullable-domain schema/data pass;
- canonical 20 rows normalized correctly;
- `resources.domain` is NOT NULL after migration;
- unknown unsafe legacy row does not get a guessed domain;
- rollback behavior matches repository convention.

Update `P1Db050ResourceAuthorizationNumberingTest` so `resources.domain` is expected non-null.

## 18.12 Seeder tests

Update `P1Seed001ResourceActionSeedTest` or add a focused test to assert both:

- existing action matrix remains exactly unchanged;
- exact 20-resource Domain matrix is correct after repeated seed.

## 18.13 Resource fixture updates

The snapshot currently has seven test files inserting `resources` directly. Update every direct fixture insert to supply a valid canonical domain after the NOT NULL migration.

Do not fix these by weakening the schema back to nullable.

## 18.14 Existing PBAC regression

Run the full authorization regression and verify no change to:

- Base Permission prerequisite;
- System Admin no-bypass behavior;
- QT1 precedence;
- Assignment path eligibility;
- ancestor inheritance;
- ABSOLUTE_DENY propagation;
- denied-set intersection;
- policy state filtering;
- sibling/Company isolation.

---

# 19. Existing test migration inventory from snapshot

Tests using `EvaluationOrganizationRequest` in the snapshot include:

```text
tests/fixtures/auto_numbering_default_concurrency_worker.php
tests/unit/P1Auth002AuthorizationServiceTest.php
tests/unit/P1Auth003AuthorizationDecisionTest.php
tests/unit/P1AuthorizationMandatoryRegressionTest.php
tests/unit/P1AutoNumberingMandatoryRegressionTest.php
tests/unit/P1Ctx001EvaluationOrganizationResolverTest.php
tests/unit/P1Num001AutoNumberingTest.php
tests/unit/P1Num002NumberingPatternValidatorTest.php
tests/unit/P1Num006ProductIdentifierAdapterTest.php
tests/unit/P1Wf002LifecycleServiceTest.php
```

Do not leave old constructor calls compiling only by reintroducing Resource/Action into the request.

Direct Resource fixture insert files found in the snapshot:

```text
tests/database/P1Db050ResourceAuthorizationNumberingTest.php
tests/unit/P1AutoNumberingMandatoryRegressionTest.php
tests/unit/P1Num001AutoNumberingTest.php
tests/unit/P1Num002NumberingPatternValidatorTest.php
tests/unit/P1Num004CounterAllocatorTest.php
tests/unit/P1Num005ResourceIdentifierAdapterTest.php
tests/unit/P1Num006ProductIdentifierAdapterTest.php
```

Re-scan the real repository because newer files may exist.

---

# 20. Error/fail-closed rules

The implementation must fail closed when any of these occurs:

- Resource Type does not exist in catalog.
- Resource Domain is null/empty/unknown/legacy `COMPANY`.
- `ORGANIZATION` resource is not registered.
- required persisted target is absent.
- required canonical owner/parent/organization field is absent or invalid.
- Contact owner type unsupported.
- Contact owner does not exist.
- Import Job target unsupported.
- resolved Organization does not exist.
- resolved Organization has noncanonical type.
- client context hint mismatches authoritative context.

Do not translate these failures into ALLOW or Primary Assignment fallback.

Persistence failures must remain persistence failures; do not convert DB outages/errors into ordinary not-found context and continue.

---

# 21. Security invariants that must remain unchanged

After context resolution, existing PBAC behavior remains authoritative:

```text
No Base Permission -> DENY

Per lane:
ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH
NO_MATCH -> ALLOW

Inheritance before denied-set intersection
Applicable ABSOLUTE_DENY propagated to affected child lanes before intersection
No System Admin bypass
No Resource Instance Authorization
```

Do not use this change as an opportunity to simplify/rewrite the policy evaluator.

---

# 22. Suggested implementation sequence

Codex should implement in this order so failures are localized:

## Phase A — Authorization types and catalog seam

1. Add `ResourceDomain`.
2. Add `ResourceCatalogRepository` + focused tests.
3. Move catalog callers from `PolicyRepository`.
4. Keep PBAC behavior unchanged.

## Phase B — Context resolver seam

5. Simplify `EvaluationOrganizationRequest`.
6. Add explicit ORGANIZATION resolver interface/value/registry.
7. Implement seven canonical registrations.
8. Rewrite `EvaluationOrganizationResolver` to use Domain + registry.
9. Remove Employee/Assignment Primary fallback dependencies and inactive-status context rejection.
10. Update `AuthorizationService` orchestration to resolve Resource catalog/domain first.

## Phase C — Caller migration

11. Update all production `EvaluationOrganizationRequest` call sites using named args.
12. Update narrow authorization ports/tests without creating another public authorization path.

## Phase D — Persistence

13. Add forward-only Resource Domain migration.
14. Update ResourceActionSeeder with exact domain matrix.
15. Update schema/seed/direct-resource-fixture tests.

## Phase E — API-010 correction

16. Remove Contact scope calculation from API-010.
17. Remove Employee Primary Assignment dependency from Contact authorization path.
18. Cache/inject a single AuthorizationService per API-010 service/request.
19. Add real API-010 Contact owner-scope regression tests.

## Phase F — verification

20. Run focused tests after each phase.
21. Run full PHPUnit.
22. Run PHPStan level 8.
23. Run fresh migration + forward migration rehearsal.
24. Re-run seed twice and verify idempotency.
25. Confirm no `.env`, build logs, cache or unrelated refactor in diff.

---

# 23. Required quality commands/evidence

Use the commands defined by the real repository/CI. At minimum provide evidence for:

```text
PHP syntax/lint as applicable
PHPUnit full suite
PHPStan level 8
fresh migration test
forward migration test
ResourceActionSeeder repeated/idempotency test
```

If the snapshot archive lacks Composer/vendor/config, run these in the actual repository environment rather than claiming success from the ZIP alone.

Final handoff report must include:

- commands executed;
- pass/fail counts;
- any skipped test and exact reason;
- migration result;
- PHPStan result;
- list of files changed;
- confirmation that `P1-API-020` was not started.

---

# 24. Definition of Done

This change is DONE only when all are true:

- [ ] Corrected documentation authority is committed first.
- [ ] No hard-coded `GROUP_RESOURCES` authorization model remains.
- [ ] No generic Organization field probing remains for Resource authorization.
- [ ] No Resource authorization Primary Assignment fallback remains.
- [ ] `EvaluationOrganizationRequest` no longer contains Resource/Action/fallback flag.
- [ ] Resource Domain is loaded from persisted Resource catalog before PBAC scope evaluation.
- [ ] Exact seven ORGANIZATION resources are explicitly registered.
- [ ] Missing/unknown Domain/resolver/context fails closed.
- [ ] Inactive Organization can remain a valid historical authorization context identity.
- [ ] `PolicyRepository` no longer acts as Resource catalog read repository.
- [ ] `resources.domain` is NOT NULL after forward migration.
- [ ] Exact 20-resource Domain matrix is persisted and seed-idempotent.
- [ ] API-010 Organization Contact resolves owner Organization.
- [ ] API-010 Employee Contact resolves GROUP regardless of Employee Primary Assignment.
- [ ] API-010 does not synthesize `owner_organization_id` for authorization.
- [ ] API-010 does not instantiate a new AuthorizationService for each row check.
- [ ] Authorization Log `NULL -> GROUP` test passes.
- [ ] Product Import Job `PRODUCT -> GROUP` test passes.
- [ ] Full authorization mandatory regression passes.
- [ ] Full PHPUnit passes.
- [ ] PHPStan level 8 passes.
- [ ] Fresh + forward migration tests pass.
- [ ] `.env` and build/test artifacts are absent from the change.
- [ ] `P1-API-020` remains blocked until this evidence is accepted.

---

# 25. Codex execution instruction

Use the following as the controlling instruction when executing this handoff:

> Implement `CHANGE-AUTH-DOMAIN-001` exactly as specified in this handoff and the committed P1 authority documents. Treat this as a targeted authorization/security refactor, not a global architecture cleanup. First inspect the real repository and reconcile any differences from `backend_v1.0.0.zip`. Keep `AuthorizationService` as the single public protected-use-case authorization entry point. Make `resources.domain` the sole Resource Domain authority; remove Resource-name Group hard-coding, generic Organization field probing, duplicate Resource/Action values in `EvaluationOrganizationRequest`, and Resource authorization Primary Assignment fallback. Implement the explicit seven-resource ORGANIZATION resolver registry, exact 20-resource domain migration/seed matrix, API-010 Contact owner-scope correction, and all mandatory regression coverage. Do not rewrite applied migrations, alter PBAC semantics, add persistence outside baseline, globally reorganize folders, or start `P1-API-020`. Run and report full PHPUnit, PHPStan level 8, fresh/forward migration, and seed idempotency evidence before declaring completion.

