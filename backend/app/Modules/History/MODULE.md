# History — Shared Business Audit Trail (HIS-01 / HIS-02.1)

- **Owner:** `App\Modules\History`; captures reviewed business milestones and operational changes. This is a shared foundation, not automatically a DB audit for every ERP Resource.
- **Writes:** `HistoryRecorder` runs only inside the calling domain service's real DB transaction; failure aborts the business write. No public mutation endpoint.
- **Current writer integration:** `App\Modules\Customer\CustomerService` (create, operational edit, Workflow transition). `HistoryPolicy` reserves both `customer` and `supplier` and explicitly approves their field redaction maps, but Supplier is **not implemented/wired yet**.
- **Selective policy:** one CREATE; no ordinary DRAFT update events; reviewed state transitions, operational updates and sensitive change metadata. No plaintext bank account, contact phone/email/address, secrets or arbitrary JSON in events.
- **Parent-scoped read:** `GET /api/v1/history/customers/{id}` checks the persisted Customer's canonical VIEW permission through `HistoryParentViewPort` / the composition-root Customer adapter. History has no direct dependency on Customer.
- **SYSTEM monitor:** `GET /api/v1/history/monitor` reads only bounded pages/time windows after explicit `business_history_monitor.view` SYSTEM PBAC approval. It is a privileged cross-Group auditor view, **not** a generic per-user read of every resource; the UI is `/system/history` with standard view icon and detail modal. It lists events **actually recorded**, not all database mutations.
- **Future onboarding:** for each Resource, review the event allowlist/redaction, operational-state mapping, owner domain/Company, Workflow and transaction hooks, visibility adapter and regression checks before enabling collection or parent-scoped reads. Do not infer Company authority from the current Group MASTER policy.
- **Other audits:** `configuration_audit_events`, `authorization_logs`, bank reveal Security Audit and financial/transaction histories remain separate.
- **Pending:** Supplier parent adapter (SUP-G1), bank-account access security events, DB write privilege hardening, audited DEV HTTP/MariaDB UAT and negative permission tests.
- **Tests:** `backend/tools/test-unit.ps1 RUN HIS-UNIT`, `RUN UNIT-STANDARD`, `RUN ROUTES`, `RUN CUS-UNIT`; UI: `node --test frontend/scripts/his02-1-monitor-modal.contract.test.cjs` and `pnpm exec vitest run src/features/history/__tests__/historyMonitorModal.spec.ts`.


## HIS-03 (2026-10-29) — DEV-only breaking event format
- `changes_json` uses only compact two-item `[before,after]` tuples or JSON `{}` redacted marker. No legacy decoding; old DEV history must be explicitly cleared by DBA per `docs/shared-history/HIS_03_COMPACT_CHANGES_AND_OBJECT_MODAL_2026-10-29.md`.
- History SYSTEM monitor enriches current Customer code/name via a typed one-to-one page SQL JOIN; `COUNT` remains on History alone. Supplier is future-only.
- Canonical Customer modal receives `customerId` from History view; standard Customer API rechecks persisted VIEW. No History-owned Customer duplicate modal.
