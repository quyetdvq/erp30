# DinkERP P2-SUP — Implementation Plan & Acceptance Gates v1.0.1

> **Document ID:** P2-SUP-PLAN-001 · **Version:** 1.0.0 · **Date:** 2026-10-29
> **Product scope:** **APPROVED 12/12** via [Decision Register](P2_SUP_DESIGN_DECISIONS_v1.0.1.md).
> **Execution readiness:** **PRE-CODE TECHNICAL VERIFICATION PENDING**; none of the code/migration/runtime gates are PASS.
> **Standards:** `docs/development-standards/01_FRONTEND_UI_STANDARD.md`, `02_SQL_FIRST_PBAC_PERFORMANCE_STANDARD.md`, `03_MODULE_DELIVERY_GATES.md`, `05_DEV_SQL_HTTP_QA.md`, `06_ROUTE_ARCHITECTURE_STANDARD.md`.
> **Source baseline:** `erp_v2.0.0_customer_required_phone_fixed.zip`, SHA-256 `9af6a01ec451400c8d3cd580396b1e6d3b294c0073b4c0675532a9752bb4aa28`.

## 1. Delivery scope and distinct gate systems

- **P2 Wave G1**: Supplier Master Core. **P2 Wave G2/G4**: real Purchase/Finance History adapters, contingent on source modules. Supplier G1 readiness is **not** full `UR-SUP-002` closure.
- **MOD-G0..G7**: quality/acceptance gates from `03_MODULE_DELIVERY_GATES.md`, independent of P2 wave names. Product decisions approved ≠ G1 Schema/PBAC PASS ≠ runtime/UAT PASS.
- **Operator's chosen UAT method:** operator performs HTTP and functional UAT directly in frontend/Network. The implementation package supplies evidence prompts; it **does not mandate the previously deferred generic M19.25B fixture** merely to begin work. SUP-specific authorization negative cases are still mandatory before production signoff.

## 2. Before writing source code (SUP-T00)

| Preflight item | Required evidence | Status now |
|---|---|---|
| 12 Product decisions + all 8 original AC | Register v1.0; traceability | **APPROVED** (decisions); 8 AC **BASELINE** |
| Stable source baseline | git commit/status, matching source archive SHA, patch inventory | **REFERENCE CAPTURED**; operator commit/status pending |
| Database/migration sequence | `php spark migrate:status`, DEV backup, schema prefices/FK, no collision with 000025 | **PENDING** |
| Bank crypto and secrets | Security review cipher/key source, reveal action, audit, error handling; no plaintext path | **BLOCKS bank code** |
| Numbering compatibility | resource catalog `supplier`, mode/scope/series; manual fallback; error mapper | **PENDING** |
| Supplier lifecycle configuration | state-graph binding and initialState availability; transition/eligibility rules | **BUSINESS RULE APPROVED**; instance preflight pending |
| Company & shared Contact semantics | scoped resource/action names, owner resolver, parent VIEW and SQL-first parity | **PENDING SOURCE DESIGN REVIEW** |
| API DTO/OpenAPI, version and lookups | exact endpoint params/response/caps/error codes/CSRF and FE integration | **MOD-G2 PENDING** |
| Data/performance representative plan | expected Supplier counts, Company density, Contact fan-out and budget for EXPLAIN | **G1/G4 PENDING** |

See [Readiness Checklist](P2_SUP_PRECODE_READINESS_v1.0.1.md) for exact blockers and owners. These are technical signoffs under the already approved product scope, **not new business features**.

## 3. Task breakdown and dependencies

| Task | Deliverable (after required preflight) | Depends | Test/review boundary |
|---|---|---|---|
| **SUP-T00** | Freeze approved v1.0 decisions/AC; verify source, SQL/PBAC owner, schema/API/crypto preflight and evidence | PO approval completed | MOD-G0 product PASS; MOD-G1/G2 technical reviews |
| **SUP-T01** | Additive migration `suppliers` + `supplier_company_usages`: required fields, Group UNIQUE, status VARCHAR20, row_version, nullable **ciphertext** bank metadata | T00, DBA/Bank review | migration id preflight; no destructive DB patch; down-empty-only |
| **SUP-T02** | Idempotent Supplier resources/actions `supplier`,`supplier_contact`, bank actions; controlled group/term codes; **preserve configured Workflow binding**, optional new default only if resource absent; Numbering preflight and **explicit opt-in** default series provisioning, **no grants** | T01, Numbering/Workflow review | rerun, conflict/no-grant, missing Lifecycle/series fail-closed |
| **SUP-T03** | `SupplierRules`, `SupplierScopeProvider`, `SupplierSql` list/count/detail/lookup with SQL PBAC | T01,T02 | required profile, phone **not unique**, Province→Ward, q/filter/Unicode, count/page/det/no-leak |
| **SUP-T04** | `SupplierService`: create/edit/usage/setstate, **SUP-specific** Numbering precedence, dynamic initial Lifecycle, CAS for **all** writes including SetState, tx/audit | T03 | duplicate/concurrent code, no hardcode ACTIVE, 409 stale, wrong Company/status |
| **SUP-T04B** | Dedicated **one-bank-account** crypto service + Bank secure DTO/permissions/mask/reveal/**explicit clear**/durable audit; endpoints mounted only at T07 after Security signoff | T01,T02,T04 + Security signoff | encryption roundtrip/tamper/missing key/permission and logging negative tests |
| **SUP-T05** | `SupplierUsabilityGuard(supplierId, persistedCompanyId, operationTime)` for future PUR | T04 | ACTIVE only, INACTIVE/draft deny, Company out-of-scope deny; no client owner spoof |
| **SUP-T06** | Shared Contacts add owner SUPPLIER, `supplier_contact` and parent-view guards in **all runtime + existing read-only audit** SQL/service/Workflow/FE, with no change to frozen nine-resource audit catalog | T02,T03,T04 | ORG/EMP/CUS 3-owner parity, supplier owner counts/direct-ID/parent denied |
| **SUP-T07** | `SuppliersRoutes.php`, controllers, canonical OpenAPI (**flat list filters, page_size default 25**), errors/i18n and TS generated client; bank routes only after T04B security gate | T04,T04B,T06 + MOD-G2 wire review | route registry additions only, auth/CSRF/handler/contract parity |
| **SUP-T08** | Vue `/suppliers` list/detail/create/edit/usage/contacts/lifecycle/**masked bank and explicit reveal/clear**, responsive vi/en, Customer-style list-query adapter | T07 | Vitest, lint/type/build, one list/all states, 409 draft preservation, masked bank |
| **SUP-T09** | DEV MariaDB migration, EXPLAIN, operator frontend/Network PBAC+functional UAT, release evidence | T08 | MOD-G4/G5/G6/G7 separately; no phantom PASS |
| **SUP-T10** | Supplier History reader for real PO/GR/Return with per-document Company/VIEW | PUR/INV published sources | UR-SUP-002 partial acceptance when sources exist |
| **SUP-T11** | Supplier History Finance Expense/Payment adapter, actual timestamps/source links | FIN source published | UR-SUP-002 full acceptance; no dummy data |

**Sequencing:** Do not start T01 until required G1 schema/crypto preflight is complete, including evidence for bank durable audit compatibility or an approved narrow schema ADR. No feature route is deployed before its own guards are ready. Limited source-discovery/scaffolding or documentation corrections under T00 may proceed without writing runtime feature code. Do not issue unsupported `SUP-UNIT` commands before they are added to runner.

### 3.1 Minimal dependency flow

```text
PO decisions 01..12 APPROVED
        |
SUP-T00: source/crypto/schema/PBAC/Numbering/Lifecycle/OpenAPI preflight
        |
SUP-T01 migration -> SUP-T02 resource/cat/workflow/numbering
        |                         |
SUP-T03 SQL/PBAC ----------------+
        |
SUP-T04 service/CAS ----> SUP-T05 Purchase eligibility guard
        |
        +----> SUP-T04B encrypted bank (Security signoff)
        +----> SUP-T06 Shared Contacts/parent scope
                      |
                  SUP-T07 routes/OpenAPI/generated DTO
                      |
                  SUP-T08 Vue/list/create/edit/detail/contacts
                      |
                  SUP-T09 DB/EXPLAIN + operator UI/Network UAT

PUR/INV available -> SUP-T10 History; FIN available -> SUP-T11 History
```

## 4. Patch boundaries (incremental against approved baseline)

| Patch candidate | Included | Must not include |
|---|---|---|
| `P2_SUP_G1_01_SCHEMA_RESOURCE.patch` | T01,T02 migration/seeder/catalog/lifecycle + focused negative tests | unrelated P1 migrations, auto grants, frontend or fake Purchase |
| `P2_SUP_G1_02_MASTER_SERVICE.patch` | T03,T04,T05 rules/scope/SQL/tx/guard + tests | Customer schema refactor, modifying frozen route signatures |
| `P2_SUP_G1_03_CONTACT_EXTENSION.patch` | T06 Contact/Access/Workflow owner rules and existing-owner regressions | nonrelated Contacts features, P1 owner relaxations |
| `P2_SUP_G1_04_SECURE_BANK.patch` | T04B bank service, encrypted metadata, permissions, secure routes/UI test contracts; may be combined with schema only after security review | plaintext bank fields or multi-bank design |
| `P2_SUP_G1_05_API_UI.patch` | T07,T08 API/OpenAPI + Vue, i18n, route gates, typed client | Notes/History fake endpoints, incompatible CUS UI rewrites |
| `P2_SUP_G1_06_QA_RELEASE` | T09 DEV QA/report and operator signoff | claim HTTP/DB PASS from source tests only |

Names and numbering are candidates. Each real patch must include SHA/source base, explicit changed files, `git apply --check`/reverse commands, migration runbook and evidence. Decomposed packages must avoid shipping intermediate routes that silently bypass bank/auth/Contacts guards.

## 5. MOD-G0 → MOD-G7 acceptance matrix

| Module gate | Required result | Status on 2026-10-29 |
|---|---|---|
| **MOD-G0 — Business scope** | 12 approved choices; UR/AC and deferred History clear | **PASS (Product scope only)** |
| **MOD-G1 — Data/PBAC freeze** | final DDL/index + Group owner + Company + 2 bank actions + Contact parent scope, Security/DBA review | **PENDING** |
| **MOD-G2 — API/UI freeze** | OpenAPI operations, **flat filters**, page_size default=25/cap=100, SetState+bank+usage expected_version, bank reveal/clear and cache headers, typed frontend contract | **PENDING** |
| **MOD-G3 — Code/Unit** | migration/seeder/logic tests, source/Routing/PBAC regression, Vue Vitest/lint/type/build | **NOT_RUN** |
| **MOD-G4 — DB/Performance** | migration DEV clone, sampled EXPLAIN/count/pages/lookup, p95 query and schema evidence | **NOT_RUN** |
| **MOD-G5 — HTTP/PBAC** | authenticated two actors incl DENY, cross-Company, no-bank-permission, CSRF, bank reveal/clear, multi-writer race and verified 409 CAS | **NOT_RUN — operator UAT method** |
| **MOD-G6 — Browser UAT** | create/edit/Phone/Address/Province/Ward/Usage/Contact/State, list ALL, bank mask, errors | **NOT_RUN — operator UAT** |
| **MOD-G7 — Release/Closure** | deployment patch+rollback, migration and QA results, approved release | **NOT_RUN** |

No provider may label a gate PASS with only docs/pure tests. Supplier Core may be marked ready for PUR once its own data/authorization/UAT gates pass, while `UR-SUP-002` History stays **DEPENDENCY_DEFERRED**.

## 6. Test matrix — business + security + regression

| Test ID | Case | Expected behavior |
|---|---|---|
| `SUP-QA-01` | Same normalized code created concurrently within Group | Exactly one success; other 409; no orphan usage/number counter corruption |
| `SUP-QA-02` | Explicit series missing vs no default + manual valid; **default AUTO_IF_EMPTY with manual code** | SUP requires AUTO when default configured, disallows manual override; no fallback for inactive/broken default; shared counter atomicity |
| `SUP-QA-03` | Missing required Name/Type/Group/Phone/Address/Province/Ward/Usage | 422 field errors; create aborts; UI mandatory marks |
| `SUP-QA-04` | Same Phone on two Suppliers, including formatted variants | **Both may succeed** (phone nonunique); invalid Phone format 422 |
| `SUP-QA-05` | Province changed with stale Ward | reject inconsistent pair; FE dependent Ward cleared |
| `SUP-QA-06` | All=true and nonempty company_ids; All=false and invalid/non-COMPANY ID | reject without DB mutation; no unauthorized scope expansion |
| `SUP-QA-07` | Lifecycle initial DRAFT and transitions DRAFT→APPROVED; **two setstate targets from same source** | persist DRAFT; legal unique transition only; ambiguous multi-target rejects 409 without mutation, no ACTIVE hardcode |
| `SUP-QA-08` | INACTIVE/DRAFT Supplier or out-of-usage Company in Purchase guard | DENY new transaction; persisted old docs unaffected; final real PO case deferred to PUR |
| `SUP-QA-09` | Concurrent PATCH/usage/setstate/**bank PUT and clear** with same expected_version | one writer wins, every success increments shared row_version, stale 409, no partial rows/lost update |
| `SUP-QA-10` | Shared Contacts many, parent denied; ORG/EMP/CUS owner regression | no contacts/count/direct-ID leakage; no owner transfer |
| `SUP-QA-11` | supplier.view denied and Customer-like status listing | no counts/details/labels leak; one list and dynamic states, hints from effective rights |
| `SUP-QA-12` | Bank missing key, tampered ciphertext, no bank permission, reveal/clear and cache | bank routes fail-closed, nonbank master unaffected; no plaintext in generic APIs/logs; secure `no-store`, durable audited reveal/PUT/clear |
| `SUP-QA-13` | q/Unicode/wildcards/flat status/company/group/type filter/invalid sort/page | SQL count/page parity, flat Customer-like wire contract, 25 default/100 cap, 400 bad params, no N+1 |
| `SUP-QA-14` | UI errors 403/404/409/422; vi/en, keyboard, mobile | stable draft on 409, accessible modals, scoped retry |
| `SUP-QA-15` | History source Company/VIEW deny, inactive Supplier history | source counts/links protected when actual PUR/FIN exists; no fake 0 |
| `SUP-QA-16` | No default Payment Term; valid four codes; unknown code | nullable accepted; only approved codes accepted; no AP schedule inferred |

**Traceability (all eight immutable UR IDs):**

| AC | QA evidence |
|---|---|
| `P2-AT-SUP-001-01` | QA-01/02: unique Supplier Code within Group |
| `P2-AT-SUP-001-02` | QA-06: All Companies / allowlist persisted |
| `P2-AT-SUP-001-03` | QA-08: not usable outside scope; final PO test at PUR |
| `P2-AT-SUP-001-04` | QA-10: multiple Shared Contacts |
| `P2-AT-SUP-001-05` | QA-07/08/15: inactive blocks new PO, history retained; final PUR proof |
| `P2-AT-SUP-002-01` | QA-15: only authorized Company history; deferred to real PUR/FIN |
| `P2-AT-SUP-002-02` | QA-15: direct links only for source VIEW; deferred |
| `P2-AT-SUP-002-03` | QA-15: inactive Supplier history still viewable; deferred |

Keep each baseline AC unchanged in source P2 UR.

## 7. Verification commands: existing vs future

Commands verified to be present in supplied source (whether they PASS after future SUP patch is **unknown**):

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN ROUTES
.\tools\test-unit.ps1 RUN P1-STANDARDS
.\tools\test-unit.ps1 RUN CUS-UNIT
python .\tools\verify_routes.py
php spark migrate:status
php spark routes

cd D:\XAMPP\htdocs\erp\frontend
pnpm exec vitest run
pnpm run type-check
pnpm run lint
pnpm run check:imports
pnpm run build
pnpm run openapi:lint
pnpm run openapi:generate:check
```

**Not yet implemented:** `RUN SUP-UNIT`, SUP SQL/PBAC coverage suite, migration/HTTP request scripts. Create them within corresponding task before prescribing a run. DEV `php spark migrate` only after operator backup and review of pending migration list; no automatic database write as part of docs acceptance.

## 8. Operator UAT protocol (no assistant-run HTTP)

Operator will execute directly on DEV frontend and provide screenshots/log/Network evidence if requesting runtime signoff. Focus at minimum: two users with different Supplier/Company/Bank permissions; create with required fields; duplicate-code conflict; **duplicate-phone accepted**; dynamic Lifecycle initial DRAFT; usage All/list; many Contacts; bank masked vs permitted reveal; wrong Company; stale CAS 409. Store sensitive account number redacted in shared evidence. Console-only frontend screenshots without response/status confirmation cannot replace a denied API test for PBAC signoff.

## 9. QA evidence and patch handoff template

```text
SOURCE: ZIP SHA / git commit / git status
MODULE: P2-SUP-G1
DECISIONS: P2-SUP-ADR-REGISTER v1.0.1, 12/12 APPROVED
P2 UR: UR-SUP-001; UR-SUP-002 DEPENDENCY_DEFERRED
MIGRATIONS: exact names + backup/preflight + DEV up evidence
POLICY: supplier + supplier_contact + view_bank/edit_bank, no auto grants
CONTRACT: OpenAPI operation IDs, q/filter/sort/page cap, error details
TESTS: SUP-QA-01..16, negative PBAC, route diff 180 frozen, all CUS regressions
DB: real Group/Company/Location schema, EXPLAIN, query counts, p50/p95
BROWSER: operator DEV evidence; vi/en; responsive; 409/422
GATES: MOD-G0..G7 = PASS/FAIL/NOT_RUN/BLOCKED each
RELEASE: exact incremental patch; git apply/reverse check; DB forward-fix plan
```

## 10. When to call the work complete

- **G1 Supplier Core Ready:** final source, DB migration, PBAC, Contact, **durable bank audit and Bank Security review**, Lifecycle, UI and operator UAT are verified with evidence; SUP-001 AC 01/02/04 supported by data; 03/05 guard proven and final actual PO integration reserved. Label **CORE_READY_FOR_PUR**, not whole SUP CLOSED.
- **Full SUP Closed:** all 8 UR acceptance criteria backed by real PO/GR/Return/Finance historical sources and per-Company VIEW proof, plus MOD-G0–G7 gate evidence and owner signoff. Until then UR-SUP-002 remains dependency-deferred.

## 11. Revision notes v0.1 → v1.0

- Source baseline raised to Customer Phone Hotfix (migration 000025), not old M19.25A.
- Dynamic Lifecycle, profile-required validations and **nonunique Supplier phone** added to tasks/tests.
- Approved 12 Product decisions; bank secure subtask explicitly mandatory with Security gate.
- Operator-owned frontend HTTP/UAT separated from offline/source evidence; no mandatory M19.25B fixture gating development.

## 12. Corrective engineering checkpoints v1.0.1

| Checkpoint | Must verify before advancing | Responsible / gate |
|---|---|---|
| **EN-01 — Numbering divergence** | CUS `AUTO_IF_EMPTY` manual behavior is **not** permitted by SUP DEC-05 if a default is configured. Test no-default manual, inactive-default deny, active-default auto, requested series and concurrent counter+unique. | Backend/Numbering · MOD-G1/G3/G5 |
| **EN-02 — Lifecycle graph** | Resource binding valid and not overwritten by seeder; initial DRAFT accepted; branching `setstate` rejected safely unless Workflow canonical API extended by reviewed ADR; SetState uses expected_version. | Workflow/Backend · G1/G2/G5 |
| **EN-03 — Bank audit and deletion** | Define proven durable sink for reveal/write/clear, audit fields and retention, key injection and encryption envelope; no silent reuse of `configuration_audit_events`. If a new audit table is required, document Security/DBA schema exception before migration. | Security/DBA · **BLOCKS T01 bank columns/T04B and G7** |
| **EN-04 — Wire compatibility** | Current CUS list uses flat `status`, `company_id`, `customer_group_code`, page default 25. SUP adopts flat `status`,`company_id`,`supplier_group_code`,`supplier_type`, page default 25/cap 100; FE adapter maps `ListQuery.filter`. | API/FE · MOD-G2 |
| **EN-05 — 4-owner Contact** | Extend ContactSQL, ContactService, parent checks AND production audit compilation for ORG/EMP/CUS/SUP. Preserve P1 nine audited resource entries and regression baseline; no unrestricted fallback. | Backend/PBAC · G1/G3/G4 |
| **EN-06 — Company/PUR boundary** | Supplier eligibility only, not PBAC. `SupplierUsabilityGuard` requires trusted persisted document Company; no real Purchase processing in G1. Do not count SUP-001-03/05 integration AC PASS until PUR. | PUR owner + Security · G5/deferred PUR |

Operator QA remains direct frontend/Network UAT per project agreement. All tasks after T00 remain `NOT_RUN` until real source/runtime evidence. Do not use any old example code block with `filter[status]` as the SUP API wire contract.
