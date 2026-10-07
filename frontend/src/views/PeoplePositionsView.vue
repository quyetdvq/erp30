<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Position, PositionCreate, State } from '@/api/generated/types.gen'
import { ListPagination, ListQueryControls, ListShell, TableRowActions } from '@/components/lists'
import type { ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { changePositionState, listPositionPage, savePosition } from '@/services/employeeStructure'
import { reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const state = useListState<Position>((query) => listPositionPage(query), { sort: 'name' })
const modalOpen = ref(false)
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
  modalOpen.value = true
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
    <button type="button" class="primary-button" @click="openCreate">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel">
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
      ><table class="data-table">
        <thead>
          <tr>
            <th>{{ t('people.code') }}</th>
            <th>{{ t('people.name') }}</th>
            <th>{{ t('people.level') }}</th>
            <th>{{ t('people.status') }}</th>
            <th>{{ t('people.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in state.rows.value" :key="row.id">
            <td>{{ row.code }}</td>
            <td>{{ row.name }}</td>
            <td>{{ row.level ?? '—' }}</td>
            <td>
              <span class="status-pill" :class="{ 'status-pill--off': row.status !== 'ACTIVE' }">{{
                t(`status.${row.status.toLowerCase()}`)
              }}</span>
            </td>
            <td>
              <TableRowActions
                :active="row.status === 'ACTIVE'"
                :busy="busy"
                :show-view="false"
                :show-edit="true"
                :show-toggle="true"
                :edit-label="t('common.edit')"
                :activate-label="t('common.activate')"
                :deactivate-label="t('common.deactivate')"
                @edit="openEdit(row)"
                @toggle="toggle(row)"
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
    /></ListShell>
  </section>
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
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
          @click="modalOpen = false"
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
