# M6.1 — Product Group / Product UOM master catalog (Backend Rewrite)

**Status: SOURCE/OFFLINE ACCEPTED, DEV HTTP/MariaDB/PBAC pending.** Based on the operator's accepted M5.5 build (110/150 API). No deployment acceptance or write parity is inferred from these offline tests.

## Frozen contract and ownership

- Pinned authority: `openapi-frozen-v1.2.4.yaml` + `openapi-operations-v1.2.4.json` (API-050); legacy behavior is in `backend_baseline/app/Services/Api050ProductCatalogService.php`, `Modules/Product/ProductGroup/ProductGroupService.php`, `Modules/Product/Uom/ProductUomMasterService.php`.
- The first M6 slice is ten routes (five Product Groups, five Product UOMs): GET list, POST create, GET detail, PATCH edit and POST set-state per resource. Inventory: **110 + 10 = 120/150**. There are no hidden Product endpoints and no implicit auto-route. The two masters live under one `Modules/Product/Catalog/` owner.
- Product Master, media aliases, role/lookup APIs, UOM conversions, Combo/BOM, Pricing and Imports are NOT implemented in M6.1. Media still requires a separate source/HTTP review; do not expose `/writable` to the web.
- No change to Frontend, the frozen OpenAPI, `backend_baseline`, `.env`, Apache or database schema/migrations.

## Master-data invariants

1. The Product Group `parent_id` references its own tree. Reparent/create must reject missing parents, self-parent, cycles and corrupted ancestry; group writes serialize the hierarchy with the baseline-compatible `product-group-tree` namespaced `GET_LOCK` and inspect SQL graph with `FOR UPDATE`. Product UOM has no parent and rejects owner/scope fields.
2. `code` is unique per table. Product Group codes are trimmed and uppercased using baseline ASCII `strtoupper`; UOM codes are trimmed, case retained. The DB UNIQUE(code) index remains authoritative if writers race. Server controls timestamps and actor audit fields.
3. Lifecycle is **authoritative** for both resources (neither belongs to the ten independent State types). CREATE uses server `ResourceStateRepository::initialState`; a supplied deprecated status hint may only equal the initial State. PATCH cannot change status. State transition requires a unique active `setstate` transition, both PBAC checks, a locked row and a conditional status update.
4. Authenticated GET requires VIEW rights. GET list applies PBAC to each persisted row before filtering, pagination and computing total. GET detail hides records without VIEW. Mutation uses Access `decideMutation` over server-selected persisted target, never an `evaluation_organization_id` hint; successful write responses must also pass VIEW inside the same transaction or rollback.
5. `ApiResponder` translates `PRODUCT_GROUP_*` through the frozen `ProductGroup` dictionary and `PRODUCT_UOM_*` through frozen `ProductUomMaster` in `en`/`vi`. HTTP error code is stable regardless of Accept-Language. Do not alter the original baseline dictionaries.

## Offline verification (no database)

From `backend/`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expected: **82/82** pure/fixture PASS, **6/6** M3.4D architecture PASS, prior milestones 84/90/93/99/105/110 and **M6.1 SOURCE PASS: 110 existing + 10 new = 120/150**.

## DEV integration checklist (operator XAMPP, existing VirtualHost)

1. Confirm `DINKERP_M33B_WRITE_MODE=enabled` **only for DEV**, Shield session/CSRF, and existing Product Group/UOM Resource+Action+Lifecycle assignments. Run against `https://api.monmin.net`, not a production endpoint. Use disposable master records.
2. GET `/api/v1/product-groups` and `/api/v1/product-uoms`, query with `q/page/page_size/filter/sort` and compare page counts for users with broad/limited/denied VIEW policies. Verify both `Accept-Language: en`/`vi`.
3. POST each master with `code/name`, and POST a child Group pointing to an existing Group. Expect 201, persisted `status` from Lifecycle initial State, audited actor IDs and valid parent links. Duplicate code must not create a second row.
4. PATCH rename and reparent a Group. Try self-parent, descendant-parent and missing parent: reject and leave SQL unchanged. PATCH UOM code/name; reject `parent_id` and direct `status` patches.
5. On a configured active Workflow transition, POST `/actions/set-state` with approved status. Confirm 200, persisted transition, correct translation and audit; repeat for a forbidden target and for a user without PBAC permission. No rows must change on rejection.
6. Test guest 401, write gate off 403, CSRF failure, denied actor, missing/hidden IDs, invalid input, concurrent duplicate writes. Verify no unauthorized list-total leakage.
7. Compare accepted read shapes/statuses with `https://apibase.monmin.net` on the **same DEV records**. Successful-write baseline parity and concurrent state transition/lock contention are a separate opt-in DEV gate, not claimed here.

## Current limitations

- The authoring container has no connected XAMPP Apache/CI4 MariaDB runtime. Source/pure acceptance only.
- PBAC policy granularity, lifecycle transition configuration, write rollback and two-writer concurrency still require on-machine tests. Earlier M5.1–M5.5 mutation HTTP acceptance remains pending and is not implicitly covered.
- List materializes authorized rows before pagination (same safety semantics as existing compatibility read). SQL optimization may follow measured performance without changing PBAC or totals.
