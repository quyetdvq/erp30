# 19 — Frontend V2.4: Security Policies / PBAC boundaries

**Change ID:** `FE-V2.4-SECURITY-POLICIES-2026-10-14`
**Parent:** Frontend V2.3 as accepted by the user on Windows (135/135 Unit, 243/243 Playwright, type-check, lint, Prettier and Vite build PASS).
**Historical baseline:** `FE-V2-START-2026-10-14` — immutable.
**V2.4 status:** **STATIC-VERIFIED / WINDOWS VERIFICATION PENDING**.

## Scope and exact boundaries

V2.4 extracts responsibilities from [`SecurityPoliciesView.vue`](../../frontend/src/features/security/pages/SecurityPoliciesView.vue) (V2.3: **962 lines**; V2.4: **616 lines**). The **entire outer Vue template** and `<style scoped>` are **byte-identical** to the accepted V2.3 source. No component markup, responsive CSS, navigation, language keys, list table controls, modal ownership or permission-hint expressions are changed. Consequently there is intentionally **no modal/subcomponent split** in this high-risk PBAC iteration.

| Owner | Responsibility | Preserved invariant |
|---|---|---|
| [`types.ts`](../../frontend/src/features/security/policy-editor/types.ts) | `ParticipantType`, draft/action shape, participant and effect option order | `USER/GROUP/POSITION/DEPARTMENT/ORGANIZATION`; `ALLOW/DENY/ABSOLUTE_DENY` |
| [`model.ts`](../../frontend/src/features/security/policy-editor/model.ts) | Create defaults, API Policy-to-edit form mapping | BIGINT as string; nullable state maps to empty select; row-action ordering unchanged |
| [`payload.ts`](../../frontend/src/features/security/policy-editor/payload.ts) | API create/edit body | **Explicit `state_id: null` for ALL_STATES on PATCH**, never omission; every effect unchanged |
| [`validation.ts`](../../frontend/src/features/security/policy-editor/validation.ts) | State-selection availability predicate; duplicate-scope display text | Invalid persisted State never auto-falls back to ALL_STATES; scope string uses five original components |
| [`usePolicyReferences.ts`](../../frontend/src/features/security/policy-editor/usePolicyReferences.ts) | Reference catalog fetch, label resolution, available actions | Same Promise.all / Promise.allSettled, sort/pagination, failure behavior; action code fallback |
| [`usePolicyParticipants.ts`](../../frontend/src/features/security/policy-editor/usePolicyParticipants.ts) | Remote participant search, debounce, race protection, selection preservation | Request-id and participant-type guard, 250 ms timer, first participant default, stale-result rejection, unmount cleanup |
| [`usePolicyWorkflowStates.ts`](../../frontend/src/features/security/policy-editor/usePolicyWorkflowStates.ts) | Resource authorized state options and pending/unavailable state | Request-id guard, fails closed on missing/catalog error without clearing selected persisted state |
| [`SecurityPoliciesView.vue`](../../frontend/src/features/security/pages/SecurityPoliciesView.vue) | Table state, modal orchestration, create/edit/save/toggle, authority hints, duplicate-scope reload | Same save and toggle services, confirm-discard, `busy`, `markClean`, `load()` ordering, duplicate error handling |

The frontend **does not implement or change the PBAC decision algorithm** (policy precedence / cross-lane behavior). The backend remains the permission authority. This refactor does **not** treat `ABSOLUTE_DENY` as equivalent to `DENY`, invent client-side validation, change lifecycle semantics or resolve any previously reported backend/OpenAPI hash drift.

## Highest-risk contracts

1. **State scope is security-sensitive.** `policyPayload()` emits `state_id: draft.state_id || null` for both CREATE/PATCH. Editing a Policy on a State that is no longer valid must display a blocking error instead of widening it silently to ALL_STATES. The async State lookup must ignore stale responses. The existing server `getResourceAvailableStates()` does the policy state-code validation and remains unchanged.
2. **Participant identity and race guards.** All IDs stay strings, including BIGINT beyond JS safe integers. The original `loadParticipantOptions()` algorithm has the same request-id and type guards, unchanged, and `openCreate` still rebases its clean draft only when the first selection remains pristine. Closing/unmounting cancels the pending debounce.
3. **Effect ordering and status.** Action IDs and all three effect values stay unchanged in payload. The create/edit form does not send `status`; state activation/deactivation stays in the original `changePolicyState()` service. No extra client authorization or fallbacks are introduced.
4. **UI permission hints and error semantics.** All original `hasBasePermission` calls and `TableRowActions` permission props remain byte-identical as part of the template; the backend remains authoritative. `POLICY_SCOPE_ALREADY_EXISTS` still triggers only the existing modal conflict notice and reload action.
5. **External boundaries.** Backend, migrations, OpenAPI, generated SDK, other security screens, router, session/authorization stores, i18n and E2E tests are untouched. The existing external hash deviations in `backend/app/Config/Routes.php` and `contracts/openapi/openapi.yaml` on the user's machine remain **open for independent compatibility review**.

## Verification and limits

| Gate | Build-time evidence | Limit |
|---|---|---|
| V2.4 structural guard | **6/6 PASS**; pinned full DOM/CSS SHA-256, untouched handler AST, relocated 12 function + 4 computed ASTs, save/toggle ordering, fail-closed guards, deterministic differential tests | Static and model-level proof, **not** Vue runtime |
| V2.1–V2.4 structural Node checks | **21/21 PASS** | No browser execution |
| Original implementation vs extracted form/payload/state/scope | **15,000 cases x 5 comparisons PASS** | Generated combinations; other service interactions validated by AST and tests pending |
| Import gate | **653/653 relative/alias edges PASS** on 175 TS/Vue source files | Not `vue-tsc` |
| Isolated TypeScript semantic probe | **PASS** for 7 modules + 4 Vitest files | Uses temporary type stubs, **not** Vue compiler or production dependencies |
| New Vitest suites | **28 tests in 4 files** | Written but **NOT RUN here**: expected total **163 tests / 36 files**, based on V2.3 user log |
| Type-check, lint, Prettier, build, Playwright for V2.4 | **PENDING on Windows** | Acceptance only after original gates run with installed dependencies and real browser |

The source snapshot and legacy manifest MUST NOT be rewritten. `pnpm run diff:v2-baseline` deliberately reports changed files after refactor, and that alone is not a regression. **Do not** use `check:v2-baseline --strict` to gate a deliberate V2.4 change.

## Windows acceptance

Apply `erp30_frontend_v2_4_from_v2_3.patch` from repository root **after V2.3**; do not apply to V2 Start or overwrite a worktree with unrelated backend changes. Use the provided upgrade guide to run scoped Prettier, followed by:

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm exec prettier --write src/features/security/pages/SecurityPoliciesView.vue src/features/security/policy-editor
pnpm run check:v2-4-policies
pnpm run check:v2-3-employees
pnpm run check:v2-2-product-editor
pnpm run check:v2-1-shell
pnpm run check:imports
pnpm run diff:v2-baseline
pnpm run type-check
pnpm run lint
pnpm run format:check
pnpm run test:unit -- --run
pnpm run build
pnpm exec playwright test
```

**Stop and report** any difference in DOM/CSS hash, PBAC effects, TypeScript, lint, unit or Playwright tests. Do not update the pinned hashes or disable permission-specific tests to make a failing gate green. Full acceptance requires **163 Unit + 243 E2E = 406 tests** if the test inventory remains unchanged; report the actual counts instead of assuming them.

**Commit/tag:** NOT CREATED; patch only. `V2.4` is `NEEDS_VERIFY` until Windows logs confirm all gates.
