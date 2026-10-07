# P1-API-020 Employee Structure API Assessment

## Scope and authority

Implemented only the CONTRACT_READY Slice 2 HTTP surface for Department,
Position, Employee, and Employee Assignment after commits `3b3e169` and
`49a9a3b`. The committed authority documents and the P1-API-020 handoff were
used; no schema migration or P1-API-030 work was added.

## Runtime boundary

- `EmployeeStructure` is a thin HTTP adapter for the 20 approved routes.
- `Api020EmployeeStructureService` owns operation context, AuthorizationService
  calls, visibility ordering, allowlisted query handling, and delegation.
- Department and Position now have repository/service boundaries with locked
  hierarchy validation and unique-code checks.
- Employee uniqueness, manager-cycle, and audit-field handling remain in the
  Employee service.
- Employee Assignment lists authorize persisted Organization context before
  data filtering, totals, and pagination. Existing detail/edit/state checks use
  the persisted target context; create uses request Organization context.

## Domain corrections

Employee Assignment create/reference changes and re-activation require active
references. Updating/ending an existing historical Assignment does not require
an already-referenced Organization, Department, Position, or Employee to remain
active; foreign keys remain unchanged. Primary overlap locking and manager-tree
locking remain in their existing domain services.

The optional Employee current Primary Assignment projection is deferred because
the current canonical API source does not define an approved object shape.

## Contract and validation evidence

- 20 routes registered: 5 Department, 5 Position, 5 Employee, 5 Assignment;
  no export, nested Assignment, delete, or gender-enum route/schema was added.
- `vendor/bin/phpunit --no-coverage` | PASS, 386 tests / 3,482 assertions.
- Focused API/auth/domain suites | PASS, 37 tests / 170 assertions.
- `vendor/bin/phpstan analyse --configuration=phpstan.neon --level=8 --no-progress --memory-limit=1G` | PASS.
- `pnpm run openapi:lint` | PASS.
- `pnpm run openapi:generate:check` | PASS, tool-owned generated artifacts.
- `pnpm run type-check`, `pnpm run lint`, `pnpm run format:check`, `pnpm run build` | PASS.
- `git diff --check` | PASS.
