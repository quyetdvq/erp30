<script setup lang="ts">
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import type { Contact, Province, Ward } from '@/api/generated/types.gen'
import { listContactPage, listProvincePage, listWardPage, saveContact } from '@/services/foundation'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableDetailModal,
  TableRowActions,
  TableStatus,
} from '@/components/lists'
import {
  createListQuery,
  type ListColumn,
  type ListFilterDefinition,
  type ListQuery,
} from '@/components/lists/listTypes'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import { reportApiError } from '@/services/errorBoundary'
import { useBusinessWorkflowStates } from '@/composables/useBusinessWorkflowStates'
import ContactFormModal from './ContactFormModal.vue'
import { scopedContactListQuery } from './contactListQuery'

const props = withDefaults(
  defineProps<{
    ownerId: string
    ownerType?: 'EMPLOYEE' | 'ORGANIZATION'
  }>(),
  { ownerType: 'ORGANIZATION' },
)

const { t } = useI18n()
const { hasBasePermission } = useBasePermission()
// Permission boundaries follow the persisted Contact owner kind; legacy
// contact.* is never a fallback for an unregistered/future owner.
const ownerResource = computed(() =>
  props.ownerType === 'ORGANIZATION' ? 'organization_contact' : 'employee_contact',
)
const workflowStates = useBusinessWorkflowStates(ownerResource, [
  'ACTIVE',
  'DRAFT',
  'INACTIVE',
  'CLOSED',
])
function displayStatus(status: string): string {
  const code = status.trim().toUpperCase()
  const key = `status.${code.toLowerCase()}`
  const localized = t(key)
  return localized === key ? code : localized
}

const statusFilters = computed<readonly ListFilterDefinition[]>(() => [
  {
    key: 'status',
    labelKey: 'contact.status',
    type: 'select',
    options: workflowStates.codes.value.map((code) => ({
      value: code,
      labelKey: 'contact.status',
      label: displayStatus(code),
    })),
  },
])
function rowPermissionResource(contact: Contact): string {
  return contact.contact_type === 'ORGANIZATION'
    ? 'organization_contact'
    : contact.contact_type === 'EMPLOYEE'
      ? 'employee_contact'
      : ''
}
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailContact = ref<Contact | null>(null)
const editing = ref<Contact | null>(null)
const mutationBusy = ref(false)
const error = ref<unknown | null>(null)
const provinces = ref<Province[]>([])
const wards = ref<Ward[]>([])

// One authorized SQL page for every Contact status. An explicit status
// filter is optional; absence means ALL states (including custom lifecycles).
const state = useListState<Contact>(
  (query) => listContactPage(scopedContactListQuery(query, props.ownerType, props.ownerId)),
  createListQuery({ sort: '-id' }),
)

const columns: readonly ListColumn<Contact>[] = [
  { key: 'name', labelKey: 'contact.name', sortable: true },
  { key: 'email', labelKey: 'contact.email' },
  { key: 'phone', labelKey: 'contact.phone' },
  { key: 'mobile', labelKey: 'contact.mobile' },
  { key: 'status', labelKey: 'contact.status', sortable: true },
  { key: 'actions', labelKey: 'contact.actions' },
]

const detailFields = computed<readonly TableDetailField[]>(() => {
  const contact = detailContact.value
  if (!contact) return []
  return [
    { key: 'name', label: t('contact.name'), value: contact.name },
    { key: 'email', label: t('contact.email'), value: contact.email },
    { key: 'phone', label: t('contact.phone'), value: contact.phone },
    { key: 'mobile', label: t('contact.mobile'), value: contact.mobile },
    { key: 'address', label: t('contact.address'), value: contact.address },
    { key: 'province', label: t('contact.province'), value: provinceLabel(contact) },
    { key: 'ward', label: t('contact.ward'), value: wardLabel(contact) },
    { key: 'description', label: t('contact.description'), value: contact.description },
    { key: 'status', label: t('contact.status'), status: contact.status },
  ]
})

function provinceLabel(contact: Contact): string | null {
  const province = provinces.value.find((item) => String(item.id) === String(contact.province_id))
  return province?.name ?? contact.province_name ?? null
}

function wardLabel(contact: Contact): string | null {
  const ward = wards.value.find((item) => String(item.id) === String(contact.ward_id))
  return ward?.name_full || ward?.name || contact.ward_name || null
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

async function loadLocations(): Promise<void> {
  try {
    const provincePage = await listProvincePage(createListQuery({ page_size: 200 }))
    provinces.value = provincePage.data
  } catch (caught: unknown) {
    reportApiError(caught)
  }
}

async function loadWards(provinceId: string): Promise<void> {
  if (!provinceId) {
    wards.value = []
    return
  }
  try {
    const wardPage = await listWardPage(
      createListQuery({ page_size: 200, filter: { province_id: provinceId } }),
    )
    wards.value = wardPage.data
  } catch (caught: unknown) {
    reportApiError(caught)
  }
}

function openCreate(): void {
  editing.value = null
  modalOpen.value = true
}

function openEdit(contact: Contact): void {
  editing.value = contact
  modalOpen.value = true
  void loadWards(contact.province_id ?? '')
}

function openView(contact: Contact): void {
  detailContact.value = contact
  detailOpen.value = true
  void loadWards(contact.province_id ? String(contact.province_id) : '')
}

async function save(payload: Parameters<typeof saveContact>[0]): Promise<void> {
  mutationBusy.value = true
  error.value = null
  try {
    await saveContact(payload, editing.value?.id)
    modalOpen.value = false
    if (!editing.value) {
      // New Contacts default to the configured initial Lifecycle state.
      // Clear a previous status/search filter so they are never hidden.
      resetToAllContacts()
    }
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    mutationBusy.value = false
  }
}

async function afterWorkflowChange(): Promise<void> {
  // Leave the user's optional status filter intact. Without a filter the
  // transitioned Contact stays visible in the same unified list.
  await load()
}

function resetToAllContacts(): void {
  state.reset()
  state.setSort('-id')
}

function applyQuery(query: ListQuery): void {
  state.setSearch(query.q)
  state.setFilters(query.filter)
  state.setSort(query.sort)
  state.setPageSize(query.page_size)
  void load()
}

function resetQuery(): void {
  resetToAllContacts()
  void load()
}

watch(
  () => [props.ownerId, props.ownerType] as const,
  () => {
    resetToAllContacts()
    void load()
  },
)

onMounted(async () => {
  await Promise.all([load(), loadLocations()])
})
</script>

<template>
  <section class="contact-list" :aria-label="t('contact.title')">
    <header class="contact-list__header">
      <button
        v-if="hasBasePermission(ownerResource, 'create')"
        type="button"
        class="primary-button"
        @click="openCreate"
      >
        ＋ {{ t('contact.add') }}
      </button>
    </header>
    <div class="contact-list__toolbar">
      <ListQueryControls
        :model-value="state.query"
        :filters="statusFilters"
        @apply="applyQuery"
        @reset="resetQuery"
      />
      <button type="button" class="secondary-button" @click="void load()">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <p v-if="error" class="inline-error" role="alert">{{ t('contact.error') }}</p>
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
        <template #cell-status="{ row }">
          <span class="contact-list__status">
            <TableStatus :status="row.status" />
            <span>{{ displayStatus(row.status) }}</span>
          </span>
        </template>
        <template #cell-actions="{ row }">
          <TableRowActions authorization-mode="effective" :view-scope-verified="true" :allowed-actions="rowAllowedActions(row)"
            :active="row.status === 'ACTIVE'"
            :state-target-id="String(row.id)"
            :busy="mutationBusy"
            :show-view="true"
            :show-edit="true"
            :show-toggle="true"
            :view-permission="`${rowPermissionResource(row)}.view`"
            :edit-permission="`${rowPermissionResource(row)}.edit`"
            :toggle-permission="`${rowPermissionResource(row)}.setstate`"
            :edit-label="t('contact.edit')"
            :activate-label="t('contact.activate')"
            :deactivate-label="t('contact.deactivate')"
            :view-label="t('common.view')"
            @view="openView(row)"
            @edit="openEdit(row)"
            @state-changed="afterWorkflowChange"
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
    <ContactFormModal
      :open="modalOpen"
      :owner-id="props.ownerId"
      :owner-type="props.ownerType"
      :contact="editing"
      :provinces="provinces"
      :wards="wards"
      :busy="mutationBusy"
      @close="modalOpen = false"
      @save="save"
      @province-change="loadWards"
    />
    <TableDetailModal
      :open="detailOpen"
      :title="t('common.view') + ' - ' + t('contact.title')"
      :fields="detailFields"
      @close="detailOpen = false"
    />
  </section>
</template>

<style scoped>
.contact-list {
  display: grid;
  gap: 12px;
  min-width: 0;
  width: 100%;
}
.contact-list__header,
.contact-list__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.contact-list__header {
  justify-content: flex-end;
}
.contact-list__status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}
.contact-list__toolbar input {
  min-height: 34px;
  width: min(320px, 100%);
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
}
.inline-error {
  margin: 0;
  color: #b64444;
  font-size: 12px;
}
</style>
