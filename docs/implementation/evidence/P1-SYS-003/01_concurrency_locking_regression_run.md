# P1-SYS-003 - Concurrency/Locking Regression Run

Task: `P1-SYS-003`
Outcome: **DONE**
Risk/impact/profile: HIGH / cross-layer / backend, OpenAPI, frontend, critical UI
HEAD before closeout: `50aab9f405d47d57740901a087e722190a419b9b`
Live ledger before closeout: `100/107 DONE`; P1-SYS-003 was `NOT_STARTED`.

## Preflight and authority

`php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-SYS-003`
returned `AUTHORITY_CACHE: VALID`, `AUTHORITY_SOURCE_READ: HASH_CHECKED`,
`CATALOG_AND_LEDGER_VALIDATORS: PASS`, and live ledger `100/107 DONE`.
Gate 2 (`8/8`), Gate 4 (`7/7`), Gate 5 (`11/11`), and Gate 6 (`9/9`) were
`DONE` in the live ledger. The required authority read was:

- Implementation Contract v1.10 sections 9 and 18.4-18.6;
- FRS v1.12 concurrency invariants in `FRS-P1-SVC-001..011`,
  `FRS-P1-NUM-001..003`, `FRS-P1-CMB-001..003`, and `FRS-P1-IMP-001..005`;
- Implementation Plan v1.10 section 17 and the P1-SYS-003 dependency row.

The worktree was `main` at the pinned HEAD. These pre-existing unrelated Pricing
changes were preserved and excluded from this task:

- `backend/app/Modules/Pricing/PriceList/PriceListService.php`
- `backend/tests/Modules/Pricing/PriceList/P1Prc001PriceListServiceTest.php`

All destructive/stateful checks used the guarded `testing` connection. The
database inventory was `erp_test`, host `127.0.0.1:3306`, prefix `dinktech_`,
18 migration rows, isolation guard `PASS`. Stateful suites were run serially.

## Independent-process evidence matrix

| Area / invariant | Command or executable evidence | Concurrent actors | Outcome / fresh final-state proof |
|---|---|---|---|
| GROUP-root uniqueness | `tests/fixtures/organization_group_root_concurrency_worker.php` launched twice with shared ready/start/result barrier | Two independent PHP processes, both call `GroupRootSeeder` on separate MariaDB sessions | Both workers `ok=true`; SQL `SELECT COUNT(*) ... type='GROUP' AND parent_id IS NULL` = `1`; surviving row `GROUP_DINKTECH/GROUP/NULL` |
| Organization reparent cycle | `P1Org002OrganizationServiceTest`, `P1Prd001ProductGroupServiceTest::testRealMariaDbReciprocalReparentSerializesAndRejectsCycle` | Two reciprocal reparent workers | One commit, one typed hierarchy-cycle rejection; fresh rows contain one parent edge and no cycle |
| Employee manager cycle | `tests/fixtures/employee_manager_concurrency_worker.php` launched twice with shared barrier | A->B and B->A `EmployeeService::setManager` workers | One `ok=true`, one `EMP_MANAGER_CYCLE`; fresh SQL read has exactly one manager edge |
| Primary Assignment overlap | `P1Emp003EmployeeAssignmentServiceTest::testConcurrentOverlappingPrimaryAssignmentsCannotBothCommit` | Two independent Assignment create workers | One commit, one `EMP_PRIMARY_ASSIGNMENT_OVERLAP`; fresh connection sees exactly one active Primary row |
| Product Parent/Child and UOM structural race | `P1Prd003ProductMasterServiceTest` and `P1Uom001ProductUomServiceTest::testRealMariaDbChildAndConversionRaceLeavesOneValidOrdering` | Child-create vs UOM-conversion workers on one structure root | Exactly one structural mutation commits; no invalid SIMPLE/PARENT plus conversion combination remains |
| COMBO reciprocal/deep graph and owner conversion | `P1Cmb001ComboCompositionServiceTest::testIndependentReciprocalAndDeepCycleMutationsSerialize`, `testSimpleComponentAndFirstChildRaceLeavesOneValidStructure` | Reciprocal/deep COMBO workers and first-child worker | One valid mutation plus one cycle rejection; no persisted cycle; SIMPLE-to-PARENT conversion removes conflicting COMBO edge atomically |
| Product Group reciprocal reparent | `P1Prd001ProductGroupServiceTest::testRealMariaDbReciprocalReparentSerializesAndRejectsCycle` | Two independent reciprocal reparent workers | One success/one `PRODUCT_GROUP_HIERARCHY_CYCLE`; fresh graph contains one parent edge |
| Price List atomic batch | `P1Prc001PriceListServiceTest::testConcurrentIndependentReplacementBatchesCommitAsWholeSets` | Two independent `replaceItems` workers | Both calls succeed; fresh item set is exactly one complete batch, never a partial union |
| Authorization Group permission batch | `P1Auth005SecurityAdministrationTest::testGroupPermissionReplacementSerializesTwoIndependentConnections` | Two independent `replacePermissions` workers | Both calls succeed; final matrix equals one complete submitted set, never a partial matrix |
| Auto Number first/existing counter races | `P1Num004CounterAllocatorTest::testRealFirstCounterRaceUsesAdvisoryLockAndOneIdentity`, `testRealExistingCounterRaceUsesForUpdateAndDistinctSequences`; `P1Num001` active-default race | Two independent allocator/configuration workers per scenario | First counter has one row with sequences `[100,105]`; existing counter has distinct `[105,110]`; active-default race has one winner and one typed conflict |
| Auto Number collision/rollback/scope | `P1Num004CounterAllocatorTest` occupied-candidate, rollback, committed no-reuse, and scope/period tests; `P1AutoNumberingMandatoryRegressionTest` | Serialized transaction callbacks plus independent allocator workers | Occupied candidate advances; failed business transaction rolls counter back/reuses number; committed sequence never decrements; GROUP/COMPANY/ORGANIZATION partitions remain isolated |
| Import row atomicity and reconciliation | `P1Imp001..008`, `P1Imp090ProductImportRegressionTest`, `P1Db080ImportTest` | Product row mutation pipeline and persisted job/row reconciliation | Failed rows leave no partial Product/UOM/COMBO or number allocation; persisted row-derived counts match job summary; `FOR UPDATE` job reconciliation passes |

## Validation commands

| Check | Result |
|---|---|
| Focused concurrency/product/security/numbering/pricing profile (12 test files) | **PASS** - 88 tests, 582 assertions |
| Import + mandatory Auto Number profile (9 test files) | **PASS** - 57 tests, 414 assertions |
| `php vendor/bin/phpunit --no-coverage` | **PASS** - 440 tests, 3,907 assertions |
| `php vendor/bin/phpstan analyse --level=8 --no-progress` | **PASS** - no errors |
| `php .codex/skills/dinkerp-implementation/scripts/architecture-guard.php` | **PASS** |
| `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | **PASS** |
| `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | **PASS** - 100/107 before closeout |
| `pnpm run openapi:lint` | **PASS** |
| `pnpm run openapi:generate:check` | **PASS** - 17 generated files verified |
| `pnpm run type-check` | **PASS** |
| `pnpm run lint` | **PASS** |
| `pnpm run format:check` | **PASS** |
| `pnpm run test:unit -- --run` | **PASS** - 15 files, 40 tests |
| `pnpm run build-only` | **PASS** - Vite production build |
| `CI=1 pnpm run test:e2e` | **PASS** - 123 tests across Chromium/Firefox/WebKit |
| `git diff --check` | **PASS** (only existing line-ending warnings on Pricing files) |

## Contract closeout

The observed races were serialized by the approved DomainLock/`FOR UPDATE`
matrix, with bounded waits, same-session advisory lock release, deterministic
multi-root ordering, and no unbounded retry. No business/API/schema/PBAC/UI
semantics were changed. The two worker fixtures are task-owned test evidence;
no production source or migration changed.

Next dependency: `P1-SYS-004` (Fresh migration rehearsal).
