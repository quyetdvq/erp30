const assert = require('node:assert/strict')
const test = require('node:test')
const { compareCriticalHashes } = require('./hash-policy.cjs')

test('identical snapshots have no drift', () => {
  const actual = { 'frontend/src/api/client.ts': 'hash-a' }
  assert.deepEqual(compareCriticalHashes(actual, actual), { frontend: [], external: [] })
})

test('frontend changes must fail the equivalence gate', () => {
  const result = compareCriticalHashes(
    { 'frontend/src/api/client.ts': 'hash-a' },
    { 'frontend/src/api/client.ts': 'hash-b' },
  )
  assert.deepEqual(result.external, [])
  assert.equal(result.frontend[0].file, 'frontend/src/api/client.ts')
})

test('backend routes and OpenAPI changes are disclosed separately', () => {
  const result = compareCriticalHashes(
    { 'backend/app/Config/Routes.php': 'a', 'contracts/openapi/openapi.yaml': 'b' },
    { 'backend/app/Config/Routes.php': 'c', 'contracts/openapi/openapi.yaml': 'd' },
  )
  assert.deepEqual(result.frontend, [])
  assert.deepEqual(result.external.map(({ file }) => file), [
    'backend/app/Config/Routes.php',
    'contracts/openapi/openapi.yaml',
  ])
})

test('unexpected tracked paths cannot silently bypass strict comparison', () => {
  const result = compareCriticalHashes({}, { 'another/critical-config.ts': 'new' })
  assert.equal(result.frontend.length, 1)
})
