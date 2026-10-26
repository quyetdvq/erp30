import {
  BarcodeOutline,
  BriefcaseOutline,
  BusinessOutline,
  CloudUploadOutline,
  CubeOutline,
  FlagOutline,
  FolderOpenOutline,
  HomeOutline,
  IdCardOutline,
  LayersOutline,
  LocationOutline,
  MapOutline,
  MenuOutline,
  OptionsOutline,
  PeopleCircleOutline,
  PeopleOutline,
  PersonCircleOutline,
  PricetagsOutline,
  ReaderOutline,
  RepeatOutline,
  ScaleOutline,
  SettingsOutline,
  ShieldCheckmarkOutline,
  StarOutline,
  TimeOutline,
} from '@vicons/ionicons5'

// Static, presentation-only menu metadata; backend RBAC/PBAC remains authoritative.
export const sidebarModes = [
  { key: 'menu', labelKey: 'shell.modes.menu', icon: MenuOutline },
  { key: 'favorites', labelKey: 'shell.modes.favorites', icon: StarOutline },
  { key: 'recent', labelKey: 'shell.modes.recent', icon: TimeOutline },
  { key: 'system', labelKey: 'shell.modes.system', icon: SettingsOutline },
  { key: 'organization', labelKey: 'shell.modes.organization', icon: BusinessOutline },
] as const

export type NavigationItem = {
  labelKey: string
  to: string
  icon: object
  permission?: string
  permissionAny?: string[]
  children?: NavigationItem[]
  disabled?: boolean
}

export const SYSTEM_NAV_PATHS = new Set([
  '/system/configuration-settings',
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
export const ORGANIZATION_NAV_PATHS = new Set([
  '/organizations',
  '/settings/locations/provinces',
  '/settings/locations/wards',
  '/people/departments',
  '/people/positions',
  '/people/employees',
])

export const navItems: NavigationItem[] = [
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
    icon: MapOutline,
    permission: 'province.view',
  },
  {
    labelKey: 'nav.wards',
    to: '/settings/locations/wards',
    icon: LocationOutline,
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
    icon: BriefcaseOutline,
    permission: 'position.view',
  },
  {
    labelKey: 'nav.employees',
    to: '/people/employees',
    icon: IdCardOutline,
    permission: 'employee.view',
  },
  {
    labelKey: 'nav.configurationSettings',
    to: '/system/configuration-settings',
    icon: OptionsOutline,
    permission: 'notification_setting.view',
  },
  {
    labelKey: 'nav.displaySettings',
    to: '/system/display-settings',
    icon: OptionsOutline,
    permissionAny: ['display_setting.view', 'notification_setting.view'],
  },
  {
    labelKey: 'nav.securityUsers',
    to: '/security/users',
    icon: PersonCircleOutline,
    permission: 'user.view',
  },
  {
    labelKey: 'nav.authorizationGroups',
    to: '/security/authorization-groups',
    icon: PeopleCircleOutline,
    permission: 'authorization_group.view',
  },
  {
    labelKey: 'nav.resources',
    to: '/security/resources',
    icon: LayersOutline,
    permission: 'resource.view',
  },
  {
    labelKey: 'nav.policies',
    to: '/security/policies',
    icon: ShieldCheckmarkOutline,
    permission: 'policy.view',
  },
  {
    labelKey: 'nav.authorizationLogs',
    to: '/security/authorization-logs',
    icon: ReaderOutline,
    permission: 'authorization_log.view',
  },
  {
    labelKey: 'nav.workflowStates',
    to: '/configuration/workflow-states',
    icon: FlagOutline,
    permission: 'workflow_state.view',
  },
  {
    labelKey: 'nav.lifecycles',
    to: '/configuration/lifecycles',
    icon: RepeatOutline,
    permission: 'lifecycle.view',
  },
  {
    labelKey: 'nav.autoNumbering',
    to: '/configuration/auto-numbering',
    icon: BarcodeOutline,
    permission: 'resource.configure_numbering',
  },
  {
    labelKey: 'nav.products',
    to: '/products',
    icon: CubeOutline,
    children: [
      {
        labelKey: 'nav.productGroups',
        to: '/products/groups',
        icon: FolderOpenOutline,
        permission: 'product_group.view',
      },
      {
        labelKey: 'nav.products',
        to: '/products',
        icon: CubeOutline,
        permission: 'product.view',
      },
      {
        labelKey: 'nav.productUoms',
        to: '/products/uoms',
        icon: ScaleOutline,
        permission: 'product_uom.view',
      },
      {
        labelKey: 'nav.productPricePolicies',
        to: '/products/price-lists',
        icon: PricetagsOutline,
        permission: 'price_list.view',
      },
      {
        labelKey: 'nav.productImport',
        to: '/products/import',
        icon: CloudUploadOutline,
        permission: 'product.import',
      },
      {
        labelKey: 'nav.productImportJobs',
        to: '/products/import-jobs',
        icon: TimeOutline,
        permission: 'import_job.view',
      },
    ],
  },
]

// Detail and self-service routes are absent from the sidebar menu but valid in Recent.
// These permissions are presentation hints only; the API still enforces authorization.
export const RECENT_ROUTE_HINTS = new Map<string, Pick<NavigationItem, 'icon' | 'permission'>>([
  ['products-new', { icon: CubeOutline, permission: 'product.view' }],
  ['products-edit', { icon: CubeOutline, permission: 'product.view' }],
  ['products-import-job-detail', { icon: TimeOutline, permission: 'import_job.view' }],
  ['profile', { icon: PersonCircleOutline }],
  ['display-preferences', { icon: SettingsOutline }],
])
