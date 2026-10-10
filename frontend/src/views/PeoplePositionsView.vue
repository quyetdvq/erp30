<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Position, PositionCreate, State } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableDetailModal,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import { useListState } from '@/composables/useListState'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { changePositionState, listPositionPage, savePosition } from '@/services/employeeStructure'
import { reportApiError } from '@/services/errorBoundary'
import { useBasePermission } from '@/composables/useBasePermission'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<Position>((query) => listPositionPage(query), { sort: 'name' })
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailRow = ref<Position | null>(null)
const editingId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const form = ref({
  code: '',
  name: '',
  level: '',
  description: '',
  status: 'ACTIVE' as State['status'],
})
const { markClean, confirmDiscard } = useUnsavedChanges(form, modalOpen)

function closeForm(): void {
  if (busy.value || !confirmDiscard()) return
  markClean()
  modalOpen.value = false
}

const columns: readonly ListColumn<Position>[] = [
  { key: 'code', labelKey: 'people.code', sortable: true },
  { key: 'name', labelKey: 'people.name', sortable: true },
  { key: 'level', labelKey: 'people.level', sortable: true },
  { key: 'status', labelKey: 'people.status', sortable: true },
  { key: 'actions', labelKey: 'people.actions' },
]
const detailFields = computed<readonly TableDetailField[]>(() => {
  const row = detailRow.value
  if (!row) return []
  return [
    { key: 'code', label: t('people.code'), value: row.code },
    { key: 'name', label: t('people.name'), value: row.name },
    { key: 'level', label: t('people.level'), value: row.level == null ? null : String(row.level) },
    { key: 'description', label: t('people.description'), value: row.description },
    { key: 'status', label: t('people.status'), status: row.status },
  ]
})

async function load(): Promise<void> {
  error.value = null
  try {
    await state.apply()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
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
function openCreate(): void {
  editingId.value = undefined
  form.value = { code: '', name: '', level: '', description: '', status: 'ACTIVE' }
  markClean()
  modalOpen.value = true
}
function openEdit(row: Position): void {
  editingId.value = String(row.id)
  form.value = {
    code: row.code,
    name: row.name,
    level: row.level == null ? '' : String(row.level),
    description: row.description ?? '',
    status: row.status,
  }
  markClean()
  modalOpen.value = true
}
function openView(row: Position): void {
  detailRow.value = row
  detailOpen.value = true
}
async function save(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const payload: PositionCreate = {
      code: form.value.code,
      name: form.value.name,
      status: form.value.status,
      ...(form.value.level !== '' ? { level: Number(form.value.level) } : {}),
      ...(form.value.description ? { description: form.value.description } : {}),
    }
    await savePosition(payload, editingId.value)
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
async function toggle(row: Position): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changePositionState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
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
      <h1>{{ t('people.positionsTitle') }}</h1>
      <p>{{ t('people.positionsDescription') }}</p>
    </div>
    <button
      v-if="hasBasePermission('position', 'create')"
      type="button"
      class="primary-button"
      @click="openCreate"
    >
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel location-panel">
    <div class="location-toolbar">
      <ListQueryControls
        :model-value="state.query"
        @apply="applyQuery"
        @reset="resetQuery"
      /><button type="button" class="secondary-button" @click="void load()">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <p v-if="error" class="inline-error" role="alert">{{ t('people.requestError') }}</p>
    <ListShell
      :initial-loading="state.isInitialLoading.value"
      :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value"
      :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value"
      :error="state.queryState.error.value"
      :retryable="true"
      @retry="void load()"
    >
      <ListTable
        :busy="state.isBackgroundRefreshing.value"
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
            :show-view="true"
            :show-edit="true"
            :show-toggle="true"
            view-permission="position.view"
            edit-permission="position.edit"
            toggle-permission="position.setstate"
            :view-label="t('common.view')"
            :edit-label="t('common.edit')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            @view="openView(row)"
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
  <TableDetailModal
    :open="detailOpen"
    :title="t('common.view') + ' - ' + t('people.position')"
    :fields="detailFields"
    @close="detailOpen = false"
  />
  <div v-if="modalOpen" class="modal-backdrop">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('people.positionForm')"
    >
      <header class="modal-card__header">
        <h2>{{ editingId ? t('common.edit') : t('common.create') }} {{ t('people.position') }}</h2>
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
        <div class="form-grid">
          <label
            ><span>{{ t('people.code') }}</span
            ><input v-model="form.code" required /></label
          ><label
            ><span>{{ t('people.name') }}</span
            ><input v-model="form.name" required
          /></label>
        </div>
        <label
          ><span>{{ t('people.level') }}</span
          ><input v-model="form.level" type="number" min="0" step="1" /></label
        ><label
          ><span>{{ t('people.description') }}</span
          ><textarea v-model="form.description" rows="3" /></label
        ><label
          ><span>{{ t('people.status') }}</span
          ><select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
            <option value="CLOSED">{{ t('status.closed') }}</option>
          </select></label
        >
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="closeForm">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="busy || !hasBasePermission('position', editingId ? 'edit' : 'create')"
          >
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.location-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
}
.location-toolbar {
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
  margin: 6px -18px -18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.icon-button {
  border: 0;
  background: transparent;
  color: #536b7b;
  font-size: 22px;
  cursor: pointer;
}
</style>
