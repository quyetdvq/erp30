<script setup lang="ts">
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
    <button
      v-if="hasBasePermission('workflow_state', 'create')"
      type="button"
      class="primary-button"
      @click="openCreate"
    >
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel workflow-panel">
    <div class="workflow-toolbar">
      <ListQueryControls :model-value="state.query" @apply="applyQuery" @reset="resetQuery" />
      <button type="button" class="secondary-button" @click="void load()">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <p v-if="error" class="inline-error" role="alert">{{ errorMessage }}</p>
    <ListShell
      :initial-loading="state.isInitialLoading.value"
      :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value"
      :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value"
      :error="state.queryState.error.value"
    >
      <ListTable
        :rows="state.rows.value"
        :columns="columns"
        :sort="state.query.sort"
        @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        "
      >
        <template #cell-actions="{ row }">
          <TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="false"
            :show-edit="true"
            :show-toggle="true"
            edit-permission="workflow_state.edit"
            toggle-permission="workflow_state.setstate"
            :edit-label="t('common.edit')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            @edit="openEdit(row)"
            @toggle="toggle(row)"
          />
        </template>
      </ListTable>
      <ListPagination
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
      />
    </ListShell>
  </section>
  <div v-if="modalOpen" class="modal-backdrop">
    <section
      class="modal-card workflow-form-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('workflow.stateForm')"
    >
      <header class="modal-card__header">
        <h2>{{ editingId ? t('common.edit') : t('common.create') }} {{ t('workflow.state') }}</h2>
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
          ><span>{{ t('workflow.code') }}</span
          ><input v-model="form.code" required
        /></label>
        <label
          ><span>{{ t('workflow.name') }}</span
          ><input v-model="form.name" required
        /></label>
        <label
          ><span>{{ t('workflow.description') }}</span
          ><textarea v-model="form.description" rows="3" />
        </label>
        <label
          ><span>{{ t('workflow.status') }}</span
          ><select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label
        >
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="modalOpen = false">
            {{ t('common.cancel') }}
          </button>
          <button
            type="submit"
            class="primary-button"
            :disabled="busy || !hasBasePermission('workflow_state', editingId ? 'edit' : 'create')"
          >
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.workflow-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
}
.workflow-toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.inline-error {
  margin: 0;
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
  width: min(100%, 680px);
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
.modal-card__footer {
  justify-content: flex-end;
  margin: 0;
  padding: 14px 22px 18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.workflow-form-modal .modal-card__body .modal-card__footer {
  margin: 4px -22px -22px;
  padding: 14px 22px 0;
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
  cursor: pointer;
}
</style>
