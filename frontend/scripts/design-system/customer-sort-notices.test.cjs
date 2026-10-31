// DS5 HF3/HF4: Customer sort must match CustomerSql; global API errors
// use a single viewport-fixed toast rather than page or modal content.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8')

const table = read('src/components/lists/ListTable.vue')
const customer = read('src/services/customer.ts')
const backendSort = ['name', '-name', 'customer_code', '-customer_code', 'id', '-id']

test('ListTable header click emits exactly one field, cycling asc and desc without a none state', () => {
  assert.match(table, /emit\('sort-change', sortDirection\(field\) === 'asc' \? `-\$\{field\}` : field\)/)
  const changeSort = table.match(/function changeSort\([^]*?\n\}/)?.[0] || ''
  assert.doesNotMatch(changeSort, /terms\.push|terms\.join|nextDirection|split\(','\)/)
  assert.match(table, /if \(props\.busy\) return/)
  assert.match(read('src/components/lists/__tests__/listComponents.spec.ts'), /alternates two sort directions and replaces rather than appends/)
})

test('Customer service cannot send compound or unreviewed sort values to CustomerSql', () => {
  const allow = customer.match(/const CUSTOMER_SORT_WHITELIST = new Set\(\[([^]*?)\]\)/)?.[1] || ''
  for (const name of backendSort) assert.ok(allow.includes(`'${name}'`), `Missing ${name}`)
  assert.match(customer, /CUSTOMER_SORT_WHITELIST\.has\(query\.sort\) \? query\.sort : 'name'/)
  assert.match(customer, /sort,\n/)
  assert.match(read('src/features/customer/__tests__/customerListAdapter.spec.ts'), /CustomerSql-whitelisted single-field sorts/)
})

test('The application root owns one global API notice and dialogs have no copy', () => {
  const app = read('src/App.vue')
  const shell = read('src/components/AppShell.vue')
  const dialog = read('src/components/ui/AppDialogHost.vue')
  assert.equal((app.match(/<ApiErrorNoticeStack\b/g) || []).length, 1)
  assert.match(app, /import ApiErrorNoticeStack from/)
  assert.doesNotMatch(shell, /ApiErrorNoticeStack|topOpenDialogId/)
  assert.doesNotMatch(dialog, /ApiErrorNoticeStack|dialogLayers/)
  assert.match(dialog, /:trap-focus="true"/)
  assert.match(dialog, /@update:show="onUpdateShow"/)
  assert.equal(fs.existsSync(path.join(root, 'src/components/ui/dialogLayers.ts')), false)
})

test('One global NAlert box preserves status, dismiss, error code and requestId', () => {
  const notices = read('src/components/ui/ApiErrorNoticeStack.vue')
  assert.match(notices, /<Teleport to="body">/)
  assert.match(notices, /<NAlert/)
  assert.match(notices, /closable/)
  assert.match(notices, /dismissApiError\(notice\.id\)/)
  assert.match(notices, /aria-live="assertive"/)
  assert.match(notices, /notice\.message/)
  assert.match(notices, /notice\.requestId/)
  assert.doesNotMatch(notices, /placement|api-error-notice-stack--(?:page|dialog)/)
  assert.match(read('src/components/ui/__tests__/ApiErrorNoticeStack.spec.ts'), /teleports exactly one notice stack/)
})
