# M19.25 — Offline Regression Repair, Workflow Composition Boundary, DEV SQL EXPLAIN

**Date:** 2026-10-29
**Starting source:** `erp_v2.0.0_m1924_fixed.zip` (M19.24 Windows hotfix included)
**Outcome:** Offline regression + static architecture **PASS**; SQL EXPLAIN prepared, **not executed against DEV database**. HTTP/UAT deliberately excluded (operator validates through frontend).
**Patch scope:** No public HTTP methods/paths/handlers, table schema, migration, OpenAPI, frontend, PBAC evaluation rule, notification feature flag value or frozen baseline fixture was changed.

## A. Six earlier UNIT-STANDARD failures, resolution and evidence

| Existing failure | Root cause seen in starting ZIP | Repair and safeguard |
|---|---|---|
| `m322_baseline_fidelity_pure.php` | M11 added EN/VI `NotificationRuntime.php` outside the older four-file overlay allowlist | Allow only this fifth *runtime overlay*; keep 74 v1.2.4 byte-level SHA pins unchanged. |
| `m34b1_launcher_pure.php` | Wrapper now also supports intentional `PREPARE M11`, but assertion expected exactly `CHECK/RUN` | Explicitly require the current three actions and guard that `PREPARE` is M11-only; no implicit DB operation added. |
| `m34c_owner_reads_fixture.php` | Standalone fixture lacked M10.2 `EffectiveRowActionHints` class; mocked access service lacked `decideMutation` | Load real hint registry and add fixture-only mock PBAC mutation decision; assert generated actions. No production PBAC altered. |
| `m34d_architecture_pure.php` | Real Workflow–Notifications–Customer import cycle; historical module file counts; stale native/All States counts | Remove direct module back-edges through a typed application port and outer composition root. Pin 33 Access / 16 Identity / 13 Workflow files; 8 native + 5 All States. Keep cycle detector and explicit negative test. |
| `m3_pure.php` | Newly registered `customer_interaction` is missing from historical 12-type expectation | Pin all 13 current independent types; explicitly verify Customer Interaction remains All States, not Native or Lifecycle. |
| `m8_contract_pure.php` | Failed Import terminal is now written transactionally via `finalizeTerminal`, not as a literal `['status'=>'FAILED']` | Pin failed-terminal invocation, `FOR UPDATE`, transaction, rollback and outbox in the *existing* implementation; no import job behavior changed. |

The restored **MODULE_GRAPH** dependency direction is:

- `Customer → Notifications → Workflow → Access` (and other existing leaf dependencies such as Contact/Numbering).
- Workflow references **only** `App\Shared\Workflow\WorkflowTransitionPort` for Customer mutation / notification integration.
- The concrete `ProductionWorkflowTransitionPort` lives under `App\Shared\Workflow`; only `Config\Services` (composition root) selects this adapter. A direct module import back to Notifications would still fail `M34DAcceptance::assertAcyclic()`.

The adapter calls the canonical `CustomerService::setState()` and `NotificationOutboxProducer::enqueueWorkflowTransition()` synchronously with the *same actor and database connection*. No exception is swallowed. Customer still owns its existing transaction/lock, Company Usage invariants and authorization; Workflow retains locked row CAS and its PBAC. The Feature Flag check is unchanged but now resolved via the port. The original two-argument `BusinessWorkflowService` construction still works; optional third argument allows a test injection.

## B. Added regression/safety tests

- `backend/tests/m1925_workflow_port_pure.php`: 12 positive/negative checks for delegation, same DB+actor, feature flag OFF/ON, outbox failure bubbling, module import boundary, composition root.
- `backend/tests/m1925_sql_explain_safety_pure.php`: 9 source safety checks for explicit DEV clone consent, the read-only `m87:db-audit` command, no HTTP, fresh report identification and sanitized output.
- `m34d_architecture_pure.php`: 21/21 checks (previously 18), including an explicit synthetic *Workflow → Notifications* back-edge rejection.

## C. Offline verification in the supplied source

| Gate | Actual result |
|---|---|
| `php backend/tools/test-unit-check.php` | PASS, 167 pure/fixture scripts inventoried |
| `php backend/tools/m34b_run.php --offline` | **167/167 PASS** (previous source: 159/165) |
| `php backend/tools/m34d_accept.php --offline` | **6/6** static gates PASS; runtime still NOT_SUPPLIED |
| `php backend/tests/m34d_architecture_pure.php` | **21/21 PASS** |
| `php backend/tests/m1925_workflow_port_pure.php` | **12/12 PASS** |
| `php backend/tests/m1925_sql_explain_safety_pure.php` | **9/9 PASS** |
| `python backend/tools/verify_routes.py` | 150/150 P1; 180/180 frozen routes PASS |
| `php backend/tools/routes/route_inventory.php --verify` | 180/180 PASS |
| `php backend/tests/m1924_route_architecture_pure.php` | **470/470 PASS** |
| Existing P1-STANDARDS source tests and CUS-UNIT pure tests | All listed checks PASS |
| `backend/tests/m11/*_pure.php` (16 M11 smoke suites) | All 16 PASS |
| M4–M8.7A source-only acceptance entrypoints | All exit 0, but many correctly retain *runtime/production BLOCKED* status |

`backend/vendor` and `pwsh` are not bundled, so real CI4 `php spark routes`, Windows PowerShell and M11 PHPUnit MariaDB integration could not be executed in this offline Linux container. No HTTP request or database connection was attempted.

## D. DEV SQL-only EXPLAIN — optional operator execution

The existing M8.7 SQL verifier already performs independent PBAC-vs-SQL checks, `EXPLAIN FORMAT=JSON`, scoped COUNT/page timings and read-only transaction rollback. M19.25 adds a **SQL-only** PowerShell wrapper and a sanitized EXPLAIN report viewer. It does **not** call the original M8.7A HTTP phase.

From `D:\XAMPP\htdocs\erp\backend` on the approved **DEV clone** (replace actor IDs/database if different):

```powershell
.\tools\m1925_sql_explain_dev.ps1 `
  -ActorA '1' `
  -ActorB '2' `
  -Database 'erp' `
  -DevConfirmation DEV_CLONE_ONLY `
  -MaxRows 2000
```

Safety: confirms two distinct actors and exact database name; the underlying CI4 command refuses production `CI_ENVIRONMENT`, checks `SELECT DATABASE()`, opens `SET TRANSACTION READ ONLY` and rolls back. It writes a sanitized local JSON report under `backend/writable/m87/reports`; no HTTP login or password is requested. An optional `-CasesFile` can supply the existing M8.7 allow/deny scenario matrix. Database name `erp` is acceptable **only** if the operator knows it is their development clone, never production.

The viewer prints `actor/resource`, COUNT/page table access, index and estimated row counts; it suppresses SQL text, bind values and business row data. A synthetic valid-report test passed, and a path outside the reports directory was rejected.

**Interpretation:** `INCONCLUSIVE_DB_FIXTURE_COVERAGE` does NOT mean SQL parity is accepted: both allowed and denied rows, Unicode/sort cases, two actors and the M8.7 required scenario matrix must be present. `EXPLAIN` alone does not prove workload latency or absence of regression; compare against a representative DEV baseline, actual table cardinality and p95 timings. Review significant unindexed `ALL` scans before approving performance.

## E. Windows application & regression commands

At repository root, apply **either** the patch **or** the full ZIP (not both):

```powershell
git apply --check .\DinkERP_M1925_Offline_Regression_Architecture_SQL.patch
git apply .\DinkERP_M1925_Offline_Regression_Architecture_SQL.patch
git diff --check
cd .\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN ROUTES
python .\tools\verify_routes.py
.\tools\test-unit.ps1 RUN P1-STANDARDS
.\tools\test-unit.ps1 RUN CUS-UNIT
php .\tests\m1925_workflow_port_pure.php
php .\tests\m1925_sql_explain_safety_pure.php
```

**Stop** if `git apply --check` fails; never use a forced patch on a diverged codebase. Do not run `RUN ALL` or `RUN INTEGRATION` as substitutes for offline tests: those have DB-side effects or connection requirements. Do not promote this *offline* PASS to production acceptance.

## F. Remaining acceptance limits

1. **DEV SQL:** real actor/scenario coverage, query plans and performance verdict require the operator's DEV clone. No result was manufactured for missing DB access.
2. **HTTP/frontend UAT:** intentionally delegated to the operator; verify Workflow Customer state changes and Notifications with producer OFF/ON, action authorization, rollback upon outbox rejection and browser-visible results. No HTTP smoke was run here.
3. **M11 integration:** SQL transactions, notification outbox delivery and real CI4 service autoload remain to be verified on the operator's environment (the 16 offline M11 pure suites are insufficient).
4. **Production cutover:** remains BLOCKED under M3.4D baseline-parity/concurrency/authorization gates and the individual M4–M8 static acceptance reports.

**Delivery classification:** `M19.25 OFFLINE DONE / DEV DB + FRONTEND UAT NEEDS_VERIFY`; no production rollout authorization.
