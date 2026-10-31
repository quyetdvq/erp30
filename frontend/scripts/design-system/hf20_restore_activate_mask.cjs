// Compatibility entrypoint retained for existing Windows commands.
// Historical HF20 used to overwrite activate/toggle-off with PowerOutline.
// The custom buttons.css now owns its artwork; NEVER rewrite it automatically.
'use strict'
const fs = require('node:fs')
const path = require('node:path')
const { inspectMaskRegistry } = require('./action-mask-registry.cjs')

function verifyActionMasks(css) {
  const icons = inspectMaskRegistry(css)
  for (const name of ['activate', 'deactivate', 'changestate']) {
    if (!icons.has(name)) throw new Error(`STOP: Missing ${name} icon`)
  }
  const unique = new Set(['activate', 'deactivate', 'changestate'].map(name => icons.get(name).svg))
  if (unique.size !== 3) throw new Error('STOP: Activate, Deactivate and Change States must have different SVG icons')
  return { css, status: 'valid-custom-icons', count: icons.size }
}

// Deprecated name retained for old callers; now strictly read-only.
const normalizeActivateMask = verifyActionMasks

if (require.main === module) {
  try {
    const target = path.resolve(__dirname, '../../src/styles/buttons.css')
    const source = fs.readFileSync(target, 'utf8')
    const result = verifyActionMasks(source)
    process.stdout.write(`Action icon registry: ${result.status} (${result.count} icons, READ-ONLY).\n`)
  } catch (error) {
    process.stderr.write(`${error instanceof Error ? error.message : String(error)}\n`)
    process.exitCode = 1
  }
}
module.exports = { verifyActionMasks, normalizeActivateMask }
