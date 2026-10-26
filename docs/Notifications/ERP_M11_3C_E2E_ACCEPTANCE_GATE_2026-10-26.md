# ERP M11.3C — Browser Push End-to-End Acceptance Gate (DEV)

**Scope:** controlled real-device verification after M11.3C + PHPUnit Hotfix 01/02. Automated checks do not change persisted notifications, import records, system flags, login sessions or browser permissions on the live site. The only outbound Push is the one explicitly initiated by an operator after the queue gate passes. No new migration or database tables.

## Evidence hierarchy

| Gate | What it proves | What it does not prove |
| --- | --- | --- |
| PHPUnit M11 / Reliability | Database rules, fake provider, retry/lease, opt-in/out, account/device transfer | Network delivery to a real browser |
| `pnpm test:push-sw` | Actual Service Worker JavaScript: generic payload, navigation, same-origin isolation | Browser-native SW execution |
| `pnpm test:push-browser` | Production-built Service Worker registered in Chromium; a CDP synthetic `push` reaches the worker and its native `showNotification()` call resolves | Real provider, VAPID network request, user-device display |
| `notifications:push-acceptance --stage=preflight` | Read-only schema, credentials, encryption continuity, policy/default, runtime flags | Connection to a provider |
| `--stage=created` / `queued` | One scoped completed Import Outbox event, then exactly one pending Push receipt and no competing unfinished Push work | Provider acceptance |
| `--stage=sent` | Provider returned an HTTP success accepted by the worker and one receipt is `SENT` | Whether OS displayed notification or user clicked |
| Human DEV-device observation | Browser permission, actual notification appears, click routes to `/notifications` under current session | Universal delivery across browsers/devices |

**NEVER claim real end-to-end PASS without both `--stage=sent` and the native notification/click observation.** Provider responses indicate acceptance, not guaranteed display. Push is at-least-once: a retry after an ACK failure can produce a second generic alert.

## 0. Preconditions

- Dedicated **DEV-only** ERP user/account, dedicated browser profile and exactly **one** active Push subscription for that user. Pause concurrent background Outbox/Push workers and unrelated Import operations for this brief DEV-only acceptance window; read-only gate results are snapshots, not locks. User explicitly opts in to `import.completed.push`; system policy default remains Off. Other accounts must not have unfinished Push receipts when sending.
- Current backend server `backend/.env` retains `DINKERP_M11_CURSOR_SECRET` (at least 32 characters). Keep DB credentials, HMAC and SMTP secrets server-side.
- Migration 022 already applied to DEV `erp`, and 019–022 on `erp_test`. Do **not** reapply migrations.
- Before provisioning or sending, **Push Queue and Push Send both Off** in `/system/configuration-settings`; keep Send Off until a single queued receipt is verified.
- Server must have `openssl`, `curl`, HTTPS trust chain, outbound HTTPS access to the Push provider, and no corporate proxy TLS interception.

## 1. Provision server-side secret values ONCE (only if never configured)

The three secrets/settings are *not* managed via `/system/configuration-settings`:

- `DINKERP_M11_PUSH_ENDPOINT_KEY_B64`: 32 random bytes, base64. Do not rotate if any encrypted subscriptions exist, or existing endpoints become undecipherable.
- `DINKERP_M11_PUSH_VAPID_PRIVATE_PEM_B64`: a **new unique** EC P-256 private key in base64 PEM. Do not use the repository's `vapid-p256-test-only.pem`; runtime now rejects it outside testing.
- `DINKERP_M11_PUSH_VAPID_SUBJECT`: `mailto:` followed by the DEV operator's valid address.

If all three are absent and there have **never** been subscriptions, this optional Windows tool appends them to `backend/.env` without displaying secrets. It **refuses** to overwrite an existing key, or to write when `.env` is tracked or not Git-ignored. Keep a secure off-Git backup of `.env`; never paste generated values into chats/logs:

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\m11-push-provision-dev.ps1 -DevConfirmation DEV_ONLY -ContactEmail 'your-real-operations@example.org'
```

If `openssl.exe` is not found, supply `-OpenSslExe 'C:\path\to\openssl.exe'`. The script uses OpenSSL CLI instead of `openssl_pkey_new()`; the latter has failed on certain XAMPP Windows builds without an OpenSSL config path. If any Push keys already exist, **do not run** provisioning; keep them unchanged.

Run read-only preflight:

```powershell
php spark notifications:push-acceptance --stage=preflight
```

Expected: all `PASS` checks; both Push Queue and Push Send must be `off` or preflight blocks acceptance. Flags may be `database` or legacy fallback. A note about no registered browsers is expected before subscription. Preflight does not contact Push providers.

## 2. Browser-automated tests (no live ERP API)

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm type-check
pnpm build
pnpm test:push-sw
pnpm test:push-browser
pnpm test:production-smoke
```

If Chromium's **full browser** for new-headless is not installed, run `pnpm exec playwright install chromium` (the earlier standard Playwright test may have used only `chromium-headless-shell`). On a Windows interactive DEV machine, if an enterprise/browser policy still blocks notifications in headless mode, inspect the native permission error first; for diagnosis only, use `$env:M11_PUSH_BROWSER_HEADED='1'; pnpm test:push-browser; Remove-Item Env:M11_PUSH_BROWSER_HEADED`. The headed run uses the same new, disposable profile and still fails unless the real Notification API reports `granted`.

The Chromium `test:push-browser` uses local `http://127.0.0.1:4174` (secure-context loopback), registers **the actual** `notification-sw.js`, then injects a synthetic event with CDP. It captures a successful native `showNotification()` call from the running worker, rather than requiring the headless OS notification list to persist it. When `getNotifications()` is nonempty its content is independently checked. The test will still FAIL when the CDP event is not delivered or the browser API rejects the call. **Chromium denies Notifications in Incognito**: the test therefore launches a fresh, regular (non-Incognito) Chromium profile via `launchPersistentContext`, using new-headless Chromium (`channel: chromium`), then closes the browser and deletes its temporary profile in `finally`. It grants and checks **real native** notification permission on that temporary profile; it never reads the operator's Chrome profile or changes live browser permissions. It does not create subscriptions or send HTTP, and does **not** prove native OS presentation. Browser differences still require real-device confirmation.

Verify the deployed Worker is available (do not interpret HTTP status alone as full acceptance):

```powershell
$r = Invoke-WebRequest 'https://erp2.monmin.net/notification-sw.js' -UseBasicParsing
$r.StatusCode
$r.Content -match 'notificationclick'
```

### Browser registration failure triage (without SQL or endpoint tokens)

The Push Devices panel now shows a **bounded, localized diagnosis** instead of a single generic error. It distinguishes browser permission, Service Worker registration (`/notification-sw.js`), VAPID public key, `pushManager.subscribe()`, and the authenticated subscription API. The UI never echoes raw exceptions, Push endpoint URLs/tokens, or key material. Verify that the new frontend `dist` was deployed before trusting these messages.

**Microsoft Edge / WNS limitation:** Edge on Windows may produce an endpoint below `*.notify.windows.com`. The current ERP Push sender intentionally uses **zero HTTP payload**, and WNS raw notifications have additional transport requirements. Such endpoints are not supported in this release and should not be silently accepted. For M11.3C real-device acceptance use **Chrome or Firefox** with an FCM or Mozilla Push endpoint. Do not broaden the backend's SSRF allowlist or add WNS sending without a separate end-to-end provider implementation and tests.

Regression for the safe error classifier:

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm test:push-registration
pnpm type-check
pnpm build
```

## 3. One controlled real-device delivery

Use a dedicated **DEV** user with only one browser subscription. Perform these steps in order:

1. In the live DEV UI, open Notification Preferences, explicitly set `import.completed.push` to **On** and save. Do **not** register the device yet: the UI requires Push Queue to be enabled before it exposes the VAPID public key.
2. In `/system/configuration-settings`, turn **Push Queue On**, **Push Send Off**, and confirm `DINKERP_M11_IMPORT_PRODUCER_ENABLED` / `DINKERP_NOTIFICATIONS_DELIVERY_ENABLED` are On. Do not alter Email or Workflow flags.
3. Back in Notification Preferences on the same user/browser, press **Subscribe this browser**, grant browser notification permission, and confirm exactly one device appears. Secure context and correct origin are required. Keep the browser available for observation.
4. Perform **one valid, controlled DEV Import** for that user, reaching `COMPLETED` (not `FAILED` or `COMPLETED_WITH_ERRORS`). Note the real **user ID** and **Import job ID** from the normal ERP workflow. Do not inject SQL or fabricate Outbox rows.
5. **Before dispatching Outbox**, run:

   ```powershell
   cd D:\XAMPP\htdocs\erp\backend
   php spark notifications:push-acceptance --stage=created --user-id=USER_ID --import-job-id=JOB_ID
   ```

   `PASS single_import_outbox_safe_to_dispatch` means the Import job is `COMPLETED` and **owned by the test user**, the target event is the **only** unfinished Outbox event, user consent is current and only one device is active. If FAIL, **STOP**; do not run the worker until the queue is isolated.
6. Dispatch **one** Outbox event:

   ```powershell
   php spark notifications:dispatch --once --batch=1
   php spark notifications:push-acceptance --stage=queued --user-id=USER_ID --import-job-id=JOB_ID
   ```

   Require `PASS single_queued_receipt_safe_to_send`: one receipt `PENDING`, no other `PENDING/RETRY/LEASED` Push receipts globally, Outbox `PROCESSED`, Push Send still Off. If FAIL, STOP.
7. **Only now**, turn **Push Send On** in Configuration Settings, then run a single Push dispatch:

   ```powershell
   php spark notifications:push-dispatch --once --batch=1
   ```

   Immediately turn **Push Send Off** afterward. Read the scoped receipt evidence:

   ```powershell
   php spark notifications:push-acceptance --stage=sent --user-id=USER_ID --import-job-id=JOB_ID
   ```

   Require `PASS provider_accepted_receipt` and `SENT:1`. If status is `RETRY/DEAD/CANCELLED`, do not repeatedly turn Send on without diagnosing the provider response.
8. On the actual subscribed browser, confirm the native notification appeared with generic text **without business data**, click it, and verify the current authorized ERP session opens `/notifications`. Record browser/version, permission state and whether the OS notification center suppressed it. HTTP `SENT` alone is **not** enough.
9. Revoke the test device through the same user's Push Devices panel, switch the user Push preference Off, and turn **Push Queue and Push Send both Off**. Re-run preflight to verify the flags. Do not remove/rotate crypto keys while any real encrypted subscription exists.

### Fail-closed rules

- No mutation through the CLI checker; it only `SELECT`s and validates secrets **in memory**, never prints encrypted endpoints, key values, or Import payloads.
- A controlled real-provider test must use one opted-in DEV user and one registered device. If any other unfinished Outbox/Push work exists, the staged checker blocks the send.
- Do not schedule a recurring Push Worker, grant Push consent on behalf of a user, or activate sending globally until the human device observation has passed.
- No actual provider call is ever made in PHPUnit, `test:push-sw`, `test:push-browser`, or the read-only CLI. Only the explicit `notifications:push-dispatch` command can send.

## Acceptance decision

- **BACKEND PASS**: prior 119/119 PHPUnit (plus new parser/security tests after this patch), migration 022 and Inbox already verified.
- **SERVICE WORKER AUTOMATION PASS**: Node/Playwright tests passing locally.
- **REAL E2E PASS**: staged CLI checks pass and one actual notification is visibly received/clicked on the dedicated DEV browser, followed by clean rollback of operational flags.

Until the last condition is observed, M11.3C has automated backend acceptance but **real Browser Push E2E remains unverified**.
