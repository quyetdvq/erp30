<script setup lang="ts">
import { onMounted, ref } from 'vue'
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
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import {
  changeProductUomState,
  listProductUomPage,
  saveProductUom,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<ProductUom>((query) => listProductUomPage(query), { sort: 'code' })
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailRow = ref<ProductUom | null>(null)
const editingId = ref<string>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const form = ref({ code: '', name: '', description: '', status: 'ACTIVE' as ProductUom['status'] })
const columns: readonly ListColumn<ProductUom>[] = [
  { key: 'code', labelKey: 'product.code', sortable: true },
  { key: 'name', labelKey: 'product.name', sortable: true },
  { key: 'description', labelKey: 'product.description' },
  { key: 'status', labelKey: 'product.status', sortable: true },
  { key: 'actions', labelKey: 'product.actions' },
]
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
  form.value = { code: '', name: '', description: '', status: 'ACTIVE' }
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
    <button
      v-if="hasBasePermission('product_uom', 'create')"
      type="button"
      class="primary-button"
      @click="openCreate"
    >
      {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel product-panel">
    <ListQueryControls
      :model-value="state.query"
      :filters="[
        {
          key: 'status',
          labelKey: 'product.status',
          type: 'select',
          options: [
            { value: 'ACTIVE', labelKey: 'status.active' },
            { value: 'INACTIVE', labelKey: 'status.inactive' },
          ],
        },
      ]"
      @apply="applyQuery"
      @reset="resetQuery"
    />
    <ListPagination
      :page="state.query.page"
      :page-size="state.query.page_size"
      :total="state.total.value"
      :disabled="state.isInitialLoading.value"
      :aria-label="t('product.topPagination')"
      @update:page="
        (value) => {
          state.setPage(value)
          void load()
        }
      "
      @update:page-size="
        (value) => {
          state.setPageSize(value)
          void load()
        }
      "
    />
    <ListShell
      :initial-loading="state.isInitialLoading.value"
      :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value"
      :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value"
      :error="state.queryState.error.value"
    >
      <ListTable :rows="state.rows.value" :columns="columns" :sort="state.query.sort">
        <template #cell-actions="{ row }"
          ><TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="hasBasePermission('product_uom', 'view')"
            :show-edit="hasBasePermission('product_uom', 'edit')"
            :show-toggle="hasBasePermission('product_uom', 'setstate')"
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
        /></template>
      </ListTable>
      <ListPagination
        :page="state.query.page"
        :page-size="state.query.page_size"
        :total="state.total.value"
        :aria-label="t('product.bottomPagination')"
        @update:page="
          (value) => {
            state.setPage(value)
            void load()
          }
        "
        @update:page-size="
          (value) => {
            state.setPageSize(value)
            void load()
          }
        "
      />
    </ListShell>
  </section>
  <TableDetailModal
    :open="detailOpen"
    :title="t('product.uomDetails')"
    :fields="
      detailRow
        ? [
            { key: 'code', label: t('product.code'), value: detailRow.code },
            { key: 'name', label: t('product.name'), value: detailRow.name },
            { key: 'description', label: t('product.description'), value: detailRow.description },
            { key: 'status', label: t('product.status'), status: detailRow.status },
          ]
        : []
    "
    @close="detailOpen = false"
  />
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('product.uomForm')">
      <header class="modal-card__header">
        <h2>{{ editingId ? t('common.edit') : t('common.create') }} {{ t('product.uom') }}</h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="modalOpen = false"
        >
          x
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="save">
        <label
          ><span>{{ t('product.code') }}</span
          ><input v-model="form.code" required /></label
        ><label
          ><span>{{ t('product.name') }}</span
          ><input v-model="form.name" required /></label
        ><label
          ><span>{{ t('product.description') }}</span
          ><textarea v-model="form.description" rows="3" /></label
        ><label
          ><span>{{ t('product.status') }}</span
          ><select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label
        >
        <p v-if="error" class="inline-error" role="alert">{{ t('product.requestError') }}</p>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="modalOpen = false">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="busy || !hasBasePermission('product_uom', editingId ? 'edit' : 'create')"
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
