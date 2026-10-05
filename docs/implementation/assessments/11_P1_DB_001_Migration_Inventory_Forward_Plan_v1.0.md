# P1-DB-001 Migration Inventory & Forward-Only Plan

**Task:** P1-DB-001
**Assessment date:** 2026-10-05
**Repository:** `D:\XAMPP\htdocs\erp`
**Status:** DONE (inventory and plan only)

## 1. Task contract

This assessment executes only **P1-DB-001 - Migration inventory & forward-only plan**.

Authoritative sources used:

- `docs/architecture/02_P1_Database_Design_v1.10.md`, sections 4, 6-23, 26-29;
- `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, sections 3, 5, 7-8, 18-25;
- `docs/architecture/00_User_Requirements_v1.0.12.md`, `01_P1_Functional_Requirements_Specification_v1.7.md`, and `03_P1_Implementation_Contract_v1.5.md` for boundary cross-check;
- `docs/implementation/assessments/08_P1_Repository_Runtime_Assessment_v1.0.md` and `09_P1_Gate0_Readiness_Assessment_v1.0.md` for the current Gate 0/runtime baseline;
- `AGENTS.md` and `.codex/skills/dinkerp-implementation/SKILL.md`.

Scope:

- inventory the actual package/application migration and seed state;
- map the DBD v1.10 canonical P1 tables to their owners and dependencies;
- propose deterministic CI4 migration filenames/classes and execution order;
- classify the DBD v1.10 existing-schema harmonization list against the clean-rebuild state;
- define fresh and forward rehearsal strategy for P1-DB-090.

Explicitly out of scope:

- no application migration PHP file;
- no seeder;
- no Slice 1/business functionality;
- no architecture-document edits;
- no database reset, refresh, rollback, or schema mutation;
- no recreation or inference of historical application migration `000021`.

Dependencies: **P1-ENV-004 is complete** according to the current task evidence and the checks recorded in this report. Therefore P1-DB-001 is READY and can be completed as a planning task.

Impact classification: DB/schema planning only. No authorization, API, UI, or i18n behavior is implemented. Future migration batches must preserve DBD FK, audit, transaction, DomainLock, decimal, Shield-boundary, and forward-only invariants.

## 2. Actual repository and migration map

Relevant runtime paths:

| Area | Actual location | Current state |
|---|---|---|
| Backend | `backend/` | CodeIgniter 4.7.4 application |
| Frontend | `frontend/` | Vue/Vite application; outside this DB-only task |
| Architecture authority | `docs/architecture/00..06_*.md` | baseline UR/FRS/DBD/Contract/Plan/API/UI documents; unchanged |
| Implementation evidence | `docs/implementation/assessments/` | prior Gate 0/P1-ENV-004 reports plus this assessment |
| Application migrations | `backend/app/Database/Migrations/` | `.gitkeep` only; no application migration class exists |
| Application seeds | `backend/app/Database/Seeds/` | `.gitkeep` only; no application seeder exists |
| Migration config | `backend/app/Config/Migrations.php` | enabled; table `migrations`; timestamp format `Y-m-d-His_`; lock disabled |
| Test DB harness | `backend/tools/test-db-harness.php` | isolated `erp_test` guard; destructive actions require `--yes` |
| Shield package migrations | `backend/vendor/codeigniter4/shield/src/Database/Migrations/` | one package migration: `2020-12-28-223112_create_auth_tables.php` |
| Settings package migrations | `backend/vendor/codeigniter4/settings/src/Database/Migrations/` | `2021-07-04-041948_CreateSettingsTable.php`, `2021-11-14-143905_AddContextColumn.php`, `2026-07-20-181246_ConvertSqlsrvValueColumn.php` |
| Tests | `backend/tests/` | current suite runs without application business schema |
| API contract | `contracts/openapi/openapi.yaml` | existing contract source; not changed by this task |
| Generated client | `frontend/src/api/generated/` | existing generated frontend artifacts; not changed by this task |

The current worktree contains unrelated pre-existing modifications and deleted legacy application migrations. They are preserved. This report does not restore, rewrite, or reinterpret them.

### 2.1 Current physical migration state

The configured development database is `erp` with `DBPrefix = dinktech_`. A read-only table inventory showed exactly these package/runtime tables:

`dinktech_users`, `dinktech_auth_identities`, `dinktech_auth_logins`, `dinktech_auth_token_logins`, `dinktech_auth_remember_tokens`, `dinktech_auth_groups_users`, `dinktech_auth_permissions_users`, `dinktech_settings`, and `dinktech_migrations`.

The configured PHPUnit database is `erp_test` with the same prefix. The read-only test inventory showed only the harness tables `dinktech_factories` and `dinktech_migrations`; it does not contain an application P1 schema.

The default database currently records four package migrations, all in batch 1:

1. `CodeIgniter\\Shield` `2020-12-28-223112_create_auth_tables`;
2. `CodeIgniter\\Settings` `2021-07-04-041948_CreateSettingsTable`;
3. `CodeIgniter\\Settings` `2021-11-14-143905_AddContextColumn`;
4. `CodeIgniter\\Settings` `2026-07-20-181246_ConvertSqlsrvValueColumn`.

`P1-DB-001` must treat the application schema as a **clean rebuild baseline after the authorized development reset**. The old application migration numbering, including the discarded `000021`, is not a source for a new migration and must not be recreated as a no-op or manually inserted into the migration ledger.

### 2.2 Package boundary

The current Shield configuration maps these physical tables:

- `users`;
- `auth_identities`;
- `auth_logins`;
- `auth_token_logins`;
- `auth_remember_tokens`;
- `auth_groups_users`;
- `auth_permissions_users`.

CodeIgniter Settings uses the physical table `settings` with `class`, `key`, `value`, `type`, `context`, `created_at`, and `updated_at` columns. These package tables are external prerequisites. Application migrations must not recreate or alter them as P1 business tables.

There is no local User/password/session table, no `profiles` table, no separate local permission table, and no Resource Instance Authorization persistence in the DBD baseline. `groups` below is the ERP Authorization Group alias bridge to Shield `auth_groups_users.group`; it is not an Organization of type `GROUP`.

## 3. Canonical P1 table inventory

The following inventory is derived from DBD v1.10 sections 6-18, 20-23 and follows DBD section 22 order. `status` is shown as `NOT NULL` unless the DBD row explicitly makes a lifecycle/state reference nullable. `audit` lists the physical audit fields required by DBD; logical Shield actor references have no physical FK.

| # / owning task | Table | Dependencies and physical FKs | Unique constraints / important indexes | Audit and lifecycle/nullability | Application invariants and polymorphic boundary | Origin |
|---|---|---|---|---|---|---|
| B / P1-DB-010 | `provinces` | none | `UNIQUE(code)`; PK `id`; indexes `name`, `status` | audit `created_by`, `updated_by`, `created_at`, nullable `updated_at`; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | inactive rows remain valid historical references; inactive rows cannot be selected for new data | create-new |
| B / P1-DB-010 | `wards` | `province_id -> provinces.id` | `UNIQUE(code)`; PK; indexes `province_id`, `name`, `status` | same audit; `status NOT NULL` | new ward reference requires active Province; Ward/Province inactive does not remove history | create-new |
| C / P1-DB-020 | `organizations` | `parent_id -> organizations.id`; `province_id -> provinces.id`; `ward_id -> wards.id` | `UNIQUE(code)`; PK; indexes `parent_id`, `type`, `status`, `tax_code`, `province_id`, `ward_id` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`/`CLOSED`); `parent_id`, `legal_name`, `tax_code`, address/location and active dates nullable | exactly one business GROUP root; no self-parent/cycle; strict parent matrix; Ward belongs to Province; resolve valid Company ancestor | create-new |
| C / P1-DB-020 | `contacts` | `province_id -> provinces.id`; `ward_id -> wards.id`; `contact_ref_id` has **no FK** | PK; indexes `(contact_type, contact_ref_id)`, `email`, `phone`, `mobile`, `province_id`, `ward_id`, `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`/`CLOSED`) | `contact_type` is `EMPLOYEE`/`ORGANIZATION`; application validates polymorphic `contact_ref_id`; Ward must match Province | create-new; polymorphic |
| D / P1-DB-030 | `departments` | `parent_id -> departments.id` | `UNIQUE(code)`; PK; indexes `parent_id`, `status` | audit fields; `status NOT NULL` | no self-parent/cycle; deliberately no `organization_id` ownership | create-new |
| D / P1-DB-030 | `positions` | none | `UNIQUE(code)`; PK; indexes `level`, `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`/`CLOSED`) | position level and active-state eligibility are service concerns | create-new |
| D / P1-DB-030 | `employees` | `manager_employee_id -> employees.id`; `user_id` is logical Shield ref, no physical FK | `UNIQUE(user_id)` (multiple NULL allowed), `UNIQUE(employee_code)`; PK; indexes `employee_name`, `manager_employee_id`, `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | one Shield user maps to at most one employee; no self-manager/cycle; inactive employee cannot be new manager; no separate contact store | create-new; logical Shield ref |
| D / P1-DB-030 | `employee_assignments` | `employee_id -> employees.id`; `organization_id -> organizations.id`; nullable `department_id -> departments.id`; nullable `position_id -> positions.id` | PK; indexes `employee_id`, `organization_id`, `department_id`, `position_id`, `(employee_id,is_primary,effective_from,effective_to)`, `status` | audit fields; `status NOT NULL`; `effective_to` nullable | effective interval/order, one non-overlapping primary assignment, inactive target rejection, exact ancestor-path PBAC eligibility; no polymorphic FK | create-new |
| E / P1-DB-040 | `lifecycles` | none | `UNIQUE(code)`; PK; index `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | lifecycle configuration is transactionally changed with its mappings | create-new |
| E / P1-DB-040 | `workflow_states` | none | `UNIQUE(code)`; PK; index `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | canonical state catalog; seed only states required by approved resources | create-new |
| E / P1-DB-040 | `lifecycle_states` | `lifecycle_id -> lifecycles.id`; `state_id -> workflow_states.id` | `UNIQUE(lifecycle_id,state_id)`; PK; index `(lifecycle_id,sequence)` | audit fields; no separate status column | at most one initial state per active lifecycle; final states may be multiple | create-new |
| E / P1-DB-040 | `lifecycle_transitions` | `lifecycle_id -> lifecycles.id`; `from_state_id -> workflow_states.id`; `to_state_id -> workflow_states.id` | `UNIQUE(lifecycle_id,from_state_id,action_code)`; PK; index `(lifecycle_id,from_state_id,status)` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | both states belong to the lifecycle; one destination per lifecycle/from/action; transition does not grant permission | create-new |
| F / P1-DB-050 | `resources` | nullable `lifecycle_id -> lifecycles.id` | `UNIQUE(type)`; PK; indexes `domain`, `lifecycle_id` | audit fields; no status column; lifecycle reference nullable | resource is capability/type, never one row per business record; no Resource Instance Authorization | create-new |
| F / P1-DB-050 | `resource_actions` | `resource_id -> resources.id` | `UNIQUE(resource_id,code)`; PK; index `code` | audit fields; no status column | permission code is `<resource.type>.<code>`; action catalog is explicit and seeded from approved contract | create-new |
| F / P1-DB-050 | `auto_numbering_series` | `resource_id -> resources.id` | `UNIQUE(resource_id,series_code)`; PK; indexes `(resource_id,status)`, `(scope_type,status)` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`); `is_default NOT NULL` | one active default per Resource is service/transaction enforced; valid scope/reset/pattern; post-issuance immutability/no hard-delete | create-new |
| F / P1-DB-050 | `auto_numbering_counters` | `numbering_series_id -> auto_numbering_series.id`; `scope_organization_id -> organizations.id` | `UNIQUE(numbering_series_id,scope_organization_id,period_key)`; PK; index `scope_organization_id` | only `created_at`, nullable `updated_at`; no status/audit actor | period key follows reset policy; counter and business create share one transaction; DomainLock/advisory lock and occupied-candidate skip are runtime rules | create-new |
| F / P1-DB-050 | `groups` | no physical FK to Shield group alias | `UNIQUE(code)`; PK; index `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | `groups.code <-> auth_groups_users.group`; not Organization `GROUP`; membership remains Shield-owned | create-new; Shield bridge |
| F / P1-DB-050 | `group_permissions` | `group_id -> groups.id`; `resource_action_id -> resource_actions.id` | composite PK `(group_id,resource_action_id)`; index `resource_action_id` | audit fields; no status column | no Organization Scope column; effective membership is resolved through Shield group aliases | create-new |
| F / P1-DB-050 | `policies` | `organization_id -> organizations.id`; `resource_id -> resources.id`; nullable `state_id -> workflow_states.id`; `participant_ref_id` no FK | PK; indexes `(organization_id,resource_id,state_id)`, `(participant_type,participant_ref_id)`, `resource_id` | audit fields; `state_id NULL` means all states | participant is polymorphic `USER`/`GROUP`/`POSITION`/`DEPARTMENT`/`ORGANIZATION`; no FK; runtime inheritance-before-intersection and `ABSOLUTE_DENY` propagation | create-new; polymorphic |
| F / P1-DB-050 | `policy_actions` | `policy_id -> policies.id`; `resource_action_id -> resource_actions.id` | `UNIQUE(policy_id,resource_action_id)`; PK; indexes `resource_action_id`, `effect` | audit fields; no status column | action Resource must match policy Resource; effect is `ALLOW`/`DENY`/`ABSOLUTE_DENY` | create-new |
| F / P1-DB-050 | `authorization_logs` | `resource_id -> resources.id`; nullable `organization_id` is contextual ID; nullable `state_id -> workflow_states.id`; nullable `matched_policy_id -> policies.id`; `user_id` logical Shield ref, no FK | PK; indexes `(user_id,created_at)`, `(organization_id,created_at)`, `(resource_id,action,created_at)`, `request_id`, `decision` | only `created_at`; no status/updated audit | persisted audit minimum is approve/delete/state change; view/list need not persist; request ID is correlation data | create-new; logical Shield ref |
| G / P1-DB-060 | `product_groups` | none | `UNIQUE(code)`; PK; index `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | configurable master | create-new |
| G / P1-DB-060 | `product_uoms` | none | `UNIQUE(code)`; PK; index `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | UOM master; Base UOM is implicit factor 1 in conversion logic | create-new |
| G / P1-DB-060 | `products` | self `parent_product_id -> products.id`; `product_group_id -> product_groups.id`; `base_uom_id -> product_uoms.id`; `code` is Auto Number business identifier, not physical `id` | `UNIQUE(code)`, nullable `UNIQUE(sku)`, nullable `UNIQUE(barcode)`; PK; indexes `parent_product_id`, `product_group_id`, `base_uom_id`, `product_name`, `product_child_name`, `product_type`, `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`); role is derived, not nullable persisted state | no Variant table; SIMPLE/PARENT/CHILD derived from type/parent/children; depth 1; PARENT not selectable; COMBO no conversion/serial; semantic fields lock after transaction history | create-new |
| G / P1-DB-060 | `product_uom_conversions` | `product_id -> products.id`; `uom_id -> product_uoms.id` | `UNIQUE(product_id,uom_id)`; PK; indexes `uom_id`, `status` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`) | direct-to-base only; alternate UOM cannot equal base; positive factor; only SIMPLE/CHILD; price is explicit, not derived | create-new |
| G / P1-DB-060 | `product_combo_items` | `combo_product_id -> products.id`; `component_product_id -> products.id` | `UNIQUE(combo_product_id,component_product_id)`; PK; index `component_product_id` | audit fields; no status column | owner COMBO; component SIMPLE/CHILD/COMBO, never PARENT; no self-edge/cycle; quantity > 0 and in component Base UOM | create-new |
| H / P1-DB-070 | `price_lists` | `organization_id -> organizations.id` | `UNIQUE(code)`; PK; index `(organization_id,status,effective_from,effective_to)`, `priority` | audit fields; `status NOT NULL` (`ACTIVE`/`INACTIVE`); `effective_to` nullable/open-ended | scope only GROUP/COMPANY; half-open interval `[effective_from,effective_to)`; overlap allowed | create-new |
| H / P1-DB-070 | `product_price_list_items` | `price_list_id -> price_lists.id`; `product_id -> products.id`; `uom_id -> product_uoms.id` | `UNIQUE(price_list_id,product_id,uom_id)`; PK; indexes `(product_id,uom_id)`, `uom_id` | audit fields; no status column | no PARENT; UOM must be valid for product; COMBO only Base UOM; price is exact DECIMAL | create-new |
| I / P1-DB-080 | `import_jobs` | none | PK; indexes `(entity_type,created_at)`, `(mode,created_at)`, `(status,created_at)`, `(created_by,created_at)`, `source_file_hash` | actor audit `created_by`, `updated_by`; `status NOT NULL`; `started_at`/`completed_at` nullable | entity type baseline only PRODUCT; mode persisted (`CREATE_ONLY`/`UPDATE_ONLY`/`UPSERT`); counts must reconcile; source hash optional | create-new |
| I / P1-DB-080 | `import_job_rows` | `import_job_id -> import_jobs.id`; `target_ref_id` no FK | `UNIQUE(import_job_id,row_number)`; PK; indexes `(import_job_id,row_status)`, `entity_key` | only `created_at`; `row_status NOT NULL`; target/error fields nullable | typed/logical target ref is polymorphic by job entity; row ERROR is not successful business data; row order preserved; no raw sensitive payload required | create-new; polymorphic |

### 3.1 Physical FK and polymorphic summary

All mandatory physical relationships are the DBD v1.10 section 18.1 list. The following are deliberately **not** physical FKs: `contacts.contact_ref_id`, `policies.participant_ref_id`, `import_job_rows.target_ref_id`, and Shield identity references including `user_id`, `created_by`, and `updated_by` where the application migration owner does not control the package schema. These references require application validation and runtime mapping.

No table in the canonical list may introduce a `profiles` table, a local permission table, a Variant table, a `product_price` table, a generic `uoms` table, `foundation_product_combo_items`, or Resource Instance Authorization storage.

### 3.2 Dependency graph

The canonical graph is:

```text
Shield/Settings package migrations
  -> provinces -> wards
  -> organizations -> contacts
  -> departments, positions -> employees -> employee_assignments
  -> lifecycles, workflow_states -> lifecycle_states -> lifecycle_transitions
  -> resources -> resource_actions -> auto_numbering_series -> auto_numbering_counters
  -> groups -> group_permissions
  -> policies -> policy_actions
  -> authorization_logs
  -> product_groups, product_uoms -> products
  -> product_uom_conversions, product_combo_items
  -> price_lists -> product_price_list_items
  -> import_jobs -> import_job_rows
```

The graph is a dependency view, not permission to reorder the DBD sections. `P1-DB-040` has no physical FK to employee tables and may be prepared in parallel after P1-DB-001, but `P1-DB-050` must wait for both the employee and workflow prerequisites required by the Implementation Plan.

## 4. Proposed physical migration batches

The names below are proposals only. They are deterministic CI4 names/classes and were **not created** by this task. The timestamp prefix follows the actual `Config\\Migrations::$timestampFormat = 'Y-m-d-His_'`; implementation should reserve the exact timestamp values before writing files and keep them monotonic.

| Task | Proposed filename | Proposed class | Purpose and tables | Depends on |
|---|---|---|---|---|
| P1-DB-010 | `2026-10-05-000001_CreateFoundationLocationTables.php` | `CreateFoundationLocationTables` | `provinces`, `wards`, including location FK/indexes | Shield/Settings package migrations |
| P1-DB-020 | `2026-10-05-000002_CreateOrganizationContactTables.php` | `CreateOrganizationContactTables` | `organizations`, `contacts`, including self/location FKs | P1-DB-010 |
| P1-DB-030 | `2026-10-05-000003_CreateEmployeeStructureTables.php` | `CreateEmployeeStructureTables` | `departments`, `positions`, `employees`, `employee_assignments` | P1-DB-020 |
| P1-DB-040 | `2026-10-05-000004_CreateWorkflowLifecycleTables.php` | `CreateWorkflowLifecycleTables` | `lifecycles`, `workflow_states`, `lifecycle_states`, `lifecycle_transitions` | P1-DB-001; independent of employee tables at schema level |
| P1-DB-050 | `2026-10-05-000005_CreateResourceAuthorizationNumberingTables.php` | `CreateResourceAuthorizationNumberingTables` | `resources`, `resource_actions`, `auto_numbering_series`, `auto_numbering_counters`, `groups`, `group_permissions`, `policies`, `policy_actions`, `authorization_logs` | P1-DB-030 and P1-DB-040 |
| P1-DB-060 | `2026-10-05-000006_CreateProductUomComboTables.php` | `CreateProductUomComboTables` | `product_groups`, `product_uoms`, `products`, `product_uom_conversions`, `product_combo_items` | P1-DB-050 |
| P1-DB-070 | `2026-10-05-000007_CreatePricingTables.php` | `CreatePricingTables` | `price_lists`, `product_price_list_items` | P1-DB-060 |
| P1-DB-080 | `2026-10-05-000008_CreateImportTables.php` | `CreateImportTables` | `import_jobs`, `import_job_rows` | P1-DB-060 |
| P1-DB-090 | `2026-10-05-000009_VerifyFreshAndForwardMigrations.php` | `VerifyFreshAndForwardMigrations` | verification task/evidence; preferably a test/evidence task rather than a production schema migration | P1-DB-010..080 and approved seeds |

The proposed P1-DB-090 class is shown only to make the physical plan explicit. It should not be created as a schema migration that changes production data; verification belongs in the harness/tests/evidence path unless a later approved implementation contract requires a migration marker.

Seeds remain separate from migration classes:

- `P1-SEED-001`: Resource + Action catalog after P1-DB-050;
- `P1-SEED-002`: workflow/lifecycle bootstrap after P1-DB-040;
- `P1-SEED-003`: privileged Authorization Group bootstrap after P1-SEED-001, with membership provisioned through approved CLI/deployment path and no runtime admin bypass;
- `P1-SEED-004`: GROUP root after P1-DB-020.

## 5. DBD section 23 harmonization disposition

The authorized reset left no application business baseline to harmonize. Therefore the disposition is based on the current physical state, not on guessed legacy columns.

| DBD v1.10 item | Disposition | Reason / later action |
|---|---|---|
| Package Shield/Settings schema boundary (cross-cutting; not a numbered DBD item) | **PACKAGE/EXISTING INTEGRATION RELEVANT** | Physical package tables already exist under the configured `dinktech_` prefix. Application migrations depend on them and must not recreate, rename, or add local identity/permission substitutes. |
| 1. Add organization `legal_name`, `tax_code`, `address`, `province_id`, `ward_id` | **NOT APPLICABLE - clean rebuild** | `organizations` is not present; define canonical columns directly in P1-DB-020. |
| 2. Add Employee P1 fields and widen name | **NOT APPLICABLE - clean rebuild** | `employees` is not present; define canonical table in P1-DB-030. |
| 3. Add `groups.code`, backfill aliases, unique constraint | **NOT APPLICABLE - clean rebuild** | `groups` is not present; create canonical table and verify Shield alias bridge in P1-DB-050/seed. |
| 4. Make `policies.state_id` nullable | **NOT APPLICABLE - clean rebuild** | `policies` is not present; create it nullable exactly as DBD specifies. |
| 5. Normalize legacy `ROLE` to `GROUP` | **NOT APPLICABLE - clean rebuild** | No legacy policy table/data remains in the authorized baseline. Do not invent a mapping. |
| 6. Create `lifecycle_transitions` | **STILL REQUIRED** | New canonical table is required by DBD and has no package equivalent; create in P1-DB-040. |
| 7. Create Product Model V2/UOM/Combo tables | **STILL REQUIRED** | No application Product baseline exists; create canonical tables in P1-DB-060, with no Variant/legacy generic table. |
| 8. Create Pricing tables | **STILL REQUIRED** | No application Pricing baseline exists; create exact UOM-aware tables in P1-DB-070. |
| 9. Create Auto Numbering tables and `resource.configure_numbering` action | **STILL REQUIRED** | No application Resource/numbering baseline exists; create tables in P1-DB-050 and seed action separately. |
| 10. Create Import tables and Product source-local key support | **STILL REQUIRED** | No application Import baseline exists; create canonical tables in P1-DB-080; source-local dependency behavior remains service-owned. |
| 11. Add `created_by`/`updated_by` audit fields to mutable master/config | **NOT APPLICABLE - clean rebuild** | Define audit columns in each canonical CREATE migration. Do not add an ALTER/backfill migration. |

Package/existing integration that remains relevant: Shield and Settings physical migrations are already applied in `erp`; application migrations must depend on, but not recreate, their tables. The Shield user/group/permission references remain logical where DBD prohibits a physical FK.

## 6. Migration safety rules for subsequent tasks

- Forward-only after application outside a disposable local/test database; never edit an applied migration.
- Fresh test migration starts from an empty approved disposable database and applies package migrations before application migrations.
- Rollback methods, when implemented, must be dependency-safe and are not a license to reset `erp`.
- Create FKs in deterministic dependency order; derive every index and unique key from DBD v1.10, not from legacy files.
- Do not create fake historical migration versions or manually insert migration ledger rows.
- Do not place business seed data inside schema migrations; use P1-SEED tasks and approved bootstrap paths.
- No production destructive reset, refresh, rollback, or migration-history rewrite.

## 7. Fresh and forward-only verification strategy

### Fresh path (disposable test database only)

1. Use the existing harness with its explicit `erp_test` isolation guard.
2. Rebuild the disposable `erp_test` only when the task explicitly authorizes a destructive rehearsal (`rebuild --yes`); never use this path for `erp`.
3. Run package migrations first, then application batches P1-DB-010 through P1-DB-080 in dependency order.
4. Run approved seeders in dependency order.
5. Assert every DBD PK, FK, unique key, index, nullability, DECIMAL definition, audit field, and forbidden-table boundary.
6. Run PHPUnit, database integrity tests, and the relevant migration evidence.

### Forward path during clean-baseline development

1. After P1-DB-010, preserve a disposable fixture at that completed migration batch.
2. Apply only the next forward batch, for example P1-DB-020, without dropping or refreshing the fixture.
3. Repeat through P1-DB-080 and the approved seed batches.
4. Record migration versions/batches and pre/post schema assertions for each step.
5. If a batch fails, stop, record the failed migration/database/pre-status/cause, and repair with a new forward migration or approved implementation change. Never edit an applied migration or insert a fake ledger row.

P1-DB-090 must provide evidence for both a full fresh install and sequential forward application through all approved batches. The current repository has no application schema yet, so that rehearsal is intentionally deferred until P1-DB-010 onward is implemented.

## 8. Gaps and blockers against the Implementation Plan

Current gaps are expected for a pre-Gate-1 clean rebuild:

- P1 business tables do not yet exist.
- P1 application migrations and seeds do not yet exist.
- No migration-level FK/unique/index assertions for the P1 schema exist yet.
- Fresh/forward P1 application rehearsal cannot run until P1-DB-010..080 and seed tasks exist.
- DBD says migration filenames are not locked; the proposed names therefore require reservation before implementation to avoid timestamp collisions.

No unresolved DBD authority conflict was found for this inventory. The only deliberate boundary is the clean-rebuild decision: no legacy harmonization can be performed without a physical legacy baseline, and historical migration `000021` must remain discarded. This is not a blocker for P1-DB-001, but it is a hard constraint for future migration implementation.

## 9. Recommended execution sequence

1. **P1-DB-010** - Foundation location migrations (`provinces`, `wards`).
2. **P1-DB-020** - Organization + Contact migrations.
3. **P1-DB-030** - Department/Position/Employee/Assignment migrations.
4. **P1-DB-040** - Workflow/Lifecycle migrations; this can be prepared after P1-DB-001 but must complete before P1-DB-050.
5. **P1-DB-050** - Resource/Auth/Auto Numbering migrations.
6. **P1-SEED-001**, then **P1-SEED-003** when the approved bootstrap path is ready; **P1-SEED-002** after workflow schema; **P1-SEED-004** after Organization schema.
7. **P1-DB-060** - Product/UOM/Combo migrations.
8. **P1-DB-070** and **P1-DB-080** can be prepared after P1-DB-060 (they are independent of each other).
9. **P1-DB-090** - Fresh + forward verification after all listed migrations and seeds.

Recommended next Task ID: **P1-DB-010**.

## 10. Validation evidence

Commands actually run from `D:\XAMPP\htdocs\erp\backend`:

| Command | Result | Evidence |
|---|---|---|
| `D:\XAMPP\php\php.exe vendor\\bin\\phpunit --no-coverage` | **PASS** | PHPUnit 10.5.65; 10 tests, 19 assertions; `OK`. |
| `D:\XAMPP\php\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` | **PASS** | Configuration `backend/phpstan.neon`; no errors. |
| `D:\XAMPP\php\php.exe tools\\test-db-harness.php inventory` | **PASS** | Configured DB `erp_test`, host `127.0.0.1:3306`, prefix `dinktech_`; isolation guard PASS; 1 migration ledger row visible; only harness tables are present. |
| `D:\XAMPP\php\php.exe spark migrate:status` | **PASS** | CodeIgniter v4.7.4; four applied package migrations in `default`; no application migration listed. |
| `D:\XAMPP\php\php.exe spark migrate:status -g tests` | **PASS** | Test group has package migrations pending; this confirms the test DB has not been migrated with the package set. |
| `D:\XAMPP\php\php.exe -r '... SELECT VERSION(), DATABASE() ...'` | **PASS** | `10.4.32-MariaDB`, database `erp`. |

No destructive command, migration execution, reset, refresh, rollback, or application file generation was run for this task.

## 11. Completion

Task: P1-DB-001 - Migration Inventory & Forward-Only Plan
Status: DONE

Validation:

- command: `D:\XAMPP\php\php.exe vendor\\bin\\phpunit --no-coverage` - PASS
- command: `D:\XAMPP\php\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` - PASS
- command: `D:\XAMPP\php\php.exe tools\\test-db-harness.php inventory` - PASS
- command: `D:\XAMPP\php\php.exe spark migrate:status` - PASS
- command: MariaDB version/database read-only query - PASS

Next task: **P1-DB-010**.
