import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => readFileSync(path.join(root, file), 'utf8').replaceAll('\r\n', '\n')
const customer = read('frontend/src/features/customer/pages/CustomerDetailView.vue')
const supplier = read('frontend/src/features/supplier/pages/SupplierDetailView.vue')
const supplierState = read('frontend/src/features/supplier/components/SupplierStateAction.vue')
const employeeWorkflow = read('frontend/src/components/lists/BusinessWorkflowAction.vue')
const shared = read('frontend/src/styles/global.css')

function detailLayout(source) {
  const headerStart = source.indexOf('<header class="modal-card__header">')
  const headerEnd = source.indexOf('</header>', headerStart)
  const footerStart = source.indexOf('<footer class="modal-card__footer', headerEnd)
  const footerEnd = source.indexOf('</footer>', footerStart)
  assert.ok(headerStart >= 0 && headerEnd > headerStart && footerStart > headerEnd && footerEnd > footerStart)
  return {
    header: source.slice(headerStart, headerEnd + 9),
    middle: source.slice(headerEnd + 9, footerStart),
    footer: source.slice(footerStart, footerEnd + 9),
  }
}

for (const [name, source, rowState] of [
  ['Customer', customer, 'state'],
  ['Supplier', supplier, 'row.status'],
]) {
  test(`${name}: Lifecycle state is in Header and not a separate toolbar`, () => {
    const { header, middle, footer } = detailLayout(source)
    assert.match(header, /class="business-detail-header-main"/)
    assert.match(header, /class="business-detail-header-status"/)
    assert.ok(header.includes(`<TableStatus :status="${rowState}" />`))
    assert.match(header, /statusLabel\(/)
    assert.doesNotMatch(middle, /(?:customer|supplier)-detail-toolbar/)
    assert.doesNotMatch(header, /common\.edit|<BusinessWorkflowAction|<SupplierStateAction/)
    assert.doesNotMatch(footer, /<TableStatus|statusLabel\(/)
  })
  test(`${name}: Edit sits in the fixed Footer immediately after Close`, () => {
    const { footer } = detailLayout(source)
    const closePos = footer.indexOf("{{ t('common.close') }}")
    const editPos = footer.indexOf("{{ t('common.edit') }}")
    const savePos = footer.indexOf("{{ busy ? t('common.saving')")
    assert.ok(closePos !== -1 && editPos > closePos)
    assert.ok(savePos > editPos)
    assert.match(footer, /v-if="[^"]*canEdit" type="button" class="secondary-button"/)
    assert.match(footer, /form="(?:customer|supplier)-profile-form"/)
    assert.match(footer, /class="primary-button"/)
  })
}

test('CUS still uses its established Workflow action in Footer', () => {
  const { footer } = detailLayout(customer)
  assert.match(footer, /<BusinessWorkflowAction v-if="!editing && canToggle" resource="customer"/)
  assert.match(footer, /@changed="void afterCustomerWorkflowChange\(\)"/)
})

test('SUP uses its own CAS SetState action in Footer, not generic Workflow', () => {
  const { footer } = detailLayout(supplier)
  assert.match(footer, /<SupplierStateAction v-if="row && !isEdit && canState" :supplier="row" variant="button"/)
  assert.match(footer, /:before-open="beforeStateOpen"/)
  assert.match(supplierState, /supplierStateBody\(target, snapshot\.row_version\)/)
  assert.doesNotMatch(supplier + supplierState, /executeBusinessWorkflowOption|<BusinessWorkflowAction/)
})

test('CUS/SUP share neutral header state style; unknown configured states are not treated as INACTIVE', () => {
  assert.match(shared, /\.business-detail-header-main \{/)
  assert.match(shared, /\.business-detail-header-status \{/)
  assert.doesNotMatch(detailLayout(customer).header + detailLayout(supplier).header, /status-pill--off|status !== 'ACTIVE'/)
  assert.match(supplier, /row\?\.status|row\.status/)
})

test('Supplier transition modal matches Employee Workflow presentation', () => {
  for (const key of ['workflow.transitionSection', 'workflow.currentState', 'workflow.availableTransition', 'workflow.selectTransition', 'workflow.transitionSubmit']) {
    assert.ok(employeeWorkflow.includes(key), `Employee Workflow missing ${key}`)
    assert.ok(supplierState.includes(key), `Supplier missing ${key}`)
  }
  assert.match(supplierState, /<section class="supplier-state-transition-section">/)
  assert.match(supplierState, /class="modal-card__header supplier-state-dialog__header"/)
  assert.match(supplierState, /class="modal-card__footer supplier-state-dialog__footer"/)
  assert.match(supplierState, /<select v-model="selected"/)
  assert.match(supplierState, /\{\{ currentSupplier.status \}\} → \{\{ target \}\} \(setstate\)/)
  // M11 assignee endpoints are now supported for Supplier; generic execution still cannot bypass CAS.
  assert.match(supplierState, /getWorkflowNotificationAssignees/)
  assert.match(supplierState, /replaceWorkflowNotificationAssignees/)
  assert.doesNotMatch(supplierState, /executeBusinessWorkflowOption/)
})
