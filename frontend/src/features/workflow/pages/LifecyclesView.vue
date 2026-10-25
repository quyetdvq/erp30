<script setup lang="ts">
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  Lifecycle,
  LifecycleCreate,
  LifecyclePatch,
  WorkflowState,
} from '@/api/generated/types.gen'
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
import { useBasePermission } from '@/composables/useBasePermission'
import {
  addLifecycleState,
  addLifecycleTransition,
  changeLifecycleState,
  getLifecycleStates,
  getLifecycleTransitions,
  listLifecyclePage,
  listWorkflowStatePage,
  type LifecycleStateMembership,
  type LifecycleTransition,
  saveLifecycle,
} from '@/services/workflow'
import { normalizeApiError, reportApiError } from '@/services/errorBoundary'
import { loadLookupPages } from '@/services/listLookups'

type Tab = 'overview' | 'states' | 'transitions'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<Lifecycle>((query) => listLifecyclePage(query), { sort: 'code' })
const selectedId = ref<string | null>(null)
const tab = ref<Tab>('overview')
const lifecycleStates = ref<LifecycleStateMembership[]>([])
const lifecycleTransitions = ref<LifecycleTransition[]>([])
const workflowStates = ref<WorkflowState[]>([])
const modalOpen = ref(false)
const stateModalOpen = ref(false)
const transitionModalOpen = ref(false)
const detailOpen = ref(false)
const stateAddButton = ref<HTMLButtonElement | null>(null)
const transitionAddButton = ref<HTMLButtonElement | null>(null)
const stateDialog = ref<HTMLElement | null>(null)
const transitionDialog = ref<HTMLElement | null>(null)
const editingId = ref<string | undefined>()
const busy = ref(false)
const detailBusy = ref(false)
const error = ref<unknown | null>(null)
const detailError = ref<unknown | null>(null)
const form = ref({ code: '', name: '', description: '', status: 'ACTIVE' as Lifecycle['status'] })
const { markClean, confirmDiscard } = useUnsavedChanges(form, modalOpen)

function closeForm(): void {
  if (busy.value || !confirmDiscard()) return
  markClean()
  modalOpen.value = false
}

const stateForm = ref({ state_id: '', sequence: 1, is_initial: false, is_final: false })
const transitionForm = ref({
  from_state_id: '',
  action_code: '',
  to_state_id: '',
  status: 'ACTIVE' as LifecycleTransition['status'],
})

const stateDraft = useUnsavedChanges(stateForm, stateModalOpen)
const transitionDraft = useUnsavedChanges(transitionForm, transitionModalOpen)

function closeStateForm(): void {
  if (busy.value || !stateDraft.confirmDiscard()) return
  stateDraft.markClean()
  stateModalOpen.value = false
  void nextTick(() => stateAddButton.value?.focus())
}

function closeTransitionForm(): void {
  if (busy.value || !transitionDraft.confirmDiscard()) return
  transitionDraft.markClean()
  transitionModalOpen.value = false
  void nextTick(() => transitionAddButton.value?.focus())
}

const columns: readonly ListColumn<Lifecycle>[] = [
  { key: 'code', labelKey: 'workflow.code', sortable: true },
  { key: 'name', labelKey: 'workflow.name', sortable: true },
  { key: 'description', labelKey: 'workflow.description' },
  { key: 'status', labelKey: 'workflow.status', sortable: true, align: 'center' },
  { key: 'actions', labelKey: 'workflow.actions', align: 'end' },
]

const selected = computed(
  () => state.rows.value.find((row) => String(row.id) === selectedId.value) ?? null,
)
const stateById = computed(
  () => new Map(workflowStates.value.map((item) => [String(item.id), item])),
)
const memberIds = computed(
  () => new Set(lifecycleStates.value.map((item) => String(item.state_id))),
)
const availableStates = computed(() =>
  workflowStates.value.filter(
    (item) => item.status === 'ACTIVE' && !memberIds.value.has(String(item.id)),
  ),
)
const memberStateOptions = computed(() =>
  lifecycleStates.value
    .map((item) => ({ membership: item, state: stateById.value.get(String(item.state_id)) }))
    .filter((item) => item.state?.status === 'ACTIVE'),
)
const initialAlreadySet = computed(() =>
  lifecycleStates.value.some((item) => flag(item.is_initial)),
)
const duplicateTransition = computed(() =>
  lifecycleTransitions.value.some(
    (item) =>
      String(item.from_state_id) === transitionForm.value.from_state_id &&
      item.action_code.trim().toLowerCase() ===
      transitionForm.value.action_code.trim().toLowerCase(),
  ),
)
const canSaveState = computed(
  () =>
    Boolean(selectedId.value) &&
    availableStates.value.some((item) => String(item.id) === stateForm.value.state_id) &&
    Number.isInteger(stateForm.value.sequence) &&
    stateForm.value.sequence >= 1,
)
const canSaveTransition = computed(
  () =>
    Boolean(selectedId.value) &&
    memberStateOptions.value.some(
      (item) => String(item.membership.state_id) === transitionForm.value.from_state_id,
    ) &&
    memberStateOptions.value.some(
      (item) => String(item.membership.state_id) === transitionForm.value.to_state_id,
    ) &&
    /^[a-zA-Z0-9_:-]+$/.test(transitionForm.value.action_code) &&
    !duplicateTransition.value,
)

const errorMessage = (value: unknown): string => {
  const notice = normalizeApiError(value)
  if (!notice) return value ? t('workflow.requestError') : ''
  return `${t(`errors.status.${notice.status}`)}${notice.code ? ` (${notice.code})` : ''}`
}

function flag(value: boolean | number | string): boolean {
  return value === true || value === 1 || value === '1' || value === 'true' || value === 'TRUE'
}

function stateLabel(id: string): string {
  const row = stateById.value.get(String(id))
  return row ? `${row.name} (${row.code})` : `#${id}`
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
    if (selectedId.value && !selected.value)
      selectedId.value = state.rows.value[0] ? String(state.rows.value[0].id) : null
    await loadReferences()
    await loadDetails()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}

async function loadReferences(): Promise<void> {
  try {
    workflowStates.value = await loadLookupPages(listWorkflowStatePage, { sort: 'name' })
  } catch (caught: unknown) {
    detailError.value = caught
    reportApiError(caught)
  }
}

async function loadDetails(): Promise<void> {
  if (!selectedId.value) {
    lifecycleStates.value = []
    lifecycleTransitions.value = []
    return
  }
  detailBusy.value = true
  detailError.value = null
  try {
    const [states, transitions] = await Promise.all([
      getLifecycleStates(selectedId.value),
      getLifecycleTransitions(selectedId.value),
    ])
    lifecycleStates.value = states
    lifecycleTransitions.value = transitions
  } catch (caught: unknown) {
    detailError.value = caught
    reportApiError(caught)
  } finally {
    detailBusy.value = false
  }
}

function selectLifecycle(row: Lifecycle): void {
  selectedId.value = String(row.id)
  tab.value = 'overview'
  detailOpen.value = true
  void loadDetails()
}

function openCreate(): void {
  editingId.value = undefined
  form.value = { code: '', name: '', description: '', status: 'ACTIVE' }
  markClean()
  modalOpen.value = true
}

function openEdit(row: Lifecycle): void {
  editingId.value = String(row.id)
  form.value = {
    code: row.code,
    name: row.name,
    description: row.description ?? '',
    status: row.status,
  }
  markClean()
  modalOpen.value = true
}

async function save(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const payload: LifecycleCreate | LifecyclePatch = {
      code: form.value.code,
      name: form.value.name,
      status: form.value.status,
      ...(form.value.description ? { description: form.value.description } : { description: null }),
    }
    const saved = await saveLifecycle(payload, editingId.value)
    markClean()
    modalOpen.value = false
    selectedId.value = String(saved.id)
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

async function toggle(row: Lifecycle): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changeLifecycleState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

function openStateCreate(): void {
  detailError.value = null
  stateForm.value = {
    state_id: '',
    sequence: lifecycleStates.value.length + 1,
    is_initial: !initialAlreadySet.value,
    is_final: false,
  }
  stateDraft.markClean()
  stateModalOpen.value = true
  void nextTick(() => stateDialog.value?.focus())
}

async function saveState(): Promise<void> {
  if (busy.value || !hasBasePermission('lifecycle', 'edit') || !canSaveState.value || !selectedId.value) return
  busy.value = true
  detailError.value = null
  try {
    await addLifecycleState(selectedId.value, stateForm.value)
    stateDraft.markClean()
    stateModalOpen.value = false
    void nextTick(() => stateAddButton.value?.focus())
    await loadDetails()
  } catch (caught: unknown) {
    detailError.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

function openTransitionCreate(): void {
  detailError.value = null
  transitionForm.value = { from_state_id: '', action_code: '', to_state_id: '', status: 'ACTIVE' }
  transitionDraft.markClean()
  transitionModalOpen.value = true
  void nextTick(() => transitionDialog.value?.focus())
}

async function saveTransition(): Promise<void> {
  if (busy.value || !hasBasePermission('lifecycle', 'edit') || !canSaveTransition.value || !selectedId.value) return
  busy.value = true
  detailError.value = null
  try {
    await addLifecycleTransition(selectedId.value, transitionForm.value)
    transitionDraft.markClean()
    transitionModalOpen.value = false
    void nextTick(() => transitionAddButton.value?.focus())
    await loadDetails()
  } catch (caught: unknown) {
    detailError.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('workflow.lifecyclesTitle') }}</h1>
      <p>{{ t('workflow.lifecyclesDescription') }}</p>
    </div>
    <button v-if="hasBasePermission('lifecycle', 'create')" type="button" class="primary-button" @click="openCreate">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ errorMessage(error) }}</p>
  <section class="content-panel workflow-panel">
    <div class="workflow-toolbar">
      <ListQueryControls :model-value="state.query" @apply="applyQuery" @reset="resetQuery" />
      <button type="button" class="secondary-button" @click="void load()">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <ListShell :initial-loading="state.isInitialLoading.value" :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value" :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value" :error="state.queryState.error.value">
      <ListTable :rows="state.rows.value" :columns="columns" :sort="state.query.sort" @sort-change="
        (sort) => {
          state.setSort(sort)
          void load()
        }
      ">
        <template #cell-actions="{ row }">
          <TableRowActions authorization-mode="effective" :view-scope-verified="true" :allowed-actions="rowAllowedActions(row)" :active="row.status === 'ACTIVE'" :busy="busy" :show-view="true" :show-edit="true"
            :show-toggle="true" view-permission="lifecycle.view" edit-permission="lifecycle.edit"
            toggle-permission="lifecycle.setstate" :view-label="t('common.view')" :edit-label="t('common.edit')"
            :activate-label="t('common.activate')" :deactivate-label="t('common.deactivate')"
            @view="selectLifecycle(row)" @edit="openEdit(row)" @toggle="toggle(row)" />
        </template>
      </ListTable>
      <ListPagination :page="state.query.page" :page-size="state.query.page_size" :total="state.total.value"
        @update:page="
          (page) => {
            state.setPage(page)
            void load()
          }
        " @update:page-size="
          (size) => {
            state.setPageSize(size)
            void load()
          }
        " />
    </ListShell>
  </section>

  <div v-if="detailOpen && selected" class="modal-backdrop">
    <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="lifecycle-detail-title"
      :aria-hidden="stateModalOpen || transitionModalOpen" :inert="stateModalOpen || transitionModalOpen">
      <header class="modal-card__header">
        
        <h2 id="lifecycle-detail-title" class="modal-card-title">
          <NIcon :size="24" aria-hidden="true"> <RepeatOutline /> </NIcon>
          {{ selected.name }}
        </h2>
        <small><code>{{ selected.code }}</code> · {{ t('workflow.status') }}:
          {{ t(`status.${selected.status.toLowerCase()}`) }}</small>

        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')"
          @click="detailOpen = false">
        </button>
      </header>


      <nav class="workflow-tabs" :aria-label="t('workflow.tabsLabel')" role="tablist">
        <button type="button" role="tab" :aria-selected="tab === 'overview'" :class="{ active: tab === 'overview' }"
          @click="tab = 'overview'">
          {{ t('workflow.tabs.overview') }}
        </button>
        <button type="button" role="tab" :aria-selected="tab === 'states'" :class="{ active: tab === 'states' }"
          @click="tab = 'states'">
          {{ t('workflow.tabs.states') }}
        </button>
        <button type="button" role="tab" :aria-selected="tab === 'transitions'"
          :class="{ active: tab === 'transitions' }" @click="tab = 'transitions'">
          {{ t('workflow.tabs.transitions') }}
        </button>
      </nav>
      <div class="modal-card__body">
        <p v-if="detailError && !stateModalOpen && !transitionModalOpen" class="inline-error" role="alert">{{ errorMessage(detailError) }}</p>
        <div v-if="detailBusy" class="workflow-detail-state" role="status">
          {{ t('list.loading') }}
        </div>
        <div v-else-if="tab === 'overview'" class="workflow-overview">
          <dl>
            <div>
              <dt>{{ t('workflow.code') }}</dt>
              <dd>
                <code>{{ selected.code }}</code>
              </dd>
            </div>
            <div>
              <dt>{{ t('workflow.name') }}</dt>
              <dd>{{ selected.name }}</dd>
            </div>
            <div>
              <dt>{{ t('workflow.description') }}</dt>
              <dd>{{ selected.description || t('list.noValue') }}</dd>
            </div>
            <div>
              <dt>{{ t('workflow.memberCount') }}</dt>
              <dd>{{ lifecycleStates.length }}</dd>
            </div>
            <div>
              <dt>{{ t('workflow.transitionCount') }}</dt>
              <dd>{{ lifecycleTransitions.length }}</dd>
            </div>
          </dl>
        </div>
        <div v-else-if="tab === 'states'" class="workflow-tab-content">
          <div class="workflow-tab-toolbar">
            <p>{{ t('workflow.statesMembershipNote') }}</p>
            <button v-if="hasBasePermission('lifecycle', 'edit')" ref="stateAddButton" type="button" class="primary-button"
              :disabled="availableStates.length === 0" @click="openStateCreate">
              ＋ {{ t('workflow.addState') }}
            </button>
          </div>
          <table class="workflow-table">
            <caption class="sr-only">
              {{
                t('workflow.statesTable')
              }}
            </caption>
            <thead>
              <tr>
                <th>{{ t('workflow.state') }}</th>
                <th>{{ t('workflow.sequence') }}</th>
                <th>{{ t('workflow.initial') }}</th>
                <th>{{ t('workflow.final') }}</th>
                <th>{{ t('workflow.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="membership in lifecycleStates" :key="membership.id">
                <td>{{ stateLabel(String(membership.state_id)) }}</td>
                <td>{{ membership.sequence }}</td>
                <td>{{ flag(membership.is_initial) ? t('workflow.yes') : t('workflow.no') }}</td>
                <td>{{ flag(membership.is_final) ? t('workflow.yes') : t('workflow.no') }}</td>
                <td>
                  <span :class="[
                    'state-note',
                    {
                      'state-note--inactive':
                        stateById.get(String(membership.state_id))?.status !== 'ACTIVE',
                    },
                  ]">{{
                      stateById.get(String(membership.state_id))?.status === 'ACTIVE'
                        ? t('status.active')
                        : t('status.inactive')
                    }}</span>
                </td>
              </tr>
              <tr v-if="lifecycleStates.length === 0">
                <td colspan="5">{{ t('workflow.noStates') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="workflow-tab-content">
          <div class="workflow-tab-toolbar">
            <p>{{ t('workflow.transitionsNote') }}</p>
            <button v-if="hasBasePermission('lifecycle', 'edit')" ref="transitionAddButton" type="button" class="primary-button"
              :disabled="memberStateOptions.length < 1" @click="openTransitionCreate">
              ＋ {{ t('workflow.addTransition') }}
            </button>
          </div>
          <table class="workflow-table">
            <caption class="sr-only">
              {{
                t('workflow.transitionsTable')
              }}
            </caption>
            <thead>
              <tr>
                <th>{{ t('workflow.fromState') }}</th>
                <th>{{ t('workflow.action') }}</th>
                <th>{{ t('workflow.toState') }}</th>
                <th>{{ t('workflow.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transition in lifecycleTransitions" :key="transition.id">
                <td>{{ stateLabel(String(transition.from_state_id)) }}</td>
                <td>
                  <code>{{ transition.action_code }}</code>
                </td>
                <td>{{ stateLabel(String(transition.to_state_id)) }}</td>
                <td>
                  {{ transition.status === 'ACTIVE' ? t('status.active') : t('status.inactive') }}
                </td>
              </tr>
              <tr v-if="lifecycleTransitions.length === 0">
                <td colspan="4">{{ t('workflow.noTransitions') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="detailOpen = false">
          <NIcon :size="20" aria-hidden="true">
            <Refresh />
          </NIcon>
          {{ t('common.close') }}
        </button>
      </footer>
    </section>
  </div>

  <div v-if="modalOpen" class="modal-backdrop">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('workflow.lifecycleForm')">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24" aria-hidden="true">
            <ContractOutline />
          </NIcon>
          {{ editingId ? t('common.edit') : t('common.create') }} {{ t('workflow.lifecycle') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="closeForm">
        </button>
      </header>
      <form class="modal-card__body" id="lifecycle-form" @submit.prevent="save">
        <label><span>{{ t('workflow.code') }}</span><input v-model="form.code" required /></label><label><span>{{
          t('workflow.name') }}</span><input v-model="form.name" required /></label><label><span>{{
              t('workflow.description') }}</span><textarea v-model="form.description" rows="3" /></label><label><span>{{
              t('workflow.status') }}</span><select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label>

      </form>

      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="closeForm">
          <NIcon :size="20" aria-hidden="true">
            <Refresh />
          </NIcon>
          {{ t('common.cancel') }}
        </button><button type="submit" class="primary-button" form="lifecycle-form"
          :disabled="busy || !hasBasePermission('lifecycle', editingId ? 'edit' : 'create')">
          <NIcon :size="20" aria-hidden="true">
            <SaveOutline />
          </NIcon>
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </div>

  <div v-if="stateModalOpen" class="modal-backdrop">
    <section ref="stateDialog" class="modal-card" role="dialog" aria-modal="true"
      :aria-label="t('workflow.stateMembershipForm')" tabindex="-1">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24" aria-hidden="true"><ContractOutline /></NIcon>
          {{ t('workflow.addState') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')"
          @click="closeStateForm"></button>
      </header>
      <form id="lifecycle-state-form" class="modal-card__body" @submit.prevent="saveState">
        <label>
          <span>{{ t('workflow.state') }}</span>
          <select v-model="stateForm.state_id" required>
            <option value="" disabled>{{ t('workflow.selectState') }}</option>
            <option v-for="item in availableStates" :key="item.id" :value="String(item.id)">
              {{ item.name }} ({{ item.code }})
            </option>
          </select>
        </label>
        <label>
          <span>{{ t('workflow.sequence') }}</span>
          <input v-model.number="stateForm.sequence" type="number" min="1" step="1" required />
        </label>
        <label class="checkbox-field">
          <input v-model="stateForm.is_initial" type="checkbox" :disabled="initialAlreadySet" />
          <span>
            {{ t('workflow.initial') }}
            <small v-if="initialAlreadySet">{{ t('workflow.initialAlreadySet') }}</small>
          </span>
        </label>
        <label class="checkbox-field">
          <input v-model="stateForm.is_final" type="checkbox" />
          <span>{{ t('workflow.final') }}</span>
        </label>
        <p v-if="detailError" class="inline-error" role="alert">{{ errorMessage(detailError) }}</p>
      </form>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="closeStateForm">
          <NIcon :size="20" aria-hidden="true"><Refresh /></NIcon>
          {{ t('common.cancel') }}
        </button>
        <button type="submit" form="lifecycle-state-form" class="primary-button"
          :disabled="busy || !canSaveState || !hasBasePermission('lifecycle', 'edit')">
          <NIcon :size="20" aria-hidden="true"><SaveOutline /></NIcon>
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </div>

  <div v-if="transitionModalOpen" class="modal-backdrop">
    <section ref="transitionDialog" class="modal-card" role="dialog" aria-modal="true"
      :aria-label="t('workflow.transitionForm')" tabindex="-1">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24" aria-hidden="true"><RepeatOutline /></NIcon>
          {{ t('workflow.addTransition') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')"
          @click="closeTransitionForm"></button>
      </header>
      <form id="lifecycle-transition-form" class="modal-card__body" @submit.prevent="saveTransition">
        <label>
          <span>{{ t('workflow.fromState') }}</span>
          <select v-model="transitionForm.from_state_id" required>
            <option value="" disabled>{{ t('workflow.selectState') }}</option>
            <option v-for="item in memberStateOptions" :key="item.membership.id"
              :value="String(item.membership.state_id)">
              {{ item.state?.name }} ({{ item.state?.code }})
            </option>
          </select>
        </label>
        <label>
          <span>{{ t('workflow.action') }}</span>
          <input v-model="transitionForm.action_code" required pattern="[a-zA-Z0-9_:\-]+" />
          <small v-if="duplicateTransition" class="field-error">{{ t('workflow.duplicateTransition') }}</small>
        </label>
        <label>
          <span>{{ t('workflow.toState') }}</span>
          <select v-model="transitionForm.to_state_id" required>
            <option value="" disabled>{{ t('workflow.selectState') }}</option>
            <option v-for="item in memberStateOptions" :key="`to-${item.membership.id}`"
              :value="String(item.membership.state_id)">
              {{ item.state?.name }} ({{ item.state?.code }})
            </option>
          </select>
        </label>
        <label>
          <span>{{ t('workflow.status') }}</span>
          <select v-model="transitionForm.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select>
        </label>
        <p v-if="detailError" class="inline-error" role="alert">{{ errorMessage(detailError) }}</p>
      </form>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="closeTransitionForm">
          <NIcon :size="20" aria-hidden="true"><Refresh /></NIcon>
          {{ t('common.cancel') }}
        </button>
        <button type="submit" form="lifecycle-transition-form" class="primary-button"
          :disabled="busy || !canSaveTransition || !hasBasePermission('lifecycle', 'edit')">
          <NIcon :size="20" aria-hidden="true"><SaveOutline /></NIcon>
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </div>

</template>

<style scoped>
.workflow-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
}

.workflow-tab-toolbar p {
  margin: 0;
  color: #536b7b;
  font-size: 13px;
}

.inline-error,
.field-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}

.workflow-tabs {
  display: flex;
  gap: 24px;
  padding: 0 24px;
  border-bottom: 1px solid #dbe5ea;
}

.workflow-tabs button {
  border: 0;
  border-bottom: 2px solid transparent;
  padding: 13px 2px 11px;
  background: transparent;
  color: #536b7b;
  cursor: pointer;
  font: inherit;
}

.workflow-tabs button.active,
.workflow-tabs button:focus-visible {
  border-bottom-color: #1876a8;
  color: #1876a8;
}

.workflow-overview dl {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 12px;
  margin: 0;
}

.workflow-overview dl div {
  padding: 12px;
  border: 1px solid #e2eaee;
  border-radius: 5px;
}

.workflow-overview dt {
  color: #536b7b;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.workflow-overview dd {
  margin: 5px 0 0;
  color: #183b57;
}

.workflow-table {
  width: 100%;
  border-collapse: collapse;
  color: #183b57;
  font-size: 13px;
}

.workflow-table th,
.workflow-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e8eef1;
  text-align: left;
  vertical-align: middle;
}

.workflow-table th {
  color: #536b7b;
  font-size: 12px;
}

.state-note--inactive {
  color: #a23a32;
}

.checkbox-field {
  display: flex !important;
  grid-template-columns: auto 1fr;
  align-items: start;
  gap: 8px !important;
}

.checkbox-field input {
  min-height: auto !important;
  margin-top: 2px;
}

.checkbox-field small {
  display: block;
  margin-top: 3px;
  font-weight: 400;
}

.modal-card {
  width: min(100%, 680px);
  max-height: calc(100vh - 40px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}


.modal-card__body label {
  display: grid;
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}

.modal-card__body input,
.modal-card__body select,
.modal-card__body textarea {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  color: #183b57;
  font: inherit;
}



.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

@media (max-width: 700px) {
  .workflow-tabs {
    gap: 14px;
    padding: 0 18px;
  }
}
</style>
