export const MAX_USER_AVATAR_BYTES = 2 * 1024 * 1024
const supported = new Set(['image/jpeg', 'image/png', 'image/webp'])

/** Client-side convenience only: the backend remains authoritative about decoded content and dimensions. */
export function avatarSelectionIssue(file: {type: string; size: number}): 'type' | 'size' | null {
  if (!supported.has(file.type)) return 'type'
  if (!Number.isFinite(file.size) || file.size <= 0 || file.size > MAX_USER_AVATAR_BYTES) return 'size'
  return null
}

export function avatarApiPath(userId: string | number): string {
  const value = String(userId)
  if (!/^[1-9][0-9]*$/.test(value)) throw new Error('INVALID_USER_ID')
  return `/api/v1/users/${value}/avatar`
}
