# P2-SUP / SUP-T06A — Shared Contact backend source QA

**Date:** 2026-10-30
**Source baseline:** `erp_v2.1.0.zip`
**Baseline ZIP SHA-256:** `3de9f21c303a76f2103d5e2ab335f391626115937c5ceb6baf5dc5656b76589b`
**Delivery:** `DinkERP_P2_SUP_T06A_Shared_Contact_Backend_Source.patch`
**Status:** **BACKEND SOURCE PASS; OFFLINE TEST PASS; SUP-T06 OVERALL PARTIAL.** PC-06 TECH REVIEW PENDING; DEV UAT NOT_RUN; RELEASE ACCEPTED = NO.

## Authority and scope

- P2-SUP design decisions v1.0.1 (12 APPROVED), implementation plan SUP-T06, technical design section 3, PC-06 pre-code review, CUS prevention checklist.
- Extends existing `contacts` table with owner `contact_type='SUPPLIER'`, `contact_ref_id=<persisted supplier id>`; **no new table or migration**, no owner transfer, no embedded Supplier-create Contact payload.
- `supplier_contact` is its own GROUP/MASTER PBAC resource (VIEW/CREATE/EDIT/SETSTATE). Every Supplier Contact operation also requires independent **parent `supplier.view`**; Supplier parent must belong to actor's **trusted GROUP root** via persisted `suppliers.group_organization_id`. Company Usage is NEVER an authorization grant.
- Parent VIEW checks use canonical `UnifiedAccessService` and dynamic Lifecycle states, not a hardcoded ACTIVE filter or a synthetic Company scope. Do not require `supplier.edit` to create Contact.
- Supplier Contact row's own `status` and parent Supplier `status` are separately compiled as canonical PBAC-state predicates in SQL before COUNT/OFFSET/LIMIT and detail. Incomplete/mismatched SQL scope configuration fails closed. Identifiers from reviewed DBPrefix adapter; all data values bound.
- List parent verification uses **one batched Supplier lookup per page** in the same read snapshot and canonical per-parent VIEW cache. No per-row Supplier SELECT. Supplier mutations recheck parent via `ContactService::assertVisible`, and creation checks it before INSERT; owner `contact_type`/`contact_ref_id` cannot change by PATCH.
- Workflow OPTIONS/transitions, notification assignee resolution and notification delivery validate the same Supplier parent owner and VIEW. Missing, denied or wrong-GROUP parent fails closed. No extra route or M8.7 audit resource.
- M8.7 audit Contact plan mirrors production **four owner branches / six PBAC scopes**: Organization, Employee, Customer Contact, Customer parent, Supplier Contact, Supplier parent. Independent read-only oracle uses Supplier persisted parent guard. Original **nine-resource** audit catalog remains unchanged.
- Old Organization/Employee/Customer Contact logic and frozen P1 routes are not changed to grant Supplier rights; source regression scripts for P1 and CUS remain authoritative.

## Change boundaries

Backend code changes include: `ContactRules`, `ContactScope`, `ContactSql`, `ContactService`, new `SupplierContactParentView`, reviewed `Access` allowlists/SQL compiler/row actions, `BusinessWorkflowRules/Service/Display`, notifications assignment/delivery, M8.7 Contact SQL plan/oracle; corresponding pure/source/fixture tests and PowerShell offline runner. Contact module design record is updated. **No frontend, generated client, canonical OpenAPI, Supplier API route, Purchase adapter, database migration, catalog provisioning, default permission grant or production data write.**

## Verified offline in an isolated source copy

| Gate | Result | Evidence boundary |
|---|---|---|
| SUP-T06 Contact SQL/dual VIEW/persisted GROUP | **35/35 PASS** | Pure plans only; no SQL server |
| SUP-T06 Contact parent/VIEW/one-page batch | **15/15 PASS** | Fake DB/PBAC only |
| SUP-T06 source runtime/audit/Workflow contract | **39/39 PASS** | Source assertions only |
| M19.25A Contact Audit regression | **25/25 PASS** | Scope source assertions incl negative mutation |
| Supplier T01–T05 (13 scripts) | PASS | Offline; fake DB where marked |
| CUS (4 scripts) and old Contact/P1 regression | PASS | Offline |
| M3.4B frozen mutation + suite | **29/29, 168/168 PASS** | Offline |
| M19.24 Routes Architecture | **476/476 PASS** | Frozen routes |
| Routes registry / M3.3 / M3.4D | PASS | 182 registry entries, 180 non-OPTIONS registrations; 150/150 P1 |
| PHP lint changed/new PHP files | PASS | Syntax only |
| Windows PowerShell aggregate test commands | **OPERATOR NOT_RUN for new patch** | Linux PHP individual scripts run; operator must run `test-unit.ps1` |
| MariaDB/PBAC multi-actor, EXPLAIN, concurrency | **NOT_RUN** | DEV clone only |
| Authenticated HTTP/Vue/browser, FE type/build | **NOT_RUN** | MOD-G2 frontend contract pending |
| Security/DBA T00, Supplier migration 000028 | **BLOCKED/NO EVIDENCE** | Never auto-run migration |

## Required negative/UAT evidence before full T06 acceptance

1. At least two Supplier Contacts under the same authorized parent: COUNT/page/detail parity; authorized actor can see all, unauthorized actor sees none (no leaked `total`, `allowed_actions`, label, direct ID).
2. Two independent deny cases: `supplier_contact.view` allowed but `supplier.view` denied; inverse; Lifecycle/DENY/ABSOLUTE_DENY variants. No cross-GROUP parent, missing/deleted Supplier, invalid owner ID, noncanonical status visible.
3. Contact CREATE/PATCH/SETSTATE, Workflow OPTIONS/execute, Notification assignee and target delivery deny when parent VIEW or GROUP fails, including concurrent state changes; rollback must leave no orphan Contact/notifications.
4. Parent per-page query batching + SQL `EXPLAIN` on representative contacts and indexes. Repeat COUNT/page/detail for ORG/EMP/CUS owners, NULL/Unicode/sort, M8.7 oracle with the original nine audited resources. No change to frozen P1 routes.
5. Supplier catalog, Supplier lifecycle, Contact legacy resources and parent Supplier data must all be ready on a reviewed **DEV clone** before exercising runtime; no live data migration/provisioning through this patch.

## Explicit blockers / remaining work

- **PC-06**: Security/backend review of the four-owner Contact SQL/PBAC and runtime/audit parity, plus negative tests on real DEV clone. Source tests are not PC-06 signoff.
- **T00/T01B**: Bank Security/DBA preflight, migration `2026-10-30-000028`, Supplier catalog/Lifecycle/PBAC provisioning and associated MariaDB evidence unresolved. The new source expects those catalog resources to exist; do not deploy it to a runtime lacking them (legacy Contact queries also compile the complete approved scope set).
- **MOD-G2 / T07 / T08**: API/OpenAPI request/response contract and typed/generated frontend client for SUPPLIER Contact, Supplier page embedded Contact UI, browser UAT. Do not hand-edit generated frontend code to mimic a frozen contract.
- **T05 Purchase adapter, T04B bank, T09 DEV performance/security acceptance** remain separate dependencies. No release acceptance from this patch.

## Operator application / regression (read-only/offline)

```powershell
cd D:\XAMPP\htdocs\erp
$patch = '.\DinkERP_P2_SUP_T06A_Shared_Contact_Backend_Source.patch'
git status --short                     # must be clean (or reviewed) before apply
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: patch mismatch' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: patch apply failure' }
git diff --check
if ($LASTEXITCODE -ne 0) { throw 'STOP: whitespace/diff failure' }
cd .\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN SUP-UNIT
.\tools\test-unit.ps1 RUN CUS-UNIT
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN ROUTES
.\tools\test-unit.ps1 RUN P1-STANDARDS
```

For rollback **before subsequent edits**: return to repository root and use `git apply --reverse --check $patch` then `git apply --reverse $patch`; do not run migrations or provisioning as part of source patch application.
