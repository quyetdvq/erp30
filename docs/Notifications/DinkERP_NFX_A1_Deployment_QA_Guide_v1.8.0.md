# DinkERP NFX-A1 - Application and QA Guide

## Baseline and scope

- Required source: `erp_v1.8.0.zip`, directory `erp30-main/`.
- Baseline SHA-256: `fde25bf21254c13b744645cddcbddf63d847c878712076e137753d60710fe907`.
- Artifact: `DinkERP_NFX_A1_Notification_UX_v1.8.0.patch`.
- Scope: frontend-only clickable bell, exact/list-only routing labels, independent 5-row preview, safe session reset, dedicated mark-read, localization and regression tests.
- No DB migration. No changes to CI4 APIs, Tasks, Outbox, Email/Push workers, consent, scheduler flags or live queues.

## Apply patch (PowerShell)

Copy the `.patch` to the repository root, and use a clean ERP v1.8.0 working tree (or a working tree with the identical targeted frontend files).

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\DinkERP_NFX_A1_Notification_UX_v1.8.0.patch

git apply .\DinkERP_NFX_A1_Notification_UX_v1.8.0.patch

git diff --check

git status --short
```

If `git apply --check` fails, do not use `--reject` or `--3way` blindly. Inspect the local modifications before applying a new patch.

## Frontend acceptance tests

```powershell
cd D:\XAMPP\htdocs\erp\frontend

pnpm exec vitest run src/features/notifications/__tests__
pnpm exec vitest run src/features/settings/pages/__tests__/ConfigurationSettingsView.spec.ts
pnpm run type-check
pnpm run build
```

Run backend M11 regression when closing the release, even though NFX-A1 does not modify backend code:

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK M11
.\tools\test-unit.ps1 RUN M11
.\tools\test-unit.ps1 RUN M11-RELIABILITY
php spark notifications:ops-audit --strict
```

The M11 Reliability suite uses guarded `erp_test` DML fixtures and must not run on production, shared test databases or concurrently. Never run `php spark tasks:run` merely to validate this UI patch.

## Browser checks on a DEV environment

The existing `VITE_M11_NOTIFICATIONS_ENABLED=true` build flag must be enabled for the Notification bell and Inbox. Do not change it in production without normal release approval.

1. Open `/notifications`, select Unread and load more. Open and close the bell. Confirm Inbox filter, visible items and pagination cursor remain unchanged.
2. Open an Import item from the bell. It must navigate to `/products/import-jobs/{id}` without first opening the Inbox.
3. Open a Product Workflow item. It must navigate to `/products/{id}`.
4. Open Department, Employee or another list-only resource. It must say "Open list" (or the Vietnamese equivalent) and show target ID. It must not pretend to select a particular record.
5. A missing/unknown target must have no invented link. A deleted or PBAC-denied target must be handled safely by the destination page and API.
6. Opening a target must not automatically mark the notification read. The explicit mark-read button must update the badge after a successful API response.
7. Log out or switch authenticated account while preview/list requests are in flight. No rows from the previous user may render.
8. Repeat in both Vietnamese/English, keyboard navigation and mobile width.

## Verification status before local application

PASS (static/source checks):

- Patch applies to the untouched source extracted from `erp_v1.8.0.zip`.
- `git diff --check` is clean in the build workspace.
- TypeScript syntax parse for changed TS files and new test files.
- Vue SFC script syntax parse and CSS parse.
- Allowlisted target mapping and adversarial invalid input checks.

NOT RUN in the packaging environment: project Vitest, Vue type-check, Vite production build and browser end-to-end checks, because the archive does not include `node_modules` and the offline environment cannot obtain the required pnpm dependencies. The project requires Node `^22.18.0 || >=24.12.0`, while the packaging environment has Node 22.16.0.

## Rollback

If patch has not been mixed with other frontend commits:

```powershell
cd D:\XAMPP\htdocs\erp
git apply --reverse --check .\DinkERP_NFX_A1_Notification_UX_v1.8.0.patch
git apply --reverse .\DinkERP_NFX_A1_Notification_UX_v1.8.0.patch
```

Rollback removes only this frontend change. It does not manipulate M11 queue rows, settings, consents, task scheduling or any DB tables.
