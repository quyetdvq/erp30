/** Frozen frontend-only source inventory for V2, independent of backend/OpenAPI drift. */
const fs = require('node:fs')
const path = require('node:path')
const crypto = require('node:crypto')

const SCAN_DIRS = Object.freeze(['src', 'e2e', 'scripts', 'public'])
const ROOT_FILES = Object.freeze([
  '.editorconfig', '.gitattributes', '.gitignore', '.oxlintrc.json',
  '.prettierignore', '.prettierrc.json', 'env.d.ts', 'eslint.config.ts',
  'index.html', 'openapi-ts.config.ts', 'package.json', 'pnpm-lock.yaml',
  'playwright.config.ts', 'playwright.firefox-diagnostic.config.ts',
  'playwright.live.config.ts', 'tsconfig.app.json', 'tsconfig.json',
  'tsconfig.node.json', 'tsconfig.vitest.json', 'vite.config.ts', 'vitest.config.ts',
])
const MANIFEST_NAME = 'v2-start-2026-10-14.json'
const EXCLUDED = new Set([`scripts/refactor/${MANIFEST_NAME}`])

function relativeKey(frontendRoot, filename) {
  const relative = path.relative(frontendRoot, filename)
  if (!relative || relative === '..' || relative.startsWith(`..${path.sep}`) || path.isAbsolute(relative)) {
    throw new Error(`Path outside frontend: ${filename}`)
  }
  return relative.split(path.sep).join('/')
}

function listTrackedFiles(frontendRoot) {
  const found = []
  function walk(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
      const filename = path.join(directory, entry.name)
      if (entry.isSymbolicLink()) throw new Error(`Symlink not allowed in V2 checkpoint: ${filename}`)
      if (entry.isDirectory()) walk(filename)
      else if (entry.isFile() && !EXCLUDED.has(relativeKey(frontendRoot, filename))) found.push(filename)
    }
  }
  for (const folder of SCAN_DIRS) {
    const folderPath = path.join(frontendRoot, folder)
    if (!fs.existsSync(folderPath)) throw new Error(`Missing tracked frontend directory: ${folder}`)
    walk(folderPath)
  }
  for (const filename of ROOT_FILES) {
    const fullPath = path.join(frontendRoot, filename)
    if (!fs.statSync(fullPath, { throwIfNoEntry: false })?.isFile()) {
      throw new Error(`Missing tracked frontend config: ${filename}`)
    }
    found.push(fullPath)
  }
  return found.sort((a, b) => relativeKey(frontendRoot, a).localeCompare(relativeKey(frontendRoot, b)))
}

function snapshotFiles(frontendRoot) {
  const result = {}
  for (const filename of listTrackedFiles(frontendRoot)) {
    const key = relativeKey(frontendRoot, filename)
    result[key] = crypto.createHash('sha256').update(fs.readFileSync(filename)).digest('hex')
  }
  return result
}

function fingerprint(files) {
  const material = Object.keys(files).sort().map(key => `${key}:${files[key]}`).join('\n')
  return crypto.createHash('sha256').update(material).digest('hex')
}

function compareSnapshots(expected, actual) {
  const added = []
  const deleted = []
  const modified = []
  for (const key of [...new Set([...Object.keys(expected), ...Object.keys(actual)])].sort()) {
    if (!Object.hasOwn(expected, key)) added.push(key)
    else if (!Object.hasOwn(actual, key)) deleted.push(key)
    else if (expected[key] !== actual[key]) modified.push(key)
  }
  return { added, deleted, modified, unchanged: Object.keys(expected).length - deleted.length - modified.length }
}

module.exports = { MANIFEST_NAME, SCAN_DIRS, ROOT_FILES, relativeKey, listTrackedFiles, snapshotFiles, fingerprint, compareSnapshots }
