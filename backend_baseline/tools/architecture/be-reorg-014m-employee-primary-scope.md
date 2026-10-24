# BE-REORG-014M — Authorization organizational-scope read audit and PRIMARY rule consolidation

**Reference:** `docs/DinkERP_Backend_Architecture_Blueprint_Lean_v1.2.2.md` §§2–3 and `docs/DinkERP_Backend_System_Architecture_Standard_v1.1.1.md` §§2.3, 10.1, 10.2. These are proposed architecture baselines, not permission to modify the schema/API/PBAC policy.

**Patch status:** `SOURCE_VERIFIED / MARIADB_SHIELD_HTTP_PENDING`. No DB schema, SQL predicates, transaction, routes, Resource/Action, or HTTP response change. The 014L privileged PBAC read port stays unchanged.

## Inventory (post-014L, before this slice)

| Reader | Data / legacy dependency | Purpose and decision in 014M |
|---|---|---|
| `EvaluationOrganizationResolver::defaultRegistry` | `EmployeeRepository`, `EmployeeAssignmentRepository`, `OrganizationRepository` (compatibility repositories backed by owner modules) | Selects authoritative resource-scoped resolver. **Keep constructor/default wiring** for existing callers; no evidence of a recursive call or unnecessary runtime roundtrip warranting a new port. |
| `EmployeeContactScopeResolver::resolve` | Employee `find(id)` and Assignment `findEffective(employee, UTC date, primaryOnly=true)` | PBAC single-contact scope: require exactly one canonical ACTIVE primary; invalid/missing employee or primary fails closed. **Reuse its canonical-primary rule** in the batch read path. |
| `EffectiveOrganizationScopeIndex::build` | Actor `findByUserId`, Assignment `findEffective(employee, UTC date)` + Organization/Resource/Policy queries | Conservative scope partition for authorized Organization/Contact lists; returns null on unknown structure and validates all paths. No changes: moving SQL/queries would risk scope-equivalence semantics without runtime parity. |
| `OwnerAwareListBucketCompiler::employeePrimaryScopes` | Bounded employee existence query; Assignment `findEffectiveForEmployees` on the **same supplied connection** | Batch Contact owner scopes. Previously independently reimplemented `count(primary) === 1`, strict canonical State and primary values. **Call the exact same canonical-primary check as the per-contact PBAC path.** |

### Observed issue and smallest safe correction

The authoritative PBAC resolver and the authorized SQL list prefilter had **two copies of the canonical PRIMARY eligibility rule**. If a future bugfix changed only one copy, authorized Contact bucket selection could disagree with per-record PBAC, including the **COUNT before LIMIT** path. Both consumers already reference `EmployeeContactScopeResolver`; putting one static pure validation method on that existing class adds no new class/interface/factory or dependency edge.

`EmployeeContactScopeResolver::uniqueCanonicalPrimary(list<row>): ?row` now applies the existing strict checks: **one** row, status **exactly** `ACTIVE`, `is_primary` **integer 1 or string "1"** (not boolean, "01", lowercase status). Both paths call it. They **retain their distinct pre-existing query shape**: `primaryOnly=true` at per-record SQL, and strict `is_primary` filtering after batched effective-assignment reads. They also retain **their own existing organization ID validation and failure behavior** (PBAC throws its established `CONTACT_EMPLOYEE_PRIMARY_SCOPE_UNAVAILABLE` error; batch skips an invalid owner so the list cannot include it).

No additional query, changed evaluation date, projection, permission, or mutation was introduced. Missing employees remain invalid. Source-level port evolution to cover `find(id)` plus batched `findEffectiveForEmployees` was **deferred**: extending the two-method 014L `EmployeeScopeReadPort` for unrelated owner-scope consumers would expand a privileged PBAC boundary and may introduce N+1 query regressions. Do not swap legacy repositories for a writable general-purpose service merely to remove two imports.

## Verification and compatibility

Run from repository root (vendor-free):

```powershell
php backend/tools/test-be-reorg-014m-employee-primary-scope.php
php backend/tools/test-be-reorg-014l-employee-scope-read.php
php backend/tools/architecture-scan.php
php backend/tools/test-architecture-conformance.php
php backend/tools/test-unit-standard-source-gate.php
php backend/tools/test-unit-no-drop-source-gate.php
```

The 014M gate exercises the **real pure rule** for canonical integer/string primary values, missing/ambiguous/noncanonical cases, verifies both source callsites, UTC and read shapes, and guards the existing DB differential test `P1Perf006OwnerAwareSqlPaginationTest::testPbacScopeSignaturesAndSqlBucketsMatchOriginalEngine`. The test has no CodeIgniter/vendor dependency. **A green source gate is not a MariaDB/Shield PBAC parity certificate.**

On the operator machine, backup and verify `erp_test` first, then run:

```powershell
cd backend
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-kir001.ps1 RUN
.\tools\test-unit.ps1 RUN NO-DROP
```

`P1ContactAuth001OwnerAwareAuthorizationTest` and `P1Perf006OwnerAwareSqlPaginationTest` perform deeper DB/Shield/Contact differential checks; run them using the safe project-approved test DB lane **only after verifying their fixture/rollback scope**. Do not add an unaudited test class to NO-DROP or weaken class hash locks. The existing NO-DROP cohort does not automatically certify these two particular tests. Dedicated HTTP Contact list parity and performance remains `NOT_RUN`; MySQL privilege protections and dynamic SQL are not established by source gates.

## Rollback / deferred work

Revert 014M on top of 014L with `git apply -R BE-REORG-014M-employee-primary-scope.patch` before committing. No DB rollback. Follow-up only if supported by parity/performance evidence: narrow owner-specific read seam for Contact by employee ID/batch, or a scoped index repository/query service, without broadening 014L's privileged two-method port or changing the authorized-before-count contract.
