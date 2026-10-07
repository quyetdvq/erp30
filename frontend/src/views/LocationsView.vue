<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

import type { Province, State, Ward } from '@/api/generated/types.gen'
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
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { useListState } from '@/composables/useListState'
import {
  changeProvinceState,
  changeWardState,
  listProvincePage,
  listWardPage,
  saveProvince,
  saveWard,
} from '@/services/foundation'
import { reportApiError } from '@/services/errorBoundary'

type LocationRow = Province | Ward

const { t } = useI18n()
const route = useRoute()
const isWards = computed(() => route.path.endsWith('/wards'))
const error = ref<unknown | null>(null)
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailRow = ref<LocationRow | null>(null)
const editingId = ref<string | undefined>()
const mutationBusy = ref(false)
const provinces = ref<Province[]>([])
const form = ref({
  code: '',
  name: '',
  name_full: '',
  province_id: '',
  status: 'ACTIVE' as State['status'],
})

const state = useListState<LocationRow>(
  (query) => (isWards.value ? listWardPage(query) : listProvincePage(query)),
  {
    filter: { status: 'ACTIVE' },
  },
)

const statusFilters = computed(() => [
  {
    key: 'status',
    labelKey: 'locations.status',
    type: 'select' as const,
    options: [
      { value: 'ACTIVE', labelKey: 'status.active' },
      { value: 'INACTIVE', labelKey: 'status.inactive' },
      { value: 'CLOSED', labelKey: 'status.closed' },
    ],
  },
])
const wardFilters = computed(() => [
  ...statusFilters.value,
  {
    key: 'province_id',
    labelKey: 'locations.province',
    type: 'select' as const,
    searchable: true,
    options: provinces.value.map((province) => ({
      value: String(province.id),
      labelKey: 'locations.province',
      label: `${province.code} - ${province.name}`,
    })),
  },
])
const filters = computed(() => (isWards.value ? wardFilters.value : statusFilters.value))
const title = computed(() =>
  isWards.value ? t('locations.wardsTitle') : t('locations.provincesTitle'),
)
const description = computed(() =>
  isWards.value ? t('locations.wardsDescription') : t('locations.provincesDescription'),
)
const provinceOptions = computed(() =>
  provinces.value.filter(
    (province) => province.status === 'ACTIVE' || String(province.id) === form.value.province_id,
  ),
)
const provinceSelectOptions = computed(() =>
  provinceOptions.value.map((province) => ({
    value: String(province.id),
    label: `${province.code} - ${province.name}`,
  })),
)
const detailFields = computed<readonly TableDetailField[]>(() => {
  const row = detailRow.value
  if (!row) return []
  const fields: TableDetailField[] = [
    { key: 'code', label: t('locations.code'), value: row.code },
    { key: 'name', label: t('locations.name'), value: row.name },
  ]
  if (isWards.value) {
    fields.push(
      { key: 'name_full', label: t('locations.fullName'), value: (row as Ward).name_full },
      {
        key: 'province',
        label: t('locations.province'),
        value: provinceName(String((row as Ward).province_id)),
      },
    )
  }
  fields.push({ key: 'status', label: t('locations.status'), status: row.status })
  return fields
})

const columns = computed<readonly ListColumn<LocationRow>[]>(() =>
  isWards.value
    ? [
        { key: 'code', labelKey: 'locations.code', sortable: true },
        { key: 'name', labelKey: 'locations.name', sortable: true },
        { key: 'name_full', labelKey: 'locations.fullName' },
        {
          key: 'province_id',
          labelKey: 'locations.province',
          value: (row) => provinceName(String((row as Ward).province_id)),
        },
        { key: 'status', labelKey: 'locations.status', sortable: true },
        { key: 'actions', labelKey: 'locations.actions' },
      ]
    : [
        { key: 'code', labelKey: 'locations.code', sortable: true },
        { key: 'name', labelKey: 'locations.name', sortable: true },
        { key: 'status', labelKey: 'locations.status', sortable: true },
        { key: 'actions', labelKey: 'locations.actions' },
      ],
)

function provinceName(id: string): string {
  const province = provinces.value.find((item) => String(item.id) === id)
  return province ? `${province.code} - ${province.name}` : id
}

async function loadProvinces(): Promise<void> {
  try {
    provinces.value = (
      await listProvincePage({ q: '', page: 1, page_size: 200, sort: 'name', filter: {} })
    ).data
  } catch (caught: unknown) {
    reportApiError(caught)
  }
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
  form.value = { code: '', name: '', name_full: '', province_id: '', status: 'ACTIVE' }
  modalOpen.value = true
}

function openEdit(row: LocationRow): void {
  editingId.value = String(row.id)
  form.value = {
    code: row.code,
    name: row.name,
    name_full: isWards.value ? ((row as Ward).name_full ?? '') : '',
    province_id: isWards.value ? String((row as Ward).province_id) : '',
    status: row.status,
  }
  modalOpen.value = true
}

function openView(row: LocationRow): void {
  detailRow.value = row
  detailOpen.value = true
}

async function save(): Promise<void> {
  mutationBusy.value = true
  error.value = null
  try {
    if (isWards.value) {
      if (!form.value.province_id) throw new Error(t('locations.provinceRequired'))
      await saveWard(
        {
          province_id: form.value.province_id,
          code: form.value.code,
          name: form.value.name,
          name_full: form.value.name_full || null,
          status: form.value.status,
        },
        editingId.value,
      )
    } else {
      await saveProvince(
        { code: form.value.code, name: form.value.name, status: form.value.status },
        editingId.value,
      )
    }
    modalOpen.value = false
    await load()
    await loadProvinces()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    mutationBusy.value = false
  }
}

async function toggleState(row: LocationRow): Promise<void> {
  mutationBusy.value = true
  error.value = null
  try {
    const next = row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'
    if (isWards.value) await changeWardState(String(row.id), next)
    else await changeProvinceState(String(row.id), next)
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    mutationBusy.value = false
  }
}

watch(isWards, () => {
  state.reset()
  void load()
})

onMounted(async () => {
  await Promise.all([load(), loadProvinces()])
})
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ title }}</h1>
      <p>{{ description }}</p>
    </div>
    <button type="button" class="primary-button" @click="openCreate">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel location-panel">
    <div class="location-toolbar">
      <ListQueryControls
        :model-value="state.query"
        :filters="filters"
        @apply="applyQuery"
        @reset="resetQuery"
      />
      <button type="button" class="secondary-button" @click="void load()">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <p v-if="error" class="inline-error" role="alert">{{ t('locations.error') }}</p>
    <ListShell
      :title="title"
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
            :busy="mutationBusy"
            :show-view="true"
            :show-edit="true"
            :show-toggle="true"
            :edit-label="t('common.edit')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            :view-label="t('common.view')"
            @view="openView(row)"
            @edit="openEdit(row)"
            @toggle="toggleState(row)"
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
    :title="t('common.view') + ' - ' + title"
    :fields="detailFields"
    @close="detailOpen = false"
  />
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('common.form')">
      <header>
        <div>
          <h2>{{ editingId ? t('common.edit') : t('common.create') }}</h2>
          <p>{{ title }}</p>
        </div>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="modalOpen = false"
        >
          ×
        </button>
      </header>
      <form @submit.prevent="save">
        <label v-if="isWards"
          ><span>{{ t('locations.province') }}</span
          ><SearchableSelect
            :model-value="form.province_id"
            :options="provinceSelectOptions"
            :placeholder="t('locations.selectProvince')"
            :aria-label="t('locations.province')"
            @update:model-value="
              (value) => (form.province_id = typeof value === 'string' ? value : '')
            "
        /></label>
        <label
          ><span>{{ t('locations.code') }}</span
          ><input v-model="form.code" required
        /></label>
        <label
          ><span>{{ t('locations.name') }}</span
          ><input v-model="form.name" required
        /></label>
        <label v-if="isWards"
          ><span>{{ t('locations.fullName') }}</span
          ><input v-model="form.name_full"
        /></label>
        <label
          ><span>{{ t('locations.status') }}</span
          ><select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
            <option value="CLOSED">{{ t('status.closed') }}</option>
          </select></label
        >
        <footer>
          <button type="button" class="secondary-button" @click="modalOpen = false">
            {{ t('common.cancel') }}</button
          ><button type="submit" class="primary-button" :disabled="mutationBusy">
            {{ mutationBusy ? t('common.saving') : t('common.save') }}
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
  width: min(100%, 520px);
  max-height: calc(100vh - 40px);
  overflow: auto;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}
.modal-card header,
.modal-card footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 15px 18px;
  border-bottom: 1px solid #e4ebef;
}
.modal-card header h2 {
  margin: 0;
  color: #173c59;
  font-size: 18px;
}
.modal-card header p {
  margin: 4px 0 0;
  color: #8292a0;
  font-size: 12px;
}
.modal-card form {
  display: grid;
  gap: 12px;
  padding: 18px;
}
.modal-card label {
  display: grid;
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}
.modal-card input,
.modal-card select {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  font: inherit;
}
.modal-card footer {
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
