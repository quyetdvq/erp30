> **M8.6D supersession notice (2026-10-25):** This document records historical opt-in behavior only. The current source has **no** `DINKERP_M32_READ_MODE`, `DINKERP_M86B_SQL_SCOPE_MODE`, or `DINKERP_M86C_SQL_SCOPE_MODE` runtime switch. Canonical authorization and reviewed Organization/People SQL are mandatory after M8.6D. See `M8_6D_CANONICAL_SQL_ENFORCEMENT_QA.md`. Do not follow the old flag-toggle steps below.

# M8.6B — Organization / Employee Assignment Multi-company SQL Scope

**Status:** SOURCE ACCEPTED (offline only). **Rollout:** guarded / default disabled. **Production:** BLOCKED.

## 1. Purpose and scope

M8.6B continues `AUTHORIZATION_SQL_SCOPE_STANDARD_v1.0.md` and M8.5/M8.6A. It introduces a reviewed SQL-PBAC path for **two** ORGANIZATION-domain read resources:

- `organization`: `GET /organizations`, `GET /organizations/{id}`, `GET /organizations/tree`, and the existing Organization label resolver.
- `employee_assignment`: `GET /employee-assignments`, `GET /employee-assignments/{id}`. Includes `filter[effective_at]`, `filter[is_primary]` and Boolean projection.

The new path uses canonical PBAC decisions for **each distinct Organization + State** combination, not State-only. The effective SQL scope is applied **before** `COUNT`, text search, business filters, sorting and `LIMIT/OFFSET`. Public JSON paths and frozen OpenAPI remain unchanged (150/150). No schema, frontend, mutation or baseline changes.

**Not migrated:** `employee`, `department`, `position` (MASTER), provinces/wards, Contact (polymorphic), Access/Identity/Workflow. Price Lists retain their existing reviewed/discovery SQL path and opt-in provider.

## 2. Security model

- `OrganizationPeopleScopeProvider` enumerates only trusted **Organization metadata IDs**, bounded at **512 IDs** (`LIMIT 513` to detect overflow). It does not trust request IDs, filters, or rows being returned to establish privileges.
- `UnifiedAccessService::viewableOrganizationStateBuckets()` reuses `viewDecisionForState()`, including active Resource, base permission, effective assignment, organization ancestry, policy priority, DENY and ABSOLUTE_DENY. Organization uses lifecycle States; Employee Assignment uses native States.
- `AuthorizedSqlScope::predicate('p')` maps `organization` to **`p.id`** and `employee_assignment` to **`p.organization_id`**. Both bind Organization IDs and canonical States as SQL values. No dynamic HTTP-supplied identifiers.
- Empty allowed slots generate `WHERE 1 = 0`; an over-limit or malformed metadata set fails closed. Requests without base VIEW permission return an empty list / 404 detail / empty label set.
- Every returned row is checked again with canonical `canView()`. Mismatch fails the **whole response**, not post-filter (which would corrupt `total`).
- The scoped metadata and `COUNT`/page/detail are read under one repeatable-read transaction. The read service never borrows or commits a mutation transaction.

## 3. Controlled rollout

The existing `ReadCatalogController` remains in charge of its routes and M3.2 `ReadAccessBridge` behavior. The new implementation **does not activate automatically** after applying the patch.

| Config | Runtime behavior |
|---|---|
| `DINKERP_M86B_SQL_SCOPE_MODE=disabled` (default) | Existing `legacy`, `shadow`, `enforce` controller paths unchanged; in-memory reads continue |
| `DINKERP_M86B_SQL_SCOPE_MODE=enabled` and `DINKERP_M32_READ_MODE=enforce` | Organization/Assignment use SQL PBAC pushdown |
| Enabled with legacy/shadow or unknown flag | Fail closed; **no permissive fallback** |

Enable only in a **disposable DEV environment after actor/assignment/policy parity is established**, and never on production based on offline fixture PASS alone.

## 4. Read-path implementation

- `backend/app/Modules/Organization/Read/OrganizationPeopleScopeProvider.php`: trusted metadata enumeration and decision compilation.
- `backend/app/Modules/Organization/Read/OrganizationPeopleSql.php`: validated filter/sort/limit SQL for both resources and batch label lookup. `effective_at` uses inclusive UTC date boundaries and requires `ACTIVE`. For exact filter equality, use binary comparison to avoid collation trailing-space surprises.
- `backend/app/Modules/Organization/Read/OrganizationPeopleReadService.php`: repeatable-read snapshots, bound SQL, canonical defense-in-depth, response projection.
- `backend/app/Controllers/Api/ReadCatalogController.php`: guarded dispatch with **no changes to route handlers or default behavior**.

## 5. Known constraints / risks

1. The 512-Organization / 512 approved-slot caps are strict **fail-closed readiness guards**, not a scalable long-term solution. Large tenants will need a separately reviewed set-based/temporary-table compiler with identical PBAC behavior.
2. Organization ancestry is still resolved by the canonical OrganizationFacts adapter. This can cost multiple metadata queries proportional to the number of organizations. **Do not claim measured speedup** without SQL profiling; the main gain is avoiding unbounded business-row materialization.
3. SQL collation and lowercase text sort may differ from legacy PHP `mb_strtolower()` + `strcmp()`, particularly with Vietnamese Unicode, numeric-looking codes, and trailing spaces. HTTP parity is mandatory.
4. Source tests do not prove MariaDB bind/transaction behavior, legacy-vs-enforce parity or index use. Do not enable on production without a live acceptance report.
5. Tree projection still applies the existing `FlatHierarchy::visibleRows()` to the **authorized page**, as it did before. It is not a full-tree response redesign.
6. SQL mode is **opt-in**; source acceptance means the new path exists and is tested offline, not that all organization reads currently use SQL pushdown.

## 6. Offline verification on XAMPP

```powershell
cd D:\XAMPP\htdocs\erp
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m86b_org_scope_pure.php
php tests\m86b_org_sql_pure.php
php tests\m86b_controller_contract_pure.php
php tools\m86b_accept.php
php tools\m85_scope_audit.php
```

Expected after M8.6B: **140/140** offline suites; 25/25 scope, 44/44 SQL, 40/40 controller/transaction contract; 150/150 frozen routes; M3.4D 6/6; source only.

## 7. DEV MariaDB acceptance (NOT SUPPLIED)

Use isolated test actors spanning at least two COMPANY organizations with the **same State** and different permissions. Compare the previous M3.2 enforcement path with opt-in SQL mode for:

- `data` and `meta.total` for page 1/2, sort `id`, name and legal name, empty filters, UTF-8 Vietnamese queries and numeric-looking codes.
- `organization` lifecycle transitions; DENY/ABSOLUTE_DENY and inherited hierarchy; inaccessible child/parent; unknown/hidden detail both 404.
- `employee_assignment` native ACTIVE/INACTIVE, two organizations with different grants; inclusive `effective_at`, nullable end date, `is_primary=true/false`; exact page total and ID order.
- Organization tree page projection and batch Organization label resolution: only allowed labels, in requested order.
- No base grant, all states denied, malformed request, invalid/over-limit organization metadata, wrong toggle combination.
- MariaDB `EXPLAIN` / profiling for both filtered `COUNT` and page query; measure query count, memory, latency before/after. Pay attention to UPPER(TRIM(status)) and OR predicates; add indexes only after evidence.
- No read transaction left open, no mutation code path changed, concurrent policy changes tested for snapshot consistency.

**Status:** `SOURCE_ACCEPTED_INTEGRATION_NOT_SUPPLIED`, production `BLOCKED`. Legacy M6.3C Avatar concurrency remains `WAIVED / NOT_VERIFIED` per prior decision; fingerprint checks remain excluded from this QA scope.
