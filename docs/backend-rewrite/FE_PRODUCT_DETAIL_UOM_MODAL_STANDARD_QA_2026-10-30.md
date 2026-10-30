# FE-PRODUCT-DETAIL-01 — Product Detail & UOM Conversion UI Standard

**Baseline:** `erp_v2.0.0_m71_uom_conversion_transport_fixed.zip` (after FE-MODAL-01 and M7.1 transport fix)

**Scope:** Frontend-only. One canonical Product detail modal in `frontend/src/components/products/ProductDetailModal.vue`. No migration, backend route, Workflow/PBAC policy, generated API or JSON payload change.

## 1. Findings and correction

1. Product detail still used bespoke header/footer and an `×` text button, unlike Customer/Employee/common ERP modal shells. **Fixed:** use `modal-backdrop`, `modal-card__header`, `modal-card-title`, `modal-card-button-close`, `modal-card__footer`, icon `CubeOutline`, name/code/role header and a status/edit toolbar. Close is a secondary footer action.
2. The multi-tab modal already opted in to `tabbed-detail-workspace`, but the Product-specific backdrop and `detail-body` allowed modal-level horizontal movement, and tables contained fixed-width cells. **Fixed:** standard viewport-bounded width, fixed 760px (viewport-capped) height inherited from shared CSS; headers/tabs/footer out of the scroll region; body vertical scroll and table-only horizontal scroll; narrow-screen rule.
3. UOM conversion rows used hardcoded `ACTIVE/INACTIVE` text and `Sửa`/`Xóa` text buttons. **Fixed:** `TableStatus` plus translated status label, and canonical `TableRowActions` edit/delete icons. Tooltips/ARIA labels include UOM name.
4. Conversions are an atomic Product **list replacement**, not an independent resource with a `delete` action. `TableRowActions` uses parent `rowAllowedActions(currentProduct)`; both icons check `product.edit` and the persisted parent row's effective `edit` hint. The `delete` visual intent maps to action code `edit`; the removal affects **local draft only** until `Lưu` submits existing `replaceProductConversions` endpoint.
5. The toolbar and UOM add/save actions require `product.edit` and persisted row hint. Business permissions are **still revalidated by backend**.

## 2. Files

- `frontend/src/components/products/ProductDetailModal.vue` — canonical modal shell, table status/action components, scoped responsive styles and edit gating.
- `frontend/scripts/fe-product-detail-modal-standard.contract.test.cjs` — six source/contract assertions.
- `frontend/src/components/products/__tests__/ProductDetailUomActions.spec.ts` — four Vue interaction scenarios for desktop/permissions/row actions, to execute on developer Windows with `pnpm`.
- `docs/backend-rewrite/FE_PRODUCT_DETAIL_UOM_MODAL_STANDARD_QA_2026-10-30.md` — this report.

## 3. Available QA evidence (authoring environment)

| Check | Result |
|---|---|
| New FE Product modal contract | **6/6 PASS** |
| Prior FE-MODAL-01 + Customer HIS-03.3 contracts | **12/12 PASS** |
| Combined Node contracts | **18/18 PASS** |
| M7.1 UOM replacement transport (Node with `--experimental-strip-types`) | **3/3 PASS** |
| Import graph | **268 TS/Vue files, 934 local edges, 0 errors** |
| Product modal and Vue test TypeScript **syntax** | **PASS** |
| `git diff --check` | **PASS** |
| Windows Vitest for new component | **NOT RUN** (no `node_modules` in authoring snapshot) |
| `pnpm run type-check` and `pnpm run build` | **NOT RUN** (must run on Windows) |
| Browser UAT | **NOT RUN** |

Source/contract PASS is not equivalent to actual Vue rendering or authenticated MariaDB UAT.

## 4. Apply on Windows

Use the patch only on the **M7.1 transport fixed baseline** (the user already applied M7.1):

```powershell
cd D:\XAMPP\htdocs\erp
$patch = '.\DinkERP_FE_Product_Detail_UOM_Standard_UI.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: Patch mismatch' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'STOP: Patch apply failed' }
git diff --check
if ($LASTEXITCODE -ne 0) { throw 'STOP: Diff check failed' }
```

Run:

```powershell
cd D:\XAMPP\htdocs\erp\frontend
node --test scripts/fe-product-detail-modal-standard.contract.test.cjs scripts/fe-tabbed-modal-height.contract.test.cjs scripts/his03-3-customer-history-modal.contract.test.cjs
node --experimental-strip-types --test scripts/m71-uom-replacement-transport.contract.test.mjs
pnpm exec vitest run src/components/products/__tests__/ProductDetailUomActions.spec.ts src/services/__tests__/productCatalog.spec.ts
pnpm run type-check
pnpm run check:imports
pnpm run build
```

## 5. Browser UAT (operator-driven)

1. In `/products`, open a **SIMPLE/CHILD** Product with existing UOM conversions, e.g. Product 1479. Modal header must show Product icon, name/code and consistent toolbar; footer `Đóng` must look like other ERP detail modals.
2. Switch `Thông tin chung → Quy đổi đơn vị → Vận chuyển và giá → Quy đổi đơn vị`. Modal height must not jump; only its body scrolls; long tables scroll **inside** the table container.
3. Conversion row must show both a `TableStatus` icon and translated status text. Edit and delete are icon buttons with accessible UOM-specific titles.
4. Click edit icon: the preexisting conversion editor must open. Save the row in the draft; then click main `Lưu` to persist changes. No UOM API calls should happen just from opening the row editor.
5. Click delete icon: the row should disappear from the local list. Until main `Lưu`, no `PUT` should be sent. After main `Lưu`, check Network `PUT /api/v1/products/{id}/uom-conversions` sends a nonempty body `{ "items": [...] }`, including `{ "items": [] }` for intentional clear-all.
6. Confirm a Product without `product.edit` effective permission does not expose edit/delete/add/save UOM controls; status remains visible. Backend should still reject direct unauthorized PUT.
7. Test narrow viewport (e.g. 390 x 640): no whole-modal horizontal overflow; tab strip and UOM table may scroll horizontally within their own containers; dialog header/footer remain visible.

## 6. Deployment controls

No database changes, migration, new route, granted permissions, or seeder. The patch must be replayed once on the matching source only; do not apply the full ZIP over a dirty working tree. The nested UOM form's business behavior and existing JSON request serializer remain unchanged.
