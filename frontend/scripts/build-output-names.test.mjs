import assert from 'node:assert/strict'
import { test } from 'node:test'
import { buildChunkFileName } from './build-output-names.ts'

test('multi-entry library chunks do not leak route names into filenames', () => {
  const examples = [
    ['naive-ui~index~AutoNumberingView~LifecyclesView~LocationsView', 'lib-ui'],
    ['vue-runtime', 'lib-vue'],
    ['icons', 'lib-icons'],
    ['vendor', 'lib-vendor'],
  ]
  for (const [name, label] of examples) {
    assert.equal(buildChunkFileName({ name }), `assets/${label}-[hash:8].js`)
  }
})

test('pages and shared modules use short descriptive kebab-case names on both OS path styles', () => {
  assert.equal(
    buildChunkFileName({
      name: 'ProductEditorView',
      facadeModuleId: '/erp/frontend/src/views/ProductEditorView.vue',
    }),
    'assets/page-product-editor-[hash:8].js',
  )
  assert.equal(
    buildChunkFileName({
      name: 'ProductsView',
      facadeModuleId: 'D:\\erp\\frontend\\src\\views\\ProductsView.vue',
    }),
    'assets/page-products-[hash:8].js',
  )
  assert.equal(
    buildChunkFileName({
      name: 'ProductEditorView',
      facadeModuleId: '/erp/frontend/src/features/catalog/pages/ProductEditorView.vue',
    }),
    'assets/page-product-editor-[hash:8].js',
  )
  assert.equal(
    buildChunkFileName({
      name: 'NotFoundView',
      facadeModuleId: 'D:\\erp\\frontend\\src\\app\\pages\\NotFoundView.vue',
    }),
    'assets/page-not-found-[hash:8].js',
  )
  assert.equal(
    buildChunkFileName({ name: 'useBusinessWorkflowStates' }),
    'assets/shared-use-business-workflow-states-[hash:8].js',
  )
})

test('names are bounded, URL-safe and deterministic', () => {
  const cases = [
    'naive-ui~index~AutoNumberingView~DisplaySettingsView~LifecyclesView~LocationsView',
    'VeryLongChunkNameContainingLOTSOfWordsAndRouteNames',
    'SecurityAuthorizationGroupsView',
    '___!@#!',
    'chunk-index',
  ]
  for (const name of cases) {
    const chunk = { name, facadeModuleId: '/frontend/src/views/test.vue' }
    const pattern = buildChunkFileName(chunk)
    assert.equal(buildChunkFileName(chunk), pattern)
    assert.match(pattern, /^assets\/[a-z0-9-]+-\[hash:8\]\.js$/)
    assert.ok(pattern.length <= 58, `Too long: ${pattern}`)
  }
})
