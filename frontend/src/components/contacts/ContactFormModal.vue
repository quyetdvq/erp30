<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import type { Contact, ContactCreate, Province, Ward } from '@/api/generated/types.gen'

const props = withDefaults(
  defineProps<{
    open: boolean
    ownerId: string
    ownerType: 'EMPLOYEE' | 'ORGANIZATION'
    contact?: Contact | null
    provinces?: readonly Province[]
    wards?: readonly Ward[]
    busy?: boolean
  }>(),
  { contact: null, provinces: () => [], wards: () => [], busy: false },
)

const emit = defineEmits<{
  close: []
  save: [payload: ContactCreate]
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
  status: 'ACTIVE' as 'ACTIVE' | 'INACTIVE' | 'CLOSED',
})

const title = computed(() => (props.contact ? t('contact.edit') : t('contact.add')))

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
      status: (contact?.status as 'ACTIVE' | 'INACTIVE' | 'CLOSED' | undefined) ?? 'ACTIVE',
    }
  },
  { immediate: true },
)

function onProvinceChange(value: string): void {
  draft.value.province_id = value
  draft.value.ward_id = ''
  emit('province-change', value)
}

function save(): void {
  const payload: ContactCreate = {
    contact_type: props.ownerType,
    contact_ref_id: props.ownerId,
    status: draft.value.status,
    ...(draft.value.name ? { name: draft.value.name } : {}),
    ...(draft.value.email ? { email: draft.value.email } : {}),
    ...(draft.value.phone ? { phone: draft.value.phone } : {}),
    ...(draft.value.mobile ? { mobile: draft.value.mobile } : {}),
    ...(draft.value.address ? { address: draft.value.address } : {}),
    ...(draft.value.description ? { description: draft.value.description } : {}),
    ...(draft.value.province_id ? { province_id: draft.value.province_id } : {}),
    ...(draft.value.ward_id ? { ward_id: draft.value.ward_id } : {}),
  }
  emit('save', payload)
}
</script>

<template>
  <div v-if="props.open" class="modal-backdrop" @click.self="emit('close')">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="title">
      <header class="modal-card__header">
        <div>
          <h2>{{ title }}</h2>
          <p>{{ t('contact.ownerHint') }}</p>
        </div>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="save">
        <div class="form-grid">
          <label
            ><span>{{ t('contact.name') }}</span
            ><input v-model="draft.name"
          /></label>
          <label
            ><span>{{ t('contact.email') }}</span
            ><input v-model="draft.email" type="email"
          /></label>
          <label
            ><span>{{ t('contact.phone') }}</span
            ><input v-model="draft.phone"
          /></label>
          <label
            ><span>{{ t('contact.mobile') }}</span
            ><input v-model="draft.mobile"
          /></label>
          <label
            ><span>{{ t('contact.province') }}</span>
            <select
              :value="draft.province_id"
              @change="onProvinceChange(($event.target as HTMLSelectElement).value)"
            >
              <option value="">{{ t('contact.selectProvince') }}</option>
              <option v-for="province in props.provinces" :key="province.id" :value="province.id">
                {{ province.code }} - {{ province.name }}
              </option>
            </select>
          </label>
          <label
            ><span>{{ t('contact.ward') }}</span>
            <select v-model="draft.ward_id" :disabled="!draft.province_id">
              <option value="">{{ t('contact.selectWard') }}</option>
              <option v-for="ward in props.wards" :key="ward.id" :value="ward.id">
                {{ ward.code }} - {{ ward.name }}
              </option>
            </select>
          </label>
        </div>
        <label
          ><span>{{ t('contact.address') }}</span
          ><input v-model="draft.address"
        /></label>
        <label
          ><span>{{ t('contact.description') }}</span
          ><textarea v-model="draft.description" rows="3" />
        </label>
        <label
          ><span>{{ t('contact.status') }}</span>
          <select v-model="draft.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
            <option value="CLOSED">{{ t('status.closed') }}</option>
          </select>
        </label>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="emit('close')">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="primary-button" :disabled="props.busy">
            {{ props.busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
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
.modal-card__header h2 {
  margin: 0;
  color: #173c59;
  font-size: 18px;
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
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
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
  font-size: 22px;
  cursor: pointer;
}
@media (max-width: 600px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
