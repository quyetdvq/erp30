<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import AppButton from './AppButton.vue'
import AppModal from './AppModal.vue'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    message: string
    confirmLabel?: string
    cancelLabel?: string
    danger?: boolean
    loading?: boolean
  }>(),
  { danger: false, loading: false },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: []
  cancel: []
}>()
const { t } = useI18n()

function cancel(): void {
  emit('update:open', false)
  emit('cancel')
}
</script>

<template>
  <AppModal
    :open="open"
    :title="title"
    size="sm"
    :mask-closable="false"
    :close-on-esc="!loading"
    :show-close="!loading"
    @close="cancel"
  >
    <p class="ds-confirm-dialog__message">{{ message }}</p>
    <template #footer>
      <AppButton icon="cancel" :disabled="loading" @click="cancel">{{ cancelLabel || t('common.cancel') }}</AppButton>
      <AppButton :icon="danger ? 'delete' : 'apply'" :variant="danger ? 'danger' : 'primary'" :loading="loading" @click="emit('confirm')">
        {{ confirmLabel || t('common.yes') }}
      </AppButton>
    </template>
  </AppModal>
</template>
