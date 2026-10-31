<script setup lang="ts">
import type { AppButtonVariant, AppButtonSize, AppActionIconName } from './types'

withDefaults(
  defineProps<{
    variant?: AppButtonVariant
    size?: AppButtonSize
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    loading?: boolean
    iconOnly?: boolean
    icon?: AppActionIconName
  }>(),
  { variant: 'secondary', size: 'md', type: 'button', disabled: false, loading: false, iconOnly: false },
)
</script>

<template>
  <button
    class="ds-button"
    :class="[`ds-button--${variant}`, `ds-button--${size}`, { 'ds-button--icon-only': iconOnly, 'ds-button--icon-slot': Boolean($slots.icon) }]"
    :data-ds-icon="!loading && !$slots.icon ? icon : undefined"
    :type="type"
    :disabled="disabled || loading"
    :aria-busy="loading ? 'true' : undefined"
  >
    <span v-if="loading" class="ds-button__spinner" aria-hidden="true" />
    <span v-else-if="$slots.icon" class="ds-button__icon" aria-hidden="true"><slot name="icon" /></span>
    <span v-if="!iconOnly" class="ds-button__label"><slot /></span>
  </button>
</template>
