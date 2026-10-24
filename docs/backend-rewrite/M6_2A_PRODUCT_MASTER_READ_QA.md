# M6.2A — Product Master authorized read (Backend Rewrite)

**Status: SOURCE/OFFLINE ACCEPTED; DEV HTTP/PBAC/PARITY NOT TESTED.**

This is intentionally **the READ half of M6.2**, not a claim that Product Master CRUD is complete. The full v1.2.4 Product Master create/update/set-state workflows include automatic numbering, parent-to-variant conversion with history locks, and COMBO component persistence. Those writes require a separate M6.2B implementation and HTTP/MariaDB verification; registering placeholder mutation routes would be misleading.

## Contract and owner

- Frozen contract: `docs/backend-rewrite/openapi-frozen-v1.2.4.yaml` and `openapi-operations-v1.2.4.json`, API-050.
- Two exact routes: `GET /api/v1/products` (`listProducts`) and `GET /api/v1/products/{id}` (`getProduct`). Both are under `Modules/Product/Master/` and explicitly registered in `Config/M62ARoutes.php`.
- API source inventory: **120 (through M6.1) + 2 (M6.2A) = 122/150**. The earlier M3/M4/M5/M6.1 source fingerprints are replayed in the route regression suite.
- Does not modify `backend_baseline`, Frontend, OpenAPI, migrations, Apache, `.env` or the accepted 74 baseline locale catalogs.
- The Product root stays canonical; no parallel `ProductManagement` module. `Catalog/` continues to own Product Group/UOM.

## Core read invariants

1. A Shield user must be authenticated. List additionally requires the `product.view` base permission, including when no products exist. Every persisted Product receives the authoritative Access/PBAC `view` decision **before filtering, counting and pagination**. Detail hides a PBAC-invisible row.
2. Product role and name are derived from a **complete Product structural snapshot**, not only the page: `SIMPLE`, `PARENT`, `CHILD`, `COMBO`; a CHILD name is `parent.product_name + ' - ' + product_child_name`. Reject broken chains, missing parents, nested children, COMBO descendants and invalid Product types rather than projecting a guessed role.
3. Only `ACTIVE` `SIMPLE`, `CHILD`, and `COMBO` can be selectable; `leaf_transactionable` is true only for SIMPLE and CHILD. `serial_management_enabled` is a boolean in the public DTO.
4. `code` is exposed as `product_code`; private image metadata is stripped. A nonempty `image_filename` indicates `image_available`; **`image_url` is returned only for valid baseline-shaped filenames using `/uploads/YYYY/filename`**, because static Product Media aliases and any `/writable` URL require their own security review. Legacy 40-hex and unrecognized image filenames still return `null` until explicitly migrated. On-disk presence/static Apache access are checked separately.
5. `allowed_actions.edit` and `.setstate` are advisory, recomputed via Access against the persisted row. They do not grant permission or guarantee that a transition exists.
6. `q`, `page`, `page_size`, exact `filter`, and `sort` operate after authorization. The frozen historical `product_id` sort alias maps to persisted `id`; `full_name`, `price`, `product_code`, `sku`, `product_type`, `status` are supported. The list currently materializes the full catalog for structural validation, so large-catalog SQL paging optimization is a separate performance task.

## Source-only QA (no SQL or HTTP)

From `backend/`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expect **85/85** pure/fixture suites, six M3.4D architecture gates and `M6.2A SOURCE PASS: 120 existing + 2 new = 122/150 routes`.

## Optional DEV GET smoke (existing Apache and DEV database)

Run against `https://api.monmin.net` with a **DEV** authenticated browser session and compare `https://apibase.monmin.net`:

1. GET `/api/v1/products?page=1&page_size=20&sort=product_id` in both `Accept-Language: vi` and `en`.
2. Verify a visible SIMPLE, PARENT, CHILD and COMBO, including full name, allowed_actions, selectable, and disabled image link. Read a valid detail ID.
3. Compare authorized users with different `product.view` policies: hidden rows must not affect `meta.total`; forbidden details must not leak fields.
4. Exercise q/filter/sort; verify malformed sort/filter is rejected. Check that Product POST/PATCH/set-state routes remain **unregistered** on Backend Rewrite.
5. Record differences for image_url, large-catalog performance, legacy Unicode sorting, and in-flight Product changes; do not claim complete baseline parity from offline tests.

**Outstanding:** Product CREATE/PATCH/set-state (including auto numbering, SKU uniqueness, parent-child history, COMBO atomicity) in M6.2B; Product selector/resolve/lookups, media, conversions, combo items and import in later slices. Earlier M5.1–M6.1 DEV mutation acceptance also remains pending. Do not infer production readiness from source tests.
