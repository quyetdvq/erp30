const crypto = require('node:crypto')
const ts = require('typescript')

function structuralFingerprint(node) {
  const shape = []
  function walk(part) {
    shape.push(ts.SyntaxKind[part.kind])
    if (ts.isIdentifier(part) || ts.isLiteralExpression(part)) shape.push(part.text)
    ts.forEachChild(part, walk)
  }
  walk(node)
  return crypto.createHash('sha256').update(shape.join('\u001f')).digest('hex')
}

function scriptStatements(sourceText, filename, nestedShellComposables = false) {
  const source = ts.createSourceFile(filename, sourceText, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
  if (source.parseDiagnostics.length) {
    throw new Error(`Invalid TypeScript in ${filename}`)
  }
  const result = []
  function visit(statement) {
    if (ts.isImportDeclaration(statement)) return
    if (nestedShellComposables && ts.isFunctionDeclaration(statement) && statement.name?.text.startsWith('useShell')) {
      for (const child of statement.body?.statements ?? []) {
        if (!ts.isReturnStatement(child)) visit(child)
      }
      return
    }
    result.push(structuralFingerprint(statement))
  }
  for (const statement of source.statements) visit(statement)
  return result
}

module.exports = { scriptStatements }
