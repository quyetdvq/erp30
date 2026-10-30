# M7.1 Product UOM Conversion PUT Transport Hotfix

**Date:** 2026-10-29
**Baseline:** `erp_v2.0.0_fe_multitab_modal_fixed.zip` (includes FE-MODAL-01)
**Scope:** frontend transport and regression tests only; no backend API, DB, PBAC, route or OpenAPI changes.

## Incident

`PUT /api/v1/products/1479/uom-conversions` returned HTTP 400 `MALFORMED_REQUEST`; CodeIgniter threw `Replacement requires an explicit JSON array or items object.` at `ProductConversionController::replacementBody()`. That exact branch executes when `trim((string)$request->getBody()) === ''`, so the **server observed an empty body**. The provided log does not show the browser Request Payload or the transport layer, therefore the exact reason the body was empty cannot be confirmed from this log alone.

Before the patch, `replaceProductConversions()` passed a bare array `body` to the generated Axios SDK without specifying the explicit JSON body serializer. Other replace-all services (`replaceProductComboItems`, `replacePriceListItemRows`) already use an object `{items: [...]}` and `jsonBodySerializer.bodySerializer`.

## Fix

- Both Product editor entrypoints still call one `replaceProductConversions` service.
- This service now always submits `body: { items: Array.isArray(body) ? body : body.items }` and `bodySerializer: jsonBodySerializer.bodySerializer`.
- Empty list is **explicit** `{ "items": [] }`; missing body or `{}` remains rejected by the backend to avoid an unintended delete-all.
- Existing backend validations, transaction, locking, SQL PBAC, and response semantics are unchanged. No migration is required.
- This operation is **full replacement**, not merge: leaving a UOM out removes it after validation. Keep the existing UI confirmation/intent model; do not automatically retry a rejected PUT.

## Offline validation and remaining DEV checks

Run on Windows:

```powershell
cd D:\XAMPP\htdocs\erp\frontend
node --experimental-strip-types --test scripts/m71-uom-replacement-transport.contract.test.mjs
pnpm exec vitest run src/services/__tests__/productCatalog.spec.ts
pnpm run type-check
pnpm run check:imports
pnpm run build

cd D:\XAMPP\htdocs\erp\backend
php tests/m71_conversion_contract_pure.php
php tests/m71_conversion_rules_pure.php
.\tools\test-unit.ps1 RUN UNIT-STANDARD
```

Frontend DEV UAT: open a `STANDARD` product with a valid ACTIVE base UOM, select alternative UOM, enter valid factor and price when SALES/BOTH, save once. Verify in DevTools → Network → Request Payload that the PUT carries `{"items":[...]}` and the response is 200; GET/reload must match the persisted conversions. Repeat deliberate remove-all to see `{ "items": [] }`. A request without a JSON body must still fail with 400, **not** silently clear the list. Check actor permission/CSRF and product type eligibility. If the body still arrives empty, capture Request Payload + Content-Type and any reverse-proxy rewrites for further diagnosis.

**QA status:** SOURCE/OFFLINE validation only; actual HTTP/MariaDB/Browser checks must be run by the user on DEV and recorded separately.
