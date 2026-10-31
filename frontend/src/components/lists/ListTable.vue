<script setup lang="ts" generic="TRow extends object">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { useFormatters } from '@/composables/useFormatters'

import TableStatus from './TableStatus.vue'
import type { ListColumn } from './listTypes'

const props = withDefaults(
  defineProps<{
    rows: readonly TRow[]
    columns: readonly ListColumn<TRow>[]
    rowKey?: string | ((row: TRow, index: number) => string)
    sort?: string
    busy?: boolean
  }>(),
  { rowKey: 'id', sort: '', busy: false },
)

const emit = defineEmits<{
  'sort-change': [value: string]
}>()

const { t } = useI18n()
const formatters = useFormatters()

function labelFor(key: string): string {
  return t(key)
}

function valueFor(row: TRow, column: ListColumn<TRow>): unknown {
  return column.value ? column.value(row) : (row as Record<string, unknown>)[column.key]
}

function displayValue(row: TRow, column: ListColumn<TRow>): string {
  const raw = valueFor(row, column)
  if (raw === null || raw === undefined || raw === '') return t('list.noValue')
  const value = String(raw)
  switch (column.format) {
    case 'date':
      return formatters.formatDate(value)
    case 'datetime':
      return formatters.formatDateTime(value)
    case 'time':
      return formatters.formatTime(value)
    case 'quantity':
      return formatters.formatQuantity(value)
    case 'percentage':
      return formatters.formatPercentage(value)
    case 'money':
      return formatters.formatMoney(value, column.currencySymbol)
    default:
      return value
  }
}

function statusValue(row: TRow, column: ListColumn<TRow>): string | boolean | null {
  const raw = valueFor(row, column)
  return typeof raw === 'string' || typeof raw === 'boolean' ? raw : null
}

function alignmentFor(column: ListColumn<TRow>): 'start' | 'center' | 'end' {
  return (
    column.align ?? (column.key === 'actions' || column.key === 'row_actions' ? 'end' : 'start')
  )
}

function keyFor(row: TRow, index: number): string {
  const value =
    typeof props.rowKey === 'function'
      ? props.rowKey(row, index)
      : (row as Record<string, unknown>)[props.rowKey]
  return value === null || value === undefined ? String(index) : String(value)
}

function sortDirection(field: string): 'asc' | 'desc' | null {
  const term = props.sort.split(',').find((item) => item.replace(/^-/, '') === field)
  if (!term) return null
  return term.startsWith('-') ? 'desc' : 'asc'
}

function changeSort(column: ListColumn<TRow>): void {
  if (props.busy) return
  const field = column.sortField ?? column.key
  // Header clicks are single-column sorting, not implicit multi-sort. Backend
  // endpoints such as Customer accept one whitelisted field only. Never emit
  // comma-joined sorts, and never introduce a third "none" click that silently
  // falls back to the API's default sort and repeats an apparent direction.
  emit('sort-change', sortDirection(field) === 'asc' ? `-${field}` : field)
}

const ariaSort = computed(() => (column: ListColumn<TRow>) => {
  const direction = sortDirection(column.sortField ?? column.key)
  return direction === 'asc' ? 'ascending' : direction === 'desc' ? 'descending' : 'none'
})
</script>

<template>
  <div class="list-table-wrap" :aria-busy="props.busy">
    <table class="list-table">
      <caption class="sr-only">
        {{
          t('list.tableCaption')
        }}
      </caption>
      <thead>
        <tr>
          <th
            v-for="column in props.columns"
            :key="column.key"
            scope="col"
            :aria-sort="column.sortable ? ariaSort(column) : undefined"
            :style="{ textAlign: alignmentFor(column) }"
          >
            <button
              v-if="column.sortable"
              type="button"
              class="list-table__sort"
              :disabled="props.busy"
              :aria-label="t('list.sortBy', { field: labelFor(column.labelKey) })"
              @click="changeSort(column)"
            >
              {{ labelFor(column.labelKey) }}
            </button>
            <span v-else>{{ labelFor(column.labelKey) }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, rowIndex) in props.rows" :key="keyFor(row, rowIndex)">
          <td
            v-for="column in props.columns"
            :key="column.key"
            :style="{ textAlign: alignmentFor(column) }"
          >
            <slot :name="`cell-${column.key}`" :row="row" :value="valueFor(row, column)">
              <TableStatus
                v-if="column.key === 'status' && statusValue(row, column) !== null"
                :status="statusValue(row, column)!"
              />
              <template v-else>{{ displayValue(row, column) }}</template>
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
