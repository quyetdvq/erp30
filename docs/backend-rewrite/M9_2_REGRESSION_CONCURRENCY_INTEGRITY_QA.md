# M9.2 – Regression, Concurrency & Data Integrity

**Base:** `erp_v1.5.0.zip` + accepted M9.1 Employee performance patch + M9.1 E2E correction.
**M9.0:** NOT APPLIED; not a dependency.
**Delivery:** M9.2A read-only Product Import journal audit + offline negative/fixture tests.
**Status:** **PARTIAL / NEEDS DEV EVIDENCE**. Do not claim M9.2 concurrency or production readiness from source-only tests.

## 1. Scope / exclusions

- No changes to `GET /employees`, M9.1 UI, PBAC policy semantics, Import runtime, database schema, migrations, frozen OpenAPI, or the existing 150 operations.
- The new CLI command, `m92:integrity-audit`, checks Product Import Job (`import_jobs`) versus row journal (`import_job_rows`), enforcing counters, terminal status, row-number uniqueness, status/payload consistency, and absence of orphan journal rows.
- Output reports contain **aggregates and fixed diagnostic codes only**. They exclude source filenames, row keys, target IDs, user identities, raw SQL, credentials and exception messages.
- The command is SELECT-only inside an InnoDB `REPEATABLE READ` **read-only consistent transaction** with a `ROLLBACK` in all exit paths. The report is written under `backend/writable/m92/reports/` (outside public). Do not point this command at production; an exact DB name is not proof a DB is a clone.
- There is **no mutation/fault-injection/concurrency** action in this patch; all potentially mutating DEV acceptance remains a separately authorized gate.

## 2. What the journal audit verifies

A snapshot checks all `entity_type=PRODUCT` import jobs and their rows within explicit bounds. The minimum invariants are:

| Class | Check | Outcome |
|---|---|---|
| Schema | Both journal tables exist and are InnoDB | Otherwise BLOCKED |
| Snapshot | Connected DB exactly matches confirmation, environment development/testing, `@@tx_isolation=REPEATABLE-READ` | Otherwise BLOCKED |
| Scope | At most 5,000 jobs and 100,000 rows, configurable lower | Otherwise INCONCLUSIVE |
| Journal | Row numbers are unique and start after header; `SUCCESS/ERROR/SKIPPED` are case-sensitive; per-row payload semantics are valid | Violation FAIL |
| Terminal | `COMPLETED` and `COMPLETED_WITH_ERRORS` have all rows and matching counters; no SKIPPED; status consistent with error count | Violation FAIL |
| Parent | No orphan `import_job_rows` records | Violation FAIL |
| Recovery | `FAILED`, `PENDING` or `PROCESSING` jobs require investigation/observation; partial FAILED journal is **not auto-retried** | INCONCLUSIVE, unless a structural violation also exists |
| Coverage | At least one fully terminal Product Import job | Otherwise INCONCLUSIVE |

**Limits:** The journal does not persist row `record_type`, so this cannot prove a `target_ref_id` belongs to the correct underlying Product/UOM/COMBO table. It also does not prove a committed business mutation agrees with the journal or that an import is idempotent. Those need actual authorized DEV integration checks and manual evidence. This tool does not replace authorization/race acceptance or source-code UAT.

## 3. Run on Windows XAMPP

From repo root, *after applying the M9.2 patch*:

```powershell
cd D:\XAMPP\htdocs\erp
php .\backend\tests\m92_integrity_pure.php
php .\backend\tests\m92_snapshot_fixture.php
php .\backend\tests\m8_sql_scope_pure.php
php .\backend\tests\m8_contract_pure.php
php .\backend\tests\m86c_sql_pure.php
php .\backend\tests\m86e_contact_sql_pure.php
php .\backend\tests\m91_employee_list_id_filter_pure.php
python .\backend\tools\verify_routes.py

# Review the running CI_ENVIRONMENT, database selection, and a backup/clone
# BEFORE invoking the read-only SQL audit. Replace `erp` if appropriate.
cd .\backend
php spark m92:integrity-audit erp DEV_CLONE_ONLY DEV_READ_ONLY 1000 50000
```

The final command **requires the connected database to be an explicitly confirmed DEV clone**. It never changes database rows. For a larger DEV fixture, raise caps deliberately (at most 5,000 / 100,000). If above caps, the script stops at `INCONCLUSIVE_AUDIT_BOUND`; it does not silently sample and claim success.

The CLI report path looks like `backend/writable/m92/reports/m92_YYYYMMDD_HHMMSS_xxxxxxxx.json`. `PASS_DEV_IMPORT_JOURNAL_SNAPSHOT` means **journal structural invariants only**. `INCONCLUSIVE_IMPORT_RECOVERY_OR_FIXTURE` or `INCONCLUSIVE_AUDIT_BOUND` means evidence is incomplete; `FAIL_DEV_IMPORT_JOURNAL_INTEGRITY` means an integrity invariant was violated. Any status other than PASS returns a nonzero exit code after writing the sanitized report. Do not delete/edit journal rows to force PASS.

## 4. M9.2B – DEV two-writer and failure-recovery acceptance (not executed by this patch)

Use a **physically isolated, backed-up disposable DB** such as `erp_test`, never live production or a valuable shared `erp` instance. Cross-check Apache's actual DB and permission context before enabling write mode. Do not run `--apply` solely because code tests passed.

| Gate | Required DEV evidence | Status until observed |
|---|---|---|
| Two independent Product writers | Two authenticated HTTP workers, unique numbered Product codes, committed row read-back and counter delta | NOT_TESTED |
| Rollback after invalid COMBO | Rejected COMBO mutation leaves **both** Product and counter unchanged | NOT_TESTED |
| Concurrent Product Import same key | Separate job journals, no unexpected duplicate Product, deterministic create-only rejection and PBAC | NOT_TESTED |
| Journal storage fault | On a sacrificial fixture, compare committed Product, journal and counters; record uncertain jobs for manual recovery; **do not auto-retry** | NOT_TESTED |
| PBAC negative/race | Recheck denied actor, revoked assignment during batch, expected no-leak total/details | NOT_TESTED |
| Baseline & release | Real HTTP comparison, backup/restore rehearsal and go/no-go | NOT_TESTED |

Already available source-only safety checks:

```powershell
php .\backend\tests\m62b4_atomicity_safety_pure.php
php .\backend\tests\m63c_avatar_race_safety_pure.php
```

An existing controlled two-writer runner (`backend/tools/m62b4_dev_atomicity.php`) can be **inspected in PLAN ONLY mode**, with existing, disposable GROUP/UOM IDs. Its `--apply` mode intentionally persists test Products and demands explicit authorization; it is **not** run or altered by this patch. Any Avatar CAS historical waiver remains separate, not an M9.2 PASS.

Before closing M9.2: attach sanitized report/evidence from the read-only journal audit and the authorized two-writer/fault tests; identify any manual recovery items. Record separately if a gate is formally waived. M8.7 functional acceptance and M9.1 PASS remain accepted and are not retroactively reopened.
