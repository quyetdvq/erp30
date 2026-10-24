# DinkERP Backend v2 — M3.1 Access/PBAC foundation & canonical State contract

**Status:** Code complete and tested offline. Runtime database audit and full actor authorization parity are pending. **M3 is NOT accepted as a complete milestone.**

## Fixed layout

- `backend/` new CI4 application. `backend_basline/` old read-only reference. `frontend/` unchanged.
- Files and images remain under `backend/public/upload/`; no changes in this overlay.
- No migration, write, seed, public route, or modification of the approved M0–M2.1.1 controllers.

## Implemented in M3.1

| Component | Purpose |
|---|---|
| `Access/ResourceStateCatalog.php` | Exactly 10 State exceptions (8 Native, 2 All States), future resources Lifecycle by default |
| `Access/ResourceStateValidator.php` | Pure persisted-State, Shield user flags, policy/creation/edit validation, initial State, Transition target, reassignment safety |
| `Access/ResourceStateRepository.php` | Indexed DB reads with per-instance cache; guarded lookups for canonical State/Lifecycle/Policy data; no DB writes |
| `Access/PbacDecision.php` | General PBAC actor/assignment lane results with matched and decisive policy IDs, fail-closed malformed effect |
| `Access/AccessDecisionCore.php` | Mandatory Base Permission, employee, assignment and Resource-domain gates before policy evaluation |
| `Commands/M3Audit.php` | `php spark m3:audit`, read-only scan of current State configuration, inactive/active policies, transition members and action integrity |

M3.1 DOES NOT expose the M3 internals through HTTP. There is no mutation gate, policy CRUD, policy decision logging, or Access UI API yet. Existing 22 API operations remain served by M2.1.1, which was accepted on the current Frontend. **The strict new Lifecycle resolver is not yet connected to those read routes**, because we must first establish if existing DB data satisfies the stricter product-owner rule. This is a deliberate compatibility gate, not a silent fallback in the new engine.

## Run after copying the overlay into ERP root

PowerShell:

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m1_pure.php
php backend\tests\m2_pure.php
php backend\tests\m21_pure.php
php backend\tests\m211_routes_pure.php
php backend\tests\m3_pure.php
php backend\tests\m3_lane_parity_pure.php
php backend\tests\m3_repository_pure.php

cd backend
php spark m3:audit
php spark routes
```

`m3:audit` must run against a **backup/test copy of the database first**, even though the command uses only SELECT/catalog reads. No migration or data auto-fix is performed. It reports `BLOCKER` with Resource/Policy IDs (not business row contents), then exits with a failure if a rule is violated. Typical findings:

- Lifecycle missing on business Resource (outside the ten exceptions)
- An independent Resource is linked to a Lifecycle
- Lifecycle inactive, empty, disabled member State or lacking exactly one initial State
- State-specific Policy for `authorization_log` / `display_setting`
- State-specific Policy pointing outside valid Native/Lifecycle members (including `INACTIVE` policies)
- Transition references State outside its Lifecycle
- Policy/action effect or relation invalid
- Existing persisted State code not mapped to the authoritative catalog, for covered persisted tables

**Do not remediate through automatic migration before reviewing policy compatibility.** Submit the command output for a planned migration/reconfiguration if blockers exist. Other current modules must not be blocked simply because an internal M3 class is present.

## Offline QA on this artifact

| Check | Observed |
|---|---|
| M1 and M2 | previous tests still PASS |
| M2.1 read logic | 36/36 PASS |
| M2.1.1 route handlers | 15/15 PASS |
| M3 State/PBAC pure | 91/91 PASS |
| M3 DB repository fixture | 15/15 PASS |
| M3 policy lane parity | 729/729 scenarios match M2.1.1 |
| Frozen OpenAPI operations registered | 22/22, no additions |
| Frontend | SHA-256 check unchanged |
| PHP syntax | all `backend/app` PHP files linted |

The fixture/CLI tests **do not** prove actual DB or HTTP PBAC parity. CI4/Shield dependencies and the live MariaDB are not available in the code-build environment.

## Next steps for M3.2–M3.5

1. Review the actual output of `php spark m3:audit`. Prepare an explicit, reversible data remediation plan only for verified gaps, including `INACTIVE` policies. Do not change business meanings or discard rows.
2. Add a request-scoped **general authorization service** using trusted context, snapshot and the canonical State reader. Keep actor/time source server-side; reject client-selected authorization scope/State.
3. Differential test ALLOW/DENY/ABSOLUTE_DENY, scoped assignments, Lifecycle State and audit results against `backend_basline` with real user/database fixtures before switching approved GETs to new enforcement.
4. Expose Access resources/groups/policies read APIs guarded by the same PBAC checks; secure count/list/lookup/detail. Then add write routes under transactional mutation authorization with audit.
5. Cutover Access only after full HTTP/UI/E2E and negative security tests pass. **Do not treat M3.1 as M3-complete.**
