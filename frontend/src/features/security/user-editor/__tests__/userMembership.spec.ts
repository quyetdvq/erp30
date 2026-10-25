import { describe, expect, it } from 'vitest'
import {
  changedMembershipGroupIds,
  mergeMembershipForUser,
  userGroupIds,
} from '../userMembership'

describe('M10.3 - user-centric group membership without N+1', () => {
  it('retains only persisted group IDs and ignores unresolved Shield groups', () => {
    expect(userGroupIds([{ id: '1' }, { id: null }, { id: '3' }, { id: '1' }]))
      .toEqual(['1', '3'])
  })

  it('checks group additions/removals only inside the VIEW-scoped group catalog', () => {
    expect(changedMembershipGroupIds(['1', '90'], ['2', '90'], ['1', '2']))
      .toEqual(['1', '2'])
    expect(changedMembershipGroupIds(['1'], ['1'], ['1'])).toEqual([])
  })

  it('preserves all other group members during a single-user removal', () => {
    expect(mergeMembershipForUser(['7', '8', '9'], '7', true, false)).toEqual(['8', '9'])
  })

  it('preserves all other group members during a single-user addition', () => {
    expect(mergeMembershipForUser(['9', '8'], '7', false, true)).toEqual(['7', '8', '9'])
  })

  it('rejects a stale membership instead of overwriting someone else\'s change', () => {
    expect(() => mergeMembershipForUser(['8'], '7', true, false))
      .toThrow('USER_GROUP_MEMBERSHIP_CHANGED_RELOAD')
    expect(() => mergeMembershipForUser(['7', '8'], '7', false, true))
      .toThrow('USER_GROUP_MEMBERSHIP_CHANGED_RELOAD')
  })

  it('rejects invalid member IDs before an API PUT', () => {
    expect(() => mergeMembershipForUser(['0'], '7', false, true))
      .toThrow('INVALID_GROUP_MEMBER_ID')
  })
})
