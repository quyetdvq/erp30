import { computed, ref, type Ref } from 'vue'
import type {
  AuthorizationGroup,
  Department,
  Organization,
  Policy,
  Position,
  Resource,
  WorkflowState,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import {
  listDepartments,
  listOrganizations,
  listPositions,
  listWorkflowStates,
} from '@/api/generated/sdk.gen'
import { i18n } from '@/i18n'
import type { ListQuery } from '@/components/lists/listTypes'
import {
  getResourceActions,
  listAuthorizationGroupPage,
  listResourcePage,
  listSecurityUserPage,
  type ManagedUser,
  type SecurityAction,
} from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'
import type { PolicyDraft, ParticipantType } from './types'

type Catalog = 'resource' | 'organization' | 'group' | 'state' | 'user' | 'department' | 'position'

/**
 * Reference data is a display aid, never a PBAC decision or authorization cache.
 * The Policy list always starts first. Lookups are fetched on demand, de-duplicated
 * for the lifetime of this mounted view, and retried after failure.
 */
export function usePolicyReferences(
  form: Ref<PolicyDraft>,
  _error: Ref<unknown | null>,
  t: (key: string) => string,
) {
  const organizations = ref<Organization[]>([])
  const resources = ref<Resource[]>([])
  const groups = ref<AuthorizationGroup[]>([])
  const users = ref<ManagedUser[]>([])
  const departments = ref<Department[]>([])
  const positions = ref<Position[]>([])
  const workflowStates = ref<WorkflowState[]>([])
  const actions = ref<SecurityAction[]>([])
  const actionCatalog = ref<SecurityAction[]>([])
  const completed = new Set<Catalog>()
  const inFlight = new Map<Catalog, Promise<void>>()
  // Preserve the SDK-compatible filter type without weakening ListQuery validation.
  const listQuery = { q: '', page: 1, page_size: 200, sort: 'name', filter: {} } satisfies ListQuery

  const actionOptions = computed(() =>
    actionCatalog.value.filter((action) => String(action.resource_id) === form.value.resource_id),
  )
  const organizationNames = computed(() =>
    new Map(organizations.value.map((row) => [String(row.id), row.name])),
  )
  const resourceNames = computed(() =>
    new Map(resources.value.map((row) => [String(row.id), row.type])),
  )
  const actionCodes = computed(() =>
    new Map(actionCatalog.value.map((row) => [String(row.id), row.code])),
  )
  const groupNames = computed(() =>
    new Map(groups.value.map((row) => [String(row.id), row.name])),
  )
  const userNames = computed(() =>
    new Map(users.value.map((row) => [String(row.id), row.name || row.username])),
  )
  const departmentNames = computed(() =>
    new Map(departments.value.map((row) => [String(row.id), row.name])),
  )
  const positionNames = computed(() =>
    new Map(positions.value.map((row) => [String(row.id), row.name])),
  )
  const stateNames = computed(() =>
    new Map(workflowStates.value.map((row) => [String(row.id), row.name])),
  )

  function once(key: Catalog, fetch: () => Promise<void>): Promise<void> {
    if (completed.has(key)) return Promise.resolve()
    const running = inFlight.get(key)
    if (running) return running
    const request = fetch()
      .then(() => { completed.add(key) })
      .finally(() => { inFlight.delete(key) })
    inFlight.set(key, request)
    return request
  }
  function withHeaders() {
    return { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
  }
  function loadCatalog(key: Catalog): Promise<void> {
    return once(key, async () => {
      switch (key) {
        case 'resource': {
          const page = await listResourcePage({ ...listQuery, sort: '' }, true)
          resources.value = page.data
          actionCatalog.value = page.data.flatMap((resource) =>
            (resource.actions ?? []).map((action) => ({
              id: String(action.id),
              resource_id: String(resource.id),
              code: action.code,
              description: action.description,
            })),
          )
          break
        }
        case 'group':
          groups.value = (await listAuthorizationGroupPage(listQuery)).data
          break
        case 'user':
          users.value = (await listSecurityUserPage({ ...listQuery, sort: 'username' })).data
          break
        case 'organization': {
          const response = await withTypedClient((client) =>
            listOrganizations({ client, headers: withHeaders(), query: listQuery, throwOnError: true }),
          )
          organizations.value = response.data.data ?? []
          break
        }
        case 'department': {
          const response = await withTypedClient((client) =>
            listDepartments({ client, headers: withHeaders(), query: listQuery, throwOnError: true }),
          )
          departments.value = response.data.data ?? []
          break
        }
        case 'position': {
          const response = await withTypedClient((client) =>
            listPositions({ client, headers: withHeaders(), query: listQuery, throwOnError: true }),
          )
          positions.value = response.data.data ?? []
          break
        }
        case 'state': {
          const response = await withTypedClient((client) =>
            listWorkflowStates({ client, headers: withHeaders(), query: listQuery, throwOnError: true }),
          )
          workflowStates.value = response.data.data ?? []
          break
        }
      }
    })
  }

  /** Optional row labels load after the protected Policy page, never before it. */
  function loadListReferences(rows: readonly Policy[]): void {
    if (!rows.length) return
    const needed = new Set<Catalog>(['resource'])
    if (rows.some((row) => row.organization_id != null)) needed.add('organization')
    if (rows.some((row) => row.state_id != null)) needed.add('state')
    const participantCatalog: Record<ParticipantType, Catalog> = {
      GROUP: 'group', USER: 'user', DEPARTMENT: 'department',
      POSITION: 'position', ORGANIZATION: 'organization',
    }
    for (const row of rows) needed.add(participantCatalog[row.participant_type])
    void Promise.allSettled([...needed].map((key) => loadCatalog(key))).then((results) => {
      // A failed label lookup must not replace a successful Policy list with an error.
      for (const result of results) {
        if (result.status === 'rejected') reportApiError(result.reason)
      }
    })
  }

  /** If list labels are already loading, editor options reuse that request. */
  function waitForPendingParticipantCatalog(type: ParticipantType): Promise<void> {
    const keys: Record<ParticipantType, Catalog> = {
      GROUP: 'group', USER: 'user', DEPARTMENT: 'department',
      POSITION: 'position', ORGANIZATION: 'organization',
    }
    return inFlight.get(keys[type]) ?? Promise.resolve()
  }

  /** Required catalogs for Create/Edit; missing lookups must fail closed, not save stale IDs. */
  async function loadEditorReferences(): Promise<void> {
    await Promise.all([loadCatalog('resource'), loadCatalog('organization')])
  }
  function labelForOrganization(id: string): string {
    return organizationNames.value.get(id) ?? `#${id}`
  }
  function labelForResource(id: string): string {
    return resourceNames.value.get(id) ?? `#${id}`
  }
  function labelForState(id: string): string {
    return stateNames.value.get(id) ?? `#${id}`
  }
  function actionCodeFor(id: string): string {
    return actionCodes.value.get(id) ?? t('list.noValue')
  }
  function labelForAction(id: string): string {
    return actionCodes.value.get(id) ?? `#${id}`
  }
  function labelForParticipant(policy: Policy): string {
    const id = String(policy.participant_ref_id)
    switch (policy.participant_type) {
      case 'GROUP': return groupNames.value.get(id) ?? `#${id}`
      case 'USER': return userNames.value.get(id) ?? `#${id}`
      case 'DEPARTMENT': return departmentNames.value.get(id) ?? `#${id}`
      case 'POSITION': return positionNames.value.get(id) ?? `#${id}`
      case 'ORGANIZATION': return organizationNames.value.get(id) ?? `#${id}`
    }
  }
  function effectsSummary(policy: Policy): string {
    return policy.actions
      .map((action) => `${action.effect}:${actionCodeFor(String(action.resource_action_id))}`)
      .join(', ')
  }
  async function loadActions(resourceId: string): Promise<void> {
    if (!resourceId) {
      actions.value = []
      return
    }
    const included = resources.value.find((resource) => String(resource.id) === resourceId)?.actions
    if (included) {
      actions.value = included.map((action) => ({
        id: String(action.id), resource_id: resourceId,
        code: action.code, description: action.description,
      }))
      return
    }
    actions.value = await getResourceActions(resourceId)
    // Populate the editor's action options even if the resource wasn't on page 1.
    actionCatalog.value = [
      ...actionCatalog.value.filter((action) => String(action.resource_id) !== resourceId),
      ...actions.value,
    ]
  }

  return {
    organizations, resources, groups, users, departments, positions, workflowStates,
    actions, actionCatalog, actionOptions,
    loadListReferences, loadEditorReferences, waitForPendingParticipantCatalog, loadActions,
    labelForOrganization, labelForResource, labelForState, labelForAction, labelForParticipant, effectsSummary,
  }
}
