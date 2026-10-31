# NUM-HF05 Backend QA / acceptance

This is a TEST-ONLY companion patch for `DinkERP_v2.3.0_NUM_HF05_Overview_SingleRead_PreserveUI.patch`.
Apply HF05 first. HF03 is optional. Do NOT apply superseded HF04. This QA
patch adds only tests, test runners and documentation; no production API, UI,
SQL schema, permission policy or fixture data is modified.

## 1. Offline backend regression (no Composer, HTTP or database)

From `D:\XAMPP\htdocs\erp\backend` in PowerShell:

```powershell
.\tools\test-unit.ps1 RUN NUM-HF05-UNIT
```

The runner exits non-zero on the first failing test and checks all of:

- HF05 overview projection: authorized Resource filtering, serialization,
  duplicates and cross-Resource rows (15 existing assertions).
- HF05 Service fake SQL/PBAC: one Resource read plus authorized series read,
  no unauthorized data (5 existing assertions).
- HF05 SQL/PBAC stress/fault injection: 405 Resource rows produce 200+200+5
  `whereIn` batches; order, full response, denied rows, SQL failures on first
  and last batch; no partial response (28 new assertions).
- HF05 controller/route/OpenAPI contract: GET-only, approved CORS route,
  shared response envelope, Shield authentication, delegated PBAC, old
  Numbering CRUD endpoints intact (30 new assertions).
- DEV HTTP auditor safety: loopback-only, only login POST + business GET,
  explicit operator confirmation, credential handling (17 new assertions).
- Existing M4 Numbering validate-config, Numbering Rules, 18 pattern
  differential assertions, security and route contract, M3.3 routes, M19.24 route architecture + frozen registry.

`RUN UNIT-STANDARD` also executes the 5 focused HF05 test scripts as part of
normal project regression. `RUN ROUTES` continues to validate the frozen
routing system. Running `php spark` or PHPUnit requires Composer dependencies,
but the dedicated offline tests do not.

## 2. Optional authenticated DEV HTTP integration

**Operator gate**: the Apache instance selected by the reviewed virtual host
MUST point to the intended DEV clone. The helper pins the connection to
`127.0.0.1` and refuses arbitrary remote URLs. `DEV_CLONE_ONLY` expresses
operator confirmation; it does NOT inspect or prove the database identity.

Prepare two existing active DEV accounts: `ActorAllow` with effective
`resource.view` + `resource.configure_numbering` and at least one authorized
Resource; `ActorDeny` with no effective configure_numbering access to any
Resource. Never use credentials for a production account.

```powershell
cd D:\XAMPP\htdocs\erp\backend

.\tools\num_hf05_run_dev.ps1 `
    -ActorAllow '1' `
    -ActorDeny '2' `
    -LoginAllow '<DEV_ALLOW_USERNAME>' `
    -LoginDeny '<DEV_DENY_USERNAME>' `
    -DevConfirmation DEV_CLONE_ONLY `
    -HttpHost api.monmin.net `
    -Scheme https `
    -MaxResources 30
```

The wrapper prompts for passwords without printing them. No password or
session cookie is written into the reports, command arguments or console.
After login (the ONLY POST), the test performs:

1. Anonymous overview GET must return 401 with no data.
2. Authorized overview GET must return 200, with unique Resource IDs, valid
   `allowed_actions.configure_numbering` and strictly scoped series rows.
3. Each returned Resource is compared with the existing scoped
   `GET /api/v1/resources/{id}/numbering-series` API. Compare all rows by
   canonical JSON fields; no data writes.
4. The denied actor must receive either 403 with no data, or 200 with both
   arrays empty. A non-empty payload or 401 after successful login is FAIL.
5. The test reports explicit request counts and a PASS only if all allowed
   Resources were checked. More than `-MaxResources` produces INCONCLUSIVE
   (exit 3), not a false PASS.

Sanitized JSON report:
`backend/writable/num_hf05/reports/num_hf05_http_*.json`.
The test does NOT prove measured MariaDB query count, baseline DB snapshot,
write transaction/concurrency parity or production acceptance. It requires
local Apache, two actors and real authenticated HTTP; these are unavailable
in the sandbox used to author the test-only patch.

## 3. Known separate frontend E2E failures (not backend acceptance)

The uploaded frontend log shows 28/33 PASS in the 3-browser Playwright run:
three instances of `a denied Resource refresh fails closed...` did not find
`.numbering-panel .inline-error`; two cases in Firefox/WebKit could not click
Refresh because an open NSelect dropdown intercepted pointer events. The
former requires a browser trace/network state check before deciding whether
the UI or test fixture is at fault. The latter is a browser test interaction
that should close the dropdown before clicking Refresh. These are **NOT**
counted as backend PASS and this test-only patch does not modify frontend
behavior or these tests.

## 4. Acceptance standard

- Offline regression: green is evidence only for pure/fixture/source rules.
- DEV HTTP: green is evidence of authenticated API response/serialization
  parity and negative PBAC, but not complete DB/runtime proof.
- Final release: verify real browser E2E, CSRF/session response handling, and
  SQL/profile measurements independently. Do not deploy to production based
  solely on the offline tests.
