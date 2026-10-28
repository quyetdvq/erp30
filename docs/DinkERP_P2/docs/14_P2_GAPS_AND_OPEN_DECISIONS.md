# 14 — P2 Source Gap Audit & Open Decision Register

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Đã kiểm tra những gì

**Source requirements** `00_User_Requirements_v1.0.15.md`: 83 UR = P1 40, P2 30, P3 13. P2 30 UR (`29 MUST`, `1 SHOULD`) có 147 acceptance bullet. UR của P2 đã được tách nguyên văn; FRS map mỗi criterion tới một test ID. P2 pricing extension vẫn được giữ theo P1 UR-PRC-001, P2 imports chỉ explicit enable.

**Source code** `erp_v1.9.0.zip`: backend CI4 Modules P1 + Notifications, Vue frontend, 22 CodeIgniter migrations and P1 OpenAPI 169 operations (syntax matches existing route declarations in prior audit). Chưa thấy P2 implementation modules `Customer`, `Supplier`, `Purchase`, `Inventory`, `Sales`, `Finance`; không được xem `P2-API-*` và proposed table là đã deploy.

**Source documentation**: ZIP chứa bộ kiến trúc/QA của P1 và M11; không có một tập **approved FRS/DB/API/UI/Implementation Plan P2 standalone** đáp ứng đủ 30 UR và 147 AC. Vì vậy bộ hiện tại là **reconstructed P2 requirements + full proposed downstream design**, chưa phải downstream approved baseline. Những chi tiết mà User Requirements không nêu đã được log OPEN chứ không tự phê duyệt.

## 2. Review outcomes / finding matrix

| Finding | Severity | Finding | Resolution in this package | Remaining action |
|---|---|---|---|---|
| P2-F001 | High | P2 UR rải ở nhiều chương, xen P1/P3 | 30 nguyên văn P2 UR vào doc 01; 147 tests | Product Owner verify extraction |
| P2-F002 | High | P2 kế thừa nhiều P1 invariant không được lặp trong UR P2 | P1 contract doc 02 and module docs | Confirm no P1 change |
| P2-F003 | High | Không có approved downstream DB/API/UI P2 trong source | 62 entity + 187 proposed API op + 31 UI screen draft | Approve architecture/G0 |
| P2-F004 | Critical | External provider worker/Service Account authorization undefined by P1 | signed inbox/no bypass + explicit decision | Security/Product approval |
| P2-F005 | Critical | Stock quantity, serial, reservations, posting concurrency sensitive | Ledger & idempotency architecture/QA | MariaDB runtime concurrency proof |
| P2-F006 | High | COMBO purchase cost not defined; selling allocation explicitly not reusable | Block unsupported path until approved | Purchase cost ADR |
| P2-F007 | High | Pricing specificity P2, Promotion conflicts missing exact persistence/algorithm | Proposed normalized context/rules + decision | Pricing/Promotion ADR |
| P2-F008 | Critical | Marketplace idempotency, COD→Finance could double count | durable inbox unique + explicit Money post | Provider and DB acceptance |
| P2-F009 | High | P2 import targets not specified | No default new import actions | Approve target per Resource |
| P2-F010 | High | Production DB/certificate/SMTP/provider credentials absent from ZIP | Environment/runbook gate, no live claim | DEV clone runtime |

## 3. Decision register (not approved)

Each decision has a proposed resolution (non-binding), owner suggestion and blocking Gate. `OPEN` must be changed to `APPROVED`, `REJECTED`, or `DEFERRED-WAIVER` via signed change control; document recommendation is **not** sign-off.

| ID | Severity | Gate | Domain | Decision needed | Proposed approach |
|---|---|---|---|---|---|
| `P2-DEC-001` | BLOCKER | G0 | IAM | P2 service/webhook worker authorization is not part of P1 service-account baseline | Use signed ingress to inbox, explicitly approved technical principal / policy for business processing |
| `P2-DEC-002` | BLOCKER | G0 | IAM | Whether new P2 resource actions/domains and special actions are allowed | Approve per-resource action matrix and effective Organization context before route exposure |
| `P2-DEC-003` | BLOCKER | G0 | API | P2 error envelope, API action body, CAS and idempotency header final shape | Freeze contract with current P1 error schema and exact mutation semantics |
| `P2-DEC-004` | BLOCKER | G0 | DB | Monetary/quantity scale and rounding/tax allocation policy | Recommend DECIMAL(18,2) money, DECIMAL(18,6) qty; approved deterministic rounding function |
| `P2-DEC-005` | BLOCKER | G1 | DOC | Document storage class, MIME/size/AV/checksum and retention | Private file store + immutable version hash, scan, owner permission |
| `P2-DEC-006` | BLOCKER | G1 | DOC | Polymorphic Document authorization and unlink/delete policy | Authoritative owner PBAC check each upload/download; no public key |
| `P2-DEC-007` | BLOCKER | G1 | CUS | Customer Groups catalog/scope/codes for P2 Pricing | Group-shared customer_groups master; exact canonical ID and status rules |
| `P2-DEC-008` | REVIEW | G1 | CUS | Customer Company Usage effective-dated vs current snapshot | Current All Companies or explicit current allowlist; preserve historic transactions |
| `P2-DEC-009` | BLOCKER | G1 | CUS | Customer Contact owner support in existing Contact capability | Extend P1 polymorphic owner with CUSTOMER and source-authority resolver |
| `P2-DEC-010` | REVIEW | G1 | SUP | Supplier group and payment term definitions | Approved catalog and validation; do not create AP ledger |
| `P2-DEC-011` | BLOCKER | G1 | SUP | Supplier Contact owner support, bank field protection | Extend owner with SUPPLIER, choose bank info encryption and access |
| `P2-DEC-012` | BLOCKER | G3 | PRC | Price List Customer/Group/Channel specificity and NULL tie-break | Company before Group; within scope Customer>Group>Channel then priority/time/id; null canonical |
| `P2-DEC-013` | BLOCKER | G3 | PRC | Promotion combinability, conflict equal priority and coupon usage | Reject overlapping undetermined candidates on activation; snapshot applied promo |
| `P2-DEC-014` | BLOCKER | G3 | PRC | Special Price, discount, tax, gifts and returns proration | Approved calculation policy and residual/tax roundtrip test |
| `P2-DEC-015` | BLOCKER | G2 | PUR | PO lifecycle, single-step approvals and edit/freeze points | Separate workflow state from PBAC, explicit allowed-from/to matrix |
| `P2-DEC-016` | BLOCKER | G2 | PUR | Purchase COMBO flatten leaf purchase cost/price behavior | Block COMBO purchase until approved leaf purchase cost algorithm |
| `P2-DEC-017` | BLOCKER | G2 | PUR | GR partial tolerance, over-receipt, PO close and return limits | Default no over-receipt unless approved; approved correction/reversal strategy |
| `P2-DEC-018` | BLOCKER | G2 | INV | Opening inventory and historical stock migration method | Approved opening inventory document/posting with full trace |
| `P2-DEC-019` | BLOCKER | G2 | INV | Negative stock handling on adjustments and initial stock | New reservation cannot make available negative; stock movement policy separate |
| `P2-DEC-020` | BLOCKER | G2 | INV | Serial status machine/normalization and correction | Unique(product,serial), one warehouse, append-only trace |
| `P2-DEC-021` | BLOCKER | G2 | INV | Stock Count snapshot consistency under concurrent movement | Version/stock lock/recount procedure, no silent post stale difference |
| `P2-DEC-022` | BLOCKER | G3 | INV | Reservation identity and partial shipment/return linkage | Reserve each leaf order line, consume per Issue, release remainder |
| `P2-DEC-023` | BLOCKER | G3 | SAL | Sales Order confirmation/cancellation exact transition graph | Confirmed triggers reserve; POST Issue triggers stock decrease |
| `P2-DEC-024` | BLOCKER | G4 | SAL | Shipment partial fulfillment and issue sequencing/status reconciliation | Shipment before Issue in canonical flow; issue per shipment partial lines |
| `P2-DEC-025` | BLOCKER | G4 | SAL | Marketplace account effective mapping overlaps and shop credential storage | At most one Company at effective time, explicit Warehouse; secrets external |
| `P2-DEC-026` | BLOCKER | G4 | SAL | Shopee/TikTok current provider API contract and signature/version fixtures | Obtain approved sandbox samples; never assume provider payload fields |
| `P2-DEC-027` | BLOCKER | G4 | SAL | Provider external price/discount corrections and cancellation mapping | Snapshot original external price; audited event version strategy |
| `P2-DEC-028` | BLOCKER | G4 | SAL | Carrier label idempotency, returns of ambiguous timeouts and status taxonomy | Idempotent label request key and inspect-before-retry provider lookup |
| `P2-DEC-029` | BLOCKER | G4 | SAL | COD payout many Shipments and fee treatment/rounding | Provider statement ID + settlement allocation, recon net/difference formula |
| `P2-DEC-030` | BLOCKER | G4 | FIN | Money account opening balance changes and backdate rules | Opening immutable after post, correction as audited adjustment |
| `P2-DEC-031` | BLOCKER | G4 | FIN | Money Posted reversal, negative balances and refund mapping | Reversal linked to source and Company, no destructive posted update |
| `P2-DEC-032` | REVIEW | G4 | FIN | Bank account field encryption/redaction policy | Restrict by Resource.Action and approved secret field representation |
| `P2-DEC-033` | BLOCKER | G5 | ALL | P2 target resources that may be imported | Require new signed per-resource import target/template/action and per-row PBAC |
| `P2-DEC-034` | REVIEW | G5 | ALL | Event retention, audit trail archival, RPO/RTO backup SLAs | Approve retention, restore drills, monitoring thresholds per resource |
| `P2-DEC-035` | REVIEW | G1 | ALL | UI locale translations and date formatting locale requirements | Follow existing vi/en, Display presentation-only |

## 4. Pre-implementation sign-off procedure

1. Product Owner confirms no content omitted/altered in 30 P2 UR and locks success criteria/priority.
2. Architecture Lead signs Company, PBAC/service worker, data ownership and transaction boundaries.
3. DBA signs schema fields/types/keys/indexes + migration/preflight/retention; replaces placeholder SQL design with final executable migrations.
4. API/Frontend Leads sign request/response schemas, action names, status machine, generated client and UI flows.
5. Integration Lead signs actual provider API/event fixtures, secrets, mapping/retry and COD payout handling.
6. QA signs a mapping 147 source AC to executable tests and test data; Operations signs backup, monitoring and incident recovery.

**G0 exit blocker:** Source requirements alone do not determine provider payload/signature, worker PBAC, exact P2 numeric/costing/rounding or lifecycle state map. These cannot be silently considered approved by publishing this documentation.
