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
