import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => readFileSync(path.join(root, file), 'utf8').replaceAll('\r\n', '\n')
const detail = read('frontend/src/features/supplier/pages/SupplierDetailView.vue')
const list = read('frontend/src/features/supplier/pages/SuppliersView.vue')
const state = read('frontend/src/features/supplier/components/SupplierStateAction.vue')
const policy = read('frontend/src/features/supplier/supplierStatePolicy.ts')
const contact = read('frontend/src/components/contacts/ContactList.vue')
const customer = read('frontend/src/features/customer/pages/CustomerDetailView.vue')
const vi = read('frontend/src/i18n/locales/vi.ts')
const en = read('frontend/src/i18n/locales/en.ts')

const testMarkup = (source, regex, name) => assert.match(source, regex, name)

test('Supplier detail uses same global modal header, footer and close button as CUS', () => {
  for (const cls of ['modal-backdrop', 'modal-card__header', 'modal-card-title', 'modal-card-button-close', 'modal-card__footer']) {
    assert.ok(customer.includes(cls), `CUS reference missing ${cls}`)
    assert.ok(detail.includes(cls), `SUP missing ${cls}`)
  }
  testMarkup(detail, /role="dialog" aria-modal="true"/, 'accessible dialog')
  testMarkup(detail, /ref="closeButton"/, 'focusable close button')
})
test('Supplier has fixed workspace geometry, scrollable body and tab scroll resets', () => {
  testMarkup(detail, /supplier-detail-modal--workspace/, 'stable workspace')
  testMarkup(detail, /height: min\(760px, calc\(100dvh - 40px\)\)/, 'CUS height')
  testMarkup(detail, /\.supplier-detail-modal__body \{[\s\S]*?overflow-y: auto/, 'body scroll')
  testMarkup(detail, /if \(detailBody.value\) detailBody.value.scrollTop = 0/, 'reset tab scroll')
  testMarkup(detail, /aria-selected="tab === item"/, 'tab states')
  testMarkup(detail, /@media \(max-width: 640px\)/, 'mobile layout')
})
test('Supplier profile reuses CUS geographic selection UX, not native province list', () => {
  testMarkup(detail, /<SearchableSelect :model-value="draft.province_id"/, 'CUS province select')
  testMarkup(detail, /<RemoteSearchableSelect :key="`supplier-ward-/, 'paged ward search')
  testMarkup(detail, /if \(provinceId === draft.value.province_id\) return/, 'no unnecessary ward reset')
  assert.doesNotMatch(detail, /<select :value="draft.province_id"/)
})
test('list keeps CUS ListQueryControls, pagination, status display and actions', () => {
  for (const name of ['ListQueryControls', 'ListShell', 'ListTable', 'ListPagination', 'TableStatus', 'TableRowActions']) {
    assert.ok(list.includes(name), name)
  }
  testMarkup(list, /<SupplierStateAction :supplier="row" variant="icon"/, 'list state action')
  testMarkup(list, /:show-toggle="false"/, 'no generic workflow toggle')
  testMarkup(list, /@changed="void load\(\)"/, 'refresh after state change')
})
test('Supplier SetState uses a Workflow-style modal, never window.confirm', () => {
  testMarkup(detail, /<SupplierStateAction v-if="row && !isEdit && canState" :supplier="row" variant="button"/, 'detail state button')
  testMarkup(state, /<Teleport to="body">/, 'nested modal escapes layout')
  testMarkup(state, /<section ref="dialog" class="supplier-state-dialog" role="dialog" aria-modal="true"/, 'accessible modal')
  testMarkup(state, /<select v-model="selected"/, 'explicit transition chooser')
  testMarkup(state, /onDialogKeydown\(event: KeyboardEvent\)/, 'keyboard support')
  assert.doesNotMatch(detail + state, /window.confirm\(t\('supplier.confirmState'/)
})
test('State action fetches fresh authorized details; server owns transitions and CAS', () => {
  testMarkup(state, /const fresh = await supplierById\(String\(props.supplier.id\)\)/, 'fresh detail snapshot')
  testMarkup(state, /currentSupplier.value = fresh/, 'fresh version used')
  testMarkup(state, /supplierTransitionTargets\(currentSupplier.value\)/, 'server targets')
  testMarkup(state, /canSubmitSupplierTransition\(currentSupplier.value, selected.value\)/, 'transition permission')
  testMarkup(state, /supplierSetState\(String\(snapshot.id\), supplierStateBody\(target, snapshot.row_version\)\)/, 'CAS')
  testMarkup(policy, /snapshot.allowed_actions\?\.setstate !== true/, 'fail closed')
  assert.doesNotMatch(state + detail + list, /executeBusinessWorkflowOption|<BusinessWorkflowAction|window\.confirm\(t\('supplier.confirmState'/)
  assert.doesNotMatch(policy, /\[(?:'DRAFT'|'ACTIVE'|'INACTIVE')/)
})
test('409 never refreshes or discards a dirty draft; explicit reload only', () => {
  testMarkup(state, /conflict.value = responseStatus === 409/, 'conflict detection')
  testMarkup(state, /if \(conflict.value\) emit\('conflict'\)/, 'conflict bubbled')
  testMarkup(detail, /function onStateConflict\(\): void \{[\s\S]*?conflict.value = true/, 'draft flagged')
  testMarkup(detail, /function reloadAfterConflict\(\): void \{\n  if \(!unsaved.confirmDiscard\(\)\) return/, 'only explicit reload')
  testMarkup(detail, /:before-open="beforeStateOpen"/, 'dirty form guard')
  testMarkup(detail, /@changed="void afterStateChanged\(\)"/, 'refresh on success only')
})
test('no Bank, invented interactions, or Supplier-specific Contact fork', () => {
  testMarkup(detail, /<ContactList owner-type="SUPPLIER" :owner-id="id"/, 'Shared Contact')
  assert.doesNotMatch(detail + state, /supplierBank|revealAccount|addCustomerInteraction|customerInteractions/)
})
test('Supplier Contact filter has no hardcoded Lifecycle fallback; CUS behavior unchanged', () => {
  assert.match(contact, /props.ownerType === 'SUPPLIER'\s*\n  \? \[\] : \['ACTIVE', 'DRAFT', 'INACTIVE', 'CLOSED'\]/)
  assert.match(contact, /useBusinessWorkflowStates\(ownerResource/)
})
test('new Supplier state message keys are present in both locales', () => {
  for (const locale of [vi, en]) {
    testMarkup(locale, /supplier: \{[\s\S]*?stateSaveError: '[^']+'/, 'save error')
    testMarkup(locale, /supplier: \{[\s\S]*?stateNotAllowed: '[^']+'/, 'permission denial')
  }
})
