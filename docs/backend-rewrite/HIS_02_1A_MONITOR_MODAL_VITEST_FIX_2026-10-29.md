# HIS-02.1A — Standard History Monitor modal Vitest harness correction

- **Date:** 2026-10-29
- **Parent:** HIS-02.1 Standard View & Modal; patch applies after `DinkERP_HIS02_1_System_History_Standard_View_Modal.patch`.
- **Scope:** Test harness only. No production frontend, PHP, SQL, routes, migrations or PBAC changes.

## Reported failure and root cause

1. The focus-return assertion compared `document.activeElement` to the VIEW eye button, but the test mounted `HistoryMonitorView` in an **unconnected** Vue Test Utils container. An unconnected button cannot receive browser focus and `TableDetailModal` intentionally restores focus only to a connected element. The test also dispatched a synthetic click without focusing the trigger first.
2. The reload test triggered `click` on the form submit button. `@vue/test-utils` synthetic `trigger('click')` does not necessarily produce the browser's native `submit` default action. As a result, `@submit.prevent="apply"` did not execute, and the modal remained open in the test despite `load()` correctly setting `selected.value = null`.

## Fix

- Mount the view using `attachTo: host`, with `host` appended to `document.body`. Always unmount and remove host in `afterEach`.
- Explicitly focus the eye button before simulating activation. Assert the initial focus, Escape close, close-button close and restored focus.
- Simulate a `submit` event on the form itself; confirm this reloads data (`monitorBusinessHistory` called twice) and closes the modal.

## Intended acceptance

Run on a Windows checkout with dependencies installed:

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm exec vitest run src/features/history/__tests__/historyMonitorModal.spec.ts
node --test scripts/his02-1-monitor-modal.contract.test.cjs
pnpm run type-check
pnpm run check:imports
pnpm run build
```

The Vitest suite should now report **4/4 PASS** (previously 1/3 PASS). The source-only contract suite should remain **3/3 PASS**. No database migration or new backend deployment is required.

**QA status:** Needs a real Vitest run on the user's Windows environment. Do not claim Vitest passed based only on source inspection or static assertions.
