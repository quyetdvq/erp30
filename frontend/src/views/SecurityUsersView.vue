<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableDetailModal,
  TableStatus,
  TableRowActions,
} from '@/components/lists'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { listSecurityUserPage } from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'
import type { ManagedUser } from '@/services/admin'

const { t } = useI18n()
const state = useListState<ManagedUser>((query) => listSecurityUserPage(query), {
  sort: 'username',
})
const selected = ref<ManagedUser | null>(null)
const detailOpen = ref(false)
const error = ref<unknown | null>(null)
const busy = ref(false)

const columns: readonly ListColumn<ManagedUser>[] = [
  { key: 'id', labelKey: 'users.id', sortable: true },
  { key: 'username', labelKey: 'users.username', sortable: true },
  { key: 'name', labelKey: 'users.name', sortable: true },
  { key: 'email', labelKey: 'users.email' },
  { key: 'status', labelKey: 'users.status', sortable: true },
  { key: 'groups', labelKey: 'users.groups' },
  { key: 'actions', labelKey: 'security.actions' },
]
const detailFields = computed<readonly TableDetailField[]>(() => {
  const user = selected.value
  if (!user) return []
  return [
    { key: 'id', label: t('users.id'), value: String(user.id) },
    { key: 'username', label: t('users.username'), value: user.username },
    { key: 'name', label: t('users.name'), value: user.name ?? t('list.noValue') },
    { key: 'email', label: t('users.email'), value: user.email ?? t('list.noValue') },
    { key: 'status', label: t('users.status'), status: user.active },
    {
      key: 'directPermissionCount',
      label: t('security.directPermissionCount'),
      value: String(user.direct_permission_count ?? 0),
    },
    {
      key: 'groups',
      label: t('users.groups'),
      value: user.groups.join(', ') || t('list.noValue'),
    },
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
function openDetails(row: ManagedUser): void {
  selected.value = row
  detailOpen.value = true
}
function groupLabel(user: ManagedUser): string {
  return user.groups.join(', ') || t('list.noValue')
}

onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.usersTitle') }}</h1>
      <p>{{ t('security.usersDescription') }}</p>
    </div>
    <button type="button" class="secondary-button" :disabled="busy" @click="void load">
      ↻ {{ t('common.refresh') }}
    </button>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ t('security.requestError') }}</p>
  <section class="content-panel security-list-panel">
    <div class="security-toolbar">
      <ListQueryControls :model-value="state.query" @apply="applyQuery" @reset="resetQuery" />
      <button type="button" class="secondary-button" :disabled="busy" @click="void load">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
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
        @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        "
      >
        <template #cell-username="{ row }"
          ><strong>{{ row.username || t('list.noValue') }}</strong></template
        >
        <template #cell-name="{ row }">{{ row.name || t('list.noValue') }}</template>
        <template #cell-status="{ row }"><TableStatus :status="row.active" /></template>
        <template #cell-groups="{ row }"
          ><span class="group-summary">{{ groupLabel(row) }}</span></template
        >
        <template #cell-actions="{ row }">
          <TableRowActions
            :busy="busy"
            :show-view="true"
            :view-label="t('security.viewDetails')"
            @view="void openDetails(row)"
          />
        </template>
      </ListTable>
      <ListPagination
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
      />
    </ListShell>
  </section>
  <TableDetailModal
    :open="detailOpen"
    :title="
      selected ? `${t('users.detailTitle')}: ${selected.username ?? ''}` : t('users.detailTitle')
    "
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
.group-summary {
  color: #345f77;
  font-size: 12px;
}
.inline-error {
  margin: 0 0 12px;
  color: #b64444;
  font-size: 12px;
}
@media (max-width: 700px) {
  .security-list-panel {
    padding: 15px 12px;
  }
}
</style>
