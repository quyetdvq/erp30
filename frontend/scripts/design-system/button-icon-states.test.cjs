// DS4 source contracts: no DOM/dependencies required. Browser + Vitest
// acceptance below validate real computed styles and Vue event semantics.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const { readdirSync, readFileSync } = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const source = (relative) => readFileSync(path.join(root, relative), 'utf8')
const buttons = source('src/styles/buttons.css')
const tokens = source('src/styles/tokens.css')
const tables = source('src/styles/tables.css')
const nativeTag = /<button\b((?:[^"'>]|"[^"]*"|'[^']*')*)>/gs
const allTags = /<(button|NButton)\b((?:[^"'>]|"[^"]*"|'[^']*')*)>/gs
const scan = (dir) => readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
  const filename = path.join(dir, entry.name)
  return entry.isDirectory() ? scan(filename) : filename.endsWith('.vue') ? [filename] : []
})
const componentFiles = scan(path.join(root, 'src'))
const compiled = componentFiles.map((file) => ({file, s:readFileSync(file,'utf8')}))
const allButtons = compiled.flatMap(({file,s}) => [...s.matchAll(allTags)].map((m) => ({file, tag:m[1], attrs:m[2], body:s.slice(m.index+m[0].length).split(`</${m[1]}>`,1)[0]})))
function requiredTokens(theme) {
  const [light, dark] = tokens.split(":root[data-ds-theme='dark']")
  const extract = (css) => Object.fromEntries([...css.matchAll(/(--ds-[\w-]+)\s*:\s*([^;]+);/g)].map(m => [m[1], m[2].trim()]))
  return theme === 'light' ? extract(light) : {...extract(light),...extract(dark)}
}
function luminosity(color) {
  const rgb=[1,3,5].map(i=>parseInt(color.slice(i,i+2),16)/255)
  return rgb.map(n=>n<=.04045?n/12.92:((n+.055)/1.055)**2.4).reduce((v,n,i)=>v+n*[.2126,.7152,.0722][i],0)
}
const contrast=(a,b)=>{const x=luminosity(a),y=luminosity(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05)}

test('DS4 inventory accounts for shared native modal Close button; handlers remain intact',()=>{
  assert.equal(allButtons.length,313)
  assert.equal(allButtons.filter(x=>x.tag==='button').length,288)
  assert.equal(allButtons.filter(x=>x.tag==='NButton').length,25)
  assert.equal(new Set(allButtons.map(x=>x.file)).size,66)
})

test('Every legacy primary/secondary text action has a decorative icon or a native NIcon child',()=>{
  let total=0, metadata=0
  for(const b of allButtons.filter(b=>b.tag==='button')) {
    const cls=b.attrs.match(/(?:^|\s)class\s*=\s*(["'])(.*?)\1/s)?.[2]??''
    if(!cls.split(/\s+/).some(x=>x==='primary-button'||x==='secondary-button')) continue
    total++
    const hasSemantic=b.attrs.includes('data-ds-icon=')
    const hasInlineIcon=/<(?:NIcon|svg|[A-Za-z]+Outline)\b/.test(b.body)
    assert.ok(hasSemantic||hasInlineIcon,`${path.relative(root,b.file)}: missing icon for native action`)
    if(hasSemantic)metadata++
  }
  assert.ok(total>=160,`Expected the full legacy button coverage, got ${total}`)
  assert.ok(metadata>=100,`Expected icon coverage on text actions, got ${metadata}`)
})

test('One CSS icon registry provides valid masks for every declared action',()=>{
  const ids=new Set([...buttons.matchAll(/\[data-ds-icon="([a-z-]+)"\]\s*\{/g)].map(m=>m[1]))
  assert.ok(ids.size>=23)
  for(const b of allButtons) {
    const icon=b.attrs.match(/(?:^|\s)data-ds-icon="([a-z-]+)"/)?.[1]
    if(icon)assert.ok(ids.has(icon),`${path.relative(root,b.file)}: undefined ${icon}`)
  }
  assert.match(buttons, /mask: var\(--ds-action-mask\)/)
  assert.match(buttons, /background: currentColor/)
  assert.match(buttons, /\.ds-button--icon-slot/)
  assert.doesNotMatch(compiled.map(c=>c.s).join('\n'), /＋|↻/, 'Unicode glyphs should not replace semantic shared icons')
})

test('AppButton state machine retains submit type, loading guard, and icon slot precedence',()=>{
  const button=source('src/components/ui/AppButton.vue')
  const types=source('src/components/ui/types.ts')
  assert.match(button, /:type="type"/)
  assert.match(button, /:disabled="disabled \|\| loading"/)
  assert.match(button, /:aria-busy="loading \? 'true' : undefined"/)
  assert.match(button, /!loading && !\$slots\.icon \? icon : undefined/)
  assert.match(button, /'ds-button--icon-slot': Boolean\(\$slots\.icon\)/)
  assert.match(button, /v-if="loading"/)
  assert.match(types,/AppActionIconName/)
  assert.match(types,/danger-outline/)
})

test('Theme-aware button hover/pressed/focus/disabled and danger states are centralized',()=>{
  for(const selector of ['ds-button--primary','ds-button--secondary','ds-button--danger','ds-button--danger-outline','ds-icon-control']) assert.match(buttons,new RegExp('\\.'+selector+'\\b'))
  for(const state of [':hover:not(:disabled)', ':active:not(:disabled)', ':focus-visible', ':disabled']) assert.ok(buttons.includes(state),state)
  for(const theme of ['light','dark']) {
    const t=requiredTokens(theme)
    for(const pair of [['--ds-primary-contrast','--ds-primary'],['--ds-primary-contrast','--ds-primary-hover'],['--ds-primary-contrast','--ds-primary-pressed'],['--ds-danger-contrast','--ds-danger'],['--ds-danger-contrast','--ds-danger-hover'],['--ds-danger-contrast','--ds-danger-pressed'],['--ds-danger','--ds-bg-surface']]) {
      assert.ok(contrast(t[pair[0]],t[pair[1]])>=4.5,`${theme} ${pair.join('/')}`)
    }
    assert.equal(t['--ds-icon-gap'],'8px')
    assert.equal(t['--ds-icon-size'],'18px')
  }
})

test('Icon-only controls have an accessible name and share one surface',()=>{
  const native=allButtons.filter(b=>b.tag==='button'&&b.attrs.includes('ds-icon-control'))
  assert.ok(native.length>=6)
  for(const b of native)assert.match(b.attrs,/:aria-label=/,path.relative(root,b.file))
  const pager=source('src/components/lists/ListPagination.vue')
  for(const name of ['first','previous','next','last']) {
    assert.match(pager,new RegExp(`data-ds-icon="${name}"`))
    assert.match(pager,new RegExp(`t\\('list\\.${name}'\\)`))
  }
  assert.match(tables,/\.table-row-action \{ font-size/)
  assert.match(source('src/components/lists/TableRowActions.vue'), /ds-icon-control--danger/)
})

test('Search, export and pagination use the same CSS action contract',()=>{
  const queries=source('src/components/lists/ListQueryControls.vue')
  const exporting=source('src/components/lists/ListExportButton.vue')
  assert.match(queries,/class="ds-button ds-button--primary" data-ds-icon="apply"/)
  assert.match(queries,/class="ds-button ds-button--secondary" data-ds-icon="reset"/)
  assert.match(exporting,/list-export-button ds-button ds-button--secondary/)
  assert.doesNotMatch(exporting,/<style\b/)
  assert.doesNotMatch(tables,/\.list-pagination__actions button \{/)
  assert.match(tables,/\.list-query-controls__actions button \{ flex: 1 1 auto;/) // mobile layout only
  // DS3 page-size policy must remain unchanged.
  assert.match(queries,/showPageSize\?: boolean/)
  assert.match(source('src/features/catalog/pages/PriceListsView.vue'),/pageSizeOptionsFor\(100\)/)
})

test('Notification and Settings action buttons use semantic roles and light/dark tokens',()=>{
  for(const [rel,icon] of [
    ['src/features/notifications/pages/NotificationOperationsView.vue','refresh'],
    ['src/features/notifications/pages/NotificationPoliciesTab.vue','save'],
    ['src/features/notifications/pages/NotificationPreferencesTab.vue','save'],
    ['src/features/notifications/pages/PushDevicesPanel.vue','subscribe'],
    ['src/features/settings/pages/ConfigurationSettingsView.vue','save'],
  ]) assert.ok(source(rel).includes(`data-ds-icon="${icon}"`),rel)
  assert.doesNotMatch(source('src/features/auth/pages/LoginView.vue'),/color="#[a-f0-9]+"/i)
  for(const rel of ['src/features/notifications/pages/MyWorkflowActionsView.vue','src/features/notifications/components/NotificationPopoverList.vue']){
    const body=source(rel).split('<style scoped>')[1]??''
    assert.doesNotMatch(body,/#[a-f0-9]{3,8}\b/i,`${rel}: hardcoded light palette`)
  }
})

test('PBAC, unsaved guards and Supplier CAS remain owned by their respective features',()=>{
  assert.match(source('src/components/lists/TableRowActions.vue'),/canMutation\('delete'\)/)
  assert.match(source('src/features/customer/pages/CustomerDetailView.vue'),/profileDraft\.confirmDiscard\(\)/)
  assert.match(source('src/features/supplier/components/SupplierStateAction.vue'),/row_version/)
  assert.match(source('src/features/catalog/pages/PriceListsView.vue'),/unsaved\.confirmDiscard\(\)/)
  assert.match(source('src/components/lists/ListQueryControls.vue'),/@submit\.prevent="apply"/)
})

// DS4 HF1: The UOM conversion form has the same Close/Cancel presentation as
// other dialogs. Cancel is an undo/discard action, distinct from Close's X.
test('Cancel uses the shared return icon while Close keeps the distinct X', () => {
  const cancel = buttons.match(/^\[data-ds-icon="cancel"\] \{([^\n]+)\}/m)?.[1]
  const close = buttons.match(/^\[data-ds-icon="close"\] \{([^\n]+)\}/m)?.[1]
  assert.ok(cancel && close, 'Both semantic icons must come from the shared registry')
  assert.ok(cancel.includes('%3Cpath'), 'Cancel should draw a return/undo path')
  assert.ok(!cancel.includes('%3Ccircle'), 'Cancel must not show the old circled X')
  assert.notEqual(cancel, close, 'Cancel and Close must have different shapes')
  assert.match(buttons, /\.modal-card-button-close\[data-ds-icon="close"\]::before\s*\{[^}]*width:\s*15px/s)
  assert.match(buttons, /:where\(button:not\(\.n-button\)\)\[data-ds-icon\]:not\(\.ds-button--icon-slot\)::before\s*\{[^}]*mask:/s)
  assert.match(buttons, /\.modal-card-button-close:focus-visible/)
})

test('Both UOM conversion entry points share one dialog header, Close X and Cancel footer', () => {
  const paths = [
    'src/components/products/ProductDetailModal.vue',
    'src/features/catalog/pages/ProductEditorView.vue',
  ]
  for (const rel of paths) {
    const vue = source(rel)
    const from = vue.indexOf('<AppDialogHost :open="conversionEditorOpen"')
    assert.ok(from >= 0, `${rel}: missing UOM conversion modal`)
    const to = vue.indexOf('</AppDialogHost>', from)
    assert.ok(to > from, `${rel}: missing modal boundary`)
    const modal = vue.slice(from, to)
    assert.match(modal, /<header class="modal-card__header">/)
    assert.match(modal, /<footer class="modal-card__footer(?: editor-actions)?">/)
    assert.match(modal, /<button\s+type="button"\s+class="modal-card-button-close"\s+:aria-label="t\('common\.close'\)"\s+@click="conversionEditorOpen = false"\s+data-ds-icon="close"\s*><\/button>/)
    assert.match(modal, /data-ds-icon="cancel"[^>]*class="secondary-button"[^>]*@click="conversionEditorOpen = false"/)
    assert.match(modal, /data-ds-icon="save"[^>]*class="primary-button"[^>]*@click="saveConversionDraft"/)
    assert.doesNotMatch(modal, /class="icon-button"|>\s*×\s*<\/button>/)
  }
  const detail = source(paths[0])
  const childStart = detail.indexOf('<AppDialogHost :open="childFormOpen"')
  const childEnd = detail.indexOf('</AppDialogHost>', childStart)
  const child = detail.slice(childStart, childEnd)
  assert.match(child, /<header class="modal-card__header">/)
  assert.match(child, /<footer class="modal-card__footer">/)
  assert.match(child, /class="modal-card-button-close"\s+:aria-label="t\('common\.close'\)"\s+@click="childFormOpen = false"/)
  assert.match(child, /data-ds-icon="cancel"[^>]*@click="childFormOpen = false"/)
  assert.match(child, /data-ds-icon="save"[^>]*@click="createChild"/)
  assert.doesNotMatch(detail.split('<style scoped>')[1] ?? '', /\.child-modal (?:header|footer|h3)\b/)
})
