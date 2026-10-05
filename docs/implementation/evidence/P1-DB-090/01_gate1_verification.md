# P1-DB-090 Evidence: Gate 1 Fresh/Forward Verification

Date: 2026-10-05
Repository: `D:\XAMPP\htdocs\erp`
Database under test: `erp_test` only (`127.0.0.1:3306`, prefix `dinktech_`)

## Authority and dependency guard

- `task-context.php P1-DB-090`: `AUTHORITY_CACHE: VALID`.
- Dependencies present on HEAD: P1-DB-010 through P1-DB-080 and P1-SEED-001 through P1-SEED-004.
- Development database `erp` was not reset, migrated, or written.
- No architecture baseline file was changed.

## Migration rehearsals

- `php tools/test-db-harness.php rebuild --yes`: PASS. Two consecutive fresh
  cycles applied the four Shield/Settings migrations and application migrations
  `2026-10-05-000001` through `2026-10-05-000008`, each with exit code `0`.
- Forward rehearsal: migration `2026-10-05-000008_CreateImportTables.php` was
  held outside discovery; fresh migration applied `000001` through `000007`
  with exit code `0`; the file was restored; `php spark migrate --all -g tests`
  applied `000008` in batch 2 with exit code `0`.
- `php spark migrate:status -g tests`: PASS. The status output lists all eight
  application migrations and the installed Shield/Settings package migrations;
  `000008` is in batch 2 after the forward rehearsal. The harness inventory
  reports 13 rows in the isolated test ledger after the PHPUnit support schema.
  The later PHPUnit database setup rebuilt the approved test ledger as a
  complete fresh state (all current migrations batch 1); no development DB was
  affected.

## Schema and bootstrap gates

- `vendor/bin/phpunit tests/database --no-coverage`: PASS, 43 tests and 1,664
  assertions.
- Targeted seed suites for P1-SEED-001 through P1-SEED-004: PASS, 14 tests and
  77 assertions. Coverage includes repeated seed runs, exact idempotency,
  privileged bootstrap negative paths, and canonical GROUP root conflicts.
- `vendor/bin/phpstan analyse --configuration phpstan.neon --no-progress`: PASS.
- `php tools/test-db-harness.php inventory`: PASS; isolation guard accepted
  only `erp_test`.
- `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php`:
  PASS; 7 authorities, 100 tasks, 22 rules, acyclic dependency graph.
- `git diff --check`: PASS.

Gate 1 fresh migration, forward migration, schema, seed idempotency, privileged
bootstrap, and GROUP root verification are complete for the current P1 source
inventory. P1-DB-090 does not start Gate 2 or downstream tasks.
