# M6.2B — Product Master CREATE / PATCH / SETSTATE

## Scope and ownership

Three exact frozen API-050 operations are added to the **existing** `Modules/Product/Master` owner:

| HTTP | Operation ID | Success |
|---|---|---|
| `POST /api/v1/products` | `createProduct` | 201 |
| `PATCH /api/v1/products/{id}` | `updateProduct` | 200 |
| `POST /api/v1/products/{id}/actions/set-state` | `setProductState` | 200 |

Prior GET list/detail, Product Group, UOM, HTTP session, public media and all 122 existing API operations remain unchanged. Total **125/150**. No Product Management duplicate root, no migrations, no media mutation, no Apache or Frontend changes.

## Consistency and domain boundaries

- **Identity and PBAC**: Shield user session required, DEV write gate `DINKERP_M33B_WRITE_MODE=enabled`. `UnifiedAccessService` authorizes `create`, `edit`, `setstate` against the persisted ERP permission snapshot, policy, State and organization scope. CREATE uses the server-owned Lifecycle initial State; set-state uses the persisted Lifecycle transition and transition action check. The client cannot grant authorization through `status`, `evaluation_organization_id`, or infrastructure fields. Read-back checks VIEW; COMBO creation independently checks edit permission on the just-created object.
- **Product semantics**: strict `STANDARD`/`COMBO`, root/child names, SKU, base UOM, price, negative values and precision. The parent of a child must be a root STANDARD. SIMPLE→PARENT clears parent price/serial management transactionally only when its existing Price List/UOM/COMBO references do not block the conversion. Direct PATCH reparent and direct PATCH status changes are rejected. Read-derived `role` and `full_name` are not persisted.
- **Numbering**: with a default/selected ACTIVE Product series, CREATE may issue a code within the same physical transaction as Product and counter; manual codes follow `AUTO_IF_EMPTY` policy. Without a series, an explicit `product_code` is required. The numbering candidate loop skips occupied identifiers, validates length and rolls back Product+counter together on failure. A Product-wide named MariaDB lock serializes writers in this initial implementation. `GROUP` scope uses the unique ACTIVE GROUP root. Timeless `ORGANIZATION` scope may reuse that authoritative GROUP root; contextual `ORGANIZATION` or `COMPANY` scope fails closed because Product has no server-trusted company context yet. Do not infer scope from arbitrary client IDs.
- **COMBO atomicity**: `combo_items` is accepted **on create only**, and only for `COMBO`. Its component IDs and quantities are validated. The Product row, numbering counter and component rows commit together or roll back together. Product edit permission in the initial State is **separate** from create permission.
- **History**: existing Price List, UOM conversion and COMBO relationships are checked before semantically incompatible changes. The accepted v1.2.4 baseline currently binds a no-op historical *transaction* provider while the transaction subsystem is absent; this implementation likewise cannot claim proof against future sales/inventory history not represented in current schema. Extend the authoritative history provider when those tables are introduced.
- **Error localization**: the new `ProductRuntime` EN/VI overlay provides Product-specific messages while preserving all 74 baseline catalogs byte-for-byte. Stable `error.code` is language-independent; HTTP 500 sanitizes SQL/exception details.

## Source/offline verification

Run from `D:\XAMPP\htdocs\erp` on the current M6.2A.1.1 installation after applying `M6.2B-product-master-mutations.patch`:

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\M6.2B-product-master-mutations.patch
git apply .\M6.2B-product-master-mutations.patch

cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

The expected inventory is **125/150**, with **3 M6.2B routes** and **all prior 122 routes pinned**. Tests cover source routing, normalizers, cross-module guards, EN/VI error mapping, and previously frozen architecture. These are **offline fixtures**, not a substitute for HTTP/MariaDB tests.

## Simple DEV HTTP/SQL checklist (not performed in the patch-building environment)

Use the existing Apache `api.monmin.net` and development database; do not require a second VirtualHost or cloned DB for ordinary DEV work. Take a local backup before successful mutations and use disposable DEV records/codes. Use the already authenticated DEV session with CSRF protection and `Accept-Language: en` then `vi`.

1. **WRITE gate**: verify 403 `ACCESS_MUTATION_NOT_ENABLED` when disabled. Restore DEV configuration only in the DEV environment; check 401 for anonymous callers and 403 for accounts without CREATE/EDIT/SETSTATE.
2. **POST**: create a SIMPLE Product using a genuinely ACTIVE `product_group_id`, `base_uom_id`, `sku`, `product_name` and `price` (and `product_code` for manual mode). Verify 201 and persisted Product/audit fields; verify duplicate SKU/code rolls back and returns conflict; test configured AUTO numbering and counter persistence if one exists.
3. **PATCH**: edit safe fields; reject direct `status`, reparent and invalid parent/type/UOM. Confirm that ordinary edits do not reset price/serial fields or alter the numbering counter.
4. **COMBO**: create with `product_type=COMBO`, `combo_items` (component IDs/positive quantities), and necessary fields. Verify Product and components both persist; force an unauthorized/invalid component and prove Product/counter/component rollback. Confirm CREATE-only permission does not grant COMBO edit.
5. **SETSTATE**: obtain valid Lifecycle state/transition from existing workflow options and execute once. Check HTTP status, persisted State, allowed actions and policy checks. Invalid transitions must leave State unchanged.
6. **Concurrency**: submit two distinct Product AUTO CREATE requests against one configured series. Confirm unique codes and a monotonic counter; this must be measured on MariaDB and is **not** implied by static advisory-lock assertions.
7. **Localization**: send EN/VI headers for validation, duplicate, permission denial and transition errors. Verify stable codes, localized messages and no raw SQL/stack trace in responses.

### Acceptance status at delivery

- **PASS**: source/contract, offline rules, locale overlays, route inventory and frozen architecture checks.
- **NOT_TESTED**: live Product mutation HTTP/MariaDB, concurrent numbering issuance, COMBO SQL rollback, and independent baseline mutation parity.
- **NO CLAIM**: complete transaction history lock or production readiness. This is a DEV implementation milestone.
