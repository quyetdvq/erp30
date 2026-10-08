import { createRouter, createWebHistory } from 'vue-router'

import AppShell from '@/components/AppShell.vue'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { useWorkspaceStore } from '@/stores/workspace'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true, closable: false },
    },
    {
      path: '/',
      component: AppShell,
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
          meta: { labelKey: 'nav.dashboard', closable: false },
        },
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/views/ProfileView.vue'),
          meta: { labelKey: 'shell.profile' },
        },
        {
          path: 'profile/display-preferences',
          name: 'display-preferences',
          component: () => import('@/views/DisplayPreferencesView.vue'),
          meta: { labelKey: 'shell.displayPreferences' },
        },
        {
          path: 'system/display-settings',
          name: 'display-settings',
          component: () => import('@/views/DisplaySettingsView.vue'),
          meta: { labelKey: 'nav.displaySettings' },
        },
        {
          path: 'security/users',
          name: 'security-users',
          component: () => import('@/views/SecurityUsersView.vue'),
          meta: { labelKey: 'nav.securityUsers' },
        },
        {
          path: 'security/authorization-groups',
          name: 'security-authorization-groups',
          component: () => import('@/views/SecurityAuthorizationGroupsView.vue'),
          meta: { labelKey: 'nav.authorizationGroups' },
        },
        {
          path: 'security/resources',
          name: 'security-resources',
          component: () => import('@/views/SecurityResourcesView.vue'),
          meta: { labelKey: 'nav.resources' },
        },
        {
          path: 'security/policies',
          name: 'security-policies',
          component: () => import('@/views/SecurityPoliciesView.vue'),
          meta: { labelKey: 'nav.policies' },
        },
        {
          path: 'security/authorization-logs',
          name: 'security-authorization-logs',
          component: () => import('@/views/SecurityAuthorizationLogsView.vue'),
          meta: { labelKey: 'nav.authorizationLogs' },
        },
        {
          path: 'configuration/workflow-states',
          name: 'configuration-workflow-states',
          component: () => import('@/views/WorkflowStatesView.vue'),
          meta: { labelKey: 'nav.workflowStates' },
        },
        {
          path: 'configuration/lifecycles',
          name: 'configuration-lifecycles',
          component: () => import('@/views/LifecyclesView.vue'),
          meta: { labelKey: 'nav.lifecycles' },
        },
        {
          path: 'configuration/auto-numbering',
          name: 'configuration-auto-numbering',
          component: () => import('@/views/AutoNumberingView.vue'),
          meta: { labelKey: 'nav.autoNumbering' },
        },
        {
          path: 'products/groups',
          name: 'products-groups',
          component: () => import('@/views/ProductGroupsView.vue'),
          meta: { labelKey: 'nav.productGroups' },
        },
        {
          path: 'products/uoms',
          name: 'products-uoms',
          component: () => import('@/views/ProductUomsView.vue'),
          meta: { labelKey: 'nav.productUoms' },
        },
        {
          path: 'products/price-lists',
          name: 'products-price-lists',
          component: () => import('@/views/PriceListsView.vue'),
          meta: { labelKey: 'nav.productPricePolicies' },
        },
        {
          path: 'products/new',
          name: 'products-new',
          component: () => import('@/views/ProductEditorView.vue'),
          meta: { labelKey: 'nav.productCreate' },
        },
        {
          path: 'products/:id',
          name: 'products-edit',
          component: () => import('@/views/ProductEditorView.vue'),
          meta: { labelKey: 'nav.productEdit' },
        },
        {
          path: 'products',
          name: 'products',
          component: () => import('@/views/ProductsView.vue'),
          meta: { labelKey: 'nav.products' },
        },
        {
          path: 'organizations',
          name: 'organizations',
          component: () => import('@/views/OrganizationsView.vue'),
          meta: { labelKey: 'nav.organizations' },
        },
        {
          path: 'settings/locations/provinces',
          name: 'locations-provinces',
          component: () => import('@/views/LocationsView.vue'),
          meta: { labelKey: 'nav.locations' },
        },
        {
          path: 'settings/locations/wards',
          name: 'locations-wards',
          component: () => import('@/views/LocationsView.vue'),
          meta: { labelKey: 'nav.locations' },
        },
        {
          path: 'people/departments',
          name: 'people-departments',
          component: () => import('@/views/PeopleDepartmentsView.vue'),
          meta: { labelKey: 'nav.departments' },
        },
        {
          path: 'people/positions',
          name: 'people-positions',
          component: () => import('@/views/PeoplePositionsView.vue'),
          meta: { labelKey: 'nav.positions' },
        },
        {
          path: 'people/employees',
          name: 'people-employees',
          component: () => import('@/views/PeopleEmployeesView.vue'),
          meta: { labelKey: 'nav.employees' },
        },
        {
          path: ':pathMatch(.*)*',
          name: 'not-found',
          component: () => import('@/views/NotFoundView.vue'),
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
