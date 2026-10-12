<script setup lang="ts">
import { computed, h, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  NAlert,
  NBadge,
  NButton,
  NIcon,
  NInput,
  NLayout,
  NLayoutHeader,
  NLayoutSider,
  NMenu,
  NModal,
  NPopover,
  NSpace,
  NTabPane,
  NTabs,
  NDropdown,
  type MenuOption,
} from 'naive-ui'
import {
  AddOutline,
  AlertCircleOutline,
  BusinessOutline,
  ChevronBackOutline,
  ChevronDownOutline,
  ChevronForwardOutline,
  CloseOutline,
  HomeOutline,
  InformationCircleOutline,
  MenuOutline,
  MoonOutline,
  NotificationsOutline,
  PeopleOutline,
  PersonCircleOutline,
  SearchOutline,
  SettingsOutline,
  StarOutline,
  SunnyOutline,
  TimeOutline,
} from '@vicons/ionicons5'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { setLocale, type AppLocale } from '@/i18n'
import { useBasePermission } from '@/composables/useBasePermission'
import { dismissApiError, errorNotices, clearApiErrors } from '@/services/errorBoundary'
import { useAuthStore } from '@/stores/auth'
import {
  SIDEBAR_MODES,
  useWorkspaceStore,
  type SidebarMode,
  type WorkspaceTab,
} from '@/stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const { hasBasePermissionKey } = useBasePermission()

const mobileMenuOpen = ref(false)
const commandOpen = ref(false)
const menuSearch = ref('')
const commandSearch = ref('')
const userMenuOpen = ref(false)
const notificationsOpen = ref(false)
const notificationsRead = ref(false)
const shellElement = ref<HTMLElement | null>(null)
const isResizingSidebar = ref(false)
let resizeFrame: number | null = null
let resizePointerId: number | null = null
let resizeStartX = 0
let resizeStartWidth = 0
let pendingSidebarWidth = 0

const icon = (component: object) => () => h(NIcon, null, { default: () => h(component) })

const sidebarModes = [
  { key: 'menu', labelKey: 'shell.modes.menu', icon: MenuOutline },
  { key: 'favorites', labelKey: 'shell.modes.favorites', icon: StarOutline },
  { key: 'recent', labelKey: 'shell.modes.recent', icon: TimeOutline },
  { key: 'system', labelKey: 'shell.modes.system', icon: SettingsOutline },
  { key: 'organization', labelKey: 'shell.modes.organization', icon: BusinessOutline },
] as const

type NavigationItem = {
  labelKey: string
  to: string
  icon: object
  permission?: string
  children?: NavigationItem[]
  disabled?: boolean
}

const SYSTEM_NAV_PATHS = new Set([
  '/system/display-settings',
  '/security/users',
  '/security/authorization-groups',
  '/security/resources',
  '/security/policies',
  '/security/authorization-logs',
  '/configuration/workflow-states',
  '/configuration/lifecycles',
  '/configuration/auto-numbering',
])
const MAIN_MENU_HIDDEN_PATHS = new Set(['/organizations', ...SYSTEM_NAV_PATHS])
const ORGANIZATION_NAV_PATHS = new Set([
  '/organizations',
  '/settings/locations/provinces',
  '/settings/locations/wards',
  '/people/departments',
  '/people/positions',
  '/people/employees',
])

const navItems: NavigationItem[] = [
  { labelKey: 'nav.dashboard', to: '/', icon: HomeOutline },
  {
    labelKey: 'nav.organizations',
    to: '/organizations',
    icon: BusinessOutline,
    permission: 'organization.view',
  },
  {
    labelKey: 'nav.locations',
    to: '/settings/locations/provinces',
    icon: BusinessOutline,
    permission: 'province.view',
  },
  {
    labelKey: 'nav.wards',
    to: '/settings/locations/wards',
    icon: BusinessOutline,
    permission: 'ward.view',
  },
  {
    labelKey: 'nav.departments',
    to: '/people/departments',
    icon: PeopleOutline,
    permission: 'department.view',
  },
  {
    labelKey: 'nav.positions',
    to: '/people/positions',
    icon: PeopleOutline,
    permission: 'position.view',
  },
  {
    labelKey: 'nav.employees',
    to: '/people/employees',
    icon: PersonCircleOutline,
    permission: 'employee.view',
  },
  {
    labelKey: 'nav.displaySettings',
    to: '/system/display-settings',
    icon: SettingsOutline,
    permission: 'display_setting.view',
  },
  {
    labelKey: 'nav.securityUsers',
    to: '/security/users',
    icon: SettingsOutline,
    permission: 'user.view',
  },
  {
    labelKey: 'nav.authorizationGroups',
    to: '/security/authorization-groups',
    icon: PeopleOutline,
    permission: 'authorization_group.view',
  },
  {
    labelKey: 'nav.resources',
    to: '/security/resources',
    icon: SettingsOutline,
    permission: 'resource.view',
  },
  {
    labelKey: 'nav.policies',
    to: '/security/policies',
    icon: SettingsOutline,
    permission: 'policy.view',
  },
  {
    labelKey: 'nav.authorizationLogs',
    to: '/security/authorization-logs',
    icon: TimeOutline,
    permission: 'authorization_log.view',
  },
  {
    labelKey: 'nav.workflowStates',
    to: '/configuration/workflow-states',
    icon: TimeOutline,
    permission: 'workflow_state.view',
  },
  {
    labelKey: 'nav.lifecycles',
    to: '/configuration/lifecycles',
    icon: TimeOutline,
    permission: 'lifecycle.view',
  },
  {
    labelKey: 'nav.autoNumbering',
    to: '/configuration/auto-numbering',
    icon: SettingsOutline,
    permission: 'resource.configure_numbering',
  },
  {
    labelKey: 'nav.products',
    to: '/products',
    icon: BusinessOutline,
    children: [
      {
        labelKey: 'nav.productGroups',
        to: '/products/groups',
        icon: BusinessOutline,
        permission: 'product_group.view',
      },
      {
        labelKey: 'nav.products',
        to: '/products',
        icon: BusinessOutline,
        permission: 'product.view',
      },
      {
        labelKey: 'nav.productUoms',
        to: '/products/uoms',
        icon: BusinessOutline,
        permission: 'product_uom.view',
      },
      {
        labelKey: 'nav.productPricePolicies',
        to: '/products/price-lists',
        icon: BusinessOutline,
        permission: 'price_list.view',
      },
      {
        labelKey: 'nav.productImport',
        to: '/products/import',
        icon: BusinessOutline,
        permission: 'product.import',
      },
      {
        labelKey: 'nav.productImportJobs',
        to: '/products/import-jobs',
        icon: BusinessOutline,
        permission: 'import_job.view',
      },
    ],
  },
]

const localeOptions = [
  { label: 'Tiếng Việt', key: 'vi' },
  { label: 'English', key: 'en' },
]

const userOptions = computed(() => [
  { label: t('shell.profile'), key: 'profile', icon: icon(PersonCircleOutline) },
  { label: t('shell.displayPreferences'), key: 'display-preferences', icon: icon(SettingsOutline) },
  { label: t('shell.signOut'), key: 'signOut', icon: icon(CloseOutline) },
])

const activeMode = computed(() => workspace.sidebarMode)
const sidebarWidth = computed(() => (workspace.sidebarCollapsed ? 72 : workspace.sidebarWidth))
const currentTabKey = computed(() => String(route.name || route.path))
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
const activeModeLabel = computed(() => {
  const mode = sidebarModes.find((item) => item.key === activeMode.value) || sidebarModes[0]
  return t(mode.labelKey)
})

function visibleNavItems(items: NavigationItem[]): NavigationItem[] {
  return items.flatMap((item) => {
    const children = item.children ? visibleNavItems(item.children) : undefined
    if (item.children && children?.length) return [{ ...item, children }]
    if (item.children) return []
    return !item.permission || hasBasePermissionKey(item.permission) ? [item] : []
  })
}

const allowedNavItems = computed(() => visibleNavItems(navItems))

const modeItems = computed(() => {
  if (activeMode.value === 'favorites' || activeMode.value === 'recent') {
    const tabItems = workspace.tabs
      .map((tab) => ({ labelKey: tab.labelKey, to: tab.path, icon: InformationCircleOutline }))
      .filter((item) => t(item.labelKey).toLowerCase().includes(menuSearch.value.toLowerCase()))
    return tabItems.length > 0 ? tabItems : allowedNavItems.value
  }
  if (activeMode.value === 'system') {
    return allowedNavItems.value.filter((item) => SYSTEM_NAV_PATHS.has(item.to))
  }
  if (activeMode.value === 'organization') {
    return allowedNavItems.value.filter((item) => ORGANIZATION_NAV_PATHS.has(item.to))
  }
  return allowedNavItems.value.filter(
    (item) =>
      !MAIN_MENU_HIDDEN_PATHS.has(item.to) &&
      !ORGANIZATION_NAV_PATHS.has(item.to) &&
      t(item.labelKey).toLowerCase().includes(menuSearch.value.toLowerCase()),
  )
})

function toMenuOption(item: NavigationItem): MenuOption {
  return {
    key: item.to,
    label: t(item.labelKey),
    icon: icon(item.icon),
    disabled: item.disabled,
    children: item.children?.map(toMenuOption),
  }
}

const menuOptions = computed<MenuOption[]>(() => modeItems.value.map(toMenuOption))

const commandOptions = computed(() =>
  visibleNavItems(navItems)
    .flatMap((item) => item.children ?? [item])
    .filter((item) => t(item.labelKey).toLowerCase().includes(commandSearch.value.toLowerCase()))
    .map((item) => ({ key: item.to, label: t(item.labelKey), icon: icon(item.icon) })),
)

function tabForRoute(): Omit<WorkspaceTab, 'dirty'> {
  const meta = route.meta as { labelKey?: string; closable?: boolean }
  return {
    key: String(route.name || route.path),
    path: route.fullPath,
    labelKey: meta.labelKey || 'nav.dashboard',
    closable: meta.closable !== false && route.name !== 'dashboard',
  }
}

watch(
  () => route.fullPath,
  () => workspace.openTab(tabForRoute()),
  { immediate: true },
)

function selectMode(mode: string): void {
  if (SIDEBAR_MODES.includes(mode as SidebarMode)) workspace.setSidebarMode(mode as SidebarMode)
  if (workspace.sidebarCollapsed) workspace.setSidebarCollapsed(false)
}

function handleMenuSelect(key: string): void {
  menuSearch.value = ''
  mobileMenuOpen.value = false
  void router.push(key)
}

function toggleSidebar(): void {
  workspace.setSidebarCollapsed(!workspace.sidebarCollapsed)
}

function clampSidebarWidth(width: number): number {
  return Math.min(workspace.MAX_SIDEBAR_WIDTH, Math.max(workspace.MIN_SIDEBAR_WIDTH, width))
}

function paintSidebarResize(): void {
  resizeFrame = null
  shellElement.value?.style.setProperty('--sidebar-width', `${pendingSidebarWidth}px`)
}

function handleResizeMove(event: PointerEvent): void {
  if (event.pointerId !== resizePointerId) return
  pendingSidebarWidth = clampSidebarWidth(resizeStartWidth + event.clientX - resizeStartX)
  if (resizeFrame === null) resizeFrame = requestAnimationFrame(paintSidebarResize)
}

function stopResize(commit: boolean): void {
  if (resizePointerId === null) return
  window.removeEventListener('pointermove', handleResizeMove)
  window.removeEventListener('pointerup', handleResizeUp)
  window.removeEventListener('pointercancel', handleResizeCancel)
  window.removeEventListener('blur', handleResizeCancel)
  if (resizeFrame !== null) cancelAnimationFrame(resizeFrame)
  resizeFrame = null
  resizePointerId = null
  if (commit) workspace.setSidebarWidth(pendingSidebarWidth)
  else shellElement.value?.style.setProperty('--sidebar-width', `${sidebarWidth.value}px`)
  isResizingSidebar.value = false
}

function handleResizeUp(event: PointerEvent): void {
  if (event.pointerId !== resizePointerId) return
  handleResizeMove(event)
  stopResize(true)
}

function handleResizeCancel(): void {
  stopResize(false)
}

function startResize(event: PointerEvent): void {
  if (workspace.sidebarCollapsed || resizePointerId !== null || event.button !== 0) return
  event.preventDefault()
  resizePointerId = event.pointerId
  resizeStartX = event.clientX
  resizeStartWidth = workspace.sidebarWidth
  pendingSidebarWidth = resizeStartWidth
  isResizingSidebar.value = true
  window.addEventListener('pointermove', handleResizeMove)
  window.addEventListener('pointerup', handleResizeUp)
  window.addEventListener('pointercancel', handleResizeCancel)
  window.addEventListener('blur', handleResizeCancel)
}

function handleResizeKey(event: KeyboardEvent): void {
  if (event.key === 'Home') workspace.setSidebarWidth(workspace.MIN_SIDEBAR_WIDTH)
  if (event.key === 'End') workspace.setSidebarWidth(workspace.MAX_SIDEBAR_WIDTH)
  if (event.key === 'ArrowLeft') workspace.setSidebarWidth(workspace.sidebarWidth - 16)
  if (event.key === 'ArrowRight') workspace.setSidebarWidth(workspace.sidebarWidth + 16)
}

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

function handleLocale(value: string): void {
  if (value === 'vi' || value === 'en') setLocale(value as AppLocale)
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
  const key = String(route.name || route.path)
  if (workspace.isDirty(key)) {
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

function openCommandPalette(): void {
  commandSearch.value = ''
  commandOpen.value = true
}

function openCommandRoute(key: string | number): void {
  handleMenuSelect(String(key))
  commandOpen.value = false
}

function handleCommandKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Enter') return
  const firstCommand = commandOptions.value[0]
  if (!firstCommand) return
  event.preventDefault()
  openCommandRoute(firstCommand.key)
}

function dismissError(id: string): void {
  dismissApiError(id)
}

onBeforeUnmount(handleResizeCancel)
</script>

<template>
  <div
    ref="shellElement"
    class="erp-shell"
    :class="{ 'theme-dark': workspace.themeMode === 'dark', 'shell-resizing': isResizingSidebar }"
    :style="{ '--sidebar-width': `${sidebarWidth}px` }"
  >
    <NLayout class="shell-layout">
      <NLayoutHeader bordered class="shell-header">
        <NSpace align="center" :size="10" class="header-left">
          <RouterLink class="brand" to="/">
            <img class="shell-brand-logo" src="/logo-dinktech.svg" alt="Dinktech" />
          </RouterLink>
          <NButton
            class="mobile-menu-button"
            quaternary
            circle
            :aria-label="t('shell.openMenu')"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <template #icon
              ><NIcon><MenuOutline /></NIcon
            ></template>
          </NButton>
          <NButton class="workspace-search" secondary @click="openCommandPalette">
            <template #icon
              ><NIcon><SearchOutline /></NIcon
            ></template>
            {{ t('shell.searchWorkspace') }}<kbd>/</kbd>
          </NButton>
        </NSpace>
        <NSpace align="center" :size="6" class="header-actions">
          <NPopover v-model:show="notificationsOpen" trigger="click" placement="bottom-end">
            <template #trigger>
              <NBadge :value="unreadNotifications" :max="99" type="error">
                <NButton quaternary circle :aria-label="t('shell.notifications')">
                  <template #icon
                    ><NIcon><NotificationsOutline /></NIcon
                  ></template>
                </NButton>
              </NBadge>
            </template>
            <section class="notification-popover" aria-live="polite">
              <header class="notification-header">
                <strong>{{ t('shell.notifications') }}</strong>
                <NButton
                  text
                  size="tiny"
                  :disabled="notificationsRead"
                  @click="markNotificationsRead"
                >
                  {{ notificationsRead ? t('shell.allRead') : t('shell.markAllRead') }}
                </NButton>
              </header>
              <div class="notification-list">
                <article
                  v-for="item in notificationItems"
                  :key="item.key"
                  class="notification-item"
                >
                  <span class="notification-dot" aria-hidden="true" />
                  <div>
                    <strong>{{ item.title }}</strong>
                    <p>{{ item.detail }}</p>
                  </div>
                </article>
              </div>
            </section>
          </NPopover>
          <NButton quaternary circle :aria-label="t('shell.theme')" @click="toggleTheme">
            <template #icon><NIcon :component="themeIcon" /></template>
          </NButton>
          <NDropdown :options="localeOptions" trigger="click" @select="handleLocale">
            <NButton quaternary size="small" :aria-label="t('shell.language')">
              {{ localeLabel }}<NIcon><ChevronDownOutline /></NIcon>
            </NButton>
          </NDropdown>
          <NDropdown
            v-model:show="userMenuOpen"
            trigger="click"
            :options="userOptions"
            @select="handleUserAction"
          >
            <NButton quaternary class="user-button">
              <template #icon
                ><NIcon><PersonCircleOutline /></NIcon
              ></template>
              {{ auth.user?.username || 'quyetdv' }}<NIcon><ChevronDownOutline /></NIcon>
            </NButton>
          </NDropdown>
        </NSpace>
      </NLayoutHeader>

      <NLayout has-sider class="shell-body">
        <NLayoutSider
          bordered
          :width="workspace.sidebarWidth"
          :collapsed-width="72"
          :collapsed="workspace.sidebarCollapsed"
          collapse-mode="width"
          class="shell-sider"
          :class="{
            'sider-mobile-open': mobileMenuOpen,
            'sider-collapsed': workspace.sidebarCollapsed,
          }"
        >
          <div class="sider-content">
            <NInput
              v-if="!workspace.sidebarCollapsed"
              v-model:value="menuSearch"
              clearable
              :placeholder="t('shell.searchMenu')"
            >
              <template #prefix
                ><NIcon><SearchOutline /></NIcon
              ></template>
            </NInput>
            <div class="mode-switcher" role="group" :aria-label="t('shell.sidebarModes')">
              <NButton
                v-for="mode in sidebarModes"
                :key="mode.key"
                quaternary
                :type="activeMode === mode.key ? 'primary' : 'default'"
                :class="{ 'mode-button--active': activeMode === mode.key }"
                :aria-pressed="activeMode === mode.key"
                :aria-label="t(mode.labelKey)"
                :title="t(mode.labelKey)"
                @click="selectMode(mode.key)"
              >
                <template #icon><NIcon :component="mode.icon" /></template>
              </NButton>
            </div>
            <div v-if="!workspace.sidebarCollapsed && !mobileMenuOpen" class="sider-heading">
              {{ activeModeLabel }}
            </div>
            <div v-if="!workspace.sidebarCollapsed" class="sider-menu-scroll">
              <NMenu
                :value="route.path"
                :options="menuOptions"
				:root-indent="12"
				:indent="14"
                :collapsed="workspace.sidebarCollapsed"
                :collapsed-width="72"
                :collapsed-icon-size="20"
                @update:value="handleMenuSelect"
              />
            </div>
            <div v-if="!workspace.sidebarCollapsed && !mobileMenuOpen" class="sider-footer">
              <NIcon><InformationCircleOutline /></NIcon
              ><span>{{ t('shell.filteredByPermission') }}</span>
            </div>
            <button
              class="sider-collapse"
              type="button"
              :aria-label="
                workspace.sidebarCollapsed ? t('shell.expandSidebar') : t('shell.collapseSidebar')
              "
              @click="toggleSidebar"
            >
              <NIcon
                ><ChevronForwardOutline v-if="workspace.sidebarCollapsed" /><ChevronBackOutline
                  v-else
              /></NIcon>
            </button>
            <div
              v-if="!workspace.sidebarCollapsed"
              class="sider-resize"
              role="separator"
              aria-orientation="vertical"
              tabindex="0"
              :aria-valuemin="workspace.MIN_SIDEBAR_WIDTH"
              :aria-valuemax="workspace.MAX_SIDEBAR_WIDTH"
              :aria-valuenow="workspace.sidebarWidth"
              @pointerdown="startResize"
              @keydown="handleResizeKey"
            />
          </div>
        </NLayoutSider>

        <NLayout class="workspace-layout">
          <div class="workspace-tabs-wrap">
            <NTabs
              type="card"
              size="small"
              :value="currentTabKey"
              closable
              @update:value="handleTabChange"
              @close="closeTab"
            >
              <NTabPane
                v-for="tab in workspace.tabs"
                :key="tab.key"
                :name="tab.key"
                :closable="tab.closable"
              >
                <template #tab>{{ t(tab.labelKey) }}</template>
              </NTabPane>
            </NTabs>
            <NButton
              quaternary
              circle
              :aria-label="t('shell.openNewTab')"
              @click="openCommandPalette"
            >
              <template #icon
                ><NIcon><AddOutline /></NIcon
              ></template>
            </NButton>
          </div>
          <div class="error-stack" aria-live="polite">
            <NAlert
              v-for="notice in errorNotices"
              :key="notice.id"
              :type="notice.status === 401 || notice.status === 403 ? 'warning' : 'error'"
              closable
              @close="dismissError(notice.id)"
            >
              <template #icon
                ><NIcon><AlertCircleOutline /></NIcon
              ></template>
              <strong>{{ t(`errors.status.${notice.status}`) }}</strong>
              <span>{{ notice.message || t('errors.defaultMessage') }}</span>
              <small v-if="notice.code || notice.requestId" class="error-meta"
                >{{ notice.code || t('errors.unknownCode')
                }}<span v-if="notice.requestId"> · {{ notice.requestId }}</span></small
              >
            </NAlert>
          </div>
          <main class="workspace-content"><RouterView /></main>
        </NLayout>
      </NLayout>
    </NLayout>

    <NModal
      v-model:show="commandOpen"
      preset="card"
      class="command-modal"
      :mask-closable="false"
      :close-on-esc="false"
    >
      <template #header>
        <div class="command-modal-header">
          <span class="command-modal-icon"
            ><NIcon><SearchOutline /></NIcon
          ></span>
          <div>
            <strong>{{ t('shell.commandPalette') }}</strong>
            <small>{{ t('shell.commandDescription') }}</small>
          </div>
          <NButton
            quaternary
            circle
            :aria-label="t('common.close')"
            :title="t('common.close')"
            @click="commandOpen = false"
          >
            <template #icon
              ><NIcon><CloseOutline /></NIcon
            ></template>
          </NButton>
        </div>
      </template>
      <NInput
        v-model:value="commandSearch"
        autofocus
        size="large"
        clearable
        :placeholder="t('shell.searchCommand')"
        class="command-search-input"
        @keydown="handleCommandKeydown"
      >
        <template #prefix
          ><NIcon><SearchOutline /></NIcon
        ></template>
      </NInput>
      <div v-if="commandOptions.length" class="command-list">
        <NButton
          v-for="command in commandOptions"
          :key="command.key"
          class="command-option"
          quaternary
          block
          @click="openCommandRoute(command.key)"
        >
          <template #icon><NIcon :component="command.icon" /></template>
          <span class="command-option-label">{{ command.label }}</span>
          <NIcon class="command-option-arrow"><ChevronForwardOutline /></NIcon>
        </NButton>
      </div>
      <div v-else class="command-empty">{{ t('shell.noCommands') }}</div>
      <footer class="command-modal-footer">
        <span><kbd>↵</kbd> {{ t('shell.commandOpen') }}</span>
        <span>{{ t('common.close') }}</span>
      </footer>
    </NModal>
  </div>
</template>

<style scoped>
.erp-shell,
.shell-layout {
  min-height: 100vh;
  background: #f5f7fa;
}
.shell-body {
  height: calc(100vh - 62px);
  min-height: 0;
  overflow: hidden;
  background: #f5f7fa;
}
.shell-header {
  position: relative;
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 62px;
  padding: 0 18px 0 16px;
  background: #fff;
}
.mobile-menu-button {
  display: none;
}
.header-left,
.header-actions {
  min-width: 0;
}
.brand {
  display: inline-flex;
  align-items: center;
  min-width: 150px;
}
.shell-brand-logo {
  display: block;
  width: 132px;
  height: 48px;
  object-fit: contain;
  object-position: left center;
}
.workspace-search {
  width: min(430px, 40vw);
  justify-content: flex-start;
  color: #718298;
  text-align: left;
}
.workspace-search kbd {
  margin-left: auto;
}
.locale-select {
  width: 122px;
}
.user-button {
  gap: 7px;
}
.shell-sider {
  position: relative;
  min-width: 0;
  background: #fff;
}
.shell-sider :deep(.n-layout-sider-scroll-container) {
  overflow: hidden;
}
.sider-content {
  position: relative;
  display: flex;
  height: 100%;
  min-width: 0;
  flex-direction: column;
  gap: 14px;
  padding: 16px 8px 12px;
  overflow: hidden;
}
.mode-switcher {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 4px;
}
.mode-switcher :deep(.n-button) {
  width: 100%;
  min-width: 0;
  height: 40px;
  padding: 0;
  border-radius: 10px;
  color: #273e57;
}
.mode-switcher :deep(.n-button:hover) {
  background: #eaf2f8;
  color: #114c7c;
}
.mode-switcher :deep(.n-button.mode-button--active) {
  background: #dff3e9;
  color: #066248;
  box-shadow: inset 0 0 0 1px #9fd8be;
}
.mode-switcher :deep(.n-button:focus-visible),
.sider-collapse:focus-visible,
.sider-resize:focus-visible {
  outline: 2px solid #126bb0;
  outline-offset: 2px;
}
.shell-sider.sider-collapsed .sider-content {
  box-sizing: border-box;
  width: 72px;
  max-width: 100%;
  align-items: center;
  padding: 16px 10px 58px;
}
.shell-sider.sider-collapsed .mode-switcher {
  width: 44px;
  max-width: 100%;
  grid-template-columns: minmax(0, 1fr);
  gap: 8px;
  justify-items: center;
}
.shell-sider.sider-collapsed .mode-switcher :deep(.n-button) {
  width: 44px;
  height: 44px;
}
.sider-heading {
  padding: 0 8px;
  color: #53687e;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.sider-menu-scroll {
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  scrollbar-gutter: stable;
}
.sider-menu-scroll :deep(.n-menu) {
  width: 100%;
  min-width: 0;
}
.sider-menu-scroll :deep(.n-menu-item-content) {
  min-width: 0;
}
.sider-menu-scroll :deep(.n-menu-item-content-header) {
  min-width: 0;
  overflow: hidden;
  color: #273e57;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sider-menu-scroll :deep(.n-menu-item-content--selected .n-menu-item-content-header) {
  color: #066248;
  font-weight: 650;
}
.sider-footer {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 8px;
  border-top: 1px solid #e9eef2;
  color: #53687e;
  font-size: 11px;
}
.sider-collapse {
  position: absolute;
  top: 128px;
  right: 12px;
  z-index: 5;
  display: grid;
  width: 26px;
  height: 26px;
  place-items: center;
  border: 1px solid #b9cad8;
  border-radius: 50%;
  background: #fff;
  color: #294761;
  cursor: pointer;
  box-shadow: 0 2px 8px rgb(24 47 68 / 8%);
}
.shell-sider.sider-collapsed .sider-collapse {
  top: auto;
  right: calc(50% - 18px);
  bottom: 16px;
  width: 36px;
  height: 36px;
}
.sider-resize {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  z-index: 3;
  width: 10px;
  cursor: col-resize;
  touch-action: none;
}
.sider-resize::after {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 2px;
  background: #1674a6;
  content: '';
  opacity: 0;
}
.sider-resize:hover::after,
.sider-resize:focus-visible::after,
.shell-resizing .sider-resize::after {
  opacity: 1;
}
.shell-resizing .shell-sider {
  width: var(--sidebar-width) !important;
  min-width: var(--sidebar-width) !important;
  max-width: var(--sidebar-width) !important;
  flex: 0 0 var(--sidebar-width) !important;
  transition: none !important;
}
.shell-resizing .shell-body {
  cursor: col-resize;
  user-select: none;
}
.shell-resizing .workspace-layout {
  pointer-events: none;
}
.workspace-layout {
  min-height: 100%;
  min-width: 0;
  overflow: hidden;
  background: #f5f7fa;
}
.workspace-tabs-wrap {
  display: flex;
  align-items: center;
  min-height: 44px;
  padding: 5px 12px 0;
  border-bottom: 1px solid #dfe7ed;
  background: #fff;
}
.workspace-tabs-wrap :deep(.n-tabs) {
  flex: 1;
  min-width: 0;
}
.workspace-tabs-wrap :deep(.n-tabs-nav) {
  margin-bottom: 0;
}
.error-stack {
  display: grid;
  gap: 8px;
  padding: 12px 28px 0;
}
.error-stack :deep(.n-alert) {
  align-items: flex-start;
}
.error-meta {
  display: block;
  margin-top: 4px;
  color: #728095;
}
.workspace-content {
  overflow-x: hidden;
  overflow-y: auto;
  min-width: 0;
  padding: 26px clamp(18px, 2.2vw, 36px) 48px;
}
.workspace-content > :deep(.breadcrumbs),
.workspace-content > :deep(.page-heading),
.workspace-content > :deep(.content-panel),
.workspace-content > :deep(.metric-grid),
.workspace-content > :deep(.dashboard-blank),
.workspace-content > :deep(.profile-layout) {
  width: min(100%, 1680px);
  margin-inline: auto;
}
.command-modal {
  width: min(620px, calc(100vw - 32px));
  border: 1px solid #dbe5ec;
  box-shadow: 0 24px 70px rgb(15 44 67 / 22%);
}
.command-modal-header {
  display: flex;
  align-items: center;
  gap: 12px;
}
.command-modal-header > div {
  display: grid;
  gap: 3px;
}
.command-modal-header > .n-button {
  margin-left: auto;
}
.command-modal-header strong {
  color: #183b57;
  font-size: 15px;
}
.command-modal-header small {
  color: #7b8d9d;
  font-size: 11px;
  font-weight: 400;
}
.command-modal-icon {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border-radius: 9px;
  background: #e9f5f7;
  color: #0878b6;
}
.command-search-input {
  margin-top: 2px;
}
.command-search-input :deep(.n-input__input-el) {
  font-size: 14px;
}
.command-list {
  display: grid;
  gap: 4px;
  max-height: 340px;
  margin-top: 12px;
  overflow: auto;
}
.command-option {
  min-height: 44px;
  justify-content: flex-start;
  border-radius: 8px;
  color: #294b65;
  text-align: left;
}
.command-option:hover {
  background: #eff8f8;
  color: #0569ad;
}
.command-option-label {
  flex: 1;
  text-align: left;
}
.command-option-arrow {
  color: #9aaab7;
}
.command-empty {
  padding: 34px 12px;
  color: #7b8d9d;
  font-size: 13px;
  text-align: center;
}
.command-modal-footer {
  display: flex;
  gap: 16px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid #edf1f4;
  color: #8393a1;
  font-size: 11px;
}
.command-modal-footer kbd {
  padding: 2px 6px;
  border: 1px solid #dce5eb;
  border-radius: 4px;
  background: #f8fafb;
  color: #5e7487;
  font-size: 10px;
}
.notification-popover {
  width: min(330px, calc(100vw - 36px));
}
.notification-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 10px;
  border-bottom: 1px solid #edf1f4;
  color: #183b57;
  font-size: 14px;
}
.notification-list {
  display: grid;
  gap: 2px;
  padding-top: 5px;
}
.notification-item {
  display: flex;
  gap: 10px;
  padding: 10px 4px;
  border-bottom: 1px solid #f0f3f5;
}
.notification-item:last-child {
  border-bottom: 0;
}
.notification-item > div {
  min-width: 0;
}
.notification-item strong {
  color: #294b65;
  font-size: 12px;
}
.notification-item p {
  margin: 3px 0 0;
  color: #7b8d9d;
  font-size: 11px;
  line-height: 1.45;
}
.notification-dot {
  width: 8px;
  height: 8px;
  flex: 0 0 auto;
  margin-top: 5px;
  border-radius: 50%;
  background: #0b7dbd;
}
.erp-shell.theme-dark,
.erp-shell.theme-dark .shell-body,
.erp-shell.theme-dark .workspace-layout {
  background: #111923;
}
.erp-shell.theme-dark .shell-header,
.erp-shell.theme-dark .shell-sider,
.erp-shell.theme-dark .workspace-tabs-wrap {
  border-color: #273746;
  background: #17232e;
}
.erp-shell.theme-dark .workspace-content,
.erp-shell.theme-dark .sider-heading,
.erp-shell.theme-dark .sider-footer,
.erp-shell.theme-dark .workspace-search,
.erp-shell.theme-dark .notification-item p,
.erp-shell.theme-dark .command-modal-footer {
  color: #a9bac7;
}
.erp-shell.theme-dark .workspace-search {
  border-color: #304454;
  background: #1b2a36;
}
.erp-shell.theme-dark .mode-switcher :deep(.n-button) {
  color: #d9e7f2;
}
.erp-shell.theme-dark .mode-switcher :deep(.n-button:hover) {
  background: #294255;
  color: #ffffff;
}
.erp-shell.theme-dark .mode-switcher :deep(.n-button.mode-button--active) {
  background: #174b3d;
  color: #9af0ce;
  box-shadow: inset 0 0 0 1px #3b9b79;
}
.erp-shell.theme-dark .sider-menu-scroll :deep(.n-menu-item-content-header) {
  color: #d9e7f2;
}
.erp-shell.theme-dark
  .sider-menu-scroll
  :deep(.n-menu-item-content--selected .n-menu-item-content-header) {
  color: #9af0ce;
}
.erp-shell.theme-dark .sider-resize::after {
  background: #7dd4ec;
}
.erp-shell.theme-dark .sider-footer,
.erp-shell.theme-dark .workspace-tabs-wrap,
.erp-shell.theme-dark .notification-header,
.erp-shell.theme-dark .notification-item,
.erp-shell.theme-dark .command-modal-footer {
  border-color: #2a3b49;
}
.erp-shell.theme-dark .sider-collapse {
  border-color: #385062;
  background: #1d2d3a;
  color: #b5c8d5;
}
.erp-shell.theme-dark .command-modal,
.erp-shell.theme-dark .command-option:hover,
.erp-shell.theme-dark .notification-popover {
  background: #1b2a36;
}
.erp-shell.theme-dark .command-modal-header strong,
.erp-shell.theme-dark .notification-header,
.erp-shell.theme-dark .notification-item strong {
  color: #e3edf3;
}
.erp-shell.theme-dark .command-modal-header small,
.erp-shell.theme-dark .command-empty {
  color: #9eb1bf;
}
.erp-shell.theme-dark .command-modal-icon {
  background: #214354;
  color: #8bd9e9;
}
@media (max-width: 800px) {
  .shell-header {
    padding-inline: 10px;
  }
  .brand {
    min-width: auto;
  }
  .mobile-menu-button {
    display: inline-flex;
  }
  .shell-brand-logo {
    width: 112px;
    height: 42px;
  }
  .workspace-search {
    width: min(260px, 44vw);
  }
  .header-actions > :deep(.n-button:nth-child(2)),
  .locale-select {
    display: none;
  }
  .shell-sider {
    position: fixed;
    inset: 62px auto 0 0;
    z-index: 15;
    height: calc(100vh - 62px);
    transform: translateX(-100%);
    box-shadow: 8px 0 24px rgb(26 51 73 / 14%);
  }
  .shell-sider.sider-mobile-open {
    transform: translateX(0);
  }
  .workspace-content {
    padding: 18px 14px 36px;
  }
}
</style>
