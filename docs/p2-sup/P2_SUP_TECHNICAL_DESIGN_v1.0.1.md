# DinkERP P2-SUP — Technical & Functional Design v1.0.1

> **Document ID:** P2-SUP-DES-001 · **Version:** 1.0.0 · **Date:** 2026-10-29
> **Product scope:** **APPROVED 12/12** (see [Decision Register](P2_SUP_DESIGN_DECISIONS_v1.0.1.md)).
> **Engineering status:** **PRODUCT DESIGN APPROVED / ENGINEERING CONTRACT CORRECTED / IMPLEMENTATION NOT STARTED**; MOD-G1/G2 implementation details require technical verification in [Pre-code Readiness](P2_SUP_PRECODE_READINESS_v1.0.1.md).
> **Source reference (read-only):** `erp_v2.0.0_customer_required_phone_fixed.zip`, SHA-256 `9af6a01ec451400c8d3cd580396b1e6d3b294c0073b4c0675532a9752bb4aa28`.
> **Authority order:** P2 baseline UR/8 AC → SUP decisions v1.0 → applicable `docs/development-standards/` → canonical OpenAPI frozen at MOD-G2 → implementation. Draft P2 specifications are not permission to change approved UR or frozen P1.

## 0. Contract scope and status

- **UR-SUP-001** — Group-shared Supplier Master. All five acceptance criteria remain mandatory. G1 delivers Core, while final proof of *new Purchase transaction* guard depends on real PUR integration.
- **UR-SUP-002** — Company/resource-authorized Purchase/Finance History. All three acceptance criteria remain mandatory at later PUR/INV/FIN delivery; **not closed** by G1.
- **In scope now:** supplier profile/list/create/edit, two-mode Company Usage, Lifecycle dynamic, Auto Numbering, Shared Contacts owner `SUPPLIER`, one encrypted optional bank account, optimistic concurrency, PBAC SQL-first, Vue list/detail/forms, i18n/audit.
- **Out of scope in G1:** Supplier Group CRUD, Payment Term due-date engine, Supplier Notes, Supplier History adapters before sources exist, PO/GR/return/payment, multiple bank accounts, multiple addresses/sites, bank approval workflow, supplier portal, import/export, price/MOQ/lead-time, scorecards, integrated Customer–Supplier identity.
- **No new code exists** merely because this design is approved. Current frozen P1 route signatures remain frozen.

### 0.1 Current source facts to carry forward

| Area | Source observation | Design implication |
|---|---|---|
| Customer | `backend/app/Modules/Customer/` has `CustomerService`, `CustomerRules`, `CustomerSql`, `CustomerNumberIssuer`; Vue `frontend/src/features/customer/` | Reuse patterns and components, not forced identical business policy. |
| Customer initial State | `CustomerService::create()` uses `ResourceStateRepository::initialState('customer')`; validates storage length instead of requiring `ACTIVE`. | Supplier create **must** use `initialState('supplier')`; no hardcoded initial state. |
| Customer type | `CustomerRules::TYPES=['INDIVIDUAL','ORGANIZATION']` | Supplier-approved values are **`INDIVIDUAL`,`COMPANY`**; do not blindly reuse CUS constants. |
| Customer phone | New `CustomerPhone` / migration `...000025_AddCustomerPhoneUniqueness.php` makes Customer phone required+unique | Supplier phone required and format validated, **NOT unique**; do not reuse Customer DB uniqueness. |
| Routing | `backend/app/Config/Routes/ApiV1/CustomersRoutes.php` and module-domain files | Supplier routes live only in `SuppliersRoutes.php`; preserve frozen route table. |
| Existing migration max in ZIP | `2026-10-29-000025_AddCustomerPhoneUniqueness.php` | Do not reuse migration 000025. Confirm *actual DEV* migration registry before assigning next sequence. |

## 1. Approved Supplier master model

### 1.1 Input rules, defaults and field types

| Field | Persistence / validation | Required on Create | Editable after Create? |
|---|---|---|---|
| `supplier_code` | VARCHAR(50), canonical uppercase/trim; generated with P1 Numbering unless explicit manual fallback; UNIQUE in Group | Server-generated or manually supplied | **No** (business identifier immutable) |
| `name` | VARCHAR(255), trimmed, non-empty | YES | YES |
| `supplier_type` | ENUM-like code `INDIVIDUAL`/`COMPANY` (VARCHAR(20)), validated strictly | YES; UI must choose | YES, unless associated commitments later prohibit |
| `supplier_group_code` | controlled catalog; **default `STANDARD`**; no management screen | YES (server default) | YES within catalog |
| `phone` | VARCHAR(50), required, format validated; **no uniqueness** (shared switchboard permitted) | YES | YES |
| `email` | VARCHAR(255), validate if provided | NO | YES |
| `tax_code` | VARCHAR(50), no global UNIQUE until separately approved | NO | YES |
| `address` | Main address text, required | YES | YES |
| `province_id` + `ward_id` | required persisted references; current Ward belongs to selected Province | YES | YES as a validated pair |
| `payment_term_code` | nullable catalog: `IMMEDIATE`,`NET15`,`NET30`,`NET60`; **no AP due date calculations** | NO | YES |
| `all_companies` / `company_ids` | explicit usage mode + list; see §2 | YES | via dedicated usage mutation |
| `status` | from canonical Supplier Lifecycle; regex `^[A-Z][A-Z0-9_:-]{0,19}$`, `strlen(code)<=20` to match VARCHAR(20) | SYSTEM | only `setstate` |
| `bank_account` | **0 or 1** encrypted information set; no raw field in generic DTO | NO | through secured bank endpoint only |
| actor/timestamps | from server identity + UTC | SYSTEM | SYSTEM |
| `row_version` | monotonic positive integer; starts at 1 | SYSTEM | increment on **every** successful PATCH / usage PUT / SetState / bank PUT-or-clear; 409 stale |

**POST consistency:** `supplier_type` is explicitly selected from `INDIVIDUAL`/`COMPANY` (no silent CUS default); absent `supplier_group_code` defaults to `STANDARD` server-side, but explicit null/unknown is invalid. `all_companies` boolean and `company_ids` array must both be supplied (`true` => `[]`, `false` => 1..512 distinct ACTIVE COMPANY IDs). All required non-bank profile fields are validated before INSERT. If default Group is used, it is persisted as `STANDARD`; optional Payment Term is null, never guessed as `IMMEDIATE`. `company_ids` is mandatory for explicit-list mode. UI shows required red marks, but backend applies identical rules. For PATCH, validate resulting **merged** profile so partial updates cannot remove required values. Check Province→Ward both on create and when either changes; ward must reset in FE when Province changes.

**Supplier vs Customer differences:** (1) Supplier Type `COMPANY` rather than Customer `ORGANIZATION`; (2) Supplier Phone **not unique**; (3) Supplier has Payment Term/Bank and no Customer Interactions; (4) Supplier Code uniqueness must honor Group scope, not Company. No shared Customer phone schema/constraint is migrated to Supplier.

### 1.2 Controlled catalogs

- **Supplier Group**: minimal G1 catalog includes `STANDARD`. No new Group CRUD. Catalog can be static reviewed config/seed/lookup but one canonical backend validation source must be chosen and OpenAPI UI synced at MOD-G2. Other codes need controlled change, not free text.
- **Payment Term**: four code values above, read-only to SUP. Labels translated vi/en; no calculation of maturity dates, instalments or reminders. If FIN later manages a canonical term master, versioned migration/contract can replace this catalog without changing Supplier IDs.
- **Supplier Code**: Group-scoped P1 Auto Numbering **or** manual only when there is **no configured default Series at all** for the Supplier resource. Precedence: (1) if any default is configured but disabled/invalid/ambiguous, **fail closed** rather than accept manual; (2) if one valid ACTIVE default exists, require auto generation (regardless of `AUTO_IF_EMPTY` permission in the current Customer issuer); (3) an explicit requested Series may be used if valid for Supplier/GROUP, without bypassing a broken default; (4) only if no default is configured AND no Series was requested, accept a validated manual code, otherwise 422. `CustomerNumberIssuer::issue()` currently accepts manual with default `AUTO_IF_EMPTY`; Supplier MUST NOT copy that behavior blindly. Validate `{RESOURCE}` rendering as `supplier`, exact scoped counter locking, `GROUP` context, and a shared transaction for counter+INSERT+usages. No implicit seeding of a default Numbering Series: explicit DEV provisioning is opt-in.

## 2. Company Usage (eligibility, not authority)

- A Supplier is a **Group Master identity**; no duplicate Supplier rows per Company.
- `all_companies=true`: `company_ids=[]` with **zero** allowlist rows. `all_companies=false`: **1..512 unique, active Company IDs** (persisted `organizations.type='COMPANY'`) in the same Group, regardless of current Supplier status.
- Read eligibility for Company C: `all_companies=true OR EXISTS(supplier_company_usages WHERE supplier_id=? AND company_organization_id=C)`; C itself must be eligible/ACTIVE when used for a new transaction. Eligibility **does not grant** `supplier.view` or `purchase_order.create`.
- Replace mode/allowlist inside one transaction, lock Supplier parent, check `expected_version`, validate all IDs/duplicates/type/group/status, atomically update rows/mode/version. `409` for stale version. No effective date in G1.
- A later PO command must read **persisted PO Company** and invoke a `SupplierUsabilityGuard` in the same business transaction; never trust FE selected Company or a hidden field to determine authority.
- `INACTIVE` is always unusable in new Purchase. Until an explicit separate eligibility mapping is approved, only status code **`ACTIVE`** passes `SupplierUsabilityGuard`, even if Supplier Lifecycle has other statuses. **This does not hardcode the initial Lifecycle State.** If a Lifecycle has no `ACTIVE` state, new Purchase remains blocked by default and requires future PO-policy review; Supplier create still works in its real initial state.
- Changes of Supplier status or allowlist must not alter Company ownership/status of existing PO/GR/Return/Payment; historical authorization uses persisted document Company.

## 3. Lifecycle, Resource/Action and Shared Contacts

### 3.1 Lifecycle dynamic by configuration

- At create: `initialState('supplier')` from canonical `ResourceStateRepository`; null, invalid or overlength → controlled domain error, rollback; use that exact state value in persisted row. Reject codes longer than 20 bytes or outside `^[A-Z][A-Z0-9_:-]{0,19}$` rather than rely on DB truncation. No `initial !== 'ACTIVE'` guard, and never `status='ACTIVE'` fallback.
- Status filter options derive from `definitionFor('supplier')` and authorized VIEW state range; default ALL. Reject unknown filter codes before building SQL; do not hardcode UI list `[ACTIVE,INACTIVE]`.
- `PATCH /suppliers/{id}` rejects `status`; `POST /suppliers/{id}/actions/set-state` **requires** `{"status":"TARGET_CODE","expected_version":N}` and checks persisted row/authorization + Lifecycle transition + CAS in the **same transaction**. P1 `transitionTarget(type, row, 'setstate')` selects a target only when exactly one active target exists for current State/action. Multiple possible targets (branching `setstate`) are **not supported by current P1 contract**: reject with a stable domain 409 rather than arbitrarily pick or bypass Workflow. To support branching later, obtain a separately reviewed canonical Workflow API/transition ID contract. P1 Workflow Notifications remain optional and must not introduce dependency cycle.
- To retire a Supplier, `INACTIVE` has disallow-new-Purchase semantics **even if it is not an initial State**. Historical rows remain available subject to source VIEW rights.
- Seeder may provision `SUP_ACTIVE_INACTIVE` only when Resource `supplier` is absent, with preflight of required active workflow states, transitions, initial and idempotence. If `supplier` exists with a valid custom Lifecycle, preserve **its binding and State graph**; if missing/inactive/invalid, fail with a deterministic preflight rather than silently reset. Do not clone `CustomerCoreSeeder::ensureLifecycle()` that insists its original initial ACTIVE even after custom configuration. Change of binding after Supplier rows exist requires persisted State and State-policy compatibility verification before reassignment; no orphaned records or policies.

### 3.2 PBAC matrix

| Resource | Domain | Actions | Rule |
|---|---|---|---|
| `supplier` | Group MASTER | `view`, `create`, `edit`, `setstate` (+ `export` only if future feature approved) | Canonical Group target, State/Resource authorization; no permissions from Company filter. |
| `supplier` (bank) | Group MASTER | `view_bank`, `edit_bank` | Field-specific; **additional to** supplier `view` / `edit` as applicable; direct-ID plus bank action, no plaintext in generic response. |
| `supplier_contact` | Group MASTER (via parent) | `view`, `create`, `edit`, `setstate` | **AND** authorized Supplier parent VIEW + canonical Contact ownership + Status policy; immutable owner reference. |
| Future PO/GR/Return/Expense | Persisted Company | respective `view`, `create`, … | Source owns permissions; Supplier VIEW is insufficient to inspect source. |

All resources/actions seeded **without automatic policy grants**. If actor does not have `supplier.view`, list/count/detail/labels fail closed; direct-ID must not expose existence. Row `allowed_actions` is a derived hint only (no client-side authorization authority). Parent Contact query compiled SQL-first with Supplier-parent scoped predicate for COUNT/PAGE/detail; Contact service, audit and all existing owner variants must use one reviewed owner-scope map. On mutation, enforce both owner resource and parent Supplier **VIEW** permissions for all Contact operations, plus Contact action-specific permission; parent EDIT is not implied by contact.create. The canonical Contact owner is immutable.

### 3.3 Contacts behavior

- Reuse existing P1 `contacts` table (`contact_type='SUPPLIER'`, `contact_ref_id=Supplier persisted ID`); allow many contacts. Do not create `supplier_contacts` table.
- Shared Contact records have their own validation/Lifecycle; `SUPPLIER` owner is immutable once created. A Contact of another owner cannot be “moved” by PATCH.
- No automatic contact creation/copy from Supplier phone unless separately approved; avoids two independently editable sources. Phone/email on the Supplier remain independent from related Contact details.
- Review exact `ContactRules`, `ContactScope`, `ContactSql`, `ContactService`, Workflow resource bindings, read-only M8.7 audit and FE owner selectors. Negative tests must prove ORG/EMP/CUS owner semantics preserved.

## 4. Secure Bank Account (single optional record)

- **One bank-information set per Supplier maximum.** User may create Supplier with no bank. The **generic Supplier Create/PATCH/List/Detail APIs exclude bank plaintext**. Bank can be added later through a dedicated secure endpoint after Supplier exists; Create screen can show it as an optional post-save section only after appropriate bank permission. **G1 release still requires the approved bank capability**; optional refers to per-record input, not skipping the secure implementation.
- Persist **one encrypted payload** containing `bank_name`, `account_number`, `account_holder` (bank name and holder may be empty only if validated by final DTO); store nonce/tag/version and minimal derived `last4` separately for masked display. Avoid plaintext free-text bank column. Store other account fields only inside ciphertext; do not copy these into search/labels/exports. Suggested columns within `suppliers`: `bank_ciphertext`, `bank_nonce`, `bank_tag`, `bank_key_version`, `bank_last4` (exact encoding/length/algorithm to be verified by Security/DBA). There is **no multiple-bank table**.
- Use an authenticated cipher with a random unique nonce per update; keep key in DEV/production secret management **outside DB and repository**. Reject bank write/reveal when key unavailable or ciphertext authentication fails, with no plaintext fallback/log; **non-bank Supplier CRUD and list must still work** even if the bank key is unavailable. Check encryption/key rotation and ciphertext tamper tests before DEV rollout. AES-256-GCM via installed OpenSSL is a **candidate technical implementation, not a secretly approved cryptographic standard**; Security reviews key loading, backup, rotation, random source, AAD and failure behavior in MOD-G1.
- UI defaults to mask; explicit authorized `view_bank` action may reveal full number only through a controlled bank endpoint, never generic detail/list/lookup. Add/edit require `edit_bank` plus Supplier `edit` and CSRF; reveal requires `view_bank` plus Supplier `view` and server audit. Do not put full bank number in logs/notifications/export/error payloads or local browser storage.
- Secure routes (to freeze at MOD-G2): `GET /api/v1/suppliers/{id}/bank-account` returns masked bank metadata **only** after `supplier.view` AND `supplier.view_bank`; `GET .../bank-account?reveal=1` requires the same rights, fresh server authorization and a recorded reveal event before returning plaintext. `PUT /api/v1/suppliers/{id}/bank-account` requires `supplier.edit` AND `supplier.edit_bank`, CSRF, `expected_version`, atomic cipher+version+audit write. Removing existing optional bank data is supported via explicit `PUT` body `{ "clear": true, "expected_version": N }` with the same permission/transaction checks (no implicit clear on omitted fields). All bank responses use `Cache-Control: no-store`, never embed bank plaintext in generic Supplier endpoints or URL; 403/404 distinctions must follow no-leak contract. No separate bank approval workflow in G1.
- Bank read/reveal/write/clear must produce **durable, access-controlled events**: actor, Supplier ID, operation, request_id, UTC time, outcome; **never** full number, token, ciphertext, key, nonce or old/new bank payload. Current source has `configuration_audit_events` with configuration-specific meaning; **its suitability for business bank access is NOT established**. PC-03 must determine a safe canonical event sink or record an explicit security-scoped schema ADR if a dedicated append-only table is necessary; do not silently add a third table despite the core two-table decision. If durable audit cannot be guaranteed, bank routes remain blocked and G1 cannot be released. Bank-less Supplier creation is supported in the meantime.

## 5. Proposed data model — exact DDL review required at MOD-G1

### 5.1 `suppliers` (Group MASTER, one row per Supplier)

| Field family | Candidate design / invariant |
|---|---|
| Identity | `id` BIGINT UNSIGNED PK; `group_organization_id` BIGINT UNSIGNED NOT NULL, FK to canonical GROUP root (server-derived, never client-controlled), `supplier_code` VARCHAR(50) upper/trim, UNIQUE **(group_organization_id, supplier_code)**; preflight P1 single active GROUP root and DB collation/code normalization; future multiple Groups require separately reviewed PBAC partitioning (do not infer multi-Group support from this index). |
| Profile | `name` VARCHAR(255), `supplier_type` VARCHAR(20), `supplier_group_code` VARCHAR(80), `phone` VARCHAR(50) NOT NULL (format-only, not unique), `email` VARCHAR(255) NULL, `tax_code` VARCHAR(50) NULL, `address` TEXT NOT NULL, `province_id`/`ward_id` BIGINT UNSIGNED; required fields non-null when new. |
| Commercial reference | `payment_term_code` VARCHAR(80) NULL; no FIN engine. |
| Scope | `all_companies` TINYINT(1) NOT NULL; `false` requires 1..512 verified usage rows, `true` requires zero rows, validated in service under transaction. |
| Lifecycle & CAS | `status` VARCHAR(20) NOT NULL populated from Lifecycle; `row_version` INT UNSIGNED NOT NULL DEFAULT 1; all master/usage/state/bank mutations bump one shared value, guarded by expected_version and parent row lock. Avoid database default `ACTIVE` silently masking missing Workflow preflight. |
| Security | Optional bank ciphertext/nonce/tag/key-version and last4; no plaintext bank column. |
| Audit | `created_by`, `updated_by` (user ID), `created_at`, `updated_at` UTC; bank events require a **proven durable security sink** before release; do not misuse `configuration_audit_events` without ADR. |

Indexes: PK; group + canonical code UNIQUE; targeted `(status,name,id)` and catalog indexes only after workload/EXPLAIN; validated FK/reference to Group/Province/Ward. **Do not add UNIQUE(phone) or UNIQUE(tax_code).** Positive ID types compatible with MariaDB and JS string-ID response. DBPrefix comes from CodeIgniter, not hardcoded physical names. No migration to existing Customer tables.

### 5.2 `supplier_company_usages`

- Composite PK `(supplier_id, company_organization_id)` with FKs to Supplier/Organization; reverse index `(company_organization_id, supplier_id)` only when read plan benefits.
- Validate Company `type/status/group` in service (FK cannot enforce type). Full replace mutation uses transaction+parent lock+CAS; no row duplications.
- This is current eligibility snapshot, *not* historical ownership or permission mapping.

### 5.3 Migration/rollback

- Latest migration in supplied current source: **App 000025 Customer Phone uniqueness**; before adding SUP, check `php spark migrate:status` against target DEV. Reserve **next genuinely unused** migration ID; do not assume 000025 available.
- Additive migration + separate explicit idempotent Resource/Action/Lifecycle seeder; Numbering default provisioning is **opt-in**, not implicit in migration/bootstrap; preflight existence and conflicts; **no seeding of actual business Suppliers or automatic grants**.
- Rollback Git ≠ DB rollback. Down migration only with empty tables/no dependent Contact/PUR references and operator backup; otherwise forward-fix. A bank crypto version migration must not leave unencrypted fields.

## 6. SQL-first authorized read contract

- `SupplierScopeProvider` canonical Group MASTER; `AuthorizedSqlScope`/compiler before `COUNT`, page, direct detail, lookups; direct ID non-viewable must be no-leak 404/403 as approved envelope. Do not materialize all Supplier rows then post-filter in PHP.
- Filter capability (G2 wire-contract recommendation based on actual Customer source): `q`, `page` (default 1), `page_size` (default **25**; max 100, matching Customer backend), `sort` (`name`, `-name`, `supplier_code`, `-supplier_code`, `id`, `-id`), `status`, `company_id`, `supplier_group_code`, `supplier_type` **as flat query keys** (matching current Customer API; frontend `ListQuery.filter` maps to flat keys via adapter). Missing `status` = all authorized states. Unknown filters/sorts/page size → 400. Validate `q` length and SQL wildcard handling, Unicode/collation.
- Scope `supplier.view` applies **before** Company-eligibility filter; group visibility does not automatically grant Purchase in that Company. Use `EXISTS` for `company_id` membership to avoid duplicated rows/false totals.
- COUNT/PAGE share same predicate and binds; return `meta.total` from SQL COUNT prior LIMIT. Sort whitelist + deterministic `id` tie-break. Read snapshot semantics at READ-COMMITTED documented; do not promise impossible perfect count/page snapshot in concurrent commits.
- List projection narrow; **exclude bank**. Detail includes `company_ids` via bounded batch/single query; labels/permissions batch, no per-row N+1. Missing PBAC → fail closed; an unexpected PHP `canView`/SQL discrepancy triggers error, not post-filter.
- Plan and benchmark COUNT/PAGE, typeahead lookup, Company eligibility and Contact parent subquery on a representative DEV clone; record `EXPLAIN` access/key/rows, p50/p95, query counts and no N+1, rather than claiming performance based on current tiny fixture.

## 7. REST API draft to freeze at MOD-G2 (approved **behavior**, exact wire contract pending review)

| Method | Path (relative `/api/v1`) | Source authority | Gate / status |
|---|---|---|---|
| GET | `/suppliers` | `supplier.view` + SQL scope | G1 Core candidate |
| GET | `/suppliers/{id}` | `supplier.view` + direct-ID no-leak | G1 Core candidate |
| POST | `/suppliers` | `supplier.create`; body profile+usage; atomic Code/Usage/State | G1 Core candidate |
| PATCH | `/suppliers/{id}` | `supplier.edit`; profile only + `expected_version` | G1 Core candidate |
| POST | `/suppliers/{id}/actions/set-state` | `supplier.setstate` + Workflow + version | G1 Core candidate |
| GET | `/suppliers/{id}/company-usages` | `supplier.view` | G1 Core candidate |
| PUT | `/suppliers/{id}/company-usages` | `supplier.edit` + CAS; full replace | G1 Core candidate |
| GET / PUT | `/suppliers/{id}/bank-account` | `view_bank` / `edit_bank` PLUS parent view/edit; mask by default | G1 secured subtask candidate |
| GET | `/suppliers/{id}/history` | Supplier view AND source-document per-Company VIEW | **DEFER to PUR/FIN**; no fake empty 200 |

**Payload rules:** `POST` accepts only reviewed profile fields, explicit `all_companies` and `company_ids`, optional `supplier_code` / `numbering_series_code`; no `status`, `created_by`, `group_organization_id`, plaintext bank or arbitrary policy fields. PATCH includes `expected_version` and explicit editable profile only. PUT usage requires full scope and `expected_version`. SetState body is `{ "status": "APPROVED", "expected_version": 3 }` (target must equal the **unique** legal `setstate` target). PUT bank requires version and authenticated encryption, or explicit `clear=true` version. All mutated responses include updated `row_version`; versions are not Company-specific. FE preserves draft on 409, refreshes version and asks user to reconcile.

**Envelope/Error contract:** reuse `ApiResponder`/CodeIgniter/Shield session/CSRF and canonical `data/meta/error.code`; 400 query/JSON invalid, 401 auth, 403 deny, 404 non-viewable ID, 409 code conflict/stale version/illegal transition per current mapper, 422 required/profile/Location/Company/term invalid, 500 persistence failure with safe request ID. Exact machine codes (`SUPPLIER_*`), i18n labels, body shapes and OpenAPI operation IDs are to be **frozen in MOD-G2**, not inferred from examples. No `additionalProperties` bypass of input allowlist.

**Routing:** one `backend/app/Config/Routes/ApiV1/SuppliersRoutes.php`, included once in `Routes.php` existing api/v1 group; explicit handlers; autorouting OFF; no P1 pinned handler or filter changes. Add approved P2 routes to verifier as additions only, never rewrite frozen 150 signatures/180 registrations.

## 8. UI / UX parity with Customer

- `/suppliers`: one table, one pagination, all states default; columns code/name/group/phone/email/usage/status plus server `allowed_actions`. Use shared `ListQueryControls`, `ListShell`, `ListTable`, `ListPagination`, `useListState`/query state, `TableStatus`, `TableRowActions`; cap 100 consistent between list controls & backend. Avoid local all-record pagination.
- Form Create/Edit matches Customer layout and responsive behavior. Required red asterisk: **Name, Supplier Type, Supplier Group, Phone, Main Address, Province, Ward, Company Usage**; Supplier Code auto-or-manual per §1; status from server. Email/Tax/Term/bank optional. Server errors mapped per field; Phone can repeat across Suppliers.
- Company Usage tab uses All/list switch + remote Company lookup + `expected_version` conflict recovery; server owns Company type/active. Contacts tab reuses Shared Contact UI and SUPPLIER owner; no duplicate local Contact form API.
- Workflow actions are dynamically sourced from assigned Lifecycle and effective PBAC (never a static ACTIVE/INACTIVE toggle). Modal via shared Teleport/portal; 409 retains editor draft; 403/404/422 are distinguished. List status filter populated dynamically from Lifecycle. **Use flat list query keys on wire** (`status`, `company_id`, `supplier_group_code`, `supplier_type`), default page_size=25 and cap 100; `ListQuery.filter` is a frontend internal object, not the API syntax.
- Bank area defaults to masked; no bank on generic list/detail. Optional post-create editing only for actor with bank permission, with explicit guarded reveal; never expose via browser console/analytics/localStorage. Hidden button does not replace backend check.
- History section clearly displays `NOT_AVAILABLE` for PUR/FIN sources not yet implemented; no invented rows or `total=0`. vi/en, keyboard/aria, desktop/mobile, loading/error/empty/refresh and stale response handling are required.

## 9. History (later source-owned integration)

- Real PO, Goods Receipt, Purchase Return, Money Expense/Payment sources persist `supplier_id`, authoritative `company_organization_id` and transaction identifiers. No `supplier_history` copy table in G1.
- Future adapter applies **each source's Resource.Action VIEW and persisted document Company** before counts/links. Supplier current All/list and status do not rewrite old document visibility.
- Only emit navigation link when actor can VIEW the source. No unauthorized rows/counts. Pagination stable across sources; keyset/UNION ALL and amount masking reviewed when implementing PUR/FIN. Mark all `P2-AT-SUP-002-*` `DEPENDENCY_DEFERRED`, not PASS.

## 10. Acceptance traceability (baseline retained)

| Baseline AC | Design evidence / test focus | Completion dependency |
|---|---|---|
| `P2-AT-SUP-001-01` | normalized code + Group UNIQUE + concurrent duplicate rejection | Core + DEV |
| `P2-AT-SUP-001-02` | All/list Company mode, only validated real COMPANY, atomic replace | Core + DEV |
| `P2-AT-SUP-001-03` | `SupplierUsabilityGuard` Company outside scope fail-closed | Guard Core; **final real-PO proof with PUR** |
| `P2-AT-SUP-001-04` | Shared `SUPPLIER` Contacts, 2+ Contacts, parent view AND contact permission | Core + DEV |
| `P2-AT-SUP-001-05` | Lifecycle-managed INACTIVE not eligible for new PO; historical docs retained | Core state/guard; **final with PUR** |
| `P2-AT-SUP-002-01` | no history Company leak; source-authorized COUNT | PUR/FIN source required |
| `P2-AT-SUP-002-02` | source VIEW link guard | PUR/FIN source required |
| `P2-AT-SUP-002-03` | history still visible when Supplier INACTIVE | PUR/FIN source required |

## 11. Engineering No-Go conditions (not open business choices)

See [Pre-code Readiness](P2_SUP_PRECODE_READINESS_v1.0.1.md). Security/DBA must validate bank algorithm/key and DDL; Backend/Tech must verify Numbering/Workflow/Contact owner SQL; OpenAPI exact wire contract must freeze; actual migration sequence on DEV must be checked. Do not claim `MOD-G1..G7` PASS from document approval. Operator owns HTTP/UAT through frontend; source tests/CI, DEV DB performance and runtime evidence remain separate.

## 12. Delta from draft v0.1

- Lifecycle initial state no longer `ACTIVE` hardcoded; status options dynamic and purchase-eligibility separate.
- Required supplier profile fields specified, including Phone/Address/Province/Ward; Phone explicitly nonunique.
- Supplier Type `INDIVIDUAL`/`COMPANY`, Group `STANDARD`; Payment Terms four approved codes.
- One optional encrypted bank record with distinct permissions; no multi-bank extension.
- Base source moved past Customer phone migration 000025. No P1/CUS code change in this document package.

## 13. Addendum kỹ thuật v1.0.1 — hợp đồng khả thi và ranh giới review

### 13.1 Hợp đồng số, Group và trạng thái

- **Group root ≠ Supplier Group:** `group_organization_id` do server xác minh từ P1 (đúng một GROUP root ACTIVE) nhằm áp uniqueness; `supplier_group_code=STANDARD` chỉ là catalog phân loại, không đi vào PBAC owner. Supplier ScopeProvider tiếp tục theo MASTER/GROUP P1, không hứa cô lập nhiều GROUP trong tương lai.
- **Series:** không có default thì manual hợp lệ; nếu tồn tại default lỗi/INACTIVE/ambiguous thì **fail**, không nhận manual. Nếu có default ACTIVE thì AUTO; `AUTO_IF_EMPTY` của CUS không cấp quyền lách default cho SUP. `numbering_series_code` chỉ chấp nhận series hợp lệ thuộc resource Supplier và scope GROUP; cách xử lý selected series khác default phải được lock cùng Numbering ở PC-04.
- **Workflow dynamic:** status filter lấy Lifecycle; `transitionTarget` hiện chỉ hỗ trợ một target cho mỗi `(from_state, action=setstate)`. Nếu Lifecycle phân nhánh cùng action, ghi `SUPPLIER_STATE_TRANSITION_AMBIGUOUS`/409 và không đổi dữ liệu. Nếu Lifecycle không có ACTIVE, Supplier tạo được nhưng không dùng trong Purchase. Inactive nghĩa là mã `INACTIVE` nếu được gắn trong Lifecycle; khi không có mã này, nghĩa vụ AC về inactive chưa thể kiểm thử và phải ghi `DEPENDENCY_BLOCKED_FOR_PUR` cho AC, không tự giả lập transition.

### 13.2 Hợp đồng API minh họa (để Tech/FE review và freeze G2)

```http
GET /api/v1/suppliers?q=abc&page=1&page_size=25&sort=name&status=DRAFT&company_id=2&supplier_group_code=STANDARD
```

- `page` >=1, `page_size` 1..100, default 25; sort whitelist `name|-name|supplier_code|-supplier_code|id|-id`, tie-break `id`; invalid/unknown query -> 400. `company_id` is only an eligibility filter, never an authorization context. Unknown Lifecycle status -> 400.
- Example POST fields: `name`, `supplier_type`, `supplier_group_code` (omit→STANDARD), `phone`, `address`, `province_id`, `ward_id`, `all_companies` (boolean), `company_ids` (array), optionally `supplier_code` or `numbering_series_code`, `email`, `tax_code`, `payment_term_code`. No bank/status/actor in generic POST/PATCH.
- Atomic writes: `PATCH {fields..., "expected_version": N}`, `PUT /company-usages {"all_companies":false,"company_ids":["2"],"expected_version":N}`, `POST /actions/set-state {"status":"APPROVED","expected_version":N}`, `PUT /bank-account {"bank_name":"...","account_number":"...","account_holder":"...","expected_version":N}` or `PUT {"clear":true,"expected_version":N}`. Fields unmentioned in PATCH remain unchanged; no bank clear via null/missing generic field.
- Draft **G2 candidate**, not a claim the endpoints already exist. Canonical errors, whether missing resource returns 404, field name aliases, `revealed` response, `Cache-Control: no-store` and all operation IDs require review and generated FE-contract tests before implementation.

### 13.3 Security & availability

- Bank CRUD only through secure bank service; masked GET and explicit reveal both need `view_bank` + `view`. `edit_bank` + `edit` guards bank writes. Audit before revealing plaintext, audit in same transaction as bank writes, no sensitive payloads. **G1 release is blocked until persistent audit is approved and tested**, even though a Supplier without bank can be created.
- Never force all Supplier operations to require a bank encryption key: key outage blocks bank read/reveal/write, not Supplier list or profile. No full bank number in list, detail, logs, caches, export, alerts or error data.
- Standardized status errors must map through the current CI4 DomainException/ApiResponder mapper, not merely throw a raw 500; no security assumption based on frontend button visibility.

### 13.4 Guard & source boundary

`SupplierUsabilityGuard` receives Supplier ID and **trusted persisted purchase Company ID** from a transaction-owned future PUR service, not caller/client-supplied arbitrary Company; check ACTIVE Supplier, Company ACTIVE and usage with proper lock/serialization. G1 implements guard with a testable trusted interface but does not claim a Purchase transaction exists or that actual `UR-SUP-001-03/05` has been fully proven in PUR. Supplier History remains dependency deferred. No mock empty history, new Supplier History table, or extra notes.
