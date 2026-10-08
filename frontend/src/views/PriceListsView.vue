<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  Organization,
  PriceList,
  PriceListCreate,
  PriceListItemsRequest,
  PriceListPatch,
  Product,
  ProductUom,
  ProductUomConversion,
} from '@/api/generated/types.gen'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useBasePermission } from '@/composables/useBasePermission'
import { useListState } from '@/composables/useListState'
import { listOrganizationPage } from '@/services/foundation'
import { reportApiError } from '@/services/errorBoundary'
import {
  changePriceListState,
  listPriceListItemRows,
  listPriceListPage,
  replacePriceListItemRows,
  savePriceList,
} from '@/services/pricingCatalog'
import {
  listProductConversions,
  listProductPage,
  listProductUomPage,
} from '@/services/productCatalog'

type ItemDraft = { product_id: string; uom_id: string; price: string }

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<PriceList>((query) => listPriceListPage(query), { sort: 'priority' })
const organizations = ref<Organization[]>([])
const products = ref<Product[]>([])
const uoms = ref<ProductUom[]>([])
const conversions = ref<Record<string, ProductUomConversion[]>>({})
const modalOpen = ref(false)
const detailOpen = ref(false)
const editingId = ref<string>()
const detailRow = ref<PriceList | null>(null)
const itemRows = ref<ItemDraft[]>([])
const busy = ref(false)
const itemBusy = ref(false)
const error = ref<unknown | null>(null)
const itemError = ref<unknown | null>(null)
const form = ref({
  code: '',
  name: '',
  organization_id: '',
  effective_from: '',
  effective_to: '',
  priority: '0',
  status: 'ACTIVE' as PriceList['status'],
  description: '',
})

const columns: readonly ListColumn<PriceList>[] = [
  { key: 'code', labelKey: 'pricing.code', sortable: true },
  { key: 'name', labelKey: 'pricing.name', sortable: true },
  { key: 'organization_id', labelKey: 'pricing.organizationScope' },
  { key: 'effective_from', labelKey: 'pricing.effectiveFrom', sortable: true },
  { key: 'effective_to', labelKey: 'pricing.effectiveTo' },
  { key: 'priority', labelKey: 'pricing.priority', sortable: true },
  { key: 'status', labelKey: 'pricing.status', sortable: true },
  { key: 'actions', labelKey: 'pricing.actions' },
]

const organizationOptions = computed(() =>
  organizations.value
    .filter((row) => (row.type === 'GROUP' || row.type === 'COMPANY') && row.status === 'ACTIVE')
    .map((row) => ({ value: String(row.id), label: `${row.code} - ${row.name} (${row.type})` })),
)
const productOptions = computed(() =>
  products.value
    .filter((row) => row.status === 'ACTIVE' && row.selectable && row.role !== 'PARENT')
    .map((row) => ({ value: String(row.id), label: `${row.full_name} (${row.role})` })),
)

function organizationName(id: string | number | null | undefined): string {
  const row = organizations.value.find((candidate) => String(candidate.id) === String(id))
  return row ? `${row.code} - ${row.name}` : t('list.noValue')
}
function productById(id: string): Product | undefined {
  return products.value.find((row) => String(row.id) === id)
}
function uomName(id: string | number | null | undefined): string {
  return uoms.value.find((row) => String(row.id) === String(id))?.code ?? t('list.noValue')
}
function inputDateTime(value: string | null | undefined): string {
  return value ? value.slice(0, 16) : ''
}
function apiDateTime(value: string): string {
  return value ? `${value}:00Z` : ''
}
function decimalNonNegative(value: string): boolean {
  return /^(?:0|[1-9]\d*)(?:\.\d+)?$/.test(value)
}
function optionsForProduct(productId: string) {
  const product = productById(productId)
  if (!product || !product.base_uom_id) return []
  const options = [{ value: String(product.base_uom_id), label: uomName(product.base_uom_id) }]
  if (product.role !== 'COMBO') {
    for (const row of conversions.value[productId] ?? []) {
      if (row.status === 'ACTIVE' && (row.usage_type === 'SALES' || row.usage_type === 'BOTH'))
        options.push({ value: String(row.uom_id), label: uomName(row.uom_id) })
    }
  }
  return options.filter(
    (option, index, all) => all.findIndex((item) => item.value === option.value) === index,
  )
}
async function ensureConversions(productId: string): Promise<void> {
  if (conversions.value[productId] || productById(productId)?.role === 'COMBO') return
  try {
    conversions.value[productId] = await listProductConversions(productId)
  } catch (caught: unknown) {
    conversions.value[productId] = []
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
  form.value = {
    code: '',
    name: '',
    organization_id: '',
    effective_from: '',
    effective_to: '',
    priority: '0',
    status: 'ACTIVE',
    description: '',
  }
  error.value = null
  modalOpen.value = true
}
function openEdit(row: PriceList): void {
  editingId.value = String(row.id)
  form.value = {
    code: row.code,
    name: row.name,
    organization_id: String(row.organization_id),
    effective_from: inputDateTime(row.effective_from),
    effective_to: inputDateTime(row.effective_to),
    priority: String(row.priority),
    status: row.status,
    description: row.description ?? '',
  }
  error.value = null
  modalOpen.value = true
}
async function save(): Promise<void> {
  error.value = null
  if (!form.value.organization_id || !form.value.effective_from || !form.value.priority) {
    error.value = new Error(t('pricing.requiredFields'))
    return
  }
  busy.value = true
  try {
    const base = {
      code: form.value.code,
      name: form.value.name,
      organization_id: form.value.organization_id,
      effective_from: apiDateTime(form.value.effective_from),
      ...(form.value.effective_to
        ? { effective_to: apiDateTime(form.value.effective_to) }
        : { effective_to: null }),
      priority: Number(form.value.priority),
      ...(form.value.description ? { description: form.value.description } : { description: null }),
    }
    const payload: PriceListCreate | PriceListPatch = editingId.value
      ? base
      : { ...base, status: form.value.status }
    await savePriceList(payload, editingId.value)
    modalOpen.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function toggle(row: PriceList): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changePriceListState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function openDetail(row: PriceList): Promise<void> {
  detailRow.value = row
  detailOpen.value = true
  itemBusy.value = true
  itemError.value = null
  try {
    const items = await listPriceListItemRows(String(row.id))
    itemRows.value = items.map((item) => ({
      product_id: String(item.product_id),
      uom_id: String(item.uom_id),
      price: String(item.price),
    }))
    await Promise.all(itemRows.value.map((item) => ensureConversions(item.product_id)))
  } catch (caught: unknown) {
    itemError.value = caught
    reportApiError(caught)
  } finally {
    itemBusy.value = false
  }
}
function addItem(): void {
  itemRows.value.push({ product_id: '', uom_id: '', price: '' })
}
function removeItem(index: number): void {
  itemRows.value = itemRows.value.filter((_, current) => current !== index)
}
async function productChanged(index: number, value: string | string[]): Promise<void> {
  const row = itemRows.value[index]
  if (!row) return
  row.product_id = typeof value === 'string' ? value : ''
  row.uom_id = ''
  await ensureConversions(row.product_id)
}
async function saveItems(): Promise<void> {
  if (!detailRow.value) return
  itemError.value = null
  if (
    itemRows.value.some((row) => !row.product_id || !row.uom_id || !decimalNonNegative(row.price))
  ) {
    itemError.value = new Error(t('pricing.itemFieldsRequired'))
    return
  }
  itemBusy.value = true
  try {
    const payload: PriceListItemsRequest = itemRows.value.map((row) => ({
      product_id: row.product_id,
      uom_id: row.uom_id,
      price: row.price,
    }))
    const saved = await replacePriceListItemRows(String(detailRow.value.id), payload)
    itemRows.value = saved.map((item) => ({
      product_id: String(item.product_id),
      uom_id: String(item.uom_id),
      price: String(item.price),
    }))
  } catch (caught: unknown) {
    itemError.value = caught
    reportApiError(caught)
  } finally {
    itemBusy.value = false
  }
}
onMounted(async () => {
  await Promise.all([
    load(),
    listOrganizationPage({ q: '', page: 1, page_size: 200, sort: 'code', filter: {} })
      .then((result) => {
        organizations.value = result.data
      })
      .catch(() => undefined),
    listProductPage({ q: '', page: 1, page_size: 200, sort: 'product_name', filter: {} })
      .then((result) => {
        products.value = result.data
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
      <h1>{{ t('pricing.title') }}</h1>
      <p>{{ t('pricing.description') }}</p>
    </div>
    <button
      v-if="hasBasePermission('price_list', 'create')"
      type="button"
      class="primary-button"
      @click="openCreate"
    >
      {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel pricing-panel">
    <ListQueryControls
      :model-value="state.query"
      :filters="[
        {
          key: 'status',
          labelKey: 'pricing.status',
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
    <p v-if="error && !state.queryState.hasData.value" class="inline-error" role="alert">
      {{ t('pricing.requestError') }}
    </p>
    <ListPagination
      :page="state.query.page"
      :page-size="state.query.page_size"
      :total="state.total.value"
      :disabled="state.isInitialLoading.value"
      :aria-label="t('pricing.topPagination')"
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
        <template #cell-organization_id="{ row }">{{
          organizationName(row.organization_id)
        }}</template>
        <template #cell-effective_to="{ row }">{{
          row.effective_to ?? t('list.noValue')
        }}</template>
        <template #cell-status="{ row }">
          {{ row.status === 'ACTIVE' ? t('status.active') : t('status.inactive') }}
        </template>
        <template #cell-actions="{ row }">
          <TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="hasBasePermission('price_list', 'view')"
            :show-edit="hasBasePermission('price_list', 'edit')"
            :show-toggle="hasBasePermission('price_list', 'setstate')"
            view-permission="price_list.view"
            edit-permission="price_list.edit"
            toggle-permission="price_list.setstate"
            :view-label="t('common.view')"
            :edit-label="t('common.edit')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            @view="openDetail(row)"
            @edit="openEdit(row)"
            @toggle="toggle(row)"
          />
        </template>
      </ListTable>
      <ListPagination
        :page="state.query.page"
        :page-size="state.query.page_size"
        :total="state.total.value"
        :aria-label="t('pricing.bottomPagination')"
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

  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('pricing.form')">
      <header class="modal-card__header">
        <h2>
          {{ editingId ? t('common.edit') : t('common.create') }} {{ t('pricing.priceList') }}
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
          ><span>{{ t('pricing.code') }}</span
          ><input v-model="form.code" required
        /></label>
        <label
          ><span>{{ t('pricing.name') }}</span
          ><input v-model="form.name" required
        /></label>
        <label>
          <span>{{ t('pricing.organizationScope') }}</span>
          <SearchableSelect
            :model-value="form.organization_id"
            :options="organizationOptions"
            :aria-label="t('pricing.organizationScope')"
            @update:model-value="
              (value) => (form.organization_id = typeof value === 'string' ? value : '')
            "
          />
        </label>
        <label
          ><span>{{ t('pricing.effectiveFrom') }}</span
          ><input v-model="form.effective_from" type="datetime-local" required
        /></label>
        <label
          ><span>{{ t('pricing.effectiveTo') }}</span
          ><input v-model="form.effective_to" type="datetime-local"
        /></label>
        <label
          ><span>{{ t('pricing.priority') }}</span
          ><input v-model="form.priority" type="number" min="0" step="1" required
        /></label>
        <label v-if="!editingId">
          <span>{{ t('pricing.status') }}</span>
          <select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select>
        </label>
        <label
          ><span>{{ t('pricing.descriptionField') }}</span
          ><textarea v-model="form.description" rows="3" />
        </label>
        <p v-if="error" class="inline-error" role="alert">{{ t('pricing.requestError') }}</p>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="modalOpen = false">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="primary-button" :disabled="busy">
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>

  <div v-if="detailOpen && detailRow" class="modal-backdrop" @click.self="detailOpen = false">
    <section
      class="modal-card price-items-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('pricing.items')"
    >
      <header class="modal-card__header">
        <div>
          <p class="eyebrow">{{ detailRow.code }}</p>
          <h2>{{ detailRow.name }}</h2>
        </div>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="detailOpen = false"
        >
          ×
        </button>
      </header>
      <div class="modal-card__body">
        <dl class="detail-grid">
          <dt>{{ t('pricing.organizationScope') }}</dt>
          <dd>{{ organizationName(detailRow.organization_id) }}</dd>
          <dt>{{ t('pricing.effectiveFrom') }}</dt>
          <dd>{{ detailRow.effective_from }}</dd>
          <dt>{{ t('pricing.effectiveTo') }}</dt>
          <dd>{{ detailRow.effective_to ?? t('list.noValue') }}</dd>
          <dt>{{ t('pricing.priority') }}</dt>
          <dd>{{ detailRow.priority }}</dd>
          <dt>{{ t('pricing.status') }}</dt>
          <dd>{{ detailRow.status === 'ACTIVE' ? t('status.active') : t('status.inactive') }}</dd>
        </dl>
        <section class="items-section">
          <header class="section-header">
            <h3>{{ t('pricing.items') }}</h3>
            <button
              v-if="hasBasePermission('price_list', 'edit')"
              type="button"
              class="secondary-button"
              @click="addItem"
            >
              {{ t('pricing.addItem') }}
            </button>
          </header>
          <p class="field-note">{{ t('pricing.itemRules') }}</p>
          <p v-if="itemError" class="inline-error" role="alert">{{ t('pricing.requestError') }}</p>
          <p v-if="itemBusy" class="field-note">{{ t('common.loading') }}</p>
          <div class="table-wrap">
            <table class="detail-table">
              <thead>
                <tr>
                  <th>{{ t('pricing.product') }}</th>
                  <th>{{ t('pricing.uom') }}</th>
                  <th>{{ t('pricing.price') }}</th>
                  <th>{{ t('pricing.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in itemRows" :key="`${item.product_id}-${index}`">
                  <td>
                    <SearchableSelect
                      :model-value="item.product_id"
                      :options="productOptions"
                      :aria-label="t('pricing.product')"
                      @update:model-value="(value) => void productChanged(index, value)"
                    />
                  </td>
                  <td>
                    <SearchableSelect
                      :model-value="item.uom_id"
                      :options="optionsForProduct(item.product_id)"
                      :aria-label="t('pricing.uom')"
                      @update:model-value="
                        (value) => (item.uom_id = typeof value === 'string' ? value : '')
                      "
                    />
                  </td>
                  <td>
                    <input
                      v-model="item.price"
                      inputmode="decimal"
                      :aria-label="t('pricing.price')"
                    />
                  </td>
                  <td>
                    <button
                      v-if="hasBasePermission('price_list', 'edit')"
                      type="button"
                      class="icon-button danger"
                      :aria-label="t('common.delete')"
                      @click="removeItem(index)"
                    >
                      {{ t('common.delete') }}
                    </button>
                  </td>
                </tr>
                <tr v-if="!itemBusy && itemRows.length === 0">
                  <td colspan="4">{{ t('list.noResults') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <button
            v-if="hasBasePermission('price_list', 'edit')"
            type="button"
            class="primary-button"
            :disabled="itemBusy"
            @click="saveItems"
          >
            {{ itemBusy ? t('common.saving') : t('common.save') }}
          </button>
        </section>
      </div>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="detailOpen = false">
          {{ t('common.close') }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.pricing-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
}
.inline-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}
.modal-card__body {
  display: grid;
  gap: 12px;
}
.price-items-modal {
  width: min(1060px, 100%);
  max-height: calc(100vh - 36px);
  overflow: auto;
}
.eyebrow {
  margin: 0 0 3px;
  color: #1876a8;
  font-size: 11px;
  font-weight: 700;
}
.eyebrow + h2 {
  margin: 0;
}
.detail-grid {
  display: grid;
  grid-template-columns: minmax(150px, 0.35fr) minmax(0, 1fr);
  margin: 0;
}
.detail-grid dt,
.detail-grid dd {
  margin: 0;
  padding: 8px 0;
  border-bottom: 1px solid #edf2f4;
}
.detail-grid dt {
  color: #536b7b;
  font-size: 12px;
  font-weight: 700;
}
.detail-grid dd {
  overflow-wrap: anywhere;
}
.items-section {
  display: grid;
  gap: 12px;
}
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.section-header h3 {
  margin: 0;
}
.field-note {
  margin: 0;
  color: #536b7b;
  font-size: 12px;
}
.table-wrap {
  overflow-x: auto;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
}
.detail-table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  font-size: 13px;
}
.detail-table th,
.detail-table td {
  padding: 9px 10px;
  border-bottom: 1px solid #e8eef1;
  text-align: start;
  vertical-align: middle;
}
.detail-table th {
  background: #f7fafb;
  color: #536b7b;
  font-size: 12px;
}
.detail-table input {
  min-height: 34px;
  width: 100%;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
}
.danger {
  color: #b64444;
}
</style>
