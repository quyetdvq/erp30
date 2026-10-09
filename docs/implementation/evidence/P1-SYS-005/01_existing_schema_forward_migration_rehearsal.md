# P1-SYS-005 - Existing-Schema Forward Migration Rehearsal

Date: 2026-10-09
Task: `P1-SYS-005`
Outcome: **BLOCKED**

## Preflight and authority

`php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-SYS-005`
returned `AUTHORITY_CACHE: VALID`, catalog/ledger validators PASS, and the
live ledger was `102/107 DONE` before this report. The verified HEAD was
`6fa87ab99e2e79e2b197a7a60a1cf0b1c794095f`. Gate 1 was `14/14 DONE`, the
declared dependency. The only pre-existing worktree changes were the
unrelated Pricing files and they were preserved:

- `backend/app/Modules/Pricing/PriceList/PriceListService.php`
- `backend/tests/Modules/Pricing/PriceList/P1Prc001PriceListServiceTest.php`

The bounded authority read covered DBD v1.15 section 23 (existing-schema
harmonization), Implementation Contract v1.10 section 19
(migration/bootstrap), Implementation Plan v1.10 section 17 (Gate 9), and
the approved overlays in `.codex/skills/dinkerp-implementation/compiled/change-controls.json`.
The database-impacting approved controls were Display Settings audit events,
Policy Scope Uniqueness, and Shield User reset-password action. No overlay
authorizes inventing a legacy fixture or rewriting an applied migration.

## Baseline decision and safety

The repository contains no credible populated legacy baseline for this task.
The retained `P1-ENV-004` recovery evidence records that the obsolete
application lineage was reset and that no SQL dump, schema snapshot, migration
fixture, or prior-state archive was recoverable. Current `erp` is a populated
canonical P1 database, not a legacy schema: it has 16 migration rows through
`2026-10-08-000012`, canonical `resources.domain`, 6 organizations, 1,452
products, 3 policies, and 3 import jobs. It is not an acceptable source to
pretend that DBD section 23 harmonization occurred.

The only destructive target remained the guarded test database:

```text
CI_ENVIRONMENT=testing
database.defaultGroup=tests
database=erp_test
host=127.0.0.1:3306
prefix=dinktech_
MariaDB=10.4.32
```

`tools/test-db-harness.php inventory` passed its isolation guard before and
after validation. A pre-validation backup was verified at:
`D:\XAMPP\temp\p1-sys-005\erp_test-before-validation.sql`.
The first dump attempt used `--routines` and exposed the local MariaDB
`mysql.proc` defect (exit 2); the corrected `--skip-routines --skip-events`
dump completed with exit 0, 72,192 bytes, SHA-256
`1DDB282A39AFA8AD9FB16F2585711DD6FCC8C4C1B5AB479E5E8BDE5026EC1AF8`, and 40
`CREATE TABLE` statements. The backup was restored after test execution into
`erp_test` only. The development database was never reset, migrated, or
seeded by the supported validation path.

Because no credible source baseline exists, the required existing-schema
forward migration was **not run**. The synthetic `forward-domain` harness
fixture was deliberately not used because it would not represent the
required populated legacy schema or exercise the full DBD section 23 list.
No application migration file was modified or rolled back.

## Reproducible validation

| Command / check | Result |
|---|---|
| `tools/test-db-harness.php inventory` | PASS; exact `erp_test` guard |
| Guarded migration status via testing bootstrap | PASS; 17 migrations applied, no pending |
| Repeat guarded `migrate --all` twice | PASS; exit codes `0,0`; history stayed at 17 |
| Guarded Resource/Action seed twice | PASS; 21 resources, 95 actions, no duplicate identities |
| `vendor/bin/phpunit tests/database --no-coverage` | PASS; 49 tests, 1,694 assertions |
| Focused `ChangeAuthDomain001MigrationTest` + `ChangePolicyScopeUniqueness001MigrationTest` | PASS; 3 tests, 12 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS; 440 tests, 3,907 assertions |
| `vendor/bin/phpstan analyse --level=8 --no-progress` | PASS; no errors |
| `architecture-guard.php` | PASS; 47 application files |
| `validate-context-cache.php` / `validate-ledger.php` | PASS; 107 tasks, 102 DONE before ledger update |
| Dynamic FK orphan scan on restored `erp_test` | PASS; `fk_orphans=0` |
| `git diff --check` | PASS |

The restored isolated schema snapshot contains 40 physical tables, 17
migration rows (`2020-12-28-223112` through `2026-10-08-000013`), 71 primary/
unique declarations, 50 foreign keys, and 218 index declarations. Seed
identity checks are stable at one `GROUP_DINKTECH` root, one `SYSTEM_ADMIN`
group, 93 `SYSTEM_ADMIN` group permissions, 21 Resources, and 95 Resource
Actions. `resources.domain` contains only `SYSTEM`, `MASTER`, and
`ORGANIZATION`; `policies.normalized_state_id` is stored/generated and
`uq_policies_scope` covers all five scope dimensions.

Pre/post comparison for the isolated validation target:

| Snapshot | Tables | Migration rows | Business rows / identities | Relationship integrity |
|---|---:|---:|---|---|
| Before validation (backup/inventory) | 40 | 17, max `000013` | 1 GROUP root, 1 `SYSTEM_ADMIN`, 93 permissions, 21 Resources, 95 Actions | Guard PASS; backup captured |
| During PHPUnit cleanup (temporary) | 41 | 18 including test support migration | Business fixtures rolled back to zero | Tests PASS; state not retained |
| After restore and repeat checks | 40 | 17, max `000013` | Same as before; Resource/Action seed twice remained 21/95 | 50 FKs; dynamic orphan scan `0`; 218 indexes |

The development sentinel remained separate before and after: `erp` retained 40
tables and 16 migration rows through `000012`, with 6 organizations, 1,452
products, 3 policies, and 3 import jobs. No development row-count or migration
history change was observed.

During diagnosis, a direct development-default `GroupRootSeeder` probe was
also observed. It found the existing canonical `GROUP_DINKTECH` root and
returned without inserting a row; the `erp` sentinel above was unchanged. No
other direct development-default mutation command was run.

The focused Policy migration tests explicitly passed both required paths:
lowest-ID survivor with union-by-action precedence
`ABSOLUTE_DENY > ALLOW > DENY`, and proof failure that blocks before cleanup
and preserves duplicate rows. These are regression proofs, not evidence of a
run against the missing representative legacy source.

Canonical Shield tables, Resource Domain values, Product/UOM, Pricing, Import,
and configuration-audit table/index/FK definitions are present in the current
schema and covered by the database suite. Legacy-to-canonical harmonization,
data preservation for populated Product/UOM/Pricing/Import rows, and
pre-existing audit/identity relationship preservation remain **NOT VERIFIED**
because the restored validation target contains only the current seeded
baseline and no legacy source rows.

## Standalone Spark defect

The direct testing-environment command was intentionally probed and failed
before dispatch:

```text
$env:CI_ENVIRONMENT='testing'; php spark migrate:status
exit 255
Undefined constant "CodeIgniter\\Config\\SUPPORTPATH"
```

The supported harness/bootstrap defines `SUPPORTPATH` and succeeds. The
standalone defect is recorded rather than hidden; fixing it is outside this
blocked rehearsal and must not be papered over as migration evidence.

## Final verdict and handoff

The migration/schema/seed/regression checks that can run without a legacy
source all pass, but P1-SYS-005 remains **BLOCKED** because the required
representative populated legacy baseline and its verified backup do not exist.
The smallest unblock is an authoritative legacy database dump/snapshot (with
schema, migration history, identities, and representative Policy/Product/UOM/
Pricing/Import rows) plus its checksum and restore instructions. Once supplied,
restore it into isolated `erp_test`, rerun the existing-schema forward path,
then retain the same duplicate-scope, FK/index, seed-idempotency, and repeat
migration assertions.

P1-SYS-006 handoff: OpenAPI/frontend drift can be audited independently, but
release/UAT must continue to treat this task as unresolved until the
authoritative legacy baseline is supplied and the forward rehearsal passes.
