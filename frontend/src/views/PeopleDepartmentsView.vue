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
import {
  changeDepartmentState,
  listDepartmentPage,
  saveDepartment,
} from '@/services/employeeStructure'
import { reportApiError } from '@/services/errorBoundary'
import { useBasePermission } from '@/composables/useBasePermission'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
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
      status: form.value.status,
      ...(form.value.parent_id ? { parent_id: form.value.parent_id } : {}),
      ...(form.value.description ? { description: form.value.description } : {}),
    }
    await saveDepartment(payload, editingId.value)
    modalOpen.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function toggle(row: Department): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changeDepartmentState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
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
      <h1>{{ t('people.departmentsTitle') }}</h1>
      <p>{{ t('people.departmentsDescription') }}</p>
    </div>
    <button
      v-if="hasBasePermission('department', 'create')"
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
    >
      <ListTable
        :rows="treeRows"
        :columns="columns"
        :sort="state.query.sort"
        @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        "
      >
        <template #cell-code="{ row }">
          <span :style="{ paddingLeft: `${row.depth * 22}px` }"
            ><strong>{{ row.code }}</strong></span
          >
        </template>
        <template #cell-actions="{ row }">
          <TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="true"
            :show-edit="true"
            :show-toggle="true"
            view-permission="department.view"
            edit-permission="department.edit"
            toggle-permission="department.setstate"
            :edit-label="t('common.edit')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            :view-label="t('common.view')"
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
    :title="t('common.view') + ' - ' + t('people.department')"
    :fields="detailFields"
    @close="detailOpen = false"
  />
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('people.departmentForm')"
    >
      <header class="modal-card__header">
        <h2>
          {{ editingId ? t('common.edit') : t('common.create') }} {{ t('people.department') }}
        </h2>
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
          ><span>{{ t('people.parent') }}</span
          ><SearchableSelect
            :model-value="form.parent_id"
            :options="parentSelectOptions"
            :placeholder="t('people.root')"
            :aria-label="t('people.parent')"
            @update:model-value="
              (value) => (form.parent_id = typeof value === 'string' ? value : '')
            "
        /></label>
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
          <button type="button" class="secondary-button" @click="modalOpen = false">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="busy || !hasBasePermission('department', editingId ? 'edit' : 'create')"
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
