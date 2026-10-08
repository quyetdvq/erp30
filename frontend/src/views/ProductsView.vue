<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { ComboItem, Product, ProductGroup, ProductUom } from '@/api/generated/types.gen'
import ProductDetailModal from '@/components/products/ProductDetailModal.vue'
import { ListPagination, ListQueryControls, ListShell, TableRowActions } from '@/components/lists'
import type { ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import {
  changeProductState,
  getProductById,
  listProductComboItems,
  listProductGroupPage,
  listProductPage,
  listProductUomPage,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'

type RelationState = {
  children: Product[]
  components: Product[]
  componentItems: ComboItem[]
  loading: boolean
}
const { t } = useI18n()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const state = useListState<Product>((query) => listProductPage(query), { sort: 'product_code' })
const groups = ref<ProductGroup[]>([])
const uoms = ref<ProductUom[]>([])
const relations = ref<Record<string, RelationState>>({})
const expandedRows = ref<Record<string, boolean>>({})
const detailProduct = ref<Product | null>(null)
const detailOpen = ref(false)
const busy = ref(false)
const error = ref<unknown | null>(null)

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
function relation(row: Product): RelationState {
  return (
    relations.value[String(row.id)] ?? {
      children: [],
      components: [],
      componentItems: [],
      loading: false,
    }
  )
}
function relatedProducts(row: Product): Product[] {
  return row.role === 'COMBO' ? relation(row).components : relation(row).children
}
function relatedLabel(row: Product): string {
  return row.role === 'COMBO' ? t('product.components') : t('product.children')
}
function canExpand(row: Product): boolean {
  return relatedProducts(row).length > 2
}
function toggleExpanded(row: Product): void {
  expandedRows.value[String(row.id)] = !expandedRows.value[String(row.id)]
}
function productName(row: Product): string {
  return row.full_name || row.product_name || row.product_child_name || t('list.noValue')
}
function openDetail(row: Product): void {
  detailProduct.value = row
  detailOpen.value = true
}
async function loadRelations(rows: readonly Product[]): Promise<void> {
  await Promise.all(
    rows
      .filter((row) => row.role === 'PARENT' || row.role === 'COMBO')
      .map(async (row) => {
        const key = String(row.id)
        relations.value[key] = { children: [], components: [], componentItems: [], loading: true }
        try {
          if (row.role === 'COMBO') {
            const componentItems = await listProductComboItems(key)
            const components = await Promise.all(
              componentItems.map((item) => getProductById(String(item.component_product_id))),
            )
            relations.value[key] = { children: [], components, componentItems, loading: false }
          } else {
            const children = (
              await listProductPage({
                q: '',
                page: 1,
                page_size: 200,
                sort: 'product_name',
                filter: { parent_product_id: key },
              })
            ).data
            relations.value[key] = { children, components: [], componentItems: [], loading: false }
          }
        } catch (caught: unknown) {
          relations.value[key] = {
            children: [],
            components: [],
            componentItems: [],
            loading: false,
          }
          error.value = caught
          reportApiError(caught)
        }
      }),
  )
}
async function load(): Promise<void> {
  error.value = null
  try {
    await state.apply()
    await loadRelations(state.rows.value)
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
      <div class="master-table-wrap">
        <table class="master-table">
          <caption class="sr-only">
            {{
              t('product.productsTitle')
            }}
          </caption>
          <thead>
            <tr>
              <th>{{ t('product.productCode') }}</th>
              <th>{{ t('product.fullName') }}</th>
              <th>{{ t('product.sku') }}</th>
              <th>{{ t('product.type') }}</th>
              <th>{{ t('product.role') }}</th>
              <th>{{ t('product.productGroup') }}</th>
              <th>{{ t('product.baseUom') }}</th>
              <th>{{ t('product.defaultPrice') }}</th>
              <th>{{ t('product.serial') }}</th>
              <th>{{ t('product.status') }}</th>
              <th>{{ t('product.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in state.rows.value" :key="String(row.id)"
              ><tr>
                <td>{{ row.product_code ?? t('list.noValue') }}</td>
                <td>
                  <button type="button" class="product-link" @click="openDetail(row)">
                    {{ productName(row) }}
                  </button>
                  <div v-if="row.role === 'COMBO' || row.role === 'PARENT'" class="related-preview">
                    <span class="related-label">{{ relatedLabel(row) }}:</span
                    ><span v-if="relation(row).loading">{{ t('common.loading') }}</span
                    ><template v-else
                      ><button
                        v-for="item in relatedProducts(row).slice(0, 2)"
                        :key="String(item.id)"
                        type="button"
                        class="related-link"
                        @click.stop="openDetail(item)"
                      >
                        {{ productName(item) }}</button
                      ><button
                        v-if="canExpand(row)"
                        type="button"
                        class="more-link"
                        @click.stop="toggleExpanded(row)"
                      >
                        {{
                          expandedRows[String(row.id)]
                            ? t('product.collapse')
                            : t('product.viewMore')
                        }}
                      </button></template
                    >
                  </div>
                </td>
                <td>{{ row.sku ?? t('list.noValue') }}</td>
                <td>{{ row.product_type }}</td>
                <td>
                  <span class="role-badge" :class="`role-${row.role.toLowerCase()}`">{{
                    row.role
                  }}</span>
                </td>
                <td>{{ groupName(row.product_group_id) }}</td>
                <td>{{ uomName(row.base_uom_id) }}</td>
                <td>{{ row.price ?? t('list.noValue') }}</td>
                <td>{{ row.serial_management_enabled ? t('common.yes') : t('common.no') }}</td>
                <td>{{ row.status === 'ACTIVE' ? t('status.active') : t('status.inactive') }}</td>
                <td>
                  <TableRowActions
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
                    @view="openDetail(row)"
                    @edit="router.push(`/products/${row.id}`)"
                    @toggle="toggle(row)"
                  />
                </td>
              </tr>
              <tr v-if="expandedRows[String(row.id)]" class="expanded-row">
                <td colspan="11">
                  <div class="expanded-content">
                    <strong>{{ relatedLabel(row) }}</strong
                    ><span v-if="relatedProducts(row).length === 0">{{ t('list.noResults') }}</span
                    ><button
                      v-for="item in relatedProducts(row)"
                      :key="String(item.id)"
                      type="button"
                      class="expanded-item"
                      @click="openDetail(item)"
                    >
                      {{ productName(item) }}<small>{{ item.product_code ?? item.role }}</small>
                    </button>
                  </div>
                </td>
              </tr></template
            >
          </tbody>
        </table>
      </div>
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
  <ProductDetailModal
    :open="detailOpen"
    :product="detailProduct"
    :groups="groups"
    :uoms="uoms"
    :products="state.rows.value"
    @close="detailOpen = false"
    @edit="(row) => router.push(`/products/${row.id}`)"
  />
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
.master-table-wrap {
  overflow-x: auto;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
}
.master-table {
  width: 100%;
  min-width: 1240px;
  border-collapse: collapse;
  color: #183b57;
  font-size: 13px;
}
.master-table th,
.master-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e8eef1;
  text-align: start;
  vertical-align: top;
}
.master-table th {
  background: #f7fafb;
  color: #536b7b;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}
.master-table tbody tr:last-child td {
  border-bottom: 0;
}
.product-link,
.related-link,
.more-link,
.expanded-item {
  border: 0;
  background: transparent;
  color: #1876a8;
  cursor: pointer;
  text-align: start;
}
.product-link {
  font-weight: 700;
}
.related-preview {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 8px;
  margin-top: 6px;
  font-size: 11px;
}
.related-label {
  color: #536b7b;
  font-weight: 700;
}
.related-link,
.more-link {
  padding: 0;
  text-decoration: underline;
}
.expanded-row td {
  background: #f7fafb;
}
.expanded-content {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
}
.expanded-item {
  display: grid;
  gap: 2px;
  min-width: 150px;
  padding: 8px;
  border: 1px solid #dbe5ea;
  border-radius: 4px;
  background: #fff;
}
.expanded-item small {
  color: #536b7b;
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
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
