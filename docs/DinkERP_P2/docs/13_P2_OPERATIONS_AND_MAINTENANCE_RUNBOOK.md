# 13 — P2 Operations, Maintenance, Backup & Recovery Runbook

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Runtime boundary

P2 implementation does **not** exist in v1.9.0; commands and dashboard names below are proposed operational patterns, not working CI4 Spark commands. Current P1/M11 runbooks remain applicable to their respective modules but do not prove P2 runtime. Actual cron/queue provider choice, secrets and alerts must be approved with deployment design.

## 2. Daily operational health checklist

| Area | Signal/health question | Safe action |
|---|---|---|
| Inventory | Does sum posted movements equal materialized balance per Company/Warehouse/Product? | pause posting per affected scope; run read-only reconciliation, investigate source keys |
| Serial | Is one Serial present in only one Warehouse/state, with trace? | quarantine affected product, do not direct UPDATE serial/location |
| Reservation | Is Available = OnHand-Reserved and nonnegative for new reservations? | block new confirm if inconsistent, inspect race and partial consume history |
| Purchase | Are GR posted and PO received qty matching? | inspect posted source/serial, avoid re-posting |
| Marketplace | Inbox backlog, mapping failures, duplicate source keys and processing lag | fix mapping, replay with unique source key, no create bypass |
| Carrier | Booked labels missing tracking, 4xx/429/5xx, stale events | compare provider ref, retry only idempotent adapter command |
| COD | Unmatched shipments, expected vs actual, fee differences | compare settlement data, do not auto-post finance |
| Finance | Posted-only balance vs account opening, double receipt links | reconcile source postings, reversal if approved |
| Documents | Storage checksum/permissions, orphan versions | scan private store, owner link authorization audit |
| Authentication | CSRF/PBAC 401/403 anomaly, SQL scope regression | review audit IDs, never disable PBAC to restore service |

## 3. Reconciliation algorithms and evidence

- Inventory: group posted ledger by `(company,warehouse,product)` and compare expected OnHand to balances; compare active reservation remainder to Reserved. Read-only SQL on replica/clone first, no automatic production correction.
- Serial: cross-check latest posted serial movement with inventory_serials.current_company/warehouse/status; any mismatch blocks posting for affected serial.
- Purchase: sum posted GR and posted Return quantities vs PO received/returned; verify no PO over-receipt beyond approved tolerance.
- COD: per Shipment reconcile Due/Collected/fees/Net/Actual/Difference with carrier statement, receipt linkage and source unique IDs. Compare posted Money Receipt amount, not draft status.
- Finance: recompute each Account closing balance from OpeningBalance and posted transactions after OpeningDate by Company; compare report with query results; avoid full bank reconciliation assertions.

## 4. Incident response procedures

### 4.1 Duplicate external order / out-of-order webhook

1. Isolate account/channel and stop replay worker temporarily (do not stop existing read/UI).
2. Find inbox row by unique `(Channel, Account, ExternalOrderID)`, compare checksum, attempts, link and ERP Sales Order.
3. Check both target SO and its reservation/issue/Shipment before reprocess; if already posted, never repeat stock mutation.
4. Repair mapping or provider event normalization via approved command, not raw CREATE second order.
5. Reprocess one event with same key; verify uniqueness and no extra stock or finance rows.

### 4.2 GR / Issue posted twice suspected

1. Temporarily disable posting entry point for affected resource, preserve state and logs.
2. Query unique posting keys, source document IDs, ledger rows, balances and serials; avoid deleting rows.
3. Check whether second event is duplicate or legitimate partial receipt/issue.
4. If erroneous stock impact committed, create reviewed reversal/adjustment with source link and authorized actor; then reconcile all projections.
5. Add concurrency/idempotency regression case and run on DB clone before re-enable.

### 4.3 Incorrect Company / Supplier / Warehouse mapping

Quarantine affected documents, stop new mutation, compute blast radius across dependent docs, verify persisted owner and ancestor Company, use approved compensating business records when posted; never rewrite `company_id` on historical ledger with an UPDATE.

### 4.4 COD/finance difference or double receipt

Lock further money posting for affected source, locate provider payout statement + reconciliation and posted receipt links, compare amount/source/currency; if double-posted use approved reversal, keep both original and reversal audit; no deleting posted Money Receipt.

### 4.5 File storage corruption / unauthorized download

Suspend download/ingest as needed, preserve storage integrity hashes and access logs, revoke compromised signed URLs, restore versions from approved object backup to private storage, re-validate checksum before pointer restoration; investigate PBAC owner link.

## 5. Backup and recovery

- **DB:** consistent MariaDB backup with binlog/point-in-time strategy; verify restore on independent DEV clone; preserve external source keys, immutable posting/serial/correlation IDs, resource/action policies, workflow state and numbering counters.
- **Files:** private attachment binaries and metadata should be recovered together at compatible point; verify `content_sha256`, avoid accidental public exposure.
- **Secrets:** provider OAuth/HMAC keys, SMTP/Push keys and DB secrets outside ZIP/source, managed by deployment secret store; after restore rotate only as controlled change.
- **Application:** rebuild versioned source from SCM/artifacts, run migration preflight, API client version check; source rollback must remain schema/posted-data compatible.
- **Test:** run failure drills: DB crash at GR post, repeat webhook, double click confirm, partial issue, provider timeout after label created, lost attachment file, wrong company mapping.

## 6. Release/change hygiene

- Before each release: check P1 baseline regressions, P2 UR change IDs, migration diff and backups, PBAC seed diff, OpenAPI generated TS diff, workflow transitions and rollback plan.
- After: read-only health, one controlled canary case for new modules, compare stock and money projections, inspect retry queue and error budgets, capture evidence.
- **Do not** run ad hoc SQL to mark posted/confirmed or manipulate stock balances; operations must flow through authenticated canonical services.
