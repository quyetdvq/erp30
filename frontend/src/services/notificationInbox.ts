import { apiClient } from '@/services/api'

export type InboxNotification = {
  id: string
  type: 'import.completed' | 'import.completed_with_errors' | 'import.failed' | 'workflow.action_required'
  priority: 'NORMAL'
  title_key: string
  body_key: string
  params: { success_rows: number; error_rows: number } | { resource_type: string; state_code: string }
  target: { type: 'import_job'; id: string } | { type: 'workflow_target'; resource_type: string; id: string }
  read_at: string | null
  created_at: string
}
export type InboxPage = { items: InboxNotification[]; next_cursor: string | null }
export type InboxEventGroup = 'all' | 'import' | 'workflow'
export type InboxAdvancedFilters = { eventGroup: InboxEventGroup; from: string | null; to: string | null }
type Envelope<T> = { data: T; meta: { request_id: string } }

export async function getInboxSummary(signal?: AbortSignal): Promise<number> {
  const response = await apiClient.get<Envelope<{ unread_count: number }>>('/api/v1/notifications/summary', { signal })
  return response.data.data.unread_count
}
export async function getInboxPage(
  filter: 'all' | 'unread', cursor: string | null = null, signal?: AbortSignal, pageSize: 5 | 20 = 20,
  advanced?: InboxAdvancedFilters,
): Promise<InboxPage> {
  const extended = advanced && (advanced.eventGroup !== 'all' || advanced.from !== null)
  const response = await apiClient.get<Envelope<InboxPage>>('/api/v1/notifications', {
    params: {
      filter, page_size: String(pageSize), ...(cursor ? { cursor } : {}),
      ...(advanced && extended ? {
        event_group: advanced.eventGroup,
        ...(advanced.from !== null && advanced.to !== null ? { from: advanced.from, to: advanced.to } : {}),
      } : {}),
    }, signal,
  })
  return response.data.data
}
export async function markInboxRead(id: string): Promise<void> {
  await apiClient.post(`/api/v1/notifications/${encodeURIComponent(id)}/actions/mark-read`)
}
export async function markInboxAllRead(): Promise<void> {
  await apiClient.post('/api/v1/notifications/actions/mark-all-read')
}
export type NotificationDestination = {
  path: string
  kind: 'detail' | 'list'
}

// This is a fixed set of application routes, not a URL derived from notification payload.
// A destination only selects where to navigate; the destination API rechecks PBAC.
const workflowListRoutes = new Map<string, string>([
  ['product_group', '/products/groups'],
  ['product_uom', '/products/uoms'],
  ['price_list', '/products/price-lists'],
  ['organization', '/organizations'],
  ['province', '/settings/locations/provinces'],
  ['ward', '/settings/locations/wards'],
  ['department', '/people/departments'],
  ['position', '/people/positions'],
  ['employee', '/people/employees'],
  ['organization_contact', '/organizations'],
  ['employee_contact', '/people/employees'],
])

/** Fixed internal destinations. An owning page must still authorize the target at request time. */
export function workflowDestination(resourceType: string, id: string): NotificationDestination | null {
  if (!/^[1-9][0-9]{0,19}$/.test(id)) return null
  if (resourceType === 'product') return { path: `/products/${id}`, kind: 'detail' }
  const path = workflowListRoutes.get(resourceType)
  return path ? { path, kind: 'list' } : null
}

export function notificationDestination(item: InboxNotification): NotificationDestination | null {
  const id = item.target.id
  if (!/^[1-9][0-9]{0,19}$/.test(id)) return null
  if (item.target.type === 'import_job') {
    return { path: `/products/import-jobs/${id}`, kind: 'detail' }
  }
  if (item.target.type !== 'workflow_target') return null
  return workflowDestination(item.target.resource_type, id)
}

/** Backwards-compatible path-only helper for existing callers. */
export function notificationTarget(item: InboxNotification): string | null {
  return notificationDestination(item)?.path ?? null
}
