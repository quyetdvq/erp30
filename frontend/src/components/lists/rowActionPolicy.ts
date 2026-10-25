/**
 * M10.2 — UI visibility is an advisory intersection, never an authorization
 * decision for the API. The mutation endpoint must reauthorize persisted data.
 */
export type RowActionCode =
  | 'edit'
  | 'setstate'
  | 'delete'
  | 'assign_member'
  | 'assign_permission'
  | 'reset_password'
  | 'configure_numbering'

export type AllowedRowActions = Partial<Record<RowActionCode, boolean>>
export type RowAuthorizationMode = 'effective' | 'legacy-base-only'
export type RowMutationIntent = 'edit' | 'toggle' | 'delete'

const defaultActionCode: Record<RowMutationIntent, RowActionCode> = {
  edit: 'edit',
  toggle: 'setstate',
  delete: 'delete',
}

/**
 * Read additive API extensions without modifying frozen code-generated SDK
 * files. Missing/non-boolean values never become authorization grants.
 */
export function rowAllowedActions(row: unknown): AllowedRowActions | null {
  if (row === null || typeof row !== 'object' || Array.isArray(row)) return null
  const hint: unknown = (row as { allowed_actions?: unknown }).allowed_actions
  if (hint === null || typeof hint !== 'object' || Array.isArray(hint)) return null
  const valid: AllowedRowActions = {}
  for (const action of Object.values(defaultActionCode)) {
    const value = (hint as Record<string, unknown>)[action]
    if (typeof value === 'boolean') valid[action] = value
  }
  for (const action of ['assign_member', 'assign_permission', 'reset_password', 'configure_numbering'] as const) {
    const value = (hint as Record<string, unknown>)[action]
    if (typeof value === 'boolean') valid[action] = value
  }
  return valid
}

type BaseAccess = {
  snapshotReady: boolean
  permission?: string
  hasBasePermission: (key: string) => boolean
}

function canUseBasePermission({ snapshotReady, permission, hasBasePermission }: BaseAccess): boolean {
  return snapshotReady && typeof permission === 'string' && permission.length > 0 &&
    hasBasePermission(permission)
}

export function canViewRow(input: BaseAccess & {
  show: boolean
  viewScopeVerified: boolean
  authorizationMode: RowAuthorizationMode
  viewActionCode?: RowActionCode
  allowedActions?: AllowedRowActions | null
}): boolean {
  return input.show && (input.authorizationMode === 'legacy-base-only' || input.viewScopeVerified) &&
    canUseBasePermission(input) && (input.viewActionCode === undefined ||
      input.allowedActions?.[input.viewActionCode] === true)
}

export function canMutateRow(input: BaseAccess & {
  show: boolean
  authorizationMode: RowAuthorizationMode
  intent: RowMutationIntent
  allowedActions?: AllowedRowActions | null
  actionCodes?: Partial<Record<RowMutationIntent, RowActionCode>>
}): boolean {
  if (!input.show || !canUseBasePermission(input)) return false
  if (input.authorizationMode === 'legacy-base-only') return true
  const action = input.actionCodes?.[input.intent] ?? defaultActionCode[input.intent]
  return input.allowedActions?.[action] === true
}
