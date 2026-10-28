# M5.2 - Organization protected CRUD (development slice)

**Status:** Source/offline implementation candidate only; HTTP/SQL and record-PBAC runtime acceptance pending on XAMPP. M5.1 Province/Ward acceptance remains independent and is not promoted to HTTP-PASS by this patch.

## Pinned v1.2.4 API-010 operations

| Verb | Path | Success |
| --- | --- | --- |
| POST | `/api/v1/organizations` | 201 |
| PATCH | `/api/v1/organizations/{id}` | 200 |
| POST | `/api/v1/organizations/{id}/actions/set-state` | 200 |

Ninety previously reviewed routes are unchanged; total 93/150 OpenAPI operations. The M2.1 `GET /organizations`, `/organizations/tree`, `/organizations/{id}` remain owned by `ReadCatalogController`. No migration, data seeder, Frontend, Apache, or `backend_basline` change is needed.

## Domain and authorization boundaries

- `OrganizationManagement/Mutation` owns mutation orchestration as a separate module because core `Access` already consumes `Organization` read projections. Putting Access imports inside the core Organization module would introduce a dependency cycle, which the M3.4D architecture gate rejects.
- The existing write feature gate defaults to `off`. Session authentication, global CSRF and explicit routes remain mandatory. No automatic route registration.
- `UnifiedAccessService::decideMutation` selects the validated **persisted parent Organization** as PBAC scope for CREATE; the child does not have an ID before insertion. Once inserted, the new persisted row must also satisfy `ReadAccessBridge` VIEW before commit; a denied response rolls back the insert.
- EDIT / SETSTATE checks VIEW first, then action permission on the locked original record. A change to `parent_id` additionally requires CREATE permission in the destination parent scope. No input JSON may supply an authorization override or action code.
- Only a server-authorized Lifecycle initial State is used for CREATE. PATCH rejects even a no-op `status`. SETSTATE requires exactly one ACTIVE persisted `setstate` transition, target-State equality, transition PBAC and a conditional status update.
- `GROUP -> COMPANY -> BRANCH/PLANT/WAREHOUSE`, `COMPANY -> BRANCH/PLANT/WAREHOUSE`, `BRANCH -> PLANT/WAREHOUSE`, `PLANT -> WAREHOUSE` are validated as in the baseline. Only one GROUP root may exist; no cycles, incompatible children or dangling parents.
- The MariaDB connection holds a named tree lock while validating + writing a transaction. Required dates and Province/Ward references are checked before persistence; immutable audit metadata comes from the authenticated actor.
- Existing deployments must have an initialized GROUP root and PBAC scope. This version deliberately does not bootstrap a first GROUP root through a public API; it fails closed.

## Apply and verify on Windows/XAMPP

From an ERP tree that already has the M5.1 patch:

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\M5.2-protected-organization-crud.patch
git apply .\M5.2-protected-organization-crud.patch

cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expected source-only signatures: 66/66 pure/fixture suites, M3.4D 6/6 gates, `M5.1 SOURCE PASS: 84 existing + 6 new = 90/150` and `M5.2 SOURCE PASS: 90 existing + 3 new = 93/150`.

### Simple DEV HTTP checks, without creating database clones

Use existing `api.monmin.net` (rewrite) and `apibase.monmin.net` (baseline) development vhosts. First verify GET `/api/v1/organizations` and `/api/v1/organizations/tree` still work. An unauthenticated POST must be rejected; authenticated POST with write gate `off` must return `403 ACCESS_MUTATION_NOT_ENABLED` before parsing the payload. Keep CSRF enabled.

For successful CRUD/record PBAC verification use a developer account authorized to mutate organizations on DEV data, and set `DINKERP_M33B_WRITE_MODE=enabled` **only in the intended DEV Apache process**, not via the test script. Use unique data with a valid persisted parent and Lifecycle initial State; perform POST, PATCH, SETSTATE, then GET each committed ID. Confirm audit fields, parent ancestry, API errors and rollback on invalid parent, cycles, invalid date, Ward/Province mismatch, denied parent scope, duplicate code, and invalid transition. Disable the DEV write gate after testing. Requiring the same IDs/permissions on the two dev vhosts is necessary for a meaningful baseline parity comparison.

**Not tested by this offline delivery:** authenticated HTTP mutations, MariaDB transactions, row-level actor PBAC, concurrent writers, successful baseline mutation parity and Frontend CRUD. Do not mark M5 complete or claim production write readiness from this patch.
