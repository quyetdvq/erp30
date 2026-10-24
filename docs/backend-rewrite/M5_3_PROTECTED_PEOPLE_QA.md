# M5.3 — Employee and Employee Assignment Protected CRUD (DEV)

**Status: SOURCE/OFFLINE PASSED; HTTP/MariaDB and actor-specific record-PBAC NOT TESTED.** This slice builds on M5.2 and preserves the accepted M2.1 list/detail read routes. It is not a production gate or a successful mutation parity claim.

## Exact v1.2.4 API-020 routes

| Verb | Path | Success |
| --- | --- | --- |
| POST | `/api/v1/employees` | 201 |
| PATCH | `/api/v1/employees/{id}` | 200 |
| POST | `/api/v1/employees/{id}/actions/set-state` | 200 |
| POST | `/api/v1/employee-assignments` | 201 |
| PATCH | `/api/v1/employee-assignments/{id}` | 200 |
| POST | `/api/v1/employee-assignments/{id}/actions/set-state` | 200 |

Total: **99/150 OpenAPI operations** (93 previously reviewed + 6). Departments, Positions and Contacts mutations are **not included**, and no baseline controller, Frontend, SQL migration, Apache virtual host, `.env` or public GET route is edited.

## Security and business semantics

- Every mutation requires Shield authentication, existing CSRF policy, explicit operation routing and `DINKERP_M33B_WRITE_MODE=enabled` (default `off`). No autorouting or backend-supplied permission hints.
- `PeopleManagement/Mutation` is separate from the M2 `People` read-projection module to avoid a module cycle with central `Access`.
- `employee` is MASTER-scoped; `employee_assignment` is ORGANIZATION-scoped. CREATE authorization uses the verified persisted Organization for an assignment, not an arbitrary client-supplied scope override. EDIT/SETSTATE require VIEW then action PBAC on the locked original row. Changing assignment organization additionally checks CREATE in the destination.
- Employee creation uses the configured Lifecycle initial state, never direct user-supplied State. PATCH rejects any direct `status`, even a no-op. SETSTATE resolves exactly one active `setstate` transition and checks the transition action before conditional persistence.
- Employee Assignment has the independent **Native ACTIVE/INACTIVE State**, not Lifecycle. PATCH still rejects `status` to require separately authorized SETSTATE; native state changes never create artificial Lifecycle Transitions.
- Manager changes reject self-management, cycles, missing and inactive target managers. Employee codes and linked Shield user IDs must remain unique, with MariaDB constraints as a secondary backstop.
- Employee Assignment validates employee, organization and optional department/position references; prevents reassignment to a different employee; validates inclusive dates and rejects overlapping active primary assignments for the same employee. A primary change is serialized by a MariaDB connection-owned lock, with SQL transaction rollback.
- Post-mutation VIEW is checked **before commit**; failure rolls back. Audit actor and UTC timestamps come from the server, never from JSON.

## Apply on existing M5.2 tree

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\M5.3-protected-employee-assignments.patch
git apply .\M5.3-protected-employee-assignments.patch

cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expected: `70/70` pure/fixture suites PASS, M3.4D six gates, M5.1 `90/150`, M5.2 `93/150`, M5.3 `99/150` source acceptance.

## Simple DEV HTTP checks — no database clone

Reuse `api.monmin.net` for the rewritten backend and `apibase.monmin.net` for baseline. With write gate **off**, verify an anonymous POST fails, and an authenticated POST with a valid CSRF session returns `403 ACCESS_MUTATION_NOT_ENABLED`. This does **not** prove the mutation path.

For *real* developer-mutation QA, deliberately enable write gate only on an isolated DEV process and use a developer account with explicit Employee and Assignment permissions. Use unique synthetic employee codes and known valid resource IDs (not arbitrary production identities). Confirm: 201 create + GET, PATCH + GET, expected State transitions, native Assignment SETSTATE, manager self/cycle/inactive rejections, overlap rejection including shared endpoints, invalid date/reference rejection, unauthorized actor and destination scope denial, rollback, audit and transaction effects. Restore the gate to **off** afterward.

**Remaining gates:** live HTTP/MariaDB successful mutations, denied actor PBAC, two-writer primary overlap, independent baseline successful-write parity, Department/Position and Contacts slices, and Frontend CRUD E2E. Existing M5.1/M5.2 mutation runtime acceptance is also pending. No database clone is required for ordinary development, but same-starting-state data is required for strict baseline *write* parity.
