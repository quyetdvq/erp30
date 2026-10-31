<script setup lang="ts">
import { NModal } from 'naive-ui'
/**
 * The only overlay host for ERP dialogs, including legacy content migrated in DS-P2.
 * Naive UI owns the focus trap, focus restoration, stacking, Escape and scroll lock.
 * An overlay dismissal always delegates to the caller's existing close handler;
 * this component never changes business/unsaved/CAS state on its own.
 * Keep the modal layer below the single application notice layer (z-index 999).
 */
const props = withDefaults(
  defineProps<{
    open: boolean
    size?: 'sm' | 'md' | 'lg' | 'xl' | 'wide'
    closeOnEsc?: boolean
    maskClosable?: boolean
  }>(),
  { size: 'md', closeOnEsc: true, maskClosable: false },
)

const emit = defineEmits<{ close: [] }>()

function onUpdateShow(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <NModal
    :show="open"
    :z-index="900"
    :mask-closable="maskClosable"
    :close-on-esc="closeOnEsc"
    :trap-focus="true"
    :auto-focus="true"
    :block-scroll="true"
    @update:show="onUpdateShow"
  >
    <div class="ds-dialog-host" :class="`ds-dialog-host--${size}`">
      <slot />
    </div>
  </NModal>
</template>
