# M11.6A — SMTP provisioning before Email channel activation

**Scope:** DinkERP M11, CodeIgniter 4.7.x, MariaDB 10.4, XAMPP first and Shared Hosting later. No new database tables, migrations, frontend API, changes to consent, or automated Email task. The existing Email Queue and Email Send switches remain **OFF** until acceptance. The four previously observed overdue Email `PENDING` receipts are **not** deleted, claimed, or resent by this patch.

## A. Configure private SMTP secrets outside source control

On XAMPP edit the **existing** `D:\XAMPP\htdocs\erp\backend\.env` (do not replace your other settings). When migrating to hosting, set the equivalent private environment variables or a non-public `backend/.env`. Do not put real passwords in `app/Config/Email.php`, frontend state, audit tables, screenshots, commits or a chat message.

**STARTTLS example (587):** Replace every `YOUR_*` with your mail provider's own values:

```ini
# M11 only: should be the authenticated mailbox or provider-approved sender alias.
DINKERP_M11_EMAIL_FROM = "YOUR_MAILBOX@YOUR_DOMAIN"

# Case-sensitive CodeIgniter Email Config property names.
email.protocol = smtp
email.SMTPHost = "YOUR_SMTP_SERVER"
email.SMTPUser = "YOUR_SMTP_LOGIN"
email.SMTPPass = "YOUR_SMTP_PASSWORD_OR_APP_PASSWORD"
email.SMTPPort = 587
email.SMTPCrypto = tls
email.SMTPAuthMethod = login
email.SMTPTimeout = 15
email.SMTPKeepAlive = false
```

For a provider specifically requiring **implicit TLS/SMTPS on port 465**, change just these two lines:

```ini
email.SMTPPort = 465
email.SMTPCrypto = ""
```

CodeIgniter 4.7.x establishes TLS at connection start on port 465; do not use `SMTPCrypto=tls` (STARTTLS) for that port. The M11.6A readiness gate supports port 465 with blank or `ssl`, and other ports using `tls`. On shared hosting, confirm the provider permits outbound SMTP on the chosen port and permits the specified authentication method. Do not assume an ordinary interactive login password or port 25 will work.

**Security:** Keep `.env` out of Git and public document roots; restrict file access to the account running PHP. If special characters in the password confuse Dotenv parsing, follow the exact quoting rules of your installed CodeIgniter/Dotenv version; never print the parsed config in logs. At runtime, host-level environment variables may override `.env`. Do **not** use `php spark config:check Email` when collecting support output; it can print `SMTPPass`.

## B. Preflight — no network traffic or email delivery

```powershell
cd D:\XAMPP\htdocs\erp\backend
php spark notifications:smtp-check
php spark notifications:ops-audit --strict
```

Expect `smtp_configuration_ready=yes` and `configured_smtp=yes`. The preflight does **not** authenticate against the server or send mail. `OPS CONFIGURATION PASS` while Email is OFF does not prove SMTP delivery. If readiness is `no`, check the nine private values above locally without copying secrets into a support ticket. Port 465 requires correct implicit-TLS mode.

## C. Explicit one-address live SMTP test (operator approval required)

After the preflight passes and you authorize one external email, run **exactly once** with a mailbox you own:

```powershell
php spark notifications:smtp-check --to=YOUR_TEST_INBOX@YOUR_DOMAIN --confirm=SEND_ONE_TEST_EMAIL
```

This command goes **directly to SMTP** for one test message. It does **not** read or change the database, claim the four historic PENDING emails, enable a runtime switch or register a scheduled worker. It rejects unknown/duplicate flags, unconfirmed sends and header injection. It refuses to send in the `testing` environment. `SMTP provider accepted one test send` is not proof of inbox delivery: verify arrival, junk folder, sender/From, provider logs and SPF/DKIM/DMARC as applicable. If it fails, the exception is deliberately generic to avoid dumping passwords, SMTP transcripts or recipient data; investigate connection/TLS/authentication locally.

## D. Hold for old PENDING queue review and explicit activation

Continue to keep these two flags in `/system/configuration-settings` **OFF**:

- `DINKERP_M11_EMAIL_QUEUE_ENABLED = OFF`
- `DINKERP_M11_EMAIL_SEND_ENABLED = OFF`

Before enabling, review the 4 old PENDING receipts **read-only**: ages, intended recipients, current consent and whether the messages are still appropriate. The M11 worker checks current consent and account state at delivery, but toggling Email Send ON then running the worker can send existing queued mail. Never clear or modify the queue just to remove an audit warning; get an explicit operational decision on the backlog.

When live delivery is approved, enable one channel at a time through the audited PBAC frontend and run a **bounded** worker in an authorized window. The automated Email Task is intentionally **not registered** in M11.5: adding it is a separately scoped follow-up after SMTP acceptance and backlog review. Avoid scheduling the Email worker now.

## E. Regression and rollback

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
php spark notifications:ops-audit --strict
php spark tasks:list
```

Expected previous baseline before M11.6A: 124 unit + 46 integration + 7 reliability. M11.6A adds an SMTP options unit test (including failure scenarios) and extra 465/587 readiness assertions. Integration and reliability counts should not change. **Use guarded `erp_test` only** for integration and reliability; no production/test DB reset or migrations.

To rollback SMTP configuration: keep Email Queue/Send OFF; remove only the SMTP-specific variables from private `.env` or restore their prior values. Do not remove or reset existing M11 tables, delete PENDING receipts, or revert unrelated scheduler flags. If a test password was compromised, rotate it with the SMTP provider and do not publish it.

**Acceptance distinction:** Source patch verified and dry preflight available != provider credentials set != test email received != M11 Email Queue safe to enable != automated Email Cron acceptance. Record those gates separately.
