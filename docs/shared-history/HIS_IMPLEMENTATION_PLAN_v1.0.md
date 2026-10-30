# HIS-01 — Shared History Foundation Implementation & Gate Plan

> 2026-10-29 · v1.0.0 · selective milestone policy **PO APPROVED**, code **SOURCE CANDIDATE**, production **BLOCKED**.

## 1. Phạm vi thực hiện trước SUP

| Task | Kết quả nguồn (không nhầm với DEV acceptance) | Owner |
|---|---|---|
| HIS-T00 | Chốt HIS-POL-001; phân biệt System History / Supplier Transaction History; xác định trường redaction | Product/Security |
| HIS-T01 | Migration `000026` bảng/index/CREATE-once, no destructive down | Backend/DBA |
| HIS-T02 | `HistoryPolicy`, `HistoryRecorder`, transaction fail-closed và selective diff | Backend |
| HIS-T03 | HistoryReadService + HistoryParentViewPort và CustomerVisibility adapter ngoài Modules: SQL/PBAC in one snapshot, route extension manifest | Backend/Security |
| HIS-T04 | Hook CREATE/UPDATE/STATE_CHANGE trong CustomerService, không ghi Customer Interaction | Backend |
| HIS-T05 | Shared Vue panel Customer tab, i18n vi/en, paged read, error/empty | Frontend |
| HIS-T06 | Pure/source/route regression + OpenAPI candidate | QA/API |
| HIS-T07 | DEV MariaDB migration, SQL/HTTP two actors, rollback failure injection, browser UAT | DBA/Operator |
| HIS-T08 | Production hardening: DB append-only, retention, audit access, backup & release | Security/DBA |

**HIS-01 không code SUP, PUR/FIN, Supplier Bank hoặc Security Audit access.** Đây là Pilot Customer để kiểm chứng foundation trước SUP-T00.

## 2. Gate MOD-G0…G7

| Gate | Requirement | Trạng thái ban đầu |
|---|---|---|
| G0 Business | HIS-POL-001 approved; các ngoại lệ bank/view và retention được nêu rõ | Product **APPROVED**; Security retention **OPEN** |
| G1 Data/PBAC | Migration/index, parent-view SQL contract, negative review, bank exclusion | Source implemented; DEV DDL/EXPLAIN **NOT_RUN** |
| G2 API/UI | Endpoint + typed response + canonical OpenAPI merge, code generation check | Design candidate; generated-contract check **NOT_RUN** |
| G3 Code/Unit | Pure/source/routes, CUS/P1 offline, Vue type-check/lint/build | Run locally and record evidence |
| G4 DB/performance | DEV backup migration, EXPLAIN COUNT/page/detail and failure injection | **NOT_RUN** |
| G5 HTTP/PBAC | Session, direct ID A/B, denied state, COUNT leak, CUS rollback/race | **NOT_RUN** |
| G6 Browser | Customer view tab History, DRAFT edits, state/ACTIVE, empty/no permission, vi/en | Operator **NOT_RUN** |
| G7 Release | Patch apply/reverse, DDL rollback safeguards, DB hardening/retention, signoff | **BLOCKED** |

## 3. SUP dependency after HIS-01

1. HIS policy/store/recorder accepted on DEV. SUP-T00 maps Supplier's **operational state codes** and bank sensitive policy (DO NOT infer from Lifecycle initial).
2. SUP-T01 schema must not create `supplier_history`. SUP CRUD/Company Usage/Workflow call recorder inside the same physical transaction; if history fails rollback Supplier.
3. SUP-T Bank writes call `sensitiveChange`, redact all values. Revealing full bank needs a **distinct Security Audit** mechanism before bank endpoint is enabled; no silent use of configuration audit.
4. Only after SUP canonical PBAC + SQL ScopeProvider is reviewed, add `/api/v1/history/suppliers/{id}` and extend fixture manifest + UI.
5. Supplier Transaction History `UR-SUP-002` remains dependent on PUR/INV/FIN, **not** HIS foundation. No fake transaction data and no change to SUP 12 approved decisions.

## 4. Work on Windows

- Source baseline: `erp_v2.0.0_customer_required_phone_fixed.zip` + SUP v1.0.1 documents where installed, migration applied on DEV through `000025` per user's logs.
- Apply incremental HIS-01 Git patch only once; check `git apply --check` before applying, and `git diff --check` after.
- Back up DEV database and pause all Customer writes. Confirm `php spark migrate:status`; ensure **only reviewed migrations** pending, then `php spark migrate`; verify migration 000026.
- `backend/tools/test-unit.ps1 RUN HIS-UNIT`, `CUS-UNIT`, `UNIT-STANDARD`, `ROUTES`, `P1-STANDARDS`; frontend `pnpm run type-check`, `pnpm run build`, `pnpm run lint`, API contract checks. Follow QA runbook in this folder.
- Migration is **not** rolled back with `git apply -R`. Data-bearing History table should remain; forward-fix after production data.

## 5. Open technical items (not PO reapproval)

- MariaDB DB-account/trigger/grant-level append-only (HIS-G7 security BLOCKER).
- Revisit precision of approved checkpoint vs redacted fields when legal/tamper-proof reconstruction is required.
- Secure Security Audit for sensitive **reads**; HIS-01 only business mutation events.
- Retention/archive duration and jurisdiction-specific requirements before production.
- Custom operational Lifecycle statuses mapping and test before setting new status live.
- Native MariaDB UAT, runtime auth negative test and two-process concurrency evidence (no fabricated PASS).
