# HIS-01 — Source QA & Handoff (2026-10-29)

> **Verdict:** SOURCE/OFFLINE PASS; **DEV DB, authenticated HTTP and browser UAT NOT RUN**; production release **BLOCKED** until security and runtime gates.

## 1. Product scope and selective policy

Approved `HIS-POL-001`: one CREATE marker per resource; suppress ordinary DRAFT/PENDING profile updates; record STATE_CHANGE and explicit Workflow decisions; record changed allowlisted fields when operational (`ACTIVE` currently explicitly mapped); record sensitive bank mutation metadata even if DRAFT. A single profile update writes at most one diff event, not one event per field. No raw bank details, arbitrary user payloads or PII copied into history. Historical rows are not backfilled.

## 2. Implemented files and ownership

- `backend/app/Database/Migrations/2026-10-29-000026_CreateBusinessHistoryEvents.php`: additive polymorphic table, indexes and DB-enforced one CREATE per resource. Data-preserving rollback guard.
- `backend/app/Modules/History/`: shared `HistoryPolicy`, `HistoryRecorder`, `HistorySql`, `HistoryReadService`, `HistoryParentViewPort`, owner MDR. No direct Customer/Supplier dependency.
- `backend/app/Bootstrap/CustomerHistoryVisibility.php` + `backend/app/Controllers/Api/BusinessHistoryController.php`: composition root and canonical Customer SQL-first PBAC. Parent-check and History COUNT/PAGE in one REPEATABLE READ transaction; no generic list / mutation endpoint.
- CustomerService records CREATE after persistence and Contacts, UPDATE only for operational changed allowed fields, STATE_CHANGE after persisted Workflow/notification, **before transaction commit**; History insert failure aborts mutation.
- One approved additive GET `api/v1/history/customers/(:num)` with fixed route extension manifest, one Customer History tab and i18n vi/en.
- `contracts/openapi/p2-his.design.openapi.yaml`: additive **candidate**; canonical OpenAPI integration and generated client verification still pending.
- Supplier overlay `docs/p2-sup/P2_SUP_HISTORY_INTEGRATION_v1.0.2.md`, **without** changing previously approved 12 SUP business decisions or treating `UR-SUP-002` transaction history as delivered.

## 3. Test evidence from source snapshot

| Gate | Actual outcome | Limits |
|---|---|---|
| HIS-01 HistoryPolicy | 39/39 PASS | PHP pure |
| HIS-01 Recorder fixture | 19/19 PASS | DB mocked; no InnoDB proof |
| HIS-01 SQL plan | 21/21 PASS | No live EXPLAIN |
| HIS-01 Source Contract | 24/24 PASS | Static checks, not runtime |
| Customer Core / Source / SQL / Workflow | 94/94, 94/94, 48/48, 51/51 PASS | Offline |
| Contact Workflow | 25/25 PASS | Offline |
| Full legacy offline suite | 168/168 PASS | No database |
| Module graph | 6/6 PASS | Cycle resolved through visibility port |
| Routes architecture | 474/474 PASS | 180 frozen unchanged + one authorized extension |
| Route inventory | PASS (181 registrations) | Static only |
| P1 SQL/PBAC scope suite | All source scripts PASS | No live database |
| PHP syntax / Git whitespace | PASS | Source-only |
| Vue TypeScript / production build | **NOT RUN** | No frontend node_modules available here |
| CI4 Spark / MariaDB migration, real rollback, HTTP/PBAC two actors | **NOT RUN** | Requires user's DEV clone and session |
| Bank reveal Security Audit and DB privilege-level append-only | **OPEN / RELEASE BLOCKER** | Must not claim done |
| Retention/archive policy and canonical OpenAPI generated client | **OPEN** | Must resolve before release |

## 4. Deployment blocking checks

1. Backup DEV clone and pause Customer writes; check `php spark migrate:status`, confirm the pending migration belongs to HIS-01 and no surprise migrations; run `php spark migrate` before new Customer business writes.
2. Verify physical table prefix and index, EXPLAIN COUNT/PAGE on representative volumes, actual InnoDB rollback if recorder insert fails, uniqueness of CREATE under concurrency.
3. Operator UAT in frontend: DRAFT create (one CREATE), many draft edits (no UPDATE), state transition (one STATE_CHANGE), ACTIVE edit (one redacted UPDATE), no-op update (zero), and negative PBAC for actor with no Customer VIEW (no leaked rows or total).
4. Run `pnpm run type-check`, `pnpm run build`, `pnpm run lint`, `pnpm run check:imports` and OpenAPI checks on Windows.
5. Do NOT treat this source candidate as a fully released shared history for all modules. Supplier port and bank access audit remain tasks in next phase.

Use [HIS DEV QA Runbook](HIS_DEV_QA_RUNBOOK_v1.0.md) for commands and safe rollback.
