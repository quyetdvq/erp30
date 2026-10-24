# BE-PERF-015B — Product List sorting and projected-key cost

**Baseline:** BE-PBAC-016A on BE-REORG-014N. **Status:** SOURCE_VERIFIED / PHPUNIT_MARIADB_HTTP_FRONTEND_RUNTIME_PENDING.

## Actual limitation

`GET /api/v1/products` uses `ProductListQueryService`, authorized State buckets, COUNT/page in one REPEATABLE READ snapshot, row rechecks and action hints only after the authorized page. Its PHP projection is intentional for Unicode/case-folded/numeric-aware comparison. The frontend, however, previously forced `sort=product_code` even when the backend default was already indexed `id ASC`; Product UI supplied no sort choices. The SQL projected-key query transferred eight columns for every candidate even when sorting required only `id` and `code`. An ordinary `ORDER BY code` is **not** a correct replacement for the established PHP comparator (e.g. numeric-looking codes, accent/case differences).

## Decision and scope

- **API default** is persisted `products.id ASC` (exposed as public `sort=product_id`). Historic `sort=id` remains valid; `product_id` and `-product_id` map to `id` and `-id` **before** canonical parsing. Duplicate aliases/unknown or injection-shaped terms still fail validation. Shared list adapters and other resource sorting defaults are unchanged.
- **Product UI** starts and resets to `product_id`; one existing `ListQueryControls` selector offers both directions for Product ID, displayed **full name**, Product default price, Product code, SKU, type and status. Multi-term API sorts (e.g. `sku,-product_code`) and legacy `product_name` remain supported even though the UI presents the common choices.
- **SQL-fast path** for ID or default `products.price` DECIMAL, only without `q` and with the standard `id ASC` price tie-break. Authorized State/filters apply before `COUNT` and `LIMIT/OFFSET`; no extra indexes/migrations. `price` is **Product's default price**, *not* a computed price-list price.
- **Projected path** for Product code/SKU/type/status/raw name/full name, plus search or nonstandard sort combinations. `ProductRepository::listProjectedKeys` now selects **only the fields required by the validated search/sort**, e.g. `id,code` for `sort=product_code` and no search; the query retains its prior `code ASC,id ASC` deterministic initial order. It still projects all matching keys and uses the shared PHP ordering, so this is bandwidth/memory reduction, **not O(1) SQL paging** for arbitrary codes.
- **`sort=full_name`** sorts the same `trim(parent.product_name) . ' - ' . trim(child.product_child_name)` shown by the UI. A bounded, ID-batched parent-name lookup is performed **only during page sorting**, not COUNT, with the same snapshot; missing parents/names fail closed. No N+1 queries and no change to `ProductRoleService::describePage` or API response structure.
- **Decimal comparison** on the projected/fallback path is exact sign/integer/fraction comparison without float coercion or BCMath dependency; NULL precedes a value in ASC. The shared in-memory list accepts one *optional comparator callback*; all other modules retain its previous comparator. Invalid DECIMAL content fails closed. SQL DECIMAL ordering remains authoritative on the fast path.
- **Security/compatibility:** no PBAC/Organization scope changes, SQL WHERE/collation safety remains, no mutation or transaction changes. Existing Product `allowed_actions` hints stay after VIEW filtering and page selection. `page`, `page_size`, `total` and stable `id` tie-breakers remain intact. Sorting `price` does not retrieve Pricing module prices and does not authorize additional records.

## Verification

```powershell
php backend/tools/test-be-perf-015b-product-sort.php
php backend/tools/test-be-reorg-014c-product-list.php
php backend/tools/test-be-reorg-014j-product-sql-owner.php
php backend/tools/test-be-pbac-016a-product-actions.php
php backend/tools/architecture-scan.php
```

On a local MariaDB/Shield environment with backed-up `erp_test`, run the guarded UNIT-STANDARD, KIR-001 and NO-DROP lanes, and frontend `pnpm run type-check`, `pnpm exec vitest run src/features/catalog/__tests__/productSortOptions.spec.ts`, `pnpm run build-only`. Test authenticated API pages for each sort (ASC/DESC; empty/null and negative prices; child full names; DENY View states), compare cross-page IDs and totals, and use `EXPLAIN` + per-stage timings and memory for realistic catalog sizes before claiming production p95 improvements. For numeric-looking codes, PHP semantics may differ from the database collation. Dedicated HTTP/DB parity tests for the new price/full-name paths remain a pre-release check.

## Risks and rollback

A computed `full_name` key can require a batch read for many distinct parents; the operation is bound to the list snapshot and must be profiled with large child-heavy catalogs. SQL price sort may use a filesort depending on filter cardinality; no index is added without `EXPLAIN`. Apply this patch incrementally after 016A; revert it to restore prior sort options. No schema or data migration is involved. Historical frontend v2 baseline hashes must not be rewritten to hide this feature change.
