# M8.6D — Canonical Authorization + Mandatory Organization/People SQL

**Date:** 2026-10-25 · **Base:** ERP v1.4.0 after M8.6C · **Status:** SOURCE ACCEPTED (pending DEV HTTP/MariaDB).

## Decision

The ERP runtime no longer offers the read-authorization modes `legacy`, `shadow` and `enforce` as alternatives. **Canonical `UnifiedAccessService` is mandatory** (the former `enforce` behavior). The two reviewed SQL readers (M8.6B Organization/Employee Assignment and M8.6C Employee/Department/Position) are **always selected**, without feature flags. This is a deliberate behavior change, not evidence of runtime parity or production readiness.

The following three variables are **obsolete and not read by the new runtime**:

```dotenv
DINKERP_M32_READ_MODE
DINKERP_M86B_SQL_SCOPE_MODE
DINKERP_M86C_SQL_SCOPE_MODE
```

They may be removed from `backend/.env`; no replacement setting is required. Any previous value (including `disabled` or `legacy`) is ignored by the reviewed source. Other unrelated ERP safety/write flags are NOT changed.

## Exact runtime matrix

| Resource | Authorization | Data access |
|---|---|---|
| Organization (list, detail, tree, labels) | Canonical PBAC, organization + State | Mandatory reviewed SQL scope (M8.6B) |
| Employee Assignment (list, detail) | Canonical PBAC, organization + native State | Mandatory reviewed SQL scope (M8.6B) |
| Employee, Department, Position (list, detail) | Canonical PBAC, MASTER State | Mandatory reviewed SQL scope (M8.6C) |
| Province, Ward (list, detail) | Canonical PBAC | Historical bounded row reader remains; **not yet SQL pushdown** |
| Product, Product Group, UOM | Canonical PBAC | Already migrated SQL (M8.5/M8.6A) |
| Contact, Access Management, Identity, Workflow | Their existing per-module authorization implementations | **Not migrated** by M8.6D |

No OpenAPI, route footprint, SQL schema, frontend, baseline or mutation behavior is intentionally changed. The historical `ReadViewGate` still exists exclusively for independent parity tooling; `ReadAccessBridge` no longer imports/uses it. Caller-provided mode overrides are rejected.

## Invariants

1. No runtime flag or old mode may restore the previous in-memory Organization/People list path. Every migrated resource is handled by its SQL read service or fails closed.
2. `WHERE` authorization is applied before `COUNT`, `ORDER BY`, `LIMIT/OFFSET`; `meta.total` describes only VIEW-authorized records.
3. Multi-company decisions use organization + State, never State alone; all returned rows are rechecked by canonical `canView()`.
4. SQL metadata exceeding the M8.6B **512-Organization/slot hard limit** fails closed rather than silently using an unbounded scan. This limit must be addressed before large-organization rollout.
5. Employee filters for organization, department and position retain independent effective-assignment `EXISTS` checks.
6. Resource type is selected by fixed route handlers and closed server-side resource map; request parameters cannot select a resource provider.
7. Transaction snapshots belong to SQL read services; they must not commit an external mutation transaction.

## Offline gates

```powershell
cd D:\XAMPP\htdocs\erp
python backend\tools\verify_routes.py
cd backend
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
php tests\m86d_enforce_contract_pure.php
php tests\m86d_enforce_runtime_fixture.php
php tools\m86d_accept.php
php tools\m85_scope_audit.php
```

These are **source-only tests**: no live MariaDB, no authenticated HTTP, no index profiling.

## Required DEV release evidence

Before deployment on a production ERP using real companies and employees, test on an isolated **copy** with representative actors and approved policies:

- Organization A and B with identical State but different privileges; hierarchy and assignment differences; ALLOW, DENY, ABSOLUTE_DENY.
- Result IDs, response fields, `meta.total`, pagination, Vietnamese search/sort, null fields, `is_primary` and `effective_at` boundaries.
- Employee with multiple simultaneously effective assignments satisfying separate filters; errors vs empty results; unauthorized detail must be indistinguishable from absent ID.
- Report `EXPLAIN` and latency for scoped `COUNT`, page and correlated `EXISTS`, including high-cardinality org metadata.
- Test metadata overflow (over 512 organizations/slots) and unexpected canonical evaluator/SQL exceptions. No permissive fallback should occur.
- Validate read paths exercised inside business mutation requests: canonical VIEW may change their authorization outcome compared with historical legacy mode. Confirm behavior, audit event and InnoDB rollback with real data.

**Known limitation:** irreversible removal of the runtime feature switch means rollback requires deploying the prior reviewed code, not toggling `.env`. Test with dedicated DEV accounts and plan a code rollback before replacing existing production code.

**Acceptance state:** SOURCE ACCEPTED; authenticated HTTP/MariaDB parity, index performance and production cutover NOT VERIFIED. Production remains BLOCKED. Previous M6.3C Avatar concurrency waiver remains WAIVED, not PASS.
