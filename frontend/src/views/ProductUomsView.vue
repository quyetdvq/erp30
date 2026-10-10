<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ProductUom, ProductUomCreate, ProductUomPatch } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableDetailModal,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn, ListFilterDefinition, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import {
  changeProductUomState,
  listProductUomPage,
  saveProductUom,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<ProductUom>(listProductUomPage, { sort: 'code' })
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailRow = ref<ProductUom | null>(null)
const editingId = ref<string>()
const busy = ref(false)
const error = ref<unknown | null>(null)

type ProductUomForm = Pick<ProductUom, 'code' | 'name' | 'status'> & { description: string }

function createEmptyForm(): ProductUomForm {
  return { code: '', name: '', description: '', status: 'ACTIVE' }
}

const form = ref<ProductUomForm>(createEmptyForm())

// Local, single-column sort: only reorder the rows already loaded for this page.
const unsaved = useUnsavedChanges(form, modalOpen)
function closeForm(): void {
  if (!unsaved.confirmDiscard()) return
  modalOpen.value = false
  unsaved.markClean()
}

const localSort = ref('')
const collator = new Intl.Collator('vi', { numeric: true, sensitivity: 'base' })
const visibleRows = computed(() => {
  const term = localSort.value
  const field = term.startsWith('-') ? term.slice(1) : term
  if (field !== 'code' && field !== 'name' && field !== 'status') return state.rows.value

  const direction = term.startsWith('-') ? -1 : 1
  // Copy first; mutating the response array would also alter unsorted order.
  return [...state.rows.value].sort(
    (first, second) =>
      direction * collator.compare(String(first[field] ?? ''), String(second[field] ?? '')),
  )
})

const columns: readonly ListColumn<ProductUom>[] = [
  { key: 'code', labelKey: 'product.code', sortable: true },
  { key: 'name', labelKey: 'product.name', sortable: true },
  { key: 'description', labelKey: 'product.description' },
  { key: 'status', labelKey: 'product.status', sortable: true },
  { key: 'actions', labelKey: 'product.actions', align: 'end' },
]

const statusFilters: readonly ListFilterDefinition[] = [
  {
    key: 'status',
    labelKey: 'product.status',
    type: 'select',
    options: [
      { value: 'ACTIVE', labelKey: 'status.active' },
      { value: 'INACTIVE', labelKey: 'status.inactive' },
    ],
  },
]

// Base Permission is a UI hint; final authorization always happens on the server.
const permissions = computed(() => ({
  create: hasBasePermission('product_uom', 'create'),
  view: hasBasePermission('product_uom', 'view'),
  edit: hasBasePermission('product_uom', 'edit'),
  setstate: hasBasePermission('product_uom', 'setstate'),
}))

const detailFields = computed(() => {
  const row = detailRow.value
  if (!row) return []

  return [
    { key: 'code', label: t('product.code'), value: row.code },
    { key: 'name', label: t('product.name'), value: row.name },
    { key: 'description', label: t('product.description'), value: row.description },
    { key: 'status', label: t('product.status'), status: row.status },
  ]
})

let latestLoadId = 0

async function load(): Promise<void> {
  const loadId = ++latestLoadId
  error.value = null
  try {
    await state.apply()
  } catch (caught: unknown) {
    // Ignore errors from requests superseded by a newer load.
    if (loadId !== latestLoadId) return
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

// ListTable may include previous sort terms; the clicked column is last.
// This changes only the displayed order, never the API query or pagination.
function handleSortChange(sort: string): void {
  const term = sort.split(',').pop() ?? ''
  const field = term.startsWith('-') ? term.slice(1) : term
  if (term && field !== 'code' && field !== 'name' && field !== 'status') return
  localSort.value = term
}

function handlePageChange(page: number): void {
  state.setPage(page)
  void load()
}

function handlePageSizeChange(pageSize: number): void {
  state.setPageSize(pageSize)
  void load()
}

function resetQuery(): void {
  localSort.value = ''
  state.reset()
  void load()
}

function openCreate(): void {
  editingId.value = undefined
  form.value = createEmptyForm()
  unsaved.markClean()
  modalOpen.value = true
}

function openEdit(row: ProductUom): void {
  editingId.value = String(row.id)
  form.value = {
    code: row.code,
    name: row.name,
    description: row.description ?? '',
    status: row.status,
  }
  unsaved.markClean()
  modalOpen.value = true
}

function openView(row: ProductUom): void {
  detailRow.value = row
  detailOpen.value = true
}

async function save(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const payload: ProductUomCreate | ProductUomPatch = {
      code: form.value.code,
      name: form.value.name,
      status: form.value.status,
      ...(form.value.description ? { description: form.value.description } : {}),
    }
    await saveProductUom(payload, editingId.value)
    unsaved.markClean()
    modalOpen.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

async function toggle(row: ProductUom): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changeProductUomState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
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
      <h1>{{ t('product.uomsTitle') }}</h1>
      <p>{{ t('product.uomsDescription') }}</p>
    </div>
    <button v-if="permissions.create" type="button" class="primary-button" @click="openCreate">
      {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel product-panel">
    <ListQueryControls
      :model-value="state.query"
      :filters="statusFilters"
      @apply="applyQuery"
      @reset="resetQuery"
    />
    <ListPagination
      :page="state.query.page"
      :page-size="state.query.page_size"
      :total="state.total.value"
      :disabled="state.isInitialLoading.value"
      :aria-label="t('product.topPagination')"
      @update:page="handlePageChange"
      @update:page-size="handlePageSizeChange"
    />
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
        :rows="visibleRows"
        :columns="columns"
        :sort="localSort"
        @sort-change="handleSortChange"
      >
        <template #cell-actions="{ row }">
          <TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="permissions.view"
            :show-edit="permissions.edit"
            :show-toggle="permissions.setstate"
            view-permission="product_uom.view"
            edit-permission="product_uom.edit"
            toggle-permission="product_uom.setstate"
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
        :aria-label="t('product.bottomPagination')"
        @update:page="handlePageChange"
        @update:page-size="handlePageSizeChange"
      />
    </ListShell>
  </section>
  <TableDetailModal
    :open="detailOpen"
    :title="t('product.uomDetails')"
    :fields="detailFields"
    @close="detailOpen = false"
  />
  <div v-if="modalOpen" class="modal-backdrop">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('product.uomForm')">
      <header class="modal-card__header">
        <h2>{{ editingId ? t('common.edit') : t('common.create') }} {{ t('product.uom') }}</h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="closeForm"
        >
          x
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="save">
        <label>
          <span>{{ t('product.code') }}</span>
          <input v-model="form.code" required />
        </label>
        <label>
          <span>{{ t('product.name') }}</span>
          <input v-model="form.name" required />
        </label>
        <label>
          <span>{{ t('product.description') }}</span>
          <textarea v-model="form.description" rows="3" />
        </label>
        <label v-if="!editingId">
          <span>{{ t('product.status') }}</span>
          <select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select>
        </label>
        <p v-if="error" class="inline-error" role="alert">{{ t('product.requestError') }}</p>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="closeForm">
            {{ t('common.cancel') }}
          </button>
          <button
            type="submit"
            class="primary-button"
            :disabled="busy || !(editingId ? permissions.edit : permissions.create)"
          >
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.product-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
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
  cursor: pointer;
}
</style>
