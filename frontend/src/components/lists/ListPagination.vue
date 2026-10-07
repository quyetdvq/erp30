<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(
  defineProps<{
    page: number
    pageSize: number
    total: number
    pageSizeOptions?: readonly number[]
    disabled?: boolean
  }>(),
  { pageSizeOptions: () => [50, 100, 200], disabled: false },
)

const emit = defineEmits<{
  'update:page': [value: number]
  'update:pageSize': [value: number]
}>()

const { t } = useI18n()
const pageCount = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)))
const currentPage = computed(() => Math.min(Math.max(1, props.page), pageCount.value))

function goTo(page: number): void {
  emit('update:page', Math.min(Math.max(1, page), pageCount.value))
}
</script>

<template>
  <nav class="list-pagination" :aria-label="t('list.pagination')">
    <div class="list-pagination__summary" aria-live="polite">
      {{ t('list.pageSummary', { page: currentPage, pages: pageCount, total: props.total }) }}
    </div>
    <label class="list-pagination__size">
      <span>{{ t('list.pageSize') }}</span>
      <select
        :value="String(props.pageSize)"
        :disabled="props.disabled"
        :aria-label="t('list.pageSize')"
        @change="emit('update:pageSize', Number(($event.target as HTMLSelectElement).value))"
      >
        <option v-for="size in props.pageSizeOptions" :key="size" :value="size">{{ size }}</option>
      </select>
    </label>
    <div class="list-pagination__actions">
      <button
        type="button"
        :disabled="props.disabled || currentPage === 1"
        :aria-label="t('list.first')"
        @click="goTo(1)"
      >
        «
      </button>
      <button
        type="button"
        :disabled="props.disabled || currentPage === 1"
        :aria-label="t('list.previous')"
        @click="goTo(currentPage - 1)"
      >
        ‹
      </button>
      <span aria-current="page">{{ currentPage }}</span>
      <button
        type="button"
        :disabled="props.disabled || currentPage === pageCount"
        :aria-label="t('list.next')"
        @click="goTo(currentPage + 1)"
      >
        ›
      </button>
      <button
        type="button"
        :disabled="props.disabled || currentPage === pageCount"
        :aria-label="t('list.last')"
        @click="goTo(pageCount)"
      >
        »
      </button>
    </div>
  </nav>
</template>

<style scoped>
.list-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  color: #536b7b;
  font-size: 13px;
}
.list-pagination__size {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.list-pagination__size span {
  font-size: 12px;
}
.list-pagination__size select {
  min-height: 30px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 4px 8px;
  background: #fff;
}
.list-pagination__actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.list-pagination__actions button {
  min-width: 30px;
  min-height: 30px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  background: #fff;
  color: #294b65;
  cursor: pointer;
}
.list-pagination__actions button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
.list-pagination__actions span {
  min-width: 30px;
  text-align: center;
  font-weight: 700;
}
</style>
