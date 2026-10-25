import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import assert from 'node:assert/strict'
import test from 'node:test'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const vue = readFileSync(resolve(root, 'src/features/catalog/pages/PriceListsView.vue'), 'utf8')
const vi = readFileSync(resolve(root, 'src/i18n/locales/vi.ts'), 'utf8')
const en = readFileSync(resolve(root, 'src/i18n/locales/en.ts'), 'utf8')

test('price list VIEW and EDIT have separate semantic dialog labels', () => {
  assert.match(vue, /type ItemDialogMode = 'view' \| 'edit'/)
  assert.match(vue, /:aria-label="detailMode === 'view' \? t\('pricing\.viewItems'\) : t\('pricing\.editItems'\)"/)
})

test('VIEW always renders plain read-only product, UOM and price cells', () => {
  assert.match(vue, /<span v-else>\{\{ itemProductLabel\(item\.product_id\) \}\}<\/span>/)
  assert.match(vue, /<span v-else>\{\{ itemUomLabel\(item\) \}\}<\/span>/)
  assert.match(vue, /<span v-else>\{\{ itemPriceLabel\(item\.price\) \}\}<\/span>/)
  for (const name of ['RemoteSearchableSelect', 'SearchableSelect', 'DecimalInput']) {
    assert.match(vue, new RegExp(`<${name}\\s+v-if="canEditItems"`))
  }
})

test('EDIT item actions require effective edit permission, not only base permission', () => {
  assert.match(vue, /rowAllowedActions\(row\)\?\.edit === true/)
  assert.match(vue, /hasBasePermission\('price_list', 'edit'\)/)
  assert.match(vue, /const canEditItems = computed\(\(\) => detailMode\.value === 'edit' && mayEditRow\(detailRow\.value\)\)/)
})

test('Add, Remove, Update and Save handlers also check edit authorization', () => {
  for (const action of ['addItem', 'removeItem', 'productChanged']) {
    const match = new RegExp(`(?:async )?function ${action}\\([^)]*\\): (?:void|Promise<void>) \\{\\s*if \\(!canEditItems\\.value \\|\\| itemBusy\\.value\\) return`)
    assert.ok(match.test(vue), `${action} needs an authorization guard`)
  }
  assert.match(vue, /async function saveItems\(\): Promise<void> \{\s*if \(!canEditItems\.value/)
})

test('VIEW has no mutation buttons and EDIT has a dedicated entry from price list form', () => {
  assert.match(vue, /v-if="editingId && mayEditRow\(editingRow\)"/)
  assert.match(vue, /@click="editPriceListItems"/)
  assert.match(vue, /v-if="canEditItems"[\s\S]*?@click="addItem"/)
  assert.match(vue, /<td v-if="canEditItems">[\s\S]*?@click="removeItem\(index\)"/)
  assert.match(vue, /v-if="canEditItems"[\s\S]*?@click="saveItems"/)
})

test('editing metadata is protected against accidental discard when switching to items', () => {
  assert.match(vue, /function editPriceListItems\(\): void \{[\s\S]*?unsaved\.confirmDiscard\(\)/)
  assert.match(vue, /void openDetail\(row, 'edit'\)/)
})

test('both languages define dedicated VIEW and EDIT labels', () => {
  for (const translation of [vi, en]) {
    for (const key of ['viewItems', 'editItems', 'viewOnly']) {
      assert.match(translation, new RegExp(`\\b${key}:`))
    }
  }
})