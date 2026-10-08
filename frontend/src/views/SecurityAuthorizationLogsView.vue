<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AuthorizationLog } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableDetailModal,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import { useListState } from '@/composables/useListState'
import { getAuthorizationLogById, listAuthorizationLogPage } from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'
import { useBasePermission } from '@/composables/useBasePermission'

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<AuthorizationLog>((query) => listAuthorizationLogPage(query), {
  sort: '-created_at',
})
const error = ref<unknown | null>(null)
const selected = ref<AuthorizationLog | null>(null)
const detailOpen = ref(false)
const copiedId = ref<string | null>(null)
const columns: readonly ListColumn<AuthorizationLog>[] = [
  { key: 'created_at', labelKey: 'security.time', format: 'datetime', sortable: true },
  { key: 'user_id', labelKey: 'security.user' },
  { key: 'organization_id', labelKey: 'security.organizationScope' },
  { key: 'resource_id', labelKey: 'security.resource' },
  { key: 'action', labelKey: 'security.action' },
  { key: 'decision', labelKey: 'security.decision' },
  { key: 'reason', labelKey: 'security.reason' },
  { key: 'request_id', labelKey: 'security.requestId' },
  { key: 'row_actions', labelKey: 'security.actions', align: 'end' },
]
const detailFields = computed<readonly TableDetailField[]>(() => {
  const row = selected.value
  if (!row) return []
  return [
    { key: 'time', label: t('security.time'), value: row.created_at, format: 'datetime' },
    { key: 'user', label: t('security.user'), value: String(row.user_id) },
    {
      key: 'organization',
      label: t('security.organizationScope'),
      value: row.organization_id ? String(row.organization_id) : t('security.groupScope'),
    },
    { key: 'resource', label: t('security.resource'), value: String(row.resource_id) },
    { key: 'action', label: t('security.action'), value: row.action },
    { key: 'decision', label: t('security.decision'), value: row.decision },
    { key: 'reason', label: t('security.reason'), value: row.reason },
    { key: 'request', label: t('security.requestId'), value: row.request_id },
  ]
})
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
async function load(): Promise<void> {
  error.value = null
  try {
    await state.apply()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function openDetail(row: AuthorizationLog): Promise<void> {
  try {
    selected.value = await getAuthorizationLogById(String(row.id))
    detailOpen.value = true
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function copyRequestId(value: string | null | undefined): Promise<void> {
  if (!value || typeof navigator === 'undefined' || !navigator.clipboard) return
  await navigator.clipboard.writeText(value)
  copiedId.value = value
  window.setTimeout(() => {
    if (copiedId.value === value) copiedId.value = null
  }, 1800)
}
onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.logsTitle') }}</h1>
      <p>{{ t('security.logsDescription') }}</p>
    </div>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ t('security.requestError') }}</p>
  <section class="content-panel security-list-panel">
    <div class="security-toolbar">
      <ListQueryControls
        :model-value="state.query"
        @apply="applyQuery"
        @reset="resetQuery"
      /><button type="button" class="secondary-button" @click="void load">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <p class="security-note">{{ t('security.logScopeNote') }}</p>
    <ListShell
      :initial-loading="state.isInitialLoading.value"
      :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value"
      :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value"
      :error="state.queryState.error.value"
      ><ListTable
        :rows="state.rows.value"
        :columns="columns"
        :sort="state.query.sort"
        @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        "
        ><template #cell-decision="{ row }"
          ><span
            :class="['decision', row.decision === 'ALLOW' ? 'decision--allow' : 'decision--deny']"
            >{{ row.decision }}</span
          ></template
        ><template #cell-request_id="{ row }"
          ><button
            v-if="row.request_id"
            type="button"
            class="request-id"
            :title="t('security.copyRequestId')"
            :aria-label="t('security.copyRequestId')"
            @click="copyRequestId(row.request_id)"
          >
            {{ copiedId === row.request_id ? t('security.copied') : row.request_id }}</button
          ><span v-else>{{ t('list.noValue') }}</span></template
        ><template #cell-row_actions="{ row }"
          ><button
            v-if="hasBasePermission('authorization_log', 'view')"
            type="button"
            class="secondary-button"
            @click="openDetail(row)"
          >
            {{ t('common.view') }}
          </button></template
        ></ListTable
      ><ListPagination
        :page="state.query.page"
        :page-size="state.query.page_size"
        :total="state.total.value"
        @update:page="
          (page) => {
            state.setPage(page)
            void load()
          }
        "
        @update:page-size="
          (size) => {
            state.setPageSize(size)
            void load()
          }
        "
    /></ListShell>
  </section>
  <TableDetailModal
    :open="detailOpen"
    :title="t('security.logDetail')"
    :fields="detailFields"
    @close="detailOpen = false"
  />
</template>

<style scoped>
.security-list-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
}
.security-toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.security-note {
  margin: 0;
  color: #526b7b;
  font-size: 12px;
  line-height: 1.6;
}
.inline-error {
  margin: 0 0 12px;
  color: #b64444;
  font-size: 12px;
}
.decision {
  display: inline-flex;
  padding: 3px 7px;
  border: 1px solid;
  font-family: ui-monospace, monospace;
  font-size: 10px;
  font-weight: 700;
}
.decision--allow {
  border-color: #b8dfcc;
  background: #effbf4;
  color: #1c8a62;
}
.decision--deny {
  border-color: #e7c9c9;
  background: #fff4f4;
  color: #b64a4a;
}
.request-id {
  max-width: 170px;
  overflow: hidden;
  border: 0;
  background: transparent;
  color: #0b69a3;
  font-family: ui-monospace, monospace;
  font-size: 10px;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}
.request-id:focus-visible {
  outline: 2px solid #1876a8;
  outline-offset: 2px;
}
</style>
