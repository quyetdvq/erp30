# M3.4D.1 — Windows line-ending acceptance fixture hotfix

## Problem

On Windows `git apply` can leave `backend/app/Config/Routes.php` with `CRLF` line endings.
The M3.4D `m34d_contract_pure.php` mutation-negative test previously tried to remove a
route with an exact `\n`-terminated string. The replacement would do nothing, so the
**test fixture** failed despite the original 70-route contract being valid.

Reproduced against M3.4D using `LF -> CRLF` in `Routes.php`:
`m34d_contract_pure.php` failed at `Missing Security User mutation is rejected`;
`m34b_run.php --offline` reported **47/48**. This is a reproduction of a matching
failure pattern, not proof of the exact failed suite on the user machine; inspect
`offline.failed` in its JSON report to confirm.

## Resolution

- Normalize **only the synthetic string used for fault injection** to LF, regardless
  of actual checkout line endings.
- Keep the real `Routes.php` untouched. All 70 live route declarations are still
  checked against SHA-256-pinned v1.2.4 contracts by `M34DAcceptance::contract()`.
- Add CRLF-positive and CRLF-negative assertions. Both full-route validity and
  missing-route rejection must succeed on the same synthetic CRLF input.
- Print just the **failing suite filename and exit code** to console, rather than
  hiding the failure inside a report or exposing raw output.

## Scope and safety

Changed: `backend/tests/m34d_contract_pure.php`,
`backend/tools/m34b_run.php`, this QA document.
No changes to `backend/app`, `frontend`, or `backend_basline`. No SQL, API, migrations,
production write-mode, pinned baseline hashes or authorization behavior changed.

## Windows usage

From `D:\XAMPP\htdocs\erp` apply `git apply --check M3.4D.1.patch` then
`git apply M3.4D.1.patch`; **or** extract the Overlay ZIP to `D:\XAMPP\htdocs\`.
Use only one method.

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
.\tools\test-unit.ps1 RUN INTEGRATION
```

`RUN` must pass all 48 offline suites, then six source gates. The CRLF test adds
assertions within an existing suite, so the suite count remains 48. The successful
sandbox integration gate must still report `29/29` mutations, `58/58` unauthenticated,
`29/29` CSRF, a real rollback pass and correctly source-bound M3.4D evidence.

For exact diagnosis before installing the hotfix:

```powershell
(Get-Content .\writable\m34b\reports\m34b_20261024_011048_79586432.json -Raw |
  ConvertFrom-Json).offline.failed | Format-List
```

If the failed suite is *not* `m34d_contract_pure.php`, its details must be
reviewed separately; do not bypass offline gates or enable production writes.
`DINKERP_M33B_WRITE_MODE=off` remains mandatory in production.

**Limits:** Passing these checks is not independent live baseline HTTP parity or
baseline transaction/concurrency equivalence. No actual Windows or MariaDB
execution is claimed by this hotfix build.
