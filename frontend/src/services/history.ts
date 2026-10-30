import { apiClient } from '@/services/api'

/** HIS-01: server-owned, redacted business milestones. No History write API. */
export type BusinessHistoryEvent = {
  id: string
  event_type: 'CREATE' | 'UPDATE' | 'STATE_CHANGE' | 'SENSITIVE_CHANGE'
    | 'SUBMIT' | 'APPROVE' | 'REJECT' | 'DELETE' | 'RESTORE'
  action_code: string | null
  status_before: string | null
  status_after: string | null
  // HIS-POL-002 NEW ONLY: [old,new] or {} (redacted); no legacy JSON support.
  changes: Record<string, [string | number | boolean | null, string | number | boolean | null] | Record<string, never>> | null
  resource_code?: string | null
  resource_name?: string | null
  checkpoint: Record<string, unknown> | null
  actor_user_id: string | null
  actor_username: string | null
  actor_full_name: string | null
  resource_type: string
  resource_id: string
  request_id: string
  actor_type: string
  created_at: string
}
export type BusinessHistoryPage = {
  data: BusinessHistoryEvent[]
  meta: { page: number; page_size: number; total: number }
}

export async function customerHistory(customerId: string, page = 1, pageSize = 25): Promise<BusinessHistoryPage> {
  if (!/^[1-9][0-9]*$/.test(customerId)) throw new Error('Invalid Customer identifier')
  const result = await apiClient.get<BusinessHistoryPage>(`/api/v1/history/customers/${customerId}`, {
    params: { page, page_size: pageSize },
  })
  return result.data
}

/** Only authorized SYSTEM auditors can use this independent monitoring endpoint. */
export type BusinessHistoryMonitorFilters = {
  from: string
  to: string
  resource_type?: string
  event_type?: string
  actor_user_id?: string
  resource_id?: string
}
export async function monitorBusinessHistory(
  filters: BusinessHistoryMonitorFilters,
  page = 1,
  pageSize = 25,
): Promise<BusinessHistoryPage> {
  const result = await apiClient.get<BusinessHistoryPage>('/api/v1/history/monitor', {
    params: { ...filters, page, page_size: pageSize },
  })
  return result.data
}
