const assert = require('node:assert/strict')
const path = require('node:path')
const test = require('node:test')
const { toRepoRelative } = require('./paths.cjs')

test('normalizes Windows drive-absolute paths to portable manifest keys', () => {
  const repo = 'D:\\XAMPP\\htdocs\\erp'
  const file = 'D:\\XAMPP\\htdocs\\erp\\frontend\\src\\api\\client.ts'
  assert.equal(toRepoRelative(file, repo, path.win32), 'frontend/src/api/client.ts')
  assert.equal(toRepoRelative('contracts/openapi/openapi.yaml', repo, path.win32), 'contracts/openapi/openapi.yaml')
})

test('normalizes POSIX absolute paths without changing relative entries', () => {
  const repo = '/workspace/erp'
  assert.equal(toRepoRelative('/workspace/erp/frontend/src/api/client.ts', repo, path.posix), 'frontend/src/api/client.ts')
  assert.equal(toRepoRelative('backend/app/Config/Routes.php', repo, path.posix), 'backend/app/Config/Routes.php')
})

test('rejects file paths escaping the repository', () => {
  assert.throws(() => toRepoRelative('D:\\other\\outside.ts', 'D:\\XAMPP\\htdocs\\erp', path.win32), /outside the repository/)
  assert.throws(() => toRepoRelative('/workspace/other.ts', '/workspace/erp', path.posix), /outside the repository/)
})
