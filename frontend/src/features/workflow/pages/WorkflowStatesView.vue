<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  WorkflowState,
  WorkflowStateCreate,
  WorkflowStatePatch,
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
import { changeWorkflowState, listWorkflowStatePage, saveWorkflowState } from '@/services/workflow'
import { normalizeApiError, reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<WorkflowState>((query) => listWorkflowStatePage(query), { sort: 'code' })
const modalOpen = ref(false)
const editingId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const form = ref({
  code: '',
  name: '',
  description: '',
  status: 'ACTIVE' as WorkflowState['status'],
})
const { markClean, confirmDiscard } = useUnsavedChanges(form, modalOpen)

function closeForm(): void {
  if (busy.value || !confirmDiscard()) return
  markClean()
  modalOpen.value = false
}

const columns: readonly ListColumn<WorkflowState>[] = [
  { key: 'code', labelKey: 'workflow.code', sortable: true },
  { key: 'name', labelKey: 'workflow.name', sortable: true },
  { key: 'description', labelKey: 'workflow.description' },
  { key: 'status', labelKey: 'workflow.status', sortable: true, align: 'center' },
  { key: 'actions', labelKey: 'workflow.actions', align: 'end' },
]

const errorMessage = computed(() => {
  const notice = normalizeApiError(error.value)
  if (!notice) return error.value ? t('workflow.requestError') : ''
  return `${t(`errors.status.${notice.status}`)}${notice.code ? ` (${notice.code})` : ''}`
})

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
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}

function openCreate(): void {
  editingId.value = undefined
  form.value = { code: '', name: '', description: '', status: 'ACTIVE' }
  markClean()
  modalOpen.value = true
}

function openEdit(row: WorkflowState): void {
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
    const payload: WorkflowStateCreate | WorkflowStatePatch = {
      code: form.value.code,
      name: form.value.name,
      status: form.value.status,
      ...(form.value.description ? { description: form.value.description } : { description: null }),
    }
    await saveWorkflowState(payload, editingId.value)
    markClean()
    modalOpen.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

async function toggle(row: WorkflowState): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changeWorkflowState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
    await load()
  } catch (caught: unknown) {
    error.value = caught
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
      <h1>{{ t('workflow.statesTitle') }}</h1>
      <p>{{ t('workflow.statesDescription') }}</p>
    </div>
    <button data-ds-icon="add" v-if="hasBasePermission('workflow_state', 'create')" type="button" class="primary-button"
      @click="openCreate">
      {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel workflow-panel ds-page-panel">
    <div class="workflow-toolbar ds-page-toolbar">
      <ListQueryControls :show-page-size="false" :model-value="state.query" @apply="applyQuery" @reset="resetQuery" >
        <template #actions>
          <button data-ds-icon="refresh" type="button" class="secondary-button" @click="void load()">
            {{ t('common.refresh') }}
          </button>
        </template>
      </ListQueryControls>
    </div>
    <p v-if="error" class="inline-error" role="alert">{{ errorMessage }}</p>
    <ListShell :initial-loading="state.isInitialLoading.value" :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value" :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value" :error="state.queryState.error.value" :retryable="true"
      @retry="void load()">
      <ListTable :busy="state.isBackgroundRefreshing.value" :rows="state.rows.value" :columns="columns"
        :sort="state.query.sort" @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        ">
        <template #cell-actions="{ row }">
          <TableRowActions authorization-mode="effective" :view-scope-verified="true" :allowed-actions="rowAllowedActions(row)" :active="row.status === 'ACTIVE'" :busy="busy" :show-view="false" :show-edit="true"
            :show-toggle="true" edit-permission="workflow_state.edit" toggle-permission="workflow_state.setstate"
            :edit-label="t('common.edit')" :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')" @edit="openEdit(row)" @toggle="toggle(row)" />
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
  <AppDialogHost :open="modalOpen" size="md" @close="closeForm">
    <section v-if="modalOpen" class="ds-dialog-surface modal-card" role="dialog" aria-modal="true" :aria-label="t('workflow.stateForm')">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24">
            <FlagOutline />
          </NIcon>
          {{ editingId ? t('common.edit') : t('common.create') }} {{ t('workflow.state') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="closeForm" data-ds-icon="close">

        </button>
      </header>
      <form class="modal-card__body ds-dialog-form-fields" id="workflow-form" @submit.prevent="save">
        <label><span>{{ t('workflow.code') }}</span><input v-model="form.code" required /></label>
        <label><span>{{ t('workflow.name') }}</span><input v-model="form.name" required /></label>
        <label><span>{{ t('workflow.description') }}</span><textarea v-model="form.description" rows="3" />
        </label>
        <label><span>{{ t('workflow.status') }}</span><select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label>

      </form>

      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="closeForm" data-ds-icon="cancel">

          {{ t('common.cancel') }}
        </button>
        <button type="submit" class="primary-button" form="workflow-form"
          :disabled="busy || !hasBasePermission('workflow_state', editingId ? 'edit' : 'create')" data-ds-icon="save">

          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </AppDialogHost>
</template>

<style scoped>

</style>
