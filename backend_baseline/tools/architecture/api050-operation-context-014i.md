# BE-REORG-014I — API-050 command-scoped OperationContext

**Scope:** `App\Services\Api050ProductCatalogService` only. No route, OpenAPI, schema, permission, PBAC rule, workflow transition, Numbering allocator, repository SQL, or transaction algorithm is changed.

## Problem confirmed from source

`Api050ProductCatalogService::operation()` creates a new `OperationContext` whenever it is called. Before 014I, multi-step facade commands could call it once for the owner mutation and again during response read authorization. `createProduct()` could call it separately for Product create, COMBO composition and Product readback. Because `MutationContext::begin()` captures `EvaluationTime` when no value is supplied, one HTTP command could therefore carry different operation objects/timestamps through its participants.

This is an architectural consistency risk, not evidence of an already observed production authorization defect.

## Change

- Capture `$operation = $this->operation()` once at each affected mutation command boundary.
- Pass that exact object to the owner mutation and the existing authorized readback.
- Add private fixed-context read helpers for Product Group, UOM, Product and Price List.
- Preserve the existing public read methods and `productReadAccess()` compatibility seam; standalone reads still capture a fresh context per invocation.
- Keep `operation()` stateless: no request-global or service-level `OperationContext` cache.
- Keep the existing COMBO transaction structure unchanged. `KIR-001` remains open.

Affected facade commands: `updateGroup`, `setGroupState`, `updateUom`, `setUomState`, `createProduct`, `updateProduct`, `setProductState`, `createPriceList`, `updatePriceList`, `setPriceListState`.

## Verification

`php tools/test-be-reorg-014i-operation-context.php` checks source-level object-flow invariants and the KIR-001 transaction freeze. `test-be-reorg-014h-residual.php` is updated so the residual manifest records CXT-001 as `SOURCE_FIXED_RUNTIME_NOT_VERIFIED` instead of silently accepting drift.

Runtime MariaDB/Shield/API parity is still required before treating CXT-001 as fully closed. In particular, actor, `evaluationTime`, request ID, PBAC result/error precedence, mutation outcome and readback response must be compared on the guarded test stack.

## Rollback

Revert the API-050 facade changes, the 014I gate, and the 014H manifest/test status update. No database rollback is required.
