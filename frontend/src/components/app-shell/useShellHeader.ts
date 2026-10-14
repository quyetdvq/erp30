import { computed, h, ref, type ComputedRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import {
  CloseOutline,
  MoonOutline,
  PersonCircleOutline,
  SettingsOutline,
  SunnyOutline,
} from '@vicons/ionicons5'
import { useRouter } from 'vue-router'

import { setLocale } from '@/i18n'
import { clearApiErrors } from '@/services/errorBoundary'
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'

// Header state belongs to the shell instance; it is not persisted in workspace preferences.
export function useShellHeader(currentTabKey: ComputedRef<string>) {
  const auth = useAuthStore()
  const workspace = useWorkspaceStore()
  const router = useRouter()
  const { t, locale } = useI18n()

  const userMenuOpen = ref(false)
  const notificationsOpen = ref(false)
  const notificationsRead = ref(false)

  const icon = (component: object) => () => h(NIcon, null, { default: () => h(component) })

  const localeOptions = [
    { label: 'Tiếng Việt', key: 'vi' },
    { label: 'English', key: 'en' },
  ]

  const userOptions = computed(() => [
    { label: t('shell.profile'), key: 'profile', icon: icon(PersonCircleOutline) },
    {
      label: t('shell.displayPreferences'),
      key: 'display-preferences',
      icon: icon(SettingsOutline),
    },
    { label: t('shell.signOut'), key: 'signOut', icon: icon(CloseOutline) },
  ])

  const localeLabel = computed(() => (locale.value === 'vi' ? 'Tiếng Việt' : 'English'))
  const themeIcon = computed(() => (workspace.themeMode === 'dark' ? SunnyOutline : MoonOutline))
  const unreadNotifications = computed(() =>
    notificationsRead.value ? 0 : notificationItems.value.length,
  )
  const notificationItems = computed(() => [
    {
      key: 'workspace',
      title: t('shell.notificationWorkspace'),
      detail: t('shell.notificationWorkspaceDetail'),
    },
    {
      key: 'security',
      title: t('shell.notificationSecurity'),
      detail: t('shell.notificationSecurityDetail'),
    },
  ])

  function handleLocale(value: string): void {
    if (value === 'vi' || value === 'en') setLocale(value)
  }

  function toggleTheme(): void {
    workspace.setThemeMode(workspace.themeMode === 'dark' ? 'light' : 'dark')
  }

  function markNotificationsRead(): void {
    notificationsRead.value = true
  }

  function handleUserAction(key: string): void {
    userMenuOpen.value = false
    if (key === 'profile') void router.push('/profile')
    if (key === 'display-preferences') void router.push('/profile/display-preferences')
    if (key === 'signOut') void signOut()
  }

  async function signOut(): Promise<void> {
    if (workspace.isDirty(currentTabKey.value)) {
      const confirmed =
        typeof window !== 'undefined' && typeof window.confirm === 'function'
          ? window.confirm(t('shell.dirtyCloseConfirm'))
          : false
      if (!confirmed) return
    }
    await auth.logout()
    clearApiErrors()
    void router.push('/login')
  }

  return {
    auth,
    userMenuOpen,
    notificationsOpen,
    notificationsRead,
    localeOptions,
    userOptions,
    localeLabel,
    themeIcon,
    unreadNotifications,
    notificationItems,
    handleLocale,
    toggleTheme,
    markNotificationsRead,
    handleUserAction,
  }
}
