# M8.7A — DEV One-Run Diagnostics, M8.7 parity gate repair

Date: 2026-10-25. Applies incrementally **after M8.7**. No OpenAPI change, migration, business mutation or changes to canonical authorization SQL scope.

## 1. What the operator's logs prove

The operator reported `INCONCLUSIVE_DB_FIXTURE_COVERAGE` for Shield actors `1` and `2` against `erp`. This **does not prove** SQL authorization is correct or incorrect. M8.7 explicitly requires independently verified allow/deny scenarios, positive and negative visibility and Unicode search coverage per resource. Only the actual JSON report reveals which resources have gaps.

The HTTP preflight printout showed `ConsentValid=False` and all four actor credential variables absent. These conditions necessarily block HTTP authentication regardless of whether the Apache virtual host is `localhost` or `api.monmin.net`. The old tool collapsed errors into `InvalidArgumentException` plus `REVIEW_LOCAL_ERROR_LOG`; it could not show a useful reason code.

During source review an additional M8.7 **test harness defect** was found: `M87SqlVerifier::queryScenarios()` omitted Product search source fields. Consequently Product's `unicode_case_tested` was permanently `false`, making overall `PASS_DEV_SQL_PARITY` unreachable. M8.7A adds real Product Unicode search fields; it does **not** alter Product API SQL or waive Unicode coverage.

## 2. M8.7A changes

- `M87Preflight`: checks DEV consent, reviewed host/scheme, four environment credential variables and DB report format/status/actor IDs/15-minute freshness. Issues are categorized; no credential values are printed.
- `M87HttpDiagnostic`: assigns fixed sanitized error codes for local Apache transport, CSRF, login, authenticated actor mismatch and invalid JSON, without disclosing password/cookie/token or raw response.
- `m87_preflight.php`: local, **zero network/database traffic** preflight.
- `m87_report_summary.php`: reports status, per-resource visible/hidden counts, Unicode coverage and `coverage_gaps`, without raw record IDs or PII.
- `m87_run_dev.ps1`: prompt for both passwords securely, run DB audit then preflight and loopback HTTP in one session, always remove ephemeral credentials from PowerShell's process environment.
- `M87SqlVerifier`: completes Product Unicode search coverage. It still requires real Unicode data and actual parity; no synthetic PASS.
- Two new offline regression suites and source acceptance gate. All 150 frozen routes unchanged.

## 3. Source QA — safe without database

From `D:\XAMPP\htdocs\erp`:

```powershell
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m87a_preflight_pure.php
php tests\m87a_launcher_pure.php
php tools\m87a_accept.php
```

## 4. One-run MariaDB + HTTP audit (preferred)

**Prerequisites:** an independently confirmed, disposable DEV database clone; `CI_ENVIRONMENT=development` or `testing`; two distinct active Shield users belonging to that same database; both user passwords; Apache serving API locally through a reviewed virtual host, and no concurrent business writes during parity. The database name `erp` is NOT proof it is safe to audit: independently verify its purpose before running this workflow.

PowerShell (run in backend):

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\m87_run_dev.ps1 `
    -ActorA '1' -ActorB '2' -Database 'erp_dev' `
    -LoginA 'LOGIN_CUA_ACTOR_1' -LoginB 'LOGIN_CUA_ACTOR_2' `
    -DevConfirmation DEV_CLONE_ONLY `
    -HttpHost api.monmin.net -Scheme https -MaxRows 1000
```

`1`, `2`, `erp_dev` and the login strings are **examples**. Replace with true local DEV user IDs, database name and username/email. With local Apache HTTP, use `-HttpHost localhost -Scheme http`. This requires the local Apache virtual host to accept the corresponding request and port. `api.monmin.net` in this tool means an HTTP `Host`/TLS SNI name while the socket **always** connects to `127.0.0.1`, not to the remote public server. TLS peer verification is disabled only for this loopback QA transport.

The runner requests two hidden passwords BEFORE generating the DB report so that there is enough time to complete HTTP within the **15-minute freshness window**. It enforces a distinct login and distinct Shield ID, validates explicit DEV-clone operator confirmation, detects the exact new DB report (rather than guessing the latest file), shows coverage, checks preflight, then runs HTTP. The process-scoped environment is cleared in `finally`, including after failure. There is no new persistent `.env` feature flag.

If a vetted case manifest exists, pass `-CasesFile 'D:\dev-qa\m87-cases.json'`. This is recommended but not fabricated by the tool. It must use independently confirmed persisted records corresponding to each actor's real PBAC allow/deny decisions.

**Important:** HTTP login executes `POST /api/v1/auth/login` solely to authenticate. No business writes or schema operations are performed. The SQL command uses its existing READ ONLY transaction and rollback behavior.

## 5. Diagnostics without credentials or HTTP traffic

Show any existing DB audit report, even if older than 15 minutes:

```powershell
php tools\m87_report_summary.php .\writable\m87\reports\m87_YYYYMMDD_HHMMSS_nonce.json
```

After configuring ephemeral DEV variables and creating a **fresh** SQL audit report, run:

```powershell
php tools\m87_preflight.php .\writable\m87\reports\m87_YYYYMMDD_HHMMSS_nonce.json
```

Typical sanitized codes:

| Code | Action |
|---|---|
| `MISSING_HTTP_DEV_CONSENT` | Set exact `DEV_HTTP_READ_ONLY`; use one-run script to do this automatically |
| `MISSING_M87_ACTOR_A_USER`, `MISSING_M87_ACTOR_B_USER` | Provide actual Shield usernames or emails matching DB actor IDs |
| `MISSING_M87_ACTOR_A_PASSWORD`, `MISSING_M87_ACTOR_B_PASSWORD` | Supply passwords privately; do not paste them into chat or terminal logs |
| `INVALID_OR_MISSING_LOCAL_VHOST` | Use `localhost` or `api.monmin.net` only; verify Apache vhost |
| `INVALID_OR_MISSING_LOCAL_SCHEME` | Use `http` or `https` on reviewed local port |
| `DB_REPORT_OUTSIDE_15_MIN_WINDOW` | Re-run MariaDB read-only audit immediately before HTTP |
| `DB_PARITY_REPORT_NOT_USABLE` | Resolve failed/blocked DB audit; never bypass it |
| `LOCAL_APACHE_CONNECTION_OR_TIMEOUT` | Check Apache listener, vhost, port, SSL and PHP connectivity |
| `CSRF_ENDPOINT_NOT_200`, `CSRF_TOKEN_MISSING` | Check API base path and auth/csrf response contract |
| `LOGIN_HTTP_NOT_200` | Confirm credentials and active Shield account |
| `ACTOR_IDENTITY_MISMATCH` | Confirm the username/email logs into the same actor ID as the DB report |
| `HTTP_RESPONSE_NOT_JSON` | Check Apache rewrite/vhost or HTML error page; never print tokens |

Reports only contain sanitized `error_codes`, failure `stage` and counts; no raw exception message or credential values.

## 6. Why DB still may be INCONCLUSIVE

M8.7 does not create fixtures. To satisfy the existing coverage gate, **each of the nine resources for each actor** needs rows on both sides of the visibility decision and a real Unicode search case; additionally, the validated case manifest must contain known `allow` and `deny` record IDs for `organization`, `employee_assignment` and `contact` for **each actor** (12 list entries minimum, one verified ID per entry). Missing tables, missing permissions, no Unicode or full-table bounds result in `INCONCLUSIVE`, not a fabricated PASS. Actor A and B must demonstrate genuinely different company permissions, and persisted policy precedence (`DENY`/`ABSOLUTE_DENY`) needs separate attestation.

Read `coverage_gaps` from the report summary and build real DEV fixtures in a **separate authorized fixture-preparation workflow**, not through this read-only runner. A 512 organization/scope cap also remains fail-closed. Raising `MAX_ROWS` up to the audited limit 5000 is possible only if database size is safe; it does not repair missing scenarios.

## 7. Non-negotiable acceptance gates

- A source QA PASS is not a MariaDB PASS. `INCONCLUSIVE_DB_FIXTURE_COVERAGE` is not an SQL bug verdict.
- HTTP `INCONCLUSIVE_BASELINE_AND_SCENARIO_GATES` can mean executed GET comparisons match, but it does not approve production.
- `FAIL_DEV_SQL_PARITY` or `FAIL_DEV_HTTP_PARITY` requires investigation and repair before deployment.
- Independent baseline parity, concurrency/assignment races, role and policy precedence, Unicode/NULL sorting behavior and agreed performance thresholds remain mandatory.
- No `enabled`/`disabled` or `legacy`/`shadow` read-mode flag is reintroduced. Reverting application code, not a mode flag, is the rollback mechanism.

**Current milestone state: QA HARNESS SOURCE ACCEPTED only after local gates; MariaDB/HTTP actual results not supplied to this patch author; production BLOCKED.**
