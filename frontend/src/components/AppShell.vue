<script setup lang="ts">
import { computed, h, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  NAlert,
  NAvatar,
  NBadge,
  NButton,
  NIcon,
  NInput,
  NLayout,
  NLayoutHeader,
  NLayoutSider,
  NMenu,
  NModal,
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
  CheckmarkCircleOutline,
  ChevronBackOutline,
  ChevronDownOutline,
  ChevronForwardOutline,
  CloseOutline,
  CubeOutline,
  GridOutline,
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
  TimeOutline,
} from '@vicons/ionicons5'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { setLocale, type AppLocale } from '@/i18n'
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

const mobileMenuOpen = ref(false)
const commandOpen = ref(false)
const menuSearch = ref('')
const commandSearch = ref('')
const userMenuOpen = ref(false)

const icon = (component: object) => () => h(NIcon, null, { default: () => h(component) })

const sidebarModes = [
  { key: 'menu', labelKey: 'shell.modes.menu', icon: MenuOutline },
  { key: 'favorites', labelKey: 'shell.modes.favorites', icon: StarOutline },
  { key: 'recent', labelKey: 'shell.modes.recent', icon: TimeOutline },
  { key: 'system', labelKey: 'shell.modes.system', icon: SettingsOutline },
  { key: 'organization', labelKey: 'shell.modes.organization', icon: BusinessOutline },
] as const

type NavigationItem = { labelKey: string; to: string; icon: object; group?: string }

const navItems: NavigationItem[] = [
  { labelKey: 'nav.dashboard', to: '/', icon: HomeOutline },
  { labelKey: 'nav.approvals', to: '/approvals', icon: CheckmarkCircleOutline },
  { labelKey: 'nav.organizations', to: '/organizations', icon: BusinessOutline },
  { labelKey: 'nav.groups', to: '/admin/groups', icon: PeopleOutline, group: 'orgadmin' },
  { labelKey: 'nav.products', to: '/products', icon: CubeOutline },
  { labelKey: 'nav.sales', to: '/sales', icon: GridOutline },
  { labelKey: 'nav.users', to: '/admin/users', icon: SettingsOutline, group: 'orgadmin' },
]

const localeOptions = [
  { label: 'Tiếng Việt', key: 'vi' },
  { label: 'English', key: 'en' },
]

const userOptions = computed(() => [
  { label: t('shell.profile'), key: 'profile', icon: icon(PersonCircleOutline) },
  { label: t('shell.signOut'), key: 'signOut', icon: icon(CloseOutline) },
])

const activeMode = computed(() => workspace.sidebarMode)
const sidebarWidth = computed(() => (workspace.sidebarCollapsed ? 72 : workspace.sidebarWidth))
const currentTabKey = computed(() => String(route.name || route.path))
const localeLabel = computed(() => (locale.value === 'vi' ? 'Tiếng Việt' : 'English'))
const activeModeLabel = computed(() => {
  const mode = sidebarModes.find((item) => item.key === activeMode.value) || sidebarModes[0]
  return t(mode.labelKey)
})

const allowedNavItems = computed(() =>
  navItems.filter((item) => !item.group || auth.user?.groups.includes(item.group)),
)

const modeItems = computed(() => {
  if (activeMode.value === 'favorites' || activeMode.value === 'recent') {
    const tabItems = workspace.tabs
      .map((tab) => ({ labelKey: tab.labelKey, to: tab.path, icon: InformationCircleOutline }))
      .filter((item) => t(item.labelKey).toLowerCase().includes(menuSearch.value.toLowerCase()))
    return tabItems.length > 0 ? tabItems : allowedNavItems.value
  }
  if (activeMode.value === 'system') {
    return allowedNavItems.value.filter((item) =>
      ['/admin/users', '/admin/groups'].includes(item.to),
    )
  }
  if (activeMode.value === 'organization') {
    return allowedNavItems.value.filter((item) => item.to === '/organizations')
  }
  return allowedNavItems.value.filter((item) =>
    t(item.labelKey).toLowerCase().includes(menuSearch.value.toLowerCase()),
  )
})

const menuOptions = computed<MenuOption[]>(() =>
  modeItems.value.map((item) => ({ key: item.to, label: t(item.labelKey), icon: icon(item.icon) })),
)

const commandOptions = computed(() =>
  navItems
    .filter((item) => !item.group || auth.user?.groups.includes(item.group))
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

let resizeMove: ((event: PointerEvent) => void) | null = null
let resizeEnd: (() => void) | null = null

function startResize(event: PointerEvent): void {
  if (workspace.sidebarCollapsed) return
  const startX = event.clientX
  const startWidth = workspace.sidebarWidth
  resizeMove = (moveEvent) => workspace.setSidebarWidth(startWidth + moveEvent.clientX - startX)
  resizeEnd = () => {
    if (resizeMove) window.removeEventListener('pointermove', resizeMove)
    if (resizeEnd) window.removeEventListener('pointerup', resizeEnd)
    resizeMove = null
    resizeEnd = null
  }
  window.addEventListener('pointermove', resizeMove)
  window.addEventListener('pointerup', resizeEnd)
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

function handleUserAction(key: string): void {
  userMenuOpen.value = false
  if (key === 'profile') void router.push('/profile')
  if (key === 'signOut') void signOut()
}

async function signOut(): Promise<void> {
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

function dismissError(id: string): void {
  dismissApiError(id)
}

onBeforeUnmount(() => resizeEnd?.())
</script>

<template>
  <div class="erp-shell" :style="{ '--sidebar-width': `${sidebarWidth}px` }">
    <NLayout has-sider class="shell-layout">
      <NLayoutHeader bordered class="shell-header">
        <NSpace align="center" :size="10" class="header-left">
          <NButton
            quaternary
            circle
            :aria-label="t('shell.openMenu')"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <template #icon
              ><NIcon><MenuOutline /></NIcon
            ></template>
          </NButton>
          <RouterLink class="brand" to="/">
            <NAvatar color="#1769aa" :size="32">D</NAvatar>
            <span class="brand-copy"
              ><strong>Dinktech</strong><small>{{ t('common.brandSubtitle') }}</small></span
            >
          </RouterLink>
          <NButton class="workspace-search" secondary @click="openCommandPalette">
            <template #icon
              ><NIcon><SearchOutline /></NIcon
            ></template>
            {{ t('shell.searchWorkspace') }}<kbd>/</kbd>
          </NButton>
        </NSpace>
        <NSpace align="center" :size="6" class="header-actions">
          <NBadge :value="15" :max="99" type="error">
            <NButton quaternary circle :aria-label="t('shell.notifications')">
              <template #icon
                ><NIcon><NotificationsOutline /></NIcon
              ></template>
            </NButton>
          </NBadge>
          <NButton quaternary circle :aria-label="t('shell.theme')">
            <template #icon
              ><NIcon><MoonOutline /></NIcon
            ></template>
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

      <NLayoutSider
        bordered
        :width="sidebarWidth"
        :collapsed-width="72"
        :collapsed="workspace.sidebarCollapsed"
        collapse-mode="width"
        class="shell-sider"
        :class="{ 'sider-mobile-open': mobileMenuOpen }"
      >
        <div class="sider-content">
          <NInput v-model:value="menuSearch" clearable :placeholder="t('shell.searchMenu')">
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
          <NMenu
            :value="route.path"
            :options="menuOptions"
            :collapsed="workspace.sidebarCollapsed"
            :collapsed-width="72"
            :collapsed-icon-size="20"
            @update:value="handleMenuSelect"
          />
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

    <NModal
      v-model:show="commandOpen"
      preset="card"
      :title="t('shell.commandPalette')"
      class="command-modal"
    >
      <p class="modal-description">{{ t('shell.commandDescription') }}</p>
      <NInput v-model:value="commandSearch" autofocus :placeholder="t('shell.searchCommand')">
        <template #prefix
          ><NIcon><SearchOutline /></NIcon
        ></template>
      </NInput>
      <div class="command-list">
        <NButton
          v-for="command in commandOptions"
          :key="command.key"
          quaternary
          block
          @click="openCommandRoute(command.key)"
        >
          <template #icon><NIcon :component="command.icon" /></template>{{ command.label }}
        </NButton>
      </div>
    </NModal>
  </div>
</template>

<style scoped>
.erp-shell,
.shell-layout {
  min-height: 100vh;
  background: #f5f7fa;
}
.shell-header {
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 62px;
  padding: 0 18px 0 16px;
  background: #fff;
}
.header-left,
.header-actions {
  min-width: 0;
}
.brand {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  min-width: 170px;
}
.brand-copy {
  display: grid;
  line-height: 1.1;
}
.brand-copy strong {
  color: #17324d;
  font-size: 15px;
}
.brand-copy small {
  margin-top: 3px;
  color: #718298;
  font-size: 10px;
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
  background: #fff;
}
.sider-content {
  position: relative;
  display: flex;
  height: 100%;
  flex-direction: column;
  gap: 14px;
  padding: 16px 12px 12px;
}
.mode-switcher {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 2px;
}
.sider-heading {
  padding: 0 8px;
  color: #7b8a9b;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.sider-content :deep(.n-menu) {
  flex: 1;
}
.sider-footer {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 8px;
  border-top: 1px solid #e9eef2;
  color: #718298;
  font-size: 11px;
}
.sider-collapse {
  position: absolute;
  top: 86px;
  right: -13px;
  z-index: 3;
  display: grid;
  width: 26px;
  height: 26px;
  place-items: center;
  border: 1px solid #dbe4eb;
  border-radius: 50%;
  background: #fff;
  color: #55708b;
  cursor: pointer;
  box-shadow: 0 2px 8px rgb(24 47 68 / 8%);
}
.sider-resize {
  position: absolute;
  top: 0;
  right: -3px;
  bottom: 0;
  z-index: 3;
  width: 6px;
  cursor: col-resize;
}
.workspace-layout {
  min-width: 0;
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
  min-width: 0;
  padding: 22px 28px 48px;
}
.command-modal {
  width: min(620px, calc(100vw - 32px));
}
.modal-description {
  margin: 0 0 14px;
  color: #718298;
  font-size: 13px;
}
.command-list {
  display: grid;
  gap: 4px;
  max-height: 360px;
  margin-top: 12px;
  overflow: auto;
}
@media (max-width: 800px) {
  .shell-header {
    padding-inline: 10px;
  }
  .brand {
    min-width: auto;
  }
  .brand-copy {
    display: none;
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
    padding-inline: 14px;
  }
}
</style>
