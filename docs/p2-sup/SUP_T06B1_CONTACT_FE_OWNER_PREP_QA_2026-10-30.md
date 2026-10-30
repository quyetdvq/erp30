# P2-SUP / SUP-T06B1 — Shared Contact frontend owner-policy preflight

**Date:** 2026-10-30
**Baseline:** `erp_v2.1.0.zip` (SHA-256 `3de9f21c303a76f2103d5e2ab335f391626115937c5ceb6baf5dc5656b76589b`) **plus applied SUP-T06A backend patch**.
**Operator evidence:** SUP-T06A `SUP-UNIT`, `CUS-UNIT`, `UNIT-STANDARD`, `ROUTES`, `P1-STANDARDS` all PASS on Windows, **offline only**.
**Status:** **T06B1 FE SOURCE PREPARATION / PORTABLE TEST PASS; MOD-G2/PC-07 NOT FROZEN; T06 OVERALL PARTIAL; DEV UAT NOT_RUN.**

## 1. Why a separate preflight patch

The approved Supplier Contact decision (SUP-DEC-04) requires one Shared Contact component with persisted owner `SUPPLIER`, resource `supplier_contact`, and independent parent `supplier.view`. SUP-T06A implements backend SQL/PBAC/Workflow/Notifications/audit; the operator has verified source/offline tests.

However, the canonical `contracts/openapi/openapi.yaml` **still** declares `Contact.contact_type` and `ContactCreate.contact_type` as only `[EMPLOYEE, ORGANIZATION]`. The generated `frontend/src/api/generated/types.gen.ts` reflects the same two-owner enum. Existing Customer implementation sends `CUSTOMER` through its own service, so this is a pre-existing schema/runtime drift as well as a new Supplier issue. MOD-G2/PC-07 review is explicitly required before changing the public contract, regenerating frontend SDK/types or activating Supplier UI. **Do not hand-edit `frontend/src/api/generated/*`.**

T06B1 is deliberately narrower: a reviewed owner->resource utility and owner-scoped list-query helper support the four runtime owner kinds. The mounted `ContactList`/`ContactFormModal` owner input remains EMPLOYEE/ORGANIZATION **until the canonical contract and generated types are reviewed**. This patch does not expose a Supplier Contact creation screen.

## 2. Scoped source delta

- New `contactOwnerPolicy.ts`: an explicit mapping for `ORGANIZATION`, `EMPLOYEE`, `CUSTOMER`, `SUPPLIER`; unknown or noncanonical owner -> empty resource (no fallback to `employee_contact`). UI permission hints remain advisory; backend effective PBAC and parent VIEW are authoritative.
- `ContactList.vue` reuses the explicit map for the old owner and each row's persisted `contact_type`; no routes, modals, or backend API calls added.
- `contactListQuery.ts` supports four owner types and replaces any caller-supplied owner/Company filters with the owner selected by the current page; it preserves optional status and leaves default status ALL. This query helper is a UI adapter, **not a security boundary**.
- Vitest coverage for four owners, Supplier query, unauthorized filter override and fail-closed map; portable Node.js test without a network/package installation.
- No update to P1 route registry, OpenAPI, generated SDK, Supplier bank, Supplier pages, MariaDB, catalog, migration or PBAC grants.

## 3. Review candidate for MOD-G2 / PC-07 (NOT YET APPROVED)

| Contract element | Observed source | Review-required direction |
|---|---|---|
| `Contact.contact_type` | generated enum EMPLOYEE/ORGANIZATION | Reconcile four runtime owners: EMPLOYEE, ORGANIZATION, CUSTOMER, SUPPLIER |
| `ContactCreate.contact_type` | same two-owner enum | Reconcile four valid owner kinds; enforce backend owner/parent authorization, preserve create-time initial Lifecycle rules |
| `ContactPatch` | no owner/status mutation | Preserve immutable `contact_type`/`contact_ref_id`; state transition only via Workflow |
| Contact list query | existing Contact API uses nested `filter` | Retain existing P1 Contact filter shape; do **not** confuse with proposed **flat** Supplier List (`status`, `company_id`, etc.) |
| Contact actions | effective `allowed_actions`, lifecycle | Map `supplier_contact` exactly; enforce independent `supplier.view` on server; never infer permissions from Company Usage |
| Supplier page | not implemented | T07/T08 must provide typed SDK + Supplier page Contacts tab and vi/en/browser tests after G2 freeze |
| P1 route registry | 180 non-OPTIONS, 150 frozen P1 | Preserve all entries; no new route for Shared Contact owner kind |

**Gate decision:** This file is a contract discrepancy and candidate resolution, not a technical signoff. An authorized Tech Lead/FE/API review must freeze exact OpenAPI schema descriptions, DTO/error behavior and generated client parity before mounting Supplier Contact in frontend or writing new Supplier API routes.

## 4. Evidence and required follow-up

- **PASS in isolated source tree:** `node --experimental-strip-types --test frontend/scripts/sup-t06b1-contact-owner.test.mjs`: **19/19** (no database/browser). Re-run on Windows after applying the patch.
- **Existing SUP-T06A on Windows:** all five requested offline suites PASS; no MariaDB, HTTP or UAT evidence.
- **NOT_RUN here:** Vitest, Vue type-check, ESLint, frontend build and OpenAPI generation (pnpm dependencies not locally installed; Corepack could not reach registry). Operator must run the commands below in an installed frontend environment.
- **NOT_RUN/BLOCKED:** T00 Security/DBA, migration 000028, Supplier catalog provisioning, PC-06 real DB/PBAC audit + EXPLAIN, PC-07 MOD-G2 contract freeze, T07/T08, authenticated browser UAT. No production rollout.

### Operator checks (after applying *only* this incremental patch, with T06A already installed)

```powershell
cd D:\XAMPP\htdocs\erp
$patch = '.\DinkERP_P2_SUP_T06B1_Contact_FE_Owner_Preparation.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: T06B1 patch mismatch' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: T06B1 apply failed' }
git diff --check
if ($LASTEXITCODE -ne 0) { throw 'STOP: invalid diff' }

cd .\frontend
node --experimental-strip-types --test .\scripts\sup-t06b1-contact-owner.test.mjs
if ($LASTEXITCODE -ne 0) { throw 'STOP: T06B1 portable tests failed' }
pnpm exec vitest run src/components/contacts/__tests__/contactListUnified.spec.ts src/components/contacts/__tests__/contactOwnerPolicy.spec.ts
if ($LASTEXITCODE -ne 0) { throw 'STOP: T06B1 Vitest failed' }
pnpm run type-check
if ($LASTEXITCODE -ne 0) { throw 'STOP: frontend type-check failed' }
pnpm run lint
if ($LASTEXITCODE -ne 0) { throw 'STOP: frontend lint failed' }
pnpm run check:imports
if ($LASTEXITCODE -ne 0) { throw 'STOP: frontend import check failed' }
pnpm run build
if ($LASTEXITCODE -ne 0) { throw 'STOP: frontend build failed' }
```

No migration, database write or permission changes are part of these commands. **Do not run OpenAPI regeneration merely to force new enums without MOD-G2 approval.**

## 5. Acceptance boundary

`SUP-T06A = SOURCE + WINDOWS OFFLINE PASS`; `SUP-T06B1 = FE PREPARATION SOURCE + PORTABLE OFFLINE PASS, WINDOWS VITEST/TYPE/LINT/BUILD PENDING`; `SUP-T06 = PARTIAL`; `MOD-G2/PC-07 = PENDING`; `DEV UAT = NOT_RUN`; `RELEASE ACCEPTED = NO`.

After PC-07/technical contract freeze, prepare a separately reviewed incremental patch to update canonical OpenAPI, regenerate the SDK/types, expand the mounted Contact form owner type, and integrate Supplier Contacts in the Supplier page at T08. Do not replace this dependency with `as ContactCreate` casts or manual edits to generated files.
