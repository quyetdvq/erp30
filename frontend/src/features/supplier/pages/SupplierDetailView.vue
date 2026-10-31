<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import RemoteSearchableSelect from '@/components/forms/RemoteSearchableSelect.vue'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { TableStatus } from '@/components/lists'
import { NIcon } from 'naive-ui'
import { PeopleOutline } from '@vicons/ionicons5'
import SupplierStateAction from '../components/SupplierStateAction.vue'
import ContactList from '@/components/contacts/ContactList.vue'
import BusinessHistoryPanel from '@/features/history/components/BusinessHistoryPanel.vue'
import { useBasePermission } from '@/composables/useBasePermission'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { listProvinces, searchCustomerWards } from '@/services/customer'
import { getWardById } from '@/services/foundation'
import { resolveOrganizationLabels, chunks } from '@/services/productLookups'
import { reportApiError } from '@/services/errorBoundary'
import {
  supplierById, saveSupplier, replaceSupplierCompanyUsage,
  searchSupplierCompanies, type Supplier,
} from '@/services/supplier'
import {
  emptySupplierDraft, supplierCreateBody, supplierPatchBody,
  supplierUsageBody, validSupplierDraft, type SupplierDraft,
} from '../supplierPayload'

// Optional embedded view is used by the authorized SYSTEM History monitor.
const props = defineProps<{ supplierId?: string }>()
const emit = defineEmits<{ close: []; saved: [] }>()
const { t, te } = useI18n()
function statusLabel(code: string): string {
  const key = `status.${code.toLowerCase()}`
  return te(key) ? t(key) : code
}
const route = useRoute()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const embedded = computed(() => props.supplierId !== undefined)
const id = computed(() => props.supplierId ?? String(route.params.id ?? ''))
const isNew = computed(() => !embedded.value && route.name === 'suppliers-new')
const isEdit = computed(() => !embedded.value && (isNew.value || route.name === 'suppliers-edit'))
const row = ref<Supplier | null>(null)
const draft = ref<SupplierDraft>(emptySupplierDraft())
const busy = ref(false)
const loading = ref(false)
const error = ref('')
const conflict = ref(false)
const profileAttempted = ref(false)
const profileErrors = computed(() => {
  if (!profileAttempted.value) return { name: false, phone: false, address: false, province: false, ward: false }
  const phone = draft.value.phone.replace(/[\s().-]+/g, '')
  return {
    name: !draft.value.name.trim(),
    phone: !/^\+?[0-9]{7,15}$/.test(phone),
    address: !draft.value.address.trim(),
    province: !draft.value.province_id,
    ward: !draft.value.ward_id,
  }
})
const tab = ref<'profile' | 'usage' | 'contacts' | 'history'>('profile')
const provinces = ref<{ id: string; code: string; name: string }[]>([])
const wardLabel = ref('')
const names = ref<Record<string, string>>({})
const selectedCompany = ref('')
const dialog = ref<HTMLElement | null>(null)
const detailBody = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
let returnFocusTo: HTMLElement | null = null
const provinceOptions = computed(() => provinces.value.map((province) => ({
  value: province.id, label: `${province.code} - ${province.name}`,
})))
const canCreate = computed(() => hasBasePermission('supplier', 'create'))
const canEdit = computed(() => !isNew.value && hasBasePermission('supplier', 'edit') && row.value?.allowed_actions?.edit === true)
const canSaveProfile = computed(() => isNew.value ? canCreate.value : isEdit.value && canEdit.value)
const canEditUsage = computed(() => !isNew.value && canEdit.value)
const canState = computed(() => !isNew.value && hasBasePermission('supplier', 'setstate') && row.value?.allowed_actions?.setstate === true)
const activeDraft = computed(() => isNew.value || isEdit.value || tab.value === 'usage')
const unsaved = useUnsavedChanges(draft, activeDraft)

function assign(rowData: Supplier): void {
  profileAttempted.value = false
  row.value = rowData
  draft.value = {
    supplier_code: rowData.supplier_code,
    name: rowData.name,
    supplier_type: rowData.supplier_type,
    phone: rowData.phone,
    email: rowData.email ?? '',
    tax_code: rowData.tax_code ?? '',
    address: rowData.address ?? '',
    province_id: String(rowData.province_id ?? ''),
    ward_id: String(rowData.ward_id ?? ''),
    payment_term_code: rowData.payment_term_code ?? '',
    all_companies: rowData.all_companies === true || String(rowData.all_companies) === '1',
    company_ids: [...(rowData.company_ids ?? [])],
  }
  unsaved.markClean()
}
async function loadSelectedCompanyLabels(): Promise<void> {
  if (!hasBasePermission('organization', 'view') || !draft.value.company_ids.length) return
  try {
    for (const ids of chunks(draft.value.company_ids, 50)) {
      const found = await resolveOrganizationLabels(ids)
      for (const item of found) names.value[item.id] = `${item.code} - ${item.name}`
    }
  } catch (caught: unknown) { reportApiError(caught) }
}
async function loadWardLabel(): Promise<void> {
  wardLabel.value = ''
  if (!draft.value.ward_id || !draft.value.province_id) return
  try {
    const found = await getWardById(draft.value.ward_id)
    if (String(found.province_id ?? '') === draft.value.province_id) {
      wardLabel.value = found.name_full || found.name
    }
  } catch (caught: unknown) { reportApiError(caught) }
}
async function refreshSupplier(): Promise<void> {
  if (isNew.value) return
  loading.value = true
  error.value = ''
  try {
    assign(await supplierById(id.value))
    await Promise.all([loadWardLabel(), loadSelectedCompanyLabels()])
  } catch (caught: unknown) {
    error.value = t('supplier.loadError')
    reportApiError(caught)
  } finally { loading.value = false }
}
async function loadProvinces(): Promise<void> {
  try {
    provinces.value = (await listProvinces()).map((item) => ({ id: String(item.id), code: item.code, name: item.name }))
  } catch (caught: unknown) { reportApiError(caught) }
}
function changeProvince(value: string | string[]): void {
  const provinceId = typeof value === 'string' ? value : value[0] ?? ''
  if (provinceId === draft.value.province_id) return
  draft.value.province_id = provinceId
  draft.value.ward_id = ''
  wardLabel.value = ''
}
async function searchWards(q: string, page: number) {
  const selectedProvince = draft.value.province_id
  if (!selectedProvince) return { options: [], total: 0 }
  const result = await searchCustomerWards(selectedProvince, q, page)
  // Discard a late response after the Province changes.
  if (draft.value.province_id !== selectedProvince) return { options: [], total: 0 }
  return {
    options: result.data.filter((ward) => String(ward.province_id ?? '') === selectedProvince)
      .map((ward) => ({ value: String(ward.id), label: ward.name_full || ward.name })),
    total: result.meta.total,
  }
}
async function searchCompanies(q: string, page: number) {
  const result = await searchSupplierCompanies(q, page)
  const options = result.data.map((item) => {
    const label = `${item.code} - ${item.name}`
    names.value[String(item.id)] = label
    return { value: String(item.id), label }
  })
  return { options, total: result.meta.total }
}
function selectCompany(value: string): void {
  selectedCompany.value = ''
  if (value && !draft.value.company_ids.includes(value) && draft.value.company_ids.length < 512) {
    draft.value.company_ids = [...draft.value.company_ids, value]
  }
}
function removeCompany(value: string): void {
  draft.value.company_ids = draft.value.company_ids.filter((id) => id !== value)
}
function toggleAllCompanies(value: boolean): void {
  draft.value.all_companies = value
  if (value) draft.value.company_ids = []
}
function changeTab(value: typeof tab.value): void {
  if (!unsaved.confirmDiscard()) return
  if (row.value) assign(row.value)
  tab.value = value
  if (detailBody.value) detailBody.value.scrollTop = 0
}
function reloadAfterConflict(): void {
  if (!unsaved.confirmDiscard()) return
  void refreshSupplier()
}
function close(): void {
  if (busy.value || !unsaved.confirmDiscard()) return
  unsaved.markClean()
  emit('close')
}
function beforeStateOpen(): boolean { return unsaved.confirmDiscard() }
async function afterStateChanged(): Promise<void> {
  await refreshSupplier()
  emit('saved')
}
function onStateConflict(): void {
  conflict.value = true
  error.value = t('supplier.conflict')
}
onBeforeUnmount(() => { if (returnFocusTo?.isConnected) returnFocusTo.focus() })
function messageForError(caught: unknown): string {
  const status = typeof caught === 'object' && caught !== null && 'response' in caught
    ? (caught as { response?: { status?: number } }).response?.status : undefined
  if (status === 409) { conflict.value = true; return t('supplier.conflict') }
  return t('supplier.saveError')
}
async function saveProfile(): Promise<void> {
  if (!canSaveProfile.value || busy.value) return
  profileAttempted.value = true
  if (!validSupplierDraft(draft.value)) { error.value = t('supplier.required'); return }
  busy.value = true; error.value = ''; conflict.value = false
  try {
    if (isNew.value) {
      const created = await saveSupplier(supplierCreateBody(draft.value))
      unsaved.markClean()
      emit('saved')
      await router.replace(`/suppliers/${created.id}`)
    } else if (row.value) {
      await saveSupplier(supplierPatchBody(draft.value, row.value.row_version), id.value)
      unsaved.markClean()
      emit('saved')
      await router.replace(`/suppliers/${id.value}`)
    }
  } catch (caught: unknown) {
    error.value = messageForError(caught)
    reportApiError(caught)
  } finally { busy.value = false }
}
async function saveUsage(): Promise<void> {
  if (!canEditUsage.value || busy.value || !row.value) return
  busy.value = true; error.value = ''; conflict.value = false
  try {
    await replaceSupplierCompanyUsage(id.value, supplierUsageBody(draft.value, row.value.row_version))
    emit('saved')
    await refreshSupplier()
  } catch (caught: unknown) {
    error.value = messageForError(caught)
    reportApiError(caught)
  } finally { busy.value = false }
}
onMounted(async () => {
  returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null
  await nextTick()
  closeButton.value?.focus()
  void loadProvinces()
  if (!isNew.value) await refreshSupplier()
})
</script>

<template>
  <AppDialogHost :open="true" size="xl" :mask-closable="true" @close="close">
    <section ref="dialog" class="ds-dialog-surface ds-detail-workspace supplier-detail-modal"
      :class="{ 'supplier-detail-modal--workspace': !isNew, 'ds-detail-workspace--fixed': !isNew }"
      role="dialog" aria-modal="true" :aria-label="isNew ? t('supplier.create') : row?.name || t('supplier.title')">
      <header class="modal-card__header">
        <div class="business-detail-header-main">
          <h2 class="modal-card-title">
            <NIcon :size="24" aria-hidden="true"><PeopleOutline /></NIcon>
            {{ isNew ? t('supplier.create') : row?.name || t('supplier.title') }}
            <span v-if="row?.supplier_code" class="ds-detail-heading-code supplier-heading-code">· {{ row.supplier_code }}</span>
          </h2>
          <span v-if="row" class="business-detail-header-status"
            :aria-label="`${t('supplier.status')}: ${statusLabel(row.status)}`">
            <TableStatus :status="row.status" />{{ statusLabel(row.status) }}
          </span>
        </div>
        <button ref="closeButton" type="button" class="modal-card-button-close"
          :aria-label="t('common.close')" :disabled="busy" @click="close" data-ds-icon="close" />
      </header>
      <div v-if="!isNew" class="ds-detail-tabs supplier-detail-tabs" role="tablist" :aria-label="t('supplier.title')">
        <button v-for="item in (['profile', 'usage', 'contacts', 'history'] as const)"
          v-show="item !== 'contacts' || hasBasePermission('supplier_contact', 'view')"
          :key="item" type="button" role="tab" :aria-selected="tab === item"
          @click="changeTab(item)">{{ t(`supplier.${item}`) }}</button>
      </div>
      <div ref="detailBody" class="ds-detail-body supplier-detail-modal__body">
        <p v-if="error" role="alert" class="supplier-error">{{ error }}</p>
        <div v-if="conflict" class="supplier-conflict">
          <span>{{ t('supplier.preserveDraft') }}</span>
          <button data-ds-icon="refresh" type="button" class="secondary-button" :disabled="busy" @click="reloadAfterConflict">
            {{ t('supplier.reload') }}
          </button>
        </div>
        <p v-if="loading" role="status">{{ t('supplier.loading') }}</p>
        <form v-if="(isNew || (row && tab === 'profile')) && !loading" id="supplier-profile-form"
          class="supplier-profile-form" @submit.prevent="saveProfile">
          <fieldset :disabled="!canSaveProfile || busy">
            <div class="ds-detail-form-grid supplier-form-grid">
              <label>
                <span>{{ t('supplier.code') }}</span>
                <input v-model="draft.supplier_code" :readonly="!isNew" maxlength="50"
                  :placeholder="isNew ? t('supplier.autoCode') : undefined" />
                <small v-if="isNew" class="ds-detail-help supplier-help">{{ t('supplier.autoCodeHint') }}</small>
              </label>
              <label>
                <span>{{ t('supplier.name') }} <span class="ds-detail-required supplier-required-star" aria-hidden="true">*</span></span>
                <input v-model="draft.name" maxlength="255" required :aria-invalid="profileErrors.name" />
                <small v-if="profileErrors.name" class="ds-detail-error supplier-field-error">{{ t('supplier.fieldRequired') }}</small>
              </label>
              <label><span>{{ t('supplier.type') }}</span>
                <select v-model="draft.supplier_type">
                  <option value="COMPANY">{{ t('supplier.companyType') }}</option>
                  <option value="INDIVIDUAL">{{ t('supplier.individualType') }}</option>
                </select>
              </label>
              <label><span>{{ t('supplier.paymentTerm') }}</span>
                <select v-model="draft.payment_term_code">
                  <option value="">{{ t('supplier.unspecified') }}</option>
                  <option v-for="term in ['IMMEDIATE', 'NET15', 'NET30', 'NET60']" :key="term" :value="term">{{ term }}</option>
                </select>
              </label>
              <label><span>{{ t('supplier.tax') }}</span><input v-model="draft.tax_code" maxlength="50" /></label>
              <label><span>{{ t('supplier.phone') }} <span class="ds-detail-required supplier-required-star" aria-hidden="true">*</span></span>
                <input v-model="draft.phone" type="tel" autocomplete="tel" maxlength="50" required :aria-invalid="profileErrors.phone" />
                <small v-if="profileErrors.phone" class="ds-detail-error supplier-field-error">{{ t('supplier.phoneInvalid') }}</small>
              </label>
              <label><span>{{ t('supplier.email') }}</span><input v-model="draft.email" type="email" maxlength="255" /></label>
              <label class="ds-detail-form-wide supplier-form-wide"><span>{{ t('supplier.address') }} <span class="ds-detail-required supplier-required-star" aria-hidden="true">*</span></span>
                <textarea v-model="draft.address" rows="2" required :aria-invalid="profileErrors.address" />
                <small v-if="profileErrors.address" class="ds-detail-error supplier-field-error">{{ t('supplier.fieldRequired') }}</small>
              </label>
              <label><span>{{ t('supplier.province') }} <span class="ds-detail-required supplier-required-star" aria-hidden="true">*</span></span>
                <SearchableSelect :model-value="draft.province_id" :options="provinceOptions"
                  :placeholder="t('supplier.choose')" :aria-label="t('supplier.province')"
                  :aria-invalid="profileErrors.province" @update:model-value="changeProvince" />
                <small v-if="profileErrors.province" class="ds-detail-error supplier-field-error">{{ t('supplier.fieldRequired') }}</small>
              </label>
              <label><span>{{ t('supplier.ward') }} <span class="ds-detail-required supplier-required-star" aria-hidden="true">*</span></span>
                <RemoteSearchableSelect :key="`supplier-ward-${draft.province_id || 'none'}`"
                  :model-value="draft.ward_id" :search="searchWards"
                  :disabled="!draft.province_id || !canSaveProfile || busy"
                  :selected-options="draft.ward_id && wardLabel ? [{ value: draft.ward_id, label: wardLabel }] : []"
                  :placeholder="t('supplier.choose')" :aria-label="t('supplier.ward')"
                  :aria-invalid="profileErrors.ward" @update:model-value="(value) => { draft.ward_id = value }" />
                <small v-if="profileErrors.ward" class="ds-detail-error supplier-field-error">{{ t('supplier.fieldRequired') }}</small>
              </label>
            </div>
          </fieldset>
          <section v-if="isNew" class="supplier-company-section">
            <h3>{{ t('supplier.usage') }}</h3>
            <p class="ds-detail-help supplier-help">{{ t('supplier.usageNotPermission') }}</p>
            <label class="supplier-checkbox">
              <input type="checkbox" :checked="draft.all_companies" :disabled="busy"
                @change="toggleAllCompanies(($event.target as HTMLInputElement).checked)" />
              <span>{{ t('supplier.allCompanies') }}</span>
            </label>
            <div v-if="!draft.all_companies" class="ds-detail-field supplier-company-picker">
              <span>{{ t('supplier.selectedCompanies') }}</span>
              <RemoteSearchableSelect v-if="hasBasePermission('organization', 'view')"
                :model-value="selectedCompany" :search="searchCompanies"
                :placeholder="t('supplier.addCompany')" :disabled="busy"
                @update:model-value="selectCompany" />
              <p v-else class="ds-detail-help supplier-help">{{ t('supplier.companyViewRequired') }}</p>
              <div class="supplier-chips"><span v-for="companyId in draft.company_ids" :key="companyId" class="supplier-chip">
                {{ names[companyId] ?? companyId }}
                <button type="button" :disabled="busy" class="ds-icon-control ds-icon-control--danger" data-ds-icon="delete" :aria-label="t('common.delete')" @click="removeCompany(companyId)"></button>
              </span></div>
            </div>
          </section>
        </form>
        <section v-else-if="row && tab === 'usage' && !loading" class="supplier-company-section">
          <h3>{{ t('supplier.usage') }}</h3>
          <p class="ds-detail-help supplier-help">{{ t('supplier.usageNotPermission') }}</p>
          <label class="supplier-checkbox">
            <input type="checkbox" :checked="draft.all_companies" :disabled="!canEditUsage || busy"
              @change="toggleAllCompanies(($event.target as HTMLInputElement).checked)" />
            <span>{{ t('supplier.allCompanies') }}</span>
          </label>
          <div v-if="!draft.all_companies" class="ds-detail-field supplier-company-picker">
            <span>{{ t('supplier.selectedCompanies') }}</span>
            <RemoteSearchableSelect v-if="canEditUsage && hasBasePermission('organization', 'view')"
              :model-value="selectedCompany" :search="searchCompanies" :placeholder="t('supplier.addCompany')"
              :disabled="busy" @update:model-value="selectCompany" />
            <p v-else-if="canEditUsage" class="ds-detail-help supplier-help">{{ t('supplier.companyViewRequired') }}</p>
            <div class="supplier-chips"><span v-for="companyId in draft.company_ids" :key="companyId" class="supplier-chip">
              {{ names[companyId] ?? companyId }}
              <button v-if="canEditUsage" type="button" :disabled="busy"
                class="ds-icon-control ds-icon-control--danger" data-ds-icon="delete"
                :aria-label="t('common.delete')" @click="removeCompany(companyId)"></button>
            </span></div>
          </div>
        </section>
        <section v-else-if="row && tab === 'contacts' && !loading && hasBasePermission('supplier_contact', 'view')"
          class="supplier-contacts">
          <ContactList owner-type="SUPPLIER" :owner-id="id" />
        </section>
        <section v-else-if="row && tab === 'history' && !loading" class="supplier-history">
          <BusinessHistoryPanel :supplier-id="id" />
        </section>
      </div>
      <footer class="modal-card__footer supplier-detail-footer">
        <span v-if="row" class="supplier-version">{{ t('supplier.version') }}: {{ row.row_version }}</span>
        <button data-ds-icon="close" type="button" class="secondary-button" :disabled="busy" @click="close">{{ t('common.close') }}</button>
        <button data-ds-icon="edit" v-if="row && !isEdit && canEdit" type="button" class="secondary-button"
          :disabled="busy || loading" @click="router.push(`/suppliers/${id}/edit`)">
          {{ t('common.edit') }}
        </button>
        <!-- Same footer workflow placement as CUS; execution remains Supplier-owned CAS. -->
        <SupplierStateAction v-if="row && !isEdit && canState" :supplier="row" variant="button"
          :busy="busy || loading" :before-open="beforeStateOpen"
          @changed="void afterStateChanged()" @conflict="onStateConflict" />
        <button data-ds-icon="save" v-if="(isNew || tab === 'profile') && canSaveProfile" type="submit" form="supplier-profile-form"
          class="primary-button" :disabled="busy || loading" @click="profileAttempted = true">
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
        <button data-ds-icon="save" v-else-if="tab === 'usage' && canEditUsage" type="button" class="primary-button"
          :disabled="busy || loading || (!draft.all_companies && draft.company_ids.length === 0)"
          @click="void saveUsage()">{{ busy ? t('common.saving') : t('common.save') }}</button>
      </footer>
    </section>
  </AppDialogHost>
</template>

<style scoped>
/* CUS detail workspace dimensions, header/tabs/footer and scroll behavior. */
.supplier-profile-form, .supplier-profile-form fieldset { margin: 0; min-width: 0; }
.supplier-profile-form fieldset { padding: 0; border: 0; }
.supplier-company-section {
  margin-top: 16px; padding: 14px; border: 1px solid var(--ds-border-subtle);
  border-radius: 6px; display: grid; align-content: start; gap: 12px;
}
.supplier-company-section h3 { margin: 0; color: var(--ds-text-primary); font-size: 16px; }
.supplier-checkbox { display: inline-flex; align-items: center; gap: 9px; color: var(--ds-text-secondary); font-size: 13px; }
.supplier-checkbox input { flex: 0 0 auto; width: 16px; height: 16px; }
.supplier-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.supplier-chip {
  display: inline-flex; align-items: center; gap: 7px; padding: 4px 8px;
  border: 1px solid var(--ds-border-subtle); border-radius: 12px; font-size: 12px;
}
.supplier-chip button { border: 0; background: transparent; color: var(--ds-danger); cursor: pointer; }
.supplier-detail-footer { align-items: center; flex-wrap: wrap; }
.supplier-version { margin-right: auto; padding-left: 16px; color: var(--ds-text-secondary); font-size: 12px; }
.supplier-conflict { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.supplier-error { color: var(--ds-danger); font-size: 12px; margin: 0 0 12px; }
.supplier-contacts { min-height: 140px; }
</style>
