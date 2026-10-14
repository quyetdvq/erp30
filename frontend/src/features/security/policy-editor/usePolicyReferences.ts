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

/** Owns policy reference catalogues and their display labels; no PBAC evaluation occurs here. */
export function usePolicyReferences(
  form: Ref<PolicyDraft>,
  error: Ref<unknown | null>,
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

  const actionOptions = computed(() =>
    actionCatalog.value.filter((action) => String(action.resource_id) === form.value.resource_id),
  )
  function labelForOrganization(id: string): string {
    return organizations.value.find((item) => String(item.id) === id)?.name ?? `#${id}`
  }
  function labelForResource(id: string): string {
    return resources.value.find((item) => String(item.id) === id)?.type ?? `#${id}`
  }
  function actionCodeFor(id: string): string {
    return actionCatalog.value.find((item) => String(item.id) === id)?.code ?? t('list.noValue')
  }
  function labelForParticipant(policy: Policy): string {
    const found = participantOptionsFor(policy.participant_type, String(policy.participant_ref_id))
    return found || `#${policy.participant_ref_id}`
  }
  function participantOptionsFor(type: ParticipantType, id: string): string {
    if (type === 'GROUP') {
      const group = groups.value.find((candidate) => String(candidate.id) === id)
      return group?.name ?? ''
    }
    if (type === 'USER') {
      const user = users.value.find((candidate) => String(candidate.id) === id)
      return user?.name || user?.username || ''
    }
    if (type === 'POSITION') {
      return positions.value.find((candidate) => String(candidate.id) === id)?.name ?? ''
    }
    if (type === 'DEPARTMENT') {
      return departments.value.find((candidate) => String(candidate.id) === id)?.name ?? ''
    }
    return organizations.value.find((candidate) => String(candidate.id) === id)?.name ?? ''
  }
  function effectsSummary(policy: Policy): string {
    return policy.actions
      .map((action) => `${action.effect}:${actionCodeFor(String(action.resource_action_id))}`)
      .join(', ')
  }
  async function loadReferences(): Promise<void> {
    const emptyQuery: ListQuery = { q: '', page: 1, page_size: 200, sort: '', filter: {} }
    const headers = { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
    try {
      const [resourcePage, groupPage, organizationPage, statePage] = await Promise.all([
        listResourcePage(emptyQuery, true),
        listAuthorizationGroupPage(emptyQuery),
        withTypedClient((client) =>
          listOrganizations({
            client,
            headers,
            query: { page: 1, page_size: 200, sort: 'name', filter: {} },
            throwOnError: true,
          }),
        ).then((response) => ({ data: response.data.data ?? [] })),
        withTypedClient((client) =>
          listWorkflowStates({
            client,
            headers,
            query: { page: 1, page_size: 200, sort: 'name', filter: {} },
            throwOnError: true,
          }),
        ).then((response) => ({ data: response.data.data ?? [] })),
      ])
      resources.value = resourcePage.data
      actionCatalog.value = resourcePage.data.flatMap((resource) =>
        (resource.actions ?? []).map((action) => ({
          id: String(action.id),
          resource_id: String(resource.id),
          code: action.code,
          description: action.description,
        })),
      )
      groups.value = groupPage.data
      organizations.value = organizationPage.data as Organization[]
      workflowStates.value = statePage.data as WorkflowState[]
      const [userResult, departmentResult, positionResult] = await Promise.allSettled([
        listSecurityUserPage({ ...emptyQuery, sort: 'username' }),
        withTypedClient((client) =>
          listDepartments({
            client,
            headers,
            query: { page: 1, page_size: 200, sort: 'name', filter: {} },
            throwOnError: true,
          }),
        ).then((response) => ({ data: response.data.data ?? [] })),
        withTypedClient((client) =>
          listPositions({
            client,
            headers,
            query: { page: 1, page_size: 200, sort: 'name', filter: {} },
            throwOnError: true,
          }),
        ).then((response) => ({ data: response.data.data ?? [] })),
      ])
      if (userResult.status === 'fulfilled') users.value = userResult.value.data
      if (departmentResult.status === 'fulfilled') {
        departments.value = departmentResult.value.data as Department[]
      }
      if (positionResult.status === 'fulfilled') {
        positions.value = positionResult.value.data as Position[]
      }
    } catch (caught: unknown) {
      error.value = caught
      reportApiError(caught)
    }
  }
  async function loadActions(resourceId: string): Promise<void> {
    if (!resourceId) {
      actions.value = []
      return
    }
    const included = resources.value.find((resource) => String(resource.id) === resourceId)?.actions
    if (included) {
      actions.value = included.map((action) => ({
        id: String(action.id),
        resource_id: resourceId,
        code: action.code,
        description: action.description,
      }))
      return
    }
    actions.value = await getResourceActions(resourceId)
  }

  return {
    organizations,
    resources,
    groups,
    users,
    departments,
    positions,
    workflowStates,
    actions,
    actionCatalog,
    actionOptions,
    loadReferences,
    loadActions,
    labelForOrganization,
    labelForResource,
    labelForParticipant,
    effectsSummary,
  }
}
