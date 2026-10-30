# P2-CUS-G1 — Configured Lifecycle Initial State Hotfix

Date: 2026-10-29
Baseline: `erp_v2.0.0_m1925a_fixed.zip` (M19.24 + M19.25 + M19.25A), plus any non-overlapping docs-only SUP additions
Gate: Source/offline PASS; DEV HTTP/UI, MariaDB and frontend build NOT_RUN

## 1. Reproduction and root cause

After assigning Customer Resource to a Lifecycle whose `is_initial=1` State code is not `ACTIVE` (e.g. `DRAFT`), `POST /api/v1/customers` threw `CUSTOMER_INITIAL_STATE_INVALID` from `CustomerService::create`. The code resolved the trusted Lifecycle initial State correctly, then hard-rejected every code other than `ACTIVE` and hardcoded `status='ACTIVE'` on INSERT. This contradicted `ResourceStateValidator::initialState()` and the frontend standard `FE-02/FE-04` (Lifecycle-driven states).

Other latent inconsistencies: `CustomerService::setState` accepted only `ACTIVE/INACTIVE`, `CustomerSql::params` filtered only those two values, frontend list filter was hardcoded, and the Customer TypeScript contract excluded other States. OpenAPI had those fixed enums as well.

## 2. Code changes (no DB schema migration)

- Customer creation uses the **server-derived** `ResourceStateRepository::initialState('customer')` for the persisted `customers.status`. The client still cannot POST or PATCH `status`. Customer and main Contact creation remain in the one serialized, numbered transaction; rollback and PBAC remain mandatory.
- The database column is currently `customers.status VARCHAR(20)`. This patch fails before NUMBER issuance/INSERT if an initial State code exceeds 20 bytes; arbitrary longer lifecycle codes require a separately reviewed schema migration. It does **not** truncate status or widen any table automatically.
- `CustomerRules::stateCode()` validates canonical, uppercase State code syntax within the storage limit for the direct SetState endpoint. `ResourceStateRepository::transitionTarget()` still requires the exact configured `setstate` transition, and PBAC is re-evaluated on the persisted row; no new transition is granted.
- `CustomerSql::params()` validates a bound, canonical `status` code rather than a fixed two-state enum. `CustomerService::list()` additionally checks the code is a **member of the persisted Customer Lifecycle** before issuing authorized SQL COUNT/PAGE. No post-page filtering or unscoped fallback.
- Customer list UI uses existing `useBusinessWorkflowStates('customer', [])` for filter options, never inventing an untrusted fallback State. List and detail render a literal canonical code when no translation exists. Client types and the **P2 Customer design OpenAPI** reflect the dynamic State contract and current 20-character storage bound.
- The `ACTIVE` Company usage check and sales eligibility semantics are unchanged. New Customer creation continues to require a valid Company usage selection when `all_companies=false`, regardless of the initial state.

## 3. Regression evidence (offline)

| Gate | Result |
|---|---|
| Customer pure: custom initial, transition, malformed State refusal | 79/79 PASS |
| Customer source contract: trusted INSERT, SQL/FE/OpenAPI bindings | 77/77 PASS |
| Customer SQL/PBAC plan: custom status uses binds, injection rejected | 48/48 PASS |
| Customer Workflow/assignees pure | 51/51 PASS |
| M3.4B offline inventory | 168/168 PASS |
| M3.4D source architecture | 6/6 PASS |
| M19.24 Route Architecture | 470/470 PASS |
| Frozen routes / P1 M8 contract | 180/180; 150/150 PASS |
| P1 SQL/PBAC source gates, M19.25A Contact | PASS |
| P2 Customer design OpenAPI YAML syntax | PASS |
| Frontend TypeScript/build/Vitest | NOT_RUN (node_modules unavailable in source archive) |
| DEV MariaDB/HTTP/Browser | NOT_RUN (operator-owned) |

## 4. Operator UAT (frontend only; no change to route signatures)

1. Configure the Customer Resource with an **ACTIVE** Lifecycle whose *one* initial State is a known code such as `DRAFT` (<= 20 characters). Existing Customer rows in an incompatible state must be considered separately before reassigning the Resource (see caution below).
2. Confirm `customer.create` and VIEW permission for the new initial State, and for an INDIVIDUAL, `customer_contact.create` plus necessary parent Customer VIEW. The patch deliberately does not bypass PBAC to make create appear successful.
3. On `/customers/new`, create an ORGANIZATION Customer (to isolate core creation), using `All Companies` and an unused code/auto-numbering. Expect successful creation with `status=DRAFT` in the returned record and UI. Create an INDIVIDUAL and confirm its initial Contact belongs to the new Customer and the write commits atomically.
4. Confirm `/customers` displays `DRAFT` in the status filter **from Workflow metadata** and as a readable status in list and detail. List with `status=DRAFT`; SQL COUNT/PAGE must remain consistent and permission-scoped. Invalid/unknown State filters must be rejected.
5. If the configured Lifecycle defines one active `setstate` transition `DRAFT -> APPROVED`, the shared Workflow action must use that configured target and enforce `customer.setstate`, State-specific policy and Company validation on transition to `ACTIVE`. The direct `/customers/{id}/actions/set-state` endpoint must reject fake targets or no-transition paths.
6. Switch back to the approved ACTIVE/INACTIVE Lifecycle and confirm normal create/state/list behavior still works. Do not switch an existing Resource with persisted rows to an incompatible Lifecycle without a state mapping/migration plan.

## 5. Explicit limits / non-goals

- **Existing persisted Customers:** Reassigning a Resource's Lifecycle does NOT auto-migrate stored statuses. If legacy records use `ACTIVE` but the new Lifecycle does not contain `ACTIVE`, reads/transition validation may fail. A reviewed state-mapping/migration is required; this hotfix does not rewrite business data.
- **Workflow action codes:** The existing Customer-owned mutation path only executes the `setstate` action, not arbitrary approval verbs (`submit`, `approve`, etc.). Those require an independently approved action/authorization design; merely adding a transition in Workflow does not authorize it.
- **Operational meaning:** downstream Sales eligibility still depends on explicit `ACTIVE`; a Lifecycle without that code must not silently treat `DRAFT`/`APPROVED` as sale-eligible. That domain mapping is a separate business decision.
- **Storage bound:** canonical State codes are allowed up to 50 characters in Workflow, but current `customers.status` stores only 20. This fix rejects longer initial/transition values and documents the constraint; no DDL has been run.
- **Acceptance:** source/offline QA does not imply DB integration, security UAT or release approval. Record actual frontend results separately.

## 6. Deployment / rollback

Apply Git patch incrementally on your M19.25A-based checkout; it has no overlap with the SUP docs-only patch. Do not apply the full source ZIP and patch together. Verify `git apply --check`, apply, `git diff --check`, rerun `UNIT-STANDARD`, `CUS-UNIT`, `ROUTES`, and (if needed) `P1-STANDARDS`. No `php spark migrate` or seeder is required for this patch. To revert the code, use `git apply -R --check` followed by `git apply -R`; this does **not** revert new business records created during testing.
