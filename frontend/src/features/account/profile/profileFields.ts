/** Omit unchanged names, including legacy NULL. Never send invalid blanks to the backend. */
export function profileNamePatch(original: string | null, edited: string): { name?: string } {
  const trimmed = edited.trim()
  if (trimmed === (original ?? '')) return {}
  if (!trimmed || Array.from(trimmed).length > 255) throw new Error('INVALID_USER_FULL_NAME')
  return { name: trimmed }
}
