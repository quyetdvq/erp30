# M7.2 — COMBO Items GET / Atomic Replacement: Implementation & QA

Date: 2026-10-25. Built on operator-accepted M7.1 source with 134/150 operations. This increment implements **exactly two** API-050 operations (136/150), without modifying the frozen OpenAPI v1.2.4 contract, schema, Frontend, backend_baseline or root README.

## Frozen contract

| HTTP | Path | operationId | Auth |
|---|---|---|---|
| GET | `/api/v1/products/{id}/combo-items` | `listComboItems` | `product.view` on persisted owner |
| PUT | `/api/v1/products/{id}/combo-items` | `replaceComboItems` | `product.edit` on locked persisted owner + view |

PUT accepts either a bare array of `{component_product_id, quantity}` or an object containing an explicit `items` list. `[]` and `{ "items": [] }` intentionally delete the complete set. Missing/`null`/`{}` payloads must **never** be treated as a clear-all operation. The optional evaluation-organization hint is validated against the actual single GROUP root and does not grant access. Extra fields, client-controlled row IDs, audit columns, duplicate normalized Product IDs and non-list arrays fail closed.

## Authorization and SQL performance

- Authenticate Shield actor and require base `product.view` on GET. Resolve the canonical allowed Product MASTER Lifecycle State set through **UnifiedAccessService**. The Product owner is fetched by primary key with a **bound SQL `WHERE` for PBAC States**, then rechecked by `canView()`. Only afterward are direct edges queried using indexed `combo_product_id`; no unbounded Product master read.
- PUT reads the authoritative Product row `FOR UPDATE` under the **same Product Master advisory mutex** used by Product Master and UOM conversions. `canView()` and the `product.edit` mutation decision are evaluated against that persisted locked row. Client input cannot authorize a row or invent a Lifecycle State.
- Component IDs are looked up in one bounded `WHERE IN (...)` Product query; role/variant and active base-UOM requirements are evaluated from persisted rows. New components must be ACTIVE and selectable (SIMPLE/CHILD/COMBO), while previously retained components follow baseline allowance for historic inactive references **subject to current VIEW-PBAC**. Parent Products, nonexistent components and missing base UOM are rejected.
- Nested graphs are read via batched indexed edge queries **starting only from requested COMBO children**. The graph DFS is iterative; circular references (`COMBO_SELF_REFERENCE`, `COMBO_CYCLE`) are rejected. Limits 5,000 nodes / 10,000 edges fail closed, rather than allowing unbounded loops. No full Product-catalog scan or PHP float-based BIGINT comparison.
- This is SQL/PBAC-first for the owner, but WRITE still requires the per-record locked authorization decision; State-only SQL is not sufficient for a privileged mutation.

## Data integrity and transaction design

- Quantity is validated as `DECIMAL(18,6)` strictly greater than zero using strings (12 maximum integral digits / 6 fractional). Float, exponent, NaN, Infinity and overprecision input are rejected. Canonical BIGINT IDs are strings.
- The service first verifies the physical `products`, `product_uoms`, `product_combo_items` tables are InnoDB via read-only `information_schema` queries. Missing, uninspectable or nontransactional engines cause `COMBO_CONNECTION_INVALID` **before SQL writes**. No automatic ALTER/migrations.
- Under `GET_LOCK(product-master)` and one explicit CI4 DB transaction, lock owner, existing edges, direct Product/UOM references. Verify graph, then run a pure `ProductComboPlan` that deletes omitted rows, updates only changed quantity (keeping item IDs), and inserts new components. Persisted audit timestamps are changed only for real updates. All writes and readback occur inside the transaction, with rollback/transaction reset on failures.
- If the database or a conflicting writer does not honor this Product Master advisory lock, true concurrency safety remains **unverified** until independent XAMPP two-writer testing. Never promote source/fixture results into a live concurrency PASS.

## Offline acceptance evidence

- `php backend/tests/m72_combo_rules_pure.php`: 43/43 PASS.
- `php backend/tests/m72_combo_graph_fixture.php`: 17/17 PASS (cycle and atomic plan model; **not live DB rollback**).
- `php backend/tests/m72_combo_contract_pure.php`: 26/26 PASS.
- `php backend/tools/m34b_run.php --offline`: 119/119 PASS.
- `php backend/tools/m34d_accept.php --offline`: 6/6 architecture PASS.
- `php backend/tools/m72_accept.php`: 136/150 source operations; exactly 14 missing.
- `python backend/tools/verify_routes.py`: 136/150, no unknown/duplicate routes.
- PHP app source lint: 295/295 files PASS in authoring environment.

## XAMPP deployment (source-only)

```powershell
cd D:\XAMPP\htdocs\erp
git -c core.autocrlf=true apply --check .\ERP_M72_COMBO_ITEMS_ATOMIC_v1.4.0.patch
git -c core.autocrlf=true apply .\ERP_M72_COMBO_ITEMS_ATOMIC_v1.4.0.patch
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m72_combo_rules_pure.php
php tests\m72_combo_graph_fixture.php
php tests\m72_combo_contract_pure.php
php tools\m72_accept.php
```

Do not rerun the waived Frontend/Backend fingerprint checks, do not change the production write gate and do not migrate user data for QA.

## Pending DEV runtime acceptance — only with disposable fixture and backup

1. **Read-only table engines**: query `information_schema.TABLES` for the actual names (consider `DBPrefix`), and confirm `products`, `product_uoms`, `product_combo_items` are all InnoDB. If not, **stop**; do not convert data engines merely to satisfy QA.
2. **GET**: authenticated visible COMBO => sorted component rows; hidden owner => no component leakage; non-COMBO owner => 422 role error; deny actor => 403/404 as determined by policy. Compare EN/VI responses with baseline.
3. **Reversible PUT**: only disposable COMBO & components. Verify create, quantity update preserving persisted ID, omission delete, empty explicit `items` clear, and SQL audit columns; verify `DECIMAL(18,6)` precision. PUT disabled by default remains 403.
4. **Negative and rollback**: self-reference, nested cycle, duplicate, invalid component role, inactive new component, missing base UOM, malformed body and failed SQL insert. Compare pre/post DB snapshots to prove no partial writes.
5. **Concurrency**: authorized PUT vs another PUT and Product PATCH/SETSTATE using independent sessions. Confirm serialization against the Product Master mutex and verify transaction isolation; **not proved offline**.
6. **Independent v1.2.4 parity**: compare behavior and SQL effects, including previously retained inactive component semantics. Run `EXPLAIN` on owner and edge lookup queries with realistic catalog data if performance certification is required.

**Status:** SOURCE_ACCEPTED; actual HTTP/MariaDB, InnoDB rollback, concurrency, benchmark and independent baseline parity NOT_TESTED. Production cutover BLOCKED. Prior M6.3C concurrency remains WAIVED/NOT_VERIFIED; the M7.1 runtime gates remain OPEN.
