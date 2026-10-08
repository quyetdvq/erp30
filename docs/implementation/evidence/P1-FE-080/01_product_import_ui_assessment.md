# P1-FE-080 Product Import UI/report Assessment

## Scope and readiness

`P1-FE-080 — Product Import UI/report` was executed after the bounded task
context reported `AUTHORITY_CACHE: VALID`, `P1-API-060 DONE` at `cce76c0`, and
`P1-FE-080 NOT_STARTED`. The exact high-risk sources were checked: UI Design
`UI-P1-IMP-001..003`, API Design v1.8 section 13, FRS Import, and Plan v1.10.
`P1-FE-090` remains not started and is the next dependency.

## Implemented behavior

- Added `/products/import` with CSV/XLSX selection, `CREATE_ONLY` default,
  `UPDATE_ONLY`, and `UPSERT` modes.
- Added canonical template guidance for Product-only import, `source_key`,
  generated `product_code`, `__NULL__`, and formula rejection. Client checks
  are convenience only; backend validation remains authoritative.
- Reused generated `startProductImport` and the shared typed client. No export
  route or pending media contract was added.
- Added `/products/import-jobs` using `useListState`, `ListQueryControls`,
  `ListPagination`, `ListTable`, `TableStatus`, and `TableRowActions`.
- Added `/products/import-jobs/:id` with summary and paginated persisted row
  results. The table exposes only `row_number`, `entity_key`, `row_status`,
  `error_code`, `error_message`, and `created_at`; it does not render
  `record_type`, `source_key`, or raw payload.
- Added vi/en translations, permission hints for UX only, and backend 403/404
  error preservation through the shared error boundary.

## Validation

| Check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-FE-080` | PASS; `AUTHORITY_CACHE: VALID`, HIGH/frontend, P1-API-060 ready |
| `pnpm run type-check` | PASS |
| touched-file ESLint | PASS |
| touched-file Oxlint | PASS |
| `pnpm run lint` | NOT PASS; untouched pre-existing `src/components/forms/__tests__/SearchableSelect.spec.ts:10:11` unused `wrapper` |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS |
| focused Vitest `src/views/__tests__/productImport.spec.ts` | PASS; 1 file, 2 tests |
| focused Playwright `e2e/fe080-product-import.spec.ts --project=chromium` | PASS; 3 tests; critical axe violations empty |
| `git diff --check` | PASS for task changes |

## Contract impact

- DB/schema: none; no migration or schema changes.
- Authorization: `product.import` and `import_job.view` are Base Permission
  UX hints only. Backend two-gate authorization remains final authority.
- API/OpenAPI: existing generated P1-API-060 operations reused; no public
  contract or generated-client drift.
- UI/i18n: three routes, Table First job/report views, and vi/en parity.
- Export: intentionally absent while the media contract is `CONTRACT_PENDING`.

Commit: recorded by task closure.
