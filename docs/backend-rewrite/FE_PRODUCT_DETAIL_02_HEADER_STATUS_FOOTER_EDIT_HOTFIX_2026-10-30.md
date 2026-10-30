# FE-PRODUCT-DETAIL-02 — Header status, footer edit, Vitest type-check correction

- Date: 2026-10-30
- Baseline: `erp_v2.0.0_fe_product_detail_uom_ui_fixed.zip` (FE-PRODUCT-DETAIL-01 applied)
- Scope: frontend only; no API, PBAC, database, migration, status transition or UOM transport changes.
- Reason: Product details displayed status and Edit on an extra toolbar under the header; owner asked to show status next to Product identity in header, and place Edit next to Close in footer. Windows TypeScript failed at `ProductDetailUomActions.spec.ts:102-103` (`.get().exists()` is not part of Vue Test Utils `get()` wrapper type).

## Changes

1. Product detail header contains name, code, role, and inline `TableStatus` with the translated state label. A flexible, wrapping heading maintains alignment on narrow viewports.
2. The redundant status/Edit toolbar under the header is removed. `Edit` is placed before `Close` in the existing standard modal footer. `Edit` still requires `canEditCurrent`, derived from the persisted row action hint AND `product.edit` base permission, and is disabled while busy. The canonical `editCurrent()` emission is unchanged.
3. Customer/Employee/Product shared fixed-height tabbed workspace, scrolled content, backdrop, tabs, child modals, UOM row icons and UOM replacement API are untouched.
4. Vitest selectors use `wrapper.find(...).exists()` for presence assertions. Regression assertions now verify header state/icon, footer Edit/Close ordering, visibility with restricted rights, and emitted edit/close actions. One Node contract test prevents `.get().exists()` regression.

## Executed in the patch-authoring container

| Check | Result |
|---|---|
| Product modal UI/contract | 8/8 PASS |
| FE-MODAL-01 shared height | 9/9 PASS |
| HIS-03.3 Customer height regression | 3/3 PASS |
| M7.1 UOM JSON transport contract | 3/3 PASS |
| Combined Node source/contract suite | **23/23 PASS** |
| TypeScript syntax parser: ProductDetailModal.vue `<script setup>` | 0 syntax diagnostics |
| TypeScript syntax parser: ProductDetailUomActions.spec.ts | 0 syntax diagnostics |
| Full Vue type-check and Vitest runtime | **Not run here**: full frontend node_modules are not provided in source ZIP |
| Browser QA | Not run; user owns XAMPP frontend UAT |

The Node tests are source/contract checks, not a substitute for Vue Vitest, `vue-tsc --build`, and browser QA.

## Windows validation

Run from `D:\XAMPP\htdocs\erp\frontend` after applying the patch:

```powershell
node --experimental-strip-types --test `
  scripts/fe-product-detail-modal-standard.contract.test.cjs `
  scripts/fe-tabbed-modal-height.contract.test.cjs `
  scripts/his03-3-customer-history-modal.contract.test.cjs `
  scripts/m71-uom-replacement-transport.contract.test.mjs

pnpm exec vitest run `
  src/components/products/__tests__/ProductDetailUomActions.spec.ts `
  src/services/__tests__/productCatalog.spec.ts

pnpm run type-check
pnpm run check:imports
pnpm run build
```

In the browser open an editable Product such as 1479:
- Header shows `[icon Product] Name · Code | Role | [status icon] Status`, without a separate toolbar.
- Footer shows `Sửa` and `Đóng` on the same line; pressing `Sửa` invokes the existing Product editor.
- With no effective edit permission, footer only has `Đóng`, but the state remains visible.
- Switching to UOM conversion or another tab leaves the header and footer fixed and preserves UOM Save behavior.
- On narrow screens, the heading/status may wrap; no horizontal overflow or hidden close control is expected.

This fix has no migration and no history-data cleanup.
