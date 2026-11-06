const assert = require('node:assert/strict')
const crypto = require('node:crypto')
const fs = require('node:fs')
const path = require('node:path')
const { test } = require('node:test')
const ts = require('typescript')

const frontend = path.resolve(__dirname, '../..')
const catalog = path.join(frontend, 'src/features/catalog')
const page = fs.readFileSync(path.join(catalog, 'pages/ProductEditorView.vue'), 'utf8')
const script = page.split('<script setup lang="ts">')[1]?.split('</script>')[0]
const digest = (text) => crypto.createHash('sha256').update(text).digest('hex')

function fingerprint(node) {
  const shape = []
  function visit(part) {
    shape.push(ts.SyntaxKind[part.kind])
    if (ts.isIdentifier(part) || ts.isLiteralExpression(part)) shape.push(part.text)
    ts.forEachChild(part, visit)
  }
  visit(node)
  return digest(shape.join('\u001f'))
}

// These fingerprints are pinned to the original V2.1 source; do not regenerate
// them from an edited V2.2 file. Formatting-only changes do not affect ASTs.
const preservedLogic = {
  load: 'fe2d413c035de88685bec85c675dc238b95af00ab63dd10cdccd27a48bbde445',
  loadConversions: '86ebbc85d2b42e416900655d5a92f112ff39ae7eb5535e48cdf6b99fc3aaf42f',
  loadChildren: 'e0d71e1a091c6cd95f949761c7bcfdd136fc1d176e0dbf6a542d3e5d0bb324d8',
  loadComboItems: 'dc2b5eaca8517ed1c5fa6687f81f3f86a79a013c5f6d45b795baf62331078fbc',
  afterWorkflowChange: '525e3a310bec98a581380ee3ca757f288e3c55110519c346bb97ba6fcd5d03a1',
  openConversion: '56d48b37a271daba9dbcf6abd6ceda48167964561df8ac0142154eaa7402d310',
  removeConversion: '0f359252f55bab48e9033154cff43fb8121d5db47487ee7c51dc5d7485bb44a9',
  addChild: '677b1e978c58cd6bbaad895fa8ccda3737ac07469b3f7dd639e8bd16847598d5',
  componentName: '5b134d07392a87daab94f7269aaf353d0de94faeca34cedcb46b34ba9bf0a3e3',
  componentRole: 'fa20e87c801c2c078a40e206e6fb0d6fec7d9f48eec5ebd9137c699631583dff',
  componentUom: '046fb966d9feb3e6f226c14a39f0ee650e35026bd31320f50ca6cbddf1d0deb2',
  addComboItem: '951ca248ec3e8f83c337f0164578d5b0542cebfeb7aa79142ddbf53ac5a58a36',
  removeComboItem: '7bd3ff1ff6fe4b7a7c765bad697d9a6efe65d5a7312577064ed9bc014ea1d2cb',
  businessStateLabel: '255d93327cb04b16bda3f8457ba707723f162dde79dc569626142662672c549d',
  displayMoney: '3a946bf568814bb620f21aa9e02ca45f9ed0dc0750b49d9fddffe5301e4d52a1',
  displayQuantity: '3c04a6f14a4c140ef09292383ac4e81fa627e5c071262515e3689e0227b3a40a',
  displayPackage: '2e46299dbec4093f6c5b492430d6c17ce7a6a0c16965dbd9ead7479d4340922a',
}

// Approved PR02 changes the editor markup and layout. Instead of pinning
// obsolete V2.1 byte-hashes (which fail on the unmodified v2.6.0 baseline),
// retain regression coverage for structural business-flow contracts.
test('PR02 editor uses one form; sections, relations and conversion dialog remain present', () => {
  assert.equal((page.match(/<form\b/g) || []).length, 1)
  assert.match(page, /<form id="product-editor-form" class="editor-form" @submit\.prevent="save">/)
  assert.match(page, /class="product-editor-layout"/)
  assert.match(page, /class="product-editor-main"/)
  assert.match(page, /class="product-editor-side"/)
  for (const key of ['editorBasics', 'uomPrice', 'editorPackaging', 'operational', 'image', 'editorSummary']) {
    assert.ok(page.includes(`t('product.${key}')`), `missing editor card ${key}`)
  }
  const formStart = page.indexOf('<form id="product-editor-form"')
  const formEnd = page.indexOf('</form>', formStart)
  assert.ok(formStart !== -1 && formEnd > formStart)
  const form = page.slice(formStart, formEnd)
  // Relations remain OUTSIDE the HTML form, as in v2.6.0: pressing Enter
  // inside a COMBO quantity editor must not implicitly submit Product.
  assert.ok(page.indexOf('showLegacyRelations', formEnd) > formEnd)
  assert.ok(page.indexOf('!editing && isCombo', formEnd) > formEnd)
  assert.match(page, /t\('product\.comboComponents'\)/)
  assert.ok(page.indexOf("t('product.comboComponents')", formEnd) < page.indexOf('<footer class="editor-actions">'))
  assert.match(page, /<BusinessWorkflowAction[\s\S]*?@changed="afterWorkflowChange"/)
  assert.match(page, /type="submit" form="product-editor-form" class="primary-button"/)
  assert.match(page, /<AppDialogHost :open="conversionEditorOpen"/)
  assert.match(page, /@click="saveConversionDraft"/)
})

test('PR02 scoped page CSS controls layout, not native control appearance', () => {
  const styles = page.split('<style scoped>')[1]?.split('</style>')[0]
  assert.ok(styles)
  assert.match(styles, /\.product-editor-layout[\s\S]*?grid-template-columns:/)
  assert.match(styles, /@container product-editor \(max-width: 640px\)/)
  assert.match(styles, /\.product-editor-fields--three/)
  assert.doesNotMatch(styles, /(?:^|\n)\s*(?:input|select|textarea)\s*\{/)
  assert.doesNotMatch(styles, /#[0-9a-f]{3,8}\b/i)
})

test('preserves every unchanged loading, workflow, relation, display and action function AST', () => {
  assert.ok(script, 'Product Editor script setup missing')
  const source = ts.createSourceFile('ProductEditorView.ts', script, ts.ScriptTarget.Latest, true)
  assert.equal(source.parseDiagnostics.length, 0)
  for (const [name, expectedHash] of Object.entries(preservedLogic)) {
    const statement = source.statements.find(
      (part) => ts.isFunctionDeclaration(part) && part.name?.text === name,
    )
    assert.ok(statement, `missing function ${name}`)
    assert.equal(fingerprint(statement), expectedHash, `unexpected behavior drift in ${name}`)
  }
})

test('original product saving transaction and lifecycle ordering remain untouched', () => {
  assert.ok(script)
  assert.equal(
    digest(
      script.slice(
        script.indexOf('  busy.value = true', script.indexOf('async function save():')),
        script.indexOf('async function afterWorkflowChange()'),
      ),
    ),
    'b95920ea8a8a40e672c501b3138a0ff12fd17097480416d0edb9655b64fbcfb5',
  )
  assert.equal(
    digest(script.slice(script.indexOf('onMounted('))),
    '3cb5be01c622493ad896ce3379a0f4d454fec21d09944738ce31f5c4425ba6b1',
  )
})

test('typed pure modules and lookup composable are connected to Product Editor', () => {
  assert.ok(script)
  for (const imported of [
    'useProductEditorLookups(',
    'emptyProductForm()',
    'productToForm(row)',
    'productValidationKey(',
    'comboValidationKey(',
    'conversionValidationKey(',
    'conversionsPayload(conversions.value)',
    'comboPayload(comboItems.value)',
    'productPayload(',
    "useUnsavedChanges(",
  ]) {
    assert.ok(script.includes(imported), `missing composition: ${imported}`)
  }
  for (const filename of ['types.ts', 'model.ts', 'validation.ts', 'payload.ts', 'useProductEditorLookups.ts']) {
    const code = fs.readFileSync(path.join(catalog, 'product-editor', filename), 'utf8')
    const tree = ts.createSourceFile(filename, code, ts.ScriptTarget.Latest, true)
    assert.equal(tree.parseDiagnostics.length, 0, `invalid TypeScript: ${filename}`)
  }
})

test('page heading and feedback retain original accessible semantics without extra DOM wrappers', () => {
  assert.match(page, /<ProductEditorPageHeading[\s\S]*?:editing="editing"[\s\S]*?save-form-id="product-editor-form"[\s\S]*?@back="router\.push\('\/products'\)"/)
  assert.match(page, /<ProductEditorFeedback\s+:error="error"[\s\S]*?:image-partial-error="imagePartialError"/)
  const head = fs.readFileSync(path.join(catalog, 'product-editor/components/ProductEditorPageHeading.vue'), 'utf8')
  const feedback = fs.readFileSync(path.join(catalog, 'product-editor/components/ProductEditorFeedback.vue'), 'utf8')
  assert.match(head, /<div class="page-heading">/)
  assert.match(head, /t\('product\.editTitle'\)/)
  assert.match(head, /t\('product\.createTitle'\)/)
  assert.match(head, /@click="emit\('back'\)"/)
  assert.match(head, /:form="saveFormId"/)
  assert.match(head, /:disabled="saveDisabled \|\| saveBusy"/)
  assert.match(feedback, /<p v-if="error \|\| validationError" class="inline-error" role="alert">/)
  assert.match(feedback, /<p v-if="isParentRole" class="restriction-note" role="status">/)
  assert.match(feedback, /<p v-if="imagePartialError" class="inline-error" role="alert">/)
  assert.doesNotMatch(feedback.split('<template>')[1].split('</template>')[0], /<div/)
})
