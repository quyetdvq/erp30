# P2-CUS — Contact as Shipping Address (minimal design)

**Document ID:** P2-CUS-ADR-SHIP-001  
**Version:** 0.2 · **Date:** 2026-10-28  
**Status:** PROPOSAL / OPEN — not approved, no code or database changed  
**Baseline:** `erp_v1.9.0.zip` — P1 CLOSED; `00_User_Requirements_v1.0.15.md` (UR-CUS-001, UR-CUS-002, UR-CORE-005, UR-SAL-001)  
**Replaces:** the **single shipping address** design and the explicit “many delivery addresses” exclusion in `P2_CUS_MINIMAL_DESIGN_PROPOSAL_v0.1.md`. This document does not change the approved URs.

## 1. Recommendation

Use the **existing P1 `contacts` table** as the Customer address book. One Customer has many Contact records; each Contact with a complete, valid delivery destination is an eligible ship-to address. Contacts without a destination remain ordinary contacts. This supports business and individual Customers with many ship-to addresses and **does not add a new shipping-address table**.

**New P2-CUS business tables remain exactly three:** `customers`, `customer_company_usages`, `customer_interactions`. The P1 `contacts`, `provinces`, `wards`, `organizations` and authorization tables are reused.

### What is a “Contact” in this design?

One record is a **recipient-at-an-address**. It combines recipient/contact details and one physical delivery destination. The same person at two sites is represented by two Contact records, possibly with the same `name` and `mobile` but different `address` / `province_id` / `ward_id`. This is a deliberate minimal-model tradeoff; Contact is not treated as a unique natural-person identity.

One Customer is still one global Group Master record, regardless of the number of delivery sites or participating Companies. Company Usage Scope and PBAC are separate checks.

## 2. Verified P1 persistence and implementation

From `backend/app/Database/Migrations/2026-10-05-000002_CreateOrganizationContactTables.php`, P1 `contacts` fields include:

| Existing Contact field | Customer shipping semantics |
|---|---|
| `id` | Exact shipping address/recipient candidate ID |
| `contact_type` | `CUSTOMER` (new allowed owner **value**, not a new column) |
| `contact_ref_id` | `customers.id` (polymorphic reference, no direct FK) |
| `description` | Optional delivery location label: 'Kho A', 'Văn phòng', 'Nhà riêng' |
| `status` | `ACTIVE` is needed for new ship-to selection |
| `name` | Recipient name / receiving unit name |
| `phone`, `mobile`, `email` | Recipient/contact channels |
| `address` | Street, number, building or other address details |
| `province_id`, `ward_id` | P1 administrative master FK |
| `province_name`, `ward_name` | Names/snapshots available in P1 for presentation/history |
| `created_by`, `updated_by`, `created_at`, `updated_at` | P1 audit convention |

P1 Contact code still supports `ORGANIZATION` and `EMPLOYEE` only (`ContactRules::OWNERS`). `ContactScope`, `ContactScopeProvider`, `ContactSql::ownerPredicate` and `ContactService::compileContactScopes` are also hard-coded to those two owner/resource branches. **Adding a CUSTOMER row without extending those paths is invalid**. The P1 migration already defines `contact_type` as `VARCHAR(50)` and the owner as a polymorphic reference: it requires no new shipping table or `contacts` DDL change.

P1 Contact address validation checks existence and Province/Ward relationship when Province is present, but does **not** currently enforce shipping-specific completeness or all geographic status conditions. Add those checks for **ship-to selection and CUS Contact creation/edit** without tightening unrelated Employee/Organization Contact validation in an incompatible way.

## 3. Canonical data model for P2-CUS

### 3.1 `customers` (new)

Retain business identity, Customer Group code, general `phone`/`email`, `tax_code`, headquarters/billing `address`, `province_id`, `ward_id`, `all_companies`, `status` and audit. Do **not** persist `shipping_address`, `shipping_province_id`, `shipping_ward_id` on `customers`. The UR shipping-address capability is provided by the associated Customer Contacts. Keep optional `primary_contact_id` if a primary business person is approved, but **do not confuse it with default shipping destination**.

For the simplest G1 scope, do **not** add a default-shipping field: the Sales Order UI selects an eligible Contact explicitly when multiple addresses exist. A nullable `default_shipping_contact_id` is a later optional single-column enhancement if the business needs a Group-wide default; a per-Company default requires an additional decision about where to store it. Do not silently use `primary_contact_id` as delivery default.

### 3.2 `customer_company_usages` (new)

Same as v0.1: `(customer_id, company_organization_id)` primary key, current allowlist with optional `customers.all_companies`. Company Usage Scope controls which Company can create **new transactions**, but it does not on its own grant PBAC permission.

### 3.3 `customer_interactions` (new)

Same as v0.1: append-only CRM activities, each persisted at its owning Company. Do not reuse `contacts` as a note/history event table.

### 3.4 `contacts` (reused P1)

Customer Contact owner: `contact_type='CUSTOMER'`, `contact_ref_id = customers.id`. For illustration:

| Contact ID | Customer ID | Description | Recipient | Mobile | Address | Can ship? |
|---|---|---|---|---|---|---|
| 501 | 101 | Kho Hà Nội | Anh A | 09... | Số nhà..., Hà Nội | Yes when complete/ACTIVE |
| 502 | 101 | Kho TP.HCM | Chị B | 09... | Số nhà..., TP.HCM | Yes when complete/ACTIVE |
| 503 | 101 | Kế toán | Anh C | 09... | NULL | No; ordinary contact |

## 4. Shipping eligibility (P2-DRAFT business rule)

For a given `(customer_id, sales_company_id, contact_id, evaluation_time)`, the system must validate server-side:

1. Customer exists, is `ACTIVE` and is usable by `sales_company_id` under `all_companies` / `customer_company_usages` (with a valid `COMPANY` owner). Evaluate Sales PBAC independently.
2. Contact is a real persisted row with **both** `contact_type='CUSTOMER'` and `contact_ref_id=customer_id`; it is `ACTIVE`.
3. Contact has a nonblank detailed `address`, `province_id`, `ward_id`, and a valid Province/Ward parent relationship. Geographic active/selectable rules for new transactions must be checked; do not silently replace historic references.
4. Shipping party is identifiable (`name` nonblank) and at least one of `mobile`/`phone` is provided for domestic delivery; individual carriers may demand additional validation. These completeness rules are **design proposals**, not fields mandated verbatim by the source UR.
5. PBAC for `customer_contact.view` (and the relevant order action) must allow the actor to select this Contact; never use client-provided Organization context to bypass persisted ownership or Company checks.
6. Inactive/closed or incomplete Contact is excluded from selectable addresses for new orders, but remains retrievable as historical data under authorization rules.

No separate `is_shipping_address` flag is required for the minimal mode; **computed eligibility** is based on data completeness and status. Do not mark every Customer Contact as a valid ship-to destination merely because it has `contact_type=CUSTOMER`.

## 5. Sales Order and Shipment contract — essential historical snapshots

P2-SAL (not implemented in P1) must capture an **immutable business shipping snapshot** on the Sales Order rather than using live Contact fields each time a label is generated. Proposed fields at Sales Order header / equivalent authoritative shipping snapshot:

- `delivery_contact_id` (nullable reference to a Customer Contact selected at order entry; traceability only),
- `delivery_recipient_name`, `delivery_phone`,
- `delivery_address` (street/address details),
- `delivery_province_id`, `delivery_ward_id`,
- optionally `delivery_province_name`, `delivery_ward_name` to preserve the printed textual address under administrative renames.

The actual location/recipient fields must be saved as values, **not fetched from Contact at shipment time**. The latest Contact edit/inactivation must not change an order or carrier label already confirmed/issued. Shipment should use the Sales Order shipping snapshot or its own immutable snapshot if the future Shipment business contract explicitly permits changing recipients/destinations. Such a change must be controlled and audited.

**One Sales Order = one ship-to destination** in this initial proposal. One order to multiple addresses needs an explicit additive Sales design (multiple shipments/line-level destinations), not another table in CUS.

For Shopee/TikTok orders, the externally supplied order address can be captured as an authoritative Sales shipping snapshot **without creating or matching a Customer Contact**. Do not pollute the Customer address book automatically from every marketplace order.

## 6. UX/API behavior

**Customer workspace:** one `Contacts & delivery addresses` section. A Contact row has display name/label, recipient, phone, full address, status and computed display `Eligible for delivery` / `Contact only` with reason if incomplete. Add/edit Contact uses the existing P1 Contact endpoints extended for `CUSTOMER`; avoid a parallel Customer-specific address CRUD service.

**Direct/Internal Sales UI:** `Customer → eligible ship-to Contact list → choose one → preview shipping details → save snapshot on order`. If exactly one candidate, UI may preselect it; when multiple exist, selection is explicit. An order-specific one-off destination can be stored in the Sales snapshot if a separate Sales decision approves it, without forcing a new Contact entry.

**Validation:** all important checks are repeated server-side, independently of frontend pickers, in the same transaction that creates/confirms the order as appropriate. Do not rely only on UI filtering.

## 7. Implementation changes, no extra CUS table

- P2-CUS migration: create the same three tables as v0.1, but **remove the three shipping address columns from `customers`** before initial migration is deployed. Do not attempt to drop columns from an existing live table without data migration and approval.
- P2-CUS contact extension: add canonical `CUSTOMER => customer_contact` to ContactRules; new Customer owner resolver based on persisted `customers.id`; reviewed `ContactScopeProvider`, `ContactSql` and `ContactService` three-owner branch and SQL-first PBAC; Resource.Action registration, lifecycle and negative test coverage. Group-level Customer master authorization must not leak Company-private interactions.
- P2-SAL design update: snapshot recipient/address on `sales_orders` and adjust Shipment/Carrier reads. Existing P2 OpenAPI/DB/UI drafts were made before this decision; this amendment is not yet merged into those contracts.
- Existing owner `ORGANIZATION` and `EMPLOYEE` behavior must remain unchanged and pass regression tests.

## 8. Trade-offs and upgrade boundary

| Question | Minimal Contact-as-address | When a separate `customer_addresses` table becomes useful |
|---|---|---|
| Multiple delivery addresses | Supported by multiple Contacts | Still supported |
| Same person at two locations | Duplicate person/phone in two Contact rows | Person and place managed independently |
| Many recipients at same warehouse | Duplicate destination across Contacts | One address links many people |
| Address-specific company visibility | Shared contact book under approved Customer/PBAC scope | Independent per-address company ownership/visibility |
| Address-only place without person | Use receiving unit name in Contact.name | Clean separation of place and recipient |
| Delivery window, geo-coordinates, carrier routing | Not in P2-CUS minimal design | Separate address entity may be preferable |

**Review trigger:** If the business must independently reuse one person across many places **and** one place across many people, or must govern ship-to locations per Company, the Contact-as-address model becomes awkward and a dedicated address table should be proposed. Do not create that table prematurely.

## 9. Minimum acceptance/regression scenarios

1. One Customer with 3 valid Contact delivery addresses → user can pick any under authorized Company.
2. Same name/mobile at two addresses → two distinct Contact records are valid and selectable.
3. Contact with no `address` / missing Province/Ward / no recipient or phone → remains contact but not selectable for shipping; no accidental leakage into Sales.
4. Inactive Contact or Customer, or Company outside usage scope → blocked for new order; historic order remains readable under the Sales resource's own PBAC.
5. `contact_type=EMPLOYEE` or `contact_ref_id` from a different Customer → cannot be used as delivery address for this Customer.
6. Edit Contact after Sales Order confirmation → historic order's recipient, phone, address and issued label stay unchanged.
7. Marketplace order with external address → shipping snapshot saved; no Contact auto-created.
8. Negative PBAC, pagination, total counts, Contact owner resolution; P1 Employee/Organization contacts unaffected.
9. Province/Ward parent mismatch and inactive geography reject new selection while historic reference remains preserved.
10. One Sales Order with multiple selected addresses is **not** supported silently; it requires a separate Sales contract.

## 10. Decisions to seek approval

- **CUS-SHIP-01 (recommended):** reuse `contacts` for Customer ship-to addresses; **no new CUS table**.
- **CUS-SHIP-02 (recommended):** recipient-at-location identity; duplicate same person across locations is accepted.
- **CUS-SHIP-03 (recommended):** computed shipping eligibility; no new `is_shipping_address` / `purpose` column in minimal scope.
- **CUS-SHIP-04 (recommended):** `customers.address` is headquarters/billing address; remove direct `customers.shipping_*` columns.
- **CUS-SHIP-05 (recommended):** one destination per Sales Order; Sales captures immutable snapshot; marketplace addresses need not be Contacts.
- **CUS-SHIP-06 (open):** whether default shipping address is needed now, and whether it is Group-wide or Company-specific. To keep the minimal design, **omit a persisted default for G1**.
- **CUS-SHIP-07 (open for SAL):** allow Sales one-off destination; conditions for post-confirmation address change and Shipment address snapshot.

No user approval or implementation action is implied by this design memo.
