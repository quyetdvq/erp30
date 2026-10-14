import { describe, expect, it, vi } from 'vitest'

// The route catalog can be tested without mounting the real shell (or its assets).
vi.mock('@/components/AppShell.vue', () => ({ default: {} }))

import router from '@/router'
import { breadcrumbTrails, breadcrumbsForRoute } from '@/router/breadcrumbs'

const allowsAll = () => true

describe('workspace breadcrumbs', () => {
  it('uses the dashboard as the only current crumb on the root route', () => {
    expect(breadcrumbsForRoute({ labelKey: 'nav.dashboard' }, allowsAll)).toEqual([
      { labelKey: 'nav.dashboard' },
    ])
  })

  it('uses translated route keys and real ancestor destinations for nested pages', () => {
    expect(
      breadcrumbsForRoute(
        {
          labelKey: 'nav.productImportJobDetail',
          breadcrumbTrail: breadcrumbTrails.importJobDetail,
        },
        allowsAll,
      ),
    ).toEqual([
      { labelKey: 'nav.dashboard', to: '/' },
      { labelKey: 'nav.products', to: '/products' },
      { labelKey: 'nav.productImportJobs', to: '/products/import-jobs' },
      { labelKey: 'nav.productImportJobDetail' },
    ])
  })

  it('does not expose protected ancestor links without the Base Permission hint', () => {
    const crumbs = breadcrumbsForRoute(
      { labelKey: 'nav.productImportJobDetail', breadcrumbTrail: breadcrumbTrails.importJobDetail },
      (permission) => permission === 'import_job.view',
    )

    expect(crumbs).toEqual([
      { labelKey: 'nav.dashboard', to: '/' },
      { labelKey: 'nav.products' },
      { labelKey: 'nav.productImportJobs', to: '/products/import-jobs' },
      { labelKey: 'nav.productImportJobDetail' },
    ])
  })

  it('never creates links for virtual groups or the current page', () => {
    expect(
      breadcrumbsForRoute(
        { labelKey: 'nav.securityUsers', breadcrumbTrail: breadcrumbTrails.security },
        allowsAll,
      ),
    ).toEqual([
      { labelKey: 'nav.dashboard', to: '/' },
      { labelKey: 'shell.modes.system' },
      { labelKey: 'breadcrumbs.security' },
      { labelKey: 'nav.securityUsers' },
    ])
  })

  it('uses the localized not-found label without reflecting the raw unknown URL', () => {
    expect(breadcrumbsForRoute({ labelKey: 'errors.status.404' }, allowsAll)).toEqual([
      { labelKey: 'nav.dashboard', to: '/' },
      { labelKey: 'errors.status.404' },
    ])
  })

  it('keeps province and ward breadcrumbs distinct despite a shared view component', () => {
    const provinces = router.resolve('/settings/locations/provinces')
    const wards = router.resolve('/settings/locations/wards')

    expect(breadcrumbsForRoute(provinces.meta, allowsAll).slice(-1)[0]?.labelKey).toBe(
      'nav.locations',
    )
    expect(breadcrumbsForRoute(wards.meta, allowsAll).slice(-1)[0]?.labelKey).toBe('nav.wards')
  })

  it('covers every authenticated application screen through route metadata', () => {
    const routes = router.getRoutes().filter((entry) => entry.name && !entry.meta.public)

    expect(routes.length).toBeGreaterThan(20)

    // Report missing route names directly without conditional assertions.
    const routesWithoutLabels = routes
      .filter((entry) => typeof entry.meta.labelKey !== 'string' || !entry.meta.labelKey)
      .map((entry) => String(entry.name))
    expect(routesWithoutLabels).toEqual([])

    const routesWithoutTrails = routes
      .filter((entry) => !['dashboard', 'not-found', 'products'].includes(String(entry.name)))
      .filter((entry) => !entry.meta.breadcrumbTrail?.length)
      .map((entry) => String(entry.name))
    expect(routesWithoutTrails).toEqual([])

    for (const entry of routes) {
      const breadcrumbs = breadcrumbsForRoute(entry.meta, allowsAll)
      const current = breadcrumbs[breadcrumbs.length - 1]
      expect(current?.labelKey).toBe(entry.meta.labelKey)
      expect(current?.to).toBeUndefined()
    }
  })
})
