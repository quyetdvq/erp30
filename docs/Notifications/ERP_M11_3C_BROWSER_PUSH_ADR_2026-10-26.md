# ERP M11.3C — Browser Push Notifications (ADR, 2026-10-26)

## Decision and scope

M11.3C adds an **optional browser Push signal** for the existing `import.completed` event only. M11.2 Workflow and Import warnings/failures remain In-App. The existing Outbox is the sole source of delivery receipts; Push work is queued **in the same DB transaction** as the Outbox ACK, and delivered later by an independent worker. Existing Email/In-App semantics remain unchanged.

Browser Push transports **zero application payload bytes**. We intentionally do **not** implement Web Push `aes128gcm` content encryption or send document IDs, import figures, users, authentication data, or other business data to Push services. The Service Worker shows a generic alert and opens `/notifications`; the ERP session must authorize viewing the Inbox. The Push service sees its subscriber bearer URL and VAPID public identity, but no ERP notification content. It cannot guarantee that the device displays the alert after accepting the request.

## Database contract — Migration 022

`2026-10-26-000022_CreateNotificationPushTables` adds **exactly two** InnoDB tables:

| Table | Purpose |
| --- | --- |
| `dinktech_notification_push_subscriptions` | Shield user-owned browser devices; HMAC endpoint fingerprint for uniqueness, AES-256-GCM encrypted endpoint at rest, enabled/revoked dates. Device IDs are the only identifier exposed to users. Max five active devices per user. |
| `dinktech_notification_push_deliveries` | Unique `(subscription_id,event_key)` receipt with PENDING/LEASED/RETRY/SENT/CANCELLED/DEAD, lease token, attempts, retry/backoff, failure code. |

The migration also inserts one **system policy row**, `import.completed.push`, into existing `dinktech_notification_settings`, with default **Off** and user override enabled. It does not add an audit table. Notifications now comprise **nine** tables: 4 core + 2 Workflow + 1 Email + 2 Push. Migration 022 must be applied once to the application DB after a backup. In `erp_test`, `PREPARE M11` safely prepares Migration 022, validating configured credentials and test-only target.

## Consent, security and lifecycle

- A system policy default **never constitutes consent**. The user must explicitly set personal preference `import.completed.push = On` under Notification Preferences, with current policy epoch/revision, then explicitly press **Subscribe this browser** and grant the browser permission. Inherit/Off is not consent. The system administrator cannot turn the Push policy default On.
- Device registration requires an authenticated active Shield user and limits active devices to five. Registration is idempotent for an already-active device on the same account, preserving existing receipts. Transferring/re-enabling an endpoint cancels earlier queued receipts; devices are stored under their current account. Revoke disables the stored device and cancels pending receipts; revocation cannot retroactively undo an HTTP request already accepted by a provider.
- Subscription endpoints are bearer secrets. Use `DINKERP_M11_PUSH_ENDPOINT_KEY_B64`, an independent **32-byte random key encoded in base64**, for AES-256-GCM encryption and HMAC-SHA256 lookup; do not store it in the database or expose it through APIs. Keep it stable, backed up securely; rotation without a data migration makes existing subscriptions undecryptable. Only recognized HTTPS provider hosts are accepted (`fcm.googleapis.com`, `updates.push.services.mozilla.com`, `web.push.apple.com`, `web.push.icloud.com`); explicit non-443 ports, credentials, fragments and redirects are rejected. TLS verification must remain enabled.
- VAPID authenticates the origin to Push providers using a dedicated EC **P-256** private key (RFC 8292, ES256) encoded as a base64 PEM server setting `DINKERP_M11_PUSH_VAPID_PRIVATE_PEM_B64`, plus `DINKERP_M11_PUSH_VAPID_SUBJECT=mailto:operator@example.com`. The browser receives only the derived **public key**. Preserve key continuity: VAPID private key rotation requires browsers to re-subscribe.
- No Push credentials, endpoint ciphertext or private data appear under `/system/configuration-settings`; it manages **only** `DINKERP_M11_PUSH_QUEUE_ENABLED` and `DINKERP_M11_PUSH_SEND_ENABLED`, both **Off by default**, as audited, revision-checked operational flags in the preexisting `dinktech_settings` table. This extends managed flags from seven to **nine**. Legacy `.env` is only a fallback until saved to DB; no need to add these two operational switches to `.env`.
- No external sending occurs in PHPUnit: the Push Worker receives a fake `NotificationPushSender` **only** under `ENVIRONMENT=testing`. The real SMTP/Push channels remain independent. Worker reevaluates user activity, subscription ownership, consent and Push Send toggle before attempting each send. Push is **at least once**: a process failure after the provider accepts a request but before DB ACK can lead to a duplicate generic alert.

## HTTP / UI / worker

- `GET /api/v1/notifications/push/config` — availability and non-secret VAPID public key.
- `GET /api/v1/notifications/push/subscriptions` — current user's active device IDs/dates only.
- `POST /api/v1/notifications/push/subscriptions` — register validated endpoint + Web Push public keys from the browser; no arbitrary URL can be posted to a backend worker.
- `DELETE /api/v1/notifications/push/subscriptions/{id}` — revoke own device.
- Notification Preferences includes the user opt-in and device panel; `/notification-sw.js` is same-origin and does not cache ERP data. Browser Push requires a secure context (HTTPS), Service Worker, PushManager and browser permission. On iOS, browsers may require a Home Screen web app. Service Worker registration and permission request happen **only** on an explicit click.
- Command: `php spark notifications:push-dispatch --once --batch=10`. Requires server extensions OpenSSL and cURL. Worker must be scheduled separately; no HTTP is initiated by business requests or inside database transactions. Turning Send off does not discard pending receipts.

## Provisioning and verification (Windows PowerShell)

Run key generation **locally**, preserve private outputs securely, and set their values in `backend/.env` without committing or sharing the secrets. Generate a storage key using PHP `random_bytes(32)`; generate a separate OpenSSL `prime256v1` EC private key, export PEM, then base64-encode PEM. Example local commands (outputs are **secrets**):

```powershell
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
php -r '$k=openssl_pkey_new(["private_key_type"=>OPENSSL_KEYTYPE_EC,"curve_name"=>"prime256v1"]); openssl_pkey_export($k,$pem); echo base64_encode($pem),PHP_EOL;'
```

After applying PATCH, use exactly these gates; **do not** enable Send yet:

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11-UNIT
.\tools\test-unit.ps1 PREPARE M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
# On backed-up application DEV database only (not erp_test):
php spark migrate
php spark migrate:status | Select-String 'CreateNotificationPushTables'
cd ..\frontend
pnpm openapi:generate
pnpm type-check
pnpm build
pnpm test:production-smoke
```

To test delivery on an approved development mailbox/device: deploy frontend, keep both operational switches Off, configure secrets and verify browser compatibility, turn **Queue On** in System Configuration Settings, set user Push preference On, register a device, create a DEV `import.completed` event and run the existing Outbox worker. Verify one PENDING Push receipt. Only then turn **Send On** and execute `notifications:push-dispatch --once --batch=10` for the controlled device. Do **not** claim acceptance from PHPUnit alone: it uses fake Push provider, so real browser permission/provider end-to-end remains a separate gate.

## Limitations and follow-ups

Only Import completed triggers Push; no general-purpose payload renderer or user-visible push content. Browser permission denial, browser shutdown, provider TTL (~60s), OS notification controls and VAPID key rotation remain outside ERP's guarantees. No independent two-process Push-worker race test is added by M11.3C (M11.3A covers concurrent Inbox Worker); ensure that reliability gate before broad rollout. Browser Push should remain Off unless a real device test succeeds.
