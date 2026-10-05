---
task_id: "P1-DB-010"
title: "Foundation Location Migrations"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-DB-010 Foundation Location Migrations

## 1. Scope and authority

This task implements only the Foundation Location schema batch: `provinces` and `wards`. It does not add seed data, models, repositories, services, controllers, API/UI behavior, status rules, or import functionality.

Authority consulted:

- `docs/architecture/01_P1_Functional_Requirements_Specification_v1.7.md`, FRS-P1-LOC-001 and FRS-P1-LOC-002;
- `docs/architecture/02_P1_Database_Design_v1.10.md`, sections 4.1, 6.1, 6.2, 18.1, and migration order;
- `docs/architecture/03_P1_Implementation_Contract_v1.5.md`, section 19;
- `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, Gate 1 and P1-DB-010.

Dependency status: P1-DB-001 is complete. P1-DB-010 is therefore READY and precedes P1-DB-020.

## 2. Initial repository and database state

- Initial Git worktree was clean at HEAD `2af84e3`.
- `backend/app/Database/Migrations/` contained only `.gitkeep`; no application schema migration was rewritten.
- The approved PHPUnit connection was `erp_test` on `127.0.0.1:3306` with prefix `dinktech_`.
- Initial harness inventory passed its isolation guard and reported one existing migration-history row (the test history table itself).
- Development database `erp` was not targeted by the destructive harness commands. The default `spark migrate:status` check was run read-only and left the development migration state unchanged.

## 3. Migration implemented

Created exactly one application migration:

`backend/app/Database/Migrations/2026-10-05-000001_CreateFoundationLocationTables.php`

Class: `App\\Database\\Migrations\\CreateFoundationLocationTables`.

`up()` creates `provinces` first and `wards` second. `down()` drops `wards` first and `provinces` second. Logical Forge table names are used; CI4 applies the configured `dinktech_` prefix.

## 4. Physical schemas

### `provinces`

| Column | Actual definition | Constraints/indexes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` auto-increment, NOT NULL | primary key |
| `code` | `VARCHAR(20)` NOT NULL | unique |
| `name` | `VARCHAR(255)` NOT NULL | index |
| `status` | `VARCHAR(20)` NOT NULL | index |
| `created_by` | nullable `BIGINT UNSIGNED` | logical Shield ref; no physical FK |
| `updated_by` | nullable `BIGINT UNSIGNED` | logical Shield ref; no physical FK |
| `created_at` | `DATETIME` NOT NULL |  |
| `updated_at` | `DATETIME` NULL |  |

### `wards`

| Column | Actual definition | Constraints/indexes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` auto-increment, NOT NULL | primary key |
| `province_id` | `BIGINT UNSIGNED` NOT NULL | index; FK to `provinces.id` |
| `code` | `VARCHAR(20)` NOT NULL | unique |
| `name` | `VARCHAR(255)` NOT NULL | index |
| `name_full` | `VARCHAR(255)` NULL |  |
| `status` | `VARCHAR(20)` NOT NULL | index |
| `created_by` | nullable `BIGINT UNSIGNED` | logical Shield ref; no physical FK |
| `updated_by` | nullable `BIGINT UNSIGNED` | logical Shield ref; no physical FK |
| `created_at` | `DATETIME` NOT NULL |  |
| `updated_at` | `DATETIME` NULL |  |

The only physical FK is `wards.province_id -> provinces.id`. No cascade action, ENUM, CHECK, translation/locale field, company field, soft-delete field, trigger, or application status rule was introduced.

## 5. Focused database tests

Created `backend/tests/database/P1Db010FoundationLocationTest.php`. The isolated tests assert:

- both physical tables exist;
- all required type/nullability rules, primary keys, unique code indexes, and ordinary indexes;
- exactly one ward-to-province FK;
- no physical FK from `created_by` or `updated_by`;
- duplicate province codes are rejected;
- duplicate ward codes are rejected;
- a ward referencing a nonexistent province is rejected.

Tests run against only the `tests` database group and do not exercise ACTIVE-only service behavior.

## 6. Fresh rehearsal and forward checkpoint

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` completed two consecutive clean rebuilds. Each rebuild reset only `erp_test`, applied package migrations, then the new application migration with exit code `0`.

Final test checkpoint after rehearsal:

- `dinktech_migrations`: five rows (four Shield/Settings package rows plus P1-DB-010), all group `tests`, batch `1`;
- `dinktech_provinces` and `dinktech_wards` exist with the schema above;
- checkpoint is ready for a future forward application of P1-DB-020 without a reset.

No P1-DB-020 migration or forward business migration was started.

Detailed rehearsal/schema evidence: [01_rehearsal_and_schema.md](../evidence/P1-DB-010/01_rehearsal_and_schema.md).

## 7. Commands and actual results

Commands were run from `D:\\XAMPP\\htdocs\\erp\\backend` unless noted.

| Command | Result |
|---|---|
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php inventory` | PASS; isolation guard confirmed `erp_test`, prefix `dinktech_` |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` | PASS; two fresh cycles, both exit code `0` |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php db-tests` | PASS; 6 tests, 94 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpunit --no-coverage` | PASS; 14 tests, 110 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` | PASS; no errors |
| `D:\\XAMPP\\php\\php.exe spark migrate:status` | PASS; read-only development status check; no development migration executed |
| `D:\\XAMPP\\php\\php.exe spark migrate:status -g tests` | PASS; migration definitions enumerated |
| `git diff --check` | PASS |
| `SHOW CREATE TABLE` on `erp_test.dinktech_provinces` and `erp_test.dinktech_wards` | PASS; definitions recorded in evidence |

## 8. Files changed

- `backend/app/Database/Migrations/2026-10-05-000001_CreateFoundationLocationTables.php`
- `backend/tests/database/P1Db010FoundationLocationTest.php`
- `docs/implementation/assessments/12_P1_DB_010_Foundation_Location_Migrations_v1.0.md`
- `docs/implementation/evidence/P1-DB-010/01_rehearsal_and_schema.md`

No architecture document, seeder, production database, or unrelated file was modified.

## 9. Git completion and blockers

All changed files above belong exclusively to P1-DB-010. The completion checkpoint is created only after cached diff checks confirm that exact file set; the required subject is:

```text
feat(P1-DB-010): add foundation location migrations
```

No blocker remains for this task. The next dependency is P1-DB-020; this run does not begin it.

## 10. Final verdict

Task: P1-DB-010 - Foundation Location Migrations
Status: DONE

Validation:

- command: `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` - PASS
- command: `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php db-tests` - PASS
- command: `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpunit --no-coverage` - PASS
- command: `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` - PASS
- command: `git diff --check` - PASS

Contract notes:

- DB: two forward-only InnoDB tables, exact DBD location keys/FK/indexes, prefix-safe.
- Authorization: actor columns are nullable logical Shield references; no authorization behavior added.
- API/OpenAPI: no impact.
- UI/i18n: no impact.
