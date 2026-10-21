# Dinktech ERP v1.2.0 — Backend UNIT testing

This project exposes **one PowerShell entrypoint** from `backend`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 CHECK UNIT-STANDARD
.\tools\test-unit.ps1 CHECK NO-DROP

.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN NO-DROP
```

You may also use the literal separator syntax: `RUN - NO-DROP`, etc.

## Suites and selection

| Suite | Classes | Test methods | Expected PHPUnit cases | Internal PHPUnit config |
| --- | ---: | ---: | ---: | --- |
| UNIT-STANDARD | 13 | 123 | 123 | `phpunit.unit-standard.xml` |
| NO-DROP | 91 | 494 | 506 (12 data-provider cases) | `phpunit.unit-no-drop.xml` |
| **Total** | **104** | **617** | **629** | |

`CHECK` runs source-only selection gates and lists every selected class and
`test*` method. It does **not** start PHPUnit, open a database, migrate, or
seed. PHPUnit is the source of truth for the runtime case count.

`RUN` without a suite runs both serially, **UNIT-STANDARD first**, then
**NO-DROP**. An error halts the runner immediately. The two source manifests
pin an exact 91/13 split; no extra class may be included implicitly.

## Database behavior and safety

Both suites target **only** `127.0.0.1:3306/erp_test` with prefix `dinktech_`
under `CI_ENVIRONMENT=testing`. Neither runner changes `.env` or credentials.

- `UNIT-STANDARD` uses CodeIgniter `DatabaseTestTrait`. Its ordinary migration
  and refresh behavior is **not disabled**. **Back up `erp_test`** before RUN.
- `NO-DROP` uses the existing guarded `test-current.php --mode=unit-safe`
  dispatcher. The attestation dynamically captures the **current database**
  and checks every table's schema and data fingerprint before and after tests.
  The baseline does not have to contain a particular number of rows.
- Following the explicit project decision, the unified runner temporarily
  sets `ERP_NO_DROP_SKIP_GRANTS=1` for **NO-DROP RUN only**. This deliberately
  **does not prevent DDL at the database privilege layer**. The target check,
  process isolation and full pre/post attestation remain enabled. The old
  environment variable value is restored after each invocation.
- No step performs a database cleanup, reset, migration or seeding on behalf of
  the NO-DROP suite. Both suites must run **sequentially**, never in parallel.

The private PHP helpers (`tools/test-current.php`, source gates, inventories,
NO-DROP attestation code), the two PHPUnit XML files, and fixture classes are
required internal dependencies. Do not delete them.

Old competing **PowerShell unit/NO-DROP launchers** and the duplicate
`phpunit.unit-standard-selection.xml` were removed in favor of this single
entrypoint. Unrelated database/integration/product-media and frontend test
coverage remains intact: it is not part of the 629-case unit partition.
