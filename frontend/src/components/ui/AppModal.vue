<script setup lang="ts">
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'

import AppDialogHost from './AppDialogHost.vue'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    size?: 'sm' | 'md' | 'lg' | 'xl' | 'wide'
    closeOnEsc?: boolean
    maskClosable?: boolean
    showClose?: boolean
  }>(),
  { size: 'md', closeOnEsc: true, maskClosable: false, showClose: true },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  close: []
}>()

const { t } = useI18n()
const headingId = `ds-modal-${useId()}`

function requestClose(): void {
  emit('update:open', false)
  emit('close')
}
</script>

<template>
  <AppDialogHost
    :open="open"
    :size="size"
    :mask-closable="maskClosable"
    :close-on-esc="closeOnEsc"
    @close="requestClose"
  >
    <section
      class="ds-modal ds-dialog-surface"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="headingId"
    >
      <header class="ds-modal__header">
        <h2 :id="headingId" class="ds-modal__title">
          <span v-if="$slots.icon" class="ds-modal__title-icon" aria-hidden="true"><slot name="icon" /></span>
          {{ title }}
        </h2>
        <button
          v-if="showClose"
          type="button"
          class="modal-card-button-close"
          :aria-label="t('common.close')"
          :title="t('common.close')"
          @click="requestClose"
         data-ds-icon="close"></button>
      </header>
      <div class="ds-modal__body"><slot /></div>
      <footer v-if="$slots.footer" class="ds-modal__footer"><slot name="footer" /></footer>
    </section>
  </AppDialogHost>
</template>
