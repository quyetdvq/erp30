# M6.4A - Product Selector & UOM Label Resolution (2026-10-25)

## Scope and evidence

M6.4A adds **exactly three** of the existing immutable API-050 operations,
increasing the explicit registered backend to **132/150** without changing the
OpenAPI schema, frontend, baseline backend, database schema or product mutation
logic. See `M6_3C_CONCURRENCY_WAIVER_2026-10-25.md` for the previous
concurrency test waiver: a UI smoke test is not a two-writer acceptance result.

| Verb | URI | Frozen operationId |
|---|---|---|
| GET | `/api/v1/products/options` | `searchProductOptions` |
| POST | `/api/v1/products/options/resolve` | `resolveProductOptions` |
| POST | `/api/v1/product-uoms/labels/resolve` | `resolveProductUomLabels` |

The new owner is `App/Modules/Product/Lookup`: Controller, Service, pure Rules.
Endpoints are read-only and **not** controlled by the mutation feature switch.

## Behavioral invariants

- The actor must be an authenticated Shield user with persisted `product.view`
  (or `product_uom.view` for UOM labels) plus per-record PBAC and State/scope
  permissions; no Product VIEW grant is inherited from Pricing permissions.
- Search filters **PBAC-visible Product rows first**, then ACTIVE status, role
  and query text, and only then counts/pages. No invisible Product may influence
  the returned `total`.
- Purpose `parent_product` accepts ACTIVE `PARENT` and `SIMPLE`. The other two
  frozen purposes accept ACTIVE `SIMPLE`, `CHILD` and `COMBO`, matching the
  source baseline. `selectable` remains the canonical persisted-role projection
  and a PARENT does not silently become sale-selectable.
- Query bounds: `page` 1..100000; `page_size` 1..50 (default 20), search text
  max 100 Unicode codepoints. Numeric ID sort is ascending; a stable total is
  calculated before slicing.
- Resolve requests accept at most 100 canonical unsigned BIGINT IDs. Duplicate
  IDs are collapsed; response order follows first requested occurrence.
  Missing/invisible IDs are omitted, not distinguished. Inactive viewable
  Products and Product UOMs may resolve historical labels, but not become
  newly selectable.
- Product output is precisely `id`, `product_code`, `full_name`, `role`,
  `status`, `base_uom_id`, `selectable`. It never includes image filenames,
  internal fields or extra metadata.
- M6.4A.1 optimization: `UnifiedAccessService` evaluates `MASTER` VIEW PBAC
  exactly once for each canonical Lifecycle State; SQL restricts results to
  those State codes **before** `COUNT`, `LIMIT` and offset. Only bounded result
  rows (<=50/page or <=100/batch) are cross-checked by `canView()`.
- `ProductLookupProjection` uses indexed parent JOIN and indexed child EXISTS
  to calculate SIMPLE/PARENT/CHILD/COMBO without loading the entire catalog.
  SQL search uses bound escaped LIKE text and binary-collated lowercase match.
- Runtime SQL/HTTP parity, Unicode collation differences and query plans on
  actual XAMPP MariaDB remain open until separately verified.

## Source QA commands

```powershell
cd D:\XAMPP\htdocs\erp
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m64a_lookup_rules_pure.php
php tests\m64a_contract_pure.php
php tools\m64a_accept.php
```

Expected source results: **113/113 offline suites**, M3.4D 6/6 static gates,
M6.4A route gate 132/150, and 18 deliberately deferred operations. These
results are **source-only**; they do not prove authenticated HTTP/MariaDB,
locale parity, record-PBAC on real actors, or external baseline differential.

## Next DEV runtime checks (read-only)

Using two existing authorized/limited DEV actors and without changing records:

1. GET options for all three purposes and an invalid purpose; compare returned
   `data` and `meta.total` against each actor's visible products, across pages.
2. POST Product labels with visible, hidden, missing and INACTIVE Product IDs,
   plus invalid/oversized batches (expect 400); confirm hidden IDs are omitted.
3. POST UOM labels with visible, hidden, missing and INACTIVE UOM IDs;
   confirm order and independently enforced `product_uom.view`.
4. Verify 401 unauthenticated; EN/VI response/error parity and session/CSRF
   on POST; compare against frozen v1.2.4 semantics.
5. Record HTTP status, request ID and reproducible server-owned DEV report.

**Status:** `SOURCE_PASS / DEV_HTTP_NOT_TESTED`. **Production cutover:** BLOCKED.
Next milestone: M7.1 Product UOM Conversions (2 new API operations).
