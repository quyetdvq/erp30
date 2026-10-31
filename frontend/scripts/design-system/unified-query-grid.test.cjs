// DS5 HF9: all listing query bars share one responsive grid and action row.
// These source-level invariants protect layout and functional event contracts;
// the Playwright suite measures geometry in real Chromium on Windows.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(root, rel), 'utf8')
const walk = (dir) => fs.readdirSync(path.join(root, dir), { withFileTypes: true }).flatMap((x) => x.isDirectory()
  ? walk(path.join(dir, x.name)) : [path.join(dir, x.name)])
const views = walk('src').filter((x) => x.endsWith('.vue') && !x.includes('/__tests__/'))
const listPages = views.filter((x) => read(x).includes('<ListQueryControls'))
const refreshPages = [
  'src/components/contacts/ContactList.vue',
  'src/features/catalog/pages/PriceListsView.vue',
  'src/features/customer/pages/CustomersView.vue',
  'src/features/organization/pages/LocationsView.vue',
  'src/features/organization/pages/OrganizationsView.vue',
  'src/features/people/pages/PeopleDepartmentsView.vue',
  'src/features/people/pages/PeopleEmployeesView.vue',
  'src/features/people/pages/PeoplePositionsView.vue',
  'src/features/security/pages/SecurityAuthorizationGroupsView.vue',
  'src/features/security/pages/SecurityAuthorizationLogsView.vue',
  'src/features/security/pages/SecurityPoliciesView.vue',
  'src/features/security/pages/SecurityResourcesView.vue',
  'src/features/workflow/pages/LifecyclesView.vue',
  'src/features/workflow/pages/WorkflowStatesView.vue',
  'src/features/supplier/pages/SuppliersView.vue',
]
const custom = [
  'src/features/history/pages/HistoryMonitorView.vue',
  'src/features/notifications/pages/NotificationsInboxView.vue',
  'src/features/notifications/pages/MyWorkflowActionsView.vue',
  'src/features/workflow/pages/AutoNumberingView.vue',
]

// Confirm complete coverage instead of silently skipping a future page.
test('All 21 listing screens use one ListQueryControls component with no forked toolbar form', () => {
  assert.equal(listPages.length, 21, 'Update inventory when adding a new list page')
  const shared = read('src/components/lists/ListQueryControls.vue')
  assert.match(shared, /<form class="list-query-controls" :class="{ 'list-query-controls--dense': dense }" role="search" @submit\.prevent="apply">/)
  assert.match(shared, /<div class="list-query-controls__grid" :style=/)
  assert.ok(shared.indexOf('<slot name="filters"') < shared.indexOf('class="list-query-controls__actions"'))
  assert.match(shared, /<slot name="actions" \/>/)
  assert.match(shared, /type="submit" class="ds-button ds-button--primary"/)
  assert.match(shared, /type="button" class="ds-button ds-button--secondary"/)
  for (const file of listPages) {
    assert.doesNotMatch(read(file), /<form class="list-query-controls"/, `${file}: do not fork shared form`)
  }
})

test('Shared list grid uses container width, adaptive tracks and stable action row', () => {
  const css = read('src/styles/tables.css')
  const query = read('src/components/lists/ListQueryControls.vue')
  assert.match(css, /\.list-query-controls\s*\{[^}]*container:\s*ds-list-query\s*\/\s*inline-size;/s)
  assert.match(css, /\.list-query-controls__grid\s*\{[^}]*grid-template-columns:\s*var\(--ds-query-columns/s)
  assert.match(css, /\.list-query-controls__actions\s*\{[^}]*display:\s*flex;[^}]*flex-wrap:\s*wrap;/s)
  assert.match(css, /\.list-query-controls__actions > :nth-child\(3\) \{ margin-inline-start: auto; \}/)
  for (const px of [1280, 1060, 860, 490]) assert.match(css, new RegExp(`@container ds-list-query \\(max-width: ${px}px\\)`))
  assert.match(query, /const desktopColumns = computed\(\(\) =>/)
  assert.match(query, /props\.filters\.length/)
  assert.match(query, /props\.sortOptions\.length/)
  assert.match(query, /props\.extraFieldCount/)
  assert.match(read('src/styles/tokens.css'), /--ds-query-columns:\s*minmax\(0, 1fr\)/)
})

test('Every list Refresh is a type=button in the shared action slot after filters', () => {
  for (const file of refreshPages) {
    const s = read(file)
    const block = s.match(/<ListQueryControls\b[\s\S]*?<\/ListQueryControls>/)?.[0] || ''
    assert.ok(block, `${file}: Refresh must be a named action inside ListQueryControls`)
    assert.match(block, /<template #actions>[\s\S]*?<button\b[\s\S]*?type="button"|<template #actions>[\s\S]*?<button\b[^>]*type="button"/,
      `${file}: Refresh must never submit the search form`)
    assert.match(block, /(?:data-ds-icon="refresh"|RefreshOutline)/, `${file}: keep Refresh semantics`)
    assert.match(block, /<\/template>\s*<\/ListQueryControls>/)
  }
})

test('Supplier Company is PBAC-gated and shares the filter row without private grid rules', () => {
  const s = read('src/features/supplier/pages/SuppliersView.vue')
  const style = s.split('<style scoped>')[1] || ''
  assert.match(s, /:extra-field-count="hasBasePermission\('organization', 'view'\) \? 1 : 0"/)
  assert.match(s, /v-if="hasBasePermission\('organization', 'view'\)" class="list-query-controls__field list-query-controls__field--wide supplier-company-filter"/)
  assert.match(s, /@update:model-value="changeCompanyFilter"/)
  assert.match(s, /@apply="applyQuery" @reset="reset"/)
  assert.doesNotMatch(style, /@container|grid-template-columns|\.supplier-company-filter\s*\{/)
})

test('Four bespoke filters have a shared field grid and a separate action row', () => {
  const css = read('src/styles/tables.css')
  assert.match(css, /\.ds-filter-container\s*\{\s*container:\s*ds-filter/)
  assert.match(css, /\.ds-filter-fields\s*\{[^}]*grid-template-columns:\s*repeat\(auto-fit/s)
  assert.match(css, /\.ds-filter-actions\s*\{[^}]*display:\s*flex;/s)
  for (const file of custom) {
    const s = read(file)
    assert.match(s, /class="ds-filter-container"/, file)
    assert.match(s, /class="ds-filter-fields"/, file)
    assert.match(s, /class="[^"]*ds-filter-actions"/, file)
    assert.ok(s.indexOf('class="ds-filter-fields"') < s.indexOf('ds-filter-actions'), file)
  }
})

test('Bespoke filtering behavior remains unchanged (dates, cursor, live search, numbering)', () => {
  const history = read(custom[0])
  const inbox = read(custom[1])
  const work = read(custom[2])
  const numbering = read(custom[3])
  assert.match(history, /role="search" @submit\.prevent="apply"/)
  assert.match(history, /v-model="from" type="date" required/)
  assert.match(history, /v-model="to" type="date" required/)
  assert.match(inbox, /data-testid="inbox-apply"[^>]*@click="applyFilters"/)
  assert.match(inbox, /data-testid="inbox-reset"[^>]*@click="resetFilters"/)
  assert.match(inbox, /@click="setFilter\('unread'\)"/)
  assert.match(work, /@change="selectType"/)
  assert.match(work, /data-testid="my-work-refresh" secondary :disabled="busy" @click="load\(\)"/)
  assert.match(numbering, /role="search" @submit\.prevent="void refreshSeries\(\)"/)
  assert.match(numbering, /type="submit" class="secondary-button" :disabled="loadingResources \|\| loadingSeries"/)
})

test('No page-owned flex/filter chrome can override the shared listing grid', () => {
  const stale = [
    ['src/features/supplier/pages/SuppliersView.vue', /supplier-filters|supplier-query--with-company/],
    ['src/features/customer/pages/CustomersView.vue', /\.customer-list-toolbar\s*\{[^}]*display:\s*flex/s],
    ['src/features/catalog/pages/PriceListsView.vue', /\.pricing-query-toolbar\s*\{[^}]*display:\s*flex/s],
    ['src/components/contacts/ContactList.vue', /\.contact-list__toolbar\s*\{[^}]*display:\s*flex/s],
    ['src/features/organization/pages/OrganizationsView.vue', /\.organization-tree__toolbar\s*\{[^}]*display:\s*flex/s],
  ]
  for (const [file, pattern] of stale) assert.doesNotMatch(read(file), pattern, file)
})

// Dense selectors have two classes; mobile overrides need matching specificity.
test('Dense six-plus-filter grids expand to three columns, then one, on small panels', () => {
  const css = read('src/styles/tables.css')
  assert.match(css, /@container ds-list-query \(max-width: 860px\) \{[\s\S]*?\.list-query-controls--dense \.list-query-controls__field \{ grid-column: span 2; \}/)
  assert.match(css, /@container ds-list-query \(max-width: 860px\) \{[\s\S]*?\.list-query-controls--dense \.list-query-controls__search,[\s\S]*?\{ grid-column: span 6; \}/)
  assert.match(css, /@container ds-list-query \(max-width: 490px\) \{[\s\S]*?\.list-query-controls--dense \.list-query-controls__field \{ grid-column: span 6; \}/)
})
