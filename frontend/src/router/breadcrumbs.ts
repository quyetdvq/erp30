/**
 * Presentation-only breadcrumb hierarchy for the authenticated workspace.
 *
 * A trail contains ancestors only; the final page label always comes from the
 * route's existing meta.labelKey. Group nodes are not fake navigation targets.
 * Clickable ancestors require an actual route and (when protected) a Base
 * Permission hint. The backend remains the authorization authority.
 */
export type BreadcrumbNode = {
  readonly labelKey: string
  readonly to?: string
  readonly permission?: string
}

type BreadcrumbRouteMeta = {
  labelKey?: unknown
  breadcrumbTrail?: readonly BreadcrumbNode[]
}

const account = [{ labelKey: 'breadcrumbs.account' }] as const
const organization = [{ labelKey: 'shell.modes.organization' }] as const
const system = [{ labelKey: 'shell.modes.system' }] as const
const products = [
  { labelKey: 'nav.products', to: '/products', permission: 'product.view' },
] as const

/** Reusable ancestors keep the router's information architecture explicit. */
export const breadcrumbTrails = {
  account,
  displayPreferences: [...account, { labelKey: 'shell.profile', to: '/profile' }],
  organization,
  locations: [...organization, { labelKey: 'breadcrumbs.locations' }],
  people: [...organization, { labelKey: 'breadcrumbs.people' }],
  system,
  security: [...system, { labelKey: 'breadcrumbs.security' }],
  configuration: [...system, { labelKey: 'breadcrumbs.configuration' }],
  products,
  customers: [...organization, { labelKey: 'nav.customers', to: '/customers', permission: 'customer.view' }],
  importJobDetail: [
    ...products,
    {
      labelKey: 'nav.productImportJobs',
      to: '/products/import-jobs',
      permission: 'import_job.view',
    },
  ],
} as const satisfies Record<string, readonly BreadcrumbNode[]>

export function breadcrumbsForRoute(
  meta: BreadcrumbRouteMeta,
  hasBasePermissionKey: (key: string) => boolean,
): BreadcrumbNode[] {
  const current =
    typeof meta.labelKey === 'string' && meta.labelKey.length > 0
      ? meta.labelKey
      : 'errors.status.404'

  if (current === 'nav.dashboard') return [{ labelKey: current }]

  const ancestors = (meta.breadcrumbTrail ?? []).map(({ labelKey, to, permission }) => ({
    labelKey,
    // A missing/stale Base Permission hint must never create a privileged link.
    ...(to && (!permission || hasBasePermissionKey(permission)) ? { to } : {}),
  }))

  return [{ labelKey: 'nav.dashboard', to: '/' }, ...ancestors, { labelKey: current }]
}

declare module 'vue-router' {
  interface RouteMeta {
    labelKey?: string
    breadcrumbTrail?: readonly BreadcrumbNode[]
  }
}
