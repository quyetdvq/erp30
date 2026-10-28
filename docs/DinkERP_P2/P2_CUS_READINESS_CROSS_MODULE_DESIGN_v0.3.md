# DinkERP P2-CUS — Readiness Audit & Cross-Module Contract

**Document ID:** P2-CUS-XMOD-001  
**Version:** 0.3 — 2026-10-28  
**Status:** **DRAFT / NOT APPROVED / NOT READY FOR FULL IMPLEMENTATION**  
**Source authority:** `00_User_Requirements_v1.0.15.md` (UR-CUS-001/002; UR-SAL-001/005/006/007; UR-FIN-001/004/005; UR-CORE-001/002; P1 PBAC, Contact, Pricing/COMBO); ZIP `erp_v1.9.0.zip` as P1 closed source.  
**Related prior proposals:** `P2_CUS_MINIMAL_DESIGN_PROPOSAL_v0.1.md`, `P2_CUS_CONTACT_SHIPPING_ADDRESS_DESIGN_v0.2.md`, `DinkERP_P2_Independent_Documents_v1.0.0` (current downstream is still DRAFT).  
**Purpose:** Make CUS implementable as a small owner of Customer data while locking the *design contract* for dependent Sales/Finance reporting now, before those modules are coded. This document does not amend approved User Requirements and does not mean any schema/API or new business rule has been approved.

## 1. Decision: Is CUS implementation-ready?

**No, not in full.** The two existing baseline URs have 8 acceptance criteria covering Customer Master, Company Usage, Contacts, notes and authorized history, but do **not** define operational sales figures, debt/receivables, payments allocation, channel payment ownership, revenue/return cutoffs or an API/reporting contract. The existing P2 downstream draft proposes four CUS tables and only a generic `/customers/{id}/history` route; the later v0.1/v0.2 minimal proposals reduce to three tables and reuse Contact as delivery addresses, but have not been synchronized into the overall P2 docs. Any cross-module number presented as a committed CUS feature now would be an invented specification.

Two separate gates are needed:

- **CUS-G1 CORE:** approved Customer CRUD, usage scope, multiple Contacts / shipping eligibility, Company-private interactions, access rules, tests. It may be delivered before Sales/Finance if the UI clearly marks their cards `NOT_AVAILABLE`, never zero.
- **CUS-XMOD CONTRACT:** approve interfaces, metrics and end-to-end acceptance now; **runtime integration/UAT** occurs when P2-SAL, P2-FIN and related modules exist. Do not claim `UR-CUS-002` end-to-end fully PASS without those source systems.

## 2. Scope and database minimization

**CUS-owned new tables (proposed):**

1. `customers`: one Group-shared master; unique code; name; `customer_type` (`INDIVIDUAL`/`ORGANIZATION`, proposal); controlled `customer_group_code`; optional phone/email/tax; primary/main address + Province/Ward; `all_companies`; status; actors/timestamps. *No shipping_* columns in this proposed model; preserve shipping functionality via Contacts after explicit PO sign-off that this satisfies the `UR-CUS-001` shipping-field requirement.
2. `customer_company_usages`: composite PK `(customer_id,company_organization_id)`; row points to existing Organization of type `COMPANY`, no duplicate Company master; no historical transaction mutation when usage list changes.
3. `customer_interactions`: append-only contact/CRM notes, owner `customer_id` + `company_organization_id` and actor/time. Status/decisions of Sales/Finance are **not** copied here.

**Reuse P1:** `contacts` with a new `CUSTOMER` owner resolver and a separate `customer_contact` resource; P1 `organizations`, `provinces`, `wards`, Identity/PBAC, Product/Pricing/Numbering. P1 Contact code currently allows only `ORGANIZATION` and `EMPLOYEE`; adding `CUSTOMER` is real code work with permission- and SQL-scope regression tests. Each shipping Contact must be active, belong to the Customer and carry valid recipient/phone/address/Province/Ward. Delivery address is *snapshotted* in Sales Order or the approved shipment contract, never dereferenced live for historical shipping labels.

**Do not create in CUS:** `customer_addresses`, `customer_sales`, `customer_revenue`, `customer_balance`, `customer_receivables`, `customer_payments`, `customer_history` materialized cache or separate Customer Contact storage. These are projections from transaction owners. No Customer Group table unless dynamic admin-managed groups are approved (the existing full P2 draft assumed one; the minimal draft does not).

**Source authority for every monetary fact:** Sales owns order/fulfillment/return amounts; Finance owns posted cash movements and payment allocations; COD/integrations own provider-collection and provider-settlement states; CUS only *reads, filters and displays* these authoritative facts.

## 3. Baseline versus requested extension

| Capability | What the original UR actually requires | Proposed additional P2-CUS contract |
|---|---|---|
| Customer master / company eligibility | UR-CUS-001 BASELINE | CUS owns and validates; P2-SAL revalidates on create |
| Multiple contacts/ship-to | Shared Contact reuse allowed; shipping fields listed in UR-CUS-001 | Reuse existing `contacts` and snapshot selected ship-to in SO; requires explicit equivalent-design sign-off |
| History of interactions, Sales Order, Shipment, Sales Return, Money Receipt | UR-CUS-002 BASELINE, only within user's permission scope | Unified read-only timeline filtered at source |
| Total sales, order value, actual sales, returns | Not defined as CUS metrics in the UR | NEW feature/metric definition; approval required |
| Customer outstanding balance, aging, overpayments, credit limit | Not specified as CUS or Finance baseline; **full AR ledger is excluded** | NEW lightweight operational receivable view may be approved; do not imply legal/statutory AR |
| Cash received | Money Receipt status POSTED is authority for operational cash balance (UR-FIN-001/004) | Customer-level total only where source Customer/order allocation is verified |
| COD carrier collections | UR-SAL-006 requires per-shipment reconciliation and can link Money Receipt | Distinguish customer paid to carrier from money carrier has remitted to Company |

**Change control:** Adding a customer operational receivable report requires a specific addendum/approved P2 `UR/CR` and acceptance tests; it must not silently redefine `UR-FIN-004`'s cash balance or introduce a full AR ledger that the baseline excludes. A legal receivables/invoice/tax ledger would need a separate approved scope, not this minimalist view.

## 4. Canonical dependency / data-owner contract

| CUS section | Authority module / source | Required join and contract | Timing |
|---|---|---|---|
| Master/profile, Contact locations | CUS + P1 Contact | `customers.id`; `contacts(contact_type='CUSTOMER', contact_ref_id=customer_id)` | G1 |
| Company eligibility | CUS + P1 Organizations | `all_companies` OR allowlist; Organization type `COMPANY` | G1 |
| Interaction history | CUS | `customer_interactions(customer_id, company_organization_id)` | G1 |
| Orders, booked value | SAL | `sales_orders.customer_id, company_id, status, confirmed_at, money totals` | SAL |
| Delivered/fulfilled sales value | SAL + INV | `sales_order_lines` + `shipment_lines` + delivered Shipment state + `sales_issues` POSTED; no double counting multiple Shipments | SAL/INV |
| Returns and commercial credits | SAL | `sales_returns` + return lines + explicit credit-approved amount/state, *separate from stock posting* | SAL |
| Customer money received / refunded | FIN | `money_transactions` POSTED, source-specific Customer/order allocation in `money_source_links`; Expense refund direction distinct | FIN |
| COD paid to carrier, carrier unsettled | SAL/COD | Per Shipment COD collected and settlement states, bound to original Sales Order | SAL/FIN |
| Source links / attachments | DOC / SAL / FIN | Link to the authoritative record only after its Resource.Action + Company check | Module availability |
| Pricing, promotion, channel | PRC/SAL | Realized order line price/discount snapshot; marketplace `EXTERNAL_CHANNEL` unchanged | PRC/SAL |

**Sales Orders may have `customer_id = NULL` in the approved UR.** Such orders cannot appear in a named Customer's totals until an explicit, authorized customer-link policy is approved; do not infer identity from telephone, address or marketplace alias. A Group-shared Customer can have transactions across multiple Companies, but each transaction has exactly one authoritative Company owner.

## 5. Vocabulary and metric definitions (PROPOSAL, not yet business-approved)

**Avoid the ambiguous single label “Tổng doanh số”.** Define separate, explicit metrics with source/status cutoffs:

| UI metric | Proposed semantics | Timing / exclusions |
|---|---|---|
| `confirmed_order_value` — Giá trị đơn xác nhận | Sum committed `sales_orders` frozen customer-payable snapshot for valid confirmed orders. Exclude unconfirmed/cancelled-without-fulfillment orders. | Confirmation event time, one order once; partial cancellation/refund policy must be fixed |
| `fulfilled_goods_value` — Giá trị hàng đã giao | Sum allocated net *goods* line value for fulfilled quantities, supported by posted Sales Issue + delivered Shipment. Partial delivery is proportional to frozen line amounts with deterministic rounding residual. | Exclude undelivered, unposted, duplicate issue/shipment events; exclude shipping fee and tax if product-only |
| `net_sales_operational` — Doanh số vận hành ròng | `fulfilled_goods_value - approved_return_sales_credit` (only sales price effects, not cash refund). | Not statutory revenue. The exact status authorizing commercial credit must be approved |
| `posted_cash_receipts` — Tiền đã thực thu | Posted Money Receipts verified as related to Customer; distinguish *allocated to order* from *unallocated*. | Real cash/account receipt; carrier COD collection alone is NOT posted Money Receipt |
| `receivable_balance` — Phải thu vận hành | Sum open, approved customer payable obligations LESS approved commercial credits LESS customer-level settled payments attributable to those obligations, PLUS posted cash refunds of previously allocated payments. | Per Company, per order; do not sum unrelated Money Receipts; see §6 |
| `past_due_amount` — Quá hạn | Sum positive outstanding of due-bearing obligations where `due_date < as_of_business_date`, per Company. | Requires an authoritative due date; unset due date = unclassified, not automatically past due |
| `unapplied_customer_credit` — Tiền nhận chưa phân bổ | Posted cash receipts/prepayments not yet allocated to any charge; never subtract from a specific order without an allocation | Separate bucket from positive order-level receivables |
| `cod_carrier_pending` — COD hãng đang giữ | Carrier-confirmed customer collections not yet remitted/settled to Company, net of reviewed fees under the COD contract | **Not** the Customer's unpaid balance and **not** cash in bank |

**Date conventions:** business DATE/timezone from the transaction's Company (P1), values `DECIMAL` not binary float. Use `as_of` for balances and `date_from/date_to` for period flows. Do **not** calculate a current receivable by applying the period filter to receipts alone; payments made before the displayed period must still reduce the as-of balance. Separate `order_period` filtering from cash flow period filtering if both are exposed.

**Amount policy to freeze in SAL:** product amount after discount and COMBO allocation; separately retain shipping fee, tax if adopted, promotion, externally authoritative discounts, original order total, cancelled/credited portions, and the portion the Customer is contractually obliged to pay. A COD fee charged to carrier must not silently change a Customer's invoice/order total. P1 COMBO rounding/aggregation must survive partial returns and reports.

## 6. Minimal operational receivable calculation and allocations

**Suggested scope for first implementation:** direct/internal **identified** Customer, postpaid transactions only, order-level operational balance; no invoice ledger, no interest, no tax-accounting AR. COD and marketplaces are separate, explicitly gated until their collection semantics are approved.

For each eligible Sales Order `o` at as-of `t`:

```
charge(o,t) = commercially_due_amount_for_fulfilled_order_as_of(t)
credit(o,t) = approved_commercial_return_or_order_adjustment_credit_as_of(t)
paid(o,t) = SUM(applied_amount of POSTED, non-reversed customer Money Receipts allocated to o up to t)
refunded(o,t) = SUM(applied_amount of POSTED, non-reversed Money Expense refunds tied to o up to t)
net_position(o,t) = charge(o,t) - credit(o,t) - paid(o,t) + refunded(o,t)
order_outstanding(o,t) = MAX(0, net_position(o,t))
order_overpayment(o,t) = MAX(0, -net_position(o,t))
```

**Customer headline:** display (a) SUM(order_outstanding), (b) SUM(order_overpayment) and unallocated deposits separately. A single net signed number is insufficient for collection and risk decisions. Finance can also display a reconciled total position, but must identify which credit can legally offset which charge. The formula is a proposal; handling prepayment, adjustments and voids is an approval blocker.

**Example — direct sale:** delivered/payable 12,000,000 VND; approved return credit 2,000,000 VND; valid posted customer receipt allocated to order 7,000,000 VND; no refund paid => **3,000,000 VND** outstanding. `12,000,000 − 2,000,000 − 7,000,000 = 3,000,000`. Simply subtracting all Company cash receipts, unrelated Customer receipts or carrier remittances is wrong.

**Payment allocation is mandatory if this report is promised.** Current P2 Finance draft includes a generic `money_source_links` table with nullable `linked_amount`, which is not by itself an approved debt-allocation contract. Before implementing debt reporting, approve explicit allocation semantics on **the existing proposed link table**, not a new CUS debt table:

- Every allocation has `money_transaction_id`, `source_type=SALES_ORDER` (or approved source), `source_id`, non-null positive `applied_amount`/`linked_amount`, consistent Company, source Customer and currency VND.
- A single posted receipt may allocate across several Sales Orders; one Sales Order may receive several receipts. `SUM(allocations for receipt) <= posted receipt amount` with transaction lock/row concurrency protection. Corrections use immutable reversal/corrective entries; no silent edit of posted cash source.
- Distinguish an **informational** `money_source_link` (trace only) from a **financial allocation**. Do not interpret every source reference as applied payment. Prefer a constrained link kind or a narrowly defined allocation subset in this table.
- Refund Money Expense must reference original Customer/Sales/Return and represent an *outflow*, not an extra receipt. Approved commercial return credits and cash refunds are distinct.
- Settlement through a carrier or marketplace **must never be counted twice**: carrier-confirmed collection can settle the Customer obligation, while later posted Money Receipt settles the *carrier's remittance*, not the Customer obligation a second time. This requires an explicit COD settlement/allocation rule; mark `COD debt` `NOT_AVAILABLE` until approved.

**Avoid premature tables:** The CUS schema remains three tables. Using existing draft `sales_orders`, `sales_returns`, `money_transactions`, `money_source_links`, `shipments`, `cod_reconciliations` may require **additional columns/constraints/statuses in SAL/FIN**, not new CUS tables. Proposed additions: Sales frozen payable total, per-order `due_date` (if aging), authoritative accepted-credit amount with event timing, typed posted receipt/refund allocation and explicit payment-channel state. These are *new downstream design decisions*, not actual P1 fields.

## 7. API design (P2-PROPOSED; not present in P1 v1.9.0)

- `GET /api/v1/customers/{id}`: Group-shared master + authorized customer Contacts (or separate P1 Contact endpoint); **no hidden Finance/Sales fields by default**.
- `GET /api/v1/customers/{id}/interactions?company_id=&page=` / `POST`: Company-owned notes and contact events.
- `GET /api/v1/customers/{id}/transactions?company_ids=&source=&from=&to=&page=`: unified read-only timeline, each entry with `source_resource,source_id,company_id,business_at,status,amount_if_authorized`; SQL Company filtering **before** count/pagination.
- `GET /api/v1/customers/{id}/sales-summary?company_ids=&from=&to=`: confirmed/fulfilled/net/return amounts separately, exact metric codes and `metric_status=READY|DEPENDENCY_PENDING|NOT_AUTHORIZED`.
- `GET /api/v1/customers/{id}/receivables?company_ids=&as_of=&page=`: order-level due/outstanding/aging/credit bucket. Requires approved addendum and finance allocation contracts; not part of default G1 release.
- `GET /api/v1/customers/{id}/overview?company_ids=&as_of=`: composition endpoint **only after** dependent endpoints and PBAC gates are proven; can aggregate *authorized* sections, not bypass their individual permissions.

**Request contracts:** `company_ids` are explicitly authorized actual Company Organization IDs; absence means only an approved default/authorized scope (never silent Group-wide leakage). Timestamp/business-date scopes, pagination, ordering, numeric decimal-string representations and response error/status are aligned with P1 conventions. Never return `0` for an unimplemented or unauthorized metric; use omitted field or tagged unavailability. No amount or count inference from `customer.view` alone. Proposed authorization: dedicated summary/read permissions and, at minimum, check the source `sales_order.view`, `sales_return.view`, `shipment.view`, `money_receipt.view` per visible data family, including row-level Company restrictions. Source links checked again at click-through.

## 8. Customer frontend: pages and progressive enablement

**Customer list:** code, name, type/group, active status, Contact count (if authorized), Company eligibility. No private notes or sales totals in Group-shared list by default.

**Customer workspace tabs:**

1. **Profile** — Customer Master and allowed Companies, address main.
2. **Contacts / Ship-to** — multiple Contacts with *computed* delivery eligibility; one contact can mean recipient-at-location.
3. **Interactions** — Company-scoped notes/activity log.
4. **Sales / Orders** — order history and separate headline `confirmed_order_value`, `fulfilled_goods_value`, `net_sales_operational`, counts/last order, Company and period filters.
5. **Payments & Receivables** — posted receipts, order-level matched payment, open amounts/aging, COD collected vs carrier unsettled when approved.
6. **Shipping & Returns** — Shipments, delivery status, Return trace; links only when source modules exist and actor is authorized.

Do not create six database tables or six independent Vue pages just because six tabs are useful. Keep one Customer workspace and source-specific API composition. G1 UI should show disabled/dependency-pending states for tabs not yet backed by modules, not mock `0` balances.

## 9. Required design decisions BEFORE code (all OPEN)

| ID | Question requiring approval | Suggested minimal default | Blocking gate |
|---|---|---|---|
| CUS-X-01 | Does Contact-as-ship-to satisfy original UR-CUS-001 shipping field requirement? | Yes, after explicit acceptance as equivalent functional storage; snapshot Sales delivery | CUS-G1 |
| CUS-X-02 | Customer type/group and whether business users create groups | `INDIVIDUAL/ORGANIZATION` + controlled group code; no group table yet | CUS-G1 |
| CUS-X-03 | Customer Master scope versus Company-private data and permissions | Group master; Company-private notes and reports; SQL-first PBAC | CUS-G1 |
| CUS-X-04 | What “tổng doanh số” means and when counted | Expose at least confirmed, fulfilled and net operational sales separately | SAL design |
| CUS-X-05 | Which Sales lifecycle/Return event starts payable and credit, partials/rounding | Delivered+Issue POSTED as initial operational cutoff; credit when commercially approved | SAL design |
| CUS-X-06 | Do users need simple operational receivables, or invoice-based legal AR? | Operational order-level only; full AR is excluded from baseline | Scope/CR before G1 closure claim |
| CUS-X-07 | Paid/allocated/prepayment/refund handling, statement, concurrent allocation | Typed allocation in FIN draft `money_source_links`; posted-only cash | FIN design |
| CUS-X-08 | COD/customer-settled vs carrier-remittance and marketplace settlement | Separate states, no double counting, defer combined debt until contract exists | SAL/FIN design |
| CUS-X-09 | Due dates and aging buckets; credit terms/credit limit | Add per-order due date only if overdue report approved; defer credit limit/credit blocking | SAL/FIN design |
| CUS-X-10 | Exact report/API fields, Company scope, status/error and export rules | Source-owned readers, explicit permission, no unapproved aggregate | CUS-G1 API + later |
| CUS-X-11 | Customer group used for P2 pricing when no catalog table | Freeze controlled stable codes before PRC, or introduce catalog with approved migration | PRC design |
| CUS-X-12 | Return/payment retroactivity, as-of and timezones | Company business dates; immutable/reversal adjustments; as-of replays | SAL/FIN design |

## 10. Mandatory tests and release criteria

**CUS-G1 Core / P1 regressions:** global unique customer code; Company allowlist SQL and create-time recheck; inactive Customer historic read; Contact owner extension `CUSTOMER` plus regressions for Employee/Organization; multiple recipient-at-location addresses; duplicate-recipient distinct addresses; shipping snapshot design contract; Company-private notes count/pagination; audit; status changes; controlled group code.

**Sales integration:** Customer A/Company A/B scope, customer NULL orders excluded from named Customer; partial delivery; cancelled pre-shipment order; cancellation after partial; return accepted without cash refund; COMBO allocated leaf amounts sum to original; marketplace amount remains externally authoritative; identical Shipment events not double counted.

**Finance/receivable integration:** two receipts against one order, one receipt against two orders; unallocated advance; a refund plus commercial return; posted-only cash; duplicate posting/reversal idempotency; no cross-Company allocation; COD collected but not remitted; COD payout not double counted; finance totals vs Customer sub-ledger reconciled; missing due date not reported as overdue; as-of with payments from before filtered period; unauthorized finance aggregate suppressed.

**Release statuses:**

- `CUS-CORE READY`: only after signed v0.3 decisions relevant to G1, approved schema/API/UI PBAC and DEV integration tests.
- `CUS-CORE CLOSED`: after G1 tests pass, release core with optional/dependent cards explicitly unavailable.
- `CUS-XMOD DESIGN LOCKED`: after SAL/FIN owner teams sign report definition and data semantics.
- `CUS-FULL ACCEPTANCE`: after source SAL/FIN/PRC modules are operational, transactions/finance E2E tested, and UR-CUS-002 history + added metrics signed off. Do not misuse the 8 source AC to claim unrequested debt metrics are approved or implemented.

## 11. Recommendations

1. **Keep 3 CUS tables**, reuse P1 Contacts for ship-to; do not create AR/analytics mirrors in CUS.
2. **Approve CUS-XMOD contracts now**, even though Sales/Finance will be implemented later. Schema and API owners must agree on `customer_id`, Company, frozen sales amounts, returns credits, payment allocations, COD settlement and as-of status before code drift.
3. **Choose the receivable business scope expressly**: simple operational open-order balances (additive P2 capability) versus genuine receivable ledger/invoices (scope change). Never equate `cash_bank_accounts` operational balance with Customer receivables.
4. **Deliver CUS-G1 core independently**, mark dependent data NOT_AVAILABLE, and close full cross-module acceptance only with actual integration test evidence.

