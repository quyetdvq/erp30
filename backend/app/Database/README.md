# ERP database ownership — Backend development phase (26/10/2026)

`backend/app/Database` is now the **active** source of ERP business schema history and bootstrap data. The old `backend_baseline/app/Database` remains **unchanged as a historical reference**. This port deliberately does not deploy Notifications, alter a running database, or mark old migrations as applied.

## Inventory

- 18 inherited CI4 migrations (`2026-10-05-000001` → `2026-10-15-000018`) in `Migrations/`; original file names, classes, DDL and migration timestamps were retained byte-for-byte.
- 4 inherited seeders (`GroupRootSeeder`, `ResourceActionSeeder`, `WorkflowStateSeeder`, `PrivilegedAuthorizationGroupSeeder`) in `Seeds/`. Some Seeder runtime/permission semantics were adapted, see below.
- 1 *new* coordinator `Seeds/BackendBootstrapSeeder.php` calls all four in dependency order.
- 1 supporting class `UserDisplayMetadataResetPolicy.php` was ported from baseline because migration 018 requires it.
- `app/Commands/LegacySchemaPreflight.php` provides a **read-only** database/migration-history diagnostic (no DDL, no ledger writes).

## Required procedure before running on the existing ERP database

1. Back up the real database (including data, schema, and `migrations` history), take a separate DEV clone, and inspect the actual CI4 connection parameters. The known installation uses MariaDB 10.4.32, physical `dinktech_` table prefix, InnoDB and UTC DB session. **`Config/Database.php` default `DBPrefix=''` does NOT establish the current runtime value.** Check the actual `.env`/configuration. Do not copy passwords into reports.
2. With Composer dependencies installed, from `backend/` run `php spark db:legacy-preflight` (read-only) and `php spark migrate:status`. Confirm the 18 application migration version records, the physical table names and a schema diff (`SHOW CREATE TABLE`) against the inherited files.
3. **If the business tables already exist but their corresponding migration history is missing, do not run `php spark migrate` yet.** Reconcile history and existing schema using a reviewed, backed-up, clone-tested procedure. The preflight intentionally refuses to write history or infer that a matching table name proves matching schema. CI4's legacy migration version/class/namespace/batch fields must be matched to the actual runtime version before adoption.
4. The first eight migrations create 30 business tables and migration 011 creates the audit table; migrations 009–018 also modify existing schema and authorization metadata. Therefore neither a blanket `IF NOT EXISTS` conversion nor a naive mass insert into the migration ledger is an acceptable adoption strategy. Use a schema-by-schema diff, not just a table-count check. **Do not run `migrate:rollback` against a populated ERP database.** Several inherited `down()` methods drop tables or columns.
5. Use a disposable **fresh** schema only when deliberately testing initial bootstrap. Install Shield and the project dependencies first; then run the appropriate CI4/Shield migration commands (`php spark migrate --all` on an isolated, empty test schema) and verify the final structure. Check the migration order and any Shield-table dependencies (especially migration 018).
6. After migration/ledger reconciliation, run `php spark db:seed BackendBootstrapSeeder` **only on the intended DEV schema**, after reviewing current `SYSTEM_ADMIN` rights and Organization root state. Repeat on a clone to confirm no accidental duplicate grant/state flip. `db:seed` is an explicit write operation.

## Changes to the four ported Seeders

- `GroupRootSeeder`: replaced the *missing* old `MariaDbDomainLockService` with `GET_LOCK`/`RELEASE_LOCK` using exactly the live Organization module's lock name `dinkerp.m52.organization.tree`. Uses the same DB connection for the lock and transaction.
- `ResourceActionSeeder`: preserves the original type/domain/action catalog, but **refuses** to overwrite an existing resource domain or reactivate a retired Contact resource. Existing native resource states are preserved. Uses explicit transaction/error handling instead of silent completion.
- `WorkflowStateSeeder`: preserved state codes; hardened transaction/error handling. Existing states are untouched.
- `PrivilegedAuthorizationGroupSeeder`: when `SYSTEM_ADMIN` is **new**, creates the group and initial approved grants; when it already exists, **does not** reactivate it or silently restore permissions deliberately revoked by an administrator. To add rights to an existing admin group, explicitly approve/manage PBAC grants. Existing approved-action list stays the same at this port stage.

`BackendBootstrapSeeder` does not wrap all four seeders in one global transaction; each seeder owns its transaction. A failure can leave earlier seeds committed; re-check state and run the explicit repair/reconciliation procedure instead of assuming global rollback.

## M11 next steps (not implemented by this port)

- Create a **new** migration after 018 for four Notification tables, with DB-native constraints and validated Shield FK types. Reuse the existing `configuration_audit_events` table.
- Create a separate Notification catalog/policy seeder for the new `notification_setting` resource, `view/edit` actions, and settings registry. Do not auto-grant new PBAC rights to existing admins without a reviewed decision.
- Extend `ResourceStateCatalog` (`notification_setting=ALL_STATES`) and the domain/authorization policy, then implement Notification services/worker/API/frontend as specified in the M11 design.
- Replace milestone-fingerprint/frozen-route rewrite checks with feature-oriented migration, PBAC, transaction, HTTP/CSRF, worker and frontend tests. Previous rewrite-milestone gates are historical, not release criteria for new M11 code.

**Verification for this source port:** PHP syntax and static cross-tree inventory can be checked offline. No MariaDB schema migration, seed execution, HTTP integration or M11 notifications were executed by the source port.

## M11.1A Notifications (new in v1.4)

The migration ledger on the user's `erp` DB shows all 18 legacy App migrations present and 31/31 business tables (`db:legacy-preflight`, 26 Oct 2026). This is **not** proof that new M11 schema has been applied.

- Work on a **backed-up DEV clone**. Verify `DBPrefix=dinktech_`, exact `SHOW CREATE TABLE` for `users`, `resources`, `resource_actions`, `configuration_audit_events`, and isolation/engine before applying schema 019.
- Run `php spark migrate:status`, then `php spark migrate` to apply `2026-10-26-000019_CreateNotificationTables`. MariaDB 10.4 may leave **partial DDL** if interrupted; if it fails, inspect all four new tables and ledger, rather than blindly retrying. By default `down()` refuses to delete notification data. Destructive DEV rollback requires `DINKERP_M11_ALLOW_SCHEMA_DROP=DEV_CLONE_ONLY` and must not be used in production.
- Run `php spark db:seed NotificationSeeder`, then repeat and verify six policy keys and exactly one `notification_setting` Resource with `view/edit` actions. Re-seeding must not reset an administrator's current defaults or re-grant revoked permissions.
- **No implicit PBAC grant:** provision `notification_setting.view/edit` for an authorized pilot actor using existing Access management. Personal Preferences need only an authenticated Shield session; policy endpoints still require PBAC.
- Use `DINKERP_M11_SETTINGS_WRITE_MODE=enabled` only in the intended DEV environment for PATCH; reads remain available after migration and seeding. This is independent of `DINKERP_M33B_WRITE_MODE`. All Import event producers and worker are still inactive in M11.1A.
- Validate HTTP 401/403/409/422, atomic multi-key PATCH and no-op/audit behavior, duplicate preference insert race, epoch lock/unlock, CSRF token refresh, and migration rollback procedure; then execute `pnpm openapi:generate`, `pnpm type-check` and frontend smoke tests in the full project environment. The ZIP environment for this patch lacks Composer/vendor and frontend node_modules, so runtime checks were **not** executed here.
