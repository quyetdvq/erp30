const DATE_TIME_LOCAL_PATTERN = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})$/
const SQL_DATE_TIME_PATTERN = /^(\d{4}-\d{2}-\d{2}) (\d{2}):(\d{2}):\d{2}(?:\.\d+)?$/
const RFC3339_DATE_TIME_PATTERN =
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/

/** Convert API timestamps to the value required by a datetime-local input. */
export function toDateTimeLocal(value: string | null | undefined): string {
  const raw = value?.trim() ?? ''
  if (!raw) return ''

  const sqlMatch = SQL_DATE_TIME_PATTERN.exec(raw)
  if (sqlMatch) return `${sqlMatch[1]}T${sqlMatch[2]}:${sqlMatch[3]}`
  if (!RFC3339_DATE_TIME_PATTERN.test(raw)) return ''

  const parsed = new Date(raw)
  return Number.isNaN(parsed.getTime()) ? '' : parsed.toISOString().slice(0, 16)
}

/** Convert a datetime-local input to the canonical RFC3339 UTC payload. */
export function toApiDateTime(value: string): string {
  const match = DATE_TIME_LOCAL_PATTERN.exec(value.trim())
  return match ? `${match[1]}T${match[2]}:${match[3]}:00Z` : ''
}
