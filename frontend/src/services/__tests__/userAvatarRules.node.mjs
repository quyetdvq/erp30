import { test } from 'node:test'
import assert from 'node:assert/strict'
import { avatarSelectionIssue, avatarApiPath, MAX_USER_AVATAR_BYTES } from '../userAvatarRules.ts'

test('only JPEG/PNG/WebP images with nonempty content and at most 2MiB are selectable', () => {
  assert.equal(avatarSelectionIssue({type:'image/png',size:32}), null)
  assert.equal(avatarSelectionIssue({type:'image/webp',size:MAX_USER_AVATAR_BYTES}), null)
  assert.equal(avatarSelectionIssue({type:'image/svg+xml',size:42}), 'type')
  assert.equal(avatarSelectionIssue({type:'image/jpeg',size:MAX_USER_AVATAR_BYTES+1}), 'size')
  assert.equal(avatarSelectionIssue({type:'image/png',size:0}), 'size')
})

test('avatar route only allows canonical positive decimal user IDs', () => {
  assert.equal(avatarApiPath('27'), '/api/v1/users/27/avatar')
  assert.throws(() => avatarApiPath('../test'))
  assert.throws(() => avatarApiPath('01'))
})
