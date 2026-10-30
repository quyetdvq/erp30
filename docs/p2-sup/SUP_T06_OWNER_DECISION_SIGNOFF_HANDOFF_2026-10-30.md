# P2-SUP / SUP-T06 — Owner implementation decision and technical signoff handoff

**Recorded on:** 2026-10-30
**Decision source:** Explicit direction of the DinkERP project requester in the current project conversation (no reviewer identity or signature asserted).
**Source baseline:** `erp_v2.1.0.zip`, SHA-256 `3de9f21c303a76f2103d5e2ab335f391626115937c5ceb6baf5dc5656b76589b`; incremental source/test patches T06A, T06B1, typed-mocks lint fix and T06B2.
**Owner decision:** `IMPLEMENTATION_APPROACH_CONFIRMED`.
**Technical state:** `PC-06_SECURITY_PENDING / PC-06_BACKEND_PENDING / CONTACT_PC-07_API_PENDING / CONTACT_PC-07_FE_PENDING / CONTACT_MOD-G2_PENDING`.
**Runtime:** `DEV_UAT_NOT_RUN / RELEASE_BLOCKED`.

> **Authority boundary:** This document records the requester's selection of the *implementation approach*. It does not attest that Security/Backend/API/FE reviewed the code, does not sign MOD-G2, and does not authorize database migration, catalog/PBAC provisioning, production activation or any runtime acceptance. Never convert an offline PASS into a technical signoff.

## 1. Requester-confirmed decisions (all five confirmed)

| ID | Requester decision | Record status |
|---|---|---|
| O-T06-01 | Accept **all 9 PC-06 Backend/Security implementation criteria**, with a conditional *source-review* path and independent DEV UAT. | **DECISION CONFIRMED**; reviewer evaluation pending |
| O-T06-02 | Accept **PC-07 Contact C01–C08** as proposed in the contract review, with additive compatibility. | **DECISION CONFIRMED**; API/FE source review pending |
| O-T06-03 | Select **MOD-G2 option A**: freeze the *Contact sub-contract* separately **after** its authorized technical signoff, leaving Supplier T07 API MOD-G2 separate. | **DECISION CONFIRMED**; technical freeze **not yet authorized** |
| O-T06-04 | Preserve CUS/P1 compatibility, existing **five** Contact operations and `SUP-DEC-01..12` without business redesign. | **DECISION CONFIRMED** |
| O-T06-05 | No automatic migration, PBAC/catalog provisioning, production activation or claims of runtime PASS. | **DECISION CONFIRMED** |

These choices are final for the implementation plan **unless technical reviewers produce a specific, evidenced conflict**. Do not ask the requester to reselect the same options merely because a reviewer has not yet signed.

## 2. PC-06: fixed implementation acceptance targets (not reviewer findings)

| Check | Required behavior accepted by requester | Technical reviewer status |
|---|---|---|
| PC06-01 | `contact_type=SUPPLIER` resolves only to `supplier_contact`; unknown/noncanonical owner fails closed. | PENDING |
| PC06-02 | Supplier Contact read requires `supplier_contact.view` **AND** authorized VIEW of its referenced Supplier record (`supplier.view`) via canonical PBAC; DENY/ABSOLUTE_DENY still apply. | PENDING |
| PC06-03 | Referenced Supplier must belong to a **server-derived trusted GROUP root**; client Company/GROUP values or Company Usage must not grant VIEW. | PENDING |
| PC06-04 | Supplier Contact and owner/parent predicates apply in SQL **before COUNT/page/detail**, with no `meta.total` or direct-ID leaks and no post-pagination filtering. | PENDING |
| PC06-05 | CREATE, PATCH and SetState recheck Supplier owner VIEW; no owner transfer, orphan write, Lifecycle or transaction bypass. | PENDING |
| PC06-06 | Workflow options/execution and Notification assignment/delivery respect the same effective parent permissions. | PENDING |
| PC06-07 | Runtime and M8.7 Contact audit use matching four-owner scope logic, while keeping the frozen **nine-resource** audit catalog. | PENDING |
| PC06-08 | Supplier parent lookup is bounded/batched; avoid new N+1; performance requires later actual DEV measurement. | PENDING |
| PC06-09 | DBPrefix, SQL binding, malformed owner/status and missing PBAC scope are handled fail-closed; retain P1/CUS regression. | PENDING |

**Reviewer evidence required:** source diff review with negative PBAC cases, CUS/ORG/EMP compatibility, `backend/app/Modules/Contact/`, Access and Workflow/Notifications bindings, M8.7 audit parity and source/test outputs. MariaDB/HTTP and query EXPLAIN are **separate** DEV gates. Source-only signoff may be `APPROVED_WITH_CONDITIONS` only when actual reviewers document the conditions and do not mislabel it as runtime acceptance.

## 3. PC-07 Contact: fixed contract targets (not a Supplier T07 API approval)

| Check | Required Contact contract behavior accepted by requester | API/FE reviewer status |
|---|---|---|
| C01 | One canonical `ContactOwnerType`: `EMPLOYEE`, `ORGANIZATION`, `CUSTOMER`, `SUPPLIER` used by `Contact` and `ContactCreate`. | PENDING |
| C02 | Preserve exact five Contact operationIds and routes: `listContacts`, `createContact`, `getContact`, `updateContact`, `setContactState`. | PENDING |
| C03 | Preserve existing nested Contact `filter`, default page size **50**, maximum **200**, and all Lifecycle statuses by default; never copy Supplier T07 flat filters or 25/100 limits into API-010. | PENDING |
| C04 | Missing Contact Lifecycle initial State fails with `WF_INITIAL_STATE_REQUIRED`; no fallback to ACTIVE. | PENDING |
| C05 | Keep legacy `ContactPatch` fields; no owner transfer or direct status write via PATCH. | PENDING |
| C06 | Optional boolean `allowed_actions.edit` and `.setstate` in read DTO; **advisory hints only**, never authorization grants. | PENDING |
| C07 | Defer legacy BIGINT validation, status length and unrelated requiredness differences to a **separate compatibility review**; do not make breaking changes in T06C. | PENDING |
| C08 | After authorized signoff, update canonical OpenAPI and regenerate SDK **with the official generator**; no manual changes to generated code, no casts to suppress type mismatch; run ORG/EMP/CUS/SUP consumers. | PENDING |

**T06C candidate:** `DinkERP_P2_SUP_T06C_Contact_Canonical_SDK_UI_Candidate.patch` remains **staged, unapplied on the Windows checkout** following the operator's successful `git apply --reverse`, `git diff --check` and `git apply --check` result. This candidate is not a technical signoff or an active SDK freeze.

## 4. Source/test evidence available to reviewers

| Source | Recorded evidence | Scope limit |
|---|---|---|
| SUP-T06A Windows | Supplier Contact SQL `35/35`, parent fake fixture `15/15`, source `39/39` PASS | No MariaDB actor A/B |
| SUP-T06B1 Windows | Portable owner `19/19`; targeted 5-file Vitest **42/42** PASS; frontend lint, type-check, imports, production build PASS | Not browser UAT |
| SUP-T06B2 Windows | Contract review `20/20` PASS; owner `19/19`; frontend lint, type-check, imports, build PASS | Review-only candidate, canonical unchanged |
| T06C preparation | Static source `17/17`, B2 compatibility `20/20`, CUS/Contact/backend regression and patch check PASS in preparation snapshot | Generator, Redocly, Windows frontend tests **NOT RUN** for T06C |
| Supplier/P1 | T06A route architecture `476/476`, CUS and Contact regressions PASS in supplied evidence | Does not establish runtime/PBAC parity |

Signoff reviewers must inspect the actual current source/diff and pin the reviewed commit or explicitly approved immutable source fingerprint. The evidence above is useful pre-review input, not automatic signoff.

## 5. Exact reviewer actions and signoff source of truth

**Primary technical review record (unchanged until reviewers act):**

`docs/p2-sup/SUP_T06_PC06_PC07_TECHNICAL_REVIEW_RECORD_2026-10-30.md`

It already contains the PC06-01..09 and C01..C08 checklists. The responsible reviewers must fill the **five existing rows** of section 5, including their real names/roles, decision (`APPROVED`, `APPROVED_WITH_CONDITIONS`, `REJECTED`), conditions/evidence, date and **reviewed commit**:

| Required reviewer | Approval scope | Current state |
|---|---|---|
| PC-06 Security | PBAC, parent boundary, negative controls and disclosure paths | **UNASSIGNED / PENDING** |
| PC-06 Backend | Contact SQL/read/write, Workflow/Notifications, M8.7 parity | **UNASSIGNED / PENDING** |
| PC-07 Contact API / Tech Lead | Five operations, canonical contract and change compatibility | **UNASSIGNED / PENDING** |
| PC-07 FE consumer | Four-owner SDK types/Contact UI, legacy regressions | **UNASSIGNED / PENDING** |
| MOD-G2 Contact authorization | Permission to modify **Contact-only** canonical OpenAPI and regenerate SDK | **UNASSIGNED / PENDING** |

This owner's decision **does not pre-fill or substitute for any row above**. No reviewer name, signature, date or source commit has been fabricated. Broader **Supplier PC-07 (T07)** stays pending independently.

## 6. GO / NO-GO and exact next execution boundary

### Gate A — NOW: documentation/review only (**GO**)

1. Add this decision record to `docs/p2-sup/` with the **docs-only patch**. Check `git apply --check`, apply once and check `git diff --check`.
2. Provide the original technical review record and T06A/B1/B2 evidence to the five reviewers. They must inspect actual source and record real outcomes/conditions and reviewed commit.
3. Verify T06C candidate remains unapplied. A read-only `git apply --check` of T06C is allowed; **do not `git apply` T06C yet**, do not run the SDK generator, and do not run the T06C post-signoff script before approvals.

### Gate B — AFTER five real source/Contact technical signoffs (**conditional GO**)

1. Operator checks the populated technical review record and conditions. If any row remains `PENDING`, `UNASSIGNED` or `REJECTED`, **STOP**.
2. Operator checks `git apply --check` for T06C, then applies it **once**. Do **not** run the old `DinkERP_P2_SUP_T06C_Apply_After_Signoff.ps1` after applying the patch: that script attempts a second `git apply`. The operator already encountered PowerShell unsigned-script execution policy, so there is no need to weaken machine policy merely to run that script.
3. From `frontend/`: run T06C source test, T06B2 compatibility test, `pnpm run openapi:lint`, `pnpm run openapi:generate:check` (**writes generated SDK**), post-generation SDK parity, T06B1 owner tests, the two Contact Vitest files separately, lint, type-check, import check, production build. Stop on first failure; inspect generated diff.
4. From `backend/`: run `SUP-UNIT`, `CUS-UNIT`, `UNIT-STANDARD`, `P1-STANDARDS`, `ROUTES` via the existing runner. Finish with `git diff --check`; preserve logs and generated file list.
5. If all source/SDK/client gates PASS, record **T06C_SOURCE_SDK_PASS**, **not** DEV UAT or full MOD-G2 Supplier API freeze. T06 remains DEV UAT pending.

### Gate C — NOT AUTHORIZED by this owner decision

- MariaDB migration `000028`, DEV catalog/Lifecycle/PBAC provisioning, production deployment/enablement, bank crypto/reveal/audit or PBAC permission grants.
- Real HTTP/MariaDB actor A/B PBAC and performance status assertions without evidence; full Supplier T07 API/Bank/Workflow CAS MOD-G2 and later release.

## 7. Follow-on scope after Contact source gate

- **PC-05 Supplier Workflow CAS**: only Supplier-owned SetState can execute `expected_version` CAS; generic Workflow path must not bypass Supplier state policy.
- **PC-03 Bank Security / SUP-T04B**: Security + DBA decide authenticated encryption, custody, masking/reveal/clear and durable audit sink.
- **SUP-T07/T08**: copy CUS architecture only; Supplier flat filters and default 25/cap 100 apply to Supplier list, not Contact list. Do not add Supplier notes/interactions or fake PUR/FIN history.
- **SUP-T09**: DEV MariaDB/HTTP, actor PBAC, EXPLAIN/concurrency and browser UAT; release only at independent approved gates.

## 8. Source documents, precedence and closure statement

1. `docs/p2-sup/P2_SUP_DESIGN_DECISIONS_v1.0.1.md` — binding business decisions SUP-DEC-01..12.
2. `docs/p2-sup/P2_SUP_TECHNICAL_DESIGN_v1.0.1.md` and `P2_SUP_PRECODE_READINESS_v1.0.1.md` — technical constraints and PC owners.
3. `docs/p2-sup/P2_SUP_IMPLEMENTATION_PLAN_v1.0.1.md` — T06/T07 and MOD-G0..G7 dependencies.
4. `docs/p2-sup/SUP_T06_PC06_PC07_TECHNICAL_REVIEW_RECORD_2026-10-30.md` — **sole technical signature location**.
5. `docs/p2-sup/SUP_T06B2_CONTACT_OPENAPI_REVIEW_2026-10-30.md` and `SUP_T06C_CONTACT_CONTRACT_STAGED_IMPLEMENTATION_2026-10-30.md` — Contact proposal and staged candidate.

**Decision recorded:** `OWNER_IMPLEMENTATION_APPROACH_CONFIRMED (5/5)`; **technical signoff remains PENDING (0/5 evidenced)**; **T06C candidate remains STAGED**; **DEV UAT NOT RUN / Supplier G1 RELEASE BLOCKED**.
