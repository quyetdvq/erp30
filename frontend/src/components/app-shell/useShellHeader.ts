import { computed, h, onMounted, onUnmounted, ref, watch, type ComputedRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import {
  CloseOutline, MoonOutline, PersonCircleOutline, SettingsOutline, SunnyOutline,
} from '@vicons/ionicons5'
import { useRouter } from 'vue-router'
import { setLocale } from '@/i18n'
import { clearApiErrors } from '@/services/errorBoundary'
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'
import { useNotificationsStore } from '@/stores/notifications'
import { inboxUiEnabled } from '@/features/notifications/featureFlags'

export function useShellHeader(currentTabKey: ComputedRef<string>) {
  const auth = useAuthStore()
  const workspace = useWorkspaceStore()
  const inbox = useNotificationsStore()
  const router = useRouter()
  const { t, locale } = useI18n()

  const userMenuOpen = ref(false)
  const notificationsOpen = ref(false)
  const icon = (component: object) => () => h(NIcon, null, { default: () => h(component) })
  const localeOptions = [
    { label: 'Tiếng Việt', key: 'vi' }, { label: 'English', key: 'en' },
  ]
  const userOptions = computed(() => [
    { label: t('shell.profile'), key: 'profile', icon: icon(PersonCircleOutline) },
    { label: t('shell.displayPreferences'), key: 'display-preferences', icon: icon(SettingsOutline) },
    { label: t('shell.signOut'), key: 'signOut', icon: icon(CloseOutline) },
  ])
  const localeLabel = computed(() => (locale.value === 'vi' ? 'Tiếng Việt' : 'English'))
  const themeIcon = computed(() => (workspace.themeMode === 'dark' ? SunnyOutline : MoonOutline))
  const unreadNotifications = computed(() => inbox.unreadCount)
  const notificationItems = computed(() => inbox.items.slice(0, 5))
  let poll: ReturnType<typeof setInterval> | null = null

  function canPoll(): boolean {
    return inboxUiEnabled && auth.isAuthenticated && typeof document !== 'undefined' && document.visibilityState === 'visible'
  }
  function refreshIfVisible(): void { if (canPoll()) void inbox.refreshSummary() }
  function setPolling(): void {
    if (poll !== null) { clearInterval(poll); poll = null }
    if (!canPoll()) return
    refreshIfVisible()
    poll = setInterval(refreshIfVisible, 60000)
  }
  watch(() => auth.user?.id, () => {
    inbox.clearSession()
    notificationsOpen.value = false
    setPolling()
  })
  watch(notificationsOpen, (open) => {
    if (open && canPoll()) {
      refreshIfVisible()
      void inbox.loadPage('all')
    }
  })
  onMounted(() => {
    if (!inboxUiEnabled) return
    document.addEventListener('visibilitychange', setPolling)
    window.addEventListener('focus', refreshIfVisible)
    setPolling()
  })
  onUnmounted(() => {
    if (poll !== null) clearInterval(poll)
    if (inboxUiEnabled) {
      document.removeEventListener('visibilitychange', setPolling)
      window.removeEventListener('focus', refreshIfVisible)
    }
  })

  function handleLocale(value: string): void {
    if (value === 'vi' || value === 'en') setLocale(value)
  }
  function toggleTheme(): void {
    workspace.setThemeMode(workspace.themeMode === 'dark' ? 'light' : 'dark')
  }
  function openInbox(): void {
    notificationsOpen.value = false
    void router.push('/notifications')
  }
  async function markNotificationsRead(): Promise<void> {
    if (!inboxUiEnabled || inbox.unreadCount === 0) return
    try { await inbox.markAllRead() } catch { /* Error boundary reports server errors. */ }
  }
  function handleUserAction(key: string): void {
    userMenuOpen.value = false
    if (key === 'profile') void router.push('/profile')
    if (key === 'display-preferences') void router.push('/profile/display-preferences')
    if (key === 'signOut') void signOut()
  }
  async function signOut(): Promise<void> {
    if (workspace.isDirty(currentTabKey.value)) {
      const confirmed = typeof window !== 'undefined' && typeof window.confirm === 'function'
        ? window.confirm(t('shell.dirtyCloseConfirm')) : false
      if (!confirmed) return
    }
    await auth.logout()
    clearApiErrors()
    void router.push('/login')
  }
  return {
    auth, inbox, inboxUiEnabled, userMenuOpen, notificationsOpen, localeOptions, userOptions,
    localeLabel, themeIcon, unreadNotifications, notificationItems,
    handleLocale, toggleTheme, markNotificationsRead, openInbox, handleUserAction,
  }
}
