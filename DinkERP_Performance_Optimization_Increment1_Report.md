# DinkERP Performance Optimization — Increment 1

**Date:** 2026-10-11  
**Source:** `backend_v1.0.5.zip` + selected API030/API060 hunks from the user-approved `DinkERP_OptIn_Lifecycle_Native_State_Stage1_After_WF006_old.patch`; backend's full current uncommitted Windows worktree is **not** available.  
**Output:** `DinkERP_Performance_Optimization_Increment1_Policy_Import_SQL.patch`  
**Nature:** additive performance patch; **no schema migration, no Auth/PBAC algorithm change, no OpenAPI field change**.

## What was implemented

### 1. P0 API030 Policy Action N+1 removal

Affected:
- `backend/app/Repositories/PolicyRepository.php`
- `backend/app/Services/Api030AuthorizationWorkflowService.php`
- existing repository PHPUnit regression test.

Before: `list('policy')` called `findPolicyActions(policyId)` on **every Policy that passed visibility**, including those outside requested pagination and filters. With 1,000 visible Policies and page_size=10, this could mean 1,000 action SELECTs before the 10 records were returned (in addition to the expensive per-row authorization queries).

After: The service still performs authorization filtering on **every Policy** before `total`/pagination. It hydrates all actions and effects only after the authorized page is known, using one bounded `WHERE policy_id IN (...)` query for <=500 IDs (the API page is capped at 200). Single-Policy detail continues using the old method.

**Expected query-count change for Action hydration only:** O(number of visible Policy rows) -> O(ceil(page_size/500)). This does **not** optimize the remaining per-record PBAC evaluation and is not a measured millisecond improvement.

### 2. P1 API060 Import Job Row SQL pagination

Affected:
- `backend/app/Infrastructure/Import/Persistence/ImportJobRowRepository.php`
- `backend/app/Services/Api060ProductImportService.php`
- two new targeted PHPUnit tests.

For terminal jobs (`COMPLETED`, `COMPLETED_WITH_ERRORS`, `FAILED`) whose query uses `row_number` ASC/DESC and optional canonical uppercase `row_status` filters, use database COUNT + indexed LIMIT/OFFSET instead of loading the entire `import_job_rows` set into PHP.

**Compatibility fallback:** `PENDING` / `PROCESSING`, searches including Unicode `q`, other sorts and lowercase filter tokens stay on the original in-memory path. `row_number` is unique per job in the existing schema. The DB query includes an extra `BINARY row_status` comparison because PHP's original filter is case-sensitive while MariaDB's collation may not be. No index/migration changes.

## Static and isolated tests completed

- `git apply --check`: PASS on an isolated snapshot with Stage 1 API030/API060 deltas.
- `git apply`: PASS on that isolated snapshot.
- `git apply --reverse --check`: PASS on the same snapshot after application.
- `git diff --check`: PASS in the isolated test Git workspace (`core.whitespace=cr-at-eol` for existing CRLF files).
- `php -l`: PASS on all seven changed/new PHP files (local PHP 8.4; target production is PHP 8.3).
- Offline native-PHP fast-path guard smoke test: 6/6 cases PASS (terminal, PROCESSING, descending, lowercase filter, nonnumeric sort, Unicode search).
- HAR parser synthetic-data smoke test: PASS; request query parameters and Authorization header not printed.

**Not executed here:** PHPUnit with MariaDB, PHPStan level 8, real backend API load tests, frontend type-check/build on the user's actual repository. The previously reported **513/513 PHPUnit PASS** belongs to the *pre-optimization* user working tree, not to this patch.

## Exact installation sequence (PowerShell)

The Windows worktree has uncommitted changes; preserve those changes. If `git apply --check` fails, **stop** and reconcile with actual current source rather than using force/3way/reset.

```powershell
cd D:\XAMPP\htdocs\erp

git status --short
git apply --check .\DinkERP_Performance_Optimization_Increment1_Policy_Import_SQL.patch
# Only after the check succeeds:
git apply .\DinkERP_Performance_Optimization_Increment1_Policy_Import_SQL.patch
git diff --check -- backend/app backend/tests

cd backend
php vendor/bin/phpunit --no-coverage tests/unit/P1Auth001PolicyRepositoryTest.php
php vendor/bin/phpunit --no-coverage tests/unit/P1Perf002ImportRowPagingTest.php
php vendor/bin/phpunit --no-coverage tests/unit/P1Perf003ImportRowSqlGuardTest.php
php vendor/bin/phpunit --no-coverage
# Then run your existing PHPStan level-8 quality command.
```

Test using **erp_test**, never the production DB. No database migration required.

## Required integration checks before merge

- `GET /api/v1/policies`: `page_size=10/50`, different Policy filters and active/inactive records, actions/effects identical to baseline, unchanged `total` for users with different scopes and Native State.
- Verify State-specific `ABSOLUTE_DENY` / assignment inheritance / all-states policy behavior; do not use an authorization cache that ignores persisted State.
- `GET /api/v1/import-jobs/{id}/rows`: terminal job page 1/last page, status filters, `-row_number`, Unicode search, nonexistent rows, `PROCESSING` job, and malformed query rejection.
- Collect **before** and **after** HAR (same account, page size, dataset, warm/cold method) and MariaDB `EXPLAIN`/digest. Report actual HTTP TTFB/p95, SQL count, DB time and response bytes. No fabricated speedup figures.

## Following increments — intentionally not implemented here

1. **PBAC per-row request-local precomputation / SQL predicate pushdown:** requires actor+Organization+Native/Lifecycle State differential tests and repository-consistent source. `ABSOLUTE_DENY` and denied-set intersection must never be bypassed.
2. **Organization, Employee and User authorized SQL pagination:** safe visibility predicates before `total` and SQL LIMIT; avoid accidental cross-Company exposure.
3. **Product UI full-catalog lookups / eager relation loading:** needs verified current component usage and an authorized on-demand selector/bulk API to avoid breaking Combo editing or record labels.
4. **Product structural scan optimization:** requires current `SqlProductListSource` and actual `EXPLAIN`; cannot eliminate fail-closed integrity checks by assumption.
5. **Index changes:** only after measured benefit, via forward-only migration; never change applied migrations.

The implementation is a **first independently reviewable performance increment**, not a claim of complete DinkERP performance remediation.
