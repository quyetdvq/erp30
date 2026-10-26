# M11.3B — Email channel (pilot gate) · ADR 2026-10-26

**Base:** M11.0 Notifications design v1.3/v1.4; accepted M11.2 (40/40 PHPUnit) and M11.3A (worker reliability 6/6). This ADR is an additive extension. Original v1.3 called Email/Push **outside MVP** and designated `import.completed.email` as **UNAVAILABLE** until M11.3.

## Deliverables and boundaries

- **Email ONLY:** `import.completed.email` becomes `capability=AVAILABLE`, `producer_state=ACTIVE` **only** when Import producer and `DINKERP_M11_EMAIL_QUEUE_ENABLED` are both enabled. Push stays unimplemented; do not advertise Push delivery.
- An **explicit current-epoch User override `true`** is necessary. Admin default for Email is immutable `false`: admin cannot opt User into Email by changing System default. Admin can lock `allow_user_override=false`, thereby stopping new receipts and cancelling pending sends at the next send-time check. Locking increments the epoch; unlocking does not restore old preferences.
- The original Import outbox event is still created once, in the Import domain transaction. The In-App dispatcher writes a separate Email receipt with UNIQUE `(recipient_user_id,event_key)` **in the same transaction as Inbox fan-out / Outbox ACK**, regardless of whether In-App preference is on. A failed receipt INSERT rolls back Inbox and ACK. No email is sent inside the business or Inbox transaction.
- A standalone Spark email worker sends **only after the previous transaction committed**. Its own row leases, recoveries, bounded retry (1/5/15/60/240 minutes, maximum 6 attempts), cancellation, and dead lettering are isolated from the In-App worker. MariaDB 10.4 uses `FOR UPDATE` without `SKIP LOCKED`. A flag OFF pauses without consuming attempts.
- Email identity comes from the current Shield `auth_identities` row (`email_password`); address is resolved immediately before each send, is validated, and is never persisted in the Email queue or printed in worker error output. User must still be active/not soft-deleted and have explicit current preference. No untrusted HTML/URL or arbitrary user-created content is sent. Initial subject/body are fixed plain-text templates describing only the terminal Import result counts.
- **At-least-once SMTP:** a process crash *after SMTP accepts a message but before MariaDB `SENT` ACK* can result in a duplicate email on retry. UNIQUE receipt keys guarantee one queue record, **not exactly-once remote delivery**. Consent revocation/identity changes are checked immediately before SMTP, but cannot make the remote HTTP/SMTP operation atomic with a concurrently changing database.
- **Pilot safety:** no sender is instantiated when send flag is off; SMTP configuration must use authenticated TLS/SSL; no `mail` or unauthenticated downgrade. `CI_ENVIRONMENT=testing` refuses the real SMTP adapter, and PHPUnit uses a fake sender with no network I/O. Admin/ops must verify email ownership and deliverability policy for production rollout; Shield identity existence alone is not independently verified mailbox ownership.

## Schema inventory

Migration `2026-10-26-000021_CreateNotificationEmailDeliveries.php` creates exactly **one** additional physical table: `dinktech_notification_email_deliveries` (InnoDB, MariaDB 10.4). Columns: `id`, `event_key`, `recipient_user_id`, `event_type`, `params_json`, `status`, `attempts`, `available_at`, `lease_token`, `leased_until`, `last_error_code`, `created_at`, `sent_at`. Unique `(recipient_user_id,event_key)`; FK to Shield User; bounded status and JSON constraints.

Total Notifications-related tables through M11.3B: **7** (four from 019, two Workflow assignment tables from 020, one Email receipt table from 021). M11.3A created **no** tables. Do not add Email data to `notification_outbox` as a second source event: that would make the Inbox worker compete with the Email worker.

## Deploy (DEV clone only until acceptance)

1. Backup the DEV clone; apply Git PATCH; `git diff --check`.
2. Test database: `cd backend; .\tools\test-unit.ps1 PREPARE M11` (guarded `database.tests=erp_test`, applies only missing 019/020/021, no DROP). Then `.\tools\test-unit.ps1 RUN M11` and `.\tools\test-unit.ps1 RUN M11-RELIABILITY`. None of these tests contacts SMTP.
3. Application DEV clone: `php spark migrate` after database backup, verify Migration 021 ledger and table. **Do not use the test PREPARE command against `erp`.** Existing six tables and their data are not modified by 021.
4. Configure CodeIgniter Email via private `backend/.env` settings (`email.protocol=smtp`, `email.SMTPHost`, `email.SMTPPort`, `email.SMTPUser`, `email.SMTPPass`, `email.SMTPCrypto=tls`, `email.SMTPTimeout` between 1 and 30, `DINKERP_M11_EMAIL_FROM=...`). Do not commit credentials. Validate DNS, TLS, SPF/DKIM and sending limits operationally. Use a pilot mailbox/domain first.
5. Set `DINKERP_M11_EMAIL_QUEUE_ENABLED=enabled` **only** when ready to queue opted-in events. Keep `DINKERP_M11_EMAIL_SEND_ENABLED=off` initially. Import producer and general notification delivery flags must already be enabled for new receipts. Explicit user opt-in via `/profile/display-preferences?tab=notifications` controls eligibility; no migration or seeder backfills opt-in.
6. Inspect queue totals: `SELECT status,COUNT(*) AS n FROM dinktech_notification_email_deliveries GROUP BY status;` (only on DEV clone). For a real SMTP pilot, set `DINKERP_M11_EMAIL_SEND_ENABLED=enabled` and schedule **separately** `php spark notifications:email-dispatch --once --batch=10`. Keep Inbox `php spark notifications:dispatch --once --batch=25` as-is.
7. To pause: set both Email flags off and stop Email worker schedule. Already queued receipts remain; if send is re-enabled, send-time consent/active/identity checks run again. No automatic replay of historical processed events. DDL rollback **never** performed automatically on live data.

## QA / acceptance

- Existing `RUN M11` baseline regression must continue to pass, including Workflow fan-out/lease.
- Unit: rejects silent/default consent, stale epoch, malformed email/header injection, forged message payload, and unsafe schema plans.
- MariaDB integration: no opt-in → no receipt; explicit opt-in → one receipt and one fake send; In-App OFF + Email ON; revoke after queue → CANCELLED; fake SMTP failure → RETRY → SENT; Email send OFF → PENDING with attempts unchanged.
- M11.3A two-process test remains separate; concurrent two-email-worker SMTP acceptance crash window and live SMTP delivery are **not** proved by these rollback-based integration tests. Pilot gate remains conditional on real SMTP configuration, bounded delivery testing and credential review. Browser Push is a separate M11.3C decision.
