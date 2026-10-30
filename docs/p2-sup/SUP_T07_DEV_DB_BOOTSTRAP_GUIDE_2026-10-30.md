# SUP-T07 DEV database bootstrap — one reviewed sequence

**Owner decision:** SUP-T06 five decisions approved for implementation; technical signoffs are not implied.
**Scope:** DEV clone only; no live/production DB, no implicit PBAC grants, no bank endpoints or business data.

## Current source reality

- Migration `backend/app/Database/Migrations/2026-10-30-000028_CreateSupplierCoreTables.php` already exists and creates `suppliers`, `supplier_company_usages` (including **unreviewed bank metadata columns**). It has **not** been executed here.
- `SupplierCatalogProvisioner` already transactionally/idempotently provisions MASTER resources `supplier` (6 actions) and `supplier_contact` (4 actions), using Supplier Lifecycle and existing `customer_contact` Lifecycle; it never assigns user/group grants or PBAC policies. Existing alternate CLI: `php spark supplier:catalog-provision CONFIRM_SUPPLIER_CATALOG_ONLY`.
- New `SupplierCoreSeeder` is a **thin explicit wrapper**, not a duplicate permission matrix. It is **not** called from `BackendBootstrapSeeder`. It requires `ENVIRONMENT=development` plus the explicit `DINKERP_SUP_CATALOG_SEED_CONFIRM=DEV_CLONE_CATALOG_ONLY`. This human acknowledgement **does not verify** the physical database identity.

## Before any DB write (mandatory)

1. Verify actual connection target (host/schema/prefix) is a dedicated, isolated, **backed-up DEV clone**; do not rely on the environment label alone. Confirm 26/27 migration history and that **only the intended App 000028** is pending. Check `php spark db:legacy-preflight` and `php spark migrate:status` against the same DB.
2. `000028` contains `bank_ciphertext/nonce/tag/key_version/last4`. The **PC-03 Security + DBA schema/crypto/audit review is still PENDING**; do **not** run this migration until the exact DDL/layout and durable audit plan are approved. No automated tool in this package bypasses that gate.
3. Confirm Customer Core and `customer_contact` MASTER Resource are present and have a valid Lifecycle with exactly one active initial state, and ACTIVE/INACTIVE Workflow State catalog rows are available. Catalog provisioning refuses missing/conflicting prerequisites, and will not rewrite an existing customized Lifecycle.

## After approved DEV-clone preflight and PC-03 schema signoff

From `D:\XAMPP\htdocs\erp\backend`:

```powershell
php spark migrate:status
# Only if App 000028 is the sole reviewed pending migration:
php spark migrate
if ($LASTEXITCODE -ne 0) { throw 'STOP: Migration failed; inspect DDL and ledger, do not retry blindly' }
php spark migrate:status

$env:DINKERP_SUP_CATALOG_SEED_CONFIRM = 'DEV_CLONE_CATALOG_ONLY'
try {
    php spark db:seed SupplierCoreSeeder
    if ($LASTEXITCODE -ne 0) { throw 'STOP: SupplierCoreSeeder failed' }
    php spark db:seed SupplierCoreSeeder
    if ($LASTEXITCODE -ne 0) { throw 'STOP: SupplierCoreSeeder idempotence failed' }
} finally {
    Remove-Item Env:DINKERP_SUP_CATALOG_SEED_CONFIRM -ErrorAction SilentlyContinue
}
```

Do **not** run both the `supplier:catalog-provision` command and `SupplierCoreSeeder` to accomplish a single seed: either interface calls the same provisioning implementation. Re-running the chosen one is the idempotence test.

## Expected catalog (read only)

Check physical DBPrefix before using these sample `dinktech_` table names:

```sql
SELECT r.type, r.domain, r.status, r.lifecycle_id,
       COUNT(a.code) AS action_count,
       GROUP_CONCAT(a.code ORDER BY a.code SEPARATOR ', ') AS action_codes
FROM dinktech_resources r
LEFT JOIN dinktech_resource_actions a ON a.resource_id = r.id
WHERE r.type IN ('supplier', 'supplier_contact')
GROUP BY r.id, r.type, r.domain, r.status, r.lifecycle_id
ORDER BY r.type;
```

Expected two ACTIVE MASTER resources with non-null Lifecycle ID: `supplier` has exactly 6 actions (`view`, `create`, `edit`, `setstate`, `view_bank`, `edit_bank`); `supplier_contact` has exactly 4 (`view`, `create`, `edit`, `setstate`). Bank actions are **catalog entries only**, not usable bank endpoints or grants. No automatic permissions, policies, group grants, numbering configuration, supplier business rows or runtime API flag changes are part of seeding.

**Acceptance boundary:** Passing this source patch + fake-provisioner unit test proves only Seeder wiring. Actual MariaDB migration/catalog consistency, two-actor PBAC/HTTP and frontend UAT must be evidenced independently. On a migration failure, inspect InnoDB tables, FK constraints, partial DDL and CI4 migrations ledger before any retry; do not use automatic rollback on an existing DB.
