# M10.2 — PBAC Row Actions Implementation / DEV Acceptance

**Authoritative standard:** `ERP_PBAC_TABLE_ROW_ACTIONS_STANDARD_v1.0.md` (approved).  This is an implementation companion, not a replacement.

**Baseline to apply to:** ERP v1.5.0 with M9.1, M9.2, M9.3, M10.1, and the doc-only standard patch **already applied**. Do not reapply those patches or M9.0.

## Scope

- Backend: `EffectiveRowActionHints` emits boolean-only, effective `allowed_actions` for a **previously VIEW-authorized** list/detail row by evaluating the current actor's canonical `UnifiedAccessService::decideMutation()` against the trusted persisted row.  It does not infer rights from status, reuse an organization decision by State alone, or call a client permission endpoint.
- Read paths: Product (unchanged), Product Group/UOM, People MASTER, Organization/Assignment, Province/Ward, Contact (owner-resolved), Price List, Access catalog, Workflow catalog, Identity Security Users.  The last case additionally preserves the hint in `mapSecurityUser()`; previously the frontend mapping dropped unknown fields.
- Frontend: 19 `TableRowActions` callsites (18 components) use `authorization-mode="effective"`. Mutation icons require `show`, a **ready base-permission snapshot**, and `allowed_actions[action] === true`. Missing/partial/non-boolean hints deny by default. `view-scope-verified` only applies to VIEW-scoped list/detail rows. Every mutation endpoint independently reauthorizes.
- Auto Numbering is special: per-series controls use the corresponding **persisted Resource row**'s `allowed_actions.configure_numbering`, not fictitious `auto_numbering.edit`. All three icons, including View, require this additional hint.
- `ImportJob` has a VIEW-only button; no fictitious mutation hints added. Product relations-preview items never inherit their parent's action hints.
- Additive contract: `contracts/extensions/effective-row-actions-v1.schema.json`. Frozen OpenAPI and generated SDK files are untouched. Frontend decodes and type-guards this additive field via `rowAllowedActions()` without changing generated source.

## Run on DEV — no DB audit/concurrency requirement

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\ERP_v1.5.0_M10_2_effective_row_actions.patch
git apply .\ERP_v1.5.0_M10_2_effective_row_actions.patch

php .\backend\tests\m102_effective_row_actions_pure.php
python .\backend\tests\m102_row_actions_source.py
python .\backend\tools\verify_routes.py

cd .\frontend
pnpm run type-check
pnpm exec vitest run src/components/lists/__tests__/rowActionPolicy.spec.ts src/components/lists/__tests__/listComponents.spec.ts
pnpm exec playwright test e2e/fe102-row-pbac.spec.ts e2e/fe090-lookup-batch-regression.spec.ts e2e/fe101-product-performance.spec.ts e2e/fe020-employee.spec.ts --reporter=line
pnpm build
```

## Manual acceptance (one row per case)

1. Actor with `*.view` + `*.edit` base permission and PBAC `ALLOW` on row A: **Edit** is shown.
2. Same actor, identical State, PBAC `DENY`/`ABSOLUTE_DENY` on row B: **Edit hidden** without hiding legitimately visible View or exposing row B when VIEW itself is denied.
3. Actor has PBAC `ALLOW` but lacks base permission: Edit hidden. Missing/malformed hints: hidden. Permission snapshot stale/unavailable: all icons hidden.
4. Contact type ORGANIZATION versus EMPLOYEE, different owners; employee assignment versus employee; Price List across two organizations; `resource.delete`; Resource `configure_numbering`.
5. Page/filter changes and policy updates: buttons reflect newly fetched row; mutations still return 403/409 when rights change after the hint was sent. No per-row HTTP permission calls.

**Do not call this release-level acceptance without running tests on your XAMPP DEV.** Existing M9.3 fingerprint naturally detects the new reviewed source files; regenerate an approved fingerprint at a later baseline freeze rather than disabling the protection. M9.2 concurrency/DB integrity waivers remain separate.
