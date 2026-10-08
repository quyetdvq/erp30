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
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import {
  changeProductGroupState,
  listProductGroupPage,
  saveProductGroup,
} from '@/services/productCatalog'
import { reportApiError } from '@/services/errorBoundary'

type TreeRow = ProductGroup & { depth: number; hasChildren: boolean; expanded: boolean }

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const state = useListState<ProductGroup>((query) => listProductGroupPage(query), { sort: 'code' })
const expanded = ref(new Set<string>())
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailRow = ref<ProductGroup | null>(null)
const editingId = ref<string>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const form = ref({
  code: '',
  name: '',
  description: '',
  parent_id: '',
  status: 'ACTIVE' as ProductGroup['status'],
})

const columns: readonly ListColumn<TreeRow>[] = [
  { key: 'code', labelKey: 'product.code', sortable: true },
  { key: 'name', labelKey: 'product.name', sortable: true },
  { key: 'parent', labelKey: 'product.parentGroup' },
  { key: 'status', labelKey: 'product.status', sortable: true },
  { key: 'actions', labelKey: 'product.actions' },
]

const byParent = computed(() => {
  const groups = new Map<string, ProductGroup[]>()
  for (const row of state.rows.value) {
    const key = row.parent_id ? String(row.parent_id) : 'ROOT'
    groups.set(key, [...(groups.get(key) ?? []), row])
  }
  for (const rows of groups.values())
    rows.sort((a, b) => `${a.code}${a.name}`.localeCompare(`${b.code}${b.name}`))
  return groups
})

const treeRows = computed<TreeRow[]>(() => {
  const output: TreeRow[] = []
  const visited = new Set<string>()
  function walk(parent: string, depth: number): void {
    for (const item of byParent.value.get(parent) ?? []) {
      const id = String(item.id)
      if (visited.has(id)) continue
      visited.add(id)
      const hasChildren = (byParent.value.get(id) ?? []).length > 0
      const isExpanded = expanded.value.has(id)
      output.push({ ...item, depth, hasChildren, expanded: isExpanded })
      if (isExpanded) walk(id, depth + 1)
    }
  }
  walk('ROOT', 0)
  for (const item of state.rows.value) {
    if (!visited.has(String(item.id)))
      output.push({ ...item, depth: 0, hasChildren: false, expanded: false })
  }
  return output
})

const parentOptions = computed(() => {
  const blocked = new Set<string>()
  if (editingId.value) {
    const collect = (id: string): void => {
      if (blocked.has(id)) return
      blocked.add(id)
      for (const child of state.rows.value.filter((row) => String(row.parent_id) === id))
        collect(String(child.id))
    }
    collect(editingId.value)
  }
  return state.rows.value.filter((row) => !blocked.has(String(row.id)))
})

function parentName(row: ProductGroup): string {
  return (
    state.rows.value.find((candidate) => String(candidate.id) === String(row.parent_id))?.name ??
    t('product.root')
  )
}
function toggleExpanded(id: string): void {
  const next = new Set(expanded.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expanded.value = next
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
  expanded.value = new Set()
  void load()
}
function openCreate(parent?: ProductGroup): void {
  editingId.value = undefined
  form.value = {
    code: '',
    name: '',
    description: '',
    parent_id: parent ? String(parent.id) : '',
    status: 'ACTIVE',
  }
  modalOpen.value = true
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
  modalOpen.value = true
}
function openView(row: ProductGroup): void {
  detailRow.value = row
  detailOpen.value = true
}
async function save(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const payload: ProductGroupCreate | ProductGroupPatch = {
      code: form.value.code,
      name: form.value.name,
      status: form.value.status,
      ...(form.value.parent_id ? { parent_id: form.value.parent_id } : { parent_id: null }),
      ...(form.value.description ? { description: form.value.description } : {}),
    }
    await saveProductGroup(payload, editingId.value)
    modalOpen.value = false
    await load()
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
    await load()
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
    <button
      v-if="hasBasePermission('product_group', 'create')"
      type="button"
      class="primary-button"
      @click="openCreate()"
    >
      {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel product-panel">
    <ListQueryControls
      :model-value="state.query"
      :filters="[
        {
          key: 'status',
          labelKey: 'product.status',
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
      <ListTable :rows="treeRows" :columns="columns" :sort="state.query.sort">
        <template #cell-code="{ row }"
          ><span class="tree-code" :style="{ paddingLeft: `${row.depth * 20}px` }"
            ><button
              v-if="row.hasChildren"
              type="button"
              class="tree-toggle"
              :aria-label="row.expanded ? t('product.collapse') : t('product.expand')"
              @click="toggleExpanded(String(row.id))"
            >
              {{ row.expanded ? '-' : '+' }}</button
            ><span v-else class="tree-toggle-spacer" />{{ row.code }}</span
          ></template
        >
        <template #cell-parent="{ row }">{{ parentName(row) }}</template>
        <template #cell-actions="{ row }"
          ><TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="hasBasePermission('product_group', 'view')"
            :show-edit="hasBasePermission('product_group', 'edit')"
            :show-toggle="hasBasePermission('product_group', 'setstate')"
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
          /><button
            v-if="hasBasePermission('product_group', 'create')"
            type="button"
            class="icon-button"
            :aria-label="t('product.createChild')"
            :title="t('product.createChild')"
            @click="openCreate(row)"
          >
            +
          </button></template
        >
      </ListTable>
      <ListPagination
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
  <TableDetailModal
    :open="detailOpen"
    :title="t('product.groupDetails')"
    :fields="
      detailRow
        ? [
            { key: 'code', label: t('product.code'), value: detailRow.code },
            { key: 'name', label: t('product.name'), value: detailRow.name },
            { key: 'parent', label: t('product.parentGroup'), value: parentName(detailRow) },
            { key: 'description', label: t('product.description'), value: detailRow.description },
            { key: 'status', label: t('product.status'), status: detailRow.status },
          ]
        : []
    "
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
          @click="modalOpen = false"
        >
          x
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="save">
        <label
          ><span>{{ t('product.code') }}</span
          ><input v-model="form.code" required /></label
        ><label
          ><span>{{ t('product.name') }}</span
          ><input v-model="form.name" required /></label
        ><label
          ><span>{{ t('product.parentGroup') }}</span
          ><SearchableSelect
            :model-value="form.parent_id"
            :options="
              parentOptions.map((row) => ({
                value: String(row.id),
                label: `${row.code} - ${row.name}`,
              }))
            "
            :placeholder="t('product.root')"
            :aria-label="t('product.parentGroup')"
            @update:model-value="
              (value) => (form.parent_id = typeof value === 'string' ? value : '')
            " /></label
        ><label
          ><span>{{ t('product.description') }}</span
          ><textarea v-model="form.description" rows="3" /></label
        ><label v-if="!editingId"
          ><span>{{ t('product.status') }}</span
          ><select v-model="form.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label
        >
        <p v-if="error" class="inline-error" role="alert">{{ t('product.requestError') }}</p>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="modalOpen = false">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="busy || !hasBasePermission('product_group', editingId ? 'edit' : 'create')"
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
