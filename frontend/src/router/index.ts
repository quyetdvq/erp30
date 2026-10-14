import { createRouter, createWebHistory } from 'vue-router'

import { routes } from '@/router/routes'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to, from) => {
  const auth = useAuthStore()
  const workspace = useWorkspaceStore()
  if (from.fullPath !== to.fullPath && workspace.isDirty(String(from.name || from.path))) {
    const allow =
      typeof window === 'undefined' || typeof window.confirm !== 'function'
        ? true
        : window.confirm(i18n.global.t('shell.dirtyCloseConfirm'))
    if (!allow) return false
    workspace.markDirty(String(from.name || from.path), false)
  }
  await auth.hydrate()
  if (to.meta.public) {
    if (auth.isAuthenticated) return '/'
    return true
  }
  if (!auth.isAuthenticated) return '/login'
  return true
})

export default router
