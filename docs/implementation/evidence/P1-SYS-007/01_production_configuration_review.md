# P1-SYS-007 - Production Configuration Review

Date: 2026-10-09
Task: `P1-SYS-007`
Outcome: **BLOCKED**

## Preflight

- `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-SYS-007`
  returned `AUTHORITY_CACHE: VALID`; catalog and ledger validators passed.
- HEAD: `d6243f02c1fdcf7816cca0221133c4e057c8a4b6` (`d6243f0` prefix), branch
  `main`.
- Live ledger: `103/107 DONE`; Gates 0-8 are complete and Gate 7A is closed.
  `P1-SYS-005` remains `BLOCKED`; `P1-SYS-007` is now recorded as `BLOCKED`.
- The pre-existing Pricing edits, SYS-005 ledger edit, and SYS-005 evidence
  directory were preserved. Only SYS-007 was narrowed to `BLOCKED`; SYS-005
  status and evidence were not changed.
- Repository `AGENTS.md` was read and its authority, forward-only migration,
  isolated-test, authorization, and task-commit rules were applied.
- Authority read: Implementation Contract v1.10 §§3, 9, 19-21; FRS v1.12
  §22; Implementation Plan v1.10 §17; `P1_DECISIONS.md`; approved change
  controls 07-11 as applicable.

## Remediation delta

The standalone Spark correction was retained and independently validated.
Production-only configuration hardening is now implemented without changing
development/testing defaults or the approved SPA/API contract:

- `backend/spark` defines `SUPPORTPATH` before `Boot::bootSpark()` when the
  test support directory exists.
- Production requires an HTTPS `app.baseURL`, enables global secure requests,
  secure cookies, secure response headers, CSP insecure-request upgrades, and
  0600 application log files.
- Shield registration, magic-link login, and remember-me are disabled by
  default in production and require explicit deployment opt-in values.
- Production CORS origins are deployment-owned (`cors.allowedOrigins`); an
  unset value denies cross-origin access rather than allowing HTTP/localhost
  origins.
- Production database construction fails closed for blank/root credentials or
  `DBDebug=true`; exception traces redact credential/token/header names.
- The frontend keeps configured `VITE_API_BASE_URL`; a missing production
  value falls back to the current HTTPS origin rather than an HTTP API host.

Global CSRF remains **BLOCKED / decision required**. The current SPA has no
CSRF token bootstrap or request interceptor. Enabling the global filter now
would reject login and mutation requests, so no silent token contract was
invented. Security must approve the token transport and bootstrap endpoint
before CSRF can be enabled and verified end-to-end.

## Bootstrap correction

The independent probe reproduced the known CI4 defect:

```text
$env:CI_ENVIRONMENT='testing'; php spark migrate:status
Undefined constant "CodeIgniter\\Config\\SUPPORTPATH"
```

`backend/spark` did not define `SUPPORTPATH` before `Boot::bootSpark()`, while
CI4's testing autoloader requires it. The entrypoint now defines the path when
`tests/_support` exists, matching the framework test bootstrap. The same
standalone command then passed and listed the database migration status. No
migration, seed, refresh, or reset command was run.

## Configuration matrix

| Area | Evidence | Disposition |
|---|---|---|
| Pagination | `CommonListQueryAdapter`: default 50, maximum 200; allowlists and deterministic `id ASC` tie-break | PASS |
| Locale | `App`: supported `vi,en`, default `vi`, negotiation enabled; API handler also constrains to `vi,en` | PASS |
| Business time | Evaluation clock normalizes UTC; Company/Group Settings resolver exists; production Settings values were not supplied | NOT VERIFIED |
| Import limits | Contract requires `import.max_file_bytes` and `import.max_rows`; no runtime/Operations values or enforcement were found | BLOCKED: Operations decision |
| Lock/retry | Domain lock defaults to 5 seconds via `DINKERP_DOMAIN_LOCK_TIMEOUT`; no `lock.retry_count` deployment value or bounded retry configuration was found | BLOCKED: Operations decision |
| Retention | FRS/Contract leave `authorization_logs` and `import_jobs` retention open; no purge may be enabled | BLOCKED: Security/Operations decision |
| Errors/logging | Production boot disables display errors; API errors use stable envelopes; exception trace fields are redacted and production file logs are 0600 | PASS for code; deployment log sink/rotation still NOT VERIFIED |
| Database | Production now fails closed for blank/root credentials or `DBDebug=true`; local `.env` still selects `erp` with root/blank password and production DSN/TLS evidence is unavailable | BLOCKED: deployment evidence |
| Session/auth | Shield remains the identity authority; production defaults disable registration, magic links, remember-me, and secure cookies are enabled. Global CSRF is intentionally not enabled pending SPA token decision | BLOCKED: Security decision |
| Transport/CSP | Production requires HTTPS base URL, enables ForceHTTPS/secure headers/CSP upgrade, and reads only explicit deployment CORS origins; unset CORS denies cross-origin access | BLOCKED: deployment origin and CSP compatibility evidence |
| Build/deploy | Frontend production build passed; Vite output has a 664.51 kB `naive-ui` chunk warning. No deployment manifest or production env snapshot is present | NOT VERIFIED |

## Required production decisions and values

Security/Operations must provide and approve these values before release; this
task intentionally leaves them unset:

- `import.max_file_bytes` and `import.max_rows` (Product import only);
- `lock.timeout_seconds` and bounded `lock.retry_count` (retry at most once and
  only for retry-safe/idempotent operations);
- Company business timezone values, plus Group/default timezone where a
  group-scoped reset policy needs one; all persistence remains UTC;
- retention duration and purge ownership for `authorization_logs` and
  `import_jobs`;
- production HTTPS `app.baseURL`, exact HTTPS SPA CORS origins,
  `VITE_API_BASE_URL`, non-default DB credentials, DB TLS/transport settings,
  log sink/rotation, and the CSRF token bootstrap/header contract.

No value above is inferred from local development configuration.

## Finding classification

**Code-owned and fixed:** standalone Spark bootstrap path; production secure
transport/cookie/header defaults; Shield risky-feature opt-ins; deployment-only
CORS allowlist enforcement; DB diagnostic fail-closed checks; exception-trace
redaction; production log-file permissions; secure frontend API fallback.

**Deployment-owned and not verifiable here:** production HTTPS host and reverse
proxy trust, exact SPA/API origins, secrets/DSN/TLS, PHP `display_errors`/
timezone/OPcache posture, log sink/rotation, and the runtime values listed
above. No credentials or environment values were fabricated.

**Authority/decision blockers:** CSRF token/bootstrap contract, import and lock
limits/retry values, business timezone values, audit/import retention, and
Security/Operations approval for the reviewed SYSTEM_ADMIN reconciliation.

## Migration comparison (read-only)

The current database query was read-only:

- `erp`: 16 rows, through application migration `2026-10-08-000012`.
- `erp_test`: 17 canonical rows, through `2026-10-08-000013`, plus the
  PHPUnit-only `Tests\\Support\\Database\\Migrations\\ExampleMigration`
  (18 physical rows currently visible).

The 16/17 application discrepancy is the pending `AddUserResetPasswordAction`
migration in development. `erp` was not migrated and `erp_test` was not reset.
The extra test migration is test bootstrap residue, not production history.

## SYSTEM_ADMIN reconciliation

The approved base snapshot is 92 actions. `user.reset_password` is the
explicitly approved additive action from `CHANGE-USER-IDENTITY-MUTATION-001`,
so the intended privileged matrix is 93. `display_setting.view/edit` are
approved catalog actions from the Display Settings change control but are
explicitly outside the privileged matrix.

Read-only `erp` evidence:

- `SYSTEM_ADMIN`: 94 group-permission rows.
- Present extras: `display_setting.view`, `display_setting.edit`.
- `user.reset_password` is absent because migration `000013` is pending.

This is an existing overgrant and migration drift, not evidence that the
current seeder should auto-grant future actions. No database correction was
performed. Production deployment needs an explicit, reviewed reconciliation
after the approved migration path is resolved.

### Reversible correction procedure (review only; NOT RUN)

1. On an isolated maintenance connection, export the `SYSTEM_ADMIN` group id,
   all current permission rows, and catalog action ids to an auditable rollback
   artifact. Abort unless the group code and catalog rows are unique.
2. Apply migration `2026-10-08-000013_AddUserResetPasswordAction` through the
   normal forward-only deployment path. Do not edit the migration or insert a
   manual migration-ledger row.
3. In one transaction, assert that the post-migration set contains exactly the
   93 approved actions (`PrivilegedAuthorizationGroupSeeder::approvedActions()`)
   and no unapproved rows. Delete only the two known
   `display_setting.view/edit` rows if explicit review approves their removal;
   otherwise abort and retain the rollback artifact.
4. Re-read the set, verify no direct-user permissions changed, commit only
   after the exact 93-row assertion, and retain the pre-state artifact for
   rollback. Never run this procedure against development `erp` in this task
   or against production without Security/Operations approval.

The current seeder remains additive and deliberately preserves
future/unrelated permissions; changing it to silently delete rows would violate
its existing approved idempotency behavior. The procedure therefore requires an
explicit reviewed correction instead of an auto-grant or auto-revoke.

## Validation

| Command | Result |
|---|---|
| `task-context.php P1-SYS-007` | PASS; `AUTHORITY_CACHE: VALID` |
| `architecture-guard.php`, cache and ledger validators | PASS; 47 files, 107 tasks, 103 DONE |
| Standalone testing-mode `php spark migrate:status` after fix | PASS; no SUPPORTPATH fatal |
| `php tools/test-db-harness.php inventory` | PASS; target exactly `erp_test`; 18 rows observed |
| Read-only MySQL migration/permission queries (`erp`, `erp_test`) | PASS; `erp` App=12 through `000012`, `erp_test` App=13 through `000013`, `erp_test` physical=18 including one PHPUnit `ExampleMigration`; `SYSTEM_ADMIN`=94 with only `display_setting.edit/view` extras and no `user.reset_password` |
| `vendor/bin/phpunit tests/unit/Gate0InfrastructureTest.php --no-coverage` | PASS; 5 tests / 12 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS; 440 tests / 3907 assertions |
| `vendor/bin/phpstan analyse --level=8 --no-progress` | PASS |
| Frontend `vue-tsc --build`, Oxlint, ESLint, Prettier check, production build | PASS |
| Frontend `pnpm run test:unit -- --run` | PASS; 40 tests / 15 files |
| Frontend OpenAPI lint and generated-client check | PASS |
| Production `php spark phpini:check` | PASS command; host reports `display_errors=1`, `date.timezone=Europe/Berlin`, OPcache disabled |
| Standalone `php spark list`, testing `php spark migrate:status`, `config:check`, and `filter:check` | PASS; HTTPS runtime flags, exact HTTPS CORS origin, secure cookie, CSP, Shield opt-outs, and production-like filters resolve without bootstrap errors; `CI_ENVIRONMENT` remains development pending non-default DSN |
| `git diff --check` and `php -l backend/spark` | PASS |
| PHP lint for changed production config classes | PASS |
| Focused PHPUnit after remediation | PASS; 22 tests / 132 assertions |
| PHPStan level 8 after remediation | PASS |
| Frontend `vue-tsc`, Oxlint, ESLint, Prettier after remediation | PASS |
| `validate-context-cache.php` | PASS; 12 authorities / 107 tasks / 47 rules |
| Live HTTPS recheck (`api.monmin.net` / `erp.monmin.net`) | PASS for HTTPS redirect, secure cookie, CSP/secure headers, exact HTTPS credentialed CORS and rejection of unapproved origins, no Debugbar, and no `X-Powered-By`; `/auth/me` is blocked by the intentional non-default-DSN guard |
| Production DSN/PHP host/retention evidence | PASS for the configured non-root DSN and `DBDebug=false`; PHP `display_startup_errors`/UTC/OPcache and retention values remain deployment evidence blockers |
| Global CSRF SPA regression | BLOCKED; token bootstrap contract is absent |

## Readiness and UAT

UAT disposition: **NOT READY / BLOCKED**. UAT must not start. Production
environment evidence, Security/Operations limits/retention/lock/timezone
decisions, explicit SYSTEM_ADMIN reconciliation, a CSRF token decision and SPA
regression proof, and the independent existing-schema forward rehearsal blocked
by `P1-SYS-005` must close first.

No completion commit was created because mandatory release checks did not pass.
Any remediation-only commit preserves the `BLOCKED` ledger disposition and is
not a release or UAT approval.
