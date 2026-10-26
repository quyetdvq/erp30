# M11.2 — PHPUnit Workflow / Notifications

The M11 PHPUnit suites use the **existing CodeIgniter `database.tests` connection**.
No `M11_TEST_*` variables, temporary accounts or separate `_m11_test` database are needed.
Use the PowerShell runner already in `backend/tools/test-unit.ps1`.

## Database configuration (existing, no new credentials)

The `backend/.env` test group must resolve to the locally configured **`erp_test`**
database, user **`erp_test_runner`**, `127.0.0.1:3306`, MySQLi and prefix `dinktech_`.
The password stays in `.env` only: do not copy credentials into Git patches, scripts,
logs or documentation. The guard rejects other database names (including `erp`),
remote hosts, root users, DSN overrides and persistent connections *before connecting*.
After connecting, it verifies the database name, the authenticated MariaDB account,
and required tables are InnoDB.

Canonical `GROUP` root: M11 uses the same fixture rule as
`backend_baseline/tests/_support/Database/CanonicalGroupRootFixture.php`.
When `erp_test` already contains one root (`type=GROUP`, `parent_id IS NULL`),
the test reuses it unchanged; when none exists, it inserts one inside its
rollback transaction. If there are multiple roots, the suite fails closed.
Do not reseed or delete an existing root just to run M11 tests.

The existing `erp_test` must have the complete ERP schema (Migrations 001–020,
Shield and Notifications). A schema-only database is **not** required; any existing
business/test rows are left untouched. Fixture assertions are scoped to rows created
by their individual test and all mutations use an outer transaction that rolls back.
Because the Worker claims globally, the safety guard rejects an `erp_test` Outbox
with any `PENDING`, `RETRY`, or `LEASED` event; historical completed rows are fine.

Use a restricted database account with SELECT/INSERT/UPDATE/DELETE privileges on
`erp_test` only, not on the application database. **Regular `RUN` never performs
schema migrations or DDL.** Always run the integration tests serially. Never
run against a real user database.

## One-time test-schema preparation (only if 019/020 are missing)

`M11-INTEGRATION` validates the schema before creating fixtures. If it reports
`Missing InnoDB M11 fixture table: dinktech_workflow_notification_assignment_sets`,
then **the isolated `erp_test` database is missing at least Migration 020**.
Migration 020 being present on `erp` does not make it present on `erp_test`.

Run this separate, explicitly opt-in command **once** (not during every test):

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 PREPARE M11
.\tools\test-unit.ps1 RUN M11
```

`PREPARE M11` uses `tools/m11-test-spark.php` to set both PHP environment
superglobals to `testing` and define `SUPPORTPATH` **before** CodeIgniter's
Spark autoloader starts. This avoids the CI4 4.7.x bootstrap mismatch when
`backend/.env` defines a different `CI_ENVIRONMENT`. `CHECK M11` verifies this
isolated bootstrap without touching MariaDB. Normal `php spark` commands remain
unchanged.

`PREPARE M11` checks the actual
MariaDB database and account before any DDL. It checks the 18 historical App
migration entries in the `tests` ledger, rejects partial/unrecorded M11 tables,
and applies **only the missing Migrations 019/020** to `erp_test`.
Already-prepared schemas are left unchanged. It never refreshes, drops, or
recreates an existing database, and never touches `erp`.

**Privileges:** The schema-preparation step uses the same configured
`erp_test_runner` login and therefore requires temporary, appropriately scoped
DDL privileges on `erp_test` if the tables are missing. If that account is
DML-only, an administrator must authorize the one-time test-schema migration
on `erp_test` (or provision its schema through an authorized DBA workflow).
After schema setup, revoke temporary DDL grants so routine PHPUnit remains
DML-only. Never give the test runner DDL privileges on `erp` or change
`database.tests` to point to the application database.

MariaDB `CREATE TABLE` is not transactional; if migration fails in the middle,
inspect table existence and the `dinktech_migrations` ledger before any retry.
Do not run `migrate:refresh`, `migrate:rollback`, or manually insert ledger rows.
If the 18 inherited migrations or Shield tables are missing, `PREPARE M11`
intentionally stops: provision/verify the base schema first.

## Commands (PowerShell, from backend/)

```powershell
cd D:\XAMPP\htdocs\erp\backend

.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 PREPARE M11     # only once, if test schema is missing 019/020
.\tools\test-unit.ps1 RUN M11-UNIT
.\tools\test-unit.ps1 RUN M11-INTEGRATION
.\tools\test-unit.ps1 RUN M11
```

`RUN M11` runs unit + MariaDB integration tests and fails if either suite fails.
You can also invoke `php vendor\bin\phpunit -c phpunit.m11.xml --testdox` directly for the rollback-only M11 suites.
If `vendor/bin/phpunit` is absent, run Composer with dev dependencies first.
These M11 modes intentionally **do not run old baseline rewrite/fingerprint gates**.

## Coverage and limits

The suite exercises genuine `BusinessWorkflowService`, assignment CAS, Outbox producer,
`NotificationDispatcher`, multi-recipient Inbox, retry/idempotency, PBAC changes,
inactive recipients, stale Workflow and policy suppression. Every case uses fresh
fixture IDs. The outer transaction rolls back even after an assertion failure.

It does not test two-process MariaDB concurrency, abrupt process termination,
HTTP session/CSRF or browser rendering. Those need separate tests.

If an integration test fails, retain the failing test name and exception: do not
substitute a pure/mock pass. Never point `database.tests` to the ERP application DB.

## M11.3A: independent-worker reliability gate (explicit opt-in)

The original M11.3 roadmap describes **Email/Push channels**. M11.3A is a separate
**reliability gate for the already deployed in-app worker** before those
channels are developed. It is not an Email/Push implementation.

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK M11-RELIABILITY
.\tools\test-unit.ps1 RUN M11       # existing rollback-only regression suites
.\tools\test-unit.ps1 RUN M11-RELIABILITY
```

The reliability tests have their **own `phpunit.m11.reliability.xml`**, so a bare
`phpunit -c phpunit.m11.xml` never unexpectedly commits fixture data.

**Distinct fixture lifecycle:** M11.2's normal `RUN M11` still uses outer
transaction rollback. `RUN M11-RELIABILITY` must commit its temporary rows
because two independent PHP processes cannot see uncommitted rows. It uses
`database.tests=erp_test` with the exact same strict safety guard, obtains a
named MariaDB advisory lock, rejects a non-idle Outbox before setup, creates
unique IDs/event keys, and deletes **only its own** Inbox/Outbox/Import/User/
policy rows in `tearDown()`. It has **no DDL**, no new DB credentials and no
production DB access. Run only serially and do not run any other M11 tests or
Worker against `erp_test` concurrently. A force-killed PHP process may leave
an isolated M11R row: inspect and reconcile it manually, not via bulk DELETE.

Coverage:

- Two actual OS processes, two MariaDB connections, one committed event:
  InnoDB `FOR UPDATE` contention, exactly one claim/terminal processing.
- Recovery of an expired lease; old token cannot update terminal event.
- Six-attempt expired-lease reaper marks DEAD without a seventh attempt.
- Delivery flag OFF preserves the queued event and attempt counter.
- Testing-only fault thrown **after Inbox INSERT but before ACK** causes full
  rollback and RETRY with database-clock backoff; retry produces exactly one
  Inbox and processing ACK.
- Permanent post-INSERT failure rolls back Inbox and sets DEAD with code.
- Pure bounded delay schedule (1/5/15/60/240 minutes; DEAD at 6 attempts).

The dispatcher test-injection callback is rejected outside
`ENVIRONMENT=testing` and cannot be supplied through HTTP, configuration
or environment switches. It never runs in production without being explicitly
constructed by test code.

This gate deliberately does **not** claim full coverage of abrupt SIGKILL
at every instruction, multiple concurrent batches of 100, HTTP/CSRF,
Email/Push or browser rendering. Those remain separate acceptance work.

## M11.3B Email channel

Before running M11 suites against `erp_test`, run `./tools/test-unit.ps1 PREPARE M11` to apply **Migration 021** after 019/020. This creates `dinktech_notification_email_deliveries` only in the guarded test DB. The standard M11 PHPUnit suites now also require that InnoDB table. `RUN M11` uses only fake Email senders, transaction rollback and never contacts SMTP. The legacy M11.3A reliability suite remains opt-in as `RUN M11-RELIABILITY` and only writes committed temporary fixtures to the guarded test DB.

Email pilot rollout flags and CLI are documented in `docs/Notifications/ERP_M11_3B_EMAIL_CHANNEL_ADR_2026-10-26.md`. Keep `DINKERP_M11_EMAIL_SEND_ENABLED=off` until after PHPUnit acceptance and explicit SMTP pilot approval. The new command is `php spark notifications:email-dispatch --once --batch=10`; do not run it from `CI_ENVIRONMENT=testing` with a real sender.

## M11.3C Browser Push

Migration 022 adds two tables to `erp_test` using `PREPARE M11` (separate DDL action).
Push Unit and MariaDB Integration cases are included in `RUN M11` and use a **fake Push provider**;
no network delivery is performed. Push test cases check explicit current-epoch consent, SSRF-safe
endpoint validation, AES-256-GCM at-rest storage, VAPID ES256 signing, registration/revocation,
account reassignment, queue gating, retry, disabled sending and 404/410 cancellation.

Run in this order on XAMPP:

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11-UNIT
.\tools\test-unit.ps1 PREPARE M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
```

See `docs/Notifications/ERP_M11_3C_BROWSER_PUSH_ADR_2026-10-26.md` for the two
new tables, server-only VAPID/encryption secrets and safe rollout sequence.
