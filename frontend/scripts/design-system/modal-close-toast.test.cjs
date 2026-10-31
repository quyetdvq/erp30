// DS5 HF5: shared modal Close contract + severity-aware global notices.
// Source guards run without dependencies; Vue/Vitest/Playwright cover runtime.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(root, rel), 'utf8')
const scan = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
  const item = path.join(dir, entry.name)
  return entry.isDirectory() ? scan(item) : item.endsWith('.vue') ? [item] : []
})

function palette(css, dark) {
  const [light, darkBlock] = css.split(":root[data-ds-theme='dark']")
  const vars = (block) => Object.fromEntries([...block.matchAll(/(--ds-[\w-]+)\s*:\s*([^;]+);/g)]
    .map((m) => [m[1], m[2].trim()]))
  return dark ? { ...vars(light), ...vars(darkBlock) } : vars(light)
}
function luminance(hex) {
  const rgb = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
  return rgb.map((n) => n <= .04045 ? n / 12.92 : ((n + .055) / 1.055) ** 2.4)
    .reduce((total, component, index) => total + component * [.2126, .7152, .0722][index], 0)
}
function contrast(a, b) {
  const x = luminance(a), y = luminance(b)
  return (Math.max(x, y) + .05) / (Math.min(x, y) + .05)
}

test('All 40 labeled modal header Close buttons use one CSS class and preserve their handlers', () => {
  const closeButtons = []
  for (const file of scan(path.join(root, 'src'))) {
    const content = fs.readFileSync(file, 'utf8')
    for (const match of content.matchAll(/<button\b((?:[^"'>]|"[^"]*"|'[^']*')*)>/gs)) {
      const attrs = match[1]
      if (!attrs.includes(':aria-label="t(\'common.close\')"')) continue
      closeButtons.push({ file, attrs })
      assert.match(attrs, /class="modal-card-button-close"/, `${file}: expected shared X style`)
      assert.match(attrs, /data-ds-icon="close"/, `${file}: expected canonical shared icon metadata`)
      assert.match(attrs, /type="button"/, `${file}: must never submit a form`)
      assert.match(attrs, /@click="[^"]+"/, `${file}: original close action must remain`)
    }
  }
  assert.equal(closeButtons.length, 40, 'all 40 header close buttons including AppModal')
  assert.doesNotMatch(read('src/components/products/ProductRelationsDialog.vue'), /relation-close|>\s*×\s*<\/button>/)
  assert.doesNotMatch(read('src/features/catalog/pages/PriceListsView.vue'), /CloseOutline|class="icon-button"\s+:aria-label="t\('common.close'\)"/)
  assert.doesNotMatch(read('src/components/ui/AppModal.vue'), /AppIconButton|CloseOutline/)
  for (const file of scan(path.join(root, 'src'))) assert.doesNotMatch(fs.readFileSync(file, 'utf8'), /modal-card-buttaon-close/, file)
})

test('Shared X icon has a single mask, visible focus, and reserved non-jumping border', () => {
  const buttons = read('src/styles/buttons.css')
  assert.match(buttons, /\.modal-card-button-close\[data-ds-icon="close"\]::before\s*\{[^}]*width:\s*15px/s)
  assert.match(buttons, /\[data-ds-icon="close"\]\s*\{[^}]*--ds-action-mask:\s*url\(/s)
  assert.match(buttons, /\.icon-button,\s*\.modal-card-button-close\s*\{[^}]*border:\s*1px solid transparent/s)
  assert.match(buttons, /\.modal-card-button-close:hover:not\(:disabled\)/)
  assert.match(buttons, /\.modal-card-button-close:focus-visible/)
  assert.doesNotMatch(read('src/styles/modal.css'), /\.relation-close/)
})

test('Error and warning notice boxes use distinct theme-aware surfaces and fixed geometry', () => {
  const css = read('src/components/ui/ApiErrorNoticeStack.vue')
  const tokens = read('src/styles/tokens.css')
  assert.match(css, /z-index:\s*999;/)
  assert.match(css, /border-inline-start:\s*4px solid var\(--ds-danger\);/)
  assert.match(css, /\.n-alert\.api-error-notice--warning\)/)
  assert.match(css, /'api-error-notice--error'/)
  assert.match(css, /'api-error-notice--warning'/)
  assert.match(css, /:color=\"notice\.status/)
  assert.doesNotMatch(css, /\.n-alert--(?:warning|error)|\.n-alert-body__icon/)
  assert.match(css, /background: var\(--ds-toast-error-bg\)/)
  assert.match(css, /background: var\(--ds-toast-warning-bg\)/)
  assert.match(css, /box-shadow: var\(--ds-shadow-toast\)/)
  for (const theme of ['light', 'dark']) {
    const vars = palette(tokens, theme === 'dark')
    for (const [severity, accent] of [
      ['error', '--ds-danger'], ['warning', '--ds-warning'],
    ]) {
      const bg = vars[`--ds-toast-${severity}-bg`]
      assert.match(bg, /^#[\da-f]{6}$/i)
      assert.ok(contrast(bg, vars['--ds-text-primary']) >= 4.5, `${theme} ${severity}: foreground AA`)
      assert.ok(contrast(bg, vars[accent]) >= 3, `${theme} ${severity}: icon/stripe contrast`)
    }
    assert.match(vars['--ds-shadow-toast'], /rgb\(/)
  }
})

test('Customer sort Vitest remains compatible with the frontend ES target', () => {
  const vitest = read('src/components/lists/__tests__/listComponents.spec.ts')
  assert.doesNotMatch(vitest, /\.at\(-1\)/)
  assert.equal((vitest.match(/emitted\('sort-change'\)\?\.slice\(-1\)\[0\]/g) || []).length, 5)
})
