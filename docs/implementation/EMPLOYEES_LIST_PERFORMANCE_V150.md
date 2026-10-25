# ERP v1.5.0 — `/people/employees` table-first performance retrofit

**Base:** exact `erp_v1.5.0.zip` archive. **Independent of:** `M9_0_v150_baseline_reconciliation.patch` (not yet applied). **Scope:** GET-only list/read behavior and Employee form load hints; no migrations, no new route, no business writes.

## Diagnosis (from source)

Previously entry mounted three loaders in parallel: the visible Employee page, `loadLookups()` (all Organizations, Departments, Positions **and every Employee** via pagination), and `loadAllAssignments()` (every PBAC-visible Employee Assignment). Selecting the first row automatically caused another assignment-history query even when no detail dialog was open. The business-workflow metadata was also requested on mount. Every `loadLookupPages` could create numerous GETs, up to four concurrent across lookups. All assignments were searched linearly to fill the current-primary columns of one visible page.

## Changed flow

- Render `GET /api/v1/employees` current page as soon as it finishes; Organizations / Departments / Positions continue loading asynchronously without blocking the table.
- Request `GET /api/v1/employee-assignments` **only for IDs on this page**, with `filter[status]=ACTIVE`, `filter[is_primary]=1` and `filter[effective_at]=<UTC YYYY-MM-DD>`. Chunk employee IDs in batches of 50, preserving all PBAC scopes and pagination. If assignment VIEW is absent, do not attempt this GET.
- Do not load history on selection or on opening overview; load it only when the user activates **Assignments**. Stale page and detail results are ignored after navigation / selection changes. Retain full history, including inactive assignments, when explicitly requested.
- Fill Manager names from current visible Employee rows, or fetch only *missing* manager labels in one scoped `GET /employees?filter[id][]=...` per 50 IDs; the full manager directory loads **only on Create/Edit**, where the existing client-side cycle validation and User-link uniqueness picker need it. Disable Save and manager picker until the full authorized manager directory is ready; fail closed if it cannot be loaded.
- Defer `GET /business-workflow/employee/states` until Create is clicked. Metadata remains a hint; server is authoritative for allowed transitions and initial state.
- Generated SDK's default deepObject serializer does not support array-valued filter entries. For `listEmployees` and `listEmployeeAssignments`, use the existing `serializeListQuery`, producing PHP-compatible `filter[id][]` and `filter[employee_id][]`. No generated SDK files are hand edited.

## Backend compatibility

`PeopleMasterSql` now accepts `filter[id]` exclusively for the `employee` resource (1–50 canonical unsigned BIGINT string IDs), preserves original string precision, and uses indexed `p.id IN (?,...)` *after* the existing canonical PBAC predicate. Errors remain HTTP 400 via BadRequestException. Organization and assignment filters are unchanged. The original 150-route OpenAPI YAML byte fingerprint is unchanged: the contract already describes a generic `filter` parameter. No new public endpoint is created. Scope enforcement and business read snapshots are unchanged.

## Request count (not measured latency)

In the common small-data case with no manager outside the visible page, initial Employee-screen traffic now comprises 1 Employee list GET + 1 scoped current-primary assignment GET + 3 reference list GETs = **5 application GETs** (plus ordinary auth/shell requests). No auto-history, no full Employee directory, no full assignment directory and no Employee workflow GET. If some managers are outside the page, add 1 batched Employee labels GET. For a larger dataset the real gain is avoiding potentially dozens of paginated full-directory GETs. Additional pages for reference catalogs and page-scoped assignments still follow normal pagination; 200-row pages can require four batches.

## Validation and DEV UAT

Executed offline on the distributed source: `php backend/tests/m91_employee_list_id_filter_pure.php` (20/20), `php backend/tests/m86c_sql_pure.php` (89/89), `php backend/tests/m86b_org_sql_pure.php` (44/44), `php backend/tools/m86c_accept.php`, `php backend/tools/m87_accept.php`, and `python3 backend/tools/verify_routes.py` (150/150); TypeScript parse for changed frontend sources. **Not executed in this environment:** `vue-tsc`, Vitest, Playwright, real HTTP/MariaDB integration and real user-observed latency (pnpm dependencies and running XAMPP not available). New Vitest and Playwright regression cases are included for local execution.

On DEV, run:

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm install --frozen-lockfile
pnpm run type-check
pnpm exec vitest run src/features/people/employee-editor/__tests__/employeeListTraffic.spec.ts src/features/people/employee-editor/__tests__/employeeAssignments.spec.ts src/features/people/employee-editor/__tests__/employeeLookups.spec.ts src/services/__tests__/listLookups.spec.ts
pnpm exec playwright test e2e/fe020-employee.spec.ts
```

Also confirm in browser DevTools (Network → Fetch/XHR): initial page, pagination, search/filter, sorting, edit/create (full manager directory + User permission), detail Assignments history, update/toggle assignment, actor with limited `employee_assignment.view`, manager name outside current page, and rapid next-page navigation; compare rendered labels against actual authorized data. **The V2.3 refactor hash test and v1.2.4 immutable-frontend fingerprint are historical snapshots; this intentional retrofit changes the preserved functions.** Run the new performance cases and stage a properly approved refreshed fingerprint separately instead of rewriting historical snapshots as though unchanged. Historical M8.7 production-gate statuses are not promoted.

## Rollback

This patch changes only source code; save/restore the modified files or use `git apply -R`. No DDL, fixture mutation, or destructive command is required. Rebuild the frontend and deploy backend PHP together: the new page-only manager label query requires the new `filter[id]` support in backend to avoid an HTTP 400 for off-page managers.
