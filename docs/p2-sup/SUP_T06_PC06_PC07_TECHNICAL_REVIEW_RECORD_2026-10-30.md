# P2-SUP / SUP-T06 — PC-06 + Contact-contract PC-07 technical review record

**Prepared:** 2026-10-30
**Source baseline:** `erp_v2.1.0.zip` SHA-256 `3de9f21c303a76f2103d5e2ab335f391626115937c5ceb6baf5dc5656b76589b`, followed by approved-for-source-test patch sequence T06A, T06B1, typed-mock lint fix, T06B2.
**Record status:** `PREPARED_FOR_REVIEW / NO_SIGNATURES / PC-06_PENDING / CONTACT_PC-07_PENDING / SUPPLIER_PC-07_T07_PENDING`.
**Authority:** This is a *decision record template*, not a technical approval, security signoff, schema approval, DEV UAT, or release acceptance.

## 1. Exact decision boundaries

The 12 Product decisions `SUP-DEC-01..12` remain approved. T06A backend and T06B1 frontend preparation have source/offline evidence; T06B2 provides a **review-only** schema candidate. None of this substitutes for the technical review required by `P2_SUP_PRECODE_READINESS_v1.0.1.md`.

| Gate | Scope to decide now | Decision boundary |
|---|---|---|
| **PC-06 (Backend + Security)** | `SUPPLIER` as a Shared Contact owner; exact `supplier_contact` access, independent Supplier parent VIEW, persisted trusted GROUP root, SQL COUNT/page/detail parity, Workflow/Notifications, existing M8.7 audit | Source/offline review can be signed only by authorized reviewers; actual MariaDB/HTTP negative tests and performance remain separate DEV UAT gates |
| **PC-07 Contact sub-contract (Tech Lead + API/FE)** | Existing Contact API-010 contract: four owner enum, Lifecycle description, optional action hints; preservation of the five Contact operations and existing filter wire | Approval here is **not approval of all Supplier API T07 DTOs or routes** |
| **PC-07 Supplier T07 (Tech Lead + API/FE)** | Supplier-specific flat list filters, defaults/caps, generic create/PATCH, CAS SetState, Bank mask/reveal/clear, error codes and endpoint ownership | **PENDING; explicitly outside this T06 Contact decision** |
| **MOD-G2** | Authorized canonical OpenAPI change and client regeneration after correct technical signoff | **PENDING**, not passed by this record |

## 2. Evidence checked / not checked

| Evidence | Result | Limit |
|---|---|---|
| Windows T06A SUP-T06 SQL plan / parent fixture / source | `35/35`, `15/15`, `39/39` PASS (operator log) | Pure/fake DB/source, no authenticated MariaDB |
| Windows T06B1 owner portable / targeted Vitest | `19/19`, `42/42` PASS | Frontend tests, not browser runtime |
| Windows T06B2 contract review | `20/20` PASS | Review candidate, **not canonical** schema |
| Windows lint, type-check, imports and Vite build | PASS; Oxlint 0 errors; 270 files / 937 edges; 4,396 modules | No Supplier page activation |
| T06A P1/CUS regressions | PASS in prior operator log | No HTTP/PBAC or InnoDB parity |
| Migration `000028`, Supplier catalog/Lifecycle and PBAC provision | **NO NEW RUNTIME EVIDENCE** | Requires T00 Security/DBA/DEV clone preflight |
| MariaDB actor A/B PBAC, COUNT/page/detail, EXPLAIN, workflow and browser | **NOT RUN** | Cannot be approved as DEV UAT/release |

### Sources to open in review

- `docs/p2-sup/P2_SUP_PRECODE_READINESS_v1.0.1.md` — PC-06 and PC-07 gates.
- `docs/p2-sup/P2_SUP_DESIGN_DECISIONS_v1.0.1.md` and `P2_SUP_TECHNICAL_DESIGN_v1.0.1.md` — approved scope and SUP-DEC-04.
- `docs/p2-sup/SUP_T06_SHARED_CONTACT_BACKEND_SOURCE_QA_2026-10-30.md` — backend source implementation and negative tests.
- `docs/p2-sup/SUP_T06B1_CONTACT_FE_OWNER_PREP_QA_2026-10-30.md` — frontend compatibility preparation.
- `docs/p2-sup/SUP_T06B2_CONTACT_OPENAPI_REVIEW_2026-10-30.md` — exact canonical/candidate differences and unresolved legacy contract issues.
- `contracts/openapi/p2-sup-contact.review.openapi.json` — **review only** (`paths: {}`, `x-do-not-generate-client`).
- `backend/app/Modules/Contact/ContactSql.php`, `ContactScope.php`, `ContactService.php`, `SupplierContactParentView.php` — source implementation.

## 3. PC-06 — security/backend decision checklist

Reviewer must explicitly examine and record findings for **all** items below; the list is not pre-approved.

| ID | Source-review question | Proposed required outcome | Review finding |
|---|---|---|---|
| PC06-01 | Is `SUPPLIER` mapped **only** to `supplier_contact`, with unknown owners fail-closed? | No fallback to `employee_contact` / generic `contact` | PENDING |
| PC06-02 | Does every authorized Supplier Contact read enforce `supplier_contact.view` **AND** `supplier.view`? | Two independent canonical PBAC decisions including DENY/Lifecycle | PENDING |
| PC06-03 | Is `suppliers.group_organization_id` matched to server-derived trusted GROUP root? | No client-provided Company/GROUP authorization; Company Usage not a grant | PENDING |
| PC06-04 | Are Contact row and Supplier parent state predicates applied in SQL **before** COUNT, LIMIT/OFFSET and detail? | No leaked `meta.total`, direct-ID or post-page authorization filter | PENDING |
| PC06-05 | Does CREATE/PATCH/SetState revalidate parent VIEW, immutable owner, canonical Lifecycle and rollback boundaries? | Deny missing/cross-GROUP/denied parent; no orphan Contact writes | PENDING |
| PC06-06 | Do Workflow options/transitions and Notification assignee/delivery match runtime parent checks? | No side-channel exposure or bypass | PENDING |
| PC06-07 | Does M8.7 Contact SQL/oracle include Supplier Contact and Supplier parent while retaining **9** audit resources? | Runtime/audit parity; no frozen registry changes | PENDING |
| PC06-08 | Are parent lookups bounded by page and P1/CUS behavior preserved? | No new N+1; benchmark on DEV before performance acceptance | PENDING |
| PC06-09 | Are DBPrefix, bindings, unknown status/owner and PBAC scope configuration fail-closed? | No concatenated untrusted SQL identifiers or permission fallback | PENDING |

**Minimum DEV UAT case set (does not block preparing the review record, but does block runtime acceptance):** actor A allowed; actor B denied parent despite Contact permission; inverse denial; explicit/absolute DENY; missing/deleted/cross-GROUP Supplier; noncanonical owner/status; create/update/state-change negative tests; workflow and notification denial; COUNT/page/detail totals and M8.7 parity; two concurrent requests; explain plans and query counts; ORG/EMP/CUS regressions. All run only on approved DEV clone with representative fixtures.

## 4. PC-07 Contact sub-contract — API/FE decision checklist

| ID | Review decision | Proposed outcome | Reviewer decision |
|---|---|---|---|
| PC07-C01 | Shared `ContactOwnerType` in `Contact` + `ContactCreate` | Exactly `EMPLOYEE`, `ORGANIZATION`, `CUSTOMER`, `SUPPLIER`; no changed casing | PENDING |
| PC07-C02 | Contact operation/route compatibility | Preserve `listContacts`, `createContact`, `getContact`, `updateContact`, `setContactState`; no new Contact routes | PENDING |
| PC07-C03 | List query compatibility | Keep API-010 nested `filter`, default 50/max 200 and all statuses; **never** apply Supplier T07 flat query/default 25/max 100 | PENDING |
| PC07-C04 | Lifecycle/failed create | Correct description: missing initial State -> `WF_INITIAL_STATE_REQUIRED`, not fallback `ACTIVE` | PENDING |
| PC07-C05 | PATCH semantics | Preserve legacy `ContactPatch` fields; no owner transfer or direct status change | PENDING |
| PC07-C06 | Optional `allowed_actions` read hints | Decide whether to include optional boolean `edit`, `setstate` (advisory only, not grants) | PENDING |
| PC07-C07 | Legacy schema mismatches | Decide whether existing `BigInt` negative-ID acceptance, max workflow status length and modal-only required fields are deferred to a **separate** compatibility decision | PENDING |
| PC07-C08 | SDK rollout and compatibility | Canonical YAML changed only after approval; regenerate with existing script; no manual generated-type edits/casts; run ORG/EMP/CUS/SUP consumer regressions | PENDING |

**Default scope recommendation for this review:** approve only an *additive* four-owner enum, truthful Lifecycle description, and (if agreed) optional boolean read hints; freeze legacy requiredness, BIGINT/status patterns and five operation parameters. Any incompatible tightening belongs to a separate, versioned contract change.

## 5. Signoff record — reviewers must complete

| Review | Reviewer / role | Decision (APPROVED / APPROVED_WITH_CONDITIONS / REJECTED) | Conditions / evidence links | Date + reviewed commit |
|---|---|---|---|---|
| PC-06 Security | **UNASSIGNED** | **PENDING** | — | — |
| PC-06 Backend | **UNASSIGNED** | **PENDING** | — | — |
| PC-07 Contact API / Tech Lead | **UNASSIGNED** | **PENDING** | — | — |
| PC-07 FE consumer | **UNASSIGNED** | **PENDING** | — | — |
| MOD-G2 authorization | **UNASSIGNED** | **PENDING** | — | — |

No signature or approver identity may be inferred from an offline PASS, test log, filename or this pre-filled document.

## 6. Safe next transitions

1. **Now:** reviewers examine PC-06/PC-07 candidate and record real findings, owner, decision, conditions, reviewed commit. No automatic approval.
2. **Only after Contact PC-07/MOD-G2 signoff:** create an incremental **T06C** patch for canonical `contracts/openapi/openapi.yaml`, regenerate the client through its official generator, expand shared Contact prop types, and execute `openapi:lint`, `openapi:generate:check`, legacy/CUS/SUP tests, type/lint/import/build. Do not mix in Supplier T07 flat filters or Bank DTOs.
3. **Only after DEV readiness and Security/DBA gates:** run MariaDB/HTTP multi-actor and browser UAT, with negative PBAC and query/EXPLAIN evidence. This is **not** authorized by step 2.
4. **Before full Supplier G1 release:** independently close T00 Bank Security/audit-sink/migration/catalog blockers, broader PC-07 Supplier API T07, required workflow, Purchase integration, runtime testing and release signoffs.

**Current final gate:** `SUP-T06A_SOURCE_OFFLINE_PASS / SUP-T06B1_SOURCE_OFFLINE_PASS / SUP-T06B2_REVIEW_OFFLINE_PASS / PC-06_PENDING / CONTACT_PC-07_PENDING / SUPPLIER_PC-07_PENDING / MOD-G2_PENDING / DEV_UAT_NOT_RUN / RELEASE_BLOCKED`.
