const assert = require('node:assert/strict')
const crypto = require('node:crypto')
const fs = require('node:fs')
const path = require('node:path')
const { test } = require('node:test')

const frontend = path.resolve(__dirname, '../..')
const source = fs.readFileSync(path.join(frontend, 'src/components/AppShell.vue'), 'utf8')
const styles = fs.readFileSync(path.join(frontend, 'src/components/app-shell/styles.css'), 'utf8')

const sha256 = (text) => crypto.createHash('sha256').update(text).digest('hex')

// Pinned from FE-V2-START-2026-10-14. These are the original template and CSS
// bodies, not the refactored files' byte hashes. Do not regenerate to silence drift.
test('the complete AppShell DOM template is unchanged from V2 Start', () => {
  const first = source.indexOf('<template>')
  const last = source.lastIndexOf('</template>')
  assert.ok(first !== -1 && last > first, 'AppShell must contain its full template')
  const template = source.slice(first, last + '</template>'.length)
  assert.equal(sha256(template), 'ae72f3ff4be7e23f1d800ef8786a1ce87a078edf7e7fba6e5892da5f191617c3')
})

test('AppShell keeps the original CSS with the Vue scoped-style compiler', () => {
  assert.match(source, /<style scoped src="\.\/app-shell\/styles\.css"><\/style>/)
  assert.equal(sha256(styles), 'a48cacc6cc3aedca31bbf5aeaf898642e8f2263ccf7c81a14422e835853c1862')
})

test('AppShell composes the navigation, header, workspace and resize owners', () => {
  for (const part of [
    'useShellNavigation',
    'useShellHeader',
    'useShellWorkspaceTabs',
    'useShellSidebarResize',
  ]) {
    const body = fs.readFileSync(path.join(frontend, `src/components/app-shell/${part}.ts`), 'utf8')
    assert.match(body, new RegExp(`export function ${part}\\(`))
    assert.ok(source.includes(`${part}(`), `${part} must be used in AppShell.vue`)
  }
})

// Formatting does not change these AST fingerprints. Every original top-level
// statement must be present in the refactored composition; new glue is allowed.
test('all original shell interaction statements survive composition unchanged', () => {
  const { scriptStatements } = require('./v2-1-shell-structure.cjs')
  const manifest = require('./v2-1-shell-logic.json')
  assert.equal(manifest.hashes.length, 72)
  const shellScript = source.split('<script setup lang="ts">')[1].split('</script>')[0]
  const observed = scriptStatements(shellScript, 'AppShell.vue')
  for (const part of [
    'useShellNavigation',
    'useShellHeader',
    'useShellWorkspaceTabs',
    'useShellSidebarResize',
  ]) {
    const code = fs.readFileSync(path.join(frontend, `src/components/app-shell/${part}.ts`), 'utf8')
    observed.push(...scriptStatements(code, `${part}.ts`, true))
  }
  const counts = new Map()
  for (const hash of observed) counts.set(hash, (counts.get(hash) ?? 0) + 1)
  for (const hash of manifest.hashes) {
    assert.ok((counts.get(hash) ?? 0) > 0, `V2 Start script statement missing: ${hash}`)
    counts.set(hash, counts.get(hash) - 1)
  }
})
