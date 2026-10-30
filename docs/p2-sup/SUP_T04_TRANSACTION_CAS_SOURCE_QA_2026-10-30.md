# P2-SUP / SUP-T04 — Supplier Core Transaction, CAS and History (Source QA)

Date: 2026-10-30. Baseline: `erp_v2.0.0_sup_t03_sql_first_pbac_source.zip` (SUP-T01A through SUP-T03). Task boundary: SUP-T04 source only.

## Implemented

- `SupplierWritePayload`: exclusive POST, PATCH, PUT Company Usage and SetState DTO parsing. Reject unknown/privileged keys; strict integer `expected_version` in [1..4294967294]; validate create required profile, full Company mode/list and dynamic State code.
- `SupplierWriteService`: dedicated transaction owner for Create/PATCH/PUT usage/SetState; P1 `UnifiedAccessService` authority reloaded inside transaction; same Supplier-master advisory lock for all writes, `SELECT ... FOR UPDATE` target and lookups; DB conditional `row_version` CAS + `affectedRows() === 1` for all updates; failed writes roll back.
- Create uses `SupplierNumberIssuer` in **same physical DB transaction** for Supplier row, usage rows, counter, and one Shared History CREATE. Group is server-derived; Series policy is SUP-specific (no manual fallback with configured default); initial State is from canonical Lifecycle, never hardcoded ACTIVE.
- Profile PATCH remains partial; merged result validates required Profile and Province/Ward. Neither phone nor tax code is made UNIQUE. Every successful PATCH, even idempotent, increments version; selective History skips unchanged/DRAFT data.
- Company Usage is a full atomic replacement with one bounded `WHERE IN ... FOR UPDATE` Company validation query (not N+1); zero rows when `all_companies=true`, 1..512 otherwise; IDs are unique ACTIVE Companies of the server-selected Group. Company Usage **never** gives PBAC rights.
- SetState is only via canonical `transitionTarget('supplier', old, 'setstate')`, expected target must match sole configured transition; authorization is checked both as mutation and transition, stale version denied. Shared History records STATE_CHANGE and entry checkpoint. No generic `status` update is allowed.
- Read responses use a narrow explicit projection; no bank fields/cleartext in generic DTO or History. `SupplierService` is the single façade for the later T07 API, delegating to T03 reads and T04 writes without creating routes.
- Catch MariaDB duplicate SQL 1062 and classify reviewed `uq_supplier_group_code` as `SUPPLIER_CODE_DUPLICATE`. This is defense in depth after the NumberIssuer uniqueness probe.

## Tests executed in the isolated source container

| Test | Result | Meaning |
|---|---:|---|
| SUP-T04 payload pure | 58/58 PASS | Allowed fields, required profile, full usage, strict CAS, state payload |
| SUP-T04 transactional fake DB | 72/72 PASS | Create/edit/usage/SetState, simulated rollback, counter, History, PBAC, CAS-zero |
| SUP-T04 source boundary | 43/43 PASS | Shared transaction, issuer, authorization, no bank/HTTP wiring |
| Historical SUP-T01A/T01B/T02/T03 | 314/314 PASS | 48+38+16+64+34+84+30 |
| Pinned M3.4B offline regression | 168/168 PASS | Existing system regression inventory |
| M19.24 Routes architecture | 476/476 PASS | No P1/P2 route mutation |
| PHP lint of new PHP files | PASS | Syntax |

**The fake DB does not verify InnoDB isolation, real SQL, DBPrefix, FK behavior, permission policy configuration, CSRF or HTTP status mapping.** Therefore T04 is `SOURCE/OFFLINE PASS`, not a signed-off integration or production feature.

## Fail-closed conditions and retained deferrals

1. Migration `000028` remains **UNAPPLIED** until DBA/Security signoff of bank metadata and table constraints. Never run `migrate` as part of these offline tests. Catalog provisioning is separate, opt-in, and grants no permissions.
2. There are no Supplier HTTP routes/controllers/OpenAPI/TS client additions yet (T07). No backend-to-browser UI entrypoint exists (T08).
3. No bank reveal/write/clear endpoints (T04B) until crypto key custody, authenticated encryption, persistent security audit, and permission gate are signed off.
4. Supplier Contact ownership/integration remains T06. Contact writes on create must be joined to the SAME owning T04 transaction **before** the one History CREATE and commit; no nested transaction or second CREATE event.
5. M11 Workflow notification producer does not yet have a reviewed Supplier source-table mapping. T04 does **not** enqueue notification events; review/enable only with later Workflow integration.
6. Operational History policy currently recognizes `ACTIVE` as operational. If custom SUP Lifecycle operational states are approved, adjust `HistoryPolicy` explicitly and add regression before deployment.
7. Real MySQL/MariaDB UAT required: `EXPLAIN`, two actor PBAC, duplicate code across concurrent sessions, physical CAS conflicts, DB 1062, failure after Supplier insert, Company/Location FK checks, History rollback, Numbering counter rollback, and branch-transition rejection. Use only an isolated authorized DEV clone or guarded `erp_test`; no automatic destructive resets.
8. T04 does not prove PUR/FIN supplier eligibility. The separate `SupplierUsabilityGuard` is T05; it must read trusted persisted Purchase Company.

## Apply and Windows offline regression

From repo root with prior SUP-T03 patch applied:

```powershell
$patch = '.\DinkERP_P2_SUP_T04_Core_Transaction_CAS_Source.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: T04 patch mismatch' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: T04 apply failed' }
git diff --check
if ($LASTEXITCODE -ne 0) { throw 'STOP: invalid diff' }
cd .\backend
.\tools\test-unit.ps1 RUN SUP-UNIT
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN ROUTES
.\tools\test-unit.ps1 RUN P1-STANDARDS
```

## Task status

SUP-T03: Windows OFFLINE ACCEPTED by operator. SUP-T04: SOURCE/OFFLINE PASS, pending Windows execution and later MariaDB/HTTP signoff. Next source task: SUP-T05 `SupplierUsabilityGuard`; remaining T04B security gate and T06/T07/T08 are not included in this patch.
