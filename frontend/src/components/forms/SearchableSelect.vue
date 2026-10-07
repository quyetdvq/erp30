<script setup lang="ts">
import { computed } from 'vue'
import { NSelect, type SelectOption } from 'naive-ui'

import { searchableSelectFilter } from './searchableSelect'

export type SearchableSelectOption = {
  value: string
  label: string
  disabled?: boolean
}

const props = withDefaults(
  defineProps<{
    modelValue?: string | readonly string[] | null
    options: readonly SearchableSelectOption[]
    placeholder?: string
    disabled?: boolean
    clearable?: boolean
    multiple?: boolean
    id?: string
    ariaLabel?: string
  }>(),
  {
    modelValue: null,
    placeholder: '',
    disabled: false,
    clearable: true,
    multiple: false,
    id: undefined,
    ariaLabel: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | string[]]
}>()

const options = computed<SelectOption[]>(() => props.options.map((option) => ({ ...option })))
const value = computed<string | string[] | null>(() => {
  if (props.multiple) {
    return Array.isArray(props.modelValue) ? [...props.modelValue] : []
  }
  return typeof props.modelValue === 'string' && props.modelValue.length > 0
    ? props.modelValue
    : null
})

function updateValue(next: string | number | Array<string | number> | null): void {
  if (props.multiple) {
    const values = Array.isArray(next) ? next.map(String) : []
    emit('update:modelValue', values)
    return
  }
  emit('update:modelValue', next === null ? '' : String(next))
}
</script>

<template>
  <NSelect
    :id="props.id"
    :value="value"
    :options="options"
    :placeholder="props.placeholder"
    :disabled="props.disabled"
    :clearable="props.clearable"
    :multiple="props.multiple"
    :filterable="true"
    :filter="searchableSelectFilter"
    :aria-label="props.ariaLabel"
    class="searchable-select"
    @update:value="updateValue"
  />
</template>

<style scoped>
.searchable-select {
  width: 100%;
  min-width: 0;
}

.searchable-select :deep(.n-base-selection) {
  min-height: 34px;
}
</style>
