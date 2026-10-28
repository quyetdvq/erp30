# M6.2B.2 — DEV Product successful-write + failed-COMBO probes

## Purpose

M6.2B.1 already passed six intentional rejection probes in both `en` and `vi` on the user's XAMPP host. M6.2B.2 adds **opt-in successful HTTP CREATE and PATCH**, plus negative duplicate SKU and malformed-after-insert COMBO scenarios. This release changes **no Product controller/service/route/database migration or baseline code**. The API inventory remains **125/150**.

This is a **development test**. It reuses `api.monmin.net` on `https://127.0.0.1:443` using the configured Apache Host and SNI. It does not need another VirtualHost, a cloned database, a guard table, a dump, or an extra PHP server. It **writes to the existing DEV database** and **leaves a clearly labelled Product fixture** because the accepted Product contract does not provide DELETE. Never run it against a real customer/production database.

## Preconditions

- `backend` contains the M6.2B.1 revision; `DINKERP_M33B_WRITE_MODE=enabled` **only in development**.
- A DEV actor with Product VIEW, CREATE and EDIT rights can log in via the local Apache site.
- A Product Group and a UOM both exist and are ACTIVE, with the Group's ancestor chain active. Use actual IDs on this database. A previous screenshot showed possible IDs `product_group=3`, `product_uom=1`; they are *examples only*, not preauthorized choices.
- Product's persisted Lifecycle has an initial state; the actor has permission in that state.
- Use a DEV database backup if created records must be removable after testing. The tool does not delete business rows or adjust numbering counters to clean up.

## Running

From `D:\XAMPP\htdocs\erp\backend`, first run the harmless plan (NO HTTP, NO DB):

```powershell
php tools\m62b2_dev_write.php --group-id=3 --uom-id=1
```

Then supply the same DEV credentials as M6.2B.1 and explicitly authorize writing:

```powershell
$env:M62B_DEV_USER = 'quyetdv'
$secret = Read-Host 'DEV password' -AsSecureString
$env:M62B_DEV_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password

php tools\m62b2_dev_write.php --group-id=3 --uom-id=1 --apply --confirm=DEV_PRODUCT_WRITE

Remove-Item Env:\M62B_DEV_PASSWORD
Remove-Item Env:\M62B_DEV_USER
```

The command writes one Product with a unique marker `M62B2_XXXXXXXXXXXX` and a `DEV_` SKU. It first uses manual Product code; if the default series explicitly rejects manual codes with `NUM_IDENTIFIER_MANUAL_NOT_ALLOWED`, it retries **without** a manual code, using the server's default AUTO policy. If that configuration cannot issue a code, it fails and reports the underlying **stable error code**. It does not reconfigure series or grant access.

The command checks:

1. Authenticated actor and ACTIVE Group/UOM references.
2. Product CREATE returns HTTP `201`, plus successful GET by its ID.
3. PATCH `description` returns `200`, GET confirms it persisted without modifying code/SKU.
4. A second Product with a duplicate SKU is rejected by `409 PRODUCT_IDENTIFIER_DUPLICATE`.
5. A COMBO Product with a valid shape but a nonexistent component fails by `404 PRODUCT_NOT_FOUND`; the API `GET products?q=MARKER` shows only the successfully created fixture. **This is not SQL atomicity proof.**
6. Optionally, `--target-state=ACTIVE` tests a **genuine** `POST /products/{id}/actions/set-state` on that disposable fixture. Only provide a target supported by the persisted Lifecycle. Omit otherwise, and state will be `NOT_TESTED`.

The JSON report is written under `backend/writable/m62b/reports/m62b2_dev_*.json`. If the runner fails after CREATE, the marker and new ID are still printed/reported so the fixture is not lost. Passwords, cookies and CSRF values are never serialized.

### Read-only SQL verification (same DEV backend)

Copy the **marker and ID** printed by the successful runner, e.g.:

```powershell
php spark m62b2:db-audit M62B2_012345ABCDEF 123
```

Expected: `PASS_DEV_PRODUCT_SQL_ROWS`. This verifies that the PHP CLI's configured MariaDB has exactly one of the test Product SKUs, that it is the returned ID, its PATCHed description is stored, and no COMBO row belongs to the SIMPLE fixture. The audit performs **SELECT only**. It also helps confirm Apache and CLI are connected to the same DEV dataset.

This **does not prove** that a failed AUTO-numbering transaction rolled back the counter: that needs counter snapshots before/after an actual failed issuance (and a two-writer test). It does not prove successful COMBO component persistence or production parity. Do not label those as PASS on this evidence.

## Regression and ownership

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

These commands should report **93/93 offline suites**, 6/6 M3.4D gates and **125/150 source APIs**, with no DB writes. The additional CLI tool is not run as part of `test-unit.ps1 RUN`, so ordinary daily tests remain non-mutating.

## Acceptance boundaries

- **OFFLINE**: pure safety/contract tests, no new API routes.
- **DEV success HTTP**: CREATE + PATCH + negative duplicate/COMBO response, only when explicit `--apply` is used.
- **DEV SQL row check**: optional separate read-only `m62b2:db-audit` command, not automatically inferred from HTTP GET.
- **OPTIONAL SETSTATE**: only when the caller specifies an allowed transition target.
- **NOT_TESTED**: counter atomicity, successful COMBO persistence, successful two-writer AUTO issuance, full rollback of all possible SQL side effects, independent baseline write parity, and production readiness.

## M6.2B.2.1 — Preflight diagnostics hotfix

If `--apply` stops at `phase=preflight` with `id=NOT_CREATED`, **do not retry the write** until it is diagnosed. Apply `M6.2B.2.1-dev-preflight-diagnostics.patch` and use `php tools/m62b2_dev_write.php --group-id=3 --uom-id=1 --preflight` with the existing DEV credentials. It sends only login and bounded GET requests; see `M6_2B_2_1_DEV_PREFLIGHT_DIAGNOSTICS_QA.md` for status/error-code interpretation.
