# P2-SUP T03 — SQL-first PBAC Read Boundary / Source QA

**Baseline:** `erp_v2.0.0_sup_t02_numbering_source.zip` (after T01B.1 and T02, confirmed Windows PASS)

**Authority:** SUP-G1 Technical Design v1.0.1 §3.2, §6, §7; `SUP_T01B1_CUS_LESSONS_PREVENTION_GATE_2026-10-30.md`; shared P1 SQL-first PBAC standard. **Status: Source/Offline candidate only; NOT MariaDB/HTTP/production accepted.**

## Delivered

1. `SupplierScopeProvider` is a reviewed `supplier` **MASTER** scope. Both the authorized SQL compiler and canonical `UnifiedAccessService` explicitly accept the new type. Existing P1 Master allowlists remain pinned; Supplier is added as an explicit extension, not a wildcard fallback.
2. `SupplierSql` validates strict flat query keys (`q`, `page`, `page_size`, `sort`, `supplier_type`, `supplier_group_code`, `status`, `company_id`); default 25, cap 100; dynamic Lifecycle code syntax; sort allowlist and id tie-break; escaped LIKE with bound values; full prefix support; no client SQL identifiers. All values in SQL are parameterized.
3. SQL COUNT, PAGE and direct-ID detail use the **same canonical PBAC State predicate** and a required `s.group_organization_id = ?` with the **server-owned GROUP root**, never a client-selected Group. Company eligibility filter is `all_companies = 1 OR EXISTS (...)` using `supplier_company_usages`; it cannot expand the actor's PBAC rights or multiply rows.
4. `SupplierReadService` implements a read-only, short-lived transaction snapshot for list/detail. It checks base `supplier.view`, validates a State filter against persisted Lifecycle definitions, executes scoped COUNT/PAGE before LIMIT, fails closed if per-row canonical `canView()` contradicts SQL, and returns a no-leak 404 for denied/nonexistent direct IDs. `allowed_actions` is an advisory hint from the canonical effective action implementation, never client authority.
5. List and detail use explicit projections. No `bank_ciphertext`, `bank_nonce`, `bank_tag`, `bank_key_version` or `bank_last4` can enter the generic Supplier response **even if a database row contains them**. Detail Company Usage query is bounded to 513 and fails on corrupt All/list combinations. Bank API/reveal/audit is a different, still-blocked workstream.
6. **No migrations, routes, HTTP controller, permissions grants, Numbering writes, bank endpoints, or Contact owner changes** in T03. Existing T02 issuer remains a T04 transaction participant.

## Test evidence (offline local)

| Gate | Result |
|---|---|
| T03 SQL plan, negative validation, bind arity, reviewed compiler, status/group/usage, bank exclusion | 84/84 PASS |
| T03 compiled PBAC/read service source contract | 30/30 PASS |
| M3.4B pinned regression | 168/168 suites PASS |
| M19.24 frozen routes | 476/476 PASS |
| Customer core | 94/94 PASS |
| Customer SQL/PBAC plans | 48/48 PASS |
| M6.4A.1 Product SQL pushdown | 54/54 PASS |
| M8.6 Product Catalog SQL scope | 32/32 PASS |
| M8.5 existing SQL scope | 23/23 PASS |
| All 11 P1-STANDARDS SQL/PBAC regression scripts | PASS |
| PHP syntax | PASS for changed PHP code/tests |
| Real MariaDB / query EXPLAIN / two-actor PBAC / HTTP | **NOT RUN** |

The historical pinned Master resource list was preserved byte-for-byte in `UnifiedAccessService` to avoid changing M6.4A/M8.6 regression contracts; Supplier is an explicit additional condition. Do **not** loosen pinned tests or whitelist unreviewed resource types.

## Developer execution (Windows)

```powershell
cd D:\XAMPP\htdocs\erp
$patch = '.\DinkERP_P2_SUP_T03_SQL_First_PBAC_Read_Source.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: Patch mismatch' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: Apply failed' }
git diff --check
if ($LASTEXITCODE -ne 0) { throw 'STOP: Invalid diff' }

cd .\backend
.\tools\test-unit.ps1 RUN SUP-UNIT
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN ROUTES
.\tools\test-unit.ps1 RUN P1-STANDARDS
```

**DO NOT run `php spark migrate` or `supplier:catalog-provision` yet.** T01 migration `000028` remains gated on verified DEV schema and separate bank metadata/key/audit security review. No business write is enabled in T03.

## Mandatory DB/HTTP gates before enabling Supplier API (T07)

- On an **approved, backed-up DEV clone**, verify `000028` schema, real catalog provisioning, Lifecycle initial/current custom states, trusted one-GROUP-root assumption, DBPrefix, and absence of automatic PBAC grants. Use the actual *production planner* for every EXPLAIN, COUNT/PAGE and direct-ID query.
- Two authenticated actors with demonstrably different base grants and persisted ALLOW/DENY/ABSOLUTE_DENY policy conditions: verify `supplier.view` controls COUNT/page/detail; list total matches full authorized SQL rows; no COUNT/page post-filter; deny and missing ID both no-leak.
- Test dynamic `DRAFT`, `ACTIVE`, `INACTIVE`, custom State; role/group membership/effective assignments; a resource status INACTIVE; `company_id` All/list eligibility with 2+ companies; Company filter must **never create** VIEW permission; repeated exact sort keys, null/Unicode searches, wildcard escaping, page edges, malicious invalid keys/sorts/IDs.
- Seed a bank ciphertext test canary (without real account numbers) and prove generic list/detail never materialize or serialize even `last4`. Confirm no N+1 Supplier list access, index use on representative fixtures, p50/p95 and real EXPLAIN access/key/rows; query counts and fixture coverage documented. Failure/empty fixtures are INCONCLUSIVE, not a security PASS.
- On HTTP rollout (T07), independently verify canonical envelope, status codes (400/401/403/404), API/FE flat filter adapter, response shape (including `row_version`, `company_ids`, `allowed_actions`) and bank data absence.

## Next task / limits

**T04:** transactional SupplierService Create/PATCH/Company Usage/CAS, with T02 issuer under the owning transaction and exact failure rollback, including real DB duplicate/counter/concurrency UAT. T05 Workflow, T06 Shared Contacts (separate owner SQL+audit gate), T07 routes/OpenAPI/HIS/Bank security, T08 Vue. Do not interpret a T03 source-only PASS as permission to deploy SUP-G1, or claim G1 is complete without the required bank capability and durable audit.
