import { apiClient } from '@/services/api'

export type AuthorizationProfile = {
  user_id: number
  employee_id: number | null
  organization_ids: number[]
  permissions: Record<string, string[]>
  groups: string[]
}

export type AuthorizationDecision = {
  allowed: boolean
  reason: string
  user_id: number | null
  organization_id: number
  resource_id: number | null
  resource_action_id: number | null
  state_id: number | null
  matched_policy_ids: number[]
  effects: string[]
}

export async function getAuthorizationProfile(): Promise<AuthorizationProfile> {
  const response = await apiClient.get<AuthorizationProfile>('/api/v1/authorization/profile')
  return response.data
}

export async function checkAuthorization(params: {
  resource: string
  action: string
  organizationId: number
  state: string
}): Promise<AuthorizationDecision> {
  const response = await apiClient.get<AuthorizationDecision>('/api/v1/authorization/check', {
    params: {
      resource: params.resource,
      action: params.action,
      organization_id: params.organizationId,
      state: params.state,
    },
  })

  return response.data
}
