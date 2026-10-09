# P1-SYS-007 - Approved Parameter Implementation and Recheck

Date: 2026-10-09  
Task: `P1-SYS-007`  
Approval input: user-approved decision table in the current task

## Current-host recheck

The current API host is `https://api.monmin.net`; `erp.monmin.net` is the Vite
SPA proxy and is not an API host. The following checks were run after a
controlled Apache restart (new workers, `httpd -t = Syntax OK`; unrelated
missing-vhost warnings are unchanged):

| Check | Evidence | Status |
|---|---|---|
| PHP startup/errors | Runtime probe returned `display_errors=""` and `display_startup_errors=""`. | PASS |
| PHP timezone | Runtime probe returned `date_timezone=UTC`; health timestamp is `+00:00`. | PASS |
| OPcache | Runtime probe returned `opcache_enable=1`, `opcache_enable_cli=0`, `opcache_validate_timestamps=1`. | PASS |
| TLS/HTTPS | `https://api.monmin.net/api/v1/health` returned `200`; HTTP health redirects to HTTPS; Apache serves OpenSSL 3.1.3. | PASS |
| Secure transport headers | Health response includes secure session cookie (`Secure; HttpOnly; SameSite=Lax`), CSP, and no `X-Powered-By`/Debugbar disclosure. | PASS |
| CORS | Credentialed preflight accepts the approved HTTPS SPA origins; HTTP origin is rejected, and CSRF `403` responses now retain the matching ACAO header after global CORS ordering fix. | PASS |
| CSRF bootstrap | `GET /api/v1/auth/csrf` returned `200`, JSON token metadata, and `X-CSRF-TOKEN`; token values are intentionally not recorded. | CODE/LIVE BOOTSTRAP PASS |

## Approved parameters

| Parameter | Implementation / evidence | Status |
|---|---|---|
| Product Import | `Config\\Import` enforces 10 MiB (`10485760`) and 5,000 rows; exact-boundary and overflow tests pass. | PASS |
| Lock/retry | `MariaDbDomainLockService` uses a 5-second timeout; approved retry default is `0`; regression test passes. | PASS |
| Business timezone | GROUP fallback is `Asia/Ho_Chi_Minh`; Company context reads canonical `App.businessTimezone`; application/database persistence remains UTC. | PASS |
| Retention | User explicitly approved the 365-day decision and marked this item PASS; purge remains intentionally disabled. | PASS BY APPROVED DECISION |
| CSRF | `/api/v1/auth/csrf`, rotated `X-CSRF-TOKEN`, mutation interceptor, and logout/login refresh are implemented. Mocked lifecycle test passes. | CODE PASS; AUTHENTICATED JOURNEY OPEN |
| SYSTEM_ADMIN | Current host has migration `000013`, one `SYSTEM_ADMIN` group, `user.reset_password`, and exact 93-action set. Guarded dry-run reports `pre-state: 93; extras=`. | PASS |

The reconciliation command uses the `tests` connection by default and only
allows an explicitly named `staging` target for non-test maintenance. The
current-host check used the approved backup
`D:\\XAMPP\\temp\\p1-sys-007-before-final.sql` with SHA-256
`55145F08A04D8AC1F558B7FE846993C3AE8480AFA5D8259756379B112F8D19B3` and
performed no additional write because the target already had the exact set.

## Validation run

| Command / journey | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-SYS-007` | PASS; `AUTHORITY_CACHE: VALID`; 104/107 ledger DONE |
| `vendor/bin/phpunit --no-coverage` | PASS; 445 tests / 3,923 assertions |
| `vendor/bin/phpstan analyse --level=8 --no-progress` | PASS |
| Frontend `pnpm run type-check` / `lint:oxlint` / `lint:eslint` / `format:check` | PASS |
| Frontend `pnpm run test:unit -- --run` | PASS; 16 files / 44 tests |
| Frontend `pnpm run build-only` | PASS; production bundle built (existing `naive-ui` chunk warning only) |
| `php spark migrate:status` (`CI_ENVIRONMENT=production`) | PASS; App migration `000013` applied to current database |
| `php spark systemadmin:reconcile ... --approved` (dry-run, explicit staging target) | PASS; exact 93-action pre-state, no extras |
| `httpd -t -f D:\\XAMPP\\apache\\conf\\httpd.conf` | PASS; `Syntax OK` (unrelated missing-vhost warnings) |
| Runtime PHP probe after Apache restart | PASS; values recorded above; probe removed immediately after check |
| Live CSRF negative checks over HTTPS | PASS; missing token and invalid token return `403`; valid token with deliberately nonexistent credentials reaches `401 INVALID_CREDENTIALS` |
| Targeted Chromium CSRF lifecycle test | PASS; 1 test / 1 assertion (mocked transport) |
| Real authenticated HTTPS browser journey (`erp2.monmin.net` -> `api.monmin.net`) | PASS; supplied account login and `/auth/me` returned `200`; missing/invalid CSRF returned `403`; same-value profile mutation returned `200`; logout/relogin/final logout returned `200`; CSRF rotated after logout and relogin. |

## Release disposition

`P1-SYS-007` is **DONE**. The approved parameters, current-host runtime,
retention decision, exact SYSTEM_ADMIN reconciliation, CORS/CSRF behavior, and
real authenticated browser journey all passed. No credentials were recorded,
no CSRF check was bypassed, and no unrelated worktree change was staged.

Next release dependencies remain `P1-UAT-001` and `P1-RC-001`; this task does
not mark either downstream task complete.

**Outcome: DONE / SYS-007 evidence complete.**
