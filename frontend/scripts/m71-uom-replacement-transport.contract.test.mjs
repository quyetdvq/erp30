import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

import { jsonBodySerializer } from '../src/api/generated/core/bodySerializer.gen.ts'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const service = read('src/services/productCatalog.ts')
const start = service.indexOf('export function replaceProductConversions(')
const end = service.indexOf('export function listProductComboItems(', start)
const replace = service.slice(start, end)

test('M7.1: UOM replacements use explicit items and the canonical JSON serializer', () => {
  assert.ok(start >= 0 && end > start)
  assert.match(replace, /body:\s*\{\s*items:\s*Array\.isArray\(body\)\s*\?\s*body\s*:\s*body\.items\s*\}/)
  assert.match(replace, /bodySerializer:\s*jsonBodySerializer\.bodySerializer/)
  assert.match(replace, /throwOnError:\s*true/)
  assert.doesNotMatch(replace, /^\s*body,\s*$/m)
})

test('M7.1: serialized JSON is nonempty for both an explicit clear-all and a normal replacement', () => {
  const empty = jsonBodySerializer.bodySerializer({ items: [] })
  assert.equal(empty, '{"items":[]}')
  const rows = [{ uom_id: '2', conversion_factor: '12.500000', usage_type: 'PURCHASE' }]
  const serialized = jsonBodySerializer.bodySerializer({ items: rows })
  assert.equal(typeof serialized, 'string')
  assert.deepEqual(JSON.parse(serialized), { items: rows })
})

test('M7.1: both editor entrypoints use the same validated UOM replacement service', () => {
  assert.match(read('src/components/products/ProductDetailModal.vue'), /await replaceProductConversions\(String\(currentProduct\.value\.id\), body\)/)
  assert.match(read('src/features/catalog/pages/ProductEditorView.vue'), /await replaceProductConversions\(id\.value, body\)/)
  const backend = read('../backend/app/Modules/Product/Conversion/ProductConversionController.php')
  assert.match(backend, /if \(\$raw === ''\)/)
  assert.match(backend, /!array_key_exists\('items', \$body\)/)
})
