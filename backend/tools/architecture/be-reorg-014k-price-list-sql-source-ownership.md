# BE-REORG-014K — Pricing SQL list source module ownership

**Authority:** `docs/DinkERP_Backend_System_Architecture_Standard_v1.1.1.md` §§2.3, 5.2, 6.4; `docs/DinkERP_Backend_Architecture_Blueprint_Lean_v1.2.2.md` §§4, 5, 9–11. These are **proposed architecture guidance**, not independent approvals of API/DB/PBAC contracts.

**Status of distributed patch:** `SOURCE_VERIFIED / MARIA_DB_SHIELD_HTTP_PENDING`. Internal namespace-only move; no new public cross-module API, no database change. The operator's prior 014J unit run is **baseline evidence only**, not a runtime verification of 014K.

## Finding and ownership decision

The shared `Infrastructure/Api/SqlPriceListListSource.php` was effectively Pricing business code: it knows the `price_lists` table, authorized `(organization_id, status)` buckets, byte-exact `HEX(status)` predicates, nonnegative priority guard and SQL count/page rules. Its single production caller is `Modules/Pricing/PriceList/Application/PriceListQueryService`. Generic `AuthorizedListSource`, `CommonListQuery`, `CommonListQueryAdapter` and `AuthorizedArrayListSource` are genuinely reusable and stay in `Infrastructure/Api`.

Move the implementation **unchanged beyond namespace/imports** to `Modules/Pricing/PriceList/Application/SqlPriceListListSource.php`, beside its query service. Retarget the two source-observed test consumers: `tests/unit/P1Perf008PriceListSqlPaginationTest.php` (real MariaDB/Shield, NO-DROP cohort) and the 014D vendor-free SQL execution fake. Because NO-DROP pins the exact source of `P1Perf008PriceListSqlPaginationTest`, its one-import-only diff was reviewed and its sole entry in `unit-no-drop-inventory.php` re-pinned from `22eb1bf3…` to `02fc97aa…`; **no class promotion, test-method count, grant policy or safety cohort change** was made. No old-namespace alias is introduced: the source inventory found no other consumers, and this implementation was not declared a public module API. An external caller importing the old internal PHP FQCN would require a manual import update.

## Invariants and gates

| Boundary | Decision / preservation |
|---|---|
| Table/data owner | Pricing owns `price_lists`; SQL reads only that table; this change adds no cross-owner read/write |
| Entry point | Existing API-050 `listPriceLists`, `GET /api/v1/price-lists`, unchanged facade, route, parameter and response projection |
| PBAC | Existing `price_list.view` via `AuthorizationService`/`PriceListAuthorizedBucketCompiler`/Bootstrap adapter; approved Owner/State predicates before SQL COUNT/page, byte-exact status under case-insensitive collation; row recheck and full-list fallback remain |
| SQL eligibility | ID or (priority, ID) sort only; text search, Unicode sort, noncanonical owner filters and signed negative priority retain legacy PHP path |
| Snapshot and error | Existing `PriceListRepository::withConsistentListSnapshot` transaction/operation context unchanged; fail-closed query/DB error paths unchanged |
| Data API | No status, 403/404, total type, schema, migration/index, cache or precision change |
| Compatibility | Only source-observed owner and internal tests import class; no generic helper or new interface/factory/adapter needed |
| Rollback | Reverse 014K Git patch; no migration or data rollback; historical architecture snapshot remains untouched |

**Source verification:** `php backend/tools/test-be-reorg-014k-price-list-sql-owner.php` runs **nine** actual-production-class scenarios against a recording fake CI4 builder: correct module location, exact bucket/Owner/State COUNT/page, OR grouping, filters, proven-deny zero, fallback eligibility, negative priority probe, stable page tie-breaker and malformed/failed SQL fail-closed. No DB connection or SQL is made. `php backend/tools/test-be-reorg-014d-price-list.php` retains 16 simulated orchestration/PBAC cases; `architecture-scan.php`, `test-architecture-conformance.php`, 014J, 014C, 014E–014I and KIR-001 source gate remain required.

**Operator runtime verification required:** from a clean Git worktree, after backing up and verifying `erp_test` is an isolated MariaDB database, run `backend/tools/test-unit.ps1 RUN UNIT-STANDARD` and `backend/tools/test-unit.ps1 RUN NO-DROP`. `P1Perf008PriceListSqlPaginationTest` belongs to NO-DROP; it checks real SQL equivalence including case-sensitive State, organization restrictions, negative priority fallback and API-050. NO-DROP grants can be skipped by operator option, so successful attestation does not make the DB grant gate PASS. Additional authenticated HTTP endpoint parity, EXPLAIN/query count/p95 and approved DBD/MDR conformance remain **NOT_VERIFIED** in this source package without `vendor` or MariaDB.

## Out-of-scope follow-ups

Authorization–EmployeeStructure dependency governance, remaining API-050 facade responsibilities, Product/Pricing public Product read capability approval, dynamic SQL auditing and database tuning require separate evidence/slices. The 014K source relocation is **not** a reason to alter 014J, KIR-001 transaction ownership or rebaseline scanner observations.
