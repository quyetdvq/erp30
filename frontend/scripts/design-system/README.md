# ERP UI foundation — Phase 1

## Token contract

- All shared palette, spacing, typography, radii, layers and motion values live in `src/styles/tokens.css`.
- **Only** `:root[data-ds-theme='dark']` overrides dark values; `App.vue` synchronizes the document root with the existing authenticated workspace theme. The root scope is mandatory because Naive UI renders modals/popovers via Teleport outside `.app-root`.
- `src/styles/global.css` is the CSS entrypoint. `base.css`, `buttons.css`, `forms.css`, `tables.css`, `modal.css`, `utilities.css` own primitives; do not copy these rules into `.vue` styles.
- `src/styles/naiveTheme.ts` bridges to Naive UI's JS color calculations. Update it when changing corresponding hex tokens. The node gate verifies exact equality.
- Existing `.theme-dark` on AppShell remains as a compatibility class but is not a separate palette.

## Standard primitives (`src/components/ui`)

| Component | Contract |
|---|---|
| `AppButton` | native `button`, variants `primary/secondary/ghost/danger`, sizes `sm/md/lg`, optional icon slot, loading, default `type=button` |
| `AppIconButton` | icon-only with **required** accessible `label`; forwards native click/attrs |
| `AppModal` | `v-model:open`, title, size `sm/md/lg/xl/wide`, header/body/footer slot layout, focus/scroll/Escape delegated to Naive `NModal` |
| `AppConfirmDialog` | controlled open, `confirm/cancel` events, non-destructive default, optional danger/loading; does not replace `window.confirm` until async navigation flow is approved |

Use the existing vi/en translations when invoking buttons/dialogs, and provide explicit aria labels for icon-only actions. Never bypass API-side authorization or business guard just because the UI is standardized.

## Accessibility gates

- Text on shared surfaces is **>=4.5:1** in light and dark (including muted text).
- Semantic text/surface and action foreground/background are **>=4.5:1** in both themes.
- Focus rings and functional borders are **>=3:1** relative to shared surfaces.
- Disabled control appearance is distinguishable; keyboard focus is visible.
- `prefers-reduced-motion` disables new UI transitions.
- Modal focus trapping, scroll lock and Escape use Naive UI primitives; no new home-grown trap.

Run after applying patch:

```powershell
cd frontend
node --test scripts/design-system/design-system.test.cjs
pnpm exec vitest run src/components/ui/__tests__/AppButton.spec.ts
pnpm run type-check
pnpm run build-only
```

## Strict scope / next phases

Phase 1 did not migrate 40 legacy dialogs, 311 button declarations, or all scoped colors.
Phase 2 now migrates **40/40** audited dialogs onto `AppDialogHost`, retires the
legacy `.modal-backdrop` layer and standardizes modal scoped colors. Phase 3–5
still need to address page-level form/table/detail styling and buttons. Do not
claim full application-wide dark-mode acceptance without later phases and browser/E2E testing.

Phase 2: completed at source level (pending browser/E2E acceptance of nested, dirty, CAS). Phase 3: form, table and detail workspace, remove duplicated styles. Phase 4: migrate buttons/icons. Phase 5: remove legacy styles and run visual/light/dark and keyboard regression.

## DS-P2 — Modal/Drawer/Dialog

- Every feature dialog must render through `AppDialogHost` or `AppModal` (the
  latter also uses `AppDialogHost`). **Do not** add a new `NModal`, `Teleport`,
  backdrop, `z-index` or modal card surface in a feature-level stylesheet.
- Preserve feature-specific validation and closing guards; `@close` receives
  Escape/mask requests, but only a feature handler may discard a form.
- Use `size="sm|md|lg|xl|wide"` for different content widths. Use `wide` only for
  large detail workspaces. A nested dialog must be an independent host instance.
- Keep one `<section role="dialog" aria-modal="true" aria-label(ledby)=...>`
  with `class="ds-dialog-surface"`. Header, body, footer, colors, focus rings,
  responsive spacing, overlay mask and reduced motion come from
  `src/styles/modal.css` / `src/styles/tokens.css`.
- Keep status, warning, validation and other feature-specific content semantics,
  but use semantic tokens rather than literal light-only colors.
- Router/window/beforeunload confirmation guards are not replaced by this
  migration. They have synchronous contracts and require a separate async design.
- `pnpm run check:design-system` now runs 10 palette/foundation tests and the
  DS-P2 inventory, overlay, nested/dirty regression gates.

Phase 2 offline gate: `pnpm run check:design-system`. Run UI test once dependencies are installed: `pnpm exec vitest run src/components/ui/__tests__/AppDialogHost.spec.ts`.
