/* PR02 - Product editor composition regression (v2.6.0 FC03 + PR01 base).
 * Pure source assertions complement the real browser tests.
 * Business transactions and backend contracts are intentionally untouched. */
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const page = read('src/features/catalog/pages/ProductEditorView.vue')
const heading = read('src/features/catalog/product-editor/components/ProductEditorPageHeading.vue')
const image = read('src/components/products/ProductImageField.vue')
const validation = read('src/features/catalog/product-editor/validation.ts')
const payload = read('src/features/catalog/product-editor/payload.ts')
const css = page.split('<style scoped>')[1]?.split('</style>')[0] ?? ''
const template = page.split('<template>')[1]?.split('</template>')[0] ?? ''

function getBlock(startTag, endTag) {
  const start = template.indexOf(startTag)
  const end = template.indexOf(endTag, start + startTag.length)
  assert.ok(start >= 0 && end > start, `expected block ${startTag} -> ${endTag}`)
  return template.slice(start, end)
}

test('PR02 has one product editor form and one shared image field; headings and actions are connected', () => {
  assert.equal((template.match(/<form\b/g) || []).length, 1)
  assert.equal((template.match(/<ProductImageField\b/g) || []).length, 1)
  assert.match(template, /<form id="product-editor-form" class="editor-form" @submit\.prevent="save">/)
  assert.match(template, /<ProductEditorPageHeading[\s\S]*?save-form-id="product-editor-form"/)
  assert.match(heading, /:form="saveFormId"/)
  assert.match(heading, /type="submit"/)
  assert.match(template, /<footer class="editor-actions">[\s\S]*?type="submit" form="product-editor-form" class="primary-button"/)
  assert.match(template, /@changed="afterWorkflowChange"/)
  assert.match(template, /:disabled="busy \|\| !canEdit \|\| !lookupsReady"/)
  assert.match(template, /:image-available="productHasImage\(product\)"/)
  assert.match(template, /@dirty="onImageDirty"/)
  assert.match(image, /defineExpose\(\{ commit \}\)/)
})

test('PR02 main cards contain exactly the canonical Product form fields, without invented controls', () => {
  assert.match(template, /class="product-editor-main"/)
  for (const key of ['editorBasics', 'uomPrice', 'editorPackaging', 'operational', 'editorImageCard']) {
    assert.match(template, new RegExp(`t\\('product\\.${key}'\\)`))
  }
  const required = ['product_code', 'sku', 'barcode', 'product_type', 'product_name',
    'product_child_name', 'price',
    'net_weight_g', 'package_gross_weight_g', 'package_length_mm',
    'package_width_mm', 'package_height_mm', 'serial_management_enabled', 'description']
  for (const field of required) assert.match(template, new RegExp(`v-model="form\\.${field}"`), `missing ${field}`)
  for (const absent of ['brand_id', 'vat_id', 'supplier_id', 'origin_id', 'catalog_visible', 'allow_sales', 'allow_purchase']) {
    assert.doesNotMatch(template, new RegExp(`v-model="form\\.${absent}"`), `invented field ${absent}`)
  }
  assert.match(template, /<SearchableSelect[\s\S]*?:model-value="form\.base_uom_id"/)
  assert.match(template, /<SearchableSelect[\s\S]*?:model-value="form\.product_group_id"/)
  assert.match(template, /<RemoteSearchableSelect[\s\S]*?:model-value="form\.parent_product_id"/)
  assert.match(template, /:max-decimals="2"/)
  assert.match(template, /:max-decimals="3"/)
})

test('PR02 optional fields stay optional; mandatory labels reflect real Product validator', () => {
  assert.match(validation, /if \(!flags\.isChild && !form\.product_name\.trim\(\)\)/)
  assert.match(validation, /if \(!flags\.isParentRole && !form\.base_uom_id\)/)
  assert.match(payload, /product_code: form\.product_code \|\| null/)
  assert.match(payload, /\.\.\.\(form\.product_group_id \?/)
  assert.match(payload, /\.\.\.\(optionalString\(form\.price\)/)
  // Only product/child name and base UOM are starred here; group, code and price are optional.
  const starred = template.match(/<span class="required-mark"/g) || []
  assert.equal(starred.length, 4, 'name, parent reference, child name, base UOM; branches are exclusive')
  const group = getBlock("<span>{{ t('product.productGroup') }}</span>", '</label>')
  const code = getBlock("<span>{{ t('product.productCode') }}</span>", '</label>')
  const price = getBlock("<span>{{ t('product.defaultPrice') }}</span>", '</label>')
  for (const field of [group, code, price]) assert.doesNotMatch(field, /required-mark|\brequired\b/)
})

test('PR02 preserves COMBO atomic create and edit-only guarded workflow', () => {
  const form = getBlock('<form id="product-editor-form"', '</form>')
  assert.doesNotMatch(form, /class="relationship-section"|v-model="row\.quantity"/,
    'COMBO quantity inputs must retain their pre-PR02 outside-form Enter behavior')
  assert.match(template, /\(\(!editing && isCombo\) \|\| \(showLegacyRelations && editing && role === 'COMBO'\)\)/)
  assert.match(template, /t\('product\.atomicComboCreate'\)/)
  assert.match(template, /<RemoteSearchableSelect[\s\S]*?:model-value="row\.component_product_id"/)
  assert.match(template, /v-model="row\.quantity"/)
  assert.match(template, /@click="addComboItem"/)
  assert.match(template, /@click="removeComboItem\(index\)"/)
  assert.ok(template.indexOf("t('product.comboComponents')") < template.indexOf('class="editor-actions"'),
    'COMBO components must be visible before the final Save bar')
  assert.match(template, /v-if="editing && product && canSetState"/)
  assert.match(form, /:disabled="isCombo"/)
  assert.match(form, /v-if="isParentRole" class="field-note"/)
  assert.match(page, /await saveProduct\(payload\(\), id\.value\)/)
  assert.match(page, /await imageField\.value\?\.commit\(String\(saved\.id\)\)/)
  assert.match(page, /unsaved\.markClean\(\)/)
})

test('PR02 summary is read-only, reactive and does not claim optional group/image as validation errors', () => {
  assert.match(page, /const groupSummary = computed/)
  assert.match(page, /const uomSummary = computed/)
  assert.match(page, /const editorNameComplete = computed/)
  assert.match(page, /const imageSummary = computed/)
  assert.match(template, /<dl class="product-editor-summary">/)
  assert.match(template, /:class="\['product-editor-summary__value', editorNameComplete \? 'is-success' : 'is-required'\]"/)
  assert.match(template, /:class="\['product-editor-summary__value', form\.base_uom_id \? 'is-success' : 'is-required'\]"/)
  assert.match(template, /<dd class="product-editor-summary__value">\{\{ groupSummary \}\}<\/dd>/)
  assert.match(template, /<dd class="product-editor-summary__value">\{\{ imageSummary \}\}<\/dd>/)
  assert.match(template, /\{\{ workflowStates\.initial\.value \}\}/)
  assert.doesNotMatch(template, />ACTIVE\.</)
})

test('PR02 uses responsive page-local composition, with no second form control style owner', () => {
  assert.match(css, /\.editor-panel\s*\{\s*container: product-editor \/ inline-size;/)
  assert.match(css, /grid-template-columns: minmax\(0, 1\.65fr\) minmax\(295px, 1fr\)/)
  assert.match(css, /@container product-editor \(max-width: 1040px\)/)
  assert.match(css, /@container product-editor \(max-width: 640px\)/)
  assert.match(css, /\.product-editor-fields--three/)
  assert.doesNotMatch(css, /(?:^|\n)\s*(?:input|textarea|select|\.n-input|\.n-base-selection)\s*\{/)
  assert.doesNotMatch(css, /#[\da-f]{3,8}\b/i)
  for (const locale of ['vi', 'en']) {
    const text = read(`src/i18n/locales/${locale}.ts`)
    for (const key of ['editorBasics', 'editorPackaging', 'editorSummary', 'editorTips',
      'editorImagePending', 'editorSaveProduct', 'editorSaveChanges']) {
      assert.match(text, new RegExp(`${key}:`), `${locale} missing ${key}`)
    }
  }
})

// Fieldsets only disable native form controls; Naive UI selections must
// receive the same permission/busy gate explicitly to avoid writable read-only forms.
test('PR02 HF01 explicitly disables Product Naive Select controls with the form gate', () => {
  const selectors = [...template.matchAll(/<(?:Remote)?SearchableSelect\b[\s\S]*?\/>/g)]
  for (const field of ['form.parent_product_id', 'form.product_group_id', 'form.base_uom_id']) {
    const control = selectors.find(([tag]) => tag.includes(`:model-value="${field}"`))
    assert.ok(control, `missing Naive selection ${field}`)
    assert.match(control[0], /:disabled="!canEdit \|\| busy"/, `${field} must respect PBAC and busy`)
  }
})
