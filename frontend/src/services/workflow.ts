import {
  configureLifecycleStates,
  configureLifecycleTransitions,
  createLifecycle,
  createWorkflowState,
  listLifecycleStates,
  listLifecycleTransitions,
  listLifecycles,
  listWorkflowStates,
  setLifecycleState,
  setWorkflowStateState,
  updateLifecycle,
  updateWorkflowState,
} from '@/api/generated/sdk.gen'
import type {
  Lifecycle,
  LifecycleConfiguration,
  LifecycleCreate,
  LifecyclePatch,
  WorkflowState,
  WorkflowStateCreate,
  WorkflowStatePatch,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { i18n } from '@/i18n'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'

export type LifecycleStateMembership = {
  id: string
  lifecycle_id: string
  state_id: string
  sequence: number
  is_initial: boolean | number | string
  is_final: boolean | number | string
}

export type LifecycleTransition = {
  id: string
  lifecycle_id: string
  from_state_id: string
  to_state_id: string
  action_code: string
  status: 'ACTIVE' | 'INACTIVE' | string
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

export function listWorkflowStatePage(query: ListQuery): Promise<ListResponse<WorkflowState>> {
  return withTypedClient((client) =>
    listWorkflowStates({
      client,
      headers: headers(),
      query: apiQuery(query),
      throwOnError: true,
    }).then(collection),
  )
}

export async function saveWorkflowState(
  payload: WorkflowStateCreate | WorkflowStatePatch,
  id?: string,
): Promise<WorkflowState> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateWorkflowState({ client, path: { id }, body: payload, throwOnError: true })
        : createWorkflowState({
            client,
            body: payload as WorkflowStateCreate,
            throwOnError: true,
          }),
    ),
  )
}

export async function changeWorkflowState(
  id: string,
  status: 'ACTIVE' | 'INACTIVE',
): Promise<WorkflowState> {
  return resource(
    await withTypedClient((client) =>
      setWorkflowStateState({ client, path: { id }, body: { status }, throwOnError: true }),
    ),
  )
}

export function listLifecyclePage(query: ListQuery): Promise<ListResponse<Lifecycle>> {
  return withTypedClient((client) =>
    listLifecycles({
      client,
      headers: headers(),
      query: apiQuery(query),
      throwOnError: true,
    }).then(collection),
  )
}

export async function saveLifecycle(
  payload: LifecycleCreate | LifecyclePatch,
  id?: string,
): Promise<Lifecycle> {
  return resource(
    await withTypedClient((client) =>
      id
        ? updateLifecycle({ client, path: { id }, body: payload, throwOnError: true })
        : createLifecycle({
            client,
            body: payload as LifecycleCreate,
            throwOnError: true,
          }),
    ),
  )
}

export async function changeLifecycleState(
  id: string,
  status: 'ACTIVE' | 'INACTIVE',
): Promise<Lifecycle> {
  return resource(
    await withTypedClient((client) =>
      setLifecycleState({ client, path: { id }, body: { status }, throwOnError: true }),
    ),
  )
}

export async function getLifecycleStates(id: string): Promise<LifecycleStateMembership[]> {
  return dataArray<LifecycleStateMembership>(
    await withTypedClient((client) =>
      listLifecycleStates({ client, path: { id }, throwOnError: true }),
    ),
  )
}

export async function getLifecycleTransitions(id: string): Promise<LifecycleTransition[]> {
  return dataArray<LifecycleTransition>(
    await withTypedClient((client) =>
      listLifecycleTransitions({ client, path: { id }, throwOnError: true }),
    ),
  )
}

export async function addLifecycleState(
  id: string,
  state: Omit<LifecycleStateMembership, 'id' | 'lifecycle_id'>,
): Promise<unknown> {
  const body: LifecycleConfiguration = {
    states: [
      {
        state_id: state.state_id,
        sequence: state.sequence,
        is_initial: state.is_initial,
        is_final: state.is_final,
      },
    ],
  }
  return withTypedClient((client) =>
    configureLifecycleStates({ client, path: { id }, body, throwOnError: true }),
  )
}

export async function addLifecycleTransition(
  id: string,
  transition: Omit<LifecycleTransition, 'id' | 'lifecycle_id'>,
): Promise<unknown> {
  const body: LifecycleConfiguration = {
    transitions: [
      {
        lifecycle_id: id,
        from_state_id: transition.from_state_id,
        to_state_id: transition.to_state_id,
        action_code: transition.action_code,
        status: transition.status,
      },
    ],
  }
  return withTypedClient((client) =>
    configureLifecycleTransitions({ client, path: { id }, body, throwOnError: true }),
  )
}
