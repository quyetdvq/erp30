import { apiClient } from '@/services/api'

export type NotificationRuntimeItem = {
  key: string
  section: 'administration' | 'producers' | 'delivery' | 'email' | 'push' | 'scheduler'
  enabled: boolean
  source: 'legacy_env_or_default' | 'database'
}
export type NotificationRuntimeSnapshot = {
  revision: string
  items: NotificationRuntimeItem[]
}
type ApiData<T> = { data: T; meta: { request_id: string } }
const path = '/api/v1/configuration-settings/notifications'

export async function getNotificationRuntimeConfig(): Promise<NotificationRuntimeSnapshot> {
  const response = await apiClient.get<ApiData<NotificationRuntimeSnapshot>>(path)
  return response.data.data
}

export async function updateNotificationRuntimeConfig(
  expected_revision: string,
  changes: Array<{ key: string; enabled: boolean }>,
): Promise<NotificationRuntimeSnapshot> {
  const response = await apiClient.patch<ApiData<NotificationRuntimeSnapshot>>(path, {
    expected_revision,
    changes,
  })
  return response.data.data
}
