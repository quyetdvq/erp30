# M7.1 — Product UOM Conversions: Implementation and QA

Date: 2026-10-25. Built on operator-installed M6.4A.1 (132/150 source routes). Exact API-050 OpenAPI v1.2.4 contract remains immutable.

## Frozen API scope

| HTTP | Path | Operation |
|---|---|---|
| GET | `/api/v1/products/{id}/uom-conversions` | `listProductUomConversions` |
| PUT | `/api/v1/products/{id}/uom-conversions` | `replaceProductUomConversions` |

M7.1 is **134/150** declared non-OPTIONS routes; 16 frozen operations remain deferred. No route renames, migrations, Frontend, Backend baseline, README, numbering or Avatar changes.

## Authorization and throughput

- **GET**: require Shield session and base `product.view` permission. Reuse Access-owned `viewableMasterLifecycleStatuses('product')`, which computes exact policy decisions across canonical MASTER Lifecycle States (base privileges, assignments, policy ALLOW/DENY/ABSOLUTE_DENY). SQL reads **only the Product ID** with an authorized-State bound `WHERE` clause; a persisted `canView()` cross-check fails closed. If product is not viewable, conversion rows are never queried. Alternate-UOM rows are fetched by the indexed `product_id` and sorted by `uom_id,id` to retain baseline order; there is no Product-catalog materialization.
- **PUT**: Shield session, default-off `DINKERP_M33B_WRITE_MODE`, `product.view` and `product.edit` on the **locked Product row**. The real per-row mutation decision is evaluated once, not for every UOM conversion. No client JSON field can authorize a Product or override persisted lifecycle/scope. An optional backward-compatible `evaluation_organization_id` hint can only match the authoritative GROUP root and never grants access.
- This GET is already indexed by Product primary key, so calculating a generic SQL policy JOIN for every conversion is unnecessary. The MASTER State SQL predicate is nonetheless applied **before querying conversions** as requested.

## Integrity boundaries

- Accept frozen body forms `[{...}]` or `{ "items": [{...}] }`, including an **explicit** empty list (clears all conversions). Missing JSON body and `{}` are rejected to prevent accidental bulk deletion. A hard cap of 250 items per request protects transactions. Input rejects client-controlled IDs, Product ownership, timestamps and extra keys; canonical BIGINT ids are strings, with no float conversions.
- `conversion_factor`: DECIMAL(18,6), strictly positive; `price`: nullable DECIMAL(18,2), nonnegative, required for SALES/BOTH; package mass/length/width/height: nullable DECIMAL(18,3), matching legacy precision. `usage_type`: PURCHASE/SALES/BOTH, `status`: ACTIVE/INACTIVE. Duplicate normalized `uom_id` is a conflict. New UOMs require ACTIVE status; retained existing conversions follow baseline merge semantics.
- Conversions only allowed on STANDARD SIMPLE/CHILD Products with active base UOM. Parent/COMBO Products, inactive base UOM, alternate=base and nonexistent UOMs fail before DML.
- PUT requires `products`, `product_uoms`, `product_uom_conversions` to be inspectable InnoDB tables **before allowing writes**. MyISAM/missing/unreadable transactional table metadata fails closed with a server error. No implicit migration or ALTER TABLE.
- Reuse Product Master and Product Image's exact MariaDB GET_LOCK key; inside one transaction lock the Product (`FOR UPDATE`), present conversions and referenced UOM rows. Validate the complete requested set, derive a pure DML plan, then delete omitted rows, update retained rows preserving stable IDs, create new rows. All DML and final SELECT execute within the transaction; SQL failures trigger rollback and CI4 transStatus reset.
- This avoids partial updates, but does **not** establish actual isolation/concurrency parity without a live MariaDB test. No external HTTP or DB connections were available in the patch authoring environment.

## Source/offline evidence (authoring environment)

- `php backend/tests/m71_conversion_rules_pure.php`: 49/49 PASS.
- `php backend/tests/m71_conversion_contract_pure.php`: 28/28 PASS.
- `php backend/tests/m71_conversion_plan_fixture.php`: 14/14 PASS (pure planning/rollback model, **not actual MariaDB rollback**).
- `php backend/tools/m34b_run.php --offline`: **116/116 suites PASS**.
- `php backend/tools/m34d_accept.php --offline`: 6/6 architecture gates PASS.
- `php backend/tools/m71_accept.php`: M7.1 SOURCE PASS, 134/150.
- `python backend/tools/verify_routes.py`: 134/150 exact routes, 16 deferred, no unknown.
- PHP app syntax: 288/288 PASS.

## Operator deployment: source only

```powershell
cd D:\XAMPP\htdocs\erp

git -c core.autocrlf=true apply --check .\ERP_M71_PRODUCT_UOM_CONVERSIONS_v1.4.0.patch
git -c core.autocrlf=true apply .\ERP_M71_PRODUCT_UOM_CONVERSIONS_v1.4.0.patch
python backend\tools\verify_routes.py

cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m71_conversion_rules_pure.php
php tests\m71_conversion_plan_fixture.php
php tools\m71_accept.php
```

Do **not** run the unrelated Frontend/Backend fingerprint verifiers waived by the operator. Do not enable production write mode. The previous M6.3C concurrency waiver remains WAIVED / NOT_VERIFIED.

## XAMPP acceptance still OPEN

1. Read-only: inspect the physical table engines using phpMyAdmin on the **intended** database before attempting PUT. Run:

   ```sql
   SELECT TABLE_NAME, ENGINE
   FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('products','product_uoms','product_uom_conversions');
   ```

   If `DBPrefix` is configured, use its physical table names. All three must be InnoDB for writes. Do not change engines solely to make QA pass.
2. Login through Frontend as an authorized `product.view` actor and request GET for a known visible Product. Verify `data` ordering, inactive retained UOM rows, and EN/VI error envelopes. Compare a denied actor and a hidden Lifecycle State Product: no hidden conversions, no existence leak via row data.
3. Only with an isolated disposable DEV Product, current backup, and explicit operator opt-in for mutation: enable existing DEV-only write gate in that environment; PUT a reversible UOM set and verify persisted `DECIMAL` bytes, created/updated auditing, stable IDs, omission deletes and GET parity. Test 422 missing price, 409 duplicate, 403 denied actor, 404 hidden Product, invalid/inactive UOM and source table unchanged after failed requests.
4. Independently test rollback by causing one write to fail within the transaction, then compare SQL snapshots before and after. Test concurrent PUT vs Product PATCH/SETSTATE to validate the cross-module advisory lock (not yet verified); return write gate to `off` afterward.
5. Compare responses, error codes, transaction effects and decimal rounding behavior against independent baseline v1.2.4 where available. Run `EXPLAIN` and record bounded `GET` latency under realistic data volumes if performance is an acceptance requirement.

**Release status:** SOURCE_ACCEPTED; HTTP/DB/rollback/concurrency/baseline parity NOT_TESTED; production_cutover BLOCKED.
