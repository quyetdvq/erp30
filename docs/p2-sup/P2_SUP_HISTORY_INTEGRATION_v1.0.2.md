# P2-SUP v1.0.2 — Shared History Integration Overlay

> **Ngày:** 2026-10-29 · **Trạng thái:** PRODUCT APPROVED integration direction, implementation pending.
> **Áp dụng sau:** `P2_SUP_TECHNICAL_DESIGN_v1.0.1.md`, `P2_SUP_IMPLEMENTATION_PLAN_v1.0.1.md`, `P2_SUP_DESIGN_DECISIONS_v1.0.1.md`.
> **Authority:** 12/12 business decisions from approved SUP-G1 retained. This document does not change any baseline UR/AC.

## 1. Chức năng nào được thêm?

Bổ sung **Shared Business History** (foundation HIS) cho Supplier **theo phạm vi change/audit vận hành**, tương tự Customer. Không bổ sung Supplier Portal, Supplier Performance, nhiều tài khoản ngân hàng, bảng giá mua hay nghiệp vụ PUR/FIN. Chức năng này là **infrastructure audit** để thực thi thay đổi một cách có trách nhiệm, không phải thêm nhóm chức năng SUP mới.

**Chú ý DEC-09:** `Supplier History` trong `UR-SUP-002` là **lịch sử chứng từ PUR/FIN**, vẫn deferred. `business_history_events` là lịch sử **thay đổi hồ sơ**; không thay thế Supplier Transaction History, không được ghi giả PO/GR/Payment.

## 2. Bất biến của SUP

- `SupplierService::create` ghi `CREATE` một lần, trong transaction sau khi insert/number/Company Usage/Contacts thành công.
- `SupplierService::update` và `replaceCompanyUsage` khi ở operational state ghi 1 `UPDATE` có diff whitelist; mọi sửa DRAFT thông thường không thêm row.
- `SupplierService::setState` ghi `STATE_CHANGE` cho mỗi transition thành công, checkpoint đã che khi vào operational phase. Nếu Workflow được mở rộng để có action `approve/submit/reject`, chỉ event kiểu tương ứng khi action nguồn thực sự tồn tại.
- `SupplierBankService` phải gọi `sensitiveChange()` khi set/replace/remove account kể cả DRAFT, **không ghi bank number, ciphertext, key, last4**. `view/reveal` vẫn cần Security Audit riêng và quyền `supplier.view_bank`.
- New bank access events chỉ được bật sau khi hoàn tất security contract; chưa được coi SUP bank readiness PASS do History foundation.
- `supplier.view` và canonical PBAC state/owner gate đọc master History; khi hiển thị `SENSITIVE_CHANGE`, phải review thêm quyền metadata trước khi mở endpoint; không dùng quyền History để mở dữ liệu bank.
- `operationalStatuses` của Supplier phải được xác nhận từ **quy tắc khả dụng mua hàng** chứ không từ `initialState()` của Lifecycle; chốt trong SUP-T00. Các code `DRAFT/PENDING/APPROVED` là ví dụ, không tự công nhận ACTIVE.

## 3. Phân kỳ

| Task | Phụ thuộc | Trạng thái |
|---|---|---|
| HIS-01 Foundation + Customer pilot | CUS hiện tại/migration 000025 | Source candidate, DB/HTTP pending |
| SUP-T00 Technical Preflight | HIS architecture, SUP v1.0.1, actual DB/crypto/Workflow | Pending |
| SUP-T01 Migration/Resources/Numbering | SUP-T00 + HIS DB readiness | Pending |
| SUP-G1 Service/UI | SUP-T01 + HistoryRecorder/Supplier visibility adapter | Pending |
| SUP Bank sensitive reads | Security Audit durable access logging, crypto and PBAC | **BLOCKED** until security review |
| SUP Transaction History UR-SUP-002 | PUR/INV/FIN real source data | **DEFERRED** |

**No Supplier source code is delivered in HIS-01 patch.** When SUP code is implemented, extend `HistorySql` parent whitelist and `HistoryReadService` using *Supplier's own reviewed SQL ScopeProvider*, plus explicit approved route extension manifest entry and frontend tab. Do not weaken P1/CUS PBAC with universal history queries.

## 4. HIS-02 monitoring/display dependency (không đổi SUP Scope)

HIS-02 bổ sung actor `username (full_name)`, ngày giờ theo common formatter và màn hình System `/system/history` được bảo vệ bởi quyền `business_history_monitor.view`. Khi SUP được nối `HistoryRecorder` theo SUP-T00/G1, các event SUP **đã ghi** sẽ tự xuất hiện trên màn hình giám sát (theo policy redaction). Không cho phép giám sát đọc thêm bank data nhạy cảm, không nhầm với lịch sử chứng từ PUR/FIN.

Không bắt buộc SUP tự triển khai cơ chế đọc monitor riêng. `HIS-02` phải qua DB/HTTP/PBAC UAT riêng; quy tắc Organization context cho Group MASTER xem tại `docs/shared-history/HIS02_ACTOR_FORMAT_MONITORING_DESIGN_v1.0.md`.
