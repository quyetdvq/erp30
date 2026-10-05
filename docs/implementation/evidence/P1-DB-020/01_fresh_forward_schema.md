# P1-DB-020 Evidence: Fresh, Forward, and Schema Checks

Date: 2026-10-05
Repository: `D:\\XAMPP\\htdocs\\erp`
Database under test: `erp_test` only

## Fresh rehearsal #1 and #2

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` completed two consecutive clean cycles. Each cycle reset only the approved test database and applied, in order:

1. Shield/Settings package migrations;
2. `2026-10-05-000001_App\\Database\\Migrations\\CreateFoundationLocationTables`;
3. `2026-10-05-000002_App\\Database\\Migrations\\CreateOrganizationContactTables`.

Both cycles exited `0`. The migration ledger after each clean cycle contained six rows: four package rows and P1-DB-010/P1-DB-020, with P1-DB-010 in batch 1 and P1-DB-020 in batch 2 for the full fresh run.

## Forward rehearsal 010 -> 020

To create a real pre-forward state without changing migration history, the P1-DB-020 file was temporarily moved outside the migration discovery directory. The exact sequence was:

1. run `tools\\test-db-harness.php fresh --yes` while P1-DB-020 was withheld;
2. verify `erp_test` contained package migrations plus P1-DB-010 only;
3. insert one minimal Province (`FWD-P1`) and Ward (`FWD-W1`, province ID `1`);
4. restore P1-DB-020 to its migration directory;
5. set process environment `database.defaultGroup=tests` and run `spark migrate --all -g tests` without resetting the database;
6. verify all four tables and the preserved rows.

Pre-forward ledger:

```text
2020-12-28-223112 | CodeIgniter\\Shield\\Database\\Migrations\\CreateAuthTables | tests | batch 1
2021-07-04-041948 | CodeIgniter\\Settings\\Database\\Migrations\\CreateSettingsTable | tests | batch 1
2021-11-14-143905 | CodeIgniter\\Settings\\Database\\Migrations\\AddContextColumn | tests | batch 1
2026-07-20-181246 | CodeIgniter\\Settings\\Database\\Migrations\\ConvertSqlsrvValueColumn | tests | batch 1
2026-10-05-000001 | App\\Database\\Migrations\\CreateFoundationLocationTables | tests | batch 1
```

Forward command result:

```text
Thực hiện: (App) 2026-10-05-000002_App\\Database\\Migrations\\CreateOrganizationContactTables
Di trú đã hoàn thành.
```

Post-forward checks:

```text
dinktech_provinces: exists
dinktech_wards: exists
dinktech_organizations: exists
dinktech_contacts: exists
province FWD-P1: Forward Province
ward FWD-W1: Forward Ward, province_id 1
2026-10-05-000002 | App\\Database\\Migrations\\CreateOrganizationContactTables | tests | batch 2
```

The P1-DB-010 tables and their data remained intact. Forward migration: PASS. Data preservation: PASS.

## Physical schema

`dinktech_organizations` has:

- unsigned BIGINT auto-increment `id` primary key;
- nullable unsigned BIGINT `parent_id` self-FK;
- required `type VARCHAR(30)`, `code VARCHAR(50)`, `name VARCHAR(255)`, and `status VARCHAR(20)`;
- nullable `legal_name VARCHAR(255)`, `tax_code VARCHAR(50)`, `address TEXT`, `province_id`, `ward_id`, `description TEXT`, `active_from DATE`, `active_to DATE`;
- nullable logical Shield actor refs `created_by` and `updated_by`;
- required `created_at DATETIME` and nullable `updated_at DATETIME`;
- unique `code` and indexes `parent_id`, `type`, `status`, `tax_code`, `province_id`, `ward_id`;
- FKs to itself, `provinces.id`, and `wards.id`, with no explicit cascade action.

`dinktech_contacts` has:

- unsigned BIGINT auto-increment `id` primary key;
- required `contact_type VARCHAR(50)` and unsigned BIGINT `contact_ref_id` with no owner FK;
- nullable description, name, email, phone, mobile, address, location IDs, and snapshot names;
- required `status VARCHAR(20)`;
- nullable logical Shield actor refs and the required/nullable timestamps;
- composite index `(contact_type, contact_ref_id)` plus indexes `email`, `phone`, `mobile`, `province_id`, `ward_id`, `status`;
- only location FKs to `provinces.id` and `wards.id`.

## Integrity and boundary assertions

The focused PHPUnit class verifies all required columns and nullability, primary/unique/ordinary indexes, organization FK targets, contact location FK targets, duplicate organization code rejection, unknown organization parent/province/ward rejection, unknown contact province/ward rejection, absence of actor FKs, and successful persistence of a contact whose polymorphic `contact_ref_id` has no Organization/Employee row.

Organization self-parent, cycles, parent matrix, status transition, Ward/Province matching, owner existence, and authorization remain application/service responsibilities and are intentionally not claimed as database constraints.

## Harness safety note

One initial manual invocation of `spark migrate --all -g tests` was stopped by CI4 at `dinktech_users already exists` because the separate process did not inherit the harness `database.defaultGroup=tests` setting. No reset or successful mutation targeted `erp`; a read-only ledger query confirmed the development database still contained only its four package migration rows. The corrected forward command explicitly set the process environment and ran only against `erp_test`.
