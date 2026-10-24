# M5.1 - Province/Ward protected CRUD (development slice)

**Status:** SOURCE/OFFLINE accepted, HTTP/MariaDB and actor-specific PBAC pending on the developer's XAMPP. M5 is **not complete**: Organization, People, Contacts mutation endpoints are deferred to separate M5 slices. Neither the baseline nor the Frontend is edited.

## Roadmap & API scope

`DEPENDENCY_ORDER.md` defines M5 as **Locations, Organization, People, Contacts protected CRUD**, dependent on M3 PBAC and M4 Lifecycle/Numbering. M5.1 implements exactly six additional baseline v1.2.4 operations (90/150 total routes after the existing 84):

| Method | Path | Expected success |
|---|---|---|
| POST | `/api/v1/provinces` | 201 |
| PATCH | `/api/v1/provinces/{id}` | 200 |
| POST | `/api/v1/provinces/{id}/actions/set-state` | 200 |
| POST | `/api/v1/wards` | 201 |
| PATCH | `/api/v1/wards/{id}` | 200 |
| POST | `/api/v1/wards/{id}/actions/set-state` | 200 |

Existing M2.1 `GET /api/v1/provinces`, `/provinces/{id}`, `/wards`, `/wards/{id}` are **unchanged**. No invented DELETE/autoroute endpoints, schema migration, seeder or Frontend changes. The baseline's other M5 operations remain unimplemented in the rewrite.

## Design and invariants

- New owner: `app/Modules/Locations/Mutation/`; routes in `Config/M5Routes.php` included once by `Config/Routes.php` inside `/api/v1`. No copy of the baseline Location service.
- Shield authenticated actor, existing `DINKERP_M33B_WRITE_MODE` default `off`; `UnifiedAccessService` handles per-action PBAC, and `ReadAccessBridge` enforces per-record VIEW for the target and returned row. Unauthorized/disabled cases fail closed.
- Only explicit `code`, `name` and Ward `province_id`, `name_full` may be edited. Client audit fields discarded; other fields rejected. Lifecycle initial State is loaded from persisted resource metadata; no request-controlled initial State.
- Status changes are rejected from PATCH; `/actions/set-state` must find exactly one active persisted `setstate` Lifecycle transition matching target State and current State and pass transition PBAC. Ambiguous/unconfigured actions are rejected.
- The mutating transaction uses the same connection for PBAC, row locks, audit, duplicate checks and updates. A changed Ward province must exist and be ACTIVE; changing it must not invalidate Organization/Contact address references. Persisted row is locked first.
- No M5 migration or new table is needed. This patch must not change `backend_baseline`, Frontend, existing `M4` endpoints, or Apache configuration.

## Apply to an existing M4.2C.2 working tree (PowerShell)

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\M5.1-protected-locations-crud.patch
git apply .\M5.1-protected-locations-crud.patch

cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expected source-only signature: `M3.4B OFFLINE: 62/62 suites PASS` and `M5.1 SOURCE PASS: 84 existing + 6 new = 90/150 routes`. These commands do **not** write to SQL and do **not** establish HTTP or database parity. Separate M4 integration and two-writer results are not M5 acceptance evidence.

## Lightweight DEV HTTP checks (no mandatory DB clone)

The developer already runs two distinct Apache VirtualHosts: `https://api.monmin.net` for the rewrite and `https://apibase.monmin.net` for the v1.2.4 baseline. To validate M5.1 after applying the patch:

1. Verify unauthenticated POST/PATCH requests return 401; OPTIONS/CORS and GET Location endpoints still behave as before.
2. With a signed-in DEV session and write gate **off**, a valid Province/Ward POST must be refused with `ACCESS_MUTATION_NOT_ENABLED`; verify no new rows. In another DEV-only test where writes are deliberately enabled, use an actor without `province.create`/`ward.create` and confirm the PBAC denial; do not test against a production DB.
3. On a disposable or otherwise approved **DEV-only** database, optionally set the gate to `enabled` for that environment, use session/cookie + CSRF (as in M4.2A) and a unique code, then test create, edit, lifecycle set-state, and GET by returned ID. Restore gate to `off` afterwards. No need to generate a DB clone or install a new server for this developer workflow.
4. Negative data checks: duplicate `code`, invalid `province_id`, inactive province, invalid transition, PATCH `status`, invalid Ward reparent with Organization/Contact references. A failed mutation must not change SQL. Test only fixtures for which the developer has permission to make changes.
5. To compare with v1.2.4 over the two DEV VirtualHosts, both must have comparable DEV data/permissions. Matching HTTP status alone is not sufficient to establish mutation parity; compare persisted response fields and SQL effects before claiming it. Do not mark these tests PASS without captured logs.

**Important:** The local review environment for this patch has PHP CLI but not CI4 `vendor` / a running MariaDB instance; therefore M5.1 HTTP, real transaction and record-PBAC integration are **NOT_TESTED**, even when all offline tests PASS. Keep real/production writes disabled.

## Next M5 slices

Implement Organization tree protected mutations, People / assignment protected mutations, and Contacts, in dependency order; add DEV HTTP permission & state/transaction checks for each. Do not call the entire M5 milestone complete based on M5.1.
