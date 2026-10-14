# Frontend route/view catalogue as-built (tự động sinh)

> Scan từ `frontend/src/router/routes.ts` và menu `frontend/src/app/navigation.ts`.

- **29** named view routes (bao gồm `/login` và not-found).
- `menu_permission` là hint ẩn/hiện menu. Không thay thế PBAC cấp record trong backend.

| Route | Name | Vue view | Menu? | Menu Base Permission hint | Source |
|---|---|---|---|---|---|
| `/login` | `login` | [`LoginView.vue`](../../../frontend/src/features/auth/pages/LoginView.vue) | no | `—` | [L10](../../../frontend/src/router/routes.ts#L10) |
| `/` | `dashboard` | [`DashboardView.vue`](../../../frontend/src/features/workspace/pages/DashboardView.vue) | yes | `—` | [L20](../../../frontend/src/router/routes.ts#L20) |
| `/profile` | `profile` | [`ProfileView.vue`](../../../frontend/src/features/account/pages/ProfileView.vue) | no | `—` | [L26](../../../frontend/src/router/routes.ts#L26) |
| `/profile/display-preferences` | `display-preferences` | [`DisplayPreferencesView.vue`](../../../frontend/src/features/account/pages/DisplayPreferencesView.vue) | no | `—` | [L32](../../../frontend/src/router/routes.ts#L32) |
| `/system/display-settings` | `display-settings` | [`DisplaySettingsView.vue`](../../../frontend/src/features/settings/pages/DisplaySettingsView.vue) | yes | `display_setting.view` | [L41](../../../frontend/src/router/routes.ts#L41) |
| `/security/users` | `security-users` | [`SecurityUsersView.vue`](../../../frontend/src/features/security/pages/SecurityUsersView.vue) | yes | `user.view` | [L50](../../../frontend/src/router/routes.ts#L50) |
| `/security/authorization-groups` | `security-authorization-groups` | [`SecurityAuthorizationGroupsView.vue`](../../../frontend/src/features/security/pages/SecurityAuthorizationGroupsView.vue) | yes | `authorization_group.view` | [L56](../../../frontend/src/router/routes.ts#L56) |
| `/security/resources` | `security-resources` | [`SecurityResourcesView.vue`](../../../frontend/src/features/security/pages/SecurityResourcesView.vue) | yes | `resource.view` | [L62](../../../frontend/src/router/routes.ts#L62) |
| `/security/policies` | `security-policies` | [`SecurityPoliciesView.vue`](../../../frontend/src/features/security/pages/SecurityPoliciesView.vue) | yes | `policy.view` | [L68](../../../frontend/src/router/routes.ts#L68) |
| `/security/authorization-logs` | `security-authorization-logs` | [`SecurityAuthorizationLogsView.vue`](../../../frontend/src/features/security/pages/SecurityAuthorizationLogsView.vue) | yes | `authorization_log.view` | [L74](../../../frontend/src/router/routes.ts#L74) |
| `/configuration/workflow-states` | `configuration-workflow-states` | [`WorkflowStatesView.vue`](../../../frontend/src/features/workflow/pages/WorkflowStatesView.vue) | yes | `workflow_state.view` | [L80](../../../frontend/src/router/routes.ts#L80) |
| `/configuration/lifecycles` | `configuration-lifecycles` | [`LifecyclesView.vue`](../../../frontend/src/features/workflow/pages/LifecyclesView.vue) | yes | `lifecycle.view` | [L86](../../../frontend/src/router/routes.ts#L86) |
| `/configuration/auto-numbering` | `configuration-auto-numbering` | [`AutoNumberingView.vue`](../../../frontend/src/features/workflow/pages/AutoNumberingView.vue) | yes | `resource.configure_numbering` | [L92](../../../frontend/src/router/routes.ts#L92) |
| `/products/groups` | `products-groups` | [`ProductGroupsView.vue`](../../../frontend/src/features/catalog/pages/ProductGroupsView.vue) | yes | `product_group.view` | [L98](../../../frontend/src/router/routes.ts#L98) |
| `/products/uoms` | `products-uoms` | [`ProductUomsView.vue`](../../../frontend/src/features/catalog/pages/ProductUomsView.vue) | yes | `product_uom.view` | [L104](../../../frontend/src/router/routes.ts#L104) |
| `/products/price-lists` | `products-price-lists` | [`PriceListsView.vue`](../../../frontend/src/features/catalog/pages/PriceListsView.vue) | yes | `price_list.view` | [L110](../../../frontend/src/router/routes.ts#L110) |
| `/products/import` | `products-import` | [`ProductImportView.vue`](../../../frontend/src/features/product-import/pages/ProductImportView.vue) | yes | `product.import` | [L119](../../../frontend/src/router/routes.ts#L119) |
| `/products/import-jobs/:id` | `products-import-job-detail` | [`ProductImportJobDetailView.vue`](../../../frontend/src/features/product-import/pages/ProductImportJobDetailView.vue) | no | `—` | [L125](../../../frontend/src/router/routes.ts#L125) |
| `/products/import-jobs` | `products-import-jobs` | [`ProductImportJobsView.vue`](../../../frontend/src/features/product-import/pages/ProductImportJobsView.vue) | yes | `import_job.view` | [L134](../../../frontend/src/router/routes.ts#L134) |
| `/products/new` | `products-new` | [`ProductEditorView.vue`](../../../frontend/src/features/catalog/pages/ProductEditorView.vue) | no | `—` | [L140](../../../frontend/src/router/routes.ts#L140) |
| `/products/:id` | `products-edit` | [`ProductEditorView.vue`](../../../frontend/src/features/catalog/pages/ProductEditorView.vue) | no | `—` | [L146](../../../frontend/src/router/routes.ts#L146) |
| `/products` | `products` | [`ProductsView.vue`](../../../frontend/src/features/catalog/pages/ProductsView.vue) | yes | `product.view` | [L152](../../../frontend/src/router/routes.ts#L152) |
| `/organizations` | `organizations` | [`OrganizationsView.vue`](../../../frontend/src/features/organization/pages/OrganizationsView.vue) | yes | `organization.view` | [L158](../../../frontend/src/router/routes.ts#L158) |
| `/settings/locations/provinces` | `locations-provinces` | [`LocationsView.vue`](../../../frontend/src/features/organization/pages/LocationsView.vue) | yes | `province.view` | [L164](../../../frontend/src/router/routes.ts#L164) |
| `/settings/locations/wards` | `locations-wards` | [`LocationsView.vue`](../../../frontend/src/features/organization/pages/LocationsView.vue) | yes | `ward.view` | [L170](../../../frontend/src/router/routes.ts#L170) |
| `/people/departments` | `people-departments` | [`PeopleDepartmentsView.vue`](../../../frontend/src/features/people/pages/PeopleDepartmentsView.vue) | yes | `department.view` | [L176](../../../frontend/src/router/routes.ts#L176) |
| `/people/positions` | `people-positions` | [`PeoplePositionsView.vue`](../../../frontend/src/features/people/pages/PeoplePositionsView.vue) | yes | `position.view` | [L182](../../../frontend/src/router/routes.ts#L182) |
| `/people/employees` | `people-employees` | [`PeopleEmployeesView.vue`](../../../frontend/src/features/people/pages/PeopleEmployeesView.vue) | yes | `employee.view` | [L188](../../../frontend/src/router/routes.ts#L188) |
| `/:pathMatch(.*)*` | `not-found` | [`NotFoundView.vue`](../../../frontend/src/app/pages/NotFoundView.vue) | no | `—` | [L194](../../../frontend/src/router/routes.ts#L194) |
