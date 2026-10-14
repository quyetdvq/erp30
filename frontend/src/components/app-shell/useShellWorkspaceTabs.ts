import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { useWorkspaceStore, type WorkspaceTab } from '@/stores/workspace'

// Keep the route/tab watcher synchronous and immediate, as in the V2 Start snapshot.
export function useShellWorkspaceTabs() {
  const workspace = useWorkspaceStore()
  const route = useRoute()
  const router = useRouter()
  const { t } = useI18n()

  const currentTabKey = computed(() => String(route.name || route.path))

  function tabForRoute(): Omit<WorkspaceTab, 'dirty'> {
    const meta = route.meta as { labelKey?: string; closable?: boolean }
    return {
      key: currentTabKey.value,
      path: route.fullPath,
      labelKey: meta.labelKey || 'nav.dashboard',
      closable: meta.closable !== false && route.name !== 'dashboard',
    }
  }

  watch(
    () => route.fullPath,
    () => {
      workspace.openTab(tabForRoute())
      if (
        route.name &&
        route.name !== 'dashboard' &&
        route.name !== 'not-found' &&
        typeof route.meta.labelKey === 'string'
      )
        workspace.recordRecent(route.path)
    },
    { immediate: true },
  )

  function confirmDirtyClose(tab: WorkspaceTab): boolean {
    if (!tab.dirty || typeof window === 'undefined' || typeof window.confirm !== 'function')
      return true
    return window.confirm(t('shell.dirtyCloseConfirm'))
  }

  function closeTab(key: string): void {
    const nextPath = workspace.closeTab(key, confirmDirtyClose)
    if (nextPath && key === currentTabKey.value) void router.push(nextPath)
  }

  function handleTabChange(key: string | number): void {
    const tab = workspace.tabs.find((candidate) => candidate.key === String(key))
    if (tab) void router.push(tab.path)
  }

  return { currentTabKey, closeTab, handleTabChange }
}
