# HIS-02.1 — System History Monitor: standardized view/detail UX

**Status:** frontend source update (DEV browser UAT pending) · **Scope:** `/system/history` only · **Base:** ERP v2.0.0 HIS-02 release with Customer pilot.

## Request and implementation

1. Replace text `Xem/View` actions with shared `TableRowActions` view (eye icon), in effective mode, **only** after the backend monitor SQL list returned the VIEW-authorized page. Require `business_history_monitor.view` base permission; backend continues to check the SYSTEM PBAC action and policy.
2. Replace inline detail panel below the list with the existing shared `TableDetailModal` in a `Teleport to="body"`. It includes header, closing button, Esc handler, focus trap/return focus, and responsive text wrapping. Display actor `username (Full Name)`, formatted time using existing System/Organization/User formatter, event, resource, state transition, action, request ID, redacted changes and checkpoint indicator.
3. Opening/closing a detail is client-side presentation of a **redacted, authorized monitor page row**; no new arbitrary-ID read endpoint. Changes to filters, pagination or user clear the selection. No history payload schema or permissions modified.
4. Add `vi/en` labels for action, changes and checkpoint; no new database migration or backend route.

## Clarifying the shared History scope

- **Shared architecture** is intended for future Resources across ERP; **automatic coverage is not** provided by the framework.
- **Actually wired** (HIS-02 codebase): `CustomerService` calls `HistoryRecorder` for create/update/workflow transition. Frontend `Customer` detail uses the `/history/customers/{id}` parent-view scoped API. The SYSTEM monitor shows events from onboarded resources only.
- **Supplier** is reserved in `HistoryPolicy` and monitor filter, but **there is no Supplier module recorder yet**. Add it during SUP-G1 (separate change with bank audit decision).
- **Other P1/P2 Resources** (Product, Organization, Employee, Contact, etc.) are not automatically captured. Each needs an approved per-resource field redaction matrix, operational-state mapping, ownership/domain policy, parent-view adapter for non-monitor users, transaction integration and tests. A new Company/ORGANIZATION owner must not reuse the present Group MASTER authority blindly.
- `configuration_audit_events`, `authorization_logs`, Security Audit for bank-account reveal and transactional histories (PO/GR/FIN) remain separate; History is not a generic DB-change capture or a substitute for those ledgers.

## DEV acceptance checklist

- [ ] With a user holding `business_history_monitor.view` and approved SYSTEM policy, list shows one eye icon per authorized event and no text `Xem` row button; click opens modal rather than inline panel.
- [ ] Modal is outside the table scroll container; close with X, footer Close and Escape; focus returns to the triggering eye icon.
- [ ] Long request ID/diff wraps on desktop and mobile; no modal/page horizontal overflow.
- [ ] Actor appears `username (Full Name)`, date/time follows display preferences, and protected fields remain redacted.
- [ ] Changing filter/page/permission closes the previous modal; no duplicate extra API request for event details.
- [ ] Non-authorized user cannot view monitor page/API; System History remains monitor-only unless a resource has a separate approved parent-view adapter.

**Source verification:** run `node --test frontend/scripts/his02-1-monitor-modal.contract.test.cjs`, `pnpm exec vitest run src/features/history/__tests__/historyMonitorModal.spec.ts`, `pnpm run type-check`, `pnpm run check:imports`, `pnpm run build` on the Windows frontend. Offline/source PASS is not HTTP/MariaDB/BROWSER sign-off.
