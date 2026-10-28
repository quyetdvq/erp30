# M3.4B.3 — Prefixed MariaDB Sandbox Schema Hotfix

## Why this patch exists

The local `erp_test` database contains 41 tables with the physical `dinktech_` prefix (for example `dinktech_users`). Previous M3.4B versions searched for unprefixed physical names (`users`) and incorrectly reported that the source schema was incomplete. Fallback to local `erp` could repeat the same error.

This is a **test-harness fix**, not a change to the ERP runtime API. It does not change the 70 registered API operations, PBAC algorithms, Frontend or `backend_basline`.

## Changes

1. Before attempting `CREATE DATABASE`, use `information_schema.TABLES` to find exactly one **complete** physical table namespace (plain or prefixed, including `dinktech_`). Ambiguous, incomplete, mixed or unsafe names remain BLOCKED.
2. Validate required columns against the **physical** table names in `information_schema.COLUMNS`.
3. Clone the physical `CREATE TABLE` definitions into the nonce-owned sandbox. Do not remove or rewrite table prefixes or cross-schema references. No business rows are read/copied from the template.
4. Map logical fixture table names to the selected sandbox physical prefix for synthetic INSERTs, row fingerprints and counts.
5. Route the integration runner's direct SQL probes (Policy ABSOLUTE_DENY, Shield hash, forced rollback trigger) to the same physical names.
6. CodeIgniter's `CI_ENVIRONMENT=testing` selects the `tests` connection group in `Config\Database`; therefore the **child process only** receives **both** `database.default.*` and `database.tests.*` set to the same sandbox/schema/prefix. Before starting HTTP, the CLI `m34b:attest` verifies the actual resolved schema, prefix and nonce marker. The HTTP router also checks environment group consistency and the guard marker on each request.
7. The JSON report includes `sandbox_table_prefix` after successful provisioning.

## Install

Starting from M3.4B.2, extract the M3.4B.3 Overlay to `D:\XAMPP\htdocs\` (ZIP contains `erp/`). It does not contain `.env` or Composer `vendor`.

## One-line test interface (unchanged)

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN DB-READ
.\tools\test-unit.ps1 RUN INTEGRATION
```

When `erp_test` contains the expected tables and columns, integration should show `sandbox physical table prefix: dinktech_` before building synthetic fixtures. It may detect **additional issues** in the data fixture or HTTP runtime; those must be fixed using the JSON report rather than considering the entire integration PASS automatically.

To run only the new metadata-level checks:

```powershell
php backend\tests\m34b3_prefix_pure.php
```

## Safety invariant

- Template `erp_test` (and the exact local `erp` metadata-only fallback) is never a writable target.
- Only a new `erp_m34b_<nonce>_sandbox` may receive DDL/fixture/mutation writes.
- Never copy table data from template or automatically enable write mode outside the isolated child process.
- On failure after creation, the temporary sandbox is **retained** for diagnosis, not silently deleted.
- The patch's validation is offline / fake metadata. **Live MariaDB + HTTP integration must be run separately on XAMPP.** It is not baseline HTTP parity.

## Architecture roadmap

No new business code is put into `Modules/Access`. M3.4C remains the refactor to move Security Users to `Identity` and Workflow/Lifecycle to `Workflow`, followed by regression/contract verification.
