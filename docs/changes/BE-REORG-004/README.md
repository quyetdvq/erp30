# BE-REORG-004 — Employee Structure

Physical relocation only for Department, Position, Employee and Employee Assignment. Canonical implementation lives under `App\Modules\EmployeeStructure`; the eight existing `App\Services` / `App\Repositories` entrypoints remain explicit compatibility delegates.

## Scope and non-goals

No schema, migration, route, controller, OpenAPI, frontend, Contact, Authorization/IAM or workflow behavior change. Existing keys remain `department-tree`, `employee-manager-tree`, and `employee-assignment:<employeeId>`; Position remains without a domain lock. The Assignment canonical service depends on Employee's module port plus Organization's existing module port.

## Apply order

Apply after the accepted `BE-REORG-003` + `BE-REORG-003_FIX01` checkpoint. Do not run Composer update and do not run application migrations for this reorg.

## Verification

From `backend`, with no other test process using the same database:

`php tools/test-employee-structure.php --confirm-test-db`

The runner requires the disposable loopback `127.0.0.1:3306/erp_test` target with `dinktech_` prefix, runs the full PHPUnit suite once, gates Foundation + Organization + Contact + Employee Structure from one fresh JUnit file, then runs PHPStan. Browser/Apache UAT remains separate.

Absent unrelated new tests, expected total is 807 tests. BE-REORG-004 contributes exactly 64 cases: Boundary 6, Compatibility 18, Department+Position 10, Employee 10, Assignment 12, integration 5, real DB lock 3. PHPStan acceptance is exact diagnostic-set equality against the inherited 121-diagnostic checkpoint; count-only comparison is insufficient.

BE-REORG-004 fixtures do not create Contact authorization grants. Production rollback guards remain unchanged; any future fixture that creates Contact authorization rows must clean them before `DatabaseTestTrait` triggers migration rollback.
