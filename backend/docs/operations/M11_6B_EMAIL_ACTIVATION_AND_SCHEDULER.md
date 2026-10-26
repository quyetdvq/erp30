# M11.6B — SMTP Inbox Verified; Email activation and shared-hosting scheduler

## Gate already achieved

Operator confirmed a one-message SMTP acceptance test from M11.6A **arrived in the intended Inbox**. This is **not** equivalent to production queue acceptance or cron acceptance. The 4 historic Email `PENDING` receipts are a separate operational gate. This patch never performs network I/O, sends email, claims queue rows, changes runtime values, cancels prior receipts or registers a cron job during installation.

## What changed

- One new **non-secret database-backed** runtime flag `DINKERP_M11_TASKS_EMAIL_ENABLED`, section `scheduler`, default OFF, managed through existing `/system/configuration-settings` with `notification_setting.view/edit` PBAC, CAS revision and audit. Strict `1/0` .env fallback is retained only until first DB save.
- A bounded `m11-email-dispatch` Tasks job using the **existing** `notifications:email-dispatch --once --batch=1` worker; its lease/consent/retry rules are unchanged. Job registered only if **all three** are true: task flag ON, existing Email Send flag ON, and SMTP configuration readiness PASS. `EMAIL_QUEUE_ENABLED` continues to govern *new* receipts; it does NOT stop already queued receipts from sending.
- Read-only `php spark notifications:email-backlog`, which prints up to 50 pending/retry receipt IDs, timestamps, event types and attempt counters **without personal recipient details, sender credentials, payloads or mutation**. The aggregate status includes all receipt states. This does not judge whether a message is appropriate to send.
- Operations audit exposes `EMAIL_TASKS_ON_WITHOUT_VALID_SMTP_CONFIG` as a blocker and `EMAIL_TASKS_ON_SEND_OFF_NO_SCHEDULE` as a warning. Unpersisted flag continues to warn until saved.
- Contract expands from 11 to **12** runtime flags. No new tables, migrations, REST endpoints, or provider credentials in source control.

## Stage 1 — apply patch and remain safely paused

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\ERP_M11_6B_Email_Activation_And_Tasks.patch
git apply .\ERP_M11_6B_Email_Activation_And_Tasks.patch
git diff --check
cd .\backend
php spark notifications:email-backlog
php spark notifications:ops-audit --strict
php spark tasks:list
```

In `/system/configuration-settings` **leave all three OFF** during backlog review:

- `DINKERP_M11_EMAIL_QUEUE_ENABLED = OFF`
- `DINKERP_M11_EMAIL_SEND_ENABLED = OFF`
- `DINKERP_M11_TASKS_EMAIL_ENABLED = OFF`

Save all 12 settings to the database. The existing In-App ON, Push Tasks OFF and Push delivery flags should retain their prior settings. Do NOT put credentials into frontend or logs. `tasks:list` must **not** include `m11-email-dispatch`. Do not run `notifications:email-dispatch` while Send is ON before approving backlog.

## Stage 2 — explicit review of 4 historic PENDING receipts

Use `notifications:email-backlog`, or a private DBA review, to classify each receipt as still relevant, no longer appropriate, or requiring investigation. The read-only CLI intentionally does not expose recipient identity, address or content. If individual recipient/consent verification is necessary, perform it using authorized private DB access, not via public screenshots or chat.

**Do not turn Email Send ON** until an operator records the decision for every old receipt. SMTP worker checks recipient's *current* consent, active account, email identity and payload shape, but it does not check whether an old business notification is too stale. For messages that must not be sent, arrange an authorized, explicitly audited cancellation procedure; never directly delete/rewrite queue rows just to make the warning disappear. This patch intentionally does not provide a bulk cancel or resend command.

## Stage 3 — controlled pilot (sends real email; operator approval)

1. Once backlog disposition is complete, use the frontend to turn Email Queue ON, then Email Send ON while **Email Tasks remains OFF**. The UI warns when Email Send is enabled. This is the first real activation point and will allow an authorized **manual Email Worker invocation** to send eligible old/new receipts.
2. Trigger a single authorized import completion for an explicitly opted-in test user. Confirm it produces exactly one expected Email receipt after Inbox/Outbox acknowledgment.
3. Only during a permitted live-send window, run `php spark notifications:email-dispatch --once --batch=1`. Inspect the worker counters and receiving mailbox. Delivery is **at least once**, not exactly once: a successful SMTP send whose DB acknowledgment is lost can potentially be retried; investigate ambiguous outcomes rather than blindly rerunning.
4. Re-check `notifications:email-backlog` and `notifications:ops-audit --strict`. Confirm no dead/retry/lease anomalies and no unexpected recipient deliveries.
5. Only then explicitly enable Email Tasks through the audited frontend. `php spark tasks:list` should show `m11-inapp-dispatch` and `m11-email-dispatch` (Push task remains absent). Never enable auto Email as a way to "test" SMTP with the old backlog.

If a step fails, immediately switch **Email Tasks OFF and Email Send OFF** through the frontend. Keep Email Queue OFF too if no new receipts are desired. Pending rows remain intact for investigation.

## Shared Hosting cron and tests

Cron remains **the same single existing runner** `m115_tasks_cron.sh` invoking `php spark tasks:run`, not a second independent Email Cron. It triggers only jobs registered in Tasks, so the Email flag will take effect on the next CLI run. If hosting supports only 5-minute cron, expect that delivery latency. Examine both Task output (`Processing/Executed/Failed`) and M11 queue metrics; Tasks process exit code 0 alone is not delivery proof.

On XAMPP:

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm run openapi:generate
pnpm run openapi:lint
pnpm run openapi:generate:check
pnpm run type-check
pnpm exec vitest run src/features/settings/pages/__tests__/ConfigurationSettingsView.spec.ts
pnpm run build
cd ..\backend
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
php spark notifications:ops-audit --strict
php spark notifications:email-backlog
php spark tasks:list
```

The integration/reliability suites use the guarded `erp_test` database, never production. The source patch is not a production rollout approval. No connection to the SMTP provider or real mail delivery is performed by running these tests.

## Rollback

First disable `DINKERP_M11_TASKS_EMAIL_ENABLED` and `DINKERP_M11_EMAIL_SEND_ENABLED` through the audited UI. Inspect `tasks:list` to confirm no Email task, then remove only this isolated patch if necessary. Do not revert migrations or other runtime switches, delete old email rows, or clear Composer dependencies. Do not silently restore legacy `.env` settings over database values.
