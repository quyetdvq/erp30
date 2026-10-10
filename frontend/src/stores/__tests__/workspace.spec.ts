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
})
