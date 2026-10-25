# M8 - Shared Import Framework, Product Adapter and CSV/XLSX Templates

**Contract:** frozen OpenAPI v1.2.4, 150 operations. **Parent milestone:** M7.4 source-accepted at 146/150.
**Acceptance mode:** SOURCE/OFFLINE only; live HTTP/MariaDB, Vue build, XLSX runtime and production cutover remain BLOCKED.

## Modules and code boundaries

- `backend/app/Modules/Import/Core/ImportAdapter.php`: an extension contract for per-module `entityType`, `template`, `authorizeStart`, `transactionalTables`, `viewableJobStatuses`, `canViewJob` and `execute`.
- `backend/app/Modules/Import/Core/ImportTemplateCatalog.php`: versioned canonical schema for upload parsing and generated template files.
- `backend/app/Modules/Import/Core/ImportFileReader.php`: bounded CSV and XLSX parsing, invalid UTF-8/formula/header rejection, string decimal preservation, ZIP expanded-data bound.
- `backend/app/Modules/Import/Core/ImportRowPlanner.php`: same-file parent dependency ordering; conversion rows then COMBO rows. Duplicate `source_key`/Product code and circular/missing parent references fail before job writes.
- `backend/app/Modules/Import/Core/ImportJobService.php`: shared bounded job orchestrator, one row journal per completed business transaction, explicit terminal job states; no direct Product table DML.
- `backend/app/Modules/Import/Core/ImportListQuery.php` and `ImportJobSql.php`: safe query filters, SQL PBAC state and entity-type WHERE before COUNT/LIMIT/OFFSET.
- `backend/app/Modules/Product/Import/ProductImportAdapter.php`: Product-specific row validation, reference resolution, PBAC and existing Product Mutation/UOM Conversion/Combo Services. It does not define its own Product mutation transaction or numbering system.
- `frontend/src/features/import/shared/ImportWizard.vue`: reusable template download, file selection, modes and controlled submit component.
- `frontend/src/features/product-import/pages/ProductImportView.vue`: Product-specific wrapper and navigation. `frontend/public/import-templates/product-v1.csv` and `.xlsx`: static downloadable versioned templates.

Future modules reuse the core pipeline by providing an adapter, its own authoritative authorization/read-scope compiler and templates. They must not reuse the Product GROUP-root authorization rule without a scope review.

## Four frozen API operations

| Method | Path | Handler |
| --- | --- | --- |
| POST | `/api/v1/products/import-jobs` | `startProductImport` |
| GET | `/api/v1/import-jobs` | `listImportJobs` |
| GET | `/api/v1/import-jobs/{id}` | `getImportJob` |
| GET | `/api/v1/import-jobs/{id}/rows` | `listImportJobRows` |

No extra download route is added. Files are served as public static frontend assets; neither `/writable` nor SQL is used to serve template bytes.

## Business and security gates

- POST uses the existing default-off `DINKERP_M33B_WRITE_MODE` gate, authenticated Shield user, `product.import` and the actual PBAC decisions for each Product create/edit/related-resource mutation. Before every row the adapter obtains a fresh import authorization snapshot. Direct status editing is rejected; authorized Workflow State transitions remain separate.
- Import jobs are an ORGANIZATION domain resource. Product-owned jobs use the trusted GROUP-root scope from the accepted baseline. `import_job.view` is independently required for all GET methods; creator POST 201 does not grant arbitrary job read access.
- The framework uses the adapter's authoritative status scope in a SQL WHERE before counting or paging and cross-checks each returned row with runtime PBAC. A new module must provide its own trusted read scope.
- All SQL table names are fixed / validated; scalar values use parameter bindings. Batch sizes and sorts are server-side whitelisted.
- CSV and XLSX max **2 MiB**, **1,000 nonempty rows**; CSV UTF-8, header validation, no formulas. XLSX additionally checks ZIP expanded bytes (32 MiB limit) and first-sheet formulas; it depends on PHP `ext-zip` and installed `phpoffice/phpspreadsheet` on the live server. File MIME is not a permission grant.
- Import mode is CREATE_ONLY, UPDATE_ONLY or UPSERT. `serial_management_enabled` accepts 1/0 or true/false as text; existing Product validation remains authoritative. `source_key` identifies an in-file Product and is not persisted as a Product code. PRODUCT -> UOM_CONVERSION -> COMBO_ITEM ordering preserves in-file references. UOM and combo merge operations call their existing atomic replacement services while holding the Product mutex.
- The importer checks all referenced tables use InnoDB before any job/mutation write, including Product numbering. It never tries to ALTER an engine automatically.

## Transaction semantics and failure recovery

**A job is not a single transaction.** Each business command is committed by its existing Application Service, then its durable ImportJobRow result is written. The journal is updated after every row, not only at completion. A job completes as `COMPLETED`, `COMPLETED_WITH_ERRORS`, or `FAILED` according to row results and fatal errors. Authorization failures stop further mutations and remaining rows become SKIPPED.

If the database/journal fails after a Product mutation commits, the job may contain successful business records without a corresponding row result. The service attempts to mark FAILED; on total DB outage it may remain PROCESSING. **Do not automatically retry an uncertain job**. Operators must examine job rows, Product codes, hash, numbering counters and database logs to reconcile. This version does not claim whole-file atomicity, cross-request idempotency or automatic recovery.

## Template behavior and UI

`tools/import_templates/build.py` generates both templates from `backend/app/Modules/Import/Templates/product-v1.json`, a **single canonical column manifest** (31 columns). The CSV ships only its header; the XLSX ships a blank first `Data` sheet, a **non-imported** `Examples` sheet, and a `Guide` sheet. Users fill Data only, and may copy valid examples after editing codes/UOMs. The backend parser always reads the first sheet, not Examples/Guide.

`ImportWizard` is configurable by labels, version, template prefix, `canStart`, `busy` and a submit event. More modules can supply a new adapter/template without copying upload form logic. The form's browser checks are convenience only; backend validation is authoritative.

## Reproduction (offline, no HTTP or SQL writes)

```powershell
cd D:\XAMPP\htdocs\erp
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m8_template_pure.php
php tests\m8_reader_fixture.php
php tests\m8_sql_scope_pure.php
php tests\m8_contract_pure.php
php tools\m8_accept.php
```

Optional frontend check, only if frontend dependencies are installed:

```powershell
cd D:\XAMPP\htdocs\erp\frontend
npm run type-check
npm run test:unit -- --run
npm run build
```

Template regeneration is a developer command requiring Python `openpyxl`, not a production step:

```powershell
cd D:\XAMPP\htdocs\erp
python tools\import_templates\build.py
```

## Live acceptance remaining (NOT proven by source tests)

1. Inspect CI4/MySQL connection, `information_schema.TABLES` engine and file-upload limits, ensure PHP `ext-zip` / PhpSpreadsheet are enabled.
2. Use an isolated DEV clone or dedicated sacrificial Product fixture (with backed-up business DB); verify multipart POST 201 with both CSV and XLSX, a real import job and exact row results.
3. Verify CREATE_ONLY, UPDATE_ONLY and UPSERT; dependency graph, Product numbering, UOM/COMBO row-level rollback, no direct status change, and errors for malformed headers/formulas/oversize files.
4. Verify PBAC for a denied actor, expired assignment, hidden Product/UOM/Group, and Product import permission without ImportJob VIEW permission. Compare SQL scoped `total` and per-page output against independently authorized rows.
5. Verify fail-closed non-InnoDB behavior and intentionally induced storage/journal faults only on disposable data. No automatic restart after a potentially partial mutation.
6. Verify two concurrent imports, real rollback, and template links in built/deployed Vue frontend. Collect timing/EXPLAIN on real data. Baseline parity and production sign-off remain separate.

Source-level 150/150 route coverage does **not** mean full live acceptance, rollback proof, baseline HTTP parity, or production readiness. Previous waiver for M6.3C two-writer avatar concurrency remains a documented exception, not a PASS.
