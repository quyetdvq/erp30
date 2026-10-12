import { apiClient } from '@/services/api'

export interface BusinessWorkflowOption {
  action_code: string
  status: string
}

export interface BusinessWorkflowStates {
  mode: 'NATIVE' | 'LIFECYCLE'
  initial: string | null
  states: string[]
}

export interface BusinessWorkflowOptions {
  mode: 'NATIVE' | 'LIFECYCLE'
  current: string
  options: BusinessWorkflowOption[]
}

export async function getBusinessWorkflowStates(resource: string): Promise<BusinessWorkflowStates> {
  const response = await apiClient.get<{ data: BusinessWorkflowStates }>(
    `/api/v1/business-workflow/${encodeURIComponent(resource)}/states`,
  )
  return response.data.data
}

// The server's resource allowlist is authoritative. Never assemble table or
// authorization details in the UI from this route parameter.
export async function getBusinessWorkflowOptions(
  resource: string,
  targetId: string,
): Promise<BusinessWorkflowOptions> {
  const response = await apiClient.get<{ data: BusinessWorkflowOptions }>(
    `/api/v1/business-workflow/${encodeURIComponent(resource)}/${encodeURIComponent(targetId)}/options`,
  )
  return response.data.data
}

const nativeSetStatePaths: Readonly<Record<string, string>> = {
  province: 'provinces',
  ward: 'wards',
  organization: 'organizations',
  department: 'departments',
  position: 'positions',
  employee: 'employees',
  product_group: 'product-groups',
  product_uom: 'product-uoms',
  product: 'products',
  price_list: 'price-lists',
  organization_contact: 'contacts',
  employee_contact: 'contacts',
}

export async function executeBusinessWorkflowOption(
  resource: string,
  targetId: string,
  mode: BusinessWorkflowOptions['mode'],
  option: BusinessWorkflowOption,
): Promise<void> {
  const id = encodeURIComponent(targetId)
  if (mode === 'LIFECYCLE') {
    await apiClient.post(
      `/api/v1/business-workflow/${encodeURIComponent(resource)}/${id}/actions/${encodeURIComponent(option.action_code)}`,
      {},
    )
    return
  }
  const path = nativeSetStatePaths[resource]
  if (!path || option.action_code !== 'setstate') {
    throw new Error('Unsupported native state transition.')
  }
  await apiClient.post(`/api/v1/${path}/${id}/actions/set-state`, { status: option.status })
}
