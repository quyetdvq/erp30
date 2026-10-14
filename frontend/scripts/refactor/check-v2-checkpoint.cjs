#!/usr/bin/env node
/** Check V2 start fingerprint without rewriting it. --report is nonblocking after intentional changes. */
const fs = require('node:fs')
const path = require('node:path')
const { MANIFEST_NAME, snapshotFiles, compareSnapshots, fingerprint } = require('./v2-checkpoint-lib.cjs')

const args = process.argv.slice(2)
if (args.some(arg => arg !== '--strict' && arg !== '--report') || (args.includes('--strict') && args.includes('--report'))) {
  console.error('Usage: node scripts/refactor/check-v2-checkpoint.cjs [--strict | --report]')
  process.exit(2)
}
const strict = !args.includes('--report')
const frontendRoot = path.resolve(__dirname, '../..')
const manifestPath = path.join(__dirname, MANIFEST_NAME)
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'))
if (manifest.schema !== 'erp30.frontend.v2-start/1' || manifest.baselineId !== 'FE-V2-START-2026-10-14' || !manifest.files) {
  throw new Error('Unrecognized V2 baseline manifest; do not silently regenerate it')
}
if (manifest.treeSha256 !== fingerprint(manifest.files)) throw new Error('V2 manifest fingerprint inconsistent; restore the reviewed baseline')
const actual = snapshotFiles(frontendRoot)
const diff = compareSnapshots(manifest.files, actual)
const count = diff.added.length + diff.deleted.length + diff.modified.length
const message = `${Object.keys(manifest.files).length} locked frontend files; ${diff.unchanged} unchanged, ${diff.modified.length} modified, ${diff.added.length} added, ${diff.deleted.length} deleted`
if (!count) {
  console.log(`PASS V2_START_SNAPSHOT: ${message}`)
} else {
  console.log(`${strict ? 'FAIL' : 'DRIFT'} V2_START_SNAPSHOT: ${message}`)
  for (const group of ['modified', 'added', 'deleted']) {
    if (!diff[group].length) continue
    console.log(`  ${group.toUpperCase()}:`)
    for (const file of diff[group].slice(0, 30)) console.log(`    ${file}`)
    if (diff[group].length > 30) console.log(`    ... ${diff[group].length - 30} more`)
  }
  console.log('This is a source diff, NOT proof of functional regression. Review approved changes and rerun behavior/contract/unit/E2E gates.')
}
if (strict && count) process.exitCode = 1
