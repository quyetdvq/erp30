// DS5 HF1: prevent border/box sizing drift on hover and keep copy-selection
// visually distinct from row/button hover. Standalone Node 22 source gates.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const walk = (folder) => fs.readdirSync(path.join(root, folder), { withFileTypes: true })
  .flatMap((entry) => entry.isDirectory() ? walk(`${folder}/${entry.name}`) : [`${folder}/${entry.name}`])
const files = walk('src').filter((p) => /\.(?:vue|css)$/.test(p))
const stripComments = (text) => text.replace(/\/\*[\s\S]*?\*\//g, '')
const styleText = (file) => {
  const source = read(file)
  return stripComments(file.endsWith('.vue')
    ? [...source.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)].map((match) => match[1]).join('\n')
    : source)
}
const styles = files.map((file) => ({ file, css: styleText(file) }))
const rules = styles.flatMap(({ file, css }) =>
  [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
    .map(([, selector, declarations]) => ({ file, selector, declarations })))
const splitDeclarations = (source) => [...source.matchAll(/(?:^|;)\s*([\w-]+)\s*:\s*([^;]+)/g)]
  .map((match) => [match[1].toLowerCase(), match[2].trim()])
const parseTokens = (source) => Object.fromEntries(
  [...source.matchAll(/(--ds-[a-z0-9-]+)\s*:\s*(#[a-f\d]{6});/gi)]
    .map((match) => [match[1], match[2].toLowerCase()]),
)
const tokens = read('src/styles/tokens.css')
const [lightRoot, darkRoot] = tokens.split(":root[data-ds-theme='dark']")
assert.ok(darkRoot, 'Document-level dark palette is required for portalled dialogs')
const palettes = {
  light: parseTokens(lightRoot),
  dark: { ...parseTokens(lightRoot), ...parseTokens(darkRoot) },
}
const luminance = (hex) => {
  assert.match(hex, /^#[\da-f]{6}$/i)
  const [r, g, b] = [1, 3, 5].map((index) => Number.parseInt(hex.slice(index, index + 2), 16) / 255)
    .map((channel) => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}
const contrast = (left, right) => {
  const [lo, hi] = [luminance(left), luminance(right)].sort((a, b) => a - b)
  return (hi + 0.05) / (lo + 0.05)
}

test('Global border-box reset includes pseudo-elements and has exactly one CSS owner', () => {
  assert.match(read('src/styles/base.css'), /\*\s*,\s*\*::before\s*,\s*\*::after\s*\{\s*box-sizing:\s*border-box;/)
  const owners = styles.flatMap(({ file, css }) =>
    [...css.matchAll(/\bbox-sizing\s*:\s*([^;]+);/g)]
      .map((match) => ({ file, value: match[1].trim() })))
  assert.deepEqual(owners, [{ file: 'src/styles/base.css', value: 'border-box' }])
})

test('Hover/focus CSS never changes border thickness, padding, size, or typography metrics', () => {
  const layoutProps = /^(?:border(?:-(?:top|right|bottom|left))?(?:-width)?|box-sizing|padding(?:-(?:top|right|bottom|left))?|margin(?:-(?:top|right|bottom|left))?|(?:min-|max-)?(?:width|height)|display|position|transform|scale|font-size|font-weight|line-height|letter-spacing|inset|top|right|bottom|left)$/
  const stateRules = rules.filter(({ selector }) => /:(?:hover|focus-visible|focus-within)\b/.test(selector))
  assert.ok(stateRules.length >= 35, 'Audit must include the actual global and feature hover/focus rules')
  for (const { file, selector, declarations } of stateRules) {
    for (const [prop, value] of splitDeclarations(declarations)) {
      assert.doesNotMatch(prop, layoutProps,
        `${file}: ${selector.trim()} changes ${prop}: ${value} during hover/focus`)
    }
  }
})

test('Shared buttons and controls reserve a 1px border before any hover and use color-only border changes', () => {
  const buttonCSS = read('src/styles/buttons.css')
  const formCSS = read('src/styles/forms.css')
  const tableCSS = read('src/styles/tables.css')
  assert.match(buttonCSS, /\.ds-button,\s*\.primary-button,\s*\.secondary-button\s*\{[^}]*border:\s*1px solid var\(--ds-border\)/s)
  assert.match(buttonCSS, /\.icon-button,\s*\.modal-card-button-close\s*\{[^}]*border:\s*1px solid transparent/s)
  assert.match(buttonCSS, /\.ds-icon-control\s*\{[^}]*border:\s*1px solid var\(--ds-border-subtle\)/s)
  assert.match(formCSS, /\.ds-input,\s*\.ds-select,\s*\.ds-textarea,\s*\.field-input,\s*\.field-select\s*\{[^}]*border:\s*1px solid var\(--ds-border\)/s)
  assert.match(tableCSS, /\.list-query-controls__field\s*>\s*:is\(input, select\)[\s\S]*?border:\s*1px solid var\(--ds-border\)/)
  assert.match(buttonCSS, /\.modal-card-button-close\[data-ds-icon="close"\]::before\s*\{[^}]*width:\s*15px/s)
  assert.equal((buttonCSS.match(/\.modal-card-button-close\s*\{/g) || []).length, 1,
    'Close button must not have overridden duplicate background-image declarations')
  for (const file of [
    'src/components/products/ProductDetailModal.vue',
    'src/features/catalog/pages/ProductEditorView.vue',
    'src/components/products/ProductRelationsDialog.vue',
  ]) {
    const css = styleText(file)
    for (const [, declarations] of css.matchAll(/\.(?:icon-button|relation-close)\s*\{([^{}]*)\}/g)) {
      assert.doesNotMatch(declarations, /\bborder\s*:\s*0\b|\bpadding\s*:/,
        `${file}: icon/close buttons must retain the common reserved border and padding`)
    }
  }
  assert.doesNotMatch(styleText('src/features/catalog/pages/ProductEditorView.vue'), /\.sr-only\s*\{/,
    'Use the global utilities.css screen-reader helper, not another copy')
})

test('Light and dark text selections remain readable and visibly distinct from hover and selected surfaces', () => {
  const base = read('src/styles/base.css')
  assert.match(base, /::selection\s*\{\s*background-color:\s*var\(--ds-selection-bg\);\s*color:\s*var\(--ds-selection-text\)/)
  assert.match(base, /::-moz-selection\s*\{\s*background-color:\s*var\(--ds-selection-bg\);\s*color:\s*var\(--ds-selection-text\)/)
  for (const [theme, palette] of Object.entries(palettes)) {
    const bg = palette['--ds-selection-bg']
    const fg = palette['--ds-selection-text']
    assert.ok(bg && fg, `${theme}: missing document-root selection tokens`)
    assert.ok(contrast(fg, bg) >= 4.5, `${theme}: selected text must pass 4.5:1`)
    for (const surface of ['--ds-bg-hover', '--ds-bg-selected', '--ds-primary-soft']) {
      assert.ok(contrast(bg, palette[surface]) >= 3,
        `${theme}: selected text background must be visually distinct from ${surface}`)
    }
  }
  for (const { file, css } of styles) {
    if (file === 'src/styles/base.css') continue
    assert.doesNotMatch(css, /::(?:-moz-)?selection\b/,
      `${file}: selection colors have one shared owner and must not be locally overridden`)
  }
})
