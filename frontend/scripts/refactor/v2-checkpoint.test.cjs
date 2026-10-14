const assert = require('node:assert/strict')
const test = require('node:test')
const path = require('node:path')
const fs = require('node:fs')
const os = require('node:os')
const { compareSnapshots, relativeKey, snapshotFiles, fingerprint, MANIFEST_NAME } = require('./v2-checkpoint-lib.cjs')

test('unchanged snapshots pass', () => {
  assert.deepEqual(compareSnapshots({ a: '1', b: '2' }, { a: '1', b: '2' }), {
    added: [], deleted: [], modified: [], unchanged: 2,
  })
})

test('fingerprint remains deterministic regardless of key insertion order', () => {
  assert.equal(fingerprint({ b: '2', a: '1' }), fingerprint({ a: '1', b: '2' }))
  assert.notEqual(fingerprint({ a: '1' }), fingerprint({ a: '2' }))
})

test('added, removed and modified files are reported independently', () => {
  assert.deepEqual(compareSnapshots({ a: 'old', b: 'keep' }, { a: 'new', b: 'keep', c: 'added' }), {
    added: ['c'], deleted: [], modified: ['a'], unchanged: 1,
  })
  assert.deepEqual(compareSnapshots({ a: '1', b: '2' }, { a: '1' }), {
    added: [], deleted: ['b'], modified: [], unchanged: 1,
  })
})

test('manifest keys stay relative and portable on current OS', () => {
  const root = path.join(path.parse(process.cwd()).root, 'example', 'frontend')
  assert.equal(relativeKey(root, path.join(root, 'src', 'router', 'routes.ts')), 'src/router/routes.ts')
  assert.throws(() => relativeKey(root, path.resolve(root, '..', 'backend', 'Routes.php')), /outside frontend/)
})

test('checkpoint never hashes its own manifest and includes app, test, tooling and configs', () => {
  const frontendRoot = path.resolve(__dirname, '../..')
  const files = snapshotFiles(frontendRoot)
  assert.equal(Object.hasOwn(files, `scripts/refactor/${MANIFEST_NAME}`), false)
  for (const key of ['src/router/routes.ts', 'e2e/fe090-lookup-batch-regression.spec.ts', 'scripts/refactor/check-v2-checkpoint.cjs', 'package.json', 'pnpm-lock.yaml', 'public/favicon.ico']) {
    assert.equal(Object.hasOwn(files, key), true, key)
  }
  const content = fs.readFileSync(path.join(frontendRoot, 'src', 'router', 'routes.ts'))
  assert.equal(files['src/router/routes.ts'], require('node:crypto').createHash('sha256').update(content).digest('hex'))
})

test('scanner cannot silently follow symlinks in its source trees', () => {
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'erp30-checkpoint-'))
  try {
    fs.mkdirSync(path.join(temp, 'src'))
    fs.writeFileSync(path.join(temp, 'src', 'ordinary.txt'), 'safe')
    // In Windows developer mode, creating symlinks may require privileges.
    // Cover link exclusion through filesystem entry checks wherever supported.
    let linked = false
    try {
      fs.symlinkSync(path.join(temp, 'src', 'ordinary.txt'), path.join(temp, 'src', 'linked.txt'))
      linked = true
    } catch (error) {
      if (!['EPERM', 'EACCES', 'ENOTSUP', 'EOPNOTSUPP'].includes(error.code)) throw error
    }
    if (linked) {
      const { listTrackedFiles } = require('./v2-checkpoint-lib.cjs')
      assert.throws(() => listTrackedFiles(temp), /Symlink not allowed/)
    }
  } finally {
    fs.rmSync(temp, { recursive: true, force: true })
  }
})
