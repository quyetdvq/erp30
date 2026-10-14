# 18 — Frontend V2.3: People / Employees feature boundaries

**Change ID:** `FE-V2.3-EMPLOYEES-2026-10-14`
**Parent:** Frontend V2.2.1 (user-reported Windows Vite build PASS and Playwright **243/243 PASS**, plus **117/117** unit tests in the preceding V2.2 run).
**Historical baseline:** `FE-V2-START-2026-10-14` — **immutable; never regenerate the 203-file manifest**.
**Status:** `STATIC-VERIFIED / WINDOWS QUALITY GATES PENDING FOR V2.3`.

## Scope and organization

This is a bounded, **behavior-preserving** restructuring of `src/features/people/pages/PeopleEmployeesView.vue`, which was **1,302 lines** in the accepted V2.2.1 source and is **1,078 lines** in V2.3. All markup between the outer `<template>` tags and all `<style scoped>` source are **byte-identical** to V2.2.1. We intentionally do **not** move the large modal into child components during this pass, because changing Vue scoped CSS and DOM ownership would enlarge the regression surface; this is a deliberate constraint, not a claim that UI decomposition is finished.

| Responsibility | New owner | Preserved behavior |
|---|---|---|
| Gender/tab and employee/assignment form types | [`types.ts`](../../frontend/src/features/people/employee-editor/types.ts) | Generated API `Employee` / `EmployeeAssignment` remains authoritative |
| Form defaults, row-to-form mapping and UTC date default | [`model.ts`](../../frontend/src/features/people/employee-editor/model.ts) | Empty vs nullable mapping, BIGINT string IDs, `today()` using UTC ISO date |
| Employee create/patch and assignment create/patch bodies | [`payload.ts`](../../frontend/src/features/people/employee-editor/payload.ts) | No status on employee save; omitted blank `user_id`; nullable personal fields; create-only `employee_id/status` on assignment; no numeric coercion |
| Manager/self/cycle/inactive and assignment form error priority | [`validation.ts`](../../frontend/src/features/people/employee-editor/validation.ts) | Existing `wouldCreateManagerCycle` and `hasPrimaryOverlap` rules; translation performed by the page |
| Organization/department/position and employee lookup/filter/options | [`useEmployeeLookups.ts`](../../frontend/src/features/people/employee-editor/useEmployeeLookups.ts) | Four Promise.all lookups, sort, error reporting, active-only dropdown options, retained selected manager, upsert order |
| Selected/all assignments, current-primary and history filter | [`useEmployeeAssignments.ts`](../../frontend/src/features/people/employee-editor/useEmployeeAssignments.ts) | Same query filters, loading/error fallbacks and primary-effective-date selection |
| List/page orchestration, permission gates, forms, workflow, ContactList, audit | [`PeopleEmployeesView.vue`](../../frontend/src/features/people/pages/PeopleEmployeesView.vue) | Same public template, DOM, CSS, handlers and API orchestration |

Backend, OpenAPI, generated SDK, i18n keys, router, menu, PBAC/permission checks, ContactList, service clients and lifecycle workflow are **not changed**. No extra changes to old P1 design docs or migrations.

## Explicit contracts and risk boundaries

1. The **employee save** still validates manager self-reference before graph cycles before inactive manager, uses the same localized error keys, then toggles `busy`, saves, upserts, marks the dirty form clean, closes the modal and reloads in the original order. `status` is still excluded from the form save body and handled by the existing workflow action.
2. The **assignment save** still rejects primary overlap before missing organization, skips when no selected employee, builds CREATE with `employee_id` and `status` but PATCH without these, saves once, marks clean, closes, reloads both assignment lists then the table. No new assignments may be saved through a missing selected employee.
3. Contact ownership (`ContactList`), audit content, displayed dates, permission-based visibility, Table First list components, details tab and modal interactions retain their original **template markup**. The note about two external hash deviations (`backend/app/Config/Routes.php` and `contracts/openapi/openapi.yaml`) is unchanged and remains an independent review item.
4. Empty optional assignment department/position are **omitted**, while its `effective_to`/description remain nullable. Employee personal/date/manager fields preserve null-versus-omission semantics; BIGINT values stay strings.
5. The `FE-V2-START-2026-10-14` snapshot is not a no-drift requirement for deliberate refactors. Use `diff:v2-baseline` for review; use per-phase structural checks and runtime suites to assess behavior.

## Verification evidence at build time

| Check | Status | Limit |
|---|---|---|
| New V2.3 Node structural guard | **5/5 PASS** | Pinned source template/style hashes, 9 unchanged handler ASTs, 3 unchanged page computed ASTs, 4 moved data functions, 6 moved computations, save ordering, composed modules |
| V2.1 + V2.2 + V2.3 structural tests | **15/15 PASS** | Source structure, not live browser semantics |
| Import scanner | **609/609 local imports PASS** (164 TS/Vue files) | Existence and feature dependency restrictions; not type-check |
| TypeScript syntax + isolated semantic probe | **PASS** | Global TS compiler with *temporary Vue/Vitest/service type stubs* for V2.3 TS files; **not** `vue-tsc` |
| Differential oracle from accepted V2.2.1 code | **10,000/10,000 matches** | Deterministic generated cases comparing employee/assignment payloads, form mapping and manager validation; not a proof for other business states |
| New Vitest coverage | **18 tests** in 3 files | Written but **NOT EXECUTED** in this environment; expected prior 117 + 18 = **135** tests in **32** test files |
| Vue type-check / full lint / Prettier / Vitest / Vite build / Playwright | **PENDING on Windows for V2.3** | npm registry was unreachable (`EAI_AGAIN`), no `frontend/node_modules` |

The 117 Vitest and 243 E2E previously passing relate to **V2.2.1 or its immediately preceding V2.2 unit run**; do not automatically transfer the PASS status to the changed V2.3 source.

## Windows gate and rollback

Apply `erp30_frontend_v2_3_from_v2_2_1.patch` only to **V2.2.1**, after its test hotfix. Do not copy the full source archive over a worktree with unrelated backend/OpenAPI changes. Then run from `frontend`:

```powershell
pnpm exec prettier --write src/features/people/pages/PeopleEmployeesView.vue src/features/people/employee-editor
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

Prettier is intentionally scoped to V2.3 files: **do not bulk-format other features**. After formatting, `check:v2-3-employees` must still PASS, particularly the template/CSS hash checks; stop and report if it fails. The three historical `playwright/no-conditional-in-test` warnings were present before V2.3 and do not count as new errors.

Acceptance requires new Node guard, imports, type-check, lint with **zero errors**, format, all Vitest tests, production build, and **243 Playwright tests** (subject to changed test inventory) passing on the actual Windows environment. Status until then: `NEEDS_VERIFY`. If a gate fails, fix only the V2.3-owned paths or roll back the patch; **do not** refresh old baseline hashes to conceal drift. Keep unrelated backend and OpenAPI modifications out of the refactor commit.
