<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import type { Organization, OrganizationCreate, State } from '@/api/generated/types.gen'
import ContactList from '@/components/contacts/ContactList.vue'
import OrganizationTreeSelect from '@/components/organizations/OrganizationTreeSelect.vue'
import { ListPagination, ListQueryControls, ListShell } from '@/components/lists'
import { useListState } from '@/composables/useListState'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import type { ListQuery } from '@/components/lists/listTypes'
import {
  changeOrganizationState,
  listOrganizationTree,
  saveOrganization,
} from '@/services/foundation'
import { reportApiError } from '@/services/errorBoundary'
import { allowedOrganizationChildren } from '@/modules/organizationMatrix'
import { useFormatters } from '@/composables/useFormatters'
import { useBasePermission } from '@/composables/useBasePermission'

type OrganizationType = Organization['type']

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
const formatters = useFormatters()
const selectedId = ref<string | null>(null)
const activeTab = ref<'overview' | 'contacts' | 'audit'>('overview')
const modalOpen = ref(false)
const editingId = ref<string | undefined>()
const mutationBusy = ref(false)
const error = ref<unknown | null>(null)
const form = ref({
  parent_id: '',
  type: 'GROUP' as OrganizationType,
  code: '',
  name: '',
  description: '',
  legal_name: '',
  tax_code: '',
  address: '',
  province_id: '',
  ward_id: '',
  status: 'ACTIVE' as State['status'],
  active_from: '',
  active_to: '',
})
const { markClean, confirmDiscard } = useUnsavedChanges(form, modalOpen)

function closeForm(): void {
  if (mutationBusy.value || !confirmDiscard()) return
  markClean()
  modalOpen.value = false
}

const state = useListState<Organization>((query) => listOrganizationTree(query), { sort: 'name' })
const organizations = computed(() => state.rows.value)
const selected = computed(
  () => organizations.value.find((item) => String(item.id) === selectedId.value) ?? null,
)
const children = computed(() => {
  const groups = new Map<string, Organization[]>()
  for (const item of organizations.value) {
    const key = item.parent_id ? String(item.parent_id) : 'ROOT'
    groups.set(key, [...(groups.get(key) ?? []), item])
  }
  for (const values of groups.values())
    values.sort((a, b) => `${a.code}${a.name}`.localeCompare(`${b.code}${b.name}`))
  return groups
})
const treeRows = computed(() => {
  const output: Array<{ item: Organization; depth: number }> = []
  function walk(parentId: string, depth: number): void {
    for (const item of children.value.get(parentId) ?? []) {
      output.push({ item, depth })
      walk(String(item.id), depth + 1)
    }
  }
  walk('ROOT', 0)
  return output
})
const parentOptions = computed(() => {
  const excluded = new Set<string>()
  if (editingId.value) {
    const collect = (id: string): void => {
      excluded.add(id)
      for (const child of organizations.value.filter(
        (item) => item.parent_id && String(item.parent_id) === id,
      ))
        collect(String(child.id))
    }
    collect(editingId.value)
  }
  return organizations.value.filter(
    (item) =>
      item.status === 'ACTIVE' &&
      !excluded.has(String(item.id)) &&
      allowedOrganizationChildren(item.type).length > 0,
  )
})
const typeOptions = computed(() => {
  const parent = organizations.value.find((item) => String(item.id) === form.value.parent_id)
  const options = [...allowedOrganizationChildren(parent?.type ?? null)]
  if (editingId.value && !options.includes(form.value.type)) options.push(form.value.type)
  return options
})
const companyFieldsVisible = computed(() => form.value.type === 'COMPANY')

function displayDate(value: string | null | undefined): string {
  return value ? formatters.formatDate(value) : '—'
}

async function load(): Promise<void> {
  error.value = null
  try {
    await state.apply()
    if (!selectedId.value && organizations.value[0])
      selectedId.value = String(organizations.value[0].id)
    if (selectedId.value && !selected.value)
      selectedId.value = organizations.value[0] ? String(organizations.value[0].id) : null
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
  const parent = selected.value
  const options = allowedOrganizationChildren(parent?.type ?? null)
  editingId.value = undefined
  form.value = {
    parent_id: parent ? String(parent.id) : '',
    type: options[0] ?? 'GROUP',
    code: '',
    name: '',
    description: '',
    legal_name: '',
    tax_code: '',
    address: '',
    province_id: '',
    ward_id: '',
    status: 'ACTIVE',
    active_from: '',
    active_to: '',
  }
  markClean()
  modalOpen.value = true
}

function openEdit(item: Organization): void {
  editingId.value = String(item.id)
  form.value = {
    parent_id: item.parent_id ? String(item.parent_id) : '',
    type: item.type,
    code: item.code,
    name: item.name,
    description: item.description ?? '',
    legal_name: item.legal_name ?? '',
    tax_code: item.tax_code ?? '',
    address: item.address ?? '',
    province_id: item.province_id ? String(item.province_id) : '',
    ward_id: item.ward_id ? String(item.ward_id) : '',
    status: item.status as State['status'],
    active_from: item.active_from ?? '',
    active_to: item.active_to ?? '',
  }
  markClean()
  modalOpen.value = true
}

function payload(): Omit<OrganizationCreate, 'status'> {
  const base: Omit<OrganizationCreate, 'status'> = {
    ...(form.value.parent_id ? { parent_id: form.value.parent_id } : {}),
    type: form.value.type,
    code: form.value.code,
    name: form.value.name,
    ...(form.value.description ? { description: form.value.description } : {}),
    ...(form.value.active_from ? { active_from: form.value.active_from } : {}),
    ...(form.value.active_to ? { active_to: form.value.active_to } : {}),
  }
  if (companyFieldsVisible.value)
    Object.assign(base, {
      ...(form.value.legal_name ? { legal_name: form.value.legal_name } : {}),
      ...(form.value.tax_code ? { tax_code: form.value.tax_code } : {}),
      ...(form.value.address ? { address: form.value.address } : {}),
      ...(form.value.province_id ? { province_id: form.value.province_id } : {}),
      ...(form.value.ward_id ? { ward_id: form.value.ward_id } : {}),
    })
  return base
}

async function save(): Promise<void> {
  mutationBusy.value = true
  error.value = null
  try {
    await saveOrganization(payload(), editingId.value)
    markClean()
    modalOpen.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    mutationBusy.value = false
  }
}
async function toggleState(item: Organization): Promise<void> {
  mutationBusy.value = true
  error.value = null
  try {
    await changeOrganizationState(String(item.id), item.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    mutationBusy.value = false
  }
}

watch(selectedId, () => {
  activeTab.value = 'overview'
})
watch(
  () => [form.value.parent_id, editingId.value] as const,
  () => {
    if (editingId.value) return
    const parent = organizations.value.find((item) => String(item.id) === form.value.parent_id)
    const options = allowedOrganizationChildren(parent?.type ?? null)
    if (!options.includes(form.value.type)) form.value.type = options[0] ?? 'GROUP'
  },
)
onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('organization.title') }}</h1>
      <p>{{ t('organization.description') }}</p>
    </div>
    <button
      type="button"
      class="primary-button"
      v-if="hasBasePermission('organization', 'create')"
      :disabled="!!selected && (selected.status !== 'ACTIVE' || selected.type === 'WAREHOUSE')"
      @click="openCreate"
    >
      ＋ {{ t('organization.createChild') }}
    </button>
  </div>
  <section class="content-panel organization-layout">
    <aside class="organization-tree" :aria-label="t('organization.tree')">
      <div class="organization-tree__toolbar">
        <ListQueryControls
          :model-value="state.query"
          @apply="applyQuery"
          @reset="resetQuery"
        /><button
          type="button"
          class="secondary-button"
          :aria-label="t('common.refresh')"
          @click="void load()"
        >
          ↻
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
        <ul class="tree-list">
          <li
            v-for="node in treeRows"
            :key="node.item.id"
            :class="{ selected: selectedId === String(node.item.id) }"
            :style="{ paddingLeft: `${node.depth * 18 + 8}px` }"
          >
            <button
              type="button"
              class="tree-node"
              :aria-current="selectedId === String(node.item.id) ? 'true' : undefined"
              @click="selectedId = String(node.item.id)"
            >
              <span class="type-badge">{{ node.item.type }}</span
              ><span
                ><strong>{{ node.item.code }}</strong> - {{ node.item.name }}</span
              ><span
                class="tree-status"
                :class="{ off: node.item.status !== 'ACTIVE' }"
                aria-hidden="true"
              />
            </button>
          </li>
        </ul>
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
        />
      </ListShell>
    </aside>
    <section class="organization-detail" aria-live="polite" :aria-label="t('organization.detail')">
      <template v-if="selected">
        <header class="organization-detail__header">
          <div>
            <span class="type-badge">{{ selected.type }}</span>
            <h2>{{ selected.code }} - {{ selected.name }}</h2>
            <p>{{ t(`status.${selected.status.toLowerCase()}`) }}</p>
          </div>
          <div class="detail-actions">
            <button
              v-if="hasBasePermission('organization', 'edit')"
              type="button"
              class="secondary-button"
              @click="openEdit(selected)"
            >
              {{ t('common.edit') }}</button
            ><button
              type="button"
              v-if="hasBasePermission('organization', 'setstate')"
              class="secondary-button"
              :disabled="mutationBusy"
              @click="toggleState(selected)"
            >
              {{ selected.status === 'ACTIVE' ? t('common.deactivate') : t('common.activate') }}
            </button>
          </div>
        </header>
        <div class="detail-tabs" role="tablist">
          <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'overview'"
            @click="activeTab = 'overview'"
          >
            {{ t('organization.overview') }}</button
          ><button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'contacts'"
            @click="activeTab = 'contacts'"
          >
            {{ t('organization.contacts') }}</button
          ><button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'audit'"
            @click="activeTab = 'audit'"
          >
            {{ t('organization.audit') }}
          </button>
        </div>
        <div v-if="activeTab === 'overview'" class="detail-grid">
          <div>
            <span>{{ t('organization.type') }}</span
            ><strong>{{ selected.type }}</strong>
          </div>
          <div>
            <span>{{ t('organization.parent') }}</span
            ><strong>{{
              organizations.find((item) => String(item.id) === String(selected?.parent_id))?.name ??
              t('organization.root')
            }}</strong>
          </div>
          <div>
            <span>{{ t('organization.activeFrom') }}</span
            ><strong>{{ displayDate(selected.active_from) }}</strong>
          </div>
          <div>
            <span>{{ t('organization.activeTo') }}</span
            ><strong>{{ displayDate(selected.active_to) }}</strong>
          </div>
          <div v-if="selected.type === 'COMPANY'">
            <span>{{ t('organization.legalName') }}</span
            ><strong>{{ selected.legal_name || '—' }}</strong>
          </div>
          <div v-if="selected.type === 'COMPANY'">
            <span>{{ t('organization.taxCode') }}</span
            ><strong>{{ selected.tax_code || '—' }}</strong>
          </div>
        </div>
        <ContactList
          v-else-if="activeTab === 'contacts'"
          :owner-id="String(selected.id)"
          owner-type="ORGANIZATION"
        />
        <div v-else class="audit-placeholder">
          <h3>{{ t('organization.audit') }}</h3>
          <p>{{ t('organization.auditDescription') }}</p>
          <dl>
            <dt>{{ t('organization.status') }}</dt>
            <dd>{{ selected.status }}</dd>
            <dt>{{ t('organization.activeFrom') }}</dt>
            <dd>{{ displayDate(selected.active_from) }}</dd>
            <dt>{{ t('organization.activeTo') }}</dt>
            <dd>{{ displayDate(selected.active_to) }}</dd>
          </dl>
        </div>
      </template>
      <div v-else class="empty-state">{{ t('organization.noSelection') }}</div>
    </section>
  </section>
  <div v-if="modalOpen" class="modal-backdrop">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('organization.form')"
    >
      <header>
        <div>
          <h2>{{ editingId ? t('organization.edit') : t('organization.createChild') }}</h2>
          <p>{{ t('organization.formHint') }}</p>
        </div>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="closeForm"
        >
          ×
        </button>
      </header>
      <form @submit.prevent="save">
        <label>
          <span>{{ t('organization.parent') }}</span>
          <OrganizationTreeSelect
            v-model="form.parent_id"
            :organizations="parentOptions"
            :placeholder="t('organization.root')"
            :clearable="true"
          />
        </label>
        <label
          ><span>{{ t('organization.type') }}</span
          ><select v-model="form.type" required>
            <option v-for="type in typeOptions" :key="type" :value="type">{{ type }}</option>
          </select></label
        >
        <div class="form-grid">
          <label
            ><span>{{ t('organization.code') }}</span
            ><input v-model="form.code" required /></label
          ><label
            ><span>{{ t('organization.name') }}</span
            ><input v-model="form.name" required
          /></label>
        </div>
        <label
          ><span>{{ t('organization.descriptionField') }}</span
          ><textarea v-model="form.description" rows="3" />
        </label>
        <div v-if="companyFieldsVisible" class="company-fields">
          <h3>{{ t('organization.companyFields') }}</h3>
          <div class="form-grid">
            <label
              ><span>{{ t('organization.legalName') }}</span
              ><input v-model="form.legal_name" /></label
            ><label
              ><span>{{ t('organization.taxCode') }}</span
              ><input v-model="form.tax_code"
            /></label>
          </div>
          <label
            ><span>{{ t('organization.address') }}</span
            ><input v-model="form.address"
          /></label>
        </div>
        <div class="form-grid">
          <label
            ><span>{{ t('organization.activeFrom') }}</span
            ><input v-model="form.active_from" type="date" /></label
          ><label
            ><span>{{ t('organization.activeTo') }}</span
            ><input v-model="form.active_to" type="date"
          /></label>
        </div>
        <label v-if="editingId">
          <span>{{ t('organization.status') }}</span>
          <output>{{ form.status }}</output>
        </label>
        <p v-else class="workflow-initial-note" role="note">
          {{ t('workflow.initialManaged') }}
        </p>
        <p v-if="error" class="inline-error" role="alert">{{ t('organization.error') }}</p>
        <footer>
          <button type="button" class="secondary-button" @click="closeForm">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="
              mutationBusy || !hasBasePermission('organization', editingId ? 'edit' : 'create')
            "
          >
            {{ mutationBusy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.organization-layout {
  display: grid;
  grid-template-columns: minmax(300px, 0.9fr) minmax(0, 1.6fr);
  gap: 20px;
  padding: 18px 20px 20px;
}
.organization-tree {
  min-width: 0;
  border-right: 1px solid #e4ebef;
  padding-right: 18px;
}
.organization-tree__toolbar {
  display: flex;
  align-items: end;
  gap: 8px;
  margin-bottom: 14px;
}
.tree-list {
  display: grid;
  gap: 3px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.tree-list li {
  border-radius: 4px;
}
.tree-list li.selected {
  background: #eaf5fa;
}
.tree-node {
  display: flex;
  align-items: center;
  gap: 7px;
  width: 100%;
  border: 0;
  padding: 8px;
  background: transparent;
  color: #183b57;
  text-align: left;
  cursor: pointer;
}
.tree-node strong {
  font-weight: 700;
}
.type-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 3px;
  padding: 2px 5px;
  background: #eaf5fa;
  color: #1876a8;
  font-size: 10px;
  font-weight: 700;
}
.tree-status {
  width: 8px;
  height: 8px;
  margin-left: auto;
  border-radius: 50%;
  background: #2e9d68;
}
.tree-status.off {
  background: #bd7c32;
}
.organization-detail {
  min-width: 0;
}
.organization-detail__header {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
  border-bottom: 1px solid #e4ebef;
  padding-bottom: 14px;
}
.organization-detail__header h2 {
  margin: 7px 0 4px;
  color: #183b57;
  font-size: 20px;
}
.organization-detail__header p {
  margin: 0;
  color: #536b7b;
}
.detail-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}
.detail-tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #e4ebef;
}
.detail-tabs button {
  border: 0;
  border-bottom: 2px solid transparent;
  padding: 10px 12px;
  background: transparent;
  color: #536b7b;
  cursor: pointer;
}
.detail-tabs button[aria-selected='true'] {
  border-bottom-color: #1876a8;
  color: #1876a8;
  font-weight: 700;
}
.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  padding-top: 16px;
}
.detail-grid div {
  display: grid;
  gap: 4px;
  border-bottom: 1px solid #edf2f4;
  padding-bottom: 10px;
}
.detail-grid span,
.audit-placeholder dt {
  color: #8292a0;
  font-size: 12px;
}
.detail-grid strong,
.audit-placeholder dd {
  color: #183b57;
}
.audit-placeholder {
  display: grid;
  gap: 10px;
  padding-top: 16px;
}
.audit-placeholder h3 {
  margin: 0;
  color: #183b57;
}
.audit-placeholder p {
  margin: 0;
  color: #536b7b;
}
.audit-placeholder dl {
  display: grid;
  grid-template-columns: 160px 1fr;
  gap: 8px;
  margin: 0;
}
.audit-placeholder dd {
  margin: 0;
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
.modal-card header,
.modal-card footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 15px 18px;
  border-bottom: 1px solid #e4ebef;
}
.modal-card header h2 {
  margin: 0;
  color: #173c59;
  font-size: 18px;
}
.modal-card header p {
  margin: 4px 0 0;
  color: #8292a0;
  font-size: 12px;
}
.modal-card form {
  display: grid;
  gap: 12px;
  padding: 18px;
}
.modal-card label {
  display: grid;
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}
.modal-card input,
.modal-card select,
.modal-card textarea {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  font: inherit;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.company-fields {
  display: grid;
  gap: 10px;
  border-left: 3px solid #eaf5fa;
  padding-left: 12px;
}
.company-fields h3 {
  margin: 0;
  color: #183b57;
  font-size: 14px;
}
.modal-card footer {
  justify-content: flex-end;
  margin: 6px -18px -18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.icon-button {
  border: 0;
  background: transparent;
  color: #536b7b;
  font-size: 22px;
  cursor: pointer;
}
.inline-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}
@media (max-width: 900px) {
  .organization-layout {
    grid-template-columns: 1fr;
    padding: 16px;
  }
  .organization-tree {
    border-right: 0;
    border-bottom: 1px solid #e4ebef;
    padding-right: 0;
    padding-bottom: 16px;
  }
}
@media (max-width: 600px) {
  .form-grid,
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
