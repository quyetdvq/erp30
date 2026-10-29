# M11 Notifications — On-demand Inbox + Detailed Push v1.9.18

Status: IMPLEMENTATION CANDIDATE, DEV HTTP/DB/BROWSER UAT NOT RUN.
Base: DinkERP v1.9.17. No new DB migration, seeder or notification type.

## Contract

### In-App synchronization on F5/navigation

`POST /api/v1/notifications/actions/sync`

- Requires an authenticated, active Shield User, existing CSRF and API session rules.
- Empty body and no query parameters; recipient is taken only from the authenticated session.
- Returns `data: {status: "CHECKED"|"THROTTLED"|"DISABLED", processed: integer}`.
- Processes at most **two due Outbox events** in a single request, with a two-second server session cooldown.
- Scoping: Import events owned by `import_jobs.created_by`; Workflow events with the actor's ID in the immutable `recipient_user_ids` snapshot; only recent seven-day events. All data is parameterized; MariaDB `JSON_CONTAINS` is used for Workflow snapshots.
- Existing Dispatcher lease/ACK/PBAC checks remain; no outbox status is written by GET.
- No user-provided resource ID, status, event type, recipient ID or URL.
- Does not invoke Push/Email HTTP transport. They still run through independently enabled cron workers.
- `/api/v1/notifications/summary` remains a read-only GET.
- The shell invokes POST then GET on initial authenticated load, F5, navigation and return-to-tab (throttled/single-flight). The 60-second polling path reads summary only. On failure of optional sync, the Inbox summary is still refreshed.

### Browser Push detail

- In-App event production and Push delivery are distinct: F5 synchronizes Inbox immediately; Chrome Push still requires scheduled Push dispatch.
- Web Push content is encrypted end-to-end; only allowlisted server-generated facts are shown on device lock screens.
- Devices lacking `key_material_cipher` are **not** sent generic payload-free notifications. Queued receipts are marked `CANCELLED` with `M11_PUSH_DEVICE_UPDATE_REQUIRED` and users must register again.
- Own-device listing gains `detail_ready: boolean` (no encryption key or endpoint disclosed). Device creation also returns `detail_ready=true`.
- The settings UI displays the actual current browser origin and warns that subscriptions on a different ERP host (e.g. `erp.monmin.net` versus `erp2.monmin.net`) cannot be updated across origins.
- New service-worker versions activate when installed; the existing subscription must be explicitly updated by its owner. ServiceWorker update never silently requests Notification permission.
- The SW renders unknown but **syntactically valid** Workflow state codes (such as `WAITING_APPROVAL`) under the fixed Workflow topic; invalid or unsafe payloads remain generic and are never rendered as arbitrary user input.
- An Order Created notification is **not** enabled here: the P2 Sales event producer/schema/permissions must be implemented first. Do not synthesize it from unrelated sources.

## Known constraints / safety

- Browser-requested sync is not a replacement for global Cron. It is limited to its authenticated User, a small batch and recent events. It is not intended to drain historical backlog or send Push/Email.
- Repeated page visits beyond client + session cooldown do not create duplicate Inbox rows because the existing Outbox leasing/ACK and unique `(recipient_user_id,event_key)` continue to apply.
- PostgreSQL/MySQL versions outside MariaDB 10.4 require compatibility review of `JSON_CONTAINS`.
- Actual performance must be checked with `EXPLAIN` against the deployment DB if Outbox backlog grows; only a bound of two events per request is guaranteed, not a specific millisecond latency.
- HTTP CSRF, cross-user isolation and Push lock-screen content must be verified on DEV before deployment.
- An old notification showing `erp.monmin.net` is evidence of that originating website; the new ERP2 service worker cannot modify it. Explicitly revoke the old site's subscription/notification permission if it is not intended to send notifications.

## DEV UAT acceptance

1. Log in as User A with enabled M11 delivery and eligible recent pending Import/Workflow event. Open ERP2 and observe `POST /api/v1/notifications/actions/sync` followed by `GET /api/v1/notifications/summary`; new In-App item appears without waiting for Cron.
2. Repeat with User B: the POST must not process an Import job created by A or a Workflow snapshot excluding B.
3. F5, navigate to Product/Customer and refocus tab: summary is refreshed; no GET has side effects; request bursts are throttled.
4. Leave Push/Email scheduled workers disabled and repeat: the POST does **not** contact the push provider. Enable only approved Cron separately.
5. Visit the notification preferences page and verify `detail_ready`. On a legacy device, click register/update and confirm `detail_ready=true`.
6. Confirm correct origin from the URL and device settings; remove subscriptions of old site explicitly if needed.
7. Trigger opted-in Import/Workflow via business action; let Cron Push process. Chrome title/body must identify topic/ID/state or counts; invalid payload still uses a generic safe fallback.
8. Test 401 unauthenticated, CSRF rejection, session switch and disabled delivery.

## Test commands

Backend (repo `backend`):
```
php tests/m11/notification_on_demand_sync_pure.php
php tests/m11/notification_push_content_pure.php
php tools/test-unit-check.php
```

Frontend (repo `frontend`):
```
pnpm run test:push-sw
pnpm exec vitest run src/features/notifications/__tests__/notificationOnDemandRefresh.spec.ts
pnpm exec vitest run
pnpm run type-check
pnpm run lint
pnpm run check:imports
pnpm run build
```

Do not rerun the v1.9.15 migration; this change has no schema alterations.
