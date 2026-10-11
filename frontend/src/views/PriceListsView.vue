<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { NIcon } from 'naive-ui'
import { CloseOutline, RefreshOutline, TrashOutline } from '@vicons/ionicons5'
import type {
  Organization,
  PriceList,
  PriceListCreate,
  PriceListItemsRequest,
  PriceListPatch,
} from '@/api/generated/types.gen'
import DecimalInput from '@/components/forms/DecimalInput.vue'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import RemoteSearchableSelect from '@/components/forms/RemoteSearchableSelect.vue'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableStatus,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useBasePermission } from '@/composables/useBasePermission'
import { useFormatters } from '@/composables/useFormatters'
import { useListState } from '@/composables/useListState'
import { toApiDateTime, toDateTimeLocal } from '@/modules/priceListDateTime'
import { listOrganizationPage } from '@/services/foundation'
import { reportApiError } from '@/services/errorBoundary'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { loadLookupPages } from '@/services/listLookups'
import { useAuthStore } from '@/stores/auth'
import {
  chunks,
  resolveOrganizationLabels,
  resolveProductOptions,
  resolveSalesUomOptions,
  resolveProductUomLabels,
  searchProductOptions,
  type OrganizationLabel,
  type ProductOption,
  type SalesUomOption,
  type UomLabel,
} from '@/services/productLookups'
import {
  changePriceListState,
  listPriceListItemRows,
  listPriceListPage,
  replacePriceListItemRows,
  savePriceList,
} from '@/services/pricingCatalog'

type ItemDraft = { product_id: string; uom_id: string; price: string }

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const auth = useAuthStore()
const formatters = useFormatters()
const state = useListState<PriceList>((query) => listPriceListPage(query), { sort: 'priority' })
const organizations = ref<Organization[]>([])
const products = ref<ProductOption[]>([])
const ownerLabels = ref<Record<string, OrganizationLabel>>({})
const salesUoms = ref<Record<string, SalesUomOption[]>>({})
const uomLabels = ref<Record<string, UomLabel>>({})
const modalOpen = ref(false)
const detailOpen = ref(false)
const editingId = ref<string>()
const detailRow = ref<PriceList | null>(null)
const itemRows = ref<ItemDraft[]>([])
const busy = ref(false)
const itemBusy = ref(false)
const organizationLookupReady = ref(false)
const itemLookupsReady = ref(false)
let listEpoch = 0
let detailEpoch = 0
let organizationLookupPending: Promise<void> | null = null
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
const unsaved = useUnsavedChanges(form, modalOpen)
const itemsUnsaved = useUnsavedChanges(itemRows, detailOpen)
function closeDetail(): void {
  if (!itemsUnsaved.confirmDiscard()) return
  detailOpen.value = false
  ++detailEpoch
  itemsUnsaved.markClean()
}
function closeForm(): void {
  if (!unsaved.confirmDiscard()) return
  modalOpen.value = false
  unsaved.markClean()
}

const listBusy = computed(() => state.isInitialLoading.value || state.isBackgroundRefreshing.value)

const columns: readonly ListColumn<PriceList>[] = [
  { key: 'code', labelKey: 'pricing.code', sortable: true },
  { key: 'name', labelKey: 'pricing.name', sortable: true },
  { key: 'organization_id', labelKey: 'pricing.organizationScope' },
  { key: 'effective_from', labelKey: 'pricing.effectiveFrom', sortable: true, format: 'datetime' },
  { key: 'effective_to', labelKey: 'pricing.effectiveTo', format: 'datetime' },
  { key: 'priority', labelKey: 'pricing.priority', sortable: true },
  { key: 'status', labelKey: 'pricing.status', sortable: true, align: 'center' },
  { key: 'actions', labelKey: 'pricing.actions', align: 'end' },
]

const organizationOptions = computed(() =>
  organizations.value
    .filter((row) => (row.type === 'GROUP' || row.type === 'COMPANY') && row.status === 'ACTIVE')
    .map((row) => ({ value: String(row.id), label: `${row.code} - ${row.name} (${row.type})` })),
)
const selectedProductOptions = computed(() =>
  products.value.map((row) => ({
    value: String(row.id),
    label: `${row.full_name} (${row.role})`,
    disabled: row.status !== 'ACTIVE' || !row.selectable,
  })),
)
function organizationName(id: string | number | null | undefined): string {
  const row =
    ownerLabels.value[String(id)] ??
    organizations.value.find((item) => String(item.id) === String(id))
  return row ? `${row.code} - ${row.name}` : t('list.noValue')
}
function optionsForProduct(productId: string) {
  const options = (salesUoms.value[productId] ?? []).map((row) => ({
    value: row.id,
    label: row.code,
    disabled: false,
  }))
  const known = new Set(options.map((row) => row.value))
  // Persisted INACTIVE or no-longer-eligible UOMs remain recognizable, but
  // are never offered as new choices. The backend still validates updates.
  for (const item of itemRows.value) {
    if (item.product_id !== productId || !item.uom_id || known.has(item.uom_id)) continue
    const label = uomLabels.value[item.uom_id]
    options.push({ value: item.uom_id, label: label?.code ?? item.uom_id, disabled: true })
    known.add(item.uom_id)
  }
  return options
}
async function hydrateExistingUomLabels(epoch: number): Promise<void> {
  const generation = auth.getSessionGeneration()
  const missing = [
    ...new Set(
      itemRows.value
        .filter(
          (item) =>
            item.uom_id &&
            !(salesUoms.value[item.product_id] ?? []).some((option) => option.id === item.uom_id),
        )
        .map((item) => item.uom_id),
    ),
  ]
  if (!missing.length) return
  const resolved = (await Promise.all(chunks(missing, 100).map(resolveProductUomLabels))).flat()
  if (epoch !== detailEpoch || generation !== auth.getSessionGeneration()) return
  uomLabels.value = Object.fromEntries(resolved.map((row) => [row.id, row]))
}
async function searchPriceListProducts(q: string, page: number) {
  const response = await searchProductOptions('price_list_item', q, page)
  return {
    options: response.data.map((row) => ({
      value: String(row.id),
      label: `${row.full_name} (${row.role})`,
    })),
    total: response.meta.total,
  }
}
async function hydrateProducts(ids: readonly string[]): Promise<void> {
  const distinct = [...new Set(ids.filter(Boolean))]
  if (!distinct.length) return
  const epoch = detailEpoch
  const generation = auth.getSessionGeneration()
  const [resolved, uomResolved] = await Promise.all([
    Promise.all(chunks(distinct, 100).map(resolveProductOptions)),
    Promise.all(chunks(distinct, 50).map(resolveSalesUomOptions)),
  ])
  if (epoch !== detailEpoch || generation !== auth.getSessionGeneration()) return
  const known = new Map(products.value.map((row) => [String(row.id), row]))
  for (const row of resolved.flat()) known.set(String(row.id), row)
  products.value = [...known.values()]
  for (const row of uomResolved.flat()) salesUoms.value[row.product_id] = row.options
}
async function ensureOrganizationLookup(): Promise<void> {
  if (organizationLookupReady.value) return
  if (organizationLookupPending) return organizationLookupPending
  const generation = auth.getSessionGeneration()
  const pending = loadLookupPages(listOrganizationPage, { sort: 'code' })
    .then((result) => {
      if (generation !== auth.getSessionGeneration()) return
      organizations.value = result
      organizationLookupReady.value = true
    })
    .catch((caught: unknown) => {
      if (generation !== auth.getSessionGeneration()) return
      error.value = caught
      reportApiError(caught)
    })
    .finally(() => {
      if (organizationLookupPending === pending) organizationLookupPending = null
    })
  organizationLookupPending = pending
  return pending
}
async function hydrateOwnerLabels(rows: readonly PriceList[], epoch: number): Promise<void> {
  const generation = auth.getSessionGeneration()
  const ids = [...new Set(rows.map((row) => String(row.organization_id)).filter(Boolean))]
  const batches = await Promise.all(chunks(ids, 50).map(resolveOrganizationLabels))
  if (epoch !== listEpoch || generation !== auth.getSessionGeneration()) return
  ownerLabels.value = Object.fromEntries(batches.flat().map((row) => [row.id, row]))
}
function decimalNonNegative(value: string): boolean {
  return /^(?:0|[1-9]\d*)(?:\.\d+)?$/.test(value)
}
async function load(): Promise<void> {
  const epoch = ++listEpoch
  error.value = null
  try {
    await state.apply()
    if (epoch === listEpoch) await hydrateOwnerLabels(state.rows.value, epoch)
  } catch (caught: unknown) {
    if (epoch !== listEpoch) return
    error.value = caught
    reportApiError(caught)
  }
}
async function refresh(): Promise<void> {
  const epoch = ++listEpoch
  error.value = null
  try {
    await state.refresh()
    if (epoch === listEpoch) await hydrateOwnerLabels(state.rows.value, epoch)
  } catch (caught: unknown) {
    if (epoch !== listEpoch) return
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
  unsaved.markClean()
  modalOpen.value = true
  void ensureOrganizationLookup()
}
function openEdit(row: PriceList): void {
  editingId.value = String(row.id)
  form.value = {
    code: row.code,
    name: row.name,
    organization_id: String(row.organization_id),
    effective_from: toDateTimeLocal(row.effective_from),
    effective_to: toDateTimeLocal(row.effective_to),
    priority: String(row.priority),
    status: row.status,
    description: row.description ?? '',
  }
  error.value = null
  unsaved.markClean()
  modalOpen.value = true
  void ensureOrganizationLookup()
}
async function save(): Promise<void> {
  if (!organizationLookupReady.value) return
  error.value = null
  const effectiveFrom = toApiDateTime(form.value.effective_from)
  const effectiveTo = form.value.effective_to ? toApiDateTime(form.value.effective_to) : ''
  if (
    !form.value.organization_id ||
    !effectiveFrom ||
    (form.value.effective_to && !effectiveTo) ||
    !form.value.priority
  ) {
    error.value = new Error(t('pricing.requiredFields'))
    return
  }
  busy.value = true
  try {
    const base = {
      code: form.value.code,
      name: form.value.name,
      organization_id: form.value.organization_id,
      effective_from: effectiveFrom,
      ...(effectiveTo ? { effective_to: effectiveTo } : { effective_to: null }),
      priority: Number(form.value.priority),
      ...(form.value.description ? { description: form.value.description } : { description: null }),
    }
    const payload: PriceListCreate | PriceListPatch = editingId.value
      ? base
      : { ...base, status: form.value.status }
    await savePriceList(payload, editingId.value)
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
  const epoch = ++detailEpoch
  const generation = auth.getSessionGeneration()
  itemRows.value = []
  itemsUnsaved.markClean()
  detailRow.value = row
  detailOpen.value = true
  itemBusy.value = true
  itemLookupsReady.value = false
  itemError.value = null
  products.value = []
  salesUoms.value = {}
  uomLabels.value = {}
  try {
    const items = await listPriceListItemRows(String(row.id))
    if (epoch !== detailEpoch || generation !== auth.getSessionGeneration()) return
    itemRows.value = items.map((item) => ({
      product_id: String(item.product_id),
      uom_id: String(item.uom_id),
      price: String(item.price),
    }))
    itemsUnsaved.markClean()
    await hydrateProducts(itemRows.value.map((item) => item.product_id))
    if (epoch !== detailEpoch || generation !== auth.getSessionGeneration()) return
    await hydrateExistingUomLabels(epoch)
    if (epoch !== detailEpoch || generation !== auth.getSessionGeneration()) return
    itemLookupsReady.value = true
  } catch (caught: unknown) {
    if (epoch !== detailEpoch) return
    itemError.value = caught
    reportApiError(caught)
  } finally {
    if (epoch === detailEpoch) itemBusy.value = false
  }
}
function addItem(): void {
  itemRows.value.push({ product_id: '', uom_id: '', price: '' })
}
function removeItem(index: number): void {
  itemRows.value = itemRows.value.filter((_, current) => current !== index)
}
async function productChanged(index: number, value: string): Promise<void> {
  const row = itemRows.value[index]
  if (!row) return
  row.product_id = value
  row.uom_id = ''
  if (!value) return
  const epoch = detailEpoch
  try {
    await hydrateProducts([value])
  } catch (caught: unknown) {
    if (epoch !== detailEpoch) return
    itemError.value = caught
    reportApiError(caught)
  }
}
async function saveItems(): Promise<void> {
  if (!detailRow.value || !itemLookupsReady.value) return
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
    itemsUnsaved.markClean()
  } catch (caught: unknown) {
    itemError.value = caught
    reportApiError(caught)
  } finally {
    itemBusy.value = false
  }
}
onMounted(() => void load())
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
    <div class="pricing-query-toolbar">
      <ListQueryControls
        :model-value="state.query"
        :show-page-size="false"
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
      <button
        type="button"
        class="secondary-button pricing-refresh"
        :disabled="listBusy"
        :aria-label="t('common.refresh')"
        @click="void refresh()"
      >
        <NIcon :component="RefreshOutline" aria-hidden="true" />
        {{ t('common.refresh') }}
      </button>
    </div>
    <ListPagination
      :page="state.query.page"
      :page-size="state.query.page_size"
      :total="state.total.value"
      :disabled="listBusy"
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
      <ListTable
        :rows="state.rows.value"
        :columns="columns"
        :sort="state.query.sort"
        :busy="state.isBackgroundRefreshing.value"
      >
        <template #cell-organization_id="{ row }">{{
          organizationName(row.organization_id)
        }}</template>
        <template #cell-status="{ row }">
          <TableStatus :status="row.status" />
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
        :disabled="listBusy"
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

  <div v-if="modalOpen" class="modal-backdrop">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('pricing.form')">
      <header class="modal-card__header">
        <h2>
          {{ editingId ? t('common.edit') : t('common.create') }} {{ t('pricing.priceList') }}
        </h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="closeForm"
        >
          <NIcon :component="CloseOutline" aria-hidden="true" />
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
          <button type="button" class="secondary-button" @click="closeForm">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="primary-button" :disabled="busy || !organizationLookupReady">
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>

  <div v-if="detailOpen && detailRow" class="modal-backdrop">
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
          @click="closeDetail"
        >
          <NIcon :component="CloseOutline" aria-hidden="true" />
        </button>
      </header>
      <div class="modal-card__body">
        <dl class="detail-grid">
          <dt>{{ t('pricing.organizationScope') }}</dt>
          <dd>{{ organizationName(detailRow.organization_id) }}</dd>
          <dt>{{ t('pricing.effectiveFrom') }}</dt>
          <dd>{{ formatters.formatDateTime(detailRow.effective_from) }}</dd>
          <dt>{{ t('pricing.effectiveTo') }}</dt>
          <dd>
            {{
              detailRow.effective_to
                ? formatters.formatDateTime(detailRow.effective_to)
                : t('list.noValue')
            }}
          </dd>
          <dt>{{ t('pricing.priority') }}</dt>
          <dd>{{ detailRow.priority }}</dd>
          <dt>{{ t('pricing.status') }}</dt>
          <dd><TableStatus :status="detailRow.status" /></dd>
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
                    <RemoteSearchableSelect
                      :model-value="item.product_id"
                      :selected-options="selectedProductOptions"
                      :search="searchPriceListProducts"
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
                    <DecimalInput
                      v-model="item.price"
                      kind="money"
                      :max-decimals="2"
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
                      <NIcon :component="TrashOutline" aria-hidden="true" />
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
            :disabled="itemBusy || !itemLookupsReady"
            @click="saveItems"
          >
            {{ itemBusy ? t('common.saving') : t('common.save') }}
          </button>
        </section>
      </div>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="closeDetail">
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
.pricing-query-toolbar {
  display: flex;
  align-items: end;
  gap: 10px;
  flex-wrap: wrap;
}
.pricing-query-toolbar :deep(.list-query-controls) {
  flex: 1 1 auto;
}
.pricing-refresh {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}
.inline-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(18 38 54 / 45%);
}
.modal-card {
  width: min(100%, 680px);
  max-height: calc(100vh - 40px);
  overflow: hidden;
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
  padding: 16px 22px;
  border-bottom: 1px solid #e4ebef;
}
.modal-card__header h2 {
  margin: 0;
}
.modal-card__body {
  display: grid;
  gap: 12px;
  max-height: calc(100vh - 150px);
  overflow: auto;
  padding: 22px;
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
  margin: 0;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.icon-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #536b7b;
  cursor: pointer;
}
.icon-button:hover,
.icon-button:focus-visible {
  color: #1876a8;
}
.price-items-modal {
  width: min(1060px, 100%);
  max-height: calc(100vh - 36px);
}
.price-items-modal .modal-card__body {
  max-height: calc(100vh - 160px);
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
