#!/usr/bin/env node
/** Validate local module boundaries and alias paths without starting Vite or the API. */
const fs = require('node:fs')
const path = require('node:path')
const ts = require('typescript')
const src = path.resolve(__dirname, '../src')
const errors = []
let files = 0
let localImports = 0
const extensions = ['', '.ts', '.tsx', '.vue', '.js', '.mjs', '.json', '.css', '.scss', '/index.ts', '/index.vue', '/index.js']

function visitDirectory(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const filename = path.join(dir, entry.name)
    if (entry.isDirectory()) {
      visitDirectory(filename)
    } else if (/\.(?:ts|tsx|vue)$/.test(filename)) {
      checkFile(filename)
    }
  }
}
function checkFile(filename) {
  let code = fs.readFileSync(filename, 'utf8')
  if (filename.endsWith('.vue')) {
    const script = code.match(/<script(?:\s+setup)?[^>]*>([\s\S]*?)<\/script>/)
    if (!script) return
    code = script[1]
  }
  const ast = ts.createSourceFile(filename, code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
  files++
  if (ast.parseDiagnostics.length) errors.push(`Invalid TypeScript syntax: ${path.relative(src, filename)}`)
  function verify(specifier) {
    if (!specifier.startsWith('@/') && !specifier.startsWith('.')) return
    localImports++
    if (specifier.startsWith('@/views/')) {
      errors.push(`Old view alias: ${path.relative(src, filename)} -> ${specifier}`)
    }
    const base = specifier.startsWith('@/')
      ? path.join(src, specifier.slice(2))
      : path.resolve(path.dirname(filename), specifier)
    if (!extensions.some((ext) => fs.existsSync(base + ext) && fs.statSync(base + ext).isFile())) {
      errors.push(`Unresolved local import: ${path.relative(src, filename)} -> ${specifier}`)
    }
    const rel = path.relative(src, filename).replaceAll('\\', '/')
    if (rel.startsWith('services/') && specifier.startsWith('@/features/')) {
      errors.push(`Forbidden service -> feature import: ${rel} -> ${specifier}`)
    }
    if (rel.startsWith('api/generated/') && specifier.startsWith('@/')) {
      errors.push(`Generated SDK must not import hand-written code: ${rel} -> ${specifier}`)
    }
  }
  function walk(node) {
    if ((ts.isImportDeclaration(node) || ts.isExportDeclaration(node)) && node.moduleSpecifier && ts.isStringLiteral(node.moduleSpecifier)) verify(node.moduleSpecifier.text)
    if (ts.isCallExpression(node) && node.arguments.length >= 1 && node.arguments[0] && ts.isStringLiteral(node.arguments[0]) && (node.expression.kind === ts.SyntaxKind.ImportKeyword || (ts.isIdentifier(node.expression) && node.expression.text === 'require'))) verify(node.arguments[0].text)
    ts.forEachChild(node, walk)
  }
  walk(ast)
}
visitDirectory(src)
for (const err of errors) console.error(err)
console.log(`Frontend import gate: ${files} TS/Vue source files; ${localImports} local import edges; ${errors.length} errors`)
if (errors.length) process.exitCode = 1
