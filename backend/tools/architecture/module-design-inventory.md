# DinkERP v1.2.2 — Interim Module Design / Conformance Register

**Standard:** `DINKERP-BE-ARCH-001 v1.1.1`, `PROPOSED BASELINE`. **Snapshot:** `erp_v1.2.2.zip`. **Status:** inventory only; ownership to be reviewed against approved DBD, API and contracts. This central document may serve legacy modules during retrofit (§3.2, §17); it is **not** a replacement for the official `DinkERP_Module_Design_Record_Template_v1.1.1.md` once supplied.

A PHP `public` method is not automatically a public module API. The `App\Modules` import graph has no detected module SCC cycle (static qualified name scan), but many dependency edges still point at legacy code. `NOT_VERIFIED` is **not** `COMPLIANT`.

| Root module | Candidate data owner / current capability | First approval/evidence to collect | Conformance |
|---|---|---|---|
| Identity | Shield users, profile, avatar; tables defined by Shield require separate DBD ownership confirmation | Shield/API entrypoint, membership and compatibility consumers | `NOT_VERIFIED` |
| Authorization | groups, group_permissions, policies, policy_actions, authorization_logs (technical log) | Public PBAC decision/read methods; mandatory gates; runtime non-recursive assignment read seam | `NOT_VERIFIED` |
| Resource | resources, resource_actions | Catalog actions, state metadata and read interfaces | `NOT_VERIFIED` |
| Workflow | lifecycles, workflow_states, lifecycle_states, lifecycle_transitions | Public transition/write boundary, state read seam and locks | `NOT_VERIFIED` |
| Numbering | auto_numbering_series, auto_numbering_counters | AUTO own-or-participate transaction semantics, rollback/collision/concurrency, `KIR-001-C2` | `NOT_VERIFIED` |
| Settings | configuration_audit_events; runtime display settings | Read/write config ownership, technical audit; retention | `NOT_VERIFIED` |
| Foundation | provinces, wards | Read/persistence and workflow ownership | `NOT_VERIFIED` |
| Organization | organizations; tree/ancestor/owner | Current PBAC scope/parent mutation. Tree projector boundary below | `NOT_VERIFIED` |
| Contact | contacts | Persisted owner, contact resource subtype visibility and reads | `NOT_VERIFIED` |
| EmployeeStructure | departments, positions, employees, employee_assignments | Assignment effective scope, read seam and primary overlap | `NOT_VERIFIED` |
| Product | product_groups, product_uoms, products, product_uom_conversions, product_combo_items | Protected Product list COUNT/page, public Product read methods used by Pricing, COMBO/AUTO | `NOT_VERIFIED` |
| Pricing | price_lists, product_price_list_items | Effective Price List + Decimal and Product read boundary | `NOT_VERIFIED` |

**Technical owner distinct from business modules:** `App\Infrastructure\Import\Persistence` writes `import_jobs` and `import_job_rows`. Product owns imported Product rows; a SQL join is not write permission. Database is shared, data ownership is not. Ownership of additional tables (especially Shield tables) must be added after approved DBD review.

## Organization tree: current retrofit slice

| Field | Decision/evidence |
|---|---|
| Use case | API-010 Organization tree read, unchanged HTTP contract |
| Owner | Organization; `Modules/Organization/Application/VisibleOrganizationTreeProjector` |
| Caller / compatibility | `Services/Api010FoundationOrganizationContactService::tree()` remains unchanged; delegate to projection after `list('organization', $query)` |
| Authorization / Evaluation Org | Existing `list('organization')` visibility; no new PBAC decision, no client owner override |
| List semantics | Project **page only**; if parent is missing from authorized page, set `parent_id = null`. Preserve result `query`, `total`, sort/page and array order |
| SQL / transaction owner | `N/A` for pure projector (no DB write/read). Existing authorized SQL/PHP fallback untouched |
| Workflow / Numbering / Import | `N/A` — read-only projection |
| Parity | `backend/tools/test-architecture-organization-tree.php` — 7 vendor-free vectors (source-level boundary assertion). Runtime API parity `BLOCKED_ENV` |
| External API/DB change | `N/A` — no new endpoint, schema, fields, action, machine error or state transition |
| Rollback | Revert import/delegate + delete projector; existing `tree` algorithm is frozen in characterization test |
| MDR lifecycle / status | Central interim `DRAFT`; architecture conformance `NOT_VERIFIED` until full tests |

## Product List Query: BE-REORG-014C retrofit slice

| Field | Implementation / evidence |
|---|---|
| Use case / API surface | Existing `GET /api/v1/products` / API-050 `listProducts`; externally `CONTRACT_READY` per API-050 baseline; **no HTTP route/response/OpenAPI change** |
| Business/data owner | `Product` / `products`; `Modules/Product/Product/Application/ProductListQueryService::list` is the Product-owned read/query entrypoint invoked by API-050. This is **not** yet an approved inter-module public method. |
| Compatibility caller | `Services/Api050ProductCatalogService::listProducts` retains signature and its injected collaborators; Controller and Product Media read paths are unchanged |
| PBAC integration | `Modules/Product/Product/Application/Ports/ProductListAuthorizationPort` is **Product-internal**, implemented by `Bootstrap/ProductListAuthorizationAdapter` using **existing** `AuthorizationService`, `EffectiveListBucketCompiler`, Workflow/Policy State gate and facade operation factory; no new Product→legacy static import. The adapter is only for Product List PBAC, **not** a general-purpose privileged bypass. |
| Protected operation | Existing Product `view`, `MASTER` at authoritative `GROUP` with persisted State; `Base Permission` and employee/assignment policy gates stay in AuthorizationService; deny/error code `PRODUCT_AUTHORIZATION_DENIED` preserved. The approved external error status/contract must be verified by runtime API tests, not assumed from a source-level gate. |
| List invariants | Allowlisted search/filter/sort unchanged; effective state buckets compiled inside authorization read-only batch and Product read snapshot; authorized State filter before `COUNT`/`PAGE`; page rows rechecked. SQL projection/source, complete role/relationship hydration and exact PHP fallback unchanged. |
| Transaction / read boundary | `readSnapshot` = existing `ProductRepository::withConsistentListSnapshot` for SQL paths, no new `BEGIN`, no write or Numbering transaction; snapshot not nested in write transaction. PBAC uses the exact legacy operation on SQL path (one instance carried to compile/recheck). Non-SQL row decisions retain legacy per-row operation capture for parity. |
| Precision/serialization | `total` stays `int|string` (`CommonListQueryAdapter` formats as decimal string), `product_code`, `serial_management_enabled` boolean, image path redaction preserved by already-existing `AuthorizedProductReadService::projectForApi` |
| SQL / schema / cross-owner write | `N/A` to new migration/write: no SQL or schema changed. As of 014J, the Product-specific SQL source lives in `Modules/Product/Product/Application/SqlProductListSource` (from `Infrastructure/Api`); no Product→Pricing dependency. The 014C SQL semantics are unchanged. |
| Characterization tests | `tools/test-be-reorg-014c-product-list.php`: 14 vendor-free scenarios with recording test doubles for SQL/PBAC branching, context identity, authorization-before-count, fallback, errors, serializer. Existing real DB test: `tests/unit/P1Api050ProductSqlPaginationTest.php` (15 tests; `UNIT-STANDARD` suite) protects actual MariaDB SQL snapshot, relationship and PBAC. |
| Runtime gates | Source-only tests: PASS as reported per change. MariaDB/Shield `UNIT-STANDARD`, API contract/404–403, SQL EXPLAIN, p95 and concurrency: `BLOCKED_ENV` in the distributed ZIP, **must be run on guarded test DB before acceptance**. SQL/parity and performance cannot be certified by fakes. |
| Release/rollback | Revert only BE-REORG-014C code+test+MDR patch; API-050 original list body is in v1.2.2 baseline, no DB rollback required. Keep 014C separate from any future Pricing/COMBO+AUTO changes. |
| Lifecycle / conformance | Interim central MDR `DRAFT`; Product overall `NOT_VERIFIED` (candidate architecture standard, security/runtime approval still pending) |

## Price List Query: BE-REORG-014D retrofit slice

| Field | Implementation / verification target |
|---|---|
| Use case / API surface | Existing API-050 `GET /api/v1/price-lists` / `listPriceLists`: `CONTRACT_READY` per current API baseline; no new endpoint, action, OpenAPI, error mapping or response changes |
| Business / technical data owner | `Pricing` owns `price_lists` and `product_price_list_items` (working ledger; DBD owner confirmation pending). `Modules/Pricing/PriceList/Application/PriceListQueryService::list` now owns **collection read orchestration only**, not a newly approved inter-module public method. |
| Compatibility | `Api050ProductCatalogService::listPriceLists` delegates without signature, constructor or route changes. `getPriceList`, create/update/set-state and items remain in API-050 to avoid crossing multiple use cases in one patch. |
| Authorization and scope | Existing `price_list.view` on persisted `ORGANIZATION` owner/state, base permission and eligible assignment lanes remain in `AuthorizationService`. `PriceListAuthorizedBucketCompiler` groups by **byte-exact** `(organization_id, status)`; missing/unsafe projection returns `null`, triggering original per-row PBAC. Client organization filter is not authoritative scope. |
| PBAC implementation boundary | `Pricing/PriceList/Application/Ports/PriceListListAuthorizationPort` is an **internal read-only consumer port** implemented by `Bootstrap/PriceListListAuthorizationAdapter`. Only existing `AuthorizationService` and `PriceListAuthorizedBucketCompiler` decide visibility. No Pricing→legacy Authorization import or recursive/privileged provider read seam is added. No `skipAuthorization` flag. |
| Snapshot / transaction | `readSnapshot`: existing `PriceListRepository::withConsistentListSnapshot` opens a short REPEATABLE READ transaction for the SQL branch (`transDepth === 0` only). Bucket collection, authorized COUNT/page and per-row recheck are in the same snapshot/batch/`OperationContext`/connection; no write transaction or new lock. If unsafe/non-BaseConnection/nested, use original full-list fallback. |
| SQL and pagination | Unmodified SQL body in Pricing-owned `Modules/Pricing/PriceList/Application/SqlPriceListListSource`, repository, `CommonListQueryAdapter` and allowlist (`q`, `code`, `name`, `organization_id`, `status`; sort `id`, `code`, `effective_from`, `name`, `organization_id`, `priority`, `status`). Exact Owner/State predicates before COUNT/page; `null` buckets cause full-list PBAC, `[]` means proven zero. Recheck false/DomainException/malformed page falls back **before emitting results**. Priority negative and noncanonical owner (`'011'`) retain PHP comparator/equality path. |
| Serialization / errors | Keep raw `effective_from`/`effective_to`, signed priority, `page`, `page_size`, string `total`, field names, original `DomainException` code/status mapping via existing HTTP adapter. Read-only list does **not** calculate DECIMAL amounts or alter Pricing resolution. Approved HTTP status review remains open per v1.1.1. |
| Connection and compatibility | Deferred DB resolver is intentional: legacy `listPriceLists` parsed/validated query **before** opening `db_connect()`; malformed filter/sort must still fail as request error first. Facade reuses existing `queryAdapter`, `priceLists` injection, lazy Authorization/OperationContext. SQL batch uses one context; fallback retains independent legacy operation capture on each row. |
| Characterization and DB tests | `tools/test-be-reorg-014d-price-list.php`: 16 vendor-free branch/authorization/parity scenarios. Existing MariaDB/Shield `tests/unit/P1Perf008PriceListSqlPaginationTest.php` tests real SQL bucket/Unicode priority/differential and API-050 facade; test is in guarded **NO-DROP**, not UNIT-STANDARD. Run both gates after backup and test DB verification. `tests/unit/P1Api050ProductCatalogTest.php` preserves 26 API-050 endpoints. |
| Proof and open gates | `test-be-reorg-014d-price-list.php`, `architecture-scan.php`, `test-architecture-conformance.php`, `test-be-reorg-014c-product-list.php` and source-only boundary gates should pass after patch. PHPUnit/Shield/MariaDB and performance `EXPLAIN`/p95 are `BLOCKED_ENV` / `NOT_RUN` in source ZIP; **never** label them PASS from recording doubles. |
| Rollback | Code-only revert of 014D patch; no schema migration or data rollback. Do not touch Product/Numbering `KIR-001`. No approval of new Pricing→Product public entrypoints occurs in 014D. |
| MDR / conformance | Interim central MDR `DRAFT`; Pricing conformance `NOT_VERIFIED`, pending approved contracts and real DB/API/benchmark verification |

## Unresolved architecture and contract reviews

- **Legacy guards:** existing guard emits 7 warnings: three Product compatibility names (`ProductImageService`, `ProductImageStorage`, `ProductLookupService`), two `WF_TRANSITION_REQUIRED` localization warnings for `Api010` (review if per-class catalogs are required by approved error contract), and two `NUM_IDENTIFIER_COLLISION` localization warnings for `AutoNumberingIdentifierCollision` (key exists in `AutoNumberingConfiguration` vi/en, possible detector false positive). None have been silently removed or certified as `LEGACY_APPROVED`.
- **Public read boundary:** Pricing currently uses `ProductRoleService::{findById,getRole,isSelectable}`, `ProductUomService::{requireActiveBaseUom,validateSellingUom}` and `ComboExpansionService::expand`. These are source-observed candidates, **not** approved public API for future consumers.
- **`KIR-001`:** AUTO Numbering allocator rejects nested transactions, while COMBO create can open an outer transaction. Do not change opener, callback or lock order without ADR + MariaDB/Shield integration + concurrency matrix.
- **OperationContext:** `Api050::operation()` may recapture evaluation time during a multi-step create. Add same-object/same-time characterization before changing; do not introduce a new context manager.
- **Runtime bootstrap / data ownership:** `ARCH-05` and dynamic SQL verification remain open. `ARCH-07` JSON report can feed CI but no CI pipeline was modified in this slice.

**Status legend:** Conformance = `COMPLIANT / LEGACY_APPROVED / GAP_TO_FIX / NOT_VERIFIED`; test gate = `PASS / FAIL / NOT_RUN / BLOCKED_ENV / N/A`. Do not confuse architecture proposal with current compliance evidence.

## BE-REORG-014E — API-050 orchestration inventory (non-mutating checkpoint)

- Source-observed audit: [`api050-orchestration-audit.md`](api050-orchestration-audit.md); operation/route/ownership registry: [`api050-orchestration-inventory.json`](api050-orchestration-inventory.json). Both are **DRAFT**, not approved public API or data ownership contracts.
- Vendor-free source gate: `php backend/tools/test-be-reorg-014e-api050-audit.php`. Route/OpenAPI/controller compatibility and KIR-001 source markers are checked; no DB, PBAC runtime, API HTTP or SQL behavior is modified or certified by this gate.
- Snapshot contains **26 API-050 operations**: **19 Product** and **7 Pricing**; Product List and Price List list-query orchestration were already delegated in **014C/014D**. The **other 24 HTTP operations** are categorized, not automatically candidates for 24 new services.
- `KIR-001` (COMBO + AUTO nested transaction), `CXT-001` (one OperationContext per command), `LIST-001` (Product Group/UOM PHP materialization), `VIEW-001` (read-after-write authorization and error precedence) remain separate review items. No transaction, SQL, API, schema, or controller change in 014E.
- **Next approved-scope proposal (not auto-authorized):** 014F Product UOM list ownership, one boundary per PR with behavior/parity tests; 014G Product Group list follows. Product/Numbering transaction remediation requires its own ADR/POC/runtime matrix.
- Standard v1.1.1 stays `PROPOSED BASELINE`; root modules stay `NOT_VERIFIED` until supported by approval and appropriate integration/runtime evidence.


## Product UOM List Query: BE-REORG-014F retrofit slice

| Field | Implementation / evidence |
|---|---|
| Existing HTTP operation | `GET /api/v1/product-uoms` / `Api050ProductCatalogService::listUoms()`. Existing API-050 baseline `CONTRACT_READY`; **no route/Controller/OpenAPI, output or machine-code change** |
| Business owner | `Product` → `Uom`; `Modules/Product/Uom/Application/ProductUomListQueryService::list` owns UOM list orchestration, **internal-only**, not a newly approved public inter-module contract |
| Data owner | Existing `ProductUomMasterService::listAll()` / `ProductUomRepository` read `product_uoms`. No SQL, schema, migration or write change. Business owner Product unchanged. |
| Compatibility | `App\Services\Api050ProductCatalogService::listUoms` keeps signature and `queryAdapter`/injected master behavior. Controller and 26 API operations remain unmodified. |
| Authorization | `product_uom.view` at authoritative Resource Domain MASTER/GROUP (existing PBAC). `requiresRowStateAuthorization('product_uom')` still checks lifecycle then PolicyRepository active state policy. State case uses `decision` on persisted row **before** page and total; non-state case checks existing base `authorize`; exceptions propagate unchanged. |
| Read port | `Modules/Product/Uom/Application/Ports/ProductUomListAuthorizationPort` is Product-internal with 3 methods. Bootstrap `ProductUomListAuthorizationAdapter` binds existing facade callbacks; no Product canonical → legacy Authorization import. No privileged PBAC read bypass and no new context manager. |
| Operation context / query semantics | No new `OperationContext` creation in module. Facade callback retains **per-row** `operation()` capture for state, one call for base; CXT-001 remains separately tracked. Full PHP materialization, allowlisted `code/name/status`, default/stable `id` sort, pagination and `total` string from existing `CommonListQueryAdapter` remain identical. |
| Transaction | `N/A` — read-only, no transaction opener/snapshot added; no Numbering, Workflow mutation or COMBO changes. READ SQL performance optimization explicitly deferred. |
| Tests | `backend/tools/test-be-reorg-014f-uom-list.php`: vendor-free frozen old-algorithm parity, actual facade with PBAC/Workflow recording doubles, fail-closed/errors, filters/search/sort/page, resource and context. `backend/tools/test-be-reorg-014e-api050-audit.php` updated to guard delegated ownership + 26 routes, all other source-only architecture/014C/014D gates retained. |
| Runtime and performance | In supplied ZIP: MariaDB/Shield `UNIT-STANDARD` and `NO-DROP` = `BLOCKED_ENV` (vendor and DB unavailable); query count/p95/EXPLAIN = `NOT_RUN`. Run both guarded suites on `erp_test`, verify backup and grant-check warning, then performance baseline on representative dataset if claiming no regression. Do not equate doubles with runtime PBAC proof. |
| Rollback | Code-only reverse 014F patch on top of 014E (and previous 001/014C/014D). Original list body preserved in Git baseline / characterization, no DB rollback. |
| MDR / conformance | Central interim `DRAFT`; Product root `NOT_VERIFIED` pending approved MDR/contracts and required runtime/performance evidence. Architecture Standard v1.1.1 remains `PROPOSED BASELINE`. |

## Product Group List Query: BE-REORG-014G retrofit slice (22/10/2026)

| Field | Implementation / evidence |
|---|---|
| Use case / API | Existing `GET /api/v1/product-groups` / `listGroups`, HTTP API contract as-is (`CONTRACT_READY` in P1 API inventory). No new route, action, API schema, error mapping or DB migration. |
| Business owner | Product → ProductGroup, persistence table `product_groups`. `Modules/Product/ProductGroup/Application/ProductGroupListQueryService::list` is an **internal Product-owned read orchestration**, not a newly approved inter-module API. |
| Legacy compatibility | `Services/Api050ProductCatalogService::listGroups()` keeps the same signature/return and binds Group service + callbacks; Controller and the remaining 25 endpoints untouched. API-050 facade remains active for Media and readback consumers. |
| PBAC / Evaluation Org | Existing `product_group.view` (MASTER/GROUP) via facade's `AuthorizationService` and authoritative persisted row. When lifecycle or active State policy applies, evaluate **every persisted Group** before `COUNT` and page. Otherwise check original base `view` gate. Errors and mandatory PBAC behavior unchanged. |
| Consumer read port | `Modules/Product/ProductGroup/Application/Ports/ProductGroupListAuthorizationPort` offers the narrow three-method State probe/base view/row decision boundary; Bootstrap adapter wraps the existing facade callbacks. No canonical Product → legacy Authorization import, no `skipAuthorization` or privileged API. |
| Query semantics | `ProductGroupService::listAll()` still fully materializes rows **before** State/PBAC/query validation. Existing `CommonListQueryAdapter` with search `code,name`, filters `code,name,parent_id,status`, sort `id,code,name,parent_id,status`. PHP comparator, exact filter strings, stable pagination, count as decimal string retained. No SQL fast path or snapshot added. |
| Hierarchy and visibility | API returns **flat rows** with unchanged `parent_id`: Group tree may have unbounded depth, but this list does not traverse, expand, prune, reparent or validate the tree. A visible child may remain visible even when its parent is denied. Group create/reparent cycle/lock invariants are unaffected. Consumer security interpretation of `parent_id` should be reviewed separately against approved API contract; 014G preserves baseline. |
| Operation / transaction | `read-only / no new transaction opener`; existing Group `listAll` repository access and per-row legacy `operation()` captures are retained. `CXT-001` one-context-per-command still pending separate decision; Numbering and `KIR-001` out of scope. |
| Verification | `tools/test-be-reorg-014g-product-group-list.php`: 34 vendor-free old/new parity and facade doubles, includes denied parent vs allowed child, deep hierarchy, exact `parent_id` filters, PBAC-before-count, errors, state policy, and response shape. `test-be-reorg-014e-api050-audit.php`: 26 routes, 4 delegated queries, guards Group/UOM source ordering. Existing MariaDB source test `tests/unit/P1Wf006StateAwarePbacAndLocationTransitionsTest::testProductGroupListFiltersStatePoliciesBeforePaginationAndTotal`. |
| Runtime gates | Vendor-free gate PASS locally. MariaDB/Shield `UNIT-STANDARD` and `NO-DROP`: `BLOCKED_ENV` in ZIP; user should run on backed-up `erp_test`. Representative large-tree p95/peak-memory/query count benchmarks: `NOT_RUN` — required before claiming performance parity or changing SQL. |
| Rollback | Revert only 014G patch on top of 014F. Original `listGroups` body is frozen in baseline/characterization. No DB or API rollback needed. |
| MDR / conformance | Central interim MDR `DRAFT`. Product root architecture `NOT_VERIFIED` pending approved contract/MDR, documented exceptions and runtime/performance evidence. Standard v1.1.1 remains `PROPOSED BASELINE`. |

## API-050 Residual / Performance: BE-REORG-014H audit (22/10/2026)

**Central MDR transitional overlay, `DRAFT / SOURCE_OBSERVED`, not module conformance approval.** See `api050-residual-014h.md` and `api050-residual-014h.json`. Snapshot after 014G: 26 ProductCatalog routes; four Product/Pricing list queries delegated; 22 remaining HTTP operations grouped as 5 owner delegated, 4 protected facade read, 8 owner mutation + readback, 3 normalized create, 1 facade `product.view` conversion read, 1 conditional COMBO create. The two non-route Product Media/Image compatibility entrypoints remain active; do not remove API-050.

**Data owners:** Product owns `product_groups`, `product_uoms`, `products`, `product_combo_items`, conversions; Pricing owns `price_lists`, `product_price_list_items`. This is an **observed interim map**, pending approved DBD/MDR review. 014H does not create a public method contract, change API `CONTRACT_READY` status, change `OperationContext`, transaction ownership or business SQL.

**Risks:** `KIR-001` P0 STOP (COMBO/AUTO transaction POC/ADR required); `CXT-001` P1 (repeat `MutationContext::begin()` capture in multi-step command); `PERF-001` P1 (`listAll()` + per-row state PBAC + PHP pagination); `COMPAT-001` P2 active media callers. Reviewable source matrix and test path: `backend/tools/test-be-reorg-014h-residual.php` (12 source-only assertions + eight negative fixtures). Synthetic PHP processor benchmark: `backend/tools/bench-be-reorg-014h-php.php`; authenticated local `erp_test` GET benchmark: `backend/tools/bench-be-reorg-014h-http.ps1` (read-only; operator DB confirmation is not DB verification).

**Gate classification:** source-only = `PASS` when actually run. Historical user 014G UNIT-STANDARD/NO-DROP = prior evidence, not re-run by 014H. MariaDB/Shield perf/EXPLAIN/query count/live HTTP p95 = `NOT_RUN` until operator tests; production p95 `NOT_RUN`. Conformance for Product/Pricing still `NOT_VERIFIED`; architecture document remains `PROPOSED BASELINE`. Rollback is code-only deletion of audit assets, no migrations.

## BE-PERF-015A — Group/UOM SQL list optimization (controlled rollout, 22/10/2026)

**Candidate/NOT_RUNTIME_VERIFIED, opt-in OFF by default.** Product owns Group and UOM list query orchestration; `Modules/Product/Lookup/Application/ProductMasterListSqlFastPath` supplies only authorized SQL COUNT/page where byte-exact equivalence to PHP list semantics can be demonstrated. `Infrastructure/Api/SqlAuthorizedBucketListSource` and `Authorization/EffectiveListBucketCompiler` support these existing MASTER resources without new Resource/Action/domain or schema. The Bootstrap adapter uses a narrow read-only Product-owned port. Active State rows are compiled from real persisted State representatives by Authorization and rechecked per returned page row; no raw Policy SQL or security bypass. Unsupported text sort/search/filter, uncertain buckets, nested transactions and injected legacy test seams retain original full-list per-row PBAC. One read snapshot / `OperationContext` for the SQL path; `CXT-001` for write commands remains open.

**Gate/evidence/rollback:** `backend/tools/architecture/api050-product-master-sql-015a.md` is the detailed SQL/PBAC/transaction and performance test record. `ERP_PRODUCT_MASTER_LIST_SQL_OPT_IN=1` is required in the PHP server to enable; unset to roll back immediately. Simulated-source gate and unchanged baseline source tests are not MariaDB/Shield authorization parity; the latter and HTTP benchmarks/EXPLAIN remain `NOT_RUN` until run and reviewed, as required by Standard v1.1.1. Historic 014H audit describes the pre-optimization observation, not a new production certification.

## API-050 OperationContext: BE-REORG-014I retrofit slice

| Field | Implementation / evidence |
|---|---|
| Boundary | Transitional `Api050ProductCatalogService`; no new module/public HTTP API |
| Problem | Multi-step mutation commands could call `MutationContext::begin()` repeatedly through `operation()`, producing different operation objects/evaluation times inside one command |
| Change | Capture one `OperationContext` at the facade command boundary and pass the same object through owner mutation, COMBO participant where applicable, and authorized response readback |
| Affected owners | Product Group/UOM/Product under Product; Price List under Pricing. No owner logic moved between modules |
| Compatibility | Existing route signatures, payloads, error codes, `productReadAccess()` Media seam and standalone reads retained |
| PBAC | Same authorization services/actions/targets; only the already-established command context is reused |
| Transaction | 014I itself made no transaction change. KIR-001-C1 is now runtime-characterized; KIR-001-C2 changes only Numbering allocator ownership semantics and remains runtime-pending |
| Verification | `tools/test-be-reorg-014i-operation-context.php`; 014H residual gate updated to fail if CXT-001 regresses |
| Runtime status | `SOURCE_FIXED_RUNTIME_NOT_VERIFIED`; MariaDB/Shield/API parity still required |

## KIR-001-C1 — Product COMBO + AUTO characterization

| Field | Implementation / evidence |
|---|---|
| Boundary | `Api050ProductCatalogService::createProduct` → `ProductMasterService::create` → Numbering allocator → COMBO composition |
| Change | Test/tooling only; no production transaction, lock, API, PBAC, schema or Numbering algorithm change |
| Runtime evidence | Operator run 2026-10-22: **6/6 PASS / 50 assertions** on guarded MariaDB/Shield |
| Result | `RUNTIME_CHARACTERIZED / OWNERSHIP_CONFLICT_CONFIRMED`; nested guard is the reproduced blocker for AUTO+COMBO |

## KIR-001-C2 — Product COMBO + AUTO ownership remediation

| Field | Implementation / evidence |
|---|---|
| Decision | Numbering allocator owns a transaction only at `transDepth === 0`; otherwise it participates and leaves physical commit/rollback/reset to the caller |
| Command owner | Product `createProduct(combo_items)` outer transaction remains unchanged |
| Production change | `Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php` only |
| Runtime matrix | 9 guarded MariaDB/Shield cases: AUTO success, empty items, standalone owner, first/existing counter rollback+reuse, PBAC rollback, collision, concurrent first-counter COMBO |
| UNIT-STANDARD | Existing NUM-004 test gains ambient-owner assertions without changing suite class/method inventory |
| Source gate | `tools/test-kir001-source-gate.php` requires own-or-participate markers and the 9-case/concurrency evidence |
| Current status | `SOURCE_READY / RUNTIME_REMEDIATION_NOT_RUN`; KIR-001 remains P0 until C2 + UNIT-STANDARD + NO-DROP runtime PASS |
| ADR/evidence | `kir001-product-combo-auto-remediation.md` |

## BE-REORG-014J — Product SQL list source ownership (internal-only)

**Source evidence / MDR status:** The Product-specific `SqlProductListSource` implementation moved out of shared `Infrastructure/Api` into the existing Product List Application folder. The reusable `AuthorizedListSource` and query parser remain generic Infrastructure. Production consumer remains `ProductListQueryService`; existing runtime PHPUnit import and 014C simulated collaborator were relocated to the new owner namespace. No new Product public inter-module method, alias, DB migration, transaction or PBAC capability. See [`be-reorg-014j-product-sql-source-ownership.md`](be-reorg-014j-product-sql-source-ownership.md).

**Source gates:** 014J actual-class vendor-free 7 checks + 014C 14 cases + architecture scanner/conformance. `P1Api050ProductSqlPaginationTest`, `UNIT-STANDARD`, `NO-DROP`, authenticated API/PBAC parity and representative SQL benchmark remain **NOT_RUN** for 014J until run by operator with `vendor` and MariaDB/Shield. Root Product conformance remains `NOT_VERIFIED`; `DINKERP-BE-ARCH-001` remains proposed.


## BE-REORG-014K — Pricing SQL list source ownership (internal-only)

**Scope:** `SqlPriceListListSource` moves from `Infrastructure/Api` to `Modules/Pricing/PriceList/Application`; `PriceListQueryService` uses the local Pricing class. The old generic location is removed; the original `AuthorizedListSource`, `CommonListQuery` and the shared list adapter remain reusable. No new interface, alias, public module API, DB schema, SQL algorithm, PBAC rule, API-050 signature or transaction owner. The runtime test `P1Perf008PriceListSqlPaginationTest` and 014D fake move their imports/namespaces to the owner; the single NO-DROP source fingerprint is explicitly re-reviewed/re-pinned without loosening safe-lane selection. All implementation class bodies after `final class SqlPriceListListSource` are byte-identical to pre-014K.

**Evidence/status:** `tools/test-be-reorg-014k-price-list-sql-owner.php` (9 real-source/recording-builder assertions) + `tools/test-be-reorg-014d-price-list.php` (16 orchestration vectors), architecture scan/conformance. **SOURCE_VERIFIED / NO-DROP RUNTIME_PENDING** for 014K in this source-only package. Operator must back up `erp_test` and run both unit cohorts; specialized authenticated HTTP, EXPLAIN/p95 and formal Pricing module conformance remain unverified. No changes to `observed-v1.2.2.json`. Details/rollback: [`be-reorg-014k-price-list-sql-source-ownership.md`](be-reorg-014k-price-list-sql-source-ownership.md).

## BE-REORG-014M — Authorization Contact PRIMARY scope rule (22/10/2026)

**Conformance:** `DRAFT / SOURCE_VERIFIED / MARIADB_SHIELD_HTTP_PENDING`. Review `be-reorg-014m-employee-primary-scope.md` for the per-resource resolver, Contact-owner batch and conservative `EffectiveOrganizationScopeIndex` inventory. **One** duplicated canonical primary-eligibility check was consolidated into `EmployeeContactScopeResolver` and reused by `OwnerAwareListBucketCompiler`, which already depended on that class's error semantics. No interface, DB query, dependency edge, cross-module write, API payload, actor assignment rule or transaction was changed. Both legacy query entrypoints and the 014L two-method privileged read port remain in place. Database/PBAC differential validation and HTTP Contact pagination are not certified by this source-only slice.

## BE-REORG-014N — Authorization Organization scope signature reuse (22/10/2026)

**Conformance:** `DRAFT / SOURCE_VERIFIED / MARIADB_SHIELD_HTTP_PENDING`. The read-only `EffectiveOrganizationScopeIndex` in Authorization retains the same legacy Organization/EmployeeStructure/Resource/Policy sources and immutable projection inputs. Only repeated signature traversal is eliminated through per-instance memoization; the **original signature strings, null fail-closed behavior, UTC effective assignment date, approved PBAC buckets, COUNT-before-LIMIT, DB schema and operation/transaction boundaries remain unchanged**. No new module dependency, public port, SQL query, wrapper or cross-owner write. See [`be-reorg-014n-scope-signature-reuse.md`](be-reorg-014n-scope-signature-reuse.md) for full evidence, synthetic-only performance caveat, 8-case source gate, DB parity requirements and code-only rollback.

## BE-PERF-015B — Product default-ID and sortable list (23/10/2026)

**Scope:** Product only; no API route, Product SQL/PBAC predicate, Action-hint timing, transaction ownership, schema or cross-module dependency changes. The public `product_id` sort alias is canonicalized to persisted `id`, preserving legacy `sort=id` and default id ASC. Product UI exposes asc/desc options for ID, full displayed name, default price, product code, SKU, type and status. Decimal prices use a SQL COUNT/page fast path when safe and exact non-float PHP comparison in projection/fallback; child full-name sort uses an on-demand batched parent lookup. Other sorts retain Unicode/numeric-aware PHP ordering, with narrow columns transferred only for actual search/sort keys.

**Evidence/status:** `tools/test-be-perf-015b-product-sort.php` (9 source-level behavior checks), `frontend/src/features/catalog/__tests__/productSortOptions.spec.ts` (Vitest runtime pending), existing 014C/014J/016A gates, architecture scanner/conformance and static lint. **SOURCE_VERIFIED / MARIADB_SHIELD_HTTP_FRONTEND_RUNTIME_PENDING**. See [`be-perf-015b-product-sort.md`](be-perf-015b-product-sort.md) for performance limitations, PBAC/snapshot invariants, local runtime commands and rollback notes. Legacy `sort=product_code` still projects matching keys and sorts in PHP; no unverified MariaDB collation rewrite was introduced.
