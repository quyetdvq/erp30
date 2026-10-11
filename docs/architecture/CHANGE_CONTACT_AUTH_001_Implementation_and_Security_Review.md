# CHANGE-CONTACT-AUTH-001 — Owner-type-aware Contact authorization

**Baseline:** `backend_v1.0.6` plus applied Increment 2A, 2B, and 2C1 (the user's last confirmed baseline was 533 PHPUnit tests / 4,768 assertions PASS). Frontend patch is based on the available frontend v1.0.5 Contact files; OpenAPI patch uses v1.0.5 plus Stage1 old.patch. **Current Windows frontend/OpenAPI were not uploaded**, so run `git apply --check` before applying. No live MariaDB access here.

## Security decision

One shared physical `contacts` table is retained; authorization is split by the **persisted owner type**:

| `contacts.contact_type` | Logical Resource | Evaluation Scope | Rollout |
|---|---|---|---|
| `ORGANIZATION` | `organization_contact` | Persisted owning Organization | Implemented |
| `EMPLOYEE` | `employee_contact` | Owner Employee's exactly one effective PRIMARY Assignment Organization | Implemented |
| `SUPPLIER` | future `supplier_contact` | Future Supplier ownership resolver | Not supported; fail closed |
| `USER` / `CUSTOM` | future personal/custom resource | Future explicit user ownership/sharing | Not supported; fail closed |
| Unknown future type | None | None | Fail closed |

`contact.view`/`contact.edit` and other legacy `contact.*` are **permanently retired for runtime authorization**. Even a reactivated legacy Resource cannot authorize Contact. New resources have their own Base Permissions, Policies, State and Organization Scope. There is no fallback from one Contact type to another.

The employee's management Organization follows a unique effective primary assignment **at the authorization evaluation date**. If it is missing, ambiguous or noncanonical, this Contact is invisible in listings and unavailable by ID. This is an **interim owner-scope rule**: Employee transfers may affect who can see existing Contacts. A future HR data-ownership project should define a permanent owner/control rule rather than quietly reusing GROUP.

## Migration 000015 and privilege cutover

`2026-10-11-000015_SplitContactAuthorizationResources.php` adds 2 logical resources, each with `view`, `create`, `edit`, `setstate`, `export`. It uses transactional DML and leaves existing Contact records in place.

1. No existing `contact.*` ALLOW grant is copied to any logical Contact Resource. Existing group/direct-user grants remain as inert audit history.
2. Existing legacy **DENY** and **ABSOLUTE_DENY** Policy Actions are carried conservatively to both logical Resources, including matching participant, Organization, status and State. Existing ALLOW Policy Actions are **not** copied. Mixed policies therefore become more restrictive after cutover, by design.
3. The legacy `contact` Resource is marked `INACTIVE`. ResourceActionSeeder enforces this on future seed runs. `AuthorizationService` blocks `contact` explicitly, and the admin `setstate` endpoint refuses to reactivate that Resource.
4. `SYSTEM_ADMIN` approved permission matrix intentionally changes from 95 to **90** actions: 5 retired Contact actions are not seeded, and no new logical Contact ALLOW is auto-granted. **Existing production group_permission rows are not deleted**, so the actual stored count in an old installation may remain above 90 until separately reviewed.
5. Migration blocks with an explicit error if legacy `contact` has a Lifecycle or a new target has conflicting Policies. No silent Policy overwrite. Rollback is guarded and intentionally refuses to restore potentially broad legacy permissions when grants/policies/logs exist. **Do not force or bypass rollback safety checks.**

**Operational consequence:** Contact UIs will remain inaccessible to people without explicit `organization_contact.*` / `employee_contact.*` grants after migration, including SYSTEM_ADMIN members until reviewed grants are assigned. Perform this as a planned security cutover during a maintenance window and authorize only intended group/action pairs. This temporary denial is preferable to silently replicating broad access.

## API and UI

The existing `/api/v1/contacts` URL remains, with no new database table. GET/PATCH/SETSTATE derive the Resource from the **persisted Contact row**, and CREATE from its validated owner type. Unsupported future types fail closed. Owner transfer via PATCH remains prohibited. Contact `status` is no longer accepted by ordinary PATCH; `/contacts/{id}/actions/set-state` is the sole authorized state mutation route. Contact creation starts `ACTIVE`.

Frontend ContactList shows Create/View/Edit/SetState actions using `organization_contact.*` or `employee_contact.*` per owner type. The modal no longer exposes `status` on a normal edit. OpenAPI now describes ContactPatch without owner fields or status. Generated frontend API types should be **regenerated** after contract application (never edit a generated file to cover for a schema mismatch).

## Security review / invariants

- Retain Base Permission and mandatory effective Employee Assignment eligibility, Organization inheritance, actor/assignment Policy lanes, ALLOW/DENY precedence and ABSOLUTE_DENY.
- Do not read owner scope or State from an untrusted client hint on existing records. `contact_type` and `contact_ref_id` are immutable after create.
- Do not auto-grant unknown owner kinds. A newly supported owner must register a Resource, Action catalog, scope resolver, native State contract, tests, UI permissions and a reviewed privilege migration **before** it becomes visible.
- Unknown/missing employee owner or missing/multiple primary assignment is not returned in Contact lists or GET results.
- No Contact-aware SQL pagination is enabled in this change. Increment 2C2 must be built after security regression, with differential PBAC test coverage.

## Static validation performed

- Reconstructed backend baseline: `backend_v1.0.6 + 2A + 2B + 2C1`.
- `git apply --check`, actual `git apply`, and `git apply --reverse --check`: **PASS** on a copy of baseline target files.
- After applying, **27 of 27 files** match the edited target source byte-for-byte.
- `php -l`: **PASS for 23 backend PHP files** (existing edits plus new files).
- Backend `git -c core.whitespace=cr-at-eol diff --check`: **PASS**.
- 6-case standalone owner registry smoke test: **PASS**.
- OpenAPI parses as YAML, 4 schema guards (`ContactPatch` owner/status immutable and `ContactCreate.status=ACTIVE`): **PASS**.
- PHPunit/MariaDB, frontend `vue-tsc`, Redocly, Prettier, and real API/UI: **NOT executed in this container** (their complete installed environments are unavailable). No runtime performance gain is claimed.

## Safe deployment sequence

1. Back up the real DB. Run `CHANGE_CONTACT_AUTH_001_Preflight_ReadOnly.sql` on the intended DB after checking `DBPrefix`; review legacy DENY Policies, existing broad permissions, unsupported Contact types and Employee Contact owners with missing/ambiguous primary assignments. **Do not silently clean up user data.**
2. On a test/staging clone, check and apply the combined patch. Do not apply both combined and individual patches. Use `git apply --check` without `--force`/`--3way`.
3. Run migration 000015 on the staging DB (`php spark migrate`); do not run rollback/refresh on the application DB.
4. Run the focused and full PHPUnit regression on a dedicated test DB. At least the pre-change 533 tests must still pass, plus the new Contact tests.
5. Regenerate frontend OpenAPI client, then lint/type-check/format/build. Verify UI Create/Edit/SetState works for each owner type and no generic `contact.*` buttons remain.
6. Explicitly grant appropriate **new** Resource Actions through existing authorization administration UI. Exercise employee assignment scope, Organization ancestor/sibling restrictions, DENY/ABSOLUTE_DENY, missing-primary, and cross-type denial.
7. Only then schedule production deployment/migration and the reviewed permission cutover, with rollback/recovery plan based on database backup (the migration down is intentionally guarded).

### Suggested Windows verification

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\CHANGE_CONTACT_AUTH_001_Type_Aware_Authorization_After_2C1.patch
git apply .\CHANGE_CONTACT_AUTH_001_Type_Aware_Authorization_After_2C1.patch
git -c core.whitespace=cr-at-eol diff --check -- backend/app backend/tests frontend/src contracts/openapi

cd backend
php vendor/bin/phpunit --no-coverage tests/unit/P1ContactAuth001OwnerAwareAuthorizationTest.php
php vendor/bin/phpunit --no-coverage tests/database/ChangeContactAuth001PolicyMigrationTest.php
php vendor/bin/phpunit --no-coverage tests/unit/P1Ctx001EvaluationOrganizationResolverTest.php
php vendor/bin/phpunit --no-coverage tests/unit/P1Wf006StateAwarePbacAndLocationTransitionsTest.php
php vendor/bin/phpunit --no-coverage tests/unit/P1Wf007NativeStateContractTest.php
php vendor/bin/phpunit --no-coverage

cd ..\frontend
pnpm run openapi:lint
pnpm run openapi:generate
pnpm run type-check
pnpm run lint
pnpm run format:check
pnpm run build
```

**Run `php spark migrate` on staging first** and only after backing up the actual DB when proceeding to a scheduled production change. The SQL preflight and PHPunit use different data sources.

## Deferred

- Supplier/Custom/Personal Contact storage, explicit owner and sharing rights, multi-owner Contact link semantics. These must be separate later changes.
- SQL-based Contact paging and `EXPLAIN`/p95 performance measurement (Increment 2C2), postponed until owner-aware access control is actually verified.
- Actual Windows frontend baseline confirmation: this package is generated from the last available frontend snapshot plus historical Stage1 contract changes, not from the user's latest full frontend source.
