<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { setLocale, type AppLocale } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const commandOpen = ref(false)
const mobileMenuOpen = ref(false)
const userMenuOpen = ref(false)
const activeMode = ref(localStorage.getItem('ui.sidebar.active_mode') || 'menu')
const sidebarWidth = ref(Number(localStorage.getItem('ui.sidebar.width')) || 290)
const sidebarCollapsed = ref(localStorage.getItem('ui.sidebar.collapsed') === 'true')

const sidebarModes = [
  { key: 'menu', labelKey: 'shell.modes.menu', icon: '☰' },
  { key: 'favorites', labelKey: 'shell.modes.favorites', icon: '☆' },
  { key: 'recent', labelKey: 'shell.modes.recent', icon: '◷' },
  { key: 'system', labelKey: 'shell.modes.system', icon: '⚙' },
  { key: 'organization', labelKey: 'shell.modes.organization', icon: '▦' },
]

const navItems = [
  { labelKey: 'nav.dashboard', to: '/', icon: '⌂' },
  { labelKey: 'nav.approvals', to: '/approvals', icon: '✓' },
  { labelKey: 'nav.organizations', to: '/organizations', icon: '▦' },
  { labelKey: 'nav.groups', to: '/admin/groups', icon: '⬡' },
  { labelKey: 'nav.products', to: '/products', icon: '◇' },
  { labelKey: 'nav.sales', to: '/sales', icon: '▤' },
  { labelKey: 'nav.users', to: '/admin/users', icon: 'ⓘ' },
]

const tabs = computed(() => {
  const current = navItems.find(
    (item) => route.path === item.to || route.path.startsWith(`${item.to}/`),
  )
  return current ? [{ label: t(current.labelKey), to: current.to }] : []
})

const visibleNavItems = computed(() => {
  if (activeMode.value === 'system')
    return navItems.filter((item) => ['/admin/users', '/admin/groups'].includes(item.to))
  if (activeMode.value === 'organization')
    return navItems.filter((item) => item.to === '/organizations')
  if (activeMode.value !== 'menu') return []
  return navItems
})

const activeModeLabel = computed(() => {
  const mode = sidebarModes.find((item) => item.key === activeMode.value) || sidebarModes[0]!
  return t(mode.labelKey)
})
const sidebarStyle = computed(() => ({
  '--sidebar-width': `${sidebarCollapsed.value ? 72 : sidebarWidth.value}px`,
}))

watch(
  () => route.path,
  () => {
    if (activeMode.value === 'menu') localStorage.setItem('ui.sidebar.active_mode', 'menu')
  },
  { immediate: true },
)

const commands = [
  { labelKey: 'commands.dashboard', to: '/' },
  { labelKey: 'commands.profile', to: '/profile' },
  { labelKey: 'commands.users', to: '/admin/users' },
  { labelKey: 'commands.organizations', to: '/organizations' },
  { labelKey: 'commands.products', to: '/products' },
]

function selectMode(mode: string): void {
  activeMode.value = mode
  localStorage.setItem('ui.sidebar.active_mode', mode)
  if (sidebarCollapsed.value) sidebarCollapsed.value = false
}

function toggleSidebar(): void {
  sidebarCollapsed.value = !sidebarCollapsed.value
  localStorage.setItem('ui.sidebar.collapsed', String(sidebarCollapsed.value))
}

function setSidebarWidth(width: number): void {
  sidebarWidth.value = Math.min(420, Math.max(240, width))
  localStorage.setItem('ui.sidebar.width', String(sidebarWidth.value))
}

let resizeMove: ((event: PointerEvent) => void) | null = null
let resizeEnd: (() => void) | null = null

function startResize(event: PointerEvent): void {
  if (sidebarCollapsed.value) return
  const startX = event.clientX
  const startWidth = sidebarWidth.value
  resizeMove = (moveEvent) => setSidebarWidth(startWidth + moveEvent.clientX - startX)
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
  if (event.key === 'Home') setSidebarWidth(240)
  else if (event.key === 'End') setSidebarWidth(420)
  else if (event.key === 'ArrowLeft') setSidebarWidth(sidebarWidth.value - 16)
  else if (event.key === 'ArrowRight') setSidebarWidth(sidebarWidth.value + 16)
}

function toggleLocale(): void {
  setLocale((locale.value === 'vi' ? 'en' : 'vi') as AppLocale)
}

function navigate(to: string): void {
  commandOpen.value = false
  mobileMenuOpen.value = false
  void router.push(to)
}

async function signOut(): Promise<void> {
  userMenuOpen.value = false
  await auth.logout()
  void router.push('/login')
}

onBeforeUnmount(() => resizeEnd?.())
</script>

<template>
  <div class="app-frame" :style="sidebarStyle">
    <header class="topbar">
      <button
        class="icon-button menu-trigger"
        :aria-label="t('shell.openMenu')"
        @click="mobileMenuOpen = !mobileMenuOpen"
      >
        ☰
      </button>
      <RouterLink class="brand" to="/">
        <span class="brand-mark">D</span>
        <span class="brand-copy"
          ><strong>Dinktech</strong><small>{{ t('common.brandSubtitle') }}</small></span
        >
      </RouterLink>
      <button class="workspace-search" type="button" @click="commandOpen = true">
        <span>⌕</span><span>{{ t('shell.searchWorkspace') }}</span
        ><kbd>/</kbd>
      </button>
      <div class="topbar-actions">
        <button class="icon-button notification-button" :aria-label="t('shell.notifications')">
          ♧<b>15</b>
        </button>
        <button class="icon-button" :aria-label="t('shell.theme')">☾</button>
        <button
          class="language-button"
          :aria-label="t('shell.language')"
          type="button"
          @click="toggleLocale"
        >
          {{ locale === 'vi' ? 'Tiếng Việt' : 'English' }}⌄
        </button>
        <div class="user-menu-wrap">
          <button class="user-chip" type="button" @click="userMenuOpen = !userMenuOpen">
            <span class="avatar">{{ (auth.user?.username || 'U').slice(0, 1).toUpperCase() }}</span
            ><span>{{ auth.user?.username || 'quyetdv' }}</span
            ><span>⌄</span>
          </button>
          <div v-if="userMenuOpen" class="user-menu">
            <button type="button" @click="navigate('/profile')">{{ t('shell.profile') }}</button>
            <button type="button" @click="signOut">{{ t('shell.signOut') }}</button>
          </div>
        </div>
      </div>
    </header>

    <aside
      class="sidebar"
      :class="{ 'sidebar--open': mobileMenuOpen, 'sidebar--collapsed': sidebarCollapsed }"
    >
      <div class="sidebar-search"><span>⌕</span><input :placeholder="t('shell.searchMenu')" /></div>
      <div class="sidebar-tools">
        <button
          v-for="mode in sidebarModes"
          :key="mode.key"
          :class="{ active: activeMode === mode.key }"
          :aria-label="t(mode.labelKey)"
          :title="t(mode.labelKey)"
          type="button"
          @click="selectMode(mode.key)"
        >
          {{ mode.icon }}
        </button>
      </div>
      <button
        class="sidebar-collapse"
        type="button"
        :aria-label="t('shell.openMenu')"
        @click="toggleSidebar"
      >
        {{ sidebarCollapsed ? '›' : '‹' }}
      </button>
      <div
        v-if="!sidebarCollapsed"
        class="sidebar-resize"
        role="separator"
        aria-orientation="vertical"
        tabindex="0"
        @pointerdown="startResize"
        @dblclick="setSidebarWidth(290)"
        @keydown="handleResizeKey"
      ></div>
      <nav class="nav-list">
        <p class="nav-section">{{ activeModeLabel }}</p>
        <RouterLink
          v-for="item in visibleNavItems"
          :key="item.to"
          :to="item.to"
          class="nav-link"
          :class="{
            'nav-link--active': route.path === item.to || route.path.startsWith(`${item.to}/`),
          }"
          @click="mobileMenuOpen = false"
        >
          <span class="nav-icon">{{ item.icon }}</span
          ><span>{{ t(item.labelKey) }}</span
          ><span
            v-if="item.to === '/organizations' || item.to === '/admin/users'"
            class="nav-chevron"
            >›</span
          >
        </RouterLink>
      </nav>
      <div class="sidebar-footer">♢ &nbsp; {{ t('shell.filteredByPermission') }}</div>
    </aside>

    <main class="workspace">
      <div class="tabs-bar">
        <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="workspace-tab"
          >{{ tab.label }} <span>×</span></RouterLink
        ><button class="tab-add" :aria-label="t('shell.openNewTab')">＋</button>
      </div>
      <div class="page-content"><RouterView /></div>
    </main>

    <div v-if="commandOpen" class="overlay" @click.self="commandOpen = false">
      <section
        class="command-palette"
        role="dialog"
        aria-modal="true"
        :aria-label="t('shell.commandPalette')"
      >
        <div class="command-heading">
          <span>⌕</span>
          <div>
            <strong>{{ t('shell.commandPalette') }}</strong
            ><small>{{ t('shell.commandDescription') }}</small>
          </div>
          <button class="icon-button" :aria-label="t('shell.close')" @click="commandOpen = false">
            ×
          </button>
        </div>
        <input autofocus class="command-input" :placeholder="t('shell.searchCommand')" />
        <button
          v-for="command in commands"
          :key="command.to"
          class="command-item"
          type="button"
          @click="navigate(command.to)"
        >
          <span>◇</span>{{ t(command.labelKey) }}
        </button>
        <footer class="command-footer">
          <kbd>Esc</kbd> {{ t('shell.commandClose') }} <kbd>↑</kbd><kbd>↓</kbd>
          {{ t('shell.commandMove') }} <kbd>Enter</kbd> {{ t('shell.commandOpen') }}
        </footer>
      </section>
    </div>
  </div>
</template>
