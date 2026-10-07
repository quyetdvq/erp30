<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import type { ListFilterDefinition, ListQuery, ListSortOption } from './listTypes'

const props = withDefaults(
  defineProps<{
    modelValue: ListQuery
    filters?: readonly ListFilterDefinition[]
    sortOptions?: readonly ListSortOption[]
    pageSizeOptions?: readonly number[]
    disabled?: boolean
  }>(),
  {
    filters: () => [],
    sortOptions: () => [],
    pageSizeOptions: () => [50, 100, 200],
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
  <form class="list-query-controls" role="search" @submit.prevent="apply">
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
      <select
        v-if="definition.type === 'select' || definition.options"
        :value="valueFor(definition.key)"
        :multiple="definition.multiple"
        :disabled="props.disabled"
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
          {{ t(option.labelKey) }}
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
      <select v-model="sort" :disabled="props.disabled">
        <option value="">{{ t('list.defaultSort') }}</option>
        <option v-for="option in props.sortOptions" :key="option.value" :value="option.value">
          {{ t(option.labelKey) }}
        </option>
      </select>
    </label>
    <label class="list-query-controls__field list-query-controls__page-size">
      <span>{{ t('list.pageSize') }}</span>
      <select v-model="pageSize" :disabled="props.disabled">
        <option v-for="size in props.pageSizeOptions" :key="size" :value="size">{{ size }}</option>
      </select>
    </label>
    <div class="list-query-controls__actions">
      <button type="submit" :disabled="props.disabled">{{ t('list.apply') }}</button>
      <button type="button" :disabled="props.disabled" @click="reset">{{ t('list.reset') }}</button>
    </div>
    <slot name="filters" :filters="filters" :set-filter="setFilter" />
  </form>
</template>

<style scoped>
.list-query-controls {
  display: flex;
  align-items: end;
  gap: 10px;
  flex-wrap: wrap;
}
.list-query-controls__field {
  display: grid;
  gap: 4px;
  min-width: 140px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}
.list-query-controls__search {
  min-width: min(280px, 100%);
  flex: 1 1 220px;
}
.list-query-controls__field input,
.list-query-controls__field select {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 6px 8px;
  background: #fff;
  color: #183b57;
}
.list-query-controls__actions {
  display: flex;
  gap: 6px;
}
.list-query-controls__actions button {
  min-height: 34px;
  border: 1px solid #1876a8;
  border-radius: 4px;
  padding: 6px 12px;
  background: #1876a8;
  color: #fff;
  cursor: pointer;
}
.list-query-controls__actions button + button {
  border-color: #c7d6df;
  background: #fff;
  color: #294b65;
}
.list-query-controls__actions button:disabled,
.list-query-controls__field input:disabled,
.list-query-controls__field select:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}
</style>
