<script setup lang="ts">
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

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    emit('close')
    return
  }
  if (event.key !== 'Tab' || !dialog.value) return
  const focusable = [
    ...dialog.value.querySelectorAll<HTMLElement>(
      'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
    ),
  ]
  if (focusable.length === 0) {
    event.preventDefault()
    return
  }
  const first = focusable[0]!
  const last = focusable[focusable.length - 1]!
  if (
    event.shiftKey &&
    (document.activeElement === first || !dialog.value.contains(document.activeElement))
  ) {
    event.preventDefault()
    last.focus()
  } else if (
    !event.shiftKey &&
    (document.activeElement === last || !dialog.value.contains(document.activeElement))
  ) {
    event.preventDefault()
    first.focus()
  }
}
</script>

<template>
  <div v-if="props.open" class="modal-backdrop">
    <section
      ref="dialog"
      class="table-detail-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="props.title"
      @keydown="onKeydown"
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
        ></button>
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
        <button type="button" class="secondary-button" @click="emit('close')">
          <NIcon :size="20"> <Refresh /> </NIcon>
          {{ t('common.close') }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.table-detail-modal {
  width: min(100%, 560px);
  max-height: calc(100vh - 40px);
  overflow: auto;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}

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
  border-bottom: 1px solid #edf2f4;
}
.table-detail-modal__fields dt {
  color: #536b7b;
  font-size: 12px;
  font-weight: 700;
}
.table-detail-modal__fields dd {
  min-width: 0;
  color: #183b57;
  overflow-wrap: anywhere;
}
</style>
