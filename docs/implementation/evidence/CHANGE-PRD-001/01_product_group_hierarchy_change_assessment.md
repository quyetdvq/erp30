# CHANGE-PRD-001 Product Group Hierarchy Change Assessment

## Outcome

`CHANGE-PRD-001` records the approved Product Owner requirement change before
Gate 5. It updates the P1 authority chain without implementing
`P1-PRD-001` ProductGroup repository/service. The P1 registry remains exactly
100 task IDs and the execution ledger remains 49/100 with Gate 4 at 7/7 and
Gate 5 at 0/11.

## Approved requirement delta

- Added `UR-PRD-007` in User Requirements v1.0.13.
- Product Group is a shared GROUP-level adjacency-list hierarchy with nullable
  `parent_id` and arbitrary depth.
- Global code uniqueness, cycle prevention, valid reparenting, effective
  ACTIVE ancestor selection for new Products, no status cascade, preserved
  historical Product references, and no Company scope are baseline semantics.
- Product Parent/Child semantics remain depth 1; this change does not alter
  Product role, UOM, COMBO, Pricing, Import, or Company usage semantics.

## Authority revisions

| Artifact | Active revision |
|---|---:|
| User Requirements | 1.0.13 |
| Functional Requirements Specification | 1.8 |
| Database Design | 1.11 |
| Implementation Contract | 1.6 |
| Implementation Plan | 1.4 |
| API Design | 1.2 |
| UI Design | 1.3 |

Prior revisions remain in `docs/architecture/` as historical baselines.

## Schema and migration

- Migration: `2026-10-05-000009_AddProductGroupHierarchy.php`.
- Forward-only additive change: nullable unsigned BIGINT `parent_id`, index
  `parent_id`, restrictive self FK to `product_groups.id`.
- Existing rows retain `parent_id = NULL`; no artificial hierarchy is
  backfilled and no table is dropped/recreated.
- `products.product_group_id` remains the existing FK to `product_groups.id`.
- No level/depth/path/closure/nested-set/materialized-path/helper table,
  organization/company column, or Product Company Usage Scope was added.

## Decision and compiled control plane

- Added approved `DEC-PRD-001` to `P1_DECISIONS.md` and
  `compiled/approved-decisions.json`.
- Updated `compiled/authority-manifest.json` to the seven active revisions.
- Updated compiled `P1-PRD-001` scope/dependencies and added the hierarchy
  rule to `compiled/rules.json`; `tasks.json` still contains 100 tasks.
- No repository generator exists for these catalogs; derived JSON was updated
  deterministically and validated with the repository validators.

## Validation evidence

The final report records the exact commands and PASS/FAIL/NOT RUN outcomes for
cache, task registry, decision registry, migration/schema tests, PHPUnit,
PHPStan, architecture guard, ledger, and `git diff --check`.

Executed checks:

- `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | PASS (`authority_count=7`, `task_count=100`, `rule_count=29`, `decision_count=4`).
- `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-PRD-001` | PASS; context resolves hierarchy scope. `CHANGE-PRD-001` itself remains outside the 100-task registry by design.
- `vendor/bin/phpunit --no-coverage tests/database/ChangePrd001ProductGroupHierarchyTest.php tests/database/P1Db060ProductUomComboTest.php` | PASS (4 tests, 307 assertions).
- `vendor/bin/phpunit --no-coverage` | PASS (229 tests, 2482 assertions).
- `vendor/bin/phpstan analyse --level=8 app --no-progress` | PASS.
- `vendor/bin/phpstan analyse --level=8 app tests --no-progress` | NOT VERIFIED; existing test bootstrap cannot open its configured SQLite database path. No application error was reported.
- `php .codex/skills/dinkerp-implementation/scripts/architecture-guard.php` | PASS.
- `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | PASS (`49/100`, Gate 4 `7/7`, Gate 5 `0/11`).
- Product Group authority-section consistency check | PASS.
- `git diff --check` | PASS.

No development/production database was dropped, reset, or backfilled. No
ProductGroup repository/service, API controller, frontend, or P1-PRD-001
implementation was added.
