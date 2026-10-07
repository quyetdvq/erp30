import {
  createAuthorizationGroup,
  createPolicy,
  createResource,
  createResourceAction,
  deletePolicy,
  deleteResource,
  deleteResourceAction,
  getAuthorizationGroup,
  getAuthorizationLog,
  getPolicy,
  getResource,
  listAuthorizationGroupMembers,
  listAuthorizationGroupPermissions,
  listAuthorizationGroups,
  listAuthorizationLogs,
  listDepartments,
  listOrganizations,
  listPolicies,
  listPositions,
  listResourceActions,
  listResources,
  listWorkflowStates,
  replaceAuthorizationGroupMembers,
  replaceAuthorizationGroupPermissions,
  updateAuthorizationGroup,
  updatePolicy,
  updateResource,
  updateResourceAction,
  setAuthorizationGroupState,
} from '@/api/generated/sdk.gen'
import type {
  AuthorizationGroup,
  AuthorizationGroupCreate,
  AuthorizationGroupPatch,
  AuthorizationLog,
  Department,
  Organization,
  Policy,
  PolicyCreate,
  PolicyPatch,
  Position,
  Resource,
  ResourceActionCreate,
  ResourceActionPatch,
  WorkflowState,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { i18n } from '@/i18n'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'
import { getUsers, type ManagedUser } from '@/services/admin'
import { getCurrentUser, type AuthUser } from '@/services/auth'

export type SecurityAction = {
  id: string
  resource_id: string
  code: string
  description?: string | null
}

export type SecurityMember = {
  id: string
  username: string
  active: boolean
  status: string | null
}

export type SecurityPermission = {
  resource_action_id: string
  resource_id?: string
  resource_name?: string
  resource_type?: string
  action?: string
  effect?: 'ALLOW' | 'DENY' | 'ABSOLUTE_DENY'
  code?: string
}

function headers() {
  return { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
}

function apiQuery(query: ListQuery) {
  return {
    q: query.q || undefined,
    page: query.page,
    page_size: query.page_size,
    sort: query.sort || undefined,
    filter: Object.fromEntries(
      Object.entries(query.filter).map(([key, value]) => [
        key,
        typeof value === 'string' ? value : [...value],
      ]),
    ),
  }
}

function collection<T>(response: {
  data: { data?: T[]; meta: ListResponse<T>['meta'] }
}): ListResponse<T> {
  return { data: response.data.data ?? [], meta: response.data.meta }
}

function resource<T>(response: { data: { data?: T } }): T {
  if (!response.data.data) throw new Error('API response did not contain a resource.')
  return response.data.data
}

function dataArray<T>(response: { data: { data?: unknown } }): T[] {
  return Array.isArray(response.data.data) ? (response.data.data as T[]) : []
}

export function listAuthorizationGroupPage(
  query: ListQuery,
): Promise<ListResponse<AuthorizationGroup>> {
  return withTypedClient((client) =>
    listAuthorizationGroups({
      client,
      headers: headers(),
      query: apiQuery(query),
      throwOnError: true,
    }).then(collection),
  )
}

export async function listSecurityUserPage(query: ListQuery): Promise<ListResponse<ManagedUser>> {
  const users = await getUsers()
  const search = query.q.trim().toLowerCase()
  const filtered = users
    .filter((user) =>
      search.length === 0
        ? true
        : `${user.username} ${user.email ?? ''}`.toLowerCase().includes(search),
    )
    .sort((left, right) => {
      const direction = query.sort.startsWith('-') ? -1 : 1
      const field = query.sort.replace(/^-/, '') as 'id' | 'username' | 'email' | 'status'
      return String(left[field] ?? '').localeCompare(String(right[field] ?? '')) * direction
    })
  const start = (query.page - 1) * query.page_size
  return {
    data: filtered.slice(start, start + query.page_size),
    meta: { page: query.page, page_size: query.page_size, total: filtered.length },
  }
}

export async function getSecurityUserById(id: string): Promise<ManagedUser> {
  const user = (await getUsers()).find((candidate) => String(candidate.id) === id)
  if (!user) throw new Error('User not found.')
  return user
}

export async function saveAuthorizationGroup(
  payload: AuthorizationGroupCreate | AuthorizationGroupPatch,
  id?: string,
): Promise<AuthorizationGroup> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateAuthorizationGroup({ client, path: { id }, body: payload, throwOnError: true })
        : createAuthorizationGroup({
            client,
            body: payload as AuthorizationGroupCreate,
            throwOnError: true,
          }),
    ),
  )
}

export async function changeAuthorizationGroupState(
  id: string,
  status: 'ACTIVE' | 'INACTIVE',
): Promise<AuthorizationGroup> {
  return resource(
    await withTypedClient((client) =>
      setAuthorizationGroupState({ client, path: { id }, body: { status }, throwOnError: true }),
    ),
  )
}

export async function getAuthorizationGroupById(id: string): Promise<AuthorizationGroup> {
  return resource(
    await withTypedClient((client) =>
      getAuthorizationGroup({ client, path: { id }, throwOnError: true }),
    ),
  )
}

export async function getGroupMembers(id: string): Promise<SecurityMember[]> {
  return dataArray<SecurityMember>(
    await withTypedClient((client) =>
      listAuthorizationGroupMembers({ client, path: { id }, throwOnError: true }),
    ),
  )
}

export async function saveGroupMembers(id: string, userIds: string[]): Promise<SecurityMember[]> {
  return dataArray<SecurityMember>(
    await withTypedClient((client) =>
      replaceAuthorizationGroupMembers({
        client,
        path: { id },
        body: { user_ids: userIds },
        throwOnError: true,
      }),
    ),
  )
}

export async function getGroupPermissions(id: string): Promise<SecurityPermission[]> {
  return dataArray<SecurityPermission>(
    await withTypedClient((client) =>
      listAuthorizationGroupPermissions({ client, path: { id }, throwOnError: true }),
    ),
  )
}

export async function saveGroupPermissions(
  id: string,
  resourceActionIds: string[],
): Promise<SecurityPermission[]> {
  return dataArray<SecurityPermission>(
    await withTypedClient((client) =>
      replaceAuthorizationGroupPermissions({
        client,
        path: { id },
        body: { resource_action_ids: resourceActionIds },
        throwOnError: true,
      }),
    ),
  )
}

export function listResourcePage(query: ListQuery): Promise<ListResponse<Resource>> {
  return withTypedClient((client) =>
    listResources({ client, headers: headers(), query: apiQuery(query), throwOnError: true }).then(
      collection,
    ),
  )
}

export async function saveResource(
  payload:
    | { type: string; name: string; domain: Resource['domain'] }
    | { name?: string; lifecycle_id?: string },
  id?: string,
): Promise<Resource> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateResource({ client, path: { id }, body: payload, throwOnError: true })
        : createResource({
            client,
            body: payload as { type: string; name: string; domain: Resource['domain'] },
            throwOnError: true,
          }),
    ),
  )
}

export async function deleteResourceById(id: string): Promise<void> {
  await withTypedClient((client) => deleteResource({ client, path: { id }, throwOnError: true }))
}

export async function getResourceById(id: string): Promise<Resource> {
  return resource(
    await withTypedClient((client) => getResource({ client, path: { id }, throwOnError: true })),
  )
}

export async function getResourceActions(id: string): Promise<SecurityAction[]> {
  return dataArray<SecurityAction>(
    await withTypedClient((client) =>
      listResourceActions({ client, path: { id }, throwOnError: true }),
    ),
  )
}

export async function saveResourceAction(
  resourceId: string,
  payload: ResourceActionCreate | ResourceActionPatch,
  id?: string,
): Promise<SecurityAction> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateResourceAction({
            client,
            path: { id: resourceId, action_id: id },
            body: payload,
            throwOnError: true,
          })
        : createResourceAction({
            client,
            path: { id: resourceId },
            body: payload as ResourceActionCreate,
            throwOnError: true,
          }),
    ),
  ) as SecurityAction
}

export async function deleteResourceActionById(resourceId: string, id: string): Promise<void> {
  await withTypedClient((client) =>
    deleteResourceAction({ client, path: { id: resourceId, action_id: id }, throwOnError: true }),
  )
}

export function listPolicyPage(query: ListQuery): Promise<ListResponse<Policy>> {
  return withTypedClient((client) =>
    listPolicies({ client, headers: headers(), query: apiQuery(query), throwOnError: true }).then(
      collection,
    ),
  )
}

export async function savePolicy(
  payload: PolicyCreate | PolicyPatch,
  id?: string,
): Promise<Policy> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updatePolicy({ client, path: { id }, body: payload, throwOnError: true })
        : createPolicy({ client, body: payload as PolicyCreate, throwOnError: true }),
    ),
  )
}

export async function deletePolicyById(id: string): Promise<void> {
  await withTypedClient((client) => deletePolicy({ client, path: { id }, throwOnError: true }))
}

export async function getPolicyById(id: string): Promise<Policy> {
  return resource(
    await withTypedClient((client) => getPolicy({ client, path: { id }, throwOnError: true })),
  )
}

export function listAuthorizationLogPage(
  query: ListQuery,
): Promise<ListResponse<AuthorizationLog>> {
  return withTypedClient((client) =>
    listAuthorizationLogs({
      client,
      headers: headers(),
      query: apiQuery(query),
      throwOnError: true,
    }).then(collection),
  )
}

export async function getAuthorizationLogById(id: string): Promise<AuthorizationLog> {
  return resource(
    await withTypedClient((client) =>
      getAuthorizationLog({ client, path: { id }, throwOnError: true }),
    ),
  )
}

export async function getSecurityReferenceData(): Promise<{
  groups: AuthorizationGroup[]
  resources: Resource[]
  organizations: Organization[]
  departments: Department[]
  positions: Position[]
  workflowStates: WorkflowState[]
  currentUser: AuthUser
}> {
  const emptyQuery: ListQuery = { q: '', page: 1, page_size: 200, sort: '', filter: {} }
  const [groups, resources, organizations, departments, positions, workflowStates, currentUser] =
    await Promise.all([
      listAuthorizationGroupPage(emptyQuery),
      listResourcePage(emptyQuery),
      withTypedClient((client) =>
        listOrganizations({
          client,
          headers: headers(),
          query: apiQuery(emptyQuery),
          throwOnError: true,
        }).then(collection),
      ),
      withTypedClient((client) =>
        listDepartments({
          client,
          headers: headers(),
          query: apiQuery(emptyQuery),
          throwOnError: true,
        }).then(collection),
      ),
      withTypedClient((client) =>
        listPositions({
          client,
          headers: headers(),
          query: apiQuery(emptyQuery),
          throwOnError: true,
        }).then(collection),
      ),
      withTypedClient((client) =>
        listWorkflowStates({
          client,
          headers: headers(),
          query: apiQuery(emptyQuery),
          throwOnError: true,
        }).then(collection),
      ),
      getCurrentUser(),
    ])
  return {
    groups: groups.data,
    resources: resources.data,
    organizations: organizations.data,
    departments: departments.data,
    positions: positions.data,
    workflowStates: workflowStates.data,
    currentUser,
  }
}
