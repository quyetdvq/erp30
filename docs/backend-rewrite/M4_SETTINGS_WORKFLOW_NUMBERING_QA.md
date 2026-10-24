# M4 — Settings, Workflow Execution, Resource Numbering Configuration

**Basis:** DinkERP v2 M3.4D.1, original baseline v1.2.4 SHA-pinned OpenAPI manifest (150 operations).
**Status:** implementation and offline contract/security regressions PASS; M4 HTTP/MariaDB integration and independent baseline-live parity **NOT YET TESTED**.

## Scope and module ownership

| Owner | Operations | Route family | Scope |
|---|---:|---|---|
| Settings | 5 | `display-settings/*` | effective presentation options, definitions, context values, atomic set/reset and audit |
| Numbering | 6 | `resources/{resource_id}/numbering-series/*` | read, create, patch, set state, dry-run pattern validation; **not number issuance** |
| Workflow | 3 | `business-workflow/{resource}/*` | allowed states, transition options, transactional state action |

Exactly **14** new methods/paths/status references from the signed baseline contract. The M3.4D.1 70-route SHA-256 footprint is maintained. 84/150 routes implemented, 29 older mutations plus 7 new M4 write operations (Settings 2, Numbering 3, Workflow 1; Numbering validate-config is a read-only POST, so total actual writes **6**). Do not infer full baseline response equivalence from static pinning.

## Core invariants

- **PBAC:** Access is the only policy evaluation engine. Settings uses `display_setting.view/edit`; Numbering uses `resource.configure_numbering`, **not** `resource.edit`; Workflow options require `view`, `setstate` and specific registered transition action. Standard `ABSOLUTE_DENY`, persisted target organization and state scope retained.
- **State:** 8 Native and 2 All States independent types do not participate in lifecycle target execution. `ResourceStateRepository` resolves current authoritative persisted State. No client-chosen destination, IDs only from fixed allowlist, row-level lock and compare-and-swap update.
- **Transactions:** Settings overrides and `configuration_audit_events` share one transaction. Numbering locks the Resource, prevents duplicate series codes, active default conflicts, issued-field mutation (counters). Workflow executes an action against a locked persisted status with rollback on failure. All of this remains to be **validated on MariaDB** for M4.
- **HTTP security:** CI4 explicit routing, global CSRF and request metadata; no autoroutes. All M4 *write* controllers authenticate first and fail-closed as **HTTP 403 ACCESS_MUTATION_NOT_ENABLED** unless `DINKERP_M33B_WRITE_MODE=enabled` in an isolated test process. Keep the flag OFF on production.
- **Isolation:** No files in `frontend/` or `backend_basline/` modified. Source code in Settings/Numbering/Workflow only; Access changes limited to the central PBAC action boundary.

## Simple Windows XAMPP checks

From `D:\XAMPP\htdocs\erp` after applying **only one** update method (`git apply M4.patch` OR unzip Overlay into `D:\XAMPP\htdocs\`):

```powershell
cd D:\XAMPP\htdocs\erp\backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
.\tools\test-unit.ps1 RUN DB-READ
.\tools\test-unit.ps1 RUN INTEGRATION
```

`CHECK` and `RUN` verify the 84 reviewed operation footprint, 53 offline suites and the older M3.4D six source gates. `ACCEPTANCE` adds the M4 source contract gate and exports a JSON report in `backend/writable/m4/reports/`. `DB-READ` is the existing seven M3 read-only commands. `INTEGRATION` remains the existing **M3.4B 29-mutation** isolated sandbox, then checks the M3.4D and M4 source gates; it **DOES NOT** execute the new M4 14 endpoints. Retain this distinction in all closure reports.

## Remaining M4 local integration / parity gates (not automatically PASS)

1. **Settings:** check 15 keys, 2 scopes, Organization ancestors and defaults, session and CSRF, SQL override + one immutable audit event in the same MariaDB transaction, reset semantics, `vi/en` errors and 403 write-gate default.
2. **Numbering:** exercise the 6 HTTP routes on a disposable MariaDB sandbox: pattern token validation, 201/create, 200/update, protected `resource.configure_numbering`, duplicate code and active default conflicts, counter-issued immutability, locking and two-writer concurrency.
3. **Workflow:** inspect allowed State/options on an actual Lifecycle business row; action permission per transition; `ABSOLUTE_DENY`, source State re-read, atomic CAS, error/rollback on stale state, contacts specialized type check.
4. **Contract/live parity:** compare request schemas, response JSON/error-code/status/body/locale and SQL side effects with isolated baseline v1.2.4 for all fourteen new routes; golden manifest pins only method/path and intended success status.
5. **Production gate:** Keep `DINKERP_M33B_WRITE_MODE=off`. Do **not** promote write enablement based on the M3.4B 29/29 test: it never covered M4 writes.

## Static tested coverage

The new `m4_settings_rules_pure.php`, `m4_numbering_rules_pure.php`, `m4_workflow_rules_pure.php`, `m4_contract_pure.php` and `m4_security_pure.php` cover input validation, fail-closed source guards, exact 14-route and 84-route inventory and deliberately forged route/handler rejection. Existing M1–M3.4D tests are retained; count **53/53 suites** at delivery. Static test assertions are **not** HTTP transaction evidence.
