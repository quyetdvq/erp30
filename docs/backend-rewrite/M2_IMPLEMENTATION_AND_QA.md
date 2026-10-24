# DinkERP v2 — M2 Read Models: Implementation & QA

Date: 2026-10-23  
Scope: `backend/` only. Neither Frontend nor `backend_basline/` has been changed.

## Scope/architecture

M2 implements internal read-only projections. **There are no new `/api/v1` HTTP routes, no CRUD, no DB migrations, and no access decisions.** Existing M1 APIs remain the only public operations. New code does not copy the former module/service hierarchy.

| File | Ownership / responsibility |
|---|---|
| `app/Modules/Locations/LocationReadRepository.php` | Province/ward lookup, wards of one province, province relationship |
| `app/Modules/Organization/OrganizationReadRepository.php` | Organization lookup, parent->root chain, GROUP roots, full internal tree snapshot |
| `app/Modules/People/PeopleReadRepository.php` | Employee by ID / Shield user ID, department, position, department tree, effective assignments, bounded batch reads |
| `app/Modules/Access/ScopeFactsReader.php` | Privileged read bridge to People and Organization facts used later by PBAC M3 |
| `app/Shared/Read/PositiveId.php` | Validate unsigned BIGINT IDs, no float conversion |
| `app/Shared/Read/UtcBusinessDate.php` | UTC business date and pure inclusive assignment window predicate |
| `app/Shared/Read/FlatHierarchy.php` | Complete tree cycle/orphan checks, ancestor/descendant navigation, parent masking of already authorized lists |
| `app/Commands/M2Verify.php` | Read-only `spark m2:verify` on the configured database; no personal data printed |

### Exact v1.2.4 behavioral invariants

1. `employees.user_id` is nullable, unique; `findByUserId` does **not** silently filter inactive employees. M3 must reject ineligible employees separately.
2. An effective assignment requires `status='ACTIVE'`, `effective_from <= UTC business date`, and `effective_to IS NULL OR effective_to >= UTC business date`. Both bounds are inclusive.
3. Effective assignment order: `is_primary DESC`, `effective_from DESC`; additional `id DESC` tie-breaker is deterministic only for otherwise equal rows. A missing effective assignment yields `[]`, never grants scope.
4. The effective-assignment query **does not additionally filter** organization, department or position by status, and does not treat `organizations.active_from`/`active_to` as employment-validity windows. These eligibility decisions remain for M3. Do not let such filters silently change v1 PBAC behavior.
5. Organization ancestor chains run root->target; for an ORGANIZATION-domain resource, assignment to an ancestor may establish scope to a descendant; assigning to a child never establishes access to its ancestor/sibling. **M2 returns only chains/facts; M3 owns the rule and decision**.
6. Full hierarchy snapshots reject cycles, self-parent references, orphan parents and duplicate IDs. An *already authorized* paginated tree page may contain orphaned-by-filter nodes: `FlatHierarchy::visibleRows()` masks the missing parent without fetching it or exposing its ID, matching v1's visible-tree behavior.
7. Preserve MySQL unsigned BIGINT identifiers as decimal strings for internal graph key comparisons; avoid floating-point ID corruption.
8. No cache containing actor or organization scope is retained across requests; capture a single `DateTimeImmutable` at authorization operation start in M3 and reuse it for all evaluation queries.

## How to apply on the existing working M1 installation

There are two distribution options:

- **Overlay ZIP (preferred):** extract at `D:\XAMPP\htdocs\erp\` and allow files in `backend/` and `docs/backend-rewrite/` to be created/updated. No `.env`, database or runtime config is distributed. Existing user files in `backend/public/upload/` must be kept.
- **Full repository ZIP:** reference/clean-checkout package. Back up local `.env`, `composer.lock` if locally generated, runtime uploads and other user modifications before replacing any directory; do **not** blindly delete/reinstall the existing working installation.

No new Composer dependency was introduced by M2. M1 vendor installation may be reused. M2 has no migration to run.

### Automated tests (Windows PowerShell)

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m1_pure.php
php backend\tests\m2_pure.php
python backend\tools\verify_routes.py
python backend\tools\verify_m2.py
python backend\tools\verify_baseline.py --v1-root .
python backend\tools\verify_layout.py
cd backend
php spark m2:verify
```

`php spark m2:verify` requires your configured MySQL/MariaDB + installed CI4 vendor and runs against the DB currently selected by `backend/.env`. It performs **read-only** checks: seven required tables; ward/province association; complete organization/department hierarchy; linked employee/user facts; and agreement between SQL effective assignments and the pure date predicate for up to 100 sample assignment records. It displays `SKIP` when a fixture is absent; a `SKIP` does not count as proof of parity for that scenario.

Avoid connecting this command to production by accident. Use a restored database clone with representative active/inactive employees, assignments with open/end dates, multi-level organizations and multiple memberships whenever possible. No credentials or employee PII are printed by the QA command.

### M2 acceptance criteria

- M1 auth `/me`, refresh, logout, profile and CSRF continue to work in your Frontend.
- `m2_pure.php` returns 39/39 assertions; `verify_m2.py`, `verify_routes.py`, fingerprint checks pass.
- `php spark m2:verify` completes without SQL exceptions; ideally all sample branches PASS, not SKIP.
- No public routes added and no mutation/permission behavior exposed before M3.
- For stronger evidence of behavioral parity: on a DB clone, compare old-vs-v2 snapshots for selected user IDs and UTC as-of dates, including user with no linked employee, inactive employee, future/expired assignments, multiple assignments, and an organization tree with multiple levels. The v1.2.4 ZIP itself contains no populated database or golden fixtures, so this last check is not possible offline in the authored package.

## Next M3 integration contract

`ScopeFactsReader::employeeFactsAt($userId, $operationInstant)` returns:

```php
[
    'employee' => $employeeRowOrNull,
    'effective_assignments' => $assignmentRows,
    'utc_business_date' => $asOfDate,
]
```

`ScopeFactsReader::organizationAncestorIds($organizationId)` returns root-first decimal string IDs.

The M3 `Access` decision pipeline must add base permissions + actor eligibility + assignment-ancestor scope checks + policy branches (`DENY`, `ABSOLUTE_DENY`, `ALLOW`) + workflow state conditions + logging. M2 read results **must never** be treated as permission to return an organization/employee record to the caller.

## QA performed in the authoring environment

- 39 M2 pure PHP assertions PASS.
- M1 assertions PASS; 6/6 M1 routes retained, OpenAPI baseline 150 operations.
- `frontend/` 277/277 unchanged; `backend_basline/` verified byte-for-byte against M0 fingerprints.
- PHP lint PASS for all 70 files in `backend/app` as of M2.
- `m2:verify` cannot be executed here because this container has no Composer vendor and no configured MySQL/MariaDB; final runtime parity check must run on the user's installation.
