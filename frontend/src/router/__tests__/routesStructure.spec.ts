import { describe, expect, it, vi } from 'vitest'
import type { RouteRecordRaw } from 'vue-router'

import { messages } from '@/i18n'
import { routes } from '@/router/routes'

// Route metadata tests must not evaluate the application shell or its public assets.
vi.mock('@/components/AppShell.vue', () => ({
  default: { name: 'AppShellTestStub' },
}))

function flatten(tree: RouteRecordRaw[]): RouteRecordRaw[] {
  return tree.flatMap((route) => [
    route,
    ...flatten('children' in route && Array.isArray(route.children) ? route.children : []),
  ])
}

function label(locale: 'vi' | 'en', key: string): unknown {
  return key.split('.').reduce<unknown>((current, segment) => {
    if (!current || typeof current !== 'object') return undefined
    return (current as Record<string, unknown>)[segment]
  }, messages[locale])
}

describe('named routes and lazy UI pages', () => {
  it('preserves the 29 named routes, distinct names and translation keys', () => {
    const named = flatten(routes).filter((route) => route.name)
    expect(named).toHaveLength(29)
    expect(new Set(named.map((route) => route.name)).size).toBe(named.length)
    for (const route of named) {
      if (!route.meta?.labelKey) continue
      expect(typeof label('vi', String(route.meta.labelKey))).toBe('string')
      expect(typeof label('en', String(route.meta.labelKey))).toBe('string')
    }
  })

  it('keeps specific product paths ahead of the parameterized editor', () => {
    const parent = routes.find((route) => route.path === '/')
    const children =
      parent && 'children' in parent && Array.isArray(parent.children) ? parent.children : []
    const paths = children.map((child) => child.path)
    expect(paths.indexOf('products/new')).toBeLessThan(paths.indexOf('products/:id'))
    expect(paths.indexOf('products/import-jobs/:id')).toBeLessThan(
      paths.indexOf('products/import-jobs'),
    )
    expect(children[children.length - 1]?.name).toBe('not-found')
    expect(children.find((route) => route.name === 'products-edit')?.component).toEqual(
      expect.any(Function),
    )
  })
})
