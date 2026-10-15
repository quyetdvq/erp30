import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { stripTypeScriptTypes } from 'node:module'

// Exercise the actual production function without requiring Vite aliases or a browser.
// Only its two imports are replaced with spies; multipart assembly is unchanged.
const calls = []
globalThis.__avatarTransportTestClient = {
  post: async (...args) => calls.push(['post', ...args]),
  put: async (...args) => calls.push(['put', ...args]),
}
const source = stripTypeScriptTypes(await readFile(new URL('../userAvatar.ts', import.meta.url), 'utf8'))
  .replace("import { apiClient } from '@/services/api'", 'const apiClient = globalThis.__avatarTransportTestClient')
  .replace(
    "import { avatarApiPath } from '@/services/userAvatarRules'",
    'const avatarApiPath = (id) => `/api/v1/users/${id}/avatar`',
  )
const { uploadUserAvatar } = await import(`data:text/javascript;charset=utf-8,${encodeURIComponent(source)}`)

test('multipart upload uses POST with _method=PUT so PHP parses image into $_FILES', async () => {
  const file = new File([Uint8Array.from([0xff, 0xd8, 0xff, 0xd9])], 'anh.jpg', {
    type: 'image/jpeg',
  })
  await uploadUserAvatar('4', file)

  assert.equal(calls.length, 1)
  const [method, path, payload] = calls[0]
  assert.equal(method, 'post')
  assert.equal(path, '/api/v1/users/4/avatar')
  assert.ok(payload instanceof FormData)
  assert.equal(payload.get('_method'), 'PUT')
  assert.equal(payload.get('image'), file)
})
