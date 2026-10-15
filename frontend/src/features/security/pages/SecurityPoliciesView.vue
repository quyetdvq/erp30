<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon, NSelect } from 'naive-ui'
import { TrashOutline } from '@vicons/ionicons5'
import type { Policy } from '@/api/generated/types.gen'
import OrganizationTreeSelect from '@/components/organizations/OrganizationTreeSelect.vue'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
  TableStatus,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { changePolicyState, listPolicyPage, savePolicy } from '@/services/security'
import { normalizeApiError, reportApiError } from '@/services/errorBoundary'
import { useBasePermission } from '@/composables/useBasePermission'
import { participantTypes, effects } from '../policy-editor/types'
import { createPolicyDraft, initialPolicyDraft, policyToDraft } from '../policy-editor/model'
import { policyPayload } from '../policy-editor/payload'
import { policyScopeSummary } from '../policy-editor/validation'
import { usePolicyReferences } from '../policy-editor/usePolicyReferences'
import { usePolicyParticipants } from '../policy-editor/usePolicyParticipants'
import { usePolicyWorkflowStates } from '../policy-editor/usePolicyWorkflowStates'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<Policy>((query) => listPolicyPage(query), { sort: 'id' })
const selectedId = ref<string | null>(null)
const modalOpen = ref(false)
const editingId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const duplicateScopeConflict = ref(false)
const form = ref(initialPolicyDraft())
const {
  organizations,
  resources,
  groups,
  users,
  departments,
  positions,
  workflowStates,
  actionOptions,
  loadReferences,
  loadActions,
  labelForOrganization,
  labelForResource,
  labelForParticipant,
  effectsSummary,
} = usePolicyReferences(form, error, t)
const {
  participantOptions,
  participantLoading,
  participantSelectOptions,
  isRemoteParticipant,
  loadParticipantOptions,
  onParticipantTypeChange,
  searchParticipants,
  currentParticipantRequestId,
} = usePolicyParticipants(form, error, { organizations, groups, users, departments, positions })
const {
  workflowStateOptions,
  workflowStatesLoading,
  workflowStatesUnavailable,
  loadWorkflowStates,
} = usePolicyWorkflowStates(form, error)
const duplicateScopeSummary = computed(() => policyScopeSummary(form.value))
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
  { key: 'status', labelKey: 'security.status' },
  { key: 'participant_type', labelKey: 'security.participantType' },
  { key: 'participant_ref_id', labelKey: 'security.participant' },
  { key: 'actions', labelKey: 'security.effectsSummary' },
  { key: 'row_actions', labelKey: 'security.actions', align: 'end' },
]
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
function onResourceChange(resourceId: string): void {
  form.value.state_id = ''
  form.value.actions = []
  void Promise.all([loadActions(resourceId), loadWorkflowStates(resourceId)])
}
function openCreate(): void {
  editingId.value = undefined
  form.value = createPolicyDraft(organizations.value, resources.value)
  // Choosing the first participant is an API-driven default, not a user edit.
  // Rebase the pristine draft only if no other field or selection changed while loading.
  const initialFields = JSON.stringify({ ...form.value, participant_ref_id: '' })
  const initialParticipantLoad = loadParticipantOptions(form.value.participant_type, '', true)
  const requestId = currentParticipantRequestId()
  void Promise.all([
    loadActions(form.value.resource_id),
    loadWorkflowStates(form.value.resource_id),
    initialParticipantLoad,
  ])
    .then(() => {
      if (
        modalOpen.value &&
        editingId.value === undefined &&
        currentParticipantRequestId() === requestId &&
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
  form.value = policyToDraft(policy)
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
    if (workflowStatesLoading.value || workflowStatesUnavailable.value) return
    await savePolicy(policyPayload(form.value), editingId.value)
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
async function toggle(row: Policy): Promise<void> {
  const status = row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'
  const label = t(status === 'ACTIVE' ? 'common.activate' : 'common.deactivate')
  const message = `${label}: ${t('security.policy')} #${row.id}?`
  if (typeof window !== 'undefined' && !window.confirm(message)) return
  busy.value = true
  error.value = null
  try {
    await changePolicyState(String(row.id), status)
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
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
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.policiesTitle') }}</h1>
      <p>{{ t('security.policiesDescription') }}</p>
    </div>
    <button v-if="hasBasePermission('policy', 'create')" type="button" class="btn-add primary-button"
      @click="openCreate">
      <NIcon :size="16">
        <ShieldCheckmarkOutline />
      </NIcon>
      {{ t('common.create') }}
    </button>
  </div>
  <p v-if="error && !duplicateScopeConflict" class="inline-error" role="alert">
    {{ t('security.requestError') }}
  </p>
  <section class="content-panel security-list-panel">
    <div class="security-toolbar">
      <ListQueryControls :model-value="state.query" @apply="applyQuery" @reset="resetQuery" />

      <button type="button" class="secondary-button" @click="void load">

        {{ t('common.refresh') }}
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
      "><template #cell-organization_id="{ row }">{{
        labelForOrganization(String(row.organization_id))
      }}</template>
      <template
          #cell-resource_id="{ row }"><code>{{ labelForResource(String(row.resource_id)) }}</code></template>
          <template
          #cell-state_id="{ row }">{{
            row.state_id
              ? (workflowStates.find((item) => String(item.id) === String(row.state_id))?.name ??
                `#${row.state_id}`)
              : t('security.allStates')
          }}</template>
          <template #cell-status="{ row }">
          <TableStatus :status="row.status" />
        </template><template #cell-participant_ref_id="{ row }">{{
          labelForParticipant(row) || `#${row.participant_ref_id}`
        }}</template><template #cell-actions="{ row }"><span class="effect-summary">{{ effectsSummary(row)
          }}</span></template><template #cell-row_actions="{ row }">
          <TableRowActions :active="row.status === 'ACTIVE'" :busy="busy" :show-view="true" :show-edit="true"
            :show-toggle="true" view-permission="policy.view" edit-permission="policy.edit"
            toggle-permission="policy.setstate" :edit-label="t('common.edit')" :view-label="t('common.view')"
            :activate-label="t('common.activate')" :deactivate-label="t('common.deactivate')" @view="openEdit(row)"
            @edit="openEdit(row)" @toggle="toggle(row)" />
        </template></ListTable>
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

  <div v-if="modalOpen" class="modal-backdrop">
    <section class="modal-card policy-form-modal" role="dialog" aria-modal="true"
      :aria-label="t('security.policyForm')">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24" aria-hidden="true">
            <ShieldCheckmarkOutline />
          </NIcon>
          {{ editingId ? t('common.edit') : t('common.create') }}
          {{ t('security.policiesTitle') }}
        </h2>

        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')"
          @click="closeForm"></button>
      </header>

      <form id="policy-form-input" class="modal-card__body" @submit.prevent="
        !busy &&
        form.actions.length > 0 &&
        form.actions.every(row => row.resource_action_id && row.effect) &&
        save()
        ">
        <div v-if="duplicateScopeConflict" class="duplicate-scope-notice" role="alert">
          <p>
            {{ t('security.policyScopeAlreadyExists') }}
            <code>{{ duplicateScopeSummary }}</code>
          </p>

          <button type="button" class="secondary-button" @click="reloadAfterDuplicate">
            <NIcon :size="20"> <RefreshOutline /> </NIcon>
            {{ t('security.reloadPolicyScope') }}
          </button>
        </div>

        <label>
          <span>{{ t('security.organizationScope') }}</span>
          <OrganizationTreeSelect id="policy-organization-scope" v-model="form.organization_id"
            :organizations="organizations" :placeholder="t('security.organizationScope')" />
        </label>

        <label>
          <span>{{ t('security.resource') }}</span>
          <select v-model="form.resource_id" class="field-select" required @change="onResourceChange(form.resource_id)">
            <option v-for="item in resources" :key="item.id" :value="String(item.id)">
              {{ item.type }} · {{ item.name }}
            </option>
          </select>
        </label>

        <label>
          <span>{{ t('security.workflowState') }}</span>
          <select v-model="form.state_id" class="field-select" :disabled="workflowStatesLoading ||
            workflowStatesUnavailable ||
            !form.resource_id
            ">
            <option value="">
              {{ t('security.allStates') }}
            </option>
            <option v-for="item in workflowStateOptions" :key="item.id" :value="item.id">
              {{ item.label }}
            </option>
          </select>
        </label>

        <p v-if="workflowStatesUnavailable" class="inline-error" role="alert">
          {{ t('security.requestError') }}
        </p>

        <div class="form-grid">
          <label>
            <span>{{ t('security.participantType') }}</span>
            <select v-model="form.participant_type" class="field-select" required
              @change="onParticipantTypeChange(form.participant_type)">
              <option v-for="type in participantTypes" :key="type" :value="type">
                {{ type }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ t('security.participant') }}</span>
            <NSelect v-model:value="form.participant_ref_id" :options="participantSelectOptions"
              :loading="participantLoading" :remote="isRemoteParticipant" :filterable="true" :clearable="false"
              :placeholder="t('security.participant')" :aria-label="t('security.participant')"
              @search="searchParticipants" />
          </label>
        </div>

        <div class="action-editor">
          <div class="detail-actions">
            <strong>{{ t('security.actionEffects') }}</strong>

            <button v-if="
              hasBasePermission(
                'policy',
                editingId ? 'edit' : 'create'
              )
            " type="button" class="secondary-button" @click="addAction">
              ＋ {{ t('security.addAction') }}
            </button>
          </div>

          <div v-for="(row, index) in form.actions" :key="index" class="action-edit-row">
            <select v-model="row.resource_action_id" class="field-select" required
              :aria-label="t('security.actionEffects')">
              <option v-for="action in actionOptions" :key="action.id" :value="action.id">
                {{ action.code }}
              </option>
            </select>

            <select v-model="row.effect" class="field-select" required :aria-label="t('security.effectsSummary')">
              <option v-for="effect in effects" :key="effect" :value="effect">
                {{ effect }}
              </option>
            </select>

            <button v-if="
              hasBasePermission(
                'policy',
                editingId ? 'edit' : 'create'
              )
            " type="button" class="icon-button action-delete-button" :aria-label="t('common.delete')"
              :title="t('common.delete')" @click="removeAction(index)">
              <NIcon :component="TrashOutline" aria-hidden="true" />
            </button>
          </div>

          <!-- Validation: Không có Action / Effect rows -->
          <p v-if="form.actions.length === 0" class="inline-error" role="alert">
            Vui lòng thêm ít nhất một dòng Action / Effect trước khi lưu.
          </p>

          <!-- Validation: Action hoặc Effect chưa được chọn -->
          <p v-else-if="
            form.actions.some(
              row => !row.resource_action_id || !row.effect
            )
          " class="inline-error" role="alert">
            Vui lòng chọn đầy đủ Action và Effect cho tất cả các dòng.
          </p>

          <p class="security-note">
            {{ t('security.policyBasePermissionNote') }}
          </p>
        </div>
      </form>

      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="closeForm">
          <NIcon :size="20" aria-hidden="true"> <Refresh /> </NIcon>
          {{ t('common.cancel') }}
        </button>

        <button type="submit" class="primary-button" form="policy-form-input" :disabled="busy ||
          workflowStatesLoading ||
          workflowStatesUnavailable ||
          form.actions.length === 0 ||
          form.actions.some(
            row => !row.resource_action_id || !row.effect
          ) ||
          !form.participant_ref_id ||
          !hasBasePermission(
            'policy',
            editingId ? 'edit' : 'create'
          )
          ">
          <NIcon :size="20" aria-hidden="true"> <SaveOutline /> </NIcon>
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
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

.modal-card {

    width: min(100%, 760px);
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
