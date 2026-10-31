<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import type { ListFilterDefinition, ListQuery, ListSortOption } from './listTypes'
import { STANDARD_PAGE_SIZE_OPTIONS } from './listPageSizePolicy'

const props = withDefaults(
  defineProps<{
    modelValue: ListQuery
    filters?: readonly ListFilterDefinition[]
    sortOptions?: readonly ListSortOption[]
    pageSizeOptions?: readonly number[]
    showPageSize?: boolean
    extraFieldCount?: number
    disabled?: boolean
  }>(),
  {
    filters: () => [],
    sortOptions: () => [],
    pageSizeOptions: () => STANDARD_PAGE_SIZE_OPTIONS,
    showPageSize: true,
    extraFieldCount: 0,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: ListQuery]
  apply: [value: ListQuery]
  reset: []
}>()

const { t } = useI18n()
const search = ref(props.modelValue.q)
const sort = ref(props.modelValue.sort)
const pageSize = ref(String(props.modelValue.page_size))
const filters = ref<Record<string, string | readonly string[]>>({ ...props.modelValue.filter })

// Field counts are presentational only. Extra fields are supplied by named
// slots (Supplier Company, etc.); never alter the query or its API contract.
const extraFields = computed(() => Math.max(0, Math.trunc(props.extraFieldCount)))
const commonFields = computed(() =>
  props.filters.length + Number(props.sortOptions.length > 0) + Number(props.showPageSize),
)
const dense = computed(() => commonFields.value + extraFields.value > 5)
const desktopColumns = computed(() => {
  const columns = ['minmax(230px, 2.6fr)']
  for (let i = 0; i < commonFields.value; i += 1) columns.push('minmax(140px, 1fr)')
  for (let i = 0; i < extraFields.value; i += 1) columns.push('minmax(205px, 1.65fr)')
  return columns.join(' ')
})

watch(
  () => props.modelValue,
  (value) => {
    search.value = value.q
    sort.value = value.sort
    pageSize.value = String(value.page_size)
    filters.value = { ...value.filter }
  },
  { deep: true },
)

function valueFor(key: string): string {
  const value = filters.value[key]
  return typeof value === 'string' ? value : String(value?.[0] ?? '')
}

function valuesFor(key: string): string[] {
  const value = filters.value[key]
  return Array.isArray(value) ? [...value] : typeof value === 'string' && value ? [value] : []
}

function selectedFor(key: string, value: string): boolean {
  const current = filters.value[key]
  return Array.isArray(current) ? current.includes(value) : current === value
}

function setFilter(key: string, value: string | readonly string[]): void {
  const hasValue = Array.isArray(value) ? value.length > 0 : value.length > 0
  if (hasValue) filters.value = { ...filters.value, [key]: value }
  else {
    const next = { ...filters.value }
    delete next[key]
    filters.value = next
  }
}

function optionLabel(option: { label?: string; labelKey: string }): string {
  return option.label ?? t(option.labelKey)
}

function searchable(definition: ListFilterDefinition): boolean {
  return (
    (definition.type === 'select' || Boolean(definition.options)) &&
    (definition.searchable ?? (definition.options?.length ?? 0) >= 8)
  )
}

function buildQuery(): ListQuery {
  return {
    ...props.modelValue,
    q: search.value.trim(),
    page: 1,
    page_size: Number(pageSize.value),
    sort: sort.value,
    filter: { ...filters.value },
  }
}

function apply(): void {
  const value = buildQuery()
  emit('update:modelValue', value)
  emit('apply', value)
}

function reset(): void {
  search.value = ''
  sort.value = ''
  filters.value = {}
  emit('reset')
}
</script>

<template>
  <form class="list-query-controls" :class="{ 'list-query-controls--dense': dense }" role="search" @submit.prevent="apply">
    <div class="list-query-controls__grid" :style="{ '--ds-query-columns': desktopColumns }">
      <label class="list-query-controls__field list-query-controls__search">
        <span>{{ t('list.search') }}</span>
        <input
          v-model="search"
          type="search"
          :disabled="props.disabled"
          :aria-label="t('list.search')"
        />
      </label>
      <label
        v-for="definition in props.filters"
        :key="definition.key"
        class="list-query-controls__field"
      >
        <span>{{ t(definition.labelKey) }}</span>
        <SearchableSelect
          v-if="searchable(definition)"
          :model-value="definition.multiple ? valuesFor(definition.key) : valueFor(definition.key)"
          :options="
            (definition.options ?? []).map((option) => ({
              value: option.value,
              label: optionLabel(option),
            }))
          "
          :multiple="definition.multiple"
          :placeholder="t('list.all')"
          :disabled="props.disabled"
          :aria-label="t(definition.labelKey)"
          @update:model-value="(value) => setFilter(definition.key, value)"
        />
        <select
          v-else-if="definition.type === 'select' || definition.options"
          :value="valueFor(definition.key)"
          :multiple="definition.multiple"
          :disabled="props.disabled"
          :aria-label="t(definition.labelKey)"
          @change="
            setFilter(
              definition.key,
              definition.multiple
                ? Array.from(($event.target as HTMLSelectElement).selectedOptions).map(
                    (option) => option.value,
                  )
                : ($event.target as HTMLSelectElement).value,
            )
          "
        >
          <option value="">{{ t('list.all') }}</option>
          <option
            v-for="option in definition.options ?? []"
            :key="option.value"
            :value="option.value"
            :selected="definition.multiple && selectedFor(definition.key, option.value)"
          >
            {{ optionLabel(option) }}
          </option>
        </select>
        <input
          v-else
          :value="valueFor(definition.key)"
          :disabled="props.disabled"
          @input="setFilter(definition.key, ($event.target as HTMLInputElement).value)"
        />
      </label>
      <label v-if="props.sortOptions.length" class="list-query-controls__field">
        <span>{{ t('list.sort') }}</span>
        <select v-model="sort" :disabled="props.disabled" :aria-label="t('list.sort')">
          <option value="">{{ t('list.defaultSort') }}</option>
          <option v-for="option in props.sortOptions" :key="option.value" :value="option.value">
            {{ t(option.labelKey) }}
          </option>
        </select>
      </label>
      <label
        v-if="props.showPageSize"
        class="list-query-controls__field list-query-controls__page-size"
      >
        <span>{{ t('list.pageSize') }}</span>
        <select v-model="pageSize" :disabled="props.disabled" :aria-label="t('list.pageSize')">
          <option v-for="size in props.pageSizeOptions" :key="size" :value="size">{{ size }}</option>
        </select>
      </label>
      <!-- Put feature-supplied fields in the same filter row, before actions. -->
      <slot name="filters" :filters="filters" :set-filter="setFilter" />
    </div>
    <div class="list-query-controls__actions">
      <button type="submit" class="ds-button ds-button--primary" data-ds-icon="apply" :disabled="props.disabled">{{ t('list.apply') }}</button>
      <button type="button" class="ds-button ds-button--secondary" data-ds-icon="reset" :disabled="props.disabled" @click="reset">{{ t('list.reset') }}</button>
      <slot name="actions" />
    </div>
  </form>
</template>
