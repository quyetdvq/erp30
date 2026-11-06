const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8')
const view = (name) => read(`src/${name}.vue`)
function cssVars(source) {
  return Object.fromEntries([...source.matchAll(/(--ds-[a-z0-9-]+)\s*:\s*([^;]+);/g)]
    .map((m) => [m[1], m[2].trim()]))
}
const [lightSource, darkSource] = read('src/styles/tokens.css').split(":root[data-ds-theme='dark']")
const light = cssVars(lightSource)
const dark = { ...light, ...cssVars(darkSource) }
function resolveToken(tokens, name, seen = []) {
  assert.ok(Object.hasOwn(tokens, name), `Missing token ${name}`)
  assert.ok(!seen.includes(name), `Circular token ${seen.join(' -> ')} -> ${name}`)
  return tokens[name].replace(/var\((--ds-[a-z0-9-]+)\)/g,
    (_match, token) => resolveToken(tokens, token, [...seen, name])).trim()
}
function extractObject(source, key) {
  const start = source.indexOf(`${key}: {`)
  assert.ok(start !== -1, `Missing Naive theme ${key}`)
  const open = source.indexOf('{', start)
  let level = 0
  for (let i = open; i < source.length; i++) {
    if (source[i] === '{') level++
    else if (source[i] === '}') {
      if (--level === 0) return source.slice(open + 1, i)
    }
  }
  throw new Error(`Unclosed theme object ${key}`)
}
function value(section, key) {
  const match = section.match(new RegExp(`\\b${key}:\\s*'([^']+)'`))
  assert.ok(match, `Missing Naive property ${key}`)
  return match[1]
}

for (const [theme, palette] of [['light', light], ['dark', dark]]) {
  test(`${theme}: Naive common/input/select/checkbox/radio/button geometry and colors match tokens`, () => {
    const source = read('src/styles/naiveTheme.ts')
    const themeSource = theme === 'light'
      ? source.split('export const naiveLightOverrides: GlobalThemeOverrides = {')[1].split('export const naiveDarkOverrides')[0]
      : source.split('export const naiveDarkOverrides: GlobalThemeOverrides = {')[1]
    assert.ok(themeSource)
    const blocks = Object.fromEntries(['common','Input','InternalSelection','Checkbox','Radio','Button']
      .map((key) => [key, extractObject(themeSource, key)]))
    const expected = [
      ['common','primaryColor','--ds-primary'], ['common','primaryColorHover','--ds-primary-hover'],
      ['common','primaryColorPressed','--ds-primary-pressed'], ['common','primaryColorSuppl','--ds-primary'],
      ['common','infoColor','--ds-info'], ['common','successColor','--ds-success'],
      ['common','warningColor','--ds-warning'], ['common','errorColor','--ds-danger'],
      ['common','bodyColor','--ds-bg-canvas'], ['common','cardColor','--ds-bg-surface'],
      ['common','modalColor','--ds-bg-elevated'], ['common','popoverColor','--ds-bg-elevated'],
      ['common','textColor1','--ds-text-primary'], ['common','textColor2','--ds-text-secondary'],
      ['common','textColor3','--ds-text-muted'], ['common','borderColor','--ds-border'],
      ['common','borderRadius','--ds-radius-md'], ['common','heightMedium','--ds-control-height'],
      ['common','fontSize','--ds-control-font-size'], ['common','placeholderColor','--ds-control-placeholder'],
      ['common','inputColor','--ds-control-bg'], ['common','inputColorDisabled','--ds-control-disabled-bg'],
      ['Input','heightMedium','--ds-control-height'], ['Input','fontSizeMedium','--ds-control-font-size'],
      ['Input','color','--ds-control-bg'], ['Input','colorDisabled','--ds-control-disabled-bg'],
      ['Input','textColor','--ds-control-text'], ['Input','textColorDisabled','--ds-control-disabled-text'],
      ['Input','placeholderColor','--ds-control-placeholder'], ['Input','borderRadius','--ds-control-radius'],
      ['InternalSelection','heightMedium','--ds-control-height'],
      ['InternalSelection','fontSizeMedium','--ds-control-font-size'],
      ['InternalSelection','textColor','--ds-control-text'],
      ['InternalSelection','placeholderColor','--ds-control-placeholder'],
      ['InternalSelection','borderRadius','--ds-control-radius'],
      ['Checkbox','sizeMedium','--ds-control-check-size'],
      ['Checkbox','fontSizeMedium','--ds-control-font-size'],
      ['Radio','radioSizeMedium','--ds-control-check-size'],
      ['Radio','fontSizeMedium','--ds-control-font-size'],
      ['Button','heightSmall','--ds-button-height-sm'], ['Button','heightMedium','--ds-button-height-md'],
      ['Button','heightLarge','--ds-button-height-lg'],
    ]
    for (const [block, key, token] of expected) {
      assert.equal(value(blocks[block], key), resolveToken(palette, token), `${theme} ${block}.${key} != ${token}`)
    }
    const padding = `${resolveToken(palette,'--ds-control-padding-block')} ${resolveToken(palette,'--ds-control-padding-inline')}`
    assert.equal(value(blocks.Input, 'paddingMedium'), padding)
    for (const block of ['Input','InternalSelection']) {
      for (const [property, token] of [['border','--ds-control-border'],['borderHover','--ds-control-border-hover'],['borderFocus','--ds-control-focus']]) {
        assert.equal(value(blocks[block],property), '1px solid '+resolveToken(palette,token), `${theme} ${block}.${property}`)
      }
    }
    // DatePicker/InputNumber use Naive's Input/Selection peer chrome. A separate
    // per-control override would create an unverified second owner here.
    assert.doesNotMatch(themeSource, /^\s*(?:DatePicker|InputNumber):\s*\{/m)
  })
}

test('Form, workflow and page styles have separate owners with lazy module CSS', () => {
  const forms = read('src/styles/forms.css')
  const page = read('src/styles/page-layout.css')
  const workflow = read('src/styles/workflow-notify.css')
  assert.doesNotMatch(forms, /\.workflow-notify-(?:section|editor|heading)\s*\{|\.ds-page-panel\s*\{/)
  assert.match(page, /\.ds-page-panel\s*\{/)
  assert.match(workflow, /\.workflow-notify-editor\s*\{/)
  for (const name of ['components/lists/BusinessWorkflowAction','features/supplier/components/SupplierStateAction']) {
    assert.match(view(name), /import '@\/styles\/workflow-notify\.css'/)
  }
  assert.doesNotMatch(read('src/styles/global.css'), /workflow-notify\.css|notificationSettings\.css/)
})

test('Native modal grids and Product editor labels inherit the shared Form Control contract', () => {
  const forms = read('src/styles/forms.css')
  assert.match(forms, /@container ds-dialog \(max-width: 560px\)/)
  for (const name of ['features/organization/pages/OrganizationsView','features/security/pages/SecurityPoliciesView']) {
    const component = view(name)
    assert.match(component, /class="form-grid"/)
    assert.doesNotMatch(component.split('<style scoped>')[1] || '', /^\.form-grid\s*\{|^\s*\.form-grid,/m)
  }
  const product = view('features/catalog/pages/ProductEditorView')
  assert.match(product, /class="ds-field product-editor-field"/)
  assert.doesNotMatch(product, /\.product-editor-field\s*\{[^}]*\b(?:font-size|font-weight|color|gap)\s*:/)
})

// A targeted ownership lint, deliberately without CSS parsing dependencies.
// Existing !important legacy rules are explicitly documented; newly introduced
// usage outside these files cannot silently expand the CSS cascade debt.
const oldImportantExceptions = new Map([
  ['components/products/ProductDetailModal.vue',2],
  ['components/products/ProductRelationsDialog.vue',1],
  ['features/customer/pages/CustomerDetailView.vue',2],
  ['features/people/pages/PeopleEmployeesView.vue',1],
])
function walk(dir) {
  const files=[]
  for (const ent of fs.readdirSync(dir,{withFileTypes:true})) {
    const entry=path.join(dir,ent.name)
    if(ent.isDirectory()) files.push(...walk(entry))
    else if(entry.endsWith('.vue')) files.push(entry)
  }
  return files
}

test('CSS ownership policy: no new feature-level control skins or specificity escalation', () => {
  const all = walk(path.join(root,'src'))
  for (const file of all) {
    const relative = path.relative(path.join(root,'src'),file).split(path.sep).join('/')
    const vue = fs.readFileSync(file,'utf8')
    const css = [...vue.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/g)].map(x=>x[1]).join('\n')
    if(!css)continue
    assert.doesNotMatch(css, /^\s*\.ds-dialog-host\b[^{}]*\{/m,relative)
    assert.doesNotMatch(css, /^\s*\.ds-detail-tabs\s*>\s*button[^{}]*\{/m,relative)
    assert.doesNotMatch(css, /^\s*\.data-table\s+(?:th|td|tbody)[^{}]*\{/m,relative)
    assert.doesNotMatch(css, /^\s*\.list-table\s+(?:th|td|tbody)[^{}]*\{/m,relative)
    assert.doesNotMatch(css, /\.n-base-selection-input[^{}]*\{[^{}]*(?:border|padding|background)\s*:/m,relative)
    const occurrences=(css.match(/!important\b/g)||[]).length
    const allowed = oldImportantExceptions.get(relative) || 0
    assert.ok(occurrences<=allowed,`${relative}: unexpected !important count ${occurrences} (allowed ${allowed})`)
  }
  // No additional doubled specificity in central modal chrome.
  const modal=read('src/styles/modal.css')
  assert.equal((modal.match(/\.ds-dialog-host\.ds-dialog-host/g)||[]).length,2, 'Only two grandfathered modal surface rules may repeat class specificity')
  // Mobile panels never become scroll owners again.
  assert.doesNotMatch(read('src/styles/page-layout.css'),/\.content-panel\s*\{[^}]*overflow-x:\s*auto/)
})
