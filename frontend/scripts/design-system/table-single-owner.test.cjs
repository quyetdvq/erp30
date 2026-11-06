// CSS-A02: the SAME table chrome contract for every in-repo table.
// Native tables remain semantic; width, sticky actions, hierarchical rows and
// responsive card mode are opt-in variants, not feature-owned theme skins.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const css = read('src/styles/tables.css')
const global = read('src/styles/global.css')
function files(dir, extension) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name)
    return entry.isDirectory() ? files(full, extension) : full.endsWith(extension) ? [full] : []
  })
}
const views = files(path.join(root, 'src'), '.vue')
const tableViews = views.filter((file) => /<table\b/.test(fs.readFileSync(file, 'utf8')))
const pathOf = (file) => path.relative(root, file).split(path.sep).join('/')

test('all native table templates explicitly opt into the single ds-table skin', () => {
  let count = 0
  for (const file of tableViews) {
    const source = fs.readFileSync(file, 'utf8')
    for (const match of source.matchAll(/<table\b[^>]*>/g)) {
      assert.match(match[0], /\bclass="[^"]*\bds-table\b/, `${pathOf(file)}: table missing ds-table`)
      count++
    }
  }
  assert.ok(count >= 17, `expected all table families, found only ${count}`)
  assert.equal((global.match(/@import '\.\/tables\.css';/g) || []).length, 1)
})

test('one selector family owns cell geometry, headers, hover, focus and row borders', () => {
  assert.match(css, /:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) :is\(th, td\)\s*\{[^}]*padding: 10px 12px;[^}]*border-bottom: 1px solid var\(--ds-border-subtle\);/s)
  assert.match(css, /:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) th\s*\{[^}]*background: var\(--ds-bg-subtle\);/s)
  assert.match(css, /:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) tbody tr:is\(:hover, :focus-visible, :has\(:focus-visible\)\) > td\s*\{[^}]*background: var\(--ds-table-row-hover\);/s)
  assert.match(css, /\.ds-table--sticky-header th\s*\{[^}]*position: sticky; top: 0;/)
  assert.match(css, /\.ds-table--responsive-cards tbody tr > td\s*\{[^}]*display: grid;[^}]*padding: 0; border: 0;/s)
  assert.doesNotMatch(css, /\.table-actions\s*\{[^}]*!important/)
})

// HF07 regression from two actual DEV traces. Clicked action button retains
// :focus-within after pointer leaves, but it must not paint a stale second row.
// Keyboard modality (:focus-visible) keeps the explicit row affordance.
test('mouse focus cannot paint a second row; keyboard focus remains visible', () => {
  const main = css.match(/:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) tbody tr:is\(([^\n]+)\) > td\s*\{/)
  assert.ok(main, 'canonical table owner must define row highlighting')
  assert.match(main[1], /:hover/)
  assert.match(main[1], /:focus-visible/, 'a focusable table row must retain keyboard focus paint')
  assert.match(main[1], /:has\(:focus-visible\)/)
  assert.doesNotMatch(main[1], /:focus-within/, 'pointer-focused actions must not retain full-row hover paint')
  const card = css.match(/\.ds-table--responsive-cards tbody tr:is\(([^\n]+)\) > td/)
  assert.ok(card && card[1].includes(':focus-visible') && card[1].includes(':has(:focus-visible)'),
    'mobile table cards must support direct row focus and descendant focus')
  const sticky = css.match(/\.list-table-wrap--sticky-actions \.list-table tbody tr:is\(:focus-visible, :has\(:focus-visible\)\) td,\s*\.list-table-wrap--sticky-actions \.list-table tbody tr:hover td:last-child/)
  assert.ok(sticky, 'sticky Actions must follow keyboard focus and pointer hover')
  assert.doesNotMatch(css, /tbody tr:focus-within/, 'no stale focus-within table row paint allowed')
  assert.match(read('src/styles/buttons.css'), /:focus-visible/, 'focus indication on the actual action must remain')
})

test('all table families use shared opt-in widths, sticky actions and scroll wrappers', () => {
  const contracts = [
    ['features/catalog/pages/ProductsView.vue', 'ds-table--master', 'master-table-wrap ds-table-scroll'],
    ['features/catalog/pages/ProductEditorView.vue', 'ds-table--relationship', 'data-table-wrap ds-table-scroll'],
    ['features/catalog/pages/PriceListsView.vue', 'ds-table--detail', 'list-table-wrap--sticky-actions list-table-wrap--price'],
    ['features/customer/pages/CustomerDetailView.vue', 'ds-table--contacts', 'customer-table-scroll ds-table-scroll'],
    ['features/people/pages/PeopleEmployeesView.vue', 'ds-table--assignment', 'ds-table-scroll'],
    ['components/products/ProductDetailModal.vue', 'ds-table--detail', 'table-wrap ds-table-scroll'],
    ['components/products/ProductRelationsDialog.vue', 'ds-table--product-relations', 'ds-table--sticky-header'],
    ['features/security/pages/SecurityAuthorizationGroupsView.vue', 'ds-table--authorization', 'detail-table-wrap ds-table-scroll'],
    ['features/notifications/pages/MyWorkflowActionsView.vue', 'ds-table--responsive-cards', 'ds-table-scroll--workflow'],
  ]
  for (const [file, variant, wrapper] of contracts) {
    const source = read(`src/${file}`)
    assert.ok(source.includes(variant), `${file}: ${variant}`)
    assert.ok(source.includes(wrapper), `${file}: ${wrapper}`)
    assert.doesNotMatch(source, /<style\b[^>]*>[\s\S]*?(?:\.detail-table\s+(?:th|td)|\.my-work-table\s+(?:th|td)|\.relation-table\s+(?:th|td))/m,
      `${file}: local table chrome`)
  }
  assert.match(css, /\.list-table-wrap--sticky-actions\.list-table-wrap--price\s*\{ --ds-list-table-min-width: 1080px; \}/)
  assert.match(css, /\.list-table-wrap--sticky-actions\s+\.list-table\s+:is\(th, td\):last-child\s*\{[^}]*position: sticky;/s)
})

test('feature CSS cannot quietly reintroduce table cell/header styling', () => {
  const appearance = /(?:^|;)\s*(?:padding|border(?:-[\w-]+)?|background(?:-[\w-]+)?|color|font-size|font-weight|box-shadow)\s*:/
  // Non-chrome semantic exceptions are intentional: hierarchical indentation,
  // group/selection highlights, and cell-specific metadata.
  const exceptions = [
    /\.product-child-row\s+td\.product-name-column/,
    /\.product-child-control-row\s+td/,
    /\.master-table td\.product-child-control-cell/,
    /\.row-selected td/,
  ]
  for (const file of views) {
    const s = fs.readFileSync(file, 'utf8')
    const styles = [...s.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)].map(x => x[1])
    for (const style of styles) {
      for (const m of style.matchAll(/([^{}]+)\{([^{}]+)\}/g)) {
        const selector = m[1].replace(/\/\*[\s\S]*?\*\//g, '').trim()
        const tableCellSelector = /(?:\b(?:th|td|thead|tbody)\b|\.detail-table\b|\.relation-table\b|\.my-work-table\b|\.customer-data-table\b|:deep\(\.list-table\b)/.test(selector)
        if (!tableCellSelector || !appearance.test(m[2])) continue
        if (exceptions.some(re => re.test(selector))) continue
        // Text inside a cell is owned by the feature, not the cell itself.
        if (/\.detail-table\s+small\b/.test(selector)) continue
        assert.fail(`${pathOf(file)}: table chrome outside tables.css: ${selector} { ${m[2].trim().slice(0, 80)} }`)
      }
    }
  }
})


// Keep the two browser suites synchronized with the canonical table geometry.
// A02 originally changed cells from 11px 13px to 10px 12px but left the
// CSS-A01 visual assertion at 13px, producing 20 identical E2E failures.
test('browser table padding expectations match the single-owner CSS contract', () => {
  const cellRule = css.match(/:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) :is\(th, td\)\s*\{([^}]+)\}/s)
  assert.ok(cellRule, 'canonical table cell CSS rule must exist')
  const padding = cellRule[1].match(/\bpadding:\s*(\d+px)\s+(\d+px);/)
  assert.ok(padding, 'canonical table padding must have vertical and horizontal values')
  const [, vertical, horizontal] = padding
  const ownershipE2E = read('e2e/css-ownership-visual.spec.ts')
  const tableE2E = read('e2e/table-design-system.spec.ts')
  assert.ok(ownershipE2E.includes(`toHaveCSS('padding-left', '${horizontal}')`),
    `CSS ownership visual E2E must expect the canonical horizontal cell padding: ${horizontal}`)
  assert.ok(tableE2E.includes(`toHaveCSS('padding', '${vertical} ${horizontal}')`),
    `Table Design System E2E must expect canonical cell padding: ${vertical} ${horizontal}`)
})


// CSS-A02-HF02: the video demonstrates a strong cyan hover flash on contact and
// Policy PBAC rows. Soft table-only surface + paint-only easing keep geometry
// stable, without mutating the shared hover for other interactive components.
test('table hover owns one subtle theme token and never changes row geometry', () => {
  const tokens = read('src/styles/tokens.css')
  const [light, dark] = tokens.split(":root[data-ds-theme='dark']")
  for (const palette of [light, dark]) {
    const token = (key) => palette.match(new RegExp(`${key}:\\s*(#[0-9a-fA-F]{6})`))?.[1]
    for (const key of ['--ds-table-row-hover', '--ds-bg-hover', '--ds-bg-surface', '--ds-bg-selected'])
      assert.ok(token(key), `missing ${key} in a theme`)
    assert.notEqual(token('--ds-table-row-hover'), token('--ds-bg-selected'),
      'hover must remain distinct from the selected surface')
    const distance = (a, b) => {
      const parts = (hex) => [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16))
      const A = parts(a), B = parts(b)
      return Math.sqrt(A.reduce((sum, ch, i) => sum + (ch - B[i]) ** 2, 0))
    }
    assert.ok(distance(token('--ds-table-row-hover'), token('--ds-bg-surface')) <
      distance(token('--ds-bg-hover'), token('--ds-bg-surface')),
      'table-only hover should be visually calmer than the original strong hover')
  }
  const rule = css.match(/:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) tbody tr:is\(:hover, :focus-visible, :has\(:focus-visible\)\) > td\s*\{([^}]+)\}/s)?.[1] || ''
  assert.match(rule, /background:\s*var\(--ds-table-row-hover\)/)
  assert.doesNotMatch(rule, /(?:^|;)\s*(?:border|padding|margin|width|height|transform|font-|position|display)\s*:/)
  const cells = css.match(/:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) td\s*\{([^}]+)\}/s)?.[1] || ''
  assert.match(cells, /transition:\s*background-color var\(--ds-motion-fast\) ease/)
  assert.match(css, /@media \(prefers-reduced-motion: reduce\) \{[\s\S]*?\.table-row-action \{ transition: none; \}[\s\S]*?transition: none;/)
})


// Positions uses ListTable, but its workflow action was a separately skinned
// 30px control alongside 32px View/Edit controls. Keep shared geometry.
test('Positions workflow action uses the shared 32px warning icon contract', () => {
  const position = read('src/features/people/pages/PeoplePositionsView.vue')
  const workflow = read('src/components/lists/BusinessWorkflowAction.vue')
  const buttons = read('src/styles/buttons.css')
  assert.match(position, /<ListTable\b/)
  assert.match(position, /<TableRowActions\b/)
  assert.match(workflow, /'workflow-action-icon ds-icon-control ds-icon-control--warning'/)
  assert.doesNotMatch(workflow, /\.workflow-action-icon\s*\{/)
  assert.match(buttons, /\.ds-icon-control--warning\s*\{\s*color: var\(--ds-warning\);/)
  assert.match(buttons, /\.ds-icon-control--warning:hover:not\(:disabled\)\s*\{[^}]*border-color: var\(--ds-warning\);/s)
  assert.match(buttons, /\.ds-icon-control\s*\{[^}]*width: var\(--ds-button-height-sm\);[^}]*height: var\(--ds-button-height-sm\);/s)
})

// CSS-A02-HF05: row-hover screenshots from a short browser viewport show a
// full-workspace x-offset, not a change to the table cell model. Keep the
// *actual* Naive workspace vertical scroll owner width stable on Windows.
test('workspace scroll owner reserves the vertical scrollbar gutter independent of table hover', () => {
  const shell = read('src/components/app-shell/styles.css')
  const source = read('src/components/AppShell.vue')
  assert.match(source, /<NLayout class="workspace-layout">/)
  assert.match(shell, /\.workspace-layout :deep\(\.n-layout-scroll-container\)\s*\{\s*scrollbar-gutter:\s*stable;/)
  // HF09: The old test required a redundant nested scroll container, even
  // though NLayout was the only owner with actual scroll range on DEV.
  assert.match(shell, /\.workspace-content\s*\{[^}]*overflow-x:\s*clip;[^}]*overflow-y:\s*visible;/s)
  assert.doesNotMatch(shell, /\.workspace-content\s*\{[^}]*overflow-y:\s*auto;/s)
  assert.doesNotMatch(read('src/styles/base.css'), /\bbody\s*\{[^}]*scrollbar-gutter/s,
    'reserve the nested scroll owner only: global body gutter creates a second column')
  const e2e = read('e2e/people-positions-hover-stability.spec.ts')
  assert.match(e2e, /height:\s*771/)
  assert.match(e2e, /scrollbar-gutter/)
  assert.match(e2e, /absoluteFrame/)
  assert.match(e2e, /deviceScaleFactor: 1\.25/)
})

// CSS-A02-HF12: The AppShell owns layout and scrolling, not table typography.
// On long Customer/Price List tables a stray inherited font/line-height from
// any outer workspace must not resize rows or the three action controls.
test('sticky table wrappers terminate inherited AppShell typography without resetting layout or theme', () => {
  const tokens = read('src/styles/tokens.css')
  const base = read('src/styles/base.css')
  const shell = read('src/components/app-shell/styles.css')
  const block = css.match(/\.list-table-wrap--sticky-actions\s*\{([^}]+)\}/s)?.[1] || ''
  assert.match(tokens, /--ds-font-family-ui:\s*Inter,/)
  assert.match(base, /:root\s*\{\s*font-family:\s*var\(--ds-font-family-ui\);/)
  for (const [property, value] of Object.entries({
    'font-family': 'var(--ds-font-family-ui)',
    'font-size': 'var(--ds-font-sm)',
    'font-style': 'normal',
    'font-weight': '400',
    'line-height': 'normal',
    'letter-spacing': 'normal',
    'text-transform': 'none',
    'text-indent': '0',
    'text-shadow': 'none',
    'white-space': 'normal',
    'text-align': 'start',
    'color': 'var(--ds-text-primary)',
  })) {
    assert.ok(block.includes(`${property}: ${value};`), `${property} must be owned by sticky table boundary`)
  }
  assert.match(css, /\.list-table-wrap--sticky-actions \.table-row-actions\s*\{[^}]*white-space:\s*nowrap;[^}]*line-height:\s*normal;/s)
  assert.doesNotMatch(block, /\ball\s*:/, 'never reset native interactions, scrolling or theme with all: unset')
  assert.doesNotMatch(css, /\.list-table-wrap--sticky-actions\s*\{[^}]*\b(?:position|overflow|pointer-events|transition)\s*:/s)
  assert.match(css, /\.list-table-wrap--sticky-actions \.list-table :is\(th, td\):last-child\s*\{[^}]*position:\s*sticky;/s)
  assert.match(shell, /\.workspace-content\s*\{[^}]*overflow-x:\s*clip;[^}]*overflow-y:\s*visible;/s)
})

test('Customers/Price Lists only opt into the isolated sticky table; People lists remain unchanged', () => {
  const customer = read('src/features/customer/pages/CustomersView.vue')
  const price = read('src/features/catalog/pages/PriceListsView.vue')
  const people = [
    read('src/features/people/pages/PeoplePositionsView.vue'),
    read('src/features/people/pages/PeopleDepartmentsView.vue'),
  ]
  assert.match(customer, /<ListTable\s+class="list-table-wrap--sticky-actions"/)
  assert.match(price, /class="list-table-wrap--sticky-actions list-table-wrap--price"/)
  for (const source of people) assert.doesNotMatch(source, /list-table-wrap--sticky-actions/)
})
