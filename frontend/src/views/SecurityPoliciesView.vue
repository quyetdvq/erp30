<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon, NSelect } from 'naive-ui'
import { TrashOutline } from '@vicons/ionicons5'
import type { Policy, PolicyAction, Resource } from '@/api/generated/types.gen'
import OrganizationTreeSelect from '@/components/organizations/OrganizationTreeSelect.vue'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import {
  getResourceActions,
  listAuthorizationGroupPage,
  listPolicyPage,
  listResourcePage,
  listSecurityUserPage,
  savePolicy,
  type ManagedUser,
  type SecurityAction,
} from '@/services/security'
import { withTypedClient } from '@/api/client'
import {
  listDepartments,
  listOrganizations,
  listPositions,
  listWorkflowStates,
} from '@/api/generated/sdk.gen'
import type {
  Department,
  Organization,
  Position,
  WorkflowState,
  AuthorizationGroup,
} from '@/api/generated/types.gen'
import { i18n } from '@/i18n'
import { normalizeApiError, reportApiError } from '@/services/errorBoundary'
import { useBasePermission } from '@/composables/useBasePermission'
import { getLifecycleStates, type LifecycleStateMembership } from '@/services/workflow'

type ParticipantType = Policy['participant_type']
type PolicyDraftAction = { resource_action_id: string; effect: PolicyAction['effect'] }
type ParticipantOption = { id: string; label: string }
const participantTypes: ParticipantType[] = [
  'USER',
  'GROUP',
  'POSITION',
  'DEPARTMENT',
  'ORGANIZATION',
]
const effects: PolicyAction['effect'][] = ['ALLOW', 'DENY', 'ABSOLUTE_DENY']
const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<Policy>((query) => listPolicyPage(query), { sort: 'id' })
const selectedId = ref<string | null>(null)
const modalOpen = ref(false)
const editingId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const duplicateScopeConflict = ref(false)
const organizations = ref<Organization[]>([])
const resources = ref<Resource[]>([])
const groups = ref<AuthorizationGroup[]>([])
const users = ref<ManagedUser[]>([])
const departments = ref<Department[]>([])
const positions = ref<Position[]>([])
const workflowStates = ref<WorkflowState[]>([])
const actions = ref<SecurityAction[]>([])
const actionCatalog = ref<SecurityAction[]>([])
const participantOptions = ref<ParticipantOption[]>([])
const participantLoading = ref(false)
const lifecycleStateMemberships = ref<LifecycleStateMembership[]>([])
const workflowStatesLoading = ref(false)
const lifecycleStatesById = new Map<string, LifecycleStateMembership[]>()
let participantRequestId = 0
let workflowStateRequestId = 0
let participantSearchTimer: ReturnType<typeof setTimeout> | undefined
const form = ref({
  organization_id: '',
  resource_id: '',
  state_id: '',
  participant_type: 'USER' as ParticipantType,
  participant_ref_id: '',
  actions: [] as PolicyDraftAction[],
})
const { markClean, confirmDiscard } = useUnsavedChanges(form, modalOpen)

function closeForm(): void {
  if (busy.value || !confirmDiscard()) return
  markClean()
  modalOpen.value = false
}

const columns: readonly ListColumn<Policy>[] = [
  { key: 'organization_id', labelKey: 'security.organizationScope' },
  { key: 'resource_id', labelKey: 'security.resource' },
  { key: 'state_id', labelKey: 'security.workflowState' },
  { key: 'participant_type', labelKey: 'security.participantType' },
  { key: 'participant_ref_id', labelKey: 'security.participant' },
  { key: 'actions', labelKey: 'security.effectsSummary' },
  { key: 'row_actions', labelKey: 'security.actions', align: 'end' },
]
const actionOptions = computed(() =>
  actionCatalog.value.filter((action) => String(action.resource_id) === form.value.resource_id),
)
const workflowStateOptions = computed(() =>
  lifecycleStateMemberships.value
    .map((membership) =>
      workflowStates.value.find((state) => String(state.id) === String(membership.state_id)),
    )
    .filter((state): state is WorkflowState => state !== undefined)
    .map((state) => ({ id: String(state.id), label: `${state.code} · ${state.name}` })),
)
const participantSelectOptions = computed(() =>
  participantOptions.value.map((option) => ({ value: option.id, label: option.label })),
)
const duplicateScopeSummary = computed(() =>
  [
    form.value.organization_id,
    form.value.resource_id,
    form.value.state_id || 'ALL_STATES',
    form.value.participant_type,
    form.value.participant_ref_id,
  ].join(' / '),
)
const isRemoteParticipant = computed(() => form.value.participant_type !== 'GROUP')
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
function applyQuery(query: ListQuery): void {
  state.setSearch(query.q)
  state.setFilters(query.filter)
  state.setSort(query.sort)
  state.setPageSize(query.page_size)
  void load()
}
function resetQuery(): void {
  state.reset()
  void load()
}
async function load(): Promise<void> {
  error.value = null
  try {
    await state.apply()
    if (!selectedId.value && state.rows.value[0]) selectedId.value = String(state.rows.value[0].id)
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
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
function preserveParticipantSelection(): void {
  const id = form.value.participant_ref_id
  if (id && !participantOptions.value.some((option) => option.id === id)) {
    participantOptions.value = [{ id, label: `#${id}` }, ...participantOptions.value]
  }
}
async function loadParticipantOptions(
  type: ParticipantType,
  query: string,
  resetSelection = false,
): Promise<void> {
  const requestId = ++participantRequestId
  participantLoading.value = true
  const listQuery: ListQuery = { q: query, page: 1, page_size: 50, sort: 'name', filter: {} }
  try {
    let options: ParticipantOption[]
    switch (type) {
      case 'USER': {
        if (!query) {
          options = users.value.map((user) => ({
            id: String(user.id),
            label: user.name || user.username,
          }))
        } else {
          const page = await listSecurityUserPage({ ...listQuery, sort: 'username' })
          options = page.data.map((user) => ({
            id: String(user.id),
            label: `${user.username}${user.name ? ` · ${user.name}` : ''}`,
          }))
        }
        break
      }
      case 'POSITION': {
        if (!query) {
          options = positions.value.map((item) => ({
            id: String(item.id),
            label: `${item.code} · ${item.name}`,
          }))
        } else {
          const response = await withTypedClient((client) =>
            listPositions({
              client,
              headers: { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' },
              query: { ...listQuery, filter: {} },
              throwOnError: true,
            }),
          )
          options = (response.data.data ?? []).map((item: Position) => ({
            id: String(item.id),
            label: `${item.code} · ${item.name}`,
          }))
        }
        break
      }
      case 'DEPARTMENT': {
        if (!query) {
          options = departments.value.map((item) => ({
            id: String(item.id),
            label: `${item.code} · ${item.name}`,
          }))
        } else {
          const response = await withTypedClient((client) =>
            listDepartments({
              client,
              headers: { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' },
              query: { ...listQuery, filter: {} },
              throwOnError: true,
            }),
          )
          options = (response.data.data ?? []).map((item: Department) => ({
            id: String(item.id),
            label: `${item.code} · ${item.name}`,
          }))
        }
        break
      }
      case 'ORGANIZATION': {
        if (!query) {
          options = organizations.value.map((item) => ({
            id: String(item.id),
            label: `${item.code} · ${item.name}`,
          }))
        } else {
          const response = await withTypedClient((client) =>
            listOrganizations({
              client,
              headers: { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' },
              query: { ...listQuery, filter: {} },
              throwOnError: true,
            }),
          )
          options = (response.data.data ?? []).map((item: Organization) => ({
            id: String(item.id),
            label: `${item.code} · ${item.name}`,
          }))
        }
        break
      }
      case 'GROUP':
        options = groups.value.map((group) => ({
          id: String(group.id),
          label: `${group.code} · ${group.name}`,
        }))
        break
    }
    if (requestId !== participantRequestId || type !== form.value.participant_type) return
    participantOptions.value = options
    if (resetSelection) form.value.participant_ref_id = options[0]?.id ?? ''
    else preserveParticipantSelection()
  } catch (caught: unknown) {
    if (requestId === participantRequestId) {
      error.value = caught
      reportApiError(caught)
    }
  } finally {
    if (requestId === participantRequestId) participantLoading.value = false
  }
}
function onParticipantTypeChange(type: ParticipantType): void {
  form.value.participant_ref_id = ''
  void loadParticipantOptions(type, '', true)
}
function searchParticipants(query: string): void {
  if (!isRemoteParticipant.value) return
  if (participantSearchTimer) clearTimeout(participantSearchTimer)
  participantSearchTimer = setTimeout(() => {
    void loadParticipantOptions(form.value.participant_type, query)
  }, 250)
}
async function loadWorkflowStates(resourceId: string): Promise<void> {
  const resource = resources.value.find((item) => String(item.id) === resourceId)
  const lifecycleId = resource?.lifecycle_id ? String(resource.lifecycle_id) : ''
  const requestId = ++workflowStateRequestId
  workflowStatesLoading.value = true
  try {
    if (!lifecycleId) {
      lifecycleStateMemberships.value = []
    } else {
      let memberships = lifecycleStatesById.get(lifecycleId)
      if (!memberships) {
        memberships = await getLifecycleStates(lifecycleId)
        lifecycleStatesById.set(lifecycleId, memberships)
      }
      if (requestId !== workflowStateRequestId) return
      lifecycleStateMemberships.value = memberships
    }
    if (
      form.value.state_id &&
      !lifecycleStateMemberships.value.some(
        (membership) => String(membership.state_id) === form.value.state_id,
      )
    ) {
      form.value.state_id = ''
    }
  } catch (caught: unknown) {
    if (requestId === workflowStateRequestId) {
      lifecycleStateMemberships.value = []
      form.value.state_id = ''
      error.value = caught
      reportApiError(caught)
    }
  } finally {
    if (requestId === workflowStateRequestId) workflowStatesLoading.value = false
  }
}
function onResourceChange(resourceId: string): void {
  form.value.state_id = ''
  void Promise.all([loadActions(resourceId), loadWorkflowStates(resourceId)])
}
function openCreate(): void {
  editingId.value = undefined
  form.value = {
    organization_id: organizations.value[0] ? String(organizations.value[0].id) : '',
    resource_id: resources.value[0] ? String(resources.value[0].id) : '',
    state_id: '',
    participant_type: 'USER',
    participant_ref_id: '',
    actions: [],
  }
  // Choosing the first participant is an API-driven default, not a user edit.
  // Rebase the pristine draft only if no other field or selection changed while loading.
  const initialFields = JSON.stringify({ ...form.value, participant_ref_id: '' })
  const initialParticipantLoad = loadParticipantOptions(form.value.participant_type, '', true)
  const requestId = participantRequestId
  void Promise.all([
    loadActions(form.value.resource_id),
    loadWorkflowStates(form.value.resource_id),
    initialParticipantLoad,
  ])
    .then(() => {
      if (
        modalOpen.value &&
        editingId.value === undefined &&
        participantRequestId === requestId &&
        JSON.stringify({ ...form.value, participant_ref_id: '' }) === initialFields &&
        form.value.participant_ref_id === (participantOptions.value[0]?.id ?? '')
      )
        markClean()
    })
    .catch(reportApiError)
  markClean()
  modalOpen.value = true
}
function openEdit(policy: Policy): void {
  editingId.value = String(policy.id)
  form.value = {
    organization_id: String(policy.organization_id),
    resource_id: String(policy.resource_id),
    state_id: policy.state_id ? String(policy.state_id) : '',
    participant_type: policy.participant_type,
    participant_ref_id: String(policy.participant_ref_id),
    actions: policy.actions.map((action) => ({
      resource_action_id: String(action.resource_action_id),
      effect: action.effect,
    })),
  }
  void Promise.all([
    loadActions(form.value.resource_id),
    loadWorkflowStates(form.value.resource_id),
    loadParticipantOptions(form.value.participant_type, ''),
  ])
  markClean()
  modalOpen.value = true
}
function addAction(): void {
  const candidate = actionOptions.value.find(
    (action) => !form.value.actions.some((row) => row.resource_action_id === action.id),
  )
  if (candidate) form.value.actions.push({ resource_action_id: candidate.id, effect: 'ALLOW' })
}
function removeAction(index: number): void {
  form.value.actions.splice(index, 1)
}
async function save(): Promise<void> {
  busy.value = true
  error.value = null
  duplicateScopeConflict.value = false
  try {
    const payload = {
      organization_id: form.value.organization_id,
      resource_id: form.value.resource_id,
      ...(form.value.state_id ? { state_id: form.value.state_id } : {}),
      participant_type: form.value.participant_type,
      participant_ref_id: form.value.participant_ref_id,
      actions: form.value.actions,
    }
    await savePolicy(payload, editingId.value)
    markClean()
    modalOpen.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    const notice = reportApiError(caught) ?? normalizeApiError(caught)
    duplicateScopeConflict.value = notice?.code === 'POLICY_SCOPE_ALREADY_EXISTS'
  } finally {
    busy.value = false
  }
}
async function reloadAfterDuplicate(): Promise<void> {
  duplicateScopeConflict.value = false
  await load()
}
onMounted(async () => {
  await loadReferences()
  await load()
})
onBeforeUnmount(() => {
  if (participantSearchTimer) clearTimeout(participantSearchTimer)
})
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.policiesTitle') }}</h1>
      <p>{{ t('security.policiesDescription') }}</p>
    </div>
    <button
      v-if="hasBasePermission('policy', 'create')"
      type="button"
      class="primary-button"
      @click="openCreate"
    >
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <p v-if="error && !duplicateScopeConflict" class="inline-error" role="alert">
    {{ t('security.requestError') }}
  </p>
  <section class="content-panel security-list-panel">
    <div class="security-toolbar">
      <ListQueryControls
        :model-value="state.query"
        @apply="applyQuery"
        @reset="resetQuery"
      /><button type="button" class="secondary-button" @click="void load">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <ListShell
      :initial-loading="state.isInitialLoading.value"
      :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value"
      :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value"
      :error="state.queryState.error.value"
      ><ListTable
        :rows="state.rows.value"
        :columns="columns"
        :sort="state.query.sort"
        @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        "
        ><template #cell-organization_id="{ row }">{{
          labelForOrganization(String(row.organization_id))
        }}</template
        ><template #cell-resource_id="{ row }"
          ><code>{{ labelForResource(String(row.resource_id)) }}</code></template
        ><template #cell-state_id="{ row }">{{
          row.state_id
            ? (workflowStates.find((item) => String(item.id) === String(row.state_id))?.name ??
              `#${row.state_id}`)
            : t('security.allStates')
        }}</template
        ><template #cell-participant_ref_id="{ row }">{{
          labelForParticipant(row) || `#${row.participant_ref_id}`
        }}</template
        ><template #cell-actions="{ row }"
          ><span class="effect-summary">{{ effectsSummary(row) }}</span></template
        ><template #cell-row_actions="{ row }"
          ><TableRowActions
            :busy="busy"
            :show-view="true"
            :show-edit="true"
            view-permission="policy.view"
            edit-permission="policy.edit"
            :edit-label="t('common.edit')"
            :view-label="t('common.view')"
            @view="openEdit(row)"
            @edit="openEdit(row)" /></template></ListTable
      ><ListPagination
        :page="state.query.page"
        :page-size="state.query.page_size"
        :total="state.total.value"
        @update:page="
          (page) => {
            state.setPage(page)
            void load()
          }
        "
        @update:page-size="
          (size) => {
            state.setPageSize(size)
            void load()
          }
        "
    /></ListShell>
  </section>
  <div v-if="modalOpen" class="modal-backdrop">
    <section
      class="modal-card policy-form-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('security.policyForm')"
    >
      <header class="modal-card__header">
        <h2>{{ editingId ? t('common.edit') : t('common.create') }} {{ t('security.policy') }}</h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="closeForm"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="save">
        <div v-if="duplicateScopeConflict" class="duplicate-scope-notice" role="alert">
          <p>
            {{ t('security.policyScopeAlreadyExists') }}
            <code>{{ duplicateScopeSummary }}</code>
          </p>
          <button type="button" class="secondary-button" @click="void reloadAfterDuplicate">
            {{ t('security.reloadPolicyScope') }}
          </button>
        </div>
        <label
          ><span>{{ t('security.organizationScope') }}</span
          ><OrganizationTreeSelect
            id="policy-organization-scope"
            v-model="form.organization_id"
            :organizations="organizations"
            :placeholder="t('security.organizationScope')" /></label
        ><label
          ><span>{{ t('security.resource') }}</span
          ><select
            v-model="form.resource_id"
            class="field-select"
            required
            @change="onResourceChange(form.resource_id)"
          >
            <option v-for="item in resources" :key="item.id" :value="String(item.id)">
              {{ item.type }} · {{ item.name }}
            </option>
          </select></label
        ><label
          ><span>{{ t('security.workflowState') }}</span
          ><select
            v-model="form.state_id"
            class="field-select"
            :disabled="workflowStatesLoading || !form.resource_id"
          >
            <option value="">{{ t('security.allStates') }}</option>
            <option v-for="item in workflowStateOptions" :key="item.id" :value="item.id">
              {{ item.label }}
            </option>
          </select></label
        >
        <div class="form-grid">
          <label
            ><span>{{ t('security.participantType') }}</span
            ><select
              v-model="form.participant_type"
              class="field-select"
              required
              @change="onParticipantTypeChange(form.participant_type)"
            >
              <option v-for="type in participantTypes" :key="type" :value="type">{{ type }}</option>
            </select></label
          ><label
            ><span>{{ t('security.participant') }}</span
            ><NSelect
              v-model:value="form.participant_ref_id"
              :options="participantSelectOptions"
              :loading="participantLoading"
              :remote="isRemoteParticipant"
              :filterable="true"
              :clearable="false"
              :placeholder="t('security.participant')"
              :aria-label="t('security.participant')"
              @search="searchParticipants"
          /></label>
        </div>
        <div class="action-editor">
          <div class="detail-actions">
            <strong>{{ t('security.actionEffects') }}</strong
            ><button
              v-if="hasBasePermission('policy', 'edit')"
              type="button"
              class="secondary-button"
              @click="addAction"
            >
              ＋ {{ t('security.addAction') }}
            </button>
          </div>
          <div
            v-for="(row, index) in form.actions"
            :key="`${row.resource_action_id}-${index}`"
            class="action-edit-row"
          >
            <select
              v-model="row.resource_action_id"
              class="field-select"
              required
              :aria-label="t('security.actionEffects')"
            >
              <option v-for="action in actionOptions" :key="action.id" :value="action.id">
                {{ action.code }}
              </option></select
            ><select
              v-model="row.effect"
              class="field-select"
              required
              :aria-label="t('security.effectsSummary')"
            >
              <option v-for="effect in effects" :key="effect" :value="effect">
                {{ effect }}
              </option></select
            ><button
              v-if="hasBasePermission('policy', 'edit')"
              type="button"
              class="icon-button action-delete-button"
              :aria-label="t('common.delete')"
              :title="t('common.delete')"
              @click="removeAction(index)"
            >
              <NIcon :component="TrashOutline" aria-hidden="true" />
            </button>
          </div>
          <p class="security-note">{{ t('security.policyBasePermissionNote') }}</p>
        </div>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="closeForm">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="
              busy ||
              form.actions.length === 0 ||
              !form.participant_ref_id ||
              !hasBasePermission(editingId ? 'policy' : 'policy', editingId ? 'edit' : 'create')
            "
          >
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.security-list-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
}
.security-toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.effect-summary {
  font-family: ui-monospace, monospace;
  color: #345f77;
  font-size: 11px;
}
.security-note {
  margin: 0;
  color: #536b7b;
  font-size: 12px;
  line-height: 1.6;
}
.inline-error {
  margin: 0 0 12px;
  color: #b64444;
  font-size: 12px;
}
.duplicate-scope-notice {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #e7b6b6;
  border-radius: 5px;
  background: #fff5f5;
  color: #8f3030;
  font-size: 12px;
}
.duplicate-scope-notice p {
  margin: 0;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(18 38 54 / 45%);
}
.modal-card {
  width: min(100%, 760px);
  max-height: calc(100vh - 40px);
  overflow: hidden;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}
.modal-card__header,
.modal-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 22px;
  border-bottom: 1px solid #e4ebef;
}
.modal-card__body {
  display: grid;
  gap: 12px;
  max-height: calc(100vh - 170px);
  overflow: auto;
  padding: 22px;
}
.modal-card__body label {
  display: grid;
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}
.modal-card__footer {
  justify-content: flex-end;
  margin: 0;
  padding: 14px 22px 18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.policy-form-modal .modal-card__body .modal-card__footer {
  margin: 4px -22px -22px;
  padding: 14px 22px 0;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.action-editor {
  display: grid;
  gap: 9px;
  padding: 12px;
  border: 1px solid #dce6ec;
  border-radius: 6px;
}
.detail-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}
.action-edit-row {
  display: grid;
  grid-template-columns: 1.4fr 1fr auto;
  gap: 8px;
  align-items: center;
}
.icon-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #536b7b;
  font-size: 22px;
}
.action-delete-button {
  display: inline-grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border: 1px solid #dce6ec;
  border-radius: 5px;
  color: #b64444;
  font-size: 18px;
}
code {
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  color: #0d5b83;
}
@media (max-width: 700px) {
  .form-grid,
  .action-edit-row {
    grid-template-columns: 1fr;
  }
}
</style>
