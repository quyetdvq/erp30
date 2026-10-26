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
type Envelope<T> = { data: T; meta: { request_id: string } }

export async function getInboxSummary(signal?: AbortSignal): Promise<number> {
  const response = await apiClient.get<Envelope<{ unread_count: number }>>('/api/v1/notifications/summary', { signal })
  return response.data.data.unread_count
}
export async function getInboxPage(
  filter: 'all' | 'unread', cursor: string | null = null, signal?: AbortSignal,
): Promise<InboxPage> {
  const response = await apiClient.get<Envelope<InboxPage>>('/api/v1/notifications', {
    params: { filter, page_size: '20', ...(cursor ? { cursor } : {}) }, signal,
  })
  return response.data.data
}
export async function markInboxRead(id: string): Promise<void> {
  await apiClient.post(`/api/v1/notifications/${encodeURIComponent(id)}/actions/mark-read`)
}
export async function markInboxAllRead(): Promise<void> {
  await apiClient.post('/api/v1/notifications/actions/mark-all-read')
}
export function notificationTarget(item: InboxNotification): string | null {
  // Trusted allowlist, never accept server-supplied URLs or arbitrary target types.
  if (item.target.type === 'import_job' && /^[1-9]\d*$/.test(item.target.id)) {
    return `/products/import-jobs/${item.target.id}`
  }
  if (item.target.type === 'workflow_target' && /^[1-9]\d*$/.test(item.target.id)) {
    // Trusted resource-to-route allowlist. Destination pages recheck PBAC.
    const paths: Record<string, string> = {
      product: `/products/${item.target.id}`,
      product_group: '/products/groups', product_uom: '/products/uoms',
      price_list: '/products/price-lists', organization: '/organizations',
      province: '/settings/locations/provinces', ward: '/settings/locations/wards',
      department: '/people/departments', position: '/people/positions',
      employee: '/people/employees', organization_contact: '/organizations',
      employee_contact: '/people/employees',
    }
    return paths[item.target.resource_type] ?? null
  }
  return null
}
