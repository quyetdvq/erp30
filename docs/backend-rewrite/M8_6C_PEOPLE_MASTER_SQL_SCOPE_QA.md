> **M8.6D supersession notice (2026-10-25):** This document records historical opt-in behavior only. The current source has **no** `DINKERP_M32_READ_MODE`, `DINKERP_M86B_SQL_SCOPE_MODE`, or `DINKERP_M86C_SQL_SCOPE_MODE` runtime switch. Canonical authorization and reviewed Organization/People SQL are mandatory after M8.6D. See `M8_6D_CANONICAL_SQL_ENFORCEMENT_QA.md`. Do not follow the old flag-toggle steps below.

# M8.6C - People MASTER SQL Scope (Employee / Department / Position)

**Version:** ERP v1.4.0 · **Milestone:** M8.6C · **Date:** 2026-10-25
**Delivery status:** Source accepted; MariaDB/HTTP integration not supplied; production BLOCKED.

## 1. Purpose and constraints

Make the `GET` list/detail operations for `employee`, `department`, and `position` SQL-first without changing any of the 150 frozen API operations. Migrate the row-level `VIEW` check to MariaDB `WHERE` **before** `COUNT`, search, sort, `LIMIT`, and `OFFSET`. Preserve the canonical `UnifiedAccessService` PBAC decision, including effective assignments, Resource Domain, state, ALLOW, DENY, and ABSOLUTE_DENY.

These resources are `MASTER` domain. Do **not** add a synthetic company foreign key or group People permissions by company merely because an Employee can have many effective assignments. Employee assignment is a separate `ORGANIZATION` resource already addressed in M8.6B.

### What does not change

- OpenAPI frozen v1.2.4, 150 route handlers, response fields (`data`, `meta`), existing mutation services, database schema, frontend, or backend baseline.
- M3.2 `legacy` and `shadow` response behavior is untouched; opt-in SQL requires the **canonical `enforce`** path.
- M8.6B Organization and Employee Assignment SQL branch remains separately gated; the flags are independent.
- M6.3C Avatar concurrency waiver remains `WAIVED` and is **not** converted to a PASS.

## 2. Files and responsibilities

| File | Responsibility |
| --- | --- |
| `Access/UnifiedAccessService.php` | Reviewed three additional Lifecycle `MASTER` resource types for canonical State VIEW decisions. |
| `Access/Query/{AuthorizationSqlScopeCompiler,AuthorizedSqlScope}.php` | Extend exact-domain whitelist. Unknown/incorrect scope fails closed. |
| `People/Read/PeopleMasterScopeProvider.php` | Maps the reviewed People resource to the canonical MASTER PBAC State set. |
| `People/Read/PeopleMasterSql.php` | Validated query, authorized `COUNT`, bounded `ORDER BY/LIMIT/OFFSET`, ID detail, effective-assignment filtering with separate EXISTS. |
| `People/Read/PeopleMasterReadService.php` | Repeatable-read transaction across policy/COUNT/page; recheck `canView()` on returned rows; no post-pagination security filtering. |
| `Controllers/Api/ReadCatalogController.php` | Opt-in reader dispatch; legacy/shadow and M8.6B dispatch preserved. |
| `tools/m86c/M86CSourceAcceptance.php` | Static source/contract safeguards only. |

## 3. Feature gate - disabled by default

```env
DINKERP_M32_READ_MODE=legacy
DINKERP_M86C_SQL_SCOPE_MODE=disabled
```

To test new People SQL only on a **separate disposable DEV instance**, set both:

```env
DINKERP_M32_READ_MODE=enforce
DINKERP_M86C_SQL_SCOPE_MODE=enabled
```

The switch accepts `disabled` or `enabled` only. Invalid values, or enabling with mode other than `enforce`, are rejected; there is no permissive fallback.

**Do not activate on production** until runtime authorization parity and query plans have been independently reviewed.

## 4. Data flow and PBAC invariants

1. Shield authentication and existing CI4 route authorization still apply.
2. `ReadCatalogController` recognizes only the fixed `employee`/`department`/`position` route entries.
3. Query parameters are checked with an exact field whitelist; no SQL identifiers come from client text.
4. A read-only repeatable-read transaction captures a single actor/policy/State snapshot.
5. Canonical PBAC is asked for the `MASTER` Lifecycle State set (GROUP-root ancestry, policies and DENY lanes).
6. SQL `WHERE` includes the compiled PBAC predicate **before** `COUNT`, `ORDER BY`, `LIMIT`, and `OFFSET`; if allowed States are empty, predicate becomes `1 = 0`.
7. Only returned rows are validated again with canonical `canView()`. Any mismatch throws (`AUTH_SCOPE_PBAC_MISMATCH`) instead of post-filtering and corrupting `total`.
8. No service writes business rows, borrows a caller's transaction, or changes the 150 API operations.

**SQL sketch (illustrative; statuses are bound canonical PBAC values):**

```sql
SELECT COUNT(*) AS total
FROM employees p
WHERE UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?, ?);

SELECT p.*
FROM employees p
WHERE UPPER(TRIM(p.status)) COLLATE utf8mb4_bin IN (?, ?)
ORDER BY p.id DESC
LIMIT ? OFFSET ?;
```

### Employee assignment filters - subtle legacy semantics

When Employee list includes `filter[organization_id]`, `filter[department_id]`, or `filter[position_id]`, baseline first finds *effective* ACTIVE assignments and separately checks whether **any assignment** matches each requested dimension. A single assignment does not need to satisfy all dimensions. Therefore the new SQL produces **a separate correlated `EXISTS` for each requested dimension**. They all use the same UTC operation date and an inclusive `[effective_from,effective_to]` interval.

Example: an Employee has assignment A (`organization_id=10`, `department_id=5`) and assignment B (`organization_id=20`, `department_id=7`). The filters `organization_id=10` and `department_id=7` match the old code (each can match a different effective assignment). A single JOIN restricted by both filters would silently reject this Employee; M8.6C avoids that error.

No query paginates raw Employee before applying effective-assignment filters.

## 5. Source acceptance (verified in development)

| Gate | Result |
| --- | --- |
| M3.4B pure/fixture offline | 143/143 PASS |
| Architecture M3.4D source gates | 6/6 PASS |
| M8.6C People MASTER scope | 43/43 PASS |
| M8.6C People SQL | 89/89 PASS |
| M8.6C Controller/transaction contracts | 63/63 PASS |
| API route inventory | 150/150, no new/unknown operation |
| PHP application lint | 336/336 PASS |

These are pure/source checks, **not** live MariaDB, HTTP or policy-parity evidence.

## 6. XAMPP source check (no writes)

```powershell
cd D:\XAMPP\htdocs\erp

git -c core.autocrlf=true apply --check .\ERP_M86C_PEOPLE_MASTER_SQL_SCOPE_v1.4.0.patch
git -c core.autocrlf=true apply .\ERP_M86C_PEOPLE_MASTER_SQL_SCOPE_v1.4.0.patch

python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m86c_scope_pure.php
php tests\m86c_sql_pure.php
php tests\m86c_controller_contract_pure.php
php tools\m86c_accept.php
php tools\m85_scope_audit.php
```

## 7. Required independent DEV runtime acceptance (NOT TESTED)

1. With `DINKERP_M86C_SQL_SCOPE_MODE=disabled`, compare baseline legacy/shadow responses for all three resources. Record exact actor, PBAC configuration and `request_id`.
2. On a disposable DEV setup in `enforce`, compare original per-record `canView()` decisions to SQL predicate for every canonical State, with users from at least two companies and permissions that differ by company, plus `DENY` and `ABSOLUTE_DENY`.
3. Compare `total`, `data` IDs, full DTO, page 1 and later pages, ID DESC sorting, `q` casefolding, Vietnamese/Unicode, `NULL` (`parent_id`/`level`), numerical-looking codes, negative and multi-field filters. **Do not assume SQL `LOWER` and PHP `mb_strtolower` collate identically.**
4. Test Employee with two simultaneous effective assignments whose `organization_id`, `department_id`, and `position_id` filters are satisfied by *different* assignments; check UTC midnight, open-ended `effective_to`, expired assignments and employee without assignments.
5. `GET /{resource}/{id}`: unauthorized ID must produce the same not-found behavior as the old route, with no existence leak.
6. Run `EXPLAIN` (and execution-time comparison) for `COUNT`, page, and correlated `EXISTS`; evaluate indexes. `UPPER(TRIM(status))` may limit normal `status` index usage. Do not add indexes without recorded evidence.
7. Verify the M8.6B Organization/Assignment modes have not changed; test the two feature flags independently.
8. Confirm business mutations and all imported data remain unchanged; read tests must not modify operational data.

**Release rule:** `SOURCE ACCEPTED`, `RUNTIME PARITY NOT VERIFIED`, `PRODUCTION BLOCKED`. No default rollout and no auto-enable until all required M9 evidence is collected.

## 8. Remaining ERP authorization backlog

- Contact lists: polymorphic owner (`organization_contact`, `employee_contact`) - a domain-aware provider is required.
- Access Management, Identity, Workflow: distinct SYSTEM/ORGANIZATION/native-State semantics; keep separate review.
- Pricing already uses its own SQL pushdown; integration with the shared compiler awaits independent multi-company parity.
- M8.6B Organization/Employee Assignment and M8.6C People MASTER are both **opt-in**, not runtime-accepted.
