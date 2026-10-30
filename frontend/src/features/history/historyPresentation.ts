import type { BusinessHistoryEvent } from '@/services/history'

/** User names are resolved by backend in one page-level SQL JOIN. */
export function historyActorLabel(event: BusinessHistoryEvent, userLabel: string, systemLabel: string): string {
  if (event.actor_type !== 'USER') return systemLabel
  const username = event.actor_username?.trim() || ''
  const fullName = event.actor_full_name?.trim() || ''
  if (username && fullName) return `${username} (${fullName})`
  if (username) return username
  if (fullName) return fullName
  return event.actor_user_id ? `${userLabel} #${event.actor_user_id}` : systemLabel
}

/**
 * HIS-POL-002: only the NEW compact shape is readable.
 * A tuple contains exactly two scalar/null values. An empty JSON object
 * means redacted. Never stringify unknown objects or reinterpret legacy
 * {changed:true}/{before,after} payloads as audited values.
 */
export function historyChangeLines(event: BusinessHistoryEvent, redacted: string): string[] {
  const changes: unknown = event.changes
  if (changes === null) return []
  if (!changes || typeof changes !== 'object' || Array.isArray(changes)) {
    throw new Error('HISTORY_COMPACT_CHANGES_INVALID')
  }
  const entries = Object.entries(changes)
  if (entries.length > 100) throw new Error('HISTORY_COMPACT_CHANGES_INVALID')
  return entries.map(([field, value]) => {
    if (!/^[a-z][a-z0-9_]{0,63}$/.test(field)) {
      throw new Error('HISTORY_COMPACT_CHANGES_INVALID')
    }
    if (Array.isArray(value) && value.length === 2 && value.every(
      (item) => item === null || ['string', 'number', 'boolean'].includes(typeof item),
    )) {
      return `${field}: ${String(value[0] ?? '—')} → ${String(value[1] ?? '—')}`
    }
    if (value && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length === 0) {
      return `${field}: ${redacted}`
    }
    throw new Error('HISTORY_COMPACT_CHANGES_INVALID')
  })
}

export type HistoryChangeDisplay = { readonly lines: readonly string[]; readonly invalid: boolean }

/**
 * A bad event must not crash Vue's render function or blank the entire modal.
 * Fail CLOSED: expose no unreviewed value. Keep the event visible to authorized
 * users with an explicit format warning, so DEV can identify and purge old data.
 * This is NOT legacy decoding / migration / compatibility support.
 */
export function historyChangeDisplay(event: BusinessHistoryEvent, redacted: string): HistoryChangeDisplay {
  try {
    return { lines: historyChangeLines(event, redacted), invalid: false }
  } catch (cause) {
    if (cause instanceof Error && cause.message === 'HISTORY_COMPACT_CHANGES_INVALID') {
      return { lines: [], invalid: true }
    }
    throw cause
  }
}
