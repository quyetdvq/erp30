# P2-SUP / SUP-T06B2 — Shared Contact four-owner contract review (NOT MOD-G2 signoff)

**Date:** 2026-10-30
**Base:** `erp_v2.1.0.zip` SHA-256 `3de9f21c303a76f2103d5e2ab335f391626115937c5ceb6baf5dc5656b76589b` + T06A + T06B1 + the independent Vitest typed-mock lint fix.
**Status:** `REVIEW_PACKAGE_SOURCE_PASS / MOD-G2_PC-07_PENDING / PC-06_PENDING / SUP-T06_PARTIAL / DEV_UAT_NOT_RUN`. This is not a security review signature or a release approval.

## 1. Input evidence and authority

The Windows operator applied the T06B1 and lint patches with `git apply --check`, `git apply` and `git diff --check` PASS. The operator subsequently ran the five targeted Vitest files separately: **2 + 9 + 5 + 13 + 13 = 42/42 PASS**. Frontend Oxlint **0 warnings / 0 errors**, ESLint, type-check, import check **270 files / 937 import edges / 0 errors**, Vite production build **4,396 transformed modules** all PASS. T06A SUP/CUS/P1 offline regressions were previously operator-PASS. This is SOURCE/OFFLINE evidence only, not Contact HTTP/PBAC, MariaDB or browser UAT.

**Authority:** `docs/DinkERP_P2/modules/P2_SUP_INDEPENDENT_MODULE_SPEC.md`, `P2_SUP_DESIGN_DECISIONS_v1.0.1.md` (SUP-DEC-04), `P2_SUP_TECHNICAL_DESIGN_v1.0.1.md`, `P2_SUP_PRECODE_READINESS_v1.0.1.md` (PC-06/PC-07), `docs/development-standards/03_MODULE_DELIVERY_GATES.md`, existing API-010 Contact routes, backend T06A code and T06B1 frontend code. The PO's 12 decisions are approved; exact public OpenAPI DTOs are **not** signed off.

## 2. Contract discrepancy and review proposal

| Item | Canonical / generated contract today | Source/runtime today | T06B2 review candidate (not applied to canonical) |
|---|---|---|---|
| `Contact.contact_type` | `EMPLOYEE`, `ORGANIZATION` | ContactRules supports `EMPLOYEE`, `ORGANIZATION`, `CUSTOMER`, `SUPPLIER` | Four-owner `ContactOwnerType` schema, used by read and create |
| `ContactCreate.contact_type` | Same two owners; description rejects others | Both Customer and Supplier are valid when parent checks pass | Four-owner enum; add exact resource/parent VIEW explanation |
| Missing Lifecycle | `ContactCreate.description` says fallback native `ACTIVE` | `ContactService::create()` throws `WF_INITIAL_STATE_REQUIRED` if `initialState(ownerResource)` unavailable | Correct misleading description; do **not** silently create ACTIVE |
| `Contact.allowed_actions` | Not typed in generated `Contact` | `list/show` return advisory `edit/setstate` booleans on VIEW-authorized records; write responses can omit | Optional, boolean-only `allowed_actions` read property (not required) |
| PATCH owner/status | `ContactPatch` excludes owner/status | PATCH rejects owner transfer/status updates | Keep **byte-equivalent schema**; workflow set-state endpoint only |
| Contact list query | Nested `filter` deepObject; page 50, cap 200 | `ContactSql` supports `contact_type`, `contact_ref_id`, optional `status`; default all states | **Do not** replace Contact query with SUP's future flat filters; keep current parameters |
| Contact API routes | Existing five API-010 operationIds | Controller continues to own five endpoints | No new Contact route; no changes to P1 frozen registry |
| FE mounted Contact form | Owner prop limited to EMPLOYEE/ORGANIZATION | T06B1 only maps four owner resources/list query | **No UI activation** until generated SDK and MOD-G2 freeze |

**Critical distinction:** flat Supplier LIST parameters (`status`, `company_id`, etc.; proposed default 25/max 100) belong to future T07 Supplier endpoints. They are **not** the API-010 Contact list shape (current `filter` object; default 50/max 200). Never silently merge the two contracts.

The review artifact is `contracts/openapi/p2-sup-contact.review.openapi.json`. It is an OpenAPI 3.1 **schema-only candidate** with `paths: {}` and explicit `x-review-only`, `x-do-not-generate-client`, `x-approval-required`. It copies the existing `BigInt`, `BusinessWorkflowStatus`, `Contact`, `ContactCreate`, `ContactPatch` schemas from canonical and makes **only** three candidate changes: (1) shared four-owner reference in Contact and ContactCreate, (2) corrected create description, (3) optional boolean action hints on read Contact. It must **not** be configured as frontend generation input or interpreted as deployed API.

## 3. Security and backwards compatibility invariants

- Map exact persisted owners: `ORGANIZATION -> organization_contact`, `EMPLOYEE -> employee_contact`, `CUSTOMER -> customer_contact`, `SUPPLIER -> supplier_contact`. Unknown/noncanonical owners fail closed; no `contact` or `employee_contact` fallback.
- `SUPPLIER` Contact read uses `supplier_contact.view` **AND** independently `supplier.view` plus Supplier persisted `group_organization_id == trustedGroupRootId`. Customer parent VIEW remains independent. Explicit DENY/Lifecycle predicates must apply before Contact SQL COUNT, rows and detail. Do not infer PBAC from Supplier Company Usage.
- CREATE needs exact owner Contact CREATE permission and permitted persisted parent; PATCH/SetState revalidate current Contact VIEW and parent VIEW. Owner ID/type are immutable. Backend derives initial status from configured Lifecycle; unsupported/missing Lifecycle fails closed.
- Contact list retains one SQL-first, server-filtered page with `meta.total`, sorted ID tie-break, default ALL statuses, no post-LIMIT authorization filtering; `allowed_actions` hints are **not** permission grants.
- Retain all five existing Contact operations: `listContacts`, `createContact`, `getContact`, `updateContact`, `setContactState`; leave P1 route registry 180 registrations/150 frozen operations unchanged. No Supplier Contact subroute or new table.
- Preserve original `ContactCreate` **required fields** (`contact_type`, `contact_ref_id`), existing writable fields, and BIGINT-as-string JSON type. Changing requiredness, positive-ID schema, validation codes, error envelope, nullable fields or status pattern requires a separate reviewed decision; T06B2 does not invent these changes.

## 4. Open technical decisions (to be signed off, NOT implicitly approved)

| Review owner | Required decision/evidence | Current |
|---|---|---|
| Tech Lead / API (MOD-G2, PC-07) | Approve four-owner `ContactOwnerType` shared schema and corrected Lifecycle description, maintain existing five operations/filter/query; decide optional action hints, final DTO/error codes, upgrade strategy for old clients | **PENDING** |
| Security / Backend (PC-06) | Approve parent dual-VIEW and GROUP root, read/write/Workflow/Notification/audit equivalence with negative role tests | **PENDING** |
| FE / API | Decide when canonical YAML can be updated, regenerated by `openapi:generate` (no hand edits), when `ContactList/ContactFormModal` can expand owner props and Supplier page mounts contacts | **PENDING** |
| DEV / QA / DBA | Run T00/000028/catalog preflight, MariaDB COUNT/page/detail/EXPLAIN, actor A/B HTTP/PBAC and browser UAT before live activation | **NOT_RUN/BLOCKED** |

**One additional pre-freeze check:** `ContactCreate` in canonical lists only owner fields as required, while the current modal requires name/phone/address client-side. The candidate deliberately does not strengthen server requiredness; FE/API must compare legacy validation and decide any extra required constraints in a separately approved change. Also note that `BigInt` in canonical allows a leading minus while `ContactRules::id` rejects nonpositive IDs; `BusinessWorkflowStatus` in canonical allows up to 50 characters while `ContactRules::status` enforces at most 20. The candidate preserves these legacy schemas and flags possible contract refinement instead of silently narrowing P1.

## 5. Validation / next execution

Portable isolated review gate (does **not** require npm packages, a database, HTTP or network):

```powershell
cd D:\XAMPP\htdocs\erp\frontend
node --test .\scripts\sup-t06b2-contact-contract-review.test.mjs
if ($LASTEXITCODE -ne 0) { throw 'STOP: SUP-T06B2 contract review regressions' }
```

The candidate must also pass JSON parsing and OpenAPI JSON `$ref` resolution, with exact field compatibility verified against canonical YAML. After an **explicit MOD-G2/PC-07 technical signoff**, generate **another incremental patch** to update `contracts/openapi/openapi.yaml`, run `pnpm run openapi:lint` and `pnpm run openapi:generate:check` with generated files included, then run FE type/lint/import/build and legacy ORG/EMP/CUS + new SUP negative contract tests. Do not edit generated source by hand. The existing Supplier API T07 and Supplier page T08 remain separately gated.

### Gate status after this review package

`T06A: SOURCE/OFFLINE PASS; T06B1: WINDOWS FRONTEND SOURCE/OFFLINE PASS; T06B2: CONTRACT CANDIDATE/OFFLINE TEST PASS, TECHNICAL SIGNOFF PENDING; SUP-T06 OVERALL: PARTIAL; MOD-G2/PC-07: PENDING; PC-06: PENDING; MARIA_DB/HTTP/BROWSER: NOT_RUN; RELEASE: BLOCKED.`
