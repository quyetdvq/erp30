# ERP M11.3B.1 — System Runtime Configuration for Notifications

## Decision

Extend the existing **System / Configuration Settings** surface with an audited, PBAC-controlled Notification Operations section at `/system/configuration-settings`. This is deliberately separate from **Display Settings** and from **Notification System Policies** (per-event defaults and User preferences).

Use the existing `dinktech_settings` table, `class=NotificationRuntime`, `context=system`, and the existing `dinktech_configuration_audit_events`. **No migration, no new tables and no schema modifications.** Only `notification_setting.view` can read, only `notification_setting.edit` can edit. Authorized edits are optimistic-revision guarded and serialized by locking the canonical GROUP root, including the first creation of a revision row.

## Managed switches

| Key | Runtime behavior |
|---|---|
| `DINKERP_M11_SETTINGS_WRITE_MODE` | Enable Notification policy and User preference mutations |
| `DINKERP_M11_IMPORT_PRODUCER_ENABLED` | Import terminal event producer |
| `DINKERP_M11_WORKFLOW_WRITE_MODE` | Independent M11 Workflow assignment/transition write gate; the existing M33B write gate remains a separate path for transitions |
| `DINKERP_M11_WORKFLOW_PRODUCER_ENABLED` | Workflow action-required event producer |
| `DINKERP_NOTIFICATIONS_DELIVERY_ENABLED` | In-App Outbox dispatcher |
| `DINKERP_M11_EMAIL_QUEUE_ENABLED` | Explicitly opted-in Import Email receipts |
| `DINKERP_M11_EMAIL_SEND_ENABLED` | Real SMTP sender, when transport is configured |

All seven controls are Boolean, default **OFF** if no database override and no legacy `.env` value. Within a running HTTP request or CLI job, the database override wins; reads are not process-cached. DB failures or corrupt saved values fail closed. Do not switch off delivery in order to discard pending events: OFF pauses workers without acknowledging pending rows.

### Migration from `.env` (no operational outage)

1. Apply this patch. Deploy backend/frontend; keep the old `.env` switches unchanged initially.
2. Open `/system/configuration-settings` with a User granted `notification_setting.view` and `notification_setting.edit`.
3. Review the **effective** values. The source label shows whether each key is persisted or inherited from legacy `.env` (or default OFF).
4. Click **Save all to database**. This persists *all seven* values, even if equal to the inherited values, and audits each initial write.
5. Only after a successful response, remove those seven **non-secret** entries from `.env`. DB values now govern all workers and producers; no service restart is required for the toggles themselves.
6. Keep SMTP sending **OFF** until real transport configuration and a controlled mailbox are ready. The UI warns before activating Email sending.

### Permanently server-only controls

Keep `database.tests.*`, SMTP credentials (`Config\Email`), `DINKERP_M11_EMAIL_FROM`, `DINKERP_M11_CURSOR_SECRET`, `DINKERP_M11_OPERATIONS_WRITE_MODE` (dangerous DEV requeue gate), `DINKERP_M11_ALLOW_SCHEMA_DROP` (migration safety gate), and `DINKERP_M33B_WRITE_MODE` outside the public configuration UI. None can be patched through the new API.

## HTTP contracts

- `GET /api/v1/configuration-settings/notifications`: `{ revision: "0", items: [{key, section, enabled, source}, ...] }`.
- `PATCH /api/v1/configuration-settings/notifications`: `{ "expected_revision":"0", "changes":[{"key":"DINKERP_M11_EMAIL_SEND_ENABLED","enabled":false}] }`.
- Allowed keys are a strict server-side allowlist; no arbitrary names or secrets. Stale revision -> HTTP 409; invalid payload -> 422; missing PBAC -> 403; logged-out -> 401.
- One transaction updates Settings, a global optimistic revision and audit events. Unchanged persisted values are no-op; inherited values are persisted even if their current value is identical, to support migration from `.env`.

## Email rollback regression (M11.3B test correction)

The earlier test `EmailNotificationsIntegrationTest::testFailedInboxAckRollsBackEmailReceiptWithInbox` ran an Inbox worker **inside an outer PHPUnit fixture transaction**. CodeIgniter nested transactions only track depth; rolling back the nested transaction does not physically roll back its DML. Therefore its row-count assertions were invalid: the earlier failure did not establish a production transaction bug.

The test now verifies the resulting retry status only. The Reliability suite adds `testEmailReceiptAndInboxPhysicallyRollBackBeforeAck` with a **committed** fixture, real top-level InnoDB Worker transaction and cleanup by generated IDs. It validates both Inbox and Email receipt are absent after injected failure, with the Outbox retained as RETRY.

## Acceptance

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\ERP_M11_3B_1_System_Configuration_Runtime_Flags.patch
git apply .\ERP_M11_3B_1_System_Configuration_Runtime_Flags.patch
git diff --check

cd .\backend
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
php spark routes | Select-String 'configuration-settings/notifications'

cd ..\frontend
pnpm openapi:generate
pnpm type-check
pnpm build
pnpm test:production-smoke
```

`PREPARE M11` is not needed if 019–021 are already applied. No real Email is sent by PHPUnit; the transport remains guarded. Record migration to database via the UI before removing legacy `.env` flags.
