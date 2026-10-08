<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import type { ImportJob } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
  TableStatus,
} from '@/components/lists'
import type { ListColumn, ListFilterDefinition, ListSortOption } from '@/components/lists/listTypes'
import { useBasePermission } from '@/composables/useBasePermission'
import { useListState } from '@/composables/useListState'
import { reportApiError } from '@/services/errorBoundary'
import { listProductImportJobs } from '@/services/productImport'

const { t } = useI18n()
const router = useRouter()
const { hasBasePermission } = useBasePermission()

const state = useListState<ImportJob>((query) => listProductImportJobs(query), {
  sort: 'created_at',
})
const columns: readonly ListColumn<ImportJob>[] = [
  {
    key: 'created_at',
    labelKey: 'productImportJobs.createdAt',
    format: 'datetime',
    sortable: true,
  },
  { key: 'source_file_name', labelKey: 'productImportJobs.file' },
  { key: 'mode', labelKey: 'productImportJobs.mode', sortable: true },
  { key: 'status', labelKey: 'productImportJobs.status', sortable: true },
  { key: 'total_rows', labelKey: 'productImportJobs.total', format: 'quantity', align: 'end' },
  { key: 'success_rows', labelKey: 'productImportJobs.success', format: 'quantity', align: 'end' },
  { key: 'error_rows', labelKey: 'productImportJobs.error', format: 'quantity', align: 'end' },
  { key: 'created_by', labelKey: 'productImportJobs.actor' },
  { key: 'actions', labelKey: 'productImportJobs.actions', align: 'end' },
]
const filters: readonly ListFilterDefinition[] = [
  {
    key: 'mode',
    labelKey: 'productImportJobs.mode',
    type: 'select',
    options: [
      { value: 'CREATE_ONLY', labelKey: 'productImport.modeCreateOnly' },
      { value: 'UPDATE_ONLY', labelKey: 'productImport.modeUpdateOnly' },
      { value: 'UPSERT', labelKey: 'productImport.modeUpsert' },
    ],
  },
  {
    key: 'status',
    labelKey: 'productImportJobs.status',
    type: 'select',
    options: [
      { value: 'PENDING', labelKey: 'status.pending' },
      { value: 'PROCESSING', labelKey: 'status.processing' },
      { value: 'COMPLETED', labelKey: 'status.completed' },
      { value: 'COMPLETED_WITH_ERRORS', labelKey: 'status.completedWithErrors' },
      { value: 'FAILED', labelKey: 'status.failed' },
    ],
  },
]
const sortOptions: readonly ListSortOption[] = [
  { value: 'created_at', labelKey: 'productImportJobs.sortCreatedAt' },
  { value: '-created_at', labelKey: 'productImportJobs.sortCreatedAtDesc' },
]

async function load(): Promise<void> {
  try {
    await state.apply()
  } catch (caught: unknown) {
    reportApiError(caught)
  }
}

function applyQuery(query: typeof state.query): void {
  state.setSearch(query.q)
  state.setFilters(query.filter)
  state.setSort(query.sort)
  state.setPageSize(query.page_size)
  void load()
}

function openJob(row: ImportJob): void {
  void router.push(`/products/import-jobs/${String(row.id)}`)
}

function resetQuery(): void {
  state.reset()
  void load()
}

function changePage(value: number): void {
  state.setPage(value)
  void load()
}

function changePageSize(value: number): void {
  state.setPageSize(value)
  void load()
}

onMounted(() => void load())
</script>

<template>
  <section class="jobs-panel">
    <header class="page-header">
      <div>
        <p class="eyebrow">{{ t('productImportJobs.eyebrow') }}</p>
        <h1>{{ t('productImportJobs.title') }}</h1>
        <p class="page-description">{{ t('productImportJobs.description') }}</p>
      </div>
      <button
        v-if="hasBasePermission('product', 'import')"
        type="button"
        class="primary-button"
        @click="router.push('/products/import')"
      >
        {{ t('productImportJobs.newImport') }}
      </button>
    </header>
    <ListQueryControls
      :model-value="state.query"
      :filters="filters"
      :sort-options="sortOptions"
      :disabled="state.isBackgroundRefreshing.value"
      @apply="applyQuery"
      @reset="resetQuery"
    />
    <ListPagination
      :page="state.query.page"
      :page-size="state.query.page_size"
      :total="state.total.value"
      :disabled="state.isBackgroundRefreshing.value"
      :aria-label="t('productImportJobs.topPagination')"
      @update:page="changePage"
      @update:page-size="changePageSize"
    />
    <ListShell
      :initial-loading="state.isInitialLoading.value"
      :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value"
      :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value"
      :error="state.queryState.error.value"
      :title="t('productImportJobs.tableTitle')"
    >
      <ListTable
        :rows="state.rows.value"
        :columns="columns"
        :sort="state.query.sort"
        :busy="state.isBackgroundRefreshing.value"
        row-key="id"
      >
        <template #cell-source_file_name="{ row }">{{
          row.source_file_name || t('list.noValue')
        }}</template>
        <template #cell-mode="{ row }">{{ row.mode }}</template>
        <template #cell-status="{ row }"><TableStatus :status="row.status" /></template>
        <template #cell-created_by="{ row }">{{ row.created_by ?? t('list.noValue') }}</template>
        <template #cell-actions="{ row }">
          <TableRowActions
            :show-view="hasBasePermission('import_job', 'view')"
            view-permission="import_job.view"
            :view-label="t('common.view')"
            @view="openJob(row)"
          />
        </template>
      </ListTable>
      <ListPagination
        :page="state.query.page"
        :page-size="state.query.page_size"
        :total="state.total.value"
        :disabled="state.isBackgroundRefreshing.value"
        :aria-label="t('productImportJobs.bottomPagination')"
        @update:page="changePage"
        @update:page-size="changePageSize"
      />
    </ListShell>
  </section>
</template>

<style scoped>
.jobs-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 24px;
  min-width: 0;
}
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.page-header h1 {
  margin: 0;
}
.page-description {
  margin: 6px 0 0;
  color: #536b7b;
}
.eyebrow {
  margin: 0 0 4px;
  color: #1876a8;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.primary-button {
  min-height: 36px;
  border: 1px solid #1876a8;
  border-radius: 4px;
  padding: 7px 14px;
  background: #1876a8;
  color: #fff;
  cursor: pointer;
}
@media (max-width: 640px) {
  .page-header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
