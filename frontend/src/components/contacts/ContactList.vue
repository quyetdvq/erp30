<script setup lang="ts">
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
} from '@/components/lists'
import { createListQuery, type ListColumn, type ListQuery } from '@/components/lists/listTypes'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import { reportApiError } from '@/services/errorBoundary'
import { useBusinessWorkflowStates } from '@/composables/useBusinessWorkflowStates'
import ContactFormModal from './ContactFormModal.vue'

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
const initialState = computed(
  () =>
    workflowStates.initial.value ??
    (workflowStates.codes.value.includes('DRAFT')
      ? 'DRAFT'
      : (workflowStates.codes.value[0] ?? 'ACTIVE')),
)
const historyStates = computed(() =>
  workflowStates.codes.value.filter((code) => code !== 'ACTIVE' && code !== initialState.value),
)
function rowPermissionResource(contact: Contact): string {
  return contact.contact_type === 'ORGANIZATION'
    ? 'organization_contact'
    : contact.contact_type === 'EMPLOYEE'
      ? 'employee_contact'
      : ''
}
const mode = ref<'active' | 'draft' | 'history'>('active')
const modalOpen = ref(false)
const detailOpen = ref(false)
const detailContact = ref<Contact | null>(null)
const editing = ref<Contact | null>(null)
const mutationBusy = ref(false)
const error = ref<unknown | null>(null)
const provinces = ref<Province[]>([])
const wards = ref<Ward[]>([])

const state = useListState<Contact>(
  (query) => {
    const ownerFilters = { ...query.filter }
    delete ownerFilters.status
    if (mode.value === 'history' && historyStates.value.length === 0) {
      return Promise.resolve({
        data: [],
        meta: { page: query.page, page_size: query.page_size, total: 0 },
      })
    }
    return listContactPage({
      ...query,
      filter: {
        ...ownerFilters,
        contact_type: props.ownerType,
        contact_ref_id: props.ownerId,
        status:
          mode.value === 'active'
            ? 'ACTIVE'
            : mode.value === 'draft'
              ? initialState.value
              : historyStates.value,
      },
    })
  },
  createListQuery({ filter: { status: 'ACTIVE' } }),
)

const columns: readonly ListColumn<Contact>[] = [
  { key: 'name', labelKey: 'contact.name', sortable: true },
  { key: 'email', labelKey: 'contact.email' },
  { key: 'phone', labelKey: 'contact.phone' },
  { key: 'mobile', labelKey: 'contact.mobile' },
  { key: 'status', labelKey: 'contact.status', sortable: true },
  { key: 'actions', labelKey: 'contact.actions' },
]

const statusLabel = computed(() =>
  mode.value === 'active'
    ? t('contact.activeView')
    : mode.value === 'draft'
      ? t('contact.draftView')
      : t('contact.historyView'),
)
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
    const saved = await saveContact(payload, editing.value?.id)
    modalOpen.value = false
    if (!editing.value) {
      mode.value =
        saved.status === 'ACTIVE'
          ? 'active'
          : saved.status === initialState.value
            ? 'draft'
            : 'history'
    }
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    mutationBusy.value = false
  }
}

async function afterWorkflowChange(status: string): Promise<void> {
  mode.value = status === 'ACTIVE' ? 'active' : status === initialState.value ? 'draft' : 'history'
  await load()
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

watch(historyStates, () => {
  if (mode.value === 'history') void load()
})

watch(mode, () => {
  state.setPage(1)
  void load()
})

watch(
  () => [props.ownerId, props.ownerType] as const,
  () => {
    state.reset()
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
      <div class="contact-list__modes" role="tablist" :aria-label="t('contact.historyToggle')">
        <button
          type="button"
          role="tab"
          :aria-selected="mode === 'active'"
          @click="mode = 'active'"
        >
          {{ t('contact.activeView') }}
        </button>
        <button
          v-if="initialState !== 'ACTIVE'"
          type="button"
          role="tab"
          :aria-selected="mode === 'draft'"
          @click="mode = 'draft'"
        >
          {{ t('contact.draftView') }} ({{ initialState }})
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="mode === 'history'"
          @click="mode = 'history'"
        >
          {{ t('contact.historyView') }}
        </button>
      </div>
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
      <ListQueryControls :model-value="state.query" @apply="applyQuery" @reset="resetQuery" />
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
        <template #cell-actions="{ row }">
          <TableRowActions
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
    <div class="contact-actions" aria-live="polite">
      <span>{{ statusLabel }}</span>
    </div>
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
.contact-list__modes {
  display: inline-flex;
  gap: 4px;
}
.contact-list__modes button {
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 11px;
  background: #fff;
  color: #536b7b;
  cursor: pointer;
}
.contact-list__modes button[aria-selected='true'] {
  border-color: #1876a8;
  background: #eaf5fa;
  color: #1876a8;
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
.contact-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  color: #536b7b;
  font-size: 12px;
}
</style>
