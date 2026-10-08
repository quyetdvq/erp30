# UI table action and status rule

This implementation rule applies to every Vue data table in DinkERP.

## Status

- A `status` column renders `TableStatus` from `frontend/src/components/lists`.
- `ACTIVE` uses a green check-circle icon.
- `INACTIVE` uses an amber inactive icon and `CLOSED` uses a neutral closed icon.
- The icon always has an i18n-derived `title`, `aria-label`, and screen-reader text. Color is not the only status signal.

## Row actions

- Render row actions with `TableRowActions` from `frontend/src/components/lists`.
- Use only actions supported by the screen's real workflow: `showView`, `showEdit`, and/or `showToggle`.
- Actions are icon-only, with an i18n label exposed through `title` and `aria-label`.
- `showToggle` maps to a ban icon for deactivation and a power icon for activation; it must preserve the existing authorization and mutation handler.
- `showView` opens the shared read-only `TableDetailModal` populated from the selected row; it must not mutate data or bypass authorization.

New tables must use these primitives instead of reintroducing text status pills or text/emoji row-action links.

Implementation note: this is a Project operational UI rule, not an amendment to the seven P1 baseline architecture documents. When a screen explicitly defines an incompatible workflow, report it for UI authority reconciliation rather than inventing a third behavior.
