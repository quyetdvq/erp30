import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
const read = (path) => readFileSync(new URL(`../../../../../${path}`, import.meta.url), 'utf8')
const field = read('frontend/src/components/products/ProductImageField.vue')
const editor = read('frontend/src/features/catalog/pages/ProductEditorView.vue')
const thumbnail = read('frontend/src/components/products/ProductListThumbnail.vue')

test('Product metadata changes do not discard the selected upload file', () => {
  assert.match(field, /watch\(\s*\(\) => props\.productId,\s*\(next, previous\)/)
  assert.match(field, /watch\(\s*\(\) => \[props\.imageAvailable, props\.imagePath\] as const/)
  assert.match(field, /if \(!selectedFile\.value && !removing\.value\) releaseImage\(\)/)
  assert.match(field, /next === adoptedProductId/)
})

test('Product JSON save commits the pending image before replacing its image metadata', () => {
  const save = editor.indexOf('const saved = await saveProduct(payload(), id.value)')
  const upload = editor.indexOf('await imageField.value?.commit(String(saved.id))', save)
  const update = editor.indexOf('product.value = { ...(imageResult ?? saved)', upload)
  assert.ok(save >= 0 && upload > save && update > upload)
  assert.match(editor, /:product-id="id \?\? \(product \? String\(product\.id\) : undefined\)"/)
  assert.match(editor, /if \(imagePartialError\.value\) imageDirty\.value = true/)
})

test('Product list trusts an authorized validated image URL without an extra availability gate', () => {
  assert.match(thumbnail, /uploadImageUrl\(props\.imagePath, 'product'\)/)
  assert.doesNotMatch(thumbnail, /auth\.user && props\.imageAvailable &&/)
  assert.doesNotMatch(thumbnail, /fetchProductImage|responseType:\s*'blob'/)
})
