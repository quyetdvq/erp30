// DS5 HF15: one semantic mask for all action buttons, independent of the page.
// These guards intentionally do not require node_modules / browser runtime.
const {test}=require('node:test')
const assert=require('node:assert/strict')
const {readFileSync,readdirSync}=require('node:fs')
const path=require('node:path')
const root=path.resolve(__dirname,'../..')
const read=(rel)=>readFileSync(path.join(root,rel),'utf8')
const files=(dir)=>readdirSync(dir,{withFileTypes:true}).flatMap(e=>{
 const f=path.join(dir,e.name);return e.isDirectory()?files(f):f.endsWith('.vue')?[f]:[]
})
const sourceFiles=files(path.join(root,'src'))
const tags=sourceFiles.flatMap(file=>{
 const code=readFileSync(file,'utf8')
 const opener=/<(button|NButton)\b((?:[^"'>]|"[^"]*"|'[^']*')*)>/gs
 const entries=[];let m
 while((m=opener.exec(code))){
  const close=new RegExp('</'+m[1]+'\\s*>','g')
  close.lastIndex=opener.lastIndex
  const closing=close.exec(code)
  entries.push({file:path.relative(root,file),tag:m[1],attrs:m[2],body:closing?code.slice(opener.lastIndex,closing.index):''})
 }
 return entries
})
const attr=(b,key)=>new RegExp('(?:^|\\s)'+key+'="([^"]*)"').exec(b.attrs)?.[1]
const semanticAttr=(b)=>attr(b,'data-ds-icon') || attr(b,':data-ds-icon')
const css=read('src/styles/buttons.css')
const {inspectMaskRegistry} = require('./action-mask-registry.cjs')

test('all native action buttons and 40 modal Close headers have canonical data-ds-icon',()=>{
 assert.equal(tags.length,313,'existing button declarations must not change during visual-only migration')
 const labeled=tags.filter(semanticAttr)
 assert.ok(labeled.length>=250,`Only ${labeled.length} action buttons have semantic metadata`)
 const modal=tags.filter(b=>b.attrs.includes('modal-card-button-close'))
 assert.equal(modal.length,40)
 for(const b of modal){
  assert.equal(attr(b,'data-ds-icon'),'close',b.file)
  assert.match(b.attrs,/(?:^|\s)type="button"/,b.file)
  assert.ok(b.attrs.includes(':aria-label=') && b.attrs.includes('@click='),b.file)
 }
 for(const b of tags.filter(b=>b.tag==='button'&&(/(?:primary-button|secondary-button|class="icon-button|ds-icon-control)/.test(b.attrs)))){
  assert.ok(semanticAttr(b),`${b.file}: missing a shared icon on action button`)
 }
})

test('one currentColor mask registry owns Save, Close, Edit, Change state, Refresh, Add, Apply and their extensions',()=>{
 const ids=new Set([...css.matchAll(/^\[data-ds-icon="([a-z-]+)"\]\s*\{[^\n]+\}/gm)].map(m=>m[1]))
 for(const key of ['save','close','edit','changestate','activate','deactivate','refresh','add','apply','cancel','reset','delete','view','retry'])assert.ok(ids.has(key),`Missing icon ${key}`)
 for(const b of tags){const key=attr(b,'data-ds-icon');if(key)assert.ok(ids.has(key),`${b.file}: unknown icon ${key}`)}
 assert.match(css,/:where\(button:not\(\.n-button\)\)\[data-ds-icon\]:not\(\.ds-button--icon-slot\)::before\s*\{[^}]*background:\s*currentColor;/s)
 assert.match(css,/\.n-button\[data-ds-icon\] \.n-button__content::before\s*\{[^}]*mask:\s*var\(--ds-action-mask\)/s)
 assert.match(css,/\.modal-card-button-close\[data-ds-icon="close"\]::before\s*\{[^}]*width:\s*15px/s)
 assert.doesNotMatch(css,/\.modal-card-button-close::before\s*\{[^}]*mask:\s*url/s,'Close must not draw a second SVG')
 assert.match(read('src/components/ui/types.ts'),/'changestate'/)
 assert.match(read('src/components/ui/types.ts'),/'deactivate'/)
 assert.match(read('src/components/ui/types.ts'),/'activate'/)
})

test('all user-authored data-ds-icon SVG masks decode and have valid drawing roots',()=>{
 const registry=inspectMaskRegistry(css)
 assert.ok(registry.size>=31,`Expected existing semantic icon set, got ${registry.size}`)
 const required=['save','close','edit','changestate','activate','deactivate','refresh','add','apply','cancel','reset','delete','view','retry']
 for(const key of required) assert.ok(registry.has(key),`Missing ${key}`)
 for(const [name,mask] of registry){
  assert.ok(mask.svg.startsWith('<svg'),`${name}: invalid SVG root`)
  assert.ok(mask.uri.startsWith('data:image/svg+xml,'),`${name}: incorrect media type`)
 }
 // Corrupt icons must fail rather than disappear silently. Mixed SVG URI
 // encoding is allowed: the uploaded CSS uses both raw and encoded data.
 const corrupt=css.replace('data:image/svg+xml,', 'data:text/plain,')
 assert.throws(()=>inspectMaskRegistry(corrupt),/SVG mask|valid mask rules/)
})

test('custom toggle-off, toggle-on and shuffle SVGs stay distinct while actions remain reactive',()=>{
 const registry=inspectMaskRegistry(css)
 const activate=registry.get('activate')?.svg ?? ''
 const deactivate=registry.get('deactivate')?.svg ?? ''
 const change=registry.get('changestate')?.svg ?? ''
 assert.equal(new Set([activate,deactivate,change]).size,3,'Three actions must use distinct SVG masks')
 assert.match(activate,/\bbi-toggle-off\b/,'Activate now uses the user-selected toggle-off glyph')
 assert.match(deactivate,/\bbi-toggle-on\b/,'Deactivate now uses the user-selected toggle-on glyph')
 assert.match(change,/\bbi-shuffle\b/,'Change States uses the user-selected shuffle glyph')
 const rows=read('src/components/lists/TableRowActions.vue')
 assert.match(rows,/toggleIcon = computed\(\(\) => props\.active === false \? 'activate' : 'deactivate'\)/)
 assert.match(rows,/:aria-label="toggleLabel"[\s\S]*?@click="emitIfReady\('toggle'\)"\s+:data-ds-icon="toggleIcon"/)
 const users=read('src/features/security/pages/SecurityUsersView.vue')
 assert.match(users,/@click="void setState\(\)" :data-ds-icon="selected\.active \? 'deactivate' : 'activate'"/)
 for(const file of ['src/components/lists/BusinessWorkflowAction.vue','src/features/supplier/components/SupplierStateAction.vue']){
  assert.match(read(file),/data-ds-icon="changestate"/,file)
 }
})

test('retired HF20 mask-repair command is read-only and never replaces custom icons',()=>{
 const {verifyActionMasks,normalizeActivateMask}=require('./hf20_restore_activate_mask.cjs')
 for(const check of [verifyActionMasks,normalizeActivateMask]){
  const result=check(css)
  assert.equal(result.status,'valid-custom-icons')
  assert.equal(result.css,css,'The tool must not alter the user-authored CSS')
 }
 const variant=css.replace('bi-toggle-off', 'bi-toggle-off-custom')
 assert.notEqual(variant,css)
 assert.equal(verifyActionMasks(variant).css,variant,'Never silently revert a valid custom mask')
 const registry=inspectMaskRegistry(css)
 const corrupt=css.replace(registry.get('deactivate').uri,registry.get('activate').uri)
 assert.notEqual(corrupt,css)
 assert.throws(()=>verifyActionMasks(corrupt),/different SVG icons/)
})

test('binary toggle label, icon and backend target use the same current-status predicate',()=>{
 const row=read('src/components/lists/TableRowActions.vue')
 assert.match(row,/const toggleLabel = computed\(\(\) =>\s*props\.active === false\s*\? \(props\.activateLabel \?\? t\('common.activate'\)\)\s*: \(props\.deactivateLabel \?\? t\('common.deactivate'\)\)/)
 assert.match(row,/:title="toggleLabel"/)
 assert.match(row,/:aria-label="toggleLabel"/)
 assert.match(row,/props.active !== undefined/)
 const security=read('src/features/security/pages/SecurityUsersView.vue')
 assert.match(security,/:data-ds-icon="selected.active \? 'deactivate' : 'activate'"/)
 assert.match(security,/\{\{ selected.active \? t\('security.inactivate'\) : t\('security.activate'\) \}\}/)
 assert.match(security,/changeSecurityUserState\(String\(target.id\), target.active \? 'INACTIVE' : 'ACTIVE'\)/)
})

test('binary status buttons use user-selected green/gray OUTLINE colors and inherit their button background',()=>{
 const rows=read('src/components/lists/TableRowActions.vue')
 const users=read('src/features/security/pages/SecurityUsersView.vue')
 const tables=read('src/styles/tables.css')
 assert.match(rows,/class="table-row-action ds-icon-control table-row-action--state ds-state-toggle"/)
 assert.match(rows,/:data-ds-state="props.active \? 'active' : 'inactive'"/)
 assert.match(rows,/:data-ds-icon="toggleIcon"/)
 assert.match(users,/class="secondary-button ds-state-toggle"/)
 assert.match(users,/:data-ds-state="selected.active \? 'active' : 'inactive'"/)
 assert.match(users,/:data-ds-icon="selected.active \? 'deactivate' : 'activate'"/)
 assert.doesNotMatch(tables,/\.table-row-action--state(?:\:|\s*\{)[^}]*var\(--ds-warning\)/s)
 for(const [state,token] of [['active','--ds-success'],['inactive','--ds-border']]){
  const entry=new RegExp('\\.ds-state-toggle\\[data-ds-state="'+state+'"\\] \\{([^}]+)\\}').exec(css)
  assert.ok(entry,`Missing ${state} button rule`)
  assert.match(entry[1],new RegExp('border-color:\\s*var\\('+token+'\\)'))
  assert.match(entry[1],new RegExp('color:\\s*var\\('+token+'\\)'))
  assert.doesNotMatch(entry[1],/\bbackground(?:-color)?\s*:/,'Do not reintroduce filled backgrounds on '+state)
  for(const pseudo of [':hover:not(:disabled)',':active:not(:disabled)']){
   assert.ok(css.includes('.ds-state-toggle[data-ds-state="'+state+'"]'+pseudo),`${state} ${pseudo} missing`)
  }
 }
 assert.match(css,/\.ds-state-toggle\[data-ds-state="inactive"\]:hover:not\(:disabled\)\s*\{[^}]*color:\s*var\(--ds-success\)/s)
 for(const token of ['--ds-success','--ds-border','--ds-bg-surface']){
  const palettes=read('src/styles/tokens.css').split(":root[data-ds-theme='dark']")
  assert.ok(palettes.length===2 && palettes.every(part=>part.includes(token+':')),`${token} must resolve in both palettes`)
 }
 for(const component of ['src/components/lists/BusinessWorkflowAction.vue','src/features/supplier/components/SupplierStateAction.vue']){
  assert.doesNotMatch(read(component),/ds-state-toggle|data-ds-state/,component+' is multi-state')
 }
})

test('custom green/gray glyphs meet 3:1 WCAG non-text contrast on their inherited surface in both themes',()=>{
 const source=read('src/styles/tokens.css')
 const parts=source.split(":root[data-ds-theme='dark']")
 assert.equal(parts.length,2)
 const token=(palette,key)=>{
  const match=new RegExp('(?:^|\\s)'+key+':\\s*(#[0-9a-f]{6});','i').exec(palette)
  assert.ok(match,`Missing ${key}`)
  return match[1]
 }
 const luminance=hex=>{
  const values=[1,3,5].map(i=>parseInt(hex.slice(i,i+2),16)/255)
    .map(value=>value<=.04045?value/12.92:((value+.055)/1.055)**2.4)
  return values[0]*.2126+values[1]*.7152+values[2]*.0722
 }
 const contrast=(a,b)=>{
  const values=[luminance(a),luminance(b)].sort((a,b)=>a-b)
  return (values[1]+.05)/(values[0]+.05)
 }
 for(const [theme,palette] of [['light',parts[0]],['dark',parts[1]]]){
  const surface=token(palette,'--ds-bg-surface')
  for(const [state,colorToken] of [['active','--ds-success'],['inactive','--ds-border']]){
   const value=contrast(token(palette,colorToken),surface)
   assert.ok(value>=3,`${theme} ${state} non-text icon contrast ${value.toFixed(2)}:1 < 3:1`)
  }
 }
})

test('Modal Close 15px icon wins the shared 18px glyph in CSS cascade order',()=>{
 const shared=':where(button:not(.n-button))[data-ds-icon]:not(.ds-button--icon-slot)::before'
 const modal='.modal-card-button-close[data-ds-icon="close"]::before'
 const sharedPos=css.indexOf(shared)
 const modalPos=css.indexOf(modal)
 assert.ok(sharedPos>=0 && modalPos>sharedPos,'Modal Close override must follow the equally specific general action rule')
 const sharedDecl=css.slice(sharedPos,css.indexOf('}',sharedPos))
 const modalDecl=css.slice(modalPos,css.indexOf('}',modalPos))
 assert.match(sharedDecl,/width:\s*var\(--ds-icon-size\)/)
 for(const property of ['width','height','flex-basis'])assert.match(modalDecl,new RegExp(property+':\\s*15px'))
})

test('semantic action buttons do not draw both a CSS mask and a nested NIcon or SVG',()=>{
 for(const b of tags.filter(semanticAttr)){
  assert.doesNotMatch(b.body,/<NIcon\b|<svg\b|<template\s+#icon/,`${b.file}: two decorative icons`)
 }
 for(const file of ['src/components/lists/TableRowActions.vue','src/features/notifications/pages/NotificationsInboxView.vue']){
  const code=read(file)
  assert.doesNotMatch(code,/<NIcon\b/,`${file}: shared masks replace custom action NIcons`)
 }
})

test('row actions and state transitions preserve PBAC, state-specific aria labels and handlers',()=>{
 const actions=read('src/components/lists/TableRowActions.vue')
 for(const key of ['view','edit','delete'])assert.match(actions,new RegExp('data-ds-icon="'+key+'"'))
 assert.match(actions,/:data-ds-icon="toggleIcon"/)
 assert.doesNotMatch(actions,/data-ds-icon="changestate"[^>]*emitIfReady\('toggle'\)/)
 for(const event of ["emitIfReady('view')","emitIfReady('edit')","emitIfReady('toggle')","emitIfReady('delete')"]){
  assert.ok(actions.includes(event),`missing ${event}`)
 }
 assert.match(actions,/canMutation\('toggle'\)/)
 assert.match(actions,/:aria-label="toggleLabel"/)
 assert.match(read('src/features/security/pages/SecurityUsersView.vue'),/:data-ds-icon="selected\.active \? 'deactivate' : 'activate'"/)
 for(const file of ['src/components/lists/BusinessWorkflowAction.vue','src/features/supplier/components/SupplierStateAction.vue']){
  const content=read(file)
  assert.match(content,/@click="void (?:open|openDialog)\(\)"\s+data-ds-icon="changestate"/)
  assert.match(content,/data-ds-icon="changestate"[^>]*@click="void apply\(\)"/)
  assert.match(content,/variant !== 'icon'/)
  assert.match(content,/data-ds-icon="close"/)
 }
})

test('native and Naive action templates preserve submit behavior and no icon slot duplication',()=>{
 const queries=read('src/components/lists/ListQueryControls.vue')
 assert.match(queries,/type="submit"[^>]*data-ds-icon="apply"/)
 assert.match(queries,/@submit\.prevent="apply"/)
 const auto=read('src/features/workflow/pages/AutoNumberingView.vue')
 assert.match(auto,/type="submit"[^>]*data-ds-icon="refresh"/)
 assert.match(auto,/data-ds-icon="save"/)
 const inbox=read('src/features/notifications/pages/NotificationsInboxView.vue')
 assert.match(inbox,/<NButton\b[^>]*data-ds-icon="apply"/)
 assert.doesNotMatch(inbox,/<template\s+#icon>/)
 const confirm=read('src/components/ui/AppConfirmDialog.vue')
 assert.match(confirm,/<AppButton icon="cancel"/)
 assert.match(confirm,/:icon="danger \? 'delete' : 'apply'"/)
 assert.match(read('src/components/ui/AppButton.vue'),/!loading && !\$slots\.icon \? icon : undefined/)
})

test('changing action icons must not alter business guards, unsaved protection or supplier CAS',()=>{
 assert.match(read('src/features/supplier/components/SupplierStateAction.vue'),/row_version/)
 assert.match(read('src/features/customer/pages/CustomerDetailView.vue'),/confirmDiscard/)
 assert.match(read('src/components/lists/rowActionPolicy.ts'),/canMutateRow/)
 assert.match(read('src/features/catalog/pages/PriceListsView.vue'),/unsaved\.confirmDiscard\(\)/)
})
