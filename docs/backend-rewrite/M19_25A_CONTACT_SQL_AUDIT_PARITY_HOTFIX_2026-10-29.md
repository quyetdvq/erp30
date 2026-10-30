# M19.25A — DEV Contact SQL/PBAC Audit Fidelity Hotfix

**Date:** 2026-10-29

**Base archive:** `erp_v2.0.0_m1925_fixed.zip` (includes M19.24 and M19.25)

**Scope:** M8.7 DEV read-only verifier and offline regression gates. No HTTP/UAT is performed here.

## 1. Observed DEV evidence

User-supplied report: `m87_20261029_035426_86c3ca0d.json` from MariaDB `10.4.32-MariaDB`.

- Report status: `FAIL_DEV_SQL_PARITY`; failed entries: `1:contact:FAIL_PARITY` and `2:contact:FAIL_PARITY` only.
- For both actors, Contact `source_rows=9`, `oracle_visible=9`, `oracle_hidden=0`, `scoped_total=7`, `expected_count=9`, `actual_count=7`, and `query_scenarios.failures=2/4`.
- Other eight resources per actor passed (`16/18` actor/resource entries overall).
- Contacts count EXPLAIN enumerated organizations and assignments, but not the Customer parent table (`cu`).
- No independent allow/deny fixture manifest was supplied; `coverage_gaps` was already nonempty.

The redacted JSON does not name the two omitted Contact IDs or their owner types. Their identification requires local DB inspection; it is not invented in this report.

## 2. Confirmed root cause in source

`App\Modules\Contact\ContactService::list()` and `::show()` invoke `ContactSql::collection()` / `::detail()` with the two Organization/Employee scopes **and** both MASTER scopes obtained from `customerScopes()`:

1. `customer_contact` lifecycle VIEW.
2. `customer` parent lifecycle VIEW.

`M87SqlVerifier::plans()` previously passed **only** the Organization and Employee scopes. In `ContactSql::ownerPredicate()`, absent Customer scopes force the Customer branch to `1=0` (fail-closed), so the audit SQL can omit contacts visible to the canonical oracle and to the production API.

A second audit-only fidelity gap existed in `M87SqlVerifier::contactOracle()`: it checked the Contact's PBAC VIEW, but did **not** check the owning Customer's VIEW. `ContactScope::resolve()` checks that the Customer exists; it does not authorize viewing that parent. The production API independently checks the parent. The verifier must do the same to avoid counting unauthorized Customer contacts as visible.

## 3. Code changes

- `backend/tools/m87/M87SqlVerifier.php`: compile both canonical Customer MASTER scopes from the **same** `UnifiedAccessService` actor snapshot; pass them to both list and detail SQL plans. For CUSTOMER-owned contacts, require the independently fetched persisted Customer parent and canonical `canView('customer', $parent)` in addition to `canView('customer_contact', $contact)`. All existing error handling remains fail-closed.
- `backend/tests/m1925a_contact_audit_pure.php`: new offline 25-assertion regression suite, including pre-fix two-scope reproduction, four-scope SQL shape, parent existence and lifecycle gates, bind counts, denied Customer/Contact states, wrong-scope rejection, source call-site parity, and missing-scope mutation guard.
- `backend/tools/test-unit-check.php`: pin new pure suite in offline inventory.
- `backend/tools/test-unit.ps1`: run the new regression in `RUN P1-STANDARDS` as well as `UNIT-STANDARD`.

**Not changed:** `ContactService`, `ContactSql`, business permission evaluation rules, database schema, HTTP/controller behavior, customer rows, routes, legacy frozen API contracts or frontend.

## 4. Verified offline results

| Gate | Outcome |
|---|---|
| `php backend/tools/test-unit-check.php` | 168 pure/fixture suites inventoried |
| `php backend/tools/m34b_run.php --offline` | **168/168 PASS** |
| `php backend/tests/m1925a_contact_audit_pure.php` | **25/25 PASS** |
| `php backend/tests/m87_sql_contract_pure.php` | **46/46 PASS** |
| `php backend/tests/m86e_contact_sql_pure.php` | **61/61 PASS** |
| `php backend/tests/m1925_sql_explain_safety_pure.php` | **9/9 PASS** |
| `php backend/tools/m34d_accept.php --offline` | **6/6 PASS** |
| `php backend/tests/m1924_route_architecture_pure.php` | **470/470 PASS** |
| `php backend/tools/routes/route_inventory.php --verify` | **180/180 PASS** |
| `python backend/tools/verify_routes.py` | **150/150 P1; 180/180 frozen PASS** |
| Existing P1 standards and Customer offline tests | All included scripts PASS |

CI4/MariaDB DEV execution and HTTP/frontend UAT were **not** performed in this workspace. Offline correctness does not imply a runtime PASS.

## 5. Windows application and DEV rerun

Apply the **patch only** to a working tree containing M19.24 + M19.25 (or use the full M19.25A ZIP instead, not both).

```powershell
cd D:\XAMPP\htdocs\erp
git apply --check .\DinkERP_M1925A_Contact_Audit_Parity_Hotfix.patch
git apply .\DinkERP_M1925A_Contact_Audit_Parity_Hotfix.patch
git diff --check
cd .\backend
.\tools\test-unit.ps1 RUN UNIT-STANDARD
.\tools\test-unit.ps1 RUN P1-STANDARDS
.\tools\test-unit.ps1 RUN ROUTES
.\tools\test-unit.ps1 RUN CUS-UNIT
```

On the **explicitly operator-approved DEV clone only**, to rerun the same read-only audit:

```powershell
.\tools\m1925_sql_explain_dev.ps1 `
    -ActorA '1' `
    -ActorB '2' `
    -Database 'erp' `
    -DevConfirmation DEV_CLONE_ONLY `
    -MaxRows 2000
```

Read the newly created `backend/writable/m87/reports/m87_*.json` (not the older report) and inspect **both** actors under `data.*.contact`: `status`, `oracle_visible`, `oracle_hidden`, `scoped_total`, `comparison.match` and `query_scenarios.failures`.

With the supplied fixture and only if no other mismatch exists, a Contact `PASS_PARITY` is expected, **not guaranteed**. The overall audit can legitimately return `INCONCLUSIVE_DB_FIXTURE_COVERAGE`, because the uploaded report has `oracle_hidden=0` for every actor/resource, there is no independent allow/deny case manifest, and some Unicode scenarios are not covered. Do not label `INCONCLUSIVE` as full DEV acceptance; dedicated deny/allow fixture coverage, representative plan inspection and DEV benchmarks remain outstanding.

**Acceptance:** M19.25A offline hotfix complete; M8.7 DEV runtime **requires rerun**. HTTP/browser UAT stays with the operator.
