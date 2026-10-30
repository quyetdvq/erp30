import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => readFileSync(path.join(root, file), 'utf8').replaceAll('\r\n', '\n')
const spec = read('contracts/openapi/openapi.yaml')
const supplierService = read('frontend/src/services/supplier.ts')
const payload = read('frontend/src/features/supplier/supplierPayload.ts')
const index = read('frontend/src/features/supplier/pages/SuppliersView.vue')
const detail = read('frontend/src/features/supplier/pages/SupplierDetailView.vue')
const stateAction = read('frontend/src/features/supplier/components/SupplierStateAction.vue')
const statePolicy = read('frontend/src/features/supplier/supplierStatePolicy.ts')
const backend = read('backend/app/Modules/Supplier/SupplierReadService.php')
const routes = read('frontend/src/router/routes.ts')
const nav = read('frontend/src/app/navigation.ts')
const ops = [
  'listSuppliers','createSupplier','getSupplier','updateSupplier','getSupplierCompanyUsages',
  'replaceSupplierCompanyUsages','setSupplierState',
]
for (const id of ops) test(`one canonical OpenAPI operation: ${id}`, () => {
  assert.equal(spec.match(new RegExp(`operationId: ${id}\\b`, 'g'))?.length ?? 0, 1)
})
test('no Supplier bank/reveal/history shortcut within the seven Core endpoints or cleartext DTO', () => {
  const source = spec.slice(spec.indexOf('# P2-SUP T07B Core contract'), spec.indexOf('  /api/v1/history/suppliers/{id}:'))
  assert.doesNotMatch(source, /operationId: .*?(bank|account|history)|\/api\/v1\/suppliers[^\n]*\/(bank|account|history)/i)
  const schema = spec.slice(spec.indexOf('# P2-SUP T07B Core contract;'), spec.indexOf('# P2-SUP T06C:'))
  assert.doesNotMatch(schema, /^\s+(?:bank|account|history)[a-z_]*:/im)
})
test('Supplier GET filters flat with page default 25 and cap 100', () => {
  const source = spec.slice(spec.indexOf('  /api/v1/suppliers:\n'), spec.indexOf('  /api/v1/suppliers/{id}:'))
  assert.match(source,/maximum: 100, default: 25/)
  assert.match(source,/name: supplier_group_code/)
  assert.match(source,/name: supplier_type/)
  assert.match(source,/name: company_id/)
  assert.doesNotMatch(source,/name: filter/)
  assert.match(supplierService,/page_size: query.page_size/)
  assert.match(supplierService,/SUPPLIER_FILTERS = \['status', 'supplier_type', 'supplier_group_code', 'company_id'\]/)
  assert.doesNotMatch(supplierService,/params\['filter'\]/)
})
test('Create requires company eligibility and explicit valid required fields',()=>{
  assert.match(spec,/required: \[name, supplier_type, phone, address, province_id, ward_id, all_companies, company_ids\]/)
  assert.match(payload,/supplierCreateBody/)
  assert.match(payload,/all_companies: draft.all_companies/)
  assert.match(payload,/supplier_group_code: 'STANDARD'/)
  assert.match(payload,/validSupplierDraft/)
})
test('all three Supplier mutation DTOs require strict integer expected_version', () => {
  for (const name of ['SupplierPatch', 'SupplierCompanyUsagePut','SupplierSetState']) {
    const section = spec.split(`    ${name}:\n`)[1]?.split(/\n    [A-Za-z][A-Za-z0-9]*:/)[0]
    assert.ok(section,name)
    assert.match(section,/expected_version: \{\$ref: '#\/components\/schemas\/SupplierExpectedVersion'\}/)
  }
  assert.match(spec,/SupplierExpectedVersion:\n      type: integer/)
  assert.match(supplierService,/value > 4294967294/)
  for (const name of ['supplierPatchBody', 'supplierUsageBody','supplierStateBody']) {
    assert.match(payload,new RegExp(`${name}\\(`))
  }
})
test('read profile never includes Bank columns; expose fail-closed state hint with PBAC',()=>{
  assert.match(backend,/\$row\['allowed_actions'\]\['setstate'\]/)
  assert.match(backend,/\$row\['setstate_targets'\] = \[\]/)
  assert.match(backend,/transitionTarget\('supplier', \$row, 'setstate'\)/)
  assert.match(spec,/setstate_targets/)
})
test('UI never uses generic Workflow execution or generic state action',()=>{
  assert.doesNotMatch(index+detail+stateAction,/BusinessWorkflowAction|executeBusinessWorkflowOption|business-workflow\/.*actions|state-target-id/)
  assert.match(index,/:show-toggle="false"/)
  assert.match(stateAction,/supplierStateBody\(target, snapshot.row_version\)/)
  assert.match(stateAction,/supplierSetState\(String\(snapshot.id\)/)
  assert.match(supplierService,/supplierSetState/)
})
test('Supplier URL + sidebar are explicitly opt-in via supplier.view',()=>{
  for (const path of ['suppliers/new','suppliers/:id/edit','suppliers/:id',"path: 'suppliers'"]) assert.ok(routes.includes(path),path)
  assert.match(nav,/permission: 'supplier.view'/)
})
test('Contact is shared; owner never changes through Supplier API',()=>{
  assert.match(detail,/<ContactList owner-type="SUPPLIER" :owner-id="id"/)
  assert.match(detail,/supplier_contact', 'view'/)
  assert.doesNotMatch(payload,/contact_type|contact_ref_id/)
})
test('Company search pages in SQL; no client first-200 filter',()=>{
  assert.match(supplierService,/page_size: 50, sort: 'name'/)
  assert.match(supplierService,/filter: \{ type: 'COMPANY', status: 'ACTIVE' \}/)
  assert.doesNotMatch(supplierService,/page_size: 200/)
})
test('409 preserves dirty form and only explicit reload can replace it',()=>{
  assert.match(detail,/if \(status === 409\) \{ conflict.value = true/)
  assert.match(detail,/function reloadAfterConflict\(\): void \{\n  if \(!unsaved.confirmDiscard\(\)\) return/)
  assert.match(detail,/<button type="button" class="secondary-button" :disabled="busy" @click="reloadAfterConflict"/)
})

// SUP-DEC-07: the Supplier Lifecycle, not frontend constants, owns the State list.
test('Supplier filter discovers status codes from its configured Lifecycle', () => {
  assert.match(index, /useBusinessWorkflowStates\('supplier', \[\], false\)/)
  assert.match(index, /const filters = computed<ListFilterDefinition\[\]>\(\(\) => \[/)
  assert.match(index, /key: 'status', labelKey: 'supplier.status', type: 'select', options: statusOptions.value/)
  assert.doesNotMatch(index, /\{ value: '(?:DRAFT|ACTIVE|INACTIVE)', labelKey: 'status\./)
  assert.match(index, /statusLabel\(row.status\)/)
  const metadata = read('frontend/src/composables/useBusinessWorkflowStates.ts')
  assert.match(metadata, /getBusinessWorkflowStates\(resourceName.value\)/)
  assert.match(metadata, /metadata.value\?\.states \?\? \[\.\.\.fallback\]/)
})

test('Supplier status actions are strictly Lifecycle-backed CAS, not generic Workflow', () => {
  const writer = read('backend/app/Modules/Supplier/SupplierWriteService.php')
  assert.match(writer, /\$this->states->initialState\('supplier'\)/)
  assert.match(writer, /\$this->states->transitionTarget\('supplier', \$old, 'setstate'\)/)
  assert.match(stateAction, /supplierById\(String\(props.supplier.id\)\)/)
  assert.match(stateAction, /supplierStateBody\(target, snapshot.row_version\)/)
  assert.match(stateAction, /supplierTransitionTargets\(currentSupplier.value\)/)
  assert.match(statePolicy, /snapshot.allowed_actions\?\.setstate !== true/)
  assert.match(index, /<SupplierStateAction :supplier="row" variant="icon"/)
  assert.match(detail, /<SupplierStateAction v-if="row && !isEdit && canState" :supplier="row" variant="button"/)
  assert.match(detail, /:before-open="beforeStateOpen"/)
  assert.doesNotMatch(index + detail + stateAction, /executeBusinessWorkflowOption|BusinessWorkflowAction/)
})
