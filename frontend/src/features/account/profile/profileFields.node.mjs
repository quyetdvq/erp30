import { test } from 'node:test'
import assert from 'node:assert/strict'
import { profileNamePatch } from './profileFields.ts'

test('legacy accounts can update email while leaving NULL name untouched', () => {
  assert.deepEqual(profileNamePatch(null, ''), {})
  assert.deepEqual(profileNamePatch(null, '   '), {})
})

test('profile updates name only on valid change and preserves Unicode', () => {
  assert.deepEqual(profileNamePatch('Lan', '  Lan  '), {})
  assert.deepEqual(profileNamePatch('Lan', ' Nguyễn Thị Lan '), {name:'Nguyễn Thị Lan'})
  assert.throws(() => profileNamePatch('Lan', '   '))
  assert.throws(() => profileNamePatch(null, 'A'.repeat(256)))
})
