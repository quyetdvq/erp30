const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const { test } = require('node:test')
const read = (file) => fs.readFileSync(path.resolve(__dirname, '..', '..', file), 'utf8')

test('Product conversion modal has no viewport-only grid rule overriding shared container query', () => {
  const product = read('src/features/catalog/pages/ProductEditorView.vue')
  const forms = read('src/styles/forms.css')
  assert.doesNotMatch(product.split('<style scoped>')[1], /\.modal-card\s+\.form-grid\s*\{/)
  assert.match(forms, /@container ds-dialog \(max-width: 560px\)/)
  assert.match(forms, /\.ds-dialog-host \.ds-dialog-surface :is\(\.ds-form-grid, \.form-grid, \.ds-detail-form-grid\)/)
})

test('Notification text buttons inherit Design System height, rather than a 34px local rule', () => {
  const buttons = read('src/styles/buttons.css')
  assert.match(buttons, /min-height: var\(--ds-button-height-md\)/)
  for (const file of ['NotificationPoliciesTab.vue','NotificationPreferencesTab.vue']) {
    const view = read('src/features/notifications/pages/' + file)
    assert.match(view, /class="ds-button ds-button--primary"/)
    assert.doesNotMatch(view, /\.m11-row button\s*\{\s*min-height:/)
  }
})

test('Shared page panels do not create a nested mobile horizontal scrollport', () => {
  const pageCSS = read('src/styles/page-layout.css')
  const tableCSS = read('src/styles/tables.css')
  assert.doesNotMatch(pageCSS, /\.content-panel\s*\{[^}]*overflow-x\s*:\s*auto/)
  assert.match(tableCSS, /\.list-table-wrap,[\s\S]*?\.ds-table-scroll\s*\{[^}]*overflow-x:\s*auto/)
})
