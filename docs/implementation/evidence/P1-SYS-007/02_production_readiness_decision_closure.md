# P1-SYS-007 - Production Readiness Decision Closure

Date: 2026-10-09
Task: `P1-SYS-007`
Disposition: **BLOCKED - decision and deployment evidence incomplete**

## Decision register

The owners below are accountable roles, not invented people or credentials.
No production value is inferred from the development `.env`.

| Decision item | Owner | Approved source | Required evidence / input | Status | Release impact |
|---|---|---|---|---|---|
| Secrets, DSN, DB TLS, HTTPS base URL, reverse-proxy trust | Operations / Platform, Security review | Contract v1.10 §§19-20; Plan v1.10 §3.2 | Secret-manager reference (no secret value), non-default DB principal, `DBDebug=false`, TLS/CA verification, approved HTTPS host/proxy IPs, staging boot/config output | OPEN | Blocks production deployment |
| SPA CORS origins and credentials | Operations / Platform, Security review | Contract v1.10 §20; API transport contract; current CORS implementation | Exact HTTPS SPA origin list, no HTTP/localhost entries, credentialed preflight and rejected-origin evidence | OPEN | Blocks authenticated SPA release |
| Product import file/row limits | Operations with Product owner confirmation | Contract v1.10 §§11, 20; FRS import requirements | Approved `import.max_file_bytes`, `import.max_rows`, CSV/XLSX staging boundary tests, rejection evidence | OPEN | Blocks import release scope |
| Domain lock timeout and retry | Operations / Platform | Contract v1.10 §§9.2-9.4, 20 | Approved `lock.timeout_seconds`, `lock.retry_count` (maximum one retry only when retry-safe), timeout/deadlock staging evidence | OPEN | Blocks concurrency release sign-off |
| Business timezone | Operations with Product owner | Contract v1.10 §3.1; FRS `FRS-P1-GEN-008`; Plan §4.4 | Company timezone settings, Group/default timezone where required, UTC persistence and boundary-date evidence | OPEN | Blocks date/effective-time release sign-off |
| `authorization_logs` / `import_jobs` retention | Security / Operations | FRS v1.12 §22 (`FRS-P1-AUD-003`); Contract v1.10 §21; Plan decision register | Retention duration, purge owner/job, access/audit controls, restore/expiry evidence | OPEN | Blocks production operations approval |
| CSRF token/bootstrap contract | Security, API and Frontend owners | Contract security/error rules; current SPA auth contract; no approved token decision in P1 authority | Approved cookie/header mode, bootstrap endpoint or equivalent, login/mutation regression evidence over HTTPS/CORS | BLOCKED - decision required | Blocks authenticated browser release |
| `SYSTEM_ADMIN` reconciliation | Security / Operations with Architecture review | `DEC-SEC-001`; `CHANGE-USER-IDENTITY-MUTATION-001`; `PrivilegedAuthorizationGroupSeeder`; migration `2026-10-08-000013` | Encrypted pre-state backup/checksum, migration approval, exact 93-action post-state assertion, review of two display-setting extras, rollback sign-off | BLOCKED - review required | Blocks authorization release |
| SYS-005 legacy baseline | Architecture / Release owner | Plan v1.10 §17; SYS-005 evidence and ledger row | Authoritative populated legacy dump/snapshot plus provenance, or formal scope-change approval | BLOCKED - evidence missing | Independent release blocker |

The approved privileged baseline remains 92 actions. The explicitly approved
`user.reset_password` addition makes the intended post-migration matrix 93.
The current development database has 94 rows because `display_setting.view`
and `display_setting.edit` are outside that matrix; no automatic grant or
revoke is authorized by this register.

## Redacted production environment checklist

Populate values only through the approved secret/configuration channel. The
placeholders below are intentionally non-functional and must not be replaced
in source control with secrets.

```dotenv
CI_ENVIRONMENT=production
app.baseURL=https://<approved-api-host>/
app.forceGlobalSecureRequests=true
app.CSPEnabled=true

database.default.hostname=<private-db-host>
database.default.database=<approved-database>
database.default.username=<non-default-secret-managed-user>
database.default.password=<secret-manager-reference>
database.default.DBDebug=false
database.default.encrypt=<approved-tls-setting>

cors.allowedOrigins=https://<approved-spa-host>
VITE_API_BASE_URL=https://<approved-api-host>

import.max_file_bytes=<approved-operations-value>
import.max_rows=<approved-operations-value>
lock.timeout_seconds=<approved-operations-value>
lock.retry_count=<approved-operations-value>
business.timezone=<approved-company-or-group-setting>
security.csrfProtection=<approved-token-mode>
```

Also verify outside `.env`: `encryption.key` is supplied by the secret
manager, PHP `display_errors=0` and UTC runtime settings are enforced, OPcache
and TLS policy match the platform baseline, reverse-proxy IPs are explicit,
logs go to an access-controlled sink with approved rotation, and no secret is
present in repository files, build output, HTTP responses, or logs.

## Staging verification commands

Run from a staging checkout after injecting approved values through the staging
secret manager. Do not replace placeholders with real values in this artifact.

```powershell
Set-Location backend
$env:CI_ENVIRONMENT = 'production'
php spark config:check App
php spark config:check Auth
php spark config:check Cookie
php spark config:check Cors
php spark config:check Database
php spark filter:check GET /api/v1/auth/me
php spark filter:check POST /api/v1/auth/login
php spark phpini:check
php spark migrate:status                 # read-only; never migrate/reset here
```

```powershell
# Use the approved staging host/origin from the secret/configuration channel.
curl.exe -i https://<approved-api-host>/api/v1/health
curl.exe -i -X OPTIONS https://<approved-api-host>/api/v1/auth/login `
  -H "Origin: https://<approved-spa-host>" `
  -H "Access-Control-Request-Method: POST" `
  -H "Access-Control-Request-Headers: content-type,x-request-id"
curl.exe -i -X OPTIONS https://<approved-api-host>/api/v1/auth/login `
  -H "Origin: https://<unapproved-origin>" `
  -H "Access-Control-Request-Method: POST"
```

The staging browser lane must verify HTTPS-only session cookies, login,
`/auth/me` hydration, logout, one authorized mutation, one backend 403/404,
and the final error envelope without stack traces or secrets. Do not call this
UAT; it is deployment/security verification only.

## Read-only migration and permission procedure

This is a reviewed procedure, not an instruction to mutate `erp` or production.
The current state is read-only: `erp` has 12 App migrations through `000012`;
`erp_test` has 13 App migrations through `000013` plus one PHPUnit support
migration.

### Approval and backup gates

1. Security, Operations, and Architecture approve the migration and permission
   reconciliation ticket. Attach the exact target environment and maintenance
   window; reject any request that targets development `erp` during SYS-007.
2. On an isolated maintenance connection, export only the relevant rows from
   `groups`, `resources`, `resource_actions`, `group_permissions`, and the
   migration ledger. Encrypt the artifact, record a checksum and row counts,
   and test that the backup can be read. Do not put the export in Git.
3. Abort before any write if `SYSTEM_ADMIN` is not unique, catalog action
   ownership is ambiguous, the backup checksum does not verify, or any
   unrecognized permission is present.

### Dry-run verification

Use read-only queries to record the group id, current 94-row set, catalog ids,
and the absence/presence of `user.reset_password`. Compare the normalized
result to `PrivilegedAuthorizationGroupSeeder::approvedActions()` plus the
approved additive action. Do not use a count-only comparison.

### Forward-only execution boundary (not run)

After approval and backup, apply migration
`2026-10-08-000013_AddUserResetPasswordAction` through the normal deployment
pipeline. Never edit an applied migration, call its no-op `down()`, insert a
manual ledger row, or run a development refresh/reset. In one transaction,
perform only the explicitly reviewed correction of the two known
`display_setting.view/edit` rows, then assert the exact 93-row matrix and that
no direct Shield user permissions changed.

### Abort and rollback criteria

Abort before commit if migration status, resource/action ownership, backup
checksum, expected row identity, direct-user permissions, or the exact 93-row
assertion differs from the approved plan. If a transaction fails, roll back
that transaction and preserve the pre-state artifact. A committed forward
migration is not rolled back with `down()`; any post-commit defect requires a
new reviewed forward migration or approved reconciliation. Restore permission
rows only from the verified backup under a separately approved maintenance
transaction, then rerun the complete assertion and audit review.

## SYS-005 independent blocker

SYS-005 is not closed by the `erp`/`erp_test` comparison. The repository has no
authoritative populated legacy baseline or provenance-bearing dump after the
approved reset, so a forward rehearsal from that state cannot be proven. The
disposition may change only after authoritative legacy evidence is supplied or
an explicit scope-change decision approves a different rehearsal basis. Do not
fabricate migration history, treat `erp_test` as the legacy baseline, or change
the SYS-005 row as part of SYS-007.

## Release and UAT gate

SYS-007 remains **BLOCKED**. Before UAT can start, all `OPEN` and
`BLOCKED - decision required/review required` rows above must have approved
decisions and staging evidence, the migration/permission reconciliation must
pass its exact assertions, and SYS-005 must have authoritative evidence or
formal scope-change approval. Until then the release disposition is
**NOT READY / UAT NOT AUTHORIZED**.

## Git disposition

No completion commit is permitted while this task is blocked. A remediation-only
commit was not created in this closure because the live ledger file contains the
pre-existing SYS-005 disposition change alongside the SYS-007 row; staging the
whole file would capture unrelated work. Pricing files and the SYS-005 evidence
directory remain untouched and unstaged.

## HTTPS recheck (2026-10-09)

The configured public hosts were tested read-only from the repository host. TLS
negotiation succeeds when certificate revocation checking is disabled for this
Windows test host (`curl --ssl-no-revoke`); the API health endpoint returned
`200 OK` with the expected JSON envelope. This proves HTTPS reachability only.

The endpoint is not running the required production configuration yet:

- `GET https://api.monmin.net/api/v1/health` returns `200`, but the response
  sets `ci_session` without `Secure`, exposes `X-Powered-By`, and includes a
  `Debugbar-Link` pointing to `http://api.monmin.net`.
- `OPTIONS` with `Origin: https://erp.monmin.net` returned `204` without an
  `Access-Control-Allow-Origin` header, while `Origin: http://erp.monmin.net`
  was accepted with credentialed CORS headers. The approved HTTPS SPA
  therefore cannot use the current live CORS policy, and an HTTP origin
  remains allowed.
- `GET http://api.monmin.net/api/v1/health` returned `200` rather than an
  HTTPS redirect/rejection.
- Local standalone checks still load `backend/.env` as development with
  `app.baseURL=http://api.monmin.net/`; `php spark config:check App` reports
  `forceGlobalSecureRequests=false`, `CSPEnabled=false`, and `Cookie::secure=false`.
  `php spark phpini:check` reports `display_errors=1`, timezone
  `Europe/Berlin`, and OPcache disabled.

Independent CLI verification after the recheck: `php spark list` passed and
testing-mode `php spark migrate:status` listed migration `000013` as pending;
no migration, seed, reset, or database write was executed. The HTTPS findings
keep SYS-007 **BLOCKED** and UAT **NOT AUTHORIZED** until Operations/Platform
activates the approved production environment and supplies the exact HTTPS
SPA origin, then the cookie, CORS, redirect, error-exposure, and browser auth
checks pass.

## Remediation recheck (2026-10-09)

The non-secret HTTPS deployment settings were applied to the ignored local
`backend/.env` used by the configured Apache vhost: HTTPS base URL, secure
requests, CSP, secure cookies, the exact HTTPS SPA origin, and Shield risky
feature opt-outs. The front controller and filter changes are repository-owned.
No database credential or secret was changed.

Live read-only verification now passes for the previously observed transport
defects:

- `GET https://api.monmin.net/api/v1/health` has no `X-Powered-By` or
  Debugbar headers; `https://api.monmin.net/api/v1/auth/me` returns the stable
  `401 UNAUTHENTICATED` envelope with `Secure; HttpOnly; SameSite=Lax`.
- `GET http://api.monmin.net/api/v1/health` returns `307` to HTTPS and emits
  HSTS/CSP headers.
- HTTPS SPA preflight returns
  `Access-Control-Allow-Origin: https://erp.monmin.net` with credentials. An
  HTTP SPA origin receives the fixed HTTPS ACAO value, so it is not accepted
  by the browser.
- CLI config checks report HTTPS enforcement, CSP, secure cookies, the exact
  HTTPS CORS origin, disabled registration/magic-link/remembering, and the
  production-like filter chain without DebugToolbar/PerformanceMetrics.

The runtime still reports `CI_ENVIRONMENT=development`, and its default DB
identity remains root with a blank password. This is intentionally not changed:
switching to `production` without an Operations-supplied non-default DSN would
trigger the existing fail-closed guard and take the service down. PHP
`display_errors`, host timezone/OPcache, CSRF bootstrap, migration `000013`,
SYSTEM_ADMIN reconciliation, and SYS-005 evidence remain release blockers.

## Production-mode recheck (2026-10-09)

Operations switched the local runtime to `CI_ENVIRONMENT=production`. The
HTTPS base URL, secure requests, CSP, secure cookies, and Shield opt-outs load
correctly. The HTTPS SPA origin was restored as an active, exact
`cors.allowedOrigins` setting (the previously commented line was the cause of
the missing preflight ACAO header).

Live results:

- HTTPS health remains `200 OK` with CSP/secure headers and no debug or
  `X-Powered-By` disclosure.
- HTTPS SPA preflight returns the exact ACAO/credentials headers. HTTP and
  unapproved origins receive no ACAO header.
- HTTP API requests return `307` to the HTTPS URL.
- `/api/v1/auth/me` now reaches the production application but returns `500`
  because the production database guard correctly rejects the current
  `root` user with blank password. No credential was fabricated and no guard
  was weakened.
- `php spark config:check Database` fails for that same intentional guard.
  `php spark phpini:check` reports `display_errors=0`, but
  `display_startup_errors=1`, timezone `Europe/Berlin`, and OPcache disabled;
  those remain host-level deployment blockers.

SYS-007 therefore remains **BLOCKED**: Operations must inject a non-default
secret-managed DSN with `DBDebug=false`, then rerun authenticated HTTPS auth
checks. UAT remains **NOT AUTHORIZED**.

## CI4 production DB configuration recheck (2026-10-09)

The deployment `.env` now supplies a non-root application DB identity and
explicit `database.default.DBDebug=false`. The previous CI4 `Database` guard
exception is resolved without weakening the guard or exposing the credential in
Git.

Read-only/runtime results:

- `php spark config:check Database`: PASS.
- `php spark migrate:status`: PASS; migration `000013` remains pending and no
  migration/write/reset was executed.
- `GET /api/v1/auth/me`: `401 UNAUTHENTICATED` with the secure session cookie,
  not `500`.
- `GET /logout`: `302` to the HTTPS login URL.
- `POST /api/v1/auth/logout`: `200` with the API envelope and HTTPS CORS.

The remaining SYS-007 blockers are unchanged: CSRF bootstrap decision,
production PHP host settings/retention decisions, reviewed SYSTEM_ADMIN
reconciliation, and independent SYS-005 legacy evidence.
