// DS5 HF2: Customer list table uses the shared visual contract without
// shrinking Actions or introducing hover geometry changes.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8')
const customer = read('src/features/customer/pages/CustomersView.vue')
const table = read('src/components/lists/ListTable.vue')
const css = read('src/styles/tables.css')
const priceLists = read('src/features/catalog/pages/PriceListsView.vue')
const extractStyle = (source) => (source.match(/<style[^>]*>([\s\S]*?)<\/style>/) || [,''])[1]

test('Customers opts into one shared wide table without replacing the ListTable or handlers', () => {
  assert.match(customer, /<ListTable\s+class="list-table-wrap--sticky-actions"\s+:busy=/)
  assert.match(customer, /:sort="state\.query\.sort"\s+@sort-change=/)
  assert.match(customer, /<template\s+#cell-actions=/)
  assert.match(customer, /<TableRowActions\s+authorization-mode="effective"/)
  assert.match(customer, /@state-changed="void load\(\)"/)
  assert.match(table, /<div\s+class="list-table-wrap"/)
  assert.doesNotMatch(extractStyle(customer), /(?:list-table|table-row-actions|\btd:|\bth:)/,
    'Customer scoped CSS must not own table chrome')
})

test('Only opted-in wide tables get a minimum width and a sticky Actions column', () => {
  assert.match(css, /\.list-table\s*\{\s*min-width:\s*var\(--ds-list-table-min-width\)/)
  assert.match(css, /\.list-table-wrap--sticky-actions\s*\{\s*--ds-list-table-min-width:\s*1040px;/)
  assert.match(css, /\.list-table-wrap--sticky-actions\s+\.list-table\s+:is\(th, td\):last-child\s*\{[^}]*position:\s*sticky;[^}]*right:\s*0;/s)
  assert.match(css, /\.list-table-wrap--sticky-actions\s+\.list-table\s+th:last-child\s*\{[^}]*z-index:\s*2;/s)
  // Price Lists keeps its 1080px minimum and opt-in sticky Actions via the same owner.
  assert.match(priceLists, /class="list-table-wrap--sticky-actions list-table-wrap--price"/)
  assert.match(css, /\.list-table-wrap--sticky-actions\.list-table-wrap--price\s*\{\s*--ds-list-table-min-width:\s*1080px;/)
  assert.doesNotMatch(extractStyle(priceLists), /:deep\(\.list-table\)|\.detail-table\s+(?:th|td)/)
  assert.doesNotMatch(read('src/features/supplier/pages/SuppliersView.vue'), /list-table-wrap--sticky-actions/)
})

test('Sticky action hover and keyboard focus stay on semantic surfaces with reserved borders', () => {
  const sticky = css.match(/\.list-table-wrap--sticky-actions\s+\.list-table\s+:is\(th, td\):last-child\s*\{([^}]+)\}/)?.[1] || ''
  assert.match(sticky, /white-space:\s*nowrap;/)
  assert.match(sticky, /background:\s*var\(--ds-bg-surface\)/)
  assert.match(sticky, /box-shadow:\s*-1px 0 0 var\(--ds-border-subtle\)/)
  assert.doesNotMatch(sticky, /(?:^|;)\s*border(?:-width)?\s*:/)
  assert.match(css, /\.list-table-wrap--sticky-actions\s+\.list-table\s+tbody tr:is\(:focus-visible, :has\(:focus-visible\)\) td,\s*\.list-table-wrap--sticky-actions\s+\.list-table\s+tbody tr:hover td:last-child\s*\{\s*background:\s*var\(--ds-table-row-hover\);/)
  assert.match(read('src/styles/base.css'), /\*,\s*\*::before,\s*\*::after\s*\{\s*box-sizing:\s*border-box;/)
  for (const [_, declarations] of css.matchAll(/[^{}]*:(?:hover|focus-visible)[^{}]*\{([^{}]+)\}/g)) {
    assert.doesNotMatch(declarations, /(?:^|;)\s*(?:border(?:-width)?|padding|margin|width|height|font-size)\s*:/,
      'Hover/focus must not modify layout metrics')
  }
})

test('Sticky Actions never expose underlying scrolled columns through paint transitions', () => {
  const tablePaint = css.match(/:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) td\s*\{([^}]+)\}/)?.[1] || ''
  assert.match(tablePaint, /transition:\s*background-color var\(--ds-motion-fast\) ease/,
    'ordinary tables must preserve their existing animation')
  const stickyPaint = css.match(/\.list-table-wrap--sticky-actions\s+\.list-table\s+tbody td\s*\{([^}]+)\}/)?.[1] || ''
  assert.match(stickyPaint, /transition:\s*none;/,
    'every cell in a sticky table must paint immediately, not just Actions')
  assert.ok(css.indexOf('.list-table-wrap--sticky-actions .list-table tbody td') >
    css.indexOf('background-color var(--ds-motion-fast)'), 'sticky override must follow generic paint')
  assert.match(css, /\.list-table-wrap--sticky-actions\s+\.list-table\s+:is\(th, td\):last-child\s*\{[^}]*background:\s*var\(--ds-bg-surface\);/s,
    'sticky surface must be opaque when idle')
  assert.match(css, /\.list-table-wrap--sticky-actions\s+\.list-table\s+tbody tr:hover td:last-child\s*\{\s*background:\s*var\(--ds-table-row-hover\);/,
    'sticky hover must use the same shared table token')
  assert.match(priceLists, /list-table-wrap--sticky-actions list-table-wrap--price/,
    'Price Lists must inherit the same stable sticky paint')
})

test('Customer sticky table colors resolve for both themes without a local palette', () => {
  const tokens = read('src/styles/tokens.css')
  const [light, dark] = tokens.split(":root[data-ds-theme='dark']")
  for (const name of ['--ds-bg-surface', '--ds-bg-subtle', '--ds-bg-hover', '--ds-table-row-hover', '--ds-border-subtle']) {
    assert.match(light, new RegExp(name + ':\\s*#[0-9a-fA-F]{6}'))
    assert.match(dark, new RegExp(name + ':\\s*#[0-9a-fA-F]{6}'))
  }
  assert.doesNotMatch(extractStyle(customer), /#[a-f\d]{3,8}\b/i)
})
