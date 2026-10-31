// DS5 HF4: one viewport toast over every ERP modal; its z-index is exactly 999.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')

test('API error notices have one mounted owner across shell and dialogs', () => {
  const source = read('src/App.vue')
  assert.equal((source.match(/<ApiErrorNoticeStack\b/g) || []).length, 1)
  assert.doesNotMatch(read('src/components/AppShell.vue'), /ApiErrorNoticeStack/)
  assert.doesNotMatch(read('src/components/ui/AppDialogHost.vue'), /ApiErrorNoticeStack/)
  assert.match(read('src/components/ui/ApiErrorNoticeStack.vue'), /<Teleport to="body">/)
})

test('Global notice is fixed top-right at exactly 999, with responsive width and limited height', () => {
  const source = read('src/components/ui/ApiErrorNoticeStack.vue')
  const css = source.split('<style scoped>')[1] || ''
  const frame = css.match(/\.api-error-notice-stack\s*\{([^}]*)\}/)?.[1] || ''
  assert.match(frame, /position:\s*fixed;/)
  assert.match(frame, /z-index:\s*999;/)
  assert.match(frame, /top:\s*max\(/)
  assert.match(frame, /right:\s*max\(/)
  assert.match(frame, /width:\s*min\(/)
  assert.match(frame, /max-height:/)
  assert.match(frame, /overflow-y:\s*auto;/)
  assert.match(css, /@media \(max-width: 600px\)/)
  assert.doesNotMatch(source, /placement=['"](?:page|dialog)/)
})

test('NModal masks stay below global notice without changing close/focus contracts', () => {
  const dialog = read('src/components/ui/AppDialogHost.vue')
  const tokens = read('src/styles/tokens.css')
  assert.match(dialog, /:z-index="900"/)
  assert.match(dialog, /:trap-focus="true"/)
  assert.match(dialog, /:auto-focus="true"/)
  assert.match(dialog, /:block-scroll="true"/)
  assert.match(dialog, /:mask-closable="maskClosable"/)
  assert.match(dialog, /:close-on-esc="closeOnEsc"/)
  assert.match(tokens, /--ds-layer-overlay:\s*900;/)
  assert.match(tokens, /--ds-layer-toast:\s*999;/)
  assert.match(read('e2e/ds5-hf3-sort-notices.spec.ts'), /overlayVisible/)
})
