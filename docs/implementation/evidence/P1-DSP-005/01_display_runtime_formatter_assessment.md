# P1-DSP-005 Assessment

Status: DONE
Task: P1-DSP-005 - Frontend display runtime store + formatter layer + local preference overlay
Risk/impact/profile: HIGH / frontend / frontend
Dependencies: P1-DSP-004 DONE; P1-ENV-003 DONE
Authority cache: VALID

## Scope and implementation

The shared frontend presentation foundation reuses the generated Display
Settings SDK from P1-DSP-004 and the existing authenticated `apiClient`.
`useDisplaySettingsStore` and `usePresentationSettings` keep server effective
runtime values separate from a local authenticated-user overlay. The overlay
allowlist contains exactly all 15 canonical Display Setting keys and persists
only under `ui.presentation.<authenticated-user-id>`. Auth hydration/login
activates the user namespace; logout and failed session hydration clear active
memory, preventing cross-user preference leakage. Local preferences are never
sent to the server as context, authorization, or business-timezone input.

`useFormatters` exposes the common `formatDate`, `formatDateTime`,
`formatTime`, `formatQuantity`, `formatPercentage`, and `formatMoney` surface.
Date/time rendering uses the presentation timezone only. Decimal formatting
accepts canonical strings, performs string-based rounding/grouping, returns
display text, and leaves source/form values unchanged. Locale remains the
separate `ui.locale` / vue-i18n concern. No UI screen, router/shell work,
server User Display Settings API, P1-DSP-006, Gate 8, or P1-FE-001 work was
started.

## Gate 7A exit criteria

All eleven Display Settings foundation criteria in Implementation Plan v1.9
§14A are verified across P1-DSP-001..005: registry/context and inheritance
tests, Business Timezone isolation, PBAC and mutation/audit regressions,
OpenAPI/generated-client checks, runtime/formatter tests, canonical-value
preservation, and rejection of arbitrary keys and USER/COMPANY pseudo-scopes.
Gate 7A is CLOSED. Gate 8 remains unopened.

## Validation

| Check | Result |
|---|---|
| Focused runtime/formatter Vitest | PASS; 3 tests |
| Full frontend unit suite (`pnpm run test:unit -- --run`) | PASS; 3 files, 5 tests |
| `pnpm run type-check` | PASS |
| `pnpm run lint` (Oxlint + ESLint) | PASS |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS; production Vite build |
| Generated-client integration/ownership (`pnpm run openapi:generate:check`) | PASS; 17 generated TypeScript files tool-owned |
| Impacted backend/API regressions (`P1Api090ContractRegressionTest`, `P1Dsp004DisplaySettingsApiTest`) | PASS; 10 tests, 98 assertions |
| `git diff --check` | PASS |

## Closure boundary

Changed files are limited to the frontend Display Settings service/store,
presentation formatter/composables, auth-session isolation hook, focused tests,
this assessment, and the execution ledger. No generated API source was
hand-edited and no backend/API contract was changed.

The ledger records the repository's pre-commit `0000000` convention; the
actual post-commit Git hash is authoritative after commit.
