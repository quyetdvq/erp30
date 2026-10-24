# M5.4 — Department/Position protected CRUD (DEV)

**Status: SOURCE/OFFLINE PASSED. HTTP/MariaDB, record PBAC and baseline successful-write parity NOT_TESTED.**

Based on the SHA-256-pinned v1.2.4 API-020 operation manifest and the M5.3.1 source tree. Adds **six** mutation routes (99 → **105/150**):

| Method | Path | Operation |
|---|---|---|
| POST | `/api/v1/departments` | `createDepartment` (201) |
| PATCH | `/api/v1/departments/{id}` | `updateDepartment` (200) |
| POST | `/api/v1/departments/{id}/actions/set-state` | `setDepartmentState` (200) |
| POST | `/api/v1/positions` | `createPosition` (201) |
| PATCH | `/api/v1/positions/{id}` | `updatePosition` (200) |
| POST | `/api/v1/positions/{id}/actions/set-state` | `setPositionState` (200) |

Existing GET routes remain in M2.1 `ReadCatalogController`; no DELETE, wildcard mutation, table migration, baseline, Frontend or Apache changes.

## Implementation safeguards

- Write-gate remains `DINKERP_M33B_WRITE_MODE=off` by default. Only an operator-confirmed development instance should set `enabled`; this flag opens **all** existing gated mutations, not just Departments/Positions.
- Use Shield authentication; `UnifiedAccessService::decideMutation` for `create`/`edit`/`setstate`, and `decideTransitionAction` for the authorized Lifecycle action. CREATE PBAC resolves the **server-owned initial Lifecycle State**, not a JSON-selected status. `ReadAccessBridge` checks persisted VIEW before changes and after writes, inside the SQL transaction.
- Master Resource types `department` and `position` take PBAC scope from authoritative resource catalog and actor facts, never a submitted `organization_id` or policy bypass.
- Department hierarchy reads persisted rows with locks, checks parent existence and rejects direct/indirect or already-corrupt cycles. Department and Position code uniqueness is protected by a per-type MariaDB named lock and the baseline table's unique index.
- PATCH refuses `status`; only `/actions/set-state` may perform an enabled `setstate` Lifecycle transition. State updates use old-status predicates and rollback on conflicting writes.
- Ignore client audit columns like baseline, but reject unsupported business fields; validate positive IDs, nullable parent, signed INT `level` and Unicode text lengths; assign `created_by/at`, `updated_by/at` server-side.
- Stable `error.code` and baseline-translated `error.message` for `Accept-Language: vi` and `en`. No raw internal exceptions returned to clients. Baseline locale PHP files remain unchanged.

## Apply after M5.3.1

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\M5.4-protected-department-position.patch
git apply .\M5.4-protected-department-position.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expected source-only outputs: **75/75** pure/fixture tests PASS; M3.4D 6/6; M5.1 90/150, M5.2 93/150, M5.3 99/150 and M5.4 **105/150**.

## Minimal runtime QA using the existing DEV Apache

Keep `https://api.monmin.net` on the rewrite and `https://apibase.monmin.net` on the baseline. No manual DB cloning is required for normal development smoke tests. Use an authorized **nonproduction** actor, active CSRF token/session, and temporary unique test codes; do not reuse real business records.

1. **Department:** create a root and child (`code`, `name`, `parent_id`), GET each, PATCH a name and change a parent. Verify both 201/200 and persistence. Reject duplicate code, missing parent, self-parent and a cycle. Test a valid `setstate` action only if configured Lifecycle transition exists; an invalid status/edge must return 422 with no DB change.
2. **Position:** create (`code`, `name`, optional signed `level`), GET, PATCH a level/name and test `setstate`. Reject duplicate code, invalid `level` (outside signed INT), direct `status` PATCH and unsupported scope field.
3. **Access:** verify anonymous 401, disabled gate 403 (`ACCESS_MUTATION_NOT_ENABLED`), denied actor 403 (`AUTH_POLICY_DENIED`), and missing/denied detail 404. These cases are separate from successful actor cases.
4. **Localization:** send equivalent failing requests with `Accept-Language: en` and `vi`. The status and `error.code` must match, while `error.message` must be translated.
5. **Concurrency:** two sessions attempt the same code or mutually conflicting department reparent operations; the unique constraint and serialization must prevent duplicates/cycles. Not yet asserted by the offline suite.

**Not proven:** live HTTP authorization, real MariaDB atomicity, concurrency and replay against baseline; previous M5.1–M5.3 runtime gates are also pending. M5 milestone is not complete until Contacts mutations and Frontend CRUD/PBAC E2E are verified. No production deployment assertion is made.
