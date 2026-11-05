const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../src')
const source = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const layout = source('styles/detail-workspace.css')
const modal = source('styles/modal.css')
const host = source('components/ui/AppDialogHost.vue')
const tabbed = [
  'components/products/ProductDetailModal.vue',
  'features/people/pages/PeopleEmployeesView.vue',
  'features/workflow/pages/LifecyclesView.vue',
  'features/security/pages/SecurityUsersView.vue',
  'features/security/pages/SecurityResourcesView.vue',
  'features/security/pages/SecurityAuthorizationGroupsView.vue',
]

test('six fixed tabbed business dialogs use variant=detail and shared workspace class', () => {
  for (const file of tabbed) {
    const text = source(file)
    assert.match(text, /<AppDialogHost[^>]*variant="detail"/s, file)
    assert.match(text, /class="ds-dialog-surface [^"]*tabbed-detail-workspace"[^>]*role="dialog"/s, file)
    const from = text.indexOf('tabbed-detail-workspace"')
    const footer = text.indexOf('<footer class="', from)
    assert.ok(footer > from, `${file} stationary footer`)
    assert.match(text.slice(from, footer), /(?:role="tablist"|class="security-tabs ds-detail-tabs)/, file)
  }
})

test('Customer detail alone opts in to fixed height; create form stays fluid', () => {
  const customer = source('features/customer/pages/CustomerDetailView.vue')
  assert.match(customer, /'ds-detail-workspace--fixed': !isNew/)
  assert.match(layout, /\.ds-detail-workspace--fixed\s*\{[^}]*height: min\(760px, calc\(100dvh - 40px\)\)/s)
  assert.match(customer, /class="ds-detail-body customer-detail-modal__body"/)
  assert.match(customer, /<form v-if="tab === 'profile' && !loading"/)
})

test('single scroll owner, stable header/tabs/footer in shared modal and detail CSS', () => {
  assert.match(layout, /\.tabbed-detail-workspace\s*\{[^}]*height: min\(760px, calc\(100dvh - 40px\)\);[^}]*overflow: hidden;/s)
  assert.match(layout, /\.tabbed-detail-workspace > :is\(\.modal-card__body, \.detail-body, \.tabbed-detail-workspace__body\)\s*\{[^}]*flex: 1 1 auto;[^}]*min-height: 0;[^}]*overflow-y: auto;/s)
  assert.match(modal, /\.ds-dialog-host \.ds-dialog-surface > header\s*\{[^}]*flex: 0 0 auto;/s)
  assert.match(modal, /\.ds-dialog-host \.ds-dialog-surface > footer\s*\{[^}]*flex: 0 0 auto;/s)
})

test('Employee tab switch remounts only body and never creates nested overlay', () => {
  const employee = source('features/people/pages/PeopleEmployeesView.vue')
  const from = employee.indexOf('class="ds-dialog-surface employee-detail-modal tabbed-detail-workspace"')
  const body = employee.indexOf('<div :key="activeTab" class="tabbed-detail-workspace__body">', from)
  const footer = employee.indexOf('<footer class="modal-card__footer">', from)
  assert.ok(from > 0 && body > from && footer > body)
  assert.match(employee.slice(body, footer), /<ContactList v-else-if="activeTab === 'contacts'"/)
  assert.match(layout, /\.tabbed-detail-workspace > :is\(\.modal-card__body, \.detail-body, \.tabbed-detail-workspace__body\)/)
})

test('Product detail still reserves the scroll slot for tab content', () => {
  const product = source('components/products/ProductDetailModal.vue')
  assert.match(product, /class="ds-dialog-surface detail-modal tabbed-detail-workspace"/)
  assert.match(product, /<div :key="activeTab" class="detail-body"/)
  assert.doesNotMatch(product, /grid-template-rows: auto auto auto 1fr auto;/)
})

test('Lifecycle body is keyed and state/transition subdialogs have separate hosts', () => {
  const lifecycle = source('features/workflow/pages/LifecyclesView.vue')
  assert.match(lifecycle, /<div :key="tab" class="modal-card__body">/)
  assert.match(lifecycle, /<AppDialogHost :open="stateModalOpen"[^>]*variant="standard"/)
  assert.match(lifecycle, /<AppDialogHost :open="transitionModalOpen"[^>]*variant="standard"/)
  assert.doesNotMatch(lifecycle, /class="(?:modal-backdrop|nested-backdrop)/)
})

test('nested editors and create/edit forms retain natural height, no forced detail variant', () => {
  for (const file of tabbed) {
    const text = source(file)
    assert.equal((text.match(/tabbed-detail-workspace"[^>]*role="dialog"/g) || []).length, 1, file)
  }
  assert.doesNotMatch(source('features/organization/pages/OrganizationsView.vue'), /tabbed-detail-workspace/)
  const product = source('components/products/ProductDetailModal.vue')
  assert.equal((product.match(/<AppDialogHost :open="(?:childFormOpen|conversionEditorOpen)"[^>]*variant="standard"/g) || []).length, 2)
})

test('detail inventory is explicit, including Customer and Supplier workspaces', () => {
  const observed = [...tabbed, 'features/customer/pages/CustomerDetailView.vue', 'features/supplier/pages/SupplierDetailView.vue']
  assert.equal(new Set(observed).size, 8)
  for (const file of observed) assert.match(source(file), /variant="detail"/)
})

test('Contacts use the same portalled host instead of nested Teleport/backdrop CSS', () => {
  const contactList = source('components/contacts/ContactList.vue')
  const contactForm = source('components/contacts/ContactFormModal.vue')
  assert.match(contactList, /<ContactFormModal/)
  assert.match(contactList, /<TableDetailModal/)
  assert.match(contactForm, /<AppDialogHost[^>]*variant="standard"/)
  assert.match(host, /<NModal/)
  assert.doesNotMatch(contactList, /<Teleport to="body">/)
})
