/**
 * M10.3: User list rows already contain canonical group IDs. The detail modal
 * must not GET every group's members just to show one user's memberships.
 * Group member lists are read only when replacing a changed group's members.
 */
export { userGroupIds } from '@/modules/userGroupIds'

/** Never touch groups that are absent from the actor's VIEW-scoped catalog. */
export function changedMembershipGroupIds(
  original: readonly string[],
  draft: readonly string[],
  visible: readonly string[],
): string[] {
  const originalIds = new Set(original)
  const draftIds = new Set(draft)
  const visibleIds = new Set(visible)
  return [...new Set([...originalIds, ...draftIds])].filter((id) =>
    visibleIds.has(id) && originalIds.has(id) !== draftIds.has(id),
  )
}

/**
 * The existing API PUT replaces *all* members of a group. Preserve other
 * members from a fresh server GET; reject a stale selected-user membership
 * instead of silently undoing someone else's change.
 */
export function mergeMembershipForUser(
  currentMemberIds: readonly string[],
  userId: string,
  wasMember: boolean,
  shouldBeMember: boolean,
): string[] {
  if (!/^[1-9][0-9]*$/.test(userId) ||
      currentMemberIds.some((id) => !/^[1-9][0-9]*$/.test(id))) {
    throw new Error('INVALID_GROUP_MEMBER_ID')
  }
  const current = new Set(currentMemberIds)
  if (current.has(userId) !== wasMember) {
    throw new Error('USER_GROUP_MEMBERSHIP_CHANGED_RELOAD')
  }
  if (shouldBeMember) current.add(userId)
  else current.delete(userId)
  return [...current].sort((a, b) => a.length - b.length || a.localeCompare(b))
}
