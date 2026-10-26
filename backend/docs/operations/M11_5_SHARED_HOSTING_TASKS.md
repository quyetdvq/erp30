# DinkERP M11.5 — CodeIgniter Tasks / Shared Hosting Cron (2026-10-26)

## Scope and safety

- Source baseline: `erp_v1.7.0.zip`; this patch only adds M11 scheduling and changes `backend/composer.json`.
- Prior M11.4A must remain applied on the operator's worktree. This patch does **not** replace its integration fixture.
- In-App/Push deliveries continue to run through the existing `notifications:*dispatch` Spark commands; M11 Outbox/Email/Push tables, consent, retries, leases, PBAC and delivery flags are unchanged.
- **All scheduled jobs are OFF by default.** M11.5B adds two PBAC-audited runtime switches on `/system/configuration-settings`; stored database values override the M11.5 legacy `.env` 1/0 values. Cron can run with zero registered jobs until an operator enables them.
- Push opt-in to cron is **independent** of the existing database Push SEND switch. Do not turn it on without authorizing real Push delivery.
- **Email is never registered** in M11.5. SMTP is missing in the recorded audit; four existing email receipts remain PENDING. Do not drain, requeue, purge or mutate them merely to pass audit.
- No automated DB migrations, DDL, HTTP endpoint, Queue job insertion, Queue worker or live channel sends are performed by patch application.

### Important Composer dependency

Official `codeigniter4/tasks:^1.0.1` **requires** `codeigniter4/queue:^1.0` transitively. Installing Tasks will also install the Queue library, but DinkERP **does not adopt Queue's handlers, migrations, tables or workers** for M11. Continue using MariaDB 10.4-compatible custom M11 queues. The existing `codeigniter4/settings:^2.4` requirement is compatible.

**Composer lock note:** The baseline ZIP contains `composer.lock` but this patch does not modify it. Dependencies cannot be resolved in the patch build environment (no Composer/network access). Resolve and commit the *Composer-generated* lockfile on XAMPP **before deployment**; do not ship a stale lockfile or hand-edit it.

## 1. Apply patch + install (local XAMPP)

Run in `D:\XAMPP\htdocs\erp` (the repository root):

```powershell
# This checks code only; it does not touch DB.
git apply --check .\ERP_M11_5_Tasks_Shared_Hosting_Cron.patch
git apply .\ERP_M11_5_Tasks_Shared_Hosting_Cron.patch
git diff --check

cd .\backend
# Must be Composer 2, running under supported PHP CLI >= 8.2.
composer --version
php -v
# Resolve codeigniter4/tasks 1.0.x and its Queue dependency to composer.lock.
# No scripts / migrations are run automatically.
composer update codeigniter4/tasks codeigniter4/queue --with-all-dependencies --no-scripts
composer validate --strict
composer show codeigniter4/tasks
composer show codeigniter4/queue
php spark tasks:list
```

If Composer reports a conflict, **STOP** and retain the error; do not loosen minimum-stability globally and do not run `composer update` without a package allowlist. Preserve the generated `backend/composer.lock` in source control for hosting deployment. Never upload `.env` to public web roots.

**Settings migration preflight:** Tasks uses the already-required CodeIgniter Settings library. Inspect `php spark migrate:status -n 'CodeIgniter\Settings'` and the existing settings tables/ledger. If the Settings migration is missing, get explicit approval and a backup before running the namespace-scoped `php spark migrate -n 'CodeIgniter\Settings'`; **never** use `migrate --all`, `migrate:refresh`, or automatic migration on the production database. Use `erp_test` for any destructive testing, never `erp`.

Do **not** run `php spark tasks:publish`: this patch already includes a reviewed `app/Config/Tasks.php`; publishing may overwrite it.

## 2. Stage safely (default OFF; managed on frontend from M11.5B)

If no database setting exists yet, the legacy values in private `backend/.env` (or equivalent protected host environment) remain the fallback:

```ini
DINKERP_M11_TASKS_INAPP_ENABLED = 0
DINKERP_M11_TASKS_PUSH_ENABLED = 0
```

Confirm that `php spark tasks:list` does not contain either `m11-*` job. This is the default fail-closed state. M11 runtime delivery flags in `configuration-settings` are **separate**, and `php spark notifications:ops-audit --strict` should continue passing with Email Queue/Send OFF.

Preferred from M11.5B: in `/system/configuration-settings`, save **In-App Tasks ON / Push Tasks OFF** using an account authorized for `notification_setting.edit`. This persists flags to existing Settings storage, increments revision and writes an audit event. No `.env` edit is needed. Only when migrating an old deployment without database values, you can still enable In-App by setting:

```ini
DINKERP_M11_TASKS_INAPP_ENABLED = 1
DINKERP_M11_TASKS_PUSH_ENABLED = 0
```

Each new CLI process reads the latest database flags, falling back to these legacy 1/0 `.env` values only if the setting is not persisted. Verify:

```powershell
php spark tasks:list
php spark notifications:ops-audit --strict
php spark notifications:inspect
# WARNING: the next command ACTUALLY executes any due scheduler jobs.
# Run it only with authorized production/DEV queues, preferably when idle.
php spark tasks:run
php spark notifications:inspect
```

Expect **one named In-App task** on `tasks:list` and no Push/Email task. A dispatcher result with `claimed=0` is normal if there is no due Outbox work. Check the `Processing`, `Executed` or `Failed` lines and that it does not create unexpected queue items. **Important:** Tasks v1.0.1 catches individual task exceptions; `tasks:run` may exit with code 0 even when a task printed `Failed`. A zero process exit alone is NOT proof of successful notification dispatch. Inspect CodeIgniter error logs and M11 queue metrics. For an end-to-end acceptance test use only a controlled, consented notification after scheduling is proven stable.

Do not enable Push scheduling until Push operations are explicitly authorized. When ready, explicitly enable `DINKERP_M11_TASKS_PUSH_ENABLED` on `/system/configuration-settings` (or use the legacy `.env` 1 only before database persistence), confirm `notifications:ops-audit --strict` and the active Push subscription; a subsequent scheduled run can send real browser Push notifications. Push uses `--batch=5` to keep worst-case five 12-second HTTP requests well under the existing M11 worker's 120-second lease. File-cache `singleInstance(180)` is a coarse overlap guard; existing DB leases remain authoritative.

Email remains out of scope until SMTP acceptance, review of four overdue PENDING receipts, and an explicit follow-up change.

## 3. Test gates (erp_test only for DB-writing tests)

```powershell
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
php spark tasks:list
php spark notifications:ops-audit --strict
php spark notifications:inspect
```

Expected with M11.4A + M11.5B: **124 unit**, **46 integration**, **7 reliability** tests, no failures (baseline before M11.5B: 122/44/7). Also run the M11.5B frontend Vitest test, OpenAPI regeneration and the frontend type-check. Unit count excludes any additional tests not covered by the frozen baseline. `RUN M11-RELIABILITY` uses committed, short-lived DML fixtures **only** on guarded `erp_test`; never run it alongside other worker/tests or on a shared test DB.

## 4. Shared Hosting (cPanel Cron Jobs)

Requirements: PHP **CLI** 8.2+, correct extensions, Composer-installed `vendor/` matching the generated lockfile, app files and `backend/writable/` outside the publicly served directory, readable private `.env`, configured MariaDB, and Cron Jobs support. Confirm host min cron interval; Tasks evaluates `everyMinute()` jobs each time `tasks:run` is invoked, even if host cron executes only every five minutes (actual delivery frequency is then five minutes).

Create **one** Cron Job in cPanel, at the fastest supported interval (typically each minute; otherwise every five minutes):

```cron
* * * * * /bin/sh /home/ACCOUNT/erp/backend/tools/m115_tasks_cron.sh
```

The wrapper moves into `backend` and appends Tasks runner status (`Processing`, `Executed`, `Failed`) to a daily log under `backend/writable/logs/`. Because `$logPerformance=false`, the buffered output from the underlying M11 Spark command is not stored as Tasks performance history; use `notifications:inspect` and `notifications:ops-audit` for queue counters. Configure routine retention of old `m11-tasks-YYYY-MM-DD.log` files; no automated deletion is included. If `php` on the Cron PATH is not the right PHP CLI binary, use a verified absolute binary:

```cron
* * * * * PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php /bin/sh /home/ACCOUNT/erp/backend/tools/m115_tasks_cron.sh
```

Replace the placeholder paths after verifying them on the actual host. Cron executes **CLI**, not a public URL. Do not expose `spark`, app sources, logs or any "cron secret" as a web endpoint. Do not deploy a second independent Cron/Windows Task Scheduler invoking the same channel.

Check daily Task statuses (especially `Failed`), PHP/CI error logs, cPanel Cron history and `php spark notifications:ops-audit --strict`. For `singleInstance()` to prevent overlap, the file cache (`backend/writable/cache/`) must be writable and shared by invocations; DB leases and idempotency are still the final defense. A cache-lock TTL is a *maximum*, not a guarantee about shared-hosting process limits. Validate host max execution time and memory limits with the actual hosting provider.

## 5. Rollback

1. Set both Tasks switches to OFF in `/system/configuration-settings` (stored database settings take precedence); stop/remove the **single** Cron Job. If no DB rows were ever saved, you may instead set both legacy `.env` flags to 0.
2. Verify `php spark tasks:list` has no M11 tasks and `php spark notifications:ops-audit --strict` still passes. Audit may warn about not-yet-persisted Tasks flags before first Save All; this does not prove cron is running.
3. Keep M11 DB data unchanged. For source rollback, revert this isolated patch / Composer lock changes in a controlled deployment; do not uninstall Settings or Queue while other code may depend on them.

## Evidence required before M11 operational closeout

- `composer validate --strict` passes after lock is generated; `composer show` confirms Tasks and Queue dependency versions.
- `tasks:list` shows only authorized jobs; no Email job; `tasks:run` proves valid execution without cron duplication.
- Automatic Cron status and last-run output visible in daily logs under real shared-hosting PHP CLI; no `Failed` entries; plus at least one *controlled* end-to-end In-App notification processed. Exit code 0 alone is insufficient.
- All M11 tests pass, `notifications:ops-audit --strict` passes, there are no expired leases, and four Email PENDING are recorded as a deferred operational item.
- Push real-delivery acceptance is separate from SENT/provider acceptance; Scheduler enabling it requires explicit authorization.

## M11.5B – Frontend scheduling controls (added after original M11.5)

`/system/configuration-settings` exposes these two switches in the **Automated scheduling (Tasks / Cron)** section:

- `DINKERP_M11_TASKS_INAPP_ENABLED`: register `m11-inapp-dispatch` every time the cron runner evaluates the schedule (minute resolution).
- `DINKERP_M11_TASKS_PUSH_ENABLED`: register `m11-push-dispatch`; enabling is separately confirmed because it can send real Push if Send and consent are enabled.

The API reuses the established `GET/PATCH /api/v1/configuration-settings/notifications` with `notification_setting.view/edit` PBAC, a single revision CAS, strict boolean validation, and `NOTIFICATION_RUNTIME` audit events. It has **11** non-secret switches total; existing 9 are unaffected. Legacy `.env` uses strictly 1/0 for these two (unlike nine legacy `enabled/off` flags). A saved database row wins on the next `php spark tasks:list` or `tasks:run`, even if `.env` disagrees. Bad stored rows/DB outage fail closed for scheduler flags.

**A UI switch does not install a Cron Job**. A shared-hosting operator must separately configure the one CLI cron wrapper, verify `Processing/Executed` (no `Failed`), and confirm scheduler/queue metrics. No Email scheduling is introduced. Do not enable Push during tests with real subscribed devices without delivery authorization.
