# M7.3 — Pricing / Price Lists (source QA; runtime gates remain open)

Date: 2026-10-25. Base: accepted M7.2 source (136/150 frozen API operations).
This increment implements exactly seven API-050 operations, yielding **143/150**.

## Owner and contract

Owner: `App\Modules\Pricing\PriceList` (not Product or Access).
No schema migration, Frontend, baseline, public media, or OpenAPI change.

| Method | Path | Handler |
|---|---|---|
| GET | `/api/v1/price-lists` | `listPriceLists` |
| POST | `/api/v1/price-lists` | `createPriceList` |
| GET | `/api/v1/price-lists/{id}` | `getPriceList` |
| PATCH | `/api/v1/price-lists/{id}` | `updatePriceList` |
| POST | `/api/v1/price-lists/{id}/actions/set-state` | `setPriceListState` |
| GET | `/api/v1/price-lists/{id}/items` | `listPriceListItems` |
| PUT | `/api/v1/price-lists/{id}/items` | `replacePriceListItems` |

The seven operations are pinned against
`docs/backend-rewrite/openapi-operations-v1.2.4.json` and the previous
M7.2/M7.1/... source acceptance gates.

## Authorization and SQL scope

`price_list` is an **ORGANIZATION** resource. It cannot use M6.4A.1's
`MASTER`-resource State-only SQL scope. The Pricing owner compiles the
existing `(organization_id, exact Lifecycle State)` tuples present in the
catalog, using the existing `UnifiedAccessService` for a representative
persisted row from each tuple. Only tuples approved by the full PBAC
engine are passed as SQL bind values:

`(p.organization_id = ? AND BINARY p.status = BINARY ?)`

This predicate is included **before** `COUNT(*)`, sort and
`LIMIT ? OFFSET ?`; the returned rows are rechecked with `canView()`.
A transaction-level REPEATABLE READ snapshot keeps tuple sampling,
COUNT and page consistent. The compilation fails closed if there are more
than 512 distinct organization/State tuples or any inconsistent persisted
bucket. This is a bounded resource-specific compiler, **not** a general
translation from ALLOW/DENY policies to SQL. It does not ignore
ABSOLUTE_DENY, State, or employee assignment scope.

The tuple discovery query is `GROUP BY organization_id, HEX(status)`.
For large or highly fragmented Pricing catalogs it can be a significant
cost: **no measured runtime performance improvement is claimed**.
Inspect SQL `EXPLAIN` and rows examined on a representative DEV dataset.

## Mutation correctness

POST, PATCH, State transition and PUT item replacement are protected by
Shield authentication and default `DINKERP_M33B_WRITE_MODE=off`.
The service uses persisted row authorization; client-supplied scope and
status are never treated as authorization facts. CREATE derives its State
from the server-defined Lifecycle initial State. PATCH cannot set status;
SETSTATE is checked against the canonical transition and write PBAC.
Moving a Price List between organizations requires authorization in both
the old and destination scope.

Before any transaction with writes, the service verifies `InnoDB` for
`price_lists`, `product_price_list_items`, `organizations`, `products`,
`product_uoms` and `product_uom_conversions`. It acquires an advisory
Pricing mutex, uses `FOR UPDATE` for the Price List and relevant reference
rows, and rolls back on failure. No auto-migration/engine conversion is
performed. Product Price List Item replacement preserves existing IDs,
updates only changed prices, deletes absent items, and adds new items.
Money is checked against `DECIMAL(18,2)` with **string exactness**, never
`float`. Duplicate `(product_id,uom_id)` pairs, unsupported UOM, inactive
Product/UOM and invalid Product roles are rejected. Missing body/null/`{}`
never deletes an item set: deletion requires an explicit `[]` or
`{"items":[]}`.

## Source verification (no vendor or live database required)

From `D:\XAMPP\htdocs\erp`:

```powershell
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m73_price_rules_pure.php
php tests\m73_price_sql_pure.php
php tests\m73_price_item_plan_fixture.php
php tests\m73_price_contract_pure.php
php tools\m73_accept.php
```

Author source results: **123/123 offline suites PASS**; **6/6 M3.4D
architecture gates PASS**; Pricing rules **75/75**, SQL/PBAC scope
**23/23**, item plan fixture **14/14**, contract **28/28**, route inventory
**143/150** and application PHP syntax **303/303**. No HTTP/MariaDB probes
or Pricing benchmark were run in the source environment.

## Open DEV acceptance (must not be silently marked PASS)

1. Authenticate two distinct actors with different Pricing organization and
   Lifecycle scopes; verify `total`, sorted pages and list items do **not**
   reveal forbidden Price Lists (including DENY / ABSOLUTE_DENY).
2. Check timezone normalization, code duplicate, invalid interval and
   State transitions against actual bound Lifecycle configuration.
3. On a disposable MariaDB clone only, verify atomic PUT item-set replace,
   unchanged ID retention, duplicate pair rejection, mid-transaction failure
   rollback and zero unintended side effects.
4. Verify two writer contention and isolation; inspect actual InnoDB engine
   from `information_schema` (earlier M6.3C attestation remains **WAIVED / NOT
   VERIFIED**, not PASS).
5. Compare baseline API envelopes and error codes; run `EXPLAIN`/profiling
   for PBAC bucket query, scoped COUNT, paginated list and lookup queries.
6. Verify English/Vietnamese runtime messages and CSRF/session behavior.

**Source acceptance does not authorize production writes or cutover.**
