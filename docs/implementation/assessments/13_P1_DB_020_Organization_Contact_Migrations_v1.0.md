---
task_id: "P1-DB-020"
title: "Organization + Contact Migrations"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-DB-020 Organization + Contact Migrations

## 1. Task scope and authority

This task implements only the canonical `organizations` and `contacts` tables. It does not implement Organization/Contact services, repositories, controllers, API, frontend, seeders, GROUP bootstrap, or P1-DB-030.

Authority consulted:

- `docs/architecture/01_P1_Functional_Requirements_Specification_v1.7.md`, FRS-P1-CON-001/002 and FRS-P1-ORG-001/002/003;
- `docs/architecture/02_P1_Database_Design_v1.10.md`, Shared Contact, Organization, audit, FK/polymorphic, and migration-order sections;
- `docs/architecture/03_P1_Implementation_Contract_v1.5.md`, persistence/migration conventions;
- `docs/architecture/04_P1_Implementation_Plan_v1.3.md`, Gate 1 and P1-DB-020 dependency sections;
- `AGENTS.md`, `dinkerp-implementation` skill, and accepted P1-DB-001/P1-DB-010 assessments.

Dependency: P1-DB-010 is accepted at commit `35233ae`, so P1-DB-020 was READY. No authority conflict was found.

## 2. Starting Git/database state

- Initial HEAD: `35233ae feat(P1-DB-010): add foundation location migrations`.
- Initial worktree: clean.
- P1-DB-010 migration physically present at version `2026-10-05-000001`.
- No `000002` filename or `CreateOrganizationContactTables` class existed before this task.
- Test database: `erp_test`, host `127.0.0.1:3306`, prefix `dinktech_`; development database `erp` was not reset or migrated.

## 3. Migration created

Created exactly:

`backend/app/Database/Migrations/2026-10-05-000002_CreateOrganizationContactTables.php`

Class: `App\\Database\\Migrations\\CreateOrganizationContactTables`.

`up()` order is `organizations` then `contacts`; `down()` order is `contacts` then `organizations`. Logical Forge names are used so CI4 applies `dinktech_` at runtime.

## 4. Organizations schema

| Column | Definition | Constraint/index |
|---|---|---|
| `id` | unsigned `BIGINT`, auto-increment, NOT NULL | primary key |
| `parent_id` | nullable unsigned `BIGINT` | self FK; index |
| `type` | `VARCHAR(30)` NOT NULL | index |
| `code` | `VARCHAR(50)` NOT NULL | unique |
| `name` | `VARCHAR(255)` NOT NULL |  |
| `legal_name` | nullable `VARCHAR(255)` |  |
| `tax_code` | nullable `VARCHAR(50)` | index |
| `address` | nullable `TEXT` |  |
| `province_id` | nullable unsigned `BIGINT` | FK; index |
| `ward_id` | nullable unsigned `BIGINT` | FK; index |
| `description` | nullable `TEXT` |  |
| `status` | `VARCHAR(20)` NOT NULL | index |
| `active_from` | nullable `DATE` |  |
| `active_to` | nullable `DATE` |  |
| `created_by` / `updated_by` | nullable unsigned `BIGINT` | logical Shield refs; no FK |
| `created_at` | `DATETIME` NOT NULL |  |
| `updated_at` | nullable `DATETIME` |  |

Required indexes and physical FKs are present. No cascade action, CHECK, ENUM, trigger, hierarchy/path table, or denormalized `company_id` was added.

## 5. Contacts schema

| Column | Definition | Constraint/index |
|---|---|---|
| `id` | unsigned `BIGINT`, auto-increment, NOT NULL | primary key |
| `contact_type` | `VARCHAR(50)` NOT NULL | part of composite index |
| `contact_ref_id` | unsigned `BIGINT` NOT NULL | polymorphic; no FK |
| `description` | nullable `TEXT` |  |
| `status` | `VARCHAR(20)` NOT NULL | index |
| `name` | nullable `VARCHAR(255)` |  |
| `email` | nullable `VARCHAR(255)` | index |
| `phone` | nullable `VARCHAR(50)` | index |
| `mobile` | nullable `VARCHAR(50)` | index |
| `address` | nullable `TEXT` |  |
| `province_id` / `ward_id` | nullable unsigned `BIGINT` | location FK and indexes |
| `province_name` / `ward_name` | nullable `VARCHAR(255)` | historical/display snapshot |
| `created_by` / `updated_by` | nullable unsigned `BIGINT` | logical Shield refs; no FK |
| `created_at` | `DATETIME` NOT NULL |  |
| `updated_at` | nullable `DATETIME` |  |

The composite `(contact_type, contact_ref_id)` index and all required scalar indexes are present. `contact_ref_id` is intentionally not a physical FK to organizations, employees, or any other table.

## 6. FK, index, unique, and polymorphic results

- `organizations.code` UNIQUE: PASS.
- Organization parent self-FK: PASS.
- Organization province FK: PASS.
- Organization ward FK: PASS.
- Organization required indexes: PASS.
- Organization actor Shield FKs absent: PASS.
- Contact `contact_ref_id` physical FK absent: PASS.
- Contact province FK: PASS.
- Contact ward FK: PASS.
- Contact required indexes: PASS.
- Contact actor Shield FKs absent: PASS.
- Duplicate organization code rejected: PASS.
- Nonexistent organization province/ward/parent rejected: PASS.
- Nonexistent contact province/ward rejected: PASS.
- Orphan polymorphic owner reference physically insertable: PASS (DB boundary only; not functional owner validation).

## 7. Fresh rehearsal

`D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` performed two clean rebuilds. Both applied package migrations, P1-DB-010, and P1-DB-020 in dependency order with exit code `0`.

- Fresh rehearsal #1: PASS.
- Fresh rehearsal #2: PASS.

## 8. Forward rehearsal and data preservation

The disposable test DB was rebuilt to package + P1-DB-010 only, without P1-DB-020. One Province/Ward pair was inserted, P1-DB-020 was restored, and migration was applied without dropping or rebuilding the database. Post-state contained all four tables, ledger batch 2 for P1-DB-020, and unchanged `FWD-P1`/`FWD-W1` rows.

- P1-DB-010 -> P1-DB-020 forward migration: PASS.
- P1-DB-010 state preserved: PASS.

Detailed command/schema evidence: [01_fresh_forward_schema.md](../evidence/P1-DB-020/01_fresh_forward_schema.md).

## 9. Validation commands

Commands run from `D:\\XAMPP\\htdocs\\erp\\backend`:

| Command | Result |
|---|---|
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php rebuild --yes` | PASS; two fresh rehearsals |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php db-tests` | PASS; 12 tests, 275 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpunit --no-coverage` | PASS; 20 tests, 291 assertions |
| `D:\\XAMPP\\php\\php.exe vendor\\bin\\phpstan analyse --level=8 --memory-limit=1G --no-progress` | PASS; no errors |
| `D:\\XAMPP\\php\\php.exe tools\\test-db-harness.php inventory` | PASS; isolation guard `erp_test`, final ledger includes P1-DB-020 |
| `D:\\XAMPP\\php\\php.exe spark migrate:status` | PASS; read-only development status; no development migration executed |
| `git diff --check` | PASS |

An initial direct forward command without the harness environment failed safely before migration because it pointed at the development connection and encountered existing `dinktech_users`. It is recorded in evidence; the corrected explicit test-group forward command passed. No development DB reset or successful mutation occurred.

## 10. Files changed

- `backend/app/Database/Migrations/2026-10-05-000002_CreateOrganizationContactTables.php`
- `backend/tests/database/P1Db020OrganizationContactTest.php`
- `docs/implementation/assessments/13_P1_DB_020_Organization_Contact_Migrations_v1.0.md`
- `docs/implementation/evidence/P1-DB-020/01_fresh_forward_schema.md`

No architecture baseline document, P1-DB-010 migration, seeder, application service, API, frontend file, or unrelated file was modified.

## 11. Git completion

All four changed files belong exclusively to P1-DB-020. The task completion checkpoint is created only after cached diff checks confirm this exact file set, using:

```text
feat(P1-DB-020): add organization and contact migrations
```

The final commit hash and post-commit worktree state are reported with the task completion result.

## 12. Remaining blockers and final verdict

No implementation blocker remains. The service-layer organization/contact rules intentionally remain for later tasks. P1-DB-030 is the next migration dependency and is not started here.

Task: P1-DB-020 - Organization + Contact Migrations
Status: DONE

Contract notes:

- DB: two forward-only InnoDB tables with exact DBD fields, indexes, and FKs.
- Authorization: no authorization behavior or bypass added; Shield actor refs remain logical.
- API/OpenAPI: no impact.
- UI/i18n: no impact.
