<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import type { Contact, Province, Ward } from '@/api/generated/types.gen'
import {
  changeContactState,
  listContactPage,
  listProvincePage,
  listWardPage,
  saveContact,
} from '@/services/foundation'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
} from '@/components/lists'
import { createListQuery, type ListColumn, type ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { reportApiError } from '@/services/errorBoundary'
import ContactFormModal from './ContactFormModal.vue'

const props = withDefaults(
  defineProps<{
    ownerId: string
    ownerType?: 'EMPLOYEE' | 'ORGANIZATION'
  }>(),
  { ownerType: 'ORGANIZATION' },
)

const { t } = useI18n()
const mode = ref<'active' | 'history'>('active')
const modalOpen = ref(false)
const editing = ref<Contact | null>(null)
const mutationBusy = ref(false)
const error = ref<unknown | null>(null)
const provinces = ref<Province[]>([])
const wards = ref<Ward[]>([])

const state = useListState<Contact>(
  (query) => {
    const ownerFilters = { ...query.filter }
    delete ownerFilters.status
    return listContactPage({
      ...query,
      filter: {
        ...ownerFilters,
        contact_type: props.ownerType,
        contact_ref_id: props.ownerId,
        status: mode.value === 'active' ? 'ACTIVE' : ['INACTIVE', 'CLOSED'],
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
  mode.value === 'active' ? t('contact.activeView') : t('contact.historyView'),
)

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

async function save(payload: Parameters<typeof saveContact>[0]): Promise<void> {
  mutationBusy.value = true
  error.value = null
  try {
    await saveContact(payload, editing.value?.id)
    modalOpen.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    mutationBusy.value = false
  }
}

async function toggleState(contact: Contact): Promise<void> {
  mutationBusy.value = true
  error.value = null
  try {
    await changeContactState(
      String(contact.id),
      contact.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
    )
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    mutationBusy.value = false
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

watch(mode, () => {
  state.setPage(1)
  void load()
})

watch(
  () => props.ownerId,
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
          type="button"
          role="tab"
          :aria-selected="mode === 'history'"
          @click="mode = 'history'"
        >
          {{ t('contact.historyView') }}
        </button>
      </div>
      <button type="button" class="primary-button" @click="openCreate">
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
            :busy="mutationBusy"
            :show-edit="true"
            :show-toggle="true"
            :edit-label="t('contact.edit')"
            :activate-label="t('contact.activate')"
            :deactivate-label="t('contact.deactivate')"
            @edit="openEdit(row)"
            @toggle="toggleState(row)"
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
    <div class="contact-actions" aria-live="polite">
      <span>{{ statusLabel }}</span>
    </div>
  </section>
</template>

<style scoped>
.contact-list {
  display: grid;
  gap: 12px;
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
