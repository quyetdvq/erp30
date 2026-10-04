<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const commandOpen = ref(false)
const mobileMenuOpen = ref(false)
const userMenuOpen = ref(false)
const activeMenuGroup = ref('WORKSPACE')

const menuGroups = [
  { key: 'WORKSPACE', label: 'Workspace', icon: '☰' },
  { key: 'QUẢN TRỊ', label: 'Quản trị', icon: '☆' },
  { key: 'NỀN TẢNG', label: 'Nền tảng', icon: '◷' },
  { key: 'NGHIỆP VỤ', label: 'Nghiệp vụ', icon: '⚙' },
]

const navItems = [
  { label: 'Tổng quan', to: '/', icon: '⌂', section: 'WORKSPACE' },
  { label: 'Phê duyệt của tôi', to: '/approvals', icon: '✓', section: 'WORKSPACE' },
  { label: 'Quản trị tổ chức và dữ liệu nền', to: '/organizations', icon: '▦', section: 'QUẢN TRỊ' },
  { label: 'Nhóm và permission', to: '/admin/groups', icon: '⬡', section: 'QUẢN TRỊ' },
  { label: 'Sản phẩm và vòng đời', to: '/products', icon: '◇', section: 'NỀN TẢNG' },
  { label: 'Bán hàng và khách hàng', to: '/sales', icon: '▤', section: 'NGHIỆP VỤ' },
  { label: 'Người dùng', to: '/admin/users', icon: 'ⓘ', section: 'QUẢN TRỊ' },
]

const tabs = computed(() => {
  const current = navItems.find((item) => route.path === item.to || route.path.startsWith(`${item.to}/`))
  return current ? [{ label: current.label, to: current.to }] : []
})

const visibleNavItems = computed(() => navItems.filter((item) => item.section === activeMenuGroup.value))

watch(() => route.path, (path) => {
  const item = navItems.find((nav) => path === nav.to || path.startsWith(`${nav.to}/`))
  if (item) activeMenuGroup.value = item.section
}, { immediate: true })

const commands = [
  { label: 'Về trang tổng quan', to: '/' },
  { label: 'Chỉnh sửa profile', to: '/profile' },
  { label: 'Quản lý người dùng và phân quyền', to: '/admin/users' },
  { label: 'Quản trị tổ chức', to: '/organizations' },
  { label: 'Sản phẩm và vòng đời', to: '/products' },
]

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
</script>

<template>
  <div class="app-frame">
    <header class="topbar">
      <button class="icon-button menu-trigger" aria-label="Mở menu" @click="mobileMenuOpen = !mobileMenuOpen">☰</button>
      <RouterLink class="brand" to="/">
        <span class="brand-mark">D</span>
        <span class="brand-copy"><strong>Dinktech</strong><small>ERP Workspace</small></span>
      </RouterLink>
      <button class="workspace-search" type="button" @click="commandOpen = true"><span>⌕</span><span>Tìm trong workspace</span><kbd>/</kbd></button>
      <div class="topbar-actions">
        <button class="icon-button notification-button" aria-label="Thông báo">♧<b>15</b></button>
        <button class="icon-button" aria-label="Đổi giao diện">☾</button>
        <button class="language-button">Tiếng Việt⌄</button>
        <div class="user-menu-wrap">
          <button class="user-chip" type="button" @click="userMenuOpen = !userMenuOpen"><span class="avatar">{{ (auth.user?.username || 'U').slice(0, 1).toUpperCase() }}</span><span>{{ auth.user?.username || 'quyetdv' }}</span><span>⌄</span></button>
          <div v-if="userMenuOpen" class="user-menu">
            <button type="button" @click="navigate('/profile')">Hồ sơ cá nhân</button>
            <button type="button" @click="signOut">Đăng xuất</button>
          </div>
        </div>
      </div>
    </header>

    <aside class="sidebar" :class="{ 'sidebar--open': mobileMenuOpen }">
      <div class="sidebar-search"><span>⌕</span><input placeholder="Tìm menu" /></div>
      <div class="sidebar-tools">
        <button v-for="group in menuGroups" :key="group.key" :class="{ active: activeMenuGroup === group.key }" :aria-label="`Mở nhóm ${group.label}`" type="button" @click="activeMenuGroup = group.key">{{ group.icon }}</button>
        </div>
      <nav class="nav-list">
        <p class="nav-section">{{ activeMenuGroup }}</p>
        <RouterLink v-for="item in visibleNavItems" :key="item.to" :to="item.to" class="nav-link" :class="{ 'nav-link--active': route.path === item.to || route.path.startsWith(`${item.to}/`) }" @click="mobileMenuOpen = false">
          <span class="nav-icon">{{ item.icon }}</span><span>{{ item.label }}</span><span v-if="item.to === '/organizations' || item.to === '/admin/users'" class="nav-chevron">›</span>
        </RouterLink>
      </nav>
      <div class="sidebar-footer">♢ &nbsp; Đã lọc theo quyền</div>
    </aside>

    <main class="workspace">
      <div class="tabs-bar"><RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="workspace-tab">{{ tab.label }} <span>×</span></RouterLink><button class="tab-add" aria-label="Mở tab mới">＋</button></div>
      <div class="page-content"><RouterView /></div>
    </main>

    <div v-if="commandOpen" class="overlay" @click.self="commandOpen = false">
      <section class="command-palette" role="dialog" aria-modal="true" aria-label="Command palette">
        <div class="command-heading"><span>⌕</span><div><strong>Command palette</strong><small>Đi tới màn hình hoặc lệnh trong workspace</small></div><button class="icon-button" aria-label="Đóng" @click="commandOpen = false">×</button></div>
        <input autofocus class="command-input" placeholder="Tìm lệnh" />
        <button v-for="command in commands" :key="command.to" class="command-item" type="button" @click="navigate(command.to)"><span>◇</span>{{ command.label }}</button>
        <footer class="command-footer"><kbd>Esc</kbd> Đóng <kbd>↑</kbd><kbd>↓</kbd> Di chuyển <kbd>Enter</kbd> Mở</footer>
      </section>
    </div>
  </div>
</template>
