<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Department, DepartmentCreate, State } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableDetailModal,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn } from '@/components/lists/listTypes'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import type { ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { listDepartmentPage, saveDepartment } from '@/services/employeeStructure'
import { reportApiError } from '@/services/errorBoundary'
import { useBasePermission } from '@/composables/useBasePermission'
import { useBusinessWorkflowStates } from '@/composables/useBusinessWorkflowStates'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const workflowStates = useBusinessWorkflowStates('department')
const state = useListState<Department>((query) => listDepartmentPage(query), { sort: 'name' })
const selectedId = ref<string | null>(null)
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailRow = ref<Department | null>(null)
const editingId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const form = ref({
  parent_id: '',
  code: '',
  name: '',
  description: '',
  status: 'ACTIVE' as State['status'],
})
const { markClean, confirmDiscard } = useUnsavedChanges(form, modalOpen)

function closeForm(): void {
  if (busy.value || !confirmDiscard()) return
  markClean()
  modalOpen.value = false
}

const byParent = computed(() => {
  const groups = new Map<string, Department[]>()
  for (const row of state.rows.value) {
    const key = row.parent_id ? String(row.parent_id) : 'ROOT'
    groups.set(key, [...(groups.get(key) ?? []), row])
  }
  for (const rows of groups.values())
    rows.sort((a, b) => `${a.code}${a.name}`.localeCompare(`${b.code}${b.name}`))
  return groups
})
const treeRows = computed(() => {
  const output: Array<Department & { depth: number }> = []
  function walk(parent: string, depth: number): void {
    for (const item of byParent.value.get(parent) ?? []) {
      output.push({ ...item, depth })
      walk(String(item.id), depth + 1)
    }
  }
  walk('ROOT', 0)
  return output
})
const columns: readonly ListColumn<Department & { depth: number }>[] = [
  { key: 'code', labelKey: 'people.code', sortable: true },
  { key: 'name', labelKey: 'people.name', sortable: true },
  {
    key: 'parent_id',
    labelKey: 'people.parent',
    value: (row) =>
      state.rows.value.find((parent) => String(parent.id) === String(row.parent_id))?.name ??
      t('people.root'),
  },
  { key: 'status', labelKey: 'people.status', sortable: true },
  { key: 'actions', labelKey: 'people.actions' },
]
const detailFields = computed<readonly TableDetailField[]>(() => {
  const row = detailRow.value
  if (!row) return []
  return [
    { key: 'code', label: t('people.code'), value: row.code },
    { key: 'name', label: t('people.name'), value: row.name },
    {
      key: 'parent',
      label: t('people.parent'),
      value:
        state.rows.value.find((parent) => String(parent.id) === String(row.parent_id))?.name ??
        t('people.root'),
    },
    { key: 'description', label: t('people.description'), value: row.description },
    { key: 'status', label: t('people.status'), status: row.status },
  ]
})
const selected = computed(
  () => state.rows.value.find((row) => String(row.id) === selectedId.value) ?? null,
)
const parentOptions = computed(() =>
  state.rows.value.filter((row) => String(row.id) !== editingId.value && row.status === 'ACTIVE'),
)
const parentSelectOptions = computed(() =>
  parentOptions.value.map((row) => ({
    value: String(row.id),
    label: `${row.code} - ${row.name}`,
  })),
)

async function load(): Promise<void> {
  error.value = null
  try {
    await state.apply()
    if (!selectedId.value && state.rows.value[0]) selectedId.value = String(state.rows.value[0].id)
    if (selectedId.value && !selected.value)
      selectedId.value = state.rows.value[0] ? String(state.rows.value[0].id) : null
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
  form.value = {
    parent_id: selected.value ? String(selected.value.id) : '',
    code: '',
    name: '',
    description: '',
    status: 'ACTIVE',
  }
  markClean()
  modalOpen.value = true
}
function openEdit(row: Department): void {
  editingId.value = String(row.id)
  form.value = {
    parent_id: row.parent_id ? String(row.parent_id) : '',
    code: row.code,
    name: row.name,
    description: row.description ?? '',
    status: row.status,
  }
  markClean()
  modalOpen.value = true
}
function openView(row: Department): void {
  detailRow.value = row
  detailOpen.value = true
}
async function save(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const payload: DepartmentCreate = {
      code: form.value.code,
      name: form.value.name,
      ...(form.value.parent_id ? { parent_id: form.value.parent_id } : {}),
      ...(form.value.description ? { description: form.value.description } : {}),
    }
    await saveDepartment(payload, editingId.value)
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
async function afterWorkflowChange(): Promise<void> {
  await load()
}
onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('people.departmentsTitle') }}</h1>
      <p>{{ t('people.departmentsDescription') }}</p>
    </div>
    <button v-if="hasBasePermission('department', 'create')" type="button" class="primary-button" @click="openCreate">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel location-panel">
    <div class="location-toolbar">
      <ListQueryControls :model-value="state.query" @apply="applyQuery" @reset="resetQuery" /><button type="button"
        class="secondary-button" @click="void load()">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <p v-if="error" class="inline-error" role="alert">{{ t('people.requestError') }}</p>
    <ListShell :initial-loading="state.isInitialLoading.value" :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value" :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value" :error="state.queryState.error.value" :retryable="true"
      @retry="void load()">
      <ListTable :busy="state.isBackgroundRefreshing.value" :rows="treeRows" :columns="columns" :sort="state.query.sort"
        @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        ">
        <template #cell-code="{ row }">
          <span :style="{ paddingLeft: `${row.depth * 22}px` }"><strong>{{ row.code }}</strong></span>
        </template>
        <template #cell-actions="{ row }">
          <TableRowActions :active="row.status === 'ACTIVE'" :state-target-id="String(row.id)" :busy="busy"
            :show-view="true" :show-edit="true" :show-toggle="true" view-permission="department.view"
            edit-permission="department.edit" toggle-permission="department.setstate" :edit-label="t('common.edit')"
            :activate-label="t('common.activate')" :deactivate-label="t('common.deactivate')"
            :view-label="t('common.view')" @view="openView(row)" @edit="openEdit(row)"
            @state-changed="afterWorkflowChange" />
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
  <TableDetailModal :open="detailOpen" :title="t('common.view') + ' - ' + t('people.department')" :fields="detailFields"
    @close="detailOpen = false" />
  <div v-if="modalOpen" class="modal-backdrop">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('people.departmentForm')">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24"> <PeopleOutline /> </NIcon>
          {{ editingId ? t('common.edit') : t('common.create') }} {{ t('people.department') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="closeForm">
        </button>
      </header>
      <form class="modal-card__body" id="department-form" @submit.prevent="save">
        <label><span>{{ t('people.parent') }}</span>
          <SearchableSelect :model-value="form.parent_id" :options="parentSelectOptions" :placeholder="t('people.root')"
            :aria-label="t('people.parent')" @update:model-value="
              (value) => (form.parent_id = typeof value === 'string' ? value : '')
            " />
        </label>
        <div class="form-grid">
          <label><span>{{ t('people.code') }}</span><input v-model="form.code" required /></label><label><span>{{
            t('people.name') }}</span><input v-model="form.name" required /></label>
        </div>
        <label><span>{{ t('people.description') }}</span><textarea v-model="form.description" rows="3" /></label><label
          v-if="editingId">
          <span>{{ t('people.status') }}</span>
          <output>{{ form.status }}</output>
        </label>
        <p v-else class="workflow-initial-note" role="note">
          {{ t('workflow.initialManaged') }}
          <strong v-if="workflowStates.initial.value">{{ workflowStates.initial.value }}</strong>
        </p>

      </form>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="closeForm">
          <NIcon :size="20"> <Refresh /> </NIcon>
          {{ t('common.cancel') }}
        </button>
          
          <button type="submit" class="primary-button"  form="department-form" 
          :disabled="busy || !hasBasePermission('department', editingId ? 'edit' : 'create')">
          <NIcon :size="20"> <SaveOutline /> </NIcon>
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
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

.modal-card {
  width: min(100%, 680px);
  max-height: calc(100vh - 40px);
  overflow: auto;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
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


</style>
