// DinkERP v2.5.0 — permanent offline regression gates for modal/form unification.
// No Vue/npm dependency required; keep this test in check:design-system.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8')

function vueFiles(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const file = path.join(directory, entry.name)
    return entry.isDirectory() ? vueFiles(file) : entry.name.endsWith('.vue') ? [file] : []
  })
}

function allVueStyles() {
  return vueFiles(path.join(root, 'src')).map((file) => ({
    file: path.relative(root, file),
    style: (fs.readFileSync(file, 'utf8').match(/<style\b[^>]*>([\s\S]*?)<\/style>/g) || []).join('\n'),
  }))
}

// Use the platform path API: Windows uses backslashes, so string.endsWith('/ui/AppModal.vue')
// incorrectly includes this shared wrapper as a 41st production dialog.
function isSharedAppModalWrapper(file, projectRoot = root, platformPath = path) {
  return file === platformPath.join(projectRoot, 'src', 'components', 'ui', 'AppModal.vue')
}

test('Shared modal wrapper is excluded on Windows and POSIX, not feature dialogs', () => {
  for (const platformPath of [path.win32, path.posix]) {
    const projectRoot = platformPath.join('erp', 'frontend')
    assert.equal(isSharedAppModalWrapper(
      platformPath.join(projectRoot, 'src', 'components', 'ui', 'AppModal.vue'), projectRoot, platformPath,
    ), true)
    assert.equal(isSharedAppModalWrapper(
      platformPath.join(projectRoot, 'src', 'features', 'sales', 'AppModal.vue'), projectRoot, platformPath,
    ), false)
    assert.equal(isSharedAppModalWrapper(
      platformPath.join(projectRoot, 'src', 'components', 'ui', 'AppDialogHost.vue'), projectRoot, platformPath,
    ), false)
  }
})

test('All 40 production dialogs declare one of exactly three modal variants', () => {
  const usages = vueFiles(path.join(root, 'src'))
    .filter((file) => !isSharedAppModalWrapper(file))
    .flatMap((file) => [...fs.readFileSync(file, 'utf8').matchAll(/<AppDialogHost\b[^>]*>/gs)]
      .map((match) => ({ file, tag: match[0] })))
  assert.equal(usages.length, 40, 'Do not silently omit an audited modal; update the inventory and gate for new modules')
  const counts = { standard: 0, detail: 0, utility: 0 }
  for (const { file, tag } of usages) {
    const variant = /\bvariant="(standard|detail|utility)"/.exec(tag)?.[1]
    assert.ok(variant, `${path.relative(root, file)} must declare standard/detail/utility`)
    counts[variant] += 1
    assert.match(tag, /:open=/, 'Overlay state must stay controlled by the caller')
    assert.match(tag, /@close=/, 'Close handling must stay with the caller')
  }
  assert.deepEqual(counts, { standard: 30, detail: 8, utility: 2 })
  const host = read('src/components/ui/AppDialogHost.vue')
  assert.match(host, /variant\?: 'standard' \| 'detail' \| 'utility'/)
  assert.match(host, /`ds-dialog-host--\$\{props\.variant\}`/)
  assert.match(read('src/components/ui/AppModal.vue'), /variant: 'utility'/)
})

test('Exactly three central stylesheets own chrome, controls and detail-workspace layout', () => {
  const global = read('src/styles/global.css')
  for (const owner of ['modal.css', 'forms.css', 'detail-workspace.css']) {
    assert.equal((global.match(new RegExp(`@import ['\"]\\./${owner.replace('.', '\\.')}['\"];`, 'g')) || []).length, 1, `${owner}: imported exactly once`)
  }
  const modal = read('src/styles/modal.css')
  const forms = read('src/styles/forms.css')
  const detail = read('src/styles/detail-workspace.css')
  assert.match(modal, /\.ds-dialog-host\.ds-dialog-host > \.ds-dialog-surface\s*\{/)
  assert.match(modal, /\.ds-dialog-host \.ds-dialog-surface > header\s*\{/)
  assert.match(modal, /\.ds-dialog-host \.ds-dialog-surface > footer\s*\{/)
  assert.match(modal, /--ds-dialog-body-padding/)
  assert.match(modal, /\.ds-dialog-host--utility/)
  assert.doesNotMatch(modal, /input:where|\.field-input|\.field-select/)
  assert.match(forms, /:where\(input:not\(/)
  assert.match(forms, /\.ds-dialog-host \.ds-dialog-surface :is\(\.ds-form-grid, \.form-grid, \.ds-detail-form-grid\)/)
  assert.match(forms, /@container ds-dialog/)
  assert.doesNotMatch(forms, /\.ds-dialog-host[^\n]*>\s*(?:header|footer)/)
  assert.match(detail, /\.tabbed-detail-workspace/)
  assert.match(detail, /\.ds-detail-tabs/)
  assert.doesNotMatch(detail, /(?:input|select|textarea):(?:hover|focus)|input:not\(/,
    'Native input states must live in forms.css')
})

test('Feature components do not redefine shared modal chrome or native form widgets', () => {
  for (const { file, style } of allVueStyles()) {
    for (const forbidden of [
      /\.modal-card__header\s*\{/,
      /\.modal-card__footer\s*\{/,
      /\.modal-card__body\s*(?:\{|label\b|input\b|select\b|textarea\b)/,
      /\.workflow-notify-editor\s*\{/,
      /\.ds-dialog-surface\s*\{/,
    ]) assert.doesNotMatch(style, forbidden, `${file} is duplicating a shared CSS contract`)
  }
})

test('Native control geometry is consistent and does not alter checkboxes, textareas or Naive internals', () => {
  const forms = read('src/styles/forms.css')
  assert.match(read('src/styles/tokens.css'), /--ds-control-height:\s*var\(--ds-button-height-md\)/)
  assert.match(forms, /:where\(input:not\(/)
  assert.match(forms, /select, textarea\):not\(\.n-base-selection \*, \.n-input \*\)/)
  assert.match(forms, /&:not\(textarea\) \{ height: var\(--ds-control-height\); \}/,
    'One-line input/select are 36px; textarea keeps its intrinsic rows')
  for (const inputType of ['checkbox', 'radio', 'file', 'hidden', 'range', 'color', 'button', 'submit', 'reset']) {
    assert.match(forms, new RegExp(`\\[type='${inputType}'\\]`), `${inputType} excluded from native text fields`)
  }
  assert.match(forms, /:not\(\.n-base-selection \*, \.n-input \*\)/)
  assert.match(forms, /\[aria-invalid='true'\]/)
  assert.match(forms, /:focus-visible/)
  assert.match(forms, /:disabled/)
  assert.match(forms, /:read-only/)
})


test('Mobile modal form grid uses an equal-specificity container override', () => {
  const forms = read('src/styles/forms.css')
  // A media-only .form-grid override loses to the three-class default rule.
  // The container override must include the same .ds-dialog-host ancestor.
  assert.match(forms, /@container ds-dialog \(max-width: 560px\)\s*\{[\s\S]*?\.ds-dialog-host \.ds-dialog-surface :is\(\.ds-form-grid, \.form-grid, \.ds-detail-form-grid\)\s*\{\s*grid-template-columns:\s*minmax\(0, 1fr\)/,
    'Narrow modal fields must switch to one column even with the desktop three-class rule')
})

test('Tokens control spacing and Light/Dark palette; invalid scrollbar rules are absent', () => {
  const tokens = read('src/styles/tokens.css')
  const modal = read('src/styles/modal.css')
  assert.match(tokens, /--ds-dialog-body-padding: var\(--ds-space-4\)/)
  assert.match(tokens, /--ds-dialog-chrome-block: var\(--ds-space-3\)/)
  assert.match(tokens, /--ds-dialog-chrome-inline: var\(--ds-space-4\)/)
  assert.match(tokens, /:root\[data-ds-theme='dark'\]/)
  assert.match(modal, /--ds-dialog-body-padding: var\(--ds-space-3\)/)
  assert.match(modal, /--ds-overlay-scrim/)
  for (const css of ['modal.css', 'forms.css', 'detail-workspace.css']) {
    assert.doesNotMatch(read(`src/styles/${css}`), /scrollbar-gutter:\s*none\s*;/)
  }
})

test('Workflow notification editor is lazy-loaded once by each action owner', () => {
  const forms = read('src/styles/forms.css')
  const workflow = read('src/styles/workflow-notify.css')
  assert.doesNotMatch(forms, /\.workflow-notify-editor\s*\{/)
  assert.match(workflow, /\.workflow-notify-editor\s*\{/)
  assert.match(workflow, /\.workflow-notify-heading\s*\{/)
  for (const component of ['src/components/lists/BusinessWorkflowAction.vue',
    'src/features/supplier/components/SupplierStateAction.vue']) {
    const source = read(component)
    assert.match(source, /import '@\/styles\/workflow-notify\.css'/)
    assert.match(source, /class="workflow-notify-editor"/)
    assert.doesNotMatch(source.split(/<style\b/)[1] || '', /\.workflow-notify-editor\s*\{/)
  }
})
