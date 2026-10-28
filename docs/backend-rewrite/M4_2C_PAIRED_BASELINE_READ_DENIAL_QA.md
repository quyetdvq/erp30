# M4.2C — Two independent clone-backend HTTP parity: read/dry-run and denial probes

**Milestone entry:** 2026-10-24. **Delivery scope: tooling and source/offline checks only.** The operator reported M4.2A.1 `PASS_LOCAL_M4_SANDBOX` (14/14 HTTP routes) and M4.2B `PASS_LOCAL_M4_TWO_WRITER` (concurrent Numbering **configuration** creation). Those results do **not** attest a second live v1.2.4 HTTP process. This milestone provides the next, opt-in differential-test harness without enabling writes on any real deployment.

## What is covered

| Gate | Description | HTTP side effects | Coverage |
|---|---|---|---|
| `inventory` | Match each M4 controller route against the SHA-pinned v1.2.4 OpenAPI manifest and *independent v1.2.4 Routes.php SHA-256* | None | 14 named M4 operations |
| `preflight` | Read nonce markers and starting SHA-256 row fingerprints on **two manually provisioned, separately named MariaDB clones** | No HTTP; SELECT only | 2 clone database guards |
| `negative` | Pair a bad-CSRF authenticated request and a strictly anonymous request in `en` and `vi` for each of **6 write endpoints** | 24 paired denials / 48 HTTP requests; no authorized write | 6 mutations, denial behavior only |
| `read` | Pair the **7 GET endpoints and 1 read-only POST validate-config**, in `en` and `vi` | 16 paired read-only requests / 32 HTTP requests | 8 read/dry-run operations |

For every pair, compare complete HTTP status and structured JSON, removing **only** per-request `meta.request_id`; check `Content-Language`, error envelopes, and exact response body. Before and after **each** request, fingerprint all supported Access/M4 business tables. Any SQL change is a fail-closed STOP; metadata/session events not among fingerprinted tables cannot be claimed mutation-free. The inspector includes `users` but ignores `last_active` per the earlier M3.4 policy. The inspector has a bounded **50,000-row per tracked table** limit; larger clones require a separate review, not skipping the check.

The tool NEVER makes an authorized mutation. It does not test six successful write operations, two simultaneous issuance requests, or production cutover. Even a full `negative` + `read` PASS cannot close **successful mutation / rollback baseline parity**. **Production remains BLOCKED.**

## Required operator setup (Windows XAMPP; CLONES ONLY)

`backend_baseline/` must be running v1.2.4, separately from the v2 `backend/` process. The tool verifies the bundled baseline route source's hash, but **cannot prove which code directory an operator-managed HTTP origin is serving**. You must independently verify each server's document root, backend version, `CI_ENVIRONMENT=testing`, DB selection and port; this identity is documented as `OPERATOR_ATTESTED_NOT_AUTOMATED`, not inferred.

### M4.2C.1 — Windows Git checkout line endings

Git for Windows may materialize the *same pinned* v1.2.4 `backend_baseline/app/Config/Routes.php` with CRLF rather than the archive's LF. Original M4.2C would then fail `m42c_parity_safety_pure.php` and `inventory` before any DB/HTTP work. M4.2C.1 explicitly recognizes **only two SHA-256 values** of that immutable route file: LF `38b48e08f3add7668d32703ef149dee6caf43e9bbc983401444959a3287e5af7` and exact CRLF `2b0e228378648b2d045d486ddf69ae6d85ecd221048656a1704bf3ecd2632cb2`. Edited content, mixed newline styles, BOM, or any other bytes remain blocked. This is not live HTTP parity, and no baseline version check is bypassed.

If the gate still blocks after M4.2C.1, do not edit or replace the baseline file to force a PASS. From the ERP root, inspect just its SHA-256: `Get-FileHash .\backend_baseline\app\Config\Routes.php -Algorithm SHA256`. Compare it with the two accepted digests above; investigate an unexpected hash before any clone testing.

1. Make **two disposable copies of exactly the same frozen database dump**, retaining IDs, policy catalogs, organizations, M4 settings and numbering tables, with the same physical table prefix. Use `erp_m34_v2_clone` and `erp_m34_baseline_clone` (exact suffix required). No production or source database is writable by the runner.
2. Start two *separate* local HTTP servers, for example v2 `http://127.0.0.1:8081` and baseline v1.2.4 `http://127.0.0.1:8082`. Both must have working Composer vendor files and independent Shield sessions, and must point to the correct clones. **Enable `DINKERP_M33B_WRITE_MODE=enabled` only on the isolated v2 clone server**, never in the real project's `.env` or a publicly accessible server. Verify that the baseline server uses the original v1.2.4 code directory, not a second v2 process.
3. Manually create a `m34_clone_guard` marker in **each clone only**, with one newly generated unpredictable 32+ character `run_id`. The runner does not create tables, markers or clone databases. Use the same nonce for both markers and the local config:

```sql
USE erp_m34_v2_clone;
SELECT DATABASE();
CREATE TABLE m34_clone_guard (clone_role VARCHAR(16) PRIMARY KEY, run_id VARCHAR(80) NOT NULL);
INSERT INTO m34_clone_guard VALUES ('v2', 'REPLACE_WITH_RANDOM_RUN_ID');

USE erp_m34_baseline_clone;
SELECT DATABASE();
CREATE TABLE m34_clone_guard (clone_role VARCHAR(16) PRIMARY KEY, run_id VARCHAR(80) NOT NULL);
INSERT INTO m34_clone_guard VALUES ('baseline', 'REPLACE_WITH_THE_SAME_RUN_ID');
```

4. Copy `backend/tests/fixtures/m42c_clones.config.example.json` into a **private** location such as `backend/writable/m42/clones.local.json`. Set `run_id`, separate clone HTTP origins, DSNs and login usernames/password environment variable **names**. Do not include any password in JSON. Supply the environment variables referenced by the config: `M34_V2_DB_PASSWORD`, `M34_BASELINE_DB_PASSWORD`, `M34_V2_LOGIN_USER`, `M34_V2_LOGIN_PASSWORD`, `M34_BASELINE_LOGIN_USER`, `M34_BASELINE_LOGIN_PASSWORD`. These are credentials for cloned accounts only.
5. For the `read` phase, copy `backend/tests/fixtures/m42c_read_cases.template.json` to `backend/writable/m42/read.local.json`. Review all eight scenarios, substitute the IDs of pre-existing **identical rows on both clones**, check the Numbering validate-config payload, and only then change each `"ready": false` to `true`. Unreviewed placeholder or incomplete cases are rejected. **Do not** enter a production business identifier or URL. Only the `context_type=SYSTEM` query is allowed on Display Setting Values.

Before doing any of this, ensure there are fresh backups and that your terminal/environment is **not connected to production**. A failure keeps your **manually created clones**, which must be disposed of by the operator after independent verification. The runner has no SQL `CREATE`, `INSERT`, `UPDATE`, or `DROP` commands.

## Run, in strict order

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tools\m42c_parity.php inventory

# Complete the manual clone preparation steps above before these commands.
php backend\tools\m42c_parity.php preflight --config=backend\writable\m42\clones.local.json --confirm=M34_CLONE_ONLY
php backend\tools\m42c_parity.php negative --config=backend\writable\m42\clones.local.json --confirm=M34_CLONE_ONLY
php backend\tools\m42c_parity.php read --config=backend\writable\m42\clones.local.json --cases=backend\writable\m42\read.local.json --confirm=M34_CLONE_ONLY
```

If any gate fails, STOP. Do not attempt to bypass the nonce check or disable SQL fingerprint assertions. Restore both clone databases from the original frozen dump before repeating a phase if anything may have changed. Result JSON files are written locally at `backend/writable/m42/reports/m42c_*.json` (no secrets or business response bodies). For a mismatch, report only `status`, `paired_cases`, `failed` and the specific `issues` entries; never post `.env` or auth session cookies.

## Acceptance labels

- `PASS_PINNED_SOURCE_ONLY`: offline manifest and baseline source SHA are unchanged; **not live parity**.
- `PASS_CLONE_PREFLIGHT`: two marked databases start with identical tracked rows; **no HTTP**.
- `PASS_LOCAL_PAIRED_CLONES` for `negative`: 24 paired security denials match over HTTP; **zero authenticated writes tested**.
- `PASS_LOCAL_PAIRED_CLONES` for `read`: 16 bilingual pairs match (8 endpoints); **no successful mutation parity tested**.
- `FAIL_BASELINE_DIFFERENCE` or `BLOCKED_ENV_OR_CONFIG`: stop and investigate, never mark a partial run PASS.

**Closure still open:** independent authorized mutation replay and rollback parity for six M4 write routes; host runtime provenance remains operator attested; Numbering issuance concurrency remains not implemented; production write enablement stays BLOCKED (`DINKERP_M33B_WRITE_MODE=off`).
