<script setup lang="ts">
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { ComboItem, Product, ProductGroup, ProductUom } from '@/api/generated/types.gen'
import ProductDetailModal from '@/components/products/ProductDetailModal.vue'
import ProductListThumbnail from '@/components/products/ProductListThumbnail.vue'
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
  getProductById,
  listProductComboItems,
  listProductGroupPage,
  listProductPage,
  listProductUomPage,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'
import { useBusinessWorkflowStates } from '@/composables/useBusinessWorkflowStates'
import { loadLookupPages } from '@/services/listLookups'
import { loadProductListCatalog, productListCatalogScope } from '@/services/productListCatalogCache'
import { i18n } from '@/i18n'
import { databaseFlagEnabled, productRoleLabel } from '@/services/productPresentation'
import { chunks, previewProductRelations, resolveProductDetails } from '@/services/productLookups'
import { DEFAULT_PRODUCT_SORT, productSortOptions } from '@/features/catalog/productSortOptions'
import { loadProductRelationBatches } from '@/features/catalog/productRelationTraffic'
import {
  buildProductInlineRows,
  type ProductInlineRelation,
} from '@/features/catalog/productInlineRows'

type RelationState = ProductInlineRelation & {
  components: Product[]
  componentItems: ComboItem[]
}
const { t } = useI18n()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const workflowStates = useBusinessWorkflowStates('product')
const auth = useAuthStore()
const formatters = useFormatters()
const state = useListState<Product>((query) => listProductPage(query), { sort: DEFAULT_PRODUCT_SORT })
const groups = ref<ProductGroup[]>([])
const uoms = ref<ProductUom[]>([])
// Resolve table labels in O(1) per visible row even with thousands of groups/UOMs.
const groupNameById = computed(() => new Map(groups.value.map((row) => [String(row.id), row.name])))
const uomNameById = computed(() => new Map(uoms.value.map((row) => [String(row.id), row.name])))
const relations = ref<Record<string, RelationState>>({})
const collapsedChildren = ref(new Set<string>())
const showAllChildren = ref(new Set<string>())
const inlineRows = computed(() =>
  buildProductInlineRows(
    state.rows.value,
    relations.value,
    collapsedChildren.value,
    showAllChildren.value,
  ),
)
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
  return relationById(String(row.id))
}
function toggleChildren(parentId: string): void {
  const next = new Set(collapsedChildren.value)
  if (next.has(parentId)) next.delete(parentId)
  else next.add(parentId)
  collapsedChildren.value = next
}
function toggleAllChildren(parentId: string): void {
  const next = new Set(showAllChildren.value)
  if (next.has(parentId) && relationById(parentId).full) {
    next.delete(parentId)
  } else {
    next.add(parentId)
    const parent = state.rows.value.find((row) => String(row.id) === parentId)
    if (parent && !relationById(parentId).full) void loadFullRelations(parent)
  }
  showAllChildren.value = next
}
function relationById(id: string): RelationState {
  return (
    relations.value[id] ?? {
      children: [],
      components: [],
      componentItems: [],
      total: 0,
      full: false,
      loading: false,
    }
  )
}
function relatedProducts(row: Product): readonly Product[] {
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
  if (previous.full || previous.loading) return
  relations.value[key] = { ...previous, loading: true, error: false }
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
        // Match the preview endpoint's product_id ASC order so the first
        // two child rows do not move when the owner is expanded.
        sort: 'product_id',
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
    relations.value[key] = { ...previous, loading: false, error: true }
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
let detailRequest = 0
function closeDetail(): void {
  detailOpen.value = false
  ++detailRequest
}
function openDetail(row: Product): void {
  const request = ++detailRequest
  if (row.allowed_actions !== undefined) {
    detailProduct.value = row
    detailOpen.value = true
    return
  }
  // The bounded relation preview is authorized for VIEW but deliberately has
  // no edit/setstate hints. Read a single product on user click instead of
  // granting inherited parent actions or issuing N per-row requests.
  const epoch = listEpoch
  const generation = auth.getSessionGeneration()
  void getProductById(String(row.id))
    .then((product) => {
      if (request !== detailRequest || epoch !== listEpoch || generation !== auth.getSessionGeneration()) return
      detailProduct.value = product
      detailOpen.value = true
    })
    .catch((caught: unknown) => {
      if (request !== detailRequest || epoch !== listEpoch || generation !== auth.getSessionGeneration()) return
      error.value = caught
      reportApiError(caught)
    })
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
  const owners = new Map(targets.map((row) => [String(row.id), row]))
  // Publish the first 50 owners as soon as their preview arrives, without
  // waiting for later batches when the user selects page_size=100/200.
  await loadProductRelationBatches(
    [...owners.keys()],
    previewProductRelations,
    (ids, previews) => {
      const received = new Set<string>()
      const batchIds = new Set(ids)
      for (const preview of previews) {
        const owner = owners.get(preview.product_id)
        if (!owner || !batchIds.has(preview.product_id)) continue
        received.add(preview.product_id)
        relations.value[preview.product_id] = {
          children: owner.role === 'PARENT' ? preview.products : [],
          components: owner.role === 'COMBO' ? preview.products : [],
          componentItems: [],
          total: preview.total,
          full: preview.total <= 2,
          loading: false,
        }
      }
      for (const id of ids) {
        if (received.has(id)) continue
        relations.value[id] = {
          children: [],
          components: [],
          componentItems: [],
          total: 0,
          full: true,
          loading: false,
        }
      }
    },
    () => epoch === listEpoch && generation === auth.getSessionGeneration(),
  )
}
async function load(): Promise<void> {
  const epoch = ++listEpoch
  ++detailRequest
  detailOpen.value = false
  detailProduct.value = null
  collapsedChildren.value = new Set()
  showAllChildren.value = new Set()
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
  state.setSort(DEFAULT_PRODUCT_SORT)
  void load()
}
async function afterWorkflowChange(): Promise<void> {
  await load()
}
let mounted = false
function catalogScope(): string | null {
  return productListCatalogScope({
    userId: auth.user?.id,
    sessionGeneration: auth.getSessionGeneration(),
    permissionState: auth.permissionSnapshotState,
    permissions: auth.user?.base_permissions ?? [],
    locale: i18n.global.locale.value,
  })
}
onMounted(() => {
  mounted = true
  const generation = auth.getSessionGeneration()
  const userId = auth.user?.id
  const scope = catalogScope()
  const current = () =>
    mounted && generation === auth.getSessionGeneration() && userId === auth.user?.id && scope === catalogScope()
  void load()
  void loadProductListCatalog('groups', scope, () =>
    loadLookupPages(listProductGroupPage, { sort: 'name' }),
  )
    .then((result) => { if (current()) groups.value = result })
    .catch((caught: unknown) => {
      if (!current()) return
      error.value = caught
      reportApiError(caught)
    })
  void loadProductListCatalog('uoms', scope, () =>
    loadLookupPages(listProductUomPage, { sort: 'code' }),
  )
    .then((result) => { if (current()) uoms.value = result })
    .catch((caught: unknown) => {
      if (!current()) return
      error.value = caught
      reportApiError(caught)
    })
})
onUnmounted(() => {
  mounted = false
  ++listEpoch
  ++detailRequest
})
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('product.productsTitle') }}</h1>
      <p>{{ t('product.productsDescription') }}</p>
    </div>
    <button data-ds-icon="add"
      v-if="hasBasePermission('product', 'create')"
      type="button"
      class="primary-button"
      @click="router.push('/products/new')"
    >
      {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel product-panel ds-page-panel">
    <ListQueryControls :show-page-size="false"
      :model-value="state.query"
      :filters="filters()"
      :sort-options="productSortOptions"
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
      <div class="master-table-wrap ds-table-scroll">
        <table class="master-table ds-data-grid">
          <caption class="sr-only">
            {{
              t('product.productsTitle')
            }}
          </caption>
          <thead>
            <tr>
              <th class="product-image-column">{{ t('product.imageColumn') }}</th>
              <th class="product-name-column">{{ t('product.fullName') }}</th>
              <th class="product-information-column">{{ t('product.information') }}</th>
              <th class="product-group-column">{{ t('product.groupColumn') }}</th>
              <th>{{ t('product.baseUom') }}</th>
              <th>{{ t('product.defaultPrice') }}</th>
              <th>{{ t('product.serial') }}</th>
              <th>{{ t('product.status') }}</th>
              <th class="table-actions">{{ t('product.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="entry in inlineRows" :key="entry.key">
              <tr v-if="entry.kind === 'product'" class="product-data-row" :class="{ 'product-child-row': !!entry.childOf }">
                <td class="product-image-column">
                  <ProductListThumbnail
                    :product-name="productName(entry.product)"
                    :image-available="entry.product.image_available === true"
                    :image-path="entry.product.image_url"
                  />
                </td>
                <td class="product-name-column">
                  <div class="product-name-cell" :class="{ 'product-name-cell--child': !!entry.childOf }">
                    <button
                      v-if="entry.product.role === 'PARENT' && relation(entry.product).total > 0"
                      type="button"
                      class="product-tree-toggle"
                      :aria-label="`${collapsedChildren.has(String(entry.product.id)) ? t('product.expand') : t('product.collapse')}: ${productName(entry.product)}`"
                      :aria-expanded="!collapsedChildren.has(String(entry.product.id))"
                      @click="toggleChildren(String(entry.product.id))"
                    >
                      {{ collapsedChildren.has(String(entry.product.id)) ? '+' : '−' }}
                    </button>
                    <span v-else-if="entry.childOf" class="product-tree-branch" aria-hidden="true" />
                    <div class="product-name-content">
                      <div class="product-name-heading">
                        <button type="button" class="product-link" @click="openDetail(entry.product)">
                          {{ productName(entry.product) }}
                        </button>
                        <span
                          v-if="entry.product.role === 'PARENT' && relation(entry.product).total > 0"
                          class="product-child-total"
                        >
                          {{ t('product.children') }}: {{ relation(entry.product).total }}
                        </span>
                      </div>
                      <ProductRelationSummary
                        v-if="entry.product.role === 'COMBO'"
                        kind="COMBO"
                        :total="relation(entry.product).total"
                        :preview="relatedProducts(entry.product)"
                        :loading="relation(entry.product).loading"
                        @view="openDetail"
                        @show-all="openRelations(entry.product)"
                      />
                    </div>
                  </div>
                </td>
                <td class="product-information-column">
                  <div class="product-metadata">
                    <div class="product-meta-line">
                      <span class="product-meta-label">{{ t('product.code') }}:</span>
                      <span class="product-meta-value product-code-value">{{ entry.product.product_code ?? t('list.noValue') }}</span>
                    </div>
                    <div class="product-meta-line">
                      <span class="product-meta-label">{{ t('product.sku') }}:</span>
                      <span class="product-meta-value">{{ entry.product.sku || t('list.noValue') }}</span>
                    </div>
                    <div class="product-meta-line">
                      <span class="product-meta-label">{{ t('product.type') }}:</span>
                      <span class="product-meta-value">{{ entry.product.product_type === 'COMBO' ? t('product.combo') : t('product.standard') }}</span>
                    </div>
                  </div>
                </td>
                <td class="product-group-column">
                  <div class="product-metadata">
                    <div class="product-meta-line">
                      <span class="product-meta-label">{{ t('product.groupColumn') }}:</span>
                      <span class="product-meta-value">{{ groupName(entry.product.product_group_id) }}</span>
                    </div>
                    <div class="product-meta-line">
                      <span class="product-meta-label">{{ t('product.role') }}:</span>
                      <span class="role-badge" :class="`role-${entry.product.role.toLowerCase()}`">{{
                        productRoleLabel(entry.product.role, t)
                      }}</span>
                    </div>
                  </div>
                </td>
                <td>{{ uomName(entry.product.base_uom_id) }}</td>
                <td>{{ displayMoney(entry.product.price) }}</td>
                <td>
                  {{
                    databaseFlagEnabled(entry.product.serial_management_enabled)
                      ? t('common.yes')
                      : t('common.no')
                  }}
                </td>
                <td><TableStatus :status="entry.product.status" /></td>
                <td class="table-actions">
                  <TableRowActions authorization-mode="effective" :view-scope-verified="true" :allowed-actions="rowAllowedActions(entry.product)"
                    :active="entry.product.status === 'ACTIVE'"
                    :state-target-id="String(entry.product.id)"
                    :busy="busy"
                    :show-view="true"
                    :show-edit="true"
                    :show-toggle="true"
                    state-resource="product" view-permission="product.view" edit-permission="product.edit" toggle-permission="product.setstate"
                    :view-label="t('common.view')"
                    :edit-label="t('common.edit')"
                    :activate-label="t('common.activate')"
                    :deactivate-label="t('common.deactivate')"
                    @view="openDetail(entry.product)"
                    @edit="router.push(`/products/${entry.product.id}`)"
                    @state-changed="afterWorkflowChange"
                  />
                </td>
              </tr>
              <tr v-else class="product-child-control-row">
                <td class="product-image-column"></td>
                <td colspan="8" class="product-child-control-cell">
                  <button
                    type="button"
                    class="product-children-control"
                    :disabled="entry.loading"
                    :aria-expanded="entry.expanded"
                    @click="toggleAllChildren(entry.parentId)"
                  >
                    <span aria-hidden="true">{{ entry.expanded ? '−' : '+' }}</span>
                    {{
                      entry.loading
                        ? t('list.loading')
                        : entry.error
                          ? t('list.retry')
                          : entry.expanded
                            ? t('product.showFirstTwoChildren')
                            : `${t('product.viewMore')} (${t('product.relationOthers', { count: entry.total - 2 })})`
                    }}
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <ListPagination :show-page-size="false"
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
    @close="closeDetail"
    @edit="(row) => row.allowed_actions?.edit === true && router.push(`/products/${row.id}`)"
  />
</template>

<style scoped>


.master-table { min-width: 1100px; }
.master-table th.table-actions,
.master-table td.table-actions {
  display: table-cell;
  text-align: right;
  vertical-align: middle;
  white-space: nowrap;
  width: 1%;
}
.product-image-column {
  width: 76px;
  min-width: 76px;
}
.product-name-column {
  min-width: 320px;
  width: 30%;
}
.product-information-column {
  min-width: 190px;
}
.product-group-column {
  min-width: 170px;
}
.product-metadata {
  display: grid;
  gap: 5px;
  font-size: 12px;
  line-height: 1.45;
}
.product-meta-line {
  display: flex;
  align-items: center;
  min-width: 0;
  gap: 6px;
}
.product-meta-label {
  flex: 0 0 42px;
  color: var(--ds-text-secondary);
  font-size: 11px;
}
.product-meta-value {
  min-width: 0;
  overflow-wrap: anywhere;
}
.product-code-value {
  overflow-wrap: anywhere;
  font-weight: 550;
}
.product-name-cell {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  min-width: 0;
}
.product-name-cell--child {
  padding-left: 16px;
}
.product-name-content {
  flex: 1 1 auto;
  min-width: 0;
  display: grid;
  gap: 7px;
}
.product-name-heading {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 9px;
  min-width: 0;
}
.product-tree-toggle {
  display: inline-grid;
  place-items: center;
  flex: 0 0 24px;
  width: 24px;
  height: 24px;
  border: 1px solid var(--ds-border);
  border-radius: 4px;
  background: var(--ds-bg-surface);
  color: var(--ds-text-primary);
  font-weight: 700;
  cursor: pointer;
}
.product-tree-toggle:hover {
  background: var(--ds-bg-hover);
  border-color: var(--ds-border-strong);
}
.product-tree-branch {
  display: block;
  flex: 0 0 20px;
  width: 20px;
  height: 20px;
  border-left: 1px solid var(--ds-border-subtle);
  border-bottom: 1px solid var(--ds-border-subtle);
  border-bottom-left-radius: 4px;
}
.master-table tbody tr.product-data-row:hover,
.master-table tbody tr.product-data-row:focus-within {
  background: var(--ds-bg-hover);
}
.product-child-row {
  background: var(--ds-bg-subtle);
}
.product-child-row td.product-name-column {
  box-shadow: inset 3px 0 0 var(--ds-border-strong);
}
.product-child-total {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 999px;
  background: var(--ds-bg-subtle);
  color: var(--ds-text-secondary);
  font-size: 11px;
  white-space: nowrap;
}
.product-child-control-row td {
  background: var(--ds-bg-subtle);
}
.master-table td.product-child-control-cell {
  padding-left: 48px;
}
.product-children-control {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  border: 0;
  padding: 4px 0;
  color: var(--ds-primary);
  background: transparent;
  cursor: pointer;
  font-weight: 650;
}
.product-children-control:hover:not(:disabled),
.product-children-control:focus-visible {
  text-decoration: underline;
}
.product-children-control:disabled {
  cursor: wait;
  opacity: 0.7;
}
.product-link {
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--ds-primary);
  cursor: pointer;
  text-align: start;
  font: inherit;
  font-weight: 700;
  line-height: 1.4;
  overflow-wrap: anywhere;
}
.product-link:hover {
  text-decoration: underline;
}
.product-tree-toggle:focus-visible,
.product-link:focus-visible,
.product-children-control:focus-visible {
  outline: 2px solid var(--ds-focus-ring);
  outline-offset: 2px;
}
.role-badge {
  display: inline-block;
  border-radius: 3px;
  padding: 3px 6px;
  background: var(--ds-info-soft);
  color: var(--ds-text-primary);
  font-size: 11px;
  font-weight: 700;
}
.role-parent {
  background: var(--ds-warning-soft);
  color: var(--ds-warning);
}
.role-child {
  background: var(--ds-success-soft);
  color: var(--ds-success);
}
.role-combo {
  background: var(--ds-role-combo-soft);
  color: var(--ds-role-combo);
}
</style>
