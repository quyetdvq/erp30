import { createRouter, createWebHistory } from 'vue-router'

import AppShell from '@/components/AppShell.vue'
import { useAuthStore } from '@/stores/auth'
import DashboardView from '@/views/DashboardView.vue'
import GroupsView from '@/views/GroupsView.vue'
import LoginView from '@/views/LoginView.vue'
import OrganizationsView from '@/views/OrganizationsView.vue'
import PlaceholderView from '@/views/PlaceholderView.vue'
import ProfileView from '@/views/ProfileView.vue'
import UsersView from '@/views/UsersView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/login', name: 'login', component: LoginView, meta: { public: true } },
    {
      path: '/',
      component: AppShell,
      children: [
        { path: '', name: 'dashboard', component: DashboardView },
        { path: 'profile', name: 'profile', component: ProfileView },
        { path: 'admin/users', name: 'users', component: UsersView, meta: { admin: true } },
        { path: 'admin/groups', name: 'groups', component: GroupsView, meta: { admin: true } },
        { path: 'organizations', name: 'organizations', component: OrganizationsView, meta: { admin: true } },
        { path: 'approvals', name: 'approvals', component: PlaceholderView, props: { title: 'Phê duyệt của tôi', description: 'Các yêu cầu đang chờ bạn xử lý.' } },
        { path: 'products', name: 'products', component: PlaceholderView, props: { title: 'Sản phẩm và vòng đời', description: 'Danh mục sản phẩm và lifecycle của doanh nghiệp.' } },
        { path: 'sales', name: 'sales', component: PlaceholderView, props: { title: 'Bán hàng và khách hàng', description: 'Không gian quản lý bán hàng và quan hệ khách hàng.' } },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.hydrate()
  if (to.meta.public) {
    if (auth.isAuthenticated) return '/'
    return true
  }
  if (!auth.isAuthenticated) return '/login'
  if (to.meta.admin && !auth.isAdmin) return '/'
  return true
})

export default router
