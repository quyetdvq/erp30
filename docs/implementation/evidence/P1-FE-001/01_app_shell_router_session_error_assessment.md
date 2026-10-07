# P1-FE-001 Assessment

Status: DONE
Task: P1-FE-001 - App shell/router/session/error boundary
Risk/impact/profile: MEDIUM / frontend / frontend
Dependencies: P1-API-001 DONE; P1-API-002 DONE; Gate 7A CLOSED
Authority cache: VALID

## Scope and implementation

The authenticated frontend foundation now uses Naive UI layout, navigation,
tabs, modal, alert, and control components with Ionicons from `@vicons/ionicons5`.
The shell exposes exactly five sidebar modes: Menu, Favorites, Recent, System,
and Organization. Sidebar mode, collapse state, and bounded resize width are
stored under `ui.workspace.<authenticated-user-id>` and reset when the session
changes, preventing cross-user preference leakage.

Workspace tabs are keyed by route name, deduplicated, and keep the dashboard
non-closable. Dirty tabs require confirmation before close or route navigation.
The router hydrates the current session before protected navigation, redirects
unauthenticated users to `/login`, and leaves backend authorization as the
final authority; client-side navigation filtering is UX-only and has no
System Admin bypass. A protected catch-all route renders the 404 boundary.

The API client reports common 401/403/404/409/422/500 errors through a shared
error boundary, preserving stable error codes and `X-Request-ID` correlation
when available. Shell locale remains the canonical `vi`/`en` vue-i18n runtime.
The implementation reuses the P1-DSP-005 display settings/formatter boundary
without adding another preference or formatter stack.

## Validation

| Check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-FE-001` | PASS; `AUTHORITY_CACHE: VALID` |
| `pnpm run test:unit -- --run` | PASS; 5 files, 8 tests |
| `pnpm run type-check` | PASS |
| `pnpm run lint` | PASS; Oxlint + ESLint |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS; Vite production build (chunk-size advisory only) |
| `pnpm run test:e2e` | PASS; 6 tests across Chromium, Firefox, WebKit |
| `git diff --check` | PASS |

## Closure boundary

Changed paths are limited to the FE-001 shell, router/session boundary, shared
API error reporting, workspace state, focused tests, the frontend icon
dependency, and this evidence/ledger record. No feature screen, typed query
state, P1-FE-002, P1-DSP-006, backend, schema, or API contract was started.

The ledger records the pre-commit `0000000` convention; the post-commit Git
hash is authoritative after the task commit.

