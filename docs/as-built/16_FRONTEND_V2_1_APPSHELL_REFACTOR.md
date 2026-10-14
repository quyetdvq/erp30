# 16 — Frontend V2.1: AppShell responsibility extraction

**Change ID:** `FE-V2.1-APPSHELL-2026-10-14`

**Source baseline:** `FE-V2-START-2026-10-14` (frozen SHA-256 manifest, not regenerated)

**Scope:** frontend only, AppShell composition, local shell styles, regression tooling.

**Status:** `STATIC-VERIFIED / WINDOWS-RUNTIME-VERIFICATION-REQUIRED`.

> The objective is **architecture-only refactoring**. This change does not intentionally alter the DOM structure, CSS declarations, navigation policy, API requests, authentication, persisted user preferences, or any screen content. Passing static equivalence gates **does not prove** full runtime equivalence; run the complete quality suite in the target Windows environment before merge/deploy.

## 1. Structural changes

| Before | After | Responsibility |
|---|---|---|
| `src/components/AppShell.vue` — 1,474 lines, state + effects + template + CSS | `src/components/AppShell.vue` — 420 lines | Composition root and original template; no new DOM wrappers |
| Inline menu/command state | `src/components/app-shell/useShellNavigation.ts` | Base-permission filtered menu tree, favorites/recent, modes, search, favorite toggles, command palette navigation |
| Inline account/locale/header state | `src/components/app-shell/useShellHeader.ts` | Notifications, user menu, sign-out/dirty confirmation, theme and locale |
| Inline router/tab watcher | `src/components/app-shell/useShellWorkspaceTabs.ts` | Immediate route watcher, recent tracking, active tabs, dirty-close confirmation |
| Inline sidebar resize handlers | `src/components/app-shell/useShellSidebarResize.ts` | Pointer lifecycle, keyboard widths, RAF paint, cleanup, collapse |
| Inline scoped CSS | `src/components/app-shell/styles.css` with `<style scoped src="...">` | Original CSS and Vue scoped selector transformation, now an adjacent asset |

`AppShell.vue` intentionally keeps the header, sidebar, tabs, notification/command overlays **in the exact original template**. Componentization of those DOM regions can come in subsequent reviewed slices, but would otherwise risk CSS scope, teleport/popover behavior and focus/ARIA regressions. In V2.1, separating **state/effects** first is the lowest-risk way to establish clear ownership.

## 2. Preserved contracts

- **Navigation and authorization display:** 29 named routes and route guards remain unchanged; `useBasePermission().hasBasePermissionKey` still filters all navigation, favorites, recent and command results. This is not a substitute for backend authorization.
- **Workspace persistence:** `useWorkspaceStore()` remains the only owner of tab/favorite/recent/width/theme storage; no storage keys, payloads or default values changed.
- **Tabs and dirty data:** `watch(route.fullPath, ..., { immediate: true })`, `workspace.openTab`, `recordRecent`, `closeTab` and user confirmation run the same original expressions, now inside `useShellWorkspaceTabs`.
- **Sidebar:** same bounds (workspace store), pointer event registration/unregistration, RAF behavior, collapsed width, keyboard controls and DOM `--sidebar-width` updates. Lifecycle cleanup is registered during the AppShell setup by the composable.
- **Header:** same `vi/en` switching, theme toggle, notifications, sign-out flow, and dirty-tab guard.
- **Visible shell DOM and CSS:** no template node reorganization, class change or inline CSS declaration change; styles are still marked `scoped` at the Vue SFC style block.
- **External contracts:** no changes to backend, database, OpenAPI, generated SDK, API client, services, feature pages, stores or i18n resource files.

## 3. Verification evidence

| Check | Result on build workspace | Interpretation |
|---|---|---|
| Full shell Vue template SHA-256 | `ae72f3ff4be7e23f1d800ef8786a1ce87a078edf7e7fba6e5892da5f191617c3` unchanged | Exact template bytes, including nested slots |
| Scoped CSS body SHA-256 | `a48cacc6cc3aedca31bbf5aeaf898642e8f2263ccf7c81a14422e835853c1862` unchanged | CSS source content unchanged; compiler output must still be checked by build/browser |
| Original AppShell TS AST statements | **72/72** structurally present after extraction | No original non-import script statement dropped; AST comparison ignores comments/whitespace, not a runtime proof |
| `pnpm run check:v2-1-shell` | **4/4** Node tests PASS | DOM/CSS fingerprints, composable integration, AST statement preservation |
| `pnpm run check:imports` | **145 TS/Vue source files; 546 local imports; 0 errors** | Import paths valid statically |
| `pnpm run diff:v2-baseline` | Reports intentional V2.1 changed/added files | Correct: immutable `FE-V2-START` remains unchanged |
| `pnpm run check:refactor-baseline` | **5/6**, expected `preservedRuntimeLogic` FAIL | Historical V1 script-and-style hash checks the old monolithic AppShell; it must not be rewritten to hide structural changes |
| `type-check`, `lint`, `format:check`, `test:unit`, `build`, 243 Playwright E2E | **Pending target Windows execution** | Required before claiming runtime-safe V2.1 |

The new AST manifest `frontend/scripts/refactor/v2-1-shell-logic.json` stores **72 original statement fingerprints** (not a copy of the old source). The test reads the new AppShell and its four composables, normalizes TypeScript syntax trees and verifies each original statement is still represented. This provides a reviewed exception for the intentional failure of `preservedRuntimeLogic`, **not permission to suppress unrelated failures**. The historical `baseline-2026-10-14.json` and `v2-start-2026-10-14.json` must not be rewritten.

## 4. Run on Windows after applying this patch

From `D:\XAMPP\htdocs\erp\frontend`:

```powershell
pnpm run check:v2-1-shell
pnpm run check:imports
pnpm run diff:v2-baseline
pnpm run check:refactor-baseline  # expected: preservedRuntimeLogic FAIL only
pnpm run type-check
pnpm run lint
pnpm run format:check
pnpm run test:unit -- --run
pnpm run build
pnpm exec playwright test
```

**Acceptance criteria:** all new V2.1 shell contracts, imports, type-check, lint (0 errors), Prettier, unit tests, build and E2E pass. The V1 `preservedRuntimeLogic` hash difference must be **only** the approved AppShell decomposition; routes/navigation/locales/views, frontend critical file hashes and backend/API contracts must still be assessed separately. Do not merge if other unexpected drift appears.

### Interpreting intentional diffs

`pnpm run check:v2-baseline` in strict mode must **FAIL** because the code has intentionally changed from the frozen V2 Start snapshot. Use `diff:v2-baseline` to review changes instead. `check:refactor-baseline` will **FAIL** in `preservedRuntimeLogic` because the V1 tool snapshots the old single-file script/style bytes; this is expected **only for this reviewed extraction**.

On the user's machine, `backend/app/Config/Routes.php` and `contracts/openapi/openapi.yaml` previously differed from the original snapshot. Their content and compatibility are still **not verified**; this patch neither overwrites nor reconciles them.

## 5. Rollback and follow-up

- Apply only on an unmodified **V2 Start** frontend with the existing local backend/OpenAPI preserved.
- Keep a clean Git checkpoint. If V2.1 has a regression, use `git apply --reverse <patch>` after checking local changes, or restore the branch from the pre-V2.1 commit.
- Once tests pass, a later V2.2 slice may extract **presentational child components** from header/sidebar, but must explicitly test responsive CSS scoping, teleport overlay content, keyboard focus, permission filtering, dirty-close, width dragging, and all 243 E2E cases.
