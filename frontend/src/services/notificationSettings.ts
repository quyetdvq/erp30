import { apiClient } from '@/services/api'

// Mirrors contracts/openapi/openapi.yaml until SDK generation runs in the project environment.
export type NotificationPolicy = {
  key: string
  default_value: boolean
  allow_user_override: boolean
  mandatory: boolean
  capability: 'AVAILABLE' | 'UNAVAILABLE'
  producer_state: 'INACTIVE' | 'ACTIVE'
  revision: string
  override_epoch: string
}
export type NotificationPreference = {
  key: string
  system_default: boolean
  allow_user_override: boolean
  user_override: boolean | null
  effective_value: boolean
  effective_source: string
  override_epoch: string
  user_revision: string
  locked: boolean
  capability: 'AVAILABLE' | 'UNAVAILABLE'
  producer_state: 'INACTIVE' | 'ACTIVE'
}
export type NotificationPolicyChange = Pick<NotificationPolicy, 'key' | 'default_value' | 'allow_user_override'> & {
  expected_revision: string
}
export type NotificationPreferenceChange = {
  key: string
  value: boolean | null
  expected_override_epoch: string
  expected_user_revision: string
}
type ApiData<T> = { data: T; meta: { request_id: string } }
export async function getNotificationPolicies(): Promise<NotificationPolicy[]> {
  const response = await apiClient.get<ApiData<NotificationPolicy[]>>('/api/v1/notification-settings')
  return response.data.data
}
export async function patchNotificationPolicies(changes: NotificationPolicyChange[]): Promise<NotificationPolicy[]> {
  const response = await apiClient.patch<ApiData<NotificationPolicy[]>>('/api/v1/notification-settings', { changes })
  return response.data.data
}
export async function getNotificationPreferences(): Promise<NotificationPreference[]> {
  const response = await apiClient.get<ApiData<NotificationPreference[]>>('/api/v1/notifications/preferences')
  return response.data.data
}
export async function patchNotificationPreferences(changes: NotificationPreferenceChange[]): Promise<NotificationPreference[]> {
  const response = await apiClient.patch<ApiData<NotificationPreference[]>>('/api/v1/notifications/preferences', { changes })
  return response.data.data
}
