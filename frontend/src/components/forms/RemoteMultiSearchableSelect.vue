<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import { NSelect, type SelectOption } from 'naive-ui'
import { useAuthStore } from '@/stores/auth'
import { reportApiError } from '@/services/errorBoundary'

/** Remote, paginated multi-User picker; never implies an authorization grant. */
export type RemoteMultiOption = { value: string; label: string; disabled?: boolean }
const props = withDefaults(defineProps<{
  modelValue: readonly string[]
  selectedOptions?: readonly RemoteMultiOption[]
  search: (q: string, page: number) => Promise<{ options: RemoteMultiOption[]; total: number }>
  ariaLabel: string
  placeholder?: string
  disabled?: boolean
  maxSelections?: number
  popupTarget?: string | HTMLElement | false
  emptyLabel?: string
  errorLabel?: string
  retryLabel?: string
}>(), {
  selectedOptions: () => [],
  placeholder: '',
  disabled: false,
  maxSelections: 25,
  popupTarget: false,
  emptyLabel: 'No matching active users',
  errorLabel: 'Unable to load users',
  retryLabel: 'Retry',
})
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()
const known = ref<RemoteMultiOption[]>([])
const loading = ref(false)
const page = ref(0)
const query = ref('')
const total = ref(0)
const loadedCount = ref(0)
const hasFetched = ref(false)
const searchFailed = ref(false)
let serial = 0
let debounce: ReturnType<typeof setTimeout> | undefined

const options = computed<SelectOption[]>(() => {
  const byId = new Map<string, RemoteMultiOption>()
  for (const option of [...props.selectedOptions, ...known.value]) byId.set(option.value, option)
  return [...byId.values()]
})
const hasMore = computed(() => loadedCount.value < total.value)

async function fetchPage(text: string, nextPage: number): Promise<void> {
  const currentSerial = ++serial
  const session = useAuthStore().getSessionGeneration()
  loading.value = true
  searchFailed.value = false
  try {
    const response = await props.search(text, nextPage)
    if (currentSerial !== serial || session !== useAuthStore().getSessionGeneration()) return
    query.value = text
    page.value = nextPage
    total.value = response.total
    hasFetched.value = true
    loadedCount.value = nextPage === 1 ? response.options.length : loadedCount.value + response.options.length
    const byId = new Map((nextPage === 1 ? [] : known.value).map(option => [option.value, option]))
    // Retain labels of selected items when searching a different page/query.
    for (const option of known.value.filter(option => props.modelValue.includes(option.value))) {
      byId.set(option.value, option)
    }
    for (const option of response.options) byId.set(option.value, option)
    known.value = [...byId.values()]
  } catch (error: unknown) {
    if (currentSerial === serial) {
      searchFailed.value = true
      hasFetched.value = true
      reportApiError(error)
    }
  } finally {
    if (currentSerial === serial) loading.value = false
  }
}
function onSearch(text: string): void {
  if (debounce) clearTimeout(debounce)
  ++serial
  query.value = text.trim()
  loading.value = false
  hasFetched.value = false
  searchFailed.value = false
  page.value = 0
  loadedCount.value = 0
  debounce = setTimeout(() => void fetchPage(query.value, 1), 250)
}
function onShow(show: boolean): void {
  if (show && page.value === 0 && !loading.value) {
    if (debounce) clearTimeout(debounce)
    void fetchPage(query.value, 1)
  }
}
function retry(): void {
  if (!loading.value && !props.disabled) void fetchPage(query.value, 1)
}
function onScroll(event: Event): void {
  const target = event.target as HTMLElement
  if (!hasMore.value || loading.value || page.value === 0) return
  if (target.scrollTop + target.clientHeight >= target.scrollHeight - 24) {
    void fetchPage(query.value, page.value + 1)
  }
}
function update(value: string | number | Array<string | number> | null): void {
  const next = Array.isArray(value) ? value.map(String) : []
  if (next.length > props.maxSelections || next.some(id => !/^[1-9][0-9]*$/.test(id))
      || new Set(next).size !== next.length) return
  emit('update:modelValue', next)
}
onUnmounted(() => { ++serial; if (debounce) clearTimeout(debounce) })
</script>

<template>
  <div class="remote-multi-select-field">
    <NSelect
    :value="[...props.modelValue]"
    :to="props.popupTarget ?? false"
    :options="options"
    :multiple="true"
    :filterable="true"
    :remote="true"
    :clearable="true"
    :loading="loading"
    :disabled="props.disabled"
    :placeholder="props.placeholder"
    :aria-label="props.ariaLabel"
    :max-tag-count="3"
    class="remote-multi-searchable-select"
    @update:value="update"
    @search="onSearch"
    @update:show="onShow"
    @scroll="onScroll"
    />
    <div v-if="searchFailed" class="remote-select-feedback" role="alert">
      <span>{{ props.errorLabel }}</span>
      <button type="button" class="secondary-button" :disabled="loading || props.disabled" @click="retry">
        {{ props.retryLabel }}
      </button>
    </div>
    <p v-else-if="hasFetched && !loading && total === 0" role="status" class="remote-select-feedback">
      {{ props.emptyLabel }}
    </p>
  </div>
</template>

<style scoped>
.remote-multi-select-field { display: grid; gap: 8px; width: 100%; min-width: 0; }
.remote-multi-searchable-select { display: block; width: 100%; min-width: 0; max-width: 100%; }
.remote-multi-searchable-select :deep(.n-base-selection) { min-height: 36px; min-width: 0; }
.remote-multi-select-field :deep(.n-base-selection-tags) { min-width: 0; }
.remote-select-feedback { margin: 0; display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
  font-size: 12px; color: #64748b; overflow-wrap: anywhere; }
.remote-select-feedback[role='alert'] { color: #a12727; }
</style>
