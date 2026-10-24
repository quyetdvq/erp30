# M4.2C DEV shortcut for existing Apache HTTPS VirtualHosts

This is **development-only** HTTP comparison, not the release/clone-based M4.2C
acceptance runner. It does not change any application controller, service,
database schema, `.env`, Apache configuration, or `test-unit.ps1` behavior.

The developer has already configured:

| Codebase | HTTPS Host | DocumentRoot in current setup |
| --- | --- | --- |
| Rewrite | `api.monmin.net` | `D:/XAMPP/htdocs/erp/backend` |
| v1.2.4 baseline | `apibase.monmin.net` | `D:/XAMPP/htdocs/erp/backend_baseline` |

The DEV tool connects **only to `https://127.0.0.1:443`**, overriding HTTP Host
and TLS SNI to select one of those two local Apache VirtualHosts. No DNS lookups
or public Internet calls are performed. As local XAMPP certificates can be
self-signed, TLS certificate trust checking is bypassed **only for these fixed
loopback connections**. Never reuse this transport against a remote system.

## Run

```powershell
cd D:\XAMPP\htdocs\erp\backend

# Zero setup; verifies two local HTTPS vhosts and session/CSRF bootstrap only.
php tools\m42c_dev_parity.php smoke

# For authenticated GET comparison, set the same DEVELOPMENT test user on both
# codebases. Type values locally; do not commit secrets or paste them in chat.
$env:M42C_DEV_USER = 'YOUR_DEV_TEST_LOGIN'
$env:M42C_DEV_PASSWORD = Read-Host 'DEV test password'

# Four no-ID GET endpoints, in en and vi; exits 3 as PARTIAL.
php tools\m42c_dev_parity.php read

# Existing resource, numbering series, and organization IDs in your DEV data:
php tools\m42c_dev_parity.php read --resource-id=5 --series-id=8 --organization-id=12

Remove-Item Env:\M42C_DEV_PASSWORD
```

The numeric IDs above are EXAMPLES ONLY. For 7/7 GET operations, replace them
with actual permitted IDs. If IDs are absent, the tool reports `PARTIAL_DEV_GET_PARITY`
rather than a false full pass; exit code is 3. A full 14/14 en/vi GET comparison
is `PASS_DEV_SEVEN_GET_PARITY`, exit code 0, only when both backends return 200,
correct `Content-Language` and equivalent normalized JSON (excluding the random
request ID). Any mismatch exits 1 with a JSON report.

Reports: `backend/writable/m42/reports/m42c_dev_*.json`.
Only operation names, HTTP status and differences are recorded. Credentials,
session cookies and returned business data are not saved in reports.

## Intentional scope

- No MariaDB DSNs, clone databases, `m34_clone_guard`, `run_id`, SQL snapshots,
  or manually prepared JSON scenario files.
- `smoke` checks service reachability; it is **not parity**.
- `read` checks at most **seven GET operations**, in `en` and `vi`, using current
  developer database state. It never sends any authorized business mutation.
- `POST .../actions/validate-config` is **not run**, even though the M4 contract
  categorizes it as dry-run. Neither are the six M4 write operations.
- Logging in may update normal Shield session/last-active metadata; no assertion
  is made that the database is untouched.
- The two backends must use comparable development data and equivalent test user
  permissions. If they use different DBs or changed data, an HTTP mismatch is
  a valid finding, not proof of a code regression.
- The existing M4.2A.1 and M4.2B sandbox reports remain the local SQL and
  concurrency evidence. Independent authorized mutation replay, full 8-route
  read/denial clone parity and production readiness are **not claimed**.

If the development team intentionally chooses to defer those acceptance gates,
track that as a **development-scope decision**, not as a PASSED test.
