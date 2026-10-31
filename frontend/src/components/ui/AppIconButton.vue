<script setup lang="ts">
import AppButton from './AppButton.vue'
import type { AppButtonSize, AppButtonVariant } from './types'

withDefaults(
  defineProps<{
    label: string
    title?: string
    variant?: AppButtonVariant
    size?: AppButtonSize
    disabled?: boolean
    loading?: boolean
    type?: 'button' | 'submit' | 'reset'
  }>(),
  { variant: 'ghost', size: 'md', disabled: false, loading: false, type: 'button' },
)
</script>

<template>
  <AppButton
    icon-only
    :type="type"
    :size="size"
    :variant="variant"
    :disabled="disabled"
    :loading="loading"
    :aria-label="label"
    :title="title || label"
  >
    <template #icon><slot /></template>
  </AppButton>
</template>
