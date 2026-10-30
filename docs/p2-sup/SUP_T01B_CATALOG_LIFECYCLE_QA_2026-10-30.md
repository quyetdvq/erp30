# P2-SUP — SUP-T01B Catalog/Lifecycle Provisioning Review

**Baseline:** `erp_v2.0.0_sup_t01a_review_source.zip` (after FE-PRODUCT-DETAIL-02).
**Decision authority:** SUP-DEC-01..12 accepted; technical design v1.0.1; no new business scope.
**Status:** SOURCE/OFFLINE REVIEW PASS; **NO DEV DB, HTTP or Supplier runtime acceptance**.

## Implementation delta

| Artifact | Purpose |
|---|---|
| `backend/app/Modules/Supplier/SupplierCatalogPolicy.php` | Pure allowlist, Resource/Lifecycle/Contact invariants, 20-char status guard |
| `backend/app/Modules/Supplier/SupplierCatalogProvisioner.php` | Transactional, idempotent, explicit catalog inserts only; stale Shield grant and unexpected Action guards |
| `backend/app/Commands/SupplierCatalogProvision.php` | `supplier:catalog-provision CONFIRM_SUPPLIER_CATALOG_ONLY` opt-in command, never runs automatically |
| `backend/app/Modules/Supplier/MODULE.md` | Ownership, boundaries, security and dependency contract |
| `backend/tests/sup/supplier_catalog_pure.php` | Pure negative state/contact/resource/action tests |
| `backend/tests/sup/supplier_catalog_provision_fixture.php` | Fake-connection idempotency and rollback tests; **not a MariaDB integration** |
| `backend/tools/test-unit.ps1` | Add SUP-UNIT runner, include two SUP tests and fixture in UNIT-STANDARD |
| `backend/tests/m541_module_ownership_pure.php` | Preserve all 16 pinned backend module owners and explicitly allow only Supplier as the 17th |

## Provisioning behavior

- Adds MASTER Resource `supplier`, `supplier_contact` without any Base Permission, ERP Group Permission, PBAC policy, default Numbering series, bank endpoint or grant.
- Supplier actions: `view`, `create`, `edit`, `setstate`, `view_bank`, `edit_bank`. Bank actions remain **dormant** until Security/DBA approve encryption/key custody and a durable bank-access audit; **no bank API** exists.
- Supplier Contact actions: `view`, `create`, `edit`, `setstate`.
- For a missing Supplier Resource, use existing `SUP_ACTIVE_INACTIVE` lifecycle if already defined and valid; otherwise create ACTIVE/INACTIVE default with initial ACTIVE and transitions. **Never rewrite** an existing lifecycle, its initial-state flags, or existing Resource linkage; supports custom DRAFT initial if configured.
- New Supplier Contact follows **Customer Contact** Lifecycle only. The customer_contact resource must be ACTIVE, MASTER and Lifecycle-backed; Organization/Employee Contact lifecycle or status are independent and do not block SUP. Conflicts with Customer Contact fail closed; no automatic reconciliation.
- Reject unexpected Actions or stale direct Shield grants for missing actions before creating new catalog entries. Transaction rollback on error, non-destructive rerun.
- Does **not** enable PBAC/SQL read access, Contact SUPPLIER owner operations or Supplier APIs. Those belong to T03/T06/T07.

## Verification

- `SUP-T01A`: 14/14 pure checks PASS.
- `SUP-T01B catalog`: 36/36 pure/source checks PASS (historical T01B baseline).
- `SUP-T01B provision fixture`: 14/14 checks PASS (fake connection only).
- `M5.4.1 module ownership`: 44/44 PASS, exact 16 baseline + Supplier.
- `M3.4B offline`: 168/168 PASS.
- `M19.24 route architecture`: 476/476 PASS; no new HTTP route.
- PHP lint: PASS for new PHP files.
- Database migration / transaction on actual MariaDB: **NOT RUN**.
- Authenticated PBAC / HTTP UAT: **NOT RUN**.
- Bank security review: **BLOCKED** for activation.

## DEV runbook — after T00 engineering signoff only

1. Validate actual DEV clone, DBPrefix, backup, pending migrations, and physical DDL for `000028`. Do not blindly migrate: nullable bank metadata needs Security/DBA review.
2. After approved schema migration, run `php spark migrate:status` and inspect `suppliers` / `supplier_company_usages` physical table DDL.
3. Only then optionally run `php spark supplier:catalog-provision CONFIRM_SUPPLIER_CATALOG_ONLY` on the isolated DEV clone. This command **writes catalog rows**.
4. Run it twice; second run must be idempotent. Inspect `resources`/`resource_actions` and Lifecycle IDs. Confirm **zero** new `group_permissions`, `policy_actions`, Shield direct permissions and PBAC policies.
5. Assign permissions/policies to test users **only via approved security administration** after SQL/PBAC implementation. Do not grant permissions to Supplier before T03.

## Release boundary

SUP-T01B only certifies catalog source. SUP-G1 is not yet ready. Next work is SUP-T02 Numbering, SUP-T03 SQL-first PBAC, SUP-T04 CRUD/CAS and later Shared Contacts, bank security and HIS integration. SUP History (PO/GR/Return/Finance) remains deferred until source modules are available.

## T01B.1 Contact Lifecycle correction

The original T01B requirement that **all three existing P1 Contact owner resources** share the same Lifecycle was stricter than necessary and could reject legitimate P1 customization. Supplier Contact now binds to the **current Customer Contact Lifecycle** only; Organization and Employee Contact are deliberately not queried or modified by Supplier provisioning. No existing Lifecycle or Resource is mutated. See `SUP_T01B1_CUS_LESSONS_PREVENTION_GATE_2026-10-30.md` for regression evidence.
