# BE-PBAC-016A — Product list/detail advisory action permissions

**Baseline:** BE-REORG-014N (including KIR-001-C2.2); additive Product response contract.
**Status:** SOURCE_VERIFIED / DB_SHIELD_HTTP_RUNTIME_PENDING.
**Scope:** Product only. No modifications to Product VIEW bucket SQL, pagination, schema, PBAC precedence or mutation authorization.

## Problem and decision

Product List previously filtered VIEW correctly (grouped persisted-State PBAC to SQL WHERE when proven), but Product List, editor and detail UI used only a user's `base_permissions` to expose `edit`/`setstate` controls. Base permission is necessary but is not a record-State PBAC decision. Therefore users could see actions that the server later denied, or be misled when an operation was allowed for one State but denied for another.

**Decision:** Product List and Product detail GET append `allowed_actions: {edit:boolean, setstate:boolean}` to already authorized rows. Other Product response operations (CREATE/PATCH/state mutation, lookups) remain unchanged; in the OpenAPI Product schema this field is optional. No Product DELETE action is introduced.

1. Existing `ProductListQueryService` retains VIEW before COUNT/page, exact State bucket filtering, structure validation, snapshot, authorized page hydration and per-row VIEW recheck. On the successful, already authorized page it calls `ProductListAuthorizationPort::withAllowedActions()`.
2. `ProductListAuthorizationAdapter` invokes the **existing** `AuthorizationService::authorize` for `edit` and `setstate` on a trusted persisted row. `setstate` gets its **FROM-State ID** from `ResourceWorkflowStateResolver::persistedStateId('product', $row)`; never an HTTP or frontend state hint. `edit` gets no explicit State override. `setstate=true` is **not** a transition-options guarantee: the workflow endpoint still decides legal destinations/actions.
3. A Product Resource metadata check is required before reusing decisions across multiple rows with exactly byte-identical State. This is safe only when the Product resource is proven `MASTER` (GROUP scope, no row identity in PBAC inputs). Unknown or changed Resource domains fall back to independent evaluations for each page row, never a guessed scope. Cache scope: **method invocation only**; never across pages, requests or actors. At most `2 × distinct returned State values` authorizations for proven MASTER lists, not `2 × total catalog rows`.
4. VIEW-only batch snapshot is closed **before** evaluating non-VIEW PBAC actions (the decision engine deliberately invalidates VIEW caches on non-VIEW calls), but still inside the same existing SQL read snapshot. Empty pages do not trigger action checks.
5. The frontend hides mutation UI unless `allowed_actions?.edit === true` / `allowed_actions?.setstate === true`; absent data is fail-closed. It does not create `product.delete`. The shared `TableRowActions` accepts an explicit `stateResource` so a server-authorized row-state action need not be re-derived from stale `base_permissions`. Other resource usages retain their previous permission checks. Product CREATE still uses Base Permission and CREATE+COMBO retains its existing edit precondition.
6. **Security boundary:** `allowed_actions` is advisory UI state only. Every PATCH/transition/image/COMBO mutation must still read trusted current data and reauthorize on the backend. No permission decision may be sent back by a client to bypass backend checks.

## Contract example

```json
{
  "data": [
    {"id": "123", "product_code": "SP001", "status": "ACTIVE",
      "allowed_actions": {"edit": false, "setstate": true}}
  ],
  "meta": {"page": 1, "page_size": 15, "total": "1"}
}
```

The snippet omits other required Product fields for readability. Backend responses are **additive**. `allowed_actions` is present on each Product returned by `GET /api/v1/products` and `GET /api/v1/products/{id}`. It is absent on other product structures until explicitly supported; consumers must handle missing flags conservatively.

## Verification gates

From repository root:

```powershell
php backend/tools/test-be-pbac-016a-product-actions.php
php backend/tools/test-be-reorg-014c-product-list.php
php backend/tools/test-be-reorg-014j-product-sql-owner.php
php backend/tools/architecture-scan.php
```

The source characterization uses the **real adapter** with PBAC/resource-metadata/State fakes. It proves separate action results, trusted FROM-State, no between-call caching, MASTER-only memoization, non-MASTER per-row fallback, fail-closed malformed rows/infrastructure failures and frontend/contract wiring. It cannot prove MariaDB/Shield/HTTP parity, frontend build or production p95.

For local runtime: backup `erp_test`, then run UNIT-STANDARD, KIR-001, NO-DROP in the guarded runners already present. Separately, using an authenticated browser session, compare `GET /products` and `GET /products/{id}` action decisions against the direct AuthorizationService for the same persisted State, actor and evaluation time, with ALLOW, DENY and ABSOLUTE_DENY fixtures. Check that hidden action controls never weaken server-side PATCH/state authorization; revalidate after a State/policy change. Benchmark action PBAC calls / SQL query count for 15 rows across 1, 2 and many States before claiming latency improvement.

**Known limits:** State-bucket VIEW inference still uses persisted State samples and can fall back when unsafe. The existing `sort=product_code` projected-key PHP pagination is **not** modified. Full HTTP/PBAC parity, frontend `pnpm` type-check/build, and throughput with many States remain runtime-pending in the supplied package (no Composer vendor or frontend dependencies). Frontend v2 *historical refactor* hash manifests are intentionally not rewritten by this feature patch: they represent earlier byte-identical states and should only be superseded through an explicit reviewed checkpoint.

## Rollback

Revert this patch. No migration or data transformation is required. Reversion removes advisory response fields and UI gating; existing backend mutation permissions and Product View SQL remain intact.
