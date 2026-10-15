/** Pure, transport-independent rules for the Shield user editor. */
export type UserEditorState = 'ACTIVE' | 'INACTIVE' | 'LOCKED' | 'SUSPENDED' | 'BANNED' | 'DISABLED'
export type UserCreateDraft = {
  username: string
  name: string
  email: string
  password: string
  state: UserEditorState
  group_ids: string[]
}
export type UserEditDraft = Pick<UserCreateDraft, 'username' | 'name' | 'email'>
export type UserEditOriginal = { username: string; email: string; name: string | null }
export type SelectableGroup = { id: string | number; name: string; code: string; status: string }

export function fullNameIssue(input: string): 'required' | 'too_long' | null {
  const value = input.trim()
  if (!value) return 'required'
  if (Array.from(value).length > 255) return 'too_long'
  return null
}

export function selectableGroupOptions(groups: readonly SelectableGroup[]): Array<{ value: string; label: string }> {
  return groups
    .filter((group) => group.status === 'ACTIVE')
    .filter((group) => /^[1-9][0-9]*$/.test(String(group.id)))
    .map((group) => ({ value: String(group.id), label: `${group.name} (${group.code})` }))
}

export function buildCreateUserPayload(draft: UserCreateDraft): UserCreateDraft {
  if (fullNameIssue(draft.name)) throw new Error('INVALID_USER_FULL_NAME')
  if (new Set(draft.group_ids).size !== draft.group_ids.length ||
      draft.group_ids.some((id) => !/^[1-9][0-9]*$/.test(id))) {
    throw new Error('INVALID_GROUP_IDS')
  }
  return { ...draft, name: draft.name.trim(), group_ids: [...draft.group_ids] }
}

export function buildEditUserPayload(original: UserEditOriginal, draft: UserEditDraft): {
  username: string; email: string; name?: string
} {
  const patch: { username: string; email: string; name?: string } = {
    username: draft.username,
    email: draft.email,
  }
  if (draft.name.trim() !== (original.name ?? '')) {
    if (fullNameIssue(draft.name)) throw new Error('INVALID_USER_FULL_NAME')
    patch.name = draft.name.trim()
  }
  return patch
}

/** Account creation commits independently from optional Avatar PUT. Keep its ID after PUT fails. */
export async function submitCreatedUserAvatar(
  state: { createdUserId: string | null },
  create: () => Promise<{ id: string | number }>,
  upload: ((id: string) => Promise<void>) | null,
): Promise<{ id: string }> {
  if (!state.createdUserId) {
    const created = await create()
    state.createdUserId = String(created.id)
  }
  if (upload) await upload(state.createdUserId)
  return { id: state.createdUserId }
}
