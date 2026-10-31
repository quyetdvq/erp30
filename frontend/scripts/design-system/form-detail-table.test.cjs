// DS3 source-level gates; no dependencies/DOM required. Runtime QA follows
// separately in the actual ERP browser, not in this static test suite.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8')
const vue = (name) => read(`src/${name}.vue`)
const css = (name) => read(`src/styles/${name}.css`)

const tables = [
  'components/lists/ListTable', 'components/lists/ListShell',
  'components/lists/ListQueryControls', 'components/lists/ListPagination',
  'components/lists/TableStatus', 'components/lists/TableRowActions',
]
const formEditors = [
  'features/catalog/pages/ProductGroupsView', 'features/catalog/pages/ProductUomsView',
  'features/catalog/pages/PriceListsView', 'features/organization/pages/LocationsView',
  'features/organization/pages/OrganizationsView',
  'features/people/pages/PeopleDepartmentsView', 'features/people/pages/PeoplePositionsView',
  'features/security/pages/SecurityPoliciesView', 'features/workflow/pages/WorkflowStatesView',
  'features/workflow/pages/AutoNumberingView',
]
const panels = [
  'features/catalog/pages/ProductGroupsView', 'features/catalog/pages/ProductUomsView',
  'features/catalog/pages/ProductsView', 'features/catalog/pages/PriceListsView',
  'features/organization/pages/LocationsView', 'features/people/pages/PeopleDepartmentsView',
  'features/people/pages/PeoplePositionsView', 'features/people/pages/PeopleEmployeesView',
  'features/security/pages/SecurityPoliciesView', 'features/security/pages/SecurityUsersView',
  'features/security/pages/SecurityResourcesView', 'features/security/pages/SecurityAuthorizationGroupsView',
  'features/security/pages/SecurityAuthorizationLogsView',
  'features/workflow/pages/WorkflowStatesView', 'features/workflow/pages/AutoNumberingView',
  'features/workflow/pages/LifecyclesView',
]

function blockTokens(content) {
  return Object.fromEntries([...content.matchAll(/(--ds-[\w-]+)\s*:\s*([^;]+);/g)]
    .map((match) => [match[1], match[2].trim()]))
}
function luminance(color) {
  assert.match(color, /^#[\da-f]{6}$/i)
  const rgb = [1, 3, 5].map((index) => parseInt(color.slice(index, index + 2), 16) / 255)
  const channel = rgb.map((n) => n <= .04045 ? n / 12.92 : ((n + .055) / 1.055) ** 2.4)
  return channel[0] * .2126 + channel[1] * .7152 + channel[2] * .0722
}
function ratio(a, b) {
  const [low, high] = [luminance(a), luminance(b)].sort((x, y) => x - y)
  return (high + .05) / (low + .05)
}

test('DS3 common CSS is imported once and every DS3 token resolves in both palettes', () => {
  const global = css('global')
  assert.equal(global.split("@import './detail-workspace.css';").length, 2)
  for (const f of ['forms', 'tables', 'detail-workspace']) {
    assert.equal(global.split(`@import './${f}.css';`).length, 2)
  }
  const [light, dark] = css('tokens').split(":root[data-ds-theme='dark']")
  const a = blockTokens(light)
  const b = {...a, ...blockTokens(dark)}
  for (const name of ['forms', 'tables', 'detail-workspace']) {
    for (const [, key] of css(name).matchAll(/var\((--ds-[a-z0-9-]+)/g)) {
      assert.ok(a[key] && b[key], `${name}: unresolved ${key}`)
    }
  }
})

test('Customer/Supplier detail presentation is shared; guards and CAS stay separate', () => {
  const common = css('detail-workspace')
  for (const cls of ['ds-detail-workspace--fixed', 'ds-detail-tabs', 'ds-detail-body',
    'ds-detail-form-grid', 'ds-detail-form-wide', 'ds-detail-required', 'ds-detail-error']) {
    assert.ok(common.includes(`.${cls}`), `shared contract ${cls}`)
    for (const module of ['customer/pages/CustomerDetailView', 'supplier/pages/SupplierDetailView']) {
      assert.ok(vue(`features/${module}`).includes(cls), `${module} missing ${cls}`)
    }
  }
  assert.match(common, /@media \(max-width: 640px\)/)
  assert.match(common, /:not\(\.n-base-selection \*, \.n-input \*\)/)
  assert.match(vue('features/customer/pages/CustomerDetailView'), /profileDraft\.confirmDiscard\(\)/)
  assert.match(vue('features/customer/pages/CustomerDetailView'), /contactDraft\.confirmDiscard\(\)/)
  assert.match(vue('features/supplier/pages/SupplierDetailView'), /unsaved\.confirmDiscard\(\)/)
  assert.match(vue('features/supplier/components/SupplierStateAction'), /row_version/)
})

test('Ten editor forms use shared layout/labels and preserve their submit handlers', () => {
  assert.match(css('forms'), /\.ds-dialog-form-fields/)
  for (const name of formEditors) {
    const source = vue(name)
    assert.match(source, /<form\b[^>]*ds-dialog-form-fields/s, `${name}: opt-in`)
    assert.match(source, /@submit\.prevent/, `${name}: native submit behavior`)
    assert.doesNotMatch(source.split('<style scoped>')[1] || '', /^\.modal-card__body label\s*\{/m)
  }
  // This protects against the original double-border problem in NSelect.
  assert.doesNotMatch(css('forms'), /\.ds-dialog-form-fields\s+input\s*\{/)
})

test('Shared page panels and toolbar replace the duplicated declarations', () => {
  const styles = css('forms')
  for (const cls of ['ds-page-panel', 'ds-page-toolbar']) assert.ok(styles.includes(`.${cls}`))
  for (const name of panels) assert.match(vue(name), /\bds-page-panel\b/, name)
})

test('All six list widgets source their presentation from shared tables.css', () => {
  const rules = css('tables')
  for (const name of tables) {
    const source = vue(name)
    assert.doesNotMatch(source, /<style\b/, `${name}: one stylesheet owns visual rules`)
  }
  for (const cls of ['list-table', 'list-table__sort', 'list-query-controls', 'list-pagination',
    'table-row-action', 'table-status', 'list-shell']) assert.ok(rules.includes(`.${cls}`), cls)
  assert.match(rules, /\.list-query-controls__field > :is\(input, select\)/)
  assert.match(rules, /:focus-visible/)
  assert.match(rules, /:disabled/)
  assert.match(vue('components/lists/ListTable'), /'sort-change'/)
  assert.match(vue('components/lists/TableRowActions'), /canMutation\('delete'\)/)
  assert.match(vue('components/lists/ListPagination'), /update:pageSize/)
})

test('Hierarchical Products and security detail grids use the same semantic base table', () => {
  const products = vue('features/catalog/pages/ProductsView')
  const authGroups = vue('features/security/pages/SecurityAuthorizationGroupsView')
  assert.match(products, /master-table ds-data-grid/)
  assert.match(products, /master-table-wrap ds-table-scroll/)
  assert.match(products, /product-tree-toggle/)
  assert.match(products, /product-child-row/)
  assert.match(authGroups, /detail-table ds-data-grid/)
  assert.match(authGroups, /detail-table-wrap ds-table-scroll/)
  assert.match(css('tables'), /\.ds-data-grid/)
})

test('Migrated table/detail styles have no fixed light palette literals', () => {
  for (const name of [...tables,
    'features/catalog/pages/ProductsView', 'features/customer/pages/CustomerDetailView',
    'features/supplier/pages/SupplierDetailView',
    'features/security/pages/SecurityAuthorizationLogsView',
    'components/products/ProductImageField', 'components/products/ProductRelationSummary',
  ]) {
    const localStyles = vue(name).split(/<style(?:\s+scoped)?\s*>/)[1] || ''
    assert.doesNotMatch(localStyles, /#[\da-f]{3,8}\b/i, `${name} hardcodes a palette color`)
  }
  assert.doesNotMatch(css('tables'), /#[\da-f]{3,8}\b/i)
  assert.doesNotMatch(css('detail-workspace'), /#[\da-f]{3,8}\b/i)
})

test('Role-combo badge colors meet WCAG AA in light and dark', () => {
  const [light, dark] = css('tokens').split(":root[data-ds-theme='dark']")
  const a = blockTokens(light)
  const b = {...a, ...blockTokens(dark)}
  for (const [name, tokens] of [['light', a], ['dark', b]]) {
    const actual = ratio(tokens['--ds-role-combo'], tokens['--ds-role-combo-soft'])
    assert.ok(actual >= 4.5, `${name} combo text contrast ${actual}`)
  }
  assert.match(vue('features/catalog/pages/ProductsView'), /var\(--ds-role-combo\)/)
})

test('Shared tabs retain clear selected/focus affordances and mobile wrapping', () => {
  const source = css('detail-workspace')
  assert.match(source, /\[aria-selected='true'\]/)
  assert.match(source, /\.ds-detail-tabs > button\.active/)
  assert.match(source, /:focus-visible/)
  assert.match(source, /@media \(max-width: 640px\)/)
  for (const name of [
    'features/security/pages/SecurityUsersView',
    'features/security/pages/SecurityResourcesView',
    'features/security/pages/SecurityAuthorizationGroupsView',
    'features/workflow/pages/LifecyclesView',
  ]) assert.match(vue(name), /\bds-detail-tabs--spaced\b/, name)
})

// DS-14: The page-size selector must be present once per list view, even if the
// top and bottom pager are both shown. Keep the component defaults for pages
// using only QueryControls; opt out only where another pager owns the selector.
test('One visible page-size picker per composed list view', () => {
  const files=[]
  function walk(dir) {
    for(const entry of fs.readdirSync(dir,{withFileTypes:true})) {
      const filename=path.join(dir, entry.name)
      if(entry.isDirectory()) walk(filename)
      else if(filename.endsWith('.vue')) {
        const contents=fs.readFileSync(filename,'utf8')
        if(contents.includes('<ListQueryControls') && contents.includes('<ListPagination')) files.push(filename)
      }
    }
  }
  walk(path.join(root,'src'))
  assert.equal(files.length,21,'audit baseline must not silently shrink')
  let doublePagerCount=0
  for(const name of files) {
    const contents=fs.readFileSync(name,'utf8')
    const queries=[...contents.matchAll(/<ListQueryControls\b[\s\S]*?\/>/g)].map(m=>m[0])
    const pagers=[...contents.matchAll(/<ListPagination\b[\s\S]*?\/>/g)].map(m=>m[0])
    assert.equal(queries.length,1,name)
    assert.ok(pagers.length===1||pagers.length===2,name)
    assert.match(queries[0],/:show-page-size="false"/,name)
    assert.doesNotMatch(pagers[0],/:show-page-size="false"/,name)
    if(pagers.length===2) { doublePagerCount++; assert.match(pagers[1],/:show-page-size="false"/,name) }
  }
  assert.equal(doublePagerCount,6)
  const component=vue('components/lists/ListPagination')
  assert.match(component,/showPageSize\?: boolean/)
  assert.match(component,/showPageSize: true/)
  assert.match(component,/v-if="props.showPageSize"/)
})

// Price Lists table regression found in DS3 acceptance: a sortable ListTable
// was rendered without subscribing to its sort-change event. The Pricing API
// also caps page_size at 100, unlike generic catalog list pages.
test('Price Lists main table forwards header sort as primary API sort', () => {
  const source = vue('features/catalog/pages/PriceListsView')
  const table = source.match(/<ListTable\b[\s\S]*?>/)?.[0] ?? ''
  assert.match(table, /:sort="state\.query\.sort"/)
  assert.match(table, /@sort-change="handleTableSort"/)
  assert.match(source, /function handleTableSort\(sort: string\): void \{[\s\S]*?state\.setSort\(terms\[terms\.length - 1\] \?\? ''\)/)
  assert.match(table, /:busy="listBusy"/)
  assert.match(source, /:busy="listBusy \|\| busy"/)
})

test('Price Lists pagers respect API size 100 and sticky actions share theme hover', () => {
  const source = vue('features/catalog/pages/PriceListsView')
  assert.match(source, /priceListPageSizeOptions = pageSizeOptionsFor\(100\)/)
  const pagers = [...source.matchAll(/<ListPagination\b[\s\S]*?\/>/g)].map(match => match[0])
  assert.equal(pagers.length, 2)
  assert.match(pagers[0], /:page-size-options="priceListPageSizeOptions"/)
  assert.match(pagers[1], /:show-page-size="false"/)
  assert.match(source, /\.pricing-panel :deep\(\.list-table\) \{\s*min-width: 1080px/)
  assert.match(source, /list-table tbody tr:hover td:last-child/)
  assert.match(source, /list-table tbody tr:focus-within td/)
  assert.match(source, /list-table tbody tr:focus-within td:last-child/)
  assert.match(source, /background: var\(--ds-bg-hover\)/)
})

// HF8: Supplier actions can no longer compete for width with the Company field.
// The shared query form hosts feature fields BEFORE its own action row. This
// preserves the form's apply/reset behavior and allows container-based layout.
test('Supplier shares the common field-first grid and keeps Company under PBAC', () => {
  const source = vue('features/supplier/pages/SuppliersView')
  const controls = vue('components/lists/ListQueryControls')
  const shared = read('src/styles/tables.css')
  const css = source.split('<style scoped>')[1] || ''
  const block = source.match(/<ListQueryControls\b[\s\S]*?<\/ListQueryControls>/)?.[0] || ''
  assert.match(block, /:show-page-size="false"/)
  assert.match(block, /:extra-field-count="hasBasePermission\('organization', 'view'\) \? 1 : 0"/)
  assert.match(block, /<template #filters>[\s\S]*class="list-query-controls__field list-query-controls__field--wide supplier-company-filter"/)
  assert.match(block, /<template #actions>[\s\S]*class="secondary-button supplier-toolbar-refresh"/)
  assert.ok(controls.indexOf('<slot name="filters"') < controls.indexOf('class="list-query-controls__actions"'))
  assert.match(controls, /class="list-query-controls__grid"/)
  assert.match(controls, /<slot name="actions" \/>/)
  assert.match(shared, /container:\s*ds-list-query\s*\/\s*inline-size/)
  assert.match(shared, /grid-template-columns:\s*var\(--ds-query-columns/)
  assert.doesNotMatch(css, /grid-template-columns|@container supplier-filters/,
    'Supplier must not duplicate the layout owner')
})

test('Responsive thresholds are shared across all ListQueryControls, not Supplier CSS', () => {
  const source = vue('features/supplier/pages/SuppliersView')
  const shared = read('src/styles/tables.css')
  for (const threshold of [1060, 860, 490])
    assert.match(shared, new RegExp(String.raw`@container ds-list-query \(max-width: ${threshold}px\)`))
  assert.match(shared, /\.list-query-controls__actions > :nth-child\(3\) \{ margin-inline-start: auto;/)
  assert.match(source, /type="button" class="secondary-button supplier-toolbar-refresh"/)
  assert.match(source, /@click="void load\(\)"/)
  assert.match(source, /v-if="hasBasePermission\('organization', 'view'\)" class="list-query-controls__field/)
  assert.match(source, /@update:model-value="changeCompanyFilter"/)
  assert.match(source, /@apply="applyQuery" @reset="reset"/)
})
