<script setup lang="ts">
import { ref } from 'vue'
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
  NTabPane,
  NTabs,
  NDropdown,
} from 'naive-ui'
import {
  AddOutline,
  AlertCircleOutline,
  ChevronBackOutline,
  ChevronDownOutline,
  ChevronForwardOutline,
  InformationCircleOutline,
  LanguageOutline,
  MenuOutline,
  NotificationsOutline,
  PersonCircleOutline,
  SearchOutline,
} from '@vicons/ionicons5'
import { RouterLink, RouterView, useRoute } from 'vue-router'

import { sidebarModes } from '@/app/navigation'
import AppBreadcrumbs from '@/components/AppBreadcrumbs.vue'
import UserAvatar from '@/components/users/UserAvatar.vue'
import NotificationPopoverList from '@/features/notifications/components/NotificationPopoverList.vue'
import { useShellHeader } from '@/components/app-shell/useShellHeader'
import { useShellNavigation } from '@/components/app-shell/useShellNavigation'
import { useShellSidebarResize } from '@/components/app-shell/useShellSidebarResize'
import { useShellWorkspaceTabs } from '@/components/app-shell/useShellWorkspaceTabs'
import { dismissApiError, errorNotices } from '@/services/errorBoundary'
import { useWorkspaceStore } from '@/stores/workspace'

const workspace = useWorkspaceStore()
const route = useRoute()
const { t } = useI18n()
const shellElement = ref<HTMLElement | null>(null)

const {
  mobileMenuOpen,
  commandOpen,
  menuSearch,
  commandSearch,
  activeMode,
  activeModeLabel,
  activeModeCount,
  sidebarEmptyMessage,
  menuOptions,
  commandOptions,
  renderFavoriteExtra,
  menuNodeProps,
  selectMode,
  handleMenuSelect,
  openCommandPalette,
  openCommandRoute,
  handleCommandKeydown,
} = useShellNavigation()

const { sidebarWidth, isResizingSidebar, toggleSidebar, startResize, handleResizeKey } =
  useShellSidebarResize(shellElement)

const { currentTabKey, closeTab, handleTabChange } = useShellWorkspaceTabs()

const {
  auth,
  userMenuOpen,
  notificationsOpen,
  inbox,
  inboxUiEnabled,
  localeOptions,
  userOptions,
  localeLabel,
  themeIcon,
  unreadNotifications,
  notificationItems,
  handleLocale,
  toggleTheme,
  markNotificationsRead,
  markOneNotificationRead,
  closeNotifications,
  openInbox,
  handleUserAction,
} = useShellHeader(currentTabKey)
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
        <div class="header-left">
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
            <template #icon>
              <NIcon><MenuOutline /></NIcon>
            </template>
          </NButton>
          <NButton
            class="workspace-search"
            secondary
            :aria-label="t('shell.searchWorkspace')"
            :title="t('shell.searchWorkspace')"
            @click="openCommandPalette"
          >
            <NIcon :size="20"><SearchOutline /></NIcon>
            <span class="workspace-search-label">{{ t('shell.searchWorkspace') }}</span>
          </NButton>
        </div>

        <div class="header-actions">
          <NPopover v-if="inboxUiEnabled" v-model:show="notificationsOpen" trigger="click" placement="bottom-end">
            <template #trigger>
              <NBadge :value="unreadNotifications" :max="99" type="error">
                <NButton quaternary circle :aria-label="t('shell.notifications')">
                  <template #icon>
                    <NIcon><NotificationsOutline /></NIcon>
                  </template>
                </NButton>
              </NBadge>
            </template>
            <section class="notification-popover" aria-live="polite">
              <header class="notification-header">
                <strong>{{ t('shell.notifications') }}</strong>
                <NButton
                  text
                  size="tiny"
                  :disabled="unreadNotifications === 0"
                  @click="markNotificationsRead"
                >
                  {{ unreadNotifications === 0 ? t('shell.allRead') : t('shell.markAllRead') }}
                </NButton>
              </header>
              <NotificationPopoverList
                :items="notificationItems"
                :busy="inbox.previewBusy"
                :error="Boolean(inbox.previewError)"
                @navigate="closeNotifications"
                @mark-read="markOneNotificationRead"
              />
              <NButton text size="small" @click="openInbox">{{ t('notificationsInbox.viewAll') }}</NButton>
            </section>
          </NPopover>
          <NButton quaternary circle :aria-label="t('shell.theme')" @click="toggleTheme">
            <template #icon>
              <NIcon :component="themeIcon" />
            </template>
          </NButton>
          <NDropdown :options="localeOptions" trigger="click" @select="handleLocale">
            <NButton
              class="header-language-button"
              quaternary
              size="small"
              :aria-label="t('shell.language')"
              :title="t('shell.language')"
            >
              <span class="header-language-label">{{ localeLabel }}</span>
              <NIcon class="header-language-chevron"><ChevronDownOutline /></NIcon>
              <NIcon class="header-language-icon" aria-hidden="true"><LanguageOutline /></NIcon>
            </NButton>
          </NDropdown>
          <NDropdown
            v-model:show="userMenuOpen"
            trigger="click"
            :options="userOptions"
            @select="handleUserAction"
          >
            <NButton
              quaternary
              class="user-button"
              :aria-label="auth.user?.name || auth.user?.username || t('shell.profile')"
              :title="auth.user?.name || auth.user?.username || t('shell.profile')"
            >
              <template #icon>
                <UserAvatar v-if="auth.user" :user-id="auth.user.id" :has-avatar="auth.user.has_avatar" :avatar-url="auth.user.avatar_url" :fallback="auth.user.name || auth.user.username" />
                <NIcon v-else><PersonCircleOutline /></NIcon>
              </template>
              <span class="header-user-label">{{ auth.user?.name || auth.user?.username || t('shell.profile') }}</span>
              <NIcon class="header-user-chevron"><ChevronDownOutline /></NIcon>
            </NButton>
          </NDropdown>
        </div>
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
              <template #prefix>
                <NIcon><SearchOutline /></NIcon>
              </template>
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
                <template #icon>
                  <NIcon :component="mode.icon" />
                </template>
              </NButton>
            </div>

            <div v-if="!workspace.sidebarCollapsed && !mobileMenuOpen" class="sider-heading">
              <span>{{ activeModeLabel }}</span>
              <span v-if="activeModeCount !== null" class="sider-heading-count">
                {{ activeModeCount }}
              </span>
            </div>

            <div v-if="!workspace.sidebarCollapsed" class="sider-menu-scroll">
              <NMenu
                v-if="menuOptions.length"
                :value="route.path"
                :options="menuOptions"
                :root-indent="12"
                :indent="14"
                :render-extra="renderFavoriteExtra"
                :node-props="menuNodeProps"
                :collapsed="workspace.sidebarCollapsed"
                :collapsed-width="72"
                :collapsed-icon-size="20"
                @update:value="handleMenuSelect"
              />
              <p v-else class="sider-empty-state">{{ sidebarEmptyMessage }}</p>
            </div>

            <div v-if="!workspace.sidebarCollapsed && !mobileMenuOpen" class="sider-footer">
              <NIcon><InformationCircleOutline /></NIcon>
              <span>{{ t('shell.filteredByPermission') }}</span>
            </div>
            <button
              class="sider-collapse"
              type="button"
              :aria-label="
                workspace.sidebarCollapsed ? t('shell.expandSidebar') : t('shell.collapseSidebar')
              "
              @click="toggleSidebar"
            >
              <NIcon>
                <ChevronForwardOutline v-if="workspace.sidebarCollapsed" />
                <ChevronBackOutline v-else />
              </NIcon>
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
              <template #icon>
                <NIcon><AddOutline /></NIcon>
              </template>
            </NButton>
          </div>

          <div class="error-stack" aria-live="polite">
            <NAlert
              v-for="notice in errorNotices"
              :key="notice.id"
              :type="notice.status === 401 || notice.status === 403 ? 'warning' : 'error'"
              closable
              @close="dismissApiError(notice.id)"
            >
              <template #icon>
                <NIcon><AlertCircleOutline /></NIcon>
              </template>
              <strong>{{ t(`errors.status.${notice.status}`) }}</strong>
              <span>{{ notice.message || t('errors.defaultMessage') }}</span>
              <small v-if="notice.code || notice.requestId" class="error-meta">
                {{ notice.code || t('errors.unknownCode')
                }}<span v-if="notice.requestId"> · {{ notice.requestId }}</span>
              </small>
            </NAlert>
          </div>

          <main class="workspace-content">
            <AppBreadcrumbs />
            <RouterView />
          </main>
        </NLayout>
      </NLayout>
    </NLayout>

    <NModal
      v-model:show="commandOpen"
      preset="card"
      class="command-modal"
      :mask-closable="false"
      :close-on-esc="true"
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
        <template #prefix>
          <NIcon><SearchOutline /></NIcon>
        </template>
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
          <template #icon>
            <NIcon :component="command.icon" />
          </template>
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

<style scoped src="./app-shell/styles.css"></style>
