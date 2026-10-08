# P1-FE-090 Accessibility + Critical Playwright Regression Closure

## Scope and readiness

`P1-FE-090` was executed from commit `bbab1d1` (`P1-FE-080`) after
`task-context.php` returned `AUTHORITY_CACHE: VALID`. The live ledger confirmed
`P1-FE-010..P1-FE-080 = DONE` and `P1-FE-090 = NOT_STARTED` before work. The
current UI Design v1.9 accessibility baseline and critical-flow requirements,
Implementation Plan v1.10 P1-FE-090, and the existing frontend test
infrastructure were reviewed. No approved change-control overlay applies.

The unrelated pre-existing backend changes in
`backend/app/Modules/Pricing/PriceList/PriceListService.php` and
`backend/tests/Modules/Pricing/PriceList/P1Prc001PriceListServiceTest.php`
were preserved and remain unstaged.

## Audit coverage

The existing authenticated suites were audited and executed across Chromium,
Firefox, and WebKit. They cover the following critical surfaces:

| Surface | Existing coverage | Closure checks |
|---|---|---|
| Application Shell | `e2e/fe001-shell.spec.ts` | five modes, workspace tabs, locale, 404, responsive/collapsed shell, keyboard-visible controls |
| Organization/Contact | `e2e/fe010-slice1.spec.ts` | hierarchy/parent matrix, contact history, 403/404, axe |
| Employee/Assignment | `e2e/fe020-employee.spec.ts` | employee detail tabs, assignments, canonical gender, 403, accessible modal close |
| Security | `e2e/fe030-security.spec.ts` | permission-aware controls, users/groups/resources/policies/logs, modal detail, PBAC vocabulary |
| Workflow | `e2e/fe040-workflow.spec.ts` | state/lifecycle CRUD, status actions, duplicate conflict, permission hiding, axe |
| Auto Numbering | `e2e/fe050-auto-numbering.spec.ts` | Table First CRUD/status/validation, issued-field lock, permission hiding |
| Product/UOM | `e2e/fe060-product-uom.spec.ts` | product/group/UOM lists, conversion/COMBO bounds, permission-aware editor, axe |
| Pricing/COMBO | `e2e/fe070-pricing-combo.spec.ts` | Price List CRUD/state/items, decimal payloads, UOM filtering, backdrop/Escape, axe |
| Product Import | `e2e/fe080-product-import.spec.ts` | wizard permissions, multipart submission, persisted job summary/rows, 403, keyboard action, axe |
| Display Settings | `e2e/dsp006-display-settings.spec.ts` | system/organization set-reset, inheritance, local preferences, locale isolation |

The common error boundary now has a regression matrix for 401, 403, 404, 409,
and 422 while preserving stable machine codes and localized detail. Existing
locale parity, Table First status/action, Display Settings formatter, and
permission-hint tests remain unchanged and passed.

## Changes

- Removed the unused `wrapper` mount from `SearchableSelect.spec.ts`; no lint
  suppression or file exclusion was added.
- Added an assertion that the Import Wizard sends the selected mode and file
  in the POST multipart request.
- Added a keyboard-only Table First journey from Import Jobs to persisted job
  results, including row error data and critical axe scan.
- Corrected stale/strict Playwright assertions: `/products` is now a valid
  FE-060 route, and detail dialogs select the shared icon close control.
- Added protected-status normalization coverage to `errorBoundary.spec.ts`.

## Validation

| Check | Result |
|---|---|
| `php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-FE-090` | PASS; `AUTHORITY_CACHE: VALID`; dependencies P1-FE-010..080 DONE |
| `pnpm run type-check` | PASS |
| `pnpm run lint` | PASS; Oxlint and ESLint repository-wide |
| `pnpm run format:check` | PASS |
| `pnpm run test:unit -- --run` | PASS; 15 files, 38 tests |
| `pnpm run build` | PASS; production Vite build |
| `pnpm exec playwright test --config=playwright.headless.config.ts --reporter=line` | PASS; 123/123 across Chromium, Firefox, WebKit; all axe assertions clean |
| `git diff --check` | PASS |

The temporary headless Playwright config was used only because the repository's
default local headed Firefox mode is unstable in this Windows session. It was
removed after the matrix run. A separate default-mode Chromium and WebKit run
also passed 41/41 each. A CI/preview smoke exposed a pre-existing Naive UI
chunk runtime error (`V is not a function`); no bundler or production behavior
was changed in this task.

## Contract and handoff

- DB/schema: none.
- Auth: backend remains authoritative; UI permission hints fail closed and
  tested 403/404 behavior remains visible.
- API/OpenAPI: no public contract or generated-client change.
- UI/i18n: existing vi/en keys, Display Settings, Table First status/actions,
  keyboard/focus paths, and axe coverage verified.
- No CONTRACT_PENDING capability, API, schema, or business behavior added.

Gate 8 closure readiness: READY. P1-SYS-001 is the next dependency/handoff;
this task does not begin any Gate 9 implementation.

Commit: recorded by task closure.
