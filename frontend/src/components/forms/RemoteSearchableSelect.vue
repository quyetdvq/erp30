<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import { NSelect, type SelectOption } from 'naive-ui'
import { useAuthStore } from '@/stores/auth'
import { reportApiError } from '@/services/errorBoundary'

export type RemoteSelectOption = { value: string; label: string; disabled?: boolean }
const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    selectedOptions?: readonly RemoteSelectOption[]
    search: (q: string, page: number) => Promise<{ options: RemoteSelectOption[]; total: number }>
    ariaLabel?: string
    placeholder?: string
    disabled?: boolean
  }>(),
  {
    modelValue: null,
    selectedOptions: () => [],
    ariaLabel: undefined,
    placeholder: '',
    disabled: false,
  },
)
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const remoteOptions = ref<RemoteSelectOption[]>([])
const busy = ref(false)
const query = ref('')
const page = ref(0)
const total = ref(0)
let seq = 0
let debounce: ReturnType<typeof setTimeout> | undefined
const options = computed<SelectOption[]>(() => {
  const all = new Map<string, RemoteSelectOption>()
  for (const item of [...props.selectedOptions, ...remoteOptions.value]) all.set(item.value, item)
  return [...all.values()]
})
const hasMore = computed(() => remoteOptions.value.length < total.value)

async function fetchPage(nextQuery: string, nextPage: number): Promise<void> {
  const stamp = ++seq
  const generation = useAuthStore().getSessionGeneration()
  busy.value = true
  try {
    const response = await props.search(nextQuery, nextPage)
    if (stamp !== seq || generation !== useAuthStore().getSessionGeneration()) return
    query.value = nextQuery
    page.value = nextPage
    total.value = response.total
    const accumulated = nextPage === 1 ? [] : remoteOptions.value
    const byId = new Map(accumulated.map((item) => [item.value, item]))
    for (const item of response.options) byId.set(item.value, item)
    remoteOptions.value = [...byId.values()]
  } catch (error: unknown) {
    if (stamp === seq) reportApiError(error)
  } finally {
    if (stamp === seq) busy.value = false
  }
}
function onSearch(value: string): void {
  if (debounce) clearTimeout(debounce)
  // Invalidate an older response immediately rather than only after debounce.
  ++seq
  busy.value = false
  debounce = setTimeout(() => void fetchPage(value.trim(), 1), 250)
}
function onShow(show: boolean): void {
  if (show && page.value === 0) void fetchPage('', 1)
}
function onScroll(event: Event): void {
  const target = event.target as HTMLElement
  if (!hasMore.value || busy.value || page.value === 0) return
  if (target.scrollTop + target.clientHeight >= target.scrollHeight - 24) {
    void fetchPage(query.value, page.value + 1)
  }
}
onUnmounted(() => {
  ++seq
  if (debounce) clearTimeout(debounce)
})
</script>

<template>
  <NSelect
    :value="modelValue || null"
    :options="options"
    :placeholder="placeholder"
    :disabled="disabled"
    :filterable="true"
    :remote="true"
    :loading="busy"
    :clearable="true"
    :aria-label="ariaLabel"
    class="remote-searchable-select"
    @update:value="
      (value: string | number | null) =>
        emit('update:modelValue', value === null ? '' : String(value))
    "
    @search="onSearch"
    @update:show="onShow"
    @scroll="onScroll"
  />
</template>

<style scoped>
.remote-searchable-select {
  width: 100%;
  min-width: 0;
}
.remote-searchable-select :deep(.n-base-selection) {
  min-height: 34px;
}
</style>
