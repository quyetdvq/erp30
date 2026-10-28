# M9.3 — Release Readiness (lightweight, source-first)

**Base:** `erp_v1.5.0.zip` + M9.1 Employee optimization + M9.1 Playwright assertion patch + M9.2 read-only QA patch.
**M9.0:** NOT applied. M9.3 supersedes its stale *frontend fingerprint proposal*, without requiring the M9.0 patch.
**Scope:** baseline reconciliation, public static asset hygiene, safe source/build preflight and concise operator handoff. **No runtime endpoint, business mutation, PBAC, frontend UI flow, schema, migration, or frozen OpenAPI change.**

## 1. What is changed

1. `backend/tools/verify_baseline.py` continues to validate the independently pinned v1.2.4 frontend manifest and both exact OpenAPI files, but now allows **only the reviewed M8/M9.1 delta** through `frontend-approved-delta-v1.5.0-m91.json`. The delta itself has a hard-coded SHA-256 pin in the verifier. Only known workspace outputs at the frontend root (`node_modules`, `dist`, Playwright reports, coverage and local .env/tsbuildinfo) are excluded from the *source* inventory; all `src/`, `public/`, `e2e/` and `scripts/` contents remain hash-checked. The built `dist` is inspected separately by the M9.3 artifact gate. The unchanged backend baseline remains independently pinned and checked by `verify_layout.py`.
2. A stale **Office lock file** `frontend/public/import-templates/~$product-v1.xlsx` is deleted from the source. The legitimate `.xlsx` and `.csv` Product Import templates remain byte-identical. Root `.gitignore` excludes future Office lock/cache files.
3. `backend/tools/m93_release_preflight.py` provides **one read-only command** for source and optional built frontend artifact checks. No CI4 bootstrap, authenticated HTTP, MariaDB, DB mutation, external services, or production connection is used.
4. `backend/tests/m93_release_preflight_pure.py` supplies lightweight fail-closed filesystem tests; no database setup is needed.

## 2. Apply to accepted M9.2 worktree (Windows PowerShell)

**Close Excel if the import template is open** before applying. Place the patch at ERP root:

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\ERP_v1.5.0_M9_3_release_readiness.patch
git apply .\ERP_v1.5.0_M9_3_release_readiness.patch

python .\backend\tests\m93_release_preflight_pure.py
python .\backend\tools\m93_release_preflight.py
```

Expected source result: `M9.3 PREFLIGHT: PASS_SOURCE`. If a changed/missing/unapproved file is detected, STOP and review the difference; never replace a fingerprint with hashes generated from an unreviewed running tree. A newly recreated `~$...xlsx` in `frontend/public/` is an **unsafe deployment artifact**, not a policy failure to bypass.

For a frontend build check (optional, but required before claiming the **built artifact** passed):

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm build
cd ..
python .\backend\tools\m93_release_preflight.py --require-build
```

The final gate checks that compiled JS entry/assets exist and that no Vite development entry, source map, Office lock file, `.env`, private file extension, or symlink is inside `frontend/dist`. The SPA `.htaccess` and Product Import `.csv/.xlsx` templates must be available in `dist`. This does **not** prove the page renders correctly over HTTPS on the deployment host.

## 3. Quick operator UI checks (no special testing environment)

- Log in as a normal permitted user. Open `/people/employees`, change page, open one detail, and open/close **Tạo** twice. Confirm no loading regression or unexpected error. M9.1 remains accepted and code is unchanged by M9.3.
- Refresh the browser on a deep route, e.g. `/people/employees`; confirm Apache serves the SPA rather than a 404. (The source preflight only checks the `.htaccess` file.)
- Confirm Product Import template download yields the valid CSV/XLSX (not an Office `~$` file). No Import mutation is necessary.
- Check browser Network/Console for API 401/403/5xx or unexpectedly repeated queries, and verify runtime cookies/HTTPS/CORS according to your actual deployment. These are observation checks, not new automated acceptance claims.

## 4. Before a production change, keep these explicit

- **Snapshot and rollback:** independently back up the currently deployed application code/assets and database using the existing operator process; record recovery location and owner. Rollback the application release as a versioned unit; never restore a database blindly merely because a frontend build failed. This M9.3 patch needs **no** migration or DB rollback.
- **Runtime config:** deployment owner must confirm virtual host points to `backend/public`, the production environment does not expose errors, trusted HTTPS forwarding/cookie/CSRF settings are appropriate, and storage/writable permissions are correct. The script does not inspect local secrets or alter config.
- **Release authority:** M8.7 functional acceptance and M9.1 PASS remain intact. M9.2 is accepted **with explicit waivers** for live journal DB audit/concurrency/fault recovery; those waivers must not be labeled test PASS. Neither source PASS nor built-artifact PASS alone authorizes production cutover.
- **Excluded:** migration/reset, active HTTP mutation, restore rehearsal, invasive fault injection, two-writer tests, changing a frozen API route, changing Product Import journal logic.

## 5. Acceptance record

| Gate | Claim once observed |
| --- | --- |
| 281-file reviewed frontend fingerprint | Source match only, M8/M9.1 accepted diff |
| 806-file backend baseline + media layout | Byte-level layout/source only |
| 150/150 frozen API routes | Route/handler source contracts, not live HTTP |
| 14 pure negative/positive public-artifact/source fixtures | Filesystem guard behavior, not Apache behavior |
| `M9.3 PREFLIGHT: PASS_SOURCE` | Source hygiene gate complete |
| `M9.3 PREFLIGHT: PASS_SOURCE_AND_BUILD` | Source and built SPA structural gate complete |
| Real server and production readiness | Not attested by M9.3; separate operator decision |

**Status delivered:** M9.3 source-only candidate, ready for developer's local application and build verification. Do not mark full deployment/production acceptance from this document alone.
