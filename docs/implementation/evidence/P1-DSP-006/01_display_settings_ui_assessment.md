# P1-DSP-006 Assessment

Status: DONE
Task: P1-DSP-006 - Display Settings administration + My Display Preferences UI
Risk/impact/profile: HIGH / frontend / frontend
Dependencies: P1-DSP-004 DONE; P1-DSP-005 DONE; P1-FE-001 DONE; P1-FE-002 DONE
Authority cache: VALID

## Scope and implementation

The `/system/display-settings` screen uses the P1-DSP-004 generated client
through FE-002's typed transport and common query state. It loads canonical
definitions, Organization tree options, and context-scoped value rows for
SYSTEM or a backend-visible ORGANIZATION. Date & Time, Number, and Money are
rendered from the fixed registry definitions; there is no arbitrary key/value
editor. Each row keeps canonical form values separate from the displayed
effective value and shows source, inherited/override state, and a formatter
preview. Save and reset call only the approved Display Settings PATCH/RESET
APIs and preserve 403/404 responses through the shared error boundary.

The user-menu `My Display Preferences` route edits the existing authenticated
browser overlay for all 15 canonical keys and the independent `ui.locale`
preference. It never calls administrative mutation APIs. Clearing a local
override removes that key and immediately reveals the Organization/System
runtime value. Existing auth activation/logout hooks continue to select and
clear `ui.presentation.<user-id>`, preserving user-switch isolation. Preview
examples use the shared presentation formatter surface and do not affect
business timezone or canonical API values.

## Corrective follow-up

The preferences view now re-activates the authenticated user on mount and when
the session user ID changes. This closes the lifecycle gap where a direct route
mount could render controls before the display store had loaded the user's
browser namespace. The focused Playwright journey also verifies that a changed
canonical value remains after a page reload. Persistence remains browser-local
by approved DSP-005 semantics; no server-side USER route or CORS-dependent
mutation was introduced.

## Validation

| Check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-DSP-006` | PASS; `AUTHORITY_CACHE: VALID`, dependencies DONE, frontend profile |
| `pnpm run type-check` | PASS |
| `pnpm run lint:oxlint` | PASS |
| `pnpm run lint:eslint` | PASS |
| `pnpm run format:check` | PASS |
| `pnpm run test:unit -- --run` | PASS; 10 files, 21 tests |
| `pnpm run build-only` | PASS; production Vite build |
| `pnpm run openapi:generate:check` | PASS; 17 generated TypeScript files tool-owned |
| `pnpm exec playwright test e2e/dsp006-display-settings.spec.ts --project=chromium` | PASS; 3 tests, including reload persistence |
| `cd backend; php vendor/bin/phpunit --no-coverage --filter P1Dsp` | PASS; 28 tests, 207 assertions |
| `git diff --check` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | PASS after ledger update |

The Playwright API routes are deterministic test doubles for the approved
authenticated journeys; they verify UI state, request method, local overlay
precedence/reset, locale independence, and accessible labels. Backend PBAC,
visibility, inheritance precedence, audit, and Business Timezone semantics
remain covered by the preceding DSP foundation/API regressions and were not
rewritten in this frontend task.

## Closure boundary

Changed task files are limited to the Display Settings admin/preferences
views, router/shell/i18n wiring, the generated-client adapter, focused
Playwright coverage, this assessment, and the execution ledger. No backend
code, schema, API contract, generated API artifact, P1-DSP-090, P1-FE-003, or
feature UI was started. The unrelated working-tree changes in the favicon,
reference logo assets, documentation images, and
`backend/app/Infrastructure/Settings/DisplaySettingRegistry.php` remain
unstaged and untouched.

The ledger uses the repository's pre-commit `0000000` convention; the actual
post-commit Git hash is authoritative after commit.
