# HIS-02 Source QA — 2026-10-29

## Implemented

- GET `/api/v1/history/monitor`, protected by isolated canonical SYSTEM resource `business_history_monitor.view`, explicit read-only provisioning command, no implicit grants.
- Backend actor JOIN (page only), username/full_name, UTC ISO `Z`, Customer History and Monitor via shared `HistoryEventPresenter`.
- UI System → Giám sát thay đổi; filter UTC window/resource/event/actor, redacted details; Admin audit scope gated.
- Display Settings automatically loads SYSTEM runtime at AppShell authentication; User Preferences override. Organization override applies only with an explicit trusted org context.
- Indexed monitoring query, bounded 90-day range, page_size <=50 and page<=1000; HIS-01 policy unchanged.

## Offline evidence

Source tests: HIS-01 39/39 policy, 19/19 recorder, 21/21 SQL, 24/24 contract; HIS-02 34/34 monitor SQL, 9/9 presenter, 31/31 source; P1 Routes 476/476; architecture source 21/21; independent M3.4D gates 6/6; 182 recognized routes; frozen 180 maintained. Remaining tests recorded in deliverable QA manifest, if run.

## Not yet accepted

- Code is a **source/offline candidate**; frontend type-check/build, DEV MariaDB migration, no-leak authenticated HTTP/PBAC, real EXPLAIN and browser UAT not performed in this delivery.
- Monitoring all *recorded events* is not the same as History already integrated into every module. Currently Customer pilot is connected; Supplier and others remain to be wired.
- Audit data retention, database-level append-only guarantees and Security Audit of bank reveal need DBA/Security signoff before production.
