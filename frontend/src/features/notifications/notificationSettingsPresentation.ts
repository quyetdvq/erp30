import type { NotificationPolicy, NotificationPreference } from '@/services/notificationSettings'

// Display-only groupings: policy keys, their order and their capabilities still
// come from the server. Unknown future channel suffixes remain visible in Other.
export type NotificationChannel = 'in_app' | 'email' | 'push' | 'other'
export type PreferenceSelection = 'inherit' | 'on' | 'off'
export type NotificationPolicyDraft = Pick<NotificationPolicy, 'default_value' | 'allow_user_override'>

const channelOrder: readonly NotificationChannel[] = ['in_app', 'email', 'push', 'other']
const labelKeys: Readonly<Record<string, string>> = {
  'import.completed.in_app': 'importCompleted',
  'import.completed_with_errors.in_app': 'importCompletedWithErrors',
  'import.failed.in_app': 'importFailed',
  'workflow.action_required.in_app': 'workflowActionRequired',
  'security.permission_changed.in_app': 'securityPermissionChanged',
  'import.completed.email': 'importCompletedEmail',
  'import.completed_with_errors.email': 'importCompletedWithErrorsEmail',
  'import.failed.email': 'importFailedEmail',
  'import.completed.push': 'importCompletedPush',
  'import.completed_with_errors.push': 'importCompletedWithErrorsPush',
  'import.failed.push': 'importFailedPush',
  'workflow.action_required.push': 'workflowActionRequiredPush',
}

export function notificationLabelKey(key: string): string | null {
  return labelKeys[key] ?? null
}

export function notificationChannel(key: string): NotificationChannel {
  if (key.endsWith('.in_app')) return 'in_app'
  if (key.endsWith('.email')) return 'email'
  if (key.endsWith('.push')) return 'push'
  return 'other'
}

export function groupedNotificationRows<T extends { key: string }>(rows: readonly T[]): Array<{
  channel: NotificationChannel
  items: T[]
}> {
  return channelOrder.flatMap((channel) => {
    const items = rows.filter((row) => notificationChannel(row.key) === channel)
    return items.length > 0 ? [{ channel, items }] : []
  })
}

export function isExplicitConsentChannel(key: string): boolean {
  const channel = notificationChannel(key)
  return channel === 'email' || channel === 'push'
}

export function preferenceSelection(value: boolean | null): PreferenceSelection {
  return value === null ? 'inherit' : value ? 'on' : 'off'
}

export function preferenceChanged(row: NotificationPreference, draft: string | undefined): boolean {
  return (draft === 'inherit' || draft === 'on' || draft === 'off')
    && draft !== preferenceSelection(row.user_override)
}

export function policyChanged(row: NotificationPolicy, draft: NotificationPolicyDraft | undefined): boolean {
  return draft !== undefined && (draft.default_value !== row.default_value
    || draft.allow_user_override !== row.allow_user_override)
}

// Frontend guard is supplementary; the backend still validates these invariants
// with CAS revisions, PBAC and explicit consent policy validation.
export function policyCanSubmit(row: NotificationPolicy, draft: NotificationPolicyDraft | undefined): boolean {
  if (!draft || row.capability !== 'AVAILABLE' || !policyChanged(row, draft)) return false
  if (isExplicitConsentChannel(row.key) && draft.default_value) return false
  if (row.mandatory && (!draft.default_value || draft.allow_user_override)) return false
  return true
}

// Never interpolate an arbitrary server-provided source code into an i18n key.
export function preferenceSourceLabel(source: string): string {
  switch (source) {
    case 'USER': return 'sourceUser'
    case 'SYSTEM_DEFAULT': return 'sourceSystem'
    case 'SYSTEM_LOCKED': return 'sourceLocked'
    case 'MANDATORY': return 'sourceMandatory'
    case 'CHANNEL_UNAVAILABLE': return 'sourceUnavailable'
    default: return 'sourceUnknown'
  }
}
