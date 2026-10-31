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

// Regression for DS-P2 HF2: a global dialog selector formerly painted a
// second border inside NSelect/NTreeSelect. Both widgets render native inputs
// below .n-base-selection, and NInput uses .n-input.
test('Naive UI controls retain their own borders and focus indicators inside dialogs', () => {
  const css = read('src/styles/modal.css')
  const base = read('src/styles/base.css')
  const formSelectors = [...css.matchAll(/([^{}]+)\{[^{}]*\}/g)]
    .map((match) => match[1])
    .filter((selector) => selector.includes('.ds-dialog-host .ds-dialog-surface') &&
      /:is\(input/.test(selector))
  assert.ok(formSelectors.length >= 5, 'must keep native input, disabled, focus and error states')
  for (const selector of formSelectors) {
    assert.match(selector, /:not\(\.n-base-selection \*, \.n-input \*\)/,
      `Naive UI internal input must be excluded: ${selector}`)
  }
  assert.match(css, /:is\(input, textarea\):not\(\.n-base-selection \*, \.n-input \*\):read-only/,
    'readonly styles must be restricted to native text fields, not custom selects')
  assert.match(css, /input:where\(:not\(\[type='checkbox'\], \[type='radio'\]\)\)/,
    'input type exclusions must not outrank native readonly/disabled color states')
  assert.match(css, /select, textarea\):not\(\.n-base-selection \*, \.n-input \*\) \{/,
    'native form control selector must join :is() and :not() without descendant whitespace')
  assert.match(css, /:is\(\.n-base-selection, \.n-input\) \{\s*min-height: var\(--ds-button-height-md\)/,
    'Naive UI outer controls retain the standard form height')
  assert.match(css, /border: 1px solid var\(--ds-border\)/,
    'native form fields must retain their accessible border')
  assert.match(base, /input:not\(\.n-base-selection \*, \.n-input \*\)/,
    'global focus ring must not be drawn on Naive UI nested inputs')
  assert.match(base, /textarea:not\(\.n-input \*\)/)
})
