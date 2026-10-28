# P2-CUS — Customer & CRM: Minimal Design Proposal

**Document:** `P2-CUS-MIN-001`  
**Version:** `0.1` | **Date:** 2026-10-28  
**Status:** `DRAFT — awaiting module-level design approval`  
**Implementation base:** `erp_v1.9.0.zip` (`P1 CLOSED BASELINE`)

## 0. Authority and interpretation

- **Approved business authority:** `00_User_Requirements_v1.0.15.md`, `UR-CUS-001`, `UR-CUS-002` (2 UR; 8 acceptance criteria) and inherited P1 constraints (Contact, Organization, PBAC, Address masters, Audit, Workflow, Auto Numbering).
- **Reviewed P2 draft:** `DinkERP_P2_Independent_Documents_v1.0.0/modules/P2_CUS_INDEPENDENT_MODULE_SPEC.md`, and `docs/05_P2_DATABASE_DESIGN.md`. The existing draft lists four CUS tables (`customers`, `customer_groups`, `customer_company_usages`, `customer_notes`); the proposed consolidation below is **not yet approved**.
- **P1 code verification:** migrations `2026-10-05-000001_CreateFoundationLocationTables.php`, `2026-10-05-000002_CreateOrganizationContactTables.php`, `2026-10-11-000015_SplitContactAuthorizationResources.php` and the `App/Modules/Contact` implementation from v1.9.0. Logical table names used below are subject to the configured CodeIgniter DBPrefix.
- **Status legend:** `BASELINE` is approved requirement; `P1-EXISTS` means observed in v1.9.0 source; `P2-PROPOSED` means a design suggestion only.

## 1. Minimal module scope

1. **Customer Master:** global-Group-unique `customer_code`, name, controlled Customer classification, phone/email/tax code, standard address, shipping address, status, audit timestamps and actors.
2. **Company usage:** `ALL` or explicit allowlist of Organization nodes whose `type=COMPANY`. Company usage is business eligibility, never a replacement for PBAC.
3. **Customer people/contacts:** multiple P1 `contacts` rows owned by a Customer, an optional primary Contact selection; no local `customer_contacts` table.
4. **Interactions:** append-only notes and simple contact events (activity kind + description + time + actor + owning Company) in one table.
5. **History:** read-only projection of sales orders, shipments, sales returns and money receipts **when the respective modules exist**; each source protected by its own Resource.Action + authoritative Company scope. No duplicate CRM transaction-history table.
6. **Search & UX:** list/filter, create/edit, activate/inactivate, Company scope selector, Contact pane, activity timeline and linked related records. One Customer workspace screen is sufficient initially.

**Explicit exclusions:** lead/opportunity pipelines, activities calendar, marketing campaigns, many delivery addresses, customer-specific accounting ledger, separate Customer-Group CRUD, effective-dated usage history.

## 2. Physical table decision: three new tables

### 2.1 New: `customers`

| Field | Proposed type | Purpose/constraint |
|---|---|---|
| `id` | `BIGINT UNSIGNED` PK auto increment | Technical identity |
| `customer_code` | `VARCHAR(50) NOT NULL` | Group-global unique; canonical normalization |
| `name` | `VARCHAR(255) NOT NULL` | Customer name |
| `customer_group_code` | `VARCHAR(50) NULL` | Source-controlled classification code; no group table in minimal proposal |
| `phone` | `VARCHAR(50) NULL` | Generic/main customer telephone |
| `email` | `VARCHAR(255) NULL` | Generic/main customer email |
| `tax_code` | `VARCHAR(50) NULL` | No automatic UNIQUE without PO decision |
| `address` | `TEXT NULL` | Main address detail |
| `province_id`, `ward_id` | `BIGINT UNSIGNED NULL` | FK to existing `provinces` / `wards` |
| `shipping_address` | `TEXT NULL` | Shipping address detail |
| `shipping_province_id`, `shipping_ward_id` | `BIGINT UNSIGNED NULL` | FK to existing administrative masters |
| `primary_contact_id` | `BIGINT UNSIGNED NULL` | Optional FK to existing `contacts.id`; service MUST check `contact_type=CUSTOMER` and owner ID |
| `all_companies` | `TINYINT(1) NOT NULL` | 1=all; 0=allowlist mode. User must select scope explicitly |
| `status` | `VARCHAR(20) NOT NULL` | Initially `ACTIVE` / `INACTIVE` subject to approved resource lifecycle |
| `created_by`, `updated_by` | `BIGINT UNSIGNED NULL` | Actor IDs, server managed |
| `created_at`, `updated_at` | `DATETIME` | Server timestamps |

**Keys:** PK `id`, UNIQUE `customer_code`, indexes on `(status, name)`, `(customer_group_code)`, optionally `(tax_code)` for nonunique lookup. Validate Province/Ward parent match for main and shipping addresses; code selection active for new data, historical reference intact. Do not cascade-delete Customer once referenced.

**Contact person:** No duplicate `contact_person` free-text field. A UI Contact Person input creates/links a `contacts` row with owner `(contact_type='CUSTOMER', contact_ref_id=customers.id)` and can assign `primary_contact_id`. This is **P2-PROPOSED**; exact mandatory/optional behavior still needs review.

### 2.2 New: `customer_company_usages`

| Field | Proposed type | Purpose/constraint |
|---|---|---|
| `customer_id` | `BIGINT UNSIGNED NOT NULL` | FK `customers.id` |
| `company_organization_id` | `BIGINT UNSIGNED NOT NULL` | FK `organizations.id`; target must have type `COMPANY` |

**Composite primary key** `(customer_id, company_organization_id)`. No independent auto ID, row status, or effective dates. If `customers.all_companies=1`, keep **zero** usage rows. If `all_companies=0`, require ≥1 allowed Company before an ACTIVE Customer can be used by a new sales transaction. Replace the allowlist and change mode atomically in a DB transaction. Changes never rewrite historical Sales records. Actor/timestamp of the change can be recorded on the parent `customers` row; assess whether the existing P1 audit mechanism is suitable for detailed scope-change audit before approval.

### 2.3 New: `customer_interactions`

| Field | Proposed type | Purpose/constraint |
|---|---|---|
| `id` | `BIGINT UNSIGNED` PK auto increment | Interaction identity |
| `customer_id` | `BIGINT UNSIGNED NOT NULL` | FK `customers.id` |
| `company_organization_id` | `BIGINT UNSIGNED NOT NULL` | Company owning the interaction; validated type `COMPANY` |
| `activity_type` | `VARCHAR(30) NOT NULL` | Controlled codes such as `NOTE`, `CALL`, `EMAIL`, `MEETING` (illustrative, pending approval) |
| `content` | `TEXT NOT NULL` | Note or contact summary |
| `happened_at` | `DATETIME NOT NULL` | Event time |
| `created_by` | `BIGINT UNSIGNED NULL` | Actor |
| `created_at` | `DATETIME NOT NULL` | Creation time |

**Keys:** PK `id`; index `(customer_id, company_organization_id, happened_at, id)` and `(company_organization_id, happened_at)`. Append-only to protect CRM history; corrections should be a new corrective entry rather than silent deletion. Company scope is authoritative and filtered **in SQL before counting, ordering or pagination**. Do not make all Company users see another Company’s private notes.

## 3. Existing P1 persistence reused — no new tables

| P1 table | Reuse |
|---|---|
| `contacts` | Multiple Customer contacts, with new `CUSTOMER` polymorphic owner and optional primary contact reference |
| `organizations` | Existing Group/Company and authoritative Company IDs for allowlist/activity |
| `provinces`, `wards` | Address master data, existing FK and Province/Ward validation |
| `resources`, `resource_actions`, `policies` and Shield identity | Authorization capability; add Resource.Action catalog records only, not a new permissions table |
| P1 Auto Numbering/Workflow/Audit mechanisms | Reuse where configured/approved; do not clone counters, lifecycle or audit tables |

**Important implementation work:** P1 `ContactRules::OWNERS`, `ContactScope`, `ContactScopeProvider` and `ContactSql` currently support only `ORGANIZATION` / `EMPLOYEE` Contact kinds. Extending to `CUSTOMER` is an *additive P2 change to shared code* with a separately reviewed `customer_contact` resource and SQL-scoping/negative regression tests. Merely inserting `contact_type='CUSTOMER'` is not sufficient, and existing Contact permission boundaries must remain intact.

## 4. Data eligibility and security rules

- **Customer vs Company:** one Customer globally. No duplicated Customer record per Company and no P1 Product Company scope copied to CUS.
- **Create/edit shared master:** evaluate `customer.create/edit/view/setstate` through P1 PBAC at canonical Group-level context for shared master. Identity mapping and System Admin no-bypass remain applicable.
- **Company eligibility for new sales:** Customer `ACTIVE`, requested Company `ACTIVE`, and `(all_companies=1 OR EXISTS customer_company_usages for sales_company)`. Enforce in backend Sales write path, not frontend list alone; require `sales_order.create`/state PBAC separately.
- **History is not eligibility:** changing a Company allowlist or Customer to `INACTIVE` must not remove or hide historically valid records from an actor otherwise authorized to view them.
- **Company-private contact history:** `customer_interaction.view/create` scoped to persisted Company, with SQL WHERE predicate before pagination. Reading Sales/Shipment/Return/Receipt follows each related module’s independent permission and Company scope. Avoid a single GROUP-level `customer.view` that unintentionally leaks Company-specific notes.
- **Contact ownership:** never allow client-selected Organization to override `customers.id`; on primary contact update check the Contact owner pair matches. Preserve Employee/Organization Contact behavior.
- **Classification:** `customer_group_code` is from a controlled code registry (not arbitrary text) and is reserved for P2 Pricing matching. An admin-maintained dynamic group catalog needs a fourth table and a separate approval.
- **Timestamps/audit:** server-managed actor/time; do not trust client-input `created_by`/`updated_by`.

## 5. Minimal API/UI proposal (not OpenAPI approved)

**Customer API:** `GET /api/v1/customers`, `POST /api/v1/customers`, `GET /api/v1/customers/{id}`, `PATCH /api/v1/customers/{id}`, `POST /api/v1/customers/{id}/actions/set-state`, `GET /api/v1/customers/{id}/history`, `POST /api/v1/customers/{id}/interactions`. Atomic Company selection can be included in Customer create/update payload, avoiding a separate `PUT /company-usages` until there is an actual use case. Reuse the existing P1 Contact API after extending its owner model rather than duplicating endpoints. Existing P2 draft API operation count is **not** frozen.

**UI:** a `/customers` list plus one customer workspace (detail/edit screen or drawer) with **Profile**, **Contacts**, **Interactions** and **Transactions (read-only)** sections. No separate Customer Group management screen in the minimal configuration. Before P2-SAL/P2-FIN there are no live Sales/Receipt tables to aggregate; show only implemented interaction/contact data, not fictitious transaction history.

## 6. Required tests before module acceptance

1. Duplicate normalized Customer code → reject globally; Company A and B see the same Customer identity when authorized.
2. Company selection `ALL`, explicit `LIST`, empty/invalid list, non-COMPANY IDs and concurrent replacement → deterministic and atomic.
3. Customer `INACTIVE` / Company removed from allowlist → reject **new** Sales selection; historic Sales records remain readable in their authorized Company.
4. P1 `contacts` owner `CUSTOMER` succeeds **only after** authorized owner extension; wrong owner ID or mismatched primary contact rejects; regression for `ORGANIZATION` / `EMPLOYEE` remains PASS.
5. Customer main/shipping Province/Ward reference validation; reject Ward from another Province.
6. Company A interaction is never shown to Company B users without A authorization, including COUNT/export and pagination.
7. Save/query interaction note and contact history; no lead/opportunity models appear.
8. When P2-SAL/P2-FIN is installed, each related-transaction source is included only when the actor has that module’s Resource.Action and Company scope.
9. Group classification codes validated; no cross-company permission derived from customer usage eligibility.
10. DB migration additive, forward-only; no P1 Contact table replacement, permission bypass or hard-delete of referenced master.

## 7. Decisions to approve for G1 (currently OPEN)

| Item | Recommended decision | Effect |
|---|---|---|
| `CUS-DEC-01` | **3 new tables**, as §2, no `customer_groups` table in CUS | Minimal normalization with real FKs for Company eligibility |
| `CUS-DEC-02` | Group classification **controlled code** in `customers.customer_group_code` until PRC | No dynamic Group CRUD now; approve fourth table only if users need self-service Group management |
| `CUS-DEC-03` | Company usage is a **current snapshot**, not effective-dated | Historical Sales snapshots preserved; no scope-history table |
| `CUS-DEC-04` | Extend shared P1 Contact to owner `CUSTOMER`, register `customer_contact` resource | No `customer_contacts` table; needs guarded shared code changes |
| `CUS-DEC-05` | Notes/interactions are **Company-owned and append-only** | No note data leakage across Company scopes |
| `CUS-DEC-06` | `tax_code` is searchable but **not UNIQUE** until business confirms otherwise | Avoid erroneous rejection of legitimate duplicates |
| `CUS-DEC-07` | Optional primary Contact `primary_contact_id` references P1 `contacts` | One source of truth for contact persons |

**Approval gate:** Do not treat the above decisions, code changes, endpoints or DB structures as baseline until Product Owner/technical design approval. Only the quoted original URs remain BASELINE.
