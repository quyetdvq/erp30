<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Policy, PolicyAction, Resource } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { getCurrentUser, type AuthUser } from '@/services/auth'
import {
  getResourceActions,
  listAuthorizationGroupPage,
  listPolicyPage,
  listResourcePage,
  savePolicy,
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
import { reportApiError } from '@/services/errorBoundary'

type ParticipantType = Policy['participant_type']
type PolicyDraftAction = { resource_action_id: string; effect: PolicyAction['effect'] }
const participantTypes: ParticipantType[] = [
  'USER',
  'GROUP',
  'POSITION',
  'DEPARTMENT',
  'ORGANIZATION',
]
const effects: PolicyAction['effect'][] = ['ALLOW', 'DENY', 'ABSOLUTE_DENY']
const { t } = useI18n()
const state = useListState<Policy>((query) => listPolicyPage(query), { sort: 'id' })
const selectedId = ref<string | null>(null)
const modalOpen = ref(false)
const editingId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const organizations = ref<Organization[]>([])
const resources = ref<Resource[]>([])
const groups = ref<AuthorizationGroup[]>([])
const departments = ref<Department[]>([])
const positions = ref<Position[]>([])
const workflowStates = ref<WorkflowState[]>([])
const currentUser = ref<AuthUser | null>(null)
const actions = ref<SecurityAction[]>([])
const form = ref({
  organization_id: '',
  resource_id: '',
  state_id: '',
  participant_type: 'USER' as ParticipantType,
  participant_ref_id: '',
  actions: [] as PolicyDraftAction[],
})
const columns: readonly ListColumn<Policy>[] = [
  { key: 'organization_id', labelKey: 'security.organizationScope' },
  { key: 'resource_id', labelKey: 'security.resource' },
  { key: 'state_id', labelKey: 'security.workflowState' },
  { key: 'participant_type', labelKey: 'security.participantType' },
  { key: 'participant_ref_id', labelKey: 'security.participant' },
  { key: 'actions', labelKey: 'security.effectsSummary' },
  { key: 'row_actions', labelKey: 'security.actions' },
]
const participantOptions = computed(() => {
  switch (form.value.participant_type) {
    case 'USER':
      return currentUser.value
        ? [
            {
              id: String(currentUser.value.id),
              label: `${currentUser.value.username} (#${currentUser.value.id})`,
            },
          ]
        : []
    case 'GROUP':
      return groups.value.map((group) => ({
        id: String(group.id),
        label: `${group.code} · ${group.name}`,
      }))
    case 'POSITION':
      return positions.value.map((item) => ({
        id: String(item.id),
        label: `${item.code} · ${item.name}`,
      }))
    case 'DEPARTMENT':
      return departments.value.map((item) => ({
        id: String(item.id),
        label: `${item.code} · ${item.name}`,
      }))
    case 'ORGANIZATION':
      return organizations.value.map((item) => ({
        id: String(item.id),
        label: `${item.code} · ${item.name}`,
      }))
  }
  return []
})
const actionOptions = computed(() =>
  actions.value.filter((action) => String(action.resource_id) === form.value.resource_id),
)
function labelForOrganization(id: string): string {
  return organizations.value.find((item) => String(item.id) === id)?.name ?? `#${id}`
}
function labelForResource(id: string): string {
  return resources.value.find((item) => String(item.id) === id)?.type ?? `#${id}`
}
function labelForParticipant(policy: Policy): string {
  const found = participantOptionsFor(policy.participant_type, String(policy.participant_ref_id))
  return found || `#${policy.participant_ref_id}`
}
function participantOptionsFor(type: ParticipantType, id: string): string {
  const source =
    type === 'GROUP'
      ? groups.value
      : type === 'POSITION'
        ? positions.value
        : type === 'DEPARTMENT'
          ? departments.value
          : type === 'ORGANIZATION'
            ? organizations.value
            : currentUser.value
              ? [currentUser.value]
              : []
  const item = source.find((candidate) => String(candidate.id) === id) as
    { name?: string; username?: string; code?: string } | undefined
  return item ? `${item.code ? `${item.code} · ` : ''}${item.name ?? item.username ?? id}` : ''
}
function effectsSummary(policy: Policy): string {
  return policy.actions.map((action) => `${action.effect}:${action.resource_action_id}`).join(', ')
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
    const [
      resourcePage,
      groupPage,
      organizationPage,
      departmentPage,
      positionPage,
      statePage,
      user,
    ] = await Promise.all([
      listResourcePage(emptyQuery),
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
      withTypedClient((client) =>
        listWorkflowStates({
          client,
          headers,
          query: { page: 1, page_size: 200, sort: 'name', filter: {} },
          throwOnError: true,
        }),
      ).then((response) => ({ data: response.data.data ?? [] })),
      getCurrentUser(),
    ])
    resources.value = resourcePage.data
    groups.value = groupPage.data
    organizations.value = organizationPage.data as Organization[]
    departments.value = departmentPage.data as Department[]
    positions.value = positionPage.data as Position[]
    workflowStates.value = statePage.data as WorkflowState[]
    currentUser.value = user
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function loadActions(resourceId: string): Promise<void> {
  actions.value = resourceId ? await getResourceActions(resourceId) : []
}
function openCreate(): void {
  editingId.value = undefined
  form.value = {
    organization_id: organizations.value[0] ? String(organizations.value[0].id) : '',
    resource_id: resources.value[0] ? String(resources.value[0].id) : '',
    state_id: '',
    participant_type: 'USER',
    participant_ref_id: currentUser.value ? String(currentUser.value.id) : '',
    actions: [],
  }
  void loadActions(form.value.resource_id)
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
  void loadActions(form.value.resource_id)
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
    modalOpen.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
onMounted(async () => {
  await Promise.all([load(), loadReferences()])
})
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.policiesTitle') }}</h1>
      <p>{{ t('security.policiesDescription') }}</p>
    </div>
    <button type="button" class="primary-button" @click="openCreate">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ t('security.requestError') }}</p>
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
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section
      class="modal-card"
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
          @click="modalOpen = false"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="save">
        <label
          ><span>{{ t('security.organizationScope') }}</span
          ><select v-model="form.organization_id" class="field-select" required>
            <option v-for="item in organizations" :key="item.id" :value="String(item.id)">
              {{ item.code }} · {{ item.name }}
            </option>
          </select></label
        ><label
          ><span>{{ t('security.resource') }}</span
          ><select
            v-model="form.resource_id"
            class="field-select"
            required
            @change="void loadActions(form.resource_id)"
          >
            <option v-for="item in resources" :key="item.id" :value="String(item.id)">
              {{ item.type }} · {{ item.name }}
            </option>
          </select></label
        ><label
          ><span>{{ t('security.workflowState') }}</span
          ><select v-model="form.state_id" class="field-select">
            <option value="">{{ t('security.allStates') }}</option>
            <option v-for="item in workflowStates" :key="item.id" :value="String(item.id)">
              {{ item.code }} · {{ item.name }}
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
              @change="form.participant_ref_id = participantOptions[0]?.id ?? ''"
            >
              <option v-for="type in participantTypes" :key="type" :value="type">{{ type }}</option>
            </select></label
          ><label
            ><span>{{ t('security.participant') }}</span
            ><select v-model="form.participant_ref_id" class="field-select" required>
              <option v-for="item in participantOptions" :key="item.id" :value="item.id">
                {{ item.label }}
              </option>
            </select></label
          >
        </div>
        <div class="action-editor">
          <div class="detail-actions">
            <strong>{{ t('security.actionEffects') }}</strong
            ><button type="button" class="secondary-button" @click="addAction">
              ＋ {{ t('security.addAction') }}
            </button>
          </div>
          <div
            v-for="(row, index) in form.actions"
            :key="`${row.resource_action_id}-${index}`"
            class="action-edit-row"
          >
            <select v-model="row.resource_action_id" class="field-select" required>
              <option v-for="action in actionOptions" :key="action.id" :value="action.id">
                {{ action.code }}
              </option></select
            ><select v-model="row.effect" class="field-select" required>
              <option v-for="effect in effects" :key="effect" :value="effect">
                {{ effect }}
              </option></select
            ><button type="button" class="secondary-button" @click="removeAction(index)">
              {{ t('common.delete') }}
            </button>
          </div>
          <p class="security-note">{{ t('security.policyBasePermissionNote') }}</p>
        </div>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="modalOpen = false">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="busy || form.actions.length === 0"
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
  color: #6c8191;
  font-size: 12px;
  line-height: 1.6;
}
.inline-error {
  margin: 0 0 12px;
  color: #b64444;
  font-size: 12px;
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
  overflow: auto;
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
  padding: 15px 18px;
  border-bottom: 1px solid #e4ebef;
}
.modal-card__body {
  display: grid;
  gap: 12px;
  padding: 18px;
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
  margin: 5px -18px -18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
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
  border: 0;
  background: transparent;
  color: #536b7b;
  font-size: 22px;
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
