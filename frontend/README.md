# ERP30 Frontend

Vue 3 + TypeScript + Vite + Pinia + Vue Router + Naive UI; Vue I18n supports Vietnamese and English. This frontend is **API-contract-driven** (`../contracts/openapi`); all authorization decisions remain authoritative on the server.

## Architecture (as-built frontend refactor v1)

- `src/features/<domain>/pages/`: route-level pages grouped by business domain (`account`, `auth`, `catalog`, `organization`, `people`, `product-import`, `security`, `settings`, `workflow`, `workspace`). Business pages are **lazy loaded**.
- `src/app/navigation.ts`: sidebar/menu and recent route hints. Menu visibility is only a Base Permission *presentation hint*.
- `src/router/routes.ts`: all route definitions, labels, breadcrumb meta and priority. `src/router/index.ts`: history, authentication and dirty-form navigation guard. Do not change one without checking the other.
- `src/i18n/locales/{vi,en}.ts`: messages; `src/i18n/index.ts`: stable public API (`messages`, `i18n`, `setLocale`, `supportedLocales`) and locale persistence.
- `src/components/`: reusable list/form/domain-specific components; `src/composables/`: reusable stateful behavior; `src/modules/`: existing pure domain helpers.
- `src/services/`: existing domain adapters and axios transport; `src/stores/`: Pinia stores for auth/workspace/display settings.
- `src/api/generated/`: OpenAPI-generated types and SDK. **Never edit generated files manually.**

## Invariants during code cleanup

1. Preserve every route path, name, breadcrumb meta, guard and lazy import. Keep specific routes (`/products/new`) before parameterized routes (`/products/:id`).
2. Preserve menu permissions and server PBAC distinction, CSRF request-turn serialization, stale-session protection, dirty-tab guard, pagination/list state and all product/import/workflow mutations.
3. Keep messages in VI and EN in parity. Treat decimal/money string serialization as part of the API contract; formatting must not mutate values submitted.
4. Keep Table First list controls and `TableStatus`, `TableRowActions`, `TableDetailModal`; do not replace server-side filtering/authorization with client-only logic.
5. Refactor large components incrementally with regression tests. Moving source/organizing configuration does **not** prove runtime compatibility by itself.

## Install & run

Node must match `package.json#engines`; use the pinned pnpm version from `packageManager`.

```bash
pnpm install --frozen-lockfile
pnpm dev
pnpm run type-check
pnpm run lint
pnpm run test:unit -- --run
pnpm run build
pnpm run test:e2e
```

## Structural verification for the 2026-10-14 refactor

```bash
pnpm run check:imports
pnpm run check:refactor-baseline
node --experimental-strip-types --test scripts/build-output-names.test.mjs
```

`check:refactor-baseline` strictly compares frontend source, routes, navigation, locale, Vue views and preserved runtime logic. Backend Routes.php and OpenAPI remain visible as **WARN** if they diverge from the original snapshot; they are shared dependencies rather than code touched by this frontend refactor. To fail on external drift as well, run `pnpm run check:refactor-external`. Review those differences for API compatibility before release; a frontend PASS does not assert external compatibility.

`check:refactor-baseline` is a **one-time behavioral-preservation snapshot** of pre-refactor routes/navigation/locales/views/critical modules. It must not be silently regenerated to mask drift and is not a permanent ban on future approved feature changes. For methodology, evidence and unresolved runtime checks see [`docs/as-built/12_FRONTEND_REFACTOR_V1.md`](../docs/as-built/12_FRONTEND_REFACTOR_V1.md).

For Windows quality-gate troubleshooting after v1.1, see [`docs/as-built/14_FRONTEND_REFACTOR_V1_2_VALIDATION.md`](../docs/as-built/14_FRONTEND_REFACTOR_V1_2_VALIDATION.md).

## Frontend V2 start: frozen baseline, not another structural refactor

The stable starting point is **`FE-V2-START-2026-10-14`** from the **v1.2 source**. User-reported Windows quality gates: **102/102 unit tests**, **243/243 default Playwright tests**, TypeScript/lint/format/build PASS (ESLint still reports 3 non-blocking E2E warnings). No feature/runtime code is modified by the V2 checkpoint setup.

```bash
pnpm run check:v2-baseline      # STRICT: byte-level clean v1.2 checkpoint (before V2 edits)
pnpm run diff:v2-baseline       # REPORT: changed/added/deleted frontend files during V2
pnpm run check:refactor-baseline # original pre-v1 behavior/static contract guard
```

A strict checkpoint is expected to fail **after legitimate V2 edits**; that failure is evidence of source drift, not by itself a regression. Each change needs review + unit/E2E/contract gates rather than re-recording the frozen snapshot. See [`docs/as-built/15_FRONTEND_V2_START_BASELINE.md`](../docs/as-built/15_FRONTEND_V2_START_BASELINE.md). Backend Routes.php and OpenAPI differ on the user's Windows checkout and are **not** silently promoted into the V2 frontend baseline.

## AppShell refactor V2.1

`src/components/AppShell.vue` composes four adjacent composables under `src/components/app-shell/` for navigation/command palette, header preferences/account, workspace tabs, and sidebar resize. It retains the original DOM template and scoped CSS declarations.

Run `pnpm run check:v2-1-shell` and `pnpm run diff:v2-baseline` alongside the normal type/lint/unit/build/E2E gates. The immutable V2 Start snapshot will show expected drift after this extraction. Details: [`../docs/as-built/16_FRONTEND_V2_1_APPSHELL_REFACTOR.md`](../docs/as-built/16_FRONTEND_V2_1_APPSHELL_REFACTOR.md).
