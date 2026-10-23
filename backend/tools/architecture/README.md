# DinkERP backend — staged architecture baseline (BE-ARCH-001)

**Standard:** `DINKERP-BE-ARCH-001` v1.1.1 — `PROPOSED BASELINE` (not an approved UR/FRS/DBD/API contract). This is the first incremental implementation, **not** a certification that 12 modules are compliant.

## Run without Composer/DB

From repository root:

```powershell
php .\backend\tools\architecture-scan.php
php .\backend\tools\architecture-scan.php --json
php .\backend\tools\test-architecture-conformance.php
php .\backend\tools\test-architecture-organization-tree.php
```

`architecture-scan.php` returns **exit 0** only when the monitored source has no **new** forbidden imports, unapproved cross-module imports, new legacy implementation files, detected direct cross-owner fluent SQL writes, unregistered root modules or module cycles. Exit **2** means a new violation. JSON mode provides `rule`, `path`, `message`, and open verification gates.

**An exit 0 is `PASS_NEW_EDGE_GATE_ONLY`, not `COMPLIANT`.** Entries in `observed-v1.2.2.json` are historically observed reference pairs, **not** approved exceptions. They are recorded at exact `source_file|fully_qualified_target` granularity. A new source file referencing the same old class is blocked; removing an existing reference is allowed and reported. No directory-wide waiver exists. Do not automatically update the observed snapshot to silence a new violation.

## Registers (review status)

- `architecture-policy.json`: 12 allowed root modules; provisional 31-table business/technical ownership ledger extracted from P1 migrations; Product methods used by Pricing are **CANDIDATE_OWNER_REVIEW**, not newly approved public API. The 31 tables exclude framework/Shield tables not defined by these application migrations. The ledger needs DB owner confirmation.
- `observed-v1.2.2.json`: 249 observed code references, 75 pre-existing flat Services/Repositories classes and 0 source-level module cycles. Legacy observations remain subject to review. This is a **snapshot**, not a permission list.
- `compatibility-inventory.json`: 30 `class_alias` references plus 2 Product media wrappers; owner and removal criteria are suggested but full caller inventory and owner approval are still missing. These are **not `LEGACY_APPROVED`** merely because they exist.
- `module-design-inventory.md`: central interim module design record / conformance register. The dedicated `DinkERP_Module_Design_Record_Template_v1.1.1.md` is referenced by Standard but not part of the uploaded sources; do not invent a replacement template or mass-create empty `MODULE.md`.

## Detection boundaries

The scanner uses PHP tokenizer-qualified class names and a deliberately limited direct SQL builder signature (`->table('literal')->...->insert/update/delete`). It can flag specific paths but **cannot** prove public **method** authorization, reflection-driven calls, constructor/runtime recursion, dynamic SQL or which DB connection participates in a transaction. These require reviewer/DB/PBAC/concurrency evidence (`ARCH-01/03/05`). Imports in docblocks/strings are ignored. A class-level approved public entrypoint must have a reviewed method list; scanner currently enforces class and consumer but *not* the method called.

No migration, endpoint, permission, PBAC or transaction owner is modified by BE-ARCH-001. Runtime gates on the supplied ZIP remain `BLOCKED_ENV` because `backend/vendor` and a test MariaDB/Shield stack are unavailable.

## Planned activation milestones

1. Architecture Owner approves baseline and resolves old guard warnings. Until then `PROPOSED`, no automatic blanket waivers.
2. Confirm 31-table ledger against approved DBD, add missing Shield/technical ownership and review direct/dynamic mutations.
3. Complete public **class+method** register for consumers, including Product → Pricing and platform read-only seams. Require runtime non-recursive PBAC adapter test before switching wiring.
4. Run the scanner + its 19 mutation fixtures in CI and publish JSON artifact; confirm its false-positive rate before making it required.
5. Run PHPUnit/MariaDB/Shield/concurrency and compare API parity on affected use cases. Do **not** use static PASS to sign off a write/query change.

### 014C / next slices

The first low-risk code movement is `Api010::tree()` → `Modules/Organization/Application/VisibleOrganizationTreeProjector`. **Only** the already authorized result is transformed; no parent lookup, authorization or pagination changes. Follow with Product SQL list → Product Query Service only after preserving exact COUNT/PBAC/snapshot behavior and collecting runtime benchmark evidence. The COMBO + AUTO transaction risk (`KIR-001`) remains **open** and is not part of this patch.

### BE-REORG-014H — API-050 residual/performance checkpoint

`api050-residual-014h.json` is a **source-observed** 22-operation residual inventory derived from the 014E 26-route manifest after 014G. Its 12 source checks and eight negative fixtures are run with:

```powershell
php .\backend\tools\test-be-reorg-014h-residual.php
php .\backend\tools\bench-be-reorg-014h-php.php --self-test
```

For large synthetic datasets and a guarded authenticated **read-only** local HTTP timing run, see `api050-residual-014h.md`. Synthetic PHP latency **is not** server/MariaDB p95. HTTP timing must confirm an isolated backed-up `erp_test` connection separately; the script does not inspect database configuration. 014H changes no application runtime code and does not authorize SQL/PBAC/Numbering transaction refactors.

### BE-REORG-014I — API-050 command OperationContext

API-050 mutation/readback paths now capture one `OperationContext` per facade invocation and reuse it through mutation participants and response authorization. This is a source-level fix for `CXT-001`; runtime MariaDB/Shield/API parity remains unverified. `KIR-001` is intentionally unchanged.

```powershell
php .\backend\tools\test-be-reorg-014i-operation-context.php
```

See `api050-operation-context-014i.md` for scope, invariants and rollback.

### KIR-001-C1 — Product COMBO + AUTO runtime characterization

C1 is complete. Operator runtime evidence on 2026-10-22 reported **6/6 tests PASS / 50 assertions** on guarded MariaDB/Shield, confirming the AUTO+COMBO ownership conflict while preserving manual COMBO rollback and standalone AUTO behavior. See `kir001-product-combo-auto-characterization.md`.

### KIR-001-C2 — transaction ownership remediation

C2 changes Numbering allocation to **own-or-participate** semantics: standalone AUTO issuance still owns its transaction, while AUTO invoked inside the Product COMBO command participates in the caller transaction without committing/rolling it back. The Product facade remains the command-level transaction owner. No API/schema/PBAC change is introduced.

```powershell
php .\backend\tools\test-kir001-source-gate.php
# Back up erp_test before runtime:
.\backend\tools\test-unit.ps1 RUN UNIT-STANDARD
.\backend\tools\test-kir001.ps1 RUN
.\backend\tools\test-unit.ps1 RUN NO-DROP
```

See `kir001-product-combo-auto-remediation.md`. Status remains `SOURCE_READY / RUNTIME_REMEDIATION_NOT_RUN` until the 9-case runtime matrix, UNIT-STANDARD and NO-DROP all pass.

### BE-REORG-014J — Product SQL source module ownership

An internal Product SQL list implementation was moved from generic `Infrastructure/Api` to `Modules/Product/Product/Application`, without SQL, authorization or transaction algorithm changes. No legacy alias was added: inventoried callers are the Product query service and its internal tests. The v1.2.2 observed dependency snapshot is intentionally **not** rewritten.

```powershell
php .\backend\tools\test-be-reorg-014j-product-sql-owner.php
php .\backend\tools\test-be-reorg-014c-product-list.php
php .\backend\tools\architecture-scan.php
```

See `be-reorg-014j-product-sql-source-ownership.md`. Source gate PASS is not MariaDB/Shield/API parity. Run `UNIT-STANDARD` then `NO-DROP` against the backed-up `erp_test` target prior to merging.


### BE-REORG-014K — Pricing SQL list source module ownership

Moved the internal `SqlPriceListListSource` from generic `Infrastructure/Api` into `Modules/Pricing/PriceList/Application`, keeping SQL body, authorization buckets, read snapshot and API behavior unchanged. Only the owner query service and its known tests were retargeted; no compatibility alias is added.

```powershell
php .\backend\tools\test-be-reorg-014k-price-list-sql-owner.php
php .\backend\tools\test-be-reorg-014d-price-list.php
php .\backend\tools\architecture-scan.php
```

See `be-reorg-014k-price-list-sql-source-ownership.md`. Source-only 9-case class test is not DB/Shield parity; run UNIT-STANDARD plus NO-DROP (including `P1Perf008PriceListSqlPaginationTest`) on a backed-up, verified `erp_test`. HTTP/benchmark evidence remains pending.

### BE-REORG-014L — PBAC EmployeeStructure read seam

Authorization's canonical decision engine now consumes the **consumer-owned** `EmployeeScopeReadPort` instead of the legacy mutable employee assignment service and employee repository. Bootstrap provides a two-method read-only bridge to existing EmployeeStructure repositories on a shared DB connection, preserving ten-argument legacy `AuthorizationService` construction. Runtime recursion into the same engine while the privileged port is active is rejected and reset on exceptions; no business PBAC policy, SQL, API, schema, or transaction changes are intended.

```powershell
php .\backend\tools\test-be-reorg-014l-employee-scope-read.php
php .\backend\tools\architecture-scan.php
php .\backend\tools\test-unit-no-drop-source-gate.php
```

See `be-reorg-014l-employee-scope-read.md` for exact owner/dependency inventory, privileged consumer restrictions, runtime negative spy, verification/rollback and deferred read seams. **Source checks are not MariaDB/Shield/HTTP parity**; UNIT-STANDARD, guarded KIR-001 and NO-DROP must run against a backed-up `erp_test` before promotion.

### BE-REORG-014M — Organizational scope read audit and PRIMARY rule parity

Audited `EvaluationOrganizationResolver`, `EmployeeContactScopeResolver`, and `EffectiveOrganizationScopeIndex`, including the Contact list's batch scope compiler. One issue warranted code change: two separate definitions of **unique canonical ACTIVE PRIMARY assignment** in the per-contact PBAC path and the authorized batch-count path. A single pure helper on the existing Contact resolver is now shared; the per-row/batch query shapes, UTC date, missing employee handling, strict status/identifier checks and fail-closed results are unchanged. No new port, SQL or layer is added.

```powershell
php .\backend\tools\test-be-reorg-014m-employee-primary-scope.php
php .\backend\tools\test-be-reorg-014l-employee-scope-read.php
php .\backend\tools\architecture-scan.php
```

See `be-reorg-014m-employee-primary-scope.md` for the three-reader inventory, unchanged index/legacy seams, test and rollback plan. Vendor-free PASS does not establish MariaDB/Shield/HTTP parity; rerun guarded UNIT-STANDARD and NO-DROP on the backed-up test database.

### BE-REORG-014N — Immutable Organization scope signature reuse

Audited `EffectiveOrganizationScopeIndex` and its consumers following 014M. A measurable duplicate computation was found: `build()` validates all ancestor chains, then the authorized Organization/Contact list compiler walks the same chains again. The only production change caches **exact legacy boundary signature strings per immutable index instance**. No shared PBAC cache, SQL, new dependency/port, permission, or transaction change. Unknown/orphan/cyclic hierarchy still fails closed.

```powershell
php .\backend\tools\test-be-reorg-014n-scope-signature.php
php .\backend\tools\test-be-reorg-014m-employee-primary-scope.php
php .\backend\tools\architecture-scan.php
```

See `be-reorg-014n-scope-signature-reuse.md` for the read/owner inventory, synthetic measurement, eight source parity checks, guarded runtime plan and rollback. `SOURCE_VERIFIED` is **not** actual MariaDB/Shield authorization or HTTP performance parity. The focused `P1Perf006OwnerAwareSqlPaginationTest` is not in the NO-DROP allowlist; use only an independently reviewed guarded execution lane for its DB differential checks.

### BE-PBAC-016A — Product allowed actions (advisory list/detail UI)

Product List and Product detail GET now expose `allowed_actions.edit` and
`allowed_actions.setstate` as authoritative, record-State PBAC previews. The
existing `view` bucket-to-SQL `WHERE` / COUNT / page behavior is untouched.
Product remains GROUP-scoped MASTER only when metadata proves it; no global
action cache or backend mutation bypass is introduced.

```powershell
php .\backend\tools\test-be-pbac-016a-product-actions.php
```

See `be-pbac-016a-product-actions.md` for the exact security, contract and
runtime verification gates. Source PASS does **not** certify HTTP/DB/Shield
parity or frontend build.
