// DS-P2 source-level regression gates; runnable without installing dependencies.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '../..')
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8')

// Scope is fixed to the 40 dialog declarations audited in ERP v2.2.0.
// Do not silently reduce this table after removing dialogs in a future module.
const migration = {
  'components/AppShell.vue': ['commandOpen = false'],
  'features/organization/pages/OrganizationsView.vue': ['closeForm'],
  'features/organization/pages/LocationsView.vue': ['closeForm'],
  'features/catalog/pages/ProductEditorView.vue': ['conversionEditorOpen = false'],
  'features/catalog/pages/PriceListsView.vue': ['closeForm', 'closeDetail'],
  'features/catalog/pages/ProductGroupsView.vue': ['closeForm'],
  'features/catalog/pages/ProductUomsView.vue': ['closeForm'],
  'features/security/pages/SecurityAuthorizationGroupsView.vue': ['detailOpen = false', 'modalOpen = false'],
  'features/security/pages/SecurityResourcesView.vue': ['detailOpen = false', 'modalOpen = false', 'actionModalOpen = false'],
  'features/security/pages/SecurityUsersView.vue': ['closeDetails', 'closeForm()', 'passwordOpen = false'],
  'features/security/pages/SecurityPoliciesView.vue': ['closeView', 'closeForm'],
  'features/people/pages/PeoplePositionsView.vue': ['closeForm'],
  'features/people/pages/PeopleEmployeesView.vue': ['employeeDetailOpen = false', 'closeEmployeeForm', 'closeAssignmentForm'],
  'features/people/pages/PeopleDepartmentsView.vue': ['closeForm'],
  'features/supplier/components/SupplierStateAction.vue': ['closeDialog'],
  'features/supplier/pages/SupplierDetailView.vue': ['close'],
  'features/workflow/pages/AutoNumberingView.vue': ['closeModal'],
  'features/workflow/pages/LifecyclesView.vue': ['detailOpen = false', 'closeForm', 'closeStateForm', 'closeTransitionForm'],
  'features/workflow/pages/WorkflowStatesView.vue': ['closeForm'],
  'features/customer/pages/CustomerDetailView.vue': ['closeDetail', 'closeContact'],
  'components/lists/TableDetailModal.vue': ["emit('close')"],
  'components/lists/BusinessWorkflowAction.vue': ['closeDialog'],
  'components/products/ProductRelationsDialog.vue': ["emit('close')"],
  'components/products/ProductDetailModal.vue': ["emit('close')", 'childFormOpen = false', 'conversionEditorOpen = false'],
  'components/contacts/ContactFormModal.vue': ['requestClose'],
}

test('All 40 audited dialogs use one portal host and retain their close handlers', () => {
  assert.equal(Object.keys(migration).length, 25)
  assert.equal(Object.values(migration).flat().length, 40)
  for (const [file, expectedClosers] of Object.entries(migration)) {
    const code = read(`src/${file}`)
    const hosts = [...code.matchAll(/<AppDialogHost\b[^>]*>/gs)].map((m) => m[0])
    assert.equal(hosts.length, expectedClosers.length, `${file}: overlay host count`)
    const labels = [...code.matchAll(/<section\b[^>]*>/gs)].map((m) => m[0]).filter((tag) => tag.includes('role="dialog"'))
    assert.equal(labels.length, expectedClosers.length, `${file}: role=dialog count`)
    for (const section of labels) {
      assert.match(section, /class="[^"]*ds-dialog-surface/, `${file}: shared surface`)
      assert.match(section, /aria-modal="true"/, `${file}: dialog semantics`)
      assert.match(section, /aria-label(?:ledby)?=/, `${file}: accessible name`)
    }
    expectedClosers.forEach((closer, index) => {
      assert.match(hosts[index], /:open=/, `${file}: open bound to existing state`)
      assert.ok(hosts[index].includes(`@close="${closer}"`), `${file}: preserve close #${index + 1}: ${closer}`)
    })
  }
})

test('NModal and focus-trapping code have exactly one owner', () => {
  const host = read('src/components/ui/AppDialogHost.vue')
  assert.match(host, /<NModal/)
  for (const property of [':trap-focus="true"', ':auto-focus="true"', ':block-scroll="true"',
    ':mask-closable="maskClosable"', ':close-on-esc="closeOnEsc"']) {
    assert.ok(host.includes(property), `Missing portal behavior: ${property}`)
  }
  assert.match(host, /if \(!value\) emit\('close'\)/)
  const modal = read('src/components/ui/AppModal.vue')
  assert.match(modal, /<AppDialogHost/)
  assert.doesNotMatch(modal, /<NModal/)
  for (const file of Object.keys(migration)) {
    const code = read(`src/${file}`)
    assert.doesNotMatch(code, /<NModal|<NDrawer|<NDialog/)
    assert.doesNotMatch(code, /@keydown(?:\.esc)?(?:\.stop)?="onDialogKeydown|@keydown="onContactKeydown|@keydown="onKeydown/)
  }
})

test('Legacy backdrops, hard-coded dialog z-index and manual portals are retired', () => {
  const oldClasses = /class="[^"]*(?:modal-backdrop|nested-backdrop|workflow-dialog-backdrop|supplier-state-backdrop|relation-backdrop)/
  for (const file of Object.keys(migration)) {
    const code = read(`src/${file}`)
    assert.doesNotMatch(code, oldClasses, `${file} still owns a backdrop`)
    assert.doesNotMatch(code, /<Teleport\s+to="body">/, `${file}: host owns portal`)
    const style = code.split(/<style\b/)[1] || ''
    assert.doesNotMatch(style, /#[\da-f]{3,8}\b/i, `${file}: use semantic light/dark colors`)
  }
  const styles = read('src/styles/modal.css')
  assert.doesNotMatch(styles, /\.modal-backdrop\s*\{/)
  assert.match(styles, /\.ds-dialog-host--wide/)
  assert.match(styles, /prefers-reduced-motion/)
  assert.match(styles, /var\(--ds-overlay-scrim\)/)
  assert.match(styles, /var\(--ds-bg-elevated\)/)
})

test('Nested and dirty-dialog close guards remain in place', () => {
  const customer = read('src/features/customer/pages/CustomerDetailView.vue')
  const supplier = read('src/features/supplier/pages/SupplierDetailView.vue')
  const supplierState = read('src/features/supplier/components/SupplierStateAction.vue')
  const contact = read('src/components/contacts/ContactFormModal.vue')
  assert.match(customer, /:close-on-esc="!contactOpen && !detailOpen"/)
  assert.match(read('src/components/products/ProductDetailModal.vue'), /:close-on-esc="!childFormOpen && !conversionEditorOpen"/)
  assert.match(customer, /profileDraft\.confirmDiscard\(\)/)
  assert.match(customer, /contactDraft\.confirmDiscard\(\)/)
  assert.match(supplier, /unsaved\.confirmDiscard\(\)/)
  assert.match(contact, /confirmDiscard\(\)/)
  assert.match(supplierState, /supplierStateBody\(target, snapshot\.row_version\)/)
  assert.match(supplierState, /responseStatus === 409/)
  assert.match(read('src/components/products/ProductDetailModal.vue'), /ds-dialog-stack-body/g)
})

// Regression for DS-P2: AppDialogHost.open is strictly boolean. The selected
// Policy record remains a nullable object and must not be bound directly.
test('Security Policy read-only dialog passes a boolean open state', () => {
  const code = read('src/features/security/pages/SecurityPoliciesView.vue')
  assert.match(code, /<AppDialogHost\s+:open="viewingPolicy !== null"\s+size="lg"/)
  assert.doesNotMatch(code, /<AppDialogHost\s+:open="viewingPolicy"/)
})

// Native controls now have exactly one owner (forms.css). The old test was
// checking modal.css because it predated the three-family design system.
test('Native modal controls are styled in forms.css without touching Naive internals or checkbox geometry', () => {
  const forms = read('src/styles/forms.css')
  const modal = read('src/styles/modal.css')
  const base = read('src/styles/base.css')
  assert.doesNotMatch(modal, /:is\(input|input:where/, 'modal.css must not own native controls')
  assert.equal((forms.match(/:where\(input:not\(/g) || []).length, 1,
    'exactly one native selector; states are nested within')
  assert.match(forms, /:where\(input:not\([\s\S]*?select, textarea\):not\(\.n-base-selection \*, \.n-input \*\)/)
  for (const state of ['&:not(textarea)', '&:is(input, textarea)::placeholder', '&:hover',
    '&:is(:focus, :active)', '&:disabled', '&:is(input, textarea):read-only',
    "&[aria-invalid='true']"]) assert.ok(forms.includes(state), `missing nested ${state}`)
  for (const type of ['checkbox', 'radio', 'file', 'hidden', 'range', 'color']) {
    assert.ok(forms.includes(`[type='${type}']`), `special input type excluded: ${type}`)
  }
  assert.match(forms, /:where\(\.n-input, \.n-base-selection\) \{ min-height: var\(--ds-control-height\); \}/)
  assert.match(forms, /border:\s*1px solid var\(--ds-control-border\)/)
  assert.match(forms, /:read-only/)
  assert.match(forms, /\[aria-invalid='true'\]/)
  assert.doesNotMatch(base, /input:not\(\.n-base-selection \*, \.n-input \*\)/)
  assert.doesNotMatch(base, /textarea:not\(\.n-input \*\)/)
  assert.doesNotMatch(base, /:where\(input, select, textarea\)/)
})
