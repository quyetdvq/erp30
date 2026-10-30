# P2-SUP / SUP-T05 — Purchase Usability Guard: Source QA

**Date:** 2026-10-30
**Baseline:** `erp_v2.0.0_sup_t04_core_transaction_cas_source.zip` (SUP-T01A through T04).
**Status:** SOURCE / OFFLINE TESTED. The Purchase module/provider is **not implemented**. NOT database, HTTP, or authorization UAT.

## Contract frozen for implementation

T05 is a **transaction participant for a new Purchase mutation**, not an automatic Supplier selector and not Supplier Master `setstate`.

1. The **Purchase service** owns transaction start/commit and performs Purchase-specific actor PBAC. It must load/lock an **already-persisted Purchase document**; the `AuthorizedPurchaseCompanyPort` returns the Company's stored ID. Never accept a body/query/session `company_id` as authority. The code intentionally ships **no permissive Purchase port implementation** because PUR is deferred.
2. T05 refuses to run without an active caller transaction. Its three bounded `FOR UPDATE` queries read the Supplier (restricted to the trusted Group), the Company and the Supplier/Company Usage row. Supplier-row lock is acquired before Company-row lock, matching the T04 Supplier→Company write lock order. It does not begin, commit or roll back the Purchase transaction.
3. Canonical `UnifiedAccessService::canView('supplier', persistedRow)` is required. A denied/invisible Supplier returns a generic unavailable error. The Purchase adapter owns authorization to mutate the Purchase document; **Supplier Usage is never a PBAC grant**.
4. Only `status=ACTIVE` is Purchase-eligible; dynamic `DRAFT`, `PENDING`, `APPROVED`, `INACTIVE` are not considered operational. A custom Lifecycle does not become Purchase-eligible merely because its state is initial. Only an approved business change may widen this list.
5. Company must exist, be `COMPANY`/`ACTIVE`, and have the trusted GROUP root as parent. `all_companies=1` requires no usage rows; `all_companies=0` requires an explicit row for the persisted Purchase Company. Corrupt mode or incorrect scope fails closed.
6. Existing PO/GR/Return/Finance history is **not** blocked by Supplier becoming INACTIVE; T05 only guards *new* Purchase work. This does not implement the deferred UR-SUP-002 transaction history. There is no effective-dated eligibility or historical/as-of grant.
7. Projections do not read any `bank_*` data, no new migration, Resource Action, PBAC grant, API route, or frontend component.

## Offline acceptance

- `php tests/sup/supplier_usability_policy_pure.php` — pure eligibility/status/usage rules.
- `php tests/sup/supplier_usability_guard_fixture.php` — fake Purchase context, fake DB and fake PBAC, negative status/company/scope/transaction/query failure cases.
- `php tests/sup/supplier_usability_source_contract_pure.php` — source isolation and future Purchase port interface.
- `tools/test-unit.ps1 RUN SUP-UNIT` and `RUN UNIT-STANDARD` include the above tests on Windows.
- Existing P1 frozen Routes and SQL/PBAC standards remain unchanged.

## Integration/UAT blockers

- T00 bank metadata encryption/key/audit signoff + migration `000028` DEV review are still pending; **do not run migrations or Supplier catalog provisioning** on live data from this source patch.
- Purchase document schema, its Company owner contract and `purchase_order.create/edit` policy do not exist. A real Purchase adapter must be reviewed separately for persisted authority, document locks, no client-scope trust and concurrent mutation.
- On an isolated, approved DEV clone: test ACTIVE vs DRAFT/INACTIVE; Company allowed vs out of scope; Supplier denied for one of two actors; Company disabled after selection; actual InnoDB lock/race between Supplier usage edit and PO write; rollback of PO on Supplier denial; no leakage of bank fields; historical PO remains readable after Supplier deactivation.
- T05 is **not** a replacement for Purchase authorization and cannot be considered a complete runnable G1 Purchase integration.

## Next module dependencies

T06 Shared Contact polymorphic owner resolution and PBAC integration; T07 Supplier HTTP/OpenAPI/History and separately reviewed Bank Security; T08 frontend. Runtime acceptance remains blocked until T00 and MariaDB/HTTP evidence exist.

## Verified in this isolated environment

| Check | Result | Boundary |
|---|---|---|
| SUP-T05 pure policy | **29/29 PASS** | No DB |
| SUP-T05 guard fake DB/port | **37/37 PASS** | No MariaDB/Purchase/PBAC runtime |
| SUP-T05 source contracts | **30/30 PASS** | Source wiring only |
| Existing SUP-T01..T04 scripts | PASS | Offline/fake DB |
| Pinned M3.4B offline | **168/168 PASS** | No DB |
| M19.24 Route Architecture | **476/476 PASS** | No HTTP |
| Customer Core/Source/SQL | **94/94, 94/94, 48/48 PASS** | Offline |
| P1 SQL/PBAC standards | **11 suites PASS** | No MariaDB/HTTP |
| Windows PowerShell SUP-UNIT / real SQL locks | **NOT RUN** | Needs operator / approved DEV clone |

**Windows commands:**

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 RUN SUP-UNIT
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN ROUTES
.\tools\test-unit.ps1 RUN P1-STANDARDS
```
