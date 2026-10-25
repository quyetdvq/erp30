import {
  createAuthorizationGroup,
  createPolicy,
  createResource,
  createResourceAction,
  createUser,
  deletePolicy,
  deleteResource,
  deleteResourceAction,
  getAuthorizationGroup,
  getAuthorizationLog,
  getPolicy,
  getResource,
  getUserPermissions,
  listAuthorizationGroupMembers,
  listAuthorizationGroupPermissions,
  listAuthorizationGroups,
  listAuthorizationLogs,
  listSecurityUsers,
  listDepartments,
  listOrganizations,
  listPolicies,
  listPositions,
  listResourceActions,
  listResources,
  listWorkflowStates,
  replaceAuthorizationGroupMembers,
  replaceAuthorizationGroupPermissions,
  replaceUserPermissions,
  updateAuthorizationGroup,
  updatePolicy,
  updateResource,
  updateResourceAction,
  updateUser,
  setAuthorizationGroupState,
  setUserState,
  resetUserPassword,
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
  SecurityUser,
  UserIdentity,
  UserIdentityCreateWritable,
  UserIdentityPatch,
  UserIdentityPasswordResetWritable,
  WorkflowState,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { apiClient } from '@/services/api'
import { i18n } from '@/i18n'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'
import { getCurrentUser, type AuthUser } from '@/services/auth'
import { rowAllowedActions, type AllowedRowActions } from '@/components/lists/rowActionPolicy'
import { userGroupIds } from '@/features/security/user-editor/userMembership'

export type ManagedUser = {
  id: number | string
  username: string
  name?: string | null
  has_avatar?: boolean
  avatar_url?: string | null
  email: string | null
  status: string | null
  active: boolean
  created_at: string | null
  groups: string[]
  groupIds?: string[]
  direct_permission_count?: number
  allowed_actions?: AllowedRowActions | null
}

export type SecurityAction = {
  id: string
  resource_id: string
  code: string
  description?: string | null
}

export type SecurityActionSummary = {
  id: string
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

export type UserState = 'ACTIVE' | 'INACTIVE' | 'LOCKED' | 'SUSPENDED' | 'BANNED' | 'DISABLED'

export type ResourceStateOption = { id: string; code: string; name: string }
export type ResourceAvailableStates = {
  mode: 'LIFECYCLE' | 'NATIVE' | 'STATELESS'
  states: ResourceStateOption[]
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

function mapSecurityUser(user: SecurityUser): ManagedUser {
  return {
    id: user.id,
    username: user.username,
    name: user.name,
    has_avatar: user.has_avatar,
    avatar_url: user.avatar_url ?? null,
    email: user.email,
    status: user.status,
    active: user.active,
    created_at: user.created_at,
    groups: user.groups.map((group) => group.name ?? group.code),
    groupIds: userGroupIds(user.groups),
    direct_permission_count: user.direct_permission_count,
    allowed_actions: rowAllowedActions(user),
  }
}

export async function listSecurityUserPage(query: ListQuery): Promise<ListResponse<ManagedUser>> {
  return withTypedClient((client) =>
    listSecurityUsers({
      client,
      headers: headers(),
      query: apiQuery(query),
      throwOnError: true,
    }).then((response) => {
      const page = collection(response)
      return { ...page, data: page.data.map(mapSecurityUser) }
    }),
  )
}

export async function saveSecurityUser(
  payload: UserIdentityCreateWritable | UserIdentityPatch,
  id?: string,
): Promise<UserIdentity> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateUser({
            client,
            path: { id },
            body: payload as UserIdentityPatch,
            throwOnError: true,
          })
        : createUser({ client, body: payload as UserIdentityCreateWritable, throwOnError: true }),
    ),
  )
}

export async function changeSecurityUserState(id: string, state: UserState): Promise<UserIdentity> {
  return resource(
    await withTypedClient((client) =>
      setUserState({ client, path: { id }, body: { state }, throwOnError: true }),
    ),
  )
}

export async function resetSecurityUserPassword(
  id: string,
  newPassword: string,
): Promise<UserIdentity> {
  const body: UserIdentityPasswordResetWritable = { new_password: newPassword }
  return resource(
    await withTypedClient((client) =>
      resetUserPassword({ client, path: { id }, body, throwOnError: true }),
    ),
  )
}

export async function getSecurityUserPermissions(id: string): Promise<string[]> {
  const response = await withTypedClient((client) =>
    getUserPermissions({ client, path: { id }, throwOnError: true }),
  )
  return response.data.data?.permissions ?? []
}

export async function saveSecurityUserPermissions(
  id: string,
  permissions: string[],
): Promise<string[]> {
  const response = await withTypedClient((client) =>
    replaceUserPermissions({ client, path: { id }, body: { permissions }, throwOnError: true }),
  )
  return response.data.data?.permissions ?? []
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

export function listResourcePage(
  query: ListQuery,
  includeActions = false,
): Promise<ListResponse<Resource>> {
  return withTypedClient((client) =>
    listResources({
      client,
      headers: headers(),
      query: { ...apiQuery(query), include: includeActions ? 'actions' : undefined },
      throwOnError: true,
    }).then(collection),
  )
}

export async function saveResource(
  payload:
    | { type: string; name: string; domain: Resource['domain'] }
    | { name?: string; lifecycle_id?: string | null },
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

/** Canonical API transport; the OpenAPI operation is declared in the contract. */
export async function changeResourceState(
  id: string,
  status: 'ACTIVE' | 'INACTIVE',
): Promise<void> {
  await apiClient.post(
    `/api/v1/resources/${encodeURIComponent(id)}/actions/set-state`,
    { status },
    { headers: headers() },
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

/**
 * Match server-authorized State codes to the existing Workflow State catalog.
 * Policy.state_id is the catalog ID, never the business status string.
 * If the catalog is inconsistent or incomplete, fail closed instead of
 * silently replacing an existing State-scoped Policy with All States.
 */
export async function getResourceAvailableStates(id: string): Promise<ResourceAvailableStates> {
  const target = await getResourceById(id)
  const codes = target.policy_state_codes
  if (
    !Array.isArray(codes) ||
    codes.some((code) => typeof code !== 'string' || code.trim() === '') ||
    new Set(codes).size !== codes.length
  ) {
    throw new Error('Resource does not provide a valid PBAC State catalog.')
  }

  const mode = target.lifecycle_id ? 'LIFECYCLE' : codes.length ? 'NATIVE' : 'STATELESS'
  if (codes.length === 0) return { mode, states: [] }

  const allowed = new Set(codes)
  const found = new Map<string, ResourceStateOption>()
  const pageSize = 200
  let page = 1
  while (found.size < allowed.size) {
    const response = await withTypedClient((client) =>
      listWorkflowStates({
        client,
        headers: headers(),
        query: { page, page_size: pageSize, sort: 'code', filter: {} },
        throwOnError: true,
      }),
    )
    const currentPage = response.data.data ?? []
    for (const state of currentPage) {
      if (allowed.has(state.code) && state.status === 'ACTIVE') {
        found.set(state.code, { id: String(state.id), code: state.code, name: state.name })
      }
    }
    if (page * pageSize >= response.data.meta.total || currentPage.length === 0) break
    page += 1
  }

  if (found.size !== allowed.size) {
    throw new Error('PBAC State codes are missing or inactive in the Workflow State catalog.')
  }
  const states = codes.map((code) => {
    const match = found.get(code)
    if (!match) throw new Error('PBAC State selection is unavailable.')
    return match
  })
  return { mode, states }
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

export async function changePolicyState(id: string, status: 'ACTIVE' | 'INACTIVE'): Promise<void> {
  await apiClient.post(
    `/api/v1/policies/${encodeURIComponent(id)}/actions/set-state`,
    { status },
    { headers: headers() },
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
