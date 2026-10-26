import type { RouteRecordRaw } from 'vue-router'

import AppShell from '@/components/AppShell.vue'
import { breadcrumbTrails } from '@/router/breadcrumbs'

// Preserve lazy imports, path priority, route names and metadata.
// Authentication/dirty navigation behavior lives in router/index.ts.
export const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/features/auth/pages/LoginView.vue'),
    meta: { public: true, closable: false },
  },
  {
    path: '/',
    component: AppShell,
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/features/workspace/pages/DashboardView.vue'),
        meta: { labelKey: 'nav.dashboard', closable: false },
      },
      {
        path: 'notifications',
        name: 'notifications-inbox',
        component: () => import('@/features/notifications/pages/NotificationsInboxView.vue'),
        meta: { labelKey: 'notificationsInbox.title', breadcrumbTrail: breadcrumbTrails.account },
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/features/account/pages/ProfileView.vue'),
        meta: { labelKey: 'shell.profile', breadcrumbTrail: breadcrumbTrails.account },
      },
      {
        path: 'profile/display-preferences',
        name: 'display-preferences',
        component: () => import('@/features/account/pages/DisplayPreferencesView.vue'),
        meta: {
          labelKey: 'shell.displayPreferences',
          breadcrumbTrail: breadcrumbTrails.displayPreferences,
        },
      },
      {
        path: 'system/configuration-settings',
        name: 'configuration-settings',
        component: () => import('@/features/settings/pages/ConfigurationSettingsView.vue'),
        meta: { labelKey: 'nav.configurationSettings', breadcrumbTrail: breadcrumbTrails.configuration },
      },
      {
        path: 'system/display-settings',
        name: 'display-settings',
        component: () => import('@/features/settings/pages/DisplaySettingsView.vue'),
        meta: {
          labelKey: 'nav.displaySettings',
          breadcrumbTrail: breadcrumbTrails.configuration,
        },
      },
      {
        path: 'security/users',
        name: 'security-users',
        component: () => import('@/features/security/pages/SecurityUsersView.vue'),
        meta: { labelKey: 'nav.securityUsers', breadcrumbTrail: breadcrumbTrails.security },
      },
      {
        path: 'security/authorization-groups',
        name: 'security-authorization-groups',
        component: () => import('@/features/security/pages/SecurityAuthorizationGroupsView.vue'),
        meta: { labelKey: 'nav.authorizationGroups', breadcrumbTrail: breadcrumbTrails.security },
      },
      {
        path: 'security/resources',
        name: 'security-resources',
        component: () => import('@/features/security/pages/SecurityResourcesView.vue'),
        meta: { labelKey: 'nav.resources', breadcrumbTrail: breadcrumbTrails.security },
      },
      {
        path: 'security/policies',
        name: 'security-policies',
        component: () => import('@/features/security/pages/SecurityPoliciesView.vue'),
        meta: { labelKey: 'nav.policies', breadcrumbTrail: breadcrumbTrails.security },
      },
      {
        path: 'security/authorization-logs',
        name: 'security-authorization-logs',
        component: () => import('@/features/security/pages/SecurityAuthorizationLogsView.vue'),
        meta: { labelKey: 'nav.authorizationLogs', breadcrumbTrail: breadcrumbTrails.security },
      },
      {
        path: 'configuration/workflow-states',
        name: 'configuration-workflow-states',
        component: () => import('@/features/workflow/pages/WorkflowStatesView.vue'),
        meta: { labelKey: 'nav.workflowStates', breadcrumbTrail: breadcrumbTrails.configuration },
      },
      {
        path: 'configuration/lifecycles',
        name: 'configuration-lifecycles',
        component: () => import('@/features/workflow/pages/LifecyclesView.vue'),
        meta: { labelKey: 'nav.lifecycles', breadcrumbTrail: breadcrumbTrails.configuration },
      },
      {
        path: 'configuration/auto-numbering',
        name: 'configuration-auto-numbering',
        component: () => import('@/features/workflow/pages/AutoNumberingView.vue'),
        meta: { labelKey: 'nav.autoNumbering', breadcrumbTrail: breadcrumbTrails.configuration },
      },
      {
        path: 'products/groups',
        name: 'products-groups',
        component: () => import('@/features/catalog/pages/ProductGroupsView.vue'),
        meta: { labelKey: 'nav.productGroups', breadcrumbTrail: breadcrumbTrails.products },
      },
      {
        path: 'products/uoms',
        name: 'products-uoms',
        component: () => import('@/features/catalog/pages/ProductUomsView.vue'),
        meta: { labelKey: 'nav.productUoms', breadcrumbTrail: breadcrumbTrails.products },
      },
      {
        path: 'products/price-lists',
        name: 'products-price-lists',
        component: () => import('@/features/catalog/pages/PriceListsView.vue'),
        meta: {
          labelKey: 'nav.productPricePolicies',
          breadcrumbTrail: breadcrumbTrails.products,
        },
      },
      {
        path: 'products/import',
        name: 'products-import',
        component: () => import('@/features/product-import/pages/ProductImportView.vue'),
        meta: { labelKey: 'nav.productImport', breadcrumbTrail: breadcrumbTrails.products },
      },
      {
        path: 'products/import-jobs/:id',
        name: 'products-import-job-detail',
        component: () => import('@/features/product-import/pages/ProductImportJobDetailView.vue'),
        meta: {
          labelKey: 'nav.productImportJobDetail',
          breadcrumbTrail: breadcrumbTrails.importJobDetail,
        },
      },
      {
        path: 'products/import-jobs',
        name: 'products-import-jobs',
        component: () => import('@/features/product-import/pages/ProductImportJobsView.vue'),
        meta: { labelKey: 'nav.productImportJobs', breadcrumbTrail: breadcrumbTrails.products },
      },
      {
        path: 'products/new',
        name: 'products-new',
        component: () => import('@/features/catalog/pages/ProductEditorView.vue'),
        meta: { labelKey: 'nav.productCreate', breadcrumbTrail: breadcrumbTrails.products },
      },
      {
        path: 'products/:id',
        name: 'products-edit',
        component: () => import('@/features/catalog/pages/ProductEditorView.vue'),
        meta: { labelKey: 'nav.productEdit', breadcrumbTrail: breadcrumbTrails.products },
      },
      {
        path: 'products',
        name: 'products',
        component: () => import('@/features/catalog/pages/ProductsView.vue'),
        meta: { labelKey: 'nav.products' },
      },
      {
        path: 'organizations',
        name: 'organizations',
        component: () => import('@/features/organization/pages/OrganizationsView.vue'),
        meta: { labelKey: 'nav.organizations', breadcrumbTrail: breadcrumbTrails.organization },
      },
      {
        path: 'settings/locations/provinces',
        name: 'locations-provinces',
        component: () => import('@/features/organization/pages/LocationsView.vue'),
        meta: { labelKey: 'nav.locations', breadcrumbTrail: breadcrumbTrails.locations },
      },
      {
        path: 'settings/locations/wards',
        name: 'locations-wards',
        component: () => import('@/features/organization/pages/LocationsView.vue'),
        meta: { labelKey: 'nav.wards', breadcrumbTrail: breadcrumbTrails.locations },
      },
      {
        path: 'people/departments',
        name: 'people-departments',
        component: () => import('@/features/people/pages/PeopleDepartmentsView.vue'),
        meta: { labelKey: 'nav.departments', breadcrumbTrail: breadcrumbTrails.people },
      },
      {
        path: 'people/positions',
        name: 'people-positions',
        component: () => import('@/features/people/pages/PeoplePositionsView.vue'),
        meta: { labelKey: 'nav.positions', breadcrumbTrail: breadcrumbTrails.people },
      },
      {
        path: 'people/employees',
        name: 'people-employees',
        component: () => import('@/features/people/pages/PeopleEmployeesView.vue'),
        meta: { labelKey: 'nav.employees', breadcrumbTrail: breadcrumbTrails.people },
      },
      {
        path: ':pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/app/pages/NotFoundView.vue'),
        meta: { labelKey: 'errors.status.404' },
      },
    ],
  },
]
