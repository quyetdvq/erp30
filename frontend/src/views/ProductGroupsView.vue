<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ProductGroup, ProductGroupCreate, ProductGroupPatch } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableDetailModal,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn, ListFilterDefinition, ListQuery } from '@/components/lists/listTypes'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import { buildVisibleProductGroupTree, type VisibleProductGroup } from '@/modules/productGroupTree'
import {
  changeProductGroupState,
  listProductGroupPage,
  saveProductGroup,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'
import { loadLookupPages } from '@/services/listLookups'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'

type TreeRow = VisibleProductGroup<ProductGroup>
type ProductGroupForm = Pick<ProductGroup, 'code' | 'name' | 'status'> & {
  description: string
  parent_id: string
}

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<ProductGroup>(listProductGroupPage, { sort: 'code' })

const expanded = ref(new Set<string>())
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailRow = ref<ProductGroup | null>(null)
const allGroups = ref<ProductGroup[]>([])
const parentLookupReady = ref(false)
const editingId = ref<string>()
const busy = ref(false)
const error = ref<unknown | null>(null)

function createEmptyForm(parent?: ProductGroup): ProductGroupForm {
  return {
    code: '',
    name: '',
    description: '',
    parent_id: parent ? String(parent.id) : '',
    status: 'ACTIVE',
  }
}

const form = ref<ProductGroupForm>(createEmptyForm())
const unsaved = useUnsavedChanges(form, modalOpen)
function closeForm(): void {
  if (!unsaved.confirmDiscard()) return
  modalOpen.value = false
  unsaved.markClean()
}

// Sort the current page in memory only. The API query is never changed by column clicks.
const localSort = ref('')
const collator = new Intl.Collator('vi', { numeric: true, sensitivity: 'base' })

function compareGroupRows(first: ProductGroup, second: ProductGroup): number {
  const term = localSort.value
  const field = term.startsWith('-') ? term.slice(1) : term
  if (field === 'code' || field === 'name' || field === 'status') {
    const direction = term.startsWith('-') ? -1 : 1
    return direction * collator.compare(String(first[field] ?? ''), String(second[field] ?? ''))
  }

  // Preserve the pre-existing tree order when no local column is selected.
  return `${first.code}${first.name}`.localeCompare(`${second.code}${second.name}`)
}

const columns: readonly ListColumn<TreeRow>[] = [
  { key: 'code', labelKey: 'product.code', sortable: true },
  { key: 'name', labelKey: 'product.name', sortable: true },
  { key: 'parent', labelKey: 'product.parentGroup' },
  { key: 'status', labelKey: 'product.status', sortable: true },
  { key: 'actions', labelKey: 'product.actions', align: 'end' },
]

const statusFilters: readonly ListFilterDefinition[] = [
  {
    key: 'status',
    labelKey: 'product.status',
    type: 'select',
    options: [
      { value: 'ACTIVE', labelKey: 'status.active' },
      { value: 'INACTIVE', labelKey: 'status.inactive' },
    ],
  },
]

// Base Permission only controls UI visibility; backend authorization remains authoritative.
const permissions = computed(() => ({
  create: hasBasePermission('product_group', 'create'),
  view: hasBasePermission('product_group', 'view'),
  edit: hasBasePermission('product_group', 'edit'),
  setstate: hasBasePermission('product_group', 'setstate'),
}))

const byParent = computed(() => {
  const groups = new Map<string, ProductGroup[]>()
  for (const row of state.rows.value) {
    const key = row.parent_id ? String(row.parent_id) : 'ROOT'
    const siblings = groups.get(key)
    if (siblings) siblings.push(row)
    else groups.set(key, [row])
  }
  // Sort siblings, not the flattened tree, to keep each parent next to its children.
  for (const rows of groups.values()) rows.sort(compareGroupRows)
  return groups
})

const byId = computed(() => {
  const index = new Map<string, ProductGroup>()
  for (const row of state.rows.value) {
    const id = String(row.id)
    if (!index.has(id)) index.set(id, row)
  }
  return index
})

const treeRows = computed<TreeRow[]>(() =>
  buildVisibleProductGroupTree(
    state.rows.value,
    byParent.value,
    expanded.value,
    localSort.value ? compareGroupRows : undefined,
  ),
)

const lookupByParent = computed(() => {
  const result = new Map<string, ProductGroup[]>()
  for (const row of allGroups.value) {
    const key = row.parent_id ? String(row.parent_id) : 'ROOT'
    const siblings = result.get(key) ?? []
    siblings.push(row)
    result.set(key, siblings)
  }
  return result
})

const parentOptions = computed(() => {
  const blocked = new Set<string>()
  if (editingId.value) {
    const groups = lookupByParent.value
    function collect(id: string): void {
      if (blocked.has(id)) return
      blocked.add(id)
      for (const child of groups.get(id) ?? []) collect(String(child.id))
    }
    collect(editingId.value)
  }
  return allGroups.value.filter((row) => !blocked.has(String(row.id)))
})

const parentSelectOptions = computed(() =>
  parentOptions.value.map((row) => ({
    value: String(row.id),
    label: `${row.code} - ${row.name}`,
  })),
)

function parentName(row: ProductGroup): string {
  return (
    allGroups.value.find((parent) => String(parent.id) === String(row.parent_id))?.name ??
    byId.value.get(String(row.parent_id))?.name ??
    t('product.root')
  )
}

const detailFields = computed(() => {
  const row = detailRow.value
  if (!row) return []
  return [
    { key: 'code', label: t('product.code'), value: row.code },
    { key: 'name', label: t('product.name'), value: row.name },
    { key: 'parent', label: t('product.parentGroup'), value: parentName(row) },
    { key: 'description', label: t('product.description'), value: row.description },
    { key: 'status', label: t('product.status'), status: row.status },
  ]
})

function toggleExpanded(id: string): void {
  const next = new Set(expanded.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expanded.value = next
}

async function loadParentLookup(): Promise<void> {
  parentLookupReady.value = false
  try {
    allGroups.value = await loadLookupPages(listProductGroupPage, { sort: 'name' })
    parentLookupReady.value = true
  } catch (caught: unknown) {
    error.value = caught
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

// ListTable owns the heading-click cycle: asc -> desc -> original order.
// Only localSort changes: do not call load() or state.setSort() here.
function handleSortChange(sort: string): void {
  const term = sort.split(',').pop() ?? ''
  const field = term.startsWith('-') ? term.slice(1) : term
  if (term && field !== 'code' && field !== 'name' && field !== 'status') return
  localSort.value = term
}

function handlePageChange(page: number): void {
  state.setPage(page)
  void load()
}

function handlePageSizeChange(pageSize: number): void {
  state.setPageSize(pageSize)
  void load()
}

function resetQuery(): void {
  localSort.value = ''
  state.reset()
  expanded.value = new Set()
  void load()
}

function openCreate(parent?: ProductGroup): void {
  editingId.value = undefined
  form.value = createEmptyForm(parent)
  unsaved.markClean()
  modalOpen.value = true
  if (!parentLookupReady.value) void loadParentLookup()
}

function openEdit(row: ProductGroup): void {
  editingId.value = String(row.id)
  form.value = {
    code: row.code,
    name: row.name,
    description: row.description ?? '',
    parent_id: row.parent_id ? String(row.parent_id) : '',
    status: row.status,
  }
  unsaved.markClean()
  modalOpen.value = true
  if (!parentLookupReady.value) void loadParentLookup()
}

function openView(row: ProductGroup): void {
  detailRow.value = row
  detailOpen.value = true
}

function updateParentId(value: string | string[]): void {
  form.value.parent_id = typeof value === 'string' ? value : ''
}

async function save(): Promise<void> {
  if (!parentLookupReady.value) return // Never save against a partial parent catalog.
  busy.value = true
  error.value = null
  try {
    const payload: ProductGroupCreate | ProductGroupPatch = {
      code: form.value.code,
      name: form.value.name,
      status: form.value.status,
      parent_id: form.value.parent_id || null,
      ...(form.value.description ? { description: form.value.description } : {}),
    }
    await saveProductGroup(payload, editingId.value)
    unsaved.markClean()
    modalOpen.value = false
    await Promise.all([load(), loadParentLookup()])
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

async function toggle(row: ProductGroup): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changeProductGroupState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
    await Promise.all([load(), loadParentLookup()])
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}

onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('product.groupsTitle') }}</h1>
      <p>{{ t('product.groupsDescription') }}</p>
    </div>
    <button v-if="permissions.create" type="button" class="primary-button" @click="openCreate()">
      {{ t('common.create') }}
    </button>
  </div>

  <section class="content-panel product-panel">
    <ListQueryControls
      :model-value="state.query"
      :filters="statusFilters"
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
      @update:page="handlePageChange"
      @update:page-size="handlePageSizeChange"
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
        :rows="treeRows"
        :columns="columns"
        :sort="localSort"
        @sort-change="handleSortChange"
      >
        <template #cell-code="{ row }">
          <span class="tree-code" :style="{ paddingLeft: `${row.depth * 20}px` }">
            <button
              v-if="row.hasChildren"
              type="button"
              class="tree-toggle"
              :aria-label="row.expanded ? t('product.collapse') : t('product.expand')"
              :aria-expanded="row.expanded"
              @click="toggleExpanded(String(row.id))"
            >
              {{ row.expanded ? '-' : '+' }}
            </button>
            <span v-else class="tree-toggle-spacer" />
            {{ row.code }}
          </span>
        </template>
        <template #cell-parent="{ row }">{{ parentName(row) }}</template>
        <template #cell-actions="{ row }">
          <span class="tree-row-actions">
            <TableRowActions
              :active="row.status === 'ACTIVE'"
              :busy="busy"
              :show-view="permissions.view"
              :show-edit="permissions.edit"
              :show-toggle="permissions.setstate"
              view-permission="product_group.view"
              edit-permission="product_group.edit"
              toggle-permission="product_group.setstate"
              :view-label="t('common.view')"
              :edit-label="t('common.edit')"
              :activate-label="t('common.activate')"
              :deactivate-label="t('common.deactivate')"
              @view="openView(row)"
              @edit="openEdit(row)"
              @toggle="toggle(row)"
            />
            <button
              v-if="permissions.create"
              type="button"
              class="icon-button"
              :aria-label="t('product.createChild')"
              :title="t('product.createChild')"
              @click="openCreate(row)"
            >
              +
            </button>
          </span>
        </template>
      </ListTable>
      <ListPagination
        :page="state.query.page"
        :page-size="state.query.page_size"
        :total="state.total.value"
        :aria-label="t('product.bottomPagination')"
        @update:page="handlePageChange"
        @update:page-size="handlePageSizeChange"
      />
    </ListShell>
  </section>

  <TableDetailModal
    :open="detailOpen"
    :title="t('product.groupDetails')"
    :fields="detailFields"
    @close="detailOpen = false"
  />

  <div v-if="modalOpen" class="modal-backdrop">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('product.groupForm')"
    >
      <header class="modal-card__header">
        <h2>{{ editingId ? t('common.edit') : t('common.create') }} {{ t('product.group') }}</h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="closeForm"
        >
          x
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="save">
        <label>
          <span>{{ t('product.code') }}</span>
          <input v-model="form.code" required />
        </label>
        <label>
          <span>{{ t('product.name') }}</span>
          <input v-model="form.name" required />
        </label>
        <label>
          <span>{{ t('product.parentGroup') }}</span>
          <SearchableSelect
            :disabled="!parentLookupReady"
            :model-value="form.parent_id"
            :options="parentSelectOptions"
            :placeholder="t('product.root')"
            :aria-label="t('product.parentGroup')"
            @update:model-value="updateParentId"
          />
        </label>
        <label>
          <span>{{ t('product.description') }}</span>
          <textarea v-model="form.description" rows="3" />
        </label>
        <label v-if="!editingId">
          <span>{{ t('product.status') }}</span>
          <select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select>
        </label>
        <p v-if="error" class="inline-error" role="alert">{{ t('product.requestError') }}</p>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="closeForm">
            {{ t('common.cancel') }}
          </button>
          <button
            type="submit"
            class="primary-button"
            :disabled="
              busy || !parentLookupReady || !(editingId ? permissions.edit : permissions.create)
            "
          >
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.product-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
}
.tree-code {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.tree-toggle,
.tree-toggle-spacer {
  width: 22px;
  min-width: 22px;
  height: 22px;
  display: inline-grid;
  place-items: center;
  border: 1px solid #b8ccd8;
  border-radius: 3px;
  background: #fff;
  color: #294b65;
  cursor: pointer;
}
.tree-toggle-spacer {
  border-color: transparent;
}
.tree-row-actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.inline-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(18 38 54 / 45%);
}
.modal-card {
  width: min(100%, 680px);
  max-height: calc(100vh - 40px);
  overflow: auto;
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
  padding: 15px 18px;
  border-bottom: 1px solid #e4ebef;
}
.modal-card__body {
  display: grid;
  gap: 12px;
  padding: 18px;
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
  margin: 6px -18px -18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.icon-button {
  border: 0;
  background: transparent;
  color: #536b7b;
  cursor: pointer;
}
</style>
