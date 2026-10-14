# 17 — Frontend V2.2: Product Editor feature boundaries

**Change ID:** `FE-V2.2-PRODUCT-EDITOR-2026-10-14`
**Parent:** `FE-V2.1-APPSHELL-2026-10-14` (102/102 Vitest and 243/243 Playwright E2E passed on the user's Windows environment).
**Frozen historical baseline:** `FE-V2-START-2026-10-14` (203-file manifest; **not regenerated**).
**Status:** `STATIC-VERIFIED / WINDOWS TYPECHECK, UNIT, BUILD, E2E PENDING`.

This is an **organization-only** refactor scoped to `frontend/src/features/catalog/pages/ProductEditorView.vue` and new `frontend/src/features/catalog/product-editor/` modules. There are no intended API, payload, role/permission, database, route, validation priority, visual, CSS or product lifecycle changes. Static checks cannot prove runtime equivalence.

## 1. Before / after

| Responsibility in V2.1 | V2.2 ownership | Notes |
|---|---|---|
| Page state, lookups, validations, payload and template in `ProductEditorView.vue` (1,295 lines) | `ProductEditorView.vue` (1,070 lines) | Retains loading, save orchestration, error handling, unsaved edits, conversions, COMBO save and workflow changes |
| Inline form/conversion/combo types | [`product-editor/types.ts`](../../frontend/src/features/catalog/product-editor/types.ts) | `ProductEditorForm`, `ConversionForm`, `ComboForm` typed against the generated API |
| Input defaults, row-to-form mapping | [`product-editor/model.ts`](../../frontend/src/features/catalog/product-editor/model.ts) | No DECIMAL-to-number conversion; TINYINT flag mapping retained |
| Validation branches in page | [`product-editor/validation.ts`](../../frontend/src/features/catalog/product-editor/validation.ts) | Pure error-key selection, unchanged condition ordering, translated in the page |
| API body mapping in page | [`product-editor/payload.ts`](../../frontend/src/features/catalog/product-editor/payload.ts) | Product CREATE/PATCH, atomic COMBO CREATE, UOM conversions and COMBO replacement |
| Remote parent/COMBO lookup logic | [`product-editor/useProductEditorLookups.ts`](../../frontend/src/features/catalog/product-editor/useProductEditorLookups.ts) | One owner for groups, UOMs, selected products, hydration and related `computed` options |
| Page heading | [`ProductEditorPageHeading.vue`](../../frontend/src/features/catalog/product-editor/components/ProductEditorPageHeading.vue) | Same DOM root; emits `back` to parent router |
| Parent restrictions / API, validation and image partial-save alerts | [`ProductEditorFeedback.vue`](../../frontend/src/features/catalog/product-editor/components/ProductEditorFeedback.vue) | Three original conditional paragraphs, with same ARIA roles and equivalent local CSS; no DOM wrapper |

The existing Product Editor `<form>`, all fields, the legacy relationship sections (still gated off), COMBO table, conversion modal and the entire page-level `<style scoped>` block remain **byte-identical** to V2.1. Only heading and feedback paragraphs were componentized outside that preserved form. The page continues to own `imageField.commit()` before navigation, calls `unsaved.markClean()` at the same points and observes `route.fullPath` as before.

## 2. Explicit business/contract invariants

1. **Product CREATE/PATCH** still sends `product_code`, `sku`, `barcode` as nullable, excludes empty optional DECIMAL and dimension strings, sends strings without floating point coercion, and preserves child (`product_child_name`) versus top-level (`product_name`) payload rules.
2. **COMBO CREATE** still submits components atomically inside the product create body; edit is not silently converted to nested COMBO save; COMBO serial management remains `false`.
3. **UOM conversions** retain order, status, usage, precision and optional field omission; `replaceProductConversions` and error handling remain the same.
4. **Eligibility validation** preserves error-message precedence for child/parent name, base UOM, disabled historical Group/UOM selections, COMBO component/quantity and alternate conversion factor/price.
5. **Selection** retains server-side search purpose (`parent_product`/`combo_component`), excludes self references, hydrates selected/retained options, and does not replace server permission enforcement with UI hints.
6. **Product image partial failures** do not cause retrying product CREATE; pending image commit remains before route replacement.
7. **Authorization and lifecycle** continue using `useBasePermission('product', action)`, the existing workflow action component and unchanged route definitions.
8. **No backend or OpenAPI change:** the two previously observed Windows hash deviations in `backend/app/Config/Routes.php` and `contracts/openapi/openapi.yaml` remain separate and still need content review.

## 3. Local source verification

| Gate | Workspace result | Meaning |
|---|---|---|
| `node --test scripts/refactor/v2-2-product-editor.test.cjs` | **6/6 PASS** | Pinned V2.1 form tail and CSS source hashes, 17 unchanged function ASTs, save/lifecycle order, module composition and ARIA semantics |
| Differential payload and validation probe against V2.1 source | **2,250/2,250 matches** | Independent deterministic randomized input scenarios; useful but not a replacement for browser test |
| `node scripts/check-imports.cjs` | **573/573 edges PASS** (155 TS/Vue files) | No unresolved relative/alias imports in scanned app source |
| `node --test scripts/refactor/v2-1-shell.test.cjs` | **4/4 PASS** | Previous AppShell contract not disturbed |
| TypeScript parser over all new TS, tests and Vue script blocks | **PASS** | Syntax only, not `vue-tsc` semantic/type check |
| New Vitest source | **15 tests in 3 files** | Payload/validation/lookup and UI components; pending actual Windows execution |
| Vue type-check, full ESLint, Prettier, Vitest and Vite build | **NOT RUN locally** | npm registry unavailable in build container; must run on Windows |
| 243 Playwright E2E | **NOT RUN for V2.2 locally** | Must be rerun on Windows, with normal backend/database/test fixtures |

A static hash does not guarantee browser rendering equivalence, especially after converting feedback paragraphs into a child Vue component. The style copying is intentionally limited to the two feedback class names while all other original scoped CSS rules remain unchanged.

## 4. Windows verification and interpretation

Apply the patch to **V2.1 already verified on Windows**, not to the original P1 or V2 Start without V2.1.

```powershell
cd D:\XAMPP\htdocs\erp

git apply --check .\erp30_frontend_v2_2_from_v2_1.patch
git apply .\erp30_frontend_v2_2_from_v2_1.patch

cd frontend
pnpm run check:v2-1-shell
pnpm run check:v2-2-product-editor
pnpm run check:imports
pnpm run diff:v2-baseline
pnpm run type-check
pnpm run lint
pnpm run format:check
pnpm run test:unit -- --run
pnpm run build
pnpm exec playwright test
```

`diff:v2-baseline` should report **DRIFT** because V2.1 and V2.2 deliberately changed files relative to the frozen 203-file V2 Start; do **not** rewrite its hash manifest. The historical `check:refactor-baseline` may report expected `views` and `preservedRuntimeLogic` drift, since V1 predates both the AppShell and Product Editor extraction; review all other reported sections independently rather than updating old fingerprints to force a pass.

If `format:check` reports newly introduced formatting differences, format **only the V2.2-owned paths** and rerun the static V2.2 hash/AST contract plus the quality suite. Do not globally reformat the existing form or prior baseline. The 3 pre-existing Playwright ESLint warnings are still a separate QA cleanup item, not an excuse for new lint errors.

**Acceptance:** `check:v2-2-product-editor`, `check:imports`, `type-check`, lint with zero errors, Prettier, unit, production build and all Playwright tests pass on the target Windows environment. Any regression requires rollback/repair before calling V2.2 `VALIDATED`.

## 5. Rollback / review / release

- Review `git diff` after applying; changes must be constrained to the new feature boundary modules, their tests, `ProductEditorView.vue`, package scripts and docs/reference inventories.
- Do not commit unrelated backend/OpenAPI changes in the V2.2 commit. The full-source ZIP is an **archive**, not a replacement for the user's current Git worktree.
- Roll back on a clean, unedited application of this patch using `git apply --reverse .\erp30_frontend_v2_2_from_v2_1.patch`, or check out the pre-V2.2 Git commit.
- `FE-V2-START-2026-10-14` remains immutable. The V2.2 pass report is an additive checkpoint, not a new definition of original code behavior.
