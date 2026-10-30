<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { ListPagination, ListQueryControls, ListShell, ListTable, TableRowActions, TableStatus } from '@/components/lists'
import type { ListColumn, ListFilterDefinition, ListQuery } from '@/components/lists/listTypes'
import { CUSTOMER_PAGE_SIZE_OPTIONS } from '@/components/lists/listPageSizePolicy'
import RemoteSearchableSelect from '@/components/forms/RemoteSearchableSelect.vue'
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import { useBusinessWorkflowStates } from '@/composables/useBusinessWorkflowStates'
import { reportApiError } from '@/services/errorBoundary'
import { listSupplierPage, searchSupplierCompanies, type Supplier } from '@/services/supplier'
import SupplierDetailView from './SupplierDetailView.vue'
import SupplierStateAction from '../components/SupplierStateAction.vue'

const { t, te } = useI18n()
const route = useRoute()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const state = useListState<Supplier>(listSupplierPage, { sort: 'name', page_size: 25 })
const error = ref<unknown | null>(null)
// Empty fallback: never invent allowed Supplier statuses when metadata fails.
const { options: statusOptions, refresh: refreshStatusOptions } = useBusinessWorkflowStates('supplier', [], false)
let stateMetadataRequested = false
const statusLabel = (code: string): string => {
  const key = `status.${code.toLowerCase()}`
  return te(key) ? t(key) : code
}
const companyFilter = computed(() => {
  const raw = state.query.filter.company_id
  return typeof raw === 'string' ? raw : ''
})
const detailOpen = computed(() => ['suppliers-new', 'suppliers-edit', 'suppliers-detail'].includes(String(route.name)))

const filters = computed<ListFilterDefinition[]>(() => [
  { key: 'status', labelKey: 'supplier.status', type: 'select', options: statusOptions.value },
  { key: 'supplier_type', labelKey: 'supplier.type', type: 'select', options: [
    { value: 'COMPANY', labelKey: 'supplier.companyType' },
    { value: 'INDIVIDUAL', labelKey: 'supplier.individualType' },
  ] },
  { key: 'supplier_group_code', labelKey: 'supplier.group', type: 'select', options: [
    { value: 'STANDARD', labelKey: 'supplier.standard' },
  ] },
])
const columns: readonly ListColumn<Supplier>[] = [
  { key: 'supplier_code', labelKey: 'supplier.code', sortable: true },
  { key: 'name', labelKey: 'supplier.name', sortable: true },
  { key: 'supplier_type', labelKey: 'supplier.type' },
  { key: 'phone', labelKey: 'supplier.phone' },
  { key: 'status', labelKey: 'supplier.status' },
  { key: 'actions', labelKey: 'supplier.actions' },
]
async function load(): Promise<void> {
  error.value = null
  try {
    await state.apply()
    // Do not request Workflow metadata until Supplier LIST is actually authorized.
    // When the Supplier API is off, this prevents a misleading second PBAC 403.
    if (!stateMetadataRequested) {
      stateMetadataRequested = true
      void refreshStatusOptions()
    }
  } catch (caught: unknown) { error.value = caught; reportApiError(caught) }
}
function applyQuery(query: ListQuery): void {
  state.setSearch(query.q)
  state.setFilters(query.filter)
  state.setSort(query.sort)
  state.setPageSize(query.page_size)
  void load()
}
function changeCompanyFilter(value: string): void {
  const next: Record<string, string | readonly string[]> = { ...state.query.filter }
  if (value) next.company_id = value
  else delete next.company_id
  state.setFilters(next)
  void load()
}
async function companySearch(q: string, page: number) {
  const result = await searchSupplierCompanies(q, page)
  return { options: result.data.map((row) => ({ value: String(row.id), label: `${row.code} - ${row.name}` })), total: result.meta.total }
}
function openSupplier(id: string, edit = false): void {
  void router.push(edit ? `/suppliers/${id}/edit` : `/suppliers/${id}`)
}
function closeDetail(): void { void router.push('/suppliers') }
function reset(): void { state.reset(); state.setSort('name'); state.setPageSize(25); void load() }
onMounted(() => { void load() })
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('supplier.title') }}</h1>
      <p>{{ t('supplier.desc') }}</p>
    </div>
    <button v-if="hasBasePermission('supplier', 'create')" type="button" class="primary-button"
      @click="router.push('/suppliers/new')">＋ {{ t('common.create') }}</button>
  </div>
  <section class="content-panel supplier-list-panel">
    <div class="supplier-list-toolbar">
      <ListQueryControls :model-value="state.query" :filters="filters" :page-size-options="CUSTOMER_PAGE_SIZE_OPTIONS"
        @apply="applyQuery" @reset="reset" />
      <label v-if="hasBasePermission('organization', 'view')" class="supplier-company-filter">
        <span>{{ t('supplier.companyFilter') }}</span>
        <RemoteSearchableSelect :model-value="companyFilter" :search="companySearch"
          :aria-label="t('supplier.companyFilter')" :placeholder="t('supplier.allCompaniesFilter')"
          @update:model-value="changeCompanyFilter" />
      </label>
      <button type="button" class="secondary-button" @click="void load()">↻ {{ t('common.refresh') }}</button>
    </div>
    <p v-if="error" role="alert" class="supplier-error">{{ t('supplier.loadError') }}</p>
    <ListShell :initial-loading="state.isInitialLoading.value" :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value" :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value" :error="state.queryState.error.value"
      :retryable="true" @retry="void load()">
      <ListTable :busy="state.isBackgroundRefreshing.value" :rows="state.rows.value" :columns="columns"
        :sort="state.query.sort" @sort-change="(sort) => { state.setSort(sort); void load() }">
        <template #cell-supplier_code="{ row }"><strong>{{ row.supplier_code }}</strong></template>
        <template #cell-supplier_type="{ row }">
          {{ t(row.supplier_type === 'COMPANY' ? 'supplier.companyType' : 'supplier.individualType') }}
        </template>
        <template #cell-status="{ row }">
          <span class="supplier-status-cell"><TableStatus :status="row.status" />{{ statusLabel(row.status) }}</span>
        </template>
        <template #cell-actions="{ row }">
          <span class="supplier-row-actions">
            <TableRowActions authorization-mode="effective" :view-scope-verified="true"
              :allowed-actions="rowAllowedActions(row)" :show-view="true" :show-edit="true" :show-toggle="false"
              view-permission="supplier.view" edit-permission="supplier.edit"
              @view="openSupplier(String(row.id))" @edit="openSupplier(String(row.id), true)" />
            <!-- Same CUS action placement, but Supplier-only CAS execution. -->
            <SupplierStateAction :supplier="row" variant="icon" @changed="void load()" />
          </span>
        </template>
      </ListTable>
      <ListPagination :page="state.query.page" :page-size="state.query.page_size"
        :total="state.total.value" :page-size-options="CUSTOMER_PAGE_SIZE_OPTIONS"
        @update:page="(page) => { state.setPage(page); void load() }"
        @update:page-size="(size) => { state.setPageSize(size); void load() }" />
    </ListShell>
  </section>
  <SupplierDetailView v-if="detailOpen" :key="route.fullPath" @close="closeDetail" @saved="void load()" />
</template>

<style scoped>
/* Match CUS list panel/toolbar rhythm while retaining Supplier SQL-paged Company filter. */
.supplier-list-panel {
  display: grid; gap: 14px; min-width: 0; padding: 18px 20px 20px;
}
.supplier-list-toolbar {
  display: flex; gap: 10px; align-items: end;
  justify-content: space-between; min-width: 0; flex-wrap: wrap;
}
.supplier-company-filter {
  min-width: 210px; max-width: 290px; display: grid;
  gap: 6px; color: #536b7b; font-size: 12px; font-weight: 600; flex: 1;
}
.supplier-status-cell { display: inline-flex; align-items: center; gap: 6px; }
.supplier-row-actions { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.supplier-error { color: #b64444; font-size: 12px; }
</style>
