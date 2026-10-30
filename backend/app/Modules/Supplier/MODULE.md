# Supplier Module — P2-SUP-G1

**Owner:** `App\Modules\Supplier` — Group MASTER Supplier identity; Shared Contact parent via `supplier_contact` resource.

**Stage:** SUP-T01A through SUP-T05 source/offline components (including T04 atomic writes and T05 Purchase eligibility guard). **Not a deployed Supplier API or Purchase integration.**

## Ownership and boundaries

- Owns tables `suppliers`, `supplier_company_usages` (migration `000028`, pending DEV/schema/security review).
- Owns pure `SupplierRules` and catalog provisioning `SupplierCatalogPolicy` / `SupplierCatalogProvisioner`.
- Catalog operation is **manual**, after migration: `php spark supplier:catalog-provision CONFIRM_SUPPLIER_CATALOG_ONLY`.
- Catalog registers Lifecycle-backed `supplier` and `supplier_contact` MASTER resources; actions: `supplier.view/create/edit/setstate/view_bank/edit_bank`, `supplier_contact.view/create/edit/setstate`. No export, notes or delete API yet.
- A missing Supplier Lifecycle may be seeded as `SUP_ACTIVE_INACTIVE` for a **new** resource only. Existing resource bindings and Lifecycle states, including a configured `DRAFT` initial state, are never overwritten.
- Supplier Contact follows the existing Customer Contact Lifecycle. Supplier provisioning does not inspect Organization/Employee Contact resources, which are separately owned and authorized.
- **No PBAC grants, policies, Group membership or bank endpoints** are created by the provisioner; `view_bank`/`edit_bank` are dormant catalog actions until Security/DBA approval and secure endpoint implementation.
- SUP-T02 owns `SupplierNumberingPolicy` and `SupplierNumberIssuer`. The issuer runs only inside a future SupplierService transaction and Supplier-master advisory mutex; it uses the existing P1 Numbering Series and counter tables, locks resource/config/counter rows, and NEVER creates a default Series or performs a commit.
- SUP-DEC-05 differs from Customer: any configured default (even INACTIVE) blocks manual fallback; an ACTIVE valid default forces AUTO regardless of AUTO_IF_EMPTY; requested Series cannot bypass a broken default.
- SUP-T03 adds `SupplierScopeProvider`, `SupplierSql` and `SupplierReadService`: strictly scoped COUNT/page/direct detail, server-owned Group root, Company Usage eligibility-only EXISTS, authorized Lifecycle State/row action hints, no Bank columns in generic reads. Read layer has no public Supplier routes yet.
- T04 adds atomic writes and CAS and must integrate the Numbering issuer and catch SQL 1062; T06 extends shared Contact owner; T07 adds HIS integration/API. History remains owned by the Shared History module.

## Current safety gates

Do not apply migration `000028` or run provisioning on a real database before DBA and Security sign off on the bank metadata layout and existing DEV schema. Avoid any automatic running of migration/seed from API or global bootstrap. No authenticated HTTP UAT, actual SQL EXPLAIN, PBAC parity or Supplier DB verification has been completed.

## Offline tests

- `php tests/sup/supplier_rules_pure.php`
- `php tests/sup/supplier_catalog_pure.php`
- `php tests/sup/supplier_catalog_provision_fixture.php` (in-memory fake connection; **not MariaDB**)
- `php tests/sup/supplier_sql_plan_pure.php` (SQL plan, bind and exclusion contracts)
- `php tests/sup/supplier_read_source_contract_pure.php` (source wiring contract, not DB)
- `tools/test-unit.ps1 RUN SUP-UNIT` on Windows

## T01B.1 proactive CUS regression gate

- Pure create/PATCH input parser now accepts only Supplier profile fields; scope, version, Numbering and bank data are separate validated contracts.
- Required profile must be verified again on merged persisted PATCH; Numbering and location checks still require the future Service/DB.
- Full QA gap matrix: `docs/p2-sup/SUP_T01B1_CUS_LESSONS_PREVENTION_GATE_2026-10-30.md`.

## SUP-T04 Core writes (offline source, not HTTP/DB accepted)

- `SupplierWritePayload` is the only write-body field allowlist. Raw request JSON must never be forwarded to a query builder.
- `SupplierWriteService` owns Supplier-master advisory mutex, one physical transaction, canonical PBAC snapshot, locked persisted facts, versioned CAS, atomic Company Usage (one bounded bulk Company lock) and Shared History. `SupplierNumberIssuer` is a participant, never a second transaction owner.
- `SupplierService` is the future API facade: its `list/show` read through the T03 SQL-first adapter and `create/update/replaceCompanyUsage/setState` delegate to T04. No bank operations or HTTP endpoints exist here.
- All mutations require the canonical Group, target row VIEW plus action authorization (except CREATE), and version match (except CREATE). State is resolved via Lifecycle and cannot be directly edited; no SQL SELECT * or bank fields in returned rows.
- T06 Contact creation must join the same T04 transaction before one History CREATE. Workflow notifications and bank operations remain blocked until their own review.
- No migrations/provisioning are executed by these services or offline tests. Run `SUP-UNIT` for deterministic pure/fake DB coverage; MariaDB/HTTP/PBAC deployment UAT is still required.

## SUP-T05 — New Purchase Usability Guard (source only)

- `SupplierUsabilityGuard::assertUsableForNewPurchase` runs **only within a Purchase-owned open database transaction**. It never begins/commits/rolls back a transaction, does not create Purchase rows, and has no API route.
- The mandatory `AuthorizedPurchaseCompanyPort` must be implemented by the future Purchase module: it must load/lock the **persisted Purchase document**, authorize the acting user for the relevant Purchase mutation on that document, and return the stored Company ID. There is deliberately **no fallback adapter** from HTTP/company selection, and no Purchase permission is granted by Supplier.
- Within the same transaction, T05 locks the Supplier, Company and at most one usage row; enforces server-owned Group, canonical `supplier.view`, ACTIVE Supplier, ACTIVE Company directly under the Group, and valid All/list Company Usage. It never reads bank columns.
- Only an explicit Supplier `ACTIVE` is operational for **new** Purchase work. Custom Workflow initial/approval states are *not* auto-operational. A future product decision that changes eligible codes requires an explicit review/test update.
- Existing PO/GR/Return/Payment history remains available according to its own document PBAC; do not use T05 to filter historical reads. Effective-dated historical eligibility is not implemented (the database usage relation has no validity window).
- T05 cannot be called end-to-end until Purchase supplies the persisted document/authorization adapter. Fake DB/port tests do not prove MariaDB locks, concurrent Purchase vs Supplier updates, or Purchase PBAC integration.
