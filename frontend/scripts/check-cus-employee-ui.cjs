'use strict'
// Static CUS/Employee UI convention check. Not a substitute for Vue/Vitest/browser tests.
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const src = path.resolve(__dirname, '../src')
function read(relative) { return fs.readFileSync(path.join(src, relative), 'utf8') }
const list = read('features/customer/pages/CustomersView.vue')
const detail = read('features/customer/pages/CustomerDetailView.vue')
const routes = read('router/routes.ts')
const bread = read('router/breadcrumbs.ts')
const service = read('services/customer.ts')
for (const name of ['ListQueryControls', 'ListShell', 'ListTable', 'ListPagination', 'TableRowActions']) {
  assert.match(list, new RegExp(`<${name}\\b`), `Customer list must reuse ${name}`)
}
for (const name of ['page-heading', 'content-panel', 'modal-backdrop', 'modal-card__header', 'modal-card__footer']) {
  assert.ok((list + detail).includes(name), `${name}: shared Employee layout convention missing`)
}
assert.match(detail, /useUnsavedChanges/)
assert.match(detail, /role="dialog" aria-modal="true"/)
assert.match(detail, /@keydown="onDialogKeydown"/)
assert.match(detail, /'CORRECTION'/)
assert.match(detail, /corrects_interaction_id/)
assert.match(list, /rowAllowedActions\(row\)/)
assert.match(list, /authorization-mode="effective"/)
assert.match(service, /export function listCustomerPage/)
for (const name of ['customers-new', 'customers-edit', 'customers-detail', 'customers']) {
  const route = routes.match(new RegExp(`name: '${name}'[^}]+`, 's'))?.[0]
  assert.ok(route, `Missing route ${name}`)
  assert.match(route, /CustomersView\.vue/)
  assert.match(route, /breadcrumbTrail:/)
}
assert.match(bread, /customers: \[/)
assert.doesNotMatch(list + detail, /class="(?:container-fluid|table-responsive|btn btn-|form-control|nav nav-tabs)/)
console.log('CUS EMPLOYEE UI SOURCE CHECK: PASS (shared list components, route modal, PBAC hints, breadcrumbs, pending modules)')
