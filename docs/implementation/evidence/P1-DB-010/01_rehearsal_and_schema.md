# P1-DB-010 Evidence: Fresh Rehearsal and Schema Checkpoint

Date: 2026-10-05
Repository: `D:\\XAMPP\\htdocs\\erp`
Test database: `erp_test` only

## Fresh rehearsal

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` was run after the migration and focused tests were complete. The harness performed two consecutive cycles of:

1. reset the approved `erp_test` database;
2. run `spark migrate --all -g tests` with `database.defaultGroup=tests`.

Both cycles exited `0` and applied the four Shield/Settings package migrations followed by:

```text
2026-10-05-000001 App\\Database\\Migrations\\CreateFoundationLocationTables
```

The harness isolation guard confirmed database `erp_test`, host `127.0.0.1:3306`, and prefix `dinktech_`.

## Physical schema evidence

The resulting physical tables are `dinktech_provinces` and `dinktech_wards`; application migration code uses logical names `provinces` and `wards` so CI4 applies the configured prefix.

`dinktech_provinces`:

- `id bigint(20) unsigned NOT NULL AUTO_INCREMENT`, primary key;
- `code varchar(20) NOT NULL`, unique;
- `name varchar(255) NOT NULL`, index;
- `status varchar(20) NOT NULL`, index;
- nullable unsigned BIGINT `created_by` and `updated_by`, with no physical Shield FK;
- `created_at datetime NOT NULL`; `updated_at datetime NULL`.

`dinktech_wards`:

- `id bigint(20) unsigned NOT NULL AUTO_INCREMENT`, primary key;
- `province_id bigint(20) unsigned NOT NULL`, index, FK to `dinktech_provinces.id`;
- `code varchar(20) NOT NULL`, unique;
- `name varchar(255) NOT NULL`, index;
- nullable `name_full varchar(255)`;
- `status varchar(20) NOT NULL`, index;
- nullable unsigned BIGINT `created_by` and `updated_by`, with no physical Shield FK;
- `created_at datetime NOT NULL`; `updated_at datetime NULL`.

The FK has no explicit `ON DELETE` or `ON UPDATE` action, so MariaDB retains its restrictive default. Both tables use InnoDB.

## Database assertions

`tests/database/P1Db010FoundationLocationTest.php` verifies table existence, column type/nullability, primary and required indexes, unique `code` indexes, exactly one ward-to-province FK, absence of actor FKs, duplicate province/ward code rejection, and rejection of a nonexistent province ID.

## Forward checkpoint

After the second fresh cycle, the deterministic `erp_test` checkpoint is:

```text
dinktech_migrations rows: 5
2020-12-28-223112 | CodeIgniter\\Shield\\Database\\Migrations\\CreateAuthTables       | tests | batch 1
2021-07-04-041948 | CodeIgniter\\Settings\\Database\\Migrations\\CreateSettingsTable    | tests | batch 1
2021-11-14-143905 | CodeIgniter\\Settings\\Database\\Migrations\\AddContextColumn       | tests | batch 1
2026-07-20-181246 | CodeIgniter\\Settings\\Database\\Migrations\\ConvertSqlsrvValueColumn | tests | batch 1
2026-10-05-000001 | App\\Database\\Migrations\\CreateFoundationLocationTables              | tests | batch 1
```

This is the preserved starting point for a future forward application of `P1-DB-020`; no P1-DB-020 migration was created or run here.
