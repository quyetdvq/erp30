import { apiClient } from '@/services/api'

export type ManagedUser = {
  id: number
  username: string
  email: string | null
  status: string | null
  active: boolean
  created_at: string | null
  groups: string[]
}

export type Group = {
  id: number
  code: string
  name: string
  description: string | null
  status: string
  permission_count: number
}

export type Organization = {
  id: number
  parent_id: number | null
  parent_name?: string | null
  type: string
  code: string
  name: string
  status: string
  active_from: string | null
  active_to: string | null
}

export type ResourceAction = {
  id: number
  resource: string
  resource_name: string
  action: string
  description: string | null
}

export async function getUsers(): Promise<ManagedUser[]> {
  const response = await apiClient.get<{ data: ManagedUser[] }>('/api/v1/admin/users')
  return response.data.data
}

export async function saveUser(payload: Record<string, unknown>, id?: number): Promise<void> {
  if (id) await apiClient.patch(`/api/v1/admin/users/${id}`, payload)
  else await apiClient.post('/api/v1/admin/users', payload)
}

export async function getGroups(): Promise<Group[]> {
  const response = await apiClient.get<{ data: Group[] }>('/api/v1/admin/groups')
  return response.data.data
}

export async function saveGroup(payload: Record<string, unknown>, id?: number): Promise<void> {
  if (id) await apiClient.patch(`/api/v1/admin/groups/${id}`, payload)
  else await apiClient.post('/api/v1/admin/groups', payload)
}

export async function getGroupPermissions(id: number): Promise<{ data: ResourceAction[]; assigned: number[] }> {
  const response = await apiClient.get<{ data: ResourceAction[]; assigned: number[] }>(`/api/v1/admin/groups/${id}/permissions`)
  return response.data
}

export async function saveGroupPermissions(id: number, resourceActionIds: number[]): Promise<void> {
  await apiClient.put(`/api/v1/admin/groups/${id}/permissions`, { resource_action_ids: resourceActionIds })
}

export async function getOrganizations(): Promise<Organization[]> {
  const response = await apiClient.get<{ data: Organization[] }>('/api/v1/admin/organizations')
  return response.data.data
}

export async function saveOrganization(payload: Record<string, unknown>, id?: number): Promise<void> {
  if (id) await apiClient.patch(`/api/v1/admin/organizations/${id}`, payload)
  else await apiClient.post('/api/v1/admin/organizations', payload)
}
