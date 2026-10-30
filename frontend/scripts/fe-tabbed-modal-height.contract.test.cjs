const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '../src')
const source = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const css = source('styles/global.css')

// Every actual multi-tab detail dialog in the HIS-03.3 baseline is enumerated.
// Organization, System/User display preferences and the AppShell have page tabs,
// not modal tabs; create/edit forms remain intentionally natural height.
const updated = [
  'components/products/ProductDetailModal.vue',
  'features/people/pages/PeopleEmployeesView.vue',
  'features/workflow/pages/LifecyclesView.vue',
  'features/security/pages/SecurityUsersView.vue',
  'features/security/pages/SecurityResourcesView.vue',
  'features/security/pages/SecurityAuthorizationGroupsView.vue',
]

test('all six previously variable-height tabbed detail dialogs opt in to the shared workspace class', () => {
  for (const file of updated) {
    const text = source(file)
    assert.match(text, /class="(?:modal-card|detail-modal|employee-detail-modal) tabbed-detail-workspace"[^>]*role="dialog"/s, file)
    const from = text.indexOf('tabbed-detail-workspace"')
    const until = text.indexOf('<footer class="', from)
    assert.ok(from > -1 && until > from, file)
    assert.match(text.slice(from, until), /(?:class="(?:detail-tabs|security-tabs|workflow-tabs)"|role="tablist")/, file)
    assert.match(text.slice(from, until + 70), /class="modal-card__footer"|class="detail-footer"/, file)
  }
})

test('Customer detail already fixes the height of its tabbed workspace; create form remains fluid', () => {
  const customer = source('features/customer/pages/CustomerDetailView.vue')
  assert.match(customer, /'customer-detail-modal--workspace': !isNew/)
  assert.match(customer, /\.customer-detail-modal--workspace \{\s*height: min\(760px, calc\(100vh - 40px\)\);\s*height: min\(760px, calc\(100dvh - 40px\)\);/)
  assert.match(customer, /\.customer-detail-modal__body \{[\s\S]*?min-height: 0;[\s\S]*?overflow-y: auto;/)
})

test('shared CSS keeps height stable and isolates scrolling to the modal body', () => {
  assert.match(css, /\.tabbed-detail-workspace \{[^}]*height: min\(760px, calc\(100vh - 40px\)\);[^}]*height: min\(760px, calc\(100dvh - 40px\)\);[^}]*overflow: hidden;/s)
  assert.match(css, /\.tabbed-detail-workspace > :is\(\s*\.modal-card__body, \.detail-body, \.tabbed-detail-workspace__body\s*\) \{[^}]*flex: 1 1 auto;[^}]*min-height: 0;[^}]*overflow-y: auto;/s)
  assert.match(css, /\.tabbed-detail-workspace > :is\(\.detail-tabs, \.security-tabs, \.workflow-tabs\) \{[^}]*overflow-x: auto;/s)
})

test('Employee content uses one scroll wrapper; its header/tabs/footer remain outside the wrapper', () => {
  const employee = source('features/people/pages/PeopleEmployeesView.vue')
  assert.match(employee, /class="employee-detail-modal tabbed-detail-workspace"/)
  const start = employee.indexOf('class="employee-detail-modal tabbed-detail-workspace"')
  const footer = employee.indexOf('<footer class="modal-card__footer">', start)
  const body = employee.indexOf('<div :key="activeTab" class="tabbed-detail-workspace__body">', start)
  assert.ok(body > start && footer > body)
  assert.match(employee.slice(body, footer), /<ContactList v-else-if="activeTab === 'contacts'"/)
  assert.match(employee, /\.employee-detail-modal \{[^}]*display: flex;[^}]*flex-direction: column;[^}]*overflow: hidden;/s)
  assert.match(employee.slice(footer, footer + 350), /<\/footer>/)
})

test('Product detail is flex-based so optional parent navigation never takes the scroll-row slot', () => {
  const product = source('components/products/ProductDetailModal.vue')
  assert.match(product, /\.detail-modal \{[^}]*display: flex;[^}]*flex-direction: column;/s)
  assert.match(product, /<div :key="activeTab" class="detail-body">/)
  assert.doesNotMatch(product, /grid-template-rows: auto auto auto 1fr auto;/)
})

test('Lifecycle remounts its scrolling body on tab change; subdialogs remain separate', () => {
  const lifecycle = source('features/workflow/pages/LifecyclesView.vue')
  assert.match(lifecycle, /<div :key="tab" class="modal-card__body">/)
  assert.match(lifecycle, /v-if="stateModalOpen" class="modal-backdrop"/)
  assert.match(lifecycle, /v-if="transitionModalOpen" class="modal-backdrop"/)
})

test('single-tab forms and nested edit dialogs do not opt in to forced height', () => {
  for (const file of updated) {
    const text = source(file)
    assert.equal((text.match(/tabbed-detail-workspace"[^>]*role="dialog"/g) || []).length, 1, file)
  }
  assert.doesNotMatch(source('features/organization/pages/OrganizationsView.vue'), /tabbed-detail-workspace/)
})

test('tabbed modal coverage is complete for the reviewed SFC inventory', () => {
  const observed = [
    'components/products/ProductDetailModal.vue',
    'features/customer/pages/CustomerDetailView.vue',
    'features/people/pages/PeopleEmployeesView.vue',
    'features/workflow/pages/LifecyclesView.vue',
    'features/security/pages/SecurityUsersView.vue',
    'features/security/pages/SecurityResourcesView.vue',
    'features/security/pages/SecurityAuthorizationGroupsView.vue',
  ]
  assert.equal(new Set([...updated, 'features/customer/pages/CustomerDetailView.vue']).size, observed.length)
  for (const f of observed) assert.ok(source(f).includes('role="dialog"'), f)
})


test('nested Contact form escapes a scrolling Employee or Customer detail workspace', () => {
  const contactList = source('components/contacts/ContactList.vue')
  assert.match(contactList, /<Teleport to="body">\s*<ContactFormModal[\s\S]*?\/>\s*<\/Teleport>/)
  assert.match(contactList, /<TableDetailModal/)
})
