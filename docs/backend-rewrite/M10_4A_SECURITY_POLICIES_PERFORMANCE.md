# ERP M10.4A — Security Policies: List-First, Lazy Lookup, Read-Only View

## Baseline and scope

Patch applies **after** the accepted M10.3 sequence (including Avatar POST/static PNG and WebKit E2E fixes) on top of v1.5.0 + M9 + M10.1 + M10.2. The previous M9.0 patch is **not** a prerequisite. No route, OpenAPI, database, migration, PHP mutation, or PBAC precedence change is included.

## Design decisions

1. `/security/policies` requests its scoped list **immediately**. After a successful list, it requests only those label catalogs represented by the current page (Resources/Actions, Organizations, relevant participant types, and Workflow States only for non-null `state_id`). These optional calls do not block list rendering; raw `#ID` labels remain available.
2. Reference calls are de-duplicated and retained **only for the mounted view**, with retry on failure. No global/session/local-storage PBAC cache; no authorization decisions are derived from lookup data.
3. The Create/Edit modal fetches the needed Resource and Organization catalogs on demand, and its participant selector supports remote search for USER/GROUP/POSITION/DEPARTMENT/ORGANIZATION. A slow lookup displays loading state rather than silently broadening a selected State/Policy scope. The existing `getResourceAvailableStates` fail-closed semantics remain intact.
4. **View** is a separate modal with plain text values and no editable form elements, Add/Delete/Save buttons, or mutations. **Edit** requires BOTH `policy.edit` base permission and `row.allowed_actions.edit === true`. Save/Add/Delete handlers also reject unauthorized action attempts; SetState requires base and `row.allowed_actions.setstate`.
5. The backend currently applies PBAC filtering and pagination to the Access resource list in application code. This patch intentionally does not change backend scoping, sort or count semantics. Scaling the backend query is a separate measured and security-reviewed task.

## Quick checks on XAMPP

From `D:\XAMPP\htdocs\erp`:

```powershell
node --test .\frontend\scripts\policy-list-first-source.test.mjs
php .\backend\tests\m102_effective_row_actions_pure.php
python .\backend\tools\verify_routes.py

cd .\frontend
pnpm run type-check
pnpm exec vitest run src/features/security/policy-editor/__tests__/policyReferences.spec.ts src/features/security/policy-editor/__tests__/policyParticipants.spec.ts
pnpm exec playwright test e2e/fe030-security.spec.ts --reporter=line
pnpm build
```

## Manual checks

1. Open `/security/policies` using DevTools Network with the XAMPP DEV user. The list `GET /api/v1/policies` must start **before** the reference catalogs. A failed optional label lookup must not remove a successfully loaded list.
2. On a page with no Policies, opening the page must not eagerly enumerate all participant catalogs. Navigate pages and confirm a catalog already fetched is reused during that visit.
3. Click **View** on a Policy: inspect Organization/Resource/State/Participant/Action Effects; no input fields or mutation buttons should exist. Close it with the footer button.
4. Click **Edit** on an allowed row: existing scoped values remain, including an existing `state_id`; Add/Remove/Save work if authorized. If a lookup fails, save should not silently widen to ALL_STATES.
5. Use an account with `policy.edit` base permission but per-row `allowed_actions.edit=false`: View should exist, Edit should be hidden, and no update request should be sent.
6. Change participant type, search for a GROUP or USER outside the first 50 results, and verify the selected ID is preserved during unrelated results or late responses.
7. Test duplicate Policy error: keep unsaved draft and allow reload. Test changing State on resource, no accidental clearing if State catalog unavailable.

**Status:** patch source QA only. Browser E2E and real-database latency/PBAC UAT remain to be verified on the user's XAMPP environment. Do not describe the milestone as fully PASS until the user confirms.
