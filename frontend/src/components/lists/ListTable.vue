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
  const field = column.sortField ?? column.key
  const direction = sortDirection(field)
  const nextDirection = direction === null ? 'asc' : direction === 'asc' ? 'desc' : null
  const terms = props.sort.split(',').filter((term) => term && term.replace(/^-/, '') !== field)
  if (nextDirection) terms.push(nextDirection === 'desc' ? `-${field}` : field)
  emit('sort-change', terms.join(','))
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
            :style="{ textAlign: column.align ?? 'start' }"
          >
            <button
              v-if="column.sortable"
              type="button"
              class="list-table__sort"
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
            :style="{ textAlign: column.align ?? 'start' }"
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

<style scoped>
.list-table-wrap {
  overflow-x: auto;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
}
.list-table {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
  color: #183b57;
  font-size: 13px;
}
.list-table th,
.list-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e8eef1;
  vertical-align: middle;
}
.list-table th {
  background: #f7fafb;
  color: #536b7b;
  font-size: 12px;
  font-weight: 700;
}
.list-table tbody tr:last-child td {
  border-bottom: 0;
}
.list-table__sort {
  border: 0;
  padding: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}
.list-table__sort:focus-visible {
  outline: 2px solid #1876a8;
  outline-offset: 2px;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
