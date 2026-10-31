<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useBasePermission } from '@/composables/useBasePermission'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import RemoteSearchableSelect from '@/components/forms/RemoteSearchableSelect.vue'
import { geographicOption, singleLocationId, wardMatchesSelection } from '@/features/customer/customerLocationSelect'
import { ListPagination, TableDetailModal, TableRowActions, TableStatus } from '@/components/lists'
import BusinessWorkflowAction from '@/components/lists/BusinessWorkflowAction.vue'
import BusinessHistoryPanel from '@/features/history/components/BusinessHistoryPanel.vue'
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import type { TableDetailField } from '@/components/lists/TableDetailModal.vue'
import { NIcon } from 'naive-ui'
import { CheckmarkCircleOutline, EllipseOutline, PeopleOutline } from '@vicons/ionicons5'
import { normalizeApiError, reportApiError } from '@/services/errorBoundary'
import { buildCustomerPayload } from '@/features/customer/customerPayload'
import { validateCustomerProfile, type CustomerProfileErrors } from '@/features/customer/customerProfileValidation'
import { contactProvinceLabel, contactWardLabel } from '@/features/customer/contactGeography'
import { getProvinceById, getWardById } from '@/services/foundation'
import { newCustomerForm } from '@/features/customer/customerDefaults'
import {
  CUSTOMER_GROUPS, addCustomerInteraction, customerById, customerContacts,
  customerInteractions, listCompanies, listProvinces, searchCustomerWards,
  saveCustomer, saveCustomerContact,
  type Company, type CustomerContact, type CustomerForm, type Interaction, type Location,
} from '@/services/customer'

// Reuse the canonical Customer modal in authorized cross-module read views.
// The route remains authoritative for all existing /customers/* pages.
const props = defineProps<{ customerId?: string }>()
const emit = defineEmits<{ close: []; saved: [] }>()
const { t, te, locale } = useI18n()
const statusLabel = (code: string): string => {
  const key = `status.${code.toLowerCase()}`
  return te(key) ? t(key) : code
}
const route = useRoute()
const router = useRouter()
const { hasBasePermission } = useBasePermission()
const embedded = computed(() => props.customerId !== undefined)
const id = computed(() => props.customerId ?? String(route.params.id ?? ''))
const isNew = computed(() => !embedded.value && route.path.endsWith('/new'))
const editing = computed(() => !embedded.value && (isNew.value || route.path.endsWith('/edit')))
const busy = ref(false)
const loading = ref(false)
const error = ref('')
const groupLabels = computed(() => CUSTOMER_GROUPS)
const provinceOptions = computed(() => provinces.value.map(geographicOption))
const profileSelectedWardOptions = computed(() => {
  const id = String(form.value.ward_id ?? '')
  const label = profileWardLabels.value.get(id)
  return id && label ? [{ value: id, label }] : []
})
const contactSelectedWardOptions = computed(() => {
  const id = contact.value.ward_id
  const label = contactWardLabels.value.get(id)
  return id && label ? [{ value: id, label }] : []
})
const contactList = ref<CustomerContact[]>([])
const contactTotal = ref(0)
const detailContact = ref<CustomerContact | null>(null)
const detailOpen = ref(false)
const detailProvinceLabel = ref<string | null>(null)
const detailWardLabel = ref<string | null>(null)
const detailProvinceLoading = ref(false)
const detailWardLoading = ref(false)
let contactDetailRequest = 0
const editingContact = ref<string | null>(null)
const contactOpen = ref(false)
const contactBusy = ref(false)
const activityBusy = ref(false)
const provinces = ref<Location[]>([])
const profileWardLabels = ref<Map<string, string>>(new Map())
const contactWardLabels = ref<Map<string, string>>(new Map())
let profileWardRequest = 0
let contactWardRequest = 0
const companies = ref<Company[]>([])
const interactions = ref<Interaction[]>([])
const interactionsPage = ref(1)
const interactionsPageSize = ref(25)
const interactionsTotal = ref(0)
const companyId = ref('')
const activity = ref<'NOTE' | 'CALL' | 'EMAIL' | 'MEETING' | 'CORRECTION'>('NOTE')
const activityContent = ref('')
const correctsId = ref('')
const tab = ref<'profile' | 'contacts' | 'interactions' | 'sales' | 'payments' | 'shipping' | 'document' | 'history'>('profile')
const state = ref<string>('')
const allowedActions = ref<{ edit?: boolean; setstate?: boolean } | null>(null)
const dialog = ref<HTMLElement | null>(null)
const detailBody = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const contactDialog = ref<HTMLElement | null>(null)
const closeContactButton = ref<HTMLButtonElement | null>(null)
let returnFocusTo: HTMLElement | null = null
const form = ref<CustomerForm>(newCustomerForm())
const profileAttempted = ref(false)
const serverPhoneDuplicate = ref(false)
const profileErrors = computed<CustomerProfileErrors>(() => profileAttempted.value ? validateCustomerProfile(form.value) : {})
const contact = ref({ description: '', name: '', phone: '', mobile: '', email: '', address: '', province_id: '', ward_id: '' })
const canEdit = computed(() => hasBasePermission('customer', isNew.value ? 'create' : 'edit') &&
  (isNew.value || allowedActions.value?.edit === true))
const canToggle = computed(() => !isNew.value && hasBasePermission('customer', 'setstate') &&
  allowedActions.value?.setstate === true)
const profileDraft = useUnsavedChanges(form, editing)
const contactDraft = useUnsavedChanges(contact, contactOpen)

function closeDetail(): void {
  if (busy.value || contactBusy.value || !profileDraft.confirmDiscard()) return
  profileDraft.markClean()
  emit('close')
}
function closeContact(): void {
  if (contactBusy.value || !contactDraft.confirmDiscard()) return
  contactDraft.markClean()
  ++contactWardRequest // Discard late selected-ward lookups when the modal closes.
  contactOpen.value = false
}
onBeforeUnmount(() => { if (returnFocusTo?.isConnected) returnFocusTo.focus() })
const canContact = computed(() => hasBasePermission('customer_contact', 'view'))
const canAddContact = computed(() => hasBasePermission('customer_contact', 'create'))
const canActivity = computed(() => hasBasePermission('customer_interaction', 'view'))
const canAddActivity = computed(() => hasBasePermission('customer_interaction', 'create'))

const detailFields = computed<readonly TableDetailField[]>(() => {
  const item = detailContact.value
  if (!item) return []
  return [
    { key: 'description', label: t('customer.addressLabel'), value: item.description },
    { key: 'name', label: t('customer.recipient'), value: item.name },
    { key: 'email', label: t('customer.email'), value: item.email },
    { key: 'phone', label: t('customer.phone'), value: item.phone },
    { key: 'mobile', label: t('customer.mobile'), value: item.mobile },
    { key: 'address', label: t('customer.address'), value: item.address },
    { key: 'province', label: t('customer.province'),
      value: detailProvinceLoading.value ? t('customer.loading') : detailProvinceLabel.value },
    { key: 'ward', label: t('customer.ward'),
      value: detailWardLoading.value ? t('customer.loading') : detailWardLabel.value },
    { key: 'status', label: t('customer.status'), status: item.status },
    { key: 'shippingReadiness', label: t('customer.shipping'), value: contactEligible(item) ? t('customer.shippable') : t('customer.contactOnly') },
  ]
})

/** Use the same P1 Naive-UI search controls in both CUS forms. */
function updateProfileProvince(value: string | string[]): void {
  const provinceId = singleLocationId(value)
  if (provinceId === String(form.value.province_id ?? '')) return
  ++profileWardRequest
  form.value.province_id = provinceId || null
  form.value.ward_id = null
  profileWardLabels.value = new Map()
}
function updateContactProvince(value: string | string[]): void {
  const provinceId = singleLocationId(value)
  if (provinceId === contact.value.province_id) return
  ++contactWardRequest
  contact.value.province_id = provinceId
  contact.value.ward_id = ''
  contactWardLabels.value = new Map()
}
function updateProfileWard(value: string): void {
  form.value.ward_id = form.value.province_id ? value || null : null
}
function updateContactWard(value: string): void {
  contact.value.ward_id = contact.value.province_id ? value : ''
}
function rememberWard(
  rows: readonly Location[],
  provinceId: string,
  currentProvinceId: string,
  labels: typeof profileWardLabels,
): { value: string; label: string }[] {
  if (provinceId !== currentProvinceId) return []
  const options = rows.filter((row) => String(row.province_id ?? '') === provinceId).map(geographicOption)
  if (options.length) {
    const next = new Map(labels.value)
    for (const option of options) next.set(option.value, option.label)
    labels.value = next
  }
  return options
}
async function searchProfileWards(q: string, page: number): Promise<{ options: { value: string; label: string }[]; total: number }> {
  const provinceId = String(form.value.province_id ?? '')
  if (!provinceId) return { options: [], total: 0 }
  const result = await searchCustomerWards(provinceId, q, page)
  if (provinceId !== String(form.value.province_id ?? '')) return { options: [], total: 0 }
  const options = rememberWard(result.data, provinceId, String(form.value.province_id ?? ''), profileWardLabels)
  return { options, total: result.meta.total }
}
async function searchContactWards(q: string, page: number): Promise<{ options: { value: string; label: string }[]; total: number }> {
  const provinceId = contact.value.province_id
  if (!provinceId) return { options: [], total: 0 }
  const result = await searchCustomerWards(provinceId, q, page)
  if (provinceId !== contact.value.province_id) return { options: [], total: 0 }
  const options = rememberWard(result.data, provinceId, contact.value.province_id, contactWardLabels)
  return { options, total: result.meta.total }
}
/** Hydrate a saved Ward's label even when it is not on the first search page. */
async function hydrateProfileWard(): Promise<void> {
  const id = String(form.value.ward_id ?? '')
  const provinceId = String(form.value.province_id ?? '')
  const request = ++profileWardRequest
  if (!id || !provinceId) return
  try {
    const row = await getWardById(id)
    if (request !== profileWardRequest || !wardMatchesSelection(row, provinceId, id) ||
        String(form.value.ward_id ?? '') !== id || String(form.value.province_id ?? '') !== provinceId) return
    profileWardLabels.value = new Map([...profileWardLabels.value, [id, geographicOption(row).label]])
  } catch (caught) { if (request === profileWardRequest) reportApiError(caught) }
}
async function hydrateContactWard(): Promise<void> {
  const id = contact.value.ward_id
  const provinceId = contact.value.province_id
  const request = ++contactWardRequest
  if (!id || !provinceId) return
  try {
    const row = await getWardById(id)
    if (request !== contactWardRequest || !contactOpen.value || !wardMatchesSelection(row, provinceId, id) ||
        contact.value.ward_id !== id || contact.value.province_id !== provinceId) return
    contactWardLabels.value = new Map([...contactWardLabels.value, [id, geographicOption(row).label]])
  } catch (caught) { if (request === contactWardRequest) reportApiError(caught) }
}
function stringOrNull(s: string): string | null { return s.trim() ? s.trim() : null }
function displayInteractionDate(value: string): string {
  if (!value) return '—'
  const instant = new Date(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`)
  if (Number.isNaN(instant.getTime())) return value
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short' }).format(instant)
}

function contactEligible(c: CustomerContact): boolean {
  return state.value === 'ACTIVE' && c.status === 'ACTIVE' && !!c.name?.trim() && !!(c.mobile?.trim() || c.phone?.trim()) &&
    !!c.address?.trim() && !!c.province_id && !!c.ward_id
}
async function refreshCustomer(): Promise<void> {
  if (isNew.value) return
  loading.value = true
  try {
    const row = await customerById(id.value)
    state.value = row.status
    allowedActions.value = row.allowed_actions ?? null
    profileAttempted.value = false
    serverPhoneDuplicate.value = false
    form.value = {
      customer_code: row.customer_code, name: row.name, customer_type: row.customer_type,
      customer_group_code: row.customer_group_code, phone: row.phone, email: row.email,
      tax_code: row.tax_code, address: row.address,
      province_id: row.province_id, ward_id: row.ward_id,
      primary_contact_id: row.primary_contact_id,
      all_companies: Boolean(Number(row.all_companies)), company_ids: row.company_ids ?? [],
    }
    profileWardLabels.value = new Map()
    await hydrateProfileWard()
    if (canContact.value) await refreshContacts()
    profileDraft.markClean()
    if (companyId.value && canActivity.value) await refreshActivities()
  } catch (caught) {
    error.value = t('customer.loadError')
    reportApiError(caught)
  } finally { loading.value = false }
}
async function refreshContacts(): Promise<void> {
  if (!id.value || !canContact.value) return
  try {
    const response = await customerContacts(id.value)
    contactList.value = response.data
    contactTotal.value = response.meta.total
  } catch (caught) { reportApiError(caught) }
}
async function refreshActivities(): Promise<void> {
  if (!id.value || !companyId.value || !canActivity.value) return
  try {
    const response = await customerInteractions(id.value, companyId.value, interactionsPage.value, interactionsPageSize.value)
    interactions.value = response.data
    interactionsTotal.value = response.meta.total
  } catch (caught) { reportApiError(caught); interactions.value = [] }
}
async function save(): Promise<void> {
  if (!canEdit.value || busy.value) return
  profileAttempted.value = true
  if (Object.keys(validateCustomerProfile(form.value)).length > 0) {
    error.value = t('customer.requiredFields')
    return
  }
  busy.value = true; error.value = ''
  try {
    const body = buildCustomerPayload(form.value, isNew.value)
    const saved = await saveCustomer(body, isNew.value ? undefined : id.value)
    profileDraft.markClean()
    emit('saved')
    await router.replace(`/customers/${saved.id}`)
  } catch (caught) {
    const code = normalizeApiError(caught)?.code
    serverPhoneDuplicate.value = code === 'CUSTOMER_PHONE_DUPLICATE'
    error.value = code === 'CUSTOMER_PHONE_DUPLICATE'
      ? t('customer.phoneDuplicate')
      : code === 'CUSTOMER_PHONE_INVALID'
        ? t('customer.phoneInvalid')
        : code === 'CUSTOMER_CODE_REQUIRED'
      ? t('customer.numberingNotConfigured')
      : code === 'NUM_IDENTIFIER_MANUAL_NOT_ALLOWED'
        ? t('customer.manualCodeNotAllowed')
        : code === 'AUTH_POLICY_DENIED' && isNew.value && form.value.customer_type === 'INDIVIDUAL'
          ? t('customer.initialMainContactPermission')
          : t('customer.saveError')
    reportApiError(caught)
  }
  finally { busy.value = false }
}
async function afterCustomerWorkflowChange(): Promise<void> {
  await refreshCustomer()
  emit('saved')
}
function closeContactDetail(): void {
  detailOpen.value = false
  contactDetailRequest++ // Ignore late location responses after closing the dialog.
}
function openContactDetail(item: CustomerContact): void {
  detailContact.value = item
  detailOpen.value = true
  const request = ++contactDetailRequest
  const matchingProvince = provinces.value.find((p) => String(p.id) === String(item.province_id))
  detailProvinceLabel.value = contactProvinceLabel(item, matchingProvince)
  detailWardLabel.value = contactWardLabel(item)
  detailProvinceLoading.value = !!item.province_id && !detailProvinceLabel.value
  detailWardLoading.value = !!item.ward_id && !detailWardLabel.value

  // Point lookups only when the authorized Contact lacks a usable display label.
  // A province may be outside the initial lookup page; never display its numeric ID.
  if (detailProvinceLoading.value && item.province_id) {
    void getProvinceById(String(item.province_id)).then((province) => {
      if (request === contactDetailRequest) detailProvinceLabel.value = contactProvinceLabel(item, province)
    }).catch(reportApiError).finally(() => {
      if (request === contactDetailRequest) detailProvinceLoading.value = false
    })
  }
  // Do not load page_size=200 wards and assume the requested ward was included.
  if (detailWardLoading.value && item.ward_id) {
    void getWardById(String(item.ward_id)).then((ward) => {
      if (request === contactDetailRequest) detailWardLabel.value = contactWardLabel(item, ward)
    }).catch(reportApiError).finally(() => {
      if (request === contactDetailRequest) detailWardLoading.value = false
    })
  }
}
async function afterContactStateChanged(): Promise<void> {
  await refreshContacts()
}
function startContact(c?: CustomerContact): void {
  ++contactWardRequest
  contactWardLabels.value = new Map()
  editingContact.value = c?.id ?? null
  contact.value = {
    description: c?.description ?? '', name: c?.name ?? '', phone: c?.phone ?? '',
    mobile: c?.mobile ?? '', email: c?.email ?? '', address: c?.address ?? '',
    province_id: c?.province_id ? String(c.province_id) : '',
    ward_id: c?.ward_id ? String(c.ward_id) : '',
  }
  contactOpen.value = true
  contactDraft.markClean()
  void nextTick(() => closeContactButton.value?.focus())
  // A persisted Contact might be outside the first page of Ward results.
  if (contact.value.ward_id && c?.ward_name?.trim()) {
    contactWardLabels.value = new Map([[contact.value.ward_id, c.ward_name.trim()]])
  }
  void hydrateContactWard()
}
async function saveContact(): Promise<void> {
  if (!contact.value.name.trim() || contactBusy.value) return
  contactBusy.value = true
  try {
    await saveCustomerContact(id.value, {
      ...contact.value, description: stringOrNull(contact.value.description),
      name: contact.value.name.trim(), phone: stringOrNull(contact.value.phone),
      mobile: stringOrNull(contact.value.mobile), email: stringOrNull(contact.value.email),
      address: stringOrNull(contact.value.address),
      province_id: stringOrNull(contact.value.province_id),
      ward_id: stringOrNull(contact.value.ward_id),
    }, editingContact.value ?? undefined)
    contactDraft.markClean()
    contactOpen.value = false
    await refreshContacts()
  } catch (caught) { reportApiError(caught) }
  finally { contactBusy.value = false }
}
async function addActivity(): Promise<void> {
  if (!id.value || !companyId.value || activityBusy.value || !activityContent.value.trim()) return
  activityBusy.value = true
  try {
    await addCustomerInteraction(id.value, {
      company_organization_id: companyId.value,
      activity_type: activity.value, content: activityContent.value.trim(),
      ...(activity.value === 'CORRECTION' ? { corrects_interaction_id: correctsId.value } : {}),
    })
    activityContent.value = ''; correctsId.value = ''; interactionsPage.value = 1
    await refreshActivities()
  } catch (caught) { reportApiError(caught) }
  finally { activityBusy.value = false }
}
function switchTab(next: typeof tab.value): void {
  tab.value = next
  // The same scrollport hosts all tabs. Never inherit the scroll position of
  // a long History/Contacts tab when switching to another workspace tab.
  if (detailBody.value) detailBody.value.scrollTop = 0
  if (next === 'contacts') void refreshContacts()
  if (next === 'interactions') void refreshActivities()
}
watch(companyId, () => { interactionsPage.value = 1; void refreshActivities() })
watch(() => form.value.phone, () => { serverPhoneDuplicate.value = false })
watch(id, () => { tab.value = 'profile'; void refreshCustomer() })
onMounted(async () => {
  returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null
  await nextTick()
  closeButton.value?.focus()
  try { companies.value = await listCompanies(); companyId.value = companies.value[0]?.id ?? '' }
  catch (caught) { reportApiError(caught) }
  try { provinces.value = await listProvinces() } catch (caught) { reportApiError(caught) }
  await refreshCustomer()
})
</script>

<template>
  <AppDialogHost :open="true" size="xl" :close-on-esc="!contactOpen && !detailOpen" :mask-closable="true" @close="closeDetail">
    <section ref="dialog" class="ds-dialog-surface ds-detail-workspace customer-detail-modal" :class="{ 'customer-detail-modal--workspace': !isNew, 'ds-detail-workspace--fixed': !isNew }" role="dialog" aria-modal="true"
      :aria-label="isNew ? t('customer.create') : form.name || t('customer.title')">
      <header class="modal-card__header">
        <div class="business-detail-header-main">
          <h2 class="modal-card-title">
            <NIcon :size="24" aria-hidden="true"><PeopleOutline /></NIcon>
            {{ isNew ? t('customer.create') : form.name || t('customer.title') }}
            <span v-if="!isNew && form.customer_code" class="ds-detail-heading-code customer-heading-code">· {{ form.customer_code }}</span>
          </h2>
          <span v-if="!isNew && state" class="business-detail-header-status"
            :aria-label="`${t('customer.status')}: ${statusLabel(state)}`">
            <TableStatus :status="state" />{{ statusLabel(state) }}
          </span>
        </div>
        <button ref="closeButton" type="button" class="modal-card-button-close"
          :aria-label="t('common.close')" @click="closeDetail" data-ds-icon="close" />
      </header>
      <div v-if="!isNew" class="ds-detail-tabs customer-detail-tabs" role="tablist" :aria-label="t('customer.title')">
        <button v-for="item in (['profile', 'contacts', 'interactions', 'sales', 'payments', 'shipping', 'document', 'history'] as const)"
          :key="item" type="button" role="tab" :aria-selected="tab === item"
          @click="switchTab(item)">{{ t(`customer.${item}`) }}</button>
      </div>
      <div ref="detailBody" class="ds-detail-body customer-detail-modal__body">
        <p v-if="error" class="inline-error" role="alert">{{ error }}</p>
        <p v-if="loading" role="status">{{ t('customer.loading') }}</p>
        <form v-if="tab === 'profile' && !loading" id="customer-profile-form" class="customer-profile-form"
          @submit.prevent="save">
          <div class="ds-detail-form-grid customer-form-grid">
            <label><span>{{ t('customer.code') }}</span>
              <input v-model="form.customer_code" :readonly="!isNew" maxlength="50"
                :placeholder="isNew ? t('customer.autoCodePlaceholder') : undefined" />
              <small v-if="isNew" class="ds-detail-help customer-help">{{ t('customer.autoCodeHint') }}</small>
            </label>
            <label><span>{{ t('customer.name') }} <span class="ds-detail-required customer-required-star" aria-hidden="true">*</span></span>
              <input v-model="form.name" :readonly="!editing" required maxlength="255"
                :aria-invalid="!!profileErrors.name" />
              <small v-if="profileErrors.name" class="ds-detail-error customer-field-error">{{ t('customer.fieldRequired') }}</small>
            </label>
            <label><span>{{ t('customer.type') }}</span>
              <select v-model="form.customer_type" :disabled="!editing">
                <option value="ORGANIZATION">{{ t('customer.organizationType') }}</option>
                <option value="INDIVIDUAL">{{ t('customer.individualType') }}</option>
              </select>
              <small v-if="isNew && form.customer_type === 'INDIVIDUAL'" class="ds-detail-help customer-help">
                {{ t('customer.initialMainContact') }}
              </small>
            </label>
            <label><span>{{ t('customer.group') }}</span>
              <select v-model="form.customer_group_code" :disabled="!editing">
                <option :value="null">—</option>
                <option v-for="group in groupLabels" :key="group.value" :value="group.value">
                  {{ group.value }} — {{ t(`customer.groupCodes.${group.value}`) }}
                </option>
              </select></label>
            <label><span>{{ t('customer.tax') }}</span><input v-model="form.tax_code" :readonly="!editing" /></label>
            <label><span>{{ t('customer.phone') }} <span class="ds-detail-required customer-required-star" aria-hidden="true">*</span></span>
              <input v-model="form.phone" type="tel" autocomplete="tel" :readonly="!editing"
                :required="editing" maxlength="50"
                :aria-invalid="!!profileErrors.phone || serverPhoneDuplicate" />
              <small v-if="serverPhoneDuplicate" class="ds-detail-error customer-field-error">{{ t('customer.phoneDuplicate') }}</small>
              <small v-else-if="profileErrors.phone" class="ds-detail-error customer-field-error">
                {{ t(profileErrors.phone === 'invalid' ? 'customer.phoneInvalid' : 'customer.fieldRequired') }}
              </small>
            </label>
            <label><span>{{ t('customer.email') }}</span><input v-model="form.email" :readonly="!editing" type="email" /></label>
            <label class="ds-detail-form-wide customer-form-wide"><span>{{ t('customer.address') }} <span class="ds-detail-required customer-required-star" aria-hidden="true">*</span></span>
              <textarea v-model="form.address" :readonly="!editing" :required="editing" rows="2"
                :aria-invalid="!!profileErrors.address" />
              <small v-if="profileErrors.address" class="ds-detail-error customer-field-error">{{ t('customer.fieldRequired') }}</small>
            </label>
            <label><span>{{ t('customer.province') }} <span class="ds-detail-required customer-required-star" aria-hidden="true">*</span></span>
              <SearchableSelect :model-value="String(form.province_id ?? '')" :options="provinceOptions"
                :placeholder="t('contact.selectProvince')" :aria-label="t('customer.province')"
                :aria-required="editing" :aria-invalid="!!profileErrors.province_id"
                :disabled="!editing" @update:model-value="updateProfileProvince" />
              <small v-if="profileErrors.province_id" class="ds-detail-error customer-field-error">{{ t('customer.fieldRequired') }}</small>
            </label>
            <label><span>{{ t('customer.ward') }} <span class="ds-detail-required customer-required-star" aria-hidden="true">*</span></span>
              <RemoteSearchableSelect :key="`customer-ward-${form.province_id ?? 'none'}`"
                :model-value="String(form.ward_id ?? '')" :selected-options="profileSelectedWardOptions"
                :search="searchProfileWards" :placeholder="t('contact.selectWard')"
                :aria-label="t('customer.ward')" :aria-required="editing"
                :aria-invalid="!!profileErrors.ward_id" :disabled="!editing || !form.province_id"
                @update:model-value="updateProfileWard" />
              <small v-if="profileErrors.ward_id" class="ds-detail-error customer-field-error">{{ t('customer.fieldRequired') }}</small>
            </label>
            <label class="customer-form-wide customer-checkbox">
              <input v-model="form.all_companies" type="checkbox" :disabled="!editing" />
              <span>{{ t('customer.allCompanies') }}</span>
            </label>
            <fieldset v-if="!form.all_companies" class="customer-form-wide customer-company-list">
              <legend>{{ t('customer.selectedCompanies') }}</legend>
              <div v-if="companies.length" class="customer-company-options">
                <label v-for="company in companies" :key="company.id" class="customer-checkbox">
                  <input v-model="form.company_ids" type="checkbox" :value="String(company.id)" :disabled="!editing" />
                  <span>{{ company.code }} — {{ company.name }}</span>
                </label>
              </div>
              <p v-else class="inline-error">{{ t('customer.noCompanies') }}</p>
            </fieldset>
            <label v-if="contactList.length && editing"><span>{{ t('customer.primaryContact') }}</span>
              <select v-model="form.primary_contact_id">
                <option :value="null">—</option>
                <option v-for="item in contactList" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </label>
          </div>
        </form>
        <section v-else-if="tab === 'contacts'" class="customer-tab-panel">
          <header class="customer-tab-heading">
            <h3>{{ t('customer.contacts') }} ({{ contactTotal }})</h3>
            <button data-ds-icon="add" v-if="canAddContact" type="button" class="primary-button" @click="startContact()">
              {{ t('customer.addContact') }}
            </button>
          </header>
          <div class="customer-table-scroll">
            <table class="data-table customer-data-table customer-contact-table">
              <thead><tr>
                <th>{{ t('customer.recipient') }}</th><th>{{ t('customer.email') }}</th>
                <th>{{ t('customer.phone') }}</th><th>{{ t('customer.mobile') }}</th>
                <th>{{ t('customer.status') }}</th><th>{{ t('people.actions') }}</th>
              </tr></thead>
              <tbody>
                <tr v-for="item in contactList" :key="item.id">
                  <td>
                    <div class="customer-contact-name">{{ item.name || '—' }}</div>
                    <div v-if="item.description" class="customer-contact-meta">{{ t('customer.addressLabel') }}: {{ item.description }}</div>
                    <div v-if="item.address" class="customer-contact-meta">{{ item.address }}</div>
                    <div class="customer-contact-tags">
                      <span class="status-pill" :class="{ 'status-pill--off': !contactEligible(item) }">
                        {{ contactEligible(item) ? t('customer.shippable') : t('customer.contactOnly') }}
                      </span>
                    </div>
                  </td>
                  <td>{{ item.email || '—' }}</td>
                  <td>{{ item.phone || '—' }}</td>
                  <td>{{ item.mobile || '—' }}</td>
                  <td>
                    <span class="customer-state-indicator" :class="{ 'customer-state-indicator--off': item.status !== 'ACTIVE' }"
                      :title="t(`status.${String(item.status).toLowerCase()}`)"
                      :aria-label="t(`status.${String(item.status).toLowerCase()}`)">
                      <NIcon :size="22" aria-hidden="true">
                        <CheckmarkCircleOutline v-if="item.status === 'ACTIVE'" />
                        <EllipseOutline v-else />
                      </NIcon>
                    </span>
                  </td>
                  <td class="table-actions">
                    <TableRowActions authorization-mode="effective" :view-scope-verified="true"
                      :allowed-actions="rowAllowedActions(item)"
                      :active="item.status === 'ACTIVE'"
                      :state-target-id="String(item.id)"
                      state-resource="customer_contact"
                      :busy="contactBusy || loading"
                      :show-view="true"
                      :show-edit="true"
                      :show-toggle="true"
                      view-permission="customer_contact.view"
                      edit-permission="customer_contact.edit"
                      toggle-permission="customer_contact.setstate"
                      :edit-label="t('customer.edit')"
                      :view-label="t('common.view')"
                      @view="openContactDetail(item)"
                      @edit="startContact(item)"
                      @state-changed="void afterContactStateChanged()" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="!contactList.length" class="customer-empty">{{ t('customer.empty') }}</p>
          <p v-if="contactTotal > contactList.length" class="ds-detail-help customer-help">{{ contactList.length }} / {{ contactTotal }}</p>
        </section>
        <section v-else-if="tab === 'history' && !isNew" class="customer-tab-panel">
          <BusinessHistoryPanel :customer-id="id" />
        </section>
        <section v-else-if="tab === 'interactions'" class="customer-tab-panel">
          <header class="customer-tab-heading"><h3>{{ t('customer.interactions') }}</h3></header>
          <label class="ds-detail-field customer-company-picker"><span>{{ t('customer.company') }}</span>
            <select v-model="companyId">
              <option value="">—</option>
              <option v-for="company in companies" :key="company.id" :value="String(company.id)">{{ company.name }}</option>
            </select>
          </label>
          <form v-if="companyId && canAddActivity" class="customer-activity-form" @submit.prevent="addActivity">
            <div class="ds-detail-form-grid customer-form-grid">
              <label><span>{{ t('customer.activityType') }}</span>
                <select v-model="activity">
                  <option v-for="kind in (['NOTE','CALL','EMAIL','MEETING','CORRECTION'] as const)" :key="kind" :value="kind">{{ kind }}</option>
                </select>
              </label>
              <label v-if="activity === 'CORRECTION'"><span>{{ t('customer.corrects') }}</span>
                <input v-model="correctsId" required inputmode="numeric" pattern="[1-9][0-9]*" /></label>
              <label class="ds-detail-form-wide customer-form-wide"><span>{{ t('customer.content') }}</span>
                <textarea v-model="activityContent" required rows="3" maxlength="5000" /></label>
            </div>
            <div class="customer-activity-actions"><button data-ds-icon="send" type="submit" class="primary-button"
              :disabled="activityBusy || !activityContent.trim()">{{ t('customer.addInteraction') }}</button></div>
          </form>
          <div v-for="event in interactions" :key="event.id" class="customer-activity-item">
            <strong>{{ event.activity_type }}</strong><span class="ds-detail-help customer-help">{{ displayInteractionDate(event.happened_at) }} · #{{ event.id }}</span>
            <p>{{ event.content }}</p>
            <small v-if="event.corrects_interaction_id" class="ds-detail-help customer-help">{{ t('customer.corrects') }}: #{{ event.corrects_interaction_id }}</small>
          </div>
          <p v-if="companyId && !interactions.length" class="customer-empty">{{ t('customer.noInteractions') }}</p>
          <ListPagination v-if="companyId" :page="interactionsPage" :page-size="interactionsPageSize" :total="interactionsTotal"
            @update:page="(page) => { interactionsPage = page; void refreshActivities() }"
            @update:page-size="(size) => { interactionsPageSize = size; interactionsPage = 1; void refreshActivities() }" />
        </section>
        <section v-else-if="!loading" class="customer-tab-panel">
          <h3>{{ t(`customer.${tab}`) }}</h3>
          <p class="ds-detail-help customer-help">{{ t('customer.pending') }} (SAL / INV / FIN / DOC)</p>
        </section>
      </div>
      <footer class="modal-card__footer">
        <button data-ds-icon="close" type="button" class="secondary-button" @click="closeDetail">{{ t('common.close') }}</button>
        <button data-ds-icon="edit" v-if="!editing && !isNew && canEdit" type="button" class="secondary-button"
          :disabled="busy || loading" @click="router.push(`/customers/${id}/edit`)">{{ t('common.edit') }}</button>
        <BusinessWorkflowAction v-if="!editing && canToggle" resource="customer" :target-id="id"
          :busy="busy || loading" variant="button" @changed="void afterCustomerWorkflowChange()" />
        <button data-ds-icon="save" v-if="tab === 'profile' && editing && canEdit" type="submit" class="primary-button"
          form="customer-profile-form" :disabled="busy || loading || (!form.all_companies && !form.company_ids.length)">
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </AppDialogHost>
  <TableDetailModal
    :open="detailOpen"
    :title="t('common.view') + ' - ' + t('customer.contacts')"
    :fields="detailFields"
    @close="closeContactDetail"
  />
  <AppDialogHost :open="contactOpen" size="md" :mask-closable="true" @close="closeContact">
    <section v-if="contactOpen" ref="contactDialog" class="ds-dialog-surface customer-contact-modal" role="dialog" aria-modal="true" :aria-label="t('customer.recipient')">
      <header class="modal-card__header">
        <h2 class="modal-card-title">{{ t('customer.recipient') }}</h2>
        <button ref="closeContactButton" type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="closeContact" data-ds-icon="close" />
      </header>
      <form id="customer-contact-form" class="customer-contact-form" @submit.prevent="saveContact">
        <div class="ds-detail-form-grid customer-form-grid">
          <label><span>{{ t('customer.recipient') }} *</span><input v-model="contact.name" required /></label>
          <label><span>{{ t('customer.addressLabel') }}</span><input v-model="contact.description" /></label>
          <label><span>{{ t('customer.phone') }}</span><input v-model="contact.phone" /></label>
          <label><span>{{ t('customer.mobile') }}</span><input v-model="contact.mobile" /></label>
          <label><span>{{ t('customer.email') }}</span><input v-model="contact.email" type="email" /></label>
          <label class="ds-detail-form-wide customer-form-wide"><span>{{ t('customer.address') }}</span><textarea v-model="contact.address" rows="2" /></label>
          <label><span>{{ t('customer.province') }}</span>
            <SearchableSelect :model-value="contact.province_id" :options="provinceOptions"
              :placeholder="t('contact.selectProvince')" :aria-label="t('customer.province')"
              @update:model-value="updateContactProvince" /></label>
          <label><span>{{ t('customer.ward') }}</span>
            <RemoteSearchableSelect :key="`contact-ward-${contact.province_id || 'none'}`"
              :model-value="contact.ward_id" :selected-options="contactSelectedWardOptions"
              :search="searchContactWards" :placeholder="t('contact.selectWard')"
              :aria-label="t('customer.ward')" :disabled="!contact.province_id"
              @update:model-value="updateContactWard" /></label>
        </div>
      </form>
      <footer class="modal-card__footer">
        <button data-ds-icon="cancel" type="button" class="secondary-button" @click="closeContact">{{ t('common.cancel') }}</button>
        <button data-ds-icon="save" type="submit" form="customer-contact-form" class="primary-button" :disabled="contactBusy || !contact.name.trim()">
          {{ contactBusy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </AppDialogHost>
</template>

<style scoped>
/* Detail workspace height stays stable across all tabs; the new-record form
   retains its existing natural height. Only the shared body scrolls. */
.customer-profile-form, .customer-contact-form { margin: 0; }
.customer-checkbox { display: flex !important; align-items: center; gap: 9px; font-weight: 500 !important; }
.customer-checkbox input { flex: 0 0 auto; width: 16px; height: 16px; }
.customer-company-list { border: 1px solid var(--ds-border-subtle); border-radius: 6px; padding: 12px; }
.customer-company-list legend { padding: 0 6px; font-size: 12px; font-weight: 700; color: var(--ds-text-secondary); }
.customer-company-options { display: flex; flex-wrap: wrap; gap: 12px 20px; }
.customer-tab-panel { display: grid; gap: 16px; }
.customer-tab-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.customer-tab-heading h3, .customer-tab-panel > h3 { margin: 0; color: var(--ds-text-primary); font-size: 16px; }
.customer-table-scroll { overflow-x: auto; }
.customer-data-table { width: 100%; min-width: 700px; }
.customer-data-table th, .customer-data-table td { padding: 10px; }
.customer-contact-table td { vertical-align: top; }
.customer-contact-name { font-weight: 600; color: var(--ds-text-primary); }
.customer-contact-meta { margin-top: 3px; color: var(--ds-text-secondary); font-size: 12px; }
.customer-contact-tags { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
.customer-state-indicator { display: inline-flex; align-items: center; justify-content: center; color: var(--ds-success); }
.customer-state-indicator--off { color: var(--ds-text-muted); }
.customer-empty { padding: 20px; text-align: center; color: var(--ds-text-secondary); }
.customer-help { color: var(--ds-text-secondary); font-size: 12px; }
.customer-activity-form { padding: 16px; border: 1px solid var(--ds-border-subtle); border-radius: 6px; }
.customer-activity-actions { display: flex; justify-content: flex-end; margin-top: 12px; }
.customer-activity-item { display: grid; gap: 4px; border-bottom: 1px solid var(--ds-border-subtle); padding: 12px 2px; }
.customer-activity-item p { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
.customer-contact-form { padding: 18px 20px 24px; overflow-y: auto; }
.inline-error { color: var(--ds-danger); font-size: 12px; }
</style>
