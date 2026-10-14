#!/usr/bin/env node
/** One-time as-built FE refactor gate. Changes to business behavior require a new, reviewed baseline. */
const fs = require('node:fs')
const path = require('node:path')
const crypto = require('node:crypto')
const ts = require('typescript')
const { toRepoRelative } = require('./paths.cjs')
const { compareCriticalHashes } = require('./hash-policy.cjs')
const root = path.resolve(__dirname, '../..')
const repo = path.resolve(root, '..')
const manifestFile = path.join(__dirname, 'baseline-2026-10-14.json')
const writeMode = process.argv.includes('--capture')
const sha = (data) => crypto.createHash('sha256').update(data).digest('hex')
const fileSha = (relative) => sha(fs.readFileSync(path.join(repo, relative)))

function ast(relative) {
  const filename = path.join(repo, relative)
  const source = ts.createSourceFile(filename, fs.readFileSync(filename, 'utf8'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
  if (source.parseDiagnostics.length) throw new Error(`Parse ${relative}: ${source.parseDiagnostics.map(d => ts.flattenDiagnosticMessageText(d.messageText, '\n')).join('; ')}`)
  return source
}
function unwrap(node) {
  while (ts.isAsExpression(node) || ts.isSatisfiesExpression(node) || ts.isParenthesizedExpression(node) || ts.isNonNullExpression(node)) node = node.expression
  return node
}
function declared(file, name) {
  const src = ast(file)
  for (const statement of src.statements) {
    if (!ts.isVariableStatement(statement)) continue
    for (const d of statement.declarationList.declarations) if (d.name.getText(src) === name) return unwrap(d.initializer)
  }
  throw new Error(`Missing ${name} declaration in ${file}`)
}
function propName(key) {
  return ts.isIdentifier(key) || ts.isStringLiteral(key) || ts.isNumericLiteral(key) ? key.text : key.getText()
}
function read(node) {
  node = unwrap(node)
  if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) return node.text
  if (ts.isNumericLiteral(node)) return Number(node.text)
  if (node.kind === ts.SyntaxKind.TrueKeyword) return true
  if (node.kind === ts.SyntaxKind.FalseKeyword) return false
  if (node.kind === ts.SyntaxKind.NullKeyword) return null
  if (ts.isIdentifier(node)) return `{identifier:${node.text}}`
  if (ts.isPropertyAccessExpression(node)) return `{member:${node.getText()}}`
  if (ts.isArrayLiteralExpression(node)) return node.elements.map(read)
  if (ts.isObjectLiteralExpression(node)) {
    const obj = {}
    for (const p of node.properties) {
      if (ts.isPropertyAssignment(p)) obj[propName(p.name)] = read(p.initializer)
      else if (ts.isShorthandPropertyAssignment(p)) obj[p.name.text] = `{identifier:${p.name.text}}`
      else throw new Error(`Unsupported object property: ${p.getText().slice(0, 70)}`)
    }
    return obj
  }
  if (ts.isNewExpression(node) && ts.isIdentifier(node.expression) && (node.expression.text === 'Set' || node.expression.text === 'Map')) return read(node.arguments[0])
  if (ts.isArrowFunction(node)) {
    const match = node.getText().match(/import\(['"]([^'"]+)['"]\)/)
    if (match) return `{lazy-view:${path.basename(match[1])}}`
  }
  if (ts.isBinaryExpression(node) && node.operatorToken.kind === ts.SyntaxKind.PlusToken) return String(read(node.left)) + String(read(node.right))
  throw new Error(`Unsupported expression: ${ts.SyntaxKind[node.kind]} ${node.getText().slice(0, 65)}`)
}
function oldRoutes() {
  const src = ast('frontend/src/router/index.ts')
  let value
  function walk(node) {
    if (ts.isCallExpression(node) && ts.isIdentifier(node.expression) && node.expression.text === 'createRouter') {
      const config = node.arguments[0]
      if (ts.isObjectLiteralExpression(config)) {
        const property = config.properties.find(p => ts.isPropertyAssignment(p) && propName(p.name) === 'routes')
        if (property) value = read(property.initializer)
      }
    }
    ts.forEachChild(node, walk)
  }
  walk(src)
  if (!value) throw new Error('Cannot capture routes')
  return value
}
function simplifyRoutes(entries) {
  return entries.map(({path, name, component, meta, children}) => ({path, name, component, meta, ...(children ? {children: simplifyRoutes(children)} : {})}))
}
function routeRecords() {
  return simplifyRoutes(writeMode ? oldRoutes() : read(declared('frontend/src/router/routes.ts', 'routes')))
}
function navigation() {
  const file = writeMode ? 'frontend/src/components/AppShell.vue' : 'frontend/src/app/navigation.ts'
  const scriptFile = writeMode ? 'frontend/scripts/refactor/.baseline-shell-script.ts' : file
  if (writeMode) {
    const s = fs.readFileSync(path.join(repo,file),'utf8')
    fs.writeFileSync(path.join(repo,scriptFile),s.match(/<script setup lang="ts">([\s\S]*?)<\/script>/)[1])
  }
  try {
    return Object.fromEntries(['sidebarModes', 'SYSTEM_NAV_PATHS','ORGANIZATION_NAV_PATHS','navItems','RECENT_ROUTE_HINTS'].map(key => [key,read(declared(scriptFile,key))]))
  } finally {
    if (writeMode) fs.unlinkSync(path.join(repo,scriptFile))
  }
}
function locales() {
  if (writeMode) {
    const node=declared('frontend/src/i18n/index.ts','messages')
    const all=read(node)
    return {vi:all.vi, en:all.en}
  }
  return {
    vi:read(declared('frontend/src/i18n/locales/vi.ts','vi')),
    en:read(declared('frontend/src/i18n/locales/en.ts','en')),
  }
}
// Protect AppShell interaction bodies/template, router guards and locale persistence
// independently of moving navigation/translation declarations.
const EXTRACTED_SHELL_NAMES = new Set([
  'sidebarModes', 'NavigationItem', 'SYSTEM_NAV_PATHS',
  'ORGANIZATION_NAV_PATHS', 'navItems', 'RECENT_ROUTE_HINTS',
])
function shellBehavior(sfc) {
  const match = sfc.match(/<script setup lang="ts">([\s\S]*?)<\/script>/)
  if (!match) throw new Error('Shell <script setup> missing')
  const body = match[1]
  const source = ts.createSourceFile('AppShell.ts', body, ts.ScriptTarget.Latest, true)
  if (source.parseDiagnostics.length) throw new Error('Shell script cannot be parsed')
  const statements = source.statements.filter((node) => {
    if (ts.isImportDeclaration(node)) return false
    if (ts.isTypeAliasDeclaration(node) && EXTRACTED_SHELL_NAMES.has(node.name.text)) return false
    if (ts.isVariableStatement(node) && node.declarationList.declarations.some(d => EXTRACTED_SHELL_NAMES.has(d.name.getText(source)))) return false
    return true
  }).map((node)=>node.getText(source))
  return {
    interactionScriptSha256: sha(statements.join('\n')),
    templateAndStylesSha256: sha(sfc.slice(sfc.indexOf('</script>') + '</script>'.length)),
  }
}
function routerGuard(content) {
  const source = ts.createSourceFile('router.ts', content, ts.ScriptTarget.Latest, true)
  const beforeEach = source.statements.filter((stmt) => ts.isExpressionStatement(stmt) && stmt.getText(source).startsWith('router.beforeEach('))
  if (beforeEach.length !== 1) throw new Error('Expected one route guard')
  return sha(beforeEach[0].getText(source))
}
function localeRuntime(content) {
  const marker = 'function resolveInitialLocale()'
  if (!content.includes(marker)) throw new Error('Locale runtime helpers missing')
  return sha(content.slice(content.indexOf(marker)).trim())
}
function preservedLogic(source = (relative) => fs.readFileSync(path.join(repo, relative),'utf8')) {
  return {
    shell: shellBehavior(source('frontend/src/components/AppShell.vue')),
    routerGuardSha256: routerGuard(source('frontend/src/router/index.ts')),
    localeRuntimeSha256: localeRuntime(source('frontend/src/i18n/index.ts')),
  }
}
function leafCount(v) {
  if (typeof v !== 'object' || v === null) return 1
  return Object.values(v).reduce((sum,x)=>sum+leafCount(x),0)
}
function build() {
  const routes=routeRecords()
  const menu=navigation()
  const translations=locales()
  const criticalFiles = [
    ...walkFiles(path.join(root,'src/services')).filter(x=>x.endsWith('.ts')),
    ...walkFiles(path.join(root,'src/stores')).filter(x=>x.endsWith('.ts')&&!x.includes('__tests__')),
    ...walkFiles(path.join(root,'src/composables')).filter(x=>x.endsWith('.ts')&&!x.includes('__tests__')),
    ...walkFiles(path.join(root,'src/api')).filter(x=>x.endsWith('.ts')&&!x.includes('__tests__')),
    'contracts/openapi/openapi.yaml', 'backend/app/Config/Routes.php',
  ].map(x=>toRepoRelative(x, repo)).sort()
  const views=writeMode?walkFiles(path.join(root,'src/views')).filter(x=>x.endsWith('.vue')):walkFiles(path.join(root,'src/features')).filter(x=>x.endsWith('View.vue')).concat(walkFiles(path.join(root,'src/app')).filter(x=>x.endsWith('View.vue')))
  const viewSha=Object.fromEntries(views.map(x=>[path.basename(x),sha(fs.readFileSync(x))]).sort(([a],[b])=>a.localeCompare(b)))
  return {schema:'frontend-refactor-equivalence/1', routes, navigation: menu,
    locales: Object.fromEntries(Object.entries(translations).map(([key,value])=>[key,{sha256:sha(JSON.stringify(value)),leafCount:leafCount(value)}])),
    views:viewSha,
    preservedRuntimeLogic: preservedLogic(),
    criticalFileHashes:Object.fromEntries(criticalFiles.map(x=>[x,fileSha(x)])),
  }
}
function walkFiles(dir) {
  if (!fs.existsSync(dir)) return []
  return fs.readdirSync(dir,{withFileTypes:true}).flatMap(x=>x.isDirectory()?walkFiles(path.join(dir,x.name)):[path.join(dir,x.name)])
}
const actual=build()
if (writeMode) {
  fs.writeFileSync(manifestFile, JSON.stringify(actual,null,2)+'\n')
  console.log(`CAPTURED: ${Object.keys(actual.views).length} views, ${actual.routes[1].children.length+1} named routes, ${actual.locales.vi.leafCount} Vietnamese keys, ${actual.locales.en.leafCount} English keys`)
  process.exit(0)
}
const expected=JSON.parse(fs.readFileSync(manifestFile,'utf8'))
const sections=['routes','navigation','locales','views','preservedRuntimeLogic','criticalFileHashes']
const hashDrift = compareCriticalHashes(expected.criticalFileHashes, actual.criticalFileHashes)
let frontendFail=0
for (const section of sections) {
  // Backend routes and OpenAPI are tracked for visibility, but are not owned by
  // this frontend-only refactor. A changed frontend hash always fails the gate.
  const changed = section === 'criticalFileHashes'
    ? hashDrift.frontend.length > 0
    : JSON.stringify(expected[section]) !== JSON.stringify(actual[section])
  if (changed) {
    console.error(`FAIL ${section}: current snapshot differs from locked frontend baseline`)
    if (section === 'views') {
      const keys=new Set([...Object.keys(expected[section]),...Object.keys(actual[section])])
      for(const key of keys) if(expected[section][key]!==actual[section][key]) console.error('  ',key)
    }
    if (section === 'criticalFileHashes') {
      for (const {file} of hashDrift.frontend) console.error('  ',file)
    }
    frontendFail++
  } else {
    console.log(`PASS ${section}: no frontend/static-contract drift`)
  }
}
if (hashDrift.external.length) {
  const strict = process.argv.includes('--strict-external')
  const log = strict ? console.error : console.warn
  log(`${strict ? 'FAIL' : 'WARN'} externalDependencyHashes: ${hashDrift.external.length} tracked file(s) differ from the original snapshot`)
  for (const {file, baseline, current} of hashDrift.external) {
    log(`  ${file} (baseline: ${baseline ?? 'missing'}, current: ${current ?? 'missing'})`)
  }
  log('  Review backend/OpenAPI changes for API compatibility. Do not overwrite the locked manifest to hide drift.')
} else {
  console.log('PASS externalDependencyHashes: backend routes and OpenAPI match the locked snapshot')
}
console.log(`${sections.length-frontendFail}/${sections.length} frontend contract sections pass`)
process.exitCode=(frontendFail || (process.argv.includes('--strict-external') && hashDrift.external.length))?1:0
