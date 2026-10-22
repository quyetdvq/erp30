# BE-REORG-014H — API-050 residual ownership and performance review

**Snapshot:** DinkERP v1.2.2 + BE-ARCH-001 + 014C/014D/014E/014F/014G. **Standard:** `DINKERP-BE-ARCH-001 v1.1.1`, `PROPOSED BASELINE`.

**Status:** `SOURCE_OBSERVED / DRAFT`, not a certification of architecture conformance or public module API approval. BE-REORG-014H is **audit + measurement tooling only**: it does not change `backend/app`, `Routes`, OpenAPI, SQL, PBAC, Data Definition, schema, transaction owners or HTTP behavior.

## 1. Reconcile 26 routes and 22 residual operations

Authoritative source inventory for the route/controller/OpenAPI mapping remains `api050-orchestration-inventory.json` (014E, updated through 014G). This 014H audit references it and adds per-method evidence markers; **do not maintain a second contradictory route table**.

| Observed class | Count | Methods | Judgment |
|---|---:|---|---|
| Already query-delegated | 4 | `listProducts`, `listPriceLists`, `listUoms`, `listGroups` | Previously moved to Product/Pricing; not residual |
| Owner-delegated | 5 | `replaceConversions`, `getComboItems`, `replaceComboItems`, `getPriceListItems`, `replacePriceListItems` | Already call owner operations; **no new wrapper just to reduce lines** |
| Facade protected read | 4 | `getGroup`, `getUom`, `getProduct`, `getPriceList` | Retain persisted-row PBAC, 404/403/error semantics, projection and authorization context |
| Owner mutation + facade readback | 8 | `updateGroup`, `setGroupState`, `updateUom`, `setUomState`, `updateProduct`, `setProductState`, `updatePriceList`, `setPriceListState` | `write → read` may run distinct PBAC checks; do not change response or error precedence without contract tests |
| Initial-state normalized create | 3 | `createGroup`, `createUom`, `createPriceList` | Must preserve normalization, mutation, optional readback, state and context |
| Facade authorization gate | 1 | `getConversions` | `product.view` is checked **before** calling ProductUomService::listByProduct (which is not self-authorizing at this entrypoint) |
| Conditional multi-step COMBO create | 1 | `createProduct` | **KIR-001 / P0 STOP** until ADR and MariaDB/Shield transaction+concurrency POC |

**Residual:** 5 + 4 + 8 + 3 + 1 + 1 = **22** operations. Product owns 19 routes overall and Pricing 7; these ownership figures **do not** mean each operation should receive its own service. A route-to-method-to-owner ledger with evidence markers lives in `api050-residual-014h.json` and is checked by `test-be-reorg-014h-residual.php`.

Two additional active non-route consumers block facade deletion: `Bootstrap/ProductMediaFactory` uses `productReadAccess()`; `Controllers/Api/ProductImages` uses `productForImage()`.

## 2. OperationContext, transaction and authorization risks

- **`CXT-001` P1**: `Api050ProductCatalogService::operation()` invokes `MutationContext::begin()` when called. `MutationContext::begin()` captures an `EvaluationTime` if one is not supplied. Multi-step facade paths can invoke this method repeatedly (including Product create/COMBO, write then authorized show). This is **source-observed risk**, **not proof of a persisted runtime defect**. Before changing semantics, add tests for same object/actor/evaluationTime/requestId across command participants, Numbering callback, audit and readback. Keep context confined to one command, not singleton/request-global state. The Standard requires one context per command; do not silently rewrite PBAC evaluation in this audit.
- **`KIR-001` P0**: In `createProduct()` the `combo_items` branch opens a transaction with `transBegin()`. AUTO Product creation can pass through Numbering's `AutoNumberingCounterAllocator` which rejects a positive `transDepth`. The exact failure/success matrix on MariaDB remains unverified here. **DO NOT** change Numbering allocator, Product create transaction opener/locks or COMBO callback without approved ADR, test DB POC, rollback, concurrency/collision and security parity.
- **Read-after-write P1**: eight `update`/`setState` methods perform mutation through the owner then authorize a show/read response at the facade. A denied read *after* a committed write is a possible observable outcome under existing code; runtime tests must characterize it and the approved contract must decide if change is needed. Do not remove readback simply to eliminate extra PBAC calls.
- **`COMPAT-001` P2**: keep API-050 compatibility for active Media/Image callers until all routes, factories, CLI/scripts and tests have been migrated with tests and a release rollback path.

## 3. Why Group/UOM List needs measurement

Both canonical list services currently use `ProductGroupService::listAll()` / `ProductUomMasterService::listAll()` and their repositories execute full ordered SELECTs (`ORDER BY code ASC, id ASC`), without SQL `LIMIT`. They then evaluate **every persisted row** with PBAC when lifecycle/state scoped, before `COUNT` and pagination. Otherwise the base `view` gate runs once. `CommonListQueryAdapter` + `AuthorizedArrayListSource` filters for authorized total, filters again for sort/page; sorting happens in PHP.

This implies full dataset memory materialization and **O(N) policy decisions on the state-scoped branch** (not necessarily N database queries; Authorization may share reads). It does **not** demonstrate that the endpoint exceeds a performance budget in the real system.

**Safety invariant:** do not push `COUNT/LIMIT/OFFSET` before PBAC. Do not prune hidden Group parents, rewrite `parent_id`, auto-convert `filter.parent_id=02` to `2`, or alter comparator/Unicode/error precedence. Any SQL fast path must prove authorized `total/items/page` equivalence for mandatory gates, effective time, state and visibility.

## 4. Measurement plan and evidence levels

| Layer | Tool | What is measured | What is NOT measured |
|---|---|---|---|
| PHP synthetic proxy | `backend/tools/bench-be-reorg-014h-php.php` | Real ProductGroup/UOM Query Services and array pagination, fake full repository/PBAC, wall p50/p95, fake decisions count, process high-water memory, authorized count assertions | MariaDB SQL/queries, Shield, actual PBAC lookup latency, real Unicode if extension absent, API status, HTTP overhead, concurrency, production p95 |
| HTTP test baseline | `backend/tools/bench-be-reorg-014h-http.ps1` | Authenticated localhost GET only: response/data/meta shape, `p50/p95` HTTP wall time, payload bytes, authorized total stability, under explicitly confirmed `erp_test` | Actual DB identity (operator attests), SQL EXPLAIN, query count, server memory, PBAC internals, concurrent reads/writes |
| SQL plan/read observation | Manual authorized test-only DBA/CodeIgniter instrumentation | `EXPLAIN SELECT * FROM dinktech_product_groups ORDER BY code ASC, id ASC` and UOM equivalent; DB roundtrips, rows examined, indexes, query count | No `EXPLAIN` or DB measurement was executed from the source ZIP. Table prefix must be confirmed in the test environment. Raw EXPLAIN is **not** a PBAC equivalence proof. |

### Running the PHP proxy (no vendor/DB)

```powershell
php .\backend\tools\bench-be-reorg-014h-php.php --self-test
php .\backend\tools\bench-be-reorg-014h-php.php --sizes=100,1000,10000 --samples=30 --warmup=4 --output=.\backend\tools\architecture\014h-local-php-metrics.json
```

This optionally emits local benchmark JSON; **do not commit the machine-specific output as a universal threshold**. Use identical PHP version/ext, fixture dimensions and flags for before/after comparisons.

### Running the authenticated HTTP benchmark (Windows)

1. Confirm the **running local ERP** uses the separately backed-up `127.0.0.1:3306/erp_test` (or the approved test target), not a production DB. The HTTP probe cannot infer its configured DB.
2. Use a limited-permission **test user** already authenticated, with policy/state scope known. Obtain its session cookie or auth token locally; never paste a credential into chat/source or commit it. Set one process-local environment variable `ERP_014H_AUTH_COOKIE` **or** `ERP_014H_BEARER_TOKEN`.
3. Run below with the actual local base URL; the script allows `localhost`/`127.0.0.1` only, rejects redirect/non-200 responses and performs **GET only**. Run separate labeled sessions for base/row-scoped actor cohorts if available.

```powershell
.\backend\tools\bench-be-reorg-014h-http.ps1 `
  -BaseUrl 'http://127.0.0.1/erp/backend/public' `
  -ConfirmTestDatabase erp_test `
  -Cohort 'authorized-test-actor' `
  -Samples 40 -Warmup 5 `
  -OutputPath '.\backend\tools\architecture\014h-local-http-metrics.json'
```

`-BaseUrl` is an **example**, not a verified running address. Clear auth variables after use:

```powershell
Remove-Item Env:ERP_014H_AUTH_COOKIE -ErrorAction SilentlyContinue
Remove-Item Env:ERP_014H_BEARER_TOKEN -ErrorAction SilentlyContinue
```

The script intentionally does not output raw API data, cookies, bearer tokens or personal identifiers. Do not upload benchmark results if they include secrets from other tools.

### Required representative scenario matrix for performance decisions

- Small, medium, large `product_groups` and `product_uoms`; record total rows and row width, including actual Organization/State distribution.
- Base-only and active lifecycle/state-scoped PBAC; if possible, test actor whose visible set is small (e.g., 5%) and actor whose visible set is large.
- First/deep pages, `q`, `status`, `parent_id` and Unicode names/collations, stable sort tie-break, and denied parent/allowed child.
- Measure client p50/p95, server p50/p95, server peak memory, actual DB query count/time and EXPLAIN on representative indexes. Verify response `meta.total` against authorized set in tests, not a raw SQL count.
- Decide thresholds **after** baselining same test environment and dataset. If changing SQL authorization strategy or boundaries, obtain ADR/approved query projection and test 404/403, total/page parity and concurrency.

## 5. Review priority and proposed next increments

| Order | Work item | Safe scope now | Blocker for behavior change |
|---|---|---|---|
| P0 | `KIR-001` Product COMBO + AUTO | ADR draft and controlled read-only tests/POC plan | MariaDB/Shield integration, collision/rollback/concurrency proof, architecture & contract approval |
| P1 | `CXT-001` operation context | Characterization tests for current per-command participants; locate duplicate captures and audit context | Approved plan and parity before passing one context through callbacks |
| P1 | `PERF-001` Group/UOM PHP lists | Gather real HTTP/DB measurements using the provided tools; document cases and budgets | No SQL/PBAC rewrite until exact authorization-aware COUNT/page is proved |
| P1 | Owner mutation/readback | Tests protecting write outcome vs view-denied response and audit/transaction status | Approved response semantics and runtime tests for each use case |
| P2 | Protected read/conversions | Document read ownership and narrow Product read entrypoints without adding wrappers | 404/403/evaluation-context parity and active Media compatibility |
| P3 | Five owner-delegated methods | Leave unchanged; update MDR/consumer list when touched | None unless there is demonstrated duplication or coupling |

**Recommended next engineering checkpoint:** `BE-REORG-014I` focused on `CXT-001` characterization and controlled `KIR-001` test-plan design, **not** a speculative production transaction patch. Performance work remains conditional on actual measurements.

## 6. Quality gate semantics

- `php tools/test-be-reorg-014h-residual.php` verifies the 22-method snapshot and intentional risk freezes; eight negative manifest fixtures must fail closed.
- `php tools/bench-be-reorg-014h-php.php --self-test` verifies runnable synthetic measurement and authorized count assertions.
- Retain 014C/014D/014E/014F/014G, architecture scanner, five old source boundary gates and PHP lint.
- `UNIT-STANDARD` / `NO-DROP` PASS **from user's previous post-014G run** is historical evidence, not a 014H re-run. Since 014H touches no `backend/app`, runtime regression is out of change scope, but real performance measurement is **NOT_RUN** until the authenticated test environment is used.
- MariaDB/Shield, SQL EXPLAIN/query count, concurrency and production p95 are **NOT_RUN** in the delivered audit; `N/A` is not a replacement for required-but-unexecuted performance checks.

**Rollback:** remove 014H audit/test/benchmark files and the two doc additions. No production app code, API, DB schema or migration changes exist in this patch.
