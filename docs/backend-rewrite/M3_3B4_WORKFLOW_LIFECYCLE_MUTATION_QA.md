# DinkERP Backend v2 — M3.3B4 Workflow States & Lifecycles Mutation

**Baseline:** `erp_v1.2.4.zip` · **Source milestone:** M3.3B3 · **Generated:** 2026-10-23

## Delivery and boundaries

M3.3B4 adds **8** exact OpenAPI v1.2.4 mutation operations, taking the total
non-OPTIONS HTTP operations from **62 to 70**. No Frontend changes; no
`backend_basline` modifications; no migrations/DDL; no user media changes.

| HTTP | Path | Baseline success |
|---|---|---:|
| POST | `/api/v1/workflow-states` | 201 |
| PATCH | `/api/v1/workflow-states/{id}` | 200 |
| POST | `/api/v1/workflow-states/{id}/actions/set-state` | 200 |
| POST | `/api/v1/lifecycles` | 201 |
| PATCH | `/api/v1/lifecycles/{id}` | 200 |
| POST | `/api/v1/lifecycles/{id}/actions/set-state` | 200 |
| PUT | `/api/v1/lifecycles/{id}/states` | 200 |
| PUT | `/api/v1/lifecycles/{id}/transitions` | 200 |

Both configuration PUT endpoints intentionally dispatch the SAME application
method. The original controller sends both endpoints to `configureStates()`;
`LifecycleApplicationService::configure()` **appends** to `lifecycle_states`
and `lifecycle_transitions`, never deletes/replaces the existing lists. An
omitted list is equivalent to an empty list, NOT a clear request.
The route parameter, not a row-supplied `lifecycle_id`, is authoritative for
both State and Transition INSERTs: matching the baseline, the incoming row may
omit `lifecycle_id` or supply a different value and it is always overwritten
with the Lifecycle ID in the URL before persistence.

### Invariants

- Resource types `workflow_state` and `lifecycle` are among the **8 Native
  State** resources. Their `status` fields are catalog-enable flags
  `ACTIVE/INACTIVE`, not business record workflow status. The 2 All States
  resource exceptions and 13 lifecycle-managed business Resource definitions
  are unmodified.
- Lifecycle configuration is validated using **persisted + incoming** states
  and transitions before inserting anything. Every member must be an ACTIVE
  Workflow State. ACTIVE Lifecycles have exactly one Initial State. A
  transition must be internal to the configured Lifecycle, and `(from_state_id,
  action_code)` must be unique across the full graph; disabled transitions are
  still validated.
- The target Lifecycle row is locked with `FOR UPDATE` in the transaction;
  referenced ACTIVE Workflow State rows are locked during validation to avoid
  deactivation races. Errors rollback the transaction and no partial
  membership/action pair is committed.
- Create/edit/setstate/edit-configuration use their **separate PBAC actions**
  on a trusted persisted target, not a client-supplied target/State.
  Authorization can be vetoed by ABSOLUTE_DENY. All audit actor/timestamps
  are assigned by Backend. JSON audit columns never reach SQL.
- `PATCH` can modify an explicit Native status on these two master types,
  consistent with baseline. Policy/Resource's `setstate`-only rules are untouched.
- `setstate` returns a full master representation after its second VIEW check,
  consistent with baseline's workflow master behavior. Policy/Resource use
  their previously established minimal response instead.
- The existing request metadata filter, CSRF, `vi/en` catalog, response envelope
  and API error handling are reused; no new divergent localization pipeline.

### Deployment protection

All M3.3B1/B2/B3/B4 writes share the existing **closed by default** switch:

```dotenv
DINKERP_M33B_WRITE_MODE=off
```

Installing the overlay registers routes but **does not enable writes**. Do not
set to `enabled` on production. For mutation HTTP testing, use a separate
XAMPP environment connected to a **database clone** with an independent
Shield session, including rollback/retry, concurrent configuration, and
`Accept-Language: vi` vs `en` verification.

### Installation & regression (PowerShell, from ERP root)

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m33b4_rules_pure.php
php backend\tests\m33b4_transaction_fixture.php
php backend\tests\m33b4_contract_pure.php
php backend\tests\m33b4_verify_pure.php

cd backend
php spark m3:audit
php spark m33b:verify
php spark m33b2:verify
php spark m33b3:verify
php spark m33b4:verify
php spark m33b4:verify 20 50
php spark routes
```

`m33b4:verify` makes SELECT-only schema, action catalog, State graph and
actor PBAC checks. It does not write data and reports exactly which Lifecycle
configuration is invalid. If the user has intentionally created a new ACTIVE
Lifecycle that is not yet configured with an Initial State, this command will
correctly fail until it has a valid initial membership.

### Implemented tests — execution environment of patch author

- M3.3B4 rules: **48/48 PASS** (four SHA-256-pinned v1.2.4 source snapshots).
- M3.3B4 SQL transaction/rollback in-memory fixtures: **41/41 PASS**.
- M3.3B4 pinned OpenAPI/route/security contract: **29/29 PASS**.
- M3.3B4 SELECT-only verify-command fixtures: **9/9 PASS**.
- All 31 pre-existing and new standalone PHP test scripts: **31/31 PASS**.

**Limits:** In-memory transaction doubles do not validate real MariaDB
isolation. No authenticated HTTP mutation, real SQL INSERT/ROLLBACK, baseline
live HTTP parity, CSRF/browser E2E or locale/error parity across all 8 new
routes has yet run on the user's Windows XAMPP database. Do not enable writes
until these checks pass on a clone. Do not switch `DINKERP_M32_READ_MODE` to
`enforce` without independent PBAC shadow/HTTP parity.

### Source fidelity and explicit distinctions

The baseline repository checks required master fields `code/name/status`, and
passes Workflow master `code` and `name` strings through to persistence.
This implementation does not secretly lower-case/trim those strings; it
normalizes only Native enable `status`. For a transition, the baseline
validator lower-cases the **comparison key** for uniqueness; storage retains
its original `action_code`. Newly written code keeps the same behavior.

The rewrite additionally validates JSON field types at the boundary and
checks first-stage configuration before INSERT. These safety checks are not
a claim of 100% live error-body/status parity. Exact conflict/duplicate SQL
mapping, concurrency conflicts, authentication edge cases and exhaustive
baseline response fidelity remain gates for DB-clone HTTP comparison.
