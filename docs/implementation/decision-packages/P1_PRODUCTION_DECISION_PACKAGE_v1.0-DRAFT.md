# P1 Production Decision Package v1.0-DRAFT

Status: **PROPOSED / NOT APPROVED**  
Date: 2026-10-09  
Scope: `P1-SYS-005`, `P1-SYS-007`  
Owners: Product Owner, Architecture, Security, Operations/Platform, API, Frontend, Release

This package records the proposed production values and the evidence required
for formal approval. It is not an approval record, does not change the pinned
architecture baselines, and does not authorize UAT or production mutation.

## Decision register

| ID | Proposed decision | Responsible approver(s) | Required evidence before approval | Status |
|---|---|---|---|---|
| `DEC-P1-SCOPE-001` | Release scope is greenfield installation only; legacy schema upgrade is excluded because no authoritative legacy baseline survived the approved reset. | Product Owner + Architecture + Release | Signed scope change linked to SYS-005; release notes and deployment runbook updated. | OPEN |
| `DEC-P1-TRANSPORT-001` | HTTPS-only production, preferably same-origin SPA/API behind a reverse proxy; explicit HTTPS CORS allowlist, verified TLS, explicit trusted proxies, secret-managed credentials, non-root DB user. | Security + Operations/Platform | Staging config output, TLS/CA verification, proxy header proof, rejected HTTP/unapproved-origin checks. | OPEN |
| `DEC-P1-CSRF-001` | Shield session auth; Secure/HttpOnly/SameSite=Lax session cookie; session CSRF with `X-CSRF-TOKEN`; pre-login bootstrap and login/logout rotation. Registration, magic-link, and remember-me disabled unless explicitly approved. | Security + API + Frontend | Authenticated positive and missing/invalid-token browser flows over HTTPS; login/logout lifecycle and mutation proof. | OPEN |
| `DEC-P1-IMPORT-001` | Product-only CSV/XLSX import, 10 MiB/file and 5,000 data rows. Backend enforcement and PHP upload setting validation. | Operations + Product | Boundary accept/reject tests at staging and stable error-envelope evidence. | OPEN |
| `DEC-P1-LOCK-001` | Domain lock timeout 5 seconds; automatic retries `0` by default and never more than one retry for an explicitly retry-safe operation. | Operations/Platform | Timeout/deadlock concurrency run, deterministic lock order, rollback and no-retry logs. | OPEN |
| `DEC-P1-TIME-001` | `Asia/Ho_Chi_Minh` is the proposed GROUP default; canonical Company Settings override it when configured; persistence remains UTC. | Operations + Product | Company/group setting matrix and boundary-date/effective-time staging evidence. | OPEN |
| `DEC-P1-RETENTION-001` | Propose 365 days for `authorization_logs` and import-job history. No destructive purge is enabled until Security/Operations approve disposal procedure. | Security + Operations | Retention owner, access controls, restore/expiry rehearsal, signed disposal procedure. | OPEN |
| `DEC-P1-SYSTEM-ADMIN-001` | Approved base snapshot 92 plus `user.reset_password` is exact intended 93. Apply a reviewed correction removing only `display_setting.view/edit` from `SYSTEM_ADMIN`. | Security + Operations + Architecture | Isolated `erp_test`/staging backup checksum, forward migration `000013`, exact identity-set assertion, rollback/review record. | OPEN |
| `DEC-P1-CONFIG-001` | Production fails closed for missing secrets, DB TLS, approved origins, and critical security settings. Repository contains redacted examples only. | Security + Operations/Platform | Staging boot/config checks, no secret leakage scan, PHP/runtime posture and log sink evidence. | OPEN |

## Code-owned implementation in this package

- `GET /api/v1/auth/csrf` bootstraps the session token; the frontend attaches
  `X-CSRF-TOKEN` to mutations and consumes the rotated response header.
- Production enables the session CSRF filter and token response filter while
  development/testing defaults remain unchanged.
- Import enforcement is centralized in `Config\\Import`: 10 MiB, 5,000 rows,
  CSV/XLSX only, PHP upload settings checked before reading the file, and stable
  `IMPORT_FILE_TOO_LARGE` / `IMPORT_ROW_LIMIT_EXCEEDED` errors.
- MariaDB domain locks keep a 5-second default and expose an explicit retry
  setting constrained to `0..1`; the existing synchronized path performs no
  automatic retry by default.
- Group-scoped business time falls back to `Asia/Ho_Chi_Minh` only when no
  canonical Company/Group setting is present; UTC persistence is unchanged.
- `systemadmin:reconcile` is a guarded, read-only-by-default maintenance path.
  It requires an auditable backup checksum, explicit approval, and an
  `erp_test` or explicitly named staging target before removing the two known
  extras. It cannot target development `erp` by default.
- Migration `2026-10-08-000013_AddUserResetPasswordAction` remains unchanged and
  forward-only. No purge job or retention deletion was added.

## Redacted deployment checklist

```dotenv
CI_ENVIRONMENT=production
app.baseURL=https://<approved-api-host>/
cors.allowedOrigins=https://<approved-spa-host>
VITE_API_BASE_URL=https://<approved-api-host>
database.default.username=<secret-managed-non-root-user>
database.default.password=<secret-manager-reference>
database.default.encrypt=<approved-db-tls-setting>
import.max_file_bytes=10485760
import.max_rows=5000
lock.timeout_seconds=5
lock.retry_count=0
security.csrfEnabled=true
business.timezone=<company-setting-or-group-default>
```

Placeholders are intentionally non-functional and must not be replaced with
real secrets or invented hosts in Git.

## Closure and release boundary

SYS-005 and SYS-007 remain **BLOCKED**. Closure requires signed decisions,
required staging evidence, exact SYSTEM_ADMIN reconciliation evidence, and the
independent SYS-005 scope-change approval or an authoritative populated legacy
baseline. UAT is **NOT READY / NOT AUTHORIZED** until both tasks are formally
closed and the release gate accepts the evidence.
