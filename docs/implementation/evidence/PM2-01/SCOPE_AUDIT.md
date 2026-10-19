# PM-2 scope-to-source audit — Product Media pilot v0.1

**Source inspected:** reconstructed PM-2/01 source copy at `pm2_01_work/source`, checked against the PM-2/01 checkpoint manifest (26/26 source + 2/2 test matches). **This is a scoped code review**, not a substitute for XAMPP's full repository hash, runtime security tests or independent approval.

## Approved obligations

| v0.1 obligation | Actual code / evidence | Finding |
| --- | --- | --- |
| Remove `productForImage()` reverse call from canonical Media | `ProductReadMediaAccessAdapter::authorizedProduct()` → `AuthorizedProductReadService::authorizedRow()`; `Modules/Product/Media` contains no Api050 or `productForImage(` | **MET** |
| Remove `getProduct()` reverse call from canonical Media | `ProductReadMediaAccessAdapter::publicProduct()` → shared Product read service `publicProduct()`; no `getProduct(` under canonical Media | **MET** |
| Keep pre-upload permission precedence | `ProductImageApplicationService::upload()` calls `authorizedProduct(id,'edit')` before `storage->store()`; controller-level transport behavior retained by tests | **MET in code + characterization tests**; real multipart still missing |
| Independently check `view` after upload/remove | Application calls `access->publicProduct($id)` after writes/cleanup; shared reader enforces its own `view` | **MET** (also PM-2 integration negative test) |
| Trust persisted Product and PBAC | `AuthorizedProductReadService::requireProduct()` loads Product from ProductMasterService; authorization creates `EvaluationOrganizationRequest(persistedTarget: $row)` | **MET in inspected seam + guarded integration** |
| Shared seam between Api050 and Media | `Api050ProductCatalogService::productForImage()` delegates shared `authorizedRow`; `getProduct()` decorates a shared-authorized row; Media uses shared adapter | **MET**; bounded DTO decoration duplication retained intentionally for source-contract compatibility |
| Preserve optional injected legacy catalog/actor | `ProductMediaFactory::create(db,catalog,storage)` calls `$catalog->productReadAccess()` when injected, otherwise `ProductReadAccessFactory::create(db)` | **MET in available tests**; legacy factory may still reference Api050 as a compatibility type outside canonical module |
| Preserve storage, metadata, signatures and failure ordering | PM-1 service/adapter, PM-2 access port; `ProductImageService` constructor public signatures unchanged | **MET in code/test coverage**; no new transaction guarantees asserted |
| No broad API/schema/PBAC/workflow refactor | PM-2 patch touches scoped classes and tests, no route or migration changes; 26 API operations guarded by preexisting tests | **No changed route/migration observed in patch**; release API/schema diff remains PM-3 evidence requirement |

## Outstanding work, classified by approved stage

1. **No unimplemented PM-2 reverse-call feature identified.** PM-2/01 is the only structural source change needed for the narrow two-call scope on current evidence. A further PM-2 patch is **not warranted** merely to make the runner report `CLEAN`.
2. **PM-3 blocker for platform-pattern acceptance:** `SqlProductImageMetadataRepository::write()` uses process-local `date()` and ambient Shield `auth()->user()` in the canonical Media infrastructure. The v0.1 plan says trusted actor/time should use an appropriate collaborator, with zero behavior drift for structural refactoring. This is an **architecture/behavior contract gap** requiring a separately scoped no-drift change or explicit review disposition before pattern promotion. Distinguish **infrastructure implementation** (SQL and filesystem are allowed there) from **application layer** (must not directly use SQL/filesystem).
3. **PM-3 coverage gates:** actual HTTP multipart boundary; secure browser CSRF; deployment/operational rehearsal; independent review; integration failure cleanup and actor/clock verification beyond current tests; exact API/schema diff.
4. **Separate code quality workstream:** PHPStan 115 diagnostics in PM-2 Pilot, regression `phpstan_exit=1`. Compare exact diagnostic IDs, file paths and messages before attributing regressions or accepting exceptions. The static type hints in source may preserve inherited diagnostics; count alone is insufficient.
5. **Compatibility debt, not a PM-2 requirement:** Api050 retains private DTO decoration while shared Product reader projects Media DTO; the factory's optional catalog type is a transitional entrypoint for existing callers. Do not remove either without consumer inventory and compatibility tests.

## Architecture reading

Expected direction:

```text
HTTP ProductImages controller
   -> legacy ProductImageService (compatibility facade)
     -> ProductMediaFactory (bootstrap composition)
       -> ProductImageApplicationService
          -> ProductMediaAccessPort -> ProductReadMediaAccessAdapter
                                 -> AuthorizedProductReadService (Product-owned)
          -> ProductImageMetadataPort -> SqlProductImageMetadataRepository
          -> ProductImageStoragePort -> LocalProductImageStorage
```

`Api050ProductCatalogService` is an **independent consumer** of `AuthorizedProductReadService`, not a dependency of `Modules/Product/Media`. Bootstrap may bridge injected legacy Api050 to shared capability but does not construct Api050 on the default Media path. Legacy actor/timestamp remains ambient in metadata infrastructure, tracked above.

**PM-2 gate decision:** reverse-call extraction **complete and accepted with exceptions**; enter PM-3 verification, not production or general rollout.
