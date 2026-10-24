# DinkERP Backend v2 — M3.3B2.1 Policy Index Audit Hotfix

## Incident

On XAMPP with CodeIgniter 4.7.5, `php spark m33b2:verify` and `php spark m33b2:verify 20 50` both stopped at:

```
[UnexpectedValueException]
SQL failed: Policy unique scope index
at APPPATH\Commands\M33B2Verify.php:156
```

The four Policy fixture/contract tests, `m3:audit`, `m33b:verify`, and `php spark routes` passed. The failed audit does **not** prove that the five-column UNIQUE constraint is absent: the old `SHOW INDEX` SQL returned `false` and the error handling discarded the database driver's message. The exact underlying MariaDB error is not present in the log.

## Fix

Replace that single `SHOW INDEX FROM policies WHERE Key_name = ...` check with a parameterized read from `information_schema.STATISTICS`, constrained to `TABLE_SCHEMA = DATABASE()` and the physical table name (`$db->getPrefix() . 'policies'`). Verify:

1. The index is named `uq_policies_scope`.
2. Exactly five **full** columns occur in the exact baseline migration order: `organization_id`, `resource_id`, `normalized_state_id`, `participant_type`, `participant_ref_id`.
3. Every index column reports `NON_UNIQUE = 0` and no prefix-only `SUB_PART`.
4. If the metadata query fails, report the actual database driver error code and message. If the index is missing/malformed, fail closed — do not enable mutation routes.

The SQL is **read-only**. No migrations, schema repairs, table writes, API route changes, Frontend edits or PBAC behavior changes are performed. `DINKERP_M33B_WRITE_MODE=off` remains the default.

## Files in Overlay

- `backend/app/Commands/M33B2Verify.php` — metadata query + diagnostics.
- `backend/app/Modules/Access/Mutation/PolicyScopeUniqueIndex.php` — pure strict index-shape validation.
- `backend/tests/m33b2_index_pure.php` — 14 query and index-shape assertions.
- `backend/tests/m33b2_index_command_pure.php` — 6 command-path assertions with DB stub including SQL errors.
- This QA guide.

## Apply

Extract the overlay into `D:\XAMPP\htdocs\erp\` and preserve the local `.env`, `vendor`, `frontend`, `backend_basline`, database and uploads. No web-server configuration change.

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m33b2_index_pure.php
php backend\tests\m33b2_index_command_pure.php
php backend\tests\m33b2_rules_pure.php
php backend\tests\m33b2_transaction_fixture.php

cd backend
php spark m33b2:verify
php spark m33b2:verify 20 50
```

Expected when the real MariaDB has the baseline schema:

```
PASS: five-dimensional Policy scope UNIQUE index is enforced by MariaDB.
PASS: Policy CREATE/EDIT/SETSTATE/DELETE registered in catalog.
...
PASS: sampled Policy write decisions, no database mutations.
```

If the new check reports **Missing/invalid `uq_policies_scope`**, do not create the index manually without verifying the current schema and checking for duplicate Policy scopes. The baseline migration `2026-10-08-000012_EnforcePolicyScopeUniqueness.php` has dedicated reconciliation checks; inspect its prerequisites and take a verified backup. If the command reports `SQL failed ... (code): message`, retain that exact error output for diagnosis. Do **not** enable write routes to work around it.

## QA performed here

- 14 pure index-shape/SQL assertions PASS.
- 6 runtime-command assertions with DB stubs PASS: valid, missing and failed-metadata-query paths.
- Complete M1–M3.3B2 pure regression suites PASS.
- All PHP files in `backend/app` pass PHP syntax checking.
- Changes are limited to command, small validator, tests and documentation.

**Limit:** No live MariaDB server is available in this environment, so the final `m33b2:verify` result on the user's XAMPP remains to be confirmed. Transaction/HTTP mutation on a DB clone also remains unverified.
