import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
const read = (path) => readFileSync(resolve(root, path), 'utf8')
const api = read('contracts/openapi/openapi.yaml')
const routes = read('backend/app/Config/Routes/ApiV1/HistoryRoutes.php')
const sql = read('backend/app/Modules/History/HistorySql.php')
const visibility = read('backend/app/Bootstrap/SupplierHistoryVisibility.php')
const historyService = read('frontend/src/services/history.ts')
const panel = read('frontend/src/features/history/components/BusinessHistoryPanel.vue')
const detail = read('frontend/src/features/supplier/pages/SupplierDetailView.vue')
const monitor = read('frontend/src/features/history/pages/HistoryMonitorView.vue')
const cDetail = read('frontend/src/features/customer/pages/CustomerDetailView.vue')
const controller = read('backend/app/Controllers/Api/BusinessHistoryController.php')
const writer = read('backend/app/Modules/Supplier/SupplierWriteService.php')

function yamlPath(fragment) {
  const start = api.indexOf(`  ${fragment}:`)
  if (start < 0) return ''
  const next = api.indexOf('\n  /api/v1/', start + fragment.length + 3)
  return api.slice(start, next < 0 ? api.length : next)
}

test('only one new canonical Supplier master change History GET endpoint', () => {
  assert.equal((api.match(/operationId: listSupplierChangeHistory\b/g) || []).length, 1)
  const path = yamlPath('/api/v1/history/suppliers/{id}')
  assert.match(path, /operationId: listSupplierChangeHistory/)
  assert.match(path, /SupplierHistoryCollectionResponse/)
  assert.match(path, /maximum: 50, default: 25/)
  assert.doesNotMatch(api, /\/api\/v1\/suppliers\/\{id\}\/history:/)
  assert.match(routes, /history\/suppliers\/\(:num\)/)
  assert.equal((routes.match(/\$routes->get\(/g) || []).length, 3)
  assert.doesNotMatch(routes, /\$routes->(?:post|patch|put|delete)\(/)
})

test('History service reuses customer-like page, strict parent scope and snapshot', () => {
  assert.match(sql, /\['customer', 'supplier'\]/)
  assert.match(sql, /h\.resource_type = \? AND h\.resource_id = \?/)
  assert.match(sql, /h\.owner_domain = 'MASTER'/)
  assert.match(visibility, /SupplierScopeProvider/)
  assert.match(visibility, /SupplierSql::detail\(\$id, \$scope, \$group/)
  assert.match(visibility, /hasBaseViewPermission\('supplier'\)/)
  assert.match(visibility, /canView\('supplier', \$parent\)/)
  assert.doesNotMatch(visibility, /company_usages|bank_ciphertext|bank_account/)
})

test('Supplier History controller remains session-protected, PBAC-scoped, read-only and no-store', () => {
  assert.match(controller, /public function supplier\(string \$id\)/)
  assert.doesNotMatch(controller, /SUPPLIER_API_NOT_ENABLED/)
  assert.match(controller, /private, no-store/)
  assert.match(controller, /new SupplierHistoryVisibility\(\)/)
  assert.doesNotMatch(controller, /function (?:create|update|delete|restore)\(/)
})

test('Supplier write service already records master milestones inside the owning transaction', () => {
  for (const call of ["->created('supplier'", "->updated('supplier'", "->transitioned('supplier'"]) {
    assert.ok(writer.includes(call), call)
  }
  assert.match(writer, /transCommit\(\)/)
  assert.match(writer, /transRollback\(\)/)
  assert.doesNotMatch(writer, /INSERT INTO supplier_history/)
})

test('Supplier detail has CUS-like lazy History tab using the existing redacted panel', () => {
  assert.match(detail, /'profile' \| 'usage' \| 'contacts' \| 'history'/)
  assert.match(detail, /\['profile', 'usage', 'contacts', 'history'\]/)
  assert.match(detail, /v-else-if="row && tab === 'history' && !loading"/)
  assert.match(detail, /<BusinessHistoryPanel :supplier-id="id"/)
  assert.match(cDetail, /<BusinessHistoryPanel :customer-id="id"/)
  assert.doesNotMatch(detail, /v-html/)
  assert.doesNotMatch(detail, /BusinessWorkflowAction/)
})

test('shared panel cannot fetch another owner if IDs collide, keeps stale-response protection', () => {
  assert.match(panel, /const parent = props\.supplierId && !props\.customerId/)
  assert.match(panel, /if \(!parent\) throw new Error\('HISTORY_PARENT_REQUIRED'\)/)
  assert.match(panel, /\? await supplierHistory\(props\.supplierId!/)
  assert.match(panel, /: await customerHistory\(props\.customerId!/)
  assert.match(panel, /if \(token !== generation\) return/)
  assert.match(panel, /historyChangeDisplay\(event/)
  assert.doesNotMatch(panel, /v-html/)
})

test('Supplier History front service uses bounded read and exact parent ID', () => {
  assert.match(historyService, /export async function supplierHistory\(supplierId: string/)
  assert.match(historyService, /\^\[1-9\]\[0-9\]\*\$/)
  assert.match(historyService, /\/api\/v1\/history\/suppliers\/\$\{supplierId\}/)
  assert.match(historyService, /params: \{ page, page_size: pageSize \}/)
})

test('SYSTEM audit monitor has typed Supplier enrichment but never confers Supplier row VIEW', () => {
  assert.match(sql, /COALESCE\(c\.customer_code,s\.supplier_code\)/)
  assert.match(sql, /h\.resource_type = 'supplier' AND s\.id = h\.resource_id/)
  assert.match(monitor, /hasBasePermission\(event\.resource_type, 'view'\)/)
  assert.match(monitor, /<SupplierDetailView v-if="allowed && selectedObject\?\.type === 'supplier'"/)
  assert.match(monitor, /:supplier-id="selectedObject\.id"/)
  assert.doesNotMatch(monitor, /supplier\.view\s*=>\s*true/)
})

test('no PUR/FIN fake documents or Bank plaintext in new contract', () => {
  const path = yamlPath('/api/v1/history/suppliers/{id}')
  assert.match(path, /never contains Purchase, Receipt, Return or Payment/)
  const schema = api.slice(api.indexOf('    SupplierHistoryChangeValue:'), api.indexOf('    # P2-SUP T06C:'))
  assert.match(schema, /HIS-POL-002 compact tuple/)
  assert.match(schema, /redacted change/)
  assert.doesNotMatch(schema, /account_number:|bank_ciphertext:|iban:/)
})

test('vi/en labels are present without hardcoding states', () => {
  for (const locale of ['vi', 'en']) {
    const source = read(`frontend/src/i18n/locales/${locale}.ts`)
    const supplier = source.slice(source.indexOf('  supplier: {'), source.indexOf('  customer: {', source.indexOf('  supplier: {')))
    assert.match(supplier, /history: '/)
    assert.doesNotMatch(panel, /\['DRAFT', 'ACTIVE', 'INACTIVE'\]/)
  }
})
