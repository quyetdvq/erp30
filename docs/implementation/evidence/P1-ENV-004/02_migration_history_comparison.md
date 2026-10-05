# P1-ENV-004 Migration History Comparison

## Configuration

- `Config\Migrations::$table = 'migrations'`.
- Both configured database groups use prefix `dinktech_`, so the physical history table is `dinktech_migrations`.
- Development database: `erp`.
- PHPUnit test database: `erp_test`.

## Pre-reset database history

Read-only inspection before the approved test reset showed:

- `erp`: CodeIgniter Shield/Settings migrations, application `000001`-`000022`, including applied `000021`.
- `erp_test`: PHPUnit support migration, CodeIgniter Shield/Settings migrations, application `000001`-`000022`, including applied `000021`.
- `erp_test` contained 30 physical tables before reset, including legacy `dinktech_roles` and `dinktech_role_permissions`.

The application row recorded for the missing version was:

```text
2026-09-29-000021
App\\Database\\Migrations\\MigrateLegacyRolesToGroups
```

## Final fresh test history

After the harness reset and fresh migration, `erp_test.dinktech_migrations` contains 25 rows:

- four package migrations (Shield/Settings);
- application `000001`-`000020` and `000022`;
- no row for `000021` because no source migration file exists.

The final fresh build contains 29 physical tables. A subsequent PHPUnit run is allowed to refresh its own support schema; the final database was rebuilt fresh again after that run.

## Development safety sentinel

The development database was never reset. Before and after the harness runs, `erp.dinktech_migrations` remained at 26 rows with minimum `2020-12-28-223112` and maximum `2026-09-29-000022`.

## Interpretation

The isolated workflow is operational for the source files currently present, but the repository and historical database state are not a complete reproducible migration lineage. The missing applied migration is a blocker under the forward-only and no-fabricated-migration rules.
