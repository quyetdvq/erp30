# M6.4A.1 — SQL PBAC Predicate Pushdown (Product lookups)

Date: 2026-10-25. No API operation added; route footprint remains **132/150**.

## Problem and decision

M6.4A initially selected every row from `products`, projected the entire
catalog with `ProductReadRules::describe()` and applied `canView()` per record
before PHP search, count and pagination. The 100-ID Product batch resolver
also loaded all Products. As catalog size grows this creates high SQL-to-PHP
transfer and materialization costs, and up to N expensive authorization calls.

**M6.4A.1 moves PBAC filtering into SQL WHERE before SQL COUNT and LIMIT/OFFSET.**
A naive `JOIN policies ... WHERE effect='ALLOW'` is **not valid**: it would
change `DENY`/`ABSOLUTE_DENY`, actor/group/assignment lanes, all-State
policies and resource-State semantics.

Frozen baseline `ResourceActionSeeder.php` assigns **MASTER** domain to both
`product` and `product_uom`. For MASTER resources the authoritative evaluation
organization is always the GROUP root; actor, scoped assignment facts and
policies are request-scoped. The only varying resource attribute in VIEW PBAC
is its **persisted Lifecycle State**. Therefore:

1. Load the Shield/ERP actor snapshot once through `UnifiedAccessService`.
2. Fail closed unless the resource is explicitly `product` or `product_uom`,
   its resource action has domain `MASTER`, and it has a canonical Lifecycle.
3. Evaluate `AccessDecisionCore` and `RuntimePolicyReader::applicable` once
   **for each canonical State** through the *same* shared VIEW decision helper
   used by `decidePersisted` (including ABSOLUTE_DENY and active groups).
4. Compile the set of permitted State codes into bound SQL parameters:
   `WHERE UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?,...)`.
   Empty allowed set means no rows; do not fall back to unfiltered reads.
5. Apply role/purpose, `ACTIVE`, text search and the PBAC State predicate
   **inside MariaDB before COUNT, ORDER BY id and LIMIT/OFFSET**.
6. Join `products` to parent on indexed `parent_product_id`; derive root
   `PARENT` vs `SIMPLE` with indexed `EXISTS`. No full-catalog hydration.
   Project bounded result rows with `ProductLookupProjection` and cross-check
   each result using persisted-row `canView()` (<=50/page or <=100/batch).
7. Batch resolve reads only its requested IDs plus permitted Statuses; the
   PHP projection restores the first-requested-ID order and omits hidden IDs.

## Changed files

- `app/Modules/Access/UnifiedAccessService.php`: `viewableMasterLifecycleStatuses`
  and the shared `viewDecisionForState` method. No mutation-PBAC changes.
- `app/Modules/Product/Lookup/ProductLookupSql.php`: pure bound SQL plans.
- `app/Modules/Product/Lookup/ProductLookupProjection.php`: bounded projection
  and per-row structure validation (SIMPLE/PARENT/CHILD/COMBO).
- `app/Modules/Product/Lookup/ProductLookupService.php`: SQL count/page/batch,
  no full Product scan; bounded `canView` double-check.
- Source acceptance, fixture and QA documentation updated.

No modifications to Frontend, backend baseline, OpenAPI, schemas, routes,
Avatar, workflow mutation gates or data. No new SQL indexes are required:
existing `products.id`, `products.parent_product_id` and `product_uoms.id`
are used by batch access/parent join and EXISTS; CI4 `DBPrefix` is respected in all raw SQL. Search `LIKE '%q%'`, and
`UPPER(TRIM(status))` are not generally index-sargable; benchmark on a real
sized MariaDB dataset before asserting latency improvements.

## Source verification

```powershell
cd D:\XAMPP\htdocs\erp
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m64a1_sql_scope_pure.php
php tests\m64a_contract_pure.php
php tools\m64a_accept.php
```

Expected source gates: **113/113** offline suites, M3.4D **6/6**, and the
same **132/150** API operations. Fixture verifies SQL bind placement, no
SQL injection through `q`/State, literal `%`, `_`, `!`, safe database table-prefix handling, restrictive PBAC
States, no-grant fail closed, numeric ID sorting, correct roles and labels
against `ProductReadRules::describe()`, and bounded row reauthorization.

## Required real MariaDB/HTTP differential acceptance (NOT TESTED)

Use approved DEV actors with different permissions on `product` and
`product_uom`. Record actual SQL `EXPLAIN`, response body, `meta.total` and
HTTP timing for:

1. Active Product selector (all three purposes) across multiple pages, including
   a hidden Product and a child whose parent is not visible. No hidden record
   may affect total or pagination.
2. Policy `ALLOW`, `DENY`, `ABSOLUTE_DENY`, actor/group/department/position
   lanes, All-State vs specific-State policies, and an actor without base VIEW.
   Verify against existing persisted-row `canView` decisions in the same
   authorization snapshot; do not alter real privileges just for QA.
3. Resolve up to 100 ID values: visible, hidden, missing, inactive, duplicate,
   very large unsigned IDs, and batch order. UOM visibility must be independent
   of Product VIEW.
4. Text search on Product code, SKU, root name, child name, full child name,
   diacritics/Unicode, and literal `%`, `_`, `!`; compare with pre-patch contract
   to identify locale/collation differences. MariaDB and PHP Unicode casing
   are not guaranteed identical for every locale; this remains a parity gate.
5. EXPLAIN and warm/cold latency on representative catalog sizes (not a claim
   of numeric speedup based solely on offline tests). Investigate any
   full-scan hotspot for broad wildcard searches or function-wrapped status.
6. EN/VI errors; unauthenticated 401; `POST` CSRF; compare payload shape and
   `total`/page ordering against frozen baseline.

**Acceptance status:** Source PASS only; live HTTP/SQL/PBAC/performance
NOT_TESTED. Production cutover remains BLOCKED. M6.3C live two-writer CAS is
still WAIVED/NOT_VERIFIED, never promoted to PASS.
