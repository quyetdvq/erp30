<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { ComboItem, Product, ProductGroup, ProductUom } from '@/api/generated/types.gen'
import ProductDetailModal from '@/components/products/ProductDetailModal.vue'
import ProductRelationSummary from '@/components/products/ProductRelationSummary.vue'
import ProductRelationsDialog from '@/components/products/ProductRelationsDialog.vue'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  TableRowActions,
  TableStatus,
} from '@/components/lists'
import type { ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import { useAuthStore } from '@/stores/auth'
import { useFormatters } from '@/composables/useFormatters'
import {
  listProductComboItems,
  listProductGroupPage,
  listProductPage,
  listProductUomPage,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'
import { useBusinessWorkflowStates } from '@/composables/useBusinessWorkflowStates'
import { loadLookupPages } from '@/services/listLookups'
import { databaseFlagEnabled, productRoleLabel } from '@/services/productPresentation'
import { chunks, previewProductRelations, resolveProductDetails } from '@/services/productLookups'

type RelationState = {
  children: Product[]
  components: Product[]
  componentItems: ComboItem[]
  total: number
  full: boolean
  loading: boolean
}
const { t } = useI18n()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const workflowStates = useBusinessWorkflowStates('product')
const auth = useAuthStore()
const formatters = useFormatters()
const state = useListState<Product>((query) => listProductPage(query), { sort: 'product_code' })
const groups = ref<ProductGroup[]>([])
const uoms = ref<ProductUom[]>([])
// Resolve table labels in O(1) per visible row even with thousands of groups/UOMs.
const groupNameById = computed(() => new Map(groups.value.map((row) => [String(row.id), row.name])))
const uomNameById = computed(() => new Map(uoms.value.map((row) => [String(row.id), row.name])))
const relations = ref<Record<string, RelationState>>({})
const relatedOwner = ref<Product | null>(null)
const relationsOpen = ref(false)
const relationLoadError = ref(false)
let relationReturnFocus: HTMLElement | null = null
const detailProduct = ref<Product | null>(null)
const detailOpen = ref(false)
const busy = ref(false)
const error = ref<unknown | null>(null)
const productsForDetail = computed(() => {
  const byId = new Map<string, Product>()
  for (const row of [
    ...state.rows.value,
    ...Object.values(relations.value).flatMap((item) => [...item.children, ...item.components]),
  ])
    byId.set(String(row.id), row)
  return [...byId.values()]
})

const filters = () => [
  {
    key: 'status',
    labelKey: 'product.status',
    type: 'select' as const,
    options: workflowStates.options.value,
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
  return groupNameById.value.get(String(id)) ?? t('list.noValue')
}
function uomName(id: string | null | undefined): string {
  return uomNameById.value.get(String(id)) ?? t('list.noValue')
}
function relation(row: Product): RelationState {
  return (
    relations.value[String(row.id)] ?? {
      children: [],
      components: [],
      componentItems: [],
      total: 0,
      full: false,
      loading: false,
    }
  )
}
function relatedProducts(row: Product): Product[] {
  return row.role === 'COMBO' ? relation(row).components : relation(row).children
}
const dialogRelation = computed(() => (relatedOwner.value ? relation(relatedOwner.value) : null))
const dialogProducts = computed(() =>
  relatedOwner.value ? relatedProducts(relatedOwner.value) : [],
)
function closeRelations(restoreFocus = true): void {
  relationsOpen.value = false
  relatedOwner.value = null
  relationLoadError.value = false
  const previousFocus = relationReturnFocus
  relationReturnFocus = null
  if (restoreFocus && previousFocus?.isConnected) {
    void nextTick(() => previousFocus.focus())
  }
}
function retryRelations(): void {
  if (relatedOwner.value) void loadFullRelations(relatedOwner.value)
}
function viewRelatedProduct(item: Product): void {
  closeRelations(false)
  openDetail(item)
}
function openRelations(row: Product): void {
  relationReturnFocus =
    document.activeElement instanceof HTMLElement ? document.activeElement : null
  relatedOwner.value = row
  relationsOpen.value = true
  relationLoadError.value = false
  void loadFullRelations(row)
}
async function loadFullRelations(row: Product): Promise<void> {
  const key = String(row.id)
  const epoch = listEpoch
  const generation = auth.getSessionGeneration()
  const previous = relation(row)
  if (previous.full) return
  relations.value[key] = { ...previous, loading: true }
  relationLoadError.value = false
  try {
    if (row.role === 'COMBO') {
      const componentItems = await listProductComboItems(key)
      const components = (
        await Promise.all(
          chunks(
            componentItems.map((item) => String(item.component_product_id)),
            100,
          ).map(resolveProductDetails),
        )
      ).flat()
      if (epoch !== listEpoch || generation !== auth.getSessionGeneration()) return
      relations.value[key] = {
        children: [],
        components,
        componentItems,
        total: components.length,
        full: true,
        loading: false,
      }
    } else {
      const children = await loadLookupPages(listProductPage, {
        sort: 'product_name',
        filter: { parent_product_id: key },
      })
      if (epoch !== listEpoch || generation !== auth.getSessionGeneration()) return
      relations.value[key] = {
        children,
        components: [],
        componentItems: [],
        total: children.length,
        full: true,
        loading: false,
      }
    }
  } catch (caught: unknown) {
    if (epoch !== listEpoch || generation !== auth.getSessionGeneration()) return
    relations.value[key] = { ...previous, loading: false }
    if (relatedOwner.value && String(relatedOwner.value.id) === key) relationLoadError.value = true
    error.value = caught
    reportApiError(caught)
  }
}
function productName(row: Product): string {
  return row.full_name || row.product_name || row.product_child_name || t('list.noValue')
}
function displayMoney(value: string | number | null | undefined): string {
  return value === null || value === undefined || value === ''
    ? t('list.noValue')
    : formatters.formatMoney(String(value))
}
function openDetail(row: Product): void {
  detailProduct.value = row
  detailOpen.value = true
}
let listEpoch = 0
async function loadRelations(rows: readonly Product[], epoch: number): Promise<void> {
  const generation = auth.getSessionGeneration()
  const targets = rows.filter((row) => row.role === 'PARENT' || row.role === 'COMBO')
  if (targets.length === 0) {
    relations.value = {}
    return
  }
  relations.value = Object.fromEntries(
    targets.map((row) => [
      String(row.id),
      {
        children: [],
        components: [],
        componentItems: [],
        total: 0,
        full: false,
        loading: true,
      },
    ]),
  )
  const previews = (
    await Promise.all(
      chunks(
        targets.map((row) => String(row.id)),
        50,
      ).map(previewProductRelations),
    )
  ).flat()
  if (epoch !== listEpoch || generation !== auth.getSessionGeneration()) return
  for (const preview of previews) {
    const owner = targets.find((row) => String(row.id) === preview.product_id)
    if (!owner) continue
    relations.value[preview.product_id] = {
      children: owner.role === 'PARENT' ? preview.products : [],
      components: owner.role === 'COMBO' ? preview.products : [],
      componentItems: [],
      total: preview.total,
      full: preview.total <= 2,
      loading: false,
    }
  }
  for (const row of targets) {
    const key = String(row.id)
    if (relations.value[key]?.loading) {
      relations.value[key] = {
        children: [],
        components: [],
        componentItems: [],
        total: 0,
        full: true,
        loading: false,
      }
    }
  }
}
async function load(): Promise<void> {
  const epoch = ++listEpoch
  error.value = null
  try {
    await state.apply()
    if (epoch !== listEpoch) return
    closeRelations()
    await loadRelations(state.rows.value, epoch)
  } catch (caught: unknown) {
    if (epoch !== listEpoch) return
    error.value = caught
    reportApiError(caught)
    relations.value = {}
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
async function afterWorkflowChange(): Promise<void> {
  await load()
}
onMounted(async () => {
  await Promise.all([
    load(),
    loadLookupPages(listProductGroupPage, { sort: 'name' })
      .then((result) => {
        groups.value = result
      })
      .catch((caught) => {
        error.value = caught
        reportApiError(caught)
      }),
    loadLookupPages(listProductUomPage, { sort: 'code' })
      .then((result) => {
        uoms.value = result
      })
      .catch((caught) => {
        error.value = caught
        reportApiError(caught)
      }),
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
            <tr v-for="row in state.rows.value" :key="String(row.id)">
              <td>{{ row.product_code ?? t('list.noValue') }}</td>
              <td>
                <button type="button" class="product-link" @click="openDetail(row)">
                  {{ productName(row) }}
                </button>
                <ProductRelationSummary
                  v-if="row.role === 'COMBO' || row.role === 'PARENT'"
                  :kind="row.role === 'COMBO' ? 'COMBO' : 'PARENT'"
                  :total="relation(row).total"
                  :preview="relatedProducts(row)"
                  :loading="relation(row).loading"
                  @view="openDetail"
                  @show-all="openRelations(row)"
                />
              </td>
              <td>{{ row.sku ?? t('list.noValue') }}</td>
              <td>
                {{ row.product_type === 'COMBO' ? t('product.combo') : t('product.standard') }}
              </td>
              <td>
                <span class="role-badge" :class="`role-${row.role.toLowerCase()}`">{{
                  productRoleLabel(row.role, t)
                }}</span>
              </td>
              <td>{{ groupName(row.product_group_id) }}</td>
              <td>{{ uomName(row.base_uom_id) }}</td>
              <td>{{ displayMoney(row.price) }}</td>
              <td>
                {{
                  databaseFlagEnabled(row.serial_management_enabled)
                    ? t('common.yes')
                    : t('common.no')
                }}
              </td>
              <td><TableStatus :status="row.status" /></td>
              <td>
                <TableRowActions
                  :active="row.status === 'ACTIVE'"
                  :state-target-id="String(row.id)"
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
                  @state-changed="afterWorkflowChange"
                />
              </td>
            </tr>
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
  <ProductRelationsDialog
    :open="relationsOpen"
    :owner="relatedOwner"
    :products="dialogProducts"
    :total="dialogRelation?.total ?? 0"
    :combo-items="dialogRelation?.componentItems ?? []"
    :loading="dialogRelation?.loading ?? false"
    :error="relationLoadError"
    :uoms="uoms"
    @close="closeRelations"
    @retry="retryRelations"
    @view="viewRelatedProduct"
  />
  <ProductDetailModal
    :open="detailOpen"
    :product="detailProduct"
    :groups="groups"
    :uoms="uoms"
    :products="productsForDetail"
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
.product-link {
  border: 0;
  background: transparent;
  color: #1876a8;
  cursor: pointer;
  text-align: start;
}
.product-link {
  font-weight: 700;
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
