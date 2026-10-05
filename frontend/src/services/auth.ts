import { apiClient } from '@/services/api'

export type AuthUser = {
  id: number
  username: string
  email: string | null
  active: boolean
  status: string | null
  groups: string[]
}

export async function login(payload: {
  email: string
  password: string
  remember: boolean
}): Promise<AuthUser> {
  const response = await apiClient.post<{ user: AuthUser }>('/api/v1/auth/login', payload)
  return response.data.user
}

export async function logout(): Promise<void> {
  await apiClient.post('/api/v1/auth/logout')
}

export async function getCurrentUser(): Promise<AuthUser> {
  const response = await apiClient.get<{ user: AuthUser }>('/api/v1/auth/me')
  return response.data.user
}

export async function updateProfile(payload: {
  username: string
  email: string
  password?: string
}): Promise<AuthUser> {
  const response = await apiClient.patch<{ user: AuthUser }>('/api/v1/auth/profile', payload)
  return response.data.user
}
