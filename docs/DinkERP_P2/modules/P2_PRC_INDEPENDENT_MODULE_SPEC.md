# P2 Module PRC — Promotion & Extended Pricing (Functional + Technical Specification)

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Scope & mandatory requirements (verbatim UR)

Các phần dưới đây trích nguyên văn để file module **tự đọc và nghiệm thu độc lập**. Nội dung domain design từ mục 2 trở đi được đề xuất và cần phê duyệt.

### UR-PRC-002 - Promotion
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Hỗ trợ promotion cơ bản:
- Giảm %
- Giảm số tiền
- Giá đặc biệt (Special Price)
- Quantity-based discount
- Tặng Product
- Coupon

Mỗi Promotion có tối thiểu:
- Effective Period
- Priority
- Eligibility/condition
- `Combinable = true/false`

Baseline **không cho stacking tự do**. Thứ tự xử lý canonical:
1. Xác định Special Price đủ điều kiện khi có.
2. Xử lý Discount/Promotion theo Priority.
3. Coupon chỉ được kết hợp nếu rule và `Combinable` cho phép.

**Acceptance criteria**
- Promotion chỉ áp dụng trong thời gian hiệu lực và khi đủ điều kiện.
- Sales Order lưu được promotion/coupon đã áp dụng.
- Special Price được xử lý trước Discount/Promotion khác.
- Promotion được evaluate theo Priority và không được tạo stacking tùy ý ngoài rule đã cấu hình.
- Promotion có `Combinable=false` không được kết hợp với Promotion/Coupon khác trái với rule.
- Nếu các Promotion cùng Priority và scope có thể tạo kết quả không xác định, cấu hình phải bị từ chối hoặc được điều chỉnh trước khi active.


---


## 2. Design-level functional decomposition (P2-DRAFT)

## Domain behavior

1. **Không làm lại Pricing P1**: Product+UOM+Company+EffectiveTime vẫn là invariant. P2 có thể thêm **Customer specific → Customer Group → Sales Channel** theo scope; Company matching item vẫn thắng Group matching item, sau đó exact UOM default. Giá external marketplace không được ERP resolve lại.
2. P2 Price List context `customer_id/customer_group_id/sales_channel_id` là cách model proposed, **không có trong P1**. Điều kiện price candidate và cách tie-break NULL wildcard/overlap, precedence group vs channel phải freeze để không nondeterministic.
3. Promotion types: `%`, fixed amount, Special Price, quantity-based discount, gift Product, coupon. Mỗi offer có period, priority, eligibility, combinable. Order: Special Price đủ điều kiện → Discount/Promotion theo Priority → Coupon khi rule/combinable cho phép. **Không stacking tùy ý**.
4. Nếu hai offer có cùng Priority và scope mà không chứng minh deterministic winner, từ chối active/config hoặc resolve bằng approved explicit rule. Không âm amount, không phân bổ residual sai; no recalculation on EXTERNAL_CHANNEL order.
5. Price and promo apply on direct/internal Sales Order; persist list ID/source, coupon and promotion IDs, rates/amount snapshots, root COMBO allocated leaf amounts. COMBO allocation vẫn dùng leaf default base-UOM weight, không dùng leaf Price List để tính weight.

## Example deterministic resolution

Given Company C Price List item for Product P+UOM U and Context Customer X, the match wins over Group scope even if Group has higher numeric priority (Company level already chosen). Within same company scope, choose specificity then priority/effective_from/id (specificity algorithm P2 needs freeze). If Company item missing exact P+UOM, fallback matching Group then product default UOM.

## Commands/queries

- `P2PriceResolver.resolve(Product,UOM,Company,Time,Customer?,CustomerGroup?,Channel?)`: read-only consistent with P1 + approved P2 context.
- `PromotionEvaluator.evaluate(OrderDraft,Time)`: stable candidate set, combinability, discount/gift snapshot, no arbitrary executable conditions.

## Negative tests

Duplicate equal priority overlap; root COMBO nested PriceList not resolved; zero total allocation weight -> fail; 2 discounts + non-combinable -> no stacking; out-of-period price/promo -> reject; marketplace EXTERNAL_CHANNEL unchanged.

## Decisions

Scope and persistence of `customer_groups`/`sales_channels`, promo tax treatment, source order discount reconciliation, quantity promotion return proration, deterministic tie-break and coupon multi-use semantics.

## 3. Proposed database entities (P2-DRAFT)

| Entity | Purpose | Important consistency constraint |
|---|---|---|
| `product_price_list_item_contexts` | Explicit P2 pricing criteria on P1 items | INDEX(price_list_item_id,customer_id,customer_group_id,sales_channel_id) |
| `promotions` | Promotion master | UNIQUE(promo_code); INDEX(status,effective_from,effective_to,priority) |
| `promotion_rules` | Explicit eligible targets and rule values | INDEX(promotion_id) |
| `coupons` | Coupon issuance and usage policy | UNIQUE(coupon_code); CHECK used_count<=max_uses if max_uses not null |
| `sales_order_promotions` | Promotion/coupon application snapshot | INDEX(sales_order_id) |

## 4. Proposed REST API operations (P2-DRAFT)

| Method | Path | Candidate Resource.Action |
|---|---|---|
| GET | `/api/v1/promotions` | `promotion.view` |
| GET | `/api/v1/promotions/{id}` | `promotion.view` |
| POST | `/api/v1/promotions` | `promotion.create` |
| PATCH | `/api/v1/promotions/{id}` | `promotion.edit` |
| POST | `/api/v1/promotions/{id}/actions/set-state` | `promotion.setstate` |
| GET | `/api/v1/coupons` | `promotion.view` |
| GET | `/api/v1/coupons/{id}` | `promotion.view` |
| POST | `/api/v1/coupons` | `promotion.create` |
| PATCH | `/api/v1/coupons/{id}` | `promotion.edit` |
| POST | `/api/v1/coupons/{id}/actions/set-state` | `promotion.setstate` |
| GET | `/api/v1/sales-channels` | `sales_channel.view` |
| GET | `/api/v1/sales-channels/{id}` | `sales_channel.view` |
| POST | `/api/v1/sales-channels` | `sales_channel.create` |
| PATCH | `/api/v1/sales-channels/{id}` | `sales_channel.edit` |
| POST | `/api/v1/sales-channels/{id}/actions/set-state` | `sales_channel.setstate` |
| GET | `/api/v1/price-lists/{id}/items/{itemId}/contexts` | `price_list.view` |
| PUT | `/api/v1/price-lists/{id}/items/{itemId}/contexts` | `price_list.edit` |
| POST | `/api/v1/pricing/resolve-p2` | `price_list.view` |
| POST | `/api/v1/promotions/evaluate` | `promotion.view` |

## 5. Proposed frontend screens (P2-DRAFT)

| UI ID | Navigation | Screen behavior |
|---|---|---|
| `P2-UI-PRC-01` | `/promotions` | Type, priority, effective dates, combinability, eligibility, simulation |
| `P2-UI-PRC-02` | `/pricing/contexts` | Customer/Group/Channel price-specificity preview |

## 6. Complete acceptance registry (BASELINE criteria)

| FR | Acceptance criterion (source wording) | Test ID |
|---|---|---|
| `P2-FR-PRC-002-01` | Promotion chỉ áp dụng trong thời gian hiệu lực và khi đủ điều kiện. | `P2-AT-PRC-002-01` |
| `P2-FR-PRC-002-02` | Sales Order lưu được promotion/coupon đã áp dụng. | `P2-AT-PRC-002-02` |
| `P2-FR-PRC-002-03` | Special Price được xử lý trước Discount/Promotion khác. | `P2-AT-PRC-002-03` |
| `P2-FR-PRC-002-04` | Promotion được evaluate theo Priority và không được tạo stacking tùy ý ngoài rule đã cấu hình. | `P2-AT-PRC-002-04` |
| `P2-FR-PRC-002-05` | Promotion có `Combinable=false` không được kết hợp với Promotion/Coupon khác trái với rule. | `P2-AT-PRC-002-05` |
| `P2-FR-PRC-002-06` | Nếu các Promotion cùng Priority và scope có thể tạo kết quả không xác định, cấu hình phải bị từ chối hoặc được điều chỉnh trước khi active. | `P2-AT-PRC-002-06` |

## 7. Domain test/approval handoff

- Trước thiết kế DB/API/UI: freeze OPEN-DECISION liên quan, lifecycle, permission, Company authority, immutable posting semantics.
- Trước code: review migration forward-only, tests negative/cross-company/idempotency/rollback và consistency với các P1 services.
- Trước release: chạy runtime trên DEV clone (CI4 + MariaDB + frontend), log test IDs và proof; không nhận PASS chỉ vì có file tài liệu hoặc route.

**State:** toàn bộ đề xuất DB/API/FE của module chưa tồn tại như nghiệp vụ P2 trong v1.9.0. Requirement P2 gốc trong §1 vẫn `BASELINE`.
