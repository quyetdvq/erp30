/* DinkERP v2.6.0 Form Controls FC01 — offline architecture regression.
 * No dependency on Naive, Vite or a browser. Complement with
 * e2e/form-control-design-system.spec.ts for computed-style validation. */
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const forms = read('src/styles/forms.css')
const tokens = read('src/styles/tokens.css')
const theme = read('src/styles/naiveTheme.ts')

function vueStyles(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const file = path.join(dir, entry.name)
    if (entry.isDirectory()) return vueStyles(file)
    if (!file.endsWith('.vue')) return []
    const text = fs.readFileSync(file, 'utf8')
    return [...text.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)]
      .map((match) => ({ file: path.relative(root, file), css: match[1] }))
  })
}

test('forms.css is the one native control owner, imported once after tokens and before tables', () => {
  const global = read('src/styles/global.css')
  assert.equal((global.match(/@import ['"]\.\/forms\.css['"];?/g) || []).length, 1)
  assert.ok(global.indexOf("@import './tokens.css'") < global.indexOf("@import './forms.css'"))
  assert.ok(global.indexOf("@import './forms.css'") < global.indexOf("@import './tables.css'"))
  assert.match(forms, /:where\(input:not\(/)
  assert.equal((forms.match(/:where\(input:not\(/g) || []).length, 1,
    'one native control selector; nested states prevent repeated selector lists')
  assert.match(forms, /select, textarea\):not\(\.n-base-selection \*, \.n-input \*\)/)
  assert.doesNotMatch(read('src/styles/base.css'), /(?:input|textarea|select).*\b(?:border|accent-color|background|padding)\s*:/)
  assert.doesNotMatch(read('src/styles/tables.css'), /\.list-query-controls__field\s*>\s*:is\(input, select\)/)
  assert.doesNotMatch(read('src/styles/modal.css'), /(?:input|textarea|select):(?:focus|hover)|\.field-input/)
})

test('all control families inherit one token contract for appearance and field labels', () => {
  for (const token of [
    'height', 'padding-block', 'padding-inline', 'radius', 'font-size',
    'font-weight', 'label-gap', 'label-size', 'label-weight', 'label-color',
    'bg', 'text', 'placeholder', 'border', 'border-hover', 'focus', 'error',
    'disabled-bg', 'disabled-text', 'readonly-bg', 'readonly-text',
    'check-size', 'switch-width', 'switch-height',
  ]) assert.match(tokens, new RegExp(`--ds-control-${token}:`), `missing --ds-control-${token}`)
  for (const kind of ['input', 'select', 'textarea', "type='number'", "type='date'", "type='checkbox'", "type='radio'", "role='switch'", 'input.ds-switch', '.n-base-selection', '.n-input', '.n-date-picker', '.n-input-number']) {
    assert.ok(forms.includes(kind) || kind.startsWith('type='), `${kind}: must be covered by CSS contract`)
  }
  for (const state of [':hover', ':focus-visible', ':active', ':disabled', ':read-only', "[aria-invalid='true']", '::placeholder']) {
    assert.ok(forms.includes(state), `${state}: required form-control state`)
  }
  for (const value of ['var(--ds-control-height)', 'var(--ds-control-padding-block)',
    'var(--ds-control-border-hover)', 'var(--ds-control-focus)',
    'var(--ds-control-label-gap)']) assert.ok(forms.includes(value), `missing token ${value}`)
  assert.match(forms, /:where\(label:has\(> input\[type='checkbox'\]\)/)
  assert.match(forms, /:where\(input\[type='checkbox'\]:not\(\[role='switch'\], \.ds-switch\), input\[type='radio'\]\)/)
  assert.match(forms, /:where\(input\[type='checkbox'\]\[role='switch'\], input\.ds-switch\)/)
})

test('Naive Input/Select medium theme synchronizes geometry and states in both palettes', () => {
  for (const palette of ['Light', 'Dark']) {
    const block = theme.split(`export const naive${palette}Overrides`)[1].split('export const naive')[0]
    assert.ok(block, `missing naive${palette}Overrides`)
    assert.match(block, /common:\s*\{[\s\S]*?heightMedium:\s*'36px'/)
    assert.match(block, /Input:\s*\{[\s\S]*?heightMedium:\s*'36px'/)
    assert.match(block, /Input:\s*\{[\s\S]*?paddingMedium:\s*'6px 12px'/)
    assert.match(block, /Input:\s*\{[\s\S]*?borderRadius:\s*'6px'/)
    assert.match(block, /Input:\s*\{[\s\S]*?textColor:\s*'#[0-9a-f]+'/)
    assert.match(block, /Input:\s*\{[\s\S]*?placeholderColor:\s*'#[0-9a-f]+'/)
    assert.match(block, /Select:\s*\{\s*peers:\s*\{\s*InternalSelection:\s*\{/)
    assert.match(block, /InternalSelection:\s*\{[\s\S]*?heightMedium:\s*'36px'/)
    assert.match(block, /InternalSelection:\s*\{[\s\S]*?textColor:\s*'#[0-9a-f]+'/)
    assert.match(block, /Checkbox:\s*\{\s*sizeMedium:\s*'18px'/)
    assert.match(block, /Radio:\s*\{\s*radioSizeMedium:\s*'18px'/)
  }
  for (const relative of ['SearchableSelect', 'RemoteSearchableSelect', 'RemoteMultiSearchableSelect']) {
    const source = read(`src/components/forms/${relative}.vue`)
    assert.match(source, /status\?: 'error' \| 'warning' \| 'success'/)
    assert.match(source, /:status="props\.status"/)
    assert.doesNotMatch(source.split('<style')[1] || '', /\.n-base-selection\s*\{[^}]*\b(?:border|min-height|padding):/)
  }
})

test('feature CSS may arrange controls, not independently redraw their skin', () => {
  const appearance = /\b(?:background(?:-color)?|border(?:-radius|-color|-width)?|font-size|font-weight|padding(?:-[a-z]+)?|min-height|box-shadow)\s*:/
  for (const { file, css } of vueStyles(path.join(root, 'src'))) {
    // Check scoped form descendants and :deep(Naive) chrome, while allowing
    // file-upload overlays (their absolute positioning is layout, not a field skin).
    const rules = [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
    for (const [, selector, declarations] of rules) {
      const isControl = /(?:\binput\b|\bselect\b|\btextarea\b|\.n-base-selection\b|\.n-input\b)/i.test(selector)
      if (!isControl || !appearance.test(declarations)) continue
      // Non-control descendants inside menu tables (e.g. text highlights) are
      // excluded by a selector filter rather than a blanket ban on CSS.
      const controlSelector = /(?:\binput(?:\[|\.|\s*\{|\s*$)|\bselect(?:\[|\.|\s*$)|\btextarea\b|\.n-base-selection\b|\.n-input\b)/i
      if (!controlSelector.test(selector.trim())) continue
      assert.fail(`${file}: duplicate form control appearance in scoped selector: ${selector.trim().slice(0, 140)}`)
    }
  }
})

test('form invalid styling is exposed through accessible state and Naive wrapper status', () => {
  for (const entity of ['customer', 'supplier']) {
    const source = read(`src/features/${entity}/pages/${entity[0].toUpperCase()}${entity.slice(1)}DetailView.vue`)
    assert.match(source, /:aria-invalid="(?:Boolean\(profileErrors\.|!!profileErrors\.)/)
    assert.match(source, /:status="profileErrors\.[a-z_]+ \? 'error' : undefined"/)
  }
  assert.match(forms, /\[aria-invalid='true'\]:is\(:focus, :active\)/)
  assert.match(forms, /--ds-control-error/)
})

test('native inputs and textareas match Naive Select visible chrome without double-bordering its inner input', () => {
  // In Naive 2.45.3, .n-base-selection-input itself is transparent/borderless.
  // The visible Select border/focus comes from .n-base-selection__state-border.
  assert.match(forms, /Naive's \.n-base-selection-input is a borderless INNER text surface/)
  assert.match(forms, /:where\(input:not\([\s\S]*?select, textarea\):not\(\.n-base-selection \*, \.n-input \*\)/)
  assert.match(forms, /box-shadow:\s*0 0 0 2px var\(--ds-control-shadow-focus\)/)
  assert.match(forms, /:is\(:focus, :active\):not\(:disabled\)/)
  assert.match(forms, /&\[aria-invalid='true'\]:is\(:focus, :active\):not\(:disabled\)/)
  assert.match(forms, /box-shadow:\s*none;[\s\S]*?&:disabled/)
  assert.match(forms, /&:is\(input, textarea\):read-only/)
  assert.match(tokens, /--ds-control-padding-inline: 12px;/)
  assert.match(tokens, /--ds-control-shadow-focus: color-mix\(in srgb, var\(--ds-primary\) 20%, transparent\)/)
  assert.doesNotMatch(forms, /\.n-base-selection-input\s*\{/, 'Naive inner input must remain library-owned')
  assert.doesNotMatch(forms, /&:(?:hover|focus-visible|active)\s*\{\s*\}/, 'control states must not be empty')
})


test('FC03 file pickers share one visible skin and one accessible overlay trigger', () => {
  assert.match(forms, /input\[type='file'\]\):not\(\.ds-file-trigger input\)::file-selector-button/)
  assert.match(forms, /--ds-control-height/)
  assert.match(forms, /--ds-control-padding-inline/)
  assert.match(forms, /:where\(input\[type='file'\]\):not\(\.ds-file-trigger input\)\[aria-invalid='true'\]/)
  assert.match(forms, /\.ds-file-trigger:focus-within/)
  assert.match(forms, /\.ds-file-trigger:has\(input:disabled\)/)
  assert.match(forms, /\.ds-file-trigger > input\[type='file'\]/)
  const product = read('src/components/products/ProductImageField.vue')
  const avatar = read('src/components/users/UserAvatarManager.vue')
  const user = read('src/features/security/pages/SecurityUsersView.vue')
  const importer = read('src/features/import/shared/ImportWizard.vue')
  assert.match(product, /<label class="ds-file-trigger"/)
  assert.doesNotMatch(product, /\.image-upload-button(?:\s|:|,|\{)/, 'Product cannot redraw file button')
  for (const [name, source] of [['Product', product], ['Avatar', avatar], ['User', user], ['Import', importer]]) {
    assert.match(source, /type="file"/, `${name} should retain native file selection`)
  }
  assert.doesNotMatch(avatar, /\.avatar-manager__file\s*\{[^}]*gap:/, 'Avatar file label uses shared gap')
  assert.doesNotMatch(importer, /\.file-field\s*span\s*\{/, 'Import label uses shared typography')
})
