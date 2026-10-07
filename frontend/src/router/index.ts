import { createRouter, createWebHistory } from 'vue-router'

import AppShell from '@/components/AppShell.vue'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import DashboardView from '@/views/DashboardView.vue'
import DisplayPreferencesView from '@/views/DisplayPreferencesView.vue'
import DisplaySettingsView from '@/views/DisplaySettingsView.vue'
import GroupsView from '@/views/GroupsView.vue'
import LoginView from '@/views/LoginView.vue'
import LocationsView from '@/views/LocationsView.vue'
import OrganizationsView from '@/views/OrganizationsView.vue'
import PlaceholderView from '@/views/PlaceholderView.vue'
import ProfileView from '@/views/ProfileView.vue'
import NotFoundView from '@/views/NotFoundView.vue'
import UsersView from '@/views/UsersView.vue'
import { useWorkspaceStore } from '@/stores/workspace'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { public: true, closable: false },
    },
    {
      path: '/',
      component: AppShell,
      children: [
        {
          path: '',
          name: 'dashboard',
          component: DashboardView,
          meta: { labelKey: 'nav.dashboard', closable: false },
        },
        {
          path: 'profile',
          name: 'profile',
          component: ProfileView,
          meta: { labelKey: 'shell.profile' },
        },
        {
          path: 'profile/display-preferences',
          name: 'display-preferences',
          component: DisplayPreferencesView,
          meta: { labelKey: 'shell.displayPreferences' },
        },
        {
          path: 'system/display-settings',
          name: 'display-settings',
          component: DisplaySettingsView,
          meta: { labelKey: 'nav.displaySettings' },
        },
        {
          path: 'admin/users',
          name: 'users',
          component: UsersView,
          meta: { labelKey: 'nav.users' },
        },
        {
          path: 'admin/groups',
          name: 'groups',
          component: GroupsView,
          meta: { labelKey: 'nav.groups' },
        },
        {
          path: 'organizations',
          name: 'organizations',
          component: OrganizationsView,
          meta: { labelKey: 'nav.organizations' },
        },
        {
          path: 'settings/locations/provinces',
          name: 'locations-provinces',
          component: LocationsView,
          meta: { labelKey: 'nav.locations' },
        },
        {
          path: 'settings/locations/wards',
          name: 'locations-wards',
          component: LocationsView,
          meta: { labelKey: 'nav.locations' },
        },
        {
          path: 'approvals',
          name: 'approvals',
          component: PlaceholderView,
          meta: { labelKey: 'nav.approvals' },
          props: { title: 'Phê duyệt của tôi', description: 'Các yêu cầu đang chờ bạn xử lý.' },
        },
        {
          path: 'products',
          name: 'products',
          component: PlaceholderView,
          props: {
            title: 'Sản phẩm và vòng đời',
            description: 'Danh mục sản phẩm và lifecycle của doanh nghiệp.',
          },
          meta: { labelKey: 'nav.products' },
        },
        {
          path: 'sales',
          name: 'sales',
          component: PlaceholderView,
          props: {
            title: 'Bán hàng và khách hàng',
            description: 'Không gian quản lý bán hàng và quan hệ khách hàng.',
          },
          meta: { labelKey: 'nav.sales' },
        },
        {
          path: ':pathMatch(.*)*',
          name: 'not-found',
          component: NotFoundView,
          meta: { labelKey: 'errors.status.404' },
        },
      ],
    },
  ],
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
