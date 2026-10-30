import { apiClient } from '@/services/api'

// This is a read model of notification recipients with live Workflow actions.
// It is NOT task ownership, a due-date ledger, or an approval mutation API.
export const myWorkflowTypes = [
  'all', 'province', 'ward', 'organization', 'department', 'position', 'employee',
  'product_group', 'product_uom', 'product', 'price_list',
  'organization_contact', 'employee_contact', 'customer', 'customer_contact', 'supplier',
] as const
export type MyWorkflowType = (typeof myWorkflowTypes)[number]

export interface MyWorkflowAction {
  action_code: string
  status: string
}
export interface MyWorkflowItem {
  resource_type: string
  resource_id: string
  current_state: string
  /** Optional additive fields from a target already checked by Workflow PBAC. */
  record_name?: string | null
  record_code?: string | null
  available_actions: MyWorkflowAction[]
  selected_at: string
}
export interface MyWorkflowPage {
  items: MyWorkflowItem[]
  next_cursor: string | null
  execution_enabled: boolean
}
type Envelope<T> = { data: T; meta: { request_id: string } }

export async function getMyWorkflowPage(
  resourceType: MyWorkflowType = 'all', cursor: string | null = null, signal?: AbortSignal,
): Promise<MyWorkflowPage> {
  const response = await apiClient.get<Envelope<MyWorkflowPage>>('/api/v1/my-work', {
    params: { resource_type: resourceType, page_size: '10', ...(cursor ? { cursor } : {}) },
    signal,
  })
  return response.data.data
}
