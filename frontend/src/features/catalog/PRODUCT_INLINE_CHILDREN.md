# Product List — inline children

- Scope: UI-only; no changes to Product API, database, PBAC, or existing page totals.
- The canonical `/api/v1/products` response still controls the main page of up to `page_size` authorized Product records. A child is nested when its parent is also visible on that page; without a visible parent, the child stays in the normal page.
- Existing `/products/relations-preview` fetches **two** authorized child records and an authorized count per parent in a batched request. Initially those children appear as indented table rows, matching the Product Groups visual treatment. Parents may be collapsed with `+/-`.
- `Xem thêm` uses the existing parent-filtered, PBAC-protected `/products` pagination, ordered by Product ID ASC to match the preview. It shows all loaded authorized children inline, and `Chỉ hiện 2 sản phẩm đầu` reduces to the preview. Existing `loadLookupPages` limit (10,000 rows per parent) remains fail-closed rather than silently truncating large relationships.
- When the same child occurs in the canonical current page and the parent preview, it is shown only once, underneath its parent. The backend's `meta.total` and page boundaries do **not** change; extra child lines are *display-only* rows, not separately counted page results.
- Preview response rows have `product.view` authorization but may not have `allowed_actions`. The UI never inherits edit/setstate from the parent: action buttons remain hidden without explicit flags. Selecting a preview child opens a separately authorized Product detail GET to retrieve its own current `allowed_actions`; after full expansion the parent-filtered Product List supplies those hints for each child. Mutation endpoints still reauthorize.
- COMBO components continue using the existing summary/dialog; they are not Product parent-child relations.

Checks:

```bash
node --experimental-strip-types --test frontend/src/features/catalog/__tests__/productInlineRows.node.mjs
cd frontend
pnpm run type-check
pnpm exec vitest run src/features/catalog/__tests__/productInlineRows.spec.ts
pnpm run build-only
```
