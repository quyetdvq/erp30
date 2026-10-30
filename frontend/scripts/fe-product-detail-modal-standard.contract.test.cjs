const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '..')
const modal = fs.readFileSync(path.join(root, 'src/components/products/ProductDetailModal.vue'), 'utf8')
const common = fs.readFileSync(path.join(root, 'src/styles/global.css'), 'utf8')

function conversionTable() {
  const from = modal.indexOf('v-else-if="activeTab === \'conversions\'"')
  const to = modal.indexOf('v-else class="tab-panel"', from)
  assert.ok(from > 0 && to > from, 'conversion tab must be present')
  return modal.slice(from, to)
}

test('Product detail reuses ERP header, title icon/code, backdrop and close button', () => {
  assert.match(modal, /class="modal-backdrop detail-backdrop"/)
  assert.match(modal, /class="modal-card__header detail-header"/)
  assert.match(modal, /class="modal-card-title product-detail-title"/)
  assert.match(modal, /<CubeOutline\s*\/>/)
  assert.match(modal, /class="product-detail-code"/)
  assert.match(modal, /class="modal-card-button-close"/)
  assert.match(modal, /class="modal-card__footer"/)
  assert.match(modal, /class="product-detail-heading"/)
  assert.match(modal, /class="product-header-status"/)
  assert.doesNotMatch(modal, /class="product-detail-toolbar"/)
  assert.match(modal, /class="secondary-button" @click="emit\('close'\)"/)
})

test('Product header shows status beside its identity; footer owns Edit and Close actions', () => {
  const header = modal.slice(modal.indexOf('<header class="modal-card__header detail-header">'), modal.indexOf('</header>', modal.indexOf('<header class="modal-card__header detail-header">')))
  const footerStart = modal.indexOf('<footer class="modal-card__footer">')
  const footer = modal.slice(footerStart, modal.indexOf('</footer>', footerStart))
  assert.match(header, /class="product-detail-heading"/)
  assert.match(header, /class="product-header-status"/)
  assert.match(header, /<TableStatus :status="currentProduct.status" \/>/)
  assert.match(header, /businessStateLabel\(currentProduct.status\)/)
  assert.doesNotMatch(header, /@click="editCurrent"/)
  assert.match(footer, /v-if="canEditCurrent"/)
  assert.match(footer, /:disabled="busy"/)
  assert.match(footer, /@click="editCurrent"/)
  assert.match(footer, /@click="emit\('close'\)"/)
  assert.ok(footer.indexOf('@click="editCurrent"') < footer.indexOf('@click="emit(\'close\')"'), 'Edit must appear before Close in footer')
  assert.match(modal, /\.product-detail-heading\s*\{[^}]*flex-wrap:\s*wrap/)
  assert.match(modal, /\.product-header-status\s*\{[^}]*align-items:\s*center/)
})

test('Product Vitest assertions use type-safe Vue Test Utils selectors', () => {
  const spec = fs.readFileSync(path.join(root, 'src/components/products/__tests__/ProductDetailUomActions.spec.ts'), 'utf8')
  assert.doesNotMatch(spec, /wrapper\.get\([^\n]+\)\.exists\(\)/, 'get() returns a wrapper type without exists(); use find().exists()')
  assert.match(spec, /wrapper\.find\('\.modal-card__header'\)\.exists\(\)/)
  assert.match(spec, /wrapper\.find\('\.modal-card__footer'\)\.exists\(\)/)
})

test('Fixed-height workspace has one scrolling body and non-scrolling header/tabs/footer', () => {
  assert.match(modal, /class="detail-modal tabbed-detail-workspace"/)
  assert.match(modal, /<nav class="detail-tabs" role="tablist"/)
  assert.match(modal, /<div :key="activeTab" class="detail-body"/)
  assert.match(common, /\.tabbed-detail-workspace\s*\{[\s\S]*?height: min\(760px, calc\(100dvh - 40px\)\)/)
  const bodyCss = modal.match(/\.detail-body\s*\{([^}]+)\}/)?.[1]
  assert.ok(bodyCss)
  assert.match(bodyCss, /overflow-y:\s*auto/)
  assert.match(bodyCss, /overflow-x:\s*hidden/)
  assert.match(modal, /\.table-wrap\s*\{[^}]*overflow-x:\s*auto/)
  assert.doesNotMatch(modal, /\.detail-header\s*\{[^}]*background:/)
  assert.doesNotMatch(modal, /\.detail-footer\s*\{[^}]*background:/)
})

test('UOM status shows the shared icon and translated label, not a hardcoded active string', () => {
  const table = conversionTable()
  assert.match(table, /<td class="conversion-state">/)
  assert.match(table, /<TableStatus :status="row\.status"\s*\/>/)
  assert.match(table, /businessStateLabel\(row\.status\)/)
  assert.doesNotMatch(table, /row\.status === 'ACTIVE' \? t\('status\.active'\)/)
})

test('UOM edit/delete use effective canonical row icons with Product parent edit hint', () => {
  const table = conversionTable()
  assert.match(table, /<TableRowActions[\s\S]*?authorization-mode="effective"/)
  assert.match(table, /:allowed-actions="rowAllowedActions\(currentProduct\)"/)
  assert.match(table, /:action-codes="conversionActionCodes"/)
  assert.match(modal, /const conversionActionCodes = \{ delete: 'edit' \} as const/)
  assert.match(table, /edit-permission="product\.edit"/)
  assert.match(table, /delete-permission="product\.edit"/)
  assert.match(table, /:show-edit="canEditCurrent"/)
  assert.match(table, /:show-delete="canEditCurrent"/)
  assert.match(table, /@edit="openConversion\(index\)"/)
  assert.match(table, /@delete="removeConversion\(index\)"/)
  assert.doesNotMatch(table, /class="icon-button danger"/)
})

test('UOM mutation continues to use explicit replacement service and cannot bypass save', () => {
  assert.match(modal, /async function saveConversions\(\): Promise<void>/)
  assert.match(modal, /await replaceProductConversions\(String\(currentProduct\.value\.id\), body\)/)
  assert.match(modal, /function removeConversion\(index: number\): void \{\s*conversions\.value = conversions\.value\.filter/)
})

test('Nested editor stays above the modal with responsive title and body', () => {
  assert.match(modal, /\.nested-backdrop\s*\{[^}]*z-index:\s*60/)
  assert.match(modal, /@media \(max-width:\s*600px\)/)
  assert.match(modal, /\.product-detail-name\s*\{[^}]*overflow-wrap:\s*anywhere/)
})
