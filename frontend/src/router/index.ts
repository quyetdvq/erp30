import { createRouter, createWebHistory } from 'vue-router'

import AppShell from '@/components/AppShell.vue'
import { breadcrumbTrails } from '@/router/breadcrumbs'
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
          meta: { labelKey: 'shell.profile', breadcrumbTrail: breadcrumbTrails.account },
        },
        {
          path: 'profile/display-preferences',
          name: 'display-preferences',
          component: () => import('@/views/DisplayPreferencesView.vue'),
          meta: {
            labelKey: 'shell.displayPreferences',
            breadcrumbTrail: breadcrumbTrails.displayPreferences,
          },
        },
        {
          path: 'system/display-settings',
          name: 'display-settings',
          component: () => import('@/views/DisplaySettingsView.vue'),
          meta: {
            labelKey: 'nav.displaySettings',
            breadcrumbTrail: breadcrumbTrails.configuration,
          },
        },
        {
          path: 'security/users',
          name: 'security-users',
          component: () => import('@/views/SecurityUsersView.vue'),
          meta: { labelKey: 'nav.securityUsers', breadcrumbTrail: breadcrumbTrails.security },
        },
        {
          path: 'security/authorization-groups',
          name: 'security-authorization-groups',
          component: () => import('@/views/SecurityAuthorizationGroupsView.vue'),
          meta: { labelKey: 'nav.authorizationGroups', breadcrumbTrail: breadcrumbTrails.security },
        },
        {
          path: 'security/resources',
          name: 'security-resources',
          component: () => import('@/views/SecurityResourcesView.vue'),
          meta: { labelKey: 'nav.resources', breadcrumbTrail: breadcrumbTrails.security },
        },
        {
          path: 'security/policies',
          name: 'security-policies',
          component: () => import('@/views/SecurityPoliciesView.vue'),
          meta: { labelKey: 'nav.policies', breadcrumbTrail: breadcrumbTrails.security },
        },
        {
          path: 'security/authorization-logs',
          name: 'security-authorization-logs',
          component: () => import('@/views/SecurityAuthorizationLogsView.vue'),
          meta: { labelKey: 'nav.authorizationLogs', breadcrumbTrail: breadcrumbTrails.security },
        },
        {
          path: 'configuration/workflow-states',
          name: 'configuration-workflow-states',
          component: () => import('@/views/WorkflowStatesView.vue'),
          meta: { labelKey: 'nav.workflowStates', breadcrumbTrail: breadcrumbTrails.configuration },
        },
        {
          path: 'configuration/lifecycles',
          name: 'configuration-lifecycles',
          component: () => import('@/views/LifecyclesView.vue'),
          meta: { labelKey: 'nav.lifecycles', breadcrumbTrail: breadcrumbTrails.configuration },
        },
        {
          path: 'configuration/auto-numbering',
          name: 'configuration-auto-numbering',
          component: () => import('@/views/AutoNumberingView.vue'),
          meta: { labelKey: 'nav.autoNumbering', breadcrumbTrail: breadcrumbTrails.configuration },
        },
        {
          path: 'products/groups',
          name: 'products-groups',
          component: () => import('@/views/ProductGroupsView.vue'),
          meta: { labelKey: 'nav.productGroups', breadcrumbTrail: breadcrumbTrails.products },
        },
        {
          path: 'products/uoms',
          name: 'products-uoms',
          component: () => import('@/views/ProductUomsView.vue'),
          meta: { labelKey: 'nav.productUoms', breadcrumbTrail: breadcrumbTrails.products },
        },
        {
          path: 'products/price-lists',
          name: 'products-price-lists',
          component: () => import('@/views/PriceListsView.vue'),
          meta: {
            labelKey: 'nav.productPricePolicies',
            breadcrumbTrail: breadcrumbTrails.products,
          },
        },
        {
          path: 'products/import',
          name: 'products-import',
          component: () => import('@/views/ProductImportView.vue'),
          meta: { labelKey: 'nav.productImport', breadcrumbTrail: breadcrumbTrails.products },
        },
        {
          path: 'products/import-jobs/:id',
          name: 'products-import-job-detail',
          component: () => import('@/views/ProductImportJobDetailView.vue'),
          meta: {
            labelKey: 'nav.productImportJobDetail',
            breadcrumbTrail: breadcrumbTrails.importJobDetail,
          },
        },
        {
          path: 'products/import-jobs',
          name: 'products-import-jobs',
          component: () => import('@/views/ProductImportJobsView.vue'),
          meta: { labelKey: 'nav.productImportJobs', breadcrumbTrail: breadcrumbTrails.products },
        },
        {
          path: 'products/new',
          name: 'products-new',
          component: () => import('@/views/ProductEditorView.vue'),
          meta: { labelKey: 'nav.productCreate', breadcrumbTrail: breadcrumbTrails.products },
        },
        {
          path: 'products/:id',
          name: 'products-edit',
          component: () => import('@/views/ProductEditorView.vue'),
          meta: { labelKey: 'nav.productEdit', breadcrumbTrail: breadcrumbTrails.products },
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
          meta: { labelKey: 'nav.organizations', breadcrumbTrail: breadcrumbTrails.organization },
        },
        {
          path: 'settings/locations/provinces',
          name: 'locations-provinces',
          component: () => import('@/views/LocationsView.vue'),
          meta: { labelKey: 'nav.locations', breadcrumbTrail: breadcrumbTrails.locations },
        },
        {
          path: 'settings/locations/wards',
          name: 'locations-wards',
          component: () => import('@/views/LocationsView.vue'),
          meta: { labelKey: 'nav.wards', breadcrumbTrail: breadcrumbTrails.locations },
        },
        {
          path: 'people/departments',
          name: 'people-departments',
          component: () => import('@/views/PeopleDepartmentsView.vue'),
          meta: { labelKey: 'nav.departments', breadcrumbTrail: breadcrumbTrails.people },
        },
        {
          path: 'people/positions',
          name: 'people-positions',
          component: () => import('@/views/PeoplePositionsView.vue'),
          meta: { labelKey: 'nav.positions', breadcrumbTrail: breadcrumbTrails.people },
        },
        {
          path: 'people/employees',
          name: 'people-employees',
          component: () => import('@/views/PeopleEmployeesView.vue'),
          meta: { labelKey: 'nav.employees', breadcrumbTrail: breadcrumbTrails.people },
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
