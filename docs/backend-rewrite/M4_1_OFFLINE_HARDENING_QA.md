# M4.1 — Numbering/Workflow source-parity hardening (2026-10-24)

**Starting point:** the supplied `erp_v1.3.0.zip` (M4 Settings, Numbering and Workflow implementation; 84 of 150 frozen baseline operations). This is an incremental patch, **not** a new API milestone and **not** M4 HTTP/SQL approval.

## Corrections verified against the included v1.2.4 baseline source

1. **Numbering canonical collision semantics:** `auto_numbering_series` now compares active pattern **structural signatures** instead of only byte-for-byte pattern strings and matching scope types. For example, `INV-{YYYY}-{SEQ:3}` and `INV-{YYYY}-{SEQ:5}` are a collision, as in the baseline canonical pattern parser. An inactive series is excluded. The baseline's explicitly rejected SQL `SELECT ...` literal is now rejected by the v2 parser.
2. **Workflow fail-closed dispatch:** selecting a configured action now requires *exactly one* valid transition from the authoritative persisted state; an absent or duplicate action refuses execution with `WF_INVALID_TRANSITION`, not the first SQL result. The options endpoint suppresses duplicate configured action codes. No client-selected destination is accepted.
3. **PBAC transition action syntax:** the Access `RuntimePolicyReader` now accepts registered action codes containing `:` or `-`, which the frozen OpenAPI explicitly allows. This does **not** grant new permissions: the resource action must still be registered and pass Base Permission + PBAC/`ABSOLUTE_DENY` checks.

The patch changes no public route, success-status contract, migration, Frontend or original Backend. M3.4D's pinned 70-operation footprint stays unchanged; M4 retains exactly 14 additional routes (84/150).

## Pure/offline evidence in the authoring environment

```text
php backend/tools/test-unit-check.php               PASS: 54/54 suites inventoried
php backend/tools/m34b_run.php --offline             PASS: 54/54 suites
php backend/tools/m34d_accept.php --offline          PASS: 6/6 M3.4D static gates
php backend/tools/m4_accept.php                      PASS: 84/150 route inventory
php backend/tests/m4_baseline_pattern_parity_pure.php PASS: 18 differential assertions
php backend/tests/m4_workflow_rules_pure.php         PASS: 28 assertions
php backend/tests/m4_security_pure.php               PASS: 21 static assertions
git diff --check                                      PASS
```

The new offline differential suite loads the frozen, **unmodified** v1.2.4 PHP numbering parser directly as a reference. It does not execute SQL or send HTTP requests. Sample parity tests do **not** prove complete behavior parity.

## Owner's XAMPP verification

Apply **only this Git patch** from `D:\XAMPP\htdocs\erp` after backing up local changes:

```powershell
git apply --check .\M4.1-numbering-workflow-hardening.patch
git apply .\M4.1-numbering-workflow-hardening.patch
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Do not proceed to HTTP writes without a disposable MariaDB clone, separate test accounts, a confirmed session+CSRF lifecycle and a baseline-v1.2.4 comparison. `RUN DB-READ` and `RUN INTEGRATION` continue to cover **M3**, not the newly added M4 mutation/rollback/concurrency paths. No migration or production media/storage change is included.

## Explicit remaining gates

- Independent 14-route HTTP contract/error/localization parity and PBAC negative probes.
- Numbering: verify resource reassignment behavior, identifier-adapter maximum length/uniqueness metadata, cross-session default/pattern collision, counter-issued immutability, and two-writer concurrency against baseline and MariaDB.
- Settings: resolver hierarchy, typed overrides and immutable configuration audit event under transaction/rollback.
- Workflow: 12 supported business adapters, authentic status row transitions, `ABSOLUTE_DENY`, transaction rollback and concurrent actions.
- Production: **BLOCKED** (`DINKERP_M33B_WRITE_MODE=off`). Do not enable it based on offline gates.

## Known legacy verification commands outside M4 gate

The old `python backend/tools/verify_routes.py`, `verify_baseline.py` and `verify_layout.py`
commands in the root README **do not pass on the supplied v1.3.0 ZIP before this
patch**: the routes checker asserts an obsolete route count, the baseline checker
requires a missing `frontend-fingerprint-v1.2.4.json`, and the layout checker
expects `backend_basline/` while the archive actually ships `backend_baseline/`.
These pre-existing M0/M1 tool/packaging mismatches are **not** counted as PASS
or used to claim M4 release readiness. Use the pinned M3.4D/M4 suite for M4
source acceptance; reconcile the old scripts as a separate documented task.
