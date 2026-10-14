# ERP workspace breadcrumbs (P1 UI-only change)

## Contract

- One breadcrumb `<nav>` belongs to `AppShell.vue` and renders before the active `RouterView`.
  The unauthenticated `/login` page has no AppShell and therefore no breadcrumb.
- `route.meta.labelKey` supplies the **current** page label. Every authenticated route
  declares `breadcrumbTrail` with its **ancestors** in `src/router/index.ts`.
  The ancestor fragments live in `src/router/breadcrumbs.ts`.
- Every trail starts with the dashboard root. Dashboard itself is a single current crumb;
  404 uses a translated page label, never a guessed URL segment.
- Breadcrumb group labels are presentation-only (no fake clickable URLs). Parent
  navigation is allowed only for a real route and a present Base Permission hint.
  The backend still decides all final authorization and visibility results.
- A clickable breadcrumb is a `RouterLink`, so the existing router dirty-form guard
  runs on navigation. No independent history, router, API, preference, or cache exists.
- The current page uses `aria-current="page"`, and `<nav>` has a localized label.
  Breadcrumbs respond to route/locale/permission changes and support light/dark themes.
- Do not store business data or translate resource identifiers in breadcrumbs.

## Hierarchy

| Family | Example |
| --- | --- |
| Dashboard | Overview |
| Account | Overview > Account > Personal profile > My Display Preferences |
| Organization | Overview > Organization > Organizations and foundation data |
| Administrative locations | Overview > Organization > Administrative locations > Wards/Communes |
| People | Overview > Organization > People > Employees |
| System/authorization | Overview > System > Authorization > Users and direct permissions |
| System/configuration | Overview > System > Configuration > Display Settings |
| Product | Overview > Products > Product groups |
| Product detail | Overview > Products > Edit product |
| Import job detail | Overview > Products > Product Import Jobs > Product Import Job Detail |
| Unknown authenticated route | Overview > Page not found |

Only a breadcrumb with an actual known route becomes a clickable link; *Overview* and
*Products* may be links on descendant pages, and *Product Import Jobs* is a link on job detail.

## Extend a new page

1. Add the route with its existing canonical `meta.labelKey`.
2. Set `meta.breadcrumbTrail` to one of the `breadcrumbTrails` entries, or define a
   meaningful typed ancestor trail in `src/router/breadcrumbs.ts`.
3. Give protected clickable ancestor links a `permission` hint. Do not run PBAC in UI.
4. Add Vietnamese/English group strings to `src/i18n/index.ts` only if necessary.
5. Do not render another `.breadcrumbs` block inside feature views.
6. Run `pnpm run type-check`, `pnpm run lint`,
   `pnpm exec vitest run src/router/__tests__/breadcrumbs.spec.ts` and
   `pnpm exec playwright test e2e/fe001-shell.spec.ts --project=chromium`.

## Scope and compatibility

Presentation-only. No backend/API/DB/permission schema changes. Existing workspace
multi-tab, 5 sidebar modes, favorites, recent, locale, and dirty-route guard are reused.
The changes were prepared against `erp_v1.1.1.zip` after applying
`AppShell_refactor_v1.1.1.patch`; they were **not** fetched from remote commit `98ad72b`.
Before applying to a newer worktree, run `git apply --check` and review conflicts.
