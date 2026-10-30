<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import type { Contact, ContactCreate, Province, Ward } from '@/api/generated/types.gen'
import type { ContactUpdate } from '@/services/foundation'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import type { ContactOwnerType } from './contactOwnerPolicy'

const props = withDefaults(
  defineProps<{
    open: boolean
    ownerId: string
    ownerType: ContactOwnerType
    contact?: Contact | null
    provinces?: readonly Province[]
    wards?: readonly Ward[]
    busy?: boolean
  }>(),
  { contact: null, provinces: () => [], wards: () => [], busy: false },
)

const emit = defineEmits<{
  close: []
  save: [payload: ContactCreate | ContactUpdate]
  'province-change': [provinceId: string]
}>()

const { t } = useI18n()
const draft = ref({
  name: '',
  email: '',
  phone: '',
  mobile: '',
  address: '',
  description: '',
  province_id: '',
  ward_id: '',
})

const requiredFieldsFilled = computed(
  () =>
    draft.value.name.trim().length > 0 &&
    draft.value.phone.trim().length > 0 &&
    draft.value.address.trim().length > 0,
)

const { markClean, confirmDiscard } = useUnsavedChanges(
  draft,
  computed(() => props.open),
)

function requestClose(): void {
  if (props.busy || !confirmDiscard()) return
  markClean()
  emit('close')
}

const title = computed(() => (props.contact ? t('contact.edit') : t('contact.add')))
const provinceOptions = computed(() =>
  props.provinces.map((province) => ({
    value: String(province.id),
    label: `${province.code} - ${province.name}`,
  })),
)
const wardOptions = computed(() =>
  props.wards.map((ward) => ({
    value: String(ward.id),
    label: `${ward.code} - ${ward.name}`,
  })),
)

watch(
  () => [props.open, props.contact] as const,
  () => {
    const contact = props.contact
    draft.value = {
      name: contact?.name ?? '',
      email: contact?.email ?? '',
      phone: contact?.phone ?? '',
      mobile: contact?.mobile ?? '',
      address: contact?.address ?? '',
      description: contact?.description ?? '',
      province_id: contact?.province_id ?? '',
      ward_id: contact?.ward_id ?? '',
    }
    markClean()
  },
  { immediate: true },
)

function onProvinceChange(value: string): void {
  draft.value.province_id = value
  draft.value.ward_id = ''
  emit('province-change', value)
}

function save(): void {
  // HTML `required` is not sufficient for values containing only whitespace.
  // Keep this guard for Enter submissions and direct calls to save().
  if (props.busy || !requiredFieldsFilled.value) return

  const editablePayload: ContactUpdate = {
    name: draft.value.name.trim(),
    phone: draft.value.phone.trim(),
    address: draft.value.address.trim(),
    ...(draft.value.email ? { email: draft.value.email } : {}),
    ...(draft.value.mobile ? { mobile: draft.value.mobile } : {}),
    ...(draft.value.description ? { description: draft.value.description } : {}),
    ...(draft.value.province_id ? { province_id: draft.value.province_id } : {}),
    ...(draft.value.ward_id ? { ward_id: draft.value.ward_id } : {}),
  }
  if (props.contact) {
    // Contact ownership is immutable; the API derives it from the existing row.
    emit('save', editablePayload)
    return
  }
  emit('save', {
    contact_type: props.ownerType,
    contact_ref_id: props.ownerId,
    ...editablePayload,
  })
}
</script>

<template>
  <div v-if="props.open" class="modal-backdrop">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="title">
      <header class="modal-card__header">
        <div>
          <h2 class="modal-card-title">
            <NIcon :size="24">
              <PeopleOutline />
            </NIcon>
            {{ title }}
          </h2>
          <p>{{ t('contact.ownerHint') }}</p>
        </div>
        <button
          type="button"
          class="modal-card-button-close"
          :aria-label="t('common.close')"
          @click="requestClose"
        ></button>
      </header>
      <form id="contact-form" class="modal-card__body" @submit.prevent="save">
        <div class="form-grid">
          <label>
            <span>
              {{ t('contact.name') }} <span class="required-mark" aria-hidden="true">*</span>
            </span>
            <input v-model.trim="draft.name" type="text" autocomplete="name" required />
          </label>
          <label>
            <span>{{ t('contact.email') }}</span>
            <input v-model="draft.email" type="email" />
          </label>
          <label>
            <span>
              {{ t('contact.phone') }} <span class="required-mark" aria-hidden="true">*</span>
            </span>
            <input v-model.trim="draft.phone" type="tel" autocomplete="tel" required />
          </label>
          <label>
            <span>{{ t('contact.mobile') }}</span>
            <input v-model="draft.mobile" />
          </label>
          <label>
            <span>{{ t('contact.province') }}</span>
            <SearchableSelect
              :model-value="draft.province_id"
              :options="provinceOptions"
              :placeholder="t('contact.selectProvince')"
              :aria-label="t('contact.province')"
              @update:model-value="
                (value) => onProvinceChange(typeof value === 'string' ? value : '')
              "
            />
          </label>
          <label>
            <span>{{ t('contact.ward') }}</span>
            <SearchableSelect
              :model-value="draft.ward_id"
              :options="wardOptions"
              :placeholder="t('contact.selectWard')"
              :disabled="!draft.province_id"
              :aria-label="t('contact.ward')"
              @update:model-value="
                (value) => (draft.ward_id = typeof value === 'string' ? value : '')
              "
            />
          </label>
        </div>
        <label>
          <span>
            {{ t('contact.address') }} <span class="required-mark" aria-hidden="true">*</span>
          </span>
          <input v-model.trim="draft.address" type="text" autocomplete="street-address" required />
        </label>
        <label>
          <span>{{ t('contact.description') }}</span>
          <textarea v-model="draft.description" rows="3" />
        </label>
      </form>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="requestClose">
          <NIcon :size="20">
            <Refresh />
          </NIcon>
          {{ t('common.cancel') }}
        </button>
        <button
          type="submit"
          class="primary-button"
          form="contact-form"
          :disabled="props.busy || !requiredFieldsFilled"
        >
          <NIcon :size="20">
            <SaveOutline />
          </NIcon>
          {{ props.busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.modal-card {
  width: min(100%, 680px);
  max-height: calc(100vh - 40px);
  overflow: auto;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}

.modal-card__header p {
  margin: 4px 0 0;
  color: #8292a0;
  font-size: 12px;
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
.modal-card__body textarea {
  resize: vertical;
}
.required-mark {
  color: #b42318;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 600px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
