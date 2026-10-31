# DinkERP Frontend Design System — DS5 coding convention

`frontend/src/styles/global.css` is the single entry point for shared styles.
Never import the shared styles individually from feature pages. Light and dark
palettes live in `tokens.css` and are applied on the **document root** so that
Naive UI portals use the same semantic colors as AppShell.

| Need | Shared contract | Scope |
| --- | --- | --- |
| Page padding/grid | `.ds-page-panel` (optionally `--relaxed`) | List and master-data pages |
| Header/query controls | `.ds-page-toolbar`, `.list-query-controls` | Panel toolbar/list search |
| Form in a modal | `.ds-dialog-form-fields` | Form markup under `AppDialogHost` |
| Two-column forms | `.ds-form-grid`, `.ds-detail-form-grid` | Forms/detail workspaces |
| Customer/Supplier tabs | `.ds-detail-tabs`, `.ds-detail-body` | `ds-detail-workspace.css` |
| Security/Workflow detail tabs | `.ds-detail-tabs--spaced` | Spaced-density variant |
| List table | `.list-table` | `ListTable.vue`, via `tables.css` |
| Other grid / hierarchy | `.ds-data-grid`, `.ds-table-scroll` | Product, authorization grid |
| Paging and row actions | `.list-pagination`, `.table-row-actions` | Shared List components |
| Semantic statuses | `.table-status`, `.status-pill`, `.tag` | List/status components |

**Rules:**

1. Use `var(--ds-*)` rather than local hex/rgb colors. Use semantic tokens for
   text, borders, feedback/status and backgrounds, including dark mode.
2. Preserve component ownership: data/query/permission/mutation rules remain in
   Vue and composables. This phase does **not** change API, PBAC, `row_version`,
   unsaved-change checks, sorting, pagination or workflow transitions.
3. A `Naive UI` input/select draws its own visual border. Do **not** add global
   borders, backgrounds or focus rings to `.n-base-selection input` or
   `.n-input input` / textarea. Use the outer component's theme instead.
4. Shared defaults live in `forms.css`, `tables.css`, `detail-workspace.css`;
   feature `scoped` CSS should contain only distinctive business layout (e.g.
   a Product hierarchy indent, Customer interaction list, Supplier conflict
   panel), never another copy of a shared field, list or tab rule.
5. Avoid changing table row markup or authorization conditions when migrating
   presentation. A row action must remain fail-closed.
6. Add regression coverage to
   `scripts/design-system/form-detail-table.test.cjs` whenever the common
   contract changes. Check both themes at narrow/wide widths in a real browser.

**Validation on the developer machine:**

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm run check:design-system
pnpm run type-check
pnpm run build-only
pnpm exec vitest run src/components/ui/__tests__/AppDialogHost.spec.ts
```

The node source-level checks are not substitutes for a real browser test or a
full `vue-tsc`/Vite build.

**Page-size ownership:** when a view renders both `ListQueryControls` and
`ListPagination`, pass `:show-page-size="false"` to the query controls.
If a top/bottom pair of pagers is rendered, pass it to the **bottom** pager too.
The top pager owns the sole visible page-size selector. Default props remain
`true` for stand-alone components. Both visible pagers retain page navigation.

## DS4 Button and Icon contract

- **One palette and interaction model:** use `AppButton`/`AppIconButton` for new
  components and `src/styles/buttons.css` as the source of all native action
  styles. Existing `primary-button`/`secondary-button` markup retains its
  business handlers and uses the same tokens, focus ring, hover, pressed and
  disabled states.
- **Roles:** Primary for the single main action; Secondary for cancellation,
  reload and navigation; `danger` for destructive primary actions; `danger-outline`
  for secondary destructive actions. `ghost` for low emphasis. Tab/filter-state
  selectors and link buttons are **not** disguised as form action buttons.
- **Semantic icons:** text actions use `data-ds-icon="save|add|refresh|..."` on
  native buttons (decorative CSS mask using currentColor). `AppButton` accepts an
  `icon` prop with the same vocabulary, or an existing `#icon` slot which takes
  precedence. Use an `NIcon` in the supported `#icon` slot of Naive UI `NButton`;
  do **not** add a `::before` decoration to `.n-button` because Naive UI owns its
  internal overlay/focus pseudos. Avoid glyph characters such as `＋` / `↻`.
- **Dimensions:** 32 / 36 / 40px (`sm`/`md`/`lg`); 18px icons with 8px gap;
  icon-only controls use `.ds-icon-control` at 32px and a mandatory `aria-label`
  or the `AppIconButton.label` prop. Text is kept visible for action buttons.
- **Loading:** `AppButton` has a disabled spinner plus `aria-busy`; native
  buttons retain existing dynamic saving labels and disabled state rather than
  changing their async/event semantics. Never replace an action handler in order
  to add an icon or loading state.
- **Color/contrast:** all icon masks inherit `currentColor` so light/dark,
  disabled, hover, pressed and Danger roles use the same text contrast. Never
  hardcode `color="#..."` on `NButton`, including Login.
- **Business safety:** role and UI-only attributes must not change `v-if`,
  `:disabled`, `@click`, `@submit`, form `type`/`form`, PBAC, row actions,
  supplier CAS, `row_version` or unsaved-change protection.
- **Exceptions:** AppShell icon controls and command palette, sortable header
  buttons, tablists, row-link buttons and disclosure/tree toggles keep native
  component semantics. Shared row actions use `.ds-icon-control` through
  `TableRowActions` with the `canMutation` fail-closed checks intact.

### DS4 acceptance

```powershell
pnpm run check:design-system
pnpm run type-check
pnpm run build-only
pnpm exec vitest run src/components/ui/__tests__/AppButton.spec.ts src/components/ui/__tests__/AppDialogHost.spec.ts src/components/lists/__tests__/listComponents.spec.ts src/components/lists/__tests__/listPageSizePolicy.spec.ts
pnpm exec playwright test e2e/ds4-button-states.spec.ts
```

Visual QA: inspect Create/Save/Cancel/Delete and list paging on Price Lists,
Customer/Supplier, Security Users/Policies, Notification Settings/Inbox and
My Workflow in light/dark at both wide and narrow widths. Check focus with Tab,
hover/press, disabled states, loading while saving, keyboard-based pagination,
and that no extra icon appears on Naive UI button overlays.

## DS5 — Final cleanup, dark mode, and accessibility gate

**CSS ownership:** `global.css` is an import-only manifest. `page-layout.css` owns
live general page, dashboard, login and profile layouts; `forms.css`,
`tables.css`, `detail-workspace.css`, `modal.css` and `buttons.css` own their
respective visual contracts. Do not reintroduce `.app-frame`, old `.topbar`,
`.sidebar-collapse` or `.overlay` rules; current shell behavior belongs to
`src/components/app-shell/styles.css` and the dialog portal belongs to
`AppDialogHost`. The responsive 680px `.data-table` width remains in `tables.css`.

**Light/dark:** all feature Vue `<style>` blocks must use `var(--ds-*)` for
colors. The Login **form** follows light/dark canvas/surface/text tokens.
The Login spotlight is a deliberately constant branded panel using documented
`--ds-auth-brand-*` tokens in `tokens.css`, with WCAG AA foreground contrast.
Do not style global `body`, `html`, or `*` from inside routed features, especially
Connectivity; that overrides the entire app when visiting the route. Naive UI
internals still own their focus/border. Branded illustration-only `rgb()` alpha
shadows are allowed when they do not encode text or an interaction state.

**Shared settings tabs:** the Account/System Display/Notifications tabs use
`.ds-settings-tabs` in `detail-workspace.css`; the views still own routing and
PBAC. Their `role="tablist"`, `role="tab"`, `aria-selected` and focus-visible
ring must remain intact. Focus color and selected-tab text color follow the
same theme as the rest of the workspace.

**Reduced motion:** `base.css` sets `--ds-motion-fast` and `--ds-motion-normal`
to `0ms` under `prefers-reduced-motion: reduce`. Future transitions should
consume these tokens rather than hardcoding durations.

**Release gates:**

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm run check:design-system
pnpm run type-check
pnpm run build-only
pnpm exec vitest run src/components/ui/__tests__/AppButton.spec.ts src/components/ui/__tests__/AppDialogHost.spec.ts src/components/lists/__tests__/listComponents.spec.ts src/components/lists/__tests__/listPageSizePolicy.spec.ts
pnpm exec playwright test e2e/ds4-button-states.spec.ts e2e/ds5-final-cleanup.spec.ts --project=chromium
```

Also perform authenticated manual QA on Customer/Supplier detail (nested
unsaved dialogs, Supplier CAS), PBAC Policies/Users, Product hierarchy/UOM,
Price Lists sorting/paging/sticky Actions, Notifications, Workflow, Import,
Profile and Settings. Check actual light/dark, mobile width, scroll/focus,
error/status color, keyboard Tab/Escape and dirty-route confirmation. Source
and isolated CSS tests do **not** prove server/API, permission, or browser
behavior across all routes.

## DS5 HF1 — Stable borders/hover geometry and text-copy selection

- `base.css` owns `box-sizing: border-box` for elements **and** pseudo-elements.
  Do not restate it inside feature styles. Never add border width, padding,
  font-weight, size, or transform on `:hover` / `:focus-visible`. Reserve border
  geometry in the resting state, including transparent `1px` borders on icon
  controls. Use `border-color`, `background-color`, `color`, `outline` or
  `box-shadow` for visual feedback; outlines and shadows do not affect layout.
- `buttons.css` owns `.icon-button` and `.modal-card-button-close` dimensions;
  feature views can adjust semantic colors, but must not override border width
  or padding. A `currentColor` CSS mask is the only Close X icon source.
- `::selection` and `::-moz-selection` are defined once in `base.css`, backed
  by theme-specific `--ds-selection-bg` and `--ds-selection-text` tokens.
  Selection must remain visibly different from `--ds-bg-hover`,
  `--ds-bg-selected`, and `--ds-primary-soft` and keep text contrast >=4.5:1.
  This applies to light/dark and portalled dialogs via the document root.
- `scripts/design-system/hover-selection.test.cjs` scans the **entire src/**
  CSS (global, app shell, and Vue SFC styles). It rejects new CSS ownership
  conflicts and any hover/focus property that changes element layout metrics.
- `e2e/ds5-hf1-hover-selection.spec.ts` uses actual Chromium computed styles
  to compare element boxes before/after hover and focus, then checks the
  computed `::selection` styling and text selection in both palettes.

Run from `frontend/`:

```powershell
pnpm run check:design-system
pnpm run type-check
pnpm run build-only
pnpm exec playwright test e2e/ds5-hf1-hover-selection.spec.ts --project=chromium
```

The isolated fixture tests do not replace authenticated manual checks of Naive
UI form controls, table rows, modal layering and real route-specific content.

### Wide list tables with row actions

Pages that need horizontally scrollable columns and a persistent Actions column
should add `class="list-table-wrap--sticky-actions"` to their shared `ListTable`.
The wrapper sets a 1040px minimum table width and keeps the final column
visible with consistent hover/focus colors in both palettes. Do not duplicate
this sticky border, hover or width styling in page-scoped CSS. Compact pages
continue using the 640px table default.

## DS5 HF4 — Global API error notification box

- Exactly one `<ApiErrorNoticeStack />` is mounted in the application root
  (`App.vue`), and its body-level `<Teleport>` keeps it outside the scrollable
  workspace and all modal content. Do not render a second copy in `AppShell`,
  `AppDialogHost`, or a feature form.
- It is a fixed **top-right box at `z-index: 999`** in both themes, with a
  responsive width, bounded height and scroll when multiple errors occur.
  Retain status, message, error code, request ID, accessible announcement and
  close button. Closing the box must not close an editing dialog.
- Shared `AppDialogHost` gives Naive UI `NModal` a lower layer (`z-index: 900`)
  without touching Escape, mask-close, unsaved-change guards or focus trap.
  The root-level toast must appear **above** the real modal mask. Do not
  increase the toast beyond 999 or move it into modal content again.
- Source tests: `scripts/design-system/customer-sort-notices.test.cjs` and
  `scripts/design-system/global-error-toast.test.cjs`. Runtime tests:
  `src/components/ui/__tests__/ApiErrorNoticeStack.spec.ts`,
  `src/components/ui/__tests__/AppDialogHost.spec.ts`, and
  `e2e/ds5-hf3-sort-notices.spec.ts` (real Naive UI modal in both themes).

## DS5 HF5 — Distinct severity toasts and one modal header Close X

- The only API-notice stack remains body-teleported and fixed at the **top right,
  `z-index: 999`**. Error and warning messages now have different tinted
  surfaces (`--ds-toast-error-bg`, `--ds-toast-warning-bg`), a stable 4px
  severity stripe, and `--ds-shadow-toast`. All colors/shadows are tokens in
  `tokens.css`; the content, request ID, dismiss action and popup layer stay
  unchanged. Do not style individual feature errors as a competing toast.
- The **canonical spelling** is `.modal-card-button-close` (*button*, not
  *buttaon*). Every header Close X, including the Price List item dialog,
  Product Relations dialog and `AppModal`, uses this class. The CSS mask in
  `buttons.css` is the sole X glyph. Do not add `CloseOutline`, `×`, or an
  `icon-button` class to modal header Close buttons; `.icon-button` is still
  valid for table row Create/Edit/Delete actions.
- All modal Close buttons have an accessible label, `type="button"`, and keep
  their existing close handler. The shared CSS reserves border geometry and
  retains keyboard focus styling. `AppDialogHost` still owns NModal focus and
  Escape; changes to Customer/Supplier dirty guards and CAS are prohibited.
- Source gate: `scripts/design-system/modal-close-toast.test.cjs` audits all
  40 labeled header Close buttons and checks toast contrast in both palettes.
  Runtime gates: `src/components/ui/__tests__/AppModalClose.spec.ts` and
  `e2e/ds5-hf3-sort-notices.spec.ts` (two additional theme-aware severity
  scenarios). The five `.at(-1)` assertions in ListTable Vitest were changed
  to `.slice(-1)[0]` to support the project's TypeScript lib target.


## DS5 HF6 — Stable NAlert severity classes (HF5 browser-test fix)

- `NAlert` accepts the `type` prop but does **not** add `n-alert--error` or
  `n-alert--warning` to its rendered element. Those selectors made the HF5
  browser regression fail and prevented Warning from receiving its amber CSS.
- `ApiErrorNoticeStack` assigns application-owned
  `.api-error-notice--error` / `.api-error-notice--warning` classes to the
  real `NAlert` root. The same status expression controls `type`, class, and
  the slotted `NIcon` color. Do not target fictional Naive alert modifier
  classes or `.n-alert-body__icon`; the real wrapper is `.n-alert__icon`.
- The single body-teleported notice still uses `z-index: 999`, above the
  modal. No API normalization, dismissal, PBAC, or modal behavior changed.
- Regression: `scripts/design-system/modal-close-toast.test.cjs`,
  `ApiErrorNoticeStack.spec.ts`, and the two severity tests in
  `e2e/ds5-hf3-sort-notices.spec.ts` (Light and Dark).

## DS5 HF9 — One responsive query toolbar contract

Use `ListQueryControls` for list and table searches. Its internal `list-query-controls__grid`
contains every search/filter field (including feature-supplied `#filters` slots), and
`list-query-controls__actions` contains Apply, Reset and optional feature actions.
Place Refresh in `<template #actions>` as `type="button"`, not beside the form.
Do not implement a local filter toolbar flex/grid or add viewport-only media queries.

The shared form is an inline-size container (`ds-list-query`); its field grid has
panel-based breakpoints at 1280px (dense cases), 1060px, 860px, and 490px.
When supplying extra slot fields, pass `:extra-field-count` and use
`list-query-controls__field` (and `list-query-controls__field--wide` if needed).
Condition extra fields on the feature's existing permission check so a hidden field
does not reserve an empty track.

Bespoke filter panels (History, Inbox, My Workflow and Auto Numbering) should use
`ds-filter-container` > `ds-filter-form` > `ds-filter-fields` followed by
`ds-filter-actions`. They retain their own input state and event handlers; do not
change quick-filter tabs or a modal's data-entry form into a search toolbar.

Regression commands:

```powershell
pnpm run check:design-system
pnpm run type-check
pnpm run build-only
pnpm exec playwright test e2e/ds5-hf7-supplier-toolbar.spec.ts e2e/ds5-hf9-unified-query-grid.spec.ts --project=chromium
```

### Action icon contract (DS5 HF15)

- All action buttons must use semantic `data-ds-icon` on their native `<button>` DOM. The supported verbs include `save`, `close`, `edit`, `changestate`, `activate`, `deactivate`, `refresh`, `add`, `apply`, `cancel`, `reset`, `delete`, `view`, `retry`, and others in `buttons.css`.
- `AppButton` uses its `icon` prop (rather than a manual `data-ds-icon` on the component) so the rendered native button receives the metadata and `loading` still suppresses the icon.
- Naive `NButton` accepts `data-ds-icon` forwarded to its button root; `buttons.css` renders the mask inside `.n-button__content`. Remove the old `#icon` slot to avoid two icons.
- Modal close controls retain `.modal-card-button-close`, `aria-label` and `type=button` and now also set `data-ds-icon="close"`. Their X comes from the **same** registry, never a second CSS SVG.
- Direct binary toggles use `activate` (PowerOutline) when the row is inactive and `deactivate` (BanOutline) when the row is active. The icon represents the action the button will perform, matching its accessible label; it must update reactively after a successful state change. `TableRowActions` owns this rule for list rows, and Security User detail applies the same rule to `selected.active`. The button surface communicates the **current record status** independently of the next-action icon: `data-ds-state="active"` uses `--ds-success` / `--ds-success-contrast` (green); `data-ds-state="inactive"` uses `--ds-bg-subtle` / `--ds-text-secondary` (light neutral gray in Light Mode). The shared `.ds-state-toggle` variants are also used by the Security User detail action. Hover, focus and disabled states remain part of the shared button contract. Workflow/supplier multi-state transitions continue using `changestate`. Preserve all PBAC guards, click handlers, unsaved changes and CAS; do not add a second SVG or `NIcon` inside a semantic button.
- Do not add decorative action icons to tab controls, tree disclosure arrows, shell navigation, user avatars or language/theme controls.
