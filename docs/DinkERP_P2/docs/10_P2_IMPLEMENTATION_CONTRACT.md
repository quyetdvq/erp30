# 10 — P2 Implementation Architecture & Development Contract

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Baseline components to reuse; never fork P1 business authorities

- Backend: CodeIgniter 4 `backend/app/Modules/{Access,Contact,Identity,Import,Locations,Numbering,Organization,People,Pricing,Product,Settings,Workflow}`. P1 migrations preserve physical semantics; edit/add through additive migration.
- Frontend: Vue 3 + TypeScript + Pinia, `frontend/src/features`, `frontend/src/services`, `frontend/src/router`, `frontend/src/i18n` and `frontend/src/api/generated`. Generated client derives from source-controlled OpenAPI.
- MariaDB/InnoDB, resource/action catalog and PBAC evaluation via existing Access service. No new identity, policy or permission table.
- M11 Notifications exists and can be integrated by **approved** domain events, but lack of email/push delivery must not rollback posting or allow duplicate business event replay. P2 cannot rely on M11 as an implicit order/warehouse state engine.

## 2. Proposed backend module structure (P2-DRAFT)

```text
backend/app/Modules/
  Document/{Controller,Application,Domain,Persistence,Storage}/
  Customer/{Controller,Application,Domain,Persistence}/
  Supplier/{Controller,Application,Domain,Persistence}/
  Promotions/{Controller,Application,Domain,Persistence}/
  Purchase/{Requests,Orders,Receipts,Returns}/
  Inventory/{Ledger,Balances,Serials,Reservation,Transfer,Counts,Adjustment}/
  Sales/{Orders,Pricing,Issues,Returns}/
  Integrations/{Marketplace,Carrier,Webhook,Retries}/
  Finance/{Accounts,Categories,Transactions,Reports}/
```

Folder names are suggestions; actual module naming must align architecture review. Every HTTP endpoint routes to a Controller that validates & delegates to Application Service; Domain rules and Persistence are testable separately. Do not create one mega-service doing Sales+Inventory+Finance side effects without explicit transaction boundary.

## 3. Responsibilities and ownership

| Module | Commands it owns | Reads / event dependencies | Must NOT own |
|---|---|---|---|
| Document | Version upload, link/unlink, authorized download | owner resource permission and identity | owner-specific bypass of PBAC |
| Customer/Supplier | master mutations, usage scope, notes | P1 Contact adapter, Organization | copy per-Company master identities |
| Promotions/Pricing | contextual internal prices and promotion evaluation | P1 price list/product/Company time | recompute marketplace external price |
| Purchase | PR/PO, GR and Purchase Return lifecycle/post command | Inventory posting command, supplier, product/UOM | direct write inventory balance outside Inventory boundary |
| Inventory | single source ledger, reservation, serial, transfer, adjustment | Product leaf, Organization, source transaction | treating shipment delivery as implicit issue |
| Sales | SO/COMBO origin/price, confirm, Sales Issue/Return | Pricing, Inventory reserve/post commands | carrier credential handling or unrestricted money post |
| Integrations | inbox/webhook/reprocess, provider adapters | Sales command under approved technical principal | PBAC bypass / direct business table mutation |
| Finance | accounts, categories, money documents/post/reverse, balance report | COD links, source documents | General Ledger / automatically book settlement |

## 4. Application transaction boundaries

**All business mutation paths (UI/API/import/worker)** must call same canonical service and validation; never let import/background bypass rules.

```text
COMMAND PostSalesIssue(actor, issueId, idempotencyKey):
  authenticate(actor); load issue+shipment+salesOrder from persisted owner
  authorize(actor, 'sales_issue.post', evaluationOrganization=sourceCompany)
  require legal Transition(DRAFT, 'post', POSTED)
  DB.transaction:
    lock issue for update; if posted with same key => return prior result
    lock reservations/stock rows in canonical order
    validate remaining qty, serial presence/location, warehouse Company
    insert UNIQUE movement rows for each SIMPLE/CHILD leaf
    atomically consume reservation and update balances/serial locations
    set SalesIssue POSTED, posted_by/at, append immutable audit
  COMMIT
  emit outbox event only if approved (never resend original posting)
```

For `PostGoodsReceipt`: positive movement + PO cumulative received in same transaction. For `Transfer`: two signed movements same Company in one transaction. For MoneyReceipt/Expense: amount>0, Account same Company; source POSTED only once; reversal is separate signed adjustment/event, not `UPDATE amount`.

**Source preflight:** implement feature flags for initially disabled actions only as technical gate, not privilege escalation; all migrations must be forward-compatible with P1 reads during rollout.

## 5. API-first contract / revision discipline

1. Review normative 30 UR + 147 AC and Open Decisions for module.
2. Draft concrete Resource/Action, state transitions, DTO input/output, validation errors, SQL scope, indexes and idempotency keys.
3. Approve additive migration plan and OpenAPI diff; use named operationIds, schema constraints, examples and error taxonomy.
4. Generate TS client and frontend services; type-check, OpenAPI lint and `openapi:generate:check`.
5. Implement in small, reversible commits: schema + persistence + domain rules + app service + controller/routes + API integration tests → frontend → E2E.
6. Record cross-module effect ownership (Purchase calls Inventory command, never mutates balance ad hoc).

## 6. Domain contracts / invariants by layer

| Layer | Required guard | Evidence |
|---|---|---|
| HTTP Controller | session/CSRF, body types, machine code, limit/offset allowlists | endpoint negative tests |
| Application Service | authoritative owner context, final PBAC, lifecycle, idempotency | service integration tests |
| Domain | Product Role/UOM, pricing snapshot, amounts, serial/location, reservation algebra | pure unit tests/property tests |
| Persistence | unique business keys, FK+Company invariants in code, row lock/order, transaction rollback | MariaDB concurrency tests |
| Integration adapter | signature, rate-limit, PII masking, provider timeout/retry | vendor fixtures and sandbox tests |
| UI/Client | vi/en, decimal string, loading/conflict, permission hints | component/E2E tests |
| Operations | audit, reconciliation, backup, replay safety and detection | runbook drill and smoke |

## 7. Migration, seeding & data ports

- P1 22 migrations are immutable. Create P2 migrations after current version; code table naming uses unprefixed logical names and respects CI4 database prefix setting.
- Build schema in dependency order: catalog→master→header/lines→ledger/reservation/serial→integration links→finance; use indexes supporting Company-scoped filters and idempotency.
- Seed `resources/resource_actions` additively. Do not rewrite existing resource domains or status silently; seed workflow states/lifecycles only after state graph approved; numbering series explicit per Resource Type.
- Never migrate production without preflight live schema and backup; test read/write compatibility on DEV clone, then controlled production deploy. No destructive down migrations as routine rollback for posted transactions.

## 8. Decimal, datetime, and money policy

- API decimal values strings, no JS floating arithmetic for money; store Decimal precision consistent with approved MariaDB schema. Proposal `DECIMAL(18,2)` money and `DECIMAL(18,6)` quantity requires approval.
- Product/UOM conversion to base quantity must preserve sufficient precision; posting must never overissue due to representation.
- COMBO total selling amount conserved after rounding; deterministic residual/tie-break, same leaf weighted by amount/qty, not average unit price.
- Business DATE from Company timezone; presentation Display timezone cannot change evaluation instant, pricing, numbering reset, date ownership.

## 9. Performance and maintainability

- SQL scope joins and `ORDER BY` allowlists inside repository, not controller concatenated strings; indexes for `(company, warehouse, product)`, `(status,time)`, unique `(channel,account,externalOrderId)`, source posting keys.
- Cursor/offset strategy per route to be approved; stable total count from authorized subset; large inventory ledger and webhook history must have bounded retention/archiving policy.
- Common outbox/retry adapter can be optional; must not be introduced as unapproved transactional authority. Logs/correlation link to source ID and actor, never raw PII/credentials.
- For every new module include `MODULE.md` with resources, owned tables, events, route files, contracts and tests; deprecation/migration note for future P3.
