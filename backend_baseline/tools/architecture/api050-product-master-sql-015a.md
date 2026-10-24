# BE-PERF-015A — Controlled SQL + PBAC optimization for Product Group / UOM

**Design record:** DRAFT / OPT-IN CANDIDATE (not a production-approved rollout). Based on Architecture Standard v1.1.1 (PROPOSED BASELINE) and source up to BE-REORG-014H. **API/DB:** No route, payload, schema, migration, Resource/Action or mutation change. `KIR-001` and `CXT-001` command mutation remain out of scope.

## Ownership and runtime boundaries

- **Business and SQL owner:** Product module; `product_groups` and `product_uoms`. The existing `ProductGroupListQueryService` and `ProductUomListQueryService` optionally delegate to `Modules/Product/Lookup/Application/ProductMasterListSqlFastPath` (shared capability under the same root). SQL predicate implementation reuses `Infrastructure/Api/SqlAuthorizedBucketListSource`, augmented ONLY for these resource types; `EffectiveListBucketCompiler` continues to ask AuthorizationService for each real persisted State sample, after checking canonical `MASTER` resource metadata.
- **Security:** `product_group.view` and `product_uom.view` use existing Base Permission, employee/assignment eligibility, Resource Domain MASTER / GROUP, Policy lane precedence and persisted native/Workflow State. The Bootstrap SQL adapter is read-only; no direct Policy joins that shortcut PBAC. Its `withReadOnlyBatch` caches read-only policy dependencies for ONE operation; it is not a cross-request permission cache. `System Admin` is not bypassed.
- **No State-scoped policy/lifecycle:** base `view` check **always** happens, including empty lists. The two resources alone can pass `null` as an approved-all-State SQL source, never as a general authorization primitive. `[]` remains deny-all for State-mode. Both SQL COUNT and page use the same predicates.
- **State-sensitive:** compiler groups existing rows by byte-exact `HEX(status)`; at most 64 buckets; checks one representative **persisted** row with a single `OperationContext`; then SQL authorized COUNT/page; rechecks every **returned** row against PBAC. Recheck denial/error aborts SQL result and falls back to the full per-row path (never serves a partial/inconsistent page). Unknown domain, invalid representatives or more than 64 buckets likewise trigger fallback; other unexpected compiler errors propagate. Do not turn an exception into empty success.
- **Snapshot:** short `SET TRANSACTION ISOLATION LEVEL REPEATABLE READ` plus CI `transBegin/transCommit/rollback`, with same connection for SQL count/page/State sample/PBAC composition. If `transDepth > 0`, use legacy path; no nested transaction. This is read snapshot consistency, **not** a promise of instant PBAC revocation across concurrent commits.
- **Query guard:** exact `id` or `-id` sort only, no `q`, and only `status` exact filter or (Group) canonical `parent_id` numeric/NULL. ID, status and parent filters preserve strict PHP comparison semantics with `HEX(status)` and exact NULL handling. Free text, other sorts, code/name filters, `parent_id=02`, invalid queries, nested connection and uncertain State remain the original `listAll -> row PBAC -> canonical PHP paginate` path. Visible children are not pruned or reparented when a parent is denied.

## Controlled rollout (default OFF)

The exact feature flag `ERP_PRODUCT_MASTER_LIST_SQL_OPT_IN=1` is required in the PHP **server process** to activate this fast path. Unset/other values keep 014F/014G behavior; custom injected Authorization/Group/UOM collaborators also keep the fallback. Do not enable this flag for production based on simulated tests alone. When running on XAMPP the PHP CLI environment variable does not necessarily set Apache/FPM environment variables: confirm HTTP server propagation separately.

A read-only authenticated OFF/ON comparison utility is included at
`backend/tools/test-be-perf-015a-http-parity.ps1`. Run `-Mode BASELINE`
with the feature flag OFF, restart the isolated PHP web process after enabling
`ERP_PRODUCT_MASTER_LIST_SQL_OPT_IN=1`, run `-Mode CANDIDATE`, then `-Mode COMPARE`
to compare 19 deterministic GET response hashes (it removes only the varying
`meta.request_id`). It does **not** itself verify the web server database or
capture concurrent changes; use the *same* account, policy, data and locale.
Secrets belong in existing `ERP_014H_AUTH_COOKIE` or `ERP_014H_BEARER_TOKEN`
process environment variables; no raw response is written to disk.

**Required before enabling in any shared environment:**

1. On backed-up loopback `127.0.0.1:3306/erp_test`, run `UNIT-STANDARD` and `NO-DROP` source/runtime gates; remember NO-DROP grant check is skipped by configuration.
2. Under the flag ON in an isolated test web process, execute authenticated differential HTTP requests vs flag OFF for both endpoints: no policy, ACTIVE vs INACTIVE/lowercase, absolute deny, inactive resource, Base Permission denied, missing/inactive employee/assignment, Group visible child whose parent is denied, empty results, `page 1/2`, `page_size`, `-id`, `status` and `parent_id` NULL/canonical values, Unicode/code/name/invalid query fallback. Compare **status, error code, items, meta.total, sorting, parent_id and hidden row counts**. Maintain exact actor/organization and a frozen dataset between runs.
3. Test snapshot consistency, concurrent update/authorization revocation semantics and rollback/commit in MariaDB/InnoDB/Shield. Confirm the same DB connection for participants and ensure security decisions never re-enter Authorization read adapter recursively.
4. Record representative real `query_count`, DB time, memory, request p50/p95, and `EXPLAIN` on `COUNT` and ordered PAGE on 100/1,000/10,000-row (or practical equivalent) datasets with partial visibility. Measure both OFF and ON with identical code/environment and no other load. Identify slow fallback coverage separately; no SLO is assumed.
5. Owner review of enabled source before production. Roll back instantly by unsetting `ERP_PRODUCT_MASTER_LIST_SQL_OPT_IN`, not by database migration. A code-level patch revert remains available.

## Evidence and test status

| Gate | Status after patch construction | Meaning |
|---|---|---|
| `tools/test-be-perf-015a-master-lists.php` | Run locally, vendor-free | Differential fake builder, SQL plan eligibility, before-COUNT bucket visibility, result shape, fallback, nested transaction, recheck, errors; NOT MariaDB |
| Existing 014C/014D/014E/014F/014G/014H and architecture source gates | Run locally | Compatibility / previous behavior, flag OFF |
| REAL MariaDB/Shield SQL/PBAC parity | NOT_RUN | Must run in backed-up `erp_test` with flag ON |
| Authenticated HTTP p50/p95, EXPLAIN/query count | NOT_RUN | Must be measured by operator |
| Production conformance | NOT_VERIFIED | Feature flag intentionally defaults OFF |

**Risk note:** A fast-path change can alter the precedence between a database read error and a permission/validation error, since old `listAll()` read all rows before checking PBAC. Test the approved error precedence explicitly before enabling; default-OFF preserves historical behavior. Also audit any unexpected SQL exception mapping in the enabled path; do not infer API success from scanner PASS.
