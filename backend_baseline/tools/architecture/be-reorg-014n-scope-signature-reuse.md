# BE-REORG-014N — EffectiveOrganizationScopeIndex signature reuse

**Baseline:** BE-REORG-014M applied after 014L, 014K, 014J and KIR-001-C2.2.
**Standard:** `docs/DinkERP_Backend_System_Architecture_Standard_v1.1.1.md` (PBAC read semantics, incremental module changes, source/runtime gates).
**Status:** `SOURCE_VERIFIED / MARIADB_SHIELD_HTTP_PENDING`. This is **not** approval of a new module contract.

## 1. Read-only dependency/owner inventory

| Path | Actual reads / responsibility | Decision |
|---|---|---|
| `EvaluationOrganizationResolver` | Resolves authoritative persisted organization context through Organization and EmployeeStructure compatibility repositories; validates caller hint | No change; direct path is the current compatibility seam, not evidence for a new port |
| `EmployeeContactScopeResolver` | Single Contact employee existence and effective PRIMARY assignment for the operation date | Keep 014M shared canonical PRIMARY rule; no new query path |
| `EffectiveOrganizationScopeIndex::build` | One bounded Organization tree read, one actor Employee/Assignment lookup, one Resource list and one bounded Policy read; conservative scope partition | **Only optimize deterministic signature traversal after these reads** |
| `OwnerAwareListBucketCompiler` | Reuses the scope index for per-Organization and per-Contact PBAC-equivalence groups before authorized COUNT/LIMIT; distinct owner batch-lookup remains | No change to SQL, State/resource grouping or representative PBAC evaluation |
| `PbacDecisionEngine` | Independent authoritative decision per representative, with opt-in read-only batch cache | No attempt to share index cache with live decisions or their privileged EmployeeScopeReadPort |

Cross-component Employee/Assignment reads exist but represent **different purposes and trust boundaries**. Sharing query results across the scope index and live PBAC could alter snapshot/effective-time/eligibility semantics. This slice adds no port, factory, cross-owner query or cache external to an index instance.

## 2. Proven local issue and chosen remediation

Prior to 014N, `build()` calls `signature(id)` once for every Organization node to validate all ancestor paths, and `OwnerAwareListBucketCompiler` calls `signature()` again when grouping Organization nodes and Contact owners. Every call walked the full ancestor chain, even after that chain had been validated. For a legacy/degraded deep tree, repeated traversal is quadratic in node count (bounded by the existing `MAX_NODES = 12000`). **The index reads one tree but redundantly computes paths.**

The sole production change is a **non-static instance-local** `resolvedSignatures` memo in `EffectiveOrganizationScopeIndex::signature()`: walk only to the first already validated ancestor, then populate unchanged comma-separated boundary strings down that path. `build()` continues to validate **all** nodes before returning the index. Once an ancestor is known valid and the index input arrays are immutable, its computed signature can safely be reused for descendants.

This removes repeated ancestor traversals; it does **not** eliminate SQL statements, rewrite repositories or change the list/HTTP contract. Path processing becomes amortized linear in the number of nodes per index; formatting boundary-prefix strings still costs proportional to the number and length of boundary paths (bounded by `MAX_BOUNDARIES = 768`). PHP's copy-on-write strings let non-boundary descendants reuse the same string value without producing new concatenations.

## 3. Frozen security and compatibility invariants

- `signature(id)` still returns **exactly** the original comma-separated, root-to-leaf boundary IDs (or `''` when no boundary), not a hash/opaque ID. Public signature and `nodes()`/`contains()` contracts unchanged.
- Unknown ID, missing parent, cycle and wrong GROUP root still yield `null`; `build()` still returns `null` when any node path is uncertain. Invalid prefixes are **never memoized as valid**.
- Resource domain/type, Policy participant/scope and actor assignment boundary collection unchanged. UTC `evaluationTime` and effective assignment reads unchanged.
- `OwnerAwareListBucketCompiler` still separates Resource type, exact raw State and signature before representative PBAC checks and authorized COUNT/page; it does not shortcut `ABSOLUTE_DENY`/Base Permission.
- Memo is owned by **one immutable index instance**, created inside one `build()` invocation; it is not static, request-global, shared with PBAC decisions or persisted to DB.
- No route, OpenAPI payload, table, migration, transaction, permission/Resource/Action, class location or module import changed. `KIR-001` untouched.

## 4. Evidence and limits

The developer's PHP CLI measurement on synthetic chains, running all `signature()` calls once, showed approximately:

| Nodes | Frozen pre-014N signature traversal | 014N same projection |
|---:|---:|---:|
| 1,200 | 79 ms | 1.0 ms |
| 2,400 | 300 ms | 0.8 ms |
| 3,600 | 645 ms | 1.2 ms |

These are **synthetic PHP-only** measurements, not representative DinkERP production hierarchy, DB query count, end-to-end HTTP p95, Shield permission throughput or an SLA. Normal Organization writes enforce a shallow hierarchy; the benefit is principally avoiding duplicate computation and bounding costs on legacy/degraded deep data and repeated reads.

`backend/tools/test-be-reorg-014n-scope-signature.php` executes the **actual production class** (reflectively supplying only the already-queried immutable projection), compares results with an independent copy of the pre-014N path algorithm, and checks eight cases: balanced hierarchy exact bytes; sparse/no boundary; deep 12k-node/706-boundary traversal; orphan/cycle and wrong root; instance cache isolation; State/Resource bucket grouping; preserved SQL/UTC limits and existing DB differential test. This vendor-free source gate runs **no SQL** and is not a runtime PBAC certificate.

Use these source gates:

```powershell
php backend/tools/test-be-reorg-014n-scope-signature.php
php backend/tools/test-be-reorg-014m-employee-primary-scope.php
php backend/tools/architecture-scan.php
php backend/tools/test-architecture-conformance.php
```

On a **backed-up, verified** `erp_test`, run the normal guarded `UNIT-STANDARD`, `KIR-001` and `NO-DROP` lanes. The existing `P1Perf006OwnerAwareSqlPaginationTest::testPbacScopeSignaturesAndSqlBucketsMatchOriginalEngine` remains the purpose-built MariaDB/Shield differential for real Organization/Contact list parity; **it is not part of the approved NO-DROP allowlist** and must not be run by bypassing its guarded transaction preconditions. Schedule it separately through a reviewed safe DB test runner before calling this slice HTTP/DB differential verified.

**Rollback:** revert only 014N patch. Signature memo and this gate/documentation disappear; prior `signature()` body is restored. No DB rollback or API migration.
