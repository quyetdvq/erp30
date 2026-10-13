import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { useWorkspaceStore } from '@/stores/workspace'

describe('workspace shell state', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    window.localStorage.clear()
  })

  it('isolates sidebar preferences by authenticated user', () => {
    const store = useWorkspaceStore()
    store.activateUser(7)
    store.setSidebarMode('favorites')
    store.setSidebarCollapsed(true)
    store.setSidebarWidth(480)
    store.setThemeMode('dark')

    store.activateUser(8)
    expect(store.sidebarMode).toBe('menu')
    expect(store.sidebarCollapsed).toBe(false)
    expect(store.sidebarWidth).toBe(288)
    expect(store.themeMode).toBe('light')

    store.activateUser(7)
    expect(store.sidebarMode).toBe('favorites')
    expect(store.sidebarCollapsed).toBe(true)
    expect(store.sidebarWidth).toBe(420)
    expect(store.themeMode).toBe('dark')
  })

  it('separates manual and form-owned dirty state, and clears both on user switch', () => {
    const store = useWorkspaceStore()
    store.activateUser(7)
    const first = Symbol('first')
    const second = Symbol('second')
    store.markDirtySource('organizations', first, true)
    store.openTab({
      key: 'organizations',
      path: '/organizations',
      labelKey: 'nav.organizations',
      closable: true,
    })
    expect(store.isDirty('organizations')).toBe(true)
    store.markDirtySource('organizations', second, true)
    store.markDirtySource('organizations', first, false)
    expect(store.tabs[0]?.dirty).toBe(true)
    store.markDirty('organizations')
    store.markDirtySource('organizations', second, false)
    expect(store.isDirty('organizations')).toBe(true)
    store.markDirty('organizations', false)
    expect(store.isDirty('organizations')).toBe(false)
    store.markDirtySource('organizations', second, true)
    store.activateUser(8)
    expect(store.isDirty('organizations')).toBe(false)
    expect(store.tabs).toHaveLength(0)
  })

  it('deduplicates tabs and protects dirty close', () => {
    const store = useWorkspaceStore()
    store.activateUser(7)
    store.openTab({
      key: 'organizations',
      path: '/organizations',
      labelKey: 'nav.organizations',
      closable: true,
    })
    store.openTab({
      key: 'organizations',
      path: '/organizations',
      labelKey: 'nav.organizations',
      closable: true,
    })
    expect(store.tabs).toHaveLength(1)

    store.markDirty('organizations')
    expect(store.closeTab('organizations', () => false)).toBeNull()
    expect(store.tabs).toHaveLength(1)
    expect(store.closeTab('organizations', () => true)).toBeNull()
    expect(store.tabs).toHaveLength(1)
  })

  it('persists Favorites independently of Recent and isolates them by user', () => {
    const store = useWorkspaceStore()
    store.activateUser(7)
    store.recordRecent('/organizations')
    store.recordRecent('/products/price-lists')
    store.recordRecent('/organizations')
    expect(store.recent).toEqual(['/organizations', '/products/price-lists'])
    expect(store.favorites).toEqual([])

    store.toggleFavorite('/products/price-lists')
    store.toggleFavorite('/organizations')
    expect(store.favorites).toEqual(['/organizations', '/products/price-lists'])
    store.toggleFavorite('/organizations')
    expect(store.favorites).toEqual(['/products/price-lists'])
    expect(store.recent).toHaveLength(2)

    store.activateUser(8)
    expect(store.favorites).toEqual([])
    expect(store.recent).toEqual([])
    store.toggleFavorite('/people/departments')
    store.recordRecent('/people/departments')

    store.activateUser(7)
    expect(store.favorites).toEqual(['/products/price-lists'])
    expect(store.recent).toEqual(['/organizations', '/products/price-lists'])
    store.clearSession()
    expect(store.favorites).toEqual([])
    expect(store.recent).toEqual([])
    store.activateUser(8)
    expect(store.favorites).toEqual(['/people/departments'])
    expect(store.recent).toEqual(['/people/departments'])
  })

  it('keeps Recent after closing a tab, limits history to 20 and ignores unsafe shortcuts', () => {
    const store = useWorkspaceStore()
    store.activateUser(7)
    store.openTab({
      key: 'organizations',
      path: '/organizations',
      labelKey: 'nav.organizations',
      closable: true,
    })
    store.openTab({
      key: 'dashboard',
      path: '/',
      labelKey: 'nav.dashboard',
      closable: false,
    })
    store.recordRecent('/organizations')
    store.closeTab('organizations')
    expect(store.tabs.map((tab) => tab.key)).toEqual(['dashboard'])
    expect(store.recent).toEqual(['/organizations'])

    store.toggleFavorite('https://untrusted.example')
    store.toggleFavorite('/organizations?token=secret')
    store.recordRecent('//external.example')
    store.recordRecent('/')
    expect(store.favorites).toEqual([])
    expect(store.recent).toEqual(['/organizations'])

    for (let index = 0; index < 24; index++) store.recordRecent(`/people/${index}`)
    expect(store.recent).toHaveLength(20)
    expect(store.recent[0]).toBe('/people/23')
    expect(store.recent[store.recent.length - 1]).toBe('/people/4')
  })

  it('discards corrupt or unexpected persisted shortcut values', () => {
    window.localStorage.setItem(
      'ui.sidebar.favorites.7',
      JSON.stringify(['/organizations', '/organizations', '/ok?query=1', 42, null]),
    )
    window.localStorage.setItem('ui.sidebar.recent.7', '{bad json')
    const store = useWorkspaceStore()
    store.activateUser(7)
    expect(store.favorites).toEqual(['/organizations'])
    expect(store.recent).toEqual([])
  })
})
