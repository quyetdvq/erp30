# P2-SUP — T01B.1 CUS Lessons / Preventive Gate

**Date:** 2026-10-30
**Baseline:** `erp_v2.0.0_sup_t01b_catalog_review_source.zip`
**Status:** preventive source hardening PASS; Supplier runtime, DB migration, bank security and API/UI remain NOT IMPLEMENTED or NOT RUN.
**Product scope:** SUP-DEC-01..12 unchanged; exactly two Supplier Core tables, no migration in this patch.

## 1. Why this review is needed

The previously reported `SUP-UNIT`, `UNIT-STANDARD`, `ROUTES`, `P1-STANDARDS` results are **source/offline** tests. They cannot prove `supplier` will work under MariaDB, authenticated HTTP, SQL/PBAC, real Lifecycle configuration, or Vue. Customer required multiple later hotfixes (dynamic initial state, mandatory profile/phone constraints, Contact parity, History serialization, modal layout). These scenarios are to be designed and tested **before** SUP's equivalent functionality is enabled.

## 2. Actual findings from SUP-T01B source and action taken

| Finding | Source evidence | Severity / action |
|---|---|---|
| Rules previously accepted unknown fields such as `created_by`, `row_version`, `bank_*` and `all_companies` | `SupplierRules::validateProfile` only checked three disallowed fields; returned the unfiltered input array | **P0 before any Service use**: replaced with explicit writable-field allowlist; internal fields rejected. A future Service must parse `expected_version`, `company_ids`, `all_companies`, `numbering_series_code` in separate paths. |
| Rules had no PATCH contract or full field limits/email/phone syntax | Same method previously required the entire profile and did not check varchar/text bounds | **P1**: introduced explicit create/partial-update handling, immutable Supplier Code on PATCH, no PATCH default-injection, phone and email syntax, string lengths, BIGINT UNSIGNED max, and merged-profile validation helper. Duplicate Supplier phone remains permitted. |
| Contact catalog demanded one identical Lifecycle ID across Organization, Employee and Customer Contact | `SupplierCatalogPolicy::contactLifecycleId` had `array_unique(...)===1` | **P1**: Supplier Contact now follows **Customer Contact's canonical Lifecycle** without querying or depending on Organization/Employee Contact. Those resources are independently configured, and may even be INACTIVE without blocking SUP. No existing binding is modified. |
| Existing suite did not guard these failure modes | SUP-T01A/B tests only covered basic profiles and aligned contact bindings | Added negative and positive pure/fixture cases; see evidence below. |

### Precise boundary of the hardened Rules

- `SupplierRules::validateProfile($payload, true)` is a **pure profile parser**, not a full HTTP request parser or authorization layer.
- A future Supplier Service MUST extract and validate the separate structures for `all_companies`, `company_ids`, `numbering_series_code` and `expected_version`, then pass **only profile fields** to this parser. Do not feed the untrusted body directly into a query builder.
- On PATCH, the Service MUST first lock and retrieve the trusted persisted Supplier, merge allowable validated fields, then run `assertRequiredProfile($merged)`, Location/Company checks, and CAS. The helper cannot verify the Province→Ward relation or whether a Company is ACTIVE; those checks require trusted database facts.
- The `supplier_code` from Create may be omitted or blank to request approved Supplier Numbering; the final code is generated/validated within the same transaction as the Supplier insert and Company Usage. PATCH never changes `supplier_code`.
- Bank payload is **not** accepted through generic profile create/edit. Bank endpoints stay blocked pending Security/DBA approval of cryptography/key custody and durable access audit.

## 3. Stop-before-code regression matrix for SUP-T02..T08

| Prior Customer incident / adjacent ERP incident | Mandatory SUP scenario | Gate |
|---|---|---|
| CUS `CUSTOMER_INITIAL_STATE_INVALID` / hardcoded `ACTIVE` | Create with Lifecycle initial `DRAFT`; stored state exactly `DRAFT`; reject invalid/overlength state; no automatic status override | T04/T05 |
| CUS required profile and phone uniqueness hotfix | Require name/phone/address/Province/Ward; Ward belongs to Province. Supplier **phone not unique**: allow two Suppliers with the same phone. Preserve fields on partial PATCH | T04, T08 |
| CUS Numbering behavior diverged from approved SUP rule | No default series → validated manual; default configured → auto only; invalid/inactive/ambiguous default → fail; no wrong `{RESOURCE}` token; single transaction/counter race | T02/T04 |
| CUS Contact scope missing from SQL parity audit | Extend *both* Contact runtime and audit paths to fourth owner `SUPPLIER`, with `supplier_contact.view` AND `supplier.view` parent guard; preserve three legacy owners; no COUNT/PAGE discrepancy | T06 |
| CUS/Contact PBAC and query planner complexity | Exact SQL-first `COUNT`/page/detail same authorized predicates; negative actor fixtures, no post-`LIMIT` PHP filter, no `SELECT all`; direct-ID denied | T03/T07 |
| CUS dynamic status filtering | Filter statuses from assigned Lifecycle; default ALL; flat query params like CUS (`status`, `company_id`), default page_size 25 cap 100; valid sort whitelist | T03/T07/T08 |
| Lost update / status mutation | All Supplier mutations require `expected_version` + `row_version` CAS in a transaction; dedicated `setstate` Action; branching transitions reject safely | T04/T05/T07 |
| HIS-03 compact `{}` accidentally serialized to `[]` | History Recorder emits only compact `[old,new]` / `{}`; API envelope preserves object shape; UI handles invalid data without blank screen | T07/T08 |
| Product UOM PUT had empty body | Freeze canonical JSON request payload per endpoint, test bytes sent by transport, including explicit empty-array semantics for usage replacement | T07/T08 |
| Multi-tab Customer/Product modal layout broke with long content | Reuse `tabbed-detail-workspace`, shared modal/action/status components, one scrollable body, modal focus/Teleport; actual Vue Vitest and browser UAT | T08 |
| Bank access not covered by generic History | Encrypt at rest, redact logs, no-store, dedicated bank rights, durable security audit on read/reveal/change; **NO bank endpoint** until reviewed | T04B/G7 |
| Data isolation in Company Usage | All/list semantics, Company ACTIVE of correct GROUP, transactional full replace and CAS; Company eligibility is not a VIEW grant | T04/T07 |

## 4. Evidence obtained now

- Pure profile tests **48/48 PASS** (previously 14): create/PATCH, input allowlist, type/phone/email/schema sizes, ID overflow, Company Usage, required persisted merge.
- Catalog tests **38/38 PASS** (previously 36): independent P1 Contact lifecycle ownership and canonical Customer Contact selection.
- Fake-connection provision tests **16/16 PASS** (previously 14): Supplier Contact binds to Customer Contact Lifecycle even if Organization Contact has a separate one. Fake connection is **not MariaDB**.
- M5.4.1 ownership **44/44 PASS**; P2-CUS pure rules **94/94 PASS**; M3.4B offline **168/168 PASS**; M19.24 routing **476/476 PASS**; source inventory CHECK PASS.
- `php -l` changed PHP source PASS; `git diff --check` PASS.
- Real migration `000028`, provisioning, PBAC HTTP, front-end build and bank threat-model review: **NOT RUN / OPEN**.

## 5. Recommendation and sequencing

**Do not advance directly from SUP-T01B to writing the entire Supplier Service or Vue.** Use the preventive matrix as a mandatory acceptance contract while implementing in small reviewed patches: T02 Numbering → T03 SQL/PBAC → T04 transaction/CRUD/CAS → T05 Workflow → T06 Shared Contacts → T07 API/History/Bank (bank security gate separate) → T08 Frontend. No backend API is declared accepted until client/transport tests and at least the planned DEV negative PBAC scenarios exist.

**DB safety:** This patch does not change `000028`, install a schema, grant permissions, or run `supplier:catalog-provision`. Both operations remain blocked until bank storage/secure audit DDL has Security and DBA approval and the DEV database has been backed up.
