import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  userGroupIds,
  changedMembershipGroupIds,
  mergeMembershipForUser,
} from '../src/features/security/user-editor/userMembership.ts'

test('User list membership IDs are canonical, deduplicated and ignore legacy NULL', () => {
  assert.deepEqual(userGroupIds([{ id: '1' }, { id: null }, { id: '1' }, { id: '2' }]), ['1', '2'])
})
test('Only VIEW-scoped changed groups may be written', () => {
  assert.deepEqual(changedMembershipGroupIds(['1', '99'], ['2', '99'], ['1', '2']), ['1', '2'])
  assert.deepEqual(changedMembershipGroupIds(['1'], ['1'], ['1']), [])
})
test('Single User removal preserves every other member of the group', () => {
  assert.deepEqual(mergeMembershipForUser(['8', '7', '9'], '7', true, false), ['8', '9'])
})
test('Single User addition preserves every other member of the group', () => {
  assert.deepEqual(mergeMembershipForUser(['9', '8'], '7', false, true), ['7', '8', '9'])
})
test('Fail closed on stale membership, including add and remove', () => {
  assert.throws(() => mergeMembershipForUser(['8'], '7', true, false), /MEMBERSHIP_CHANGED/)
  assert.throws(() => mergeMembershipForUser(['7', '8'], '7', false, true), /MEMBERSHIP_CHANGED/)
})
test('Never PUT invalid member IDs', () => {
  assert.throws(() => mergeMembershipForUser(['0'], '7', false, true), /INVALID_GROUP_MEMBER_ID/)
})
