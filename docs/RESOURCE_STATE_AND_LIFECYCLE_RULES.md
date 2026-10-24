# DinkERP Backend v2 - Canonical Resource State and Lifecycle Contract

**Decision ID:** M3-STATE-001  
**Effective:** 2026-10-23  
**Authority:** Product owner confirmation, binding for Backend Rewrite M3 and later.  
**Status:** Approved business classification; implementation and full PBAC runtime equivalence still pending.  
**Scope:** Access/PBAC, Resource administration, Workflow/Lifecycle, mutation APIs, policy UI and tests.

## 1. The single authoritative classification

Exactly **10** Resources are exceptions to Lifecycle/Transition management. Do not add another exception implicitly. ALL other business Resources must use configured Lifecycles and Transitions.

| Resource `type` | State model | Lifecycle / Transition | PBAC State choices |
|---|---|---|---|
| `authorization_group` | Native State | Not used | `ACTIVE`, `INACTIVE` |
| `authorization_log` | **All States only** | Not used | Only All States |
| `display_setting` | **All States only** | Not used | Only All States |
| `employee_assignment` | Native State | Not used | `ACTIVE`, `INACTIVE` |
| `import_job` | Native State | Not used | `PENDING`, `PROCESSING`, `COMPLETED`, `COMPLETED_WITH_ERRORS`, `FAILED` |
| `lifecycle` | Native State (self-managed) | Not used | `ACTIVE`, `INACTIVE` |
| `policy` | Native State | Not used | `ACTIVE`, `INACTIVE` |
| `user` | Native State (CodeIgniter Shield) | Not used | PBAC `ACTIVE`, `INACTIVE` derived from trusted Shield account data |
| `workflow_state` | Native State (self-managed catalog row) | Not used | `ACTIVE`, `INACTIVE` |
| `resource` | Native State | Not used | `ACTIVE`, `INACTIVE` |
| **Every other Resource** | **Lifecycle-managed** | **Required** | Valid State members of its assigned Lifecycle, plus All States |

The native code lists above reflect the baseline's `ResourceNativeStateCatalog` for this version; they do **not** introduce Lifecycle bindings. A change to supported native codes is a separate business change and needs explicit approval.

## 2. Precise semantics

**A. Native State (8 Resources)**

- State comes from the resource's authoritative persisted data or trusted adapter, not a client-provided `state_id`.
- `resources.lifecycle_id` MUST be `NULL` for these types. Do not invoke Lifecycle/Transition evaluation on them.
- State-specific PBAC is allowed only for that Resource's native State codes (plus a policy covering All States).
- Native state changes are controlled by the Resource's own business rules and Access permissions. A generic Lifecycle transition cannot update them.
- `user` is special: Shield account `status` and its `active` flag jointly determine the PBAC active/inactive category; never infer ACTIVE solely from one of these fields.
- `workflow_state.status` is the enable flag of a state *definition*, not an entity state derived from an assigned Lifecycle. Its self-management must remain possible under authorized operations.
- `lifecycle.status`, `policy.status` and `resource.status` are each self-managed native fields; these entities must not reference a Lifecycle to govern their own states.

**B. All States only (2 Resources)**

- `authorization_log` and `display_setting` do not expose State-specific PBAC policies. Only **All States** is selectable.
- In the current data model, an All States policy is represented by `policies.state_id IS NULL`; do not manufacture a state ID from unrelated metadata or default it to ACTIVE.
- `resources.lifecycle_id` MUST be `NULL`. Neither resource uses Lifecycle/Transitions.
- Having metadata that looks like a status does not change this classification.

**C. Lifecycle-managed (all other Resources)**

- A valid association `resources.lifecycle_id` is REQUIRED, pointing to the configured Lifecycle. Its allowed states come only from `lifecycle_states`; changes of state use authorized `lifecycle_transitions`.
- Creation follows the Lifecycle's configured initial State; subsequent State changes require the appropriate Transition and permission. Ordinary edit endpoints must not bypass Transition rules.
- Validate trusted persisted State, Lifecycle membership and current Transition before changing the record. In the same transaction, persist the new State and the associated audit changes.
- Unknown Resource, missing Lifecycle binding, disabled/invalid Lifecycle, absent State membership or disallowed Transition are **configuration/authorization failures**. Fail closed; do not silently fall back to Native State.
- A Resource introduced in the future is Lifecycle-managed by default. Any new independent exception needs an explicit owner-approved amendment to this list.

**Important distinction:** The global `workflow_states` table can still store canonical State codes used by policy mapping. Using this catalog for an independent Resource does **not** make that Resource Lifecycle-managed. Conversely, a business row having a `status` column does **not** make it Native State.

## 3. PBAC and API behavior

1. Resolve `(Resource type -> State model)` using one central classifier, consumed consistently by Access, Workflow, resource configuration and HTTP serializers. Avoid scattered controller `if` checks.
2. Base Permission, actor eligibility, domain/scope and active Resource gating are still mandatory before evaluating State-specific policy branches. This classification does not grant permissions or bypass PBAC.
3. For Stateful Resources, policy applies if its `state_id` is `NULL` (**All States**) or matches the State ID resolved from trusted current data. For All States only Resources, State-scoped policy selection is forbidden.
4. If the API allows an explicit State hint, treat it only as a consistency check against the persisted record; it cannot determine or override the authorization State.
5. Resource administration and policy editors must supply State options from this classifier. Do not offer a Lifecycle selector for the 10 exceptions; `authorization_log` and `display_setting` offer only All States.
6. Ensure same decision logic across list/detail/count/lookup, transition/mutation and audit paths. Never leak out-of-scope State existence or counts.
7. Keep unchanged API paths, HTTP methods and JSON behavior required by Frontend v1.2.4. State options and transition validation must stay compatible with the existing contracts.

## 4. Baseline code evidence and known compatibility risk

- `backend_basline/app/Modules/Resource/Domain/ResourceNativeStateCatalog.php` already declares the same 10 independent Resources and lists the native State codes.
- `backend_basline/app/Modules/Workflow/StateResolution/Application/ResourceStateResolutionService.php` explicitly distinguishes All States only from native states. It also retains a **legacy compatibility fallback** for other business Resources without Lifecycle association. This fallback is **not** authorization to add an 11th exception in Backend v2.
- M2.1.1 has a restricted read-only `ReadViewGate`, not the complete M3 State/Transition engine. M2.1.1 was accepted for current Frontend read-route testing; its legacy read paths do not waive this M3 invariant.
- Before enforcing the stricter M3 rule against real data, inspect existing `resources.lifecycle_id`, `lifecycles`, `lifecycle_states`, `lifecycle_transitions`, and all `policies.state_id` (including INACTIVE policies). An unbound business Resource needs reviewed configuration/data remediation; do NOT silently rewrite legacy data or delete policies.

## 5. Mandatory M3 acceptance matrix

| Test | Required result |
|---|---|
| Independent Resource catalog | Exactly the 10 names above; no extra type implicitly native |
| Each of 10 with `lifecycle_id != NULL` | Reject configuration, no silent Lifecycle binding |
| `authorization_log` and `display_setting` State selector | Only All States; no State-specific policy |
| All States policy | Stored/evaluated as `state_id = NULL`, matches without a fabricated current State |
| `user`: Shield status ACTIVE but `active = 0` | Treated as not ACTIVE for State-scoped PBAC |
| `employee_assignment`: ACTIVE vs INACTIVE | Native status respected, separate from effective assignment date checks |
| `import_job` PENDING/PROCESSING/COMPLETED/COMPLETED_WITH_ERRORS/FAILED | All valid Native States; no Lifecycle Transition |
| `workflow_state` self-reactivation by permitted action | Does not require Lifecycle; avoids mistaken State-definition disable lockout |
| Business Resource without valid Lifecycle | Fail closed, report configuration defect; do not map to default Native statuses |
| Business Resource with a State not a Lifecycle member | Fail closed, no accidental State-specific permission match |
| Direct edit attempting Lifecycle status change | Reject if Transition is required |
| Transition with absent permission or invalid source/destination | Reject; do not mutate; maintain transactional correctness |
| Actor-specific ALLOW/DENY/ABSOLUTE_DENY across State scopes | Preserve PBAC lane precedence, with ABSOLUTE_DENY veto |
| List, detail, labels and totals over hidden State/scope | No leakage of rows, IDs or counts |
| Existing policy compatibility including INACTIVE policies | Identify and review before any Lifecycle reassignment/migration |
| 10-independent vs lifecycle-managed policy UI selections | Correct State options without modifying Frontend source |

**Milestone gate:** M3 cannot be considered complete based only on route presence, schema checks, or pure tests. Verify authorization/State parity against `backend_basline` with real actor/database fixtures, and identify any legacy Lifecycle gaps explicitly.

## 6. Scope and file policy

This is a documentation decision for M3. It introduces **no runtime code change**, no new routes, no schema migration and no changes to `frontend/`, `backend_basline/` or `backend/public/upload/`.
