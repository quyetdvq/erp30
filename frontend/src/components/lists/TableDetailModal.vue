<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import { useI18n } from 'vue-i18n'
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

import TableStatus from './TableStatus.vue'

export type TableDetailField = {
  key: string
  label: string
  value?: string | null
  status?: string | boolean
}

const props = defineProps<{
  open: boolean
  title: string
  fields: readonly TableDetailField[]
}>()

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()
const dialog = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
let returnFocusTo: HTMLElement | null = null

function restoreFocus(): void {
  if (returnFocusTo?.isConnected) returnFocusTo.focus()
  returnFocusTo = null
}

watch(
  () => props.open,
  async (open, previousOpen) => {
    if (open) {
      returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null
      await nextTick()
      if (props.open) closeButton.value?.focus()
    } else if (previousOpen) {
      restoreFocus()
    }
  },
  { immediate: true, flush: 'post' },
)

onBeforeUnmount(restoreFocus)

</script>

<template>
  <AppDialogHost :open="props.open" size="sm" @close="emit('close')">
    <section v-if="props.open"
      ref="dialog"
      class="ds-dialog-surface table-detail-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="props.title"
    >
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24"> <EyeOutline /> </NIcon>
          {{ props.title }}
        </h2>

        <button
          type="button"
          ref="closeButton"
          class="modal-card-button-close"
          :aria-label="t('common.close')"
          :title="t('common.close')"
          @click="emit('close')"
         data-ds-icon="close"></button>
      </header>
      <div class="table-detail-modal__body">
        <dl class="table-detail-modal__fields">
          <template v-for="field in props.fields" :key="field.key">
            <dt>{{ field.label }}</dt>
            <dd>
              <TableStatus v-if="field.status !== undefined" :status="field.status" />
              <template v-else>{{ field.value || t('list.noValue') }}</template>
            </dd>
          </template>
        </dl>
      </div>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="emit('close')" data-ds-icon="close">

          {{ t('common.close') }}
        </button>
      </footer>
    </section>
  </AppDialogHost>
</template>

<style scoped>

.table-detail-modal__fields {
  display: grid;
  grid-template-columns: minmax(130px, 0.35fr) minmax(0, 1fr);
  gap: 0;
  margin: 0;
  padding: 8px 18px 18px;
}
.table-detail-modal__body {
  max-height: calc(100vh - 180px);
  overflow: auto;
}

.table-detail-modal__fields dt,
.table-detail-modal__fields dd {
  margin: 0;
  padding: 10px 0;
  border-bottom: 1px solid var(--ds-border-subtle);
}
.table-detail-modal__fields dt {
  color: var(--ds-text-secondary);
  font-size: 12px;
  font-weight: 700;
}
.table-detail-modal__fields dd {
  min-width: 0;
  color: var(--ds-text-primary);
  overflow-wrap: anywhere;
}
</style>
