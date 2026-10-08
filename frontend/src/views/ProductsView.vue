<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { Product, ProductGroup, ProductUom } from '@/api/generated/types.gen'
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
import {
  changeProductState,
  listProductGroupPage,
  listProductPage,
  listProductUomPage,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const state = useListState<Product>((query) => listProductPage(query), { sort: 'product_code' })
const groups = ref<ProductGroup[]>([])
const uoms = ref<ProductUom[]>([])
const busy = ref(false)
const error = ref<unknown | null>(null)
const columns: readonly ListColumn<Product>[] = [
  { key: 'product_code', labelKey: 'product.productCode', sortable: true },
  { key: 'full_name', labelKey: 'product.fullName', sortable: true },
  { key: 'sku', labelKey: 'product.sku', sortable: true },
  { key: 'product_type', labelKey: 'product.type', sortable: true },
  { key: 'role', labelKey: 'product.role', sortable: true },
  { key: 'product_group_id', labelKey: 'product.productGroup' },
  { key: 'base_uom_id', labelKey: 'product.baseUom' },
  { key: 'price', labelKey: 'product.defaultPrice' },
  { key: 'serial_management_enabled', labelKey: 'product.serial' },
  { key: 'status', labelKey: 'product.status', sortable: true },
  { key: 'actions', labelKey: 'product.actions' },
]
const filters = () => [
  {
    key: 'status',
    labelKey: 'product.status',
    type: 'select' as const,
    options: [
      { value: 'ACTIVE', labelKey: 'status.active' },
      { value: 'INACTIVE', labelKey: 'status.inactive' },
    ],
  },
  {
    key: 'product_type',
    labelKey: 'product.type',
    type: 'select' as const,
    options: [
      { value: 'STANDARD', labelKey: 'product.standard' },
      { value: 'COMBO', labelKey: 'product.combo' },
    ],
  },
  {
    key: 'role',
    labelKey: 'product.role',
    type: 'select' as const,
    options: [
      { value: 'SIMPLE', labelKey: 'product.simple' },
      { value: 'PARENT', labelKey: 'product.parentRole' },
      { value: 'CHILD', labelKey: 'product.child' },
      { value: 'COMBO', labelKey: 'product.combo' },
    ],
  },
  {
    key: 'product_group_id',
    labelKey: 'product.productGroup',
    type: 'select' as const,
    searchable: true,
    options: groups.value.map((row) => ({
      value: String(row.id),
      labelKey: 'product.productGroup',
      label: row.name,
    })),
  },
  {
    key: 'base_uom_id',
    labelKey: 'product.baseUom',
    type: 'select' as const,
    searchable: true,
    options: uoms.value.map((row) => ({
      value: String(row.id),
      labelKey: 'product.baseUom',
      label: row.name,
    })),
  },
]
function groupName(id: string | null | undefined): string {
  return groups.value.find((row) => String(row.id) === String(id))?.name ?? t('list.noValue')
}
function uomName(id: string | null | undefined): string {
  return uoms.value.find((row) => String(row.id) === String(id))?.code ?? t('list.noValue')
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
async function toggle(row: Product): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changeProductState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
onMounted(async () => {
  await Promise.all([
    load(),
    listProductGroupPage({ q: '', page: 1, page_size: 200, sort: 'name', filter: {} })
      .then((result) => {
        groups.value = result.data
      })
      .catch(() => undefined),
    listProductUomPage({ q: '', page: 1, page_size: 200, sort: 'code', filter: {} })
      .then((result) => {
        uoms.value = result.data
      })
      .catch(() => undefined),
  ])
})
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('product.productsTitle') }}</h1>
      <p>{{ t('product.productsDescription') }}</p>
    </div>
    <button
      v-if="hasBasePermission('product', 'create')"
      type="button"
      class="primary-button"
      @click="router.push('/products/new')"
    >
      {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel product-panel">
    <ListQueryControls
      :model-value="state.query"
      :filters="filters()"
      @apply="applyQuery"
      @reset="resetQuery"
    />
    <p v-if="error && !state.queryState.hasData.value" class="inline-error" role="alert">
      {{ t('product.requestError') }}
    </p>
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
        <template #cell-product_group_id="{ row }">{{ groupName(row.product_group_id) }}</template>
        <template #cell-base_uom_id="{ row }">{{ uomName(row.base_uom_id) }}</template>
        <template #cell-price="{ row }">{{ row.price ?? t('list.noValue') }}</template>
        <template #cell-serial_management_enabled="{ row }">{{
          row.serial_management_enabled ? t('common.yes') : t('common.no')
        }}</template>
        <template #cell-role="{ row }"
          ><span class="role-badge" :class="`role-${row.role.toLowerCase()}`">{{
            row.role
          }}</span></template
        >
        <template #cell-actions="{ row }"
          ><TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="hasBasePermission('product', 'view')"
            :show-edit="hasBasePermission('product', 'edit')"
            :show-toggle="hasBasePermission('product', 'setstate')"
            view-permission="product.view"
            edit-permission="product.edit"
            toggle-permission="product.setstate"
            :view-label="t('common.view')"
            :edit-label="t('common.edit')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            @view="router.push(`/products/${row.id}`)"
            @edit="router.push(`/products/${row.id}`)"
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
.role-badge {
  display: inline-block;
  border-radius: 3px;
  padding: 3px 6px;
  background: #e8f1f5;
  color: #294b65;
  font-size: 11px;
  font-weight: 700;
}
.role-parent {
  background: #fff4dc;
  color: #8a5a00;
}
.role-child {
  background: #e8f4e8;
  color: #27653a;
}
.role-combo {
  background: #efe9fa;
  color: #5b3e86;
}
</style>
