# P1-PRD-001 Product Group Hierarchy Assessment

Status: DONE

## Scope

Implemented the canonical Product Group adjacency-list repository/service for
the shared GROUP master. No migration, Product master, UOM, Pricing, COMBO,
Import, API, OpenAPI, or frontend implementation was added.

## Contract evidence

- Repository exposes positive-ID lookup, code lookup, parent lookup, deterministic
  child listing, row locking, create, mutable update, parent update, and status update.
- Service owns required-field/status validation, global code uniqueness handling,
  parent existence, arbitrary-depth ancestor traversal, self-parent/cycle checks,
  and corrupted-cycle fail-closed behavior.
- Reparenting changes only `parent_id`; descendants are not rewritten.
- Effective selectability requires ACTIVE target and ACTIVE every ancestor.
- INACTIVE state does not cascade and historical Product Group/Product references
  remain readable and unchanged.
- All protected mutations authorize `product_group` at canonical GROUP context;
  no SYSTEM_ADMIN bypass or client context override exists.
- Trusted `OperationContext` stamps audit fields. `setstate` uses the existing
  AuthorizationAuditService path and rolls back audit/business mutation together.
- Parent create and reparent use same-session `product-group-tree` DomainLock,
  transaction, authoritative re-read, cycle validation, and bounded failure behavior.

## Validation evidence

- `vendor/bin/phpunit --no-coverage tests/unit/P1Prd001ProductGroupServiceTest.php` |
  PASS | 9 tests, 43 assertions; includes real reciprocal reparent workers.
- `vendor/bin/phpunit --no-coverage tests/database/ChangePrd001ProductGroupHierarchyTest.php tests/database/P1Db060ProductUomComboTest.php` |
  PASS | 4 tests, 307 assertions.
- `vendor/bin/phpunit --no-coverage tests/unit/P1Auth002AuthorizationServiceTest.php tests/unit/P1Auth004AuthorizationAuditTest.php tests/unit/P1AuthorizationMandatoryRegressionTest.php` |
  PASS | 22 tests, 123 assertions.
- `vendor/bin/phpunit --no-coverage` | PASS | 238 tests, 2532 assertions.
- `vendor/bin/phpstan analyse --no-progress` | PASS | level 8, no errors.
- Focused changed-file PHPStan | PASS | application, test, and worker files.
- `architecture-guard.php`, `validate-context-cache.php`, `validate-ledger.php`,
  and `git diff --check` | PASS before commit.

## Schema and downstream boundary

No schema migration was added. `product_groups.parent_id` remains the existing
self-FK adjacency-list column; no helper table, depth/path column, organization
scope, Product relation, or Product parent/child semantic was changed.
