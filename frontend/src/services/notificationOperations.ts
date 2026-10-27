import { apiClient } from '@/services/api'

/** Non-sensitive, PBAC-protected E1.1 monitoring DTO. */
export type NotificationOperationsQueue = {
  available: boolean
  status_counts: Record<string, number>
  expired_leases: number
  overdue_ready_15m: number
}
export type NotificationOperationsRuntimeSwitch = {
  key: string
  section: string
  enabled: boolean
  source: 'database' | 'legacy_env_or_default'
}
export type NotificationOperationsOverview = {
  as_of_utc: string
  queues: Record<'outbox' | 'email' | 'push', NotificationOperationsQueue>
  runtime_switches: NotificationOperationsRuntimeSwitch[]
  cron: { health: 'UNKNOWN'; reason: 'TELEMETRY_UNAVAILABLE'; last_tick_at: null }
  telemetry_available: false
}
type Envelope<T> = { data: T; meta: { request_id: string } }

export async function getNotificationOperationsOverview(signal?: AbortSignal): Promise<NotificationOperationsOverview> {
  const response = await apiClient.get<Envelope<NotificationOperationsOverview>>(
    '/api/v1/notification-operations/overview', { signal },
  )
  return response.data.data
}

/** E1.2B aggregate triage. Error categories are server-defined; raw receipt errors never leave PHP. */
export type OperationsFinding = 'QUEUE_UNAVAILABLE' | 'DEAD_PRESENT' | 'EXPIRED_LEASE'
  | 'OVERDUE_60M' | 'OVERDUE_15M' | 'RETRY_READY' | 'RETRY_DELAYED'
export type OperationsErrorCategory = 'TRANSPORT' | 'LEASE_EXHAUSTED' | 'POLICY'
  | 'IDENTITY' | 'OTHER' | 'UNSPECIFIED'
export type NotificationOperationsDiagnosticsQueue = {
  available: boolean
  ready: number
  retry_ready: number
  retry_delayed: number
  dead: number
  expired_leases: number
  overdue_ready_15m: number
  overdue_ready_60m: number
  oldest_ready_minutes: number | null
  error_categories: Partial<Record<OperationsErrorCategory, number>>
  findings: OperationsFinding[]
}
export type NotificationOperationsDiagnostics = {
  as_of_utc: string
  queues: Record<'outbox' | 'email' | 'push', NotificationOperationsDiagnosticsQueue>
  cron: { health: 'UNKNOWN'; reason: 'TELEMETRY_UNAVAILABLE' }
}
export async function getNotificationOperationsDiagnostics(signal?: AbortSignal): Promise<NotificationOperationsDiagnostics> {
  const response = await apiClient.get<Envelope<NotificationOperationsDiagnostics>>(
    '/api/v1/notification-operations/diagnostics', { signal },
  )
  return response.data.data
}
