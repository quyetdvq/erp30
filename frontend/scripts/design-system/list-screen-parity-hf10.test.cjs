// CSS-A02-HF10: one list/table Design System across the 20 management screens.
// This is a source-level regression gate, not an authenticated browser test.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { resolve } = require('node:path')
const root = resolve(__dirname, '../..')
const source = (name) => readFileSync(resolve(root, name), 'utf8')

// Explicit inventory prevents silent forks of the list rendering contract.
const listPages = [
  'catalog/pages/PriceListsView',
  'catalog/pages/ProductGroupsView',
  'catalog/pages/ProductUomsView',
  'customer/pages/CustomersView',
  'history/pages/HistoryMonitorView',
  'organization/pages/LocationsView',
  'people/pages/PeopleDepartmentsView',
  'people/pages/PeopleEmployeesView',
  'people/pages/PeoplePositionsView',
  'product-import/pages/ProductImportJobDetailView',
  'product-import/pages/ProductImportJobsView',
  'security/pages/SecurityAuthorizationGroupsView',
  'security/pages/SecurityAuthorizationLogsView',
  'security/pages/SecurityPoliciesView',
  'security/pages/SecurityResourcesView',
  'security/pages/SecurityUsersView',
  'supplier/pages/SuppliersView',
  'workflow/pages/AutoNumberingView',
  'workflow/pages/LifecyclesView',
  'workflow/pages/WorkflowStatesView',
]
const readPage = (name) => source(`src/features/${name}.vue`)
const scoped = (content) => [...content.matchAll(/<style[^>]*scoped[^>]*>([\s\S]*?)<\/style>/g)]
  .map((match) => match[1]).join('\n')

test('20 list screens mount the canonical ListTable and ListPagination rather than forked list markup', () => {
  assert.equal(listPages.length, 20)
  for (const name of listPages) {
    const text = readPage(name)
    assert.match(text, /<ListTable\b/, `${name}: canonical ListTable missing`)
    assert.match(text, /<ListPagination\b/, `${name}: canonical ListPagination missing`)
    assert.match(text, /<\/ListTable\s*>/, `${name}: list table not closed`)
  }
})

test('all 20 management tables share one row paint and scroll wrapper owner', () => {
  const table = source('src/components/lists/ListTable.vue')
  const css = source('src/styles/tables.css')
  assert.match(table, /<div class="list-table-wrap"/)
  assert.match(table, /<table class="list-table ds-table">/)
  assert.match(css, /\.list-table-wrap,\s*\.ds-table-scroll\s*\{[^}]*overflow-x:\s*auto;/s)
  assert.match(css, /tbody tr:is\(:hover, :focus-visible, :has\(:focus-visible\)\) > td/)
  for (const name of listPages) {
    const pageCSS = scoped(readPage(name))
    assert.doesNotMatch(pageCSS, /(?:\btr\s*:\s*hover|\btr\s*:\s*focus-within)\b/,
      `${name} must not fork global row highlighting`)
  }
})

test('Customer and Supplier text statuses use the exact same shared spacing owner', () => {
  const css = source('src/styles/tables.css')
  const customer = readPage('customer/pages/CustomersView')
  const supplier = readPage('supplier/pages/SuppliersView')
  assert.match(css, /\.ds-table-status-with-label\s*\{\s*display:\s*inline-flex;\s*align-items:\s*center;\s*gap:\s*6px;/)
  for (const [name, page] of [['Customer', customer], ['Supplier', supplier]]) {
    assert.match(page, /<span class="[^"]*ds-table-status-with-label[^"]*"><TableStatus\s+:status="row\.status"\s*\/>/,
      `${name}: text+icon spacing must be shared`)
    assert.doesNotMatch(scoped(page), /\.(?:customer|supplier)-status-cell\s*\{/,
      `${name}: avoid duplicated scoped status presentation`)
  }
})

test('Supplier CAS state button retains business ownership, but uses the canonical 32px warning icon', () => {
  const supplier = source('src/features/supplier/components/SupplierStateAction.vue')
  const workflow = source('src/components/lists/BusinessWorkflowAction.vue')
  const buttons = source('src/styles/buttons.css')
  assert.match(supplier, /variant === 'icon' \? 'supplier-state-action-icon ds-icon-control ds-icon-control--warning'/)
  assert.match(workflow, /variant === 'icon' \? 'workflow-action-icon ds-icon-control ds-icon-control--warning'/)
  assert.doesNotMatch(scoped(supplier), /(?:width|height)\s*:\s*30px/)
  assert.match(buttons, /\.ds-icon-control\s*\{[^}]*width:\s*var\(--ds-button-height-sm\);[^}]*height:\s*var\(--ds-button-height-sm\);/s)
  assert.match(supplier, /await supplierSetState\(/)
  assert.match(supplier, /supplierStateBody\(target, snapshot\.row_version\)/)
  assert.match(supplier, /props\.supplier\.allowed_actions\?\.setstate === true/)
})

test('Customer and Price Lists alone opt into sticky actions; other screens keep standard horizontal tables', () => {
  const sticky = listPages.filter((name) => /list-table-wrap--sticky-actions/.test(readPage(name))).sort()
  assert.deepEqual(sticky, ['catalog/pages/PriceListsView', 'customer/pages/CustomersView'])
  const css = source('src/styles/tables.css')
  assert.match(css, /\.list-table-wrap--sticky-actions\s*\{\s*--ds-list-table-min-width:\s*1040px;/)
  assert.match(css, /\.list-table-wrap--sticky-actions\.list-table-wrap--price\s*\{\s*--ds-list-table-min-width:\s*1080px;/)
  assert.match(css, /\.list-table-wrap--sticky-actions\s+\.list-table\s+:is\(th, td\):last-child\s*\{[^}]*position:\s*sticky;[^}]*right:\s*0;/s)
})

test('global AppShell owns vertical scrolling across every list, not feature pages', () => {
  const shell = source('src/components/app-shell/styles.css')
  assert.match(shell, /\.workspace-content\s*\{[^}]*display:\s*flow-root;[^}]*overflow-x:\s*clip;[^}]*overflow-y:\s*visible;[^}]*padding:\s*26px clamp\(18px, 2\.2vw, 36px\) 0;/s)
  assert.match(shell, /\.workspace-layout\s+:deep\(\.n-layout-scroll-container\)\s*\{[^}]*scrollbar-gutter:\s*stable;/s)
  for (const name of listPages) {
    const pageCSS = scoped(readPage(name))
    assert.doesNotMatch(pageCSS, /\.workspace-(?:layout|content)\s*\{/,
      `${name} must not override global AppShell scroll ownership`)
  }
})
