import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { strict as assert } from 'node:assert'
import { buildProductInlineRows } from '../productInlineRows.ts'

const product = (id, role = 'SIMPLE', parent = null, allowed_actions) => ({
  id: String(id), role, parent_product_id: parent == null ? null : String(parent),
  product_code: `P${id}`, status: 'ACTIVE',
  ...(allowed_actions ? { allowed_actions } : {}),
})
const relation = (children, total = children.length, full = false, extra = {}) => ({
  children, total, full, loading: false, ...extra,
})
const layout = (page, relations = {}, collapsed = new Set(), showAll = new Set()) =>
  buildProductInlineRows(page, relations, collapsed, showAll)
const ids = (rows) => rows.filter((r) => r.kind === 'product').map((r) => r.product.id)

// Tests execute the exact TypeScript production helper without a bundler or DB.
// Run: node --experimental-strip-types --test frontend/src/features/catalog/__tests__/productInlineRows.node.mjs

test('initially renders two child rows per parent, with one view-more control', () => {
  const parent = product(10, 'PARENT')
  const children = [product(11, 'CHILD', 10), product(12, 'CHILD', 10)]
  const result = layout([parent], { '10': relation(children, 7) })
  assert.deepEqual(ids(result), ['10', '11', '12'])
  assert.deepEqual(result.filter((r) => r.kind === 'more').map(({ parentId, total, expanded }) =>
    [parentId, total, expanded]), [['10', 7, false]])
})

test('zero, one and two authorized children do not get an unnecessary more control', () => {
  const parent = product(5, 'PARENT')
  for (const size of [0, 1, 2]) {
    const children = Array.from({ length: size }, (_, i) => product(i + 6, 'CHILD', 5))
    const result = layout([parent], { '5': relation(children, size, true) })
    assert.equal(result.filter((r) => r.kind === 'more').length, 0)
    assert.equal(result.length, size + 1)
  }
})

test('expands to all authorized children and contracts to the two-row preview', () => {
  const parent = product(1, 'PARENT')
  const children = [2, 3, 4, 5].map((id) => product(id, 'CHILD', 1))
  const full = { '1': relation(children, 4, true) }
  assert.deepEqual(ids(layout([parent], full)), ['1', '2', '3'])
  const expanded = layout([parent], full, new Set(), new Set(['1']))
  assert.deepEqual(ids(expanded), ['1', '2', '3', '4', '5'])
  assert.equal(expanded.at(-1).expanded, true)
})

test('a parent can be collapsed without hiding the unrelated parents or page children', () => {
  const parentA = product(1, 'PARENT')
  const parentB = product(7, 'PARENT')
  const pageChild = product(99, 'CHILD', 90)
  const rows = layout([parentA, pageChild, parentB], {
    '1': relation([product(2, 'CHILD', 1)], 1, true),
    '7': relation([product(8, 'CHILD', 7)], 1, true),
  }, new Set(['1']))
  assert.deepEqual(ids(rows), ['1', '99', '7', '8'])
})

test('a child already on the primary page is not duplicated beneath a visible parent', () => {
  const parent = product(20, 'PARENT')
  const pageChild = product(22, 'CHILD', 20, { edit: true, setstate: false })
  const preview = product(22, 'CHILD', 20)
  const rows = layout([pageChild, parent], { '20': relation([preview], 1, true) })
  assert.deepEqual(ids(rows), ['20', '22'])
  assert.equal(rows[1].childOf, '20')
  assert.deepEqual(rows[1].product.allowed_actions, { edit: true, setstate: false })
})

test('expanded rows use fresh backend action hints, not older page-level grants', () => {
  const parent = product(20, 'PARENT')
  const pageChild = product(22, 'CHILD', 20, { edit: true, setstate: true })
  const freshChild = product(22, 'CHILD', 20, { edit: false, setstate: false })
  const rows = layout([pageChild, parent], { '20': relation([freshChild], 1, true) },
    new Set(), new Set(['20']))
  assert.deepEqual(rows[1].product.allowed_actions, { edit: false, setstate: false })
})

test('a page child is never hidden without a reliable authorized parent preview', () => {
  const child = product(22, 'CHILD', 20)
  const parent = product(20, 'PARENT')
  assert.deepEqual(ids(layout([child, parent])), ['22', '20'])
  assert.deepEqual(ids(layout([child, parent], { '20': relation([], 0, true) })), ['22', '20'])
  assert.deepEqual(ids(layout([child, parent], { '20': relation([], 1, false, { loading: true }) })), ['22', '20'])
})

test('only parent-child links are embedded; Combo components keep their existing dialog', () => {
  const combo = product(5, 'COMBO')
  const unrelated = product(8, 'CHILD', 123)
  const parent = product(10, 'PARENT')
  const related = product(11, 'CHILD', 10)
  const wrong = product(99, 'CHILD', 123)
  const rows = layout([combo, parent, unrelated], {
    '5': relation([product(7)], 1, true),
    '10': relation([wrong, related, related], 3, true),
  }, new Set(), new Set(['10']))
  assert.deepEqual(ids(rows), ['5', '10', '11', '8'])
})

test('preview-only child never inherits parent edit/state grants', () => {
  const parent = product(10, 'PARENT', null, { edit: true, setstate: true })
  const preview = product(11, 'CHILD', 10)
  const rows = layout([parent], { '10': relation([preview], 1, true) })
  assert.equal(rows[1].product.allowed_actions, undefined)
})

test('loading and errors preserve preview rows without inventing a full list', () => {
  const parent = product(10, 'PARENT')
  const children = [product(11, 'CHILD', 10), product(12, 'CHILD', 10)]
  const loading = layout([parent], { '10': relation(children, 8, false, { loading: true }) },
    new Set(), new Set(['10']))
  assert.deepEqual(ids(loading), ['10', '11', '12'])
  assert.equal(loading.at(-1).loading, true)
  const failed = layout([parent], { '10': relation(children, 8, false, { error: true }) },
    new Set(), new Set(['10']))
  assert.equal(failed.at(-1).error, true)
  assert.equal(failed.at(-1).expanded, false)
})

test('frontend uses the production helper, retains authorized actions and preserves table cells', () => {
  const vue = readFileSync(fileURLToPath(new URL('../pages/ProductsView.vue', import.meta.url)), 'utf8')
  assert.match(vue, /v-for="entry in inlineRows"/)
  assert.match(vue, /:show-edit="entry\.product\.allowed_actions\?\.edit === true"/)
  assert.match(vue, /:show-toggle="entry\.product\.allowed_actions\?\.setstate === true"/)
  assert.match(vue, /:show-view="true"/)
  assert.match(vue, /sort: 'product_id',[\s\S]*filter: \{ parent_product_id: key \}/)
  assert.match(vue, /getProductById\(String\(row\.id\)\)/)
  assert.match(vue, /display: table-cell;/)
  assert.doesNotMatch(vue, /\.table-actions\s*\{\s*display:\s*flex/)
})
