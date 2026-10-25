# M8.6A — Product Group/UOM SQL-first PBAC Read Migration

Status: **SOURCE ACCEPTED / AUTHENTICATED HTTP–MariaDB NOT_VERIFIED / PRODUCTION BLOCKED**.

## 1. Scope and baseline

Incremental on **M8.5**, preserving 150/150 frozen API operations. This slice migrates `GET /api/v1/product-groups`, `GET /api/v1/product-groups/{id}`, `GET /api/v1/product-uoms` and `GET /api/v1/product-uoms/{id}` from in-memory list authorization to SQL PBAC pushdown. It does **not** change POST/PATCH/SetState, OpenAPI, frontend, baseline, migrations, Identity/Avatar or stored business rows.

`product_group` and `product_uom` are reviewed `MASTER` resources with the GROUP-root authorization scope. `organization` is a fundamentally different `ORGANIZATION` domain and **is not migrated** in M8.6A. In particular, no code may treat an `ACTIVE` organization in company A as equivalent to an `ACTIVE` organization in company B.

## 2. GET execution path

1. `ProductCatalogController` authenticates via Shield, forwards `q`, `page`, `page_size`, `sort` and `filter` to `ProductCatalogService`.
2. The service validates the query using `ProductCatalogSql::normalize`, opens a local `REPEATABLE READ` transaction, and creates `UnifiedAccessService` from the **persisted actor snapshot inside that transaction**. Reads must never silently borrow a writer transaction.
3. For lists, a base `resource.view` permission is required even if the table is empty. `ProductCatalogScopeProvider` calls `viewableMasterLifecycleStatuses(resource)` and `AuthorizationSqlScopeCompiler` only allows reviewed resource/domain pairs.
4. `AuthorizedSqlScope::predicate('p')` creates an allow-only canonical State predicate using value binds. Empty authorized State set means `WHERE 1 = 0`. Policy precedence (ALLOW, DENY, ABSOLUTE_DENY), GROUP ancestry, employee/assignment and active Resource gates are resolved by the **existing canonical PBAC engine**, not recreated in SQL JOINs.
5. `COUNT(*)` runs with the same scope and query filters. The paged query uses the identical `WHERE`, a whitelisted `ORDER BY`, and `LIMIT ? OFFSET ?`. Only selected page records reach PHP.
6. Each returned row is rechecked by canonical `canView(resource, persistedRow)`; any SQL/PBAC disagreement raises an error for the *entire response*, never post-filters a page or gives a misleading `total`.
7. Detail GET uses `WHERE scope AND id = ? LIMIT 1`; absent ID returns the previous domain-specific NOT_FOUND error, whereas an existing but hidden ID remains 404 without protected fields. Both paths retain the exact original row shape.
8. The service commits the read snapshot only after authorization is complete; errors rollback and reset transaction state. Mutation service methods still use their existing lock/transaction, target and PBAC gates.

## 3. Security and behavioral boundaries

- **Query whitelist**: only `q`, `page`, `page_size`, `sort`, `filter`; max 200 rows/page; safe table aliases/prefix. Group accepts search `code,name`, filter `code,name,parent_id,status`, sort `id,code,name,parent_id,status`. UOM accepts search `code,name`, filter `code,name,status`, sort `id,code,name,status`.
- **Binary value comparison**: filter `NULL` is represented by the string `''` for `parent_id` (actual HTTP `filter[parent_id]=`), not PHP null input; malformed scalars/arrays rejected. `LIKE` escapes `!`, `%`, `_`. SQL identifiers never come from untrusted input; parameters are bound.
- **Sort differences require live parity**: MariaDB `LOWER(... ) COLLATE utf8mb4_bin` can differ from PHP `mb_strtolower`+`strcmp`, especially Unicode/collation, numeric-looking *text codes* and NULLs. Do not assume exact sorted ID parity without MariaDB fixtures.
- **No cross-company shortcut**: Organization, employee assignment, Pricing, Contact and Import Job domains are **not** implicitly authorized by State-only MASTER scope.
- **Transaction ownership**: Catalog GET refuses an unexpected transaction; Catalog mutations never call `show()` internally and retain their original `target()` + `visible()` checks, avoiding false nested-transaction commits.
- **`allowed_actions`**: Product Group/UOM existing row contracts do not contain this field. Product Master remains M8.5: `allowed_actions` is generated for page rows only, cached by State under MASTER; multi-company resources must not use a State-only cache.

## 4. Source gates (no HTTP/database writes)

```powershell
cd D:\XAMPP\htdocs\erp
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m86_catalog_sql_pure.php
php tests\m86_catalog_scope_pure.php
php tools\m86_accept.php
php tools\m85_scope_audit.php
```

`m86_catalog_sql_pure.php`: params, search/wildcard bind, sort/filters, row limits, `COUNT` and page using one PBAC predicate, zero grant, input rejection, prefix and no cross-resource scope.

`m86_catalog_scope_pure.php`: MASTER-only review, canonical policy entrypoint, provider/resource/domain mismatch and anti-injection behavior, old mutation guards intact.

`M86SourceAcceptance`: source-only footprint including 150 operations, no in-memory full-catalog page, transaction ownership, canonical checks and two reviewed Catalog resource scopes.

Historical M6.1/M6.2A.1/M6.4A.1 source assertions were updated to recognize **the reviewed SQL replacement**, not weaken the authorization gates. `m85_scope_audit.php` now marks Product Catalog as `SQL_VIEW_PUSHDOWN_M86A` while continuing to list other resources as backlog.

## 5. Required DEV MariaDB acceptance before rollout

1. Verify schema tables and engines (`product_groups`, `product_uoms`, `organizations` and authorization tables); require InnoDB for consistent snapshot semantics. No automatic ALTER.
2. For representative actors: VIEW only ACTIVE, VIEW DRAFT/ACTIVE, no base VIEW, explicit DENY, ABSOLUTE_DENY, disabled employee, out-of-assignment scope. Compare exact response IDs, `total`, page, sort/filter, missing/hidden GET and status against canonical PBAC decisions on persisted rows.
3. Negative query tests: injected sort/field, invalid page, multi-filter, `filter[parent_id]=`, nonexistent ID, hidden ID, 200/201 `page_size`, page beyond last.
4. Verify Vietnamese diacritics and mixed numeric/string `code` sorts, NULL `parent_id`, ties and deterministic ID tie-breaker. If parity differs, treat as a **compatibility change** requiring a reviewed fix, never a silent PASS.
5. Inspect `EXPLAIN` for `COUNT` and `ORDER BY id DESC LIMIT 15` at realistic cardinalities; measure elapsed time, rows examined and peak PHP memory vs old source under identical DB and actor fixture.
6. Verify Catalog Create/Update/SetState behavior and transaction rollback are unchanged. These source fixtures do **not** prove database transaction behavior.

Without the above: **source-only PASS, live read authorization/DB parity NOT_VERIFIED, production BLOCKED**.

## 6. Next migration: M8.6B Organization and People

Organization is authorized by the **target organization's persisted ID and ancestor path**, not by group-root State. `organization` list and `tree` need an `ORGANIZATION` provider with `id` as scope key and canonical `(organization_id,State)` decisions, retaining ancestor redaction for hidden parents. Employee and Department/Position need reviewed resource-specific domains; Employee Assignment uses organization_id plus temporal filters. Count and pagination must be scoped before they are visible to users. Contacts use polymorphic owner scope and are deferred until a safe owner union can be proven. PriceList provider remains opt-in until live multi-company parity.
