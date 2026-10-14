import { describe, expect, it, vi } from 'vitest'

import {
  ORGANIZATION_NAV_PATHS,
  RECENT_ROUTE_HINTS,
  SYSTEM_NAV_PATHS,
  navItems,
  sidebarModes,
  type NavigationItem,
} from '@/app/navigation'
import { routes } from '@/router/routes'

// Keep menu contract tests isolated from AppShell and its browser-only assets.
vi.mock('@/components/AppShell.vue', () => ({
  default: { name: 'AppShellTestStub' },
}))

function flatten(items: NavigationItem[]): NavigationItem[] {
  return items.flatMap((item) => [item, ...flatten(item.children ?? [])])
}

function getChildRoutes() {
  const root = routes.find((route) => route.path === '/')
  return root && 'children' in root && Array.isArray(root.children) ? root.children : []
}

describe('workspace navigation contract', () => {
  it('keeps every menu item pointing to an existing named route', () => {
    const children = getChildRoutes()
    const available = new Set(children.map((route) => (route.path ? `/${route.path}` : '/')))
    const unknown = flatten(navItems)
      .map((item) => item.to)
      .filter((target) => !available.has(target))

    expect(unknown).toEqual([])
  })

  it('keeps sidebar groups and recent-view hints internally consistent', () => {
    const items = flatten(navItems)
    const paths = new Set(items.map(({ to }) => to))
    const missingPaths = [...SYSTEM_NAV_PATHS, ...ORGANIZATION_NAV_PATHS].filter(
      (path) => !paths.has(path),
    )
    expect(missingPaths).toEqual([])
    const children = getChildRoutes()
    const names = new Set(children.map((route) => String(route.name)))
    const missingRouteNames = [...RECENT_ROUTE_HINTS.keys()].filter(
      (routeName) => !names.has(routeName),
    )
    expect(missingRouteNames).toEqual([])
    expect(sidebarModes.map((mode) => mode.key)).toEqual([
      'menu',
      'favorites',
      'recent',
      'system',
      'organization',
    ])
  })
})
