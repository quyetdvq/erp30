<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { ListPagination, ListQueryControls, ListShell, ListTable, TableRowActions } from '@/components/lists'
import type { ListColumn, ListFilterDefinition, ListQuery } from '@/components/lists/listTypes'
import { CUSTOMER_PAGE_SIZE_OPTIONS } from '@/components/lists/listPageSizePolicy'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import { reportApiError } from '@/services/errorBoundary'
import {
  CUSTOMER_GROUPS,
  listCustomerPage,
  listCompanies,
  type Customer,
  type Company,
} from '@/services/customer'
import CustomerDetailView from './CustomerDetailView.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const state = useListState<Customer>(listCustomerPage, { sort: 'name' })
const error = ref<unknown | null>(null)
const companies = ref<Company[]>([])
const detailOpen = computed(() => ['customers-new', 'customers-edit', 'customers-detail'].includes(String(route.name)))

const filters = computed<ListFilterDefinition[]>(() => [
  { key: 'status', labelKey: 'customer.status', type: 'select', options: [
    { value: 'ACTIVE', labelKey: 'status.active' },
    { value: 'INACTIVE', labelKey: 'status.inactive' },
  ] },
  { key: 'customer_type', labelKey: 'customer.type', type: 'select', options: [
    { value: 'ORGANIZATION', labelKey: 'customer.organizationType' },
    { value: 'INDIVIDUAL', labelKey: 'customer.individualType' },
  ] },
  { key: 'customer_group_code', labelKey: 'customer.group', type: 'select', options:
    CUSTOMER_GROUPS.map((group) => ({ value: group.value, labelKey: `customer.groupCodes.${group.value}` })),
  },
  ...(hasBasePermission('organization', 'view') ? [{
    key: 'company_id', labelKey: 'customer.company', type: 'select' as const, searchable: true,
    options: companies.value.map((company) => ({ value: String(company.id), labelKey: company.name, label: `${company.code} — ${company.name}` })),
  }] : []),
])

const columns: readonly ListColumn<Customer>[] = [
  { key: 'customer_code', labelKey: 'customer.code', sortable: true },
  { key: 'name', labelKey: 'customer.name', sortable: true },
  { key: 'customer_type', labelKey: 'customer.type' },
  { key: 'customer_group_code', labelKey: 'customer.group' },
  { key: 'phone', labelKey: 'customer.phone' },
  { key: 'status', labelKey: 'customer.status' },
  { key: 'actions', labelKey: 'people.actions' },
]

function typeLabel(type: Customer['customer_type']): string {
  return t(type === 'ORGANIZATION' ? 'customer.organizationType' : 'customer.individualType')
}
function groupLabel(group: Customer['customer_group_code']): string {
  return group ? t(`customer.groupCodes.${group}`) : '—'
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
function openDetail(id: string, edit = false): void {
  void router.push(edit ? `/customers/${id}/edit` : `/customers/${id}`)
}
function closeDetail(): void {
  void router.push('/customers')
}
onMounted(() => {
  void load()
  if (hasBasePermission('organization', 'view')) {
    void listCompanies().then((rows) => { companies.value = rows }).catch(reportApiError)
  }
})
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('customer.title') }}</h1>
      <p>{{ t('customer.description') }}</p>
    </div>
    <button v-if="hasBasePermission('customer', 'create')" type="button" class="primary-button"
      @click="router.push('/customers/new')">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel customer-list-panel">
    <div class="customer-list-toolbar">
      <ListQueryControls :model-value="state.query" :filters="filters" :page-size-options="CUSTOMER_PAGE_SIZE_OPTIONS" @apply="applyQuery" @reset="resetQuery" />
      <button type="button" class="secondary-button" @click="void load()">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <p v-if="error" class="inline-error" role="alert">
      {{ t('customer.loadError') }} <span v-if="error instanceof Error">{{ error.message }}</span>
    </p>
    <ListShell :initial-loading="state.isInitialLoading.value" :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value" :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value" :error="state.queryState.error.value"
      :retryable="true" @retry="void load()">
      <ListTable :busy="state.isBackgroundRefreshing.value" :rows="state.rows.value" :columns="columns"
        :sort="state.query.sort" @sort-change="(sort) => { state.setSort(sort); void load() }">
        <template #cell-customer_code="{ row }"><strong>{{ row.customer_code }}</strong></template>
        <template #cell-customer_type="{ row }">{{ typeLabel(row.customer_type) }}</template>
        <template #cell-customer_group_code="{ row }">{{ groupLabel(row.customer_group_code) }}</template>
        <template #cell-actions="{ row }">
          <TableRowActions authorization-mode="effective" :view-scope-verified="true"
            :allowed-actions="rowAllowedActions(row)" :active="row.status === 'ACTIVE'"
            :state-target-id="String(row.id)" state-resource="customer"
            :show-view="true" :show-edit="true" :show-toggle="true"
            view-permission="customer.view" edit-permission="customer.edit"
            toggle-permission="customer.setstate" :view-label="t('common.view')"
            :edit-label="t('common.edit')" :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            @view="openDetail(String(row.id))" @edit="openDetail(String(row.id), true)"
            @state-changed="void load()" />
        </template>
      </ListTable>
      <ListPagination :page="state.query.page" :page-size="state.query.page_size"
        :total="state.total.value" :page-size-options="CUSTOMER_PAGE_SIZE_OPTIONS" @update:page="(page) => { state.setPage(page); void load() }"
        @update:page-size="(size) => { state.setPageSize(size); void load() }" />
    </ListShell>
  </section>
  <CustomerDetailView v-if="detailOpen" :key="route.fullPath" @close="closeDetail" @saved="void load()" />
</template>

<style scoped>
.customer-list-panel {
  display: grid;
  gap: 14px;
  min-width: 0;
  padding: 18px 20px 20px;
}
.customer-list-toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 10px;
  min-width: 0;
  flex-wrap: wrap;
}
.inline-error { color: #b64444; font-size: 12px; }
</style>
