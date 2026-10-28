# M3.4B.4 — Isolated CI4 child attestation hotfix

## Observed Windows XAMPP evidence

From M3.4B.3:

- `CHECK` PASS, 41/41 pure/fixture suites PASS.
- `erp_test` was accepted as a schema-only template, with `dinktech_` table prefix.
- A new `erp_m34b_<nonce>_sandbox` was created and seeded with synthetic fixtures.
- Before launching the HTTP server, the separate `php spark m34b:attest ...` process returned no accepted sandbox marker; the runner discarded its real error and only reported "Forked CI4 did not attest".
- The result was `NEEDS_FIX_OR_VERIFY`, **zero HTTP probes** and `NOT_RUN` rollback. This was **not** a failure of any one of the 29 mutation APIs.

The exact underlying error in the child was lost by M3.4B.3 and cannot be retroactively inferred from the report alone. CI4's `testing` environment is reserved for PHPUnit and can change framework behavior; it should not be used to host an ordinary HTTP integration server.

## Changes

1. Use `CI_ENVIRONMENT=development` **inside the isolated child only**. Parent/current `.env` and production write mode are unchanged. Normal HTTP/session mechanics apply; no special PHPUnit `testing` environment behavior.
2. All `database.default.*` and `database.tests.*` settings, including `DBPrefix`, still point to the one generated, nonce-owned sandbox. The CLI attester and HTTP router verify environment, hostname, schema, prefix, and marker before any mutation.
3. Ensure the isolated session directory exists before the child Spark attestation.
4. Use `SELECT DATABASE() AS selected_schema` in CLI attestation for an unambiguous field name.
5. On failed attestation, write **structured, sanitized metadata only** to `backend/writable/m34b/run_<nonce>/child_attestation.json`. Includes `code`, child `exit_code`, and advice; never write raw stdout/stderr/SQL or passwords.
6. Do not proceed to PHP HTTP server if child attestation fails. On any failure, retain the generated sandbox for diagnosis.
7. Add 25 independent assertions, including spawning a PHP subprocess to verify dotted `database.*` env keys survive `proc_open()`.

## Install and run on Windows

Upgrade M3.4B.3 by unpacking the ZIP overlay at `D:\XAMPP\htdocs\` (archive contains the `erp/` root). Do not modify `.env`, `backend_basline/`, or `frontend/`.

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN INTEGRATION
```

Expected offline coverage: **42/42 suites**; inventory **29/29 operations**. The complete HTTP/real MariaDB result is determined only by the Windows `INTEGRATION` run. A successful child attestation should create a `child_attestation.json` with `status: PASS` and `code: ATTESTED`, after which the real HTTP tests begin.

On integration failure, provide the new top-level report from `backend/writable/m34b/reports/` and, if the child was blocked, its **sanitized** `child_attestation.json`. Do **not** send database passwords or raw `.env` / framework logs.

Codes include `CHILD_ENVIRONMENT`, `CHILD_GUARD`, `DB_CONFIG`, `DB_PREFIX`, `DB_MARKER`, `DB_CONNECTION`, `CLI_COMMAND`, `CLI_BOOT` and `CLI_EXIT_OR_MARKER`. These are diagnostic codes, not API error codes. A code of `CLI_EXIT_OR_MARKER` means the old error cannot yet be categorized and must not be treated as PASS.

## Scope and acceptance criteria

- Existing Frontend, `backend_basline/`, API routes, 29 mutation contracts, PBAC, locale files and business services stay unchanged.
- `CHECK` and ordinary `RUN` never touch MariaDB. `DB-READ` remains read-only.
- `INTEGRATION` runs only on an isolated local MariaDB sandbox; no production writes and no automatic fallback to using the template as a target.
- Offline tests **cannot** establish 29 successful HTTP mutations, real rollback, or live baseline parity.
- M3.4C Modular Monolith boundary refactor is still next after addressing integration blockers: Security Users to Identity and Workflow/Lifecycles to Workflow.
