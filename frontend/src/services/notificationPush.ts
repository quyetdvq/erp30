import { apiClient } from '@/services/api'

type ApiData<T> = { data: T; meta: { request_id: string } }
export type PushConfig = {
  available: boolean
  public_key: string | null
  queue_enabled: boolean
  send_enabled: boolean
}
export type PushDevice = { id: string; created_at: string; detail_ready: boolean }
const base = '/api/v1/notifications/push'

export async function getPushConfig(): Promise<PushConfig> {
  const response = await apiClient.get<ApiData<PushConfig>>(`${base}/config`)
  return response.data.data
}
export async function getPushDevices(): Promise<PushDevice[]> {
  const response = await apiClient.get<ApiData<PushDevice[]>>(`${base}/subscriptions`)
  return response.data.data
}
export async function registerPushSubscription(subscription: PushSubscription): Promise<PushDevice> {
  const json = subscription.toJSON()
  if (!json.endpoint || !json.keys?.p256dh || !json.keys.auth) {
    throw new Error('INVALID_PUSH_SUBSCRIPTION')
  }
  const response = await apiClient.post<ApiData<PushDevice>>(`${base}/subscriptions`, {
    endpoint: json.endpoint,
    keys: { p256dh: json.keys.p256dh, auth: json.keys.auth },
  })
  return response.data.data
}
export async function revokePushDevice(id: string): Promise<void> {
  await apiClient.delete(`${base}/subscriptions/${encodeURIComponent(id)}`)
}
export function decodeVapidPublicKey(input: string): Uint8Array {
  if (!/^[A-Za-z0-9_-]+$/.test(input)) throw new Error('INVALID_VAPID_KEY')
  const b64 = input.replace(/-/g, '+').replace(/_/g, '/')
  const bytes = atob(b64.padEnd(Math.ceil(b64.length / 4) * 4, '='))
  const result = Uint8Array.from(bytes, (c) => c.charCodeAt(0))
  if (result.length !== 65 || result[0] !== 4) throw new Error('INVALID_VAPID_KEY')
  return result
}
