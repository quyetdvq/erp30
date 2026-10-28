# 09 — P2 Marketplace, Carrier, Webhook, Retry and Idempotency Architecture

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Scope and provider-adapter boundary

P2 explicitly includes Shopee and TikTok Shop order ingestion and status sync, marketplace account mapping/idempotency, carriers for quotes/labels/tracking/cancellation/delivery/COD, and COD reconciliation. Exact provider API endpoints, webhook payload, HMAC signing and OAuth permissions are **not** specified by source UR and are not asserted as verified. Do not invent provider-specific URL, callback secrets, signatures, TTL, rate-limit guarantees or settlement formulas.

The following is a robust P2-DRAFT adapter design and an approval checklist; each provider needs a separate signed integration contract/fixture.

## 2. Marketplace account and external order identity

**Canonical unique business key (BASELINE-UR):** `Sales Channel + Shop/Account + External Order ID`. Each effective Shop/Account maps one Company and a fulfillment Warehouse (or approved explicit Warehouse mapping), BEFORE ERP Sales Order creation. Account Company cannot be inferred from external buyer address or selected UI Company filter.

```text
ShopperOrderRaw(provider, shop, externalOrderId)
 -> Verify provider signature and timestamp/nonces (provider-specific)
 -> Canonicalize provider identity and safe schema
 -> Durable inbox with UNIQUE(channel, account, orderId)
 -> Resolve approved account→Company+Warehouse at event effective time
 -> Validate SKU/Product mapping; missing mapping -> retained failed state
 -> Create/link ERP SO idempotently with EXTERNAL_CHANNEL external price snapshot
 -> Return only existing/new ERP order identity; safe retries
```

**Two identical parallel notifications:** one inbox business identity, one sales_order link, at most one ERP SO. **Different shop/account with same external_order_id:** allowed as separate business identity. **One external order with changed payload:** do not blindly overwrite prices/qty; version/event processing policy and conflict rules require provider-specific approval.

## 3. Event intake versus business mutation authorization

External HTTP webhooks should perform signature/nonce verification, deduplication and durable inbox recording only. They must not automatically bypass PBAC or call business creation under privileged root/System Admin. Define approved system integration principal/technical authorization pipeline and actor attribution before turning on worker processing. P1 v1.0.15 says service-account business access requires additive design decision.

## 4. Marketplace source price & state

- Store source order ID, account/channel identity, external unit price/discount, original line mapping, Price Source `EXTERNAL_CHANNEL`; these are authoritative in marketplace Sales Order.
- ERP internal Price List/Promotion is **not** invoked to overwrite marketplace price. Source amounts should survive data correction/replay with immutable/audited source event log.
- Mapping failures must persist codes and allow authorized retry; unknown SKU/Company/Warehouse must result in **no ERP order**.
- Provider update statuses may drive module lifecycle only through explicit allowed mappings; do not infer that `DELIVERED` means inventory issue/finance receipt/return already posted.

## 5. Carrier integration adapter contract

| Command/query | Inputs | Outputs & audit | Failure approach |
|---|---|---|---|
| `QuoteRate` | Company/warehouse, delivery address, parcel weight/dimensions | amount/currency, provider method, timestamp | cache policy TBD, quote not shipment |
| `CreateLabel` | approved shipment, service, recipient, COD | provider shipment ID, tracking, label ref | provider idempotency key or reconcile before retry |
| `GetTracking` | carrier account, provider shipment ID | list events, latest mapped status | store raw event safe digest, process dedup/out-of-order |
| `CancelLabel` | shipment, provider reference | provider cancel result | never delete ERP shipment or reverse stock implicitly |
| `GetCodSettlement` | provider/account/time window | collected, fees, payout ref, net | reconciled against shipment, not auto-post finance |

External response data must be mapped to provider-independent internal DTO; unknown provider status preserved as `UNKNOWN`/unmapped and flagged for review instead of guessed `DELIVERED`.

## 6. COD reconciliation versus finance

Per Shipment: COD Due, Collected by Carrier, Shipping Fee, Other Fee, expected Net Transfer, Actual Received, Difference and Status. Formula `net_due = carrier_collected - shipping_fee - other_fee` is **proposed**, subject to payer/fee allocation terms in provider contracts; do not freeze until approved.

A COD reconciliation `MATCHED` may create/link a Money Receipt as user-approved action; Money Receipt POSTED is what changes Cash/Bank Account operational balance. A single carrier settlement may cover multiple Shipments: P2 UR does not specify the allocation mechanism, so settlement-to-shipment cardinality is an OPEN decision. Retain proof of source settlement IDs and retry dedup.

## 7. Retry, backoff, dedup and observability

- `event_uid/provider`, `payload_sha256`, `received_at`, processing status and attempt history; distinguish transient 429/5xx/timeout vs permanent invalid mapping/schema/signature.
- Bounded exponential backoff + jitter, documented max attempts/dead-letter queue, manual authorized reprocess; policies require approval and must not cause duplicate Sales Order, Label, Stock Issue or Money Receipt.
- Outbox recommended for write-then-notify sync events; if integration fails after committed business transaction, retry side effect only, never rerun original stock posting.
- Keep audit/correlation: provider, account, external business key, ERP source ID, attempt/result, logical error code, sanitized payload digest. Never log access token, full credentials, customer PII or raw card/bank information.
- Monitor ingestion lag, mapping failures, rate limit, stale outstanding COD, repeated event rejects, missing tracking, unposted Sales Issues and reconciliation differences. Health probe should show `UNKNOWN` when telemetry absent rather than silent green.

## 8. Provider acceptance fixtures required before implementation (OPEN)

For each **Shopee** and **TikTok Shop**: approved versioned event fixtures for new order, cancel, paid, partial order, repeat event, signature bad, stale timestamp, unknown SKU, unmapped Shop, shop rebind, provider error, out-of-order update, webhook delivery after API ingestion. For each Carrier: rate, label success/timeout-success ambiguity, tracking out-of-order, cancel after label, delivered/cancelled conflict, COD split settlement and fee variance.

## 9. Integration security and emergency procedures

1. Compromised webhook secret: disable ingress, rotate secret, reject stale signatures, preserve inbox for reprocessing; no direct SQL delete of ERP SO.
2. Provider outage: stop replay worker or throttle; business transactions remain consistent, no posting twice.
3. Wrong Shop→Company mapping: suspend account, isolate affected orders, investigate exact source keys; correcting persisted orders requires approved source-preserving repair with audit, not bulk UPDATE Company IDs.
4. Duplicate delivery / retry confusion: query inbox and linkage unique keys first, reconcile before issuing another command.
5. COD discrepancy: quarantine unsettled batch, compare provider payout data and posted Money Receipts; never insert compensating money silently.
