import { apiClient, invalidateCsrfToken, refreshCsrfToken } from '@/services/api'

type ApiEnvelope<T> = {
  data: T
  meta?: { request_id?: string }
}

export type AuthUser = {
  id: number
  username: string
  name: string | null
  has_avatar: boolean
  email: string | null
  active: boolean
  status: string | null
  groups: string[]
  base_permissions: string[]
}

type AuthPayload = {
  user: AuthUser | null
}

function userFromResponse(response: { data: ApiEnvelope<AuthPayload> }): AuthUser {
  const user = response.data.data.user
  if (!user) throw new Error('Authenticated response did not include a user.')
  return { ...user, name: user.name ?? null, has_avatar: user.has_avatar === true }
}

export async function login(payload: {
  email: string
  password: string
  remember: boolean
}): Promise<AuthUser> {
  const response = await apiClient.post<ApiEnvelope<AuthPayload>>('/api/v1/auth/login', payload)
  await refreshCsrfToken()
  return userFromResponse(response)
}

export async function logout(): Promise<void> {
  try {
    await apiClient.post('/api/v1/auth/logout')
  } finally {
    invalidateCsrfToken()
  }
}

export async function getCurrentUser(): Promise<AuthUser> {
  const response = await apiClient.get<ApiEnvelope<AuthPayload>>('/api/v1/auth/me')
  return userFromResponse(response)
}

export async function updateProfile(payload: {
  username: string
  email: string
  name?: string
  password?: string
}): Promise<AuthUser> {
  const response = await apiClient.patch<ApiEnvelope<AuthPayload>>('/api/v1/auth/profile', payload)
  return userFromResponse(response)
}
