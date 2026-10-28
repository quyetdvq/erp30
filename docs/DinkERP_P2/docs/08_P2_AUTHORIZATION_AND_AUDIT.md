# 08 — P2 Authorization, Company Scope, Audit & Threat Model

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Baseline PBAC semantics (P1 normative)

Canonical chain: `Shield User → Employee mapping → Employee Assignment(s) → Effective Profile(Base Permission union) → Resource.Action → Policy Rule → final authorization`. System Admin is **not** a bypass. Authentication status must permit business operation. Direct permission addition is allow-only; Policy does not create Base Permission. 

**PBAC precedence** per lane: `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`. `NO_MATCH => ALLOW` only after Base Permission. Policy inheritance along exact GROUP→...→Evaluation Organization scope path; propagate applicable ABSOLUTE_DENY into every lane before denied-set intersection. Assignment from sibling branch or different Company does not apply. No row-instance authorization or arbitrary amount-condition engine in P1.

**Action guard**: It is not enough to have `view` for `edit/export/approve/post`. Server must separately validate legal Lifecycle transition, Company ownership, active refs and idempotency. Frontend menu/row action are hints, not authority.

## 2. P2 Resource.Action registry proposal

**State: P2-DRAFT**, not currently registered by P1 ResourceActionSeeder. Existing P1 `price_list` used for extended price item contexts is `P1-EXISTS`, but any new action must be reviewed. Proposed resource action assignments follow:

| P2 Resource | Actions suggested by proposed API | Business owner/context |
|---|---|---|
| `carrier_account` | `create`, `edit`, `setstate`, `view` | Company integration config |
| `cod_reconciliation` | `create`, `edit`, `reconcile`, `view` | Shipment Company; owner Company / Group scope per canonical resource |
| `customer` | `create`, `edit`, `setstate`, `view` | Group master; Group master; scoped history by company; owner Company / Group scope per canonical resource |
| `document` | `create`, `edit`, `setstate`, `view` | owner Company / Group scope per canonical resource; owner-based resource; owner-based resource, polymorphic |
| `goods_receipt` | `cancel`, `create`, `edit`, `post`, `view` | persisted Company+Warehouse |
| `integration_webhook` | `receive` | resource/owner authoritative; webhook special signed ingress |
| `inventory_adjustment` | `cancel`, `create`, `edit`, `post`, `view` | persisted Company+Warehouse |
| `inventory_balance` | `view` | owner Company / Group scope per canonical resource; persisted Company+Warehouse |
| `inventory_movement` | `view` | persisted Company+Warehouse |
| `inventory_reservation` | `view` | persisted Company+Warehouse |
| `inventory_serial` | `view` | persisted Company+Warehouse |
| `marketplace_account` | `create`, `edit`, `setstate`, `view` | Company mapping in persisted target |
| `marketplace_sync` | `reprocess`, `view` | source Shop/Account Company mapping; worker auth open decision |
| `money_account` | `create`, `edit`, `setstate`, `view` | persisted Company owner |
| `money_category` | `create`, `edit`, `setstate`, `view` | Group catalog; finance type |
| `money_expense` | `create`, `edit`, `post`, `reverse`, `view` | persisted Company/account |
| `money_receipt` | `create`, `edit`, `post`, `reverse`, `view` | persisted Company/account |
| `money_report` | `view` | owner Company / Group scope per canonical resource |
| `price_list` | `edit`, `view` | owner Company / Group scope per canonical resource |
| `promotion` | `create`, `edit`, `setstate`, `view` | Group/Company applicability to be approved; applicable promotion scope; owner Company / Group scope per ... |
| `purchase_order` | `approve`, `cancel`, `create`, `edit`, `reject`, `submit`, `view` | owner Company / Group scope per canonical resource; persisted Company owner |
| `purchase_request` | `approve`, `cancel`, `create`, `edit`, `reject`, `submit`, `view` | persisted Company owner |
| `purchase_return` | `cancel`, `create`, `edit`, `post`, `view` | persisted Company+Warehouse |
| `sales_channel` | `create`, `edit`, `setstate`, `view` | Group catalog |
| `sales_issue` | `create`, `edit`, `post`, `view` | Shipment Company |
| `sales_order` | `cancel`, `create`, `edit`, `setstate`, `submit`, `view` | owner Company / Group scope per canonical resource; persisted Company+Warehouse |
| `sales_return` | `cancel`, `create`, `edit`, `post`, `view` | Sales Order/Shipment Company |
| `shipment` | `cancel`, `create`, `edit`, `post`, `view` | owner Company / Group scope per canonical resource; sales order Company |
| `stock_count` | `approve`, `cancel`, `create`, `edit`, `submit`, `view` | persisted Company+Warehouse |
| `supplier` | `create`, `edit`, `setstate`, `view` | Group master; scoped history by company; owner Company / Group scope per canonical resource |
| `warehouse_transfer` | `cancel`, `create`, `edit`, `post`, `view` | same Company source+destination warehouses |

`P2-DRAFT` action additions needing explicit approval include e.g. `marketplace_sync.reprocess`, `cod_reconciliation.reconcile`, `integration_webhook.receive`. For `SalesOrder.confirm` API proposal, suggested P1-style permission is `sales_order.setstate`; do not create a new `confirm` resource action without reviewing approved catalog semantics. If an action is a service-only automation, its identity/authorization boundary must also be approved — do not infer a System Admin superuser or service-account bypass.

## 3. Evaluation Organization rules by operation

| Operation | Evaluation Organization derive from | Reject |
|---|---|---|
| Customer/Supplier create/edit | Group shared master scope GROUP; Company usage list as secondary validation | client Company override of Group master permission |
| Contact for Customer/Supplier | owner master authoritative; Group/Company context per owner-specific design | owner type spoofing, unauthenticated polymorphic record |
| Document link/download | linked owner persisted target; verify caller view on owner and document | leaked file through generic attachment ID |
| Promotion / extended Price List | price_list persisted `organization_id`, Group or Company; promotion applicability scope approved | product/UOM mismatch, wrong Company masquerade |
| Purchase Request/Order/GR/Return | create payload owner Company, existing persisted document Company | Warehouse in another Company; Supplier unusable |
| Inventory balance/movement/reservation | persisted Company/Warehouse, from source business doc | count/list query without authorized SQL Company predicate |
| Sales Order/Issue/Return | Sales Order persisted Company; Issue via Shipment/SO | client-controlled evaluation org on existing ID |
| Marketplace Account | account Company+Warehouse effective mapping | cross-company shop use, overlap mapping |
| Shipment/Carrier/COD | Shipment→persisted Sales Order Company | service callback changing owner |
| Money receipt/expense | persisted money document Company+Account; create via Company owner | Account Company mismatch, source link leak |

**All-company report** = aggregate only subset of Companies actor is authorized to view, not necessarily every Company in business Group.

## 4. SQL-scope BEFORE pagination/aggregation

```sql
-- PSEUDOCODE, not executable SQL, emphasizes required order.
SELECT x.*
FROM <p2_company_owned_table> x
WHERE x.company_id IN (<actor_authorized_company_ids_from_PBAC>)
  AND <validated filters>
ORDER BY <allowlisted column> ASC, x.id ASC
LIMIT :limit OFFSET :offset;
```

Never interpolate raw order-by/sort into SQL. Count and data queries must use the **same scoped predicate**. If exact owner is a Warehouse Organization, resolve Company by canonical ancestor path. Existing target must not rely on `company_id` supplied by client on `GET /{id}`.

## 5. Data protection and audit

- **At rest:** vendor credentials encrypted via approved secrets store; database row only credential reference. Do not include raw supplier bank account, customer address or full marketplace webhook in debug logs, response errors, analytics or exception traces.
- **Attachments:** check owner permission on every download; forbid public URL leakage, unsafe MIME/path traversal; upload quotas/retention policy is open decision.
- **Auditable actions:** create/edit/setstate, submit/approve/reject, GR Issue/Transfer/Return posting, reservation consume/release, marketplace replay, carrier label/cancel, COD reconcile, financial post/reversal. Capture actor, Company, source, action, request id, timestamp, reason and old/new state where appropriate. No secret values.
- **Immutable history:** stock movement, serial trace, money posting, carrier provider interaction, marketplace inbox and document version cannot be erased just because master becomes inactive.
- **Service-to-service:** provider callbacks authenticate at ingress, validate replay, store inbox only. Background business mutation must use separately approved identity/authorization execution design — a service cannot gain blanket permission by hardcoding privileged username.
- **CSRF:** any session-based mutation/HTTP POST uses P1 CSRF/session protection; webhook with signature is a different trust boundary.

## 6. Negative security test matrix

| Test ID | Scenario | Expected |
|---|---|---|
| P2-SEC-01 | User without Base Permission but has matching ALLOW Policy | DENY |
| P2-SEC-02 | ABSOLUTE_DENY at Group with allow at child Department | DENY |
| P2-SEC-03 | Assignment at Company A attempts Company B stock | DENY / zero rows |
| P2-SEC-04 | Existing Order Company A + client evaluation org Company B | 403/409; unchanged persisted Company |
| P2-SEC-05 | `view` granted, `export` absent | Export forbidden |
| P2-SEC-06 | `view` granted, `post` absent | Posting forbidden, no stock/finance change |
| P2-SEC-07 | Direct download document ID without owner access | Deny even if attachment ID guessed |
| P2-SEC-08 | P1 System Admin user without required resource permission | Deny as regular user |
| P2-SEC-09 | Replay provider webhook with bad signature/stale event | No durable business mutation |
| P2-SEC-10 | P2 report query filters after offset/limit | Test fails; must scope first |
| P2-SEC-11 | Supplier bank or provider secret appears in error payload | Test fails; redact |
| P2-SEC-12 | Draft sales transaction uses disabled Company/Warehouse | Reject business operation |

## 7. Resource/Action seeding governance

Do not repurpose P1 `ResourceActionSeeder` entries; make an additive and repeatable P2 seeder with semantic Resource Types and explicit action list, including domain `MASTER/ORGANIZATION/SYSTEM` determined by authority. Duplicates safe; existing domain/status conflict must BLOCK, not silently rewrite. Then verify `GroupPermission`/effective profile, Policy restrictions and actor assignment ancestor paths on DEV clone. Seed ownership, not seed root bypass.
