# M8.7 — DEV SQL Authorization Runtime Parity & Performance Verification

**Baseline:** M8.6E SOURCE ACCEPTED (150 frozen routes). **Deliverable:** incremental, read-only DEV QA harness and source gates. **Production:** BLOCKED until independent HTTP/MariaDB, multi-company, fixture, baseline and performance signoff. **No runtime authorization or schema change.**

## 1. Why this gate exists

M8.5 through M8.6E moved Product, Catalog, Organization, People and polymorphic Contact list reads into SQL PBAC before `COUNT` and `LIMIT/OFFSET`. M8.6D removed the legacy/shadow and `enabled`/`disabled` runtime switches. Therefore a source-only PASS cannot establish correct permissions or MariaDB performance. M8.7 is an *auditor*, not another SQL migration.

The following nine table-backed collections are audited, using the *production SQL plan builder* for each resource: `product`, `product_group`, `product_uom`, `organization`, `employee_assignment`, `employee`, `department`, `position`, `contact`. `Contact` is checked through both independent `organization_contact` and `employee_contact` policy scopes. Pricing uses its older SQL pushdown and is **not** claimed migrated/verified by this harness; Access/Identity/Workflow remain in-memory.

## 2. Safety and prerequisites

1. Use a **dedicated DEV MariaDB clone**, never a production or independently pinned baseline database. Back up the DEV database first; do not modify fixture tables unless a separate, explicitly authorized setup process has been approved.
2. Set CodeIgniter `CI_ENVIRONMENT=development` or `testing`. The CLI refuses `production`. The database name must match the operator's exact expected name and must not use obvious `prod`/`live` naming. Both the environment gate **and** exact name attestation are mandatory; neither alone proves the database is disposable.
3. Supply **two different existing, active Shield users** (e.g. Actor A and Actor B) with distinct organization grants and policy outcomes. The tool does not manufacture actors, policies or Employee assignments.
4. Use MariaDB credentials restricted to `SELECT` (plus `EXPLAIN`) if possible. The SQL command executes `SET TRANSACTION ISOLATION LEVEL REPEATABLE READ`, `SET TRANSACTION READ ONLY`, starts one transaction, and explicitly **rolls back even after success**. No `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP` or business HTTP mutation is executed.
5. Stop concurrent business writes during DB and HTTP parity comparison; HTTP requests have separate transactions and therefore cannot share the CLI's MVCC snapshot. A fresh report is accepted by the HTTP auditor only within **15 minutes**, but even a fresh report is invalidated by a concurrent business change.
6. For complete parity comparison, each audited table must contain **at most `MAX_ROWS` rows** (default 1000, maximum 5000). An oversized table is `INCONCLUSIVE_OVERSIZE`, **never** a sampling PASS. 512-Organization/slot caps in M8.6B/M8.6E are not bypassed: compilation overflow must fail closed.

## 3. Stage A — Source gates (NO database)

From `D:\XAMPP\htdocs\erp`:

```powershell
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m87_plan_pure.php
php tests\m87_sql_contract_pure.php
php tests\m87_http_safety_pure.php
php tools\m87_accept.php
```

These gates prove structure and offline fixture behavior **only**. They cannot prove SQL and canonical PBAC are equivalent on the local database.

## 4. Stage B — MariaDB parity and plan audit (DEV, read-only)

From `D:\XAMPP\htdocs\erp\backend`:

```powershell
php spark m87:db-audit ACTOR_A_ID ACTOR_B_ID EXPECTED_DEV_DATABASE DEV_READ_ONLY 1000
```

Substitute numeric Shield actor IDs and the exact database name returned by your local DB config; `1000` may be raised only as high as `5000`. The CLI refuses duplicate actors, a wrong DB name, non-DEV environment or missing confirmation. It writes a report under `backend/writable/m87/reports/m87_YYYYMMDD_HHMMSS_<nonce>.json` with private-file permissions where supported.

The audit uses one repeatable-read, database-enforced READ ONLY transaction for **both actors**. For each resource it:

- Counts the full source table first and refuses to pretend to exhaust an oversized dataset.
- Fetches the bounded full source rows **only for the audit** and computes the independent canonical oracle with `UnifiedAccessService::canView`. Invalid Contact owners and non-unique effective primary assignments are classified as hidden, not authorized.
- Compiles the same production SQL scope (`AuthorizationSqlScopeCompiler`, resource provider, real list/detail SQL-builder), runs scoped `COUNT`, all SQL pages in ID order, and bounded detail checks for visible/hidden records.
- Asserts identical authorized `COUNT`, exact ordered IDs, and correct detail visibility. A mismatch fails the entire resource. There is **no post-`COUNT` trimming** to force a PASS.
- Exercises alternative sorts, multi-page boundaries, State filter, and a real row-derived search string. When Unicode is present, it prioritizes a Unicode test term and compares result IDs/total against `AuthorizedListPage` as an independent PHP oracle. If no Unicode sample exists, the report explicitly shows `unicode_case_tested=false`.
- Collects three observed SQL COUNT and page timings (p50/p95/max in milliseconds) plus redacted `EXPLAIN FORMAT=JSON` summaries (`table`, access method, index, estimated rows, SHA-256 of full plan). **Three samples are diagnostic, not a stable production p95.**

The report **does not** save names, contacts, emails, raw SQL, token credentials, row IDs, PBAC bind values or raw policies. It records aggregate counts, mismatches and SHA-256 of ID sequences. `PASS_PARITY` is per-resource, **not a production acceptance verdict**. The DB phase reports `PASS_DEV_SQL_PARITY` only if all resource comparisons have real positive/negative/Unicode coverage for both actors **and** the case manifest supplies at least one allow and one deny case for `organization`, `employee_assignment` and `contact` for each actor; otherwise it reports `INCONCLUSIVE_DB_FIXTURE_COVERAGE`. `INCONCLUSIVE_EMPTY`, `INCONCLUSIVE_OVERSIZE` and `INCONCLUSIVE_NO_BASE_PERMISSION` mean no parity proof for that resource.

### Optional explicit scenario case file

A read-only expectation file (not generated by the harness) lets the operator label known record IDs and require both the canonical oracle and scoped SQL to honor `allow`/`deny` for each actor. Use a local file outside the repository or under a DEV-only working directory:

```json
{
  "actors": {
    "3": {
      "organization": {"allow": ["101"], "deny": ["102"]},
      "employee_assignment": {"allow": ["201"], "deny": ["202"]},
      "contact": {"allow": ["301"], "deny": ["302"]}
    },
    "5": {
      "organization": {"allow": ["102"], "deny": ["101"]},
      "employee_assignment": {"allow": ["202"], "deny": ["201"]},
      "contact": {"allow": ["302"], "deny": ["301"]}
    }
  }
}
```

**These are illustrative IDs only.** Replace actor IDs and every record ID with known DEV fixtures; do not run the example unchanged. If provided, invoke:

```powershell
php spark m87:db-audit 3 5 EXPECTED_DEV_DATABASE DEV_READ_ONLY 1000 D:\dev-qa\m87-cases.json
```

Cases pointing to missing rows or disagreeing with either canonical PBAC or SQL are failures. Cases alone do **not** prove that DENY/ABSOLUTE_DENY precedence is exercised; the operator must attest the underlying persisted policies/assignments.

## 5. Stage C — Authenticated HTTP parity (localhost only)

Use two distinct **DEV accounts that are the exact Shield actors audited in Stage B**. Keep credentials out of the shell command line and out of source-controlled `.env`/reports; supply as ephemeral PowerShell process environment variables:

```powershell
$env:M87_DEV_CONSENT = 'DEV_HTTP_READ_ONLY'
$env:M87_DEV_HOST = 'api.monmin.net'    # or 'localhost' (fixed allowlist)
$env:M87_DEV_SCHEME = 'https'           # or 'http' for local Apache
$env:M87_ACTOR_A_USER = 'DEV_A_LOGIN'
$env:M87_ACTOR_A_PASSWORD = 'DEV_A_PASSWORD'
$env:M87_ACTOR_B_USER = 'DEV_B_LOGIN'
$env:M87_ACTOR_B_PASSWORD = 'DEV_B_PASSWORD'

php tools\m87_http_readonly.php writable\m87\reports\m87_YYYYMMDD_HHMMSS_NONCE.json D:\dev-qa\m87-cases.json

Remove-Item Env:\M87_ACTOR_A_PASSWORD, Env:\M87_ACTOR_B_PASSWORD
```

The script connects **only** to `127.0.0.1` port 443 (`https`) or 80 (`http`), with the reviewed Apache `Host` header; self-signed TLS verification bypass is restricted to the loopback connection. It never contacts an arbitrary remote URL. It performs GET `/auth/csrf`, POST `/auth/login` **only for authentication**, GET `/auth/me` to attest exact user ID, then GET list pages of the nine resources and optional GET detail for explicit fixture IDs. No business POST/PATCH/DELETE is allowed.

It compares authenticated HTTP `meta.total`, page/size, and the entire ordered ID digest to the recent DB audit result. It tests positive fixture details return 200 and hidden existing fixture details return 404. A timing gap or concurrent writes can cause different snapshots; such a result must be investigated rather than relabeled PASS. HTTP results are written to `backend/writable/m87/reports/m87_http_*.json` without credentials or row data.

**HTTP cannot be marked fully accepted by a source-only run.** The tool's top-level result remains `INCONCLUSIVE_BASELINE_AND_SCENARIO_GATES` even when all executed HTTP comparisons match.

## 6. Required manual MariaDB / security signoff matrix

| Test lane | Required verified evidence | Gate when missing |
|---|---|---|
| Actor A vs B | Same State, different Company grants/denials, swaps, no leaks across page, detail, `total` | INCONCLUSIVE |
| Policy precedence | Persisted ALLOW, DENY, ABSOLUTE_DENY, inactive policy, user/group/assignment scope under both actors | INCONCLUSIVE |
| Product/Catalog | `MASTER` GROUP scope, State changes, authorized child with invisible parent, search/Unicode/sort/NULL | INCONCLUSIVE |
| Organization/People | `organization.id` vs `assignment.organization_id`, ancestor/descendant, independent Employee filter `EXISTS`, UTC `effective_at` | INCONCLUSIVE |
| Contact | Organization vs Employee resource grants *independent*; no/one/two primary, invalid owner, future/expired/inactive, UTC boundary | INCONCLUSIVE |
| Count/pagination | No skipped/duplicate/unauthorized ID, correct total and stable sort, PAGE 1/2, 50/100/200, Unicode/NULL | INCONCLUSIVE |
| Concurrency | Controlled tests of concurrent assignment, policy and State changes; stable MVCC snapshot & no partial leakage | INCONCLUSIVE |
| Performance | MariaDB version, dataset sizes, EXPLAIN indexes/rows examined, CPU/latency/memory, compare approved baseline P95 | INCONCLUSIVE |
| Baseline / existing APIs | Authenticated independent baseline comparison (when semantic change intentionally security-hardens, record approved deviation) | INCONCLUSIVE |
| Deployment | 150/150 routes, no writes, DB backup, rollback by code revert, reviewer signoff | BLOCKED |

**Automatic tool limitations:** The CLI exercises *existing* data; it does not create the missing scenarios. The 512 Organization/State cap can cause fail-closed on large DEV datasets and is not proof of scalability. Timing samples and EXPLAIN are diagnostics; choose explicit acceptance thresholds after measuring real row counts. For Contact, concurrency proof requires a separate controlled two-session test on a disposable DEV clone; M8.7 intentionally does not mutate assignments. No production approval is conferred by either M8.7 command.

## 7. Verdict policy

- `FAIL_DEV_SQL_PARITY` or `FAIL_DEV_HTTP_PARITY`: STOP and correct source/query/fixture; no runtime approval.
- `BLOCKED_ENV_OR_RUNTIME` / `BLOCKED_ENV_OR_HTTP`: inspect configuration and selected database; no assumption of correctness.
- `PASS_DEV_SQL_PARITY`: the bounded database comparison and minimum fixture coverage passed; **HTTP, baseline, concurrency and production remain BLOCKED**.
- `INCONCLUSIVE_*`: executed checks may have matched, but critical real-data, HTTP, security or baseline evidence is still missing. **Never** relabel as production PASS.
- `SOURCE PASS`: only the incremental QA code, static invariants and offline regressions are accepted.

There is **no feature toggle** to roll back M8.6D/E. Reverting source in a controlled deployment, then re-running the frozen API/source gates, is the rollback mechanism. The QA harness itself can be removed independently without modifying runtime read code.
