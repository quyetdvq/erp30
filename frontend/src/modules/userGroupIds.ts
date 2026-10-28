/** Shared, transport-independent Shield group membership IDs. */
export function userGroupIds(groups: readonly { id: string | null }[]): string[] {
  return [...new Set(groups
    .map((group) => group.id)
    .filter((id): id is string => typeof id === 'string' && /^[1-9][0-9]*$/.test(id)))]
}
