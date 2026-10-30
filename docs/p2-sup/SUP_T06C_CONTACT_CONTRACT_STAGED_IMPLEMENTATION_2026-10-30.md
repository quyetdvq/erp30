# P2-SUP / SUP-T06C — Shared Contact canonical contract + Vue consumer STAGED SOURCE

**Prepared:** 2026-10-30. **Input baseline:** `erp_v2.1.0.zip` + SUP-T06A backend + T06B1 FE owner prep + independent typed mock fix + T06B2 contract candidate. This patch is incremental to T06B2. The PC-06/PC-07 review-record document (T06C0) is OPTIONAL for patch matching, but its signed decisions are REQUIRED before applying the candidate.

**Current gate:** `STAGED_SOURCE_PREPARED / NOT_AUTHORIZED_FOR_ACTIVATION / PC-06_PENDING / CONTACT_PC-07_PENDING / MOD-G2_PENDING / DEV_UAT_NOT_RUN`. Creating/testing this candidate does not constitute PC-06, PC-07 or MOD-G2 approval.

## Scope included (no Supplier API or production changes)

- Canonical `contracts/openapi/openapi.yaml`: shared `ContactOwnerType` enum EXACTLY `EMPLOYEE, ORGANIZATION, CUSTOMER, SUPPLIER`, referenced by existing `Contact` and `ContactCreate`; optional `Contact.allowed_actions.edit/setstate` boolean hints; truthful fail-closed Lifecycle description (`WF_INITIAL_STATE_REQUIRED`). Existing `ContactPatch`, required fields, BIGINT JSON string and five Contact API-010 operationIds remain untouched. Contact list remains the nested `filter` wire and default 50/cap 200, not Supplier T07 flat filters.
- `ContactList.vue` and `ContactFormModal.vue` consume the same four-owner `ContactOwnerType` derived from the explicit PBAC resource map. The map is exhaustively checked against **generated** `ContactOwnerType` after official SDK generation. Unknown owners have no permission fallback.
- Added source and post-generation parity Node tests, repaired T06B2 review regression to accept both pre-freeze inline legacy enum and post-freeze canonical enum without removing assertions.
- Not included: generated files (must be produced by `pnpm run openapi:generate` on the authorized Windows checkout), Supplier routes/UI, migrations, Catalog, BANK cryptography/audit, purchase adapter, Workflow CAS implementation or DEV/production DB writes. Generated code must **never** be manually patched.

## Required approvals before applying the canonical candidate

1. **PC-06 Security + Backend:** Supplier Contact parent VIEW, dual PBAC, persisted trusted GROUP, SQL COUNT/page/detail, mutation re-check, Notification/Workflow/audit parity. Record reviewer, role, reviewed commit, conditions and signature in the existing review record.
2. **PC-07 Contact API / FE:** exact four-owner schema, optional boolean action hints, old API-010 five operations and nested filter, unchanged PATCH, and compatibility/error policy.
3. **MOD-G2 Contact sub-contract authorization** to modify canonical OpenAPI and regenerate SDK. The full Supplier T07 API MOD-G2 remains separate/pending.

**Do NOT apply this patch or run SDK generation** based only on the user's implementation-plan agreement or the offline PASS. Use `DinkERP_P2_SUP_T06C_Apply_After_Signoff.ps1` only *after* real authorized signoff; its switches are a deliberate operator attestation and **do not independently verify signatures**.

## Offline validation on preparation environment

- `node --test frontend/scripts/sup-t06c-contact-contract-source.test.mjs` (source only; no DB)
- `node --test frontend/scripts/sup-t06b2-contact-contract-review.test.mjs` (upgraded legacy contract review)
- PHP SUP/CUS offline source/regression tests. **No frontend deps in packaging environment**, so Redocly, SDK generation, Vitest/type-check/lint/build must be run on the Windows checkout after signoff and source patch application.

## Post-signoff Windows activation checks (staging/dev only)

From repo root (post-signoff), place the `.patch` and `DinkERP_P2_SUP_T06C_Apply_After_Signoff.ps1` in the root, then run:

```powershell
cd D:\XAMPP\htdocs\erp
.\DinkERP_P2_SUP_T06C_Apply_After_Signoff.ps1 `
  -ReviewRecord '.\docs\p2-sup\SUP_T06_PC06_PC07_TECHNICAL_REVIEW_RECORD_2026-10-30.md' `
  -Pc06Signed -ContactPc07Signed -ContactModG2Signed
```

The command requires actual documented review signatures/conditions. On Windows it performs `git apply --check`, applies patch, Redocly lint, **official generated SDK**, source/SDK parity, Contact/CUS/SUP regressions, typed FE tests, `pnpm` lint/types/imports/build. It will STOP on any failed gate. No migration, catalog provisioning, database or production deployment is performed by this script.

If any gate fails, **stop**, keep output and review `git diff` and generated SDK files; do not mark `SOURCE PASS` or `G2 SIGNED`. Do not use a static source PASS in place of generated client/type-check evidence.

## Separate unresolved blockers

- `PC-03` Bank secure storage, key custody, reveal/clear audit and DBA approval; T04B not implemented.
- `PC-05` Supplier-owned Workflow SetState must be routed through Supplier CAS `expected_version`, never generic execute.
- Full `PC-07` Supplier T07 API including flat filters (25/100), bank endpoints and secure errors.
- `PC-01/02/09/10` schema, DEV MariaDB/HTTP two-actor PBAC, query EXPLAIN/COUNT performance, browser UAT and release signoff.

**Exit criteria:** After the authorized post-signoff script and manual backend/security review have passed, mark only `SUP-T06C_CANONICAL_SDK_FE_SOURCE_PASS`. SUP-T06 overall remains `DEV_UAT_PENDING`, Supplier G1 `RELEASE_BLOCKED` until all runtime gates pass.
