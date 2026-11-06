const assert = require('node:assert/strict')
const { test } = require('node:test')
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8')
const view = (path) => read(`src/${path}.vue`)

test('Product, Organization and People detail tabs share the one selected/focus contract', () => {
  const shared = read('src/styles/detail-workspace.css')
  for (const file of ['components/products/ProductDetailModal', 'features/organization/pages/OrganizationsView',
    'features/people/pages/PeopleEmployeesView']) {
    const code = view(file)
    assert.match(code, /class="detail-tabs ds-detail-tabs\b/, file)
    const scoped = code.split('<style scoped>')[1] || ''
    assert.doesNotMatch(scoped, /\.detail-tabs(?:\s+button|\s*\{)/, file)
  }
  assert.match(view('features/people/pages/PeopleEmployeesView'), /detail-tabs__sub ds-detail-tabs ds-detail-tabs--nested/)
  assert.match(shared, /\.ds-detail-tabs > button\[aria-selected='true'\]/)
  assert.match(shared, /\.ds-detail-tabs > button:focus-visible/)
})

test('Product relation tables preserve min-width and use the shared table skin and scroll wrapper', () => {
  const product = view('features/catalog/pages/ProductEditorView')
  const scoped = product.split('<style scoped>')[1] || ''
  const tables = read('src/styles/tables.css')
  assert.equal((product.match(/class="data-table-wrap ds-table-scroll"/g)||[]).length, 3)
  assert.equal((product.match(/class="data-table ds-table ds-table--relationship"/g)||[]).length, 3)
  assert.match(tables, /\.ds-table--relationship \{ min-width: 760px/)
  assert.match(tables, /\.data-table\.ds-table--relationship \{ min-width: 640px/)
  assert.doesNotMatch(scoped, /\.relationship-section \.data-table|\.data-table (?:th|td|tbody)|^\.data-table \{/m)
  assert.match(tables, /:is\(\.ds-table, \.list-table, \.data-table, \.ds-data-grid\) :is\(th, td\)/)
})

test('Notifications Policy and Preferences import exactly one module-shared CSS without local button skin', () => {
  const shared = read('src/features/notifications/styles/notificationSettings.css')
  for (const [file,mod] of [['NotificationPoliciesTab','policies'], ['NotificationPreferencesTab','preferences']]) {
    const s = view('features/notifications/pages/' + file)
    assert.match(s, /import '\.\.\/styles\/notificationSettings\.css'/)
    assert.match(s, new RegExp(`m11-panel--${mod}`))
    assert.doesNotMatch(s, /<style scoped>/)
  }
  assert.match(shared, /--m11-row-columns/)
  assert.doesNotMatch(shared, /min-height:\s*34px|\.m11-row button\s*\{[^}]*padding:/)
})

test('Customer and Supplier opt into one page panel without copying dimensions', () => {
  for (const file of ['features/customer/pages/CustomersView','features/supplier/pages/SuppliersView']) {
    const code=view(file)
    assert.match(code, /class="content-panel [a-z-]+ ds-page-panel"/)
    assert.doesNotMatch(code.split('<style scoped>')[1]||'', /\.(?:customer|supplier)-list-panel\s*\{/)
  }
})

test('Error and Security helper skins are shared; views retain only margins', () => {
  const forms=read('src/styles/forms.css')
  assert.match(forms, /\.inline-error/)
  const utils=read('src/styles/utilities.css')
  assert.match(utils, /\.security-note\s*\{[^}]*color: var\(--ds-text-secondary\)/)
  for (const file of fs.readdirSync(path.join(root, 'src/features/security/pages')).filter(x=>x.endsWith('.vue'))) {
    const code=read('src/features/security/pages/'+file)
    const scoped=code.split('<style scoped>')[1]||''
    for (const m of scoped.matchAll(/^\.security-note\s*\{([^{}]*)\}/gm)) {
      assert.doesNotMatch(m[1], /\b(?:font-size|color|line-height|background|border)\s*:/,file)
    }
  }
  // All root .inline-error rules, including a combined .field-error rule, contain no skin properties.
  function walk(dir){for(const ent of fs.readdirSync(dir,{withFileTypes:true})){
    const p=path.join(dir,ent.name)
    if(ent.isDirectory()){walk(p);continue}
    if(!p.endsWith('.vue'))continue
    const scoped=fs.readFileSync(p,'utf8').split('<style scoped>')[1]||''
    for(const m of scoped.matchAll(/^\.inline-error\s*\{([^{}]*)\}/gm)) {
      assert.doesNotMatch(m[1], /\b(?:font-size|color|line-height|background|border)\s*:/,p)
    }
  }}
  walk(path.join(root,'src'))
})

test('Product Image, Security and Numbering buttons inherit shared actionable variants', () => {
  const image=view('components/products/ProductImageField')
  const security=view('features/security/pages/SecurityPoliciesView')
  const numbering=view('features/workflow/pages/AutoNumberingView')
  assert.match(image,/class="ds-button ds-button--danger-outline"/)
  assert.match(security,/class="ds-icon-control ds-icon-control--danger"/)
  assert.match(numbering,/class="ds-button ds-button--secondary ds-button--sm"/)
  for(const s of [image,security,numbering]) assert.doesNotMatch(s, /\.(?:image-remove-button|action-delete-button|token-button)\s*\{/)
})
