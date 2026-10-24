# M5.4.1 — Consolidate module ownership (Backend Rewrite)

**Change type:** Structure-only refactor on top of M5.4 (105/150 API). No new endpoint, payload, PBAC action, schema, migration, response translation, database write behavior, or Frontend change.

## 1. Document authority and naming decision

- `docs/DinkERP_Backend_System_Architecture_Standard_v1.1.1.md` §3 establishes **business-data ownership**, not root-per-CRUD: Organization owns its tree; logical EmployeeStructure owns Department/Position/Employee/Assignment; Foundation owns Province/Ward; Authorization owns PBAC.
- `docs/DinkERP_Backend_Rewrite_Architecture_v1.2.4_Rev1_StateRules.md` §B uses the **Rewrite runtime names** `Access`, `Locations`, `Organization`, `People` for those same business areas, and explicitly preserves current API/auth semantics. That document is marked a proposal, so M5.4.1 does **not** silently rename accepted M3 `Access` to `Authorization` nor change the Resource/Authorization split. The architecture standard remains the logical owner map; runtime naming harmonization requires a separate accepted decision.
- `docs/DinkERP_Backend_Architecture_Blueprint_Lean_v1.2.2.md` §4 favors incremental refactors without moving healthy modules merely for name symmetry, and §5 forbids cross-owner internal imports and new compatibility layers.

**Current installed owner mapping (not a claim all future roots already exist):**

| Runtime root | Business scope | Logical standard label | State |
| --- | --- | --- | --- |
| `Identity` | Shield identities, users | Identity | Retained |
| `Access` | PBAC/group/policy + current Resource metadata | Authorization + Resource | Retained compatibility boundary |
| `Locations` | Provinces/Wards | Foundation | Retained Rewrite name |
| `Organization` | Organization read + command | Organization | Consolidated |
| `People` | Department, Position, Employee, Assignment | EmployeeStructure | Consolidated |
| `Workflow` | Lifecycle/State/Transition | Workflow | Retained |
| `Numbering` | Series/configuration | Numbering | Retained |
| `Settings` | Display/runtime settings | Settings | Retained |

**Not implemented yet:** Contact, Product, Pricing. Do not create empty business roots to give the appearance of implementation. Future Contacts mutation scope belongs to one Contact/Contacts owner, not `ContactsManagement`.

## 2. Bounded changes

| Before | After | Reason |
| --- | --- | --- |
| `Modules/OrganizationManagement/Mutation/*` | `Modules/Organization/Mutation/*` | Read and command share one owner |
| `Modules/PeopleManagement/Mutation/*` | `Modules/People/Mutation/*` | Employee/Assignment command shares read-data owner |
| `Modules/EmployeeStructureManagement/Mutation/*` | `Modules/People/Structure/Mutation/*` | Department/Position is a People capability |
| `Modules/ReadApi/ReadCatalogController.php` | `Controllers/Api/ReadCatalogController.php` | Cross-owner M2 compatibility HTTP adapter is not a business root |
| Access direct import of Organization/People repositories | Two Access-owned factual read ports wired by `Bootstrap/AccessReadAdapters.php` | Prevent static and runtime circular module imports while retaining M3 constructors |

The `ReadCatalogController` remains one **transitional** controller to protect frozen M2.1 routes, pagination and PBAC. Splitting into three owner-specific read controllers requires a separate parity-backed change; do not claim it is already performed.

Only `App\Bootstrap\AccessReadAdapters` knows which provider implements each read port. No SQL is executed by this composition class; it returns the existing read repositories. The Access port contracts carry employee/assignment/organization **facts**, never an allow decision. These are source-level module-boundary improvements; use the operator's DEV HTTP checks to verify runtime behavior.

## 3. Regression and acceptance checklist

From `backend/`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

The new `m541_module_ownership_pure.php` pins the exact active module roots, prevents reappearance of the three `*Management` roots or `ReadApi`, verifies namespace/route owner, and executes fixture-based Access read-port assertions. Prior 75 suites must remain passing; the total becomes **76 suites**. The 6 M3.4D static source gates and 105/150 OpenAPI inventory are unchanged.

**DEV runtime smoke after applying:** authenticate normally to the existing XAMPP VirtualHost and verify the accepted GET Organization/Employee lists; repeat at least one `PATCH /api/v1/organizations/{id}`, `PATCH /api/v1/employees/{id}` and `PATCH /api/v1/departments/{id}` with permitted actor, plus a denied actor, then verify bilingual errors with `Accept-Language: en`/`vi`. Never mistake source acceptance for completed HTTP/PBAC integration.

## 4. Acceptance boundaries and remaining debt

- No live HTTP/MariaDB/rollback/concurrency was run in the patch author environment.
- The v1.2.4 Rewrite 11-area naming and the 12-root architecture standard are not perfectly identical. `Access` currently also owns Resource catalog metadata; splitting Resource out is a **separate architectural decision**, not cosmetic file movement.
- The M2.1 cross-owner read HTTP adapter remains transitional and is outside `Modules`; it retains old handler methods exactly.
- The transitional Bootstrap adapter keeps existing Access constructors stable. A future explicit constructor-injection pass may remove this compatibility assembly seam after runtime parity.
