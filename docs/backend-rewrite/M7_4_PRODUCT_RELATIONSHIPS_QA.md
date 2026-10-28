# M7.4 — Product Relationship Lookups (source implementation / QA)

Date: 2026-10-25. Base: operator-confirmed M7.3 source acceptance, 143/150 operations.

## Exact API-050 contracts (read-only, no mutation)

| Method | URI | Request | Response `data` |
| --- | --- | --- | --- |
| POST | `/api/v1/products/details/resolve` | `{ "product_ids": ["...", ...] }` max 100 | Full `Product[]`, authorized only, ordered by requested IDs |
| POST | `/api/v1/products/relations-preview` | `{ "product_ids": ["...", ...], "limit_per_product": 2 }` max 50, limit 1..5 | `{ product_id, total, products: Product[] }[]` for visible PARENT/COMBO |
| POST | `/api/v1/products/sales-uom-options/resolve` | `{ "product_ids": ["...", ...] }` max 50 | `{ product_id, options:[{id, code}] }[]` for visible Products |

The OpenAPI reference and generated frontend SDK are **unchanged**. The new
handlers are under Product/Lookup, never the Pricing module. POST is used for
batched read projections as in the frozen contract; they make no DB writes.

## PBAC and performance

- Shield session and `product.view` base permission are checked for all three handlers.
- Product Lifecycle State VIEW rights are compiled by `UnifiedAccessService`
  using the canonical `MASTER` PBAC engine and cached per HTTP request.
- Product owners, child details and full detail batches are selected by
  **bound** SQL `WHERE p.id IN (...) AND authorized-state-scope` before projection.
- `ProductRelationshipProjection::detail` provides the complete Product DTO
  including derived role, child full name, exact numeric strings, safe static
  image URL and private image metadata removal, without a full-catalog read.
- Relation counts and per-owner limit execute within one SQL window query.
  Child State PBAC filters the relation stream **before** `COUNT(*) OVER`
  and `ROW_NUMBER() OVER`, so unauthorized children do not inflate counts.
  The server loads at most 50 * 5 child detail projections; the service
  rechecks PBAC for every materialized Product and fails closed on mismatch.
- SALES UOMs additionally require independent `product_uom.view` rights.
  SQL limits eligible UOMs to exact `ACTIVE` and alternate conversions to exact
  `ACTIVE` with `SALES` or `BOTH`; COMBO only offers base UOM.
  Base UOM precedes conversions; duplicate UOMs are deduplicated per Product.
  A visible Product with no eligible UOM returns an empty `options` array.
- All SQL uses bound values. Physical table prefixes are validated backend
  config, never user input. IDs remain canonical BIGINT strings.
- No implicit cross-resource grants from Pricing are used. Missing and
  forbidden Product IDs are both silently omitted from result arrays.

## Source gates and regression

1. `php backend/tests/m74_relation_rules_pure.php`: limits, rejection, missing/hidden
   IDs, stable order, empty previews, and independent empty SALES options.
2. `php backend/tests/m74_relation_sql_pure.php`: bind counts, authorization
   before counts/limits, no catalog scan, role sources, independent UOM VIEW,
   table prefixes and SQL-injection rejection.
3. `php backend/tests/m74_relation_projection_fixture.php`: full DTO parity
   versus `ProductReadRules::describe`, image-url rules and malformed roles.
4. `php backend/tests/m74_relation_contract_pure.php`: frozen operations,
   handler existence, auth, contracts and no writes.
5. `php backend/tools/m74_accept.php`: exact 143 + 3 = 146 operations,
   no unknown or extra method, previous M7.3 acceptance pinned separately.
6. `python backend/tools/verify_routes.py` and `cd backend &&
   .\tools\test-unit.ps1 RUN`: all previous milestones retained.

## Runtime acceptance still required

These source/fixture tests cannot prove authenticated HTTP/MariaDB behavior.
On a DEV clone, verify (a) authorized and forbidden users across `ACTIVE`,
`INACTIVE`, and custom Lifecycle States, (b) missing vs hidden ID omission,
(c) parents/COMBO with 0/1/>5 children and restricted child States, where
`total` counts only visible children and `products` is bounded, (d) exact
`Product` DTO parity with Product GET for real rows, (e) UOM independence,
INACTIVE exclusion, base-first dedup, and COMBO no-conversion, (f) query
EXPLAIN for `products.parent_product_id`, `product_combo_items.combo_product_id`,
`product_uom_conversions.product_id`, (g) session CSRF, 400 malformed JSON,
401 unauthenticated, 403 missing base grant, and (h) independent baseline
HTTP parity against a separate snapshot clone.

No schema or data mutation is introduced in M7.4. Previous M6.3C concurrency
waiver remains NOT VERIFIED. Other M5–M7 runtime and integration gaps remain
OPEN. Production cutover is **BLOCKED**.
