<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Department, DepartmentCreate, State } from '@/api/generated/types.gen'
import { ListPagination, ListQueryControls, ListShell, TableRowActions } from '@/components/lists'
import type { ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import {
  changeDepartmentState,
  listDepartmentPage,
  saveDepartment,
} from '@/services/employeeStructure'
import { reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const state = useListState<Department>((query) => listDepartmentPage(query), { sort: 'name' })
const selectedId = ref<string | null>(null)
const modalOpen = ref(false)
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
  const output: Array<{ item: Department; depth: number }> = []
  function walk(parent: string, depth: number): void {
    for (const item of byParent.value.get(parent) ?? []) {
      output.push({ item, depth })
      walk(String(item.id), depth + 1)
    }
  }
  walk('ROOT', 0)
  return output
})
const selected = computed(
  () => state.rows.value.find((row) => String(row.id) === selectedId.value) ?? null,
)
const parentOptions = computed(() =>
  state.rows.value.filter((row) => String(row.id) !== editingId.value && row.status === 'ACTIVE'),
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
    <button type="button" class="primary-button" @click="openCreate">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel people-layout">
    <div class="toolbar">
      <ListQueryControls
        :model-value="state.query"
        @apply="applyQuery"
        @reset="resetQuery"
      /><button type="button" class="secondary-button" @click="void load">
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
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ t('people.code') }}</th>
            <th>{{ t('people.name') }}</th>
            <th>{{ t('people.parent') }}</th>
            <th>{{ t('people.status') }}</th>
            <th>{{ t('people.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="node in treeRows"
            :key="node.item.id"
            :class="{ 'row-selected': selectedId === String(node.item.id) }"
            @click="selectedId = String(node.item.id)"
          >
            <td :style="{ paddingLeft: `${node.depth * 22 + 13}px` }">
              <strong>{{ node.item.code }}</strong>
            </td>
            <td>{{ node.item.name }}</td>
            <td>
              {{
                state.rows.value.find((parent) => String(parent.id) === String(node.item.parent_id))
                  ?.name ?? t('people.root')
              }}
            </td>
            <td>
              <span
                class="status-pill"
                :class="{ 'status-pill--off': node.item.status !== 'ACTIVE' }"
                >{{ t(`status.${node.item.status.toLowerCase()}`) }}</span
              >
            </td>
            <td>
              <TableRowActions
                :active="node.item.status === 'ACTIVE'"
                :busy="busy"
                :show-view="false"
                :show-edit="true"
                :show-toggle="true"
                :edit-label="t('common.edit')"
                :activate-label="t('common.activate')"
                :deactivate-label="t('common.deactivate')"
                @edit="openEdit(node.item)"
                @toggle="toggle(node.item)"
              />
            </td>
          </tr>
        </tbody>
      </table>
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
          ><select v-model="form.parent_id">
            <option value="">{{ t('people.root') }}</option>
            <option v-for="row in parentOptions" :key="row.id" :value="row.id">
              {{ row.code }} - {{ row.name }}
            </option>
          </select></label
        >
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
          ><button type="submit" class="primary-button" :disabled="busy">
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.people-layout {
  overflow: hidden;
}
.row-selected td {
  background: #f0f8fb;
}
.inline-error {
  margin: 10px 14px 0;
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
