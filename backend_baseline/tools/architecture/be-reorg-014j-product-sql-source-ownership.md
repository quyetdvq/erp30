# BE-REORG-014J — Product SQL list implementation ownership

**Authority:** `docs/DinkERP_Backend_System_Architecture_Standard_v1.1.1.md` §5.2/§17; `docs/DinkERP_Backend_Architecture_Blueprint_Lean_v1.2.2.md` §4/§9/§10. Both documents are proposed architectural guidance, **not** approved API/DB contracts.

**Status:** `SOURCE_REFACTORED / REAL_DB_RUNTIME_NOT_RUN` in the distributed source package. This change is **internal-only**; no new Product public inter-module contract is approved.

## Finding and boundary decision

`Infrastructure/Api/SqlProductListSource.php` depended directly on `Modules/Product/Product/{ProductRepository,ProductRoleService}`, implementing Product-specific hierarchy validation, authorized SQL count/page and relation hydration inside generic API Infrastructure. The generic API package already supplies `AuthorizedListSource`, `CommonListQuery` and `AuthorizedArrayListSource` as reusable contracts/algorithms; it should not own Product rules.

Move the **unchanged SQL list implementation** to `Modules/Product/Product/Application/SqlProductListSource.php`. `ProductListQueryService` is its single production consumer and resolves it locally. `tests/unit/P1Api050ProductSqlPaginationTest.php` now targets the owner class. `014C` fake collaborator mirrors its new namespace. Existing consumers were inventoried with source search; none outside Product and the named tests were found. Therefore **no legacy class alias** is introduced for this internal-only implementation. The old class name is intentionally not a compatibility API.

## Invariants and transaction/security effects

| Concern | Decision |
|---|---|
| Business/table owner | Product; read-only queries on `products` and existing Product relationships |
| Public HTTP entrypoint | `GET /api/v1/products` through unchanged API-050 facade / `ProductListQueryService` |
| Resource/action | `product.view`; no new bypass, PBAC decision or actor-context construction |
| SQL | Method bodies for `countAuthorized`, `fetchAuthorized`, hierarchy validation, projected keys and hydration **not changed**; only PHP namespace/imports changed |
| Visibility and totals | Preserved SQL State buckets before COUNT/page; noncanonical/filter/sort fallback remains |
| Read snapshot | Existing owner query service and Product repository retain their exact snapshot ownership; no new transaction or connection |
| API/schema/precision | No route, OpenAPI, response, DB migration/index, 404/403 or machine error changes |
| Compatibility | No alias because only source-observed Product internal + owned unit test consumers; verify external plugins/references before publishing if applicable |
| Rollback | Revert 014J patch; no database rollback |

## Verification and outstanding runtime evidence

- `php backend/tools/test-be-reorg-014j-product-sql-owner.php`: seven vendor-free checks running the **actual moved class** against in-memory Product collaborators: source ownership, authorized COUNT/page, projection hydration, empty State buckets, whole-catalog role check and fail-closed missing-page race. **Not a DB/Shield substitute.**
- `php backend/tools/test-be-reorg-014c-product-list.php`: original 14 fake orchestration parity scenarios, fake source retargeted to Product namespace.
- `php backend/tools/architecture-scan.php`: generic Infrastructure → Product references decrease by two; snapshot `observed-v1.2.2.json` stays historical and is **not regenerated**.
- Also run `test-architecture-conformance.php`, 014D–014I, KIR-001 source gate, all PHP lint. Real PHPUnit `P1Api050ProductSqlPaginationTest` and Product List API/PBAC snapshot/SQL plans remain **NOT_RUN** in package without `vendor`/MariaDB; run operator `UNIT-STANDARD` and `NO-DROP` on backed-up `erp_test` before merge. No latency/EXPLAIN claim is made for a class relocation.

## Remaining architecture work

`Infrastructure/Api/SqlPriceListListSource` still holds Pricing-owned authorized SQL logic and should be considered separately, **not** moved in this PR. Existing Authorization read dependencies and potential direct SQL hotspots remain separate review items. Mark root Product conformance `NOT_VERIFIED` until owner/contract approval and runtime gates; a clean static dependency graph alone is insufficient.

**Follow-up:** BE-REORG-014K subsequently moved the Pricing source in a separate patch; the statement above records the unfinished work *at the end of 014J*, not the post-014K tree. See `be-reorg-014k-price-list-sql-source-ownership.md`.
