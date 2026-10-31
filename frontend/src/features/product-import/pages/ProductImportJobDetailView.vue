<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import type { ImportJob, ImportJobRow } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableStatus,
} from '@/components/lists'
import type { ListColumn, ListFilterDefinition, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { getProductImportJob, listProductImportJobRows } from '@/services/productImport'
import { reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const job = ref<ImportJob | null>(null)
const summaryError = ref<unknown | null>(null)
const jobId = computed(() => String(route.params.id))

const state = useListState<ImportJobRow>((query) => listProductImportJobRows(jobId.value, query), {
  sort: 'row_number',
})
const columns: readonly ListColumn<ImportJobRow>[] = [
  { key: 'row_number', labelKey: 'productImportJobDetail.rowNumber', sortable: true, align: 'end' },
  { key: 'entity_key', labelKey: 'productImportJobDetail.entityKey' },
  { key: 'row_status', labelKey: 'productImportJobDetail.result', sortable: true },
  { key: 'error_code', labelKey: 'productImportJobDetail.errorCode' },
  { key: 'error_message', labelKey: 'productImportJobDetail.errorMessage' },
  {
    key: 'created_at',
    labelKey: 'productImportJobDetail.createdAt',
    format: 'datetime',
    sortable: true,
  },
]
const filters: readonly ListFilterDefinition[] = [
  {
    key: 'row_status',
    labelKey: 'productImportJobDetail.result',
    type: 'select',
    options: [
      { value: 'SUCCESS', labelKey: 'productImportJobDetail.success' },
      { value: 'ERROR', labelKey: 'productImportJobDetail.error' },
      { value: 'SKIPPED', labelKey: 'productImportJobDetail.skipped' },
    ],
  },
]

async function loadSummary(): Promise<void> {
  summaryError.value = null
  try {
    job.value = await getProductImportJob(jobId.value)
  } catch (caught: unknown) {
    summaryError.value = caught
    reportApiError(caught)
  }
}

async function loadRows(): Promise<void> {
  try {
    await state.apply()
  } catch (caught: unknown) {
    reportApiError(caught)
  }
}

function applyQuery(query: ListQuery): void {
  state.setSearch(query.q)
  state.setFilters(query.filter)
  state.setSort(query.sort)
  state.setPageSize(query.page_size)
  void loadRows()
}

function statusLabel(status: ImportJobRow['row_status']): string {
  const key =
    status === 'SUCCESS' ? 'successResult' : status === 'ERROR' ? 'errorResult' : 'skipped'
  return t(`productImportJobDetail.${key}`)
}

function resetQuery(): void {
  state.reset()
  void loadRows()
}

function changePage(value: number): void {
  state.setPage(value)
  void loadRows()
}

function changePageSize(value: number): void {
  state.setPageSize(value)
  void loadRows()
}

onMounted(() => {
  void loadSummary()
  void loadRows()
})
</script>

<template>
  <section class="detail-panel">
    <header class="page-header">
      <div>
        <p class="eyebrow">{{ t('productImportJobDetail.eyebrow') }}</p>
        <h1>{{ t('productImportJobDetail.title') }}</h1>
        <p class="page-description">{{ t('productImportJobDetail.description', { id: jobId }) }}</p>
      </div>
      <button data-ds-icon="back" type="button" class="secondary-button" @click="router.push('/products/import-jobs')">
        {{ t('productImportJobDetail.back') }}
      </button>
    </header>

    <div v-if="summaryError" class="inline-error" role="alert">
      {{ t('productImportJobDetail.requestError') }}
    </div>
    <section v-if="job" class="summary" :aria-label="t('productImportJobDetail.summary')">
      <div>
        <dt>{{ t('productImportJobDetail.status') }}</dt>
        <dd><TableStatus :status="job.status" /></dd>
      </div>
      <div>
        <dt>{{ t('productImportJobDetail.mode') }}</dt>
        <dd>{{ job.mode }}</dd>
      </div>
      <div>
        <dt>{{ t('productImportJobDetail.total') }}</dt>
        <dd>{{ job.total_rows }}</dd>
      </div>
      <div>
        <dt>{{ t('productImportJobDetail.success') }}</dt>
        <dd>{{ job.success_rows }}</dd>
      </div>
      <div>
        <dt>{{ t('productImportJobDetail.error') }}</dt>
        <dd>{{ job.error_rows }}</dd>
      </div>
      <div>
        <dt>{{ t('productImportJobDetail.sourceFile') }}</dt>
        <dd>{{ job.source_file_name || t('list.noValue') }}</dd>
      </div>
      <div>
        <dt>{{ t('productImportJobDetail.hash') }}</dt>
        <dd>{{ job.source_file_hash || t('list.noValue') }}</dd>
      </div>
    </section>

    <ListQueryControls :show-page-size="false"
      :model-value="state.query"
      :filters="filters"
      :disabled="state.isBackgroundRefreshing.value"
      @apply="applyQuery"
      @reset="resetQuery"
    />
    <ListPagination
      :page="state.query.page"
      :page-size="state.query.page_size"
      :total="state.total.value"
      :disabled="state.isBackgroundRefreshing.value"
      :aria-label="t('productImportJobDetail.topPagination')"
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
      :title="t('productImportJobDetail.rowsTitle')"
    >
      <ListTable
        :rows="state.rows.value"
        :columns="columns"
        :sort="state.query.sort"
        :busy="state.isBackgroundRefreshing.value"
        row-key="row_number"
      >
        <template #cell-entity_key="{ row }">{{ row.entity_key || t('list.noValue') }}</template>
        <template #cell-row_status="{ row }">
          <span class="result-cell"
            ><TableStatus :status="row.row_status" /> {{ statusLabel(row.row_status) }}</span
          >
        </template>
        <template #cell-error_code="{ row }">{{ row.error_code || t('list.noValue') }}</template>
        <template #cell-error_message="{ row }">{{
          row.error_message || t('list.noValue')
        }}</template>
      </ListTable>
      <ListPagination :show-page-size="false"
        :page="state.query.page"
        :page-size="state.query.page_size"
        :total="state.total.value"
        :disabled="state.isBackgroundRefreshing.value"
        :aria-label="t('productImportJobDetail.bottomPagination')"
        @update:page="changePage"
        @update:page-size="changePageSize"
      />
    </ListShell>
  </section>
</template>

<style scoped>
.detail-panel {
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
  color: var(--ds-text-secondary);
}
.eyebrow {
  margin: 0 0 4px;
  color: var(--ds-primary);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  margin: 0;
  padding: 16px;
  border: 1px solid var(--ds-border-subtle);
  border-radius: 6px;
  background: var(--ds-bg-surface);
}
.summary div {
  min-width: 0;
}
.summary dt {
  color: var(--ds-text-secondary);
  font-size: 12px;
  font-weight: 700;
}
.summary dd {
  margin: 4px 0 0;
  overflow-wrap: anywhere;
}
.result-cell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.inline-error {
  color: var(--ds-danger);
}

@media (max-width: 640px) {
  .page-header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
