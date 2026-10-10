import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

export const SIDEBAR_MODES = ['menu', 'favorites', 'recent', 'system', 'organization'] as const
export type SidebarMode = (typeof SIDEBAR_MODES)[number]
export type ThemeMode = 'light' | 'dark'

export type WorkspaceTab = {
  key: string
  path: string
  labelKey: string
  closable: boolean
  dirty: boolean
}

export type WorkspacePreferences = {
  sidebarMode: SidebarMode
  sidebarCollapsed: boolean
  sidebarWidth: number
  themeMode: ThemeMode
}

const DEFAULT_PREFERENCES: WorkspacePreferences = {
  sidebarMode: 'menu',
  sidebarCollapsed: false,
  sidebarWidth: 288,
  themeMode: 'light',
}

const MIN_SIDEBAR_WIDTH = 240
const MAX_SIDEBAR_WIDTH = 420
const STORAGE_PREFIX = 'ui.workspace.'

function storageKey(userId: string): string {
  return `${STORAGE_PREFIX}${userId}`
}

function normalizePreferences(value: unknown): WorkspacePreferences {
  if (!value || typeof value !== 'object') return { ...DEFAULT_PREFERENCES }
  const candidate = value as Partial<WorkspacePreferences>
  const mode = SIDEBAR_MODES.includes(candidate.sidebarMode as SidebarMode)
    ? (candidate.sidebarMode as SidebarMode)
    : DEFAULT_PREFERENCES.sidebarMode
  const width = Number(candidate.sidebarWidth)
  const themeMode = candidate.themeMode === 'dark' ? 'dark' : DEFAULT_PREFERENCES.themeMode
  return {
    sidebarMode: mode,
    sidebarCollapsed: candidate.sidebarCollapsed === true,
    sidebarWidth: Number.isFinite(width)
      ? Math.min(MAX_SIDEBAR_WIDTH, Math.max(MIN_SIDEBAR_WIDTH, width))
      : DEFAULT_PREFERENCES.sidebarWidth,
    themeMode,
  }
}

export const useWorkspaceStore = defineStore('workspace', () => {
  const activeUserId = ref<string | null>(null)
  const preferences = ref<WorkspacePreferences>({ ...DEFAULT_PREFERENCES })
  const tabs = ref<WorkspaceTab[]>([])
  // Non-persistent form instances tracked independently per route/tab.
  const dirtySources = new Map<string, Set<symbol>>()
  const manuallyDirty = new Set<string>()

  function syncDirty(key: string): void {
    const tab = tabs.value.find((candidate) => candidate.key === key)
    if (tab) tab.dirty = manuallyDirty.has(key) || (dirtySources.get(key)?.size ?? 0) > 0
  }

  function markDirtySource(key: string, source: symbol, dirty: boolean): void {
    if (dirty) {
      const sources = dirtySources.get(key) ?? new Set<symbol>()
      sources.add(source)
      dirtySources.set(key, sources)
    } else {
      const sources = dirtySources.get(key)
      sources?.delete(source)
      if (sources?.size === 0) dirtySources.delete(key)
    }
    syncDirty(key)
  }

  const sidebarMode = computed(() => preferences.value.sidebarMode)
  const sidebarCollapsed = computed(() => preferences.value.sidebarCollapsed)
  const sidebarWidth = computed(() => preferences.value.sidebarWidth)
  const themeMode = computed(() => preferences.value.themeMode)

  function persistPreferences(): void {
    if (!activeUserId.value || typeof window === 'undefined') return
    window.localStorage.setItem(storageKey(activeUserId.value), JSON.stringify(preferences.value))
  }

  function activateUser(userId: number | string): void {
    const normalized = String(userId).trim()
    if (!normalized) return
    dirtySources.clear()
    manuallyDirty.clear()
    activeUserId.value = normalized
    let stored: unknown = null
    if (typeof window !== 'undefined') {
      try {
        stored = JSON.parse(window.localStorage.getItem(storageKey(normalized)) || 'null')
      } catch {
        stored = null
      }
    }
    preferences.value = normalizePreferences(stored)
    tabs.value = []
  }

  function clearSession(): void {
    dirtySources.clear()
    manuallyDirty.clear()
    activeUserId.value = null
    preferences.value = { ...DEFAULT_PREFERENCES }
    tabs.value = []
  }

  function setSidebarMode(mode: SidebarMode): void {
    if (!SIDEBAR_MODES.includes(mode)) return
    preferences.value = { ...preferences.value, sidebarMode: mode }
    persistPreferences()
  }

  function setSidebarCollapsed(collapsed: boolean): void {
    preferences.value = { ...preferences.value, sidebarCollapsed: collapsed }
    persistPreferences()
  }

  function setSidebarWidth(width: number): void {
    if (!Number.isFinite(width)) return
    preferences.value = {
      ...preferences.value,
      sidebarWidth: Math.min(MAX_SIDEBAR_WIDTH, Math.max(MIN_SIDEBAR_WIDTH, Math.round(width))),
    }
    persistPreferences()
  }

  function setThemeMode(mode: ThemeMode): void {
    preferences.value = { ...preferences.value, themeMode: mode }
    persistPreferences()
  }

  function openTab(tab: Omit<WorkspaceTab, 'dirty'> & { dirty?: boolean }): WorkspaceTab {
    const existing = tabs.value.find((candidate) => candidate.key === tab.key)
    if (existing) return existing
    if (tab.dirty) manuallyDirty.add(tab.key)
    const next: WorkspaceTab = {
      ...tab,
      dirty: manuallyDirty.has(tab.key) || (dirtySources.get(tab.key)?.size ?? 0) > 0,
    }
    tabs.value = [...tabs.value, next]
    return next
  }

  function markDirty(key: string, dirty = true): void {
    if (dirty) manuallyDirty.add(key)
    else manuallyDirty.delete(key)
    syncDirty(key)
  }

  function isDirty(key: string): boolean {
    return manuallyDirty.has(key) || (dirtySources.get(key)?.size ?? 0) > 0
  }

  function closeTab(key: string, confirmDirty?: (tab: WorkspaceTab) => boolean): string | null {
    const index = tabs.value.findIndex((tab) => tab.key === key)
    if (index < 0) return null
    const target = tabs.value[index]!
    if (target.dirty && confirmDirty && !confirmDirty(target)) return null
    const remaining = tabs.value.filter((tab) => tab.key !== key)
    if (remaining.length > 0) {
      dirtySources.delete(key)
      manuallyDirty.delete(key)
      tabs.value = remaining
    }
    const fallback = remaining[index - 1] || remaining[index] || remaining[0]
    return fallback?.path || null
  }

  return {
    activeUserId,
    preferences,
    tabs,
    sidebarMode,
    sidebarCollapsed,
    sidebarWidth,
    themeMode,
    activateUser,
    clearSession,
    setSidebarMode,
    setSidebarCollapsed,
    setSidebarWidth,
    setThemeMode,
    openTab,
    markDirty,
    markDirtySource,
    isDirty,
    closeTab,
    MIN_SIDEBAR_WIDTH,
    MAX_SIDEBAR_WIDTH,
  }
})
