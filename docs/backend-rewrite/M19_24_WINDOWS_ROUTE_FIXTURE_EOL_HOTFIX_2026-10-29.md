# M19.24 - Windows CRLF frozen route fixture hotfix (2026-10-29)

## Scope

Offline-only test reliability change for the v1.9.24 modular Routes refactor.
No production PHP controller, module route registration, PBAC, SQL, migrations,
OpenAPI contract or frozen fixture contents were modified.

## Reproduction / root cause

`backend/tests/m1924_route_architecture_pure.php` originally used raw
`hash_file('sha256', ...)` on four JSON fixtures authored with LF newlines.
A Windows Git checkout with `core.autocrlf=true` can materialize CRLF instead,
causing a checksum mismatch even if all 180 registered route signatures remain
identical. A controlled LF-to-CRLF conversion of `routes_v1923.json` reproduced
the exact `M19.24 ROUTE ARCHITECTURE FAILED: frozen route baseline evidence`
fatal error reported on Windows.

## Fix and hardening

* `.gitattributes` pins `backend/tests/fixtures/routes_v1923*.json` to LF
  when Git checks out those files. Existing CRLF checkouts are still accepted.
* The M19.24 pure test canonicalizes CRLF to LF **only when computing the
  SHA-256 over fixture evidence**. The four original reference checksums are
  untouched, so changes to fields/values, whitespace and record order still
  cause failure. Stray bare-CR bytes are refused.
* Each frozen fixture now has explicit positive CRLF and negative tampering /
  malformed-newline guards. Actual routing, handler and CORS checks are unchanged.

## Verified offline in the supplied v2.0.0 source archive

* Original four fixture SHA-256 values match all four pinned expected values.
* `php backend/tests/m1924_route_architecture_pure.php`: **470/470 PASS**.
* LF and simulated Windows CRLF both PASS; altering fixture contents FAILS.
* `php backend/tools/routes/route_inventory.php --verify`: **180/180 PASS**.
* `python backend/tools/verify_routes.py`: **150 P1 + 180 complete routes PASS**.
* `php backend/tests/m33_routes_pure.php`: **20 checks PASS**.
* `php backend/tests/m34d_contract_pure.php`: **17 checks PASS**.
* `php backend/tools/test-unit-check.php`: **PASS**.
* `P1-STANDARDS` and `CUS-UNIT` individual pure tests: **PASS**.

`php spark routes` and the Windows PowerShell wrapper could not run in this
Linux archive test environment because `backend/vendor` and `pwsh` are absent.
The submitter's previously captured before/after compiled route comparison
showed no difference; re-run it on Windows after deployment.

## Windows application (project root)

```powershell
# First keep a backup or commit of the current clean workspace.
git apply --check .\DinkERP_M1924_Windows_Route_Fixture_Hotfix.patch
git apply .\DinkERP_M1924_Windows_Route_Fixture_Hotfix.patch
git diff --check
cd .\backend
.\tools\test-unit.ps1 RUN ROUTES
python .\tools\verify_routes.py
.\tools\test-unit.ps1 RUN P1-STANDARDS
.\tools\test-unit.ps1 RUN CUS-UNIT
```

Stop if `git apply --check` fails; do not force the patch or rewrite the frozen
JSON files/checksums to silence the gate. On a persistent failure after the
hotfix, compare **LF-normalized** SHA-256 of each local frozen fixture against
its unchanged pinned value to identify a genuine local source divergence.

## Other broad-suite findings (not part of this routing-only patch)

A supplemental `php tools/m34b_run.php --offline` on the supplied archive
reported **159/165** suites PASS. Six existing unrelated failures were
identified in `m322_baseline_fidelity_pure.php`, `m34b1_launcher_pure.php`,
`m34c_owner_reads_fixture.php`, `m34d_architecture_pure.php`, `m3_pure.php`
and `m8_contract_pure.php`. They are not prerequisites of the requested
`RUN ROUTES` gate. In particular, the broad M3.4D gate identifies current
cross-module cycles (Workflow / Notifications / Customer), which require a
separate module-design change; weakening that gate would conceal a real
architecture issue. Do not treat `UNIT-STANDARD` as PASS.
