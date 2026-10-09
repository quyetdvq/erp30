<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'

import { usePresentationSettings } from '@/composables/usePresentationSettings'
import {
  formatEditableDecimal,
  parseDecimalInput,
  type DecimalInputKind,
} from '@/presentation/formatters'

const props = withDefaults(
  defineProps<{
    modelValue: string
    kind?: DecimalInputKind
    ariaLabel?: string
    placeholder?: string
    required?: boolean
    disabled?: boolean
    allowNegative?: boolean
    maxDecimals?: number
  }>(),
  {
    kind: 'number',
    ariaLabel: undefined,
    placeholder: undefined,
    required: false,
    disabled: false,
    allowNegative: false,
    maxDecimals: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const settings = usePresentationSettings()
const draft = ref('')
const focused = ref(false)
const runtime = computed(() => settings.runtime.value)

function displayValue(value: string): string {
  return value ? formatEditableDecimal(value, runtime.value, props.kind) : ''
}

function canonicalValue(value: string): string {
  return parseDecimalInput(value, runtime.value, props.kind, props.allowNegative, props.maxDecimals)
}

function decimalSeparator(): string {
  return props.kind === 'money'
    ? runtime.value.money.decimal_separator
    : runtime.value.number.decimal_separator
}

function hasTrailingDecimalSeparator(value: string): boolean {
  const configured = decimalSeparator()
  const alternate = configured === '.' ? ',' : '.'
  return value.endsWith(configured) || value.endsWith(alternate)
}

function formatWhileTyping(value: string): string {
  const canonical = canonicalValue(value)
  if (!canonical) return ''
  const formatted = formatEditableDecimal(canonical, runtime.value, props.kind)
  return hasTrailingDecimalSeparator(value) ? `${formatted}${decimalSeparator()}` : formatted
}

function syncFromModel(): void {
  if (!focused.value) draft.value = displayValue(props.modelValue)
}

watch(() => [props.modelValue, props.kind, runtime.value] as const, syncFromModel, {
  immediate: true,
})

function handleInput(event: Event): void {
  const input = event.target as HTMLInputElement
  const value = input.value
  const caret = input.selectionStart ?? value.length
  const formatted = formatWhileTyping(value)
  const caretValue = formatWhileTyping(value.slice(0, caret))
  draft.value = formatted
  const canonical = canonicalValue(value)
  if (canonical !== props.modelValue) emit('update:modelValue', canonical)
  void nextTick(() => {
    if (document.activeElement !== input) return
    const nextCaret = Math.min(caretValue.length, formatted.length)
    input.setSelectionRange(nextCaret, nextCaret)
  })
}

function handleFocus(): void {
  focused.value = true
  draft.value = displayValue(props.modelValue)
}

function handleBlur(): void {
  const canonical = canonicalValue(draft.value)
  focused.value = false
  if (canonical !== props.modelValue) emit('update:modelValue', canonical)
  draft.value = displayValue(canonical)
}
</script>

<template>
  <input
    :value="draft"
    type="text"
    inputmode="decimal"
    autocomplete="off"
    :aria-label="props.ariaLabel"
    :placeholder="props.placeholder"
    :required="props.required"
    :disabled="props.disabled"
    @focus="handleFocus"
    @input="handleInput"
    @blur="handleBlur"
  />
</template>
