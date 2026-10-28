# DinkERP Backend v2 — M3.4A: Access Mutation HTTP/Clone Parity Harness

Status: **IMPLEMENTED / OFFLINE PASS / CLONE EXECUTION NOT YET VERIFIED**. As of 2026-10-23. This is a **harness delivery**, not a claim that real HTTP mutation/rollback parity passed.

## Scope

- Keep `frontend/` and `backend_basline/` unchanged; keep existing 70 API operations unchanged.
- Add PHP CLI tools that work without Composer to compare baseline v1.2.4 and Backend v2 for the **29 previously implemented Access mutations**.
- Pin API contracts to `docs/backend-rewrite/openapi-frozen-v1.2.4.yaml` SHA-256 and validate all explicit v2 routes.
- Check locale (`Accept-Language`/`Content-Language`), API error envelope, `X-Request-ID`, CSRF, Shield session, HTTP status, `error.code`, `message`, `details`. Only `meta.request_id` is ignored when comparing JSON; all other fields remain strict.
- Reject requests if their origin is not local loopback. Require two independent, manually marked disposable MariaDB clone schemas and a per-run `run_id`.
- Do not write any SQL through the harness. HTTP positive replay **does** cause writes in two disposable app databases, only after explicit confirmation and scenario review.

## Test phases and evidence levels

| Phase | Command | Effect | Current evidence |
|---|---|---|---|
| Static inventory | `php backend/tools/m34_http_parity.php inventory` | No network, DB or mutations | 29/29 pinned operations verified offline |
| Clone preflight | `... preflight --config=... --confirm=M34_CLONE_ONLY` | DB reads only | Not run on XAMPP clone |
| HTTP negative matrix | `... negative --config=... --confirm=M34_CLONE_ONLY` | 29 × 4 = 116 paired probes, 232 total mutation HTTP requests to two CLONE apps; rejects unexpected DB Access table changes | Not run on XAMPP clone |
| Positive mutation replay | `... positive --config=... --cases=... --confirm=M34_CLONE_ONLY` | 29 *authorized* operations on two separately disposable CLONE apps, with before/after table digests | Not run, template is disabled |
| Real Shield hashing and MariaDB rollback | Explicit negative SQL/permission scenarios on two disposable clones, plus verification of hash via login | Destructive clone-only | Not run; separate closure gate |

The negative matrix runs, per endpoint: (1) authenticated `{}`/missing ID in `en`, (2) the same in `vi`, (3) bad CSRF in `en`, and (4) anonymous no-cookie/no-token request in `vi`. Each must return 4xx; no tracked Access table may change. If the v2 feature gate is OFF, or if any operation unexpectedly succeeds / returns 5xx / mutates data, the runner fails closed. Each response is compared to the running baseline, not just a static fixture. Note that the mutation client may still cause normal login/session/security-log writes; these are intentionally excluded from Access business-table fingerprints.

**Positive-replay caution:** The initial 29-scenario JSON is a template, not ready-to-run data. Every `ready` is `false`. Some example payloads (e.g. participant IDs, Workflow graph, Policy PATCH) must be adapted to the approved baseline and seeded clone data. The tool requires **all 29** cases prepared and explicitly marked ready. A partially filled template cannot trigger writes.

## Clone setup — Windows XAMPP only; do not use production

1. Back up original MariaDB. Create **two independently disposable DB copies from the same frozen dump**: `erp_m34_v2_clone` and `erp_m34_baseline_clone`. Never reuse the source DB. Keep the same user IDs, grants, Policy/Resource catalog, table definitions and initial row IDs in both clones.
2. Prepare **two independently hosted local app copies** using those clones only. Examples: `http://127.0.0.1:8081` -> new `backend/public`; `http://127.0.0.1:8082` -> pinned `backend_basline/public`. Install Composer dependencies in these app copies if required. In each *copy's* `.env`, set `CI_ENVIRONMENT=testing`, DB credentials for the appropriate `_m34_*_clone` schema and disable caching between requests. **Only in the new Backend v2 clone**, set `DINKERP_M33B_WRITE_MODE=enabled`. Do not change the real deployment `.env`.
3. Manually create a test-only marker table in **each cloned database** (the harness itself cannot create it). Generate a new cryptographically random 32+ character `run_id` and use exactly the same nonce for both markers and the local configuration. SQL below is an example; run it **only after selecting and verifying the clone schema**:

```sql
USE erp_m34_v2_clone;
SELECT DATABASE();
CREATE TABLE m34_clone_guard (
  clone_role VARCHAR(16) PRIMARY KEY,
  run_id VARCHAR(80) NOT NULL
);
INSERT INTO m34_clone_guard (clone_role, run_id)
VALUES ('v2', 'REPLACE_WITH_YOUR_RANDOM_RUN_ID');

USE erp_m34_baseline_clone;
SELECT DATABASE();
CREATE TABLE m34_clone_guard (
  clone_role VARCHAR(16) PRIMARY KEY,
  run_id VARCHAR(80) NOT NULL
);
INSERT INTO m34_clone_guard (clone_role, run_id)
VALUES ('baseline', 'REPLACE_WITH_YOUR_RANDOM_RUN_ID');
```

4. Copy `backend/tests/fixtures/m34_clones.config.example.json` to a **private local config file** (for example `backend/writable/m34/clones.local.json`), replace `run_id` and update *local clone* ports/credentials variable names. Never put plain-text passwords in the config. Provide `M34_V2_DB_PASSWORD`, `M34_BASELINE_DB_PASSWORD`, `M34_V2_LOGIN_USER`, `M34_V2_LOGIN_PASSWORD`, `M34_BASELINE_LOGIN_USER`, and `M34_BASELINE_LOGIN_PASSWORD` through the PowerShell process environment or another protected local mechanism. Use an actor with appropriate baseline admin rights in both copies. No secret is included in reports.
5. Verify both HTTP servers and DB clones are not the real site. **The tool will reject `https://api.monmin.net`, any non-loopback origin, production-shaped DB names, missing marker or nonce mismatch.** It also rejects a config with the same origin or database assigned to both roles.

## Run the gates

```powershell
cd D:\XAMPP\htdocs\erp

php backend\tests\m34_contract_pure.php
php backend\tests\m34_safety_pure.php
php backend\tests\m34_transport_pure.php
php backend\tools\m34_http_parity.php inventory

# Only after provisioning two real, disposable clones:
php backend\tools\m34_http_parity.php preflight --config=backend\writable\m34\clones.local.json --confirm=M34_CLONE_ONLY
php backend\tools\m34_http_parity.php negative --config=backend\writable\m34\clones.local.json --confirm=M34_CLONE_ONLY --out=backend\writable\m34\negative_report.json

# OPTIONAL: only when all 29 cases have been reviewed and marked ready=true;
# clone DBs must be freshly restored from the same clean snapshot first.
php backend\tools\m34_http_parity.php positive --config=backend\writable\m34\clones.local.json --cases=backend\writable\m34\positive.local.json --confirm=M34_CLONE_ONLY --out=backend\writable\m34\positive_report.json
```

Use `backend/tests/fixtures/m34_positive_scenarios.template.json` as a preparation worksheet, not as a runnable test. Scenario `{{scenario_name.data.id}}` references are resolved **independently for each app**, preventing IDs from one clone being blindly sent to the other. `${ENV:VAR_NAME}` supplies a test-only password without storing it in the fixture. Before running positive replay, check ALL placeholder IDs, group memberships, Policy scopes, Workflow graph, transaction rules, and expected HTTP statuses.

**No environment or production DB changes are made by installing this overlay.** It does not enable routes; `DINKERP_M33B_WRITE_MODE=off` remains the required production configuration.

## What is compared — and what is not

- For invalid/CSRF/unauthenticated probes: exact status, error JSON excluding only per-request request ID, locale header, and zero changes to the 14 monitored Access/Shield tables (the SQL fingerprint ignores `users.last_active` only). A mismatch produces a FAIL, not a success.
- For authorized positive replay: exact success HTTP status and normalized JSON (request ID only ignored), changed table names, and post-operation SHA-256 fingerprints for all monitored business tables. Unlike timestamps, Shield password hashes are expected to be non-deterministic between independent requests. A hash difference causes **FAIL/REVIEW**; this tool never silently asserts equivalence for divergent database contents.
- CI4 sessions, Shield login events, `authorization_logs`, untracked tables and actual database commit/concurrency traces are **not** asserted by the business-table fingerprint. Those need dedicated external DB change audits and rollback/concurrency cases before final M3.4 sign-off.
- Real HTTP/CSRF/E2E has **not** been run against the user's actual clone; the local transport fixture is a mock, not baseline runtime.

## Offline implementation QA

- `m34_contract_pure.php`: 15 assertions PASS.
- `m34_safety_pure.php`: 25 assertions PASS.
- `m34_transport_pure.php`: 10 assertions PASS using a loopback HTTP fixture (does NOT connect to ERP/DB).
- M1–M3.3B4 + M3.4: 34/34 PHP pure/fixture test scripts PASS on source snapshot.
- No new public API; all 70 previous operations remain unchanged.

**Milestone outcome:** M3.4A harness **implemented and offline verified**. M3.4 actual transaction/HTTP/baseline parity is **PENDING clone execution and defect correction**. Do NOT cut over PBAC enforce or enable real mutation based on the offline results.
